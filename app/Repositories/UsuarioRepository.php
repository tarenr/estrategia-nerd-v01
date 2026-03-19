<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Repositories/UsuarioRepository.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Acesso aos dados de usuários (tabela usuarios)
 * @description Executa queries relacionadas à tabela usuarios (findById, findByUsuario).
 * @usage       (new UsuarioRepository($pdo))->findByUsuario('admin')
 * @notes       A tabela usuarios possui: id, usuario, senha, criado_em.
 *              Não deve conter regra de negócio (isso fica no Service).
 * -----------------------------------------------------------------------------
 */

namespace App\Repositories;

use PDO;

final class UsuarioRepository
{
    public function __construct(private PDO $pdo) {}

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, usuario, criado_em FROM usuarios WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByUsuario(string $usuario): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, usuario, senha, criado_em FROM usuarios WHERE usuario = :usuario LIMIT 1'
        );
        $stmt->execute(['usuario' => $usuario]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}