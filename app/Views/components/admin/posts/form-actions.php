<?php
declare(strict_types=1);

$mode = (string) ($mode ?? 'create');
$submitLabel = (string) ($submitLabel ?? 'Salvar');
?>

<section class="admin-panel flex flex-wrap items-center justify-between gap-3">
  <div class="text-xs text-slate-400">Modo atual: <?= $mode === 'create' ? 'criacao' : 'edicao' ?>.</div>
  <div class="flex flex-wrap gap-2">
    <a href="<?= url('/admin/posts') ?>" class="admin-btn admin-btn-secondary">Cancelar</a>
    <button type="button" class="admin-btn admin-btn-secondary" onclick="abrirPreview()">Ver preview</button>
    <button type="submit" class="admin-btn admin-btn-primary"><?= htmlspecialchars($submitLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
  </div>
</section>
