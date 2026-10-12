<?php
/**
 * -----------------------------------------------------------------------------
 * @file        scripts/menu-instagram-js-deploy.php
 * @project     Estrategia Nerd
 * @purpose     Deploy restrito de UM arquivo: assets/js/site-home.js (menu x secao do
 *              Instagram, FEAT-010 v2.12.1), com backup, hash e verificacao HTTP
 * @usage       C:\xampp\php\php.exe scripts/menu-instagram-js-deploy.php <acao> --target=<stage|production>
 *              acoes: --backup | --deploy | --verify | --rollback
 * @notes       Pontual: so age se o remoto for exatamente a versao anterior (hash). Escopo remoto:
 *              <public>/assets/js/site-home.js (e o temporario .tmp ao lado). Nada de PHP, .env ou banco.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }

require __DIR__ . '/backup/EnvLoader.php';
$root = dirname(__DIR__);
Scripts\Backup\EnvLoader::load($root . '/.env');
$config = require $root . '/config/content-sync.php';

const BEFORE_SHA = '809cdfe27a4985d3dd45c3661b7e99a49b47e0d46001f517391aa12b8f5365ba'; // versao publicada ate 11/10/2026
const AFTER_SHA  = '86181542d5387804aa9335fe2f64c834a791bb1a142c1fbea3a82b112de1c0cd'; // v2.12.1 (instagram -> instagram-feed)
const REMOTE_REL = 'assets/js/site-home.js';
const SITE_URLS  = ['stage' => 'https://estrategianerd.com.br/stage', 'production' => 'https://estrategianerd.com.br'];

$action = (string) ($argv[1] ?? '');
if (!in_array($action, ['--backup', '--deploy', '--verify', '--rollback'], true)) {
    fwrite(STDERR, "Use --backup, --deploy, --verify ou --rollback, com --target=stage|production.\n");
    exit(2);
}
$target = '';
foreach (array_slice($argv, 2) as $argument) {
    if (preg_match('/^--target=(stage|production)$/', $argument, $matches) === 1) { $target = $matches[1]; }
}
if ($target === '') {
    fwrite(STDERR, "Informe --target=stage ou --target=production (sem padrao, de proposito).\n");
    exit(2);
}

$localFile = $root . '/public/' . REMOTE_REL;
if (!is_file($localFile) || hash_file('sha256', $localFile) !== AFTER_SHA) {
    fwrite(STDERR, "STOP: site-home.js local nao e a versao aprovada (v2.12.1).\n");
    exit(1);
}

$profile = $config['profiles'][$target];
$code    = (array) $profile['code_deploy'];
$dir     = $root . '/storage/menu-instagram-js-deploy/' . $target;
$backup  = $dir . '/site-home.before.js';

function remoteBytes(FTP\Connection $ftp, string $path): string
{
    $stream = fopen('php://temp', 'w+b');
    if ($stream === false || !@ftp_fget($ftp, $stream, $path, FTP_BINARY)) { throw new RuntimeException('Arquivo remoto indisponivel.'); }
    rewind($stream);
    $bytes = (string) stream_get_contents($stream);
    fclose($stream);
    if ($bytes === '') { throw new RuntimeException('Arquivo remoto vazio.'); }

    return $bytes;
}

function putAndVerify(FTP\Connection $ftp, string $remote, string $bytes): void
{
    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, $bytes);
    rewind($stream);
    $ok = @ftp_fput($ftp, $remote, $stream, FTP_BINARY);
    fclose($stream);
    if (!$ok || hash('sha256', remoteBytes($ftp, $remote)) !== hash('sha256', $bytes)) { throw new RuntimeException('Envio remoto nao confere.'); }
}

function httpGet(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30, CURLOPT_USERAGENT => 'EstrategiaNerd-JsDeploy/1.0']);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $status, 'body' => is_string($body) ? $body : ''];
}

$ftp = null;
try {
    foreach (['host', 'port', 'username', 'password', 'root'] as $required) {
        if (trim((string) ($code[$required] ?? '')) === '') { throw new RuntimeException('Configuracao de FTP incompleta (' . $required . ').'); }
    }
    $ftp = @ftp_connect((string) $code['host'], (int) $code['port'], 30);
    if ($ftp === false || !@ftp_login($ftp, (string) $code['username'], (string) $code['password'])) { throw new RuntimeException('FTP indisponivel.'); }
    @ftp_pasv($ftp, (bool) ($code['passive'] ?? true));

    $publicRoot = dirname('/' . trim((string) $profile['uploads']['root'], '/'));
    $validRoot = $target === 'stage'
        ? str_ends_with($publicRoot, '/public_html/stage')
        : (str_ends_with($publicRoot, '/public_html') && !str_contains($publicRoot, '/stage'));
    if (!$validRoot) { throw new RuntimeException('Raiz publica remota nao confere com o alvo.'); }
    $remote = $publicRoot . '/' . REMOTE_REL;

    if ($action === '--backup') {
        $bytes = remoteBytes($ftp, $remote);
        $sha = hash('sha256', $bytes);
        if (!in_array($sha, [BEFORE_SHA, AFTER_SHA], true)) { throw new RuntimeException('Remoto difere da versao anterior esperada; nada foi feito.'); }
        if (!is_dir($dir)) { mkdir($dir, 0700, true); }
        if (is_file($backup) && hash_file('sha256', $backup) !== $sha && $sha === BEFORE_SHA) { throw new RuntimeException('Backup existente difere do remoto.'); }
        if (!is_file($backup) && $sha === BEFORE_SHA) { file_put_contents($backup, $bytes, LOCK_EX); }
        echo 'PASS: backup ' . ($sha === BEFORE_SHA ? 'guardado em ' . $backup : 'nao necessario (remoto ja esta na v2.12.1)') . PHP_EOL;
        exit(0);
    }

    if ($action === '--deploy') {
        if (!is_file($backup)) { throw new RuntimeException('Rode --backup antes de --deploy.'); }
        $current = hash('sha256', remoteBytes($ftp, $remote));
        if ($current === AFTER_SHA) { echo "PASS: remoto ja esta na v2.12.1 (nada a fazer).\n"; exit(0); }
        if ($current !== BEFORE_SHA) { throw new RuntimeException('Remoto mudou desde o backup; abortado.'); }
        $tmp = $remote . '.tmp';
        putAndVerify($ftp, $tmp, (string) file_get_contents($localFile));
        if (!@ftp_rename($ftp, $tmp, $remote)) { @ftp_delete($ftp, $tmp); throw new RuntimeException('Hospedagem recusou rename; remoto intacto.'); }
        if (hash('sha256', remoteBytes($ftp, $remote)) !== AFTER_SHA) { throw new RuntimeException('Hash remoto nao confere apos a troca.'); }
        echo "PASS: site-home.js v2.12.1 aplicado em {$target}.\n";
        exit(0);
    }

    if ($action === '--rollback') {
        if (!is_file($backup) || hash_file('sha256', $backup) !== BEFORE_SHA) { throw new RuntimeException('Backup ausente ou invalido.'); }
        $tmp = $remote . '.tmp';
        putAndVerify($ftp, $tmp, (string) file_get_contents($backup));
        if (!@ftp_rename($ftp, $tmp, $remote)) { @ftp_delete($ftp, $tmp); throw new RuntimeException('Hospedagem recusou rename; remoto intacto.'); }
        if (hash('sha256', remoteBytes($ftp, $remote)) !== BEFORE_SHA) { throw new RuntimeException('Hash remoto nao confere apos o rollback.'); }
        echo "PASS: versao anterior restaurada em {$target}.\n";
        exit(0);
    }

    // --verify
    $base = SITE_URLS[$target];
    $failures = [];
    if (hash('sha256', remoteBytes($ftp, $remote)) !== AFTER_SHA) { $failures[] = 'hash remoto != v2.12.1'; }
    $home = httpGet($base . '/?jscheck=' . time());
    if ($home['code'] !== 200) { $failures[] = 'home respondeu ' . $home['code']; }
    if (preg_match('~src="([^"]*assets/js/site-home\.js[^"]*)"~', $home['body'], $match) !== 1) {
        $failures[] = 'home nao referencia site-home.js';
    } else {
        $served = httpGet(html_entity_decode($match[1]));
        if ($served['code'] !== 200 || hash('sha256', $served['body']) !== AFTER_SHA) { $failures[] = 'JS servido pela home (' . $match[1] . ') nao e a v2.12.1 (codigo ' . $served['code'] . ')'; }
    }
    if (!str_contains($home['body'], 'id="instagram-feed"')) { $failures[] = 'secao instagram-feed ausente na home'; }
    if ($failures !== []) { fwrite(STDERR, 'FAIL: ' . implode(' | ', $failures) . PHP_EOL); exit(1); }
    echo "PASS: {$target} serve a v2.12.1; home 200; secao #instagram-feed presente.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'STOP: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($ftp instanceof FTP\Connection) { ftp_close($ftp); }
}
