<?php
/**
 * -----------------------------------------------------------------------------
 * @file        scripts/operations/en-instagram-standardize-all-reels.php
 * @project     Estrategia Nerd
 * @purpose     Migrar todos os Reels e capas para o padrão oficial:
 *              public/uploads/posts/<slug>/reels/reel.mp4 e capa.jpg.
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

$publicRoot = $root . '/public';

// Mapeamento preciso de cada post ativo para seu slug oficial
$postSlugMap = [
    190 => '16gb-ou-32gb-ram-pc-gamer-2026',
    195 => 'a-guerra-eterna-o-conflito-que-nunca-teve-fim',
    197 => 'a-lore-completa-de-diablo-entenda-toda-a-historia-do-universo',
    198 => 'o-setup-que-sustenta-o-estrategia-nerd-a-maquina-por-tras-de-tudo',
    199 => 'amd-ryzen-5-7600x-desempenho-real-no-meu-setup',
    200 => 'msi-mag-b650-tomahawk-wifi-a-base-que-sustenta-um-setup-de-verdade',
    201 => 'rtx-3050-6gb-a-escolha-inteligente-para-um-setup-equilibrado',
    202 => 'corsair-vengeance-ddr5-32gb-6000mhz-equilibrio-ideal-para-setups-modernos',
    203 => 'kingston-nv3-1tb-nvme-pcie-4-0-velocidade-real-para-o-dia-a-dia',
    204 => 'erros-comuns-ao-montar-um-pc-gamer-e-como-evitar',
    205 => '10-jogos-que-todo-fa-de-rpg-precisa-jogar-antes-de-morrer',
    206 => 'teste-rtx-3050-vs-rx-6600-qual-vale-mais-a-pena-em-2026',
    207 => 'pc-liga-mas-nao-da-video-checklist-diagnostico',
    208 => 'the-witcher-3-wild-hunt-remastered-chega-gr-atis-dia-29-de-setembro',
    210 => 'rtx-5060-vs-rx-9060-xt-1080p',
    211 => 'am4-ou-am5-em-2026',
    212 => 'como-escolher-fonte-para-pc',
    213 => 'como-verificar-saude-ssd-hd',
    214 => 'temperatura-cpu-gpu-quando-se-preocupar',
    215 => '12-jogos-leves-pc-fraco-ou-modesto',
    216 => 'historia-counter-strike',
    217 => '10-filmes-nerds-essenciais',
    218 => 'animes-essenciais-para-iniciantes',
    219 => 'desenhos-anos-90-2000-que-envelheceram-bem',
    224 => 'o-mundo-de-diablo-entenda-santuario-ceu-e-inferno',
    225 => 'os-arcanjos-do-ceu-a-verdade-por-tras-da-luz-em-diablo',
    226 => 'os-males-do-inferno-em-diablo-quem-realmente-controla-o-caos',
    227 => 'os-nefalem-o-verdadeiro-poder-por-tras-da-humanidade',
    229 => 'muito-alem-dos-jumpscares-3-jogos-que-transformam-a-atmosfera-em-verdadeiro-terror',
    230 => 'muito-alem-dos-jumpscares-3-jogos-que-transformam-a-atmosfera-em-verdadeiro-terror',
    231 => 'como-o-som-cria-medo-nos-jogos-a-ciencia-por-tras-da-atmosfera-de-terror',
    232 => 'jogos-para-quem-so-tem-30-minutos-por-dia',
    233 => 'jogos-para-quem-so-tem-30-minutos-por-dia',
    234 => 'como-voltar-a-um-jogo-depois-de-meses-sem-jogar',
    243 => 'como-voltar-a-um-jogo-depois-de-meses-sem-jogar',
    244 => 'amd-ryzen-7-5700x-desempenho-real-no-meu-setup',
    246 => 'windows-11-24h2-fim-do-suporte-em-13-de-outubro-de-2026',
    5251 => 'apresentacao-estrategia-nerd',
];

// Identificar colisões para criar subpastas limpas (ex: reel-229 vs reel-230)
$counts = array_count_values($postSlugMap);
$sharedSlugs = array_keys(array_filter($counts, fn($c) => $c > 1));

echo "=== PADRONIZACAO GERAL DE REELS E CAPAS ===\n\n";

// 1. Consultar posts ativos com vídeo
$ids = array_keys($postSlugMap);
$inClause = implode(',', $ids);
$stmt = $pdo->query("SELECT * FROM instagram_posts WHERE id IN ($inClause) ORDER BY id");
$activePosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "1. Total de posts a padronizar: " . count($activePosts) . "\n";

// 2. Snapshot de Backup Preventivo em JSON
echo "\n2. Criando snapshot preventivo no banco de dados...\n";
$backupDir = $root . '/storage/backups/instagram-publish';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$stmtMedia = $pdo->query("SELECT * FROM instagram_post_media WHERE post_id IN ($inClause) ORDER BY post_id, ordem");
$activeMedia = $stmtMedia->fetchAll(PDO::FETCH_ASSOC);

$backupData = [
    'timestamp' => date('c'),
    'purpose' => 'Padronizacao de todas as pastas de reels para public/uploads/posts/<slug>/reels/',
    'posts' => $activePosts,
    'media' => $activeMedia,
];
$backupFile = $backupDir . '/all-reels-before-' . date('Ymd-His') . '.json';
file_put_contents($backupFile, json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "  [OK] Backup salvo em: " . $backupFile . "\n";

// 3. ETAPA DE CÓPIA PARA OS DESTINOS FINAIS
echo "\n3. Executando etapa de copia (sem apagar os arquivos originais)...\n";

$migrations = [];

foreach ($activePosts as $post) {
    $id = (int) $post['id'];
    $slug = $postSlugMap[$id] ?? 'post-' . $id;

    // Se mais de um post compartilha o mesmo slug, usar subpasta limpa com o ID
    if (in_array($slug, $sharedSlugs, true)) {
        $destRelFolder = "uploads/posts/{$slug}/reels/reel-{$id}";
    } else {
        $destRelFolder = "uploads/posts/{$slug}/reels";
    }

    $destFullFolder = $publicRoot . '/' . $destRelFolder;
    if (!is_dir($destFullFolder)) {
        mkdir($destFullFolder, 0755, true);
    }

    $currentVideoRel = (string) $post['video_rendered_path'];
    $currentVideoFull = $publicRoot . '/' . $currentVideoRel;

    if (!is_file($currentVideoFull) || filesize($currentVideoFull) === 0) {
        throw new RuntimeException("Video de origem ausente para o post #{$id}: {$currentVideoRel}");
    }

    $destVideoRel = $destRelFolder . '/reel.mp4';
    $destVideoFull = $publicRoot . '/' . $destVideoRel;
    $destCoverRel = $destRelFolder . '/capa.jpg';
    $destCoverFull = $publicRoot . '/' . $destCoverRel;

    // Copiar vídeo
    copy($currentVideoFull, $destVideoFull);

    // Copiar ou extrair capa oficial (.jpg)
    $sourceCoverByVideo = preg_replace('#\.mp4$#i', '.jpg', $currentVideoFull);
    $sourceCoverStandard = dirname($currentVideoFull) . '/capa.jpg';

    if (is_file($sourceCoverStandard) && filesize($sourceCoverStandard) > 0) {
        copy($sourceCoverStandard, $destCoverFull);
    } elseif (is_file($sourceCoverByVideo) && filesize($sourceCoverByVideo) > 0) {
        copy($sourceCoverByVideo, $destCoverFull);
    } else {
        // Extrair frame aos 3.2s via ffmpeg
        $cmd = sprintf(
            'ffmpeg -y -ss 00:00:03.200 -i %s -vframes 1 -q:v 2 %s 2>&1',
            escapeshellarg($destVideoFull),
            escapeshellarg($destCoverFull)
        );
        exec($cmd, $out, $ret);
        if ($ret !== 0 || !is_file($destCoverFull) || filesize($destCoverFull) === 0) {
            throw new RuntimeException("Falha ao extrair capa para o post #{$id}");
        }
    }

    // Validar com o validador do publicador
    $coverCheck = InstagramApiService::reelCoverParams($destVideoRel, $publicRoot);
    if (empty($coverCheck['cover_url'])) {
        throw new RuntimeException("Falha na validacao reelCoverParams para #{$id}: {$destVideoRel}");
    }

    $migrations[$id] = [
        'new_video' => $destVideoRel,
        'new_cover' => $destCoverRel,
        'cover_url' => $coverCheck['cover_url'],
    ];

    echo "  [COPIADO] Post #{$id} -> {$destVideoRel} | Capa: {$destCoverRel}\n";
}

echo "  [OK] Todos os " . count($migrations) . " posts copiados e com capas validadas!\n";

// 4. ATUALIZACAO TRANSACIONAL NO BANCO DE DADOS
echo "\n4. Atualizando banco de dados com transacao segura...\n";
$pdo->beginTransaction();

try {
    $stmtUpdatePost = $pdo->prepare("
        UPDATE instagram_posts
        SET video_rendered_path = :path,
            atualizado_em = NOW()
        WHERE id = :id
    ");

    $stmtUpdateMedia = $pdo->prepare("
        UPDATE instagram_post_media
        SET caminho = :path
        WHERE post_id = :id AND tipo_arquivo = 'video' AND ordem = 0
    ");

    foreach ($migrations as $id => $info) {
        $stmtUpdatePost->execute([
            ':path' => $info['new_video'],
            ':id' => $id,
        ]);
        $stmtUpdateMedia->execute([
            ':path' => $info['new_video'],
            ':id' => $id,
        ]);
    }

    $pdo->commit();
    echo "  [OK] Transacao no banco comitada com sucesso!\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Erro na transacao. Rollback executado: " . $e->getMessage() . "\n");
    exit(1);
}

// 5. TESTES E VERIFICACOES FINAIS
echo "\n5. Executando testes e verificacoes de consistencia...\n";
$stmtCheck = $pdo->query("SELECT id, video_rendered_path FROM instagram_posts WHERE id IN ($inClause)");
$verifiedCount = 0;
while ($row = $stmtCheck->fetch(PDO::FETCH_ASSOC)) {
    $params = InstagramApiService::reelCoverParams($row['video_rendered_path'], $publicRoot);
    if (!empty($params['cover_url'])) {
        $verifiedCount++;
    }
}
echo "  [OK] Validador oficial da Meta validou com sucesso: {$verifiedCount} / " . count($migrations) . " posts.\n";

if ($verifiedCount !== count($migrations)) {
    fwrite(STDERR, "Erro: nem todos os posts foram validados com sucesso. Limpeza abortada.\n");
    exit(1);
}

// 6. ETAPA DE LIMPEZA (SOMENTE APÓS 100% DE SUCESSO COMPROVADO)
echo "\n6. Executando etapa de limpeza segura da pasta temporaria...\n";
$archiveBase = $root . '/storage/archive-temp-reels';
if (!is_dir($archiveBase)) {
    mkdir($archiveBase, 0755, true);
}

$tempFolder = $publicRoot . '/uploads/reels/correcao-instagram-20261010';
$archiveFolder = $archiveBase . '/backup-correcao-20261010-' . date('Ymd-His');

if (is_dir($tempFolder)) {
    echo "  Movendo pasta temporaria para arquivo de seguranca em: {$archiveFolder}...\n";
    rename($tempFolder, $archiveFolder);
    echo "  [OK] Pasta temporaria arquivada com seguranca em storage/ e removida de public/uploads/reels/!\n";
}

// Mover também novos-20261011 e apresentacao-20261013 se existirem
foreach (['novos-20261011', 'apresentacao-20261013'] as $extra) {
    $p = $publicRoot . '/uploads/reels/' . $extra;
    if (is_dir($p)) {
        $dest = $archiveBase . '/' . $extra . '-' . date('Ymd-His');
        rename($p, $dest);
        echo "  [OK] Pasta temporaria {$extra} arquivada e removida de public/uploads/reels/!\n";
    }
}

echo "\n=== MIGRACAO E PADRONIZACAO CONCLUIDA COM 100% DE SUCESSO ===\n";
