<?php

declare(strict_types=1);

use App\Support\View;

$overview = is_array($overview ?? null) ? $overview : [];
$facts = is_array($overview['facts'] ?? null) ? $overview['facts'] : [];
$environments = is_array($overview['environments'] ?? null) ? $overview['environments'] : [];
$observabilityEnvironments = is_array($overview['observability_environments'] ?? null) ? $overview['observability_environments'] : [];

$systemOk = 0;
$editorialOk = 0;
$editorialUploads = 0;
foreach ($environments as $environment) {
    $system = is_array($environment['system'] ?? null) ? $environment['system'] : [];
    $editorial = is_array($environment['editorial'] ?? null) ? $environment['editorial'] : [];
    if (($system['status'] ?? '') === 'OK') {
        $systemOk++;
    }
    if (($editorial['status'] ?? '') === 'OK') {
        $editorialOk++;
    }

    $uploads = (string) ($editorial['uploads'] ?? '');
    if (preg_match('/^(\d+)/', $uploads, $match)) {
        $editorialUploads += (int) $match[1];
    }
}

$eventTotal = 0;
foreach ($observabilityEnvironments as $environment) {
    $eventTotal += (int) ($environment['events'] ?? 0);
}

$modules = [
    [
        'label' => 'Backup Sistemico',
        'description' => 'Banco, sistema, restore e historico tecnico.',
        'href' => url('/admin/central-operacional-v2/backup-sistemico/resumo'),
        'icon' => 'fa-solid fa-database',
        'metric' => $systemOk . ' / 3',
        'metric_label' => 'ambientes com leitura',
        'tone' => $systemOk >= 3 ? 'success' : 'neutral',
    ],
    [
        'label' => 'Backup Editorial',
        'description' => 'Pacotes editoriais, conteudo, restore e historico.',
        'href' => url('/admin/central-operacional-v2/backup-editorial/resumo'),
        'icon' => 'fa-solid fa-newspaper',
        'metric' => $editorialOk . ' / 3',
        'metric_label' => 'ambientes com pacote',
        'tone' => $editorialOk >= 3 ? 'success' : 'neutral',
    ],
    [
        'label' => 'Backup em Nuvem',
        'description' => 'Dropbox, automacao e historico de envios.',
        'href' => url('/admin/central-operacional-v2/backup-em-nuvem/resumo'),
        'icon' => 'fa-solid fa-cloud-arrow-up',
        'metric' => 'Dropbox',
        'metric_label' => 'sincronizacao',
        'tone' => 'info',
    ],
    [
        'label' => 'Observabilidade',
        'description' => 'Logs, smoke tests e sinais operacionais.',
        'href' => url('/admin/central-operacional-v2/observabilidade'),
        'icon' => 'fa-solid fa-wave-square',
        'metric' => (string) $eventTotal,
        'metric_label' => 'eventos recentes',
        'tone' => $eventTotal > 0 ? 'warning' : 'success',
    ],
    [
        'label' => 'SEO Tecnico',
        'description' => 'Search Console, sitemaps, indexacao e URLs criticas.',
        'href' => url('/admin/central-operacional-v2/seo-tecnico/resumo'),
        'icon' => 'fa-solid fa-magnifying-glass-chart',
        'metric' => 'GSC',
        'metric_label' => 'monitoramento',
        'tone' => 'info',
    ],
];

$alerts = [];
foreach ($environments as $environment) {
    $label = (string) ($environment['label'] ?? 'Ambiente');
    $system = is_array($environment['system'] ?? null) ? $environment['system'] : [];
    $editorial = is_array($environment['editorial'] ?? null) ? $environment['editorial'] : [];
    $systemOkForEnv = (string) ($system['status'] ?? '') === 'OK';
    $editorialOkForEnv = (string) ($editorial['status'] ?? '') === 'OK';
    $alerts[] = [
        'label' => $label . ': sistema ' . ($systemOkForEnv ? 'OK' : 'pendente') . ', editorial ' . ($editorialOkForEnv ? 'OK' : 'pendente'),
        'tone' => $systemOkForEnv && $editorialOkForEnv ? 'success' : 'neutral',
    ];
}

if ($eventTotal > 0) {
    $alerts[] = [
        'label' => 'Observabilidade com ' . $eventTotal . ' evento(s) recente(s)',
        'tone' => 'warning',
    ];
}

$badgeClasses = static function (string $tone): string {
    return match ($tone) {
        'success' => 'border-emerald-500/25 bg-emerald-500/10 text-emerald-100',
        'warning' => 'border-amber-500/25 bg-amber-500/10 text-amber-100',
        'info' => 'border-cyan-500/25 bg-cyan-500/10 text-cyan-100',
        default => 'border-slate-700 bg-slate-900/75 text-slate-300',
    };
};
?>
<section class="space-y-5">
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
        <strong>Hub</strong>
      </span>
    </div>
  </div>

  <div class="grid gap-4 xl:grid-cols-5">
    <?php foreach ($modules as $module): ?>
      <a href="<?= htmlspecialchars((string) $module['href'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="group flex min-h-[13rem] flex-col justify-between rounded-[1.35rem] border border-slate-800 bg-slate-900/85 p-5 shadow-[0_0_28px_rgba(2,6,23,0.18)] transition hover:border-cyan-400/45 hover:bg-slate-950">
        <div>
          <div class="flex items-center justify-between gap-3">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-cyan-500/25 bg-cyan-500/10 text-cyan-200">
              <i class="<?= htmlspecialchars((string) $module['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-hidden="true"></i>
            </span>
            <span class="rounded-full border px-3 py-1 text-[0.62rem] font-black uppercase tracking-[0.16em] <?= $badgeClasses((string) $module['tone']) ?>">
              <?= htmlspecialchars((string) $module['metric'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </span>
          </div>
          <h2 class="mt-4 font-orbitron text-base font-black text-white"><?= htmlspecialchars((string) $module['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
          <p class="mt-2 text-sm leading-6 text-slate-400"><?= htmlspecialchars((string) $module['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        </div>
        <div class="mt-5 flex items-center justify-between gap-3 text-xs">
          <span class="font-semibold text-slate-500"><?= htmlspecialchars((string) $module['metric_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          <span class="inline-flex items-center gap-2 font-black uppercase tracking-[0.12em] text-cyan-200 transition group-hover:text-white">
            Abrir
            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]" aria-hidden="true"></i>
          </span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
    <div class="rounded-[1.25rem] border border-slate-800 bg-slate-950/70 p-4">
      <h3 class="font-orbitron text-xs font-black uppercase tracking-[0.18em] text-cyan-300/70">Ambientes</h3>
      <div class="mt-4 grid gap-3 md:grid-cols-3">
        <?php foreach ($environments as $environment): ?>
          <?php
            $system = is_array($environment['system'] ?? null) ? $environment['system'] : [];
            $editorial = is_array($environment['editorial'] ?? null) ? $environment['editorial'] : [];
          ?>
          <article class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
            <div class="flex items-center justify-between gap-2">
              <h4 class="font-orbitron text-sm font-black text-white"><?= htmlspecialchars((string) ($environment['label'] ?? 'Ambiente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h4>
              <?php View::component('admin/v2/status-badge', [
                  'label' => (string) ($environment['status'] ?? 'Sem leitura'),
                  'tone' => (string) ($environment['tone'] ?? 'neutral'),
              ]); ?>
            </div>
            <div class="mt-4 space-y-2 text-xs">
              <div class="flex justify-between gap-3 rounded-xl border border-slate-800 bg-slate-950/70 px-3 py-2">
                <span class="text-slate-500">Sistema</span>
                <strong class="text-slate-100"><?= htmlspecialchars((string) ($system['status'] ?? 'Pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
              </div>
              <div class="flex justify-between gap-3 rounded-xl border border-slate-800 bg-slate-950/70 px-3 py-2">
                <span class="text-slate-500">Editorial</span>
                <strong class="text-slate-100"><?= htmlspecialchars((string) ($editorial['status'] ?? 'Pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="rounded-[1.25rem] border border-slate-800 bg-slate-950/70 p-4">
      <h3 class="font-orbitron text-xs font-black uppercase tracking-[0.18em] text-cyan-300/70">Sinais</h3>
      <div class="mt-4 grid gap-2">
        <?php foreach (array_slice($alerts, 0, 5) as $alert): ?>
          <div class="rounded-xl border px-3 py-2 text-xs font-semibold <?= $badgeClasses((string) ($alert['tone'] ?? 'neutral')) ?>">
            <?= htmlspecialchars((string) ($alert['label'] ?? 'Leitura pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
