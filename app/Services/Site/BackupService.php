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

    public function getViewModel(?array $flash = null, ?array $lastVerification = null): array
    {
        return [
            'title' => 'Backup Local | Estratégia Nerd',
            'meta_description' => 'Painel local de backup, verificação e restore do projeto.',
            'site_chrome' => false,
            'backup_status' => $this->presentStatus($this->manager->status()),
            'flash' => is_array($flash) ? $flash : null,
            'last_verification' => is_array($lastVerification) ? $lastVerification : null,
            'production_ready' => $this->productionProfileReady(),
        ];
    }

    public function run(string $profile): array
    {
        return $this->manager->run($profile);
    }

    public function verify(?string $backupId): array
    {
        return $this->manager->verify($backupId);
    }

    public function markUploaded(?string $backupId): array
    {
        return $this->manager->markUploaded($backupId);
    }

    public function restore(?string $backupId, string $targetProfile, string $scope): array
    {
        return $this->manager->restore($backupId, $targetProfile, $scope, true);
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

        foreach (['host', 'database', 'username'] as $required) {
            if (trim((string) ($database[$required] ?? '')) === '') {
                return false;
            }
        }

        $mode = strtolower((string) ($uploads['mode'] ?? 'local'));
        if ($mode === 'local') {
            return trim((string) ($uploads['path'] ?? '')) !== '';
        }

        foreach (['host', 'username', 'password', 'root'] as $required) {
            if (trim((string) ($uploads[$required] ?? '')) === '') {
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

        return true;
    }

    private function presentStatus(array $status): array
    {
        $items = [];
        foreach ((array) ($status['items'] ?? []) as $item) {
            $databaseBytes = (int) ($item['database']['size_bytes'] ?? 0);
            $uploadsBytes = (int) ($item['uploads']['size_bytes'] ?? 0);
            $items[] = [
                'backup_id' => (string) ($item['backup_id'] ?? ''),
                'profile' => (string) ($item['profile'] ?? ''),
                'profile_label' => $this->profileLabel((string) ($item['profile'] ?? ''), (string) ($item['profile_label'] ?? '')),
                'created_at' => (string) ($item['created_at'] ?? ''),
                'cloud_uploaded' => (bool) ($item['cloud_uploaded'] ?? false),
                'cloud_uploaded_at' => (string) ($item['cloud_uploaded_at'] ?? ''),
                'is_valid' => (bool) ($item['is_valid'] ?? false),
                'database_size' => $this->formatBytes($databaseBytes),
                'uploads_size' => $this->formatBytes($uploadsBytes),
                'total_size' => $this->formatBytes($databaseBytes + $uploadsBytes),
                'database_status' => (string) ($item['verification']['database']['message'] ?? '—'),
                'uploads_status' => (string) ($item['verification']['uploads']['message'] ?? '—'),
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
            'profile_label' => (string) ($running['profile_label'] ?? 'Backup em execução'),
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
