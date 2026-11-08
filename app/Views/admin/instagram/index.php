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

$filters     = $filters ?? ['busca' => '', 'tipo' => '', 'status' => '', 'origem' => '', 'periodo_post' => ''];
$busca       = (string) ($filters['busca'] ?? '');
$tipo        = (string) ($filters['tipo'] ?? '');
$status      = (string) ($filters['status'] ?? '');
$origem      = (string) ($filters['origem'] ?? '');
$periodoPost = (string) ($filters['periodo_post'] ?? '');

$sort        = (string) ($sort ?? 'publicado_em');
$dir         = (string) ($dir ?? 'desc');
$period      = (string) ($period ?? '7d');
$start       = (string) ($start ?? date('Y-m-d', strtotime('-7 days')));
$end         = (string) ($end ?? date('Y-m-d'));
$minDate     = (string) ($min_date ?? '2024-11-20');
$maxDate     = (string) ($max_date ?? date('Y-m-d'));
$adjustedNotice = !empty($adjusted_notice) ? (string) $adjusted_notice : null;
$alcanceModo = (string) ($alcance_modo ?? 'exato');
$viewMode    = isset($_GET['view']) && in_array((string) $_GET['view'], ['grid', 'table'], true) ? (string) $_GET['view'] : 'table';
$contentTypes = $content_types ?? ['total' => $totalPosts, 'items' => []];
$dailyPerformance = $daily_performance ?? [];
$kpiMeta = $kpi_meta ?? [
    'deltas' => ['followers' => null, 'follows' => null, 'media' => null, 'alcance_pct' => null],
    'series' => ['followers' => [], 'follows' => [], 'media' => [], 'alcance' => []],
];

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
        'cancelado'  => 'status-badge border-slate-500/30 text-slate-400 bg-slate-500/10',
        default      => 'status-badge',
    };
};

$statusLabel = [
    'publicado'  => 'Publicado',
    'agendado'   => 'Agendado',
    'rascunho'   => 'Rascunho',
    'publicando' => 'Publicando',
    'erro'       => 'Erro',
    'cancelado'  => 'Cancelado',
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

$resolveMediaUrl = static function (?string $path, int $postId = 0): string {
    if ($postId > 0) {
        $localAsset = '/assets/instagram-feed/post_' . $postId . '.jpg';
        if (is_file(base_path('public' . $localAsset))) {
            return url($localAsset);
        }
    }
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }
    // Para Reels/vídeos MP4, checa se existe a miniatura JPG gerada correspondente
    if (preg_match('#\.mp4(\?.*)?$#i', $path)) {
        $cleanPath = preg_replace('#\?.*$#', '', $path);
        $cleanDir = dirname(ltrim($cleanPath, '/'));
        $standardRel = ($cleanDir === '.' ? '' : $cleanDir . '/') . 'capa.jpg';
        if (is_file(base_path('public/' . $standardRel))) {
            return url('/' . $standardRel);
        }
        $jpgPath = preg_replace('#\.mp4$#i', '.jpg', $cleanPath);
        $localJpg = base_path('public/' . ltrim($jpgPath, '/'));
        if (is_file($localJpg)) {
            return url('/' . ltrim($jpgPath, '/'));
        }
        return '';
    }
    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:') || str_starts_with($path, 'blob:')) {
        return $path;
    }
    return url('/' . ltrim($path, '/'));
};

$baseUrl = function_exists('url') ? url('/admin/instagram') : '/admin/instagram';
$buildUrl = static function (array $overrides = []) use ($baseUrl, $filters, $sort, $dir, $page, $perPage, $period, $start, $end, $viewMode): string {
    $current = [
        'busca'        => (string) ($filters['busca'] ?? ''),
        'tipo'         => (string) ($filters['tipo'] ?? ''),
        'status'       => (string) ($filters['status'] ?? ''),
        'origem'       => (string) ($filters['origem'] ?? ''),
        'periodo_post' => (string) ($filters['periodo_post'] ?? ''),
        'period'       => $period,
        'start'        => $start,
        'end'          => $end,
        'sort'         => $sort,
        'dir'          => $dir,
        'page'         => $page,
        'per_page'     => $perPage,
        'view'         => $viewMode,
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
    if (!empty($current['origem'])) {
        $clean['origem'] = (string) $current['origem'];
    }
    if (!empty($current['periodo_post'])) {
        $clean['periodo_post'] = (string) $current['periodo_post'];
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

    <!-- Fileira de 4 KPIs com Sparklines (série real de instagram_insights_cache) -->
    <?php
    $followersCount = (int) ($account['followers_count'] ?? 0);
    $followsCount   = (int) ($account['follows_count'] ?? 0);
    $mediaCount     = (int) ($account['media_count'] ?? $totalPosts);
    $alcanceVal     = ($insights !== null && isset($insights['alcance'])) ? (int) $insights['alcance'] : null;
    $alcanceLabel   = ($alcanceModo === 'soma_blocos') ? 'Alcance (soma dos blocos)' : 'Alcance (' . $period . ')';
    $alcanceTitle   = ($alcanceModo === 'soma_blocos')
        ? 'Soma dos alcances de cada bloco de até 28 dias. A mesma pessoa pode ter sido contabilizada em mais de um bloco.'
        : 'Alcance total oficial no período selecionado (Meta Graph API).';

    // Badge de variação e sparkline são preenchidos pelo JS (renderKpiExtras) a partir de $kpiMeta.
    $kpiCards = [
        ['key' => 'followers', 'label' => 'Seguidores', 'value' => $followersCount, 'icon' => 'fa-users',      'border' => 'border-l-sky-400',    'box' => 'bg-sky-500/10 border border-sky-500/30 text-sky-400', 'title' => 'Total de seguidores da conta'],
        ['key' => 'follows',   'label' => 'Seguindo',   'value' => $followsCount,   'icon' => 'fa-user-plus',  'border' => 'border-l-purple-400', 'box' => 'bg-purple-500/10 border border-purple-500/30 text-purple-400', 'title' => 'Contas seguidas pelo perfil'],
        ['key' => 'media',     'label' => 'Mídias',     'value' => $mediaCount,     'icon' => 'fa-photo-film', 'border' => 'border-l-pink-400',   'box' => 'bg-pink-500/10 border border-pink-500/30 text-pink-400', 'title' => 'Total de mídias publicadas'],
        ['key' => 'alcance',   'label' => $alcanceLabel, 'value' => $alcanceVal,    'icon' => 'fa-eye',        'border' => 'border-l-amber-400',  'box' => 'bg-amber-500/10 border border-amber-500/30 text-amber-400', 'title' => $alcanceTitle],
    ];
    ?>
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <?php foreach ($kpiCards as $card): ?>
        <div class="admin-panel !p-4 flex items-center justify-between border-l-4 <?= $card['border'] ?> bg-slate-900/90 shadow-lg" title="<?= $esc($card['title']) ?>">
          <div class="flex items-center gap-3.5 min-w-0">
            <div class="h-11 w-11 rounded-xl <?= $card['box'] ?> flex items-center justify-center text-lg shrink-0">
              <i class="fa-solid <?= $card['icon'] ?>" aria-hidden="true"></i>
            </div>
            <div class="min-w-0">
              <div class="text-xl font-black text-white" data-kpi-val="<?= $card['key'] ?>" data-kpi-num="<?= $card['value'] !== null ? (int) $card['value'] : '' ?>"><?= $card['value'] !== null ? $fmt((int) $card['value']) : '—' ?></div>
              <div class="text-xs text-slate-400 font-medium truncate"<?= $card['key'] === 'alcance' ? ' data-kpi-val="alcance-label"' : '' ?>><?= $esc($card['label']) ?></div>
            </div>
          </div>
          <div class="flex flex-col items-end gap-1 shrink-0">
            <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-800/80 text-slate-400 border border-slate-700/60" data-kpi-badge="<?= $card['key'] ?>">—</span>
            <div class="w-16 h-6" data-kpi-spark="<?= $card['key'] ?>"></div>
          </div>
        </div>
      <?php endforeach; ?>
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
            <span>Posts</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-slate-800 text-slate-300 font-semibold"><?= $totalPosts ?></span>
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
              min="<?= $esc($minDate) ?>"
              max="<?= $esc($maxDate) ?>"
              class="nerd-input !py-1 !px-2 rounded-lg text-xs font-bold"
              aria-label="Data inicial"
            >
            <span class="text-slate-500 text-xs">-</span>
            <input
              type="date"
              name="end"
              id="ig-filter-end"
              value="<?= $esc($end) ?>"
              min="<?= $esc($minDate) ?>"
              max="<?= $esc($maxDate) ?>"
              class="nerd-input !py-1 !px-2 rounded-lg text-xs font-bold"
              aria-label="Data final"
            >
            <button type="submit" class="admin-btn admin-btn-secondary !px-2.5 !py-1 text-xs" title="Filtrar por data" id="ig-filter-submit-btn">
              <i class="fa-solid fa-filter"></i>
            </button>
          </form>
        </div>
      </div>
      <!-- Aviso de ajuste de período (limites da conta) -->
      <div id="ig-period-notice" class="mt-2 text-xs font-semibold text-amber-300 bg-amber-500/10 border border-amber-500/30 px-3 py-1.5 rounded-lg flex items-center gap-2 <?= !empty($adjustedNotice) ? '' : 'hidden' ?>">
        <i class="fa-solid fa-triangle-exclamation text-amber-400" aria-hidden="true"></i>
        <span id="ig-period-notice-text"><?= $esc($adjustedNotice ?? '') ?></span>
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
                <p class="text-xs text-slate-400 mt-0.5">Evolução de seguidores, curtidas e alcance no período selecionado.<span id="ig-perf-grouping"></span></p>
              </div>
              <!-- Legenda colorida -->
              <div class="flex flex-wrap items-center gap-4 text-xs font-semibold">
                <span class="inline-flex items-center gap-1.5 text-pink-400">
                  <span class="h-2.5 w-2.5 rounded-full bg-pink-400"></span> Curtidas (posts publicados)
                </span>
                <span class="inline-flex items-center gap-1.5 text-sky-400/60" title="Série histórica diária de seguidores será integrada na Fase 2 com snapshots contínuos">
                  <span class="h-2.5 w-2.5 rounded-full bg-sky-400/40"></span> Seguidores <span class="text-[10px] text-slate-500 font-normal">(Fase 2)</span>
                </span>
                <span class="inline-flex items-center gap-1.5 text-amber-400" title="Alcance da conta por ponto com cache (Meta Graph API / instagram_reach_blocks)">
                  <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span> Alcance
                </span>
              </div>
            </div>

            <!-- Gráfico Vetorial SVG Fluido Responsivo -->
            <!-- Gráfico Vetorial SVG: desenhado por renderPerfChart() (agrupamento por dia, semana ou mês) -->
            <div class="mt-5 w-full overflow-hidden" id="ig-perf-chart-wrap" style="transition: opacity .2s ease"></div>
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
              <p class="text-xs text-slate-400 mt-0.5">Distribuição das publicações no período.</p>
            </div>

            <!-- Gráfico Donut SVG Central -->
            <div class="mt-6 flex flex-col sm:flex-row lg:flex-col items-center justify-center gap-6" id="ig-donut-container">
              <?php
              $totDist = (int) ($contentTypes['total'] ?? 0);
              $pctImg  = (float) ($contentTypes['items']['imagem']['pct'] ?? 0.0);
              $pctVid  = (float) ($contentTypes['items']['reels']['pct'] ?? 0.0);
              $pctCar  = (float) ($contentTypes['items']['carrossel']['pct'] ?? 0.0);
              $pctSty  = (float) ($contentTypes['items']['story']['pct'] ?? 0.0);

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
                  <?php if ($totDist > 0): ?>
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
                  <?php endif; ?>
                </svg>
                <!-- Texto Central do Donut -->
                <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                  <span class="text-2xl font-black text-white leading-none" data-donut-total><?= $fmt($totDist) ?></span>
                  <span class="text-[10px] uppercase tracking-wide text-slate-400 mt-1 font-bold">Posts no período</span>
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

    <!-- Conteúdo da Aba 2: Posts (Filtros, Tabela/Cards e Paginação) -->
    <div data-ig-tab-content="feed" data-ig-posts-root class="hidden space-y-6">
      <!-- Painel de Filtros da Lista de Posts (Padrão Central de Posts) -->
      <form method="GET" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-panel admin-filter-panel" data-admin-instagram-filters>
      <div class="admin-filter-head">
        <div>
          <h3 class="font-orbitron text-xl font-black text-white">Filtros</h3>
          <div class="text-xs text-slate-400 mt-1">Refine a listagem mantendo a navegação fluida sem refresh</div>
        </div>
        <div class="text-xs text-slate-400">
          Página atual <span class="text-cyan-300 font-bold"><?= $page ?></span> - <span class="text-slate-200 font-bold"><?= $perPage >= 9999 ? 'Todos' : $perPage ?></span> por página
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
            <option value="" <?= $tipo === '' ? 'selected' : '' ?>>Todos os tipos</option>
            <option value="imagem" <?= $tipo === 'imagem' ? 'selected' : '' ?>>Imagem</option>
            <option value="carrossel" <?= $tipo === 'carrossel' ? 'selected' : '' ?>>Carrossel</option>
            <option value="reels" <?= $tipo === 'reels' ? 'selected' : '' ?>>Reels</option>
            <option value="story" <?= $tipo === 'story' ? 'selected' : '' ?>>Story</option>
          </select>
        </div>

        <div class="admin-filter-field">
          <label class="admin-filter-label" for="posts-status">Status</label>
          <select id="posts-status" name="status" class="nerd-input admin-filter-control">
            <option value="" <?= $status === '' ? 'selected' : '' ?>>Todos os status</option>
            <option value="publicado" <?= $status === 'publicado' ? 'selected' : '' ?>>Publicado</option>
            <option value="agendado" <?= $status === 'agendado' ? 'selected' : '' ?>>Agendado</option>
            <option value="rascunho" <?= $status === 'rascunho' ? 'selected' : '' ?>>Rascunho</option>
            <option value="erro" <?= $status === 'erro' ? 'selected' : '' ?>>Erro/Falhou</option>
            <option value="cancelado" <?= $status === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
          </select>
        </div>

        <div class="admin-filter-field">
          <label class="admin-filter-label" for="posts-origem">Origem</label>
          <select id="posts-origem" name="origem" class="nerd-input admin-filter-control">
            <option value="" <?= $origem === '' ? 'selected' : '' ?>>Todas as origens</option>
            <option value="blog" <?= $origem === 'blog' ? 'selected' : '' ?>>Blog (Sincronizado)</option>
            <option value="local" <?= $origem === 'local' ? 'selected' : '' ?>>Painel Local</option>
            <option value="instagram" <?= $origem === 'instagram' ? 'selected' : '' ?>>Instagram (Feed)</option>
          </select>
        </div>

        <div class="admin-filter-field">
          <label class="admin-filter-label" for="posts-periodo">Período</label>
          <select id="posts-periodo" name="periodo_post" class="nerd-input admin-filter-control">
            <option value="" <?= $periodoPost === '' ? 'selected' : '' ?>>Todo o período</option>
            <option value="hoje" <?= $periodoPost === 'hoje' ? 'selected' : '' ?>>Hoje</option>
            <option value="7d" <?= $periodoPost === '7d' ? 'selected' : '' ?>>Últimos 7 dias</option>
            <option value="30d" <?= $periodoPost === '30d' ? 'selected' : '' ?>>Últimos 30 dias</option>
            <option value="mes" <?= $periodoPost === 'mes' ? 'selected' : '' ?>>Este Mês</option>
          </select>
        </div>

        <div class="admin-filter-field">
          <label class="admin-filter-label" for="posts-per-page">Por Página</label>
          <select id="posts-per-page" name="per_page" class="nerd-input admin-filter-control">
            <option value="8" <?= $perPage === 8 ? 'selected' : '' ?>>8 por página</option>
            <option value="16" <?= $perPage === 16 ? 'selected' : '' ?>>16 por página</option>
            <option value="24" <?= $perPage === 24 ? 'selected' : '' ?>>24 por página</option>
            <option value="32" <?= $perPage === 32 ? 'selected' : '' ?>>32 por página</option>
            <option value="48" <?= $perPage === 48 ? 'selected' : '' ?>>48 por página</option>
            <option value="todos" <?= $perPage >= 9999 ? 'selected' : '' ?>>Todos</option>
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
        <input type="hidden" name="view" value="<?= $esc($viewMode) ?>">
        <button class="admin-btn admin-btn-primary admin-filter-button" type="submit">Filtrar</button>
        <a class="admin-btn admin-btn-secondary admin-filter-button" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-admin-instagram-link>Limpar</a>
      </div>
    </form>

    <!-- Lista de Posts (Tabela / Cards Oficial Estratégia Nerd) -->
    <section class="admin-panel posts-table-panel">
      <div class="posts-table-head">
        <div>
          <h3 class="font-orbitron text-xl font-black text-white">Lista de Posts</h3>
          <div class="text-xs text-slate-400 mt-1"><?= number_format($totalPosts, 0, ',', '.') ?> resultado(s) encontrado(s)</div>
          <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-400" aria-label="Legenda da tabela">
            <span><i class="fa-solid fa-heart text-pink-400" aria-hidden="true"></i> Curtidas</span>
            <span><i class="fa-solid fa-comment text-cyan-400" aria-hidden="true"></i> Comentários</span>
          </div>
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
                    <a data-admin-instagram-link class="posts-table-sort posts-table-sort-left" href="<?= htmlspecialchars($sortLink('legenda'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      Legenda <?= $sortIcon('legenda') ?>
                    </a>
                  </th>
                  <th class="posts-table-th posts-table-th-center" style="width: 110px;">
                    <a data-admin-instagram-link class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      Status <?= $sortIcon('status') ?>
                    </a>
                  </th>
                  <th class="posts-table-th posts-table-th-center" style="width: 140px;">
                    <a data-admin-instagram-link class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('publicado_em'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      Publicação <?= $sortIcon('publicado_em') ?>
                    </a>
                  </th>
                  <th class="posts-table-th posts-table-th-center" style="width: 90px;">
                    <a data-admin-instagram-link class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('curtidas'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="Curtidas" aria-label="Ordenar por curtidas">
                      <i class="fa-solid fa-heart text-pink-400" aria-hidden="true"></i> <?= $sortIcon('curtidas') ?>
                    </a>
                  </th>
                  <th class="posts-table-th posts-table-th-center" style="width: 110px;">
                    <a data-admin-instagram-link class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('comentarios'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="Comentários" aria-label="Ordenar por comentários">
                      <i class="fa-solid fa-comment text-cyan-400" aria-hidden="true"></i> <?= $sortIcon('comentarios') ?>
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
                    $thumbUrl    = $resolveMediaUrl($medias[0] ?? '', $pId);
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
                        <?= $esc(in_array((string) ($item['publish_phase'] ?? ''), ['awaiting_confirmation','published_id_pending'], true) ? 'Aguardando confirmação' : ($statusLabel[$pStatus] ?? $pStatus)) ?>
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
                $pDataPub    = !empty($item['publicado_em']) ? $item['publicado_em'] : ($item['agendado_para'] ?? null);
                $medias      = array_values(array_filter(explode('|', (string) ($item['medias'] ?? ''))));
                $thumbUrl    = $resolveMediaUrl($medias[0] ?? '', $pId);
                $editUrl     = url('/admin/instagram/posts/' . $pId . '/editar');
              ?>
              <div class="rounded-2xl border border-slate-800/80 bg-slate-900/60 overflow-hidden hover:border-cyan-500/40 transition-all flex flex-col group shadow-lg">
                <!-- Cabeçalho do Card: Tipo (esq) e Status (dir) -->
                <div class="px-3 py-2 flex items-center justify-between gap-2 border-b border-slate-800/80 bg-slate-900/90 shrink-0">
                  <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider <?= $tipoBadge($pTipo) ?>">
                    <?= $esc($tipoLabel[$pTipo] ?? $pTipo) ?>
                  </span>
                  <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider <?= htmlspecialchars($statusClasses($pStatus), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <?= $esc(in_array((string) ($item['publish_phase'] ?? ''), ['awaiting_confirmation','published_id_pending'], true) ? 'Aguardando confirmação' : ($statusLabel[$pStatus] ?? $pStatus)) ?>
                  </span>
                </div>

                <!-- Capa Vertical 9:16 com Largura Total -->
                <div class="aspect-[9/16] w-full bg-slate-950 relative overflow-hidden flex items-center justify-center shrink-0">
                  <?php if ($thumbUrl !== ''): ?>
                    <img src="<?= $esc($thumbUrl) ?>" alt="" class="h-full w-full <?= in_array($pTipo, ['reels', 'story'], true) ? 'object-cover' : 'object-contain' ?> transition-transform duration-300 group-hover:scale-105" loading="lazy" onerror="this.classList.add('hidden'); if(this.nextElementSibling) this.nextElementSibling.classList.remove('hidden');">
                    <div class="hidden absolute inset-0 flex items-center justify-center bg-slate-900 text-slate-500">
                      <i class="fa-brands fa-instagram text-4xl text-slate-600" aria-hidden="true"></i>
                    </div>
                  <?php else: ?>
                    <i class="fa-brands fa-instagram text-4xl text-slate-600" aria-hidden="true"></i>
                  <?php endif; ?>
                </div>

                <!-- Conteúdo do Card -->
                <div class="p-3.5 flex-1 flex flex-col justify-between space-y-3">
                  <div class="text-xs text-slate-300 leading-relaxed font-medium line-clamp-3">
                    <?= $esc($excerpt($pLegenda, 90)) ?>
                  </div>

                  <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-400">
                    <div class="flex items-center gap-2.5">
                      <span class="text-pink-400 font-bold" title="Curtidas">
                        <i class="fa-solid fa-heart mr-1" aria-hidden="true"></i> <?= $fmt($pCurtidas) ?>
                      </span>
                      <span class="text-cyan-400 font-bold" title="Comentários">
                        <i class="fa-solid fa-comment mr-1" aria-hidden="true"></i> <?= $fmt($pComents) ?>
                      </span>
                    </div>
                    <span class="text-[11px] text-slate-400 font-mono" title="<?= $pStatus === 'agendado' ? 'Agendado para' : 'Publicado em' ?>">
                      <i class="fa-regular fa-calendar mr-1 text-slate-500" aria-hidden="true"></i><?= $esc($formatDate((string) $pDataPub)) ?>
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
              <?php foreach ([8 => '8', 16 => '16', 24 => '24', 32 => '32', 48 => '48', 'todos' => 'Todos'] as $optVal => $optLabel): ?>
                <?php $activeChip = ($optVal === 'todos' ? $perPage >= 9999 : $perPage === (int) $optVal); ?>
                <a
                  data-admin-instagram-link
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
                data-admin-instagram-link
                class="posts-pagination-link posts-pagination-link-wide<?= $totalPosts === 0 || $page <= 1 ? ' is-disabled' : '' ?>"
                href="<?= htmlspecialchars($buildUrl(['page' => $page - 1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
              >Anterior</a>

              <?php for ($curr = $startRange; $curr <= $endRange; $curr++): ?>
                <a
                  data-admin-instagram-link
                  class="posts-pagination-link<?= $curr === $page ? ' is-active' : '' ?>"
                  href="<?= htmlspecialchars($buildUrl(['page' => $curr]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                ><?= $curr ?></a>
              <?php endfor; ?>

              <a
                data-admin-instagram-link
                class="posts-pagination-link posts-pagination-link-wide<?= $totalPosts === 0 || $page >= $pages ? ' is-disabled' : '' ?>"
                href="<?= htmlspecialchars($buildUrl(['page' => $page + 1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
              >Próxima</a>
            </nav>
          <?php endif; ?>
        </div>
      </div>
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
              <?= $alcanceVal !== null ? $fmt($alcanceVal) : '—' ?>
            </div>
            <div class="stat-label">Alcance</div>
            <div class="admin-summary-card__hint">Contas únicas que visualizaram publicações.</div>
          </div>

          <div class="stat-card stat-card-compact admin-summary-card">
            <div class="stat-icon" style="background: #facc1522; color: #facc15;">
              <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
            </div>
            <div class="stat-value neon-text" style="color: #facc15;" data-insight-val="visualizacoes">
              <?= ($insights !== null && (isset($insights['visualizacoes']) || isset($insights['impressoes']))) ? $fmt((int) ($insights['visualizacoes'] ?? $insights['impressoes'])) : '—' ?>
            </div>
            <div class="stat-label">Visualizações</div>
            <div class="admin-summary-card__hint">Total de exibições e reproduções no período.</div>
          </div>

          <div class="stat-card stat-card-compact admin-summary-card">
            <div class="stat-icon" style="background: #34d39922; color: #34d399;">
              <i class="fa-solid fa-user-check" aria-hidden="true"></i>
            </div>
            <div class="stat-value neon-text" style="color: #34d399;" data-insight-val="visitas_perfil">
              <?= ($insights !== null && isset($insights['visitas_perfil'])) ? $fmt((int) $insights['visitas_perfil']) : '—' ?>
            </div>
            <div class="stat-label">Visitas ao perfil</div>
            <div class="admin-summary-card__hint">Usuários que acessaram a página do perfil.</div>
          </div>

          <div class="stat-card stat-card-compact admin-summary-card">
            <div class="stat-icon" style="background: #f472b622; color: #f472b6;">
              <i class="fa-solid fa-heart" aria-hidden="true"></i>
            </div>
            <div class="stat-value neon-text" style="color: #f472b6;" data-insight-val="interacoes">
              <?= ($insights !== null && isset($insights['interacoes'])) ? $fmt((int) $insights['interacoes']) : '—' ?>
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

  // Dados iniciais do servidor (mesmo formato da resposta ?ajax=metrics)
  var IG_INITIAL = <?= json_encode([
      'daily'  => array_values($dailyPerformance),
      'deltas' => $kpiMeta['deltas'],
      'series' => $kpiMeta['series'],
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: 'null' ?> || {};

  var reduceMotion = false;
  try { reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (e) {}

  // Força o navegador a aplicar o estado inicial antes da transição
  function reflow(el) {
    if (el && el.getBoundingClientRect) el.getBoundingClientRect();
  }

  // Conta do valor exibido até o novo valor (o atual fica em data-kpi-num)
  function animateNumber(el, to) {
    if (!el) return;
    if (to === null || typeof to === 'undefined') {
      el._igAnim = null;
      el.textContent = '—';
      el.setAttribute('data-kpi-num', '');
      return;
    }
    to = Number(to) || 0;
    var fromAttr = el.getAttribute('data-kpi-num');
    var from = (fromAttr === null || fromAttr === '') ? 0 : Number(fromAttr) || 0;
    el.setAttribute('data-kpi-num', String(to));
    if (reduceMotion || from === to) {
      el._igAnim = null;
      el.textContent = formatNum(to);
      return;
    }
    var token = {};
    el._igAnim = token;
    var t0 = null;
    var dur = 700;
    function step(ts) {
      if (el._igAnim !== token) return;
      if (t0 === null) t0 = ts;
      var k = Math.min(1, (ts - t0) / dur);
      var e = 1 - Math.pow(1 - k, 3);
      el.textContent = formatNum(Math.round(from + (to - from) * e));
      if (k < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  // Badges de variação e sparklines dos 4 KPIs
  var KPI_COLORS = { followers: '#38bdf8', follows: '#c084fc', media: '#f472b6', alcance: '#fbbf24' };
  var BADGE_BASE = 'inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold ';
  var BADGE_UP   = 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30';
  var BADGE_DOWN = 'bg-rose-500/15 text-rose-400 border border-rose-500/30';
  var BADGE_FLAT = 'bg-slate-800/80 text-slate-400 border border-slate-700/60';

  function sparkSvg(values, color) {
    var vals = (Array.isArray(values) ? values : [])
      .map(function (v) { return Number(v); })
      .filter(function (v) { return isFinite(v); })
      .slice(-30);
    if (vals.length < 2) {
      return '<div style="font-size:9px;line-height:24px;color:#64748b;text-align:right;white-space:nowrap" title="A curva aparece a partir do segundo registro diário">sem histórico ainda</div>';
    }
    var min = Math.min.apply(null, vals);
    var max = Math.max.apply(null, vals);
    var span = max - min;
    var stepX = 96 / (vals.length - 1);
    var pts = vals.map(function (v, i) {
      var y = span === 0 ? 18 : 31 - ((v - min) / span) * 26;
      return [2 + i * stepX, y];
    });
    var poly = pts.map(function (p) { return p[0].toFixed(1) + ',' + p[1].toFixed(1); }).join(' ');
    var last = pts[pts.length - 1];
    return '<svg class="w-16 h-6" viewBox="0 0 100 35" fill="none">' +
      '<polyline points="' + poly + '" stroke="' + color + '" stroke-opacity="0.85" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>' +
      '<circle cx="' + last[0].toFixed(1) + '" cy="' + last[1].toFixed(1) + '" r="2.5" fill="' + color + '"/>' +
      '</svg>';
  }

  function renderKpiExtras(deltas, series) {
    deltas = deltas || {};
    series = series || {};
    ['followers', 'follows', 'media', 'alcance'].forEach(function (key) {
      var badge = document.querySelector('[data-kpi-badge="' + key + '"]');
      var d = key === 'alcance' ? deltas.alcance_pct : deltas[key];
      if (badge) {
        var suffix = key === 'alcance' ? '%' : '';
        if (typeof d === 'number') {
          if (d === 0) {
            badge.className = BADGE_BASE + BADGE_FLAT;
            badge.textContent = '= 0' + suffix;
          } else {
            badge.className = BADGE_BASE + (d > 0 ? BADGE_UP : BADGE_DOWN);
            badge.innerHTML = '<i class="fa-solid ' + (d > 0 ? 'fa-arrow-up' : 'fa-arrow-down') + ' text-[8px]"></i> ' +
              (d > 0 ? '+' : '−') + formatNum(Math.abs(d)) + suffix;
          }
          badge.title = key === 'alcance'
            ? 'Variação do alcance em relação ao registro anterior do mesmo período'
            : 'Variação em relação ao registro anterior';
        } else {
          badge.className = BADGE_BASE + BADGE_FLAT;
          badge.textContent = '—';
          badge.title = 'Ainda não há registro anterior para comparar';
        }
      }
      var spark = document.querySelector('[data-kpi-spark="' + key + '"]');
      if (spark) spark.innerHTML = sparkSvg(series[key], KPI_COLORS[key]);
    });
  }

  // Agrupa os pontos de desempenho nas faixas adaptativas (FEAT-010 2.14.0)
  var MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
  function groupDaily(daily) {
    var n = daily.length;
    if (n === 0) return { mode: 'dia', buckets: [], labelStep: 1 };

    // Se o backend já enviou os pontos particionados nas faixas com datas e alcance
    if (daily[0].data_inicio !== undefined && daily[0].data_fim !== undefined) {
      var s0 = new Date(daily[0].data_inicio + 'T00:00:00');
      var e0 = new Date(daily[0].data_fim + 'T00:00:00');
      var diffDays = Math.max(1, Math.round((e0 - s0) / 86400000) + 1);
      var mode = diffDays <= 1 ? 'dia' : (diffDays <= 3 ? '3 dias' : (diffDays <= 7 ? 'semana' : 'blocos de 28 dias'));
      var maxLabels = (mode === 'semana' || mode === 'blocos de 28 dias') ? 6 : 10;
      return { mode: mode, buckets: daily, labelStep: Math.max(1, Math.ceil(daily.length / maxLabels)) };
    }

    var mode = n <= 14 ? 'dia' : (n <= 45 ? '3 dias' : (n <= 180 ? 'semana' : 'blocos de 28 dias'));
    var buckets = [];

    function pushChunk(chunk, label, tip) {
      var sum = 0;
      var sumAlc = 0;
      var hasAlc = false;
      var any = false;
      chunk.forEach(function (d) {
        sum += Number(d.curtidas || 0);
        if (typeof d.alcance === 'number') { sumAlc += d.alcance; hasAlc = true; }
        if (d.has_post) any = true;
      });
      buckets.push({
        label: label,
        tip: tip,
        curtidas: sum,
        alcance: hasAlc ? sumAlc : null,
        has_post: any
      });
    }

    if (mode === 'dia') {
      daily.forEach(function (d) { pushChunk([d], d.label, d.label); });
    } else if (mode === '3 dias') {
      for (var i = 0; i < n; i += 3) {
        var chunk = daily.slice(i, i + 3);
        var lbl = chunk.length > 1 ? chunk[0].label + '–' + chunk[chunk.length - 1].label : chunk[0].label;
        pushChunk(chunk, lbl, lbl);
      }
    } else if (mode === 'semana') {
      var first = n % 7;
      var starts = [];
      if (first > 0) starts.push([0, first]);
      for (var i = first; i < n; i += 7) starts.push([i, i + 7]);
      starts.forEach(function (r) {
        var chunk = daily.slice(r[0], r[1]);
        var lbl = chunk.length > 1 ? chunk[0].label + '–' + chunk[chunk.length - 1].label : chunk[0].label;
        pushChunk(chunk, lbl, lbl);
      });
    } else {
      for (var i = 0; i < n; i += 28) {
        var chunk = daily.slice(i, i + 28);
        var lbl = chunk.length > 1 ? chunk[0].label + '–' + chunk[chunk.length - 1].label : chunk[0].label;
        pushChunk(chunk, lbl, lbl);
      }
    }

    var maxLabels = (mode === 'semana' || mode === 'blocos de 28 dias') ? 6 : 10;
    return { mode: mode, buckets: buckets, labelStep: Math.max(1, Math.ceil(buckets.length / maxLabels)) };
  }

  // Renderizador dinâmico do gráfico de Desempenho SVG com Curtidas e Alcance
  function renderPerfChart(daily, animate) {
    var wrap = document.getElementById('ig-perf-chart-wrap');
    if (!wrap) return;

    var grouped = groupDaily(Array.isArray(daily) ? daily : []);
    var buckets = grouped.buckets;
    var perfCount = buckets.length;

    var groupingEl = document.getElementById('ig-perf-grouping');
    if (groupingEl) {
      groupingEl.textContent = perfCount > 0
        ? ' Pontos agrupados por ' + (grouped.mode === 'mes' ? 'mês' : grouped.mode) + '.'
        : '';
    }

    var maxVal = 1;
    var hasPosts = false;
    var hasReach = false;
    for (var i = 0; i < perfCount; i++) {
      if (buckets[i].curtidas > maxVal) maxVal = buckets[i].curtidas;
      if (typeof buckets[i].alcance === 'number' && buckets[i].alcance > maxVal) maxVal = buckets[i].alcance;
      if (buckets[i].has_post) hasPosts = true;
      if (typeof buckets[i].alcance === 'number' && buckets[i].alcance > 0) hasReach = true;
    }
    maxVal = Math.max(5, Math.ceil(maxVal * 1.25));

    var chartH = 200;
    var inset = (grouped.mode === 'semana' || grouped.mode === 'blocos de 28 dias') ? 30 : 0;
    var x0 = 40 + inset;
    var x1 = 660 - inset;
    var stepX = perfCount > 1 ? (x1 - x0) / (perfCount - 1) : 0;

    var ptsCur = [];
    var ptsAlc = [];
    for (var j = 0; j < perfCount; j++) {
      var cx = perfCount > 1 ? x0 + (j * stepX) : 350;
      var cyCur = chartH - 30 - ((buckets[j].curtidas / maxVal) * (chartH - 60));
      ptsCur.push([cx, cyCur]);

      var aVal = typeof buckets[j].alcance === 'number' ? buckets[j].alcance : 0;
      var cyAlc = chartH - 30 - ((aVal / maxVal) * (chartH - 60));
      ptsAlc.push([cx, cyAlc]);
    }

    var polyCur = ptsCur.map(function (p) { return p[0].toFixed(1) + ',' + p[1].toFixed(1); }).join(' ');
    var polyAlc = ptsAlc.map(function (p) { return p[0].toFixed(1) + ',' + p[1].toFixed(1); }).join(' ');

    var html = '<svg class="w-full h-56" viewBox="0 0 680 210" preserveAspectRatio="none">';
    html += '<defs>';
    html += '<linearGradient id="gradCur" x1="0" y1="0" x2="0" y2="1">';
    html += '<stop offset="0%" stop-color="#f43f5e" stop-opacity="0.25"/>';
    html += '<stop offset="100%" stop-color="#f43f5e" stop-opacity="0.0"/>';
    html += '</linearGradient>';
    html += '<linearGradient id="gradAlc" x1="0" y1="0" x2="0" y2="1">';
    html += '<stop offset="0%" stop-color="#fbbf24" stop-opacity="0.20"/>';
    html += '<stop offset="100%" stop-color="#fbbf24" stop-opacity="0.0"/>';
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
      if (hasReach) {
        var lastPtAlc = ptsAlc[ptsAlc.length - 1];
        html += '<polygon data-perf-reach-area points="' + ptsAlc[0][0].toFixed(1) + ',180 ' + polyAlc + ' ' + lastPtAlc[0].toFixed(1) + ',180" fill="url(#gradAlc)" />';
        html += '<polyline data-perf-reach-line points="' + polyAlc + '" fill="none" stroke="#fbbf24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>';
      }

      if (hasPosts) {
        var lastPtCur = ptsCur[ptsCur.length - 1];
        html += '<polygon data-perf-area points="' + ptsCur[0][0].toFixed(1) + ',180 ' + polyCur + ' ' + lastPtCur[0].toFixed(1) + ',180" fill="url(#gradCur)" />';
        html += '<polyline data-perf-line points="' + polyCur + '" fill="none" stroke="#f43f5e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>';
      }

      if (!hasPosts && !hasReach) {
        html += '<text x="350" y="105" fill="#64748b" font-size="11" text-anchor="middle">Sem publicações ou dados de alcance registrados no período selecionado.</text>';
      }

      for (var k = 0; k < perfCount; k++) {
        var pCur = ptsCur[k];
        var item = buckets[k];
        if (k % grouped.labelStep === 0) {
          html += '<text x="' + pCur[0].toFixed(1) + '" y="200" fill="#94a3b8" font-size="10" text-anchor="middle">' + escapeHtml(item.label) + '</text>';
        }
        if (typeof item.alcance === 'number' && item.alcance > 0) {
          var pAlc = ptsAlc[k];
          html += '<circle data-perf-reach-dot cx="' + pAlc[0].toFixed(1) + '" cy="' + pAlc[1].toFixed(1) + '" r="3" fill="#fbbf24" stroke="#0f172a" stroke-width="1.5"><title>' + escapeHtml(item.tip || item.label) + ' - Alcance: ' + item.alcance + '</title></circle>';
        }
        if (item.has_post) {
          html += '<circle data-perf-dot cx="' + pCur[0].toFixed(1) + '" cy="' + pCur[1].toFixed(1) + '" r="3.5" fill="#f43f5e" stroke="#0f172a" stroke-width="1.5"><title>' + escapeHtml(item.tip || item.label) + ' - Curtidas: ' + item.curtidas + '</title></circle>';
        }
      }
    }
    html += '</svg>';
    wrap.innerHTML = html;

    if (!animate || reduceMotion) return;

    // Linha de curtidas
    var line = wrap.querySelector('[data-perf-line]');
    var area = wrap.querySelector('[data-perf-area]');
    var dots = wrap.querySelectorAll('[data-perf-dot]');
    var len = (line && line.getTotalLength) ? line.getTotalLength() : 0;
    if (line && len > 0) {
      line.style.strokeDasharray = len.toFixed(1);
      line.style.strokeDashoffset = len.toFixed(1);
    }
    if (area) area.style.opacity = '0';
    dots.forEach(function (dot) { dot.style.opacity = '0'; });

    // Linha de alcance
    var rLine = wrap.querySelector('[data-perf-reach-line]');
    var rArea = wrap.querySelector('[data-perf-reach-area]');
    var rDots = wrap.querySelectorAll('[data-perf-reach-dot]');
    var rLen = (rLine && rLine.getTotalLength) ? rLine.getTotalLength() : 0;
    if (rLine && rLen > 0) {
      rLine.style.strokeDasharray = rLen.toFixed(1);
      rLine.style.strokeDashoffset = rLen.toFixed(1);
    }
    if (rArea) rArea.style.opacity = '0';
    rDots.forEach(function (dot) { dot.style.opacity = '0'; });

    reflow(wrap);

    if (line && len > 0) {
      line.style.transition = 'stroke-dashoffset .8s cubic-bezier(.22,.61,.36,1)';
      line.style.strokeDashoffset = '0';
    }
    if (area) {
      area.style.transition = 'opacity .5s ease .35s';
      area.style.opacity = '1';
    }

    if (rLine && rLen > 0) {
      rLine.style.transition = 'stroke-dashoffset .8s cubic-bezier(.22,.61,.36,1) .1s';
      rLine.style.strokeDashoffset = '0';
    }
    if (rArea) {
      rArea.style.transition = 'opacity .5s ease .45s';
      rArea.style.opacity = '1';
    }

    var lastX = ptsCur.length ? ptsCur[ptsCur.length - 1][0] : 1;
    dots.forEach(function (dot) {
      var frac = (Number(dot.getAttribute('cx')) - x0) / Math.max(1, lastX - x0);
      dot.style.transition = 'opacity .25s ease ' + (0.1 + 0.7 * Math.max(0, frac)).toFixed(2) + 's';
      dot.style.opacity = '1';
    });
    rDots.forEach(function (dot) {
      var frac = (Number(dot.getAttribute('cx')) - x0) / Math.max(1, lastX - x0);
      dot.style.transition = 'opacity .25s ease ' + (0.15 + 0.7 * Math.max(0, frac)).toFixed(2) + 's';
      dot.style.opacity = '1';
    });
  }
  }

  // Renderizador dinâmico do Donut e Legendas
  function renderDonutChart(contentTypes, animate) {
    var svgWrap = document.getElementById('ig-donut-svg-wrap');
    var legendsWrap = document.getElementById('ig-donut-legends-wrap');
    if (!svgWrap || !legendsWrap || !contentTypes) return;

    var total = Math.max(0, contentTypes.total || 0);
    var prevTotalEl = svgWrap.querySelector('[data-donut-total]');
    var prevTotal = prevTotalEl ? (Number(prevTotalEl.getAttribute('data-kpi-num') || String(prevTotalEl.textContent).replace(/\D/g, '')) || 0) : 0;
    var items = contentTypes.items || {};
    var pctImg = (total > 0 && items.imagem && items.imagem.pct) ? items.imagem.pct : 0;
    var pctVid = (total > 0 && items.reels && items.reels.pct) ? items.reels.pct : 0;
    var pctCar = (total > 0 && items.carrossel && items.carrossel.pct) ? items.carrossel.pct : 0;
    var pctSty = (total > 0 && items.story && items.story.pct) ? items.story.pct : 0;

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
    var segs = [[lenImg, offImg, '#f43f5e'], [lenVid, offVid, '#a855f7'], [lenCar, offCar, '#06b6d4'], [lenSty, offSty, '#f59e0b']];
    if (total > 0) {
      segs.forEach(function (sg) {
        svgHtml += '<circle data-donut-seg="' + sg[0].toFixed(1) + '" cx="80" cy="80" r="60" fill="none" stroke="' + sg[2] + '" stroke-width="18" stroke-dasharray="' + sg[0].toFixed(1) + ' ' + (cCirc - sg[0]).toFixed(1) + '" stroke-dashoffset="' + sg[1].toFixed(1) + '" />';
      });
    }
    svgHtml += '</svg>';
    svgHtml += '<div class="absolute inset-0 flex flex-col items-center justify-center text-center">';
    svgHtml += '<span class="text-2xl font-black text-white leading-none" data-donut-total data-kpi-num="' + prevTotal + '">' + formatNum(animate ? prevTotal : total) + '</span>';
    svgHtml += '<span class="text-[10px] uppercase tracking-wide text-slate-400 mt-1 font-bold">Posts no período</span>';
    svgHtml += '</div>';

    svgWrap.innerHTML = svgHtml;

    if (animate) {
      animateNumber(svgWrap.querySelector('[data-donut-total]'), total);
      if (!reduceMotion) {
        // Cada segmento cresce a partir do próprio início até o tamanho final
        var segEls = svgWrap.querySelectorAll('[data-donut-seg]');
        segEls.forEach(function (el) { el.style.strokeDasharray = '0 ' + cCirc; });
        reflow(svgWrap);
        segEls.forEach(function (el) {
          var len = Number(el.getAttribute('data-donut-seg')) || 0;
          el.style.transition = 'stroke-dasharray .8s cubic-bezier(.22,.61,.36,1)';
          el.style.strokeDasharray = len.toFixed(1) + ' ' + (cCirc - len).toFixed(1);
        });
      }
    }

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

        // Atualizar KPIs (números contam até o novo valor; badges e sparklines com dados reais)
        if (data.kpis) {
          var kpis = data.kpis;
          animateNumber(document.querySelector('[data-kpi-val="followers"]'), kpis.followers);
          animateNumber(document.querySelector('[data-kpi-val="follows"]'), kpis.follows);
          animateNumber(document.querySelector('[data-kpi-val="media"]'), kpis.media);
          animateNumber(document.querySelector('[data-kpi-val="alcance"]'), kpis.alcance);
          var elAlcLabel = document.querySelector('[data-kpi-val="alcance-label"]');
          if (elAlcLabel) {
            if (data.alcance_modo === 'soma_blocos') {
              elAlcLabel.textContent = 'Alcance (soma dos blocos)';
              elAlcLabel.title = 'Soma dos alcances de cada bloco de até 28 dias. A mesma pessoa pode ter sido contabilizada em mais de um bloco.';
            } else {
              elAlcLabel.textContent = 'Alcance (' + data.period + ')';
              elAlcLabel.title = 'Alcance total oficial no período selecionado (Meta Graph API).';
            }
          }
          renderKpiExtras(kpis.deltas, kpis.series);
        }

        // Atualizar Insights Detalhados (Aba 4)
        var inAlc = document.querySelector('[data-insight-val="alcance"]');
        var inVis = document.querySelector('[data-insight-val="visualizacoes"]');
        var inPer = document.querySelector('[data-insight-val="visitas_perfil"]');
        var inInt = document.querySelector('[data-insight-val="interacoes"]');

        if (data.insights) {
          var ins = data.insights;
          if (inAlc) inAlc.textContent = (ins.alcance !== null && typeof ins.alcance !== 'undefined') ? formatNum(ins.alcance) : '—';
          if (inVis) inVis.textContent = (ins.visualizacoes !== null && typeof ins.visualizacoes !== 'undefined') ? formatNum(ins.visualizacoes) : '—';
          if (inPer) inPer.textContent = (ins.visitas_perfil !== null && typeof ins.visitas_perfil !== 'undefined') ? formatNum(ins.visitas_perfil) : '—';
          if (inInt) inInt.textContent = (ins.interacoes !== null && typeof ins.interacoes !== 'undefined') ? formatNum(ins.interacoes) : '—';
        } else {
          if (inAlc) inAlc.textContent = '—';
          if (inVis) inVis.textContent = '—';
          if (inPer) inPer.textContent = '—';
          if (inInt) inInt.textContent = '—';
        }

        // Redesenhar gráfico de desempenho
        if (Array.isArray(data.daily_performance)) {
          renderPerfChart(data.daily_performance, true);
        }

        // Redesenhar gráfico donut
        if (data.content_types) {
          renderDonutChart(data.content_types, true);
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

        // Atualizar aviso de ajuste de datas
        var noticeWrap = document.getElementById('ig-period-notice');
        var noticeText = document.getElementById('ig-period-notice-text');
        if (noticeWrap && noticeText) {
          if (data.adjusted_notice) {
            noticeText.textContent = data.adjusted_notice;
            noticeWrap.classList.remove('hidden');
          } else {
            noticeText.textContent = '';
            noticeWrap.classList.add('hidden');
          }
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

  // Alternador de Abas (Métricas, Posts, Insights)
  window.switchIgTab = function (tabName) {
    var tabs = ['metricas', 'feed', 'insights'];
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
    if (urlParams.has('page') || urlParams.has('busca') || urlParams.has('tipo') || urlParams.has('status') || urlParams.has('origem') || urlParams.has('periodo_post')) {
      savedTab = 'feed';
    }
    if (savedTab && ['metricas', 'feed', 'insights'].includes(savedTab)) {
      window.switchIgTab(savedTab);
    }
  } catch (e) {}

  // Render inicial dos KPIs e do gráfico com os dados do servidor
  renderKpiExtras(IG_INITIAL.deltas, IG_INITIAL.series);
  renderPerfChart(IG_INITIAL.daily, true);
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

<?php if (!isset($_GET['_partial']) || (string) $_GET['_partial'] !== '1'): ?>
<script src="<?= url('/assets/js/admin-instagram-posts.js') . '?v=' . @filemtime(base_path('public/assets/js/admin-instagram-posts.js')) ?>" defer></script>
<?php endif; ?>
