<?php

declare(strict_types=1);

namespace App\Services\Site;

use Scripts\ContentSync\ContentSyncManager;
use Scripts\Deploy\DeployManager;
use Scripts\Operations\OperationLogger;

final class CentralOperacionalService
{
    public function __construct(
        private BackupService $backupService,
        private ContentSyncManager $contentManager,
        private DeployManager $deployManager,
        private OperationLogger $operationLogger,
        private ?SmokeTestService $smokeTestService = null
    ) {
    }

    public function getViewModel(?array $flash = null, string $overviewSection = 'resumo'): array
    {
        return [
            'title' => 'Central Operacional | EstratÃ©gia Nerd',
            'meta_description' => 'Painel local com visÃ£o consolidada de backup, deploy tÃ©cnico, conteÃºdo e polÃ­tica operacional.',
            'site_chrome' => false,
            'body_class' => 'central-operacional-body',
            'flash' => is_array($flash) ? $flash : null,
            'operations_status' => $this->buildOverviewStatus($overviewSection),
        ];
    }

    public function runTechnicalBackup(string $profileName): array
    {
        return $this->deployManager->backupTecnico($profileName);
    }

    public function runDataBackup(string $profileName): array
    {
        return $this->backupService->run($profileName);
    }

    public function verifyDataBackup(string $backupId): array
    {
        return $this->backupService->verify($backupId);
    }

    public function exportContent(string $profileName): array
    {
        return $this->contentManager->export($profileName);
    }

    public function verifyContentPackage(string $packageId): array
    {
        return $this->contentManager->verify($packageId);
    }

    public function exportCodePackage(?string $notes = null): array
    {
        return $this->contentManager->exportCode($notes);
    }

    public function applyContentPackage(string $packageId, string $targetProfile): array
    {
        $preApplyBackup = $this->backupService->run($targetProfile);
        $preApplyBackupId = (string) ($preApplyBackup['backup_id'] ?? '');

        $this->operationLogger->write(
            'backup_pre_conteudo',
            $targetProfile,
            $targetProfile,
            $preApplyBackupId,
            'OK',
            'Backup preventivo criado antes de aplicar pacote de conteudo.',
            ['package_id' => $packageId]
        );

        $result = $this->contentManager->apply($packageId, $targetProfile, true);
        $result['pre_apply_backup_id'] = $preApplyBackupId;

        return $result;
    }

    public function applyCodePackage(string $packageId, string $targetProfile): array
    {
        $preApplyBackup = $this->deployManager->backupTecnico($targetProfile);
        $preApplyBackupId = (string) ($preApplyBackup['backup_id'] ?? '');

        $this->operationLogger->write(
            'backup_pre_deploy_tecnico',
            $targetProfile,
            $targetProfile,
            $preApplyBackupId,
            'OK',
            'Backup tecnico preventivo criado antes de aplicar pacote tecnico.',
            ['package_id' => $packageId]
        );

        $result = $this->deployManager->applyCode($packageId, $targetProfile, true);
        $result['pre_apply_backup_id'] = $preApplyBackupId;

        return $result;
    }

    public function restoreData(string $backupId, string $targetProfile, string $scope): array
    {
        return $this->backupService->restore($backupId, $targetProfile, $scope);
    }

    public function rollbackTechnical(string $backupId, string $targetProfile): array
    {
        return $this->deployManager->rollbackTecnico($targetProfile, $backupId, true);
    }

    public function runSmokeTest(string $environment): array
    {
        return $this->smokeTests()->run($environment);
    }

    private function latestTechnicalBackup(array $items): ?array
    {
        if ($items === []) {
            return null;
        }

        $latest = $items[0] ?? null;
        if (!is_array($latest)) {
            return null;
        }

        return [
            'backup_id' => (string) ($latest['backup_id'] ?? ''),
            'created_at' => (string) ($latest['created_at'] ?? ''),
            'files_count' => (int) ($latest['files_count'] ?? 0),
            'status' => (string) ($latest['status'] ?? ''),
            'profile_label' => (string) ($latest['profile_label'] ?? ''),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function presentTechnicalBackups(array $items): array
    {
        $presented = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $presented[] = [
                'backup_id' => (string) ($item['backup_id'] ?? ''),
                'created_at' => (string) ($item['created_at'] ?? ''),
                'files_count' => (int) ($item['files_count'] ?? 0),
                'status' => (string) ($item['status'] ?? ''),
                'profile_label' => (string) ($item['profile_label'] ?? ''),
            ];
        }

        return $presented;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function presentContentPackages(array $items): array
    {
        $presented = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $sourceProfile = strtolower(trim((string) ($item['source_profile'] ?? '')));
            if (!in_array($sourceProfile, ['stage', 'production'], true)) {
                continue;
            }

            $presented[] = [
                'package_id' => (string) ($item['package_id'] ?? ''),
                'source_profile' => $sourceProfile,
                'source_profile_label' => (string) ($item['source_profile_label'] ?? ''),
                'created_at' => (string) ($item['created_at'] ?? ''),
                'status' => (string) ($item['status'] ?? ''),
                'is_valid' => (bool) ($item['is_valid'] ?? false),
                'allowed_targets' => array_values((array) ($item['allowed_targets'] ?? [])),
                'applied_targets' => array_values((array) ($item['applied_targets'] ?? [])),
            ];
        }

        return $presented;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupBackupsByProfile(array $items): array
    {
        $groups = [
            'local' => [],
            'stage' => [],
            'production' => [],
        ];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $profile = strtolower(trim((string) ($item['profile'] ?? '')));
            if (!array_key_exists($profile, $groups)) {
                continue;
            }

            $groups[$profile][] = $item;
        }

        return $groups;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOverviewStatus(string $overviewSection): array
    {
        return match (strtolower(trim($overviewSection))) {
            'backups' => $this->buildBackupsStatus(),
            'pacotes' => $this->buildPackagesStatus(),
            'testes' => $this->buildSmokeTestsStatus(),
            'historico' => $this->buildHistoryStatus(),
            default => $this->buildSummaryStatus(),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSummaryStatus(): array
    {
        return [
            'policy' => $this->deployManager->deploymentPolicyStatus(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBackupsStatus(): array
    {
        $backupViewModel = $this->backupService->getViewModel();
        $backupStatus = (array) ($backupViewModel['backup_status'] ?? []);
        $technicalStatus = $this->deployManager->technicalBackupStatus();

        return [
            'backup' => [
                'root' => (string) ($backupStatus['backup_root'] ?? ''),
                'total' => (int) ($backupStatus['total_backups'] ?? 0),
                'latest' => is_array($backupStatus['latest'] ?? null) ? $backupStatus['latest'] : null,
                'latest_uploaded' => is_array($backupStatus['latest_uploaded'] ?? null) ? $backupStatus['latest_uploaded'] : null,
                'running' => is_array($backupStatus['running'] ?? null) ? $backupStatus['running'] : null,
                'items' => array_values((array) ($backupStatus['items'] ?? [])),
                'items_by_profile' => $this->groupBackupsByProfile(array_values((array) ($backupStatus['items'] ?? []))),
                'local_ready' => $this->backupService->profileReady('local'),
                'stage_ready' => $this->backupService->profileReady('stage'),
                'production_ready' => $this->backupService->profileReady('production'),
            ],
            'technical_backup' => [
                'root' => (string) ($technicalStatus['backup_root'] ?? ''),
                'local_latest' => $this->latestTechnicalBackup((array) ($technicalStatus['profiles']['local'] ?? [])),
                'stage_latest' => $this->latestTechnicalBackup((array) ($technicalStatus['profiles']['stage'] ?? [])),
                'production_latest' => $this->latestTechnicalBackup((array) ($technicalStatus['profiles']['production'] ?? [])),
                'local_ready' => $this->deployManager->profileReady('local'),
                'stage_ready' => $this->deployManager->profileReady('stage'),
                'production_ready' => $this->deployManager->profileReady('production'),
                'profiles' => [
                    'local' => $this->presentTechnicalBackups((array) ($technicalStatus['profiles']['local'] ?? [])),
                    'stage' => $this->presentTechnicalBackups((array) ($technicalStatus['profiles']['stage'] ?? [])),
                    'production' => $this->presentTechnicalBackups((array) ($technicalStatus['profiles']['production'] ?? [])),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPackagesStatus(): array
    {
        $contentStatus = $this->contentManager->status();
        $contentPackages = $this->presentContentPackages((array) ($contentStatus['items'] ?? []));
        $parityStatus = $this->contentManager->parityStatus();
        $codeStatus = $this->deployManager->codeStatus();

        return [
            'content' => [
                'root' => (string) ($contentStatus['package_root'] ?? ''),
                'total' => count($contentPackages),
                'latest' => $contentPackages[0] ?? null,
                'latest_stage_apply' => is_array($contentStatus['latest_stage_apply'] ?? null) ? $contentStatus['latest_stage_apply'] : null,
                'latest_production_apply' => is_array($contentStatus['latest_production_apply'] ?? null) ? $contentStatus['latest_production_apply'] : null,
                'items' => $contentPackages,
                'stage_ready' => $this->contentManager->profileReady('stage'),
                'production_ready' => $this->contentManager->profileReady('production'),
            ],
            'code' => [
                'root' => (string) ($codeStatus['package_root'] ?? ''),
                'total' => (int) ($codeStatus['total_packages'] ?? 0),
                'latest' => is_array($codeStatus['latest'] ?? null) ? $codeStatus['latest'] : null,
                'latest_stage_apply' => is_array($codeStatus['latest_stage_apply'] ?? null) ? $codeStatus['latest_stage_apply'] : null,
                'latest_production_apply' => is_array($codeStatus['latest_production_apply'] ?? null) ? $codeStatus['latest_production_apply'] : null,
                'items' => array_values((array) ($codeStatus['items'] ?? [])),
                'stage_ready' => $this->deployManager->profileReady('stage'),
                'production_ready' => $this->deployManager->profileReady('production'),
            ],
            'parity' => $parityStatus,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHistoryStatus(): array
    {
        return [
            'logs' => [
                'categories' => [
                    'dados' => [
                        'label' => 'Backup de dados',
                        'latest_file' => $this->operationLogger->latestLogFile('dados'),
                        'entries' => $this->presentOperationEntries($this->operationLogger->recentEntries(5, 'dados')),
                    ],
                    'tecnico' => [
                        'label' => 'Backup tecnico',
                        'latest_file' => $this->operationLogger->latestLogFile('tecnico'),
                        'entries' => $this->presentOperationEntries($this->operationLogger->recentEntries(5, 'tecnico')),
                    ],
                    'conteudo' => [
                        'label' => 'Conteudo e deploy',
                        'latest_file' => $this->operationLogger->latestLogFile('conteudo'),
                        'entries' => $this->presentOperationEntries($this->operationLogger->recentEntries(5, 'conteudo')),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSmokeTestsStatus(): array
    {
        return [
            'smoke_tests' => $this->smokeTests()->viewModel(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function presentOperationEntries(array $entries): array
    {
        $presented = [];

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $context = is_array($entry['context'] ?? null) ? $entry['context'] : [];

            $presented[] = [
                'timestamp' => (string) ($entry['timestamp'] ?? ''),
                'type' => (string) ($entry['tipo'] ?? ''),
                'origin' => (string) ($entry['origem'] ?? ''),
                'destination' => (string) ($entry['destino'] ?? ''),
                'identifier' => (string) ($entry['id'] ?? ''),
                'status' => (string) ($entry['status'] ?? ''),
                'message' => (string) ($entry['msg'] ?? 'Sem resumo adicional.'),
                'context' => $context,
            ];
        }

        return $presented;
    }

    private function smokeTests(): SmokeTestService
    {
        if ($this->smokeTestService instanceof SmokeTestService) {
            return $this->smokeTestService;
        }

        $this->smokeTestService = new SmokeTestService(require base_path('config/smoke-tests.php'));

        return $this->smokeTestService;
    }
}
