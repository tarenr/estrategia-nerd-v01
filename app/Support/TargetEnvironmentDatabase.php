<?php
declare(strict_types=1);

namespace App\Support;

use PDO;
use RuntimeException;

final class TargetEnvironmentDatabase
{
    /**
     * @var array<string, PDO>
     */
    private static array $connections = [];

    public static function pdo(?string $targetEnvironment = null): PDO
    {
        $target = EnvironmentManager::normalize($targetEnvironment ?? EnvironmentManager::target());

        if ($target === 'local' && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            return $GLOBALS['pdo'];
        }

        if (isset(self::$connections[$target])) {
            return self::$connections[$target];
        }

        $profile = self::profile($target);
        $database = (array) ($profile['database'] ?? []);

        foreach (['host', 'port', 'database', 'username'] as $required) {
            if (trim((string) ($database[$required] ?? '')) === '') {
                throw new RuntimeException('Configuracao de banco incompleta para o ambiente ' . $target . ': faltando ' . $required . '.');
            }
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            (string) $database['host'],
            (string) $database['port'],
            (string) $database['database']
        );

        self::$connections[$target] = new PDO(
            $dsn,
            (string) $database['username'],
            (string) ($database['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return self::$connections[$target];
    }

    /**
     * @return array<string, mixed>
     */
    public static function profile(string $targetEnvironment): array
    {
        $target = EnvironmentManager::normalize($targetEnvironment);
        $profiles = (array) config('content_sync.profiles', []);
        $profile = (array) ($profiles[$target] ?? []);

        if ($profile === []) {
            throw new RuntimeException('Perfil de ambiente nao encontrado para ' . $target . '.');
        }

        return $profile;
    }
}
