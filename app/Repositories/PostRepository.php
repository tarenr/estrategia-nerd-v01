<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Repositories/PostRepository.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Repository de Posts (SQL)
 * @description Centraliza queries SQL relacionadas a posts para o Dashboard/Admin.
 * @usage       Injetado em Services (ex.: DashboardService/PostsService) para leitura de dados.
 * @notes       Somente SQL (sem regra de negócio). Retorna arrays simples.
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
        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    /**
     * @return array{publicados:int, rascunhos:int, agendados:int}
     */
    public function countByStatus(): array
    {
        $sql = "
            SELECT
                SUM(CASE WHEN status = 'publicado' THEN 1 ELSE 0 END) AS publicados,
                SUM(CASE WHEN status = 'rascunho' THEN 1 ELSE 0 END) AS rascunhos,
                SUM(CASE WHEN status = 'agendado' THEN 1 ELSE 0 END) AS agendados
            FROM posts
        ";
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'publicados' => (int)($row['publicados'] ?? 0),
            'rascunhos' => (int)($row['rascunhos'] ?? 0),
            'agendados' => (int)($row['agendados'] ?? 0),
        ];
    }

    public function sumViews(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(SUM(views), 0) AS total FROM posts');
        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function sumLikes(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(SUM(curtidas), 0) AS total FROM posts');
        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function sumComentariosCount(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(SUM(comentarios_count), 0) AS total FROM posts');
        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function countToday(): int
    {
        $sql = "SELECT COUNT(*) AS total FROM posts WHERE DATE(data_publicacao) = CURDATE()";
        $stmt = $this->pdo->query($sql);
        return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    /**
     * Retorna os posts mais recentes (para lista do Dashboard).
     *
     * Campos retornados:
     * - post: id, titulo, slug, imagem_capa, status, data_publicacao, views, curtidas, comentarios_count
     * - categoria: categoria_nome, categoria_cor
     *
     * @return array<int, array<string,mixed>>
     */
    public function latestWithCategoria(int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));

        $sql = "
            SELECT
                p.id,
                p.titulo,
                p.slug,
                p.imagem_capa,
                p.status,
                p.data_publicacao,
                p.views,
                p.curtidas,
                p.comentarios_count,
                c.nome AS categoria_nome,
                c.cor  AS categoria_cor
            FROM posts p
            LEFT JOIN categoria_post c ON c.id = p.categoria_post_id
            ORDER BY p.data_publicacao DESC, p.id DESC
            LIMIT :limit
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * -----------------------------------------------------------------------------
     * @purpose     Resumo (cards) com filtros do Admin
     * @description Retorna contagens/somas aplicando filtros (status/categoria/destaque/busca).
     * @notes       Apenas SQL + montagem de array simples.
     * -----------------------------------------------------------------------------
     *
     * @param array{status:string,categoria:int,destaque:string,busca:string} $filters
     * @return array{
     *   total_posts:int, publicados:int, rascunhos:int, agendados:int,
     *   destaques:int, total_views:int, total_curtidas:int, total_comentarios:int
     * }
     */
    public function summaryFiltered(array $filters): array
    {
        [$whereSql, $params] = $this->buildAdminWhere($filters);

        $sql = "
            SELECT
                COUNT(*) AS total_posts,
                SUM(CASE WHEN p.status = 'publicado' THEN 1 ELSE 0 END) AS publicados,
                SUM(CASE WHEN p.status = 'rascunho' THEN 1 ELSE 0 END) AS rascunhos,
                SUM(CASE WHEN p.status = 'agendado' THEN 1 ELSE 0 END) AS agendados,
                SUM(CASE WHEN COALESCE(p.destaque, 0) = 1 THEN 1 ELSE 0 END) AS destaques,
                COALESCE(SUM(p.views), 0) AS total_views,
                COALESCE(SUM(p.curtidas), 0) AS total_curtidas,
                COALESCE(SUM(p.comentarios_count), 0) AS total_comentarios
            FROM posts p
            {$whereSql}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_posts' => (int)($row['total_posts'] ?? 0),
            'publicados' => (int)($row['publicados'] ?? 0),
            'rascunhos' => (int)($row['rascunhos'] ?? 0),
            'agendados' => (int)($row['agendados'] ?? 0),
            'destaques' => (int)($row['destaques'] ?? 0),
            'total_views' => (int)($row['total_views'] ?? 0),
            'total_curtidas' => (int)($row['total_curtidas'] ?? 0),
            'total_comentarios' => (int)($row['total_comentarios'] ?? 0),
        ];
    }

    /**
     * -----------------------------------------------------------------------------
     * @purpose     Paginação/listagem do Admin com filtros + ordenação segura
     * @description Lista posts com categoria para a central do admin.
     * @notes       Ordenação usa mapa seguro (evita SQL injection em ORDER BY).
     * -----------------------------------------------------------------------------
     *
     * @param array{status:string,categoria:int,destaque:string,busca:string} $filters
     * @return array{
     *   items:array<int,array<string,mixed>>,
     *   total:int,
     *   page:int,
     *   per_page:int,
     *   pages:int
     * }
     */
    public function paginateAdmin(
        array $filters,
        int $page,
        int $perPage,
        string $sort,
        string $dir
    ): array {
        $page = max(1, $page);
        $perPage = max(5, min(50, $perPage));

        $sortMap = [
            'id' => 'p.id',
            'titulo' => 'p.titulo',
            'categoria' => "COALESCE(cp.nome, 'Sem categoria')",
            'status' => 'p.status',
            'data' => 'p.data_publicacao',
            'views' => 'p.views',
            'curtidas' => 'p.curtidas',
            'comentarios' => 'p.comentarios_count',
        ];

        if (!isset($sortMap[$sort])) $sort = 'data';
        $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

        $orderBy = $sortMap[$sort] . " {$dir}";
        $orderBy .= match ($sort) {
            'titulo' => ', p.id DESC',
            'categoria' => ', p.titulo ASC, p.id DESC',
            'status' => ', p.data_publicacao DESC, p.id DESC',
            'views', 'curtidas', 'comentarios' => ', p.data_publicacao DESC, p.id DESC',
            'id' => '',
            default => ', p.id DESC',
        };

        [$whereSql, $params] = $this->buildAdminWhere($filters);

        $countSql = "SELECT COUNT(*) FROM posts p {$whereSql}";
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int)($stmt->fetchColumn() ?: 0);

        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) $page = $pages;

        $offset = ($page - 1) * $perPage;

        $sql = "
            SELECT
                p.id,
                p.titulo,
                p.slug,
                p.imagem_capa,
                p.status,
                p.data_publicacao,
                COALESCE(p.views, 0) AS views,
                COALESCE(p.curtidas, 0) AS curtidas,
                COALESCE(p.comentarios_count, 0) AS comentarios_count,
                COALESCE(p.destaque, 0) AS destaque,
                p.categoria_post_id,
                COALESCE(cp.nome, 'Sem categoria') AS categoria_nome,
                cp.cor AS categoria_cor
            FROM posts p
            LEFT JOIN categoria_post cp ON cp.id = p.categoria_post_id
            {$whereSql}
            ORDER BY {$orderBy}
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    /**
     * Top post por views.
     * @return array{titulo:string, views:int}|null
     */
    public function topByViews(): ?array
    {
        $sql = "SELECT titulo, views FROM posts ORDER BY views DESC, id DESC LIMIT 1";
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        return [
            'titulo' => (string)($row['titulo'] ?? ''),
            'views' => (int)($row['views'] ?? 0),
        ];
    }

    /**
     * Top post por curtidas.
     * @return array{titulo:string, curtidas:int}|null
     */
    public function topByLikes(): ?array
    {
        $sql = "SELECT titulo, curtidas FROM posts ORDER BY curtidas DESC, id DESC LIMIT 1";
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        return [
            'titulo' => (string)($row['titulo'] ?? ''),
            'curtidas' => (int)($row['curtidas'] ?? 0),
        ];
    }

    /**
     * Top post por comentarios_count.
     * @return array{titulo:string, comentarios_count:int}|null
     */
    public function topByComments(): ?array
    {
        $sql = "SELECT titulo, comentarios_count FROM posts ORDER BY comentarios_count DESC, id DESC LIMIT 1";
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        return [
            'titulo' => (string)($row['titulo'] ?? ''),
            'comentarios_count' => (int)($row['comentarios_count'] ?? 0),
        ];
    }

    /**
     * -----------------------------------------------------------------------------
     * @purpose     Builder de WHERE para filtros do Admin
     * @description Gera WHERE + params para queries filtradas da Central de Posts.
     * -----------------------------------------------------------------------------
     *
     * @param array{status:string,categoria:int,destaque:string,busca:string} $filters
     * @return array{0:string,1:array<string,mixed>} whereSql, params
     */
    private function buildAdminWhere(array $filters): array
    {
        $where = [];
        $params = [];

        $status = trim((string)($filters['status'] ?? ''));
        $categoria = (int)($filters['categoria'] ?? 0);
        $destaque = trim((string)($filters['destaque'] ?? ''));
        $busca = trim((string)($filters['busca'] ?? ''));

        if ($status !== '') {
            $where[] = 'p.status = :status';
            $params[':status'] = $status;
        }

        if ($categoria > 0) {
            $where[] = 'p.categoria_post_id = :categoria';
            $params[':categoria'] = $categoria;
        }

        if ($destaque !== '') {
            $where[] = 'COALESCE(p.destaque, 0) = :destaque';
            $params[':destaque'] = $destaque === '1' ? 1 : 0;
        }

        if ($busca !== '') {
            $where[] = '(p.titulo LIKE :busca OR p.resumo LIKE :busca OR p.slug LIKE :busca)';
            $params[':busca'] = '%' . $busca . '%';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        return [$whereSql, $params];
    }
}