<?php
/**
 * -----------------------------------------------------------------------------
 * en-blog-schedule-publish.php
 * Publica automaticamente (rascunho -> publicado) os posts da leva FEAT-005
 * no banco do ambiente onde executa (PRODUCAO ou STAGE), na data planejada.
 *
 * Idempotente: so mexe em post cuja data planejada ja chegou E que ainda
 * esteja com status='rascunho'. Post ja publicado (manualmente ou em execucao
 * anterior) ou com data futura e pulado.
 *
 * Suporta execucao direta via Cron da Hostinger (SAPI cli) ou local no PC.
 * Saida registrada em tela e em _app_core/storage/logs/cron-schedule-publish.log.
 *
 * Uso:
 *   php scripts/en-blog-schedule-publish.php [--remote-production] [--test-post-id=ID]
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

// Agenda definida em docs/features/FEAT-008.md - producao_id => data planejada.
// O post 30 (Windows 11 24H2) ja foi publicado manualmente e nao entra aqui.
const SCHEDULE = [
    31 => '2026-09-22', // RTX 5060 vs RX 9060 XT (1080p)
    32 => '2026-09-25', // AM4 ou AM5 em 2026
    33 => '2026-09-29', // Como escolher fonte para PC
    34 => '2026-10-02', // Como verificar saude de SSD/HD
    35 => '2026-10-06', // Temperatura CPU/GPU: quando se preocupar
    36 => '2026-10-09', // 12 jogos leves para PC fraco ou modesto
    37 => '2026-10-13', // A historia do Counter-Strike
    38 => '2026-10-16', // 10 filmes nerds essenciais
    39 => '2026-10-20', // 10 animes essenciais
    40 => '2026-10-23', // 10 desenhos dos anos 90/2000 que envelheceram bem
];

// Resolucao do ambiente e conexao com banco local do ambiente
$currentEnv = EnvironmentManager::current();
$isRemoteFlag = in_array('--remote-production', $argv, true);
$targetEnv = ($currentEnv === 'local' && $isRemoteFlag) ? 'production' : $currentEnv;

try {
    $pdo = TargetEnvironmentDatabase::pdo($targetEnv);
} catch (Throwable $exception) {
    $errMsg = sprintf("[%s] ERRO: falha ao conectar no banco (%s): %s\n", date('Y-m-d H:i:s'), $targetEnv, $exception->getMessage());
    fwrite(STDERR, $errMsg);
    $logDir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }
    @file_put_contents($logDir . '/cron-schedule-publish.log', $errMsg, FILE_APPEND);
    exit(1);
}

// Analise de argumentos de teste
$schedule = SCHEDULE;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--test-post-id=')) {
        $testId = (int) substr($arg, strlen('--test-post-id='));
        if ($testId > 0) {
            $schedule[$testId] = date('Y-m-d');
        }
    }
}

$postsRepo = new PostRepository($pdo);
$today = date('Y-m-d');
$results = [];

foreach ($schedule as $postId => $scheduledDate) {
    $post = $postsRepo->findAdminById($postId);
    if ($post === null) {
        $results[] = ['post_id' => $postId, 'action' => 'erro', 'motivo' => 'post nao encontrado no banco'];
        continue;
    }

    $status = (string) ($post['status'] ?? '');
    if ($status !== 'rascunho') {
        $results[] = ['post_id' => $postId, 'action' => 'pulado', 'motivo' => "status atual e '{$status}', nao 'rascunho'"];
        continue;
    }

    if ($scheduledDate > $today) {
        $results[] = ['post_id' => $postId, 'action' => 'pulado', 'motivo' => "data planejada ({$scheduledDate}) ainda nao chegou"];
        continue;
    }

    $payload = $post;
    $payload['status'] = 'publicado';
    $payload['data_publicacao'] = $today . ' ' . date('H:i:s');
    $postsRepo->updateAdmin($postId, $payload);

    $results[] = ['post_id' => $postId, 'action' => 'publicado', 'data_publicacao' => $payload['data_publicacao']];
}

$outputData = [
    'ok' => true,
    'ambiente' => $targetEnv,
    'data_execucao' => $today,
    'hora_execucao' => date('H:i:s'),
    'fuso' => date_default_timezone_get(),
    'resultados' => $results,
];

$jsonOutput = json_encode($outputData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
echo $jsonOutput . PHP_EOL;

// Gravacao persistente de log
$logDir = dirname(__DIR__) . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}
$logLine = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), strtoupper($targetEnv), $jsonOutput);
@file_put_contents($logDir . '/cron-schedule-publish.log', $logLine, FILE_APPEND);
