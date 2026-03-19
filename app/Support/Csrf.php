<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Support/Csrf.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Gerenciar proteção CSRF dos formulários da aplicação.
 * @description Gera, armazena, recupera e valida tokens CSRF utilizados para
 *              proteger requisições de formulários contra falsificação.
 * @usage       Utilizado em formulários administrativos e públicos que exijam
 *              validação de origem da requisição.
 * @notes       Depende da Session para persistência do token.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Support;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Gera um novo token CSRF e o salva na sessão.
     */
    public static function generate(): string
    {
        $token = bin2hex(random_bytes(32));

        Session::put(self::SESSION_KEY, $token);

        return $token;
    }

    /**
     * Retorna o token atual da sessão ou gera um novo se não existir.
     */
    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            return self::generate();
        }

        return $token;
    }

    /**
     * Valida se o token enviado corresponde ao armazenado na sessão.
     */
    public static function validate(?string $token): bool
    {
        $sessionToken = Session::get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            return false;
        }

        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Remove o token atual da sessão.
     */
    public static function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Regenera o token CSRF.
     */
    public static function refresh(): string
    {
        self::clear();

        return self::generate();
    }

    /**
     * Gera o campo hidden HTML com o token CSRF.
     */
    public static function field(): string
    {
        $token = self::token();

        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}