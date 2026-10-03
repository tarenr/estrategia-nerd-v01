<?php

declare(strict_types=1);

use App\Services\Instagram\EditorialMotionReelRenderer;
use App\Services\Instagram\MotionReelBatchService;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
date_default_timezone_set('America/Sao_Paulo');
$root = dirname(__DIR__);
require_once $root . '/app/Support/Helpers.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) {
        $path = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) { require_once $path; }
    }
});
$options = getopt('', ['dry-run', 'inventory', 'pilots', 'prepare', 'validate', 'apply', 'rollback',
    'manifest:', 'approved-manifest:', 'approved-pilots:', 'ffmpeg:', 'ffprobe:', 'help']);
$modes = array_intersect(['dry-run', 'inventory', 'pilots', 'prepare', 'validate', 'apply', 'rollback'], array_keys($options));
if (isset($options['help']) || $options === []) {
    echo "Reels locais por categoria; nunca publica nem escreve na origem dos artigos.\n"
        . "--dry-run: inventario estritamente leitura, stdout; --inventory: grava snapshot/manifesto\n"
        . "--pilots: gera quatro pilotos reais, sem modificar registros\n"
        . "--prepare --manifest=CAMINHO --approved-pilots=HASH: gera/retoma lote apos avaliacao\n"
        . "--validate --manifest=CAMINHO: verifica fontes/MP4s e emite hash para aprovacao\n"
        . "--apply --manifest=CAMINHO --approved-manifest=HASH: troca referencias locais\n"
        . "--rollback --manifest=CAMINHO --approved-manifest=HASH: recupera referencias sob guarda\n";
    exit(0);
}
$lock = null;
$local = null;
$production = null;
try {
    if (count($modes) !== 1) { throw new RuntimeException('Escolha exatamente um modo.'); }
    $mode = array_values($modes)[0];
    foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (preg_match('/^((?:DB_|CONTENT_SYNC_PRODUCTION_DB_|BACKUP_PRODUCTION_DB_)[A-Z_]+)=(.*)$/', trim($line), $m)) {
            $_ENV[$m[1]] = trim($m[2], " \t\"'");
        }
    }
    $config = require $root . '/config/content-sync.php';
    $connect = static function (array $db): PDO {
        return new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']),
            $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 6]);
    };
    $localConfig = $config['profiles']['local']['database'];
    if (!in_array($localConfig['host'], ['localhost', '127.0.0.1', '::1'], true)) { throw new RuntimeException('Conexao Instagram deve ser local.'); }
    $local = $connect($localConfig);
    $renderer = new EditorialMotionReelRenderer((string) ($options['ffmpeg'] ?? 'ffmpeg'), (string) ($options['ffprobe'] ?? 'ffprobe'));
    $batch = new MotionReelBatchService($local, $renderer, $root);
    if (in_array($mode, ['dry-run', 'inventory', 'pilots'], true)) {
        $local->exec('SET TRANSACTION READ ONLY');
        $local->beginTransaction();
        $production = $connect($config['profiles']['production']['database']);
        $production->exec('SET TRANSACTION READ ONLY');
        $production->beginTransaction();
        $items = $batch->inventory($production);
        $production->rollBack();
        $local->rollBack();
        $report = array_map(static fn (array $i): array => ['reel' => $i['id'], 'state' => $i['state'],
            'artigo' => $i['article']['id'] ?? null, 'categoria' => $i['article']['categoria'] ?? null,
            'template' => $i['template'] ?? null, 'capa' => $i['article']['imagem_capa'] ?? null,
            'trilha' => $i['track']['arquivo_path'] ?? null, 'inicio' => $i['audio_start'] ?? null,
            'duracao' => $i['duration'] ?? null, 'motivo' => $i['reason'] ?? null], $items);
        if ($mode === 'dry-run') { echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n"; }
        else { echo 'INVENTARIO: ' . count($items) . ' itens, ' . count(array_filter($items, static fn (array $i): bool => $i['state'] === 'inventoried')) . " elegiveis\n"; }
        if ($mode === 'dry-run') { exit(0); }
        $run = 'batch-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $dir = $root . '/storage/' . ($mode === 'pilots' ? 'previews' : 'backups') . '/reels-motion/' . $run;
        if (!mkdir($dir, 0755, true)) { throw new RuntimeException('Nao foi possivel criar a pasta do lote.'); }
        if ($mode === 'pilots') {
            $selected = [];
            foreach ($items as $item) {
                if ($item['state'] !== 'inventoried') { continue; }
                $key = $item['template'];
                // Pilota o titulo mais longo de cada familia para exercitar layout.
                if (!isset($selected[$key]) || mb_strlen($item['article']['titulo']) > mb_strlen($selected[$key]['article']['titulo'])) { $selected[$key] = $item; }
            }
            if (count($selected) !== 4) { throw new RuntimeException('Quatro categorias elegiveis necessarias para os pilotos.'); }
            foreach ($selected as $key => $item) {
                $articlePath = $dir . '/' . $key . '.json';
                MotionReelBatchService::save($articlePath, $item['article']);
                $pipes = [];
                $args = [PHP_BINARY, $root . '/scripts/en-instagram-preview-motion-reel.php', '--article=' . $articlePath,
                    '--audio=' . $batch->asset($item['track']['arquivo_path']), '--audio-start=' . $item['audio_start'],
                    '--ffmpeg=' . (string) ($options['ffmpeg'] ?? 'ffmpeg'), '--ffprobe=' . (string) ($options['ffprobe'] ?? 'ffprobe')];
                $proc = proc_open($args, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $dir . '/' . $key . '-preview.log', 'a']], $pipes);
                if (!is_resource($proc)) { throw new RuntimeException('Falha ao iniciar previa.'); }
                fclose($pipes[0]);
                $output = (string) stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                if (proc_close($proc) !== 0 || !preg_match('~PILOTO PRONTO: (.+)/pilot\.mp4~', $output, $match)) {
                    throw new RuntimeException('Falha no piloto ' . $key . '; consulte o log da previa.');
                }
                $item['pilot'] = str_replace('\\', '/', trim($match[1])) . '/pilot.mp4';
                $item['pilot_sha256'] = hash_file('sha256', $item['pilot']);
                $selected[$key] = $item;
                echo $key . ': ' . $output;
            }
            MotionReelBatchService::save($dir . '/pilots.json', ['renderer' => EditorialMotionReelRenderer::VERSION,
                'renderer_sha256' => hash_file('sha256', $root . '/app/Services/Instagram/EditorialMotionReelRenderer.php'),
                'config_sha256' => hash_file('sha256', $root . '/config/instagram-motion-templates.php'), 'items' => $selected]);
            echo 'PILOTS MANIFEST: ' . $dir . '/pilots.json' . "\nHASH: " . hash_file('sha256', $dir . '/pilots.json') . "\n";
            exit(0);
        }
        $manifest = ['run' => $run, 'renderer' => EditorialMotionReelRenderer::VERSION,
            'renderer_sha256' => hash_file('sha256', $root . '/app/Services/Instagram/EditorialMotionReelRenderer.php'),
            'config_sha256' => hash_file('sha256', $root . '/config/instagram-motion-templates.php'), 'items' => $items];
        MotionReelBatchService::save($dir . '/manifest.json', $manifest);
        echo 'MANIFEST: ' . $dir . '/manifest.json' . "\n";
        exit(0);
    }
    $path = realpath((string) ($options['manifest'] ?? ''));
    $base = realpath($root . '/storage/backups/reels-motion');
    if ($path === false || $base === false || basename($path) !== 'manifest.json'
        || !str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $base) . '/')) {
        throw new RuntimeException('Use um manifesto gerado pelo modo inventory em storage/backups/reels-motion.');
    }
    $lock = fopen(dirname($path) . '/batch.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) { throw new RuntimeException('Outra execucao deste lote esta ativa.'); }
    $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($manifest)) { throw new RuntimeException('Manifesto invalido.'); }
    $batch->assertVersion($manifest);
    if (in_array($mode, ['prepare', 'validate', 'apply'], true)) {
        $production = $connect($config['profiles']['production']['database']);
        $production->exec('SET SESSION TRANSACTION READ ONLY');
    }
    if ($mode === 'prepare') {
        $approved = (string) ($options['approved-pilots'] ?? '');
        $pilotFiles = glob($root . '/storage/previews/reels-motion/batch-*/pilots.json') ?: [];
        $found = false;
        foreach ($pilotFiles as $pilotFile) {
            if (!hash_equals((string) hash_file('sha256', $pilotFile), $approved)) { continue; }
            $pilots = json_decode((string) file_get_contents($pilotFile), true, 512, JSON_THROW_ON_ERROR);
            $batch->assertVersion($pilots);
            foreach ($pilots['items'] as $pilot) {
                if (hash_file('sha256', $pilot['pilot']) !== $pilot['pilot_sha256']) { throw new RuntimeException('Piloto aprovado mudou.'); }
            }
            $found = true;
        }
        if (!$found) { throw new RuntimeException('Informe o hash dos quatro pilotos avaliados e aprovados.'); }
        foreach ($manifest['items'] as &$item) {
            if ($item['state'] === 'skipped') { continue; }
            try { $batch->assertArticle($production, $item); $item = $batch->prepareItem($item, $manifest['run']); unset($item['reason']); }
            catch (RuntimeException $e) { $item['reason'] = $e->getMessage(); }
            MotionReelBatchService::save($path, $manifest);
            echo 'Reel ' . $item['id'] . ': ' . $item['state'] . ' ' . ($item['reason'] ?? '') . "\n";
        }
        unset($item);
    } elseif ($mode === 'validate') {
        foreach ($manifest['items'] as $item) {
            if ($item['state'] !== 'ready') { echo 'PENDENTE ' . $item['id'] . ': ' . ($item['reason'] ?? $item['state']) . "\n"; continue; }
            $batch->assertArticle($production, $item);
            $batch->prepareItem($item, $manifest['run']);
            echo 'VALIDADO ' . $item['id'] . ' | ' . $item['template'] . ' | ' . $item['video'] . "\n";
        }
        echo 'HASH PARA AVALIACAO: ' . hash_file('sha256', $path) . "\n";
    } else {
        if (!hash_equals((string) hash_file('sha256', $path), (string) ($options['approved-manifest'] ?? ''))) {
            throw new RuntimeException('Hash nao corresponde ao manifesto avaliado.');
        }
        $journalPath = dirname($path) . '/application.json';
        $journal = is_file($journalPath) ? json_decode((string) file_get_contents($journalPath), true, 512, JSON_THROW_ON_ERROR) : [];
        if ($mode === 'apply') {
            foreach ($manifest['items'] as $item) {
                if ($item['state'] !== 'ready') { continue; }
                $key = (string) $item['id'];
                if (isset($journal[$key])) {
                    $current = $batch->snapshot((int) $item['id']);
                    if ($current === $journal[$key]['after']) { echo "JA APLICADO {$key}\n"; continue; }
                    if ($current !== $item['before']) { echo "PRESERVADO {$key}: estado mudou\n"; continue; }
                }
                $item['apply_time'] = date('Y-m-d H:i:s');
                $journal[$key] = ['before' => $item['before'], 'after' => $batch->expectedAfter($item), 'state' => 'intent'];
                MotionReelBatchService::save($journalPath, $journal); // Backup duravel ANTES do commit SQL.
                try {
                    $batch->assertArticle($production, $item);
                    $journal[$key]['after'] = $batch->applyItem($item);
                    $journal[$key]['state'] = 'applied';
                    echo "APLICADO {$key}\n";
                } catch (RuntimeException $e) { $journal[$key]['reason'] = $e->getMessage(); echo "PRESERVADO {$key}: " . $e->getMessage() . "\n"; }
                MotionReelBatchService::save($journalPath, $journal);
            }
        } else {
            foreach ($manifest['items'] as $item) {
                $key = (string) $item['id'];
                if (!isset($journal[$key]) || ($journal[$key]['state'] ?? '') === 'rolled_back') { continue; }
                if ($journal[$key]['before'] !== $item['before']) { throw new RuntimeException('Backup nao corresponde ao manifesto.'); }
                if ($batch->snapshot((int) $item['id']) === $item['before']) {
                    $journal[$key]['state'] = 'rolled_back';
                    MotionReelBatchService::save($journalPath, $journal);
                    echo "JA RECUPERADO {$key}\n";
                    continue;
                }
                try {
                    $batch->rollbackItem($item, $journal[$key]['after']);
                    $journal[$key]['state'] = 'rolled_back';
                    echo "RECUPERADO {$key}\n";
                } catch (RuntimeException $e) { echo "PRESERVADO {$key}: " . $e->getMessage() . "\n"; }
                MotionReelBatchService::save($journalPath, $journal);
            }
        }
    }
} catch (Throwable $e) {
    foreach ([$local, $production] as $pdo) { if ($pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); } }
    fwrite(STDERR, $e instanceof PDOException ? "Falha de banco; credenciais nao sao exibidas.\n" : $e->getMessage() . "\n");
    exit(1);
} finally {
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
}
