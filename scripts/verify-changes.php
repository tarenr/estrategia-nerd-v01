<?php
/**
 * -----------------------------------------------------------------------------
 * @file        scripts/verify-changes.php
 * @project     Estrategia Nerd
 * @purpose     Rotina automatizada de testes pós-alteração (sintaxe, phpstan, renderização multi-ambiente)
 * @usage       C:\xampp\php\php.exe scripts/verify-changes.php
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$phpBinary = PHP_BINARY;

$testsTotal = 0;
$testsPassed = 0;
$testsFailed = 0;
$failures = [];

function recordResult(string $name, bool $success, string $detail = ''): void
{
    global $testsTotal, $testsPassed, $testsFailed, $failures;
    $testsTotal++;
    if ($success) {
        $testsPassed++;
        echo sprintf("  [\033[32mPASS\033[0m] %s %s\n", $name, $detail !== '' ? "(\033[90m{$detail}\033[0m)" : '');
    } else {
        $testsFailed++;
        $failures[] = "{$name}: {$detail}";
        echo sprintf("  [\033[31mFAIL\033[0m] %s - \033[31m%s\033[0m\n", $name, $detail);
    }
}

function runIsolatedPhp(string $phpBinary, string $root, string $script): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open(escapeshellarg($phpBinary), $descriptors, $pipes, $root);
    if (!is_resource($process)) {
        return [1, '', 'Falha ao iniciar processo PHP'];
    }

    fwrite($pipes[0], "<?php\n" . $script);
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $returnCode = proc_close($process);

    return [$returnCode, (string)$stdout, (string)$stderr];
}

echo "\n=======================================================\n";
echo "   ESTRATÉGIA NERD — SUÍTE DE VERIFICAÇÃO PÓS-ALTERAÇÃO\n";
echo "=======================================================\n\n";

// ── 1. Verificação de Sintaxe PHP ─────────────────────────────────────────────
echo "1. Sintaxe e Linting (php -l):\n";
$criticalFiles = [
    'app/Controllers/Admin/DashboardController.php',
    'app/Services/Admin/DashboardService.php',
    'app/Controllers/Admin/InstagramController.php',
    'app/Repositories/InstagramPostRepository.php',
    'app/Services/Instagram/InstagramApiService.php',
    'app/Views/admin/dashboard.php',
    'config/automated-tests.php',
];

foreach ($criticalFiles as $file) {
    $fullPath = $root . '/' . $file;
    if (!file_exists($fullPath)) {
        recordResult("Lint {$file}", false, 'Arquivo não encontrado');
        continue;
    }
    $cmd = escapeshellarg($phpBinary) . ' -l ' . escapeshellarg($fullPath) . ' 2>&1';
    $output = [];
    $returnVar = 0;
    exec($cmd, $output, $returnVar);
    recordResult("Lint {$file}", $returnVar === 0, $returnVar !== 0 ? implode(' ', $output) : 'OK');
}

// ── 2. Análise Estática (PHPStan) ─────────────────────────────────────────────
echo "\n2. Análise Estática (PHPStan Level 5):\n";
$phpstanCmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($root . '/vendor/bin/phpstan') . ' analyse --level=5 --no-progress 2>&1';
$stanOutput = [];
$stanCode = 0;
exec($phpstanCmd, $stanOutput, $stanCode);
$stanSummary = '';
foreach ($stanOutput as $line) {
    if (str_contains($line, '[OK]') || str_contains($line, '[ERROR]') || str_contains($line, 'errors')) {
        $stanSummary = trim($line);
    }
}
recordResult("PHPStan Level 5", $stanCode === 0, $stanSummary !== '' ? $stanSummary : ($stanCode === 0 ? '0 erros' : 'Falha na análise'));

// ── 3. Renderização do Dashboard nos 3 Ambientes-Alvo ─────────────────────────
echo "\n3. Renderização Multi-Ambiente do Dashboard Admin (/admin):\n";

$environments = ['local', 'production', 'stage'];

foreach ($environments as $env) {
    $script = <<<PHP
require 'bootstrap.php';
\App\Support\EnvironmentManager::setTarget('{$env}');
try {
    ob_start();
    @(new \App\Controllers\Admin\DashboardController())->index();
    \$html = ob_get_clean();
    \$bytes = strlen(\$html);
    \$kpi = str_contains(\$html, 'Seguidores Instagram') ? 1 : 0;
    \$panel = str_contains(\$html, 'Instagram & Redes Sociais') ? 1 : 0;
    echo "STATUS:OK|BYTES:{\$bytes}|KPI:{\$kpi}|PANEL:{\$panel}\n";
} catch (\Throwable \$e) {
    echo "STATUS:ERROR|MSG:" . \$e->getMessage() . "\n";
}
PHP;

    [$code, $stdout, $stderr] = runIsolatedPhp($phpBinary, $root, $script);

    $isOk = false;
    $detail = '';

    if (str_contains($stdout, 'STATUS:OK')) {
        preg_match('/BYTES:(\d+)\|KPI:(\d+)\|PANEL:(\d+)/', $stdout, $matches);
        $bytes = (int)($matches[1] ?? 0);
        $kpi = (int)($matches[2] ?? 0) === 1;
        $panel = (int)($matches[3] ?? 0) === 1;

        $isOk = ($bytes > 20000) && $kpi && $panel;
        $detail = sprintf("%d KB | KPI: %s | Painel: %s", (int)($bytes / 1024), $kpi ? 'Sim' : 'Não', $panel ? 'Sim' : 'Não');
    } else {
        $detail = $stderr !== '' ? trim($stderr) : trim($stdout);
    }

    recordResult("Dashboard [Target: {$env}]", $isOk, $detail);
}

// ── 4. Renderização do Módulo Instagram ──────────────────────────────────────
echo "\n4. Integridade do Módulo Instagram (/admin/instagram):\n";

$igScript = <<<PHP
require 'bootstrap.php';
try {
    \$_GET = [];
    ob_start();
    @(new \App\Controllers\Admin\InstagramController())->index();
    \$html = ob_get_clean();
    \$bytes = strlen(\$html);
    \$hasHeader = str_contains(\$html, 'Instagram') ? 1 : 0;
    \$hasProfile = str_contains(\$html, 'estrategia_nerd') ? 1 : 0;
    echo "STATUS:OK|BYTES:{\$bytes}|HEADER:{\$hasHeader}|PROFILE:{\$hasProfile}\n";
} catch (\Throwable \$e) {
    echo "STATUS:ERROR|MSG:" . \$e->getMessage() . "\n";
}
PHP;

[$igCode, $igStdout, $igStderr] = runIsolatedPhp($phpBinary, $root, $igScript);

$igOk = false;
$igDetail = '';

if (str_contains($igStdout, 'STATUS:OK')) {
    preg_match('/BYTES:(\d+)\|HEADER:(\d+)\|PROFILE:(\d+)/', $igStdout, $matches);
    $bytes = (int)($matches[1] ?? 0);
    $header = (int)($matches[2] ?? 0) === 1;
    $profile = (int)($matches[3] ?? 0) === 1;

    $igOk = ($bytes > 10000) && $header;
    $igDetail = sprintf("%d KB | Header: %s | Perfil: %s", (int)($bytes / 1024), $header ? 'Sim' : 'Não', $profile ? 'Sim' : 'Não');
} else {
    $igDetail = $igStderr !== '' ? trim($igStderr) : trim($igStdout);
}

recordResult("Dashboard Instagram (/admin/instagram)", $igOk, $igDetail);

// ── 5. Resumo Final ──────────────────────────────────────────────────────────
echo "\n-------------------------------------------------------\n";
echo sprintf(
    "Resumo: \033[32m%d OK\033[0m | \033[31m%d FALHAS\033[0m | Total: %d testes\n",
    $testsPassed,
    $testsFailed,
    $testsTotal
);
echo "-------------------------------------------------------\n";

if ($testsFailed > 0) {
    echo "\n\033[31mFALHAS ENCONTRADAS:\033[0m\n";
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
    echo "\nStatus: \033[31mREPROVADO\033[0m\n\n";
    exit(1);
}

echo "\nStatus: \033[32mAPROVADO COM SUCESSO!\033[0m Todas as verificações passaram.\n\n";
exit(0);
