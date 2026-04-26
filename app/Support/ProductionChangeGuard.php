<?php
declare(strict_types=1);

namespace App\Support;

final class ProductionChangeGuard
{
    public const PHRASE = 'PRODUCAO';

    public static function requiresConfirmation(?string $targetEnvironment = null): bool
    {
        return EnvironmentManager::isLocal()
            && EnvironmentManager::normalize($targetEnvironment ?? EnvironmentManager::target()) === 'production';
    }

    public static function isValidPhrase(?string $phrase): bool
    {
        return strtoupper(trim((string) $phrase)) === self::PHRASE;
    }
}
