<?php
declare(strict_types=1);

// One-off, explicitly scoped application of the ten reviewed Diablo images.
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__) . '/backup/EnvLoader.php';
\Scripts\Backup\EnvLoader::load(dirname(__DIR__, 2) . '/.env');
$root = dirname(__DIR__, 2);
$config = require $root . '/config/content-sync.php';
$assetDir = $root . '/resources/reels-review/assets/diablo-adicionais';
$evidenceDir = $root . '/storage/previews/diablo-adicionais-stage-20261008';
$action = $argv[1] ?? '';
$target = $argv[2] ?? '';
if (!in_array($action, ['--inspect', '--apply', '--verify'], true) || !in_array($target, ['local', 'stage'], true)) {
    fwrite(STDERR, "Use --inspect|--apply|--verify local|stage\n"); exit(1);
}
$slugs = [
    'os-arcanjos-do-ceu-a-verdade-por-tras-da-luz-em-diablo',
    'os-males-do-inferno-em-diablo-quem-realmente-controla-o-caos',
    'a-lore-completa-de-diablo-entenda-toda-a-historia-do-universo',
];
function saveEvidence(string $path, array $data): void {
    if (file_exists($path)) { throw new RuntimeException('Evidence already exists; preserve it'); }
    if (file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) {
        throw new RuntimeException('Cannot save evidence');
    }
}
function selectedRows(PDO $pdo, array $slugs, bool $lock = false): array {
    $q = $pdo->prepare('SELECT * FROM posts WHERE slug IN (?,?,?) ORDER BY slug' . ($lock ? ' FOR UPDATE' : ''));
    $q->execute($slugs); $rows = $q->fetchAll();
    if (count($rows) !== 3 || count(array_unique(array_column($rows, 'slug'))) !== 3) { throw new RuntimeException('Article scope mismatch'); }
    return $rows;
}
function transformContent(array $row, array $mapping): string {
    $content = (string) $row['conteudo'];
    foreach ($mapping as $map) {
        if ($map['slug'] !== $row['slug']) { continue; }
        $old = $map['old'];
        $legacy = str_replace('/images/', '/', $old);
        $variants = [$old, $legacy];
        $from = [];
        foreach ($variants as $v) {
            foreach (['https://estrategianerd.com.br/', 'http://estrategianerd.com.br/', '/stage/', '/', ''] as $prefix) {
                $from[] = $prefix . $v;
            }
        }
        usort($from, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
        $count = 0;
        foreach ($from as $reference) {
            $content = str_replace($reference, $map['new'], $content, $n); $count += $n;
        }
        if ($count === 0 && !str_contains($content, $map['new'])) { throw new RuntimeException('Expected image reference absent: ' . $map['key']); }
    }
    return $content;
}
function remoteHash(FTP\Connection $ftp, string $path): string {
    $stream = fopen('php://memory', 'w+b');
    if ($stream === false || !@ftp_fget($ftp, $stream, $path, FTP_BINARY)) { throw new RuntimeException('Cannot read uploaded image'); }
    rewind($stream); $hash = hash('sha256', (string) stream_get_contents($stream)); fclose($stream); return $hash;
}
$pdo = null; $ftp = null;
try {
    $manifest = json_decode((string) file_get_contents($assetDir . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    if (count($manifest['entries']) !== 10) { throw new RuntimeException('Exactly ten images required'); }
    $mapping = [];
    foreach ($manifest['entries'] as $entry) {
        if (!in_array($entry['slug'], $slugs, true) || !preg_match('/^\d{2}-mesa-v[12]\.webp$/', $entry['after'])) { throw new RuntimeException('Unexpected asset'); }
        $source = $assetDir . '/' . $entry['after'];
        if (hash_file('sha256', $source) !== $entry['sha256'] || hash_file('sha256', $entry['original']) !== $entry['originalSha256']) { throw new RuntimeException('Asset or original hash changed'); }
        $size = getimagesize($source);
        if ($size === false || $size[0] !== 1672 || $size[1] !== 941) { throw new RuntimeException('Image dimensions mismatch'); }
        $old = 'uploads/posts/' . $entry['slug'] . '/images/' . basename($entry['original']);
        $new = dirname($old) . '/diablo-adicional-' . $entry['key'] . '-mesa-' . ($entry['key'] === '09' ? 'v2' : 'v1') . '-' . substr($entry['sha256'], 0, 12) . '.webp';
        $mapping[] = ['key' => $entry['key'], 'slug' => $entry['slug'], 'old' => $old, 'new' => $new, 'source' => $source, 'sha256' => $entry['sha256']];
    }
    $profile = $config['profiles'][$target]; $d = $profile['database']; $uploads = $profile['uploads'];
    if ($target === 'stage') {
        $production = $config['profiles']['production'];
        if (($d['host'] === $production['database']['host'] && $d['database'] === $production['database']['database']) || $uploads['root'] === $production['uploads']['root']) { throw new RuntimeException('Stage must be distinct from production'); }
        if (!str_ends_with(rtrim($uploads['root'], '/'), '/uploads')) { throw new RuntimeException('Unexpected uploads root'); }
    }
    $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $d['host'], $d['port'], $d['database']), $d['username'], $d['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    if (!is_dir($evidenceDir) && !mkdir($evidenceDir, 0755, true)) { throw new RuntimeException('Cannot create evidence directory'); }
    $stateFile = $evidenceDir . '/references-' . $target . '.json';
    if ($action === '--inspect') {
        $pdo->exec('START TRANSACTION READ ONLY');
        $rows = selectedRows($pdo, $slugs);
        $items = [];
        foreach ($rows as $row) { $items[] = ['before' => $row, 'expectedContent' => transformContent($row, $mapping)]; }
        $other = $pdo->query('SELECT id,slug,conteudo,imagem_capa,imagem_thumb,status,data_publicacao FROM posts ORDER BY id')->fetchAll();
        $pdo->rollBack();
        saveEvidence($stateFile, ['environment' => $target, 'capturedAt' => date(DATE_ATOM), 'mapping' => $mapping, 'items' => $items, 'allReferences' => $other]);
        echo "BACKUP $target: three articles, ten image mappings\n"; exit;
    }
    $state = json_decode((string) file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR);
    if ($state['environment'] !== $target || $state['mapping'] !== $mapping) { throw new RuntimeException('Snapshot scope or assets changed'); }
    if ($target === 'stage' && $action === '--apply' && !is_file($evidenceDir . '/verified-local.json')) { throw new RuntimeException('Local verification required'); }
    if ($target === 'stage') {
        $ftp = @ftp_connect($uploads['host'], (int) $uploads['port'], 30);
        if (!$ftp || !@ftp_login($ftp, $uploads['username'], $uploads['password'])) { throw new RuntimeException('Stage FTP unavailable'); }
        ftp_pasv($ftp, (bool) $uploads['passive']);
    }
    foreach ($mapping as $map) {
        $destination = rtrim($target === 'local' ? $uploads['path'] : $uploads['root'], '/\\') . '/' . substr($map['new'], 8);
        // FTP chdir below must not change how the upload destination resolves.
        if ($target === 'stage') { $destination = '/' . ltrim($destination, '/'); }
        $exists = $ftp ? @ftp_size($ftp, $destination) >= 0 : is_file($destination);
        if (!$exists && $action === '--apply') {
            if ($ftp) {
                $parent = dirname($destination);
                if (!@ftp_chdir($ftp, $parent)) { throw new RuntimeException('Expected article images directory absent'); }
                if (!@ftp_put($ftp, $destination, $map['source'], FTP_BINARY)) { throw new RuntimeException('Upload failed'); }
            } else {
                if (!is_dir(dirname($destination)) || !copy($map['source'], $destination)) { throw new RuntimeException('Local copy failed'); }
            }
        } elseif (!$exists) { throw new RuntimeException('Expected new image missing'); }
        $hash = $ftp ? remoteHash($ftp, $destination) : hash_file('sha256', $destination);
        if ($hash !== $map['sha256']) { throw new RuntimeException('Uploaded file differs; never overwrite'); }
    }
    $pdo->beginTransaction(); $rows = selectedRows($pdo, $slugs, true);
    foreach ($state['items'] as $i => $item) {
        $row = $rows[$i]; $before = $item['before'];
        if ($row['id'] !== $before['id'] || $row['slug'] !== $before['slug'] || !in_array($row['conteudo'], [$before['conteudo'], $item['expectedContent']], true)) { throw new RuntimeException('Concurrent content change'); }
        foreach (['imagem_capa', 'imagem_thumb', 'status', 'data_publicacao'] as $field) {
            if ($row[$field] !== $before[$field]) { throw new RuntimeException('Protected field changed since snapshot'); }
        }
        if ($action === '--apply' && $row['conteudo'] !== $item['expectedContent']) {
            $q = $pdo->prepare('UPDATE posts SET conteudo=? WHERE id=? AND slug=?'); $q->execute([$item['expectedContent'], $row['id'], $row['slug']]);
        }
        $q = $pdo->prepare('SELECT * FROM posts WHERE id=?'); $q->execute([$row['id']]); $saved = $q->fetch();
        $expected = $row; $expected['conteudo'] = $item['expectedContent'];
        unset($saved['data_atualizacao'], $expected['data_atualizacao']);
        if ($saved !== $expected) { throw new RuntimeException('Unexpected article field mutation'); }
    }
    $ids = array_column($rows, 'id');
    foreach ($state['allReferences'] as $before) {
        if (in_array($before['id'], $ids, true)) { continue; }
        $q = $pdo->prepare('SELECT id,slug,conteudo,imagem_capa,imagem_thumb,status,data_publicacao FROM posts WHERE id=?'); $q->execute([$before['id']]);
        if ($q->fetch() !== $before) { throw new RuntimeException('Another article changed'); }
    }
    $pdo->commit();
    if ($action === '--verify') {
        $proof = ['environment' => $target, 'articles' => $ids, 'images' => 10, 'hashesVerified' => true, 'otherArticleReferencesPreserved' => true, 'protectedFieldsPreserved' => true];
        $proofFile = $evidenceDir . '/verified-' . $target . '.json';
        if (!is_file($proofFile)) { saveEvidence($proofFile, $proof); }
        elseif (json_decode((string) file_get_contents($proofFile), true, 512, JSON_THROW_ON_ERROR) !== $proof) { throw new RuntimeException('Previous verification differs'); }
    }
    echo strtoupper($action) . " $target: three articles; ten hashes verified; other fields preserved\n";
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, 'STOP: ' . $e->getMessage() . PHP_EOL); exit(1);
} finally { if ($ftp instanceof FTP\Connection) { ftp_close($ftp); } }
