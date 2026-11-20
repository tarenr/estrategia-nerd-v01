<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/Instagram/InstagramStudioService.php
 * @project     Estrategia Nerd
 * @purpose     Serviço de ponte entre o admin PHP e o motor Instagram Studio (instagrapi)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services\Instagram;

use App\Support\EnvironmentGuard;
use RuntimeException;
use Throwable;

final class InstagramStudioService
{
    private string $projectRoot;
    private string $pythonBinary;
    private string $cliScript;
    private string $storageRoot;
    private string $tempDir;

    public function __construct()
    {
        EnvironmentGuard::requireLocal();

        $this->projectRoot = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, dirname(__DIR__, 3));
        $this->pythonBinary = $this->projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'instagram-highlights' . DIRECTORY_SEPARATOR . 'venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe';
        $this->cliScript = $this->projectRoot . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'instagram-studio' . DIRECTORY_SEPARATOR . 'cli.py';
        $this->storageRoot = $this->projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'instagram-highlights';
        $this->tempDir = $this->projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'instagram-studio';

        if (!is_dir($this->tempDir)) {
            @mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Consulta status da conexão e sessão com o Instagram.
     *
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        return $this->callCli('status');
    }

    /**
     * Busca faixas musicais do catálogo do Instagram por termo.
     *
     * @param string $query
     * @return array<string, mixed>
     */
    public function searchMusic(string $query): array
    {
        return $this->callCli('search_music', ['query' => $query]);
    }

    /**
     * Busca músicas que estão em alta / tendências no Instagram.
     *
     * @return array<string, mixed>
     */
    public function getTrendingMusic(): array
    {
        return $this->callCli('trending_music');
    }

    /**
     * Publica foto ou carrossel no feed com áudio opcional.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function uploadFeed(array $payload): array
    {
        return $this->callCli('upload_feed', $payload);
    }

    /**
     * Obtém informações detalhadas do perfil e posts recentes.
     *
     * @return array<string, mixed>
     */
    public function getProfile(): array
    {
        return $this->callCli('get_profile');
    }

    /**
     * Atualiza a biografia e/ou o link externo na bio.
     *
     * @param string|null $bio
     * @param string|null $externalUrl
     * @return array<string, mixed>
     */
    public function updateProfile(?string $bio, ?string $externalUrl): array
    {
        return $this->callCli('update_profile', [
            'biography' => $bio,
            'external_url' => $externalUrl
        ]);
    }

    /**
     * Fixa ou desafixa um post no topo do feed.
     *
     * @param string $mediaId
     * @param bool $pin
     * @return array<string, mixed>
     */
    public function pinMedia(string $mediaId, bool $pin = true): array
    {
        return $this->callCli('pin_media', [
            'media_id' => $mediaId,
            'pin' => $pin
        ]);
    }

    /**
     * Envia uma mensagem direta (DM) para um usuário.
     *
     * @param string $username
     * @param string $text
     * @return array<string, mixed>
     */
    public function sendDm(string $username, string $text): array
    {
        return $this->callCli('send_dm', [
            'username' => $username,
            'text' => $text
        ]);
    }

    /**
     * Minera posts virais por hashtag ou usuário para benchmarking.
     *
     * @param string $target
     * @param bool $isHashtag
     * @param int $amount
     * @return array<string, mixed>
     */
    public function searchViral(string $target, bool $isHashtag = false, int $amount = 9): array
    {
        return $this->callCli('search_viral', [
            'target' => $target,
            'is_hashtag' => $isHashtag,
            'amount' => $amount
        ]);
    }

    /**
     * Compila métricas da conta: engajamento médio, ranking dos top posts e comparativo de formatos.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        return $this->callCli('metrics');
    }

    /**
     * Obtém espectadores dos stories ativos.
     *
     * @return array<string, mixed>
     */
    public function getStoryViewers(): array
    {
        return $this->callCli('story_viewers');
    }

    /**
     * Executa a CLI do Python de forma segura via proc_open.
     *
     * @param string $action
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function callCli(string $action, array $payload = []): array
    {
        if (!is_file($this->pythonBinary)) {
            return [
                'ok' => false,
                'message' => 'Ambiente virtual Python do Instagrapi não encontrado em ' . $this->pythonBinary
            ];
        }

        if (!is_file($this->cliScript)) {
            return [
                'ok' => false,
                'message' => 'Script da CLI do Instagram Studio não encontrado em ' . $this->cliScript
            ];
        }

        $cmd = [
            $this->pythonBinary,
            $this->cliScript,
            $action,
            '--root',
            $this->storageRoot
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $options = [
            'bypass_shell' => true,
        ];
        if (DIRECTORY_SEPARATOR === '\\') {
            $options['create_no_window'] = true;
        }

        $env = array_merge($_SERVER, [
            'PYTHONIOENCODING' => 'utf-8',
            'PYTHONUTF8' => '1',
        ]);

        $process = @proc_open($cmd, $descriptors, $pipes, $this->projectRoot, $env, $options);
        if (!is_resource($process)) {
            return [
                'ok' => false,
                'message' => 'Não foi possível inicializar o processo do Instagram Studio.'
            ];
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        @fwrite($pipes[0], $jsonPayload);
        @fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';

        @fclose($pipes[1]);
        @fclose($pipes[2]);
        $exitCode = proc_close($process);

        $trimmedStdout = trim($stdout);
        if ($trimmedStdout !== '') {
            $decoded = json_decode($trimmedStdout, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
            if (is_array($decoded)) {
                return $decoded;
            }
            return [
                'ok' => false,
                'message' => 'Falha ao decodificar JSON: ' . json_last_error_msg() . ' | Raw: ' . mb_substr($trimmedStdout, 0, 200)
            ];
        }

        $errClean = trim($stderr);
        return [
            'ok' => false,
            'message' => $errClean !== '' ? 'Falha no processo: ' . mb_substr($errClean, 0, 200) : 'Resposta vazia ou inválida do motor.'
        ];
    }
}
