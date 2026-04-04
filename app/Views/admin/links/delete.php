<?php
declare(strict_types=1);

use App\Support\Csrf;

$link = $link ?? [];

$id = (int) ($link['id'] ?? 0);
$titulo = (string) ($link['titulo'] ?? '');
$slug = (string) ($link['slug'] ?? '');
$urlDestino = (string) ($link['url'] ?? '');
$tipo = (string) ($link['tipo'] ?? 'conteudo');
$status = (string) ($link['status'] ?? 'ativo');
$descricao = (string) ($link['descricao'] ?? '');
$imagem = trim((string) ($link['imagem'] ?? ''));
$destaque = (int) ($link['destaque'] ?? 0) === 1;
$expiraEm = (string) ($link['expira_em'] ?? '');
?>

<div class="max-w-5xl mx-auto px-4 py-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title text-rose-300">Confirmar Exclusao</h1>
      <div class="admin-page-subtitle">Remova o link da base da bio e da operacao de monetizacao com uma confirmacao final do sistema.</div>
    </div>

    <div class="admin-page-actions">
      <a href="<?= url('/admin/links') ?>" class="admin-btn admin-btn-secondary">Voltar para links</a>
    </div>
  </div>

  <section class="admin-panel border border-rose-500/30">
    <div class="grid grid-cols-1 lg:grid-cols-[220px_1fr] gap-6 items-start">
      <div class="w-full aspect-video rounded-2xl border border-slate-800 bg-slate-900/70 overflow-hidden flex items-center justify-center">
        <?php if ($imagem !== ''): ?>
          <img src="<?= htmlspecialchars($imagem, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="Imagem do link" class="w-full h-full object-cover">
        <?php else: ?>
          <div class="text-center px-5">
            <div class="text-sm font-bold text-slate-200 uppercase tracking-[0.18em]"><?= htmlspecialchars($tipo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div class="mt-2 text-xs text-slate-500">Sem imagem vinculada</div>
          </div>
        <?php endif; ?>
      </div>

      <div class="space-y-5">
        <div>
          <div class="flex flex-wrap items-center gap-2 mb-3">
            <span class="admin-chip">ID: <?= $id ?></span>
            <span class="admin-chip">Slug: <?= htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            <span class="admin-chip">Status: <?= htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            <span class="admin-chip">Tipo: <?= htmlspecialchars($tipo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          </div>

          <h2 class="font-orbitron text-2xl font-black text-white"><?= htmlspecialchars($titulo !== '' ? $titulo : 'Sem titulo', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
          <div class="mt-3 text-sm text-slate-400 break-all"><?= htmlspecialchars($urlDestino, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
              <div class="text-slate-400">Destaque</div>
              <div class="mt-1 text-xl font-bold text-white"><?= $destaque ? 'Sim' : 'Nao' ?></div>
            </div>
            <div>
              <div class="text-slate-400">Expira em</div>
              <div class="mt-1 text-xl font-bold text-white"><?= htmlspecialchars($expiraEm !== '' ? $expiraEm : '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-amber-500/25 bg-amber-500/10 p-4">
          <div class="text-sm font-bold text-amber-200">Esta acao remove o link permanentemente.</div>
          <div class="mt-2 text-sm leading-relaxed text-amber-100">
            <?= htmlspecialchars($descricao !== '' ? $descricao : 'Se este link ainda estiver em uso na bio ou em campanhas, ajuste a operacao antes de confirmar a exclusao.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
          </div>
        </div>

        <form id="deleteLinkForm" method="POST" action="<?= url('/admin/excluir-link?id=' . $id) ?>" class="flex flex-wrap gap-3">
          <?= Csrf::field() ?>
          <input type="hidden" name="id" value="<?= $id ?>">

          <button type="submit" class="admin-btn admin-btn-primary" style="background:rgba(244,63,94,.18); border-color:rgba(244,63,94,.35); color:#fecdd3;">Excluir link</button>
          <a href="<?= url('/admin/links') ?>" class="admin-btn admin-btn-secondary">Cancelar</a>
        </form>
      </div>
    </div>
  </section>
</div>

<div id="deleteLinkModal" style="display:none; position:fixed; inset:0; background:rgba(2,6,23,0.86); z-index:1200; align-items:center; justify-content:center; padding:24px;">
  <div style="width:min(520px,100%); background:#0f172a; border:1px solid rgba(244,63,94,.35); border-radius:20px; box-shadow:0 24px 80px rgba(2,6,23,.6); padding:24px;">
    <div style="font-family:Orbitron,sans-serif; font-size:1.15rem; font-weight:900; color:#fff;">Confirmar exclusao</div>
    <div style="margin-top:10px; color:#cbd5e1; line-height:1.7;">
      Este link sera removido da base do admin e deixara de aparecer na futura pagina de bio, ofertas ou campanhas ligadas a ele.
    </div>
    <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:20px; flex-wrap:wrap;">
      <button type="button" id="cancelDeleteLinkModal" class="admin-btn admin-btn-secondary">Cancelar</button>
      <button type="button" id="confirmDeleteLinkModal" class="admin-btn admin-btn-primary" style="background:rgba(244,63,94,.18); border-color:rgba(244,63,94,.35); color:#fecdd3;">Excluir agora</button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('deleteLinkForm');
  var modal = document.getElementById('deleteLinkModal');
  var confirmButton = document.getElementById('confirmDeleteLinkModal');
  var cancelButton = document.getElementById('cancelDeleteLinkModal');
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
