<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class NewsletterRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function countAll(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) AS total FROM newsletter');
        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function countByStatus(string $status): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS total FROM newsletter WHERE status = :status');
        $stmt->execute(['status' => $status]);

        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function countActive(): int
    {
        return $this->countByStatus('ativo');
    }

    public function countInactive(): int
    {
        return $this->countByStatus('inativo');
    }

    public function countUnsubscribed(): int
    {
        return $this->countByStatus('desinscreve');
    }

    public function countToday(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) AS total FROM newsletter WHERE DATE(data_cadastro) = CURDATE()');
        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function countLastDays(int $days): int
    {
        $days = max(1, min(365, $days));

        $sql = '
            SELECT COUNT(*) AS total
            FROM newsletter
            WHERE DATE(data_cadastro) >= DATE_SUB(CURDATE(), INTERVAL :days - 1 DAY)
              AND DATE(data_cadastro) <= CURDATE()
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function countActiveLastDays(int $days): int
    {
        $days = max(1, min(365, $days));

        $sql = '
            SELECT COUNT(*) AS total
            FROM newsletter
            WHERE status = :status
              AND DATE(data_cadastro) >= DATE_SUB(CURDATE(), INTERVAL :days - 1 DAY)
              AND DATE(data_cadastro) <= CURDATE()
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('status', 'ativo');
        $stmt->bindValue('days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    public function listAdmin(array $filters = [], string $sort = 'data_cadastro', string $dir = 'desc'): array
    {
        [$where, $params] = $this->buildAdminWhere($filters);
        $orderBy = $this->buildAdminOrderBy($sort, $dir);

        $sql = '
            SELECT id, email, nome, data_cadastro, status, ip
            FROM newsletter
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

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) AS total FROM newsletter ' . $where);
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sql = '
            SELECT id, email, nome, data_cadastro, status, ip
            FROM newsletter
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
            SELECT id, email, nome, data_cadastro, status, ip
            FROM newsletter
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);

        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($item) ? $item : null;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE newsletter SET status = :status WHERE id = :id LIMIT 1');
        $stmt->execute(['status' => $status, 'id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function deleteById(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM newsletter WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    private function buildAdminWhere(array $filters): array
    {
        $clauses = [];
        $params = [];

        $busca = trim((string) ($filters['busca'] ?? ''));
        if ($busca !== '') {
            $clauses[] = '(email LIKE :busca_email OR nome LIKE :busca_nome OR ip LIKE :busca_ip)';
            $like = '%' . $busca . '%';
            $params['busca_email'] = $like;
            $params['busca_nome'] = $like;
            $params['busca_ip'] = $like;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['ativo', 'inativo', 'desinscreve'], true)) {
            $clauses[] = 'status = :status';
            $params['status'] = $status;
        }

        if ($clauses === []) {
            return ['', $params];
        }

        return ['WHERE ' . implode(' AND ', $clauses), $params];
    }

    private function buildAdminOrderBy(string $sort, string $dir): string
    {
        $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';
        $map = [
            'nome' => "COALESCE(NULLIF(nome, ''), email)",
            'email' => 'email',
            'status' => 'status',
            'data_cadastro' => 'data_cadastro',
            'ip' => 'ip',
        ];

        $column = $map[$sort] ?? 'data_cadastro';
        return $column . ' ' . $dir . ', id DESC';
    }
}