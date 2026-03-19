<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Support/Auth.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Controle de autenticação por sessão
 * @description Centraliza estado de login na sessão e oferece helpers para proteger rotas.
 * @usage       Auth::check(), Auth::login($user), Auth::logout(), Auth::require('/login')
 * @notes       Não deve conter SQL nem depender de Repository/Service (isso fica no Service).
 * -----------------------------------------------------------------------------
 */

namespace App\Support;

final class Auth
{
    private const SESSION_KEY = 'auth.user_id';
    private const SESSION_USER = 'auth.user';

    public static function check(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    public static function id(): ?int
    {
        $id = Session::get(self::SESSION_KEY);
        return is_numeric($id) ? (int) $id : null;
    }

    public static function user(): ?array
    {
        $user = Session::get(self::SESSION_USER);
        return is_array($user) ? $user : null;
    }

    /**
     * @param array{id:int|string} $user
     */
    public static function login(array $user): void
    {
        Session::regenerate();
        Session::put(self::SESSION_KEY, (int) $user['id']);

        unset($user['password']);
        Session::put(self::SESSION_USER, $user);
    }

    public static function logout(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::forget(self::SESSION_USER);
        Session::regenerate();
    }

    public static function require(string $redirectTo = '/login'): void
    {
        if (!self::check()) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }
}