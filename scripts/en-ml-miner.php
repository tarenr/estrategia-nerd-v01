<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Repositories\LinkRepository;
use App\Services\Affiliate\MercadoLivreExtractorService;

function printMinerUsage(): void
{
    echo "========================================================\n";
    echo "  Estratégia Nerd - Minerador de Produtos Mercado Livre\n";
    echo "========================================================\n";
    echo "Uso:\n";
    echo "  php scripts/en-ml-miner.php [--query=\"...\"] [--urls=\"...\"] [opcoes]\n\n";
    echo "Opcoes:\n";
    echo "  --query=\"...\"          Termo de busca direta no Mercado Livre\n";
    echo "  --urls=\"...\"           Lista de URLs separadas por virgula (ideal para alimentar via IA)\n";
    echo "  --file=\"...\"           Arquivo texto contendo uma URL do Mercado Livre por linha\n";
    echo "  --min-rating=4.8       Nota minima para corte de qualidade (padrao: 4.8)\n";
    echo "  --limit=10             Maximo de produtos a retornar (padrao: 10)\n";
    echo "  --json                 Formato de saida em JSON estruturado (ideal para IAs/Agentes)\n";
    echo "  --auto-import          Importa e cadastra automaticamente os aprovados no banco\n";
    echo "  --subgrupo=\"...\"       Subgrupo na Central Nerd em caso de --auto-import (padrao: 'Setup Gamer')\n";
    echo "  --affiliate-tag=\"...\"  Tag de afiliado para auto-import (padrao: 'estrategianerd')\n";
    echo "\nExemplos:\n";
    echo "  php scripts/en-ml-miner.php --query=\"controle hall effect\" --min-rating=4.8 --json\n";
    echo "  php scripts/en-ml-miner.php --urls=\"https://meli.la/2D5Y6ir,https://meli.la/1XdYbzM\" --json\n";
    echo "========================================================\n";
}

$options = getopt('', [
    'query:',
    'urls:',
    'file:',
    'min-rating:',
    'limit:',
    'json',
    'auto-import',
    'subgrupo:',
    'affiliate-tag:',
    'help',
]);

if (isset($options['help']) || (empty($options['query']) && empty($options['urls']) && empty($options['file']))) {
    printMinerUsage();
    exit(isset($options['help']) ? 0 : 1);
}

$query = isset($options['query']) ? trim((string) $options['query']) : '';
$urlsInput = isset($options['urls']) ? trim((string) $options['urls']) : '';
$fileInput = isset($options['file']) ? trim((string) $options['file']) : '';
$minRating = isset($options['min-rating']) ? (float) $options['min-rating'] : 4.8;
$limit = isset($options['limit']) ? max(1, (int) $options['limit']) : 10;
$asJson = isset($options['json']);
$autoImport = isset($options['auto-import']);
$subgrupo = isset($options['subgrupo']) ? trim((string) $options['subgrupo']) : 'Setup Gamer';
$affiliateTag = isset($options['affiliate-tag']) ? trim((string) $options['affiliate-tag']) : 'estrategianerd';

$extractor = new MercadoLivreExtractorService(null, dirname(__DIR__) . '/public');
$candidateUrls = [];

if ($urlsInput !== '') {
    $candidateUrls = array_filter(array_map('trim', explode(',', $urlsInput)));
} elseif ($fileInput !== '' && is_file($fileInput)) {
    $lines = file($fileInput, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        $candidateUrls = array_filter(array_map('trim', $lines));
    }
} elseif ($query !== '') {
    // Tenta varredura na busca publica
    $querySlug = rawurlencode($query);
    $searchUrl = "https://lista.mercadolivre.com.br/{$querySlug}";
    $html = $extractor->fetchPage($searchUrl);

    if ($html !== null && !str_contains($html, 'suspicious-traffic')) {
        preg_match_all('/<a[^>]+href=["\'](https:\/\/[^"\']+)["\'][^>]*class=["\'][^"\']*(?:ui-search-link|poly-component__title)[^"\']*/i', $html, $m);
        if (!empty($m[1])) {
            $candidateUrls = array_slice(array_unique($m[1]), 0, 15);
        }
    }
}

if ($candidateUrls === [] && $query !== '') {
    // Mensagem informativa e sugestao de uso para IAs
    if ($asJson) {
        echo json_encode([
            'ok' => false,
            'notice' => 'O Mercado Livre ativou desafio anti-bot na busca publica direta.',
            'dica' => 'Passe a lista de URLs mineradas via --urls="url1,url2,..." para que o minerador extraia as fichas completas, aplique o corte 4.8+ e cadastre.',
            'query' => $query,
            'produtos' => [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "[AVISO] A busca direta por termo encontrou desafio anti-bot do Mercado Livre.\n";
        echo "Dica: Como IA/Agente, utilize a ferramenta de busca para obter as URLs dos produtos e execute:\n";
        echo "php scripts/en-ml-miner.php --urls=\"https://...\" --json\n";
    }
    exit(0);
}

if (!$asJson) {
    echo "== Minerando e avaliando produtos (" . count($candidateUrls) . " candidatos) ==\n";
    echo "Corte Minimo de Avaliacao: ⭐ {$minRating} / 5.0\n\n";
}

$extractedItems = [];
foreach ($candidateUrls as $url) {
    if (!$asJson) {
        echo "Analisando: {$url} ...\n";
    }
    $prod = $extractor->extractProduct($url);
    if ($prod !== null && $prod['titulo'] !== '') {
        $extractedItems[] = $prod;
    }
}

// Filtra produtos pelo corte
$approved = [];
foreach ($extractedItems as $item) {
    $hasGoodRating = $item['rating'] !== null && $item['rating'] >= $minRating;
    $isBestSeller = in_array('Mais Vendido', (array) $item['selos'], true);

    // Se nao possui rating explicito na pagina (ex. redirect), aceita se tiver selo ou for candidato analisado
    if ($hasGoodRating || $isBestSeller || $item['rating'] === null) {
        $approved[] = $item;
    }
}

usort($approved, static function (array $a, array $b): int {
    $rA = (float) ($a['rating'] ?? 0);
    $rB = (float) ($b['rating'] ?? 0);
    if ($rA !== $rB) {
        return $rB <=> $rA;
    }
    return ((int) ($b['rating_count'] ?? 0)) <=> ((int) ($a['rating_count'] ?? 0));
});

$approved = array_slice($approved, 0, $limit);

if ($asJson) {
    echo json_encode([
        'ok' => true,
        'total_analisados' => count($extractedItems),
        'total_aprovados' => count($approved),
        'produtos' => $approved,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

echo "\n=== Aprovados no corte (" . count($approved) . ") ===\n";
$repo = new LinkRepository($GLOBALS['pdo']);

foreach ($approved as $idx => $prod) {
    $num = $idx + 1;
    echo "--------------------------------------------------------\n";
    echo "#{$num} - {$prod['titulo']}\n";
    echo "     Preco: R$ " . number_format((float) $prod['preco'], 2, ',', '.') . "\n";
    echo "     Nota: " . ($prod['rating'] !== null ? "⭐ {$prod['rating']} / 5.0" : "Nota alta") . (!empty($prod['rating_count']) ? " ({$prod['rating_count']} avaliacoes)" : "") . "\n";
    if (!empty($prod['selos'])) {
        echo "     Selos: " . implode(' | ', (array) $prod['selos']) . "\n";
    }
    echo "     URL: {$prod['final_url']}\n";

    if ($autoImport) {
        echo "     -> [AUTO-IMPORT] Importando para o portal...\n";
        $slugBase = strtolower($prod['titulo']);
        $slugBase = (string) preg_replace('/[^a-z0-9]+/i', '-', $slugBase);
        $slug = $repo->nextAvailableSlug($slugBase);
        $targetImg = "uploads/links/{$slug}/capa.webp";

        $imgRes = null;
        if (!empty($prod['imagem_url'])) {
            $imgRes = $extractor->standardizeImage((string) $prod['imagem_url'], $targetImg, 1080, 85);
        }

        $id = $repo->insertAdmin([
            'titulo' => $prod['titulo'],
            'slug' => $slug,
            'url' => $prod['final_url'],
            'tipo' => 'produto',
            'promocao' => 0,
            'desconto_percentual' => $prod['desconto_percentual'] ?? null,
            'desconto_contexto' => 'no Mercado Livre',
            'codigo_cupom' => null,
            'secao_publica' => 'produtos',
            'subgrupo_publico' => $subgrupo,
            'descricao' => !empty($prod['descricao']) ? mb_substr((string) $prod['descricao'], 0, 240) : 'Produto campeao de avaliacoes no Mercado Livre.',
            'cta_curto' => in_array('Mais Vendido', (array) $prod['selos'], true) ? 'Mais Vendido' : 'Destaque',
            'texto_botao' => 'Ver no Mercado Livre',
            'selo' => $prod['rating'] !== null ? "⭐ {$prod['rating']} / 5.0" : 'Mais Vendido',
            'imagem' => ($imgRes['ok'] ?? false) === true ? $imgRes['relative_path'] : $targetImg,
            'posicao' => 0,
            'status' => 'ativo',
            'destaque' => 0,
            'expira_em' => null,
            'observacao_status' => 'Minerado via script',
        ]);
        echo "     -> [OK] Cadastrado com ID #{$id} (/link/{$slug})\n";
    }
}
echo "--------------------------------------------------------\n";
