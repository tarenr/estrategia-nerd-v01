<?php
/**
 * -----------------------------------------------------------------------------
 * @file        scripts/home-rio-stage-deploy.php
 * @project     Estrategia Nerd
 * @purpose     Deploy da home nova (FEAT-015 rio de luz + FEAT-016 fantasia do Echo) na STAGE,
 *              com conferencia do estado real, backup, envio conferido por hash e rollback.
 * @usage       C:\xampp\php\php.exe scripts/home-rio-stage-deploy.php <acao>
 *              acoes: --check | --backup | --deploy | --verify | --rollback
 * @notes       So stage (producao recebe o pacote tirado da stage, em outro plano).
 *              - --check: so leitura. Compara o remoto com a ultima versao commitada (git HEAD)
 *                dos arquivos alterados e das dependencias, e confirma que os novos nao existem.
 *              - Alterados (HomeController, HomeService, site.css) vao como "versao da stage +
 *                so a mudanca desta entrega", montados no --backup a partir do remoto.
 *              - Nada de .env, banco ou outros arquivos. Credenciais nunca sao exibidas.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }

require __DIR__ . '/backup/EnvLoader.php';
$root = dirname(__DIR__);
Scripts\Backup\EnvLoader::load($root . '/.env');
$config = require $root . '/config/content-sync.php';

const TARGET = 'stage';
const SITE_URL = 'https://estrategianerd.com.br/stage';

// Arquivos novos: [local relativo, base remota ('core' ou 'public'), caminho remoto relativo].
const NEW_FILES = [
    ['app/Views/site/home-circuito-preview.php', 'core', 'app/Views/site/home-circuito-preview.php'],
    ['app/Views/components/site/home-flow/hero.php', 'core', 'app/Views/components/site/home-flow/hero.php'],
    ['app/Views/components/site/home-flow/about.php', 'core', 'app/Views/components/site/home-flow/about.php'],
    ['app/Support/EchoFantasias.php', 'core', 'app/Support/EchoFantasias.php'],
    ['config/echo-fantasias.php', 'core', 'config/echo-fantasias.php'],
    ['public/assets/css/site-home-flow.css', 'public', 'assets/css/site-home-flow.css'],
    ['public/assets/js/site-home-flow.js', 'public', 'assets/js/site-home-flow.js'],
    ['public/assets/vendor/echo-robo/robo-motor.js', 'public', 'assets/vendor/echo-robo/robo-motor.js'],
    ['public/assets/vendor/echo-robo/robo-roupas.js', 'public', 'assets/vendor/echo-robo/robo-roupas.js'],
    ['public/assets/vendor/echo-robo/robo-roupas-echo.js', 'public', 'assets/vendor/echo-robo/robo-roupas-echo.js'],
];
// Alterados: vao como versao do remoto + patch (o remoto precisa ser igual ao git HEAD).
const PATCHED = [
    ['app/Services/Site/HomeService.php', 'core', 'app/Services/Site/HomeService.php'],
    ['public/assets/css/site.css', 'public', 'assets/css/site.css'],
    // Por ultimo: a virada da home.
    ['app/Controllers/Site/HomeController.php', 'core', 'app/Controllers/Site/HomeController.php'],
];
// Substituidos inteiros pela versao local (aprovado pelo dono): card de destaque do Blog igual ao local.
const REPLACED = [
    ['public/assets/css/site-hero-preview.css', 'public', 'assets/css/site-hero-preview.css'],
];
// Diferencas da stage em relacao ao git HEAD ja analisadas (25/10/2026) e que nao afetam a home nova.
const KNOWN_STAGE_DIFFS = [
    'app/Support/Helpers.php' => 'stage sem a funcao asset(); a home nova nao a usa',
    'bootstrap.php' => 'stage sem config/instagram.php e pasta de sessao local; HomeService ja roda assim na stage',
];
// Dependencias que precisam estar na stage iguais ao git HEAD (so conferidas, nao enviadas).
const DEPENDENCIES = [
    ['app/Views/components/site/shared/nav.php', 'core', 'app/Views/components/site/shared/nav.php'],
    ['app/Views/components/site/home/posts-preview.php', 'core', 'app/Views/components/site/home/posts-preview.php'],
    ['app/Views/components/site/home/instagram-feed.php', 'core', 'app/Views/components/site/home/instagram-feed.php'],
    ['app/Views/components/site/home/newsletter.php', 'core', 'app/Views/components/site/home/newsletter.php'],
    ['app/Views/components/site/home/footer.php', 'core', 'app/Views/components/site/home/footer.php'],
    ['app/Views/site/home.php', 'core', 'app/Views/site/home.php'],
    ['app/Support/Helpers.php', 'core', 'app/Support/Helpers.php'],
    ['bootstrap.php', 'core', 'bootstrap.php'],
    ['public/assets/js/site-hero-preview.js', 'public', 'assets/js/site-hero-preview.js'],
];

$action = (string) ($argv[1] ?? '');
if (!in_array($action, ['--check', '--backup', '--deploy', '--verify', '--rollback', '--refresh'], true)) {
    fwrite(STDERR, "Use --check, --backup, --deploy, --verify, --rollback ou --refresh <arquivo novo do pacote>.\n");
    exit(2);
}

$profile = $config['profiles'][TARGET];
$code = (array) $profile['code_deploy'];
$dir = $root . '/storage/home-rio-deploy/' . TARGET;
$manifestFile = $dir . '/manifest.json';

/** Bytes exatos da versao commitada (git HEAD); null se o arquivo nao esta no HEAD. */
function gitHead(string $root, string $rel): ?string
{
    $proc = proc_open(['git', '-C', $root, 'show', 'HEAD:' . $rel], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) { return null; }
    $bytes = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return proc_close($proc) === 0 ? $bytes : null;
}

/** Mesmo conteudo ignorando o fim de linha (a stage guarda CRLF; o git, LF). */
function sameContent(string $a, string $b): bool
{
    return str_replace("\r\n", "\n", $a) === str_replace("\r\n", "\n", $b);
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
    $parts = explode('/', trim($dir, '/'));
    $path = '';
    foreach ($parts as $part) {
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

/** Aplica o patch desta entrega sobre a versao remota; cada ancora precisa existir uma vez. */
function patchFor(string $rel, string $bytes): string
{
    $once = static function (string $haystack, string $needle, string $replace, string $what): string {
        if (substr_count($haystack, $needle) !== 1) { throw new RuntimeException("Ancora do patch nao encontrada (ou repetida) em {$what}."); }
        return str_replace($needle, $replace, $haystack);
    };
    $eol = str_contains($bytes, "\r\n") ? "\r\n" : "\n";
    if ($rel === 'app/Controllers/Site/HomeController.php') {
        return $once($bytes, "        View::render('site/home', \$this->service()->getViewModel());",
            "        // Home com o rio de luz (FEAT-015), com o SEO oficial da home. A home{$eol}"
            . "        // anterior continua em site/home: para voltar, troque a view aqui.{$eol}"
            . "        View::render('site/home-circuito-preview', \$this->service()->getViewModel());", $rel);
    }
    if ($rel === 'app/Services/Site/HomeService.php') {
        return $once($bytes, "            'site_meta' => \$siteMeta,",
            "            'site_meta' => \$siteMeta,{$eol}"
            . "            // Fantasia do Echo escolhida no admin para este ambiente (FEAT-016).{$eol}"
            . "            'echo_outfit' => \\App\\Support\\EchoFantasias::piecesForSite(),", $rel);
    }
    if ($rel === 'public/assets/css/site.css') {
        // Botao de menu: oculto por padrao, visivel so com o menu recolhido (ate 767 px).
        // Ha mais de uma regra .site-mobile-toggle; o patch vai na unica que define o display.
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
        $newBlock = $once($block, '  display: inline-flex;', '  display: none;', 'site.css (.site-mobile-toggle)');
        $media = $eol . $eol . '@media (max-width: 767px) {' . $eol . '  .site-mobile-toggle {' . $eol . '    display: inline-flex;' . $eol . '  }' . $eol . '}';
        return substr($bytes, 0, $start) . $newBlock . $media . substr($bytes, $end + 1);
    }
    throw new RuntimeException('Sem patch para ' . $rel);
}

$ftp = null;
try {
    foreach (['host', 'port', 'username', 'password', 'root'] as $required) {
        if (trim((string) ($code[$required] ?? '')) === '') { throw new RuntimeException('Configuracao de FTP incompleta (' . $required . ').'); }
    }
    $ftp = @ftp_connect((string) $code['host'], (int) $code['port'], 30);
    if ($ftp === false || !@ftp_login($ftp, (string) $code['username'], (string) $code['password'])) { throw new RuntimeException('FTP indisponivel.'); }
    @ftp_pasv($ftp, (bool) ($code['passive'] ?? true));

    // Raizes: publica = pai de uploads; nucleo = raiz de codigo (+ /_app_core quando houver).
    $publicRoot = dirname('/' . trim((string) $profile['uploads']['root'], '/'));
    if (!str_ends_with($publicRoot, '/public_html/stage')) { throw new RuntimeException('Raiz publica remota nao e a da stage.'); }
    $coreRoot = '/' . trim((string) $code['root'], '/');
    if (!str_ends_with($coreRoot, '/_app_core') && @ftp_size($ftp, $coreRoot . '/_app_core/bootstrap.php') >= 0) { $coreRoot .= '/_app_core'; }
    if (!in_array($coreRoot, [$publicRoot, $publicRoot . '/_app_core'], true) || @ftp_size($ftp, $coreRoot . '/bootstrap.php') < 0) {
        throw new RuntimeException('Core remoto nao confere com a stage.');
    }
    $remotePath = static fn (string $base, string $rel): string => ($base === 'core' ? $coreRoot : $publicRoot) . '/' . $rel;

    if ($action === '--check') {
        $problems = [];
        foreach (array_merge(PATCHED, DEPENDENCIES) as [$local, $base, $rel]) {
            $remote = remoteBytes($ftp, $remotePath($base, $rel));
            $head = gitHead($root, $local);
            if ($remote === null) { $problems[] = "AUSENTE na stage: {$rel}"; echo "AUSENTE  {$rel}\n"; continue; }
            $same = $head !== null && sameContent($remote, $head);
            $known = KNOWN_STAGE_DIFFS[$rel] ?? null;
            echo ($same ? 'IGUAL   ' : ($known !== null ? 'CONHECIDA ' : 'DIFERE  ')) . "{$rel}" . ($known !== null && !$same ? " ({$known})" : '') . "\n";
            if (!$same && $known === null) { $problems[] = "stage difere do git HEAD: {$rel}"; }
        }
        foreach (PATCHED as [$local, $base, $rel]) {
            $remote = remoteBytes($ftp, $remotePath($base, $rel));
            if ($remote !== null) { patchFor($local, $remote); echo "PATCH OK {$rel}\n"; }
        }
        foreach (REPLACED as [$local, $base, $rel]) {
            $remote = remoteBytes($ftp, $remotePath($base, $rel));
            echo ($remote === null ? 'AUSENTE  ' : 'SUBSTITUI ') . "{$rel}\n";
        }
        foreach (NEW_FILES as [$local, $base, $rel]) {
            $exists = @ftp_size($ftp, $remotePath($base, $rel)) >= 0;
            echo ($exists ? 'JA EXISTE ' : 'NOVO      ') . "{$rel}\n";
            if ($exists) { $problems[] = "arquivo novo ja existe na stage: {$rel}"; }
        }
        if ($problems !== []) { fwrite(STDERR, "ATENCAO:\n- " . implode("\n- ", $problems) . "\n"); exit(1); }
        echo "PASS: stage no estado esperado; nada foi enviado.\n";
        exit(0);
    }

    if ($action === '--backup') {
        if (is_file($manifestFile)) { throw new RuntimeException('Ja existe um backup/pacote em ' . $dir . '; confira antes de refazer.'); }
        if (!is_dir($dir . '/before')) { mkdir($dir . '/before', 0700, true); }
        if (!is_dir($dir . '/package')) { mkdir($dir . '/package', 0700, true); }
        $manifest = ['created_at' => date('c'), 'files' => []];
        foreach (PATCHED as [$local, $base, $rel]) {
            $remote = remoteBytes($ftp, $remotePath($base, $rel));
            if ($remote === null) { throw new RuntimeException('Remoto ausente: ' . $rel); }
            $head = gitHead($root, $local);
            if ($head === null || !sameContent($remote, $head)) { throw new RuntimeException('Stage difere do git HEAD em ' . $rel . '; rode --check.'); }
            $after = patchFor($local, $remote);
            $safe = str_replace('/', '__', $rel);
            file_put_contents($dir . '/before/' . $safe, $remote, LOCK_EX);
            file_put_contents($dir . '/package/' . $safe, $after, LOCK_EX);
            $manifest['files'][] = ['rel' => $rel, 'base' => $base, 'kind' => 'patched', 'before' => hash('sha256', $remote), 'after' => hash('sha256', $after), 'store' => $safe];
        }
        foreach (REPLACED as [$local, $base, $rel]) {
            $remote = remoteBytes($ftp, $remotePath($base, $rel));
            if ($remote === null) { throw new RuntimeException('Remoto ausente: ' . $rel); }
            $after = (string) file_get_contents($root . '/' . $local);
            $safe = str_replace('/', '__', $rel);
            file_put_contents($dir . '/before/' . $safe, $remote, LOCK_EX);
            file_put_contents($dir . '/package/' . $safe, $after, LOCK_EX);
            $manifest['files'][] = ['rel' => $rel, 'base' => $base, 'kind' => 'replaced', 'before' => hash('sha256', $remote), 'after' => hash('sha256', $after), 'store' => $safe];
        }
        foreach (NEW_FILES as [$local, $base, $rel]) {
            if (@ftp_size($ftp, $remotePath($base, $rel)) >= 0) { throw new RuntimeException('Arquivo novo ja existe na stage: ' . $rel); }
            $bytes = (string) file_get_contents($root . '/' . $local);
            $safe = str_replace('/', '__', $rel);
            file_put_contents($dir . '/package/' . $safe, $bytes, LOCK_EX);
            $manifest['files'][] = ['rel' => $rel, 'base' => $base, 'kind' => 'new', 'before' => null, 'after' => hash('sha256', $bytes), 'store' => $safe];
        }
        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        echo 'PASS: copias e pacote em ' . $dir . ' (' . count($manifest['files']) . " arquivos).\n";
        exit(0);
    }

    $manifest = is_file($manifestFile) ? json_decode((string) file_get_contents($manifestFile), true) : null;
    if (!is_array($manifest)) { throw new RuntimeException('Rode --backup antes.'); }
    // Ordem: novos primeiro (assets e PHP que ainda nao sao usados), depois os alterados, a virada por ultimo.
    $files = array_merge(
        array_values(array_filter($manifest['files'], static fn (array $f): bool => $f['kind'] === 'new')),
        array_values(array_filter($manifest['files'], static fn (array $f): bool => $f['kind'] === 'replaced')),
        array_values(array_filter($manifest['files'], static fn (array $f): bool => $f['kind'] === 'patched'))
    );

    if ($action === '--refresh') {
        // Reenvia UM arquivo novo do pacote com a versao local atual (ex.: correcao do ?v= dos assets),
        // so se a stage estiver exatamente com a versao enviada antes; guarda essa versao para o rollback.
        $rel = (string) ($argv[2] ?? '');
        $index = null;
        foreach ($manifest['files'] as $i => $f) { if ($f['rel'] === $rel && $f['kind'] === 'new') { $index = $i; } }
        if ($index === null) { throw new RuntimeException('Arquivo novo do pacote nao encontrado no manifesto: ' . $rel); }
        $f = $manifest['files'][$index];
        $localRel = null;
        foreach (NEW_FILES as [$local, $base, $r]) { if ($r === $rel) { $localRel = $local; } }
        $path = $remotePath($f['base'], $rel);
        $current = remoteBytes($ftp, $path);
        if ($current === null || hash('sha256', $current) !== $f['after']) { throw new RuntimeException('Stage nao esta com a versao enviada antes: ' . $rel); }
        $bytes = (string) file_get_contents($root . '/' . $localRel);
        file_put_contents($dir . '/package/' . $f['store'] . '.v1', $current, LOCK_EX);
        file_put_contents($dir . '/package/' . $f['store'], $bytes, LOCK_EX);
        putAndVerify($ftp, $path, $bytes);
        $manifest['files'][$index]['after'] = hash('sha256', $bytes);
        $manifest['files'][$index]['refreshed_at'] = date('c');
        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        echo "PASS: {$rel} reenviado e conferido; manifesto atualizado.\n";
        exit(0);
    }

    if ($action === '--deploy') {
        foreach ($files as $f) {
            $current = remoteBytes($ftp, $remotePath($f['base'], $f['rel']));
            $currentSha = $current === null ? null : hash('sha256', $current);
            if ($currentSha === $f['after']) { echo "JA OK  {$f['rel']}\n"; continue; }
            if ($currentSha !== $f['before']) { throw new RuntimeException('Remoto mudou desde o backup: ' . $f['rel'] . '; abortado.'); }
            $bytes = (string) file_get_contents($dir . '/package/' . $f['store']);
            if (hash('sha256', $bytes) !== $f['after']) { throw new RuntimeException('Pacote local alterado: ' . $f['rel']); }
            putAndVerify($ftp, $remotePath($f['base'], $f['rel']), $bytes);
            echo "ENVIADO {$f['rel']}\n";
        }
        echo "PASS: pacote aplicado na stage e conferido por hash.\n";
        exit(0);
    }

    if ($action === '--rollback') {
        foreach (array_reverse($files) as $f) {
            $path = $remotePath($f['base'], $f['rel']);
            if ($f['kind'] === 'patched' || $f['kind'] === 'replaced') {
                $bytes = (string) file_get_contents($dir . '/before/' . $f['store']);
                if (hash('sha256', $bytes) !== $f['before']) { throw new RuntimeException('Copia anterior invalida: ' . $f['rel']); }
                putAndVerify($ftp, $path, $bytes);
                echo "RESTAURADO {$f['rel']}\n";
            } elseif (@ftp_size($ftp, $path) >= 0) {
                if (!@ftp_delete($ftp, $path)) { throw new RuntimeException('Nao foi possivel remover ' . $f['rel']); }
                echo "REMOVIDO {$f['rel']}\n";
            }
        }
        echo "PASS: stage voltou ao estado anterior.\n";
        exit(0);
    }

    // --verify: hashes + home nova renderizada.
    $failures = [];
    foreach ($files as $f) {
        $current = remoteBytes($ftp, $remotePath($f['base'], $f['rel']));
        if ($current === null || hash('sha256', $current) !== $f['after']) { $failures[] = 'hash != pacote: ' . $f['rel']; }
    }
    $ch = curl_init(SITE_URL . '/?homecheck=' . time());
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30, CURLOPT_USERAGENT => 'EstrategiaNerd-HomeRioDeploy/1.0']);
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status !== 200) { $failures[] = 'home respondeu ' . $status; }
    foreach (['data-flow-path', 'data-flow-echo', 'window.EN_ECHO_OUTFIT', 'site-home-flow.js', 'id="instagram-feed"'] as $marker) {
        if (!str_contains($body, $marker)) { $failures[] = 'home sem ' . $marker; }
    }
    if ($failures !== []) { fwrite(STDERR, 'FAIL: ' . implode(' | ', $failures) . PHP_EOL); exit(1); }
    echo "PASS: stage com o pacote (hashes conferidos); home nova 200 com rio, Echo e feed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'STOP: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($ftp instanceof FTP\Connection) { ftp_close($ftp); }
}
