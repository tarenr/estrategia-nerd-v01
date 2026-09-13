<?php
declare(strict_types=1);

namespace App\Services\Admin;

use App\Services\Site\BackupService;
use App\Support\Auth;
use App\Support\SystemActivityLogger;
use RuntimeException;
use Scripts\ContentSync\ContentSyncManager;

final class ContentSyncAdminService
{
    public const CONFIRMATION_PHRASE = 'SINCRONIZAR STAGE';

    public function __construct(
        private BackupService $backupService,
        private ContentSyncManager $contentManager
    ) {
    }

    /**
     * @param array<string, mixed>|null $flash
     * @param array<string, mixed>|null $lastRun
     * @return array<string, mixed>
     */
    public function getIndexViewModel(?array $flash = null, ?array $lastRun = null): array
    {
        $status = $this->contentManager->status();
        $packages = $this->presentProductionPackages((array) ($status['items'] ?? []));

        return [
            'title' => 'Central Operacional',
            'flash' => $flash,
            'last_run' => $lastRun,
            'requires_confirmation_phrase' => self::CONFIRMATION_PHRASE,
            'sync_status' => [
                'stage_ready' => $this->backupService->profileReady('stage') && $this->contentManager->profileReady('stage'),
                'production_ready' => $this->contentManager->profileReady('production'),
                'latest_stage_apply' => is_array($status['latest_stage_apply'] ?? null) ? $status['latest_stage_apply'] : null,
                'latest_production_apply' => is_array($status['latest_production_apply'] ?? null) ? $status['latest_production_apply'] : null,
                'production_packages' => $packages,
                'package_root' => (string) ($status['package_root'] ?? ''),
                'total_production_packages' => count($packages),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function syncProductionToStage(string $confirmationPhrase): array
    {
        if (trim(mb_strtoupper($confirmationPhrase, 'UTF-8')) !== self::CONFIRMATION_PHRASE) {
            throw new RuntimeException('Digite SINCRONIZAR STAGE para confirmar a operacao.');
        }

        if (!$this->contentManager->profileReady('production')) {
            throw new RuntimeException('O perfil de conteudo da producao nao esta pronto para exportacao.');
        }

        if (!$this->contentManager->profileReady('stage')) {
            throw new RuntimeException('O perfil de conteudo da stage nao esta pronto para aplicacao.');
        }

        if (!$this->backupService->profileReady('stage')) {
            throw new RuntimeException('O perfil de backup da stage nao esta pronto para o backup preventivo.');
        }

        $operationId = $this->buildOperationId();
        $beforeStatus = $this->contentManager->status();
        $beforeStagePackage = (string) (($beforeStatus['latest_stage_apply']['package_id'] ?? ''));
        $beforeStageAppliedAt = (string) (($beforeStatus['latest_stage_apply']['applied_at'] ?? ''));
        $user = Auth::user() ?? [];
        $packageId = '';
        $backupId = '';

        try {
            $manifest = $this->contentManager->export('production');
            $packageId = (string) ($manifest['package_id'] ?? '');

            $verification = $this->contentManager->verify($packageId);
            if (!(bool) ($verification['is_valid'] ?? false)) {
                throw new RuntimeException('O pacote exportado da producao nao passou na verificacao antes da aplicacao.');
            }

            $backup = $this->backupService->run('stage');
            $backupId = (string) ($backup['backup_id'] ?? '');

            $apply = $this->contentManager->apply($packageId, 'stage', true);
            $afterStatus = $this->contentManager->status();

            $result = [
                'operation_id' => $operationId,
                'package_id' => $packageId,
                'pre_apply_backup_id' => $backupId,
                'verification' => [
                    'is_valid' => (bool) ($verification['is_valid'] ?? false),
                    'stats' => (array) ($verification['stats'] ?? []),
                    'uploads_included' => (int) ($verification['uploads']['included_files'] ?? 0),
                ],
                'apply' => [
                    'target_profile' => (string) ($apply['target_profile'] ?? 'stage'),
                    'applied_at' => (string) (($apply['apply_record']['applied_at'] ?? $apply['applied_at'] ?? '')),
                ],
                'before' => [
                    'stage_latest_package_id' => $beforeStagePackage,
                    'stage_latest_applied_at' => $beforeStageAppliedAt,
                ],
                'after' => [
                    'stage_latest_package_id' => (string) (($afterStatus['latest_stage_apply']['package_id'] ?? '')),
                    'stage_latest_applied_at' => (string) (($afterStatus['latest_stage_apply']['applied_at'] ?? '')),
                ],
            ];

            SystemActivityLogger::write('system', 'content_sync_production_to_stage_succeeded', [
                'operation_id' => $operationId,
                'current_environment' => current_environment(),
                'target_environment' => 'stage',
                'actor' => [
                    'id' => $user['id'] ?? null,
                    'usuario' => $user['usuario'] ?? null,
                    'nome' => $user['nome'] ?? null,
                ],
                'before' => $result['before'],
                'after' => $result['after'],
                'package_id' => $packageId,
                'pre_apply_backup_id' => $backupId,
                'verification' => $result['verification'],
                'status' => 'ok',
            ]);

            return $result;
        } catch (\Throwable $exception) {
            SystemActivityLogger::write('system', 'content_sync_production_to_stage_failed', [
                'operation_id' => $operationId,
                'current_environment' => current_environment(),
                'target_environment' => 'stage',
                'actor' => [
                    'id' => $user['id'] ?? null,
                    'usuario' => $user['usuario'] ?? null,
                    'nome' => $user['nome'] ?? null,
                ],
                'before' => [
                    'stage_latest_package_id' => $beforeStagePackage,
                    'stage_latest_applied_at' => $beforeStageAppliedAt,
                ],
                'after' => [],
                'package_id' => $packageId,
                'pre_apply_backup_id' => $backupId,
                'status' => 'fail',
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function buildOperationId(): string
    {
        return 'content-sync-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function presentProductionPackages(array $items): array
    {
        $packages = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $sourceProfile = strtolower(trim((string) ($item['source_profile'] ?? '')));
            if ($sourceProfile !== 'production') {
                continue;
            }

            $packages[] = [
                'package_id' => (string) ($item['package_id'] ?? ''),
                'created_at' => (string) ($item['created_at'] ?? ''),
                'is_valid' => (bool) ($item['is_valid'] ?? false),
                'posts' => (int) ($item['stats']['posts'] ?? 0),
                'links' => (int) ($item['stats']['links'] ?? 0),
                'configs' => (int) ($item['stats']['configuracoes'] ?? 0),
                'uploads' => (int) ($item['uploads']['included_files'] ?? 0),
            ];
        }

        return $packages;
    }
}
