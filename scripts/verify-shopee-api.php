<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Services\Affiliate\ShopeeApiService;

echo "========================================================\n";
echo "  Estratégia Nerd - Validador da API Shopee Afiliados\n";
echo "========================================================\n\n";

try {
    $shopeeApi = new ShopeeApiService();

    if (!$shopeeApi->isConfigured()) {
        echo "[ERRO] Credenciais SHOPEE_APP_ID e SHOPEE_APP_SECRET nao encontradas no arquivo .env.\n";
        exit(1);
    }

    echo "[OK] Credenciais ativas no arquivo central de credenciais (.env).\n";
    echo "     AppID: " . substr((string)($_ENV['SHOPEE_APP_ID'] ?? ''), 0, 5) . "*****\n\n";

    echo "1. Solicitando geracao de link curto oficial via GraphQL da Shopee...\n";
    $testUrl = 'https://shopee.com.br/product/627750190/21998280953';
    
    $shortLink = $shopeeApi->generateShortLink($testUrl, 'centralnerd');
    
    echo "   [SUCESSO INTEGRAL] API respondeu com sucesso!\n";
    echo "   -> URL Original : {$testUrl}\n";
    echo "   -> Link Afiliado: {$shortLink}\n";
    echo "   -> Sub_ID       : centralnerd\n\n";

    echo "========================================================\n";
    echo "  Validação da API oficial da Shopee finalizada com 100% de sucesso!\n";
    echo "========================================================\n";
    exit(0);

} catch (\Throwable $e) {
    echo "[ERRO FATAL] " . $e->getMessage() . "\n";
    exit(1);
}
