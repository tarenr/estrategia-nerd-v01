<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\Admin\ContentSyncAdminService;
use App\Services\Site\BackupService;
use App\Support\Csrf;
use App\Support\Session;
use App\Support\View;
use Scripts\Backup\BackupManager;
use Scripts\ContentSync\ContentSyncManager;

final class ContentSyncController
{
    public function index(): void
    {
        $flash = Session::pull('admin_content_sync_flash');
        $lastRun = Session::pull('admin_content_sync_result');

        View::render('admin/operations/index', $this->service()->getIndexViewModel(
            is_array($flash) ? $flash : null,
            is_array($lastRun) ? $lastRun : null
        ));
    }

    public function syncProductionToStage(): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token CSRF invalido.';
            return;
        }

        try {
            $result = $this->service()->syncProductionToStage((string) ($_POST['confirmation_phrase'] ?? ''));
            Session::put('admin_content_sync_flash', [
                'type' => 'success',
                'message' => sprintf(
                    'Sincronizacao concluida. Pacote %s aplicado na stage com backup preventivo %s.',
                    (string) ($result['package_id'] ?? ''),
                    (string) ($result['pre_apply_backup_id'] ?? '-')
                ),
            ]);
            Session::put('admin_content_sync_result', $result);
        } catch (\Throwable $exception) {
            Session::put('admin_content_sync_flash', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        header('Location: ' . url('/admin/central-operacional'));
        exit;
    }

    private function service(): ContentSyncAdminService
    {
        require_once base_path('scripts/backup/BackupManager.php');
        require_once base_path('scripts/content-sync/ContentSyncManager.php');

        $backupConfig = require base_path('config/backup.php');
        $contentConfig = require base_path('config/content-sync.php');

        return new ContentSyncAdminService(
            new BackupService(new BackupManager($backupConfig), $backupConfig),
            new ContentSyncManager($contentConfig),
        );
    }
}
