<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Services\Site\BackupService;
use App\Services\Site\DropboxBackupService;
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

        if ($this->isProgressRequest()) {
            $this->progress();
            return;
        }

        if ($this->isSectionFragmentRequest()) {
            echo $this->renderSection(false);
            return;
        }

        View::render('site/backup-tools', $this->viewData());
    }

    /**
     * @return array<string,mixed>
     */
    public function viewData(bool $adminEmbed = false, ?string $section = null, ?string $baseUrl = null): array
    {
        $flash = Session::pull('backup_tools_flash');
        $lastVerification = Session::pull('backup_tools_verification');
        $activeSection = $this->normalizeSection($section);
        $historyPage = $this->normalizeHistoryPage();
        $historyPerPage = $this->normalizeHistoryPerPage();
        $cloudTab = $this->normalizeCloudTab();
        $cloudPage = $this->normalizeCloudPage();
        $cloudPerPage = $this->normalizeCloudPerPage();
        $resolvedBaseUrl = $baseUrl ?? ($adminEmbed ? url('/admin/central-operacional?aba=backup-restore') : url('/local/backup'));

        $cloudViewData = $activeSection === 'nuvem'
            ? $this->cloudService()->getPanelData($this->manager(), $cloudPage, $cloudPerPage)
            : [
                'backup_cloud' => [],
                'backup_cloud_recent_uploads' => [],
                'backup_cloud_pagination' => ['total' => 0, 'page' => 1, 'per_page' => 5, 'pages' => 1],
            ];

        return $this->service()->getViewModel(
            $activeSection,
            $historyPage,
            $historyPerPage,
            is_array($flash) ? $flash : null,
            is_array($lastVerification) ? $lastVerification : null
        ) + $cloudViewData + [
            'embed_mode' => $adminEmbed ? true : $this->embedMode(),
            'admin_embed' => $adminEmbed,
            'backup_section' => $activeSection,
            'backup_sections' => $this->sections(),
            'backup_base_url' => $resolvedBaseUrl,
            'backup_cloud_tab' => $cloudTab,
        ];
    }

    public function handle(): void
    {
        $this->ensureLocalOnly();
        $redirectTarget = $this->normalizeRedirectTarget($_POST['redirect_to'] ?? null);

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            $this->flash('error', 'Sessao expirada. Atualize a pagina e tente novamente.');
            $this->redirect($redirectTarget);
        }

        $action = strtolower(trim((string) ($_POST['action'] ?? '')));
        $service = $this->service();
        $respondJson = $this->wantsJsonResponse();
        $longCloudAction = in_array($action, ['dropbox_upload_latest', 'dropbox_upload_backup'], true);
        $longLocalAction = in_array($action, ['run', 'verify', 'restore'], true);
        $successMessage = null;

        try {
            if ($longCloudAction || $longLocalAction) {
                $this->closeSessionWrite();
            }

            switch ($action) {
                case 'run':
                    $profile = strtolower(trim((string) ($_POST['profile'] ?? 'local')));
                    $manifest = $service->run($profile, $this->normalizeProgressId($_POST['progress_id'] ?? null));
                    $cloudNotice = '';

                    if ($this->cloudService()->autoUploadEnabled()) {
                        try {
                            $cloudResult = $this->cloudService()->uploadBackup($this->manager(), (string) ($manifest['backup_id'] ?? ''));
                            $cloudNotice = sprintf(' Backup enviado para o Dropbox em %s.', (string) ($cloudResult['destination'] ?? '/'));
                        } catch (\Throwable $cloudException) {
                            $cloudNotice = ' Backup gerado, mas o envio automatico para Dropbox falhou: ' . $cloudException->getMessage();
                        }
                    }

                    $successMessage = sprintf('Backup %s concluido com sucesso.%s', (string) ($manifest['backup_id'] ?? ''), $cloudNotice);
                    break;

                case 'verify':
                    $backupId = $this->normalizeOptionalId($_POST['backup_id'] ?? 'latest');
                    $verification = $service->verify($backupId, $this->normalizeProgressId($_POST['progress_id'] ?? null));
                    Session::put('backup_tools_verification', $verification);
                    $successMessage = 'Verificacao concluida.';
                    break;

                case 'mark_uploaded':
                    $backupId = $this->normalizeOptionalId($_POST['backup_id'] ?? 'latest');
                    $backup = $service->markUploaded($backupId);
                    $successMessage = sprintf('Backup %s marcado como enviado para a nuvem.', (string) ($backup['backup_id'] ?? ''));
                    break;

                case 'restore':
                    $phrase = trim((string) ($_POST['restore_phrase'] ?? ''));
                    if (mb_strtoupper($phrase, 'UTF-8') !== 'RESTAURAR') {
                        throw new \RuntimeException('Digite RESTAURAR para confirmar a volta do backup.');
                    }

                    $backupId = $this->normalizeOptionalId($_POST['backup_id'] ?? 'latest');
                    $targetProfile = strtolower(trim((string) ($_POST['target_profile'] ?? 'local')));
                    $scope = strtolower(trim((string) ($_POST['scope'] ?? 'all')));
                    $result = $service->restore($backupId, $targetProfile, $scope, $this->normalizeProgressId($_POST['progress_id'] ?? null));
                    $successMessage = sprintf('Restore concluido do backup %s (%s).', (string) ($result['backup_id'] ?? ''), (string) ($result['scope'] ?? 'all'));
                    break;

                case 'dropbox_connect':
                    $oauthState = bin2hex(random_bytes(24));
                    Session::put('backup_tools_dropbox_oauth_state', $oauthState);
                    Session::put('backup_tools_dropbox_oauth_redirect', $redirectTarget ?? url('/local/backup?backup_secao=nuvem'));
                    header('Location: ' . $this->cloudService()->authorizationUrl($oauthState));
                    exit;

                case 'dropbox_disconnect':
                    $this->cloudService()->disconnect();
                    $successMessage = 'Conexao com Dropbox removida.';
                    break;

                case 'dropbox_auto_upload':
                    $enabled = in_array(strtolower(trim((string) ($_POST['enabled'] ?? '0'))), ['1', 'true', 'on', 'yes'], true);
                    $this->cloudService()->setAutoUpload($enabled);
                    $successMessage = $enabled
                        ? 'Envio automatico para Dropbox ativado.'
                        : 'Envio automatico para Dropbox desativado.';
                    break;

                case 'dropbox_upload_latest':
                    $cloudUpload = $this->cloudService()->uploadLatest($this->manager(), $this->normalizeProgressId($_POST['progress_id'] ?? null));
                    $successMessage = sprintf('Ultimo backup enviado ao Dropbox em %s.', (string) ($cloudUpload['destination'] ?? '/'));
                    break;

                case 'dropbox_upload_backup':
                    $backupId = $this->normalizeOptionalId($_POST['backup_id'] ?? '');
                    if ($backupId === null) {
                        throw new \RuntimeException('Selecione um backup valido para enviar ao Dropbox.');
                    }

                    $cloudUpload = $this->cloudService()->uploadBackup($this->manager(), $backupId, $this->normalizeProgressId($_POST['progress_id'] ?? null));
                    $successMessage = sprintf('Backup %s enviado ao Dropbox em %s.', $backupId, (string) ($cloudUpload['destination'] ?? '/'));
                    break;

                default:
                    throw new \RuntimeException('Acao de backup invalida.');
            }
        } catch (\Throwable $exception) {
            $this->reopenSessionWrite();
            $this->flash('error', $exception->getMessage());
            if ($respondJson) {
                $this->json([
                    'ok' => false,
                    'redirect_url' => $redirectTarget ?? url('/local/backup?backup_secao=nuvem'),
                    'message' => $exception->getMessage(),
                ], 422);
            }
            $this->redirect($redirectTarget);
        }

        $this->reopenSessionWrite();
        if ($successMessage !== null) {
            $this->flash('success', $successMessage);
        }

        if ($respondJson) {
            $this->json([
                'ok' => true,
                'redirect_url' => $redirectTarget ?? url('/local/backup?backup_secao=nuvem'),
                'message' => $successMessage ?? 'Rotina executada com sucesso.',
            ]);
        }

        $this->redirect($redirectTarget);
    }

    public function dropboxCallback(): void
    {
        $this->ensureLocalOnly();

        $redirectTarget = (string) Session::pull('backup_tools_dropbox_oauth_redirect', url('/local/backup?backup_secao=nuvem'));
        $expectedState = (string) Session::pull('backup_tools_dropbox_oauth_state', '');
        $incomingState = trim((string) ($_GET['state'] ?? ''));
        $error = trim((string) ($_GET['error'] ?? ''));
        $description = trim((string) ($_GET['error_description'] ?? ''));

        if ($error !== '') {
            $this->flash('error', 'Dropbox recusou a autorizacao: ' . ($description !== '' ? $description : $error));
            $this->redirect($redirectTarget);
        }

        if ($expectedState === '' || $incomingState === '' || !hash_equals($expectedState, $incomingState)) {
            $this->flash('error', 'Falha ao validar o retorno OAuth do Dropbox.');
            $this->redirect($redirectTarget);
        }

        $code = trim((string) ($_GET['code'] ?? ''));
        if ($code === '') {
            $this->flash('error', 'Dropbox nao retornou um codigo de autorizacao.');
            $this->redirect($redirectTarget);
        }

        try {
            $account = $this->cloudService()->completeAuthorization($code);
            $accountName = (string) ($account['account_name'] ?? 'Conta Dropbox');
            $this->flash('success', sprintf('Dropbox conectado com sucesso: %s.', $accountName));
        } catch (\Throwable $exception) {
            $this->flash('error', $exception->getMessage());
        }

        $this->redirect($redirectTarget);
    }

    public function progress(): void
    {
        $this->ensureLocalOnly();
        $progressId = $this->normalizeProgressId($_GET['id'] ?? null);
        $backupProgress = $this->manager()->getProgress($progressId);
        if (($backupProgress['status'] ?? 'idle') !== 'idle') {
            $this->json($backupProgress);
        }

        $this->json($this->cloudService()->getProgress($progressId));
    }

    private function ensureLocalOnly(): void
    {
        LocalOnlyAccess::enforce();
    }

    private function embedMode(): bool
    {
        return (string) ($_GET['embed'] ?? '0') === '1';
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function sections(): array
    {
        return [
            'resumo' => [
                'label' => 'Resumo',
                'description' => 'Estado atual e ultimos backups.',
            ],
            'acoes' => [
                'label' => 'Acoes',
                'description' => 'Execucao e verificacao rapida.',
            ],
            'restore' => [
                'label' => 'Restore',
                'description' => 'Retorno controlado de dados.',
            ],
            'historico' => [
                'label' => 'Historico',
                'description' => 'Backups recentes e verificacao.',
            ],
            'nuvem' => [
                'label' => 'Nuvem',
                'description' => 'Dropbox e automacao.',
            ],
        ];
    }

    private function normalizeSection(?string $section = null): string
    {
        $value = strtolower(trim((string) ($section ?? ($_GET['backup_secao'] ?? 'resumo'))));
        $allowed = array_keys($this->sections());

        return in_array($value, $allowed, true) ? $value : 'resumo';
    }

    private function isSectionFragmentRequest(): bool
    {
        return (string) ($_GET['backup_fragment'] ?? '0') === '1';
    }

    private function isProgressRequest(): bool
    {
        return (string) ($_GET['backup_progress'] ?? '0') === '1';
    }

    private function normalizeHistoryPage(): int
    {
        return max(1, (int) ($_GET['backup_pagina'] ?? 1));
    }

    private function normalizeHistoryPerPage(): int
    {
        $perPage = (int) ($_GET['backup_por_pagina'] ?? 5);
        if ($perPage < 5) {
            return 5;
        }

        return min($perPage, 50);
    }

    private function normalizeCloudTab(): string
    {
        $tab = strtolower(trim((string) ($_GET['cloud_tab'] ?? 'painel')));
        return in_array($tab, ['painel', 'historico'], true) ? $tab : 'painel';
    }

    private function normalizeCloudPage(): int
    {
        return max(1, (int) ($_GET['cloud_pagina'] ?? 1));
    }

    private function normalizeCloudPerPage(): int
    {
        $perPage = (int) ($_GET['cloud_por_pagina'] ?? 5);
        if ($perPage < 5) {
            return 5;
        }

        return min($perPage, 50);
    }

    public function renderSection(bool $adminEmbed): string
    {
        $data = $this->viewData(
            $adminEmbed,
            $this->normalizeSection(),
            $adminEmbed ? url('/admin/central-operacional?aba=backup-restore') : url('/local/backup')
        );

        return View::fragment('site/partials/backup-tools-content', $data);
    }

    private function service(): BackupService
    {
        return new BackupService($this->manager(), $this->backupConfig());
    }

    private function manager(): BackupManager
    {
        require_once base_path('scripts/backup/BackupManager.php');

        return new BackupManager($this->backupConfig());
    }

    /**
     * @return array<string, mixed>
     */
    private function backupConfig(): array
    {
        /** @var array<string, mixed> $config */
        $config = require base_path('config/backup.php');
        return $config;
    }

    private function cloudService(): DropboxBackupService
    {
        /** @var array<string, mixed> $config */
        $config = require base_path('config/backup-cloud.php');
        return new DropboxBackupService($config);
    }

    private function redirect(?string $target = null): void
    {
        header('Location: ' . ($target ?? url('/local/backup')));
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

    private function wantsJsonResponse(): bool
    {
        return strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))) === 'xmlhttprequest'
            || strtolower(trim((string) ($_POST['response'] ?? ''))) === 'json';
    }

    private function closeSessionWrite(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    private function reopenSessionWrite(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
