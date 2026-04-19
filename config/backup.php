<?php

declare(strict_types=1);

$databaseConfig = require __DIR__ . '/database.php';

return [
    'backup_root' => $_ENV['BACKUP_ROOT'] ?? 'D:\\Taren\\Documents\\Backup\\Estratégia Nerd',
    'retention' => max(1, (int) ($_ENV['BACKUP_RETENTION'] ?? 14)),
    'mysqldump_binary' => $_ENV['BACKUP_MYSQLDUMP_BINARY'] ?? 'C:\\xampp\\mysql\\bin\\mysqldump.exe',
    'mysql_binary' => $_ENV['BACKUP_MYSQL_BINARY'] ?? 'C:\\xampp\\mysql\\bin\\mysql.exe',
    'profiles' => [
        'local' => [
            'label' => 'Local / Homologacao',
            'slug' => 'local-homologacao',
            'database' => [
                'host' => (string) ($databaseConfig['host'] ?? ''),
                'port' => (string) ($databaseConfig['port'] ?? '3306'),
                'database' => (string) ($databaseConfig['database'] ?? ''),
                'username' => (string) ($databaseConfig['username'] ?? ''),
                'password' => (string) ($databaseConfig['password'] ?? ''),
            ],
            'uploads' => [
                'mode' => 'local',
                'path' => dirname(__DIR__) . '/public/uploads',
            ],
        ],
        'stage' => [
            'label' => (string) ($_ENV['CONTENT_SYNC_STAGE_PROFILE_LABEL'] ?? 'Stage / Homologacao remota'),
            'slug' => 'stage-homologacao',
            'database' => [
                'host' => (string) ($_ENV['CONTENT_SYNC_STAGE_DB_HOST'] ?? ''),
                'port' => (string) ($_ENV['CONTENT_SYNC_STAGE_DB_PORT'] ?? '3306'),
                'database' => (string) ($_ENV['CONTENT_SYNC_STAGE_DB_DATABASE'] ?? ''),
                'username' => (string) ($_ENV['CONTENT_SYNC_STAGE_DB_USERNAME'] ?? ''),
                'password' => (string) ($_ENV['CONTENT_SYNC_STAGE_DB_PASSWORD'] ?? ''),
            ],
            'uploads' => [
                'mode' => (string) ($_ENV['CONTENT_SYNC_STAGE_UPLOAD_MODE'] ?? 'ftp'),
                'host' => (string) ($_ENV['CONTENT_SYNC_STAGE_FTP_HOST'] ?? ''),
                'port' => (int) ($_ENV['CONTENT_SYNC_STAGE_FTP_PORT'] ?? 21),
                'username' => (string) ($_ENV['CONTENT_SYNC_STAGE_FTP_USERNAME'] ?? ''),
                'password' => (string) ($_ENV['CONTENT_SYNC_STAGE_FTP_PASSWORD'] ?? ''),
                'root' => (string) ($_ENV['CONTENT_SYNC_STAGE_FTP_ROOT'] ?? ''),
                'passive' => !in_array(strtolower((string) ($_ENV['CONTENT_SYNC_STAGE_FTP_PASSIVE'] ?? 'true')), ['0', 'false', 'off', 'no'], true),
            ],
        ],
        'production' => [
            'label' => 'Producao',
            'slug' => 'producao',
            'database' => [
                'host' => (string) ($_ENV['BACKUP_PRODUCTION_DB_HOST'] ?? ''),
                'port' => (string) ($_ENV['BACKUP_PRODUCTION_DB_PORT'] ?? '3306'),
                'database' => (string) ($_ENV['BACKUP_PRODUCTION_DB_DATABASE'] ?? ''),
                'username' => (string) ($_ENV['BACKUP_PRODUCTION_DB_USERNAME'] ?? ''),
                'password' => (string) ($_ENV['BACKUP_PRODUCTION_DB_PASSWORD'] ?? ''),
            ],
            'uploads' => [
                'mode' => (string) ($_ENV['BACKUP_PRODUCTION_UPLOAD_MODE'] ?? 'ftp'),
                'host' => (string) ($_ENV['BACKUP_PRODUCTION_FTP_HOST'] ?? ''),
                'port' => (int) ($_ENV['BACKUP_PRODUCTION_FTP_PORT'] ?? 21),
                'username' => (string) ($_ENV['BACKUP_PRODUCTION_FTP_USERNAME'] ?? ''),
                'password' => (string) ($_ENV['BACKUP_PRODUCTION_FTP_PASSWORD'] ?? ''),
                'root' => (string) ($_ENV['BACKUP_PRODUCTION_FTP_ROOT'] ?? ''),
                'passive' => !in_array(strtolower((string) ($_ENV['BACKUP_PRODUCTION_FTP_PASSIVE'] ?? 'true')), ['0', 'false', 'off', 'no'], true),
            ],
        ],
    ],
];
