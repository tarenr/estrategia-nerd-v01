<?php
/**
 * -----------------------------------------------------------------------------
 * @file        scripts/en-instagram-sync-blog-reels.php
 * @project     Estrategia Nerd
 * @purpose     Sincroniza posts reais publicados em PRODUÇÃO para o Instagram
 *              local gerando rascunhos de Reels 9:16 com a nova arte editorial
 *              limpa (Smart Canvas), chamada de impacto e trilha sonora automática.
 *
 * Uso:
 *   php scripts/en-instagram-sync-blog-reels.php [--dry-run] [--limit=N] [--post-id=ID] [--force] [--motion]
 * --motion e opt-in: o hook automatico existente continua no template estatico.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$sessionPath = dirname(__DIR__) . '/storage/sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0777, true);
}
ini_set('session.save_path', $sessionPath);

require dirname(__DIR__) . '/bootstrap.php';

use App\Repositories\InstagramPostRepository;
use App\Services\Instagram\AudioReelGeneratorService;
use App\Services\Instagram\BlogCrosspostService;
use App\Services\Instagram\EditorialMotionReelRenderer;
use App\Services\Instagram\GeminiCaptionService;
use App\Services\Instagram\InstagramApiService;
use App\Services\Instagram\TrackPickerService;
use App\Support\TargetEnvironmentDatabase;

$isDryRun = in_array('--dry-run', $argv, true);
$force = in_array('--force', $argv, true);
$animated = in_array('--motion', $argv, true);
$includeScheduled = in_array('--include-scheduled', $argv, true);
$limit = 0;
$targetPostId = 0;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int) substr($arg, 8));
    }
    if (str_starts_with($arg, '--post-id=')) {
        $targetPostId = max(1, (int) substr($arg, 10));
    }
}

echo "\n=======================================================\n";
echo "   ESTRATÉGIA NERD — SINCRONIZAÇÃO BLOG -> INSTAGRAM REELS\n";
echo "=======================================================\n";
echo sprintf("Modo: %s | Limite: %s | Post Específico: %s | Incluir Agendados: %s\n\n",
    $isDryRun ? 'DRY-RUN (Simulação)' : 'EXECUÇÃO REAL',
    $limit > 0 ? (string)$limit : 'Sem limite',
    $targetPostId > 0 ? '#' . $targetPostId : 'Todos os pendentes',
    $includeScheduled ? 'SIM' : 'NÃO'
);

try {
    $prodPdo = TargetEnvironmentDatabase::pdo('production');
} catch (Throwable $e) {
    fwrite(STDERR, "ERRO: Falha ao conectar no banco de produção: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

/** @var PDO $localPdo */
$localPdo = $GLOBALS['pdo'];
$igRepo = new InstagramPostRepository($localPdo);
$activeAccount = $igRepo->findActiveAccount();

if ($activeAccount === null) {
    fwrite(STDERR, "ERRO: Nenhuma conta ativa do Instagram encontrada no banco local.\n");
    exit(1);
}

$reelGenerator = AudioReelGeneratorService::fromGlobals();
$captionService = GeminiCaptionService::fromEnv();
$apiService = new InstagramApiService('', '');

// 1. Seletor de trilhas: faixa sem uso do grupo da categoria; não baixa do Audius (IMP-032)
$trackPicker = new TrackPickerService($localPdo);

// 2. Buscar posts em produção (publicados ou agendados conforme opção)
$statusList = $includeScheduled ? "'publicado', 'agendado'" : "'publicado'";
$sql = "SELECT id, titulo, slug, categoria, resumo, conteudo, imagem_capa, data_publicacao, tags, status 
          FROM posts 
         WHERE status IN ($statusList)";
$params = [];

if ($targetPostId > 0) {
    // Se um ID específico foi pedido, permite mesmo que não seja 'publicado' por padrão
    if (!$includeScheduled) {
        $sql = "SELECT id, titulo, slug, categoria, resumo, conteudo, imagem_capa, data_publicacao, tags, status 
                  FROM posts 
                 WHERE id = ?";
        $params = [$targetPostId];
    } else {
        $sql .= " AND id = ?";
        $params[] = $targetPostId;
    }
}
$sql .= " ORDER BY id ASC";

if ($limit > 0) {
    $sql .= " LIMIT " . (int) $limit;
}

$stmt = $prodPdo->prepare($sql);
$stmt->execute($params);
$prodPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo sprintf("Total de posts publicados encontrados em produção: %d\n\n", count($prodPosts));

$createdCount = 0;
$skippedCount = 0;
$failedCount = 0;

foreach ($prodPosts as $post) {
    $postId = (int) $post['id'];
    $slug = (string) ($post['slug'] ?? '');
    $tituloOriginal = (string) ($post['titulo'] ?? '');
    $categoria = strtolower(trim((string) ($post['categoria'] ?? '')));
    if ($categoria === '') {
        $categoria = 'cultura';
    }
    $resumo = (string) ($post['resumo'] ?? '');
    $coverRel = (string) ($post['imagem_capa'] ?? '');

    // Limpar marcações wiki [[termo]]
    $cleanTitulo = trim((string) preg_replace('/\[\[(.*?)\]\]/', '$1', $tituloOriginal));
    $cleanResumo = trim((string) preg_replace('/\[\[(.*?)\]\]/', '$1', $resumo));

    $idempotencyKey = BlogCrosspostService::idempotencyKey('production', $postId);

    echo sprintf("-------------------------------------------------------\n");
    echo sprintf("[Post #%02d] %s\n", $postId, mb_substr($cleanTitulo, 0, 50));
    echo sprintf("Categoria: %s | Slug: %s\n", $categoria, $slug);

    // Verificar se já existe no Instagram local
    $existing = $igRepo->findByIdempotencyKey($idempotencyKey);
    if ($existing !== null && !$force) {
        echo sprintf("  -> [SKIP] Já existe rascunho no Instagram (ID #%d, Status: %s)\n", $existing['id'], $existing['status']);
        $skippedCount++;
        continue;
    }
    if ($existing !== null && !in_array((string) ($existing['status'] ?? ''), ['rascunho', 'erro'], true)) {
        echo "  -> [SKIP] Recriacao por --force permitida somente para rascunhos ou erros; status preservado.\n";
        $skippedCount++;
        continue;
    }

    if ($isDryRun) {
        echo $animated ? "  -> [DRY-RUN] Seria gerado Reel animado + Rascunho com audio.\n"
            : "  -> [DRY-RUN] Seria gerado Smart Canvas 9:16 + Reel MP4 + Rascunho com áudio.\n";
        $createdCount++;
        continue;
    }

    try {
        // A. Assegurar imagem de capa localmente
        $coverAbs = base_path('public/' . ltrim($coverRel, '/\\'));
        if (!is_file($coverAbs) || filesize($coverAbs) === 0) {
            echo "  -> Capa não encontrada localmente. Baixando de produção...\n";
            $prodMediaUrl = 'https://estrategianerd.com.br/' . ltrim($coverRel, '/');
            $dir = dirname($coverAbs);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException("Não foi possível criar pasta: {$dir}");
            }
            $imgData = @file_get_contents($prodMediaUrl);
            if ($imgData === false || strlen($imgData) < 1000) {
                throw new RuntimeException("Falha ao baixar imagem de capa de produção: {$prodMediaUrl}");
            }
            file_put_contents($coverAbs, $imgData);
            echo "  -> Capa baixada com sucesso (" . filesize($coverAbs) . " bytes).\n";
        }

        // C. Metadados do Smart Canvas
        $hookTitle = match ($categoria) {
            'hardware' => 'TESTAMOS NA PRÁTICA:',
            'games'    => 'ANÁLISE DEFINITIVA:',
            'dicas'    => 'DICA ESSENCIAL:',
            default    => 'GUIA COMPLETO:',
        };

        $hookText = $cleanResumo;
        if (mb_strlen($hookText) > 170) {
            $hookText = mb_substr($hookText, 0, 167) . '...';
        }

        $meta = [
            'titulo'         => $cleanTitulo,
            'resumo'         => $cleanResumo,
            'categoria'      => $categoria,
            'chamada_titulo' => $hookTitle,
            'chamada_texto'  => $hookText,
            'cta_texto'      => 'VER TESTES E ANÁLISE COMPLETA',
        ];

        // B. Trilha sem repetição (IMP-032): duração prevista do Reel define o trecho sorteado
        $plannedSeconds = $animated ? (new EditorialMotionReelRenderer())->duration($meta, 12) : 12;
        $picked = $trackPicker->pick($categoria, $plannedSeconds);
        if ($picked === null) {
            throw new RuntimeException("Nenhuma trilha ativa na biblioteca para a categoria {$categoria}. Cadastre faixas com scripts/en-instagram-import-tracks.php.");
        }
        $audioRelPath = (string) $picked['track']['arquivo_path'];
        $audioTrackId = (int) $picked['track']['id'];
        $audioStart = $picked['start'];
        echo sprintf("  -> Trilha: #%d - %s (%s) a partir de %ds%s\n", $audioTrackId, $picked['track']['titulo'],
            $picked['track']['genero'], $audioStart, $picked['reused'] ? ' [repetida: biblioteca esgotada]' : '');

        // D. Gerar Reel 9:16 com áudio
        echo "  -> Renderizando Smart Canvas 9:16 e Reel em vídeo via FFmpeg...\n";
        $reelResult = $reelGenerator->generateEditorialReel(
            $coverRel,
            $audioRelPath,
            $meta,
            $audioStart,
            12, // O template animado amplia a duracao conforme o tempo de leitura.
            null,
            $animated
        );

        $videoRel = $reelResult['video_path'];
        $videoAbs = base_path('public/' . ltrim($videoRel, '/\\'));
        echo sprintf("  -> Reel gerado: %s (%d KB)\n", $videoRel, (int)(filesize($videoAbs) / 1024));

        // E. Gerar Legenda
        $captionData = $captionService->generate($cleanTitulo, $cleanResumo, $categoria);
        $legendaFinal = trim($captionData['caption']);
        if ($legendaFinal === '') {
            $legendaFinal = $cleanTitulo . "\n\n" . $cleanResumo;
        }

        $rawTags = $captionData['hashtags'];
        $cleanedTags = [];
        foreach ($rawTags as $tag) {
            if (trim($tag) !== '') {
                $cleanedTags[] = str_starts_with(trim($tag), '#') ? trim($tag) : '#' . trim($tag);
            }
        }
        $hashtagsFinal = implode(' ', $cleanedTags);

        $fullCaption = $legendaFinal;
        if ($hashtagsFinal !== '') {
            $fullCaption .= "\n\n" . $hashtagsFinal;
        }

        $hashtagsCount = $apiService->countHashtags($fullCaption);

        // F. Salvar no Banco Local (instagram_posts e instagram_post_media)
        $localPdo->beginTransaction();

        $accountId = (int) $activeAccount['id'];
        $authorId = 1;

        if ($existing !== null) {
            $igPostId = (int) $existing['id'];
            $upd = $localPdo->prepare(
                'UPDATE instagram_posts 
                    SET tipo = "reels", legenda = :legenda, hashtags_count = :ht,
                        audio_track_id = :audio_id, audio_start_seconds = :audio_start, audio_duration_seconds = :duration,
                        status = "rascunho", atualizado_em = NOW()
                  WHERE id = :id'
            );
            $upd->execute([
                ':legenda'  => $fullCaption,
                ':ht'       => $hashtagsCount,
                ':audio_id' => $audioTrackId,
                ':audio_start' => $audioStart,
                ':duration' => $reelResult['duration'],
                ':id'       => $igPostId,
            ]);

            $igRepo->deleteMediaByPostId($igPostId);
        } else {
            $igPostId = $igRepo->create([
                'account_id'             => $accountId,
                'status'                 => 'rascunho',
                'tipo'                   => 'reels',
                'legenda'                => $fullCaption,
                'hashtags_count'         => $hashtagsCount,
                'agendado_para'          => null,
                'post_blog_id'           => $postId,
                'idempotency_key'        => $idempotencyKey,
                'origin'                 => 'local',
                'audio_track_id'         => $audioTrackId,
                'audio_start_seconds'    => $audioStart,
                'audio_duration_seconds' => $reelResult['duration'],
                'criado_por'             => $authorId,
            ]);
        }

        $appUrl = rtrim((string) config('app.url', 'http://127.0.0.1:8000'), '/');
        $publicVideoUrl = $appUrl . '/' . ltrim($videoRel, '/');

        $igRepo->addMedia($igPostId, [
            'ordem'        => 0,
            'tipo_arquivo' => 'video',
            'caminho'      => $videoRel,
            'url_publica'  => $publicVideoUrl,
            'largura'      => 1080,
            'altura'       => 1920,
            'duracao_s'    => $reelResult['duration'],
        ]);

        // Depois de addMedia: alteracoes de midia invalidam qualquer cache anterior.
        $igRepo->markRenderedReady($igPostId, $videoRel);

        $localPdo->commit();

        echo sprintf("  -> [SUCESSO] Rascunho de Reel criado no Instagram (#ID %d)!\n", $igPostId);
        $createdCount++;

    } catch (Throwable $e) {
        if ($localPdo->inTransaction()) {
            $localPdo->rollBack();
        }
        fwrite(STDERR, sprintf("  -> [FALHA] Post #%d: %s\n", $postId, $e->getMessage()));
        $failedCount++;
    }
}

echo "\n=======================================================\n";
echo sprintf("RESUMO DA EXECUÇÃO:\n");
echo sprintf("Criados: %d | Ignorados: %d | Falhas: %d | Total: %d\n",
    $createdCount, $skippedCount, $failedCount, count($prodPosts)
);
echo "=======================================================\n\n";

exit($failedCount > 0 ? 1 : 0);
