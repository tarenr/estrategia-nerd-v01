<?php
declare(strict_types=1);

$summary = $summary ?? [];
$environments = $environments ?? [];
$executionEnvironmentLabel = (string) ($execution_environment_label ?? environment_label(current_environment()));

$statusCardClass = static function (string $status): string {
    return match ($status) {
        'ok' => 'border-emerald-500/25 bg-emerald-500/10 text-emerald-200',
        'warn' => 'border-amber-500/25 bg-amber-500/10 text-amber-200',
        default => 'border-rose-500/25 bg-rose-500/10 text-rose-200',
    };
};

$statusBadgeClass = static function (string $status): string {
    return match ($status) {
        'ok' => 'border-emerald-500/25 bg-emerald-500/15 text-emerald-100',
        'warn' => 'border-amber-500/25 bg-amber-500/15 text-amber-100',
        default => 'border-rose-500/25 bg-rose-500/15 text-rose-100',
    };
};
?>

<?php
$environmentTabIds = [];
foreach ($environments as $index => $environment) {
    $label = strtolower((string) ($environment['key'] ?? $environment['label'] ?? ('ambiente-' . $index)));
    $label = preg_replace('/[^a-z0-9]+/', '-', $label) ?? ('ambiente-' . $index);
    $label = trim($label, '-');
    if ($label === '') {
        $label = 'ambiente-' . $index;
    }

    $environmentTabIds[$index] = $label;
}
?>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6" data-audit-tabs>
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Auditoria Geral</h1>
      <div class="admin-page-subtitle">
        Leitura centralizada no ambiente local para comparar local, stage e producao sem depender do seletor global.
      </div>
    </div>
    <div class="admin-page-actions">
      <a href="<?= htmlspecialchars(url('/admin/auditoria-geral'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary" data-admin-audit-trigger="1">Rodar novamente</a>
    </div>
  </div>

  <section class="admin-panel">
    <div class="flex flex-wrap items-center gap-3">
      <span class="inline-flex items-center gap-2 rounded-full border border-slate-700 bg-slate-900/70 px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-slate-200">
        <span class="text-slate-400">Execucao</span>
        <span><?= htmlspecialchars($executionEnvironmentLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
      </span>
      <span class="inline-flex items-center gap-2 rounded-full border border-cyan-500/25 bg-cyan-500/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-cyan-100">
        <span>Auditoria multiambiente</span>
      </span>
    </div>
    <div class="mt-3 text-sm text-slate-400">
      Esta pagina ignora o ambiente-alvo do topo. Ela sempre audita os tres ambientes configurados na base local.
    </div>
  </section>

  <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
    <article class="admin-panel">
      <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Status geral</div>
      <div class="mt-3 font-orbitron text-2xl font-black <?= (($summary['overall_status'] ?? 'ok') === 'fail') ? 'text-rose-300' : ((($summary['overall_status'] ?? 'ok') === 'warn') ? 'text-amber-300' : 'text-emerald-300') ?>">
        <?= htmlspecialchars(strtoupper((string) ($summary['overall_status'] ?? 'ok')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
      </div>
      <div class="mt-2 text-sm text-slate-400"><?= htmlspecialchars((string) ($summary['headline'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    </article>
    <article class="admin-panel">
      <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Ambientes OK</div>
      <div class="mt-3 font-orbitron text-4xl font-black text-emerald-300"><?= (int) ($summary['environments_ok'] ?? 0) ?></div>
      <div class="mt-2 text-sm text-slate-400">Ambientes sem alerta relevante nesta leitura.</div>
    </article>
    <article class="admin-panel">
      <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Ambientes com alerta</div>
      <div class="mt-3 font-orbitron text-4xl font-black text-amber-300"><?= (int) ($summary['environments_warn'] ?? 0) ?></div>
      <div class="mt-2 text-sm text-slate-400">Ambientes usaveis, mas com pendencias acompanhaveis.</div>
    </article>
    <article class="admin-panel">
      <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Achados criticos</div>
      <div class="mt-3 font-orbitron text-4xl font-black text-rose-300"><?= (int) ($summary['critical_findings'] ?? 0) ?></div>
      <div class="mt-2 text-sm text-slate-400">Problemas que podem bloquear stage ou producao.</div>
    </article>
    <article class="admin-panel">
      <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Duracao</div>
      <div class="mt-3 font-orbitron text-4xl font-black text-white"><?= (int) ($summary['duration_ms'] ?? 0) ?>ms</div>
      <div class="mt-2 text-sm text-slate-400">Ultima leitura em <?= htmlspecialchars((string) ($summary['checked_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>.</div>
    </article>
  </section>

  <section class="admin-panel">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h2 class="font-orbitron text-lg font-black text-white">Ambientes auditados</h2>
        <div class="mt-1 text-sm text-slate-400">Abra um ambiente por vez para ler os achados com mais foco e menos rolagem.</div>
      </div>
      <div class="flex flex-wrap gap-2" role="tablist" aria-label="Ambientes da auditoria">
        <?php foreach ($environments as $index => $environment): ?>
          <?php
            $status = (string) ($environment['status'] ?? 'warn');
            $tabId = $environmentTabIds[$index] ?? ('ambiente-' . $index);
            $isFirstTab = $index === 0;
            $tabClasses = $isFirstTab
                ? 'border-cyan-400/40 bg-cyan-500/15 text-cyan-100 shadow-[0_0_24px_rgba(34,211,238,0.12)]'
                : 'border-slate-700 bg-slate-900/60 text-slate-300 hover:border-cyan-500/20 hover:text-cyan-200';
            $statusDotClass = match ($status) {
                'ok' => 'bg-emerald-300',
                'warn' => 'bg-amber-300',
                default => 'bg-rose-300',
            };
          ?>
          <button
            type="button"
            role="tab"
            id="audit-tab-<?= htmlspecialchars($tabId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            class="inline-flex items-center gap-3 rounded-2xl border px-4 py-3 text-sm font-bold transition <?= $tabClasses ?>"
            data-audit-tab
            data-audit-target="<?= htmlspecialchars($tabId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            aria-selected="<?= $isFirstTab ? 'true' : 'false' ?>"
            aria-controls="audit-panel-<?= htmlspecialchars($tabId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            tabindex="<?= $isFirstTab ? '0' : '-1' ?>"
          >
            <span class="inline-flex h-2.5 w-2.5 rounded-full <?= $statusDotClass ?>"></span>
            <span><?= htmlspecialchars((string) ($environment['label'] ?? 'Ambiente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            <span class="inline-flex items-center rounded-full bg-slate-950/50 px-2 py-0.5 text-[11px] uppercase tracking-[0.14em] text-slate-300">
              <?= htmlspecialchars(strtoupper($status), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </span>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <div class="space-y-6">
    <?php foreach ($environments as $index => $environment): ?>
      <?php
        $tabId = $environmentTabIds[$index] ?? ('ambiente-' . $index);
        $isFirstPanel = $index === 0;
        $envSummary = (array) ($environment['summary'] ?? []);
        $checks = (array) ($environment['checks'] ?? []);
        $criticalFindings = (array) ($environment['critical_findings'] ?? []);
        $warningFindings = (array) ($environment['warning_findings'] ?? []);
        $metrics = (array) ($environment['metrics'] ?? []);
        $status = (string) ($environment['status'] ?? 'warn');
      ?>
      <section
        id="audit-panel-<?= htmlspecialchars($tabId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
        class="admin-panel space-y-5<?= $isFirstPanel ? '' : ' hidden' ?>"
        role="tabpanel"
        aria-labelledby="audit-tab-<?= htmlspecialchars($tabId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
        data-audit-panel
        data-audit-panel-id="<?= htmlspecialchars($tabId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
      >
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
          <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-3">
              <h2 class="font-orbitron text-xl font-black text-white"><?= htmlspecialchars((string) ($environment['label'] ?? 'Ambiente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
              <span class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em] <?= $statusBadgeClass($status) ?>">
                <?= htmlspecialchars(strtoupper($status), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
              </span>
            </div>
            <div class="text-sm text-slate-400"><?= htmlspecialchars((string) ($envSummary['headline'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          </div>

          <div class="grid grid-cols-2 md:grid-cols-5 gap-3 min-w-0 xl:min-w-[520px]">
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
              <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Posts</div>
              <div class="mt-2 font-orbitron text-2xl font-black text-white"><?= (int) ($metrics['published_posts'] ?? 0) ?></div>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
              <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Categorias</div>
              <div class="mt-2 font-orbitron text-2xl font-black text-white"><?= (int) ($metrics['categories'] ?? 0) ?></div>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
              <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Links</div>
              <div class="mt-2 font-orbitron text-2xl font-black text-white"><?= (int) ($metrics['links'] ?? 0) ?></div>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
              <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Criticos</div>
              <div class="mt-2 font-orbitron text-2xl font-black text-rose-300"><?= (int) ($envSummary['critical'] ?? 0) ?></div>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
              <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Alertas</div>
              <div class="mt-2 font-orbitron text-2xl font-black text-amber-300"><?= (int) ($envSummary['warning'] ?? 0) ?></div>
            </div>
          </div>
        </div>

        <div class="space-y-4">
          <div class="rounded-2xl border border-rose-500/20 bg-rose-500/10 p-4">
            <div class="flex items-center justify-between gap-3">
              <h3 class="font-orbitron text-base font-black text-white">Achados criticos</h3>
              <span class="inline-flex items-center rounded-full bg-rose-500/20 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em] text-rose-100">
                <?= count($criticalFindings) ?>
              </span>
            </div>
            <div class="mt-4 space-y-3">
              <?php if ($criticalFindings === []): ?>
                <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                  Nenhum achado critico entrou na amostra desta leitura.
                </div>
              <?php else: ?>
                <?php foreach ($criticalFindings as $finding): ?>
                  <article class="rounded-2xl border border-rose-500/20 bg-slate-950/40 p-4">
                    <div class="font-bold text-white"><?= htmlspecialchars((string) ($finding['title'] ?? 'Achado'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    <div class="mt-2 text-sm leading-6 text-slate-300"><?= htmlspecialchars((string) ($finding['detail'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  </article>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="rounded-2xl border border-amber-500/20 bg-amber-500/10 p-4">
            <div class="flex items-center justify-between gap-3">
              <h3 class="font-orbitron text-base font-black text-white">Alertas</h3>
              <span class="inline-flex items-center rounded-full bg-amber-500/20 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em] text-amber-100">
                <?= count($warningFindings) ?>
              </span>
            </div>
            <div class="mt-4 space-y-3">
              <?php if ($warningFindings === []): ?>
                <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
                  Nenhum alerta adicional entrou na amostra desta leitura.
                </div>
              <?php else: ?>
                <?php foreach ($warningFindings as $finding): ?>
                  <article class="rounded-2xl border border-amber-500/20 bg-slate-950/40 p-4">
                    <div class="font-bold text-white"><?= htmlspecialchars((string) ($finding['title'] ?? 'Alerta'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    <div class="mt-2 text-sm leading-6 text-slate-300"><?= htmlspecialchars((string) ($finding['detail'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  </article>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="space-y-4">
            <div>
              <h3 class="font-orbitron text-base font-black text-white">Checks principais</h3>
              <div class="mt-1 text-xs text-slate-400">Leitura editorial, storage e rotas criticas do ambiente.</div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
              <?php foreach ($checks as $check): ?>
                <?php $checkStatus = (string) ($check['status'] ?? 'warn'); ?>
                <article class="rounded-2xl border p-4 <?= $statusCardClass($checkStatus) ?>">
                  <div class="flex items-center justify-between gap-3">
                    <div class="font-bold text-white"><?= htmlspecialchars((string) ($check['label'] ?? 'Check'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em] <?= $checkStatus === 'ok' ? 'bg-emerald-500/20 text-emerald-100' : ($checkStatus === 'warn' ? 'bg-amber-500/20 text-amber-100' : 'bg-rose-500/20 text-rose-100') ?>">
                      <?= htmlspecialchars(strtoupper($checkStatus), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </span>
                  </div>
                  <div class="mt-3 text-sm font-bold text-white break-all"><?= htmlspecialchars((string) ($check['value'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <div class="mt-2 text-sm leading-6 text-slate-300"><?= htmlspecialchars((string) ($check['detail'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </article>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-audit-tabs]');
    if (!root) {
      return;
    }

    var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-audit-tab]'));
    var panels = Array.prototype.slice.call(root.querySelectorAll('[data-audit-panel]'));

    if (tabs.length === 0 || panels.length === 0) {
      return;
    }

    var setActiveTab = function (targetId) {
      tabs.forEach(function (tab, index) {
        var isActive = tab.getAttribute('data-audit-target') === targetId;
        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        tab.setAttribute('tabindex', isActive ? '0' : '-1');

        tab.classList.toggle('border-cyan-400/40', isActive);
        tab.classList.toggle('bg-cyan-500/15', isActive);
        tab.classList.toggle('text-cyan-100', isActive);
        tab.classList.toggle('shadow-[0_0_24px_rgba(34,211,238,0.12)]', isActive);
        tab.classList.toggle('border-slate-700', !isActive);
        tab.classList.toggle('bg-slate-900/60', !isActive);
        tab.classList.toggle('text-slate-300', !isActive);

        if (isActive) {
          tabs[index].focus({ preventScroll: true });
        }
      });

      panels.forEach(function (panel) {
        var isActive = panel.getAttribute('data-audit-panel-id') === targetId;
        panel.classList.toggle('hidden', !isActive);
      });
    };

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        setActiveTab(tab.getAttribute('data-audit-target') || '');
      });

      tab.addEventListener('keydown', function (event) {
        var currentIndex = tabs.indexOf(tab);
        if (currentIndex === -1) {
          return;
        }

        var nextIndex = currentIndex;
        if (event.key === 'ArrowRight') {
          nextIndex = (currentIndex + 1) % tabs.length;
        } else if (event.key === 'ArrowLeft') {
          nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
        } else {
          return;
        }

        event.preventDefault();
        var nextTab = tabs[nextIndex];
        var nextTarget = nextTab.getAttribute('data-audit-target') || '';
        setActiveTab(nextTarget);
      });
    });
  });
</script>
