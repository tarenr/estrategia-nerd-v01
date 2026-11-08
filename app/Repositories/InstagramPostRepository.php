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
     * Atualiza o access_token e a data de expiração da conta.
     */
    public function updateAccountToken(int $accountId, string $newToken, ?string $expiresAt = null): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE instagram_accounts
                SET access_token = :token,
                    token_expires_at = :expires,
                    atualizado_em = NOW()
              WHERE id = :id"
        );
        $stmt->execute([
            ':token'   => $newToken,
            ':expires' => $expiresAt,
            ':id'      => $accountId,
        ]);
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
     * Posts do Instagram com data no mes, para o calendario de Agendamento:
     * publicados pela data em que sairam; agendado/publicando/erro pela data agendada.
     * Rascunhos (sem data) ficam de fora.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listForCalendar(int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $end   = date('Y-m-d H:i:s', (int) mktime(23, 59, 59, $month + 1, 0, $year));

        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.status, p.tipo, p.legenda, p.agendado_para, p.publicado_em, p.origin,
                    CASE WHEN p.status = 'publicado' THEN p.publicado_em ELSE p.agendado_para END AS data_evento,
                    COALESCE(p.video_rendered_path, (SELECT m.caminho FROM instagram_post_media m WHERE m.post_id = p.id ORDER BY m.ordem ASC LIMIT 1)) AS media_caminho
               FROM instagram_posts p
              WHERE (p.status = 'publicado' AND p.publicado_em BETWEEN :s1 AND :e1)
                 OR (p.status IN ('agendado', 'publicando', 'erro') AND p.agendado_para BETWEEN :s2 AND :e2)
              ORDER BY data_evento ASC"
        );
        $stmt->execute([':s1' => $start, ':e1' => $end, ':s2' => $start, ':e2' => $end]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca posts agendados prontos para publicar (agendado_para <= NOW() e sem lock).
     *
     * @return array<int,array<string,mixed>>
     */
    public function findDueScheduled(): array
    {
        // Sem FOR UPDATE SKIP LOCKED (MariaDB 10.4): concorrencia garantida pelo flock do script e por lockForPublishing().
        $stmt = $this->pdo->query(
            "SELECT * FROM instagram_posts
              WHERE status = 'agendado'
                AND publish_phase IN ('idle','failed')
                AND (creation_id IS NULL OR publish_phase = 'failed')
                AND agendado_para <= NOW()
              ORDER BY agendado_para ASC"
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
                agendado_para, post_blog_id, audio_track_id, audio_start_seconds,
                audio_duration_seconds, video_rendered_path, render_status,
                idempotency_key, origin, criado_por)
             VALUES
               (:account_id, :status, :tipo, :legenda, :hashtags_count,
                :agendado_para, :post_blog_id, :audio_track_id, :audio_start_seconds,
                :audio_duration_seconds, :video_rendered_path, :render_status,
                :idempotency_key, :origin, :criado_por)"
        );
        $stmt->execute([
            ':account_id'             => (int) ($data['account_id'] ?? 0),
            ':status'                 => (string) ($data['status'] ?? 'rascunho'),
            ':tipo'                   => (string) ($data['tipo'] ?? 'imagem'),
            ':legenda'                => $data['legenda'] ?? null,
            ':hashtags_count'         => (int) ($data['hashtags_count'] ?? 0),
            ':agendado_para'          => $data['agendado_para'] ?? null,
            ':post_blog_id'           => $data['post_blog_id'] ?? null,
            ':audio_track_id'         => !empty($data['audio_track_id']) ? (int) $data['audio_track_id'] : null,
            ':audio_start_seconds'    => (int) ($data['audio_start_seconds'] ?? 0),
            ':audio_duration_seconds' => (int) ($data['audio_duration_seconds'] ?? 0),
            ':video_rendered_path'    => $data['video_rendered_path'] ?? null,
            ':render_status'          => (string) ($data['render_status'] ?? 'idle'),
            ':idempotency_key'        => $data['idempotency_key'] ?? null,
            ':origin'                 => (string) ($data['origin'] ?? 'local'),
            ':criado_por'             => $data['criado_por'] ?? null,
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
        $previous = $this->findById($id);
        if ($previous === null) {
            return false;
        }
        $audioChanged = false;
        foreach (['audio_track_id', 'audio_start_seconds', 'audio_duration_seconds'] as $key) {
            if ((int) ($previous[$key] ?? 0) !== (int) ($data[$key] ?? 0)) {
                $audioChanged = true;
            }
        }
        if (!array_key_exists('video_rendered_path', $data)) {
            $data['video_rendered_path'] = $audioChanged ? null : ($previous['video_rendered_path'] ?? null);
            $data['render_status'] = $audioChanged ? 'idle' : ($previous['render_status'] ?? 'idle');
        }
        $stmt = $this->pdo->prepare(
            "UPDATE instagram_posts
                SET status                 = :status,
                    tipo                   = :tipo,
                    legenda                = :legenda,
                    hashtags_count         = :hashtags_count,
                    agendado_para          = :agendado_para,
                    post_blog_id           = :post_blog_id,
                    audio_track_id         = :audio_track_id,
                    audio_start_seconds    = :audio_start_seconds,
                    audio_duration_seconds = :audio_duration_seconds,
                    video_rendered_path    = :video_rendered_path,
                    render_status          = :render_status,
                    atualizado_em          = NOW()
              WHERE id = :id"
        );

        return $stmt->execute([
            ':id'                     => $id,
            ':status'                 => (string) ($data['status'] ?? 'rascunho'),
            ':tipo'                   => (string) ($data['tipo'] ?? 'imagem'),
            ':legenda'                => $data['legenda'] ?? null,
            ':hashtags_count'         => (int) ($data['hashtags_count'] ?? 0),
            ':agendado_para'          => $data['agendado_para'] ?? null,
            ':post_blog_id'           => $data['post_blog_id'] ?? null,
            ':audio_track_id'         => !empty($data['audio_track_id']) ? (int) $data['audio_track_id'] : null,
            ':audio_start_seconds'    => (int) ($data['audio_start_seconds'] ?? 0),
            ':audio_duration_seconds' => (int) ($data['audio_duration_seconds'] ?? 0),
            ':video_rendered_path'    => $data['video_rendered_path'] ?? null,
            ':render_status'          => (string) ($data['render_status'] ?? 'idle'),
        ]);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findAudioTrack(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM instagram_audio_tracks WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByIdempotencyKey(string $key): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM instagram_posts WHERE idempotency_key = :k LIMIT 1");
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Le e trava a linha do cross-post; exige transacao aberta pelo chamador.
     *
     * @return array<string,mixed>|null
     */
    public function lockByIdempotencyKey(string $key): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM instagram_posts WHERE idempotency_key = :k LIMIT 1 FOR UPDATE");
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Atualiza so a legenda de um rascunho criado pelo cross-post do blog.
     * O status ja deve ter sido conferido sob lock (lockByIdempotencyKey).
     */
    public function updateCrosspostDraft(int $id, string $legenda, int $hashtagsCount): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE instagram_posts
                SET status         = 'rascunho',
                    legenda        = :legenda,
                    hashtags_count = :hashtags_count,
                    error_log      = NULL,
                    atualizado_em  = NOW()
              WHERE id = :id AND status IN ('rascunho', 'erro')"
        );
        $stmt->execute([
            ':id'             => $id,
            ':legenda'        => $legenda,
            ':hashtags_count' => $hashtagsCount,
        ]);
    }

    /**
     * Marca um post como "publicando" usando lock atômico.
     * Retorna true somente se a linha foi efetivamente atualizada.
     */
    public function lockForPublishing(int $id): bool
    {
        return $this->claimPublication($id, ['agendado']);
    }

    public function claimForImmediatePublishing(int $id): bool
    {
        return $this->claimPublication($id, ['rascunho', 'agendado', 'erro']);
    }

    /** @param list<string> $statuses */
    private function claimPublication(int $id, array $statuses): bool
    {
        $allowed = implode(',', array_map(static fn (string $s): string => "'" . $s . "'", $statuses));
        $stmt = $this->pdo->prepare(
            "UPDATE instagram_posts
                SET status = 'publicando', publish_phase = 'preparing', publish_attempted_at = NULL,
                    creation_id = NULL, error_log = NULL, atualizado_em = NOW()
              WHERE id = :id AND status IN ($allowed) AND ig_media_id IS NULL
                AND publish_phase IN ('idle','failed')
                AND (creation_id IS NULL OR publish_phase = 'failed')"
        );
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Libera posts presos em 'publicando' por processo encerrado no meio.
     * Vai para 'erro' (nao 'agendado') porque o post pode ja ter saido na Meta.
     */
    public function markStalePublishingAsError(int $minutes): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE instagram_posts
                SET status        = 'erro', publish_phase = 'failed',
                    error_log     = :error_log,
                    atualizado_em = NOW()
              WHERE status = 'publicando'
                AND (publish_phase IN ('preparing','container_created')
                     OR (publish_phase IN ('idle','failed') AND creation_id IS NULL))
                AND atualizado_em < NOW() - INTERVAL :minutes MINUTE"
        );
        $stmt->bindValue(':error_log', 'Publicação interrompida (processo encerrado no meio). Verifique no Instagram se o post saiu antes de reagendar.');
        $stmt->bindValue(':minutes', max(1, $minutes), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
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
     * Cancela posts publicados que já não existem no Instagram (apagados na plataforma).
     * Só toca em status 'publicado'; preserva ig_media_id e acrescenta a nota em error_log.
     *
     * @param list<int> $ids
     */
    public function markRemovedFromInstagram(array $ids, string $note): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "UPDATE instagram_posts
                SET status        = 'cancelado',
                    error_log     = CONCAT_WS(CHAR(10), NULLIF(error_log, ''), ?),
                    atualizado_em = NOW()
              WHERE status = 'publicado' AND id IN ($placeholders)"
        );
        $stmt->execute(array_merge([$note], $ids));

        return $stmt->rowCount();
    }

    /**
     * Marca um post como erro e registra a mensagem.
     */
    public function markError(int $id, string $errorMessage): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE instagram_posts
                SET status        = 'erro', publish_phase = 'failed',
                    error_log     = :error_log,
                    atualizado_em = NOW()
              WHERE id = :id AND status <> 'publicado'
                AND publish_phase NOT IN ('awaiting_confirmation','published_id_pending','confirmed')"
        );

        return $stmt->execute([':id' => $id, ':error_log' => $errorMessage]);
    }

    /**
     * Salva o creation_id (container da Meta) em um post.
     */
    public function saveCreationId(int $id, string $creationId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE instagram_posts SET creation_id = :cid, publish_phase = 'container_created', atualizado_em = NOW()
              WHERE id = :id AND status = 'publicando' AND publish_phase = 'preparing'"
        );
        $stmt->execute([':id' => $id, ':cid' => $creationId]);
        return $stmt->rowCount() === 1;
    }

    public function beginPublishAttempt(int $id, string $creationId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE instagram_posts SET publish_phase='awaiting_confirmation',
            publish_attempted_at=NOW(), atualizado_em=NOW()
            WHERE id=:id AND creation_id=:cid AND status='publicando' AND publish_phase='container_created' AND ig_media_id IS NULL");
        $stmt->execute([':id'=>$id, ':cid'=>$creationId]);
        return $stmt->rowCount() === 1;
    }

    public function markPublishPending(int $id, string $message, bool $containerPublished = false): void
    {
        $stmt = $this->pdo->prepare("UPDATE instagram_posts SET error_log=:message,
            publish_phase=CASE WHEN publish_phase='published_id_pending' THEN publish_phase ELSE :phase END, atualizado_em=NOW()
            WHERE id=:id AND status='publicando' AND publish_phase IN ('awaiting_confirmation','published_id_pending') AND ig_media_id IS NULL");
        $stmt->execute([':id'=>$id, ':message'=>$message, ':phase'=>$containerPublished ? 'published_id_pending' : 'awaiting_confirmation']);
    }

    public function rejectPublishAttempt(int $id, string $creationId, string $message): void
    {
        $stmt = $this->pdo->prepare("UPDATE instagram_posts SET status='erro',publish_phase='failed',error_log=:message,atualizado_em=NOW()
            WHERE id=:id AND creation_id=:cid AND status='publicando' AND publish_phase='awaiting_confirmation' AND ig_media_id IS NULL");
        $stmt->execute([':id'=>$id, ':cid'=>$creationId, ':message'=>$message]);
    }

    public function confirmPublishAttempt(int $id, string $creationId, string $mediaId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE instagram_posts SET status='publicado', publish_phase='confirmed',
            ig_media_id=:mid, error_log=NULL, publicado_em=COALESCE(publicado_em,NOW()), atualizado_em=NOW()
            WHERE id=:id AND creation_id=:cid AND status='publicando'
              AND publish_phase IN ('awaiting_confirmation','published_id_pending') AND ig_media_id IS NULL");
        $stmt->execute([':id'=>$id, ':cid'=>$creationId, ':mid'=>$mediaId]);
        if ($stmt->rowCount() === 1) { return true; }
        $current = $this->findById($id);
        return $current !== null && $current['status']==='publicado' && $current['creation_id']===$creationId && $current['ig_media_id']===$mediaId;
    }

    public function updatePublishedPermalink(int $id, string $mediaId, string $permalink): void
    {
        $stmt = $this->pdo->prepare("UPDATE instagram_posts SET permalink=:link, atualizado_em=NOW()
            WHERE id=:id AND status='publicado' AND ig_media_id=:mid");
        $stmt->execute([':id'=>$id, ':mid'=>$mediaId, ':link'=>$permalink]);
    }

    /** @return list<array<string,mixed>> */
    public function listPublishConfirmations(int $accountId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM instagram_posts WHERE account_id=:account
            AND ((status='publicando' AND publish_phase IN ('awaiting_confirmation','published_id_pending'))
              OR (status='publicado' AND publish_phase='confirmed' AND (permalink IS NULL OR permalink='')))
            ORDER BY atualizado_em,id LIMIT :lim");
        $stmt->bindValue(':account', $accountId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        $id = (int) $this->pdo->lastInsertId();
        $this->invalidateRenderedVideo($postId);
        return $id;
    }

    /**
     * Aponta uma midia para outro arquivo (ex.: versao ajustada pelo Smart Canvas).
     */
    public function updateMediaFile(int $mediaId, string $caminho, ?string $urlPublica, int $largura, int $altura): void
    {
        $previous = $this->findMediaById($mediaId);
        $stmt = $this->pdo->prepare(
            "UPDATE instagram_post_media
                SET caminho = :caminho, url_publica = :url_publica, largura = :largura, altura = :altura
              WHERE id = :id"
        );
        $stmt->execute([
            ':id'          => $mediaId,
            ':caminho'     => $caminho,
            ':url_publica' => $urlPublica,
            ':largura'     => $largura,
            ':altura'      => $altura,
        ]);
        if ($previous !== null && ((string) ($previous['caminho'] ?? '') !== $caminho
            || (int) ($previous['largura'] ?? 0) !== $largura || (int) ($previous['altura'] ?? 0) !== $altura)) {
            $this->invalidateRenderedVideo((int) $previous['post_id']);
        }
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

        $ok = $stmt->execute([
            ':id'      => $mediaId,
            ':post_id' => $postId,
        ]);
        if ($stmt->rowCount() > 0) {
            $this->invalidateRenderedVideo($postId);
        }
        return $ok;
    }

    public function invalidateRenderedVideo(int $postId): void
    {
        $this->pdo->prepare('UPDATE instagram_posts SET video_rendered_path = NULL, render_status = "idle" WHERE id = :id')
            ->execute([':id' => $postId]);
    }

    /** Chamar depois de salvar todas as midias, na mesma transacao quando aplicavel. */
    public function markRenderedReady(int $postId, string $path, ?int $seconds = null): void
    {
        if ($seconds === null) {
            $this->pdo->prepare('UPDATE instagram_posts SET video_rendered_path = :path, render_status = "ready" WHERE id = :id')
                ->execute([':path' => $path, ':id' => $postId]);
            return;
        }
        // Editorial cache regeneration updates the media reference and cache together,
        // only while the caller still owns the pre-publication claim.
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) { $this->pdo->beginTransaction(); }
        try {
            $statement = $this->pdo->prepare("UPDATE instagram_posts SET video_rendered_path=:path,render_status='ready',audio_duration_seconds=:seconds
                WHERE id=:id AND status='publicando' AND publish_phase='preparing' AND creation_id IS NULL AND ig_media_id IS NULL");
            $statement->execute([':path'=>$path,':seconds'=>$seconds,':id'=>$postId]);
            if ($statement->rowCount() !== 1) { throw new \RuntimeException('Post mudou durante a regeneração; vídeo não enviado.'); }
            $statement = $this->pdo->prepare("UPDATE instagram_post_media SET caminho=:path,url_publica=NULL,largura=1080,altura=1920,duracao_s=:seconds
                WHERE post_id=:id AND tipo_arquivo='video'");
            $statement->execute([':path'=>$path,':seconds'=>$seconds,':id'=>$postId]);
            if ($statement->rowCount() !== 1) { throw new \RuntimeException('Composição editorial mudou; vídeo não enviado.'); }
            if ($ownsTransaction) { $this->pdo->commit(); }
        } catch (\Throwable $e) {
            if ($ownsTransaction) { $this->pdo->rollBack(); }
            throw $e;
        }
    }

    /**
     * Regra tipo x midias: imagem = 1 imagem; reels = 1 video; story = 1 item; carrossel = 2 a 10.
     * Retorna a mensagem de erro, ou null quando o conjunto e valido.
     *
     * @param list<string> $kinds 'imagem' ou 'video', na ordem de publicacao
     */
    public static function mediaRuleError(string $tipo, array $kinds, bool $allowEmpty = false, bool $hasAudioTrack = false): ?string
    {
        $total  = count($kinds);
        $videos = count(array_filter($kinds, static fn (string $k): bool => $k === 'video'));

        if (!in_array($tipo, ['imagem', 'carrossel', 'reels', 'story'], true)) {
            return 'Tipo de post inválido.';
        }
        if ($total === 0) {
            return $allowEmpty ? null : 'Adicione a mídia do post antes de agendar ou publicar.';
        }

        if ($hasAudioTrack) {
            // Com trilha sonora, o post vira Reels vertical compilado via FFmpeg
            // Aceita de 1 a 10 imagens (ou 1 vídeo)
            if ($total <= 10) {
                return null;
            }
            return 'Post com trilha sonora aceita de 1 a 10 imagens para compor o Reel.';
        }

        return match ($tipo) {
            'imagem'    => ($total === 1 && $videos === 0) ? null : 'Post do tipo Imagem aceita exatamente 1 imagem (vídeo vai como Reels; várias mídias, como Carrossel).',
            'reels'     => ($total === 1 && $videos === 1) ? null : 'Reels aceita exatamente 1 vídeo.',
            'story'     => $total === 1 ? null : 'Story aceita exatamente 1 imagem ou 1 vídeo.',
            default     => ($total >= 2 && $total <= 10) ? null : 'Carrossel precisa de 2 a 10 mídias (hoje: ' . $total . ').',
        };
    }

    /**
     * Tipos ('imagem'/'video') das midias salvas de um post, na ordem de publicacao.
     *
     * @param array<int,array<string,mixed>> $medias
     * @return list<string>
     */
    public static function mediaKinds(array $medias): array
    {
        $kinds = [];
        foreach ($medias as $m) {
            $kinds[] = (string) ($m['tipo_arquivo'] ?? 'imagem') === 'video' ? 'video' : 'imagem';
        }

        return $kinds;
    }

    /**
     * Renumera a ordem das midias do post em sequencia (0, 1, 2...) mantendo a ordem atual.
     */
    public function renumberMedia(int $postId): void
    {
        $ids  = $this->pdo->prepare("SELECT id FROM instagram_post_media WHERE post_id = :post_id ORDER BY ordem ASC, id ASC");
        $ids->execute([':post_id' => $postId]);
        $stmt = $this->pdo->prepare("UPDATE instagram_post_media SET ordem = :ordem WHERE id = :id");
        foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $i => $mediaId) {
            $stmt->execute([':ordem' => $i, ':id' => (int) $mediaId]);
        }
    }

    /**
     * Remove todas as mídias de um post (usado ao reeditar).
     */
    public function deleteMediaByPostId(int $postId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM instagram_post_media WHERE post_id = :post_id"
        );

        $ok = $stmt->execute([':post_id' => $postId]);
        if ($stmt->rowCount() > 0) {
            $this->invalidateRenderedVideo($postId);
        }
        return $ok;
    }

    /**
     * Exclui um post e todos os seus vínculos de mídias em transação.
     */
    public function deletePost(int $id): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("DELETE FROM instagram_post_media WHERE post_id = :post_id");
            $stmt->execute([':post_id' => $id]);

            $stmt2 = $this->pdo->prepare("DELETE FROM instagram_posts WHERE id = :id");
            $stmt2->execute([':id' => $id]);

            $this->pdo->commit();

            return $stmt2->rowCount() > 0;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
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

        $accountId = (int) ($data['account_id'] ?? 0);
        $permalink = trim((string) ($data['permalink'] ?? ''));
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            // Instagrapi devolve o PK privado; a Graph API devolve outro ID para
            // a mesma publicação. Vincule o ID oficial ao registro local pelo
            // permalink antes do upsert para não criar dois cards no site.
            if ($permalink !== '') {
                $existing = $this->pdo->prepare(
                    "SELECT id FROM instagram_posts
                      WHERE account_id = :account_id
                        AND status = 'publicado'
                        AND origin = 'local'
                        AND permalink = :permalink
                      ORDER BY id DESC LIMIT 1"
                );
                $existing->execute([':account_id' => $accountId, ':permalink' => $permalink]);
                $existingId = (int) $existing->fetchColumn();
                if ($existingId > 0) {
                    $bindGraphId = $this->pdo->prepare(
                        'UPDATE instagram_posts SET ig_media_id = :ig_media_id, atualizado_em = NOW() WHERE id = :id'
                    );
                    $bindGraphId->execute([':ig_media_id' => $igMediaId, ':id' => $existingId]);
                }
            }

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
                ':account_id'        => $accountId,
                ':tipo'              => (string) ($data['tipo'] ?? 'imagem'),
                ':legenda'           => $data['legenda'] ?? null,
                ':hashtags_count'    => (int) ($data['hashtags_count'] ?? 0),
                ':curtidas'          => (int) ($data['curtidas'] ?? 0),
                ':comentarios_count' => (int) ($data['comentarios_count'] ?? 0),
                ':ig_media_id'       => $igMediaId,
                ':permalink'         => $permalink !== '' ? $permalink : null,
                ':publicado_em'      => $data['publicado_em'] ?? null,
            ]);

            $stmtId = $this->pdo->prepare("SELECT id, origin FROM instagram_posts WHERE ig_media_id = :ig_media_id LIMIT 1");
            $stmtId->execute([':ig_media_id' => $igMediaId]);
            $row    = $stmtId->fetch(PDO::FETCH_ASSOC) ?: [];
            $postId = (int) ($row['id'] ?? 0);

            // Post criado no admin (origin 'local') ja tem as proprias midias, numeradas a partir de 0;
            // a capa do feed so e gravada nos posts que vieram do Instagram.
            if ($postId > 0 && ($row['origin'] ?? '') === 'instagram') {
                $rawThumb = trim((string) ($data['thumbnail_url'] ?? ''));
                $rawMedia = trim((string) ($data['media_url'] ?? ''));
                $isReels  = ($data['tipo'] ?? '') === 'reels';

                // Para Reels, a capa visual primária (ordem 1) deve ser a thumbnail_url
                $coverUrl = ($isReels && $rawThumb !== '') ? $rawThumb : ($rawMedia !== '' ? $rawMedia : $rawThumb);

                if ($coverUrl !== '') {
                    $stmtMedia = $this->pdo->prepare("SELECT id FROM instagram_post_media WHERE post_id = :post_id AND ordem = 1 LIMIT 1");
                    $stmtMedia->execute([':post_id' => $postId]);
                    $mediaId = (int) $stmtMedia->fetchColumn();

                    if ($mediaId > 0) {
                        $updMedia = $this->pdo->prepare("UPDATE instagram_post_media SET caminho = :caminho, url_publica = :url_publica, tipo_arquivo = 'imagem' WHERE id = :id");
                        $updMedia->execute([
                            ':caminho'     => $coverUrl,
                            ':url_publica' => $coverUrl,
                            ':id'          => $mediaId,
                        ]);
                    } else {
                        $insMedia = $this->pdo->prepare(
                            "INSERT INTO instagram_post_media (post_id, ordem, tipo_arquivo, caminho, url_publica)
                             VALUES (:post_id, 1, 'imagem', :caminho, :url_publica)"
                        );
                        $insMedia->execute([
                            ':post_id'     => $postId,
                            ':caminho'     => $coverUrl,
                            ':url_publica' => $coverUrl,
                        ]);
                    }
                }
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction) {
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
            return is_array($row) ? $row : null;
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
            'data'              => 'COALESCE(p.publicado_em, p.agendado_para, p.criado_em)',
            'publicado_em'      => 'COALESCE(p.publicado_em, p.agendado_para, p.criado_em)',
            'curtidas'          => 'p.curtidas',
            'comentarios'       => 'p.comentarios_count',
            'comentarios_count' => 'p.comentarios_count',
            'tipo'              => 'p.tipo',
            'status'            => 'p.status',
            'legenda'           => 'p.legenda',
            'id'                => 'p.id',
        ];

        $sortCol = $allowedSort[$sort] ?? 'COALESCE(p.publicado_em, p.agendado_para, p.criado_em)';
        $direction = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';

        $where = ['p.account_id = :account_id'];
        $params = [':account_id' => $accountId];

        $busca = trim((string) ($filters['busca'] ?? ''));
        if ($busca !== '') {
            $where[] = '(p.legenda LIKE :busca1 OR bp.titulo LIKE :busca2)';
            $params[':busca1'] = '%' . $busca . '%';
            $params[':busca2'] = '%' . $busca . '%';
        }

        $tipo = trim((string) ($filters['tipo'] ?? ''));
        if ($tipo !== '' && in_array($tipo, ['imagem', 'carrossel', 'reels', 'story'], true)) {
            $where[] = 'p.tipo = :tipo';
            $params[':tipo'] = $tipo;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['publicado', 'agendado', 'rascunho', 'publicando', 'erro', 'cancelado'], true)) {
            $where[] = 'p.status = :status';
            $params[':status'] = $status;
        }

        $origem = trim((string) ($filters['origem'] ?? ''));
        if ($origem === 'blog') {
            $where[] = 'p.post_blog_id IS NOT NULL';
        } elseif ($origem === 'local') {
            $where[] = "p.origin = 'local' AND p.post_blog_id IS NULL";
        } elseif ($origem === 'instagram') {
            $where[] = "p.origin = 'instagram'";
        }

        $periodoPost = trim((string) ($filters['periodo_post'] ?? ''));
        if ($periodoPost === 'hoje') {
            $where[] = 'DATE(COALESCE(p.publicado_em, p.agendado_para, p.criado_em)) = CURDATE()';
        } elseif ($periodoPost === '7d') {
            $where[] = 'COALESCE(p.publicado_em, p.agendado_para, p.criado_em) >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        } elseif ($periodoPost === '30d') {
            $where[] = 'COALESCE(p.publicado_em, p.agendado_para, p.criado_em) >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        } elseif ($periodoPost === 'mes') {
            $where[] = 'YEAR(COALESCE(p.publicado_em, p.agendado_para, p.criado_em)) = YEAR(CURDATE()) AND MONTH(COALESCE(p.publicado_em, p.agendado_para, p.criado_em)) = MONTH(CURDATE())';
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM instagram_posts p LEFT JOIN posts bp ON bp.id = p.post_blog_id WHERE {$whereSql}");
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
                  LEFT JOIN posts bp ON bp.id = p.post_blog_id
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

    /**
     * Retorna a distribuição de posts publicados por tipo no período selecionado.
     * Considera somente publicações efetivamente publicadas, excluindo rascunhos e agendamentos.
     *
     * @return array{total: int, items: array<string, array{label: string, count: int, pct: float, color: string}>}
     */
    public function getContentTypeDistribution(int $accountId, ?string $startDate = null, ?string $endDate = null): array
    {
        $sql = "SELECT tipo, COUNT(*) AS total
                  FROM instagram_posts
                 WHERE account_id = :account_id
                   AND status = 'publicado'";
        $params = [':account_id' => $accountId];

        if ($startDate !== null && $endDate !== null) {
            $sql .= " AND publicado_em >= :start AND publicado_em <= :end";
            $params[':start'] = $startDate . ' 00:00:00';
            $params[':end']   = $endDate . ' 23:59:59';
        }

        $sql .= " GROUP BY tipo";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        if (isset($params[':start'], $params[':end'])) {
            $stmt->bindValue(':start', (string) $params[':start']);
            $stmt->bindValue(':end', (string) $params[':end']);
        }
        $stmt->execute();
        /** @var list<array<string,mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $types = [
            'imagem'    => ['label' => 'Imagens',      'count' => 0, 'pct' => 0.0, 'color' => '#f43f5e'],
            'reels'     => ['label' => 'Vídeos/Reels', 'count' => 0, 'pct' => 0.0, 'color' => '#a855f7'],
            'carrossel' => ['label' => 'Carrossel',    'count' => 0, 'pct' => 0.0, 'color' => '#06b6d4'],
            'story'     => ['label' => 'Stories',      'count' => 0, 'pct' => 0.0, 'color' => '#f59e0b'],
        ];

        $grandTotal = 0;
        foreach ($rows as $r) {
            $t = (string) ($r['tipo'] ?? 'imagem');
            $c = (int) ($r['total'] ?? 0);
            $grandTotal += $c;
            if (isset($types[$t])) {
                $types[$t]['count'] += $c;
            } else {
                $types['imagem']['count'] += $c;
            }
        }

        if ($grandTotal > 0) {
            foreach ($types as $k => $v) {
                $types[$k]['pct'] = round(($v['count'] / $grandTotal) * 100, 1);
            }
        }

        return [
            'total' => $grandTotal,
            'items' => $types,
        ];
    }

    /**
     * Retorna a data do primeiro post publicado da conta (YYYY-MM-DD),
     * ou fallback para 1 ano atrás caso não haja publicações.
     */
    public function getFirstPublishedPostDate(int $accountId): string
    {
        $stmt = $this->pdo->prepare(
            "SELECT MIN(publicado_em) AS primeiro_post
               FROM instagram_posts
              WHERE account_id = :account_id
                AND status = 'publicado'"
        );
        $stmt->execute([':account_id' => $accountId]);
        $val = $stmt->fetchColumn();

        if ($val !== false && !empty($val) && is_string($val)) {
            $ts = strtotime($val);
            if ($ts !== false) {
                return date('Y-m-d', $ts);
            }
        }

        return date('Y-m-d', strtotime('-1 year'));
    }

    /**
     * Consulta bloco de alcance em cache local (instagram_reach_blocks).
     * Um bloco encerrado há mais de 2 dias é considerado imutável.
     *
     * @return array{id: int, alcance: int, consultado_em: string, is_fresh: bool}|null
     */
    public function getReachBlock(int $accountId, string $startDate, string $endDate): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, alcance, consultado_em
               FROM instagram_reach_blocks
              WHERE account_id = :account_id
                AND data_inicio = :start
                AND data_fim = :end
              LIMIT 1"
        );
        $stmt->execute([
            ':account_id' => $accountId,
            ':start'      => $startDate,
            ':end'        => $endDate,
        ]);
        /** @var array<string,mixed>|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $twoDaysAgo = date('Y-m-d', strtotime('-2 days'));
        $isClosed = ($endDate < $twoDaysAgo);
        $consultadoTs = strtotime((string) $row['consultado_em']) ?: 0;
        $isFresh = $isClosed || ((time() - $consultadoTs) < 86400);

        return [
            'id'            => (int) $row['id'],
            'alcance'       => (int) $row['alcance'],
            'consultado_em' => (string) $row['consultado_em'],
            'is_fresh'      => $isFresh,
        ];
    }

    /**
     * Persiste ou atualiza bloco de alcance no cache local (instagram_reach_blocks).
     */
    public function saveReachBlock(int $accountId, string $startDate, string $endDate, int $alcance): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO instagram_reach_blocks (account_id, data_inicio, data_fim, alcance, consultado_em)
             VALUES (:account_id, :start, :end, :alcance, NOW())
             ON DUPLICATE KEY UPDATE
                 alcance = VALUES(alcance),
                 consultado_em = NOW()"
        );
        $stmt->execute([
            ':account_id' => $accountId,
            ':start'      => $startDate,
            ':end'        => $endDate,
            ':alcance'    => max(0, $alcance),
        ]);
    }

    /**
     * Retorna a performance da conta dividida pelas faixas adaptativas (FEAT-010 2.14.0):
     * - Até 14 dias: dia a dia (1 dia por ponto)
     * - 15 a 45 dias: a cada 3 dias
     * - 46 a 180 dias: semanal (7 dias por ponto)
     * - Acima de 180 dias: blocos de 28 dias
     *
     * Cada ponto traz curtidas reais e alcance consultado em instagram_reach_blocks.
     *
     * @return list<array{data: string, data_inicio: string, data_fim: string, label: string, tip: string, seguidores: int|null, curtidas: int, alcance: int|null, has_post: bool, is_fresh: bool}>
     */
    public function getDailyPerformance(int $accountId, string $startDate, string $endDate, int $currentFollowers, ?int $totalAlcance): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DATE(publicado_em) AS dia, SUM(curtidas) AS curtidas
               FROM instagram_posts
              WHERE account_id = :account_id
                AND status = 'publicado'
                AND publicado_em >= :start AND publicado_em <= :end
              GROUP BY DATE(publicado_em)"
        );
        $stmt->execute([
            ':account_id' => $accountId,
            ':start'      => $startDate . ' 00:00:00',
            ':end'        => $endDate . ' 23:59:59',
        ]);
        /** @var list<array<string,mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $likesByDay = [];
        foreach ($rows as $row) {
            $likesByDay[(string) $row['dia']] = (int) $row['curtidas'];
        }

        $startTs = strtotime($startDate) ?: time();
        $endTs   = strtotime($endDate) ?: time();
        if ($startTs > $endTs) {
            [$startTs, $endTs] = [$endTs, $startTs];
        }
        $daysCount = max(1, (int) round(($endTs - $startTs) / 86400) + 1);

        $step = match (true) {
            $daysCount <= 14 => 1,
            $daysCount <= 45 => 3,
            $daysCount <= 180 => 7,
            default => 28,
        };

        $points = [];
        $curStartTs = $startTs;

        while ($curStartTs <= $endTs) {
            $curEndTs = min($endTs, $curStartTs + (($step - 1) * 86400));
            $startYmd = date('Y-m-d', $curStartTs);
            $endYmd   = date('Y-m-d', $curEndTs);

            if ($startYmd === $endYmd) {
                $label = date('d/m', $curStartTs);
                $tip   = $label;
            } else {
                $startLbl = date('d/m', $curStartTs);
                $endLbl   = date('d/m', $curEndTs);
                $label    = $startLbl . '–' . $endLbl;
                $tip      = $startYmd . ' a ' . $endYmd;
            }

            $chunkLikes = 0;
            $chunkHasPost = false;
            $probeTs = $curStartTs;
            while ($probeTs <= $curEndTs) {
                $probeYmd = date('Y-m-d', $probeTs);
                if (isset($likesByDay[$probeYmd])) {
                    $chunkLikes += $likesByDay[$probeYmd];
                    $chunkHasPost = true;
                }
                $probeTs += 86400;
            }

            $cachedBlock = $this->getReachBlock($accountId, $startYmd, $endYmd);
            $reachVal = $cachedBlock !== null ? $cachedBlock['alcance'] : null;
            $isFresh  = $cachedBlock !== null ? $cachedBlock['is_fresh'] : false;

            $points[] = [
                'data'        => $startYmd,
                'data_inicio' => $startYmd,
                'data_fim'    => $endYmd,
                'label'       => $label,
                'tip'         => $tip,
                'seguidores'  => null,
                'curtidas'    => $chunkLikes,
                'alcance'     => $reachVal,
                'has_post'    => $chunkHasPost,
                'is_fresh'    => $isFresh,
            ];

            $curStartTs = $curEndTs + 86400;
        }

        return $points;
    }

    /**
     * Constrói as séries históricas reais para os sparklines dos 4 KPIs e calcula
     * os comparativos com snapshots anteriores armazenados em instagram_insights_cache.
     *
     * @param array<string,mixed>      $account
     * @param array<string,mixed>|null $currentInsights
     * @return array{
     *   deltas: array{followers: int|null, follows: int|null, media: int|null, alcance_pct: int|null},
     *   series: array{followers: list<int>, follows: list<int>, media: list<int>, alcance: list<int>}
     * }
     */
    public function getKpiHistoryAndComparisons(
        int $accountId,
        string $period,
        string $startDate,
        string $endDate,
        array $account,
        ?array $currentInsights,
    ): array {
        $currentFollowers = (int) ($account['followers_count'] ?? 0);
        $currentFollows   = (int) ($account['follows_count'] ?? 0);
        $currentMedia     = (int) ($account['media_count'] ?? 0);
        $currentAlcance   = ($currentInsights !== null && isset($currentInsights['alcance'])) ? (int) $currentInsights['alcance'] : null;
        $refDate          = (string) ($currentInsights['data_referencia'] ?? date('Y-m-d'));

        $stmt = $this->pdo->prepare(
            "SELECT periodo, data_referencia, alcance, seguidores, payload_raw
               FROM instagram_insights_cache
              WHERE account_id = :account_id
              ORDER BY data_referencia ASC, id ASC"
        );
        $stmt->execute([':account_id' => $accountId]);
        /** @var list<array<string,mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $followersByDate = [];
        $followsByDate   = [];
        $mediaByDate     = [];
        $alcanceByDate   = [];

        $prevFollowers = null;
        $prevFollows   = null;
        $prevMedia     = null;
        $prevAlcance   = null;

        foreach ($rows as $row) {
            $d   = (string) ($row['data_referencia'] ?? '');
            $p   = (string) ($row['periodo'] ?? '');
            $seg = (int) ($row['seguidores'] ?? 0);
            $alc = (int) ($row['alcance'] ?? 0);

            if ($seg > 0) {
                $followersByDate[$d] = $seg;
                if ($d < $refDate) {
                    $prevFollowers = $seg;
                }
            }

            if ($p === $period && $alc >= 0) {
                $alcanceByDate[$d] = $alc;
                if ($d < $refDate && $alc > 0) {
                    $prevAlcance = $alc;
                }
            }

            $raw = isset($row['payload_raw']) && is_string($row['payload_raw'])
                ? json_decode($row['payload_raw'], true)
                : null;
            if (is_array($raw) && isset($raw['_account_snapshot']) && is_array($raw['_account_snapshot'])) {
                $snap = $raw['_account_snapshot'];
                if (isset($snap['follows_count']) && (int) $snap['follows_count'] > 0) {
                    $followsByDate[$d] = (int) $snap['follows_count'];
                    if ($d < $refDate) {
                        $prevFollows = (int) $snap['follows_count'];
                    }
                }
                if (isset($snap['media_count']) && (int) $snap['media_count'] > 0) {
                    $mediaByDate[$d] = (int) $snap['media_count'];
                    if ($d < $refDate) {
                        $prevMedia = (int) $snap['media_count'];
                    }
                }
            }
        }

        $today = date('Y-m-d');
        if ($currentFollowers > 0) {
            $followersByDate[$today] = $currentFollowers;
        }
        if ($currentFollows > 0) {
            $followsByDate[$today] = $currentFollows;
        }
        if ($currentMedia > 0) {
            $mediaByDate[$today] = $currentMedia;
        }
        if ($currentAlcance !== null) {
            $alcanceByDate[$today] = $currentAlcance;
        }

        // Série real de mídias publicadas acumuladas por dia no intervalo
        $stmtMedia = $this->pdo->prepare(
            "SELECT DATE(publicado_em) AS dia, COUNT(*) AS qtd
               FROM instagram_posts
              WHERE account_id = :account_id
                AND status = 'publicado'
                AND publicado_em IS NOT NULL
              GROUP BY DATE(publicado_em)
              ORDER BY dia ASC"
        );
        $stmtMedia->execute([':account_id' => $accountId]);
        /** @var list<array<string,mixed>> $mediaRows */
        $mediaRows = $stmtMedia->fetchAll(PDO::FETCH_ASSOC);

        $mediaSeries = [];
        if ($mediaRows !== []) {
            $cum = 0;
            $byDayCum = [];
            foreach ($mediaRows as $mr) {
                $cum += (int) ($mr['qtd'] ?? 0);
                $byDayCum[(string) $mr['dia']] = $cum;
            }
            $startTs   = strtotime($startDate) ?: time();
            $endTs     = strtotime($endDate) ?: time();
            $daysCount = max(1, (int) round(($endTs - $startTs) / 86400) + 1);
            $running   = 0;
            foreach ($byDayCum as $d => $c) {
                if ($d < $startDate) {
                    $running = $c;
                }
            }
            for ($i = 0; $i < $daysCount; $i++) {
                $ymd = date('Y-m-d', $startTs + ($i * 86400));
                if (isset($byDayCum[$ymd])) {
                    $running = $byDayCum[$ymd];
                }
                $mediaSeries[] = $running;
            }
        } else {
            $mediaSeries = array_values($mediaByDate);
        }

        $deltaFollowers = null;
        if ($prevFollowers !== null && $currentFollowers > 0) {
            $deltaFollowers = $currentFollowers - $prevFollowers;
        } elseif ($currentInsights !== null && isset($currentInsights['variacao_seguidores']) && (int) $currentInsights['variacao_seguidores'] !== 0) {
            $deltaFollowers = (int) $currentInsights['variacao_seguidores'];
        }

        $deltaFollows = ($prevFollows !== null && $currentFollows > 0)
            ? ($currentFollows - $prevFollows)
            : null;

        $deltaMedia = ($prevMedia !== null && $currentMedia > 0)
            ? ($currentMedia - $prevMedia)
            : null;

        // $prevAlcance só é atribuído quando > 0
        $deltaAlcancePct = ($prevAlcance !== null && $currentAlcance !== null)
            ? (int) round((($currentAlcance - $prevAlcance) / $prevAlcance) * 100)
            : null;

        return [
            'deltas' => [
                'followers'   => $deltaFollowers,
                'follows'     => $deltaFollows,
                'media'       => $deltaMedia,
                'alcance_pct' => $deltaAlcancePct,
            ],
            'series' => [
                'followers' => array_values($followersByDate),
                'follows'   => array_values($followsByDate),
                'media'     => $mediaSeries,
                'alcance'   => array_values($alcanceByDate),
            ],
        ];
    }
}
