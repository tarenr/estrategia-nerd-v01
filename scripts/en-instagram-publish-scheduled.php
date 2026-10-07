<?php
/**
 * -----------------------------------------------------------------------------
 * @file        scripts/en-instagram-publish-scheduled.php
 * @project     Estrategia Nerd
 * @purpose     CLI: publicar posts agendados do Instagram com lock atômico (FEAT-010)
 * @usage       C:\xampp\php\php.exe scripts/en-instagram-publish-scheduled.php
 *              (Recomendado: executar via Task Scheduler a cada 5 minutos)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

// Previne execução via browser
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script é exclusivo para execução via CLI.' . PHP_EOL);
}

require_once __DIR__ . '/../bootstrap.php';

use App\Repositories\InstagramPostRepository;
use App\Services\Instagram\AudioReelGeneratorService;
use App\Services\Instagram\InstagramApiService;
use App\Services\Instagram\InstagramPublishConfirmationService;

// ── Configuração de Logs ──────────────────────────────────────────────────────
$logDir  = __DIR__ . '/../storage/logs/instagram/';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . 'publish-' . date('Y-m') . '.log';

/**
 * Grava uma linha de log no arquivo mensal e no STDOUT.
 */
function ig_log(string $level, string $message): void
{
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

// ── Lock Global de Processo ───────────────────────────────────────────────────
$lockFile = sys_get_temp_dir() . '/en-instagram-publish.lock';
$lock     = fopen($lockFile, 'w');

if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    ig_log('info', 'Outra instância do script já está em execução. Saindo.');
    exit(0);
}

ig_log('info', '=== Início da execução ===');

// ── Inicialização ─────────────────────────────────────────────────────────────
/** @var \PDO $pdo */
$pdo  = $GLOBALS['pdo'];
$repo = new InstagramPostRepository($pdo);

// Antes da conta: recuperar presos nao depende da Meta nem de conta ativa.
// 90 min > limite de 60 min da tarefa agendada; com o flock, nenhuma outra instancia esta publicando.
try {
    $stale = $repo->markStalePublishingAsError(90);
    if ($stale > 0) {
        ig_log('warn', "Posts presos em 'publicando' marcados como erro: {$stale}.");
    }
} catch (Throwable $e) {
    ig_log('error', 'Falha ao recuperar posts presos em publicando: ' . $e->getMessage());
}

$account = $repo->findActiveAccount();

if ($account === null) {
    ig_log('warn', 'Nenhuma conta Instagram ativa configurada. Saindo.');
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(0);
}

$accountId = (int) $account['id'];
$api = new InstagramApiService((string) ($account['access_token'] ?? ''), (string) ($account['ig_user_id'] ?? ''));
$confirmation = new InstagramPublishConfirmationService($repo, $api);
// Reconciliation only reads Meta; never re-sends an uncertain media_publish.
try {
    foreach ($repo->listPublishConfirmations($accountId) as $pendingPost) {
        try { $confirmation->reconcile((int) $pendingPost['id']); }
        catch (Throwable) { ig_log('warn', 'Confirmação local pendente para post #' . (int) $pendingPost['id'] . '; sem novo envio.'); }
    }
} catch (Throwable) { ig_log('warn', 'Não foi possível consultar as confirmações pendentes; nenhuma tentativa reenviada.'); }

// ── Busca Posts Agendados ─────────────────────────────────────────────────────
$pdo->beginTransaction();

try {
    $duePosts = $repo->findDueScheduled();
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    ig_log('error', 'Falha ao buscar posts agendados: ' . $e->getMessage());
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(1);
}

if (empty($duePosts)) {
    ig_log('info', 'Nenhum post agendado pendente. Saindo.');
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(0);
}

ig_log('info', 'Posts agendados encontrados: ' . count($duePosts));

// ── Publicação ────────────────────────────────────────────────────────────────
$published = 0;
$errors    = 0;
$pending = 0;

foreach ($duePosts as $post) {
    $postId = (int) $post['id'];

    // Lock atômico: só processa se conseguiu mudar status de 'agendado' → 'publicando'
    if (!$repo->lockForPublishing($postId)) {
        ig_log('info', "Post #{$postId} já está sendo processado por outra instância. Pulando.");
        continue;
    }

    ig_log('info', "Publicando post #{$postId} (tipo: {$post['tipo']})...");

    try {
        $medias = $repo->findMediaByPostId($postId);

        if (empty($medias)) {
            throw new RuntimeException('Nenhuma mídia encontrada para o post.');
        }

        // Post antigo pode ter combinacao invalida (antes das travas): vai para erro sem chamar a Meta.
        $hasAudio = !empty($post['audio_track_id']);
        $ruleError = InstagramPostRepository::mediaRuleError((string) ($post['tipo'] ?? ''), InstagramPostRepository::mediaKinds($medias), false, $hasAudio);
        if ($ruleError !== null) {
            throw new RuntimeException('Mídias incompatíveis com o tipo do post (nada foi enviado ao Instagram): ' . $ruleError);
        }

        $legenda = $post['legenda'] ?? null;

        // Valida legenda antes de chamar a API
        $validation = $api->validateCaption((string) $legenda);
        if (!$validation['ok']) {
            throw new RuntimeException('Legenda inválida: ' . implode(' ', $validation['errors']));
        }

        // Cria containers e publica conforme o tipo
        $tipo    = (string) ($post['tipo'] ?? 'imagem');
        $isVideo = false;

        if ($hasAudio) {
            $renderedRel = AudioReelGeneratorService::readyVideoPath($post, base_path('public'));
            if ($renderedRel === null) {
                $audioTrack = $repo->findAudioTrack((int) $post['audio_track_id']);
                if ($audioTrack === null) {
                    throw new RuntimeException('Trilha sonora associada ao post não foi encontrada no banco.');
                }
                if (\App\Services\Instagram\EditorialReelService::isLinked($post)) {
                    $result = \App\Services\Instagram\EditorialReelService::regenerate($post, $audioTrack);
                    $renderedRel = $result['video_path'];
                    $repo->markRenderedReady($postId, $renderedRel, $result['duration']);
                } else {
                    $reelGen = AudioReelGeneratorService::fromGlobals();
                    $imagePaths = [];
                    foreach ($medias as $m) {
                        $p = trim((string) ($m['caminho'] ?? ''));
                        if ($p !== '') {
                            $imagePaths[] = $p;
                        }
                    }
                    if ($imagePaths === []) {
                        throw new RuntimeException('Ao menos uma imagem é necessária para gerar o Reel com áudio.');
                    }
                    $startSec = (int) ($post['audio_start_seconds'] ?? 0);
                    $durSec   = (int) ($post['audio_duration_seconds'] ?? 0);
                    $renderedRel = $reelGen->generateReel(
                        $imagePaths,
                        (string) $audioTrack['arquivo_path'],
                        $startSec,
                        $durSec > 0 ? $durSec : null
                    );
                    $repo->markRenderedReady($postId, $renderedRel);
                }
            }

            $reelVideoUrl = InstagramApiService::buildPublicMediaUrl($renderedRel);
            $cover        = InstagramApiService::reelCoverParams($renderedRel, base_path('public'));
            ig_log('info', "Post #{$postId} — capa validada: " . $cover['cover_url']);

            $isVideo = true;
            $creationId = $api->createVideoContainer(
                (string) $reelVideoUrl,
                $legenda,
                ['media_type' => 'REELS'] + $cover,
            );
        } elseif ($tipo === 'carrossel' && count($medias) >= 2) {
            $childIds = [];
            foreach ($medias as $m) {
                $mTipo    = (string) ($m['tipo_arquivo'] ?? 'imagem');
                $mediaUrl = InstagramApiService::buildPublicMediaUrl((string) ($m['caminho'] ?? $m['url_publica'] ?? ''));
                if ($mTipo === 'video') {
                    $isVideo    = true;
                    $childIds[] = $api->createVideoContainer($mediaUrl, null, ['is_carousel_item' => 'true']);
                } else {
                    $childIds[] = $api->createImageContainer($mediaUrl, null, ['is_carousel_item' => 'true']);
                }
                usleep(500_000); // 0.5s entre containers
            }
            $creationId = $api->createCarouselContainer($childIds, $legenda);
        } elseif ($tipo === 'reels') {
            $first      = $medias[0];
            $firstPath  = (string) ($first['caminho'] ?? $first['url_publica'] ?? '');
            $mediaUrl   = InstagramApiService::buildPublicMediaUrl($firstPath);
            $cover      = InstagramApiService::reelCoverParams($firstPath, base_path('public'));
            ig_log('info', "Post #{$postId} — capa validada: " . $cover['cover_url']);
            $isVideo    = true;
            $creationId = $api->createVideoContainer(
                $mediaUrl,
                $legenda,
                ['media_type' => 'REELS'] + $cover,
            );
        } elseif ($tipo === 'story') {
            $first    = $medias[0];
            $mTipo    = (string) ($first['tipo_arquivo'] ?? 'imagem');
            $mediaUrl = InstagramApiService::buildPublicMediaUrl((string) ($first['caminho'] ?? $first['url_publica'] ?? ''));
            if ($mTipo === 'video') {
                $isVideo    = true;
                $creationId = $api->createVideoContainer($mediaUrl, null, ['media_type' => 'STORIES']);
            } else {
                $creationId = $api->createImageContainer($mediaUrl, null, ['media_type' => 'STORIES']);
            }
        } else {
            $first      = $medias[0];
            $mediaUrl   = InstagramApiService::buildPublicMediaUrl((string) ($first['caminho'] ?? $first['url_publica'] ?? ''));
            $creationId = $api->createImageContainer(
                $mediaUrl,
                $legenda,
            );
        }

        if (!$repo->saveCreationId($postId, $creationId)) { throw new RuntimeException('Container não registrado; nada publicado.'); }
        ig_log('info', "Post #{$postId} — container criado: {$creationId} (tipo: {$tipo})");

        // Polling do status do container (vídeos têm tempo limite maior: até 60s)
        $maxTries = $isVideo ? 12 : 6;
        $containerStatus = 'IN_PROGRESS';
        $tries = 0;
        while ($containerStatus !== 'FINISHED' && $tries < $maxTries) {
            sleep(5);
            $containerStatus = $api->checkContainerStatus($creationId);
            ig_log('info', "Post #{$postId} — status container: {$containerStatus} (tentativa " . ($tries + 1) . "/{$maxTries})");
            $tries++;
            if ($containerStatus === 'ERROR' || $containerStatus === 'EXPIRED') {
                throw new RuntimeException("Falha no container Meta (status: {$containerStatus}).");
            }
        }

        if ($containerStatus !== 'FINISHED') {
            throw new RuntimeException("Container não ficou pronto após {$tries} tentativas (status: {$containerStatus}).");
        }

        // Publica
        $result = $confirmation->publish($postId, $creationId);
        if ($result['state'] === 'published') {
            ig_log('info', "Post #{$postId} publicado com sucesso. ID Meta: " . $result['media_id']);
            $published++;
        } elseif ($result['state'] === 'pending') {
            ig_log('warn', "Post #{$postId}: aguardando confirmação. Não será reenviado automaticamente.");
            $pending++;
        } else { ig_log('error', "Post #{$postId}: publicação rejeitada pela Meta."); $errors++; }
    } catch (Throwable $e) {
        $errMsg = $e->getMessage();
        $repo->markError($postId, $errMsg);
        ig_log('error', "Post #{$postId} falhou: {$errMsg}");
        $errors++;
    }
}

// ── Relatório Final ───────────────────────────────────────────────────────────
ig_log('info', "=== Fim da execução: {$published} publicados, {$errors} erros, {$pending} aguardando confirmação ===");

flock($lock, LOCK_UN);
fclose($lock);

exit($errors > 0 ? 1 : 0);
