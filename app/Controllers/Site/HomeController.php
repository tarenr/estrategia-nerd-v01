<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Repositories\CategoriaPostRepository;
use App\Repositories\LinkRepository;
use App\Repositories\NewsletterRepository;
use App\Repositories\PostRepository;
use App\Services\Site\HomeService;
use App\Support\View;

final class HomeController
{
    public function index(): void
    {
        View::render('site/home', $this->service()->getViewModel());
    }

    private function service(): HomeService
    {
        /** @var \PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new HomeService(
            new PostRepository($pdo),
            new CategoriaPostRepository($pdo),
            new LinkRepository($pdo),
            new NewsletterRepository($pdo),
        );
    }
}
