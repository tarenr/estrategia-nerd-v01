<?php
declare(strict_types=1);

// Approved one-off promotion: stage is the source; production is editorial base.
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__) . '/backup/EnvLoader.php';
\Scripts\Backup\EnvLoader::load(dirname(__DIR__, 2) . '/.env');
date_default_timezone_set('America/Sao_Paulo');
$root = dirname(__DIR__, 2);
$config = require $root . '/config/content-sync.php';
$dir = $root . '/storage/previews/diablo-adicionais-production-20261008';
$action = $argv[1] ?? '';
if (!in_array($action, ['--prepare', '--apply', '--verify'], true)) {
    fwrite(STDERR, "Use --prepare|--apply|--verify (stage -> production only)\n"); exit(1);
}
function dpWrite(string $path, string $bytes): void {
    $handle = @fopen($path, 'xb');
    if (!$handle) { throw new RuntimeException('Evidence exists or cannot be created: ' . basename($path)); }
    $written = fwrite($handle, $bytes); fclose($handle);
    if ($written !== strlen($bytes)) { throw new RuntimeException('Evidence write incomplete'); }
}
function dpJson(string $path, array $data): void {
    dpWrite($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}
function dpRead(FTP\Connection $ftp, string $path): string {
    $stream = fopen('php://memory', 'w+b');
    if (!$stream || !@ftp_fget($ftp, $stream, $path, FTP_BINARY)) { throw new RuntimeException('Remote read failed'); }
    rewind($stream); $bytes = (string) stream_get_contents($stream); fclose($stream); return $bytes;
}
function dpFtp(array $profile): FTP\Connection {
    if (($profile['mode'] ?? '') !== 'ftp') { throw new RuntimeException('FTP profile required'); }
    $ftp = @ftp_connect($profile['host'], (int) $profile['port'], 30);
    if (!$ftp || !@ftp_login($ftp, $profile['username'], $profile['password'])) { throw new RuntimeException('FTP connection unavailable'); }
    ftp_pasv($ftp, (bool) $profile['passive']); return $ftp;
}
function dpPdo(array $profile): PDO {
    $d = $profile['database'];
    return new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $d['host'], $d['port'], $d['database']), $d['username'], $d['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}
function dpRows(PDO $pdo, array $slugs, bool $lock = false): array {
    $q = $pdo->prepare('SELECT * FROM posts WHERE slug IN (?,?,?) ORDER BY slug' . ($lock ? ' FOR UPDATE' : ''));
    $q->execute($slugs); $rows = $q->fetchAll();
    if (count($rows) !== 3 || count(array_unique(array_column($rows, 'slug'))) !== 3) { throw new RuntimeException('Exactly three articles required'); }
    return $rows;
}
function dpEditorial(array $row): array {
    foreach (['views', 'curtidas', 'comentarios_count', 'likes_count', 'data_atualizacao'] as $field) { unset($row[$field]); }
    return $row;
}
function dpTransform(string $content, string $slug, array $mapping): string {
    $hits = [];
    $result = preg_replace_callback('~\b(data-src|src|href)=("|\')([^"\']+)\2~i', static function (array $match) use ($slug, $mapping, &$hits): string {
        $url = $match[3]; $host = parse_url($url, PHP_URL_HOST);
        if (is_string($host) && $host !== '' && strtolower($host) !== 'estrategianerd.com.br') { return $match[0]; }
        $path = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        if (str_starts_with($path, 'stage/')) { $path = substr($path, 6); }
        foreach ($mapping as $map) {
            if ($map['slug'] !== $slug) { continue; }
            if (in_array($path, [$map['old'], str_replace('/images/', '/', $map['old']), $map['new']], true)) {
                $hits[$map['key']] = true;
                return $match[1] . '=' . $match[2] . $map['new'] . $match[2];
            }
        }
        return $match[0];
    }, $content);
    if (!is_string($result)) { throw new RuntimeException('Image transformation failed'); }
    foreach ($mapping as $map) {
        if ($map['slug'] === $slug && !isset($hits[$map['key']])) { throw new RuntimeException('Expected image absent: ' . $map['key']); }
    }
    return $result;
}
function dpCheckRows(array $rows, array $items, bool $final = false): void {
    foreach ($items as $i => $item) {
        $row = $rows[$i]; $before = $item['before'];
        $expected = $before; $expected['conteudo'] = $item['expectedContent'];
        $allowed = $final ? [dpEditorial($expected)] : [dpEditorial($before), dpEditorial($expected)];
        if (!in_array(dpEditorial($row), $allowed, true)) { throw new RuntimeException('Concurrent article change or protected field mismatch'); }
    }
}
function dpCheckOthers(PDO $pdo, array $before, array $ids): void {
    $current = $pdo->query('SELECT * FROM posts ORDER BY id')->fetchAll();
    if (count($current) !== count($before)) { throw new RuntimeException('Article set changed'); }
    foreach ($before as $i => $row) {
        if (!in_array($row['id'], $ids, true) && dpEditorial($row) !== dpEditorial($current[$i])) { throw new RuntimeException('Another article changed'); }
    }
}
$connections = []; $pdo = null;
try {
    $stage = $config['profiles']['stage']; $prod = $config['profiles']['production'];
    $stageRoot = '/' . trim($stage['uploads']['root'], '/');
    $prodRoot = '/' . trim($prod['uploads']['root'], '/');
    if (!str_ends_with($stageRoot, '/public_html/stage/uploads') || !str_ends_with($prodRoot, '/public_html/uploads')) { throw new RuntimeException('Approved remote roots required'); }
    if ($stage['database']['host'] === $prod['database']['host'] && $stage['database']['database'] === $prod['database']['database']) { throw new RuntimeException('Distinct databases required'); }
    $codeRelative = 'app/Services/Site/PostService.php';
    $stageCodeRoot = '/' . trim($stage['code_deploy']['root'], '/');
    $prodPublicRoot = dirname($prodRoot); $prodCoreRoot = $prodPublicRoot . '/_app_core';
    $configuredProdCode = '/' . trim($prod['code_deploy']['root'], '/');
    if ($stageCodeRoot !== dirname($stageRoot) . '/_app_core' || !in_array($configuredProdCode, [$prodPublicRoot, $prodCoreRoot], true)) { throw new RuntimeException('Code root mismatch'); }
    $stagePath = $stageCodeRoot . '/' . $codeRelative; $prodPath = $prodCoreRoot . '/' . $codeRelative;
    $slugs = ['a-lore-completa-de-diablo-entenda-toda-a-historia-do-universo', 'os-arcanjos-do-ceu-a-verdade-por-tras-da-luz-em-diablo', 'os-males-do-inferno-em-diablo-quem-realmente-controla-o-caos'];
    $expectedIds = [17, 13, 12];
    $manifest = json_decode((string) file_get_contents($root . '/resources/reels-review/assets/diablo-adicionais/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $mapping = [];
    if (count($manifest['entries']) !== 10 || array_column($manifest['entries'], 'key') !== ['01','02','03','04','05','06','07','08','09','10']) { throw new RuntimeException('Ten approved keys required'); }
    foreach ($manifest['entries'] as $entry) {
        if (!in_array($entry['slug'], $slugs, true) || !preg_match('/^[a-f0-9]{64}$/', $entry['sha256'])) { throw new RuntimeException('Unexpected manifest scope'); }
        $old = 'uploads/posts/' . $entry['slug'] . '/images/' . basename($entry['original']);
        $new = dirname($old) . '/diablo-adicional-' . $entry['key'] . '-mesa-' . ($entry['key'] === '09' ? 'v2' : 'v1') . '-' . substr($entry['sha256'], 0, 12) . '.webp';
        $mapping[] = ['key' => $entry['key'], 'slug' => $entry['slug'], 'old' => $old, 'new' => $new, 'sha256' => $entry['sha256']];
    }
    $deployment = json_decode((string) file_get_contents($root . '/storage/previews/diablo-adicionais-stage-20261008/resolver-stage-deployment.json'), true, 512, JSON_THROW_ON_ERROR);
    $connections[] = $stageAssets = dpFtp($stage['uploads']);
    $connections[] = $prodAssets = dpFtp($prod['uploads']);
    $connections[] = $stageCode = dpFtp($stage['code_deploy']);
    $connections[] = $prodCode = dpFtp($prod['code_deploy']);
    $front = dpRead($prodCode, $prodPublicRoot . '/index.php');
    if (!str_contains($front, "__DIR__ . '/_app_core'") || @ftp_size($prodCode, $prodCoreRoot . '/bootstrap.php') < 1) { throw new RuntimeException('Embedded production core not confirmed'); }
    $pdo = dpPdo($prod); $stagePdo = dpPdo($stage);
    if (!is_dir($dir) && !mkdir($dir, 0700, true)) { throw new RuntimeException('Cannot create private evidence directory'); }
    $packageFile = $dir . '/package.json';
    if ($action === '--prepare') {
        if (file_exists($packageFile) || file_exists($dir . '/snapshots.json')) { throw new RuntimeException('Preserve existing preparation'); }
        $pdo->exec('START TRANSACTION READ ONLY'); $rows = dpRows($pdo, $slugs);
        $all = $pdo->query('SELECT * FROM posts ORDER BY id')->fetchAll(); $pdo->rollBack();
        if (array_column($rows, 'id') !== $expectedIds) { throw new RuntimeException('Production IDs differ from approved scope'); }
        $stagePdo->exec('START TRANSACTION READ ONLY'); $sourceRows = dpRows($stagePdo, $slugs); $stagePdo->rollBack();
        $items = [];
        foreach ($rows as $i => $row) {
            foreach ($mapping as $map) { if ($map['slug'] === $row['slug'] && !str_contains($sourceRows[$i]['conteudo'], $map['new'])) { throw new RuntimeException('Stage no longer matches approved artwork'); } }
            $items[] = ['before' => $row, 'expectedContent' => dpTransform($row['conteudo'], $row['slug'], $mapping)];
        }
        dpJson($dir . '/snapshots.json', ['capturedAt' => date(DATE_ATOM), 'source' => 'stage', 'destination' => 'production', 'productionRows' => $rows, 'stageRows' => $sourceRows, 'allProductionPosts' => $all]);
        $beforeCode = dpRead($prodCode, $prodPath); $sourceCode = dpRead($stageCode, $stagePath);
        dpWrite($dir . '/PostService-production-before.php', $beforeCode);
        if (hash('sha256', $beforeCode) !== $deployment['beforeSha256'] || hash('sha256', $sourceCode) !== $deployment['afterSha256']) { throw new RuntimeException('Code differs from reviewed baseline or stage deployment'); }
        dpWrite($dir . '/PostService-stage-approved.php', $sourceCode);
        foreach ($mapping as $map) {
            $bytes = dpRead($stageAssets, $stageRoot . '/' . substr($map['new'], 8));
            if (hash('sha256', $bytes) !== $map['sha256']) { throw new RuntimeException('Stage asset hash mismatch'); }
            $size = getimagesizefromstring($bytes);
            if (!$size || $size[0] !== 1672 || $size[1] !== 941) { throw new RuntimeException('Stage image dimensions mismatch'); }
            dpWrite($dir . '/' . basename($map['new']), $bytes);
            $destination = $prodRoot . '/' . substr($map['new'], 8);
            if (@ftp_size($prodAssets, $destination) >= 0 && hash('sha256', dpRead($prodAssets, $destination)) !== $map['sha256']) { throw new RuntimeException('Existing production asset differs'); }
        }
        dpJson($packageFile, ['source' => 'stage', 'destination' => 'production', 'preparedAt' => date(DATE_ATOM), 'mapping' => $mapping, 'items' => $items, 'allProductionPosts' => $all, 'sourceCodeSha256' => hash('sha256', $sourceCode), 'beforeCodeSha256' => hash('sha256', $beforeCode), 'productionCodePath' => $prodPath, 'stageCodePath' => $stagePath]);
        echo "PREPARED: production snapshots/code backup; ten assets and code sourced from stage\n";
    } else {
        $package = json_decode((string) file_get_contents($packageFile), true, 512, JSON_THROW_ON_ERROR);
        if ($package['source'] !== 'stage' || $package['destination'] !== 'production' || $package['mapping'] !== $mapping || $package['productionCodePath'] !== $prodPath || $package['stageCodePath'] !== $stagePath) { throw new RuntimeException('Prepared scope changed'); }
        if (hash_file('sha256', $dir . '/PostService-production-before.php') !== $package['beforeCodeSha256'] || hash_file('sha256', $dir . '/PostService-stage-approved.php') !== $package['sourceCodeSha256']) { throw new RuntimeException('Code backup or package hash mismatch'); }
        if (hash('sha256', dpRead($stageCode, $stagePath)) !== $package['sourceCodeSha256']) { throw new RuntimeException('Stage code changed'); }
        $stageRows = dpRows($stagePdo, $slugs);
        foreach ($stageRows as $row) { foreach ($mapping as $map) { if ($map['slug'] === $row['slug'] && !str_contains($row['conteudo'], $map['new'])) { throw new RuntimeException('Stage image references changed'); } } }
        dpCheckRows(dpRows($pdo, $slugs), $package['items'], $action === '--verify');
        dpCheckOthers($pdo, $package['allProductionPosts'], $expectedIds);
        $currentCodeHash = hash('sha256', dpRead($prodCode, $prodPath));
        if (!in_array($currentCodeHash, [$package['beforeCodeSha256'], $package['sourceCodeSha256']], true)) { throw new RuntimeException('Concurrent production code change'); }
        foreach ($mapping as $map) {
            $local = $dir . '/' . basename($map['new']); $destination = $prodRoot . '/' . substr($map['new'], 8);
            if (hash_file('sha256', $local) !== $map['sha256'] || hash('sha256', dpRead($stageAssets, $stageRoot . '/' . substr($map['new'], 8))) !== $map['sha256']) { throw new RuntimeException('Prepared or stage asset changed'); }
            if (@ftp_size($prodAssets, $destination) < 0) {
                if ($action !== '--apply' || !@ftp_chdir($prodAssets, dirname($destination)) || !@ftp_put($prodAssets, $destination, $local, FTP_BINARY)) { throw new RuntimeException('Approved asset upload unavailable'); }
            }
            if (hash('sha256', dpRead($prodAssets, $destination)) !== $map['sha256']) { throw new RuntimeException('Production asset hash mismatch'); }
        }
        if ($action === '--apply' && $currentCodeHash !== $package['sourceCodeSha256']) {
            $temporary = dirname($prodPath) . '/PostService.diablo-20261008.tmp';
            if (@ftp_size($prodCode, $temporary) < 0 && !@ftp_put($prodCode, $temporary, $dir . '/PostService-stage-approved.php', FTP_BINARY)) { throw new RuntimeException('Temporary code upload failed'); }
            if (hash('sha256', dpRead($prodCode, $temporary)) !== $package['sourceCodeSha256'] || hash('sha256', dpRead($prodCode, $prodPath)) !== $package['beforeCodeSha256']) { throw new RuntimeException('Temporary file mismatch or concurrent code change'); }
            if (!@ftp_rename($prodCode, $temporary, $prodPath)) { throw new RuntimeException('Code replacement failed; database not updated'); }
        }
        if (hash('sha256', dpRead($prodCode, $prodPath)) !== $package['sourceCodeSha256']) { throw new RuntimeException('Active code hash mismatch'); }
        $pdo->beginTransaction(); $current = dpRows($pdo, $slugs, true);
        dpCheckRows($current, $package['items'], $action === '--verify');
        dpCheckOthers($pdo, $package['allProductionPosts'], $expectedIds);
        foreach ($package['items'] as $i => $item) {
            $row = $current[$i];
            if ($action === '--apply' && $row['conteudo'] !== $item['expectedContent']) {
                $q = $pdo->prepare('UPDATE posts SET conteudo=? WHERE id=? AND slug=?'); $q->execute([$item['expectedContent'], $row['id'], $row['slug']]);
            }
            $q = $pdo->prepare('SELECT * FROM posts WHERE id=?'); $q->execute([$row['id']]); $saved = $q->fetch();
            $expected = $row; $expected['conteudo'] = $item['expectedContent'];
            unset($saved['data_atualizacao'], $expected['data_atualizacao']);
            if ($saved !== $expected) { throw new RuntimeException('Unexpected field changed during update'); }
        }
        $pdo->commit();
        $proof = ['source' => 'stage', 'destination' => 'production', 'articleIds' => $expectedIds, 'images' => 10, 'codeSha256' => $package['sourceCodeSha256'], 'otherArticleEditorialFieldsPreserved' => true, 'checkedAt' => date(DATE_ATOM)];
        $proofFile = $dir . ($action === '--apply' ? '/application.json' : '/verified.json');
        if (!file_exists($proofFile)) { dpJson($proofFile, $proof); }
        echo strtoupper($action) . ": ten image hashes, active code and three articles verified; editorial fields preserved\n";
    }
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, 'STOP: ' . $e->getMessage() . PHP_EOL); exit(1);
} finally { foreach ($connections as $ftp) { ftp_close($ftp); } }
