<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Repositories\InstagramPostRepository;
use App\Services\Instagram\InstagramApiService;
use App\Services\Instagram\BlogCrosspostService;

echo "=== RE-PUBLICAÇÃO DO REEL DO ECHO COM NOVA CAPA HUD ===\n";

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

$videoRelPre = (string) ($post['video_rendered_path'] ?? 'uploads/posts/conheca-o-echo-assistente-estrategia-nerd/reels/reel.mp4');
$mediaUrlPre = InstagramApiService::buildPublicMediaUrl($videoRelPre);
$coverUrlPre = (string) (InstagramApiService::reelCoverParams($videoRelPre, base_path('public'))['cover_url'] ?? '');

echo "0. Valores anteriores do registro: status=" . ($post['status'] ?? '') . " ig_media_id=" . ($post['ig_media_id'] ?? '') . " permalink=" . ($post['permalink'] ?? '') . " creation_id=" . ($post['creation_id'] ?? '') . "\n";

foreach (['Vídeo' => $mediaUrlPre, 'Capa' => $coverUrlPre] as $nome => $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($url === '' || $code !== 200) {
        fwrite(STDERR, "Abortado antes de alterar o banco: {$nome} inacessível (HTTP {$code}).\n");
        exit(1);
    }
}

echo "1. Resetando container anterior e vinculando ao post de Produção (#{$prodPostId})...\n";
$stmt = $localPdo->prepare("
    UPDATE instagram_posts 
    SET post_blog_id = :blog_id,
        idempotency_key = :key,
        creation_id = NULL,
        ig_media_id = NULL,
        permalink = NULL,
        status = 'publicando',
        atualizado_em = NOW()
    WHERE id = :id
");
$stmt->execute([
    ':blog_id' => $prodPostId,
    ':key' => $prodKey,
    ':id' => $postId
]);
echo "Status redefinido para 'publicando' com idempotency_key: {$prodKey}\n";

$api = new InstagramApiService(
    (string) ($account['access_token'] ?? ''),
    (string) ($account['ig_user_id'] ?? '')
);

$videoRel = (string) ($post['video_rendered_path'] ?? 'uploads/posts/conheca-o-echo-assistente-estrategia-nerd/reels/reel.mp4');
$legenda = (string) ($post['legenda'] ?? '');

echo "\n2. Verificando URLs públicas do vídeo e da nova capa oficial...\n";
$mediaUrl = InstagramApiService::buildPublicMediaUrl($videoRel);
$coverParams = InstagramApiService::reelCoverParams($videoRel, base_path('public'));

echo "Vídeo URL: {$mediaUrl}\n";
echo "Capa URL:  {$coverParams['cover_url']}\n";

// Testar acessibilidade via HTTP das mídias
foreach (['Vídeo' => $mediaUrl, 'Capa' => $coverParams['cover_url']] as $nome => $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "Teste HTTP {$nome}: {$code} (OK)\n";
}

echo "\n3. Criando novo container de vídeo REELS na Meta Graph API com cover_url...\n";
$creationId = $api->createVideoContainer(
    $mediaUrl,
    $legenda,
    ['media_type' => 'REELS'] + $coverParams
);

$repo->saveCreationId($postId, $creationId);
echo "Novo container criado na Meta com sucesso! ID: {$creationId}\n";

echo "\n4. Aguardando processamento do container pela Meta...\n";
$maxTries = 20;
$tries = 0;
$containerStatus = 'IN_PROGRESS';

while ($containerStatus !== 'FINISHED' && $tries < $maxTries) {
    sleep(6);
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

echo "\n5. Publicando container no perfil oficial do Instagram...\n";
$mediaId = $api->publishMedia($creationId);
echo "Mídia publicada com sucesso! ID no Instagram: {$mediaId}\n";

echo "\n6. Obtendo permalink oficial...\n";
$permalink = null;
try {
    $details = $api->getMediaDetails($mediaId);
    $permalink = $details['permalink'] ?? null;
    echo "Permalink Oficial: {$permalink}\n";
} catch (\Throwable $e) {
    echo "Aviso ao obter permalink: " . $e->getMessage() . "\n";
}

echo "\n7. Atualizando registro no banco local...\n";
$stmtSuccess = $localPdo->prepare("
    UPDATE instagram_posts 
    SET ig_media_id = :ig_id,
        permalink = :permalink,
        status = 'publicado',
        publicado_em = NOW(),
        atualizado_em = NOW()
    WHERE id = :id
");
$stmtSuccess->execute([
    ':ig_id' => $mediaId,
    ':permalink' => $permalink,
    ':id' => $postId
]);

echo "Sucesso total! Post #{$postId} atualizado com novo Reel publicado.\n";
echo "URL do Reel: {$permalink}\n";
