<?php

declare(strict_types=1);

namespace App\Services\Site;

use RuntimeException;
use Scripts\Backup\BackupManager;
use Scripts\ContentSync\ContentSyncManager;

/**
 * Envio de backups (sistemico e editorial) do Estrategia Nerd para o Google
 * Drive - unico provedor de nuvem usado pelo projeto (Dropbox descontinuado).
 */
final class GoogleDriveBackupService
{
    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';
    private const ABOUT_URL = 'https://www.googleapis.com/drive/v3/about';
    private const FILES_URL = 'https://www.googleapis.com/drive/v3/files';
    private const UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3/files';
    private const PROGRESS_TITLE = 'Enviando conteudo para o Google Drive';
    private const RESUMABLE_THRESHOLD_BYTES = 5 * 1024 * 1024;

    public function __construct(private array $config)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getEditorialPanelData(ContentSyncManager $manager): array
    {
        $state = $this->readState();
        $connected = $this->isConnectedState($state);
        $spaceUsage = $connected ? $this->spaceUsage($state) : [
            'available' => false,
            'message' => 'Conecte a conta do Google Drive para consultar o espaco disponivel.',
        ];
        $status = $manager->status();
        $items = [];
        $totalUploaded = 0;

        foreach ((array) ($status['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $bytes = $this->contentPackageBytes($item);
            $uploaded = (bool) ($item['cloud_uploaded_gdrive'] ?? false);
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
                'cloud_uploaded_at' => (string) ($item['cloud_uploaded_at_gdrive'] ?? ''),
                'cloud_destination' => (string) ($item['cloud_destination_gdrive'] ?? ''),
                'cloud_uploaded_size' => $bytes > 0 ? $this->formatBytes($bytes) : '-',
                'cloud_uploaded_size_bytes' => (int) ($item['cloud_uploaded_size_bytes_gdrive'] ?? 0),
                'cloud_uploaded_files_count' => count((array) ($item['cloud_uploaded_files_gdrive'] ?? [])),
                'stats' => is_array($item['stats'] ?? null) ? $item['stats'] : [],
                'uploads' => is_array($item['uploads'] ?? null) ? $item['uploads'] : [],
            ];
        }

        return [
            'provider' => 'Google Drive',
            'configured' => $this->isConfigured(),
            'connected' => $connected,
            'account_email' => (string) ($state['account_email'] ?? ''),
            'account_name' => (string) ($state['account_name'] ?? ''),
            'connected_at' => (string) ($state['connected_at'] ?? ''),
            'auto_upload_enabled' => (bool) ($state['editorial_auto_upload_enabled'] ?? false),
            'root_folder_name' => $this->rootFolderName(),
            'redirect_uri' => $this->redirectUri(),
            'space_usage' => $spaceUsage,
            'items' => $items,
            'pending' => array_values(array_filter($items, static fn (array $item): bool => ($item['cloud_uploaded'] ?? false) !== true)),
            'uploaded' => array_values(array_filter($items, static fn (array $item): bool => ($item['cloud_uploaded'] ?? false) === true)),
            'total' => count($items),
            'total_uploaded_size' => $this->formatBytes($totalUploaded),
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
                throw new RuntimeException('Nenhum pacote editorial encontrado para enviar ao Google Drive.');
            }

            $packageCode = (string) ($package['package_id'] ?? '');
            if ($packageCode === '') {
                throw new RuntimeException('Pacote editorial sem identificador.');
            }

            if ((bool) ($package['cloud_uploaded_gdrive'] ?? false)) {
                throw new RuntimeException(sprintf('Pacote editorial %s ja foi enviado ao Google Drive.', $packageCode));
            }

            $directory = (string) ($package['_dir'] ?? '');
            if ($directory === '' || !is_dir($directory)) {
                throw new RuntimeException('Pasta do pacote editorial nao encontrada para envio.');
            }

            $accessToken = $this->freshAccessToken($state);
            $profile = strtolower((string) ($package['source_profile'] ?? 'local'));

            $this->updateProgress($progress, 'Preparando pastas', 'Organizando pastas no Google Drive.', 10);
            $rootId = $this->ensureRootFolder($accessToken, $state);
            $profileId = $this->ensureFolder($accessToken, $this->profileLabel($profile), $rootId);
            $packageFolderId = $this->ensureFolder($accessToken, $packageCode, $profileId);

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
            foreach ($entries as $entry) {
                $localPath = $directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry);
                if (!is_file($localPath)) {
                    throw new RuntimeException('Arquivo do pacote editorial ausente para envio: ' . $entry);
                }

                $parentId = $packageFolderId;
                $name = basename($entry);
                if (str_starts_with($entry, 'data/')) {
                    $dataFolderId = $this->ensureFolder($accessToken, 'data', $packageFolderId);
                    $parentId = $dataFolderId;
                }

                $fileId = $this->uploadFile($accessToken, $localPath, $name, $parentId, $progress);
                $uploadedSize += (int) filesize($localPath);
                $uploadedFiles[] = ['name' => $entry, 'id' => $fileId];
            }

            $destination = sprintf('%s/%s/%s', $this->rootFolderName(), $this->profileLabel($profile), $packageCode);

            $result = [
                'provider' => 'google_drive',
                'package_id' => $packageCode,
                'destination' => $destination,
                'uploaded_files' => $uploadedFiles,
                'uploaded_files_count' => count($uploadedFiles),
                'uploaded_size_bytes' => $uploadedSize,
            ];

            $this->markContentPackageCloudUploaded($package, $result);

            $state['last_editorial_upload'] = [
                'package_id' => $packageCode,
                'profile' => $profile,
                'destination' => $destination,
                'uploaded_at' => date('c'),
                'files' => count($uploadedFiles),
            ];
            $this->writeState($state);

            $this->updateProgress($progress, 'Concluido', 'Pacote editorial enviado ao Google Drive.', 100);

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
    public function deleteEditorialPackage(ContentSyncManager $manager, string $packageId, string $confirmation): array
    {
        $packageId = trim($packageId);
        if ($packageId === '' || strtolower($packageId) === 'latest') {
            throw new RuntimeException('Informe o ID exato do pacote editorial que sera removido do Google Drive.');
        }

        if (!hash_equals($packageId, trim($confirmation))) {
            throw new RuntimeException('Confirmacao invalida. Digite o ID exato do pacote editorial para remover do Google Drive.');
        }

        $state = $this->requireConnectedState();
        $package = $this->findContentPackage($manager, $packageId);
        if (!is_array($package)) {
            throw new RuntimeException('Pacote editorial nao encontrado no historico local.');
        }

        if (($package['cloud_uploaded_gdrive'] ?? false) !== true) {
            throw new RuntimeException('Este pacote editorial nao esta marcado como enviado ao Google Drive.');
        }

        $accessToken = $this->freshAccessToken($state);
        $profile = strtolower((string) ($package['source_profile'] ?? 'local'));
        $rootId = $this->ensureRootFolder($accessToken, $state);
        $profileId = $this->findFolderId($accessToken, $this->profileLabel($profile), $rootId);
        $packageFolderId = $profileId !== null ? $this->findFolderId($accessToken, $packageId, $profileId) : null;

        if ($packageFolderId === null) {
            throw new RuntimeException('Pasta do pacote editorial nao foi encontrada no Google Drive (pode ja ter sido removida).');
        }

        $this->curl(self::FILES_URL . '/' . rawurlencode($packageFolderId), [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        ], false);

        $this->markContentPackageCloudDeleted($package);

        $state['last_editorial_delete'] = [
            'package_id' => $packageId,
            'deleted_at' => date('c'),
        ];
        $this->writeState($state);

        return [
            'provider' => 'google_drive',
            'package_id' => $packageId,
            'destination' => sprintf('%s/%s/%s', $this->rootFolderName(), $this->profileLabel($profile), $packageId),
        ];
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

        try {
            $state = $this->requireConnectedState();
            $backup = $manager->findBackup($backupId, false);
            if (!is_array($backup)) {
                throw new RuntimeException('Nenhum backup encontrado para enviar ao Google Drive.');
            }

            $backupCode = (string) ($backup['backup_id'] ?? '');
            if (($backup['cloud_uploaded'] ?? false) === true) {
                throw new RuntimeException(sprintf('Backup %s ja foi enviado para a nuvem.', $backupCode));
            }

            $directory = (string) ($backup['_dir'] ?? '');
            if ($directory === '' || !is_dir($directory)) {
                throw new RuntimeException('Pasta do backup nao encontrada para envio.');
            }

            $accessToken = $this->freshAccessToken($state);
            $profile = strtolower((string) ($backup['profile'] ?? 'local'));

            $this->updateProgress($progress, 'Preparando pastas', 'Organizando pastas no Google Drive.', 10);
            $rootId = $this->ensureRootFolder($accessToken, $state);
            $systemRootId = $this->ensureFolder($accessToken, 'Sistema', $rootId);
            $profileId = $this->ensureFolder($accessToken, $this->profileLabel($profile), $systemRootId);
            $backupFolderId = $this->ensureFolder($accessToken, $backupCode, $profileId);

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
            foreach ($entries as $entry) {
                $localPath = $directory . DIRECTORY_SEPARATOR . $entry;
                if (!is_file($localPath)) {
                    throw new RuntimeException('Arquivo do backup ausente para envio: ' . $entry);
                }

                $fileId = $this->uploadFile($accessToken, $localPath, $entry, $backupFolderId, $progress);
                $uploadedSize += (int) filesize($localPath);
                $uploadedFiles[] = ['name' => $entry, 'id' => $fileId];
            }

            $destination = sprintf('%s/Sistema/%s/%s', $this->rootFolderName(), $this->profileLabel($profile), $backupCode);

            $result = [
                'provider' => 'google_drive',
                'destination' => $destination,
                'remote_id' => (string) ($uploadedFiles[0]['id'] ?? ''),
                'uploaded_files' => $uploadedFiles,
                'uploaded_files_count' => count($uploadedFiles),
                'uploaded_size_bytes' => $uploadedSize,
            ];

            $manager->markCloudUploaded($backupCode, $result);

            $state['last_upload'] = [
                'backup_id' => $backupCode,
                'profile' => $profile,
                'destination' => $destination,
                'uploaded_at' => date('c'),
                'files' => count($uploadedFiles),
            ];
            $this->writeState($state);

            $this->updateProgress($progress, 'Concluido', 'Backup enviado ao Google Drive.', 100);

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
            throw new RuntimeException('Informe o ID exato do backup que sera removido do Google Drive.');
        }

        if (!hash_equals($backupId, trim($confirmation))) {
            throw new RuntimeException('Confirmacao invalida. Digite o ID exato do backup para remover do Google Drive.');
        }

        $state = $this->requireConnectedState();
        $backup = $manager->findBackup($backupId, false);
        if (!is_array($backup) || ($backup['cloud_uploaded'] ?? false) !== true) {
            throw new RuntimeException('Este backup nao esta marcado como enviado ao Google Drive.');
        }

        $accessToken = $this->freshAccessToken($state);
        $profile = strtolower((string) ($backup['profile'] ?? 'local'));
        $rootId = $this->ensureRootFolder($accessToken, $state);
        $systemRootId = $this->findFolderId($accessToken, 'Sistema', $rootId);
        $profileId = $systemRootId !== null ? $this->findFolderId($accessToken, $this->profileLabel($profile), $systemRootId) : null;
        $backupFolderId = $profileId !== null ? $this->findFolderId($accessToken, $backupId, $profileId) : null;

        if ($backupFolderId === null) {
            throw new RuntimeException('Pasta do backup nao foi encontrada no Google Drive (pode ja ter sido removida).');
        }

        $this->curl(self::FILES_URL . '/' . rawurlencode($backupFolderId), [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        ], false);

        $destination = sprintf('%s/Sistema/%s/%s', $this->rootFolderName(), $this->profileLabel($profile), $backupId);
        $manager->markCloudDeleted($backupId, ['provider' => 'google_drive', 'destination' => $destination]);

        return ['provider' => 'google_drive', 'backup_id' => $backupId, 'destination' => $destination];
    }

    public function authorizationUrl(string $state): string
    {
        $this->assertConfigured();

        $query = http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'access_type' => 'offline',
            // 'consent' forca o Google a devolver refresh_token mesmo se essa conta
            // ja tiver autorizado o app antes (sem isso, uma reconexao pode vir sem
            // refresh_token, quebrando a automacao).
            'prompt' => 'consent',
            'scope' => implode(' ', (array) ($this->config['google_drive']['scopes'] ?? [])),
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
        if ($accessToken === '') {
            throw new RuntimeException('Google Drive nao retornou um access_token valido.');
        }
        if ($refreshToken === '') {
            throw new RuntimeException('Google Drive nao retornou refresh_token. Revogue o acesso do app em myaccount.google.com/permissions e tente conectar de novo.');
        }

        $userInfo = $this->decodeJwtPayload((string) ($tokenPayload['id_token'] ?? ''));

        $state = $this->readState();
        $state['account_email'] = (string) ($userInfo['email'] ?? '');
        $state['account_name'] = (string) ($userInfo['name'] ?? ($userInfo['email'] ?? 'Google Drive'));
        $state['access_token'] = $accessToken;
        $state['refresh_token'] = $refreshToken;
        $state['token_expires_at'] = time() + max(60, (int) ($tokenPayload['expires_in'] ?? 0));
        $state['connected_at'] = date('c');
        $state['provider'] = 'google_drive';
        $state['editorial_auto_upload_enabled'] = (bool) ($state['editorial_auto_upload_enabled'] ?? false);

        $this->writeState($state);

        return $state;
    }

    public function disconnect(): void
    {
        $state = $this->readState();
        $refreshToken = (string) ($state['refresh_token'] ?? '');
        if ($refreshToken !== '') {
            try {
                $this->curl(self::REVOKE_URL, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => http_build_query(['token' => $refreshToken]),
                ], false);
            } catch (\Throwable) {
                // Revogacao remota e best-effort; o estado local e removido de qualquer forma.
            }
        }

        $path = $this->statePath();
        if (is_file($path)) {
            @unlink($path);
        }
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
        return $this->isConnectedState($state) && (bool) ($state['editorial_auto_upload_enabled'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function getProgress(?string $progressId): array
    {
        $progress = $this->normalizeProgressId($progressId);
        if ($progress === null) {
            return ['status' => 'idle', 'title' => self::PROGRESS_TITLE, 'stage' => 'Aguardando', 'message' => 'Nenhum envio em andamento.', 'percent' => 0];
        }

        $path = $this->progressPath($progress);
        if (!is_file($path)) {
            return ['status' => 'idle', 'title' => self::PROGRESS_TITLE, 'stage' => 'Aguardando', 'message' => 'Nenhum progresso encontrado para este envio.', 'percent' => 0];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : ['status' => 'error', 'title' => self::PROGRESS_TITLE, 'stage' => 'Erro de leitura', 'message' => 'Nao foi possivel ler o progresso.', 'percent' => 0];
    }

    private function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Configure BACKUP_GOOGLE_DRIVE_CLIENT_ID e BACKUP_GOOGLE_DRIVE_CLIENT_SECRET antes de conectar o Google Drive.');
        }
    }

    private function clientId(): string
    {
        return trim((string) ($this->config['google_drive']['client_id'] ?? ''));
    }

    private function clientSecret(): string
    {
        return trim((string) ($this->config['google_drive']['client_secret'] ?? ''));
    }

    private function redirectUri(): string
    {
        return trim((string) ($this->config['google_drive']['redirect_uri'] ?? ''));
    }

    private function rootFolderName(): string
    {
        return trim((string) ($this->config['google_drive']['editorial_root_folder_name'] ?? 'Estrategia Nerd - Backups de Conteudo'));
    }

    private function statePath(): string
    {
        return (string) ($this->config['google_drive']['state_path'] ?? base_path('storage/app/backup-cloud/google-drive-connection.json'));
    }

    private function progressPath(string $progressId): string
    {
        $baseDirectory = (string) ($this->config['google_drive']['progress_path'] ?? base_path('storage/app/backup-cloud/progress'));
        return rtrim($baseDirectory, '/\\') . DIRECTORY_SEPARATOR . 'gdrive-' . $progressId . '.json';
    }

    /**
     * @return array<string, mixed>
     */
    private function requireConnectedState(): array
    {
        $state = $this->readState();
        if (!$this->isConnectedState($state)) {
            throw new RuntimeException('Conecte a conta do Google Drive antes de enviar conteudo.');
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function isConnectedState(array $state): bool
    {
        return trim((string) ($state['refresh_token'] ?? '')) !== '';
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
            throw new RuntimeException('Nao foi possivel criar a pasta de configuracao do Google Drive.');
        }

        file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
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
            throw new RuntimeException('Google Drive nao retornou um novo access_token. Pode ser necessario reconectar a conta.');
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
        $response = $this->curl(self::TOKEN_URL, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($form + [
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        return $this->decodeJsonResponse($response['body'], $response['status'], 'Google Drive token');
    }

    /**
     * Decodifica o payload do id_token (JWT) so pra ler nome/email - a assinatura
     * ja foi validada pelo proprio Google ao emitir o token nesta troca servidor-a-servidor
     * (chegou direto da resposta HTTPS do endpoint oficial de token, nao de terceiros).
     *
     * @return array<string, mixed>
     */
    private function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return [];
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payload === false) {
            return [];
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function rpc(string $url, string $accessToken, array $payload): array
    {
        $response = $this->curl($url, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
        ]);

        return $this->decodeJsonResponse($response['body'], $response['status'], 'Google Drive API');
    }

    /**
     * Garante a pasta raiz do backup de conteudo, criando se necessario, e guarda
     * o id no estado local (com escopo drive.file, o app so enxerga o que ele mesmo
     * cria - por isso o id precisa ficar guardado, nao dá pra so pesquisar por nome
     * com garantia de achar a mesma pasta sempre).
     *
     * @param array<string, mixed> $state
     */
    private function ensureRootFolder(string $accessToken, array &$state): string
    {
        $existing = trim((string) ($state['editorial_root_folder_id'] ?? ''));
        if ($existing !== '' && $this->folderExists($accessToken, $existing)) {
            return $existing;
        }

        $created = $this->rpc(self::FILES_URL, $accessToken, [
            'name' => $this->rootFolderName(),
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);
        $id = (string) ($created['id'] ?? '');
        if ($id === '') {
            throw new RuntimeException('Nao foi possivel criar a pasta raiz no Google Drive.');
        }

        $state['editorial_root_folder_id'] = $id;
        $this->writeState($state);

        return $id;
    }

    private function folderExists(string $accessToken, string $folderId): bool
    {
        try {
            $response = $this->curl(self::FILES_URL . '/' . rawurlencode($folderId) . '?' . http_build_query(['fields' => 'id,trashed']), [
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
            ]);
            $decoded = $this->decodeJsonResponse($response['body'], $response['status'], 'Google Drive API');
            return ($decoded['trashed'] ?? false) !== true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Busca uma subpasta pelo nome dentro do pai; retorna null se nao existir.
     */
    private function findFolderId(string $accessToken, string $name, string $parentId): ?string
    {
        $escapedName = str_replace("'", "\\'", $name);
        $query = sprintf(
            "name = '%s' and mimeType = 'application/vnd.google-apps.folder' and '%s' in parents and trashed = false",
            $escapedName,
            $parentId
        );

        $response = $this->curl(self::FILES_URL . '?' . http_build_query([
            'q' => $query,
            'fields' => 'files(id,name)',
            'spaces' => 'drive',
            'pageSize' => 1,
        ]), [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        ]);
        $decoded = $this->decodeJsonResponse($response['body'], $response['status'], 'Google Drive API');
        $files = (array) ($decoded['files'] ?? []);

        return isset($files[0]['id']) ? (string) $files[0]['id'] : null;
    }

    /**
     * Busca uma subpasta pelo nome dentro do pai; cria se nao existir.
     */
    private function ensureFolder(string $accessToken, string $name, string $parentId): string
    {
        $existing = $this->findFolderId($accessToken, $name, $parentId);
        if ($existing !== null) {
            return $existing;
        }

        $created = $this->rpc(self::FILES_URL, $accessToken, [
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId],
        ]);
        $id = (string) ($created['id'] ?? '');
        if ($id === '') {
            throw new RuntimeException('Nao foi possivel criar a pasta "' . $name . '" no Google Drive.');
        }

        return $id;
    }

    private function uploadFile(string $accessToken, string $localPath, string $name, string $parentId, ?string $progressId): string
    {
        $size = filesize($localPath);
        if ($size === false || $size <= 0) {
            throw new RuntimeException('Arquivo vazio ou inacessivel para upload: ' . basename($localPath));
        }

        $this->updateProgress($progressId, 'Enviando arquivo', sprintf('Enviando %s.', $name), 40);

        if ($size <= self::RESUMABLE_THRESHOLD_BYTES) {
            return $this->simpleUpload($accessToken, $localPath, $name, $parentId);
        }

        return $this->resumableUpload($accessToken, $localPath, $name, $parentId, $size, $progressId);
    }

    private function simpleUpload(string $accessToken, string $localPath, string $name, string $parentId): string
    {
        $boundary = 'gdrive-' . bin2hex(random_bytes(16));
        $metadata = json_encode(['name' => $name, 'parents' => [$parentId]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $content = (string) file_get_contents($localPath);

        $body = "--{$boundary}\r\n"
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . $metadata . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: application/octet-stream\r\n\r\n"
            . $content . "\r\n"
            . "--{$boundary}--";

        $response = $this->curl(self::UPLOAD_URL . '?uploadType=multipart&fields=id', [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: multipart/related; boundary=' . $boundary,
            ],
        ]);

        $decoded = $this->decodeJsonResponse($response['body'], $response['status'], 'Google Drive upload');
        $id = (string) ($decoded['id'] ?? '');
        if ($id === '') {
            throw new RuntimeException('Google Drive nao retornou o id do arquivo enviado: ' . $name);
        }

        return $id;
    }

    private function resumableUpload(string $accessToken, string $localPath, string $name, string $parentId, int $size, ?string $progressId): string
    {
        $metadata = json_encode(['name' => $name, 'parents' => [$parentId]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $startResponse = $this->curl(self::UPLOAD_URL . '?uploadType=resumable&fields=id', [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $metadata,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json; charset=UTF-8',
            ],
        ], false, true);

        if (!preg_match('/^Location:\s*(\S+)/mi', $startResponse['body'], $matches)) {
            throw new RuntimeException('Google Drive nao retornou a URL de sessao para upload em partes.');
        }
        $sessionUri = trim($matches[1]);

        $handle = fopen($localPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Nao foi possivel abrir o arquivo para upload em partes.');
        }

        $chunkSize = max(4 * 1024 * 1024, (int) ($this->config['google_drive']['chunk_size'] ?? (8 * 1024 * 1024)));
        $offset = 0;
        $fileId = '';

        try {
            while ($offset < $size) {
                $this->allowLongRunningUpload();
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false) {
                    throw new RuntimeException('Falha ao ler um bloco do arquivo para upload.');
                }
                $chunkLength = strlen($chunk);
                $rangeEnd = $offset + $chunkLength - 1;

                $response = $this->curl($sessionUri, [
                    CURLOPT_CUSTOMREQUEST => 'PUT',
                    CURLOPT_POSTFIELDS => $chunk,
                    CURLOPT_TIMEOUT => 0,
                    CURLOPT_HTTPHEADER => [
                        'Content-Length: ' . $chunkLength,
                        'Content-Range: bytes ' . $offset . '-' . $rangeEnd . '/' . $size,
                    ],
                ], false);

                $offset += $chunkLength;
                $percent = 40 + (int) round(($offset / max(1, $size)) * 50);
                $this->updateProgress($progressId, 'Enviando arquivo grande', sprintf('Enviando %s: %s de %s.', $name, $this->formatBytes($offset), $this->formatBytes($size)), min(90, $percent));

                if ($response['status'] === 200 || $response['status'] === 201) {
                    $decoded = json_decode($response['body'], true);
                    $fileId = is_array($decoded) ? (string) ($decoded['id'] ?? '') : '';
                }
            }
        } finally {
            fclose($handle);
        }

        if ($fileId === '') {
            throw new RuntimeException('Google Drive nao confirmou a conclusao do upload em partes para: ' . $name);
        }

        return $fileId;
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
            $response = $this->curl(self::ABOUT_URL . '?fields=storageQuota', [
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
            ]);
            $decoded = $this->decodeJsonResponse($response['body'], $response['status'], 'Google Drive about');
            $quota = (array) ($decoded['storageQuota'] ?? []);
            $usedBytes = (int) ($quota['usage'] ?? 0);
            $limitBytes = isset($quota['limit']) ? (int) $quota['limit'] : null;
            $freeBytes = $limitBytes !== null ? max(0, $limitBytes - $usedBytes) : null;

            return [
                'available' => true,
                'used_bytes' => $usedBytes,
                'limit_bytes' => $limitBytes,
                'used' => $this->formatBytes($usedBytes),
                'limit' => $limitBytes !== null ? $this->formatBytes($limitBytes) : 'Ilimitado',
                'free' => $freeBytes !== null ? $this->formatBytes($freeBytes) : '-',
                'percent_used' => $limitBytes !== null && $limitBytes > 0 ? min(100, round(($usedBytes / $limitBytes) * 100, 1)) : null,
            ];
        } catch (\Throwable $exception) {
            return [
                'available' => false,
                'message' => 'Nao foi possivel consultar o espaco do Google Drive: ' . $exception->getMessage(),
            ];
        }
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
            if (is_array($item) && (string) ($item['package_id'] ?? '') === $packageId) {
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

        // Chaves com sufixo _gdrive pra nao colidir com o mesmo controle ja usado
        // pelo Dropbox no mesmo manifest.json (os dois provedores podem coexistir).
        $manifest['cloud_uploaded_gdrive'] = true;
        $manifest['cloud_uploaded_at_gdrive'] = date('c');
        $manifest['cloud_destination_gdrive'] = (string) ($result['destination'] ?? '');
        $manifest['cloud_uploaded_size_bytes_gdrive'] = (int) ($result['uploaded_size_bytes'] ?? 0);
        $manifest['cloud_uploaded_files_gdrive'] = array_values((array) ($result['uploaded_files'] ?? []));

        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param array<string, mixed> $package
     */
    private function markContentPackageCloudDeleted(array $package): void
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

        $manifest['cloud_uploaded_gdrive'] = false;
        unset($manifest['cloud_uploaded_at_gdrive'], $manifest['cloud_destination_gdrive'], $manifest['cloud_uploaded_size_bytes_gdrive'], $manifest['cloud_uploaded_files_gdrive']);

        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
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

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
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

    /**
     * @return array{status:int,body:string}
     */
    private function curl(string $url, array $options, bool $expectJson = true, bool $includeHeaders = false): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL nao esta disponivel para integrar com o Google Drive.');
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Nao foi possivel iniciar a conexao cURL com o Google Drive.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => $includeHeaders,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_CONNECTTIMEOUT => 20,
        ] + $options);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($body === false) {
            throw new RuntimeException('Falha de conexao com o Google Drive: ' . $error);
        }

        if (!$includeHeaders && $status >= 400) {
            $message = $body;
            if ($expectJson) {
                $decoded = json_decode($body, true);
                if (is_array($decoded)) {
                    $message = (string) ($decoded['error']['message'] ?? $decoded['error_description'] ?? $body);
                }
            }

            throw new RuntimeException('Google Drive respondeu com erro HTTP ' . $status . ': ' . trim((string) $message));
        }

        return ['status' => $status, 'body' => (string) $body];
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
