<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Support\EnvironmentManager;
use App\Support\SystemActivityLogger;
use App\Support\TargetEnvironmentDatabase;
use PDO;
use Throwable;

final class AuditService
{
    private const ENVIRONMENTS = ['local', 'stage', 'production'];

    /**
     * @var array<string, object|false|null>
     */
    private array $ftpConnections = [];

    /**
     * @var array<string, bool|null>
     */
    private array $storageReady = [];

    /**
     * @var array<string, bool>
     */
    private array $assetExistsCache = [];

    /**
     * @return array<string, mixed>
     */
    public function getViewModel(): array
    {
        $startedAt = microtime(true);
        $environments = [];

        foreach (self::ENVIRONMENTS as $environment) {
            $environments[] = $this->auditEnvironment($environment);
        }

        $summary = $this->buildSummary($environments, $startedAt);

        SystemActivityLogger::write('system', 'general_audit_ran', [
            'current_environment' => current_environment(),
            'target_environment' => current_environment(),
            'duration_ms' => $summary['duration_ms'],
            'overall_status' => $summary['overall_status'],
            'critical_findings' => $summary['critical_findings'],
            'warning_findings' => $summary['warning_findings'],
            'environments' => array_map(static function (array $item): array {
                return [
                    'environment' => (string) ($item['environment'] ?? ''),
                    'status' => (string) ($item['status'] ?? ''),
                    'critical' => (int) (($item['summary']['critical'] ?? 0)),
                    'warning' => (int) (($item['summary']['warning'] ?? 0)),
                ];
            }, $environments),
        ]);

        return [
            'title' => 'Auditoria Geral',
            'summary' => $summary,
            'environments' => $environments,
            'execution_environment' => current_environment(),
            'execution_environment_label' => environment_label(current_environment()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditEnvironment(string $environment): array
    {
        $normalized = EnvironmentManager::normalize($environment);
        $label = environment_label($normalized);
        $checks = [];
        $criticalFindings = [];
        $warningFindings = [];
        $metrics = [
            'published_posts' => 0,
            'categories' => 0,
            'links' => 0,
        ];

        try {
            $profile = TargetEnvironmentDatabase::profile($normalized);
            $pdo = TargetEnvironmentDatabase::pdo($normalized);

            $checks[] = $this->makeCheck('Banco editorial', 'ok', 'Conectado', 'A conexao com o banco editorial do ambiente foi aberta com sucesso.');
            $checks[] = $this->checkSiteUrl($pdo, $normalized);
            $checks[] = $this->checkUploadsAccess($normalized, $profile);

            $categories = $this->fetchCategories($pdo);
            $posts = $this->fetchPublishedPosts($pdo);
            $links = $this->countLinks($pdo);
            $metrics['published_posts'] = count($posts);
            $metrics['categories'] = count($categories);
            $metrics['links'] = $links;

            $checks[] = $this->makeCheck(
                'Conteudo publicado',
                count($posts) > 0 ? 'ok' : 'warn',
                (string) count($posts),
                count($posts) > 0
                    ? 'Quantidade de posts publicados encontrada com sucesso.'
                    : 'Nenhum post publicado foi encontrado neste ambiente.'
            );
            $checks[] = $this->makeCheck('Categorias', count($categories) > 0 ? 'ok' : 'warn', (string) count($categories), 'Leitura da taxonomia editorial do ambiente.');
            $checks[] = $this->makeCheck('Links ativos/cadastrados', 'ok', (string) $links, 'Quantidade de itens encontrados na area de links.');

            $this->collectEditorialFindings($normalized, $pdo, $posts, $categories, $criticalFindings, $warningFindings, $checks);
            $this->collectRouteChecks($normalized, $pdo, $posts, $checks, $criticalFindings, $warningFindings);
        } catch (Throwable $exception) {
            $checks[] = $this->makeCheck('Diagnostico do ambiente', 'fail', 'Falha', $exception->getMessage());
            $criticalFindings[] = [
                'title' => 'Nao foi possivel auditar este ambiente',
                'detail' => $exception->getMessage(),
            ];
        } finally {
            $this->closeFtpConnection($normalized);
        }

        $summary = $this->buildEnvironmentSummary($checks, $criticalFindings, $warningFindings);

        return [
            'environment' => $normalized,
            'label' => $label,
            'status' => $summary['status'],
            'summary' => $summary,
            'checks' => $checks,
            'metrics' => $metrics,
            'critical_findings' => $criticalFindings,
            'warning_findings' => $warningFindings,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     * @param array<int, array<string, string>> $criticalFindings
     * @param array<int, array<string, string>> $warningFindings
     * @return array<string, mixed>
     */
    private function buildEnvironmentSummary(array $checks, array $criticalFindings, array $warningFindings): array
    {
        $ok = 0;
        $warn = 0;
        $fail = 0;

        foreach ($checks as $check) {
            $status = (string) ($check['status'] ?? 'warn');
            if ($status === 'ok') {
                $ok++;
            } elseif ($status === 'fail') {
                $fail++;
            } else {
                $warn++;
            }
        }

        $criticalCount = count($criticalFindings);
        $warningCount = count($warningFindings);
        $status = 'ok';
        $headline = 'Ambiente sem bloqueios relevantes na V1 da auditoria.';

        if ($criticalCount > 0 || $fail > 0) {
            $status = 'fail';
            $headline = 'Ambiente com achados criticos que merecem correcao antes de publicar.';
        } elseif ($warningCount > 0 || $warn > 0) {
            $status = 'warn';
            $headline = 'Ambiente utilizavel, mas com alertas editoriais ou tecnicos.';
        }

        return [
            'status' => $status,
            'headline' => $headline,
            'ok' => $ok,
            'warn' => $warn,
            'fail' => $fail,
            'critical' => $criticalCount,
            'warning' => $warningCount,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $environments
     * @return array<string, mixed>
     */
    private function buildSummary(array $environments, float $startedAt): array
    {
        $critical = 0;
        $warning = 0;
        $okEnvironments = 0;
        $failEnvironments = 0;
        $warnEnvironments = 0;

        foreach ($environments as $environment) {
            $summary = (array) ($environment['summary'] ?? []);
            $critical += (int) ($summary['critical'] ?? 0);
            $warning += (int) ($summary['warning'] ?? 0);

            $status = (string) ($environment['status'] ?? 'warn');
            if ($status === 'ok') {
                $okEnvironments++;
            } elseif ($status === 'fail') {
                $failEnvironments++;
            } else {
                $warnEnvironments++;
            }
        }

        $overallStatus = 'ok';
        $headline = 'Os tres ambientes passaram na leitura inicial da auditoria.';
        if ($critical > 0 || $failEnvironments > 0) {
            $overallStatus = 'fail';
            $headline = 'Existe pelo menos um ambiente com bloqueios reais nesta auditoria.';
        } elseif ($warning > 0 || $warnEnvironments > 0) {
            $overallStatus = 'warn';
            $headline = 'A auditoria encontrou alertas que merecem acompanhamento.';
        }

        return [
            'overall_status' => $overallStatus,
            'headline' => $headline,
            'environments_total' => count($environments),
            'environments_ok' => $okEnvironments,
            'environments_warn' => $warnEnvironments,
            'environments_fail' => $failEnvironments,
            'critical_findings' => $critical,
            'warning_findings' => $warning,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'checked_at' => date('d/m/Y H:i:s'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkSiteUrl(PDO $pdo, string $environment): array
    {
        $siteUrl = $this->fetchConfigValue($pdo, 'site_url');
        if ($siteUrl === '') {
            return $this->makeCheck('URL principal do portal', 'fail', '(nao configurada)', 'A configuracao site_url esta vazia no ambiente.');
        }

        if ($environment !== 'local' && str_contains(strtolower($siteUrl), 'localhost')) {
            return $this->makeCheck('URL principal do portal', 'fail', $siteUrl, 'A URL principal ainda aponta para localhost neste ambiente remoto.');
        }

        return $this->makeCheck('URL principal do portal', 'ok', $siteUrl, 'Valor carregado da configuracao publica principal do portal.');
    }

    /**
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    private function checkUploadsAccess(string $environment, array $profile): array
    {
        $status = $this->storageReady($environment, $profile);
        if ($status === true) {
            $uploads = (array) ($profile['uploads'] ?? []);
            $mode = strtoupper((string) ($uploads['mode'] ?? 'local'));
            $root = $mode === 'LOCAL'
                ? (string) ($uploads['path'] ?? '-')
                : (string) ($uploads['root'] ?? '-');

            return $this->makeCheck('Storage de uploads', 'ok', $mode, 'O auditor conseguiu validar o acesso ao storage de uploads do ambiente. ' . $root);
        }

        if ($status === null) {
            return $this->makeCheck('Storage de uploads', 'warn', 'Leitura parcial', 'Nao foi possivel validar o storage remoto agora. As checagens de midia podem ficar incompletas.');
        }

        return $this->makeCheck('Storage de uploads', 'fail', 'Falha', 'O storage configurado para uploads nao respondeu como esperado neste ambiente.');
    }

    /**
     * @param array<int, array<string, mixed>> $posts
     * @param array<int, array<string, mixed>> $categories
     * @param array<int, array<string, string>> $criticalFindings
     * @param array<int, array<string, string>> $warningFindings
     * @param array<int, array<string, mixed>> $checks
     */
    private function collectEditorialFindings(
        string $environment,
        PDO $pdo,
        array $posts,
        array $categories,
        array &$criticalFindings,
        array &$warningFindings,
        array &$checks
    ): void {
        $categoryMap = [];
        foreach ($categories as $category) {
            $categoryMap[(int) ($category['id'] ?? 0)] = $category;
        }

        $publishedIds = [];
        foreach ($posts as $post) {
            $publishedIds[(int) ($post['id'] ?? 0)] = true;
        }

        $missingCoverThumb = 0;
        $missingMedia = 0;
        $missingSeo = 0;
        $inactiveCategoryUsage = 0;
        $invalidNextStep = 0;

        foreach ($posts as $post) {
            $postId = (int) ($post['id'] ?? 0);
            $postTitle = trim((string) ($post['titulo'] ?? 'Post #' . $postId));
            $slug = trim((string) ($post['slug'] ?? ''));

            $cover = $this->normalizeAssetReference((string) ($post['imagem_capa'] ?? ''));
            $thumb = $this->normalizeAssetReference((string) ($post['imagem_thumb'] ?? ''));

            foreach ([
                'capa' => $cover,
                'thumb' => $thumb,
            ] as $role => $assetPath) {
                if ($assetPath === '' || $this->assetExists($environment, $assetPath) === true) {
                    continue;
                }

                $missingCoverThumb++;
                if (count($criticalFindings) < 8) {
                    $criticalFindings[] = [
                        'title' => sprintf('Post %d sem %s valida', $postId, $role),
                        'detail' => $postTitle . ' referencia ' . ($assetPath !== '' ? $assetPath : '(vazio)') . '.',
                    ];
                }
            }

            $contentAssets = $this->extractContentAssetReferences((string) ($post['conteudo'] ?? ''));
            foreach ($contentAssets as $assetPath) {
                $exists = $this->assetExists($environment, $assetPath);
                if ($exists === false) {
                    $missingMedia++;
                    if (count($criticalFindings) < 8) {
                        $criticalFindings[] = [
                            'title' => sprintf('Midia ausente no post %d', $postId),
                            'detail' => $postTitle . ' referencia ' . $assetPath . ' no HTML publicado.',
                        ];
                    }
                }
            }

            $seoTitle = trim((string) ($post['seo_title'] ?? ''));
            $seoDescription = trim((string) ($post['seo_description'] ?? ''));
            if ($seoTitle === '' || $seoDescription === '') {
                $missingSeo++;
                if (count($warningFindings) < 8) {
                    $warningFindings[] = [
                        'title' => sprintf('SEO incompleto no post %d', $postId),
                        'detail' => $postTitle . ' esta sem SEO title ou SEO description preenchidos.',
                    ];
                }
            }

            $categoryId = (int) ($post['categoria_post_id'] ?? 0);
            $category = $categoryMap[$categoryId] ?? null;
            if ($category === null || (int) ($category['ativo'] ?? 1) !== 1) {
                $inactiveCategoryUsage++;
                if (count($warningFindings) < 8) {
                    $warningFindings[] = [
                        'title' => sprintf('Categoria invalida no post %d', $postId),
                        'detail' => $postTitle . ' aponta para categoria ausente ou inativa.',
                    ];
                }
            }

            $nextStepId = $this->readNextStepId($pdo, $post);
            if ($nextStepId > 0 && !isset($publishedIds[$nextStepId])) {
                $invalidNextStep++;
                if (count($criticalFindings) < 8) {
                    $criticalFindings[] = [
                        'title' => sprintf('Proximo passo invalido no post %d', $postId),
                        'detail' => $postTitle . ' aponta para o post ' . $nextStepId . ', que nao esta publicado.',
                    ];
                }
            }
        }

        $checks[] = $this->makeCheck(
            'Capas e thumbs publicadas',
            $missingCoverThumb === 0 ? 'ok' : 'fail',
            $missingCoverThumb === 0 ? 'Sem faltas' : (string) $missingCoverThumb,
            $missingCoverThumb === 0
                ? 'Os posts publicados possuem capa e thumb resolvidas no storage.'
                : 'Foram encontradas capas ou thumbs faltando nos posts publicados.'
        );
        $checks[] = $this->makeCheck(
            'Midia referenciada no HTML',
            $missingMedia === 0 ? 'ok' : 'fail',
            $missingMedia === 0 ? 'Sem faltas' : (string) $missingMedia,
            $missingMedia === 0
                ? 'Nao foram encontradas referencias quebradas no HTML dos posts publicados.'
                : 'Existem arquivos referenciados no HTML publicado que nao foram encontrados.'
        );
        $checks[] = $this->makeCheck(
            'SEO editorial',
            $missingSeo === 0 ? 'ok' : 'warn',
            $missingSeo === 0 ? 'Completo' : (string) $missingSeo,
            $missingSeo === 0
                ? 'Todos os posts publicados auditados possuem SEO title e description.'
                : 'Foram encontrados posts publicados com SEO incompleto.'
        );
        $checks[] = $this->makeCheck(
            'Integridade de categoria',
            $inactiveCategoryUsage === 0 ? 'ok' : 'warn',
            $inactiveCategoryUsage === 0 ? 'Coerente' : (string) $inactiveCategoryUsage,
            $inactiveCategoryUsage === 0
                ? 'Todos os posts publicados apontam para categorias ativas.'
                : 'Existem posts publicados apontando para categoria ausente ou inativa.'
        );
        $checks[] = $this->makeCheck(
            'Proximo passo',
            $invalidNextStep === 0 ? 'ok' : 'fail',
            $invalidNextStep === 0 ? 'Coerente' : (string) $invalidNextStep,
            $invalidNextStep === 0
                ? 'Os relacionamentos de proximo passo auditados apontam para posts publicados.'
                : 'Existem posts publicados com proximo passo invalido.'
        );
    }

    /**
     * @param array<int, array<string, mixed>> $posts
     * @param array<int, array<string, mixed>> $checks
     * @param array<int, array<string, string>> $criticalFindings
     * @param array<int, array<string, string>> $warningFindings
     */
    private function collectRouteChecks(
        string $environment,
        PDO $pdo,
        array $posts,
        array &$checks,
        array &$criticalFindings,
        array &$warningFindings
    ): void {
        $siteUrl = $this->resolveBaseUrl($environment, $this->fetchConfigValue($pdo, 'site_url'));
        if ($siteUrl === '') {
            $checks[] = $this->makeCheck('Rotas criticas', 'warn', 'Ignoradas', 'A auditoria nao conseguiu resolver uma URL base para este ambiente.');
            return;
        }

        if (!extension_loaded('curl')) {
            $checks[] = $this->makeCheck('Rotas criticas', 'warn', 'cURL ausente', 'A extensao cURL nao esta habilitada para validar rotas HTTP nesta maquina.');
            return;
        }

        $routes = [
            'Home' => rtrim($siteUrl, '/') . '/',
            'Blog' => rtrim($siteUrl, '/') . '/blog',
            'Sitemap' => rtrim($siteUrl, '/') . '/sitemap.xml',
        ];

        if ($posts !== []) {
            $firstPost = $posts[0];
            $postSlug = trim((string) ($firstPost['slug'] ?? ''));
            if ($postSlug !== '') {
                $routes['Primeiro post auditado'] = rtrim($siteUrl, '/') . '/post/' . rawurlencode($postSlug);
            }
        }

        foreach ($routes as $label => $url) {
            $result = $this->probeHttp($url);
            $statusCode = (int) ($result['status_code'] ?? 0);
            $status = 'fail';
            $detail = $url;

            if ($statusCode === 0) {
                $status = 'warn';
                $detail = $url . ' nao respondeu nesta leitura. Isso pode indicar limite de rede, DNS ou SSL da maquina local.';
            } elseif ($statusCode >= 200 && $statusCode < 400) {
                $status = 'ok';
            }

            $checks[] = $this->makeCheck(
                'Rota: ' . $label,
                $status,
                $statusCode > 0 ? (string) $statusCode : 'Sem resposta',
                $detail
            );

            if ($status === 'fail') {
                if (count($criticalFindings) < 8) {
                    $criticalFindings[] = [
                        'title' => 'Rota critica falhou em ' . $label,
                        'detail' => $url . ' retornou ' . ($statusCode > 0 ? (string) $statusCode : 'sem resposta') . '.',
                    ];
                }
            } elseif ($status === 'warn') {
                if (count($warningFindings) < 8) {
                    $warningFindings[] = [
                        'title' => 'Rota externa sem resposta em ' . $label,
                        'detail' => $url . ' nao respondeu nesta leitura. Vale conferir novamente em outra rede ou validar manualmente no navegador.',
                    ];
                }
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPublishedPosts(PDO $pdo): array
    {
        $supportsNextStep = $this->supportsColumn($pdo, 'posts', 'proximo_post_id');
        $nextStepSelect = $supportsNextStep ? 'proximo_post_id' : 'NULL AS proximo_post_id';

        $stmt = $pdo->query(
            "SELECT id, titulo, slug, categoria_post_id, imagem_capa, imagem_thumb, seo_title, seo_description, conteudo, {$nextStepSelect}
             FROM posts
             WHERE status = 'publicado'
             ORDER BY data_publicacao DESC, id DESC"
        );

        return $stmt !== false ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchCategories(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT id, nome, slug, COALESCE(ativo, 1) AS ativo FROM categoria_post ORDER BY ordem ASC, nome ASC, id ASC');

        return $stmt !== false ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    private function countLinks(PDO $pdo): int
    {
        $stmt = $pdo->query('SELECT COUNT(*) FROM links');
        return (int) ($stmt !== false ? ($stmt->fetchColumn() ?: 0) : 0);
    }

    private function supportsColumn(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column
             LIMIT 1'
        );
        $stmt->execute([
            'table' => $table,
            'column' => $column,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $post
     */
    private function readNextStepId(PDO $pdo, array $post): int
    {
        return max(0, (int) ($post['proximo_post_id'] ?? 0));
    }

    private function fetchConfigValue(PDO $pdo, string $key): string
    {
        $stmt = $pdo->prepare('SELECT valor FROM configuracoes WHERE chave = :chave LIMIT 1');
        $stmt->execute(['chave' => $key]);
        $value = $stmt->fetchColumn();

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return array<int, string>
     */
    private function extractContentAssetReferences(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $references = [];

        preg_match_all('~(?:src|href|poster)\s*=\s*["\']([^"\']+)["\']~i', $html, $matches);
        foreach ((array) ($matches[1] ?? []) as $value) {
            $normalized = $this->normalizeAssetReference((string) $value);
            if ($normalized !== '') {
                $references[$normalized] = true;
            }
        }

        preg_match_all('~data-audio-(?:narracao|ambiente)\s*=\s*["\']([^"\']+)["\']~i', $html, $audioMatches);
        foreach ((array) ($audioMatches[1] ?? []) as $value) {
            $normalized = $this->normalizeAssetReference((string) $value);
            if ($normalized !== '') {
                $references[$normalized] = true;
            }
        }

        return array_values(array_keys($references));
    }

    private function normalizeAssetReference(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('~^(data:|blob:|https?://)~i', $value)) {
            $path = parse_url($value, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                return '';
            }
            $value = $path;
        }

        $value = ltrim(str_replace('\\', '/', $value), '/');
        if ($value === '' || !str_starts_with($value, 'uploads/')) {
            return '';
        }

        return $value;
    }

    private function assetExists(string $environment, string $relativePath): ?bool
    {
        $relativePath = $this->normalizeAssetReference($relativePath);
        if ($relativePath === '') {
            return true;
        }

        $cacheKey = $environment . '|' . $relativePath;
        if (array_key_exists($cacheKey, $this->assetExistsCache)) {
            return $this->assetExistsCache[$cacheKey];
        }

        $profile = TargetEnvironmentDatabase::profile($environment);
        $uploads = (array) ($profile['uploads'] ?? []);
        $mode = strtolower(trim((string) ($uploads['mode'] ?? 'local')));

        if ($mode === 'local') {
            $path = trim((string) ($uploads['path'] ?? ''));
            if ($path === '') {
                return $this->assetExistsCache[$cacheKey] = false;
            }

            $assetPath = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, substr($relativePath, strlen('uploads/')));
            return $this->assetExistsCache[$cacheKey] = is_file($assetPath);
        }

        if ($this->storageReady($environment, $profile) !== true) {
            return null;
        }

        $connection = $this->ftpConnection($environment, $profile);
        if (!is_object($connection) && $connection !== null) {
            return null;
        }

        $root = rtrim((string) ($uploads['root'] ?? ''), '/');
        $remotePath = $root . '/' . ltrim(substr($relativePath, strlen('uploads/')), '/');
        $size = @ftp_size($connection, $remotePath);

        return $this->assetExistsCache[$cacheKey] = $size >= 0;
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function storageReady(string $environment, array $profile): ?bool
    {
        if (array_key_exists($environment, $this->storageReady)) {
            return $this->storageReady[$environment];
        }

        $uploads = (array) ($profile['uploads'] ?? []);
        $mode = strtolower(trim((string) ($uploads['mode'] ?? 'local')));

        if ($mode === 'local') {
            $path = trim((string) ($uploads['path'] ?? ''));
            return $this->storageReady[$environment] = ($path !== '' && is_dir($path));
        }

        if (!extension_loaded('ftp')) {
            return $this->storageReady[$environment] = null;
        }

        $connection = $this->ftpConnection($environment, $profile);
        if (!is_object($connection) && $connection !== null) {
            return $this->storageReady[$environment] = null;
        }

        $root = trim((string) ($uploads['root'] ?? ''));
        if ($root === '') {
            return $this->storageReady[$environment] = false;
        }

        $ok = @ftp_chdir($connection, $root);
        if ($ok) {
            @ftp_chdir($connection, '/');
        }

        return $this->storageReady[$environment] = (bool) $ok;
    }

    /**
     * @param array<string, mixed> $profile
     * @return object|false|null
     */
    private function ftpConnection(string $environment, array $profile)
    {
        if (array_key_exists($environment, $this->ftpConnections)) {
            return $this->ftpConnections[$environment];
        }

        $uploads = (array) ($profile['uploads'] ?? []);
        $host = trim((string) ($uploads['host'] ?? ''));
        $port = (int) ($uploads['port'] ?? 21);
        $username = (string) ($uploads['username'] ?? '');
        $password = (string) ($uploads['password'] ?? '');

        if ($host === '' || $username === '') {
            return $this->ftpConnections[$environment] = null;
        }

        $connection = @ftp_connect($host, $port, 5);
        if ($connection === false) {
            return $this->ftpConnections[$environment] = false;
        }

        if (!@ftp_login($connection, $username, $password)) {
            @ftp_close($connection);
            return $this->ftpConnections[$environment] = false;
        }

        @ftp_pasv($connection, (bool) ($uploads['passive'] ?? true));

        return $this->ftpConnections[$environment] = $connection;
    }

    private function closeFtpConnection(string $environment): void
    {
        if (!array_key_exists($environment, $this->ftpConnections)) {
            return;
        }

        $connection = $this->ftpConnections[$environment];
        if (is_object($connection)) {
            @ftp_close($connection);
        }

        unset($this->ftpConnections[$environment]);
    }

    private function resolveBaseUrl(string $environment, string $siteUrl): string
    {
        $siteUrl = trim($siteUrl);
        if ($siteUrl !== '' && preg_match('~^https?://~i', $siteUrl)) {
            return rtrim($siteUrl, '/');
        }

        if ($environment === 'local') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $base = $siteUrl !== '' ? $siteUrl : app_url();
            if ($base === '') {
                return $scheme . '://' . $host;
            }

            if (preg_match('~^https?://~i', $base)) {
                return rtrim($base, '/');
            }

            return rtrim($scheme . '://' . $host . '/' . ltrim($base, '/'), '/');
        }

        return $siteUrl !== '' ? rtrim($siteUrl, '/') : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function probeHttp(string $url): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            return ['status_code' => 0];
        }

        curl_setopt_array($handle, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => 'Estratégia Nerd Auditoria/1.0',
        ]);

        curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return ['status_code' => $statusCode];
    }

    /**
     * @return array<string, string>
     */
    private function makeCheck(string $label, string $status, string $value, string $detail): array
    {
        return [
            'label' => $label,
            'status' => $status,
            'value' => $value,
            'detail' => $detail,
        ];
    }
}
