<?php

declare(strict_types=1);

require_once __DIR__ . '/backup/EnvLoader.php';
require_once __DIR__ . '/backup/BackupManager.php';

use Scripts\Backup\BackupManager;
use Scripts\Backup\EnvLoader;

EnvLoader::load(dirname(__DIR__) . '/.env');

$config = require dirname(__DIR__) . '/config/backup.php';
$manager = new BackupManager($config);
$command = strtolower((string) ($argv[1] ?? 'help'));

try {
    switch ($command) {
        case 'run':
            $profile = (string) ($argv[2] ?? 'local');
            $includeUploads = !in_array('--no-uploads', $argv, true);
            $manifest = $manager->run($profile, null, $includeUploads);
            echo 'Backup concluido com sucesso.' . PHP_EOL;
            echo 'ID: ' . ($manifest['backup_id'] ?? '-') . PHP_EOL;
            echo 'Perfil: ' . ($manifest['profile_label'] ?? '-') . PHP_EOL;
            echo 'Pasta: ' . dirname((string) (($manifest['database']['path'] ?? '') ?: ($manifest['uploads']['path'] ?? ''))) . PHP_EOL;
            echo 'Banco: ' . number_format(((int) ($manifest['database']['size_bytes'] ?? 0)) / 1024, 1, ',', '.') . ' KB' . PHP_EOL;
            echo 'Uploads: ' . ($includeUploads ? number_format(((int) ($manifest['uploads']['size_bytes'] ?? 0)) / 1024, 1, ',', '.') . ' KB' : 'nao incluido') . PHP_EOL;
            echo 'Sistema: ' . number_format(((int) ($manifest['system_files']['size_bytes'] ?? 0)) / 1024, 1, ',', '.') . ' KB' . PHP_EOL;
            break;

        case 'status':
            $status = $manager->status();
            echo 'Raiz: ' . ($status['backup_root'] ?? '-') . PHP_EOL;
            echo 'Total de backups: ' . ($status['total_backups'] ?? 0) . PHP_EOL;

            if (!empty($status['latest'])) {
                $latest = $status['latest'];
                echo PHP_EOL . 'Ultimo backup:' . PHP_EOL;
                echo '- ID: ' . ($latest['backup_id'] ?? '-') . PHP_EOL;
                echo '- Perfil: ' . ($latest['profile_label'] ?? '-') . PHP_EOL;
                echo '- Criado em: ' . ($latest['created_at'] ?? '-') . PHP_EOL;
                echo '- Valido: ' . (($latest['is_valid'] ?? false) ? 'sim' : 'nao') . PHP_EOL;
                echo '- Enviado para nuvem: ' . (($latest['cloud_uploaded'] ?? false) ? 'sim' : 'nao') . PHP_EOL;
                echo '- Enviado em: ' . (($latest['cloud_uploaded_at'] ?? '-') ?: '-') . PHP_EOL;
            }

            if (!empty($status['latest_uploaded'])) {
                $latestUploaded = $status['latest_uploaded'];
                echo PHP_EOL . 'Ultimo backup enviado:' . PHP_EOL;
                echo '- ID: ' . ($latestUploaded['backup_id'] ?? '-') . PHP_EOL;
                echo '- Enviado em: ' . ($latestUploaded['cloud_uploaded_at'] ?? '-') . PHP_EOL;
            }

            if (!empty($status['items'])) {
                echo PHP_EOL . 'Backups recentes:' . PHP_EOL;
                foreach (array_slice((array) $status['items'], 0, 5) as $item) {
                    echo sprintf(
                        '- %s | %s | valido=%s | enviado=%s',
                        (string) ($item['backup_id'] ?? '-'),
                        (string) ($item['profile'] ?? '-'),
                        (($item['is_valid'] ?? false) ? 'sim' : 'nao'),
                        (($item['cloud_uploaded'] ?? false) ? 'sim' : 'nao')
                    ) . PHP_EOL;
                }
            }
            break;

        case 'verify':
            $backupId = $argv[2] ?? null;
            $backup = $manager->verify(is_string($backupId) ? $backupId : null);
            echo 'Verificacao do backup ' . ($backup['backup_id'] ?? '-') . PHP_EOL;
            echo '- Banco: ' . ($backup['database_verification']['message'] ?? 'sem info') . PHP_EOL;
            echo '- Uploads: ' . ($backup['uploads_verification']['message'] ?? 'sem info') . PHP_EOL;
            echo '- Sistema: ' . ($backup['system_files_verification']['message'] ?? 'sem info') . PHP_EOL;
            echo '- Resultado final: ' . (($backup['is_valid'] ?? false) ? 'valido' : 'invalido') . PHP_EOL;
            break;

        case 'mark-uploaded':
            $backupId = $argv[2] ?? null;
            $backup = $manager->markUploaded(is_string($backupId) ? $backupId : null);
            echo 'Backup marcado como enviado.' . PHP_EOL;
            echo 'ID: ' . ($backup['backup_id'] ?? '-') . PHP_EOL;
            echo 'Enviado em: ' . ($backup['cloud_uploaded_at'] ?? '-') . PHP_EOL;
            break;

        case 'restore':
            $backupId = $argv[2] ?? 'latest';
            $profile = (string) ($argv[3] ?? 'local');
            $scope = (string) ($argv[4] ?? 'all');
            $force = in_array('--force', $argv, true);
            $result = $manager->restore(is_string($backupId) ? $backupId : 'latest', $profile, $scope, $force);
            echo 'Restore concluido.' . PHP_EOL;
            echo 'ID: ' . ($result['backup_id'] ?? '-') . PHP_EOL;
            echo 'Perfil destino: ' . ($result['target_profile'] ?? '-') . PHP_EOL;
            echo 'Escopo: ' . ($result['scope'] ?? '-') . PHP_EOL;
            echo 'Restaurado: ' . implode(', ', (array) ($result['restored'] ?? [])) . PHP_EOL;
            break;

        case 'delete-local':
            $backupId = (string) ($argv[2] ?? '');
            $confirmation = (string) ($argv[3] ?? '');
            $result = $manager->deleteLocalBackup($backupId, $confirmation);
            echo 'Backup removido da pasta local.' . PHP_EOL;
            echo 'ID: ' . ($result['backup_id'] ?? '-') . PHP_EOL;
            echo 'Pasta: ' . ($result['directory'] ?? '-') . PHP_EOL;
            break;

        default:
            echo 'Uso:' . PHP_EOL;
            echo '  php scripts/backup.php run [local|stage|production] [--no-uploads]' . PHP_EOL;
            echo '  php scripts/backup.php status' . PHP_EOL;
            echo '  php scripts/backup.php verify [backup_id|latest]' . PHP_EOL;
            echo '  php scripts/backup.php mark-uploaded [backup_id|latest]' . PHP_EOL;
            echo '  php scripts/backup.php delete-local [backup_id] [confirmacao_id]' . PHP_EOL;
            echo '  php scripts/backup.php restore [backup_id|latest] [local|stage|production] [all|database|uploads|system_files] --force' . PHP_EOL;
            exit(0);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Erro: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
