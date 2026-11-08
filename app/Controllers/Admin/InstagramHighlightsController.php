<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Controllers/Admin/InstagramHighlightsController.php
 * @project     Estrategia Nerd
 * @purpose     Controller do estúdio de Stories e Destaques (@estrategia_nerd)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Instagram\InstagramHighlightsService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\EnvironmentGuard;
use App\Support\View;
use Throwable;

final class InstagramHighlightsController
{
    private InstagramHighlightsService $service;

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

        $this->service = new InstagramHighlightsService();
    }

    /**
     * Página principal do estúdio de Stories e Destaques (redirecionada para a aba unificada no Studio).
     */
    public function index(): void
    {
        header('Location: ' . url('/admin/instagram-studio#stories'), true, 302);
        exit;
    }

    /**
     * Status de conexão offline e lista de jobs recentes.
     */
    public function status(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $status = $this->service->getStatus();
            echo json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Consulta detalhes de um job específico.
     */
    public function job(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $id = (string) ($_GET['id'] ?? '');
        if ($id === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Identificador da operação não informado.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $res = $this->service->getJob($id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Entrega imagem local armazenada na biblioteca privada de ativos.
     *
     * @param string $id Identificador hexadecimal de 32 caracteres
     */
    public function media(string $id): void
    {
        $this->service->serveAsset($id);
    }

    /**
     * Upload de arquivo para story ou capa.
     */
    public function upload(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (!$this->validateCsrf()) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'message' => 'Token de proteção CSRF inválido ou expirado.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $file = $_FILES['file'] ?? null;
        if (!is_array($file)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Nenhum arquivo enviado.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $kind = (string) ($_POST['kind'] ?? 'story');

        try {
            $asset = $this->service->uploadAsset($file, $kind);
            echo json_encode(['ok' => true, 'asset' => $asset], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Validação e congelamento do manifesto (offline).
     */
    public function prepare(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (!$this->validateCsrf()) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'message' => 'Token de proteção CSRF inválido ou expirado.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $payload = $this->readJsonBody();
        if ($payload === null) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Corpo da requisição em formato JSON inválido.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $res = $this->service->prepare($payload);
            echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Execução de 1 etapa da operação (atômica).
     */
    public function step(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (!$this->validateCsrf()) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'message' => 'Token de proteção CSRF inválido ou expirado.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $payload = $this->readJsonBody() ?? [];
        $jobId = (string) ($payload['job_id'] ?? $_POST['job_id'] ?? '');

        if ($jobId === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Identificador da operação não informado.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $res = $this->service->step($jobId);
            echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Consulta explícita do catálogo de destaques remotos.
     */
    public function catalog(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (!$this->validateCsrf()) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'message' => 'Token de proteção CSRF inválido ou expirado.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $res = $this->service->catalog();
            echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Reconciliação manual assistida após status incerto.
     */
    public function reconcile(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (!$this->validateCsrf()) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'message' => 'Token de proteção CSRF inválido ou expirado.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $payload = $this->readJsonBody() ?? [];
        $jobId = (string) ($payload['job_id'] ?? '');
        $remoteId = isset($payload['remote_id']) && is_string($payload['remote_id']) ? $payload['remote_id'] : null;
        $verifiedVisual = ($payload['verified_visual'] ?? null) === true;

        if (!$verifiedVisual) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Confirme explicitamente a conferência visual antes de reconciliar.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($jobId === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Identificador da operação não informado.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $res = $this->service->reconcile($jobId, $remoteId, $verifiedVisual);
            echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    private function validateCsrf(): bool
    {
        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (is_string($token) && Csrf::validate($token)) {
            return true;
        }

        // Tenta ler do body JSON se não encontrou em POST ou header
        $raw = (string) file_get_contents('php://input');
        if ($raw !== '') {
            $data = json_decode($raw, true);
            if (is_array($data) && isset($data['_csrf_token']) && is_string($data['_csrf_token'])) {
                return Csrf::validate($data['_csrf_token']);
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJsonBody(): ?array
    {
        $raw = (string) file_get_contents('php://input');
        if ($raw === '') {
            return !empty($_POST) ? $_POST : null;
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }
}
