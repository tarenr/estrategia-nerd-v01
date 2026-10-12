<?php
/**
 * -----------------------------------------------------------------------------
 * @file        scripts/sync-instagram-feed-assets.php
 * @project     Estrategia Nerd
 * @purpose     Sincroniza posts da Meta, atualiza BD com thumbnail_url de Reels,
 *              baixa as capas locais para public/assets/instagram-feed/ e
 *              atualiza o snapshot de cache config/instagram-feed.json.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Repositories\InstagramPostRepository;
use App\Services\Instagram\InstagramApiService;
use App\Services\Instagram\InstagramFeedReconciler;
use App\Services\Instagram\InstagramProfileAvatar;
use App\Services\Site\InstagramFeedService;

// --only-missing: modo automatico; so baixa capas que ainda nao existem em disco (sem rebaixar as ~92 a cada rodada).
$onlyMissing = in_array('--only-missing', $argv ?? [], true);

/** @var \PDO $pdo */
$pdo = $GLOBALS['pdo'];
$repo = new InstagramPostRepository($pdo);
$account = $repo->findActiveAccount();

if ($account === null) {
    fwrite(STDERR, "Erro: Nenhuma conta do Instagram ativa encontrada no banco.\n");
    exit(1);
}

$accessToken = (string) ($account['access_token'] ?? '');
$igUserId    = (string) ($account['ig_user_id'] ?? '');

if ($accessToken === '' || $igUserId === '') {
    fwrite(STDERR, "Erro: Credenciais de acesso à Meta API incompletas.\n");
    exit(1);
}

echo "=== 1. Conectando à Meta Graph API v21.0 ===\n";
$api = new InstagramApiService($accessToken, $igUserId);

try {
    // 0 = busca todas as mídias da conta com paginação completa
    $feedRes = $api->getMediaFeed(0);
    $apiItems = $feedRes['data'] ?? [];
    echo "Sucesso: " . count($apiItems) . " publicacoes obtidas da Meta API.\n\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "Erro ao consultar Meta Graph API: " . $e->getMessage() . "\n");
    exit(1);
}

echo "=== 2. Atualizando banco de dados local com capas de Reels ===\n";
$apiMapByIgId = [];
$upsertCount = 0;

foreach ($apiItems as $item) {
    $igMediaId = (string) ($item['id'] ?? '');
    if ($igMediaId === '') {
        continue;
    }
    $apiMapByIgId[$igMediaId] = $item;

    $tipo = match ((string) ($item['media_type'] ?? '')) {
        'VIDEO'          => 'reels',
        'CAROUSEL_ALBUM' => 'carrossel',
        default          => 'imagem',
    };

    $caption = (string) ($item['caption'] ?? '');
    preg_match_all('/#\w+/u', $caption, $matches);
    $hashtagsCount = count($matches[0]);

    $publishedAt = null;
    if (!empty($item['timestamp'])) {
        $publishedAt = date('Y-m-d H:i:s', strtotime((string) $item['timestamp']) ?: time());
    }

    $repo->upsertFromFeed([
        'account_id'        => (int) $account['id'],
        'tipo'              => $tipo,
        'legenda'           => $caption,
        'hashtags_count'    => $hashtagsCount,
        'curtidas'          => (int) ($item['like_count'] ?? 0),
        'comentarios_count' => (int) ($item['comments_count'] ?? 0),
        'ig_media_id'       => $igMediaId,
        'permalink'         => (string) ($item['permalink'] ?? ''),
        'publicado_em'      => $publishedAt,
        'media_url'         => (string) ($item['media_url'] ?? ''),
        'thumbnail_url'     => (string) ($item['thumbnail_url'] ?? ''),
    ]);
    $upsertCount++;
}
echo "Total de posts sincronizados/atualizados no banco: {$upsertCount}\n\n";

echo "=== 2a. Atualizando o perfil (seguidores, midias e foto) ===\n";
// A URL da foto vence em ~14 dias: a foto e guardada em arquivo local. Falha aqui nunca derruba o sync dos posts.
try {
    $avatarFile = base_path('public' . InstagramProfileAvatar::REL_PATH);
    $profile = $api->getProfile();
    if (!InstagramProfileAvatar::isCompleteProfile($profile)) {
        echo "Aviso: perfil incompleto devolvido pela Meta; banco e foto mantidos.\n\n";
    } else {
        $repo->syncAccount((int) $account['id'], $profile);
        echo "Perfil atualizado: " . (int) ($profile['followers_count'] ?? 0) . " seguidores, " . (int) ($profile['media_count'] ?? 0) . " midias.\n";

        if ($onlyMissing && InstagramProfileAvatar::isFresh($avatarFile, 86400)) {
            echo "Foto de perfil local recente (mantida).\n\n";
        } else {
            $ch = curl_init((string) $profile['profile_picture_url']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) EstrategiaNerd/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $avatarBytes = curl_exec($ch);
            $avatarCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($avatarCode === 200 && is_string($avatarBytes) && InstagramProfileAvatar::save($avatarBytes, $avatarFile)) {
                echo "Foto de perfil salva em public" . InstagramProfileAvatar::REL_PATH . " (" . round(strlen($avatarBytes) / 1024, 1) . " KB).\n\n";
            } else {
                echo "Aviso: foto de perfil nao baixada (HTTP {$avatarCode} ou imagem invalida); foto anterior mantida.\n\n";
            }
        }
    }
} catch (\Throwable $e) {
    echo "Aviso: perfil nao atualizado (" . $e->getMessage() . "); posts seguem normalmente.\n\n";
}

echo "=== 2b. Reconciliando posts apagados no Instagram ===\n";
// A Meta e a fonte da verdade: post "publicado" que sumiu da listagem completa vira "cancelado"
// e deixa de aparecer no feed (listPublished so devolve status 'publicado').
$reconcile = InstagramFeedReconciler::findOrphans(
    $repo->listPublished((int) $account['id'], 100000),
    array_keys($apiMapByIgId)
);
if ($reconcile['aborted'] !== null) {
    echo "Aviso: " . $reconcile['aborted'] . "\n\n";
} elseif ($reconcile['orphans'] === []) {
    echo "Nenhum post publicado ausente da Meta.\n\n";
} else {
    $note = '[' . date('Y-m-d H:i:s') . '] Removido do Instagram (ausente da listagem da Meta); status anterior: publicado.';
    $cancelled = $repo->markRemovedFromInstagram($reconcile['orphans'], $note);
    echo "Posts cancelados por ausencia na Meta: {$cancelled} (ids: " . implode(', ', $reconcile['orphans']) . ")\n\n";
}

echo "=== 3. Baixando e persistindo capas em public/assets/instagram-feed/ ===\n";
$targetDir = base_path('public/assets/instagram-feed');
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

// Capa local de todos os posts publicados: o admin mostra todos; o feed do site usa os 18 mais recentes.
$published = $repo->listPublished((int) $account['id'], max(18, count($apiItems)));
$total = count($published);
echo "Encontrados {$total} posts publicados.\n";

$downloadedCount = 0;
$reusedCount = 0;
$failCount = 0;

foreach ($published as $index => $post) {
    $postId = (int) $post['id'];
    $igMediaId = (string) ($post['ig_media_id'] ?? '');
    $targetFile = $targetDir . '/post_' . $postId . '.jpg';

    // Determinar a melhor URL de imagem da publicação
    $imageUrl = '';
    if (isset($apiMapByIgId[$igMediaId])) {
        $apiItem = $apiMapByIgId[$igMediaId];
        if (($apiItem['media_type'] ?? '') === 'VIDEO' && !empty($apiItem['thumbnail_url'])) {
            $imageUrl = (string) $apiItem['thumbnail_url'];
        } elseif (!empty($apiItem['media_url']) && ($apiItem['media_type'] ?? '') !== 'VIDEO') {
            $imageUrl = (string) $apiItem['media_url'];
        } elseif (!empty($apiItem['thumbnail_url'])) {
            $imageUrl = (string) $apiItem['thumbnail_url'];
        }
    }

    if ($imageUrl === '') {
        $medias = explode('|', (string) ($post['medias'] ?? ''));
        $first = trim($medias[0] ?? '');
        if (str_starts_with($first, 'http')) {
            $imageUrl = $first;
        }
    }

    $num = $index + 1;
    echo sprintf("[%02d/%02d] Post #%d (%s): ", $num, $total, $postId, $post['tipo']);

    if ($onlyMissing && is_file($targetFile) && filesize($targetFile) > 1000) {
        echo "EXISTENTE (mantido)\n";
        $reusedCount++;
        continue;
    }

    // Se já temos a imagem válida em disco e tem mais de 2KB, mantemos ou atualizamos
    if ($imageUrl !== '') {
        $ch = curl_init($imageUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) EstrategiaNerd/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $binary = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && is_string($binary) && strlen($binary) > 1000) {
            file_put_contents($targetFile, $binary);
            $sizeKb = round(strlen($binary) / 1024, 1);
            echo "OK (Baixado {$sizeKb} KB) -> post_{$postId}.jpg\n";
            $downloadedCount++;
            continue;
        }
    }

    // Se falhou o download mas já tínhamos o arquivo local
    if (is_file($targetFile) && filesize($targetFile) > 1000) {
        $sizeKb = round(filesize($targetFile) / 1024, 1);
        echo "REAPROVEITADO (Arquivo local existente: {$sizeKb} KB)\n";
        $reusedCount++;
        continue;
    }

    echo "FALHA ao obter imagem!\n";
    $failCount++;
}

echo "\nResultado do download: {$downloadedCount} baixados, {$reusedCount} reaproveitados, {$failCount} falhas.\n\n";

echo "=== 4. Atualizando snapshot de cache em config/instagram-feed.json ===\n";
$feedService = new InstagramFeedService($repo);
$feed = $feedService->getFeed(18);

if ($feed !== null && !empty($feed['posts'])) {
    $feedPosts = $feed['posts'];
    $validCount = 0;
    foreach ($feedPosts as $p) {
        if (!empty($p['media_url'])) {
            $validCount++;
        }
    }
    echo "Feed atualizado com sucesso: {$validCount}/" . count($feedPosts) . " posts possuem media_url definida.\n";
} else {
    fwrite(STDERR, "Aviso: Falha ao obter dados do feed atualizados.\n");
}

echo "\n=== Sincronização concluída com sucesso! ===\n";
