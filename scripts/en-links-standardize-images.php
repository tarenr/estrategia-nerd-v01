<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Services\Affiliate\MercadoLivreExtractorService;

echo "========================================================\n";
echo "  Estratégia Nerd - Padronizacao de Capas do Catalogo   \n";
echo "========================================================\n";

$pdo = $GLOBALS['pdo'];
$extractor = new MercadoLivreExtractorService(null, dirname(__DIR__) . '/public');

$stmt = $pdo->query('SELECT id, titulo, slug, imagem FROM links ORDER BY id ASC');
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($links);
$converted = 0;
$totalBytesSaved = 0;

echo "Total de links no catalogo: {$total}\n\n";

foreach ($links as $link) {
    $id = (int) $link['id'];
    $slug = (string) $link['slug'];
    $currentImage = (string) $link['imagem'];

    if ($currentImage === '') {
        echo "#{$id} - {$link['titulo']}: [IGNORADO - Sem imagem]\n";
        continue;
    }

    $absoluteCurrent = dirname(__DIR__) . '/public/' . ltrim($currentImage, '/\\');
    if (!is_file($absoluteCurrent)) {
        echo "#{$id} - {$link['titulo']}: [AVISO - Arquivo nao encontrado: {$currentImage}]\n";
        continue;
    }

    $origSize = filesize($absoluteCurrent);
    $targetRelative = "uploads/links/{$slug}/capa.webp";

    $result = $extractor->standardizeImage($absoluteCurrent, $targetRelative, 1080, 85);
    if (($result['ok'] ?? false) === true) {
        $newSize = (int) $result['bytes'];
        $diff = $origSize - $newSize;
        $totalBytesSaved += max(0, $diff);

        // Atualiza no banco de dados
        $updateStmt = $pdo->prepare('UPDATE links SET imagem = :imagem WHERE id = :id');
        $updateStmt->execute([
            'imagem' => $result['relative_path'],
            'id' => $id,
        ]);

        $converted++;
        $pct = $origSize > 0 ? round(($diff / $origSize) * 100) : 0;
        echo "#{$id} - {$link['titulo']}\n";
        echo "     De: " . round($origSize / 1024, 1) . " KB  -->  Para: {$result['kb']} KB (Reducao de {$pct}% | 1080x1080 WebP)\n";
    } else {
        echo "#{$id} - {$link['titulo']}: [ERRO - " . ($result['error'] ?? 'falha') . "]\n";
    }
}

echo "\n========================================================\n";
echo "Padronizacao concluida com sucesso!\n";
echo "Convertidos: {$converted} / {$total}\n";
echo "Economia de banda total gerada: " . round($totalBytesSaved / (1024 * 1024), 2) . " MB\n";
echo "========================================================\n";
