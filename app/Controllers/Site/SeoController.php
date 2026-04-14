<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Repositories\CategoriaPostRepository;
use App\Repositories\PostRepository;
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

        $urls = [];
        $now = date(DATE_ATOM);
        $latestPostUpdate = $this->resolveLatestPostUpdate() ?? $now;

        $urls[] = [
            'loc' => url('/'),
            'lastmod' => $this->resolveLatestHomeUpdate() ?? $now,
            'changefreq' => 'daily',
            'priority' => '1.0',
        ];

        if (site_section_public_active('blog')) {
            $urls[] = [
                'loc' => site_section_href('blog'),
                'lastmod' => $latestPostUpdate,
                'changefreq' => 'daily',
                'priority' => '0.9',
            ];

            foreach ($this->categories()->listForBlog() as $category) {
                $slug = trim((string) ($category['slug'] ?? ''));
                if ($slug === '') {
                    continue;
                }

                $urls[] = [
                    'loc' => url('/blog?categoria=' . urlencode($slug)),
                    'lastmod' => $latestPostUpdate,
                    'changefreq' => 'weekly',
                    'priority' => '0.7',
                ];
            }
        }

        if (site_section_public_active('central_nerd')) {
            $urls[] = [
                'loc' => site_section_href('central_nerd'),
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];
        }

        $urls[] = [
            'loc' => url('/politica-de-privacidade'),
            'lastmod' => $now,
            'changefreq' => 'monthly',
            'priority' => '0.3',
        ];
        $urls[] = [
            'loc' => url('/termos-de-uso'),
            'lastmod' => $now,
            'changefreq' => 'monthly',
            'priority' => '0.3',
        ];

        foreach ($this->posts()->publishedForSitemap() as $post) {
            $slug = trim((string) ($post['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $lastmodRaw = (string) ($post['lastmod'] ?? $post['data_publicacao'] ?? '');
            $urls[] = [
                'loc' => url('/post/' . $slug),
                'lastmod' => $this->toAtom($lastmodRaw) ?? $now,
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        }

        echo $this->renderSitemap($urls);
    }

    private function renderSitemap(array $urls): string
    {
        $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

        foreach ($urls as $url) {
            $xml[] = '  <url>';
            $xml[] = '    <loc>' . htmlspecialchars((string) ($url['loc'] ?? ''), ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</loc>';
            $xml[] = '    <lastmod>' . htmlspecialchars((string) ($url['lastmod'] ?? date(DATE_ATOM)), ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</lastmod>';
            $xml[] = '    <changefreq>' . htmlspecialchars((string) ($url['changefreq'] ?? 'weekly'), ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</changefreq>';
            $xml[] = '    <priority>' . htmlspecialchars((string) ($url['priority'] ?? '0.5'), ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</priority>';
            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return implode("\n", $xml) . "\n";
    }

    private function resolveLatestHomeUpdate(): ?string
    {
        return $this->resolveLatestPostUpdate() ?? $this->toAtom((string) date('Y-m-d H:i:s'));
    }

    private function resolveLatestPostUpdate(): ?string
    {
        $items = $this->posts()->publishedForSitemap();
        $last = $items[0]['lastmod'] ?? $items[0]['data_publicacao'] ?? null;

        return is_string($last) ? $this->toAtom($last) : null;
    }

    private function toAtom(string $value): ?string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date(DATE_ATOM, $timestamp);
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
}