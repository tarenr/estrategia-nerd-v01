<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\CategoriaPostRepository;
use App\Services\Admin\CategoriasService;
use App\Support\Csrf;
use App\Support\View;

final class CategoriasController
{
    public function index(): void
    {
        View::render('admin/categories/index', $this->service()->getIndexViewModel($_GET));
    }

    public function create(): void
    {
        View::render('admin/categories/create', $this->service()->getCreateViewModel());
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->createCategoria($_POST);
        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
            View::render('admin/categories/create', $result['viewModel'] ?? []);
            return;
        }

        header('Location: ' . url('/admin/categorias?created=1'));
        exit;
    }

    public function edit(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $viewModel = $this->service()->getEditViewModel($id);
        if ($viewModel === null) {
            http_response_code(404);
            echo 'Categoria nao encontrada.';
            return;
        }

        View::render('admin/categories/edit', $viewModel);
    }

    public function update(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->updateCategoria($id, $_POST);
        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Categoria nao encontrada.';
            return;
        }

        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
            View::render('admin/categories/edit', $result['viewModel'] ?? []);
            return;
        }

        header('Location: ' . url('/admin/categorias?updated=1'));
        exit;
    }

    public function deleteConfirm(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $viewModel = $this->service()->getDeleteViewModel($id);
        if ($viewModel === null) {
            http_response_code(404);
            echo 'Categoria nao encontrada.';
            return;
        }

        View::render('admin/categories/delete', $viewModel);
    }

    public function destroy(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->deleteCategoria($id);
        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Categoria nao encontrada.';
            return;
        }

        $mode = (string) ($result['mode'] ?? 'deleted');
        header('Location: ' . url('/admin/categorias?' . ($mode === 'deactivated' ? 'deactivated=1' : 'deleted=1')));
        exit;
    }

    private function service(): CategoriasService
    {
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new CategoriasService(new CategoriaPostRepository($pdo));
    }
}
