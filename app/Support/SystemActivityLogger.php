<?php

declare(strict_types=1);

namespace App\Support;

final class SystemActivityLogger
{
    public static function write(string $channel, string $event, array $context = []): void
    {
        $channel = self::normalizeChannel($channel);
        $event = trim($event) !== '' ? trim($event) : 'event';
        $directory = base_path('storage/logs');

        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            return;
        }

        $payload = [
            'timestamp' => date('c'),
            'event' => $event,
            'context' => self::normalizeContext($context),
        ];

        $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($line) || $line === '') {
            return;
        }

        @file_put_contents(
            $directory . DIRECTORY_SEPARATOR . $channel . '-' . date('Y-m') . '.log',
            $line . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private static function normalizeChannel(string $channel): string
    {
        $channel = strtolower(trim($channel));
        $channel = preg_replace('/[^a-z0-9_-]+/', '-', $channel) ?? 'system';
        $channel = trim($channel, '-_');

        return $channel !== '' ? $channel : 'system';
    }

    private static function normalizeContext(array $context): array
    {
        $normalized = [];

        foreach ($context as $key => $value) {
            $safeKey = trim((string) $key);
            if ($safeKey === '') {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $normalized[$safeKey] = $value;
                continue;
            }

            if (is_array($value)) {
                $normalized[$safeKey] = self::normalizeContext($value);
                continue;
            }

            if (is_object($value) && method_exists($value, '__toString')) {
                $normalized[$safeKey] = (string) $value;
            }
        }

        return $normalized;
    }
}
