<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Controllers/Admin/PostsController.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Controlar o fluxo da central de posts no painel admin
 * @description Recebe a requisição, delega para o Service e renderiza a View.
 * @usage       GET /admin/posts
 * @notes       Não conter SQL nem regra de negócio; apenas orquestração.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Repositories\CategoriaPostRepository;
use App\Repositories\PostRepository;
use App\Services\Admin\PostsService;
use App\Support\View;

final class PostsController
{
    public function index(): void
    {
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        $postsRepo = new PostRepository($pdo);
        $categoriasRepo = new CategoriaPostRepository($pdo);

        $service = new PostsService($postsRepo, $categoriasRepo);

        $viewModel = $service->getIndexViewModel($_GET);

        View::render('admin/posts/index', $viewModel);
    }
}