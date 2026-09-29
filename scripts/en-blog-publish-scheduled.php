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
 * Toda execucao deixa rastro em storage/logs/cron-publish-scheduled.log e
 * storage/logs/cron-publish-scheduled.last.json (heartbeat), inclusive quando
 * o bootstrap encerra o processo por falha de banco. Falha = exit code 1.
 *
 * Uso:
 *   php scripts/en-blog-publish-scheduled.php [--remote-production] [--dry-run]
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

use App\Repositories\PostRepository;
use App\Support\EnvironmentManager;
use App\Support\TargetEnvironmentDatabase;

if (PHP_SAPI !== 'cli') { exit(1); }

date_default_timezone_set('America/Sao_Paulo');

$logDir        = dirname(__DIR__) . '/storage/logs';
$logFile       = $logDir . '/cron-publish-scheduled.log';
$heartbeatFile = $logDir . '/cron-publish-scheduled.last.json';
$isDryRun      = in_array('--dry-run', $argv, true);

$run = [
    'iniciado_em'       => date('c'),
    'finalizado_em'     => null,
    'ambiente'          => null,
    'dry_run'           => $isDryRun,
    'ok'                => false,
    'publicados'        => [],
    'seriam_publicados' => [],
    'erro'              => null,
];
$state = new stdClass();
$state->finished = false;

function cron_ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, '[' . date('Y-m-d H:i:s') . "] AVISO: nao foi possivel criar a pasta de logs {$dir}\n");
    }
}

function cron_append_log(string $dir, string $file, string $line): void
{
    cron_ensure_dir($dir);
    if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
        fwrite(STDERR, '[' . date('Y-m-d H:i:s') . "] AVISO: nao foi possivel gravar o log em {$file}\n");
    }
}

/**
 * @param array<string,mixed> $data
 */
function cron_write_heartbeat(string $dir, string $file, array $data): void
{
    cron_ensure_dir($dir);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $tmp  = $file . '.tmp';
    // Arquivo temporario + rename: quem ler o heartbeat nunca ve JSON pela metade.
    if ($json === false || @file_put_contents($tmp, $json . "\n", LOCK_EX) === false || !@rename($tmp, $file)) {
        @unlink($tmp);
        fwrite(STDERR, '[' . date('Y-m-d H:i:s') . "] AVISO: nao foi possivel gravar o heartbeat em {$file}\n");
    }
}

/**
 * @param array<string,mixed> $run
 */
function cron_record_failure(array &$run, string $logDir, string $logFile, string $heartbeatFile, string $message): void
{
    $run['ok']            = false;
    $run['finalizado_em'] = date('c');
    $run['erro']          = mb_substr($message, 0, 500);

    $line = sprintf("[%s] [%s] ERRO: %s\n", date('Y-m-d H:i:s'), strtoupper((string) ($run['ambiente'] ?? '?')), $run['erro']);
    fwrite(STDERR, $line);
    cron_append_log($logDir, $logFile, $line);
    cron_write_heartbeat($logDir, $heartbeatFile, $run);
}

// O bootstrap encerra o processo com exit (sem codigo de erro) quando o banco
// falha; so um shutdown function registrado antes dele consegue registrar isso.
register_shutdown_function(static function () use (&$run, $state, $logDir, $logFile, $heartbeatFile): void {
    /** @var bool $finished alterado pelo fluxo principal depois deste closure ser registrado */
    $finished = $state->finished;
    if ($finished) {
        return;
    }

    $fatal   = error_get_last();
    $isFatal = is_array($fatal) && in_array($fatal['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);
    $message = $isFatal
        ? 'Erro fatal: ' . $fatal['message']
        : 'Execucao encerrada antes de concluir (ex.: falha de conexao com o banco no bootstrap).';

    cron_record_failure($run, $logDir, $logFile, $heartbeatFile, $message);
    exit(1);
});

try {
    $sessionPath = dirname(__DIR__) . '/storage/sessions';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0777, true);
    }
    ini_set('session.save_path', $sessionPath);

    require dirname(__DIR__) . '/bootstrap.php';

    $currentEnv   = EnvironmentManager::current();
    $isRemoteFlag = in_array('--remote-production', $argv, true);
    $targetEnv    = ($currentEnv === 'local' && $isRemoteFlag) ? 'production' : $currentEnv;
    $run['ambiente'] = $targetEnv;

    try {
        $pdo = TargetEnvironmentDatabase::pdo($targetEnv);
    } catch (Throwable $exception) {
        throw new RuntimeException(sprintf('falha ao conectar no banco (%s): %s', $targetEnv, $exception->getMessage()), 0, $exception);
    }

    $postsRepo = new PostRepository($pdo);
    $now = date('Y-m-d H:i:s');
    $due = $postsRepo->findDueScheduled($now);

    $results = [];
    foreach ($due as $post) {
        $postId = (int) ($post['id'] ?? 0);
        if ($isDryRun) {
            $run['seriam_publicados'][] = $postId;
            $results[] = ['post_id' => $postId, 'action' => 'seria_publicado', 'data_publicacao' => $post['data_publicacao'] ?? null];
            continue;
        }
        $postsRepo->markPublished($postId);
        // Registrado a cada post: se um posterior falhar, os ja publicados ficam no heartbeat.
        $run['publicados'][] = $postId;
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

    cron_append_log($logDir, $logFile, sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), strtoupper($targetEnv), $jsonOutput));

    $run['ok']            = true;
    $run['finalizado_em'] = date('c');
    cron_write_heartbeat($logDir, $heartbeatFile, $run);
    $state->finished = true;
} catch (Throwable $exception) {
    cron_record_failure($run, $logDir, $logFile, $heartbeatFile, $exception->getMessage());
    $state->finished = true;
    exit(1);
}
