<?php

declare(strict_types=1);

namespace App\Repositories;

use DateTimeImmutable;
use PDO;

final class LinkClickRepository
{
    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
    }

    public function registerClick(int $linkId, string $origin, string $sessionHash, string $referer, string $userAgent): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO link_clicks (link_id, origem, referer, session_hash, user_agent, clicked_at)
            VALUES (:link_id, :origem, :referer, :session_hash, :user_agent, :clicked_at)
        ');
        $stmt->execute([
            'link_id' => $linkId,
            'origem' => $this->nullable($origin, 60),
            'referer' => $this->nullable($referer, 2048),
            'session_hash' => $this->nullable($sessionHash, 64),
            'user_agent' => $this->nullable($userAgent, 255),
            'clicked_at' => $this->nowLocal(),
        ]);
    }

    public function summaryByRange(string $start, string $end): array
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) AS total,
                   COUNT(DISTINCT session_hash) AS unique_sessions,
                   MAX(clicked_at) AS last_click_at
            FROM link_clicks
            WHERE clicked_at BETWEEN :start_at AND :end_at
        ');
        $stmt->execute([
            'start_at' => $start . ' 00:00:00',
            'end_at' => $end . ' 23:59:59',
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'unique_sessions' => (int) ($row['unique_sessions'] ?? 0),
            'last_click_at' => (string) ($row['last_click_at'] ?? ''),
        ];
    }

    public function countToday(): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS total FROM link_clicks WHERE DATE(clicked_at) = :today');
        $stmt->execute(['today' => $this->todayLocal()]);
        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    /**
     * @param array<int,int> $linkIds
     * @return array<int, array{total_clicks:int, clicks_today:int, last_click_at:string}>
     */
    public function metricsByLinkIds(array $linkIds): array
    {
        $linkIds = array_values(array_unique(array_filter(array_map('intval', $linkIds), static fn (int $id): bool => $id > 0)));
        if ($linkIds === []) {
            return [];
        }

        $params = ['today' => $this->todayLocal()];
        $placeholders = [];
        foreach ($linkIds as $index => $id) {
            $key = 'id' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $stmt = $this->pdo->prepare(
            'SELECT link_id,
                    COUNT(*) AS total_clicks,
                    SUM(CASE WHEN DATE(clicked_at) = :today THEN 1 ELSE 0 END) AS clicks_today,
                    MAX(clicked_at) AS last_click_at
             FROM link_clicks
             WHERE link_id IN (' . implode(', ', $placeholders) . ')
             GROUP BY link_id'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, $key === 'today' ? PDO::PARAM_STR : PDO::PARAM_INT);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $metrics = [];
        foreach ($rows as $row) {
            $metrics[(int) ($row['link_id'] ?? 0)] = [
                'total_clicks' => (int) ($row['total_clicks'] ?? 0),
                'clicks_today' => (int) ($row['clicks_today'] ?? 0),
                'last_click_at' => (string) ($row['last_click_at'] ?? ''),
            ];
        }

        return $metrics;
    }

    public function topLinksByRange(string $start, string $end, int $limit = 5): array
    {
        $limit = max(1, min(10, $limit));

        $stmt = $this->pdo->prepare('
            SELECT l.id,
                   l.titulo,
                   l.slug,
                   l.tipo,
                   l.promocao,
                   COUNT(*) AS total_clicks,
                   MAX(lc.clicked_at) AS last_click_at
            FROM link_clicks lc
            INNER JOIN links l ON l.id = lc.link_id
            WHERE lc.clicked_at BETWEEN :start_at AND :end_at
            GROUP BY l.id, l.titulo, l.slug, l.tipo, l.promocao
            ORDER BY total_clicks DESC, last_click_at DESC, l.titulo ASC
            LIMIT :limit
        ');
        $stmt->bindValue('start_at', $start . ' 00:00:00');
        $stmt->bindValue('end_at', $end . ' 23:59:59');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function sectionBreakdownByRange(string $start, string $end): array
    {
        $stmt = $this->pdo->prepare('
            SELECT CASE
                     WHEN l.tipo = "produto" AND l.promocao = 1 THEN "Promocoes"
                     WHEN l.tipo = "produto" THEN "Produtos"
                     WHEN l.tipo = "cupom" THEN "Cupons"
                     WHEN l.tipo = "conteudo" THEN "Conteudo"
                     WHEN l.tipo = "rede_social" THEN "Rede Social"
                     WHEN l.tipo = "servico" THEN "Servicos"
                     ELSE "Outros"
                   END AS secao,
                   COUNT(*) AS total_clicks
            FROM link_clicks lc
            INNER JOIN links l ON l.id = lc.link_id
            WHERE lc.clicked_at BETWEEN :start_at AND :end_at
            GROUP BY secao
            ORDER BY total_clicks DESC, secao ASC
        ');
        $stmt->execute([
            'start_at' => $start . ' 00:00:00',
            'end_at' => $end . ' 23:59:59',
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS link_clicks (
                id INT(11) NOT NULL AUTO_INCREMENT,
                link_id INT(11) NOT NULL,
                origem VARCHAR(60) NULL,
                referer VARCHAR(2048) NULL,
                session_hash CHAR(64) NULL,
                user_agent VARCHAR(255) NULL,
                clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_link_clicks_link_id (link_id),
                KEY idx_link_clicks_clicked_at (clicked_at),
                KEY idx_link_clicks_session_hash (session_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    private function nullable(string $value, int $maxLen): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLen);
    }

    private function todayLocal(): string
    {
        return (new DateTimeImmutable('today'))->format('Y-m-d');
    }

    private function nowLocal(): string
    {
        return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    }
}