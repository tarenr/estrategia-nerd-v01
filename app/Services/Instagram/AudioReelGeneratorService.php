<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/Instagram/AudioReelGeneratorService.php
 * @project     Estrategia Nerd
 * @purpose     Gera vídeo MP4 vertical 1080x1920 (Instagram Reels) a partir de
 *              uma ou mais imagens com trilha sonora cortada e fade-out (FEAT-012)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services\Instagram;

use RuntimeException;

final class AudioReelGeneratorService
{
    private string $ffmpegBinary;
    private string $ffprobeBinary;
    private string $publicRoot;

    public function __construct(
        ?string $ffmpegBinary = null,
        ?string $ffprobeBinary = null,
        ?string $publicRoot = null
    ) {
        $this->ffmpegBinary = $ffmpegBinary ?? (string) config('instagram.ffmpeg_path', 'ffmpeg');
        $this->ffprobeBinary = $ffprobeBinary ?? (string) config('instagram.ffprobe_path', 'ffprobe');
        $this->publicRoot = $publicRoot ?? base_path('public');
    }

    public static function fromGlobals(): self
    {
        return new self();
    }

    /**
     * Cache pronto e presente: publicadores nao voltam a codificar esse MP4.
     * @param array<string, mixed> $post
     */
    public static function readyVideoPath(array $post, string $publicRoot): ?string
    {
        if (($post['render_status'] ?? '') !== 'ready') {
            return null;
        }
        $path = trim((string) ($post['video_rendered_path'] ?? ''));
        if ($path === '' || !str_ends_with(strtolower($path), '.mp4')) {
            return null;
        }
        $root = realpath($publicRoot);
        $full = realpath($publicRoot . '/' . ltrim($path, '/\\'));
        if ($root === false || $full === false || !is_file($full) || filesize($full) === 0) {
            return null;
        }
        $prefix = strtolower(str_replace('\\', '/', $root)) . '/';
        if (!str_starts_with(strtolower(str_replace('\\', '/', $full)), $prefix)) {
            return null;
        }
        return str_replace('\\', '/', $path);
    }

    /**
     * Calcula a duração recomendada em segundos com base na quantidade de imagens.
     * Regra: 4s por imagem, mínimo de 10s e máximo de 30s.
     */
    public static function calculateDuration(int $imageCount): int
    {
        $count = max(1, $imageCount);
        $duration = $count * 4;
        return (int) min(30, max(10, $duration));
    }

    /**
     * Obtém a duração total de um arquivo de áudio via ffprobe em segundos.
     */
    public function getAudioDuration(string $audioPath): float
    {
        $fullPath = $this->resolvePath($audioPath);
        if (!is_file($fullPath)) {
            throw new RuntimeException("Arquivo de áudio não encontrado: {$fullPath}");
        }

        $cmd = sprintf(
            '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s',
            escapeshellcmd($this->ffprobeBinary),
            escapeshellarg($fullPath)
        );

        $output = @shell_exec($cmd);
        $duration = (float) trim((string) $output);

        return max(0.0, $duration);
    }

    /**
     * Verifica se o arquivo informado possui extensão ou tipo de vídeo.
     */
    private function isVideoFile(string $filePath): bool
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (in_array($ext, ['mp4', 'mov', 'webm', 'mkv', 'm4v', 'avi'], true)) {
            return true;
        }

        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($filePath);
            if (is_string($mime) && str_starts_with($mime, 'video/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retorna a duração em segundos de um arquivo de vídeo usando ffprobe.
     */
    public function getVideoDuration(string $path): float
    {
        $fullPath = $this->resolvePath($path);
        if (!is_file($fullPath)) {
            return 0.0;
        }

        $cmdStream = sprintf(
            '%s -v error -select_streams v:0 -show_entries stream=duration -of default=noprint_wrappers=1:nokey=1 %s',
            escapeshellcmd($this->ffprobeBinary),
            escapeshellarg($fullPath)
        );
        $output = @shell_exec($cmdStream);
        $duration = (float) trim((string) $output);

        if ($duration <= 0.0) {
            $duration = $this->getAudioDuration($fullPath);
        }

        return max(0.0, $duration);
    }

    /**
     * Gera um vídeo Reels MP4 (1080x1920) unindo imagens ou 1 vídeo à trilha de áudio.
     *
     * @param list<string> $imagePaths Imagens ou 1 vídeo (caminhos absolutos ou relativos à pasta public)
     * @param string $audioPath Áudio MP3/M4A (caminho absoluto ou relativo à pasta public)
     * @param int $startSeconds Ponto de início do corte do áudio em segundos
     * @param int|null $durationSeconds Duração total do vídeo (null para cálculo automático)
     * @param string|null $outputPath Caminho de saída relativo a public (ex.: uploads/reels/nome.mp4)
     * @return string Caminho relativo gerado (ex.: uploads/reels/reel_20261001_120000_abcd.mp4)
     */
    public function generateReel(
        array $imagePaths,
        string $audioPath,
        int $startSeconds = 0,
        ?int $durationSeconds = null,
        ?string $outputPath = null
    ): string {
        if ($imagePaths === []) {
            throw new RuntimeException('Ao menos uma imagem ou vídeo deve ser fornecido para gerar o Reel.');
        }

        $fullAudioPath = $this->resolvePath($audioPath);
        if (!is_file($fullAudioPath)) {
            throw new RuntimeException("Arquivo de áudio não encontrado: {$fullAudioPath}");
        }

        $resolvedMedias = [];
        $videoCount = 0;
        $imageCount = 0;

        foreach ($imagePaths as $mediaItem) {
            $p = $this->resolvePath($mediaItem);
            if (!is_file($p)) {
                throw new RuntimeException("Arquivo de mídia não encontrado: {$p}");
            }
            if ($this->isVideoFile($p)) {
                $videoCount++;
            } else {
                $imageCount++;
            }
            $resolvedMedias[] = $p;
        }

        if ($videoCount > 0 && $imageCount > 0) {
            throw new RuntimeException('Post com trilha aceita imagens ou 1 vídeo');
        }

        if ($videoCount > 1) {
            throw new RuntimeException('Post com trilha aceita imagens ou 1 vídeo');
        }

        $isVideoInput = ($videoCount === 1);

        if ($isVideoInput) {
            if ($durationSeconds !== null && $durationSeconds > 0) {
                $reelDuration = (int) min(60, max(1, $durationSeconds));
            } else {
                $detectedDur = $this->getVideoDuration($resolvedMedias[0]);
                if ($detectedDur <= 0.0) {
                    $detectedDur = 12.0;
                }
                $reelDuration = (int) min(60, max(1, (int) round($detectedDur)));
            }
        } else {
            $totalImages = count($resolvedMedias);
            $reelDuration = $durationSeconds !== null && $durationSeconds > 0
                ? (int) min(60, max(5, $durationSeconds))
                : self::calculateDuration($totalImages);
        }

        // Clampar o startSeconds para não ultrapassar o áudio disponível
        $audioTotalDuration = $this->getAudioDuration($fullAudioPath);
        if ($audioTotalDuration > 0) {
            $maxStart = max(0, (int) floor($audioTotalDuration - $reelDuration));
            $startSeconds = max(0, min($startSeconds, $maxStart));
        } else {
            $startSeconds = max(0, $startSeconds);
        }

        if ($outputPath === null || trim($outputPath) === '') {
            $relDir = 'uploads/reels';
            $fullDir = $this->publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relDir);
            if (!is_dir($fullDir) && !@mkdir($fullDir, 0755, true) && !is_dir($fullDir)) {
                throw new RuntimeException("Não foi possível criar o diretório de destino: {$fullDir}");
            }
            $filename = 'reel_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.mp4';
            $outputPath = $relDir . '/' . $filename;
        }

        $fullOutputPath = $this->resolvePath($outputPath);
        $outputDir = dirname($fullOutputPath);
        if (!is_dir($outputDir) && !@mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
            throw new RuntimeException("Não foi possível criar o diretório: {$outputDir}");
        }

        // Construir os argumentos de inputs e filtro FFmpeg
        $inputArgs = [];
        $filterParts = [];
        $fadeStartTime = max(0.0, (float) $reelDuration - 1.5);
        $fadeDuration = min(1.5, (float) $reelDuration);

        if ($isVideoInput) {
            // Vídeo único de entrada em scale/pad 1080x1920 a 30fps sem loop
            $inputArgs[] = '-t ' . escapeshellarg((string) $reelDuration);
            $inputArgs[] = '-i ' . escapeshellarg($resolvedMedias[0]);

            $filterParts[] = '[0:v]scale=1080:1920:force_original_aspect_ratio=decrease:force_divisible_by=2,'
                . 'pad=1080:1920:(ow-iw)/2:(oh-ih)/2:color=black,setsar=1,fps=30,format=yuv420p[v]';

            $audioInputIndex = 1;
        } elseif ($totalImages === 1) {
            // Imagem única em loop contínuo
            $inputArgs[] = '-loop 1';
            $inputArgs[] = '-t ' . escapeshellarg((string) $reelDuration);
            $inputArgs[] = '-i ' . escapeshellarg($resolvedMedias[0]);

            $filterParts[] = '[0:v]scale=1080:1920:force_original_aspect_ratio=decrease:force_divisible_by=2,'
                . 'pad=1080:1920:(ow-iw)/2:(oh-ih)/2:color=black,setsar=1,fps=30,format=yuv420p[v]';

            $audioInputIndex = 1;
        } else {
            // Múltiplas imagens (slideshow uniforme)
            $perImageDuration = round($reelDuration / $totalImages, 3);
            foreach ($resolvedMedias as $idx => $img) {
                $inputArgs[] = '-loop 1';
                $inputArgs[] = '-t ' . escapeshellarg((string) $perImageDuration);
                $inputArgs[] = '-i ' . escapeshellarg($img);

                $filterParts[] = sprintf(
                    '[%d:v]scale=1080:1920:force_original_aspect_ratio=decrease:force_divisible_by=2,'
                    . 'pad=1080:1920:(ow-iw)/2:(oh-ih)/2:color=black,setsar=1,fps=30,format=yuv420p[v%d]',
                    $idx,
                    $idx
                );
            }

            $concatInputs = '';
            for ($i = 0; $i < $totalImages; $i++) {
                $concatInputs .= "[v{$i}]";
            }
            $filterParts[] = sprintf('%sconcat=n=%d:v=1:a=0[v]', $concatInputs, $totalImages);

            $audioInputIndex = $totalImages;
        }

        // Áudio cortado com fade-out no final (áudio original do vídeo é descartado)
        $inputArgs[] = '-ss ' . escapeshellarg((string) $startSeconds);
        $inputArgs[] = '-t ' . escapeshellarg((string) $reelDuration);
        $inputArgs[] = '-i ' . escapeshellarg($fullAudioPath);

        $filterParts[] = sprintf(
            '[%d:a]afade=t=out:st=%.2f:d=%.2f[a]',
            $audioInputIndex,
            $fadeStartTime,
            $fadeDuration
        );

        $filterComplex = implode(';', $filterParts);

        $cmd = sprintf(
            '%s -y %s -filter_complex %s -map "[v]" -map "[a]" -c:v libx264 -preset fast -crf 22 -pix_fmt yuv420p -color_range tv -colorspace bt709 -color_primaries bt709 -color_trc bt709 -c:a aac -b:a 128k -ar 48000 -movflags +faststart -t %s %s 2>&1',
            escapeshellcmd($this->ffmpegBinary),
            implode(' ', $inputArgs),
            escapeshellarg($filterComplex),
            escapeshellarg((string) $reelDuration),
            escapeshellarg($fullOutputPath)
        );

        $outputLog = [];
        $exitCode = 0;
        @exec($cmd, $outputLog, $exitCode);

        if ($exitCode !== 0 || !is_file($fullOutputPath) || filesize($fullOutputPath) === 0) {
            $logText = implode("\n", array_slice($outputLog, -15));
            throw new RuntimeException(
                "Falha na renderização do Reel via FFmpeg (código {$exitCode}):\n{$logText}"
            );
        }

        // Gera automaticamente a miniatura pôster .jpg para o vídeo
        $posterPath = preg_replace('/\.mp4$/i', '.jpg', $fullOutputPath);
        if (is_string($posterPath) && !is_file($posterPath)) {
            $thumbCmd = sprintf(
                '%s -y -ss 00:00:00.100 -i %s -vframes 1 -q:v 2 %s 2>&1',
                escapeshellcmd($this->ffmpegBinary),
                escapeshellarg($fullOutputPath),
                escapeshellarg($posterPath)
            );
            @exec($thumbCmd);
        }

        return str_replace('\\', '/', $outputPath);
    }

    /**
     * Gera um Reel completo a partir da capa horizontal do blog, compondo
     * primeiro o Smart Canvas vertical 9:16 editorial e em seguida renderizando
     * o vídeo MP4 com trilha sonora e fade-out.
     *
     * @param string $coverPath Caminho da capa do post (relativo ou absoluto)
     * @param string $audioPath Caminho do áudio
     * @param array<string, mixed> $meta Metadados (categoria, resumo, chamada_titulo, chamada_texto, cta_texto)
     * @param int $startSeconds Início do corte
     * @param int|null $durationSeconds Duração (null = 12s padrão)
     * @param string|null $outputVideoPath
     * @param bool $animated Opt-in para camadas animadas; false preserva Smart Canvas.
     * @return array{video_path: string, canvas_path: string, duration: int}
     */
    public function generateEditorialReel(
        string $coverPath,
        string $audioPath,
        array $meta = [],
        int $startSeconds = 0,
        ?int $durationSeconds = 12,
        ?string $outputVideoPath = null,
        bool $animated = false
    ): array {
        if ($animated) {
            $videoRel = $outputVideoPath ?? ('uploads/reels/motion_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.mp4');
            $motion = new EditorialMotionReelRenderer($this->ffmpegBinary, $this->ffprobeBinary);
            $result = $motion->render($this->resolvePath($coverPath), $this->resolvePath($audioPath), $meta,
                $this->resolvePath($videoRel), $startSeconds, $durationSeconds);
            return [
                'video_path' => str_replace('\\', '/', $videoRel),
                'canvas_path' => str_replace('\\', '/', dirname($videoRel) . '/' . pathinfo($videoRel, PATHINFO_FILENAME) . '.jpg'),
                'duration' => $result['duration'],
            ];
        }
        $renderer = new SmartCanvasRenderer();
        $fullCoverPath = $this->resolvePath($coverPath);

        $canvasRel = 'uploads/instagram/canvas_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.jpg';
        $fullCanvasPath = $this->publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $canvasRel);

        $renderer->renderVerticalEditorial($fullCoverPath, $fullCanvasPath, $meta);

        $videoRel = $this->generateReel(
            [$fullCanvasPath],
            $audioPath,
            $startSeconds,
            $durationSeconds ?? 12,
            $outputVideoPath
        );

        return [
            'video_path' => $videoRel,
            'canvas_path' => $canvasRel,
            'duration' => $durationSeconds ?? 12,
        ];
    }

    private function resolvePath(string $path): string
    {
        $trimmed = trim($path);
        // Se for caminho absoluto no Windows (ex.: C:\...) ou Linux (/...)
        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $trimmed) || str_starts_with($trimmed, '/')) {
            return $trimmed;
        }

        return $this->publicRoot . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $trimmed), '\\/');
    }
}
