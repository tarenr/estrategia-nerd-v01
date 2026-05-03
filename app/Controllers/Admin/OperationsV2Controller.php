<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Site\BackupToolsController;
use App\Controllers\Site\ContentSyncToolsController;
use App\Services\Admin\Presenters\OperationsV2Presenter;
use App\Services\Site\DropboxBackupService;
use App\Support\Csrf;
use App\Support\Session;
use App\Support\View;
use Scripts\ContentSync\ContentSyncManager;

final class OperationsV2Controller
{
    public function index(): void
    {
        $overview = (new OperationsV2Presenter())->overview();

        if ((string) ($_GET['fragment'] ?? '0') === '1') {
            header('Content-Type: text/html; charset=UTF-8');
            echo View::fragment('admin/operations-v2/panel', [
                'overview' => $overview,
            ]);
            return;
        }

        View::render('admin/operations-v2/index', [
            'title' => 'Central Operacional | Estrategia Nerd',
            'planned_modules' => $this->modules(),
            'overview' => $overview,
        ]);
    }

    public function backupSistemico(): void
    {
        $module = $this->modules()['backup-sistemico'];
        $section = is_string($_GET['backup_secao'] ?? null) ? (string) $_GET['backup_secao'] : 'resumo';
        if ($section === 'nuvem') {
            $this->redirect('/admin/central-operacional-v2/backup-em-nuvem');
            return;
        }

        $backupTools = (new BackupToolsController())->viewData(
            true,
            $section,
            url('/admin/central-operacional-v2/backup-sistemico')
        );
        if (is_array($backupTools['backup_sections'] ?? null)) {
            unset($backupTools['backup_sections']['nuvem']);
        }

        if ((string) ($_GET['backup_fragment'] ?? '0') === '1') {
            header('Content-Type: text/html; charset=UTF-8');
            echo View::fragment('site/partials/backup-tools-content', $backupTools);
            return;
        }

        View::render('admin/operations-v2/backup-sistemico', [
            'title' => ($module['label'] ?? 'Backup Sistêmico e Restore') . ' | Estrategia Nerd',
            'module' => $module,
            'backup_tools' => $backupTools,
        ]);
    }

    public function backupEditorial(): void
    {
        $module = $this->modules()['backup-editorial'];
        $section = is_string($_GET['editorial_secao'] ?? null) ? (string) $_GET['editorial_secao'] : 'resumo';
        $allowedSections = ['resumo', 'acoes', 'restore', 'historico'];
        if (!in_array($section, $allowedSections, true)) {
            $section = 'resumo';
        }

        $contentTools = (new ContentSyncToolsController())->viewData(
            true,
            'editorial',
            url('/admin/central-operacional-v2/backup-editorial')
        );
        $contentTools['editorial_section'] = $section;
        $contentTools['editorial_sections'] = [
            'resumo' => ['label' => 'Resumo'],
            'acoes' => ['label' => 'Acoes'],
            'restore' => ['label' => 'Restore'],
            'historico' => ['label' => 'Historico'],
        ];
        $contentTools['editorial_base_url'] = url('/admin/central-operacional-v2/backup-editorial');
        $contentTools['module'] = $module;

        if ((string) ($_GET['editorial_fragment'] ?? '0') === '1') {
            header('Content-Type: text/html; charset=UTF-8');
            echo View::fragment('admin/operations-v2/partials/backup-editorial-content', $contentTools);
            return;
        }

        View::render('admin/operations-v2/backup-editorial', [
            'title' => ($module['label'] ?? 'Backup Editorial e Restore') . ' | Estrategia Nerd',
            'module' => $module,
            'content_tools' => $contentTools,
        ]);
    }

    public function backupEmNuvem(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleCloudAction();
            return;
        }

        $module = $this->modules()['backup-nuvem'];
        $backupTools = (new BackupToolsController())->viewData(
            true,
            'nuvem',
            url('/admin/central-operacional-v2/backup-em-nuvem')
        );

        View::render('admin/operations-v2/backup-nuvem', [
            'title' => ($module['label'] ?? 'Backup em Nuvem') . ' | Estrategia Nerd',
            'module' => $module,
            'backup_tools' => $backupTools,
            'editorial_cloud' => $this->cloudService()->getEditorialPanelData($this->contentManager()),
            'cloud_flash' => Session::pull('operations_v2_cloud_flash'),
        ]);
    }

    public function observabilidade(): void
    {
        $module = $this->modules()['observabilidade'];
        $observability = (new OperationsV2Presenter())->observability();

        View::render('admin/operations-v2/observabilidade', [
            'title' => ($module['label'] ?? 'Observabilidade') . ' | Estrategia Nerd',
            'module' => $module,
            'observability' => $observability,
        ]);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function modules(): array
    {
        return [
            'visao-geral' => [
                'label' => 'Visão Geral',
                'description' => 'Entrada executiva da Central Operacional.',
                'status' => 'em-leitura',
            ],
            'backup-sistemico' => [
                'label' => 'Backup Sistêmico e Restore',
                'description' => 'Leitura organizada para a rotina sistêmica atual.',
                'status' => 'planejado',
                'href' => url('/admin/central-operacional-v2/backup-sistemico'),
            ],
            'backup-editorial' => [
                'label' => 'Backup Editorial e Restore',
                'description' => 'Leitura organizada para pacotes e sincronização editorial atual.',
                'status' => 'planejado',
                'href' => url('/admin/central-operacional-v2/backup-editorial'),
            ],
            'backup-nuvem' => [
                'label' => 'Backup em Nuvem',
                'description' => 'Dropbox para backups sistêmicos e editoriais.',
                'status' => 'em-leitura',
                'href' => url('/admin/central-operacional-v2/backup-em-nuvem'),
            ],
            'observabilidade' => [
                'label' => 'Observabilidade',
                'description' => 'Leitura organizada para logs, histórico, alertas e sinais operacionais.',
                'status' => 'planejado',
                'href' => url('/admin/central-operacional-v2/observabilidade'),
            ],
        ];
    }

    private function showModule(string $moduleKey): void
    {
        $modules = $this->modules();
        $module = $modules[$moduleKey] ?? null;

        if ($module === null) {
            $this->redirect('/admin/central-operacional-v2');
            return;
        }

        View::render('admin/operations-v2/show', [
            'title' => ($module['label'] ?? 'Central Operacional') . ' | Estrategia Nerd',
            'module' => $module,
            'module_key' => $moduleKey,
        ]);
    }

    private function handleCloudAction(): void
    {
        $redirect = $this->normalizeRedirectTarget($_POST['redirect_to'] ?? null) ?? url('/admin/central-operacional-v2/backup-em-nuvem');
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            $this->cloudFlash('error', 'Sessão expirada. Atualize a página e tente novamente.');
            $this->redirect($redirect);
            return;
        }

        $action = strtolower(trim((string) ($_POST['action'] ?? '')));

        try {
            if ($action === 'dropbox_editorial_auto_upload') {
                $enabled = in_array(strtolower(trim((string) ($_POST['enabled'] ?? '0'))), ['1', 'true', 'on', 'yes'], true);
                $this->cloudService()->setEditorialAutoUpload($enabled);
                $this->cloudFlash('success', $enabled
                    ? 'Envio automÃ¡tico editorial ativado.'
                    : 'Envio automÃ¡tico editorial desativado.'
                );
                $this->redirect($redirect);
                return;
            }

            if ($action === 'dropbox_upload_editorial_latest') {
                $result = $this->cloudService()->uploadLatestEditorial($this->contentManager(), $this->normalizeProgressId($_POST['progress_id'] ?? null));
            } elseif ($action === 'dropbox_upload_editorial_package') {
                $packageId = trim((string) ($_POST['package_id'] ?? ''));
                if ($packageId === '') {
                    throw new \RuntimeException('Selecione um pacote editorial para enviar.');
                }

                $result = $this->cloudService()->uploadEditorialPackage($this->contentManager(), $packageId, $this->normalizeProgressId($_POST['progress_id'] ?? null));
            } elseif ($action === 'dropbox_delete_editorial_package') {
                $packageId = trim((string) ($_POST['package_id'] ?? ''));
                if ($packageId === '') {
                    throw new \RuntimeException('Selecione um pacote editorial para remover do Dropbox.');
                }

                $result = $this->cloudService()->deleteEditorialPackage(
                    $this->contentManager(),
                    $packageId,
                    (string) ($_POST['delete_confirmation'] ?? '')
                );
            } else {
                throw new \RuntimeException('Ação inválida para Backup em Nuvem.');
            }

            if ($action === 'dropbox_delete_editorial_package') {
                $this->cloudFlash('success', sprintf(
                    'Pacote editorial %s removido do Dropbox em %s.',
                    (string) ($result['package_id'] ?? ''),
                    (string) ($result['destination'] ?? '/')
                ));
            } else {
                $this->cloudFlash('success', sprintf(
                    'Pacote editorial %s enviado ao Dropbox em %s.',
                    (string) ($result['package_id'] ?? ''),
                    (string) ($result['destination'] ?? '/')
                ));
            }
        } catch (\Throwable $exception) {
            $this->cloudFlash('error', $exception->getMessage());
        }

        $this->redirect($redirect);
    }

    private function cloudFlash(string $type, string $message): void
    {
        Session::put('operations_v2_cloud_flash', [
            'type' => $type,
            'message' => $message,
        ]);
    }

    private function cloudService(): DropboxBackupService
    {
        return new DropboxBackupService(require base_path('config/backup-cloud.php'));
    }

    private function contentManager(): ContentSyncManager
    {
        require_once base_path('scripts/content-sync/ContentSyncManager.php');

        return new ContentSyncManager(require base_path('config/content-sync.php'));
    }

    private function redirect(string $target): void
    {
        header('Location: ' . $target);
        exit;
    }

    private function normalizeRedirectTarget(mixed $target): ?string
    {
        $value = trim((string) $target);
        if ($value === '' || !str_starts_with($value, '/')) {
            return null;
        }

        return $value;
    }

    private function normalizeProgressId(mixed $value): ?string
    {
        $progressId = strtolower(trim((string) $value));
        if ($progressId === '' || !preg_match('/^[a-z0-9_-]{8,80}$/', $progressId)) {
            return null;
        }

        return $progressId;
    }
}
