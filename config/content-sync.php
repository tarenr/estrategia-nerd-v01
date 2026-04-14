<?php

declare(strict_types=1);

$databaseConfig = require __DIR__ . '/database.php';

return [
    'package_root' => $_ENV['CONTENT_SYNC_ROOT'] ?? dirname(__DIR__) . '/storage/content-sync',
    'seven_zip_binary' => $_ENV['CONTENT_SYNC_7ZIP_BINARY'] ?? 'C:\\Program Files\\7-Zip\\7z.exe',
    'profiles' => [
        'local' => [
            'label' => 'Local / Homologação',
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
        'production' => [
            'label' => 'Produção',
            'slug' => 'producao',
            'database' => [
                'host' => (string) ($_ENV['CONTENT_SYNC_PRODUCTION_DB_HOST'] ?? ($_ENV['BACKUP_PRODUCTION_DB_HOST'] ?? '')),
                'port' => (string) ($_ENV['CONTENT_SYNC_PRODUCTION_DB_PORT'] ?? ($_ENV['BACKUP_PRODUCTION_DB_PORT'] ?? '3306')),
                'database' => (string) ($_ENV['CONTENT_SYNC_PRODUCTION_DB_DATABASE'] ?? ($_ENV['BACKUP_PRODUCTION_DB_DATABASE'] ?? '')),
                'username' => (string) ($_ENV['CONTENT_SYNC_PRODUCTION_DB_USERNAME'] ?? ($_ENV['BACKUP_PRODUCTION_DB_USERNAME'] ?? '')),
                'password' => (string) ($_ENV['CONTENT_SYNC_PRODUCTION_DB_PASSWORD'] ?? ($_ENV['BACKUP_PRODUCTION_DB_PASSWORD'] ?? '')),
            ],
            'uploads' => [
                'mode' => (string) ($_ENV['CONTENT_SYNC_PRODUCTION_UPLOAD_MODE'] ?? ($_ENV['BACKUP_PRODUCTION_UPLOAD_MODE'] ?? 'ftp')),
                'host' => (string) ($_ENV['CONTENT_SYNC_PRODUCTION_FTP_HOST'] ?? ($_ENV['BACKUP_PRODUCTION_FTP_HOST'] ?? '')),
                'port' => (int) ($_ENV['CONTENT_SYNC_PRODUCTION_FTP_PORT'] ?? ($_ENV['BACKUP_PRODUCTION_FTP_PORT'] ?? 21)),
                'username' => (string) ($_ENV['CONTENT_SYNC_PRODUCTION_FTP_USERNAME'] ?? ($_ENV['BACKUP_PRODUCTION_FTP_USERNAME'] ?? '')),
                'password' => (string) ($_ENV['CONTENT_SYNC_PRODUCTION_FTP_PASSWORD'] ?? ($_ENV['BACKUP_PRODUCTION_FTP_PASSWORD'] ?? '')),
                'root' => (string) ($_ENV['CONTENT_SYNC_PRODUCTION_FTP_ROOT'] ?? ($_ENV['BACKUP_PRODUCTION_FTP_ROOT'] ?? 'domains/estrategianerd.com.br/public_html/uploads')),
                'passive' => !in_array(strtolower((string) ($_ENV['CONTENT_SYNC_PRODUCTION_FTP_PASSIVE'] ?? ($_ENV['BACKUP_PRODUCTION_FTP_PASSIVE'] ?? 'true'))), ['0', 'false', 'off', 'no'], true),
            ],
        ],
    ],
    'public_config_keys' => [
        'bio_avatar_url',
        'bio_descricao',
        'bio_titulo',
        'brand_symbol_url',
        'descricao_site',
        'email_contato',
        'favicon_url',
        'footer_texto',
        'home_menu_sections',
        'instagram_url',
        'logo_url',
        'meta_description_padrao',
        'meta_title_padrao',
        'nome_site',
        'site_kicker',
        'site_url',
        'sobre_imagem_url',
        'telegram_url',
        'whatsapp_url',
        'youtube_url',
    ],
];