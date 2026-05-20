<?php

declare(strict_types=1);

use App\Support\Csrf;

$backupStatus = (array) ($backup_status ?? []);
$compactCopy = (bool) ($embed_mode ?? false);
$items = (array) ($backupStatus['items'] ?? []);
$pendingCloudItems = array_values(array_filter($items, static fn ($item): bool => is_array($item) && (($item['cloud_uploaded'] ?? false) !== true)));
$running = $backupStatus['running'] ?? null;
$lastVerification = is_array($last_verification ?? null) ? $last_verification : null;
$localReady = (bool) ($local_ready ?? true);
$stageReady = (bool) ($stage_ready ?? false);
$productionReady = (bool) ($production_ready ?? false);
$backupSection = (string) ($backup_section ?? 'resumo');
$historyPagination = is_array($backup_history_pagination ?? null) ? $backup_history_pagination : ['total' => count($items), 'page' => 1, 'per_page' => 5, 'pages' => 1];
$historyPage = max(1, (int) ($historyPagination['page'] ?? 1));
$historyPerPage = max(5, (int) ($historyPagination['per_page'] ?? 5));
$historyPages = max(1, (int) ($historyPagination['pages'] ?? 1));
$historyTotal = max(0, (int) ($historyPagination['total'] ?? count($items)));
$historyFirstItem = $historyTotal > 0 ? (($historyPage - 1) * $historyPerPage) + 1 : 0;
$historyLastItem = $historyTotal > 0 ? min($historyTotal, (($historyPage - 1) * $historyPerPage) + count($items)) : 0;
$historyBaseUrl = (string) ($backup_base_url ?? url('/local/backup'));
$historyBasePath = parse_url($historyBaseUrl, PHP_URL_PATH);
$historyBaseQuery = parse_url($historyBaseUrl, PHP_URL_QUERY);
$usesPrettyBackupSections = is_string($historyBasePath)
    && str_contains($historyBasePath, '/central-operacional-v2/backup-sistemico')
    && ($historyBaseQuery === null || $historyBaseQuery === false || $historyBaseQuery === '');
$historySearch = trim((string) ($_GET['backup_busca'] ?? ''));
$historyEnvironment = strtolower(trim((string) ($_GET['backup_ambiente'] ?? '')));
$historyFilterStatus = strtolower(trim((string) ($_GET['backup_status'] ?? '')));
$historySort = strtolower(trim((string) ($_GET['backup_ordem'] ?? 'data_desc')));
$allowedHistoryEnvironments = ['', 'local', 'stage', 'production'];
$allowedHistoryStatuses = ['', 'valido', 'falhou', 'nuvem', 'pendente'];
$allowedHistorySorts = ['data_desc', 'data_asc', 'ambiente', 'tamanho_desc', 'tamanho_asc'];
if (!in_array($historyEnvironment, $allowedHistoryEnvironments, true)) {
    $historyEnvironment = '';
}
if (!in_array($historyFilterStatus, $allowedHistoryStatuses, true)) {
    $historyFilterStatus = '';
}
if (!in_array($historySort, $allowedHistorySorts, true)) {
    $historySort = 'data_desc';
}
$historyBuildUrl = static function (int $page, ?int $perPage = null) use ($historyBaseUrl, $historyPerPage, $historySearch, $historyEnvironment, $historyFilterStatus, $historySort, $usesPrettyBackupSections): string {
    $query = [
        'backup_pagina' => max(1, $page),
        'backup_por_pagina' => $perPage ?? $historyPerPage,
        'backup_busca' => $historySearch,
        'backup_ambiente' => $historyEnvironment,
        'backup_status' => $historyFilterStatus,
        'backup_ordem' => $historySort,
    ];
    if (!$usesPrettyBackupSections) {
        $query = ['backup_secao' => 'historico'] + $query;
    }
    $query = array_filter($query, static fn ($value): bool => !($value === '' || $value === null));
    $base = $usesPrettyBackupSections ? rtrim($historyBaseUrl, '/') . '/historico' : $historyBaseUrl;
    $separator = str_contains($base, '?') ? '&' : '?';

    return $base . $separator . http_build_query($query);
};
$currentReturnUrl = $backupSection === 'historico'
    ? $historyBuildUrl($historyPage, $historyPerPage)
    : ($usesPrettyBackupSections
        ? rtrim($historyBaseUrl, '/') . '/' . rawurlencode($backupSection)
        : ($historyBaseUrl . (str_contains($historyBaseUrl, '?') ? '&' : '?') . 'backup_secao=' . rawurlencode($backupSection)));
$currentReturnPath = (string) parse_url($currentReturnUrl, PHP_URL_PATH);
$currentReturnQuery = (string) parse_url($currentReturnUrl, PHP_URL_QUERY);
$currentReturnTarget = $currentReturnPath . ($currentReturnQuery !== '' ? '?' . $currentReturnQuery : '');
$historyStart = max(1, $historyPage - 2);
$historyEnd = min($historyPages, $historyPage + 2);
if (($historyEnd - $historyStart) < 4) {
    $historyStart = max(1, $historyEnd - 4);
    $historyEnd = min($historyPages, $historyStart + 4);
}

$latestByProfile = [
    'local' => null,
    'stage' => null,
    'production' => null,
];

foreach ($items as $backupItem) {
    $profileKey = strtolower((string) ($backupItem['profile'] ?? ''));
    if (array_key_exists($profileKey, $latestByProfile) && $latestByProfile[$profileKey] === null) {
        $latestByProfile[$profileKey] = $backupItem;
    }
}

$backupCloud = is_array($backup_cloud ?? null) ? $backup_cloud : [];
$cloudSpaceUsage = is_array($backupCloud['space_usage'] ?? null) ? $backupCloud['space_usage'] : [];
$backupCloudRecentUploads = is_array($backup_cloud_recent_uploads ?? null) ? $backup_cloud_recent_uploads : [];
$backupCloudPagination = is_array($backup_cloud_pagination ?? null) ? $backup_cloud_pagination : ['total' => count($backupCloudRecentUploads), 'page' => 1, 'per_page' => 5, 'pages' => 1];
$backupCloudTab = (string) ($backup_cloud_tab ?? 'painel');
$cloudPage = max(1, (int) ($backupCloudPagination['page'] ?? 1));
$cloudPerPage = max(5, (int) ($backupCloudPagination['per_page'] ?? 5));
$cloudPages = max(1, (int) ($backupCloudPagination['pages'] ?? 1));
$cloudTotal = max(0, (int) ($backupCloudPagination['total'] ?? count($backupCloudRecentUploads)));
$cloudFirstItem = $cloudTotal > 0 ? (($cloudPage - 1) * $cloudPerPage) + 1 : 0;
$cloudLastItem = $cloudTotal > 0 ? min($cloudTotal, (($cloudPage - 1) * $cloudPerPage) + count($backupCloudRecentUploads)) : 0;
$cloudBaseUrl = (string) ($backup_base_url ?? url('/local/backup'));
$cloudBuildUrl = static function (int $page, ?int $perPage = null) use ($cloudBaseUrl, $cloudPerPage): string {
    $separator = str_contains($cloudBaseUrl, '?') ? '&' : '?';

    return $cloudBaseUrl
        . $separator
        . 'backup_secao=nuvem&cloud_tab=historico&cloud_pagina=' . max(1, $page)
        . '&cloud_por_pagina=' . ($perPage ?? $cloudPerPage);
};
$cloudCurrentReturnUrl = $backupCloudTab === 'historico'
    ? $cloudBuildUrl($cloudPage, $cloudPerPage)
    : ($cloudBaseUrl . (str_contains($cloudBaseUrl, '?') ? '&' : '?') . 'backup_secao=nuvem&cloud_tab=' . rawurlencode($backupCloudTab));
$cloudCurrentReturnPath = (string) parse_url($cloudCurrentReturnUrl, PHP_URL_PATH);
$cloudCurrentReturnQuery = (string) parse_url($cloudCurrentReturnUrl, PHP_URL_QUERY);
$cloudCurrentReturnTarget = $cloudCurrentReturnPath . ($cloudCurrentReturnQuery !== '' ? '?' . $cloudCurrentReturnQuery : '');
$cloudStart = max(1, $cloudPage - 2);
$cloudEnd = min($cloudPages, $cloudPage + 2);
if (($cloudEnd - $cloudStart) < 4) {
    $cloudStart = max(1, $cloudEnd - 4);
    $cloudEnd = min($cloudPages, $cloudStart + 4);
}

$parseSize = static function (string $value): float {
    if (!preg_match('/([\d\.,]+)\s*([KMGT]?B)/i', $value, $matches)) {
        return 0.0;
    }

    $number = (float) str_replace(',', '.', str_replace('.', '', $matches[1]));
    $unit = strtoupper($matches[2]);
    $factor = match ($unit) {
        'KB' => 1024,
        'MB' => 1024 ** 2,
        'GB' => 1024 ** 3,
        'TB' => 1024 ** 4,
        default => 1,
    };

    return $number * $factor;
};

$relativeTime = static function (string $value): string {
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return 'sem leitura relativa';
    }

    $diff = max(0, time() - $timestamp);
    if ($diff < 60) {
        return 'agora ha pouco';
    }
    if ($diff < 3600) {
        return 'ha ' . (int) floor($diff / 60) . ' min';
    }
    if ($diff < 86400) {
        return 'ha ' . (int) floor($diff / 3600) . ' h';
    }

    return 'ha ' . (int) floor($diff / 86400) . ' dia(s)';
};

$historyVisibleItems = array_values(array_filter($items, static function ($item) use ($historySearch, $historyEnvironment, $historyFilterStatus): bool {
    if (!is_array($item)) {
        return false;
    }

    $profile = strtolower((string) ($item['profile'] ?? ''));
    if ($historyEnvironment !== '' && $profile !== $historyEnvironment) {
        return false;
    }

    if ($historySearch !== '') {
        $haystack = strtolower((string) ($item['backup_id'] ?? '') . ' ' . (string) ($item['profile_label'] ?? '') . ' ' . $profile);
        if (!str_contains($haystack, strtolower($historySearch))) {
            return false;
        }
    }

    $valid = (bool) ($item['is_valid'] ?? false);
    $cloud = (bool) ($item['cloud_uploaded'] ?? false);
    return match ($historyFilterStatus) {
        'valido' => $valid,
        'falhou' => !$valid,
        'nuvem' => $cloud,
        'pendente' => !$cloud,
        default => true,
    };
}));

usort($historyVisibleItems, static function (array $a, array $b) use ($historySort, $parseSize): int {
    return match ($historySort) {
        'data_asc' => strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? '')),
        'ambiente' => strcmp((string) ($a['profile_label'] ?? ''), (string) ($b['profile_label'] ?? '')),
        'tamanho_asc' => $parseSize((string) ($a['total_size'] ?? '')) <=> $parseSize((string) ($b['total_size'] ?? '')),
        'tamanho_desc' => $parseSize((string) ($b['total_size'] ?? '')) <=> $parseSize((string) ($a['total_size'] ?? '')),
        default => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')),
    };
});

$historySummary = [
    'total' => count($items),
    'valid' => count(array_filter($items, static fn ($item): bool => is_array($item) && (bool) ($item['is_valid'] ?? false))),
    'cloud' => count(array_filter($items, static fn ($item): bool => is_array($item) && (bool) ($item['cloud_uploaded'] ?? false))),
    'production' => count(array_filter($items, static fn ($item): bool => is_array($item) && strtolower((string) ($item['profile'] ?? '')) === 'production')),
];
?>
<section data-backup-tools-panel class="space-y-6">
  <?php if ($backupSection === 'resumo'): ?>
    <?php if (is_array($running)): ?>
      <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
        <strong class="font-semibold">Backup em execucao:</strong>
        <?= htmlspecialchars((string) ($running['profile_label'] ?? 'Rotina ativa'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        <?php if (!empty($running['started_at'])): ?>
          <span class="text-amber-200/90">desde <?= htmlspecialchars((string) $running['started_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="grid gap-4 xl:grid-cols-4">
      <?php foreach ([
          'local' => ['label' => 'Ambiente local', 'empty' => 'Nenhum backup local criado ainda.', 'pending' => 'A rotina de backup local ja pode ser executada.'],
          'stage' => ['label' => 'Ambiente stage', 'empty' => 'A rotina de backup da stage ja pode ser executada.', 'pending' => 'Complete as variaveis CONTENT_SYNC_STAGE_* para habilitar o backup da stage.'],
          'production' => ['label' => 'Ambiente producao', 'empty' => 'A rotina de backup da producao ja pode ser executada.', 'pending' => 'Preencha as variaveis BACKUP_PRODUCTION_* no .env antes de usar backup remoto.'],
      ] as $profileKey => $profileMeta): ?>
        <?php $entry = $latestByProfile[$profileKey]; ?>
        <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
          <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80"><?= htmlspecialchars($profileMeta['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
          <div class="mt-4 text-sm text-slate-300">
            <?php if (is_array($entry)): ?>
              <div class="font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars((string) ($entry['backup_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <?php if (!$compactCopy): ?>
                <div class="mt-1 text-slate-400"><?= ($entry['includes_uploads'] ?? true) ? 'Ultimo backup: banco, uploads e arquivos do sistema.' : 'Ultimo backup leve: banco e arquivos do sistema, sem uploads.' ?></div>
              <?php endif; ?>
              <div class="mt-4 grid gap-2 text-sm">
                <div class="flex items-center justify-between"><span class="text-slate-500">Validade</span><span class="<?= ($entry['is_valid'] ?? false) ? 'text-emerald-300' : 'text-rose-300' ?>"><?= ($entry['is_valid'] ?? false) ? 'OK' : 'Falhou' ?></span></div>
                <div class="flex items-center justify-between"><span class="text-slate-500">Banco</span><span><?= htmlspecialchars((string) ($entry['database_size'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                <div class="flex items-center justify-between"><span class="text-slate-500">Uploads</span><span><?= htmlspecialchars((string) ($entry['uploads_size'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                <div class="flex items-center justify-between"><span class="text-slate-500">Sistema</span><span><?= htmlspecialchars((string) ($entry['system_files_size'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                <div class="flex items-center justify-between"><span class="text-slate-500">Pacote</span><span><?= htmlspecialchars((string) ($entry['total_size'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
              </div>
            <?php else: ?>
              <?php
              $isReady = match ($profileKey) {
                  'stage' => $stageReady,
                  'production' => $productionReady,
                  default => $localReady,
              };
              ?>
              <div class="font-rajdhani text-2xl font-bold <?= $isReady ? 'text-white' : 'text-amber-300' ?>"><?= $isReady ? 'Sem backup' : 'Pendente' ?></div>
              <div class="mt-1 text-slate-400"><?= htmlspecialchars($isReady ? $profileMeta['empty'] : $profileMeta['pending'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Prontidao</p>
        <div class="mt-4 text-sm text-slate-300">
          <div class="font-rajdhani text-2xl font-bold text-white">3 ambientes</div>
          <?php if (!$compactCopy): ?>
            <div class="mt-1 text-slate-400">Confere se cada perfil ja esta pronto para executar a rotina de backup de ambiente.</div>
          <?php endif; ?>
          <div class="mt-4 grid gap-2 text-sm">
            <div class="flex items-center justify-between"><span class="text-slate-500">Local</span><span class="<?= $localReady ? 'text-emerald-300' : 'text-amber-300' ?>"><?= $localReady ? 'Pronto' : 'Pendente' ?></span></div>
            <div class="flex items-center justify-between"><span class="text-slate-500">Stage</span><span class="<?= $stageReady ? 'text-emerald-300' : 'text-amber-300' ?>"><?= $stageReady ? 'Pronto' : 'Pendente' ?></span></div>
            <div class="flex items-center justify-between"><span class="text-slate-500">Producao</span><span class="<?= $productionReady ? 'text-emerald-300' : 'text-amber-300' ?>"><?= $productionReady ? 'Pronto' : 'Pendente' ?></span></div>
          </div>
        </div>
      </div>
    </div>
  <?php elseif ($backupSection === 'acoes'): ?>
    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <h2 class="font-orbitron text-lg font-bold text-white">Acoes de backup de ambiente</h2>
      <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <?php foreach ([
            'local' => ['label' => 'Backup local', 'message' => 'Estamos exportando banco e arquivos do sistema local. Uploads entram somente se a opcao estiver marcada.', 'button' => 'Executar agora', 'ready' => $localReady, 'button_class' => 'border-cyan-400/40 bg-cyan-500/10 text-cyan-200 hover:border-cyan-300 hover:bg-cyan-500/20'],
            'stage' => ['label' => 'Backup stage', 'message' => 'Estamos coletando banco e arquivos do sistema da stage. Uploads entram somente se a opcao estiver marcada.', 'button' => 'Executar stage', 'ready' => $stageReady, 'button_class' => 'border-sky-400/40 bg-sky-500/10 text-sky-200 hover:border-sky-300 hover:bg-sky-500/20'],
            'production' => ['label' => 'Backup producao', 'message' => 'Estamos coletando banco e arquivos do sistema da producao. Uploads entram somente se a opcao estiver marcada.', 'button' => 'Executar producao', 'ready' => $productionReady, 'button_class' => 'border-fuchsia-400/40 bg-fuchsia-500/10 text-fuchsia-200 hover:border-fuchsia-300 hover:bg-fuchsia-500/20'],
        ] as $profileKey => $meta): ?>
          <form method="POST" action="<?= url('/local/backup') ?>" class="backup-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-backup-async="true" data-progress-title="Executando <?= htmlspecialchars($meta['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-progress-message="<?= htmlspecialchars($meta['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-progress-stage="<?= htmlspecialchars($meta['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($currentReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <input type="hidden" name="action" value="run">
            <input type="hidden" name="profile" value="<?= htmlspecialchars($profileKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80"><?= htmlspecialchars($meta['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <?php if (!$compactCopy): ?>
              <p class="mt-2 text-sm text-slate-400">Gera backup de banco e sistema. Marque uploads apenas quando quiser incluir as midias no pacote.</p>
            <?php endif; ?>
            <label class="mt-4 flex items-start gap-3 rounded-xl border border-slate-800 bg-slate-900/70 p-3 text-sm text-slate-300">
              <input type="checkbox" name="include_uploads" value="1" class="mt-1 h-4 w-4 rounded border-slate-600 bg-slate-950 text-cyan-400 focus:ring-cyan-400">
              <span>
                <span class="block font-semibold text-white">Incluir pasta uploads</span>
                <span class="mt-1 block text-xs leading-5 text-slate-500">Use quando precisar de backup completo com midias. Desmarcado gera pacote menor.</span>
              </span>
            </label>
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border px-4 py-2 text-sm font-semibold transition <?= $meta['ready'] ? $meta['button_class'] : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= $meta['ready'] ? '' : 'disabled' ?>><?= htmlspecialchars($meta['button'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
          </form>
        <?php endforeach; ?>

        <form method="POST" action="<?= url('/local/backup') ?>" class="backup-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-backup-async="true" data-progress-title="Verificando backup" data-progress-message="Estamos conferindo o manifesto, os checksums e o pacote mais recente." data-progress-stage="Verificacao">
          <?= Csrf::field() ?>
          <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($currentReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          <input type="hidden" name="action" value="verify">
          <input type="hidden" name="backup_id" value="latest">
          <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Verificar ultimo</p>
          <?php if (!$compactCopy): ?>
            <p class="mt-2 text-sm text-slate-400">Confirma se o manifesto, o dump e os arquivos gerados estao integros. Uploads podem estar ausentes por escolha do backup.</p>
          <?php endif; ?>
          <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border border-emerald-400/40 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-200 transition hover:border-emerald-300 hover:bg-emerald-500/20">Verificar</button>
        </form>
      </div>
    </div>
  <?php elseif ($backupSection === 'restore'): ?>
    <div class="grid gap-6 xl:grid-cols-[1.1fr_1fr]">
      <div class="rounded-3xl border border-rose-500/20 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-bold text-white">Restore de ambiente completo</h2>
        <?php if (!$compactCopy): ?>
          <p class="mt-2 text-sm leading-7 text-slate-400">Use esta area so quando for realmente necessario voltar o ambiente. O restore aplica os blocos disponiveis no backup escolhido: banco, sistema e uploads quando o backup tiver sido gerado com midias. A confirmacao exige a frase <span class="font-semibold text-white">RESTAURAR</span>.</p>
        <?php endif; ?>

        <form method="POST" action="<?= url('/local/backup') ?>" class="backup-action-form mt-5 space-y-4" data-backup-async="true" data-progress-title="Executando restore" data-progress-message="Estamos aplicando o backup selecionado. Esse passo pode sobrescrever banco ou uploads." data-progress-stage="Restore">
          <?= Csrf::field() ?>
          <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($currentReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          <input type="hidden" name="action" value="restore">
          <input type="hidden" name="scope" value="all">

          <div>
            <label class="mb-2 block text-sm font-semibold text-slate-200">Backup</label>
            <select name="backup_id" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-cyan-400">
              <option value="latest">Ultimo valido</option>
              <?php foreach ($items as $item): ?>
                <option value="<?= htmlspecialchars((string) ($item['backup_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars((string) ($item['backup_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <label class="mb-2 block text-sm font-semibold text-slate-200">Destino</label>
              <select name="target_profile" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-cyan-400">
                <option value="local">Local</option>
                <option value="stage" <?= $stageReady ? '' : 'disabled' ?>>Stage</option>
                <option value="production" <?= $productionReady ? '' : 'disabled' ?>>Producao</option>
              </select>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-4">
              <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Escopo fixo</div>
              <div class="mt-2 text-sm font-semibold text-white">Banco + sistema + uploads se existirem</div>
              <?php if (!$compactCopy): ?>
                <div class="mt-1 text-xs text-slate-400">Backups sem uploads restauram somente banco e sistema.</div>
              <?php endif; ?>
            </div>
          </div>

          <div>
            <label class="mb-2 block text-sm font-semibold text-slate-200">Confirmacao</label>
            <input type="text" name="restore_phrase" placeholder="Digite RESTAURAR" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-rose-400">
          </div>

          <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-rose-400/40 bg-rose-500/10 px-5 py-3 text-sm font-semibold text-rose-200 transition hover:border-rose-300 hover:bg-rose-500/20">Executar restore</button>
        </form>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-bold text-white">Ultima verificacao</h2>
        <?php if ($lastVerification !== null): ?>
          <div class="mt-4 grid gap-4">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Backup</div>
              <div class="mt-2 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars((string) ($lastVerification['backup_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Banco</div>
              <div class="mt-2 text-sm text-white"><?= htmlspecialchars((string) ($lastVerification['database_verification']['message'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Uploads</div>
              <div class="mt-2 text-sm text-white"><?= htmlspecialchars((string) ($lastVerification['uploads_verification']['message'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Sistema</div>
              <div class="mt-2 text-sm text-white"><?= htmlspecialchars((string) ($lastVerification['system_files_verification']['message'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            </div>
          </div>
        <?php else: ?>
          <p class="mt-4 text-sm text-slate-400">Nenhuma verificacao executada nesta sessao.</p>
        <?php endif; ?>
      </div>
    </div>
  <?php elseif ($backupSection === 'historico'): ?>
    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
        <div>
          <h2 class="font-orbitron text-lg font-bold text-white">Backups recentes</h2>
          <?php if (!$compactCopy): ?>
            <p class="mt-1 text-sm text-slate-400">A lista abaixo mostra validade, tamanho e o estado dos pacotes de ambiente salvos localmente.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="mt-5 grid gap-3 md:grid-cols-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Total</div>
          <div class="mt-2 text-2xl font-black text-white"><?= (int) $historySummary['total'] ?></div>
        </div>
        <div class="rounded-2xl border border-emerald-500/25 bg-emerald-500/10 p-4">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-emerald-200/75">Validos</div>
          <div class="mt-2 text-2xl font-black text-white"><?= (int) $historySummary['valid'] ?></div>
        </div>
        <div class="rounded-2xl border border-sky-500/25 bg-sky-500/10 p-4">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-sky-200/75">Nuvem</div>
          <div class="mt-2 text-2xl font-black text-white"><?= (int) $historySummary['cloud'] ?></div>
        </div>
        <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-amber-200/75">Producao</div>
          <div class="mt-2 text-2xl font-black text-white"><?= (int) $historySummary['production'] ?></div>
        </div>
      </div>

      <form method="GET" action="<?= htmlspecialchars($usesPrettyBackupSections ? rtrim($historyBaseUrl, '/') . '/historico' : $historyBaseUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="mt-5 rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
        <?php if (!$usesPrettyBackupSections): ?>
          <input type="hidden" name="backup_secao" value="historico">
        <?php endif; ?>
        <div class="grid gap-3 xl:grid-cols-[1.2fr_0.8fr_0.8fr_0.8fr_auto]">
          <input type="search" name="backup_busca" value="<?= htmlspecialchars($historySearch, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" placeholder="Buscar backup..." class="min-h-11 rounded-xl border border-slate-700 bg-slate-950/80 px-4 text-sm text-white outline-none focus:border-cyan-400">
          <select name="backup_ambiente" class="min-h-11 rounded-xl border border-slate-700 bg-slate-950/80 px-4 text-sm text-white outline-none focus:border-cyan-400">
            <option value="">Todos ambientes</option>
            <option value="local" <?= $historyEnvironment === 'local' ? 'selected' : '' ?>>Local</option>
            <option value="stage" <?= $historyEnvironment === 'stage' ? 'selected' : '' ?>>Stage</option>
            <option value="production" <?= $historyEnvironment === 'production' ? 'selected' : '' ?>>Producao</option>
          </select>
          <select name="backup_status" class="min-h-11 rounded-xl border border-slate-700 bg-slate-950/80 px-4 text-sm text-white outline-none focus:border-cyan-400">
            <option value="">Todos status</option>
            <option value="valido" <?= $historyFilterStatus === 'valido' ? 'selected' : '' ?>>Validos</option>
            <option value="falhou" <?= $historyFilterStatus === 'falhou' ? 'selected' : '' ?>>Falhou</option>
            <option value="nuvem" <?= $historyFilterStatus === 'nuvem' ? 'selected' : '' ?>>Enviados nuvem</option>
            <option value="pendente" <?= $historyFilterStatus === 'pendente' ? 'selected' : '' ?>>Nuvem pendente</option>
          </select>
          <select name="backup_ordem" class="min-h-11 rounded-xl border border-slate-700 bg-slate-950/80 px-4 text-sm text-white outline-none focus:border-cyan-400">
            <option value="data_desc" <?= $historySort === 'data_desc' ? 'selected' : '' ?>>Mais recentes</option>
            <option value="data_asc" <?= $historySort === 'data_asc' ? 'selected' : '' ?>>Mais antigos</option>
            <option value="ambiente" <?= $historySort === 'ambiente' ? 'selected' : '' ?>>Ambiente</option>
            <option value="tamanho_desc" <?= $historySort === 'tamanho_desc' ? 'selected' : '' ?>>Maior pacote</option>
            <option value="tamanho_asc" <?= $historySort === 'tamanho_asc' ? 'selected' : '' ?>>Menor pacote</option>
          </select>
          <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-cyan-400/35 bg-cyan-500/10 px-4 text-xs font-black uppercase tracking-[0.12em] text-cyan-100 transition hover:border-cyan-300 hover:bg-cyan-500/18">Filtrar</button>
        </div>
      </form>

      <div class="mt-5 overflow-x-auto">
        <table class="min-w-full border-separate border-spacing-y-3 text-sm text-slate-200">
          <thead>
            <tr class="text-left font-orbitron text-[10px] uppercase tracking-[0.18em] text-slate-500">
              <th class="px-4 py-2">Data / Identificador</th>
              <th class="px-4 py-2">Ambiente</th>
              <th class="px-4 py-2">Conteudo / Tamanho</th>
              <th class="px-4 py-2">Status</th>
              <th class="px-4 py-2 text-right">Acoes</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($historyVisibleItems as $item): ?>
              <?php
                $isProductionBackup = strtolower((string) ($item['profile'] ?? '')) === 'production';
                $isValidBackup = (bool) ($item['is_valid'] ?? false);
                $isCloudBackup = (bool) ($item['cloud_uploaded'] ?? false);
                $createdAt = (string) ($item['created_at'] ?? '-');
              ?>
              <tr class="<?= $isProductionBackup ? 'outline outline-1 outline-amber-400/30' : '' ?> rounded-2xl border border-slate-800 bg-slate-950/70 transition hover:bg-slate-950">
                <td class="rounded-l-2xl border-y border-l border-slate-800 px-4 py-4 align-top">
                  <div class="font-semibold text-white"><?= htmlspecialchars((string) ($item['backup_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars($createdAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <div class="mt-1 text-[11px] text-cyan-300/70"><?= htmlspecialchars($relativeTime($createdAt), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </td>
                <td class="border-y border-slate-800 px-4 py-4 align-top">
                  <span class="inline-flex rounded-full border px-3 py-1 text-xs font-black <?= $isProductionBackup ? 'border-amber-400/35 bg-amber-500/10 text-amber-100' : 'border-slate-700 bg-slate-900/80 text-slate-200' ?>">
                    <?= htmlspecialchars((string) ($item['profile_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                  </span>
                  <div class="mt-2 text-xs text-slate-500"><?= htmlspecialchars((string) ($item['profile'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </td>
                <td class="border-y border-slate-800 px-4 py-4 align-top">
                  <div class="font-black text-white"><?= htmlspecialchars((string) ($item['total_size'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <div class="mt-2 grid gap-1 text-xs text-slate-500">
                    <div>Banco: <span class="text-slate-300"><?= htmlspecialchars((string) ($item['database_size'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                    <div>Uploads: <span class="text-slate-300"><?= htmlspecialchars((string) ($item['uploads_size'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                    <div>Sistema: <span class="text-slate-300"><?= htmlspecialchars((string) ($item['system_files_size'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                  </div>
                </td>
                <td class="border-y border-slate-800 px-4 py-4 align-top">
                  <div class="flex flex-wrap gap-2">
                    <span class="inline-flex rounded-full border px-3 py-1 text-[11px] font-black uppercase tracking-[0.12em] <?= $isValidBackup ? 'border-emerald-400/30 bg-emerald-500/10 text-emerald-200' : 'border-rose-400/30 bg-rose-500/10 text-rose-200' ?>"><?= $isValidBackup ? 'Valido' : 'Falhou' ?></span>
                  </div>
                </td>
                <td class="rounded-r-2xl border-y border-r border-slate-800 px-4 py-4 align-top">
                  <div class="flex min-w-[18rem] flex-col items-stretch gap-2 md:items-end">
                    <div class="grid w-full max-w-xs gap-2">
                      <form method="POST" action="<?= url('/local/backup') ?>" class="backup-action-form" data-backup-async="true" data-progress-title="Verificando backup" data-progress-message="Estamos conferindo a integridade do pacote selecionado." data-progress-stage="Verificacao">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($currentReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="verify">
                        <input type="hidden" name="backup_id" value="<?= htmlspecialchars((string) ($item['backup_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-200 transition hover:bg-emerald-500/20">Verificar</button>
                      </form>

                      <?php if (!$isCloudBackup): ?>
                        <form method="POST" action="<?= url('/local/backup') ?>" class="backup-action-form" data-backup-cloud-async="true" data-progress-title="Enviando backup para nuvem" data-progress-message="Validando conexao Dropbox e preparando o backup escolhido para envio." data-progress-stage="Preparando envio">
                          <?= Csrf::field() ?>
                          <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($currentReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                          <input type="hidden" name="action" value="dropbox_upload_backup">
                          <input type="hidden" name="backup_id" value="<?= htmlspecialchars((string) ($item['backup_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                          <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-sky-400/30 bg-sky-500/10 px-3 py-2 text-xs font-semibold text-sky-200 transition hover:bg-sky-500/20">Enviar nuvem</button>
                        </form>
                      <?php endif; ?>
                    </div>

                    <form method="POST" action="<?= url('/local/backup') ?>" class="backup-action-form grid w-full max-w-xs gap-2 rounded-xl border border-slate-800 bg-slate-900/50 p-2">
                      <?= Csrf::field() ?>
                      <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($currentReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      <input type="hidden" name="action" value="delete_local_backup">
                      <input type="hidden" name="backup_id" value="<?= htmlspecialchars((string) ($item['backup_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      <input type="text" name="delete_confirmation" placeholder="Confirmar ID para excluir" class="w-full rounded-lg border border-slate-700 bg-slate-950/80 px-2 py-2 text-xs text-white outline-none focus:border-rose-400">
                      <button type="submit" class="rounded-xl border border-rose-400/30 bg-transparent px-3 py-2 text-xs font-semibold text-rose-200 transition hover:bg-rose-500/10">Excluir local</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>

            <?php if ($historyVisibleItems === []): ?>
              <tr>
                <td colspan="5" class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-8 text-center text-sm text-slate-400">Nenhum backup encontrado para os filtros atuais.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <section class="mt-5 rounded-2xl border border-cyan-500/20 bg-slate-950/70 px-4 py-3">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
          <div class="text-sm text-slate-300">
            <?php if ($historyTotal > 0): ?>
              Exibindo <span class="font-semibold text-white"><?= number_format($historyFirstItem, 0, ',', '.') ?></span> ate <span class="font-semibold text-white"><?= number_format($historyLastItem, 0, ',', '.') ?></span> de <span class="font-semibold text-white"><?= number_format($historyTotal, 0, ',', '.') ?></span> backups
            <?php else: ?>
              Nenhum backup para paginar no momento.
            <?php endif; ?>
          </div>

          <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="flex items-center gap-3">
              <span class="font-orbitron text-[11px] uppercase tracking-[0.2em] text-slate-500">Por pagina</span>
              <div class="flex items-center gap-2">
                <?php foreach ([5, 10, 20, 50] as $option): ?>
                  <?php $active = $historyPerPage === $option; ?>
                  <a href="<?= htmlspecialchars($historyBuildUrl(1, $option), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-backup-link="true" class="inline-flex h-9 min-w-[2.25rem] items-center justify-center rounded-xl border px-3 text-sm font-semibold transition <?= $active ? 'border-cyan-300/70 bg-cyan-500/15 text-cyan-100' : 'border-slate-700 bg-slate-900 text-slate-300 hover:border-cyan-400/50 hover:bg-cyan-500/10' ?>"><?= $option ?></a>
                <?php endforeach; ?>
              </div>
            </div>

            <nav class="flex items-center gap-2" aria-label="Paginacao dos backups">
              <a href="<?= htmlspecialchars($historyBuildUrl(max(1, $historyPage - 1)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-backup-link="true" class="inline-flex h-9 items-center justify-center rounded-xl border px-4 text-sm transition <?= $historyTotal === 0 || $historyPage <= 1 ? 'pointer-events-none border-slate-800 bg-slate-900 text-slate-600' : 'border-slate-700 bg-slate-900 text-slate-300 hover:border-cyan-400/50 hover:bg-cyan-500/10 hover:text-white' ?>">Anterior</a>
              <?php for ($current = $historyStart; $current <= $historyEnd; $current++): ?>
                <a href="<?= htmlspecialchars($historyBuildUrl($current), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-backup-link="true" class="inline-flex h-9 min-w-[2.25rem] items-center justify-center rounded-xl border px-3 text-sm font-semibold transition <?= $current === $historyPage ? 'border-cyan-300/70 bg-cyan-500/15 text-cyan-100' : 'border-slate-700 bg-slate-900 text-slate-300 hover:border-cyan-400/50 hover:bg-cyan-500/10' ?>"><?= $current ?></a>
              <?php endfor; ?>
              <a href="<?= htmlspecialchars($historyBuildUrl(min($historyPages, $historyPage + 1)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-backup-link="true" class="inline-flex h-9 items-center justify-center rounded-xl border px-4 text-sm transition <?= $historyTotal === 0 || $historyPage >= $historyPages ? 'pointer-events-none border-slate-800 bg-slate-900 text-slate-600' : 'border-slate-700 bg-slate-900 text-slate-300 hover:border-cyan-400/50 hover:bg-cyan-500/10 hover:text-white' ?>">Proximo</a>
            </nav>
          </div>
        </div>
      </section>
    </div>
  <?php else: ?>
    <div class="space-y-6" data-backup-cloud-tabs>
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-4">
        <div class="grid gap-3 md:grid-cols-2">
          <button
            type="button"
            data-cloud-tab-trigger="painel"
            class="rounded-2xl border border-cyan-300/70 bg-cyan-500/15 px-4 py-4 text-left text-cyan-100 shadow-[0_0_24px_rgba(34,211,238,0.12)] transition"
            aria-pressed="true"
          >
            <div class="font-orbitron text-sm font-bold tracking-wide">Conexao e envio</div>
            <div class="mt-1 text-xs text-slate-300">Conta conectada, automacao e disparo manual.</div>
          </button>
          <button
            type="button"
            data-cloud-tab-trigger="historico"
            class="rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-4 text-left text-slate-200 transition hover:border-cyan-400/50 hover:bg-cyan-500/10"
            aria-pressed="false"
          >
            <div class="font-orbitron text-sm font-bold tracking-wide">Historico de envios</div>
            <div class="mt-1 text-xs text-slate-400">Backups ja enviados e destino no Dropbox.</div>
          </button>
        </div>
      </div>

      <div data-cloud-tab-panel="painel" class="grid gap-6 xl:grid-cols-2" style="display:grid;">
        <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <h2 class="font-orbitron text-lg font-bold text-white">Dropbox para backup de ambiente</h2>
            <p class="mt-2 text-sm leading-7 text-slate-400">Conecte uma conta do Dropbox para enviar automaticamente os backups de ambiente dos perfis local, stage e producao. O envio continua guardando a copia local do backup.</p>
          </div>
          <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold <?= ($backupCloud['connected'] ?? false) ? 'border-emerald-400/40 bg-emerald-500/10 text-emerald-200' : 'border-amber-400/40 bg-amber-500/10 text-amber-200' ?>"><?= ($backupCloud['connected'] ?? false) ? 'Conectado' : 'Desconectado' ?></span>
        </div>

        <?php if (!($backupCloud['configured'] ?? false)): ?>
          <div class="mt-5 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-100">
            <div class="font-semibold">Configuracao pendente</div>
            <p class="mt-2 leading-7">Preencha <code class="font-mono text-amber-50">BACKUP_DROPBOX_APP_KEY</code> e <code class="font-mono text-amber-50">BACKUP_DROPBOX_APP_SECRET</code> no <code class="font-mono text-amber-50">.env</code>. O callback sugerido para cadastrar no Dropbox e:</p>
            <div class="mt-3 rounded-xl border border-amber-400/20 bg-slate-950/70 px-3 py-2 font-mono text-xs text-amber-50"><?= htmlspecialchars((string) ($backupCloud['redirect_uri'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <p class="mt-3 text-xs text-amber-100/80">Scopes recomendados: <?= htmlspecialchars((string) ($backupCloud['recommended_scopes'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
          </div>
        <?php else: ?>
          <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Conta</div>
              <?php if ($backupCloud['connected'] ?? false): ?>
                <div class="mt-3 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars((string) ($backupCloud['account_name'] ?? 'Conta Dropbox'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="mt-1 text-sm text-slate-400"><?= htmlspecialchars((string) ($backupCloud['account_email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="mt-4 grid gap-2 text-sm">
                  <div class="flex items-center justify-between"><span class="text-slate-500">Conectado em</span><span><?= htmlspecialchars((string) ($backupCloud['connected_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                  <div class="flex items-center justify-between"><span class="text-slate-500">Destino raiz</span><span class="max-w-[12rem] truncate" title="<?= htmlspecialchars((string) ($backupCloud['remote_root'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars((string) ($backupCloud['remote_root'] ?? '/'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                </div>
              <?php else: ?>
                <div class="mt-3 font-rajdhani text-2xl font-bold text-white">Dropbox pronto para conectar</div>
                <div class="mt-1 text-sm text-slate-400">Depois de autorizar, os backups completos poderao ser enviados manualmente ou automaticamente.</div>
              <?php endif; ?>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Automacao</div>
              <div class="mt-3 font-rajdhani text-2xl font-bold text-white"><?= ($backupCloud['auto_upload_enabled'] ?? false) ? 'Ativa' : 'Manual' ?></div>
              <div class="mt-1 text-sm text-slate-400">Quando ativa, todo novo backup completo tenta subir automaticamente para o Dropbox logo apos ser gerado.</div>
              <?php if (is_array($backupCloud['last_upload'] ?? null)): ?>
                <div class="mt-4 rounded-xl border border-slate-800 bg-slate-900/80 px-3 py-3 text-xs text-slate-300">Ultimo envio: <span class="font-semibold text-white"><?= htmlspecialchars((string) (($backupCloud['last_upload']['backup_id'] ?? '-') . ' em ' . ($backupCloud['last_upload']['uploaded_at'] ?? '-')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
              <?php endif; ?>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4 md:col-span-2">
              <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                  <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Espaco Dropbox</div>
                  <div class="mt-3 font-rajdhani text-2xl font-bold text-white">
                    <?php if (($cloudSpaceUsage['available'] ?? false) === true): ?>
                      <?= htmlspecialchars((string) ($cloudSpaceUsage['free'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> livres
                    <?php else: ?>
                      Indisponivel
                    <?php endif; ?>
                  </div>
                </div>
                <?php if (($cloudSpaceUsage['available'] ?? false) === true): ?>
                  <span class="inline-flex items-center rounded-full border border-cyan-400/30 bg-cyan-500/10 px-3 py-1 text-xs font-semibold text-cyan-100">
                    <?= htmlspecialchars((string) ($cloudSpaceUsage['percent_used'] ?? '0'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>% usado
                  </span>
                <?php endif; ?>
              </div>

              <?php if (($cloudSpaceUsage['available'] ?? false) === true): ?>
                <?php $spacePercent = max(0, min(100, (float) ($cloudSpaceUsage['percent_used'] ?? 0))); ?>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-800">
                  <div class="h-full rounded-full bg-cyan-400" style="width: <?= htmlspecialchars((string) $spacePercent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>%"></div>
                </div>
                <div class="mt-4 grid gap-2 text-sm md:grid-cols-3">
                  <div class="flex items-center justify-between gap-3 md:block"><span class="text-slate-500">Usado</span><span class="font-semibold text-white md:mt-1 md:block"><?= htmlspecialchars((string) ($cloudSpaceUsage['used'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                  <div class="flex items-center justify-between gap-3 md:block"><span class="text-slate-500">Total</span><span class="font-semibold text-white md:mt-1 md:block"><?= htmlspecialchars((string) ($cloudSpaceUsage['allocated'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                  <div class="flex items-center justify-between gap-3 md:block"><span class="text-slate-500">Livre</span><span class="font-semibold text-white md:mt-1 md:block"><?= htmlspecialchars((string) ($cloudSpaceUsage['free'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                </div>
              <?php else: ?>
                <p class="mt-3 text-sm leading-7 text-slate-400"><?= htmlspecialchars((string) ($cloudSpaceUsage['message'] ?? 'A consulta sera exibida quando a conta estiver conectada.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
              <?php endif; ?>
            </div>
          </div>

          <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <?php if (!($backupCloud['connected'] ?? false)): ?>
              <form method="POST" action="<?= url('/local/backup') ?>" class="backup-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Conectando Dropbox" data-progress-message="Estamos abrindo a autorizacao segura do Dropbox para vincular a conta." data-progress-stage="Dropbox OAuth" data-progress-steps="Validando configuracao local|Preparando autorizacao OAuth|Abrindo permissao no Dropbox">
                <?= Csrf::field() ?>
                <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($cloudCurrentReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <input type="hidden" name="action" value="dropbox_connect">
                <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Conectar conta</p>
                <p class="mt-2 text-sm text-slate-400">Inicia o OAuth com refresh token para permitir envio automatico em background.</p>
                <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20">Conectar Dropbox</button>
              </form>
            <?php else: ?>
              <form method="POST" action="<?= url('/local/backup') ?>" class="backup-action-form flex h-full flex-col rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-backup-cloud-async="true" data-progress-title="Enviando ultimo backup" data-progress-message="Validando conexao Dropbox e preparando o envio do backup mais recente." data-progress-stage="Preparando envio">
                <?= Csrf::field() ?>
                <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($cloudCurrentReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <input type="hidden" name="action" value="dropbox_upload_latest">
                <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Enviar ultimo backup</p>
                <p class="mt-2 text-sm text-slate-400">Usa o backup mais recente disponivel e envia manifesto, banco, sistema e uploads quando existirem.</p>
                <div class="mt-auto pt-4">
                  <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-sky-400/40 bg-sky-500/10 px-4 py-2 text-sm font-semibold text-sky-200 transition hover:border-sky-300 hover:bg-sky-500/20">Enviar agora</button>
                </div>
              </form>

              <form method="POST" action="<?= url('/local/backup') ?>" class="backup-action-form flex h-full flex-col rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Atualizando automacao" data-progress-message="Estamos salvando a politica de envio automatico dos backups." data-progress-stage="Automacao Dropbox" data-progress-steps="Lendo conexao atual|Atualizando preferencia de automacao|Salvando estado local">
                <?= Csrf::field() ?>
                <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($cloudCurrentReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <input type="hidden" name="action" value="dropbox_auto_upload">
                <input type="hidden" name="enabled" value="<?= ($backupCloud['auto_upload_enabled'] ?? false) ? '0' : '1' ?>">
                <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Envio automatico</p>
                <p class="mt-2 text-sm text-slate-400"><?= ($backupCloud['auto_upload_enabled'] ?? false) ? 'Desative se quiser controlar manualmente cada envio.' : 'Ative para enviar cada novo backup automaticamente apos a geracao.' ?></p>
                <div class="mt-auto pt-4">
                  <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-emerald-400/40 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-200 transition hover:border-emerald-300 hover:bg-emerald-500/20"><?= ($backupCloud['auto_upload_enabled'] ?? false) ? 'Desativar automacao' : 'Ativar automacao' ?></button>
                </div>
              </form>

              <form method="POST" action="<?= url('/local/backup') ?>" class="backup-action-form flex h-full flex-col rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Desconectando Dropbox" data-progress-message="Estamos removendo a vinculacao local com a conta do Dropbox." data-progress-stage="Dropbox" data-progress-steps="Lendo conexao atual|Removendo tokens locais|Finalizando desconexao">
                <?= Csrf::field() ?>
                <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($cloudCurrentReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <input type="hidden" name="action" value="dropbox_disconnect">
                <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Desconectar</p>
                <p class="mt-2 text-sm text-slate-400">Remove os tokens locais e desliga a automacao sem apagar os backups ja enviados.</p>
                <div class="mt-auto pt-4">
                  <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-rose-400/40 bg-rose-500/10 px-4 py-2 text-sm font-semibold text-rose-200 transition hover:border-rose-300 hover:bg-rose-500/20">Desconectar</button>
                </div>
              </form>
            <?php endif; ?>
          </div>

          <?php if ($backupCloud['connected'] ?? false): ?>
            <div class="mt-5 rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <h3 class="font-orbitron text-sm font-bold text-white">Enviar backup especifico</h3>
              <?php if ($pendingCloudItems !== []): ?>
                <form method="POST" action="<?= url('/local/backup') ?>" class="backup-action-form mt-4 grid gap-4 md:grid-cols-[1fr_auto]" data-backup-cloud-async="true" data-progress-title="Enviando backup selecionado" data-progress-message="Validando conexao Dropbox e preparando o backup escolhido para envio." data-progress-stage="Preparando envio">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($cloudCurrentReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="dropbox_upload_backup">
                  <select name="backup_id" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-cyan-400">
                    <?php foreach ($pendingCloudItems as $item): ?>
                      <option value="<?= htmlspecialchars((string) ($item['backup_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars((string) (($item['backup_id'] ?? '') . ' - ' . ($item['profile_label'] ?? 'Backup')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-5 py-3 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20">Enviar selecionado</button>
                </form>
              <?php else: ?>
                <div class="mt-4 rounded-2xl border border-amber-400/25 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                  Todos os backups locais listados ja foram enviados para a nuvem.
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
        </div>

        <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-lg font-bold text-white">Resumo rapido</h2>
          <p class="mt-2 text-sm leading-7 text-slate-400">Este bloco resume o estado da conexao, da automacao e o ultimo envio conhecido da conta Dropbox vinculada.</p>

          <div class="mt-5 grid gap-4">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Conexao</div>
              <div class="mt-2 font-rajdhani text-2xl font-bold text-white"><?= ($backupCloud['connected'] ?? false) ? 'Conta conectada' : 'Aguardando conexao' ?></div>
              <div class="mt-1 text-sm text-slate-400"><?= ($backupCloud['connected'] ?? false) ? 'A conta do Dropbox ja esta pronta para envio manual ou automatico.' : 'Conecte a conta para habilitar envio de backups para a nuvem.' ?></div>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Automacao</div>
              <div class="mt-2 font-rajdhani text-2xl font-bold text-white"><?= ($backupCloud['auto_upload_enabled'] ?? false) ? 'Ativa' : 'Manual' ?></div>
              <div class="mt-1 text-sm text-slate-400"><?= ($backupCloud['auto_upload_enabled'] ?? false) ? 'Todo novo backup completo tenta subir para o Dropbox logo apos a geracao.' : 'Os envios sao disparados manualmente a partir desta subaba.' ?></div>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Espaco Dropbox</div>
              <?php if (($cloudSpaceUsage['available'] ?? false) === true): ?>
                <div class="mt-2 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars((string) ($cloudSpaceUsage['free'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="mt-1 text-sm text-slate-400"><?= htmlspecialchars((string) ($cloudSpaceUsage['used'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> usados de <?= htmlspecialchars((string) ($cloudSpaceUsage['allocated'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>.</div>
              <?php else: ?>
                <div class="mt-2 font-rajdhani text-2xl font-bold text-white">Indisponivel</div>
                <div class="mt-1 text-sm text-slate-400"><?= htmlspecialchars((string) ($cloudSpaceUsage['message'] ?? 'Conecte a conta para consultar a cota.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <?php endif; ?>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Ultimo envio conhecido</div>
              <?php if (is_array($backupCloud['last_upload'] ?? null)): ?>
                <div class="mt-2 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars((string) ($backupCloud['last_upload']['backup_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="mt-1 text-sm text-slate-400"><?= htmlspecialchars((string) ($backupCloud['last_upload']['uploaded_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="mt-3 text-xs text-slate-500"><?= htmlspecialchars((string) ($backupCloud['last_upload']['destination'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <?php else: ?>
                <div class="mt-2 font-rajdhani text-2xl font-bold text-white">Nenhum envio</div>
                <div class="mt-1 text-sm text-slate-400">Assim que um backup for enviado com sucesso, ele aparece aqui.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div data-cloud-tab-panel="historico" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6" style="display:none;">
        <h2 class="font-orbitron text-lg font-bold text-white">Historico de envios</h2>
        <?php if ($backupCloudRecentUploads === []): ?>
          <p class="mt-4 text-sm text-slate-400">Nenhum backup enviado para o Dropbox ainda.</p>
        <?php else: ?>
          <div class="mt-4 space-y-3">
            <?php foreach ($backupCloudRecentUploads as $upload): ?>
              <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                <div class="flex items-center justify-between gap-4">
                  <div>
                    <div class="font-semibold text-white"><?= htmlspecialchars((string) ($upload['backup_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) ($upload['profile_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  </div>
                  <span class="rounded-full border border-emerald-400/30 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-200"><?= htmlspecialchars((string) ($upload['cloud_provider'] ?? 'Dropbox'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                </div>
                <div class="mt-3 grid gap-2 text-sm text-slate-300">
                  <div class="flex items-center justify-between gap-4"><span class="text-slate-500">Enviado em</span><span><?= htmlspecialchars((string) ($upload['cloud_uploaded_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                  <div class="flex items-center justify-between gap-4"><span class="text-slate-500">Tamanho enviado</span><span class="font-semibold text-white"><?= htmlspecialchars((string) ($upload['cloud_uploaded_size'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                  <div class="flex items-start justify-between gap-4"><span class="text-slate-500">Destino</span><span class="max-w-[16rem] break-all text-right"><?= htmlspecialchars((string) ($upload['cloud_destination'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                </div>
                <form method="POST" action="<?= url('/local/backup') ?>" class="mt-4 grid gap-3 rounded-2xl border border-rose-500/20 bg-rose-500/5 p-3 md:grid-cols-[1fr_auto] md:items-end">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($cloudCurrentReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="dropbox_delete_backup">
                  <input type="hidden" name="backup_id" value="<?= htmlspecialchars((string) ($upload['backup_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                  <label class="block">
                    <span class="text-xs uppercase tracking-[0.2em] text-rose-200/70">Confirmar exclusao</span>
                    <input type="text" name="delete_confirmation" placeholder="<?= htmlspecialchars((string) ($upload['backup_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="mt-2 w-full rounded-xl border border-slate-700 bg-slate-950/80 px-3 py-2 text-sm text-white outline-none focus:border-rose-400">
                  </label>
                  <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-rose-400/40 bg-rose-500/10 px-4 py-2 text-sm font-semibold text-rose-100 transition hover:border-rose-300 hover:bg-rose-500/20">Excluir do Dropbox</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>

          <section class="mt-5 rounded-2xl border border-cyan-500/20 bg-slate-950/70 px-4 py-3">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
              <div class="text-sm text-slate-300">
                Exibindo <span class="font-semibold text-white"><?= number_format($cloudFirstItem, 0, ',', '.') ?></span> ate <span class="font-semibold text-white"><?= number_format($cloudLastItem, 0, ',', '.') ?></span> de <span class="font-semibold text-white"><?= number_format($cloudTotal, 0, ',', '.') ?></span> envios
              </div>

              <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="flex items-center gap-3">
                  <span class="font-orbitron text-[11px] uppercase tracking-[0.2em] text-slate-500">Por pagina</span>
                  <div class="flex items-center gap-2">
                    <?php foreach ([5, 10, 20, 50] as $option): ?>
                      <?php $active = $cloudPerPage === $option; ?>
                      <a href="<?= htmlspecialchars($cloudBuildUrl(1, $option), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-backup-link="true" class="inline-flex h-9 min-w-[2.25rem] items-center justify-center rounded-xl border px-3 text-sm font-semibold transition <?= $active ? 'border-cyan-300/70 bg-cyan-500/15 text-cyan-100' : 'border-slate-700 bg-slate-900 text-slate-300 hover:border-cyan-400/50 hover:bg-cyan-500/10' ?>"><?= $option ?></a>
                    <?php endforeach; ?>
                  </div>
                </div>

                <nav class="flex items-center gap-2" aria-label="Paginacao do historico de envios">
                  <a href="<?= htmlspecialchars($cloudBuildUrl(max(1, $cloudPage - 1)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-backup-link="true" class="inline-flex h-9 items-center justify-center rounded-xl border px-4 text-sm transition <?= $cloudTotal === 0 || $cloudPage <= 1 ? 'pointer-events-none border-slate-800 bg-slate-900 text-slate-600' : 'border-slate-700 bg-slate-900 text-slate-300 hover:border-cyan-400/50 hover:bg-cyan-500/10 hover:text-white' ?>">Anterior</a>
                  <?php for ($current = $cloudStart; $current <= $cloudEnd; $current++): ?>
                    <a href="<?= htmlspecialchars($cloudBuildUrl($current), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-backup-link="true" class="inline-flex h-9 min-w-[2.25rem] items-center justify-center rounded-xl border px-3 text-sm font-semibold transition <?= $current === $cloudPage ? 'border-cyan-300/70 bg-cyan-500/15 text-cyan-100' : 'border-slate-700 bg-slate-900 text-slate-300 hover:border-cyan-400/50 hover:bg-cyan-500/10' ?>"><?= $current ?></a>
                  <?php endfor; ?>
                  <a href="<?= htmlspecialchars($cloudBuildUrl(min($cloudPages, $cloudPage + 1)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-backup-link="true" class="inline-flex h-9 items-center justify-center rounded-xl border px-4 text-sm transition <?= $cloudTotal === 0 || $cloudPage >= $cloudPages ? 'pointer-events-none border-slate-800 bg-slate-900 text-slate-600' : 'border-slate-700 bg-slate-900 text-slate-300 hover:border-cyan-400/50 hover:bg-cyan-500/10 hover:text-white' ?>">Proximo</a>
                </nav>
              </div>
            </div>
          </section>
        <?php endif; ?>
      </div>

      <script>
        (() => {
          const root = document.querySelector('[data-backup-cloud-tabs]');
          if (!root) {
            return;
          }

          const triggers = Array.from(root.querySelectorAll('[data-cloud-tab-trigger]'));
          const panels = Array.from(root.querySelectorAll('[data-cloud-tab-panel]'));
          if (triggers.length === 0 || panels.length === 0) {
            return;
          }

          const initialTab = <?= json_encode($backupCloudTab, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

          const activate = (target) => {
            triggers.forEach((trigger) => {
              const active = trigger.dataset.cloudTabTrigger === target;
              trigger.classList.toggle('border-cyan-300/70', active);
              trigger.classList.toggle('bg-cyan-500/15', active);
              trigger.classList.toggle('text-cyan-100', active);
              trigger.classList.toggle('shadow-[0_0_24px_rgba(34,211,238,0.12)]', active);
              trigger.classList.toggle('border-slate-700', !active);
              trigger.classList.toggle('bg-slate-950/70', !active);
              trigger.classList.toggle('text-slate-200', !active);
              trigger.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            panels.forEach((panel) => {
              const active = panel.dataset.cloudTabPanel === target;
              panel.style.display = active
                ? (panel.dataset.cloudTabPanel === 'painel' ? 'grid' : 'block')
                : 'none';
            });
          };

          triggers.forEach((trigger) => {
            trigger.addEventListener('click', () => activate(trigger.dataset.cloudTabTrigger || 'painel'));
          });

          activate(initialTab === 'historico' ? 'historico' : 'painel');
        })();
      </script>
    </div>
  <?php endif; ?>
</section>
