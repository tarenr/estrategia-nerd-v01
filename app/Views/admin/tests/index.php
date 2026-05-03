<?php

declare(strict_types=1);

use App\Support\View;

$tests = is_array($tests ?? null) ? $tests : [];
$latestByEnvironment = is_array($tests['latest_by_environment'] ?? null) ? $tests['latest_by_environment'] : [];
$history = is_array($tests['history'] ?? null) ? $tests['history'] : [];
$environments = [
    'local' => 'Local',
    'stage' => 'Stage',
    'production' => 'Produção',
];

$formatDate = static function (?string $value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return 'Leitura pendente';
    }

    $timestamp = strtotime($value);
    return $timestamp !== false ? date('d/m/Y H:i:s', $timestamp) : $value;
};

$statusTone = static function (array $item): string {
    $summary = is_array($item['summary'] ?? null) ? $item['summary'] : [];
    return strtolower((string) ($item['status'] ?? '')) === 'ok' && (int) ($summary['fail'] ?? 0) === 0 ? 'success' : 'warning';
};
?>
<section class="space-y-6">
  <?php View::component('admin/v2/page-header', [
      'eyebrow' => 'Operacional',
      'title' => 'Testes Automatizados',
      'description' => '',
  ]); ?>

  <div class="rounded-3xl border border-slate-800 bg-slate-900/70 p-2">
    <div class="grid gap-2 md:grid-cols-4">
      <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Modo</div>
        <div class="mt-2 text-sm font-black text-white">Somente Leitura</div>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Fonte</div>
        <div class="mt-2 text-sm font-black text-white">Smoke tests salvos</div>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Ambientes</div>
        <div class="mt-2 text-sm font-black text-white"><?= count($environments) ?></div>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Histórico</div>
        <div class="mt-2 text-sm font-black text-white"><?= count($history) ?> registro(s)</div>
      </div>
    </div>
  </div>

  <section class="grid gap-4 lg:grid-cols-3">
    <?php foreach ($environments as $key => $label): ?>
      <?php
        $latest = is_array($latestByEnvironment[$key] ?? null) ? $latestByEnvironment[$key] : [];
        $summary = is_array($latest['summary'] ?? null) ? $latest['summary'] : [];
        $tone = $latest !== [] ? $statusTone($latest) : 'neutral';
      ?>
      <article class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-cyan-300/70"><?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div class="mt-2 text-xl font-black text-white"><?= htmlspecialchars((string) ($latest['id'] ?? 'Leitura pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          </div>
          <?php View::component('admin/v2/status-badge', [
              'label' => $latest !== [] ? strtoupper((string) ($latest['status'] ?? 'pendente')) : 'Pendente',
              'tone' => $tone,
          ]); ?>
        </div>

        <dl class="mt-5 grid gap-3 text-sm">
          <div class="flex items-center justify-between gap-3 border-t border-slate-800 pt-3">
            <dt class="text-slate-500">Última execução</dt>
            <dd class="text-right font-semibold text-slate-200"><?= htmlspecialchars($formatDate((string) ($latest['finished_at'] ?? $latest['started_at'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
          </div>
          <div class="flex items-center justify-between gap-3 border-t border-slate-800 pt-3">
            <dt class="text-slate-500">OK</dt>
            <dd class="text-right font-semibold text-slate-200"><?= (int) ($summary['ok'] ?? 0) ?></dd>
          </div>
          <div class="flex items-center justify-between gap-3 border-t border-slate-800 pt-3">
            <dt class="text-slate-500">Falhas</dt>
            <dd class="text-right font-semibold text-slate-200"><?= (int) ($summary['fail'] ?? 0) ?></dd>
          </div>
          <div class="flex items-center justify-between gap-3 border-t border-slate-800 pt-3">
            <dt class="text-slate-500">Total</dt>
            <dd class="text-right font-semibold text-slate-200"><?= (int) ($summary['total'] ?? 0) ?></dd>
          </div>
        </dl>
      </article>
    <?php endforeach; ?>
  </section>

  <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
    <h2 class="font-orbitron text-lg font-black text-white">Histórico Recente</h2>
    <div class="mt-5 overflow-x-auto">
      <table class="min-w-full border-separate border-spacing-y-3 text-left text-sm text-slate-200">
        <thead>
          <tr class="font-orbitron text-[10px] uppercase tracking-[0.18em] text-slate-500">
            <th class="px-4 py-2">Data</th>
            <th class="px-4 py-2">Ambiente</th>
            <th class="px-4 py-2">Identificador</th>
            <th class="px-4 py-2">Resumo</th>
            <th class="px-4 py-2">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $item): ?>
            <?php
              $item = is_array($item) ? $item : [];
              $summary = is_array($item['summary'] ?? null) ? $item['summary'] : [];
              $tone = $statusTone($item);
            ?>
            <tr>
              <td class="rounded-l-2xl border-y border-l border-slate-800 bg-slate-950/70 px-4 py-3 text-xs font-semibold text-slate-300"><?= htmlspecialchars($formatDate((string) ($item['finished_at'] ?? $item['started_at'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
              <td class="border-y border-slate-800 bg-slate-950/70 px-4 py-3 font-black text-white"><?= htmlspecialchars((string) ($item['environment_label'] ?? $item['environment'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
              <td class="border-y border-slate-800 bg-slate-950/70 px-4 py-3 font-semibold text-cyan-100"><?= htmlspecialchars((string) ($item['id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
              <td class="border-y border-slate-800 bg-slate-950/70 px-4 py-3"><?= (int) ($summary['ok'] ?? 0) ?> OK / <?= (int) ($summary['fail'] ?? 0) ?> falha(s) / <?= (int) ($summary['skip'] ?? 0) ?> ignorado(s)</td>
              <td class="rounded-r-2xl border-y border-r border-slate-800 bg-slate-950/70 px-4 py-3">
                <?php View::component('admin/v2/status-badge', [
                    'label' => strtoupper((string) ($item['status'] ?? 'pendente')),
                    'tone' => $tone,
                ]); ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if ($history === []): ?>
            <tr><td colspan="5" class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-8 text-center text-sm font-semibold text-slate-400">Nenhum teste salvo encontrado.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</section>
