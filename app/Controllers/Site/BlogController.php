<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Repositories\CategoriaPostRepository;
use App\Repositories\PostRepository;
use App\Services\Site\BlogService;
use App\Support\View;

final class BlogController
{
    public function index(): void
    {
        View::render('site/blog', $this->service()->getViewModel([
            'busca' => (string) ($_GET['q'] ?? ''),
            'categoria' => (string) ($_GET['categoria'] ?? ''),
            'page' => (int) ($_GET['page'] ?? 1),
        ]));
    }

    private function service(): BlogService
    {
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new BlogService(
            new PostRepository($pdo),
            new CategoriaPostRepository($pdo),
        );
    }
}
