<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Repositories/PostRepository.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Repository de Posts (SQL)
 * @description Centraliza queries SQL relacionadas a posts para o Dashboard/Admin.
 * @usage       Injetado em Services (ex.: DashboardService) para leitura de métricas.
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
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
}