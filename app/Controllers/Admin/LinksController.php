<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\LinkRepository;
use App\Services\Admin\LinksService;
use App\Services\Admin\MidiaService;
use App\Support\Csrf;
use App\Support\View;

final class LinksController
{
    public function index(): void
    {
        View::render('admin/links/index', $this->service()->getIndexViewModel($_GET));
    }

    public function create(): void
    {
        View::render('admin/links/create', $this->service()->getCreateViewModel());
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->createLink($_POST);
        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
            View::render('admin/links/create', $result['viewModel'] ?? []);
            return;
        }

        header('Location: ' . url('/admin/links?created=1'));
        exit;
    }

    public function edit(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $viewModel = $this->service()->getEditViewModel($id);
        if ($viewModel === null) {
            http_response_code(404);
            echo 'Link nao encontrado.';
            return;
        }

        View::render('admin/links/edit', $viewModel);
    }

    public function update(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->updateLink($id, $_POST);
        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Link nao encontrado.';
            return;
        }

        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
            View::render('admin/links/edit', $result['viewModel'] ?? []);
            return;
        }

        header('Location: ' . url('/admin/links?updated=1'));
        exit;
    }

    public function deleteConfirm(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $viewModel = $this->service()->getDeleteViewModel($id);
        if ($viewModel === null) {
            http_response_code(404);
            echo 'Link nao encontrado.';
            return;
        }

        View::render('admin/links/delete', $viewModel);
    }

    public function destroy(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->deleteLink($id);
        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Link nao encontrado.';
            return;
        }

        header('Location: ' . url('/admin/links?deleted=1'));
        exit;
    }

    private function service(): LinksService
    {
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new LinksService(new LinkRepository($pdo), new MidiaService());
    }
}
