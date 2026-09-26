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
        'editorial_remote_root' => (string) ($_ENV['BACKUP_DROPBOX_EDITORIAL_REMOTE_ROOT'] ?? '/Estrategia Nerd/backups-editoriais'),
        'scopes' => [
            'files.content.write',
            'files.content.read',
            'files.metadata.write',
            'account_info.read',
        ],
    ],
    // So cobre conteudo editorial (midia/uploads) - banco/sistema fica com a
    // rotina local (restic+rclone) do The Forge, nao com este provedor.
    'google_drive' => [
        'client_id' => (string) ($_ENV['BACKUP_GOOGLE_DRIVE_CLIENT_ID'] ?? ''),
        'client_secret' => (string) ($_ENV['BACKUP_GOOGLE_DRIVE_CLIENT_SECRET'] ?? ''),
        'redirect_uri' => (string) ($_ENV['BACKUP_GOOGLE_DRIVE_REDIRECT_URI'] ?? url('/local/backup/google-drive/callback')),
        'editorial_root_folder_name' => (string) ($_ENV['BACKUP_GOOGLE_DRIVE_EDITORIAL_FOLDER'] ?? 'Estrategia Nerd - Backups de Conteudo'),
        'state_path' => base_path('storage/app/backup-cloud/google-drive-connection.json'),
        'progress_path' => base_path('storage/app/backup-cloud/progress'),
        'chunk_size' => 8 * 1024 * 1024,
        'scopes' => [
            'https://www.googleapis.com/auth/drive.file',
        ],
    ],
];
