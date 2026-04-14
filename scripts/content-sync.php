<?php

declare(strict_types=1);

require_once __DIR__ . '/backup/EnvLoader.php';
require_once __DIR__ . '/content-sync/ContentSyncManager.php';

use Scripts\Backup\EnvLoader;
use Scripts\ContentSync\ContentSyncManager;

EnvLoader::load(dirname(__DIR__) . '/.env');

$config = require dirname(__DIR__) . '/config/content-sync.php';
$manager = new ContentSyncManager($config);
$command = strtolower((string) ($argv[1] ?? 'help'));

try {
    switch ($command) {
        case 'export':
            $profile = (string) ($argv[2] ?? 'local');
            $manifest = $manager->export($profile);
            echo 'Pacote de conteudo gerado com sucesso.' . PHP_EOL;
            echo 'ID: ' . ($manifest['package_id'] ?? '-') . PHP_EOL;
            echo 'Origem: ' . ($manifest['source_profile_label'] ?? '-') . PHP_EOL;
            echo 'Posts: ' . (int) ($manifest['stats']['posts'] ?? 0) . PHP_EOL;
            echo 'Links: ' . (int) ($manifest['stats']['links'] ?? 0) . PHP_EOL;
            echo 'Uploads referenciados: ' . (int) ($manifest['uploads']['included_files'] ?? 0) . PHP_EOL;
            break;

        case 'status':
            $status = $manager->status();
            echo 'Raiz: ' . ($status['package_root'] ?? '-') . PHP_EOL;
            echo 'Total de pacotes: ' . (int) ($status['total_packages'] ?? 0) . PHP_EOL;
            if (!empty($status['latest'])) {
                $latest = $status['latest'];
                echo PHP_EOL . 'Ultimo pacote:' . PHP_EOL;
                echo '- ID: ' . ($latest['package_id'] ?? '-') . PHP_EOL;
                echo '- Origem: ' . ($latest['source_profile_label'] ?? '-') . PHP_EOL;
                echo '- Valido: ' . (($latest['is_valid'] ?? false) ? 'sim' : 'nao') . PHP_EOL;
            }
            if (!empty($status['latest_production_apply'])) {
                $latestApply = $status['latest_production_apply'];
                echo PHP_EOL . 'Ultima publicacao em producao:' . PHP_EOL;
                echo '- ID: ' . ($latestApply['package_id'] ?? '-') . PHP_EOL;
                echo '- Aplicado em: ' . ($latestApply['applied_at'] ?? '-') . PHP_EOL;
            }
            break;

        case 'verify':
            $packageId = $argv[2] ?? null;
            $package = $manager->verify(is_string($packageId) ? $packageId : null);
            echo 'Verificacao do pacote ' . ($package['package_id'] ?? '-') . PHP_EOL;
            echo '- Resultado final: ' . (($package['is_valid'] ?? false) ? 'valido' : 'invalido') . PHP_EOL;
            echo '- Uploads: ' . (($package['verification']['uploads']['message'] ?? 'sem info')) . PHP_EOL;
            break;

        case 'apply':
            $packageId = $argv[2] ?? 'latest';
            $targetProfile = (string) ($argv[3] ?? 'production');
            $force = in_array('--force', $argv, true);
            $result = $manager->apply(is_string($packageId) ? $packageId : 'latest', $targetProfile, $force);
            echo 'Publicacao concluida.' . PHP_EOL;
            echo 'ID: ' . ($result['package_id'] ?? '-') . PHP_EOL;
            echo 'Destino: ' . ($result['target_profile'] ?? '-') . PHP_EOL;
            break;

        default:
            echo 'Uso:' . PHP_EOL;
            echo '  php scripts/content-sync.php export [local|production]' . PHP_EOL;
            echo '  php scripts/content-sync.php status' . PHP_EOL;
            echo '  php scripts/content-sync.php verify [package_id|latest]' . PHP_EOL;
            echo '  php scripts/content-sync.php apply [package_id|latest] [local|production] --force' . PHP_EOL;
            exit(0);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Erro: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}