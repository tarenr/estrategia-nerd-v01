<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Support/Session.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Centralizar o gerenciamento de sessão da aplicação.
 * @description Fornece métodos utilitários para manipular dados da sessão de
 *              forma organizada, evitando acesso direto espalhado ao array
 *              global $_SESSION.
 * @usage       Utilizado por autenticação, flash messages, CSRF e demais
 *              recursos que dependam de persistência temporária por sessão.
 * @notes       Este arquivo não deve conter regra de negócio.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Support;

final class Session
{
    /**
     * Verifica se uma chave existe na sessão.
     */
    public static function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    /**
     * Retorna um valor da sessão.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::has($key) ? $_SESSION[$key] : $default;
    }

    /**
     * Define um valor na sessão.
     */
    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Define múltiplos valores na sessão.
     */
    public static function setMany(array $data): void
    {
        foreach ($data as $key => $value) {
            $_SESSION[$key] = $value;
        }
    }

    /**
     * Remove uma chave da sessão.
     */
    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Remove múltiplas chaves da sessão.
     */
    public static function forgetMany(array $keys): void
    {
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Retorna um valor da sessão e remove logo em seguida.
     */
    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);

        return $value;
    }

    /**
     * Retorna todos os dados da sessão.
     */
    public static function all(): array
    {
        return $_SESSION;
    }

    /**
     * Limpa todos os dados da sessão.
     */
    public static function flush(): void
    {
        $_SESSION = [];
    }

    /**
     * Regenera o ID da sessão por segurança.
     */
    public static function regenerate(bool $deleteOldSession = true): void
    {
        session_regenerate_id($deleteOldSession);
    }

    /**
     * Destrói completamente a sessão atual.
     */
    public static function destroy(): void
    {
        self::flush();

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool) ($params['secure'] ?? false),
                (bool) ($params['httponly'] ?? true)
            );
        }

        session_destroy();
    }
}