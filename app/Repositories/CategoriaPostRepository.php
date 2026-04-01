<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Repositories/CategoriaPostRepository.php
 * @project     Estrategia Nerd
 * @purpose     Repository de Categorias de Post (SQL)
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
            'nome' => (string) ($row['nome'] ?? ''),
            'cor' => (string) ($row['cor'] ?? ''),
            'total_posts' => (int) ($row['total_posts'] ?? 0),
            'total_views' => (int) ($row['total_views'] ?? 0),
        ];
    }

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

        return array_map(static function (array $row): array {
            return [
                'nome' => (string) ($row['nome'] ?? ''),
                'cor' => (string) ($row['cor'] ?? ''),
                'total_posts' => (int) ($row['total_posts'] ?? 0),
                'total_views' => (int) ($row['total_views'] ?? 0),
            ];
        }, $rows);
    }

    public function listForSelect(): array
    {
        $sql = "
            SELECT
                id,
                nome,
                slug,
                COALESCE(cor, '') AS cor
            FROM categoria_post
            WHERE ativo = 1
            ORDER BY ordem ASC, nome ASC, id DESC
        ";

        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'nome' => (string) ($row['nome'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'cor' => (string) ($row['cor'] ?? ''),
            ];
        }, $rows);
    }
}
