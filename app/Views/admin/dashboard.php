<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Views/admin/dashboard.php
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Dashboard do painel administrativo
 * @description Dashboard no estilo do admin do repositório, usando admin.css.
 * @usage       Renderizado por Admin\DashboardController em GET /admin.
 * @notes       Mudanças mínimas no markup; tooltips extras nos pontos-chave da linha de views.
 * ------------------------------------------------------------------------------
 */

declare(strict_types=1);

$days = (int)($days ?? 7);

/** escape */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function fmt(int|string $num): string { return number_format((int)$num, 0, ',', '.'); }
function fmt_k(int|string $num): string
{
    $n = (int)$num;
    return $n >= 1000 ? (string)round($n / 1000, 1) . 'k' : (string)$n;
}
function cover_url(?string $imagemCapa): string
{
    $raw = is_string($imagemCapa) ? trim($imagemCapa) : '';
    if ($raw === '') return '';
    $raw = ltrim($raw, '/');
    if (str_starts_with($raw, 'uploads/')) return url('/' . $raw);
    if (str_contains($raw, '/')) return url('/' . $raw);
    return url('/uploads/' . basename($raw));
}
function status_badge_class(string $status): string
{
    $s = strtolower(trim($status));
    return match ($s) {
        'publicado' => 'status-badge status-publicado',
        'rascunho'  => 'status-badge status-rascunho',
        'agendado'  => 'status-badge status-agendado',
        default     => 'status-badge',
    };
}
function day_label(string $dateStr): string
{
    if ($dateStr === '') return '--/--';
    $ts = strtotime($dateStr);
    return $ts ? date('d/m', $ts) : '--/--';
}

if (!function_exists('day_label_range')) {
function day_label_range(string $startYmd, string $endYmd): string
{
    $ts1 = strtotime($startYmd);
    $ts2 = strtotime($endYmd);
    if (!$ts1 || !$ts2) return '--/--';
    if ($startYmd === $endYmd) return date('d/m', $ts1);
    return date('d/m', $ts1) . '–' . date('d/m', $ts2);
}
}


/**
 * Agrupa série diária em buckets para melhorar legibilidade do gráfico.
 * - views/posts_novos/inscricoes: soma por bucket
 * - views_ma7: último valor não-nulo do bucket
 */
if (!function_exists('bucketize_series')) {
function bucketize_series(array $series, int $bucketSize): array
{
    if ($bucketSize <= 1) return array_values($series);

    $rows = array_values($series);
    $out = [];
    $n = count($rows);

    for ($i = 0; $i < $n; $i += $bucketSize) {
        $chunk = array_slice($rows, $i, $bucketSize);
        if (!$chunk) continue;

        $sumViews = 0;
        $sumPosts = 0;
        $sumSubs  = 0;
        $lastMa7  = null;

        $rangeStart = (string)($chunk[0]['data'] ?? '');
        $rangeEnd   = (string)($chunk[count($chunk) - 1]['data'] ?? '');

        foreach ($chunk as $r) {
            $sumViews += (int)($r['views'] ?? 0);
            $sumPosts += (int)($r['posts_novos'] ?? 0);
            $sumSubs  += (int)($r['inscricoes'] ?? 0);

            $mv = $r['views_ma7'] ?? null;
            if ($mv !== null) $lastMa7 = $mv;
        }

        $out[] = [
            'data'        => $rangeEnd,
            'range_start' => $rangeStart,
            'range_end'   => $rangeEnd,
            'views'       => $sumViews,
            'posts_novos' => $sumPosts,
            'inscricoes'  => $sumSubs,
            'views_ma7'   => $lastMa7,
        ];
    }

    return $out;
}
}


function parse_ymd(?string $s): ?string
{
    $v = is_string($s) ? trim($s) : '';
    if ($v === '') return null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return null;
    [$y, $m, $d] = array_map('intval', explode('-', $v));
    return checkdate($m, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : null;
}

function clamp_range_90(string $start, string $end): array
{
    try {
        $ds = new DateTimeImmutable($start);
        $de = new DateTimeImmutable($end);
    } catch (Throwable) {
        return [$start, $end];
    }

    if ($ds > $de) {
        [$ds, $de] = [$de, $ds];
    }

    $days = (int)$ds->diff($de)->days + 1;
    if ($days <= 90) {
        return [$ds->format('Y-m-d'), $de->format('Y-m-d')];
    }

    $ds2 = $de->modify('-89 days');
    return [$ds2->format('Y-m-d'), $de->format('Y-m-d')];
}

/**
 * Intervalo por data (start/end) — default 30 dias corridos, máximo 90.
 * Mantém $days para o resto do template (médias/labels).
 */
$todayYmd = date('Y-m-d');

$defaultEnd = $todayYmd;
$defaultStart = date('Y-m-d', strtotime('-29 days'));

if ($days > 0) {
    $defaultStart = date('Y-m-d', strtotime('-' . max(0, $days - 1) . ' days'));
}

$startIn = parse_ymd($_GET['start'] ?? ($start ?? null)) ?? $defaultStart;
$endIn   = parse_ymd($_GET['end'] ?? ($end ?? null)) ?? $defaultEnd;

[$startIn, $endIn] = clamp_range_90($startIn, $endIn);

try {
    $ds = new DateTimeImmutable($startIn);
    $de = new DateTimeImmutable($endIn);
    if ($ds > $de) {
        [$ds, $de] = [$de, $ds];
        $startIn = $ds->format('Y-m-d');
        $endIn = $de->format('Y-m-d');
    }
    $days = (int)$ds->diff($de)->days + 1;
} catch (Throwable) {
    $startIn = $defaultStart;
    $endIn = $defaultEnd;
    $days = 30;
}

// KPIs
$totalPosts = (int)($total_posts ?? 0);
$postsPublicados = (int)($posts_publicados ?? 0);
$postsRascunho = (int)($posts_rascunho ?? 0);
$postsAgendados = (int)($posts_agendados ?? 0);

$totalViews = (int)($total_views ?? 0);
$viewsHoje = (int)($views_hoje ?? 0);
$viewsSemana = (int)($views_semana ?? 0);

$likesTotal = (int)($likes_total ?? 0);
$totalComentarios = (int)($total_comentarios ?? 0);
$engagementRate = (float)($engagement_rate ?? 0);

$totalInscritos = (int)($total_inscritos ?? 0);
$inscritosNovos30 = (int)($inscritos_novos_30dias ?? 0);

// Hoje
$postsHoje = (int)($posts_hoje ?? 0);
$inscritosHoje = (int)($inscritos_hoje ?? 0);
$comentariosHoje = (int)($comentarios_hoje ?? 0);
$comentariosPendentes = (int)($comentarios_pendentes ?? 0);
$taxaAprovacao = (float)($taxa_aprovacao_comentarios ?? 0);

// Destaques
$topViews = is_array($top_post_views ?? null) ? $top_post_views : null;
$topLikes = is_array($top_post_likes ?? null) ? $top_post_likes : null;
$topComments = is_array($top_post_comments ?? null) ? $top_post_comments : null;

// Categoria
$categoriaPopular = is_array($categoria_popular ?? null) ? $categoria_popular : null;

// Série (para gráfico)
$chart = is_array($chart ?? null) ? $chart : [];
$series = is_array($chart['series'] ?? null) ? $chart['series'] : [];

$current = is_array($chart['current'] ?? null) ? $chart['current'] : [];
$curViews = (int)($current['views'] ?? 0);
$curPosts = (int)($current['posts_novos'] ?? 0);
$curSubs  = (int)($current['inscricoes'] ?? 0);

// Lista
$postsRecentes = is_array($posts_recentes ?? null) ? $posts_recentes : [];

/**
 * Chart SVG: se não há série, desenha placeholder (linha reta)
 */
// normaliza ordem por data (garante agrupamento consistente)
if (is_array($series)) {
    $series = array_values($series);
    usort($series, static fn($a, $b) => strcmp((string)($a['data'] ?? ''), (string)($b['data'] ?? '')));
}

// bucketize (melhor legibilidade quando intervalo é grande)
// regra por intervalo selecionado; fallback pelo tamanho real da série, se vier diferente do $days
$effectiveDays = max((int)$days, is_array($series) ? (int)count($series) : 0);

$bucketSize = 1;
if ($effectiveDays <= 30) {
    $bucketSize = 5;
} elseif ($effectiveDays <= 60) {
    $bucketSize = 7;
} else {
    $bucketSize = 10; // 61–90
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
$stepX = ($plotW / ($n - 1));

$viewsArr = $hasChart ? array_map(static fn($d) => (int)($d['views'] ?? 0), $chartRows) : [0, 0];
$postsArr = $hasChart ? array_map(static fn($d) => (int)($d['posts_novos'] ?? 0), $chartRows) : [0, 0];
$inscArr  = $hasChart ? array_map(static fn($d) => (int)($d['inscricoes'] ?? 0), $chartRows) : [0, 0];
$ma7Arr   = $hasChart ? array_map(static fn($d) => $d['views_ma7'] ?? null, $chartRows) : [null, null];

$viewsMax = max(1, ...$viewsArr);
$postsMax = max(1, ...$postsArr);
$inscMax  = max(1, ...$inscArr);

// line points (views)
$pts = [];
for ($i = 0; $i < $n; $i++) {
    $x = $padX + ($i * $stepX);
    $v = $viewsArr[$i] ?? 0;
    $y = $padY + ($plotH - (($v / $viewsMax) * $plotH));
    $pts[] = ['x' => $x, 'y' => $y];
}
$poly = implode(' ', array_map(static fn($p) => round($p['x'], 2) . ',' . round($p['y'], 2), $pts));
$area = $poly . ' ' . ($w - $padX) . ',' . ($h - $padY) . ' ' . $padX . ',' . ($h - $padY);

// MA7 line points
$maPts = [];
foreach ($ma7Arr as $i => $mv) {
    if ($mv === null) continue;
    $x = $padX + ($i * $stepX);
    $y = $padY + ($plotH - (((float)$mv / $viewsMax) * $plotH));
    $maPts[] = round($x, 2) . ',' . round($y, 2);
}
$maPoly = implode(' ', $maPts);

/** tooltips extras: índices principais da linha de views */
$mainViewIdx = [];
if ($hasChart) {
    $iMax = array_search(max($viewsArr), $viewsArr, true);
    $iMin = array_search(min($viewsArr), $viewsArr, true);
    $iLast = $n - 1;

    foreach ([$iMax, $iMin, $iLast] as $idx) {
        if (is_int($idx) && $idx >= 0 && $idx < $n) {
            $mainViewIdx[$idx] = true;
        }
    }
}

$startLabel = date('d/m/Y', strtotime($startIn));
$endLabel   = date('d/m/Y', strtotime($endIn));
?>

<!-- HEADER -->
<div class="flex items-start justify-between gap-4 mb-6">
  <div>
    <h1 class="font-orbitron text-2xl font-black text-white">Dashboard</h1>
    <div class="text-xs text-slate-400 mt-1">Métricas do portal • estilo admin.css</div>
  </div>

  <div class="flex items-center gap-2">
    <form action="<?= e(url('/admin')) ?>" method="get" class="flex items-center gap-2">
      <input
        type="date"
        id="startDate"
        name="start"
        value="<?= e($startIn) ?>"
        class="px-3 py-2 rounded-xl text-xs font-black border transition-all bg-slate-800/40 border-slate-700 text-slate-300 hover:border-cyan-500/40 hover:text-cyan-200"
        aria-label="Data inicial">

      <input
        type="date"
        id="endDate"
        name="end"
        value="<?= e($endIn) ?>"
        class="px-3 py-2 rounded-xl text-xs font-black border transition-all bg-slate-800/40 border-slate-700 text-slate-300 hover:border-cyan-500/40 hover:text-cyan-200"
        aria-label="Data final">

      <button
        type="submit"
        class="px-3 py-2 rounded-xl text-xs font-black border transition-all bg-cyan-500/20 border-cyan-400/40 text-cyan-200">
        Aplicar
      </button>
    </form>

    <script>
      (() => {
        const startEl = document.getElementById('startDate');
        const endEl = document.getElementById('endDate');
        if (!startEl || !endEl) return;

        const MS_DAY = 24 * 60 * 60 * 1000;

        const parse = (v) => {
          if (!v) return null;
          const [y, m, d] = v.split('-').map(Number);
          if (!y || !m || !d) return null;
          return new Date(Date.UTC(y, m - 1, d));
        };

        const fmt = (dt) => {
          const y = dt.getUTCFullYear();
          const m = String(dt.getUTCMonth() + 1).padStart(2, '0');
          const d = String(dt.getUTCDate()).padStart(2, '0');
          return `${y}-${m}-${d}`;
        };

        const clampTo90FromStart = () => {
          const s = parse(startEl.value);
          const e = parse(endEl.value);
          if (!s) return;

          const maxEnd = new Date(s.getTime() + (89 * MS_DAY));
          endEl.min = fmt(s);
          endEl.max = fmt(maxEnd);

          if (e) {
            if (e < s) endEl.value = fmt(s);
            else if (e > maxEnd) endEl.value = fmt(maxEnd);
          } else {
            endEl.value = fmt(s);
          }
        };

        const clampStartFromEnd = () => {
          const s = parse(startEl.value);
          const e = parse(endEl.value);
          if (!e) return;

          const minStart = new Date(e.getTime() - (89 * MS_DAY));
          startEl.max = fmt(e);
          startEl.min = fmt(minStart);

          if (s) {
            if (s > e) startEl.value = fmt(e);
            else if (s < minStart) startEl.value = fmt(minStart);
          } else {
            startEl.value = fmt(minStart);
          }
        };

        startEl.addEventListener('change', () => {
          clampTo90FromStart();
          clampStartFromEnd();
        });

        endEl.addEventListener('change', () => {
          clampStartFromEnd();
          clampTo90FromStart();
        });

        clampTo90FromStart();
        clampStartFromEnd();
      })();
    </script>

  </div>
</div>

<!-- ATIVIDADE -->
<div class="bg-slate-900/50 border border-cyan-500/20 rounded-2xl p-6 mb-8">
  <div class="flex items-start justify-between gap-4 mb-4">
    <div>
      <h3 class="font-orbitron text-lg font-bold text-white">📈 Atividade (<?= e($startLabel) ?> a <?= e($endLabel) ?> • <?= (int)$days ?> dias)</h3>
      <div class="text-xs text-slate-400 mt-1">
        <?php if ($hasChart): ?>
          Linha: <span class="text-cyan-300 font-bold">views</span> · Tracejado: <span class="text-slate-200 font-bold">MA7</span> · Barras: <span class="text-fuchsia-300 font-bold">posts</span> · Pontos: <span class="text-emerald-300 font-bold">inscrições</span>
        <?php else: ?>
          Sem dados em <code>estatisticas</code> no período selecionado. Exibindo zero/placeholder.
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- resumo do período -->
  <div class="grid sm:grid-cols-3 gap-3 mb-4">
    <div class="px-4 py-3 rounded-2xl bg-slate-800/30 border border-slate-700">
      <div class="text-xs text-slate-400">Views (período)</div>
      <div class="text-white text-2xl font-black"><?= fmt($curViews) ?></div>
      <div class="text-xs text-slate-500">média <?= fmt((int)round($days > 0 ? $curViews / $days : 0)) ?>/dia</div>
    </div>

    <div class="px-4 py-3 rounded-2xl bg-slate-800/30 border border-slate-700">
      <div class="text-xs text-slate-400">Posts novos (período)</div>
      <div class="text-white text-2xl font-black"><?= fmt($curPosts) ?></div>
      <div class="text-xs text-slate-500">média <?= number_format(($days > 0 ? $curPosts / $days : 0), 1, ',', '.') ?>/dia</div>
    </div>

    <div class="px-4 py-3 rounded-2xl bg-slate-800/30 border border-slate-700">
      <div class="text-xs text-slate-400">Inscrições (período)</div>
      <div class="text-white text-2xl font-black"><?= fmt($curSubs) ?></div>
      <div class="text-xs text-slate-500">média <?= number_format(($days > 0 ? $curSubs / $days : 0), 1, ',', '.') ?>/dia</div>
    </div>
  </div>

  <!-- gráfico (placeholder quando vazio) -->
  <div class="relative bg-slate-800/20 p-4 rounded-xl">
    <svg id="activitySvg" viewBox="0 0 <?= (int)$w ?> <?= (int)$h ?>" class="w-full h-72">
      <?php for ($g = 0; $g <= 4; $g++): $gy = $padY + ($g * ($plotH / 4)); ?>
        <line x1="<?= (int)$padX ?>" y1="<?= (float)$gy ?>" x2="<?= (int)($w - $padX) ?>" y2="<?= (float)$gy ?>"
              stroke="rgba(148,163,184,0.12)" stroke-width="1" />
      <?php endfor; ?>

      <?php if ($hasChart): ?>
        <?php
          $barMaxH = $plotH * 0.35;
          $barW = min(26, max(10, (int)round($stepX * 0.35)));
          foreach ($chartRows as $i => $d):
            $p = (int)($postsArr[$i] ?? 0);
            $x = $padX + ($i * $stepX);
            $bh = $p > 0 ? (($p / $postsMax) * $barMaxH) : 0;
            $bx = $x - ($barW / 2);
            $by = $padY + ($plotH - $bh);
            $rangeStart = (string)($d['range_start'] ?? ($d['data'] ?? ''));
            $rangeEnd   = (string)($d['range_end'] ?? ($d['data'] ?? ''));
            $label = day_label_range($rangeStart, $rangeEnd);
          $v = (int)($viewsArr[$i] ?? 0);
            $ins = (int)($inscArr[$i] ?? 0);
            $mv = $ma7Arr[$i] ?? null;
            $tip = $label . " • " . fmt($v) . " views • " . fmt($p) . " posts • " . fmt($ins) . " insc";
            if ($mv !== null) $tip .= " • MA7 " . number_format((float)$mv, 0, ',', '.');
        ?>
          <rect x="<?= (float)$bx ?>" y="<?= (float)$by ?>" width="<?= (float)$barW ?>" height="<?= (float)$bh ?>"
                rx="6" fill="rgba(217,70,239,0.35)" stroke="rgba(217,70,239,0.25)" stroke-width="1"
                data-tip="<?= e($tip) ?>" class="cursor-pointer" />
        <?php endforeach; ?>
      <?php endif; ?>

      <polygon points="<?= e($area) ?>" fill="rgba(34,211,238,0.10)"></polygon>
      <polyline points="<?= e($poly) ?>" fill="none" stroke="rgba(34,211,238,0.95)"
                stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></polyline>

      <?php if ($hasChart && $maPoly !== ''): ?>
        <polyline points="<?= e($maPoly) ?>" fill="none" stroke="rgba(226,232,240,0.55)"
                  stroke-width="2" stroke-dasharray="6 6" stroke-linecap="round" stroke-linejoin="round"></polyline>
      <?php endif; ?>

      <?php if ($hasChart): ?>
        <!-- ✅ tooltips extras na linha de views: pico, vale, último -->
        <?php foreach (array_keys($mainViewIdx) as $i):
            $d = $chartRows[$i] ?? [];
            $rangeStart = (string)($d['range_start'] ?? ($d['data'] ?? ''));
            $rangeEnd   = (string)($d['range_end'] ?? ($d['data'] ?? ''));
            $label = day_label_range($rangeStart, $rangeEnd);
          $v = (int)($viewsArr[$i] ?? 0);
            $p = (int)($postsArr[$i] ?? 0);
            $ins = (int)($inscArr[$i] ?? 0);

            $tag = 'Views';
            if ($i === array_search(max($viewsArr), $viewsArr, true)) $tag = 'Pico de views';
            if ($i === array_search(min($viewsArr), $viewsArr, true)) $tag = 'Vale de views';
            if ($i === ($n - 1)) $tag = 'Último dia';

            $tip = $tag . " • " . $label . " • " . fmt($v) . " views • " . fmt($p) . " posts • " . fmt($ins) . " insc";

            $cx = (float)($pts[$i]['x'] ?? 0);
            $cy = (float)($pts[$i]['y'] ?? 0);
        ?>
          <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="7"
                  fill="rgba(34,211,238,0.35)" stroke="rgba(34,211,238,0.95)" stroke-width="2"
                  data-tip="<?= e($tip) ?>" class="cursor-pointer" />
        <?php endforeach; ?>

        <!-- pontos: inscrições (já tinham tooltip) -->
        <?php foreach ($chartRows as $i => $d):
          $ins = (int)($inscArr[$i] ?? 0);
          $x = $padX + ($i * $stepX);
          $y = $padY + ($plotH - (($ins / $inscMax) * $plotH));
          $rangeStart = (string)($d['range_start'] ?? ($d['data'] ?? ''));
            $rangeEnd   = (string)($d['range_end'] ?? ($d['data'] ?? ''));
            $label = day_label_range($rangeStart, $rangeEnd);
          $v = (int)($viewsArr[$i] ?? 0);
          $p = (int)($postsArr[$i] ?? 0);
          $mv = $ma7Arr[$i] ?? null;
          $tip = $label . " • " . fmt($v) . " views • " . fmt($p) . " posts • " . fmt($ins) . " insc";
          if ($mv !== null) $tip .= " • MA7 " . number_format((float)$mv, 0, ',', '.');
        ?>
          <circle cx="<?= (float)$x ?>" cy="<?= (float)$y ?>" r="5"
                  fill="rgba(16,185,129,0.95)" stroke="rgba(16,185,129,0.35)" stroke-width="2"
                  data-tip="<?= e($tip) ?>" class="cursor-pointer" />
        <?php endforeach; ?>
      <?php endif; ?>
    </svg>

    <?php if ($hasChart): ?>
      <div class="mt-2 grid" style="grid-template-columns: repeat(<?= (int)count($chartRows) ?>, minmax(0, 1fr));">
        <?php foreach ($chartRows as $d): ?>
          <div class="text-center text-xs text-gray-500"><?= e(day_label_range((string)($d['range_start'] ?? ($d['data'] ?? '')), (string)($d['range_end'] ?? ($d['data'] ?? '')))) ?></div>
        <?php endforeach; ?>
      </div>
      <div id="chartTip"
           class="pointer-events-none hidden absolute z-10 px-3 py-2 rounded-xl bg-slate-950/90 border border-cyan-500/20 text-xs text-slate-200 shadow-xl"></div>
      <script>
        (function () {
          const svg = document.getElementById('activitySvg');
          const tip = document.getElementById('chartTip');
          if (!svg || !tip) return;

          svg.addEventListener('mousemove', (e) => {
            const t = e.target;
            const msg = t && t.getAttribute && t.getAttribute('data-tip');
            if (!msg) { tip.classList.add('hidden'); return; }

            const box = svg.getBoundingClientRect();
            const x = e.clientX - box.left + 12;
            const y = e.clientY - box.top - 36;

            const maxX = box.width - 240;
            tip.textContent = msg;
            tip.classList.remove('hidden');
            tip.style.left = Math.max(8, Math.min(x, maxX)) + 'px';
            tip.style.top = Math.max(8, y) + 'px';
          });

          svg.addEventListener('mouseleave', () => tip.classList.add('hidden'));
        })();
      </script>
    <?php endif; ?>
  </div>
</div>

<!-- KPIs -->
<div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
  <div class="stat-card">
    <div class="stat-icon" style="background: rgba(59,130,246,0.18);">📝</div>
    <div class="stat-value neon-text" style="color:#60a5fa;"><?= fmt($totalPosts) ?></div>
    <div class="text-slate-400 text-sm mt-2">Total Posts</div>
    <div class="flex flex-wrap gap-2 text-xs mt-3">
      <span class="status-badge status-publicado"><?= fmt($postsPublicados) ?> pub</span>
      <span class="status-badge status-rascunho"><?= fmt($postsRascunho) ?> rasc</span>
      <span class="status-badge status-agendado"><?= fmt($postsAgendados) ?> agend</span>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="background: rgba(34,211,238,0.16);">👁️</div>
    <div class="stat-value neon-text" style="color: var(--neon-blue);"><?= fmt_k($totalViews) ?></div>
    <div class="text-slate-400 text-sm mt-2">Views Totais</div>
    <div class="flex flex-wrap gap-2 text-xs mt-3">
      <span class="px-2 py-1 rounded-full" style="background: rgba(0,212,255,0.12); color: var(--neon-blue);">+<?= fmt_k($viewsHoje) ?> hoje</span>
      <span class="px-2 py-1 rounded-full" style="background: rgba(0,212,255,0.12); color: var(--neon-blue);">+<?= fmt_k($viewsSemana) ?> 7d</span>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="background: rgba(168,85,247,0.16);">❤️</div>
    <div class="stat-value neon-text" style="color:#c084fc;"><?= fmt_k($likesTotal) ?></div>
    <div class="text-slate-400 text-sm mt-2">Curtidas</div>
    <div class="text-xs mt-3" style="color:#c084fc;">
      💬 <?= fmt_k($totalComentarios) ?> comentários
      <?php if ($engagementRate > 0): ?>
        <br>📊 <?= number_format($engagementRate, 2, ',', '.') ?>% engajamento
      <?php endif; ?>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="background: rgba(16,185,129,0.16);">📧</div>
    <div class="stat-value neon-text" style="color:#34d399;"><?= fmt_k($totalInscritos) ?></div>
    <div class="text-slate-400 text-sm mt-2">Inscritos Ativos</div>
    <div class="text-xs mt-3" style="color:#34d399;">
      +<?= fmt($inscritosNovos30) ?> últimos 30 dias
    </div>
  </div>
</div>

<!-- Destaques + Hoje -->
<div class="grid lg:grid-cols-3 gap-8 mb-8">
  <div class="lg:col-span-2 bg-slate-900/50 border border-cyan-500/20 rounded-2xl p-6">
    <h3 class="font-orbitron text-xl font-black text-white mb-6">🏆 Posts em Destaque</h3>

    <div class="grid md:grid-cols-3 gap-4">
      <div class="bg-slate-800/50 rounded-xl p-4 border border-slate-700 hover:border-cyan-500/50 transition-all">
        <div class="text-cyan-400 text-sm font-bold mb-2">👁️ Mais Visto</div>
        <?php if ($topViews): ?>
          <h4 class="text-white font-bold text-sm line-clamp-2 mb-2" title="<?= e((string)$topViews['titulo']) ?>"><?= e((string)$topViews['titulo']) ?></h4>
          <div class="text-2xl font-black text-cyan-400"><?= fmt_k((int)$topViews['views']) ?></div>
          <div class="text-xs text-gray-500">visualizações</div>
        <?php else: ?>
          <div class="text-gray-500 text-sm">Nenhum post</div>
        <?php endif; ?>
      </div>

      <div class="bg-slate-800/50 rounded-xl p-4 border border-slate-700 hover:border-purple-500/50 transition-all">
        <div class="text-purple-400 text-sm font-bold mb-2">❤️ Mais Curtido</div>
        <?php if ($topLikes): ?>
          <h4 class="text-white font-bold text-sm line-clamp-2 mb-2" title="<?= e((string)$topLikes['titulo']) ?>"><?= e((string)$topLikes['titulo']) ?></h4>
          <div class="text-2xl font-black text-purple-400"><?= fmt_k((int)$topLikes['curtidas']) ?></div>
          <div class="text-xs text-gray-500">curtidas</div>
        <?php else: ?>
          <div class="text-gray-500 text-sm">Nenhum post</div>
        <?php endif; ?>
      </div>

      <div class="bg-slate-800/50 rounded-xl p-4 border border-slate-700 hover:border-yellow-500/50 transition-all">
        <div class="text-yellow-400 text-sm font-bold mb-2">💬 Mais Comentado</div>
        <?php if ($topComments): ?>
          <h4 class="text-white font-bold text-sm line-clamp-2 mb-2" title="<?= e((string)$topComments['titulo']) ?>"><?= e((string)$topComments['titulo']) ?></h4>
          <div class="text-2xl font-black text-yellow-400"><?= fmt_k((int)$topComments['comentarios_count']) ?></div>
          <div class="text-xs text-gray-500">comentários</div>
        <?php else: ?>
          <div class="text-gray-500 text-sm">Nenhum post</div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($categoriaPopular): ?>
      <div class="mt-6 p-4 bg-slate-800/30 rounded-xl border border-slate-700">
        <div class="flex items-center justify-between">
          <div>
            <span class="text-gray-400 text-sm">Categoria mais popular</span>
            <div class="flex items-center gap-2 mt-1">
              <span class="w-3 h-3 rounded-full" style="background: <?= e((string)$categoriaPopular['cor']) ?>"></span>
              <span class="text-white font-bold"><?= e((string)$categoriaPopular['nome']) ?></span>
            </div>
          </div>
          <div class="text-right">
            <div class="text-2xl font-black text-cyan-400"><?= fmt_k((int)$categoriaPopular['total_views']) ?></div>
            <div class="text-xs text-gray-500">views</div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="bg-slate-900/50 border border-cyan-500/20 rounded-2xl p-6">
    <h3 class="font-orbitron text-xl font-black text-white mb-6">⚡ Hoje</h3>

    <div class="space-y-3">
      <div class="flex items-center justify-between p-3 bg-slate-800/50 rounded-lg">
        <span class="text-gray-400 text-sm">📰 Posts</span>
        <span class="text-white font-bold"><?= fmt($postsHoje) ?></span>
      </div>
      <div class="flex items-center justify-between p-3 bg-slate-800/50 rounded-lg">
        <span class="text-gray-400 text-sm">👁️ Views</span>
        <span class="text-cyan-400 font-bold"><?= fmt_k($viewsHoje) ?></span>
      </div>
      <div class="flex items-center justify-between p-3 bg-slate-800/50 rounded-lg">
        <span class="text-gray-400 text-sm">📧 Inscritos</span>
        <span class="text-emerald-400 font-bold"><?= fmt($inscritosHoje) ?></span>
      </div>
      <div class="flex items-center justify-between p-3 bg-slate-800/50 rounded-lg">
        <span class="text-gray-400 text-sm">💬 Comentários</span>
        <span class="text-purple-400 font-bold"><?= fmt($comentariosHoje) ?></span>
      </div>
      <div class="flex items-center justify-between p-3 bg-slate-800/50 rounded-lg border border-yellow-500/30">
        <span class="text-gray-400 text-sm">⏳ Pendentes</span>
        <span class="text-yellow-400 font-bold"><?= fmt($comentariosPendentes) ?></span>
      </div>
    </div>

    <div class="mt-4 p-3 bg-slate-800/50 rounded-lg">
      <div class="flex items-center justify-between text-sm">
        <span class="text-gray-400">Taxa aprovação</span>
        <span class="text-green-400 font-bold"><?= number_format($taxaAprovacao, 2, ',', '.') ?>%</span>
      </div>

      <div class="progress-bar mt-2">
        <div class="progress-fill" style="width: <?= max(0, min(100, $taxaAprovacao)) ?>%"></div>
      </div>
    </div>
  </div>
</div>

<!-- Posts Recentes -->
<div class="bg-slate-900/50 border border-cyan-500/20 rounded-2xl p-6">
  <div class="flex items-center justify-between mb-6">
    <h3 class="font-orbitron text-xl font-black text-white">🕐 Posts Recentes</h3>
    <span class="text-cyan-400 text-sm font-bold">Últimos 5</span>
  </div>

  <?php if (empty($postsRecentes)): ?>
    <div class="text-center py-12 border-2 border-dashed border-gray-700 rounded-xl">
      <div class="text-5xl mb-4">📝</div>
      <h4 class="text-xl font-bold text-white mb-2">Nenhum post ainda</h4>
      <div class="text-slate-400 text-sm">Crie seu primeiro post para alimentar o portal.</div>
    </div>
  <?php else: ?>
    <div class="flex flex-col gap-3">
      <?php foreach ($postsRecentes as $post): ?>
        <?php
          $cover = cover_url($post['imagem_capa'] ?? null);
          $titulo = (string)($post['titulo'] ?? '');
          $status = (string)($post['status'] ?? '');
          $dataPub = (string)($post['data_publicacao'] ?? '');
          $views = (int)($post['views'] ?? 0);
          $catNome = (string)($post['categoria_nome'] ?? '');
          $catCor = (string)($post['categoria_cor'] ?? '#00d4ff');
        ?>
        <div class="group bg-slate-800/40 hover:bg-slate-800/70 border border-slate-700 hover:border-cyan-500/50 rounded-xl p-4 transition-all flex items-center gap-4">
          <div class="w-20 h-14 rounded-lg overflow-hidden bg-slate-700/50 flex items-center justify-center flex-shrink-0">
            <?php if ($cover !== ''): ?>
              <img src="<?= e($cover) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform" onerror="this.style.display='none'">
            <?php else: ?>
              <span class="text-xl">📝</span>
            <?php endif; ?>
          </div>

          <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-3">
              <h4 class="font-bold text-white text-sm md:text-base leading-snug line-clamp-1" title="<?= e($titulo) ?>">
                <?= e($titulo) ?>
              </h4>

              <div class="hidden md:flex items-center gap-2 text-xs text-gray-400 flex-shrink-0">
                <span><?= $dataPub !== '' ? e(date('d/m', strtotime($dataPub))) : '--/--' ?></span>
                <span class="flex items-center gap-1">👁️ <?= fmt_k($views) ?></span>
              </div>
            </div>

            <div class="flex items-center gap-2 mt-1 text-xs text-gray-400">
              <?php if ($catNome !== ''): ?>
                <span class="w-2 h-2 rounded-full" style="background: <?= e($catCor) ?>"></span>
                <span class="truncate"><?= e($catNome) ?></span>
              <?php endif; ?>
              <span class="ml-2 <?= e(status_badge_class($status)) ?>"><?= e($status) ?></span>
            </div>
          </div>

          <div class="flex items-center gap-2 flex-shrink-0">
            <span class="btn-edit px-3 py-2 rounded-lg text-xs font-bold">Editar</span>
            <span class="btn-delete px-3 py-2 rounded-lg text-xs font-bold">Excluir</span>
            <span class="w-9 h-9 bg-slate-700 hover:bg-slate-600 text-gray-300 rounded-lg flex items-center justify-center transition-all" title="Ver no site">👁️</span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>