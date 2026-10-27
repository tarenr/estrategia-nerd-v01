<?php
/**
 * -----------------------------------------------------------------------------
 * @file        scripts/home-bordas-production-deploy.php
 * @project     Estrategia Nerd
 * @purpose     Deploy em PRODUCAO da remocao de bordas e emendas das paginas publicas (FEAT-015,
 *              Forge #1691 e #1698), validada na stage em 26/10/2026.
 * @usage       C:\xampp\php\php.exe scripts/home-bordas-production-deploy.php <acao>
 *              acoes: --check | --backup | --sandbox-test | --deploy | --verify | --rollback --confirmar
 * @notes       - 6 views vao como o pacote TIRADO DA STAGE (iguais ao commit). O site.css vai como
 *                "versao atual da PRODUCAO + so os 3 trechos das bordas" (mesma excecao do deploy de
 *                25/10, PATCH_ON_PRODUCTION em scripts/home-rio-production-deploy.php): o site.css da
 *                stage tem FIX-009, o cabecalho 768-1023 px (#1684) e uma regra de movimento reduzido
 *                que nao fazem parte desta entrega. O texto promovido e lido do site.css da stage.
 *              - --check e somente leitura (producao e stage). Cada trecho do patch precisa aparecer
 *                exatamente uma vez; senao, aborta sem montar nada.
 *              - Toda escrita rele o hash remoto antes e aborta se divergir; envio por .tmp,
 *                conferencia, rename e nova conferencia. Deploy e rollback sao idempotentes; hash
 *                fora de {antes, depois} aborta antes de qualquer escrita (protege mudancas de
 *                terceiros). Ordem: site.css e depois as views (os estados intermediarios so tiram
 *                fundos e bordas decorativos).
 *              - O backup completo da producao (banco + arquivos) e feito antes, por
 *                scripts/backup.php run production. Nada de .env ou banco aqui.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }

require __DIR__ . '/backup/EnvLoader.php';
$root = dirname(__DIR__);
Scripts\Backup\EnvLoader::load($root . '/.env');

$action = (string) ($argv[1] ?? '');
$confirmed = in_array('--confirmar', $argv, true);
if (!in_array($action, ['--check', '--backup', '--sandbox-test', '--deploy', '--verify', '--rollback'], true)) {
    fwrite(STDERR, "Use --check, --backup, --sandbox-test, --deploy, --verify ou --rollback --confirmar.\n");
    exit(2);
}

const SITE_URL = 'https://estrategianerd.com.br';
const CSS_REL = 'assets/css/site.css';

// Ordem = ordem do deploy (site.css primeiro).
const FILES = [
    ['base' => 'public', 'rel' => 'assets/css/site.css'],
    ['base' => 'core', 'rel' => 'app/Views/site/blog.php'],
    ['base' => 'core', 'rel' => 'app/Views/site/post.php'],
    ['base' => 'core', 'rel' => 'app/Views/site/central.php'],
    ['base' => 'core', 'rel' => 'app/Views/site/page.php'],
    ['base' => 'core', 'rel' => 'app/Views/site/error-404.php'],
    ['base' => 'core', 'rel' => 'app/Views/site/post-unavailable.php'],
];
// Producao em 27/10/2026 (conferida por FTP em 26/10 no inicio e no fim do trabalho do Echo).
const PROD_BEFORE = [
    'assets/css/site.css' => '16cba1a43ee9c7c47af8636c512a0d568b7569f9f860fa7e20b9dc7ffcf91bca',
    'app/Views/site/blog.php' => 'b1ca330ee4226a36c64921efe413969a540eeba3c56680443a48b567bc901017',
    'app/Views/site/post.php' => '086ad99fb06cef2ffb5045088a155b4b122dcecfd4cd16b81f6a030548590900',
    'app/Views/site/central.php' => '03e5d948a55570627d8485d34501d13c226e540d1b761b678d323dba9b2e2189',
    'app/Views/site/page.php' => '6f77dc8652b93521be281a0f962c768405e86555c6d4641c51763539e78695ca',
    'app/Views/site/error-404.php' => '21b08f1e69de43e7b0431ff30ea36316d89c6514fe3d878886ec3d3b7be57f4c',
    'app/Views/site/post-unavailable.php' => 'd53735ab0a80b4af799cc95565597539316e348d18bc256eef560f4e005f12b6',
];
// Views da stage = commit (reconciliacao de 26/10, commits 15e965e e 07fd82c).
const VIEWS_AFTER = [
    'app/Views/site/blog.php' => 'ca1963fcf3b86cce5222a1b73736eeca8959aa8e71beb02dc92b15098f9127a7',
    'app/Views/site/post.php' => '8bebf8b194a8e86d88eab7d55f469c1f653b42508198a1b6467b1ac8969bd48a',
    'app/Views/site/central.php' => '703fcafa7265a996f247a8c8a0fc197e8d1bc0f113c407a6279125a02bf3c4ca',
    'app/Views/site/page.php' => 'e14cb157cb00ef3dd26a4983b46d5dc3a47ad77a5554663e8d6fd11d2f839389',
    'app/Views/site/error-404.php' => '5cbdda3d83610fa88aa93f32d21589320f0d3590898ca7a50f6b8c7da23a6d6c',
    'app/Views/site/post-unavailable.php' => '298587bf153fe31f85fc6e0aea5fb37f7e3d76bc585a902d2e4cdd406e4bc202',
];
// Dependencias do fundo das paginas internas: precisam continuar como estao na producao.
const PROD_DEPENDENCIES = [
    ['core', 'app/Views/layouts/site.php', '485cae00eb88961394ccbe74e62943a3c836ba03fb88217807dff4476c169fbb'],
    ['core', 'app/Views/components/site/shared/ambient-background.php', '553dafc443e0c390cbd9e70387a8ca5941a1037455532c84f38826c75830df93'],
];
// Paginas do --verify: [caminho, status, textos que NAO podem aparecer no HTML].
const PAGES = [
    ['/', 200, []],
    ['/blog', 200, ['border-y border-cyan-500/10 bg-slate-900/30', 'to-slate-900/50']],
    ['/blog/hardware', 200, ['to-slate-900/50']],
    ['/post/post-inexistente-auditoria', 404, ['to-slate-950/60']],
    ['/central-nerd', 200, ['central-hero-backdrop']],
    ['/politica-de-privacidade', 200, ['site-about-backdrop', 'site-about-grid']],
    ['/termos-de-uso', 200, ['site-about-backdrop', 'site-about-grid']],
    ['/rota-inexistente-auditoria', 404, ['to-slate-950/60']],
];

interface Store
{
    public function get(string $base, string $rel): ?string;
    public function put(string $base, string $rel, string $bytes): void;
}

final class FtpStore implements Store
{
    public function __construct(private FTP\Connection $ftp, private string $coreRoot, private string $publicRoot) {}

    private function path(string $base, string $rel): string
    {
        return ($base === 'core' ? $this->coreRoot : $this->publicRoot) . '/' . $rel;
    }

    public function get(string $base, string $rel): ?string
    {
        $path = $this->path($base, $rel);
        if (@ftp_size($this->ftp, $path) < 0) { return null; }
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false || !@ftp_fget($this->ftp, $stream, $path, FTP_BINARY)) { throw new RuntimeException('Falha ao ler ' . $rel); }
        rewind($stream);
        $bytes = (string) stream_get_contents($stream);
        fclose($stream);
        return $bytes;
    }

    public function put(string $base, string $rel, string $bytes): void
    {
        $remote = $this->path($base, $rel);
        $tmp = $remote . '.tmp';
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) { throw new RuntimeException('Sem memoria temporaria para ' . $rel); }
        fwrite($stream, $bytes);
        rewind($stream);
        $ok = @ftp_fput($this->ftp, $tmp, $stream, FTP_BINARY);
        fclose($stream);
        if (!$ok || hashOf($this->get($base, $rel . '.tmp')) !== hash('sha256', $bytes)) {
            @ftp_delete($this->ftp, $tmp);
            throw new RuntimeException('Envio nao confere: ' . $rel);
        }
        // O arquivo anterior so e substituido se o envio conferiu.
        if (!@ftp_rename($this->ftp, $tmp, $remote)) { @ftp_delete($this->ftp, $tmp); throw new RuntimeException('Rename recusado: ' . $rel); }
    }
}

/** Pasta local com core/ e public/, usada pelo --sandbox-test. failAfter >= 0 interrompe a escrita seguinte. */
final class DirStore implements Store
{
    private int $writes = 0;

    public function __construct(private string $dir, private int $failAfter = -1) {}

    private function path(string $base, string $rel): string
    {
        return $this->dir . '/' . $base . '/' . $rel;
    }

    public function get(string $base, string $rel): ?string
    {
        $path = $this->path($base, $rel);
        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    public function put(string $base, string $rel, string $bytes): void
    {
        if ($this->failAfter >= 0 && $this->writes >= $this->failAfter) {
            throw new RuntimeException('Interrupcao simulada apos ' . $this->writes . ' escrita(s).');
        }
        $this->writes++;
        $path = $this->path($base, $rel);
        if (!is_dir(dirname($path))) { mkdir(dirname($path), 0700, true); }
        file_put_contents($path . '.tmp', $bytes, LOCK_EX);
        if (hash_file('sha256', $path . '.tmp') !== hash('sha256', $bytes)) { throw new RuntimeException('Envio nao confere: ' . $rel); }
        rename($path . '.tmp', $path);
    }
}

function hashOf(?string $bytes): ?string
{
    return $bytes === null ? null : hash('sha256', $bytes);
}

function storeName(string $rel): string
{
    return str_replace('/', '__', $rel);
}

/** Grava e confere pela releitura do disco. */
function saveVerified(string $path, string $bytes): void
{
    if (!is_dir(dirname($path))) { mkdir(dirname($path), 0700, true); }
    file_put_contents($path, $bytes, LOCK_EX);
    if (hash_file('sha256', $path) !== hash('sha256', $bytes)) { throw new RuntimeException('Copia local nao confere: ' . $path); }
}

/**
 * Leva cada arquivo de $from para $to (hash completo). Antes de escrever: todos precisam estar em
 * $from ou $to, senao aborta sem escrever. Em cada arquivo: rele o hash imediatamente antes, pula se
 * ja estiver em $to e confere depois.
 *
 * @param array<string,string> $from
 * @param array<string,string> $to
 * @param array<string,string> $payload bytes do estado $to
 */
function transition(Store $store, array $from, array $to, array $payload, callable $log): void
{
    foreach (FILES as $f) {
        $h = hashOf($store->get($f['base'], $f['rel']));
        if ($h !== $from[$f['rel']] && $h !== $to[$f['rel']]) {
            throw new RuntimeException('Estado inesperado em ' . $f['rel'] . ' (' . ($h ?? 'ausente') . '); nada foi alterado.');
        }
    }
    foreach (FILES as $f) {
        $rel = $f['rel'];
        $h = hashOf($store->get($f['base'], $rel));
        if ($h === $to[$rel]) { $log('JA OK     ' . $rel); continue; }
        if ($h !== $from[$rel]) { throw new RuntimeException($rel . ' mudou durante a operacao (' . ($h ?? 'ausente') . '); abortado.'); }
        if (!isset($payload[$rel]) || hash('sha256', $payload[$rel]) !== $to[$rel]) { throw new RuntimeException('Pacote local nao confere: ' . $rel); }
        $store->put($f['base'], $rel, $payload[$rel]);
        if (hashOf($store->get($f['base'], $rel)) !== $to[$rel]) { throw new RuntimeException('Conferencia final falhou: ' . $rel); }
        $log('ENVIADO   ' . $rel . ' ' . $to[$rel]);
    }
}

/** @return array{0: FtpStore, 1: FTP\Connection} */
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
    return [new FtpStore($ftp, $coreRoot, $publicRoot), $ftp];
}

/**
 * site.css da producao + os 3 trechos das bordas, com o texto promovido lido do site.css da stage.
 * Devolve [css, erros]; com erro, o css nao e usado.
 *
 * @return array{0: string, 1: list<string>}
 */
function patchCss(string $prod, string $stage): array
{
    $errors = [];
    // A producao tem fins de linha misturados (o bloco .en-flow-bg de 25/10 em CRLF, o resto em LF):
    // cada trecho e localizado aceitando \r?\n e o resto do arquivo fica byte a byte igual.
    $block = '\.en-flow-bg \{\r?\n  position: fixed;\r?\n  inset: 0;\r?\n  width: 100%;\r?\n  height: 100%;\r?\n  z-index: -2;\r?\n  pointer-events: none;\r?\n\}\r?\n\r?\n';
    // 1) Degrade da newsletter fora da Home: o trecho entre o bloco .en-flow-bg e .scanline na stage.
    $news = '';
    if (preg_match_all('~' . $block . '(.*?)(?=\.scanline \{)~s', $stage, $ms) === 1) { $news = $ms[1][0]; }
    if (!str_contains($news, '.en-flow-bg ~ main #newsletter > .absolute.inset-0 {') || substr_count($news, '{') !== 1) { $errors[] = 'trecho da newsletter nao encontrado (ou ambiguo) no site.css da stage'; }
    $insertAt = -1;
    if (preg_match_all('~' . $block . '(?=\.scanline \{)~', $prod, $mp, PREG_OFFSET_CAPTURE) === 1) { $insertAt = $mp[0][0][1] + strlen($mp[0][0][0]); }
    if ($insertAt < 0) { $errors[] = 'ancora .en-flow-bg + .scanline nao aparece exatamente 1 vez na producao'; }
    if (str_contains($prod, '.en-flow-bg ~ main #newsletter')) { $errors[] = 'producao ja tem a regra da newsletter'; }
    // 2) e 3) Regras removidas (com a linha em branco seguinte): 1 vez na producao, nenhuma na stage.
    $removed = [];
    foreach (['.central-nerd-body {', '.central-hero-backdrop {'] as $selector) {
        if (substr_count($prod, $selector) !== 1 || preg_match_all('~' . preg_quote($selector, '~') . '[^}]*\}\r?\n\r?\n~', $prod, $mr) !== 1) { $errors[] = $selector . ' nao aparece exatamente 1 vez na producao'; continue; }
        if (str_contains($stage, $selector)) { $errors[] = $selector . ' ainda existe na stage'; }
        $removed[] = $mr[0][0];
    }
    if ($errors !== []) { return ['', $errors]; }
    $css = substr($prod, 0, $insertAt) . $news . substr($prod, $insertAt);
    foreach ($removed as $rule) { $css = str_replace($rule, '', $css); }
    $expectedLen = strlen($prod) + strlen($news) - array_sum(array_map('strlen', $removed));
    if (strlen($css) !== $expectedLen) { return ['', ['patch alterou mais do que os 3 trechos']]; }
    return [$css, []];
}

/** @return array{0:int, 1:string, 2:string} status, cabecalhos em minusculas, corpo */
function fetchUrl(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 30, CURLOPT_USERAGENT => 'EstrategiaNerd-Bordas/1.0']);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [$status, strtolower(substr($raw, 0, $headerSize)), substr($raw, $headerSize)];
}

function cssVersion(string $html): string
{
    return preg_match('~assets/css/site\.css\?v=([0-9-]+)~', $html, $m) === 1 ? $m[1] : '';
}

$dir = $root . '/storage/home-bordas-deploy/production';
$manifestFile = $dir . '/manifest.json';
if (!is_dir($dir)) { mkdir($dir, 0700, true); }
$log = static function (string $line) use ($dir): void {
    echo $line . "\n";
    file_put_contents($dir . '/log.txt', date('c') . ' ' . $line . "\n", FILE_APPEND | LOCK_EX);
};
$results = [];
$check = static function (bool $ok, string $label) use (&$results, $log): void {
    $results[] = $ok;
    $log(($ok ? 'PASS ' : 'FAIL ') . $label);
};
$failed = static function () use (&$results): bool { return in_array(false, $results, true); };

try {
    $log("== producao {$action}");
    $config = require $root . '/config/content-sync.php';
    $manifest = is_file($manifestFile) ? (array) json_decode((string) file_get_contents($manifestFile), true) : [];

    if ($action === '--check' || $action === '--backup') {
        // Somente leitura: producao, stage e commit.
        [$prod, $ftp] = connect($config['profiles']['production'], 'production');
        $current = [];
        foreach (FILES as $f) { $current[$f['rel']] = $prod->get($f['base'], $f['rel']); }
        $deps = [];
        foreach (PROD_DEPENDENCIES as [$base, $rel, $hash]) { $deps[$rel] = [hashOf($prod->get($base, $rel)), $hash]; }
        ftp_close($ftp);
        [$stage, $ftp] = connect($config['profiles']['stage'], 'stage');
        $stageViews = [];
        foreach (FILES as $f) { if ($f['rel'] !== CSS_REL) { $stageViews[$f['rel']] = $stage->get($f['base'], $f['rel']); } }
        $stageCss = (string) $stage->get('public', CSS_REL);
        ftp_close($ftp);

        $state = [];
        foreach (FILES as $f) {
            $h = hashOf($current[$f['rel']]);
            $state[$f['rel']] = $h === PROD_BEFORE[$f['rel']] ? 'antes' : (($manifest['files'][$f['rel']]['after'] ?? '') === $h ? 'depois' : 'DIVERGENTE');
            $check($state[$f['rel']] !== 'DIVERGENTE', sprintf('producao %-38s %s (%s)', $f['rel'], $state[$f['rel']], $h ?? 'ausente'));
        }
        foreach ($deps as $rel => [$h, $expected]) { $check($h === $expected, sprintf('producao dependencia %-50s inalterada', $rel)); }
        foreach (VIEWS_AFTER as $rel => $hash) {
            // Blob do Git (SHA-1 de "blob <tamanho>\0<bytes>"): compara com o commit sem passar os bytes por pipe.
            $blob = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD:' . escapeshellarg($rel) . ' 2>&1'));
            $bytes = (string) $stageViews[$rel];
            $check(hash('sha256', $bytes) === $hash && sha1('blob ' . strlen($bytes) . "\0" . $bytes) === $blob, sprintf('stage = commit %-38s %s', $rel, $hash));
        }
        $css = '';
        if ($state[CSS_REL] === 'depois') {
            $log('INFO site.css da producao ja e o do pacote; patch nao conferido de novo.');
        } else {
            [$css, $errors] = patchCss((string) $current[CSS_REL], $stageCss);
            foreach ($errors as $e) { $check(false, 'patch do site.css: ' . $e); }
            if ($errors === []) { $check(true, 'patch do site.css: os 3 trechos casam exatamente uma vez (' . hash('sha256', $css) . ')'); }
        }
        if ($failed()) { throw new RuntimeException('Conferencia falhou; nada foi preparado.'); }
        if ($action === '--check') { $log('PASS: producao, stage e commit conferidos (somente leitura).'); exit(0); }

        // --backup: copias da producao atual e pacote (views da stage + site.css montado).
        if (in_array('depois', $state, true)) { throw new RuntimeException('Producao ja tem arquivos do pacote; use --verify ou --rollback.'); }
        [, , $home] = fetchUrl(SITE_URL . '/?bordas=' . time());
        $files = [];
        foreach (FILES as $f) {
            $rel = $f['rel'];
            saveVerified($dir . '/before/' . storeName($rel), (string) $current[$rel]);
            $bytes = $rel === CSS_REL ? $css : (string) $stageViews[$rel];
            saveVerified($dir . '/package/' . storeName($rel), $bytes);
            $files[$rel] = ['base' => $f['base'], 'before' => PROD_BEFORE[$rel], 'after' => hash('sha256', $bytes), 'bytes_after' => strlen($bytes), 'source' => $rel === CSS_REL ? 'producao + 3 trechos do site.css da stage' : 'stage (= commit)'];
            $log(sprintf('COPIA     %-38s antes %s | depois %s', $rel, PROD_BEFORE[$rel], $files[$rel]['after']));
        }
        $manifest = [
            'target' => 'production', 'created_at' => date('c'), 'status' => 'pacote preparado',
            'commit' => trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD')),
            'stage_site_css' => hash('sha256', $stageCss), 'css_version_before' => cssVersion($home), 'files' => $files,
        ];
        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        $log('PASS: copias da producao e pacote conferidos em ' . $dir);
        exit(0);
    }

    // Demais acoes: pacote e copias conferidos pelo manifesto.
    if (empty($manifest['files'])) { throw new RuntimeException('Rode --backup antes.'); }
    $after = [];
    $before = [];
    $package = [];
    foreach (FILES as $f) {
        $rel = $f['rel'];
        $after[$rel] = (string) $manifest['files'][$rel]['after'];
        $b = (string) @file_get_contents($dir . '/before/' . storeName($rel));
        $p = (string) @file_get_contents($dir . '/package/' . storeName($rel));
        if (hash('sha256', $b) !== PROD_BEFORE[$rel] || hash('sha256', $p) !== $after[$rel]) { throw new RuntimeException('Copia ou pacote invalido: ' . $rel); }
        $before[$rel] = $b;
        $package[$rel] = $p;
    }

    if ($action === '--sandbox-test') {
        $sandbox = $dir . '/sandbox';
        $quiet = static function (string $line): void {};
        $seed = static function () use ($sandbox, $before): DirStore {
            if (is_dir($sandbox)) {
                $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sandbox, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($items as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
            }
            foreach (FILES as $f) { saveVerified($sandbox . '/' . $f['base'] . '/' . $f['rel'], $before[$f['rel']]); }
            return new DirStore($sandbox);
        };
        $snap = static function (Store $s): array { $o = []; foreach (FILES as $f) { $o[$f['rel']] = hashOf($s->get($f['base'], $f['rel'])); } return $o; };
        $seed();
        $interrupted = false;
        try { transition(new DirStore($sandbox, 3), PROD_BEFORE, $after, $package, $quiet); } catch (RuntimeException $e) { $interrupted = str_contains($e->getMessage(), 'Interrupcao simulada'); }
        $mid = $snap(new DirStore($sandbox));
        $check($interrupted && count(array_filter($mid, static fn (?string $h, string $rel): bool => $h === $after[$rel], ARRAY_FILTER_USE_BOTH)) === 3, 'teste 1: deploy interrompido apos 3 arquivos deixa 3 no estado novo e o resto no anterior');
        transition(new DirStore($sandbox), $after, PROD_BEFORE, $before, $quiet);
        $check($snap(new DirStore($sandbox)) === PROD_BEFORE, 'teste 2: rollback apos a interrupcao restaura os 7 arquivos');
        transition(new DirStore($sandbox), PROD_BEFORE, $after, $package, $quiet);
        $check($snap(new DirStore($sandbox)) === $after, 'teste 3: novo deploy completa e termina igual ao pacote');
        $store = $seed();
        file_put_contents($sandbox . '/core/app/Views/site/blog.php', "\n<!-- alterado fora do fluxo -->\n", FILE_APPEND);
        $guard = $snap($store);
        $aborted = false;
        try { transition($store, PROD_BEFORE, $after, $package, $quiet); } catch (RuntimeException $e) { $aborted = str_contains($e->getMessage(), 'nada foi alterado'); }
        $check($aborted && $snap($store) === $guard, 'teste 4: arquivo alterado fora do fluxo aborta sem nenhuma escrita');
        foreach (FILES as $f) {
            if ($f['rel'] === CSS_REL) { continue; }
            $lint = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($dir . '/package/' . storeName($f['rel'])) . ' 2>&1');
            $check(str_contains($lint, 'No syntax errors'), 'teste 5: php -l ' . $f['rel']);
        }
        if ($failed()) { throw new RuntimeException('Teste isolado do deploy falhou.'); }
        $manifest['status'] = 'validado';
        $manifest['validated_at'] = date('c');
        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        $log('PASS: deploy, interrupcao, rollback, retomada e trava validados em sandbox; producao nao foi tocada.');
        exit(0);
    }

    if ($action === '--deploy' || $action === '--rollback') {
        if ($action === '--deploy' && !in_array($manifest['status'] ?? '', ['validado', 'aplicado', 'revertido'], true)) { throw new RuntimeException('Rode --sandbox-test antes.'); }
        if ($action === '--rollback' && !$confirmed) { throw new RuntimeException('Rollback de producao exige --confirmar.'); }
        if ($action === '--deploy') {
            // A stage precisa continuar igual ao pacote (views) no momento do deploy.
            [$stage, $ftp] = connect($config['profiles']['stage'], 'stage');
            foreach (VIEWS_AFTER as $rel => $hash) { $check(hashOf($stage->get('core', $rel)) === $hash && $after[$rel] === $hash, 'stage ainda = pacote ' . $rel); }
            ftp_close($ftp);
            if ($failed()) { throw new RuntimeException('Stage mudou desde o --backup; nada foi enviado.'); }
        }
        [$prod, $ftp] = connect($config['profiles']['production'], 'production');
        if ($action === '--deploy') {
            transition($prod, PROD_BEFORE, $after, $package, $log);
            $manifest['status'] = 'aplicado';
            $manifest['deployed_at'] = date('c');
        } else {
            transition($prod, $after, PROD_BEFORE, $before, $log);
            $manifest['status'] = 'revertido';
            $manifest['rolled_back_at'] = date('c');
        }
        ftp_close($ftp);
        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        $log('PASS: producao ' . $manifest['status'] . ' e conferida por hash.');
        exit(0);
    }

    // --verify: hashes, paginas (status, sem as bordas, indexavel) e site.css servido.
    [$prod, $ftp] = connect($config['profiles']['production'], 'production');
    foreach (FILES as $f) {
        $h = hashOf($prod->get($f['base'], $f['rel']));
        $check($h === $after[$f['rel']], sprintf('producao %-38s %s', $f['rel'], $h ?? 'ausente'));
    }
    ftp_close($ftp);
    [, , $sitemap] = fetchUrl(SITE_URL . '/sitemap.xml');
    $pages = PAGES;
    // Um post publicado (soma 1 visualizacao na producao): o primeiro do sitemap.
    if (preg_match('~<loc>' . preg_quote(SITE_URL, '~') . '(/post/[^<]+)</loc>~', $sitemap, $m) === 1) {
        $pages[] = [$m[1], 200, ['to-slate-950/60', 'id="comentarios" class="py-16 bg-slate-900/30']];
    } else {
        $check(false, 'nenhum post no sitemap para conferir post.php');
    }
    $homeHtml = '';
    foreach ($pages as [$path, $expected, $absent]) {
        [$status, $headers, $body] = fetchUrl(SITE_URL . $path . (str_contains($path, '?') ? '&' : '?') . 'v=' . time());
        if ($path === '/') { $homeHtml = $body; }
        $isHtml = str_contains($headers, 'content-type: text/html') && str_contains($body, '<html');
        $missing = array_values(array_filter($absent, static fn (string $s): bool => str_contains($body, $s)));
        $ok = $status === $expected && $isHtml && str_contains($body, 'en-flow-bg') && $missing === []
            && !str_contains($headers, 'x-robots-tag: noindex') && !str_contains($body, 'site-ambient-background');
        $check($ok, sprintf('%-62s HTTP %d (esperado %d), HTML, en-flow-bg, indexavel%s', $path, $status, $expected, $missing === [] ? ', sem as bordas' : ', AINDA TEM: ' . implode(' | ', $missing)));
    }
    $version = cssVersion($homeHtml);
    [$cssStatus, , $cssBody] = fetchUrl(SITE_URL . '/assets/css/site.css?v=' . $version);
    $check($version !== '' && $version !== (string) ($manifest['css_version_before'] ?? ''), 'site.css com versao nova na URL (' . ($manifest['css_version_before'] ?? '?') . ' -> ' . $version . ')');
    $check($cssStatus === 200 && hash('sha256', $cssBody) === $after[CSS_REL], 'site.css servido = pacote (' . hash('sha256', $cssBody) . ')');
    if ($failed()) { throw new RuntimeException('Verificacao da producao falhou.'); }
    $manifest['verified_at'] = date('c');
    file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    $log('PASS: producao igual ao pacote, paginas sem as bordas e site.css novo servido.');
    exit(0);
} catch (Throwable $e) {
    $log('STOP: ' . $e->getMessage());
    exit(1);
}
