<?php
/**
 * -----------------------------------------------------------------------------
 * Arquivo: scripts/tests.php
 * Projeto: Estrategia Nerd
 * Proposito: Executar a suite automatizada operacional via CLI.
 * Uso: php scripts/tests.php unit local
 * Observacoes: Routine executa apenas rotinas selecionadas; full permanece bloqueado.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

$sessionPath = dirname(__DIR__) . '/storage/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0777, true);
}
ini_set('session.save_path', $sessionPath);

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\Site\AutomatedTestService;

$level = strtolower(trim((string) ($argv[1] ?? 'safe')));
$environment = strtolower(trim((string) ($argv[2] ?? 'local')));
$routines = array_slice($argv, 3);
$service = new AutomatedTestService(require base_path('config/automated-tests.php'));

try {
    $result = $service->run($level, $environment, $routines);

    echo 'Suite automatizada: ' . ($result['id'] ?? '-') . PHP_EOL;
    echo 'Ambiente: ' . ($result['environment'] ?? '-') . PHP_EOL;
    echo 'Nivel: ' . ($result['level'] ?? '-') . PHP_EOL;
    echo 'Status: ' . strtoupper((string) ($result['status'] ?? 'fail')) . PHP_EOL;
    if (($result['selected_routines'] ?? []) !== []) {
        echo 'Rotinas: ' . implode(', ', (array) $result['selected_routines']) . PHP_EOL;
    }
    echo 'Resumo: OK=' . (int) ($result['tests_ok'] ?? 0)
        . ' FAIL=' . (int) ($result['tests_failed'] ?? 0)
        . ' SKIP=' . (int) ($result['tests_skipped'] ?? 0)
        . ' BLOCKED=' . count((array) ($result['security_blocks'] ?? []))
        . ' TOTAL=' . (int) ($result['tests_executed'] ?? 0)
        . PHP_EOL . PHP_EOL;

    foreach ((array) ($result['tests'] ?? []) as $test) {
        echo sprintf(
            '[%s] %-34s HTTP %-4s %5sms %s',
            strtoupper((string) ($test['status'] ?? 'fail')),
            (string) ($test['name'] ?? '-'),
            (string) ($test['http_status'] ?? '-'),
            (string) ($test['duration_ms'] ?? '0'),
            (string) ($test['message'] ?? '')
        ) . PHP_EOL;
    }

    $blocks = (array) ($result['security_blocks'] ?? []);
    if ($blocks !== []) {
        echo PHP_EOL . 'Bloqueios de seguranca:' . PHP_EOL;
        foreach ($blocks as $block) {
            echo sprintf(
                '- [%s] %s: %s',
                (string) ($block['rule'] ?? '-'),
                (string) ($block['action'] ?? '-'),
                (string) ($block['reason'] ?? '-')
            ) . PHP_EOL;
        }
    }

    echo PHP_EOL . 'Relatorio salvo em: storage/automated-tests/' . ($result['id'] ?? '-') . '.json' . PHP_EOL;

    exit(((string) ($result['status'] ?? 'fail')) === 'fail' ? 1 : 0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Falha na suite automatizada: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
