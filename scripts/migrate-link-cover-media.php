<?php
declare(strict_types=1);

require_once __DIR__ . '/backup/EnvLoader.php';

use Scripts\Backup\EnvLoader;

EnvLoader::load(dirname(__DIR__) . '/.env');

$profileName = strtolower(trim((string) ($argv[1] ?? 'local')));
$apply = in_array('--apply', $argv, true);

$config = require dirname(__DIR__) . '/config/backup.php';
$profiles = (array) ($config['profiles'] ?? []);
if (!isset($profiles[$profileName]) || !is_array($profiles[$profileName])) {
    fwrite(STDERR, "Perfil invalido. Use local, stage ou production.\n");
    exit(1);
}

$profile = $profiles[$profileName];
$pdo = connectPdo((array) ($profile['database'] ?? []));
$storage = createStorage((array) ($profile['uploads'] ?? []));
$rows = $pdo->query('SELECT id, titulo, slug, imagem FROM links ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];

$stats = [
    'total' => count($rows),
    'ok' => 0,
    'planned' => 0,
    'migrated' => 0,
    'skipped_empty' => 0,
    'skipped_external' => 0,
    'missing' => 0,
    'conflict' => 0,
    'failed' => 0,
];

echo '== Migracao de capas de Links ==' . PHP_EOL;
echo 'Perfil: ' . $profileName . ' (' . (string) ($profile['label'] ?? $profileName) . ')' . PHP_EOL;
echo 'Modo: ' . ($apply ? 'APLICAR' : 'DRY-RUN') . PHP_EOL;
echo 'Storage: ' . (string) ($storage['mode'] ?? '-') . PHP_EOL . PHP_EOL;

foreach ($rows as $row) {
    $id = (int) ($row['id'] ?? 0);
    $title = trim((string) ($row['titulo'] ?? ''));
    $slug = slugify((string) ($row['slug'] ?? ''));
    $image = trim((string) ($row['imagem'] ?? ''));
    $label = '#' . $id . ' ' . ($title !== '' ? $title : $slug);

    if ($image === '') {
        $stats['skipped_empty']++;
        echo '[vazio] ' . $label . PHP_EOL;
        continue;
    }

    $sourceRelative = normalizeUploadPath($image);
    if ($slug === '' || $sourceRelative === null || !str_starts_with($sourceRelative, 'uploads/links/')) {
        $stats['skipped_external']++;
        echo '[fora] ' . $label . ' -> ' . $image . PHP_EOL;
        continue;
    }

    if (!storageFileExists($storage, $sourceRelative)) {
        $stats['missing']++;
        echo '[nao encontrado] ' . $label . ' -> ' . $sourceRelative . PHP_EOL;
        continue;
    }

    $extension = strtolower((string) pathinfo($sourceRelative, PATHINFO_EXTENSION));
    if ($extension === '') {
        $stats['failed']++;
        echo '[erro] ' . $label . ' -> extensao ausente' . PHP_EOL;
        continue;
    }

    $targetRelativeDir = 'uploads/links/' . $slug;
    $targetRelative = $targetRelativeDir . '/capa.' . $extension;

    if ($sourceRelative === $targetRelative) {
        $stats['ok']++;
        echo '[ok] ' . $label . ' -> ' . $sourceRelative . PHP_EOL;
        continue;
    }

    $existingTargets = storageListCovers($storage, $targetRelativeDir);
    if ($existingTargets !== []) {
        $sameContent = false;
        $hasConflict = false;
        foreach ($existingTargets as $existingTarget) {
            if ($existingTarget === $sourceRelative || storageFilesHaveSameContent($storage, $sourceRelative, $existingTarget)) {
                $sameContent = true;
                continue;
            }

            $hasConflict = true;
            break;
        }

        if ($hasConflict) {
            $stats['conflict']++;
            echo '[conflito] ' . $label . ' -> ja existe capa diferente em ' . $targetRelativeDir . PHP_EOL;
            continue;
        }

        echo ($apply ? '[atualizando] ' : '[dry-run] ') . $label . ' -> capa ja existe em ' . $targetRelative . PHP_EOL;
        if ($apply) {
            updateLinkImage($pdo, $id, $targetRelative);
            storageDeleteFile($storage, $sourceRelative);
            storageRemoveEmptyParent($storage, $sourceRelative);
            $stats['migrated']++;
        } else {
            $stats['planned']++;
        }
        continue;
    }

    echo ($apply ? '[migrando] ' : '[dry-run] ') . $label . ' -> ' . $sourceRelative . ' => ' . $targetRelative . PHP_EOL;
    if (!$apply) {
        $stats['planned']++;
        continue;
    }

    if (!storageCopyFile($storage, $sourceRelative, $targetRelative)) {
        $stats['failed']++;
        echo '  erro: nao foi possivel criar a capa no destino' . PHP_EOL;
        continue;
    }

    updateLinkImage($pdo, $id, $targetRelative);
    storageDeleteFile($storage, $sourceRelative);
    storageRemoveEmptyParent($storage, $sourceRelative);
    $stats['migrated']++;
}

storageClose($storage);

echo PHP_EOL . 'Resumo:' . PHP_EOL;
foreach ($stats as $key => $value) {
    echo '- ' . $key . ': ' . $value . PHP_EOL;
}

if (!$apply) {
    echo PHP_EOL . "Nenhuma alteracao foi aplicada. Rode com --apply para executar.\n";
}

function connectPdo(array $database): PDO
{
    foreach (['host', 'database', 'username'] as $required) {
        if (trim((string) ($database[$required] ?? '')) === '') {
            throw new RuntimeException('Configuracao de banco incompleta: ' . $required);
        }
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        (string) $database['host'],
        (string) ($database['port'] ?? '3306'),
        (string) $database['database']
    );

    return new PDO($dsn, (string) $database['username'], (string) ($database['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function createStorage(array $uploads): array
{
    $mode = strtolower(trim((string) ($uploads['mode'] ?? 'local')));
    if ($mode === 'local') {
        $path = rtrim((string) ($uploads['path'] ?? ''), '\\/');
        if ($path === '') {
            throw new RuntimeException('Storage local sem path configurado.');
        }

        return [
            'mode' => 'local',
            'uploads_root' => $path,
            'public_root' => dirname($path),
        ];
    }

    if ($mode !== 'ftp') {
        throw new RuntimeException('Modo de upload nao suportado: ' . $mode);
    }

    foreach (['host', 'port', 'username', 'password', 'root'] as $required) {
        if (trim((string) ($uploads[$required] ?? '')) === '') {
            throw new RuntimeException('Configuracao FTP incompleta: ' . $required);
        }
    }

    $ftp = @ftp_connect((string) $uploads['host'], (int) $uploads['port'], 30);
    if ($ftp === false) {
        throw new RuntimeException('Nao foi possivel conectar ao FTP.');
    }

    if (!@ftp_login($ftp, (string) $uploads['username'], (string) $uploads['password'])) {
        ftp_close($ftp);
        throw new RuntimeException('Falha de login no FTP.');
    }

    ftp_pasv($ftp, !in_array(strtolower((string) ($uploads['passive'] ?? 'true')), ['0', 'false', 'off', 'no'], true));

    return [
        'mode' => 'ftp',
        'ftp' => $ftp,
        'root' => rtrim(str_replace('\\', '/', (string) $uploads['root']), '/'),
    ];
}

function storagePath(array $storage, string $relative): string
{
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    if (($storage['mode'] ?? '') === 'local') {
        $publicRoot = rtrim((string) ($storage['public_root'] ?? ''), '\\/');
        return $publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    $root = rtrim((string) ($storage['root'] ?? ''), '/');
    $underUploads = preg_replace('~^uploads/~', '', $relative) ?? $relative;
    return $root . '/' . ltrim($underUploads, '/');
}

function storageFileExists(array $storage, string $relative): bool
{
    if (($storage['mode'] ?? '') === 'local') {
        return is_file(storagePath($storage, $relative));
    }

    return @ftp_size($storage['ftp'], storagePath($storage, $relative)) >= 0;
}

function storageListCovers(array $storage, string $relativeDir): array
{
    if (($storage['mode'] ?? '') === 'local') {
        $dir = storagePath($storage, $relativeDir);
        $items = glob($dir . DIRECTORY_SEPARATOR . 'capa.*') ?: [];
        return array_map(static function (string $path) use ($storage): string {
            $publicRoot = rtrim(str_replace('\\', '/', (string) ($storage['public_root'] ?? '')), '/');
            return ltrim(substr(str_replace('\\', '/', $path), strlen($publicRoot)), '/');
        }, $items);
    }

    $remoteDir = storagePath($storage, $relativeDir);
    $items = @ftp_nlist($storage['ftp'], $remoteDir);
    if (!is_array($items)) {
        return [];
    }

    $root = rtrim((string) ($storage['root'] ?? ''), '/');
    $result = [];
    foreach ($items as $item) {
        $name = basename((string) $item);
        if (!str_starts_with($name, 'capa.')) {
            continue;
        }
        $remotePath = str_replace('\\', '/', (string) $item);
        $relative = 'uploads/' . ltrim(substr($remotePath, strlen($root)), '/');
        $result[] = $relative;
    }

    return $result;
}

function storageCopyFile(array $storage, string $sourceRelative, string $targetRelative): bool
{
    if (($storage['mode'] ?? '') === 'local') {
        $source = storagePath($storage, $sourceRelative);
        $target = storagePath($storage, $targetRelative);
        $targetDir = dirname($target);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return false;
        }
        return copyAndVerify($source, $target);
    }

    $tmp = tempnam(sys_get_temp_dir(), 'en-link-cover-');
    if (!is_string($tmp)) {
        return false;
    }

    try {
        if (!@ftp_get($storage['ftp'], $tmp, storagePath($storage, $sourceRelative), FTP_BINARY)) {
            return false;
        }
        ensureRemoteDirectory($storage['ftp'], dirname(storagePath($storage, $targetRelative)));
        return @ftp_put($storage['ftp'], storagePath($storage, $targetRelative), $tmp, FTP_BINARY);
    } finally {
        @unlink($tmp);
    }
}

function storageDeleteFile(array $storage, string $relative): void
{
    if (($storage['mode'] ?? '') === 'local') {
        @unlink(storagePath($storage, $relative));
        return;
    }

    @ftp_delete($storage['ftp'], storagePath($storage, $relative));
}

function storageRemoveEmptyParent(array $storage, string $relative): void
{
    $parent = dirname($relative);
    if ($parent === 'uploads/links' || $parent === 'uploads' || $parent === '.') {
        return;
    }

    if (($storage['mode'] ?? '') === 'local') {
        $dir = storagePath($storage, $parent);
        $items = @scandir($dir);
        if ($items !== false && array_diff($items, ['.', '..']) === []) {
            @rmdir($dir);
        }
        return;
    }

    $items = @ftp_nlist($storage['ftp'], storagePath($storage, $parent));
    if (is_array($items) && count(array_filter($items, static fn (string $item): bool => !in_array(basename($item), ['.', '..'], true))) === 0) {
        @ftp_rmdir($storage['ftp'], storagePath($storage, $parent));
    }
}

function storageFilesHaveSameContent(array $storage, string $left, string $right): bool
{
    if (($storage['mode'] ?? '') === 'local') {
        return filesHaveSameContent(storagePath($storage, $left), storagePath($storage, $right));
    }

    $leftTmp = tempnam(sys_get_temp_dir(), 'en-link-left-');
    $rightTmp = tempnam(sys_get_temp_dir(), 'en-link-right-');
    if (!is_string($leftTmp) || !is_string($rightTmp)) {
        return false;
    }

    try {
        if (!@ftp_get($storage['ftp'], $leftTmp, storagePath($storage, $left), FTP_BINARY)) {
            return false;
        }
        if (!@ftp_get($storage['ftp'], $rightTmp, storagePath($storage, $right), FTP_BINARY)) {
            return false;
        }
        return filesHaveSameContent($leftTmp, $rightTmp);
    } finally {
        @unlink($leftTmp);
        @unlink($rightTmp);
    }
}

function storageClose(array $storage): void
{
    if (($storage['mode'] ?? '') === 'ftp' && isset($storage['ftp'])) {
        @ftp_close($storage['ftp']);
    }
}

function updateLinkImage(PDO $pdo, int $id, string $path): void
{
    $stmt = $pdo->prepare('UPDATE links SET imagem = :imagem WHERE id = :id LIMIT 1');
    $stmt->execute(['imagem' => $path, 'id' => $id]);
}

function normalizeUploadPath(string $path): ?string
{
    $relative = trim(urldecode($path));
    if ($relative === '') {
        return null;
    }

    if (preg_match('~^https?://~i', $relative) === 1) {
        $parsedPath = parse_url($relative, PHP_URL_PATH);
        $relative = is_string($parsedPath) ? $parsedPath : '';
    }

    $relative = str_replace('\\', '/', $relative);
    $uploadsPos = strpos($relative, '/uploads/');
    if ($uploadsPos !== false) {
        $relative = substr($relative, $uploadsPos + 1);
    }

    $relative = ltrim($relative, '/');
    if ($relative === '' || !str_starts_with($relative, 'uploads/')) {
        return null;
    }

    return $relative;
}

function slugify(string $value): string
{
    $value = trim(mb_strtolower($value));
    if ($value === '') {
        return '';
    }

    if (function_exists('iconv')) {
        $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($normalized) && $normalized !== '') {
            $value = $normalized;
        }
    }

    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
    return trim(mb_substr($value, 0, 150), '-');
}

function copyAndVerify(string $source, string $target): bool
{
    if (!@copy($source, $target)) {
        return false;
    }

    $sourceSize = @filesize($source);
    $targetSize = @filesize($target);
    if ($sourceSize === false || $targetSize === false || $sourceSize !== $targetSize) {
        @unlink($target);
        return false;
    }

    @unlink($source);
    return true;
}

function filesHaveSameContent(string $left, string $right): bool
{
    if (!is_file($left) || !is_file($right)) {
        return false;
    }

    $leftSize = @filesize($left);
    $rightSize = @filesize($right);
    if ($leftSize === false || $rightSize === false || $leftSize !== $rightSize) {
        return false;
    }

    $leftHash = @hash_file('sha256', $left);
    $rightHash = @hash_file('sha256', $right);
    return is_string($leftHash) && is_string($rightHash) && $leftHash === $rightHash;
}

function ensureRemoteDirectory($ftp, string $remoteDirectory): void
{
    $parts = array_values(array_filter(explode('/', trim(str_replace('\\', '/', $remoteDirectory), '/')), static fn (string $part): bool => $part !== ''));
    $current = '';
    foreach ($parts as $part) {
        $current .= '/' . $part;
        @ftp_mkdir($ftp, $current);
    }
}
