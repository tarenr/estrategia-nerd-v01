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
            'busca'  => trim((string) ($_GET['busca'] ?? '')),
            'tipo'   => trim((string) ($_GET['tipo'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];

        $sort    = trim((string) ($_GET['sort'] ?? 'publicado_em'));
        $dir     = strtolower(trim((string) ($_GET['dir'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc';
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $rawPerPage = strtolower(trim((string) ($_GET['per_page'] ?? '8')));
        if ($rawPerPage === 'todos' || $rawPerPage === 'all') {
            $perPage = 9999;
        } else {
            $perPageInt = (int) $rawPerPage;
            $perPage = in_array($perPageInt, [8, 16, 24, 48], true) ? $perPageInt : 8;
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

        View::render('admin/instagram/index', [
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
        ]);
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

        // Posts do blog disponíveis para importação
        $blogPosts = $this->blogPosts();

        View::render('admin/instagram/create', [
            'title'           => 'Novo Post — Instagram',
            'account'         => $account,
            'blog_posts'      => $blogPosts,
            'csrf_token'      => Csrf::generate(),
            'is_local_target' => target_environment() === 'local',
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
                'blog_posts' => $this->blogPosts(),
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

        $postId = $repo->create([
            'account_id'      => (int) $account['id'],
            'status'          => $status,
            'tipo'            => $tipo,
            'legenda'         => $legenda ?: null,
            'hashtags_count'  => $api->countHashtags($legenda),
            'agendado_para'   => $agendadoPara,
            'post_blog_id'    => ($_POST['post_blog_id'] ?? '') !== '' ? (int) $_POST['post_blog_id'] : null,
            'idempotency_key' => $this->uuid4(),
            'origin'          => 'local',
            'criado_por'      => Auth::id(),
        ]);

        // Salvar mídias enviadas
        $uploadErrors = $this->saveUploadedMedia($repo, $postId, $_FILES, $_POST);
        if (!empty($uploadErrors)) {
            $errors = implode(' ', $uploadErrors);
            View::render('admin/instagram/create', [
                'title'      => 'Novo Post — Instagram',
                'account'    => $account,
                'blog_posts' => $this->blogPosts(),
                'csrf_token' => Csrf::generate(),
                'error'      => $errors,
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

        View::render('admin/instagram/edit', [
            'title'      => 'Editar Post — Instagram',
            'post'       => $post,
            'medias'     => $medias,
            'blog_posts' => $this->blogPosts(),
            'csrf_token' => Csrf::generate(),
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
                'blog_posts' => $this->blogPosts(),
                'csrf_token' => Csrf::generate(),
                'error'      => $errors,
                'old'        => $_POST,
            ]);
            return;
        }

        $repo->update($id, [
            'status'          => $status,
            'tipo'            => $tipo,
            'legenda'         => $legenda ?: null,
            'hashtags_count'  => $api->countHashtags($legenda),
            'agendado_para'   => $agendado,
            'post_blog_id'    => ($_POST['post_blog_id'] ?? '') !== '' ? (int) $_POST['post_blog_id'] : null,
        ]);

        // Adicionar novas mídias se foram enviadas
        if (!empty($_FILES['medias']['name'][0])) {
            $existingMedias = $repo->findMediaByPostId($id);
            $uploadErrors   = $this->saveUploadedMedia($repo, $id, $_FILES, $_POST, count($existingMedias));
            if (!empty($uploadErrors)) {
                $medias = $repo->findMediaByPostId($id);
                View::render('admin/instagram/edit', [
                    'title'      => 'Editar Post — Instagram',
                    'post'       => $post,
                    'medias'     => $medias,
                    'blog_posts' => $this->blogPosts(),
                    'csrf_token' => Csrf::generate(),
                    'error'      => implode(' ', $uploadErrors),
                    'old'        => $_POST,
                ]);
                return;
            }
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

        $repo->deleteMedia($mediaId, $postId);

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

        View::render('admin/instagram/show', [
            'title'    => 'Detalhes do Post — Instagram',
            'post'     => $post,
            'medias'   => $medias,
            'insights' => $insights,
            'comments' => $comments,
        ]);
    }

    // ── API: Dados de Post do Blog ─────────────────────────────────────────────

    public function blogPostData(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
            return;
        }

        /** @var \PDO $pdo */
        $pdo  = $GLOBALS['pdo'];
        $stmt = $pdo->prepare(
            "SELECT id, titulo, imagem_capa AS capa, resumo FROM posts WHERE id = :id AND status = 'publicado' LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            http_response_code(404);
            echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
            return;
        }

        $linked = $this->linkedLocalBlogPosts()[(int) $row['id']] ?? null;

        echo json_encode(['ok' => true, 'post' => $row, 'linked' => $linked], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
     * @return array<int,array<string,mixed>>
     */
    private function blogPosts(): array
    {
        /** @var \PDO $pdo */
        $pdo  = $GLOBALS['pdo'];
        $stmt = $pdo->query(
            "SELECT id, titulo, imagem_capa AS capa, resumo FROM posts WHERE status = 'publicado' ORDER BY data_publicacao DESC LIMIT 100"
        );

        if ($stmt === false) {
            return [];
        }

        $linked = $this->linkedLocalBlogPosts();

        return array_values(array_filter(
            $stmt->fetchAll(\PDO::FETCH_ASSOC),
            static fn (array $post): bool => !isset($linked[(int) ($post['id'] ?? 0)]),
        ));
    }

    /**
     * Posts do blog LOCAL que ja tem post no Instagram, indexados pelo id do blog.
     * Cross-post feito em stage/producao grava o id DAQUELE ambiente em post_blog_id;
     * a chave UUID v5 identifica esses casos para nao esconder o post local de mesmo id.
     *
     * @return array<int,array{id:int,status:string}>
     */
    private function linkedLocalBlogPosts(): array
    {
        try {
            /** @var \PDO $pdo */
            $pdo  = $GLOBALS['pdo'];
            $stmt = $pdo->query('SELECT id, status, post_blog_id, idempotency_key FROM instagram_posts WHERE post_blog_id IS NOT NULL ORDER BY id ASC');
            $rows = $stmt !== false ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        } catch (\Throwable $e) {
            error_log('[InstagramController] linkedLocalBlogPosts: ' . $e->getMessage());

            return [];
        }

        $linked = [];
        foreach ($rows as $row) {
            $blogId = (int) $row['post_blog_id'];
            $key    = (string) ($row['idempotency_key'] ?? '');
            if ($key === BlogCrosspostService::idempotencyKey('stage', $blogId) || $key === BlogCrosspostService::idempotencyKey('production', $blogId)) {
                continue;
            }
            $linked[$blogId] ??= ['id' => (int) $row['id'], 'status' => (string) $row['status']];
        }

        return $linked;
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

            $tipo    = (string) ($post['tipo'] ?? 'imagem');
            $legenda = $post['legenda'] ?? null;
            $isVideo = false;

            if ($tipo === 'carrossel' && count($medias) >= 2) {
                $childIds = [];
                foreach ($medias as $m) {
                    $mediaUrl = (string) ($m['url_publica'] ?? '');
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
                $isVideo    = true;
                $creationId = $api->createVideoContainer(
                    (string) ($first['url_publica'] ?? ''),
                    $legenda,
                    ['media_type' => 'REELS'],
                );
            } elseif ($tipo === 'story') {
                $first    = $medias[0];
                $mTipo    = (string) ($first['tipo_arquivo'] ?? 'imagem');
                $mediaUrl = (string) ($first['url_publica'] ?? '');
                if ($mTipo === 'video') {
                    $isVideo    = true;
                    $creationId = $api->createVideoContainer($mediaUrl, null, ['media_type' => 'STORIES']);
                } else {
                    $creationId = $api->createImageContainer($mediaUrl, null, ['media_type' => 'STORIES']);
                }
            } else {
                $first      = $medias[0];
                $creationId = $api->createImageContainer(
                    (string) ($first['url_publica'] ?? ''),
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
     * Salva arquivos $_FILES['medias'] ou URLs da biblioteca no repositório de mídias com validação robusta.
     *
     * @param array<string,mixed> $files   $_FILES
     * @param array<string,mixed> $post    $_POST
     * @return list<string> Lista de erros encontrados
     */
    private function saveUploadedMedia(
        InstagramPostRepository $repo,
        int $postId,
        array $files,
        array $post,
        int $startOrder = 0,
    ): array {
        $appUrl   = rtrim((string) config('app.url', ''), '/');
        $errors   = [];
        $uploaded = $files['medias'] ?? [];

        // Validação e inclusão de URLs enviadas da biblioteca interna
        $libraryUrls = $post['media_urls'] ?? [];
        if (is_array($libraryUrls) && !empty($libraryUrls)) {
            $ordem = $startOrder;
            foreach ($libraryUrls as $rawUrl) {
                $url = trim((string) $rawUrl);
                if ($url === '') {
                    continue;
                }
                $ext = strtolower((string) pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov'], true)) {
                    $errors[] = "A URL selecionada possui formato '{$ext}' incompatível com o Instagram.";
                    continue;
                }
                $tipoArq    = in_array($ext, ['mp4', 'mov'], true) ? 'video' : 'imagem';
                $urlPublica = str_starts_with($url, 'http') ? $url : $appUrl . '/' . ltrim($url, '/');

                $repo->addMedia($postId, [
                    'ordem'        => $ordem++,
                    'tipo_arquivo' => $tipoArq,
                    'caminho'      => $url,
                    'url_publica'  => $urlPublica,
                ]);
            }
        }

        if (!is_array($uploaded) || empty($uploaded['name']) || empty($uploaded['name'][0])) {
            return $errors;
        }

        $names    = (array) $uploaded['name'];
        $tmpNames = (array) $uploaded['tmp_name'];
        $errCodes = (array) ($uploaded['error'] ?? []);
        $sizes    = (array) ($uploaded['size'] ?? []);

        $uploadsDir = __DIR__ . '/../../../public/uploads/instagram/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $allowedImageMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $allowedVideoMimes = ['video/mp4', 'video/quicktime'];
        $maxImageBytes     = 8 * 1024 * 1024;    // 8MB
        $maxVideoBytes     = 100 * 1024 * 1024;  // 100MB

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        foreach ($names as $i => $name) {
            $nameStr = (string) $name;
            $tmpName = (string) ($tmpNames[$i] ?? '');
            $errCode = (int) ($errCodes[$i] ?? UPLOAD_ERR_NO_FILE);
            $size    = (int) ($sizes[$i] ?? 0);

            if ($errCode === UPLOAD_ERR_NO_FILE || $nameStr === '') {
                continue;
            }

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

            $isVideo = in_array($detectedMime, $allowedVideoMimes, true);
            $isImage = in_array($detectedMime, $allowedImageMimes, true);

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

            // Nome de arquivo aleatório seguro (evita colisão e sobrescrita)
            $filename = bin2hex(random_bytes(16)) . '.' . $ext;
            $dest     = $uploadsDir . $filename;

            if (move_uploaded_file($tmpName, $dest)) {
                $relative  = 'uploads/instagram/' . $filename;
                $tipoArq   = $isVideo ? 'video' : 'imagem';
                $urlPublic = $appUrl . '/' . $relative;

                $repo->addMedia($postId, [
                    'ordem'        => $startOrder + $i,
                    'tipo_arquivo' => $tipoArq,
                    'caminho'      => $relative,
                    'url_publica'  => $urlPublic,
                ]);
            } else {
                $errors[] = "Falha ao gravar o arquivo '{$nameStr}' no disco.";
            }
        }

        if ($finfo !== false) {
            finfo_close($finfo);
        }

        return $errors;
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
