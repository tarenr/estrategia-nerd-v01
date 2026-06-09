<?php

declare(strict_types=1);


namespace Scripts\Backup;

require_once __DIR__ . '/../operations/OperationLogger.php';

use Scripts\Operations\OperationLogger;
use RuntimeException;
use ZipArchive;

final class BackupManager
{
    private const DATA_DIRECTORIES = [
        'local' => '01-local',
        'stage' => '02-stage',
        'production' => '03-prod',
    ];
    private const SYSTEM_FILE_EXCLUDED_EXACT = [
        '.git',
        'node_modules',
        'storage',
        'public/uploads',
        'vendor',
    ];
    private const SYSTEM_FILE_EXCLUDED_PREFIXES = [
        '.git/',
        'node_modules/',
        'storage/',
        'public/uploads/',
        'vendor/',
    ];
    private const SYSTEM_FILE_EXCLUDED_SEGMENTS = [
        'uploads',
    ];
    private const PROGRESS_TITLE = 'Executando rotina de backup';

    private OperationLogger $logger;

    public function __construct(private array $config)
    {
        date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo');
        $this->logger = new OperationLogger($this->operationRoot());
    }

    public function run(string $profileName = 'local', ?string $progressId = null, bool $includeUploads = true): array
    {
        $this->allowLongRunningProcess();
        $profile = $this->profile($profileName);
        $operationRoot = $this->backupRoot();
        $backupRoot = $this->profileBackupRoot($profileName);
        $profileLabel = (string) ($profile['label'] ?? $profileName);
        $lock = null;
        $backupId = '';
        $this->writeProgress($progressId, [
            'status' => 'running',
            'title' => self::PROGRESS_TITLE,
            'stage' => 'Validando ambiente',
            'message' => sprintf('Preparando o backup de ambiente do perfil %s.', $profileLabel),
            'percent' => 6,
            'updated_at' => date('c'),
        ]);

        $temporaryDirectory = '';
        $manifest = [];
        $backupDir = '';

        try {
            $lock = $this->acquireRunLock($operationRoot, $profileName, (string) ($profile['label'] ?? $profileName));
            $profileSlug = trim((string) ($profile['slug'] ?? $profileName));
            $backupId = $this->buildBackupId('BS', $profileName);
            $backupDir = $backupRoot . DIRECTORY_SEPARATOR . $backupId;

            if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
                throw new RuntimeException('Nao foi possivel criar a pasta do backup: ' . $backupDir);
            }

            $manifest = [
                'backup_id' => $backupId,
                'profile' => $profileName,
                'profile_label' => (string) ($profile['label'] ?? $profileName),
                'profile_slug' => $profileSlug,
                'created_at' => date('c'),
                'status' => 'running',
                'kind' => $includeUploads ? 'system_full' : 'system_without_uploads',
                'includes_uploads' => $includeUploads,
                'cloud_uploaded' => false,
                'cloud_uploaded_at' => null,
                'database' => null,
                'uploads' => null,
                'system_files' => null,
                'system_files_count' => 0,
                'error' => null,
            ];

            $databasePath = $backupDir . DIRECTORY_SEPARATOR . 'database.sql';
            $this->updateProgress($progressId, 'Exportando banco', sprintf('Gerando database.sql do ambiente %s.', $profileLabel), 22);
            $this->backupDatabase((array) ($profile['database'] ?? []), $databasePath);
            $manifest['database'] = $this->fileDetails($databasePath, 'database.sql');

            if ($includeUploads) {
                $uploadsZipPath = $backupDir . DIRECTORY_SEPARATOR . 'uploads.zip';
                $this->updateProgress($progressId, 'Coletando uploads', sprintf('Preparando a arvore de uploads do ambiente %s.', $profileLabel), 44);
                $temporaryDirectory = $this->materializeUploads((array) ($profile['uploads'] ?? []));
                $this->updateProgress($progressId, 'Compactando uploads', 'Gerando uploads.zip para fechar o backup de ambiente.', 64);
                $this->compressDirectory($temporaryDirectory, $uploadsZipPath);
                $manifest['uploads'] = $this->fileDetails($uploadsZipPath, 'uploads.zip');
                if ($temporaryDirectory !== '' && str_contains($temporaryDirectory, sys_get_temp_dir())) {
                    $this->removeDirectory($temporaryDirectory);
                    $temporaryDirectory = '';
                }
            } else {
                $manifest['uploads'] = [
                    'included' => false,
                    'name' => null,
                    'size_bytes' => 0,
                    'sha256' => null,
                    'message' => 'Uploads ignorados por escolha da rotina.',
                ];
                $this->updateProgress($progressId, 'Pulando uploads', 'Backup configurado para gerar somente banco e sistema.', 64);
            }

            $systemFilesZipPath = $backupDir . DIRECTORY_SEPARATOR . 'system-files.zip';
            $this->updateProgress($progressId, 'Coletando sistema', sprintf('Preparando arquivos do sistema do ambiente %s.', $profileLabel), 72);
            $temporaryDirectory = $this->materializeSystemFiles((array) ($profile['system_files'] ?? []), (array) ($profile['uploads'] ?? []));
            $manifest['system_files_count'] = $this->countFiles($temporaryDirectory);
            $this->updateProgress($progressId, 'Compactando sistema', 'Gerando system-files.zip com os arquivos do sistema.', 82);
            $this->compressDirectory($temporaryDirectory, $systemFilesZipPath);
            $manifest['system_files'] = $this->fileDetails($systemFilesZipPath, 'system-files.zip');

            $manifest['status'] = 'ready';
            $manifest['verified_at'] = date('c');
            $this->updateProgress($progressId, 'Gravando manifesto', 'Registrando o manifesto local do backup gerado.', 90);
            $this->writeManifest($backupDir, $manifest);
            $this->updateProgress($progressId, 'Aplicando retencao', 'Limpando backups excedentes conforme a politica de retencao.', 94);
            $this->applyRetention($profileName);
            $this->logOperation('backup_dados', $profileName, $profileName, (string) $backupId, 'OK', $includeUploads ? 'Backup completo do sistema concluido.' : 'Backup do sistema sem uploads concluido.');

            $this->writeProgress($progressId, [
                'status' => 'completed',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Backup concluido',
                'message' => sprintf('Backup %s concluido com sucesso para o ambiente %s.', $backupId, $profileLabel),
                'percent' => 100,
                'updated_at' => date('c'),
            ]);

            return $manifest;
        } catch (\Throwable $exception) {
            if ($manifest !== [] && $backupDir !== '') {
                $manifest['status'] = 'failed';
                $manifest['error'] = $exception->getMessage();
                $this->writeManifest($backupDir, $manifest);
            }
            $this->logOperation('backup_dados', $profileName, $profileName, $backupId !== '' ? (string) $backupId : 'sem-id', 'FAIL', $exception->getMessage());
            $this->writeProgress($progressId, [
                'status' => 'error',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Falha no backup',
                'message' => $exception->getMessage(),
                'percent' => 100,
                'updated_at' => date('c'),
            ]);
            throw $exception;
        } finally {
            if ($temporaryDirectory !== '' && str_contains($temporaryDirectory, sys_get_temp_dir())) {
                $this->removeDirectory($temporaryDirectory);
            }

            if (is_array($lock)) {
                $this->releaseRunLock($lock);
            }
        }
    }

    public function status(bool $withVerification = true, ?int $limit = null, int $offset = 0): array
    {
        try {
            $backupRoot = $this->backupRoot();
        } catch (RuntimeException $exception) {
            return [
                'backup_root' => (string) ($this->config['backup_root'] ?? ''),
                'available' => false,
                'unavailable_reason' => $exception->getMessage(),
                'total_backups' => 0,
                'latest' => null,
                'latest_uploaded' => null,
                'running' => null,
                'items' => [],
            ];
        }

        $allItems = $this->allBackups(false);
        $totalBackups = count($allItems);
        $items = $allItems;

        if ($limit !== null && $limit > 0) {
            $items = array_slice($allItems, max(0, $offset), $limit);
        }

        if ($withVerification) {
            foreach ($items as &$manifest) {
                $directory = (string) ($manifest['_dir'] ?? '');
                if ($directory === '') {
                    continue;
                }

                $manifest['verification'] = $this->verifyBackupManifest($manifest, $directory);
                $manifest['is_valid'] = $this->backupVerificationIsValid($manifest, $manifest['verification']);
            }
            unset($manifest);
        }

        $latest = $items[0] ?? null;
        $latestUploaded = null;

        foreach ($allItems as $item) {
            if (($item['cloud_uploaded'] ?? false) === true) {
                $latestUploaded = $item;
                break;
            }
        }

        return [
            'backup_root' => $backupRoot,
            'available' => true,
            'unavailable_reason' => '',
            'total_backups' => $totalBackups,
            'latest' => $latest,
            'latest_uploaded' => $latestUploaded,
            'running' => $this->readRunLock($backupRoot),
            'items' => $items,
        ];
    }

    public function markUploaded(?string $backupId = null): array
    {
        $backup = $this->backupById($backupId);
        if ($backup === null) {
            throw new RuntimeException('Nenhum backup encontrado para marcar como enviado.');
        }

        $backup['cloud_uploaded'] = true;
        $backup['cloud_uploaded_at'] = date('c');
        $this->writeManifest((string) $backup['_dir'], $backup);
        $this->logOperation('backup_registro_nuvem', (string) ($backup['profile'] ?? 'local'), 'nuvem', (string) ($backup['backup_id'] ?? ''), 'OK', 'Backup marcado como enviado para a nuvem.');

        return $backup;
    }

    public function markCloudUploaded(string $backupId, array $metadata = []): array
    {
        $backup = $this->backupById($backupId, false);
        if ($backup === null) {
            throw new RuntimeException('Nenhum backup encontrado para registrar envio em nuvem.');
        }

        $backup['cloud_uploaded'] = true;
        $backup['cloud_uploaded_at'] = date('c');
        $backup['cloud_provider'] = (string) ($metadata['provider'] ?? 'dropbox');
        $backup['cloud_destination'] = (string) ($metadata['destination'] ?? '');
        $backup['cloud_remote_id'] = (string) ($metadata['remote_id'] ?? '');
        $backup['cloud_uploaded_files'] = (array) ($metadata['uploaded_files'] ?? []);
        $backup['cloud_uploaded_files_count'] = (int) ($metadata['uploaded_files_count'] ?? count((array) ($metadata['uploaded_files'] ?? [])));
        $backup['cloud_uploaded_size_bytes'] = (int) ($metadata['uploaded_size_bytes'] ?? 0);

        $this->writeManifest((string) $backup['_dir'], $backup);
        $this->logOperation(
            'backup_envio_nuvem',
            (string) ($backup['profile'] ?? 'local'),
            'dropbox',
            (string) ($backup['backup_id'] ?? ''),
            'OK',
            'Backup enviado para a nuvem.',
            [
                'destination' => (string) ($metadata['destination'] ?? ''),
                'provider' => (string) ($metadata['provider'] ?? 'dropbox'),
            ]
        );

        return $backup;
    }

    public function markCloudDeleted(string $backupId, array $metadata = []): array
    {
        $backup = $this->backupById($backupId, false);
        if ($backup === null) {
            throw new RuntimeException('Nenhum backup encontrado para registrar exclusao da nuvem.');
        }

        $backup['cloud_uploaded'] = false;
        $backup['cloud_deleted'] = true;
        $backup['cloud_deleted_at'] = date('c');
        $backup['cloud_deleted_provider'] = (string) ($metadata['provider'] ?? 'dropbox');
        $backup['cloud_deleted_destination'] = (string) ($metadata['destination'] ?? ($backup['cloud_destination'] ?? ''));
        $backup['cloud_delete_metadata'] = is_array($metadata['dropbox_metadata'] ?? null) ? $metadata['dropbox_metadata'] : [];

        $this->writeManifest((string) $backup['_dir'], $backup);
        $this->logOperation(
            'backup_exclusao_nuvem',
            (string) ($backup['profile'] ?? 'local'),
            'dropbox',
            (string) ($backup['backup_id'] ?? ''),
            'OK',
            'Backup removido da nuvem.',
            [
                'destination' => (string) ($metadata['destination'] ?? ''),
                'provider' => (string) ($metadata['provider'] ?? 'dropbox'),
            ]
        );

        return $backup;
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteLocalBackup(string $backupId, string $confirmation): array
    {
        $backupId = trim($backupId);
        if ($backupId === '' || strtolower($backupId) === 'latest') {
            throw new RuntimeException('Informe o ID exato do backup que sera excluido da pasta local.');
        }

        if (!hash_equals($backupId, trim($confirmation))) {
            throw new RuntimeException('Confirmacao invalida. Digite o ID exato do backup para excluir da pasta local.');
        }

        $backup = $this->backupById($backupId, false);
        if ($backup === null) {
            throw new RuntimeException('Backup nao encontrado para exclusao local.');
        }

        $directory = (string) ($backup['_dir'] ?? '');
        $this->assertBackupDirectoryCanBeDeleted($directory, $backupId);
        $profile = (string) ($backup['profile'] ?? 'local');
        $this->removeDirectory($directory);
        if (is_dir($directory)) {
            throw new RuntimeException('Nao foi possivel remover completamente a pasta local do backup.');
        }

        $this->logOperation(
            'backup_exclusao_local',
            $profile,
            $profile,
            $backupId,
            'OK',
            'Backup removido da pasta local.',
            ['directory' => $directory]
        );

        return [
            'backup_id' => $backupId,
            'profile' => $profile,
            'directory' => $directory,
            'deleted_at' => date('c'),
        ];
    }

    public function verify(?string $backupId, ?string $progressId = null): array
    {
        $this->allowLongRunningProcess();
        $this->writeProgress($progressId, [
            'status' => 'running',
            'title' => self::PROGRESS_TITLE,
            'stage' => 'Localizando backup',
            'message' => 'Preparando a verificacao do backup informado.',
            'percent' => 10,
            'updated_at' => date('c'),
        ]);
        $requestedBackupId = trim((string) $backupId);
        $backup = $this->backupById($backupId, false);
        if ($backup === null) {
            throw new RuntimeException('Nenhum backup encontrado para verificar.');
        }

        $resolvedBackupId = (string) ($backup['backup_id'] ?? ($requestedBackupId !== '' ? $requestedBackupId : 'ultimo disponivel'));

        $this->updateProgress($progressId, 'Verificando banco', sprintf('Conferindo o manifesto e o arquivo SQL do backup %s.', $resolvedBackupId), 36);
        $backup['database_verification'] = $this->verifyEntry((array) ($backup['database'] ?? []), (string) $backup['_dir']);
        $hasUploads = $this->backupIncludesUploads($backup);
        $this->updateProgress($progressId, $hasUploads ? 'Verificando uploads' : 'Uploads ignorados', $hasUploads ? 'Conferindo uploads.zip e seus checksums.' : 'Este backup foi gerado sem uploads.zip.', 58);
        $backup['uploads_verification'] = $hasUploads
            ? $this->verifyEntry((array) ($backup['uploads'] ?? []), (string) $backup['_dir'])
            : $this->skippedVerification('Uploads nao incluidos neste backup.');
        $this->updateProgress($progressId, 'Verificando sistema', 'Conferindo system-files.zip e seu checksum.', 78);
        $backup['system_files_verification'] = $this->verifyEntry((array) ($backup['system_files'] ?? []), (string) $backup['_dir']);
        $backup['is_valid'] = $this->backupVerificationIsValid($backup, [
            'database' => $backup['database_verification'],
            'uploads' => $backup['uploads_verification'],
            'system_files' => $backup['system_files_verification'],
        ]);
        $this->logOperation('backup_verificacao', (string) ($backup['profile'] ?? 'local'), (string) ($backup['profile'] ?? 'local'), (string) ($backup['backup_id'] ?? ''), ($backup['is_valid'] ?? false) ? 'OK' : 'FAIL', 'Verificacao de backup executada.');

        $this->writeProgress($progressId, [
                'status' => 'completed',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Verificacao concluida',
                'message' => sprintf('Backup %s verificado com status %s.', $resolvedBackupId, ($backup['is_valid'] ?? false) ? 'valido' : 'invalido'),
                'percent' => 100,
                'updated_at' => date('c'),
            ]);

        return $backup;
    }

    public function restore(?string $backupId, string $targetProfile = 'local', string $scope = 'all', bool $force = false, ?string $progressId = null): array
    {
        $this->allowLongRunningProcess();
        $this->writeProgress($progressId, [
            'status' => 'running',
            'title' => self::PROGRESS_TITLE,
            'stage' => 'Validando restore',
            'message' => 'Preparando a restauracao completa do ambiente selecionado.',
            'percent' => 6,
            'updated_at' => date('c'),
        ]);
        if (!$force) {
            throw new RuntimeException('Restore exige confirmacao explicita. Use o comando com --force.');
        }

        $requestedBackupId = trim((string) $backupId);
        $backup = $this->backupById($backupId, false);
        if ($backup === null) {
            throw new RuntimeException('Nenhum backup encontrado para restaurar.');
        }
        $resolvedBackupId = (string) ($backup['backup_id'] ?? ($requestedBackupId !== '' ? $requestedBackupId : 'ultimo disponivel'));

        $sourceProfile = strtolower(trim((string) ($backup['profile'] ?? '')));
        $targetProfile = strtolower(trim($targetProfile));
        if ($sourceProfile === '' || $sourceProfile !== $targetProfile) {
            throw new RuntimeException(sprintf(
                'Backup nao pertence ao ambiente selecionado. Origem: %s, Destino: %s.',
                $sourceProfile !== '' ? $sourceProfile : 'desconhecida',
                $targetProfile
            ));
        }

        $profile = $this->profile($targetProfile);
        $scope = strtolower(trim($scope));
        if (!in_array($scope, ['all', 'database', 'uploads', 'system_files'], true)) {
            throw new RuntimeException('Escopo de restore invalido. Use all, database, uploads ou system_files.');
        }

        $restored = [];
        try {
            if ($scope === 'all' || $scope === 'database') {
                $databaseFile = (string) (($backup['database']['name'] ?? '') ?: 'database.sql');
                $this->updateProgress($progressId, 'Restaurando banco', sprintf('Aplicando database.sql no ambiente %s.', (string) ($profile['label'] ?? $targetProfile)), 34);
                $this->restoreDatabase((array) ($profile['database'] ?? []), (string) $backup['_dir'] . DIRECTORY_SEPARATOR . $databaseFile);
                $restored[] = 'database';
            }

            if (($scope === 'all' || $scope === 'uploads') && $this->backupIncludesUploads($backup)) {
                $uploadsFile = (string) (($backup['uploads']['name'] ?? '') ?: 'uploads.zip');
                $zipPath = (string) $backup['_dir'] . DIRECTORY_SEPARATOR . $uploadsFile;
                $this->updateProgress($progressId, 'Extraindo uploads', 'Descompactando uploads.zip do backup selecionado.', 62);
                $tmpDirectory = $this->extractArchive($zipPath);
                try {
                    $this->updateProgress($progressId, 'Restaurando uploads', 'Sincronizando os uploads do backup para o ambiente de destino.', 82);
                    $this->restoreUploads((array) ($profile['uploads'] ?? []), $tmpDirectory);
                } finally {
                    $this->removeDirectory($tmpDirectory);
                }
                $restored[] = 'uploads';
            } elseif ($scope === 'uploads') {
                throw new RuntimeException('Este backup foi gerado sem uploads.zip.');
            }

            if ($scope === 'all' || $scope === 'system_files') {
                $systemFilesFile = (string) (($backup['system_files']['name'] ?? '') ?: '');
                if ($systemFilesFile === '') {
                    throw new RuntimeException('Este backup nao possui system-files.zip para restore completo.');
                }

                $zipPath = (string) $backup['_dir'] . DIRECTORY_SEPARATOR . $systemFilesFile;
                $this->updateProgress($progressId, 'Extraindo sistema', 'Descompactando system-files.zip do backup selecionado.', 88);
                $tmpDirectory = $this->extractArchive($zipPath);
                try {
                    $this->updateProgress($progressId, 'Restaurando sistema', 'Sincronizando arquivos do sistema do backup para o ambiente de destino.', 94);
                    $this->restoreSystemFiles((array) ($profile['system_files'] ?? []), $tmpDirectory);
                } finally {
                    $this->removeDirectory($tmpDirectory);
                }
                $restored[] = 'system_files';
            }

            $result = [
                'backup_id' => $backup['backup_id'] ?? null,
                'source_profile' => $sourceProfile,
                'target_profile' => $targetProfile,
                'scope' => $scope,
                'restored' => $restored,
                'restored_at' => date('c'),
            ];

            $this->logOperation('restore_dados', (string) ($backup['profile'] ?? 'local'), $targetProfile, (string) ($backup['backup_id'] ?? ''), 'OK', 'Restore de dados executado.', [
                'scope' => $scope,
            ]);

            $this->writeProgress($progressId, [
                'status' => 'completed',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Restore concluido',
                'message' => sprintf('Backup %s restaurado com sucesso no ambiente %s.', $resolvedBackupId, (string) ($profile['label'] ?? $targetProfile)),
                'percent' => 100,
                'updated_at' => date('c'),
            ]);

            return $result;
        } catch (\Throwable $exception) {
            $this->logOperation('restore_dados', (string) ($backup['profile'] ?? 'local'), $targetProfile, (string) ($backup['backup_id'] ?? ''), 'FAIL', $exception->getMessage(), [
                'scope' => $scope,
                'restored' => $restored,
            ]);
            $this->writeProgress($progressId, [
                'status' => 'error',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Falha no restore',
                'message' => $exception->getMessage(),
                'percent' => 100,
                'updated_at' => date('c'),
            ]);
            throw $exception;
        }
    }

    public function findBackup(?string $backupId = null, bool $withVerification = false): ?array
    {
        return $this->backupById($backupId, $withVerification);
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
                'message' => 'Nenhuma rotina de backup em andamento.',
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
                'message' => 'Nao foi possivel ler o andamento atual do backup.',
                'percent' => 0,
            ];
        }

        return $decoded;
    }

    private function operationRoot(): string
    {
        $root = trim((string) ($this->config['backup_root'] ?? ''));
        if ($root !== '') {
            return $root;
        }

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'operations';
    }

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

    private function lockPath(string $backupRoot): string
    {
        return $backupRoot . DIRECTORY_SEPARATOR . '.backup-running.lock.json';
    }

    private function acquireRunLock(string $backupRoot, string $profileName, string $profileLabel): array
    {
        $lockPath = $this->lockPath($backupRoot);
        $handle = fopen($lockPath, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Nao foi possivel criar o lock de execucao do backup.');
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            $raw = stream_get_contents($handle);
            $running = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            fclose($handle);

            $message = 'Ja existe um backup em execucao.';
            if (is_array($running) && !empty($running['started_at'])) {
                $message .= ' Inicio: ' . (string) $running['started_at'];
            }

            throw new RuntimeException($message);
        }

        $payload = [
            'profile' => $profileName,
            'profile_label' => $profileLabel,
            'started_at' => date('c'),
            'pid' => function_exists('getmypid') ? getmypid() : null,
        ];

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($handle);

        return [
            'path' => $lockPath,
            'handle' => $handle,
        ];
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

    private function readRunLock(string $backupRoot): ?array
    {
        $lockPath = $this->lockPath($backupRoot);
        if (!is_file($lockPath)) {
            return null;
        }

        $payload = json_decode((string) file_get_contents($lockPath), true);
        if (!is_array($payload)) {
            return [
                'profile' => 'unknown',
                'profile_label' => 'Backup em execucao',
                'started_at' => null,
            ];
        }

        return [
            'profile' => (string) ($payload['profile'] ?? 'unknown'),
            'profile_label' => (string) ($payload['profile_label'] ?? 'Backup em execucao'),
            'started_at' => (string) ($payload['started_at'] ?? ''),
        ];
    }

    private function profile(string $profileName): array
    {
        $profiles = (array) ($this->config['profiles'] ?? []);
        if (!isset($profiles[$profileName]) || !is_array($profiles[$profileName])) {
            throw new RuntimeException('Perfil de backup nao encontrado: ' . $profileName);
        }

        return $profiles[$profileName];
    }

    private function backupRoot(): string
    {
        $root = (string) ($this->config['backup_root'] ?? '');
        if ($root === '') {
            throw new RuntimeException('BACKUP_ROOT nao configurado.');
        }

        if (!is_dir($root) && !@mkdir($root, 0777, true) && !is_dir($root)) {
            throw new RuntimeException('Nao foi possivel criar a pasta raiz de backup: ' . $root);
        }

        return $root;
    }

    private function profileBackupRoot(string $profileName): string
    {
        $baseRoot = $this->backupRoot();
        $directory = self::DATA_DIRECTORIES[$profileName] ?? null;
        if ($directory === null) {
            throw new RuntimeException('Perfil de backup nao suportado na estrutura numerada: ' . $profileName);
        }

        $root = $baseRoot . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . 'dados';
        if (!is_dir($root) && !@mkdir($root, 0777, true) && !is_dir($root)) {
            throw new RuntimeException('Nao foi possivel criar a pasta de dados do backup: ' . $root);
        }

        return $root;
    }

    private function buildBackupId(string $type, string $profileName): string
    {
        return sprintf(
            '%s-%s-%s',
            strtoupper(trim($type)),
            $this->profileToken($profileName),
            date('Ymd-His')
        );
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

    private function backupDatabase(array $databaseConfig, string $destinationPath): void
    {
        foreach (['host', 'port', 'database', 'username'] as $required) {
            if (trim((string) ($databaseConfig[$required] ?? '')) === '') {
                throw new RuntimeException('Configuracao de banco incompleta para backup: faltando ' . $required);
            }
        }

        $mysqldumpBinary = (string) ($this->config['mysqldump_binary'] ?? '');
        if ($mysqldumpBinary === '' || !is_file($mysqldumpBinary)) {
            throw new RuntimeException('mysqldump nao encontrado. Ajuste BACKUP_MYSQLDUMP_BINARY.');
        }

        $defaultsFile = $this->createMysqlDefaultsFile($databaseConfig);
        $command = sprintf(
            '%s --defaults-extra-file=%s --single-transaction --skip-lock-tables --routines --triggers --default-character-set=utf8mb4 --result-file=%s %s 2>&1',
            escapeshellarg($mysqldumpBinary),
            escapeshellarg($defaultsFile),
            escapeshellarg($destinationPath),
            escapeshellarg((string) $databaseConfig['database'])
        );

        exec($command, $output, $exitCode);
        @unlink($defaultsFile);

        if ($exitCode !== 0 || !is_file($destinationPath) || filesize($destinationPath) === 0) {
            throw new RuntimeException('Falha ao gerar o dump do banco. ' . trim(implode(PHP_EOL, $output)));
        }
    }

    private function restoreDatabase(array $databaseConfig, string $sourceSqlPath): void
    {
        foreach (['host', 'port', 'database', 'username'] as $required) {
            if (trim((string) ($databaseConfig[$required] ?? '')) === '') {
                throw new RuntimeException('Configuracao de banco incompleta para restore: faltando ' . $required);
            }
        }

        if (!is_file($sourceSqlPath)) {
            throw new RuntimeException('Arquivo SQL do backup nao encontrado: ' . $sourceSqlPath);
        }

        $mysqlBinary = (string) ($this->config['mysql_binary'] ?? '');
        if ($mysqlBinary === '' || !is_file($mysqlBinary)) {
            throw new RuntimeException('mysql.exe nao encontrado. Ajuste BACKUP_MYSQL_BINARY.');
        }

        $defaultsFile = $this->createMysqlDefaultsFile($databaseConfig);
        $sourceSqlForMysql = str_replace('\\', '/', $sourceSqlPath);
        $command = sprintf(
            '%s --defaults-extra-file=%s --default-character-set=utf8mb4 %s --execute=%s 2>&1',
            escapeshellarg($mysqlBinary),
            escapeshellarg($defaultsFile),
            escapeshellarg((string) $databaseConfig['database']),
            escapeshellarg('source ' . $sourceSqlForMysql)
        );

        exec($command, $output, $exitCode);
        @unlink($defaultsFile);

        if ($exitCode !== 0) {
            throw new RuntimeException('Falha ao restaurar o banco. ' . trim(implode(PHP_EOL, $output)));
        }
    }

    private function createMysqlDefaultsFile(array $databaseConfig): string
    {
        $defaultsFile = tempnam(sys_get_temp_dir(), 'en-db-');
        if ($defaultsFile === false) {
            throw new RuntimeException('Nao foi possivel criar o arquivo temporario do mysql.');
        }

        $defaultsContent = implode(PHP_EOL, [
            '[client]',
            'host="' . $this->escapeMysqlOptionValue((string) $databaseConfig['host']) . '"',
            'port="' . $this->escapeMysqlOptionValue((string) $databaseConfig['port']) . '"',
            'user="' . $this->escapeMysqlOptionValue((string) $databaseConfig['username']) . '"',
            'password="' . $this->escapeMysqlOptionValue((string) ($databaseConfig['password'] ?? '')) . '"',
            '',
        ]);

        file_put_contents($defaultsFile, $defaultsContent);
        return $defaultsFile;
    }

    private function escapeMysqlOptionValue(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    private function materializeUploads(array $uploadsConfig): string
    {
        $mode = strtolower((string) ($uploadsConfig['mode'] ?? 'local'));
        if ($mode === 'local') {
            $sourcePath = (string) ($uploadsConfig['path'] ?? '');
            if ($sourcePath === '' || !is_dir($sourcePath)) {
                throw new RuntimeException('Pasta local de uploads nao encontrada: ' . $sourcePath);
            }

            return $sourcePath;
        }

        if ($mode !== 'ftp') {
            throw new RuntimeException('Modo de uploads nao suportado: ' . $mode);
        }

        foreach (['host', 'port', 'username', 'password', 'root'] as $required) {
            if (trim((string) ($uploadsConfig[$required] ?? '')) === '') {
                throw new RuntimeException('Configuracao FTP incompleta: faltando ' . $required);
            }
        }

        $tmpRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'en-uploads-' . bin2hex(random_bytes(6));
        if (!mkdir($tmpRoot, 0777, true) && !is_dir($tmpRoot)) {
            throw new RuntimeException('Nao foi possivel criar a pasta temporaria do backup FTP.');
        }

        $connection = @ftp_connect((string) $uploadsConfig['host'], (int) $uploadsConfig['port'], 30);
        if ($connection === false) {
            throw new RuntimeException('Nao foi possivel conectar ao FTP configurado.');
        }

        try {
            if (!@ftp_login($connection, (string) $uploadsConfig['username'], (string) $uploadsConfig['password'])) {
                throw new RuntimeException('Falha de login no FTP.');
            }

            ftp_pasv($connection, (bool) ($uploadsConfig['passive'] ?? true));
            $this->downloadFtpTree($connection, rtrim((string) $uploadsConfig['root'], '/'), $tmpRoot);
            $this->ensureDirectoryHasContent($tmpRoot);
        } finally {
            ftp_close($connection);
        }

        return $tmpRoot;
    }

    private function materializeSystemFiles(array $systemConfig, array $uploadsConfig): string
    {
        $mode = strtolower((string) ($systemConfig['mode'] ?? 'local'));
        $tmpRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'en-system-files-' . bin2hex(random_bytes(6));
        if (!mkdir($tmpRoot, 0777, true) && !is_dir($tmpRoot)) {
            throw new RuntimeException('Nao foi possivel criar a pasta temporaria do backup do sistema.');
        }

        if ($mode === 'local') {
            $sourceRoot = (string) ($systemConfig['root'] ?? '');
            if ($sourceRoot === '' || !is_dir($sourceRoot)) {
                throw new RuntimeException('Raiz local do sistema nao encontrada: ' . $sourceRoot);
            }

            $this->copyFilteredSystemTree($sourceRoot, $tmpRoot, $this->normalizeSystemExcludes($systemConfig['exclude'] ?? []));
            $this->ensureDirectoryHasContent($tmpRoot);

            return $tmpRoot;
        }

        if ($mode !== 'ftp') {
            throw new RuntimeException('Modo de backup de arquivos do sistema nao suportado: ' . $mode);
        }

        foreach (['host', 'port', 'username', 'password', 'root'] as $required) {
            if (trim((string) ($systemConfig[$required] ?? '')) === '') {
                throw new RuntimeException('Configuracao FTP incompleta para backup do sistema: faltando ' . $required);
            }
        }

        $connection = @ftp_connect((string) $systemConfig['host'], (int) $systemConfig['port'], 30);
        if ($connection === false) {
            throw new RuntimeException('Nao foi possivel conectar ao FTP configurado para arquivos do sistema.');
        }

        try {
            if (!@ftp_login($connection, (string) $systemConfig['username'], (string) $systemConfig['password'])) {
                throw new RuntimeException('Falha de login no FTP para backup do sistema.');
            }

            ftp_pasv($connection, (bool) ($systemConfig['passive'] ?? true));
            $root = $this->resolveFtpSystemRoot($connection, (string) $systemConfig['root']);
            $uploadsRoot = rtrim((string) ($uploadsConfig['root'] ?? ''), '/');
            $uploadsRelative = $this->relativeRemotePath($root, $uploadsRoot);
            $this->downloadFtpSystemTree($connection, $root, $tmpRoot, '', $uploadsRelative, $this->normalizeSystemExcludes($systemConfig['exclude'] ?? []));
            $this->ensureDirectoryHasContent($tmpRoot);
        } finally {
            ftp_close($connection);
        }

        return $tmpRoot;
    }

    private function restoreUploads(array $uploadsConfig, string $sourceDirectory): void
    {
        $mode = strtolower((string) ($uploadsConfig['mode'] ?? 'local'));
        if ($mode === 'local') {
            $destinationPath = (string) ($uploadsConfig['path'] ?? '');
            if ($destinationPath === '') {
                throw new RuntimeException('Destino local de uploads nao configurado.');
            }

            $this->mirrorLocalDirectory($sourceDirectory, $destinationPath);
            return;
        }

        if ($mode !== 'ftp') {
            throw new RuntimeException('Modo de restore de uploads nao suportado: ' . $mode);
        }

        foreach (['host', 'port', 'username', 'password', 'root'] as $required) {
            if (trim((string) ($uploadsConfig[$required] ?? '')) === '') {
                throw new RuntimeException('Configuracao FTP incompleta para restore: faltando ' . $required);
            }
        }

        $connection = @ftp_connect((string) $uploadsConfig['host'], (int) $uploadsConfig['port'], 30);
        if ($connection === false) {
            throw new RuntimeException('Nao foi possivel conectar ao FTP configurado para restore.');
        }

        try {
            if (!@ftp_login($connection, (string) $uploadsConfig['username'], (string) $uploadsConfig['password'])) {
                throw new RuntimeException('Falha de login no FTP para restore.');
            }

            ftp_pasv($connection, (bool) ($uploadsConfig['passive'] ?? true));
            $this->uploadFtpTree($connection, $sourceDirectory, rtrim((string) $uploadsConfig['root'], '/'));
        } finally {
            ftp_close($connection);
        }
    }

    private function restoreSystemFiles(array $systemConfig, string $sourceDirectory): void
    {
        $mode = strtolower((string) ($systemConfig['mode'] ?? 'local'));
        if ($mode === 'local') {
            $destinationRoot = (string) ($systemConfig['root'] ?? '');
            if ($destinationRoot === '' || !is_dir($destinationRoot)) {
                throw new RuntimeException('Destino local do sistema nao configurado.');
            }

            $this->copySystemFilesToDestination($sourceDirectory, $destinationRoot);
            return;
        }

        if ($mode !== 'ftp') {
            throw new RuntimeException('Modo de restore de arquivos do sistema nao suportado: ' . $mode);
        }

        foreach (['host', 'port', 'username', 'password', 'root'] as $required) {
            if (trim((string) ($systemConfig[$required] ?? '')) === '') {
                throw new RuntimeException('Configuracao FTP incompleta para restore do sistema: faltando ' . $required);
            }
        }

        $connection = @ftp_connect((string) $systemConfig['host'], (int) $systemConfig['port'], 30);
        if ($connection === false) {
            throw new RuntimeException('Nao foi possivel conectar ao FTP configurado para restore do sistema.');
        }

        try {
            if (!@ftp_login($connection, (string) $systemConfig['username'], (string) $systemConfig['password'])) {
                throw new RuntimeException('Falha de login no FTP para restore do sistema.');
            }

            ftp_pasv($connection, (bool) ($systemConfig['passive'] ?? true));
            $root = $this->resolveFtpSystemRoot($connection, (string) $systemConfig['root']);
            $this->uploadFtpSystemTree($connection, $sourceDirectory, $root);
        } finally {
            ftp_close($connection);
        }
    }

    private function compressDirectory(string $sourceDirectory, string $destinationZipPath): void
    {
        $this->ensureDirectoryHasContent($sourceDirectory);

        $sourceLiteral = $this->toPowerShellLiteral($sourceDirectory);
        $destinationLiteral = $this->toPowerShellLiteral($destinationZipPath);
        $command = sprintf(
            "C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe -NoProfile -Command \"Add-Type -AssemblyName 'System.IO.Compression.FileSystem'; if (Test-Path -LiteralPath %s) { Remove-Item -LiteralPath %s -Force }; [System.IO.Compression.ZipFile]::CreateFromDirectory(%s, %s, [System.IO.Compression.CompressionLevel]::Optimal, \$false)\"",
            $destinationLiteral,
            $destinationLiteral,
            $sourceLiteral,
            $destinationLiteral
        );

        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || !is_file($destinationZipPath) || filesize($destinationZipPath) === 0) {
            throw new RuntimeException('Falha ao compactar uploads em zip.');
        }
    }

    private function extractArchive(string $zipPath): string
    {
        if (!is_file($zipPath)) {
            throw new RuntimeException('Arquivo zip nao encontrado no backup: ' . $zipPath);
        }

        $destination = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'en-restore-' . bin2hex(random_bytes(6));
        if (!mkdir($destination, 0777, true) && !is_dir($destination)) {
            throw new RuntimeException('Nao foi possivel criar pasta temporaria para restore.');
        }

        $zipLiteral = $this->toPowerShellLiteral($zipPath);
        $destinationLiteral = $this->toPowerShellLiteral($destination);
        $command = sprintf(
            "C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe -NoProfile -Command \"Add-Type -AssemblyName 'System.IO.Compression.FileSystem'; [System.IO.Compression.ZipFile]::ExtractToDirectory(%s, %s)\"",
            $zipLiteral,
            $destinationLiteral
        );

        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            $this->removeDirectory($destination);
            throw new RuntimeException('Falha ao extrair o arquivo de uploads.');
        }

        return $destination;
    }

    private function ensureDirectoryHasContent(string $directory): void
    {
        if (!is_dir($directory)) {
            throw new RuntimeException('Diretorio nao encontrado: ' . $directory);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                return;
            }
        }

        $placeholder = $directory . DIRECTORY_SEPARATOR . '.backup-empty';
        file_put_contents($placeholder, 'empty uploads backup');
    }

    private function downloadFtpTree($connection, string $remoteDirectory, string $localDirectory): void
    {
        if (!is_dir($localDirectory) && !mkdir($localDirectory, 0777, true) && !is_dir($localDirectory)) {
            throw new RuntimeException('Nao foi possivel criar pasta local temporaria: ' . $localDirectory);
        }

        $items = function_exists('ftp_mlsd') ? @ftp_mlsd($connection, $remoteDirectory) : false;
        if (is_array($items)) {
            foreach ($items as $item) {
                $name = (string) ($item['name'] ?? '');
                if ($name === '' || $name === '.' || $name === '..') {
                    continue;
                }

                $remotePath = $remoteDirectory . '/' . $name;
                $localPath = $localDirectory . DIRECTORY_SEPARATOR . $name;
                $type = strtolower((string) ($item['type'] ?? ''));

                if ($type === 'dir') {
                    $this->downloadFtpTree($connection, $remotePath, $localPath);
                    continue;
                }

                if (!@ftp_get($connection, $localPath, $remotePath, FTP_BINARY)) {
                    throw new RuntimeException('Falha ao baixar arquivo FTP: ' . $remotePath);
                }
            }
            return;
        }

        $rawItems = @ftp_rawlist($connection, $remoteDirectory);
        if ($rawItems === false) {
            throw new RuntimeException('Nao foi possivel listar o diretorio FTP: ' . $remoteDirectory);
        }

        foreach ($rawItems as $rawItem) {
            $parts = preg_split('/\s+/', trim((string) $rawItem), 9);
            if (!is_array($parts) || count($parts) < 9) {
                continue;
            }

            $name = $parts[8];
            if ($name === '.' || $name === '..') {
                continue;
            }

            $remotePath = $remoteDirectory . '/' . $name;
            $localPath = $localDirectory . DIRECTORY_SEPARATOR . $name;
            $isDirectory = str_starts_with((string) $parts[0], 'd');

            if ($isDirectory) {
                $this->downloadFtpTree($connection, $remotePath, $localPath);
                continue;
            }

            if (!@ftp_get($connection, $localPath, $remotePath, FTP_BINARY)) {
                throw new RuntimeException('Falha ao baixar arquivo FTP: ' . $remotePath);
            }
        }
    }

    private function resolveFtpSystemRoot($connection, string $configuredRoot): string
    {
        $root = rtrim(trim(str_replace('\\', '/', $configuredRoot)), '/');
        if ($root === '') {
            throw new RuntimeException('Raiz FTP do sistema nao configurada.');
        }

        if (str_ends_with(strtolower($root), '/_app_core')) {
            return $root;
        }

        if (@ftp_size($connection, $root . '/_app_core/bootstrap.php') >= 0) {
            return $root . '/_app_core';
        }

        return $root;
    }

    private function downloadFtpSystemTree($connection, string $remoteDirectory, string $localDirectory, string $relativeBase = '', string $uploadsRelative = '', array $excludedPaths = []): void
    {
        if (!is_dir($localDirectory) && !mkdir($localDirectory, 0777, true) && !is_dir($localDirectory)) {
            throw new RuntimeException('Nao foi possivel criar pasta local temporaria do sistema: ' . $localDirectory);
        }

        $items = function_exists('ftp_mlsd') ? @ftp_mlsd($connection, $remoteDirectory) : false;
        if (is_array($items)) {
            foreach ($items as $item) {
                $name = (string) ($item['name'] ?? '');
                if ($name === '' || $name === '.' || $name === '..') {
                    continue;
                }

                $relative = ltrim($relativeBase . '/' . $name, '/');
                if (!$this->shouldIncludeSystemRelativePath($relative, $uploadsRelative, $excludedPaths)) {
                    continue;
                }

                $remotePath = $remoteDirectory . '/' . $name;
                $localPath = $localDirectory . DIRECTORY_SEPARATOR . $name;
                $type = strtolower((string) ($item['type'] ?? ''));

                if ($type === 'dir') {
                    $this->downloadFtpSystemTree($connection, $remotePath, $localPath, $relative, $uploadsRelative, $excludedPaths);
                    continue;
                }

                if (!@ftp_get($connection, $localPath, $remotePath, FTP_BINARY)) {
                    throw new RuntimeException('Falha ao baixar arquivo do sistema via FTP: ' . $remotePath);
                }
            }
            return;
        }

        $rawItems = @ftp_rawlist($connection, $remoteDirectory);
        if ($rawItems === false) {
            throw new RuntimeException('Nao foi possivel listar o diretorio FTP do sistema: ' . $remoteDirectory);
        }

        foreach ($rawItems as $rawItem) {
            $parts = preg_split('/\s+/', trim((string) $rawItem), 9);
            if (!is_array($parts) || count($parts) < 9) {
                continue;
            }

            $name = $parts[8];
            if ($name === '.' || $name === '..') {
                continue;
            }

            $relative = ltrim($relativeBase . '/' . $name, '/');
            if (!$this->shouldIncludeSystemRelativePath($relative, $uploadsRelative, $excludedPaths)) {
                continue;
            }

            $remotePath = $remoteDirectory . '/' . $name;
            $localPath = $localDirectory . DIRECTORY_SEPARATOR . $name;
            $isDirectory = str_starts_with((string) $parts[0], 'd');

            if ($isDirectory) {
                $this->downloadFtpSystemTree($connection, $remotePath, $localPath, $relative, $uploadsRelative, $excludedPaths);
                continue;
            }

            if (!@ftp_get($connection, $localPath, $remotePath, FTP_BINARY)) {
                throw new RuntimeException('Falha ao baixar arquivo do sistema via FTP: ' . $remotePath);
            }
        }
    }

    private function uploadFtpTree($connection, string $localDirectory, string $remoteDirectory): void
    {
        if (!is_dir($localDirectory)) {
            throw new RuntimeException('Diretorio local de restore nao encontrado: ' . $localDirectory);
        }

        $this->ensureFtpDirectory($connection, $remoteDirectory);
        $items = scandir($localDirectory);
        if ($items === false) {
            throw new RuntimeException('Nao foi possivel ler a pasta local de restore.');
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $localPath = $localDirectory . DIRECTORY_SEPARATOR . $item;
            if ($item === '.backup-empty') {
                continue;
            }
            $remotePath = $remoteDirectory . '/' . $item;

            if (is_dir($localPath)) {
                $this->uploadFtpTree($connection, $localPath, $remotePath);
                continue;
            }

            if (!@ftp_put($connection, $remotePath, $localPath, FTP_BINARY)) {
                throw new RuntimeException('Falha ao enviar arquivo FTP: ' . $remotePath);
            }
        }
    }

    private function uploadFtpSystemTree($connection, string $localDirectory, string $remoteDirectory, string $relativeBase = ''): void
    {
        if (!is_dir($localDirectory)) {
            throw new RuntimeException('Diretorio local de restore do sistema nao encontrado: ' . $localDirectory);
        }

        $this->ensureFtpDirectory($connection, $remoteDirectory);
        $items = scandir($localDirectory);
        if ($items === false) {
            throw new RuntimeException('Nao foi possivel ler a pasta local de restore do sistema.');
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $relative = ltrim($relativeBase . '/' . $item, '/');
            if (!$this->shouldIncludeSystemRelativePath($relative)) {
                continue;
            }

            $localPath = $localDirectory . DIRECTORY_SEPARATOR . $item;
            if ($item === '.backup-empty') {
                continue;
            }
            $remotePath = $remoteDirectory . '/' . $item;

            if (is_dir($localPath)) {
                $this->uploadFtpSystemTree($connection, $localPath, $remotePath, $relative);
                continue;
            }

            if (!@ftp_put($connection, $remotePath, $localPath, FTP_BINARY)) {
                throw new RuntimeException('Falha ao enviar arquivo do sistema via FTP: ' . $remotePath);
            }
        }
    }

    private function ensureFtpDirectory($connection, string $remoteDirectory): void
    {
        $parts = array_values(array_filter(explode('/', trim($remoteDirectory, '/')), static fn ($part) => $part !== ''));
        $current = '';
        foreach ($parts as $part) {
            $current .= '/' . $part;
            @ftp_mkdir($connection, $current);
        }
    }

    private function mirrorLocalDirectory(string $sourceDirectory, string $destinationDirectory): void
    {
        if (is_dir($destinationDirectory)) {
            $this->removeDirectory($destinationDirectory);
        }

        if (!mkdir($destinationDirectory, 0777, true) && !is_dir($destinationDirectory)) {
            throw new RuntimeException('Nao foi possivel criar o destino local do restore: ' . $destinationDirectory);
        }

        $items = scandir($sourceDirectory);
        if ($items === false) {
            throw new RuntimeException('Nao foi possivel ler o diretorio do backup para restore.');
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourcePath = $sourceDirectory . DIRECTORY_SEPARATOR . $item;
            $destinationPath = $destinationDirectory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($sourcePath)) {
                $this->mirrorLocalDirectory($sourcePath, $destinationPath);
                continue;
            }

            if (!copy($sourcePath, $destinationPath)) {
                throw new RuntimeException('Falha ao copiar arquivo no restore local: ' . $sourcePath);
            }
        }
    }

    private function copyFilteredSystemTree(string $sourceRoot, string $destinationRoot, array $excludedPaths = []): void
    {
        $normalizedSourceRoot = rtrim(str_replace('\\', '/', realpath($sourceRoot) ?: $sourceRoot), '/');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceRoot, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $sourcePath = $item->getPathname();
            $normalizedSourcePath = str_replace('\\', '/', $sourcePath);
            $relative = ltrim(substr($normalizedSourcePath, strlen($normalizedSourceRoot)), '/');
            if (!$this->shouldIncludeSystemRelativePath($relative, '', $excludedPaths)) {
                continue;
            }

            $destinationPath = $destinationRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $destinationDir = dirname($destinationPath);
            if (!is_dir($destinationDir) && !mkdir($destinationDir, 0777, true) && !is_dir($destinationDir)) {
                throw new RuntimeException('Nao foi possivel criar a pasta temporaria do arquivo do sistema: ' . $destinationDir);
            }

            if (!copy($sourcePath, $destinationPath)) {
                throw new RuntimeException('Falha ao copiar arquivo do sistema: ' . $relative);
            }
        }
    }

    private function copySystemFilesToDestination(string $sourceRoot, string $destinationRoot): void
    {
        $normalizedSourceRoot = rtrim(str_replace('\\', '/', realpath($sourceRoot) ?: $sourceRoot), '/');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceRoot, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $sourcePath = $item->getPathname();
            $normalizedSourcePath = str_replace('\\', '/', $sourcePath);
            $relative = ltrim(substr($normalizedSourcePath, strlen($normalizedSourceRoot)), '/');
            if (!$this->shouldIncludeSystemRelativePath($relative)) {
                continue;
            }

            $destinationPath = $destinationRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $destinationDir = dirname($destinationPath);
            if (!is_dir($destinationDir) && !mkdir($destinationDir, 0777, true) && !is_dir($destinationDir)) {
                throw new RuntimeException('Nao foi possivel criar pasta de destino para restore do sistema: ' . $relative);
            }

            if (!copy($sourcePath, $destinationPath)) {
                throw new RuntimeException('Falha ao restaurar arquivo do sistema: ' . $relative);
            }
        }
    }

    private function shouldIncludeSystemRelativePath(string $relativePath, string $uploadsRelative = '', array $excludedPaths = []): bool
    {
        $normalized = strtolower(ltrim(str_replace('\\', '/', trim($relativePath)), '/'));
        if ($normalized === '' || str_contains($normalized, '..')) {
            return false;
        }

        $segments = array_values(array_filter(explode('/', $normalized), static fn (string $segment): bool => $segment !== ''));
        foreach (self::SYSTEM_FILE_EXCLUDED_SEGMENTS as $segment) {
            if (in_array($segment, $segments, true)) {
                return false;
            }
        }

        $uploads = strtolower(ltrim(str_replace('\\', '/', trim($uploadsRelative)), '/'));
        if ($uploads !== '' && ($normalized === $uploads || str_starts_with($normalized, $uploads . '/'))) {
            return false;
        }

        if (in_array($normalized, self::SYSTEM_FILE_EXCLUDED_EXACT, true)) {
            return false;
        }

        foreach (self::SYSTEM_FILE_EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return false;
            }
        }

        foreach ($excludedPaths as $excludedPath) {
            if ($normalized === $excludedPath || str_starts_with($normalized, $excludedPath . '/')) {
                return false;
            }
        }

        return true;
    }

    private function normalizeSystemExcludes(mixed $excludedPaths): array
    {
        if (is_string($excludedPaths)) {
            $excludedPaths = explode(',', $excludedPaths);
        }

        if (!is_array($excludedPaths)) {
            return [];
        }

        $normalized = [];
        foreach ($excludedPaths as $excludedPath) {
            $path = strtolower(trim(str_replace('\\', '/', (string) $excludedPath), '/'));
            if ($path === '' || str_contains($path, '..')) {
                continue;
            }

            $normalized[] = $path;
        }

        return array_values(array_unique($normalized));
    }

    private function relativeRemotePath(string $root, string $candidate): string
    {
        $root = trim(str_replace('\\', '/', $root), '/');
        $candidate = trim(str_replace('\\', '/', $candidate), '/');
        if ($root === '' || $candidate === '' || !str_starts_with($candidate, $root . '/')) {
            return '';
        }

        return substr($candidate, strlen($root) + 1);
    }

    private function countFiles(string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $count++;
            }
        }

        return $count;
    }

    private function fileDetails(string $path, string $name): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Arquivo nao encontrado no backup: ' . $path);
        }

        return [
            'name' => $name,
            'path' => $path,
            'size_bytes' => filesize($path),
            'sha256' => hash_file('sha256', $path),
        ];
    }

    private function writeManifest(string $backupDir, array $manifest): void
    {
        unset($manifest['_dir'], $manifest['database_verification'], $manifest['uploads_verification'], $manifest['system_files_verification'], $manifest['verification'], $manifest['is_valid']);
        file_put_contents(
            $backupDir . DIRECTORY_SEPARATOR . 'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function allBackups(bool $withVerification = true): array
    {
        $directories = [];
        foreach ($this->backupSearchRoots() as $root) {
            foreach ((glob($root . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: []) as $directory) {
                $directories[] = $directory;
            }
        }

        $items = [];
        foreach ($directories as $directory) {
            $manifestPath = $directory . DIRECTORY_SEPARATOR . 'manifest.json';
            if (!is_file($manifestPath)) {
                continue;
            }

            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (!is_array($manifest)) {
                continue;
            }
            $manifest['_dir'] = $directory;

            $items[] = $manifest;
        }

        usort($items, static function (array $left, array $right): int {
            $leftTime = strtotime((string) ($left['created_at'] ?? '')) ?: 0;
            $rightTime = strtotime((string) ($right['created_at'] ?? '')) ?: 0;

            return $rightTime <=> $leftTime;
        });

        if ($withVerification) {
            foreach ($items as &$manifest) {
                $directory = (string) ($manifest['_dir'] ?? '');
                if ($directory === '') {
                    continue;
                }

                $manifest['verification'] = $this->verifyBackupManifest($manifest, $directory);
                $manifest['is_valid'] = $this->backupVerificationIsValid($manifest, $manifest['verification']);
            }
            unset($manifest);
        }

        return $items;
    }

    private function backupById(?string $backupId, bool $withVerification = true): ?array
    {
        $items = $this->allBackups($withVerification);
        if ($backupId === null || trim($backupId) === '' || strtolower(trim($backupId)) === 'latest') {
            return $items[0] ?? null;
        }

        foreach ($items as $item) {
            if ((string) ($item['backup_id'] ?? '') === $backupId) {
                return $item;
            }
        }

        return null;
    }

    private function toPowerShellLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private function verifyEntry(array $entry, string $backupDir): array
    {
        $name = (string) ($entry['name'] ?? '');
        if ($name === '') {
            return ['valid' => false, 'message' => 'Entrada ausente no manifesto.'];
        }

        $path = $backupDir . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path)) {
            return ['valid' => false, 'message' => 'Arquivo nao encontrado: ' . $name];
        }

        $size = filesize($path);
        if ($size === false || $size <= 0) {
            return ['valid' => false, 'message' => 'Arquivo vazio: ' . $name];
        }

        $hash = hash_file('sha256', $path);
        if ($hash === false || $hash !== (string) ($entry['sha256'] ?? '')) {
            return ['valid' => false, 'message' => 'Checksum diferente para: ' . $name];
        }

        return ['valid' => true, 'message' => 'OK'];
    }

    private function verifyBackupManifest(array $manifest, string $directory): array
    {
        return [
            'database' => $this->verifyEntry((array) ($manifest['database'] ?? []), $directory),
            'uploads' => $this->backupIncludesUploads($manifest)
                ? $this->verifyEntry((array) ($manifest['uploads'] ?? []), $directory)
                : $this->skippedVerification('Uploads nao incluidos neste backup.'),
            'system_files' => $this->verifyEntry((array) ($manifest['system_files'] ?? []), $directory),
        ];
    }

    private function backupVerificationIsValid(array $manifest, array $verification): bool
    {
        $uploadsValid = $this->backupIncludesUploads($manifest)
            ? (bool) ($verification['uploads']['valid'] ?? false)
            : true;

        return (bool) ($verification['database']['valid'] ?? false)
            && $uploadsValid
            && (
                !array_key_exists('system_files', $manifest)
                || (bool) ($verification['system_files']['valid'] ?? false)
            );
    }

    private function backupIncludesUploads(array $manifest): bool
    {
        if (array_key_exists('includes_uploads', $manifest)) {
            return (bool) $manifest['includes_uploads'];
        }

        $uploads = $manifest['uploads'] ?? null;
        if (is_array($uploads) && array_key_exists('included', $uploads)) {
            return (bool) $uploads['included'];
        }

        return is_array($uploads) && trim((string) ($uploads['name'] ?? '')) !== '';
    }

    private function skippedVerification(string $message): array
    {
        return ['valid' => true, 'skipped' => true, 'message' => $message];
    }

    private function applyRetention(string $profileName): void
    {
        $keep = max(1, (int) ($this->config['retention'] ?? 14));
        $items = $this->backupsBySearchRoot($this->profileBackupRoot($profileName), false);
        $itemsToRemove = array_slice($items, $keep);

        foreach ($itemsToRemove as $item) {
            $directory = (string) ($item['_dir'] ?? '');
            if ($directory !== '') {
                $this->removeDirectory($directory);
            }
        }
    }

    private function assertBackupDirectoryCanBeDeleted(string $directory, string $backupId): void
    {
        if ($directory === '' || !is_dir($directory)) {
            throw new RuntimeException('Pasta local do backup nao encontrada.');
        }

        $realDirectory = realpath($directory);
        if ($realDirectory === false) {
            throw new RuntimeException('Nao foi possivel resolver a pasta local do backup.');
        }

        $allowed = false;
        foreach ($this->backupSearchRoots() as $root) {
            $realRoot = realpath($root);
            if ($realRoot === false) {
                continue;
            }

            $normalizedRoot = rtrim(str_replace('\\', '/', $realRoot), '/');
            $normalizedDirectory = rtrim(str_replace('\\', '/', $realDirectory), '/');
            if (str_starts_with($normalizedDirectory . '/', $normalizedRoot . '/')) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            throw new RuntimeException('Exclusao bloqueada: pasta fora da raiz de backups.');
        }

        if (basename($realDirectory) !== $backupId || !preg_match('/^B[A-Z]-[A-Z]+-\d{8}-\d{6}$/', $backupId)) {
            throw new RuntimeException('Exclusao bloqueada: ID ou pasta fora do padrao esperado.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function backupSearchRoots(): array
    {
        $roots = [];
        $baseRoot = $this->backupRoot();

        foreach (self::DATA_DIRECTORIES as $directory) {
            $candidate = $baseRoot . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . 'dados';
            if (is_dir($candidate)) {
                $roots[] = $candidate;
            }
        }

        $roots[] = $baseRoot;

        return array_values(array_unique($roots));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function backupsBySearchRoot(string $root, bool $withVerification = true): array
    {
        $directories = glob($root . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
        $items = [];

        foreach ($directories as $directory) {
            $manifestPath = $directory . DIRECTORY_SEPARATOR . 'manifest.json';
            if (!is_file($manifestPath)) {
                continue;
            }

            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (!is_array($manifest)) {
                continue;
            }

            $manifest['_dir'] = $directory;
            if ($withVerification) {
                $manifest['verification'] = $this->verifyBackupManifest($manifest, $directory);
                $manifest['is_valid'] = $this->backupVerificationIsValid($manifest, $manifest['verification']);
            }

            $items[] = $manifest;
        }

        usort($items, static function (array $left, array $right): int {
            $leftTime = strtotime((string) ($left['created_at'] ?? '')) ?: 0;
            $rightTime = strtotime((string) ($right['created_at'] ?? '')) ?: 0;

            return $rightTime <=> $leftTime;
        });

        return $items;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            @unlink($path);
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
        $baseDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'backup-progress';
        return $baseDirectory . DIRECTORY_SEPARATOR . $progressId . '.json';
    }
}
