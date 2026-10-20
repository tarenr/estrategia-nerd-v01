<?php

declare(strict_types=1);

// Deploy restrito SOMENTE na stage: bloqueio de indexacao (X-Robots-Tag e robots.txt) em 2 arquivos,
// com backup imutavel dos arquivos remotos e patch aplicado sobre a versao que esta na stage.
// Uso: php scripts/stage-noindex-deploy.php --backup | --deploy | --verify
require __DIR__ . '/backup/EnvLoader.php';
$root = dirname(__DIR__);
Scripts\Backup\EnvLoader::load($root . '/.env');
$config = require $root . '/config/content-sync.php';
$action = $argv[1] ?? '';
if (!in_array($action, ['--backup', '--deploy', '--verify'], true)) { throw new RuntimeException('Use --backup, --deploy ou --verify.'); }
$profile = $config['profiles']['stage']['code_deploy'];
$files = ['bootstrap.php', 'app/Controllers/Site/SeoController.php'];
$dir = $root . '/storage/stage-noindex-20261015';
if (!is_dir($dir)) { mkdir($dir, 0700, true); }

function noindexRemoteBytes(FTP\Connection $ftp, string $path): string
{
    $stream = fopen('php://temp', 'w+');
    if ($stream === false || !ftp_fget($ftp, $stream, $path, FTP_BINARY)) { throw new RuntimeException('Arquivo remoto indisponivel: ' . $path); }
    rewind($stream);
    $bytes = stream_get_contents($stream);
    fclose($stream);
    if ($bytes === false || $bytes === '') { throw new RuntimeException('Arquivo remoto vazio.'); }
    return $bytes;
}

/** Aplica o patch (trechos unicos) sobre os bytes remotos. */
function noindexPatch(string $file, string $bytes): string
{
    if (str_contains($bytes, "\r\n")) { throw new RuntimeException('Fim de linha inesperado: ' . $file); }
    if ($file === 'bootstrap.php') {
        $anchor = "    session_start();\n}\n";
        $block = "\n// A stage fica sob o dominio publico (/stage/) e o robots.txt valido e o da raiz: o bloqueio de indexacao\n"
            . "// precisa ir em cada resposta. Producao e local nao sao afetados.\n"
            . "if (PHP_SAPI !== 'cli' && strtolower(trim((string) (\$GLOBALS['config']['app']['env'] ?? 'production'))) === 'stage' && !headers_sent()) {\n"
            . "    header('X-Robots-Tag: noindex, nofollow', true);\n}\n";
        if (substr_count($bytes, $anchor) !== 1) { throw new RuntimeException('Trecho remoto nao e univoco: ' . $file); }
        return str_replace($anchor, $anchor . $block, $bytes);
    }
    $useAnchor = "use App\\Services\\Site\\SitemapService;\n";
    $robotsAnchor = "            'Sitemap: ' . url('/sitemap.xml'),\n        ];\n";
    if (substr_count($bytes, $useAnchor) !== 1 || substr_count($bytes, $robotsAnchor) !== 1) { throw new RuntimeException('Trecho remoto nao e univoco: ' . $file); }
    $bytes = str_replace($useAnchor, $useAnchor . "use App\\Support\\EnvironmentManager;\n", $bytes);
    $stageBlock = "\n        if (EnvironmentManager::current() === 'stage') {\n            // Stage nunca entra em buscadores.\n            \$lines = [\n                'User-agent: *',\n                'Disallow: /',\n            ];\n        }\n";
    return str_replace($robotsAnchor, $robotsAnchor . $stageBlock, $bytes);
}

$ftp = ftp_connect($profile['host'], (int) $profile['port'], 30);
if ($ftp === false || !ftp_login($ftp, $profile['username'], $profile['password'])) { throw new RuntimeException('FTP indisponivel.'); }
ftp_pasv($ftp, (bool) $profile['passive']);
try {
    $remoteRoot = '/' . trim((string) $profile['root'], '/');
    if (!str_ends_with($remoteRoot, '/_app_core') && ftp_size($ftp, $remoteRoot . '/_app_core/bootstrap.php') >= 0) { $remoteRoot .= '/_app_core'; }
    if (!preg_match('~/stage/_app_core$~', $remoteRoot)) { throw new RuntimeException('Raiz de stage invalida.'); }
    $manifestPath = $dir . '/before.json';
    $manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR) : ['remoteRoot' => $remoteRoot, 'files' => [], 'payloadHashes' => []];
    if ($manifest['remoteRoot'] !== $remoteRoot) { throw new RuntimeException('Raiz mudou desde o backup.'); }
    if ($action === '--backup') {
        if (is_file($manifestPath)) { throw new RuntimeException('Backup ja feito; o estado anterior e imutavel.'); }
        foreach ($files as $file) {
            $bytes = noindexRemoteBytes($ftp, $remoteRoot . '/' . $file);
            $before = $dir . '/before/' . $file;
            if (!is_dir(dirname($before))) { mkdir(dirname($before), 0700, true); }
            if (file_put_contents($before, $bytes, LOCK_EX) !== strlen($bytes)) { throw new RuntimeException('Backup incompleto.'); }
            $manifest['files'][$file] = hash('sha256', $bytes);
            $patched = noindexPatch($file, $bytes);
            $payload = $dir . '/deploy/' . $file;
            if (!is_dir(dirname($payload))) { mkdir(dirname($payload), 0700, true); }
            file_put_contents($payload, $patched, LOCK_EX);
            $manifest['payloadHashes'][$file] = hash('sha256', $patched);
        }
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        echo "PASS: backup remoto completo; payload restrito ao bloqueio de indexacao.\n";
        exit;
    }
    foreach ($files as $file) {
        if (!isset($manifest['files'][$file])) { throw new RuntimeException('Backup incompleto.'); }
        $payload = $dir . '/deploy/' . $file;
        $payloadHash = hash_file('sha256', $payload);
        if ($payloadHash !== $manifest['payloadHashes'][$file]) { throw new RuntimeException('Payload alterado desde a preparacao.'); }
        $remoteHash = hash('sha256', noindexRemoteBytes($ftp, $remoteRoot . '/' . $file));
        if ($action === '--deploy' && $remoteHash !== $manifest['files'][$file] && $remoteHash !== $payloadHash) { throw new RuntimeException('Mudanca concorrente remota: ' . $file); }
    }
    foreach ($files as $file) {
        $payload = $dir . '/deploy/' . $file;
        $payloadHash = hash_file('sha256', $payload);
        if ($action === '--deploy' && !ftp_put($ftp, $remoteRoot . '/' . $file, $payload, FTP_BINARY)) { throw new RuntimeException('Upload falhou: ' . $file); }
        if (hash('sha256', noindexRemoteBytes($ftp, $remoteRoot . '/' . $file)) !== $payloadHash) { throw new RuntimeException('Hash remoto divergente: ' . $file); }
        echo 'PASS remoto: ' . $file . PHP_EOL;
    }
    file_put_contents($dir . '/validation.json', json_encode(['files' => $files, 'remoteHashesMatch' => true, 'verifiedAt' => gmdate('c')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
} finally {
    ftp_close($ftp);
}
