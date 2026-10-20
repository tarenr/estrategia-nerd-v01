<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

$pdo = $GLOBALS['pdo'];

$accountId = 1;
$postBlogId = 88;
$tipo = 'reels';
$status = 'rascunho';
$renderStatus = 'ready';
$videoPath = 'uploads/posts/conheca-o-echo-assistente-estrategia-nerd/reels/reel.mp4';
$origin = 'local';

$legenda = <<<TEXT
O Estratégia Nerd tem um assistente. 🤖

O nome dele é Echo: um robô de mesa que mostra o que o Claude Code, o Antigravity e o Codex estão fazendo, avisa quando precisa de permissão, responde por voz e guarda o que você pede para lembrar.

E com o Dia das Bruxas chegando, ele já se vestiu de Jack. A Luna, esposa dele, está de Sally. 🎃

O artigo completo, com o vídeo de 1 min 44 s e tudo que ele faz, está no blog. Link na bio!

#EstratégiaNerd #Echo #AssistenteVirtual #ClaudeCode #IA #Programação #Dev #Tech #Nerd #Halloween #DiaDasBruxas #OEstranhoMundoDeJack
TEXT;

$hashtagsCount = 11;

try {
    // Verifica se já existe um reel vinculado a este post do blog
    $stmtCheck = $pdo->prepare('SELECT id FROM instagram_posts WHERE post_blog_id = :post_blog_id AND tipo = "reels"');
    $stmtCheck->execute(['post_blog_id' => $postBlogId]);
    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $id = (int) $existing['id'];
        $stmtUpdate = $pdo->prepare('
            UPDATE instagram_posts SET
                legenda = :legenda,
                hashtags_count = :hashtags_count,
                video_rendered_path = :video_rendered_path,
                render_status = :render_status,
                status = :status,
                atualizado_em = NOW()
            WHERE id = :id
        ');
        $stmtUpdate->execute([
            'legenda' => $legenda,
            'hashtags_count' => $hashtagsCount,
            'video_rendered_path' => $videoPath,
            'render_status' => $renderStatus,
            'status' => $status,
            'id' => $id,
        ]);
        echo "Reel atualizado com sucesso no módulo Instagram! ID: {$id}\n";
    } else {
        $stmtInsert = $pdo->prepare('
            INSERT INTO instagram_posts (
                account_id, status, tipo, legenda, hashtags_count,
                post_blog_id, video_rendered_path, render_status,
                origin, criado_em, atualizado_em
            ) VALUES (
                :account_id, :status, :tipo, :legenda, :hashtags_count,
                :post_blog_id, :video_rendered_path, :render_status,
                :origin, NOW(), NOW()
            )
        ');
        $stmtInsert->execute([
            'account_id' => $accountId,
            'status' => $status,
            'tipo' => $tipo,
            'legenda' => $legenda,
            'hashtags_count' => $hashtagsCount,
            'post_blog_id' => $postBlogId,
            'video_rendered_path' => $videoPath,
            'render_status' => $renderStatus,
            'origin' => $origin,
        ]);
        $id = (int) $pdo->lastInsertId();
        echo "Reel cadastrado com sucesso no módulo Instagram! ID: {$id}\n";
    }

    echo "Status: {$status}\n";
    echo "Tipo: {$tipo}\n";
    echo "Post Blog ID: {$postBlogId}\n";
    echo "Video Rendered Path: {$videoPath}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Erro ao cadastrar Reel: " . $e->getMessage() . "\n");
    exit(1);
}
