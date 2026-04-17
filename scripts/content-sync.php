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
            $parity = $manager->parityStatus();
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
            echo PHP_EOL . 'Paridade geral: ' . (($parity['overall_in_sync'] ?? false) ? 'sincronizada' : 'pendente') . PHP_EOL;
            if (!empty($parity['content'])) {
                echo '- Conteudo: ' . (($parity['content']['in_sync'] ?? false) ? 'ok' : 'divergente') . PHP_EOL;
            }
            if (!empty($parity['code'])) {
                echo '- Codigo: ' . (($parity['code']['in_sync'] ?? false) ? 'ok' : 'divergente') . PHP_EOL;
            }
            break;

        case 'parity':
            $parity = $manager->parityStatus();
            echo 'Paridade local x producao' . PHP_EOL;
            echo '- Verificado em: ' . ($parity['checked_at'] ?? '-') . PHP_EOL;
            echo '- Geral: ' . (($parity['overall_in_sync'] ?? false) ? 'sincronizada' : 'pendente') . PHP_EOL;
            if (!empty($parity['content'])) {
                echo PHP_EOL . 'Conteudo:' . PHP_EOL;
                echo '- Ultimo pacote local: ' . (($parity['content']['latest_local_package_id'] ?? null) ?: '-') . PHP_EOL;
                echo '- Ultimo pacote em producao: ' . (($parity['content']['latest_production_package_id'] ?? null) ?: '-') . PHP_EOL;
                echo '- Status: ' . (($parity['content']['in_sync'] ?? false) ? 'ok' : 'divergente') . PHP_EOL;
            }
            if (!empty($parity['code'])) {
                echo PHP_EOL . 'Codigo:' . PHP_EOL;
                echo '- Ultimo pacote local: ' . (($parity['code']['latest_local_package_id'] ?? null) ?: '-') . PHP_EOL;
                echo '- Ultimo pacote em producao: ' . (($parity['code']['latest_production_package_id'] ?? null) ?: '-') . PHP_EOL;
                echo '- Status: ' . (($parity['code']['in_sync'] ?? false) ? 'ok' : 'divergente') . PHP_EOL;
            }
            if (!empty($parity['recommendations'])) {
                echo PHP_EOL . 'Recomendacoes:' . PHP_EOL;
                foreach ((array) $parity['recommendations'] as $recommendation) {
                    echo '- ' . $recommendation . PHP_EOL;
                }
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
            echo '  php scripts/content-sync.php parity' . PHP_EOL;
            echo '  php scripts/content-sync.php verify [package_id|latest]' . PHP_EOL;
            echo '  php scripts/content-sync.php apply [package_id|latest] [local|production] --force' . PHP_EOL;
            exit(0);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Erro: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
