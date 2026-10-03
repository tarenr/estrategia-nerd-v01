<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Controllers/Admin/InstagramController.php
 * @project     Estrategia Nerd
 * @purpose     Controller do módulo Instagram no painel Admin (FEAT-010)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\InstagramPostRepository;
use App\Repositories\PostRepository;
use App\Services\Instagram\AudioReelGeneratorService;
use App\Services\Instagram\AudiusTrackService;
use App\Services\Instagram\BlogCrosspostService;
use App\Services\Instagram\InstagramApiService;
use App\Services\Instagram\InstagramMediaFitter;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\View;
use RuntimeException;

final class InstagramController
{
    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function index(): void
    {
        $repo    = $this->repo();
        $account = $repo->findActiveAccount();

        if ($account === null) {
            View::render('admin/instagram/index', [
                'title'   => 'Instagram',
                'account' => null,
            ]);
            return;
        }

        $accountId = (int) $account['id'];
        $scheduled = $repo->listScheduled($accountId, 20);
        $drafts    = $repo->listDrafts($accountId, 20);

        // ── Filtro de Datas e Intervalo de Insights ─────────────────────────────
        $todayYmd   = date('Y-m-d');
        $minAllowed = date('Y-m-d', strtotime('-90 days'));

        $period = trim((string) ($_GET['period'] ?? ''));
        if (!in_array($period, ['7d', '14d', '21d', '30d', 'custom'], true)) {
            $period = '7d';
        }

        $startIn = trim((string) ($_GET['start'] ?? ''));
        $endIn   = trim((string) ($_GET['end'] ?? ''));

        if ($startIn !== '' && $endIn !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startIn) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endIn)) {
            $period = 'custom';
        } else {
            $days = match ($period) {
                '14d'   => 14,
                '21d'   => 21,
                '30d'   => 30,
                default => 7,
            };
            $startIn = date('Y-m-d', strtotime("-{$days} days"));
            $endIn   = $todayYmd;
        }

        // Clamping estrito contra os limites da Meta (máx 30 dias de janela, até 90 dias atrás)
        if ($startIn < $minAllowed) {
            $startIn = $minAllowed;
        }
        if ($endIn > $todayYmd) {
            $endIn = $todayYmd;
        }
        if ($startIn > $endIn) {
            [$startIn, $endIn] = [$endIn, $startIn];
        }
        $windowDays = (int) ((strtotime($endIn) - strtotime($startIn)) / 86400);
        if ($windowDays > 30) {
            $startIn = date('Y-m-d', strtotime('-30 days', strtotime($endIn)));
        }

        // Buscar insights no cache
        $insights = $repo->getLatestInsights($accountId, $period, $startIn, $endIn);

        // Se não houver no cache, tenta buscar ao vivo na API
        if ($insights === null) {
            try {
                $api = $this->api($account);
                $apiRes = $api->getInsights(strtotime($startIn . ' 00:00:00'), strtotime($endIn . ' 23:59:59'));
                $followers = (int) ($account['followers_count'] ?? 0);
                $this->persistInsights($repo, $accountId, $period, $apiRes, $followers, $startIn, $endIn);
                $insights = $repo->getLatestInsights($accountId, $period, $startIn, $endIn);
            } catch (RuntimeException) {
                // Continua sem snapshot
            }
        }

        // ── Filtros, Ordenação e Paginação da Lista de Posts ───────────────────
        $filters = [
            'busca'        => trim((string) ($_GET['busca'] ?? '')),
            'tipo'         => trim((string) ($_GET['tipo'] ?? '')),
            'status'       => trim((string) ($_GET['status'] ?? '')),
            'origem'       => trim((string) ($_GET['origem'] ?? '')),
            'periodo_post' => trim((string) ($_GET['periodo_post'] ?? '')),
        ];

        $sort    = trim((string) ($_GET['sort'] ?? 'publicado_em'));
        $dir     = strtolower(trim((string) ($_GET['dir'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc';
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $rawPerPage = strtolower(trim((string) ($_GET['per_page'] ?? '8')));
        if ($rawPerPage === 'todos' || $rawPerPage === 'all') {
            $perPage = 9999;
        } else {
            $perPageInt = (int) $rawPerPage;
            $perPage = in_array($perPageInt, [8, 16, 24, 32, 48], true) ? $perPageInt : 8;
        }

        $postsPaged = $repo->listPostsPaged($accountId, $filters, $sort, $dir, $page, $perPage);
        $contentTypeDist = $repo->getContentTypeDistribution($accountId);
        $totalAlcance    = (int) ($insights['alcance'] ?? 154);
        $followersCount  = (int) ($account['followers_count'] ?? 91);
        $dailyPerf       = $repo->getDailyPerformance($accountId, $startIn, $endIn, $followersCount, $totalAlcance);

        if (isset($_GET['ajax']) && $_GET['ajax'] === 'metrics') {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok'                => true,
                'period'            => $period,
                'start'             => $startIn,
                'end'               => $endIn,
                'start_formatted'   => date('d/m/Y', strtotime($startIn) ?: time()),
                'end_formatted'     => date('d/m/Y', strtotime($endIn) ?: time()),
                'kpis'              => [
                    'followers'       => $followersCount,
                    'followers_count' => $followersCount,
                    'follows'         => (int) ($account['follows_count'] ?? 0),
                    'follows_count'   => (int) ($account['follows_count'] ?? 0),
                    'media'           => (int) ($account['media_count'] ?? $postsPaged['total']),
                    'media_count'     => (int) ($account['media_count'] ?? $postsPaged['total']),
                    'alcance'         => $totalAlcance,
                    'delta'           => (int) ($insights['variacao_seguidores'] ?? 0),
                ],
                'insights'          => [
                    'alcance'             => $totalAlcance,
                    'visualizacoes'       => (int) ($insights['visualizacoes'] ?? $insights['impressoes'] ?? 0),
                    'visitas_perfil'      => (int) ($insights['visitas_perfil'] ?? 0),
                    'interacoes'          => (int) ($insights['interacoes'] ?? 0),
                    'seguidores'          => $followersCount,
                    'variacao_seguidores' => (int) ($insights['variacao_seguidores'] ?? 0),
                ],
                'content_types'     => $contentTypeDist,
                'daily_performance' => $dailyPerf,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $viewData = [
            'title'             => 'Instagram',
            'account'           => $account,
            'scheduled'         => $scheduled,
            'drafts'            => $drafts,
            'posts_paged'       => $postsPaged,
            'filters'           => $filters,
            'sort'              => $sort,
            'dir'               => $dir,
            'period'            => $period,
            'start'             => $startIn,
            'end'               => $endIn,
            'insights'          => $insights,
            'content_types'     => $contentTypeDist,
            'daily_performance' => $dailyPerf,
        ];

        if (isset($_GET['_partial']) && (string) $_GET['_partial'] === '1') {
            echo View::fragment('admin/instagram/index', $viewData);
            return;
        }

        View::render('admin/instagram/index', $viewData);
    }

    // ── Sincronizar ───────────────────────────────────────────────────────────

    public function sync(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $repo    = $this->repo();
        $account = $repo->findActiveAccount();

        if ($account === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Nenhuma conta configurada.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $api     = $this->api($account);
            $profile = $api->getProfile();
            $repo->syncAccount((int) $account['id'], $profile);

            $followers = (int) ($profile['followers_count'] ?? $account['followers_count'] ?? 0);

            // Sincronizar períodos padrão de insights
            foreach (['7d', '14d', '21d', '30d'] as $p) {
                try {
                    $ins = $api->getInsights($p);
                    $days = (int) str_replace('d', '', $p);
                    $sDate = date('Y-m-d', strtotime("-{$days} days"));
                    $eDate = date('Y-m-d');
                    $this->persistInsights($repo, (int) $account['id'], $p, $ins, $followers, $sDate, $eDate);
                } catch (RuntimeException) {
                    // Falha em período individual não interrompe os outros
                }
            }

            // Sincronizar todos os posts do feed (0 = todos)
            $feedRes = $api->getMediaFeed(0);
            $postsSynced = 0;
            foreach ($feedRes['data'] as $item) {
                $tipo = match ((string) ($item['media_type'] ?? '')) {
                    'VIDEO'          => 'reels',
                    'CAROUSEL_ALBUM' => 'carrossel',
                    default          => 'imagem',
                };
                $caption = (string) ($item['caption'] ?? '');
                preg_match_all('/#\w+/u', $caption, $matches);
                $hashtagsCount = count($matches[0]);

                $publishedAt = null;
                if (!empty($item['timestamp'])) {
                    $publishedAt = date('Y-m-d H:i:s', strtotime((string) $item['timestamp']) ?: time());
                }

                $repo->upsertFromFeed([
                    'account_id'        => (int) $account['id'],
                    'tipo'              => $tipo,
                    'legenda'           => $caption,
                    'hashtags_count'    => $hashtagsCount,
                    'curtidas'          => (int) ($item['like_count'] ?? 0),
                    'comentarios_count' => (int) ($item['comments_count'] ?? 0),
                    'ig_media_id'       => (string) ($item['id'] ?? ''),
                    'permalink'         => (string) ($item['permalink'] ?? ''),
                    'publicado_em'      => $publishedAt,
                    'media_url'         => (string) ($item['media_url'] ?? ''),
                    'thumbnail_url'     => (string) ($item['thumbnail_url'] ?? ''),
                ]);
                $postsSynced++;
            }

            echo json_encode([
                'ok'           => true,
                'synced_at'    => date('d/m/Y H:i'),
                'posts_synced' => $postsSynced,
                'is_partial'   => (bool) $feedRes['is_partial'],
            ], JSON_UNESCAPED_UNICODE);
        } catch (RuntimeException $e) {
            http_response_code(502);
            $safeError = (string) preg_replace('/([?&]access_token=)[^&]+/i', '$1[REDACTED]', $e->getMessage());
            echo json_encode(['ok' => false, 'error' => $safeError], JSON_UNESCAPED_UNICODE);
        }
    }

    // ── Criar Post ────────────────────────────────────────────────────────────

    public function create(): void
    {
        $repo    = $this->repo();
        $account = $repo->findActiveAccount();

        View::render('admin/instagram/create', [
            'title'           => 'Novo Post — Instagram',
            'account'         => $account,
            'csrf_token'      => Csrf::generate(),
        ]);
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF inválido.';
            return;
        }

        $repo    = $this->repo();
        $account = $repo->findActiveAccount();

        if ($account === null) {
            http_response_code(400);
            echo 'Nenhuma conta Instagram configurada.';
            return;
        }

        $api     = $this->api($account);
        $legenda = $this->buildCaption((string) ($_POST['legenda'] ?? ''), (string) ($_POST['hashtags'] ?? ''));
        $tipo    = (string) ($_POST['tipo'] ?? 'imagem');
        $acao    = (string) ($_POST['acao'] ?? 'rascunho');

        // Validação da legenda
        $validation = $api->validateCaption($legenda);
        if (!$validation['ok']) {
            $errors = implode(' ', $validation['errors']);
            View::render('admin/instagram/create', [
                'title'      => 'Novo Post — Instagram',
                'account'    => $account,
                'csrf_token' => Csrf::generate(),
                'error'      => $errors,
                'old'        => $_POST,
            ]);
            return;
        }

        $status      = 'rascunho';
        $agendadoPara = null;

        if ($acao === 'agendar') {
            $status       = 'agendado';
            $agendadoPara = (string) ($_POST['agendado_para'] ?? '');
            if ($agendadoPara === '') {
                $agendadoPara = null;
                $status       = 'rascunho';
            }
        }

        // Valida tipo x midias antes de gravar qualquer coisa (rascunho sem midia continua permitido).
        $hasAudio  = !empty($_POST['audio_track_id']);
        $input     = $this->inspectMediaInput($_FILES, $_POST);
        $ruleError = $input['errors'] === []
            ? InstagramPostRepository::mediaRuleError($tipo, array_column($input['items'], 'kind'), $status === 'rascunho' && $acao !== 'publicar', $hasAudio)
            : null;
        $moved     = ($input['errors'] === [] && $ruleError === null) ? $this->moveUploads($input['items']) : ['items' => [], 'errors' => []];
        $failure   = $this->mediaErrorMessage(array_merge($input['errors'], $moved['errors']), $ruleError, $input['had_files']);

        if ($failure === null) {
            /** @var \PDO $pdo */
            $pdo = $GLOBALS['pdo'];
            try {
                $pdo->beginTransaction();
                $postId = $repo->create([
                    'account_id'             => (int) $account['id'],
                    'status'                 => $status,
                    'tipo'                   => !empty($_POST['audio_track_id']) ? 'reels' : $tipo,
                    'legenda'                => $legenda ?: null,
                    'hashtags_count'         => $api->countHashtags($legenda),
                    'agendado_para'          => $agendadoPara,
                    'post_blog_id'           => ($_POST['post_blog_id'] ?? '') !== '' ? (int) $_POST['post_blog_id'] : null,
                    'audio_track_id'         => !empty($_POST['audio_track_id']) ? (int) $_POST['audio_track_id'] : null,
                    'audio_start_seconds'    => (int) ($_POST['audio_start_seconds'] ?? 0),
                    'audio_duration_seconds' => (int) ($_POST['audio_duration_seconds'] ?? 0),
                    'idempotency_key'        => $this->uuid4(),
                    'origin'                 => 'local',
                    'criado_por'             => Auth::id(),
                ]);
                $this->persistMedia($repo, $postId, $moved['items'], 0);
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $this->discardMovedFiles($moved['items']);
                error_log('[InstagramController] store: ' . $e->getMessage());
                $failure = $this->mediaErrorMessage(['Não foi possível salvar o post; nada foi gravado.'], null, $input['had_files']);
            }
        }

        if ($failure !== null || !isset($postId)) {
            View::render('admin/instagram/create', [
                'title'      => 'Novo Post — Instagram',
                'account'    => $account,
                'csrf_token' => Csrf::generate(),
                'error'      => $failure,
                'old'        => $_POST,
            ]);
            return;
        }

        if (!$this->fitMediaIfRequested($postId, $tipo)) {
            header('Location: ' . url('/admin/instagram/posts/' . $postId . '/editar?ajuste=falhou'));
            exit;
        }

        if ($acao === 'publicar') {
            $this->publishNow($repo, $api, $postId, $account);
            header('Location: ' . url('/admin/instagram?published=1'));
            exit;
        }

        header('Location: ' . url('/admin/instagram?saved=1'));
        exit;
    }

    // ── Editar Post ───────────────────────────────────────────────────────────

    public function edit(string $id = '0'): void
    {
        $id   = (int) $id;
        $repo = $this->repo();
        $post = $repo->findById($id);

        if ($post === null) {
            http_response_code(404);
            echo 'Post não encontrado.';
            return;
        }

        $medias = $repo->findMediaByPostId($id);
        $audioTrack = !empty($post['audio_track_id']) ? $repo->findAudioTrack((int) $post['audio_track_id']) : null;

        View::render('admin/instagram/edit', [
            'title'       => 'Editar Post — Instagram',
            'post'        => $post,
            'medias'      => $medias,
            'audio_track' => $audioTrack,
            'account'     => $repo->findActiveAccount(),
            'csrf_token'  => Csrf::generate(),
        ]);
    }

    public function update(string $id = '0'): void
    {
        $id = (int) $id;

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF inválido.';
            return;
        }

        $repo    = $this->repo();
        $post    = $repo->findById($id);
        $account = $repo->findActiveAccount();

        if ($post === null || $account === null) {
            http_response_code(404);
            echo 'Post não encontrado.';
            return;
        }

        // Não permite editar posts já publicados ou em publicação
        if (in_array((string) ($post['status'] ?? ''), ['publicado', 'publicando'], true)) {
            header('Location: ' . url('/admin/instagram/posts/' . $id . '?error=' . urlencode('Post publicado não pode ser editado.')));
            exit;
        }

        $api      = $this->api($account);
        $legenda  = $this->buildCaption((string) ($_POST['legenda'] ?? ''), (string) ($_POST['hashtags'] ?? ''));
        $tipo     = (string) ($_POST['tipo'] ?? 'imagem');
        $acao     = (string) ($_POST['acao'] ?? 'rascunho');
        $status   = 'rascunho';
        $agendado = null;

        if ($acao === 'agendar') {
            $status   = 'agendado';
            $agendado = ($_POST['agendado_para'] ?? '') !== '' ? (string) $_POST['agendado_para'] : null;
        }

        $validation = $api->validateCaption($legenda);
        if (!$validation['ok']) {
            $errors = implode(' ', $validation['errors']);
            $medias = $repo->findMediaByPostId($id);
            View::render('admin/instagram/edit', [
                'title'      => 'Editar Post — Instagram',
                'post'       => $post,
                'medias'     => $medias,
                'account'    => $account,
                'csrf_token' => Csrf::generate(),
                'error'      => $errors,
                'old'        => $_POST,
            ]);
            return;
        }

        // Midias salvas marcadas na lixeira: so saem agora, junto com o resto, se o conjunto final for valido.
        $existing  = $repo->findMediaByPostId($id);
        $removeIds = array_map('intval', (array) ($_POST['remove_media_ids'] ?? []));
        $kept      = array_values(array_filter($existing, static fn (array $m): bool => !in_array((int) ($m['id'] ?? 0), $removeIds, true)));
        $removed   = array_values(array_filter($existing, static fn (array $m): bool => in_array((int) ($m['id'] ?? 0), $removeIds, true)));

        $hasAudio  = !empty($_POST['audio_track_id']);
        $input     = $this->inspectMediaInput($_FILES, $_POST);
        $ruleError = $input['errors'] === []
            ? InstagramPostRepository::mediaRuleError(
                $tipo,
                array_merge(InstagramPostRepository::mediaKinds($kept), array_column($input['items'], 'kind')),
                $status === 'rascunho' && $acao !== 'publicar',
                $hasAudio,
            )
            : null;
        $moved     = ($input['errors'] === [] && $ruleError === null) ? $this->moveUploads($input['items']) : ['items' => [], 'errors' => []];
        $failure   = $this->mediaErrorMessage(array_merge($input['errors'], $moved['errors']), $ruleError, $input['had_files']);

        if ($failure === null) {
            /** @var \PDO $pdo */
            $pdo = $GLOBALS['pdo'];
            try {
                $pdo->beginTransaction();
                $repo->update($id, [
                    'status'                 => $status,
                    'tipo'                   => !empty($_POST['audio_track_id']) ? 'reels' : $tipo,
                    'legenda'                => $legenda ?: null,
                    'hashtags_count'         => $api->countHashtags($legenda),
                    'agendado_para'          => $agendado,
                    'post_blog_id'           => ($_POST['post_blog_id'] ?? '') !== '' ? (int) $_POST['post_blog_id'] : null,
                    'audio_track_id'         => !empty($_POST['audio_track_id']) ? (int) $_POST['audio_track_id'] : null,
                    'audio_start_seconds'    => (int) ($_POST['audio_start_seconds'] ?? 0),
                    'audio_duration_seconds' => (int) ($_POST['audio_duration_seconds'] ?? 0),
                ]);
                foreach ($removed as $m) {
                    $repo->deleteMedia((int) $m['id'], $id);
                }
                // Novas entram depois das salvas; a renumeracao deixa 0, 1, 2... na ordem de publicacao.
                $this->persistMedia($repo, $id, $moved['items'], 100000);
                $repo->renumberMedia($id);
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $this->discardMovedFiles($moved['items']);
                error_log('[InstagramController] update: ' . $e->getMessage());
                $failure = $this->mediaErrorMessage(['Não foi possível salvar as alterações; nada foi gravado.'], null, $input['had_files']);
            }
        }

        if ($failure !== null) {
            View::render('admin/instagram/edit', [
                'title'      => 'Editar Post — Instagram',
                'post'       => $post,
                'medias'     => $existing,
                'account'    => $account,
                'csrf_token' => Csrf::generate(),
                'error'      => $failure,
                'old'        => $_POST,
            ]);
            return;
        }

        foreach ($removed as $m) {
            $this->unlinkInstagramUpload((string) ($m['caminho'] ?? ''));
        }

        if (!$this->fitMediaIfRequested($id, $tipo)) {
            header('Location: ' . url('/admin/instagram/posts/' . $id . '/editar?ajuste=falhou'));
            exit;
        }

        if ($acao === 'publicar') {
            $this->publishNow($repo, $api, $id, $account);
            header('Location: ' . url('/admin/instagram?published=1'));
            exit;
        }

        header('Location: ' . url('/admin/instagram/posts/' . $id . '?updated=1'));
        exit;
    }

    // ── Remover Mídia Individual ──────────────────────────────────────────────

    public function deleteMedia(string $id = '0'): void
    {
        $mediaId = (int) $id;
        $postId  = (int) ($_POST['post_id'] ?? 0);

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF inválido.';
            return;
        }

        $repo = $this->repo();
        $post = $repo->findById($postId);

        if ($post === null) {
            http_response_code(404);
            echo 'Post não encontrado.';
            return;
        }

        // Não permite remover mídia de posts já publicados ou em processo de publicação
        if (in_array((string) ($post['status'] ?? ''), ['publicado', 'publicando'], true)) {
            header('Location: ' . url('/admin/instagram/posts/' . $postId . '/editar?error=' . urlencode('Não é possível remover mídia de um post já publicado ou em publicação.')));
            exit;
        }

        $media = $repo->findMediaById($mediaId);
        if ($media === null || (int) ($media['post_id'] ?? 0) !== $postId) {
            http_response_code(404);
            echo 'Mídia não encontrada para este post.';
            return;
        }

        // Post agendado nao pode ficar invalido para o tipo (ex.: carrossel com 1 item).
        if ((string) ($post['status'] ?? '') === 'agendado') {
            $remaining = array_values(array_filter($repo->findMediaByPostId($postId), static fn (array $m): bool => (int) ($m['id'] ?? 0) !== $mediaId));
            $ruleError = InstagramPostRepository::mediaRuleError((string) ($post['tipo'] ?? ''), InstagramPostRepository::mediaKinds($remaining));
            if ($ruleError !== null) {
                header('Location: ' . url('/admin/instagram/posts/' . $postId . '/editar?error=' . urlencode('Remoção bloqueada: ' . $ruleError)));
                exit;
            }
        }

        $repo->deleteMedia($mediaId, $postId);
        $repo->renumberMedia($postId);

        // Se for upload local do Instagram, remove o arquivo físico com segurança
        $caminho = (string) ($media['caminho'] ?? '');
        if ($caminho !== '' && str_starts_with($caminho, 'uploads/instagram/')) {
            $baseDir  = realpath(__DIR__ . '/../../../public/uploads/instagram');
            $fullPath = realpath(__DIR__ . '/../../../public/' . $caminho);
            if ($baseDir && $fullPath && str_starts_with($fullPath, $baseDir) && is_file($fullPath)) {
                @unlink($fullPath);
            }
        }

        header('Location: ' . url('/admin/instagram/posts/' . $postId . '/editar?media_deleted=1'));
        exit;
    }

    // ── Excluir Post (Rascunho, Agendado ou Erro) ─────────────────────────────

    public function deletePost(string $id = '0'): void
    {
        $postId = (int) $id;

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF inválido.';
            return;
        }

        $repo = $this->repo();
        $post = $repo->findById($postId);

        if ($post === null) {
            http_response_code(404);
            echo 'Post não encontrado.';
            return;
        }

        // Não permite excluir posts publicados ou que estejam em processo de publicação
        $status = (string) ($post['status'] ?? '');
        if (in_array($status, ['publicado', 'publicando'], true)) {
            header('Location: ' . url('/admin/instagram?error=' . urlencode('Não é possível excluir um post que já foi publicado ou está em processo de publicação.')));
            exit;
        }

        // Remove os arquivos físicos das mídias associadas
        $medias = $repo->findMediaByPostId($postId);
        $baseDir = realpath(__DIR__ . '/../../../public/uploads/instagram');

        foreach ($medias as $media) {
            $caminho = (string) ($media['caminho'] ?? '');
            if ($caminho !== '' && str_starts_with($caminho, 'uploads/instagram/')) {
                $fullPath = realpath(__DIR__ . '/../../../public/' . $caminho);
                if ($baseDir && $fullPath && str_starts_with($fullPath, $baseDir) && is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }
        }

        $repo->deletePost($postId);

        header('Location: ' . url('/admin/instagram?deleted=1'));
        exit;
    }

    /**
     * Mescla o texto da legenda e as hashtags separadas em uma única legenda formatada.
     */
    private function buildCaption(string $texto, string $hashtags): string
    {
        $texto = trim($texto);
        $hashtags = trim($hashtags);

        if ($hashtags === '') {
            return $texto;
        }

        $tags = preg_split('/[\s,]+/', $hashtags, -1, PREG_SPLIT_NO_EMPTY);
        $formatted = [];
        if (is_array($tags)) {
            foreach ($tags as $tag) {
                $tag = ltrim($tag, '#');
                if ($tag !== '') {
                    $formatted[] = '#' . $tag;
                }
            }
        }

        $tagsString = implode(' ', $formatted);
        if ($tagsString === '') {
            return $texto;
        }

        return $texto !== '' ? $texto . "\n\n" . $tagsString : $tagsString;
    }

    // ── Detalhes da Mídia ─────────────────────────────────────────────────────

    public function show(string $id = '0'): void
    {
        $id   = (int) $id;
        $repo = $this->repo();
        $post = $repo->findById($id);

        if ($post === null) {
            http_response_code(404);
            echo 'Post não encontrado.';
            return;
        }

        $medias   = $repo->findMediaByPostId($id);
        $insights = [];
        $comments = [];
        $account  = $repo->findActiveAccount();

        if ($account !== null && !empty($post['ig_media_id'])) {
            try {
                $api      = $this->api($account);
                $detail   = $api->getMediaDetails((string) $post['ig_media_id']);
                $insights = $detail['insights'] ?? [];
                $result   = $api->getMediaComments((string) $post['ig_media_id']);
                $comments = $result['data'] ?? [];
            } catch (RuntimeException) {
                // Exibe o que há localmente
            }
        }

        $audioTrack = !empty($post['audio_track_id']) ? $repo->findAudioTrack((int) $post['audio_track_id']) : null;

        View::render('admin/instagram/show', [
            'title'       => 'Detalhes do Post — Instagram',
            'post'        => $post,
            'medias'      => $medias,
            'audio_track' => $audioTrack,
            'insights'    => $insights,
            'comments'    => $comments,
        ]);
    }

    // ── API: Previa do cross-post do blog ──────────────────────────────────────

    public function crosspostPreview(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'error' => 'Token CSRF invalido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $result = BlogCrosspostService::fromGlobals()->preview($_POST, $_FILES, Auth::id(), target_environment());
        } catch (\Throwable $e) {
            error_log('[InstagramController] crosspostPreview: ' . $e->getMessage());
            $result = ['ok' => false, 'error' => 'Nao foi possivel gerar a previa agora.'];
        }

        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // ── Trilha Sonora / Audius (FEAT-012) ────────────────────────────────────

    public function audioLocalTracks(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $service = AudiusTrackService::fromGlobals();
            $tracks = $service->listLocalTracks();
            echo json_encode(['ok' => true, 'tracks' => $tracks], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    public function audioSearch(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q === '') {
            echo json_encode(['ok' => true, 'tracks' => []], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $service = AudiusTrackService::fromGlobals();
            $tracks = $service->search($q, 15);
            echo json_encode(['ok' => true, 'tracks' => $tracks], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    public function audioDownload(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $trackId = trim((string) ($_POST['track_id'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? 'Sem título'));
        $artist = trim((string) ($_POST['artist'] ?? 'Artista Audius'));
        $genre = trim((string) ($_POST['genre'] ?? 'Eletrônica'));
        $duration = (int) ($_POST['duration'] ?? 0);

        if ($trackId === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'ID da faixa ausente.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $service = AudiusTrackService::fromGlobals();
            $track = $service->downloadAudiusTrack($trackId, $title, $artist, $genre, $duration);
            echo json_encode(['ok' => true, 'track' => $track], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    public function audioUpload(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $file = $_FILES['audio_file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Nenhum arquivo de áudio enviado.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $artist = trim((string) ($_POST['artist'] ?? ''));

        try {
            $service = AudiusTrackService::fromGlobals();
            $track = $service->uploadCustomTrack($file, $title, $artist);
            echo json_encode(['ok' => true, 'track' => $track], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    // ── Helpers Privados ──────────────────────────────────────────────────────

    private function repo(): InstagramPostRepository
    {
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new InstagramPostRepository($pdo);
    }

    /**
     * @param array<string,mixed> $account
     */
    private function api(array $account): InstagramApiService
    {
        return new InstagramApiService(
            (string) ($account['access_token'] ?? ''),
            (string) ($account['ig_user_id'] ?? ''),
        );
    }

    /**
     * Aplica o Smart Canvas nas imagens fora do padrao quando a opcao veio marcada.
     * Retorna false se o ajuste falhou (o post fica salvo com as imagens originais).
     */
    private function fitMediaIfRequested(int $postId, string $tipo): bool
    {
        if ((string) ($_POST['ig_auto_fit'] ?? '') !== '1') {
            return true;
        }

        try {
            return InstagramMediaFitter::fromGlobals()->fitPost($postId, $tipo)['ok'];
        } catch (\Throwable $e) {
            error_log('[InstagramController] fitMediaIfRequested: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Publica um post imediatamente via Meta API.
     * Em caso de erro registra o log e não lança exceção.
     *
     * @param array<string,mixed> $account
     */
    private function publishNow(
        InstagramPostRepository $repo,
        InstagramApiService $api,
        int $postId,
        array $account,
    ): void {
        try {
            $post   = $repo->findById($postId);
            $medias = $repo->findMediaByPostId($postId);

            if ($post === null || empty($medias)) {
                $repo->markError($postId, 'Post ou mídias não encontrados para publicação.');
                return;
            }

            $hasAudio = !empty($post['audio_track_id']);
            $ruleError = InstagramPostRepository::mediaRuleError((string) ($post['tipo'] ?? ''), InstagramPostRepository::mediaKinds($medias), false, $hasAudio);
            if ($ruleError !== null) {
                $repo->markError($postId, 'Mídias incompatíveis com o tipo do post (nada foi enviado ao Instagram): ' . $ruleError);
                return;
            }

            $tipo    = (string) ($post['tipo'] ?? 'imagem');
            $legenda = $post['legenda'] ?? null;
            $isVideo = false;

            if ($hasAudio) {
                $renderedRel = AudioReelGeneratorService::readyVideoPath($post, base_path('public'));
                if ($renderedRel === null) {
                    $audioTrack = $repo->findAudioTrack((int) $post['audio_track_id']);
                    if ($audioTrack === null) {
                        throw new RuntimeException('Trilha sonora associada ao post não foi encontrada.');
                    }
                    $reelGen = AudioReelGeneratorService::fromGlobals();
                    $imagePaths = [];
                    foreach ($medias as $m) {
                        $p = trim((string) ($m['caminho'] ?? ''));
                        if ($p !== '') {
                            $imagePaths[] = $p;
                        }
                    }
                    if ($imagePaths === []) {
                        throw new RuntimeException('Ao menos uma imagem é necessária para gerar o Reel com áudio.');
                    }
                    $startSec = (int) ($post['audio_start_seconds'] ?? 0);
                    $durSec   = (int) ($post['audio_duration_seconds'] ?? 0);
                    $renderedRel = $reelGen->generateReel(
                        $imagePaths,
                        (string) $audioTrack['arquivo_path'],
                        $startSec,
                        $durSec > 0 ? $durSec : null
                    );
                    $repo->markRenderedReady($postId, $renderedRel);
                }

                $reelVideoUrl = InstagramApiService::buildPublicMediaUrl($renderedRel);

                $isVideo = true;
                $creationId = $api->createVideoContainer(
                    (string) $reelVideoUrl,
                    $legenda,
                    ['media_type' => 'REELS'],
                );
            } elseif ($tipo === 'carrossel' && count($medias) >= 2) {
                $childIds = [];
                foreach ($medias as $m) {
                    $mediaUrl = InstagramApiService::buildPublicMediaUrl((string) ($m['caminho'] ?? $m['url_publica'] ?? ''));
                    $mTipo    = (string) ($m['tipo_arquivo'] ?? 'imagem');
                    if ($mTipo === 'video') {
                        $isVideo    = true;
                        $childIds[] = $api->createVideoContainer($mediaUrl, null, ['is_carousel_item' => 'true']);
                    } else {
                        $childIds[] = $api->createImageContainer($mediaUrl, null, ['is_carousel_item' => 'true']);
                    }
                    usleep(500_000);
                }
                $creationId = $api->createCarouselContainer($childIds, $legenda);
            } elseif ($tipo === 'reels') {
                $first      = $medias[0];
                $mediaUrl   = InstagramApiService::buildPublicMediaUrl((string) ($first['caminho'] ?? $first['url_publica'] ?? ''));
                $isVideo    = true;
                $creationId = $api->createVideoContainer(
                    $mediaUrl,
                    $legenda,
                    ['media_type' => 'REELS'],
                );
            } elseif ($tipo === 'story') {
                $first    = $medias[0];
                $mTipo    = (string) ($first['tipo_arquivo'] ?? 'imagem');
                $mediaUrl = InstagramApiService::buildPublicMediaUrl((string) ($first['caminho'] ?? $first['url_publica'] ?? ''));
                if ($mTipo === 'video') {
                    $isVideo    = true;
                    $creationId = $api->createVideoContainer($mediaUrl, null, ['media_type' => 'STORIES']);
                } else {
                    $creationId = $api->createImageContainer($mediaUrl, null, ['media_type' => 'STORIES']);
                }
            } else {
                $first      = $medias[0];
                $mediaUrl   = InstagramApiService::buildPublicMediaUrl((string) ($first['caminho'] ?? $first['url_publica'] ?? ''));
                $creationId = $api->createImageContainer(
                    $mediaUrl,
                    $legenda,
                );
            }

            $repo->saveCreationId($postId, $creationId);

            // Aguarda o container estar pronto (vídeos demandam mais tempo de transcodificação)
            $maxTries = $isVideo ? 15 : 6;
            $tries    = 0;
            do {
                usleep(2_000_000);
                $containerStatus = $api->checkContainerStatus($creationId);
                $tries++;
                if ($containerStatus === 'ERROR' || $containerStatus === 'EXPIRED') {
                    throw new RuntimeException("Falha no processamento do container Meta (status: {$containerStatus}).");
                }
            } while ($containerStatus !== 'FINISHED' && $tries < $maxTries);

            if ($containerStatus !== 'FINISHED') {
                throw new RuntimeException("Container não ficou pronto a tempo na Meta (status: {$containerStatus}).");
            }

            $igMediaId = $api->publishMedia($creationId);
            $detail    = $api->getMediaDetails($igMediaId);
            $permalink = (string) ($detail['permalink'] ?? '');

            $repo->markPublished($postId, $igMediaId, $permalink);
        } catch (RuntimeException $e) {
            $repo->markError($postId, $e->getMessage());
        }
    }

    /**
     * Confere URLs da biblioteca e uploads sem gravar nada: formato, MIME real e tamanho.
     * Uploads que falharam viram erro e nao entram na contagem de midias.
     *
     * @param array<string,mixed> $files $_FILES
     * @param array<string,mixed> $post  $_POST
     * @return array{items: list<array<string,mixed>>, errors: list<string>, had_files: bool}
     */
    private function inspectMediaInput(array $files, array $post): array
    {
        $appUrl   = rtrim((string) config('app.url', ''), '/');
        $items    = [];
        $errors   = [];
        $hadFiles = false;

        $libraryUrls = $post['media_urls'] ?? [];
        if (is_array($libraryUrls)) {
            foreach ($libraryUrls as $rawUrl) {
                $url = trim((string) $rawUrl);
                if ($url === '') {
                    continue;
                }
                $ext = strtolower((string) pathinfo((string) (parse_url($url, PHP_URL_PATH) ?? ''), PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov'], true)) {
                    $errors[] = "A URL selecionada possui formato '{$ext}' incompatível com o Instagram.";
                    continue;
                }
                $items[] = [
                    'source'      => 'url',
                    'kind'        => in_array($ext, ['mp4', 'mov'], true) ? 'video' : 'imagem',
                    'caminho'     => $url,
                    'url_publica' => str_starts_with($url, 'http') ? $url : $appUrl . '/' . ltrim($url, '/'),
                ];
            }
        }

        $uploaded = $files['medias'] ?? [];
        if (!is_array($uploaded) || empty($uploaded['name']) || !is_array($uploaded['name'])) {
            return ['items' => $items, 'errors' => $errors, 'had_files' => $hadFiles];
        }

        $tmpNames = (array) ($uploaded['tmp_name'] ?? []);
        $errCodes = (array) ($uploaded['error'] ?? []);
        $sizes    = (array) ($uploaded['size'] ?? []);

        $allowedImageMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $allowedVideoMimes = ['video/mp4', 'video/quicktime'];
        $maxImageBytes     = 8 * 1024 * 1024;    // 8MB
        $maxVideoBytes     = 100 * 1024 * 1024;  // 100MB

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        foreach ($uploaded['name'] as $i => $name) {
            $nameStr = (string) $name;
            $tmpName = (string) ($tmpNames[$i] ?? '');
            $errCode = (int) ($errCodes[$i] ?? UPLOAD_ERR_NO_FILE);
            $size    = (int) ($sizes[$i] ?? 0);

            if ($errCode === UPLOAD_ERR_NO_FILE || $nameStr === '') {
                continue;
            }
            $hadFiles = true;

            if ($errCode !== UPLOAD_ERR_OK || $tmpName === '' || !is_uploaded_file($tmpName)) {
                $errors[] = "Falha no upload do arquivo '{$nameStr}'.";
                continue;
            }

            $ext = strtolower((string) pathinfo($nameStr, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov'], true)) {
                $errors[] = "Extensão '.{$ext}' não permitida para '{$nameStr}'. Envie JPG, PNG, WEBP, MP4 ou MOV.";
                continue;
            }

            $detectedMime = $finfo !== false ? (string) finfo_file($finfo, $tmpName) : (string) mime_content_type($tmpName);
            $isVideo      = in_array($detectedMime, $allowedVideoMimes, true);
            $isImage      = in_array($detectedMime, $allowedImageMimes, true);

            if (!$isVideo && !$isImage) {
                $errors[] = "Tipo MIME real ('{$detectedMime}') não permitido para '{$nameStr}'.";
                continue;
            }
            if ($isImage && $size > $maxImageBytes) {
                $errors[] = "A imagem '{$nameStr}' excede o limite máximo permitido de 8MB.";
                continue;
            }
            if ($isVideo && $size > $maxVideoBytes) {
                $errors[] = "O vídeo '{$nameStr}' excede o limite máximo permitido de 100MB.";
                continue;
            }

            $items[] = ['source' => 'upload', 'kind' => $isVideo ? 'video' : 'imagem', 'name' => $nameStr, 'tmp' => $tmpName, 'ext' => $ext];
        }

        if ($finfo !== false) {
            finfo_close($finfo);
        }

        return ['items' => $items, 'errors' => $errors, 'had_files' => $hadFiles];
    }

    /**
     * Move os uploads conferidos para public/uploads/instagram. Se algum falhar, desfaz os ja movidos.
     *
     * @param list<array<string,mixed>> $items
     * @return array{items: list<array<string,mixed>>, errors: list<string>}
     */
    private function moveUploads(array $items): array
    {
        $appUrl     = rtrim((string) config('app.url', ''), '/');
        $uploadsDir = __DIR__ . '/../../../public/uploads/instagram/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        foreach ($items as $k => $item) {
            if ($item['source'] !== 'upload') {
                continue;
            }
            // Nome de arquivo aleatório seguro (evita colisão e sobrescrita)
            $filename = bin2hex(random_bytes(16)) . '.' . (string) $item['ext'];
            if (!move_uploaded_file((string) $item['tmp'], $uploadsDir . $filename)) {
                $this->discardMovedFiles($items);

                return ['items' => [], 'errors' => ["Falha ao gravar o arquivo '" . (string) $item['name'] . "' no disco."]];
            }
            $items[$k]['caminho']     = 'uploads/instagram/' . $filename;
            $items[$k]['url_publica'] = $appUrl . '/uploads/instagram/' . $filename;
        }

        return ['items' => $items, 'errors' => []];
    }

    /**
     * @param list<array<string,mixed>> $items Itens ja movidos (moveUploads)
     */
    private function persistMedia(InstagramPostRepository $repo, int $postId, array $items, int $startOrder): void
    {
        foreach ($items as $i => $item) {
            $repo->addMedia($postId, [
                'ordem'        => $startOrder + $i,
                'tipo_arquivo' => (string) $item['kind'],
                'caminho'      => (string) $item['caminho'],
                'url_publica'  => $item['url_publica'] ?? null,
            ]);
        }
    }

    /**
     * Apaga os arquivos movidos nesta requisicao (usado quando o salvamento e desfeito).
     *
     * @param list<array<string,mixed>> $items
     */
    private function discardMovedFiles(array $items): void
    {
        foreach ($items as $item) {
            if ($item['source'] === 'upload' && isset($item['caminho'])) {
                $this->unlinkInstagramUpload((string) $item['caminho']);
            }
        }
    }

    /**
     * Remove um arquivo de public/uploads/instagram, sem sair dessa pasta.
     */
    private function unlinkInstagramUpload(string $caminho): void
    {
        if ($caminho === '' || !str_starts_with($caminho, 'uploads/instagram/')) {
            return;
        }
        $baseDir  = realpath(__DIR__ . '/../../../public/uploads/instagram');
        $fullPath = realpath(__DIR__ . '/../../../public/' . $caminho);
        if ($baseDir && $fullPath && str_starts_with($fullPath, $baseDir) && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    /**
     * Junta os erros de midia numa mensagem; o navegador descarta os arquivos ao recarregar a tela.
     *
     * @param list<string> $errors
     */
    private function mediaErrorMessage(array $errors, ?string $ruleError, bool $hadFiles): ?string
    {
        if ($ruleError !== null) {
            $errors[] = $ruleError;
        }
        if ($errors === []) {
            return null;
        }

        return implode(' ', $errors) . ($hadFiles ? ' Selecione os arquivos novamente.' : '');
    }

    /**
     * Gera um UUID v4.
     */
    private function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Persiste o payload de insights em formato padronizado calculando delta de seguidores.
     *
     * @param array<string,mixed> $apiResponse
     */
    private function persistInsights(
        InstagramPostRepository $repo,
        int $accountId,
        string $period,
        array $apiResponse,
        int $currentFollowers = 0,
        ?string $startDate = null,
        ?string $endDate = null,
    ): void {
        $data   = $apiResponse['data'] ?? [];
        $totals = ['reach' => 0, 'views' => 0, 'impressions' => 0, 'profile_views' => 0, 'total_interactions' => 0];

        if (is_array($data)) {
            foreach ($data as $metric) {
                $name = (string) ($metric['name'] ?? '');
                $val  = 0;
                if (isset($metric['total_value']['value'])) {
                    $val = (int) $metric['total_value']['value'];
                } elseif (isset($metric['values']) && is_array($metric['values'])) {
                    foreach ($metric['values'] as $v) {
                        if (isset($v['value'])) {
                            $val += (int) $v['value'];
                        }
                    }
                }
                if (array_key_exists($name, $totals)) {
                    $totals[$name] = $val;
                }
            }
        }

        if ($totals['impressions'] === 0 && $totals['views'] > 0) {
            $totals['impressions'] = $totals['views'];
        }

        $today = date('Y-m-d');
        $prev  = $repo->getPreviousInsights($accountId, $period, $today);
        $variacao = 0;
        if ($prev !== null && isset($prev['seguidores']) && (int) $prev['seguidores'] > 0 && $currentFollowers > 0) {
            $variacao = $currentFollowers - (int) $prev['seguidores'];
        }

        $repo->upsertInsightsCache([
            'account_id'          => $accountId,
            'periodo'             => $period,
            'data_inicio'         => $startDate,
            'data_fim'            => $endDate,
            'data_referencia'     => $today,
            'alcance'             => $totals['reach'],
            'visualizacoes'       => $totals['views'],
            'impressoes'          => $totals['impressions'],
            'visitas_perfil'      => $totals['profile_views'],
            'interacoes'          => $totals['total_interactions'],
            'seguidores'          => $currentFollowers,
            'variacao_seguidores' => $variacao,
            'payload_raw'         => $apiResponse,
        ]);
    }
}
