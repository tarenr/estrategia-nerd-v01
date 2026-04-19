<?php

declare(strict_types=1);

$backupConfig = require __DIR__ . '/backup.php';
$contentSyncConfig = require __DIR__ . '/content-sync.php';

return [
    'backup_root' => (string) ($backupConfig['backup_root'] ?? ''),
    'profiles' => (array) ($contentSyncConfig['profiles'] ?? []),
    'deployment_policy' => (array) ($contentSyncConfig['deployment_policy'] ?? []),
    'code_package_root' => (string) ($contentSyncConfig['code_package_root'] ?? ''),
    'content_sync' => $contentSyncConfig,
];
