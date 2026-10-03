<?php

declare(strict_types=1);

namespace App\Services\Instagram;

use GdImage;
use RuntimeException;

/** Camadas GD independentes; FFmpeg anima e codifica o video com audio uma vez. */
final class EditorialMotionReelRenderer
{
    public const VERSION = 'editorial-motion-v1';
    public const WIDTH = 1080;
    public const HEIGHT = 1920;
    public const FPS = 30;

    public function __construct(
        private readonly string $ffmpeg = 'ffmpeg',
        private readonly string $ffprobe = 'ffprobe',
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     * @return array{video_path: string, canvas_path: string, duration: int}
     */
    public function render(string $cover, string $audio, array $meta, string $output, int $audioStart = 0, ?int $duration = null): array
    {
        if (is_file($output)) {
            throw new RuntimeException('A saida ja existe; escolha outro nome para preservar o video anterior.');
        }
        if (!is_file($audio)) {
            throw new RuntimeException('A trilha local nao foi encontrada.');
        }
        $title = $this->shortText((string) ($meta['titulo'] ?? $meta['chamada_titulo'] ?? ''), 110);
        if ($title === '') {
            throw new RuntimeException('Informe o titulo do artigo para o Reel animado.');
        }
        $summary = $this->shortText((string) ($meta['resumo'] ?? $meta['chamada_texto'] ?? ''), 260);
        $points = $this->splitSummary($summary);
        // Tempo de leitura conservador; a duracao explicita nunca encurta esse minimo.
        $readingSeconds = (int) ceil(str_word_count($summary, 0, 'áàâãéêíóôõúçÁÀÂÃÉÊÍÓÔÕÚÇ') / 2.8);
        $seconds = min(30, max(16, 7 + $readingSeconds, $duration ?? 0));
        $audioSpec = $this->probe($audio);
        $audioSeconds = (float) ($audioSpec['format']['duration'] ?? 0);
        if ($audioSeconds < $seconds) {
            throw new RuntimeException('A trilha precisa cobrir toda a duracao do Reel.');
        }
        $audioStart = min(max(0, $audioStart), max(0, (int) floor($audioSeconds - $seconds)));
        $dir = dirname($output);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Nao foi possivel criar a pasta do piloto.');
        }
        $layers = $dir . '/' . pathinfo($output, PATHINFO_FILENAME) . '-layers';
        if (is_dir($layers) || !mkdir($layers, 0755, true)) {
            throw new RuntimeException('A pasta de camadas ja existe ou nao pode ser criada.');
        }
        $font = $this->font();
        $this->background($layers . '/background.png', $font, (string) ($meta['categoria'] ?? 'EDITORIAL'));
        $this->coverLayer($cover, $layers . '/cover.png');
        $this->hudLayer($layers . '/hud.png');
        $this->textLayer($layers . '/title.png', $title, $font, 60, 285, false);
        $this->textLayer($layers . '/point-1.png', $points[0], $font, 43, 300, true, '01 / DESTAQUE');
        $this->textLayer($layers . '/point-2.png', $points[1], $font, 43, 300, true, '02 / CONTEXTO');
        $this->textLayer($layers . '/cta.png', 'Confira o artigo completo.\nLink na bio.', $font, 51, 300, true, 'CONTINUE NO BLOG');

        $end = $seconds - 3;
        $middle = (5 + $end) / 2;
        $args = [$this->ffmpeg, '-hide_banner', '-loglevel', 'error', '-n'];
        foreach (['background', 'cover', 'title', 'point-1', 'point-2', 'cta', 'hud'] as $layer) {
            array_push($args, '-loop', '1', '-framerate', '30', '-i', $layers . '/' . $layer . '.png');
        }
        array_push($args, '-ss', (string) $audioStart, '-i', $audio);
        // Zoom restrito a capa. Textos nunca recebem zoom e continuam legiveis.
        $filters = [
            '[0:v]format=yuv420p[bg]',
            "[1:v]zoompan=z='1+min(on,900)*0.000035':x='iw/2-iw/zoom/2':y='ih/2-ih/zoom/2':d=1:s=960x600:fps=30,format=rgba,fade=t=in:st=1.5:d=0.5:alpha=1[cover]",
            "[bg][cover]overlay=x=60:y='590+90*pow(1-min(max((t-1.5)/0.65,0),1),3)':enable='gte(t,1.5)'[s1]",
            '[2:v]format=rgba,fade=t=in:st=0:d=0.5:alpha=1[title]',
            "[s1][title]overlay=x='60+100*pow(1-min(t/0.65,1),3)':y=260[s2]",
            sprintf('[3:v]format=rgba,fade=t=in:st=5:d=0.4:alpha=1,fade=t=out:st=%.3f:d=0.3:alpha=1[p1]', $middle - 0.3),
            sprintf("[s2][p1]overlay=x='60+70*pow(1-min(max((t-5)/0.55,0),1),3)':y=1270:enable='between(t,5,%.3f)'[s3]", $middle),
            sprintf('[4:v]format=rgba,fade=t=in:st=%.3f:d=0.4:alpha=1,fade=t=out:st=%.3f:d=0.3:alpha=1[p2]', $middle, $end - 0.3),
            sprintf("[s3][p2]overlay=x='60+70*pow(1-min(max((t-%.3f)/0.55,0),1),3)':y=1270:enable='between(t,%.3f,%d)'[s4]", $middle, $middle, $end),
            sprintf('[5:v]format=rgba,fade=t=in:st=%d:d=0.4:alpha=1[cta]', $end),
            sprintf("[s4][cta]overlay=x=60:y='1270+45*pow(1-min(max((t-%d)/0.6,0),1),3)':enable='gte(t,%d)'[s5]", $end, $end),
            '[6:v]format=rgba,fade=t=in:st=2:d=0.7:alpha=1[hud]',
            "[s5][hud]overlay=x=60:y=590:enable='gte(t,2)'[s6]",
            // A barra fica recortada na margem segura, sem atravessar a tela.
            sprintf("color=c=0x203344:s=960x4:r=30[barbase];color=c=0x22d3ee:s=960x4:r=30[bar];[barbase][bar]overlay=x='-960+960*min(t/%d,1)':y=0[progress];[s6][progress]overlay=x=60:y=1690,format=yuv420p[v]", $seconds),
            sprintf('[7:a]atrim=duration=%d,asetpts=PTS-STARTPTS,afade=t=in:st=0:d=0.25,afade=t=out:st=%.2f:d=1.5[a]', $seconds, $seconds - 1.5),
        ];
        array_push($args, '-filter_complex_threads', '1', '-filter_complex', implode(';', $filters), '-map', '[v]', '-map', '[a]',
            '-c:v', 'libx264', '-preset', 'fast', '-crf', '20', '-threads', '2', '-pix_fmt', 'yuv420p',
            '-color_range', 'tv', '-colorspace', 'bt709', '-color_primaries', 'bt709', '-color_trc', 'bt709',
            '-c:a', 'aac', '-b:a', '128k', '-ar', '48000', '-ac', '2', '-movflags', '+faststart', '-t', (string) $seconds, $output);
        $this->command($args, $layers . '/encode.log');
        $this->assertVideo($output, $seconds);
        $poster = $dir . '/' . pathinfo($output, PATHINFO_FILENAME) . '.jpg';
        $this->capture($output, 3.5, $poster);
        $manifest = ['template' => self::VERSION, 'titulo' => $title, 'resumo' => $summary, 'destaques' => $points,
            'duration' => $seconds, 'audio_start_seconds' => $audioStart, 'scenes' => [0, 1.5, 5, $middle, $end]];
        if (file_put_contents($layers . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) {
            throw new RuntimeException('Falha ao gravar o manifesto do Reel.');
        }
        return ['video_path' => $output, 'canvas_path' => $poster, 'duration' => $seconds];
    }

    /** @return array<string, mixed> */
    public function probe(string $path): array
    {
        $pipes = [];
        $proc = proc_open([$this->ffprobe, '-v', 'error', '-show_streams', '-show_format', '-of', 'json', $path],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            throw new RuntimeException('Nao foi possivel iniciar FFprobe.');
        }
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        if (proc_close($proc) !== 0) {
            throw new RuntimeException('FFprobe: ' . $error);
        }
        return (array) json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);
    }

    public function assertVideo(string $path, int $seconds): void
    {
        $spec = $this->probe($path);
        $video = null;
        $audio = null;
        foreach ($spec['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? '') === 'video') { $video = $stream; }
            if (($stream['codec_type'] ?? '') === 'audio') { $audio = $stream; }
        }
        if ($video === null || $audio === null || ($video['codec_name'] ?? '') !== 'h264'
            || ($video['width'] ?? 0) !== self::WIDTH || ($video['height'] ?? 0) !== self::HEIGHT
            || ($video['avg_frame_rate'] ?? '') !== '30/1' || ($video['pix_fmt'] ?? '') !== 'yuv420p'
            || ($audio['codec_name'] ?? '') !== 'aac' || (int) ($audio['sample_rate'] ?? 0) !== 48000
            || abs((float) ($spec['format']['duration'] ?? 0) - $seconds) > 0.15
            || abs((float) ($video['duration'] ?? 0) - $seconds) > 0.15
            || abs((float) ($audio['duration'] ?? 0) - $seconds) > 0.15) {
            throw new RuntimeException('O MP4 nao atende ao contrato de video/audio do template.');
        }
    }

    public function capture(string $video, float $time, string $output): void
    {
        $this->command([$this->ffmpeg, '-hide_banner', '-loglevel', 'error', '-n', '-ss', (string) $time, '-i', $video,
            '-frames:v', '1', '-q:v', '2', $output], dirname($output) . '/capture-' . pathinfo($output, PATHINFO_FILENAME) . '.log');
    }

    /** @param list<string> $args */
    private function command(array $args, string $log): void
    {
        $pipes = [];
        $proc = proc_open($args, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes);
        if (!is_resource($proc)) { throw new RuntimeException('Nao foi possivel iniciar FFmpeg.'); }
        fclose($pipes[0]);
        if (proc_close($proc) !== 0) {
            throw new RuntimeException('Falha no FFmpeg: ' . mb_substr((string) file_get_contents($log), -2500));
        }
    }

    private function font(): string
    {
        foreach (['C:/Windows/Fonts/bahnschrift.ttf', 'C:/Windows/Fonts/arialbd.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'] as $font) {
            if (is_file($font)) { return $font; }
        }
        throw new RuntimeException('Nao ha fonte TrueType disponivel.');
    }

    private function image(int $width, int $height, bool $transparent = false): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        if (!$image instanceof GdImage) { throw new RuntimeException('Falha ao criar camada GD.'); }
        if ($transparent) {
            imagealphablending($image, false);
            imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
            imagesavealpha($image, true);
            imagealphablending($image, true);
        }
        return $image;
    }

    private function save(GdImage $image, string $path): void
    {
        try {
            if (!imagepng($image, $path)) { throw new RuntimeException('Falha ao gravar camada PNG.'); }
        } finally { imagedestroy($image); }
    }

    private function background(string $path, string $font, string $category): void
    {
        $image = $this->image(self::WIDTH, self::HEIGHT);
        for ($y = 0; $y < self::HEIGHT; $y++) {
            $glow = max(0, 1 - abs($y - 650) / 1000);
            $color = (int) imagecolorallocate($image, 7, 11 + (int) ($glow * 12), 20 + (int) ($glow * 18));
            imageline($image, 0, $y, 1079, $y, $color);
        }
        $grid = (int) imagecolorallocate($image, 18, 38, 51);
        for ($x = 60; $x < 1020; $x += 60) {
            for ($y = 80; $y < 1700; $y += 60) { imagefilledellipse($image, $x, $y, 2, 2, $grid); }
        }
        $cyan = (int) imagecolorallocate($image, 34, 211, 238);
        $muted = (int) imagecolorallocate($image, 148, 163, 184);
        imagettftext($image, 22, 0, 60, 160, $cyan, $font, 'ESTRATÉGIA NERD');
        $cat = mb_strtoupper($this->shortText($category, 20));
        imagettftext($image, 16, 0, 60, 208, $muted, $font, '// ' . $cat . '  /  EM FOCO');
        imageline($image, 60, 235, 1020, 235, $grid);
        imagettftext($image, 16, 0, 60, 1750, $muted, $font, 'ESTRATEGIANERD.COM.BR');
        $this->save($image, $path);
    }

    private function coverLayer(string $sourcePath, string $path): void
    {
        $mime = (new SmartCanvasRenderer())->assertValidSource($sourcePath);
        $source = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($sourcePath),
            'image/png' => imagecreatefrompng($sourcePath),
            'image/webp' => imagecreatefromwebp($sourcePath),
            default => false,
        };
        if (!$source instanceof GdImage) { throw new RuntimeException('Falha ao carregar a capa.'); }
        $image = $this->image(960, 600);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 9, 18, 30));
        $scale = min(928 / imagesx($source), 550 / imagesy($source));
        $w = (int) round(imagesx($source) * $scale);
        $h = (int) round(imagesy($source) * $scale);
        imagecopyresampled($image, $source, (int) ((960 - $w) / 2), (int) ((600 - $h) / 2), 0, 0, $w, $h, imagesx($source), imagesy($source));
        imagedestroy($source);
        $this->save($image, $path);
    }

    private function hudLayer(string $path): void
    {
        $image = $this->image(960, 600, true);
        $cyan = (int) imagecolorallocate($image, 34, 211, 238);
        imagesetthickness($image, 4);
        foreach ([[2, 2, 1, 1], [957, 2, -1, 1], [2, 597, 1, -1], [957, 597, -1, -1]] as [$x, $y, $dx, $dy]) {
            imageline($image, $x, $y, $x + $dx * 45, $y, $cyan);
            imageline($image, $x, $y, $x, $y + $dy * 45, $cyan);
        }
        $this->save($image, $path);
    }

    private function textLayer(string $path, string $text, string $font, int $size, int $height, bool $panel, string $label = ''): void
    {
        $image = $this->image(960, $height, true);
        $white = (int) imagecolorallocate($image, 241, 245, 249);
        $cyan = (int) imagecolorallocate($image, 34, 211, 238);
        $top = $panel ? 84 : 8;
        if ($panel) {
            imagefilledrectangle($image, 0, 0, 959, $height - 1, (int) imagecolorallocatealpha($image, 9, 18, 30, 8));
            imagefilledrectangle($image, 0, 0, 5, $height - 1, $cyan);
            imagettftext($image, 16, 0, 30, 44, $cyan, $font, $label);
        }
        $text = str_replace('\\n', "\n", $text);
        do {
            $lines = $this->wrap($text, $font, $size, 890);
            $lineHeight = (int) ceil($size * 1.45);
            $fits = $top + count($lines) * $lineHeight <= $height - 16;
            foreach ($lines as $line) {
                $box = imagettfbbox($size, 0, $font, $line);
                if ($box === false || $box[2] - $box[0] > 890) { $fits = false; }
            }
            if (!$fits) { $size--; }
        } while (!$fits && $size >= 24);
        if (!$fits) { imagedestroy($image); throw new RuntimeException('Texto excede a area segura da camada.'); }
        foreach ($lines as $i => $line) {
            imagettftext($image, $size, 0, $panel ? 30 : 0, $top + $size + $i * $lineHeight, $white, $font, $line);
        }
        $this->save($image, $path);
    }

    /** @return list<string> */
    private function wrap(string $text, string $font, int $size, int $maxWidth): array
    {
        $lines = [];
        foreach (explode("\n", $text) as $paragraph) {
            $line = '';
            foreach (preg_split('/\s+/u', trim($paragraph)) ?: [] as $word) {
                $candidate = $line === '' ? $word : $line . ' ' . $word;
                $box = imagettfbbox($size, 0, $font, $candidate);
                if ($box === false) { throw new RuntimeException('Falha ao medir texto.'); }
                if ($box[2] - $box[0] > $maxWidth && $line !== '') { $lines[] = $line; $line = $word; }
                else { $line = $candidate; }
            }
            $lines[] = $line;
        }
        return $lines;
    }

    private function shortText(string $text, int $limit): string
    {
        $text = html_entity_decode(strip_tags((string) preg_replace('/\[\[(.*?)\]\]/u', '$1', $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) <= $limit) { return $text; }
        $cut = mb_substr($text, 0, $limit - 1);
        $space = mb_strrpos($cut, ' ');
        return ($space !== false ? mb_substr($cut, 0, $space) : $cut) . '…';
    }

    /** @return array{0:string,1:string} */
    private function splitSummary(string $summary): array
    {
        if ($summary === '') { return ['Conheça os detalhes deste artigo.', 'Veja o conteúdo completo no blog.']; }
        $sentences = preg_split('/(?<=[.!?])\s+/u', $summary) ?: [$summary];
        if (count($sentences) >= 2 && mb_strlen($sentences[0]) <= 150) {
            return [$this->shortText($sentences[0], 150), $this->shortText(implode(' ', array_slice($sentences, 1)), 150)];
        }
        if (mb_strlen($summary) <= 70) { return [$summary, 'Veja os detalhes e o contexto no artigo completo.']; }
        // Distribui o resumo nas duas cenas, sem substituir contexto por uma alegacao nova.
        $middle = (int) floor(mb_strlen($summary) / 2);
        $split = $middle;
        $bestDistance = PHP_INT_MAX;
        for ($i = 30; $i < mb_strlen($summary) - 30; $i++) {
            if (in_array(mb_substr($summary, $i, 1), [',', ';'], true) && abs($i - $middle) < $bestDistance) {
                $split = $i + 1;
                $bestDistance = abs($i - $middle);
            }
        }
        if ($bestDistance > 45) {
            $space = mb_strrpos(mb_substr($summary, 0, min(140, $middle + 15)), ' ');
            $split = $space !== false ? $space : $middle;
        }
        return [rtrim(mb_substr($summary, 0, $split)) . '…', '…' . ltrim(mb_substr($summary, $split))];
    }
}
