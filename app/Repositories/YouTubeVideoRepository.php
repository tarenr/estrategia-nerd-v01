<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

final class YouTubeVideoRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array<string,mixed>|null */
    public function findActiveAccount(): ?array
    {
        $statement = $this->pdo->query('SELECT * FROM youtube_accounts WHERE ativo=1 ORDER BY id LIMIT 1');
        $row = $statement !== false ? $statement->fetch(PDO::FETCH_ASSOC) : false;
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $channel @param list<string> $scopes */
    public function upsertAccount(array $channel, array $scopes): int
    {
        $publishedAt = null;
        if (!empty($channel['published_at'])) {
            try {
                $publishedAt = (new \DateTimeImmutable((string) $channel['published_at']))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                $publishedAt = null;
            }
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO youtube_accounts(channel_id,title,description,handle,custom_url,thumbnail_url,published_at,uploads_playlist_id,scopes,subscribers_count,video_count,view_count,synced_at,ativo)
             VALUES(:channel_id,:title,:description,:handle,:custom_url,:thumbnail_url,:published_at,:uploads_playlist_id,:scopes,:subscribers,:videos,:views,NOW(),1)
             ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description),handle=VALUES(handle),custom_url=VALUES(custom_url),thumbnail_url=VALUES(thumbnail_url),published_at=COALESCE(VALUES(published_at),published_at),uploads_playlist_id=COALESCE(VALUES(uploads_playlist_id),uploads_playlist_id),scopes=VALUES(scopes),subscribers_count=VALUES(subscribers_count),video_count=VALUES(video_count),view_count=VALUES(view_count),synced_at=NOW(),ativo=1,id=LAST_INSERT_ID(id)'
        );
        $statement->execute([
            ':channel_id' => (string) $channel['channel_id'],
            ':title' => (string) $channel['title'],
            ':description' => $channel['description'] ?? null,
            ':handle' => $channel['handle'] ?? null,
            ':custom_url' => $channel['custom_url'] ?? null,
            ':thumbnail_url' => $channel['thumbnail_url'] ?? null,
            ':published_at' => $publishedAt,
            ':uploads_playlist_id' => $channel['uploads_playlist_id'] ?? null,
            ':scopes' => json_encode(array_values($scopes), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ':subscribers' => (int) ($channel['subscribers_count'] ?? 0),
            ':videos' => (int) ($channel['video_count'] ?? 0),
            ':views' => (int) ($channel['view_count'] ?? 0),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string,mixed> $video */
    public function syncRemoteVideo(int $accountId, array $video): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO youtube_videos (
                account_id, origin, content_kind, status, publish_phase,
                title, description, duration_seconds, privacy_status,
                made_for_kids, published_at, youtube_video_id,
                permalink, thumbnail_url, views_count, likes_count,
                comments_count, last_synced_at, idempotency_key
            ) VALUES (
                :account_id, "youtube", :content_kind, "publicado", "confirmed",
                :title, :description, :duration_seconds, :privacy_status,
                :made_for_kids, :published_at, :youtube_video_id,
                :permalink, :thumbnail_url, :views_count, :likes_count,
                :comments_count, NOW(), :idempotency_key
            ) ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                description = VALUES(description),
                content_kind = VALUES(content_kind),
                duration_seconds = VALUES(duration_seconds),
                privacy_status = VALUES(privacy_status),
                made_for_kids = VALUES(made_for_kids),
                published_at = COALESCE(VALUES(published_at), published_at),
                permalink = VALUES(permalink),
                thumbnail_url = VALUES(thumbnail_url),
                views_count = VALUES(views_count),
                likes_count = VALUES(likes_count),
                comments_count = VALUES(comments_count),
                last_synced_at = NOW()'
        );
        $statement->execute([
            ':account_id' => $accountId,
            ':content_kind' => (string) ($video['content_kind'] ?? 'video'),
            ':title' => mb_substr((string) ($video['title'] ?? ''), 0, 100),
            ':description' => (string) ($video['description'] ?? ''),
            ':duration_seconds' => (int) ($video['duration_seconds'] ?? 0),
            ':privacy_status' => (string) ($video['privacy_status'] ?? 'private'),
            ':made_for_kids' => !empty($video['made_for_kids']) ? 1 : 0,
            ':published_at' => !empty($video['published_at']) ? (string) $video['published_at'] : null,
            ':youtube_video_id' => (string) ($video['youtube_video_id'] ?? ''),
            ':permalink' => $video['permalink'] ?? null,
            ':thumbnail_url' => $video['thumbnail_url'] ?? null,
            ':views_count' => (int) ($video['views_count'] ?? 0),
            ':likes_count' => (int) ($video['likes_count'] ?? 0),
            ':comments_count' => (int) ($video['comments_count'] ?? 0),
            ':idempotency_key' => (string) ($video['idempotency_key'] ?? ('youtube:' . ($video['youtube_video_id'] ?? uniqid()))),
        ]);
    }

    /**
     * @param array<string,string> $filters
     * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
     */
    public function listVideosPaged(
        int $accountId,
        array $filters = [],
        string $sort = 'published_at',
        string $dir = 'desc',
        int $page = 1,
        int $perPage = 10
    ): array {
        // Agendado usa a data marcada; o published_at dele é só a hora do envio ao YouTube.
        $dateExpr = "COALESCE(CASE WHEN status IN ('agendado','agendado_remoto') THEN scheduled_at END, published_at, scheduled_at, criado_em)";
        $allowedSort = [
            'data'            => $dateExpr,
            'published_at'    => $dateExpr,
            'views'           => 'views_count',
            'views_count'     => 'views_count',
            'likes'           => 'likes_count',
            'likes_count'     => 'likes_count',
            'comments'        => 'comments_count',
            'comments_count'  => 'comments_count',
            'duration'        => 'duration_seconds',
            'status'          => 'status',
            'title'           => 'title',
            'content_kind'    => 'content_kind',
            'tipo'            => 'content_kind',
            'id'              => 'id',
        ];

        $direction = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';
        // Ordem padrão: publicados, depois agendados (fila local ou no YouTube), depois o resto; cada grupo do mais recente ao mais antigo.
        $orderSql = isset($allowedSort[$sort])
            ? "{$allowedSort[$sort]} {$direction}"
            : "CASE WHEN status = 'publicado' THEN 0 WHEN status IN ('agendado','agendado_remoto') THEN 1 ELSE 2 END ASC, {$dateExpr} DESC";

        $where = ['account_id = :account_id'];
        $params = [':account_id' => $accountId];

        $busca = trim((string) ($filters['busca'] ?? ''));
        if ($busca !== '') {
            $where[] = '(title LIKE :busca1 OR description LIKE :busca2)';
            $params[':busca1'] = '%' . $busca . '%';
            $params[':busca2'] = '%' . $busca . '%';
        }

        $tipo = trim((string) ($filters['tipo'] ?? ''));
        if ($tipo !== '' && in_array($tipo, ['video', 'short'], true)) {
            $where[] = 'content_kind = :tipo';
            $params[':tipo'] = $tipo;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['publicado', 'agendado', 'rascunho', 'enviando', 'processando', 'agendado_remoto', 'erro', 'resultado_incerto', 'cancelado'], true)) {
            if ($status === 'agendado') {
                // "Agendado" cobre a fila local e o vídeo já enviado ao YouTube com hora marcada.
                $where[] = "status IN ('agendado','agendado_remoto')";
            } else {
                $where[] = 'status = :status';
                $params[':status'] = $status;
            }
        } else {
            // Cancelados (grade antiga) só aparecem quando o filtro Status pede "Cancelado".
            $where[] = "status <> 'cancelado'";
        }

        $origem = trim((string) ($filters['origem'] ?? ''));
        if ($origem !== '' && in_array($origem, ['local', 'youtube'], true)) {
            $where[] = 'origin = :origem';
            $params[':origem'] = $origem;
        }

        // Os vídeos antigos do canal ficaram não listados em 31/10/2026; por padrão a lista mostra só a fase nova.
        $privacidade = trim((string) ($filters['privacidade'] ?? ''));
        if ($privacidade === 'unlisted') {
            $where[] = "privacy_status = 'unlisted'";
        } elseif ($privacidade !== 'todos') {
            $where[] = "privacy_status <> 'unlisted'";
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM youtube_videos WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = max(1, $page);
        $perPage = max(1, min(9999, $perPage));
        $pages = max(1, (int) ceil($total / $perPage));
        if ($page > $pages && $total > 0) {
            $page = $pages;
        }
        $offset = ($page - 1) * $perPage;

        $selectSql = "SELECT * FROM youtube_videos WHERE {$whereSql} ORDER BY {$orderSql}, id DESC LIMIT :offset, :limit";
        $stmt = $this->pdo->prepare($selectSql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    /** Soma das visualizações gravadas na última sincronização, sem os vídeos não listados (fase antiga). */
    public function sumPhaseViews(int $accountId): int
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(views_count),0) FROM youtube_videos WHERE account_id = :account_id AND privacy_status <> 'unlisted'");
        $stmt->execute([':account_id' => $accountId]);
        return (int) $stmt->fetchColumn();
    }

    /** Vídeos da fase nova que contam para o canal: sem cancelados e sem não listados. */
    public function countActiveVideos(int $accountId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM youtube_videos WHERE account_id = :account_id AND status <> 'cancelado' AND privacy_status <> 'unlisted'");
        $stmt->execute([':account_id' => $accountId]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array{total:int,items:array<string,array{count:int,percentage:float}>} */
    public function getContentTypeDistribution(int $accountId, ?string $startDate = null, ?string $endDate = null): array
    {
        $sql = "SELECT content_kind, COUNT(*) AS total
                  FROM youtube_videos
                 WHERE account_id = :account_id
                   AND status = 'publicado'
                   AND privacy_status <> 'unlisted'";
        $params = [':account_id' => $accountId];

        if ($startDate !== null && $endDate !== null) {
            $sql .= " AND published_at >= :start AND published_at <= :end";
            $params[':start'] = $startDate . ' 00:00:00';
            $params[':end'] = $endDate . ' 23:59:59';
        }

        $sql .= " GROUP BY content_kind";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $counts = ['video' => 0, 'short' => 0];
        $total = 0;
        foreach ($rows as $row) {
            $kind = (string) ($row['content_kind'] ?? 'video');
            $cnt = (int) ($row['total'] ?? 0);
            if (isset($counts[$kind])) {
                $counts[$kind] = $cnt;
            }
            $total += $cnt;
        }

        $items = [];
        foreach ($counts as $kind => $cnt) {
            $items[$kind] = [
                'count' => $cnt,
                'percentage' => $total > 0 ? round(($cnt / $total) * 100, 1) : 0.0,
            ];
        }

        return ['total' => $total, 'items' => $items];
    }

    /** @return list<array{date:string,views:int,likes:int,comments:int}> */
    public function getDailyPerformance(int $accountId, string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DATE(published_at) AS dia, SUM(views_count) AS total_views, SUM(likes_count) AS total_likes, SUM(comments_count) AS total_comments
               FROM youtube_videos
              WHERE account_id = :account_id
                AND status = 'publicado'
                AND privacy_status <> 'unlisted'
                AND published_at >= :start AND published_at <= :end
              GROUP BY DATE(published_at)"
        );
        $stmt->execute([
            ':account_id' => $accountId,
            ':start' => $startDate . ' 00:00:00',
            ':end' => $endDate . ' 23:59:59',
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $byDay = [];
        foreach ($rows as $r) {
            $byDay[(string) $r['dia']] = [
                'views' => (int) ($r['total_views'] ?? 0),
                'likes' => (int) ($r['total_likes'] ?? 0),
                'comments' => (int) ($r['total_comments'] ?? 0),
            ];
        }

        $result = [];
        $start = new \DateTimeImmutable($startDate);
        $end = new \DateTimeImmutable($endDate);
        for ($curr = $start; $curr <= $end; $curr = $curr->modify('+1 day')) {
            $ymd = $curr->format('Y-m-d');
            $result[] = [
                'date' => $ymd,
                'views' => $byDay[$ymd]['views'] ?? 0,
                'likes' => $byDay[$ymd]['likes'] ?? 0,
                'comments' => $byDay[$ymd]['comments'] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $account
     * @return array{deltas:array{subscribers:?int,views:?int,videos:?int,engagement:?int},aggregates:array{views:int,likes:int,comments:int,total_published:int}}
     */
    public function getKpiHistoryAndComparisons(int $accountId, string $startDate, string $endDate, array $account): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS total_pub, SUM(views_count) AS views, SUM(likes_count) AS likes, SUM(comments_count) AS comments
               FROM youtube_videos
              WHERE account_id = :account_id
                AND status = 'publicado'
                AND privacy_status <> 'unlisted'
                AND published_at >= :start AND published_at <= :end"
        );
        $stmt->execute([
            ':account_id' => $accountId,
            ':start' => $startDate . ' 00:00:00',
            ':end' => $endDate . ' 23:59:59',
        ]);
        $agg = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $viewsPeriod = (int) ($agg['views'] ?? 0);
        $likesPeriod = (int) ($agg['likes'] ?? 0);
        $commentsPeriod = (int) ($agg['comments'] ?? 0);
        $pubPeriod = (int) ($agg['total_pub'] ?? 0);

        $days = max(1, (int) ((strtotime($endDate) - strtotime($startDate)) / 86400) + 1);
        $prevStart = date('Y-m-d', strtotime("-{$days} days", strtotime($startDate)));
        $prevEnd = date('Y-m-d', strtotime('-1 day', strtotime($startDate)));

        $prevStmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS total_pub, SUM(views_count) AS views, SUM(likes_count) AS likes, SUM(comments_count) AS comments
               FROM youtube_videos
              WHERE account_id = :account_id
                AND status = 'publicado'
                AND privacy_status <> 'unlisted'
                AND published_at >= :start AND published_at <= :end"
        );
        $prevStmt->execute([
            ':account_id' => $accountId,
            ':start' => $prevStart . ' 00:00:00',
            ':end' => $prevEnd . ' 23:59:59',
        ]);
        $prevAgg = $prevStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $prevViews = (int) ($prevAgg['views'] ?? 0);
        $prevPub = (int) ($prevAgg['total_pub'] ?? 0);
        $prevEng = (int) ($prevAgg['likes'] ?? 0) + (int) ($prevAgg['comments'] ?? 0);
        $currEng = $likesPeriod + $commentsPeriod;

        $subDelta = null;
        $snapStmt = $this->pdo->prepare(
            "SELECT subscribers FROM youtube_metrics_cache WHERE account_id = :account_id AND snapshot_at <= :start ORDER BY snapshot_at DESC LIMIT 1"
        );
        $snapStmt->execute([':account_id' => $accountId, ':start' => $startDate . ' 00:00:00']);
        $oldSub = $snapStmt->fetchColumn();
        if ($oldSub !== false && $oldSub !== null) {
            $subDelta = (int) ($account['subscribers_count'] ?? 0) - (int) $oldSub;
        }

        return [
            'deltas' => [
                'subscribers' => $subDelta,
                'views' => $viewsPeriod - $prevViews,
                'videos' => $pubPeriod - $prevPub,
                'engagement' => $currEng - $prevEng,
            ],
            'aggregates' => [
                'views' => $viewsPeriod,
                'likes' => $likesPeriod,
                'comments' => $commentsPeriod,
                'total_published' => $pubPeriod,
            ],
        ];
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO youtube_videos(account_id,status,publish_phase,title,description,tags,category_id,privacy_status,made_for_kids,contains_synthetic_media,scheduled_at,remote_upload_due_at,post_blog_id,video_path,thumbnail_path,source_size,source_sha256,idempotency_key,criado_por)
             VALUES(:account_id,:status,"idle",:title,:description,:tags,:category_id,:privacy,:kids,:synthetic,:scheduled,:due,:blog,:video,:thumbnail,:size,:sha,:key,:user)'
        );
        $statement->execute([
            ':account_id' => (int) $data['account_id'], ':status' => (string) $data['status'], ':title' => (string) $data['title'],
            ':description' => (string) ($data['description'] ?? ''), ':tags' => json_encode(array_values((array) ($data['tags'] ?? [])), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ':category_id' => (string) ($data['category_id'] ?? '28'), ':privacy' => (string) ($data['privacy_status'] ?? 'private'),
            ':kids' => !empty($data['made_for_kids']) ? 1 : 0, ':synthetic' => !empty($data['contains_synthetic_media']) ? 1 : 0,
            ':scheduled' => $data['scheduled_at'] ?: null, ':due' => $data['remote_upload_due_at'] ?: null,
            ':blog' => $data['post_blog_id'] ?: null, ':video' => (string) $data['video_path'], ':thumbnail' => $data['thumbnail_path'] ?: null,
            ':size' => (int) $data['source_size'], ':sha' => (string) $data['source_sha256'], ':key' => (string) $data['idempotency_key'],
            ':user' => $data['criado_por'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<int,array<string,mixed>> */
    public function listAll(int $limit = 100): array
    {
        $statement = $this->pdo->prepare("SELECT * FROM youtube_videos WHERE privacy_status <> 'unlisted' ORDER BY COALESCE(scheduled_at,criado_em) DESC LIMIT :limit");
        $statement->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function listForCalendar(int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $end = (new \DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            "SELECT id, title, status,
                    CASE WHEN status = 'publicado' THEN COALESCE(published_at, scheduled_at)
                         ELSE scheduled_at
                    END AS scheduled_at,
                    thumbnail_path, thumbnail_url
               FROM youtube_videos
              WHERE (status = 'publicado' AND COALESCE(published_at, scheduled_at) >= :s1 AND COALESCE(published_at, scheduled_at) < :e1)
                 OR (status IN ('agendado', 'agendado_remoto', 'enviando', 'processando', 'erro') AND scheduled_at >= :s2 AND scheduled_at < :e2)
              ORDER BY scheduled_at ASC"
        );
        $statement->execute([':s1' => $start, ':e1' => $end, ':s2' => $start, ':e2' => $end]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed> */
    public function dashboardSummary(): array
    {
        $account = $this->findActiveAccount();
        $statement = $this->pdo->query("SELECT COUNT(*) total,SUM(status IN ('agendado','agendado_remoto')) scheduled,SUM(status='publicado') published,SUM(status IN ('erro','resultado_incerto')) attention FROM youtube_videos WHERE privacy_status <> 'unlisted'");
        $counts = $statement !== false ? $statement->fetch(PDO::FETCH_ASSOC) : false;
        return ['account' => $account, 'counts' => is_array($counts) ? $counts : [], 'recent' => $this->listAll(4)];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM youtube_videos WHERE id=:id');
        $statement->execute([':id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array<int,array<string,mixed>> */
    public function dueForUpload(int $limit = 3): array
    {
        $statement = $this->pdo->prepare("SELECT * FROM youtube_videos WHERE status IN ('agendado','erro') AND publish_phase IN ('idle','failed') AND remote_upload_due_at<=NOW() ORDER BY remote_upload_due_at,id LIMIT :limit");
        $statement->bindValue(':limit', max(1, min(10, $limit)), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function claim(int $id): bool
    {
        $statement = $this->pdo->prepare("UPDATE youtube_videos SET status='enviando',publish_phase='starting_session',upload_attempts=upload_attempts+1,error_log=NULL,atualizado_em=NOW() WHERE id=:id AND status IN ('agendado','erro') AND publish_phase IN ('idle','failed')");
        $statement->execute([':id' => $id]);
        return $statement->rowCount() === 1;
    }

    public function saveSession(int $id, string $sessionUri): void
    {
        $this->pdo->prepare("UPDATE youtube_videos SET resumable_session_uri=:uri,publish_phase='uploading',status='enviando',atualizado_em=NOW() WHERE id=:id AND youtube_video_id IS NULL")
            ->execute([':uri' => $sessionUri, ':id' => $id]);
    }

    public function saveOffset(int $id, int $offset): void
    {
        $this->pdo->prepare('UPDATE youtube_videos SET upload_offset=:offset,atualizado_em=NOW() WHERE id=:id')
            ->execute([':offset' => $offset, ':id' => $id]);
    }

    public function markReceived(int $id, string $youtubeId): void
    {
        $statement = $this->pdo->prepare("UPDATE youtube_videos SET youtube_video_id=:remote,permalink=:link,status='processando',publish_phase='processing',processing_status='processing',uploaded_at=NOW(),resumable_session_uri=NULL,atualizado_em=NOW() WHERE id=:id AND youtube_video_id IS NULL");
        $statement->execute([':remote' => $youtubeId, ':link' => 'https://youtu.be/' . $youtubeId, ':id' => $id]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('O vídeo remoto não pôde ser associado com segurança.');
        }
    }

    public function updateThumbnailUrl(int $id, string $thumbnailUrl): void
    {
        $this->pdo->prepare('UPDATE youtube_videos SET thumbnail_url=:thumb,atualizado_em=NOW() WHERE id=:id')
            ->execute([':thumb' => $thumbnailUrl, ':id' => $id]);
    }

    public function updateThumbnailPath(int $id, string $thumbnailPath): void
    {
        $this->pdo->prepare('UPDATE youtube_videos SET thumbnail_path=:path,atualizado_em=NOW() WHERE id=:id')
            ->execute([':path' => $thumbnailPath, ':id' => $id]);
    }

    public function markProcessing(int $id, string $processing, string $status): void
    {
        $phase = $status === 'erro' ? 'failed' : ($status === 'publicado' || $status === 'agendado_remoto' ? 'confirmed' : 'processing');
        $published = $status === 'publicado' ? ',published_at=COALESCE(published_at,NOW())' : '';
        $this->pdo->prepare("UPDATE youtube_videos SET processing_status=:processing,status=:status,publish_phase=:phase{$published},atualizado_em=NOW() WHERE id=:id")
            ->execute([':processing' => $processing, ':status' => $status, ':phase' => $phase, ':id' => $id]);
    }

    public function markError(int $id, string $message, bool $uncertain = false): void
    {
        $status = $uncertain ? 'resultado_incerto' : 'erro';
        $phase = $uncertain ? 'unknown' : 'failed';
        $safe = mb_substr($message, 0, 4000);
        $this->pdo->prepare('UPDATE youtube_videos SET status=:status,publish_phase=:phase,error_log=:error,atualizado_em=NOW() WHERE id=:id')
            ->execute([':status' => $status, ':phase' => $phase, ':error' => $safe, ':id' => $id]);
    }

    public function addWarning(int $id, string $message): void
    {
        $safe = mb_substr($message, 0, 1000);
        $this->pdo->prepare("UPDATE youtube_videos SET error_log=CONCAT_WS('\n',NULLIF(error_log,''),:warning),atualizado_em=NOW() WHERE id=:id")
            ->execute([':warning' => $safe, ':id' => $id]);
    }

    public function markStaleAsUncertain(int $minutes = 120): int
    {
        $statement = $this->pdo->prepare("UPDATE youtube_videos SET status='resultado_incerto',publish_phase='unknown',error_log='Upload interrompido; reconciliação necessária antes de reenviar.',atualizado_em=NOW() WHERE status='enviando' AND atualizado_em<DATE_SUB(NOW(),INTERVAL :minutes MINUTE)");
        $statement->bindValue(':minutes', max(30, $minutes), PDO::PARAM_INT);
        $statement->execute();
        return $statement->rowCount();
    }

    /** @return array<int,array<string,mixed>> */
    public function pendingProcessing(int $limit = 10): array
    {
        $statement = $this->pdo->prepare("SELECT * FROM youtube_videos WHERE youtube_video_id IS NOT NULL AND status IN ('processando','agendado_remoto') ORDER BY atualizado_em LIMIT :limit");
        $statement->bindValue(':limit', max(1, min(50, $limit)), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string,mixed> $statistics */
    public function saveMetrics(int $accountId, ?int $videoId, array $statistics, array $raw): void
    {
        $this->pdo->prepare('INSERT INTO youtube_metrics_cache(account_id,video_id,snapshot_at,views,likes,comments,subscribers,payload_raw) VALUES(:account,:video,NOW(),:views,:likes,:comments,:subscribers,:raw)')
            ->execute([':account' => $accountId, ':video' => $videoId, ':views' => (int) ($statistics['viewCount'] ?? 0), ':likes' => (int) ($statistics['likeCount'] ?? 0), ':comments' => (int) ($statistics['commentCount'] ?? 0), ':subscribers' => isset($statistics['subscriberCount']) ? (int) $statistics['subscriberCount'] : null, ':raw' => json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
    }
}
