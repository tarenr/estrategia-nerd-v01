<?php

declare(strict_types=1);

// Approved editorial correction, limited to production article 43. No Instagram writes.
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Repositories\PostRepository;
use App\Support\TargetEnvironmentDatabase;
use App\Support\TargetEnvironmentUploads;

$action = $argv[1] ?? '';
if (!in_array($action, ['--backup', '--apply', '--verify'], true)) {
    throw new RuntimeException('Use --backup, --apply ou --verify.');
}
$pdo = TargetEnvironmentDatabase::pdo('production');
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$dir = base_path('storage/editorial-5700x-20261011');
if (!is_dir($dir)) { mkdir($dir, 0700, true); }
$backupPath = $dir . '/before.json';
$rows = $pdo->query('SELECT * FROM posts ORDER BY id')->fetchAll();
$byId = array_column($rows, null, 'id');
$current = $byId[43] ?? null;
if ($current === null || $current['slug'] !== 'amd-ryzen-7-5700x-desempenho-real-no-meu-setup') {
    throw new RuntimeException('Identidade do artigo divergiu.');
}
if ($action === '--backup') {
    if (!is_file($backupPath)) {
        $handle = fopen($backupPath, 'x');
        if ($handle === false) { throw new RuntimeException('Não foi possível preservar o backup.'); }
        fwrite($handle, json_encode(['posts' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        fclose($handle);
    }
    foreach (['imagem_capa', 'imagem_thumb'] as $field) {
        $destination = $dir . '/' . $field . '-before.webp';
        if (is_file($destination)) { continue; }
        $bytes = file_get_contents('https://estrategianerd.com.br/' . $current[$field]);
        if ($bytes === false || getimagesizefromstring($bytes) === false) { throw new RuntimeException('Backup da imagem falhou.'); }
        file_put_contents($destination, $bytes);
    }
    echo "Backup de registros e imagens preservado.\n";
    exit;
}
if (!is_file($backupPath)) { throw new RuntimeException('Backup obrigatório ausente.'); }
$before = json_decode((string) file_get_contents($backupPath), true, 512, JSON_THROW_ON_ERROR);
$previous = array_column($before['posts'], null, 'id');
$patch = json_decode((string) file_get_contents(base_path('resources/reels-review/review-5700x-20261011.json')), true, 512, JSON_THROW_ON_ERROR);
$allowed = ['titulo','resumo','conteudo','seo_title','seo_description','tipo_post','imagem_capa','imagem_thumb','proximo_post_id'];
if (array_diff(array_keys($patch['fields']), $allowed) !== []) { throw new RuntimeException('Campo fora do escopo.'); }
$incoming = $pdo->query('SELECT id FROM posts WHERE proximo_post_id=43')->fetchAll();
if ($incoming !== []) { throw new RuntimeException('Vínculo de entrada mudou desde a inspeção; preservar e revisar.'); }
if (($byId[32]['slug'] ?? '') !== 'am4-ou-am5-em-2026' || $byId[32]['status'] !== 'publicado') {
    throw new RuntimeException('Próximo artigo indisponível.');
}
$asset = base_path('public/' . $patch['fields']['imagem_capa']);
if (!is_file($asset) || hash_file('sha256', $asset) !== $patch['assetSha256']) { throw new RuntimeException('Arte divergente.'); }
if ($action === '--apply') {
    $isApplied = true;
    foreach ($patch['fields'] as $field => $value) {
        if ($current[$field] != $value) { $isApplied = false; }
        if ($current[$field] != $value && $current[$field] != $previous[43][$field]) { throw new RuntimeException('Mudança concorrente em ' . $field); }
    }
    if (!$isApplied) {
        TargetEnvironmentUploads::putFile('production', $asset, $patch['fields']['imagem_capa']);
        $remote = file_get_contents('https://estrategianerd.com.br/' . $patch['fields']['imagem_capa']);
        if ($remote === false || hash('sha256', $remote) !== $patch['assetSha256']) { throw new RuntimeException('Imagem remota não validada; banco preservado.'); }
        $pdo->beginTransaction();
        try {
            $lock = $pdo->query('SELECT * FROM posts WHERE id=43 FOR UPDATE')->fetch();
            foreach ($patch['fields'] as $field => $value) {
                if ($lock[$field] != $current[$field]) { throw new RuntimeException('Artigo mudou durante a aplicação.'); }
            }
            (new PostRepository($pdo))->updateAdmin(43, array_replace($lock, $patch['fields']));
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }
    }
    echo "Correção aplicada em produção ao artigo 43.\n";
}
$after = array_column($pdo->query('SELECT * FROM posts ORDER BY id')->fetchAll(), null, 'id');
foreach ($patch['fields'] as $field => $value) {
    if ($after[43][$field] != $value) { throw new RuntimeException('Campo não aplicado: ' . $field); }
}
$volatile = ['views','curtidas','likes_count','comentarios_count','data_atualizacao'];
foreach ($previous as $id => $row) {
    foreach ($row as $field => $value) {
        if (in_array($field, $volatile, true) || ($id === 43 && in_array($field, $allowed, true))) { continue; }
        if (!isset($after[$id]) || $after[$id][$field] !== $value) { throw new RuntimeException('Campo fora do escopo alterado: ' . $id . '/' . $field); }
    }
}
file_put_contents($dir . '/validation.json', json_encode(['articleId'=>43,'environment'=>'production','nextPostId'=>32,'previousArticlesPreserved'=>true,'assetSha256'=>$patch['assetSha256'],'verifiedAt'=>gmdate('c')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo "PASS: review publicado, próximo artigo 32, campos restantes e outros artigos preservados.\n";
