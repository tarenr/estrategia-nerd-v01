<?php

declare(strict_types=1);

namespace App\Services\YouTube;

use App\Repositories\YouTubeVideoRepository;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class YouTubePublishService
{
    public function __construct(private readonly YouTubeVideoRepository $repository, private readonly YouTubeApiService $api) {}

    /** @param array<string,mixed> $video */
    public function upload(array $video): void
    {
        $id = (int) $video['id']; $file = base_path('public/' . ltrim((string) $video['video_path'], '/'));
        if (!is_file($file) || !str_starts_with(str_replace('\\', '/', (string) realpath($file)), str_replace('\\', '/', (string) realpath(base_path('public/uploads'))) . '/')) {
            $this->repository->markError($id, 'Arquivo de vídeo ausente ou fora de public/uploads.'); return;
        }
        if (!hash_equals((string) ($video['source_sha256'] ?? ''), (string) hash_file('sha256', $file))) {
            $this->repository->markError($id, 'O arquivo de vídeo mudou depois do agendamento.'); return;
        }

        $sessionStarted = trim((string) ($video['resumable_session_uri'] ?? '')) !== '';
        $bytesSent = (int) ($video['upload_offset'] ?? 0) > 0;
        try {
            $session = trim((string) ($video['resumable_session_uri'] ?? ''));
            if ($session === '') {
                $session = $this->api->startResumableUpload($this->metadata($video), $file);
                $this->repository->saveSession($id, $session);
                $sessionStarted = true;
            }
            $offset = (int) ($video['upload_offset'] ?? 0);
            do {
                $result = $this->api->uploadChunk($session, $file, $offset);
                $offset = $result['offset']; $this->repository->saveOffset($id, $offset);
                $bytesSent = $offset > 0;
            } while (!$result['complete']);
            $remoteId = (string) ($result['video_id'] ?? '');
            if ($remoteId === '') { throw new RuntimeException('O YouTube recebeu o arquivo sem devolver o ID do vídeo.'); }
            $this->repository->markReceived($id, $remoteId);
            $thumbnail = trim((string) ($video['thumbnail_path'] ?? ''));
            if ($thumbnail !== '') {
                try {
                    $this->api->setThumbnail($remoteId, base_path('public/' . ltrim($thumbnail, '/')));
                } catch (\Throwable $exception) {
                    $this->repository->addWarning($id, 'Miniatura pendente: ' . $exception->getMessage());
                }
            }
        } catch (\Throwable $exception) {
            $uncertain = $sessionStarted || $bytesSent;
            $this->repository->markError($id, $exception->getMessage(), $uncertain);
        }
    }

    /** @param array<string,mixed> $video */
    public function reconcile(array $video): void
    {
        $remoteId = trim((string) ($video['youtube_video_id'] ?? '')); if ($remoteId === '') { return; }
        try {
            $remote = $this->api->videoStatus($remoteId);
            $processing = (string) ($remote['processingDetails']['processingStatus'] ?? 'processing');
            $privacy = (string) ($remote['status']['privacyStatus'] ?? 'private');
            $publishAt = isset($remote['status']['publishAt']) ? (string) $remote['status']['publishAt'] : null;
            $status = YouTubePublishState::statusAfterProcessing($processing, $privacy, $publishAt);
            $this->repository->markProcessing((int) $video['id'], $processing, $status);
            $this->repository->saveMetrics((int) $video['account_id'], (int) $video['id'], (array) ($remote['statistics'] ?? []), $remote);

            $thumbs = (array) ($remote['snippet']['thumbnails'] ?? []);
            $thumbUrl = $thumbs['maxres']['url'] ?? $thumbs['standard']['url'] ?? $thumbs['high']['url'] ?? $thumbs['medium']['url'] ?? $thumbs['default']['url'] ?? null;
            if ($thumbUrl !== null && empty($video['thumbnail_url'])) {
                $this->repository->updateThumbnailUrl((int) $video['id'], (string) $thumbUrl);
            }
        } catch (\Throwable $exception) {
            $this->repository->markError((int) $video['id'], 'Reconciliação: ' . $exception->getMessage(), true);
        }
    }

    /** @param array<string,mixed> $video @return array<string,mixed> */
    private function metadata(array $video): array
    {
        $status = ['privacyStatus' => (string) ($video['privacy_status'] ?? 'private'), 'selfDeclaredMadeForKids' => !empty($video['made_for_kids']), 'containsSyntheticMedia' => !empty($video['contains_synthetic_media'])];
        $scheduled = trim((string) ($video['scheduled_at'] ?? ''));
        if ($scheduled !== '') {
            $local = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $scheduled, new DateTimeZone('America/Sao_Paulo'));
            if (!$local instanceof DateTimeImmutable || $local <= new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'))) { throw new RuntimeException('Agendamento precisa estar no futuro.'); }
            $status['privacyStatus'] = 'private'; $status['publishAt'] = $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        }
        $tags = json_decode((string) ($video['tags'] ?? '[]'), true); if (!is_array($tags)) { $tags = []; }
        return ['snippet' => ['title' => (string) $video['title'], 'description' => (string) ($video['description'] ?? ''), 'tags' => array_values(array_map('strval', $tags)), 'categoryId' => (string) ($video['category_id'] ?? '28')], 'status' => $status];
    }
}
