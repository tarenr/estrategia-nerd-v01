<?php

declare(strict_types=1);

return [
    // Unico provedor de nuvem dos backups do Estrategia Nerd (IMP-023/IMP-033):
    // backup sistemico (banco, uploads e sistema de local/stage/producao) e
    // pacotes editoriais. O Dropbox foi removido no IMP-033.
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
