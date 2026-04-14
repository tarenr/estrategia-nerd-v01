<?php
declare(strict_types=1);

require_once __DIR__ . '/backup/EnvLoader.php';

use Scripts\Backup\EnvLoader;

EnvLoader::load(dirname(__DIR__) . '/.env');

$projectRoot = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
$normalizedProjectRoot = normalizePath($projectRoot);

$defaultCanonical = 'D:\\Taren\\htdocs\\estrategia-nerd';
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
$errors = [];

echo "== Preflight Estrategia Nerd ==\n";
echo "Project root atual: {$projectRoot}\n";

if ($canonicalRoots !== [] && !in_array($normalizedProjectRoot, $canonicalRoots, true)) {
    $warnings[] = 'Projeto aberto fora do caminho canonico. Ajuste antes de modificar/publicar.';
    echo "Canonicos esperados:\n";
    foreach ($canonicalRoots as $canonical) {
        echo ' - ' . $canonical . "\n";
    }
}

$lockCandidates = [
    $projectRoot . '/storage/content-sync/.content-sync-running.lock.json',
    $projectRoot . '/storage/code-sync/.content-sync-running.lock.json',
];
foreach ($lockCandidates as $lockFile) {
    if (is_file($lockFile)) {
        $warnings[] = 'Existe lock de rotina pendente: ' . $lockFile;
    }
}

$gitBinary = 'git';
$statusCmd = $gitBinary . ' -C ' . escapeshellarg($projectRoot) . ' status --porcelain';
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

if ($errors !== []) {
    echo "\nFalhas bloqueantes:\n";
    foreach ($errors as $error) {
        echo ' - ' . $error . "\n";
    }
    exit(1);
}

echo "\nPreflight finalizado com sucesso.\n";
exit(0);

function normalizePath(string $path): string
{
    $path = str_replace('/', '\\', trim($path));
    $real = realpath($path);
    if (is_string($real) && $real !== '') {
        $path = $real;
    }

    return strtolower(rtrim(str_replace('/', '\\', $path), '\\'));
}

/**
 * @return array{issues: array<int, string>, files_scanned: int}
 */
function scanEncodingIssues(string $projectRoot): array
{
    $roots = [
        $projectRoot . '/app/Views',
        $projectRoot . '/app/Services',
        $projectRoot . '/app/Controllers',
        $projectRoot . '/config',
        $projectRoot . '/public/assets/js',
    ];
    $extensions = ['php', 'html', 'txt', 'md', 'xml', 'js', 'css'];
    $badPatterns = [
        '/\x{00C3}./u' => 'mojibake-utf8',
        '/\x{00C2}./u' => 'mojibake-cp1252',
        '/\x{FFFD}/u' => 'replacement-char',
    ];

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

        $textWithoutUrls = preg_replace('~https?://\S+|/[^\s\'"]*\?[^\s\'"]*~u', '', $content) ?? $content;
        if (preg_match('/\p{L}\?\p{L}/u', $textWithoutUrls) === 1) {
            $issues[] = 'question-inside-word => ' . $filePath;
        }
    }

    $issues = array_values(array_unique($issues));
    sort($issues);

    return [
        'issues' => $issues,
        'files_scanned' => $filesScanned,
    ];
}

/**
 * @return array<int, string>
 */
function scanMergeMarkers(string $projectRoot): array
{
    $roots = [
        $projectRoot . '/app',
        $projectRoot . '/config',
        $projectRoot . '/public',
        $projectRoot . '/scripts',
    ];
    $extensions = ['php', 'html', 'txt', 'md', 'xml', 'js', 'css'];
    $issues = [];

    foreach (iterateTextFiles($roots, $extensions) as $filePath) {
        $content = (string) file_get_contents($filePath);
        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        foreach ($lines as $lineNumber => $line) {
            $trimmed = ltrim($line);
            $isStart = preg_match('/^<{7}(?:\s|$)/', $trimmed) === 1;
            $isSplit = preg_match('/^={7}(?:\s|$)/', $trimmed) === 1;
            $isEnd = preg_match('/^>{7}(?:\s|$)/', $trimmed) === 1;

            if ($isStart || $isSplit || $isEnd) {
                $issues[] = $filePath . ':' . ($lineNumber + 1);
            }
        }
    }

    $issues = array_values(array_unique($issues));
    sort($issues);
    return $issues;
}

/**
 * @return iterable<int, string>
 */
function iterateTextFiles(array $roots, array $extensions): iterable
{
    foreach ($roots as $root) {
        if (!is_dir($root)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

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
