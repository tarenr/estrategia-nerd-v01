<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/Instagram/InstagramHighlightsService.php
 * @project     Estrategia Nerd
 * @purpose     Serviço de ponte com o motor Python de Stories e Destaques (instagrapi)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services\Instagram;

use App\Support\EnvironmentGuard;
use RuntimeException;

final class InstagramHighlightsService
{
    private string $projectRoot;
    private string $pythonBinary;
    private string $cliScript;
    private string $storageRoot;
    private string $assetsDir;
    private float $processTimeoutSeconds = 60.0;
    private const MAX_PROCESS_BYTES = 1048576;

    public function __construct()
    {
        EnvironmentGuard::requireLocal();

        $this->projectRoot = dirname(__DIR__, 3);
        $this->pythonBinary = $this->projectRoot . '/storage/instagram-highlights/venv/Scripts/python.exe';
        $this->cliScript = $this->projectRoot . '/tools/instagram-highlights/cli.py';
        $this->storageRoot = $this->projectRoot . '/storage/instagram-highlights';
        $this->assetsDir = $this->storageRoot . '/assets';

        if (!is_dir($this->assetsDir)) {
            @mkdir($this->assetsDir, 0755, true);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        return $this->callCli('status');
    }

    /**
     * @param string $jobId
     * @return array<string, mixed>
     */
    public function getJob(string $jobId): array
    {
        $this->validateHex32($jobId, 'Identificador da operação inválido.');
        return $this->callCli('job', ['job_id' => $jobId]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function prepare(array $payload): array
    {
        return $this->callCli('prepare', $payload);
    }

    /**
     * @param string $jobId
     * @return array<string, mixed>
     */
    public function step(string $jobId): array
    {
        $this->validateHex32($jobId, 'Identificador da operação inválido.');
        return $this->callCli('step', ['job_id' => $jobId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        return $this->callCli('catalog');
    }

    /**
     * @param string $jobId
     * @param string|null $remoteId
     * @param bool $verifiedVisual
     * @return array<string, mixed>
     */
    public function reconcile(string $jobId, ?string $remoteId, bool $verifiedVisual): array
    {
        $this->validateHex32($jobId, 'Identificador da operação inválido.');
        return $this->callCli('reconcile', [
            'job_id' => $jobId,
            'remote_id' => $remoteId,
            'verified_visual' => $verifiedVisual,
        ]);
    }

    /**
     * Upload de arquivo para story ou capa com validação estrita.
     *
     * @param array<string, mixed> $file
     * @param string $kind 'story'|'cover'
     * @return array{id: string, width: int, height: int, url: string}
     */
    public function uploadAsset(array $file, string $kind): array
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha no upload do arquivo. Código de erro: ' . ($file['error'] ?? 'indefinido'));
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Arquivo de upload inválido ou corrompido.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 8 * 1024 * 1024) {
            throw new RuntimeException('O arquivo excede o limite máximo permitido de 8 MB.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? finfo_file($finfo, $tmpPath) : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if ($mime !== 'image/jpeg') {
            throw new RuntimeException('Formato de imagem inválido. Somente arquivos JPEG (.jpg, .jpeg) são permitidos.');
        }

        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false || $imageInfo[2] !== IMAGETYPE_JPEG) {
            throw new RuntimeException('O arquivo enviado não é um JPEG válido.');
        }

        $width = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];

        if ($kind === 'story') {
            if ($width !== 1080 || $height !== 1920) {
                throw new RuntimeException("Dimensões incorretas para Story. Enviado: {$width}×{$height}px. Obrigatório: 1080×1920px (9:16).");
            }
        } elseif ($kind === 'cover') {
            if ($width !== $height || $width < 320 || $width > 2160) {
                throw new RuntimeException("A capa deve ser uma imagem quadrada entre 320×320px e 2160×2160px. Enviado: {$width}×{$height}px.");
            }
        } else {
            throw new RuntimeException("Tipo de imagem '{$kind}' não reconhecido.");
        }

        $assetId = bin2hex(random_bytes(16));
        $destPath = $this->assetsDir . '/' . $assetId . '.jpg';

        if (!move_uploaded_file($tmpPath, $destPath)) {
            throw new RuntimeException('Não foi possível gravar o arquivo na biblioteca de ativos privados.');
        }

        return [
            'id' => $assetId,
            'width' => $width,
            'height' => $height,
            'url' => url('/admin/instagram-destaques/media/' . $assetId),
        ];
    }

    /**
     * Entrega imagem da biblioteca privada com validação de caminho.
     */
    public function serveAsset(string $assetId): void
    {
        $this->validateHex32($assetId, 'Identificador de imagem inválido.');

        $safePath = realpath($this->assetsDir . '/' . $assetId . '.jpg');
        $expectedDir = realpath($this->assetsDir);

        if ($safePath === false || $expectedDir === false || !str_starts_with($safePath, $expectedDir) || !is_file($safePath)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Imagem não encontrada.';
            exit;
        }

        header('Content-Type: image/jpeg');
        header('Content-Length: ' . (string) filesize($safePath));
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        readfile($safePath);
        exit;
    }

    /**
     * Executa a CLI do Python via proc_open com isolamento estrito.
     *
     * @param string $action
     * @param array<string, mixed> $stdinData
     * @return array<string, mixed>
     */
    private function callCli(string $action, array $stdinData = []): array
    {
        if (!is_file($this->pythonBinary)) {
            throw new RuntimeException('Ambiente Python indisponível. Consulte o procedimento de instalação na Central Técnica.');
        }

        if (!is_file($this->cliScript)) {
            throw new RuntimeException('Motor de Stories indisponível. Confira a instalação local.');
        }

        // Fecha a sessão PHP antes de bloquear em subprocesso para não travar outras abas
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $cmd = [
            $this->pythonBinary,
            $this->cliScript,
            $action,
            '--root',
            $this->storageRoot,
        ];

        $jsonPayload = json_encode($stdinData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($jsonPayload === false || strlen($jsonPayload) > self::MAX_PROCESS_BYTES) {
            throw new RuntimeException('Dados da operação inválidos ou acima do limite permitido.');
        }

        // PHP/Windows não oferece pipes de processo confiavelmente não bloqueantes.
        // Arquivos privados permitem drenar ambas as saídas sem deadlock e aplicar
        // um prazo de parede, inclusive quando o filho não escreve nada.
        $temporaryFiles = [];
        $readers = [];
        $process = null;
        try {
            foreach (['input', 'output', 'error'] as $name) {
                $path = tempnam($this->storageRoot, 'bridge-' . $name . '-');
                if ($path === false || realpath(dirname($path)) !== realpath($this->storageRoot)) {
                    if (is_string($path)) {
                        @unlink($path);
                    }
                    throw new RuntimeException('Não foi possível preparar a comunicação privada com o motor.');
                }
                $temporaryFiles[] = $path;
                @chmod($path, 0600);
            }
            if (file_put_contents($temporaryFiles[0], $jsonPayload) !== strlen($jsonPayload)) {
                throw new RuntimeException('Não foi possível preparar os dados da operação.');
            }
            $descriptors = [
                0 => ['file', $temporaryFiles[0], 'rb'],
                1 => ['file', $temporaryFiles[1], 'wb'],
                2 => ['file', $temporaryFiles[2], 'wb'],
            ];
            foreach ([1, 2] as $index) {
                $reader = fopen($temporaryFiles[$index], 'rb');
                if ($reader === false) {
                    throw new RuntimeException('Não foi possível iniciar a leitura da resposta do motor.');
                }
                $readers[$index] = $reader;
            }
            $deadline = microtime(true) + $this->processTimeoutSeconds;
            $process = proc_open($cmd, $descriptors, $pipes, $this->projectRoot, null, ['bypass_shell' => true, 'create_process_group' => true]);
            if (!is_resource($process)) {
                throw new RuntimeException('Falha ao inicializar o motor de Stories.');
            }

            $stdout = '';
            $totalRead = 0;
            do {
                $status = proc_get_status($process);
                foreach ($readers as $index => $reader) {
                    // Um read limitado por fluxo a cada volta; stderr é descartado.
                    $chunk = fread($reader, 65536);
                    if ($chunk === false) {
                        throw new RuntimeException('Resposta do motor indisponível. Confira a operação antes de continuar.');
                    }
                    $totalRead += strlen($chunk);
                    $size = fstat($reader);
                    if ($totalRead > self::MAX_PROCESS_BYTES || ($size !== false && $size['size'] > self::MAX_PROCESS_BYTES)) {
                        throw new RuntimeException('Resposta do motor excedeu o limite. Resultado incerto; confira a operação antes de continuar.');
                    }
                    if ($index === 1) {
                        $stdout .= $chunk;
                    }
                }
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Tempo limite do motor excedido. Resultado incerto; consulte a operação e confira o Instagram antes de continuar.');
                }
                if (!$status['running']) {
                    // Drenar o restante já gravado sem exceder o limite total.
                    foreach ($readers as $index => $reader) {
                        $chunk = stream_get_contents($reader, self::MAX_PROCESS_BYTES + 1 - $totalRead);
                        $totalRead += strlen((string) $chunk);
                        if ($index === 1) {
                            $stdout .= (string) $chunk;
                        }
                    }
                    if ($totalRead > self::MAX_PROCESS_BYTES) {
                        throw new RuntimeException('Resposta do motor excedeu o limite. Confira a operação antes de continuar.');
                    }
                    if ($status['exitcode'] !== 0) {
                        throw new RuntimeException('O motor encerrou com falha. Confira o estado da operação antes de continuar.');
                    }
                    break;
                }
                usleep(20000);
            } while (true);

            $decoded = json_decode(trim($stdout), true);
            if (!is_array($decoded) || !isset($decoded['ok']) || !is_bool($decoded['ok'])) {
                throw new RuntimeException('Resposta inválida do motor. Confira o estado da operação antes de continuar.');
            }
            return $decoded;
        } finally {
            if (is_resource($process)) {
                $status = proc_get_status($process);
                if ($status['running']) {
                    // proc_open ignora o shell; no Windows, proc_terminate encerra
                    // diretamente o Python via TerminateProcess (nenhum taskkill genérico).
                    proc_terminate($process);
                    $stopDeadline = microtime(true) + 0.5;
                    do {
                        usleep(10000);
                        $status = proc_get_status($process);
                    } while ($status['running'] && microtime(true) < $stopDeadline);
                    if ($status['running']) {
                        proc_terminate($process, 9);
                    }
                }
                proc_close($process);
            }
            foreach ($readers as $reader) {
                fclose($reader);
            }
            foreach ($temporaryFiles as $path) {
                @unlink($path);
            }
        }
    }

    private function validateHex32(string $value, string $message): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $value)) {
            throw new RuntimeException($message);
        }
    }
}
