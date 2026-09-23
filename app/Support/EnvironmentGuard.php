<?php
declare(strict_types=1);

namespace App\Support;

final class EnvironmentGuard
{
    /**
     * Fonte unica de decisao para "esta maquina pode executar isso?".
     * Criterios cumulativos (AND), nunca atalhos alternativos (OR):
     * - APP_ENV precisa indicar ambiente local (stage/homolog/production
     *   NUNCA passam aqui, mesmo com debug=true).
     * - Em contexto web, a origem da requisicao tambem precisa ser local.
     * - Em CLI, so a checagem de APP_ENV se aplica (nao ha requisicao HTTP).
     */
    public static function requireLocal(): void
    {
        $currentEnv = EnvironmentManager::current();
        if (in_array($currentEnv, ['production', 'stage'], true)) {
            self::denyNotFound();
        }

        if (self::isAllowedLocalContext()) {
            return;
        }

        self::denyNotFound();
    }

    private static function denyNotFound(): void
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            fwrite(STDERR, 'Esta operacao so pode ser executada no ambiente local.' . PHP_EOL);
            exit(1);
        }

        http_response_code(404);
        header('X-Robots-Tag: noindex, nofollow', true);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Pagina nao encontrada.';
        exit;
    }

    private static function isAllowedLocalContext(): bool
    {
        if (!EnvironmentManager::isLocal()) {
            return false;
        }

        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return true;
        }

        return self::isLocalOrigin();
    }

    private static function isLocalOrigin(): bool
    {
        // 1. Acesso local direto no PC (localhost / 127.0.0.1 sem cabeçalhos Cloudflare)
        if (self::isDirectLocalAccess()) {
            return true;
        }

        // 2. Acesso remoto (celular / externo) com JWT do Cloudflare Access válido
        if (CloudflareAccessValidator::validate()) {
            return true;
        }

        // 3. Acesso via LAN privada (apenas se opt-in explícito via ALLOW_LAN_ACCESS=true)
        if (self::isLanAccessAllowed()) {
            return true;
        }

        return false;
    }

    private static function isDirectLocalAccess(): bool
    {
        if (self::hasCloudflareHeaders()) {
            return false;
        }

        $hostRaw = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = strtolower(trim((string) preg_replace('/:\d+$/', '', $hostRaw)));
        $host = trim($host, '[]');

        if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        return $remoteAddr === '127.0.0.1' || $remoteAddr === '::1';
    }

    private static function hasCloudflareHeaders(): bool
    {
        return !empty($_SERVER['HTTP_CF_RAY'])
            || !empty($_SERVER['HTTP_CF_CONNECTING_IP'])
            || !empty($_SERVER['HTTP_CF_VISITOR'])
            || !empty($_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION']);
    }

    private static function isLanAccessAllowed(): bool
    {
        $allowLan = filter_var($_ENV['ALLOW_LAN_ACCESS'] ?? getenv('ALLOW_LAN_ACCESS') ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$allowLan) {
            return false;
        }

        if (self::hasCloudflareHeaders()) {
            return false;
        }

        $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        return $remoteAddr !== '' && self::isPrivateIp($remoteAddr);
    }

    private static function isPrivateIp(string $ip): bool
    {
        $ip = strtolower(trim($ip));
        if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[0-1])\.)/', $ip) === 1;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return str_starts_with($ip, 'fc')
                || str_starts_with($ip, 'fd')
                || str_starts_with($ip, 'fe80:');
        }

        return false;
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
