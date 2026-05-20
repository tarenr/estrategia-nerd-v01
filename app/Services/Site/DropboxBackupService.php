<?php

declare(strict_types=1);

namespace App\Services\Site;

use RuntimeException;
use Scripts\Backup\BackupManager;
use Scripts\ContentSync\ContentSyncManager;

final class DropboxBackupService
{
    private const AUTHORIZE_URL = 'https://www.dropbox.com/oauth2/authorize';
    private const TOKEN_URL = 'https://api.dropboxapi.com/oauth2/token';
    private const CURRENT_ACCOUNT_URL = 'https://api.dropboxapi.com/2/users/get_current_account';
    private const SPACE_USAGE_URL = 'https://api.dropboxapi.com/2/users/get_space_usage';
    private const CREATE_FOLDER_URL = 'https://api.dropboxapi.com/2/files/create_folder_v2';
    private const DELETE_URL = 'https://api.dropboxapi.com/2/files/delete_v2';
    private const UPLOAD_URL = 'https://content.dropboxapi.com/2/files/upload';
    private const UPLOAD_SESSION_START_URL = 'https://content.dropboxapi.com/2/files/upload_session/start';
    private const UPLOAD_SESSION_APPEND_URL = 'https://content.dropboxapi.com/2/files/upload_session/append_v2';
    private const UPLOAD_SESSION_FINISH_URL = 'https://content.dropboxapi.com/2/files/upload_session/finish';
    private const PROGRESS_TITLE = 'Enviando backup para Dropbox';

    public function __construct(private array $config)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getPanelData(BackupManager $manager, int $page = 1, int $perPage = 5): array
    {
        $state = $this->readState();
        $connected = $this->isConnectedState($state);
        $spaceUsage = $connected ? $this->spaceUsage($state) : [
            'available' => false,
            'message' => 'Conecte a conta do Dropbox para consultar o espaco disponivel.',
        ];
        $page = max(1, $page);
        $perPage = max(5, min(50, $perPage));
        $recentBackups = (array) ($manager->status(false, null, 0)['items'] ?? []);
        $allUploads = [];

        foreach ($recentBackups as $item) {
            if (($item['cloud_uploaded'] ?? false) !== true) {
                continue;
            }

            $uploadedBytes = $this->uploadedBackupBytes($item);
            $allUploads[] = [
                'backup_id' => (string) ($item['backup_id'] ?? ''),
                'profile_label' => $this->profileLabel((string) ($item['profile'] ?? '')),
                'profile' => (string) ($item['profile'] ?? ''),
                'cloud_uploaded_at' => (string) ($item['cloud_uploaded_at'] ?? ''),
                'cloud_destination' => (string) ($item['cloud_destination'] ?? ''),
                'cloud_provider' => (string) ($item['cloud_provider'] ?? 'Dropbox'),
                'cloud_uploaded_size_bytes' => $uploadedBytes,
                'cloud_uploaded_size' => $uploadedBytes > 0 ? $this->formatBytes($uploadedBytes) : '-',
                'cloud_uploaded_files_count' => (int) ($item['cloud_uploaded_files_count'] ?? 0),
            ];
        }

        $totalUploads = count($allUploads);
        $pages = max(1, (int) ceil($totalUploads / max(1, $perPage)));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $recentUploads = array_slice($allUploads, $offset, $perPage);

        return [
            'backup_cloud' => [
                'provider' => 'Dropbox',
                'configured' => $this->isConfigured(),
                'connected' => $connected,
                'account_id' => (string) ($state['account_id'] ?? ''),
                'account_email' => (string) ($state['account_email'] ?? ''),
                'account_name' => (string) ($state['account_name'] ?? ''),
                'connected_at' => (string) ($state['connected_at'] ?? ''),
                'auto_upload_enabled' => (bool) ($state['auto_upload_enabled'] ?? false),
                'remote_root' => $this->remoteRoot(),
                'redirect_uri' => $this->redirectUri(),
                'last_upload' => is_array($state['last_upload'] ?? null) ? $state['last_upload'] : null,
                'recommended_scopes' => implode(', ', (array) ($this->config['dropbox']['scopes'] ?? [])),
                'space_usage' => $spaceUsage,
            ],
            'backup_cloud_recent_uploads' => $recentUploads,
            'backup_cloud_pagination' => [
                'total' => $totalUploads,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => $pages,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getEditorialPanelData(ContentSyncManager $manager): array
    {
        $state = $this->readState();
        $status = $manager->status();
        $items = [];
        $totalUploaded = 0;

        foreach ((array) ($status['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $bytes = $this->contentPackageBytes($item);
            $uploaded = (bool) ($item['cloud_uploaded'] ?? false);
            if ($uploaded) {
                $totalUploaded += $bytes;
            }

            $items[] = [
                'package_id' => (string) ($item['package_id'] ?? ''),
                'source_profile' => (string) ($item['source_profile'] ?? ''),
                'source_profile_label' => (string) ($item['source_profile_label'] ?? $this->profileLabel((string) ($item['source_profile'] ?? ''))),
                'created_at' => (string) ($item['created_at'] ?? ''),
                'is_valid' => (bool) ($item['is_valid'] ?? false),
                'cloud_uploaded' => $uploaded,
                'cloud_uploaded_at' => (string) ($item['cloud_uploaded_at'] ?? ''),
                'cloud_destination' => (string) ($item['cloud_destination'] ?? ''),
                'cloud_uploaded_size' => $bytes > 0 ? $this->formatBytes($bytes) : '-',
                'stats' => is_array($item['stats'] ?? null) ? $item['stats'] : [],
                'uploads' => is_array($item['uploads'] ?? null) ? $item['uploads'] : [],
            ];
        }

        return [
            'items' => $items,
            'pending' => array_values(array_filter($items, static fn (array $item): bool => ($item['cloud_uploaded'] ?? false) !== true)),
            'uploaded' => array_values(array_filter($items, static fn (array $item): bool => ($item['cloud_uploaded'] ?? false) === true)),
            'total' => count($items),
            'total_uploaded_size' => $this->formatBytes($totalUploaded),
            'remote_root' => $this->editorialRemoteRoot(),
            'auto_upload_enabled' => (bool) ($state['editorial_auto_upload_enabled'] ?? false),
            'last_upload' => is_array($state['last_editorial_upload'] ?? null) ? $state['last_editorial_upload'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadLatestEditorial(ContentSyncManager $manager, ?string $progressId = null): array
    {
        return $this->uploadEditorialPackage($manager, null, $progressId);
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadEditorialPackage(ContentSyncManager $manager, ?string $packageId = null, ?string $progressId = null): array
    {
        $this->allowLongRunningUpload();
        $progress = $this->normalizeProgressId($progressId);

        try {
            $state = $this->requireConnectedState();
            $package = $this->findContentPackage($manager, $packageId);
            if ($package === null) {
                throw new RuntimeException('Nenhum pacote editorial encontrado para enviar ao Dropbox.');
            }

            $packageCode = (string) ($package['package_id'] ?? '');
            if ($packageCode === '') {
                throw new RuntimeException('Pacote editorial sem identificador.');
            }

            if ((bool) ($package['cloud_uploaded'] ?? false)) {
                throw new RuntimeException(sprintf('Pacote editorial %s ja foi enviado para a nuvem.', $packageCode));
            }

            $directory = (string) ($package['_dir'] ?? '');
            if ($directory === '' || !is_dir($directory)) {
                throw new RuntimeException('Pasta do pacote editorial nao encontrada para envio.');
            }

            $accessToken = $this->freshAccessToken($state);
            $profile = strtolower((string) ($package['source_profile'] ?? 'local'));
            $remoteFolder = $this->normalizeDropboxPath($this->editorialRemoteRoot() . '/' . $profile . '/' . $packageCode);

            $this->ensureRemoteFolder($accessToken, $this->normalizeDropboxPath($this->editorialRemoteRoot()));
            $this->ensureRemoteFolder($accessToken, $this->normalizeDropboxPath($this->editorialRemoteRoot() . '/' . $profile));
            $this->ensureRemoteFolder($accessToken, $remoteFolder);

            $entries = ['manifest.json'];
            foreach (glob($directory . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
                if (is_file($file)) {
                    $entries[] = 'data/' . basename($file);
                }
            }
            if (is_file($directory . DIRECTORY_SEPARATOR . 'uploads.zip')) {
                $entries[] = 'uploads.zip';
            }

            $uploadedFiles = [];
            $uploadedSize = 0;
            foreach ($entries as $index => $entry) {
                $localPath = $directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry);
                if (!is_file($localPath)) {
                    throw new RuntimeException('Arquivo do pacote editorial ausente para envio: ' . $entry);
                }

                $remotePath = $remoteFolder . '/' . $entry;
                $metadata = $this->uploadFile($accessToken, $localPath, $remotePath, $progress, $entry);
                $uploadedSize += (int) filesize($localPath);
                $uploadedFiles[] = [
                    'name' => $entry,
                    'path' => $remotePath,
                    'id' => (string) ($metadata['id'] ?? ''),
                    'rev' => (string) ($metadata['rev'] ?? ''),
                ];
            }

            $result = [
                'provider' => 'dropbox',
                'package_id' => $packageCode,
                'destination' => $remoteFolder,
                'uploaded_files' => $uploadedFiles,
                'uploaded_files_count' => count($uploadedFiles),
                'uploaded_size_bytes' => $uploadedSize,
            ];

            $this->markContentPackageCloudUploaded($package, $result);

            $state['last_editorial_upload'] = [
                'package_id' => $packageCode,
                'profile' => $profile,
                'destination' => $remoteFolder,
                'uploaded_at' => date('c'),
                'files' => count($uploadedFiles),
            ];
            $this->writeState($state);

            return $result;
        } catch (\Throwable $exception) {
            $this->writeProgress($progress, [
                'status' => 'error',
                'title' => 'Enviando pacote editorial para Dropbox',
                'stage' => 'Falha no envio',
                'message' => $exception->getMessage(),
                'percent' => 100,
                'updated_at' => date('c'),
            ]);
            throw $exception;
        }
    }

    public function authorizationUrl(string $state): string
    {
        $this->assertConfigured();

        $query = http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'token_access_type' => 'offline',
            'scope' => implode(' ', (array) ($this->config['dropbox']['scopes'] ?? [])),
            'state' => $state,
        ]);

        return self::AUTHORIZE_URL . '?' . $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function completeAuthorization(string $code): array
    {
        $this->assertConfigured();

        $tokenPayload = $this->requestToken([
            'code' => trim($code),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri(),
        ]);

        $accessToken = (string) ($tokenPayload['access_token'] ?? '');
        $refreshToken = (string) ($tokenPayload['refresh_token'] ?? '');
        if ($accessToken === '' || $refreshToken === '') {
            throw new RuntimeException('Dropbox nao retornou access_token e refresh_token validos.');
        }

        $account = $this->rpc(self::CURRENT_ACCOUNT_URL, $accessToken, []);
        $state = $this->readState();
        $state['account_id'] = (string) ($account['account_id'] ?? '');
        $state['account_email'] = (string) ($account['email'] ?? '');
        $state['account_name'] = (string) (($account['name']['display_name'] ?? '') ?: ($account['name']['familiar_name'] ?? 'Dropbox'));
        $state['access_token'] = $accessToken;
        $state['refresh_token'] = $refreshToken;
        $state['token_expires_at'] = time() + max(60, (int) ($tokenPayload['expires_in'] ?? 0));
        $state['connected_at'] = date('c');
        $state['provider'] = 'dropbox';
        $state['remote_root'] = $this->remoteRoot();
        $state['auto_upload_enabled'] = (bool) ($state['auto_upload_enabled'] ?? false);

        $this->writeState($state);

        return $state;
    }

    public function disconnect(): void
    {
        $path = $this->statePath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function setAutoUpload(bool $enabled): array
    {
        $state = $this->requireConnectedState();
        $state['auto_upload_enabled'] = $enabled;
        $this->writeState($state);

        return $state;
    }

    /**
     * @return array<string, mixed>
     */
    public function setEditorialAutoUpload(bool $enabled): array
    {
        $state = $this->requireConnectedState();
        $state['editorial_auto_upload_enabled'] = $enabled;
        $this->writeState($state);

        return $state;
    }

    public function autoUploadEnabled(): bool
    {
        $state = $this->readState();
        return $this->isConnectedState($state) && (bool) ($state['auto_upload_enabled'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadLatest(BackupManager $manager, ?string $progressId = null): array
    {
        return $this->uploadBackup($manager, null, $progressId);
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadBackup(BackupManager $manager, ?string $backupId = null, ?string $progressId = null): array
    {
        $this->allowLongRunningUpload();
        $progress = $this->normalizeProgressId($progressId);

        if ($progress !== null) {
            $this->writeProgress($progress, [
                'status' => 'running',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Validando conexao',
                'message' => 'Validando a conta conectada e preparando o envio do backup.',
                'percent' => 6,
                'updated_at' => date('c'),
            ]);
        }

        try {
            $state = $this->requireConnectedState();
            $backup = $manager->findBackup($backupId, false);
            if (!is_array($backup)) {
                throw new RuntimeException('Nenhum backup encontrado para enviar ao Dropbox.');
            }

            $backupCode = (string) ($backup['backup_id'] ?? '');
            if (($backup['cloud_uploaded'] ?? false) === true) {
                throw new RuntimeException(sprintf('Backup %s ja foi enviado para a nuvem.', $backupCode));
            }

            $this->updateProgress($progress, 'Localizando backup', sprintf('Backup %s localizado e pronto para envio.', $backupCode), 12);

            $directory = (string) ($backup['_dir'] ?? '');
            if ($directory === '' || !is_dir($directory)) {
                throw new RuntimeException('Pasta do backup nao encontrada para envio.');
            }

            $accessToken = $this->freshAccessToken($state);
            $profile = strtolower((string) ($backup['profile'] ?? 'local'));
            $profileLabel = $this->profileLabel($profile);
            $remoteFolder = $this->normalizeDropboxPath($this->remoteRoot() . '/' . $profile . '/' . $backupCode);

            $this->updateProgress($progress, 'Preparando destino', sprintf('Criando pastas remotas para o ambiente %s.', $profileLabel), 18);
            $this->ensureRemoteFolder($accessToken, $this->normalizeDropboxPath($this->remoteRoot()));
            $this->ensureRemoteFolder($accessToken, $this->normalizeDropboxPath($this->remoteRoot() . '/' . $profile));
            $this->ensureRemoteFolder($accessToken, $remoteFolder);

            $entries = [
                'manifest.json',
                (string) (($backup['database']['name'] ?? '') ?: 'database.sql'),
            ];
            $uploadsEntry = (string) (($backup['uploads']['name'] ?? '') ?: '');
            if ($uploadsEntry !== '') {
                $entries[] = $uploadsEntry;
            }
            $systemFilesEntry = (string) (($backup['system_files']['name'] ?? '') ?: '');
            if ($systemFilesEntry !== '') {
                $entries[] = $systemFilesEntry;
            }

            $uploadedFiles = [];
            $uploadedSize = 0;
            $basePercents = [
                'manifest.json' => 28,
                'database.sql' => 42,
                'uploads.zip' => 58,
                'system-files.zip' => 76,
            ];

            foreach ($entries as $entry) {
                $localPath = $directory . DIRECTORY_SEPARATOR . $entry;
                if (!is_file($localPath)) {
                    throw new RuntimeException('Arquivo do backup ausente para envio: ' . $entry);
                }

                $remotePath = $remoteFolder . '/' . $entry;
                $message = sprintf('Enviando %s para %s no Dropbox.', $entry, $remoteFolder);
                $this->updateProgress($progress, 'Enviando arquivo', $message, $basePercents[$entry] ?? 55);
                $metadata = $this->uploadFile($accessToken, $localPath, $remotePath, $progress, $entry);
                $uploadedSize += (int) filesize($localPath);
                $uploadedFiles[] = [
                    'name' => $entry,
                    'path' => $remotePath,
                    'id' => (string) ($metadata['id'] ?? ''),
                    'rev' => (string) ($metadata['rev'] ?? ''),
                ];
            }

            $result = [
                'provider' => 'dropbox',
                'destination' => $remoteFolder,
                'remote_id' => (string) ($uploadedFiles[0]['id'] ?? ''),
                'uploaded_files' => $uploadedFiles,
                'uploaded_files_count' => count($uploadedFiles),
                'uploaded_size_bytes' => $uploadedSize,
            ];

            $this->updateProgress($progress, 'Gravando manifesto local', 'Atualizando o manifesto local para marcar o envio na nuvem.', 94);
            $manager->markCloudUploaded($backupCode, $result);

            $this->updateProgress($progress, 'Atualizando historico', 'Registrando o ultimo envio da conta conectada.', 98);
            $state['last_upload'] = [
                'backup_id' => $backupCode,
                'profile' => $profile,
                'destination' => $remoteFolder,
                'uploaded_at' => date('c'),
                'files' => count($uploadedFiles),
            ];
            $this->writeState($state);

            $this->writeProgress($progress, [
                'status' => 'completed',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Envio concluido',
                'message' => sprintf('Backup %s enviado com sucesso para %s.', $backupCode, $remoteFolder),
                'percent' => 100,
                'updated_at' => date('c'),
            ]);

            return $result;
        } catch (\Throwable $exception) {
            $this->writeProgress($progress, [
                'status' => 'error',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Falha no envio',
                'message' => $exception->getMessage(),
                'percent' => 100,
                'updated_at' => date('c'),
            ]);
            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteBackup(BackupManager $manager, string $backupId, string $confirmation): array
    {
        $backupId = trim($backupId);
        if ($backupId === '' || strtolower($backupId) === 'latest') {
            throw new RuntimeException('Informe o ID exato do backup que sera removido do Dropbox.');
        }

        if (!hash_equals($backupId, trim($confirmation))) {
            throw new RuntimeException('Confirmacao invalida. Digite o ID exato do backup para remover do Dropbox.');
        }

        $state = $this->requireConnectedState();
        $backup = $manager->findBackup($backupId, false);
        if (!is_array($backup)) {
            throw new RuntimeException('Backup nao encontrado no historico local.');
        }

        if (($backup['cloud_uploaded'] ?? false) !== true) {
            throw new RuntimeException('Este backup nao esta marcado como enviado ao Dropbox.');
        }

        $remotePath = $this->deleteTargetPath($backup);
        $accessToken = $this->freshAccessToken($state);
        $metadata = $this->rpc(self::DELETE_URL, $accessToken, [
            'path' => $remotePath,
        ]);

        $manager->markCloudDeleted($backupId, [
            'provider' => 'dropbox',
            'destination' => $remotePath,
            'dropbox_metadata' => $metadata,
        ]);

        $state['last_delete'] = [
            'backup_id' => $backupId,
            'destination' => $remotePath,
            'deleted_at' => date('c'),
        ];
        $this->writeState($state);

        return [
            'provider' => 'dropbox',
            'backup_id' => $backupId,
            'destination' => $remotePath,
            'metadata' => $metadata,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteEditorialPackage(ContentSyncManager $manager, string $packageId, string $confirmation): array
    {
        $packageId = trim($packageId);
        if ($packageId === '' || strtolower($packageId) === 'latest') {
            throw new RuntimeException('Informe o ID exato do pacote editorial que sera removido do Dropbox.');
        }

        if (!hash_equals($packageId, trim($confirmation))) {
            throw new RuntimeException('Confirmacao invalida. Digite o ID exato do pacote editorial para remover do Dropbox.');
        }

        $state = $this->requireConnectedState();
        $package = $this->findContentPackage($manager, $packageId);
        if (!is_array($package)) {
            throw new RuntimeException('Pacote editorial nao encontrado no historico local.');
        }

        if (($package['cloud_uploaded'] ?? false) !== true) {
            throw new RuntimeException('Este pacote editorial nao esta marcado como enviado ao Dropbox.');
        }

        $remotePath = (string) ($package['cloud_destination'] ?? '');
        if ($remotePath === '') {
            $profile = strtolower((string) ($package['source_profile'] ?? 'local'));
            $remotePath = $this->normalizeDropboxPath($this->editorialRemoteRoot() . '/' . $profile . '/' . $packageId);
        }

        $accessToken = $this->freshAccessToken($state);
        $metadata = $this->rpc(self::DELETE_URL, $accessToken, [
            'path' => $remotePath,
        ]);

        $manager->markCloudDeleted($packageId, [
            'provider' => 'dropbox',
            'destination' => $remotePath,
            'dropbox_metadata' => $metadata,
        ]);

        $state['last_editorial_delete'] = [
            'package_id' => $packageId,
            'destination' => $remotePath,
            'deleted_at' => date('c'),
        ];
        $this->writeState($state);

        return [
            'provider' => 'dropbox',
            'package_id' => $packageId,
            'destination' => $remotePath,
            'metadata' => $metadata,
        ];
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
                'message' => 'Nenhum envio em andamento.',
                'percent' => 0,
            ];
        }

        $path = $this->progressPath($progress);
        if (!is_file($path)) {
            return [
                'status' => 'idle',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Aguardando',
                'message' => 'Nenhum progresso encontrado para este envio.',
                'percent' => 0,
            ];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [
                'status' => 'error',
                'title' => self::PROGRESS_TITLE,
                'stage' => 'Erro de leitura',
                'message' => 'Nao foi possivel ler o progresso atual do envio.',
                'percent' => 0,
            ];
        }

        return $decoded;
    }

    private function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Configure BACKUP_DROPBOX_APP_KEY e BACKUP_DROPBOX_APP_SECRET antes de conectar o Dropbox.');
        }
    }

    private function clientId(): string
    {
        return trim((string) ($this->config['dropbox']['client_id'] ?? ''));
    }

    private function clientSecret(): string
    {
        return trim((string) ($this->config['dropbox']['client_secret'] ?? ''));
    }

    private function redirectUri(): string
    {
        return trim((string) ($this->config['dropbox']['redirect_uri'] ?? ''));
    }

    private function remoteRoot(): string
    {
        return $this->normalizeDropboxPath((string) ($this->config['dropbox']['remote_root'] ?? '/Estrategia Nerd/backups-ambiente'));
    }

    private function editorialRemoteRoot(): string
    {
        return $this->normalizeDropboxPath((string) ($this->config['dropbox']['editorial_remote_root'] ?? '/Estrategia Nerd/backups-editoriais'));
    }

    private function statePath(): string
    {
        return (string) ($this->config['state_path'] ?? base_path('storage/app/backup-cloud/dropbox-connection.json'));
    }

    private function chunkSize(): int
    {
        $configured = (int) ($this->config['chunk_size'] ?? (8 * 1024 * 1024));
        return max(4 * 1024 * 1024, $configured);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireConnectedState(): array
    {
        $state = $this->readState();
        if (!$this->isConnectedState($state)) {
            throw new RuntimeException('Conecte a conta do Dropbox antes de enviar backups.');
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function isConnectedState(array $state): bool
    {
        return trim((string) ($state['refresh_token'] ?? '')) !== '' && trim((string) ($state['account_id'] ?? '')) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    private function readState(): array
    {
        $path = $this->statePath();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function writeState(array $state): void
    {
        $path = $this->statePath();
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Nao foi possivel criar a pasta de configuracao do Dropbox.');
        }

        file_put_contents(
            $path,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * @param array<string, mixed> $state
     */
    private function freshAccessToken(array &$state): string
    {
        $accessToken = trim((string) ($state['access_token'] ?? ''));
        $expiresAt = (int) ($state['token_expires_at'] ?? 0);
        if ($accessToken !== '' && $expiresAt > (time() + 60)) {
            return $accessToken;
        }

        $tokenPayload = $this->requestToken([
            'refresh_token' => (string) ($state['refresh_token'] ?? ''),
            'grant_type' => 'refresh_token',
        ]);

        $accessToken = (string) ($tokenPayload['access_token'] ?? '');
        if ($accessToken === '') {
            throw new RuntimeException('Dropbox nao retornou um novo access_token.');
        }

        $state['access_token'] = $accessToken;
        $state['token_expires_at'] = time() + max(60, (int) ($tokenPayload['expires_in'] ?? 0));
        $this->writeState($state);

        return $accessToken;
    }

    /**
     * @param array<string, string> $form
     * @return array<string, mixed>
     */
    private function requestToken(array $form): array
    {
        $response = $this->curl(
            self::TOKEN_URL,
            [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($form),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                ],
                CURLOPT_USERPWD => $this->clientId() . ':' . $this->clientSecret(),
            ]
        );

        return $this->decodeJsonResponse($response['body'], $response['status'], 'Dropbox token');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function rpc(string $url, string $accessToken, array $payload): array
    {
        $body = $payload === []
            ? 'null'
            : (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response = $this->curl(
            $url,
            [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json',
                ],
            ]
        );

        return $this->decodeJsonResponse($response['body'], $response['status'], 'Dropbox RPC');
    }

    private function ensureRemoteFolder(string $accessToken, string $path): void
    {
        if ($path === '' || $path === '/') {
            return;
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $segment): bool => $segment !== ''));
        $current = '';

        foreach ($segments as $segment) {
            $current .= '/' . $segment;

            try {
                $this->rpc(self::CREATE_FOLDER_URL, $accessToken, [
                    'path' => $current,
                    'autorename' => false,
                ]);
            } catch (RuntimeException $exception) {
                if (!str_contains(strtolower($exception->getMessage()), 'conflict')) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $backup
     */
    private function deleteTargetPath(array $backup): string
    {
        $backupId = (string) ($backup['backup_id'] ?? '');
        $profile = strtolower((string) ($backup['profile'] ?? 'local'));
        $destination = $this->normalizeDropboxPath((string) ($backup['cloud_destination'] ?? ''));
        if ($destination === '/' || $destination === '') {
            throw new RuntimeException('Destino remoto do backup nao esta registrado no manifesto local.');
        }

        $expected = $this->normalizeDropboxPath($this->remoteRoot() . '/' . $profile . '/' . $backupId);
        $root = $this->normalizeDropboxPath($this->remoteRoot());
        if ($destination !== $expected || !str_starts_with($destination . '/', $root . '/')) {
            throw new RuntimeException('Destino remoto bloqueado por seguranca: ' . $destination);
        }

        if (!preg_match('/^B[A-Z]-[A-Z]+-\d{8}-\d{6}$/', $backupId)) {
            throw new RuntimeException('ID de backup fora do padrao esperado para exclusao.');
        }

        return $destination;
    }

    /**
     * @param array<string, mixed> $backup
     */
    private function uploadedBackupBytes(array $backup): int
    {
        $cloudBytes = (int) ($backup['cloud_uploaded_size_bytes'] ?? 0);
        if ($cloudBytes > 0) {
            return $cloudBytes;
        }

        $total = (int) ($backup['database']['size_bytes'] ?? 0)
            + (int) ($backup['uploads']['size_bytes'] ?? 0)
            + (int) ($backup['system_files']['size_bytes'] ?? 0);

        return max(0, $total);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findContentPackage(ContentSyncManager $manager, ?string $packageId): ?array
    {
        $items = (array) ($manager->status()['items'] ?? []);
        $requested = strtolower(trim((string) $packageId));
        if ($requested === '' || $requested === 'latest') {
            return is_array($items[0] ?? null) ? $items[0] : null;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            if ((string) ($item['package_id'] ?? '') === $packageId) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $package
     */
    private function contentPackageBytes(array $package): int
    {
        $directory = (string) ($package['_dir'] ?? '');
        if ($directory === '' || !is_dir($directory)) {
            return 0;
        }

        $total = 0;
        foreach (['manifest.json', 'uploads.zip'] as $entry) {
            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path)) {
                $total += (int) filesize($path);
            }
        }

        foreach (glob($directory . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . '*.json') ?: [] as $path) {
            if (is_file($path)) {
                $total += (int) filesize($path);
            }
        }

        return $total;
    }

    /**
     * @param array<string, mixed> $package
     * @param array<string, mixed> $result
     */
    private function markContentPackageCloudUploaded(array $package, array $result): void
    {
        $directory = (string) ($package['_dir'] ?? '');
        if ($directory === '') {
            return;
        }

        $manifestPath = $directory . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!is_file($manifestPath)) {
            return;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            return;
        }

        $manifest['cloud_uploaded'] = true;
        $manifest['cloud_provider'] = 'Dropbox';
        $manifest['cloud_uploaded_at'] = date('c');
        $manifest['cloud_destination'] = (string) ($result['destination'] ?? '');
        $manifest['cloud_uploaded_size_bytes'] = (int) ($result['uploaded_size_bytes'] ?? 0);
        $manifest['cloud_uploaded_files_count'] = (int) ($result['uploaded_files_count'] ?? 0);
        $manifest['cloud_uploaded_files'] = array_values((array) ($result['uploaded_files'] ?? []));

        file_put_contents(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function spaceUsage(array $state): array
    {
        try {
            $accessState = $state;
            $accessToken = $this->freshAccessToken($accessState);
            $payload = $this->rpc(self::SPACE_USAGE_URL, $accessToken, []);
            $usedBytes = max(0, (int) ($payload['used'] ?? 0));
            $allocation = is_array($payload['allocation'] ?? null) ? $payload['allocation'] : [];
            $allocatedBytes = $this->allocatedBytesFromAllocation($allocation);
            $freeBytes = $allocatedBytes > 0 ? max(0, $allocatedBytes - $usedBytes) : null;
            $percentUsed = $allocatedBytes > 0 ? min(100, round(($usedBytes / $allocatedBytes) * 100, 1)) : null;

            return [
                'available' => $allocatedBytes > 0,
                'used_bytes' => $usedBytes,
                'allocated_bytes' => $allocatedBytes,
                'free_bytes' => $freeBytes,
                'used' => $this->formatBytes($usedBytes),
                'allocated' => $allocatedBytes > 0 ? $this->formatBytes($allocatedBytes) : '-',
                'free' => $freeBytes !== null ? $this->formatBytes($freeBytes) : '-',
                'percent_used' => $percentUsed,
                'allocation_type' => (string) ($allocation['.tag'] ?? ''),
                'message' => $allocatedBytes > 0 ? '' : 'Dropbox nao retornou o limite total da conta.',
            ];
        } catch (\Throwable $exception) {
            return [
                'available' => false,
                'message' => 'Nao foi possivel consultar o espaco do Dropbox: ' . $exception->getMessage(),
            ];
        }
    }

    /**
     * @param array<string, mixed> $allocation
     */
    private function allocatedBytesFromAllocation(array $allocation): int
    {
        $allocated = $allocation['allocated'] ?? null;
        if (is_numeric($allocated)) {
            return max(0, (int) $allocated);
        }

        foreach ($allocation as $value) {
            if (is_array($value)) {
                $nested = $this->allocatedBytesFromAllocation($value);
                if ($nested > 0) {
                    return $nested;
                }
            }
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadFile(string $accessToken, string $localPath, string $remotePath, ?string $progressId = null, ?string $entryName = null): array
    {
        $size = filesize($localPath);
        if ($size === false || $size <= 0) {
            throw new RuntimeException('Arquivo vazio ou inacessivel para upload: ' . basename($localPath));
        }

        if ($size <= 150 * 1024 * 1024) {
            $label = $entryName ?? basename($localPath);
            $routinePercent = $label === 'manifest.json' ? 34 : 48;
            $this->updateProgress($progressId, 'Enviando arquivo', sprintf('Enviando %s em upload direto.', $label), $routinePercent);
            $metadata = $this->simpleUpload($accessToken, $localPath, $remotePath);
            $this->updateProgress($progressId, 'Arquivo enviado', sprintf('%s enviado ao Dropbox.', $label), $routinePercent);

            return $metadata;
        }

        return $this->sessionUpload($accessToken, $localPath, $remotePath, $size, $progressId, $entryName ?? basename($localPath));
    }

    /**
     * @return array<string, mixed>
     */
    private function simpleUpload(string $accessToken, string $localPath, string $remotePath): array
    {
        $body = (string) file_get_contents($localPath);
        $response = $this->curl(
            self::UPLOAD_URL,
            [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/octet-stream',
                    'Expect:',
                    'Dropbox-API-Arg: ' . json_encode([
                        'path' => $remotePath,
                        'mode' => 'add',
                        'autorename' => true,
                        'mute' => true,
                        'strict_conflict' => false,
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ],
            ]
        );

        return $this->decodeJsonResponse($response['body'], $response['status'], 'Dropbox upload');
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionUpload(string $accessToken, string $localPath, string $remotePath, int $size, ?string $progressId = null, string $entryName = 'uploads.zip'): array
    {
        $this->updateFileProgress($progressId, 'Iniciando upload grande', sprintf('Abrindo sessao de upload para %s.', $entryName), 58, $entryName, 0, $size);
        $startResponse = $this->curl(
            self::UPLOAD_SESSION_START_URL,
            [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => '',
                CURLOPT_TIMEOUT => 0,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/octet-stream',
                    'Expect:',
                    'Dropbox-API-Arg: ' . json_encode(['close' => false], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ],
            ]
        );
        $startPayload = $this->decodeJsonResponse($startResponse['body'], $startResponse['status'], 'Dropbox upload session start');
        $sessionId = (string) ($startPayload['session_id'] ?? '');
        if ($sessionId === '') {
            throw new RuntimeException('Dropbox nao retornou session_id para upload em partes.');
        }

        $handle = fopen($localPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Nao foi possivel abrir o arquivo para upload em partes.');
        }

        $offset = 0;
        $chunkSize = $this->chunkSize();

        try {
            while (($offset + $chunkSize) < $size) {
                $this->allowLongRunningUpload();
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false || $chunk === '') {
                    throw new RuntimeException('Falha ao ler um bloco do arquivo para upload.');
                }

                $nextOffset = $offset + strlen($chunk);
                $progressPercent = 58 + (int) round(($nextOffset / max(1, $size)) * 30);
                $this->curl(
                    self::UPLOAD_SESSION_APPEND_URL,
                    [
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => $chunk,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_HTTPHEADER => [
                            'Authorization: Bearer ' . $accessToken,
                            'Content-Type: application/octet-stream',
                            'Expect:',
                            'Dropbox-API-Arg: ' . json_encode([
                                'cursor' => [
                                    'session_id' => $sessionId,
                                    'offset' => $offset,
                                ],
                                'close' => false,
                            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                    false
                );

                $offset += strlen($chunk);
                $this->updateFileProgress(
                    $progressId,
                    'Enviando arquivo grande',
                    sprintf('Enviando %s: %.1f%% concluido (%s de %s).', $entryName, ($offset / max(1, $size)) * 100, $this->formatBytes($offset), $this->formatBytes($size)),
                    min(90, $progressPercent),
                    $entryName,
                    $offset,
                    $size
                );
            }

            $lastChunk = stream_get_contents($handle);
            if (!is_string($lastChunk)) {
                throw new RuntimeException('Falha ao ler o bloco final do arquivo para upload.');
            }

            $this->updateFileProgress(
                $progressId,
                'Finalizando arquivo grande',
                sprintf('Concluindo o envio de %s e fechando a sessao remota.', $entryName),
                92,
                $entryName,
                $size,
                $size
            );

            $finishResponse = $this->curl(
                self::UPLOAD_SESSION_FINISH_URL,
                [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $lastChunk,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $accessToken,
                        'Content-Type: application/octet-stream',
                        'Expect:',
                        'Dropbox-API-Arg: ' . json_encode([
                            'cursor' => [
                                'session_id' => $sessionId,
                                'offset' => $offset,
                            ],
                            'commit' => [
                                'path' => $remotePath,
                                'mode' => 'add',
                                'autorename' => true,
                                'mute' => true,
                                'strict_conflict' => false,
                            ],
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ],
                ]
            );
        } finally {
            fclose($handle);
        }

        return $this->decodeJsonResponse($finishResponse['body'], $finishResponse['status'], 'Dropbox upload session finish');
    }

    /**
     * @return array{status:int,body:string}
     */
    private function curl(string $url, array $options, bool $expectJson = true): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL nao esta disponivel para integrar com o Dropbox.');
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Nao foi possivel iniciar a conexao cURL com o Dropbox.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_CONNECTTIMEOUT => 20,
        ] + $options);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($body === false) {
            throw new RuntimeException('Falha de conexao com o Dropbox: ' . $error);
        }

        if ($status >= 400) {
            $message = $body;
            if ($expectJson) {
                $decoded = json_decode($body, true);
                if (is_array($decoded)) {
                    $summary = (string) ($decoded['error_summary'] ?? '');
                    $errorData = $decoded['error'] ?? null;
                    $message = trim($summary . ($errorData !== null ? ' ' . json_encode($errorData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''));
                }
            }

            throw new RuntimeException('Dropbox respondeu com erro HTTP ' . $status . ': ' . trim((string) $message));
        }

        return [
            'status' => $status,
            'body' => (string) $body,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonResponse(string $body, int $status, string $context): array
    {
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException($context . ' retornou uma resposta invalida (HTTP ' . $status . ').');
        }

        return $decoded;
    }

    private function normalizeDropboxPath(string $path): string
    {
        $trimmed = trim(str_replace('\\', '/', $path));
        if ($trimmed === '') {
            return '/';
        }

        $trimmed = '/' . trim($trimmed, '/');
        return preg_replace('#/+#', '/', $trimmed) ?: '/';
    }

    private function profileLabel(string $profile): string
    {
        return match (strtolower(trim($profile))) {
            'local' => 'Local',
            'stage' => 'Stage',
            'production' => 'Producao',
            default => ucfirst($profile),
        };
    }

    private function allowLongRunningUpload(): void
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

    /**
     * @param array<string, mixed> $extra
     */
    private function updateProgress(?string $progressId, string $stage, string $message, int $percent, array $extra = []): void
    {
        $payload = [
            'status' => 'running',
            'title' => self::PROGRESS_TITLE,
            'stage' => $stage,
            'message' => $message,
            'percent' => max(0, min(100, $percent)),
            'updated_at' => date('c'),
        ];

        $this->writeProgress($progressId, array_merge($payload, $extra));
    }

    private function updateFileProgress(?string $progressId, string $stage, string $message, int $percent, string $fileName, int $sentBytes, int $totalBytes): void
    {
        $sentBytes = max(0, $sentBytes);
        $totalBytes = max(1, $totalBytes);
        $filePercent = max(0, min(100, round(($sentBytes / $totalBytes) * 100, 1)));

        $this->updateProgress($progressId, $stage, $message, $percent, [
            'file' => [
                'name' => $fileName,
                'percent' => $filePercent,
                'sent_bytes' => $sentBytes,
                'total_bytes' => $totalBytes,
                'sent_label' => $this->formatBytes($sentBytes),
                'total_label' => $this->formatBytes($totalBytes),
            ],
        ]);
    }

    private function progressPath(string $progressId): string
    {
        $baseDirectory = (string) ($this->config['progress_path'] ?? base_path('storage/app/backup-cloud/progress'));
        return rtrim($baseDirectory, '/\\') . DIRECTORY_SEPARATOR . $progressId . '.json';
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) max(0, $bytes);
        $index = 0;

        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return number_format($size, $index === 0 ? 0 : 1, ',', '.') . ' ' . $units[$index];
    }
}
