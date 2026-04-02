<?php
declare(strict_types=1);

use App\Support\Csrf;

$comment = $comment ?? [];
$returnTo = (string) ($return_to ?? url('/admin/comentarios'));
$id = (int) ($comment['id'] ?? 0);
$postId = (int) ($comment['post_id'] ?? 0);
$postTitulo = (string) ($comment['post_titulo'] ?? 'Post removido');
$status = (string) ($comment['status'] ?? 'pendente');
$respondido = (int) ($comment['respondido'] ?? 0) === 1;
?>

<div class="max-w-5xl mx-auto px-4 py-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title text-rose-300">Confirmar Exclusao</h1>
      <div class="admin-page-subtitle">Esta acao remove o comentario permanentemente da moderacao.</div>
    </div>

    <div class="admin-page-actions">
      <a href="<?= htmlspecialchars($returnTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary">Voltar para comentarios</a>
    </div>
  </div>

  <section class="admin-panel border border-rose-500/30">
    <div class="grid grid-cols-1 lg:grid-cols-[220px_1fr] gap-6 items-start">
      <div class="w-full aspect-square rounded-2xl border border-slate-800 bg-slate-900/70 flex flex-col items-center justify-center px-5 text-center">
        <div class="text-5xl font-orbitron font-black text-rose-300">#<?= $id ?></div>
        <div class="mt-4 text-sm font-bold text-slate-200"><?= htmlspecialchars((string) ($comment['nome'] ?? 'Anonimo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <div class="mt-2 text-xs text-slate-400"><?= htmlspecialchars((string) ($comment['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </div>

      <div class="space-y-5">
        <div>
          <div class="flex flex-wrap items-center gap-2 mb-3">
            <span class="admin-chip">Status: <?= htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            <span class="admin-chip">Resposta: <?= $respondido ? 'respondido' : 'sem resposta' ?></span>
            <span class="admin-chip">Post #<?= $postId ?></span>
          </div>
          <h2 class="font-orbitron text-2xl font-black text-white"><?= htmlspecialchars($postTitulo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
          <div class="text-xs uppercase tracking-[0.3em] text-slate-500 mb-3">Comentario</div>
          <div class="text-sm leading-relaxed text-slate-200 whitespace-pre-line"><?= htmlspecialchars((string) ($comment['comentario'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </div>

        <div class="rounded-2xl border border-amber-500/25 bg-amber-500/10 p-4">
          <div class="text-sm font-bold text-amber-200">A exclusao e irreversivel.</div>
          <div class="mt-2 text-sm leading-relaxed text-amber-100">Depois de excluir este comentario, ele nao aparecera mais na central e deixara de contar nas moderacoes futuras.</div>
        </div>

        <form id="deleteComentarioForm" method="POST" action="<?= url('/admin/excluir-comentario?id=' . $id) ?>" class="flex flex-wrap gap-3">
          <?= Csrf::field() ?>
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          <button type="submit" class="admin-btn admin-btn-primary" style="background:rgba(244,63,94,.18); border-color:rgba(244,63,94,.35); color:#fecdd3;">Excluir comentario</button>
          <a href="<?= htmlspecialchars($returnTo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary">Cancelar</a>
        </form>
      </div>
    </div>
  </section>
</div>

<div id="deleteComentarioModal" style="display:none; position:fixed; inset:0; background:rgba(2,6,23,0.86); z-index:1200; align-items:center; justify-content:center; padding:24px;">
  <div style="width:min(520px,100%); background:#0f172a; border:1px solid rgba(244,63,94,.35); border-radius:20px; box-shadow:0 24px 80px rgba(2,6,23,.6); padding:24px;">
    <div style="font-family:Orbitron,sans-serif; font-size:1.15rem; font-weight:900; color:#fff;">Confirmar exclusao</div>
    <div style="margin-top:10px; color:#cbd5e1; line-height:1.7;">Este comentario sera removido permanentemente do sistema. Deseja continuar?</div>
    <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:20px; flex-wrap:wrap;">
      <button type="button" id="cancelDeleteComentarioModal" class="admin-btn admin-btn-secondary">Cancelar</button>
      <button type="button" id="confirmDeleteComentarioModal" class="admin-btn admin-btn-primary" style="background:rgba(244,63,94,.18); border-color:rgba(244,63,94,.35); color:#fecdd3;">Excluir agora</button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('deleteComentarioForm');
  var modal = document.getElementById('deleteComentarioModal');
  var confirmButton = document.getElementById('confirmDeleteComentarioModal');
  var cancelButton = document.getElementById('cancelDeleteComentarioModal');
  if (!form || !modal || !confirmButton || !cancelButton) return;

  var closeModal = function () {
    modal.style.display = 'none';
    document.body.style.overflow = '';
  };

  form.addEventListener('submit', function (event) {
    if (form.dataset.confirmed === '1') {
      form.dataset.confirmed = '0';
      return;
    }

    event.preventDefault();
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  });

  confirmButton.addEventListener('click', function () {
    form.dataset.confirmed = '1';
    closeModal();
    form.submit();
  });

  cancelButton.addEventListener('click', closeModal);
  modal.addEventListener('click', function (event) {
    if (event.target === modal) closeModal();
  });
});
</script>