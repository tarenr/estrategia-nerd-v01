<?php
declare(strict_types=1);

$item = $item ?? [];
$path = (string) ($item['relative_path'] ?? '');
$url = (string) ($item['public_url'] ?? '#');
?>

<div class="max-w-4xl mx-auto px-4 py-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Excluir Midia</h1>
      <div class="admin-page-subtitle">Confirme a exclusao do arquivo selecionado. Esta acao nao pode ser desfeita.</div>
    </div>
    <div class="admin-page-actions">
      <a href="<?= url('/admin/midia') ?>" class="admin-btn admin-btn-secondary">Voltar para a biblioteca</a>
    </div>
  </div>

  <div class="grid lg:grid-cols-[1.2fr_0.8fr] gap-6">
    <section class="admin-panel">
      <div class="admin-panel-title"><i class="fa-solid fa-triangle-exclamation text-rose-300"></i><span>Confirmacao final</span></div>
      <div class="admin-panel-subtitle">Revise o arquivo antes de remover da biblioteca de uploads.</div>

      <div class="mt-6 rounded-2xl border border-rose-500/20 bg-rose-500/5 p-5">
        <div class="text-sm font-bold text-rose-200">A exclusao remove o arquivo fisicamente de <code>public/uploads</code>.</div>
        <ul class="mt-3 text-sm text-slate-300 space-y-2 list-disc pl-5">
          <li>O link publico deixa de funcionar imediatamente.</li>
          <li>Posts que apontarem para essa URL podem ficar sem imagem.</li>
          <li>Esta acao so deve ser feita quando voce tiver certeza do impacto.</li>
        </ul>
      </div>

      <form method="POST" action="<?= url('/admin/excluir-midia?path=' . rawurlencode($path)) ?>" class="mt-6">
        <?= \App\Support\Csrf::field() ?>
        <input type="hidden" name="path" value="<?= htmlspecialchars($path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <button type="submit" class="admin-btn admin-btn-danger">Excluir permanentemente</button>
      </form>
    </section>

    <aside class="admin-panel">
      <div class="admin-panel-title"><i class="fa-solid fa-file-image text-cyan-300"></i><span>Arquivo</span></div>
      <div class="admin-panel-subtitle">Dados do item selecionado na biblioteca.</div>

      <?php if (($item['is_image'] ?? false) === true): ?>
        <div class="mt-6 rounded-2xl overflow-hidden border border-slate-800 bg-slate-950/50 aspect-[16/10] flex items-center justify-center">
          <img src="<?= htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($item['name'] ?? 'midia'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="w-full h-full object-contain">
        </div>
      <?php endif; ?>

      <dl class="mt-6 space-y-3 text-sm">
        <div><dt class="text-slate-500 uppercase tracking-wide text-[11px]">Arquivo</dt><dd class="text-slate-100 font-semibold break-all"><?= htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd></div>
        <div><dt class="text-slate-500 uppercase tracking-wide text-[11px]">Caminho</dt><dd class="text-slate-300 break-all"><?= htmlspecialchars($path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd></div>
        <div><dt class="text-slate-500 uppercase tracking-wide text-[11px]">Tamanho</dt><dd class="text-slate-300"><?= htmlspecialchars((string) ($item['size_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd></div>
        <div><dt class="text-slate-500 uppercase tracking-wide text-[11px]">Dimensoes</dt><dd class="text-slate-300"><?= htmlspecialchars((string) ($item['dimensions_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd></div>
        <div><dt class="text-slate-500 uppercase tracking-wide text-[11px]">Atualizado</dt><dd class="text-slate-300"><?= htmlspecialchars((string) ($item['modified_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd></div>
      </dl>
    </aside>
  </div>
</div>