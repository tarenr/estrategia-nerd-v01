<?php

declare(strict_types=1);

$sessionPath = dirname(__DIR__) . '/storage/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0777, true);
}
ini_set('session.save_path', $sessionPath);

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\Site\SmokeTestService;

$environment = strtolower(trim((string) ($argv[1] ?? 'stage')));
$service = new SmokeTestService(require base_path('config/smoke-tests.php'));

try {
    $result = $service->run($environment);
    $summary = (array) ($result['summary'] ?? []);

    echo 'Smoke test: ' . ($result['id'] ?? '-') . PHP_EOL;
    echo 'Ambiente: ' . ($result['environment_label'] ?? $environment) . ' (' . ($result['base_url'] ?? '-') . ')' . PHP_EOL;
    echo 'Status: ' . strtoupper((string) ($result['status'] ?? 'fail')) . PHP_EOL;
    echo 'Resumo: OK=' . (int) ($summary['ok'] ?? 0)
        . ' FAIL=' . (int) ($summary['fail'] ?? 0)
        . ' SKIP=' . (int) ($summary['skip'] ?? 0)
        . ' TOTAL=' . (int) ($summary['total'] ?? 0)
        . PHP_EOL . PHP_EOL;

    foreach ((array) ($result['tests'] ?? []) as $test) {
        echo sprintf(
            '[%s] %-28s HTTP %-4s %5sms %s',
            strtoupper((string) ($test['status'] ?? 'fail')),
            (string) ($test['name'] ?? '-'),
            (string) ($test['http_status'] ?? '-'),
            (string) ($test['duration_ms'] ?? '0'),
            (string) ($test['message'] ?? '')
        ) . PHP_EOL;
    }

    exit(((string) ($result['status'] ?? 'fail')) === 'ok' ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Falha no smoke test: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
