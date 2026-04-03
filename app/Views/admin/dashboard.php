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
function cover_url(?string $imagemCapa): string
{
    $raw = is_string($imagemCapa) ? trim($imagemCapa) : '';
    if ($raw === '') {
        return '';
    }

    $raw = ltrim($raw, '/');

    if (str_starts_with($raw, 'uploads/')) {
        return url('/' . $raw);
    }

    if (str_contains($raw, '/')) {
        return url('/' . $raw);
    }

    return url('/uploads/' . basename($raw));
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
$postsRecentes = is_array($posts_recentes ?? null) ? $posts_recentes : [];

if (is_array($series)) {
    $series = array_values($series);
    usort($series, static fn($a, $b) => strcmp((string) ($a['data'] ?? ''), (string) ($b['data'] ?? '')));
}

$effectiveDays = max((int) $days, is_array($series) ? (int) count($series) : 0);
$bucketSize = 1;
if ($effectiveDays <= 30) {
    $bucketSize = 5;
} elseif ($effectiveDays <= 60) {
    $bucketSize = 7;
} else {
    $bucketSize = 10;
}

if (is_array($series) && $bucketSize > 1) {
    $series = bucketize_series($series, $bucketSize);
}

$chartRows = array_values($series);
$hasChart = count($chartRows) >= 2;
$w = 920;
$h = 260;
$padX = 24;
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

$mainViewIdx = [];
if ($hasChart) {
    $maxIndex = array_search(max($viewsArr), $viewsArr, true);
    $minIndex = array_search(min($viewsArr), $viewsArr, true);
    $lastIndex = $n - 1;

    foreach ([$maxIndex, $minIndex, $lastIndex] as $index) {
        if (is_int($index) && $index >= 0 && $index < $n) {
            $mainViewIdx[$index] = true;
        }
    }
}

$startLabel = date('d/m/Y', strtotime($startIn));
$endLabel = date('d/m/Y', strtotime($endIn));
?>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Dashboard</h1>
      <div class="admin-page-subtitle">Metricas do portal, desempenho editorial e visao diaria do admin.</div>
    </div>

    <div class="admin-page-actions">
      <div class="admin-chip">
        <i class="fa-regular fa-calendar"></i>
        <?= e($startLabel) ?> a <?= e($endLabel) ?>
      </div>

      <form action="<?= e(url('/admin')) ?>" method="get">
        <input type="date" id="startDate" name="start" value="<?= e($startIn) ?>" class="nerd-input px-3 py-2 rounded-xl text-xs font-black" aria-label="Data inicial">
        <input type="date" id="endDate" name="end" value="<?= e($endIn) ?>" class="nerd-input px-3 py-2 rounded-xl text-xs font-black" aria-label="Data final">
        <button type="submit" class="admin-btn admin-btn-primary">
          <i class="fa-solid fa-filter"></i>
          Aplicar
        </button>
      </form>
    </div>
  </div>

  <section class="grid grid-cols-2 xl:grid-cols-4 gap-4 max-[520px]:grid-cols-1">
    <article class="stat-card">
      <div class="stat-icon" style="background: rgba(59,130,246,0.18);"><i class="fa-solid fa-newspaper"></i></div>
      <div class="stat-value neon-text" style="color:#60a5fa;"><?= fmt($totalPosts) ?></div>
      <div class="text-slate-400 text-sm mt-2">Total de posts</div>
      <div class="flex flex-wrap gap-2 text-xs mt-3">
        <span class="status-badge status-publicado"><?= fmt($postsPublicados) ?> publicados</span>
        <span class="status-badge status-rascunho"><?= fmt($postsRascunho) ?> rascunhos</span>
        <span class="status-badge status-agendado"><?= fmt($postsAgendados) ?> agendados</span>
      </div>
    </article>

    <article class="stat-card">
      <div class="stat-icon" style="background: rgba(34,211,238,0.16);"><i class="fa-solid fa-eye"></i></div>
      <div class="stat-value neon-text" style="color: var(--neon-blue);"><?= fmt_k($totalViews) ?></div>
      <div class="text-slate-400 text-sm mt-2">Views totais</div>
      <div class="flex flex-wrap gap-2 text-xs mt-3">
        <span class="px-2 py-1 rounded-full" style="background: rgba(0,212,255,0.12); color: var(--neon-blue);">+<?= fmt_k($viewsHoje) ?> hoje</span>
        <span class="px-2 py-1 rounded-full" style="background: rgba(0,212,255,0.12); color: var(--neon-blue);">+<?= fmt_k($viewsSemana) ?> em 7 dias</span>
      </div>
    </article>

    <article class="stat-card">
      <div class="stat-icon" style="background: rgba(168,85,247,0.16);"><i class="fa-solid fa-heart"></i></div>
      <div class="stat-value neon-text" style="color:#c084fc;"><?= fmt_k($likesTotal) ?></div>
      <div class="text-slate-400 text-sm mt-2">Curtidas e engajamento</div>
      <div class="text-xs mt-3" style="color:#c084fc;">
        <div><?= fmt_k($totalComentarios) ?> comentarios</div>
        <?php if ($engagementRate > 0): ?>
          <div><?= number_format($engagementRate, 2, ',', '.') ?>% de engajamento</div>
        <?php endif; ?>
      </div>
    </article>

    <article class="stat-card">
      <div class="stat-icon" style="background: rgba(16,185,129,0.16);"><i class="fa-solid fa-envelope"></i></div>
      <div class="stat-value neon-text" style="color:#34d399;"><?= fmt_k($totalInscritos) ?></div>
      <div class="text-slate-400 text-sm mt-2">Inscritos ativos</div>
      <div class="text-xs mt-3" style="color:#34d399;">+<?= fmt($inscritosNovos30) ?> nos ultimos 30 dias</div>
    </article>
  </section>

  <section class="admin-panel">
    <div class="flex items-start justify-between gap-4 mb-4 flex-wrap">
      <div>
        <div class="admin-panel-title">
          <i class="fa-solid fa-chart-line text-cyan-300"></i>
          <span>Atividade</span>
        </div>
        <div class="admin-panel-subtitle">
          <?= e($startLabel) ?> a <?= e($endLabel) ?> - <?= (int) $days ?> dias monitorados.
          <?php if ($hasChart): ?>
            Linha principal: views. Tracejado: media movel de 7 dias. Barras: posts novos. Pontos: inscricoes.
          <?php else: ?>
            Ainda nao ha dados suficientes no periodo selecionado.
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="grid sm:grid-cols-3 gap-3 mb-4">
      <div class="px-4 py-3 rounded-2xl bg-slate-800/30 border border-slate-700">
        <div class="text-xs text-slate-400">Views no periodo</div>
        <div class="text-white text-2xl font-black"><?= fmt($curViews) ?></div>
        <div class="text-xs text-slate-500">Media de <?= fmt((int) round($days > 0 ? $curViews / $days : 0)) ?> por dia</div>
      </div>
      <div class="px-4 py-3 rounded-2xl bg-slate-800/30 border border-slate-700">
        <div class="text-xs text-slate-400">Posts novos</div>
        <div class="text-white text-2xl font-black"><?= fmt($curPosts) ?></div>
        <div class="text-xs text-slate-500">Media de <?= number_format(($days > 0 ? $curPosts / $days : 0), 1, ',', '.') ?> por dia</div>
      </div>
      <div class="px-4 py-3 rounded-2xl bg-slate-800/30 border border-slate-700">
        <div class="text-xs text-slate-400">Inscricoes</div>
        <div class="text-white text-2xl font-black"><?= fmt($curSubs) ?></div>
        <div class="text-xs text-slate-500">Media de <?= number_format(($days > 0 ? $curSubs / $days : 0), 1, ',', '.') ?> por dia</div>
      </div>
    </div>

    <div class="relative bg-slate-800/20 p-4 rounded-xl border border-slate-800/70">
      <svg id="activitySvg" viewBox="0 0 <?= (int) $w ?> <?= (int) $h ?>" class="w-full h-72">
        <?php for ($grid = 0; $grid <= 4; $grid++): $gridY = $padY + ($grid * ($plotH / 4)); ?>
          <line x1="<?= (int) $padX ?>" y1="<?= (float) $gridY ?>" x2="<?= (int) ($w - $padX) ?>" y2="<?= (float) $gridY ?>" stroke="rgba(148,163,184,0.12)" stroke-width="1" />
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

  <section class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 admin-panel">
      <div class="admin-panel-title">
        <i class="fa-solid fa-trophy text-amber-300"></i>
        <span>Destaques editoriais</span>
      </div>
      <div class="admin-panel-subtitle">Os posts com melhor desempenho ajudam a orientar pauta, capa e chamadas.</div>

      <div class="grid md:grid-cols-3 gap-4 mt-6">
        <div class="bg-slate-800/50 rounded-xl p-4 border border-slate-700 hover:border-cyan-500/50 transition-all">
          <div class="text-cyan-400 text-sm font-bold mb-2">Mais visto</div>
          <?php if ($topViews): ?>
            <h4 class="text-white font-bold text-sm line-clamp-2 mb-2" title="<?= e((string) $topViews['titulo']) ?>"><?= e((string) $topViews['titulo']) ?></h4>
            <div class="text-2xl font-black text-cyan-400"><?= fmt_k((int) $topViews['views']) ?></div>
            <div class="text-xs text-gray-500">visualizacoes</div>
          <?php else: ?>
            <div class="text-gray-500 text-sm">Nenhum post disponivel.</div>
          <?php endif; ?>
        </div>

        <div class="bg-slate-800/50 rounded-xl p-4 border border-slate-700 hover:border-purple-500/50 transition-all">
          <div class="text-purple-400 text-sm font-bold mb-2">Mais curtido</div>
          <?php if ($topLikes): ?>
            <h4 class="text-white font-bold text-sm line-clamp-2 mb-2" title="<?= e((string) $topLikes['titulo']) ?>"><?= e((string) $topLikes['titulo']) ?></h4>
            <div class="text-2xl font-black text-purple-400"><?= fmt_k((int) $topLikes['curtidas']) ?></div>
            <div class="text-xs text-gray-500">curtidas</div>
          <?php else: ?>
            <div class="text-gray-500 text-sm">Nenhum post disponivel.</div>
          <?php endif; ?>
        </div>

        <div class="bg-slate-800/50 rounded-xl p-4 border border-slate-700 hover:border-yellow-500/50 transition-all">
          <div class="text-yellow-400 text-sm font-bold mb-2">Mais comentado</div>
          <?php if ($topComments): ?>
            <h4 class="text-white font-bold text-sm line-clamp-2 mb-2" title="<?= e((string) $topComments['titulo']) ?>"><?= e((string) $topComments['titulo']) ?></h4>
            <div class="text-2xl font-black text-yellow-400"><?= fmt_k((int) $topComments['comentarios_count']) ?></div>
            <div class="text-xs text-gray-500">comentarios</div>
          <?php else: ?>
            <div class="text-gray-500 text-sm">Nenhum post disponivel.</div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($categoriaPopular): ?>
        <div class="mt-6 p-4 bg-slate-800/30 rounded-xl border border-slate-700">
          <div class="flex items-center justify-between gap-4">
            <div>
              <div class="text-gray-400 text-sm">Categoria com maior tracao</div>
              <div class="flex items-center gap-2 mt-1">
                <span class="w-3 h-3 rounded-full" style="background: <?= e((string) $categoriaPopular['cor']) ?>"></span>
                <span class="text-white font-bold"><?= e((string) $categoriaPopular['nome']) ?></span>
              </div>
            </div>
            <div class="text-right">
              <div class="text-2xl font-black text-cyan-400"><?= fmt_k((int) $categoriaPopular['total_views']) ?></div>
              <div class="text-xs text-gray-500">views acumuladas</div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="admin-panel">
      <div class="admin-panel-title">
        <i class="fa-solid fa-bolt text-yellow-300"></i>
        <span>Hoje</span>
      </div>
      <div class="admin-panel-subtitle">Leitura rapida do dia para moderar, publicar e ajustar prioridades.</div>

      <div class="space-y-3 mt-6">
        <div class="flex items-center justify-between p-3 bg-slate-800/50 rounded-lg"><span class="text-gray-400 text-sm">Posts</span><span class="text-white font-bold"><?= fmt($postsHoje) ?></span></div>
        <div class="flex items-center justify-between p-3 bg-slate-800/50 rounded-lg"><span class="text-gray-400 text-sm">Views</span><span class="text-cyan-400 font-bold"><?= fmt_k($viewsHoje) ?></span></div>
        <div class="flex items-center justify-between p-3 bg-slate-800/50 rounded-lg"><span class="text-gray-400 text-sm">Inscritos</span><span class="text-emerald-400 font-bold"><?= fmt($inscritosHoje) ?></span></div>
        <div class="flex items-center justify-between p-3 bg-slate-800/50 rounded-lg"><span class="text-gray-400 text-sm">Comentarios</span><span class="text-purple-400 font-bold"><?= fmt($comentariosHoje) ?></span></div>
        <div class="flex items-center justify-between p-3 bg-slate-800/50 rounded-lg border border-yellow-500/30"><span class="text-gray-400 text-sm">Pendentes</span><span class="text-yellow-400 font-bold"><?= fmt($comentariosPendentes) ?></span></div>
      </div>

      <div class="mt-4 p-3 bg-slate-800/50 rounded-lg">
        <div class="flex items-center justify-between text-sm"><span class="text-gray-400">Taxa de aprovacao</span><span class="text-green-400 font-bold"><?= number_format($taxaAprovacao, 2, ',', '.') ?>%</span></div>
        <div class="progress-bar mt-2"><div class="progress-fill" style="width: <?= max(0, min(100, $taxaAprovacao)) ?>%"></div></div>
      </div>
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
      <div class="flex flex-col gap-3">
        <?php foreach ($postsRecentes as $post): ?>
          <?php $cover = cover_url($post['imagem_capa'] ?? null); $titulo = (string) ($post['titulo'] ?? ''); $status = (string) ($post['status'] ?? ''); $dataPub = (string) ($post['data_publicacao'] ?? ''); $views = (int) ($post['views'] ?? 0); $catNome = (string) ($post['categoria_nome'] ?? ''); $catCor = (string) ($post['categoria_cor'] ?? '#00d4ff'); $postId = (int) ($post['id'] ?? 0); ?>
          <div class="group bg-slate-800/40 hover:bg-slate-800/70 border border-slate-700 hover:border-cyan-500/50 rounded-xl p-4 transition-all flex items-center gap-4">
            <div class="w-20 h-14 rounded-lg overflow-hidden bg-slate-700/50 flex items-center justify-center flex-shrink-0 text-cyan-300">
              <?php if ($cover !== ''): ?>
                <img src="<?= e($cover) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform" onerror="this.style.display='none'">
              <?php else: ?>
                <i class="fa-solid fa-image"></i>
              <?php endif; ?>
            </div>

            <div class="min-w-0 flex-1">
              <div class="flex items-start justify-between gap-3">
                <h4 class="font-bold text-white text-sm md:text-base leading-snug line-clamp-1" title="<?= e($titulo) ?>"><?= e($titulo) ?></h4>
                <div class="hidden md:flex items-center gap-2 text-xs text-gray-400 flex-shrink-0"><span><?= $dataPub !== '' ? e(date('d/m', strtotime($dataPub))) : '--/--' ?></span><span class="flex items-center gap-1"><i class="fa-regular fa-eye"></i> <?= fmt_k($views) ?></span></div>
              </div>

              <div class="flex items-center gap-2 mt-1 text-xs text-gray-400 flex-wrap">
                <?php if ($catNome !== ''): ?><span class="w-2 h-2 rounded-full" style="background: <?= e($catCor) ?>"></span><span class="truncate"><?= e($catNome) ?></span><?php endif; ?>
                <span class="ml-0 md:ml-2 <?= e(status_badge_class($status)) ?>"><?= e($status) ?></span>
              </div>
            </div>

            <?php if ($postId > 0): ?>
              <div class="flex items-center gap-2 flex-shrink-0">
                <a href="<?= e(url('/admin/editar-post?id=' . $postId)) ?>" class="admin-btn admin-btn-secondary">Editar</a>
                <a href="<?= e(url('/admin/excluir-post?id=' . $postId)) ?>" class="admin-btn admin-btn-danger">Excluir</a>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<script>
  (() => {
    const startEl = document.getElementById('startDate');
    const endEl = document.getElementById('endDate');
    if (!startEl || !endEl) return;

    const MS_DAY = 24 * 60 * 60 * 1000;
    const parse = (value) => {
      if (!value) return null;
      const [year, month, day] = value.split('-').map(Number);
      if (!year || !month || !day) return null;
      return new Date(Date.UTC(year, month - 1, day));
    };
    const format = (date) => {
      const year = date.getUTCFullYear();
      const month = String(date.getUTCMonth() + 1).padStart(2, '0');
      const day = String(date.getUTCDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    };
    const clampTo90FromStart = () => {
      const start = parse(startEl.value);
      const end = parse(endEl.value);
      if (!start) return;
      const maxEnd = new Date(start.getTime() + (89 * MS_DAY));
      endEl.min = format(start);
      endEl.max = format(maxEnd);
      if (end) {
        if (end < start) endEl.value = format(start);
        else if (end > maxEnd) endEl.value = format(maxEnd);
      } else {
        endEl.value = format(start);
      }
    };
    const clampStartFromEnd = () => {
      const start = parse(startEl.value);
      const end = parse(endEl.value);
      if (!end) return;
      const minStart = new Date(end.getTime() - (89 * MS_DAY));
      startEl.max = format(end);
      startEl.min = format(minStart);
      if (start) {
        if (start > end) startEl.value = format(end);
        else if (start < minStart) startEl.value = format(minStart);
      } else {
        startEl.value = format(minStart);
      }
    };
    startEl.addEventListener('change', () => { clampTo90FromStart(); clampStartFromEnd(); });
    endEl.addEventListener('change', () => { clampStartFromEnd(); clampTo90FromStart(); });
    clampTo90FromStart();
    clampStartFromEnd();
  })();
</script>