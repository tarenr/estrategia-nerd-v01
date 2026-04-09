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
            return ['ok' => false, 'error' => 'Informe usuario e senha.'];
        }

        $user = $this->usuarios->findByUsuario($usuario);

        if (!$user) {
            return ['ok' => false, 'error' => 'Credenciais invalidas.'];
        }

        if (($user['status'] ?? 'ativo') !== 'ativo') {
            return ['ok' => false, 'error' => 'Este usuario esta inativo.'];
        }

        $dbSenha = (string) ($user['senha'] ?? '');
        if ($dbSenha === '') {
            return ['ok' => false, 'error' => 'Usuario sem senha cadastrada.'];
        }

        if (!$this->checkSenha($senha, $dbSenha)) {
            return ['ok' => false, 'error' => 'Credenciais invalidas.'];
        }

        $agora = date('Y-m-d H:i:s');
        $this->usuarios->touchLastAccess((int) ($user['id'] ?? 0), $agora);
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