<?php
/**
 * -----------------------------------------------------------------------------
 * en-blog-publish-scheduled.php
 * Publica automaticamente (agendado -> publicado) qualquer post cuja
 * data_publicacao ja tenha chegado, no banco do ambiente onde executa.
 *
 * Generico: nao depende de lista fixa de post_id (ao contrario do antigo
 * en-blog-schedule-publish.php, que segue existindo, intocado, so pra
 * leva FEAT-008). Idempotente: so mexe em post com status='agendado' e
 * data_publicacao <= agora; post ja publicado ou com data futura e ignorado.
 *
 * Uso:
 *   php scripts/en-blog-publish-scheduled.php [--remote-production] [--dry-run]
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }

date_default_timezone_set('America/Sao_Paulo');

$sessionPath = dirname(__DIR__) . '/storage/sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0777, true);
}
ini_set('session.save_path', $sessionPath);

require dirname(__DIR__) . '/bootstrap.php';

use App\Repositories\PostRepository;
use App\Support\EnvironmentManager;
use App\Support\TargetEnvironmentDatabase;

$currentEnv = EnvironmentManager::current();
$isRemoteFlag = in_array('--remote-production', $argv, true);
$isDryRun = in_array('--dry-run', $argv, true);
$targetEnv = ($currentEnv === 'local' && $isRemoteFlag) ? 'production' : $currentEnv;

try {
    $pdo = TargetEnvironmentDatabase::pdo($targetEnv);
} catch (Throwable $exception) {
    $errMsg = sprintf("[%s] ERRO: falha ao conectar no banco (%s): %s\n", date('Y-m-d H:i:s'), $targetEnv, $exception->getMessage());
    fwrite(STDERR, $errMsg);
    $logDir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }
    @file_put_contents($logDir . '/cron-publish-scheduled.log', $errMsg, FILE_APPEND);
    exit(1);
}

$postsRepo = new PostRepository($pdo);
$now = date('Y-m-d H:i:s');
$due = $postsRepo->findDueScheduled($now);

$results = [];
foreach ($due as $post) {
    $postId = (int) ($post['id'] ?? 0);
    if ($isDryRun) {
        $results[] = ['post_id' => $postId, 'action' => 'seria_publicado', 'data_publicacao' => $post['data_publicacao'] ?? null];
        continue;
    }
    $postsRepo->markPublished($postId);
    $results[] = ['post_id' => $postId, 'action' => 'publicado', 'data_publicacao' => $post['data_publicacao'] ?? null];
}

$outputData = [
    'ok' => true,
    'ambiente' => $targetEnv,
    'dry_run' => $isDryRun,
    'data_execucao' => date('Y-m-d'),
    'hora_execucao' => date('H:i:s'),
    'fuso' => date_default_timezone_get(),
    'total_encontrados' => count($due),
    'resultados' => $results,
];

$jsonOutput = json_encode($outputData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
echo $jsonOutput . PHP_EOL;

$logDir = dirname(__DIR__) . '/storage/logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }
$logLine = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), strtoupper($targetEnv), $jsonOutput);
@file_put_contents($logDir . '/cron-publish-scheduled.log', $logLine, FILE_APPEND);