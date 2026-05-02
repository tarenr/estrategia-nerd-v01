<?php

declare(strict_types=1);

use App\Support\View;

$module = is_array($module ?? null) ? $module : [];
$observability = is_array($observability ?? null) ? $observability : [];
$facts = is_array($observability['facts'] ?? null) ? $observability['facts'] : [];
$cards = is_array($observability['cards'] ?? null) ? $observability['cards'] : [];
$environments = is_array($observability['environments'] ?? null) ? $observability['environments'] : [];
$smokeHistory = is_array($observability['smoke_history'] ?? null) ? $observability['smoke_history'] : [];
$operationLogs = is_array($observability['operation_logs'] ?? null) ? $observability['operation_logs'] : [];
$applicationLogs = is_array($observability['application_logs'] ?? null) ? $observability['application_logs'] : [];
$alerts = is_array($observability['alerts'] ?? null) ? $observability['alerts'] : [];
?>
<section class="space-y-6">
  <?php View::component('admin/v2/page-header', [
      'eyebrow' => 'Central Operacional V2',
      'title' => (string) ($module['label'] ?? 'Observabilidade'),
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

  <div class="rounded-3xl border border-slate-800 bg-slate-900/70 p-2">
    <div class="grid gap-2 md:grid-cols-4">
      <?php foreach ($facts as $fact): ?>
        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500"><?= htmlspecialchars((string) ($fact['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="mt-2 text-sm font-black text-white"><?= htmlspecialchars((string) ($fact['value'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    <?php foreach ($cards as $card): ?>
      <article class="group rounded-[1.35rem] border border-slate-800 bg-slate-900/80 p-5 shadow-[0_0_28px_rgba(2,6,23,0.18)] transition hover:-translate-y-0.5 hover:border-cyan-400/30 hover:bg-slate-900">
        <div class="flex items-start justify-between gap-4">
          <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-cyan-400/15 bg-cyan-500/10 text-cyan-200">
            <i class="<?= htmlspecialchars((string) ($card['icon'] ?? 'fa-solid fa-chart-line'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-hidden="true"></i>
          </div>
          <?php View::component('admin/v2/status-badge', [
              'label' => (string) ($card['tone'] ?? 'neutral'),
              'tone' => (string) ($card['tone'] ?? 'neutral'),
          ]); ?>
        </div>
        <div class="mt-6 font-orbitron text-[10px] font-black uppercase tracking-[0.16em] text-slate-500"><?= htmlspecialchars((string) ($card['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <div class="mt-2 text-2xl font-black text-white"><?= htmlspecialchars((string) ($card['value'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <div class="mt-2 text-xs font-semibold text-slate-400"><?= htmlspecialchars((string) ($card['hint'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </article>
    <?php endforeach; ?>
  </section>

  <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h2 class="font-orbitron text-lg font-black text-white">Ambientes</h2>
      <?php View::component('admin/v2/status-badge', ['label' => 'Somente leitura', 'tone' => 'info']); ?>
    </div>

    <div class="mt-5 grid gap-4 lg:grid-cols-3">
      <?php foreach ($environments as $environment): ?>
        <?php $isProduction = (string) ($environment['key'] ?? '') === 'production'; ?>
        <article class="rounded-2xl border <?= $isProduction ? 'border-amber-400/35 bg-amber-500/[0.04]' : 'border-slate-800 bg-slate-950/70' ?> p-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] <?= $isProduction ? 'text-amber-200/75' : 'text-cyan-300/70' ?>"><?= htmlspecialchars((string) ($environment['label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <div class="mt-2 text-lg font-black text-white"><?= htmlspecialchars((string) ($environment['status'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            </div>
            <?php View::component('admin/v2/status-badge', [
                'label' => (string) ($environment['status'] ?? 'Pendente'),
                'tone' => (string) ($environment['tone'] ?? 'neutral'),
            ]); ?>
          </div>
          <dl class="mt-5 grid gap-3 text-sm">
            <div class="flex items-center justify-between gap-3 border-t border-slate-800 pt-3">
              <dt class="text-slate-500">Último teste</dt>
              <dd class="text-right font-semibold text-slate-200"><?= htmlspecialchars((string) ($environment['run_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
            </div>
            <div class="flex items-center justify-between gap-3 border-t border-slate-800 pt-3">
              <dt class="text-slate-500">Data</dt>
              <dd class="text-right font-semibold text-slate-200"><?= htmlspecialchars((string) ($environment['last_run'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
            </div>
            <div class="flex items-center justify-between gap-3 border-t border-slate-800 pt-3">
              <dt class="text-slate-500">Resultado</dt>
              <dd class="text-right font-semibold text-slate-200"><?= htmlspecialchars((string) ($environment['tests'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
            </div>
            <div class="flex items-center justify-between gap-3 border-t border-slate-800 pt-3">
              <dt class="text-slate-500">Duração</dt>
              <dd class="text-right font-semibold text-slate-200"><?= htmlspecialchars((string) ($environment['duration'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
            </div>
          </dl>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-orbitron text-lg font-black text-white">Testes Automatizados</h2>
        <a href="<?= htmlspecialchars(url('/admin/central-operacional'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-700 bg-slate-950/70 px-4 text-xs font-black text-slate-200 transition hover:border-cyan-400/35 hover:text-cyan-100">Abrir V1</a>
      </div>

      <div class="mt-5 overflow-x-auto">
        <table class="min-w-full border-separate border-spacing-y-3 text-left text-sm text-slate-200">
          <thead>
            <tr class="font-orbitron text-[10px] uppercase tracking-[0.18em] text-slate-500">
              <th class="px-4 py-2">Data</th>
              <th class="px-4 py-2">Ambiente</th>
              <th class="px-4 py-2">Resumo</th>
              <th class="px-4 py-2">Duração</th>
              <th class="px-4 py-2">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($smokeHistory as $item): ?>
              <tr>
                <td class="rounded-l-2xl border-y border-l border-slate-800 bg-slate-950/70 px-4 py-3 text-xs font-semibold text-slate-300"><?= htmlspecialchars((string) ($item['finished_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                <td class="border-y border-slate-800 bg-slate-950/70 px-4 py-3 font-black text-white"><?= htmlspecialchars((string) ($item['environment'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                <td class="border-y border-slate-800 bg-slate-950/70 px-4 py-3"><?= htmlspecialchars((string) ($item['summary'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                <td class="border-y border-slate-800 bg-slate-950/70 px-4 py-3"><?= htmlspecialchars((string) ($item['duration'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                <td class="rounded-r-2xl border-y border-r border-slate-800 bg-slate-950/70 px-4 py-3">
                  <?php View::component('admin/v2/status-badge', [
                      'label' => (string) ($item['status'] ?? 'Pendente'),
                      'tone' => (string) ($item['tone'] ?? 'neutral'),
                  ]); ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if ($smokeHistory === []): ?>
              <tr><td colspan="5" class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-8 text-center text-sm font-semibold text-slate-400">Nenhuma execução salva encontrada.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <h2 class="font-orbitron text-lg font-black text-white">Alertas Operacionais</h2>
      <div class="mt-5 grid gap-3">
        <?php foreach ($alerts as $alert): ?>
          <article class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="font-black text-white"><?= htmlspecialchars((string) ($alert['label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="mt-1 text-xs font-semibold text-slate-400"><?= htmlspecialchars((string) ($alert['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>
              <?php View::component('admin/v2/status-badge', [
                  'label' => (string) ($alert['tone'] ?? 'info'),
                  'tone' => (string) ($alert['tone'] ?? 'neutral'),
              ]); ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="grid gap-6 xl:grid-cols-2">
    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <h2 class="font-orbitron text-lg font-black text-white">Logs Operacionais</h2>
      <div class="mt-5 grid gap-4">
        <?php foreach ($operationLogs as $category): ?>
          <article class="rounded-2xl border border-slate-800 bg-slate-950/70 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-cyan-300/70"><?= htmlspecialchars((string) ($category['label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="mt-2 text-sm font-semibold text-slate-300"><?= htmlspecialchars((string) ($category['latest_message'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>
              <?php View::component('admin/v2/status-badge', [
                  'label' => (string) ($category['latest_status'] ?? 'Pendente'),
                  'tone' => (string) ($category['tone'] ?? 'neutral'),
              ]); ?>
            </div>
            <div class="mt-4 grid gap-3 text-xs font-semibold text-slate-400 sm:grid-cols-3">
              <div>Eventos: <span class="text-white"><?= (int) ($category['count'] ?? 0) ?></span></div>
              <div>Último: <span class="text-white"><?= htmlspecialchars((string) ($category['latest_date'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
              <div>Arquivo: <span class="text-white"><?= htmlspecialchars((string) ($category['latest_file'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
            </div>
          </article>
        <?php endforeach; ?>
        <?php if ($operationLogs === []): ?>
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-8 text-center text-sm font-semibold text-slate-400">Nenhum log operacional encontrado.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <h2 class="font-orbitron text-lg font-black text-white">Logs do Sistema</h2>
      <div class="mt-5 divide-y divide-slate-800 overflow-hidden rounded-2xl border border-slate-800 bg-slate-950/70">
        <?php foreach ($applicationLogs as $log): ?>
          <article class="p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div class="font-black text-white"><?= htmlspecialchars((string) ($log['event'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="mt-1 text-xs font-semibold text-slate-400"><?= htmlspecialchars((string) ($log['detail'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>
              <div class="text-right text-xs font-semibold text-slate-500">
                <div><?= htmlspecialchars((string) ($log['date'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="mt-1"><?= htmlspecialchars((string) ($log['source'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
        <?php if ($applicationLogs === []): ?>
          <div class="px-4 py-8 text-center text-sm font-semibold text-slate-400">Nenhum log recente encontrado.</div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</section>
