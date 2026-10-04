<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Services\Affiliate\MercadoLivreExtractorService;

echo "========================================================\n";
echo "  Estratégia Nerd - Processamento das Novas Capas WebP  \n";
echo "========================================================\n";

$pdo = $GLOBALS['pdo'];
$extractor = new MercadoLivreExtractorService(null, dirname(__DIR__) . '/public');

$imagesMap = [
    [
        'id' => 1,
        'slug' => 'action-figure-homem-de-ferro-mark-39-starboost',
        'source' => 'C:\\Users\\WINDOWS\\.gemini\\antigravity\\brain\\744a0c64-90e0-43d8-b970-75d11fd25796\\iron_man_starboost_1791139583603.jpg',
        'title' => 'Action Figure Homem de Ferro Mark 39 Starboost',
    ],
    [
        'id' => 2,
        'slug' => 'action-figure-kaiju-no-8-blocos-hibino-kafka',
        'source' => 'C:\\Users\\WINDOWS\\.gemini\\antigravity\\brain\\744a0c64-90e0-43d8-b970-75d11fd25796\\kaiju_no8_figure_1791139602736.jpg',
        'title' => 'Action Figure Kaiju No. 8 Blocos Hibino Kafka',
    ],
    [
        'id' => 3,
        'slug' => 'action-figure-ash-williams-evil-dead-2-blocos',
        'source' => 'C:\\Users\\WINDOWS\\.gemini\\antigravity\\brain\\744a0c64-90e0-43d8-b970-75d11fd25796\\ash_williams_figure_1791139624090.jpg',
        'title' => 'Action Figure Ash Williams Evil Dead 2 Blocos',
    ],
    [
        'id' => 4,
        'slug' => 'ssd-kingston-a400-960gb-sata-3-0-alta-velocidade',
        'source' => 'C:\\Users\\WINDOWS\\.gemini\\antigravity\\brain\\744a0c64-90e0-43d8-b970-75d11fd25796\\kingston_a400_ssd_1791139649578.jpg',
        'title' => 'SSD Kingston A400 960GB SATA 3.0',
    ],
    [
        'id' => 5,
        'slug' => 'smartwatch-gt5-pro-max',
        'source' => 'C:\\Users\\WINDOWS\\.gemini\\antigravity\\brain\\744a0c64-90e0-43d8-b970-75d11fd25796\\smartwatch_gt5_pro_1791139676487.jpg',
        'title' => 'Smartwatch GT5 Pro Max',
    ],
    [
        'id' => 6,
        'slug' => 'headset-gamer-attack-shark-l80pro-wireless-7-1-ultra-leve',
        'source' => 'C:\\Users\\WINDOWS\\.gemini\\antigravity\\brain\\744a0c64-90e0-43d8-b970-75d11fd25796\\attack_shark_headset_1791139707331.jpg',
        'title' => 'Headset Gamer ATTACK SHARK L80PRO Wireless 7.1',
    ],
    [
        'id' => 15,
        'slug' => 'teclado-ajazz-ak820-ak820pro-rgb-wireless',
        'source' => 'C:\\Users\\WINDOWS\\.gemini\\antigravity\\brain\\744a0c64-90e0-43d8-b970-75d11fd25796\\ajazz_ak820_pro_1791139742654.jpg',
        'title' => 'Teclado AJAZZ AK820 Pro RGB Wireless',
    ],
    [
        'id' => 7,
        'slug' => 'cupom-hostinger-20-off-hospedagem-de-sites',
        'source' => 'C:\\Users\\WINDOWS\\.gemini\\antigravity\\brain\\744a0c64-90e0-43d8-b970-75d11fd25796\\hostinger_coupon_card_1791139778531.jpg',
        'title' => 'Cupom Hostinger 20% OFF',
    ],
    [
        'id' => 8,
        'slug' => 'cupom-aliexpress-r-25-off-acima-de-r-180',
        'source' => 'C:\\Users\\WINDOWS\\.gemini\\antigravity\\brain\\744a0c64-90e0-43d8-b970-75d11fd25796\\aliexpress_coupon_card_1791139817300.jpg',
        'title' => 'Cupom AliExpress R$25 OFF',
    ],
];

$successCount = 0;

foreach ($imagesMap as $item) {
    $id = $item['id'];
    $slug = $item['slug'];
    $source = $item['source'];
    $title = $item['title'];

    if (!is_file($source)) {
        echo "ERRO: Arquivo fonte nao encontrado para #{$id} - {$title}: {$source}\n";
        continue;
    }

    $targetRelative = "uploads/links/{$slug}/capa.webp";
    $result = $extractor->standardizeImage($source, $targetRelative, 1080, 85);

    if (($result['ok'] ?? false) === true) {
        $kb = round((int) $result['bytes'] / 1024, 1);
        echo "[OK] #{$id} - {$title}\n";
        echo "     Destino: {$result['relative_path']} ({$kb} KB | {$result['width']}x{$result['height']} WebP)\n";

        // Atualizar no banco
        $stmt = $pdo->prepare('UPDATE links SET imagem = :imagem WHERE id = :id');
        $stmt->execute([
            'imagem' => $result['relative_path'],
            'id' => $id,
        ]);
        $successCount++;
    } else {
        echo "[FALHA] #{$id} - {$title}: " . ($result['error'] ?? 'Erro desconhecido') . "\n";
    }
}

echo "\nConcluido! {$successCount} de " . count($imagesMap) . " capas atualizadas e integradas com sucesso.\n";
