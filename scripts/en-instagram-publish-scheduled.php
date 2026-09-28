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
use App\Services\Instagram\InstagramApiService;

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

$account = $repo->findActiveAccount();

if ($account === null) {
    ig_log('warn', 'Nenhuma conta Instagram ativa configurada. Saindo.');
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(0);
}

$accountId = (int) $account['id'];

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
$api       = new InstagramApiService(
    (string) ($account['access_token'] ?? ''),
    (string) ($account['ig_user_id'] ?? ''),
);
$published = 0;
$errors    = 0;

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

        $legenda = $post['legenda'] ?? null;

        // Valida legenda antes de chamar a API
        $validation = $api->validateCaption((string) $legenda);
        if (!$validation['ok']) {
            throw new RuntimeException('Legenda inválida: ' . implode(' ', $validation['errors']));
        }

        // Cria containers e publica conforme o tipo
        $tipo    = (string) ($post['tipo'] ?? 'imagem');
        $isVideo = false;

        if ($tipo === 'carrossel' && count($medias) >= 2) {
            $childIds = [];
            foreach ($medias as $m) {
                $mTipo    = (string) ($m['tipo_arquivo'] ?? 'imagem');
                $mediaUrl = (string) ($m['url_publica'] ?? '');
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
            $isVideo    = true;
            $creationId = $api->createVideoContainer(
                (string) ($first['url_publica'] ?? ''),
                $legenda,
                ['media_type' => 'REELS'],
            );
        } elseif ($tipo === 'story') {
            $first    = $medias[0];
            $mTipo    = (string) ($first['tipo_arquivo'] ?? 'imagem');
            $mediaUrl = (string) ($first['url_publica'] ?? '');
            if ($mTipo === 'video') {
                $isVideo    = true;
                $creationId = $api->createVideoContainer($mediaUrl, null, ['media_type' => 'STORIES']);
            } else {
                $creationId = $api->createImageContainer($mediaUrl, null, ['media_type' => 'STORIES']);
            }
        } else {
            $first      = $medias[0];
            $creationId = $api->createImageContainer(
                (string) ($first['url_publica'] ?? ''),
                $legenda,
            );
        }

        $repo->saveCreationId($postId, $creationId);
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
        $igMediaId = $api->publishMedia($creationId);
        ig_log('info', "Post #{$postId} — ig_media_id: {$igMediaId}");

        // Busca permalink
        $detail    = $api->getMediaDetails($igMediaId);
        $permalink = (string) ($detail['permalink'] ?? '');

        $repo->markPublished($postId, $igMediaId, $permalink);
        ig_log('info', "Post #{$postId} publicado com sucesso. Permalink: {$permalink}");
        $published++;
    } catch (Throwable $e) {
        $errMsg = $e->getMessage();
        $repo->markError($postId, $errMsg);
        ig_log('error', "Post #{$postId} falhou: {$errMsg}");
        $errors++;
    }
}

// ── Relatório Final ───────────────────────────────────────────────────────────
ig_log('info', "=== Fim da execução: {$published} publicados, {$errors} erros ===");

flock($lock, LOCK_UN);
fclose($lock);

exit($errors > 0 ? 1 : 0);
