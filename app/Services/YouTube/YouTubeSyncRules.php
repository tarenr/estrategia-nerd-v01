<?php

declare(strict_types=1);

namespace App\Services\YouTube;

final class YouTubeSyncRules
{
    public static function durationSeconds(string $duration): int
    {
        try {
            $interval = new \DateInterval($duration);
        } catch (\Throwable) {
            return 0;
        }
        return ($interval->d * 86400) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    }

    public static function contentKind(int $seconds): string
    {
        return $seconds > 0 && $seconds <= 180 ? 'short' : 'video';
    }

    /** @return array{key:string,start:string,end:string,days:int} */
    public static function period(string $key, ?string $start, ?string $end): array
    {
        $today = new \DateTimeImmutable('today');
        if ($key === 'custom' && self::date($start) && self::date($end) && $start <= $end) {
            $days = (int) (new \DateTimeImmutable($start))->diff(new \DateTimeImmutable($end))->days + 1;
            return ['key' => 'custom', 'start' => $start, 'end' => $end, 'days' => min(366, $days)];
        }
        $days = match ($key) { '14d' => 14, '30d' => 30, default => 7 };
        return ['key' => $days . 'd', 'start' => $today->modify('-' . ($days - 1) . ' days')->format('Y-m-d'), 'end' => $today->format('Y-m-d'), 'days' => $days];
    }

    /** @param array<string,mixed> $item @return array<string,mixed> */
    public static function normalizeVideo(array $item): array
    {
        $id = trim((string) ($item['id'] ?? ''));
        $snippet = (array) ($item['snippet'] ?? []);
        $status = (array) ($item['status'] ?? []);
        $stats = (array) ($item['statistics'] ?? []);
        $seconds = self::durationSeconds((string) (($item['contentDetails']['duration'] ?? '')));
        $thumbs = (array) ($snippet['thumbnails'] ?? []);
        $thumbnail = $thumbs['maxres']['url'] ?? $thumbs['standard']['url'] ?? $thumbs['high']['url'] ?? $thumbs['medium']['url'] ?? $thumbs['default']['url'] ?? null;
        return [
            'youtube_video_id' => $id,
            'idempotency_key' => 'youtube:' . $id,
            'title' => mb_substr((string) ($snippet['title'] ?? ''), 0, 100),
            'description' => (string) ($snippet['description'] ?? ''),
            'privacy_status' => in_array(($status['privacyStatus'] ?? ''), ['private', 'unlisted', 'public'], true) ? $status['privacyStatus'] : 'private',
            'made_for_kids' => !empty($status['madeForKids']),
            'published_at' => self::mysqlDate($snippet['publishedAt'] ?? null),
            'duration_seconds' => $seconds,
            'content_kind' => self::contentKind($seconds),
            'thumbnail_url' => $thumbnail,
            'views_count' => (int) ($stats['viewCount'] ?? 0),
            'likes_count' => (int) ($stats['likeCount'] ?? 0),
            'comments_count' => (int) ($stats['commentCount'] ?? 0),
            'permalink' => $id !== '' ? 'https://youtu.be/' . $id : null,
            'raw' => $item,
        ];
    }

    private static function date(?string $value): bool
    {
        if ($value === null) return false;
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $parsed !== false && $parsed->format('Y-m-d') === $value;
    }

    private static function mysqlDate(mixed $value): ?string
    {
        try {
            return $value ? (new \DateTimeImmutable((string) $value))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s') : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
