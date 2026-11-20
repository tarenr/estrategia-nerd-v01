<?php
/** Regression test: Graph IDs must enrich, not duplicate, an Instagrapi post. */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Repositories\InstagramPostRepository;

/** @var PDO $pdo */
$pdo = $GLOBALS['pdo'];
$accountId = (int) $pdo->query("SELECT id FROM instagram_accounts WHERE ativo = 1 ORDER BY id LIMIT 1")->fetchColumn();
if ($accountId <= 0) {
    fwrite(STDERR, "[FAIL] Conta ativa do Instagram ausente.\n");
    exit(1);
}

$repo = new InstagramPostRepository($pdo);
$suffix = bin2hex(random_bytes(8));
$privateId = 'private-' . $suffix;
$graphId = 'graph-' . $suffix;
$permalink = 'https://www.instagram.com/p/TDD' . $suffix . '/';
$exitCode = 1;

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        "INSERT INTO instagram_posts
            (account_id, status, tipo, legenda, ig_media_id, permalink, publicado_em, origin)
         VALUES (:account_id, 'publicado', 'imagem', 'fixture privada', :ig_media_id, :permalink, NOW(), 'local')"
    );
    $stmt->execute([':account_id' => $accountId, ':ig_media_id' => $privateId, ':permalink' => $permalink]);

    $repo->upsertFromFeed([
        'account_id' => $accountId,
        'tipo' => 'imagem',
        'legenda' => 'fixture Graph',
        'ig_media_id' => $graphId,
        'permalink' => $permalink,
        'publicado_em' => date('Y-m-d H:i:s'),
    ]);

    $check = $pdo->prepare(
        'SELECT COUNT(*) AS total, MAX(ig_media_id = :graph_id) AS graph_bound
           FROM instagram_posts WHERE account_id = :account_id AND permalink = :permalink'
    );
    $check->execute([':graph_id' => $graphId, ':account_id' => $accountId, ':permalink' => $permalink]);
    $result = $check->fetch(PDO::FETCH_ASSOC) ?: [];
    $ok = (int) ($result['total'] ?? 0) === 1 && (int) ($result['graph_bound'] ?? 0) === 1;
    echo ($ok ? '[PASS]' : '[FAIL]') . " ID da Graph consolida o registro privado pelo permalink.\n";
    $exitCode = $ok ? 0 : 1;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

exit($exitCode);
