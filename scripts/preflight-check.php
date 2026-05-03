<?php
declare(strict_types=1);

require_once __DIR__ . '/backup/EnvLoader.php';

use Scripts\Backup\EnvLoader;

EnvLoader::load(dirname(__DIR__) . '/.env');

$projectRoot = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
$normalizedProjectRoot = normalizePath($projectRoot);

$defaultCanonical = 'C:\\xampp\\htdocs\\estrategia-nerd';
$canonicalRaw = trim((string) ($_ENV['PROJECT_CANONICAL_ROOTS'] ?? $defaultCanonical));
$canonicalRoots = [];
if ($canonicalRaw !== '') {
    foreach (explode(',', $canonicalRaw) as $root) {
        $root = trim($root);
        if ($root === '') {
            continue;
        }
        $canonicalRoots[] = normalizePath($root);
    }
}

$warnings = [];
$notes = [];
$errors = [];
$deploymentPolicy = deploymentPolicy();

echo "== Preflight Estrategia Nerd ==\n";
echo "Project root atual: {$projectRoot}\n";
echo 'Origem atual para producao: ' . $deploymentPolicy['current_source'] . "\n";
echo 'Origem aprovada para pacote de producao: ' . $deploymentPolicy['approved_source'] . ' (' . $deploymentPolicy['stage_label'] . ")\n";

if ($canonicalRoots !== [] && !in_array($normalizedProjectRoot, $canonicalRoots, true)) {
    $warnings[] = 'Projeto aberto fora do caminho canonico. Ajuste antes de modificar/publicar.';
    echo "Canonicos esperados:\n";
    foreach ($canonicalRoots as $canonical) {
        echo ' - ' . $canonical . "\n";
    }
}

if (!$deploymentPolicy['production_allowed']) {
    $warnings[] = $deploymentPolicy['message'];
}

$lockCandidates = [
    $projectRoot . '/storage/content-sync/.content-sync-running.lock.json',
    $projectRoot . '/storage/code-sync/.content-sync-running.lock.json',
];
foreach ($lockCandidates as $lockFile) {
    if (!is_file($lockFile)) {
        continue;
    }

    $state = inspectLockFile($lockFile);
    if ($state['status'] === 'active') {
        $warnings[] = 'Existe lock de rotina ativo: ' . $lockFile;
        continue;
    }

    $notes[] = 'Lock antigo detectado (nao ativo): ' . $lockFile . '.';
}

$statusCmd = 'git -C ' . escapeshellarg($projectRoot) . ' status --porcelain';
$statusOutput = [];
$statusExit = 0;
exec($statusCmd . ' 2>&1', $statusOutput, $statusExit);
if ($statusExit !== 0) {
    $warnings[] = 'Nao foi possivel ler status do git.';
} elseif ($statusOutput !== []) {
    $warnings[] = 'Existem alteracoes locais (git status nao esta limpo).';
    echo "Mudancas locais detectadas:\n";
    foreach (array_slice($statusOutput, 0, 25) as $line) {
        echo ' - ' . $line . "\n";
    }
    if (count($statusOutput) > 25) {
        echo ' - ... +' . (count($statusOutput) - 25) . " arquivos\n";
    }
}

$criticalFilesAudit = auditCriticalPublicFiles($projectRoot);
foreach ($criticalFilesAudit['warnings'] as $warning) {
    $warnings[] = $warning;
}
foreach ($criticalFilesAudit['errors'] as $error) {
    $errors[] = $error;
}
foreach ($criticalFilesAudit['notes'] as $note) {
    $notes[] = $note;
}

$encodingScan = scanEncodingIssues($projectRoot);
if ($encodingScan['issues'] !== []) {
    $errors[] = 'Detectado possivel problema de encoding/texto quebrado.';
    echo "Arquivos com sinais de encoding invalido:\n";
    foreach ($encodingScan['issues'] as $issue) {
        echo ' - ' . $issue . "\n";
    }
} else {
    echo 'Encoding guard: OK (files=' . $encodingScan['files_scanned'] . ")\n";
}

$mergeMarkerIssues = scanMergeMarkers($projectRoot);
if ($mergeMarkerIssues !== []) {
    $errors[] = 'Detectado marcador de merge pendente (<<<<<<, ======, >>>>>>).';
    echo "Marcadores de merge encontrados:\n";
    foreach ($mergeMarkerIssues as $issue) {
        echo ' - ' . $issue . "\n";
    }
}

if ($warnings !== []) {
    echo "\nAvisos:\n";
    foreach ($warnings as $warning) {
        echo ' - ' . $warning . "\n";
    }
}

if ($notes !== []) {
    echo "\nNotas:\n";
    foreach ($notes as $note) {
        echo ' - ' . $note . "\n";
    }
}

if ($errors !== []) {
    echo "\nFalhas bloqueantes:\n";
    foreach ($errors as $error) {
        echo ' - ' . $error . "\n";
    }
    exit(1);
}

echo "\nPreflight finalizado com sucesso.\n";
exit(0);

function deploymentPolicy(): array
{
    $currentSource = strtolower(trim((string) ($_ENV['CONTENT_SYNC_CURRENT_SOURCE'] ?? 'local')));
    $approvedSource = strtolower(trim((string) ($_ENV['CONTENT_SYNC_APPROVED_PACKAGE_SOURCE'] ?? 'stage')));
    $stageLabel = trim((string) ($_ENV['CONTENT_SYNC_STAGE_LABEL'] ?? 'estrategia-nerd-stage'));
    $productionAllowed = $currentSource !== '' && $approvedSource !== '' && $currentSource === $approvedSource;

    return [
        'current_source' => $currentSource !== '' ? $currentSource : 'local',
        'approved_source' => $approvedSource !== '' ? $approvedSource : 'stage',
        'stage_label' => $stageLabel !== '' ? $stageLabel : 'estrategia-nerd-stage',
        'production_allowed' => $productionAllowed,
        'message' => $productionAllowed
            ? 'Origem atual autorizada para pacote de producao.'
            : sprintf('Publicacao em producao bloqueada: a origem atual e "%s" e a regra permanente exige "%s" (%s).', $currentSource !== '' ? $currentSource : 'local', $approvedSource !== '' ? $approvedSource : 'stage', $stageLabel !== '' ? $stageLabel : 'estrategia-nerd-stage'),
    ];
}

function auditCriticalPublicFiles(string $projectRoot): array
{
    $warnings = [];
    $errors = [];
    $notes = [];
    $indexPath = $projectRoot . '/public/index.php';
    if (!is_file($indexPath)) {
        $errors[] = 'Arquivo critico ausente: public/index.php';
    } else {
        $indexContent = (string) file_get_contents($indexPath);
        if (!str_contains($indexContent, 'bootstrap.php')) {
            $errors[] = 'public/index.php nao referencia bootstrap.php.';
        }
        if (!str_contains($indexContent, '_app_core')) {
            $warnings[] = 'public/index.php nao possui fallback explicito para _app_core.';
        }
        if (!str_contains($indexContent, 'Status: 200 OK')) {
            $warnings[] = 'public/index.php nao registra header explicito de 200 OK.';
        }
        $phpBinary = defined('PHP_BINARY') ? (string) PHP_BINARY : '';
        if ($phpBinary !== '') {
            $lintOutput = [];
            $lintExit = 0;
            exec(escapeshellarg($phpBinary) . ' -l ' . escapeshellarg($indexPath) . ' 2>&1', $lintOutput, $lintExit);
            if ($lintExit !== 0) {
                $errors[] = 'Lint falhou em public/index.php: ' . trim(implode(' | ', $lintOutput));
            } else {
                $notes[] = 'Lint OK em public/index.php.';
            }
        }
    }
    $htaccessPath = $projectRoot . '/public/.htaccess';
    if (!is_file($htaccessPath)) {
        $errors[] = 'Arquivo critico ausente: public/.htaccess';
    } else {
        $htaccessContent = (string) file_get_contents($htaccessPath);
        foreach (['DirectoryIndex index.php', 'RewriteEngine On'] as $requiredSnippet) {
            if (!str_contains($htaccessContent, $requiredSnippet)) {
                $errors[] = 'public/.htaccess sem trecho obrigatorio: ' . $requiredSnippet;
            }
        }
        if (!str_contains($htaccessContent, 'RewriteRule')) {
            $warnings[] = 'public/.htaccess sem regra explicita de rewrite. Validar deploy antes de publicar.';
        }
    }
    return ['warnings' => $warnings, 'errors' => $errors, 'notes' => $notes];
}

function normalizePath(string $path): string
{
    $path = str_replace('/', '\\', trim($path));
    $real = realpath($path);
    if (is_string($real) && $real !== '') {
        $path = $real;
    }
    return strtolower(rtrim(str_replace('/', '\\', $path), '\\'));
}

function scanEncodingIssues(string $projectRoot): array
{
    $roots = [$projectRoot . '/app/Views', $projectRoot . '/app/Services', $projectRoot . '/app/Controllers', $projectRoot . '/config', $projectRoot . '/public/assets/js'];
    $extensions = ['php', 'html', 'txt', 'md', 'xml', 'js', 'css'];
    $badPatterns = ['/\x{00C3}./u' => 'mojibake-utf8', '/\x{00C2}./u' => 'mojibake-cp1252', '/\x{FFFD}/u' => 'replacement-char'];
    $issues = [];
    $filesScanned = 0;
    foreach (iterateTextFiles($roots, $extensions) as $filePath) {
        $filesScanned++;
        $content = (string) file_get_contents($filePath);
        foreach ($badPatterns as $pattern => $label) {
            if (preg_match($pattern, $content) === 1) {
                $issues[] = $label . ' => ' . $filePath;
            }
        }
        $skipQuestionInsideWord = str_replace('\\', '/', $filePath);
        $skipQuestionInsideWord = str_ends_with($skipQuestionInsideWord, '/public/assets/js/admin-post-html-editor.js');

        $textWithoutUrls = preg_replace('~https?://\S+|/[^\s\'\"]*\?[^\s\'\"]*~u', '', $content) ?? $content;
        if (!$skipQuestionInsideWord && preg_match('/\p{L}\?\p{L}/u', $textWithoutUrls) === 1) {
            $issues[] = 'question-inside-word => ' . $filePath;
        }
    }
    $issues = array_values(array_unique($issues));
    sort($issues);
    return ['issues' => $issues, 'files_scanned' => $filesScanned];
}

function scanMergeMarkers(string $projectRoot): array
{
    $roots = [$projectRoot . '/app', $projectRoot . '/config', $projectRoot . '/public', $projectRoot . '/scripts'];
    $extensions = ['php', 'html', 'txt', 'md', 'xml', 'js', 'css'];
    $issues = [];
    foreach (iterateTextFiles($roots, $extensions) as $filePath) {
        $content = (string) file_get_contents($filePath);
        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        foreach ($lines as $lineNumber => $line) {
            $trimmed = ltrim($line);
            if (preg_match('/^<{7}(?:\s|$)/', $trimmed) === 1 || preg_match('/^={7}(?:\s|$)/', $trimmed) === 1 || preg_match('/^>{7}(?:\s|$)/', $trimmed) === 1) {
                $issues[] = $filePath . ':' . ($lineNumber + 1);
            }
        }
    }
    $issues = array_values(array_unique($issues));
    sort($issues);
    return $issues;
}

function iterateTextFiles(array $roots, array $extensions): iterable
{
    foreach ($roots as $root) {
        if (!is_dir($root)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }
            $ext = strtolower((string) $file->getExtension());
            if (!in_array($ext, $extensions, true)) {
                continue;
            }
            yield $file->getPathname();
        }
    }
}

function inspectLockFile(string $lockPath): array
{
    $raw = (string) @file_get_contents($lockPath);
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        return ['status' => 'stale'];
    }
    $pid = (int) ($payload['pid'] ?? 0);
    if ($pid <= 0) {
        return ['status' => 'stale'];
    }
    if (isWindowsPidRunning($pid)) {
        return ['status' => 'active'];
    }
    return ['status' => 'stale'];
}

function isWindowsPidRunning(int $pid): bool
{
    if ($pid <= 0) {
        return false;
    }
    $output = [];
    $exitCode = 0;
    @exec('tasklist /FI "PID eq ' . $pid . '" /NH 2>NUL', $output, $exitCode);
    if ($exitCode !== 0 || $output === []) {
        return false;
    }
    $joined = strtolower(trim(implode("\n", $output)));
    if ($joined === '' || str_contains($joined, 'no tasks are running')) {
        return false;
    }
    return str_contains($joined, (string) $pid);
}
