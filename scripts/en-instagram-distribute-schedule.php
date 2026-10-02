<?php
/**
 * -----------------------------------------------------------------------------
 * @file        scripts/en-instagram-distribute-schedule.php
 * @project     Estrategia Nerd
 * @purpose     Distribuição e agendamento coordenado da grade do Instagram
 *              com o Blog (posts novos no mesmo dia às 19:30 + acervo nos dias livres)
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Support\TargetEnvironmentDatabase;

date_default_timezone_set('America/Sao_Paulo');

$isDryRun = in_array('--dry-run', $argv, true);
$force = in_array('--force', $argv, true);

echo "\n=======================================================================\n";
echo "   ESTRATÉGIA NERD — AGENDAMENTO COORDENADO INSTAGRAM & BLOG\n";
echo "=======================================================================\n";
echo sprintf("Modo: %s | Reagendamento forçado (--force): %s\n\n",
    $isDryRun ? 'DRY-RUN (Simulação)' : 'EXECUÇÃO REAL',
    $force ? 'SIM' : 'NÃO'
);

try {
    $prodPdo = TargetEnvironmentDatabase::pdo('production');
} catch (Throwable $e) {
    fwrite(STDERR, "ERRO: Falha ao conectar no banco de produção do blog: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

/** @var PDO $localPdo */
$localPdo = $GLOBALS['pdo'];

// 1. Obter posts agendados do blog em produção
$stmt = $prodPdo->query(
    "SELECT id, titulo, slug, data_publicacao, status 
       FROM posts 
      WHERE status = 'agendado' 
      ORDER BY data_publicacao ASC"
);
$blogScheduledPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Obter posts locais do Instagram sincronizados do blog
$statusCondition = $force ? "p.status IN ('rascunho', 'agendado')" : "p.status = 'rascunho'";
$stmt = $localPdo->query(
    "SELECT p.id, p.status, p.agendado_para, p.post_blog_id, p.idempotency_key, p.legenda
       FROM instagram_posts p
      WHERE $statusCondition
        AND (p.idempotency_key LIKE 'blog_crosspost:production:%' OR p.post_blog_id IS NOT NULL)
      ORDER BY p.id ASC"
);
$localIgPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($localIgPosts === []) {
    echo "Nenhum post do Instagram elegível para agendamento encontrado.\n";
    if (!$force) {
        echo "Dica: Use --force para reagendar posts que já estejam com status 'agendado'.\n";
    }
    exit(0);
}

// Mapear posts do Instagram por ID do post do blog
$igByBlogId = [];
$otherIgDrafts = [];

foreach ($localIgPosts as $igPost) {
    $blogId = !empty($igPost['post_blog_id']) ? (int)$igPost['post_blog_id'] : 0;
    if ($blogId <= 0) {
        $key = (string) ($igPost['idempotency_key'] ?? '');
        if (preg_match('/^blog_crosspost:production:(\d+)$/', $key, $m)) {
            $blogId = (int) $m[1];
        }
    }

    if ($blogId > 0) {
        $igByBlogId[$blogId] = $igPost;
    } else {
        $otherIgDrafts[] = $igPost;
    }
}

// 3. Montar plano de agendamento
$schedulePlan = [];
$occupiedDates = [];

// A. Prioridade 1: Posts novos agendados no blog (Reel sai às 19:30 do mesmo dia em que o artigo sai no blog)
foreach ($blogScheduledPosts as $bPost) {
    $bId = (int) $bPost['id'];
    $bDateRaw = (string) $bPost['data_publicacao'];
    $day = substr($bDateRaw, 0, 10); // YYYY-MM-DD
    $targetTime = $day . ' 19:30:00';

    if (isset($igByBlogId[$bId])) {
        $igPost = $igByBlogId[$bId];
        $cleanTitulo = trim((string) preg_replace('/\[\[(.*?)\]\]/', '$1', (string)$bPost['titulo']));

        $schedulePlan[] = [
            'date_time'  => $targetTime,
            'day'        => $day,
            'origin'     => 'NOVO (Blog Agendado)',
            'blog_id'    => $bId,
            'ig_id'      => (int) $igPost['id'],
            'titulo'     => $cleanTitulo,
        ];

        $occupiedDates[$day] = true;
        unset($igByBlogId[$bId]);
    }
}

// B. Prioridade 2: Demais posts sincronizados (acervo de artigos já publicados)
// Ordenar os posts restantes pelo ID do blog
ksort($igByBlogId);
$remainingIgPosts = array_values($igByBlogId);

// Obter títulos do blog para os posts do acervo
$backlogBlogIds = [];
foreach ($remainingIgPosts as $p) {
    $bId = !empty($p['post_blog_id']) ? (int)$p['post_blog_id'] : 0;
    if ($bId <= 0 && preg_match('/^blog_crosspost:production:(\d+)$/', (string)($p['idempotency_key'] ?? ''), $m)) {
        $bId = (int)$m[1];
    }
    if ($bId > 0) {
        $backlogBlogIds[] = $bId;
    }
}

$blogTitles = [];
if ($backlogBlogIds !== []) {
    $inClause = implode(',', $backlogBlogIds);
    $tStmt = $prodPdo->query("SELECT id, titulo FROM posts WHERE id IN ($inClause)");
    while ($row = $tStmt->fetch(PDO::FETCH_ASSOC)) {
        $clean = trim((string) preg_replace('/\[\[(.*?)\]\]/', '$1', (string)$row['titulo']));
        $blogTitles[(int)$row['id']] = $clean;
    }
}

// Gerar grade para os dias de Outubro/2026 (02/10/2026 a 31/10/2026)
$currentDayTimestamp = strtotime('2026-10-02');
$endTimestamp = strtotime('2026-10-31');

$remainingIdx = 0;
while ($currentDayTimestamp <= $endTimestamp && $remainingIdx < count($remainingIgPosts)) {
    $day = date('Y-m-d', $currentDayTimestamp);

    // Se este dia não estiver ocupado por um post novo do blog, aloca um post do acervo
    if (!isset($occupiedDates[$day])) {
        $igPost = $remainingIgPosts[$remainingIdx];
        $targetTime = $day . ' 19:30:00';
        $bId = !empty($igPost['post_blog_id']) ? (int)$igPost['post_blog_id'] : 0;
        if ($bId <= 0 && preg_match('/^blog_crosspost:production:(\d+)$/', (string)($igPost['idempotency_key'] ?? ''), $m)) {
            $bId = (int)$m[1];
        }
        $titulo = $blogTitles[$bId] ?? 'Reel #' . $igPost['id'];

        $schedulePlan[] = [
            'date_time'  => $targetTime,
            'day'        => $day,
            'origin'     => 'ACERVO (Artigo no Ar)',
            'blog_id'    => $bId,
            'ig_id'      => (int) $igPost['id'],
            'titulo'     => $titulo,
        ];

        $occupiedDates[$day] = true;
        $remainingIdx++;
    }

    $currentDayTimestamp = strtotime('+1 day', $currentDayTimestamp);
}

// Ordenar o plano final cronologicamente
usort($schedulePlan, static fn($a, $b) => strcmp($a['date_time'], $b['date_time']));

// 4. Exibir grade calculada
$diasSemana = [
    'Sun' => 'Dom',
    'Mon' => 'Seg',
    'Tue' => 'Ter',
    'Wed' => 'Qua',
    'Thu' => 'Qui',
    'Fri' => 'Sex',
    'Sat' => 'Sáb',
];

echo "CALENDÁRIO DA GRADE DE OUTUBRO/2026 (30 DIAS):\n";
echo str_repeat('-', 100) . "\n";
echo sprintf("%-19s | %-4s | %-22s | %-6s | %-7s | %s\n", "Data & Hora", "Dia", "Tipo de Publicação", "IG ID", "Blog ID", "Título do Post");
echo str_repeat('-', 100) . "\n";

foreach ($schedulePlan as $item) {
    $weekday = $diasSemana[date('D', strtotime($item['day']))] ?? '';
    echo sprintf(
        "%-19s | %-4s | %-22s | #%-5d | #%-6d | %s\n",
        $item['date_time'],
        $weekday,
        $item['origin'],
        $item['ig_id'],
        $item['blog_id'],
        mb_strimwidth($item['titulo'], 0, 38, '...')
    );
}
echo str_repeat('-', 100) . "\n";
echo sprintf("Total de posts na grade: %d (Novos: %d | Acervo: %d)\n\n",
    count($schedulePlan),
    count($blogScheduledPosts),
    count($schedulePlan) - count($blogScheduledPosts)
);

if ($isDryRun) {
    echo "[DRY-RUN] Nenhuma alteração foi persistida no banco de dados.\n";
    echo "Para aplicar efetivamente essa distribuição, execute sem a flag --dry-run.\n";
    exit(0);
}

// 5. Aplicar atualização no banco de dados local
$updateStmt = $localPdo->prepare(
    "UPDATE instagram_posts 
        SET status = 'agendado', 
            agendado_para = :agendado_para, 
            atualizado_em = NOW() 
      WHERE id = :id"
);

$appliedCount = 0;
foreach ($schedulePlan as $item) {
    $updateStmt->execute([
        ':agendado_para' => $item['date_time'],
        ':id'            => $item['ig_id'],
    ]);
    $appliedCount++;
}

echo sprintf("[SUCESSO] Grade aplicada com sucesso! %d posts do Instagram foram marcados como 'agendado'.\n", $appliedCount);
