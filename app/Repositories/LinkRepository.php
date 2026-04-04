<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class LinkRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listAdmin(array $filters = [], string $sort = 'posicao', string $dir = 'asc'): array
    {
        [$where, $params] = $this->buildAdminWhere($filters);
        $orderBy = $this->buildAdminOrderBy($sort, $dir);

        $sql = '
            SELECT id, titulo, slug, url, tipo, descricao, imagem, posicao, status, destaque,
                   expira_em, ultima_verificacao, codigo_http, url_final, observacao_status,
                   created_at, updated_at
            FROM links
            ' . $where . '
            ORDER BY ' . $orderBy . '
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function paginateAdmin(array $filters, int $page, int $perPage, string $sort, string $dir): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        [$where, $params] = $this->buildAdminWhere($filters);
        $orderBy = $this->buildAdminOrderBy($sort, $dir);

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) AS total FROM links ' . $where);
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sql = '
            SELECT id, titulo, slug, url, tipo, descricao, imagem, posicao, status, destaque,
                   expira_em, ultima_verificacao, codigo_http, url_final, observacao_status,
                   created_at, updated_at
            FROM links
            ' . $where . '
            ORDER BY ' . $orderBy . '
            LIMIT :limit OFFSET :offset
        ';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT id, titulo, slug, url, tipo, descricao, imagem, posicao, status, destaque,
                   expira_em, ultima_verificacao, codigo_http, url_final, observacao_status,
                   created_at, updated_at
            FROM links
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($item) ? $item : null;
    }

    public function insertAdmin(array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO links (titulo, slug, url, tipo, descricao, imagem, posicao, status, destaque, expira_em, observacao_status)
            VALUES (:titulo, :slug, :url, :tipo, :descricao, :imagem, :posicao, :status, :destaque, :expira_em, :observacao_status)
        ');
        $stmt->execute([
            'titulo' => $data['titulo'],
            'slug' => $data['slug'],
            'url' => $data['url'],
            'tipo' => $data['tipo'],
            'descricao' => $this->nullableString($data['descricao'] ?? null),
            'imagem' => $this->nullableString($data['imagem'] ?? null),
            'posicao' => (int) ($data['posicao'] ?? 0),
            'status' => $data['status'],
            'destaque' => (int) ($data['destaque'] ?? 0),
            'expira_em' => $this->nullableString($data['expira_em'] ?? null),
            'observacao_status' => $this->nullableString($data['observacao_status'] ?? null),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateAdmin(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE links
            SET titulo = :titulo,
                slug = :slug,
                url = :url,
                tipo = :tipo,
                descricao = :descricao,
                imagem = :imagem,
                posicao = :posicao,
                status = :status,
                destaque = :destaque,
                expira_em = :expira_em,
                observacao_status = :observacao_status
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute([
            'id' => $id,
            'titulo' => $data['titulo'],
            'slug' => $data['slug'],
            'url' => $data['url'],
            'tipo' => $data['tipo'],
            'descricao' => $this->nullableString($data['descricao'] ?? null),
            'imagem' => $this->nullableString($data['imagem'] ?? null),
            'posicao' => (int) ($data['posicao'] ?? 0),
            'status' => $data['status'],
            'destaque' => (int) ($data['destaque'] ?? 0),
            'expira_em' => $this->nullableString($data['expira_em'] ?? null),
            'observacao_status' => $this->nullableString($data['observacao_status'] ?? null),
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deleteById(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM links WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM links WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($ignoreId !== null && $ignoreId > 0) {
            $sql .= ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function nextAvailableSlug(string $baseSlug, ?int $ignoreId = null): string
    {
        $baseSlug = trim($baseSlug);
        if ($baseSlug === '') {
            $baseSlug = 'link';
        }

        $candidate = $baseSlug;
        $suffix = 2;
        while ($this->slugExists($candidate, $ignoreId)) {
            $candidate = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function buildAdminWhere(array $filters): array
    {
        $clauses = [];
        $params = [];

        $busca = trim((string) ($filters['busca'] ?? ''));
        if ($busca !== '') {
            $clauses[] = '(titulo LIKE :busca_titulo OR slug LIKE :busca_slug OR url LIKE :busca_url OR descricao LIKE :busca_descricao)';
            $like = '%' . $busca . '%';
            $params['busca_titulo'] = $like;
            $params['busca_slug'] = $like;
            $params['busca_url'] = $like;
            $params['busca_descricao'] = $like;
        }

        $tipo = trim((string) ($filters['tipo'] ?? ''));
        if ($tipo !== '' && in_array($tipo, ['afiliado', 'oferta', 'conteudo', 'rede_social', 'servico'], true)) {
            $clauses[] = 'tipo = :tipo';
            $params['tipo'] = $tipo;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['ativo', 'oculto', 'expirado', 'quebrado'], true)) {
            $clauses[] = 'status = :status';
            $params['status'] = $status;
        }

        $destaque = trim((string) ($filters['destaque'] ?? ''));
        if ($destaque === '1') {
            $clauses[] = 'destaque = 1';
        } elseif ($destaque === '0') {
            $clauses[] = 'destaque = 0';
        }

        if ($clauses === []) {
            return ['', $params];
        }

        return ['WHERE ' . implode(' AND ', $clauses), $params];
    }

    private function buildAdminOrderBy(string $sort, string $dir): string
    {
        $dir = strtolower($dir) === 'desc' ? 'DESC' : 'ASC';
        $map = [
            'titulo' => 'titulo',
            'tipo' => 'tipo',
            'status' => 'status',
            'posicao' => 'posicao',
            'expira_em' => 'expira_em',
            'updated_at' => 'updated_at',
        ];

        $column = $map[$sort] ?? 'posicao';
        return $column . ' ' . $dir . ', id DESC';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
