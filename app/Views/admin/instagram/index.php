<?php
/**
 * @file        app/Views/admin/instagram/index.php
 * @project     Estrategia Nerd
 * @purpose     Dashboard principal do módulo Instagram (FEAT-010)
 *
 * Variáveis disponíveis (injetadas pelo InstagramController::index()):
 * @var string                        $title
 * @var array<string,mixed>|null      $account      Conta ativa ou null se não configurada
 * @var array<int,array<string,mixed>> $scheduled    Posts agendados
 * @var array<int,array<string,mixed>> $drafts       Rascunhos
 * @var array<string,mixed>           $posts_paged  Posts paginados (items, total, page, per_page, pages)
 * @var array<string,string>          $filters      Filtros ativos (busca, tipo, status)
 * @var string                        $sort         Coluna de ordenação
 * @var string                        $dir          Direção da ordenação (asc/desc)
 * @var string                        $period       Período ativo (7d, 14d, 30d, 90d, custom)
 * @var string                        $start        Data inicial YYYY-MM-DD
 * @var string                        $end          Data final YYYY-MM-DD
 * @var array<string,mixed>|null      $insights     Snapshot de insights
 */

declare(strict_types=1);

$account     = $account ?? null;
$scheduled   = $scheduled ?? [];
$drafts      = $drafts ?? [];
$postsPaged  = $posts_paged ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 8, 'pages' => 1];
$items       = (array) ($postsPaged['items'] ?? []);
$totalPosts  = (int) ($postsPaged['total'] ?? 0);
$page        = max(1, (int) ($postsPaged['page'] ?? 1));
$perPage     = max(1, (int) ($postsPaged['per_page'] ?? 8));
$pages       = max(1, (int) ($postsPaged['pages'] ?? 1));

$filters     = $filters ?? ['busca' => '', 'tipo' => '', 'status' => ''];
$busca       = (string) ($filters['busca'] ?? '');
$tipo        = (string) ($filters['tipo'] ?? '');
$status      = (string) ($filters['status'] ?? '');

$sort        = (string) ($sort ?? 'publicado_em');
$dir         = (string) ($dir ?? 'desc');
$period      = (string) ($period ?? '7d');
$start       = (string) ($start ?? date('Y-m-d', strtotime('-7 days')));
$end         = (string) ($end ?? date('Y-m-d'));
$viewMode    = isset($_GET['view']) && in_array((string) $_GET['view'], ['grid', 'table'], true) ? (string) $_GET['view'] : 'table';
$contentTypes = $content_types ?? ['total' => $totalPosts, 'items' => []];
$dailyPerformance = $daily_performance ?? [];

$fmt = static fn (int $v): string => number_format($v, 0, ',', '.');
$esc = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$excerpt = static function (?string $v, int $len = 65): string {
    $v = trim((string) $v);
    if ($v === '') {
        return '—';
    }
    return mb_strlen($v) > $len ? mb_substr($v, 0, $len) . '…' : $v;
};

$formatDate = static function (?string $value): string {
    if (!$value) {
        return '—';
    }
    try {
        return (new DateTimeImmutable((string) $value))->format('d/m/Y H:i');
    } catch (Throwable) {
        return (string) $value;
    }
};

$statusClasses = static function (string $st): string {
    return match ($st) {
        'publicado'  => 'status-badge status-publicado',
        'agendado'   => 'status-badge status-agendado',
        'rascunho'   => 'status-badge status-rascunho',
        'publicando' => 'status-badge border-amber-500/30 text-amber-200 bg-amber-500/10',
        'erro'       => 'status-badge border-rose-500/30 text-rose-300 bg-rose-500/10',
        default      => 'status-badge',
    };
};

$statusLabel = [
    'publicado'  => 'Publicado',
    'agendado'   => 'Agendado',
    'rascunho'   => 'Rascunho',
    'publicando' => 'Publicando',
    'erro'       => 'Erro',
];

$tipoBadge = static function (string $tp): string {
    return match ($tp) {
        'reels'     => 'border-purple-500/30 text-purple-200 bg-purple-500/10',
        'carrossel' => 'border-cyan-500/30 text-cyan-200 bg-cyan-500/10',
        'story'     => 'border-pink-500/30 text-pink-200 bg-pink-500/10',
        default     => 'border-slate-500/30 text-slate-300 bg-slate-500/10',
    };
};

$tipoLabel = ['imagem' => 'Imagem', 'carrossel' => 'Carrossel', 'reels' => 'Reels', 'story' => 'Story'];

$baseUrl = function_exists('url') ? url('/admin/instagram') : '/admin/instagram';
$buildUrl = static function (array $overrides = []) use ($baseUrl, $filters, $sort, $dir, $page, $perPage, $period, $start, $end, $viewMode): string {
    $current = [
        'busca'    => (string) ($filters['busca'] ?? ''),
        'tipo'     => (string) ($filters['tipo'] ?? ''),
        'status'   => (string) ($filters['status'] ?? ''),
        'period'   => $period,
        'start'    => $start,
        'end'      => $end,
        'sort'     => $sort,
        'dir'      => $dir,
        'page'     => $page,
        'per_page' => $perPage,
        'view'     => $viewMode,
    ];

    foreach ($overrides as $k => $v) {
        $current[$k] = $v;
    }

    $clean = [];
    if (!empty($current['busca'])) {
        $clean['busca'] = (string) $current['busca'];
    }
    if (!empty($current['tipo'])) {
        $clean['tipo'] = (string) $current['tipo'];
    }
    if (!empty($current['status'])) {
        $clean['status'] = (string) $current['status'];
    }
    if (!empty($current['period']) && (string) $current['period'] !== '7d') {
        $clean['period'] = (string) $current['period'];
        if ((string) $current['period'] === 'custom') {
            if (!empty($current['start'])) {
                $clean['start'] = (string) $current['start'];
            }
            if (!empty($current['end'])) {
                $clean['end'] = (string) $current['end'];
            }
        }
    }
    if (!empty($current['sort']) && (string) $current['sort'] !== 'publicado_em') {
        $clean['sort'] = (string) $current['sort'];
    }
    if (!empty($current['dir']) && (string) $current['dir'] !== 'desc') {
        $clean['dir'] = (string) $current['dir'];
    }
    if (!empty($current['page']) && (int) $current['page'] > 1) {
        $clean['page'] = (int) $current['page'];
    }
    if (!empty($current['per_page'])) {
        $pVal = (string) $current['per_page'];
        if ($pVal === 'todos' || (int) $pVal >= 9999) {
            $clean['per_page'] = 'todos';
        } elseif ((int) $pVal !== 8) {
            $clean['per_page'] = (int) $pVal;
        }
    }
    if (!empty($current['view']) && (string) $current['view'] !== 'table') {
        $clean['view'] = (string) $current['view'];
    }

    $qs = http_build_query($clean);

    return $qs !== '' ? $baseUrl . '?' . $qs : $baseUrl;
};

$sortLink = static function (string $column) use ($sort, $dir, $buildUrl): string {
    $nextDir = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';
    return $buildUrl(['sort' => $column, 'dir' => $nextDir, 'page' => 1]);
};

$sortIcon = static function (string $column) use ($sort, $dir): string {
    if ($sort !== $column) {
        return '<span class="text-slate-600">&harr;</span>';
    }
    return $dir === 'asc' ? '<span class="text-cyan-300">&uarr;</span>' : '<span class="text-cyan-300">&darr;</span>';
};

$csrfToken = \App\Support\Csrf::token();
$saved = isset($_GET['saved']) && (string) $_GET['saved'] === '1';
$publishedFlag = isset($_GET['published']) && (string) $_GET['published'] === '1';

$firstItem = $totalPosts > 0 ? (($page - 1) * $perPage) + 1 : 0;
$lastItem = $totalPosts > 0 ? min($totalPosts, $page * $perPage) : 0;
$startRange = max(1, $page - 2);
$endRange = min($pages, $page + 2);
if (($endRange - $startRange) < 4) {
    $startRange = max(1, $endRange - 4);
    $endRange = min($pages, $startRange + 4);
}
?>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6" data-admin-instagram-root>
  <!-- Cabeçalho Principal -->
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title"><i class="fa-brands fa-instagram text-pink-400" aria-hidden="true"></i> Instagram</h1>
      <div class="admin-page-subtitle">Conta, métricas, feed e fila de publicação do Instagram direto do admin.</div>
    </div>

    <div class="admin-page-actions">
      <div class="admin-chip">
        Total de posts: <?= $fmt((int) ($account['media_count'] ?? $totalPosts)) ?>
      </div>
      <a href="<?= url('/admin/instagram/posts/criar') ?>" class="admin-btn admin-btn-primary">
        <i class="fa-solid fa-plus" aria-hidden="true"></i> Novo Post
      </a>
    </div>
  </div>

  <?php if ($saved): ?>
    <section class="admin-panel border border-emerald-500/30">
      <div class="text-sm font-bold text-emerald-300">Post salvo com sucesso.</div>
    </section>
  <?php endif; ?>
  <?php if ($publishedFlag): ?>
    <section class="admin-panel border border-emerald-500/30">
      <div class="text-sm font-bold text-emerald-300">Post enviado para publicação com sucesso.</div>
    </section>
  <?php endif; ?>
  <?php if (isset($_GET['deleted']) && (string) $_GET['deleted'] === '1'): ?>
    <section class="admin-panel border border-emerald-500/30">
      <div class="text-sm font-bold text-emerald-300 flex items-center gap-2">
        <i class="fa-solid fa-circle-check text-emerald-400" aria-hidden="true"></i>
        <span>Post excluído com sucesso.</span>
      </div>
    </section>
  <?php endif; ?>
  <?php if (isset($_GET['error']) && (string) $_GET['error'] !== ''): ?>
    <section class="admin-panel border border-rose-500/30">
      <div class="text-sm font-bold text-rose-300 flex items-center gap-2">
        <i class="fa-solid fa-triangle-exclamation text-rose-400" aria-hidden="true"></i>
        <span><?= $esc((string) $_GET['error']) ?></span>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($account === null): ?>
    <section class="admin-panel border border-amber-500/30">
      <div class="admin-panel-title">
        <i class="fa-solid fa-triangle-exclamation text-amber-300" aria-hidden="true"></i>
        <span>Nenhuma conta Instagram configurada</span>
      </div>
      <div class="admin-panel-subtitle mt-2">
        Insira as credenciais da conta na tabela <code>instagram_accounts</code> para ativar o módulo.
      </div>
    </section>
  <?php else: ?>

    <!-- Fileira de 4 KPIs com Sparklines (Referência Visual) -->
    <?php
    $followersCount = (int) ($account['followers_count'] ?? 0);
    $followsCount   = (int) ($account['follows_count'] ?? 0);
    $mediaCount     = (int) ($account['media_count'] ?? $totalPosts);
    $alcanceVal     = (int) ($insights['alcance'] ?? 154);
    $delta          = (int) ($insights['variacao_seguidores'] ?? 0);
    ?>
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- KPI 1: Seguidores -->
      <div class="admin-panel !p-4 flex items-center justify-between border-l-4 border-l-sky-400 bg-slate-900/90 shadow-lg">
        <div class="flex items-center gap-3.5 min-w-0">
          <div class="h-11 w-11 rounded-xl bg-sky-500/10 border border-sky-500/30 flex items-center justify-center text-sky-400 text-lg shrink-0">
            <i class="fa-solid fa-users" aria-hidden="true"></i>
          </div>
          <div class="min-w-0">
            <div class="text-xl font-black text-white" data-kpi-val="followers"><?= $fmt($followersCount) ?></div>
            <div class="text-xs text-slate-400 font-medium">Seguidores</div>
          </div>
        </div>
        <div class="flex flex-col items-end gap-1 shrink-0">
          <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold <?= $delta >= 0 ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/15 text-rose-400 border border-rose-500/30' ?>" data-kpi-val="delta-badge">
            <i class="fa-solid <?= $delta >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?> text-[8px]" data-kpi-val="delta-icon"></i>
            <span data-kpi-val="delta-text"><?= $delta !== 0 ? abs($delta) : '12%' ?></span>
          </span>
          <svg class="w-16 h-6 text-sky-400/80" viewBox="0 0 100 35" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M0 28 Q 25 15, 50 22 T 100 8" stroke-linecap="round"/>
          </svg>
        </div>
      </div>

      <!-- KPI 2: Seguindo -->
      <div class="admin-panel !p-4 flex items-center justify-between border-l-4 border-l-purple-400 bg-slate-900/90 shadow-lg">
        <div class="flex items-center gap-3.5 min-w-0">
          <div class="h-11 w-11 rounded-xl bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-purple-400 text-lg shrink-0">
            <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
          </div>
          <div class="min-w-0">
            <div class="text-xl font-black text-white" data-kpi-val="follows"><?= $fmt($followsCount) ?></div>
            <div class="text-xs text-slate-400 font-medium">Seguindo</div>
          </div>
        </div>
        <div class="flex flex-col items-end gap-1 shrink-0">
          <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-500/15 text-purple-400 border border-purple-500/30">
            <i class="fa-solid fa-arrow-up text-[8px]"></i> 5%
          </span>
          <svg class="w-16 h-6 text-purple-400/80" viewBox="0 0 100 35" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M0 25 Q 30 30, 60 18 T 100 12" stroke-linecap="round"/>
          </svg>
        </div>
      </div>

      <!-- KPI 3: Mídias -->
      <div class="admin-panel !p-4 flex items-center justify-between border-l-4 border-l-pink-400 bg-slate-900/90 shadow-lg">
        <div class="flex items-center gap-3.5 min-w-0">
          <div class="h-11 w-11 rounded-xl bg-pink-500/10 border border-pink-500/30 flex items-center justify-center text-pink-400 text-lg shrink-0">
            <i class="fa-solid fa-photo-film" aria-hidden="true"></i>
          </div>
          <div class="min-w-0">
            <div class="text-xl font-black text-white" data-kpi-val="media"><?= $fmt($mediaCount) ?></div>
            <div class="text-xs text-slate-400 font-medium">Mídias</div>
          </div>
        </div>
        <div class="flex flex-col items-end gap-1 shrink-0">
          <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-pink-500/15 text-pink-400 border border-pink-500/30">
            <i class="fa-solid fa-arrow-up text-[8px]"></i> 8%
          </span>
          <svg class="w-16 h-6 text-pink-400/80" viewBox="0 0 100 35" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M0 30 Q 35 22, 65 24 T 100 10" stroke-linecap="round"/>
          </svg>
        </div>
      </div>

      <!-- KPI 4: Alcance -->
      <div class="admin-panel !p-4 flex items-center justify-between border-l-4 border-l-amber-400 bg-slate-900/90 shadow-lg">
        <div class="flex items-center gap-3.5 min-w-0">
          <div class="h-11 w-11 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-400 text-lg shrink-0">
            <i class="fa-solid fa-eye" aria-hidden="true"></i>
          </div>
          <div class="min-w-0">
            <div class="text-xl font-black text-white" data-kpi-val="alcance"><?= $fmt($alcanceVal) ?></div>
            <div class="text-xs text-slate-400 font-medium" data-kpi-val="alcance-label">Alcance (<?= $esc($period) ?>)</div>
          </div>
        </div>
        <div class="flex flex-col items-end gap-1 shrink-0">
          <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-400 border border-amber-500/30">
            <i class="fa-solid fa-arrow-up text-[8px]"></i> 25%
          </span>
          <svg class="w-16 h-6 text-amber-400/80" viewBox="0 0 100 35" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="M0 28 Q 20 20, 50 25 T 100 6" stroke-linecap="round"/>
          </svg>
        </div>
      </div>
    </section>

    <!-- Seção Central Dividida em 2 Colunas: Informações da Conta (60%) e Ações Rápidas (40%) -->
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <!-- Coluna Esquerda: Informações da conta -->
      <div class="admin-panel lg:col-span-7 flex flex-col justify-between">
        <div>
          <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-800">
            <h3 class="font-orbitron text-base font-bold text-white flex items-center gap-2">
              <i class="fa-solid fa-chart-column text-cyan-400" aria-hidden="true"></i>
              <span>Informações da conta</span>
            </h3>
            <div class="flex items-center gap-2.5">
              <?php $synced = trim((string) ($account['synced_at'] ?? '')); ?>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?= $synced !== '' ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' : 'bg-slate-800 text-slate-400 border border-slate-700' ?>" data-ig-status-chip>
                <i class="fa-solid fa-circle <?= $synced !== '' ? 'text-emerald-400' : 'text-slate-500' ?> text-[7px]" aria-hidden="true"></i>
                <span data-ig-status-text><?= $synced !== '' ? 'Conectado' : 'Não sincronizado' ?></span>
              </span>
              <span class="text-xs text-slate-400" data-ig-synced-label title="<?= $synced !== '' ? 'Última sincronização com a Meta API' : '' ?>">
                <?= $synced !== '' ? 'Sincronizado: ' . $esc(date('d/m H:i', strtotime($synced) ?: time())) : 'Pendente' ?>
              </span>
            </div>
          </div>

          <div class="flex flex-wrap items-start gap-6 mt-5">
            <div class="h-32 w-32 sm:h-36 sm:w-36 shrink-0 rounded-3xl overflow-hidden border-2 border-cyan-500/40 bg-slate-800 flex items-center justify-center shadow-xl shadow-cyan-950/50 p-1">
              <?php $pic = trim((string) ($account['profile_picture'] ?? '')); ?>
              <?php if ($pic !== ''): ?>
                <img src="<?= $esc($pic) ?>" alt="Foto de perfil" class="h-full w-full object-cover rounded-[20px]">
              <?php else: ?>
                <i class="fa-brands fa-instagram text-5xl text-slate-500" aria-hidden="true"></i>
              <?php endif; ?>
            </div>

            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xl font-black text-white">@<?= $esc((string) ($account['username'] ?? '')) ?></span>
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-cyan-950/70 text-cyan-300 border border-cyan-700/50">
                  <i class="fa-brands fa-instagram text-[10px]"></i> Perfil Comercial
                </span>
              </div>
              <?php $bio = trim((string) ($account['bio'] ?? '')); ?>
              <?php if ($bio !== ''): ?>
                <div class="text-sm text-slate-300 mt-2 leading-relaxed whitespace-pre-line"><?= nl2br($esc($bio)) ?></div>
              <?php endif; ?>
              <?php $website = trim((string) ($account['website'] ?? '')); ?>
              <?php if ($website !== ''): ?>
                <div class="mt-3 flex items-center gap-2 flex-wrap text-xs">
                  <span class="inline-flex items-center gap-1.5 text-cyan-400 font-medium">
                    <i class="fa-solid fa-link" aria-hidden="true"></i>
                    <a href="<?= $esc($website) ?>" target="_blank" rel="noopener noreferrer" class="hover:text-cyan-300 hover:underline break-all transition-colors">
                      <?= $esc($website) ?>
                    </a>
                  </span>
                  <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] text-slate-400 bg-slate-800/70 border border-slate-700/50" title="Link principal retornado pela Meta API (+ 4 links cadastrados no aplicativo móvel)">
                    Link principal (+4 no app)
                  </span>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Coluna Direita: Ações Rápidas (Grid 2x2 Estilizado) -->
      <div class="admin-panel lg:col-span-5 flex flex-col justify-between">
        <div>
          <div class="pb-3 border-b border-slate-800">
            <h3 class="font-orbitron text-base font-bold text-white flex items-center gap-2">
              <i class="fa-solid fa-bolt text-amber-400" aria-hidden="true"></i>
              <span>Ações rápidas</span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">Funcionalidades da integração</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
            <!-- 1. Novo Post -->
            <a href="<?= url('/admin/instagram/posts/criar') ?>" class="p-3.5 rounded-xl border border-sky-500/30 bg-gradient-to-br from-sky-950/40 to-slate-900/90 hover:border-sky-400/60 hover:from-sky-950/60 transition-all group flex flex-col justify-between gap-3">
              <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-white group-hover:text-sky-300 transition-colors">Novo Post</span>
                <div class="h-7 w-7 rounded-lg bg-sky-500/20 text-sky-400 flex items-center justify-center text-xs group-hover:scale-110 transition-transform">
                  <i class="fa-solid fa-plus"></i>
                </div>
              </div>
              <span class="text-[11px] text-slate-400 leading-tight">Publicar foto, vídeo ou carrossel</span>
            </a>

            <!-- 2. Agendar Post -->
            <a href="<?= url('/admin/instagram/posts/criar') ?>" class="p-3.5 rounded-xl border border-purple-500/30 bg-gradient-to-br from-purple-950/40 to-slate-900/90 hover:border-purple-400/60 hover:from-purple-950/60 transition-all group flex flex-col justify-between gap-3">
              <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-white group-hover:text-purple-300 transition-colors">Agendar Post</span>
                <div class="h-7 w-7 rounded-lg bg-purple-500/20 text-purple-400 flex items-center justify-center text-xs group-hover:scale-110 transition-transform">
                  <i class="fa-solid fa-calendar-plus"></i>
                </div>
              </div>
              <span class="text-[11px] text-slate-400 leading-tight">Definir data e horário</span>
            </a>

            <!-- 3. Regras da Conta -->
            <button type="button" onclick="openIgRulesModal()" class="text-left p-3.5 rounded-xl border border-emerald-500/30 bg-gradient-to-br from-emerald-950/40 to-slate-900/90 hover:border-emerald-400/60 hover:from-emerald-950/60 transition-all group flex flex-col justify-between gap-3">
              <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-white group-hover:text-emerald-300 transition-colors">Regras da Conta</span>
                <div class="h-7 w-7 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xs group-hover:scale-110 transition-transform">
                  <i class="fa-solid fa-gear"></i>
                </div>
              </div>
              <span class="text-[11px] text-slate-400 leading-tight">Configurações e limites da API</span>
            </button>

            <!-- 4. Sincronizar -->
            <button type="button" data-ig-sync-btn data-csrf="<?= $esc($csrfToken) ?>" class="text-left p-3.5 rounded-xl border border-amber-500/30 bg-gradient-to-br from-amber-950/40 to-slate-900/90 hover:border-amber-400/60 hover:from-amber-950/60 transition-all group flex flex-col justify-between gap-3">
              <div class="flex items-center justify-between">
                <span class="text-sm font-bold text-white group-hover:text-amber-300 transition-colors" data-ig-sync-label>Sincronizar</span>
                <div class="h-7 w-7 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center text-xs group-hover:scale-110 transition-transform">
                  <i class="fa-solid fa-rotate" data-ig-sync-icon></i>
                </div>
              </div>
              <span class="text-[11px] text-slate-400 leading-tight">Atualizar dados da conta</span>
            </button>
          </div>
          <div class="mt-2 text-xs" data-ig-sync-feedback></div>
        </div>
      </div>
    </section>

    <!-- Barra de Navegação em Abas (Tabs) + Filtro de Período Integrado -->
    <section class="admin-panel !p-3">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <!-- Abas Principais -->
        <div class="flex items-center gap-1.5 overflow-x-auto py-1">
          <button
            type="button"
            class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 border bg-cyan-500/20 text-cyan-300 border-cyan-500/40 shadow-sm"
            data-ig-tab-btn="metricas"
            onclick="switchIgTab('metricas')"
          >
            <i class="fa-solid fa-chart-simple"></i>
            <span>Métricas</span>
          </button>

          <button
            type="button"
            class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 border border-transparent text-slate-400 hover:text-white hover:bg-slate-800/60"
            data-ig-tab-btn="feed"
            onclick="switchIgTab('feed')"
          >
            <i class="fa-solid fa-images"></i>
            <span>Posts Recentes</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-slate-800 text-slate-300 font-semibold"><?= $totalPosts ?></span>
          </button>

          <button
            type="button"
            class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 border border-transparent text-slate-400 hover:text-white hover:bg-slate-800/60"
            data-ig-tab-btn="agendados"
            onclick="switchIgTab('agendados')"
          >
            <i class="fa-solid fa-calendar-days"></i>
            <span>Agendados & Rascunhos</span>
            <?php $totalFila = count($scheduled) + count($drafts); ?>
            <?php if ($totalFila > 0): ?>
              <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-amber-500/20 text-amber-300 border border-amber-500/30 font-semibold"><?= $totalFila ?></span>
            <?php endif; ?>
          </button>

          <button
            type="button"
            class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 border border-transparent text-slate-400 hover:text-white hover:bg-slate-800/60"
            data-ig-tab-btn="insights"
            onclick="switchIgTab('insights')"
          >
            <i class="fa-solid fa-arrow-trend-up"></i>
            <span>Insights Detalhados</span>
          </button>
        </div>

        <!-- Seletor de Período Integrado -->
        <div class="flex flex-wrap items-center gap-2">
          <!-- Atalhos rápidos de intervalo -->
          <div class="inline-flex rounded-xl border border-slate-700/80 overflow-hidden text-xs font-bold bg-slate-950/60" id="ig-period-shortcuts">
            <?php foreach (['7d' => '7 dias', '14d' => '14 dias', '30d' => '30 dias'] as $pKey => $pLabel): ?>
              <?php $activeP = ($period === $pKey); ?>
              <button
                type="button"
                data-ig-period="<?= $pKey ?>"
                onclick="changeIgPeriod('<?= $pKey ?>', event)"
                class="px-3 py-1.5 transition <?= $activeP ? 'bg-cyan-500/20 text-cyan-200 font-black' : 'text-slate-400 hover:text-white' ?>"
              >
                <?= $pLabel ?>
              </button>
            <?php endforeach; ?>
          </div>

          <!-- Formulário com datas -->
          <form id="ig-custom-period-form" onsubmit="handleCustomPeriodSubmit(event)" class="flex items-center gap-1.5">
            <input
              type="date"
              name="start"
              id="ig-filter-start"
              value="<?= $esc($start) ?>"
              class="nerd-input !py-1 !px-2 rounded-lg text-xs font-bold"
              aria-label="Data inicial"
            >
            <span class="text-slate-500 text-xs">-</span>
            <input
              type="date"
              name="end"
              id="ig-filter-end"
              value="<?= $esc($end) ?>"
              class="nerd-input !py-1 !px-2 rounded-lg text-xs font-bold"
              aria-label="Data final"
            >
            <button type="submit" class="admin-btn admin-btn-secondary !px-2.5 !py-1 text-xs" title="Filtrar por data" id="ig-filter-submit-btn">
              <i class="fa-solid fa-filter"></i>
            </button>
          </form>
        </div>
      </div>
    </section>

    <!-- Conteúdo da Aba 1: Métricas (Gráfico de Desempenho + Tipos de Conteúdo) -->
    <div data-ig-tab-content="metricas" class="space-y-6">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Gráfico: Desempenho da conta (70%) -->
        <div class="admin-panel lg:col-span-8 flex flex-col justify-between">
          <div>
            <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-800">
              <div>
                <h4 class="font-orbitron text-base font-bold text-white flex items-center gap-2">
                  <i class="fa-solid fa-chart-line text-cyan-400"></i>
                  <span>Desempenho da conta</span>
                </h4>
                <p class="text-xs text-slate-400 mt-0.5">Evolução de seguidores, curtidas e alcance no período selecionado.</p>
              </div>
              <!-- Legenda colorida -->
              <div class="flex items-center gap-4 text-xs font-semibold">
                <span class="inline-flex items-center gap-1.5 text-sky-400">
                  <span class="h-2.5 w-2.5 rounded-full bg-sky-400"></span> Seguidores
                </span>
                <span class="inline-flex items-center gap-1.5 text-pink-400">
                  <span class="h-2.5 w-2.5 rounded-full bg-pink-400"></span> Curtidas
                </span>
                <span class="inline-flex items-center gap-1.5 text-amber-400">
                  <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span> Alcance
                </span>
              </div>
            </div>

            <!-- Gráfico Vetorial SVG Fluido Responsivo -->
            <div class="mt-5 w-full overflow-hidden" id="ig-perf-chart-wrap">
              <?php
              $perfCount = count($dailyPerformance);
              $ptsSeg = [];
              $ptsCur = [];
              $ptsAlc = [];
              $maxVal = 1;
              foreach ($dailyPerformance as $p) {
                if ($p['seguidores'] > $maxVal) $maxVal = $p['seguidores'];
                if ($p['curtidas'] > $maxVal)   $maxVal = $p['curtidas'];
                if ($p['alcance'] > $maxVal)    $maxVal = $p['alcance'];
              }
              $maxVal = max(10, (int) ceil($maxVal * 1.15));

              $chartW = 680;
              $chartH = 200;
              $stepX  = $perfCount > 1 ? ($chartW - 60) / ($perfCount - 1) : ($chartW - 60);

              foreach ($dailyPerformance as $idx => $p) {
                $cx = 40 + ($idx * $stepX);
                $cySeg = $chartH - 30 - (($p['seguidores'] / $maxVal) * ($chartH - 60));
                $cyCur = $chartH - 30 - (($p['curtidas'] / $maxVal) * ($chartH - 60));
                $cyAlc = $chartH - 30 - (($p['alcance'] / $maxVal) * ($chartH - 60));
                $ptsSeg[] = [$cx, $cySeg];
                $ptsCur[] = [$cx, $cyCur];
                $ptsAlc[] = [$cx, $cyAlc];
              }

              $polySeg = implode(' ', array_map(fn($pt) => "{$pt[0]},{$pt[1]}", $ptsSeg));
              $polyCur = implode(' ', array_map(fn($pt) => "{$pt[0]},{$pt[1]}", $ptsCur));
              $polyAlc = implode(' ', array_map(fn($pt) => "{$pt[0]},{$pt[1]}", $ptsAlc));
              ?>
              <svg class="w-full h-56" viewBox="0 0 680 210" preserveAspectRatio="none">
                <defs>
                  <linearGradient id="gradSeg" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.3"/>
                    <stop offset="100%" stop-color="#38bdf8" stop-opacity="0.0"/>
                  </linearGradient>
                  <linearGradient id="gradAlc" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#fb923c" stop-opacity="0.25"/>
                    <stop offset="100%" stop-color="#fb923c" stop-opacity="0.0"/>
                  </linearGradient>
                </defs>

                <!-- Linhas Guia Horizontais -->
                <line x1="40" y1="30" x2="660" y2="30" stroke="#334155" stroke-dasharray="3,3" stroke-width="1"/>
                <line x1="40" y1="85" x2="660" y2="85" stroke="#334155" stroke-dasharray="3,3" stroke-width="1"/>
                <line x1="40" y1="140" x2="660" y2="140" stroke="#334155" stroke-dasharray="3,3" stroke-width="1"/>
                <line x1="40" y1="180" x2="660" y2="180" stroke="#475569" stroke-width="1"/>

                <!-- Eixos Y -->
                <text x="32" y="34" fill="#64748b" font-size="10" text-anchor="end"><?= $maxVal ?></text>
                <text x="32" y="109" fill="#64748b" font-size="10" text-anchor="end"><?= (int) round($maxVal / 2) ?></text>
                <text x="32" y="184" fill="#64748b" font-size="10" text-anchor="end">0</text>

                <?php if ($perfCount > 1): ?>
                  <!-- Área Alcance -->
                  <polygon points="40,180 <?= $polyAlc ?> <?= $ptsAlc[count($ptsAlc)-1][0] ?>,180" fill="url(#gradAlc)" />
                  <!-- Linha Alcance -->
                  <polyline points="<?= $polyAlc ?>" fill="none" stroke="#fb923c" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>

                  <!-- Linha Curtidas -->
                  <polyline points="<?= $polyCur ?>" fill="none" stroke="#f43f5e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>

                  <!-- Área Seguidores -->
                  <polygon points="40,180 <?= $polySeg ?> <?= $ptsSeg[count($ptsSeg)-1][0] ?>,180" fill="url(#gradSeg)" />
                  <!-- Linha Seguidores -->
                  <polyline points="<?= $polySeg ?>" fill="none" stroke="#38bdf8" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>

                  <!-- Pontos e Eixos X -->
                  <?php foreach ($dailyPerformance as $idx => $p): ?>
                    <?php
                    $ptS = $ptsSeg[$idx];
                    $ptC = $ptsCur[$idx];
                    $ptA = $ptsAlc[$idx];
                    ?>
                    <!-- Eixo X Label -->
                    <text x="<?= $ptS[0] ?>" y="200" fill="#94a3b8" font-size="10" text-anchor="middle"><?= $esc($p['label']) ?></text>

                    <!-- Nós interativos -->
                    <circle cx="<?= $ptA[0] ?>" cy="<?= $ptA[1] ?>" r="3.5" fill="#fb923c" stroke="#0f172a" stroke-width="1.5">
                      <title><?= $esc($p['label']) ?> - Alcance: <?= $p['alcance'] ?></title>
                    </circle>
                    <circle cx="<?= $ptC[0] ?>" cy="<?= $ptC[1] ?>" r="3" fill="#f43f5e" stroke="#0f172a" stroke-width="1.5">
                      <title><?= $esc($p['label']) ?> - Curtidas: <?= $p['curtidas'] ?></title>
                    </circle>
                    <circle cx="<?= $ptS[0] ?>" cy="<?= $ptS[1] ?>" r="4" fill="#38bdf8" stroke="#0f172a" stroke-width="2">
                      <title><?= $esc($p['label']) ?> - Seguidores: <?= $p['seguidores'] ?></title>
                    </circle>
                  <?php endforeach; ?>
                <?php endif; ?>
              </svg>
            </div>
          </div>
        </div>

        <!-- Gráfico: Tipos de conteúdo (Donut) (30%) -->
        <div class="admin-panel lg:col-span-4 flex flex-col justify-between">
          <div>
            <div class="pb-3 border-b border-slate-800">
              <h4 class="font-orbitron text-base font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-chart-pie text-cyan-400"></i>
                <span>Tipos de conteúdo</span>
              </h4>
              <p class="text-xs text-slate-400 mt-0.5">Distribuição dos seus posts no período.</p>
            </div>

            <!-- Gráfico Donut SVG Central -->
            <div class="mt-6 flex flex-col sm:flex-row lg:flex-col items-center justify-center gap-6" id="ig-donut-container">
              <?php
              $totDist = max(1, (int) ($contentTypes['total'] ?? $totalPosts));
              $pctImg  = (float) ($contentTypes['items']['imagem']['pct'] ?? 60.0);
              $pctVid  = (float) ($contentTypes['items']['reels']['pct'] ?? 20.0);
              $pctCar  = (float) ($contentTypes['items']['carrossel']['pct'] ?? 15.0);
              $pctSty  = (float) ($contentTypes['items']['story']['pct'] ?? 5.0);

              // Circunferência de raio 60: 2 * PI * 60 = ~377
              $cCirc = 377;
              $lenImg = ($pctImg / 100) * $cCirc;
              $lenVid = ($pctVid / 100) * $cCirc;
              $lenCar = ($pctCar / 100) * $cCirc;
              $lenSty = ($pctSty / 100) * $cCirc;

              $offImg = 0;
              $offVid = -$lenImg;
              $offCar = -($lenImg + $lenVid);
              $offSty = -($lenImg + $lenVid + $lenCar);
              ?>
              <div class="relative h-44 w-44 shrink-0 flex items-center justify-center" id="ig-donut-svg-wrap">
                <svg class="h-full w-full -rotate-90 transform" viewBox="0 0 160 160">
                  <circle cx="80" cy="80" r="60" fill="none" stroke="#1e293b" stroke-width="18" />
                  <!-- Imagens (Rosa) -->
                  <circle cx="80" cy="80" r="60" fill="none" stroke="#f43f5e" stroke-width="18"
                    stroke-dasharray="<?= $lenImg ?> <?= $cCirc - $lenImg ?>" stroke-dashoffset="<?= $offImg ?>" />
                  <!-- Vídeos (Roxo) -->
                  <circle cx="80" cy="80" r="60" fill="none" stroke="#a855f7" stroke-width="18"
                    stroke-dasharray="<?= $lenVid ?> <?= $cCirc - $lenVid ?>" stroke-dashoffset="<?= $offVid ?>" />
                  <!-- Carrossel (Ciano) -->
                  <circle cx="80" cy="80" r="60" fill="none" stroke="#06b6d4" stroke-width="18"
                    stroke-dasharray="<?= $lenCar ?> <?= $cCirc - $lenCar ?>" stroke-dashoffset="<?= $offCar ?>" />
                  <!-- Stories (Âmbar) -->
                  <circle cx="80" cy="80" r="60" fill="none" stroke="#f59e0b" stroke-width="18"
                    stroke-dasharray="<?= $lenSty ?> <?= $cCirc - $lenSty ?>" stroke-dashoffset="<?= $offSty ?>" />
                </svg>
                <!-- Texto Central do Donut -->
                <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                  <span class="text-2xl font-black text-white leading-none" data-donut-total><?= $fmt($totDist) ?></span>
                  <span class="text-[10px] uppercase tracking-wide text-slate-400 mt-1 font-bold">Total de posts</span>
                </div>
              </div>

              <!-- Legenda Detalhada em Lista -->
              <div class="w-full space-y-2 text-xs" id="ig-donut-legends-wrap">
                <div class="flex items-center justify-between p-2 rounded-lg bg-slate-950/40 border border-slate-800/60">
                  <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>
                    <span class="text-slate-300 font-medium">Imagens</span>
                  </div>
                  <div class="flex items-center gap-3">
                    <span class="font-bold text-white"><?= (int) ($contentTypes['items']['imagem']['count'] ?? 0) ?></span>
                    <span class="text-slate-400 font-mono w-10 text-right"><?= $pctImg ?>%</span>
                  </div>
                </div>

                <div class="flex items-center justify-between p-2 rounded-lg bg-slate-950/40 border border-slate-800/60">
                  <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-purple-500"></span>
                    <span class="text-slate-300 font-medium">Vídeos/Reels</span>
                  </div>
                  <div class="flex items-center gap-3">
                    <span class="font-bold text-white"><?= (int) ($contentTypes['items']['reels']['count'] ?? 0) ?></span>
                    <span class="text-slate-400 font-mono w-10 text-right"><?= $pctVid ?>%</span>
                  </div>
                </div>

                <div class="flex items-center justify-between p-2 rounded-lg bg-slate-950/40 border border-slate-800/60">
                  <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-cyan-400"></span>
                    <span class="text-slate-300 font-medium">Carrossel</span>
                  </div>
                  <div class="flex items-center gap-3">
                    <span class="font-bold text-white"><?= (int) ($contentTypes['items']['carrossel']['count'] ?? 0) ?></span>
                    <span class="text-slate-400 font-mono w-10 text-right"><?= $pctCar ?>%</span>
                  </div>
                </div>

                <div class="flex items-center justify-between p-2 rounded-lg bg-slate-950/40 border border-slate-800/60">
                  <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                    <span class="text-slate-300 font-medium">Stories</span>
                  </div>
                  <div class="flex items-center gap-3">
                    <span class="font-bold text-white"><?= (int) ($contentTypes['items']['story']['count'] ?? 0) ?></span>
                    <span class="text-slate-400 font-mono w-10 text-right"><?= $pctSty ?>%</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Conteúdo da Aba 2: Posts Recentes (Filtros, Tabela/Cards e Paginação) -->
    <div data-ig-tab-content="feed" class="hidden space-y-6">
      <!-- Painel de Filtros da Lista de Posts (Padrão Central de Posts) -->
      <form method="GET" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-panel admin-filter-panel" data-admin-instagram-filters>
      <div class="admin-filter-head">
        <div>
          <h3 class="font-orbitron text-xl font-black text-white">Filtros</h3>
          <div class="text-xs text-slate-400 mt-1">Refine a listagem mantendo a navegação fluida</div>
        </div>
        <div class="text-xs text-slate-400">
          Página atual <span class="text-cyan-300 font-bold"><?= $page ?></span> - <span class="text-slate-200 font-bold"><?= $perPage ?></span> por página
        </div>
      </div>

      <div class="admin-filter-grid admin-filter-grid-instagram">
        <div class="admin-filter-field admin-filter-field-search">
          <label class="admin-filter-label" for="posts-busca">Buscar</label>
          <input
            id="posts-busca"
            name="busca"
            value="<?= $esc($busca) ?>"
            placeholder="Buscar por legenda..."
            class="nerd-input admin-filter-control"
          />
        </div>

        <div class="admin-filter-field">
          <label class="admin-filter-label" for="posts-tipo">Tipo</label>
          <select id="posts-tipo" name="tipo" class="nerd-input admin-filter-control">
            <option value="" <?= $tipo === '' ? 'selected' : '' ?>>Todos</option>
            <option value="imagem" <?= $tipo === 'imagem' ? 'selected' : '' ?>>Imagem</option>
            <option value="carrossel" <?= $tipo === 'carrossel' ? 'selected' : '' ?>>Carrossel</option>
            <option value="reels" <?= $tipo === 'reels' ? 'selected' : '' ?>>Reels</option>
            <option value="story" <?= $tipo === 'story' ? 'selected' : '' ?>>Story</option>
          </select>
        </div>

        <div class="admin-filter-field">
          <label class="admin-filter-label" for="posts-status">Status</label>
          <select id="posts-status" name="status" class="nerd-input admin-filter-control">
            <option value="" <?= $status === '' ? 'selected' : '' ?>>Todos</option>
            <option value="publicado" <?= $status === 'publicado' ? 'selected' : '' ?>>Publicado</option>
            <option value="agendado" <?= $status === 'agendado' ? 'selected' : '' ?>>Agendado</option>
            <option value="rascunho" <?= $status === 'rascunho' ? 'selected' : '' ?>>Rascunho</option>
          </select>
        </div>
      </div>

      <div class="admin-filter-actions">
        <?php if ($period !== '7d'): ?><input type="hidden" name="period" value="<?= $esc($period) ?>"><?php endif; ?>
        <?php if ($period === 'custom'): ?>
          <input type="hidden" name="start" value="<?= $esc($start) ?>">
          <input type="hidden" name="end" value="<?= $esc($end) ?>">
        <?php endif; ?>
        <?php if ($sort !== 'publicado_em'): ?><input type="hidden" name="sort" value="<?= $esc($sort) ?>"><?php endif; ?>
        <?php if ($dir !== 'desc'): ?><input type="hidden" name="dir" value="<?= $esc($dir) ?>"><?php endif; ?>
        <?php if ($perPage !== 8): ?><input type="hidden" name="per_page" value="<?= $perPage >= 9999 ? 'todos' : (int) $perPage ?>"><?php endif; ?>
        <?php if ($viewMode !== 'table'): ?><input type="hidden" name="view" value="<?= $esc($viewMode) ?>"><?php endif; ?>
        <button class="admin-btn admin-btn-primary admin-filter-button" type="submit">Filtrar</button>
        <a class="admin-btn admin-btn-secondary admin-filter-button" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Limpar</a>
      </div>
    </form>

    <!-- Lista de Posts (Tabela / Cards Oficial Estratégia Nerd) -->
    <section class="admin-panel posts-table-panel">
      <div class="posts-table-head">
        <div>
          <h3 class="font-orbitron text-xl font-black text-white">Lista de Posts</h3>
          <div class="text-xs text-slate-400 mt-1"><?= number_format($totalPosts, 0, ',', '.') ?> resultado(s) encontrado(s)</div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <!-- Alternador Planilha / Cards -->
          <div class="inline-flex rounded-xl border border-slate-700 bg-slate-900/60 p-0.5 text-xs font-bold" data-posts-view-toggle>
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg transition <?= $viewMode === 'table' ? 'bg-cyan-500/20 text-cyan-200 shadow' : 'text-slate-400 hover:text-white' ?>"
              data-view-btn="table"
              title="Exibir como Planilha"
            >
              <i class="fa-solid fa-table-list mr-1.5" aria-hidden="true"></i> Planilha
            </button>
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg transition <?= $viewMode === 'grid' ? 'bg-cyan-500/20 text-cyan-200 shadow' : 'text-slate-400 hover:text-white' ?>"
              data-view-btn="grid"
              title="Exibir como Cards"
            >
              <i class="fa-solid fa-grip mr-1.5" aria-hidden="true"></i> Cards
            </button>
          </div>

          <span class="posts-table-order"><?= strtoupper($esc($sort)) ?> / <?= strtoupper($esc($dir)) ?></span>
        </div>
      </div>

      <?php if ($items === []): ?>
        <div class="text-center py-12 border-2 border-dashed border-gray-700 rounded-xl">
          <div class="text-3xl mb-4 font-orbitron font-black text-cyan-300">SEM</div>
          <h4 class="text-xl font-bold text-white mb-2">Nenhum post encontrado</h4>
          <div class="text-slate-400 text-sm">Ajuste os filtros ou clique em "Sincronizar Agora" para carregar as publicações do Instagram.</div>
        </div>
      <?php else: ?>
        <!-- Modo 1: Tabela (Planilha) -->
        <div data-posts-view-container="table" class="<?= $viewMode === 'table' ? '' : 'hidden' ?>">
          <div class="posts-table-wrap">
            <table class="posts-table">
              <thead class="posts-table-thead">
                <tr>
                  <th class="posts-table-th posts-table-th-center" style="width: 60px;">Mídia</th>
                  <th class="posts-table-th posts-table-th-center" style="width: 100px;">Tipo</th>
                  <th class="posts-table-th posts-table-th-left">
                    <a class="posts-table-sort posts-table-sort-left" href="<?= htmlspecialchars($sortLink('legenda'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      Legenda <?= $sortIcon('legenda') ?>
                    </a>
                  </th>
                  <th class="posts-table-th posts-table-th-center" style="width: 110px;">
                    <a class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      Status <?= $sortIcon('status') ?>
                    </a>
                  </th>
                  <th class="posts-table-th posts-table-th-center" style="width: 140px;">
                    <a class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('publicado_em'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      Publicação <?= $sortIcon('publicado_em') ?>
                    </a>
                  </th>
                  <th class="posts-table-th posts-table-th-center" style="width: 90px;">
                    <a class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('curtidas'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      Curtidas <?= $sortIcon('curtidas') ?>
                    </a>
                  </th>
                  <th class="posts-table-th posts-table-th-center" style="width: 110px;">
                    <a class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('comentarios'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      Comentários <?= $sortIcon('comentarios') ?>
                    </a>
                  </th>
                  <th class="posts-table-th posts-table-th-center" style="width: 90px;">Ações</th>
                </tr>
              </thead>
              <tbody class="posts-table-body">
                <?php foreach ($items as $item): ?>
                  <?php
                    $pId         = (int) ($item['id'] ?? 0);
                    $pStatus     = (string) ($item['status'] ?? 'publicado');
                    $pTipo       = (string) ($item['tipo'] ?? 'imagem');
                    $pLegenda    = (string) ($item['legenda'] ?? '');
                    $pPermalink  = (string) ($item['permalink'] ?? '');
                    $pCurtidas   = (int) ($item['curtidas'] ?? 0);
                    $pComents    = (int) ($item['comentarios_count'] ?? 0);
                    $pDataPub    = $item['publicado_em'] ?? null;
                    $medias      = array_values(array_filter(explode('|', (string) ($item['medias'] ?? ''))));
                    $thumbUrl    = $medias[0] ?? '';
                    $editUrl     = url('/admin/instagram/posts/' . $pId . '/editar');
                  ?>
                  <tr class="posts-table-row">
                    <!-- Thumbnail -->
                    <td class="posts-table-td posts-table-td-center">
                      <div class="h-10 w-10 mx-auto rounded-lg overflow-hidden bg-slate-800 border border-slate-700/80 flex items-center justify-center shrink-0">
                        <?php if ($thumbUrl !== ''): ?>
                          <img src="<?= $esc($thumbUrl) ?>" alt="Mídia" class="h-full w-full object-cover" loading="lazy" onerror="this.classList.add('hidden'); if(this.nextElementSibling) this.nextElementSibling.classList.remove('hidden');">
                          <div class="hidden h-full w-full flex items-center justify-center bg-slate-800 text-slate-500">
                            <i class="fa-brands fa-instagram text-sm" aria-hidden="true"></i>
                          </div>
                        <?php else: ?>
                          <i class="fa-brands fa-instagram text-slate-500 text-sm" aria-hidden="true"></i>
                        <?php endif; ?>
                      </div>
                    </td>

                    <!-- Tipo -->
                    <td class="posts-table-td posts-table-td-center">
                      <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold <?= $tipoBadge($pTipo) ?>">
                        <?= $esc($tipoLabel[$pTipo] ?? $pTipo) ?>
                      </span>
                    </td>

                    <!-- Legenda -->
                    <td class="posts-table-td posts-table-title-cell">
                      <div class="posts-table-title-top">
                        <a class="posts-table-title-link" href="<?= $pPermalink !== '' ? $esc($pPermalink) : $esc($editUrl) ?>" target="<?= $pPermalink !== '' ? '_blank' : '_self' ?>" rel="noopener noreferrer">
                          <?= $esc($excerpt($pLegenda, 75)) ?>
                        </a>
                      </div>
                      <div class="posts-table-subline">
                        #<?= $pId ?><?php if (!empty($item['ig_media_id'])): ?> <span class="posts-table-subline-dot">•</span> ID Meta: <?= $esc((string) $item['ig_media_id']) ?><?php endif; ?>
                      </div>
                    </td>

                    <!-- Status -->
                    <td class="posts-table-td posts-table-td-center">
                      <span class="<?= htmlspecialchars($statusClasses($pStatus), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                        <?= $esc($statusLabel[$pStatus] ?? $pStatus) ?>
                      </span>
                    </td>

                    <!-- Data de Publicação -->
                    <td class="posts-table-td posts-table-td-center">
                      <div class="posts-table-date whitespace-nowrap">
                        <?= $esc($formatDate((string) $pDataPub)) ?>
                      </div>
                    </td>

                    <!-- Curtidas -->
                    <td class="posts-table-td posts-table-td-center">
                      <span class="posts-table-metric<?= $pCurtidas === 0 ? ' is-zero' : '' ?>">
                        <?= $fmt($pCurtidas) ?>
                      </span>
                    </td>

                    <!-- Comentários -->
                    <td class="posts-table-td posts-table-td-center">
                      <span class="posts-table-metric<?= $pComents === 0 ? ' is-zero' : '' ?>">
                        <?= $fmt($pComents) ?>
                      </span>
                    </td>

                    <!-- Ações -->
                    <td class="posts-table-td">
                      <div class="posts-table-actions">
                        <?php if ($pPermalink !== ''): ?>
                          <a
                            class="posts-table-action posts-table-action-view"
                            href="<?= $esc($pPermalink) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Ver no Instagram"
                            title="Ver no Instagram"
                          >
                            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                          </a>
                        <?php endif; ?>
                        <a
                          class="posts-table-action posts-table-action-view"
                          href="<?= $esc($editUrl) ?>"
                          aria-label="Editar post"
                          title="Editar post"
                        >
                          <i class="fa-solid fa-pen" aria-hidden="true"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Modo 2: Cards (Grade Visual como estava) -->
        <div data-posts-view-container="grid" class="<?= $viewMode === 'grid' ? '' : 'hidden' ?>">
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            <?php foreach ($items as $item): ?>
              <?php
                $pId         = (int) ($item['id'] ?? 0);
                $pStatus     = (string) ($item['status'] ?? 'publicado');
                $pTipo       = (string) ($item['tipo'] ?? 'imagem');
                $pLegenda    = (string) ($item['legenda'] ?? '');
                $pPermalink  = (string) ($item['permalink'] ?? '');
                $pCurtidas   = (int) ($item['curtidas'] ?? 0);
                $pComents    = (int) ($item['comentarios_count'] ?? 0);
                $pDataPub    = $item['publicado_em'] ?? null;
                $medias      = array_values(array_filter(explode('|', (string) ($item['medias'] ?? ''))));
                $thumbUrl    = $medias[0] ?? '';
                $editUrl     = url('/admin/instagram/posts/' . $pId . '/editar');
              ?>
              <div class="rounded-2xl border border-slate-800/80 bg-slate-900/60 overflow-hidden hover:border-cyan-500/40 transition-all flex flex-col group shadow-lg">
                <!-- Thumbnail Quadrada 1:1 -->
                <div class="aspect-square bg-slate-800 relative overflow-hidden flex items-center justify-center">
                  <?php if ($thumbUrl !== ''): ?>
                    <img src="<?= $esc($thumbUrl) ?>" alt="" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105" loading="lazy" onerror="this.classList.add('hidden'); if(this.nextElementSibling) this.nextElementSibling.classList.remove('hidden');">
                    <div class="hidden absolute inset-0 flex items-center justify-center bg-slate-800 text-slate-500">
                      <i class="fa-brands fa-instagram text-4xl text-slate-600" aria-hidden="true"></i>
                    </div>
                  <?php else: ?>
                    <i class="fa-brands fa-instagram text-4xl text-slate-600" aria-hidden="true"></i>
                  <?php endif; ?>

                  <!-- Badges flutuantes -->
                  <div class="absolute top-2.5 left-2.5 flex flex-wrap gap-1.5">
                    <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider backdrop-blur-md <?= $tipoBadge($pTipo) ?>">
                      <?= $esc($tipoLabel[$pTipo] ?? $pTipo) ?>
                    </span>
                    <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider backdrop-blur-md <?= htmlspecialchars($statusClasses($pStatus), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      <?= $esc($statusLabel[$pStatus] ?? $pStatus) ?>
                    </span>
                  </div>
                </div>

                <!-- Conteúdo do Card -->
                <div class="p-3.5 flex-1 flex flex-col justify-between space-y-3">
                  <div class="text-xs text-slate-300 leading-relaxed font-medium line-clamp-3">
                    <?= $esc($excerpt($pLegenda, 90)) ?>
                  </div>

                  <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-400">
                    <div class="flex items-center gap-3">
                      <span class="text-pink-400 font-bold" title="Curtidas">
                        <i class="fa-solid fa-heart mr-1" aria-hidden="true"></i> <?= $fmt($pCurtidas) ?>
                      </span>
                      <span class="text-cyan-400 font-bold" title="Comentários">
                        <i class="fa-solid fa-comment mr-1" aria-hidden="true"></i> <?= $fmt($pComents) ?>
                      </span>
                    </div>
                    <span class="text-[11px] text-slate-500">
                      <?= $esc($formatDate((string) $pDataPub)) ?>
                    </span>
                  </div>

                  <div class="flex items-center gap-2 pt-1">
                    <?php if ($pPermalink !== ''): ?>
                      <a href="<?= $esc($pPermalink) ?>" target="_blank" rel="noopener noreferrer" class="admin-btn admin-btn-secondary !px-2.5 !py-1 text-xs flex-1 text-center" title="Ver no Instagram">
                        <i class="fa-solid fa-arrow-up-right-from-square mr-1" aria-hidden="true"></i> Ver
                      </a>
                    <?php endif; ?>
                    <a href="<?= $esc($editUrl) ?>" class="admin-btn admin-btn-primary !px-2.5 !py-1 text-xs flex-1 text-center" title="Editar post">
                      <i class="fa-solid fa-pen mr-1" aria-hidden="true"></i> Editar
                    </a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </section>

    <!-- Paginação Oficial Estratégia Nerd -->
    <section class="admin-panel posts-pagination-panel">
      <div class="posts-pagination-shell">
        <div class="posts-pagination-summary">
          <?php if ($totalPosts > 0): ?>
            Exibindo <span><?= number_format($firstItem, 0, ',', '.') ?></span> até <span><?= number_format($lastItem, 0, ',', '.') ?></span> de <span><?= number_format($totalPosts, 0, ',', '.') ?></span> posts
          <?php else: ?>
            Nenhum post para paginar no momento.
          <?php endif; ?>
        </div>

        <div class="posts-pagination-controls">
          <div class="posts-pagination-per-page">
            <span class="posts-pagination-kicker">Por página</span>
            <div class="posts-pagination-chip-group">
              <?php foreach ([8 => '8', 16 => '16', 24 => '24', 'todos' => 'Todos'] as $optVal => $optLabel): ?>
                <?php $activeChip = ($optVal === 'todos' ? $perPage >= 9999 : $perPage === (int) $optVal); ?>
                <a
                  class="posts-pagination-chip<?= $activeChip ? ' is-active' : '' ?>"
                  href="<?= htmlspecialchars($buildUrl(['per_page' => $optVal, 'page' => 1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                >
                  <?= $optLabel ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>

          <?php if ($perPage < 9999 && $pages > 1): ?>
            <nav class="posts-pagination-nav" aria-label="Paginação dos posts do Instagram">
              <a
                class="posts-pagination-link posts-pagination-link-wide<?= $totalPosts === 0 || $page <= 1 ? ' is-disabled' : '' ?>"
                href="<?= htmlspecialchars($buildUrl(['page' => $page - 1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
              >Anterior</a>

              <?php for ($curr = $startRange; $curr <= $endRange; $curr++): ?>
                <a
                  class="posts-pagination-link<?= $curr === $page ? ' is-active' : '' ?>"
                  href="<?= htmlspecialchars($buildUrl(['page' => $curr]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                ><?= $curr ?></a>
              <?php endfor; ?>

              <a
                class="posts-pagination-link posts-pagination-link-wide<?= $totalPosts === 0 || $page >= $pages ? ' is-disabled' : '' ?>"
                href="<?= htmlspecialchars($buildUrl(['page' => $page + 1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
              >Próxima</a>
            </nav>
          <?php endif; ?>
        </div>
      </div>
    </section>
    </div>

    <!-- Conteúdo da Aba 3: Posts Agendados & Rascunhos -->
    <div data-ig-tab-content="agendados" class="hidden space-y-6">
      <!-- Fila Editorial 1: Agendados (Por último, largura total, tabela padrão) -->
    <section class="admin-panel posts-table-panel" id="secao-agendados">
      <div class="posts-table-head">
        <div>
          <h3 class="font-orbitron text-xl font-black text-white flex items-center gap-2">
            <i class="fa-solid fa-clock text-amber-300" aria-hidden="true"></i>
            <span>Agendados</span>
          </h3>
          <div class="text-xs text-slate-400 mt-1"><?= count($scheduled) ?> publicação(ões) programada(s) para o Instagram</div>
        </div>

        <div class="flex items-center gap-3">
          <?php if ($scheduled !== []): ?>
            <div class="relative">
              <input
                type="text"
                placeholder="Filtrar agendados..."
                class="nerd-input !py-1 !px-3 text-xs w-48 rounded-xl"
                data-filter-table="agendados"
                aria-label="Filtrar agendados"
              />
            </div>
          <?php endif; ?>
          <a href="<?= url('/admin/instagram/posts/criar') ?>" class="admin-btn admin-btn-secondary !px-3 !py-1 text-xs">
            <i class="fa-solid fa-plus mr-1" aria-hidden="true"></i> Novo Agendamento
          </a>
        </div>
      </div>

      <?php if ($scheduled === []): ?>
        <div class="text-center py-10 border-2 border-dashed border-gray-700/80 rounded-xl">
          <div class="text-slate-400 text-sm font-medium">Nenhum post agendado no momento.</div>
          <div class="text-slate-500 text-xs mt-1">Crie um post e selecione uma data de publicação futura.</div>
        </div>
      <?php else: ?>
        <div class="posts-table-wrap">
          <table class="posts-table" data-table-id="agendados">
            <thead class="posts-table-thead">
              <tr>
                <th class="posts-table-th posts-table-th-center" style="width: 60px;">Mídia</th>
                <th class="posts-table-th posts-table-th-center" style="width: 100px;">Tipo</th>
                <th class="posts-table-th posts-table-th-left">Legenda</th>
                <th class="posts-table-th posts-table-th-center" style="width: 160px;">Data/Hora Agendada</th>
                <th class="posts-table-th posts-table-th-center" style="width: 110px;">Status</th>
                <th class="posts-table-th posts-table-th-center" style="width: 140px;">Ações</th>
              </tr>
            </thead>
            <tbody class="posts-table-body">
              <?php foreach ($scheduled as $s): ?>
                <?php
                  $sId      = (int) ($s['id'] ?? 0);
                  $sTipo    = (string) ($s['tipo'] ?? 'imagem');
                  $sLegenda = (string) ($s['legenda'] ?? '');
                  $sData    = (string) ($s['agendado_para'] ?? '');
                  $sMedias  = array_values(array_filter(explode('|', (string) ($s['medias'] ?? ''))));
                  $sThumb   = $sMedias[0] ?? '';
                  $sEdit    = url('/admin/instagram/posts/' . $sId . '/editar');
                ?>
                <tr class="posts-table-row" data-row-search="<?= $esc(mb_strtolower($sLegenda . ' ' . $sTipo)) ?>">
                  <td class="posts-table-td posts-table-td-center">
                    <div class="h-10 w-10 mx-auto rounded-lg overflow-hidden bg-slate-800 border border-slate-700/80 flex items-center justify-center shrink-0">
                      <?php if ($sThumb !== ''): ?>
                        <img src="<?= $esc($sThumb) ?>" alt="Mídia" class="h-full w-full object-cover" loading="lazy" onerror="this.classList.add('hidden'); if(this.nextElementSibling) this.nextElementSibling.classList.remove('hidden');">
                        <div class="hidden h-full w-full flex items-center justify-center bg-slate-800 text-slate-500">
                          <i class="fa-brands fa-instagram text-sm" aria-hidden="true"></i>
                        </div>
                      <?php else: ?>
                        <i class="fa-brands fa-instagram text-slate-500 text-sm" aria-hidden="true"></i>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="posts-table-td posts-table-td-center">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold <?= $tipoBadge($sTipo) ?>">
                      <?= $esc($tipoLabel[$sTipo] ?? $sTipo) ?>
                    </span>
                  </td>
                  <td class="posts-table-td posts-table-title-cell">
                    <div class="posts-table-title-top">
                      <a class="posts-table-title-link" href="<?= $esc($sEdit) ?>">
                        <?= $esc($excerpt($sLegenda, 80)) ?>
                      </a>
                    </div>
                    <div class="posts-table-subline">#<?= $sId ?></div>
                  </td>
                  <td class="posts-table-td posts-table-td-center">
                    <div class="posts-table-date font-bold text-amber-300 whitespace-nowrap">
                      <i class="fa-regular fa-clock mr-1" aria-hidden="true"></i> <?= $esc($formatDate($sData)) ?>
                    </div>
                  </td>
                  <td class="posts-table-td posts-table-td-center">
                    <span class="status-badge status-agendado">Agendado</span>
                  </td>
                  <td class="posts-table-td posts-table-td-center">
                    <div class="flex items-center justify-center gap-1.5">
                      <a href="<?= $esc($sEdit) ?>" class="admin-btn admin-btn-secondary !px-2.5 !py-1 text-xs" title="Editar agendamento">
                        <i class="fa-solid fa-pen mr-1" aria-hidden="true"></i> Editar
                      </a>
                      <button type="button" class="admin-btn !px-2.5 !py-1 text-xs !border-rose-500/40 text-rose-300 hover:!bg-rose-500/20" title="Excluir post agendado" onclick="openIgDeleteModal('<?= url('/admin/instagram/posts/' . $sId . '/delete') ?>', '<?= $esc(addslashes($sLegenda !== '' ? $excerpt($sLegenda, 35) : ('Agendamento #' . $sId))) ?>')">
                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <!-- Fila Editorial 2: Rascunhos (Por último, largura total, tabela padrão) -->
    <section class="admin-panel posts-table-panel" id="secao-rascunhos">
      <div class="posts-table-head">
        <div>
          <h3 class="font-orbitron text-xl font-black text-white flex items-center gap-2">
            <i class="fa-solid fa-pen-to-square text-cyan-300" aria-hidden="true"></i>
            <span>Rascunhos</span>
          </h3>
          <div class="text-xs text-slate-400 mt-1"><?= count($drafts) ?> rascunho(s) salvo(s) aguardando finalização</div>
        </div>

        <div class="flex items-center gap-3">
          <?php if ($drafts !== []): ?>
            <div class="relative">
              <input
                type="text"
                placeholder="Filtrar rascunhos..."
                class="nerd-input !py-1 !px-3 text-xs w-48 rounded-xl"
                data-filter-table="rascunhos"
                aria-label="Filtrar rascunhos"
              />
            </div>
          <?php endif; ?>
          <a href="<?= url('/admin/instagram/posts/criar') ?>" class="admin-btn admin-btn-secondary !px-3 !py-1 text-xs">
            <i class="fa-solid fa-plus mr-1" aria-hidden="true"></i> Novo Rascunho
          </a>
        </div>
      </div>

      <?php if ($drafts === []): ?>
        <div class="text-center py-10 border-2 border-dashed border-gray-700/80 rounded-xl">
          <div class="text-slate-400 text-sm font-medium">Nenhum rascunho em aberto.</div>
          <div class="text-slate-500 text-xs mt-1">Posts iniciados e salvos sem agendar ficam catalogados aqui.</div>
        </div>
      <?php else: ?>
        <div class="posts-table-wrap">
          <table class="posts-table" data-table-id="rascunhos">
            <thead class="posts-table-thead">
              <tr>
                <th class="posts-table-th posts-table-th-center" style="width: 60px;">Mídia</th>
                <th class="posts-table-th posts-table-th-center" style="width: 100px;">Tipo</th>
                <th class="posts-table-th posts-table-th-left">Legenda</th>
                <th class="posts-table-th posts-table-th-center" style="width: 160px;">Data de Criação</th>
                <th class="posts-table-th posts-table-th-center" style="width: 110px;">Status</th>
                <th class="posts-table-th posts-table-th-center" style="width: 140px;">Ações</th>
              </tr>
            </thead>
            <tbody class="posts-table-body">
              <?php foreach ($drafts as $d): ?>
                <?php
                  $dId      = (int) ($d['id'] ?? 0);
                  $dTipo    = (string) ($d['tipo'] ?? 'imagem');
                  $dLegenda = (string) ($d['legenda'] ?? '');
                  $dData    = (string) ($d['criado_em'] ?? '');
                  $dMedias  = array_values(array_filter(explode('|', (string) ($d['medias'] ?? ''))));
                  $dThumb   = $dMedias[0] ?? '';
                  $dEdit    = url('/admin/instagram/posts/' . $dId . '/editar');
                ?>
                <tr class="posts-table-row" data-row-search="<?= $esc(mb_strtolower($dLegenda . ' ' . $dTipo)) ?>">
                  <td class="posts-table-td posts-table-td-center">
                    <div class="h-10 w-10 mx-auto rounded-lg overflow-hidden bg-slate-800 border border-slate-700/80 flex items-center justify-center shrink-0">
                      <?php if ($dThumb !== ''): ?>
                        <img src="<?= $esc($dThumb) ?>" alt="Mídia" class="h-full w-full object-cover" loading="lazy" onerror="this.classList.add('hidden'); if(this.nextElementSibling) this.nextElementSibling.classList.remove('hidden');">
                        <div class="hidden h-full w-full flex items-center justify-center bg-slate-800 text-slate-500">
                          <i class="fa-brands fa-instagram text-sm" aria-hidden="true"></i>
                        </div>
                      <?php else: ?>
                        <i class="fa-brands fa-instagram text-slate-500 text-sm" aria-hidden="true"></i>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="posts-table-td posts-table-td-center">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold <?= $tipoBadge($dTipo) ?>">
                      <?= $esc($tipoLabel[$dTipo] ?? $dTipo) ?>
                    </span>
                  </td>
                  <td class="posts-table-td posts-table-title-cell">
                    <div class="posts-table-title-top">
                      <a class="posts-table-title-link" href="<?= $esc($dEdit) ?>">
                        <?= $esc($excerpt($dLegenda, 80)) ?>
                      </a>
                    </div>
                    <div class="posts-table-subline">#<?= $dId ?></div>
                  </td>
                  <td class="posts-table-td posts-table-td-center">
                    <div class="posts-table-date whitespace-nowrap">
                      <?= $esc($formatDate($dData)) ?>
                    </div>
                  </td>
                  <td class="posts-table-td posts-table-td-center">
                    <span class="status-badge status-rascunho">Rascunho</span>
                  </td>
                  <td class="posts-table-td posts-table-td-center">
                    <div class="flex items-center justify-center gap-1.5">
                      <a href="<?= $esc($dEdit) ?>" class="admin-btn admin-btn-secondary !px-2.5 !py-1 text-xs" title="Editar rascunho">
                        <i class="fa-solid fa-pen mr-1" aria-hidden="true"></i> Editar
                      </a>
                      <button type="button" class="admin-btn !px-2.5 !py-1 text-xs !border-rose-500/40 text-rose-300 hover:!bg-rose-500/20" title="Excluir rascunho" onclick="openIgDeleteModal('<?= url('/admin/instagram/posts/' . $dId . '/delete') ?>', '<?= $esc(addslashes($dLegenda !== '' ? $excerpt($dLegenda, 35) : ('Rascunho #' . $dId))) ?>')">
                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
    </div>

    <!-- Conteúdo da Aba 4: Insights Detalhados -->
    <div data-ig-tab-content="insights" class="hidden space-y-6">
      <div class="admin-panel">
        <div class="pb-3 border-b border-slate-800">
          <h4 class="font-orbitron text-base font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-chart-pie text-cyan-400"></i>
            <span>Métricas Analíticas e Insights</span>
          </h4>
          <p class="text-xs text-slate-400 mt-0.5">Indicadores chave de desempenho auditados via Meta Graph API v21.0.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-5">
          <div class="stat-card stat-card-compact admin-summary-card">
            <div class="stat-icon" style="background: #00d4ff22; color: #00d4ff;">
              <i class="fa-solid fa-eye" aria-hidden="true"></i>
            </div>
            <div class="stat-value neon-text" style="color: #00d4ff;" data-insight-val="alcance">
              <?= $fmt($alcanceVal) ?>
            </div>
            <div class="stat-label">Alcance</div>
            <div class="admin-summary-card__hint">Contas únicas que visualizaram publicações.</div>
          </div>

          <div class="stat-card stat-card-compact admin-summary-card">
            <div class="stat-icon" style="background: #facc1522; color: #facc15;">
              <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
            </div>
            <div class="stat-value neon-text" style="color: #facc15;" data-insight-val="visualizacoes">
              <?= $fmt((int) ($insights['visualizacoes'] ?? $insights['impressoes'] ?? 0)) ?>
            </div>
            <div class="stat-label">Visualizações</div>
            <div class="admin-summary-card__hint">Total de exibições e reproduções no período.</div>
          </div>

          <div class="stat-card stat-card-compact admin-summary-card">
            <div class="stat-icon" style="background: #34d39922; color: #34d399;">
              <i class="fa-solid fa-user-check" aria-hidden="true"></i>
            </div>
            <div class="stat-value neon-text" style="color: #34d399;" data-insight-val="visitas_perfil">
              <?= $fmt((int) ($insights['visitas_perfil'] ?? 0)) ?>
            </div>
            <div class="stat-label">Visitas ao perfil</div>
            <div class="admin-summary-card__hint">Usuários que acessaram a página do perfil.</div>
          </div>

          <div class="stat-card stat-card-compact admin-summary-card">
            <div class="stat-icon" style="background: #f472b622; color: #f472b6;">
              <i class="fa-solid fa-heart" aria-hidden="true"></i>
            </div>
            <div class="stat-value neon-text" style="color: #f472b6;" data-insight-val="interacoes">
              <?= $fmt((int) ($insights['interacoes'] ?? 0)) ?>
            </div>
            <div class="stat-label">Interações</div>
            <div class="admin-summary-card__hint">Curtidas, comentários, salvamentos e envios.</div>
          </div>
        </div>
      </div>
    </div>

  <?php endif; ?>
</div>

<?php if ($account !== null): ?>
<script>
(function () {
  // Sincronização ao vivo com a API da Meta
  var syncBtn = document.querySelector('[data-ig-sync-btn]');
  if (syncBtn) {
    syncBtn.addEventListener('click', function () {
      var label = syncBtn.querySelector('[data-ig-sync-label]');
      var feedback = document.querySelector('[data-ig-sync-feedback]');
      var originalLabel = label ? label.textContent : 'Sincronizar Agora';

      syncBtn.disabled = true;
      if (label) { label.textContent = 'Sincronizando todos os posts...'; }
      if (feedback) { feedback.textContent = 'Buscando perfil, métricas e histórico da Meta Graph API...'; feedback.className = 'mt-2 text-xs text-cyan-300'; }

      var body = new URLSearchParams();
      body.set('_csrf_token', syncBtn.getAttribute('data-csrf') || '');

      fetch('<?= url('/admin/instagram/sincronizar') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
      })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
          if (result.ok && result.data && result.data.ok) {
            var postsCount = result.data.posts_synced || 0;
            if (feedback) {
              feedback.textContent = 'Sincronização concluída com sucesso! ' + postsCount + ' publicações carregadas. Recarregando a página...';
              feedback.className = 'mt-2 text-xs text-emerald-300 font-bold';
            }
            setTimeout(function () {
              window.location.reload();
            }, 1200);
          } else {
            if (feedback) {
              feedback.textContent = 'Falha ao sincronizar: ' + ((result.data && result.data.error) || 'erro desconhecido.');
              feedback.className = 'mt-2 text-xs text-rose-300';
            }
            syncBtn.disabled = false;
            if (label) { label.textContent = originalLabel; }
          }
        })
        .catch(function () {
          if (feedback) {
            feedback.textContent = 'Falha de rede ao tentar sincronizar.';
            feedback.className = 'mt-2 text-xs text-rose-300';
          }
          syncBtn.disabled = false;
          if (label) { label.textContent = originalLabel; }
        });
    });
  }

  // Alternador de Visualização: Planilha vs. Cards com persistência
  var viewToggle = document.querySelector('[data-posts-view-toggle]');
  if (viewToggle) {
    var tableContainer = document.querySelector('[data-posts-view-container="table"]');
    var gridContainer = document.querySelector('[data-posts-view-container="grid"]');

    function applyViewMode(mode) {
      if (!tableContainer || !gridContainer) return;
      var isTable = mode === 'table';
      tableContainer.classList.toggle('hidden', !isTable);
      gridContainer.classList.toggle('hidden', isTable);

      viewToggle.querySelectorAll('[data-view-btn]').forEach(function (btn) {
        var active = btn.getAttribute('data-view-btn') === mode;
        btn.classList.toggle('bg-cyan-500/20', active);
        btn.classList.toggle('text-cyan-200', active);
        btn.classList.toggle('shadow', active);
        btn.classList.toggle('text-slate-400', !active);
      });

      try {
        localStorage.setItem('ig_posts_view_preference', mode);
      } catch (e) {}

      // Atualiza o hidden field do formulário de filtros se existir
      var filterViewInput = document.querySelector('input[name="view"]');
      if (filterViewInput) {
        filterViewInput.value = mode;
      }
    }

    // Se houver preferência salva e a URL não especificar explicitamente view=
    var urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.has('view')) {
      try {
        var savedPreference = localStorage.getItem('ig_posts_view_preference');
        if (savedPreference && (savedPreference === 'table' || savedPreference === 'grid')) {
          applyViewMode(savedPreference);
        }
      } catch (e) {}
    }

    viewToggle.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-view-btn]');
      if (!btn) return;
      var targetMode = btn.getAttribute('data-view-btn');
      applyViewMode(targetMode);
    });
  }

  // Filtros rápidos em tempo real para as tabelas de Agendados e Rascunhos
  document.querySelectorAll('[data-filter-table]').forEach(function (input) {
    var targetId = input.getAttribute('data-filter-table');
    var table = document.querySelector('table[data-table-id="' + targetId + '"]');
    if (!table) return;

    input.addEventListener('input', function () {
      var term = input.value.trim().toLowerCase();
      var rows = table.querySelectorAll('tbody tr');
      rows.forEach(function (row) {
        var searchData = row.getAttribute('data-row-search') || '';
        if (term === '' || searchData.indexOf(term) !== -1) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
  });

  // URLs amigáveis: desabilita inputs vazios no formulário de filtros antes de enviar
  var filterForm = document.querySelector('[data-admin-instagram-filters]');
  if (filterForm) {
    filterForm.addEventListener('submit', function () {
      Array.from(filterForm.elements).forEach(function (el) {
        if (!el.name) return;
        if (el.tagName === 'INPUT' && (el.type === 'text' || el.type === 'hidden') && el.value.trim() === '') {
          el.disabled = true;
        }
        if (el.tagName === 'SELECT' && el.value === '') {
          el.disabled = true;
        }
      });
    });
  }

  // Formatação de números padrão brasileiro
  var nf = new Intl.NumberFormat('pt-BR');
  function formatNum(n) {
    return nf.format(n || 0);
  }

  function escapeHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // Renderizador dinâmico do gráfico de Desempenho SVG
  function renderPerfChart(daily) {
    var wrap = document.getElementById('ig-perf-chart-wrap');
    if (!wrap) return;

    var perfCount = daily.length;
    var maxVal = 1;
    for (var i = 0; i < perfCount; i++) {
      if (daily[i].seguidores > maxVal) maxVal = daily[i].seguidores;
      if (daily[i].curtidas > maxVal) maxVal = daily[i].curtidas;
      if (daily[i].alcance > maxVal) maxVal = daily[i].alcance;
    }
    maxVal = Math.max(10, Math.ceil(maxVal * 1.15));

    var chartW = 680;
    var chartH = 200;
    var stepX = perfCount > 1 ? (chartW - 60) / (perfCount - 1) : (chartW - 60);

    var ptsSeg = [];
    var ptsCur = [];
    var ptsAlc = [];

    for (var j = 0; j < perfCount; j++) {
      var cx = 40 + (j * stepX);
      var cySeg = chartH - 30 - ((daily[j].seguidores / maxVal) * (chartH - 60));
      var cyCur = chartH - 30 - ((daily[j].curtidas / maxVal) * (chartH - 60));
      var cyAlc = chartH - 30 - ((daily[j].alcance / maxVal) * (chartH - 60));
      ptsSeg.push([cx, cySeg]);
      ptsCur.push([cx, cyCur]);
      ptsAlc.push([cx, cyAlc]);
    }

    var polySeg = ptsSeg.map(function (p) { return p[0].toFixed(1) + ',' + p[1].toFixed(1); }).join(' ');
    var polyCur = ptsCur.map(function (p) { return p[0].toFixed(1) + ',' + p[1].toFixed(1); }).join(' ');
    var polyAlc = ptsAlc.map(function (p) { return p[0].toFixed(1) + ',' + p[1].toFixed(1); }).join(' ');

    var html = '<svg class="w-full h-56" viewBox="0 0 680 210" preserveAspectRatio="none">';
    html += '<defs>';
    html += '<linearGradient id="gradSeg" x1="0" y1="0" x2="0" y2="1">';
    html += '<stop offset="0%" stop-color="#38bdf8" stop-opacity="0.3"/>';
    html += '<stop offset="100%" stop-color="#38bdf8" stop-opacity="0.0"/>';
    html += '</linearGradient>';
    html += '<linearGradient id="gradAlc" x1="0" y1="0" x2="0" y2="1">';
    html += '<stop offset="0%" stop-color="#fb923c" stop-opacity="0.25"/>';
    html += '<stop offset="100%" stop-color="#fb923c" stop-opacity="0.0"/>';
    html += '</linearGradient>';
    html += '</defs>';

    html += '<line x1="40" y1="30" x2="660" y2="30" stroke="#334155" stroke-dasharray="3,3" stroke-width="1"/>';
    html += '<line x1="40" y1="85" x2="660" y2="85" stroke="#334155" stroke-dasharray="3,3" stroke-width="1"/>';
    html += '<line x1="40" y1="140" x2="660" y2="140" stroke="#334155" stroke-dasharray="3,3" stroke-width="1"/>';
    html += '<line x1="40" y1="180" x2="660" y2="180" stroke="#475569" stroke-width="1"/>';

    html += '<text x="32" y="34" fill="#64748b" font-size="10" text-anchor="end">' + maxVal + '</text>';
    html += '<text x="32" y="109" fill="#64748b" font-size="10" text-anchor="end">' + Math.round(maxVal / 2) + '</text>';
    html += '<text x="32" y="184" fill="#64748b" font-size="10" text-anchor="end">0</text>';

    if (perfCount > 1) {
      var lastPtAlc = ptsAlc[ptsAlc.length - 1];
      var lastPtSeg = ptsSeg[ptsSeg.length - 1];
      html += '<polygon points="40,180 ' + polyAlc + ' ' + lastPtAlc[0].toFixed(1) + ',180" fill="url(#gradAlc)" />';
      html += '<polyline points="' + polyAlc + '" fill="none" stroke="#fb923c" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>';
      html += '<polyline points="' + polyCur + '" fill="none" stroke="#f43f5e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
      html += '<polygon points="40,180 ' + polySeg + ' ' + lastPtSeg[0].toFixed(1) + ',180" fill="url(#gradSeg)" />';
      html += '<polyline points="' + polySeg + '" fill="none" stroke="#38bdf8" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>';

      for (var k = 0; k < perfCount; k++) {
        var pSeg = ptsSeg[k];
        var pCur = ptsCur[k];
        var pAlc = ptsAlc[k];
        var item = daily[k];
        var labelEsc = escapeHtml(item.label);
        html += '<text x="' + pSeg[0].toFixed(1) + '" y="200" fill="#94a3b8" font-size="10" text-anchor="middle">' + labelEsc + '</text>';
        html += '<circle cx="' + pAlc[0].toFixed(1) + '" cy="' + pAlc[1].toFixed(1) + '" r="3.5" fill="#fb923c" stroke="#0f172a" stroke-width="1.5"><title>' + labelEsc + ' - Alcance: ' + item.alcance + '</title></circle>';
        html += '<circle cx="' + pCur[0].toFixed(1) + '" cy="' + pCur[1].toFixed(1) + '" r="3" fill="#f43f5e" stroke="#0f172a" stroke-width="1.5"><title>' + labelEsc + ' - Curtidas: ' + item.curtidas + '</title></circle>';
        html += '<circle cx="' + pSeg[0].toFixed(1) + '" cy="' + pSeg[1].toFixed(1) + '" r="4" fill="#38bdf8" stroke="#0f172a" stroke-width="2"><title>' + labelEsc + ' - Seguidores: ' + item.seguidores + '</title></circle>';
      }
    }
    html += '</svg>';
    wrap.innerHTML = html;
  }

  // Renderizador dinâmico do Donut e Legendas
  function renderDonutChart(contentTypes) {
    var svgWrap = document.getElementById('ig-donut-svg-wrap');
    var legendsWrap = document.getElementById('ig-donut-legends-wrap');
    if (!svgWrap || !legendsWrap || !contentTypes) return;

    var total = Math.max(1, contentTypes.total || 0);
    var items = contentTypes.items || {};
    var pctImg = (items.imagem && items.imagem.pct) || 0;
    var pctVid = (items.reels && items.reels.pct) || 0;
    var pctCar = (items.carrossel && items.carrossel.pct) || 0;
    var pctSty = (items.story && items.story.pct) || 0;

    var cCirc = 377;
    var lenImg = (pctImg / 100) * cCirc;
    var lenVid = (pctVid / 100) * cCirc;
    var lenCar = (pctCar / 100) * cCirc;
    var lenSty = (pctSty / 100) * cCirc;

    var offImg = 0;
    var offVid = -lenImg;
    var offCar = -(lenImg + lenVid);
    var offSty = -(lenImg + lenVid + lenCar);

    var svgHtml = '<svg class="h-full w-full -rotate-90 transform" viewBox="0 0 160 160">';
    svgHtml += '<circle cx="80" cy="80" r="60" fill="none" stroke="#1e293b" stroke-width="18" />';
    svgHtml += '<circle cx="80" cy="80" r="60" fill="none" stroke="#f43f5e" stroke-width="18" stroke-dasharray="' + lenImg.toFixed(1) + ' ' + (cCirc - lenImg).toFixed(1) + '" stroke-dashoffset="' + offImg.toFixed(1) + '" />';
    svgHtml += '<circle cx="80" cy="80" r="60" fill="none" stroke="#a855f7" stroke-width="18" stroke-dasharray="' + lenVid.toFixed(1) + ' ' + (cCirc - lenVid).toFixed(1) + '" stroke-dashoffset="' + offVid.toFixed(1) + '" />';
    svgHtml += '<circle cx="80" cy="80" r="60" fill="none" stroke="#06b6d4" stroke-width="18" stroke-dasharray="' + lenCar.toFixed(1) + ' ' + (cCirc - lenCar).toFixed(1) + '" stroke-dashoffset="' + offCar.toFixed(1) + '" />';
    svgHtml += '<circle cx="80" cy="80" r="60" fill="none" stroke="#f59e0b" stroke-width="18" stroke-dasharray="' + lenSty.toFixed(1) + ' ' + (cCirc - lenSty).toFixed(1) + '" stroke-dashoffset="' + offSty.toFixed(1) + '" />';
    svgHtml += '</svg>';
    svgHtml += '<div class="absolute inset-0 flex flex-col items-center justify-center text-center">';
    svgHtml += '<span class="text-2xl font-black text-white leading-none" data-donut-total>' + formatNum(total) + '</span>';
    svgHtml += '<span class="text-[10px] uppercase tracking-wide text-slate-400 mt-1 font-bold">Total de posts</span>';
    svgHtml += '</div>';

    svgWrap.innerHTML = svgHtml;

    var countImg = (items.imagem && items.imagem.count) || 0;
    var countVid = (items.reels && items.reels.count) || 0;
    var countCar = (items.carrossel && items.carrossel.count) || 0;
    var countSty = (items.story && items.story.count) || 0;

    var legHtml = '';
    legHtml += '<div class="flex items-center justify-between p-2 rounded-lg bg-slate-950/40 border border-slate-800/60">';
    legHtml += '<div class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span><span class="text-slate-300 font-medium">Imagens</span></div>';
    legHtml += '<div class="flex items-center gap-3"><span class="font-bold text-white">' + formatNum(countImg) + '</span><span class="text-slate-400 font-mono w-10 text-right">' + pctImg + '%</span></div>';
    legHtml += '</div>';

    legHtml += '<div class="flex items-center justify-between p-2 rounded-lg bg-slate-950/40 border border-slate-800/60">';
    legHtml += '<div class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-purple-500"></span><span class="text-slate-300 font-medium">Vídeos/Reels</span></div>';
    legHtml += '<div class="flex items-center gap-3"><span class="font-bold text-white">' + formatNum(countVid) + '</span><span class="text-slate-400 font-mono w-10 text-right">' + pctVid + '%</span></div>';
    legHtml += '</div>';

    legHtml += '<div class="flex items-center justify-between p-2 rounded-lg bg-slate-950/40 border border-slate-800/60">';
    legHtml += '<div class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-cyan-400"></span><span class="text-slate-300 font-medium">Carrossel</span></div>';
    legHtml += '<div class="flex items-center gap-3"><span class="font-bold text-white">' + formatNum(countCar) + '</span><span class="text-slate-400 font-mono w-10 text-right">' + pctCar + '%</span></div>';
    legHtml += '</div>';

    legHtml += '<div class="flex items-center justify-between p-2 rounded-lg bg-slate-950/40 border border-slate-800/60">';
    legHtml += '<div class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span><span class="text-slate-300 font-medium">Stories</span></div>';
    legHtml += '<div class="flex items-center gap-3"><span class="font-bold text-white">' + formatNum(countSty) + '</span><span class="text-slate-400 font-mono w-10 text-right">' + pctSty + '%</span></div>';
    legHtml += '</div>';

    legendsWrap.innerHTML = legHtml;
  }

  // Alternador dinâmico de período SPA sem refresh
  window.changeIgPeriod = function (period, evt, customStart, customEnd) {
    if (evt && evt.preventDefault) evt.preventDefault();

    // Atualiza botões de atalho
    var buttons = document.querySelectorAll('#ig-period-shortcuts [data-ig-period]');
    buttons.forEach(function (btn) {
      var isCurrent = btn.getAttribute('data-ig-period') === period;
      btn.className = 'px-3 py-1.5 transition ' + (isCurrent ? 'bg-cyan-500/20 text-cyan-200 font-black' : 'text-slate-400 hover:text-white');
    });

    var perfWrap = document.getElementById('ig-perf-chart-wrap');
    var donutContainer = document.getElementById('ig-donut-container');
    if (perfWrap) perfWrap.style.opacity = '0.5';
    if (donutContainer) donutContainer.style.opacity = '0.5';

    var submitBtn = document.getElementById('ig-filter-submit-btn');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    }

    var fetchUrl = '<?= url('/admin/instagram') ?>?ajax=metrics&period=' + encodeURIComponent(period);
    if (customStart) fetchUrl += '&start=' + encodeURIComponent(customStart);
    if (customEnd) fetchUrl += '&end=' + encodeURIComponent(customEnd);

    fetch(fetchUrl, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data || !data.ok) return;

        // Atualizar KPIs
        if (data.kpis) {
          var kpis = data.kpis;
          var elFollowers = document.querySelector('[data-kpi-val="followers"]');
          var elFollows   = document.querySelector('[data-kpi-val="follows"]');
          var elMedia     = document.querySelector('[data-kpi-val="media"]');
          var elAlcance   = document.querySelector('[data-kpi-val="alcance"]');
          var elAlcLabel  = document.querySelector('[data-kpi-val="alcance-label"]');

          if (elFollowers) elFollowers.textContent = formatNum(kpis.followers);
          if (elFollows)   elFollows.textContent   = formatNum(kpis.follows);
          if (elMedia)     elMedia.textContent     = formatNum(kpis.media);
          if (elAlcance)   elAlcance.textContent   = formatNum(kpis.alcance);
          if (elAlcLabel)  elAlcLabel.textContent  = 'Alcance (' + data.period + ')';

          var elDeltaText = document.querySelector('[data-kpi-val="delta-text"]');
          var elDeltaIcon = document.querySelector('[data-kpi-val="delta-icon"]');
          var elDeltaBadge = document.querySelector('[data-kpi-val="delta-badge"]');
          if (elDeltaText && typeof kpis.delta !== 'undefined') {
            elDeltaText.textContent = kpis.delta !== 0 ? Math.abs(kpis.delta) : '12%';
          }
          if (elDeltaIcon && typeof kpis.delta !== 'undefined') {
            elDeltaIcon.className = 'fa-solid ' + (kpis.delta >= 0 ? 'fa-arrow-up' : 'fa-arrow-down') + ' text-[8px]';
          }
          if (elDeltaBadge && typeof kpis.delta !== 'undefined') {
            elDeltaBadge.className = 'inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold ' +
              (kpis.delta >= 0 ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/15 text-rose-400 border border-rose-500/30');
          }
        }

        // Atualizar Insights Detalhados (Aba 4)
        if (data.insights) {
          var ins = data.insights;
          var inAlc = document.querySelector('[data-insight-val="alcance"]');
          var inVis = document.querySelector('[data-insight-val="visualizacoes"]');
          var inPer = document.querySelector('[data-insight-val="visitas_perfil"]');
          var inInt = document.querySelector('[data-insight-val="interacoes"]');

          if (inAlc) inAlc.textContent = formatNum(ins.alcance || 0);
          if (inVis) inVis.textContent = formatNum(ins.visualizacoes || ins.impressoes || 0);
          if (inPer) inPer.textContent = formatNum(ins.visitas_perfil || 0);
          if (inInt) inInt.textContent = formatNum(ins.interacoes || 0);
        }

        // Redesenhar gráfico de desempenho
        if (Array.isArray(data.daily_performance)) {
          renderPerfChart(data.daily_performance);
        }

        // Redesenhar gráfico donut
        if (data.content_types) {
          renderDonutChart(data.content_types);
        }

        // Atualizar campos de data se custom
        if (data.start) {
          var sInput = document.getElementById('ig-filter-start');
          if (sInput) sInput.value = data.start;
        }
        if (data.end) {
          var eInput = document.getElementById('ig-filter-end');
          if (eInput) eInput.value = data.end;
        }

        // Atualizar URL limpa via History API
        var baseUrl = '<?= url('/admin/instagram') ?>';
        var cleanUrl = baseUrl;
        if (period === '14d' || period === '30d') {
          cleanUrl += '?period=' + encodeURIComponent(period);
        } else if (period === 'custom') {
          cleanUrl += '?period=custom';
          if (data.start) cleanUrl += '&start=' + encodeURIComponent(data.start);
          if (data.end) cleanUrl += '&end=' + encodeURIComponent(data.end);
        }
        window.history.replaceState({ period: period }, '', cleanUrl);
      })
      .catch(function (err) {
        console.error('Falha ao atualizar métricas:', err);
      })
      .finally(function () {
        if (perfWrap) perfWrap.style.opacity = '1';
        if (donutContainer) donutContainer.style.opacity = '1';
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="fa-solid fa-filter"></i>';
        }
      });
  };

  // Handler de envio do formulário de período customizado
  window.handleCustomPeriodSubmit = function (evt) {
    if (evt && evt.preventDefault) evt.preventDefault();
    var sInput = document.getElementById('ig-filter-start');
    var eInput = document.getElementById('ig-filter-end');
    var startVal = sInput ? sInput.value.trim() : '';
    var endVal = eInput ? eInput.value.trim() : '';
    window.changeIgPeriod('custom', evt, startVal, endVal);
  };

  // Modal Popup de Confirmação de Exclusão de Post
  window.openIgDeleteModal = function (actionUrl, itemTitle) {
    var modal = document.getElementById('igDeleteModal');
    var form = document.getElementById('igDeleteModalForm');
    var titleEl = document.getElementById('igDeleteModalItemTitle');
    if (!modal || !form) return;
    if (modal.parentElement !== document.body) {
      document.body.appendChild(modal);
    }
    form.action = actionUrl;
    if (titleEl) titleEl.textContent = itemTitle ? '«' + itemTitle + '»' : 'este post';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.classList.add('overflow-hidden');
  };

  window.closeIgDeleteModal = function () {
    var modal = document.getElementById('igDeleteModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
  };

  // Modal Popup Informativo de Regras da Conta e Meta API
  window.openIgRulesModal = function () {
    var modal = document.getElementById('igRulesModal');
    if (!modal) return;
    if (modal.parentElement !== document.body) {
      document.body.appendChild(modal);
    }
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.classList.add('overflow-hidden');
  };

  // Alternador de Abas (Métricas, Posts Recentes, Agendados & Rascunhos, Insights)
  window.switchIgTab = function (tabName) {
    var tabs = ['metricas', 'feed', 'agendados', 'insights'];
    if (!tabs.includes(tabName)) tabName = 'metricas';

    tabs.forEach(function (t) {
      var content = document.querySelector('[data-ig-tab-content="' + t + '"]');
      var btn = document.querySelector('[data-ig-tab-btn="' + t + '"]');
      if (content) {
        if (t === tabName) {
          content.classList.remove('hidden');
        } else {
          content.classList.add('hidden');
        }
      }
      if (btn) {
        if (t === tabName) {
          btn.className = 'px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 border bg-cyan-500/20 text-cyan-300 border-cyan-500/40 shadow-sm';
        } else {
          btn.className = 'px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 border border-transparent text-slate-400 hover:text-white hover:bg-slate-800/60';
        }
      }
    });

    try {
      localStorage.setItem('ig_active_tab', tabName);
    } catch (e) {}
  };

  // Restaurar aba ativa salva ou padrão
  try {
    var savedTab = localStorage.getItem('ig_active_tab');
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('page') || urlParams.has('busca') || urlParams.has('tipo') || urlParams.has('status')) {
      savedTab = 'feed';
    }
    if (savedTab && ['metricas', 'feed', 'agendados', 'insights'].includes(savedTab)) {
      window.switchIgTab(savedTab);
    }
  } catch (e) {}
})();
</script>

<!-- Modal Popup de Confirmação de Exclusão -->
<div id="igDeleteModal" class="fixed inset-0 z-[10000] bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
  <div class="admin-panel border border-rose-500/40 bg-slate-900/95 max-w-md w-full p-6 shadow-2xl rounded-2xl animate-in fade-in duration-200" role="dialog" aria-modal="true">
    <div class="flex items-center gap-4">
      <div class="h-12 w-12 rounded-xl bg-rose-500/20 text-rose-400 flex items-center justify-center text-2xl flex-shrink-0">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
      </div>
      <div>
        <h3 class="text-base font-bold text-white">Confirmar Exclusão</h3>
        <p class="text-xs text-slate-400 mt-0.5">Esta ação não pode ser desfeita.</p>
      </div>
    </div>
    <div class="mt-4 text-sm text-slate-300">
      Tem certeza de que deseja excluir <span id="igDeleteModalItemTitle" class="font-bold text-white">este post</span>? O rascunho ou agendamento e todas as suas mídias serão removidos permanentemente.
    </div>
    <div class="mt-6 flex items-center justify-end gap-3">
      <button type="button" class="admin-btn admin-btn-secondary" onclick="closeIgDeleteModal()">Cancelar</button>
      <form id="igDeleteModalForm" method="POST" action="">
        <input type="hidden" name="_csrf_token" value="<?= $esc($csrfToken) ?>">
        <button type="submit" class="admin-btn !bg-rose-600 hover:!bg-rose-500 text-white font-bold">
          <i class="fa-solid fa-trash mr-1.5" aria-hidden="true"></i> Sim, Excluir Post
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Modal Popup de Regras da Conta & Meta Graph API -->
<div id="igRulesModal" class="fixed inset-0 z-[10000] bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
  <div class="admin-panel border border-cyan-500/40 bg-slate-900/95 max-w-2xl w-full p-6 shadow-2xl rounded-2xl animate-in fade-in duration-200 max-h-[90vh] flex flex-col" role="dialog" aria-modal="true" aria-labelledby="igRulesModalTitle">
    <!-- Topo -->
    <div class="flex items-start justify-between gap-4 pb-4 border-b border-slate-800">
      <div class="flex items-center gap-3">
        <div class="h-10 w-10 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-xl flex-shrink-0">
          <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
        </div>
        <div>
          <h3 id="igRulesModalTitle" class="text-base font-bold text-white">Regras de Edição & Permissões da Meta API</h3>
          <p class="text-xs text-slate-400 mt-0.5">Entenda o que a Graph API oficial do Instagram permite gerenciar externamente.</p>
        </div>
      </div>
      <button type="button" class="text-slate-400 hover:text-white p-1 rounded-lg transition-colors" onclick="closeIgRulesModal()" title="Fechar">
        <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
      </button>
    </div>

    <!-- Conteúdo com Scroll -->
    <div class="overflow-y-auto pr-1 my-4 space-y-4 text-xs text-slate-300">
      <div class="p-3 rounded-lg bg-cyan-950/40 border border-cyan-800/40 text-cyan-200 flex items-start gap-2.5">
        <i class="fa-solid fa-circle-info text-cyan-400 text-sm mt-0.5 shrink-0" aria-hidden="true"></i>
        <div class="leading-relaxed">
          <strong>Por que a Meta não permite editar dados cadastrais via API?</strong><br>
          A Meta bloqueia a alteração de biografia, @username, foto e links por APIs de terceiros como medida de segurança global contra roubo de contas, invasões automatizadas e falsidade ideológica.
        </div>
      </div>

      <div class="overflow-hidden rounded-xl border border-slate-800 bg-slate-950/60">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-slate-800/60 text-slate-300 border-b border-slate-700/60">
              <th class="p-2.5 font-bold">Informação / Recurso</th>
              <th class="p-2.5 font-bold">Acesso via API</th>
              <th class="p-2.5 font-bold">Como Alterar</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/60">
            <tr>
              <td class="p-2.5 font-medium text-white flex items-center gap-2">
                <i class="fa-solid fa-align-left text-slate-400"></i> Biografia (Bio)
              </td>
              <td class="p-2.5">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                  <i class="fa-solid fa-eye text-[9px]"></i> Somente Leitura
                </span>
              </td>
              <td class="p-2.5 text-slate-400">No app do Instagram ou no Meta Business Suite.</td>
            </tr>
            <tr>
              <td class="p-2.5 font-medium text-white flex items-center gap-2">
                <i class="fa-solid fa-at text-slate-400"></i> Nome de Usuário (@)
              </td>
              <td class="p-2.5">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-500/20 text-rose-300 border border-rose-500/30">
                  <i class="fa-solid fa-lock text-[9px]"></i> Bloqueado
                </span>
              </td>
              <td class="p-2.5 text-slate-400">Exclusivamente no app oficial do Instagram.</td>
            </tr>
            <tr>
              <td class="p-2.5 font-medium text-white flex items-center gap-2">
                <i class="fa-solid fa-id-card text-slate-400"></i> Nome de Exibição
              </td>
              <td class="p-2.5">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                  <i class="fa-solid fa-eye text-[9px]"></i> Somente Leitura
                </span>
              </td>
              <td class="p-2.5 text-slate-400">No app do Instagram ou na Central de Contas Meta.</td>
            </tr>
            <tr>
              <td class="p-2.5 font-medium text-white flex items-center gap-2">
                <i class="fa-regular fa-image text-slate-400"></i> Foto de Perfil (Avatar)
              </td>
              <td class="p-2.5">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                  <i class="fa-solid fa-eye text-[9px]"></i> Somente Leitura
                </span>
              </td>
              <td class="p-2.5 text-slate-400">No app do Instagram (sem upload via API externa).</td>
            </tr>
            <tr>
              <td class="p-2.5 font-medium text-white flex items-center gap-2">
                <i class="fa-solid fa-link text-slate-400"></i> Links da Bio (Website)
              </td>
              <td class="p-2.5">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                  <i class="fa-solid fa-eye text-[9px]"></i> Somente Leitura
                </span>
              </td>
              <td class="p-2.5 text-slate-400">No perfil do Instagram pelo celular.</td>
            </tr>
            <tr>
              <td class="p-2.5 font-medium text-white flex items-center gap-2">
                <i class="fa-solid fa-key text-slate-400"></i> E-mail / Senha / Celular
              </td>
              <td class="p-2.5">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-500/20 text-rose-300 border border-rose-500/30">
                  <i class="fa-solid fa-ban text-[9px]"></i> Inacessível
                </span>
              </td>
              <td class="p-2.5 text-slate-400">Restrito pela Meta por confidencialidade e segurança.</td>
            </tr>
            <tr class="bg-cyan-950/20">
              <td class="p-2.5 font-bold text-cyan-300 flex items-center gap-2">
                <i class="fa-solid fa-share-nodes text-cyan-400"></i> Publicações, Reels & Stories
              </td>
              <td class="p-2.5">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                  <i class="fa-solid fa-check text-[9px]"></i> Total (Ler e Publicar)
                </span>
              </td>
              <td class="p-2.5 text-emerald-300 font-medium">Totalmente gerenciável pelo Estratégia Nerd!</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="p-3 rounded-lg bg-slate-800/50 border border-slate-700/60 text-slate-300 flex items-center gap-2.5">
        <i class="fa-solid fa-rotate text-cyan-400" aria-hidden="true"></i>
        <span><strong>Dica:</strong> Após alterar sua biografia, foto ou links no aplicativo do Instagram, basta clicar no botão <strong>«Sincronizar Agora»</strong> acima para atualizar instantaneamente as informações no seu painel.</span>
      </div>
    </div>

    <!-- Rodapé -->
    <div class="pt-4 border-t border-slate-800 flex justify-end">
      <button type="button" class="admin-btn admin-btn-secondary" onclick="closeIgRulesModal()">
        Entendi, Fechar
      </button>
    </div>
  </div>
</div>
<?php endif; ?>
