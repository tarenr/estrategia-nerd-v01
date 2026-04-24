<?php
declare(strict_types=1);

$mode = (string) ($mode ?? 'create');
$submitLabel = (string) ($submitLabel ?? 'Salvar');
?>

<section class="admin-panel flex flex-wrap items-center justify-between gap-3">
  <div class="flex flex-col gap-1">
    <div class="text-xs text-slate-400">Modo atual: <?= $mode === 'create' ? 'criacao' : 'edicao' ?>.</div>
    <div id="postSubmitGuardMessage" class="post-submit-guard-message hidden" role="status" aria-live="polite"></div>
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="<?= url('/admin/posts') ?>" class="admin-btn admin-btn-secondary" data-confirm-leave="1">Cancelar</a>
    <button type="button" class="admin-btn admin-btn-secondary" onclick="abrirPreview()">Ver preview</button>
    <button type="submit" class="admin-btn admin-btn-primary" id="postPrimarySubmit" data-submit-role="primary"><?= htmlspecialchars($submitLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
  </div>
</section>
