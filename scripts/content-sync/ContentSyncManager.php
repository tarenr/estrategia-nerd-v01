<?php

declare(strict_types=1);


namespace Scripts\ContentSync;

require_once __DIR__ . '/../operations/OperationLogger.php';

use Scripts\Operations\OperationLogger;
use PDO;
use RuntimeException;
use ZipArchive;

final class ContentSyncManager
{
    private const CONTENT_DIRECTORIES = [
        'local' => '01-local',
        'stage' => '02-stage',
        'production' => '03-prod',
    ];
    private const PROGRESS_TITLE = 'Executando rotina de conteudo';

    private array $columnSupportCache = [];
    private OperationLogger $logger;

    public function __construct(private array $config)
    {
        date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo');
        $this->logger = new OperationLogger($this->operationRoot());
    }

    public function export(string $profileName = 'local', ?string $progressId = null): array
    {
        $this->allowLongRunningProcess();
        $profile = $this->profile($profileName);
        $root = $this->packageRoot();
        $profileLabel = (string) ($profile['label'] ?? $profileName);
        $this->writeProgress($progressId, [
            'status' => 'running',
            'title' => self::PROGRESS_TITLE,
            'stage' => 'Validando origem',
            'message' => sprintf('Preparando a exportacao editorial da origem %s.', $profileLabel),
            'percent' => 6,
            'updated_at' => date('c'),
        ]);
        $lock = $this->acquireRunLock($root, 'export', $profileName, (string) ($profile['label'] ?? $profileName));
        $packageId = $this->buildPackageId('PC', $profileName);
        $packageDir = $this->profilePackageRoot($profileName) . DIRECTORY_SEPARATOR . $packageId;
        $dataDir = $packageDir . DIRECTORY_SEPARATOR . 'data';

        if (!is_dir($dataDir) && !mkdir($dataDir, 0777, true) && !is_dir($dataDir)) {
            $this->releaseRunLock($lock);
            throw new RuntimeException('Nao foi possivel criar a pasta do pacote: ' . $packageDir);
        }

        $manifest = [
            'package_id' => $packageId,
            'source_profile' => $profileName,
            'source_profile_label' => (string) ($profile['label'] ?? $profileName),
            'created_at' => date('c'),
            'status' => 'running',
            'is_valid' => false,
            'applied_targets' => [],
            'data_files' => [],
            'uploads' => ['name' => 'uploads.zip', 'included_files' => 0, 'size_bytes' => 0, 'sha1' => null],
            'stats' => ['categories' => 0, 'posts' => 0, 'post_slug_history' => 0, 'links' => 0, 'configuracoes' => 0],
            'error' => null,
        ];

        $tmpDir = '';

        try {
            $this->updateProgress($progressId, 'Conectando banco', sprintf('Abrindo a base da origem %s para montar o pacote %s.', $profileLabel, $packageId), 14);
            $pdo = $this->connectPdo((array) ($profile['database'] ?? []));
            $this->updateProgress($progressId, 'Lendo conteudo', 'Carregando categorias, posts, historico de slugs, links e configuracoes publicas.', 22);
            $payload = $this->exportPayload($pdo);

            $totalFiles = count((array) ($payload['files'] ?? []));
            $index = 0;
            foreach ($payload['files'] as $fileName => $rows) {
                $index++;
                $this->updateProgress(
                    $progressId,
                    'Gravando JSONs',
                    sprintf('Gerando %s (%d de %d) para o pacote %s.', $fileName, $index, max(1, $totalFiles), $packageId),
                    22 + (int) round(($index / max(1, $totalFiles)) * 18)
                );
                $filePath = $dataDir . DIRECTORY_SEPARATOR . $fileName;
                $this->writeJson($filePath, $rows);
                $manifest['data_files'][$fileName] = $this->fileDetails($filePath, 'data/' . $fileName);
            }

            $manifest['stats'] = [
                'categories' => count((array) ($payload['files']['categoria_post.json'] ?? [])),
                'posts' => count((array) ($payload['files']['posts.json'] ?? [])),
                'post_slug_history' => count((array) ($payload['files']['post_slug_history.json'] ?? [])),
                'links' => count((array) ($payload['files']['links.json'] ?? [])),
                'configuracoes' => count((array) ($payload['files']['configuracoes.json'] ?? [])),
            ];

            $this->updateProgress($progressId, 'Coletando uploads', 'Montando a amostra de uploads referenciados pelo conteudo selecionado.', 44);
            $tmpDir = $this->materializeUploadsSubset((array) ($profile['uploads'] ?? []), (array) ($payload['upload_paths'] ?? []));
            $zipPath = $packageDir . DIRECTORY_SEPARATOR . 'uploads.zip';
            $this->updateProgress($progressId, 'Compactando uploads', 'Gerando uploads.zip para acompanhar o pacote editorial.', 58);
            $this->compressDirectory($tmpDir, $zipPath);
            $manifest['uploads'] = $this->fileDetails($zipPath, 'uploads.zip');
            $manifest['uploads']['included_files'] = count((array) ($payload['upload_paths'] ?? []));
            $manifest['uploads']['paths'] = array_values((array) ($payload['upload_paths'] ?? []));

            $this->updateProgress($progressId, 'Verificando pacote', 'Conferindo manifesto, JSONs e uploads gerados antes de liberar o pacote.', 78);
            $verification = $this->verifyPackageDirectory($packageDir, $manifest);
            $manifest['verification'] = $verification;
            $manifest['is_valid'] = (bool) ($verification['is_valid'] ?? false);
            $manifest['status'] = 'ready';
            $this->updateProgress($progressId, 'Gravando manifesto', 'Registrando no manifesto local que o pacote editorial foi concluido.', 92);
            $this->writeManifest($packageDir, $manifest);
            $this->logOperation('pacote_conteudo', $profileName, $profileName, (string) $packageId, 'OK', 'Pacote de conteudo gerado.', [
                'posts' => (int) ($manifest['stats']['posts'] ?? 0),
                'uploads' => (int) ($manifest['uploads']['included_files'] ?? 0),
            ]);

            $this->writeProgress($progressId, [
                'status' => 'completed',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Pacote concluido',
                'message' => sprintf('Pacote %s gerado com sucesso a partir da origem %s.', $packageId, $profileLabel),
                'percent' => 100,
                'updated_at' => date('c'),
            ]);

            return $manifest;
        } catch (\Throwable $exception) {
            $manifest['status'] = 'failed';
            $manifest['error'] = $exception->getMessage();
            $this->writeManifest($packageDir, $manifest);
            $this->logOperation('pacote_conteudo', $profileName, $profileName, (string) $packageId, 'FAIL', $exception->getMessage());
            $this->writeProgress($progressId, [
                'status' => 'error',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Falha na exportacao',
                'message' => $exception->getMessage(),
                'percent' => 100,
                'updated_at' => date('c'),
            ]);
            throw $exception;
        } finally {
            if ($tmpDir !== '' && str_contains($tmpDir, sys_get_temp_dir())) {
                $this->removeDirectory($tmpDir);
            }
            $this->releaseRunLock($lock);
        }
    }

    public function status(): array
    {
        $root = $this->packageRoot();
        $items = $this->allPackages();
        $latestStageApply = $this->latestAppliedTarget($items, 'stage');
        $latestProductionApply = $this->latestAppliedTarget($items, 'production');

        return [
            'package_root' => $root,
            'total_packages' => count($items),
            'latest' => $items[0] ?? null,
            'latest_stage_apply' => $latestStageApply,
            'latest_production_apply' => $latestProductionApply,
            'running' => $this->readRunLock($root),
            'items' => $items,
        ];
    }

    public function codeStatus(): array
    {
        $root = $this->codePackageRoot();
        $items = $this->allCodePackages();
        $latestStageApply = $this->latestAppliedTarget($items, 'stage');
        $latestProductionApply = $this->latestAppliedTarget($items, 'production');

        return [
            'package_root' => $root,
            'total_packages' => count($items),
            'latest' => $items[0] ?? null,
            'latest_stage_apply' => $latestStageApply,
            'latest_production_apply' => $latestProductionApply,
            'items' => $items,
        ];
    }

    public function exportCode(?string $notes = null, ?string $progressId = null): array
    {
        $this->allowLongRunningProcess();
        $root = $this->codePackageRoot();
        $this->writeProgress($progressId, [
            'status' => 'running',
            'title' => self::PROGRESS_TITLE,
            'stage' => 'Preparando pacote tecnico',
            'message' => 'Lendo alteracoes atuais para montar um novo pacote de codigo.',
            'percent' => 8,
            'updated_at' => date('c'),
        ]);
        $lock = $this->acquireRunLock($root, 'export_code', 'local', 'Local / Codigo');
        $packageId = $this->buildCodePackageId();
        $packageDir = $root . DIRECTORY_SEPARATOR . $packageId;
        $filesDir = $packageDir . DIRECTORY_SEPARATOR . 'files';

        if (!is_dir($filesDir) && !mkdir($filesDir, 0777, true) && !is_dir($filesDir)) {
            $this->releaseRunLock($lock);
            throw new RuntimeException('Nao foi possivel criar a pasta do pacote tecnico: ' . $packageDir);
        }

        $manifest = [
            'package_id' => $packageId,
            'commit' => $this->currentHeadCommit(),
            'created_at' => date('c'),
            'files_count' => 0,
            'notes' => trim((string) $notes),
            'files' => [],
            'ignored_files' => [],
            'applied_targets' => [],
            'zip_path' => '',
            'manifest_path' => $packageDir . DIRECTORY_SEPARATOR . 'manifest.json',
        ];

        try {
            $this->updateProgress($progressId, 'Selecionando arquivos', 'Separando apenas os arquivos tecnicos elegiveis para o pacote atual.', 18);
            $selection = $this->collectCodePackageFiles();
            $files = (array) ($selection['files'] ?? []);
            $ignored = (array) ($selection['ignored'] ?? []);

            if ($files === []) {
                throw new RuntimeException('Nenhum arquivo tecnico elegivel foi encontrado nas alteracoes atuais.');
            }

            $this->updateProgress($progressId, 'Copiando arquivos', sprintf('Copiando %d arquivos para a estrutura do pacote tecnico.', count($files)), 44);
            $this->copyCodePackageFiles($files, $filesDir);
            $zipPath = $root . DIRECTORY_SEPARATOR . $packageId . '.zip';
            $this->updateProgress($progressId, 'Compactando pacote tecnico', 'Gerando o zip final do pacote de codigo.', 68);
            $this->compressDirectory($packageDir, $zipPath);

            $manifest['files'] = array_values($files);
            $manifest['ignored_files'] = array_values($ignored);
            $manifest['files_count'] = count($files);
            $manifest['zip_path'] = $zipPath;

            $this->updateProgress($progressId, 'Gravando manifesto tecnico', 'Registrando os arquivos incluidos e o commit atual.', 90);
            $this->writeJson($packageDir . DIRECTORY_SEPARATOR . 'manifest.json', $manifest);
            $this->logOperation('pacote_tecnico', 'local', 'local', $packageId, 'OK', 'Pacote tecnico gerado com sucesso.', [
                'files' => count($files),
            ]);

            $this->writeProgress($progressId, [
                'status' => 'completed',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Pacote tecnico concluido',
                'message' => sprintf('Pacote tecnico %s gerado com sucesso.', $packageId),
                'percent' => 100,
                'updated_at' => date('c'),
            ]);

            return $manifest;
        } catch (\Throwable $exception) {
            $this->removeDirectory($packageDir);
            $zipPath = $root . DIRECTORY_SEPARATOR . $packageId . '.zip';
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }

            $this->logOperation('pacote_tecnico', 'local', 'local', $packageId, 'FAIL', $exception->getMessage());
            $this->writeProgress($progressId, [
                'status' => 'error',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Falha no pacote tecnico',
                'message' => $exception->getMessage(),
                'percent' => 100,
                'updated_at' => date('c'),
            ]);
            throw $exception;
        } finally {
            $this->releaseRunLock($lock);
        }
    }

    public function parityStatus(): array
    {
        $contentStatus = $this->status();
        $codeStatus = $this->codeStatus();

        $latestContent = is_array($contentStatus['latest'] ?? null) ? $contentStatus['latest'] : null;
        $latestContentProd = is_array($contentStatus['latest_production_apply'] ?? null) ? $contentStatus['latest_production_apply'] : null;
        $latestCode = is_array($codeStatus['latest'] ?? null) ? $codeStatus['latest'] : null;
        $latestCodeProd = is_array($codeStatus['latest_production_apply'] ?? null) ? $codeStatus['latest_production_apply'] : null;

        $contentSync = $latestContent !== null
            && $latestContentProd !== null
            && (string) ($latestContent['package_id'] ?? '') !== ''
            && (string) ($latestContent['package_id'] ?? '') === (string) ($latestContentProd['package_id'] ?? '');

        $codeSync = $latestCode !== null
            && $latestCodeProd !== null
            && (string) ($latestCode['package_id'] ?? '') !== ''
            && (string) ($latestCode['package_id'] ?? '') === (string) ($latestCodeProd['package_id'] ?? '');

        $recommendations = [];
        if ($latestContent !== null && !$contentSync) {
            $recommendations[] = 'Conteudo local mais novo do que a producao. Validar e publicar o pacote mais recente.';
        }
        if ($latestCode !== null && !$codeSync) {
            $recommendations[] = 'Codigo local mais novo do que a producao. Gerar/aplicar o pacote tecnico mais recente.';
        }
        if ($recommendations === []) {
            $recommendations[] = 'Local e producao estao alinhados nos ultimos pacotes registrados.';
        }

        return [
            'checked_at' => date('c'),
            'overall_in_sync' => $contentSync && $codeSync,
            'content' => [
                'in_sync' => $contentSync,
                'latest_local_package_id' => (string) ($latestContent['package_id'] ?? ''),
                'latest_local_created_at' => (string) ($latestContent['created_at'] ?? ''),
                'latest_production_package_id' => (string) ($latestContentProd['package_id'] ?? ''),
                'latest_production_applied_at' => (string) ($latestContentProd['applied_at'] ?? ''),
            ],
            'code' => [
                'in_sync' => $codeSync,
                'latest_local_package_id' => (string) ($latestCode['package_id'] ?? ''),
                'latest_local_created_at' => (string) ($latestCode['created_at'] ?? ''),
                'latest_production_package_id' => (string) ($latestCodeProd['package_id'] ?? ''),
                'latest_production_applied_at' => (string) ($latestCodeProd['applied_at'] ?? ''),
            ],
            'recommendations' => $recommendations,
        ];
    }

    public function deploymentPolicyStatus(): array
    {
        $policy = (array) ($this->config['deployment_policy'] ?? []);
        $currentSource = strtolower(trim((string) ($policy['current_source'] ?? 'local')));
        $approvedSource = strtolower(trim((string) ($policy['approved_source'] ?? 'stage')));
        $stageLabel = trim((string) ($policy['stage_label'] ?? 'estrategia-nerd-stage'));
        $productionAllowed = $currentSource !== '' && $approvedSource !== '' && $currentSource === $approvedSource;

        return [
            'current_source' => $currentSource !== '' ? $currentSource : 'local',
            'approved_source' => $approvedSource !== '' ? $approvedSource : 'stage',
            'stage_label' => $stageLabel !== '' ? $stageLabel : 'estrategia-nerd-stage',
            'production_allowed' => $productionAllowed,
            'reason' => $productionAllowed ? 'Origem aprovada.' : 'Origem atual diferente da origem autorizada para producao.',
            'message' => $productionAllowed
                ? 'Origem atual autorizada para pacote de producao.'
                : sprintf('Publicacao em producao bloqueada: a origem atual e \"%s\" e a regra permanente exige \"%s\" (%s).', $currentSource !== '' ? $currentSource : 'local', $approvedSource !== '' ? $approvedSource : 'stage', $stageLabel !== '' ? $stageLabel : 'estrategia-nerd-stage'),
        ];
    }

    public function profileReady(string $profileName): bool
    {
        $profile = (array) ($this->config['profiles'][$profileName] ?? []);
        if ($profile === []) {
            return false;
        }

        $database = (array) ($profile['database'] ?? []);
        $uploads = (array) ($profile['uploads'] ?? []);

        foreach (['host', 'database', 'username'] as $required) {
            if (trim((string) ($database[$required] ?? '')) === '') {
                return false;
            }
        }

        $mode = strtolower(trim((string) ($uploads['mode'] ?? 'ftp')));
        if ($mode === 'local') {
            return trim((string) ($uploads['path'] ?? '')) !== '';
        }

        foreach (['host', 'username', 'password', 'root'] as $required) {
            if (trim((string) ($uploads[$required] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    private function assertProductionPublishAllowed(string $targetProfile): void
    {
        if (strtolower(trim($targetProfile)) !== 'production') {
            return;
        }

        $policy = $this->deploymentPolicyStatus();
        if (!(bool) ($policy['production_allowed'] ?? false)) {
            throw new RuntimeException((string) ($policy['message'] ?? 'Publicacao em producao bloqueada pela politica operacional.'));
        }
    }

    public function verify(?string $packageId, ?string $progressId = null): array
    {
        $this->allowLongRunningProcess();
        $this->writeProgress($progressId, [
            'status' => 'running',
            'title' => self::PROGRESS_TITLE,
            'stage' => 'Localizando pacote',
            'message' => 'Preparando a verificacao do pacote editorial informado.',
            'percent' => 10,
            'updated_at' => date('c'),
        ]);
        $requestedPackageId = trim((string) $packageId);
        $package = $this->packageById($packageId);
        if ($package === null) {
            throw new RuntimeException('Nenhum pacote encontrado para verificar.');
        }
        $resolvedPackageId = (string) ($package['package_id'] ?? ($requestedPackageId !== '' ? $requestedPackageId : 'ultimo disponivel'));

        $this->updateProgress($progressId, 'Lendo manifesto', sprintf('Pacote %s encontrado. Conferindo manifesto e arquivos internos.', $resolvedPackageId), 28);
        $verification = $this->verifyPackageDirectory((string) $package['_dir'], $package);
        $package['verification'] = $verification;
        $package['is_valid'] = (bool) ($verification['is_valid'] ?? false);
        $this->updateProgress($progressId, 'Gravando verificacao', 'Atualizando o manifesto local com o resultado da verificacao.', 84);
        $this->writeManifest((string) $package['_dir'], $package);
        $this->logOperation('verificacao_conteudo', (string) ($package['source_profile'] ?? 'stage'), (string) ($package['source_profile'] ?? 'stage'), (string) ($package['package_id'] ?? ''), ($package['is_valid'] ?? false) ? 'OK' : 'FAIL', 'Verificacao de pacote de conteudo executada.');

        $this->writeProgress($progressId, [
                'status' => 'completed',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Verificacao concluida',
                'message' => sprintf('Pacote %s verificado com status %s.', $resolvedPackageId, ($package['is_valid'] ?? false) ? 'valido' : 'invalido'),
                'percent' => 100,
                'updated_at' => date('c'),
            ]);

        return $package;
    }

    public function apply(?string $packageId, string $targetProfile = 'production', bool $force = false, ?string $progressId = null): array
    {
        $this->allowLongRunningProcess();
        $this->writeProgress($progressId, [
            'status' => 'running',
            'title' => self::PROGRESS_TITLE,
            'stage' => 'Validando publicacao',
            'message' => 'Preparando a aplicacao editorial no ambiente de destino.',
            'percent' => 6,
            'updated_at' => date('c'),
        ]);
        if (!$force) {
            throw new RuntimeException('Publicacao exige confirmacao explicita.');
        }

        $requestedPackageId = trim((string) $packageId);
        $package = $this->packageById($packageId);
        if ($package === null) {
            throw new RuntimeException('Nenhum pacote encontrado para aplicar.');
        }
        $resolvedPackageId = (string) ($package['package_id'] ?? ($requestedPackageId !== '' ? $requestedPackageId : 'ultimo disponivel'));

        $sourceProfile = strtolower(trim((string) ($package['source_profile'] ?? '')));
        $allowedTargets = $this->allowedApplyTargets($sourceProfile);
        if (!in_array(strtolower(trim($targetProfile)), $allowedTargets, true)) {
            throw new RuntimeException(sprintf(
                'Pacote %s com origem %s nao pode ser aplicado em %s.',
                (string) ($package['package_id'] ?? ''),
                $sourceProfile !== '' ? $sourceProfile : 'desconhecida',
                $targetProfile
            ));
        }

        $verification = $this->verifyPackageDirectory((string) $package['_dir'], $package);
        if (!(bool) ($verification['is_valid'] ?? false)) {
            throw new RuntimeException('O pacote selecionado nao passou na verificacao.');
        }

        $this->assertProductionPublishAllowed($targetProfile);
        $profile = $this->profile($targetProfile);
        $profileLabel = (string) ($profile['label'] ?? $targetProfile);
        $lock = $this->acquireRunLock($this->packageRoot(), 'apply', $targetProfile, (string) ($profile['label'] ?? $targetProfile));
        $tmpDir = '';

        try {
            $this->updateProgress($progressId, 'Lendo pacote', sprintf('Pacote %s validado. Preparando a aplicacao em %s.', $resolvedPackageId, $profileLabel), 18);
            $payload = $this->readPayload((string) $package['_dir']);
            $this->updateProgress($progressId, 'Conectando destino', sprintf('Abrindo o banco do ambiente %s.', $profileLabel), 26);
            $pdo = $this->connectPdo((array) ($profile['database'] ?? []));
            $this->updateProgress($progressId, 'Extraindo uploads', 'Descompactando uploads.zip para aplicar os arquivos referenciados.', 34);
            $tmpDir = $this->extractArchive((string) $package['_dir'] . DIRECTORY_SEPARATOR . 'uploads.zip');

            $pdo->beginTransaction();
            try {
                $this->updateProgress($progressId, 'Aplicando categorias', 'Atualizando categorias editoriais no banco de destino.', 44);
                $categories = $this->applyCategories($pdo, (array) ($payload['categoria_post.json'] ?? []));
                $this->updateProgress($progressId, 'Aplicando posts', 'Atualizando posts e historico de slugs no banco de destino.', 56);
                $posts = $this->applyPosts($pdo, (array) ($payload['posts.json'] ?? []), (array) ($payload['post_slug_history.json'] ?? []), $categories);
                $this->updateProgress($progressId, 'Aplicando links', 'Atualizando links publicos do pacote editorial.', 64);
                $links = $this->applyLinks($pdo, (array) ($payload['links.json'] ?? []));
                $this->updateProgress($progressId, 'Aplicando configuracoes', 'Atualizando configuracoes publicas do ambiente de destino.', 70);
                $configs = $this->applyConfiguracoes($pdo, (array) ($payload['configuracoes.json'] ?? []));
                $this->updateProgress($progressId, 'Validando integridade', 'Conferindo a integridade dos posts aplicados antes do commit final.', 78);
                $this->assertAppliedPostIntegrity($pdo, (array) ($payload['posts.json'] ?? []));
                $pdo->commit();
            } catch (\Throwable $exception) {
                $pdo->rollBack();
                throw $exception;
            }

            $this->updateProgress($progressId, 'Aplicando uploads', 'Copiando uploads referenciados para o ambiente de destino.', 86);
            $uploads = $this->applyUploads((array) ($profile['uploads'] ?? []), $tmpDir);
            $apply = [
                'target_profile' => $targetProfile,
                'target_profile_label' => (string) ($profile['label'] ?? $targetProfile),
                'applied_at' => date('c'),
                'result' => ['categorias' => $categories['stats'], 'posts' => $posts, 'links' => $links, 'configuracoes' => $configs, 'uploads' => $uploads],
            ];
            $package['applied_targets'] = array_values(array_merge((array) ($package['applied_targets'] ?? []), [$apply]));
            $package['verification'] = $verification;
            $package['is_valid'] = true;
            $this->updateProgress($progressId, 'Gravando aplicacao', 'Registrando no manifesto local que este pacote ja foi aplicado no destino.', 94);
            $this->writeManifest((string) $package['_dir'], $package);
            $type = strtolower(trim($targetProfile)) === 'production' ? 'promocao_conteudo' : 'aplicacao_conteudo';
            $this->logOperation($type, (string) ($package['source_profile'] ?? 'stage'), $targetProfile, (string) ($package['package_id'] ?? ''), 'OK', 'Pacote de conteudo aplicado com sucesso.');

            $this->writeProgress($progressId, [
                'status' => 'completed',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Aplicacao concluida',
                'message' => sprintf('Pacote %s aplicado com sucesso em %s.', (string) ($package['package_id'] ?? $resolvedPackageId), $profileLabel),
                'percent' => 100,
                'updated_at' => date('c'),
            ]);

            return ['package_id' => $package['package_id'] ?? null, 'target_profile' => $targetProfile, 'applied_at' => $apply['applied_at'], 'result' => $apply['result']];
        } catch (\Throwable $exception) {
            $this->logOperation('aplicacao_conteudo', (string) ($package['source_profile'] ?? 'stage'), $targetProfile, (string) ($package['package_id'] ?? ($packageId ?? '')), 'FAIL', $exception->getMessage());
            $this->writeProgress($progressId, [
                'status' => 'error',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Falha na aplicacao',
                'message' => $exception->getMessage(),
                'percent' => 100,
                'updated_at' => date('c'),
            ]);
            throw $exception;
        } finally {
            if ($tmpDir !== '' && str_contains($tmpDir, sys_get_temp_dir())) {
                $this->removeDirectory($tmpDir);
            }
            $this->releaseRunLock($lock);
        }
    }

    public function applyCode(?string $packageId, string $targetProfile = 'production', bool $force = false, ?string $progressId = null): array
    {
        $this->allowLongRunningProcess();
        $this->writeProgress($progressId, [
            'status' => 'running',
            'title' => self::PROGRESS_TITLE,
            'stage' => 'Validando deploy tecnico',
            'message' => 'Preparando a aplicacao do pacote de codigo no destino escolhido.',
            'percent' => 6,
            'updated_at' => date('c'),
        ]);
        if (!$force) {
            throw new RuntimeException('Publicacao de codigo exige confirmacao explicita.');
        }

        $requestedPackageId = trim((string) $packageId);
        $package = $this->codePackageById($packageId);
        if ($package === null) {
            throw new RuntimeException('Nenhum pacote de codigo encontrado para aplicar.');
        }
        $resolvedPackageId = (string) ($package['package_id'] ?? ($requestedPackageId !== '' ? $requestedPackageId : 'ultimo disponivel'));

        $this->assertProductionPublishAllowed($targetProfile);
        $profile = $this->profile($targetProfile);
        $profileLabel = (string) ($profile['label'] ?? $targetProfile);
        $lock = $this->acquireRunLock($this->packageRoot(), 'apply_code', $targetProfile, $profileLabel);
        $tmpDir = '';

        try {
            $zipPath = (string) ($package['zip_path'] ?? '');
            if ($zipPath === '' || !is_file($zipPath)) {
                throw new RuntimeException('Arquivo ZIP do pacote de codigo nao encontrado.');
            }

            $this->updateProgress($progressId, 'Extraindo pacote tecnico', sprintf('Abrindo o pacote %s para preparar o deploy tecnico.', $resolvedPackageId), 24);
            $tmpDir = $this->extractArchive($zipPath);
            $sourceDir = $tmpDir . DIRECTORY_SEPARATOR . 'files';
            if (!is_dir($sourceDir)) {
                throw new RuntimeException('Pacote de codigo invalido: pasta "files/" nao encontrada.');
            }

            $deployConfig = $this->codeDeployConfig($targetProfile);
            $this->updateProgress($progressId, 'Aplicando arquivos tecnicos', sprintf('Enviando os arquivos do pacote tecnico para %s.', $profileLabel), 56);
            $result = $this->deployCode($deployConfig, $sourceDir);
            $apply = [
                'target_profile' => $targetProfile,
                'target_profile_label' => $profileLabel,
                'applied_at' => date('c'),
                'result' => $result,
            ];
            $package['applied_targets'] = array_values(array_merge((array) ($package['applied_targets'] ?? []), [$apply]));
            $manifestDir = (string) ($package['_dir'] ?? '');
            if ($manifestDir !== '') {
                $this->updateProgress($progressId, 'Gravando deploy tecnico', 'Atualizando o manifesto local do pacote tecnico com o destino aplicado.', 90);
                $this->writeManifest($manifestDir, $package);
            }
            $type = strtolower(trim($targetProfile)) === 'production' ? 'deploy_tecnico' : 'deploy_tecnico_stage';
            $this->logOperation($type, 'local', $targetProfile, (string) ($package['package_id'] ?? ''), 'OK', 'Pacote tecnico aplicado com sucesso.', [
                'files' => (int) ($result['files_applied'] ?? 0),
            ]);

            $this->writeProgress($progressId, [
                'status' => 'completed',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Deploy tecnico concluido',
                'message' => sprintf('Pacote tecnico %s aplicado com sucesso em %s.', (string) ($package['package_id'] ?? $resolvedPackageId), $profileLabel),
                'percent' => 100,
                'updated_at' => date('c'),
            ]);

            return [
                'package_id' => (string) ($package['package_id'] ?? ''),
                'target_profile' => $targetProfile,
                'target_profile_label' => $profileLabel,
                'applied_at' => (string) $apply['applied_at'],
                'result' => $result,
            ];
        } catch (\Throwable $exception) {
            $this->logOperation('deploy_tecnico', 'local', $targetProfile, (string) ($package['package_id'] ?? ($packageId ?? '')), 'FAIL', $exception->getMessage());
            $this->writeProgress($progressId, [
                'status' => 'error',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Falha no deploy tecnico',
                'message' => $exception->getMessage(),
                'percent' => 100,
                'updated_at' => date('c'),
            ]);
            throw $exception;
        } finally {
            if ($tmpDir !== '' && str_contains($tmpDir, sys_get_temp_dir())) {
                $this->removeDirectory($tmpDir);
            }
            $this->releaseRunLock($lock);
        }
    }

    private function operationRoot(): string
    {
        $root = trim((string) ($_ENV['BACKUP_ROOT'] ?? ''));
        if ($root !== '') {
            return $root;
        }

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'operations';
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private function logOperation(
        string $type,
        string $origin,
        string $destination,
        string $id,
        string $status,
        string $message,
        array $context = []
    ): void {
        try {
            $this->logger->write($type, $origin, $destination, $id, $status, $message, $context);
        } catch (\Throwable) {
            // Log operacional nunca deve derrubar a rotina principal.
        }
    }

    private function exportPayload(PDO $pdo): array
    {
        $supportsNextStep = $this->tableHasColumn($pdo, 'posts', 'proximo_post_id');
        $supportsCategorySeoTitle = $this->tableHasColumn($pdo, 'categoria_post', 'seo_title');
        $supportsCategorySeoDescription = $this->tableHasColumn($pdo, 'categoria_post', 'seo_description');
        $supportsCategoryIndexar = $this->tableHasColumn($pdo, 'categoria_post', 'indexar');
        $supportsCategoryMenu = $this->tableHasColumn($pdo, 'categoria_post', 'exibir_no_menu');
        $categories = $this->fetchAll(
            $pdo,
            'SELECT id, nome, slug, descricao, ativo, ordem, cor, '
            . ($supportsCategorySeoTitle ? 'seo_title' : 'NULL AS seo_title') . ', '
            . ($supportsCategorySeoDescription ? 'seo_description' : 'NULL AS seo_description') . ', '
            . ($supportsCategoryIndexar ? 'indexar' : '1 AS indexar') . ', '
            . ($supportsCategoryMenu ? 'exibir_no_menu' : '1 AS exibir_no_menu')
            . ' FROM categoria_post ORDER BY ordem ASC, nome ASC, id ASC'
        );
        $nextStepSelect = $supportsNextStep ? 'proximo_post_id' : 'NULL AS proximo_post_id';
        $posts = $this->fetchAll($pdo, "SELECT id, titulo, slug, resumo, conteudo, categoria, categoria_id, categoria_post_id, imagem_capa, imagem_thumb, autor_id, data_publicacao, data_atualizacao, tempo_leitura, seo_title, seo_description, seo_keywords, tags, status, destaque, {$nextStepSelect} FROM posts ORDER BY data_publicacao ASC, id ASC");
        $history = $this->fetchAll($pdo, 'SELECT post_id, slug, created_at FROM post_slug_history ORDER BY id ASC');
        $links = $this->fetchAll($pdo, 'SELECT id, titulo, slug, url, tipo, promocao, desconto_percentual, desconto_contexto, codigo_cupom, secao_publica, subgrupo_publico, descricao, cta_curto, texto_botao, selo, imagem, posicao, status, destaque, expira_em FROM links ORDER BY posicao ASC, id ASC');
        $configs = $this->fetchConfiguracoes($pdo);

        $categorySlugById = [];
        foreach ($categories as $category) {
            $id = (int) ($category['id'] ?? 0);
            if ($id > 0) {
                $categorySlugById[$id] = (string) ($category['slug'] ?? '');
            }
        }

        $postSlugById = [];
        foreach ($posts as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            $itemSlug = trim((string) ($item['slug'] ?? ''));
            if ($itemId > 0 && $itemSlug !== '') {
                $postSlugById[$itemId] = $itemSlug;
            }
        }

        foreach ($posts as &$post) {
            $categoryPostId = (int) ($post['categoria_post_id'] ?? 0);
            $nextPostId = (int) ($post['proximo_post_id'] ?? 0);
            $post['categoria_post_slug'] = $categoryPostId > 0 ? (string) ($categorySlugById[$categoryPostId] ?? '') : '';
            $post['proximo_post_slug'] = $nextPostId > 0 ? (string) ($postSlugById[$nextPostId] ?? '') : '';
        }
        unset($post);

        return [
            'files' => [
                'categoria_post.json' => $categories,
                'posts.json' => $posts,
                'post_slug_history.json' => $history,
                'links.json' => $links,
                'configuracoes.json' => $configs,
            ],
            'upload_paths' => $this->collectUploadPaths($posts, $links, $configs),
        ];
    }

    private function fetchConfiguracoes(PDO $pdo): array
    {
        $keys = array_values(array_filter(array_map('strval', (array) ($this->config['public_config_keys'] ?? []))));
        if ($keys === []) {
            return [];
        }

        $stmt = $pdo->prepare('SELECT chave, valor FROM configuracoes WHERE chave IN (' . implode(', ', array_fill(0, count($keys), '?')) . ') ORDER BY chave ASC');
        $stmt->execute($keys);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function collectUploadPaths(array $posts, array $links, array $configs): array
    {
        $paths = [];

        foreach ($posts as $post) {
            foreach (['imagem_capa', 'imagem_thumb'] as $field) {
                $normalized = $this->normalizeUploadReference((string) ($post[$field] ?? ''));
                if ($normalized !== null) {
                    $paths[$normalized] = true;
                }
            }
            foreach ($this->extractUploadReferencesFromHtml((string) ($post['conteudo'] ?? '')) as $path) {
                $paths[$path] = true;
            }
        }

        foreach ($links as $link) {
            $normalized = $this->normalizeUploadReference((string) ($link['imagem'] ?? ''));
            if ($normalized !== null) {
                $paths[$normalized] = true;
            }
        }

        foreach ($configs as $config) {
            $normalized = $this->normalizeUploadReference((string) ($config['valor'] ?? ''));
            if ($normalized !== null) {
                $paths[$normalized] = true;
            }
        }

        $values = array_keys($paths);
        sort($values);
        return $values;
    }

    private function normalizeUploadReference(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $value) === 1) {
            $path = parse_url($value, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                return null;
            }
            $value = $path;
        }

        $value = str_replace('\\', '/', $value);
        $value = preg_replace('#/+#', '/', $value) ?? $value;
        $position = stripos($value, '/uploads/');
        if ($position !== false) {
            $value = substr($value, $position + 1);
        }

        $value = ltrim($value, '/');
        if (!str_starts_with($value, 'uploads/')) {
            return null;
        }

        $relative = trim(substr($value, strlen('uploads/')), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        return 'uploads/' . $relative;
    }

    private function extractUploadReferencesFromHtml(string $html): array
    {
        if ($html === '') {
            return [];
        }

        preg_match_all('/(?:src|href)=["\']([^"\']+)["\']/i', $html, $matches);
        $paths = [];
        foreach ((array) ($matches[1] ?? []) as $match) {
            $normalized = $this->normalizeUploadReference((string) $match);
            if ($normalized !== null) {
                $paths[$normalized] = true;
            }
        }

        return array_keys($paths);
    }

    private function materializeUploadsSubset(array $uploadsConfig, array $paths): string
    {
        $tmpRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'en-content-' . bin2hex(random_bytes(6));
        $uploadsRoot = $tmpRoot . DIRECTORY_SEPARATOR . 'uploads';
        if (!mkdir($uploadsRoot, 0777, true) && !is_dir($uploadsRoot)) {
            throw new RuntimeException('Nao foi possivel criar a pasta temporaria de uploads.');
        }

        if ($paths === []) {
            return $tmpRoot;
        }

        $mode = strtolower((string) ($uploadsConfig['mode'] ?? 'local'));
        if ($mode === 'local') {
            $sourceRoot = rtrim((string) ($uploadsConfig['path'] ?? ''), '\\/');
            if ($sourceRoot === '' || !is_dir($sourceRoot)) {
                throw new RuntimeException('Pasta local de uploads nao encontrada: ' . $sourceRoot);
            }

            foreach ($paths as $relativePath) {
                $relative = substr($relativePath, strlen('uploads/'));
                $sourceFile = $sourceRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if (!is_file($sourceFile)) {
                    continue;
                }
                $destination = $tmpRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                $destinationDir = dirname($destination);
                if (!is_dir($destinationDir) && !mkdir($destinationDir, 0777, true) && !is_dir($destinationDir)) {
                    throw new RuntimeException('Nao foi possivel criar a pasta temporaria do pacote.');
                }
                if (!copy($sourceFile, $destination)) {
                    throw new RuntimeException('Falha ao copiar o upload para o pacote: ' . $relativePath);
                }
            }

            return $tmpRoot;
        }

        if ($mode !== 'ftp') {
            throw new RuntimeException('Modo de uploads nao suportado para exportacao: ' . $mode);
        }

        foreach (['host', 'port', 'username', 'password', 'root'] as $required) {
            if (trim((string) ($uploadsConfig[$required] ?? '')) === '') {
                throw new RuntimeException('Configuracao FTP incompleta: faltando ' . $required);
            }
        }

        $ftp = @ftp_connect((string) $uploadsConfig['host'], (int) $uploadsConfig['port'], 30);
        if ($ftp === false) {
            throw new RuntimeException('Nao foi possivel conectar ao FTP configurado.');
        }

        try {
            if (!@ftp_login($ftp, (string) $uploadsConfig['username'], (string) $uploadsConfig['password'])) {
                throw new RuntimeException('Falha de login no FTP.');
            }
            ftp_pasv($ftp, (bool) ($uploadsConfig['passive'] ?? true));
            $root = rtrim((string) $uploadsConfig['root'], '/');

            foreach ($paths as $relativePath) {
                $relative = substr($relativePath, strlen('uploads/'));
                $remoteFile = $root . '/' . $relative;
                $localFile = $tmpRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                $localDir = dirname($localFile);
                if (!is_dir($localDir) && !mkdir($localDir, 0777, true) && !is_dir($localDir)) {
                    throw new RuntimeException('Nao foi possivel criar a pasta temporaria do pacote.');
                }
                @ftp_get($ftp, $localFile, $remoteFile, FTP_BINARY);
            }
        } finally {
            ftp_close($ftp);
        }

        return $tmpRoot;
    }

    private function applyCategories(PDO $pdo, array $categories): array
    {
        $stats = ['created' => 0, 'updated' => 0];
        $map = [];
        $supportsCategorySeoTitle = $this->tableHasColumn($pdo, 'categoria_post', 'seo_title');
        $supportsCategorySeoDescription = $this->tableHasColumn($pdo, 'categoria_post', 'seo_description');
        $supportsCategoryIndexar = $this->tableHasColumn($pdo, 'categoria_post', 'indexar');
        $supportsCategoryMenu = $this->tableHasColumn($pdo, 'categoria_post', 'exibir_no_menu');

        foreach ($categories as $category) {
            $slug = trim((string) ($category['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $existing = $this->fetchOne($pdo, 'SELECT id FROM categoria_post WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
            if ($existing !== null) {
                $assignments = [
                    'nome = :nome',
                    'descricao = :descricao',
                    'ativo = :ativo',
                    'ordem = :ordem',
                    'cor = :cor',
                ];
                if ($supportsCategorySeoTitle) {
                    $assignments[] = 'seo_title = :seo_title';
                }
                if ($supportsCategorySeoDescription) {
                    $assignments[] = 'seo_description = :seo_description';
                }
                if ($supportsCategoryIndexar) {
                    $assignments[] = 'indexar = :indexar';
                }
                if ($supportsCategoryMenu) {
                    $assignments[] = 'exibir_no_menu = :exibir_no_menu';
                }

                $stmt = $pdo->prepare('UPDATE categoria_post SET ' . implode(', ', $assignments) . ' WHERE id = :id');
                $params = [
                    'id' => (int) $existing['id'],
                    'nome' => (string) ($category['nome'] ?? ''),
                    'descricao' => $this->nullableString($category['descricao'] ?? null),
                    'ativo' => (int) ($category['ativo'] ?? 1),
                    'ordem' => (int) ($category['ordem'] ?? 0),
                    'cor' => (string) ($category['cor'] ?? '#00d4ff'),
                ];
                if ($supportsCategorySeoTitle) {
                    $params['seo_title'] = $this->nullableString($category['seo_title'] ?? null);
                }
                if ($supportsCategorySeoDescription) {
                    $params['seo_description'] = $this->nullableString($category['seo_description'] ?? null);
                }
                if ($supportsCategoryIndexar) {
                    $params['indexar'] = (int) ($category['indexar'] ?? 1);
                }
                if ($supportsCategoryMenu) {
                    $params['exibir_no_menu'] = (int) ($category['exibir_no_menu'] ?? 1);
                }
                $stmt->execute($params);
                $categoryId = (int) $existing['id'];
                $stats['updated']++;
            } else {
                $columns = ['nome', 'slug', 'descricao', 'ativo', 'ordem', 'cor'];
                $values = [':nome', ':slug', ':descricao', ':ativo', ':ordem', ':cor'];
                $params = [
                    'nome' => (string) ($category['nome'] ?? ''),
                    'slug' => $slug,
                    'descricao' => $this->nullableString($category['descricao'] ?? null),
                    'ativo' => (int) ($category['ativo'] ?? 1),
                    'ordem' => (int) ($category['ordem'] ?? 0),
                    'cor' => (string) ($category['cor'] ?? '#00d4ff'),
                ];
                if ($supportsCategorySeoTitle) {
                    $columns[] = 'seo_title';
                    $values[] = ':seo_title';
                    $params['seo_title'] = $this->nullableString($category['seo_title'] ?? null);
                }
                if ($supportsCategorySeoDescription) {
                    $columns[] = 'seo_description';
                    $values[] = ':seo_description';
                    $params['seo_description'] = $this->nullableString($category['seo_description'] ?? null);
                }
                if ($supportsCategoryIndexar) {
                    $columns[] = 'indexar';
                    $values[] = ':indexar';
                    $params['indexar'] = (int) ($category['indexar'] ?? 1);
                }
                if ($supportsCategoryMenu) {
                    $columns[] = 'exibir_no_menu';
                    $values[] = ':exibir_no_menu';
                    $params['exibir_no_menu'] = (int) ($category['exibir_no_menu'] ?? 1);
                }

                $stmt = $pdo->prepare('INSERT INTO categoria_post (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')');
                $stmt->execute($params);
                $categoryId = (int) $pdo->lastInsertId();
                $stats['created']++;
            }
            $map[$slug] = $categoryId;
        }

        return ['map' => $map, 'stats' => $stats];
    }
    private function applyPosts(PDO $pdo, array $posts, array $historyRows, array $categoryPayload): array
    {
        $supportsNextStep = $this->tableHasColumn($pdo, 'posts', 'proximo_post_id');
        $categoryMap = (array) ($categoryPayload['map'] ?? []);
        $historyByPost = [];
        foreach ($historyRows as $row) {
            $postId = (int) ($row['post_id'] ?? 0);
            $slug = trim((string) ($row['slug'] ?? ''));
            if ($postId > 0 && $slug !== '') {
                $historyByPost[$postId][] = $slug;
            }
        }

        $stats = ['created' => 0, 'updated' => 0, 'history_added' => 0, 'next_step_links' => 0];
        $targetIdBySlug = [];
        $nextStepSlugByTargetId = [];

        foreach ($posts as $post) {
            $sourcePostId = (int) ($post['id'] ?? 0);
            $currentSlug = trim((string) ($post['slug'] ?? ''));
            if ($currentSlug === '') {
                continue;
            }

            $knownSlugs = array_values(array_unique(array_filter(array_merge([$currentSlug], $historyByPost[$sourcePostId] ?? []))));
            $existing = $this->findTargetPost($pdo, $currentSlug, $knownSlugs);
            $currentTargetSlug = (string) ($existing['slug'] ?? '');
            $categorySlug = trim((string) ($post['categoria_post_slug'] ?? ''));
            $categoryId = $categorySlug !== '' ? (int) ($categoryMap[$categorySlug] ?? 0) : 0;
            $authorId = $this->resolveAuthorId($pdo, (int) ($post['autor_id'] ?? 1));
            $nextStepSlug = trim((string) ($post['proximo_post_slug'] ?? ''));

            $data = [
                'titulo' => (string) ($post['titulo'] ?? ''),
                'slug' => $currentSlug,
                'resumo' => (string) ($post['resumo'] ?? ''),
                'conteudo' => (string) ($post['conteudo'] ?? ''),
                'categoria' => (string) ($post['categoria'] ?? 'gadgets'),
                'categoria_post_id' => $categoryId > 0 ? $categoryId : null,
                'imagem_capa' => $this->nullableString($post['imagem_capa'] ?? null),
                'imagem_thumb' => $this->nullableString($post['imagem_thumb'] ?? null),
                'autor_id' => $authorId,
                'data_publicacao' => (string) ($post['data_publicacao'] ?? date('Y-m-d H:i:s')),
                'tempo_leitura' => (int) ($post['tempo_leitura'] ?? 5),
                'seo_title' => $this->nullableString($post['seo_title'] ?? null),
                'seo_description' => $this->nullableString($post['seo_description'] ?? null),
                'seo_keywords' => $this->nullableString($post['seo_keywords'] ?? null),
                'tags' => $this->nullableString($post['tags'] ?? null),
                'status' => (string) ($post['status'] ?? 'rascunho'),
                'destaque' => (int) ($post['destaque'] ?? 0),
            ];
            if ($supportsNextStep) {
                $data['proximo_post_id'] = null;
            }

            if ($existing !== null) {
                $assignments = 'titulo = :titulo, slug = :slug, resumo = :resumo, conteudo = :conteudo, categoria = :categoria, categoria_post_id = :categoria_post_id, imagem_capa = :imagem_capa, imagem_thumb = :imagem_thumb, autor_id = :autor_id, data_publicacao = :data_publicacao, tempo_leitura = :tempo_leitura, seo_title = :seo_title, seo_description = :seo_description, seo_keywords = :seo_keywords, tags = :tags, status = :status, destaque = :destaque';
                if ($supportsNextStep) {
                    $assignments .= ', proximo_post_id = :proximo_post_id';
                }
                $stmt = $pdo->prepare("UPDATE posts SET {$assignments} WHERE id = :id");
                $stmt->execute($data + ['id' => (int) $existing['id']]);
                $targetPostId = (int) $existing['id'];
                $stats['updated']++;
                if ($currentTargetSlug !== '' && $currentTargetSlug !== $currentSlug && $this->storePostSlug($pdo, $targetPostId, $currentTargetSlug)) {
                    $stats['history_added']++;
                }
            } else {
                $columns = 'titulo, slug, resumo, conteudo, categoria, categoria_post_id, imagem_capa, imagem_thumb, autor_id, data_publicacao, tempo_leitura, seo_title, seo_description, seo_keywords, tags, status, destaque';
                $values = ':titulo, :slug, :resumo, :conteudo, :categoria, :categoria_post_id, :imagem_capa, :imagem_thumb, :autor_id, :data_publicacao, :tempo_leitura, :seo_title, :seo_description, :seo_keywords, :tags, :status, :destaque';
                if ($supportsNextStep) {
                    $columns .= ', proximo_post_id';
                    $values .= ', :proximo_post_id';
                }
                $stmt = $pdo->prepare("INSERT INTO posts ({$columns}, views, curtidas, comentarios_count, likes_count) VALUES ({$values}, 0, 0, 0, 0)");
                $stmt->execute($data);
                $targetPostId = (int) $pdo->lastInsertId();
                $stats['created']++;
            }

            $targetIdBySlug[$currentSlug] = $targetPostId;
            $nextStepSlugByTargetId[$targetPostId] = $nextStepSlug;

            foreach ($knownSlugs as $historySlug) {
                if ($historySlug !== $currentSlug && $this->storePostSlug($pdo, $targetPostId, $historySlug)) {
                    $stats['history_added']++;
                }
            }
        }

        if ($supportsNextStep && $nextStepSlugByTargetId !== []) {
            $updateNextStep = $pdo->prepare('UPDATE posts SET proximo_post_id = :proximo_post_id WHERE id = :id');
            foreach ($nextStepSlugByTargetId as $targetPostId => $nextStepSlug) {
                $targetPostId = (int) $targetPostId;
                if ($targetPostId <= 0) {
                    continue;
                }

                $nextTargetId = null;
                $normalizedNextSlug = trim((string) $nextStepSlug);
                if ($normalizedNextSlug !== '') {
                    $nextTargetId = (int) ($targetIdBySlug[$normalizedNextSlug] ?? 0);
                    if ($nextTargetId <= 0) {
                        $nextExisting = $this->fetchOne($pdo, 'SELECT id FROM posts WHERE slug = :slug LIMIT 1', ['slug' => $normalizedNextSlug]);
                        $nextTargetId = (int) ($nextExisting['id'] ?? 0);
                    }
                }

                if ($nextTargetId > 0 && $nextTargetId !== $targetPostId) {
                    $updateNextStep->bindValue(':proximo_post_id', $nextTargetId, PDO::PARAM_INT);
                    $stats['next_step_links']++;
                } else {
                    $updateNextStep->bindValue(':proximo_post_id', null, PDO::PARAM_NULL);
                }
                $updateNextStep->bindValue(':id', $targetPostId, PDO::PARAM_INT);
                $updateNextStep->execute();
            }
        }

        return $stats;
    }

    private function tableHasColumn(PDO $pdo, string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnSupportCache)) {
            return (bool) $this->columnSupportCache[$cacheKey];
        }

        $stmt = $pdo->prepare("SELECT 1
                               FROM information_schema.COLUMNS
                               WHERE TABLE_SCHEMA = DATABASE()
                                 AND TABLE_NAME = :table
                                 AND COLUMN_NAME = :column
                               LIMIT 1");
        $stmt->bindValue(':table', $table, PDO::PARAM_STR);
        $stmt->bindValue(':column', $column, PDO::PARAM_STR);
        $stmt->execute();

        $this->columnSupportCache[$cacheKey] = $stmt->fetchColumn() !== false;
        return (bool) $this->columnSupportCache[$cacheKey];
    }

    private function findTargetPost(PDO $pdo, string $currentSlug, array $knownSlugs): ?array
    {
        $direct = $this->fetchOne($pdo, 'SELECT id, slug FROM posts WHERE slug = :slug LIMIT 1', ['slug' => $currentSlug]);
        if ($direct !== null) {
            return $direct;
        }

        foreach ($knownSlugs as $slug) {
            $row = $this->fetchOne($pdo, 'SELECT p.id, p.slug FROM post_slug_history h INNER JOIN posts p ON p.id = h.post_id WHERE h.slug = :slug ORDER BY h.id DESC LIMIT 1', ['slug' => $slug]);
            if ($row !== null) {
                return $row;
            }
        }

        return null;
    }

    private function storePostSlug(PDO $pdo, int $postId, string $slug): bool
    {
        $slug = trim($slug);
        if ($postId <= 0 || $slug === '') {
            return false;
        }

        $existing = $this->fetchOne($pdo, 'SELECT post_id FROM post_slug_history WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
        if ($existing !== null) {
            return (int) ($existing['post_id'] ?? 0) === $postId;
        }

        $stmt = $pdo->prepare('INSERT INTO post_slug_history (post_id, slug, created_at) VALUES (:post_id, :slug, NOW())');
        $stmt->execute(['post_id' => $postId, 'slug' => $slug]);
        return true;
    }

    private function resolveAuthorId(PDO $pdo, int $authorId): int
    {
        if ($authorId > 0) {
            $existing = $this->fetchOne($pdo, 'SELECT id FROM usuarios WHERE id = :id LIMIT 1', ['id' => $authorId]);
            if ($existing !== null) {
                return (int) $existing['id'];
            }
        }

        $fallback = $this->fetchOne($pdo, 'SELECT id FROM usuarios ORDER BY id ASC LIMIT 1');
        return (int) ($fallback['id'] ?? 1);
    }

    private function applyLinks(PDO $pdo, array $links): array
    {
        $stats = ['created' => 0, 'updated' => 0];

        foreach ($links as $link) {
            $slug = trim((string) ($link['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $data = [
                'titulo' => (string) ($link['titulo'] ?? ''),
                'slug' => $slug,
                'url' => (string) ($link['url'] ?? ''),
                'tipo' => (string) ($link['tipo'] ?? 'produto'),
                'promocao' => (int) ($link['promocao'] ?? 0),
                'desconto_percentual' => $this->nullableString($link['desconto_percentual'] ?? null),
                'desconto_contexto' => $this->nullableString($link['desconto_contexto'] ?? null),
                'codigo_cupom' => $this->nullableString($link['codigo_cupom'] ?? null),
                'secao_publica' => (string) ($link['secao_publica'] ?? 'produtos'),
                'subgrupo_publico' => $this->nullableString($link['subgrupo_publico'] ?? null),
                'descricao' => $this->nullableString($link['descricao'] ?? null),
                'cta_curto' => $this->nullableString($link['cta_curto'] ?? null),
                'texto_botao' => $this->nullableString($link['texto_botao'] ?? null),
                'selo' => $this->nullableString($link['selo'] ?? null),
                'imagem' => $this->nullableString($link['imagem'] ?? null),
                'posicao' => (int) ($link['posicao'] ?? 0),
                'status' => (string) ($link['status'] ?? 'ativo'),
                'destaque' => (int) ($link['destaque'] ?? 0),
                'expira_em' => $this->nullableString($link['expira_em'] ?? null),
            ];

            $existing = $this->fetchOne($pdo, 'SELECT id FROM links WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
            if ($existing !== null) {
                $stmt = $pdo->prepare('UPDATE links SET titulo = :titulo, slug = :slug, url = :url, tipo = :tipo, promocao = :promocao, desconto_percentual = :desconto_percentual, desconto_contexto = :desconto_contexto, codigo_cupom = :codigo_cupom, secao_publica = :secao_publica, subgrupo_publico = :subgrupo_publico, descricao = :descricao, cta_curto = :cta_curto, texto_botao = :texto_botao, selo = :selo, imagem = :imagem, posicao = :posicao, status = :status, destaque = :destaque, expira_em = :expira_em WHERE id = :id');
                $stmt->execute($data + ['id' => (int) $existing['id']]);
                $stats['updated']++;
            } else {
                $stmt = $pdo->prepare('INSERT INTO links (titulo, slug, url, tipo, promocao, desconto_percentual, desconto_contexto, codigo_cupom, secao_publica, subgrupo_publico, descricao, cta_curto, texto_botao, selo, imagem, posicao, status, destaque, expira_em) VALUES (:titulo, :slug, :url, :tipo, :promocao, :desconto_percentual, :desconto_contexto, :codigo_cupom, :secao_publica, :subgrupo_publico, :descricao, :cta_curto, :texto_botao, :selo, :imagem, :posicao, :status, :destaque, :expira_em)');
                $stmt->execute($data);
                $stats['created']++;
            }
        }

        return $stats;
    }

    private function applyConfiguracoes(PDO $pdo, array $configs): array
    {
        $stats = ['saved' => 0];
        if ($configs === []) {
            return $stats;
        }

        $stmt = $pdo->prepare('INSERT INTO configuracoes (chave, valor, updated_at) VALUES (:chave, :valor, NOW()) ON DUPLICATE KEY UPDATE valor = VALUES(valor), updated_at = NOW()');
        foreach ($configs as $config) {
            $key = trim((string) ($config['chave'] ?? ''));
            if ($key === '') {
                continue;
            }
            $stmt->execute(['chave' => $key, 'valor' => (string) ($config['valor'] ?? '')]);
            $stats['saved']++;
        }

        return $stats;
    }

    private function assertAppliedPostIntegrity(PDO $pdo, array $posts): void
    {
        $slugs = array_values(array_unique(array_filter(array_map(
            static fn (array $post): string => trim((string) ($post['slug'] ?? '')),
            $posts
        ))));

        if ($slugs === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($slugs), '?'));
        $stmt = $pdo->prepare('SELECT slug, COUNT(*) AS total FROM posts WHERE slug IN (' . $placeholders . ') GROUP BY slug');
        $stmt->execute($slugs);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $counts = [];
        foreach ($rows as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $counts[$slug] = (int) ($row['total'] ?? 0);
        }

        $missing = [];
        $duplicated = [];
        foreach ($slugs as $slug) {
            $total = (int) ($counts[$slug] ?? 0);
            if ($total <= 0) {
                $missing[] = $slug;
                continue;
            }

            if ($total > 1) {
                $duplicated[] = $slug;
            }
        }

        if ($missing !== [] || $duplicated !== []) {
            $parts = [];
            if ($missing !== []) {
                $parts[] = 'slugs ausentes: ' . implode(', ', array_slice($missing, 0, 10));
            }
            if ($duplicated !== []) {
                $parts[] = 'slugs duplicados: ' . implode(', ', array_slice($duplicated, 0, 10));
            }

            throw new RuntimeException('Falha na validacao de integridade apos aplicacao (' . implode(' | ', $parts) . ').');
        }
    }

    private function applyUploads(array $uploadsConfig, string $tmpDir): array
    {
        $files = $this->listFiles($tmpDir . DIRECTORY_SEPARATOR . 'uploads');
        $mode = strtolower((string) ($uploadsConfig['mode'] ?? 'local'));

        if ($mode === 'local') {
            $root = rtrim((string) ($uploadsConfig['path'] ?? ''), '\\/');
            if ($root === '') {
                throw new RuntimeException('Destino local de uploads nao configurado.');
            }

            foreach ($files as $file) {
                $relative = substr(str_replace('\\', '/', $file), strlen(str_replace('\\', '/', $tmpDir)) + 1);
                $relative = preg_replace('#^uploads/#', '', $relative) ?? $relative;
                $destination = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                $destinationDir = dirname($destination);
                if (!is_dir($destinationDir) && !mkdir($destinationDir, 0777, true) && !is_dir($destinationDir)) {
                    throw new RuntimeException('Nao foi possivel criar a pasta local de uploads.');
                }
                if (!copy($file, $destination)) {
                    throw new RuntimeException('Falha ao copiar o upload local: ' . $relative);
                }
            }

            return ['uploaded' => count($files), 'mode' => 'local'];
        }

        if ($mode !== 'ftp') {
            throw new RuntimeException('Modo de uploads nao suportado para aplicacao: ' . $mode);
        }

        foreach (['host', 'port', 'username', 'password', 'root'] as $required) {
            if (trim((string) ($uploadsConfig[$required] ?? '')) === '') {
                throw new RuntimeException('Configuracao FTP incompleta: faltando ' . $required);
            }
        }

        $ftp = @ftp_connect((string) $uploadsConfig['host'], (int) $uploadsConfig['port'], 30);
        if ($ftp === false) {
            throw new RuntimeException('Nao foi possivel conectar ao FTP configurado.');
        }

        try {
            if (!@ftp_login($ftp, (string) $uploadsConfig['username'], (string) $uploadsConfig['password'])) {
                throw new RuntimeException('Falha de login no FTP.');
            }
            ftp_pasv($ftp, (bool) ($uploadsConfig['passive'] ?? true));
            $root = rtrim((string) $uploadsConfig['root'], '/');

            foreach ($files as $file) {
                $relative = substr(str_replace('\\', '/', $file), strlen(str_replace('\\', '/', $tmpDir)) + 1);
                $relative = preg_replace('#^uploads/#', '', $relative) ?? $relative;
                $remotePath = $root . '/' . $relative;
                $this->ensureRemoteDirectory($ftp, dirname($remotePath));
                if (!@ftp_put($ftp, $remotePath, $file, FTP_BINARY)) {
                    throw new RuntimeException('Falha ao enviar o upload para a producao: ' . $relative);
                }
            }
        } finally {
            ftp_close($ftp);
        }

        return ['uploaded' => count($files), 'mode' => 'ftp'];
    }
    private function verifyPackageDirectory(string $packageDir, array $manifest): array
    {
        $dataResults = [];
        $allValid = true;
        $textIssues = [];

        foreach ((array) ($manifest['data_files'] ?? []) as $name => $fileInfo) {
            $dataResults[$name] = $this->verifyEntry((array) $fileInfo, $packageDir, true);
            $allValid = $allValid && (bool) ($dataResults[$name]['valid'] ?? false);

            if ((bool) ($dataResults[$name]['valid'] ?? false)) {
                $path = $packageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) ($fileInfo['name'] ?? ''));
                $decoded = json_decode((string) file_get_contents($path), true);
                if (is_array($decoded)) {
                    $issues = $this->findPayloadTextIssues($decoded, (string) $name);
                    if ($issues !== []) {
                        $dataResults[$name]['valid'] = false;
                        $dataResults[$name]['message'] = 'Texto suspeito detectado no pacote.';
                        $dataResults[$name]['text_issues'] = array_slice($issues, 0, 20);
                        $allValid = false;
                        $textIssues = array_merge($textIssues, array_slice($issues, 0, 20));
                    }
                }
            }
        }

        $uploadsResult = $this->verifyEntry((array) ($manifest['uploads'] ?? []), $packageDir, false);
        $allValid = $allValid && (bool) ($uploadsResult['valid'] ?? false);

        return ['is_valid' => $allValid, 'data_files' => $dataResults, 'uploads' => $uploadsResult, 'text_issues' => $textIssues];
    }

    private function verifyEntry(array $entry, string $packageDir, bool $expectJson): array
    {
        $name = (string) ($entry['name'] ?? '');
        if ($name === '') {
            return ['valid' => false, 'message' => 'Sem nome de arquivo.'];
        }

        $path = $packageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
        if (!is_file($path)) {
            return ['valid' => false, 'message' => 'Arquivo nao encontrado: ' . $name];
        }
        if ((int) ($entry['size_bytes'] ?? 0) > 0 && filesize($path) !== (int) $entry['size_bytes']) {
            return ['valid' => false, 'message' => 'Tamanho divergente em ' . $name];
        }
        if (($entry['sha1'] ?? null) !== null && (string) $entry['sha1'] !== '' && sha1_file($path) !== (string) $entry['sha1']) {
            return ['valid' => false, 'message' => 'Hash divergente em ' . $name];
        }
        if ($expectJson && !is_array(json_decode((string) file_get_contents($path), true))) {
            return ['valid' => false, 'message' => 'JSON invalido em ' . $name];
        }

        return ['valid' => true, 'message' => 'OK'];
    }

    private function findPayloadTextIssues(array $payload, string $sourceName): array
    {
        $issues = [];
        $this->inspectPayloadNode($payload, $sourceName, '$', $issues);
        return $issues;
    }

    private function inspectPayloadNode(mixed $value, string $sourceName, string $path, array &$issues): void
    {
        if (count($issues) >= 100) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $nextPath = is_int($key)
                    ? $path . '[' . $key . ']'
                    : $path . "['" . (string) $key . "']";
                $this->inspectPayloadNode($child, $sourceName, $nextPath, $issues);
            }
            return;
        }

        if (!is_string($value)) {
            return;
        }

        $text = trim($value);
        if ($text === '') {
            return;
        }

        $reason = $this->detectBrokenTextReason($text);
        if ($reason === null) {
            return;
        }

        $snippet = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $snippet = mb_substr($snippet, 0, 140, 'UTF-8');
        $issues[] = $sourceName . ' ' . $path . ' => ' . $reason . ' :: ' . $snippet;
    }

    private function detectBrokenTextReason(string $text): ?string
    {
        if (preg_match('/\x{00C3}./u', $text) === 1) {
            return 'mojibake-utf8';
        }

        if (preg_match('/\x{00C2}./u', $text) === 1) {
            return 'mojibake-cp1252';
        }

        if (preg_match('/\x{FFFD}/u', $text) === 1) {
            return 'replacement-char';
        }

        $withoutUrls = preg_replace('~https?://\S+|/[^\s\'"]*\?[^\s\'"]*~u', '', $text) ?? $text;
        if (preg_match('/\p{L}\?\p{L}/u', $withoutUrls) === 1) {
            return 'question-inside-word';
        }

        return null;
    }

    private function readPayload(string $packageDir): array
    {
        $result = [];
        foreach (['categoria_post.json', 'posts.json', 'post_slug_history.json', 'links.json', 'configuracoes.json'] as $fileName) {
            $path = $packageDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . $fileName;
            if (!is_file($path)) {
                throw new RuntimeException('Arquivo de dados ausente no pacote: ' . $fileName);
            }
            $decoded = json_decode((string) file_get_contents($path), true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Arquivo JSON invalido no pacote: ' . $fileName);
            }
            $result[$fileName] = $decoded;
        }
        return $result;
    }

    private function connectPdo(array $databaseConfig): PDO
    {
        foreach (['host', 'port', 'database', 'username'] as $required) {
            if (trim((string) ($databaseConfig[$required] ?? '')) === '') {
                throw new RuntimeException('Configuracao de banco incompleta: faltando ' . $required);
            }
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', (string) $databaseConfig['host'], (string) $databaseConfig['port'], (string) $databaseConfig['database']);
        return new PDO($dsn, (string) $databaseConfig['username'], (string) ($databaseConfig['password'] ?? ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    }

    private function packageRoot(): string
    {
        $root = (string) ($this->config['package_root'] ?? '');
        if ($root === '') {
            throw new RuntimeException('CONTENT_SYNC_ROOT nao configurado.');
        }
        if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
            throw new RuntimeException('Nao foi possivel criar a pasta raiz dos pacotes: ' . $root);
        }
        return $root;
    }

    private function profilePackageRoot(string $profileName): string
    {
        $baseRoot = $this->packageRoot();
        $directory = self::CONTENT_DIRECTORIES[$profileName] ?? null;
        if ($directory === null) {
            throw new RuntimeException('Perfil de conteudo nao suportado na estrutura numerada: ' . $profileName);
        }

        $root = $baseRoot . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . 'conteudo';
        if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
            throw new RuntimeException('Nao foi possivel criar a pasta de conteudo do perfil: ' . $root);
        }

        return $root;
    }

    private function buildPackageId(string $type, string $profileName): string
    {
        return sprintf('%s-%s-%s', strtoupper(trim($type)), $this->profileToken($profileName), date('Ymd-His'));
    }

    private function profileToken(string $profileName): string
    {
        return match (strtolower(trim($profileName))) {
            'local' => 'LOCAL',
            'stage' => 'STAGE',
            'production' => 'PROD',
            default => strtoupper(preg_replace('/[^A-Z0-9]+/i', '-', trim($profileName)) ?: 'GEN'),
        };
    }

    private function codePackageRoot(): string
    {
        $root = (string) ($this->config['code_package_root'] ?? '');
        if ($root === '') {
            throw new RuntimeException('CONTENT_SYNC_CODE_ROOT nao configurado.');
        }
        if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
            throw new RuntimeException('Nao foi possivel criar a pasta raiz dos pacotes de codigo: ' . $root);
        }
        return $root;
    }

    private function buildCodePackageId(): string
    {
        $commit = $this->currentHeadCommit();
        $suffix = $commit !== '' ? '_' . $commit : '';

        return 'code_' . date('Y-m-d_H-i-s') . $suffix;
    }

    private function currentHeadCommit(): string
    {
        $output = [];
        $exitCode = 0;
        $command = sprintf(
            'git -C %s rev-parse --short HEAD 2>&1',
            escapeshellarg($this->projectRoot())
        );

        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            return '';
        }

        return trim((string) ($output[0] ?? ''));
    }

    private function profile(string $profileName): array
    {
        $profiles = (array) ($this->config['profiles'] ?? []);
        if (!isset($profiles[$profileName]) || !is_array($profiles[$profileName])) {
            throw new RuntimeException('Perfil de conteudo nao encontrado: ' . $profileName);
        }
        return $profiles[$profileName];
    }

    private function codeDeployConfig(string $profileName): array
    {
        $profile = $this->profile($profileName);
        $config = (array) ($profile['code_deploy'] ?? []);
        if ($config === []) {
            $uploads = (array) ($profile['uploads'] ?? []);
            $config = [
                'mode' => (string) ($uploads['mode'] ?? 'ftp'),
                'host' => (string) ($uploads['host'] ?? ''),
                'port' => (int) ($uploads['port'] ?? 21),
                'username' => (string) ($uploads['username'] ?? ''),
                'password' => (string) ($uploads['password'] ?? ''),
                'root' => (string) preg_replace('~/uploads/?$~i', '', (string) ($uploads['root'] ?? '')),
                'passive' => (bool) ($uploads['passive'] ?? true),
            ];
        }

        $mode = strtolower(trim((string) ($config['mode'] ?? 'ftp')));
        if ($mode === 'local') {
            $root = trim((string) ($config['root'] ?? ''));
            if ($root === '') {
                throw new RuntimeException('Configuracao de deploy local incompleta: root.');
            }
            return ['mode' => 'local', 'root' => $root];
        }

        foreach (['host', 'port', 'username', 'password', 'root'] as $required) {
            if (trim((string) ($config[$required] ?? '')) === '') {
                throw new RuntimeException('Configuracao de deploy de codigo incompleta: faltando ' . $required);
            }
        }

        return [
            'mode' => 'ftp',
            'host' => (string) $config['host'],
            'port' => (int) $config['port'],
            'username' => (string) $config['username'],
            'password' => (string) $config['password'],
            'root' => rtrim((string) $config['root'], '/'),
            'passive' => (bool) ($config['passive'] ?? true),
        ];
    }

    private function writeManifest(string $packageDir, array $manifest): void
    {
        $this->writeJson($packageDir . DIRECTORY_SEPARATOR . 'manifest.json', $manifest);
    }

    private function writeJson(string $path, array $payload): void
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new RuntimeException('Falha ao serializar JSON.');
        }
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('Nao foi possivel criar a pasta do arquivo: ' . $path);
        }
        file_put_contents($path, $json . PHP_EOL);
    }

    private function fileDetails(string $path, string $name): array
    {
        return ['name' => $name, 'path' => $path, 'size_bytes' => is_file($path) ? (int) filesize($path) : 0, 'sha1' => is_file($path) ? sha1_file($path) : null];
    }

        private function compressDirectory(string $sourceDirectory, string $zipPath): void
    {
        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $files = $this->listFiles($sourceDirectory);
                $rootLength = strlen(rtrim($sourceDirectory, '\\/')) + 1;
                foreach ($files as $file) {
                    $relativePath = substr($file, $rootLength);
                    $zip->addFile($file, str_replace('\\', '/', $relativePath));
                }
                if ($zip->close()) {
                    return;
                }
            }
        }

        $sevenZip = $this->sevenZipBinary();
        $command = sprintf('%s a -tzip -mx=5 %s .', escapeshellarg($sevenZip), escapeshellarg($zipPath));
        $cwd = getcwd();
        chdir($sourceDirectory);
        exec($command . ' 2>&1', $output, $exitCode);
        if ($cwd !== false) {
            chdir($cwd);
        }

        if ($exitCode !== 0 || !is_file($zipPath)) {
            throw new RuntimeException('Nao foi possivel criar o zip do pacote. ' . trim(implode(PHP_EOL, $output)));
        }
    }

        private function extractArchive(string $zipPath): string
    {
        if (!is_file($zipPath)) {
            throw new RuntimeException('Arquivo zip nao encontrado: ' . $zipPath);
        }
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'en-content-restore-' . bin2hex(random_bytes(6));
        if (!mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
            throw new RuntimeException('Nao foi possivel criar a pasta temporaria do pacote.');
        }

        if (class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) === true) {
                if ($zip->extractTo($tmpDir)) {
                    $zip->close();
                    return $tmpDir;
                }
                $zip->close();
            }
        }

        $sevenZip = $this->sevenZipBinary();
        $command = sprintf('%s x -y %s -o%s', escapeshellarg($sevenZip), escapeshellarg($zipPath), escapeshellarg($tmpDir));
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Nao foi possivel extrair o zip do pacote. ' . trim(implode(PHP_EOL, $output)));
        }

        return $tmpDir;
    }

    private function sevenZipBinary(): string
    {
        $configured = (string) ($this->config['seven_zip_binary'] ?? '');
        $candidates = array_filter([
            $configured,
            'C:\\Program Files\\7-Zip\\7z.exe',
            'C:\\Program Files (x86)\\7-Zip\\7z.exe',
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('7-Zip nao encontrado para compactar ou extrair o pacote.');
    }
    private function listFiles(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }
        $result = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $result[] = $item->getPathname();
            }
        }
        sort($result);
        return $result;
    }

    private function deployCode(array $deployConfig, string $sourceDir): array
    {
        $mode = (string) ($deployConfig['mode'] ?? 'ftp');
        if ($mode === 'local') {
            return $this->deployCodeLocal((string) ($deployConfig['root'] ?? ''), $sourceDir);
        }

        if ($mode !== 'ftp') {
            throw new RuntimeException('Modo de deploy de codigo nao suportado: ' . $mode);
        }

        return $this->deployCodeFtp($deployConfig, $sourceDir);
    }

    private function deployCodeLocal(string $targetRoot, string $sourceDir): array
    {
        $targetRoot = rtrim($targetRoot, '\\/');
        if ($targetRoot === '' || !is_dir($targetRoot)) {
            throw new RuntimeException('Raiz local de deploy de codigo nao encontrada: ' . $targetRoot);
        }

        $files = $this->listFiles($sourceDir);
        $copied = 0;
        foreach ($files as $file) {
            $relative = substr(str_replace('\\', '/', $file), strlen(str_replace('\\', '/', $sourceDir)) + 1);
            $relative = ltrim($relative, '/');
            if ($relative === '' || str_contains($relative, '..')) {
                continue;
            }

            $destination = $this->resolveCodeDeployPath($targetRoot, $relative);
            $destinationDir = dirname($destination);
            if (!is_dir($destinationDir) && !mkdir($destinationDir, 0777, true) && !is_dir($destinationDir)) {
                throw new RuntimeException('Nao foi possivel criar pasta de destino para deploy local: ' . $relative);
            }
            if (!copy($file, $destination)) {
                throw new RuntimeException('Falha ao copiar arquivo do pacote de codigo: ' . $relative);
            }
            $copied++;
        }

        return [
            'mode' => 'local',
            'files_applied' => $copied,
            'target_root' => $targetRoot,
            'target_public_root' => $this->resolveCodeDeployPublicRoot($targetRoot),
        ];
    }

    private function deployCodeFtp(array $deployConfig, string $sourceDir): array
    {
        $ftp = @ftp_connect((string) $deployConfig['host'], (int) $deployConfig['port'], 30);
        if ($ftp === false) {
            throw new RuntimeException('Nao foi possivel conectar ao FTP da producao para deploy de codigo.');
        }

        $uploaded = 0;
        try {
            if (!@ftp_login($ftp, (string) $deployConfig['username'], (string) $deployConfig['password'])) {
                throw new RuntimeException('Falha de login no FTP da producao para deploy de codigo.');
            }
            ftp_pasv($ftp, (bool) ($deployConfig['passive'] ?? true));
            $root = $this->resolveCodeDeployRoot($ftp, (string) ($deployConfig['root'] ?? ''));

            $files = $this->listFiles($sourceDir);
            foreach ($files as $file) {
                $relative = substr(str_replace('\\', '/', $file), strlen(str_replace('\\', '/', $sourceDir)) + 1);
                $relative = ltrim($relative, '/');
                if ($relative === '' || str_contains($relative, '..')) {
                    continue;
                }

                $remotePath = $this->resolveCodeDeployPath($root, $relative);
                $this->ensureRemoteDirectory($ftp, dirname($remotePath));
                if (!@ftp_put($ftp, $remotePath, $file, FTP_BINARY)) {
                    throw new RuntimeException('Falha ao enviar arquivo do pacote de codigo: ' . $relative);
                }
                $uploaded++;
            }
        } finally {
            ftp_close($ftp);
        }

        return [
            'mode' => 'ftp',
            'files_applied' => $uploaded,
            'target_root' => $root,
            'target_public_root' => $this->resolveCodeDeployPublicRoot($root),
        ];
    }

    private function ensureRemoteDirectory($ftp, string $remoteDirectory): void
    {
        $parts = array_values(array_filter(explode('/', trim(str_replace('\\', '/', $remoteDirectory), '/')), static fn(string $part): bool => $part !== ''));
        $current = '';
        foreach ($parts as $part) {
            $current .= '/' . $part;
            @ftp_mkdir($ftp, $current);
        }
    }

    private function resolveCodeDeployRoot($ftp, string $configuredRoot): string
    {
        $root = rtrim(trim(str_replace('\\', '/', $configuredRoot)), '/');
        if ($root === '') {
            throw new RuntimeException('Configuracao de deploy de codigo incompleta: root.');
        }

        if (str_ends_with(strtolower($root), '/_app_core')) {
            return $root;
        }

        $indexContent = $this->readRemoteTextFile($ftp, $root . '/index.php');
        if (is_string($indexContent) && str_contains($indexContent, '/_app_core/bootstrap.php')) {
            return $root . '/_app_core';
        }

        if (@ftp_size($ftp, $root . '/_app_core/bootstrap.php') >= 0) {
            return $root . '/_app_core';
        }

        return $root;
    }

    private function resolveCodeDeployPublicRoot(string $codeRoot): string
    {
        $normalized = rtrim(str_replace('\\', '/', trim($codeRoot)), '/');
        if ($normalized === '') {
            throw new RuntimeException('Raiz de deploy de codigo invalida.');
        }

        if (str_ends_with(strtolower($normalized), '/_app_core')) {
            return substr($normalized, 0, -strlen('/_app_core'));
        }

        return $normalized;
    }

    private function resolveCodeDeployPath(string $codeRoot, string $relative): string
    {
        $relative = ltrim(str_replace('\\', '/', trim($relative)), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            throw new RuntimeException('Caminho relativo invalido no pacote de codigo: ' . $relative);
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', trim($codeRoot)), '/');
        if ($normalizedRoot === '') {
            throw new RuntimeException('Raiz de deploy de codigo invalida.');
        }

        if (str_ends_with(strtolower($normalizedRoot), '/_app_core')) {
            $publicRoot = $this->resolveCodeDeployPublicRoot($normalizedRoot);

            if (str_starts_with($relative, 'public/')) {
                $publicRelative = ltrim(substr($relative, strlen('public/')), '/');
                return $publicRoot . '/' . $publicRelative;
            }

            if (str_starts_with($relative, 'EN/')) {
                return $publicRoot . '/' . $relative;
            }
        }

        return $normalizedRoot . '/' . $relative;
    }

    private function readRemoteTextFile($ftp, string $remotePath): ?string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'en-content-sync-');
        if (!is_string($tmpPath) || $tmpPath === '') {
            return null;
        }

        try {
            if (!@ftp_get($ftp, $tmpPath, $remotePath, FTP_BINARY)) {
                return null;
            }
            $content = @file_get_contents($tmpPath);
            return is_string($content) ? $content : null;
        } finally {
            @unlink($tmpPath);
        }
    }

    private function fetchAll(PDO $pdo, string $sql, array $params = []): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function fetchOne(PDO $pdo, string $sql, array $params = []): ?array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function lockPath(string $root): string
    {
        return $root . DIRECTORY_SEPARATOR . '.content-sync-running.lock.json';
    }

    private function acquireRunLock(string $root, string $operation, string $profileName, string $profileLabel): array
    {
        $lockPath = $this->lockPath($root);
        $handle = fopen($lockPath, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Nao foi possivel criar o lock da rotina de conteudo.');
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            $raw = stream_get_contents($handle);
            $running = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            fclose($handle);
            $message = 'Ja existe uma rotina de conteudo em execucao.';
            if (is_array($running) && !empty($running['started_at'])) {
                $message .= ' Inicio: ' . (string) $running['started_at'];
            }
            throw new RuntimeException($message);
        }
        $payload = ['operation' => $operation, 'profile' => $profileName, 'profile_label' => $profileLabel, 'started_at' => date('c'), 'pid' => function_exists('getmypid') ? getmypid() : null];
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($handle);
        return ['path' => $lockPath, 'handle' => $handle];
    }

    private function releaseRunLock(array $lock): void
    {
        $handle = $lock['handle'] ?? null;
        $path = (string) ($lock['path'] ?? '');
        if (is_resource($handle)) {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    private function readRunLock(string $root): ?array
    {
        $lockPath = $this->lockPath($root);
        if (!is_file($lockPath)) {
            return null;
        }
        $payload = json_decode((string) file_get_contents($lockPath), true);
        if (!is_array($payload)) {
            return ['operation' => 'unknown', 'profile' => 'unknown', 'profile_label' => 'Rotina em execucao', 'started_at' => null];
        }
        return ['operation' => (string) ($payload['operation'] ?? 'unknown'), 'profile' => (string) ($payload['profile'] ?? 'unknown'), 'profile_label' => (string) ($payload['profile_label'] ?? 'Rotina em execucao'), 'started_at' => (string) ($payload['started_at'] ?? '')];
    }

    private function packageById(?string $packageId): ?array
    {
        $items = $this->allPackages();
        if ($packageId === null || trim($packageId) === '' || strtolower(trim($packageId)) === 'latest') {
            return $items[0] ?? null;
        }
        foreach ($items as $item) {
            if (($item['package_id'] ?? null) === $packageId) {
                return $item;
            }
        }
        return null;
    }

    private function codePackageById(?string $packageId): ?array
    {
        $items = $this->allCodePackages();
        if ($packageId === null || trim($packageId) === '' || strtolower(trim($packageId)) === 'latest') {
            return $items[0] ?? null;
        }
        foreach ($items as $item) {
            if (($item['package_id'] ?? null) === $packageId) {
                return $item;
            }
        }
        return null;
    }

    private function allPackages(): array
    {
        $dirs = [];
        foreach ($this->packageSearchRoots() as $root) {
            foreach (array_filter(glob($root . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [], 'is_dir') as $dir) {
                $dirs[] = $dir;
            }
        }
        $dirs = array_values(array_unique($dirs));
        rsort($dirs);
        $items = [];
        foreach ($dirs as $dir) {
            $manifestPath = $dir . DIRECTORY_SEPARATOR . 'manifest.json';
            if (!is_file($manifestPath)) {
                continue;
            }
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (!is_array($manifest)) {
                continue;
            }
            $manifest['_dir'] = $dir;
            $verification = $this->verifyPackageDirectory($dir, $manifest);
            $manifest['verification'] = $verification;
            $manifest['is_valid'] = (bool) ($verification['is_valid'] ?? false);
            $manifest['allowed_targets'] = $this->allowedApplyTargets((string) ($manifest['source_profile'] ?? ''));
            $items[] = $manifest;
        }
        return $items;
    }

    /**
     * @return array<int, string>
     */
    private function packageSearchRoots(): array
    {
        $roots = [];
        $baseRoot = $this->packageRoot();

        foreach (self::CONTENT_DIRECTORIES as $directory) {
            $candidate = $baseRoot . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . 'conteudo';
            if (is_dir($candidate)) {
                $roots[] = $candidate;
            }
        }

        $legacyRoot = trim((string) ($this->config['legacy_package_root'] ?? ''));
        if ($legacyRoot !== '' && is_dir($legacyRoot)) {
            $roots[] = $legacyRoot;
        }

        return array_values(array_unique($roots));
    }

    /**
     * @return array<int, string>
     */
    private function allowedApplyTargets(string $sourceProfile): array
    {
        return match (strtolower(trim($sourceProfile))) {
            'stage' => ['local', 'production'],
            'production' => ['local', 'stage'],
            default => [],
        };
    }

    private function allCodePackages(): array
    {
        $zipPaths = [];
        foreach ($this->codePackageSearchRoots() as $root) {
            foreach (glob($root . DIRECTORY_SEPARATOR . 'code_*.zip') ?: [] as $zipPath) {
                $zipPaths[] = $zipPath;
            }
        }

        $zipPaths = array_values(array_unique($zipPaths));
        usort($zipPaths, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));

        $items = [];
        foreach ($zipPaths as $zipPath) {
            $basename = basename($zipPath, '.zip');
            $packageDir = $root . DIRECTORY_SEPARATOR . $basename;
            $manifestPath = $packageDir . DIRECTORY_SEPARATOR . 'manifest.json';
            $manifest = $this->readJsonFile($manifestPath) ?? $this->readCodeManifestFromZip($zipPath) ?? [];
            $filesCount = (int) ($manifest['files_count'] ?? 0);
            if ($filesCount <= 0) {
                $filesCount = $this->countCodeFilesInZip($zipPath);
            }
            $commit = trim((string) ($manifest['commit'] ?? ''));
            if ($commit === '' && preg_match('/_([0-9a-f]{7,40})$/i', $basename, $matches) === 1) {
                $commit = (string) ($matches[1] ?? '');
            }

            $items[] = [
                'package_id' => (string) ($manifest['package_id'] ?? $basename),
                'commit' => $commit,
                'created_at' => (string) ($manifest['created_at'] ?? date('c', (int) filemtime($zipPath))),
                'files_count' => $filesCount,
                'notes' => (string) ($manifest['notes'] ?? ''),
                'files' => $this->listCodePackageFiles($packageDir, $zipPath),
                'applied_targets' => array_values((array) ($manifest['applied_targets'] ?? [])),
                'zip_path' => $zipPath,
                'manifest_path' => is_file($manifestPath) ? $manifestPath : '',
                '_dir' => $packageDir,
            ];
        }

        return $items;
    }

    /**
     * @return array{files: array<int, string>, ignored: array<int, string>}
     */
    private function collectCodePackageFiles(): array
    {
        $output = [];
        $exitCode = 0;
        $command = sprintf(
            'git -C %s status --short --untracked-files=all 2>&1',
            escapeshellarg($this->projectRoot())
        );

        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Nao foi possivel ler o estado atual do git para montar o pacote tecnico.');
        }

        $files = [];
        $ignored = [];

        foreach ($output as $line) {
            $line = rtrim((string) $line);
            if ($line === '') {
                continue;
            }

            $status = substr($line, 0, 2);
            $rawPath = trim(substr($line, 3));
            if ($rawPath === '') {
                continue;
            }

            if (str_contains($rawPath, ' -> ')) {
                $parts = explode(' -> ', $rawPath);
                $rawPath = (string) end($parts);
            }

            $relativePath = str_replace('/', DIRECTORY_SEPARATOR, $rawPath);
            $relativePath = ltrim($relativePath, '\\/');
            if ($relativePath === '') {
                continue;
            }

            if (str_contains($status, 'D')) {
                $ignored[] = $relativePath . ' [delete]';
                continue;
            }

            if (!$this->isCodePackagePathAllowed($relativePath)) {
                $ignored[] = $relativePath;
                continue;
            }

            $absolutePath = $this->projectRoot() . DIRECTORY_SEPARATOR . $relativePath;
            if (!is_file($absolutePath)) {
                $ignored[] = $relativePath . ' [missing]';
                continue;
            }

            $files[] = str_replace('\\', '/', $relativePath);
        }

        $files = array_values(array_unique($files));
        sort($files);
        $ignored = array_values(array_unique($ignored));
        sort($ignored);

        return [
            'files' => $files,
            'ignored' => $ignored,
        ];
    }

    private function isCodePackagePathAllowed(string $relativePath): bool
    {
        $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($normalized === '' || str_contains($normalized, '..')) {
            return false;
        }

        if ($normalized === '.env' || $normalized === '.env.example') {
            return false;
        }

        if (str_starts_with($normalized, 'storage/') || str_starts_with($normalized, 'public/uploads/')) {
            return false;
        }

        $allowedRoots = [
            'app/',
            'config/',
            'public/',
            'scripts/',
        ];

        foreach ($allowedRoots as $root) {
            if (str_starts_with($normalized, $root)) {
                return true;
            }
        }

        return in_array($normalized, ['bootstrap.php', 'public/.htaccess'], true);
    }

    /**
     * @param array<int, string> $files
     */
    private function copyCodePackageFiles(array $files, string $destinationRoot): void
    {
        foreach ($files as $relativePath) {
            $sourcePath = $this->projectRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $destinationPath = $destinationRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $destinationDir = dirname($destinationPath);

            if (!is_dir($destinationDir) && !mkdir($destinationDir, 0777, true) && !is_dir($destinationDir)) {
                throw new RuntimeException('Nao foi possivel criar a pasta do pacote tecnico: ' . $destinationDir);
            }

            if (!copy($sourcePath, $destinationPath)) {
                throw new RuntimeException('Falha ao copiar arquivo para o pacote tecnico: ' . $relativePath);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function codePackageSearchRoots(): array
    {
        $roots = [];

        $currentRoot = trim((string) ($this->config['code_package_root'] ?? ''));
        if ($currentRoot !== '') {
            $roots[] = $currentRoot;
        }

        $legacyRoot = trim((string) ($this->config['legacy_code_package_root'] ?? ''));
        if ($legacyRoot !== '') {
            $roots[] = $legacyRoot;
        }

        return array_values(array_unique(array_filter($roots, static fn(string $root): bool => $root !== '')));
    }

    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private function readJsonFile(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $raw = (string) file_get_contents($path);
        if ($raw === '') {
            return null;
        }
        $raw = str_replace("\xEF\xBB\xBF", '', $raw);
        $raw = str_replace("\u{FEFF}", '', $raw);

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProgress(?string $progressId): array
    {
        $progress = $this->normalizeProgressId($progressId);
        if ($progress === null) {
            return [
                'status' => 'idle',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Aguardando',
                'message' => 'Nenhuma rotina de conteudo em andamento.',
                'percent' => 0,
            ];
        }

        $path = $this->progressPath($progress);
        if (!is_file($path)) {
            return [
                'status' => 'idle',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Aguardando',
                'message' => 'Nenhum progresso encontrado para esta rotina.',
                'percent' => 0,
            ];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [
                'status' => 'error',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Erro de leitura',
                'message' => 'Nao foi possivel ler o andamento atual da rotina.',
                'percent' => 0,
            ];
        }

        return $decoded;
    }

    private function latestAppliedTarget(array $items, string $targetProfile): ?array
    {
        foreach ($items as $item) {
            foreach (array_reverse((array) ($item['applied_targets'] ?? [])) as $apply) {
                if (($apply['target_profile'] ?? '') !== $targetProfile) {
                    continue;
                }

                return [
                    'package_id' => (string) ($item['package_id'] ?? ''),
                    'applied_at' => (string) ($apply['applied_at'] ?? ''),
                    'target_profile' => (string) ($apply['target_profile'] ?? ''),
                    'target_profile_label' => (string) ($apply['target_profile_label'] ?? $targetProfile),
                ];
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function listCodePackageFiles(string $packageDir, string $zipPath): array
    {
        $sourceDir = $packageDir . DIRECTORY_SEPARATOR . 'files';
        if (is_dir($sourceDir)) {
            $files = $this->listFiles($sourceDir);
            $prefix = rtrim(str_replace('\\', '/', $sourceDir), '/') . '/';

            return array_map(
                static fn(string $file): string => ltrim(substr(str_replace('\\', '/', $file), strlen($prefix)), '/'),
                $files
            );
        }

        if (!is_file($zipPath) || !class_exists(ZipArchive::class)) {
            return [];
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return [];
        }

        try {
            $files = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = str_replace('\\', '/', (string) $zip->getNameIndex($index));
                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }
                if (!str_starts_with($name, 'files/')) {
                    continue;
                }

                $relative = substr($name, strlen('files/'));
                if ($relative !== '') {
                    $files[] = $relative;
                }
            }

            sort($files);
            return $files;
        } finally {
            $zip->close();
        }
    }

    private function readCodeManifestFromZip(string $zipPath): ?array
    {
        if (!is_file($zipPath) || !class_exists(ZipArchive::class)) {
            return null;
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return null;
        }

        try {
            $index = $zip->locateName('manifest.json', ZipArchive::FL_NOCASE);
            if ($index === false) {
                return null;
            }

            $content = $zip->getFromIndex($index);
            if (!is_string($content) || trim($content) === '') {
                return null;
            }
            $content = str_replace("\xEF\xBB\xBF", '', $content);
            $content = str_replace("\u{FEFF}", '', $content);

            $decoded = json_decode($content, true);
            return is_array($decoded) ? $decoded : null;
        } finally {
            $zip->close();
        }
    }

    private function countCodeFilesInZip(string $zipPath): int
    {
        if (!is_file($zipPath) || !class_exists(ZipArchive::class)) {
            return 0;
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return 0;
        }

        try {
            $count = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }
                if (!str_starts_with($name, 'files/')) {
                    continue;
                }
                $count++;
            }
            return $count;
        } finally {
            $zip->close();
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($directory);
    }

    private function allowLongRunningProcess(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
    }

    private function normalizeProgressId(?string $progressId): ?string
    {
        $value = strtolower(trim((string) $progressId));
        if ($value === '' || !preg_match('/^[a-z0-9_-]{8,80}$/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeProgress(?string $progressId, array $payload): void
    {
        $progress = $this->normalizeProgressId($progressId);
        if ($progress === null) {
            return;
        }

        $path = $this->progressPath($progress);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            return;
        }

        file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function updateProgress(?string $progressId, string $stage, string $message, int $percent): void
    {
        $this->writeProgress($progressId, [
            'status' => 'running',
            'title' => self::PROGRESS_TITLE,
            'stage' => $stage,
            'message' => $message,
            'percent' => max(0, min(100, $percent)),
            'updated_at' => date('c'),
        ]);
    }

    private function progressPath(string $progressId): string
    {
        $baseDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'content-sync' . DIRECTORY_SEPARATOR . 'progress';
        return $baseDirectory . DIRECTORY_SEPARATOR . $progressId . '.json';
    }
}
