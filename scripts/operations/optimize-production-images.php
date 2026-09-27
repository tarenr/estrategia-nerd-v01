<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__) . '/backup/EnvLoader.php';

use Scripts\Backup\EnvLoader;

EnvLoader::load(dirname(__DIR__, 2) . '/.env');

/**
 * -----------------------------------------------------------------------------
 * scripts/operations/optimize-production-images.php
 *
 * Otimizacao em lote de imagens legadas existentes no FTP de producao (IMP-024 / Fase 2).
 *
 * Modos de execucao:
 *   php scripts/operations/optimize-production-images.php --dry-run
 *   php scripts/operations/optimize-production-images.php --apply --limit=5
 *   php scripts/operations/optimize-production-images.php --apply
 *
 * Opcoes adicionais:
 *   --threshold-kb=200       (tamanho minimo em KB para avaliar, padrao: 200)
 *   --min-gain-percent=10    (ganho minimo percentual exigido, padrao: 10)
 *   --min-gain-kb=25         (ganho minimo absoluto em KB, padrao: 25)
 *   --max-dim=2000           (dimensao maxima largura/altura, padrao: 2000)
 *   --force-reprocess        (rebaixa e recomprime mesmo se ja estiver no cache local)
 * -----------------------------------------------------------------------------
 */

class ProductionImageOptimizer
{
    private string $ftpHost;
    private int $ftpPort;
    private string $ftpUser;
    private string $ftpPass;
    private string $ftpRoot;
    private bool $ftpPassive;

    private string $baseDir;
    private string $origDir;
    private string $optDir;
    private string $manifestPath;

    private int $thresholdBytes;
    private float $minGainPercent;
    private int $minGainBytes;
    private int $maxDimension;
    private bool $forceReprocess;

    /** @var resource|null */
    private $ftpConn = null;

    public function __construct(array $options = [])
    {
        $this->ftpHost = (string) ($_ENV['CONTENT_SYNC_PRODUCTION_FTP_HOST'] ?? ($_ENV['BACKUP_PRODUCTION_FTP_HOST'] ?? ''));
        $this->ftpPort = (int) ($_ENV['CONTENT_SYNC_PRODUCTION_FTP_PORT'] ?? ($_ENV['BACKUP_PRODUCTION_FTP_PORT'] ?? 21));
        $this->ftpUser = (string) ($_ENV['CONTENT_SYNC_PRODUCTION_FTP_USERNAME'] ?? ($_ENV['BACKUP_PRODUCTION_FTP_USERNAME'] ?? ''));
        $this->ftpPass = (string) ($_ENV['CONTENT_SYNC_PRODUCTION_FTP_PASSWORD'] ?? ($_ENV['BACKUP_PRODUCTION_FTP_PASSWORD'] ?? ''));
        $this->ftpRoot = rtrim((string) ($_ENV['CONTENT_SYNC_PRODUCTION_FTP_ROOT'] ?? ($_ENV['BACKUP_PRODUCTION_FTP_ROOT'] ?? '')), '/');
        $this->ftpPassive = !in_array(strtolower((string) ($_ENV['CONTENT_SYNC_PRODUCTION_FTP_PASSIVE'] ?? ($_ENV['BACKUP_PRODUCTION_FTP_PASSIVE'] ?? 'true'))), ['0', 'false', 'off', 'no'], true);

        if ($this->ftpHost === '' || $this->ftpUser === '' || $this->ftpRoot === '') {
            throw new RuntimeException('Credenciais de FTP de producao ausentes ou incompletas no .env.');
        }

        $this->baseDir = dirname(__DIR__, 2) . '/storage/tmp/img-optimize';
        $this->origDir = $this->baseDir . '/originais';
        $this->optDir = $this->baseDir . '/otimizadas';
        $this->manifestPath = $this->baseDir . '/manifest.json';

        $thresholdKb = isset($options['threshold-kb']) ? (int) $options['threshold-kb'] : 200;
        $this->thresholdBytes = max(10, $thresholdKb) * 1024;

        $this->minGainPercent = isset($options['min-gain-percent']) ? (float) $options['min-gain-percent'] : 10.0;
        $minGainKb = isset($options['min-gain-kb']) ? (int) $options['min-gain-kb'] : 25;
        $this->minGainBytes = max(5, $minGainKb) * 1024;

        $this->maxDimension = isset($options['max-dim']) ? (int) $options['max-dim'] : 2000;
        $this->forceReprocess = isset($options['force-reprocess']);

        if (!is_dir($this->origDir) && !mkdir($this->origDir, 0775, true) && !is_dir($this->origDir)) {
            throw new RuntimeException("Nao foi possivel criar diretorio de originais: {$this->origDir}");
        }
        if (!is_dir($this->optDir) && !mkdir($this->optDir, 0775, true) && !is_dir($this->optDir)) {
            throw new RuntimeException("Nao foi possivel criar diretorio de otimizadas: {$this->optDir}");
        }
    }

    public function __destruct()
    {
        $this->disconnectFtp();
    }

    private function connectFtp(): void
    {
        if ($this->ftpConn !== null && @ftp_systype($this->ftpConn) !== false) {
            return;
        }

        $this->disconnectFtp();

        $conn = @ftp_connect($this->ftpHost, $this->ftpPort, 30);
        if ($conn === false) {
            throw new RuntimeException("Falha ao conectar ao FTP {$this->ftpHost}:{$this->ftpPort}");
        }

        if (!@ftp_login($conn, $this->ftpUser, $this->ftpPass)) {
            @ftp_close($conn);
            throw new RuntimeException("Falha na autenticacao do usuario FTP {$this->ftpUser}");
        }

        @ftp_pasv($conn, $this->ftpPassive);
        @ftp_set_option($conn, FTP_TIMEOUT_SEC, 90);

        $this->ftpConn = $conn;
    }

    private function disconnectFtp(): void
    {
        if ($this->ftpConn !== null) {
            @ftp_close($this->ftpConn);
            $this->ftpConn = null;
        }
    }

    /**
     * Mapeia recursivamente os arquivos remotos no FTP a partir da raiz de uploads.
     */
    public function scanRemoteFiles(): array
    {
        $this->connectFtp();
        echo "Escaneando diretorio remoto: {$this->ftpRoot} ...\n";
        $results = [];
        $this->scanDirRecursive('', $results);
        echo "Total de arquivos encontrados nos uploads: " . count($results) . "\n";
        return $results;
    }

    private function scanDirRecursive(string $relativeSubDir, array &$results): void
    {
        $targetDir = $this->ftpRoot . ($relativeSubDir !== '' ? '/' . $relativeSubDir : '');
        $rawList = @ftp_rawlist($this->ftpConn, $targetDir);

        if ($rawList === false) {
            return;
        }

        foreach ($rawList as $line) {
            $parts = preg_split('/\s+/', $line, 9);
            if (!isset($parts[8])) {
                continue;
            }

            $perms = $parts[0];
            $name = $parts[8];

            if ($name === '.' || $name === '..') {
                continue;
            }

            $currentRelative = ($relativeSubDir !== '' ? $relativeSubDir . '/' : '') . $name;

            if (str_starts_with($perms, 'd')) {
                $this->scanDirRecursive($currentRelative, $results);
            } elseif (str_starts_with($perms, '-')) {
                $size = (int) ($parts[4] ?? 0);
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    $results[$currentRelative] = [
                        'relative_path' => $currentRelative,
                        'name' => $name,
                        'extension' => $ext,
                        'remote_size' => $size,
                    ];
                }
            }
        }
    }

    /**
     * Executa a etapa Dry-Run:
     * - Baixa imagens candidatas (> threshold).
     * - Processa otimizacao local.
     * - Valida integridade e ganho.
     * - Salva o manifesto imutavel.
     */
    public function runDryRun(): array
    {
        echo "=== INICIANDO DRY-RUN (SOMENTE LEITURA REMOTA) ===\n";
        $files = $this->scanRemoteFiles();

        $candidates = [];
        foreach ($files as $relPath => $info) {
            if ($info['remote_size'] >= $this->thresholdBytes) {
                $candidates[$relPath] = $info;
            }
        }

        echo "Arquivos que atendem ao criterio de tamanho (>= " . ($this->thresholdBytes / 1024) . " KB): " . count($candidates) . "\n\n";

        $manifestItems = [];
        $totalOriginal = 0;
        $totalOptimized = 0;
        $approvedCount = 0;

        foreach ($candidates as $relPath => $info) {
            $localOrig = $this->origDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
            $localOpt = $this->optDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relPath);

            $origDirname = dirname($localOrig);
            if (!is_dir($origDirname)) {
                mkdir($origDirname, 0775, true);
            }
            $optDirname = dirname($localOpt);
            if (!is_dir($optDirname)) {
                mkdir($optDirname, 0775, true);
            }

            // Baixar se nao existir ou se for reprocessamento forcado
            if ($this->forceReprocess || !file_exists($localOrig) || filesize($localOrig) !== $info['remote_size']) {
                $this->connectFtp();
                $remoteFile = $this->ftpRoot . '/' . $relPath;
                echo "Baixando: {$relPath} (" . round($info['remote_size'] / 1024, 1) . " KB) ... ";
                $downloaded = @ftp_get($this->ftpConn, $localOrig, $remoteFile, FTP_BINARY);
                if (!$downloaded) {
                    echo "ERRO NO DOWNLOAD. Pulando.\n";
                    continue;
                }
                echo "OK.\n";
            }

            $origSize = filesize($localOrig);
            $origSha = hash_file('sha256', $localOrig);

            // Processar otimizacao
            $optResult = $this->optimizeLocalImage($localOrig, $localOpt, $info['extension']);

            if (!$optResult['success']) {
                echo "[IGNORADO] {$relPath} - Motivo: {$optResult['reason']}\n";
                $manifestItems[$relPath] = [
                    'relative_path' => $relPath,
                    'extension' => $info['extension'],
                    'status' => 'skipped',
                    'reason' => $optResult['reason'],
                    'original_bytes' => $origSize,
                    'original_sha256' => $origSha,
                    'applied' => false,
                ];
                continue;
            }

            $newSize = filesize($localOpt);
            $newSha = hash_file('sha256', $localOpt);
            $savedBytes = $origSize - $newSize;
            $savedPercent = ($savedBytes / $origSize) * 100.0;

            $approved = ($savedPercent >= $this->minGainPercent && $savedBytes >= $this->minGainBytes)
                || ($savedPercent >= 5.0 && $savedBytes >= 50 * 1024);

            $status = $approved ? 'approved' : 'insufficient_gain';
            if ($approved) {
                $approvedCount++;
                $totalOriginal += $origSize;
                $totalOptimized += $newSize;
                echo sprintf(
                    "[APROVADO] %s | %s -> %s (-%s%% / -%s KB)\n",
                    $relPath,
                    $this->formatBytes($origSize),
                    $this->formatBytes($newSize),
                    number_format($savedPercent, 1),
                    number_format($savedBytes / 1024, 1)
                );
            } else {
                echo sprintf(
                    "[INSUFICIENTE] %s | Ganho de apenas -%s%% / -%s KB (exige >= %s%% e >= %s KB, ou >= 5%% e >= 50 KB)\n",
                    $relPath,
                    number_format($savedPercent, 1),
                    number_format($savedBytes / 1024, 1),
                    $this->minGainPercent,
                    number_format($this->minGainBytes / 1024, 1)
                );
            }

            $manifestItems[$relPath] = [
                'relative_path' => $relPath,
                'extension' => $info['extension'],
                'status' => $status,
                'original_bytes' => $origSize,
                'optimized_bytes' => $newSize,
                'saved_bytes' => $savedBytes,
                'saved_percent' => round($savedPercent, 2),
                'original_sha256' => $origSha,
                'optimized_sha256' => $newSha,
                'original_dims' => $optResult['orig_dims'],
                'optimized_dims' => $optResult['opt_dims'],
                'applied' => false,
            ];
        }

        $totalSaved = $totalOriginal - $totalOptimized;
        $totalSavedPercent = $totalOriginal > 0 ? ($totalSaved / $totalOriginal) * 100.0 : 0.0;

        $manifestData = [
            'generated_at' => date('Y-m-d H:i:s'),
            'profile' => 'production',
            'ftp_root' => $this->ftpRoot,
            'config' => [
                'threshold_bytes' => $this->thresholdBytes,
                'min_gain_percent' => $this->minGainPercent,
                'min_gain_bytes' => $this->minGainBytes,
                'max_dimension' => $this->maxDimension,
            ],
            'summary' => [
                'total_scanned_images' => count($files),
                'total_candidates' => count($candidates),
                'approved_for_optimization' => $approvedCount,
                'total_original_bytes' => $totalOriginal,
                'total_optimized_bytes' => $totalOptimized,
                'total_saved_bytes' => $totalSaved,
                'saved_percent' => round($totalSavedPercent, 2),
            ],
            'items' => $manifestItems,
        ];

        file_put_contents($this->manifestPath, json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        echo "\n" . str_repeat('=', 60) . "\n";
        echo "RESUMO DO DRY-RUN:\n";
        echo "- Imagens totais escaneadas: " . count($files) . "\n";
        echo "- Imagens avaliadas (>= " . ($this->thresholdBytes / 1024) . " KB): " . count($candidates) . "\n";
        echo "- Imagens aprovadas para otimizacao: {$approvedCount}\n";
        echo "- Espaco original aprovado: " . $this->formatBytes($totalOriginal) . "\n";
        echo "- Espaco apos otimizacao: " . $this->formatBytes($totalOptimized) . "\n";
        echo "- Economia liquida prevista: " . $this->formatBytes($totalSaved) . " (-" . number_format($totalSavedPercent, 1) . "%)\n";
        echo "- Manifesto gerado em: {$this->manifestPath}\n";
        echo str_repeat('=', 60) . "\n";

        return $manifestData;
    }

    /**
     * Otimizacao e validacao rigorosa da imagem local usando GD.
     */
    private function optimizeLocalImage(string $sourcePath, string $targetPath, string $extension): array
    {
        // 1. Checagem finfo de MIME
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($sourcePath);

        $allowedMimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];

        if (!isset($allowedMimes[$extension]) || $mime !== $allowedMimes[$extension]) {
            return ['success' => false, 'reason' => "MIME divergente da extensao ({$mime})"];
        }

        // 2. Protecao contra animacao (APNG ou WebP animado)
        if ($extension === 'png') {
            $handle = fopen($sourcePath, 'rb');
            if ($handle) {
                $header = fread($handle, 1024);
                fclose($handle);
                if (str_contains($header, 'acTL')) {
                    return ['success' => false, 'reason' => 'Detectado APNG (animado). Excluido por seguranca.'];
                }
            }
        } elseif ($extension === 'webp') {
            $handle = fopen($sourcePath, 'rb');
            if ($handle) {
                $header = fread($handle, 64);
                fclose($handle);
                if (str_contains($header, 'ANIM')) {
                    return ['success' => false, 'reason' => 'Detectado WebP animado. Excluido por seguranca.'];
                }
            }
        }

        // 3. getimagesize()
        $info = @getimagesize($sourcePath);
        if ($info === false) {
            return ['success' => false, 'reason' => 'Falha ao ler dimensoes da imagem'];
        }

        [$width, $height, $imageType] = $info;
        if ($width <= 0 || $height <= 0) {
            return ['success' => false, 'reason' => 'Dimensoes invalidas (<= 0)'];
        }

        // 4. Decodificacao GD completa
        $source = match ($imageType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };

        if (!$source) {
            return ['success' => false, 'reason' => 'Falha ao decodificar imagem no GD'];
        }

        // 5. Tratar EXIF orientation para JPEG
        if ($imageType === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($sourcePath);
            $orientation = (int) ($exif['Orientation'] ?? 1);
            if ($orientation === 3) {
                $source = imagerotate($source, 180, 0);
            } elseif ($orientation === 6) {
                $source = imagerotate($source, -90, 0);
                [$width, $height] = [$height, $width];
            } elseif ($orientation === 8) {
                $source = imagerotate($source, 90, 0);
                [$width, $height] = [$height, $width];
            }
        }

        // 6. Preservar canal alfa apenas se a imagem realmente contiver canal alfa
        $hasAlpha = false;
        if ($imageType === IMAGETYPE_PNG) {
            $fp = fopen($sourcePath, 'rb');
            if ($fp) {
                $hdr = fread($fp, 30);
                fclose($fp);
                $colorType = isset($hdr[25]) ? ord($hdr[25]) : 0;
                $hasAlpha = in_array($colorType, [4, 6], true);
            }
        } elseif ($imageType === IMAGETYPE_WEBP) {
            $hasAlpha = true;
        }

        if ($hasAlpha) {
            imagealphablending($source, false);
            imagesavealpha($source, true);
        }

        // 7. Redimensionamento se necessario
        $newWidth = $width;
        $newHeight = $height;
        $finalImage = $source;

        if ($width > $this->maxDimension || $height > $this->maxDimension) {
            $scale = $this->maxDimension / max($width, $height);
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            if ($hasAlpha) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transColor = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefill($resized, 0, 0, $transColor);
            }

            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($source);
            $finalImage = $resized;
        }

        // 8. Gravar saida
        $saved = match ($imageType) {
            IMAGETYPE_JPEG => imagejpeg($finalImage, $targetPath, 82),
            IMAGETYPE_PNG => imagepng($finalImage, $targetPath, 9),
            IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($finalImage, $targetPath, 82) : false,
            default => false,
        };

        imagedestroy($finalImage);

        if (!$saved || !file_exists($targetPath) || filesize($targetPath) === 0) {
            return ['success' => false, 'reason' => 'Falha ao salvar imagem otimizada em disco'];
        }

        // 9. Validar a saida gerada
        $targetInfo = @getimagesize($targetPath);
        if ($targetInfo === false || $targetInfo[0] !== $newWidth || $targetInfo[1] !== $newHeight) {
            @unlink($targetPath);
            return ['success' => false, 'reason' => 'Validacao da imagem gerada falhou'];
        }

        return [
            'success' => true,
            'orig_dims' => [$width, $height],
            'opt_dims' => [$newWidth, $newHeight],
        ];
    }

    /**
     * Aplica os arquivos aprovados do manifesto ao FTP remoto.
     */
    public function runApply(?int $limit = null): void
    {
        if (!file_exists($this->manifestPath)) {
            throw new RuntimeException("Manifesto nao encontrado em {$this->manifestPath}. Execute o --dry-run primeiro.");
        }

        $manifest = json_decode((string) file_get_contents($this->manifestPath), true);
        if (!is_array($manifest) || !isset($manifest['items'])) {
            throw new RuntimeException("Manifesto corrompido ou invalido.");
        }

        echo "=== INICIANDO APLICACAO EM PRODUCAO VIA FTP ===\n";
        if ($limit !== null) {
            echo "LOTE LIMITADO: maximo de {$limit} imagens serao aplicadas nesta execucao.\n";
        }

        $this->connectFtp();

        $items = $manifest['items'];
        $appliedCount = 0;
        $appliedBytesSaved = 0;
        $urlsToVerify = [];

        foreach ($items as $relPath => &$item) {
            if (($item['status'] ?? '') !== 'approved') {
                continue;
            }

            if (($item['applied'] ?? false) === true) {
                continue;
            }

            if ($limit !== null && $appliedCount >= $limit) {
                break;
            }

            $localOptPath = $this->optDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
            if (!file_exists($localOptPath)) {
                echo "[ERRO] Arquivo otimizado local nao encontrado: {$localOptPath}\n";
                continue;
            }

            $remoteTarget = $this->ftpRoot . '/' . $relPath;
            $remoteTmp = $remoteTarget . '.tmp_opt';

            echo "Enviando: {$relPath} ... ";

            // 1. Upload para arquivo temporario remoto
            $uploaded = @ftp_put($this->ftpConn, $remoteTmp, $localOptPath, FTP_BINARY);
            if (!$uploaded) {
                echo "FALHA NO UPLOAD DO TEMPORARIO. Pulando.\n";
                continue;
            }

            // 2. Conferir tamanho do temporario no servidor
            $remoteSize = @ftp_size($this->ftpConn, $remoteTmp);
            $localSize = filesize($localOptPath);
            if ($remoteSize !== $localSize) {
                echo "ERRO: Tamanho divergente no servidor (local: {$localSize}, remoto: {$remoteSize}). Removendo temporario.\n";
                @ftp_delete($this->ftpConn, $remoteTmp);
                continue;
            }

            // 3. Substituir no arquivo final (tentar rename; se servidor falhar sobrescrever via rename, usar ftp_put direto no destino validado)
            $renamed = @ftp_rename($this->ftpConn, $remoteTmp, $remoteTarget);
            if (!$renamed) {
                // Tenta sobrescrever via ftp_put direto e limpa o .tmp
                @ftp_delete($this->ftpConn, $remoteTmp);
                $directUpload = @ftp_put($this->ftpConn, $remoteTarget, $localOptPath, FTP_BINARY);
                if (!$directUpload) {
                    echo "FALHA AO SUBSTITUIR NO SERVIDOR.\n";
                    continue;
                }
            }

            // 4. Conferir tamanho final
            $finalRemoteSize = @ftp_size($this->ftpConn, $remoteTarget);
            if ($finalRemoteSize !== $localSize) {
                echo "ALERTA: Tamanho final divergente no destino: {$finalRemoteSize} vs {$localSize}.\n";
                continue;
            }

            echo "SUCESSO (" . $this->formatBytes($item['original_bytes']) . " -> " . $this->formatBytes($localSize) . ")\n";

            $item['applied'] = true;
            $item['applied_at'] = date('Y-m-d H:i:s');
            $appliedCount++;
            $appliedBytesSaved += (int) ($item['saved_bytes'] ?? 0);
            $urlsToVerify[] = 'https://estrategianerd.com.br/uploads/' . $relPath;

            // Salvar manifesto a cada arquivo aplicado para idempotencia
            file_put_contents($this->manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
        unset($item);

        echo "\n" . str_repeat('=', 60) . "\n";
        echo "LOTE CONCLUIDO:\n";
        echo "- Imagens aplicadas nesta execucao: {$appliedCount}\n";
        echo "- Espaco economizado nesta execucao: " . $this->formatBytes($appliedBytesSaved) . "\n";
        echo str_repeat('=', 60) . "\n\n";

        // Smoke test das URLs publicas
        if (!empty($urlsToVerify)) {
            echo "Executando smoke test HTTP GET nas URLs publicas aplicadas...\n";
            $this->smokeTestUrls($urlsToVerify);
        }
    }

    private function smokeTestUrls(array $urls): void
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $passed = 0;
        $failed = 0;

        foreach ($urls as $url) {
            curl_setopt($ch, CURLOPT_URL, $url);
            $body = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $contentLength = strlen((string) $body);

            if ($httpCode === 200 && $contentLength > 0 && str_starts_with((string) $contentType, 'image/')) {
                echo "[HTTP 200 OK] {$url} ({$contentType}, " . $this->formatBytes($contentLength) . ")\n";
                $passed++;
            } else {
                echo "[FALHA HTTP {$httpCode}] {$url} (Tipo: {$contentType}, Bytes: {$contentLength})\n";
                $failed++;
            }
        }

        curl_close($ch);
        echo "Resultado dos testes HTTP: {$passed} aprovados, {$failed} falhas.\n";
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        }
        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }
}

// -----------------------------------------------------------------------------
// Ponto de entrada CLI
// -----------------------------------------------------------------------------
$options = [];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--')) {
        $parts = explode('=', substr($arg, 2), 2);
        $options[$parts[0]] = $parts[1] ?? true;
    }
}

$isDryRun = isset($options['dry-run']) || (!isset($options['apply']) && !isset($options['dry-run']));
$isApply = isset($options['apply']);
$limit = isset($options['limit']) ? (int) $options['limit'] : null;

try {
    $optimizer = new ProductionImageOptimizer($options);

    if ($isApply) {
        $optimizer->runApply($limit);
    } else {
        $optimizer->runDryRun();
    }
} catch (Throwable $e) {
    echo "\nERRO FATAL: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
