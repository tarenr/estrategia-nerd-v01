<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Repositories/ComentarioRepository.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Repository de Comentários (SQL)
 * @description Centraliza queries SQL na tabela comentarios para métricas do dashboard.
 * @usage       Injetado em Services (ex.: DashboardService) para totais, pendências e taxa de aprovação.
 * @notes       Somente SQL (sem regra de negócio). Assume campos: status e data.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ComentarioRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function countAll(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) AS total FROM comentarios');
        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function countByStatus(string $status): int
    {
        $sql = 'SELECT COUNT(*) AS total FROM comentarios WHERE status = :status';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['status' => $status]);

        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function countPending(): int
    {
        return $this->countByStatus('pendente');
    }

    public function countApproved(): int
    {
        return $this->countByStatus('aprovado');
    }

    public function countRejected(): int
    {
        return $this->countByStatus('reprovado');
    }

    public function countSpam(): int
    {
        return $this->countByStatus('spam');
    }

    public function countToday(): int
    {
        $sql = 'SELECT COUNT(*) AS total FROM comentarios WHERE DATE(data) = CURDATE()';
        $stmt = $this->pdo->query($sql);

        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    /**
     * @return array{aprovados:int,reprovados:int,pendentes:int,spam:int,total:int}
     */
    public function countsByStatus(): array
    {
        $sql = "
            SELECT
                SUM(CASE WHEN status = 'aprovado' THEN 1 ELSE 0 END) AS aprovados,
                SUM(CASE WHEN status = 'reprovado' THEN 1 ELSE 0 END) AS reprovados,
                SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) AS pendentes,
                SUM(CASE WHEN status = 'spam' THEN 1 ELSE 0 END) AS spam,
                COUNT(*) AS total
            FROM comentarios
        ";

        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'aprovados' => (int)($row['aprovados'] ?? 0),
            'reprovados' => (int)($row['reprovados'] ?? 0),
            'pendentes' => (int)($row['pendentes'] ?? 0),
            'spam' => (int)($row['spam'] ?? 0),
            'total' => (int)($row['total'] ?? 0),
        ];
    }

    /**
     * Taxa de aprovação (%) = aprovados / (aprovados + reprovados) * 100
     * (o cálculo final fica no Service; aqui retornamos só os números necessários).
     *
     * @return array{aprovados:int,reprovados:int}
     */
    public function approvalBaseCounts(): array
    {
        $sql = "
            SELECT
                SUM(CASE WHEN status = 'aprovado' THEN 1 ELSE 0 END) AS aprovados,
                SUM(CASE WHEN status = 'reprovado' THEN 1 ELSE 0 END) AS reprovados
            FROM comentarios
        ";

        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'aprovados' => (int)($row['aprovados'] ?? 0),
            'reprovados' => (int)($row['reprovados'] ?? 0),
        ];
    }
}