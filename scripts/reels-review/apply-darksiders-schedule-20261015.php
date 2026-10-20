<?php
declare(strict_types=1);

// Script operacional para aplicar agendamento sequencial da saga Darksiders
// Blog em produção: 29/10 a 03/11 às 12:00 (status = agendado)
// Instagram local: 29/10 a 03/11 às 19:30 (status = agendado)

require __DIR__ . '/../../bootstrap.php';

use App\Support\TargetEnvironmentDatabase;

$schedule = [
    [
        'blog_id' => 52,
        'slug' => 'o-mundo-de-darksiders-ceu-inferno-e-o-equilibrio',
        'date_blog' => '2026-10-29 12:00:00',
        'date_ig' => '2026-10-29 19:30:00',
        'video' => 'reel-cap01-motion.mp4',
        'titulo' => 'O mundo de Darksiders: Céu, Inferno e o Equilíbrio explicados',
        'resumo' => 'Explore o universo sombrio de Darksiders e conheça o conflito primordial entre Céu e Inferno, a criação do Conselho das Cinzas e os Quatro Cavaleiros.',
    ],
    [
        'blog_id' => 53,
        'slug' => 'os-nephilim-a-raca-proibida-de-darksiders',
        'date_blog' => '2026-10-30 12:00:00',
        'date_ig' => '2026-10-30 19:30:00',
        'video' => 'reel-cap02-motion.mp4',
        'titulo' => 'Os Nephilim: a raça proibida de Darksiders explicada',
        'resumo' => 'Descubra a origem dos Nephilim, a união proibida entre anjos e demônios, e por que os Quatro Cavaleiros escolheram aniquilar sua própria espécie.',
    ],
    [
        'blog_id' => 54,
        'slug' => 'os-quatro-cavaleiros-de-darksiders',
        'date_blog' => '2026-10-31 12:00:00',
        'date_ig' => '2026-10-31 19:30:00',
        'video' => 'reel-cap03-motion.mp4',
        'titulo' => 'Os Quatro Cavaleiros: quem são os executores do Equilíbrio em Darksiders',
        'resumo' => 'Conheça Guerra, Morte, Fúria e Conflito: seus poderes, armamentos lendários e o juramento inquebrável que os transformou nas armas definitivas do Conselho.',
    ],
    [
        'blog_id' => 55,
        'slug' => 'o-apocalipse-antecipado-de-darksiders',
        'date_blog' => '2026-11-01 12:00:00',
        'date_ig' => '2026-11-01 19:30:00',
        'video' => 'reel-cap04-motion.mp4',
        'titulo' => 'O Apocalipse Antecipado: a conspiração que condenou a Terra em Darksiders',
        'resumo' => 'Entenda como os Sete Selos foram corrompidos, a conspiração secreta que destruiu a humanidade antes da hora e a acusação injusta que recaiu sobre Guerra.',
    ],
    [
        'blog_id' => 56,
        'slug' => 'a-jornada-de-morte-em-darksiders',
        'date_blog' => '2026-11-02 12:00:00',
        'date_ig' => '2026-11-02 19:30:00',
        'video' => 'reel-cap05-motion.mp4',
        'titulo' => 'A Jornada de Morte: o sacrifício do primogênito em Darksiders II',
        'resumo' => 'Acompanhe a missão solitária de Morte para redimir seu irmão Guerra, enfrentando a Corrupção que devora reinos e ressuscitando a humanidade.',
    ],
    [
        'blog_id' => 57,
        'slug' => 'a-furia-do-equilibrio-em-darksiders',
        'date_blog' => '2026-11-03 12:00:00',
        'date_ig' => '2026-11-03 19:30:00',
        'video' => 'reel-cap06-motion.mp4',
        'titulo' => 'A Fúria do Equilíbrio: a caçada aos Sete Pecados em Darksiders III',
        'resumo' => 'A jornada de amadurecimento de Fúria ao caçar os Sete Pecados Capitais na Terra arruinada, descobrindo traições e assumindo a proteção do que restou dos humanos.',
    ],
];

// 1. Atualizar posts de produção
$prod = TargetEnvironmentDatabase::pdo('production');
$prod->beginTransaction();
try {
    $st = $prod->prepare("UPDATE posts SET status = 'agendado', data_publicacao = :data WHERE id = :id AND slug = :slug");
    foreach ($schedule as $item) {
        $st->execute([
            'data' => $item['date_blog'],
            'id' => $item['blog_id'],
            'slug' => $item['slug'],
        ]);
        if ($st->rowCount() !== 1) {
            throw new RuntimeException("Falha ao atualizar post ID {$item['blog_id']}");
        }
    }
    $prod->commit();
    echo "PASS: 6 posts em produção atualizados para 'agendado' (12:00).\n";
} catch (Throwable $e) {
    if ($prod->inTransaction()) {
        $prod->rollBack();
    }
    throw $e;
}

// 2. Inserir reels na fila do Instagram local
$local = $GLOBALS['pdo'];
$local->beginTransaction();
try {
    // Obter account_id padrão do Instagram
    $accountId = (int) $local->query("SELECT id FROM instagram_accounts LIMIT 1")->fetchColumn();
    if ($accountId <= 0) {
        $accountId = 1;
    }

    $insPost = $local->prepare("
        INSERT INTO instagram_posts (
            account_id, status, tipo, legenda, hashtags_count, agendado_para,
            post_blog_id, origin, render_status, video_rendered_path
        ) VALUES (
            :account_id, 'agendado', 'reels', :legenda, 5, :agendado_para,
            :post_blog_id, 'local', 'ready', :video_path
        )
    ");

    $insMedia = $local->prepare("
        INSERT INTO instagram_post_media (
            post_id, ordem, tipo_arquivo, caminho, largura, altura, duracao_s
        ) VALUES (
            :post_id, 0, 'video', :caminho, 1080, 1920, 26
        )
    ");

    foreach ($schedule as $item) {
        // Verificar se já existe agendamento deste reel
        $check = $local->prepare("SELECT id FROM instagram_posts WHERE post_blog_id = :blog_id AND status != 'cancelado'");
        $check->execute(['blog_id' => $item['blog_id']]);
        $existingId = $check->fetchColumn();
        if ($existingId) {
            echo "Aviso: Já existe post no Instagram para blog_id {$item['blog_id']}. Atualizando agendamento...\n";
            $up = $local->prepare("UPDATE instagram_posts SET agendado_para = :agendado_para, status = 'agendado' WHERE id = :id");
            $up->execute(['agendado_para' => $item['date_ig'], 'id' => $existingId]);
            continue;
        }

        $caption = "{$item['titulo']}\n\n{$item['resumo']}\n\nLeia o artigo completo no blog - link na bio.\n\n#EstratégiaNerd #Darksiders #Games #Gamer #CulturaGeek";
        $videoRelative = "uploads/reels/{$item['video']}";

        $insPost->execute([
            'account_id' => $accountId,
            'legenda' => $caption,
            'agendado_para' => $item['date_ig'],
            'post_blog_id' => $item['blog_id'],
            'video_path' => $videoRelative,
        ]);
        $newPostId = (int) $local->lastInsertId();

        $insMedia->execute([
            'post_id' => $newPostId,
            'caminho' => $videoRelative,
        ]);
        echo "PASS: Reel para Cap. '{$item['slug']}' agendado com sucesso (ID local: $newPostId para {$item['date_ig']}).\n";
    }

    $local->commit();
    echo "PASS: Fila do Instagram atualizada com sucesso!\n";
} catch (Throwable $e) {
    if ($local->inTransaction()) {
        $local->rollBack();
    }
    throw $e;
}
