<?php

declare(strict_types=1);

$title = (string) ($title ?? 'Painel');
$description = (string) ($description ?? '');
$state = (string) ($state ?? 'idle');
$refreshUrl = (string) ($refresh_url ?? '');
?>
<section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6" data-admin-v2-async-panel data-state="<?= htmlspecialchars($state, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
  <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
    <div>
      <h2 class="font-orbitron text-lg font-black text-white"><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
      <?php if ($description !== ''): ?>
        <p class="mt-2 text-sm leading-7 text-slate-400"><?= htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
      <?php endif; ?>
    </div>
    <?php if ($refreshUrl !== ''): ?>
      <a href="<?= htmlspecialchars($refreshUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary">
        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
        Atualizar
      </a>
    <?php endif; ?>
  </div>

  <div class="mt-5 rounded-2xl border border-dashed border-slate-700 bg-slate-950/60 px-4 py-5 text-sm text-slate-400">
    Este painel e uma base visual V2. Nas proximas fases, ele podera carregar fragments existentes por AJAX sem alterar as rotinas atuais.
  </div>
</section>
