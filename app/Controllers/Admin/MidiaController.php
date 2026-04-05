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
            View::render('admin/media/index', $result['viewModel'] ?? []);
            return;
        }

        header('Location: ' . url('/admin/midia?uploaded=1'));
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
        header('Location: ' . url('/admin/midia?orphan_cleaned=1&orphan_removed=' . $removed));
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

    private function service(): MidiaService
    {
        /** @var \PDO|null $pdo */
        $pdo = $GLOBALS['pdo'] ?? null;
        return new MidiaService($pdo);
    }
}
