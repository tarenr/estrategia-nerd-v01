<?php

declare(strict_types=1);

namespace App\Services\YouTube;

use Throwable;

final class YouTubeRadarService
{
    private string $pythonBinary;
    private string $scriptPath;
    private string $cacheDir;

    public function __construct()
    {
        $baseDir = dirname(__DIR__, 3);
        $venvPython = $baseDir . '/storage/instagram-highlights/venv/Scripts/python.exe';
        
        $this->pythonBinary = file_exists($venvPython) ? $venvPython : 'python';
        $this->scriptPath = $baseDir . '/tools/youtube-radar/radar.py';
        $this->cacheDir = $baseDir . '/storage/cache/youtube-radar';

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0775, true);
        }
    }

    /**
     * Termos sugeridos de alta tração no nicho nerd/tech para atalhos rápidos de pesquisa.
     *
     * @return list<string>
     */
    public function suggestedKeywords(): array
    {
        return [
            'Setup Gamer',
            'Hardware Custo Benefício',
            'Curiosidades de Games',
            'Easter Eggs de Jogos',
            'Inteligência Artificial Gadgets',
            'Periféricos Baratos',
            'Nostalgia Anos 90 Games',
            'Placa de Vídeo Comparativo',
        ];
    }

    /**
     * Busca vídeos ou shorts usando a InnerTube API sem consumir quota.
     *
     * @param string $query
     * @param string $type all|shorts|videos
     * @param int $limit
     * @param bool $bypassCache
     * @return array<string, mixed>
     */
    public function search(string $query, string $type = 'all', int $limit = 24, bool $bypassCache = false): array
    {
        $query = trim($query);
        if ($query === '') {
            $query = 'setup gamer';
        }

        $cacheKey = 'search_' . md5($query . '_' . $type . '_' . $limit);
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.json';

        // Cache de 3 horas (10800 segundos)
        if (!$bypassCache && file_exists($cacheFile) && (time() - filemtime($cacheFile) < 10800)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached) && !empty($cached['ok'])) {
                $cached['from_cache'] = true;
                return $cached;
            }
        }

        $cmd = sprintf(
            '%s %s --action search --query %s --type %s --limit %d',
            escapeshellarg($this->pythonBinary),
            escapeshellarg($this->scriptPath),
            escapeshellarg($query),
            escapeshellarg($type),
            $limit
        );

        $output = $this->executeCommand($cmd);
        $data = json_decode($output, true);

        if (is_array($data) && !empty($data['ok'])) {
            file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $data['from_cache'] = false;
            return $data;
        }

        return [
            'ok' => false,
            'query' => $query,
            'total' => 0,
            'items' => [],
            'error' => $data['error'] ?? 'Não foi possível carregar os resultados da busca.',
        ];
    }

    /**
     * Extrai a transcrição completa e os blocos temporais do vídeo.
     *
     * @param string $videoId
     * @return array<string, mixed>
     */
    public function getTranscript(string $videoId): array
    {
        $videoId = trim($videoId);
        if ($videoId === '') {
            return ['ok' => false, 'error' => 'ID do vídeo não informado.'];
        }

        // Sanitiza ID ou URL
        if (str_contains($videoId, 'v=')) {
            $parts = explode('v=', $videoId);
            $subparts = explode('&', $parts[1] ?? '');
            $videoId = $subparts[0];
        } elseif (str_contains($videoId, 'shorts/')) {
            $parts = explode('shorts/', $videoId);
            $subparts = explode('?', $parts[1] ?? '');
            $subparts2 = explode('/', $subparts[0] ?? '');
            $videoId = $subparts2[0];
        }

        $cacheKey = 'transcript_' . md5($videoId);
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.json';

        // Cache de transcrição por 24 horas
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached) && !empty($cached['ok'])) {
                $cached['from_cache'] = true;
                return $cached;
            }
        }

        $cmd = sprintf(
            '%s %s --action transcript --video-id %s',
            escapeshellarg($this->pythonBinary),
            escapeshellarg($this->scriptPath),
            escapeshellarg($videoId)
        );

        $output = $this->executeCommand($cmd);
        $data = json_decode($output, true);

        if (is_array($data) && !empty($data['ok'])) {
            file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $data['from_cache'] = false;
            return $data;
        }

        return [
            'ok' => false,
            'video_id' => $videoId,
            'error' => $data['error'] ?? 'Transcrição não encontrada ou indisponível.',
        ];
    }

    private function executeCommand(string $command): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // No Windows, proc_open com $env isolado descarta SYSTEMROOT e quebra o DNS do Winsock
        $systemEnv = [];
        foreach (array_merge($_ENV, $_SERVER) as $key => $val) {
            if (is_string($val)) {
                $systemEnv[$key] = $val;
            }
        }
        $systemEnv['PYTHONIOENCODING'] = 'utf-8';

        $process = proc_open($command, $descriptors, $pipes, null, $systemEnv);

        if (!is_resource($process)) {
            return '';
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        proc_close($process);

        return trim((string) $stdout);
    }
}
