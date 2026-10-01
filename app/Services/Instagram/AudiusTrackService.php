<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/Instagram/AudiusTrackService.php
 * @project     Estrategia Nerd
 * @purpose     Serviço para busca, reprodução e download de trilhas sonoras via
 *              Audius API e gestão da biblioteca local de áudio (FEAT-012)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services\Instagram;

use PDO;
use RuntimeException;

final class AudiusTrackService
{
    private const AUDIUS_DEFAULT_HOST = 'https://discoveryprovider.audius.co';
    private const APP_NAME = 'EstrategiaNerd';

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $publicRoot,
        private readonly string $appUrl,
        private readonly AudioReelGeneratorService $reelGenerator
    ) {
    }

    public static function fromGlobals(): self
    {
        /** @var PDO $pdo */
        $pdo = $GLOBALS['pdo'];
        $publicRoot = base_path('public');
        $appUrl = rtrim((string) config('app.url', ''), '/');

        return new self($pdo, $publicRoot, $appUrl, AudioReelGeneratorService::fromGlobals());
    }

    /**
     * Busca trilhas no Audius pelo termo informado.
     *
     * @return list<array{id: string, title: string, artist: string, genre: string, duration: int, stream_url: string, artwork: string|null}>
     */
    public function search(string $query, int $limit = 12): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }

        $url = sprintf(
            '%s/v1/tracks/search?query=%s&limit=%d&app_name=%s',
            self::AUDIUS_DEFAULT_HOST,
            rawurlencode($q),
            max(1, min(30, $limit)),
            self::APP_NAME
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'EstrategiaNerd/1.0',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($rawResponse === false || $httpCode !== 200) {
            error_log(sprintf('[AudiusTrackService] Erro na busca Audius (%d): %s', $httpCode, $curlError));
            return [];
        }

        $decoded = json_decode((string) $rawResponse, true);
        $items = $decoded['data'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $results = [];
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['id'])) {
                continue;
            }

            $trackId = (string) $item['id'];
            $title   = trim((string) ($item['title'] ?? 'Sem título'));
            $artist  = trim((string) ($item['user']['name'] ?? 'Artista Audius'));
            $genre   = trim((string) ($item['genre'] ?? 'Geral'));
            $dur     = (int) ($item['duration'] ?? 0);

            $artwork = null;
            if (!empty($item['artwork']['150x150'])) {
                $artwork = (string) $item['artwork']['150x150'];
            } elseif (!empty($item['artwork']['480x480'])) {
                $artwork = (string) $item['artwork']['480x480'];
            }

            $streamUrl = sprintf(
                '%s/v1/tracks/%s/stream?app_name=%s',
                self::AUDIUS_DEFAULT_HOST,
                $trackId,
                self::APP_NAME
            );

            $results[] = [
                'id'         => $trackId,
                'title'      => $title,
                'artist'     => $artist,
                'genre'      => $genre,
                'duration'   => $dur,
                'stream_url' => $streamUrl,
                'artwork'    => $artwork,
            ];
        }

        return $results;
    }

    /**
     * Baixa uma faixa do Audius e a armazena na biblioteca local de áudio.
     *
     * @return array<string,mixed>
     */
    public function downloadAudiusTrack(
        string $trackId,
        string $title,
        string $artist = 'Artista Audius',
        string $genre = 'Eletrônica',
        int $durationHint = 0
    ): array {
        $trackId = trim($trackId);
        if ($trackId === '') {
            throw new RuntimeException('ID da faixa Audius é obrigatório.');
        }

        // 1. Verificar se já existe na tabela de áudio
        $stmt = $this->pdo->prepare('SELECT * FROM instagram_audio_tracks WHERE origem = "audius" AND origem_id = :oid LIMIT 1');
        $stmt->execute([':oid' => $trackId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($existing)) {
            $localFile = $this->publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $existing['arquivo_path']);
            if (is_file($localFile) && filesize($localFile) > 0) {
                return $this->formatTrackRecord($existing);
            }
        }

        // 2. Fazer download do stream MP3
        $streamUrl = sprintf(
            '%s/v1/tracks/%s/stream?app_name=%s',
            self::AUDIUS_DEFAULT_HOST,
            $trackId,
            self::APP_NAME
        );

        $relDir = 'uploads/audio';
        $fullDir = $this->publicRoot . DIRECTORY_SEPARATOR . $relDir;
        if (!is_dir($fullDir) && !@mkdir($fullDir, 0755, true) && !is_dir($fullDir)) {
            throw new RuntimeException("Não foi possível criar o diretório: {$fullDir}");
        }

        $filename = 'audius_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $trackId) . '.mp3';
        $relPath = $relDir . '/' . $filename;
        $fullPath = $this->publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);

        $fp = fopen($fullPath, 'wb');
        if ($fp === false) {
            throw new RuntimeException("Não foi possível abrir o arquivo para gravação: {$fullPath}");
        }

        $ch = curl_init($streamUrl);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'EstrategiaNerd/1.0',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $success = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode >= 400 || !is_file($fullPath) || filesize($fullPath) < 1024) {
            @unlink($fullPath);
            throw new RuntimeException("Falha ao baixar áudio do Audius (código {$httpCode}): {$error}");
        }

        // 3. Obter duração real e hash
        $realDuration = (int) round($this->reelGenerator->getAudioDuration($relPath));
        if ($realDuration <= 0) {
            $realDuration = max(1, $durationHint);
        }
        $fileHash = hash_file('sha256', $fullPath) ?: null;

        // 4. Salvar ou atualizar no banco
        if (is_array($existing)) {
            $upd = $this->pdo->prepare(
                'UPDATE instagram_audio_tracks
                    SET titulo = :titulo, artista = :artista, genero = :genero, arquivo_path = :path,
                        duracao_s = :duracao, file_hash = :hash, ativo = 1, atualizado_em = NOW()
                  WHERE id = :id'
            );
            $upd->execute([
                ':titulo'  => $title,
                ':artista' => $artist,
                ':genero'  => $genre,
                ':path'    => $relPath,
                ':duracao' => $realDuration,
                ':hash'    => $fileHash,
                ':id'      => (int) $existing['id'],
            ]);
            $recordId = (int) $existing['id'];
        } else {
            $ins = $this->pdo->prepare(
                'INSERT INTO instagram_audio_tracks
                    (origem, origem_id, titulo, artista, genero, arquivo_path, duracao_s, file_hash, ativo)
                 VALUES
                    ("audius", :oid, :titulo, :artista, :genero, :path, :duracao, :hash, 1)'
            );
            $ins->execute([
                ':oid'     => $trackId,
                ':titulo'  => $title,
                ':artista' => $artist,
                ':genero'  => $genre,
                ':path'    => $relPath,
                ':duracao' => $realDuration,
                ':hash'    => $fileHash,
            ]);
            $recordId = (int) $this->pdo->lastInsertId();
        }

        return $this->getTrackById($recordId) ?? [
            'id'           => $recordId,
            'origem'       => 'audius',
            'origem_id'    => $trackId,
            'titulo'       => $title,
            'artista'      => $artist,
            'genero'       => $genre,
            'arquivo_path' => $relPath,
            'duracao_s'    => $realDuration,
            'url'          => $this->formatAudioUrl($relPath),
        ];
    }

    /**
     * Retorna a lista de faixas ativas da biblioteca local.
     *
     * @return list<array<string,mixed>>
     */
    public function listLocalTracks(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM instagram_audio_tracks WHERE ativo = 1 ORDER BY id DESC');
        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $list = [];
        foreach ($rows as $row) {
            $list[] = $this->formatTrackRecord($row);
        }

        return $list;
    }

    /**
     * Busca uma faixa por ID no banco.
     *
     * @return array<string,mixed>|null
     */
    public function getTrackById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM instagram_audio_tracks WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->formatTrackRecord($row) : null;
    }

    /**
     * Salva um arquivo de áudio enviado via upload personalizado.
     *
     * @param array{tmp_name: string, name: string, error: int, size: int} $file
     * @return array<string,mixed>
     */
    public function uploadCustomTrack(array $file, string $title, string $artist = 'Customizado'): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Erro no envio do arquivo de áudio.');
        }

        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            throw new RuntimeException('Arquivo de áudio inválido.');
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['mp3', 'm4a', 'aac', 'wav', 'ogg'], true)) {
            throw new RuntimeException('Formato de áudio não suportado. Use MP3, M4A ou AAC.');
        }

        $relDir = 'uploads/audio';
        $fullDir = $this->publicRoot . DIRECTORY_SEPARATOR . $relDir;
        if (!is_dir($fullDir) && !@mkdir($fullDir, 0755, true) && !is_dir($fullDir)) {
            throw new RuntimeException("Não foi possível criar o diretório: {$fullDir}");
        }

        $filename = 'custom_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $relPath = $relDir . '/' . $filename;
        $destPath = $this->publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);

        if (!move_uploaded_file($tmp, $destPath)) {
            throw new RuntimeException('Falha ao mover arquivo enviado para o armazenamento.');
        }

        $realDuration = (int) round($this->reelGenerator->getAudioDuration($relPath));
        if ($realDuration <= 0) {
            $realDuration = 30;
        }

        $cleanTitle = trim($title) !== '' ? trim($title) : pathinfo((string) $file['name'], PATHINFO_FILENAME);
        $cleanArtist = trim($artist) !== '' ? trim($artist) : 'Customizado';
        $fileHash = hash_file('sha256', $destPath) ?: null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO instagram_audio_tracks
                (origem, origem_id, titulo, artista, genero, arquivo_path, duracao_s, file_hash, ativo)
             VALUES
                ("custom", NULL, :titulo, :artista, "Custom", :path, :duracao, :hash, 1)'
        );
        $stmt->execute([
            ':titulo'  => $cleanTitle,
            ':artista' => $cleanArtist,
            ':path'    => $relPath,
            ':duracao' => $realDuration,
            ':hash'    => $fileHash,
        ]);

        $trackId = (int) $this->pdo->lastInsertId();

        return $this->getTrackById($trackId) ?? [
            'id'           => $trackId,
            'origem'       => 'custom',
            'origem_id'    => null,
            'titulo'       => $cleanTitle,
            'artista'      => $cleanArtist,
            'genero'       => 'Custom',
            'arquivo_path' => $relPath,
            'duracao_s'    => $realDuration,
            'url'          => $this->formatAudioUrl($relPath),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function formatTrackRecord(array $row): array
    {
        $relPath = (string) ($row['arquivo_path'] ?? '');
        $url = $this->formatAudioUrl($relPath);

        return [
            'id'           => (int) ($row['id'] ?? 0),
            'origem'       => (string) ($row['origem'] ?? 'local'),
            'origem_id'    => $row['origem_id'] ?? null,
            'titulo'       => (string) ($row['titulo'] ?? 'Trilha sonora'),
            'artista'      => (string) ($row['artista'] ?? 'Desconhecido'),
            'genero'       => (string) ($row['genero'] ?? 'Geral'),
            'arquivo_path' => $relPath,
            'duracao_s'    => (int) ($row['duracao_s'] ?? 0),
            'url'          => $url,
            'criado_em'    => (string) ($row['criado_em'] ?? ''),
        ];
    }

    private function formatAudioUrl(string $relPath): string
    {
        if ($relPath === '') {
            return '';
        }

        if (function_exists('asset')) {
            return (string) asset($relPath);
        }

        return $this->appUrl . '/' . ltrim($relPath, '/');
    }
}
