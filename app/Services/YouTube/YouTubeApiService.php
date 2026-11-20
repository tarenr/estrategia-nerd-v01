<?php

declare(strict_types=1);

namespace App\Services\YouTube;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RuntimeException;

final class YouTubeApiService
{
    private const API = 'https://www.googleapis.com/youtube/v3';
    private const UPLOAD = 'https://www.googleapis.com/upload/youtube/v3/videos';

    public function __construct(private readonly YouTubeOAuthService $oauth) {}

    /** @return array<string,mixed> */
    public function myChannel(): array
    {
        $payload = $this->json('GET', self::API . '/channels?' . http_build_query(['part' => 'snippet,contentDetails,statistics', 'mine' => 'true']));
        $item = $payload['items'][0] ?? null;
        if (!is_array($item)) { throw new RuntimeException('A conta autorizada não possui canal do YouTube.'); }
        $snippet = (array) ($item['snippet'] ?? []);
        $stats = (array) ($item['statistics'] ?? []);
        $content = (array) ($item['contentDetails'] ?? []);
        $related = (array) ($content['relatedPlaylists'] ?? []);
        $thumbs = (array) ($snippet['thumbnails'] ?? []);
        $thumbnail = $thumbs['high']['url'] ?? $thumbs['medium']['url'] ?? $thumbs['default']['url'] ?? null;
        return [
            'channel_id' => (string) ($item['id'] ?? ''),
            'title' => (string) ($snippet['title'] ?? ''),
            'description' => (string) ($snippet['description'] ?? ''),
            'published_at' => (string) ($snippet['publishedAt'] ?? ''),
            'handle' => $snippet['customUrl'] ?? null,
            'custom_url' => $snippet['customUrl'] ?? null,
            'thumbnail_url' => $thumbnail,
            'uploads_playlist_id' => (string) ($related['uploads'] ?? ''),
            'subscribers_count' => (int) ($stats['subscriberCount'] ?? 0),
            'video_count' => (int) ($stats['videoCount'] ?? 0),
            'view_count' => (int) ($stats['viewCount'] ?? 0),
            'raw' => $item,
        ];
    }

    /** @return list<string> */
    public function fetchUploadedVideoIds(string $uploadsPlaylistId, int $max = 200): array
    {
        $videoIds = [];
        $pageToken = null;
        do {
            $query = [
                'part' => 'contentDetails',
                'playlistId' => $uploadsPlaylistId,
                'maxResults' => 50,
            ];
            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }
            $res = $this->json('GET', self::API . '/playlistItems?' . http_build_query($query));
            foreach ($res['items'] ?? [] as $item) {
                $vid = trim((string) ($item['contentDetails']['videoId'] ?? ''));
                if ($vid !== '') {
                    $videoIds[] = $vid;
                }
            }
            $pageToken = $res['nextPageToken'] ?? null;
        } while ($pageToken !== null && count($videoIds) < $max);

        return $videoIds;
    }

    /** @param list<string> $videoIds @return list<array<string,mixed>> */
    public function fetchVideosDetails(array $videoIds): array
    {
        if ($videoIds === []) {
            return [];
        }
        $results = [];
        $chunks = array_chunk($videoIds, 50);
        foreach ($chunks as $chunk) {
            $res = $this->json('GET', self::API . '/videos?' . http_build_query([
                'part' => 'snippet,contentDetails,status,statistics',
                'id' => implode(',', $chunk),
            ]));
            foreach ($res['items'] ?? [] as $item) {
                $results[] = YouTubeSyncRules::normalizeVideo($item);
            }
        }
        return $results;
    }

    /** @param array<string,mixed> $metadata */
    public function startResumableUpload(array $metadata, string $file): string
    {
        $size = filesize($file); if ($size === false) { throw new RuntimeException('Arquivo de vídeo não encontrado.'); }
        $url = self::UPLOAD . '?' . http_build_query(['uploadType' => 'resumable', 'part' => 'snippet,status']);
        $response = $this->raw('POST', $url, json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), ['Content-Type: application/json; charset=UTF-8', 'X-Upload-Content-Length: ' . $size, 'X-Upload-Content-Type: video/mp4'], true);
        $location = (string) ($response['headers']['location'] ?? '');
        if ($response['status'] < 200 || $response['status'] >= 300 || $location === '') { throw new RuntimeException($this->apiError($response)); }
        return $location;
    }

    /** @return array{complete:bool,offset:int,video_id:?string} */
    public function uploadChunk(string $sessionUri, string $file, int $offset, int $chunkSize = 8388608): array
    {
        $total = filesize($file); if ($total === false || $offset < 0 || $offset > $total) { throw new RuntimeException('Estado local do upload inválido.'); }
        $length = min($chunkSize, $total - $offset);
        $stream = fopen($file, 'rb'); if ($stream === false || fseek($stream, $offset) !== 0) { throw new RuntimeException('Não foi possível ler o vídeo.'); }
        $bytes = $length > 0 ? fread($stream, $length) : ''; fclose($stream);
        if (!is_string($bytes) || strlen($bytes) !== $length) { throw new RuntimeException('Leitura incompleta do vídeo.'); }
        $end = $length > 0 ? $offset + $length - 1 : $offset;
        $response = $this->raw('PUT', $sessionUri, $bytes, ['Content-Type: video/mp4', 'Content-Length: ' . $length, 'Content-Range: bytes ' . $offset . '-' . $end . '/' . $total], true);
        if ($response['status'] === 308) {
            $range = (string) ($response['headers']['range'] ?? '');
            preg_match('/bytes=0-(\d+)/', $range, $match);
            return ['complete' => false, 'offset' => isset($match[1]) ? (int) $match[1] + 1 : $offset + $length, 'video_id' => null];
        }
        $payload = json_decode($response['body'], true);
        if ($response['status'] >= 200 && $response['status'] < 300 && is_array($payload) && trim((string) ($payload['id'] ?? '')) !== '') {
            return ['complete' => true, 'offset' => $total, 'video_id' => (string) $payload['id']];
        }
        throw new RuntimeException($this->apiError($response));
    }

    /** @return array<string,mixed> */
    public function videoStatus(string $videoId): array
    {
        $payload = $this->json('GET', self::API . '/videos?' . http_build_query(['part' => 'snippet,status,processingDetails,statistics', 'id' => $videoId]));
        $item = $payload['items'][0] ?? null;
        if (!is_array($item)) { throw new RuntimeException('Vídeo não encontrado no canal conectado.'); }
        return $item;
    }

    /** @return array<string,mixed> */
    public function reschedulePrivateVideo(string $videoId, DateTimeInterface $publishAt): array
    {
        $videoId = trim($videoId);
        if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId)) {
            throw new RuntimeException('ID de vídeo do YouTube inválido.');
        }

        $body = json_encode([
            'id' => $videoId,
            'status' => [
                'privacyStatus' => 'private',
                'publishAt' => DateTimeImmutable::createFromInterface($publishAt)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $response = $this->raw(
            'PUT',
            self::API . '/videos?' . http_build_query(['part' => 'status']),
            $body,
            ['Content-Type: application/json; charset=UTF-8', 'Accept: application/json'],
            true
        );
        $payload = json_decode($response['body'], true);
        if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($payload)) {
            throw new RuntimeException($this->apiError($response));
        }

        return $payload;
    }

    public function setThumbnail(string $videoId, string $file): void
    {
        if (!is_file($file)) {
            throw new RuntimeException('Arquivo de miniatura não encontrado.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            throw new RuntimeException('Formato de miniatura não suportado.');
        }
        $body = file_get_contents($file);
        if (!is_string($body)) {
            throw new RuntimeException('Não foi possível ler a miniatura.');
        }
        $url = 'https://www.googleapis.com/upload/youtube/v3/thumbnails/set?' . http_build_query(['videoId' => $videoId, 'uploadType' => 'media']);
        $response = $this->raw('POST', $url, $body, ['Content-Type: ' . $mime, 'Content-Length: ' . strlen($body)], true);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException($this->apiError($response));
        }
    }

    /** @return array<string,mixed> */
    private function json(string $method, string $url): array
    {
        $response = $this->raw($method, $url, null, ['Accept: application/json'], true);
        $payload = json_decode($response['body'], true);
        if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($payload)) { throw new RuntimeException($this->apiError($response)); }
        return $payload;
    }

    /** @param list<string> $headers @return array{status:int,body:string,headers:array<string,string>} */
    private function raw(string $method, string $url, ?string $body, array $headers, bool $includeAuthorization): array
    {
        if (!str_starts_with($url, 'https://www.googleapis.com/') && !str_starts_with($url, 'https://youtube.googleapis.com/')) { throw new RuntimeException('Destino de API do YouTube não permitido.'); }
        if ($includeAuthorization) { $headers[] = 'Authorization: Bearer ' . $this->oauth->accessToken(); }
        $responseHeaders = [];
        $handle = curl_init($url); if ($handle === false) { throw new RuntimeException('Não foi possível iniciar a chamada ao YouTube.'); }
        curl_setopt_array($handle, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_HTTPHEADER => $headers, CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int { $parts = explode(':', $line, 2); if (count($parts) === 2) { $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]); } return strlen($line); }]);
        if ($body !== null) { curl_setopt($handle, CURLOPT_POSTFIELDS, $body); }
        $responseBody = curl_exec($handle); $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE); $error = curl_error($handle); curl_close($handle);
        if ($responseBody === false) { throw new RuntimeException('Falha de transporte com o YouTube: ' . $error); }
        return ['status' => $status, 'body' => (string) $responseBody, 'headers' => $responseHeaders];
    }

    /** @param array{status:int,body:string,headers:array<string,string>} $response */
    private function apiError(array $response): string
    {
        $payload = json_decode($response['body'], true);
        $message = is_array($payload) ? (string) ($payload['error']['message'] ?? 'Resposta inválida do YouTube.') : 'Resposta inválida do YouTube.';
        return 'YouTube API HTTP ' . $response['status'] . ': ' . $message;
    }
}
