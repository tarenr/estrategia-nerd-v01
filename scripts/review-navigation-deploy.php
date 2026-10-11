<?php

declare(strict_types=1);

// Restricted deployment of the two approved navigation files, with immutable backups.
require __DIR__ . '/backup/EnvLoader.php';
$root = dirname(__DIR__);
Scripts\Backup\EnvLoader::load($root . '/.env');
$config = require $root . '/config/content-sync.php';
$action = $argv[1] ?? '';
if (!in_array($action, ['--backup', '--deploy', '--verify'], true)) { throw new RuntimeException('Use --backup, --deploy ou --verify.'); }
$target = 'production';
foreach (array_slice($argv, 2) as $argument) {
    if (!preg_match('/^--target=(stage|production)$/', $argument, $matches)) { throw new RuntimeException('Use --target=stage ou --target=production.'); }
    $target = $matches[1];
}
$profile = $config['profiles'][$target]['code_deploy'];
$files = ['app/Repositories/PostRepository.php', 'app/Services/Site/PostService.php'];
$dir = $root . '/storage/review-navigation-20261011' . ($target === 'stage' ? '-stage' : '');
if (!is_dir($dir)) { mkdir($dir, 0700, true); }
$ftp = ftp_connect($profile['host'], (int) $profile['port'], 30);
if ($ftp === false || !ftp_login($ftp, $profile['username'], $profile['password'])) { throw new RuntimeException('FTP indisponível.'); }
ftp_pasv($ftp, (bool) $profile['passive']);
function navigationRemoteBytes(FTP\Connection $ftp, string $path): string
{
    $stream = fopen('php://temp', 'w+');
    if ($stream === false || !ftp_fget($ftp, $stream, $path, FTP_BINARY)) { throw new RuntimeException('Arquivo remoto indisponível.'); }
    rewind($stream);
    $bytes = stream_get_contents($stream);
    fclose($stream);
    if ($bytes === false || $bytes === '') { throw new RuntimeException('Arquivo remoto vazio.'); }
    return $bytes;
}
try {
    $remoteRoot = '/' . trim((string) $profile['root'], '/');
    if ($remoteRoot === '/' || str_contains($remoteRoot, '..')) { throw new RuntimeException('Raiz de deploy inválida.'); }
    if (!str_ends_with($remoteRoot, '/_app_core') && ftp_size($ftp, $remoteRoot . '/_app_core/bootstrap.php') >= 0) { $remoteRoot .= '/_app_core'; }
    if ($target === 'stage' && !preg_match('~/stage(?:/_app_core)?$~', $remoteRoot)) { throw new RuntimeException('Raiz de stage inválida.'); }
    $manifestPath = $dir . '/before.json';
    $manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR) : ['remoteRoot'=>$remoteRoot,'files'=>[]];
    if ($manifest['remoteRoot'] !== $remoteRoot) { throw new RuntimeException('Raiz mudou desde o backup.'); }
    if ($action === '--backup') {
        foreach ($files as $file) {
            $bytes = navigationRemoteBytes($ftp, $remoteRoot . '/' . $file);
            $destination = $dir . '/before/' . $file;
            if (!is_dir(dirname($destination))) { mkdir(dirname($destination), 0700, true); }
            if (!is_file($destination)) { file_put_contents($destination, $bytes, LOCK_EX); }
            if ($bytes !== file_get_contents($destination)) { throw new RuntimeException('Backup existente difere do remoto.'); }
            $manifest['files'][$file] = hash('sha256', $bytes);
            $separator = str_contains($bytes, "\r\n") ? "\r\n" : "\n";
            if ($file === $files[0]) {
                $anchors = [
                    "        \$nextStepSelect = \$this->supportsNextStepColumn() ? 'p.proximo_post_id' : 'NULL AS proximo_post_id';" . $separator,
                    '                       p.seo_title, p.seo_description, p.tags, p.autor_id, {$nextStepSelect},',
                ];
                $replacements = [
                    $anchors[0] . "        \$tipoPostSelect = \$this->supportsTipoPostColumn() ? 'p.tipo_post' : 'NULL AS tipo_post';" . $separator,
                    '                       p.seo_title, p.seo_description, p.tags, p.autor_id, {$nextStepSelect}, {$tipoPostSelect},',
                ];
            } else {
                $anchors = ["        \$siteName = (string) portal_config('nome_site', 'Estrategia Nerd');"];
                $replacements = ["        // Independent reviews follow the configured reading; other posts keep chronology." . $separator
                    . "        if ((\$row['tipo_post'] ?? '') === 'review' && is_array(\$nextStep)) {" . $separator
                    . "            \$next = array_replace(\$nextStep, ['direction' => 'proximo']);" . $separator
                    . '        }' . $separator . $anchors[0]];
            }
            foreach ($anchors as $index => $anchor) {
                if (substr_count($bytes, $anchor) !== 1) { throw new RuntimeException('Trecho remoto não é unívoco: ' . $file); }
                $bytes = str_replace($anchor, $replacements[$index], $bytes);
            }
            $payload = $dir . '/deploy/' . $file;
            if (!is_dir(dirname($payload))) { mkdir(dirname($payload), 0700, true); }
            if (is_file($payload) && file_get_contents($payload) !== $bytes) { throw new RuntimeException('Payload anterior divergiu.'); }
            if (!is_file($payload)) { file_put_contents($payload, $bytes); }
            $manifest['payloadHashes'][$file] = hash('sha256', $bytes);
        }
        if (!is_file($manifestPath)) { file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)); }
        echo "PASS: backup remoto completo; payload restrito aos trechos de navegação.\n";
        exit;
    }
    foreach ($files as $file) {
        if (!isset($manifest['files'][$file])) { throw new RuntimeException('Backup incompleto.'); }
        $bytes = navigationRemoteBytes($ftp, $remoteRoot . '/' . $file);
        $remoteHash = hash('sha256', $bytes);
        $localHash = hash_file('sha256', $dir . '/deploy/' . $file);
        if ($localHash !== $manifest['payloadHashes'][$file]) { throw new RuntimeException('Payload alterado desde a preparação.'); }
        if ($action === '--deploy' && $remoteHash !== $manifest['files'][$file] && $remoteHash !== $localHash) { throw new RuntimeException('Mudança concorrente remota: ' . $file); }
    }
    foreach ($files as $file) {
        $localHash = hash_file('sha256', $dir . '/deploy/' . $file);
        if ($action === '--deploy' && !ftp_put($ftp, $remoteRoot . '/' . $file, $dir . '/deploy/' . $file, FTP_BINARY)) { throw new RuntimeException('Upload falhou: ' . $file); }
        if (hash('sha256', navigationRemoteBytes($ftp, $remoteRoot . '/' . $file)) !== $localHash) { throw new RuntimeException('Hash remoto divergente: ' . $file); }
        echo 'PASS remoto: ' . $file . PHP_EOL;
    }
    file_put_contents($dir . '/validation.json', json_encode(['files'=>$files,'remoteHashesMatch'=>true,'verifiedAt'=>gmdate('c')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
} finally {
    ftp_close($ftp);
}
