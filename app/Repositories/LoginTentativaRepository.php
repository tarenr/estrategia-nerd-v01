<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Repositories/LoginTentativaRepository.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Registro e consulta de tentativas de login (tabela login_tentativas)
 * @description Da suporte ao bloqueio temporario por excesso de falhas no AuthService.
 * @notes       A tabela nao possui AUTO_INCREMENT: o id e calculado por MAX(id)+1,
 *              mesmo padrao usado em UsuarioRepository.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class LoginTentativaRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function registrarFalha(string $ip, string $usuario, string $userAgent = ''): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO login_tentativas (id, ip, usuario_tentado, sucesso, data_hora, user_agent)
             VALUES (:id, :ip, :usuario_tentado, 0, NOW(), :user_agent)'
        );
        $stmt->bindValue(':id', $this->nextId(), PDO::PARAM_INT);
        $stmt->bindValue(':ip', $this->normalizeIp($ip), PDO::PARAM_STR);
        $stmt->bindValue(':usuario_tentado', $this->normalizeUsuario($usuario), PDO::PARAM_STR);
        $stmt->bindValue(':user_agent', mb_substr(trim($userAgent), 0, 255), PDO::PARAM_STR);
        $stmt->execute();
    }

    public function contarFalhasRecentes(string $ip, string $usuario, int $janelaMinutos): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - max(1, $janelaMinutos) * 60);

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_tentativas
             WHERE sucesso = 0
               AND data_hora >= :cutoff
               AND (ip = :ip OR usuario_tentado = :usuario_tentado)'
        );
        $stmt->bindValue(':cutoff', $cutoff, PDO::PARAM_STR);
        $stmt->bindValue(':ip', $this->normalizeIp($ip), PDO::PARAM_STR);
        $stmt->bindValue(':usuario_tentado', $this->normalizeUsuario($usuario), PDO::PARAM_STR);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function limparFalhas(string $ip, string $usuario): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM login_tentativas
             WHERE sucesso = 0
               AND (ip = :ip OR usuario_tentado = :usuario_tentado)'
        );
        $stmt->bindValue(':ip', $this->normalizeIp($ip), PDO::PARAM_STR);
        $stmt->bindValue(':usuario_tentado', $this->normalizeUsuario($usuario), PDO::PARAM_STR);
        $stmt->execute();
    }

    private function nextId(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM login_tentativas');
        $nextId = $stmt !== false ? (int) $stmt->fetchColumn() : 1;

        return max(1, $nextId);
    }

    private function normalizeIp(string $ip): string
    {
        return mb_substr(trim($ip), 0, 45);
    }

    private function normalizeUsuario(string $usuario): string
    {
        return mb_substr(trim($usuario), 0, 50);
    }
}
