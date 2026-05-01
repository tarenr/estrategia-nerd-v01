<?php

declare(strict_types=1);

return [
    'provider' => 'dropbox',
    'state_path' => base_path('storage/app/backup-cloud/dropbox-connection.json'),
    'progress_path' => base_path('storage/app/backup-cloud/progress'),
    'chunk_size' => 4 * 1024 * 1024,
    'dropbox' => [
        'client_id' => (string) ($_ENV['BACKUP_DROPBOX_APP_KEY'] ?? ''),
        'client_secret' => (string) ($_ENV['BACKUP_DROPBOX_APP_SECRET'] ?? ''),
        'redirect_uri' => (string) ($_ENV['BACKUP_DROPBOX_REDIRECT_URI'] ?? url('/local/backup/dropbox/callback')),
        'remote_root' => (string) ($_ENV['BACKUP_DROPBOX_REMOTE_ROOT'] ?? '/Estrategia Nerd/backups-ambiente'),
        'scopes' => [
            'files.content.write',
            'files.content.read',
            'files.metadata.write',
            'account_info.read',
        ],
    ],
];
