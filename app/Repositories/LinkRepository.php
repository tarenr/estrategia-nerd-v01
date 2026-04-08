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
            SELECT id, titulo, slug, url, tipo, promocao, desconto_percentual, desconto_contexto, codigo_cupom, secao_publica, subgrupo_publico,
                   descricao, cta_curto, texto_botao, selo, imagem, posicao, status, destaque,
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
            SELECT id, titulo, slug, url, tipo, promocao, desconto_percentual, desconto_contexto, codigo_cupom, secao_publica, subgrupo_publico,
                   descricao, cta_curto, texto_botao, selo, imagem, posicao, status, destaque,
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
            SELECT id, titulo, slug, url, tipo, promocao, desconto_percentual, desconto_contexto, codigo_cupom, secao_publica, subgrupo_publico,
                   descricao, cta_curto, texto_botao, selo, imagem, posicao, status, destaque,
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

    public function findPublicBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT id, titulo, slug, url, tipo, promocao, secao_publica, subgrupo_publico,
                   descricao, cta_curto, texto_botao, selo, imagem, posicao, status, destaque, expira_em
            FROM links
            WHERE slug = :slug
              AND status NOT IN ("oculto", "expirado")
              AND (expira_em IS NULL OR expira_em >= NOW())
            LIMIT 1
        ');
        $stmt->execute(['slug' => trim($slug)]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($item) ? $item : null;
    }

    public function insertAdmin(array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO links (
                titulo, slug, url, tipo, promocao, desconto_percentual, desconto_contexto, codigo_cupom, secao_publica, subgrupo_publico,
                descricao, cta_curto, texto_botao, selo, imagem, posicao, status, destaque, expira_em, observacao_status
            )
            VALUES (
                :titulo, :slug, :url, :tipo, :promocao, :desconto_percentual, :desconto_contexto, :codigo_cupom, :secao_publica, :subgrupo_publico,
                :descricao, :cta_curto, :texto_botao, :selo, :imagem, :posicao, :status, :destaque, :expira_em, :observacao_status
            )
        ');
        $stmt->execute([
            'titulo' => $data['titulo'],
            'slug' => $data['slug'],
            'url' => $data['url'],
            'tipo' => $data['tipo'],
            'promocao' => (int) ($data['promocao'] ?? 0),
            'desconto_percentual' => $this->nullableString($data['desconto_percentual'] ?? null),
            'desconto_contexto' => $this->nullableString($data['desconto_contexto'] ?? null),
            'codigo_cupom' => $this->nullableString($data['codigo_cupom'] ?? null),
            'secao_publica' => $data['secao_publica'],
            'subgrupo_publico' => $this->nullableString($data['subgrupo_publico'] ?? null),
            'descricao' => $this->nullableString($data['descricao'] ?? null),
            'cta_curto' => $this->nullableString($data['cta_curto'] ?? null),
            'texto_botao' => $this->nullableString($data['texto_botao'] ?? null),
            'selo' => $this->nullableString($data['selo'] ?? null),
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
                promocao = :promocao,
                desconto_percentual = :desconto_percentual,
                desconto_contexto = :desconto_contexto,
                codigo_cupom = :codigo_cupom,
                secao_publica = :secao_publica,
                subgrupo_publico = :subgrupo_publico,
                descricao = :descricao,
                cta_curto = :cta_curto,
                texto_botao = :texto_botao,
                selo = :selo,
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
            'promocao' => (int) ($data['promocao'] ?? 0),
            'desconto_percentual' => $this->nullableString($data['desconto_percentual'] ?? null),
            'desconto_contexto' => $this->nullableString($data['desconto_contexto'] ?? null),
            'codigo_cupom' => $this->nullableString($data['codigo_cupom'] ?? null),
            'secao_publica' => $data['secao_publica'],
            'subgrupo_publico' => $this->nullableString($data['subgrupo_publico'] ?? null),
            'descricao' => $this->nullableString($data['descricao'] ?? null),
            'cta_curto' => $this->nullableString($data['cta_curto'] ?? null),
            'texto_botao' => $this->nullableString($data['texto_botao'] ?? null),
            'selo' => $this->nullableString($data['selo'] ?? null),
            'imagem' => $this->nullableString($data['imagem'] ?? null),
            'posicao' => (int) ($data['posicao'] ?? 0),
            'status' => $data['status'],
            'destaque' => (int) ($data['destaque'] ?? 0),
            'expira_em' => $this->nullableString($data['expira_em'] ?? null),
            'observacao_status' => $this->nullableString($data['observacao_status'] ?? null),
        ]);

        return $stmt->rowCount() > 0;
    }

    public function updateQuickFields(int $id, array $data): bool
    {
        $sets = [];
        $params = ['id' => $id];

        foreach (['status', 'destaque'] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $sets[] = $field . ' = :' . $field;
            $params[$field] = $data[$field];
        }

        if ($sets === []) {
            return false;
        }

        $sql = 'UPDATE links SET ' . implode(', ', $sets) . ' WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function updatePositionById(int $id, int $position): bool
    {
        $stmt = $this->pdo->prepare('UPDATE links SET posicao = :posicao WHERE id = :id LIMIT 1');
        $stmt->execute([
            'id' => $id,
            'posicao' => $position,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function updateMonitoringById(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE links
            SET status = :status,
                ultima_verificacao = :ultima_verificacao,
                codigo_http = :codigo_http,
                url_final = :url_final,
                observacao_status = :observacao_status
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute([
            'id' => $id,
            'status' => $data['status'],
            'ultima_verificacao' => $data['ultima_verificacao'],
            'codigo_http' => $data['codigo_http'],
            'url_final' => $this->nullableString($data['url_final'] ?? null),
            'observacao_status' => $this->nullableString($data['observacao_status'] ?? null),
        ]);

        return $stmt->rowCount() > 0;
    }

    public function reorderPositions(array $orderedIds): void
    {
        $position = 10;
        foreach ($orderedIds as $id) {
            $this->updatePositionById((int) $id, $position);
            $position += 10;
        }
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

    public function countPublicActive(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) AS total FROM links WHERE status NOT IN ("oculto", "expirado") AND (expira_em IS NULL OR expira_em >= NOW())');
        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function countReview(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) AS total FROM links WHERE status = "quebrado"');
        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function listForHome(int $limit = 6): array
    {
        $limit = max(1, min(12, $limit));
        $stmt = $this->pdo->prepare('
            SELECT id, titulo, slug, url, tipo, promocao, desconto_percentual, desconto_contexto, codigo_cupom, secao_publica, subgrupo_publico,
                   descricao, cta_curto, texto_botao, selo, imagem, posicao, status, destaque
            FROM links
            WHERE status NOT IN ("oculto", "expirado")
              AND (expira_em IS NULL OR expira_em >= NOW())
            ORDER BY promocao DESC, destaque DESC, posicao ASC, id DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listForCentral(int $limit = 60): array
    {
        $limit = max(1, min(120, $limit));
        $stmt = $this->pdo->prepare('
            SELECT id, titulo, slug, url, tipo, promocao, desconto_percentual, desconto_contexto, codigo_cupom, secao_publica, subgrupo_publico,
                   descricao, cta_curto, texto_botao, selo, imagem, posicao, status, destaque
            FROM links
            WHERE status NOT IN ("oculto", "expirado")
              AND (expira_em IS NULL OR expira_em >= NOW())
            ORDER BY promocao DESC, destaque DESC, posicao ASC, id DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
            $clauses[] = '(titulo LIKE :busca_titulo OR slug LIKE :busca_slug OR url LIKE :busca_url OR descricao LIKE :busca_descricao OR subgrupo_publico LIKE :busca_grupo OR codigo_cupom LIKE :busca_cupom)';
            $like = '%' . $busca . '%';
            $params['busca_titulo'] = $like;
            $params['busca_slug'] = $like;
            $params['busca_url'] = $like;
            $params['busca_descricao'] = $like;
            $params['busca_grupo'] = $like;
            $params['busca_cupom'] = $like;
        }

        $tipo = trim((string) ($filters['tipo'] ?? ''));
        if ($tipo !== '' && in_array($tipo, ['produto', 'cupom', 'conteudo', 'rede_social', 'servico'], true)) {
            $clauses[] = 'tipo = :tipo';
            $params['tipo'] = $tipo;
        }

        $promocao = trim((string) ($filters['promocao'] ?? ''));
        if ($promocao === '1') {
            $clauses[] = 'promocao = 1';
        } elseif ($promocao === '0') {
            $clauses[] = 'promocao = 0';
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

        $monitoramento = trim((string) ($filters['monitoramento'] ?? ''));
        if ($monitoramento === 'expirando') {
            $clauses[] = 'expira_em IS NOT NULL AND expira_em >= NOW() AND expira_em <= DATE_ADD(NOW(), INTERVAL 7 DAY)';
        } elseif ($monitoramento === 'quebrados') {
            $clauses[] = 'status = "quebrado"';
        } elseif ($monitoramento === 'sem_verificacao') {
            $clauses[] = 'ultima_verificacao IS NULL';
        } elseif ($monitoramento === 'verificados') {
            $clauses[] = 'ultima_verificacao IS NOT NULL';
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
        return $column . ' ' . $dir . ', promocao DESC, destaque DESC, id DESC';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}