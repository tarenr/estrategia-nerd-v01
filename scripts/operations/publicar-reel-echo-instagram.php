<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Repositories\InstagramPostRepository;
use App\Services\Instagram\InstagramApiService;
use App\Services\Instagram\BlogCrosspostService;

echo "=== INICIANDO PUBLICAÇÃO DO REEL ECHO NA META GRAPH API ===\n";

$localPdo = $GLOBALS['pdo'];
$repo = new InstagramPostRepository($localPdo);

$postId = 10300;
$post = $repo->findById($postId);
if ($post === null) {
    throw new RuntimeException("Post #{$postId} não encontrado no banco local.");
}

$account = $repo->findActiveAccount();
if ($account === null) {
    throw new RuntimeException("Nenhuma conta ativa do Instagram encontrada.");
}

$prodPostId = 51;
$prodKey = BlogCrosspostService::idempotencyKey('production', $prodPostId);

echo "1. Vinculando post #{$postId} ao post de Produção (#{$prodPostId})...\n";
$stmt = $localPdo->prepare("
    UPDATE instagram_posts 
    SET post_blog_id = :blog_id,
        idempotency_key = :key,
        status = 'publicando',
        atualizado_em = NOW()
    WHERE id = :id
");
$stmt->execute([
    ':blog_id' => $prodPostId,
    ':key' => $prodKey,
    ':id' => $postId
]);
echo "Vínculo atualizado com chave: {$prodKey}\n";

$api = new InstagramApiService(
    (string) ($account['access_token'] ?? ''),
    (string) ($account['ig_user_id'] ?? '')
);

$videoRel = (string) ($post['video_rendered_path'] ?? 'uploads/posts/conheca-o-echo-assistente-estrategia-nerd/reels/reel.mp4');
$legenda = (string) ($post['legenda'] ?? '');

echo "\n2. Preparando mídias públicas do Reel...\n";
$mediaUrl = InstagramApiService::buildPublicMediaUrl($videoRel);
$coverParams = InstagramApiService::reelCoverParams($videoRel, base_path('public'));

echo "Vídeo URL: {$mediaUrl}\n";
echo "Capa URL: {$coverParams['cover_url']}\n";

$creationId = (string) ($post['creation_id'] ?? '');
if ($creationId !== '') {
    echo "\n3. Verificando container existente na Meta (ID: {$creationId})...\n";
    $statusCheck = $api->checkContainerStatus($creationId);
    echo "Status atual do container existente: {$statusCheck}\n";
    if ($statusCheck === 'FINISHED') {
        $containerStatus = 'FINISHED';
    } else {
        $creationId = '';
    }
}

if ($creationId === '') {
    echo "\n3. Criando novo container de vídeo REELS na Meta...\n";
    $creationId = $api->createVideoContainer(
        $mediaUrl,
        $legenda,
        ['media_type' => 'REELS'] + $coverParams
    );

    $repo->saveCreationId($postId, $creationId);
    echo "Container criado com ID: {$creationId}\n";
}

echo "\n4. Aguardando processamento do container pela Meta...\n";
$maxTries = 15;
$tries = 0;
$containerStatus = $containerStatus ?? 'IN_PROGRESS';

while ($containerStatus !== 'FINISHED' && $tries < $maxTries) {
    sleep(5);
    $tries++;
    $containerStatus = $api->checkContainerStatus($creationId);
    echo "Tentativa {$tries}/{$maxTries} - Status: {$containerStatus}\n";

    if ($containerStatus === 'ERROR' || $containerStatus === 'EXPIRED') {
        $errorMsg = "Meta retornou status {$containerStatus} para o container {$creationId}.";
        $repo->markError($postId, $errorMsg);
        throw new RuntimeException($errorMsg);
    }
}

if ($containerStatus !== 'FINISHED') {
    $errorMsg = "Timeout aguardando processamento do container pela Meta ({$creationId}).";
    $repo->markError($postId, $errorMsg);
    throw new RuntimeException($errorMsg);
}

echo "\n5. Publicando container no perfil do Instagram...\n";
$mediaId = $api->publishMedia($creationId);
echo "Mídia publicada com sucesso! ID no Instagram: {$mediaId}\n";

echo "\n6. Obtendo permalink oficial...\n";
$permalink = null;
try {
    $details = $api->getMediaDetails($mediaId);
    $permalink = $details['permalink'] ?? null;
    echo "Permalink: {$permalink}\n";
} catch (\Throwable $e) {
    echo "Aviso ao obter permalink: " . $e->getMessage() . "\n";
}

if ($permalink === null || $permalink === '') {
    $permalink = "https://www.instagram.com/reel/{$mediaId}/";
}

$repo->markPublished($postId, $mediaId, $permalink);

echo "\n=== REEL #{$postId} PUBLICADO COM SUCESSO NO INSTAGRAM ===\n";
echo "ID: {$mediaId}\n";
echo "Link: {$permalink}\n";
