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
        $sql = "SELECT
                    c.id,
                    c.nome,
                    c.slug,
                    COALESCE(c.descricao, '') AS descricao_publica,
                    COALESCE(c.seo_title, '') AS seo_title,
                    COALESCE(c.seo_description, '') AS seo_description,
                    COALESCE(c.cor, '') AS cor,
                    COALESCE(c.ativo, 1) AS ativo,
                    COALESCE(c.indexar, 1) AS indexar,
                    COALESCE(c.exibir_no_menu, 1) AS exibir_no_menu,
                    COALESCE(c.ordem, 0) AS ordem,
                    COUNT(p.id) AS total_posts,
                    COALESCE(SUM(p.views), 0) AS total_views
                FROM categoria_post c
                LEFT JOIN posts p ON p.categoria_post_id = c.id
                {$whereSql}
                GROUP BY c.id, c.nome, c.slug, c.descricao, c.seo_title, c.seo_description, c.cor, c.ativo, c.indexar, c.exibir_no_menu, c.ordem
                ORDER BY c.ordem ASC, c.nome ASC, c.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(fn (array $row): array => $this->hydrateAdminRow($row), $rows);
    }

    public function paginateAdmin(array $filters, int $page, int $perPage, string $sort, string $dir): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(50, $perPage));
        $sortMap = [
            'nome' => 'c.nome',
            'slug' => 'c.slug',
            'ativo' => 'c.ativo',
            'indexar' => 'c.indexar',
            'exibir_no_menu' => 'c.exibir_no_menu',
            'ordem' => 'c.ordem',
            'total_posts' => 'total_posts',
            'total_views' => 'total_views',
        ];
        if (!isset($sortMap[$sort])) {
            $sort = 'ordem';
        }

        $direction = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        $orderBy = $sortMap[$sort] . ' ' . $direction;
        $orderBy .= match ($sort) {
            'nome', 'slug' => ', c.id DESC',
            'ativo', 'indexar', 'exibir_no_menu' => ', c.ordem ASC, c.nome ASC, c.id DESC',
            'total_posts', 'total_views' => ', c.ordem ASC, c.nome ASC, c.id DESC',
            default => ', c.nome ASC, c.id DESC',
        };

        [$whereSql, $params] = $this->buildAdminWhere($filters);
        $countSql = "SELECT COUNT(*) FROM (
                        SELECT c.id
                        FROM categoria_post c
                        LEFT JOIN posts p ON p.categoria_post_id = c.id
                        {$whereSql}
                        GROUP BY c.id, c.nome, c.slug, c.descricao, c.seo_title, c.seo_description, c.cor, c.ativo, c.indexar, c.exibir_no_menu, c.ordem
                    ) filtered";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int) ($stmt->fetchColumn() ?: 0);
        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT
                    c.id,
                    c.nome,
                    c.slug,
                    COALESCE(c.descricao, '') AS descricao_publica,
                    COALESCE(c.seo_title, '') AS seo_title,
                    COALESCE(c.seo_description, '') AS seo_description,
                    COALESCE(c.cor, '') AS cor,
                    COALESCE(c.ativo, 1) AS ativo,
                    COALESCE(c.indexar, 1) AS indexar,
                    COALESCE(c.exibir_no_menu, 1) AS exibir_no_menu,
                    COALESCE(c.ordem, 0) AS ordem,
                    COUNT(p.id) AS total_posts,
                    COALESCE(SUM(p.views), 0) AS total_views
                FROM categoria_post c
                LEFT JOIN posts p ON p.categoria_post_id = c.id
                {$whereSql}
                GROUP BY c.id, c.nome, c.slug, c.descricao, c.seo_title, c.seo_description, c.cor, c.ativo, c.indexar, c.exibir_no_menu, c.ordem
                ORDER BY {$orderBy}
                LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = array_map(fn (array $row): array => $this->hydrateAdminRow($row), $rows);

        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    public function mostPopular(): ?array
    {
        $sql = "SELECT c.nome AS nome, c.cor AS cor, COUNT(p.id) AS total_posts, COALESCE(SUM(p.views), 0) AS total_views
                FROM categoria_post c
                LEFT JOIN posts p ON p.categoria_post_id = c.id
                GROUP BY c.id, c.nome, c.cor
                ORDER BY total_posts DESC, total_views DESC, c.id DESC
                LIMIT 1";
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
        $sql = "SELECT c.nome AS nome, c.cor AS cor, COUNT(p.id) AS total_posts, COALESCE(SUM(p.views), 0) AS total_views
                FROM categoria_post c
                LEFT JOIN posts p ON p.categoria_post_id = c.id
                GROUP BY c.id, c.nome, c.cor
                ORDER BY total_posts DESC, total_views DESC, c.id DESC";
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
        $sql = "SELECT id, nome, slug, COALESCE(cor, '') AS cor
                FROM categoria_post
                WHERE COALESCE(ativo, 1) = 1
                ORDER BY ordem ASC, nome ASC, id DESC";
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
                ORDER BY total_views DESC, total_posts DESC, c.ordem ASC, c.nome ASC
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
                       COALESCE(c.descricao, '') AS descricao_publica,
                       COALESCE(c.indexar, 1) AS indexar,
                       COALESCE(c.exibir_no_menu, 1) AS exibir_no_menu,
                       COUNT(p.id) AS total_posts
                FROM categoria_post c
                LEFT JOIN posts p ON p.categoria_post_id = c.id AND p.status = 'publicado'
                WHERE COALESCE(c.ativo, 1) = 1
                  AND COALESCE(c.exibir_no_menu, 1) = 1
                GROUP BY c.id, c.nome, c.slug, c.cor, c.descricao, c.indexar, c.exibir_no_menu, c.ordem
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
                'descricao_publica' => (string) ($row['descricao_publica'] ?? ''),
                'indexar' => (int) ($row['indexar'] ?? 1),
                'exibir_no_menu' => (int) ($row['exibir_no_menu'] ?? 1),
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

        $sql = "SELECT c.id, c.nome, c.slug,
                       COALESCE(c.cor, '') AS cor,
                       COALESCE(c.descricao, '') AS descricao_publica,
                       COALESCE(c.seo_title, '') AS seo_title,
                       COALESCE(c.seo_description, '') AS seo_description,
                       COALESCE(c.indexar, 1) AS indexar,
                       COALESCE(c.exibir_no_menu, 1) AS exibir_no_menu,
                       COUNT(p.id) AS total_posts
                FROM categoria_post c
                LEFT JOIN posts p ON p.categoria_post_id = c.id AND p.status = 'publicado'
                WHERE COALESCE(c.ativo, 1) = 1
                  AND c.slug = :slug
                GROUP BY c.id, c.nome, c.slug, c.cor, c.descricao, c.seo_title, c.seo_description, c.indexar, c.exibir_no_menu
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
            'descricao_publica' => (string) ($row['descricao_publica'] ?? ''),
            'seo_title' => (string) ($row['seo_title'] ?? ''),
            'seo_description' => (string) ($row['seo_description'] ?? ''),
            'indexar' => (int) ($row['indexar'] ?? 1),
            'exibir_no_menu' => (int) ($row['exibir_no_menu'] ?? 1),
            'total_posts' => (int) ($row['total_posts'] ?? 0),
        ];
    }

    public function publishedForSitemap(): array
    {
        $sql = "SELECT c.slug,
                       MAX(COALESCE(p.data_atualizacao, p.data_publicacao)) AS lastmod,
                       COUNT(p.id) AS total_posts
                FROM categoria_post c
                INNER JOIN posts p ON p.categoria_post_id = c.id AND p.status = 'publicado'
                WHERE COALESCE(c.ativo, 1) = 1
                  AND COALESCE(c.indexar, 1) = 1
                  AND c.slug <> ''
                GROUP BY c.id, c.slug, c.ordem, c.nome
                HAVING COUNT(p.id) > 0
                ORDER BY c.ordem ASC, c.nome ASC, c.id DESC";
        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT
                    c.id,
                    c.nome,
                    c.slug,
                    COALESCE(c.descricao, '') AS descricao_publica,
                    COALESCE(c.seo_title, '') AS seo_title,
                    COALESCE(c.seo_description, '') AS seo_description,
                    COALESCE(c.cor, '') AS cor,
                    COALESCE(c.ativo, 1) AS ativo,
                    COALESCE(c.indexar, 1) AS indexar,
                    COALESCE(c.exibir_no_menu, 1) AS exibir_no_menu,
                    COALESCE(c.ordem, 0) AS ordem,
                    COUNT(p.id) AS total_posts,
                    COALESCE(SUM(p.views), 0) AS total_views
                FROM categoria_post c
                LEFT JOIN posts p ON p.categoria_post_id = c.id
                WHERE c.id = :id
                GROUP BY c.id, c.nome, c.slug, c.descricao, c.seo_title, c.seo_description, c.cor, c.ativo, c.indexar, c.exibir_no_menu, c.ordem
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->hydrateAdminRow($row);
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM categoria_post WHERE slug = :slug';
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        if ($ignoreId !== null) {
            $stmt->bindValue(':ignore_id', $ignoreId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) ($stmt->fetchColumn() ?: 0) > 0;
    }

    public function nextAvailableSlug(string $baseSlug, ?int $ignoreId = null): string
    {
        $baseSlug = trim($baseSlug) !== '' ? trim($baseSlug) : 'categoria';
        $slug = $baseSlug;
        $suffix = 2;
        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }
        return $slug;
    }

    public function insertAdmin(array $data): int
    {
        $sql = 'INSERT INTO categoria_post (nome, slug, descricao, seo_title, seo_description, cor, ativo, indexar, exibir_no_menu, ordem) VALUES (:nome, :slug, :descricao, :seo_title, :seo_description, :cor, :ativo, :indexar, :exibir_no_menu, :ordem)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'nome' => (string) ($data['nome'] ?? ''),
            'slug' => (string) ($data['slug'] ?? ''),
            'descricao' => $this->nullableString($data['descricao_publica'] ?? null),
            'seo_title' => $this->nullableString($data['seo_title'] ?? null),
            'seo_description' => $this->nullableString($data['seo_description'] ?? null),
            'cor' => (string) ($data['cor'] ?? '#00d4ff'),
            'ativo' => (int) ($data['ativo'] ?? 1),
            'indexar' => (int) ($data['indexar'] ?? 1),
            'exibir_no_menu' => (int) ($data['exibir_no_menu'] ?? 1),
            'ordem' => (int) ($data['ordem'] ?? 0),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateAdmin(int $id, array $data): void
    {
        $sql = 'UPDATE categoria_post SET nome = :nome, slug = :slug, descricao = :descricao, seo_title = :seo_title, seo_description = :seo_description, cor = :cor, ativo = :ativo, indexar = :indexar, exibir_no_menu = :exibir_no_menu, ordem = :ordem WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'nome' => (string) ($data['nome'] ?? ''),
            'slug' => (string) ($data['slug'] ?? ''),
            'descricao' => $this->nullableString($data['descricao_publica'] ?? null),
            'seo_title' => $this->nullableString($data['seo_title'] ?? null),
            'seo_description' => $this->nullableString($data['seo_description'] ?? null),
            'cor' => (string) ($data['cor'] ?? '#00d4ff'),
            'ativo' => (int) ($data['ativo'] ?? 1),
            'indexar' => (int) ($data['indexar'] ?? 1),
            'exibir_no_menu' => (int) ($data['exibir_no_menu'] ?? 1),
            'ordem' => (int) ($data['ordem'] ?? 0),
        ]);
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
        if ($busca !== '') {
            $where[] = '(c.nome LIKE :busca_nome OR c.slug LIKE :busca_slug OR COALESCE(c.seo_title, \'\') LIKE :busca_seo)';
            $params[':busca_nome'] = '%' . $busca . '%';
            $params[':busca_slug'] = '%' . $busca . '%';
            $params[':busca_seo'] = '%' . $busca . '%';
        }
        if ($status === 'ativas') {
            $where[] = 'COALESCE(c.ativo, 1) = 1';
        } elseif ($status === 'inativas') {
            $where[] = 'COALESCE(c.ativo, 1) = 0';
        } elseif ($status === 'com_posts') {
            $where[] = 'EXISTS (SELECT 1 FROM posts px WHERE px.categoria_post_id = c.id)';
        } elseif ($status === 'sem_posts') {
            $where[] = 'NOT EXISTS (SELECT 1 FROM posts px WHERE px.categoria_post_id = c.id)';
        } elseif ($status === 'indexaveis') {
            $where[] = 'COALESCE(c.indexar, 1) = 1';
        } elseif ($status === 'noindex') {
            $where[] = 'COALESCE(c.indexar, 1) = 0';
        } elseif ($status === 'menu') {
            $where[] = 'COALESCE(c.exibir_no_menu, 1) = 1';
        } elseif ($status === 'fora_menu') {
            $where[] = 'COALESCE(c.exibir_no_menu, 1) = 0';
        }
        return [$where ? ('WHERE ' . implode(' AND ', $where)) : '', $params];
    }

    private function hydrateAdminRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'nome' => (string) ($row['nome'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'descricao_publica' => (string) ($row['descricao_publica'] ?? ''),
            'seo_title' => (string) ($row['seo_title'] ?? ''),
            'seo_description' => (string) ($row['seo_description'] ?? ''),
            'cor' => (string) ($row['cor'] ?? ''),
            'ativo' => (int) ($row['ativo'] ?? 1),
            'indexar' => (int) ($row['indexar'] ?? 1),
            'exibir_no_menu' => (int) ($row['exibir_no_menu'] ?? 1),
            'ordem' => (int) ($row['ordem'] ?? 0),
            'total_posts' => (int) ($row['total_posts'] ?? 0),
            'total_views' => (int) ($row['total_views'] ?? 0),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);
        return $normalized !== '' ? $normalized : null;
    }
}
