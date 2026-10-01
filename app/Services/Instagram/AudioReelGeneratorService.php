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
     * Gera um vídeo Reels MP4 (1080x1920) unindo imagens à trilha de áudio.
     *
     * @param list<string> $imagePaths Imagens (caminhos absolutos ou relativos à pasta public)
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
            throw new RuntimeException('Ao menos uma imagem deve ser fornecida para gerar o Reel.');
        }

        $fullAudioPath = $this->resolvePath($audioPath);
        if (!is_file($fullAudioPath)) {
            throw new RuntimeException("Arquivo de áudio não encontrado: {$fullAudioPath}");
        }

        $resolvedImages = [];
        foreach ($imagePaths as $img) {
            $p = $this->resolvePath($img);
            if (!is_file($p)) {
                throw new RuntimeException("Arquivo de imagem não encontrado: {$p}");
            }
            $resolvedImages[] = $p;
        }

        $totalImages = count($resolvedImages);
        $reelDuration = $durationSeconds !== null && $durationSeconds > 0
            ? (int) min(60, max(5, $durationSeconds))
            : self::calculateDuration($totalImages);

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

        if ($totalImages === 1) {
            // Imagem única em loop contínuo
            $inputArgs[] = '-loop 1';
            $inputArgs[] = '-t ' . escapeshellarg((string) $reelDuration);
            $inputArgs[] = '-i ' . escapeshellarg($resolvedImages[0]);

            $filterParts[] = '[0:v]scale=1080:1920:force_original_aspect_ratio=decrease:force_divisible_by=2,'
                . 'pad=1080:1920:(ow-iw)/2:(oh-ih)/2:color=black,setsar=1,fps=30,format=yuv420p[v]';

            $audioInputIndex = 1;
        } else {
            // Múltiplas imagens (slideshow uniforme)
            $perImageDuration = round($reelDuration / $totalImages, 3);
            foreach ($resolvedImages as $idx => $img) {
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

        // Áudio cortado com fade-out de 1.5s no final
        $inputArgs[] = '-ss ' . escapeshellarg((string) $startSeconds);
        $inputArgs[] = '-t ' . escapeshellarg((string) $reelDuration);
        $inputArgs[] = '-i ' . escapeshellarg($fullAudioPath);

        $filterParts[] = sprintf(
            '[%d:a]afade=t=out:st=%.2f:d=1.5[a]',
            $audioInputIndex,
            $fadeStartTime
        );

        $filterComplex = implode(';', $filterParts);

        $cmd = sprintf(
            '%s -y %s -filter_complex %s -map "[v]" -map "[a]" -c:v libx264 -preset fast -crf 22 -pix_fmt yuv420p -c:a aac -b:a 128k -ar 48000 -movflags +faststart -shortest %s 2>&1',
            escapeshellcmd($this->ffmpegBinary),
            implode(' ', $inputArgs),
            escapeshellarg($filterComplex),
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

        return str_replace('\\', '/', $outputPath);
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
