<?php
/**
 * -----------------------------------------------------------------------------
 * Arquivo: app/Services/Site/OperationalTestService.php
 * Projeto: Estrategia Nerd
 * Proposito: Fornecer utilitarios, contratos e eventos da suite operacional.
 * Uso: Usado por AutomatedTestService para HTTP, login, testes e bloqueios.
 * Observacoes: Nao executa deploy, restore, Dropbox, backup ou escrita remota.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services\Site;

final class OperationalTestService
{
    public const LEVEL_SAFE = 'safe';
    public const LEVEL_ROUTINE = 'routine';
    public const LEVEL_FULL = 'full';
    public const LEVEL_UNIT = 'unit';

    public const ENV_LOCAL = 'local';
    public const ENV_STAGE = 'stage';

    public const STATUS_OK = 'ok';
    public const STATUS_FAIL = 'fail';
    public const STATUS_SKIP = 'skip';
    public const STATUS_BLOCKED = 'blocked';

    public const RULE_PRODUCTION_NOT_SUPPORTED = 'PRODUCTION_NOT_SUPPORTED';
    public const RULE_ROUTINE_NOT_IMPLEMENTED = 'ROUTINE_NOT_IMPLEMENTED';
    public const RULE_FULL_BLOCKED = 'FULL_BLOCKED';
    public const RULE_DEPLOY_DISABLED = 'DEPLOY_DISABLED';
    public const RULE_RESTORE_DISABLED = 'RESTORE_DISABLED';
    public const RULE_BACKUP_WITH_UPLOADS_DISABLED = 'BACKUP_WITH_UPLOADS_DISABLED';
    public const RULE_DROPBOX_UPLOAD_DISABLED = 'DROPBOX_UPLOAD_DISABLED';
    public const RULE_DATA_SYNC_DISABLED = 'DATA_SYNC_DISABLED';
    public const RULE_STAGE_WRITE_DISABLED = 'STAGE_WRITE_DISABLED';

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(private array $config)
    {
    }

    /**
     * @return list<string>
     */
    public function allowedLevels(): array
    {
        return [self::LEVEL_SAFE, self::LEVEL_ROUTINE, self::LEVEL_FULL, self::LEVEL_UNIT];
    }

    /**
     * @return list<string>
     */
    public function allowedEnvironments(): array
    {
        return [self::ENV_LOCAL, self::ENV_STAGE];
    }

    public function normalizeLevel(string $level): string
    {
        $level = strtolower(trim($level));
        if (!in_array($level, $this->allowedLevels(), true)) {
            throw new \RuntimeException('Nivel invalido para testes automatizados.');
        }

        return $level;
    }

    public function normalizeEnvironment(string $environment): string
    {
        $environment = strtolower(trim($environment));
        if (!in_array($environment, $this->allowedEnvironments(), true)) {
            throw new \RuntimeException('Ambiente invalido para testes automatizados.');
        }

        return $environment;
    }

    /**
     * @return array<string,mixed>
     */
    public function environmentConfig(string $environment): array
    {
        $environment = $this->normalizeEnvironment($environment);
        $profile = ((array) ($this->config['environments'] ?? []))[$environment] ?? null;
        if (!is_array($profile)) {
            throw new \RuntimeException('Ambiente sem configuracao de testes automatizados.');
        }

        return $profile;
    }

    public function baseUrl(string $environment): string
    {
        $profile = $this->environmentConfig($environment);
        $baseUrl = rtrim((string) ($profile['base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            throw new \RuntimeException('URL base nao configurada para testes automatizados.');
        }

        return $baseUrl;
    }

    public function hasAdminCredentials(): bool
    {
        return trim((string) ($this->config['admin']['user'] ?? '')) !== ''
            && trim((string) ($this->config['admin']['password'] ?? '')) !== '';
    }

    /**
     * @return array<string,mixed>
     */
    public function request(string $url, string $method = 'GET', array $fields = [], ?string $cookieJar = null): array
    {
        $started = microtime(true);
        $timeout = max(3, (int) ($this->config['timeout_seconds'] ?? 20));

        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 4,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => 'EstrategiaNerdAutomatedTests/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROXY => '',
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

        return [
            'status' => 0,
            'body' => '',
            'error' => 'cURL nao esta disponivel para os testes automatizados.',
            'effective_url' => $url,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    /**
     * @param list<int> $expectedStatuses
     * @param list<string> $requiredFragments
     * @return array<string,mixed>
     */
    public function httpTest(string $group, string $name, string $url, array $expectedStatuses, array $requiredFragments, ?string $cookieJar = null): array
    {
        $response = $this->request($url, 'GET', [], $cookieJar);
        $status = (int) ($response['status'] ?? 0);
        $body = (string) ($response['body'] ?? '');
        $missing = $this->missingFragments($body, $requiredFragments);
        $errorSignature = $this->firstErrorSignature($body);
        $ok = in_array($status, $expectedStatuses, true) && $missing === [] && $errorSignature === '';

        return [
            'group' => $group,
            'name' => $name,
            'status' => $ok ? self::STATUS_OK : self::STATUS_FAIL,
            'http_status' => $status,
            'duration_ms' => (int) ($response['duration_ms'] ?? 0),
            'url' => $url,
            'message' => $ok ? 'Resposta dentro do esperado.' : $this->failureMessage($status, $expectedStatuses, $missing, $errorSignature, (string) ($response['error'] ?? '')),
        ];
    }

    /**
     * @return array{cookie_jar:string,tests:list<array<string,mixed>>,admin_body:string}
     */
    public function login(string $baseUrl): array
    {
        $cookieJar = tempnam(sys_get_temp_dir(), 'en-automated-cookie-');
        if ($cookieJar === false) {
            return [
                'cookie_jar' => '',
                'tests' => [$this->failed('admin', 'Preparar sessao admin', 'Nao foi possivel preparar cookie jar temporario.')],
                'admin_body' => '',
            ];
        }

        $tests = [];
        $loginUrl = rtrim($baseUrl, '/') . '/login';
        $loginPage = $this->request($loginUrl, 'GET', [], $cookieJar);
        $csrf = $this->csrfFromHtml((string) ($loginPage['body'] ?? ''));
        if ($csrf === '') {
            $tests[] = $this->failed('admin', 'Login do admin', 'Token CSRF nao encontrado na tela de login.', (int) ($loginPage['status'] ?? 0), (int) ($loginPage['duration_ms'] ?? 0), $loginUrl);
            return ['cookie_jar' => $cookieJar, 'tests' => $tests, 'admin_body' => ''];
        }

        $login = $this->request($loginUrl, 'POST', [
            '_csrf_token' => $csrf,
            'usuario' => trim((string) ($this->config['admin']['user'] ?? '')),
            'senha' => (string) ($this->config['admin']['password'] ?? ''),
        ], $cookieJar);

        $body = (string) ($login['body'] ?? '');
        $loginOk = (int) ($login['status'] ?? 0) === 200
            && !$this->isLoginPage($body, (string) ($login['effective_url'] ?? ''))
            && $this->firstErrorSignature($body) === '';

        $tests[] = [
            'group' => 'admin',
            'name' => 'Login do admin',
            'status' => $loginOk ? self::STATUS_OK : self::STATUS_FAIL,
            'http_status' => (int) ($login['status'] ?? 0),
            'duration_ms' => (int) ($login['duration_ms'] ?? 0),
            'url' => $loginUrl,
            'message' => $loginOk ? 'Login efetuado com sucesso.' : 'Login nao carregou area autenticada.',
        ];

        return ['cookie_jar' => $cookieJar, 'tests' => $tests, 'admin_body' => $body];
    }

    /**
     * @return array<string,mixed>
     */
    public function logout(string $baseUrl, string $cookieJar, string $adminBody): array
    {
        $adminUrl = rtrim($baseUrl, '/') . '/admin';
        if ($adminBody === '') {
            $adminPage = $this->request($adminUrl, 'GET', [], $cookieJar);
            $adminBody = (string) ($adminPage['body'] ?? '');
        }

        $csrf = $this->csrfFromHtml($adminBody);
        if ($csrf === '') {
            return $this->failed('admin', 'Logout do admin', 'Token CSRF nao encontrado apos login.', null, 0, $adminUrl);
        }

        $logoutUrl = rtrim($baseUrl, '/') . '/logout';
        $logout = $this->request($logoutUrl, 'POST', ['_csrf_token' => $csrf], $cookieJar);
        $afterLogout = $this->request($adminUrl, 'GET', [], $cookieJar);
        $logoutOk = in_array((int) ($logout['status'] ?? 0), [200, 302], true)
            && $this->isLoginPage((string) ($afterLogout['body'] ?? ''), (string) ($afterLogout['effective_url'] ?? ''));

        return [
            'group' => 'admin',
            'name' => 'Logout do admin',
            'status' => $logoutOk ? self::STATUS_OK : self::STATUS_FAIL,
            'http_status' => (int) ($logout['status'] ?? 0),
            'duration_ms' => (int) (($logout['duration_ms'] ?? 0) + ($afterLogout['duration_ms'] ?? 0)),
            'url' => $logoutUrl,
            'message' => $logoutOk ? 'Logout encerrou a sessao.' : 'A sessao parece continuar ativa apos logout.',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function securityBlock(string $action, string $rule, string $reason, string $environment, string $level): array
    {
        return [
            'action' => $action,
            'status' => self::STATUS_BLOCKED,
            'rule' => $rule,
            'reason' => $reason,
            'timestamp' => date('c'),
            'environment' => $environment,
            'level' => $level,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function skipped(string $group, string $name, string $message): array
    {
        return [
            'group' => $group,
            'name' => $name,
            'status' => self::STATUS_SKIP,
            'http_status' => null,
            'duration_ms' => 0,
            'url' => '',
            'message' => $message,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function failed(string $group, string $name, string $message, ?int $httpStatus = null, int $durationMs = 0, string $url = ''): array
    {
        return [
            'group' => $group,
            'name' => $name,
            'status' => self::STATUS_FAIL,
            'http_status' => $httpStatus,
            'duration_ms' => $durationMs,
            'url' => $url,
            'message' => $message,
        ];
    }

    public function csrfFromHtml(string $html): string
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
     * @param list<string> $fragments
     * @return list<string>
     */
    public function missingFragments(string $body, array $fragments): array
    {
        $missing = [];
        foreach ($fragments as $fragment) {
            if ($fragment !== '' && stripos($body, $fragment) === false) {
                $missing[] = $fragment;
            }
        }

        return $missing;
    }

    public function firstErrorSignature(string $body): string
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

    public function isLoginPage(string $body, string $effectiveUrl): bool
    {
        return stripos($effectiveUrl, '/login') !== false
            || (
                stripos($body, 'name="usuario"') !== false
                && stripos($body, 'name="senha"') !== false
                && stripos($body, 'Login') !== false
            );
    }

    /**
     * @param list<string> $fragments
     */
    public function firstAssetUrl(string $html, string $baseUrl): ?string
    {
        if (preg_match('~(?:href|src)=["\']([^"\']+\.(?:css|js|png|jpg|jpeg|webp|ico)(?:\?[^"\']*)?)["\']~i', $html, $matches)) {
            return $this->absoluteUrl((string) $matches[1], $baseUrl);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function assetUrlsFromHtml(string $html, string $baseUrl): array
    {
        preg_match_all('~(?:href|src)=["\']([^"\']+\.(?:css|js|png|jpg|jpeg|webp|ico)(?:\?[^"\']*)?)["\']~i', $html, $matches);
        $urls = [];
        foreach ((array) ($matches[1] ?? []) as $url) {
            $urls[] = $this->absoluteUrl((string) $url, $baseUrl);
        }

        return array_values(array_unique($urls));
    }

    public function absoluteUrl(string $url, string $baseUrl): string
    {
        if (preg_match('~^https?://~i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
    }

    public function resultRoot(): string
    {
        return (string) ($this->config['result_root'] ?? dirname(__DIR__, 3) . '/storage/automated-tests');
    }

    /**
     * @param list<int> $expectedStatuses
     * @param list<string> $missing
     */
    private function failureMessage(int $status, array $expectedStatuses, array $missing, string $errorSignature, string $error): string
    {
        if ($error !== '') {
            return $error;
        }

        if ($errorSignature !== '') {
            return 'Assinatura de erro encontrada: ' . $errorSignature;
        }

        if ($missing !== []) {
            return 'Resposta sem trecho esperado: ' . implode(', ', $missing);
        }

        return sprintf('HTTP %d fora do esperado (%s).', $status, implode(', ', $expectedStatuses));
    }
}
