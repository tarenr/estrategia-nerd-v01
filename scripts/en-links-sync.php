<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__) . '/bootstrap.php';

// Publica SOMENTE a tabela links entre ambientes (IMP-030).
// Diferente do content-sync: atualiza/cria apenas pelo slug, nunca casa por URL e nunca apaga linhas,
// porque o catalogo tem links intencionais com a mesma URL e slugs diferentes.

const CAMPOS = [
    'titulo', 'slug', 'url', 'tipo', 'promocao', 'desconto_percentual', 'desconto_contexto', 'codigo_cupom',
    'secao_publica', 'subgrupo_publico', 'descricao', 'cta_curto', 'texto_botao', 'selo', 'imagem',
    'posicao', 'status', 'destaque', 'expira_em',
];
const CAMPOS_INTEIROS = ['promocao', 'posicao', 'destaque'];
const CAMPOS_OBRIGATORIOS = ['titulo', 'slug', 'url', 'tipo', 'secao_publica', 'status'];

// Producao so recebe o que ja passou por stage (mesma regra do content-sync).
const ORIGEM_PERMITIDA = ['stage' => 'local', 'production' => 'stage'];

const SITE_URL = [
    'stage' => 'https://estrategianerd.com.br/stage',
    'production' => 'https://estrategianerd.com.br',
];

function printUsage(): void
{
    echo "Uso: php scripts/en-links-sync.php --source=local|stage --target=stage|production [--dry-run]\n";
    echo "  local -> stage e stage -> production. --dry-run mostra o que mudaria sem gravar nada.\n";
}

/**
 * @param array<string, mixed> $db
 */
function conectar(array $db): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', (string) $db['host'], (string) ($db['port'] ?? '3306'), (string) $db['database']);

    return new PDO($dsn, (string) $db['username'], (string) ($db['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function normalizar(string $campo, mixed $valor): ?string
{
    if (in_array($campo, CAMPOS_INTEIROS, true)) {
        return (string) (int) $valor;
    }
    if ($valor === null) {
        return in_array($campo, CAMPOS_OBRIGATORIOS, true) ? '' : null;
    }
    $texto = (string) $valor;
    if (!in_array($campo, CAMPOS_OBRIGATORIOS, true) && trim($texto) === '') {
        return null;
    }

    return $texto;
}

function caminhoUpload(?string $imagem): ?string
{
    $imagem = ltrim(str_replace('\\', '/', trim((string) $imagem)), '/');
    if (!str_starts_with($imagem, 'uploads/') || str_contains($imagem, '..')) {
        return null;
    }

    return $imagem;
}

/**
 * @param array<string, mixed> $config
 */
function conectarFtp(array $config): \FTP\Connection
{
    foreach (['host', 'port', 'username', 'password', 'root'] as $obrigatorio) {
        if (trim((string) ($config[$obrigatorio] ?? '')) === '') {
            throw new RuntimeException('Configuracao FTP incompleta: faltando ' . $obrigatorio);
        }
    }
    $ftp = @ftp_connect((string) $config['host'], (int) $config['port'], 30);
    if ($ftp === false) {
        throw new RuntimeException('Nao foi possivel conectar ao FTP.');
    }
    if (!@ftp_login($ftp, (string) $config['username'], (string) $config['password'])) {
        ftp_close($ftp);
        throw new RuntimeException('Falha de login no FTP.');
    }
    ftp_pasv($ftp, (bool) ($config['passive'] ?? true));

    return $ftp;
}

function garantirPastaRemota(\FTP\Connection $ftp, string $pasta): void
{
    $atual = '';
    foreach (array_filter(explode('/', trim($pasta, '/')), static fn (string $p): bool => $p !== '') as $parte) {
        $atual .= '/' . $parte;
        @ftp_mkdir($ftp, $atual);
    }
}

function capaNoAr(string $url): bool
{
    $ch = curl_init($url);
    if ($ch === false) {
        return false;
    }
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 EstrategiaNerd-links-sync',
    ]);
    curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tipo = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    return $codigo === 200 && str_starts_with($tipo, 'image/');
}

$opcoes = getopt('', ['source:', 'target:', 'dry-run', 'help']);
$origem = strtolower(trim((string) ($opcoes['source'] ?? '')));
$destino = strtolower(trim((string) ($opcoes['target'] ?? '')));
$dryRun = isset($opcoes['dry-run']);

if (isset($opcoes['help']) || !isset(ORIGEM_PERMITIDA[$destino]) || ORIGEM_PERMITIDA[$destino] !== $origem) {
    printUsage();
    if (!isset($opcoes['help'])) {
        fwrite(STDERR, "[ERRO] Combinacao nao permitida. Use local -> stage ou stage -> production.\n");
    }
    exit(isset($opcoes['help']) ? 0 : 1);
}

$config = require dirname(__DIR__) . '/config/content-sync.php';
$perfilOrigem = (array) ($config['profiles'][$origem] ?? []);
$perfilDestino = (array) ($config['profiles'][$destino] ?? []);

// Mesma trava do content-sync: impede rodar junto com outra rotina de conteudo.
$raizPacotes = rtrim((string) ($config['package_root'] ?? ''), '\\/');
if ($raizPacotes === '' || !is_dir($raizPacotes)) {
    fwrite(STDERR, "[ERRO] Pasta de pacotes do content-sync nao encontrada (trava).\n");
    exit(1);
}
$caminhoTrava = $raizPacotes . DIRECTORY_SEPARATOR . '.content-sync-running.lock.json';
$trava = fopen($caminhoTrava, 'c+');
if ($trava === false || !flock($trava, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "[ERRO] Ja existe uma rotina de conteudo em execucao. Nada foi feito.\n");
    exit(1);
}
ftruncate($trava, 0);
fwrite($trava, (string) json_encode(['operation' => 'links-sync', 'profile' => $destino, 'started_at' => date('c'), 'pid' => getmypid()]));
fflush($trava);

$codigoSaida = 0;
try {
    $dbOrigem = conectar((array) $perfilOrigem['database']);
    $dbDestino = conectar((array) $perfilDestino['database']);

    $origemLinks = $dbOrigem->query('SELECT ' . implode(', ', CAMPOS) . ' FROM links ORDER BY posicao ASC, id ASC')->fetchAll();
    $destinoLinks = [];
    foreach ($dbDestino->query('SELECT id, ' . implode(', ', CAMPOS) . ' FROM links')->fetchAll() as $linha) {
        $destinoLinks[(string) $linha['slug']] = $linha;
    }

    $criar = [];
    $atualizar = [];
    $iguais = 0;
    $slugsOrigem = [];
    foreach ($origemLinks as $link) {
        $slug = (string) $link['slug'];
        if ($slug === '') {
            continue;
        }
        $slugsOrigem[$slug] = true;
        $dados = [];
        foreach (CAMPOS as $campo) {
            $dados[$campo] = normalizar($campo, $link[$campo] ?? null);
        }
        if (!isset($destinoLinks[$slug])) {
            $criar[] = $dados;
            continue;
        }
        $diferencas = [];
        foreach (CAMPOS as $campo) {
            $atual = normalizar($campo, $destinoLinks[$slug][$campo] ?? null);
            if ($atual !== $dados[$campo]) {
                $diferencas[$campo] = [$atual, $dados[$campo]];
            }
        }
        if ($diferencas === []) {
            $iguais++;
        } else {
            $atualizar[] = ['id' => (int) $destinoLinks[$slug]['id'], 'dados' => $dados, 'diferencas' => $diferencas];
        }
    }
    $soNoDestino = array_values(array_diff(array_keys($destinoLinks), array_keys($slugsOrigem)));

    echo "== links: {$origem} -> {$destino}" . ($dryRun ? ' (DRY-RUN)' : '') . " ==\n";
    echo 'Origem: ' . count($origemLinks) . ' | destino: ' . count($destinoLinks) . ' | criar: ' . count($criar)
        . ' | atualizar: ' . count($atualizar) . ' | iguais: ' . $iguais . ' | so no destino (intocados): ' . count($soNoDestino) . "\n";
    foreach ($criar as $dados) {
        echo "  + {$dados['slug']} [{$dados['status']}] " . ($dados['subgrupo_publico'] ?? $dados['secao_publica']) . "\n";
    }
    foreach ($atualizar as $item) {
        echo "  ~ #{$item['id']} {$item['dados']['slug']}\n";
        foreach ($item['diferencas'] as $campo => [$antes, $depois]) {
            echo "      {$campo}: " . mb_substr((string) ($antes ?? '(vazio)'), 0, 60) . ' -> ' . mb_substr((string) ($depois ?? '(vazio)'), 0, 60) . "\n";
        }
    }
    foreach ($soNoDestino as $slug) {
        echo "  = {$slug} (existe so no destino, nao sera alterado)\n";
    }

    if ($dryRun || ($criar === [] && $atualizar === [])) {
        echo $dryRun ? "\n[DRY-RUN] Nada foi gravado.\n" : "\nNada a fazer.\n";
        return;
    }

    $marca = date('Ymd-His');
    $pastaBackup = dirname(__DIR__) . '/storage/backups/links-sync';
    if (!is_dir($pastaBackup) && !mkdir($pastaBackup, 0775, true) && !is_dir($pastaBackup)) {
        throw new RuntimeException('Nao foi possivel criar a pasta de backup.');
    }
    $arquivoBackup = "{$pastaBackup}/{$marca}-{$destino}-links-antes.json";
    file_put_contents($arquivoBackup, json_encode($dbDestino->query('SELECT * FROM links ORDER BY id')->fetchAll(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo "\nBackup do destino: {$arquivoBackup}\n";

    // 1) Capas primeiro: se alguma falhar, o banco nao e tocado.
    $capas = [];
    foreach (array_merge($criar, array_column($atualizar, 'dados')) as $dados) {
        $caminho = caminhoUpload($dados['imagem']);
        if ($caminho !== null) {
            $capas[$caminho] = true;
        }
    }
    $capas = array_keys($capas);
    echo 'Enviando ' . count($capas) . " capas...\n";

    $uploadsOrigem = (array) ($perfilOrigem['uploads'] ?? []);
    $uploadsDestino = (array) ($perfilDestino['uploads'] ?? []);
    $ftpDestino = conectarFtp($uploadsDestino);
    $ftpOrigem = strtolower((string) ($uploadsOrigem['mode'] ?? 'local')) === 'ftp' ? conectarFtp($uploadsOrigem) : null;
    $temporaria = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'en-links-sync-' . bin2hex(random_bytes(4));
    mkdir($temporaria, 0777, true);
    try {
        $raizDestino = rtrim((string) $uploadsDestino['root'], '/');
        foreach ($capas as $caminho) {
            $relativo = substr($caminho, strlen('uploads/'));
            if ($ftpOrigem !== null) {
                $arquivoLocal = $temporaria . DIRECTORY_SEPARATOR . md5($caminho);
                if (!@ftp_get($ftpOrigem, $arquivoLocal, rtrim((string) $uploadsOrigem['root'], '/') . '/' . $relativo, FTP_BINARY)) {
                    throw new RuntimeException("Capa nao encontrada na origem: {$caminho}");
                }
            } else {
                $arquivoLocal = rtrim((string) $uploadsOrigem['path'], '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativo);
                if (!is_file($arquivoLocal)) {
                    throw new RuntimeException("Capa nao encontrada na origem: {$caminho}");
                }
            }
            $remoto = $raizDestino . '/' . $relativo;
            garantirPastaRemota($ftpDestino, dirname($remoto));
            if (!@ftp_put($ftpDestino, $remoto, $arquivoLocal, FTP_BINARY)) {
                throw new RuntimeException("Falha ao enviar a capa: {$caminho}");
            }
        }
    } finally {
        ftp_close($ftpDestino);
        if ($ftpOrigem !== null) {
            ftp_close($ftpOrigem);
        }
        array_map('unlink', glob($temporaria . DIRECTORY_SEPARATOR . '*') ?: []);
        @rmdir($temporaria);
    }

    $falhas = array_values(array_filter($capas, static fn (string $c): bool => !capaNoAr(SITE_URL[$destino] . '/' . $c)));
    if ($falhas !== []) {
        throw new RuntimeException('Capas sem HTTP 200 no destino (banco nao alterado): ' . implode(', ', array_slice($falhas, 0, 5)));
    }
    echo 'Capas no ar: ' . count($capas) . "/" . count($capas) . "\n";

    // 2) Banco: so pelo slug, numa transacao. Conexao nova porque o MySQL da hospedagem
    // derruba conexoes ociosas em poucos segundos (wait_timeout) durante o envio das capas.
    $dbDestino = conectar((array) $perfilDestino['database']);
    $colunas = implode(', ', CAMPOS);
    $marcadores = implode(', ', array_map(static fn (string $c): string => ':' . $c, CAMPOS));
    $sets = implode(', ', array_map(static fn (string $c): string => "{$c} = :{$c}", CAMPOS));
    $insert = $dbDestino->prepare("INSERT INTO links ({$colunas}) VALUES ({$marcadores})");
    $update = $dbDestino->prepare("UPDATE links SET {$sets} WHERE id = :id");
    $criados = [];
    $atualizados = [];
    $dbDestino->beginTransaction();
    try {
        foreach ($criar as $dados) {
            $insert->execute($dados);
            $criados[(int) $dbDestino->lastInsertId()] = $dados['slug'];
        }
        foreach ($atualizar as $item) {
            $update->execute($item['dados'] + ['id' => $item['id']]);
            $atualizados[$item['id']] = $item['dados']['slug'];
        }
        $dbDestino->commit();
    } catch (Throwable $e) {
        $dbDestino->rollBack();
        throw $e;
    }

    $arquivoLog = "{$pastaBackup}/{$marca}-{$destino}-log.json";
    file_put_contents($arquivoLog, json_encode([
        'origem' => $origem, 'destino' => $destino, 'executado_em' => date('c'), 'backup' => $arquivoBackup,
        'criados' => $criados, 'atualizados' => $atualizados, 'capas' => $capas,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo 'Banco: ' . count($criados) . ' criados, ' . count($atualizados) . " atualizados.\nLog: {$arquivoLog}\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[ERRO] ' . $e->getMessage() . "\n");
    $codigoSaida = 1;
} finally {
    flock($trava, LOCK_UN);
    fclose($trava);
    @unlink($caminhoTrava);
}

exit($codigoSaida);
