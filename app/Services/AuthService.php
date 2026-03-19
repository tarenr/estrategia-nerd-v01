<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Services/AuthService.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Serviço de autenticação (login)
 * @description Valida credenciais e retorna resultado para o Controller/Painel.
 * @usage       (new AuthService(new UsuarioRepository($pdo)))->attempt($usuario, $senha)
 * @notes       Compatível com senha em texto (fase inicial) e com hash (password_verify).
 * -----------------------------------------------------------------------------
 */

namespace App\Services;

use App\Repositories\UsuarioRepository;

final class AuthService
{
    public function __construct(private UsuarioRepository $usuarios) {}

    /**
     * @return array{ok:bool, user?:array, error?:string}
     */
    public function attempt(string $usuario, string $senha): array
    {
        $usuario = trim($usuario);
        $senha = (string)$senha;

        if ($usuario === '' || $senha === '') {
            return ['ok' => false, 'error' => 'Informe usuário e senha.'];
        }

        $user = $this->usuarios->findByUsuario($usuario);

        if (!$user) {
            return ['ok' => false, 'error' => 'Credenciais inválidas.'];
        }

        $dbSenha = (string)($user['senha'] ?? '');

        if ($dbSenha === '') {
            return ['ok' => false, 'error' => 'Usuário sem senha cadastrada.'];
        }

        if (!$this->checkSenha($senha, $dbSenha)) {
            return ['ok' => false, 'error' => 'Credenciais inválidas.'];
        }

        unset($user['senha']);

        return ['ok' => true, 'user' => $user];
    }

    private function checkSenha(string $input, string $stored): bool
    {
        // Hash (recomendado) — password_hash/password_verify
        if (password_get_info($stored)['algo'] !== 0) {
            return password_verify($input, $stored);
        }

        // Fase inicial: senha em texto no banco (vamos migrar depois)
        return hash_equals($stored, $input);
    }
}