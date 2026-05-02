<?php

declare(strict_types=1);

$eyebrow = (string) ($eyebrow ?? 'Admin V2');
$title = (string) ($title ?? 'Painel');
$description = (string) ($description ?? '');
$actions = is_array($actions ?? null) ? $actions : [];
?>
<header class="admin-v2-page-header relative overflow-hidden rounded-[2rem] border border-cyan-500/25 bg-slate-900/90 p-7 shadow-[0_0_46px_rgba(6,182,212,0.10)]">
  <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-cyan-300/60 to-transparent"></div>
  <div class="pointer-events-none absolute -right-20 -top-24 h-56 w-56 rounded-full bg-cyan-500/10 blur-3xl"></div>
  <div class="relative flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
    <div class="min-w-0">
      <p class="font-orbitron text-xs font-bold uppercase tracking-[0.32em] text-cyan-300/70"><?= htmlspecialchars($eyebrow, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
      <h1 class="mt-3 font-orbitron text-3xl font-black tracking-tight text-white sm:text-4xl"><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
      <?php if ($description !== ''): ?>
        <p class="mt-4 max-w-4xl text-base leading-7 text-slate-300"><?= htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
      <?php endif; ?>
    </div>

    <?php if ($actions !== []): ?>
      <div class="flex shrink-0 flex-wrap gap-2">
        <?php foreach ($actions as $action): ?>
          <?php
            $href = (string) ($action['href'] ?? '#');
            $label = (string) ($action['label'] ?? 'Acao');
            $icon = (string) ($action['icon'] ?? '');
            $variant = (string) ($action['variant'] ?? 'secondary');
            $class = $variant === 'primary' ? 'admin-btn admin-btn-primary' : 'admin-btn admin-btn-secondary';
          ?>
          <a href="<?= htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="<?= htmlspecialchars($class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <?php if ($icon !== ''): ?><i class="<?= htmlspecialchars($icon, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-hidden="true"></i><?php endif; ?>
            <?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</header>
