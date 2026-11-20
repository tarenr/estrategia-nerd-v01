<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script é exclusivo para execução via CLI.' . PHP_EOL);
}

require_once __DIR__ . '/../bootstrap.php';

use App\Repositories\YouTubeVideoRepository;
use App\Services\YouTube\YouTubeApiService;
use App\Services\YouTube\YouTubeOAuthService;
use App\Services\YouTube\YouTubePublishService;

$dryRun = in_array('--dry-run', $argv, true);
$logDirectory = dirname(__DIR__) . '/storage/logs/youtube';
if (!is_dir($logDirectory) && !mkdir($logDirectory, 0750, true) && !is_dir($logDirectory)) {
    fwrite(STDERR, 'Não foi possível criar o diretório de logs.' . PHP_EOL);
    exit(1);
}
$logFile = $logDirectory . '/publish-' . date('Y-m') . '.log';

$log = static function (string $level, string $message) use ($logFile): void {
    $safe = preg_replace('/https:\/\/[^\s]+upload_id=[^\s]+/i', '[upload-session-redacted]', $message) ?? $message;
    $line = '[' . date('Y-m-d H:i:s') . '] [' . strtoupper($level) . '] ' . $safe . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
};

$lock = fopen(sys_get_temp_dir() . '/en-youtube-publish.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    $log('info', 'Outra instância já está em execução.');
    exit(0);
}

try {
    /** @var PDO $pdo */
    $pdo = $GLOBALS['pdo'];
    $repository = new YouTubeVideoRepository($pdo);
    $oauth = new YouTubeOAuthService();

    $due = $repository->dueForUpload(3);
    $pending = $repository->pendingProcessing(10);
    if ($dryRun) {
        $log('info', 'Dry-run: ' . count($due) . ' envio(s) e ' . count($pending) . ' reconciliação(ões) pendentes.');
        exit(0);
    }
    if (!$oauth->isConfigured() || !$oauth->connected()) {
        $log('warn', 'OAuth do YouTube ainda não está configurado/conectado; nenhuma alteração remota foi feita.');
        exit(0);
    }

    $service = new YouTubePublishService($repository, new YouTubeApiService($oauth));
    $stale = $repository->markStaleAsUncertain(120);
    if ($stale > 0) {
        $log('warn', $stale . ' upload(s) interrompido(s) marcado(s) para reconciliação manual.');
    }
    foreach ($pending as $video) {
        $service->reconcile($video);
        $log('info', 'Reconciliação executada para o item local #' . (int) $video['id'] . '.');
    }
    foreach ($due as $video) {
        $id = (int) $video['id'];
        if (!$repository->claim($id)) {
            continue;
        }
        $service->upload($video);
        $log('info', 'Ciclo de upload executado para o item local #' . $id . '.');
    }
    $log('info', 'Ciclo concluído.');
} catch (Throwable $exception) {
    $log('error', $exception->getMessage());
    exit(1);
} finally {
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
