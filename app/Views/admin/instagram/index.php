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
$insights    = $insights ?? null;
$viewMode    = isset($_GET['view']) && in_array((string) $_GET['view'], ['grid', 'table'], true) ? (string) $_GET['view'] : 'table';

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

    <!-- Status da Conexão & Botão de Sincronização -->
    <section class="admin-panel">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <?php $synced = trim((string) ($account['synced_at'] ?? '')); ?>
          <span class="admin-chip <?= $synced !== '' ? 'border-emerald-500/40 text-emerald-200' : 'border-slate-600 text-slate-300' ?>">
            <i class="fa-solid fa-circle <?= $synced !== '' ? 'text-emerald-400' : 'text-slate-500' ?> text-[8px]" aria-hidden="true"></i>
            <?= $synced !== '' ? 'Conectado' : 'Nunca sincronizado' ?>
          </span>
          <span class="text-xs text-slate-400" data-ig-synced-label>
            <?= $synced !== '' ? 'Última sincronização: ' . $esc(date('d/m/Y H:i', strtotime($synced) ?: time())) : 'Sincronize para puxar o perfil, métricas e todos os posts da Meta.' ?>
          </span>
        </div>
        <button type="button" class="admin-btn admin-btn-secondary" data-ig-sync-btn data-csrf="<?= $esc($csrfToken) ?>">
          <i class="fa-solid fa-rotate" aria-hidden="true"></i> <span data-ig-sync-label>Sincronizar Agora</span>
        </button>
      </div>
      <div class="mt-2 text-xs" data-ig-sync-feedback></div>
    </section>

    <!-- Header do Perfil -->
    <section class="admin-panel">
      <div class="flex flex-wrap items-center gap-5">
        <div class="h-16 w-16 shrink-0 rounded-full overflow-hidden border border-slate-700 bg-slate-800 flex items-center justify-center">
          <?php $pic = trim((string) ($account['profile_picture'] ?? '')); ?>
          <?php if ($pic !== ''): ?>
            <img src="<?= $esc($pic) ?>" alt="Foto de perfil" class="h-full w-full object-cover">
          <?php else: ?>
            <i class="fa-brands fa-instagram text-2xl text-slate-500" aria-hidden="true"></i>
          <?php endif; ?>
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-lg font-black text-white">@<?= $esc((string) ($account['username'] ?? '')) ?></div>
          <?php $bio = trim((string) ($account['bio'] ?? '')); ?>
          <?php if ($bio !== ''): ?>
            <div class="text-sm text-slate-300 mt-1"><?= $esc($excerpt($bio, 140)) ?></div>
          <?php endif; ?>
        </div>
        <div class="flex items-center gap-6 text-center">
          <div>
            <div class="flex items-center justify-center gap-1.5">
              <span class="text-lg font-black text-white"><?= $fmt((int) ($account['followers_count'] ?? 0)) ?></span>
              <?php
              $delta = (int) ($insights['variacao_seguidores'] ?? 0);
              if ($delta > 0): ?>
                <span class="text-xs font-bold text-emerald-400" title="Variação recente">+<?= $fmt($delta) ?></span>
              <?php elseif ($delta < 0): ?>
                <span class="text-xs font-bold text-rose-400" title="Variação recente"><?= $fmt($delta) ?></span>
              <?php endif; ?>
            </div>
            <div class="text-[11px] uppercase tracking-wide text-slate-400">Seguidores</div>
          </div>
          <div>
            <div class="text-lg font-black text-white"><?= $fmt((int) ($account['follows_count'] ?? 0)) ?></div>
            <div class="text-[11px] uppercase tracking-wide text-slate-400">Seguindo</div>
          </div>
          <div>
            <div class="text-lg font-black text-white"><?= $fmt((int) ($account['media_count'] ?? $totalPosts)) ?></div>
            <div class="text-[11px] uppercase tracking-wide text-slate-400">Mídias</div>
          </div>
        </div>
      </div>
    </section>

    <!-- Métricas com Filtro de Data e Atalhos -->
    <section class="admin-panel">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <div class="admin-panel-title">
            <i class="fa-solid fa-chart-simple text-cyan-300" aria-hidden="true"></i>
            <span>Métricas</span>
          </div>
          <div class="text-xs text-slate-400 mt-1">Desempenho da conta no período selecionado</div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <!-- Chip com o intervalo ativo -->
          <div class="admin-chip">
            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
            <?= $esc(date('d/m/Y', strtotime($start))) ?> a <?= $esc(date('d/m/Y', strtotime($end))) ?>
          </div>

          <!-- Atalhos rápidos de intervalo -->
          <div class="inline-flex rounded-xl border border-slate-700 overflow-hidden text-xs font-bold">
            <?php foreach (['7d' => '7 dias', '14d' => '14 dias', '21d' => '21 dias', '30d' => '30 dias'] as $pKey => $pLabel): ?>
              <?php $activeP = ($period === $pKey); ?>
              <a
                href="<?= htmlspecialchars($buildUrl(['period' => $pKey, 'start' => '', 'end' => '', 'page' => 1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                class="px-3 py-1.5 transition <?= $activeP ? 'bg-cyan-500/20 text-cyan-200' : 'text-slate-400 hover:text-white' ?>"
              >
                <?= $pLabel ?>
              </a>
            <?php endforeach; ?>
          </div>

          <!-- Formulário de intervalo livre de datas -->
          <form method="GET" action="<?= htmlspecialchars($baseUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="period" value="custom">
            <input type="hidden" name="busca" value="<?= $esc($busca) ?>">
            <input type="hidden" name="tipo" value="<?= $esc($tipo) ?>">
            <input type="hidden" name="status" value="<?= $esc($status) ?>">
            <input type="hidden" name="sort" value="<?= $esc($sort) ?>">
            <input type="hidden" name="dir" value="<?= $esc($dir) ?>">
            <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
            <input
              type="date"
              name="start"
              value="<?= $esc($start) ?>"
              class="nerd-input px-3 py-1.5 rounded-xl text-xs font-black"
              aria-label="Data inicial"
            >
            <input
              type="date"
              name="end"
              value="<?= $esc($end) ?>"
              class="nerd-input px-3 py-1.5 rounded-xl text-xs font-black"
              aria-label="Data final"
            >
            <button type="submit" class="admin-btn admin-btn-primary !px-3 !py-1.5 text-xs">
              <i class="fa-solid fa-filter" aria-hidden="true"></i> Aplicar
            </button>
          </form>
        </div>
      </div>

      <!-- Cards de Métricas -->
      <?php
      $alcanceVal      = (int) ($insights['alcance'] ?? 0);
      $viewsVal        = (int) ($insights['visualizacoes'] ?? $insights['impressoes'] ?? 0);
      $visitasVal      = (int) ($insights['visitas_perfil'] ?? 0);
      $interacoesVal   = (int) ($insights['interacoes'] ?? 0);
      ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-5">
        <div class="stat-card stat-card-compact admin-summary-card">
          <div class="stat-icon" style="background: #00d4ff22; color: #00d4ff;">
            <i class="fa-solid fa-eye" aria-hidden="true"></i>
          </div>
          <div class="stat-value neon-text" style="color: #00d4ff;">
            <?= $fmt($alcanceVal) ?>
          </div>
          <div class="stat-label">Alcance</div>
          <div class="admin-summary-card__hint">Contas únicas que visualizaram publicações.</div>
        </div>

        <div class="stat-card stat-card-compact admin-summary-card">
          <div class="stat-icon" style="background: #facc1522; color: #facc15;">
            <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
          </div>
          <div class="stat-value neon-text" style="color: #facc15;">
            <?= $fmt($viewsVal) ?>
          </div>
          <div class="stat-label">Visualizações</div>
          <div class="admin-summary-card__hint">Total de exibições e reproduções no período.</div>
        </div>

        <div class="stat-card stat-card-compact admin-summary-card">
          <div class="stat-icon" style="background: #34d39922; color: #34d399;">
            <i class="fa-solid fa-user-check" aria-hidden="true"></i>
          </div>
          <div class="stat-value neon-text" style="color: #34d399;">
            <?= $fmt($visitasVal) ?>
          </div>
          <div class="stat-label">Visitas ao perfil</div>
          <div class="admin-summary-card__hint">Usuários que acessaram a página do perfil.</div>
        </div>

        <div class="stat-card stat-card-compact admin-summary-card">
          <div class="stat-icon" style="background: #f472b622; color: #f472b6;">
            <i class="fa-solid fa-heart" aria-hidden="true"></i>
          </div>
          <div class="stat-value neon-text" style="color: #f472b6;">
            <?= $fmt($interacoesVal) ?>
          </div>
          <div class="stat-label">Interações</div>
          <div class="admin-summary-card__hint">Curtidas, comentários, salvamentos e envios.</div>
        </div>
      </div>
    </section>

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
<?php endif; ?>
