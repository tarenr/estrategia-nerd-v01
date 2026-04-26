<?php
declare(strict_types=1);

namespace App\Support;

final class EnvironmentManager
{
    private const LOCAL = 'local';
    private const STAGE = 'stage';
    private const PRODUCTION = 'production';
    private const SESSION_KEY = 'target_environment';

    public static function current(): string
    {
        $raw = (string) config('app.env', env('APP_ENV', self::PRODUCTION));
        return self::normalize($raw);
    }

    public static function target(): string
    {
        $current = self::current();
        if ($current !== self::LOCAL) {
            return $current;
        }

        $target = Session::get(self::SESSION_KEY, self::LOCAL);
        $normalized = self::normalizeTarget(is_string($target) ? $target : self::LOCAL);

        return $normalized ?? self::LOCAL;
    }

    public static function setTarget(string $environment): void
    {
        if (self::current() !== self::LOCAL) {
            return;
        }

        $normalized = self::normalizeTarget($environment);
        if ($normalized === null) {
            return;
        }

        Session::put(self::SESSION_KEY, $normalized);
    }

    public static function isLocal(): bool
    {
        return self::current() === self::LOCAL;
    }

    public static function canTarget(string $environment): bool
    {
        $normalized = self::normalize($environment);

        if (self::isLocal()) {
            return in_array($normalized, self::allowedTargets(), true);
        }

        return self::current() === $normalized;
    }

    /**
     * @return array<int, string>
     */
    public static function allowedTargets(): array
    {
        return [self::LOCAL, self::STAGE, self::PRODUCTION];
    }

    public static function isAllowedTarget(string $environment): bool
    {
        return in_array($environment, self::allowedTargets(), true);
    }

    public static function label(string $environment): string
    {
        return match (self::normalize($environment)) {
            self::LOCAL => 'Local',
            self::STAGE => 'Stage',
            default => 'Producao',
        };
    }

    public static function normalize(string $environment): string
    {
        $value = strtolower(trim($environment));

        return match ($value) {
            self::LOCAL, 'development', 'dev' => self::LOCAL,
            self::STAGE, 'staging', 'homolog', 'homologacao' => self::STAGE,
            self::PRODUCTION, 'prod' => self::PRODUCTION,
            default => self::PRODUCTION,
        };
    }

    public static function normalizeTarget(string $environment): ?string
    {
        $value = strtolower(trim($environment));

        return match ($value) {
            self::LOCAL, 'development', 'dev' => self::LOCAL,
            self::STAGE, 'staging', 'homolog', 'homologacao' => self::STAGE,
            self::PRODUCTION, 'prod' => self::PRODUCTION,
            default => null,
        };
    }
}
