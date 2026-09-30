<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/Instagram/SmartCanvasRenderer.php
 * @project     Estrategia Nerd
 * @purpose     Compor arte quadrada para o Instagram a partir da capa do blog
 *              sem cortar a imagem original (FEAT-010 / cross-post do blog)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services\Instagram;

use GdImage;
use RuntimeException;

final class SmartCanvasRenderer
{
    public const MAX_BYTES  = 10 * 1024 * 1024;
    public const MAX_PIXELS = 25_000_000;

    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private const FONT_CANDIDATES = [
        'C:/Windows/Fonts/bahnschrift.ttf',
        'C:/Windows/Fonts/arialbd.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    ];

    private const TOP_LABEL    = 'ESTRATEGIA NERD';
    private const BOTTOM_LABEL = 'LEIA O ARTIGO NO BLOG /// LINK NA BIO';

    /**
     * Valida tamanho, pixels e tipo real antes de abrir no GD.
     *
     * @return string MIME detectado
     */
    public function assertValidSource(string $sourcePath): string
    {
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new RuntimeException('Imagem de origem nao encontrada.');
        }

        $bytes = (int) filesize($sourcePath);
        if ($bytes <= 0 || $bytes > self::MAX_BYTES) {
            throw new RuntimeException('A imagem excede o limite de 10 MB.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo !== false ? (string) finfo_file($finfo, $sourcePath) : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new RuntimeException('Formato de imagem nao suportado. Use JPEG, PNG ou WebP.');
        }

        $size = @getimagesize($sourcePath);
        if ($size === false || (int) $size[0] <= 0 || (int) $size[1] <= 0) {
            throw new RuntimeException('Nao foi possivel ler as dimensoes da imagem.');
        }

        if ((int) $size[0] * (int) $size[1] > self::MAX_PIXELS) {
            throw new RuntimeException('A imagem excede o limite de 25 megapixels.');
        }

        return $mime;
    }

    /**
     * Gera o canvas (JPEG) com a imagem inteira centralizada sobre fundo desfocado.
     */
    public function render(string $sourcePath, string $destPath, int $width = 1080, int $height = 1080, bool $withLabels = true): string
    {
        $mime   = $this->assertValidSource($sourcePath);
        $source = $this->load($sourcePath, $mime);

        try {
            $canvas = $this->newTrueColor($width, $height);
            try {
                $this->paintBackground($canvas, $source, $width, $height);
                $this->paintForeground($canvas, $source, $width, $height, $withLabels);
                if ($withLabels) {
                    $this->paintLabels($canvas, $width, $height);
                }

                $dir = dirname($destPath);
                if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                    throw new RuntimeException('Nao foi possivel criar a pasta de destino do canvas.');
                }

                if (!imagejpeg($canvas, $destPath, 88)) {
                    throw new RuntimeException('Falha ao gravar o canvas.');
                }
            } finally {
                imagedestroy($canvas);
            }
        } finally {
            imagedestroy($source);
        }

        return $destPath;
    }

    /**
     * Largura e altura como a imagem sera exibida, ja considerando a orientacao EXIF
     * (fotos de celular giradas 90/270 graus trocam largura e altura).
     *
     * @return array{0:int,1:int}
     */
    public function effectiveSize(string $sourcePath): array
    {
        $mime = $this->assertValidSource($sourcePath);
        $size = getimagesize($sourcePath);
        if ($size === false) {
            throw new RuntimeException('Nao foi possivel ler as dimensoes da imagem.');
        }

        $w = (int) $size[0];
        $h = (int) $size[1];
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($sourcePath);
            $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
            if (in_array($orientation, [5, 6, 7, 8], true)) {
                return [$h, $w];
            }
        }

        return [$w, $h];
    }

    /**
     * Regrava a arte dedicada como JPEG (descarta metadados/payload) sem compor.
     *
     * @return array{0:int,1:int} largura e altura finais
     */
    public function reencode(string $sourcePath, string $destPath, int $maxWidth = 1440): array
    {
        $mime   = $this->assertValidSource($sourcePath);
        $source = $this->load($sourcePath, $mime);

        try {
            $srcW  = imagesx($source);
            $srcH  = imagesy($source);
            $scale = min(1, $maxWidth / $srcW);
            $dstW  = (int) max(1, round($srcW * $scale));
            $dstH  = (int) max(1, round($srcH * $scale));

            $out = $this->newTrueColor($dstW, $dstH);
            try {
                $white = imagecolorallocate($out, 255, 255, 255);
                if ($white !== false) {
                    imagefilledrectangle($out, 0, 0, $dstW, $dstH, $white);
                }
                imagecopyresampled($out, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

                $dir = dirname($destPath);
                if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                    throw new RuntimeException('Nao foi possivel criar a pasta de destino da arte.');
                }
                if (!imagejpeg($out, $destPath, 90)) {
                    throw new RuntimeException('Falha ao gravar a arte dedicada.');
                }
            } finally {
                imagedestroy($out);
            }
        } finally {
            imagedestroy($source);
        }

        return [$dstW, $dstH];
    }

    private function load(string $path, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default      => false,
        };

        if (!$image instanceof GdImage) {
            throw new RuntimeException('Nao foi possivel decodificar a imagem.');
        }

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif        = @exif_read_data($path);
            $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
            $angle       = match ($orientation) {
                3, 4    => 180,
                5, 6    => -90,
                7, 8    => 90,
                default => 0,
            };
            // Orientacoes 2, 4, 5 e 7 sao as espelhadas.
            if (in_array($orientation, [2, 4, 5, 7], true)) {
                imageflip($image, IMG_FLIP_HORIZONTAL);
            }
            if ($angle !== 0) {
                $rotated = imagerotate($image, $angle, 0);
                if ($rotated instanceof GdImage) {
                    imagedestroy($image);
                    $image = $rotated;
                }
            }
        }

        return $image;
    }

    private function newTrueColor(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));
        if (!$image instanceof GdImage) {
            throw new RuntimeException('Falha ao alocar imagem.');
        }

        return $image;
    }

    private function paintBackground(GdImage $canvas, GdImage $source, int $width, int $height): void
    {
        $srcW = imagesx($source);
        $srcH = imagesy($source);

        // Desfoque em copia reduzida e depois numa intermediaria: barato em memoria
        // e sem o efeito de blocos que aparece ao ampliar direto uma copia minuscula.
        $smallW = max(1, (int) round($width / 5));
        $smallH = max(1, (int) round($height / 5));
        $scale  = max($smallW / $srcW, $smallH / $srcH);
        $cropW  = (int) max(1, round($smallW / $scale));
        $cropH  = (int) max(1, round($smallH / $scale));
        $cropX  = (int) max(0, round(($srcW - $cropW) / 2));
        $cropY  = (int) max(0, round(($srcH - $cropH) / 2));

        $small = $this->newTrueColor($smallW, $smallH);
        try {
            imagecopyresampled($small, $source, 0, 0, $cropX, $cropY, $smallW, $smallH, $cropW, $cropH);
            for ($i = 0; $i < 16; $i++) {
                imagefilter($small, IMG_FILTER_GAUSSIAN_BLUR);
            }

            $midW = max(1, (int) round($width / 2));
            $midH = max(1, (int) round($height / 2));
            $mid  = $this->newTrueColor($midW, $midH);
            try {
                imagecopyresampled($mid, $small, 0, 0, 0, 0, $midW, $midH, $smallW, $smallH);
                for ($i = 0; $i < 4; $i++) {
                    imagefilter($mid, IMG_FILTER_GAUSSIAN_BLUR);
                }
                imagecopyresampled($canvas, $mid, 0, 0, 0, 0, $width, $height, $midW, $midH);
            } finally {
                imagedestroy($mid);
            }
        } finally {
            imagedestroy($small);
        }

        $overlay = imagecolorallocatealpha($canvas, 11, 15, 25, 38);
        if ($overlay !== false) {
            imagealphablending($canvas, true);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $overlay);
        }
    }

    private function paintForeground(GdImage $canvas, GdImage $source, int $width, int $height, bool $withLabels = true): void
    {
        $srcW = imagesx($source);
        $srcH = imagesy($source);
        // Sem as faixas de texto, a imagem usa a altura toda (sem reservar espaco para os rotulos).
        $band = $withLabels ? (int) round($height * 0.09) : 0;

        $scale = min($width / $srcW, ($height - 2 * $band) / $srcH);
        $dstW  = (int) max(1, round($srcW * $scale));
        $dstH  = (int) max(1, round($srcH * $scale));
        $dstX  = (int) round(($width - $dstW) / 2);
        $dstY  = (int) round(($height - $dstH) / 2);

        imagecopyresampled($canvas, $source, $dstX, $dstY, 0, 0, $dstW, $dstH, $srcW, $srcH);

        $accent = $withLabels ? imagecolorallocatealpha($canvas, 34, 211, 238, 50) : false;
        if ($accent !== false) {
            imagefilledrectangle($canvas, $dstX, max(0, $dstY - 3), $dstX + $dstW - 1, max(0, $dstY - 1), $accent);
            imagefilledrectangle($canvas, $dstX, min($height - 1, $dstY + $dstH), $dstX + $dstW - 1, min($height - 1, $dstY + $dstH + 2), $accent);
        }
    }

    private function paintLabels(GdImage $canvas, int $width, int $height): void
    {
        $band  = (int) round($height * 0.09);
        $light = imagecolorallocatealpha($canvas, 226, 232, 240, 20);
        $cyan  = imagecolorallocatealpha($canvas, 34, 211, 238, 10);
        if ($light === false || $cyan === false) {
            return;
        }

        $font = $this->resolveFont();
        $this->drawCentered($canvas, self::TOP_LABEL, $font, (int) round($height * 0.024), $cyan, (int) round($band * 0.62), $width);
        $this->drawCentered($canvas, self::BOTTOM_LABEL, $font, (int) round($height * 0.018), $light, $height - (int) round($band * 0.45), $width);
    }

    private function drawCentered(GdImage $canvas, string $text, ?string $font, int $size, int $color, int $baselineY, int $width): void
    {
        if ($font !== null) {
            $box = imagettfbbox($size, 0, $font, $text);
            if ($box !== false) {
                $textW = abs($box[2] - $box[0]);
                imagettftext($canvas, $size, 0, (int) round(($width - $textW) / 2), $baselineY, $color, $font, $text);
                return;
            }
        }

        $builtin = 5;
        $textW   = imagefontwidth($builtin) * strlen($text);
        imagestring($canvas, $builtin, (int) round(($width - $textW) / 2), $baselineY - imagefontheight($builtin), $text, $color);
    }

    private function resolveFont(): ?string
    {
        if (!function_exists('imagettftext')) {
            return null;
        }

        foreach (self::FONT_CANDIDATES as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
