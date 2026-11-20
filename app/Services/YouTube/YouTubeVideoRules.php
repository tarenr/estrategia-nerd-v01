<?php

declare(strict_types=1);

namespace App\Services\YouTube;

use DateTimeImmutable;

final class YouTubeVideoRules
{
    /** @param array<string,mixed> $input @return array<string,string> */
    public static function validate(array $input): array
    {
        $errors = [];
        $title = trim((string) ($input['title'] ?? ''));
        $description = (string) ($input['description'] ?? '');
        $privacy = (string) ($input['privacy_status'] ?? 'private');
        $scheduledAt = trim((string) ($input['scheduled_at'] ?? ''));

        if ($title === '' || mb_strlen($title) > 100) {
            $errors['title'] = 'O título deve ter entre 1 e 100 caracteres.';
        }
        if (mb_strlen($description) > 5000) {
            $errors['description'] = 'A descrição deve ter no máximo 5000 caracteres.';
        }
        if (!in_array($privacy, ['private', 'unlisted', 'public'], true)) {
            $errors['privacy_status'] = 'Privacidade inválida.';
        }
        if ($scheduledAt !== '' && !self::isSqlDateTime($scheduledAt)) {
            $errors['scheduled_at'] = 'Data de agendamento inválida.';
        }

        return $errors;
    }

    public static function remoteUploadDueAt(string $scheduledAt, int $leadHours = 24): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $scheduledAt);
        if (!$date instanceof DateTimeImmutable) {
            throw new \InvalidArgumentException('Data de agendamento inválida.');
        }

        return $date->modify('-' . max(0, $leadHours) . ' hours')->format('Y-m-d H:i:s');
    }

    public static function immediateUploadDueAt(): string
    {
        return date('Y-m-d H:i:s');
    }

    private static function isSqlDateTime(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        return $date instanceof DateTimeImmutable && $date->format('Y-m-d H:i:s') === $value;
    }
}
