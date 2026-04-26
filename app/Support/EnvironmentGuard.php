<?php
declare(strict_types=1);

namespace App\Support;

final class EnvironmentGuard
{
    public static function requireLocal(): void
    {
        if (EnvironmentManager::isLocal()) {
            return;
        }

        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Acesso disponivel apenas no ambiente local.';
        exit;
    }

    public static function requireCapability(string $capability): void
    {
        if (EnvironmentCapabilities::has($capability)) {
            return;
        }

        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Acesso nao permitido neste ambiente.';
        exit;
    }
}
