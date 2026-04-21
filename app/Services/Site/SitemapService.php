<?php
declare(strict_types=1);

namespace App\Services\Site;

use App\Repositories\CategoriaPostRepository;
use App\Repositories\PostRepository;

final class SitemapService
{
    /** @var array<string, true> */
    private array $seen = [];

    public function __construct(
        private PostRepository $posts,
        private CategoriaPostRepository $categories,
    ) {
    }

    public function renderXml(): string
    {
        $urls = $this->urls();
        $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

        foreach ($urls as $url) {
            $xml[] = '  <url>';
            $xml[] = '    <loc>' . htmlspecialchars((string) ($url['loc'] ?? ''), ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</loc>';
            $xml[] = '    <lastmod>' . htmlspecialchars((string) ($url['lastmod'] ?? ''), ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</lastmod>';
            $xml[] = '    <changefreq>' . htmlspecialchars((string) ($url['changefreq'] ?? 'weekly'), ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</changefreq>';
            $xml[] = '    <priority>' . htmlspecialchars((string) ($url['priority'] ?? '0.5'), ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</priority>';
            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return implode("\n", $xml) . "\n";
    }

    /**
     * @return array<int, array{loc: string, lastmod: string, changefreq: string, priority: string}>
     */
    public function urls(): array
    {
        $this->seen = [];
        $urls = [];
        $latestPostUpdate = $this->latestPostUpdate();
        $staticFallback = $latestPostUpdate ?? $this->toAtomFromTimestamp(time());

        $this->addUrl($urls, url('/'), $this->staticLastmod([
            'app/Controllers/Site/HomeController.php',
            'app/Services/Site/HomeService.php',
            'app/Views/site/home.php',
        ], $staticFallback), 'daily', '1.0');

        if (site_section_public_active('blog')) {
            $this->addUrl($urls, site_section_href('blog'), $latestPostUpdate ?? $staticFallback, 'daily', '0.9');

            foreach ($this->categories->publishedForSitemap() as $category) {
                $slug = trim((string) ($category['slug'] ?? ''));
                if ($slug === '') {
                    continue;
                }

                $this->addUrl(
                    $urls,
                    url('/blog/' . rawurlencode($slug)),
                    $this->toAtom((string) ($category['lastmod'] ?? '')) ?? $latestPostUpdate ?? $staticFallback,
                    'weekly',
                    '0.7'
                );
            }
        }

        if (site_section_public_active('central_nerd')) {
            $this->addUrl($urls, site_section_href('central_nerd'), $this->staticLastmod([
                'app/Controllers/Site/CentralController.php',
                'app/Services/Site/CentralService.php',
                'app/Views/site/central-nerd.php',
            ], $staticFallback), 'weekly', '0.7');
        }

        $this->addUrl($urls, url('/politica-de-privacidade'), $this->staticLastmod([
            'app/Controllers/Site/PagesController.php',
            'app/Views/site/privacy.php',
        ], $staticFallback), 'monthly', '0.3');

        $this->addUrl($urls, url('/termos-de-uso'), $this->staticLastmod([
            'app/Controllers/Site/PagesController.php',
            'app/Views/site/terms.php',
        ], $staticFallback), 'monthly', '0.3');

        foreach ($this->posts->publishedForSitemap() as $post) {
            $slug = trim((string) ($post['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $this->addUrl(
                $urls,
                url('/post/' . rawurlencode($slug)),
                $this->toAtom((string) ($post['lastmod'] ?? $post['data_publicacao'] ?? '')) ?? $staticFallback,
                'weekly',
                '0.8'
            );
        }

        return $urls;
    }

    /**
     * @param array<int, array{loc: string, lastmod: string, changefreq: string, priority: string}> $urls
     */
    private function addUrl(array &$urls, string $loc, string $lastmod, string $changefreq, string $priority): void
    {
        $loc = $this->canonicalLoc($loc);
        if ($loc === '' || isset($this->seen[$loc])) {
            return;
        }

        $this->seen[$loc] = true;
        $urls[] = [
            'loc' => $loc,
            'lastmod' => $lastmod,
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
    }

    private function canonicalLoc(string $loc): string
    {
        $loc = trim($loc);
        if ($loc === '' || str_contains($loc, '?')) {
            return '';
        }

        return rtrim($loc, '/') === rtrim(url('/'), '/') ? rtrim($loc, '/') . '/' : rtrim($loc, '/');
    }

    private function latestPostUpdate(): ?string
    {
        foreach ($this->posts->publishedForSitemap() as $post) {
            $lastmod = $this->toAtom((string) ($post['lastmod'] ?? $post['data_publicacao'] ?? ''));
            if ($lastmod !== null) {
                return $lastmod;
            }
        }

        return null;
    }

    /**
     * @param list<string> $relativePaths
     */
    private function staticLastmod(array $relativePaths, string $fallback): string
    {
        $latest = 0;
        $root = dirname(__DIR__, 3);

        foreach ($relativePaths as $relativePath) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
            if (is_file($path)) {
                $latest = max($latest, (int) filemtime($path));
            }
        }

        return $latest > 0 ? $this->toAtomFromTimestamp($latest) : $fallback;
    }

    private function toAtom(string $value): ?string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return $this->toAtomFromTimestamp($timestamp);
    }

    private function toAtomFromTimestamp(int $timestamp): string
    {
        return date(DATE_ATOM, $timestamp);
    }
}
