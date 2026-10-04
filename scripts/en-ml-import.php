<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Repositories\LinkRepository;
use App\Services\Affiliate\MercadoLivreExtractorService;

function printUsage(): void
{
    echo "========================================================\n";
    echo "  Estratégia Nerd - Importador de Produtos Mercado Livre\n";
    echo "========================================================\n";
    echo "Uso:\n";
    echo "  php scripts/en-ml-import.php --url=\"<URL_DO_MERCADO_LIVRE_OU_MELI_LA>\" [opcoes]\n\n";
    echo "Opcoes:\n";
    echo "  --url=\"...\"             URL do anuncio no Mercado Livre ou link meli.la (obrigatorio)\n";
    echo "  --affiliate-url=\"...\"   Link curto de afiliado oficial (ex: https://meli.la/2oFrmbH)\n";
    echo "  --affiliate-tag=\"...\"   Tag de afiliado para anexar a URL (ex: estrategianerd)\n";
    echo "  --subgrupo=\"...\"        Subgrupo/Categoria na Central Nerd (padrao: 'Setup Gamer')\n";
    echo "  --title=\"...\"           Titulo customizado (substitui o titulo extraido)\n";
    echo "  --image=\"...\"           Caminho de imagem customizada ou gerada previamente para usar de capa\n";
    echo "  --promocao              Marca o produto como Promocao/Oferta (topo da lista)\n";
    echo "  --destaque              Define como o destaque principal (1 unico item)\n";
    echo "  --dry-run               Apenas extrai e exibe os dados sem gravar no banco\n";
    echo "\nExemplo:\n";
    echo "  php scripts/en-ml-import.php --url=\"https://produto.mercadolivre.com.br/MLB-123\" --subgrupo=\"Setup Gamer\"\n";
    echo "========================================================\n";
}

$options = getopt('', [
    'url:',
    'affiliate-url:',
    'affiliate-tag:',
    'subgrupo:',
    'title:',
    'image:',
    'promocao',
    'destaque',
    'dry-run',
    'help',
]);

if (isset($options['help']) || empty($options['url'])) {
    printUsage();
    exit(isset($options['help']) ? 0 : 1);
}

$inputUrl = trim((string) $options['url']);
$affiliateUrl = isset($options['affiliate-url']) ? trim((string) $options['affiliate-url']) : '';
$affiliateTag = isset($options['affiliate-tag']) ? trim((string) $options['affiliate-tag']) : 'estrategianerd';
$subgrupo = isset($options['subgrupo']) ? trim((string) $options['subgrupo']) : 'Setup Gamer';
$customTitle = isset($options['title']) ? trim((string) $options['title']) : '';
$customImage = isset($options['image']) ? trim((string) $options['image']) : '';
$isPromo = isset($options['promocao']) ? 1 : 0;
$isDestaque = isset($options['destaque']) ? 1 : 0;
$dryRun = isset($options['dry-run']);

echo "== Iniciando extracao do produto... ==\n";
echo "URL informada: {$inputUrl}\n";

$extractor = new MercadoLivreExtractorService(null, dirname(__DIR__) . '/public');
$data = $extractor->extractProduct($inputUrl);

if ($data === null) {
    fwrite(STDERR, "[ERRO] Nao foi possivel extrair informacoes do produto na URL fornecida.\n");
    exit(1);
}

$titulo = $customTitle !== '' ? $customTitle : $data['titulo'];
if ($titulo === '') {
    $titulo = 'Produto Mercado Livre';
}

// Monta a URL de destino final do afiliado
$finalDestinationUrl = $inputUrl;
if ($affiliateUrl !== '') {
    $finalDestinationUrl = $affiliateUrl;
} elseif ($affiliateTag !== '' && !str_contains($finalDestinationUrl, 'matt_tool=')) {
    $separator = str_contains($finalDestinationUrl, '?') ? '&' : '?';
    $finalDestinationUrl .= $separator . 'tag=' . urlencode($affiliateTag);
}

// Gera slug amigavel
$pdo = $GLOBALS['pdo'];
$repo = new LinkRepository($pdo);

$slugBase = strtolower($titulo);
$slugBase = (string) preg_replace('/[áàãâä]/u', 'a', $slugBase);
$slugBase = (string) preg_replace('/[éèêë]/u', 'e', $slugBase);
$slugBase = (string) preg_replace('/[íìîï]/u', 'i', $slugBase);
$slugBase = (string) preg_replace('/[óòõôö]/u', 'o', $slugBase);
$slugBase = (string) preg_replace('/[úùûü]/u', 'u', $slugBase);
$slugBase = (string) preg_replace('/[ç]/u', 'c', $slugBase);
$slugBase = (string) preg_replace('/[^a-z0-9]+/i', '-', $slugBase);
$slugBase = trim($slugBase, '-');
if ($slugBase === '') {
    $slugBase = 'produto-ml';
}

$slug = $repo->nextAvailableSlug($slugBase);

echo "Titulo: {$titulo}\n";
echo "Slug gerado: {$slug}\n";
echo "Preco extraido: R$ " . number_format((float) $data['preco'], 2, ',', '.') . "\n";
if (!empty($data['desconto_percentual'])) {
    echo "Desconto: {$data['desconto_percentual']}\n";
}
if (!empty($data['rating'])) {
    echo "Avaliacao: {$data['rating']} / 5.0" . (!empty($data['rating_count']) ? " ({$data['rating_count']} avaliacoes)" : "") . "\n";
}
if (!empty($data['selos'])) {
    echo "Selos: " . implode(', ', (array) $data['selos']) . "\n";
}

// Processa a imagem padronizada 1080x1080 WebP
$targetRelativeImage = "uploads/links/{$slug}/capa.webp";
$imageSource = $customImage !== '' ? $customImage : (string) ($data['imagem_url'] ?? '');

$imageResult = null;
if ($imageSource !== '') {
    echo "Processando imagem padronizada (1080x1080 WebP)...\n";
    $imageResult = $extractor->standardizeImage($imageSource, $targetRelativeImage, 1080, 85);
    if (($imageResult['ok'] ?? false) === true) {
        echo "[OK] Capa salva: {$imageResult['relative_path']} ({$imageResult['kb']} KB)\n";
    } else {
        echo "[AVISO] Falha ao padronizar imagem: " . ($imageResult['error'] ?? 'desconhecido') . "\n";
    }
}

$seloBadge = null;
if (!empty($data['rating']) && (float) $data['rating'] >= 4.8) {
    $seloBadge = '⭐ ' . number_format((float) $data['rating'], 1) . ' / 5.0';
} elseif (!empty($data['selos'])) {
    $seloBadge = $data['selos'][0];
}

$ctaCurto = !empty($data['vendas_texto']) ? $data['vendas_texto'] : 'Oferta no Mercado Livre';
$descricao = !empty($data['descricao']) ? mb_substr($data['descricao'], 0, 240) . '...' : 'Confira a melhor oferta e avaliacoes de compradores.';

$payload = [
    'titulo' => $titulo,
    'slug' => $slug,
    'url' => $finalDestinationUrl,
    'tipo' => 'produto',
    'promocao' => $isPromo,
    'desconto_percentual' => $data['desconto_percentual'] ?? null,
    'desconto_contexto' => 'no Mercado Livre',
    'codigo_cupom' => null,
    'secao_publica' => 'produtos',
    'subgrupo_publico' => $subgrupo,
    'descricao' => $descricao,
    'cta_curto' => $ctaCurto,
    'texto_botao' => 'Ver no Mercado Livre',
    'selo' => $seloBadge,
    'imagem' => ($imageResult['ok'] ?? false) === true ? $imageResult['relative_path'] : $targetRelativeImage,
    'posicao' => 0,
    'status' => 'ativo',
    'destaque' => $isDestaque,
    'expira_em' => null,
    'observacao_status' => 'Importado via script Mercado Livre',
];

if ($dryRun) {
    echo "\n[DRY-RUN] Dados preparados para insercao:\n";
    print_r($payload);
    echo "\nNenhuma alteracao gravada no banco.\n";
    exit(0);
}

// Insercao no banco de dados
$id = $repo->insertAdmin($payload);

echo "\n========================================================\n";
echo " [SUCESSO] Produto cadastrado com sucesso! ID: #{$id}\n";
echo " Titulo: {$titulo}\n";
echo " Rota de redirecionamento interno: /link/{$slug}\n";
echo " Link na Central Nerd: /central-nerd#central-" . urlencode(strtolower(str_replace(' ', '-', $subgrupo))) . "\n";
echo " Capa salva: {$payload['imagem']}\n";
echo "========================================================\n";
