<?php
declare(strict_types=1);

/**
 * Teste de Regressão do Extrator e Minerador do Mercado Livre (IMP-027 / Tarefa #705)
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Services\Affiliate\MercadoLivreExtractorService;

echo "========================================================\n";
echo "  Teste de Regressão: Extrator e Minerador Mercado Livre\n";
echo "========================================================\n\n";

$extractor = new MercadoLivreExtractorService(null, dirname(__DIR__) . '/public');
$errors = [];

// ----------------------------------------------------
// Teste 1: Ryzen 5 7600X (meli.la/2CYn1Ws)
// ----------------------------------------------------
echo "[1/3] Testando Produto 1: Ryzen 5 7600X (meli.la/2CYn1Ws)...\n";
$p1 = $extractor->extractProduct('https://meli.la/2CYn1Ws');

if ($p1 === null) {
    $errors[] = 'Falha ao extrair produto 1 (retornou null).';
} else {
    echo "  - Titulo: {$p1['titulo']}\n";
    echo "  - Preco: R$ {$p1['preco']} (Original: R$ {$p1['preco_original']}) [{$p1['desconto_percentual']}]\n";
    echo "  - Rating: {$p1['rating']} | Vendas: {$p1['vendas_texto']}\n";
    echo "  - Selos: " . implode(', ', (array) $p1['selos']) . "\n";
    echo "  - Imagem: {$p1['imagem_url']}\n";

    if ((float) $p1['preco'] < 1400.0) {
        $errors[] = "Preco do produto 1 incorreto (esperado ~1459, obtido {$p1['preco']}). Possivel captura de carrossel!";
    }
    if ((float) $p1['rating'] !== 4.9) {
        $errors[] = "Rating do produto 1 incorreto (esperado 4.9, obtido {$p1['rating']}).";
    }
    if (in_array('Mais Vendido', (array) $p1['selos'], true)) {
        $errors[] = "Falso positivo: Produto 1 recebeu selo 'Mais Vendido' indevidamente!";
    }
    if (!in_array('Loja Oficial', (array) $p1['selos'], true)) {
        $errors[] = "Falso negativo: Produto 1 deveria conter selo 'Loja Oficial' (Chipart).";
    }
    if (!str_ends_with((string) $p1['imagem_url'], '-O.webp')) {
        $errors[] = "A imagem do produto 1 deveria estar com sufixo de alta definicao (-O.webp).";
    }
}

// ----------------------------------------------------
// Teste 2: SSD Kingston A400 (meli.la/2D5Y6ir)
// ----------------------------------------------------
echo "\n[2/3] Testando Produto 2: SSD Kingston A400 (meli.la/2D5Y6ir)...\n";
$p2 = $extractor->extractProduct('https://meli.la/2D5Y6ir');

if ($p2 === null) {
    $errors[] = 'Falha ao extrair produto 2 (retornou null).';
} else {
    echo "  - Titulo: {$p2['titulo']}\n";
    echo "  - Preco: R$ {$p2['preco']}\n";
    echo "  - Rating: {$p2['rating']} | Vendas: {$p2['vendas_texto']}\n";
    echo "  - Selos: " . implode(', ', (array) $p2['selos']) . "\n";

    if (!in_array('Mais Vendido', (array) $p2['selos'], true)) {
        $errors[] = "Falso negativo: Produto 2 deveria conter selo 'Mais Vendido'.";
    }
    if (in_array('Loja Oficial', (array) $p2['selos'], true)) {
        $errors[] = "Falso positivo: Produto 2 recebeu selo 'Loja Oficial' indevidamente!";
    }
}

// ----------------------------------------------------
// Teste 3: Padronização gráfica 1080x1080 WebP
// ----------------------------------------------------
echo "\n[3/3] Testando Padronizacao Grafica (1080x1080 WebP 85%)...\n";
if (!empty($p1['imagem_url'])) {
    $targetPath = 'scratch/test_ryzen_cover.webp';
    $imgRes = $extractor->standardizeImage($p1['imagem_url'], $targetPath, 1080, 85);
    if (!($imgRes['ok'] ?? false)) {
        $errors[] = "Falha ao padronizar imagem: " . ($imgRes['error'] ?? 'Erro desconhecido');
    } else {
        echo "  - Imagem gerada com sucesso: {$imgRes['relative_path']}\n";
        echo "  - Dimensoes: {$imgRes['size']}x{$imgRes['size']} px\n";
        echo "  - Peso: {$imgRes['kb']} KB\n";
        if ($imgRes['kb'] > 300) {
            $errors[] = "Imagem gerada muito pesada ({$imgRes['kb']} KB).";
        }
    }
}

// ----------------------------------------------------
// Relatório Final
// ----------------------------------------------------
echo "\n========================================================\n";
if (empty($errors)) {
    echo "  STATUS: TODOS OS TESTES PASSARAM COM 100% DE SUCESSO!\n";
    echo "========================================================\n";
    exit(0);
} else {
    echo "  STATUS: FALHAS DETECTADAS (" . count($errors) . "):\n";
    foreach ($errors as $e) {
        echo "  [FALHA] {$e}\n";
    }
    echo "========================================================\n";
    exit(1);
}
