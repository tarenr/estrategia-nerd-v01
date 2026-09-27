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
use App\Services\Instagram\InstagramApiService;
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
        $scheduled = $repo->listScheduled($accountId, 10);
        $drafts    = $repo->listDrafts($accountId, 10);
        $published = $repo->listPublished($accountId, 12);
        $insights7 = $repo->getLatestInsights($accountId, '7d');
        $insights30 = $repo->getLatestInsights($accountId, '30d');

        // Feed ao vivo via API (best-effort)
        $feed = [];
        try {
            $api = $this->api($account);
            $result = $api->getMediaFeed(12);
            $feed = $result['data'] ?? [];
        } catch (RuntimeException) {
            // Continua sem feed ao vivo — usa publicados locais
        }

        View::render('admin/instagram/index', [
            'title'      => 'Instagram',
            'account'    => $account,
            'scheduled'  => $scheduled,
            'drafts'     => $drafts,
            'published'  => $published,
            'feed'       => $feed,
            'insights7'  => $insights7,
            'insights30' => $insights30,
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

            // Salvar insights 7d
            $insights7 = $api->getInsights('7d');
            $this->persistInsights($repo, (int) $account['id'], '7d', $insights7);

            // Salvar insights 30d
            $insights30 = $api->getInsights('30d');
            $this->persistInsights($repo, (int) $account['id'], '30d', $insights30);

            echo json_encode(['ok' => true, 'synced_at' => date('d/m/Y H:i')], JSON_UNESCAPED_UNICODE);
        } catch (RuntimeException $e) {
            http_response_code(502);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
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
            'title'      => 'Novo Post — Instagram',
            'account'    => $account,
            'blog_posts' => $blogPosts,
            'csrf_token' => Csrf::generate(),
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
        $legenda = (string) ($_POST['legenda'] ?? '');
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
        $this->saveUploadedMedia($repo, $postId, $_FILES, $_POST);

        if ($acao === 'publicar') {
            $this->publishNow($repo, $api, $postId, $account);
            header('Location: ' . url('/admin/instagram?published=1'));
            exit;
        }

        header('Location: ' . url('/admin/instagram?saved=1'));
        exit;
    }

    // ── Editar Post ───────────────────────────────────────────────────────────

    public function edit(): void
    {
        $id   = (int) ($_GET['id'] ?? 0);
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

    public function update(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

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

        $api      = $this->api($account);
        $legenda  = (string) ($_POST['legenda'] ?? '');
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

        // Substituir mídias se novas foram enviadas
        if (!empty($_FILES['medias']['name'][0])) {
            $repo->deleteMediaByPostId($id);
            $this->saveUploadedMedia($repo, $id, $_FILES, $_POST);
        }

        if ($acao === 'publicar') {
            $this->publishNow($repo, $api, $id, $account);
            header('Location: ' . url('/admin/instagram?published=1'));
            exit;
        }

        header('Location: ' . url('/admin/instagram/posts/' . $id . '?updated=1'));
        exit;
    }

    // ── Detalhes da Mídia ─────────────────────────────────────────────────────

    public function show(): void
    {
        $id   = (int) ($_GET['id'] ?? 0);
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
            "SELECT id, titulo, capa, resumo FROM posts WHERE id = :id AND status = 'publicado' LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            http_response_code(404);
            echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode(['ok' => true, 'post' => $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
            "SELECT id, titulo, capa, resumo FROM posts WHERE status = 'publicado' ORDER BY criado_em DESC LIMIT 100"
        );

        if ($stmt === false) {
            return [];
        }

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
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

            $legenda = $post['legenda'] ?? null;

            if ((string) ($post['tipo'] ?? '') === 'carrossel' && count($medias) >= 2) {
                $childIds = [];
                foreach ($medias as $m) {
                    $childIds[] = $api->createImageContainer(
                        (string) ($m['url_publica'] ?? ''),
                        null,
                        ['is_carousel_item' => 'true'],
                    );
                }
                $creationId = $api->createCarouselContainer($childIds, $legenda);
            } else {
                $first = $medias[0];
                $creationId = $api->createImageContainer(
                    (string) ($first['url_publica'] ?? ''),
                    $legenda,
                );
            }

            $repo->saveCreationId($postId, $creationId);

            // Aguarda o container estar pronto (max 10s)
            $tries = 0;
            do {
                usleep(2_000_000);
                $containerStatus = $api->checkContainerStatus($creationId);
                $tries++;
            } while ($containerStatus !== 'FINISHED' && $tries < 5);

            $igMediaId = $api->publishMedia($creationId);
            $detail    = $api->getMediaDetails($igMediaId);
            $permalink = (string) ($detail['permalink'] ?? '');

            $repo->markPublished($postId, $igMediaId, $permalink);
        } catch (RuntimeException $e) {
            $repo->markError($postId, $e->getMessage());
        }
    }

    /**
     * Salva arquivos $_FILES['medias'] no repositório de mídias.
     *
     * @param array<string,mixed> $files   $_FILES
     * @param array<string,mixed> $post    $_POST
     */
    private function saveUploadedMedia(
        InstagramPostRepository $repo,
        int $postId,
        array $files,
        array $post,
    ): void {
        $appUrl   = rtrim((string) config('app.url', ''), '/');
        $uploaded = $files['medias'] ?? [];

        if (!is_array($uploaded) || empty($uploaded['name'])) {
            // Tenta usar URLs já fornecidas em $_POST (biblioteca)
            $libraryUrls = $post['media_urls'] ?? [];
            if (is_array($libraryUrls)) {
                foreach ($libraryUrls as $ordem => $url) {
                    $repo->addMedia($postId, [
                        'ordem'       => (int) $ordem,
                        'tipo_arquivo' => 'imagem',
                        'caminho'     => (string) $url,
                        'url_publica' => (str_starts_with((string) $url, 'http') ? (string) $url : $appUrl . '/' . ltrim((string) $url, '/')),
                    ]);
                }
            }
            return;
        }

        $names    = (array) $uploaded['name'];
        $tmpNames = (array) $uploaded['tmp_name'];
        $errors   = (array) ($uploaded['error'] ?? []);

        $uploadsDir = __DIR__ . '/../../../public/uploads/instagram/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        foreach ($names as $i => $name) {
            $tmpName = (string) ($tmpNames[$i] ?? '');
            $err     = (int) ($errors[$i] ?? UPLOAD_ERR_NO_FILE);

            if ($err !== UPLOAD_ERR_OK || $tmpName === '') {
                continue;
            }

            $ext      = strtolower((string) pathinfo((string) $name, PATHINFO_EXTENSION));
            $filename = date('Ymd-His') . '-' . $i . '.' . $ext;
            $dest     = $uploadsDir . $filename;

            if (move_uploaded_file($tmpName, $dest)) {
                $relative  = 'uploads/instagram/' . $filename;
                $tipoArq   = in_array($ext, ['mp4', 'mov', 'avi'], true) ? 'video' : 'imagem';
                $urlPublic = $appUrl . '/' . $relative;

                $repo->addMedia($postId, [
                    'ordem'        => $i,
                    'tipo_arquivo' => $tipoArq,
                    'caminho'      => $relative,
                    'url_publica'  => $urlPublic,
                ]);
            }
        }
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
     * Persiste o payload de insights em formato padronizado.
     *
     * @param array<string,mixed> $apiResponse
     */
    private function persistInsights(
        InstagramPostRepository $repo,
        int $accountId,
        string $period,
        array $apiResponse,
    ): void {
        $data   = $apiResponse['data'] ?? [];
        $totals = ['reach' => 0, 'impressions' => 0, 'profile_views' => 0, 'total_interactions' => 0];

        if (is_array($data)) {
            foreach ($data as $metric) {
                $name   = (string) ($metric['name'] ?? '');
                $values = (array) ($metric['values'] ?? []);
                $sum    = 0;
                foreach ($values as $v) {
                    $sum += (int) ($v['value'] ?? 0);
                }
                if (array_key_exists($name, $totals)) {
                    $totals[$name] = $sum;
                }
            }
        }

        $repo->upsertInsightsCache([
            'account_id'      => $accountId,
            'periodo'         => $period,
            'data_referencia' => date('Y-m-d'),
            'alcance'         => $totals['reach'],
            'impressoes'      => $totals['impressions'],
            'visitas_perfil'  => $totals['profile_views'],
            'interacoes'      => $totals['total_interactions'],
            'payload_raw'     => $apiResponse,
        ]);
    }
}
