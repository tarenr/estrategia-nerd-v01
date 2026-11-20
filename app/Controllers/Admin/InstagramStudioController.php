<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Controllers/Admin/InstagramStudioController.php
 * @project     Estrategia Nerd
 * @purpose     Controller do Instagram Studio Pro (instagrapi - segundo dashboard)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Instagram\InstagramStudioService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\EnvironmentGuard;
use App\Support\View;
use Throwable;

final class InstagramStudioController
{
    private InstagramStudioService $service;

    public function __construct()
    {
        EnvironmentGuard::requireLocal();
        Auth::require();

        if ((Auth::user()['papel'] ?? null) !== 'admin') {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => false, 'message' => 'Acesso exclusivo de administradores.']);
            exit;
        }

        $this->service = new InstagramStudioService();
    }

    /**
     * Dashboard visual principal do Instagram Studio.
     */
    public function index(): void
    {
        $csrfToken = Csrf::token();
        try {
            $connection = $this->service->getStatus();
        } catch (Throwable $e) {
            $connection = [
                'ok' => false,
                'authenticated' => false,
                'message' => 'Motor offline ou em inicialização: ' . $e->getMessage()
            ];
        }

        $highlightsStatus = [
            'ok' => false,
            'account' => 'estrategia_nerd',
            'runtime_ready' => false,
            'session_stored' => false,
            'verified_at' => null,
            'jobs' => [],
        ];
        try {
            $highlightsStatus = (new \App\Services\Instagram\InstagramHighlightsService())->getStatus();
        } catch (Throwable) {
            // Fallback silencioso para manter o carregamento seguro
        }

        View::render('admin/instagram-studio/index', [
            'title' => 'Instagram Studio Pro',
            'endpoint' => url('/admin/instagram-studio'),
            'highlights_endpoint' => url('/admin/instagram-destaques'),
            'csrf_token' => $csrfToken,
            'connection' => $connection,
            'highlights_status' => $highlightsStatus,
        ]);
    }

    /**
     * Retorna o status de autenticação da sessão via AJAX.
     */
    public function status(): void
    {
        $this->jsonResponse($this->service->getStatus());
    }

    /**
     * Busca faixas de áudio no catálogo oficial do Instagram.
     */
    public function searchMusic(): void
    {
        $query = (string) ($_GET['q'] ?? $_POST['q'] ?? '');
        if (trim($query) === '') {
            $this->jsonResponse(['ok' => true, 'tracks' => []]);
            return;
        }

        $this->jsonResponse($this->service->searchMusic($query));
    }

    /**
     * Busca faixas em alta/tendências no Instagram.
     */
    public function trendingMusic(): void
    {
        $this->jsonResponse($this->service->getTrendingMusic());
    }

    /**
     * Publica foto ou carrossel com música no feed.
     */
    public function uploadFeed(): void
    {
        if (!$this->checkCsrf()) {
            $this->jsonResponse(['ok' => false, 'message' => 'Token CSRF inválido ou expirado.']);
            return;
        }

        $mediaType = (string) ($_POST['media_type'] ?? 'photo');
        $caption = (string) ($_POST['caption'] ?? '');
        $startTimeMs = (int) ($_POST['start_time_ms'] ?? 0);
        $rawTrack = (string) ($_POST['track_json'] ?? '');

        $trackDict = null;
        if ($rawTrack !== '') {
            $decoded = json_decode($rawTrack, true);
            if (is_array($decoded)) {
                $trackDict = $decoded;
            }
        }

        $tempBase = base_path('storage/tmp/instagram-studio');
        if (!is_dir($tempBase)) {
            @mkdir($tempBase, 0755, true);
        }

        if ($mediaType === 'album') {
            $uploadedFiles = $_FILES['files'] ?? null;
            if (!$uploadedFiles || !is_array($uploadedFiles['name'] ?? null)) {
                $this->jsonResponse(['ok' => false, 'message' => 'Nenhum slide enviado para o carrossel.']);
                return;
            }

            $paths = [];
            $count = count($uploadedFiles['name']);
            if ($count < 2 || $count > 10) {
                $this->jsonResponse(['ok' => false, 'message' => 'Carrossel requer entre 2 e 10 imagens.']);
                return;
            }

            for ($i = 0; $i < $count; $i++) {
                if (($uploadedFiles['error'][$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                    continue;
                }
                $ext = strtolower(pathinfo($uploadedFiles['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    continue;
                }
                $dest = $tempBase . '/' . uniqid('slide_', true) . '.' . $ext;
                if (move_uploaded_file($uploadedFiles['tmp_name'][$i], $dest)) {
                    $paths[] = $dest;
                }
            }

            if (count($paths) < 2) {
                $this->jsonResponse(['ok' => false, 'message' => 'Menos de 2 slides válidos foram processados.']);
                return;
            }

            $result = $this->service->uploadFeed([
                'media_type' => 'album',
                'paths' => $paths,
                'caption' => $caption,
                'track' => $trackDict,
                'start_time_ms' => $startTimeMs,
            ]);

            // Limpeza de arquivos temporários locais
            foreach ($paths as $p) {
                @unlink($p);
            }

            $this->jsonResponse($result);
            return;
        }

        // Foto única
        $file = $_FILES['file'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $this->jsonResponse(['ok' => false, 'message' => 'Nenhum arquivo de imagem válido enviado.']);
            return;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $this->jsonResponse(['ok' => false, 'message' => 'Formato inválido. Envie JPG, PNG ou WebP.']);
            return;
        }

        $dest = $tempBase . '/' . uniqid('feed_', true) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $this->jsonResponse(['ok' => false, 'message' => 'Falha ao salvar arquivo temporário no servidor.']);
            return;
        }

        $result = $this->service->uploadFeed([
            'media_type' => 'photo',
            'path' => $dest,
            'caption' => $caption,
            'track' => $trackDict,
            'start_time_ms' => $startTimeMs,
        ]);

        @unlink($dest);
        $this->jsonResponse($result);
    }

    /**
     * Consulta dados completos do perfil.
     */
    public function getProfile(): void
    {
        $this->jsonResponse($this->service->getProfile());
    }

    /**
     * Atualiza biografia e link externo da bio.
     */
    public function updateProfile(): void
    {
        if (!$this->checkCsrf()) {
            $this->jsonResponse(['ok' => false, 'message' => 'Token CSRF inválido ou expirado.']);
            return;
        }

        $bio = isset($_POST['biography']) ? (string) $_POST['biography'] : null;
        $url = isset($_POST['external_url']) ? (string) $_POST['external_url'] : null;

        $this->jsonResponse($this->service->updateProfile($bio, $url));
    }

    /**
     * Fixa ou desafixa uma mídia no topo da grade.
     */
    public function pinMedia(): void
    {
        if (!$this->checkCsrf()) {
            $this->jsonResponse(['ok' => false, 'message' => 'Token CSRF inválido ou expirado.']);
            return;
        }

        $mediaId = (string) ($_POST['media_id'] ?? '');
        $pin = (bool) ($_POST['pin'] ?? true);

        if ($mediaId === '') {
            $this->jsonResponse(['ok' => false, 'message' => 'Identificador da mídia ausente.']);
            return;
        }

        $this->jsonResponse($this->service->pinMedia($mediaId, $pin));
    }

    /**
     * Pesquisa conteúdos virais de concorrentes ou hashtags.
     */
    public function searchViral(): void
    {
        $target = (string) ($_GET['target'] ?? $_POST['target'] ?? '');
        $isHashtag = (bool) ($_GET['is_hashtag'] ?? $_POST['is_hashtag'] ?? false);
        $amount = (int) ($_GET['amount'] ?? $_POST['amount'] ?? 9);

        if (trim($target) === '') {
            $this->jsonResponse(['ok' => true, 'posts' => []]);
            return;
        }

        $this->jsonResponse($this->service->searchViral($target, $isHashtag, $amount));
    }

    /**
     * Envia mensagem direta (DM).
     */
    public function sendDm(): void
    {
        if (!$this->checkCsrf()) {
            $this->jsonResponse(['ok' => false, 'message' => 'Token CSRF inválido ou expirado.']);
            return;
        }

        $username = (string) ($_POST['username'] ?? '');
        $text = (string) ($_POST['text'] ?? '');

        if (trim($username) === '' || trim($text) === '') {
            $this->jsonResponse(['ok' => false, 'message' => 'Destinatário e mensagem são obrigatórios.']);
            return;
        }

        $this->jsonResponse($this->service->sendDm($username, $text));
    }

    /**
     * Retorna métricas ao vivo da conta: engajamento, ranking dos top posts e comparativo.
     */
    public function metrics(): void
    {
        $this->jsonResponse($this->service->getMetrics());
    }

    /**
     * Retorna espectadores nominais dos stories ativos.
     */
    public function storyViewers(): void
    {
        $this->jsonResponse($this->service->getStoryViewers());
    }

    /**
     * Valida o token CSRF vindo via POST ou header.
     */
    private function checkCsrf(): bool
    {
        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (is_string($token) && Csrf::validate($token)) {
            return true;
        }

        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $data = json_decode($raw, true);
            if (is_array($data) && isset($data['_csrf_token']) && is_string($data['_csrf_token'])) {
                return Csrf::validate($data['_csrf_token']);
            }
        }

        return false;
    }

    /**
     * Emite resposta JSON segura.
     *
     * @param array<string, mixed> $data
     */
    private function jsonResponse(array $data): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
