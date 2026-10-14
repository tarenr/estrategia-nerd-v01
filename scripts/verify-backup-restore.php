<?php
/**
 * -----------------------------------------------------------------------------
 * @file        scripts/verify-backup-restore.php
 * @project     Estrategia Nerd
 * @purpose     CLI: prova que um backup sistemico pode ser restaurado, SEM tocar nos ambientes reais.
 *              1) confere os SHA-256 do manifesto (BackupManager::verify);
 *              2) le todas as entradas de system-files.zip e uploads.zip conferindo o CRC de cada arquivo;
 *              3) importa database.sql em um banco TEMPORARIO (en_restore_check_*), confere tabelas e linhas
 *                 e apaga esse banco temporario ao final (mesmo em caso de falha).
 * @usage       C:\xampp\php\php.exe scripts/verify-backup-restore.php <local|stage|production> [BACKUP_ID] [--skip-uploads]
 * @notes       Nunca usa o restore oficial (que sobrescreve o ambiente). O relatorio fica em
 *              storage/logs/backup-restore-check/. Ver docs/governanca/documentacao-central.md e
 *              a secao Backup da Documentacao Tecnica.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once __DIR__ . '/backup/EnvLoader.php';
require_once __DIR__ . '/backup/BackupManager.php';
require_once dirname(__DIR__) . '/app/Support/Helpers.php';
require_once dirname(__DIR__) . '/app/Support/EnvironmentManager.php';
require_once dirname(__DIR__) . '/app/Support/EnvironmentGuard.php';

use App\Support\EnvironmentGuard;
use Scripts\Backup\BackupManager;
use Scripts\Backup\EnvLoader;

EnvLoader::load(dirname(__DIR__) . '/.env');
EnvironmentGuard::requireLocal();

const TEMP_DB_PREFIX = 'en_restore_check_';
/** Tabelas que precisam ter linhas em qualquer backup valido do portal. */
const REQUIRED_NON_EMPTY = ['posts', 'categorias', 'usuarios'];

$profileArg = strtolower(trim((string) ($argv[1] ?? '')));
$args = array_slice($argv, 2);
$skipUploads = in_array('--skip-uploads', $args, true);
$backupIdArg = '';
foreach ($args as $arg) {
    if (!str_starts_with($arg, '--')) {
        $backupIdArg = trim($arg);
    }
}

if (!in_array($profileArg, ['local', 'stage', 'production'], true)) {
    fwrite(STDERR, "Uso: php scripts/verify-backup-restore.php <local|stage|production> [BACKUP_ID] [--skip-uploads]\n");
    exit(2);
}

$config = require dirname(__DIR__) . '/config/backup.php';
$manager = new BackupManager($config);
$report = ['profile' => $profileArg, 'started_at' => date('c'), 'checks' => [], 'ok' => false];

/** @param array<string,mixed> $report */
function check(array &$report, string $name, bool $ok, string $detail): void
{
    $report['checks'][] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
    echo ($ok ? '[OK]   ' : '[FALHA] ') . $name . ' - ' . $detail . PHP_EOL;
}

/**
 * Le cada entrada do zip e confere o CRC32 calculado com o registrado no arquivo.
 *
 * @return array{entries:int, bad:list<string>}
 */
function verifyZipCrc(string $zipPath): array
{
    $zip = new ZipArchive();
    $open = $zip->open($zipPath, ZipArchive::CHECKCONS);
    if ($open !== true) {
        throw new RuntimeException('Zip invalido ou inconsistente (codigo ' . (string) $open . '): ' . basename($zipPath));
    }
    $bad = [];
    $entries = 0;
    try {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                $bad[] = '#' . $i;
                continue;
            }
            $name = (string) $stat['name'];
            // Pastas vazias: o ZipFile do .NET (usado pelo BackupManager) grava com barra invertida no fim.
            if (str_ends_with($name, '/') || str_ends_with($name, '\\')) {
                continue;
            }
            $entries++;
            $stream = $zip->getStream($name);
            if ($stream === false) {
                $bad[] = $name;
                continue;
            }
            $hash = hash_init('crc32b');
            while (!feof($stream)) {
                $chunk = fread($stream, 1048576);
                if ($chunk === false) {
                    break;
                }
                hash_update($hash, $chunk);
            }
            fclose($stream);
            $expected = sprintf('%08x', ((int) $stat['crc']) & 0xFFFFFFFF);
            if (hash_final($hash) !== $expected) {
                $bad[] = $name;
            }
        }
    } finally {
        $zip->close();
    }

    return ['entries' => $entries, 'bad' => $bad];
}

/**
 * Executa o cliente mysql com credenciais em arquivo temporario (nunca na linha de comando).
 *
 * @param array<string,mixed> $db
 * @return list<string>
 */
function mysqlRun(string $binary, array $db, string $database, string $sql): array
{
    $defaults = tempnam(sys_get_temp_dir(), 'en-rc-');
    if ($defaults === false) {
        throw new RuntimeException('Nao foi possivel criar o arquivo temporario do mysql.');
    }
    $esc = static fn (string $v): string => str_replace(['\\', '"'], ['\\\\', '\\"'], $v);
    file_put_contents($defaults, implode(PHP_EOL, [
        '[client]',
        'host="' . $esc((string) $db['host']) . '"',
        'port="' . $esc((string) $db['port']) . '"',
        'user="' . $esc((string) $db['username']) . '"',
        'password="' . $esc((string) ($db['password'] ?? '')) . '"',
        '',
    ]));
    $command = sprintf(
        '%s --defaults-extra-file=%s --default-character-set=utf8mb4 --batch --skip-column-names %s --execute=%s 2>&1',
        escapeshellarg($binary),
        escapeshellarg($defaults),
        $database !== '' ? escapeshellarg($database) : '',
        escapeshellarg($sql)
    );
    exec($command, $output, $code);
    @unlink($defaults);
    if ($code !== 0) {
        throw new RuntimeException('mysql retornou erro: ' . trim(implode(' ', $output)));
    }

    return $output;
}

$tempDb = '';
$mysqlBinary = (string) ($config['mysql_binary'] ?? '');
$localDb = (array) ($config['profiles']['local']['database'] ?? []);

try {
    // 1. Localizar o backup do perfil pedido
    $backupId = $backupIdArg;
    if ($backupId === '') {
        $status = $manager->status(false);
        foreach ((array) ($status['items'] ?? []) as $item) {
            if (strtolower((string) ($item['profile'] ?? '')) === $profileArg) {
                $backupId = (string) ($item['backup_id'] ?? '');
                break;
            }
        }
    }
    $backup = $backupId !== '' ? $manager->findBackup($backupId) : null;
    if ($backup === null || strtolower((string) ($backup['profile'] ?? '')) !== $profileArg) {
        throw new RuntimeException('Nenhum backup do perfil ' . $profileArg . ' encontrado.');
    }
    $report['backup_id'] = (string) $backup['backup_id'];
    $dir = (string) $backup['_dir'];
    echo 'Backup: ' . $report['backup_id'] . ' (' . $dir . ')' . PHP_EOL;

    // 2. Checksums do manifesto (mesma rotina do botao "Verificar ultimo")
    $verified = $manager->verify($report['backup_id']);
    check($report, 'Checksums do manifesto', (bool) ($verified['is_valid'] ?? false), 'database.sql, uploads.zip e system-files.zip conferidos por SHA-256');

    // 3. Zips: consistencia + CRC de cada entrada
    $systemZip = $dir . DIRECTORY_SEPARATOR . (string) ($backup['system_files']['name'] ?? 'system-files.zip');
    $sys = verifyZipCrc($systemZip);
    $expectedCount = (int) ($backup['system_files_count'] ?? 0);
    check($report, 'system-files.zip', $sys['bad'] === [] && ($expectedCount === 0 || $sys['entries'] === $expectedCount),
        sprintf('%d arquivos lidos (manifesto: %d), %d com CRC divergente', $sys['entries'], $expectedCount, count($sys['bad'])));

    if (($backup['includes_uploads'] ?? false) && !$skipUploads) {
        $uploadsZip = $dir . DIRECTORY_SEPARATOR . (string) ($backup['uploads']['name'] ?? 'uploads.zip');
        $up = verifyZipCrc($uploadsZip);
        check($report, 'uploads.zip', $up['bad'] === [] && $up['entries'] > 0, sprintf('%d arquivos lidos, %d com CRC divergente', $up['entries'], count($up['bad'])));
    } else {
        check($report, 'uploads.zip', true, $skipUploads ? 'ignorado por --skip-uploads' : 'backup gerado sem uploads');
    }

    // 4. Banco: importar em um banco temporario e conferir
    $sqlPath = $dir . DIRECTORY_SEPARATOR . (string) ($backup['database']['name'] ?? 'database.sql');
    $sql = (string) file_get_contents($sqlPath);
    if (preg_match('/^\s*(USE|CREATE\s+DATABASE|DROP\s+DATABASE)\b/im', $sql) === 1) {
        throw new RuntimeException('database.sql contem USE/CREATE/DROP DATABASE; importacao isolada recusada para nao atingir um banco real.');
    }
    $expectedTables = preg_match_all('/^CREATE TABLE `/m', $sql);
    unset($sql);
    if ($mysqlBinary === '' || !is_file($mysqlBinary)) {
        throw new RuntimeException('mysql.exe nao encontrado. Ajuste BACKUP_MYSQL_BINARY.');
    }

    $tempDb = TEMP_DB_PREFIX . date('Ymd_His');
    mysqlRun($mysqlBinary, $localDb, '', 'CREATE DATABASE `' . $tempDb . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    mysqlRun($mysqlBinary, $localDb, $tempDb, 'source ' . str_replace('\\', '/', $sqlPath));
    $tables = mysqlRun($mysqlBinary, $localDb, '', "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='" . $tempDb . "' AND TABLE_TYPE='BASE TABLE'");
    $views = mysqlRun($mysqlBinary, $localDb, '', "SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA='" . $tempDb . "'");
    $restoredObjects = count($tables) + (int) ($views[0] ?? 0);
    check($report, 'Importacao do banco', $restoredObjects === $expectedTables, sprintf('%d tabelas/views restauradas de %d no dump', $restoredObjects, $expectedTables));

    $counts = [];
    foreach ($tables as $table) {
        $row = mysqlRun($mysqlBinary, $localDb, $tempDb, 'SELECT COUNT(*) FROM `' . str_replace('`', '', $table) . '`');
        $counts[$table] = (int) ($row[0] ?? 0);
    }
    $report['row_counts'] = $counts;
    $missing = array_values(array_filter(REQUIRED_NON_EMPTY, static fn (string $t): bool => ($counts[$t] ?? 0) === 0));
    check($report, 'Dados essenciais', $missing === [], $missing === []
        ? sprintf('posts=%d, categorias=%d, usuarios=%d', $counts['posts'] ?? 0, $counts['categorias'] ?? 0, $counts['usuarios'] ?? 0)
        : 'tabelas vazias ou ausentes: ' . implode(', ', $missing));
} catch (Throwable $e) {
    check($report, 'Execucao', false, $e->getMessage());
} finally {
    if ($tempDb !== '' && str_starts_with($tempDb, TEMP_DB_PREFIX)) {
        try {
            mysqlRun($mysqlBinary, $localDb, '', 'DROP DATABASE IF EXISTS `' . $tempDb . '`');
            echo 'Banco temporario removido: ' . $tempDb . PHP_EOL;
        } catch (Throwable $e) {
            echo '[ATENCAO] Nao foi possivel remover o banco temporario ' . $tempDb . ': ' . $e->getMessage() . PHP_EOL;
        }
    }
}

$report['ok'] = $report['checks'] !== [] && !in_array(false, array_column($report['checks'], 'ok'), true);
$report['finished_at'] = date('c');
$logDir = dirname(__DIR__) . '/storage/logs/backup-restore-check';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/' . ($report['backup_id'] ?? $profileArg) . '-' . date('Ymd-His') . '.json';
file_put_contents($logFile, (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo PHP_EOL . ($report['ok'] ? 'RESULTADO: backup restauravel.' : 'RESULTADO: backup com problemas, ver itens acima.') . PHP_EOL;
echo 'Relatorio: ' . $logFile . PHP_EOL;
exit($report['ok'] ? 0 : 1);
