<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Repositories/ComentarioRepository.php
 * @project     Estrategia Nerd
 * @purpose     Repository de Comentarios (SQL)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ComentarioRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function countAll(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) AS total FROM comentarios');
        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function countByStatus(string $status): int
    {
        $sql = 'SELECT COUNT(*) AS total FROM comentarios WHERE status = :status';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['status' => $status]);

        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function countPending(): int
    {
        return $this->countByStatus('pendente');
    }

    public function countApproved(): int
    {
        return $this->countByStatus('aprovado');
    }

    public function countRejected(): int
    {
        return $this->countByStatus('reprovado');
    }

    public function countSpam(): int
    {
        return $this->countByStatus('spam');
    }

    public function countToday(): int
    {
        $sql = 'SELECT COUNT(*) AS total FROM comentarios WHERE DATE(data) = CURDATE()';
        $stmt = $this->pdo->query($sql);

        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function countsByStatus(): array
    {
        $sql = "SELECT SUM(CASE WHEN status = 'aprovado' THEN 1 ELSE 0 END) AS aprovados, SUM(CASE WHEN status = 'reprovado' THEN 1 ELSE 0 END) AS reprovados, SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) AS pendentes, SUM(CASE WHEN status = 'spam' THEN 1 ELSE 0 END) AS spam, COUNT(*) AS total FROM comentarios";
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'aprovados' => (int) ($row['aprovados'] ?? 0),
            'reprovados' => (int) ($row['reprovados'] ?? 0),
            'pendentes' => (int) ($row['pendentes'] ?? 0),
            'spam' => (int) ($row['spam'] ?? 0),
            'total' => (int) ($row['total'] ?? 0),
        ];
    }

    public function approvalBaseCounts(): array
    {
        $sql = "SELECT SUM(CASE WHEN status = 'aprovado' THEN 1 ELSE 0 END) AS aprovados, SUM(CASE WHEN status = 'reprovado' THEN 1 ELSE 0 END) AS reprovados FROM comentarios";
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'aprovados' => (int) ($row['aprovados'] ?? 0),
            'reprovados' => (int) ($row['reprovados'] ?? 0),
        ];
    }

    public function summaryFiltered(array $filters): array
    {
        [$whereSql, $params] = $this->buildAdminWhere($filters);
        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN c.status = 'pendente' THEN 1 ELSE 0 END) AS pendentes,
                    SUM(CASE WHEN c.status = 'aprovado' THEN 1 ELSE 0 END) AS aprovados,
                    SUM(CASE WHEN c.status = 'reprovado' THEN 1 ELSE 0 END) AS reprovados,
                    SUM(CASE WHEN c.status = 'spam' THEN 1 ELSE 0 END) AS spam,
                    SUM(CASE WHEN c.parent_id IS NULL THEN 1 ELSE 0 END) AS comentarios_raiz,
                    SUM(CASE WHEN c.parent_id IS NOT NULL THEN 1 ELSE 0 END) AS respostas,
                    SUM(
                        CASE
                            WHEN c.parent_id IS NULL
                             AND EXISTS (
                                SELECT 1
                                FROM comentarios cr
                                WHERE cr.parent_id = c.id
                                  AND (cr.admin_user_id IS NOT NULL OR LOWER(COALESCE(cr.email, '')) LIKE '%@admin.estrategia-nerd.local')
                            )
                            THEN 1 ELSE 0
                        END
                    ) AS threads_respondidas
                FROM comentarios c
                LEFT JOIN posts p ON p.id = c.post_id
                {$whereSql}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'pendentes' => (int) ($row['pendentes'] ?? 0),
            'aprovados' => (int) ($row['aprovados'] ?? 0),
            'reprovados' => (int) ($row['reprovados'] ?? 0),
            'spam' => (int) ($row['spam'] ?? 0),
            'comentarios_raiz' => (int) ($row['comentarios_raiz'] ?? 0),
            'respostas' => (int) ($row['respostas'] ?? 0),
            'threads_respondidas' => (int) ($row['threads_respondidas'] ?? 0),
            'respondidos' => (int) ($row['threads_respondidas'] ?? 0),
        ];
    }

    public function paginateAdmin(array $filters, int $page, int $perPage, string $sort, string $dir): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(50, $perPage));
        $sortMap = [
            'id' => 'c.id',
            'autor' => 'c.nome',
            'email' => 'c.email',
            'post' => "COALESCE(p.titulo, 'Post removido')",
            'status' => 'c.status',
            'respondido' => 'has_admin_reply',
            'data' => 'c.data',
        ];
        if (!isset($sortMap[$sort])) {
            $sort = 'data';
        }

        $direction = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';
        $orderBy = $sortMap[$sort] . ' ' . $direction;
        $orderBy .= match ($sort) {
            'autor', 'email', 'post' => ', c.id DESC',
            'status', 'respondido' => ', c.data DESC, c.id DESC',
            'id' => '',
            default => ', c.id DESC',
        };

        [$whereSql, $params] = $this->buildAdminWhere($filters);
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM comentarios c LEFT JOIN posts p ON p.id = c.post_id {$whereSql}");
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetchColumn() ?: 0);
        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT
                    c.id,
                    c.post_id,
                    c.nome,
                    c.email,
                    c.comentario,
                    c.status,
                    c.parent_id,
                    COALESCE(c.respondido, 0) AS respondido_legacy,
                    EXISTS (
                        SELECT 1
                        FROM comentarios cr
                        WHERE cr.parent_id = c.id
                          AND (cr.admin_user_id IS NOT NULL OR LOWER(COALESCE(cr.email, '')) LIKE '%@admin.estrategia-nerd.local')
                    ) AS has_admin_reply,
                    (
                        SELECT COUNT(*)
                        FROM comentarios cr_count
                        WHERE cr_count.parent_id = c.id
                          AND (cr_count.admin_user_id IS NOT NULL OR LOWER(COALESCE(cr_count.email, '')) LIKE '%@admin.estrategia-nerd.local')
                    ) AS admin_reply_count,
                    parent.nome AS parent_nome,
                    parent.status AS parent_status,
                    c.data,
                    COALESCE(p.titulo, 'Post removido') AS post_titulo,
                    p.slug AS post_slug
                FROM comentarios c
                LEFT JOIN posts p ON p.id = c.post_id
                LEFT JOIN comentarios parent ON parent.id = c.parent_id
                {$whereSql}
                ORDER BY {$orderBy}
                LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
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

    public function listPostsForFilter(): array
    {
        $sql = "SELECT
                    p.id,
                    p.titulo,
                    COUNT(c.id) AS total_comentarios
                FROM posts p
                INNER JOIN comentarios c ON c.post_id = p.id
                   AND COALESCE(c.admin_user_id, 0) = 0 AND LOWER(COALESCE(c.email, '')) NOT LIKE '%@admin.estrategia-nerd.local'
                GROUP BY p.id, p.titulo
                ORDER BY p.titulo ASC, p.id DESC";
        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'titulo' => (string) ($row['titulo'] ?? ''),
                'total_comentarios' => (int) ($row['total_comentarios'] ?? 0),
            ];
        }, $rows);
    }

    public function findAdminById(int $id): ?array
    {
        $sql = "SELECT c.id, c.post_id, c.nome, c.email, c.comentario, c.status, c.parent_id, EXISTS (SELECT 1 FROM comentarios cr WHERE cr.parent_id = c.id) AS has_reply, parent.nome AS parent_nome, parent.status AS parent_status, c.data, COALESCE(p.titulo, 'Post removido') AS post_titulo, p.slug AS post_slug FROM comentarios c LEFT JOIN posts p ON p.id = c.post_id LEFT JOIN comentarios parent ON parent.id = c.parent_id WHERE c.id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function findApprovedById(int $id): ?array
    {
        $sql = 'SELECT id, post_id, nome, email, comentario, status, parent_id, admin_user_id, data FROM comentarios WHERE id = :id AND status = :status LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':status', 'aprovado', PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }
    public function listDirectRepliesByParent(int $parentId): array
    {
        $sql = "SELECT
                    c.id,
                    c.post_id,
                    c.nome,
                    c.email,
                    c.comentario,
                    c.status,
                    c.parent_id,
                    c.admin_user_id,
                    c.data
                FROM comentarios c
                WHERE c.parent_id = :parent_id
                ORDER BY c.data ASC, c.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE comentarios SET status = :status WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function insertReply(array $data): int
    {
        $sql = 'INSERT INTO comentarios (post_id, nome, email, comentario, status, parent_id, admin_user_id, respondido) VALUES (:post_id, :nome, :email, :comentario, :status, :parent_id, :admin_user_id, 0)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':post_id', (int) ($data['post_id'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':nome', (string) ($data['nome'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':email', (string) ($data['email'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':comentario', (string) ($data['comentario'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':status', (string) ($data['status'] ?? 'aprovado'), PDO::PARAM_STR);
        $stmt->bindValue(':parent_id', (int) ($data['parent_id'] ?? 0), PDO::PARAM_INT);
        if ((int) ($data['admin_user_id'] ?? 0) > 0) {
            $stmt->bindValue(':admin_user_id', (int) ($data['admin_user_id'] ?? 0), PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':admin_user_id', null, PDO::PARAM_NULL);
        }
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    public function listApprovedByPost(int $postId): array
    {
        $sql = "SELECT
                    c.id,
                    c.post_id,
                    c.nome,
                    c.email,
                    c.comentario,
                    c.status,
                    c.parent_id,
                    c.admin_user_id,
                    c.data,
                    u.nome AS admin_nome,
                    u.usuario AS admin_usuario,
                    u.avatar_tipo AS admin_avatar_tipo,
                    u.avatar_icone AS admin_avatar_icone,
                    u.avatar_cor AS admin_avatar_cor,
                    u.avatar_imagem AS admin_avatar_imagem,
                    u.avatar_focal_x AS admin_avatar_focal_x,
                    u.avatar_focal_y AS admin_avatar_focal_y
                FROM comentarios c
                LEFT JOIN usuarios u ON u.id = c.admin_user_id
                WHERE c.post_id = :post_id
                  AND c.status = 'aprovado'
                ORDER BY c.parent_id IS NOT NULL, COALESCE(c.parent_id, c.id), c.data ASC, c.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':post_id', $postId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function insertPublic(array $data): int
    {
        $sql = 'INSERT INTO comentarios (post_id, nome, email, comentario, status, parent_id, admin_user_id, respondido) VALUES (:post_id, :nome, :email, :comentario, :status, :parent_id, :admin_user_id, 0)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':post_id', (int) ($data['post_id'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':nome', (string) ($data['nome'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':email', (string) ($data['email'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':comentario', (string) ($data['comentario'] ?? ''), PDO::PARAM_STR);
        $stmt->bindValue(':status', (string) ($data['status'] ?? 'pendente'), PDO::PARAM_STR);
        $parentId = (int) ($data['parent_id'] ?? 0);
        if ($parentId > 0) {
            $stmt->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':parent_id', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':admin_user_id', null, PDO::PARAM_NULL);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    public function deleteThreadById(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM comentarios WHERE id = :id OR parent_id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function buildAdminWhere(array $filters): array
    {
        $where = ["COALESCE(c.admin_user_id, 0) = 0", "LOWER(COALESCE(c.email, '')) NOT LIKE '%@admin.estrategia-nerd.local'"];
        $params = [];
        $busca = trim((string) ($filters['busca'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $respondido = trim((string) ($filters['respondido'] ?? ''));
        $postId = (int) ($filters['post'] ?? 0);

        if ($busca !== '') {
            $where[] = '(c.nome LIKE :busca_nome OR c.email LIKE :busca_email OR c.comentario LIKE :busca_comentario OR COALESCE(p.titulo, \'\') LIKE :busca_post)';
            $params[':busca_nome'] = '%' . $busca . '%';
            $params[':busca_email'] = '%' . $busca . '%';
            $params[':busca_comentario'] = '%' . $busca . '%';
            $params[':busca_post'] = '%' . $busca . '%';
        }

        if (in_array($status, ['pendente', 'aprovado', 'reprovado', 'spam'], true)) {
            $where[] = 'c.status = :status';
            $params[':status'] = $status;
        }

        if ($respondido === '1') {
            $where[] = "EXISTS (SELECT 1 FROM comentarios cr WHERE cr.parent_id = c.id AND (cr.admin_user_id IS NOT NULL OR LOWER(COALESCE(cr.email, '')) LIKE '%@admin.estrategia-nerd.local'))";
        } elseif ($respondido === '0') {
            $where[] = "NOT EXISTS (SELECT 1 FROM comentarios cr WHERE cr.parent_id = c.id AND (cr.admin_user_id IS NOT NULL OR LOWER(COALESCE(cr.email, '')) LIKE '%@admin.estrategia-nerd.local'))";
        }

        if ($postId > 0) {
            $where[] = 'c.post_id = :post_id';
            $params[':post_id'] = $postId;
        }

        return ['WHERE ' . implode(' AND ', $where), $params];
    }
}
