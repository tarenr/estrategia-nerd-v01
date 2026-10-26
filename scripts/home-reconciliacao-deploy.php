<?php
/**
 * -----------------------------------------------------------------------------
 * @file        scripts/home-reconciliacao-deploy.php
 * @project     Estrategia Nerd
 * @purpose     Reconciliacao da home nova (26/10/2026, Forge #1689 a #1692, FEAT-015):
 *              - producao: rollback PREPARADO das 3 alteracoes publicadas em 25/10 23:43
 *                (layouts/site.php, assets/css/site.css e shared/ambient-background.php).
 *              - stage: deploy controlado do fundo das paginas publicas sem emendas, do Echo
 *                (#1666) e do cabecalho (#1684), com a remocao do motor Canvas 2D descartado.
 * @usage       C:\xampp\php\php.exe scripts/home-reconciliacao-deploy.php producao <acao>
 *                acoes: --check | --sandbox-test | --rollback --confirmar
 *              C:\xampp\php\php.exe scripts/home-reconciliacao-deploy.php stage <acao>
 *                acoes: --check | --backup | --sandbox-test | --deploy | --verify | --rollback
 *                       | --refresh <arquivo do pacote>  (reenvia um arquivo ja aplicado, conferido)
 * @notes       - producao --check e somente leitura no servidor: baixa os 3 arquivos atuais
 *                (after/), monta as versoes anteriores (before/) a partir do backup
 *                BS-PROD-20261025-234355 e do pacote do deploy das 21:21 e confere tudo pelo
 *                SHA-256 completo. O --rollback real exige --confirmar.
 *              - Estados do rollback de producao no manifesto: preparado -> validado -> executado.
 *              - Toda escrita rele o hash remoto imediatamente antes e aborta se divergir.
 *                Envio por arquivo .tmp, conferencia, rename e nova conferencia.
 *              - Rollback e deploy sao idempotentes: arquivo ja no estado final e pulado; hash
 *                fora de {antes, depois} aborta antes de qualquer escrita.
 *              - --sandbox-test roda a mesma logica numa pasta local (sandbox/), com
 *                interrupcao simulada, sem tocar em nenhum servidor.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }

require __DIR__ . '/backup/EnvLoader.php';
$root = dirname(__DIR__);
Scripts\Backup\EnvLoader::load($root . '/.env');

$target = (string) ($argv[1] ?? '');
$action = (string) ($argv[2] ?? '');
$confirmed = in_array('--confirmar', $argv, true);
$actions = [
    'producao' => ['--check', '--sandbox-test', '--rollback'],
    'stage' => ['--check', '--backup', '--sandbox-test', '--deploy', '--verify', '--rollback', '--refresh'],
];
if (!isset($actions[$target]) || !in_array($action, $actions[$target], true)) {
    fwrite(STDERR, "Use: producao --check|--sandbox-test|--rollback --confirmar\n     stage --check|--backup|--sandbox-test|--deploy|--verify|--rollback|--refresh <arquivo>\n");
    exit(2);
}

const STAGE_URL = 'https://estrategianerd.com.br/stage';

// Producao: estado publicado em 25/10 23:43 (after) e o estado anterior comprovado (before).
// before null = o arquivo nao existia (o rollback o remove). Ordem = ordem do rollback.
const PROD_FILES = [
    ['base' => 'core', 'rel' => 'app/Views/layouts/site.php'],
    ['base' => 'public', 'rel' => 'assets/css/site.css'],
    ['base' => 'core', 'rel' => 'app/Views/components/site/shared/ambient-background.php'],
];
const PROD_AFTER = [
    'app/Views/layouts/site.php' => '485cae00eb88961394ccbe74e62943a3c836ba03fb88217807dff4476c169fbb',
    'assets/css/site.css' => '16cba1a43ee9c7c47af8636c512a0d568b7569f9f860fa7e20b9dc7ffcf91bca',
    'app/Views/components/site/shared/ambient-background.php' => '553dafc443e0c390cbd9e70387a8ca5941a1037455532c84f38826c75830df93',
];
const PROD_BEFORE = [
    'app/Views/layouts/site.php' => '8625f9ab51fa3ed5fb9d2e68fd42fbc569dfaec26b8a915ce5ad91485295fb6d',
    'assets/css/site.css' => 'ebe714d98dfa44f6129f6a924d63aba09b2cf835d183a33e13a6cf78053e7016',
    'app/Views/components/site/shared/ambient-background.php' => null,
];
const PROD_BACKUP_ID = 'BS-PROD-20261025-234355';
const PROD_BACKUP_ZIP_SHA = '85a9dfeff7cd509b687a2ae7f0fe543994a4e184605f18eaabc21c4ee8633f1f';

// Stage: inventario de 26/10/2026 (before). local null = remover do servidor. Ordem = ordem do deploy.
const STAGE_FILES = [
    ['base' => 'public', 'rel' => 'assets/css/site-home-flow.css', 'local' => 'public/assets/css/site-home-flow.css'],
    ['base' => 'public', 'rel' => 'assets/js/site-home-flow.js', 'local' => 'public/assets/js/site-home-flow.js'],
    ['base' => 'public', 'rel' => 'assets/css/site.css', 'local' => 'public/assets/css/site.css'],
    ['base' => 'core', 'rel' => 'app/Views/site/blog.php', 'local' => 'app/Views/site/blog.php'],
    ['base' => 'core', 'rel' => 'app/Views/site/post.php', 'local' => 'app/Views/site/post.php'],
    ['base' => 'core', 'rel' => 'app/Views/site/central.php', 'local' => 'app/Views/site/central.php'],
    ['base' => 'core', 'rel' => 'app/Views/site/page.php', 'local' => 'app/Views/site/page.php'],
    ['base' => 'core', 'rel' => 'app/Views/site/error-404.php', 'local' => 'app/Views/site/error-404.php'],
    ['base' => 'core', 'rel' => 'app/Views/site/post-unavailable.php', 'local' => 'app/Views/site/post-unavailable.php'],
    ['base' => 'public', 'rel' => 'assets/js/site-ambient-background.js', 'local' => null],
];
const STAGE_BEFORE = [
    'assets/css/site-home-flow.css' => '23cfd061c9bf3e6121d5a5ab350aa0735bf9c7591bed70cca4de61d5458821dd',
    'assets/js/site-home-flow.js' => 'b3e200400f9a59d6f10a9d7dddede44aa63cd4691d0f7e0b4e10f631275647ec',
    'assets/css/site.css' => 'bc64a637e65a06433ad978e90c833a4f432076c35e3839a778bbb3ea8e929a8b',
    'app/Views/site/blog.php' => 'b1ca330ee4226a36c64921efe413969a540eeba3c56680443a48b567bc901017',
    'app/Views/site/post.php' => '086ad99fb06cef2ffb5045088a155b4b122dcecfd4cd16b81f6a030548590900',
    'app/Views/site/central.php' => '03e5d948a55570627d8485d34501d13c226e540d1b761b678d323dba9b2e2189',
    'app/Views/site/page.php' => '6f77dc8652b93521be281a0f962c768405e86555c6d4641c51763539e78695ca',
    'app/Views/site/error-404.php' => '21b08f1e69de43e7b0431ff30ea36316d89c6514fe3d878886ec3d3b7be57f4c',
    'app/Views/site/post-unavailable.php' => 'd53735ab0a80b4af799cc95565597539316e348d18bc256eef560f4e005f12b6',
    'assets/js/site-ambient-background.js' => 'a23b1b60177053a4f199bd9585c64533502f1e76cfc9e5f54728af58b118f020',
];
// Paginas conferidas no --verify da stage: [caminho, status esperado].
const STAGE_PAGES = [
    ['/', 200], ['/blog', 200], ['/blog/hardware', 200],
    ['/post/10-jogos-que-todo-fa-de-rpg-precisa-jogar-antes-de-morrer', 200],
    ['/post/post-inexistente-auditoria', 404], ['/central-nerd', 200],
    ['/politica-de-privacidade', 200], ['/termos-de-uso', 200], ['/rota-inexistente-auditoria', 404],
];

interface Store
{
    public function get(string $base, string $rel): ?string;
    public function put(string $base, string $rel, string $bytes): void;
    public function delete(string $base, string $rel): void;
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

    public function delete(string $base, string $rel): void
    {
        $path = $this->path($base, $rel);
        if (@ftp_size($this->ftp, $path) >= 0 && !@ftp_delete($this->ftp, $path)) { throw new RuntimeException('Nao foi possivel remover ' . $rel); }
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

    private function tick(): void
    {
        if ($this->failAfter >= 0 && $this->writes >= $this->failAfter) {
            throw new RuntimeException('Interrupcao simulada apos ' . $this->writes . ' escrita(s).');
        }
        $this->writes++;
    }

    public function get(string $base, string $rel): ?string
    {
        $path = $this->path($base, $rel);
        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    public function put(string $base, string $rel, string $bytes): void
    {
        $this->tick();
        $path = $this->path($base, $rel);
        if (!is_dir(dirname($path))) { mkdir(dirname($path), 0700, true); }
        file_put_contents($path . '.tmp', $bytes, LOCK_EX);
        if (hash_file('sha256', $path . '.tmp') !== hash('sha256', $bytes)) { throw new RuntimeException('Envio nao confere: ' . $rel); }
        rename($path . '.tmp', $path);
    }

    public function delete(string $base, string $rel): void
    {
        $this->tick();
        $path = $this->path($base, $rel);
        if (is_file($path)) { unlink($path); }
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
 * Leva cada arquivo de $from para $to (hash completo; null = ausente).
 * Antes de escrever: todos precisam estar em $from ou $to, senao aborta sem escrever.
 * Em cada arquivo: rele o hash imediatamente antes, pula se ja estiver em $to e confere depois.
 *
 * @param list<array{base:string,rel:string}> $files
 * @param array<string,?string> $from
 * @param array<string,?string> $to
 * @param array<string,string> $payload bytes do estado $to
 */
function transition(Store $store, array $files, array $from, array $to, array $payload, callable $log): void
{
    foreach ($files as $f) {
        $h = hashOf($store->get($f['base'], $f['rel']));
        if ($h !== $from[$f['rel']] && $h !== $to[$f['rel']]) {
            throw new RuntimeException('Estado inesperado em ' . $f['rel'] . ' (' . ($h ?? 'ausente') . '); nada foi alterado.');
        }
    }
    foreach ($files as $f) {
        $rel = $f['rel'];
        $h = hashOf($store->get($f['base'], $rel));
        if ($h === $to[$rel]) { $log('JA OK     ' . $rel); continue; }
        if ($h !== $from[$rel]) { throw new RuntimeException($rel . ' mudou durante a operacao (' . ($h ?? 'ausente') . '); abortado.'); }
        if ($to[$rel] === null) {
            $store->delete($f['base'], $rel);
            $log('REMOVIDO  ' . $rel);
        } else {
            if (!isset($payload[$rel]) || hash('sha256', $payload[$rel]) !== $to[$rel]) { throw new RuntimeException('Pacote local nao confere: ' . $rel); }
            $store->put($f['base'], $rel, $payload[$rel]);
            $log('ENVIADO   ' . $rel . ' ' . $to[$rel]);
        }
        if (hashOf($store->get($f['base'], $rel)) !== $to[$rel]) { throw new RuntimeException('Conferencia final falhou: ' . $rel); }
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

/** Apaga somente pastas sandbox/ dentro de storage/home-pendencias-deploy. */
function resetSandbox(string $dir): void
{
    $base = str_replace('\\', '/', (string) realpath(dirname(__DIR__) . '/storage/home-pendencias-deploy'));
    $real = str_replace('\\', '/', (string) realpath($dir));
    if ($real !== '' && is_dir($dir)) {
        if (!str_starts_with($real, $base . '/') || basename($real) !== 'sandbox') { throw new RuntimeException('Sandbox fora do lugar esperado: ' . $real); }
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
        rmdir($dir);
    }
    mkdir($dir, 0700, true);
}

/** @param list<array{base:string,rel:string}> $files @return array<string,?string> */
function snapshot(Store $store, array $files): array
{
    $out = [];
    foreach ($files as $f) { $out[$f['rel']] = hashOf($store->get($f['base'], $f['rel'])); }
    return $out;
}

/** @param array<string,string|null> $bytes */
function seed(string $dir, array $files, array $bytes): DirStore
{
    resetSandbox($dir);
    $store = new DirStore($dir);
    foreach ($files as $f) {
        if ($bytes[$f['rel']] !== null) { saveVerified($dir . '/' . $f['base'] . '/' . $f['rel'], (string) $bytes[$f['rel']]); }
    }
    return $store;
}

$dir = $root . '/storage/home-pendencias-deploy/' . ($target === 'producao' ? 'production' : 'stage-20261026');
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

try {
    $log("== {$target} {$action}");
    $config = require $root . '/config/content-sync.php';

    if ($target === 'producao') {
        $files = PROD_FILES;
        $manifest = is_file($manifestFile) ? (array) json_decode((string) file_get_contents($manifestFile), true) : [];

        if ($action === '--check') {
            // 1) Versoes anteriores: site.php do backup das 23:43, site.css do pacote das 21:21.
            $backupRoot = (string) ((require $root . '/config/backup.php')['backup_root'] ?? '');
            $zipPath = $backupRoot . '/03-prod/dados/' . PROD_BACKUP_ID . '/system-files.zip';
            $check(is_file($zipPath) && hash_file('sha256', $zipPath) === PROD_BACKUP_ZIP_SHA, 'zip do backup ' . PROD_BACKUP_ID . ' confere com o SHA-256 do manifesto do backup');
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) { throw new RuntimeException('Nao foi possivel abrir o zip do backup.'); }
            $layout = $zip->getFromName('app\\Views\\layouts\\site.php');
            $hasAmbient = $zip->locateName('app\\Views\\components\\site\\shared\\ambient-background.php') !== false
                || $zip->locateName('app/Views/components/site/shared/ambient-background.php') !== false;
            $zip->close();
            $cssBefore = (string) @file_get_contents($root . '/storage/home-rio-deploy/production/package/assets__css__site.css');
            $rioManifest = (array) json_decode((string) @file_get_contents($root . '/storage/home-rio-deploy/production/manifest.json'), true);
            $rioCssAfter = '';
            foreach ((array) ($rioManifest['files'] ?? []) as $f) { if (($f['rel'] ?? '') === 'assets/css/site.css') { $rioCssAfter = (string) $f['after']; } }
            $before = [
                'app/Views/layouts/site.php' => is_string($layout) ? $layout : null,
                'assets/css/site.css' => $cssBefore !== '' ? $cssBefore : null,
                'app/Views/components/site/shared/ambient-background.php' => null,
            ];
            $check(hashOf($before['app/Views/layouts/site.php']) === PROD_BEFORE['app/Views/layouts/site.php'], 'site.php anterior (zip) = ' . PROD_BEFORE['app/Views/layouts/site.php']);
            $check(hashOf($before['assets/css/site.css']) === PROD_BEFORE['assets/css/site.css'] && $rioCssAfter === PROD_BEFORE['assets/css/site.css'], 'site.css anterior (pacote 21:21, "after" do manifesto 21:21) = ' . PROD_BEFORE['assets/css/site.css']);
            $check(!$hasAmbient, 'ambient-background.php ausente no backup das 23:43 (rollback = remover)');

            // 2) Estado atual da producao: somente leitura.
            [$prod, $ftp] = connect($config['profiles']['production'], 'production');
            $after = [];
            foreach ($files as $f) { $after[$f['rel']] = $prod->get($f['base'], $f['rel']); }
            ftp_close($ftp);
            foreach ($files as $f) { $check(hashOf($after[$f['rel']]) === PROD_AFTER[$f['rel']], 'producao atual ' . $f['rel'] . ' = ' . PROD_AFTER[$f['rel']]); }
            if (in_array(false, $results, true)) { throw new RuntimeException('Conferencia falhou; nada preparado.'); }

            // 3) Copias locais conferidas pela releitura do disco.
            foreach ($files as $f) {
                saveVerified($dir . '/after/' . storeName($f['rel']), (string) $after[$f['rel']]);
                if ($before[$f['rel']] !== null) { saveVerified($dir . '/before/' . storeName($f['rel']), (string) $before[$f['rel']]); }
            }
            $plain = str_replace("\r\n", "\n", (string) $after['assets/css/site.css']);
            $block = ".en-flow-bg {\n  position: fixed;\n  inset: 0;\n  width: 100%;\n  height: 100%;\n  z-index: -2;\n  pointer-events: none;\n}\n\n";
            $check(str_replace($block, '', $plain) === str_replace("\r\n", "\n", (string) $before['assets/css/site.css']), 'site.css atual = site.css anterior + bloco .en-flow-bg de 9 linhas');

            $manifest['target'] = 'production';
            $manifest['prepared_at'] = $manifest['prepared_at'] ?? date('c');
            $manifest['checked_at'] = date('c');
            $manifest['status'] = in_array($manifest['status'] ?? '', ['validado', 'executado'], true) ? $manifest['status'] : 'preparado';
            $manifest['sources'] = ['site.php' => PROD_BACKUP_ID . '/system-files.zip', 'site.css' => 'storage/home-rio-deploy/production/package/assets__css__site.css'];
            $manifest['files'] = array_map(static fn (array $f): array => $f + ['before' => PROD_BEFORE[$f['rel']], 'after' => PROD_AFTER[$f['rel']], 'store' => storeName($f['rel'])], $files);
            file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

            $log('Passos do rollback (NAO executados): restaurar app/Views/layouts/site.php; restaurar assets/css/site.css; remover app/Views/components/site/shared/ambient-background.php.');
            if (in_array(false, $results, true)) { throw new RuntimeException('Conferencia falhou.'); }
            $log('PASS: rollback ' . $manifest['status'] . ' em ' . $dir . ' (producao nao foi alterada).');
            exit(0);
        }

        $before = [];
        $after = [];
        foreach ($files as $f) {
            $after[$f['rel']] = is_file($dir . '/after/' . storeName($f['rel'])) ? (string) file_get_contents($dir . '/after/' . storeName($f['rel'])) : null;
            $before[$f['rel']] = PROD_BEFORE[$f['rel']] === null ? null : (string) @file_get_contents($dir . '/before/' . storeName($f['rel']));
            if (hashOf($after[$f['rel']]) !== PROD_AFTER[$f['rel']] || hashOf($before[$f['rel']]) !== PROD_BEFORE[$f['rel']]) { throw new RuntimeException('Copias before/after invalidas; rode producao --check.'); }
        }
        $payload = array_filter($before, static fn (?string $b): bool => $b !== null);

        if ($action === '--sandbox-test') {
            $sandbox = $dir . '/sandbox';
            $quiet = static function (string $line): void {};
            // 1) Rollback completo.
            $store = seed($sandbox, $files, $after);
            transition($store, $files, PROD_AFTER, PROD_BEFORE, $payload, $quiet);
            $check(snapshot($store, $files) === PROD_BEFORE, 'teste 1: rollback completo deixa site.php e site.css anteriores e remove o componente');
            // 2) Interrupcao apos o 1o arquivo e nova execucao.
            seed($sandbox, $files, $after);
            $failed = false;
            try { transition(new DirStore($sandbox, 1), $files, PROD_AFTER, PROD_BEFORE, $payload, $quiet); } catch (RuntimeException $e) { $failed = str_contains($e->getMessage(), 'Interrupcao simulada'); }
            $mid = snapshot(new DirStore($sandbox), $files);
            $check($failed && $mid['app/Views/layouts/site.php'] === PROD_BEFORE['app/Views/layouts/site.php'] && $mid['assets/css/site.css'] === PROD_AFTER['assets/css/site.css'], 'teste 2a: interrupcao apos o 1o arquivo deixa estado misto conhecido');
            $store = new DirStore($sandbox);
            transition($store, $files, PROD_AFTER, PROD_BEFORE, $payload, $quiet);
            $check(snapshot($store, $files) === PROD_BEFORE, 'teste 2b: nova execucao completa o rollback (idempotente)');
            // 3) Trava: hash estranho aborta sem escrever.
            $store = seed($sandbox, $files, $after);
            file_put_contents($sandbox . '/public/assets/css/site.css', "/* alterado fora do fluxo */\n", FILE_APPEND);
            $beforeGuard = snapshot($store, $files);
            $aborted = false;
            try { transition($store, $files, PROD_AFTER, PROD_BEFORE, $payload, $quiet); } catch (RuntimeException $e) { $aborted = str_contains($e->getMessage(), 'nada foi alterado'); }
            $check($aborted && snapshot($store, $files) === $beforeGuard, 'teste 3: arquivo com hash fora de {antes, depois} aborta sem nenhuma escrita');
            // 4) Coerencia do site.php restaurado.
            $restored = (string) $before['app/Views/layouts/site.php'];
            $lint = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($dir . '/before/' . storeName('app/Views/layouts/site.php')) . ' 2>&1');
            $check(str_contains($lint, 'No syntax errors'), 'teste 4a: php -l no site.php anterior');
            $check(!str_contains($restored, 'ambient-background') && !str_contains($restored, 'en-flow-bg') && !str_contains($restored, 'site-home-flow.js'), 'teste 4b: site.php anterior nao referencia o componente nem o fundo removidos');
            resetSandbox($sandbox);
            if (in_array(false, $results, true)) { throw new RuntimeException('Teste isolado falhou; rollback NAO validado.'); }
            $manifest['status'] = ($manifest['status'] ?? '') === 'executado' ? 'executado' : 'validado';
            $manifest['validated_at'] = date('c');
            $manifest['validation'] = 'sandbox local: rollback completo, interrupcao e retomada, trava de hash, php -l e coerencia do layout';
            file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
            $log('PASS: rollback validado em sandbox; producao nao foi tocada.');
            exit(0);
        }

        // --rollback real em producao.
        if (!$confirmed) { throw new RuntimeException('Rollback de producao exige --confirmar.'); }
        [$prod, $ftp] = connect($config['profiles']['production'], 'production');
        transition($prod, $files, PROD_AFTER, PROD_BEFORE, $payload, $log);
        ftp_close($ftp);
        $manifest['status'] = 'executado';
        $manifest['executed_at'] = date('c');
        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        $log('PASS: producao voltou ao estado anterior a 25/10 23:43.');
        exit(0);
    }

    // ---------------------------------------------------------------- stage
    $files = array_map(static fn (array $f): array => ['base' => $f['base'], 'rel' => $f['rel']], STAGE_FILES);
    $manifest = is_file($manifestFile) ? (array) json_decode((string) file_get_contents($manifestFile), true) : [];
    $afterHashes = [];
    foreach ((array) ($manifest['files'] ?? []) as $f) { $afterHashes[(string) $f['rel']] = $f['after']; }

    if ($action === '--check' || $action === '--backup') {
        [$stage, $ftp] = connect($config['profiles']['stage'], 'stage');
        $current = [];
        foreach ($files as $f) { $current[$f['rel']] = $stage->get($f['base'], $f['rel']); }
        ftp_close($ftp);
        foreach ($files as $f) {
            $h = hashOf($current[$f['rel']]);
            $state = $h === STAGE_BEFORE[$f['rel']] ? 'antes' : (array_key_exists($f['rel'], $afterHashes) && $h === $afterHashes[$f['rel']] ? 'depois' : 'DIVERGENTE');
            $check($state !== 'DIVERGENTE' && ($action === '--check' || $state === 'antes'), sprintf('%-40s %s (%s)', $f['rel'], $state, $h ?? 'ausente'));
        }
        if (in_array(false, $results, true)) { throw new RuntimeException('Stage diverge do inventario; nada foi feito.'); }
        if ($action === '--check') { $log('PASS: stage conferida (somente leitura).'); exit(0); }

        $manifest = ['target' => 'stage', 'created_at' => date('c'), 'status' => 'pacote preparado', 'files' => []];
        foreach (STAGE_FILES as $f) {
            saveVerified($dir . '/before/' . storeName($f['rel']), (string) $current[$f['rel']]);
            $afterHash = null;
            if ($f['local'] !== null) {
                $bytes = (string) file_get_contents($root . '/' . $f['local']);
                saveVerified($dir . '/package/' . storeName($f['rel']), $bytes);
                $afterHash = hash('sha256', $bytes);
            }
            $manifest['files'][] = ['base' => $f['base'], 'rel' => $f['rel'], 'local' => $f['local'], 'before' => STAGE_BEFORE[$f['rel']], 'after' => $afterHash, 'store' => storeName($f['rel'])];
            $log(sprintf('COPIA     %-40s antes %s | depois %s', $f['rel'], STAGE_BEFORE[$f['rel']], $afterHash ?? 'removido'));
        }
        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        $log('PASS: copias da stage e pacote local conferidos em ' . $dir);
        exit(0);
    }

    if ($afterHashes === []) { throw new RuntimeException('Rode stage --backup antes.'); }
    $before = [];
    $package = [];
    foreach (STAGE_FILES as $f) {
        $b = (string) @file_get_contents($dir . '/before/' . storeName($f['rel']));
        if (hash('sha256', $b) !== STAGE_BEFORE[$f['rel']]) { throw new RuntimeException('Copia before invalida: ' . $f['rel']); }
        $before[$f['rel']] = $b;
        if ($afterHashes[$f['rel']] !== null) {
            $p = (string) @file_get_contents($dir . '/package/' . storeName($f['rel']));
            if (hash('sha256', $p) !== $afterHashes[$f['rel']]) { throw new RuntimeException('Pacote invalido: ' . $f['rel']); }
            $package[$f['rel']] = $p;
        }
    }

    if ($action === '--refresh') {
        // Reenvia um arquivo do pacote ja aplicado (ajuste apos a medicao). before/ nao muda: o
        // --rollback continua levando ao estado original da stage.
        $rel = (string) ($argv[3] ?? '');
        $entry = null;
        foreach (STAGE_FILES as $f) { if ($f['rel'] === $rel && $f['local'] !== null) { $entry = $f; } }
        if ($entry === null) { throw new RuntimeException('Arquivo fora do pacote: ' . $rel); }
        $bytes = (string) file_get_contents($root . '/' . $entry['local']);
        $newHash = hash('sha256', $bytes);
        [$stage, $ftp] = connect($config['profiles']['stage'], 'stage');
        transition($stage, [['base' => $entry['base'], 'rel' => $rel]], [$rel => $afterHashes[$rel]], [$rel => $newHash], [$rel => $bytes], $log);
        ftp_close($ftp);
        saveVerified($dir . '/package/' . storeName($rel), $bytes);
        foreach ($manifest['files'] as $i => $mf) {
            if ($mf['rel'] === $rel) { $manifest['files'][$i]['refreshed_from'] = $mf['after']; $manifest['files'][$i]['after'] = $newHash; }
        }
        $manifest['refreshed_at'] = date('c');
        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        $log('PASS: ' . $rel . ' reenviado e conferido (' . $newHash . ').');
        exit(0);
    }

    if ($action === '--sandbox-test') {
        $sandbox = $dir . '/sandbox';
        $quiet = static function (string $line): void {};
        $store = seed($sandbox, $files, $before);
        $failed = false;
        try { transition(new DirStore($sandbox, 4), $files, STAGE_BEFORE, $afterHashes, $package, $quiet); } catch (RuntimeException $e) { $failed = str_contains($e->getMessage(), 'Interrupcao simulada'); }
        $mid = snapshot($store, $files);
        $done = count(array_filter($files, static fn (array $f): bool => $mid[$f['rel']] === $afterHashes[$f['rel']]));
        $check($failed && $done === 4, 'teste 1: deploy interrompido apos 4 arquivos deixa 4 no estado novo e o resto no anterior');
        transition($store, $files, $afterHashes, STAGE_BEFORE, $before, $quiet);
        $check(snapshot($store, $files) === STAGE_BEFORE, 'teste 2: rollback apos a interrupcao restaura todos os arquivos (inclusive o removido)');
        transition($store, $files, STAGE_BEFORE, $afterHashes, $package, $quiet);
        $check(snapshot($store, $files) === $afterHashes, 'teste 3: novo deploy completa e termina igual ao pacote');
        $store = seed($sandbox, $files, $before);
        file_put_contents($sandbox . '/core/app/Views/site/blog.php', "\n<!-- alterado fora do fluxo -->\n", FILE_APPEND);
        $guard = snapshot($store, $files);
        $aborted = false;
        try { transition($store, $files, STAGE_BEFORE, $afterHashes, $package, $quiet); } catch (RuntimeException $e) { $aborted = str_contains($e->getMessage(), 'nada foi alterado'); }
        $check($aborted && snapshot($store, $files) === $guard, 'teste 4: arquivo alterado fora do fluxo aborta sem nenhuma escrita');
        resetSandbox($sandbox);
        if (in_array(false, $results, true)) { throw new RuntimeException('Teste isolado do deploy falhou.'); }
        $log('PASS: deploy, interrupcao, rollback e retomada validados em sandbox.');
        exit(0);
    }

    if ($action === '--deploy' || $action === '--rollback') {
        [$stage, $ftp] = connect($config['profiles']['stage'], 'stage');
        if ($action === '--deploy') {
            transition($stage, $files, STAGE_BEFORE, $afterHashes, $package, $log);
            $manifest['status'] = 'aplicado';
            $manifest['deployed_at'] = date('c');
        } else {
            transition($stage, $files, $afterHashes, STAGE_BEFORE, $before, $log);
            $manifest['status'] = 'revertido';
            $manifest['rolled_back_at'] = date('c');
        }
        ftp_close($ftp);
        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        $log('PASS: stage ' . $manifest['status'] . ' e conferida por hash.');
        exit(0);
    }

    // --verify: hashes + paginas.
    [$stage, $ftp] = connect($config['profiles']['stage'], 'stage');
    foreach ($files as $f) {
        $h = hashOf($stage->get($f['base'], $f['rel']));
        $check($h === $afterHashes[$f['rel']], sprintf('%-40s %s', $f['rel'], $h ?? 'ausente (removido)'));
    }
    ftp_close($ftp);
    foreach (STAGE_PAGES as [$path, $expected]) {
        $ch = curl_init(STAGE_URL . ($path === '/' ? '/' : $path) . (str_contains($path, '?') ? '&' : '?') . 'v=' . time());
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 30, CURLOPT_USERAGENT => 'EstrategiaNerd-Reconciliacao/1.0']);
        $raw = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $headers = strtolower(substr($raw, 0, $headerSize));
        $body = substr($raw, $headerSize);
        $isHtml = str_contains($headers, 'content-type: text/html') && str_contains($body, '<html');
        $ok = $status === $expected && $isHtml && !str_contains($body, 'site-ambient-background')
            && str_contains($body, 'en-flow-bg') && str_contains($headers, 'x-robots-tag: noindex');
        $check($ok, sprintf('%-62s HTTP %d (esperado %d), HTML, noindex, en-flow-bg, sem motor 2D', $path, $status, $expected));
    }
    if (in_array(false, $results, true)) { throw new RuntimeException('Verificacao da stage falhou.'); }
    $log('PASS: stage igual ao pacote e paginas conferidas.');
    exit(0);
} catch (Throwable $e) {
    $log('STOP: ' . $e->getMessage());
    exit(1);
}
