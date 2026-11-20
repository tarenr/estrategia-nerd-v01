<?php
/**
 * @file        app/Views/admin/youtube/index.php
 * @project     Estrategia Nerd
 * @purpose     Dashboard principal do módulo YouTube (Paridade com Instagram)
 *
 * Variáveis disponíveis (injetadas pelo YouTubeController::index()):
 * @var string                        $title
 * @var array<string,mixed>|null      $account           Conta ativa do YouTube
 * @var array<string,mixed>           $videos_paged      Vídeos paginados (items, total, page, per_page, pages)
 * @var array<string,string>          $filters           Filtros ativos (busca, tipo, status, origem)
 * @var string                        $sort              Coluna de ordenação
 * @var string                        $dir               Direção da ordenação (asc/desc)
 * @var string                        $period            Período ativo (7d, 14d, 30d, custom)
 * @var string                        $start             Data inicial YYYY-MM-DD
 * @var string                        $end               Data final YYYY-MM-DD
 * @var array<string,mixed>           $content_types     Distribuição entre vídeos e shorts
 * @var list<array<string,mixed>>     $daily_performance Desempenho diário de visualizações e engajamento
 * @var array<string,mixed>           $kpi_meta          Deltas e agregados
 * @var bool                          $oauth_configured  Se as credenciais estão no .env
 * @var bool                          $connected         Se a conta está conectada
 * @var string                        $csrf_token        Token CSRF
 * @var string|null                   $notice            Mensagem flash de sucesso
 * @var string|null                   $error             Mensagem flash de erro
 * @var int                           $phase_views       Soma das visualizações da fase nova
 * @var int                           $active_videos     Vídeos sem cancelados e sem não listados
 * @var bool                          $only_list         true em ?ajax=videos: devolve só _lista-videos.php
 */

declare(strict_types=1);

$account = $account ?? null;
$videosPaged = $videos_paged ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 8, 'pages' => 1];
$items = (array) ($videosPaged['items'] ?? []);
$totalVideos = (int) ($videosPaged['total'] ?? 0);
$page = max(1, (int) ($videosPaged['page'] ?? 1));
$perPage = max(1, (int) ($videosPaged['per_page'] ?? 8));
$pages = max(1, (int) ($videosPaged['pages'] ?? 1));

$filters = $filters ?? ['busca' => '', 'tipo' => '', 'status' => '', 'origem' => '', 'privacidade' => ''];
$busca = (string) ($filters['busca'] ?? '');
$tipo = (string) ($filters['tipo'] ?? '');
$status = (string) ($filters['status'] ?? '');
$origem = (string) ($filters['origem'] ?? '');
$privacidade = (string) ($filters['privacidade'] ?? '');

$sort = (string) ($sort ?? '');
$dir = (string) ($dir ?? 'desc');
$period = (string) ($period ?? '7d');
$start = (string) ($start ?? date('Y-m-d', strtotime('-6 days')));
$end = (string) ($end ?? date('Y-m-d'));
$viewMode = isset($_GET['view']) && in_array((string) $_GET['view'], ['grid', 'table'], true) ? (string) $_GET['view'] : 'table';
$contentTypes = $content_types ?? ['total' => $totalVideos, 'items' => []];
$dailyPerformance = $daily_performance ?? [];
$kpiMeta = $kpi_meta ?? [
    'deltas' => ['subscribers' => null, 'views' => null, 'videos' => null, 'engagement' => null],
    'aggregates' => ['views' => 0, 'likes' => 0, 'comments' => 0, 'total_published' => 0],
];

$fmt = static fn (int|float $v): string => number_format((float) $v, 0, ',', '.');
$esc = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$formatDuration = static function (int $seconds): string {
    if ($seconds <= 0) return '—';
    $h = (int) floor($seconds / 3600);
    $m = (int) floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    if ($h > 0) {
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
    return sprintf('%02d:%02d', $m, $s);
};

$formatDate = static function (?string $value): string {
    if (!$value) return '—';
    try {
        return (new DateTimeImmutable((string) $value))->format('d/m/Y H:i');
    } catch (\Throwable) {
        return (string) $value;
    }
};

// Datas do vídeo: data de envio/postagem no YouTube e data de agendamento/público
$videoDate = static function (array $v) use ($formatDate): array {
    $postado = $formatDate($v['uploaded_at'] ?? $v['criado_em'] ?? null);

    $isAgendado = in_array((string) ($v['status'] ?? ''), ['agendado', 'agendado_remoto'], true) && !empty($v['scheduled_at']);
    if ($isAgendado) {
        return [
            'postado' => $postado,
            'label' => 'Agendado',
            'valor' => $formatDate((string) $v['scheduled_at']),
            'classe' => 'text-cyan-300 font-semibold',
            'icone' => 'fa-regular fa-calendar-clock text-cyan-400'
        ];
    }

    $isPublicado = ($v['status'] ?? '') === 'publicado';
    if ($isPublicado) {
        return [
            'postado' => $postado,
            'label' => 'Público',
            'valor' => $formatDate($v['published_at'] ?? $v['uploaded_at'] ?? null),
            'classe' => 'text-emerald-400 font-medium',
            'icone' => 'fa-regular fa-circle-check text-emerald-400'
        ];
    }

    return [
        'postado' => $postado,
        'label' => 'Público',
        'valor' => !empty($v['scheduled_at']) ? $formatDate((string) $v['scheduled_at']) : '—',
        'classe' => 'text-slate-400',
        'icone' => 'fa-regular fa-clock text-slate-500'
    ];
};

$statusClasses = static function (string $st): string {
    return match ($st) {
        'publicado' => 'border-emerald-500/30 text-emerald-300 bg-emerald-500/10',
        'agendado', 'agendado_remoto' => 'border-cyan-500/30 text-cyan-300 bg-cyan-500/10',
        'rascunho' => 'border-slate-500/30 text-slate-300 bg-slate-500/10',
        'enviando', 'processando' => 'border-amber-500/30 text-amber-200 bg-amber-500/10',
        'erro', 'resultado_incerto' => 'border-rose-500/30 text-rose-300 bg-rose-500/10',
        'cancelado' => 'border-slate-600/30 text-slate-400 bg-slate-800/40',
        default => 'border-slate-700 text-slate-400 bg-slate-800/50',
    };
};

// Selo de privacidade: só aparece quando o vídeo não é público (lido do YouTube na sincronização).
$privacyBadge = static function (array $v): string {
    $label = match ((string) ($v['privacy_status'] ?? '')) {
        'unlisted' => 'Não listado',
        'private' => 'Privado',
        default => '',
    };
    return $label === '' ? '' : '<span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold border border-amber-500/30 text-amber-200 bg-amber-500/10" title="Privacidade no YouTube">' . $label . '</span>';
};

$statusLabel = [
    'publicado' => 'Publicado',
    'agendado' => 'Agendado',
    'agendado_remoto' => 'Agendado no YouTube',
    'rascunho' => 'Rascunho',
    'enviando' => 'Enviando',
    'processando' => 'Processando',
    'erro' => 'Erro',
    'resultado_incerto' => 'Reconciliação necessária',
    'cancelado' => 'Cancelado',
];

$baseUrl = function_exists('url') ? url('/admin/youtube') : '/admin/youtube';
$buildUrl = static function (array $overrides = []) use ($baseUrl, $filters, $sort, $dir, $page, $perPage, $period, $start, $end, $viewMode): string {
    $current = [
        'busca'    => (string) ($filters['busca'] ?? ''),
        'tipo'     => (string) ($filters['tipo'] ?? ''),
        'status'   => (string) ($filters['status'] ?? ''),
        'origem'   => (string) ($filters['origem'] ?? ''),
        'privacidade' => (string) ($filters['privacidade'] ?? ''),
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
    if (!empty($current['origem'])) {
        $clean['origem'] = (string) $current['origem'];
    }
    if (!empty($current['privacidade'])) {
        $clean['privacidade'] = (string) $current['privacidade'];
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
    if (!empty($current['sort'])) {
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

$firstItem = $totalVideos > 0 ? (($page - 1) * $perPage) + 1 : 0;
$lastItem = $totalVideos > 0 ? min($totalVideos, $page * $perPage) : 0;
$startRange = max(1, $page - 2);
$endRange = min($pages, $page + 2);
if (($endRange - $startRange) < 4) {
    $startRange = max(1, $endRange - 4);
    $endRange = min($pages, $startRange + 4);
}

// ?ajax=videos: só a lista, com os mesmos helpers acima.
if (!empty($only_list)) {
    require __DIR__ . '/_lista-videos.php';
    return;
}

$activeVideos = (int) ($active_videos ?? $totalVideos);
$periodOn = 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/40 shadow-sm';
$periodOff = 'bg-slate-800/80 text-slate-400 hover:text-white border border-slate-700/60';

$channelUrl = !empty($account['custom_url'])
    ? 'https://www.youtube.com/' . ltrim((string) $account['custom_url'], '/')
    : (!empty($account['channel_id']) ? 'https://www.youtube.com/channel/' . $account['channel_id'] : 'https://www.youtube.com');

$kpiCards = [
    [
        'key' => 'subscribers',
        'label' => 'Inscritos no canal',
        'value' => (int) ($account['subscribers_count'] ?? 0),
        'delta' => $kpiMeta['deltas']['subscribers'] ?? null,
        'icon' => 'fa-users',
        'border' => 'border-red-500',
        'box' => 'bg-red-500/20 text-red-400',
    ],
    [
        'key' => 'views',
        'label' => 'Visualizações (fase nova)',
        // Soma dos vídeos sincronizados; o total do canal vindo da API atrasa e não conta os não listados.
        'value' => (int) ($phase_views ?? 0),
        // Curta para caber em uma linha no card; a hora da sincronização fica só no painel "Canal Conectado".
        'note' => 'Canal: ' . $fmt((int) ($account['view_count'] ?? 0)),
        'note_title' => 'Total do canal no YouTube (vem atrasado e não conta os não listados): ' . $fmt((int) ($account['view_count'] ?? 0)),
        'delta' => $kpiMeta['deltas']['views'] ?? null,
        'icon' => 'fa-eye',
        'border' => 'border-cyan-500',
        'box' => 'bg-cyan-500/20 text-cyan-400',
    ],
    [
        'key' => 'videos',
        'label' => 'Vídeos e Shorts',
        // Fase nova: sem os cancelados da grade antiga e sem os não listados.
        'value' => $activeVideos,
        'delta' => $kpiMeta['deltas']['videos'] ?? null,
        'icon' => 'fa-video',
        'border' => 'border-purple-500',
        'box' => 'bg-purple-500/20 text-purple-400',
    ],
    [
        'key' => 'engagement',
        'label' => 'Curtidas no período',
        'value' => (int) ($kpiMeta['aggregates']['likes'] ?? 0),
        'delta' => $kpiMeta['deltas']['engagement'] ?? null,
        'icon' => 'fa-heart',
        'border' => 'border-emerald-500',
        'box' => 'bg-emerald-500/20 text-emerald-400',
    ],
];
?>

<div id="yt-dashboard" class="w-full px-4 py-6 space-y-6" data-base-url="<?= $esc($baseUrl) ?>">
  <!-- Top bar / Header -->
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title flex items-center gap-3">
        <i class="fa-brands fa-youtube text-red-500" aria-hidden="true"></i>
        <span>YouTube Studio</span>
      </h1>
      <div class="admin-page-subtitle">Métricas analíticas, acervo sincronizado e publicação resiliente do canal.</div>
    </div>
    <div class="admin-page-actions flex items-center gap-2">
      <a href="<?= url('/admin/youtube/radar') ?>" class="admin-btn admin-btn-secondary flex items-center gap-2" title="Pesquisar tendências, temas em alta e transcrições completas">
        <i class="fa-solid fa-radar text-red-500"></i>
        <span>Radar de Conteúdo</span>
      </a>
      <?php if ($connected && $account !== null): ?>
        <a href="<?= url('/admin/youtube/posts/criar') ?>" class="admin-btn admin-btn-primary flex items-center gap-2">
          <i class="fa-solid fa-cloud-arrow-up"></i>
          <span>Novo vídeo</span>
        </a>
        <form method="post" action="<?= url('/admin/youtube/sincronizar') ?>" class="inline-block js-yt-sync">
          <input type="hidden" name="_csrf_token" value="<?= $esc($csrf_token) ?>">
          <button type="submit" class="admin-btn admin-btn-secondary flex items-center gap-2" title="Puxar novos vídeos e atualizar estatísticas">
            <i class="fa-solid fa-arrows-rotate"></i>
            <span>Sincronizar</span>
          </button>
        </form>
      <?php endif; ?>
      <?php if ($account !== null): ?>
        <a href="<?= $esc($channelUrl) ?>" target="_blank" rel="noopener noreferrer" class="admin-btn admin-btn-secondary flex items-center gap-2">
          <i class="fa-solid fa-arrow-up-right-from-square"></i>
          <span>Ver canal</span>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Avisos / Notificações -->
  <?php if (!empty($notice)): ?>
    <div class="admin-panel border border-emerald-500/40 bg-emerald-950/20 text-emerald-200 text-sm flex items-center gap-3 py-3 px-4 rounded-xl" role="status">
      <i class="fa-solid fa-circle-check text-emerald-400 text-lg"></i>
      <span><?= $esc($notice) ?></span>
    </div>
  <?php endif; ?>

  <!-- Avisos das ações sem recarregar (Sincronizar, erros de carregamento) -->
  <div id="yt-flash" hidden></div>

  <?php if (!empty($error)): ?>
    <div class="admin-panel border border-rose-500/40 bg-rose-950/20 text-rose-200 text-sm flex items-center gap-3 py-3 px-4 rounded-xl" role="alert">
      <i class="fa-solid fa-triangle-exclamation text-rose-400 text-lg"></i>
      <span><?= $esc($error) ?></span>
    </div>
  <?php endif; ?>

  <?php if ($account === null): ?>
    <!-- Estado Não Conectado -->
    <section class="admin-panel border border-slate-800 bg-slate-900/80 p-8 rounded-2xl text-center space-y-4">
      <div class="w-16 h-16 rounded-2xl bg-red-500/10 text-red-400 flex items-center justify-center mx-auto text-3xl">
        <i class="fa-brands fa-youtube"></i>
      </div>
      <h2 class="text-xl font-bold text-white">Conecte o canal do YouTube</h2>
      <p class="text-slate-400 max-w-lg mx-auto text-sm">
        Conecte o canal do YouTube via OAuth para sincronizar automaticamente seus vídeos, Shorts, métricas de visualização e permitir agendamentos diretos pelo painel.
      </p>
      <?php if ($oauth_configured): ?>
        <form method="post" action="<?= url('/admin/youtube/conectar') ?>" class="pt-2">
          <input type="hidden" name="_csrf_token" value="<?= $esc($csrf_token) ?>">
          <button type="submit" class="admin-btn admin-btn-primary px-6 py-2.5 text-sm font-semibold inline-flex items-center gap-2">
            <i class="fa-brands fa-google"></i>
            <span>Conectar canal via Google OAuth</span>
          </button>
        </form>
      <?php else: ?>
        <div class="p-4 bg-amber-500/10 border border-amber-500/30 rounded-xl max-w-md mx-auto text-xs text-amber-200">
          Credenciais OAuth não encontradas no <code>.env</code> local. Configure <code>YOUTUBE_CLIENT_ID</code>, <code>YOUTUBE_CLIENT_SECRET</code> e <code>YOUTUBE_REDIRECT_URI</code>.
        </div>
      <?php endif; ?>
    </section>
  <?php else: ?>

    <!-- Seletor de Período (7d, 14d, 30d, custom) -->
    <section class="admin-panel !p-3 flex flex-wrap items-center justify-between gap-3 bg-slate-900/90 border border-slate-800 rounded-xl">
      <div class="flex items-center gap-1.5 flex-wrap">
        <span class="text-xs font-semibold text-slate-400 mr-2 flex items-center gap-1.5">
          <i class="fa-regular fa-calendar"></i> Período:
        </span>
        <?php foreach (['7d' => '7 dias', '14d' => '14 dias', '30d' => '30 dias'] as $pKey => $pLabel): ?>
          <a
            href="<?= $esc($buildUrl(['period' => $pKey, 'page' => 1])) ?>"
            data-yt-period="<?= $pKey ?>"
            class="px-3 py-1.5 rounded-lg text-xs font-bold transition <?= $period === $pKey ? $periodOn : $periodOff ?>"
          >
            <?= $pLabel ?>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Filtro Personalizado com Datas -->
      <form id="yt-period-form" method="get" action="<?= $baseUrl ?>" class="flex items-center gap-2 flex-wrap">
        <input type="hidden" name="period" value="custom">
        <input type="hidden" name="busca" value="<?= $esc($busca) ?>">
        <input type="hidden" name="tipo" value="<?= $esc($tipo) ?>">
        <input type="hidden" name="status" value="<?= $esc($status) ?>">
        <input type="hidden" name="origem" value="<?= $esc($origem) ?>">
        <input type="hidden" name="privacidade" value="<?= $esc($privacidade) ?>">
        <input type="hidden" name="sort" value="<?= $esc($sort) ?>">
        <input type="hidden" name="dir" value="<?= $esc($dir) ?>">
        <input type="hidden" name="view" value="<?= $esc($viewMode) ?>">

        <div class="flex items-center gap-1.5 bg-slate-800/80 border border-slate-700/70 rounded-lg px-2 py-1 text-xs">
          <label class="text-[10px] text-slate-400 font-semibold uppercase">De</label>
          <input type="date" name="start" value="<?= $esc($start) ?>" class="bg-transparent text-slate-200 text-xs focus:outline-none border-0 p-0">
          <span class="text-slate-500">→</span>
          <label class="text-[10px] text-slate-400 font-semibold uppercase">Até</label>
          <input type="date" name="end" value="<?= $esc($end) ?>" class="bg-transparent text-slate-200 text-xs focus:outline-none border-0 p-0">
        </div>
        <button type="submit" class="admin-btn admin-btn-secondary !py-1 !px-2.5 text-xs font-semibold">
          Aplicar
        </button>
      </form>
    </section>

    <!-- 4 KPI Cards com Variações -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <?php foreach ($kpiCards as $card): ?>
        <div class="admin-panel !p-4 flex items-center justify-between border-l-4 <?= $card['border'] ?> bg-slate-900/90 shadow-lg rounded-xl">
          <div class="flex items-center gap-3.5 min-w-0">
            <div class="h-11 w-11 rounded-xl <?= $card['box'] ?> flex items-center justify-center text-lg shrink-0">
              <i class="fa-solid <?= $card['icon'] ?>" aria-hidden="true"></i>
            </div>
            <div class="min-w-0">
              <div class="text-xl font-black text-white" data-yt-kpi-value="<?= $card['key'] ?>"><?= $card['value'] !== null ? $fmt((int) $card['value']) : '—' ?></div>
              <div class="text-xs text-slate-400 font-medium"><?= $esc($card['label']) ?></div>
              <?php if (!empty($card['note'])): ?>
                <div class="text-[10px] text-slate-500 truncate" data-yt-kpi-note="<?= $card['key'] ?>" title="<?= $esc((string) ($card['note_title'] ?? $card['note'])) ?>"><?= $esc((string) $card['note']) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <div class="flex flex-col items-end gap-1 shrink-0" data-yt-kpi-delta="<?= $card['key'] ?>">
            <?php if ($card['delta'] !== null): ?>
              <?php $d = (int) $card['delta']; ?>
              <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold <?= $d > 0 ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : ($d < 0 ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : 'bg-slate-800 text-slate-400 border border-slate-700') ?>">
                <?= $d > 0 ? '+' . $fmt($d) : ($d < 0 ? $fmt($d) : '= 0') ?>
              </span>
            <?php else: ?>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-800/80 text-slate-400 border border-slate-700/60">—</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </section>

    <!-- Seção Central Dividida: Informações do Canal (60%) e Ações Rápidas (40%) -->
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <!-- Coluna Esquerda: Informações do Canal -->
      <div class="admin-panel lg:col-span-7 flex flex-col justify-between rounded-xl">
        <div>
          <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-800">
            <h3 class="font-orbitron text-base font-bold text-white flex items-center gap-2">
              <i class="fa-solid fa-tower-broadcast text-red-500" aria-hidden="true"></i>
              <span>Canal Conectado</span>
            </h3>
            <div class="flex items-center gap-2">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">
                <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Conectado</span>
              </span>
              <span class="text-xs text-slate-400 font-mono">ID: <?= $esc($account['channel_id'] ?? '') ?></span>
            </div>
          </div>

          <div class="flex items-start gap-4 mt-4">
            <?php
            $localAvatar = '/assets/youtube/channel_avatar.jpg';
            $avatarUrl = is_file(base_path('public' . $localAvatar))
                ? url($localAvatar . '?v=' . substr(md5_file(base_path('public' . $localAvatar)) ?: '1', 0, 8))
                : (!empty($account['thumbnail_url']) ? $account['thumbnail_url'] : null);
            ?>
            <?php if ($avatarUrl): ?>
              <img src="<?= $esc($avatarUrl) ?>" alt="Avatar" referrerpolicy="no-referrer" class="w-16 h-16 rounded-full border-2 border-red-500/40 object-cover shrink-0">
            <?php else: ?>
              <div class="w-16 h-16 rounded-full bg-slate-800 border-2 border-slate-700 flex items-center justify-center text-red-400 text-2xl shrink-0">
                <i class="fa-brands fa-youtube"></i>
              </div>
            <?php endif; ?>
            <div class="min-w-0 flex-1">
              <h4 class="text-lg font-bold text-white truncate"><?= $esc($account['title'] ?? 'Canal YouTube') ?></h4>
              <p class="text-xs text-cyan-300 font-mono"><?= $esc($account['handle'] ?? $account['custom_url'] ?? '') ?></p>
              <?php if (!empty($account['description'])): ?>
                <p class="text-xs text-slate-300 mt-2 leading-relaxed whitespace-pre-line"><?= $esc($account['description']) ?></p>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Inscritos, visualizações e vídeos ficam só nos cards de cima. -->
        <div class="pt-4 mt-4 border-t border-slate-800/80 flex flex-wrap items-center justify-end gap-3 text-xs text-slate-400">
          <div>
            <span>Sincronizado em: <strong id="yt-channel-synced" class="text-slate-300"><?= $formatDate($account['synced_at'] ?? null) ?></strong></span>
          </div>
        </div>
      </div>

      <!-- Coluna Direita: Ações Rápidas & Fila -->
      <div class="admin-panel lg:col-span-5 flex flex-col justify-between rounded-xl">
        <div>
          <div class="flex items-center justify-between pb-4 border-b border-slate-800">
            <h3 class="font-orbitron text-base font-bold text-white flex items-center gap-2">
              <i class="fa-solid fa-bolt text-amber-400" aria-hidden="true"></i>
              <span>Ações Rápidas</span>
            </h3>
            <span class="text-xs text-slate-400">Fila e Publicação</span>
          </div>

          <div class="grid grid-cols-2 gap-3 mt-4">
            <a href="<?= url('/admin/youtube/posts/criar') ?>" class="p-3 rounded-xl bg-slate-800/70 hover:bg-slate-800 border border-slate-700/60 transition group flex flex-col items-start gap-1">
              <i class="fa-solid fa-circle-plus text-cyan-400 text-lg group-hover:scale-110 transition-transform"></i>
              <span class="text-xs font-bold text-white mt-1">Upload MP4</span>
              <span class="text-[11px] text-slate-400">Agendar publicação</span>
            </a>

            <form method="post" action="<?= url('/admin/youtube/sincronizar') ?>" class="block js-yt-sync">
              <input type="hidden" name="_csrf_token" value="<?= $esc($csrf_token) ?>">
              <button type="submit" class="w-full text-left p-3 rounded-xl bg-slate-800/70 hover:bg-slate-800 border border-slate-700/60 transition group flex flex-col items-start gap-1">
                <i class="fa-solid fa-arrows-rotate text-emerald-400 text-lg group-hover:rotate-180 transition-transform"></i>
                <span class="text-xs font-bold text-white mt-1">Sincronizar Acervo</span>
                <span class="text-[11px] text-slate-400">Buscar novos vídeos</span>
              </button>
            </form>

            <a href="<?= $esc($channelUrl) ?>" target="_blank" rel="noopener noreferrer" class="p-3 rounded-xl bg-slate-800/70 hover:bg-slate-800 border border-slate-700/60 transition group flex flex-col items-start gap-1">
              <i class="fa-brands fa-youtube text-red-500 text-lg group-hover:scale-110 transition-transform"></i>
              <span class="text-xs font-bold text-white mt-1">Canal Oficial</span>
              <span class="text-[11px] text-slate-400">Abrir no YouTube</span>
            </a>

            <a href="https://studio.youtube.com" target="_blank" rel="noopener noreferrer" class="p-3 rounded-xl bg-slate-800/70 hover:bg-slate-800 border border-slate-700/60 transition group flex flex-col items-start gap-1">
              <i class="fa-solid fa-sliders text-purple-400 text-lg group-hover:scale-110 transition-transform"></i>
              <span class="text-xs font-bold text-white mt-1">YouTube Studio</span>
              <span class="text-[11px] text-slate-400">Painel do criador</span>
            </a>
          </div>
        </div>

        <div class="pt-4 mt-4 border-t border-slate-800/80 text-xs text-slate-400 flex items-center justify-end">
          <span class="text-emerald-400 font-semibold flex items-center gap-1">
            <i class="fa-solid fa-shield-halved"></i> Upload Resumível Ativo
          </span>
        </div>
      </div>
    </section>

    <!-- Navegação por Abas: Métricas vs Vídeos & Shorts -->
    <div class="flex items-center gap-2 border-b border-slate-800 pt-2">
      <button
        type="button"
        id="btn-tab-metricas"
        onclick="switchYtTab('metricas')"
        class="px-4 py-2.5 rounded-t-xl text-xs font-bold transition flex items-center gap-2 border-b-2 bg-slate-800/80 text-cyan-300 border-cyan-400"
      >
        <i class="fa-solid fa-chart-line"></i>
        <span>Métricas e Desempenho</span>
      </button>

      <button
        type="button"
        id="btn-tab-videos"
        onclick="switchYtTab('videos')"
        class="px-4 py-2.5 rounded-t-xl text-xs font-bold transition flex items-center gap-2 border-b-2 border-transparent text-slate-400 hover:text-white"
      >
        <i class="fa-solid fa-play"></i>
        <span>Vídeos e Shorts</span>      </button>
    </div>

    <!-- Aba 1: Métricas e Desempenho -->
    <div id="tab-content-metricas" class="space-y-6">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Gráfico de Desempenho Diário (8 colunas) -->
        <div class="admin-panel lg:col-span-8 flex flex-col justify-between rounded-xl">
          <div>
            <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-800">
              <div>
                <h4 class="font-orbitron text-base font-bold text-white flex items-center gap-2">
                  <i class="fa-solid fa-chart-simple text-cyan-400"></i>
                  <span>Evolução no Período (<span id="yt-chart-range"><?= $esc($start) ?> a <?= $esc($end) ?></span>)</span>
                </h4>
                <p class="text-xs text-slate-400 mt-0.5">Visualizações acumuladas de cada vídeo, no dia da publicação.</p>
              </div>
              <div class="flex items-center gap-4 text-xs font-semibold">
                <span class="inline-flex items-center gap-1.5 text-cyan-400">
                  <span class="h-2.5 w-2.5 rounded-full bg-cyan-400"></span> Visualizações
                </span>
                <span class="inline-flex items-center gap-1.5 text-pink-400">
                  <span class="h-2.5 w-2.5 rounded-full bg-pink-400"></span> Curtidas
                </span>
              </div>
            </div>

            <!-- Gráfico de barras: as cores ficam no <style> abaixo, porque o Tailwind compilado não tem gradiente para cima. -->
            <div class="mt-6">
              <?php
              $chartPoints = $dailyPerformance;
              $chartMax = 0;
              foreach ($chartPoints as $cp) {
                  $chartMax = max($chartMax, (int) $cp['views'], (int) $cp['likes']);
              }
              // Uma data a cada N dias (a última sempre aparece), para caber no quadro.
              $labelStep = max(1, (int) ceil(count($chartPoints) / 7));
              $lastIndex = count($chartPoints) - 1;
              $barH = static fn (int $v): int => $v > 0 && $chartMax > 0 ? max(2, (int) round($v / $chartMax * 160)) : 0;
              ?>
              <div id="yt-chart" class="yt-chart" role="img" aria-label="Visualizações e curtidas por dia de publicação">
                <?php foreach ($chartPoints as $i => $cp): ?>
                  <?php $day = new DateTimeImmutable((string) $cp['date']); ?>
                  <div class="yt-chart-col" title="<?= $esc($day->format('d/m/Y') . ': ' . $fmt((int) $cp['views']) . ' visualizações, ' . $fmt((int) $cp['likes']) . ' curtidas') ?>">
                    <div class="yt-chart-bars">
                      <div class="yt-chart-bar yt-chart-bar-views" style="height: <?= $barH((int) $cp['views']) ?>px;"></div>
                      <div class="yt-chart-bar yt-chart-bar-likes" style="height: <?= $barH((int) $cp['likes']) ?>px;"></div>
                    </div>
                    <span class="yt-chart-label"><?= ($lastIndex - $i) % $labelStep === 0 ? $day->format('d/m') : '' ?></span>
                  </div>
                <?php endforeach; ?>
                <?php if ($chartMax === 0): ?>
                  <div class="yt-chart-empty">Nenhum vídeo publicado neste período.</div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="pt-3 mt-4 border-t border-slate-800 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-400">
            <span>Total de views no filtro: <strong id="yt-total-views" class="text-white"><?= $fmt((int) ($kpiMeta['aggregates']['views'] ?? 0)) ?></strong></span>
            <span>Total de curtidas no filtro: <strong id="yt-total-likes" class="text-white"><?= $fmt((int) ($kpiMeta['aggregates']['likes'] ?? 0)) ?></strong></span>
          </div>
        </div>

        <!-- Distribuição Vídeos vs Shorts (4 colunas) -->
        <div class="admin-panel lg:col-span-4 flex flex-col justify-between rounded-xl">
          <div>
            <div class="pb-3 border-b border-slate-800">
              <h4 class="font-orbitron text-base font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-chart-pie text-purple-400"></i>
                <span>Distribuição de Conteúdo</span>
              </h4>
              <p class="text-xs text-slate-400 mt-0.5">Proporção entre vídeos longos e Shorts publicados.</p>
            </div>

            <?php
            $shortsCount = (int) ($contentTypes['items']['short']['count'] ?? 0);
            $videosCount = (int) ($contentTypes['items']['video']['count'] ?? 0);
            $totalDist = max(1, $shortsCount + $videosCount);
            $shortsPct = round(($shortsCount / $totalDist) * 100, 1);
            $videosPct = round(($videosCount / $totalDist) * 100, 1);
            ?>

            <div class="mt-6 space-y-5">
              <!-- Item Shorts -->
              <div>
                <div class="flex items-center justify-between text-xs mb-1.5 font-semibold">
                  <span class="text-purple-300 flex items-center gap-1.5">
                    <i class="fa-solid fa-mobile-screen-button"></i> Shorts (até 3 min)
                  </span>
                  <span id="yt-dist-short" class="text-white font-mono"><?= $shortsCount ?> (<?= $shortsPct ?>%)</span>
                </div>
                <div class="h-2.5 w-full bg-slate-800 rounded-full overflow-hidden">
                  <div id="yt-dist-short-bar" class="h-full bg-gradient-to-r from-purple-600 to-purple-400 rounded-full" style="width: <?= $shortsPct ?>%;"></div>
                </div>
              </div>

              <!-- Item Vídeos Longos -->
              <div>
                <div class="flex items-center justify-between text-xs mb-1.5 font-semibold">
                  <span class="text-cyan-300 flex items-center gap-1.5">
                    <i class="fa-solid fa-film"></i> Vídeos Longos (> 3 min)
                  </span>
                  <span id="yt-dist-video" class="text-white font-mono"><?= $videosCount ?> (<?= $videosPct ?>%)</span>
                </div>
                <div class="h-2.5 w-full bg-slate-800 rounded-full overflow-hidden">
                  <div id="yt-dist-video-bar" class="h-full bg-gradient-to-r from-cyan-600 to-cyan-400 rounded-full" style="width: <?= $videosPct ?>%;"></div>
                </div>
              </div>

              <!-- Cartão de resumo -->
              <div class="p-4 rounded-xl bg-slate-800/40 border border-slate-700/60 mt-6 space-y-2">
                <div class="text-xs font-bold text-white flex items-center gap-2">
                  <i class="fa-solid fa-circle-info text-cyan-400"></i>
                  <span>Formatos no Canal</span>
                </div>
                <p class="text-[11px] text-slate-300 leading-relaxed">
                  O canal foca majoritariamente em <strong>Shorts verticais de alta retenção</strong> com o Echo e curiosidades tech, com vídeos longos dedicados para análises profundas.
                </p>
              </div>
            </div>
          </div>

          <div class="pt-3 mt-4 border-t border-slate-800 text-xs text-slate-400 flex items-center justify-between">
            <span>Acervo classificado: <strong id="yt-dist-total" class="text-white"><?= $shortsCount + $videosCount ?></strong></span>
            <span id="yt-dist-vertical" class="text-purple-300 font-semibold"><?= $shortsPct ?>% vertical</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Aba 2: Vídeos e Shorts -->
    <div id="tab-content-videos" class="space-y-6 hidden">
      <!-- Barra de Filtros e Busca -->
      <section class="admin-panel !p-4 bg-slate-900/90 border border-slate-800 rounded-xl space-y-4">
        <div class="flex items-center justify-between text-xs text-slate-400 pb-2 border-b border-slate-800">
          <div class="font-bold text-slate-300">
            <i class="fa-solid fa-filter text-cyan-400 mr-1.5"></i> Filtros e Pesquisa
          </div>
          <div>
            Página atual <span id="yt-page-current" class="text-cyan-300 font-bold"><?= $page ?></span> — <span id="yt-page-size" class="text-slate-200 font-bold"><?= $perPage >= 9999 ? 'Todos' : $perPage ?></span> por página
          </div>
        </div>
        <form id="yt-filter-form" method="get" action="<?= $baseUrl ?>" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
          <input type="hidden" name="period" value="<?= $esc($period) ?>">
          <input type="hidden" name="start" value="<?= $esc($start) ?>">
          <input type="hidden" name="end" value="<?= $esc($end) ?>">
          <input type="hidden" name="view" value="<?= $esc($viewMode) ?>">
          <input type="hidden" name="per_page" value="<?= $perPage >= 9999 ? 'todos' : $perPage ?>">

          <!-- Busca -->
          <div class="lg:col-span-2">
            <label class="block text-xs font-semibold text-slate-400 mb-1">Buscar por título ou descrição</label>
            <div class="relative">
              <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-500 text-xs"></i>
              <input
                type="text"
                name="busca"
                value="<?= $esc($busca) ?>"
                placeholder="Ex: Predador, Echo, setup..."
                class="w-full bg-slate-800 border border-slate-700 rounded-lg pl-8 pr-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-400"
              >
            </div>
          </div>

          <!-- Tipo -->
          <div class="lg:col-span-2">
            <label class="block text-xs font-semibold text-slate-400 mb-1">Formato</label>
            <select name="tipo" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-cyan-400">
              <option value="">Todos os formatos</option>
              <option value="short" <?= $tipo === 'short' ? 'selected' : '' ?>>Shorts (≤ 3 min)</option>
              <option value="video" <?= $tipo === 'video' ? 'selected' : '' ?>>Vídeos Longos</option>
            </select>
          </div>

          <!-- Status -->
          <div class="lg:col-span-2">
            <label class="block text-xs font-semibold text-slate-400 mb-1">Status</label>
            <select name="status" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-cyan-400">
              <option value="">Todos os status</option>
              <option value="publicado" <?= $status === 'publicado' ? 'selected' : '' ?>>Publicado</option>
              <option value="agendado" <?= $status === 'agendado' ? 'selected' : '' ?>>Agendado (fila ou no YouTube)</option>
              <option value="rascunho" <?= $status === 'rascunho' ? 'selected' : '' ?>>Rascunho</option>
              <option value="erro" <?= $status === 'erro' ? 'selected' : '' ?>>Erro</option>
              <option value="cancelado" <?= $status === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
            </select>
          </div>

          <!-- Privacidade: os vídeos antigos ficaram não listados em 31/10/2026 -->
          <div class="lg:col-span-2">
            <label class="block text-xs font-semibold text-slate-400 mb-1">Privacidade</label>
            <select name="privacidade" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-cyan-400">
              <option value="">Fase nova (sem não listados)</option>
              <option value="unlisted" <?= $privacidade === 'unlisted' ? 'selected' : '' ?>>Só não listados (antigos)</option>
              <option value="todos" <?= $privacidade === 'todos' ? 'selected' : '' ?>>Todos</option>
            </select>
          </div>

          <!-- Ordenação -->
          <div class="lg:col-span-2">
            <label class="block text-xs font-semibold text-slate-400 mb-1">Ordenar por</label>
            <select name="sort" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:outline-none focus:border-cyan-400">
              <option value="" <?= $sort === '' ? 'selected' : '' ?>>Publicados, agendados, resto</option>
              <option value="published_at" <?= $sort === 'published_at' ? 'selected' : '' ?>>Mais recentes</option>
              <option value="views_count" <?= $sort === 'views_count' ? 'selected' : '' ?>>Mais vistos</option>
              <option value="likes_count" <?= $sort === 'likes_count' ? 'selected' : '' ?>>Mais curtidos</option>
              <option value="duration_seconds" <?= $sort === 'duration_seconds' ? 'selected' : '' ?>>Duração</option>
            </select>
          </div>

          <!-- Botões Ação -->
          <div class="lg:col-span-2 flex items-center gap-2">
            <button type="submit" class="admin-btn admin-btn-primary !py-1.5 flex-1 text-xs justify-center font-bold">
              Filtrar
            </button>
            <a href="<?= $baseUrl ?>" data-yt-clear class="admin-btn admin-btn-secondary !py-1.5 text-xs" title="Limpar filtros">
              <i class="fa-solid fa-xmark"></i>
            </a>
          </div>
        </form>
      </section>

      <!-- Tabela / Lista de Vídeos (trocada por ?ajax=videos sem recarregar a página) -->
      <div id="yt-video-list" class="space-y-6" aria-live="polite">
        <?php require __DIR__ . '/_lista-videos.php'; ?>
      </div>
    </div>

  <?php endif; ?>
</div>

<script>
function switchYtTab(tab) {
  const tabMetricas = document.getElementById('tab-content-metricas');
  const tabVideos = document.getElementById('tab-content-videos');
  const btnMetricas = document.getElementById('btn-tab-metricas');
  const btnVideos = document.getElementById('btn-tab-videos');

  if (tab === 'metricas') {
    tabMetricas.classList.remove('hidden');
    tabVideos.classList.add('hidden');
    btnMetricas.className = 'px-4 py-2.5 rounded-t-xl text-xs font-bold transition flex items-center gap-2 border-b-2 bg-slate-800/80 text-cyan-300 border-cyan-400';
    btnVideos.className = 'px-4 py-2.5 rounded-t-xl text-xs font-bold transition flex items-center gap-2 border-b-2 border-transparent text-slate-400 hover:text-white';
  } else {
    tabMetricas.classList.add('hidden');
    tabVideos.classList.remove('hidden');
    btnVideos.className = 'px-4 py-2.5 rounded-t-xl text-xs font-bold transition flex items-center gap-2 border-b-2 bg-slate-800/80 text-cyan-300 border-cyan-400';
    btnMetricas.className = 'px-4 py-2.5 rounded-t-xl text-xs font-bold transition flex items-center gap-2 border-b-2 border-transparent text-slate-400 hover:text-white';
  }
  if (window.ytRememberTab) window.ytRememberTab(tab);
}

// Se a URL tiver a aba, busca ou filtro, abre direto na aba de vídeos
(function () {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('tab') === 'videos' || urlParams.has('busca') || urlParams.has('tipo') || urlParams.has('status') || urlParams.has('page') || urlParams.has('view') || urlParams.has('per_page') || urlParams.has('sort') || urlParams.has('dir')) {
    switchYtTab('videos');
  }
})();

// Atualização sem recarregar: período/datas por ?ajax=metrics; filtros, ordem, paginação e Planilha/Cards por ?ajax=videos.
// Sem JavaScript os links e formulários continuam recarregando a página como antes.
(function () {
  var root = document.getElementById('yt-dashboard');
  var list = document.getElementById('yt-video-list');
  if (!root || !list || !window.fetch || !window.URLSearchParams) return;

  var baseUrl = root.getAttribute('data-base-url');
  var basePath = new URL(baseUrl, window.location.href).pathname;
  var state = new URLSearchParams(window.location.search);
  var defaults = { period: '7d', dir: 'desc', page: '1', per_page: '8', view: 'table', tab: 'metricas' };
  var listKeys = ['busca', 'tipo', 'status', 'origem', 'privacidade', 'sort', 'dir', 'page', 'per_page', 'view'];
  var periodOn = <?= json_encode($periodOn) ?>;
  var periodOff = <?= json_encode($periodOff) ?>;
  var numberFmt = new Intl.NumberFormat('pt-BR');

  // Mesma limpeza do $buildUrl do PHP: some o que é padrão ou vazio.
  function syncUrl() {
    var clean = new URLSearchParams();
    state.forEach(function (value, key) {
      if (key === 'ajax' || value === '' || defaults[key] === value) return;
      if ((key === 'start' || key === 'end') && state.get('period') !== 'custom') return;
      clean.set(key, value);
    });
    var qs = clean.toString();
    window.history.replaceState(null, '', baseUrl + (qs ? '?' + qs : ''));
  }

  window.ytRememberTab = function (tab) {
    state.set('tab', tab);
    syncUrl();
  };

  function flash(message, ok) {
    var box = document.getElementById('yt-flash');
    if (!box) return;
    var div = document.createElement('div');
    div.className = ok
      ? 'admin-panel border border-emerald-500/40 bg-emerald-950/20 text-emerald-200 text-sm flex items-center gap-3 py-3 px-4 rounded-xl'
      : 'admin-panel border border-rose-500/40 bg-rose-950/20 text-rose-200 text-sm flex items-center gap-3 py-3 px-4 rounded-xl';
    div.setAttribute('role', ok ? 'status' : 'alert');
    var icon = document.createElement('i');
    icon.className = ok ? 'fa-solid fa-circle-check text-emerald-400 text-lg' : 'fa-solid fa-triangle-exclamation text-rose-400 text-lg';
    var text = document.createElement('span');
    text.textContent = message;
    div.appendChild(icon);
    div.appendChild(text);
    box.replaceChildren(div);
    box.hidden = false;
  }

  function busy(el, on) {
    if (!el) return;
    el.setAttribute('aria-busy', on ? 'true' : 'false');
    el.style.opacity = on ? '0.55' : '';
    el.style.transition = 'opacity .15s';
  }

  function getJson(url, options) {
    return fetch(url, Object.assign({ credentials: 'same-origin', headers: { Accept: 'application/json' } }, options || {}))
      .then(function (res) {
        return res.text().then(function (body) {
          var data = null;
          try { data = JSON.parse(body); } catch (e) { data = null; }
          if (!data) throw new Error(res.ok ? 'Resposta inesperada do servidor.' : (body.trim().slice(0, 160) || ('Erro HTTP ' + res.status)));
          if (!res.ok || data.ok === false) throw new Error(data.message || ('Erro HTTP ' + res.status));
          return data;
        });
      });
  }

  // ---------- Métricas ----------
  function deltaHtml(d) {
    if (d === null || d === undefined) {
      return '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-800/80 text-slate-400 border border-slate-700/60">—</span>';
    }
    var cls = d > 0 ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'
      : (d < 0 ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : 'bg-slate-800 text-slate-400 border border-slate-700');
    var label = d > 0 ? '+' + numberFmt.format(d) : (d < 0 ? numberFmt.format(d) : '= 0');
    return '<span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-bold ' + cls + '">' + label + '</span>';
  }

  function setText(id, value) {
    var el = document.getElementById(id);
    if (el) el.textContent = value;
  }

  function pad(n) { return (n < 10 ? '0' : '') + n; }

  // Mesmo desenho do PHP: escala única, uma data a cada N dias, a última sempre visível.
  function renderChart(daily) {
    var chart = document.getElementById('yt-chart');
    if (!chart) return;
    var max = 0;
    daily.forEach(function (p) { max = Math.max(max, p.views || 0, p.likes || 0); });
    var step = Math.max(1, Math.ceil(daily.length / 7));
    var last = daily.length - 1;
    var barH = function (v) { return v > 0 && max > 0 ? Math.max(2, Math.round(v / max * 160)) : 0; };
    chart.replaceChildren();
    daily.forEach(function (p, i) {
      var parts = String(p.date).split('-');
      var col = document.createElement('div');
      col.className = 'yt-chart-col';
      col.title = parts[2] + '/' + parts[1] + '/' + parts[0] + ': ' + numberFmt.format(p.views || 0) + ' visualizações, ' + numberFmt.format(p.likes || 0) + ' curtidas';
      var bars = document.createElement('div');
      bars.className = 'yt-chart-bars';
      [['views', p.views], ['likes', p.likes]].forEach(function (b) {
        var bar = document.createElement('div');
        bar.className = 'yt-chart-bar yt-chart-bar-' + b[0];
        bar.style.height = barH(b[1] || 0) + 'px';
        bars.appendChild(bar);
      });
      var label = document.createElement('span');
      label.className = 'yt-chart-label';
      label.textContent = (last - i) % step === 0 ? parts[2] + '/' + parts[1] : '';
      col.appendChild(bars);
      col.appendChild(label);
      chart.appendChild(col);
    });
    if (max === 0) {
      var empty = document.createElement('div');
      empty.className = 'yt-chart-empty';
      empty.textContent = 'Nenhum vídeo publicado neste período.';
      chart.appendChild(empty);
    }
  }

  function applyMetrics(data) {
    state.set('period', data.period);
    state.set('start', data.start);
    state.set('end', data.end);

    document.querySelectorAll('[data-yt-period]').forEach(function (a) {
      var key = a.getAttribute('data-yt-period');
      a.className = 'px-3 py-1.5 rounded-lg text-xs font-bold transition ' + (key === data.period ? periodOn : periodOff);
      var href = new URL(a.href, window.location.href);
      href.searchParams.set('period', key);
      a.href = href.toString();
    });
    var periodForm = document.getElementById('yt-period-form');
    if (periodForm) {
      periodForm.elements.start.value = data.start;
      periodForm.elements.end.value = data.end;
    }
    var filterForm = document.getElementById('yt-filter-form');
    if (filterForm) {
      filterForm.elements.period.value = data.period;
      filterForm.elements.start.value = data.start;
      filterForm.elements.end.value = data.end;
    }

    var k = data.kpis || {};
    var deltas = k.deltas || {};
    var agg = k.aggregates || {};
    ['subscribers', 'views', 'videos', 'engagement'].forEach(function (key) {
      var box = root.querySelector('[data-yt-kpi-delta="' + key + '"]');
      if (box) box.innerHTML = deltaHtml(deltas[key]);
    });
    var setKpi = function (key, value) {
      var el = root.querySelector('[data-yt-kpi-value="' + key + '"]');
      if (el) el.textContent = numberFmt.format(value || 0);
    };
    setKpi('engagement', agg.likes);

    var ch = data.channel;
    if (ch) {
      setKpi('subscribers', ch.subscribers);
      setKpi('views', ch.phase_views);
      setKpi('videos', ch.active_videos);
      if (ch.synced_at) setText('yt-channel-synced', ch.synced_at);
      var note = root.querySelector('[data-yt-kpi-note="views"]');
      if (note) {
        note.textContent = 'Canal: ' + numberFmt.format(ch.view_count);
        note.title = 'Total do canal no YouTube (vem atrasado e não conta os não listados): ' + numberFmt.format(ch.view_count);
      }
    }

    setText('yt-chart-range', data.start + ' a ' + data.end);
    setText('yt-total-views', numberFmt.format(agg.views || 0));
    setText('yt-total-likes', numberFmt.format(agg.likes || 0));
    renderChart(data.daily || []);

    var dist = data.distribution || { items: {} };
    var shorts = (dist.items.short || {}).count || 0;
    var videos = (dist.items.video || {}).count || 0;
    var total = Math.max(1, shorts + videos);
    var pct = function (n) { return Math.round(n / total * 1000) / 10; };
    setText('yt-dist-short', shorts + ' (' + pct(shorts) + '%)');
    setText('yt-dist-video', videos + ' (' + pct(videos) + '%)');
    var sb = document.getElementById('yt-dist-short-bar');
    if (sb) sb.style.width = pct(shorts) + '%';
    var vb = document.getElementById('yt-dist-video-bar');
    if (vb) vb.style.width = pct(videos) + '%';
    setText('yt-dist-total', String(shorts + videos));
    setText('yt-dist-vertical', pct(shorts) + '% vertical');
  }

  function loadMetrics(period, start, end) {
    var q = new URLSearchParams({ ajax: 'metrics', period: period });
    if (period === 'custom') {
      q.set('start', start || '');
      q.set('end', end || '');
    }
    var area = document.getElementById('tab-content-metricas');
    busy(area, true);
    return getJson(baseUrl + '?' + q.toString())
      .then(function (data) { applyMetrics(data); syncUrl(); })
      .catch(function (err) { flash('Não foi possível atualizar as métricas: ' + err.message, false); })
      .finally(function () { busy(area, false); });
  }

  document.querySelectorAll('[data-yt-period]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
      e.preventDefault();
      loadMetrics(a.getAttribute('data-yt-period'));
    });
  });

  var periodForm = document.getElementById('yt-period-form');
  if (periodForm) {
    periodForm.addEventListener('submit', function (e) {
      e.preventDefault();
      loadMetrics('custom', periodForm.elements.start.value, periodForm.elements.end.value);
    });
  }

  // ---------- Lista ----------
  function loadList(params) {
    var q = new URLSearchParams();
    ['period', 'start', 'end'].concat(listKeys).forEach(function (key) {
      if (params.get(key)) q.set(key, params.get(key));
    });
    q.set('ajax', 'videos');
    busy(list, true);
    return getJson(baseUrl + '?' + q.toString())
      .then(function (data) {
        list.innerHTML = data.html;
        listKeys.forEach(function (key) {
          if (params.has(key)) state.set(key, params.get(key)); else state.delete(key);
        });
        // A página pode ter sido ajustada pelo servidor (ex.: além da última).
        state.set('page', String(data.page));
        state.set('tab', 'videos');
        setText('yt-page-current', String(data.page));
        setText('yt-page-size', data.per_page >= 9999 ? 'Todos' : String(data.per_page));
        var filterForm = document.getElementById('yt-filter-form');
        if (filterForm) filterForm.elements.per_page.value = data.per_page >= 9999 ? 'todos' : String(data.per_page);
        if (filterForm) filterForm.elements.view.value = state.get('view') || 'table';
        syncUrl();
      })
      .catch(function (err) { flash('Não foi possível atualizar a lista: ' + err.message, false); })
      .finally(function () { busy(list, false); });
  }

  function clearFilters() {
    var p = new URLSearchParams(state);
    ['busca', 'tipo', 'status', 'origem', 'privacidade', 'sort', 'dir', 'page'].forEach(function (key) { p.delete(key); });
    var form = document.getElementById('yt-filter-form');
    if (form) {
      ['busca', 'tipo', 'status', 'privacidade', 'sort'].forEach(function (name) {
        if (form.elements[name]) form.elements[name].value = '';
      });
    }
    return loadList(p);
  }

  // Links da lista (ordenar, paginar, por página, Planilha/Cards): mesmos parâmetros, sem recarregar.
  list.addEventListener('click', function (e) {
    var a = e.target.closest('a[href]');
    if (!a || a.target === '_blank' || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
    if (a.hasAttribute('data-yt-clear')) {
      e.preventDefault();
      clearFilters();
      return;
    }
    var url = new URL(a.href, window.location.href);
    if (url.pathname !== basePath) return;
    e.preventDefault();
    if (a.classList.contains('is-disabled')) return;
    var p = new URLSearchParams(url.search);
    ['period', 'start', 'end'].forEach(function (key) {
      if (state.get(key)) p.set(key, state.get(key)); else p.delete(key);
    });
    loadList(p);
  });

  var filterForm = document.getElementById('yt-filter-form');
  if (filterForm) {
    filterForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var p = new URLSearchParams(state);
      new FormData(filterForm).forEach(function (value, key) {
        if (listKeys.indexOf(key) !== -1) p.set(key, String(value));
      });
      p.delete('page');
      p.delete('dir');
      loadList(p);
    });
    var clearLink = filterForm.querySelector('[data-yt-clear]');
    if (clearLink) {
      clearLink.addEventListener('click', function (e) {
        e.preventDefault();
        clearFilters();
      });
    }
  }

  // ---------- Sincronizar ----------
  document.querySelectorAll('form.js-yt-sync').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var buttons = document.querySelectorAll('form.js-yt-sync button');
      var icons = document.querySelectorAll('form.js-yt-sync .fa-arrows-rotate');
      buttons.forEach(function (b) { b.disabled = true; });
      icons.forEach(function (i) { i.classList.add('fa-spin'); });
      getJson(form.action, { method: 'POST', body: new FormData(form) })
        .then(function (data) {
          flash(data.message || 'Sincronizado.', true);
          return Promise.all([
            loadMetrics(state.get('period') || '7d', state.get('start'), state.get('end')),
            loadList(new URLSearchParams(state)),
          ]).then(function () {
            // loadList marca a aba de vídeos no endereço; mantém a aba que está aberta.
            var videosOpen = !document.getElementById('tab-content-videos').classList.contains('hidden');
            window.ytRememberTab(videosOpen ? 'videos' : 'metricas');
          });
        })
        .catch(function (err) { flash('Falha ao sincronizar: ' + err.message, false); })
        .finally(function () {
          buttons.forEach(function (b) { b.disabled = false; });
          icons.forEach(function (i) { i.classList.remove('fa-spin'); });
        });
    });
  });
})();
</script>

<style>
  /* Gráfico do período: cores fixas aqui porque o Tailwind compilado não tem bg-gradient-to-t nem as variantes de hover. */
  .yt-chart { position: relative; display: flex; align-items: flex-end; gap: 2px; height: 14rem; padding: 1.5rem 1rem .5rem; background: rgba(2, 6, 23, .4); border: 1px solid rgba(30, 41, 59, .6); border-radius: .75rem; overflow: hidden; }
  .yt-chart-col { flex: 1 1 0; min-width: 0; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; border-radius: 4px; cursor: default; }
  .yt-chart-col:hover { background: rgba(148, 163, 184, .08); }
  .yt-chart-bars { width: 100%; height: 160px; display: flex; align-items: flex-end; justify-content: center; gap: 2px; }
  .yt-chart-bar { flex: 1 1 0; max-width: 14px; border-radius: 3px 3px 0 0; }
  .yt-chart-bar-views { background: #22d3ee; }
  .yt-chart-bar-likes { background: #f472b6; }
  .yt-chart-col:hover .yt-chart-bar-views { background: #67e8f9; }
  .yt-chart-col:hover .yt-chart-bar-likes { background: #f9a8d4; }
  .yt-chart-label { height: 14px; margin-top: 4px; font: 10px/14px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color: #64748b; white-space: nowrap; }
  /* Tabela de vídeos: margem interna menor que a padrão do admin, título limitado e o resto da largura para as outras colunas. */
  .yt-videos-table .posts-table-th, .yt-videos-table .posts-table-td { padding-left: .5rem; padding-right: .5rem; white-space: nowrap; }
  .yt-videos-table .yt-title-col { width: 240px; }
  .yt-videos-table .yt-title-box { max-width: 240px; }
  .yt-videos-table .yt-cell-line { display: flex; flex-direction: column; align-items: center; gap: .25rem; }
  /* Janela larga: título até 380 px, selos e data numa linha só. Abaixo disso empilham para não rolar. */
  @media (min-width: 1700px) {
    .yt-videos-table .yt-title-col { width: 380px; }
    .yt-videos-table .yt-title-box { max-width: 380px; }
    .yt-videos-table .yt-cell-line { flex-direction: row; justify-content: center; gap: .375rem; }
  }
  .yt-chart-empty { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #64748b; pointer-events: none; }
</style>
