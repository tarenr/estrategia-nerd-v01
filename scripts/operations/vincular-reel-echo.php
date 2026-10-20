<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Services\Instagram\BlogCrosspostService;

$pdo = $GLOBALS['pdo'];

$igPostId = 10300;
$blogPostId = 88;
$environment = 'local';
$idempotencyKey = BlogCrosspostService::idempotencyKey($environment, $blogPostId);
$videoPath = 'uploads/posts/conheca-o-echo-assistente-estrategia-nerd/reels/reel.mp4';

try {
    // 1. Atualizar idempotency_key em instagram_posts
    $stmtUpdate = $pdo->prepare('
        UPDATE instagram_posts SET
            idempotency_key = :idempotency_key,
            post_blog_id = :post_blog_id,
            video_rendered_path = :video_rendered_path,
            atualizado_em = NOW()
        WHERE id = :id
    ');
    $stmtUpdate->execute([
        'idempotency_key' => $idempotencyKey,
        'post_blog_id' => $blogPostId,
        'video_rendered_path' => $videoPath,
        'id' => $igPostId,
    ]);
    echo "1. instagram_posts #{$igPostId} atualizado com idempotency_key: {$idempotencyKey}\n";

    // 2. Inserir ou atualizar em instagram_post_media
    $stmtCheckMedia = $pdo->prepare('SELECT id FROM instagram_post_media WHERE post_id = :post_id');
    $stmtCheckMedia->execute(['post_id' => $igPostId]);
    $existingMedia = $stmtCheckMedia->fetch(PDO::FETCH_ASSOC);

    if ($existingMedia) {
        $stmtUpdateMedia = $pdo->prepare('
            UPDATE instagram_post_media SET
                tipo_arquivo = "video",
                caminho = :caminho,
                largura = 1080,
                altura = 1920,
                duracao_s = 60
            WHERE id = :id
        ');
        $stmtUpdateMedia->execute([
            'caminho' => $videoPath,
            'id' => (int) $existingMedia['id'],
        ]);
        echo "2. instagram_post_media atualizado (ID: {$existingMedia['id']})\n";
    } else {
        $stmtInsertMedia = $pdo->prepare('
            INSERT INTO instagram_post_media (
                post_id, ordem, tipo_arquivo, caminho, largura, altura, duracao_s, criado_em
            ) VALUES (
                :post_id, 0, "video", :caminho, 1080, 1920, 60, NOW()
            )
        ');
        $stmtInsertMedia->execute([
            'post_id' => $igPostId,
            'caminho' => $videoPath,
        ]);
        $mediaId = (int) $pdo->lastInsertId();
        echo "2. instagram_post_media inserido (ID: {$mediaId})\n";
    }

    // 3. Teste do vinculo via BlogCrosspostService
    $crosspostService = BlogCrosspostService::fromGlobals();
    $linked = $crosspostService->findLinked($environment, $blogPostId);

    if (is_array($linked)) {
        echo "3. Vínculo validado com SUCESSO!\n";
        echo "   - Instagram Post ID: {$linked['id']}\n";
        echo "   - Status: {$linked['status']}\n";
        echo "   - Mídia: {$linked['_media']}\n";
        echo "   - É Reels: " . ($linked['_is_reels'] ? 'SIM' : 'NÃO') . "\n";
        echo "   - Editável pelo blog: " . ($linked['_editable'] ? 'SIM' : 'NÃO (correto para Reels)') . "\n";
    } else {
        throw new RuntimeException("Falha: findLinked retornou null para post #{$blogPostId}");
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Erro ao vincular Reel: " . $e->getMessage() . "\n");
    exit(1);
}
