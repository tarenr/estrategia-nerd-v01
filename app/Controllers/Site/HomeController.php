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

    public function heroPreview(): void
    {
        $viewModel = $this->service()->getViewModel();
        $viewModel['title'] = 'Preview Hero Circuito | Estrategia Nerd';
        $viewModel['canonical_url'] = url('/local/preview/home-hero-circuito');
        $viewModel['meta_robots'] = 'noindex, nofollow';
        $viewModel['structured_data'] = [];

        View::render('site/home-hero-preview', $viewModel);
    }

    public function conversionPreview(): void
    {
        $viewModel = $this->service()->getViewModel();
        $viewModel['title'] = 'Preview Home Editorial | Estrategia Nerd';
        $viewModel['canonical_url'] = url('/home-preview');
        $viewModel['meta_robots'] = 'noindex,nofollow';
        $viewModel['structured_data'] = [];

        View::render('site/home-conversion-preview', $viewModel);
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
