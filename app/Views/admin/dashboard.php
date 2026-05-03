<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Views/admin/dashboard.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.1.1
 * @purpose     Dashboard do painel administrativo
 * @description Visao geral do portal alinhada ao padrao visual do admin.
 * ------------------------------------------------------------------------------
 */

declare(strict_types=1);

$days = (int) ($days ?? 7);

function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function fmt(int|string $number): string { return number_format((int) $number, 0, ',', '.'); }
function fmt_k(int|string $number): string
{
    $value = (int) $number;
    return $value >= 1000 ? (string) round($value / 1000, 1) . 'k' : (string) $value;
}
function dashboard_public_url(string $baseUrl, string $path = ''): string
{
    $baseUrl = rtrim(trim($baseUrl), '/');
    if ($baseUrl === '') {
        return $path !== '' ? $path : '';
    }

    if ($path === '' || $path === '/') {
        return $baseUrl . '/';
    }

    return $baseUrl . '/' . ltrim($path, '/');
}

function cover_url(?string $imagemCapa, string $publicBaseUrl = ''): string
{
    $raw = is_string($imagemCapa) ? trim($imagemCapa) : '';
    if ($raw === '') {
        return '';
    }

    $raw = ltrim($raw, '/');

    if (str_starts_with($raw, 'uploads/')) {
        return $publicBaseUrl !== '' ? dashboard_public_url($publicBaseUrl, $raw) : url('/' . $raw);
    }

    if (str_contains($raw, '/')) {
        return $publicBaseUrl !== '' ? dashboard_public_url($publicBaseUrl, $raw) : url('/' . $raw);
    }

    return $publicBaseUrl !== '' ? dashboard_public_url($publicBaseUrl, 'uploads/' . basename($raw)) : url('/uploads/' . basename($raw));
}
function link_type_label(string $tipo, bool $promocao = false): string
{
    if ($tipo === 'produto' && $promocao) {
        return 'Promocao';
    }

    return match ($tipo) {
        'produto' => 'Produto',
        'cupom' => 'Cupom',
        'conteudo' => 'Conteudo',
        'rede_social' => 'Rede Social',
        'servico' => 'Servicos',
        default => 'Link',
    };
}

function admin_clean_post_title(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return 'Sem titulo';
    }

    $value = preg_replace('/\[\[(.*?)\]\]/u', '$1', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    $value = trim($value);

    return $value !== '' ? $value : 'Sem titulo';
}

function dashboard_post_url(?array $post, string $publicBaseUrl = ''): string
{
    $slug = trim((string) ($post['slug'] ?? ''));
    if ($slug === '') {
        return '';
    }

    return $publicBaseUrl !== '' ? dashboard_public_url($publicBaseUrl, 'post/' . rawurlencode($slug)) : url('/post/' . rawurlencode($slug));
}

function status_badge_class(string $status): string
{
    $normalized = strtolower(trim($status));

    return match ($normalized) {
        'publicado' => 'status-badge status-publicado',
        'rascunho' => 'status-badge status-rascunho',
        'agendado' => 'status-badge status-agendado',
        default => 'status-badge',
    };
}

if (!function_exists('day_label_range')) {
    function day_label_range(string $startYmd, string $endYmd): string
    {
        $start = strtotime($startYmd);
        $end = strtotime($endYmd);

        if (!$start || !$end) {
            return '--/--';
        }

        if ($startYmd === $endYmd) {
            return date('d/m', $start);
        }

        return date('d/m', $start) . ' - ' . date('d/m', $end);
    }
}

if (!function_exists('bucketize_series')) {
    function bucketize_series(array $series, int $bucketSize): array
    {
        if ($bucketSize <= 1) {
            return array_values($series);
        }

        $rows = array_values($series);
        $output = [];
        $count = count($rows);

        for ($index = 0; $index < $count; $index += $bucketSize) {
            $chunk = array_slice($rows, $index, $bucketSize);
            if (!$chunk) {
                continue;
            }

            $views = 0;
            $posts = 0;
            $subs = 0;
            $lastMa7 = null;
            $rangeStart = (string) ($chunk[0]['data'] ?? '');
            $rangeEnd = (string) ($chunk[count($chunk) - 1]['data'] ?? '');

            foreach ($chunk as $row) {
                $views += (int) ($row['views'] ?? 0);
                $posts += (int) ($row['posts_novos'] ?? 0);
                $subs += (int) ($row['inscricoes'] ?? 0);

                $ma7 = $row['views_ma7'] ?? null;
                if ($ma7 !== null) {
                    $lastMa7 = $ma7;
                }
            }

            $output[] = [
                'data' => $rangeEnd,
                'range_start' => $rangeStart,
                'range_end' => $rangeEnd,
                'views' => $views,
                'posts_novos' => $posts,
                'inscricoes' => $subs,
                'views_ma7' => $lastMa7,
            ];
        }

        return $output;
    }
}

function parse_ymd(?string $value): ?string
{
    $normalized = is_string($value) ? trim($value) : '';
    if ($normalized === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
        return null;
    }

    [$year, $month, $day] = array_map('intval', explode('-', $normalized));

    return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
}

function clamp_range_90(string $start, string $end): array
{
    try {
        $startDate = new DateTimeImmutable($start);
        $endDate = new DateTimeImmutable($end);
    } catch (Throwable) {
        return [$start, $end];
    }

    if ($startDate > $endDate) {
        [$startDate, $endDate] = [$endDate, $startDate];
    }

    $days = (int) $startDate->diff($endDate)->days + 1;
    if ($days <= 90) {
        return [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')];
    }

    $adjustedStart = $endDate->modify('-89 days');
    return [$adjustedStart->format('Y-m-d'), $endDate->format('Y-m-d')];
}

$todayYmd = date('Y-m-d');
$defaultEnd = $todayYmd;
$defaultStart = date('Y-m-d', strtotime('-29 days'));

if ($days > 0) {
    $defaultStart = date('Y-m-d', strtotime('-' . max(0, $days - 1) . ' days'));
}

$startIn = parse_ymd($_GET['start'] ?? ($start ?? null)) ?? $defaultStart;
$endIn = parse_ymd($_GET['end'] ?? ($end ?? null)) ?? $defaultEnd;
[$startIn, $endIn] = clamp_range_90($startIn, $endIn);

try {
    $startDate = new DateTimeImmutable($startIn);
    $endDate = new DateTimeImmutable($endIn);

    if ($startDate > $endDate) {
        [$startDate, $endDate] = [$endDate, $startDate];
        $startIn = $startDate->format('Y-m-d');
        $endIn = $endDate->format('Y-m-d');
    }

    $days = (int) $startDate->diff($endDate)->days + 1;
} catch (Throwable) {
    $startIn = $defaultStart;
    $endIn = $defaultEnd;
    $days = 30;
}

$totalPosts = (int) ($total_posts ?? 0);
$postsPublicados = (int) ($posts_publicados ?? 0);
$postsRascunho = (int) ($posts_rascunho ?? 0);
$postsAgendados = (int) ($posts_agendados ?? 0);
$totalViews = (int) ($total_views ?? 0);
$viewsHoje = (int) ($views_hoje ?? 0);
$viewsSemana = (int) ($views_semana ?? 0);
$likesTotal = (int) ($likes_total ?? 0);
$totalComentarios = (int) ($total_comentarios ?? 0);
$engagementRate = (float) ($engagement_rate ?? 0);
$totalInscritos = (int) ($total_inscritos ?? 0);
$inscritosNovos30 = (int) ($inscritos_novos_30dias ?? 0);
$postsHoje = (int) ($posts_hoje ?? 0);
$inscritosHoje = (int) ($inscritos_hoje ?? 0);
$comentariosHoje = (int) ($comentarios_hoje ?? 0);
$comentariosPendentes = (int) ($comentarios_pendentes ?? 0);
$taxaAprovacao = (float) ($taxa_aprovacao_comentarios ?? 0);
$topViews = is_array($top_post_views ?? null) ? $top_post_views : null;
$topLikes = is_array($top_post_likes ?? null) ? $top_post_likes : null;
$topComments = is_array($top_post_comments ?? null) ? $top_post_comments : null;
$categoriaPopular = is_array($categoria_popular ?? null) ? $categoria_popular : null;
$chart = is_array($chart ?? null) ? $chart : [];
$series = is_array($chart['series'] ?? null) ? $chart['series'] : [];
$current = is_array($chart['current'] ?? null) ? $chart['current'] : [];
$curViews = (int) ($current['views'] ?? 0);
$curPosts = (int) ($current['posts_novos'] ?? 0);
$curSubs = (int) ($current['inscricoes'] ?? 0);
$periodViewsDailyAverage = (int) round($days > 0 ? $curViews / $days : 0);
$periodPostsDailyAverage = (float) ($days > 0 ? $curPosts / $days : 0);
$periodSubsDailyAverage = (float) ($days > 0 ? $curSubs / $days : 0);
$postsRecentes = is_array($posts_recentes ?? null) ? $posts_recentes : [];
$targetPublicBaseUrl = trim((string) ($target_public_base_url ?? ''));
$targetEnvironment = (string) ($target_environment ?? current_environment());
$targetEnvironmentLabel = (string) ($target_environment_label ?? environment_label($targetEnvironment));
$isRemoteTarget = (bool) ($is_remote_target ?? false);

if (is_array($series)) {
    $series = array_values($series);
    usort($series, static fn($a, $b) => strcmp((string) ($a['data'] ?? ''), (string) ($b['data'] ?? '')));
}

$effectiveDays = max((int) $days, is_array($series) ? (int) count($series) : 0);
$bucketSize = $effectiveDays <= 30 ? 1 : 7;
$bucketModeLabel = $bucketSize <= 1 ? 'Leitura diaria' : 'Agregacao semanal';

if (is_array($series) && $bucketSize > 1) {
    $series = bucketize_series($series, $bucketSize);
}

$chartRows = array_values($series);
$hasChart = count($chartRows) >= 2;
$w = 920;
$h = 260;
$padX = 52;
$padY = 18;
$plotW = $w - ($padX * 2);
$plotH = $h - ($padY * 2);
$n = max(2, count($chartRows));
$stepX = $plotW / ($n - 1);

$viewsArr = $hasChart ? array_map(static fn($row) => (int) ($row['views'] ?? 0), $chartRows) : [0, 0];
$postsArr = $hasChart ? array_map(static fn($row) => (int) ($row['posts_novos'] ?? 0), $chartRows) : [0, 0];
$inscArr = $hasChart ? array_map(static fn($row) => (int) ($row['inscricoes'] ?? 0), $chartRows) : [0, 0];
$ma7Arr = $hasChart ? array_map(static fn($row) => $row['views_ma7'] ?? null, $chartRows) : [null, null];

$viewsMax = max(1, ...$viewsArr);
$postsMax = max(1, ...$postsArr);
$inscMax = max(1, ...$inscArr);

$points = [];
for ($index = 0; $index < $n; $index++) {
    $x = $padX + ($index * $stepX);
    $value = $viewsArr[$index] ?? 0;
    $y = $padY + ($plotH - (($value / $viewsMax) * $plotH));
    $points[] = ['x' => $x, 'y' => $y];
}
$poly = implode(' ', array_map(static fn($point) => round($point['x'], 2) . ',' . round($point['y'], 2), $points));
$area = $poly . ' ' . ($w - $padX) . ',' . ($h - $padY) . ' ' . $padX . ',' . ($h - $padY);

$maPoints = [];
foreach ($ma7Arr as $index => $ma7Value) {
    if ($ma7Value === null) {
        continue;
    }

    $x = $padX + ($index * $stepX);
    $y = $padY + ($plotH - (((float) $ma7Value / $viewsMax) * $plotH));
    $maPoints[] = round($x, 2) . ',' . round($y, 2);
}
$maPoly = implode(' ', $maPoints);

$peakViewsIndex = $hasChart ? array_search(max($viewsArr), $viewsArr, true) : null;
$peakPostsIndex = $hasChart ? array_search(max($postsArr), $postsArr, true) : null;
$peakSubsIndex = $hasChart ? array_search(max($inscArr), $inscArr, true) : null;
$latestIndex = $hasChart ? ($n - 1) : null;
$previousIndex = $hasChart && $n > 1 ? ($n - 2) : null;

$mainViewIdx = [];
if ($hasChart) {
    $minIndex = array_search(min($viewsArr), $viewsArr, true);

    foreach ([$peakViewsIndex, $minIndex, $latestIndex] as $index) {
        if (is_int($index) && $index >= 0 && $index < $n) {
            $mainViewIdx[$index] = true;
        }
    }
}

$activityInsights = [];
if ($hasChart && is_int($peakViewsIndex)) {
    $peakRow = $chartRows[$peakViewsIndex] ?? [];
    $activityInsights[] = [
        'title' => 'Pico de views',
        'value' => fmt($viewsArr[$peakViewsIndex] ?? 0) . ' views',
        'meta' => day_label_range((string) ($peakRow['range_start'] ?? ($peakRow['data'] ?? '')), (string) ($peakRow['range_end'] ?? ($peakRow['data'] ?? ''))),
        'tone' => 'views',
    ];
}
if ($hasChart && is_int($peakPostsIndex) && (int) ($postsArr[$peakPostsIndex] ?? 0) > 0) {
    $postRow = $chartRows[$peakPostsIndex] ?? [];
    $activityInsights[] = [
        'title' => 'Maior ritmo de publicacao',
        'value' => fmt($postsArr[$peakPostsIndex] ?? 0) . ' posts',
        'meta' => day_label_range((string) ($postRow['range_start'] ?? ($postRow['data'] ?? '')), (string) ($postRow['range_end'] ?? ($postRow['data'] ?? ''))),
        'tone' => 'posts',
    ];
}
if ($hasChart && is_int($peakSubsIndex) && (int) ($inscArr[$peakSubsIndex] ?? 0) > 0) {
    $subsRow = $chartRows[$peakSubsIndex] ?? [];
    $activityInsights[] = [
        'title' => 'Maior captacao',
        'value' => fmt($inscArr[$peakSubsIndex] ?? 0) . ' inscricoes',
        'meta' => day_label_range((string) ($subsRow['range_start'] ?? ($subsRow['data'] ?? '')), (string) ($subsRow['range_end'] ?? ($subsRow['data'] ?? ''))),
        'tone' => 'subs',
    ];
}
if ($hasChart && is_int($latestIndex)) {
    $latestRow = $chartRows[$latestIndex] ?? [];
    $currentViewsBucket = (int) ($viewsArr[$latestIndex] ?? 0);
    $trendValue = fmt($currentViewsBucket) . ' views';
    $trendMeta = 'Ultima janela do periodo';
    $trendTone = 'flat';

    if (is_int($previousIndex)) {
        $previousViewsBucket = (int) ($viewsArr[$previousIndex] ?? 0);
        if ($previousViewsBucket === 0 && $currentViewsBucket > 0) {
            $trendValue = 'Saiu do zero';
            $trendMeta = 'Ultima janela com retomada de views';
            $trendTone = 'up';
        } elseif ($previousViewsBucket > 0) {
            $deltaPct = (($currentViewsBucket - $previousViewsBucket) / $previousViewsBucket) * 100;
            if (abs($deltaPct) < 0.5) {
                $trendValue = 'Janela estavel';
                $trendMeta = 'Variacao pequena frente a janela anterior';
                $trendTone = 'flat';
            } elseif ($deltaPct > 0) {
                $trendValue = 'Subiu ' . number_format(abs($deltaPct), 1, ',', '.') . '%';
                $trendMeta = 'Comparado a janela anterior';
                $trendTone = 'up';
            } else {
                $trendValue = 'Caiu ' . number_format(abs($deltaPct), 1, ',', '.') . '%';
                $trendMeta = 'Comparado a janela anterior';
                $trendTone = 'down';
            }
        }
    }

    $activityInsights[] = [
        'title' => 'Tendencia final',
        'value' => $trendValue,
        'meta' => day_label_range((string) ($latestRow['range_start'] ?? ($latestRow['data'] ?? '')), (string) ($latestRow['range_end'] ?? ($latestRow['data'] ?? ''))) . ' - ' . $trendMeta,
        'tone' => $trendTone,
    ];
}

$startLabel = date('d/m/Y', strtotime($startIn));
$endLabel = date('d/m/Y', strtotime($endIn));

$editorialCards = [
    [
        'eyebrow' => 'Mais visto',
        'tone' => 'views',
        'context' => 'Puxa audiencia',
        'post' => $topViews,
        'metric' => fmt_k((int) ($topViews['views'] ?? 0)),
        'unit' => 'visualizacoes',
    ],
    [
        'eyebrow' => 'Mais curtido',
        'tone' => 'likes',
        'context' => 'Gera reacao',
        'post' => $topLikes,
        'metric' => fmt_k((int) ($topLikes['curtidas'] ?? 0)),
        'unit' => 'curtidas',
    ],
    [
        'eyebrow' => 'Mais comentado',
        'tone' => 'comments',
        'context' => 'Gera conversa',
        'post' => $topComments,
        'metric' => fmt_k((int) ($topComments['comentarios_count'] ?? 0)),
        'unit' => 'comentarios',
    ],
];

$todayCards = [
    ['label' => 'Posts', 'value' => fmt($postsHoje), 'tone' => 'neutral'],
    ['label' => 'Views', 'value' => fmt_k($viewsHoje), 'tone' => 'views'],
    ['label' => 'Inscritos', 'value' => fmt($inscritosHoje), 'tone' => 'subs'],
    ['label' => 'Comentarios', 'value' => fmt($comentariosHoje), 'tone' => 'comments'],
];
?>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Dashboard</h1>
      <div class="admin-page-subtitle">Metricas do portal, desempenho editorial e visao diaria do admin.</div>
      <div class="mt-3 flex flex-wrap items-center gap-2">
        <div class="admin-chip<?= $isRemoteTarget ? ' border-cyan-500/30 text-cyan-200' : '' ?>">
          Ambiente alvo: <?= e($targetEnvironmentLabel) ?>
        </div>
        <?php if ($isRemoteTarget): ?>
          <div class="admin-chip border-emerald-500/30 text-emerald-200">
            Lendo dados remotos para <?= e($targetEnvironmentLabel) ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="admin-page-actions">
      <div class="admin-chip">
        <i class="fa-regular fa-calendar"></i>
        <?= e($startLabel) ?> a <?= e($endLabel) ?>
      </div>

      <form id="js-date-range-form" action="<?= e(url('/admin')) ?>" method="get" class="flex flex-wrap items-center gap-2">
        <input type="date" id="js-start-date" name="start" value="<?= e($startIn) ?>" class="nerd-input px-3 py-2 rounded-xl text-xs font-black" aria-label="Data inicial">
        <input type="date" id="js-end-date" name="end" value="<?= e($endIn) ?>" class="nerd-input px-3 py-2 rounded-xl text-xs font-black" aria-label="Data final">
        <button type="submit" id="js-apply-range" class="admin-btn admin-btn-primary">
          <i class="fa-solid fa-filter"></i>
          Aplicar
        </button>
      </form>
    </div>
  </div>

    <section class="dashboard-kpi-grid">
    <article class="stat-card stat-card-compact admin-summary-card">
      <div class="stat-icon" style="background: linear-gradient(135deg, rgba(96,165,250,.92), rgba(59,130,246,.92)); color:#60a5fa;"><i class="fa-solid fa-newspaper"></i></div>
      <div class="stat-value neon-text" style="color:#60a5fa;"><?= fmt($curPosts) ?></div>
      <div class="stat-label">Posts no periodo</div>
      <div class="admin-summary-card__hint">Publicacoes dentro do filtro aplicado.</div>
      <div class="stat-support">
        <div class="stat-support-line"><span class="stat-support-label">Media diaria</span><span class="stat-support-value" style="color:#60a5fa;"><?= number_format($periodPostsDailyAverage, 1, ',', '.') ?></span></div>
        <div class="stat-support-line"><span class="stat-support-label">Total no painel</span><span class="stat-support-value"><?= fmt($totalPosts) ?></span></div>
      </div>
    </article>

    <article class="stat-card stat-card-compact admin-summary-card">
      <div class="stat-icon" style="background: linear-gradient(135deg, rgba(34,211,238,.92), rgba(37,99,235,.92)); color: var(--neon-blue);"><i class="fa-solid fa-eye"></i></div>
      <div class="stat-value neon-text" style="color: var(--neon-blue);"><?= fmt_k($curViews) ?></div>
      <div class="stat-label">Views no periodo</div>
      <div class="admin-summary-card__hint">Alcance registrado dentro do filtro aplicado.</div>
      <div class="stat-support">
        <div class="stat-support-line"><span class="stat-support-label">Media diaria</span><span class="stat-support-value" style="color: var(--neon-blue);"><?= fmt_k($periodViewsDailyAverage) ?></span></div>
        <div class="stat-support-line"><span class="stat-support-label">Total geral</span><span class="stat-support-value" style="color: var(--neon-blue);"><?= fmt_k($totalViews) ?></span></div>
      </div>
    </article>

    <article class="stat-card stat-card-compact admin-summary-card">
      <div class="stat-icon" style="background: linear-gradient(135deg, rgba(168,85,247,.88), rgba(236,72,153,.88)); color:#c084fc;"><i class="fa-solid fa-heart"></i></div>
      <div class="stat-value neon-text" style="color:#c084fc;"><?= fmt_k($likesTotal) ?></div>
      <div class="stat-label">Engajamento geral</div>
      <div class="admin-summary-card__hint">Acumulado historico do conteudo publicado.</div>
      <div class="stat-support">
        <div class="stat-support-line"><span class="stat-support-label">Comentarios</span><span class="stat-support-value" style="color:#c084fc;"><?= fmt_k($totalComentarios) ?></span></div>
        <div class="stat-support-line"><span class="stat-support-label">Engajamento</span><span class="stat-support-value" style="color:#c084fc;"><?= number_format($engagementRate, 2, ',', '.') ?>%</span></div>
      </div>
    </article>

    <article class="stat-card stat-card-compact admin-summary-card">
      <div class="stat-icon" style="background: linear-gradient(135deg, rgba(56,189,248,.92), rgba(37,99,235,.92)); color:#38bdf8;"><i class="fa-solid fa-link"></i></div>
      <div class="stat-value neon-text" style="color:#38bdf8;"><?= fmt_k((int) ($link_clicks_periodo ?? 0)) ?></div>
      <div class="stat-label">Cliques em links</div>
      <div class="admin-summary-card__hint">Saida rastreada para Central Nerd e destinos externos.</div>
      <div class="stat-support">
        <div class="stat-support-line"><span class="stat-support-label">Hoje</span><span class="stat-support-value" style="color:#38bdf8;">+<?= fmt((int) ($link_clicks_hoje ?? 0)) ?></span></div>
        <div class="stat-support-line"><span class="stat-support-label">Unicos</span><span class="stat-support-value" style="color:#38bdf8;"><?= fmt((int) ($link_clicks_unicos ?? 0)) ?></span></div>
      </div>
    </article>

    <article class="stat-card stat-card-compact admin-summary-card">
      <div class="stat-icon" style="background: linear-gradient(135deg, rgba(34,197,94,.88), rgba(6,182,212,.88)); color:#34d399;"><i class="fa-solid fa-envelope"></i></div>
      <div class="stat-value neon-text" style="color:#34d399;"><?= fmt_k($curSubs) ?></div>
      <div class="stat-label">Inscricoes no periodo</div>
      <div class="admin-summary-card__hint">Novos cadastros dentro do filtro aplicado.</div>
      <div class="stat-support">
        <div class="stat-support-line"><span class="stat-support-label">Media diaria</span><span class="stat-support-value" style="color:#34d399;"><?= number_format($periodSubsDailyAverage, 1, ',', '.') ?></span></div>
        <div class="stat-support-line"><span class="stat-support-label">Base total</span><span class="stat-support-value" style="color:#34d399;"><?= fmt_k($totalInscritos) ?></span></div>
      </div>
    </article>
  </section>

  <section class="admin-panel">
    <div class="activity-panel-head">
      <div>
        <div class="admin-panel-title">
          <i class="fa-solid fa-chart-line text-cyan-300"></i>
          <span>Atividade</span>
        </div>
        <div class="activity-panel-copy">
          <div class="activity-panel-range"><span><?= e($startLabel) ?> a <?= e($endLabel) ?></span><span class="activity-panel-divider">&bull;</span><span><?= (int) $days ?> dias monitorados</span><span class="activity-panel-mode"><?= e($bucketModeLabel) ?></span></div>
          <?php if ($hasChart): ?>
            <div class="activity-panel-legend">
              <span class="activity-legend-item"><span class="activity-legend-dot activity-legend-views"></span>Views</span>
              <span class="activity-legend-item"><span class="activity-legend-dot activity-legend-ma"></span>Media movel 7 dias</span>
              <span class="activity-legend-item"><span class="activity-legend-dot activity-legend-posts"></span>Posts novos</span>
              <span class="activity-legend-item"><span class="activity-legend-dot activity-legend-subs"></span>Inscricoes</span>
            </div>
          <?php else: ?>
            <div class="activity-panel-empty">Ainda nao ha dados suficientes no periodo selecionado.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="activity-summary-grid">
      <div class="activity-summary-card">
        <div class="activity-summary-top">
          <div class="activity-summary-icon activity-summary-icon-views"><i class="fa-solid fa-eye"></i></div>
          <div class="activity-summary-label">Views no periodo</div>
        </div>
        <div class="activity-summary-value activity-summary-value-views"><?= fmt($curViews) ?></div>
        <div class="activity-summary-meta">Media de <?= fmt($periodViewsDailyAverage) ?> por dia</div>
      </div>
      <div class="activity-summary-card">
        <div class="activity-summary-top">
          <div class="activity-summary-icon activity-summary-icon-posts"><i class="fa-solid fa-pen-nib"></i></div>
          <div class="activity-summary-label">Posts novos</div>
        </div>
        <div class="activity-summary-value activity-summary-value-posts"><?= fmt($curPosts) ?></div>
        <div class="activity-summary-meta">Media de <?= number_format($periodPostsDailyAverage, 1, ',', '.') ?> por dia</div>
      </div>
      <div class="activity-summary-card">
        <div class="activity-summary-top">
          <div class="activity-summary-icon activity-summary-icon-subs"><i class="fa-solid fa-envelope"></i></div>
          <div class="activity-summary-label">Inscricoes</div>
        </div>
        <div class="activity-summary-value activity-summary-value-subs"><?= fmt($curSubs) ?></div>
        <div class="activity-summary-meta">Media de <?= number_format($periodSubsDailyAverage, 1, ',', '.') ?> por dia</div>
      </div>
    </div>

    <?php if (!empty($activityInsights)): ?>
      <div class="activity-insights-grid">
        <?php foreach ($activityInsights as $insight): ?>
          <div class="activity-insight-card activity-insight-<?= e((string) ($insight['tone'] ?? 'flat')) ?>">
            <div class="activity-insight-title"><?= e((string) ($insight['title'] ?? 'Insight')) ?></div>
            <div class="activity-insight-value"><?= e((string) ($insight['value'] ?? '--')) ?></div>
            <div class="activity-insight-meta"><?= e((string) ($insight['meta'] ?? '')) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="relative bg-slate-800/20 p-4 rounded-xl border border-slate-800/70">
      <svg id="activitySvg" viewBox="0 0 <?= (int) $w ?> <?= (int) $h ?>" class="w-full h-72">
        <?php for ($grid = 0; $grid <= 4; $grid++): $gridY = $padY + ($grid * ($plotH / 4)); $gridValue = (int) round($viewsMax - ($grid * ($viewsMax / 4))); ?>
          <line x1="<?= (int) $padX ?>" y1="<?= (float) $gridY ?>" x2="<?= (int) ($w - $padX) ?>" y2="<?= (float) $gridY ?>" stroke="rgba(148,163,184,0.12)" stroke-width="1" />
          <text x="<?= (int) ($padX - 10) ?>" y="<?= (float) ($gridY + 4) ?>" fill="rgba(148,163,184,0.5)" font-size="11" text-anchor="end"><?= e(fmt($gridValue)) ?></text>
        <?php endfor; ?>

        <?php if ($hasChart): ?>
          <?php $barMaxH = $plotH * 0.35; $barW = min(26, max(10, (int) round($stepX * 0.35))); foreach ($chartRows as $index => $row): $postCount = (int) ($postsArr[$index] ?? 0); $x = $padX + ($index * $stepX); $barHeight = $postCount > 0 ? (($postCount / $postsMax) * $barMaxH) : 0; $barX = $x - ($barW / 2); $barY = $padY + ($plotH - $barHeight); $rangeStart = (string) ($row['range_start'] ?? ($row['data'] ?? '')); $rangeEnd = (string) ($row['range_end'] ?? ($row['data'] ?? '')); $label = day_label_range($rangeStart, $rangeEnd); $views = (int) ($viewsArr[$index] ?? 0); $insc = (int) ($inscArr[$index] ?? 0); $ma7 = $ma7Arr[$index] ?? null; $tip = $label . ' | ' . fmt($views) . ' views | ' . fmt($postCount) . ' posts | ' . fmt($insc) . ' inscricoes'; if ($ma7 !== null) { $tip .= ' | MA7 ' . number_format((float) $ma7, 0, ',', '.'); } ?>
            <rect x="<?= (float) $barX ?>" y="<?= (float) $barY ?>" width="<?= (float) $barW ?>" height="<?= (float) $barHeight ?>" rx="6" fill="rgba(217,70,239,0.35)" stroke="rgba(217,70,239,0.25)" stroke-width="1" data-tip="<?= e($tip) ?>" class="cursor-pointer" />
          <?php endforeach; ?>
        <?php endif; ?>

        <polygon points="<?= e($area) ?>" fill="rgba(34,211,238,0.10)"></polygon>
        <polyline points="<?= e($poly) ?>" fill="none" stroke="rgba(34,211,238,0.95)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></polyline>
        <?php if ($hasChart && $maPoly !== ''): ?><polyline points="<?= e($maPoly) ?>" fill="none" stroke="rgba(226,232,240,0.55)" stroke-width="2" stroke-dasharray="6 6" stroke-linecap="round" stroke-linejoin="round"></polyline><?php endif; ?>

        <?php if ($hasChart): ?>
          <?php foreach (array_keys($mainViewIdx) as $index): $row = $chartRows[$index] ?? []; $rangeStart = (string) ($row['range_start'] ?? ($row['data'] ?? '')); $rangeEnd = (string) ($row['range_end'] ?? ($row['data'] ?? '')); $label = day_label_range($rangeStart, $rangeEnd); $views = (int) ($viewsArr[$index] ?? 0); $postCount = (int) ($postsArr[$index] ?? 0); $insc = (int) ($inscArr[$index] ?? 0); $tag = 'Views'; if ($index === array_search(max($viewsArr), $viewsArr, true)) { $tag = 'Pico de views'; } if ($index === array_search(min($viewsArr), $viewsArr, true)) { $tag = 'Vale de views'; } if ($index === ($n - 1)) { $tag = 'Ultimo dia'; } $tip = $tag . ' | ' . $label . ' | ' . fmt($views) . ' views | ' . fmt($postCount) . ' posts | ' . fmt($insc) . ' inscricoes'; $cx = (float) ($points[$index]['x'] ?? 0); $cy = (float) ($points[$index]['y'] ?? 0); ?>
            <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="7" fill="rgba(34,211,238,0.35)" stroke="rgba(34,211,238,0.95)" stroke-width="2" data-tip="<?= e($tip) ?>" class="cursor-pointer" />
          <?php endforeach; ?>

          <?php foreach ($chartRows as $index => $row): $insc = (int) ($inscArr[$index] ?? 0); $x = $padX + ($index * $stepX); $y = $padY + ($plotH - (($insc / $inscMax) * $plotH)); $rangeStart = (string) ($row['range_start'] ?? ($row['data'] ?? '')); $rangeEnd = (string) ($row['range_end'] ?? ($row['data'] ?? '')); $label = day_label_range($rangeStart, $rangeEnd); $views = (int) ($viewsArr[$index] ?? 0); $postCount = (int) ($postsArr[$index] ?? 0); $ma7 = $ma7Arr[$index] ?? null; $tip = $label . ' | ' . fmt($views) . ' views | ' . fmt($postCount) . ' posts | ' . fmt($insc) . ' inscricoes'; if ($ma7 !== null) { $tip .= ' | MA7 ' . number_format((float) $ma7, 0, ',', '.'); } ?>
            <circle cx="<?= (float) $x ?>" cy="<?= (float) $y ?>" r="5" fill="rgba(16,185,129,0.95)" stroke="rgba(16,185,129,0.35)" stroke-width="2" data-tip="<?= e($tip) ?>" class="cursor-pointer" />
          <?php endforeach; ?>
        <?php endif; ?>
      </svg>

      <?php if ($hasChart): ?>
        <div class="mt-2 grid" style="grid-template-columns: repeat(<?= (int) count($chartRows) ?>, minmax(0, 1fr));">
          <?php foreach ($chartRows as $row): ?><div class="text-center text-xs text-gray-500"><?= e(day_label_range((string) ($row['range_start'] ?? ($row['data'] ?? '')), (string) ($row['range_end'] ?? ($row['data'] ?? '')))) ?></div><?php endforeach; ?>
        </div>
        <div id="chartTip" class="pointer-events-none hidden absolute z-10 px-3 py-2 rounded-xl bg-slate-950/90 border border-cyan-500/20 text-xs text-slate-200 shadow-xl"></div>
        <script>
          (function () {
            const svg = document.getElementById('activitySvg');
            const tip = document.getElementById('chartTip');
            if (!svg || !tip) return;
            svg.addEventListener('mousemove', (event) => {
              const target = event.target;
              const message = target && target.getAttribute && target.getAttribute('data-tip');
              if (!message) {
                tip.classList.add('hidden');
                return;
              }
              const box = svg.getBoundingClientRect();
              const x = event.clientX - box.left + 12;
              const y = event.clientY - box.top - 36;
              const maxX = box.width - 240;
              tip.textContent = message;
              tip.classList.remove('hidden');
              tip.style.left = Math.max(8, Math.min(x, maxX)) + 'px';
              tip.style.top = Math.max(8, y) + 'px';
            });
            svg.addEventListener('mouseleave', () => tip.classList.add('hidden'));
          })();
        </script>
      <?php endif; ?>
    </div>
  </section>

  <section class="grid lg:grid-cols-[minmax(0,2fr)_360px] gap-6">
    <div class="admin-panel dashboard-editorial-panel">
      <div class="admin-panel-title">
        <i class="fa-solid fa-trophy text-amber-300"></i>
        <span>Destaques editoriais</span>
      </div>
      <div class="admin-panel-subtitle">Os posts com melhor desempenho ajudam a orientar pauta, capa e chamadas.</div>

      <div class="dashboard-editorial-grid mt-6">
        <?php foreach ($editorialCards as $editorialCard): ?>
          <?php
            $editorialPost = is_array($editorialCard['post'] ?? null) ? $editorialCard['post'] : null;
            $editorialTitle = admin_clean_post_title((string) ($editorialPost['titulo'] ?? ''));
            $editorialHref = dashboard_post_url($editorialPost, $targetPublicBaseUrl);
          ?>
          <article class="dashboard-editorial-card dashboard-editorial-card-<?= e((string) ($editorialCard['tone'] ?? 'views')) ?>">
            <div class="dashboard-editorial-card-top">
              <span class="dashboard-editorial-eyebrow"><?= e((string) ($editorialCard['eyebrow'] ?? 'Destaque')) ?></span>
              <span class="dashboard-editorial-context"><?= e((string) ($editorialCard['context'] ?? '')) ?></span>
            </div>
            <?php if ($editorialPost !== null): ?>
              <h4 class="dashboard-editorial-title" title="<?= e($editorialTitle) ?>">
                <?php if ($editorialHref !== ''): ?>
                  <a href="<?= e($editorialHref) ?>" target="_blank" rel="noopener noreferrer"><?= e($editorialTitle) ?></a>
                <?php else: ?>
                  <span><?= e($editorialTitle) ?></span>
                <?php endif; ?>
              </h4>
              <div class="dashboard-editorial-metric"><?= e((string) ($editorialCard['metric'] ?? '0')) ?></div>
              <div class="dashboard-editorial-unit"><?= e((string) ($editorialCard['unit'] ?? 'interacoes')) ?></div>
            <?php else: ?>
              <div class="dashboard-editorial-empty">Nenhum post disponivel.</div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>

      <?php if ($categoriaPopular): ?>
        <div class="dashboard-editorial-traction mt-6">
          <div class="dashboard-editorial-traction-copy">
            <div class="dashboard-editorial-traction-label">Categoria com maior tracao</div>
            <div class="dashboard-editorial-traction-name">
              <span class="dashboard-editorial-traction-dot" style="background: <?= e((string) ($categoriaPopular['cor'] ?? '#22d3ee')) ?>"></span>
              <strong><?= e((string) ($categoriaPopular['nome'] ?? 'Sem categoria')) ?></strong>
            </div>
            <div class="dashboard-editorial-traction-meta">Categoria que mais sustentou o volume de leitura no periodo.</div>
          </div>
          <div class="dashboard-editorial-traction-metric">
            <strong><?= fmt_k((int) ($categoriaPopular['total_views'] ?? 0)) ?></strong>
            <span>views acumuladas</span>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="admin-panel dashboard-today-panel">
      <div class="admin-panel-title">
        <i class="fa-solid fa-bolt text-yellow-300"></i>
        <span>Hoje</span>
      </div>
      <div class="admin-panel-subtitle">Leitura rapida do dia para moderar, publicar e ajustar prioridades.</div>

      <div class="dashboard-today-grid mt-6">
        <?php foreach ($todayCards as $todayCard): ?>
          <div class="dashboard-today-card dashboard-today-card-<?= e((string) ($todayCard['tone'] ?? 'neutral')) ?>">
            <span class="dashboard-today-card-label"><?= e((string) ($todayCard['label'] ?? 'Metrica')) ?></span>
            <strong class="dashboard-today-card-value"><?= e((string) ($todayCard['value'] ?? '0')) ?></strong>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="dashboard-today-pending mt-4">
        <div>
          <div class="dashboard-today-pending-label">Pendentes</div>
          <div class="dashboard-today-pending-copy">Comentarios aguardando moderacao e resposta da equipe.</div>
        </div>
        <strong><?= fmt($comentariosPendentes) ?></strong>
      </div>

      <div class="dashboard-today-approval mt-4">
        <div class="dashboard-today-approval-head">
          <span>Taxa de aprovacao</span>
          <strong><?= number_format($taxaAprovacao, 2, ',', '.') ?>%</strong>
        </div>
        <div class="dashboard-today-approval-copy">Leitura rapida da qualidade da moderacao no periodo recente.</div>
        <div class="progress-bar mt-3"><div class="progress-fill" style="width: <?= max(0, min(100, $taxaAprovacao)) ?>%"></div></div>
      </div>
    </div>
  </section>


  <section class="grid lg:grid-cols-[minmax(0,2fr)_360px] gap-6">
    <div class="admin-panel">
      <div class="flex items-center justify-between gap-4 mb-6 flex-wrap">
        <div>
          <div class="admin-panel-title">
            <i class="fa-solid fa-link text-cyan-300"></i>
            <span>Links e Central Nerd</span>
          </div>
          <div class="admin-panel-subtitle">Cliques monitorados na Central Nerd dentro do periodo selecionado.</div>
        </div>
        <a href="<?= e(url('/admin/links')) ?>" class="admin-btn admin-btn-secondary">
          <i class="fa-solid fa-arrow-up-right-from-square"></i>
          Abrir links
        </a>
      </div>

      <div class="dashboard-link-kpi-grid">
        <div class="dashboard-link-kpi-card">
          <div class="dashboard-link-kpi-label">Cliques no periodo</div>
          <div class="dashboard-link-kpi-value text-cyan-300"><?= fmt((int) ($link_clicks_periodo ?? 0)) ?></div>
          <div class="dashboard-link-kpi-meta">Interacoes registradas por redirect interno</div>
        </div>
        <div class="dashboard-link-kpi-card">
          <div class="dashboard-link-kpi-label">Cliques unicos</div>
          <div class="dashboard-link-kpi-value text-sky-200"><?= fmt((int) ($link_clicks_unicos ?? 0)) ?></div>
          <div class="dashboard-link-kpi-meta">Estimativa baseada em sessao</div>
        </div>
        <div class="dashboard-link-kpi-card">
          <div class="dashboard-link-kpi-label">Links ativos</div>
          <div class="dashboard-link-kpi-value text-emerald-300"><?= fmt((int) ($links_ativos ?? 0)) ?></div>
          <div class="dashboard-link-kpi-meta">Itens visiveis no publico agora</div>
        </div>
        <div class="dashboard-link-kpi-card">
          <div class="dashboard-link-kpi-label">Em revisao</div>
          <div class="dashboard-link-kpi-value text-amber-300"><?= fmt((int) ($links_revisao ?? 0)) ?></div>
          <div class="dashboard-link-kpi-meta">Links que pedem nova verificacao</div>
        </div>
      </div>

      <div class="dashboard-link-list">
        <div class="dashboard-link-list-head">
          <div class="dashboard-link-list-title">Links com melhor tracao</div>
          <div class="dashboard-link-list-meta">Mais clicados no periodo filtrado</div>
        </div>

        <?php if (empty($top_links_clicks ?? [])): ?>
          <div class="dashboard-link-empty">Ainda nao ha cliques registrados para montar este ranking.</div>
        <?php else: ?>
          <div class="dashboard-link-stack">
            <?php foreach (($top_links_clicks ?? []) as $topLink): ?>
              <?php $topLinkSlug = trim((string) ($topLink['slug'] ?? '')); ?>
              <?php $topLinkPublicHref = $topLinkSlug !== '' ? dashboard_public_url($targetPublicBaseUrl, 'link/' . rawurlencode($topLinkSlug)) : ''; ?>
              <div class="dashboard-link-row">
                <div class="dashboard-link-copy">
                  <div class="dashboard-link-title">
                    <?php if ($topLinkPublicHref !== ''): ?>
                      <a href="<?= e($topLinkPublicHref) ?>" target="_blank" rel="noopener noreferrer"><?= e((string) ($topLink['titulo'] ?? 'Link')) ?></a>
                    <?php else: ?>
                      <span><?= e((string) ($topLink['titulo'] ?? 'Link')) ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="dashboard-link-subtitle"><?= e(link_type_label((string) ($topLink['tipo'] ?? ''), (int) ($topLink['promocao'] ?? 0) === 1)) ?><?php if ($topLinkSlug !== ''): ?> &bull; /link/<?= e($topLinkSlug) ?><?php endif; ?></div>
                </div>
                <div class="dashboard-link-metric">
                  <strong><?= fmt((int) ($topLink['total_clicks'] ?? 0)) ?></strong>
                  <span>cliques</span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="admin-panel">
      <div class="admin-panel-title">
        <i class="fa-solid fa-bullseye text-cyan-300"></i>
        <span>Secoes mais clicadas</span>
      </div>
      <div class="admin-panel-subtitle">Leitura comercial da Central Nerd no periodo filtrado.</div>

      <div class="dashboard-link-side-meta">
        <div class="dashboard-link-side-chip">
          <span>Hoje</span>
          <strong><?= fmt((int) ($link_clicks_hoje ?? 0)) ?></strong>
        </div>
        <div class="dashboard-link-side-chip">
          <span>Periodo</span>
          <strong><?= fmt((int) ($link_clicks_periodo ?? 0)) ?></strong>
        </div>
      </div>

      <?php $sectionClicks = is_array($link_section_clicks ?? null) ? $link_section_clicks : []; ?>
      <?php $sectionTotal = max(1, array_sum(array_map(static fn(array $row): int => (int) ($row['total_clicks'] ?? 0), $sectionClicks))); ?>
      <?php if ($sectionClicks === []): ?>
        <div class="dashboard-link-empty">Assim que os links receberem cliques, esta area vai mostrar quais secoes puxam mais resultado.</div>
      <?php else: ?>
        <div class="dashboard-link-section-stack">
          <?php foreach ($sectionClicks as $sectionRow): ?>
            <?php $sectionCount = (int) ($sectionRow['total_clicks'] ?? 0); $sectionPct = (int) round(($sectionCount / $sectionTotal) * 100); ?>
            <div class="dashboard-link-section-row">
              <div class="dashboard-link-section-copy">
                <div class="dashboard-link-section-label"><?= e((string) ($sectionRow['secao'] ?? 'Secao')) ?></div>
                <div class="dashboard-link-section-bar"><span style="width: <?= max(6, min(100, $sectionPct)) ?>%"></span></div>
              </div>
              <div class="dashboard-link-section-value"><?= fmt($sectionCount) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="admin-panel">
    <div class="flex items-center justify-between gap-4 mb-6 flex-wrap">
      <div>
        <div class="admin-panel-title">
          <i class="fa-regular fa-clock text-cyan-300"></i>
          <span>Posts recentes</span>
        </div>
        <div class="admin-panel-subtitle">Ultimos 5 conteudos para revisao rapida e acesso direto as acoes do admin.</div>
      </div>
      <a href="<?= e(url('/admin/posts')) ?>" class="admin-btn admin-btn-secondary">
        <i class="fa-solid fa-arrow-right"></i>
        Abrir central
      </a>
    </div>

    <?php if (empty($postsRecentes)): ?>
      <div class="text-center py-12 border-2 border-dashed border-gray-700 rounded-xl">
        <div class="text-4xl text-cyan-300 mb-4"><i class="fa-solid fa-newspaper"></i></div>
        <h4 class="text-xl font-bold text-white mb-2">Nenhum post ainda</h4>
        <div class="text-slate-400 text-sm">Crie seu primeiro post para comecar a alimentar o portal.</div>
      </div>
    <?php else: ?>
      <div class="dashboard-recent-stack">
        <?php foreach ($postsRecentes as $post): ?>
          <?php
            $cover = cover_url($post['imagem_capa'] ?? null, $targetPublicBaseUrl);
            $tituloLimpo = admin_clean_post_title((string) ($post['titulo'] ?? ''));
            $status = (string) ($post['status'] ?? '');
            $dataPub = (string) ($post['data_publicacao'] ?? '');
            $views = (int) ($post['views'] ?? 0);
            $curtidasRecentes = (int) ($post['curtidas'] ?? 0);
            $comentariosRecentes = (int) ($post['comentarios_count'] ?? 0);
            $catNome = (string) ($post['categoria_nome'] ?? '');
            $catCor = (string) ($post['categoria_cor'] ?? '#00d4ff');
            $postId = (int) ($post['id'] ?? 0);
            $postPublicUrl = dashboard_post_url($post, $targetPublicBaseUrl);
          ?>
          <article class="dashboard-recent-row group">
            <div class="dashboard-recent-media <?= $cover === '' ? 'dashboard-recent-media-fallback' : '' ?>">
              <?php if ($cover !== ''): ?>
                <img src="<?= e($cover) ?>" alt="<?= e($tituloLimpo) ?>" class="dashboard-recent-image" onerror="this.style.display='none'">
              <?php else: ?>
                <i class="fa-solid fa-image"></i>
              <?php endif; ?>
            </div>

            <div class="dashboard-recent-body">
              <h4 class="dashboard-recent-title" title="<?= e($tituloLimpo) ?>">
                <?php if ($postPublicUrl !== ''): ?>
                  <a href="<?= e($postPublicUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($tituloLimpo) ?></a>
                <?php else: ?>
                  <span><?= e($tituloLimpo) ?></span>
                <?php endif; ?>
              </h4>

              <div class="dashboard-recent-tags">
                <?php if ($catNome !== ''): ?>
                  <span class="dashboard-recent-category">
                    <span class="dashboard-recent-category-dot" style="background: <?= e($catCor) ?>"></span>
                    <span><?= e($catNome) ?></span>
                  </span>
                <?php endif; ?>
                <span class="<?= e(status_badge_class($status)) ?>"><?= e($status) ?></span>
              </div>

              <div class="dashboard-recent-meta">
                <span><i class="fa-regular fa-calendar"></i><?= $dataPub !== '' ? e(date('d/m', strtotime($dataPub))) : '--/--' ?></span>
                <span><i class="fa-regular fa-eye"></i><?= fmt_k($views) ?></span>
                <span><i class="fa-regular fa-heart"></i><?= fmt_k($curtidasRecentes) ?></span>
                <span><i class="fa-regular fa-comments"></i><?= fmt_k($comentariosRecentes) ?></span>
              </div>
            </div>

            <?php if ($postId > 0): ?>
              <div class="dashboard-recent-actions">
                <a href="<?= e(url('/admin/editar-post?id=' . $postId)) ?>" class="dashboard-recent-action" title="Editar post" aria-label="Editar post">
                  <i class="fa-regular fa-pen-to-square"></i>
                </a>
                <a href="<?= e(url('/admin/excluir-post?id=' . $postId)) ?>" class="dashboard-recent-action dashboard-recent-action-danger" title="Excluir post" aria-label="Excluir post">
                  <i class="fa-solid fa-trash"></i>
                </a>
              </div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
