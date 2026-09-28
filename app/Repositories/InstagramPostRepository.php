<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Repositories/InstagramPostRepository.php
 * @project     Estrategia Nerd
 * @purpose     CRUD de posts locais do Instagram e sincronização com a Meta (FEAT-010)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class InstagramPostRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    // ── Leitura ───────────────────────────────────────────────────────────────

    /**
     * Retorna o primeiro account ativo ou null.
     *
     * @return array<string,mixed>|null
     */
    public function findActiveAccount(): ?array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM instagram_accounts WHERE ativo = 1 ORDER BY id ASC LIMIT 1"
        );

        $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listDrafts(int $accountId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, GROUP_CONCAT(m.caminho ORDER BY m.ordem SEPARATOR '|') AS medias
               FROM instagram_posts p
               LEFT JOIN instagram_post_media m ON m.post_id = p.id
              WHERE p.account_id = :account_id
                AND p.status = 'rascunho'
              GROUP BY p.id
              ORDER BY p.criado_em DESC
              LIMIT :lim"
        );
        $stmt->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listScheduled(int $accountId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, GROUP_CONCAT(m.caminho ORDER BY m.ordem SEPARATOR '|') AS medias
               FROM instagram_posts p
               LEFT JOIN instagram_post_media m ON m.post_id = p.id
              WHERE p.account_id = :account_id
                AND p.status = 'agendado'
              GROUP BY p.id
              ORDER BY p.agendado_para ASC
              LIMIT :lim"
        );
        $stmt->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listPublished(int $accountId, int $limit = 30): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, GROUP_CONCAT(m.caminho ORDER BY m.ordem SEPARATOR '|') AS medias
               FROM instagram_posts p
               LEFT JOIN instagram_post_media m ON m.post_id = p.id
              WHERE p.account_id = :account_id
                AND p.status = 'publicado'
              GROUP BY p.id
              ORDER BY p.publicado_em DESC
              LIMIT :lim"
        );
        $stmt->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*
               FROM instagram_posts p
              WHERE p.id = :id
              LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Retorna os arquivos de mídia de um post, ordenados por `ordem`.
     *
     * @return array<int,array<string,mixed>>
     */
    public function findMediaByPostId(int $postId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM instagram_post_media WHERE post_id = :post_id ORDER BY ordem ASC"
        );
        $stmt->execute([':post_id' => $postId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca posts agendados prontos para publicar (agendado_para <= NOW() e sem lock).
     *
     * @return array<int,array<string,mixed>>
     */
    public function findDueScheduled(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM instagram_posts
              WHERE status = 'agendado'
                AND agendado_para <= NOW()
              ORDER BY agendado_para ASC
              FOR UPDATE SKIP LOCKED"
        );

        if ($stmt === false) {
            return [];
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Escrita ───────────────────────────────────────────────────────────────

    /**
     * Cria um novo post local e retorna o ID gerado.
     *
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO instagram_posts
               (account_id, status, tipo, legenda, hashtags_count,
                agendado_para, post_blog_id, idempotency_key, origin, criado_por)
             VALUES
               (:account_id, :status, :tipo, :legenda, :hashtags_count,
                :agendado_para, :post_blog_id, :idempotency_key, :origin, :criado_por)"
        );
        $stmt->execute([
            ':account_id'      => (int) ($data['account_id'] ?? 0),
            ':status'          => (string) ($data['status'] ?? 'rascunho'),
            ':tipo'            => (string) ($data['tipo'] ?? 'imagem'),
            ':legenda'         => $data['legenda'] ?? null,
            ':hashtags_count'  => (int) ($data['hashtags_count'] ?? 0),
            ':agendado_para'   => $data['agendado_para'] ?? null,
            ':post_blog_id'    => $data['post_blog_id'] ?? null,
            ':idempotency_key' => $data['idempotency_key'] ?? null,
            ':origin'          => (string) ($data['origin'] ?? 'local'),
            ':criado_por'      => $data['criado_por'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Atualiza dados de um post existente.
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE instagram_posts
                SET status         = :status,
                    tipo           = :tipo,
                    legenda        = :legenda,
                    hashtags_count = :hashtags_count,
                    agendado_para  = :agendado_para,
                    post_blog_id   = :post_blog_id,
                    atualizado_em  = NOW()
              WHERE id = :id"
        );

        return $stmt->execute([
            ':id'             => $id,
            ':status'         => (string) ($data['status'] ?? 'rascunho'),
            ':tipo'           => (string) ($data['tipo'] ?? 'imagem'),
            ':legenda'        => $data['legenda'] ?? null,
            ':hashtags_count' => (int) ($data['hashtags_count'] ?? 0),
            ':agendado_para'  => $data['agendado_para'] ?? null,
            ':post_blog_id'   => $data['post_blog_id'] ?? null,
        ]);
    }

    /**
     * Marca um post como "publicando" usando lock atômico.
     * Retorna true somente se a linha foi efetivamente atualizada.
     */
    public function lockForPublishing(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE instagram_posts
                SET status = 'publicando', atualizado_em = NOW()
              WHERE id = :id AND status = 'agendado'"
        );
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Marca um post como publicado com ig_media_id e permalink.
     */
    public function markPublished(int $id, string $igMediaId, string $permalink): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE instagram_posts
                SET status        = 'publicado',
                    ig_media_id   = :ig_media_id,
                    permalink     = :permalink,
                    publicado_em  = NOW(),
                    atualizado_em = NOW()
              WHERE id = :id"
        );

        return $stmt->execute([
            ':id'          => $id,
            ':ig_media_id' => $igMediaId,
            ':permalink'   => $permalink,
        ]);
    }

    /**
     * Marca um post como erro e registra a mensagem.
     */
    public function markError(int $id, string $errorMessage): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE instagram_posts
                SET status        = 'erro',
                    error_log     = :error_log,
                    atualizado_em = NOW()
              WHERE id = :id"
        );

        return $stmt->execute([':id' => $id, ':error_log' => $errorMessage]);
    }

    /**
     * Salva o creation_id (container da Meta) em um post.
     */
    public function saveCreationId(int $id, string $creationId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE instagram_posts SET creation_id = :cid, atualizado_em = NOW() WHERE id = :id"
        );

        return $stmt->execute([':id' => $id, ':cid' => $creationId]);
    }

    /**
     * Adiciona uma mídia ao post (usada para carrossel e posts simples).
     *
     * @param array<string,mixed> $media
     */
    public function addMedia(int $postId, array $media): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO instagram_post_media
               (post_id, ordem, tipo_arquivo, caminho, url_publica, largura, altura, duracao_s)
             VALUES
               (:post_id, :ordem, :tipo_arquivo, :caminho, :url_publica, :largura, :altura, :duracao_s)"
        );
        $stmt->execute([
            ':post_id'      => $postId,
            ':ordem'        => (int) ($media['ordem'] ?? 0),
            ':tipo_arquivo' => (string) ($media['tipo_arquivo'] ?? 'imagem'),
            ':caminho'      => (string) ($media['caminho'] ?? ''),
            ':url_publica'  => $media['url_publica'] ?? null,
            ':largura'      => $media['largura'] ?? null,
            ':altura'       => $media['altura'] ?? null,
            ':duracao_s'    => $media['duracao_s'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Busca uma mídia específica por seu ID.
     *
     * @return array<string,mixed>|null
     */
    public function findMediaById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM instagram_post_media WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Remove uma mídia individual de um post específico.
     */
    public function deleteMedia(int $mediaId, int $postId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM instagram_post_media WHERE id = :id AND post_id = :post_id"
        );

        return $stmt->execute([
            ':id'      => $mediaId,
            ':post_id' => $postId,
        ]);
    }

    /**
     * Remove todas as mídias de um post (usado ao reeditar).
     */
    public function deleteMediaByPostId(int $postId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM instagram_post_media WHERE post_id = :post_id"
        );

        return $stmt->execute([':post_id' => $postId]);
    }

    /**
     * Upsert de um post vindo do feed do Instagram (origin = 'instagram').
     * Atualiza se ig_media_id já existir, insere caso contrário.
     *
     * @param array<string,mixed> $data
     */
    public function upsertFromFeed(array $data): void
    {
        $igMediaId = trim((string) ($data['ig_media_id'] ?? ''));
        if ($igMediaId === '') {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO instagram_posts
                   (account_id, status, tipo, legenda, hashtags_count, curtidas, comentarios_count,
                    ig_media_id, permalink, publicado_em, origin)
                 VALUES
                   (:account_id, 'publicado', :tipo, :legenda, :hashtags_count, :curtidas, :comentarios_count,
                    :ig_media_id, :permalink, :publicado_em, 'instagram')
                 ON DUPLICATE KEY UPDATE
                   tipo              = VALUES(tipo),
                   legenda           = VALUES(legenda),
                   hashtags_count    = VALUES(hashtags_count),
                   curtidas          = VALUES(curtidas),
                   comentarios_count = VALUES(comentarios_count),
                   permalink         = VALUES(permalink),
                   publicado_em      = VALUES(publicado_em),
                   atualizado_em     = NOW()"
            );
            $stmt->execute([
                ':account_id'        => (int) ($data['account_id'] ?? 0),
                ':tipo'              => (string) ($data['tipo'] ?? 'imagem'),
                ':legenda'           => $data['legenda'] ?? null,
                ':hashtags_count'    => (int) ($data['hashtags_count'] ?? 0),
                ':curtidas'          => (int) ($data['curtidas'] ?? 0),
                ':comentarios_count' => (int) ($data['comentarios_count'] ?? 0),
                ':ig_media_id'       => $igMediaId,
                ':permalink'         => $data['permalink'] ?? null,
                ':publicado_em'      => $data['publicado_em'] ?? null,
            ]);

            $stmtId = $this->pdo->prepare("SELECT id FROM instagram_posts WHERE ig_media_id = :ig_media_id LIMIT 1");
            $stmtId->execute([':ig_media_id' => $igMediaId]);
            $postId = (int) $stmtId->fetchColumn();

            if ($postId > 0) {
                $mediaUrl = trim((string) ($data['media_url'] ?? $data['thumbnail_url'] ?? ''));
                if ($mediaUrl !== '') {
                    $tipoArquivo = ($data['tipo'] ?? '') === 'reels' ? 'video' : 'imagem';
                    $stmtMedia = $this->pdo->prepare("SELECT id FROM instagram_post_media WHERE post_id = :post_id AND ordem = 1 LIMIT 1");
                    $stmtMedia->execute([':post_id' => $postId]);
                    $mediaId = (int) $stmtMedia->fetchColumn();

                    if ($mediaId > 0) {
                        $updMedia = $this->pdo->prepare("UPDATE instagram_post_media SET caminho = :caminho, url_publica = :url_publica, tipo_arquivo = :tipo WHERE id = :id");
                        $updMedia->execute([
                            ':caminho'     => $mediaUrl,
                            ':url_publica' => $mediaUrl,
                            ':tipo'        => $tipoArquivo,
                            ':id'          => $mediaId,
                        ]);
                    } else {
                        $insMedia = $this->pdo->prepare(
                            "INSERT INTO instagram_post_media (post_id, ordem, tipo_arquivo, caminho, url_publica)
                             VALUES (:post_id, 1, :tipo, :caminho, :url_publica)"
                        );
                        $insMedia->execute([
                            ':post_id'     => $postId,
                            ':tipo'        => $tipoArquivo,
                            ':caminho'     => $mediaUrl,
                            ':url_publica' => $mediaUrl,
                        ]);
                    }
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    // ── Cache de Insights ─────────────────────────────────────────────────────

    /**
     * Salva (upsert) um snapshot de insights no cache.
     *
     * @param array<string,mixed> $data
     */
    public function upsertInsightsCache(array $data): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO instagram_insights_cache
               (account_id, periodo, data_inicio, data_fim, data_referencia, alcance, visualizacoes, impressoes,
                visitas_perfil, interacoes, seguidores, variacao_seguidores, payload_raw)
             VALUES
               (:account_id, :periodo, :data_inicio, :data_fim, :data_referencia, :alcance, :visualizacoes, :impressoes,
                :visitas_perfil, :interacoes, :seguidores, :variacao_seguidores, :payload_raw)
             ON DUPLICATE KEY UPDATE
               alcance             = VALUES(alcance),
               visualizacoes       = VALUES(visualizacoes),
               impressoes          = VALUES(impressoes),
               visitas_perfil      = VALUES(visitas_perfil),
               interacoes          = VALUES(interacoes),
               seguidores          = VALUES(seguidores),
               variacao_seguidores = VALUES(variacao_seguidores),
               payload_raw         = VALUES(payload_raw)"
        );
        $stmt->execute([
            ':account_id'          => (int) ($data['account_id'] ?? 0),
            ':periodo'             => (string) ($data['periodo'] ?? '7d'),
            ':data_inicio'         => $data['data_inicio'] ?? null,
            ':data_fim'            => $data['data_fim'] ?? null,
            ':data_referencia'     => (string) ($data['data_referencia'] ?? date('Y-m-d')),
            ':alcance'             => (int) ($data['alcance'] ?? 0),
            ':visualizacoes'       => (int) ($data['visualizacoes'] ?? $data['views'] ?? 0),
            ':impressoes'          => (int) ($data['impressoes'] ?? 0),
            ':visitas_perfil'      => (int) ($data['visitas_perfil'] ?? 0),
            ':interacoes'          => (int) ($data['interacoes'] ?? 0),
            ':seguidores'          => (int) ($data['seguidores'] ?? 0),
            ':variacao_seguidores' => (int) ($data['variacao_seguidores'] ?? 0),
            ':payload_raw'         => isset($data['payload_raw']) ? json_encode($data['payload_raw']) : null,
        ]);
    }

    /**
     * Retorna o último snapshot de insights para o período ou intervalo informado.
     *
     * @return array<string,mixed>|null
     */
    public function getLatestInsights(int $accountId, string $period = '7d', ?string $startDate = null, ?string $endDate = null): ?array
    {
        if ($startDate !== null && $endDate !== null) {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM instagram_insights_cache
                  WHERE account_id = :account_id AND data_inicio = :start_date AND data_fim = :end_date
                  ORDER BY data_referencia DESC
                  LIMIT 1"
            );
            $stmt->execute([
                ':account_id' => $accountId,
                ':start_date' => $startDate,
                ':end_date'   => $endDate,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $row;
            }
        }

        $stmt = $this->pdo->prepare(
            "SELECT * FROM instagram_insights_cache
              WHERE account_id = :account_id AND periodo = :periodo
              ORDER BY data_referencia DESC
              LIMIT 1"
        );
        $stmt->execute([':account_id' => $accountId, ':periodo' => $period]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Retorna o snapshot anterior a uma data de referência (ex: anterior a hoje).
     *
     * @return array<string,mixed>|null
     */
    public function getPreviousInsights(int $accountId, string $period, string $beforeDate): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM instagram_insights_cache
              WHERE account_id = :account_id AND periodo = :periodo AND data_referencia < :before_date
              ORDER BY data_referencia DESC
              LIMIT 1"
        );
        $stmt->execute([
            ':account_id'  => $accountId,
            ':periodo'     => $period,
            ':before_date' => $beforeDate,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Lista posts com paginação, filtros e ordenação para a tabela do painel admin.
     *
     * @param int                 $accountId
     * @param array<string,mixed> $filters
     * @param string              $sort
     * @param string              $dir
     * @param int                 $page
     * @param int                 $perPage
     * @return array{items: list<array<string,mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function listPostsPaged(
        int $accountId,
        array $filters = [],
        string $sort = 'publicado_em',
        string $dir = 'desc',
        int $page = 1,
        int $perPage = 10
    ): array {
        $allowedSort = [
            'data'              => 'p.publicado_em',
            'publicado_em'      => 'p.publicado_em',
            'curtidas'          => 'p.curtidas',
            'comentarios'       => 'p.comentarios_count',
            'comentarios_count' => 'p.comentarios_count',
            'tipo'              => 'p.tipo',
            'status'            => 'p.status',
            'id'                => 'p.id',
        ];

        $sortCol = $allowedSort[$sort] ?? 'p.publicado_em';
        $direction = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

        $where = ['p.account_id = :account_id'];
        $params = [':account_id' => $accountId];

        $busca = trim((string) ($filters['busca'] ?? ''));
        if ($busca !== '') {
            $where[] = 'p.legenda LIKE :busca';
            $params[':busca'] = '%' . $busca . '%';
        }

        $tipo = trim((string) ($filters['tipo'] ?? ''));
        if ($tipo !== '' && in_array($tipo, ['imagem', 'carrossel', 'reels', 'story'], true)) {
            $where[] = 'p.tipo = :tipo';
            $params[':tipo'] = $tipo;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['publicado', 'agendado', 'rascunho', 'publicando', 'erro'], true)) {
            $where[] = 'p.status = :status';
            $params[':status'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM instagram_posts p WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = max(1, $page);
        $isAll = $perPage >= 9999;
        $perPage = in_array($perPage, [8, 16, 24, 48], true) ? $perPage : ($isAll ? 9999 : 8);
        $pages = $isAll ? 1 : max(1, (int) ceil($total / $perPage));
        if ($page > $pages && $total > 0) {
            $page = $pages;
        }
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT p.*, GROUP_CONCAT(m.caminho ORDER BY m.ordem SEPARATOR '|') AS medias
                  FROM instagram_posts p
                  LEFT JOIN instagram_post_media m ON m.post_id = p.id
                 WHERE {$whereSql}
                 GROUP BY p.id
                 ORDER BY {$sortCol} {$direction}, p.id DESC
                 LIMIT :offset, :per_page";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
        $stmt->execute();

        /** @var list<array<string,mixed>> $items */
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $pages,
        ];
    }

    /**
     * Atualiza dados de sincronização da conta (followers, etc).
     *
     * @param array<string,mixed> $profile
     */
    public function syncAccount(int $accountId, array $profile): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE instagram_accounts
                SET username         = :username,
                    bio              = :bio,
                    website          = :website,
                    profile_picture  = :profile_picture,
                    followers_count  = :followers_count,
                    follows_count    = :follows_count,
                    media_count      = :media_count,
                    synced_at        = NOW(),
                    atualizado_em    = NOW()
              WHERE id = :id"
        );

        return $stmt->execute([
            ':id'              => $accountId,
            ':username'        => (string) ($profile['username'] ?? ''),
            ':bio'             => $profile['biography'] ?? null,
            ':website'         => $profile['website'] ?? null,
            ':profile_picture' => $profile['profile_picture_url'] ?? null,
            ':followers_count' => (int) ($profile['followers_count'] ?? 0),
            ':follows_count'   => (int) ($profile['follows_count'] ?? 0),
            ':media_count'     => (int) ($profile['media_count'] ?? 0),
        ]);
    }
}
