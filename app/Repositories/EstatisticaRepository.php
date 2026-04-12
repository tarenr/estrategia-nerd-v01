<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Repositories/EstatisticaRepository.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Repository de EstatÃ­sticas (SQL)
 * @description Centraliza queries SQL na tabela estatisticas para mÃ©tricas e sÃ©ries do dashboard.
 * @usage       Injetado em Services (ex.: DashboardService) para grÃ¡ficos e agregaÃ§Ãµes por perÃ­odo.
 * @notes       Somente SQL (sem regra de negÃ³cio). Baseado na tabela estatisticas(data, views, posts_novos, inscricoes).
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Repositories;

use DateTimeImmutable;
use PDO;

final class EstatisticaRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Views do dia (CURDATE()) a partir da tabela estatisticas.
     */
    public function viewsToday(): int
    {
        $sql = "SELECT COALESCE(views, 0) AS views FROM estatisticas WHERE data = CURDATE() LIMIT 1";
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($row['views'] ?? 0);
    }

    /**
     * Soma de views dos Ãºltimos N dias (inclui hoje).
     */
    public function viewsLastDays(int $days): int
    {
        $days = max(1, min(365, $days));

        $sql = "
            SELECT COALESCE(SUM(views), 0) AS total
            FROM estatisticas
            WHERE data >= DATE_SUB(CURDATE(), INTERVAL :days-1 DAY)
              AND data <= CURDATE()
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return (int)(($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0));
    }

    /**
     * Soma de posts_novos dos Ãºltimos N dias (inclui hoje).
     */
    public function postsNovosLastDays(int $days): int
    {
        $days = max(1, min(365, $days));

        $sql = "
            SELECT COALESCE(SUM(posts_novos), 0) AS total
            FROM estatisticas
            WHERE data >= DATE_SUB(CURDATE(), INTERVAL :days-1 DAY)
              AND data <= CURDATE()
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return (int)(($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0));
    }

    /**
     * Soma de inscricoes dos Ãºltimos N dias (inclui hoje).
     */
    public function inscricoesLastDays(int $days): int
    {
        $days = max(1, min(365, $days));

        $sql = "
            SELECT COALESCE(SUM(inscricoes), 0) AS total
            FROM estatisticas
            WHERE data >= DATE_SUB(CURDATE(), INTERVAL :days-1 DAY)
              AND data <= CURDATE()
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return (int)(($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0));
    }

    /**
     * SÃ©rie diÃ¡ria (para grÃ¡fico): retorna rows ordenadas por data asc.
     *
     * @return array<int, array{data:string, views:int, posts_novos:int, inscricoes:int}>
     */
    public function seriesLastDays(int $days): array
    {
        $days = max(1, min(365, $days));

        $sql = "
            SELECT
                DATE_FORMAT(data, '%Y-%m-%d') AS data,
                COALESCE(views, 0) AS views,
                COALESCE(posts_novos, 0) AS posts_novos,
                COALESCE(inscricoes, 0) AS inscricoes
            FROM estatisticas
            WHERE data >= DATE_SUB(CURDATE(), INTERVAL :days-1 DAY)
              AND data <= CURDATE()
            ORDER BY data ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('days', $days, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Cast defensivo (SQL only; sem cÃ¡lculo de negÃ³cio)
        return array_map(static function (array $r): array {
            return [
                'data' => (string)($r['data'] ?? ''),
                'views' => (int)($r['views'] ?? 0),
                'posts_novos' => (int)($r['posts_novos'] ?? 0),
                'inscricoes' => (int)($r['inscricoes'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * Busca por data especÃ­fica.
     *
     * @return array{data:string, views:int, posts_novos:int, inscricoes:int}|null
     */
    public function findByDate(DateTimeImmutable $date): ?array
    {
        $sql = "
            SELECT
                DATE_FORMAT(data, '%Y-%m-%d') AS data,
                COALESCE(views, 0) AS views,
                COALESCE(posts_novos, 0) AS posts_novos,
                COALESCE(inscricoes, 0) AS inscricoes
            FROM estatisticas
            WHERE data = :data
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['data' => $date->format('Y-m-d')]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'data' => (string)($row['data'] ?? ''),
            'views' => (int)($row['views'] ?? 0),
            'posts_novos' => (int)($row['posts_novos'] ?? 0),
            'inscricoes' => (int)($row['inscricoes'] ?? 0),
        ];
    }
    public function incrementViewsOnDate(string $date, int $amount = 1): void
    {
        $date = $this->normalizeDate($date);
        if ($date === null) {
            return;
        }

        $amount = max(1, $amount);

        $sql = '
            INSERT INTO estatisticas (data, views, posts_novos, inscricoes)
            VALUES (:data, :views, 0, 0)
            ON DUPLICATE KEY UPDATE views = views + VALUES(views)
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('data', $date);
        $stmt->bindValue('views', $amount, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function normalizeDate(string $date): ?string
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($date))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
