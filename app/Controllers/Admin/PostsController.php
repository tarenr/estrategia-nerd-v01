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
use App\Services\Admin\PostsService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\View;

final class PostsController
{
    public function index(): void
    {
        $viewModel = $this->service()->getIndexViewModel($_GET);
        View::render('admin/posts/index', $viewModel);
    }

    public function create(): void
    {
        $viewModel = $this->service()->getCreateViewModel();
        View::render('admin/posts/create', $viewModel);
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        $result = $this->service()->createPost($_POST, Auth::id());

        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
            View::render('admin/posts/create', $result['viewModel'] ?? []);
            return;
        }

        header('Location: ' . url('/admin/posts?created=1'));
        exit;
    }

    private function service(): PostsService
    {
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new PostsService(
            new PostRepository($pdo),
            new CategoriaPostRepository($pdo),
        );
    }
}
