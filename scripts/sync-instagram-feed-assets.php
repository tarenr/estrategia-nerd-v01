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
use App\Services\Site\InstagramFeedService;

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
    $feedRes = $api->getMediaFeed(35);
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

echo "=== 3. Baixando e persistindo capas em public/assets/instagram-feed/ ===\n";
$targetDir = base_path('public/assets/instagram-feed');
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

// Buscar as 18 publicações que vão para o feed
$published = $repo->listPublished((int) $account['id'], 18);
echo "Encontrados " . count($published) . " posts publicados para o feed.\n";

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
    echo sprintf("[%02d/18] Post #%d (%s): ", $num, $postId, $post['tipo']);

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
