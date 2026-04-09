<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Repositories/UsuarioRepository.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.1.1
 * @purpose     Acesso aos dados de usuarios (tabela usuarios)
 * @description Executa queries relacionadas ao modulo de usuarios do admin e ao login.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UsuarioRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . $this->adminSelect() . ' FROM usuarios WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByUsuario(string $usuario): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . $this->adminSelect() . ', senha FROM usuarios WHERE usuario = :usuario LIMIT 1'
        );
        $stmt->execute(['usuario' => $usuario]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listAdmin(array $filters = []): array
    {
        $params = [];
        $where = $this->buildAdminWhere($filters, $params);
        $sql = 'SELECT ' . $this->adminSelect() . ' FROM usuarios' . $where . ' ORDER BY criado_em DESC, id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function paginateAdmin(array $filters, int $page, int $perPage, string $sort, string $dir): array
    {
        $params = [];
        $where = $this->buildAdminWhere($filters, $params);

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM usuarios' . $where);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $pages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT ' . $this->adminSelect() . ' FROM usuarios' . $where . ' ORDER BY ' . $this->adminOrderBy($sort, $dir) . ' LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
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

    public function existsUsuario(string $usuario, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM usuarios WHERE usuario = :usuario';
        $params = ['usuario' => $usuario];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function existsEmail(string $email, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM usuarios WHERE email = :email';
        $params = ['email' => $email];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public function insertAdmin(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO usuarios (usuario, nome, email, papel, status, avatar_tipo, avatar_icone, avatar_cor, avatar_imagem, avatar_focal_x, avatar_focal_y, senha)
             VALUES (:usuario, :nome, :email, :papel, :status, :avatar_tipo, :avatar_icone, :avatar_cor, :avatar_imagem, :avatar_focal_x, :avatar_focal_y, :senha)'
        );
        $stmt->execute([
            'usuario' => $data['usuario'],
            'nome' => $data['nome'],
            'email' => $data['email'],
            'papel' => $data['papel'],
            'status' => $data['status'],
            'avatar_tipo' => $data['avatar_tipo'],
            'avatar_icone' => $data['avatar_icone'],
            'avatar_cor' => $data['avatar_cor'],
            'avatar_imagem' => $data['avatar_imagem'],
            'avatar_focal_x' => $data['avatar_focal_x'],
            'avatar_focal_y' => $data['avatar_focal_y'],
            'senha' => $data['senha'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateAdmin(int $id, array $data): void
    {
        $sql = 'UPDATE usuarios SET
            usuario = :usuario,
            nome = :nome,
            email = :email,
            papel = :papel,
            status = :status,
            avatar_tipo = :avatar_tipo,
            avatar_icone = :avatar_icone,
            avatar_cor = :avatar_cor,
            avatar_imagem = :avatar_imagem,
            avatar_focal_x = :avatar_focal_x,
            avatar_focal_y = :avatar_focal_y';

        $params = [
            'id' => $id,
            'usuario' => $data['usuario'],
            'nome' => $data['nome'],
            'email' => $data['email'],
            'papel' => $data['papel'],
            'status' => $data['status'],
            'avatar_tipo' => $data['avatar_tipo'],
            'avatar_icone' => $data['avatar_icone'],
            'avatar_cor' => $data['avatar_cor'],
            'avatar_imagem' => $data['avatar_imagem'],
            'avatar_focal_x' => $data['avatar_focal_x'],
            'avatar_focal_y' => $data['avatar_focal_y'],
        ];

        if (!empty($data['senha'])) {
            $sql .= ', senha = :senha';
            $params['senha'] = $data['senha'];
        }

        $sql .= ' WHERE id = :id LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE usuarios SET status = :status WHERE id = :id LIMIT 1');
        $stmt->execute([
            'id' => $id,
            'status' => $status,
        ]);
    }

    public function deleteById(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
    }

    public function countActiveAdmins(?int $ignoreId = null): int
    {
        $sql = "SELECT COUNT(*) FROM usuarios WHERE papel = 'admin' AND status = 'ativo'";
        $params = [];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function findFirstActiveAdmin(?int $ignoreId = null): ?array
    {
        $sql = 'SELECT ' . $this->adminSelect() . " FROM usuarios WHERE papel = 'admin' AND status = 'ativo'";
        $params = [];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        $sql .= ' ORDER BY id ASC LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function countPostsByAuthor(int $id): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM posts WHERE autor_id = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    public function reassignPostsByAuthor(int $fromId, int $toId): void
    {
        $stmt = $this->pdo->prepare('UPDATE posts SET autor_id = :to_id WHERE autor_id = :from_id');
        $stmt->execute([
            'from_id' => $fromId,
            'to_id' => $toId,
        ]);
    }

    public function touchLastAccess(int $id, ?string $datetime = null): void
    {
        $stmt = $this->pdo->prepare('UPDATE usuarios SET ultimo_acesso = :ultimo_acesso WHERE id = :id LIMIT 1');
        $stmt->execute([
            'id' => $id,
            'ultimo_acesso' => $datetime ?? date('Y-m-d H:i:s'),
        ]);
    }

    private function adminSelect(): string
    {
        return implode(', ', [
            'id',
            'usuario',
            'nome',
            'email',
            'papel',
            'status',
            'avatar_tipo',
            'avatar_icone',
            'avatar_cor',
            'avatar_imagem',
            'avatar_focal_x',
            'avatar_focal_y',
            'ultimo_acesso',
            'criado_em',
            'atualizado_em',
        ]);
    }

    private function buildAdminWhere(array $filters, array &$params): string
    {
        $where = [];

        $busca = trim((string) ($filters['busca'] ?? ''));
        if ($busca !== '') {
            $params['busca_nome'] = '%' . $busca . '%';
            $params['busca_usuario'] = '%' . $busca . '%';
            $params['busca_email'] = '%' . $busca . '%';
            $where[] = '(nome LIKE :busca_nome OR usuario LIKE :busca_usuario OR email LIKE :busca_email)';
        }

        $papel = trim((string) ($filters['papel'] ?? ''));
        if ($papel !== '' && in_array($papel, ['admin', 'editor'], true)) {
            $params['papel'] = $papel;
            $where[] = 'papel = :papel';
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['ativo', 'inativo'], true)) {
            $params['status'] = $status;
            $where[] = 'status = :status';
        }

        return $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    }

    private function adminOrderBy(string $sort, string $dir): string
    {
        $column = match ($sort) {
            'nome' => 'nome',
            'usuario' => 'usuario',
            'email' => 'email',
            'papel' => 'papel',
            'status' => 'status',
            'ultimo_acesso' => 'ultimo_acesso',
            default => 'criado_em',
        };

        $direction = strtolower(trim($dir)) === 'asc' ? 'ASC' : 'DESC';
        return $column . ' ' . $direction . ', id DESC';
    }
}