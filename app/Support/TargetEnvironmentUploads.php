<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class TargetEnvironmentUploads
{
    public static function isRemote(string $targetEnvironment): bool
    {
        return EnvironmentManager::normalize($targetEnvironment) !== EnvironmentManager::current();
    }

    /**
     * Envia um arquivo local para o ambiente-alvo. Quando o alvo e o
     * proprio ambiente de execucao, nao faz nada (o chamador ja grava em
     * disco local normalmente).
     */
    public static function putFile(string $targetEnvironment, string $localAbsolutePath, string $relativePath): bool
    {
        if (!self::isRemote($targetEnvironment)) {
            return true;
        }

        $uploads = self::uploadsProfile($targetEnvironment);
        if ((string) ($uploads['mode'] ?? 'ftp') !== 'ftp') {
            throw new RuntimeException('Modo de upload nao suportado para o ambiente ' . $targetEnvironment . '.');
        }

        $connection = self::connect($uploads);
        try {
            $remotePath = self::remotePath($uploads, $relativePath);
            self::ensureRemoteDir($connection, dirname($remotePath));
            if (!@ftp_put($connection, $remotePath, $localAbsolutePath, FTP_BINARY)) {
                throw new RuntimeException('Falha ao enviar arquivo via FTP para ' . $targetEnvironment . '.');
            }

            return true;
        } finally {
            @ftp_close($connection);
        }
    }

    public static function deleteFile(string $targetEnvironment, string $relativePath): bool
    {
        if (!self::isRemote($targetEnvironment)) {
            return true;
        }

        $uploads = self::uploadsProfile($targetEnvironment);
        $connection = self::connect($uploads);
        try {
            $remotePath = self::remotePath($uploads, $relativePath);
            return @ftp_delete($connection, $remotePath);
        } finally {
            @ftp_close($connection);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function uploadsProfile(string $targetEnvironment): array
    {
        $profile = TargetEnvironmentDatabase::profile($targetEnvironment);
        $uploads = (array) ($profile['uploads'] ?? []);
        if ($uploads === []) {
            throw new RuntimeException('Configuracao de uploads nao encontrada para o ambiente ' . $targetEnvironment . '.');
        }

        return $uploads;
    }

    /**
     * @param array<string, mixed> $uploads
     * @return resource
     */
    private static function connect(array $uploads)
    {
        $host = (string) ($uploads['host'] ?? '');
        $port = (int) ($uploads['port'] ?? 21);
        if ($host === '') {
            throw new RuntimeException('FTP de uploads nao configurado para este ambiente. Preencha as variaveis *_FTP_* no .env.');
        }

        $connection = @ftp_connect($host, $port, 15);
        if ($connection === false) {
            throw new RuntimeException('Nao foi possivel conectar ao FTP de uploads configurado (' . $host . ':' . $port . ').');
        }

        $username = (string) ($uploads['username'] ?? '');
        $password = (string) ($uploads['password'] ?? '');
        if (!@ftp_login($connection, $username, $password)) {
            @ftp_close($connection);
            throw new RuntimeException('Falha na autenticacao FTP de uploads.');
        }

        @ftp_pasv($connection, ($uploads['passive'] ?? true) !== false);

        return $connection;
    }

    /**
     * @param array<string, mixed> $uploads
     */
    private static function remotePath(array $uploads, string $relativePath): string
    {
        $root = trim((string) ($uploads['root'] ?? ''), '/');
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        if (str_starts_with($relativePath, 'uploads/')) {
            $relativePath = substr($relativePath, strlen('uploads/'));
        }

        // Sempre absoluto: ensureRemoteDir() navega via ftp_chdir (o que muda
        // o diretorio atual da conexao), entao um caminho relativo aqui
        // resolveria a partir do lugar errado depois desse side effect.
        return '/' . $root . '/' . $relativePath;
    }

    /**
     * @param resource $connection
     */
    private static function ensureRemoteDir($connection, string $remoteDir): void
    {
        $remoteDir = trim($remoteDir, '/');
        if ($remoteDir === '' || $remoteDir === '.') {
            return;
        }

        $path = '';
        foreach (explode('/', $remoteDir) as $part) {
            if ($part === '') {
                continue;
            }
            $path .= '/' . $part;
            if (@ftp_chdir($connection, $path)) {
                continue;
            }
            @ftp_mkdir($connection, $path);
        }
    }
}
