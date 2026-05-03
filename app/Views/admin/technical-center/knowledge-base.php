<?php

declare(strict_types=1);

use App\Support\View;

$knowledge = is_array($knowledge ?? null) ? $knowledge : [];
$facts = is_array($knowledge['facts'] ?? null) ? $knowledge['facts'] : [];
$cards = is_array($knowledge['cards'] ?? null) ? $knowledge['cards'] : [];
$sections = is_array($knowledge['sections'] ?? null) ? $knowledge['sections'] : [];
$summary = is_array($knowledge['summary'] ?? null) ? $knowledge['summary'] : [];
?>
<section class="space-y-6">
  <?php View::component('admin/v2/page-header', [
      'eyebrow' => 'Central Técnica',
      'title' => 'Base de Conhecimento',
      'description' => '',
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
      <a href="<?= htmlspecialchars((string) ($card['href'] ?? '#'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="group rounded-[1.35rem] border border-slate-800 bg-slate-900/80 p-5 shadow-[0_0_28px_rgba(2,6,23,0.18)] transition hover:-translate-y-0.5 hover:border-cyan-400/30 hover:bg-slate-900">
        <div class="flex items-start justify-between gap-4">
          <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-cyan-400/15 bg-cyan-500/10 text-cyan-200">
            <i class="<?= htmlspecialchars((string) ($card['icon'] ?? 'fa-solid fa-book'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-hidden="true"></i>
          </div>
          <?php View::component('admin/v2/status-badge', [
              'label' => (string) ($card['tone'] ?? 'neutral'),
              'tone' => (string) ($card['tone'] ?? 'neutral'),
          ]); ?>
        </div>
        <div class="mt-6 font-orbitron text-[10px] font-black uppercase tracking-[0.16em] text-slate-500"><?= htmlspecialchars((string) ($card['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <div class="mt-2 text-2xl font-black text-white"><?= htmlspecialchars((string) ($card['value'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <div class="mt-2 text-xs font-semibold text-slate-400"><?= htmlspecialchars((string) ($card['hint'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </a>
    <?php endforeach; ?>
  </section>

  <section class="grid gap-6 xl:grid-cols-[1fr_0.75fr]">
    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-orbitron text-lg font-black text-white">Mapa da Base</h2>
        <?php View::component('admin/v2/status-badge', ['label' => 'Somente leitura', 'tone' => 'info']); ?>
      </div>

      <div class="mt-5 divide-y divide-slate-800 overflow-hidden rounded-2xl border border-slate-800 bg-slate-950/70">
        <?php foreach ($sections as $section): ?>
          <?php $href = (string) ($section['href'] ?? '#'); ?>
          <article class="flex flex-col gap-4 p-4 md:flex-row md:items-center md:justify-between">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-3">
                <h3 class="font-orbitron text-sm font-black text-white"><?= htmlspecialchars((string) ($section['label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
                <?php View::component('admin/v2/status-badge', [
                    'label' => (string) ($section['status'] ?? 'Planejado'),
                    'tone' => (string) ($section['tone'] ?? 'neutral'),
                ]); ?>
              </div>
              <p class="mt-2 text-sm font-semibold text-slate-400"><?= htmlspecialchars((string) ($section['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            </div>

            <?php if ($href !== '#'): ?>
              <a href="<?= htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-xl border border-slate-700 bg-slate-900/80 px-4 text-xs font-black text-slate-200 transition hover:border-cyan-400/35 hover:text-cyan-100">Abrir</a>
            <?php else: ?>
              <span class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-xl border border-slate-800 bg-slate-900/50 px-4 text-xs font-black text-slate-500">Planejado</span>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </div>

    <aside class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <h2 class="font-orbitron text-lg font-black text-white">Resumo Técnico</h2>
      <div class="mt-5 grid gap-3">
        <?php foreach ($summary as $item): ?>
          <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500"><?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div class="text-sm font-black text-white"><?= htmlspecialchars((string) ($item['value'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </aside>
  </section>
</section>
