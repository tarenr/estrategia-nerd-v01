<?php

declare(strict_types=1);

namespace App\Services\YouTube;

final class YouTubePublishState
{
    public static function canClaim(string $status, string $phase): bool
    {
        return in_array($status, ['agendado', 'erro'], true)
            && in_array($phase, ['idle', 'failed'], true);
    }

    public static function canRetryAutomatically(string $status): bool
    {
        return $status === 'erro';
    }

    public static function statusAfterProcessing(string $processing, string $privacy, ?string $publishAt): string
    {
        if ($processing === 'failed') {
            return 'erro';
        }
        if ($processing !== 'succeeded') {
            return 'processando';
        }
        if ($privacy === 'private' && $publishAt !== null && $publishAt !== '') {
            return 'agendado_remoto';
        }
        if ($privacy === 'public') {
            return 'publicado';
        }

        return 'processando';
    }
}
