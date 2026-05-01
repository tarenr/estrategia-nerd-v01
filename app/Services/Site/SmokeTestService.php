<?php

declare(strict_types=1);

namespace App\Services\Site;

final class SmokeTestService
{
    /**
     * @param array<string,mixed> $config
     */
    public function __construct(private array $config)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function run(string $environment): array
    {
        $environment = $this->normalizeEnvironment($environment);
        $startedAt = microtime(true);
        $profile = $this->environmentConfig($environment);
        $baseUrl = rtrim((string) ($profile['base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            throw new \RuntimeException('URL base nao configurada para o ambiente de testes.');
        }

        $tests = [];
        $home = $this->checkHttp($tests, 'public', 'Home publica', $baseUrl . '/', [200], ['<title']);
        $this->checkHttp($tests, 'public', 'Blog', $baseUrl . '/blog', [200], ['blog']);
        $this->checkHttp($tests, 'seo', 'Sitemap XML', $baseUrl . '/sitemap.xml', [200], ['<urlset', '<loc>']);
        $this->checkHttp($tests, 'public', '404 publico', $baseUrl . '/__smoke-test-url-inexistente-' . date('YmdHis'), [404], []);

        $postUrl = $this->firstPostUrl($baseUrl);
        if ($postUrl !== null) {
            $this->checkHttp($tests, 'public', 'Post publicado do sitemap', $postUrl, [200], ['canonical']);
        } else {
            $tests[] = $this->skip('public', 'Post publicado do sitemap', 'Nenhum post encontrado no sitemap.');
        }

        $assetUrl = $this->firstAssetUrl($home['body'] ?? '', $baseUrl);
        if ($assetUrl !== null) {
            $this->checkHttp($tests, 'assets', 'Asset principal', $assetUrl, [200], []);
        } else {
            $tests[] = $this->skip('assets', 'Asset principal', 'Nenhum asset principal encontrado no HTML da home.');
        }

        $admin = $this->checkHttp($tests, 'admin', 'Entrada do admin', $baseUrl . '/admin', [200, 302], []);
        if ($this->hasAdminCredentials()) {
            $this->checkAdminLoginLogout($tests, $baseUrl, $environment);
        } else {
            $tests[] = $this->skip('admin', 'Login e logout do admin', 'Credenciais SMOKE_ADMIN_USER e SMOKE_ADMIN_PASSWORD nao configuradas.');
        }

        $summary = $this->summarize($tests);
        $result = [
            'id' => $this->buildRunId($environment),
            'environment' => $environment,
            'environment_label' => (string) ($profile['label'] ?? $environment),
            'base_url' => $baseUrl,
            'status' => $summary['status'],
            'ready_for_technical_deploy' => $environment === 'stage' && $summary['status'] === 'ok',
            'started_at' => date('c', (int) $startedAt),
            'finished_at' => date('c'),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'summary' => $summary,
            'tests' => $tests,
        ];

        $this->writeResult($result);

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    public function latest(?string $environment = null): array
    {
        $items = $this->history(20, $environment);

        return $items[0] ?? [];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function history(int $limit = 10, ?string $environment = null): array
    {
        $root = $this->resultRoot();
        if (!is_dir($root)) {
            return [];
        }

        $files = glob($root . DIRECTORY_SEPARATOR . '*.json') ?: [];
        rsort($files, SORT_STRING);
        $items = [];
        $environment = $environment !== null ? $this->normalizeEnvironment($environment) : null;

        foreach ($files as $file) {
            $payload = json_decode((string) @file_get_contents($file), true);
            if (!is_array($payload)) {
                continue;
            }

            if ($environment !== null && (string) ($payload['environment'] ?? '') !== $environment) {
                continue;
            }

            $items[] = $payload;
            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    /**
     * @return array<string,mixed>
     */
    public function viewModel(): array
    {
        $latestByEnvironment = [];
        foreach (['local', 'stage', 'production'] as $environment) {
            $latestByEnvironment[$environment] = $this->latest($environment);
        }

        return [
            'latest_by_environment' => $latestByEnvironment,
            'history' => $this->history(10),
            'environments' => $this->configuredEnvironments(),
        ];
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function configuredEnvironments(): array
    {
        $items = [];
        foreach ((array) ($this->config['environments'] ?? []) as $key => $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $items[] = [
                'key' => (string) $key,
                'label' => (string) ($profile['label'] ?? $key),
                'base_url' => rtrim((string) ($profile['base_url'] ?? ''), '/'),
            ];
        }

        return $items;
    }

    /**
     * @param array<int,array<string,mixed>> $tests
     * @param list<int> $expectedStatuses
     * @param list<string> $requiredFragments
     * @return array<string,mixed>
     */
    private function checkHttp(array &$tests, string $group, string $name, string $url, array $expectedStatuses, array $requiredFragments): array
    {
        $response = $this->request($url);
        $status = (int) ($response['status'] ?? 0);
        $body = (string) ($response['body'] ?? '');
        $missing = [];
        foreach ($requiredFragments as $fragment) {
            if ($fragment !== '' && stripos($body, $fragment) === false) {
                $missing[] = $fragment;
            }
        }

        $ok = in_array($status, $expectedStatuses, true) && $missing === [];
        $tests[] = [
            'group' => $group,
            'name' => $name,
            'status' => $ok ? 'ok' : 'fail',
            'http_status' => $status,
            'duration_ms' => (int) ($response['duration_ms'] ?? 0),
            'url' => $url,
            'message' => $ok
                ? 'Resposta dentro do esperado.'
                : $this->failureMessage($status, $expectedStatuses, $missing, (string) ($response['error'] ?? '')),
        ];

        return $response;
    }

    /**
     * @param array<string,string> $fields
     * @return array<string,mixed>
     */
    private function request(string $url, string $method = 'GET', array $fields = [], ?string $cookieJar = null): array
    {
        $started = microtime(true);
        $timeout = max(3, (int) ($this->config['timeout_seconds'] ?? 15));

        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 4,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => 'EstrategiaNerdSmokeTest/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            if ($cookieJar !== null) {
                curl_setopt($handle, CURLOPT_COOKIEJAR, $cookieJar);
                curl_setopt($handle, CURLOPT_COOKIEFILE, $cookieJar);
            }

            if (strtoupper($method) === 'POST') {
                curl_setopt($handle, CURLOPT_POST, true);
                curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($fields));
            }

            $body = curl_exec($handle);
            $error = curl_error($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $effectiveUrl = (string) curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
            curl_close($handle);

            return [
                'status' => $status,
                'body' => is_string($body) ? $body : '',
                'error' => $error,
                'effective_url' => $effectiveUrl,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'timeout' => $timeout,
                'ignore_errors' => true,
                'header' => "User-Agent: EstrategiaNerdSmokeTest/1.0\r\n",
                'content' => $fields !== [] ? http_build_query($fields) : null,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $headers = is_array($http_response_header ?? null) ? $http_response_header : [];
        $status = 0;
        if (isset($headers[0]) && preg_match('/\s(\d{3})\s/', (string) $headers[0], $matches)) {
            $status = (int) $matches[1];
        }

        return [
            'status' => $status,
            'body' => is_string($body) ? $body : '',
            'error' => is_string($body) ? '' : 'Falha ao executar requisicao HTTP.',
            'effective_url' => $url,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $tests
     */
    private function checkAdminLoginLogout(array &$tests, string $baseUrl, string $environment): void
    {
        $cookieJar = tempnam(sys_get_temp_dir(), 'en-smoke-cookie-');
        if ($cookieJar === false) {
            $tests[] = $this->failed('admin', 'Login e logout do admin', 'Nao foi possivel preparar cookie jar temporario.');
            return;
        }

        try {
            $loginPage = $this->request(rtrim($baseUrl, '/') . '/login', 'GET', [], $cookieJar);
            $csrf = $this->csrfFromHtml((string) ($loginPage['body'] ?? ''));
            if ($csrf === '') {
                $tests[] = $this->failed('admin', 'Login do admin', 'Token CSRF nao encontrado na tela de login.', (int) ($loginPage['status'] ?? 0), (int) ($loginPage['duration_ms'] ?? 0), rtrim($baseUrl, '/') . '/login');
                return;
            }

            $login = $this->request(rtrim($baseUrl, '/') . '/login', 'POST', [
                '_csrf_token' => $csrf,
                'usuario' => trim((string) ($this->config['admin']['user'] ?? ($this->config['admin']['email'] ?? ''))),
                'senha' => (string) ($this->config['admin']['password'] ?? ''),
            ], $cookieJar);
            $loginOk = (int) ($login['status'] ?? 0) === 200
                && stripos((string) ($login['body'] ?? ''), 'Credenciais') === false
                && stripos((string) ($login['body'] ?? ''), 'Dashboard') !== false;
            $tests[] = [
                'group' => 'admin',
                'name' => 'Login do admin',
                'status' => $loginOk ? 'ok' : 'fail',
                'http_status' => (int) ($login['status'] ?? 0),
                'duration_ms' => (int) ($login['duration_ms'] ?? 0),
                'url' => rtrim($baseUrl, '/') . '/login',
                'message' => $loginOk ? 'Login efetuado e dashboard carregado.' : 'Login nao carregou o dashboard esperado.',
            ];

            if (!$loginOk) {
                return;
            }

            $adminPage = $this->request(rtrim($baseUrl, '/') . '/admin', 'GET', [], $cookieJar);
            $this->checkAdminCriticalPages($tests, $baseUrl, $cookieJar, $environment);
            $this->checkAdminAssets($tests, $baseUrl, (string) ($adminPage['body'] ?? ''), $cookieJar);

            $logoutCsrf = $this->csrfFromHtml((string) ($adminPage['body'] ?? ''));
            if ($logoutCsrf === '') {
                $tests[] = $this->failed('admin', 'Logout do admin', 'Token CSRF nao encontrado apos login.', (int) ($adminPage['status'] ?? 0), (int) ($adminPage['duration_ms'] ?? 0), rtrim($baseUrl, '/') . '/admin');
                return;
            }

            $logout = $this->request(rtrim($baseUrl, '/') . '/logout', 'POST', [
                '_csrf_token' => $logoutCsrf,
            ], $cookieJar);
            $afterLogout = $this->request(rtrim($baseUrl, '/') . '/admin', 'GET', [], $cookieJar);
            $logoutOk = in_array((int) ($logout['status'] ?? 0), [200, 302], true)
                && (
                    stripos((string) ($afterLogout['body'] ?? ''), 'name="usuario"') !== false
                    || stripos((string) ($afterLogout['effective_url'] ?? ''), '/login') !== false
                );
            $tests[] = [
                'group' => 'admin',
                'name' => 'Logout do admin',
                'status' => $logoutOk ? 'ok' : 'fail',
                'http_status' => (int) ($logout['status'] ?? 0),
                'duration_ms' => (int) (($logout['duration_ms'] ?? 0) + ($afterLogout['duration_ms'] ?? 0)),
                'url' => rtrim($baseUrl, '/') . '/logout',
                'message' => $logoutOk ? 'Logout encerrou a sessao.' : 'A sessao parece continuar ativa apos logout.',
            ];
        } finally {
            @unlink($cookieJar);
        }
    }

    /**
     * @param array<int,array<string,mixed>> $tests
     */
    private function checkAdminCriticalPages(array &$tests, string $baseUrl, string $cookieJar, string $environment): void
    {
        $pages = [
            ['Dashboard', '/admin', ['Dashboard']],
            ['Posts', '/admin/posts', ['Posts']],
            ['Criar post', '/admin/criar-post', ['Criar Post', 'Post']],
            ['Links', '/admin/links', ['Links']],
            ['Criar link', '/admin/criar-link', ['link']],
            ['Midia', '/admin/midia', ['Midia', 'Arquivos']],
            ['Comentarios', '/admin/comentarios', ['Coment']],
            ['Newsletter', '/admin/newsletter', ['Newsletter']],
        ];

        if ($environment === 'local') {
            $pages[] = ['Central Operacional', '/admin/central-operacional', ['Central Operacional']];
            $pages[] = ['Base Tecnica', '/admin/base-tecnica', ['documentacao', 'mudancas']];
            $pages[] = ['Backup e Restore', '/admin/central-operacional?aba=backup-restore', ['Backup']];
        }

        foreach ($pages as [$name, $path, $fragments]) {
            $url = rtrim($baseUrl, '/') . $path;
            $response = $this->request($url, 'GET', [], $cookieJar);
            $body = (string) ($response['body'] ?? '');
            $missing = $this->missingFragments($body, $fragments);
            $errorSignature = $this->firstErrorSignature($body);
            $loginPage = $this->isLoginPage($body, (string) ($response['effective_url'] ?? ''));
            $ok = (int) ($response['status'] ?? 0) === 200 && !$loginPage && $missing === [] && $errorSignature === '';

            $tests[] = [
                'group' => 'admin',
                'name' => 'Admin: ' . $name,
                'status' => $ok ? 'ok' : 'fail',
                'http_status' => (int) ($response['status'] ?? 0),
                'duration_ms' => (int) ($response['duration_ms'] ?? 0),
                'url' => $url,
                'message' => $ok
                    ? 'Pagina critica carregou sem erro aparente.'
                    : $this->adminFailureMessage((int) ($response['status'] ?? 0), $missing, $errorSignature, $loginPage),
            ];
        }
    }

    /**
     * @param array<int,array<string,mixed>> $tests
     */
    private function checkAdminAssets(array &$tests, string $baseUrl, string $html, string $cookieJar): void
    {
        $assets = $this->assetUrlsFromHtml($html, $baseUrl);
        $critical = array_values(array_filter($assets, static function (string $asset): bool {
            return str_contains($asset, '/assets/css/')
                || str_contains($asset, '/assets/js/')
                || str_contains($asset, '/assets/brand/')
                || str_ends_with(parse_url($asset, PHP_URL_PATH) ?: '', '.ico');
        }));

        $critical = array_slice(array_values(array_unique($critical)), 0, 8);
        if ($critical === []) {
            $tests[] = $this->failed('assets', 'Assets admin criticos', 'Nenhum CSS/JS/asset critico encontrado no dashboard admin.');
            return;
        }

        foreach ($critical as $assetUrl) {
            $response = $this->request($assetUrl, 'GET', [], $cookieJar);
            $ok = (int) ($response['status'] ?? 0) === 200 && (string) ($response['body'] ?? '') !== '';
            $tests[] = [
                'group' => 'assets',
                'name' => 'Asset admin: ' . basename((string) (parse_url($assetUrl, PHP_URL_PATH) ?: $assetUrl)),
                'status' => $ok ? 'ok' : 'fail',
                'http_status' => (int) ($response['status'] ?? 0),
                'duration_ms' => (int) ($response['duration_ms'] ?? 0),
                'url' => $assetUrl,
                'message' => $ok ? 'Asset critico carregou.' : 'Asset critico nao carregou corretamente.',
            ];
        }
    }

    private function csrfFromHtml(string $html): string
    {
        if (preg_match('~name=["\']_csrf_token["\'][^>]*value=["\']([^"\']+)["\']~i', $html, $matches)) {
            return html_entity_decode((string) $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (preg_match('~value=["\']([^"\']+)["\'][^>]*name=["\']_csrf_token["\']~i', $html, $matches)) {
            return html_entity_decode((string) $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }

    /**
     * @return array<string,mixed>
     */
    private function failed(string $group, string $name, string $message, ?int $httpStatus = null, int $durationMs = 0, string $url = ''): array
    {
        return [
            'group' => $group,
            'name' => $name,
            'status' => 'fail',
            'http_status' => $httpStatus,
            'duration_ms' => $durationMs,
            'url' => $url,
            'message' => $message,
        ];
    }

    private function firstPostUrl(string $baseUrl): ?string
    {
        $response = $this->request(rtrim($baseUrl, '/') . '/sitemap.xml');
        $body = (string) ($response['body'] ?? '');
        if ($body === '') {
            return null;
        }

        if (preg_match('~<loc>([^<]+/post/[^<]+)</loc>~i', $body, $matches)) {
            return html_entity_decode((string) $matches[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        return null;
    }

    private function firstAssetUrl(string $html, string $baseUrl): ?string
    {
        if ($html === '') {
            return null;
        }

        if (preg_match('~(?:href|src)=["\']([^"\']+\.(?:css|js|png|jpg|jpeg|webp|ico)(?:\?[^"\']*)?)["\']~i', $html, $matches)) {
            return $this->absoluteUrl((string) $matches[1], $baseUrl);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function assetUrlsFromHtml(string $html, string $baseUrl): array
    {
        if ($html === '') {
            return [];
        }

        preg_match_all('~(?:href|src)=["\']([^"\']+\.(?:css|js|png|jpg|jpeg|webp|ico)(?:\?[^"\']*)?)["\']~i', $html, $matches);
        $urls = [];
        foreach ((array) ($matches[1] ?? []) as $url) {
            $urls[] = $this->absoluteUrl((string) $url, $baseUrl);
        }

        return array_values(array_unique($urls));
    }

    private function absoluteUrl(string $url, string $baseUrl): string
    {
        if (preg_match('~^https?://~i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
    }

    /**
     * @param list<int> $expectedStatuses
     * @param list<string> $missingFragments
     */
    private function failureMessage(int $status, array $expectedStatuses, array $missingFragments, string $error): string
    {
        if ($error !== '') {
            return $error;
        }

        if ($missingFragments !== []) {
            return 'Resposta sem trecho esperado: ' . implode(', ', $missingFragments);
        }

        return sprintf('HTTP %d fora do esperado (%s).', $status, implode(', ', $expectedStatuses));
    }

    /**
     * @param list<string> $fragments
     * @return list<string>
     */
    private function missingFragments(string $body, array $fragments): array
    {
        $missing = [];
        foreach ($fragments as $fragment) {
            if ($fragment !== '' && stripos($body, $fragment) === false) {
                $missing[] = $fragment;
            }
        }

        return $missing;
    }

    private function firstErrorSignature(string $body): string
    {
        foreach ([
            '~HTTP ERROR 500~i' => 'HTTP ERROR 500',
            '~(?:PHP\s+)?Fatal error\s*:|<b>\s*Fatal error\s*</b>\s*:~i' => 'Fatal error',
            '~(?:PHP\s+)?Parse error\s*:|<b>\s*Parse error\s*</b>\s*:~i' => 'Parse error',
            '~PHP Warning\s*:|<b>\s*Warning\s*</b>\s*:~i' => 'PHP Warning',
            '~PHP Notice\s*:|<b>\s*Notice\s*</b>\s*:~i' => 'PHP Notice',
            '~Undefined (?:variable|array key|index)~i' => 'Undefined value',
            '~Uncaught [A-Za-z\\\\]+~i' => 'Uncaught exception',
            '~Stack trace:\s*#0~i' => 'Stack trace',
        ] as $pattern => $label) {
            if (preg_match($pattern, $body) === 1) {
                return $label;
            }
        }

        return '';
    }

    private function isLoginPage(string $body, string $effectiveUrl): bool
    {
        return stripos($effectiveUrl, '/login') !== false
            || (
                stripos($body, 'name="usuario"') !== false
                && stripos($body, 'name="senha"') !== false
                && stripos($body, 'Login') !== false
            );
    }

    /**
     * @param list<string> $missing
     */
    private function adminFailureMessage(int $status, array $missing, string $errorSignature, bool $loginPage = false): string
    {
        if ($status !== 200) {
            return 'HTTP ' . $status . ' ao abrir pagina critica.';
        }

        if ($loginPage) {
            return 'A pagina critica redirecionou para login.';
        }

        if ($errorSignature !== '') {
            return 'Assinatura de erro encontrada: ' . $errorSignature;
        }

        if ($missing !== []) {
            return 'Resposta sem trecho esperado: ' . implode(', ', $missing);
        }

        return 'Pagina critica falhou sem detalhe especifico.';
    }

    /**
     * @return array<string,mixed>
     */
    private function skip(string $group, string $name, string $message): array
    {
        return [
            'group' => $group,
            'name' => $name,
            'status' => 'skip',
            'http_status' => null,
            'duration_ms' => 0,
            'url' => '',
            'message' => $message,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $tests
     * @return array<string,mixed>
     */
    private function summarize(array $tests): array
    {
        $counts = ['ok' => 0, 'fail' => 0, 'skip' => 0];
        foreach ($tests as $test) {
            $status = (string) ($test['status'] ?? 'fail');
            if (!array_key_exists($status, $counts)) {
                $status = 'fail';
            }

            $counts[$status]++;
        }

        return [
            'status' => $counts['fail'] > 0 ? 'fail' : 'ok',
            'ok' => $counts['ok'],
            'fail' => $counts['fail'],
            'skip' => $counts['skip'],
            'total' => count($tests),
        ];
    }

    private function hasAdminCredentials(): bool
    {
        $user = trim((string) ($this->config['admin']['user'] ?? ($this->config['admin']['email'] ?? '')));
        $password = trim((string) ($this->config['admin']['password'] ?? ''));

        return $user !== '' && $password !== '';
    }

    /**
     * @return array<string,mixed>
     */
    private function environmentConfig(string $environment): array
    {
        $profiles = (array) ($this->config['environments'] ?? []);
        $profile = $profiles[$environment] ?? null;
        if (!is_array($profile)) {
            throw new \RuntimeException('Ambiente invalido para smoke test.');
        }

        return $profile;
    }

    private function normalizeEnvironment(string $environment): string
    {
        $environment = strtolower(trim($environment));
        if (!in_array($environment, ['local', 'stage', 'production'], true)) {
            throw new \RuntimeException('Ambiente invalido para smoke test.');
        }

        return $environment;
    }

    private function buildRunId(string $environment): string
    {
        return 'SMOKE-' . strtoupper($environment) . '-' . date('Ymd-His');
    }

    private function resultRoot(): string
    {
        return (string) ($this->config['result_root'] ?? dirname(__DIR__, 3) . '/storage/smoke-tests');
    }

    /**
     * @param array<string,mixed> $result
     */
    private function writeResult(array $result): void
    {
        $root = $this->resultRoot();
        if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
            throw new \RuntimeException('Nao foi possivel criar a pasta de resultados dos testes.');
        }

        $path = $root . DIRECTORY_SEPARATOR . (string) ($result['id'] ?? ('SMOKE-' . date('Ymd-His'))) . '.json';
        file_put_contents($path, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
