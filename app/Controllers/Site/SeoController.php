<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Repositories\CategoriaPostRepository;
use App\Repositories\PostRepository;
use App\Services\Site\SitemapService;
use PDO;

final class SeoController
{
    public function robots(): void
    {
        header('Content-Type: text/plain; charset=UTF-8');

        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /login',
            'Disallow: /local/',
            'Disallow: /dev',
            '',
            'Sitemap: ' . url('/sitemap.xml'),
        ];

        echo implode("\n", $lines) . "\n";
    }

    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=UTF-8');

        echo $this->sitemapService()->renderXml();
    }

    private function posts(): PostRepository
    {
        /** @var PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new PostRepository($pdo);
    }

    private function categories(): CategoriaPostRepository
    {
        /** @var PDO $pdo */
        $pdo = $GLOBALS['pdo'];

        return new CategoriaPostRepository($pdo);
    }

    private function sitemapService(): SitemapService
    {
        return new SitemapService($this->posts(), $this->categories());
    }
}
