<?php

declare(strict_types=1);

namespace Scripts\Deploy;

require_once __DIR__ . '/../content-sync/ContentSyncManager.php';
require_once __DIR__ . '/../operations/OperationLogger.php';

use RuntimeException;
use Scripts\ContentSync\ContentSyncManager;
use Scripts\Operations\OperationLogger;

final class DeployManager
{
    private const TECHNICAL_DIRECTORIES = [
        'local' => '01-local',
        'stage' => '02-stage',
        'production' => '03-prod',
    ];
    private const PROTECTED_TECHNICAL_PATHS = [
        '.env',
        '_app_core/.env',
        'index.php',
        '.htaccess',
        'public/index.php',
        'public/.htaccess',
    ];

    private OperationLogger $logger;
    private ContentSyncManager $contentSync;

    public function __construct(private array $config)
    {
        date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo');
        $this->logger = new OperationLogger($this->backupRoot());
        $this->contentSync = new ContentSyncManager((array) ($this->config['content_sync'] ?? []));
    }

    public function deploymentPolicyStatus(): array
    {
        return $this->contentSync->deploymentPolicyStatus();
    }

    public function codeStatus(): array
    {
        return $this->contentSync->codeStatus();
    }

    public function applyCode(?string $packageId, string $targetProfile = 'production', bool $force = false): array
    {
        return $this->contentSync->applyCode($packageId, $targetProfile, $force);
    }

    public function profileReady(string $profileName): bool
    {
        $profileName = strtolower(trim($profileName));

        try {
            $config = $this->codeDeployConfig($profileName);
        } catch (\Throwable) {
            return false;
        }

        if (($config['mode'] ?? '') === 'local') {
            return trim((string) ($config['root'] ?? '')) !== '';
        }

        foreach (['host', 'username', 'password', 'root'] as $required) {
            if (trim((string) ($config[$required] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    public function backupTecnico(string $profileName): array
    {
        $profileName = strtolower(trim($profileName));
        $profile = $this->profile($profileName);
        $rootDirectory = $this->technicalRootDirectory($profileName);
        $backupId = $this->buildTechnicalBackupId($profileName);
        $backupDir = $this->backupRoot()
            . DIRECTORY_SEPARATOR
            . $rootDirectory
            . DIRECTORY_SEPARATOR
            . 'tecnico'
            . DIRECTORY_SEPARATOR
            . $backupId;

        if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
            throw new RuntimeException('Nao foi possivel criar a pasta do backup tecnico: ' . $backupDir);
        }

        $deployConfig = $this->codeDeployConfig($profileName);
        $tmpDirectory = '';

        $manifest = [
            'backup_id' => $backupId,
            'profile' => $profileName,
            'profile_label' => (string) ($profile['label'] ?? $profileName),
            'created_at' => date('c'),
            'status' => 'running',
            'mode' => (string) ($deployConfig['mode'] ?? ''),
            'source_root' => (string) ($deployConfig['root'] ?? ''),
            'files_count' => 0,
            'files_zip' => null,
            'checksums_file' => null,
            'protected_paths' => self::PROTECTED_TECHNICAL_PATHS,
            'error' => null,
        ];

        try {
            $tmpDirectory = $this->materializeTechnicalSource($deployConfig);
            $checksums = $this->buildChecksums($tmpDirectory);
            $manifest['files_count'] = count($checksums);

            $checksumsPath = $backupDir . DIRECTORY_SEPARATOR . 'checksums.json';
            $this->writeJson($checksumsPath, $checksums);
            $manifest['checksums_file'] = $this->fileDetails($checksumsPath, 'checksums.json');

            $zipPath = $backupDir . DIRECTORY_SEPARATOR . 'files.zip';
            $this->compressDirectory($tmpDirectory, $zipPath);
            $manifest['files_zip'] = $this->fileDetails($zipPath, 'files.zip');

            $manifest['status'] = 'ready';
            $this->writeJson($backupDir . DIRECTORY_SEPARATOR . 'manifest.json', $manifest);
            $this->logOperation('backup_tecnico', $profileName, $profileName, $backupId, 'OK', 'Backup tecnico concluido.', [
                'files' => $manifest['files_count'],
            ]);

            return $manifest;
        } catch (\Throwable $exception) {
            $manifest['status'] = 'failed';
            $manifest['error'] = $exception->getMessage();
            $this->writeJson($backupDir . DIRECTORY_SEPARATOR . 'manifest.json', $manifest);
            $this->logOperation('backup_tecnico', $profileName, $profileName, $backupId, 'FAIL', $exception->getMessage());
            throw $exception;
        } finally {
            if ($tmpDirectory !== '' && is_dir($tmpDirectory) && str_contains($tmpDirectory, sys_get_temp_dir())) {
                $this->removeDirectory($tmpDirectory);
            }
        }
    }

    public function technicalBackupStatus(?string $profileName = null): array
    {
        $items = [];

        foreach (self::TECHNICAL_DIRECTORIES as $name => $directory) {
            if ($profileName !== null && strtolower(trim($profileName)) !== $name) {
                continue;
            }

            $items[$name] = $this->listTechnicalBackups($name, $directory);
        }

        return [
            'backup_root' => $this->backupRoot(),
            'profiles' => $items,
        ];
    }

    public function rollbackTecnico(string $targetProfile, ?string $backupId = null, bool $force = false): array
    {
        $this->extendExecutionWindow();

        if (!$force) {
            throw new RuntimeException('Rollback tecnico exige confirmacao explicita.');
        }

        $targetProfile = strtolower(trim($targetProfile));
        $backup = $this->technicalBackupById($targetProfile, $backupId);
        if ($backup === null) {
            throw new RuntimeException('Nenhum backup tecnico encontrado para rollback.');
        }

        $zipPath = (string) ($backup['_dir'] ?? '') . DIRECTORY_SEPARATOR . (string) (($backup['files_zip']['name'] ?? '') ?: 'files.zip');
        if (!is_file($zipPath)) {
            throw new RuntimeException('Arquivo files.zip do backup tecnico nao encontrado.');
        }

        $tmpDir = '';
        try {
            $tmpDir = $this->extractArchive($zipPath);
            $result = $this->deployCode($this->codeDeployConfig($targetProfile), $tmpDir);
            $rollback = [
                'target_profile' => $targetProfile,
                'target_profile_label' => (string) ($backup['profile_label'] ?? $targetProfile),
                'applied_at' => date('c'),
                'result' => $result,
            ];

            $backup['rollback_targets'] = array_values(array_merge((array) ($backup['rollback_targets'] ?? []), [$rollback]));
            $backupDir = (string) ($backup['_dir'] ?? '');
            if ($backupDir !== '') {
                $manifest = $backup;
                unset($manifest['_dir']);
                $this->writeJson($backupDir . DIRECTORY_SEPARATOR . 'manifest.json', $manifest);
            }

            $this->logOperation(
                'rollback_tecnico',
                $targetProfile,
                $targetProfile,
                (string) ($backup['backup_id'] ?? ''),
                'OK',
                'Rollback tecnico executado com sucesso.',
                ['files' => (int) ($result['files_applied'] ?? 0)]
            );

            return [
                'backup_id' => (string) ($backup['backup_id'] ?? ''),
                'target_profile' => $targetProfile,
                'applied_at' => (string) ($rollback['applied_at'] ?? ''),
                'result' => $result,
            ];
        } catch (\Throwable $exception) {
            $this->logOperation(
                'rollback_tecnico',
                $targetProfile,
                $targetProfile,
                (string) ($backup['backup_id'] ?? ($backupId ?? '')),
                'FAIL',
                $exception->getMessage()
            );
            throw $exception;
        } finally {
            if ($tmpDir !== '' && is_dir($tmpDir) && str_contains($tmpDir, sys_get_temp_dir())) {
                $this->removeDirectory($tmpDir);
            }
        }
    }

    private function profile(string $profileName): array
    {
        $profiles = (array) ($this->config['profiles'] ?? []);
        if (!isset($profiles[$profileName]) || !is_array($profiles[$profileName])) {
            throw new RuntimeException('Perfil tecnico nao encontrado: ' . $profileName);
        }

        return $profiles[$profileName];
    }

    private function codeDeployConfig(string $profileName): array
    {
        $profile = $this->profile($profileName);
        $config = (array) ($profile['code_deploy'] ?? []);
        $mode = strtolower(trim((string) ($config['mode'] ?? 'ftp')));

        if ($mode === 'local') {
            if (trim((string) ($config['root'] ?? '')) === '') {
                throw new RuntimeException('Configuracao de deploy local incompleta: root.');
            }

            return [
                'mode' => 'local',
                'root' => (string) $config['root'],
            ];
        }

        foreach (['host', 'port', 'username', 'password', 'root'] as $required) {
            if (trim((string) ($config[$required] ?? '')) === '') {
                throw new RuntimeException('Configuracao tecnica incompleta para ' . $profileName . ': faltando ' . $required);
            }
        }

        return [
            'mode' => 'ftp',
            'host' => (string) $config['host'],
            'port' => (int) $config['port'],
            'username' => (string) $config['username'],
            'password' => (string) $config['password'],
            'root' => (string) $config['root'],
            'passive' => (bool) ($config['passive'] ?? true),
        ];
    }

    private function technicalRootDirectory(string $profileName): string
    {
        if (!isset(self::TECHNICAL_DIRECTORIES[$profileName])) {
            throw new RuntimeException('Backup tecnico suporta apenas local, stage e production nesta V1.');
        }

        return self::TECHNICAL_DIRECTORIES[$profileName];
    }

    private function technicalBackupById(string $profileName, ?string $backupId = null): ?array
    {
        $items = $this->listTechnicalBackups($profileName, $this->technicalRootDirectory($profileName));
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

    private function backupRoot(): string
    {
        $root = trim((string) ($this->config['backup_root'] ?? ''));
        if ($root === '') {
            throw new RuntimeException('BACKUP_ROOT nao configurado para o deploy.');
        }

        return $root;
    }

    private function buildTechnicalBackupId(string $profileName): string
    {
        return sprintf('BT-%s-%s', $this->profileToken($profileName), date('Ymd-His'));
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

    private function materializeTechnicalSource(array $deployConfig): string
    {
        $mode = strtolower((string) ($deployConfig['mode'] ?? 'ftp'));
        $tmpRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'en-tech-' . bin2hex(random_bytes(6));
        if (!mkdir($tmpRoot, 0777, true) && !is_dir($tmpRoot)) {
            throw new RuntimeException('Nao foi possivel criar a pasta temporaria do backup tecnico.');
        }

        if ($mode === 'local') {
            $sourceRoot = (string) ($deployConfig['root'] ?? '');
            if ($sourceRoot === '' || !is_dir($sourceRoot)) {
                throw new RuntimeException('Raiz local tecnica nao encontrada: ' . $sourceRoot);
            }

            $this->copyFilteredTree($sourceRoot, $tmpRoot);
            $this->ensureDirectoryHasContent($tmpRoot);

            return $tmpRoot;
        }

        $rawTmpRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'en-tech-raw-' . bin2hex(random_bytes(6));
        if (!mkdir($rawTmpRoot, 0777, true) && !is_dir($rawTmpRoot)) {
            throw new RuntimeException('Nao foi possivel criar a pasta temporaria bruta do backup tecnico.');
        }

        $connection = @ftp_connect((string) $deployConfig['host'], (int) $deployConfig['port'], 30);
        if ($connection === false) {
            throw new RuntimeException('Nao foi possivel conectar ao FTP do backup tecnico.');
        }

        try {
            if (!@ftp_login($connection, (string) $deployConfig['username'], (string) $deployConfig['password'])) {
                throw new RuntimeException('Falha de login no FTP do backup tecnico.');
            }

            ftp_pasv($connection, (bool) ($deployConfig['passive'] ?? true));
            $this->downloadFtpTree($connection, rtrim((string) $deployConfig['root'], '/'), $rawTmpRoot);
            $this->copyFilteredTree($rawTmpRoot, $tmpRoot);
            $this->ensureDirectoryHasContent($tmpRoot);
        } finally {
            ftp_close($connection);
            if (is_dir($rawTmpRoot) && str_contains($rawTmpRoot, sys_get_temp_dir())) {
                $this->removeDirectory($rawTmpRoot);
            }
        }

        return $tmpRoot;
    }

    /**
     * @return array<string, string>
     */
    private function buildChecksums(string $directory): array
    {
        $files = $this->listFiles($directory);
        $checksums = [];
        $root = rtrim(str_replace('\\', '/', $directory), '/');

        foreach ($files as $file) {
            $normalized = str_replace('\\', '/', $file);
            $relative = ltrim(substr($normalized, strlen($root)), '/');
            if ($relative === '') {
                continue;
            }

            $hash = hash_file('sha256', $file);
            if ($hash === false) {
                throw new RuntimeException('Falha ao calcular checksum do arquivo tecnico: ' . $relative);
            }

            $checksums[$relative] = $hash;
        }

        ksort($checksums);

        return $checksums;
    }

    /**
     * @return array<int, string>
     */
    private function listFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        $files = [];
        $root = rtrim(str_replace('\\', '/', $directory), '/');
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $path = $item->getPathname();
                $normalized = str_replace('\\', '/', $path);
                $relative = ltrim(substr($normalized, strlen($root)), '/');
                if ($relative === '' || !$this->shouldIncludeTechnicalRelativePath($relative)) {
                    continue;
                }

                $files[] = $path;
            }
        }

        sort($files);

        return $files;
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
            throw new RuntimeException('Falha ao compactar o backup tecnico.');
        }
    }

    private function extractArchive(string $zipPath): string
    {
        if (!is_file($zipPath)) {
            throw new RuntimeException('Arquivo zip do backup tecnico nao encontrado: ' . $zipPath);
        }

        $destination = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'en-tech-restore-' . bin2hex(random_bytes(6));
        if (!mkdir($destination, 0777, true) && !is_dir($destination)) {
            throw new RuntimeException('Nao foi possivel criar pasta temporaria para rollback tecnico.');
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
            throw new RuntimeException('Falha ao extrair o backup tecnico para rollback.');
        }

        return $destination;
    }

    private function fileDetails(string $path, string $name): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Arquivo tecnico nao encontrado: ' . $path);
        }

        return [
            'name' => $name,
            'path' => $path,
            'size_bytes' => filesize($path),
            'sha256' => hash_file('sha256', $path),
        ];
    }

    private function listTechnicalBackups(string $profileName, string $rootDirectory): array
    {
        $baseDir = $this->backupRoot() . DIRECTORY_SEPARATOR . $rootDirectory . DIRECTORY_SEPARATOR . 'tecnico';
        if (!is_dir($baseDir)) {
            return [];
        }

        $directories = glob($baseDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
        rsort($directories);

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

        return $items;
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

    private function writeJson(string $path, array $payload): void
    {
        if (file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
            throw new RuntimeException('Nao foi possivel gravar o arquivo JSON: ' . $path);
        }
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
        $skippedProtected = 0;
        foreach ($files as $file) {
            $relative = substr(str_replace('\\', '/', $file), strlen(str_replace('\\', '/', $sourceDir)) + 1);
            $relative = ltrim($relative, '/');
            if ($relative === '' || str_contains($relative, '..')) {
                continue;
            }
            if (!$this->shouldIncludeTechnicalRelativePath($relative)) {
                $skippedProtected++;
                continue;
            }

            $destination = $this->resolveCodeDeployPath($targetRoot, $relative);
            $destinationDir = dirname($destination);
            if (!is_dir($destinationDir) && !mkdir($destinationDir, 0777, true) && !is_dir($destinationDir)) {
                throw new RuntimeException('Nao foi possivel criar pasta de destino para rollback local: ' . $relative);
            }

            if (!copy($file, $destination)) {
                throw new RuntimeException('Falha ao restaurar arquivo tecnico: ' . $relative);
            }
            $copied++;
        }

        return [
            'mode' => 'local',
            'files_applied' => $copied,
            'skipped_protected' => $skippedProtected,
            'target_root' => $targetRoot,
            'target_public_root' => $this->resolveCodeDeployPublicRoot($targetRoot),
        ];
    }

    private function deployCodeFtp(array $deployConfig, string $sourceDir): array
    {
        $this->extendExecutionWindow();

        $ftp = @ftp_connect((string) $deployConfig['host'], (int) $deployConfig['port'], 60);
        if ($ftp === false) {
            throw new RuntimeException('Nao foi possivel conectar ao FTP para rollback tecnico.');
        }

        $uploaded = 0;
        try {
            if (!@ftp_login($ftp, (string) $deployConfig['username'], (string) $deployConfig['password'])) {
                throw new RuntimeException('Falha de login no FTP para rollback tecnico.');
            }
            ftp_pasv($ftp, (bool) ($deployConfig['passive'] ?? true));
            $root = $this->resolveCodeDeployRoot($ftp, (string) ($deployConfig['root'] ?? ''));

            $files = $this->listFiles($sourceDir);
            $skippedProtected = 0;
            foreach ($files as $file) {
                $this->extendExecutionWindow();

                $relative = substr(str_replace('\\', '/', $file), strlen(str_replace('\\', '/', $sourceDir)) + 1);
                $relative = ltrim($relative, '/');
                if ($relative === '' || str_contains($relative, '..')) {
                    continue;
                }
                if (!$this->shouldIncludeTechnicalRelativePath($relative)) {
                    $skippedProtected++;
                    continue;
                }

                $remotePath = $this->resolveCodeDeployPath($root, $relative);
                $this->ensureRemoteDirectory($ftp, dirname($remotePath));
                if (!@ftp_put($ftp, $remotePath, $file, FTP_BINARY)) {
                    throw new RuntimeException('Falha ao restaurar arquivo tecnico via FTP: ' . $relative);
                }
                $uploaded++;
            }
        } finally {
            ftp_close($ftp);
        }

        return [
            'mode' => 'ftp',
            'files_applied' => $uploaded,
            'skipped_protected' => $skippedProtected ?? 0,
            'target_root' => $root,
            'target_public_root' => $this->resolveCodeDeployPublicRoot($root),
        ];
    }

    private function copyFilteredTree(string $sourceRoot, string $destinationRoot): void
    {
        $normalizedSourceRoot = rtrim(str_replace('\\', '/', $sourceRoot), '/');
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
            if ($relative === '' || !$this->shouldIncludeTechnicalRelativePath($relative)) {
                continue;
            }

            $destinationPath = $destinationRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $destinationDir = dirname($destinationPath);
            if (!is_dir($destinationDir) && !mkdir($destinationDir, 0777, true) && !is_dir($destinationDir)) {
                throw new RuntimeException('Nao foi possivel criar a pasta temporaria do backup tecnico: ' . $destinationDir);
            }

            if (!copy($sourcePath, $destinationPath)) {
                throw new RuntimeException('Falha ao materializar arquivo tecnico: ' . $relative);
            }
        }
    }

    private function shouldIncludeTechnicalRelativePath(string $relativePath): bool
    {
        $normalized = strtolower(ltrim(str_replace('\\', '/', trim($relativePath)), '/'));
        if ($normalized === '' || str_contains($normalized, '..')) {
            return false;
        }

        return !in_array($normalized, self::PROTECTED_TECHNICAL_PATHS, true);
    }

    private function extendExecutionWindow(int $seconds = 900): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }
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
            throw new RuntimeException('Caminho relativo invalido no rollback tecnico: ' . $relative);
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
        $tmpPath = tempnam(sys_get_temp_dir(), 'en-tech-');
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

    private function ensureDirectoryHasContent(string $directory): void
    {
        if (!is_dir($directory)) {
            throw new RuntimeException('Diretorio nao encontrado: ' . $directory);
        }

        foreach ($this->listFiles($directory) as $_file) {
            return;
        }

        $placeholder = $directory . DIRECTORY_SEPARATOR . '.backup-empty';
        file_put_contents($placeholder, 'empty technical backup');
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
                    throw new RuntimeException('Falha ao baixar arquivo tecnico via FTP: ' . $remotePath);
                }
            }

            return;
        }

        $rawItems = @ftp_rawlist($connection, $remoteDirectory);
        if ($rawItems === false) {
            throw new RuntimeException('Nao foi possivel listar o diretorio tecnico FTP: ' . $remoteDirectory);
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
                throw new RuntimeException('Falha ao baixar arquivo tecnico via FTP: ' . $remotePath);
            }
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($directory);
    }

    private function toPowerShellLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
