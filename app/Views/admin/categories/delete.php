<?php
declare(strict_types=1);

use App\Support\Csrf;

$categoria = $categoria ?? [];

$id = (int) ($categoria['id'] ?? 0);
$nome = (string) ($categoria['nome'] ?? '');
$slug = (string) ($categoria['slug'] ?? '');
$cor = (string) ($categoria['cor'] ?? '#00d4ff');
$ativo = (int) ($categoria['ativo'] ?? 1) === 1;
$totalPosts = (int) ($categoria['total_posts'] ?? 0);
$totalViews = (int) ($categoria['total_views'] ?? 0);
?>

<div class="max-w-5xl mx-auto px-4 py-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title text-rose-300">Confirmar Exclusao</h1>
      <div class="admin-page-subtitle">Categorias com posts vinculados serao desativadas em vez de removidas por completo.</div>
    </div>

    <div class="admin-page-actions">
      <a href="<?= url('/admin/categorias') ?>" class="admin-btn admin-btn-secondary">Voltar para categorias</a>
    </div>
  </div>

  <section class="admin-panel border border-rose-500/30">
    <div class="grid grid-cols-1 lg:grid-cols-[220px_1fr] gap-6 items-start">
      <div class="w-full aspect-square rounded-2xl border border-slate-800 bg-slate-900/70 flex flex-col items-center justify-center px-5 text-center">
        <span class="inline-flex w-16 h-16 rounded-3xl border border-slate-700" style="background: <?= htmlspecialchars($cor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></span>
        <div class="mt-4 text-sm font-bold text-slate-200"><?= htmlspecialchars($cor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <div class="mt-2 text-xs text-slate-400">Cor principal da categoria</div>
      </div>

      <div class="space-y-5">
        <div>
          <div class="flex flex-wrap items-center gap-2 mb-3">
            <span class="admin-chip">ID: <?= $id ?></span>
            <span class="admin-chip">Slug: <?= htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            <span class="admin-chip">Status: <?= $ativo ? 'ativa' : 'inativa' ?></span>
          </div>

          <h2 class="font-orbitron text-2xl font-black text-white"><?= htmlspecialchars($nome !== '' ? $nome : 'Sem nome', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
              <div class="text-slate-400">Posts vinculados</div>
              <div class="mt-1 text-xl font-bold text-white"><?= number_format($totalPosts, 0, ',', '.') ?></div>
            </div>
            <div>
              <div class="text-slate-400">Views somadas</div>
              <div class="mt-1 text-xl font-bold text-white"><?= number_format($totalViews, 0, ',', '.') ?></div>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-amber-500/25 bg-amber-500/10 p-4">
          <?php if ($totalPosts > 0): ?>
            <div class="text-sm font-bold text-amber-200">A categoria sera desativada, nao excluida.</div>
            <div class="mt-2 text-sm leading-relaxed text-amber-100">Como existem posts vinculados, o sistema preserva os relacionamentos e apenas remove a categoria do seletor ativo.</div>
          <?php else: ?>
            <div class="text-sm font-bold text-amber-200">A categoria sera removida permanentemente.</div>
            <div class="mt-2 text-sm leading-relaxed text-amber-100">Sem posts vinculados, a exclusao pode ser feita com seguranca.</div>
          <?php endif; ?>
        </div>

        <form id="deleteCategoriaForm" method="POST" action="<?= url('/admin/excluir-categoria?id=' . $id) ?>" class="flex flex-wrap gap-3">
          <?= Csrf::field() ?>
          <input type="hidden" name="id" value="<?= $id ?>">

          <button type="submit" class="admin-btn admin-btn-primary" style="background:rgba(244,63,94,.18); border-color:rgba(244,63,94,.35); color:#fecdd3;">
            <?= $totalPosts > 0 ? 'Desativar categoria' : 'Excluir categoria' ?>
          </button>
          <a href="<?= url('/admin/categorias') ?>" class="admin-btn admin-btn-secondary">Cancelar</a>
        </form>
      </div>
    </div>
  </section>
</div>

<div id="deleteCategoriaModal" style="display:none; position:fixed; inset:0; background:rgba(2,6,23,0.86); z-index:1200; align-items:center; justify-content:center; padding:24px;">
  <div style="width:min(520px,100%); background:#0f172a; border:1px solid rgba(244,63,94,.35); border-radius:20px; box-shadow:0 24px 80px rgba(2,6,23,.6); padding:24px;">
    <div style="font-family:Orbitron,sans-serif; font-size:1.15rem; font-weight:900; color:#fff;">Confirmar acao</div>
    <div style="margin-top:10px; color:#cbd5e1; line-height:1.7;">
      <?= $totalPosts > 0
          ? 'Esta categoria possui posts vinculados. O sistema vai desativa-la para manter os posts consistentes.'
          : 'Esta categoria sera removida permanentemente e nao podera ser recuperada.' ?>
    </div>
    <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:20px; flex-wrap:wrap;">
      <button type="button" id="cancelDeleteCategoriaModal" class="admin-btn admin-btn-secondary">Cancelar</button>
      <button type="button" id="confirmDeleteCategoriaModal" class="admin-btn admin-btn-primary" style="background:rgba(244,63,94,.18); border-color:rgba(244,63,94,.35); color:#fecdd3;">
        <?= $totalPosts > 0 ? 'Desativar agora' : 'Excluir agora' ?>
      </button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('deleteCategoriaForm');
  var modal = document.getElementById('deleteCategoriaModal');
  var confirmButton = document.getElementById('confirmDeleteCategoriaModal');
  var cancelButton = document.getElementById('cancelDeleteCategoriaModal');
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
