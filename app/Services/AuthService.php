<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/AuthService.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.1.0
 * @purpose     Servico de autenticacao (login)
 * @description Valida credenciais e retorna resultado para o Controller/Painel.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UsuarioRepository;
use App\Support\SystemActivityLogger;
use PDOException;

final class AuthService
{
    public function __construct(private UsuarioRepository $usuarios)
    {
    }

    /**
     * @return array{ok:bool, user?:array, error?:string}
     */
    public function attempt(string $usuario, string $senha): array
    {
        $usuario = trim($usuario);
        $senha = (string) $senha;

        if ($usuario === '' || $senha === '') {
            return ['ok' => false, 'error' => 'Informe usuário e senha.'];
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
            return ['ok' => false, 'error' => 'Credenciais inválidas.'];
        }

        if (($user['status'] ?? 'ativo') !== 'ativo') {
            return ['ok' => false, 'error' => 'Este usuário está inativo.'];
        }

        $dbSenha = (string) ($user['senha'] ?? '');
        if ($dbSenha === '') {
            return ['ok' => false, 'error' => 'Usuário sem senha cadastrada.'];
        }

        if (!$this->checkSenha($senha, $dbSenha)) {
            return ['ok' => false, 'error' => 'Credenciais inválidas.'];
        }

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

    private function checkSenha(string $input, string $stored): bool
    {
        if (password_get_info($stored)['algo'] !== 0) {
            return password_verify($input, $stored);
        }

        return hash_equals($stored, $input);
    }
}
