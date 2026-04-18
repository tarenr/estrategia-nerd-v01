<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Admin\MidiaService;
use App\Support\Csrf;
use App\Support\View;

final class MidiaController
{
    public function index(): void
    {
        View::render('admin/media/index', $this->service()->getIndexViewModel($_GET));
    }

    public function upload(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->upload($_FILES['arquivo'] ?? null, $_GET + $_POST);
        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
            if ($this->wantsJson()) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'error' => (string) (($result['viewModel']['errors']['arquivo'] ?? null) ?: 'Falha no upload do arquivo.')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return;
            }
            View::render('admin/media/index', $result['viewModel'] ?? []);
            return;
        }

        if ($this->wantsJson()) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => true,
                'path' => (string) ($result['path'] ?? ''),
                'item' => is_array($result['item'] ?? null) ? $result['item'] : null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $redirectQuery = is_array($result['redirect_query'] ?? null) ? $result['redirect_query'] : ['uploaded' => 1];
        $redirectQuery = array_filter($redirectQuery, static fn (mixed $value): bool => !($value === '' || $value === null || $value === 0));
        $queryString = http_build_query($redirectQuery);
        header('Location: ' . url('/admin/midia' . ($queryString !== '' ? '?' . $queryString : '')));
        exit;
    }

    public function cleanupOrphans(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->cleanupVisibleOrphans($_GET + $_POST);
        $removed = max(0, (int) ($result['removed'] ?? 0));
        $failed = max(0, (int) ($result['failed'] ?? 0));
        header('Location: ' . url('/admin/midia?orphan_cleaned=1&orphan_removed=' . $removed . '&orphan_failed=' . $failed));
        exit;
    }

    public function deleteConfirm(): void
    {
        $path = (string) ($_GET['path'] ?? '');
        $viewModel = $this->service()->getDeleteViewModel($path);
        if ($viewModel === null) {
            http_response_code(404);
            echo 'Arquivo nao encontrado.';
            return;
        }

        View::render('admin/media/delete', $viewModel);
    }

    public function destroy(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $path = (string) ($_GET['path'] ?? $_POST['path'] ?? '');
        $result = $this->service()->delete($path);
        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Arquivo nao encontrado.';
            return;
        }

        header('Location: ' . url('/admin/midia?deleted=1'));
        exit;
    }


    private function wantsJson(): bool
    {
        $requestedWith = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        return $requestedWith === 'xmlhttprequest' || str_contains($accept, 'application/json');
    }

    private function service(): MidiaService
    {
        /** @var \PDO|null $pdo */
        $pdo = $GLOBALS['pdo'] ?? null;
        return new MidiaService($pdo);
    }
}
