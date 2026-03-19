<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Repositories/NewsletterRepository.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Repository de Newsletter (SQL)
 * @description Centraliza queries SQL na tabela newsletter para métricas do dashboard.
 * @usage       Injetado em Services (ex.: DashboardService) para totais/novos/ativos.
 * @notes       Somente SQL (sem regra de negócio). Baseado no dump: newsletter(id,email,nome,data_cadastro,status,ip).
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class NewsletterRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function countAll(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS total FROM newsletter");
        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function countByStatus(string $status): int
    {
        $sql = "SELECT COUNT(*) AS total FROM newsletter WHERE status = :status";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['status' => $status]);

        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function countActive(): int
    {
        return $this->countByStatus('ativo');
    }

    public function countInactive(): int
    {
        return $this->countByStatus('inativo');
    }

    public function countUnsubscribed(): int
    {
        return $this->countByStatus('desinscreve');
    }

    public function countToday(): int
    {
        $sql = "SELECT COUNT(*) AS total FROM newsletter WHERE DATE(data_cadastro) = CURDATE()";
        $stmt = $this->pdo->query($sql);

        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    /**
     * Conta cadastros dos últimos N dias (inclui hoje).
     */
    public function countLastDays(int $days): int
    {
        $days = max(1, min(365, $days));

        $sql = "
            SELECT COUNT(*) AS total
            FROM newsletter
            WHERE DATE(data_cadastro) >= DATE_SUB(CURDATE(), INTERVAL :days-1 DAY)
              AND DATE(data_cadastro) <= CURDATE()
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    /**
     * Conta cadastros ativos dos últimos N dias (inclui hoje).
     */
    public function countActiveLastDays(int $days): int
    {
        $days = max(1, min(365, $days));

        $sql = "
            SELECT COUNT(*) AS total
            FROM newsletter
            WHERE status = 'ativo'
              AND DATE(data_cadastro) >= DATE_SUB(CURDATE(), INTERVAL :days-1 DAY)
              AND DATE(data_cadastro) <= CURDATE()
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }
}