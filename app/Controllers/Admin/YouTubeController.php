<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\YouTubeVideoRepository;
use App\Services\YouTube\YouTubeApiService;
use App\Services\YouTube\YouTubeOAuthService;
use App\Services\YouTube\YouTubeRadarService;
use App\Services\YouTube\YouTubeSyncRules;
use App\Services\YouTube\YouTubeVideoRules;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Session;
use App\Support\View;
use RuntimeException;

final class YouTubeController
{
    public function index(): void
    {
        $oauth = new YouTubeOAuthService();
        $repo = $this->repo();
        $account = $repo->findActiveAccount();

        if ($account === null) {
            View::render('admin/youtube/index', [
                'title' => 'YouTube',
                'account' => null,
                'videos_paged' => ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1],
                'filters' => ['busca' => '', 'tipo' => '', 'status' => '', 'origem' => ''],
                'sort' => '',
                'dir' => 'desc',
                'period' => '7d',
                'start' => date('Y-m-d', strtotime('-6 days')),
                'end' => date('Y-m-d'),
                'content_types' => ['total' => 0, 'items' => []],
                'daily_performance' => [],
                'kpi_meta' => [
                    'deltas' => ['subscribers' => null, 'views' => null, 'videos' => null, 'engagement' => null],
                    'aggregates' => ['views' => 0, 'likes' => 0, 'comments' => 0, 'total_published' => 0],
                ],
                'oauth_configured' => $oauth->isConfigured(),
                'connected' => false,
                'csrf_token' => Csrf::token(),
                'notice' => Session::get('youtube_notice'),
                'error' => Session::get('youtube_error'),
            ]);
            Session::forget('youtube_notice');
            Session::forget('youtube_error');
            return;
        }

        $accountId = (int) $account['id'];

        // Filtro de período e datas
        $periodKey = trim((string) ($_GET['period'] ?? '7d'));
        $startParam = trim((string) ($_GET['start'] ?? ''));
        $endParam = trim((string) ($_GET['end'] ?? ''));
        $periodInfo = YouTubeSyncRules::period($periodKey, $startParam ?: null, $endParam ?: null);
        $period = $periodInfo['key'];
        $start = $periodInfo['start'];
        $end = $periodInfo['end'];

        // Filtros e ordenação da lista
        $filters = [
            'busca' => trim((string) ($_GET['busca'] ?? '')),
            'tipo' => trim((string) ($_GET['tipo'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'origem' => trim((string) ($_GET['origem'] ?? '')),
            'privacidade' => trim((string) ($_GET['privacidade'] ?? '')),
        ];
        // Vazio = ordem padrão do repositório (publicados, agendados, resto).
        $sort = trim((string) ($_GET['sort'] ?? ''));
        $dir = strtolower(trim((string) ($_GET['dir'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $rawPerPage = strtolower(trim((string) ($_GET['per_page'] ?? '8')));
        if ($rawPerPage === 'todos' || $rawPerPage === 'all') {
            $perPage = 9999;
        } else {
            $perPageInt = (int) $rawPerPage;
            $perPage = in_array($perPageInt, [8, 16, 24, 32, 48], true) ? $perPageInt : 8;
        }

        $ajax = (string) ($_GET['ajax'] ?? '');
        $videosPaged = $repo->listVideosPaged($accountId, $filters, $sort, $dir, $page, $perPage);
        $listData = [
            'account' => $account,
            'videos_paged' => $videosPaged,
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
            'period' => $period,
            'start' => $start,
            'end' => $end,
        ];

        // Só a lista (filtros, ordenação, paginação, Planilha/Cards): mesmo HTML da página, sem recarregar.
        if ($ajax === 'videos') {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => true,
                'html' => View::fragment('admin/youtube/index', $listData + ['only_list' => true]),
                'total' => (int) $videosPaged['total'],
                'page' => (int) $videosPaged['page'],
                'per_page' => (int) $videosPaged['per_page'],
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            exit;
        }

        $contentTypes = $repo->getContentTypeDistribution($accountId, $start, $end);
        $dailyPerformance = $repo->getDailyPerformance($accountId, $start, $end);
        $kpiMeta = $repo->getKpiHistoryAndComparisons($accountId, $start, $end, $account);
        $phaseViews = $repo->sumPhaseViews($accountId);
        $activeVideos = $repo->countActiveVideos($accountId);

        if ($ajax === 'metrics') {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => true,
                'period' => $period,
                'start' => $start,
                'end' => $end,
                'kpis' => $kpiMeta,
                'daily' => $dailyPerformance,
                'distribution' => $contentTypes,
                // Números que só mudam depois de "Sincronizar".
                'channel' => [
                    'subscribers' => (int) ($account['subscribers_count'] ?? 0),
                    'phase_views' => $phaseViews,
                    'active_videos' => $activeVideos,
                    'view_count' => (int) ($account['view_count'] ?? 0),
                    'synced_at' => !empty($account['synced_at']) ? date('d/m/Y H:i', (int) strtotime((string) $account['synced_at'])) : null,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            exit;
        }

        View::render('admin/youtube/index', $listData + [
            'title' => 'YouTube',
            'content_types' => $contentTypes,
            'daily_performance' => $dailyPerformance,
            'kpi_meta' => $kpiMeta,
            'phase_views' => $phaseViews,
            'active_videos' => $activeVideos,
            'oauth_configured' => $oauth->isConfigured(),
            'connected' => $oauth->connected(),
            'csrf_token' => Csrf::token(),
            'notice' => Session::get('youtube_notice'),
            'error' => Session::get('youtube_error'),
        ]);
        Session::forget('youtube_notice');
        Session::forget('youtube_error');
    }

    public function connect(): void
    {
        $this->requireCsrf();
        try {
            $state = bin2hex(random_bytes(24));
            Session::put('youtube_oauth_state', $state);
            header('Location: ' . (new YouTubeOAuthService())->authorizationUrl($state));
            exit;
        } catch (\Throwable $exception) {
            $this->redirectWithError($exception->getMessage());
        }
    }

    public function callback(): void
    {
        $expected = (string) Session::get('youtube_oauth_state', '');
        Session::forget('youtube_oauth_state');
        $state = (string) ($_GET['state'] ?? '');
        $code = (string) ($_GET['code'] ?? '');
        if ($expected === '' || $state === '' || !hash_equals($expected, $state) || $code === '') {
            $this->redirectWithError('Retorno OAuth do YouTube inválido ou expirado.');
        }

        try {
            $oauth = new YouTubeOAuthService();
            $oauth->completeAuthorization($code);
            $api = new YouTubeApiService($oauth);
            $channel = $api->myChannel();
            $accountId = $this->repo()->upsertAccount($channel, $oauth->scopes());

            // Sincronização inicial de uploads do canal
            if (!empty($channel['uploads_playlist_id'])) {
                $ids = $api->fetchUploadedVideoIds((string) $channel['uploads_playlist_id']);
                if ($ids !== []) {
                    $details = $api->fetchVideosDetails($ids);
                    foreach ($details as $vid) {
                        $this->repo()->syncRemoteVideo($accountId, $vid);
                    }
                }
            }

            Session::put('youtube_notice', 'Canal conectado e acervo sincronizado com sucesso.');
            $this->redirectIndex();
        } catch (\Throwable $exception) {
            $this->redirectWithError($exception->getMessage());
        }
    }

    public function sync(): void
    {
        $this->requireCsrf();
        try {
            $oauth = new YouTubeOAuthService();
            $api = new YouTubeApiService($oauth);
            $channel = $api->myChannel();
            $repo = $this->repo();
            $accountId = $repo->upsertAccount($channel, $oauth->scopes());

            $syncedCount = 0;
            if (!empty($channel['uploads_playlist_id'])) {
                $ids = $api->fetchUploadedVideoIds((string) $channel['uploads_playlist_id']);
                if ($ids !== []) {
                    $details = $api->fetchVideosDetails($ids);
                    foreach ($details as $vid) {
                        $repo->syncRemoteVideo($accountId, $vid);
                        $syncedCount++;
                    }
                }
            }

            // Snapshot de métricas no cache
            $repo->saveMetrics($accountId, null, [
                'subscriberCount' => $channel['subscribers_count'] ?? 0,
                'viewCount' => $channel['view_count'] ?? 0,
                'videoCount' => $channel['video_count'] ?? 0,
            ], $channel['raw'] ?? []);

            $message = sprintf('Dados do canal e acervo sincronizados (%d vídeos e Shorts).', $syncedCount);
            if ($this->wantsJson()) {
                $this->json(['ok' => true, 'message' => $message]);
            }
            Session::put('youtube_notice', $message);
            $this->redirectIndex();
        } catch (\Throwable $exception) {
            if ($this->wantsJson()) {
                $this->json(['ok' => false, 'message' => $exception->getMessage()], 502);
            }
            $this->redirectWithError($exception->getMessage());
        }
    }

    /** O botão "Sincronizar" com JavaScript pede JSON; sem JavaScript continua o redirecionamento. */
    private function wantsJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    /** @param array<string,mixed> $payload */
    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    public function create(): void
    {
        View::render('admin/youtube/create', [
            'title' => 'Novo vídeo — YouTube',
            'account' => $this->repo()->findActiveAccount(),
            'csrf_token' => Csrf::token(),
        ]);
    }

    public function store(): void
    {
        $this->requireCsrf();
        $repo = $this->repo();
        $account = $repo->findActiveAccount();
        if ($account === null) {
            $this->renderCreate('Conecte um canal antes de criar vídeos.');
            return;
        }

        $scheduled = str_replace('T', ' ', trim((string) ($_POST['scheduled_at'] ?? '')));
        if ($scheduled !== '' && strlen($scheduled) === 16) {
            $scheduled .= ':00';
        }
        $input = $_POST;
        $input['scheduled_at'] = $scheduled;
        $errors = YouTubeVideoRules::validate($input);
        if ($scheduled === '') {
            $errors['scheduled_at'] = 'Informe a data e a hora de publicação.';
        }
        if (!isset($_FILES['video']) || (int) ($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors['video'] = 'Selecione um vídeo MP4 válido.';
        }
        if ($errors !== []) {
            $this->renderCreate(implode(' ', $errors));
            return;
        }

        $video = $_FILES['video'];
        $temporaryVideo = (string) $video['tmp_name'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryVideo);
        if ($mime !== 'video/mp4') {
            $this->renderCreate('O vídeo precisa estar no formato MP4.');
            return;
        }

        $key = bin2hex(random_bytes(16));
        $relativeDirectory = 'uploads/youtube/' . $key;
        $directory = base_path('public/' . $relativeDirectory);
        if (!mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível criar o diretório do vídeo.');
        }
        $videoRelative = $relativeDirectory . '/video.mp4';
        $videoPath = base_path('public/' . $videoRelative);
        if (!move_uploaded_file($temporaryVideo, $videoPath)) {
            throw new RuntimeException('Não foi possível armazenar o vídeo enviado.');
        }

        $thumbnailRelative = null;
        try {
            $thumbnailRelative = $this->moveThumbnail($directory, $relativeDirectory);
            $repoId = $repo->create([
                'account_id' => (int) $account['id'],
                'status' => 'agendado',
                'title' => trim((string) $_POST['title']),
                'description' => trim((string) ($_POST['description'] ?? '')),
                'tags' => array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['tags'] ?? ''))))),
                'category_id' => (string) ($_POST['category_id'] ?? '28'),
                'privacy_status' => 'private',
                'made_for_kids' => isset($_POST['made_for_kids']),
                'contains_synthetic_media' => isset($_POST['contains_synthetic_media']),
                'scheduled_at' => $scheduled,
                'remote_upload_due_at' => YouTubeVideoRules::immediateUploadDueAt(),
                'post_blog_id' => null,
                'video_path' => $videoRelative,
                'thumbnail_path' => $thumbnailRelative,
                'source_size' => (int) filesize($videoPath),
                'source_sha256' => (string) hash_file('sha256', $videoPath),
                'idempotency_key' => $key,
                'criado_por' => Auth::id(),
            ]);
        } catch (\Throwable $exception) {
            @unlink($videoPath);
            if ($thumbnailRelative !== null) {
                @unlink(base_path('public/' . $thumbnailRelative));
            }
            @rmdir($directory);
            throw $exception;
        }

        header('Location: ' . url('/admin/youtube/posts/' . $repoId));
        exit;
    }

    public function show(string $id = '0'): void
    {
        $video = $this->repo()->find((int) $id);
        if ($video === null) {
            http_response_code(404);
            echo 'Vídeo não encontrado.';
            return;
        }
        View::render('admin/youtube/show', ['title' => $video['title'], 'video' => $video]);
    }

    private function moveThumbnail(string $directory, string $relativeDirectory): ?string
    {
        if (!isset($_FILES['thumbnail']) || (int) ($_FILES['thumbnail']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) $_FILES['thumbnail']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha no envio da miniatura.');
        }
        $temporary = (string) $_FILES['thumbnail']['tmp_name'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($temporary);
        $extension = match ($mime) { 'image/jpeg' => 'jpg', 'image/png' => 'png', default => null };
        if ($extension === null) {
            throw new RuntimeException('A miniatura precisa ser JPG ou PNG.');
        }
        $relative = $relativeDirectory . '/thumbnail.' . $extension;
        if (!move_uploaded_file($temporary, $directory . '/thumbnail.' . $extension)) {
            throw new RuntimeException('Não foi possível armazenar a miniatura.');
        }
        return $relative;
    }

    public function radar(): void
    {
        $radar = new YouTubeRadarService();
        $query = trim((string) ($_GET['q'] ?? 'setup gamer'));
        $type = trim((string) ($_GET['type'] ?? 'all'));
        if (!in_array($type, ['all', 'shorts', 'videos'], true)) {
            $type = 'all';
        }

        $refresh = isset($_GET['refresh']);
        $results = $radar->search($query, $type, 24, $refresh);

        $account = $this->repo()->findActiveAccount();

        View::render('admin/youtube/radar', [
            'title' => 'Radar de Conteúdo & Virais — YouTube',
            'account' => $account,
            'query' => $query,
            'type' => $type,
            'results' => $results,
            'suggested' => $radar->suggestedKeywords(),
            'csrf_token' => Csrf::token(),
        ]);
    }

    public function radarTranscript(): void
    {
        $videoId = trim((string) ($_GET['video_id'] ?? ''));
        if ($videoId === '') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Informe o ID do vídeo.']);
            exit;
        }

        $radar = new YouTubeRadarService();
        $transcript = $radar->getTranscript($videoId);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($transcript, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function renderCreate(string $error): void
    {
        View::render('admin/youtube/create', ['title' => 'Novo vídeo — YouTube', 'account' => $this->repo()->findActiveAccount(), 'csrf_token' => Csrf::token(), 'error' => $error, 'old' => $_POST]);
    }

    private function requireCsrf(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF inválido.';
            exit;
        }
    }

    private function redirectWithError(string $message): never
    {
        Session::put('youtube_error', $message);
        $this->redirectIndex();
    }

    private function redirectIndex(): never
    {
        header('Location: ' . url('/admin/youtube'));
        exit;
    }

    private function repo(): YouTubeVideoRepository
    {
        return new YouTubeVideoRepository($GLOBALS['pdo']);
    }
}
