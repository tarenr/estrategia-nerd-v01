<?php
/**
 * -----------------------------------------------------------------------------
 * Arquivo: app/Services/Site/AutomatedTestService.php
 * Projeto: Estrategia Nerd
 * Proposito: Orquestrar a suite automatizada operacional por nivel e ambiente.
 * Uso: Chamado por scripts/tests.php e futuramente pela Central Operacional.
 * Observacoes: Routine executa apenas rotinas selecionadas; full permanece bloqueado.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace App\Services\Site;

final class AutomatedTestService
{
    private const ROUTINE_SAFE = 'safe';
    private const ROUTINE_DATABASE = 'database_crud';
    private const ROUTINE_MEDIA = 'media';
    private const ROUTINE_BACKUP = 'backup_without_uploads';
    private const ROUTINE_PREFLIGHT = 'preflight';

    private OperationalTestService $operations;
    private UnitTestService $unitTests;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(private array $config, ?OperationalTestService $operations = null)
    {
        $this->operations = $operations ?? new OperationalTestService($config);
        $this->unitTests = new UnitTestService($config);
    }

    /**
     * @return array<string,mixed>
     */
    public function run(string $level, string $environment, array $selectedRoutines = []): array
    {
        $level = $this->operations->normalizeLevel($level);
        $environment = $this->operations->normalizeEnvironment($environment);
        $startedAt = microtime(true);

        $selectedRoutines = $level === OperationalTestService::LEVEL_ROUTINE
            ? $this->normalizeSelectedRoutines($selectedRoutines, $environment)
            : [];
        $tests = [];
        $securityBlocks = $this->defaultSecurityBlocks($environment, $level);
        $routinesExecuted = [];
        $createdData = [];
        $removedData = [];
        $pendingResidues = [];
        $errors = [];

        if ($level === OperationalTestService::LEVEL_UNIT) {
            try {
                if ($environment !== OperationalTestService::ENV_LOCAL) {
                    $tests[] = $this->operations->failed('unit', 'Ambiente unitario', 'Testes unitarios executam apenas no ambiente local.');
                } else {
                    $tests = $this->unitTests->run();
                    $routinesExecuted[] = 'unit_contracts_local';
                }
            } catch (\Throwable $exception) {
                $errors[] = $exception->getMessage();
                $tests[] = $this->operations->failed('unit', 'Execucao unit', $exception->getMessage());
            }
        } elseif ($level === OperationalTestService::LEVEL_ROUTINE) {
            try {
                $tests = $this->runRoutine($environment, $selectedRoutines, $routinesExecuted, $createdData, $removedData, $pendingResidues);
            } catch (\Throwable $exception) {
                $errors[] = $exception->getMessage();
                $tests[] = $this->operations->failed('routine', 'Execucao routine', $exception->getMessage());
            }
        } elseif ($level === OperationalTestService::LEVEL_FULL) {
            $securityBlocks[] = $this->operations->securityBlock(
                'full_suite',
                OperationalTestService::RULE_FULL_BLOCKED,
                'Nivel full reservado para rotinas pesadas ou externas e bloqueado por padrao.',
                $environment,
                $level
            );
        } else {
            try {
                $tests = $this->runSafe($environment);
            } catch (\Throwable $exception) {
                $errors[] = $exception->getMessage();
                $tests[] = $this->operations->failed('suite', 'Execucao safe', $exception->getMessage());
            }
        }

        $summary = $this->summarize($tests, $securityBlocks);
        $result = [
            'id' => $this->buildRunId($level, $environment),
            'environment' => $environment,
            'level' => $level,
            'selected_routines' => $selectedRoutines,
            'started_at' => date('c', (int) $startedAt),
            'finished_at' => date('c'),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'tests_executed' => count($tests),
            'tests_ok' => (int) $summary['ok'],
            'tests_failed' => (int) $summary['fail'],
            'tests_skipped' => (int) $summary['skip'],
            'routines_executed' => $routinesExecuted,
            'created_data' => $createdData,
            'removed_data' => $removedData,
            'pending_residues' => $pendingResidues,
            'security_blocks' => $securityBlocks,
            'errors' => $errors,
            'status' => (string) $summary['status'],
            'tests' => $tests,
        ];

        $this->writeResult($result);

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    public function viewModel(): array
    {
        $latestByEnvironment = [];
        $latestByLevel = [];
        foreach ($this->operations->allowedEnvironments() as $environment) {
            $latestByEnvironment[$environment] = $this->latest($environment);
            $latestByLevel[$environment] = [];
            foreach ($this->operations->allowedLevels() as $level) {
                $latestByLevel[$environment][$level] = $this->latest($environment, $level);
            }
        }

        return [
            'latest_by_environment' => $latestByEnvironment,
            'latest_by_level' => $latestByLevel,
            'history' => $this->history(10),
            'report_history' => $this->history(50),
            'report_summary' => $this->reportSummary($this->history(50)),
            'environments' => array_map(
                fn (string $environment): array => [
                    'key' => $environment,
                    'label' => (string) ($this->operations->environmentConfig($environment)['label'] ?? $environment),
                    'base_url' => $this->operations->baseUrl($environment),
                ],
                $this->operations->allowedEnvironments()
            ),
            'routine_catalog' => [
                OperationalTestService::ENV_LOCAL => $this->routineCatalog(OperationalTestService::ENV_LOCAL),
                OperationalTestService::ENV_STAGE => $this->routineCatalog(OperationalTestService::ENV_STAGE),
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $items
     * @return array<string,mixed>
     */
    private function reportSummary(array $items): array
    {
        $summary = [
            'total' => count($items),
            'ok' => 0,
            'fail' => 0,
            'blocked' => 0,
            'tests_ok' => 0,
            'tests_failed' => 0,
            'security_blocks' => 0,
            'by_level' => [],
            'by_environment' => [],
        ];

        foreach ($items as $item) {
            $status = strtolower((string) ($item['status'] ?? ''));
            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            }

            $level = strtolower((string) ($item['level'] ?? '-'));
            $environment = strtolower((string) ($item['environment'] ?? '-'));
            $summary['by_level'][$level] = (int) ($summary['by_level'][$level] ?? 0) + 1;
            $summary['by_environment'][$environment] = (int) ($summary['by_environment'][$environment] ?? 0) + 1;
            $summary['tests_ok'] += (int) ($item['tests_ok'] ?? 0);
            $summary['tests_failed'] += (int) ($item['tests_failed'] ?? 0);
            $summary['security_blocks'] += count((array) ($item['security_blocks'] ?? []));
        }

        return $summary;
    }

    /**
     * @return array<string,mixed>
     */
    public function latest(?string $environment = null, ?string $level = null): array
    {
        $history = $this->history(20, $environment, $level);

        return $history[0] ?? [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function history(int $limit = 10, ?string $environment = null, ?string $level = null): array
    {
        $root = $this->operations->resultRoot();
        if (!is_dir($root)) {
            return [];
        }

        $files = glob($root . DIRECTORY_SEPARATOR . '*.json') ?: [];
        rsort($files, SORT_STRING);
        $items = [];
        $environment = $environment !== null ? $this->operations->normalizeEnvironment($environment) : null;
        $level = $level !== null ? $this->operations->normalizeLevel($level) : null;

        foreach ($files as $file) {
            $payload = json_decode((string) @file_get_contents($file), true);
            if (!is_array($payload)) {
                continue;
            }

            if ($environment !== null && (string) ($payload['environment'] ?? '') !== $environment) {
                continue;
            }

            if ($level !== null && (string) ($payload['level'] ?? '') !== $level) {
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
     * @return list<array<string,mixed>>
     */
    private function runSafe(string $environment): array
    {
        $baseUrl = $this->operations->baseUrl($environment);
        $tests = [];

        foreach ((array) ($this->config['public_paths'] ?? []) as $pathConfig) {
            if (!is_array($pathConfig)) {
                continue;
            }

            $path = (string) ($pathConfig['path'] ?? '/');
            if ($path === '/__automated-test-url-inexistente') {
                $path .= '-' . date('YmdHis');
            }

            $tests[] = $this->operations->httpTest(
                'public',
                (string) ($pathConfig['name'] ?? $path),
                rtrim($baseUrl, '/') . $path,
                array_map('intval', (array) ($pathConfig['expected_statuses'] ?? [200])),
                array_values(array_map('strval', (array) ($pathConfig['fragments'] ?? [])))
            );
        }

        $this->appendPostFromSitemapTest($tests, $baseUrl);
        $this->appendMainAssetTest($tests, $baseUrl);

        if (!$this->operations->hasAdminCredentials()) {
            $tests[] = $this->operations->skipped('admin', 'Login/logout e paginas admin', 'Credenciais automatizadas nao configuradas.');
            return $tests;
        }

        $login = $this->operations->login($baseUrl);
        $tests = array_merge($tests, $login['tests']);
        $cookieJar = (string) ($login['cookie_jar'] ?? '');
        $adminBody = (string) ($login['admin_body'] ?? '');
        $loginOk = $this->allTestsOk((array) ($login['tests'] ?? []));

        if ($loginOk && $cookieJar !== '') {
            $this->appendAdminPageTests($tests, $baseUrl, $cookieJar, $environment);
            $this->appendAdminAssetsTests($tests, $baseUrl, $cookieJar);
            if ($environment === OperationalTestService::ENV_STAGE) {
                $this->appendStageForbiddenMenuTest($tests, $baseUrl, $cookieJar);
            }
            $tests[] = $this->operations->logout($baseUrl, $cookieJar, $adminBody);
        }

        if ($cookieJar !== '') {
            @unlink($cookieJar);
        }

        return $tests;
    }

    /**
     * @param list<string> $routinesExecuted
     * @param list<array<string,mixed>> $createdData
     * @param list<array<string,mixed>> $removedData
     * @param list<array<string,mixed>> $pendingResidues
     * @return list<array<string,mixed>>
     */
    private function runRoutine(string $environment, array $selectedRoutines, array &$routinesExecuted, array &$createdData, array &$removedData, array &$pendingResidues): array
    {
        $tests = [];
        if (in_array(self::ROUTINE_SAFE, $selectedRoutines, true)) {
            $tests = $this->runSafe($environment);
            if (!$this->allTestsOk($tests)) {
                $tests[] = $this->operations->failed('routine', 'Pre-condicao safe', 'Routine bloqueado porque a validacao safe nao passou integralmente.');
                return $tests;
            }
        }

        $runCode = strtolower('auto_' . $environment . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(2)));
        $pdo = $this->targetPdo($environment);
        $this->cleanupRoutineResidues($pdo, $runCode);

        try {
            if (in_array(self::ROUTINE_DATABASE, $selectedRoutines, true)) {
                $this->runDatabaseRoutine($pdo, $environment, $runCode, $tests, $routinesExecuted, $createdData, $removedData, $pendingResidues);
            }
            if (in_array(self::ROUTINE_MEDIA, $selectedRoutines, true)) {
                $this->runMediaRoutine($environment, $runCode, $tests, $routinesExecuted, $createdData, $removedData, $pendingResidues);
            }
            if (in_array(self::ROUTINE_BACKUP, $selectedRoutines, true)) {
                $this->runBackupRoutine($environment, $tests, $routinesExecuted, $createdData);
            }
            if (in_array(self::ROUTINE_PREFLIGHT, $selectedRoutines, true) && $environment === OperationalTestService::ENV_LOCAL) {
                $this->runPreflightRoutine($tests, $routinesExecuted);
            }
        } catch (\Throwable $exception) {
            $pendingResidues = array_merge($pendingResidues, $this->detectRoutineResidues($pdo, $runCode));
            throw $exception;
        }

        $pendingResidues = array_merge($pendingResidues, $this->detectRoutineResidues($pdo, $runCode));
        if ($pendingResidues === []) {
            $tests[] = [
                'group' => 'routine',
                'name' => 'Limpeza de residuos',
                'status' => OperationalTestService::STATUS_OK,
                'http_status' => null,
                'duration_ms' => 0,
                'url' => '',
                'message' => 'Nenhum residuo de teste pendente encontrado.',
            ];
        } else {
            $tests[] = $this->operations->failed('routine', 'Limpeza de residuos', 'Foram encontrados residuos pendentes apos a rotina.');
        }

        return $tests;
    }

    /**
     * @return list<array{key:string,label:string,description:string}>
     */
    private function routineCatalog(string $environment): array
    {
        $items = [
            ['key' => self::ROUTINE_SAFE, 'label' => 'Safe completo', 'description' => 'Rotas, login, assets e paginas criticas.'],
            ['key' => self::ROUTINE_DATABASE, 'label' => 'CRUD tecnico', 'description' => 'Cria, verifica e remove registros tecnicos temporarios.'],
            ['key' => self::ROUTINE_MEDIA, 'label' => 'Midia tecnica', 'description' => 'Cria e remove um arquivo tecnico temporario em uploads.'],
            ['key' => self::ROUTINE_BACKUP, 'label' => 'Backup sem uploads', 'description' => 'Gera e verifica backup sem uploads.'],
        ];

        if ($environment === OperationalTestService::ENV_LOCAL) {
            $items[] = ['key' => self::ROUTINE_PREFLIGHT, 'label' => 'Preflight local', 'description' => 'Executa a checagem tecnica local de preflight.'];
        }

        return $items;
    }

    /**
     * @param list<string> $selectedRoutines
     * @return list<string>
     */
    private function normalizeSelectedRoutines(array $selectedRoutines, string $environment): array
    {
        $allowed = array_column($this->routineCatalog($environment), 'key');
        $selected = array_values(array_unique(array_filter(array_map(
            static fn (mixed $item): string => strtolower(trim((string) $item)),
            $selectedRoutines
        ), static fn (string $item): bool => $item !== '')));

        if ($selected === []) {
            return [self::ROUTINE_SAFE];
        }

        return array_values(array_filter($selected, static fn (string $item): bool => in_array($item, $allowed, true)));
    }

    /**
     * @param list<array<string,mixed>> $tests
     * @param list<string> $routinesExecuted
     * @param list<array<string,mixed>> $createdData
     * @param list<array<string,mixed>> $removedData
     * @param list<array<string,mixed>> $pendingResidues
     */
    private function runDatabaseRoutine(\PDO $pdo, string $environment, string $runCode, array &$tests, array &$routinesExecuted, array &$createdData, array &$removedData, array &$pendingResidues): void
    {
        $started = microtime(true);
        $slug = str_replace('_', '-', $runCode);
        $email = $runCode . '@automated-tests.local';
        $ids = [];

        try {
            $pdo->beginTransaction();

            $ids['categoria_post'] = $this->nextId($pdo, 'categoria_post');
            $stmt = $pdo->prepare('INSERT INTO categoria_post (id, nome, slug, descricao, seo_title, seo_description, ativo, indexar, exibir_no_menu, ordem, cor) VALUES (:id, :nome, :slug, :descricao, :seo_title, :seo_description, 0, 0, 0, 9999, :cor)');
            $stmt->execute([
                'id' => $ids['categoria_post'],
                'nome' => 'AUTO TESTE ' . strtoupper($environment),
                'slug' => $slug . '-categoria',
                'descricao' => 'Registro tecnico temporario da suite automatizada.',
                'seo_title' => 'AUTO TESTE',
                'seo_description' => 'Registro tecnico temporario.',
                'cor' => '#22d3ee',
            ]);
            $createdData[] = ['type' => 'categoria_post', 'id' => $ids['categoria_post'], 'slug' => $slug . '-categoria'];

            $ids['posts'] = $this->nextId($pdo, 'posts');
            $stmt = $pdo->prepare('INSERT INTO posts (id, titulo, slug, resumo, conteudo, categoria, categoria_id, categoria_post_id, autor_id, data_publicacao, tempo_leitura, status, destaque, seo_title, seo_description, tags) VALUES (:id, :titulo, :slug, :resumo, :conteudo, :categoria, :categoria_id, :categoria_post_id, :autor_id, NOW(), 1, "rascunho", 0, :seo_title, :seo_description, :tags)');
            $stmt->execute([
                'id' => $ids['posts'],
                'titulo' => 'AUTO TESTE ' . strtoupper($environment),
                'slug' => $slug . '-post',
                'resumo' => 'Registro tecnico temporario.',
                'conteudo' => '<p>Registro tecnico temporario da suite automatizada.</p>',
                'categoria' => 'dicas',
                'categoria_id' => null,
                'categoria_post_id' => $ids['categoria_post'],
                'autor_id' => 1,
                'seo_title' => 'AUTO TESTE',
                'seo_description' => 'Registro tecnico temporario.',
                'tags' => 'auto-teste',
            ]);
            $createdData[] = ['type' => 'posts', 'id' => $ids['posts'], 'slug' => $slug . '-post'];

            $ids['comentarios'] = $this->nextId($pdo, 'comentarios');
            $stmt = $pdo->prepare('INSERT INTO comentarios (id, post_id, nome, email, comentario, status, respondido) VALUES (:id, :post_id, :nome, :email, :comentario, "pendente", 0)');
            $stmt->execute([
                'id' => $ids['comentarios'],
                'post_id' => $ids['posts'],
                'nome' => 'AUTO TESTE',
                'email' => $email,
                'comentario' => 'Comentario tecnico temporario da suite automatizada.',
            ]);
            $createdData[] = ['type' => 'comentarios', 'id' => $ids['comentarios'], 'email' => $email];

            $ids['newsletter'] = $this->nextId($pdo, 'newsletter');
            $stmt = $pdo->prepare('INSERT INTO newsletter (id, email, nome, status, ip) VALUES (:id, :email, :nome, "inativo", :ip)');
            $stmt->execute([
                'id' => $ids['newsletter'],
                'email' => $email,
                'nome' => 'AUTO TESTE',
                'ip' => '127.0.0.1',
            ]);
            $createdData[] = ['type' => 'newsletter', 'id' => $ids['newsletter'], 'email' => $email];

            $ids['links'] = $this->nextId($pdo, 'links');
            $stmt = $pdo->prepare('INSERT INTO links (id, titulo, slug, url, tipo, promocao, secao_publica, descricao, texto_botao, posicao, status, destaque, observacao_status) VALUES (:id, :titulo, :slug, :url, "conteudo", 0, "produtos", :descricao, :texto_botao, 9999, "oculto", 0, :observacao_status)');
            $stmt->execute([
                'id' => $ids['links'],
                'titulo' => 'AUTO TESTE ' . strtoupper($environment),
                'slug' => $slug . '-link',
                'url' => 'https://example.com/automated-test',
                'descricao' => 'Registro tecnico temporario.',
                'texto_botao' => 'Teste',
                'observacao_status' => 'Criado por suite automatizada e removido na mesma execucao.',
            ]);
            $createdData[] = ['type' => 'links', 'id' => $ids['links'], 'slug' => $slug . '-link'];

            $checks = [
                'categoria_post' => (int) $pdo->query('SELECT COUNT(*) FROM categoria_post WHERE slug LIKE ' . $pdo->quote($slug . '%'))->fetchColumn(),
                'posts' => (int) $pdo->query('SELECT COUNT(*) FROM posts WHERE slug LIKE ' . $pdo->quote($slug . '%'))->fetchColumn(),
                'comentarios' => (int) $pdo->query('SELECT COUNT(*) FROM comentarios WHERE email = ' . $pdo->quote($email))->fetchColumn(),
                'newsletter' => (int) $pdo->query('SELECT COUNT(*) FROM newsletter WHERE email = ' . $pdo->quote($email))->fetchColumn(),
                'links' => (int) $pdo->query('SELECT COUNT(*) FROM links WHERE slug LIKE ' . $pdo->quote($slug . '%'))->fetchColumn(),
            ];

            foreach (['comentarios', 'posts', 'categoria_post', 'newsletter', 'links'] as $table) {
                $stmt = $pdo->prepare('DELETE FROM ' . $table . ' WHERE id = :id');
                $stmt->execute(['id' => $ids[$table]]);
                $removedData[] = ['type' => $table, 'id' => $ids[$table]];
            }

            $pdo->commit();
            $routinesExecuted[] = 'database_crud_' . $environment;
            $tests[] = [
                'group' => 'routine',
                'name' => 'CRUD tecnico controlado',
                'status' => min($checks) > 0 ? OperationalTestService::STATUS_OK : OperationalTestService::STATUS_FAIL,
                'http_status' => null,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'url' => '',
                'message' => min($checks) > 0 ? 'Registros tecnicos criados, verificados e removidos.' : 'Algum registro tecnico nao foi confirmado antes da limpeza.',
            ];
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pendingResidues = array_merge($pendingResidues, $this->detectRoutineResidues($pdo, $runCode));
            throw $exception;
        }
    }

    /**
     * @param list<array<string,mixed>> $tests
     * @param list<string> $routinesExecuted
     * @param list<array<string,mixed>> $createdData
     * @param list<array<string,mixed>> $removedData
     * @param list<array<string,mixed>> $pendingResidues
     */
    private function runMediaRoutine(string $environment, string $runCode, array &$tests, array &$routinesExecuted, array &$createdData, array &$removedData, array &$pendingResidues): void
    {
        $started = microtime(true);
        $relative = 'automated-tests/' . $runCode . '.gif';
        $bytes = base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==', true);
        if (!is_string($bytes)) {
            throw new \RuntimeException('Nao foi possivel preparar midia de teste.');
        }

        $profile = $this->backupProfile($environment);
        $uploads = (array) ($profile['uploads'] ?? []);
        $mode = strtolower(trim((string) ($uploads['mode'] ?? 'local')));

        if ($mode === 'local') {
            $root = rtrim((string) ($uploads['path'] ?? ''), '/\\');
            if ($root === '') {
                throw new \RuntimeException('Uploads local sem path configurado.');
            }
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new \RuntimeException('Nao foi possivel criar pasta de midia de teste.');
            }
            file_put_contents($path, $bytes);
            $createdData[] = ['type' => 'media_file', 'path' => $path];
            $exists = is_file($path) && filesize($path) > 0;
            @unlink($path);
            @rmdir($directory);
            $removedData[] = ['type' => 'media_file', 'path' => $path];
        } elseif ($mode === 'ftp') {
            $exists = $this->ftpWriteAndDelete($uploads, $relative, $bytes);
            $createdData[] = ['type' => 'media_file', 'path' => (string) ($uploads['root'] ?? '') . '/' . $relative];
            $removedData[] = ['type' => 'media_file', 'path' => (string) ($uploads['root'] ?? '') . '/' . $relative];
        } else {
            $tests[] = $this->operations->skipped('routine', 'Midia tecnica temporaria', 'Modo de uploads nao suportado para teste: ' . $mode);
            return;
        }

        $routinesExecuted[] = 'media_write_delete_' . $environment;
        $tests[] = [
            'group' => 'routine',
            'name' => 'Midia tecnica temporaria',
            'status' => $exists ? OperationalTestService::STATUS_OK : OperationalTestService::STATUS_FAIL,
            'http_status' => null,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'url' => '',
            'message' => $exists ? 'Arquivo de midia tecnico criado e removido.' : 'Arquivo de midia tecnico nao foi confirmado.',
        ];
    }

    /**
     * @param list<array<string,mixed>> $tests
     * @param list<string> $routinesExecuted
     * @param list<array<string,mixed>> $createdData
     */
    private function runBackupRoutine(string $environment, array &$tests, array &$routinesExecuted, array &$createdData): void
    {
        $started = microtime(true);
        require_once base_path('scripts/backup/BackupManager.php');
        $manager = new \Scripts\Backup\BackupManager(require base_path('config/backup.php'));
        $manifest = $manager->run($environment, null, false);
        $backupId = (string) ($manifest['backup_id'] ?? '');
        $verification = $manager->verify($backupId !== '' ? $backupId : 'latest');
        $valid = (bool) ($verification['is_valid'] ?? false) && (bool) ($manifest['includes_uploads'] ?? true) === false;

        $routinesExecuted[] = 'backup_without_uploads_' . $environment;
        $createdData[] = ['type' => 'backup_without_uploads', 'id' => $backupId, 'path' => dirname((string) ($manifest['database']['path'] ?? ''))];
        $tests[] = [
            'group' => 'routine',
            'name' => 'Backup sem uploads',
            'status' => $valid ? OperationalTestService::STATUS_OK : OperationalTestService::STATUS_FAIL,
            'http_status' => null,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'url' => '',
            'message' => $valid ? 'Backup sem uploads gerado e verificado.' : 'Backup sem uploads nao foi validado.',
        ];
    }

    /**
     * @param list<array<string,mixed>> $tests
     * @param list<string> $routinesExecuted
     */
    private function runPreflightRoutine(array &$tests, array &$routinesExecuted): void
    {
        $started = microtime(true);
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('scripts/preflight-check.php'));
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $routinesExecuted[] = 'preflight_local';
        $tests[] = [
            'group' => 'routine',
            'name' => 'Preflight local',
            'status' => $exitCode === 0 ? OperationalTestService::STATUS_OK : OperationalTestService::STATUS_FAIL,
            'http_status' => null,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'url' => '',
            'message' => $exitCode === 0 ? 'Preflight local executado sem bloqueios.' : 'Preflight retornou codigo ' . $exitCode . '.',
        ];
    }

    private function targetPdo(string $environment): \PDO
    {
        $database = (array) ($this->backupProfile($environment)['database'] ?? []);
        $host = trim((string) ($database['host'] ?? ''));
        $port = trim((string) ($database['port'] ?? '3306'));
        $name = trim((string) ($database['database'] ?? ''));
        $username = trim((string) ($database['username'] ?? ''));
        $password = (string) ($database['password'] ?? '');

        if ($host === '' || $name === '' || $username === '') {
            throw new \RuntimeException('Banco do ambiente sem configuracao completa para rotina de teste.');
        }

        return new \PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name),
            $username,
            $password,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function backupProfile(string $environment): array
    {
        $config = require base_path('config/backup.php');
        $profile = ((array) ($config['profiles'] ?? []))[$environment] ?? null;
        if (!is_array($profile)) {
            throw new \RuntimeException('Perfil de backup/teste nao encontrado para ' . $environment . '.');
        }

        return $profile;
    }

    private function nextId(\PDO $pdo, string $table): int
    {
        $allowed = ['categoria_post', 'posts', 'comentarios', 'newsletter', 'links'];
        if (!in_array($table, $allowed, true)) {
            throw new \RuntimeException('Tabela nao permitida para rotina: ' . $table);
        }

        return max(1, (int) $pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM ' . $table)->fetchColumn());
    }

    private function cleanupRoutineResidues(\PDO $pdo, string $runCode): void
    {
        $slugPrefix = str_replace('_', '-', $runCode);
        $email = $runCode . '@automated-tests.local';

        $pdo->prepare('DELETE FROM comentarios WHERE email = :email')->execute(['email' => $email]);
        $pdo->prepare('DELETE FROM posts WHERE slug LIKE :slug')->execute(['slug' => $slugPrefix . '%']);
        $pdo->prepare('DELETE FROM categoria_post WHERE slug LIKE :slug')->execute(['slug' => $slugPrefix . '%']);
        $pdo->prepare('DELETE FROM newsletter WHERE email = :email')->execute(['email' => $email]);
        $pdo->prepare('DELETE FROM links WHERE slug LIKE :slug')->execute(['slug' => $slugPrefix . '%']);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function detectRoutineResidues(\PDO $pdo, string $runCode): array
    {
        $slugPrefix = str_replace('_', '-', $runCode);
        $email = $runCode . '@automated-tests.local';
        $checks = [
            'comentarios' => ['sql' => 'SELECT COUNT(*) FROM comentarios WHERE email = :value', 'value' => $email],
            'posts' => ['sql' => 'SELECT COUNT(*) FROM posts WHERE slug LIKE :value', 'value' => $slugPrefix . '%'],
            'categoria_post' => ['sql' => 'SELECT COUNT(*) FROM categoria_post WHERE slug LIKE :value', 'value' => $slugPrefix . '%'],
            'newsletter' => ['sql' => 'SELECT COUNT(*) FROM newsletter WHERE email = :value', 'value' => $email],
            'links' => ['sql' => 'SELECT COUNT(*) FROM links WHERE slug LIKE :value', 'value' => $slugPrefix . '%'],
        ];

        $residues = [];
        foreach ($checks as $type => $check) {
            $stmt = $pdo->prepare($check['sql']);
            $stmt->execute(['value' => $check['value']]);
            $count = (int) $stmt->fetchColumn();
            if ($count > 0) {
                $residues[] = ['type' => $type, 'count' => $count];
            }
        }

        return $residues;
    }

    /**
     * @param array<string,mixed> $uploads
     */
    private function ftpWriteAndDelete(array $uploads, string $relative, string $bytes): bool
    {
        if (!function_exists('ftp_connect')) {
            throw new \RuntimeException('FTP nao esta disponivel para teste de midia.');
        }

        $host = trim((string) ($uploads['host'] ?? ''));
        $port = (int) ($uploads['port'] ?? 21);
        $username = (string) ($uploads['username'] ?? '');
        $password = (string) ($uploads['password'] ?? '');
        $root = trim((string) ($uploads['root'] ?? ''));
        if ($host === '' || $username === '' || $root === '') {
            throw new \RuntimeException('FTP de uploads sem configuracao completa.');
        }

        $connection = @ftp_connect($host, $port, 20);
        if (!$connection || !@ftp_login($connection, $username, $password)) {
            throw new \RuntimeException('Nao foi possivel conectar ao FTP de uploads.');
        }

        @ftp_pasv($connection, (bool) ($uploads['passive'] ?? true));
        $directory = rtrim($root, '/') . '/automated-tests';
        @ftp_mkdir($connection, $directory);
        $remotePath = rtrim($root, '/') . '/' . ltrim($relative, '/');
        $temp = tempnam(sys_get_temp_dir(), 'en-auto-media-');
        if ($temp === false) {
            @ftp_close($connection);
            throw new \RuntimeException('Nao foi possivel preparar arquivo temporario de FTP.');
        }

        file_put_contents($temp, $bytes);
        $uploaded = @ftp_put($connection, $remotePath, $temp, FTP_BINARY);
        $size = $uploaded ? @ftp_size($connection, $remotePath) : -1;
        @ftp_delete($connection, $remotePath);
        @ftp_rmdir($connection, $directory);
        @ftp_close($connection);
        @unlink($temp);

        return $uploaded && $size > 0;
    }

    /**
     * @param list<array<string,mixed>> $tests
     */
    private function appendPostFromSitemapTest(array &$tests, string $baseUrl): void
    {
        $response = $this->operations->request(rtrim($baseUrl, '/') . '/sitemap.xml');
        $body = (string) ($response['body'] ?? '');
        if ($body !== '' && preg_match('~<loc>([^<]+/post/[^<]+)</loc>~i', $body, $matches) === 1) {
            $postUrl = html_entity_decode((string) $matches[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
            $tests[] = $this->operations->httpTest('public', 'Post real do sitemap', $postUrl, [200], ['canonical']);
            return;
        }

        $tests[] = $this->operations->skipped('public', 'Post real do sitemap', 'Nenhum post encontrado no sitemap.');
    }

    /**
     * @param list<array<string,mixed>> $tests
     */
    private function appendMainAssetTest(array &$tests, string $baseUrl): void
    {
        $home = $this->operations->request(rtrim($baseUrl, '/') . '/');
        $assetUrl = $this->operations->firstAssetUrl((string) ($home['body'] ?? ''), $baseUrl);
        if ($assetUrl === null) {
            $tests[] = $this->operations->skipped('assets', 'Asset principal publico', 'Nenhum asset principal encontrado na home.');
            return;
        }

        $tests[] = $this->operations->httpTest('assets', 'Asset principal publico', $assetUrl, [200], []);
    }

    /**
     * @param list<array<string,mixed>> $tests
     */
    private function appendAdminPageTests(array &$tests, string $baseUrl, string $cookieJar, string $environment): void
    {
        $pathsByEnvironment = (array) ($this->config['admin_paths'] ?? []);
        foreach ((array) ($pathsByEnvironment[$environment] ?? []) as $pathConfig) {
            if (!is_array($pathConfig)) {
                continue;
            }

            $tests[] = $this->operations->httpTest(
                'admin',
                'Admin: ' . (string) ($pathConfig['name'] ?? ($pathConfig['path'] ?? '-')),
                rtrim($baseUrl, '/') . (string) ($pathConfig['path'] ?? '/admin'),
                [200],
                array_values(array_map('strval', (array) ($pathConfig['fragments'] ?? []))),
                $cookieJar
            );
        }
    }

    /**
     * @param list<array<string,mixed>> $tests
     */
    private function appendAdminAssetsTests(array &$tests, string $baseUrl, string $cookieJar): void
    {
        $dashboard = $this->operations->request(rtrim($baseUrl, '/') . '/admin', 'GET', [], $cookieJar);
        $assets = $this->operations->assetUrlsFromHtml((string) ($dashboard['body'] ?? ''), $baseUrl);
        $critical = array_slice(array_values(array_filter($assets, static function (string $asset): bool {
            $path = (string) (parse_url($asset, PHP_URL_PATH) ?: '');
            return str_contains($path, '/assets/css/')
                || str_contains($path, '/assets/js/')
                || str_contains($path, '/assets/brand/')
                || str_ends_with($path, '.ico');
        })), 0, 8);

        if ($critical === []) {
            $tests[] = $this->operations->skipped('assets', 'Assets admin criticos', 'Nenhum CSS/JS/asset critico encontrado no dashboard.');
            return;
        }

        foreach ($critical as $assetUrl) {
            $tests[] = $this->operations->httpTest('assets', 'Asset admin: ' . basename((string) (parse_url($assetUrl, PHP_URL_PATH) ?: $assetUrl)), $assetUrl, [200], [], $cookieJar);
        }
    }

    /**
     * @param list<array<string,mixed>> $tests
     */
    private function appendStageForbiddenMenuTest(array &$tests, string $baseUrl, string $cookieJar): void
    {
        $dashboard = $this->operations->request(rtrim($baseUrl, '/') . '/admin', 'GET', [], $cookieJar);
        $body = (string) ($dashboard['body'] ?? '');
        $forbidden = [];
        foreach ((array) ($this->config['stage_forbidden_fragments'] ?? []) as $fragment) {
            $fragment = (string) $fragment;
            if ($fragment !== '' && stripos($body, $fragment) !== false) {
                $forbidden[] = $fragment;
            }
        }

        $tests[] = [
            'group' => 'security',
            'name' => 'Stage sem menus tecnicos',
            'status' => $forbidden === [] ? OperationalTestService::STATUS_OK : OperationalTestService::STATUS_FAIL,
            'http_status' => (int) ($dashboard['status'] ?? 0),
            'duration_ms' => (int) ($dashboard['duration_ms'] ?? 0),
            'url' => rtrim($baseUrl, '/') . '/admin',
            'message' => $forbidden === []
                ? 'Nenhum menu tecnico proibido encontrado no stage.'
                : 'Menu proibido encontrado no stage: ' . implode(', ', $forbidden),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function defaultSecurityBlocks(string $environment, string $level): array
    {
        return [
            $this->operations->securityBlock('deploy', OperationalTestService::RULE_DEPLOY_DISABLED, 'Deploy nunca e executado pela suite automatizada.', $environment, $level),
            $this->operations->securityBlock('restore', OperationalTestService::RULE_RESTORE_DISABLED, 'Restore nunca e executado pela suite automatizada.', $environment, $level),
            $this->operations->securityBlock('backup_with_uploads', OperationalTestService::RULE_BACKUP_WITH_UPLOADS_DISABLED, 'Backup com uploads e bloqueado pela suite automatizada.', $environment, $level),
            $this->operations->securityBlock('dropbox_upload', OperationalTestService::RULE_DROPBOX_UPLOAD_DISABLED, 'Envio de arquivos ao Dropbox e bloqueado pela suite automatizada.', $environment, $level),
            $this->operations->securityBlock('data_sync', OperationalTestService::RULE_DATA_SYNC_DISABLED, 'Sincronizacao de dados para stage ou producao e bloqueada pela suite automatizada.', $environment, $level),
        ];
    }

    /**
     * @param list<array<string,mixed>> $tests
     */
    private function allTestsOk(array $tests): bool
    {
        foreach ($tests as $test) {
            if ((string) ($test['status'] ?? OperationalTestService::STATUS_FAIL) !== OperationalTestService::STATUS_OK) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string,mixed>> $tests
     * @param list<array<string,mixed>> $securityBlocks
     * @return array<string,mixed>
     */
    private function summarize(array $tests, array $securityBlocks): array
    {
        $counts = [
            OperationalTestService::STATUS_OK => 0,
            OperationalTestService::STATUS_FAIL => 0,
            OperationalTestService::STATUS_SKIP => 0,
        ];

        foreach ($tests as $test) {
            $status = (string) ($test['status'] ?? OperationalTestService::STATUS_FAIL);
            if (!array_key_exists($status, $counts)) {
                $status = OperationalTestService::STATUS_FAIL;
            }
            $counts[$status]++;
        }

        $onlyBlocked = $tests === [] && $securityBlocks !== [];

        return [
            'status' => $counts[OperationalTestService::STATUS_FAIL] > 0
                ? OperationalTestService::STATUS_FAIL
                : ($onlyBlocked ? OperationalTestService::STATUS_BLOCKED : OperationalTestService::STATUS_OK),
            'ok' => $counts[OperationalTestService::STATUS_OK],
            'fail' => $counts[OperationalTestService::STATUS_FAIL],
            'skip' => $counts[OperationalTestService::STATUS_SKIP],
        ];
    }

    private function buildRunId(string $level, string $environment): string
    {
        return 'AUTO-' . strtoupper($environment) . '-' . strtoupper($level) . '-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
    }

    /**
     * @param array<string,mixed> $result
     */
    private function writeResult(array $result): void
    {
        $root = $this->operations->resultRoot();
        if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
            throw new \RuntimeException('Nao foi possivel criar a pasta de relatorios dos testes automatizados.');
        }

        $path = $root . DIRECTORY_SEPARATOR . (string) ($result['id'] ?? ('AUTO-' . date('Ymd-His'))) . '.json';
        file_put_contents($path, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
