<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Controllers/Site/AuthController.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Controller de autenticação
 * @description Exibe login, processa login e processa logout.
 * @usage       GET /login, POST /login, POST /logout
 * @notes       Auth::login espera array do usuário (não id separado).
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Repositories\UsuarioRepository;
use App\Services\AuthService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\View;

final class AuthController
{
    private AuthService $auth;

    public function __construct()
    {
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        $usuarios = new UsuarioRepository($pdo);
        $this->auth = new AuthService($usuarios);
    }

    public function showLogin(): void
    {
        View::render('site/login', [
            'title' => 'Login — Estratégia Nerd',
        ]);
    }

    public function login(): void
    {
        Csrf::validate($_POST['_csrf_token'] ?? null);

        $usuario = trim((string)($_POST['usuario'] ?? ''));
        $senha = (string)($_POST['senha'] ?? '');

        $result = $this->auth->attempt($usuario, $senha);

        if (!($result['ok'] ?? false)) {
            View::render('site/login', [
                'title' => 'Login — Estratégia Nerd',
                'error' => (string)($result['error'] ?? 'Credenciais inválidas.'),
                'old' => ['usuario' => $usuario],
            ]);
            return;
        }

        /** @var array $user */
        $user = $result['user'];

        Auth::login($user);

        header('Location: ' . url('/admin'));
        exit;
    }

    public function logout(): void
    {
        Csrf::validate($_POST['_csrf_token'] ?? null);

        Auth::logout();

        header('Location: ' . url('/'));
        exit;
    }
}