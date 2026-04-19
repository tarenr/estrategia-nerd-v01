<?php

declare(strict_types=1);

$databaseConfig = require __DIR__ . '/database.php';

$productionUploadsRoot = (string) ($_ENV['CONTENT_SYNC_PRODUCTION_FTP_ROOT'] ?? ($_ENV['BACKUP_PRODUCTION_FTP_ROOT'] ?? ''));
$productionCodeRootDefault = (string) preg_replace('~/uploads/?$~i', '', $productionUploadsRoot);
if ($productionCodeRootDefault === '' || $productionCodeRootDefault === $productionUploadsRoot) {
    $productionCodeRootDefault = '';
}

$stageUploadsRoot = (string) ($_ENV['CONTENT_SYNC_STAGE_FTP_ROOT'] ?? '');
$stageCodeRootDefault = (string) preg_replace('~/uploads/?$~i', '', $stageUploadsRoot);
if ($stageCodeRootDefault === '' || $stageCodeRootDefault === $stageUploadsRoot) {
    $stageCodeRootDefault = '';
}

return [
    'package_root' => $_ENV['CONTENT_SYNC_ROOT'] ?? ($_ENV['BACKUP_ROOT'] ?? 'D:\\Taren\\Documents\\Backup\\Estratégia Nerd'),
    'legacy_package_root' => dirname(__DIR__) . '/storage/content-sync',
    'code_package_root' => $_ENV['CONTENT_SYNC_CODE_ROOT'] ?? (($_ENV['BACKUP_ROOT'] ?? 'D:\\Taren\\Documents\\Backup\\Estratégia Nerd') . '\\01-local\\codigo'),
    'legacy_code_package_root' => dirname(__DIR__) . '/storage/code-sync',
    'seven_zip_binary' => $_ENV['CONTENT_SYNC_7ZIP_BINARY'] ?? 'C:\\Program Files\\7-Zip\\7z.exe',
    'deployment_policy' => [
        'current_source' => strtolower(trim((string) ($_ENV['CONTENT_SYNC_CURRENT_SOURCE'] ?? 'local'))),
        'approved_source' => strtolower(trim((string) ($_ENV['CONTENT_SYNC_APPROVED_PACKAGE_SOURCE'] ?? 'stage'))),
        'stage_label' => trim((string) ($_ENV['CONTENT_SYNC_STAGE_LABEL'] ?? 'estrategia-nerd-stage')),
    ],
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
            'code_deploy' => [
                'mode' => 'local',
                'root' => dirname(__DIR__),
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
            'code_deploy' => [
                'mode' => (string) ($_ENV['CONTENT_SYNC_STAGE_CODE_MODE'] ?? 'ftp'),
                'host' => (string) ($_ENV['CONTENT_SYNC_STAGE_CODE_FTP_HOST'] ?? ($_ENV['CONTENT_SYNC_STAGE_FTP_HOST'] ?? '')),
                'port' => (int) ($_ENV['CONTENT_SYNC_STAGE_CODE_FTP_PORT'] ?? ($_ENV['CONTENT_SYNC_STAGE_FTP_PORT'] ?? 21)),
                'username' => (string) ($_ENV['CONTENT_SYNC_STAGE_CODE_FTP_USERNAME'] ?? ($_ENV['CONTENT_SYNC_STAGE_FTP_USERNAME'] ?? '')),
                'password' => (string) ($_ENV['CONTENT_SYNC_STAGE_CODE_FTP_PASSWORD'] ?? ($_ENV['CONTENT_SYNC_STAGE_FTP_PASSWORD'] ?? '')),
                'root' => (string) ($_ENV['CONTENT_SYNC_STAGE_CODE_FTP_ROOT'] ?? $stageCodeRootDefault),
                'passive' => !in_array(strtolower((string) ($_ENV['CONTENT_SYNC_STAGE_CODE_FTP_PASSIVE'] ?? ($_ENV['CONTENT_SYNC_STAGE_FTP_PASSIVE'] ?? 'true'))), ['0', 'false', 'off', 'no'], true),
            ],
        ],
        'production' => [
            'label' => 'Producao',
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
                'root' => (string) ($_ENV['CONTENT_SYNC_PRODUCTION_FTP_ROOT'] ?? ($_ENV['BACKUP_PRODUCTION_FTP_ROOT'] ?? '')),
                'passive' => !in_array(strtolower((string) ($_ENV['CONTENT_SYNC_PRODUCTION_FTP_PASSIVE'] ?? ($_ENV['BACKUP_PRODUCTION_FTP_PASSIVE'] ?? 'true'))), ['0', 'false', 'off', 'no'], true),
            ],
            'code_deploy' => [
                'mode' => (string) ($_ENV['CONTENT_SYNC_PRODUCTION_CODE_MODE'] ?? 'ftp'),
                'host' => (string) ($_ENV['CONTENT_SYNC_PRODUCTION_CODE_FTP_HOST'] ?? ($_ENV['CONTENT_SYNC_PRODUCTION_FTP_HOST'] ?? ($_ENV['BACKUP_PRODUCTION_FTP_HOST'] ?? ''))),
                'port' => (int) ($_ENV['CONTENT_SYNC_PRODUCTION_CODE_FTP_PORT'] ?? ($_ENV['CONTENT_SYNC_PRODUCTION_FTP_PORT'] ?? ($_ENV['BACKUP_PRODUCTION_FTP_PORT'] ?? 21))),
                'username' => (string) ($_ENV['CONTENT_SYNC_PRODUCTION_CODE_FTP_USERNAME'] ?? ($_ENV['CONTENT_SYNC_PRODUCTION_FTP_USERNAME'] ?? ($_ENV['BACKUP_PRODUCTION_FTP_USERNAME'] ?? ''))),
                'password' => (string) ($_ENV['CONTENT_SYNC_PRODUCTION_CODE_FTP_PASSWORD'] ?? ($_ENV['CONTENT_SYNC_PRODUCTION_FTP_PASSWORD'] ?? ($_ENV['BACKUP_PRODUCTION_FTP_PASSWORD'] ?? ''))),
                'root' => (string) ($_ENV['CONTENT_SYNC_PRODUCTION_CODE_FTP_ROOT'] ?? $productionCodeRootDefault),
                'passive' => !in_array(strtolower((string) ($_ENV['CONTENT_SYNC_PRODUCTION_CODE_FTP_PASSIVE'] ?? ($_ENV['CONTENT_SYNC_PRODUCTION_FTP_PASSIVE'] ?? ($_ENV['BACKUP_PRODUCTION_FTP_PASSIVE'] ?? 'true')))), ['0', 'false', 'off', 'no'], true),
            ],
        ],
    ],
    'public_config_keys' => [
        'bio_avatar_url','bio_descricao','bio_titulo','brand_symbol_url','descricao_site','email_contato','favicon_url','footer_texto','home_menu_sections','instagram_url','logo_url','meta_description_padrao','meta_title_padrao','nome_site','site_kicker','site_url','sobre_imagem_url','telegram_url','whatsapp_url','youtube_url',
    ],
];
