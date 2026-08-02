<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Repositories\CategoriaPostRepository;
use PDO;

final class SearchConsoleService
{
    private const DASHBOARD_CACHE_TTL = 900;
    private const CRITICAL_URLS_CACHE_TTL = 900;
    private const NON_INDEXED_POSTS_CACHE_TTL = 3600;
    private const NON_INDEXED_POSTS_LIMIT = 24;
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const SITES_ENDPOINT = 'https://www.googleapis.com/webmasters/v3/sites';
    private const SEARCH_ANALYTICS_ENDPOINT = 'https://www.googleapis.com/webmasters/v3/sites/%s/searchAnalytics/query';
    private const SITEMAPS_ENDPOINT = 'https://www.googleapis.com/webmasters/v3/sites/%s/sitemaps';
    private const URL_INSPECTION_ENDPOINT = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';

    /**
     * @return array<string, mixed>
     */
    public function dashboard(string $section = 'resumo', ?string $inspectionUrl = null, bool $forceRefresh = false): array
    {
        $siteUrl = trim((string) ($_ENV['GOOGLE_SEARCH_CONSOLE_SITE_URL'] ?? ''));
        $configured = $this->isConfigured();
        $activeSection = in_array($section, ['resumo', 'inspecao'], true) ? $section : 'resumo';
        $cacheable = $activeSection === 'resumo' && ($inspectionUrl === null || $inspectionUrl === '');
        $cacheKey = $this->dashboardCacheKey($activeSection, $siteUrl);

        $data = [
            'configured' => $configured,
            'site_url' => $siteUrl,
            'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            'token_status' => 'Pendente',
            'connected_property' => null,
            'available_sites' => [],
            'sitemaps' => [],
            'performance' => [
                'range_label' => 'Ultimos 28 dias',
                'summary' => null,
                'top_queries' => [],
                'top_pages' => [],
            ],
            'critical_urls' => [],
            'non_indexed_posts' => [],
            'non_indexed_posts_cache' => [
                'mode' => 'not_loaded',
                'cached_count' => 0,
                'stale_count' => 0,
                'missing_count' => 0,
                'oldest_cached_at' => null,
                'newest_cached_at' => null,
                'ttl_seconds' => self::NON_INDEXED_POSTS_CACHE_TTL,
            ],
            'inspection' => [
                'requested_url' => $inspectionUrl,
                'result' => null,
                'error' => null,
            ],
            'cache' => [
                'enabled' => $cacheable,
                'hit' => false,
                'forced_refresh' => $forceRefresh,
                'cached_at' => null,
                'expires_at' => null,
                'ttl_seconds' => self::DASHBOARD_CACHE_TTL,
            ],
            'error' => null,
        ];

        if (!$configured) {
            $data['error'] = 'Credenciais do Search Console ainda nao configuradas no .env local.';
            return $data;
        }

        try {
            if ($cacheable && !$forceRefresh) {
                $cached = $this->loadDashboardCacheEntry($cacheKey);
                if ($cached !== null) {
                    return $cached;
                }
            }

            if ($activeSection === 'inspecao' && !$forceRefresh && ($inspectionUrl === null || $inspectionUrl === '')) {
                $nonIndexedPosts = $this->fetchNonIndexedPostsFromCacheOnly($siteUrl);
                $data['non_indexed_posts'] = $nonIndexedPosts['items'];
                $data['non_indexed_posts_cache'] = $nonIndexedPosts['cache'];
                $data['token_status'] = 'Cache local';

                return $data;
            }

            $accessToken = $this->fetchAccessToken();
            $data['token_status'] = 'Autenticado';

            $sitesPayload = $this->requestJson(
                'GET',
                self::SITES_ENDPOINT,
                null,
                [
                    'Authorization: Bearer ' . $accessToken,
                ]
            );

            $siteEntries = array_values((array) ($sitesPayload['siteEntry'] ?? []));
            $data['available_sites'] = array_map(
                static fn (array $site): array => [
                    'site_url' => (string) ($site['siteUrl'] ?? ''),
                    'permission_level' => (string) ($site['permissionLevel'] ?? ''),
                ],
                array_filter($siteEntries, 'is_array')
            );

            $connectedProperty = $this->findConnectedProperty($siteEntries, $siteUrl);
            $data['connected_property'] = $connectedProperty;

            if ($connectedProperty === null) {
                $data['error'] = 'A propriedade configurada no .env nao apareceu na lista de sites autorizados da conta.';
                return $data;
            }

            $encodedSiteUrl = rawurlencode($siteUrl);
            if ($activeSection === 'resumo') {
                $data['sitemaps'] = $this->fetchSitemaps($accessToken, $encodedSiteUrl);
                $data['performance'] = $this->fetchPerformance($accessToken, $encodedSiteUrl);
                $data['critical_urls'] = $this->fetchCriticalUrls($accessToken, $siteUrl);
            }

            if ($activeSection === 'inspecao') {
                $nonIndexedPosts = $this->fetchNonIndexedPosts($accessToken, $siteUrl, $forceRefresh);
                $data['non_indexed_posts'] = $nonIndexedPosts['items'];
                $data['non_indexed_posts_cache'] = $nonIndexedPosts['cache'];
            }

            if ($activeSection === 'inspecao' && $inspectionUrl !== null && $inspectionUrl !== '') {
                $data['inspection']['result'] = $this->inspectUrl($accessToken, $siteUrl, $inspectionUrl);
            }

            if ($cacheable && $data['error'] === null) {
                $data = $this->persistDashboardCacheEntry($cacheKey, $data);
            }
        } catch (\Throwable $exception) {
            $data['error'] = $exception->getMessage();
            $data['token_status'] = 'Falhou';
            $data['inspection']['error'] = $inspectionUrl !== null ? $exception->getMessage() : null;
        }

        return $data;
    }

    private function dashboardCacheKey(string $section, string $siteUrl): string
    {
        return $section . ':' . hash('sha256', $siteUrl);
    }

    public function isConfigured(): bool
    {
        return trim((string) ($_ENV['GOOGLE_SEARCH_CONSOLE_CLIENT_ID'] ?? '')) !== ''
            && trim((string) ($_ENV['GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET'] ?? '')) !== ''
            && trim((string) ($_ENV['GOOGLE_SEARCH_CONSOLE_REFRESH_TOKEN'] ?? '')) !== ''
            && trim((string) ($_ENV['GOOGLE_SEARCH_CONSOLE_SITE_URL'] ?? '')) !== '';
    }

    private function fetchAccessToken(): string
    {
        $payload = http_build_query([
            'client_id' => trim((string) ($_ENV['GOOGLE_SEARCH_CONSOLE_CLIENT_ID'] ?? '')),
            'client_secret' => trim((string) ($_ENV['GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET'] ?? '')),
            'refresh_token' => trim((string) ($_ENV['GOOGLE_SEARCH_CONSOLE_REFRESH_TOKEN'] ?? '')),
            'grant_type' => 'refresh_token',
        ]);

        $response = $this->requestRaw(
            'POST',
            self::TOKEN_ENDPOINT,
            $payload,
            ['Content-Type: application/x-www-form-urlencoded']
        );

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded) || trim((string) ($decoded['access_token'] ?? '')) === '') {
            $message = is_array($decoded)
                ? $this->extractErrorMessage($decoded)
                : 'Nao foi possivel obter access token do Google Search Console.';

            throw new \RuntimeException($message);
        }

        return trim((string) $decoded['access_token']);
    }

    /**
     * @param array<int, array<string, mixed>> $siteEntries
     * @return array<string, string>|null
     */
    private function findConnectedProperty(array $siteEntries, string $siteUrl): ?array
    {
        foreach ($siteEntries as $site) {
            if (!is_array($site)) {
                continue;
            }

            if ((string) ($site['siteUrl'] ?? '') !== $siteUrl) {
                continue;
            }

            return [
                'site_url' => (string) ($site['siteUrl'] ?? ''),
                'permission_level' => (string) ($site['permissionLevel'] ?? ''),
            ];
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchSitemaps(string $accessToken, string $encodedSiteUrl): array
    {
        $payload = $this->requestJson(
            'GET',
            sprintf(self::SITEMAPS_ENDPOINT, $encodedSiteUrl),
            null,
            ['Authorization: Bearer ' . $accessToken]
        );

        $sitemaps = [];
        foreach ((array) ($payload['sitemap'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $sitemaps[] = [
                'path' => (string) ($item['path'] ?? ''),
                'type' => (string) ($item['type'] ?? ''),
                'pending' => (bool) ($item['isPending'] ?? false),
                'warnings' => (int) ($item['warnings'] ?? 0),
                'errors' => (int) ($item['errors'] ?? 0),
                'last_submitted' => (string) ($item['lastSubmitted'] ?? ''),
                'last_downloaded' => (string) ($item['lastDownloaded'] ?? ''),
            ];
        }

        return $sitemaps;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchPerformance(string $accessToken, string $encodedSiteUrl): array
    {
        $endDate = date('Y-m-d', strtotime('-3 days'));
        $startDate = date('Y-m-d', strtotime('-30 days'));

        $summary = $this->requestJson(
            'POST',
            sprintf(self::SEARCH_ANALYTICS_ENDPOINT, $encodedSiteUrl),
            [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'rowLimit' => 1,
            ],
            [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ]
        );

        $topQueries = $this->requestJson(
            'POST',
            sprintf(self::SEARCH_ANALYTICS_ENDPOINT, $encodedSiteUrl),
            [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => ['query'],
                'rowLimit' => 5,
            ],
            [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ]
        );

        $topPages = $this->requestJson(
            'POST',
            sprintf(self::SEARCH_ANALYTICS_ENDPOINT, $encodedSiteUrl),
            [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => ['page'],
                'rowLimit' => 5,
            ],
            [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ]
        );

        $summaryRow = is_array(($summary['rows'][0] ?? null)) ? $summary['rows'][0] : null;

        return [
            'range_label' => 'Ultimos 28 dias consolidados',
            'summary' => $summaryRow === null ? null : [
                'clicks' => (float) ($summaryRow['clicks'] ?? 0),
                'impressions' => (float) ($summaryRow['impressions'] ?? 0),
                'ctr' => (float) ($summaryRow['ctr'] ?? 0),
                'position' => (float) ($summaryRow['position'] ?? 0),
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'top_queries' => $this->normalizeRows((array) ($topQueries['rows'] ?? []), 'query'),
            'top_pages' => $this->normalizeRows((array) ($topPages['rows'] ?? []), 'page'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inspectUrl(string $accessToken, string $siteUrl, string $inspectionUrl): array
    {
        $payload = $this->requestJson(
            'POST',
            self::URL_INSPECTION_ENDPOINT,
            [
                'inspectionUrl' => $inspectionUrl,
                'siteUrl' => $siteUrl,
                'languageCode' => 'pt-BR',
            ],
            [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ]
        );

        $result = is_array($payload['inspectionResult'] ?? null) ? $payload['inspectionResult'] : [];
        $indexStatus = is_array($result['indexStatusResult'] ?? null) ? $result['indexStatusResult'] : [];
        $sitemapInfo = is_array($result['sitemap'] ?? null) ? $result['sitemap'] : [];
        $inspectionResultLink = trim((string) ($result['inspectionResultLink'] ?? ''));

        return [
            'verdict' => (string) ($indexStatus['verdict'] ?? ''),
            'coverage_state' => (string) ($indexStatus['coverageState'] ?? ''),
            'indexing_state' => (string) ($indexStatus['indexingState'] ?? ''),
            'robots_txt_state' => (string) ($indexStatus['robotsTxtState'] ?? ''),
            'page_fetch_state' => (string) ($indexStatus['pageFetchState'] ?? ''),
            'google_canonical' => (string) ($indexStatus['googleCanonical'] ?? ''),
            'user_canonical' => (string) ($indexStatus['userCanonical'] ?? ''),
            'referring_urls' => array_values((array) ($indexStatus['referringUrls'] ?? [])),
            'last_crawl_time' => (string) ($indexStatus['lastCrawlTime'] ?? ''),
            'sitemaps' => array_values((array) ($sitemapInfo['sitemap'] ?? [])),
            'inspection_result_link' => $inspectionResultLink,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchCriticalUrls(string $accessToken, string $siteUrl): array
    {
        $targets = $this->criticalUrlsList($siteUrl);
        if ($targets === []) {
            return [];
        }

        $cache = $this->loadCriticalUrlsCache();
        $updatedCache = $cache;
        $results = [];
        $now = time();

        foreach ($targets as $target) {
            $url = (string) ($target['url'] ?? '');
            if ($url === '') {
                continue;
            }

            $cached = is_array($cache[$url] ?? null) ? $cache[$url] : null;
            $inspection = null;

            if ($cached !== null) {
                $cachedAt = (int) ($cached['cached_at'] ?? 0);
                if ($cachedAt > 0 && ($now - $cachedAt) <= self::CRITICAL_URLS_CACHE_TTL && is_array($cached['result'] ?? null)) {
                    $inspection = $cached['result'];
                }
            }

            if ($inspection === null) {
                $inspection = $this->inspectUrl($accessToken, $siteUrl, $url);
                $updatedCache[$url] = [
                    'cached_at' => $now,
                    'result' => $inspection,
                ];
            }

            $results[] = [
                'label' => (string) ($target['label'] ?? $url),
                'source' => (string) ($target['source'] ?? 'site'),
                'url' => $url,
                'result' => $inspection,
                'tone' => $this->inspectionTone($inspection),
            ];
        }

        if ($updatedCache !== $cache) {
            $this->persistCriticalUrlsCache($updatedCache);
        }

        return $results;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function criticalUrlsList(string $siteUrl): array
    {
        $base = rtrim($siteUrl, '/');
        if ($base === '') {
            return [];
        }

        $items = [
            ['label' => 'Home', 'source' => 'sistema', 'url' => $base . '/'],
            ['label' => 'Blog', 'source' => 'sistema', 'url' => $base . '/blog'],
        ];

        $pdo = $GLOBALS['pdo'] ?? null;
        if (!$pdo instanceof PDO) {
            return $items;
        }

        $categoriaRepository = new CategoriaPostRepository($pdo);
        foreach (array_slice($categoriaRepository->listForBlog(), 0, 4) as $category) {
            $slug = trim((string) ($category['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $items[] = [
                'label' => 'Categoria: ' . trim((string) ($category['nome'] ?? $slug)),
                'source' => 'categoria',
                'url' => $base . '/blog/' . rawurlencode($slug),
            ];
        }

        return $items;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, cache: array<string, mixed>}
     */
    private function fetchNonIndexedPosts(string $accessToken, string $siteUrl, bool $forceRefresh = false): array
    {
        $cacheMeta = [
            'mode' => $forceRefresh ? 'refreshed' : 'cache',
            'cached_count' => 0,
            'stale_count' => 0,
            'missing_count' => 0,
            'oldest_cached_at' => null,
            'newest_cached_at' => null,
            'ttl_seconds' => self::NON_INDEXED_POSTS_CACHE_TTL,
        ];
        $targets = $this->nonIndexedPostsList($siteUrl);
        if ($targets === []) {
            $cacheMeta['mode'] = 'empty';

            return [
                'items' => [],
                'cache' => $cacheMeta,
            ];
        }

        $cache = $this->loadNonIndexedPostsCache();
        $updatedCache = $cache;
        $results = [];
        $now = time();

        foreach ($targets as $target) {
            $url = (string) ($target['url'] ?? '');
            if ($url === '') {
                continue;
            }

            $cached = is_array($cache[$url] ?? null) ? $cache[$url] : null;
            $inspection = null;

            if ($cached !== null) {
                $cachedAt = (int) ($cached['cached_at'] ?? 0);
                if ($cachedAt > 0 && is_array($cached['result'] ?? null)) {
                    $cacheMeta['cached_count']++;
                    $cacheMeta['oldest_cached_at'] = $cacheMeta['oldest_cached_at'] === null
                        ? $cachedAt
                        : min((int) $cacheMeta['oldest_cached_at'], $cachedAt);
                    $cacheMeta['newest_cached_at'] = $cacheMeta['newest_cached_at'] === null
                        ? $cachedAt
                        : max((int) $cacheMeta['newest_cached_at'], $cachedAt);

                    if (($now - $cachedAt) > self::NON_INDEXED_POSTS_CACHE_TTL) {
                        $cacheMeta['stale_count']++;
                    }
                }

                if (!$forceRefresh && $cachedAt > 0 && is_array($cached['result'] ?? null)) {
                    $inspection = $cached['result'];
                }
            }

            if ($inspection === null && $forceRefresh) {
                $inspection = $this->inspectUrl($accessToken, $siteUrl, $url);
                $updatedCache[$url] = [
                    'cached_at' => $now,
                    'result' => $inspection,
                ];
                $cacheMeta['cached_count']++;
                $cacheMeta['newest_cached_at'] = $now;
                $cacheMeta['oldest_cached_at'] = $cacheMeta['oldest_cached_at'] === null
                    ? $now
                    : min((int) $cacheMeta['oldest_cached_at'], $now);
            }

            if ($inspection === null) {
                $cacheMeta['missing_count']++;
                continue;
            }

            if ($this->isIndexedInspection($inspection)) {
                continue;
            }

            $results[] = [
                'label' => (string) ($target['label'] ?? $url),
                'url' => $url,
                'lastmod' => (string) ($target['lastmod'] ?? ''),
                'result' => $inspection,
                'reason' => $this->inspectionReason($inspection),
                'tone' => $this->inspectionTone($inspection),
            ];
        }

        if ($forceRefresh && $updatedCache !== $cache) {
            $this->persistNonIndexedPostsCache($updatedCache);
            $cacheMeta['cached_count'] = count($targets);
            $cacheMeta['stale_count'] = 0;
            $cacheMeta['missing_count'] = 0;
            $cacheMeta['mode'] = 'refreshed';
        } elseif ($cacheMeta['cached_count'] === 0) {
            $cacheMeta['mode'] = 'empty';
        } elseif ($cacheMeta['stale_count'] > 0 || $cacheMeta['missing_count'] > 0) {
            $cacheMeta['mode'] = 'stale';
        }

        if (is_int($cacheMeta['oldest_cached_at'])) {
            $cacheMeta['oldest_cached_at'] = date('d/m/Y H:i', $cacheMeta['oldest_cached_at']);
        }

        if (is_int($cacheMeta['newest_cached_at'])) {
            $cacheMeta['newest_cached_at'] = date('d/m/Y H:i', $cacheMeta['newest_cached_at']);
        }

        return [
            'items' => $results,
            'cache' => $cacheMeta,
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, cache: array<string, mixed>}
     */
    private function fetchNonIndexedPostsFromCacheOnly(string $siteUrl): array
    {
        $base = rtrim($siteUrl, '/');
        $cache = $this->loadNonIndexedPostsCache();
        $now = time();
        $results = [];
        $cachedCount = 0;
        $staleCount = 0;
        $oldestCachedAt = null;
        $newestCachedAt = null;

        foreach ($cache as $url => $cached) {
            if (!is_string($url) || ($base !== '' && !str_starts_with($url, $base . '/post/'))) {
                continue;
            }

            if (!is_array($cached) || !is_array($cached['result'] ?? null)) {
                continue;
            }

            $cachedAt = (int) ($cached['cached_at'] ?? 0);
            if ($cachedAt <= 0) {
                continue;
            }

            $cachedCount++;
            $oldestCachedAt = $oldestCachedAt === null ? $cachedAt : min($oldestCachedAt, $cachedAt);
            $newestCachedAt = $newestCachedAt === null ? $cachedAt : max($newestCachedAt, $cachedAt);

            if (($now - $cachedAt) > self::NON_INDEXED_POSTS_CACHE_TTL) {
                $staleCount++;
            }

            $inspection = $cached['result'];
            if ($this->isIndexedInspection($inspection)) {
                continue;
            }

            $path = (string) parse_url($url, PHP_URL_PATH);
            $slug = trim((string) preg_replace('#^/post/#', '', $path), '/');

            $results[] = [
                'label' => $slug !== '' ? rawurldecode($slug) : $url,
                'url' => $url,
                'lastmod' => '',
                'result' => $inspection,
                'reason' => $this->inspectionReason($inspection),
                'tone' => $this->inspectionTone($inspection),
            ];

            if (count($results) >= self::NON_INDEXED_POSTS_LIMIT) {
                break;
            }
        }

        $cacheMeta = [
            'mode' => $cachedCount === 0 ? 'empty' : ($staleCount > 0 ? 'stale' : 'cache'),
            'cached_count' => $cachedCount,
            'stale_count' => $staleCount,
            'missing_count' => 0,
            'oldest_cached_at' => $oldestCachedAt !== null ? date('d/m/Y H:i', $oldestCachedAt) : null,
            'newest_cached_at' => $newestCachedAt !== null ? date('d/m/Y H:i', $newestCachedAt) : null,
            'ttl_seconds' => self::NON_INDEXED_POSTS_CACHE_TTL,
        ];

        return [
            'items' => $results,
            'cache' => $cacheMeta,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function nonIndexedPostsList(string $siteUrl): array
    {
        $base = rtrim($siteUrl, '/');
        if ($base === '') {
            return [];
        }
        $items = [];

        foreach (array_slice($this->fetchPublicSitemapPostEntries($base), 0, self::NON_INDEXED_POSTS_LIMIT) as $post) {
            $url = $this->normalizeInspectionUrl(trim((string) ($post['url'] ?? '')), $base);
            if ($url === '') {
                continue;
            }

            $label = trim((string) ($post['label'] ?? ''));
            if ($label === '') {
                $label = basename(parse_url($url, PHP_URL_PATH) ?: $url);
            }

            $items[] = [
                'label' => $label,
                'url' => $url,
                'lastmod' => (string) ($post['lastmod'] ?? ''),
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array{label:string,url:string,lastmod:string}>
     */
    private function fetchPublicSitemapPostEntries(string $baseUrl): array
    {
        $sitemapUrl = rtrim($baseUrl, '/') . '/sitemap.xml';
        $response = $this->requestRaw('GET', $sitemapUrl, null, []);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response['body']);
        libxml_clear_errors();

        if (!$xml instanceof \SimpleXMLElement) {
            throw new \RuntimeException('Nao foi possivel ler o sitemap publico da producao.');
        }

        $entries = [];
        foreach ($xml->url as $urlNode) {
            $loc = trim((string) ($urlNode->loc ?? ''));
            if ($loc === '' || !str_contains($loc, '/post/')) {
                continue;
            }

            $path = (string) parse_url($loc, PHP_URL_PATH);
            $slug = trim((string) preg_replace('#^/post/#', '', $path), '/');

            $entries[] = [
                'label' => rawurldecode($slug),
                'url' => $loc,
                'lastmod' => trim((string) ($urlNode->lastmod ?? '')),
            ];
        }

        return $entries;
    }

    private function normalizeInspectionUrl(string $url, string $siteUrl): string
    {
        $url = trim($url);
        $base = rtrim(trim($siteUrl), '/');
        if ($url === '' || $base === '') {
            return '';
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return $url;
        }

        if (preg_match('~^https?://(?:localhost|127\.0\.0\.1)(?::\d+)?(?:/estrategia-nerd)?(?:/public)?(?=/|$)~i', $url) === 1) {
            $query = parse_url($url, PHP_URL_QUERY);
            return $base . $path . (is_string($query) && $query !== '' ? '?' . $query : '');
        }

        return $url;
    }

    /**
     * @param array<string, mixed> $inspection
     */
    private function inspectionTone(array $inspection): string
    {
        $verdict = strtoupper(trim((string) ($inspection['verdict'] ?? '')));
        $coverage = strtolower(trim((string) ($inspection['coverage_state'] ?? '')));
        $indexing = strtolower(trim((string) ($inspection['indexing_state'] ?? '')));

        if ($verdict === 'PASS') {
            return 'ok';
        }

        if (str_contains($coverage, 'redirect') || str_contains($coverage, 'blocked') || str_contains($indexing, 'not indexed')) {
            return 'error';
        }

        return 'warn';
    }

    /**
     * @param array<string, mixed> $inspection
     */
    private function isIndexedInspection(array $inspection): bool
    {
        $verdict = strtoupper(trim((string) ($inspection['verdict'] ?? '')));
        $coverage = strtolower(trim((string) ($inspection['coverage_state'] ?? '')));
        $indexing = strtolower(trim((string) ($inspection['indexing_state'] ?? '')));

        if ($verdict === 'PASS') {
            return true;
        }

        $coverageLooksIndexed = str_contains($coverage, 'indexad') && !str_contains($coverage, 'nao indexad') && !str_contains($coverage, 'não indexad');
        $indexingLooksIndexed = str_contains($indexing, 'indexad') && !str_contains($indexing, 'nao indexad') && !str_contains($indexing, 'não indexad');

        if ($coverageLooksIndexed || $indexingLooksIndexed) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $inspection
     */
    private function inspectionReason(array $inspection): string
    {
        $coverage = trim((string) ($inspection['coverage_state'] ?? ''));
        if ($coverage !== '') {
            return $coverage;
        }

        $indexing = trim((string) ($inspection['indexing_state'] ?? ''));
        if ($indexing !== '') {
            return $indexing;
        }

        return 'Motivo nao informado pela API.';
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRows(array $rows, string $labelKey): array
    {
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $items[] = [
                'label' => (string) (($row['keys'][0] ?? '') ?: $labelKey),
                'clicks' => (float) ($row['clicks'] ?? 0),
                'impressions' => (float) ($row['impressions'] ?? 0),
                'ctr' => (float) ($row['ctr'] ?? 0),
                'position' => (float) ($row['position'] ?? 0),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<int, string> $headers
     * @return array<string, mixed>
     */
    private function requestJson(string $method, string $url, ?array $body = null, array $headers = []): array
    {
        $payload = $body === null ? null : (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response = $this->requestRaw($method, $url, $payload, $headers);
        $decoded = json_decode($response['body'], true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Resposta invalida da API do Search Console.');
        }

        return $decoded;
    }

    /**
     * @param array<int, string> $headers
     * @return array{status:int, body:string}
     */
    private function requestRaw(string $method, string $url, ?string $body = null, array $headers = []): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Nao foi possivel iniciar requisicao cURL para o Search Console.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            throw new \RuntimeException('Falha de rede ao consultar o Search Console: ' . $error);
        }

        if ($statusCode >= 400) {
            $decoded = json_decode((string) $responseBody, true);
            $message = is_array($decoded)
                ? $this->extractErrorMessage($decoded)
                : 'Erro nao identificado do Search Console.';

            throw new \RuntimeException(sprintf('Search Console respondeu com HTTP %d: %s', $statusCode, $message));
        }

        return [
            'status' => $statusCode,
            'body' => (string) $responseBody,
        ];
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractErrorMessage(array $decoded): string
    {
        $errorNode = $decoded['error'] ?? null;

        if (is_array($errorNode)) {
            $message = trim((string) ($errorNode['message'] ?? ''));
            $status = trim((string) ($errorNode['status'] ?? ''));

            if ($message !== '' && $status !== '') {
                return $status . ': ' . $message;
            }

            if ($message !== '') {
                return $message;
            }
        }

        if (is_string($errorNode) && $errorNode !== '') {
            $description = trim((string) ($decoded['error_description'] ?? ''));

            return $description !== '' ? $errorNode . ': ' . $description : $errorNode;
        }

        return 'Erro nao identificado do Search Console.';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadCriticalUrlsCache(): array
    {
        $file = $this->criticalUrlsCacheFile();
        if (!is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, array<string, mixed>> $cache
     */
    private function persistCriticalUrlsCache(array $cache): void
    {
        $file = $this->criticalUrlsCacheFile();
        $directory = dirname($file);

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        @file_put_contents($file, (string) json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function criticalUrlsCacheFile(): string
    {
        return base_path('storage/app/search-console/critical-urls-cache.json');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadDashboardCacheEntry(string $cacheKey): ?array
    {
        $cache = $this->loadDashboardCache();
        $entry = is_array($cache[$cacheKey] ?? null) ? $cache[$cacheKey] : null;
        if ($entry === null) {
            return null;
        }

        $cachedAt = (int) ($entry['cached_at'] ?? 0);
        $data = is_array($entry['data'] ?? null) ? $entry['data'] : null;
        if ($cachedAt <= 0 || $data === null || (time() - $cachedAt) > self::DASHBOARD_CACHE_TTL) {
            return null;
        }

        $data['cache'] = [
            'enabled' => true,
            'hit' => true,
            'forced_refresh' => false,
            'cached_at' => date('Y-m-d H:i:s', $cachedAt),
            'expires_at' => date('Y-m-d H:i:s', $cachedAt + self::DASHBOARD_CACHE_TTL),
            'ttl_seconds' => self::DASHBOARD_CACHE_TTL,
        ];

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function persistDashboardCacheEntry(string $cacheKey, array $data): array
    {
        $cachedAt = time();
        $data['cache'] = [
            'enabled' => true,
            'hit' => false,
            'forced_refresh' => (bool) ($data['cache']['forced_refresh'] ?? false),
            'cached_at' => date('Y-m-d H:i:s', $cachedAt),
            'expires_at' => date('Y-m-d H:i:s', $cachedAt + self::DASHBOARD_CACHE_TTL),
            'ttl_seconds' => self::DASHBOARD_CACHE_TTL,
        ];

        $cache = $this->loadDashboardCache();
        $cache[$cacheKey] = [
            'cached_at' => $cachedAt,
            'data' => $data,
        ];

        $file = $this->dashboardCacheFile();
        $directory = dirname($file);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        @file_put_contents($file, (string) json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $data;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadDashboardCache(): array
    {
        $file = $this->dashboardCacheFile();
        if (!is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function dashboardCacheFile(): string
    {
        return base_path('storage/app/search-console/dashboard-cache.json');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadNonIndexedPostsCache(): array
    {
        $file = $this->nonIndexedPostsCacheFile();
        if (!is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, array<string, mixed>> $cache
     */
    private function persistNonIndexedPostsCache(array $cache): void
    {
        $file = $this->nonIndexedPostsCacheFile();
        $directory = dirname($file);

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        @file_put_contents($file, (string) json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function nonIndexedPostsCacheFile(): string
    {
        return base_path('storage/app/search-console/non-indexed-posts-cache.json');
    }
}
