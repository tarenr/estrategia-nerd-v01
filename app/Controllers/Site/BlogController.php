<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Repositories\CategoriaPostRepository;
use App\Repositories\PostRepository;
use App\Services\Site\BlogService;
use App\Support\View;

final class BlogController
{
    private const RESERVED_CATEGORY_SLUGS = [
        'page',
        'tag',
        'autor',
        'author',
        'search',
        'busca',
        'feed',
        'post',
        'categoria',
        'admin',
        'rss',
        'sitemap',
    ];

    public function index(): void
    {
        if (!site_section_public_active('blog')) {
            http_response_code(404);
            echo 'Blog indisponível no momento.';
            return;
        }

        $legacyCategory = trim((string) ($_GET['categoria'] ?? ''));
        if (array_key_exists('categoria', $_GET)) {
            $target = $legacyCategory !== '' && $legacyCategory !== 'all'
                ? url('/blog/' . rawurlencode($legacyCategory))
                : url('/blog');
            $query = [];
            $search = trim((string) ($_GET['q'] ?? ''));
            $page = max(1, (int) ($_GET['page'] ?? 1));
            if ($search !== '') {
                $query['q'] = $search;
            }
            if ($page > 1) {
                $query['page'] = $page;
            }
            if ($query !== []) {
                $target .= '?' . http_build_query($query);
            }

            header('Location: ' . $target, true, 301);
            exit;
        }

        View::render('site/blog', $this->service()->getViewModel([
            'busca' => (string) ($_GET['q'] ?? ''),
            'page' => (int) ($_GET['page'] ?? 1),
        ]));
    }

    public function category(string $slug): void
    {
        if (!site_section_public_active('blog')) {
            http_response_code(404);
            echo 'Blog indisponivel no momento.';
            return;
        }

        $slug = strtolower(trim($slug));
        if ($slug === '' || in_array($slug, self::RESERVED_CATEGORY_SLUGS, true)) {
            $this->notFound($slug);
            return;
        }

        $service = $this->service();
        $category = $service->findCategoryForRoute($slug);
        if ($category === null) {
            $this->notFound($slug);
            return;
        }

        View::render('site/blog', $service->getViewModel([
            'busca' => (string) ($_GET['q'] ?? ''),
            'categoria' => $slug,
            'category_route' => true,
            'resolved_category' => $category,
            'page' => (int) ($_GET['page'] ?? 1),
        ]));
    }

    private function notFound(string $slug): void
    {
        http_response_code(404);

        View::render('site/error-404', [
            'title' => 'Categoria não encontrada | Estratégia Nerd',
            'meta_description' => 'A categoria solicitada não foi encontrada no blog do Estratégia Nerd.',
            'requested_path' => '/blog/' . $slug,
        ]);
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
