<?php

declare(strict_types=1);

$active = strtolower(trim((string) ($active ?? '')));
$items = [
    [
        'id' => 'operations',
        'label' => 'Central',
        'description' => 'Status operacional consolidado',
        'href' => url('/local/operacoes'),
    ],
    [
        'id' => 'backup',
        'label' => 'Backup',
        'description' => 'Backup, verificacao e restore',
        'href' => url('/local/backup'),
    ],
    [
        'id' => 'content',
        'label' => 'Conteudo',
        'description' => 'Pacote e publicacao controlada',
        'href' => url('/local/conteudo'),
    ],
    [
        'id' => 'docs',
        'label' => 'Documentacao',
        'description' => 'Base tecnica e fluxo oficial',
        'href' => url('/local/documentacao'),
    ],
    [
        'id' => 'rules',
        'label' => 'Regras',
        'description' => 'Regras permanentes e bloqueios',
        'href' => url('/local/regras'),
    ],
    [
        'id' => 'backlog',
        'label' => 'Backlog',
        'description' => 'Evolucao tecnica por fases',
        'href' => url('/local/backlog'),
    ],
];

$gridColumnsClass = count($items) >= 6 ? 'md:grid-cols-3 xl:grid-cols-6' : (count($items) >= 5 ? 'md:grid-cols-5' : (count($items) >= 4 ? 'md:grid-cols-4' : 'md:grid-cols-3'));
?>
<nav class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-4 shadow-[0_0_30px_rgba(6,182,212,0.06)]" aria-label="Menu local de operacao">
  <div class="flex flex-wrap items-center gap-3">
    <p class="font-orbitron text-xs uppercase tracking-[0.32em] text-cyan-300/70">Operacao Local</p>
    <span class="text-xs text-slate-500">Central + Backup + Conteudo + Documentacao + Regras + Backlog</span>
  </div>
  <div class="mt-4 grid gap-3 <?= htmlspecialchars($gridColumnsClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <?php foreach ($items as $item): ?>
      <?php $isActive = ($item['id'] === $active); ?>
      <a
        href="<?= htmlspecialchars((string) $item['href'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
        class="rounded-2xl border px-4 py-3 transition <?= $isActive ? 'border-cyan-300/70 bg-cyan-500/15 text-cyan-100 shadow-[0_0_24px_rgba(34,211,238,0.12)]' : 'border-slate-700 bg-slate-950/70 text-slate-200 hover:border-cyan-400/50 hover:bg-cyan-500/10' ?>"
        <?= $isActive ? 'aria-current="page"' : '' ?>
      >
        <div class="font-orbitron text-sm font-bold tracking-wide"><?= htmlspecialchars((string) $item['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <div class="mt-1 text-xs text-slate-400"><?= htmlspecialchars((string) $item['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</nav>
