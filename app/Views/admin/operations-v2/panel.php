<?php

declare(strict_types=1);

use App\Support\View;

$overview = is_array($overview ?? null) ? $overview : [];
$facts = is_array($overview['facts'] ?? null) ? $overview['facts'] : [];
$environments = is_array($overview['environments'] ?? null) ? $overview['environments'] : [];
$observabilityEnvironments = is_array($overview['observability_environments'] ?? null) ? $overview['observability_environments'] : [];

$activePanel = strtolower(trim((string) ($_GET['painel'] ?? 'backup-sistemico')));
$allowedPanels = ['backup-sistemico', 'backup-editorial', 'observabilidade'];
if (!in_array($activePanel, $allowedPanels, true)) {
    $activePanel = 'backup-sistemico';
}

$panelTabs = [
    'backup-sistemico' => 'Backup Sistêmico',
    'backup-editorial' => 'Backup Editorial',
    'observabilidade' => 'Observabilidade',
];

$mainScreens = [
    'backup-sistemico' => [
        'label' => 'Abrir Backup Sistêmico',
        'href' => url('/admin/central-operacional-v2/backup-sistemico'),
    ],
    'backup-editorial' => [
        'label' => 'Abrir Backup Editorial',
        'href' => url('/admin/central-operacional-v2/backup-editorial'),
    ],
    'observabilidade' => [
        'label' => 'Abrir Observabilidade',
        'href' => url('/admin/central-operacional-v2/observabilidade'),
    ],
];

$factValue = static function (array $facts, string $label, string $fallback = 'Leitura pendente'): string {
    foreach ($facts as $fact) {
        if ((string) ($fact['label'] ?? '') === $label) {
            $value = trim((string) ($fact['value'] ?? ''));

            return $value !== '' ? $value : $fallback;
        }
    }

    return $fallback;
};

$metricStatus = static function (string $status): string {
    return $status === 'OK' ? 'OK' : 'Leitura pendente';
};

$systemOk = 0;
$editorialOk = 0;
foreach ($environments as $environment) {
    $system = is_array($environment['system'] ?? null) ? $environment['system'] : [];
    $editorial = is_array($environment['editorial'] ?? null) ? $environment['editorial'] : [];
    if (($system['status'] ?? '') === 'OK') {
        $systemOk++;
    }
    if (($editorial['status'] ?? '') === 'OK') {
        $editorialOk++;
    }
}

$eventTotal = 0;
$activeEventEnvironments = 0;
foreach ($observabilityEnvironments as $environment) {
    $events = (int) ($environment['events'] ?? 0);
    $eventTotal += $events;
    if ($events > 0) {
        $activeEventEnvironments++;
    }
}

$lastRead = $factValue($facts, 'Última Leitura');
$editorialUploads = 0;
foreach ($environments as $environment) {
    $editorial = is_array($environment['editorial'] ?? null) ? $environment['editorial'] : [];
    $uploads = (string) ($editorial['uploads'] ?? '');
    if (preg_match('/^(\d+)/', $uploads, $match)) {
        $editorialUploads += (int) $match[1];
    }
}

$summaryCards = match ($activePanel) {
    'backup-editorial' => [
        ['label' => 'Pacotes OK', 'value' => $editorialOk . ' / 3'],
        ['label' => 'Uploads', 'value' => $editorialUploads > 0 ? $editorialUploads . ' arquivos' : 'Leitura pendente'],
        ['label' => 'Mídia', 'value' => $editorialUploads > 0 ? 'Com leitura' : 'Leitura pendente'],
        ['label' => 'Pendências', 'value' => (string) (3 - $editorialOk)],
    ],
    'observabilidade' => [
        ['label' => 'Eventos Hoje', 'value' => (string) $eventTotal],
        ['label' => 'Alertas Críticos', 'value' => 'Leitura pendente'],
        ['label' => 'Warnings', 'value' => $eventTotal > 0 ? (string) $eventTotal : '0'],
        ['label' => 'Saúde Geral', 'value' => $eventTotal > 0 ? 'Atenção' : 'Boa'],
    ],
    default => [
        ['label' => 'Ambientes OK', 'value' => $systemOk . ' / 3'],
        ['label' => 'Pendentes', 'value' => (string) (3 - $systemOk)],
        ['label' => 'Última Leitura', 'value' => $lastRead],
        ['label' => 'Origem', 'value' => 'Backups Sistêmicos'],
    ],
};

$buildAlerts = static function (string $panel, array $environments, array $observabilityEnvironments): array {
    $alerts = [];

    if ($panel === 'observabilidade') {
        foreach ($observabilityEnvironments as $environment) {
            $events = (int) ($environment['events'] ?? 0);
            $label = (string) ($environment['label'] ?? 'Ambiente');
            $alerts[] = [
                'label' => $events > 0 ? $label . ' com ' . $events . ' evento(s)' : $label . ' sem eventos recentes',
                'tone' => $events > 0 ? 'warning' : 'success',
            ];
        }

        return $alerts;
    }

    $key = $panel === 'backup-editorial' ? 'editorial' : 'system';
    $name = $panel === 'backup-editorial' ? 'editorial' : 'sistêmico';
    foreach ($environments as $environment) {
        $data = is_array($environment[$key] ?? null) ? $environment[$key] : [];
        $label = (string) ($environment['label'] ?? 'Ambiente');
        $ok = (string) ($data['status'] ?? '') === 'OK';
        $alerts[] = [
            'label' => $ok ? $label . ' com backup ' . $name . ' disponível' : $label . ' com leitura ' . $name . ' pendente',
            'tone' => $ok ? 'success' : 'neutral',
        ];
    }

    return $alerts;
};

$alerts = $buildAlerts($activePanel, $environments, $observabilityEnvironments);
$mainScreen = $mainScreens[$activePanel] ?? $mainScreens['backup-sistemico'];
?>
<section class="space-y-5">
  <div class="grid gap-2 rounded-[1.25rem] border border-slate-800 bg-slate-950/70 p-2 md:grid-cols-3">
    <?php foreach ($panelTabs as $key => $label): ?>
      <?php $isActive = $key === $activePanel; ?>
      <a
        href="<?= htmlspecialchars(url('/admin/central-operacional-v2?painel=' . $key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
        class="flex min-h-11 items-center rounded-xl border px-4 py-3 text-left transition <?= $isActive ? 'border-cyan-400/45 bg-cyan-500/10 text-cyan-100 shadow-[0_0_22px_rgba(34,211,238,0.12)]' : 'border-slate-800 bg-slate-900/70 text-slate-300 hover:border-cyan-500/35 hover:bg-cyan-500/10 hover:text-cyan-100' ?>"
        aria-current="<?= $isActive ? 'page' : 'false' ?>"
        data-operations-v2-tab
      >
        <span class="font-orbitron text-xs font-black uppercase tracking-[0.14em]"><?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="rounded-[1.25rem] border border-slate-800/90 bg-slate-950/70 p-3 shadow-[0_0_28px_rgba(2,6,23,0.22)]">
    <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-5">
      <?php foreach ($facts as $fact): ?>
        <span class="flex min-h-12 items-center justify-between gap-3 rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-3 text-xs font-semibold text-slate-300">
          <span class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500"><?= htmlspecialchars((string) ($fact['label'] ?? 'Info'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          <strong class="truncate text-right text-slate-100"><?= htmlspecialchars((string) ($fact['value'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
        </span>
      <?php endforeach; ?>
      <span class="flex min-h-12 items-center justify-between gap-3 rounded-2xl border border-cyan-500/25 bg-cyan-500/10 px-4 py-3 text-xs font-semibold text-cyan-100">
        <span class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-cyan-200/70">Modo</span>
        <strong>Somente Leitura</strong>
      </span>
    </div>
  </div>

  <div class="rounded-[1.6rem] border border-slate-800 bg-slate-900/85 p-5 shadow-[0_0_34px_rgba(2,6,23,0.18)]">
  <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
    <?php foreach ($summaryCards as $card): ?>
      <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-cyan-300/65"><?= htmlspecialchars($card['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <div class="mt-2 text-xl font-black text-white"><?= htmlspecialchars($card['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="mt-4 flex justify-end">
    <a
      href="<?= htmlspecialchars((string) $mainScreen['href'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
      class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-cyan-400/35 bg-cyan-500/10 px-4 py-2 text-xs font-black uppercase tracking-[0.12em] text-cyan-100 transition hover:border-cyan-300 hover:bg-cyan-500/18 hover:text-white"
    >
      <i class="fa-solid fa-arrow-up-right-from-square text-[11px]" aria-hidden="true"></i>
      <?= htmlspecialchars((string) $mainScreen['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </a>
  </div>

  <?php if ($activePanel === 'observabilidade'): ?>
    <div class="mt-5 grid gap-4 md:grid-cols-3">
      <?php foreach ($observabilityEnvironments as $environment): ?>
        <?php
          $categories = is_array($environment['categories'] ?? null) ? $environment['categories'] : [];
          $isProduction = (string) ($environment['key'] ?? '') === 'production';
        ?>
        <article class="group rounded-[1.25rem] border <?= $isProduction ? 'border-amber-400/40 bg-amber-400/[0.035] shadow-[0_0_24px_rgba(251,191,36,0.08)]' : 'border-slate-800 bg-slate-950/75' ?> p-5 transition hover:border-cyan-500/35 hover:bg-slate-950">
          <div class="flex items-center justify-between gap-3">
            <h3 class="font-orbitron text-base font-black text-white"><?= htmlspecialchars((string) ($environment['label'] ?? 'Ambiente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
            <?php View::component('admin/v2/status-badge', [
                'label' => (string) ($environment['status'] ?? 'Sem eventos'),
                'tone' => (string) ($environment['tone'] ?? 'neutral'),
            ]); ?>
          </div>

          <div class="mt-5 grid gap-3">
            <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
              <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Eventos Recentes</div>
              <div class="mt-2 text-2xl font-black text-white"><?= (int) ($environment['events'] ?? 0) ?></div>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
              <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Última Leitura</div>
              <div class="mt-2 text-xs font-semibold leading-5 text-slate-200"><?= htmlspecialchars((string) ($environment['latest'] ?? 'Sem leitura recente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            </div>
          </div>

          <div class="mt-4 space-y-2 text-xs">
            <?php if ($categories === []): ?>
              <div class="rounded-xl border border-slate-800 bg-slate-900/60 px-3 py-2 text-slate-400">Sem eventos classificados</div>
            <?php endif; ?>
            <?php foreach ($categories as $category => $count): ?>
              <div class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-900/60 px-3 py-2">
                <span class="text-slate-400"><?= htmlspecialchars((string) $category, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <strong class="text-slate-100"><?= (int) $count ?></strong>
              </div>
            <?php endforeach; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="mt-5 grid gap-4 md:grid-cols-3">
      <?php foreach ($environments as $environment): ?>
        <?php
          $dataKey = $activePanel === 'backup-editorial' ? 'editorial' : 'system';
          $data = is_array($environment[$dataKey] ?? null) ? $environment[$dataKey] : [];
          $status = (string) ($data['status'] ?? 'Leitura pendente');
          $isOk = $status === 'OK';
          $isProduction = (string) ($environment['key'] ?? '') === 'production';
          $rows = $activePanel === 'backup-editorial'
              ? [
                  ['label' => 'Pacote Editorial', 'value' => (string) ($data['id'] ?? 'Leitura pendente')],
                  ['label' => 'Posts', 'value' => (string) ($data['posts'] ?? 'Leitura pendente')],
                  ['label' => 'Links', 'value' => (string) ($data['links'] ?? 'Leitura pendente')],
                  ['label' => 'Uploads', 'value' => (string) ($data['uploads'] ?? 'Leitura pendente')],
                  ['label' => 'Última Exportação', 'value' => (string) ($data['date'] ?? 'Leitura pendente')],
                  ['label' => 'Status', 'value' => $metricStatus($status)],
              ]
              : [
                  ['label' => 'Identificador', 'value' => (string) ($data['id'] ?? 'Leitura pendente')],
                  ['label' => 'Data/Hora', 'value' => (string) ($data['date'] ?? 'Leitura pendente')],
                  ['label' => 'Tamanho', 'value' => (string) ($data['size'] ?? 'Leitura pendente')],
                  ['label' => 'Perfil', 'value' => (string) ($data['profile'] ?? 'Leitura pendente')],
                  ['label' => 'Enviado Nuvem', 'value' => (string) ($data['cloud'] ?? 'Leitura pendente')],
                  ['label' => 'Retenção Local', 'value' => 'Leitura pendente'],
              ];
        ?>
        <article class="group rounded-[1.25rem] border <?= $isProduction ? 'border-amber-400/40 bg-amber-400/[0.035] shadow-[0_0_24px_rgba(251,191,36,0.08)]' : 'border-slate-800 bg-slate-950/75' ?> p-5 transition hover:border-cyan-500/35 hover:bg-slate-950">
          <div class="flex items-center justify-between gap-3">
            <h3 class="font-orbitron text-base font-black text-white"><?= htmlspecialchars((string) ($environment['label'] ?? 'Ambiente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
            <?php View::component('admin/v2/status-badge', [
                'label' => $isOk ? 'OK' : 'Leitura Pendente',
                'tone' => $isOk ? 'success' : 'neutral',
            ]); ?>
          </div>

          <div class="mt-5 grid gap-3">
            <?php foreach ($rows as $row): ?>
              <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
                <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500"><?= htmlspecialchars($row['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <p class="mt-2 break-words text-xs font-semibold leading-5 text-slate-200"><?= htmlspecialchars($row['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="mt-5 rounded-[1.25rem] border border-slate-800 bg-slate-950/70 p-4">
    <h3 class="font-orbitron text-xs font-black uppercase tracking-[0.18em] text-cyan-300/70">Alertas Operacionais</h3>
    <div class="mt-3 grid gap-2 md:grid-cols-3">
      <?php foreach ($alerts as $alert): ?>
        <?php
          $tone = (string) ($alert['tone'] ?? 'neutral');
          $classes = $tone === 'success'
              ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-100'
              : ($tone === 'warning'
                  ? 'border-amber-500/25 bg-amber-500/10 text-amber-100'
                  : 'border-slate-700 bg-slate-900/75 text-slate-300');
        ?>
        <div class="rounded-xl border px-3 py-2 text-xs font-semibold <?= $classes ?>">
          <?= htmlspecialchars((string) ($alert['label'] ?? 'Leitura pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  </div>
</section>
