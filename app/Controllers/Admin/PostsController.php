<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Controllers/Admin/PostsController.php
 * @project     Estrategia Nerd
 * @purpose     Fluxo da central de posts no painel admin
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\CategoriaPostRepository;
use App\Repositories\PostRepository;
use App\Services\Admin\MidiaService;
use App\Services\Admin\PostsService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\View;

final class PostsController
{
    public function index(): void
    {
        View::render('admin/posts/index', $this->service()->getIndexViewModel($_GET));
    }

    public function create(): void
    {
        View::render('admin/posts/create', $this->service()->getCreateViewModel());
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->createPost($_POST, $_FILES, Auth::id());
        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
            View::render('admin/posts/create', $result['viewModel'] ?? []);
            return;
        }

        header('Location: ' . url('/admin/posts?created=1'));
        exit;
    }

    public function edit(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $viewModel = $this->service()->getEditViewModel($id);
        if ($viewModel === null) {
            http_response_code(404);
            echo 'Post nao encontrado.';
            return;
        }

        View::render('admin/posts/edit', $viewModel);
    }

    public function update(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->updatePost($id, $_POST, $_FILES, Auth::id());
        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Post nao encontrado.';
            return;
        }

        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
            View::render('admin/posts/edit', $result['viewModel'] ?? []);
            return;
        }

        header('Location: ' . url('/admin/editar-post?id=' . $id . '&updated=1'));
        exit;
    }

    public function uploadInlineImage(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'error' => 'Token CSRF invalido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $result = $this->service()->uploadInlineImage($_POST, $_FILES);
        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function duplicate(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->duplicatePost($id, Auth::id());
        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Post nao encontrado.';
            return;
        }

        $newId = (int) ($result['id'] ?? 0);
        header('Location: ' . url('/admin/editar-post?id=' . $newId . '&duplicated=1'));
        exit;
    }

    public function deleteConfirm(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $viewModel = $this->service()->getDeleteViewModel($id);
        if ($viewModel === null) {
            http_response_code(404);
            echo 'Post nao encontrado.';
            return;
        }

        View::render('admin/posts/delete', $viewModel);
    }

    public function destroy(): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->deletePost($id);
        if (($result['not_found'] ?? false) === true) {
            http_response_code(404);
            echo 'Post nao encontrado.';
            return;
        }

        header('Location: ' . url('/admin/posts?deleted=1'));
        exit;
    }

    private function service(): PostsService
    {
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new PostsService(
            new PostRepository($pdo),
            new CategoriaPostRepository($pdo),
            new MidiaService(),
        );
    }
}
