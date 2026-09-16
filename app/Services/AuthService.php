<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/AuthService.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.2.0
 * @purpose     Servico de autenticacao (login)
 * @description Valida credenciais, aplica bloqueio temporario por excesso de
 *              falhas e retorna resultado para o Controller/Painel.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LoginTentativaRepository;
use App\Repositories\UsuarioRepository;
use App\Support\SystemActivityLogger;
use PDOException;

final class AuthService
{
    private const MAX_FALHAS = 5;
    private const JANELA_MINUTOS = 15;

    public function __construct(
        private UsuarioRepository $usuarios,
        private LoginTentativaRepository $tentativas,
    ) {
    }

    /**
     * @return array{ok:bool, user?:array, error?:string, blocked?:bool}
     */
    public function attempt(string $usuario, string $senha, string $ip = '', string $userAgent = ''): array
    {
        $usuario = trim($usuario);
        $senha = (string) $senha;

        if ($usuario === '' || $senha === '') {
            return ['ok' => false, 'error' => 'Informe usuário e senha.'];
        }

        if ($this->estaBloqueado($ip, $usuario)) {
            SystemActivityLogger::write('auth', 'login_blocked', [
                'stage' => 'rate_limit',
                'usuario' => $usuario,
                'ip' => $ip,
                'janela_minutos' => self::JANELA_MINUTOS,
            ]);

            return [
                'ok' => false,
                'blocked' => true,
                'error' => 'Muitas tentativas de login. Aguarde alguns minutos e tente novamente.',
            ];
        }

        try {
            $user = $this->usuarios->findByUsuario($usuario);
        } catch (PDOException $exception) {
            SystemActivityLogger::write('auth', 'login_database_error', [
                'stage' => 'find_user',
                'code' => (string) $exception->getCode(),
                'message' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'Banco de dados temporariamente indisponivel. Reinicie o MySQL/XAMPP ou tente novamente em instantes.'];
        }

        if (!$user) {
            $this->registrarFalha($ip, $usuario, $userAgent);
            return ['ok' => false, 'error' => 'Credenciais inválidas.'];
        }

        if (($user['status'] ?? 'ativo') !== 'ativo') {
            $this->registrarFalha($ip, $usuario, $userAgent);
            return ['ok' => false, 'error' => 'Este usuário está inativo.'];
        }

        $dbSenha = (string) ($user['senha'] ?? '');
        if ($dbSenha === '') {
            $this->registrarFalha($ip, $usuario, $userAgent);
            return ['ok' => false, 'error' => 'Usuário sem senha cadastrada.'];
        }

        if (!$this->checkSenha($senha, $dbSenha)) {
            $this->registrarFalha($ip, $usuario, $userAgent);
            return ['ok' => false, 'error' => 'Credenciais inválidas.'];
        }

        $this->limparFalhas($ip, $usuario);

        $agora = date('Y-m-d H:i:s');
        try {
            $this->usuarios->touchLastAccess((int) ($user['id'] ?? 0), $agora);
        } catch (PDOException $exception) {
            SystemActivityLogger::write('auth', 'login_last_access_error', [
                'stage' => 'touch_last_access',
                'user_id' => (int) ($user['id'] ?? 0),
                'code' => (string) $exception->getCode(),
                'message' => $exception->getMessage(),
            ]);
        }
        $user['ultimo_acesso'] = $agora;

        unset($user['senha']);

        return ['ok' => true, 'user' => $user];
    }

    private function estaBloqueado(string $ip, string $usuario): bool
    {
        if ($ip === '' && $usuario === '') {
            return false;
        }

        try {
            return $this->tentativas->contarFalhasRecentes($ip, $usuario, self::JANELA_MINUTOS) >= self::MAX_FALHAS;
        } catch (PDOException $exception) {
            // Falha ao consultar o historico nao deve impedir logins legitimos.
            SystemActivityLogger::write('auth', 'login_rate_limit_error', [
                'stage' => 'count_failures',
                'code' => (string) $exception->getCode(),
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function registrarFalha(string $ip, string $usuario, string $userAgent): void
    {
        try {
            $this->tentativas->registrarFalha($ip, $usuario, $userAgent);
        } catch (PDOException $exception) {
            SystemActivityLogger::write('auth', 'login_rate_limit_error', [
                'stage' => 'register_failure',
                'code' => (string) $exception->getCode(),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function limparFalhas(string $ip, string $usuario): void
    {
        try {
            $this->tentativas->limparFalhas($ip, $usuario);
        } catch (PDOException $exception) {
            SystemActivityLogger::write('auth', 'login_rate_limit_error', [
                'stage' => 'clear_failures',
                'code' => (string) $exception->getCode(),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function checkSenha(string $input, string $stored): bool
    {
        if (password_get_info($stored)['algo'] !== 0) {
            return password_verify($input, $stored);
        }

        return hash_equals($stored, $input);
    }
}
