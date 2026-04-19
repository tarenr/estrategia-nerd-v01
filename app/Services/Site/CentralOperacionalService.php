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
        private OperationLogger $operationLogger
    ) {
    }

    public function getViewModel(?array $flash = null): array
    {
        $backupViewModel = $this->backupService->getViewModel();
        $backupStatus = (array) ($backupViewModel['backup_status'] ?? []);
        $contentStatus = $this->contentManager->status();
        $contentPackages = $this->presentContentPackages((array) ($contentStatus['items'] ?? []));
        $parityStatus = $this->contentManager->parityStatus();
        $deploymentPolicy = $this->deployManager->deploymentPolicyStatus();
        $codeStatus = $this->deployManager->codeStatus();
        $technicalStatus = $this->deployManager->technicalBackupStatus();
        $logCategories = [
            'dados' => [
                'label' => 'Backup de dados',
                'entries' => $this->operationLogger->recentEntries(8, 'dados'),
                'latest_file' => $this->operationLogger->latestLogFile('dados'),
            ],
            'tecnico' => [
                'label' => 'Backup tecnico',
                'entries' => $this->operationLogger->recentEntries(8, 'tecnico'),
                'latest_file' => $this->operationLogger->latestLogFile('tecnico'),
            ],
            'conteudo' => [
                'label' => 'Pacote de conteudo',
                'entries' => $this->operationLogger->recentEntries(8, 'conteudo'),
                'latest_file' => $this->operationLogger->latestLogFile('conteudo'),
            ],
        ];

        return [
            'title' => 'Central Operacional | Estratégia Nerd',
            'meta_description' => 'Painel local com visão consolidada de backup, deploy técnico, conteúdo e política operacional.',
            'site_chrome' => false,
            'body_class' => 'central-operacional-body',
            'flash' => is_array($flash) ? $flash : null,
            'operations_status' => [
                'policy' => $deploymentPolicy,
                'backup' => [
                    'root' => (string) ($backupStatus['backup_root'] ?? ''),
                    'total' => (int) ($backupStatus['total_backups'] ?? 0),
                    'latest' => is_array($backupStatus['latest'] ?? null) ? $backupStatus['latest'] : null,
                    'latest_uploaded' => is_array($backupStatus['latest_uploaded'] ?? null) ? $backupStatus['latest_uploaded'] : null,
                    'running' => is_array($backupStatus['running'] ?? null) ? $backupStatus['running'] : null,
                    'local_ready' => $this->backupService->profileReady('local'),
                    'stage_ready' => $this->backupService->profileReady('stage'),
                    'production_ready' => $this->backupService->profileReady('production'),
                ],
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
                    'stage_ready' => $this->deployManager->profileReady('stage'),
                    'production_ready' => $this->deployManager->profileReady('production'),
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
                'logs' => [
                    'categories' => [
                        'dados' => [
                            'label' => (string) ($logCategories['dados']['label'] ?? 'Backup de dados'),
                            'latest_file' => $logCategories['dados']['latest_file'] ?? null,
                            'entries' => $logCategories['dados']['entries'] ?? [],
                            'total_loaded' => count((array) ($logCategories['dados']['entries'] ?? [])),
                        ],
                        'tecnico' => [
                            'label' => (string) ($logCategories['tecnico']['label'] ?? 'Backup tecnico'),
                            'latest_file' => $logCategories['tecnico']['latest_file'] ?? null,
                            'entries' => $logCategories['tecnico']['entries'] ?? [],
                            'total_loaded' => count((array) ($logCategories['tecnico']['entries'] ?? [])),
                        ],
                        'conteudo' => [
                            'label' => (string) ($logCategories['conteudo']['label'] ?? 'Pacote de conteudo'),
                            'latest_file' => $logCategories['conteudo']['latest_file'] ?? null,
                            'entries' => $logCategories['conteudo']['entries'] ?? [],
                            'total_loaded' => count((array) ($logCategories['conteudo']['entries'] ?? [])),
                        ],
                    ],
                ],
                'parity' => $parityStatus,
            ],
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

    public function verifyLatestDataBackup(): array
    {
        return $this->backupService->verify(null);
    }

    public function exportContent(string $profileName): array
    {
        return $this->contentManager->export($profileName);
    }

    public function verifyLatestContent(): array
    {
        return $this->contentManager->verify(null);
    }

    public function exportCodePackage(?string $notes = null): array
    {
        return $this->contentManager->exportCode($notes);
    }

    public function applyContentPackage(string $packageId, string $targetProfile): array
    {
        return $this->contentManager->apply($packageId, $targetProfile, true);
    }

    public function applyLatestCode(string $targetProfile): array
    {
        return $this->deployManager->applyCode(null, $targetProfile, true);
    }

    public function restoreLatestData(string $targetProfile, string $scope): array
    {
        return $this->backupService->restore(null, $targetProfile, $scope);
    }

    public function rollbackLatestTechnical(string $targetProfile): array
    {
        return $this->deployManager->rollbackTecnico($targetProfile, null, true);
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
}
