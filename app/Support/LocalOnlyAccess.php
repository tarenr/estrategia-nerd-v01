<?php

declare(strict_types=1);

namespace App\Support;

final class LocalOnlyAccess
{
    public static function enforce(): void
    {
        if (self::isAllowed()) {
            return;
        }

        header('X-Robots-Tag: noindex, nofollow', true);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
        http_response_code(404);
        echo 'Pagina nao encontrada.';
        exit;
    }

    private static function isAllowed(): bool
    {
        $env = strtolower(trim((string) config('app.env', 'production')));
        $debug = (bool) config('app.debug', false);
        $isNonProductionEnv = in_array($env, ['local', 'development', 'dev', 'homolog', 'homologacao'], true);

        if (!$isNonProductionEnv && !$debug) {
            return false;
        }

        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return true;
        }

        return self::isLocalNetworkRequest();
    }

    private static function isLocalNetworkRequest(): bool
    {
        $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $hostRaw = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = strtolower(trim((string) preg_replace('/:\d+$/', '', $hostRaw)));
        $host = trim($host, '[]');

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        if ($remoteAddr !== '' && self::isPrivateOrLoopbackIp($remoteAddr)) {
            return true;
        }

        return false;
    }

    private static function isPrivateOrLoopbackIp(string $ip): bool
    {
        $ip = strtolower(trim($ip));
        if ($ip === '' || $ip === '::1') {
            return $ip === '::1';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_match('/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[0-1])\.)/', $ip) === 1;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return str_starts_with($ip, 'fc')
                || str_starts_with($ip, 'fd')
                || str_starts_with($ip, 'fe80:')
                || $ip === '::1';
        }

        return false;
    }
}
