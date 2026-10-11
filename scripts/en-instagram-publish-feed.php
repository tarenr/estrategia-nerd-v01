<?php
/**
 * -----------------------------------------------------------------------------
 * @file        scripts/en-instagram-publish-feed.php
 * @project     Estrategia Nerd
 * @purpose     CLI: publica o feed do Instagram (JSON + capas) em Stage/Producao (FEAT-010 v2.12.0)
 * @usage       C:\xampp\php\php.exe scripts/en-instagram-publish-feed.php <stage|production> [--dry-run] [--probe]
 *              --dry-run  valida e mostra o que enviaria, sem escrever no remoto
 *              --probe    testa se a hospedagem aceita rename sobre arquivo existente (arquivos descartaveis)
 * @notes       Escopo remoto restrito a <core>/config/instagram-feed.json e <public>/assets/instagram-feed/.
 *              Para desativar o envio automatico pelo agendador, crie
 *              storage/instagram-feed-publish/auto.disabled.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Este script é exclusivo para execução via CLI.' . PHP_EOL);
}

require_once __DIR__ . '/../bootstrap.php';

use App\Services\Instagram\InstagramFeedPublisher;

$root   = dirname(__DIR__);
$target = (string) ($argv[1] ?? '');
$dryRun = in_array('--dry-run', $argv, true);
$probe  = in_array('--probe', $argv, true);

if (!in_array($target, ['stage', 'production'], true)) {
    fwrite(STDERR, "Uso: php scripts/en-instagram-publish-feed.php <stage|production> [--dry-run] [--probe]\n");
    exit(2);
}

$logDir = $root . '/storage/logs/instagram/';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . 'feed-publish-' . date('Y-m') . '.log';
$log = static function (string $message) use ($logFile, $target): void {
    $line = '[' . date('Y-m-d H:i:s') . '] [' . $target . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
};

// Lock proprio: nunca dois envios ao mesmo tempo.
$lock = fopen(sys_get_temp_dir() . '/en-instagram-feed-publish.lock', 'w');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    $log('Outra instância do publicador já está em execução. Saindo.');
    exit(0);
}

try {
    $config = require $root . '/config/content-sync.php';
    $profile = $config['profiles'][$target] ?? null;
    if (!is_array($profile)) {
        throw new RuntimeException('Perfil ' . $target . ' não encontrado em config/content-sync.php.');
    }

    $publisher = new InstagramFeedPublisher($target, $profile, $root, $log);

    if ($probe) {
        $probeResult = $publisher->probeRename();
        $log(($probeResult['ok'] ? 'PROBE OK: ' : 'PROBE FALHOU: ') . $probeResult['message']);
        exit($probeResult['ok'] ? 0 : 1);
    }

    $result = $publisher->publish($dryRun);
    $log('Resultado: ' . $result['status'] . ' — ' . $result['message']
        . ($result['uploaded'] > 0 ? ' (capas enviadas: ' . $result['uploaded'] . ')' : ''));

    if (!$dryRun) {
        $state = InstagramFeedPublisher::readState($root);
        $state[$target] = [
            'status'  => $result['status'],
            'hash'    => $result['hash'],
            'at'      => date('c'),
            'message' => $result['message'],
        ];
        InstagramFeedPublisher::writeState($root, $state);
    }

    exit(in_array($result['status'], ['ok', 'unchanged', 'dry-run'], true) ? 0 : 1);
} catch (Throwable $e) {
    $log('ERRO: ' . $e->getMessage());
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
