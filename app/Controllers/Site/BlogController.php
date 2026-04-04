<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Repositories\PostRepository;
use App\Services\Site\BlogService;
use App\Support\View;

final class BlogController
{
    public function index(): void
    {
        View::render('site/blog', $this->service()->getViewModel());
    }

    private function service(): BlogService
    {
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new BlogService(new PostRepository($pdo));
    }
}
