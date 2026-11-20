<?php
/**
 * -----------------------------------------------------------------------------
 * @file        scripts/home-pendencias-stage-deploy.php
 * @project     Estrategia Nerd
 * @purpose     Deploy das pendencias pos-release da home nova (FEAT-015 / FEAT-016) na STAGE:
 *              - Item 2: cabecalho responsivo em 768 px e faixa 768-1023 px (public/assets/css/site.css)
 *              - Item 3: Echo sem sobrepor textos/controles e visivel no celular apos o Hero (#1666)
 *                        (public/assets/css/site-home-flow.css e public/assets/js/site-home-flow.js)
 *              - Item 4: desativacao do padrao debug na stage (config/app.php: true -> false)
 * @usage       C:\xampp\php\php.exe scripts/home-pendencias-stage-deploy.php <acao>
 *              acoes: --check | --backup | --deploy | --verify | --rollback
 * @notes       So stage (producao não é alterada neste bloco).
 *              - --check: somente leitura. Confere que os arquivos remotos estao iguais ao git HEAD
 *                (ou, no caso de config/app.php, que difere do git HEAD exclusivamente pelo padrao true).
 *              - --backup: baixa copia integral antes da mudanca em storage/home-pendencias-deploy/stage/before
 *                e gera o pacote em storage/home-pendencias-deploy/stage/package.
 *              - --deploy: envia via arquivo .tmp + rename atomico e confere SHA-256 remoto.
 *              - --verify: confere hashes remotos, padrao false do debug, HTTP 200 e X-Robots-Tag em / e /blog.
 *              - --rollback: restaura as versoes em before/ somente se o remoto nao tiver sofrido alteracao
 *                externa apos o deploy.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/backup/EnvLoader.php';
$root = dirname(__DIR__);
Scripts\Backup\EnvLoader::load($root . '/.env');
$config = require $root . '/config/content-sync.php';

const TARGET = 'stage';
const SITE_URL = 'https://estrategianerd.com.br/stage';

// Arquivos alterados: [caminho local relativo, base remota ('core' ou 'public'), caminho remoto relativo].
const PATCHED = [
    ['config/app.php', 'core', 'config/app.php'],
    ['app/Views/layouts/site.php', 'core', 'app/Views/layouts/site.php'],
    ['public/assets/css/site.css', 'public', 'assets/css/site.css'],
    ['public/assets/css/site-home-flow.css', 'public', 'assets/css/site-home-flow.css'],
    ['public/assets/js/site-home-flow.js', 'public', 'assets/js/site-home-flow.js'],
];

// Arquivos novos do fundo ambiente compartilhado.
const NEW_FILES = [
    ['app/Views/components/site/shared/ambient-background.php', 'core', 'app/Views/components/site/shared/ambient-background.php'],
    ['public/assets/js/site-ambient-background.js', 'public', 'assets/js/site-ambient-background.js'],
];

// Dependencias conferidas em modo somente leitura para garantir integridade do ambiente.
const DEPENDENCIES = [
    ['app/Views/site/home-circuito-preview.php', 'core', 'app/Views/site/home-circuito-preview.php'],
    ['app/Views/components/site/shared/nav.php', 'core', 'app/Views/components/site/shared/nav.php'],
    ['app/Views/components/site/home-flow/hero.php', 'core', 'app/Views/components/site/home-flow/hero.php'],
    ['app/Views/components/site/home-flow/about.php', 'core', 'app/Views/components/site/home-flow/about.php'],
];

$action = (string) ($argv[1] ?? '');
if (!in_array($action, ['--check', '--backup', '--deploy', '--verify', '--rollback'], true)) {
    fwrite(STDERR, "Use --check, --backup, --deploy, --verify ou --rollback.\n");
    exit(2);
}

$profile = $config['profiles'][TARGET];
$code = (array) $profile['code_deploy'];
$dir = $root . '/storage/home-pendencias-deploy/' . TARGET;
$manifestFile = $dir . '/manifest.json';

/** Bytes exatos da versao commitada (git HEAD); null se o arquivo nao esta no HEAD. */
function gitHead(string $root, string $rel): ?string
{
    $proc = proc_open(['git', '-C', $root, 'show', 'HEAD:' . $rel], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        return null;
    }
    $bytes = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return proc_close($proc) === 0 ? $bytes : null;
}

/** Mesmo conteudo ignorando diferenca de fim de linha (CRLF vs LF). */
function sameContent(string $a, string $b): bool
{
    return str_replace("\r\n", "\n", $a) === str_replace("\r\n", "\n", $b);
}

/** Layout esperado na stage: igual ao HEAD, exceto pelo Tailwind via CDN ja documentado. */
function expectedStageLayout(string $head): string
{
    $link = "  <link rel=\"stylesheet\" href=\"<?= asset('assets/css/tailwind.min.css') ?>\">";
    $cdn = <<<'HTML'
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            orbitron: ['Orbitron', 'ui-sans-serif', 'system-ui'],
            rajdhani: ['Rajdhani', 'ui-sans-serif', 'system-ui'],
          }
        }
      }
    };
  </script>
HTML;
    if (substr_count($head, $link) !== 1) {
        throw new RuntimeException('Ancora do Tailwind nao encontrada no layout do git HEAD.');
    }
    return str_replace($link, $cdn, $head);
}

function remoteBytes(FTP\Connection $ftp, string $path): ?string
{
    if (@ftp_size($ftp, $path) < 0) {
        return null;
    }
    $stream = fopen('php://temp', 'w+b');
    if ($stream === false || !@ftp_fget($ftp, $stream, $path, FTP_BINARY)) {
        throw new RuntimeException('Falha ao ler ' . basename($path));
    }
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
        if (@ftp_chdir($ftp, $path)) {
            continue;
        }
        if (!@ftp_mkdir($ftp, $path)) {
            throw new RuntimeException('Nao foi possivel criar a pasta remota ' . $path);
        }
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
        throw new RuntimeException('Envio temporario nao confere: ' . $remote);
    }
    if (!@ftp_rename($ftp, $tmp, $remote)) {
        @ftp_delete($ftp, $tmp);
        throw new RuntimeException('Rename recusado: ' . $remote);
    }
    if (hash('sha256', (string) remoteBytes($ftp, $remote)) !== hash('sha256', $bytes)) {
        throw new RuntimeException('Hash remoto nao confere apos a troca: ' . $remote);
    }
}

/**
 * Aplica a mudanca exclusiva deste bloco sobre a versao da stage.
 * Confirma tambem que o estado inicial e o resultado batem com o esperado.
 */
function patchFor(string $root, string $local, string $remoteBytes): string
{
    $head = gitHead($root, $local);
    if ($head === null) {
        throw new RuntimeException('Arquivo nao encontrado no git HEAD: ' . $local);
    }

    if ($local === 'config/app.php') {
        $needle = "'debug' => filter_var(\$_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOL),";
        $replace = "'debug' => filter_var(\$_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),";
        if (substr_count($remoteBytes, $needle) !== 1) {
            throw new RuntimeException('Ancora de debug nao encontrada (ou repetida) em config/app.php da stage.');
        }
        $after = str_replace($needle, $replace, $remoteBytes);
        if (!sameContent($after, $head)) {
            throw new RuntimeException('config/app.php da stage possui diferenca adicional alem de APP_DEBUG ?? true.');
        }
        return $after;
    }

    if ($local === 'app/Views/layouts/site.php') {
        if (!sameContent($remoteBytes, $head) && !sameContent($remoteBytes, expectedStageLayout($head))) {
            throw new RuntimeException('Layout da stage possui diferencas alem do Tailwind CDN documentado.');
        }
        $eol = str_contains($remoteBytes, "\r\n") ? "\r\n" : "\n";
        $once = static function (string $bytes, string $needle, string $replace) use ($local): string {
            if (substr_count($bytes, $needle) !== 1) {
                throw new RuntimeException('Ancora ausente ou repetida em ' . $local . '.');
            }
            return str_replace($needle, $replace, $bytes);
        };
        $after = $once(
            $remoteBytes,
            'use App\\Support\\Csrf;' . $eol,
            'use App\\Support\\Csrf;' . $eol . 'use App\\Support\\View;' . $eol
        );
        $after = $once(
            $after,
            "\$siteJsCandidates = [{$eol}    dirname(__DIR__, 3) . '/public/assets/js/site-home.js',{$eol}    dirname(__DIR__, 4) . '/assets/js/site-home.js',{$eol}];{$eol}",
            "\$siteJsCandidates = [{$eol}    dirname(__DIR__, 3) . '/public/assets/js/site-home.js',{$eol}    dirname(__DIR__, 4) . '/assets/js/site-home.js',{$eol}];{$eol}"
            . "\$siteAmbientJsCandidates = [{$eol}    dirname(__DIR__, 3) . '/public/assets/js/site-ambient-background.js',{$eol}    dirname(__DIR__, 4) . '/assets/js/site-ambient-background.js',{$eol}];{$eol}"
        );
        $loop = "\$siteHomeJsPath = '';{$eol}foreach (\$siteJsCandidates as \$candidate) {{$eol}    if (is_file(\$candidate)) {{$eol}        \$siteHomeJsPath = \$candidate;{$eol}        break;{$eol}    }{$eol}}{$eol}";
        $after = $once(
            $after,
            $loop,
            $loop . "\$siteAmbientJsPath = '';{$eol}foreach (\$siteAmbientJsCandidates as \$candidate) {{$eol}    if (is_file(\$candidate)) {{$eol}        \$siteAmbientJsPath = \$candidate;{$eol}        break;{$eol}    }{$eol}}{$eol}"
        );
        $after = $once(
            $after,
            '$siteHomeJsVersion = $assetVersion($siteHomeJsPath);',
            '$siteHomeJsVersion = $assetVersion($siteHomeJsPath);' . $eol . '$siteAmbientJsVersion = $assetVersion($siteAmbientJsPath);'
        );
        $body = '<body class="bg-slate-950 text-slate-100 <?= htmlspecialchars($bodyClass, ENT_QUOTES | ENT_SUBSTITUTE, \'UTF-8\') ?>">';
        $after = $once(
            $after,
            $body . $eol,
            $body . $eol . "  <?php if (!\$isLogin && !\$homePage): ?>{$eol}    <?= View::component('site/shared/ambient-background') ?>{$eol}  <?php endif; ?>{$eol}"
        );
        $homeScript = "  <?php if (!\$isLogin): ?>{$eol}    <script src=\"<?= url('/assets/js/site-home.js?v=' . \$siteHomeJsVersion) ?>\" defer></script>{$eol}  <?php endif; ?>{$eol}";
        $after = $once(
            $after,
            $homeScript,
            $homeScript . "  <?php if (!\$isLogin && !\$homePage): ?>{$eol}    <script src=\"<?= url('/assets/js/site-ambient-background.js?v=' . \$siteAmbientJsVersion) ?>\" defer></script>{$eol}  <?php endif; ?>{$eol}"
        );
        return $after;
    }

    // Para os assets existentes, o remoto precisa partir exatamente do git HEAD.
    if (!sameContent($remoteBytes, $head)) {
        throw new RuntimeException('Stage difere do git HEAD em ' . $local . '; pare e verifique.');
    }

    $localBytes = (string) file_get_contents($root . '/' . $local);
    if (sameContent($localBytes, $head)) {
        throw new RuntimeException('Arquivo local ainda nao contem a alteracao esperada: ' . $local);
    }

    // Preserva o padrao de quebra de linha do arquivo local/remoto.
    return $localBytes;
}

/** Consulta HTTP com captura de status, cabecalhos e corpo. */
function fetchUrl(string $url): array
{
    $headers = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'EstrategiaNerd-HomePendenciasStageDeploy/1.0',
        CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers): int {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))][] = trim($parts[1]);
            }
            return strlen($line);
        },
    ]);
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'headers' => $headers, 'body' => $body];
}

$ftp = null;
try {
    foreach (['host', 'port', 'username', 'password', 'root'] as $required) {
        if (trim((string) ($code[$required] ?? '')) === '') {
            throw new RuntimeException('Configuracao de FTP incompleta (' . $required . ').');
        }
    }
    $ftp = @ftp_connect((string) $code['host'], (int) $code['port'], 30);
    if ($ftp === false || !@ftp_login($ftp, (string) $code['username'], (string) $code['password'])) {
        throw new RuntimeException('FTP indisponivel.');
    }
    @ftp_pasv($ftp, (bool) ($code['passive'] ?? true));

    $publicRoot = dirname('/' . trim((string) $profile['uploads']['root'], '/'));
    if (!str_ends_with($publicRoot, '/public_html/stage')) {
        throw new RuntimeException('Raiz publica remota nao e a da stage.');
    }
    $coreRoot = '/' . trim((string) $code['root'], '/');
    if (!str_ends_with($coreRoot, '/_app_core') && @ftp_size($ftp, $coreRoot . '/_app_core/bootstrap.php') >= 0) {
        $coreRoot .= '/_app_core';
    }
    if (!in_array($coreRoot, [$publicRoot, $publicRoot . '/_app_core'], true) || @ftp_size($ftp, $coreRoot . '/bootstrap.php') < 0) {
        throw new RuntimeException('Core remoto nao confere com a stage.');
    }
    $remotePath = static fn (string $base, string $rel): string => ($base === 'core' ? $coreRoot : $publicRoot) . '/' . $rel;

    if ($action === '--check') {
        $problems = [];
        foreach (DEPENDENCIES as [$local, $base, $rel]) {
            $remote = remoteBytes($ftp, $remotePath($base, $rel));
            $head = gitHead($root, $local);
            if ($remote === null) {
                $problems[] = "Dependencia ausente na stage: {$rel}";
                echo "AUSENTE  {$rel}\n";
                continue;
            }
            $same = $head !== null && sameContent($remote, $head);
            echo ($same ? 'DEP OK   ' : 'DEP DIF  ') . "{$rel}\n";
            if (!$same) {
                $problems[] = "Dependencia na stage difere do git HEAD: {$rel}";
            }
        }

        foreach (PATCHED as [$local, $base, $rel]) {
            $remote = remoteBytes($ftp, $remotePath($base, $rel));
            if ($remote === null) {
                $problems[] = "Arquivo ausente na stage: {$rel}";
                echo "AUSENTE  {$rel}\n";
                continue;
            }
            try {
                $after = patchFor($root, $local, $remote);
                echo 'PATCH OK ' . $rel . ' (before=' . substr(hash('sha256', $remote), 0, 12) . ' -> after=' . substr(hash('sha256', $after), 0, 12) . ")\n";
            } catch (Throwable $e) {
                $problems[] = $e->getMessage();
                echo "FALHA    {$rel}: {$e->getMessage()}\n";
            }
        }

        foreach (NEW_FILES as [$local, $base, $rel]) {
            $exists = remoteBytes($ftp, $remotePath($base, $rel)) !== null;
            echo ($exists ? 'JA EXISTE ' : 'NOVO      ') . "{$rel}\n";
            if ($exists) {
                $problems[] = 'Arquivo novo ja existe na stage: ' . $rel;
            }
        }

        if ($problems !== []) {
            fwrite(STDERR, "ATENCAO:\n- " . implode("\n- ", $problems) . "\n");
            exit(1);
        }
        echo "PASS: stage conferida e pronta para --backup; nada foi enviado.\n";
        exit(0);
    }

    if ($action === '--backup') {
        if (is_file($manifestFile)) {
            throw new RuntimeException('Ja existe um backup/pacote em ' . $dir . '; confira antes de refazer.');
        }
        if (!is_dir($dir . '/before')) {
            mkdir($dir . '/before', 0700, true);
        }
        if (!is_dir($dir . '/package')) {
            mkdir($dir . '/package', 0700, true);
        }
        $manifest = ['created_at' => date('c'), 'target' => TARGET, 'files' => []];
        foreach (PATCHED as [$local, $base, $rel]) {
            $remote = remoteBytes($ftp, $remotePath($base, $rel));
            if ($remote === null) {
                throw new RuntimeException('Remoto ausente: ' . $rel);
            }
            $after = patchFor($root, $local, $remote);
            $safe = str_replace('/', '__', $rel);
            file_put_contents($dir . '/before/' . $safe, $remote, LOCK_EX);
            file_put_contents($dir . '/package/' . $safe, $after, LOCK_EX);
            $manifest['files'][] = [
                'local' => $local,
                'rel' => $rel,
                'base' => $base,
                'kind' => 'patched',
                'before' => hash('sha256', $remote),
                'after' => hash('sha256', $after),
                'store' => $safe,
            ];
            echo "GUARDADO {$rel}\n";
        }

        foreach (NEW_FILES as [$local, $base, $rel]) {
            if (remoteBytes($ftp, $remotePath($base, $rel)) !== null) {
                throw new RuntimeException('Arquivo novo ja existe na stage: ' . $rel);
            }
            $bytes = (string) file_get_contents($root . '/' . $local);
            $safe = str_replace('/', '__', $rel);
            file_put_contents($dir . '/package/' . $safe, $bytes, LOCK_EX);
            $manifest['files'][] = [
                'local' => $local,
                'rel' => $rel,
                'base' => $base,
                'kind' => 'new',
                'before' => null,
                'after' => hash('sha256', $bytes),
                'store' => $safe,
            ];
            echo "EMPACOTADO {$rel}\n";
        }
        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        echo 'PASS: copias em ' . $dir . '/before e pacote em ' . $dir . '/package (' . count($manifest['files']) . " arquivos).\n";
        exit(0);
    }

    $manifest = is_file($manifestFile) ? json_decode((string) file_get_contents($manifestFile), true) : null;
    if (!is_array($manifest) || empty($manifest['files'])) {
        throw new RuntimeException('Manifesto ausente ou invalido; rode --backup antes.');
    }
    $manifestFiles = (array) $manifest['files'];
    $files = array_merge(
        array_values(array_filter($manifestFiles, static fn (array $f): bool => ($f['kind'] ?? '') === 'new')),
        array_values(array_filter($manifestFiles, static fn (array $f): bool => ($f['kind'] ?? '') !== 'new'))
    );

    if ($action === '--deploy') {
        foreach ($files as $f) {
            $path = $remotePath($f['base'], $f['rel']);
            $current = remoteBytes($ftp, $path);
            $currentSha = $current === null ? null : hash('sha256', $current);
            if ($currentSha === $f['after']) {
                echo "JA OK   {$f['rel']}\n";
                continue;
            }
            if ($currentSha !== $f['before']) {
                throw new RuntimeException('Remoto mudou desde o backup: ' . $f['rel'] . '; deploy abortado.');
            }
            $bytes = (string) file_get_contents($dir . '/package/' . $f['store']);
            if (hash('sha256', $bytes) !== $f['after']) {
                throw new RuntimeException('Pacote local corrompido: ' . $f['rel']);
            }
            putAndVerify($ftp, $path, $bytes);
            echo "ENVIADO {$f['rel']}\n";
        }
        echo "PASS: pacote aplicado na stage e conferido por SHA-256.\n";
        exit(0);
    }

    if ($action === '--rollback') {
        // Verifica antes de escrever se algum arquivo sofreu alteracao externa apos o deploy.
        foreach (array_reverse($files) as $f) {
            $path = $remotePath($f['base'], $f['rel']);
            $current = remoteBytes($ftp, $path);
            $currentSha = $current === null ? null : hash('sha256', $current);
            if ($currentSha !== $f['after'] && $currentSha !== $f['before']) {
                throw new RuntimeException(
                    'Remoto alterado externamente apos o backup/deploy em ' . $f['rel']
                    . '; rollback abortado para nao sobrescrever alteracoes externas.'
                );
            }
        }
        foreach (array_reverse($files) as $f) {
            $path = $remotePath($f['base'], $f['rel']);
            $current = remoteBytes($ftp, $path);
            $currentSha = $current === null ? null : hash('sha256', $current);
            if (($f['kind'] ?? '') === 'new') {
                if ($currentSha === null) {
                    echo "JA REMOVIDO {$f['rel']}\n";
                    continue;
                }
                if ($currentSha !== $f['after'] || !@ftp_delete($ftp, $path)) {
                    throw new RuntimeException('Nao foi possivel remover com seguranca: ' . $f['rel']);
                }
                echo "REMOVIDO {$f['rel']}\n";
                continue;
            }
            if ($currentSha === $f['before']) {
                echo "JA RESTAURADO {$f['rel']}\n";
                continue;
            }
            $bytes = (string) file_get_contents($dir . '/before/' . $f['store']);
            if (hash('sha256', $bytes) !== $f['before']) {
                throw new RuntimeException('Copia anterior invalida em before/: ' . $f['rel']);
            }
            putAndVerify($ftp, $path, $bytes);
            echo "RESTAURADO {$f['rel']}\n";
        }
        echo "PASS: stage restaurada para o estado anterior ao deploy.\n";
        exit(0);
    }

    // --verify: confere hashes remotos, padrao false do debug em config/app.php e respostas HTTP da stage.
    $failures = [];
    foreach ($files as $f) {
        $current = remoteBytes($ftp, $remotePath($f['base'], $f['rel']));
        if ($current === null || hash('sha256', $current) !== $f['after']) {
            $failures[] = 'hash != pacote: ' . $f['rel'];
        } else {
            echo "HASH OK  {$f['rel']} (" . substr($f['after'], 0, 12) . ")\n";
        }
        if ($f['rel'] === 'config/app.php' && $current !== null) {
            if (!str_contains($current, "\$_ENV['APP_DEBUG'] ?? false") || str_contains($current, "\$_ENV['APP_DEBUG'] ?? true")) {
                $failures[] = 'config/app.php na stage nao esta com padrao false';
            }
        }
    }

    $homeRes = fetchUrl(SITE_URL . '/?pendenciascheck=' . time());
    if ($homeRes['status'] !== 200) {
        $failures[] = 'home respondeu HTTP ' . $homeRes['status'];
    }
    $robotsHeader = implode(', ', $homeRes['headers']['x-robots-tag'] ?? []);
    if (!str_contains(strtolower($robotsHeader), 'noindex')) {
        $failures[] = 'home sem X-Robots-Tag noindex';
    }
    foreach (['data-flow-path', 'data-flow-echo', 'window.EN_ECHO_OUTFIT', 'site-home-flow.js', 'id="instagram-feed"'] as $marker) {
        if (!str_contains($homeRes['body'], $marker)) {
            $failures[] = 'home sem ' . $marker;
        }
    }

    $blogRes = fetchUrl(SITE_URL . '/blog?pendenciascheck=' . time());
    if ($blogRes['status'] !== 200) {
        $failures[] = '/blog respondeu HTTP ' . $blogRes['status'];
    }
    $blogRobots = implode(', ', $blogRes['headers']['x-robots-tag'] ?? []);
    if (!str_contains(strtolower($blogRobots), 'noindex')) {
        $failures[] = '/blog sem X-Robots-Tag noindex';
    }

    foreach (['Fatal error', 'Uncaught ', 'Stack trace:', 'Warning:', 'Notice:'] as $errMarker) {
        if (str_contains($homeRes['body'], $errMarker) || str_contains($blogRes['body'], $errMarker)) {
            $failures[] = 'vazamento de erro/debug detectado: ' . $errMarker;
        }
    }

    if ($failures !== []) {
        fwrite(STDERR, 'FAIL: ' . implode(' | ', $failures) . PHP_EOL);
        exit(1);
    }

    echo "PASS: hashes da stage conferem, debug padrao false ativo, home (200) e /blog (200) validados com noindex.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERRO: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($ftp !== null && $ftp !== false) {
        @ftp_close($ftp);
    }
}
