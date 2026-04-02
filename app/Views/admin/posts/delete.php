<?php
declare(strict_types=1);

use App\Support\Csrf;

$post = $post ?? [];

$postId = (int) ($post['id'] ?? 0);
$titulo = trim((string) ($post['titulo'] ?? ''));
$slug = trim((string) ($post['slug'] ?? ''));
$status = trim((string) ($post['status'] ?? ''));
$imagem = trim((string) ($post['imagem_capa'] ?? ''));
$conteudo = trim(strip_tags((string) ($post['conteudo'] ?? '')));
$resumo = $conteudo !== '' ? mb_substr($conteudo, 0, 180) : '';
$dataPublicacao = trim((string) ($post['data_publicacao'] ?? ''));

$formatDate = static function (string $value): string {
    if ($value === '') {
        return '-';
    }

    try {
        return (new DateTimeImmutable($value))->format('d/m/Y H:i');
    } catch (Throwable) {
        return $value;
    }
};

$coverUrl = '';
if ($imagem !== '') {
    $coverUrl = preg_match('#^https?://#i', $imagem) === 1
        ? $imagem
        : url('/' . ltrim($imagem, '/'));
}
?>

<div class="max-w-5xl mx-auto px-4 py-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title text-rose-300">Confirmar Exclusao</h1>
      <div class="admin-page-subtitle">Esta acao e irreversivel e remove o post do painel e do fluxo editorial.</div>
    </div>

    <div class="admin-page-actions">
      <a href="<?= url('/admin/posts') ?>" class="admin-btn admin-btn-secondary">Voltar para posts</a>
    </div>
  </div>

  <section class="admin-panel border border-rose-500/30">
    <div class="grid grid-cols-1 lg:grid-cols-[220px_1fr] gap-6 items-start">
      <div>
        <?php if ($coverUrl !== ''): ?>
          <img
            src="<?= htmlspecialchars($coverUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            alt="<?= htmlspecialchars($titulo !== '' ? $titulo : 'Post', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            class="w-full aspect-square object-cover rounded-2xl border border-rose-500/20 bg-slate-900/70"
          >
        <?php else: ?>
          <div class="w-full aspect-square rounded-2xl border border-slate-800 bg-slate-900/70 flex flex-col items-center justify-center px-5 text-center">
            <div class="w-12 h-12 rounded-2xl bg-rose-500/12 border border-rose-500/20 flex items-center justify-center text-xs font-black tracking-[0.2em] text-rose-200">
              POST
            </div>
            <div class="mt-4 text-sm font-bold text-slate-200">Sem imagem de capa</div>
            <div class="mt-2 text-xs leading-relaxed text-slate-400">A exclusao continua removendo o conteudo e os arquivos locais relacionados, quando existirem.</div>
          </div>
        <?php endif; ?>
      </div>

      <div class="space-y-5">
        <div>
          <div class="flex flex-wrap items-center gap-2 mb-3">
            <span class="admin-chip">ID: <?= $postId ?></span>
            <?php if ($status !== ''): ?><span class="admin-chip">Status: <?= htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span><?php endif; ?>
            <?php if ($dataPublicacao !== ''): ?><span class="admin-chip">Publicado: <?= htmlspecialchars($formatDate($dataPublicacao), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span><?php endif; ?>
          </div>

          <h2 class="font-orbitron text-2xl font-black text-white"><?= htmlspecialchars($titulo !== '' ? $titulo : 'Sem titulo', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
          <?php if ($slug !== ''): ?><div class="mt-2 text-sm text-slate-400">Slug: <?= htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
        </div>

        <?php if ($resumo !== ''): ?>
          <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 text-sm leading-relaxed text-slate-300">
            <?= htmlspecialchars($resumo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>...
          </div>
        <?php endif; ?>

        <div class="rounded-2xl border border-amber-500/25 bg-amber-500/10 p-4">
          <div class="text-sm font-bold text-amber-200">O que sera removido permanentemente</div>
          <ul class="mt-3 text-sm text-amber-100 space-y-2">
            <li>Post completo e seu conteudo.</li>
            <li>Imagem de capa e thumbnail locais, quando existirem em `public/uploads`.</li>
            <li>Referencias deste item na central de posts.</li>
          </ul>
        </div>

        <form id="deletePostForm" method="POST" action="<?= url('/admin/excluir-post?id=' . $postId) ?>" class="flex flex-wrap gap-3">
          <?= Csrf::field() ?>
          <input type="hidden" name="id" value="<?= $postId ?>">

          <button type="submit" class="admin-btn admin-btn-primary" style="background:rgba(244,63,94,.18); border-color:rgba(244,63,94,.35); color:#fecdd3;">
            Excluir permanentemente
          </button>
          <a href="<?= url('/admin/posts') ?>" class="admin-btn admin-btn-secondary">Cancelar</a>
        </form>
      </div>
    </div>
  </section>
</div>

<div id="deleteConfirmModal" style="display:none; position:fixed; inset:0; background:rgba(2,6,23,0.86); z-index:1200; align-items:center; justify-content:center; padding:24px;">
  <div style="width:min(520px,100%); background:#0f172a; border:1px solid rgba(244,63,94,.35); border-radius:20px; box-shadow:0 24px 80px rgba(2,6,23,.6); padding:24px;">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
      <div style="width:44px; height:44px; border-radius:14px; background:rgba(244,63,94,.16); color:#fda4af; display:flex; align-items:center; justify-content:center; font-weight:900;">DEL</div>
      <div>
        <div style="font-family:Orbitron,sans-serif; font-size:1.15rem; font-weight:900; color:#fff;">Confirmar exclusao</div>
        <div style="font-size:.8rem; color:#94a3b8;">Essa acao remove o post permanentemente.</div>
      </div>
    </div>

    <div style="border:1px solid rgba(51,65,85,1); background:rgba(15,23,42,.7); border-radius:16px; padding:14px 16px; color:#cbd5e1; font-size:.95rem; line-height:1.7;">
      Voce esta prestes a excluir <strong style="color:#fff;"><?= htmlspecialchars($titulo !== '' ? $titulo : 'este post', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>.
      Essa operacao nao pode ser desfeita.
    </div>

    <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:20px; flex-wrap:wrap;">
      <button type="button" id="cancelDeleteModal" class="admin-btn admin-btn-secondary">Cancelar</button>
      <button type="button" id="confirmDeleteModal" class="admin-btn admin-btn-primary" style="background:rgba(244,63,94,.18); border-color:rgba(244,63,94,.35); color:#fecdd3;">
        Excluir permanentemente
      </button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('deletePostForm');
  var modal = document.getElementById('deleteConfirmModal');
  var confirmButton = document.getElementById('confirmDeleteModal');
  var cancelButton = document.getElementById('cancelDeleteModal');
  if (!form || !modal || !confirmButton || !cancelButton) return;

  var closeModal = function () {
    modal.style.display = 'none';
    document.body.style.overflow = '';
  };

  var openModal = function () {
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  };

  form.addEventListener('submit', function (event) {
    if (form.dataset.confirmed === '1') {
      form.dataset.confirmed = '0';
      return;
    }

    event.preventDefault();
    openModal();
  });

  confirmButton.addEventListener('click', function () {
    form.dataset.confirmed = '1';
    closeModal();
    form.submit();
  });

  cancelButton.addEventListener('click', closeModal);

  modal.addEventListener('click', function (event) {
    if (event.target === modal) {
      closeModal();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && modal.style.display === 'flex') {
      closeModal();
    }
  });
});
</script>
