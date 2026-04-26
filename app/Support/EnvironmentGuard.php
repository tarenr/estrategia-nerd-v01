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

        if (self::shouldRedirectAdminRequest()) {
            Session::put('admin_flash', [
                'type' => 'warning',
                'message' => 'Este modulo nao esta disponivel neste ambiente.',
            ]);

            header('Location: ' . url('/admin'));
            exit;
        }

        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Acesso nao permitido neste ambiente.';
        exit;
    }

    private static function shouldRedirectAdminRequest(): bool
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg' || headers_sent()) {
            return false;
        }

        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $path = is_string($path) ? rtrim($path, '/') : '';

        if ($path === '') {
            return false;
        }

        return preg_match('#(^|/)admin(/|$)#', $path . '/') === 1;
    }
}
