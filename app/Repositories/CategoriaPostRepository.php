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

    public function listAdmin(array $filters = []): array
    {
        [$whereSql, $params] = $this->buildAdminWhere($filters);
        $sql = "SELECT c.id, c.nome, c.slug, COALESCE(c.cor, '') AS cor, COALESCE(c.ativo, 1) AS ativo, COALESCE(c.ordem, 0) AS ordem, COUNT(p.id) AS total_posts, COALESCE(SUM(p.views), 0) AS total_views FROM categoria_post c LEFT JOIN posts p ON p.categoria_post_id = c.id {$whereSql} GROUP BY c.id, c.nome, c.slug, c.cor, c.ativo, c.ordem ORDER BY c.ordem ASC, c.nome ASC, c.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            return ['id' => (int) ($row['id'] ?? 0), 'nome' => (string) ($row['nome'] ?? ''), 'slug' => (string) ($row['slug'] ?? ''), 'cor' => (string) ($row['cor'] ?? ''), 'ativo' => (int) ($row['ativo'] ?? 1), 'ordem' => (int) ($row['ordem'] ?? 0), 'total_posts' => (int) ($row['total_posts'] ?? 0), 'total_views' => (int) ($row['total_views'] ?? 0)];
        }, $rows);
    }

    public function paginateAdmin(array $filters, int $page, int $perPage, string $sort, string $dir): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(50, $perPage));
        $sortMap = ['nome' => 'c.nome', 'slug' => 'c.slug', 'cor' => 'c.cor', 'ativo' => 'c.ativo', 'ordem' => 'c.ordem', 'total_posts' => 'total_posts', 'total_views' => 'total_views'];
        if (!isset($sortMap[$sort])) { $sort = 'ordem'; }

        $direction = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        $orderBy = $sortMap[$sort] . ' ' . $direction;
        $orderBy .= match ($sort) {
            'nome', 'slug', 'cor' => ', c.id DESC',
            'ativo' => ', c.ordem ASC, c.nome ASC, c.id DESC',
            'total_posts', 'total_views' => ', c.ordem ASC, c.nome ASC, c.id DESC',
            default => ', c.nome ASC, c.id DESC',
        };

        [$whereSql, $params] = $this->buildAdminWhere($filters);
        $countSql = "SELECT COUNT(*) FROM (SELECT c.id FROM categoria_post c LEFT JOIN posts p ON p.categoria_post_id = c.id {$whereSql} GROUP BY c.id, c.nome, c.slug, c.cor, c.ativo, c.ordem) filtered";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int) ($stmt->fetchColumn() ?: 0);
        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) { $page = $pages; }
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT c.id, c.nome, c.slug, COALESCE(c.cor, '') AS cor, COALESCE(c.ativo, 1) AS ativo, COALESCE(c.ordem, 0) AS ordem, COUNT(p.id) AS total_posts, COALESCE(SUM(p.views), 0) AS total_views FROM categoria_post c LEFT JOIN posts p ON p.categoria_post_id = c.id {$whereSql} GROUP BY c.id, c.nome, c.slug, c.cor, c.ativo, c.ordem ORDER BY {$orderBy} LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = array_map(static function (array $row): array {
            return ['id' => (int) ($row['id'] ?? 0), 'nome' => (string) ($row['nome'] ?? ''), 'slug' => (string) ($row['slug'] ?? ''), 'cor' => (string) ($row['cor'] ?? ''), 'ativo' => (int) ($row['ativo'] ?? 1), 'ordem' => (int) ($row['ordem'] ?? 0), 'total_posts' => (int) ($row['total_posts'] ?? 0), 'total_views' => (int) ($row['total_views'] ?? 0)];
        }, $rows);

        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    public function mostPopular(): ?array
    {
        $sql = "SELECT c.nome AS nome, c.cor AS cor, COUNT(p.id) AS total_posts, COALESCE(SUM(p.views), 0) AS total_views FROM categoria_post c LEFT JOIN posts p ON p.categoria_post_id = c.id GROUP BY c.id, c.nome, c.cor ORDER BY total_posts DESC, total_views DESC, c.id DESC LIMIT 1";
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { return null; }
        return ['nome' => (string) ($row['nome'] ?? ''), 'cor' => (string) ($row['cor'] ?? ''), 'total_posts' => (int) ($row['total_posts'] ?? 0), 'total_views' => (int) ($row['total_views'] ?? 0)];
    }

    public function listWithAggregates(): array
    {
        $sql = "SELECT c.nome AS nome, c.cor AS cor, COUNT(p.id) AS total_posts, COALESCE(SUM(p.views), 0) AS total_views FROM categoria_post c LEFT JOIN posts p ON p.categoria_post_id = c.id GROUP BY c.id, c.nome, c.cor ORDER BY total_posts DESC, total_views DESC, c.id DESC";
        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            return ['nome' => (string) ($row['nome'] ?? ''), 'cor' => (string) ($row['cor'] ?? ''), 'total_posts' => (int) ($row['total_posts'] ?? 0), 'total_views' => (int) ($row['total_views'] ?? 0)];
        }, $rows);
    }

    public function listForSelect(): array
    {
        $sql = "SELECT id, nome, slug, COALESCE(cor, '') AS cor FROM categoria_post WHERE ativo = 1 ORDER BY ordem ASC, nome ASC, id DESC";
        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            return ['id' => (int) ($row['id'] ?? 0), 'nome' => (string) ($row['nome'] ?? ''), 'slug' => (string) ($row['slug'] ?? ''), 'cor' => (string) ($row['cor'] ?? '')];
        }, $rows);
    }

    public function listForHome(int $limit = 4): array
    {
        $limit = max(1, min(12, $limit));
        $sql = "SELECT c.id, c.nome, c.slug, COALESCE(c.cor, '') AS cor,
                       COUNT(p.id) AS total_posts,
                       COALESCE(SUM(p.views), 0) AS total_views
                FROM categoria_post c
                LEFT JOIN posts p ON p.categoria_post_id = c.id AND p.status = 'publicado'
                WHERE COALESCE(c.ativo, 1) = 1
                GROUP BY c.id, c.nome, c.slug, c.cor, c.ordem
                HAVING COUNT(p.id) > 0
                ORDER BY total_posts DESC, total_views DESC, c.ordem ASC, c.nome ASC
                LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'nome' => (string) ($row['nome'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'cor' => (string) ($row['cor'] ?? ''),
                'total_posts' => (int) ($row['total_posts'] ?? 0),
                'total_views' => (int) ($row['total_views'] ?? 0),
            ];
        }, $rows);
    }

    public function listForBlog(): array
    {
        $sql = "SELECT c.id, c.nome, c.slug, COALESCE(c.cor, '') AS cor,
                       COUNT(p.id) AS total_posts
                FROM categoria_post c
                LEFT JOIN posts p ON p.categoria_post_id = c.id AND p.status = 'publicado'
                WHERE COALESCE(c.ativo, 1) = 1
                GROUP BY c.id, c.nome, c.slug, c.cor, c.ordem
                HAVING COUNT(p.id) > 0
                ORDER BY c.ordem ASC, c.nome ASC, c.id DESC";
        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'nome' => (string) ($row['nome'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'cor' => (string) ($row['cor'] ?? ''),
                'total_posts' => (int) ($row['total_posts'] ?? 0),
            ];
        }, $rows);
    }

    public function findPublicBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $sql = "SELECT c.id, c.nome, c.slug, COALESCE(c.cor, '') AS cor,
                       COUNT(p.id) AS total_posts
                FROM categoria_post c
                LEFT JOIN posts p ON p.categoria_post_id = c.id AND p.status = 'publicado'
                WHERE COALESCE(c.ativo, 1) = 1
                  AND c.slug = :slug
                GROUP BY c.id, c.nome, c.slug, c.cor
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'nome' => (string) ($row['nome'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'cor' => (string) ($row['cor'] ?? ''),
            'total_posts' => (int) ($row['total_posts'] ?? 0),
        ];
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT c.id, c.nome, c.slug, COALESCE(c.cor, '') AS cor, COALESCE(c.ativo, 1) AS ativo, COALESCE(c.ordem, 0) AS ordem, COUNT(p.id) AS total_posts, COALESCE(SUM(p.views), 0) AS total_views FROM categoria_post c LEFT JOIN posts p ON p.categoria_post_id = c.id WHERE c.id = :id GROUP BY c.id, c.nome, c.slug, c.cor, c.ativo, c.ordem LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) { return null; }
        return ['id' => (int) ($row['id'] ?? 0), 'nome' => (string) ($row['nome'] ?? ''), 'slug' => (string) ($row['slug'] ?? ''), 'cor' => (string) ($row['cor'] ?? ''), 'ativo' => (int) ($row['ativo'] ?? 1), 'ordem' => (int) ($row['ordem'] ?? 0), 'total_posts' => (int) ($row['total_posts'] ?? 0), 'total_views' => (int) ($row['total_views'] ?? 0)];
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM categoria_post WHERE slug = :slug';
        if ($ignoreId !== null) { $sql .= ' AND id <> :ignore_id'; }
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        if ($ignoreId !== null) { $stmt->bindValue(':ignore_id', $ignoreId, PDO::PARAM_INT); }
        $stmt->execute();
        return (int) ($stmt->fetchColumn() ?: 0) > 0;
    }

    public function nextAvailableSlug(string $baseSlug, ?int $ignoreId = null): string
    {
        $baseSlug = trim($baseSlug) !== '' ? trim($baseSlug) : 'categoria';
        $slug = $baseSlug;
        $suffix = 2;
        while ($this->slugExists($slug, $ignoreId)) { $slug = $baseSlug . '-' . $suffix; $suffix++; }
        return $slug;
    }

    public function insertAdmin(array $data): int
    {
        $sql = 'INSERT INTO categoria_post (nome, slug, cor, ativo, ordem) VALUES (:nome, :slug, :cor, :ativo, :ordem)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':nome', (string) ($data['nome'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':slug', (string) ($data['slug'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':cor', (string) ($data['cor'] ?? '#00d4ff'), PDO::PARAM_STR);
        $stmt->bindValue(':ativo', (int) ($data['ativo'] ?? 1), PDO::PARAM_INT);
        $stmt->bindValue(':ordem', (int) ($data['ordem'] ?? 0), PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    public function updateAdmin(int $id, array $data): void
    {
        $sql = 'UPDATE categoria_post SET nome = :nome, slug = :slug, cor = :cor, ativo = :ativo, ordem = :ordem WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':nome', (string) ($data['nome'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':slug', (string) ($data['slug'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':cor', (string) ($data['cor'] ?? '#00d4ff'), PDO::PARAM_STR);
        $stmt->bindValue(':ativo', (int) ($data['ativo'] ?? 1), PDO::PARAM_INT);
        $stmt->bindValue(':ordem', (int) ($data['ordem'] ?? 0), PDO::PARAM_INT);
        $stmt->execute();
    }

    public function deactivateById(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE categoria_post SET ativo = 0 WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function deleteById(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM categoria_post WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function buildAdminWhere(array $filters): array
    {
        $where = [];
        $params = [];
        $busca = trim((string) ($filters['busca'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        if ($busca !== '') { $where[] = '(c.nome LIKE :busca_nome OR c.slug LIKE :busca_slug)'; $params[':busca_nome'] = '%' . $busca . '%'; $params[':busca_slug'] = '%' . $busca . '%'; }
        if ($status === 'ativas') { $where[] = 'COALESCE(c.ativo, 1) = 1'; }
        elseif ($status === 'inativas') { $where[] = 'COALESCE(c.ativo, 1) = 0'; }
        elseif ($status === 'com_posts') { $where[] = 'EXISTS (SELECT 1 FROM posts px WHERE px.categoria_post_id = c.id)'; }
        elseif ($status === 'sem_posts') { $where[] = 'NOT EXISTS (SELECT 1 FROM posts px WHERE px.categoria_post_id = c.id)'; }
        return [$where ? ('WHERE ' . implode(' AND ', $where)) : '', $params];
    }
}
