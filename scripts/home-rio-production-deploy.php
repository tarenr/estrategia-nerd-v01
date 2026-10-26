<?php
/**
 * -----------------------------------------------------------------------------
 * @file        scripts/home-rio-production-deploy.php
 * @project     Estrategia Nerd
 * @purpose     Deploy da home nova (FEAT-015 + FEAT-016) em PRODUCAO com o pacote TIRADO DA STAGE
 *              (fluxo oficial), conferencia do estado real, copia previa, hash e rollback.
 * @usage       C:\xampp\php\php.exe scripts/home-rio-production-deploy.php <acao>
 *              acoes: --check | --backup | --deploy | --verify | --rollback
 * @notes       Base: o manifesto do deploy da stage (storage/home-rio-deploy/stage/manifest.json).
 *              - --check: so leitura. Producao precisa estar como a stage estava ANTES do deploy
 *                (os 4 alterados iguais as copias "antes" da stage; os novos ausentes) e com as
 *                dependencias da home iguais as da stage.
 *              - --backup: baixa o pacote da stage, confere com o manifesto da stage e guarda a
 *                copia dos 4 arquivos de producao que serao trocados.
 *              - O backup completo de producao (banco + arquivos) e feito antes, por
 *                scripts/backup.php run production. Nada de .env ou banco aqui.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }

require __DIR__ . '/backup/EnvLoader.php';
$root = dirname(__DIR__);
Scripts\Backup\EnvLoader::load($root . '/.env');
$config = require $root . '/config/content-sync.php';

const SITE_URL = 'https://estrategianerd.com.br';
// Diferencas producao x stage ja analisadas (25/10/2026) que nao afetam a home nova.
const KNOWN_PROD_DIFFS = [
    'bootstrap.php' => 'so a stage manda X-Robots-Tag noindex (bloco limitado ao ambiente stage); nao e enviado',
];
// Vai como "versao da PRODUCAO + so a mudanca desta entrega", e nao tirado da stage: o site.css da
// stage tem a FIX-009 (imagens dos artigos), que nunca foi promovida e nao faz parte desta entrega.
const PATCH_ON_PRODUCTION = ['assets/css/site.css'];
// Dependencias da home nova: producao precisa ter o mesmo conteudo que a stage.
const DEPENDENCIES = [
    ['core', 'app/Views/components/site/shared/nav.php'],
    ['core', 'app/Views/components/site/home/posts-preview.php'],
    ['core', 'app/Views/components/site/home/instagram-feed.php'],
    ['core', 'app/Views/components/site/home/newsletter.php'],
    ['core', 'app/Views/components/site/home/footer.php'],
    ['core', 'app/Views/site/home.php'],
    ['core', 'app/Views/layouts/site.php'],
    ['core', 'app/Support/Helpers.php'],
    ['core', 'bootstrap.php'],
    ['core', 'app/Services/Site/InstagramFeedService.php'],
    ['core', 'app/Repositories/PostRepository.php'],
    ['public', 'assets/js/site-hero-preview.js'],
];

$action = (string) ($argv[1] ?? '');
if (!in_array($action, ['--check', '--backup', '--deploy', '--verify', '--rollback'], true)) {
    fwrite(STDERR, "Use --check, --backup, --deploy, --verify ou --rollback.\n");
    exit(2);
}

$stageDir = $root . '/storage/home-rio-deploy/stage';
$stageManifest = json_decode((string) @file_get_contents($stageDir . '/manifest.json'), true);
if (!is_array($stageManifest) || empty($stageManifest['files'])) {
    fwrite(STDERR, "STOP: manifesto do deploy da stage ausente.\n");
    exit(1);
}
$dir = $root . '/storage/home-rio-deploy/production';
$manifestFile = $dir . '/manifest.json';

function sameContent(string $a, string $b): bool
{
    return str_replace("\r\n", "\n", $a) === str_replace("\r\n", "\n", $b);
}

/** Mesma regra do botao de menu aplicada na stage: oculto por padrao, visivel ate 767 px. */
function patchMenuCss(string $bytes): string
{
    $eol = str_contains($bytes, "\r\n") ? "\r\n" : "\n";
    $selector = $eol . '.site-mobile-toggle {' . $eol;
    $start = false;
    $end = 0;
    for ($pos = strpos($bytes, $selector); $pos !== false; $pos = strpos($bytes, $selector, $pos + 1)) {
        $close = strpos($bytes, '}', $pos);
        if ($close !== false && str_contains(substr($bytes, $pos, $close - $pos), '  display: inline-flex;')) {
            if ($start !== false) { throw new RuntimeException('Mais de uma regra .site-mobile-toggle com display em site.css.'); }
            $start = $pos;
            $end = $close;
        }
    }
    if ($start === false) { throw new RuntimeException('Regra .site-mobile-toggle com display nao encontrada em site.css.'); }
    $block = substr($bytes, $start, $end - $start + 1);
    if (substr_count($block, '  display: inline-flex;') !== 1) { throw new RuntimeException('Ancora do patch repetida em site.css.'); }
    $media = $eol . $eol . '@media (max-width: 767px) {' . $eol . '  .site-mobile-toggle {' . $eol . '    display: inline-flex;' . $eol . '  }' . $eol . '}';
    return substr($bytes, 0, $start) . str_replace('  display: inline-flex;', '  display: none;', $block) . $media . substr($bytes, $end + 1);
}

function remoteBytes(FTP\Connection $ftp, string $path): ?string
{
    if (@ftp_size($ftp, $path) < 0) { return null; }
    $stream = fopen('php://temp', 'w+b');
    if ($stream === false || !@ftp_fget($ftp, $stream, $path, FTP_BINARY)) { throw new RuntimeException('Falha ao ler ' . basename($path)); }
    rewind($stream);
    $bytes = (string) stream_get_contents($stream);
    fclose($stream);
    return $bytes;
}

function ensureRemoteDir(FTP\Connection $ftp, string $dir): void
{
    $path = '';
    foreach (explode('/', trim($dir, '/')) as $part) {
        $path .= '/' . $part;
        if (@ftp_chdir($ftp, $path)) { continue; }
        if (!@ftp_mkdir($ftp, $path)) { throw new RuntimeException('Nao foi possivel criar a pasta remota ' . $path); }
    }
    @ftp_chdir($ftp, '/');
}

function putAndVerify(FTP\Connection $ftp, string $remote, string $bytes): void
{
    ensureRemoteDir($ftp, dirname($remote));
    $tmp = $remote . '.tmp';
    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, $bytes);
    rewind($stream);
    $ok = @ftp_fput($ftp, $tmp, $stream, FTP_BINARY);
    fclose($stream);
    if (!$ok || hash('sha256', (string) remoteBytes($ftp, $tmp)) !== hash('sha256', $bytes)) {
        @ftp_delete($ftp, $tmp);
        throw new RuntimeException('Envio nao confere: ' . $remote);
    }
    // Troca pelo rename (o remoto anterior so e substituido se o envio conferiu).
    if (!@ftp_rename($ftp, $tmp, $remote)) { @ftp_delete($ftp, $tmp); throw new RuntimeException('Rename recusado: ' . $remote); }
    if (hash('sha256', (string) remoteBytes($ftp, $remote)) !== hash('sha256', $bytes)) { throw new RuntimeException('Hash remoto nao confere apos a troca: ' . $remote); }
}

/** Conecta no ambiente e devolve [ftp, fn(base, rel) => caminho remoto]. */
function connect(array $profile, string $target): array
{
    $code = (array) $profile['code_deploy'];
    foreach (['host', 'port', 'username', 'password', 'root'] as $required) {
        if (trim((string) ($code[$required] ?? '')) === '') { throw new RuntimeException("Configuracao de FTP incompleta ({$target}: {$required})."); }
    }
    $ftp = @ftp_connect((string) $code['host'], (int) $code['port'], 30);
    if ($ftp === false || !@ftp_login($ftp, (string) $code['username'], (string) $code['password'])) { throw new RuntimeException("FTP indisponivel ({$target})."); }
    @ftp_pasv($ftp, (bool) ($code['passive'] ?? true));
    $publicRoot = dirname('/' . trim((string) $profile['uploads']['root'], '/'));
    $validRoot = $target === 'stage'
        ? str_ends_with($publicRoot, '/public_html/stage')
        : (str_ends_with($publicRoot, '/public_html') && !str_contains($publicRoot, '/stage'));
    if (!$validRoot) { throw new RuntimeException("Raiz publica remota nao confere com {$target}."); }
    $coreRoot = '/' . trim((string) $code['root'], '/');
    if (!str_ends_with($coreRoot, '/_app_core') && @ftp_size($ftp, $coreRoot . '/_app_core/bootstrap.php') >= 0) { $coreRoot .= '/_app_core'; }
    if (!in_array($coreRoot, [$publicRoot, $publicRoot . '/_app_core'], true) || @ftp_size($ftp, $coreRoot . '/bootstrap.php') < 0) {
        throw new RuntimeException("Core remoto nao confere com {$target}.");
    }
    return [$ftp, static fn (string $base, string $rel): string => ($base === 'core' ? $coreRoot : $publicRoot) . '/' . $rel];
}

$prod = null;
$stage = null;
try {
    [$prod, $prodPath] = connect($config['profiles']['production'], 'production');

    if ($action === '--check') {
        [$stage, $stagePath] = connect($config['profiles']['stage'], 'stage');
        $problems = [];
        foreach (DEPENDENCIES as [$base, $rel]) {
            $p = remoteBytes($prod, $prodPath($base, $rel));
            $s = remoteBytes($stage, $stagePath($base, $rel));
            $same = $p !== null && $s !== null && sameContent($p, $s);
            $known = KNOWN_PROD_DIFFS[$rel] ?? null;
            echo ($same ? 'IGUAL A STAGE ' : ($known !== null ? 'CONHECIDA     ' : 'DIFERE        ')) . $rel . ($known !== null && !$same ? " ({$known})" : '') . "\n";
            if (!$same && $known === null) { $problems[] = "producao difere da stage: {$rel}"; }
        }
        foreach ($stageManifest['files'] as $f) {
            $p = remoteBytes($prod, $prodPath($f['base'], $f['rel']));
            if (in_array($f['rel'], PATCH_ON_PRODUCTION, true)) {
                if ($p === null) { $problems[] = "ausente em producao: {$f['rel']}"; continue; }
                patchMenuCss($p);
                echo 'PATCH OK (versao da producao + regra do menu) ' . $f['rel'] . "\n";
                continue;
            }
            if ($f['kind'] === 'new') {
                echo ($p === null ? 'NOVO          ' : 'JA EXISTE     ') . $f['rel'] . "\n";
                if ($p !== null) { $problems[] = "arquivo novo ja existe em producao: {$f['rel']}"; }
                continue;
            }
            $before = (string) @file_get_contents($stageDir . '/before/' . $f['store']);
            $same = $p !== null && $before !== '' && sameContent($p, $before);
            echo ($same ? 'COMO A STAGE ANTES ' : 'DIFERE DO "ANTES"  ') . $f['rel'] . "\n";
            if (!$same) { $problems[] = "producao difere da stage antes do deploy: {$f['rel']}"; }
        }
        if ($problems !== []) { fwrite(STDERR, "ATENCAO:\n- " . implode("\n- ", $problems) . "\n"); exit(1); }
        echo "PASS: producao no estado esperado; nada foi enviado.\n";
        exit(0);
    }

    if ($action === '--backup') {
        if (is_file($manifestFile)) { throw new RuntimeException('Ja existe um backup/pacote em ' . $dir . '; confira antes de refazer.'); }
        [$stage, $stagePath] = connect($config['profiles']['stage'], 'stage');
        if (!is_dir($dir . '/before')) { mkdir($dir . '/before', 0700, true); }
        if (!is_dir($dir . '/package')) { mkdir($dir . '/package', 0700, true); }
        $manifest = ['created_at' => date('c'), 'source' => 'stage', 'files' => []];
        foreach ($stageManifest['files'] as $f) {
            if (in_array($f['rel'], PATCH_ON_PRODUCTION, true)) {
                $current = remoteBytes($prod, $prodPath($f['base'], $f['rel']));
                if ($current === null) { throw new RuntimeException('Ausente em producao: ' . $f['rel']); }
                $after = patchMenuCss($current);
                file_put_contents($dir . '/before/' . $f['store'], $current, LOCK_EX);
                file_put_contents($dir . '/package/' . $f['store'], $after, LOCK_EX);
                $manifest['files'][] = ['rel' => $f['rel'], 'base' => $f['base'], 'kind' => $f['kind'], 'before' => hash('sha256', $current), 'after' => hash('sha256', $after), 'store' => $f['store'], 'source' => 'producao + regra do menu'];
                continue;
            }
            // Pacote tirado da stage, conferido com o que foi enviado e testado la.
            $bytes = remoteBytes($stage, $stagePath($f['base'], $f['rel']));
            if ($bytes === null || hash('sha256', $bytes) !== $f['after']) { throw new RuntimeException('Stage nao confere com o manifesto: ' . $f['rel']); }
            file_put_contents($dir . '/package/' . $f['store'], $bytes, LOCK_EX);
            $current = remoteBytes($prod, $prodPath($f['base'], $f['rel']));
            if ($f['kind'] === 'new') {
                if ($current !== null) { throw new RuntimeException('Arquivo novo ja existe em producao: ' . $f['rel']); }
                $before = null;
            } else {
                $stageBefore = (string) @file_get_contents($stageDir . '/before/' . $f['store']);
                if ($current === null || !sameContent($current, $stageBefore)) { throw new RuntimeException('Producao difere da stage antes do deploy: ' . $f['rel']); }
                file_put_contents($dir . '/before/' . $f['store'], $current, LOCK_EX);
                $before = hash('sha256', $current);
            }
            $manifest['files'][] = ['rel' => $f['rel'], 'base' => $f['base'], 'kind' => $f['kind'], 'before' => $before, 'after' => $f['after'], 'store' => $f['store']];
        }
        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        echo 'PASS: pacote da stage conferido e copias de producao em ' . $dir . ' (' . count($manifest['files']) . " arquivos).\n";
        exit(0);
    }

    $manifest = is_file($manifestFile) ? json_decode((string) file_get_contents($manifestFile), true) : null;
    if (!is_array($manifest)) { throw new RuntimeException('Rode --backup antes.'); }
    // Ordem: novos (sem efeito no site) -> CSS substituido -> alterados, com a virada (HomeController) por ultimo.
    $files = array_merge(
        array_values(array_filter($manifest['files'], static fn (array $f): bool => $f['kind'] === 'new')),
        array_values(array_filter($manifest['files'], static fn (array $f): bool => $f['kind'] === 'replaced')),
        array_values(array_filter($manifest['files'], static fn (array $f): bool => $f['kind'] === 'patched'))
    );

    if ($action === '--deploy') {
        foreach ($files as $f) {
            $current = remoteBytes($prod, $prodPath($f['base'], $f['rel']));
            $currentSha = $current === null ? null : hash('sha256', $current);
            if ($currentSha === $f['after']) { echo "JA OK  {$f['rel']}\n"; continue; }
            if ($currentSha !== $f['before']) { throw new RuntimeException('Producao mudou desde o backup: ' . $f['rel'] . '; abortado.'); }
            $bytes = (string) file_get_contents($dir . '/package/' . $f['store']);
            if (hash('sha256', $bytes) !== $f['after']) { throw new RuntimeException('Pacote local alterado: ' . $f['rel']); }
            putAndVerify($prod, $prodPath($f['base'], $f['rel']), $bytes);
            echo "ENVIADO {$f['rel']}\n";
        }
        echo "PASS: pacote da stage aplicado em producao e conferido por hash.\n";
        exit(0);
    }

    if ($action === '--rollback') {
        foreach (array_reverse($files) as $f) {
            $path = $prodPath($f['base'], $f['rel']);
            if ($f['kind'] !== 'new') {
                $bytes = (string) file_get_contents($dir . '/before/' . $f['store']);
                if (hash('sha256', $bytes) !== $f['before']) { throw new RuntimeException('Copia anterior invalida: ' . $f['rel']); }
                putAndVerify($prod, $path, $bytes);
                echo "RESTAURADO {$f['rel']}\n";
            } elseif (@ftp_size($prod, $path) >= 0) {
                if (!@ftp_delete($prod, $path)) { throw new RuntimeException('Nao foi possivel remover ' . $f['rel']); }
                echo "REMOVIDO {$f['rel']}\n";
            }
        }
        echo "PASS: producao voltou ao estado anterior.\n";
        exit(0);
    }

    // --verify: hashes (paridade com a stage) + home nova renderizada e indexavel.
    $failures = [];
    foreach ($files as $f) {
        $current = remoteBytes($prod, $prodPath($f['base'], $f['rel']));
        if ($current === null || hash('sha256', $current) !== $f['after']) { $failures[] = 'hash != pacote da stage: ' . $f['rel']; }
    }
    $ch = curl_init(SITE_URL . '/?homecheck=' . time());
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30, CURLOPT_HEADER => true, CURLOPT_USERAGENT => 'EstrategiaNerd-HomeRioDeploy/1.0']);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    if ($status !== 200) { $failures[] = 'home respondeu ' . $status; }
    foreach (['data-flow-path', 'data-flow-echo', 'window.EN_ECHO_OUTFIT', 'site-home-flow.js', 'id="instagram-feed"'] as $marker) {
        if (!str_contains($body, $marker)) { $failures[] = 'home sem ' . $marker; }
    }
    // Hashes acima: 13 iguais a stage; site.css = producao + regra do menu (excecao registrada na FEAT-015).
    if (stripos($headers, 'noindex') !== false || stripos($body, 'name="robots" content="noindex') !== false) { $failures[] = 'home de producao com noindex'; }
    if ($failures !== []) { fwrite(STDERR, 'FAIL: ' . implode(' | ', $failures) . PHP_EOL); exit(1); }
    echo "PASS: producao = pacote da stage (hashes); home nova 200, indexavel, com rio, Echo e feed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'STOP: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($prod instanceof FTP\Connection) { ftp_close($prod); }
    if ($stage instanceof FTP\Connection) { ftp_close($stage); }
}
