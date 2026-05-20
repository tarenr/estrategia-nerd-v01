<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\View;

$module = is_array($module ?? null) ? $module : [];
$cloudSort = in_array((string) ($_GET['cloud_sort'] ?? 'date'), ['date', 'type', 'profile', 'id', 'size', 'status', 'files'], true)
    ? (string) ($_GET['cloud_sort'] ?? 'date')
    : 'date';
$cloudDir = strtolower((string) ($_GET['cloud_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
$cloudPage = max(1, (int) ($_GET['cloud_page'] ?? 1));
$cloudPerPage = in_array((int) ($_GET['cloud_per_page'] ?? 10), [10, 20, 50], true) ? (int) ($_GET['cloud_per_page'] ?? 10) : 10;
$cloudSearch = trim((string) ($_GET['cloud_busca'] ?? ''));
$cloudEnvironment = strtolower(trim((string) ($_GET['cloud_ambiente'] ?? '')));
$cloudStatus = strtolower(trim((string) ($_GET['cloud_status'] ?? '')));
if (!in_array($cloudEnvironment, ['', 'local', 'stage', 'production'], true)) {
    $cloudEnvironment = '';
}
if (!in_array($cloudStatus, ['', 'enviado', 'pendente'], true)) {
    $cloudStatus = '';
}
$backupTools = is_array($backup_tools ?? null) ? $backup_tools : [];
$backupStatus = is_array($backupTools['backup_status'] ?? null) ? $backupTools['backup_status'] : [];
$systemItems = is_array($backupStatus['items'] ?? null) ? $backupStatus['items'] : [];
$backupCloud = is_array($backupTools['backup_cloud'] ?? null) ? $backupTools['backup_cloud'] : [];
$cloudSpaceUsage = is_array($backupCloud['space_usage'] ?? null) ? $backupCloud['space_usage'] : [];
$editorialCloud = is_array($editorial_cloud ?? null) ? $editorial_cloud : [];
$editorialItems = is_array($editorialCloud['items'] ?? null) ? $editorialCloud['items'] : [];
$cloudFlash = is_array($cloud_flash ?? null) ? $cloud_flash : null;

$spacePercent = max(0, min(100, (float) ($cloudSpaceUsage['percent_used'] ?? 0)));
$systemAutoEnabled = (bool) ($backupCloud['auto_upload_enabled'] ?? false);
$editorialAutoEnabled = (bool) ($editorialCloud['auto_upload_enabled'] ?? false);
$systemUploaded = array_values(array_filter($systemItems, static fn (array $item): bool => ($item['cloud_uploaded'] ?? false) === true));
$editorialUploaded = array_values(array_filter($editorialItems, static fn (array $item): bool => ($item['cloud_uploaded'] ?? false) === true));
$systemLastUpload = is_array($backupCloud['last_upload'] ?? null) ? $backupCloud['last_upload'] : null;
$editorialLastUpload = is_array($editorialCloud['last_upload'] ?? null) ? $editorialCloud['last_upload'] : null;

$formatDate = static function (?string $value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return 'Leitura pendente';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('d/m/Y H:i:s', $timestamp);
};

$lastTimestamp = static function (?array $item, array $fields): int {
    if ($item === null) {
        return 0;
    }

    foreach ($fields as $field) {
        $timestamp = strtotime((string) ($item[$field] ?? ''));
        if ($timestamp !== false) {
            return $timestamp;
        }
    }

    return 0;
};

$latestForProfile = static function (array $items, string $profile, array $fields) use ($lastTimestamp): ?array {
    $latest = null;
    $latestTimestamp = 0;

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $itemProfile = strtolower((string) ($item['profile'] ?? $item['source_profile'] ?? ''));
        if ($itemProfile !== $profile) {
            continue;
        }

        $timestamp = $lastTimestamp($item, $fields);
        if ($latest === null || $timestamp >= $latestTimestamp) {
            $latest = $item;
            $latestTimestamp = $timestamp;
        }
    }

    return $latest;
};

$latestSync = null;
$latestSyncType = '';
if ($systemLastUpload !== null) {
    $latestSync = [
        'id' => (string) ($systemLastUpload['backup_id'] ?? ''),
        'uploaded_at' => (string) ($systemLastUpload['uploaded_at'] ?? $systemLastUpload['cloud_uploaded_at'] ?? ''),
    ];
    $latestSyncType = 'Sistema';
}
if ($editorialLastUpload !== null) {
    $editorialTimestamp = $lastTimestamp($editorialLastUpload, ['uploaded_at', 'cloud_uploaded_at']);
    $systemTimestamp = $lastTimestamp($latestSync, ['uploaded_at', 'cloud_uploaded_at']);
    if ($latestSync === null || $editorialTimestamp >= $systemTimestamp) {
        $latestSync = [
            'id' => (string) ($editorialLastUpload['package_id'] ?? ''),
            'uploaded_at' => (string) ($editorialLastUpload['uploaded_at'] ?? $editorialLastUpload['cloud_uploaded_at'] ?? ''),
        ];
        $latestSyncType = 'Editorial';
    }
}

$environments = [
    'local' => 'Local',
    'stage' => 'Stage',
    'production' => 'Produ&ccedil;&atilde;o',
];
$profileLabels = [
    'local' => 'Local',
    'stage' => 'Stage',
    'production' => 'Producao',
];

$alerts = [];
if (!($backupCloud['connected'] ?? false)) {
    $alerts[] = ['tone' => 'neutral', 'label' => 'Dropbox pendente', 'text' => 'Conta ainda n&atilde;o conectada.'];
}
if (($cloudSpaceUsage['available'] ?? false) && $spacePercent >= 80) {
    $alerts[] = ['tone' => 'warning', 'label' => 'Espa&ccedil;o alto', 'text' => 'Uso do Dropbox acima de 80%.'];
}
if ($systemLastUpload === null && $editorialLastUpload === null) {
    $alerts[] = ['tone' => 'neutral', 'label' => 'Leitura pendente', 'text' => 'Nenhuma sincroniza&ccedil;&atilde;o registrada no estado local.'];
}
if ($alerts === []) {
    $alerts[] = ['tone' => 'success', 'label' => 'Sem alertas', 'text' => 'Nenhum alerta cr&iacute;tico encontrado na leitura atual.'];
}

$historyRows = [];
foreach ($systemItems as $item) {
    if (!is_array($item)) {
        continue;
    }

    $profile = strtolower((string) ($item['profile'] ?? ''));
    $timestamp = $lastTimestamp($item, ['cloud_uploaded_at', 'created_at', 'generated_at']);
    $historyRows[] = [
        'timestamp' => $timestamp,
        'date' => $formatDate((string) ($item['cloud_uploaded_at'] ?? $item['created_at'] ?? $item['generated_at'] ?? '')),
        'created_at' => (string) ($item['created_at'] ?? $item['generated_at'] ?? ''),
        'type' => 'Sistema',
        'type_key' => 'system',
        'profile_key' => $profile,
        'profile' => $profileLabels[$profile] ?? ucfirst($profile ?: 'Local'),
        'id' => (string) ($item['backup_id'] ?? '-'),
        'content' => 'Banco, uploads e sistema',
        'size_bytes' => (int) ($item['cloud_uploaded_size_bytes'] ?? $item['total_size_bytes'] ?? 0),
        'size' => (string) ($item['cloud_uploaded_size'] ?? $item['total_size'] ?? '-'),
        'status' => (bool) ($item['cloud_uploaded'] ?? false) ? 'Enviado' : 'Pendente',
        'tone' => (bool) ($item['cloud_uploaded'] ?? false) ? 'success' : 'neutral',
        'destination' => (string) ($item['cloud_destination'] ?? '-'),
        'files' => (string) ($item['cloud_uploaded_files_count'] ?? '-'),
    ];
}

foreach ($editorialItems as $item) {
    if (!is_array($item)) {
        continue;
    }

    $profile = strtolower((string) ($item['source_profile'] ?? ''));
    $timestamp = $lastTimestamp($item, ['cloud_uploaded_at', 'created_at']);
    $uploads = is_array($item['uploads'] ?? null) ? $item['uploads'] : [];
    $historyRows[] = [
        'timestamp' => $timestamp,
        'date' => $formatDate((string) ($item['cloud_uploaded_at'] ?? $item['created_at'] ?? '')),
        'created_at' => (string) ($item['created_at'] ?? ''),
        'type' => 'Editorial',
        'type_key' => 'editorial',
        'profile_key' => $profile,
        'profile' => $profileLabels[$profile] ?? ucfirst($profile ?: 'Local'),
        'id' => (string) ($item['package_id'] ?? '-'),
        'content' => sprintf(
            '%d posts, %d links',
            (int) ($item['stats']['posts'] ?? 0),
            (int) ($item['stats']['links'] ?? 0)
        ),
        'size_bytes' => (int) ($item['cloud_uploaded_size_bytes'] ?? 0),
        'size' => (string) ($item['cloud_uploaded_size'] ?? '-'),
        'status' => (bool) ($item['cloud_uploaded'] ?? false) ? 'Enviado' : 'Pendente',
        'tone' => (bool) ($item['cloud_uploaded'] ?? false) ? 'success' : 'neutral',
        'destination' => (string) ($item['cloud_destination'] ?? '-'),
        'files' => (string) ($item['cloud_uploaded_files_count'] ?? $uploads['included_files'] ?? '-'),
    ];
}

$cloudHistorySummary = [
    'total' => count($historyRows),
    'sent' => count(array_filter($historyRows, static fn (array $row): bool => ($row['status'] ?? '') === 'Enviado')),
    'pending' => count(array_filter($historyRows, static fn (array $row): bool => ($row['status'] ?? '') !== 'Enviado')),
    'production' => count(array_filter($historyRows, static fn (array $row): bool => ($row['profile_key'] ?? '') === 'production')),
];
$allHistoryRows = $historyRows;

$historyRows = array_values(array_filter($historyRows, static function (array $row) use ($cloudSearch, $cloudEnvironment, $cloudStatus): bool {
    if ($cloudEnvironment !== '' && (string) ($row['profile_key'] ?? '') !== $cloudEnvironment) {
        return false;
    }

    if ($cloudStatus !== '') {
        $status = strtolower((string) ($row['status'] ?? ''));
        if ($status !== $cloudStatus) {
            return false;
        }
    }

    if ($cloudSearch !== '') {
        $haystack = strtolower((string) ($row['type'] ?? '') . ' ' . (string) ($row['profile'] ?? '') . ' ' . (string) ($row['id'] ?? ''));
        if (!str_contains($haystack, strtolower($cloudSearch))) {
            return false;
        }
    }

    return true;
}));

$sortValue = static function (array $row, string $sort): int|string {
    return match ($sort) {
        'date' => (int) ($row['timestamp'] ?? 0),
        'size' => (int) ($row['size_bytes'] ?? 0),
        'files' => is_numeric($row['files'] ?? null) ? (int) $row['files'] : 0,
        default => strtolower((string) ($row[$sort] ?? '')),
    };
};

usort($historyRows, static function (array $a, array $b) use ($cloudSort, $cloudDir, $sortValue): int {
    $left = $sortValue($a, $cloudSort);
    $right = $sortValue($b, $cloudSort);
    $result = $left <=> $right;

    return $cloudDir === 'asc' ? $result : -$result;
});

$historyTotal = count($historyRows);
$historyPages = max(1, (int) ceil($historyTotal / max(1, $cloudPerPage)));
$cloudPage = min($cloudPage, $historyPages);
$historyOffset = ($cloudPage - 1) * $cloudPerPage;
$historyPageRows = array_slice($historyRows, $historyOffset, $cloudPerPage);
$historyFirstItem = $historyTotal > 0 ? $historyOffset + 1 : 0;
$historyLastItem = $historyTotal > 0 ? min($historyTotal, $historyOffset + $cloudPerPage) : 0;
$historyStartPage = max(1, $cloudPage - 2);
$historyEndPage = min($historyPages, $cloudPage + 2);
if (($historyEndPage - $historyStartPage) < 4) {
    $historyStartPage = max(1, $historyEndPage - 4);
    $historyEndPage = min($historyPages, $historyStartPage + 4);
}

$cloudBaseUrl = function_exists('url') ? url('/admin/central-operacional-v2/backup-em-nuvem') : '/admin/central-operacional-v2/backup-em-nuvem';
$cloudOverviewUrl = rtrim($cloudBaseUrl, '/') . '/resumo';
$cloudHistoryUrl = rtrim($cloudBaseUrl, '/') . '/historico';
$cloudReturnTarget = static function (string $target): string {
    $path = (string) parse_url($target, PHP_URL_PATH);
    $query = (string) parse_url($target, PHP_URL_QUERY);

    return $path . ($query !== '' ? '?' . $query : '');
};
$cloudOverviewReturnTarget = $cloudReturnTarget($cloudBaseUrl);
$cloudHistoryReturnTarget = $cloudReturnTarget($cloudHistoryUrl);
$cloudBuildUrl = static function (array $overrides = []) use ($cloudHistoryUrl, $cloudSort, $cloudDir, $cloudPage, $cloudPerPage, $cloudSearch, $cloudEnvironment, $cloudStatus): string {
    $query = [
        'cloud_sort' => $cloudSort,
        'cloud_dir' => $cloudDir,
        'cloud_page' => $cloudPage,
        'cloud_per_page' => $cloudPerPage,
        'cloud_busca' => $cloudSearch,
        'cloud_ambiente' => $cloudEnvironment,
        'cloud_status' => $cloudStatus,
    ];

    foreach ($overrides as $key => $value) {
        $query[$key] = $value;
    }

    $query = array_filter($query, static fn ($value): bool => !($value === '' || $value === null || $value === 0));
    $qs = http_build_query($query);

    return $qs !== '' ? $cloudHistoryUrl . '?' . $qs : $cloudHistoryUrl;
};
$cloudSortLink = static function (string $column) use ($cloudSort, $cloudDir, $cloudBuildUrl): string {
    $nextDir = ($cloudSort === $column && $cloudDir === 'asc') ? 'desc' : 'asc';

    return $cloudBuildUrl(['cloud_sort' => $column, 'cloud_dir' => $nextDir, 'cloud_page' => 1]);
};
$cloudSortIcon = static function (string $column) use ($cloudSort, $cloudDir): string {
    if ($cloudSort !== $column) {
        return '<span class="text-slate-600">&harr;</span>';
    }

    return $cloudDir === 'asc'
        ? '<span class="text-cyan-300">&uarr;</span>'
        : '<span class="text-cyan-300">&darr;</span>';
};
$initialCloudTab = (string) ($_GET['cloud_tab'] ?? '') === 'history' || isset($_GET['cloud_sort'], $_GET['cloud_page'], $_GET['cloud_per_page'])
    ? 'history'
    : 'overview';
?>
<section class="space-y-6" data-cloud-backup-root>
  <?php View::component('admin/v2/page-header', [
      'eyebrow' => 'Central Operacional',
      'title' => (string) ($module['label'] ?? 'Backup em Nuvem'),
      'description' => '',
      'actions' => [
          [
              'href' => url('/admin/central-operacional-v2'),
              'label' => 'Voltar',
              'icon' => 'fa-solid fa-arrow-left',
              'variant' => 'secondary',
          ],
      ],
  ]); ?>

  <?php if ($cloudFlash !== null): ?>
    <?php $flashType = (string) ($cloudFlash['type'] ?? 'info'); ?>
    <div class="rounded-2xl border px-4 py-3 text-sm font-semibold <?= $flashType === 'success' ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-100' : 'border-amber-500/30 bg-amber-500/10 text-amber-100' ?>">
      <?= htmlspecialchars((string) ($cloudFlash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <style>
    .cloud-progress-overlay {
      position: fixed;
      inset: 0;
      z-index: 9999;
      display: none;
      align-items: flex-start;
      justify-content: center;
      overflow: auto;
      padding: 2rem 1rem;
      background: rgba(2, 6, 23, 0.84);
      backdrop-filter: blur(12px);
    }

    .cloud-progress-overlay.is-visible {
      display: flex;
    }

    .cloud-progress-card {
      box-sizing: border-box;
      width: min(92vw, 44rem);
      max-width: calc(100vw - 32px);
      max-height: calc(100vh - 4rem);
      overflow: auto;
      contain: layout paint;
      border-radius: 1.35rem;
      border: 1px solid rgba(34, 211, 238, 0.28);
      background: linear-gradient(180deg, rgba(15, 23, 42, 0.94), rgba(2, 6, 23, 0.94));
      padding: 1.5rem;
      box-shadow: 0 0 34px rgba(6, 182, 212, 0.1);
    }

    .cloud-progress-card h2 {
      overflow-wrap: anywhere;
    }

    .cloud-progress-bar {
      height: 0.8rem;
      overflow: hidden;
      border-radius: 999px;
      background: rgba(30, 41, 59, 0.9);
      border: 1px solid rgba(51, 65, 85, 0.8);
    }

    .cloud-progress-fill {
      height: 100%;
      width: 8%;
      border-radius: inherit;
      background: linear-gradient(90deg, #22d3ee, #60a5fa, #c084fc);
      box-shadow: 0 0 24px rgba(96, 165, 250, 0.35);
      transition: width 0.4s ease;
    }

    .cloud-file-progress {
      display: none;
    }

    .cloud-file-progress.is-visible {
      display: grid;
    }

    @media (max-width: 640px) {
      .cloud-progress-card { padding: 1rem; }
    }
  </style>

  <div id="cloud-progress-overlay" class="cloud-progress-overlay" aria-hidden="true" tabindex="-1">
    <div class="cloud-progress-card" role="status" aria-live="polite">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Processando</p>
      <h2 id="cloud-progress-title" class="mt-3 font-orbitron text-2xl font-black text-white">Enviando para nuvem</h2>
      <p id="cloud-progress-message" class="mt-3 text-sm leading-7 text-slate-300">Validando a acao e preparando o envio.</p>
      <div class="mt-6 cloud-progress-bar">
        <div id="cloud-progress-fill" class="cloud-progress-fill"></div>
      </div>
      <div class="mt-4 flex items-center justify-between text-xs uppercase tracking-[0.2em] text-slate-400">
        <span id="cloud-progress-stage">Preparando</span>
        <span id="cloud-progress-percent">0%</span>
      </div>
      <div id="cloud-file-progress" class="cloud-file-progress mt-4 gap-2 rounded-2xl border border-slate-700/70 bg-slate-950/70 p-3">
        <div class="flex items-center justify-between gap-3 text-xs font-semibold text-slate-300">
          <span id="cloud-file-name" class="truncate">Arquivo atual</span>
          <span id="cloud-file-percent" class="font-orbitron text-cyan-200">0%</span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-slate-800">
          <div id="cloud-file-fill" class="h-full rounded-full bg-cyan-300 transition-all duration-300" style="width: 0%"></div>
        </div>
        <div id="cloud-file-bytes" class="text-xs text-slate-500">0 B de 0 B</div>
      </div>
      <p id="cloud-progress-meta" class="mt-4 text-xs text-slate-500">Aguardando atualizacao da rotina de backup em nuvem.</p>
    </div>
  </div>

  <div class="rounded-[1.25rem] border border-slate-800 bg-slate-950/70 p-2">
    <div class="grid gap-2 md:grid-cols-2">
      <a href="<?= htmlspecialchars($cloudOverviewUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="flex min-h-11 items-center rounded-xl border border-cyan-400/45 bg-cyan-500/10 px-4 py-3 text-left font-orbitron text-xs font-black uppercase tracking-[0.14em] text-cyan-100 shadow-[0_0_22px_rgba(34,211,238,0.12)] transition hover:border-cyan-300 hover:bg-cyan-500/15" data-cloud-tab="overview">
        Visao Geral
      </a>
      <a href="<?= htmlspecialchars($cloudHistoryUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="flex min-h-11 items-center rounded-xl border border-slate-800 bg-slate-900/70 px-4 py-3 text-left font-orbitron text-xs font-black uppercase tracking-[0.14em] text-slate-300 transition hover:border-cyan-500/35 hover:bg-cyan-500/10 hover:text-cyan-100" data-cloud-tab="history">
        Historico de Envios
      </a>
    </div>
  </div>

  <div class="space-y-6<?= $initialCloudTab === 'overview' ? '' : ' hidden' ?>" data-cloud-tab-panel="overview">
  <section class="rounded-[1.6rem] border border-slate-800 bg-slate-900/85 p-5 shadow-[0_0_34px_rgba(2,6,23,0.18)]">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h2 class="font-orbitron text-sm font-black uppercase tracking-[0.16em] text-cyan-300/75">Dropbox</h2>
      <?php View::component('admin/v2/status-badge', [
          'label' => ($backupCloud['connected'] ?? false) ? 'Conectado' : 'Pendente',
          'tone' => ($backupCloud['connected'] ?? false) ? 'success' : 'neutral',
      ]); ?>
    </div>

    <div class="mt-5 grid gap-4 xl:grid-cols-4">
      <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Conta</div>
        <div class="mt-2 text-lg font-black text-white"><?= htmlspecialchars((string) ($backupCloud['account_name'] ?? 'Aguardando conexao'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <div class="mt-1 text-xs text-slate-400"><?= htmlspecialchars((string) ($backupCloud['account_email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </div>

      <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Automacao</div>
        <div class="mt-2 text-lg font-black text-white"><?= $systemAutoEnabled || $editorialAutoEnabled ? 'Ativa' : 'Manual' ?></div>
        <div class="mt-1 text-xs text-slate-400">Sistema: <?= $systemAutoEnabled ? 'ativa' : 'manual' ?> | Editorial: <?= $editorialAutoEnabled ? 'ativa' : 'manual' ?></div>
      </div>

      <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Ultima Sync</div>
        <div class="mt-2 text-lg font-black text-white"><?= $latestSync !== null ? htmlspecialchars($latestSyncType, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'Leitura pendente' ?></div>
        <div class="mt-1 text-xs text-slate-400"><?= htmlspecialchars($formatDate($latestSync['uploaded_at'] ?? null), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </div>

      <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Armazenamento</div>
        <div class="mt-2 text-lg font-black text-white"><?= count($systemUploaded) + count($editorialUploaded) ?> enviados</div>
        <div class="mt-1 text-xs text-slate-400">Sistema: <?= count($systemUploaded) ?> | Editorial: <?= count($editorialUploaded) ?></div>
      </div>
    </div>

    <div class="mt-4 rounded-2xl border border-slate-800 bg-slate-950/70 p-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Espa&ccedil;o Dropbox</div>
          <div class="mt-3 text-2xl font-black text-white"><?= htmlspecialchars((string) ($cloudSpaceUsage['free'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> livres</div>
        </div>
        <span class="inline-flex items-center rounded-full border border-cyan-400/35 bg-cyan-500/10 px-3 py-1 text-[10px] font-black text-cyan-100">
          <?= htmlspecialchars((string) round($spacePercent), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>% usando
        </span>
      </div>
      <div class="mt-5 h-2 overflow-hidden rounded-full bg-slate-800">
        <div class="h-full rounded-full bg-cyan-400" style="width: <?= htmlspecialchars((string) $spacePercent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>%"></div>
      </div>
      <div class="mt-4 grid gap-3 text-xs md:grid-cols-3">
        <div>
          <div class="text-slate-500">Usado</div>
          <div class="mt-1 font-black text-white"><?= htmlspecialchars((string) ($cloudSpaceUsage['used'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </div>
        <div>
          <div class="text-slate-500">Total</div>
          <div class="mt-1 font-black text-white"><?= htmlspecialchars((string) ($cloudSpaceUsage['allocated'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </div>
        <div>
          <div class="text-slate-500">Livre</div>
          <div class="mt-1 font-black text-white"><?= htmlspecialchars((string) ($cloudSpaceUsage['free'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </div>
      </div>
    </div>

    <div class="mt-4 grid gap-3 md:grid-cols-2">
      <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Raiz Sistemica</div>
        <div class="mt-2 break-all text-sm font-semibold text-slate-200"><?= htmlspecialchars((string) ($backupCloud['remote_root'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Raiz Editorial</div>
        <div class="mt-2 break-all text-sm font-semibold text-slate-200"><?= htmlspecialchars((string) ($editorialCloud['remote_root'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </div>
    </div>

    <?php if ($backupCloud['connected'] ?? false): ?>
      <div class="mt-4 grid gap-3 md:grid-cols-3">
        <form method="POST" action="<?= htmlspecialchars(url('/local/backup'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="cloud-action-form flex h-full flex-col rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Atualizando automacao sistemica" data-progress-message="Salvando a politica de envio automatico dos backups sistemicos." data-progress-stage="Automacao Dropbox">
          <?= Csrf::field() ?>
          <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($cloudOverviewReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          <input type="hidden" name="action" value="dropbox_auto_upload">
          <input type="hidden" name="enabled" value="<?= $systemAutoEnabled ? '0' : '1' ?>">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-cyan-300/80">Envio Automatico - Sistema</div>
          <p class="mt-2 text-xs font-semibold leading-5 text-slate-400"><?= $systemAutoEnabled ? 'Controle manual para backups sistemicos.' : 'Automacao para backups sistemicos.' ?></p>
          <div class="mt-auto pt-4">
            <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-emerald-400/40 bg-emerald-500/10 px-4 py-2 text-xs font-black text-emerald-200 transition hover:border-emerald-300 hover:bg-emerald-500/20"><?= $systemAutoEnabled ? 'Desativar automacao' : 'Ativar automacao' ?></button>
          </div>
        </form>

        <form method="POST" action="<?= htmlspecialchars(url('/admin/central-operacional-v2/backup-em-nuvem'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="cloud-action-form flex h-full flex-col rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Atualizando automacao editorial" data-progress-message="Salvando a politica de envio automatico dos pacotes editoriais." data-progress-stage="Automacao Dropbox">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="dropbox_editorial_auto_upload">
          <input type="hidden" name="enabled" value="<?= $editorialAutoEnabled ? '0' : '1' ?>">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-cyan-300/80">Envio Automatico - Editorial</div>
          <p class="mt-2 text-xs font-semibold leading-5 text-slate-400"><?= $editorialAutoEnabled ? 'Controle manual para pacotes editoriais.' : 'Automacao para pacotes editoriais.' ?></p>
          <div class="mt-auto pt-4">
            <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-sky-400/40 bg-sky-500/10 px-4 py-2 text-xs font-black text-sky-200 transition hover:border-sky-300 hover:bg-sky-500/20"><?= $editorialAutoEnabled ? 'Desativar automacao' : 'Ativar automacao' ?></button>
          </div>
        </form>

        <form method="POST" action="<?= htmlspecialchars(url('/local/backup'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="cloud-action-form flex h-full flex-col rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Desconectando Dropbox" data-progress-message="Removendo a vinculacao local com a conta do Dropbox." data-progress-stage="Dropbox">
          <?= Csrf::field() ?>
          <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($cloudOverviewReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          <input type="hidden" name="action" value="dropbox_disconnect">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-rose-300/80">Desconectar</div>
          <p class="mt-2 text-xs font-semibold leading-5 text-slate-400">Remove tokens locais sem apagar backups ja enviados.</p>
          <div class="mt-auto pt-4">
            <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-rose-400/40 bg-rose-500/10 px-4 py-2 text-xs font-black text-rose-200 transition hover:border-rose-300 hover:bg-rose-500/20">Desconectar</button>
          </div>
        </form>
      </div>
    <?php else: ?>
      <form method="POST" action="<?= htmlspecialchars(url('/local/backup'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="cloud-action-form mt-4 rounded-2xl border border-cyan-400/25 bg-cyan-500/10 p-4" data-progress-title="Conectando Dropbox" data-progress-message="Abrindo a autorizacao segura do Dropbox para vincular a conta." data-progress-stage="Dropbox OAuth">
        <?= Csrf::field() ?>
        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($cloudOverviewReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="action" value="dropbox_connect">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-cyan-200">Conectar Dropbox</div>
        <p class="mt-2 text-xs font-semibold leading-5 text-cyan-100/80">Vincula a conta para consultar espaco e registrar envios em nuvem.</p>
        <button type="submit" class="mt-4 inline-flex min-h-10 items-center justify-center rounded-xl border border-cyan-300/40 bg-cyan-400/10 px-4 py-2 text-xs font-black text-cyan-100 transition hover:border-cyan-200 hover:bg-cyan-400/20">Conectar</button>
      </form>
    <?php endif; ?>
  </section>

  <section class="rounded-[1.6rem] border border-slate-800 bg-slate-900/85 p-5 shadow-[0_0_34px_rgba(2,6,23,0.18)]">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h2 class="font-orbitron text-sm font-black uppercase tracking-[0.16em] text-cyan-300/75">Backups por Ambiente</h2>
      <?php View::component('admin/v2/status-badge', [
          'label' => 'Sistema + Editorial',
          'tone' => 'neutral',
      ]); ?>
    </div>

    <div class="mt-5 grid gap-4 xl:grid-cols-3">
      <?php foreach ($environments as $profile => $label): ?>
        <?php
          $system = $latestForProfile($systemItems, $profile, ['cloud_uploaded_at', 'created_at', 'generated_at']);
          $editorial = $latestForProfile($editorialItems, $profile, ['cloud_uploaded_at', 'created_at']);
          $systemUploadedForProfile = (bool) ($system['cloud_uploaded'] ?? false);
          $editorialUploadedForProfile = (bool) ($editorial['cloud_uploaded'] ?? false);
        ?>
        <article class="rounded-2xl border <?= $profile === 'production' ? 'border-amber-400/35 bg-amber-500/[0.04]' : 'border-slate-800 bg-slate-950/70' ?> p-5">
          <div class="flex items-center justify-between gap-3">
            <h3 class="font-orbitron text-sm font-black uppercase tracking-[0.14em] text-white"><?= $label ?></h3>
            <?php View::component('admin/v2/status-badge', [
                'label' => ($systemUploadedForProfile || $editorialUploadedForProfile) ? 'Com leitura' : 'Pendente',
                'tone' => ($systemUploadedForProfile || $editorialUploadedForProfile) ? 'success' : 'neutral',
            ]); ?>
          </div>

          <div class="mt-4 grid gap-3">
            <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-4">
              <div class="flex items-center justify-between gap-3">
                <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-cyan-300/70">Sistema</div>
                <?php View::component('admin/v2/status-badge', [
                    'label' => $systemUploadedForProfile ? 'Enviado' : 'Pendente',
                    'tone' => $systemUploadedForProfile ? 'success' : 'neutral',
                ]); ?>
              </div>
              <div class="mt-3 text-sm font-black text-white"><?= htmlspecialchars((string) ($system['backup_id'] ?? 'Leitura pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <div class="mt-1 text-xs text-slate-400"><?= htmlspecialchars($formatDate($system['cloud_uploaded_at'] ?? $system['created_at'] ?? null), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <div class="mt-2 text-xs text-slate-500">Tamanho: <?= htmlspecialchars((string) ($system['total_size'] ?? $system['cloud_uploaded_size'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            </div>

            <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-4">
              <div class="flex items-center justify-between gap-3">
                <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-cyan-300/70">Editorial</div>
                <?php View::component('admin/v2/status-badge', [
                    'label' => $editorialUploadedForProfile ? 'Enviado' : 'Pendente',
                    'tone' => $editorialUploadedForProfile ? 'success' : 'neutral',
                ]); ?>
              </div>
              <div class="mt-3 text-sm font-black text-white"><?= htmlspecialchars((string) ($editorial['package_id'] ?? 'Leitura pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <div class="mt-1 text-xs text-slate-400"><?= htmlspecialchars($formatDate($editorial['cloud_uploaded_at'] ?? $editorial['created_at'] ?? null), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <div class="mt-2 text-xs text-slate-500">Tamanho: <?= htmlspecialchars((string) ($editorial['cloud_uploaded_size'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="grid gap-4 xl:grid-cols-[0.9fr_1.1fr]">
    <div class="rounded-[1.6rem] border border-slate-800 bg-slate-900/85 p-5 shadow-[0_0_34px_rgba(2,6,23,0.18)]">
      <h2 class="font-orbitron text-sm font-black uppercase tracking-[0.16em] text-cyan-300/75">Armazenamento</h2>
      <div class="mt-5 grid gap-3 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Sistemicos</div>
          <div class="mt-2 text-xl font-black text-white"><?= count($systemUploaded) ?></div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Editoriais</div>
          <div class="mt-2 text-xl font-black text-white"><?= count($editorialUploaded) ?></div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Total</div>
          <div class="mt-2 text-xl font-black text-white"><?= count($systemUploaded) + count($editorialUploaded) ?></div>
        </div>
      </div>
      <div class="mt-4 rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Retencao</div>
        <div class="mt-2 text-sm font-semibold text-slate-200">Politica atual preservada pelas rotinas existentes.</div>
      </div>
    </div>

    <div class="rounded-[1.6rem] border border-slate-800 bg-slate-900/85 p-5 shadow-[0_0_34px_rgba(2,6,23,0.18)]">
      <h2 class="font-orbitron text-sm font-black uppercase tracking-[0.16em] text-cyan-300/75">Alertas</h2>
      <div class="mt-5 grid gap-3">
        <?php foreach ($alerts as $alert): ?>
          <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <div>
              <div class="font-semibold text-white"><?= $alert['label'] ?></div>
              <div class="mt-1 text-xs text-slate-400"><?= $alert['text'] ?></div>
            </div>
            <?php View::component('admin/v2/status-badge', [
                'label' => $alert['tone'] === 'success' ? 'OK' : 'Atencao',
                'tone' => $alert['tone'],
            ]); ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  </div>

  <div class="<?= $initialCloudTab === 'history' ? '' : 'hidden' ?>" data-cloud-tab-panel="history">
    <style>
      .ops-history-table tbody tr td {
        border-top: 1px solid rgb(30 41 59);
        border-bottom: 1px solid rgb(30 41 59);
        background: rgba(2, 6, 23, 0.7);
        padding: 1rem;
        vertical-align: top;
      }
      .ops-history-table tbody tr td:first-child {
        border-left: 1px solid rgb(30 41 59);
        border-radius: 1rem 0 0 1rem;
      }
      .ops-history-table tbody tr td:last-child {
        border-right: 1px solid rgb(30 41 59);
        border-radius: 0 1rem 1rem 0;
      }
      .ops-history-table tbody tr:hover td {
        background: rgb(2 6 23);
      }
    </style>
    <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="font-orbitron text-lg font-bold text-white">Historico de Envios</h2>
        </div>
      </div>

      <div class="mt-5 grid gap-3 md:grid-cols-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Total</div>
          <div class="mt-2 text-2xl font-black text-white"><?= (int) $cloudHistorySummary['total'] ?></div>
        </div>
        <div class="rounded-2xl border border-emerald-500/25 bg-emerald-500/10 p-4">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-emerald-200/75">Enviados</div>
          <div class="mt-2 text-2xl font-black text-white"><?= (int) $cloudHistorySummary['sent'] ?></div>
        </div>
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Pendentes</div>
          <div class="mt-2 text-2xl font-black text-white"><?= (int) $cloudHistorySummary['pending'] ?></div>
        </div>
        <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-amber-200/75">Produção</div>
          <div class="mt-2 text-2xl font-black text-white"><?= (int) $cloudHistorySummary['production'] ?></div>
        </div>
      </div>

      <form method="GET" action="<?= htmlspecialchars($cloudHistoryUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="mt-5 rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-cloud-history-filters>
        <div class="grid gap-3 xl:grid-cols-[1.2fr_0.8fr_0.8fr_auto]">
          <input type="search" name="cloud_busca" value="<?= htmlspecialchars($cloudSearch, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" placeholder="Buscar envio..." data-cloud-filter-search class="min-h-11 rounded-xl border border-slate-700 bg-slate-950/80 px-4 text-sm text-white outline-none focus:border-cyan-400">
          <select name="cloud_ambiente" data-cloud-filter-environment class="min-h-11 rounded-xl border border-slate-700 bg-slate-950/80 px-4 text-sm text-white outline-none focus:border-cyan-400">
            <option value="">Todos ambientes</option>
            <option value="local" <?= $cloudEnvironment === 'local' ? 'selected' : '' ?>>Local</option>
            <option value="stage" <?= $cloudEnvironment === 'stage' ? 'selected' : '' ?>>Stage</option>
            <option value="production" <?= $cloudEnvironment === 'production' ? 'selected' : '' ?>>Produção</option>
          </select>
          <select name="cloud_status" data-cloud-filter-status class="min-h-11 rounded-xl border border-slate-700 bg-slate-950/80 px-4 text-sm text-white outline-none focus:border-cyan-400">
            <option value="">Todos status</option>
            <option value="enviado" <?= $cloudStatus === 'enviado' ? 'selected' : '' ?>>Enviados</option>
            <option value="pendente" <?= $cloudStatus === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
          </select>
          <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-cyan-400/35 bg-cyan-500/10 px-4 text-xs font-black uppercase tracking-[0.12em] text-cyan-100 transition hover:border-cyan-300 hover:bg-cyan-500/18">Filtrar</button>
        </div>
      </form>

      <div class="mt-5 overflow-x-auto">
        <div class="overflow-x-auto">
          <table class="ops-history-table min-w-full border-separate border-spacing-y-3 text-left text-sm text-slate-200">
            <thead>
              <tr class="font-orbitron text-[10px] uppercase tracking-[0.18em] text-slate-500">
                <th class="px-4 py-3 font-black"><a class="inline-flex items-center gap-1 transition hover:text-cyan-200" data-cloud-history-sort="date" href="<?= htmlspecialchars($cloudSortLink('date'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Data <?= $cloudSortIcon('date') ?></a></th>
                <th class="px-4 py-3 font-black"><a class="inline-flex items-center gap-1 transition hover:text-cyan-200" data-cloud-history-sort="profile" href="<?= htmlspecialchars($cloudSortLink('profile'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Ambiente <?= $cloudSortIcon('profile') ?></a></th>
                <th class="px-4 py-3 font-black"><a class="inline-flex items-center gap-1 transition hover:text-cyan-200" data-cloud-history-sort="type" href="<?= htmlspecialchars($cloudSortLink('type'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Tipo <?= $cloudSortIcon('type') ?></a></th>
                <th class="px-4 py-3 font-black"><a class="inline-flex items-center gap-1 transition hover:text-cyan-200" data-cloud-history-sort="id" href="<?= htmlspecialchars($cloudSortLink('id'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Identificador <?= $cloudSortIcon('id') ?></a></th>
                <th class="px-4 py-3 font-black">Conteudo</th>
                <th class="px-4 py-3 font-black"><a class="inline-flex items-center gap-1 transition hover:text-cyan-200" data-cloud-history-sort="size" href="<?= htmlspecialchars($cloudSortLink('size'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Tamanho <?= $cloudSortIcon('size') ?></a></th>
                <th class="px-4 py-3 font-black"><a class="inline-flex items-center gap-1 transition hover:text-cyan-200" data-cloud-history-sort="status" href="<?= htmlspecialchars($cloudSortLink('status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Status <?= $cloudSortIcon('status') ?></a></th>
                <th class="px-4 py-3 font-black">Acoes</th>
              </tr>
            </thead>
            <tbody data-cloud-history-body>
              <?php foreach ($historyPageRows as $row): ?>
                <tr class="<?= ($row['profile_key'] ?? '') === 'production' ? 'bg-amber-500/[0.04]' : '' ?> text-slate-300 transition hover:bg-slate-900/70">
                  <td class="whitespace-nowrap px-4 py-3 text-xs font-semibold text-slate-400">
                    <span class="block text-slate-200"><?= htmlspecialchars((string) ($row['date'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    <span class="mt-1 block text-[11px] text-slate-500"><?= htmlspecialchars($formatDate((string) ($row['created_at'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                  </td>
                  <td class="whitespace-nowrap px-4 py-3"><?= htmlspecialchars((string) ($row['profile'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                  <td class="whitespace-nowrap px-4 py-3 font-black text-white"><?= htmlspecialchars((string) ($row['type'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                  <td class="whitespace-nowrap px-4 py-3 font-semibold text-cyan-100"><?= htmlspecialchars((string) ($row['id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                  <td class="whitespace-nowrap px-4 py-3"><?= htmlspecialchars((string) ($row['content'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) ($row['files'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> arquivo(s)</div></td>
                  <td class="whitespace-nowrap px-4 py-3"><?= htmlspecialchars((string) ($row['size'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                  <td class="whitespace-nowrap px-4 py-3">
                    <?php View::component('admin/v2/status-badge', [
                        'label' => (string) ($row['status'] ?? 'Pendente'),
                        'tone' => (string) ($row['tone'] ?? 'neutral'),
                    ]); ?>
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex min-w-[15rem] flex-col gap-2">
                      <?php if (($row['type_key'] ?? '') === 'system'): ?>
                        <form method="POST" action="<?= url('/local/backup') ?>" class="cloud-action-form inline-flex" data-progress-title="Verificando backup sistemico" data-progress-message="Conferindo manifesto, banco, uploads e arquivos do sistema." data-progress-stage="Verificacao">
                          <?= Csrf::field() ?>
                          <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($cloudHistoryReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                          <input type="hidden" name="action" value="verify">
                          <input type="hidden" name="backup_id" value="<?= htmlspecialchars((string) ($row['id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                          <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-emerald-400/35 bg-emerald-500/10 px-3 py-2 text-xs font-black text-emerald-100 transition hover:border-emerald-300 hover:bg-emerald-500/20">Verificar</button>
                        </form>
                        <?php if (($row['status'] ?? '') === 'Enviado'): ?>
                          <form method="POST" action="<?= url('/local/backup') ?>" class="cloud-action-form rounded-xl border border-rose-400/25 bg-rose-500/5 p-2" data-progress-title="Excluindo backup da nuvem" data-progress-message="Solicitando remocao segura do backup selecionado no Dropbox." data-progress-stage="Exclusao Dropbox">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($cloudHistoryReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="dropbox_delete_backup">
                            <input type="hidden" name="backup_id" value="<?= htmlspecialchars((string) ($row['id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                            <input type="text" name="delete_confirmation" placeholder="Confirmar ID para excluir" class="w-full rounded-lg border border-slate-700 bg-slate-950/80 px-2 py-2 text-xs text-white outline-none focus:border-rose-400">
                            <button type="submit" class="mt-2 inline-flex w-full items-center justify-center rounded-lg border border-rose-400/30 bg-transparent px-3 py-2 text-xs font-semibold text-rose-200 transition hover:bg-rose-500/10">Excluir da nuvem</button>
                          </form>
                        <?php else: ?>
                          <form method="POST" action="<?= url('/local/backup') ?>" class="cloud-action-form inline-flex" data-progress-title="Enviando backup para nuvem" data-progress-message="Validando conexao Dropbox e preparando o backup selecionado para envio." data-progress-stage="Envio para nuvem">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($cloudHistoryReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="dropbox_upload_backup">
                            <input type="hidden" name="backup_id" value="<?= htmlspecialchars((string) ($row['id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-sky-400/35 bg-sky-500/10 px-3 py-2 text-xs font-black text-sky-100 transition hover:border-sky-300 hover:bg-sky-500/20">Enviar nuvem</button>
                          </form>
                        <?php endif; ?>
                      <?php else: ?>
                        <form method="POST" action="<?= url('/local/conteudo') ?>" class="cloud-action-form inline-flex" data-progress-title="Verificando pacote editorial" data-progress-message="Conferindo manifesto, JSONs e uploads do pacote selecionado." data-progress-stage="Verificacao editorial">
                          <?= Csrf::field() ?>
                          <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($cloudHistoryReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                          <input type="hidden" name="action" value="verify">
                          <input type="hidden" name="package_id" value="<?= htmlspecialchars((string) ($row['id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                          <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-emerald-400/35 bg-emerald-500/10 px-3 py-2 text-xs font-black text-emerald-100 transition hover:border-emerald-300 hover:bg-emerald-500/20">Verificar</button>
                        </form>
                        <?php if (($row['status'] ?? '') === 'Enviado'): ?>
                          <form method="POST" action="<?= url('/admin/central-operacional-v2/backup-em-nuvem') ?>" class="cloud-action-form rounded-xl border border-rose-400/25 bg-rose-500/5 p-2" data-progress-title="Excluindo pacote da nuvem" data-progress-message="Solicitando remocao segura do pacote editorial no Dropbox." data-progress-stage="Exclusao Dropbox">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($cloudHistoryReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="dropbox_delete_editorial_package">
                            <input type="hidden" name="package_id" value="<?= htmlspecialchars((string) ($row['id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                            <input type="text" name="delete_confirmation" placeholder="Confirmar ID para excluir" class="w-full rounded-lg border border-slate-700 bg-slate-950/80 px-2 py-2 text-xs text-white outline-none focus:border-rose-400">
                            <button type="submit" class="mt-2 inline-flex w-full items-center justify-center rounded-lg border border-rose-400/30 bg-transparent px-3 py-2 text-xs font-semibold text-rose-200 transition hover:bg-rose-500/10">Excluir da nuvem</button>
                          </form>
                        <?php else: ?>
                          <form method="POST" action="<?= url('/admin/central-operacional-v2/backup-em-nuvem') ?>" class="cloud-action-form inline-flex" data-progress-title="Enviando pacote editorial" data-progress-message="Validando conexao Dropbox e preparando o pacote editorial para envio." data-progress-stage="Envio para nuvem">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($cloudHistoryReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="dropbox_upload_editorial_package">
                            <input type="hidden" name="package_id" value="<?= htmlspecialchars((string) ($row['id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-sky-400/35 bg-sky-500/10 px-3 py-2 text-xs font-black text-sky-100 transition hover:border-sky-300 hover:bg-sky-500/20">Enviar nuvem</button>
                          </form>
                        <?php endif; ?>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>

              <?php if ($historyRows === []): ?>
                <tr>
                  <td colspan="8" class="px-4 py-8 text-center text-sm font-semibold text-slate-400">Nenhum envio encontrado.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="mt-4 rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
          <div class="text-xs font-semibold text-slate-400" data-cloud-history-count-summary>
            <?php if ($historyTotal > 0): ?>
              Exibindo <span class="font-black text-white"><?= number_format($historyFirstItem, 0, ',', '.') ?></span> ate <span class="font-black text-white"><?= number_format($historyLastItem, 0, ',', '.') ?></span> de <span class="font-black text-white"><?= number_format($historyTotal, 0, ',', '.') ?></span> registros
            <?php else: ?>
              Nenhum envio para paginar no momento.
            <?php endif; ?>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
              <span class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Por pagina</span>
              <?php foreach ([10, 20, 50] as $option): ?>
                <?php $active = $cloudPerPage === $option; ?>
                <a class="inline-flex min-h-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition <?= $active ? 'border-cyan-400/40 bg-cyan-500/10 text-cyan-100' : 'border-slate-700 bg-slate-900/80 text-slate-300 hover:border-cyan-500/35 hover:text-cyan-100' ?>" data-cloud-history-per-page="<?= $option ?>" href="<?= htmlspecialchars($cloudBuildUrl(['cloud_page' => 1, 'cloud_per_page' => $option]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= $option ?></a>
              <?php endforeach; ?>
            </div>

            <nav class="flex items-center gap-2" aria-label="Paginacao do historico de envios">
              <a class="inline-flex min-h-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition <?= $historyTotal === 0 || $cloudPage <= 1 ? 'pointer-events-none border-slate-800 bg-slate-900/40 text-slate-600' : 'border-slate-700 bg-slate-900/80 text-slate-300 hover:border-cyan-500/35 hover:text-cyan-100' ?>" data-cloud-history-prev href="<?= htmlspecialchars($cloudBuildUrl(['cloud_page' => max(1, $cloudPage - 1)]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Anterior</a>
              <?php for ($current = $historyStartPage; $current <= $historyEndPage; $current++): ?>
                <a class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition <?= $current === $cloudPage ? 'border-cyan-400/40 bg-cyan-500/10 text-cyan-100' : 'border-slate-700 bg-slate-900/80 text-slate-300 hover:border-cyan-500/35 hover:text-cyan-100' ?>" data-cloud-history-page="<?= $current ?>" href="<?= htmlspecialchars($cloudBuildUrl(['cloud_page' => $current]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= $current ?></a>
              <?php endfor; ?>
              <a class="inline-flex min-h-9 items-center justify-center rounded-xl border px-3 text-xs font-black transition <?= $historyTotal === 0 || $cloudPage >= $historyPages ? 'pointer-events-none border-slate-800 bg-slate-900/40 text-slate-600' : 'border-slate-700 bg-slate-900/80 text-slate-300 hover:border-cyan-500/35 hover:text-cyan-100' ?>" data-cloud-history-next href="<?= htmlspecialchars($cloudBuildUrl(['cloud_page' => min($historyPages, $cloudPage + 1)]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Proxima</a>
            </nav>
          </div>
        </div>
      </div>
    </section>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-cloud-backup-root]');
    if (!root) {
      return;
    }

    var overlay = document.getElementById('cloud-progress-overlay');
    var progressTitle = document.getElementById('cloud-progress-title');
    var progressMessage = document.getElementById('cloud-progress-message');
    var progressStage = document.getElementById('cloud-progress-stage');
    var progressPercent = document.getElementById('cloud-progress-percent');
    var progressFill = document.getElementById('cloud-progress-fill');
    var progressMeta = document.getElementById('cloud-progress-meta');
    var fileProgress = document.getElementById('cloud-file-progress');
    var fileName = document.getElementById('cloud-file-name');
    var filePercent = document.getElementById('cloud-file-percent');
    var fileFill = document.getElementById('cloud-file-fill');
    var fileBytes = document.getElementById('cloud-file-bytes');
    var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-cloud-tab]'));
    var panels = Array.prototype.slice.call(root.querySelectorAll('[data-cloud-tab-panel]'));
    var historyRows = <?= json_encode($allHistoryRows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]' ?>;
    var historyState = {
      sort: '<?= htmlspecialchars($cloudSort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>',
      dir: '<?= htmlspecialchars($cloudDir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>',
      page: <?= (int) $cloudPage ?>,
      perPage: <?= (int) $cloudPerPage ?>,
      search: '<?= htmlspecialchars($cloudSearch, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>',
      environment: '<?= htmlspecialchars($cloudEnvironment, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>',
      status: '<?= htmlspecialchars($cloudStatus, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>'
    };
    var csrfField = <?= json_encode(Csrf::field(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: "''" ?>;
    var cloudReturnTarget = <?= json_encode($cloudHistoryReturnTarget, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: "''" ?>;
    var systemActionUrl = <?= json_encode(url('/local/backup'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: "''" ?>;
    var editorialActionUrl = <?= json_encode(url('/local/conteudo'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: "''" ?>;
    var cloudActionUrl = <?= json_encode(url('/admin/central-operacional-v2/backup-em-nuvem'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: "''" ?>;
    var cloudPollTimer = null;

    function setModalState(state) {
      if (!overlay || !progressTitle || !progressMessage || !progressStage || !progressPercent || !progressFill || !progressMeta) {
        return;
      }

      var percent = Math.max(0, Math.min(100, Number(state.percent || 10)));
      var file = state.file && typeof state.file === 'object' ? state.file : null;
      var fileProgressValue = file && file.percent !== undefined ? Math.max(0, Math.min(100, Number(file.percent || 0))) : null;
      var visiblePercent = fileProgressValue !== null ? fileProgressValue : percent;
      progressTitle.textContent = state.title || 'Enviando para nuvem';
      progressMessage.textContent = state.message || 'Validando a acao e preparando o envio.';
      progressStage.textContent = state.stage || 'Preparando';
      progressPercent.textContent = fileProgressValue !== null ? 'Arquivo ' + visiblePercent + '%' : percent + '%';
      progressFill.style.width = Math.max(6, visiblePercent) + '%';
      if (fileProgress && fileName && filePercent && fileFill && fileBytes) {
        if (file) {
          fileProgress.classList.add('is-visible');
          fileName.textContent = file.name || 'Arquivo atual';
          filePercent.textContent = visiblePercent + '%';
          fileFill.style.width = Math.max(3, visiblePercent) + '%';
          fileBytes.textContent = (file.sent_label || '0 B') + ' de ' + (file.total_label || '0 B');
        } else {
          fileProgress.classList.remove('is-visible');
          fileFill.style.width = '0%';
        }
      }
      if (state.meta) {
        progressMeta.textContent = state.meta;
      }
    }

    function showModal(form) {
      if (!overlay) {
        return;
      }

      root.classList.add('is-cloud-busy');
      overlay.classList.add('is-visible');
      overlay.setAttribute('aria-hidden', 'false');
      window.scrollTo({ top: 0, behavior: 'smooth' });
      overlay.focus({ preventScroll: true });
      setModalState({
        title: form && form.dataset.progressTitle ? form.dataset.progressTitle : 'Enviando para nuvem',
        message: form && form.dataset.progressMessage ? form.dataset.progressMessage : 'Validando a acao e preparando o envio.',
        stage: form && form.dataset.progressStage ? form.dataset.progressStage : 'Preparando',
        percent: 12,
        meta: 'Aguardando atualizacao da rotina de backup em nuvem.'
      });
    }

    function hideModalSoon() {
      window.setTimeout(function () {
        if (!overlay) {
          return;
        }
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-hidden', 'true');
        root.classList.remove('is-cloud-busy');
      }, 650);
    }

    function stopCloudPolling() {
      if (cloudPollTimer !== null) {
        window.clearInterval(cloudPollTimer);
        cloudPollTimer = null;
      }
    }

    function progressEndpoint(form, progressId) {
      var action = String((form.querySelector('input[name="action"]') || {}).value || '');
      var formAction = String(form.getAttribute('action') || '');
      var endpoint = new URL(systemActionUrl, window.location.origin);
      if (formAction.indexOf('/local/conteudo') !== -1 || action === 'verify' && form.querySelector('input[name="package_id"]')) {
        endpoint = new URL(editorialActionUrl, window.location.origin);
        endpoint.searchParams.set('content_progress', '1');
      } else {
        endpoint.searchParams.set('backup_progress', '1');
      }
      endpoint.searchParams.set('id', progressId);

      return endpoint.toString();
    }

    function pollCloudProgress(form, progressId) {
      stopCloudPolling();
      cloudPollTimer = window.setInterval(function () {
        fetch(progressEndpoint(form, progressId), {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
          .then(function (response) {
            return response.ok ? response.json() : null;
          })
          .then(function (payload) {
            if (!payload) {
              return;
            }
            setModalState({
              title: payload.title || (form.dataset.progressTitle || 'Enviando para nuvem'),
              message: payload.message || (form.dataset.progressMessage || 'Processando rotina na nuvem.'),
              stage: payload.stage || payload.status || (form.dataset.progressStage || 'Processando'),
              percent: payload.percent || 18,
              file: payload.file || null,
              meta: payload.file
                ? 'Rotina: ' + Math.max(0, Math.min(100, Number(payload.percent || 0))) + '%. Ultima atualizacao: ' + (payload.updated_at || '-')
                : (payload.updated_at ? 'Ultima atualizacao: ' + payload.updated_at : 'Aguardando nova etapa da rotina.')
            });
          })
          .catch(function () {
            // Mantem a ultima etapa visivel ate a acao principal responder.
          });
      }, 900);
    }

    function responseMessage(text, fallback) {
      if (!text) {
        return fallback;
      }

      try {
        var payload = JSON.parse(text);
        return payload.message || fallback;
      } catch (error) {
        return text.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220) || fallback;
      }
    }

    root.addEventListener('submit', function (event) {
      var form = event.target;
      if (!(form instanceof HTMLFormElement) || !form.classList.contains('cloud-action-form')) {
        return;
      }

      if (root.classList.contains('is-cloud-busy')) {
        event.preventDefault();
        return;
      }

      var actionName = String((form.querySelector('input[name="action"]') || {}).value || '');
      if (actionName === 'dropbox_connect') {
        showModal(form);
        return;
      }

      event.preventDefault();
      var progressId = 'cloud_' + Date.now().toString(36) + '_' + Math.random().toString(16).slice(2, 10);
      var formData = new FormData(form);
      formData.set('response', 'json');
      formData.set('progress_id', progressId);

      showModal(form);
      pollCloudProgress(form, progressId);

      fetch(form.getAttribute('action') || window.location.href, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (response) {
          return response.text().then(function (text) {
            var payload = null;
            try {
              payload = text ? JSON.parse(text) : null;
            } catch (error) {
              payload = null;
            }
            if (!response.ok || !payload) {
              throw new Error(payload && payload.message ? payload.message : responseMessage(text, 'Nao foi possivel concluir a rotina de nuvem.'));
            }
            return payload;
          });
        })
        .then(function (payload) {
          stopCloudPolling();
          setModalState({
            title: payload.ok ? 'Rotina concluida' : 'Falha na rotina',
            message: payload.message || 'Rotina de nuvem concluida.',
            stage: payload.ok ? 'Atualizando painel' : 'Falha',
            percent: 100,
            meta: 'Atualizando a tela com o resultado final.'
          });
          window.setTimeout(function () {
            window.location.href = new URL(payload.redirect_url || window.location.href, window.location.origin).toString();
          }, 450);
        })
        .catch(function (error) {
          stopCloudPolling();
          setModalState({
            title: 'Falha na rotina',
            message: error && error.message ? error.message : 'Nao foi possivel concluir a rotina de nuvem.',
            stage: 'Erro',
            percent: 100,
            meta: 'A rotina retornou erro. Revise a mensagem antes de tentar novamente.'
          });
          hideModalSoon();
        });
    });

    function escapeHtml(value) {
      return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function valueForSort(row, sort) {
      if (sort === 'date') return Number(row.timestamp || 0);
      if (sort === 'size') return Number(row.size_bytes || 0);
      if (sort === 'files') return Number(row.files || 0);
      return String(row[sort] || '').toLowerCase();
    }

    function badge(row) {
      var success = row.tone === 'success';
      var classes = success
        ? 'border-emerald-400/25 bg-emerald-500/10 text-emerald-200'
        : 'border-slate-700 bg-slate-900/80 text-slate-300';
      return '<span class="inline-flex items-center rounded-full border px-3.5 py-1.5 text-[11px] font-black uppercase tracking-[0.14em] shadow-[0_0_18px_rgba(15,23,42,0.22)] ' + classes + '">' + escapeHtml(row.status || 'Pendente') + '</span>';
    }

    function hiddenInput(name, value) {
      return '<input type="hidden" name="' + escapeHtml(name) + '" value="' + escapeHtml(value || '') + '">';
    }

    function actionButton(label, tone) {
      var tones = {
        verify: 'border-emerald-400/35 bg-emerald-500/10 text-emerald-100 hover:border-emerald-300 hover:bg-emerald-500/20',
        upload: 'border-sky-400/35 bg-sky-500/10 text-sky-100 hover:border-sky-300 hover:bg-sky-500/20',
        delete: 'border-rose-400/30 bg-transparent text-rose-200 hover:bg-rose-500/10'
      };
      return '<button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border px-3 py-2 text-xs font-black transition ' + (tones[tone] || tones.verify) + '">' + escapeHtml(label) + '</button>';
    }

    function postForm(actionUrl, fields, buttonHtml, wrapperClass, progress) {
      var inputs = Object.keys(fields || {}).map(function (name) {
        return hiddenInput(name, fields[name]);
      }).join('');
      var progressAttrs = progress
        ? ' data-progress-title="' + escapeHtml(progress.title || '') + '" data-progress-message="' + escapeHtml(progress.message || '') + '" data-progress-stage="' + escapeHtml(progress.stage || '') + '"'
        : '';
      return '<form method="POST" action="' + escapeHtml(actionUrl) + '" class="' + escapeHtml(wrapperClass || 'cloud-action-form inline-flex w-full') + '"' + progressAttrs + '>'
        + csrfField
        + hiddenInput('redirect_to', cloudReturnTarget)
        + inputs
        + buttonHtml
        + '</form>';
    }

    function deleteForm(actionUrl, fields, label, progress) {
      var inputs = Object.keys(fields || {}).map(function (name) {
        return hiddenInput(name, fields[name]);
      }).join('');
      var progressAttrs = progress
        ? ' data-progress-title="' + escapeHtml(progress.title || '') + '" data-progress-message="' + escapeHtml(progress.message || '') + '" data-progress-stage="' + escapeHtml(progress.stage || '') + '"'
        : '';
      return '<form method="POST" action="' + escapeHtml(actionUrl) + '" class="cloud-action-form rounded-xl border border-rose-400/25 bg-rose-500/5 p-2"' + progressAttrs + '>'
        + csrfField
        + hiddenInput('redirect_to', cloudReturnTarget)
        + inputs
        + '<input type="text" name="delete_confirmation" placeholder="Confirmar ID para excluir" class="w-full rounded-lg border border-slate-700 bg-slate-950/80 px-2 py-2 text-xs text-white outline-none focus:border-rose-400">'
        + '<div class="mt-2">' + actionButton(label, 'delete') + '</div>'
        + '</form>';
    }

    function actions(row) {
      var id = String(row.id || '');
      var sent = String(row.status || '').toLowerCase() === 'enviado';
      var html = '<div class="flex min-w-[15rem] flex-col gap-2">';

      if (row.type_key === 'system') {
        html += postForm(systemActionUrl, { action: 'verify', backup_id: id }, actionButton('Verificar', 'verify'), null, {
          title: 'Verificando backup sistemico',
          message: 'Conferindo manifesto, banco, uploads e arquivos do sistema.',
          stage: 'Verificacao'
        });
        html += sent
          ? deleteForm(systemActionUrl, { action: 'dropbox_delete_backup', backup_id: id }, 'Excluir da nuvem', {
            title: 'Excluindo backup da nuvem',
            message: 'Solicitando remocao segura do backup selecionado no Dropbox.',
            stage: 'Exclusao Dropbox'
          })
          : postForm(systemActionUrl, { action: 'dropbox_upload_backup', backup_id: id }, actionButton('Enviar nuvem', 'upload'), null, {
            title: 'Enviando backup para nuvem',
            message: 'Validando conexao Dropbox e preparando o backup selecionado para envio.',
            stage: 'Envio para nuvem'
          });
      } else {
        html += postForm(editorialActionUrl, { action: 'verify', package_id: id }, actionButton('Verificar', 'verify'), null, {
          title: 'Verificando pacote editorial',
          message: 'Conferindo manifesto, JSONs e uploads do pacote selecionado.',
          stage: 'Verificacao editorial'
        });
        html += sent
          ? deleteForm(cloudActionUrl, { action: 'dropbox_delete_editorial_package', package_id: id }, 'Excluir da nuvem', {
            title: 'Excluindo pacote da nuvem',
            message: 'Solicitando remocao segura do pacote editorial no Dropbox.',
            stage: 'Exclusao Dropbox'
          })
          : postForm(cloudActionUrl, { action: 'dropbox_upload_editorial_package', package_id: id }, actionButton('Enviar nuvem', 'upload'), null, {
            title: 'Enviando pacote editorial',
            message: 'Validando conexao Dropbox e preparando o pacote editorial para envio.',
            stage: 'Envio para nuvem'
          });
      }

      return html + '</div>';
    }

    function renderHistory() {
      var body = root.querySelector('[data-cloud-history-body]');
      if (!body) return;

      var rows = historyRows.slice().filter(function (row) {
        if (historyState.environment && String(row.profile_key || '') !== historyState.environment) return false;
        if (historyState.status && String(row.status || '').toLowerCase() !== historyState.status) return false;
        if (historyState.search) {
          var haystack = String((row.type || '') + ' ' + (row.profile || '') + ' ' + (row.id || '')).toLowerCase();
          if (haystack.indexOf(historyState.search.toLowerCase()) === -1) return false;
        }
        return true;
      }).sort(function (a, b) {
        var left = valueForSort(a, historyState.sort);
        var right = valueForSort(b, historyState.sort);
        var result = left > right ? 1 : (left < right ? -1 : 0);
        return historyState.dir === 'asc' ? result : -result;
      });
      var total = rows.length;
      var pages = Math.max(1, Math.ceil(total / Math.max(1, historyState.perPage)));
      historyState.page = Math.max(1, Math.min(historyState.page, pages));
      var offset = (historyState.page - 1) * historyState.perPage;
      var pageRows = rows.slice(offset, offset + historyState.perPage);
      var summary = root.querySelector('[data-cloud-history-count-summary]');
      var first = total > 0 ? offset + 1 : 0;
      var last = total > 0 ? Math.min(total, offset + historyState.perPage) : 0;

      if (summary) {
        summary.innerHTML = total > 0
          ? 'Exibindo <span class="font-black text-white">' + first.toLocaleString('pt-BR') + '</span> ate <span class="font-black text-white">' + last.toLocaleString('pt-BR') + '</span> de <span class="font-black text-white">' + total.toLocaleString('pt-BR') + '</span> registros'
          : 'Nenhum envio para paginar no momento.';
      }

      body.innerHTML = pageRows.map(function (row) {
        return '<tr class="text-slate-300 transition hover:bg-slate-900/70">'
          + '<td class="whitespace-nowrap px-4 py-3 text-xs font-semibold text-slate-400"><span class="block text-slate-200">' + escapeHtml(row.date || '-') + '</span><span class="mt-1 block text-[11px] text-slate-500">' + escapeHtml(row.created_at || '-') + '</span></td>'
          + '<td class="whitespace-nowrap px-4 py-3">' + escapeHtml(row.profile || '-') + '</td>'
          + '<td class="whitespace-nowrap px-4 py-3 font-black text-white">' + escapeHtml(row.type || '-') + '</td>'
          + '<td class="whitespace-nowrap px-4 py-3 font-semibold text-cyan-100">' + escapeHtml(row.id || '-') + '</td>'
          + '<td class="whitespace-nowrap px-4 py-3">' + escapeHtml(row.content || '-') + '<div class="mt-1 text-xs text-slate-500">' + escapeHtml(row.files || '-') + ' arquivo(s)</div></td>'
          + '<td class="whitespace-nowrap px-4 py-3">' + escapeHtml(row.size || '-') + '</td>'
          + '<td class="whitespace-nowrap px-4 py-3">' + badge(row) + '</td>'
          + '<td class="px-4 py-3">' + actions(row) + '</td>'
          + '</tr>';
      }).join('') || '<tr><td colspan="8" class="px-4 py-8 text-center text-sm font-semibold text-slate-400">Nenhum envio encontrado.</td></tr>';
    }

    function activate(name) {
      tabs.forEach(function (tab) {
        var active = tab.getAttribute('data-cloud-tab') === name;
        tab.classList.toggle('border-cyan-400/45', active);
        tab.classList.toggle('bg-cyan-500/10', active);
        tab.classList.toggle('text-cyan-100', active);
        tab.classList.toggle('shadow-[0_0_22px_rgba(34,211,238,0.12)]', active);
        tab.classList.toggle('border-slate-800', !active);
        tab.classList.toggle('bg-slate-900/70', !active);
        tab.classList.toggle('text-slate-300', !active);
      });

      panels.forEach(function (panel) {
        panel.classList.toggle('hidden', panel.getAttribute('data-cloud-tab-panel') !== name);
      });
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function (event) {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        var name = tab.getAttribute('data-cloud-tab') || 'overview';
        activate(name);
        if (tab instanceof HTMLAnchorElement) {
          window.history.pushState({}, '', tab.href);
        }
      });
    });

    activate('<?= $initialCloudTab === 'history' ? 'history' : 'overview' ?>');
      renderHistory();

    var filterForm = root.querySelector('[data-cloud-history-filters]');
    if (filterForm) {
      filterForm.addEventListener('submit', function (event) {
        event.preventDefault();
        historyState.search = String((root.querySelector('[data-cloud-filter-search]') || {}).value || '').trim();
        historyState.environment = String((root.querySelector('[data-cloud-filter-environment]') || {}).value || '');
        historyState.status = String((root.querySelector('[data-cloud-filter-status]') || {}).value || '');
        historyState.page = 1;
        activate('history');
        renderHistory();
      });
      filterForm.addEventListener('change', function () {
        historyState.search = String((root.querySelector('[data-cloud-filter-search]') || {}).value || '').trim();
        historyState.environment = String((root.querySelector('[data-cloud-filter-environment]') || {}).value || '');
        historyState.status = String((root.querySelector('[data-cloud-filter-status]') || {}).value || '');
        historyState.page = 1;
        renderHistory();
      });
      filterForm.addEventListener('input', function (event) {
        if (!event.target.matches('[data-cloud-filter-search]')) return;
        historyState.search = String(event.target.value || '').trim();
        historyState.page = 1;
        renderHistory();
      });
    }

    root.addEventListener('click', function (event) {
      var sort = event.target.closest('[data-cloud-history-sort]');
      var perPage = event.target.closest('[data-cloud-history-per-page]');
      var page = event.target.closest('[data-cloud-history-page]');
      var prev = event.target.closest('[data-cloud-history-prev]');
      var next = event.target.closest('[data-cloud-history-next]');

      if (!sort && !perPage && !page && !prev && !next) {
        return;
      }

      event.preventDefault();
      activate('history');

      if (sort) {
        var column = sort.getAttribute('data-cloud-history-sort') || 'date';
        historyState.dir = historyState.sort === column && historyState.dir === 'asc' ? 'desc' : 'asc';
        historyState.sort = column;
        historyState.page = 1;
      } else if (perPage) {
        historyState.perPage = Number(perPage.getAttribute('data-cloud-history-per-page') || 10);
        historyState.page = 1;
      } else if (page) {
        historyState.page = Number(page.getAttribute('data-cloud-history-page') || 1);
      } else if (prev) {
        historyState.page = Math.max(1, historyState.page - 1);
      } else if (next) {
        historyState.page += 1;
      }

      renderHistory();
    });
  });
  </script>
</section>
