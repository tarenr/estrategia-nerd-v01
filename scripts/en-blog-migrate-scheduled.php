<?php
/**
 * -----------------------------------------------------------------------------
 * scripts/en-blog-migrate-scheduled.php
 * Migracao dos 8 posts da leva FEAT-008 para status='agendado' e datas reais,
 * com garantia de transacao atomica, rollback e validacao por ID e slug.
 *
 * Executa em:
 * 1. Producao: posts #33 a #40 -> status='agendado' (29/09 a 23/10 as 09:00:00)
 * 2. Local:    posts #65 a #72 -> status='agendado' (29/09 a 23/10 as 09:00:00)
 *              posts #63 e #64 -> status='publicado' (paridade com #31 e #32 de prod)
 *
 * Uso:
 *   php scripts/en-blog-migrate-scheduled.php --dry-run
 *   php scripts/en-blog-migrate-scheduled.php --apply
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Support\TargetEnvironmentDatabase;

$isDryRun = in_array('--dry-run', $argv, true);
$isApply = in_array('--apply', $argv, true);

if (!$isDryRun && !$isApply) {
    echo "Informe --dry-run para simular ou --apply para executar a migracao.\n";
    exit(1);
}

$schedulePosts = [
    [33, 65, 'como-escolher-fonte-para-pc', '2026-09-29 09:00:00'],
    [34, 66, 'como-verificar-saude-ssd-hd', '2026-10-02 09:00:00'],
    [35, 67, 'temperatura-cpu-gpu-quando-se-preocupar', '2026-10-06 09:00:00'],
    [36, 68, '12-jogos-leves-pc-fraco-ou-modesto', '2026-10-09 09:00:00'],
    [37, 69, 'historia-counter-strike', '2026-10-13 09:00:00'],
    [38, 70, '10-filmes-nerds-essenciais', '2026-10-16 09:00:00'],
    [39, 71, 'animes-essenciais-para-iniciantes', '2026-10-20 09:00:00'],
    [40, 72, 'desenhos-anos-90-2000-que-envelheceram-bem', '2026-10-23 09:00:00'],
];

$localPublishedParity = [
    [63, 'rtx-5060-vs-rx-9060-xt-1080p', '2026-09-22 09:00:02'],
    [64, 'am4-ou-am5-em-2026', '2026-09-25 23:25:02'],
];

echo "=== MIGRACAO DE POSTS PARA AGENDAMENTO (FEAT-009) ===\n";
echo "Modo: " . ($isApply ? "APPLY (ESCRITA)" : "DRY-RUN (SIMULACAO)") . "\n\n";

// --- 1. MIGRACAO EM PRODUCAO ---
echo "--- PRODUCAO ---\n";
$pdoProd = TargetEnvironmentDatabase::pdo('production');
$pdoProd->beginTransaction();

try {
    $prodUpdated = 0;
    foreach ($schedulePosts as [$prodId, $localId, $slug, $targetDate]) {
        // Valida existencia, slug e status anterior
        $stmtCheck = $pdoProd->prepare("SELECT id, slug, status FROM posts WHERE id = :id FOR UPDATE");
        $stmtCheck->execute([':id' => $prodId]);
        $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException("Post de producao #{$prodId} nao encontrado.");
        }
        if ($row['slug'] !== $slug) {
            throw new RuntimeException("Post de producao #{$prodId} tem slug divergente: '{$row['slug']}' != '{$slug}'.");
        }
        if ($row['status'] !== 'rascunho' && $row['status'] !== 'agendado') {
            throw new RuntimeException("Post de producao #{$prodId} tem status inesperado: '{$row['status']}'.");
        }

        if ($isApply) {
            $stmtUp = $pdoProd->prepare("UPDATE posts SET status = 'agendado', data_publicacao = :data WHERE id = :id AND slug = :slug");
            $stmtUp->execute([
                ':data' => $targetDate,
                ':id' => $prodId,
                ':slug' => $slug,
            ]);
            $prodUpdated += $stmtUp->rowCount();
        } else {
            $prodUpdated++;
        }

        echo sprintf("  [PROD] #%d (%s) -> agendado para %s\n", $prodId, $slug, $targetDate);
    }

    if ($prodUpdated !== 8) {
        throw new RuntimeException("Esperado atualizar exatamente 8 posts em producao, mas foram {$prodUpdated}. Abortando.");
    }

    if ($isApply) {
        $pdoProd->commit();
        echo ">> Producao: 8 posts atualizados com sucesso (COMMIT REALIZADO).\n\n";
    } else {
        $pdoProd->rollBack();
        echo ">> Producao: 8 posts validados (ROLLBACK DRY-RUN).\n\n";
    }
} catch (Throwable $e) {
    if ($pdoProd->inTransaction()) {
        $pdoProd->rollBack();
    }
    echo "ERRO EM PRODUCAO: " . $e->getMessage() . "\n";
    exit(1);
}

// --- 2. MIGRACAO NO BANCO LOCAL ---
echo "--- LOCAL ---\n";
$pdoLocal = TargetEnvironmentDatabase::pdo('local');
$pdoLocal->beginTransaction();

try {
    $localUpdated = 0;

    // A. Paridade dos ja publicados (#63 e #64)
    foreach ($localPublishedParity as [$localId, $slug, $publishDate]) {
        $stmtCheck = $pdoLocal->prepare("SELECT id, slug, status FROM posts WHERE id = :id FOR UPDATE");
        $stmtCheck->execute([':id' => $localId]);
        $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$row || $row['slug'] !== $slug) {
            throw new RuntimeException("Post local #{$localId} nao encontrado ou slug divergente.");
        }

        if ($isApply) {
            $stmtUp = $pdoLocal->prepare("UPDATE posts SET status = 'publicado', data_publicacao = :data WHERE id = :id");
            $stmtUp->execute([':data' => $publishDate, ':id' => $localId]);
        }
        $localUpdated++;
        echo sprintf("  [LOCAL] #%d (%s) -> publicado em %s (paridade)\n", $localId, $slug, $publishDate);
    }

    // B. Os 8 posts a agendar (#65 a #72)
    foreach ($schedulePosts as [$prodId, $localId, $slug, $targetDate]) {
        $stmtCheck = $pdoLocal->prepare("SELECT id, slug, status FROM posts WHERE id = :id FOR UPDATE");
        $stmtCheck->execute([':id' => $localId]);
        $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException("Post local #{$localId} nao encontrado.");
        }
        if ($row['slug'] !== $slug) {
            throw new RuntimeException("Post local #{$localId} tem slug divergente: '{$row['slug']}' != '{$slug}'.");
        }

        if ($isApply) {
            $stmtUp = $pdoLocal->prepare("UPDATE posts SET status = 'agendado', data_publicacao = :data WHERE id = :id AND slug = :slug");
            $stmtUp->execute([
                ':data' => $targetDate,
                ':id' => $localId,
                ':slug' => $slug,
            ]);
        }
        $localUpdated++;
        echo sprintf("  [LOCAL] #%d (%s) -> agendado para %s\n", $localId, $slug, $targetDate);
    }

    if ($localUpdated !== 10) {
        throw new RuntimeException("Esperado atualizar exatamente 10 posts no local, mas foram {$localUpdated}. Abortando.");
    }

    if ($isApply) {
        $pdoLocal->commit();
        echo ">> Local: 10 posts atualizados com sucesso (COMMIT REALIZADO).\n";
    } else {
        $pdoLocal->rollBack();
        echo ">> Local: 10 posts validados (ROLLBACK DRY-RUN).\n";
    }
} catch (Throwable $e) {
    if ($pdoLocal->inTransaction()) {
        $pdoLocal->rollBack();
    }
    echo "ERRO NO LOCAL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nMigracao finalizada com sucesso.\n";
