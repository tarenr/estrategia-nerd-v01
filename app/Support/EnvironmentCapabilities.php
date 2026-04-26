<?php
declare(strict_types=1);

namespace App\Support;

final class EnvironmentCapabilities
{
    /**
     * @return array<int, string>
     */
    public static function all(?string $environment = null): array
    {
        $key = EnvironmentManager::normalize($environment ?? EnvironmentManager::current());
        $capabilities = config('environment_capabilities.' . $key, []);

        return is_array($capabilities) ? array_values($capabilities) : [];
    }

    public static function has(string $capability, ?string $environment = null): bool
    {
        return in_array($capability, self::all($environment), true);
    }
}
