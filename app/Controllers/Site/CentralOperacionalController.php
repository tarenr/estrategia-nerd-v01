<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Services\Site\BackupService;
use App\Services\Site\CentralOperacionalService;
use App\Support\Csrf;
use App\Support\LocalOnlyAccess;
use App\Support\Session;
use App\Support\View;
use Scripts\Backup\BackupManager;
use Scripts\ContentSync\ContentSyncManager;
use Scripts\Deploy\DeployManager;
use Scripts\Operations\OperationLogger;

final class CentralOperacionalController
{
    public function index(): void
    {
        $this->ensureLocalOnly();

        $flash = Session::pull('operations_flash');

        View::render('site/central-operacional', $this->service()->getViewModel(
            is_array($flash) ? $flash : null
        ));
    }

    public function handle(): void
    {
        $this->ensureLocalOnly();

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            $this->flash('error', 'Sessao expirada. Atualize a pagina e tente novamente.');
            $this->redirect();
        }

        $action = strtolower(trim((string) ($_POST['action'] ?? '')));

        try {
            switch ($action) {
                case 'backup_dados':
                    $profile = strtolower(trim((string) ($_POST['profile'] ?? '')));
                    if (!in_array($profile, ['local', 'stage', 'production'], true)) {
                        throw new \RuntimeException('Perfil invalido para backup de dados.');
                    }

                    $manifest = $this->service()->runDataBackup($profile);
                    $this->flash('success', sprintf('Backup de dados %s criado com sucesso para %s.', (string) ($manifest['backup_id'] ?? ''), $profile));
                    break;

                case 'verify_backup_dados':
                    $backupId = trim((string) ($_POST['backup_id'] ?? ''));
                    if ($backupId === '' || strtolower($backupId) === 'latest') {
                        throw new \RuntimeException('Selecione o backup exato para verificar.');
                    }

                    $verification = $this->service()->verifyDataBackup($backupId);
                    $this->flash('success', sprintf('Verificacao concluida para o backup %s.', (string) ($verification['backup_id'] ?? $backupId)));
                    break;

                case 'export_content':
                    $profile = strtolower(trim((string) ($_POST['profile'] ?? 'stage')));
                    if (!in_array($profile, ['stage', 'production'], true)) {
                        throw new \RuntimeException('Perfil invalido para exportacao de conteudo.');
                    }

                    $manifest = $this->service()->exportContent($profile);
                    $this->flash('success', sprintf('Pacote de conteudo %s gerado com sucesso.', (string) ($manifest['package_id'] ?? '')));
                    break;

                case 'verify_content':
                    $packageId = trim((string) ($_POST['package_id'] ?? ''));
                    if ($packageId === '' || strtolower($packageId) === 'latest') {
                        throw new \RuntimeException('Selecione o pacote de conteudo exato para verificar.');
                    }

                    $verification = $this->service()->verifyContentPackage($packageId);
                    $this->flash('success', sprintf('Verificacao concluida para o pacote %s.', (string) ($verification['package_id'] ?? $packageId)));
                    break;

                case 'apply_content':
                    $packageId = trim((string) ($_POST['package_id'] ?? ''));
                    if ($packageId === '' || strtolower($packageId) === 'latest') {
                        throw new \RuntimeException('Selecione um pacote de conteudo para aplicar.');
                    }

                    $targetProfile = strtolower(trim((string) ($_POST['target_profile'] ?? 'local')));
                    if (!in_array($targetProfile, ['local', 'stage', 'production'], true)) {
                        throw new \RuntimeException('Destino invalido para publicacao de conteudo.');
                    }

                    $phrase = trim((string) ($_POST['apply_phrase'] ?? ''));
                    $this->assertOperationPhrase($phrase, $targetProfile, $packageId, 'PUBLICAR', 'conteudo');

                    $result = $this->service()->applyContentPackage($packageId, $targetProfile);
                    $this->flash('success', sprintf(
                        'Pacote %s aplicado em %s. Backup preventivo: %s.',
                        (string) ($result['package_id'] ?? ''),
                        (string) ($result['target_profile'] ?? ''),
                        (string) ($result['pre_apply_backup_id'] ?? '-')
                    ));
                    break;

                case 'backup_tecnico':
                    $profile = strtolower(trim((string) ($_POST['profile'] ?? '')));
                    if (!in_array($profile, ['local', 'stage', 'production'], true)) {
                        throw new \RuntimeException('Perfil invalido para backup tecnico.');
                    }

                    $manifest = $this->service()->runTechnicalBackup($profile);
                    $this->flash('success', sprintf('Backup tecnico %s criado com sucesso para %s.', (string) ($manifest['backup_id'] ?? ''), $profile));
                    break;

                case 'export_code':
                    $notes = trim((string) ($_POST['code_notes'] ?? ''));
                    $manifest = $this->service()->exportCodePackage($notes !== '' ? $notes : null);
                    $this->flash('success', sprintf('Pacote tecnico %s gerado com sucesso (%d arquivos).', (string) ($manifest['package_id'] ?? ''), (int) ($manifest['files_count'] ?? 0)));
                    break;

                case 'apply_code':
                    $packageId = trim((string) ($_POST['package_id'] ?? ''));
                    if ($packageId === '' || strtolower($packageId) === 'latest') {
                        throw new \RuntimeException('Selecione o pacote tecnico exato para publicar.');
                    }

                    $targetProfile = strtolower(trim((string) ($_POST['target_profile'] ?? 'stage')));
                    if (!in_array($targetProfile, ['stage', 'production'], true)) {
                        throw new \RuntimeException('Destino invalido para deploy tecnico.');
                    }

                    $phrase = trim((string) ($_POST['apply_phrase'] ?? ''));
                    $this->assertOperationPhrase($phrase, $targetProfile, $packageId, 'PUBLICAR', 'codigo');

                    $result = $this->service()->applyCodePackage($packageId, $targetProfile);
                    $this->flash('success', sprintf(
                        'Pacote tecnico %s aplicado em %s (%d arquivos). Backup tecnico preventivo: %s.',
                        (string) ($result['package_id'] ?? ''),
                        (string) ($result['target_profile'] ?? ''),
                        (int) ($result['result']['files_applied'] ?? 0),
                        (string) ($result['pre_apply_backup_id'] ?? '-')
                    ));
                    break;

                case 'restore_data':
                    $backupId = trim((string) ($_POST['backup_id'] ?? ''));
                    if ($backupId === '' || strtolower($backupId) === 'latest') {
                        throw new \RuntimeException('Selecione o backup exato para executar o restore.');
                    }

                    $targetProfile = strtolower(trim((string) ($_POST['target_profile'] ?? 'local')));
                    if (!in_array($targetProfile, ['local', 'stage', 'production'], true)) {
                        throw new \RuntimeException('Destino invalido para restore de dados.');
                    }

                    $scope = strtolower(trim((string) ($_POST['scope'] ?? 'all')));
                    if (!in_array($scope, ['all', 'database', 'uploads'], true)) {
                        throw new \RuntimeException('Escopo invalido para restore de dados.');
                    }

                    $phrase = trim((string) ($_POST['restore_phrase'] ?? ''));
                    $this->assertOperationPhrase($phrase, $targetProfile, $backupId, 'CONFIRMAR', 'restore');

                    $result = $this->service()->restoreData($backupId, $targetProfile, $scope);
                    $this->flash('success', sprintf(
                        'Restore do backup %s executado em %s (%s).',
                        (string) ($result['backup_id'] ?? ''),
                        (string) ($result['target_profile'] ?? ''),
                        strtoupper((string) ($result['scope'] ?? 'all'))
                    ));
                    break;

                case 'rollback_technical':
                    $backupId = trim((string) ($_POST['backup_id'] ?? ''));
                    if ($backupId === '' || strtolower($backupId) === 'latest') {
                        throw new \RuntimeException('Selecione o snapshot tecnico exato para executar o rollback.');
                    }

                    $targetProfile = strtolower(trim((string) ($_POST['target_profile'] ?? 'stage')));
                    if (!in_array($targetProfile, ['local', 'stage', 'production'], true)) {
                        throw new \RuntimeException('Destino invalido para rollback tecnico.');
                    }

                    $phrase = trim((string) ($_POST['rollback_phrase'] ?? ''));
                    $this->assertOperationPhrase($phrase, $targetProfile, $backupId, 'CONFIRMAR', 'rollback');

                    $result = $this->service()->rollbackTechnical($backupId, $targetProfile);
                    $this->flash('success', sprintf(
                        'Rollback tecnico %s executado em %s (%d arquivos).',
                        (string) ($result['backup_id'] ?? ''),
                        (string) ($result['target_profile'] ?? ''),
                        (int) ($result['result']['files_applied'] ?? 0)
                    ));
                    break;

                default:
                    throw new \RuntimeException('Acao invalida na central operacional.');
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

    private function assertOperationPhrase(string $phrase, string $targetProfile, string $expectedId, string $defaultPhrase, string $operationLabel): void
    {
        if ($targetProfile === 'production') {
            if ($phrase !== $expectedId) {
                throw new \RuntimeException(sprintf(
                    'Para executar %s em producao, digite exatamente o ID: %s',
                    $operationLabel,
                    $expectedId
                ));
            }

            return;
        }

        if (mb_strtoupper($phrase, 'UTF-8') !== $defaultPhrase) {
            throw new \RuntimeException(sprintf('Digite %s para confirmar a operacao.', $defaultPhrase));
        }
    }

    private function service(): CentralOperacionalService
    {
        require_once base_path('scripts/backup/BackupManager.php');
        require_once base_path('scripts/content-sync/ContentSyncManager.php');
        require_once base_path('scripts/deploy/DeployManager.php');
        require_once base_path('scripts/operations/OperationLogger.php');

        $backupConfig = require base_path('config/backup.php');
        $contentConfig = require base_path('config/content-sync.php');
        $deployConfig = require base_path('config/deploy.php');

        return new CentralOperacionalService(
            new BackupService(new BackupManager($backupConfig), $backupConfig),
            new ContentSyncManager($contentConfig),
            new DeployManager($deployConfig),
            new OperationLogger((string) ($backupConfig['backup_root'] ?? ''))
        );
    }

    private function redirect(): void
    {
        header('Location: ' . url('/local/operacoes'));
        exit;
    }

    private function flash(string $type, string $message): void
    {
        Session::put('operations_flash', [
            'type' => $type,
            'message' => $message,
        ]);
    }
}
