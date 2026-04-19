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

    private OperationLogger $logger;

    public function __construct(private array $config)
    {
        date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo');
        $this->logger = new OperationLogger($this->operationRoot());
    }

    public function run(string $profileName = 'local'): array
    {
        $profile = $this->profile($profileName);
        $operationRoot = $this->backupRoot();
        $backupRoot = $this->profileBackupRoot($profileName);
        $lock = $this->acquireRunLock($operationRoot, $profileName, (string) ($profile['label'] ?? $profileName));
        $profileSlug = trim((string) ($profile['slug'] ?? $profileName));
        $backupId = $this->buildBackupId('BD', $profileName);
        $backupDir = $backupRoot . DIRECTORY_SEPARATOR . $backupId;

        if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
            $this->releaseRunLock($lock);
            throw new RuntimeException('Nao foi possivel criar a pasta do backup: ' . $backupDir);
        }

        $manifest = [
            'backup_id' => $backupId,
            'profile' => $profileName,
            'profile_label' => (string) ($profile['label'] ?? $profileName),
            'profile_slug' => $profileSlug,
            'created_at' => date('c'),
            'status' => 'running',
            'cloud_uploaded' => false,
            'cloud_uploaded_at' => null,
            'database' => null,
            'uploads' => null,
            'error' => null,
        ];

        $temporaryDirectory = '';

        try {
            $databasePath = $backupDir . DIRECTORY_SEPARATOR . 'database.sql';
            $this->backupDatabase((array) ($profile['database'] ?? []), $databasePath);
            $manifest['database'] = $this->fileDetails($databasePath, 'database.sql');

            $uploadsZipPath = $backupDir . DIRECTORY_SEPARATOR . 'uploads.zip';
            $temporaryDirectory = $this->materializeUploads((array) ($profile['uploads'] ?? []));
            $this->compressDirectory($temporaryDirectory, $uploadsZipPath);
            $manifest['uploads'] = $this->fileDetails($uploadsZipPath, 'uploads.zip');

            $manifest['status'] = 'ready';
            $manifest['verified_at'] = date('c');
            $this->writeManifest($backupDir, $manifest);
            $this->applyRetention($profileName);
            $this->logOperation('backup_dados', $profileName, $profileName, (string) $backupId, 'OK', 'Backup de dados concluido.');

            return $manifest;
        } catch (\Throwable $exception) {
            $manifest['status'] = 'failed';
            $manifest['error'] = $exception->getMessage();
            $this->writeManifest($backupDir, $manifest);
            $this->logOperation('backup_dados', $profileName, $profileName, (string) $backupId, 'FAIL', $exception->getMessage());
            throw $exception;
        } finally {
            if ($temporaryDirectory !== '' && str_contains($temporaryDirectory, sys_get_temp_dir())) {
                $this->removeDirectory($temporaryDirectory);
            }

            $this->releaseRunLock($lock);
        }
    }

    public function status(): array
    {
        $backupRoot = $this->backupRoot();
        $items = $this->allBackups();
        $latest = $items[0] ?? null;
        $latestUploaded = null;

        foreach ($items as $item) {
            if (($item['cloud_uploaded'] ?? false) === true) {
                $latestUploaded = $item;
                break;
            }
        }

        return [
            'backup_root' => $backupRoot,
            'total_backups' => count($items),
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

    public function verify(?string $backupId = null): array
    {
        $backup = $this->backupById($backupId);
        if ($backup === null) {
            throw new RuntimeException('Nenhum backup encontrado para verificar.');
        }

        $backup['database_verification'] = $this->verifyEntry((array) ($backup['database'] ?? []), (string) $backup['_dir']);
        $backup['uploads_verification'] = $this->verifyEntry((array) ($backup['uploads'] ?? []), (string) $backup['_dir']);
        $backup['is_valid'] = ($backup['database_verification']['valid'] ?? false) && ($backup['uploads_verification']['valid'] ?? false);
        $this->logOperation('backup_verificacao', (string) ($backup['profile'] ?? 'local'), (string) ($backup['profile'] ?? 'local'), (string) ($backup['backup_id'] ?? ''), ($backup['is_valid'] ?? false) ? 'OK' : 'FAIL', 'Verificacao de backup executada.');

        return $backup;
    }

    public function restore(?string $backupId, string $targetProfile = 'local', string $scope = 'all', bool $force = false): array
    {
        if (!$force) {
            throw new RuntimeException('Restore exige confirmacao explicita. Use o comando com --force.');
        }

        $backup = $this->backupById($backupId);
        if ($backup === null) {
            throw new RuntimeException('Nenhum backup encontrado para restaurar.');
        }

        $profile = $this->profile($targetProfile);
        $scope = strtolower(trim($scope));
        if (!in_array($scope, ['all', 'database', 'uploads'], true)) {
            throw new RuntimeException('Escopo de restore invalido. Use all, database ou uploads.');
        }

        $restored = [];
        if ($scope === 'all' || $scope === 'database') {
            $databaseFile = (string) (($backup['database']['name'] ?? '') ?: 'database.sql');
            $this->restoreDatabase((array) ($profile['database'] ?? []), (string) $backup['_dir'] . DIRECTORY_SEPARATOR . $databaseFile);
            $restored[] = 'database';
        }

        if ($scope === 'all' || $scope === 'uploads') {
            $uploadsFile = (string) (($backup['uploads']['name'] ?? '') ?: 'uploads.zip');
            $zipPath = (string) $backup['_dir'] . DIRECTORY_SEPARATOR . $uploadsFile;
            $tmpDirectory = $this->extractArchive($zipPath);
            try {
                $this->restoreUploads((array) ($profile['uploads'] ?? []), $tmpDirectory);
            } finally {
                $this->removeDirectory($tmpDirectory);
            }
            $restored[] = 'uploads';
        }

        $result = [
            'backup_id' => $backup['backup_id'] ?? null,
            'target_profile' => $targetProfile,
            'scope' => $scope,
            'restored' => $restored,
            'restored_at' => date('c'),
        ];

        $this->logOperation('restore_dados', (string) ($backup['profile'] ?? 'local'), $targetProfile, (string) ($backup['backup_id'] ?? ''), 'OK', 'Restore de dados executado.', [
            'scope' => $scope,
        ]);

        return $result;
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

        if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
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
        if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
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
        unset($manifest['_dir'], $manifest['database_verification'], $manifest['uploads_verification'], $manifest['verification'], $manifest['is_valid']);
        file_put_contents(
            $backupDir . DIRECTORY_SEPARATOR . 'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function allBackups(): array
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
            $manifest['verification'] = [
                'database' => $this->verifyEntry((array) ($manifest['database'] ?? []), $directory),
                'uploads' => $this->verifyEntry((array) ($manifest['uploads'] ?? []), $directory),
            ];
            $manifest['is_valid'] =
                (bool) ($manifest['verification']['database']['valid'] ?? false)
                && (bool) ($manifest['verification']['uploads']['valid'] ?? false);

            $items[] = $manifest;
        }

        usort($items, static function (array $left, array $right): int {
            $leftTime = strtotime((string) ($left['created_at'] ?? '')) ?: 0;
            $rightTime = strtotime((string) ($right['created_at'] ?? '')) ?: 0;

            return $rightTime <=> $leftTime;
        });

        return $items;
    }

    private function backupById(?string $backupId): ?array
    {
        $items = $this->allBackups();
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

    private function applyRetention(string $profileName): void
    {
        $keep = max(1, (int) ($this->config['retention'] ?? 14));
        $items = $this->backupsBySearchRoot($this->profileBackupRoot($profileName));
        $itemsToRemove = array_slice($items, $keep);

        foreach ($itemsToRemove as $item) {
            $directory = (string) ($item['_dir'] ?? '');
            if ($directory !== '') {
                $this->removeDirectory($directory);
            }
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
    private function backupsBySearchRoot(string $root): array
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
            $manifest['verification'] = [
                'database' => $this->verifyEntry((array) ($manifest['database'] ?? []), $directory),
                'uploads' => $this->verifyEntry((array) ($manifest['uploads'] ?? []), $directory),
            ];
            $manifest['is_valid'] =
                (bool) ($manifest['verification']['database']['valid'] ?? false)
                && (bool) ($manifest['verification']['uploads']['valid'] ?? false);

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
}
