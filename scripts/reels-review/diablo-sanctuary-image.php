<?php
declare(strict_types=1);
// Scoped, recoverable insertion of the approved Sanctuary artwork; no schedule changes.
if (PHP_SAPI !== 'cli') { exit(1); }
$root = dirname(__DIR__, 2);
require dirname(__DIR__) . '/backup/EnvLoader.php';
\Scripts\Backup\EnvLoader::load($root . '/.env');
$config = require $root . '/config/content-sync.php';
$action = $argv[1] ?? ''; $target = $argv[2] ?? '';
if (!in_array($action, ['--inspect', '--apply', '--verify'], true) || !in_array($target, ['local', 'production'], true)) { exit(1); }
$slug = 'a-guerra-eterna-o-conflito-que-nunca-teve-fim';
$source = $root . '/resources/reels-review/assets/diablo/art-santuario-humanidade-mesa-v1.png';
$relative = 'uploads/posts/' . $slug . '/images/santuario-humanidade-mesa-' . substr(hash_file('sha256', $source), 0, 12) . '.png';
$figure = '<figure><img src="/' . $relative . '" alt="Santuário e a humanidade entre as forças do Céu e do Inferno, em gravura sobre pergaminho" loading="lazy"><figcaption>Ilustração de Santuário e da humanidade entre Céu e Inferno.</figcaption></figure>';
$evidence = $root . '/storage/correcao-instagram-20261010/diablo-sanctuary-blog';
if (!is_dir($evidence)) { mkdir($evidence, 0755, true); }
$backup = $evidence . '/before-' . $target . '.json';
$profile = $config['profiles'][$target]; $db = $profile['database'];
$pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['database']), $db['username'], $db['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$query = $pdo->prepare('SELECT * FROM posts WHERE slug=?'); $query->execute([$slug]); $row = $query->fetch();
if (!$row) { throw new RuntimeException('Article absent'); }
if ($action === '--inspect') {
    if (!is_file($backup)) { file_put_contents($backup, json_encode($row, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)); }
    preg_match_all('~<h[2-3][^>]*>(.*?)</h[2-3]>~is', $row['conteudo'], $headings);
    echo json_encode(['target'=>$target,'id'=>$row['id'],'headings'=>array_map('strip_tags', $headings[1]),'image'=>$relative], JSON_UNESCAPED_UNICODE) . PHP_EOL; exit;
}
$before = json_decode((string)file_get_contents($backup), true, 512, JSON_THROW_ON_ERROR);
$content = $before['conteudo'];
if (!str_contains($content, $relative)) {
    $content = preg_replace('~(<h[23][^>]*>\s*Santuário nunca esteve fora disso\s*</h[23]>\s*<p\b[^>]*>.*?</p>)~isu', '$1' . $figure, $content, 1, $count);
    if ($count !== 1 || !is_string($content)) { throw new RuntimeException('Sanctuary paragraph absent'); }
}
if ($action === '--apply') {
    $u = $profile['uploads'];
    if ($target === 'local') {
        $destination = $root . '/public/' . $relative;
        if (!is_file($destination) && !copy($source, $destination)) { throw new RuntimeException('Copy failed'); }
        if (hash_file('sha256', $destination) !== hash_file('sha256', $source)) { throw new RuntimeException('Asset mismatch'); }
    } else {
        $ftp = ftp_connect($u['host'], (int)$u['port'], 30);
        if (!$ftp || !ftp_login($ftp, $u['username'], $u['password'])) { throw new RuntimeException('FTP unavailable'); }
        ftp_pasv($ftp, (bool)$u['passive']);
        $destination = '/' . ltrim(rtrim($u['root'], '/') . '/' . substr($relative, 8), '/');
        if (ftp_size($ftp, $destination) < 0 && !ftp_put($ftp, $destination, $source, FTP_BINARY)) { throw new RuntimeException('Upload failed'); }
        $stream = fopen('php://memory', 'w+b');
        if (!$stream || !ftp_fget($ftp, $stream, $destination, FTP_BINARY)) { throw new RuntimeException('Upload verification failed'); }
        rewind($stream); $hash = hash('sha256', (string)stream_get_contents($stream)); fclose($stream); ftp_close($ftp);
        if ($hash !== hash_file('sha256', $source)) { throw new RuntimeException('Remote hash mismatch'); }
    }
}
$pdo->beginTransaction();
try {
    $q = $pdo->prepare('SELECT * FROM posts WHERE id=? AND slug=? FOR UPDATE'); $q->execute([$before['id'],$slug]); $current = $q->fetch();
    if (!$current || !in_array($current['conteudo'], [$before['conteudo'], $content], true)) { throw new RuntimeException('Concurrent article change'); }
    $expected = $before; $expected['conteudo'] = $current['conteudo'];
    unset($expected['data_atualizacao']); $protected = $current; unset($protected['data_atualizacao']);
    if ($protected !== $expected) { throw new RuntimeException('Protected fields changed'); }
    if ($action === '--apply' && $current['conteudo'] !== $content) { $q = $pdo->prepare('UPDATE posts SET conteudo=? WHERE id=? AND slug=?'); $q->execute([$content,$before['id'],$slug]); }
    $query->execute([$slug]); $saved = $query->fetch();
    if ($saved['conteudo'] !== $content || substr_count($saved['conteudo'], $relative) !== 1) { throw new RuntimeException('Content verification failed'); }
    $expected = $before; $expected['conteudo'] = $content; unset($expected['data_atualizacao'], $saved['data_atualizacao']);
    if ($saved !== $expected) { throw new RuntimeException('Unexpected mutation'); }
    $pdo->commit(); echo strtoupper($action) . ' ' . $target . ': one image added; other article fields preserved; contentSha256=' . hash('sha256', $content) . PHP_EOL;
} catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
