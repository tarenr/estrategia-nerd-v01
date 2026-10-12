<?php
declare(strict_types=1);

// Alignment of Instagram cross-post links for all 35 real production posts.
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Services\Instagram\BlogCrosspostService;
use App\Support\TargetEnvironmentDatabase;

$action = $argv[1] ?? '--inspect';
if (!in_array($action, ['--inspect', '--prepare', '--apply', '--verify'], true)) {
    throw new RuntimeException('Ação inválida. Use --inspect, --prepare, --apply ou --verify.');
}

$local = $GLOBALS['pdo'];
$prod = TargetEnvironmentDatabase::pdo('production');
$dir = base_path('storage/backups/instagram-publish/links-alignment-20261011');
$packagePath = $dir . '/package.json';

// Posts reais de produção (ignora 44 e 45 que são testes)
$prodPosts = $prod->query('SELECT id, slug, titulo, status FROM posts WHERE id NOT IN (44, 45) ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$prodRealIds = array_map(fn($p) => (int)$p['id'], $prodPosts);

function getCrosspostService(): BlogCrosspostService {
    return BlogCrosspostService::fromGlobals();
}

function getAllInstagramPosts(PDO $pdo): array {
    return $pdo->query('SELECT id, status, post_blog_id, idempotency_key, agendado_para, publicado_em, video_rendered_path FROM instagram_posts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
}

$crosspost = getCrosspostService();
$currentLinked = $crosspost->linkedBlogPosts('production');
$missingBefore = array_values(array_diff($prodRealIds, array_keys($currentLinked)));

if ($action === '--inspect') {
    echo json_encode([
        'total_real_production_posts' => count($prodRealIds),
        'currently_linked_in_production' => count(array_intersect(array_keys($currentLinked), $prodRealIds)),
        'missing_link_count' => count($missingBefore),
        'missing_post_ids' => $missingBefore,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
}

if ($action === '--prepare') {
    if (is_file($packagePath)) {
        throw new RuntimeException('Pacote de alinhamento já existe; backup preservado.');
    }
    if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
        throw new RuntimeException('Não foi possível criar pasta de backup.');
    }

    $allPostsBefore = getAllInstagramPosts($local);
    file_put_contents($dir . '/before.json', json_encode($allPostsBefore, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

    $manifest = [
        'totalRealPosts' => count($prodRealIds),
        'missingBefore' => $missingBefore,
        'preparedAt' => date('c'),
    ];
    file_put_contents($packagePath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

    echo "PASS: backup imutável salvo em storage/backups/instagram-publish/links-alignment-20261011/before.json (" . count($allPostsBefore) . " registros).\n";
    exit(0);
}

if ($action === '--apply') {
    if (!is_file($dir . '/before.json')) {
        throw new RuntimeException('Execute --prepare antes de --apply para garantir backup imutável.');
    }

    $local->beginTransaction();
    try {
        // 1. Novos 4 posts promovidos (IDs 46 a 49)
        // #46 -> Reel 229 (e também atualiza 230)
        $local->prepare('UPDATE instagram_posts SET post_blog_id = 46, idempotency_key = :k WHERE id = 229')
            ->execute(['k' => BlogCrosspostService::idempotencyKey('production', 46)]);
        $local->prepare('UPDATE instagram_posts SET post_blog_id = 46 WHERE id = 230')
            ->execute();

        // #47 -> Reel 231
        $local->prepare('UPDATE instagram_posts SET post_blog_id = 47, idempotency_key = :k WHERE id = 231')
            ->execute(['k' => BlogCrosspostService::idempotencyKey('production', 47)]);

        // #48 -> Reel 233
        $local->prepare('UPDATE instagram_posts SET post_blog_id = 48, idempotency_key = :k WHERE id = 233')
            ->execute(['k' => BlogCrosspostService::idempotencyKey('production', 48)]);

        // #49 -> Reel 243
        $local->prepare('UPDATE instagram_posts SET post_blog_id = 49, idempotency_key = :k WHERE id = 243')
            ->execute(['k' => BlogCrosspostService::idempotencyKey('production', 49)]);

        // 2. Chaves legadas dos posts 25, 36, 37, 38, 39, 40
        $legacyMap = [
            25 => 205,
            36 => 215,
            37 => 216,
            38 => 217,
            39 => 218,
            40 => 219,
        ];
        foreach ($legacyMap as $blogId => $igId) {
            $local->prepare('UPDATE instagram_posts SET idempotency_key = :k WHERE id = :id')
                ->execute(['k' => BlogCrosspostService::idempotencyKey('production', $blogId), 'id' => $igId]);
        }

        // 3. Sequência de Diablo (transferir chave de produção para os Reels agendados ativos)
        $diabloCancelados = [191, 192, 193, 194];
        foreach ($diabloCancelados as $cid) {
            $local->prepare('UPDATE instagram_posts SET idempotency_key = :k WHERE id = :id')
                ->execute(['k' => 'legacy:canceled:' . $cid, 'id' => $cid]);
        }
        $diabloAtivos = [
            11 => 224,
            12 => 226,
            13 => 225,
            14 => 227,
        ];
        foreach ($diabloAtivos as $blogId => $igId) {
            $local->prepare('UPDATE instagram_posts SET post_blog_id = :b, idempotency_key = :k WHERE id = :id')
                ->execute(['b' => $blogId, 'k' => BlogCrosspostService::idempotencyKey('production', $blogId), 'id' => $igId]);
        }

        // 4. Windows #30 -> apontar para a versão corrigida e republicada #246
        $local->prepare('UPDATE instagram_posts SET idempotency_key = :k WHERE id = 209')
            ->execute(['k' => 'legacy:replaced:209:v2']);
        $local->prepare('UPDATE instagram_posts SET post_blog_id = 30, idempotency_key = :k WHERE id = 246')
            ->execute(['k' => BlogCrosspostService::idempotencyKey('production', 30)]);

        $local->commit();

        file_put_contents($dir . '/applied.json', json_encode([
            'appliedAt' => date('c'),
            'updatedReels' => [229, 230, 231, 233, 243, 205, 215, 216, 217, 218, 219, 191, 192, 193, 194, 224, 225, 226, 227, 209, 246],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        echo "PASS: vínculos atualizados em transação no banco local com sucesso.\n";
    } catch (Throwable $e) {
        if ($local->inTransaction()) {
            $local->rollBack();
        }
        throw $e;
    }
}

// Verificação
$afterLinked = $crosspost->linkedBlogPosts('production');
$missingAfter = array_values(array_diff($prodRealIds, array_keys($afterLinked)));

if ($action === '--verify') {
    if (!empty($missingAfter)) {
        throw new RuntimeException('Ainda restam posts reais de produção sem vínculo: ' . implode(',', $missingAfter));
    }

    file_put_contents($dir . '/verified.json', json_encode([
        'totalRealPosts' => count($prodRealIds),
        'totalLinked' => count($afterLinked),
        'missingAfter' => $missingAfter,
        'verifiedAt' => date('c'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

    echo "PASS: todos os " . count($prodRealIds) . " posts reais de produção estão 100% vinculados aos Reels correspondentes no painel!\n";
}
