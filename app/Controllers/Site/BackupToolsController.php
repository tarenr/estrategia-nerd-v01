<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Services\Site\BackupService;
use App\Support\Csrf;
use App\Support\LocalOnlyAccess;
use App\Support\Session;
use App\Support\View;
use Scripts\Backup\BackupManager;

final class BackupToolsController
{
    public function index(): void
    {
        $this->ensureLocalOnly();

        View::render('site/backup-tools', $this->viewData());
    }

    /**
     * @return array<string,mixed>
     */
    public function viewData(bool $adminEmbed = false): array
    {
        $flash = Session::pull('backup_tools_flash');
        $lastVerification = Session::pull('backup_tools_verification');

        return $this->service()->getViewModel(
            is_array($flash) ? $flash : null,
            is_array($lastVerification) ? $lastVerification : null
        ) + [
            'embed_mode' => $adminEmbed ? true : $this->embedMode(),
            'admin_embed' => $adminEmbed,
        ];
    }

    public function handle(): void
    {
        $this->ensureLocalOnly();

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            $this->flash('error', 'Sessão expirada. Atualize a página e tente novamente.');
            $this->redirect();
        }

        $action = strtolower(trim((string) ($_POST['action'] ?? '')));
        $service = $this->service();

        try {
            switch ($action) {
                case 'run':
                    $profile = strtolower(trim((string) ($_POST['profile'] ?? 'local')));
                    $manifest = $service->run($profile);
                    $this->flash('success', sprintf('Backup %s concluído com sucesso.', (string) ($manifest['backup_id'] ?? '')));
                    break;

                case 'verify':
                    $backupId = $this->normalizeOptionalId($_POST['backup_id'] ?? 'latest');
                    $verification = $service->verify($backupId);
                    Session::put('backup_tools_verification', $verification);
                    $this->flash('success', 'Verificação concluída.');
                    break;

                case 'mark_uploaded':
                    $backupId = $this->normalizeOptionalId($_POST['backup_id'] ?? 'latest');
                    $backup = $service->markUploaded($backupId);
                    $this->flash('success', sprintf('Backup %s marcado como enviado para a nuvem.', (string) ($backup['backup_id'] ?? '')));
                    break;

                case 'restore':
                    $phrase = trim((string) ($_POST['restore_phrase'] ?? ''));
                    if (mb_strtoupper($phrase, 'UTF-8') !== 'RESTAURAR') {
                        throw new \RuntimeException('Digite RESTAURAR para confirmar a volta do backup.');
                    }

                    $backupId = $this->normalizeOptionalId($_POST['backup_id'] ?? 'latest');
                    $targetProfile = strtolower(trim((string) ($_POST['target_profile'] ?? 'local')));
                    $scope = strtolower(trim((string) ($_POST['scope'] ?? 'all')));
                    $result = $service->restore($backupId, $targetProfile, $scope);
                    $this->flash('success', sprintf('Restore concluído do backup %s (%s).', (string) ($result['backup_id'] ?? ''), (string) ($result['scope'] ?? 'all')));
                    break;

                default:
                    throw new \RuntimeException('Ação de backup inválida.');
            }
        } catch (\Throwable $exception) {
            $this->flash('error', $exception->getMessage());
        }

        $this->redirect();
    }

    private function ensureLocalOnly(): void
    {
        LocalOnlyAccess::enforce();
    }

    private function embedMode(): bool
    {
        return (string) ($_GET['embed'] ?? '0') === '1';
    }

    private function service(): BackupService
    {
        require_once base_path('scripts/backup/BackupManager.php');
        $config = require base_path('config/backup.php');

        return new BackupService(new BackupManager($config), $config);
    }

    private function redirect(): void
    {
        header('Location: ' . url('/local/backup'));
        exit;
    }

    private function flash(string $type, string $message): void
    {
        Session::put('backup_tools_flash', [
            'type' => $type,
            'message' => $message,
        ]);
    }

    private function normalizeOptionalId(mixed $backupId): ?string
    {
        $value = trim((string) $backupId);
        if ($value === '' || strtolower($value) === 'latest') {
            return null;
        }

        return $value;
    }
}
