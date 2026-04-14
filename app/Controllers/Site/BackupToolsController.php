<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Support\Csrf;
use App\Support\Session;
use App\Support\View;
use Scripts\Backup\BackupManager;

final class BackupToolsController
{
    public function index(): void
    {
        $this->ensureLocalOnly();

        $manager = $this->manager();
        $status = $manager->status();
        $flash = Session::pull('backup_tools_flash');
        $lastVerification = Session::pull('backup_tools_verification');

        View::render('site/backup-tools', [
            'title' => 'Backup Local | Estratégia Nerd',
            'meta_description' => 'Painel local de backup, verificação e restore do projeto.',
            'site_chrome' => false,
            'backup_status' => $this->presentStatus($status),
            'flash' => is_array($flash) ? $flash : null,
            'last_verification' => is_array($lastVerification) ? $lastVerification : null,
            'production_ready' => $this->productionProfileReady(),
        ]);
    }

    public function handle(): void
    {
        $this->ensureLocalOnly();

        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            $this->flash('error', 'Sessão expirada. Atualize a página e tente novamente.');
            $this->redirect();
        }

        $action = strtolower(trim((string) ($_POST['action'] ?? '')));
        $manager = $this->manager();

        try {
            switch ($action) {
                case 'run':
                    $profile = strtolower(trim((string) ($_POST['profile'] ?? 'local')));
                    $manifest = $manager->run($profile);
                    $this->flash('success', sprintf('Backup %s concluído com sucesso.', (string) ($manifest['backup_id'] ?? '')));
                    break;

                case 'verify':
                    $backupId = $this->normalizeOptionalId($_POST['backup_id'] ?? 'latest');
                    $verification = $manager->verify($backupId);
                    Session::put('backup_tools_verification', $verification);
                    $this->flash('success', 'Verificação concluída.');
                    break;

                case 'mark_uploaded':
                    $backupId = $this->normalizeOptionalId($_POST['backup_id'] ?? 'latest');
                    $backup = $manager->markUploaded($backupId);
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
                    $result = $manager->restore($backupId, $targetProfile, $scope, true);
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
        $env = (string) config('app.env', 'production');
        $debug = (bool) config('app.debug', false);

        if ($env === 'local' || $debug) {
            return;
        }

        http_response_code(404);
        echo 'Página não encontrada.';
        exit;
    }

    private function manager(): BackupManager
    {
        require_once base_path('scripts/backup/BackupManager.php');

        return new BackupManager(require base_path('config/backup.php'));
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

    private function productionProfileReady(): bool
    {
        $config = require base_path('config/backup.php');
        $profile = (array) ($config['profiles']['production'] ?? []);
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
            'production' => "Produ\u{00E7}\u{00E3}o",
            'local' => "Local / Homologa\u{00E7}\u{00E3}o",
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