<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Repositories/PostRepository.php
 * @project     Estrategia Nerd
 * @purpose     Repository de Posts (SQL)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PostRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function countAll(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) AS total FROM posts');
        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function countByStatus(): array
    {
        $sql = "SELECT SUM(CASE WHEN status = 'publicado' THEN 1 ELSE 0 END) AS publicados, SUM(CASE WHEN status = 'rascunho' THEN 1 ELSE 0 END) AS rascunhos, SUM(CASE WHEN status = 'agendado' THEN 1 ELSE 0 END) AS agendados FROM posts";
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'publicados' => (int) ($row['publicados'] ?? 0),
            'rascunhos' => (int) ($row['rascunhos'] ?? 0),
            'agendados' => (int) ($row['agendados'] ?? 0),
        ];
    }

    public function sumViews(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(SUM(views), 0) AS total FROM posts');
        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function sumLikes(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(SUM(curtidas), 0) AS total FROM posts');
        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function sumComentariosCount(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(SUM(comentarios_count), 0) AS total FROM posts');
        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function countToday(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS total FROM posts WHERE DATE(data_publicacao) = CURDATE()");
        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function latestWithCategoria(int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));
        $sql = "SELECT p.id, p.titulo, p.slug, p.imagem_capa, p.status, p.data_publicacao, p.views, p.curtidas, p.comentarios_count, c.nome AS categoria_nome, c.cor AS categoria_cor FROM posts p LEFT JOIN categoria_post c ON c.id = p.categoria_post_id ORDER BY p.data_publicacao DESC, p.id DESC LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countPublished(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS total FROM posts WHERE status = 'publicado'");
        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function latestPublicWithCategoria(int $limit = 6, array $excludeIds = []): array
    {
        $limit = max(1, min(24, $limit));
        $excludeIds = array_values(array_filter(array_map('intval', $excludeIds), static fn (int $id): bool => $id > 0));

        $where = ["p.status = 'publicado'"];
        if ($excludeIds !== []) {
            $where[] = 'p.id NOT IN (' . implode(',', $excludeIds) . ')';
        }

        $sql = "SELECT p.id, p.titulo, p.slug, p.resumo, p.imagem_capa, p.imagem_thumb, p.status, p.data_publicacao, COALESCE(p.views, 0) AS views, COALESCE(p.tempo_leitura, 5) AS tempo_leitura, COALESCE(p.comentarios_count, 0) AS comentarios_count, COALESCE(p.destaque, 0) AS destaque, c.nome AS categoria_nome, c.slug AS categoria_slug, c.cor AS categoria_cor
                FROM posts p
                LEFT JOIN categoria_post c ON c.id = p.categoria_post_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY p.data_publicacao DESC, p.id DESC
                LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function featuredPublic(int $limit = 2): array
    {
        $limit = max(1, min(6, $limit));
        $sql = "SELECT p.id, p.titulo, p.slug, p.resumo, p.imagem_capa, p.imagem_thumb, p.data_publicacao, COALESCE(p.views, 0) AS views, COALESCE(p.tempo_leitura, 5) AS tempo_leitura, COALESCE(p.comentarios_count, 0) AS comentarios_count, COALESCE(p.destaque, 0) AS destaque, c.nome AS categoria_nome, c.slug AS categoria_slug, c.cor AS categoria_cor
                FROM posts p
                LEFT JOIN categoria_post c ON c.id = p.categoria_post_id
                WHERE p.status = 'publicado'
                ORDER BY COALESCE(p.destaque, 0) DESC, p.data_publicacao DESC, p.id DESC
                LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function paginatePublic(array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(24, $perPage));
        $offset = ($page - 1) * $perPage;

        [$whereSql, $params] = $this->buildPublicWhere($filters);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM posts p LEFT JOIN categoria_post c ON c.id = p.categoria_post_id {$whereSql}");
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetchColumn() ?: 0);
        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
            $offset = ($page - 1) * $perPage;
        }

        $sql = "SELECT p.id, p.titulo, p.slug, p.resumo, p.imagem_capa, p.imagem_thumb, p.data_publicacao,
                       COALESCE(p.views, 0) AS views, COALESCE(p.tempo_leitura, 5) AS tempo_leitura,
                       COALESCE(p.comentarios_count, 0) AS comentarios_count, COALESCE(p.destaque, 0) AS destaque,
                       c.nome AS categoria_nome, c.slug AS categoria_slug, c.cor AS categoria_cor
                FROM posts p
                LEFT JOIN categoria_post c ON c.id = p.categoria_post_id
                {$whereSql}
                ORDER BY COALESCE(p.destaque, 0) DESC, p.data_publicacao DESC, p.id DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    public function featuredPublicOne(array $filters = []): ?array
    {
        $filters['featured_first'] = true;
        [$whereSql, $params] = $this->buildPublicWhere($filters);

        $sql = "SELECT p.id, p.titulo, p.slug, p.resumo, p.imagem_capa, p.imagem_thumb, p.data_publicacao,
                       COALESCE(p.views, 0) AS views, COALESCE(p.tempo_leitura, 5) AS tempo_leitura,
                       COALESCE(p.comentarios_count, 0) AS comentarios_count, COALESCE(p.destaque, 0) AS destaque,
                       c.nome AS categoria_nome, c.slug AS categoria_slug, c.cor AS categoria_cor
                FROM posts p
                LEFT JOIN categoria_post c ON c.id = p.categoria_post_id
                {$whereSql}
                ORDER BY COALESCE(p.destaque, 0) DESC, p.data_publicacao DESC, p.id DESC
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function summaryFiltered(array $filters): array
    {
        [$whereSql, $params] = $this->buildAdminWhere($filters);
        $sql = "SELECT COUNT(*) AS total_posts, SUM(CASE WHEN p.status = 'publicado' THEN 1 ELSE 0 END) AS publicados, SUM(CASE WHEN p.status = 'rascunho' THEN 1 ELSE 0 END) AS rascunhos, SUM(CASE WHEN p.status = 'agendado' THEN 1 ELSE 0 END) AS agendados, SUM(CASE WHEN COALESCE(p.destaque, 0) = 1 THEN 1 ELSE 0 END) AS destaques, COALESCE(SUM(p.views), 0) AS total_views, COALESCE(SUM(p.curtidas), 0) AS total_curtidas, COALESCE(SUM(p.comentarios_count), 0) AS total_comentarios FROM posts p {$whereSql}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_posts' => (int) ($row['total_posts'] ?? 0),
            'publicados' => (int) ($row['publicados'] ?? 0),
            'rascunhos' => (int) ($row['rascunhos'] ?? 0),
            'agendados' => (int) ($row['agendados'] ?? 0),
            'destaques' => (int) ($row['destaques'] ?? 0),
            'total_views' => (int) ($row['total_views'] ?? 0),
            'total_curtidas' => (int) ($row['total_curtidas'] ?? 0),
            'total_comentarios' => (int) ($row['total_comentarios'] ?? 0),
        ];
    }

    public function paginateAdmin(array $filters, int $page, int $perPage, string $sort, string $dir): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(50, $perPage));
        $sortMap = ['id' => 'p.id', 'titulo' => 'p.titulo', 'categoria' => "COALESCE(cp.nome, 'Sem categoria')", 'status' => 'p.status', 'data' => 'p.data_publicacao', 'views' => 'p.views', 'curtidas' => 'p.curtidas', 'comentarios' => 'p.comentarios_count'];
        if (!isset($sortMap[$sort])) { $sort = 'data'; }
        $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';
        $orderBy = $sortMap[$sort] . " {$dir}";
        $orderBy .= match ($sort) { 'titulo' => ', p.id DESC', 'categoria' => ', p.titulo ASC, p.id DESC', 'status' => ', p.data_publicacao DESC, p.id DESC', 'views', 'curtidas', 'comentarios' => ', p.data_publicacao DESC, p.id DESC', 'id' => '', default => ', p.id DESC', };
        [$whereSql, $params] = $this->buildAdminWhere($filters);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM posts p {$whereSql}");
        $stmt->execute($params);
        $total = (int) ($stmt->fetchColumn() ?: 0);
        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) { $page = $pages; }
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT p.id, p.titulo, p.slug, p.imagem_capa, p.status, p.data_publicacao, COALESCE(p.views, 0) AS views, COALESCE(p.curtidas, 0) AS curtidas, COALESCE(p.comentarios_count, 0) AS comentarios_count, COALESCE(p.destaque, 0) AS destaque, p.categoria_post_id, COALESCE(cp.nome, 'Sem categoria') AS categoria_nome, cp.cor AS categoria_cor FROM posts p LEFT JOIN categoria_post cp ON cp.id = p.categoria_post_id {$whereSql} ORDER BY {$orderBy} LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
    }

    public function topByViews(): ?array
    {
        $stmt = $this->pdo->query("SELECT titulo, views FROM posts ORDER BY views DESC, id DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? ['titulo' => (string) ($row['titulo'] ?? ''), 'views' => (int) ($row['views'] ?? 0)] : null;
    }

    public function topByLikes(): ?array
    {
        $stmt = $this->pdo->query("SELECT titulo, curtidas FROM posts ORDER BY curtidas DESC, id DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? ['titulo' => (string) ($row['titulo'] ?? ''), 'curtidas' => (int) ($row['curtidas'] ?? 0)] : null;
    }

    public function topByComments(): ?array
    {
        $stmt = $this->pdo->query("SELECT titulo, comentarios_count FROM posts ORDER BY comentarios_count DESC, id DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? ['titulo' => (string) ($row['titulo'] ?? ''), 'comentarios_count' => (int) ($row['comentarios_count'] ?? 0)] : null;
    }

    public function findAdminById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, titulo, slug, resumo, conteudo, categoria, categoria_post_id, imagem_capa, imagem_thumb, autor_id, data_publicacao, tempo_leitura, seo_title, seo_description, seo_keywords, tags, status, destaque FROM posts WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function deleteById(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM posts WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function nextAvailableSlug(string $baseSlug, ?int $ignoreId = null): string
    {
        $baseSlug = trim($baseSlug) !== '' ? trim($baseSlug) : 'post';
        $slug = $baseSlug;
        $suffix = 2;
        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }
        return $slug;
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM posts WHERE slug = :slug';
        if ($ignoreId !== null) { $sql .= ' AND id <> :ignore_id'; }
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
        if ($ignoreId !== null) { $stmt->bindValue(':ignore_id', $ignoreId, PDO::PARAM_INT); }
        $stmt->execute();
        return (int) ($stmt->fetchColumn() ?: 0) > 0;
    }

    public function insertAdmin(array $data): int
    {
        $sql = "INSERT INTO posts (titulo, slug, resumo, conteudo, categoria, categoria_post_id, imagem_capa, imagem_thumb, autor_id, data_publicacao, tempo_leitura, seo_title, seo_description, seo_keywords, tags, status, destaque, views, curtidas, comentarios_count, likes_count) VALUES (:titulo, :slug, :resumo, :conteudo, :categoria, :categoria_post_id, :imagem_capa, :imagem_thumb, :autor_id, :data_publicacao, :tempo_leitura, :seo_title, :seo_description, :seo_keywords, :tags, :status, :destaque, 0, 0, 0, 0)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':titulo', (string) ($data['titulo'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':slug', (string) ($data['slug'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':resumo', (string) ($data['resumo'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':conteudo', (string) ($data['conteudo'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':categoria', (string) ($data['categoria'] ?? 'gadgets'), PDO::PARAM_STR);
        $stmt->bindValue(':categoria_post_id', (int) ($data['categoria_post_id'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':imagem_capa', (string) ($data['imagem_capa'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':imagem_thumb', (string) ($data['imagem_thumb'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':autor_id', (int) ($data['autor_id'] ?? 1), PDO::PARAM_INT);
        $stmt->bindValue(':data_publicacao', (string) ($data['data_publicacao'] ?? date('Y-m-d H:i:s')), PDO::PARAM_STR);
        $stmt->bindValue(':tempo_leitura', (int) ($data['tempo_leitura'] ?? 5), PDO::PARAM_INT);
        $stmt->bindValue(':seo_title', (string) ($data['seo_title'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':seo_description', (string) ($data['seo_description'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':seo_keywords', (string) ($data['seo_keywords'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':tags', (string) ($data['tags'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':status', (string) ($data['status'] ?? 'rascunho'), PDO::PARAM_STR);
        $stmt->bindValue(':destaque', (int) ($data['destaque'] ?? 0), PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    public function updateAdmin(int $id, array $data): void
    {
        $sql = 'UPDATE posts SET titulo = :titulo, slug = :slug, resumo = :resumo, conteudo = :conteudo, categoria = :categoria, categoria_post_id = :categoria_post_id, imagem_capa = :imagem_capa, imagem_thumb = :imagem_thumb, autor_id = :autor_id, data_publicacao = :data_publicacao, tempo_leitura = :tempo_leitura, seo_title = :seo_title, seo_description = :seo_description, seo_keywords = :seo_keywords, tags = :tags, status = :status, destaque = :destaque WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':titulo', (string) ($data['titulo'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':slug', (string) ($data['slug'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':resumo', (string) ($data['resumo'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':conteudo', (string) ($data['conteudo'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':categoria', (string) ($data['categoria'] ?? 'gadgets'), PDO::PARAM_STR);
        $stmt->bindValue(':categoria_post_id', (int) ($data['categoria_post_id'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':imagem_capa', (string) ($data['imagem_capa'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':imagem_thumb', (string) ($data['imagem_thumb'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':autor_id', (int) ($data['autor_id'] ?? 1), PDO::PARAM_INT);
        $stmt->bindValue(':data_publicacao', (string) ($data['data_publicacao'] ?? date('Y-m-d H:i:s')), PDO::PARAM_STR);
        $stmt->bindValue(':tempo_leitura', (int) ($data['tempo_leitura'] ?? 5), PDO::PARAM_INT);
        $stmt->bindValue(':seo_title', (string) ($data['seo_title'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':seo_description', (string) ($data['seo_description'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':seo_keywords', (string) ($data['seo_keywords'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':tags', (string) ($data['tags'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':status', (string) ($data['status'] ?? 'rascunho'), PDO::PARAM_STR);
        $stmt->bindValue(':destaque', (int) ($data['destaque'] ?? 0), PDO::PARAM_INT);
        $stmt->execute();
    }

    private function buildAdminWhere(array $filters): array
    {
        $where = [];
        $params = [];
        $status = trim((string) ($filters['status'] ?? ''));
        $categoria = (int) ($filters['categoria'] ?? 0);
        $destaque = trim((string) ($filters['destaque'] ?? ''));
        $busca = trim((string) ($filters['busca'] ?? ''));
        if ($status !== '') { $where[] = 'p.status = :status'; $params[':status'] = $status; }
        if ($categoria > 0) { $where[] = 'p.categoria_post_id = :categoria'; $params[':categoria'] = $categoria; }
        if ($destaque !== '') { $where[] = 'COALESCE(p.destaque, 0) = :destaque'; $params[':destaque'] = $destaque === '1' ? 1 : 0; }
        if ($busca !== '') { $where[] = '(p.titulo LIKE :busca OR p.resumo LIKE :busca OR p.slug LIKE :busca)'; $params[':busca'] = '%' . $busca . '%'; }
        return [$where ? ('WHERE ' . implode(' AND ', $where)) : '', $params];
    }

    private function buildPublicWhere(array $filters): array
    {
        $where = ["p.status = 'publicado'"];
        $params = [];

        $search = trim((string) ($filters['busca'] ?? ''));
        if ($search !== '') {
            $where[] = '(p.titulo LIKE :busca_titulo OR p.resumo LIKE :busca_resumo OR p.conteudo LIKE :busca_conteudo)';
            $searchLike = '%' . $search . '%';
            $params[':busca_titulo'] = $searchLike;
            $params[':busca_resumo'] = $searchLike;
            $params[':busca_conteudo'] = $searchLike;
        }

        $category = trim((string) ($filters['categoria'] ?? ''));
        if ($category !== '' && $category !== 'all') {
            $where[] = 'c.slug = :categoria_slug';
            $params[':categoria_slug'] = $category;
        }

        $excludeId = (int) ($filters['exclude_id'] ?? 0);
        if ($excludeId > 0) {
            $where[] = 'p.id <> :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        return ['WHERE ' . implode(' AND ', $where), $params];
    }
}
