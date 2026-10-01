<?php
declare(strict_types=1);

$dir = __DIR__ . '/../public/uploads/reels';
$files = glob($dir . '/*.mp4') ?: [];
echo "Processando " . count($files) . " arquivos de vídeo...\n";

$generated = 0;
foreach ($files as $mp4) {
    $jpg = preg_replace('/\.mp4$/i', '.jpg', $mp4);
    if (!is_file($jpg)) {
        $cmd = sprintf(
            'ffmpeg -y -ss 00:00:00.100 -i %s -vframes 1 -q:v 2 %s 2>&1',
            escapeshellarg($mp4),
            escapeshellarg($jpg)
        );
        exec($cmd, $out, $code);
        if ($code === 0 && is_file($jpg)) {
            $generated++;
            echo "Gerado: " . basename($jpg) . "\n";
        } else {
            echo "Erro em: " . basename($mp4) . "\n";
        }
    } else {
        $generated++;
    }
}

echo "Total de posters prontos: {$generated}/" . count($files) . "\n";
