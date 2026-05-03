<?php

declare(strict_types=1);

use App\Support\View;

$sections = is_array($sections ?? null) ? $sections : [];
$current = is_array($current_section ?? null) ? $current_section : [];
$activeSection = (string) ($active_section ?? '');
$contentHtml = (string) ($content_html ?? '');
$plannedContent = is_array($planned_content ?? null) ? $planned_content : [];
$source = (string) ($source ?? 'Base Técnica V1');
$isPlanned = $contentHtml === '';
?>
<section class="space-y-6">
  <?php View::component('admin/v2/page-header', [
      'eyebrow' => 'Central Técnica',
      'title' => (string) ($current['label'] ?? 'Base de Conhecimento'),
      'description' => '',
      'actions' => [
          [
              'href' => url('/admin/central-tecnica/base-conhecimento'),
              'label' => 'Visão Geral',
              'icon' => 'fa-solid fa-arrow-left',
              'variant' => 'secondary',
          ],
      ],
  ]); ?>

  <div class="rounded-3xl border border-slate-800 bg-slate-900/70 p-2">
    <div class="grid gap-2 md:grid-cols-4">
      <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Modo</div>
        <div class="mt-2 text-sm font-black text-white">Somente Leitura</div>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Fonte</div>
        <div class="mt-2 text-sm font-black text-white"><?= htmlspecialchars($source, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Status</div>
        <div class="mt-2">
          <?php View::component('admin/v2/status-badge', [
              'label' => (string) ($current['status'] ?? 'Planejado'),
              'tone' => (string) ($current['tone'] ?? 'neutral'),
          ]); ?>
        </div>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Última Leitura</div>
        <div class="mt-2 text-sm font-black text-white"><?= date('d/m/Y H:i:s') ?></div>
      </div>
    </div>
  </div>

  <?php if ($isPlanned): ?>
    <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
          <p class="font-orbitron text-xs font-black uppercase tracking-[0.28em] text-cyan-300/70">Base de Conhecimento</p>
          <h2 class="mt-3 font-orbitron text-2xl font-black text-white"><?= htmlspecialchars((string) ($plannedContent['title'] ?? $current['label'] ?? 'Seção'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
          <p class="mt-3 max-w-3xl text-sm font-semibold leading-7 text-slate-400"><?= htmlspecialchars((string) ($plannedContent['summary'] ?? 'Seção organizada para leitura e consulta interna.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        </div>
        <?php View::component('admin/v2/status-badge', ['label' => (string) ($current['status'] ?? 'Planejado'), 'tone' => (string) ($current['tone'] ?? 'neutral')]); ?>
      </div>

      <?php $cards = is_array($plannedContent['cards'] ?? null) ? $plannedContent['cards'] : []; ?>
      <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <?php foreach ($cards as $card): ?>
          <article class="rounded-2xl border border-slate-800 bg-slate-950/70 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <h3 class="font-orbitron text-base font-black text-white"><?= htmlspecialchars((string) ($card['title'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
              <?php View::component('admin/v2/status-badge', [
                  'label' => (string) ($card['status'] ?? 'Planejado'),
                  'tone' => (string) ($card['tone'] ?? 'neutral'),
              ]); ?>
            </div>
            <?php $items = is_array($card['items'] ?? null) ? $card['items'] : []; ?>
            <ul class="mt-4 grid gap-2">
              <?php foreach ($items as $item): ?>
                <li class="flex items-start gap-3 rounded-xl border border-slate-800 bg-slate-900/70 px-3 py-2 text-sm font-semibold text-slate-300">
                  <i class="fa-solid fa-check mt-1 text-[10px] text-cyan-300" aria-hidden="true"></i>
                  <span><?= htmlspecialchars((string) $item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php else: ?>
    <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-4">
      <?= $contentHtml ?>
    </section>
  <?php endif; ?>
</section>
