<?php

declare(strict_types=1);

use App\Support\Csrf;

$section = (string) ($editorial_section ?? 'resumo');
$baseUrl = (string) ($editorial_base_url ?? url('/admin/central-operacional-v2/backup-editorial'));
$basePath = parse_url($baseUrl, PHP_URL_PATH);
$baseQuery = parse_url($baseUrl, PHP_URL_QUERY);
$usesPrettyEditorialSections = is_string($basePath)
    && str_contains($basePath, '/central-operacional-v2/backup-editorial')
    && ($baseQuery === null || $baseQuery === false || $baseQuery === '');
$sectionUrl = static function (string $targetSection, array $query = []) use ($baseUrl, $usesPrettyEditorialSections): string {
    if ($usesPrettyEditorialSections) {
        $url = rtrim($baseUrl, '/') . '/' . rawurlencode($targetSection);
    } else {
        $url = $baseUrl;
        $query = ['editorial_secao' => $targetSection] + $query;
    }

    $query = array_filter($query, static fn ($value): bool => !($value === '' || $value === null || $value === 0));
    if ($query !== []) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    return $url;
};
$redirectPath = parse_url($baseUrl, PHP_URL_PATH) ?: '/admin/central-operacional-v2/backup-editorial';
$redirectTo = (string) parse_url($sectionUrl($section), PHP_URL_PATH);
$redirectQuery = (string) parse_url($sectionUrl($section), PHP_URL_QUERY);
$redirectTo .= $redirectQuery !== '' ? '?' . $redirectQuery : '';
$status = (array) ($content_status ?? []);
$items = array_values(array_filter((array) ($status['items'] ?? []), 'is_array'));
$running = is_array($status['running'] ?? null) ? $status['running'] : null;
$flash = is_array($flash ?? null) ? $flash : null;
$lastVerification = is_array($last_verification ?? null) ? $last_verification : null;
$stageReady = (bool) ($stage_ready ?? false);
$productionReady = (bool) ($production_ready ?? false);
$deploymentPolicy = is_array($deployment_policy ?? null) ? $deployment_policy : [];
$parityStatus = is_array($parity_status ?? null) ? $parity_status : [];

$search = trim((string) ($_GET['editorial_busca'] ?? ''));
$environment = strtolower(trim((string) ($_GET['editorial_ambiente'] ?? '')));
$historyStatus = strtolower(trim((string) ($_GET['editorial_status'] ?? '')));
$sort = strtolower(trim((string) ($_GET['editorial_sort'] ?? 'date')));
$dir = strtolower(trim((string) ($_GET['editorial_dir'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc';
$page = max(1, (int) ($_GET['editorial_page'] ?? 1));
$perPage = in_array((int) ($_GET['editorial_per_page'] ?? 10), [10, 20, 50], true) ? (int) ($_GET['editorial_per_page'] ?? 10) : 10;

if (!in_array($environment, ['', 'local', 'stage', 'production'], true)) {
    $environment = '';
}
if (!in_array($historyStatus, ['', 'valido', 'pendente', 'invalido', 'nuvem'], true)) {
    $historyStatus = '';
}
if (!in_array($sort, ['date', 'profile', 'id', 'posts', 'links', 'uploads', 'status'], true)) {
    $sort = 'date';
}

$labels = [
    'local' => 'Local',
    'stage' => 'Stage',
    'production' => 'Producao',
];

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

$relativeDate = static function (?string $value): string {
    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return 'sem leitura relativa';
    }

    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'agora';
    }
    if ($diff < 3600) {
        return 'ha ' . max(1, (int) floor($diff / 60)) . ' min';
    }
    if ($diff < 86400) {
        return 'ha ' . max(1, (int) floor($diff / 3600)) . ' h';
    }

    return 'ha ' . max(1, (int) floor($diff / 86400)) . ' d';
};

$formatBytes = static function (int $bytes): string {
    if ($bytes <= 0) {
        return '-';
    }

    $units = ['B', 'KB', 'MB', 'GB'];
    $size = (float) $bytes;
    $unit = 0;
    while ($size >= 1024 && $unit < count($units) - 1) {
        $size /= 1024;
        $unit++;
    }

    return number_format($size, $unit === 0 ? 0 : 1, ',', '.') . ' ' . $units[$unit];
};

$packageBytes = static function (array $item): int {
    $uploads = is_array($item['uploads'] ?? null) ? $item['uploads'] : [];
    $bytes = (int) ($uploads['size_bytes'] ?? 0);
    foreach ((array) ($item['data_files'] ?? []) as $file) {
        if (is_array($file)) {
            $bytes += (int) ($file['size_bytes'] ?? 0);
        }
    }

    return $bytes;
};

$latestByProfile = [
    'local' => null,
    'stage' => null,
    'production' => null,
];
foreach ($items as $item) {
    $profile = strtolower((string) ($item['source_profile'] ?? ''));
    if (array_key_exists($profile, $latestByProfile) && $latestByProfile[$profile] === null) {
        $latestByProfile[$profile] = $item;
    }
}

$validCount = count(array_filter($items, static fn(array $item): bool => (bool) ($item['is_valid'] ?? false)));
$cloudCount = count(array_filter($items, static fn(array $item): bool => (bool) ($item['cloud_uploaded'] ?? false)));
$productionCount = count(array_filter($items, static fn(array $item): bool => strtolower((string) ($item['source_profile'] ?? '')) === 'production'));

$alerts = [];
foreach ($latestByProfile as $profile => $item) {
    if ($item === null) {
        $alerts[] = ['tone' => 'neutral', 'title' => $labels[$profile] ?? ucfirst($profile), 'text' => 'Leitura pendente'];
        continue;
    }

    $createdAt = (string) ($item['created_at'] ?? '');
    $age = strtotime($createdAt);
    if ($age !== false && (time() - $age) > 86400) {
        $alerts[] = ['tone' => 'warning', 'title' => $labels[$profile] ?? ucfirst($profile), 'text' => 'Backup editorial acima de 24h'];
    }
    if (!(bool) ($item['is_valid'] ?? false)) {
        $alerts[] = ['tone' => 'danger', 'title' => $labels[$profile] ?? ucfirst($profile), 'text' => 'Pacote sem validacao OK'];
    }
}
if ($alerts === []) {
    $alerts[] = ['tone' => 'success', 'title' => 'Sem alertas', 'text' => 'Nenhuma pendencia critica na leitura atual'];
}

$badgeClass = static function (string $tone): string {
    return match ($tone) {
        'success', 'valido' => 'border-emerald-400/35 bg-emerald-500/10 text-emerald-200',
        'warning', 'pendente' => 'border-amber-400/35 bg-amber-500/10 text-amber-200',
        'danger', 'invalido' => 'border-rose-400/35 bg-rose-500/10 text-rose-200',
        'cloud' => 'border-sky-400/35 bg-sky-500/10 text-sky-200',
        default => 'border-slate-700 bg-slate-900/80 text-slate-300',
    };
};

$rowStatus = static function (array $item): array {
    if ((bool) ($item['cloud_uploaded'] ?? false)) {
        return ['label' => 'Nuvem', 'tone' => 'cloud'];
    }
    if ((bool) ($item['is_valid'] ?? false)) {
        return ['label' => 'Valido', 'tone' => 'valido'];
    }
    if (strtolower((string) ($item['status'] ?? '')) === 'failed') {
        return ['label' => 'Invalido', 'tone' => 'invalido'];
    }

    return ['label' => 'Pendente', 'tone' => 'pendente'];
};

$buildActionForm = static function (string $action, array $hidden, string $button, string $classes, string $progressTitle, string $progressMessage, string $progressStage, bool $disabled = false) use ($redirectTo): string {
    $html = '<form method="POST" action="' . htmlspecialchars(url('/local/conteudo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="editorial-action-form" data-progress-title="' . htmlspecialchars($progressTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" data-progress-message="' . htmlspecialchars($progressMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" data-progress-stage="' . htmlspecialchars($progressStage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
    $html .= Csrf::field();
    $html .= '<input type="hidden" name="action" value="' . htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
    $html .= '<input type="hidden" name="redirect_to" value="' . htmlspecialchars($redirectTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
    foreach ($hidden as $name => $value) {
        $html .= '<input type="hidden" name="' . htmlspecialchars((string) $name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" value="' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
    }
    $html .= '<button type="submit" class="' . htmlspecialchars($classes, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' . ($disabled ? ' disabled' : '') . '>' . htmlspecialchars($button, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</button>';
    $html .= '</form>';

    return $html;
};
?>
<section class="space-y-6">
  <?php if ($flash !== null): ?>
    <div class="rounded-2xl border px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'error' ? 'border-rose-500/40 bg-rose-500/10 text-rose-100' : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100' ?>">
      <?= htmlspecialchars((string) ($flash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <?php if ($running !== null): ?>
    <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
      <strong class="font-semibold">Rotina em execucao:</strong>
      <?= htmlspecialchars((string) ($running['profile_label'] ?? 'Fluxo ativo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <?php if ($section === 'resumo'): ?>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-2xl border border-slate-800 bg-slate-950/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/75">Total</p>
        <div class="mt-3 font-orbitron text-3xl font-black text-white"><?= count($items) ?></div>
      </div>
      <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-emerald-200/80">Validos</p>
        <div class="mt-3 font-orbitron text-3xl font-black text-white"><?= $validCount ?></div>
      </div>
      <div class="rounded-2xl border border-sky-500/20 bg-sky-500/10 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-sky-200/80">Nuvem</p>
        <div class="mt-3 font-orbitron text-3xl font-black text-white"><?= $cloudCount ?></div>
      </div>
      <div class="rounded-2xl border border-amber-500/25 bg-amber-500/10 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-amber-200/80">Producao</p>
        <div class="mt-3 font-orbitron text-3xl font-black text-white"><?= $productionCount ?></div>
      </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
      <?php foreach (['local', 'stage', 'production'] as $profile): ?>
        <?php
        $item = $latestByProfile[$profile];
        $stats = is_array($item['stats'] ?? null) ? $item['stats'] : [];
        $uploads = is_array($item['uploads'] ?? null) ? $item['uploads'] : [];
        $isProduction = $profile === 'production';
        $statusInfo = $item === null ? ['label' => 'Leitura pendente', 'tone' => 'pendente'] : $rowStatus($item);
        ?>
        <article class="rounded-3xl border <?= $isProduction ? 'border-amber-400/35 bg-amber-500/10 shadow-[0_0_30px_rgba(251,191,36,0.08)]' : 'border-slate-800 bg-slate-900/85' ?> p-5 transition hover:-translate-y-0.5 hover:border-cyan-400/35">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/75">Ambiente</p>
              <h2 class="mt-2 font-orbitron text-2xl font-black text-white"><?= htmlspecialchars($labels[$profile] ?? ucfirst($profile), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
            </div>
            <span class="rounded-full border px-3 py-1 text-[0.65rem] font-black uppercase tracking-[0.18em] <?= $badgeClass($statusInfo['tone']) ?>"><?= htmlspecialchars($statusInfo['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          </div>

          <div class="mt-5 rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <p class="text-xs uppercase tracking-[0.22em] text-slate-500">Ultimo pacote</p>
            <div class="mt-2 break-all font-orbitron text-lg font-black text-white"><?= htmlspecialchars((string) ($item['package_id'] ?? 'Leitura pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <p class="mt-2 text-xs text-slate-500"><?= htmlspecialchars($formatDate((string) ($item['created_at'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> · <?= htmlspecialchars($relativeDate((string) ($item['created_at'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
          </div>

          <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/60 p-3"><span class="block text-slate-500">Posts</span><strong class="text-white"><?= (int) ($stats['posts'] ?? 0) ?></strong></div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/60 p-3"><span class="block text-slate-500">Links</span><strong class="text-white"><?= (int) ($stats['links'] ?? 0) ?></strong></div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/60 p-3"><span class="block text-slate-500">Uploads</span><strong class="text-white"><?= (int) ($uploads['included_files'] ?? $stats['uploads'] ?? 0) ?></strong></div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/60 p-3"><span class="block text-slate-500">Tamanho</span><strong class="text-white"><?= htmlspecialchars($formatBytes($item !== null ? $packageBytes($item) : 0), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/85 p-6">
        <h2 class="font-orbitron text-xl font-black text-white">Resumo Operacional</h2>
        <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4"><p class="text-xs uppercase tracking-[0.22em] text-cyan-300/70">Fluxo</p><strong class="mt-2 block text-white">Local -> Stage -> Producao</strong></div>
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4"><p class="text-xs uppercase tracking-[0.22em] text-cyan-300/70">Origem</p><strong class="mt-2 block text-white">Pacotes editoriais</strong></div>
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4"><p class="text-xs uppercase tracking-[0.22em] text-cyan-300/70">Stage</p><strong class="mt-2 block <?= $stageReady ? 'text-emerald-200' : 'text-amber-200' ?>"><?= $stageReady ? 'Disponivel' : 'Pendente' ?></strong></div>
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4"><p class="text-xs uppercase tracking-[0.22em] text-cyan-300/70">Producao</p><strong class="mt-2 block <?= $productionReady ? 'text-emerald-200' : 'text-amber-200' ?>"><?= $productionReady ? 'Disponivel' : 'Protegida' ?></strong></div>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/85 p-6">
        <h2 class="font-orbitron text-xl font-black text-white">Alertas Operacionais</h2>
        <div class="mt-5 space-y-3">
          <?php foreach ($alerts as $alert): ?>
            <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <div>
                <p class="font-semibold text-white"><?= htmlspecialchars((string) $alert['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                <p class="mt-1 text-sm text-slate-400"><?= htmlspecialchars((string) $alert['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
              </div>
              <span class="rounded-full border px-3 py-1 text-[0.65rem] font-black uppercase tracking-[0.18em] <?= $badgeClass((string) $alert['tone']) ?>"><?= htmlspecialchars((string) $alert['tone'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php elseif ($section === 'acoes'): ?>
    <div class="grid gap-4 xl:grid-cols-3">
      <?php foreach (['local', 'stage', 'production'] as $profile): ?>
        <?php
          $ready = $profile === 'local' || ($profile === 'stage' ? $stageReady : true);
          $isProduction = $profile === 'production';
        ?>
        <article class="rounded-3xl border <?= $isProduction ? 'border-amber-400/35 bg-amber-500/10' : 'border-slate-800 bg-slate-900/85' ?> p-5">
          <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/75">Gerar pacote</p>
          <h2 class="mt-2 font-orbitron text-2xl font-black text-white"><?= htmlspecialchars($labels[$profile] ?? ucfirst($profile), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
          <div class="mt-5 rounded-2xl border border-slate-800 bg-slate-950/70 p-4 text-sm text-slate-300">
            <div class="flex justify-between gap-4"><span class="text-slate-500">Ultimo pacote</span><span class="text-right text-white"><?= htmlspecialchars((string) ($latestByProfile[$profile]['package_id'] ?? 'Pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
            <div class="mt-2 flex justify-between gap-4"><span class="text-slate-500">Status</span><span class="<?= $ready ? 'text-emerald-200' : 'text-amber-200' ?>"><?= $ready ? 'Disponivel' : 'Pendente' ?></span></div>
          </div>
          <div class="mt-5">
            <?= $buildActionForm(
                'export',
                ['profile' => $profile],
                'Gerar pacote',
                'inline-flex w-full items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-3 text-sm font-black text-cyan-100 transition hover:border-cyan-300 hover:bg-cyan-500/20 disabled:cursor-not-allowed disabled:border-slate-700 disabled:bg-slate-900 disabled:text-slate-500',
                'Gerando pacote editorial',
                'Lendo banco e uploads da origem selecionada.',
                'Exportacao editorial',
                !$ready
            ) ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="rounded-3xl border border-slate-800 bg-slate-900/85 p-6">
      <h2 class="font-orbitron text-xl font-black text-white">Verificacao Rapida</h2>
      <div class="mt-5 grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-5">
          <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/75">Ultimo pacote</p>
          <div class="mt-3 break-all font-orbitron text-xl font-black text-white"><?= htmlspecialchars((string) ($status['latest']['package_id'] ?? 'Pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <?php if ($lastVerification !== null): ?>
            <p class="mt-2 text-sm text-slate-400">Ultima verificacao: <?= htmlspecialchars((string) ($lastVerification['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
          <?php endif; ?>
        </div>
        <div class="flex items-center rounded-2xl border border-slate-800 bg-slate-950/70 p-5">
          <?= $buildActionForm(
              'verify',
              ['package_id' => 'latest'],
              'Verificar ultimo pacote',
              'inline-flex w-full items-center justify-center rounded-2xl border border-emerald-400/40 bg-emerald-500/10 px-4 py-3 text-sm font-black text-emerald-100 transition hover:border-emerald-300 hover:bg-emerald-500/20',
              'Verificando pacote editorial',
              'Conferindo manifesto, JSONs e uploads do ultimo pacote.',
              'Verificacao editorial'
          ) ?>
        </div>
      </div>
    </div>
  <?php elseif ($section === 'restore'): ?>
    <div class="rounded-3xl border border-slate-800 bg-slate-900/85 p-6">
      <h2 class="font-orbitron text-xl font-black text-white">Restore Editorial Controlado</h2>
      <form method="POST" action="<?= url('/local/conteudo') ?>" class="editorial-action-form mt-5 grid gap-4 xl:grid-cols-[1.2fr_0.8fr_0.8fr_auto]" data-progress-title="Aplicando pacote editorial" data-progress-message="Aplicando conteudo e uploads no destino escolhido." data-progress-stage="Restore editorial">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="apply">
        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($redirectTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <label class="block">
          <span class="mb-2 block text-xs font-black uppercase tracking-[0.22em] text-slate-500">Pacote</span>
          <select name="package_id" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-cyan-400">
            <option value="latest">Ultimo valido</option>
            <?php foreach ($items as $item): ?>
              <option value="<?= htmlspecialchars((string) ($item['package_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars((string) ($item['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> - <?= htmlspecialchars((string) ($item['source_profile_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="block">
          <span class="mb-2 block text-xs font-black uppercase tracking-[0.22em] text-slate-500">Destino</span>
          <select name="target_profile" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-cyan-400">
            <option value="local">Local</option>
            <option value="stage">Stage</option>
            <option value="production">Producao</option>
          </select>
        </label>
        <label class="block">
          <span class="mb-2 block text-xs font-black uppercase tracking-[0.22em] text-slate-500">Confirmacao</span>
          <input name="apply_phrase" value="" placeholder="PUBLICAR" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-cyan-400">
        </label>
        <div class="flex items-end">
          <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl border border-amber-400/40 bg-amber-500/10 px-5 py-3 text-sm font-black text-amber-100 transition hover:border-amber-300 hover:bg-amber-500/20">Aplicar</button>
        </div>
      </form>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
      <?php foreach (array_slice($items, 0, 3) as $item): ?>
        <?php $statusInfo = $rowStatus($item); ?>
        <article class="rounded-3xl border border-slate-800 bg-slate-900/85 p-5">
          <div class="flex items-start justify-between gap-4">
            <h3 class="break-all font-orbitron text-lg font-black text-white"><?= htmlspecialchars((string) ($item['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
            <span class="rounded-full border px-3 py-1 text-[0.65rem] font-black uppercase tracking-[0.18em] <?= $badgeClass($statusInfo['tone']) ?>"><?= htmlspecialchars($statusInfo['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          </div>
          <p class="mt-3 text-sm text-slate-400"><?= htmlspecialchars((string) ($item['source_profile_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> · <?= htmlspecialchars($formatDate((string) ($item['created_at'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
          <?php if (is_array($item['last_apply'] ?? null)): ?>
            <p class="mt-3 text-xs text-slate-500">Ultimo destino: <span class="text-slate-300"><?= htmlspecialchars((string) (($item['last_apply']['target_profile_label'] ?? '-') . ' em ' . ($item['last_apply']['applied_at'] ?? '-')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <?php
      $rows = array_values(array_filter($items, static function (array $item) use ($search, $environment, $historyStatus, $rowStatus): bool {
          $profile = strtolower((string) ($item['source_profile'] ?? ''));
          if ($environment !== '' && $profile !== $environment) {
              return false;
          }

          $statusInfo = $rowStatus($item);
          if ($historyStatus !== '' && strtolower((string) ($statusInfo['label'] ?? '')) !== $historyStatus) {
              return false;
          }

          if ($search !== '') {
              $haystack = strtolower((string) ($item['package_id'] ?? '') . ' ' . (string) ($item['source_profile_label'] ?? ''));
              if (!str_contains($haystack, strtolower($search))) {
                  return false;
              }
          }

          return true;
      }));

      $sortValue = static function (array $item, string $sort) use ($packageBytes, $rowStatus): int|string {
          $stats = is_array($item['stats'] ?? null) ? $item['stats'] : [];
          $uploads = is_array($item['uploads'] ?? null) ? $item['uploads'] : [];

          return match ($sort) {
              'profile' => (string) ($item['source_profile_label'] ?? ''),
              'id' => (string) ($item['package_id'] ?? ''),
              'posts' => (int) ($stats['posts'] ?? 0),
              'links' => (int) ($stats['links'] ?? 0),
              'uploads' => (int) ($uploads['included_files'] ?? $stats['uploads'] ?? 0),
              'status' => (string) ($rowStatus($item)['label'] ?? ''),
              default => strtotime((string) ($item['created_at'] ?? '')) ?: 0,
          };
      };

      usort($rows, static function (array $left, array $right) use ($sort, $dir, $sortValue): int {
          $a = $sortValue($left, $sort);
          $b = $sortValue($right, $sort);
          $result = is_int($a) && is_int($b) ? $a <=> $b : strcmp((string) $a, (string) $b);

          return $dir === 'asc' ? $result : -$result;
      });

      $totalRows = count($rows);
      $totalPages = max(1, (int) ceil($totalRows / $perPage));
      $page = min($page, $totalPages);
      $offset = ($page - 1) * $perPage;
      $pageRows = array_slice($rows, $offset, $perPage);
      $nextDir = $dir === 'asc' ? 'desc' : 'asc';
      $sortUrl = static function (string $field) use ($sectionUrl, $nextDir, $search, $environment, $historyStatus, $perPage): string {
          return $sectionUrl('historico', [
              'editorial_sort' => $field,
              'editorial_dir' => $nextDir,
              'editorial_busca' => $search,
              'editorial_ambiente' => $environment,
              'editorial_status' => $historyStatus,
              'editorial_per_page' => $perPage,
          ]);
      };
    ?>
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
    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
        <div>
          <h2 class="font-orbitron text-lg font-bold text-white">Backups editoriais recentes</h2>
        </div>
      </div>

    <div class="mt-5 grid gap-3 md:grid-cols-4">
      <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4"><p class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Total</p><div class="mt-2 text-2xl font-black text-white"><?= count($items) ?></div></div>
      <div class="rounded-2xl border border-emerald-500/25 bg-emerald-500/10 p-4"><p class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-emerald-200/75">Validos</p><div class="mt-2 text-2xl font-black text-white"><?= $validCount ?></div></div>
      <div class="rounded-2xl border border-sky-500/25 bg-sky-500/10 p-4"><p class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-sky-200/75">Nuvem</p><div class="mt-2 text-2xl font-black text-white"><?= $cloudCount ?></div></div>
      <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4"><p class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-amber-200/75">Producao</p><div class="mt-2 text-2xl font-black text-white"><?= $productionCount ?></div></div>
    </div>

    <form method="GET" action="<?= htmlspecialchars($sectionUrl('historico'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="mt-5 rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
      <?php if (!$usesPrettyEditorialSections): ?>
        <input type="hidden" name="editorial_secao" value="historico">
      <?php endif; ?>
      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[1.3fr_0.8fr_0.8fr_0.7fr_auto]">
        <input name="editorial_busca" value="<?= htmlspecialchars($search, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" placeholder="Buscar pacote..." class="min-h-11 rounded-xl border border-slate-700 bg-slate-950/80 px-4 text-sm text-white outline-none focus:border-cyan-400">
        <select name="editorial_ambiente" class="min-h-11 rounded-xl border border-slate-700 bg-slate-950/80 px-4 text-sm text-white outline-none focus:border-cyan-400">
          <option value="">Todos ambientes</option>
          <?php foreach ($labels as $key => $label): ?>
            <option value="<?= htmlspecialchars($key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= $environment === $key ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
        <select name="editorial_status" class="min-h-11 rounded-xl border border-slate-700 bg-slate-950/80 px-4 text-sm text-white outline-none focus:border-cyan-400">
          <option value="">Todos status</option>
          <option value="valido" <?= $historyStatus === 'valido' ? 'selected' : '' ?>>Valido</option>
          <option value="nuvem" <?= $historyStatus === 'nuvem' ? 'selected' : '' ?>>Nuvem</option>
          <option value="pendente" <?= $historyStatus === 'pendente' ? 'selected' : '' ?>>Pendente</option>
          <option value="invalido" <?= $historyStatus === 'invalido' ? 'selected' : '' ?>>Invalido</option>
        </select>
        <select name="editorial_per_page" class="min-h-11 rounded-xl border border-slate-700 bg-slate-950/80 px-4 text-sm text-white outline-none focus:border-cyan-400">
          <?php foreach ([10, 20, 50] as $option): ?>
            <option value="<?= $option ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= $option ?>/pag.</option>
          <?php endforeach; ?>
        </select>
        <button class="inline-flex min-h-11 items-center justify-center rounded-xl border border-cyan-400/35 bg-cyan-500/10 px-4 text-xs font-black uppercase tracking-[0.12em] text-cyan-100 transition hover:border-cyan-300 hover:bg-cyan-500/18">Filtrar</button>
      </div>
    </form>

    <div class="mt-5 overflow-hidden rounded-2xl border border-slate-800 bg-slate-950/70">
      <div class="overflow-x-auto">
        <table class="ops-history-table min-w-full border-separate border-spacing-y-3 text-left text-sm">
          <thead class="border-b border-slate-800 bg-slate-950/80 text-xs uppercase tracking-[0.22em] text-slate-500">
            <tr>
              <th class="px-5 py-4"><a href="<?= htmlspecialchars($sortUrl('date'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-editorial-link="true">Data</a></th>
              <th class="px-5 py-4"><a href="<?= htmlspecialchars($sortUrl('profile'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-editorial-link="true">Ambiente</a></th>
              <th class="px-5 py-4">Tipo</th>
              <th class="px-5 py-4"><a href="<?= htmlspecialchars($sortUrl('id'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-editorial-link="true">Identificador</a></th>
              <th class="px-5 py-4"><a href="<?= htmlspecialchars($sortUrl('posts'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-editorial-link="true">Conteudo</a></th>
              <th class="px-5 py-4">Tamanho</th>
              <th class="px-5 py-4"><a href="<?= htmlspecialchars($sortUrl('status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-editorial-link="true">Status</a></th>
              <th class="px-5 py-4">Acoes</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <?php if ($pageRows === []): ?>
              <tr><td colspan="8" class="px-5 py-8 text-center text-slate-400">Nenhum pacote encontrado.</td></tr>
            <?php endif; ?>
            <?php foreach ($pageRows as $item): ?>
              <?php
                $profile = strtolower((string) ($item['source_profile'] ?? ''));
                $stats = is_array($item['stats'] ?? null) ? $item['stats'] : [];
                $uploads = is_array($item['uploads'] ?? null) ? $item['uploads'] : [];
                $statusInfo = $rowStatus($item);
              ?>
              <tr class="<?= $profile === 'production' ? 'bg-amber-500/[0.04]' : 'bg-slate-950/20' ?> transition hover:bg-cyan-500/[0.05]">
                <td class="px-5 py-4"><span class="block text-white"><?= htmlspecialchars($formatDate((string) ($item['created_at'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span><span class="text-xs text-slate-500"><?= htmlspecialchars($relativeDate((string) ($item['created_at'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></td>
                <td class="px-5 py-4"><span class="font-semibold text-white"><?= htmlspecialchars($labels[$profile] ?? (string) ($item['source_profile_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></td>
                <td class="px-5 py-4 font-black text-white">Editorial</td>
                <td class="px-5 py-4"><span class="break-all font-orbitron text-xs font-black text-white"><?= htmlspecialchars((string) ($item['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></td>
                <td class="px-5 py-4 text-slate-300"><?= (int) ($stats['posts'] ?? 0) ?> posts<br><span class="text-xs text-slate-500"><?= (int) ($stats['links'] ?? 0) ?> links · <?= (int) ($uploads['included_files'] ?? $stats['uploads'] ?? 0) ?> arquivo(s)</span></td>
                <td class="px-5 py-4 text-slate-300"><?= htmlspecialchars($formatBytes($packageBytes($item)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                <td class="px-5 py-4"><span class="rounded-full border px-3 py-1 text-[0.65rem] font-black uppercase tracking-[0.18em] <?= $badgeClass($statusInfo['tone']) ?>"><?= htmlspecialchars($statusInfo['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></td>
                <td class="px-5 py-4">
                  <div class="flex min-w-[13rem] flex-col gap-2">
                    <?= $buildActionForm(
                        'verify',
                        ['package_id' => (string) ($item['package_id'] ?? '')],
                        'Verificar',
                        'inline-flex w-full items-center justify-center rounded-xl border border-emerald-400/35 bg-emerald-500/10 px-3 py-2 text-xs font-black text-emerald-100 transition hover:border-emerald-300 hover:bg-emerald-500/20',
                        'Verificando pacote editorial',
                        'Conferindo manifesto, JSONs e uploads do pacote selecionado.',
                        'Verificacao editorial'
                    ) ?>
                    <?php if (!(bool) ($item['cloud_uploaded'] ?? false)): ?>
                      <form method="POST" action="<?= url('/admin/central-operacional-v2/backup-em-nuvem') ?>" class="editorial-action-form" data-progress-title="Enviando pacote editorial" data-progress-message="Validando conexao Dropbox e preparando o pacote selecionado para envio." data-progress-stage="Envio para nuvem">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="dropbox_upload_editorial_package">
                        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($redirectTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                        <input type="hidden" name="package_id" value="<?= htmlspecialchars((string) ($item['package_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-sky-400/35 bg-sky-500/10 px-3 py-2 text-xs font-black text-sky-100 transition hover:border-sky-300 hover:bg-sky-500/20">Enviar nuvem</button>
                      </form>
                    <?php endif; ?>
                    <form method="POST" action="<?= url('/local/conteudo') ?>" class="editorial-action-form rounded-xl border border-rose-400/25 bg-rose-500/5 p-2" data-progress-title="Excluindo pacote editorial" data-progress-message="Removendo somente a pasta local do pacote selecionado." data-progress-stage="Exclusao local">
                      <?= Csrf::field() ?>
                      <input type="hidden" name="action" value="delete_local_package">
                      <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($redirectTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      <input type="hidden" name="package_id" value="<?= htmlspecialchars((string) ($item['package_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      <input type="text" name="delete_confirmation" placeholder="Confirmar ID para excluir" class="w-full rounded-lg border border-slate-700 bg-slate-950/80 px-2 py-2 text-xs text-white outline-none focus:border-rose-400">
                      <button type="submit" class="mt-2 inline-flex w-full items-center justify-center rounded-lg border border-rose-400/30 bg-transparent px-3 py-2 text-xs font-semibold text-rose-200 transition hover:bg-rose-500/10">Excluir local</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="mt-5 flex flex-col gap-3 rounded-2xl border border-cyan-500/20 bg-slate-950/70 px-4 py-3 text-sm text-slate-300 md:flex-row md:items-center md:justify-between">
      <span><?= $totalRows ?> registro(s) · pagina <?= $page ?> de <?= $totalPages ?></span>
      <div class="flex gap-2">
        <?php
          $pageUrl = static function (int $target) use ($sectionUrl, $search, $environment, $historyStatus, $sort, $dir, $perPage): string {
              return $sectionUrl('historico', [
                  'editorial_busca' => $search,
                  'editorial_ambiente' => $environment,
                  'editorial_status' => $historyStatus,
                  'editorial_sort' => $sort,
                  'editorial_dir' => $dir,
                  'editorial_per_page' => $perPage,
                  'editorial_page' => $target,
              ]);
          };
        ?>
        <a data-editorial-link="true" class="rounded-xl border border-slate-700 px-3 py-2 transition hover:border-cyan-400 hover:text-cyan-100 <?= $page <= 1 ? 'pointer-events-none opacity-40' : '' ?>" href="<?= htmlspecialchars($pageUrl(max(1, $page - 1)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Anterior</a>
        <a data-editorial-link="true" class="rounded-xl border border-slate-700 px-3 py-2 transition hover:border-cyan-400 hover:text-cyan-100 <?= $page >= $totalPages ? 'pointer-events-none opacity-40' : '' ?>" href="<?= htmlspecialchars($pageUrl(min($totalPages, $page + 1)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Proxima</a>
      </div>
    </div>
    </div>
  <?php endif; ?>
</section>
