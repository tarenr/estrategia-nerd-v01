<?php
/**
 * -----------------------------------------------------------------------------
 * @file        scripts/operations/en-instagram-update-diablo-series.php
 * @project     Estrategia Nerd
 * @purpose     Atualizar caminhos de vídeo, capa e trilha dos 6 Reels de Diablo.
 * @usage       php scripts/operations/en-instagram-update-diablo-series.php
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Somente via CLI.\n");
    exit(1);
}

$root = dirname(__DIR__, 2);
require_once $root . '/bootstrap.php';
require_once $root . '/app/Services/Instagram/InstagramApiService.php';

use App\Services\Instagram\InstagramApiService;

/** @var PDO $pdo */
$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "Erro: Conexão PDO não disponível no bootstrap.\n");
    exit(1);
}

$targets = [
    224 => [
        'video' => 'uploads/reels/correcao-instagram-20261010/reel-224-v3.mp4',
        'cover' => 'uploads/reels/correcao-instagram-20261010/reel-224-v3.jpg',
        'audio_track_id' => 25,
    ],
    225 => [
        'video' => 'uploads/reels/correcao-instagram-20261010/reel-225-v3.mp4',
        'cover' => 'uploads/reels/correcao-instagram-20261010/reel-225-v3.jpg',
        'audio_track_id' => 25,
    ],
    226 => [
        'video' => 'uploads/reels/correcao-instagram-20261010/reel-226-v3.mp4',
        'cover' => 'uploads/reels/correcao-instagram-20261010/reel-226-v3.jpg',
        'audio_track_id' => 25,
    ],
    195 => [
        'video' => 'uploads/reels/correcao-instagram-20261010/reel-195-v5.mp4',
        'cover' => 'uploads/reels/correcao-instagram-20261010/reel-195-v5.jpg',
        'audio_track_id' => 25,
    ],
    227 => [
        'video' => 'uploads/reels/correcao-instagram-20261010/reel-227-v3.mp4',
        'cover' => 'uploads/reels/correcao-instagram-20261010/reel-227-v3.jpg',
        'audio_track_id' => 25,
    ],
    197 => [
        'video' => 'uploads/reels/correcao-instagram-20261010/reel-197-v3.mp4',
        'cover' => 'uploads/reels/correcao-instagram-20261010/reel-197-v3.jpg',
        'audio_track_id' => 25,
    ],
];

$ids = array_keys($targets);
$inClause = implode(',', $ids);

echo "=== ATUALIZACAO SERIE DIABLO (#224, #225, #226, #195, #227, #197) ===\n\n";

// 1. Validar integridade dos arquivos fisicos antes de qualquer toque no banco
echo "1. Validando presenca e conformidade dos arquivos fisicos...\n";
$publicRoot = $root . '/public';

foreach ($targets as $id => $info) {
    $fullVideo = $publicRoot . '/' . $info['video'];
    $fullCover = $publicRoot . '/' . $info['cover'];

    if (!is_file($fullVideo) || filesize($fullVideo) === 0) {
        throw new RuntimeException("Arquivo de video ausente ou vazio: {$info['video']}");
    }
    if (!is_file($fullCover) || filesize($fullCover) === 0) {
        throw new RuntimeException("Arquivo de capa ausente ou vazio: {$info['cover']}");
    }

    // Validar validador oficial do publicador InstagramApiService::reelCoverParams
    $params = InstagramApiService::reelCoverParams($info['video'], $publicRoot);
    if (empty($params['cover_url'])) {
        throw new RuntimeException("Falha no reelCoverParams para o post #{$id}");
    }

    echo "  [OK] Post #{$id}: Video (" . round(filesize($fullVideo) / (1024 * 1024), 2) . " MB) + Capa (" . round(filesize($fullCover) / 1024, 1) . " KB)\n";
}

// 2. Backup previo em JSON
echo "\n2. Criando snapshot de backup dos registros no banco...\n";
$backupDir = $root . '/storage/backups/instagram-publish';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$stmtPosts = $pdo->query("SELECT * FROM instagram_posts WHERE id IN ($inClause) ORDER BY id");
$beforePosts = $stmtPosts->fetchAll(PDO::FETCH_ASSOC);

$stmtMedia = $pdo->query("SELECT * FROM instagram_post_media WHERE post_id IN ($inClause) ORDER BY post_id, ordem");
$beforeMedia = $stmtMedia->fetchAll(PDO::FETCH_ASSOC);

$backupData = [
    'timestamp' => date('c'),
    'purpose' => 'Substituicao de videos/capas e unificacao de trilha sonora da serie Diablo (Posts 224, 225, 226, 195, 227, 197)',
    'posts' => $beforePosts,
    'media' => $beforeMedia,
];

$backupFile = $backupDir . '/diablo-series-before-' . date('Ymd-His') . '.json';
file_put_contents($backupFile, json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "  Backup salvo com sucesso em: " . $backupFile . "\n";

// 3. Execucao transacional
echo "\n3. Executando atualizacoes transacionais...\n";
$pdo->beginTransaction();

try {
    $stmtUpdatePost = $pdo->prepare("
        UPDATE instagram_posts
        SET video_rendered_path = :path,
            render_status = 'ready',
            audio_track_id = :audio_id,
            atualizado_em = NOW()
        WHERE id = :id
    ");

    $stmtUpdateMedia = $pdo->prepare("
        UPDATE instagram_post_media
        SET caminho = :path
        WHERE post_id = :id AND tipo_arquivo = 'video' AND ordem = 0
    ");

    foreach ($targets as $id => $info) {
        $stmtUpdatePost->execute([
            ':path' => $info['video'],
            ':audio_id' => $info['audio_track_id'],
            ':id' => $id,
        ]);

        $stmtUpdateMedia->execute([
            ':path' => $info['video'],
            ':id' => $id,
        ]);

        echo "  [DB] Post #{$id} atualizado -> {$info['video']} (audio_track_id={$info['audio_track_id']})\n";
    }

    $pdo->commit();
    echo "\n  Transacao comitada com sucesso!\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "\nErro na transacao. Rollback executado: " . $e->getMessage() . "\n");
    exit(1);
}

// 4. Verificacao pos-commit
echo "\n4. Verificando estado final gravado no banco...\n";
$stmtAfterPosts = $pdo->query("SELECT id, status, tipo, render_status, audio_track_id, video_rendered_path, agendado_para FROM instagram_posts WHERE id IN ($inClause) ORDER BY id");
$afterPosts = $stmtAfterPosts->fetchAll(PDO::FETCH_ASSOC);

$stmtAfterMedia = $pdo->query("SELECT id, post_id, ordem, tipo_arquivo, caminho FROM instagram_post_media WHERE post_id IN ($inClause) ORDER BY post_id");
$afterMedia = $stmtAfterMedia->fetchAll(PDO::FETCH_ASSOC);

$afterData = [
    'timestamp' => date('c'),
    'posts' => $afterPosts,
    'media' => $afterMedia,
];
$afterBackupFile = $backupDir . '/diablo-series-after-' . date('Ymd-His') . '.json';
file_put_contents($afterBackupFile, json_encode($afterData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

foreach ($afterPosts as $row) {
    echo "  Post #{$row['id']}: status={$row['status']}, render={$row['render_status']}, audio={$row['audio_track_id']}, agendado={$row['agendado_para']}\n";
    echo "    video: {$row['video_rendered_path']}\n";
}

echo "\nOperacao concluida com 100% de sucesso.\n";
