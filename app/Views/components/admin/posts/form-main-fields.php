<?php
declare(strict_types=1);

$fieldError = $fieldError ?? static fn (string $key): string => '';
$form = $form ?? [];
?>

<section class="admin-panel space-y-5">
  <div>
    <h2 class="font-orbitron text-lg font-black text-white">Informacoes principais</h2>
    <div class="text-xs text-slate-400 mt-1">Defina titulo, slug, data de publicacao e um resumo curto para o post.</div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
      <label for="titulo" class="block text-sm font-bold text-slate-200 mb-2">Titulo</label>
      <input id="titulo" name="titulo" type="text" value="<?= htmlspecialchars((string) ($form['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Ex.: RTX 5070 vs RX 9070: qual vale mais a pena?">
      <?php if ($fieldError('titulo') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('titulo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
    </div>

    <div>
      <label for="slug" class="block text-sm font-bold text-slate-200 mb-2">Slug</label>
      <div class="flex gap-2">
        <input id="slug" name="slug" type="text" value="<?= htmlspecialchars((string) ($form['slug'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="slug-do-post">
        <button type="button" class="admin-btn admin-btn-secondary" onclick="gerarSlug()">Gerar</button>
      </div>
      <?php if ($fieldError('slug') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('slug'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
    </div>

    <div>
      <label for="data_publicacao" class="block text-sm font-bold text-slate-200 mb-2">Data de publicacao</label>
      <input id="data_publicacao" name="data_publicacao" type="datetime-local" value="<?= htmlspecialchars((string) ($form['data_publicacao'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl">
      <?php if ($fieldError('data_publicacao') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('data_publicacao'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
    </div>
  </div>

  <div>
    <div class="flex items-center justify-between gap-3 flex-wrap mb-2">
      <label for="resumo" class="block text-sm font-bold text-slate-200">Resumo</label>
      <div class="text-xs text-slate-400"><span id="resumoCount">0</span> caracteres</div>
    </div>
    <textarea id="resumo" name="resumo" rows="4" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Resumo curto para listagens e meta description."><?= htmlspecialchars((string) ($form['resumo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
  </div>
</section>
