<?php

declare(strict_types=1);

namespace App\Services\Site;

use Scripts\Backup\BackupManager;

final class BackupService
{
    public function __construct(
        private BackupManager $manager,
        private array $config
    ) {
    }

    public function getViewModel(string $section = 'resumo', int $historyPage = 1, int $historyPerPage = 10, ?array $flash = null, ?array $lastVerification = null): array
    {
        $section = strtolower(trim($section));
        $withVerification = $section === 'historico';
        $historyPage = max(1, $historyPage);
        $historyPerPage = max(5, min(50, $historyPerPage));
        $historyOffset = ($historyPage - 1) * $historyPerPage;

        $status = $this->manager->status(
            $withVerification,
            $withVerification ? $historyPerPage : null,
            $withVerification ? $historyOffset : 0
        );

        $totalBackups = (int) ($status['total_backups'] ?? 0);
        $historyPages = max(1, (int) ceil($totalBackups / max(1, $historyPerPage)));

        return [
            'title' => 'Backup de Ambiente | Estrategia Nerd',
            'meta_description' => 'Painel local de backup de ambiente, verificacao e restore do projeto.',
            'site_chrome' => false,
            'backup_status' => $this->presentStatus($status, $withVerification),
            'flash' => is_array($flash) ? $flash : null,
            'last_verification' => is_array($lastVerification) ? $lastVerification : null,
            'local_ready' => $this->profileReady('local'),
            'stage_ready' => $this->profileReady('stage'),
            'production_ready' => $this->productionProfileReady(),
            'backup_history_pagination' => [
                'total' => $totalBackups,
                'page' => min($historyPage, $historyPages),
                'per_page' => $historyPerPage,
                'pages' => $historyPages,
            ],
        ];
    }

    public function run(string $profile, ?string $progressId = null): array
    {
        return $this->manager->run($profile, $progressId);
    }

    public function verify(?string $backupId, ?string $progressId = null): array
    {
        return $this->manager->verify($backupId, $progressId);
    }

    public function markUploaded(?string $backupId): array
    {
        return $this->manager->markUploaded($backupId);
    }

    public function restore(?string $backupId, string $targetProfile, string $scope, ?string $progressId = null): array
    {
        return $this->manager->restore($backupId, $targetProfile, $scope, true, $progressId);
    }

    public function profileReady(string $profileName): bool
    {
        $profileName = strtolower(trim($profileName));
        $profile = (array) ($this->config['profiles'][$profileName] ?? []);
        if ($profile === []) {
            return false;
        }

        $database = (array) ($profile['database'] ?? []);
        $uploads = (array) ($profile['uploads'] ?? []);
        $systemFiles = (array) ($profile['system_files'] ?? []);

        foreach (['host', 'database', 'username'] as $required) {
            if (trim((string) ($database[$required] ?? '')) === '') {
                return false;
            }
        }

        $mode = strtolower((string) ($uploads['mode'] ?? 'local'));
        if ($mode === 'local') {
            if (trim((string) ($uploads['path'] ?? '')) === '') {
                return false;
            }
        } else {
            foreach (['host', 'username', 'password', 'root'] as $required) {
                if (trim((string) ($uploads[$required] ?? '')) === '') {
                    return false;
                }
            }
        }

        $systemMode = strtolower((string) ($systemFiles['mode'] ?? 'local'));
        if ($systemMode === 'local') {
            return trim((string) ($systemFiles['root'] ?? '')) !== '';
        }

        foreach (['host', 'username', 'password', 'root'] as $required) {
            if (trim((string) ($systemFiles[$required] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    private function productionProfileReady(): bool
    {
        $profile = (array) ($this->config['profiles']['production'] ?? []);
        $database = (array) ($profile['database'] ?? []);
        $uploads = (array) ($profile['uploads'] ?? []);
        $systemFiles = (array) ($profile['system_files'] ?? []);

        foreach (['host', 'database', 'username'] as $required) {
            if (trim((string) ($database[$required] ?? '')) === '') {
                return false;
            }
        }

        foreach (['host', 'username', 'password', 'root'] as $required) {
            if (trim((string) ($uploads[$required] ?? '')) === '') {
                return false;
            }
        }

        foreach (['host', 'username', 'password', 'root'] as $required) {
            if (trim((string) ($systemFiles[$required] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    private function presentStatus(array $status, bool $withVerification = true): array
    {
        $items = [];
        foreach ((array) ($status['items'] ?? []) as $item) {
            $databaseBytes = (int) ($item['database']['size_bytes'] ?? 0);
            $uploadsBytes = (int) ($item['uploads']['size_bytes'] ?? 0);
            $systemFilesBytes = (int) ($item['system_files']['size_bytes'] ?? 0);
            $isValid = $withVerification
                ? (bool) ($item['is_valid'] ?? false)
                : strtolower((string) ($item['status'] ?? '')) === 'ready';

            $items[] = [
                'backup_id' => (string) ($item['backup_id'] ?? ''),
                'profile' => (string) ($item['profile'] ?? ''),
                'profile_label' => $this->profileLabel((string) ($item['profile'] ?? ''), (string) ($item['profile_label'] ?? '')),
                'created_at' => (string) ($item['created_at'] ?? ''),
                'cloud_uploaded' => (bool) ($item['cloud_uploaded'] ?? false),
                'cloud_uploaded_at' => (string) ($item['cloud_uploaded_at'] ?? ''),
                'is_valid' => $isValid,
                'kind' => (string) ($item['kind'] ?? 'data_uploads'),
                'system_files_count' => (int) ($item['system_files_count'] ?? 0),
                'database_size' => $this->formatBytes($databaseBytes),
                'uploads_size' => $this->formatBytes($uploadsBytes),
                'system_files_size' => $systemFilesBytes > 0 ? $this->formatBytes($systemFilesBytes) : '-',
                'total_size' => $this->formatBytes($databaseBytes + $uploadsBytes + $systemFilesBytes),
                'database_status' => (string) ($item['verification']['database']['message'] ?? ($withVerification ? '—' : 'Nao verificado nesta consulta.')),
                'uploads_status' => (string) ($item['verification']['uploads']['message'] ?? ($withVerification ? '—' : 'Nao verificado nesta consulta.')),
            ];
        }

        return [
            'backup_root' => (string) ($status['backup_root'] ?? ''),
            'total_backups' => (int) ($status['total_backups'] ?? 0),
            'latest' => $items[0] ?? null,
            'latest_uploaded' => $this->presentLatestUploaded((array) ($status['latest_uploaded'] ?? [])),
            'running' => $this->presentRunning((array) ($status['running'] ?? [])),
            'items' => $items,
        ];
    }

    private function presentLatestUploaded(array $item): ?array
    {
        if ($item === []) {
            return null;
        }

        return [
            'backup_id' => (string) ($item['backup_id'] ?? ''),
            'cloud_uploaded_at' => (string) ($item['cloud_uploaded_at'] ?? ''),
        ];
    }

    private function presentRunning(array $running): ?array
    {
        if ($running === []) {
            return null;
        }

        return [
            'profile' => (string) ($running['profile'] ?? ''),
            'profile_label' => (string) ($running['profile_label'] ?? 'Backup em execucao'),
            'started_at' => (string) ($running['started_at'] ?? ''),
        ];
    }

    private function profileLabel(string $profile, string $fallback = ''): string
    {
        return match (strtolower(trim($profile))) {
            'production' => 'Producao',
            'stage' => 'Stage / Homologacao remota',
            'local' => 'Local / Homologacao',
            default => $fallback !== '' ? $fallback : 'Backup',
        };
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return number_format($value, 1, ',', '.') . ' ' . $units[$unit];
    }
}
