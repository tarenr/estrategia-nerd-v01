<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Repositories/CategoriaPostRepository.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Repository de Categorias de Post (SQL)
 * @description Centraliza queries SQL para categoria_post e métricas agregadas com posts.
 * @usage       Injetado em Services (ex.: DashboardService) para categoria mais popular.
 * @notes       Somente SQL (sem regra de negócio). Assume:
 *              - categoria_post(id, nome, cor)
 *              - posts(categoria_post_id, views)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CategoriaPostRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Categoria mais popular por quantidade de posts (desempate por views).
     *
     * @return array{nome:string,cor:string,total_posts:int,total_views:int}|null
     */
    public function mostPopular(): ?array
    {
        $sql = "
            SELECT
                c.nome AS nome,
                c.cor  AS cor,
                COUNT(p.id) AS total_posts,
                COALESCE(SUM(p.views), 0) AS total_views
            FROM categoria_post c
            LEFT JOIN posts p ON p.categoria_post_id = c.id
            GROUP BY c.id, c.nome, c.cor
            ORDER BY total_posts DESC, total_views DESC, c.id DESC
            LIMIT 1
        ";

        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'nome' => (string)($row['nome'] ?? ''),
            'cor' => (string)($row['cor'] ?? ''),
            'total_posts' => (int)($row['total_posts'] ?? 0),
            'total_views' => (int)($row['total_views'] ?? 0),
        ];
    }

    /**
     * Lista categorias com agregados (útil para telas futuras).
     *
     * @return array<int, array{nome:string,cor:string,total_posts:int,total_views:int}>
     */
    public function listWithAggregates(): array
    {
        $sql = "
            SELECT
                c.nome AS nome,
                c.cor  AS cor,
                COUNT(p.id) AS total_posts,
                COALESCE(SUM(p.views), 0) AS total_views
            FROM categoria_post c
            LEFT JOIN posts p ON p.categoria_post_id = c.id
            GROUP BY c.id, c.nome, c.cor
            ORDER BY total_posts DESC, total_views DESC, c.id DESC
        ";

        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $r): array {
            return [
                'nome' => (string)($r['nome'] ?? ''),
                'cor' => (string)($r['cor'] ?? ''),
                'total_posts' => (int)($r['total_posts'] ?? 0),
                'total_views' => (int)($r['total_views'] ?? 0),
            ];
        }, $rows);
    }
}