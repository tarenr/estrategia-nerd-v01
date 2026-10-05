<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Repositories\LinkRepository;
use App\Services\Affiliate\MercadoLivreExtractorService;

// Importador de produtos de afiliados a partir de fichas JSON ja conferidas (IMP-029).
// Nao le a pagina da loja: os dados chegam prontos na ficha (coleta assistida ou API).

const FICHA_VERSAO = 1;

const LOJAS = [
    'shopee' => [
        'nome' => 'Shopee',
        'hosts_link' => ['s.shopee.com.br', 'shope.ee'],
        'host_destino' => 'shopee.com.br',
        'caminho_destino' => null,
        'texto_botao' => 'Ver na Shopee',
        'desconto_contexto' => 'na Shopee',
    ],
    'mercadolivre' => [
        'nome' => 'Mercado Livre',
        'hosts_link' => ['meli.la'],
        'host_destino' => 'mercadolivre.com.br',
        'caminho_destino' => null,
        'texto_botao' => 'Ver no Mercado Livre',
        'desconto_contexto' => 'no Mercado Livre',
    ],
    'aliexpress' => [
        'nome' => 'AliExpress',
        'hosts_link' => ['s.click.aliexpress.com'],
        'host_destino' => 'aliexpress.com',
        // Link curto invalido cai na pagina inicial (best.aliexpress.com); so produto e aceito.
        'caminho_destino' => '#^/item/\d+\.html$#',
        'texto_botao' => 'Ver na AliExpress',
        'desconto_contexto' => 'na AliExpress',
    ],
];

const HOSTS_IMAGEM = ['susercontent.com', 'mlstatic.com', 'aliexpress-media.com', 'alicdn.com'];
const STATUS_PERMITIDOS = ['ativo', 'oculto'];

function printUsage(): void
{
    echo "========================================================\n";
    echo "  Estratégia Nerd - Importador de Afiliados por Ficha JSON\n";
    echo "========================================================\n";
    echo "Uso:\n";
    echo "  php scripts/en-affiliate-import.php --file=\"fichas.json\" [opcoes]\n\n";
    echo "Opcoes:\n";
    echo "  --file=\"...\"        Arquivo JSON: {\"versao\":1,\"produtos\":[{...}]} (obrigatorio)\n";
    echo "  --dry-run           Valida tudo e mostra a previa, sem gravar nada (nem capa)\n";
    echo "  --allow-duplicate   Permite link de afiliado ja cadastrado ou repetido no lote\n";
    echo "\nCampos de cada produto:\n";
    echo "  loja (shopee|mercadolivre|aliexpress), titulo, url (link de afiliado), imagem (URL),\n";
    echo "  subgrupo, posicao, status (ativo|oculto); opcionais: cta_curto, selo, descricao, origem\n";
    echo "========================================================\n";
}

function hostMatches(string $host, string $suffix): bool
{
    return $host === $suffix || str_ends_with($host, '.' . $suffix);
}

function slugBase(string $titulo): string
{
    $slug = mb_strtolower($titulo, 'UTF-8');
    $slug = strtr($slug, [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ]);
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
    $slug = mb_substr($slug, 0, 150);

    return $slug !== '' ? rtrim($slug, '-') : 'produto';
}

/**
 * @param array<string, mixed> $item
 * @return array{erros: list<string>, dados: array<string, mixed>}
 */
function validarItem(array $item, MercadoLivreExtractorService $resolver): array
{
    $erros = [];
    $texto = static fn (string $campo): string => trim((string) ($item[$campo] ?? ''));

    $loja = $texto('loja');
    if (!isset(LOJAS[$loja])) {
        return ['erros' => ["loja invalida: '{$loja}' (use " . implode(', ', array_keys(LOJAS)) . ')'], 'dados' => []];
    }
    $config = LOJAS[$loja];

    $dados = [
        'loja' => $loja,
        'titulo' => $texto('titulo'),
        'url' => $texto('url'),
        'imagem' => $texto('imagem'),
        'subgrupo' => $texto('subgrupo'),
        'cta_curto' => $texto('cta_curto'),
        'selo' => $texto('selo'),
        'descricao' => $texto('descricao'),
        'status' => $texto('status'),
        'posicao' => $item['posicao'] ?? null,
        'origem' => $texto('origem'),
        'url_final' => '',
    ];

    $limites = ['titulo' => 150, 'subgrupo' => 80, 'cta_curto' => 120, 'selo' => 60, 'descricao' => 255];
    foreach ($limites as $campo => $max) {
        if (mb_strlen((string) $dados[$campo]) > $max) {
            $erros[] = "{$campo} passa de {$max} caracteres";
        }
    }
    if ($dados['titulo'] === '') {
        $erros[] = 'titulo vazio';
    }
    if ($dados['subgrupo'] === '') {
        $erros[] = 'subgrupo vazio (grupo exibido na Central Nerd)';
    }
    if (!in_array($dados['status'], STATUS_PERMITIDOS, true)) {
        $erros[] = "status invalido: '{$dados['status']}' (use ativo ou oculto)";
    }
    if (!is_int($dados['posicao']) || $dados['posicao'] < 0) {
        $erros[] = 'posicao precisa ser inteiro >= 0';
    }

    $hostImagem = strtolower((string) parse_url((string) $dados['imagem'], PHP_URL_HOST));
    $imagemOk = str_starts_with((string) $dados['imagem'], 'https://')
        && array_filter(HOSTS_IMAGEM, static fn (string $s): bool => hostMatches($hostImagem, $s)) !== [];
    if (!$imagemOk) {
        $erros[] = "imagem precisa ser https em " . implode(' ou ', HOSTS_IMAGEM);
    }

    $hostLink = strtolower((string) parse_url((string) $dados['url'], PHP_URL_HOST));
    if (!str_starts_with((string) $dados['url'], 'https://') || !in_array($hostLink, $config['hosts_link'], true)) {
        $erros[] = 'url precisa ser link de afiliado https em ' . implode(' ou ', $config['hosts_link']);
    } else {
        // Link curto invalido cai numa pagina de erro que responde 200; por isso confere o destino.
        $final = $resolver->resolveFinalUrl((string) $dados['url']);
        $dados['url_final'] = $final;
        $hostFinal = strtolower((string) parse_url($final, PHP_URL_HOST));
        $caminhoFinal = (string) parse_url($final, PHP_URL_PATH);
        $caminhoOk = $config['caminho_destino'] === null || preg_match($config['caminho_destino'], $caminhoFinal) === 1;
        if (str_contains($final, 'error_page') || !hostMatches($hostFinal, $config['host_destino']) || !$caminhoOk) {
            $erros[] = 'link de afiliado nao abre um destino valido: ' . mb_substr($final, 0, 120);
        }
    }

    return ['erros' => $erros, 'dados' => $dados];
}

$options = getopt('', ['file:', 'dry-run', 'allow-duplicate', 'help']);

if (isset($options['help']) || empty($options['file'])) {
    printUsage();
    exit(isset($options['help']) ? 0 : 1);
}

$dryRun = isset($options['dry-run']);
$allowDuplicate = isset($options['allow-duplicate']);
$arquivo = (string) $options['file'];

$conteudo = is_file($arquivo) ? file_get_contents($arquivo) : false;
$ficha = $conteudo !== false ? json_decode($conteudo, true) : null;
if (!is_array($ficha) || ($ficha['versao'] ?? null) !== FICHA_VERSAO || !is_array($ficha['produtos'] ?? null) || $ficha['produtos'] === []) {
    fwrite(STDERR, "[ERRO] Ficha invalida: esperado {\"versao\":" . FICHA_VERSAO . ",\"produtos\":[...]} com ao menos 1 produto.\n");
    exit(1);
}

$pdo = $GLOBALS['pdo'];
$repo = new LinkRepository($pdo);
$extractor = new MercadoLivreExtractorService(null, dirname(__DIR__) . '/public');
$urlExiste = $pdo->prepare('SELECT id FROM links WHERE url = :url LIMIT 1');

echo "== Fase 1: validacao (" . count($ficha['produtos']) . " produtos) ==\n";

$validos = [];
$urlsLote = [];
$slugsLote = [];
$totalErros = 0;

foreach ($ficha['produtos'] as $idx => $item) {
    $num = (int) $idx + 1;
    $resultado = is_array($item) ? validarItem($item, $extractor) : ['erros' => ['item nao e um objeto'], 'dados' => []];
    $erros = $resultado['erros'];
    $dados = $resultado['dados'];

    if ($dados !== []) {
        $urlExiste->execute(['url' => $dados['url']]);
        $existente = $urlExiste->fetchColumn();
        if (!$allowDuplicate && $existente !== false) {
            $erros[] = "link ja cadastrado como #{$existente} (use --allow-duplicate se for intencional)";
        }
        if (!$allowDuplicate && in_array($dados['url'], $urlsLote, true)) {
            $erros[] = 'link repetido dentro do lote';
        }
        $urlsLote[] = $dados['url'];

        $base = slugBase((string) $dados['titulo']);
        $slug = $repo->nextAvailableSlug($base);
        $n = 2;
        while (in_array($slug, $slugsLote, true)) {
            $slug = $repo->nextAvailableSlug($base . '-' . $n);
            $n++;
        }
        $slugsLote[] = $slug;
        $dados['slug'] = $slug;
    }

    $titulo = (string) ($dados['titulo'] ?? '?');
    if ($erros !== []) {
        $totalErros++;
        echo "[{$num}] REPROVADO: {$titulo}\n";
        foreach ($erros as $erro) {
            echo "      - {$erro}\n";
        }
        continue;
    }

    echo "[{$num}] OK: {$titulo}\n";
    echo "      loja={$dados['loja']} grupo=\"{$dados['subgrupo']}\" posicao={$dados['posicao']} status={$dados['status']}\n";
    echo "      slug={$dados['slug']} | destino: " . mb_substr((string) $dados['url_final'], 0, 90) . "\n";
    $validos[] = $dados;
}

if ($totalErros > 0) {
    echo "\n[ERRO] {$totalErros} produto(s) reprovado(s). Nada foi gravado.\n";
    exit(1);
}

if ($dryRun) {
    echo "\n[DRY-RUN] " . count($validos) . " produto(s) validos. Nenhuma alteracao gravada (banco e capas intactos).\n";
    exit(0);
}

echo "\n== Fase 2: cadastro ==\n";
$falhas = 0;

foreach ($validos as $idx => $dados) {
    $num = $idx + 1;
    $config = LOJAS[$dados['loja']];
    $alvoCapa = "uploads/links/{$dados['slug']}/capa.webp";

    $capa = $extractor->standardizeImage((string) $dados['imagem'], $alvoCapa, 1080, 85);
    if (($capa['ok'] ?? false) !== true) {
        $falhas++;
        echo "[{$num}] FALHA na capa ({$dados['titulo']}): " . ($capa['error'] ?? 'desconhecido') . " — nao cadastrado.\n";
        continue;
    }

    try {
        $id = $repo->insertAdmin([
            'titulo' => $dados['titulo'],
            'slug' => $dados['slug'],
            'url' => $dados['url'],
            'tipo' => 'produto',
            'promocao' => 0,
            'desconto_percentual' => null,
            'desconto_contexto' => $config['desconto_contexto'],
            'codigo_cupom' => null,
            'secao_publica' => 'produtos',
            'subgrupo_publico' => $dados['subgrupo'],
            'descricao' => $dados['descricao'] !== '' ? $dados['descricao'] : null,
            'cta_curto' => $dados['cta_curto'] !== '' ? $dados['cta_curto'] : null,
            'texto_botao' => $config['texto_botao'],
            'selo' => $dados['selo'] !== '' ? $dados['selo'] : null,
            'imagem' => $capa['relative_path'],
            'posicao' => $dados['posicao'],
            'status' => $dados['status'],
            'destaque' => 0,
            'expira_em' => null,
            'observacao_status' => 'Importado via ficha JSON (' . $config['nome'] . ')',
        ]);
    } catch (Throwable $e) {
        $falhas++;
        $absoluta = (string) ($capa['absolute_path'] ?? '');
        if ($absoluta !== '' && is_file($absoluta)) {
            @unlink($absoluta);
            @rmdir(dirname($absoluta));
        }
        echo "[{$num}] FALHA no cadastro ({$dados['titulo']}): " . $e->getMessage() . " — capa removida.\n";
        continue;
    }

    echo "[{$num}] OK #{$id}: {$dados['titulo']} | /link/{$dados['slug']} | capa {$capa['kb']} KB\n";
}

echo "\n" . (count($validos) - $falhas) . " cadastrado(s), {$falhas} falha(s).\n";
exit($falhas > 0 ? 1 : 0);
