<?php

declare(strict_types=1);

// Local Instagram only. No publishing, scheduling or changes to blog articles.
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Repositories\InstagramPostRepository;
use App\Services\Instagram\BlogCrosspostService;

$catalog = json_decode((string) file_get_contents(base_path('resources/reels-review/missing-20261011.json')), true, 512, JSON_THROW_ON_ERROR);
$pdo = $GLOBALS['pdo'];
$repo = new InstagramPostRepository($pdo);
$account = $repo->findActiveAccount();
if ($account === null) { throw new RuntimeException('Conta local não encontrada.'); }
$apply = in_array('--apply', $argv, true);
foreach ($catalog['items'] as $index => $item) {
    $path = base_path('public/' . $item['video']);
    if ($item['state'] !== 'ready' || !is_file($path) || hash_file('sha256', $path) !== $item['sha256']) {
        throw new RuntimeException('Renderização não validável.');
    }
    $key = BlogCrosspostService::idempotencyKey($item['originEnvironment'], (int) $item['articleId']);
    $existing = $repo->findByIdempotencyKey($key);
    if ($existing !== null) {
        if ($existing['video_rendered_path'] !== $item['video']) {
            throw new RuntimeException('Vínculo já existente divergente; preservar o registro.');
        }
        echo 'Já registrado #' . $existing['id'] . PHP_EOL;
        continue;
    }
    if (!$apply) { echo 'DRY RUN: rascunho local #' . $item['id'] . PHP_EOL; continue; }
    $pdo->beginTransaction();
    try {
        $id = $repo->create([
            'account_id' => (int) $account['id'], 'status' => 'rascunho', 'tipo' => 'reels',
            'legenda' => $item['caption'], 'hashtags_count' => 5, 'agendado_para' => null,
            'post_blog_id' => (int) $item['articleId'], 'idempotency_key' => $key, 'origin' => 'local',
            'audio_track_id' => (int) $item['trackId'], 'audio_start_seconds' => 20,
            'audio_duration_seconds' => 20, 'criado_por' => null,
        ]);
        $repo->addMedia($id, [
            'ordem' => 0, 'tipo_arquivo' => 'video', 'caminho' => $item['video'],
            'url_publica' => rtrim((string) config('app.url'), '/') . '/' . $item['video'],
            'largura' => 1080, 'altura' => 1920, 'duracao_s' => 20,
        ]);
        $repo->markRenderedReady($id, $item['video']);
        $pdo->commit();
        $catalog['items'][$index]['instagramPostId'] = $id;
        file_put_contents(base_path('resources/reels-review/missing-20261011.json'), json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
        echo 'Rascunho local registrado #' . $id . PHP_EOL;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
