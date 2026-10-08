<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/backup/EnvLoader.php';
Scripts\Backup\EnvLoader::load(dirname(__DIR__, 2) . '/.env');
$root = dirname(__DIR__, 2);
$config = require $root . '/config/content-sync.php';
$d = $config['profiles']['local']['database'];
$pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $d['host'], $d['port'], $d['database']), $d['username'], $d['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$slugs = ['msi-mag-b650-tomahawk-wifi-a-base-que-sustenta-um-setup-de-verdade', 'kingston-nv3-1tb-nvme-pcie-4-0-velocidade-real-para-o-dia-a-dia'];
$file = $root . '/storage/previews/reels-review/hardware-samples-20261008/blog-backup.json';
$action = $argv[1] ?? '';
if (!in_array($action, ['--apply', '--verify', '--rollback'], true)) { throw new RuntimeException('Use --apply|--verify|--rollback (somente local)'); }
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT * FROM posts WHERE slug IN (?,?) ORDER BY slug FOR UPDATE');
    $stmt->execute($slugs); $rows = $stmt->fetchAll();
    if (count($rows) !== 2) { throw new RuntimeException('Dois artigos obrigatorios'); }
    if (!is_file($file)) {
        if ($action !== '--apply') { throw new RuntimeException('Backup ausente'); }
        $backup = [];
        foreach ($rows as $row) {
            $name = $row['slug'] === $slugs[0] ? 'b650' : 'nv3';
            $source = $root . '/resources/reels-review/assets/' . $name . '-cenario-v1.png';
            $size = getimagesize($source);
            if ($size === false || $size[0] !== 1200 || $size[1] !== 800) { throw new RuntimeException('Dimensoes invalidas'); }
            $path = 'uploads/posts/' . $row['slug'] . '/images/capa-cenario-' . substr((string) hash_file('sha256', $source), 0, 12) . '.png';
            $backup[] = ['row' => $row, 'source' => $source, 'path' => $path, 'sha256' => hash_file('sha256', $source), 'originalSha256' => hash_file('sha256', $root . '/public/' . $row['imagem_capa'])];
        }
        $json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $handle = fopen($file, 'x'); if ($handle === false) { throw new RuntimeException('Backup existente'); } fwrite($handle, $json); fclose($handle);
    }
    $backup = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    foreach ($backup as $item) {
        $matches = array_values(array_filter($rows, static fn(array $r): bool => $r['id'] === $item['row']['id'] && $r['slug'] === $item['row']['slug']));
        if (count($matches) !== 1) { throw new RuntimeException('Identidade do artigo mudou'); }
        $row = $matches[0];
        if (hash_file('sha256', $item['source']) !== $item['sha256'] || hash_file('sha256', $root . '/public/' . $item['row']['imagem_capa']) !== $item['originalSha256']) { throw new RuntimeException('Original ou arte divergente'); }
        $desired = ['imagem_capa' => $item['path'], 'imagem_thumb' => $item['path'], 'conteudo' => str_replace($item['row']['imagem_capa'], $item['path'], $item['row']['conteudo'])];
        foreach ($desired as $field => $value) {
            if ($row[$field] !== $item['row'][$field] && $row[$field] !== $value) { throw new RuntimeException('Campo mudou desde backup: ' . $field); }
        }
        $destination = $root . '/public/' . $item['path'];
        if ($action === '--apply' && !is_file($destination) && !copy($item['source'], $destination)) { throw new RuntimeException('Falha copiando arte'); }
        if ($action !== '--rollback' && (!is_file($destination) || hash_file('sha256', $destination) !== $item['sha256'])) { throw new RuntimeException('Arte aplicada divergente'); }
        if ($action === '--verify') {
            foreach ($desired as $field => $value) { if ($row[$field] !== $value) { throw new RuntimeException('Referencia incorreta'); } }
        } else {
            $values = $action === '--apply' ? $desired : array_intersect_key($item['row'], $desired);
            $update = $pdo->prepare('UPDATE posts SET imagem_capa=?, imagem_thumb=?, conteudo=? WHERE id=? AND slug=?');
            $update->execute([$values['imagem_capa'], $values['imagem_thumb'], $values['conteudo'], $row['id'], $row['slug']]);
            $stmt->execute($slugs); $after = array_values(array_filter($stmt->fetchAll(), static fn(array $r): bool => $r['id'] === $row['id']))[0];
            foreach ($row as $field => $value) { if (!isset($desired[$field]) && $field !== 'data_atualizacao' && $after[$field] !== $value) { throw new RuntimeException('Outro campo alterado'); } }
        }
        echo 'PASS local: ' . $row['id'] . ' ' . $item['path'] . PHP_EOL;
    }
    $pdo->commit();
} catch (Throwable $e) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $e; }
