<?php
declare(strict_types=1);

$fieldError = $fieldError ?? static fn (string $key): string => '';
$form = $form ?? [];
?>

<section class="admin-panel space-y-5">
  <div>
    <h2 class="font-orbitron text-lg font-black text-white">Midia e SEO</h2>
    <div class="text-xs text-slate-400 mt-1">Campos complementares para capa, miniatura e busca.</div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label for="imagem_capa" class="block text-sm font-bold text-slate-200 mb-2">Imagem de capa</label>
      <input id="imagem_capa" name="imagem_capa" type="text" value="<?= htmlspecialchars((string) ($form['imagem_capa'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="/uploads/capas/meu-post.jpg">
    </div>

    <div>
      <label for="imagem_thumb" class="block text-sm font-bold text-slate-200 mb-2">Imagem thumb</label>
      <input id="imagem_thumb" name="imagem_thumb" type="text" value="<?= htmlspecialchars((string) ($form['imagem_thumb'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="/uploads/thumbs/meu-post.jpg">
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <div class="flex items-center justify-between gap-3 flex-wrap mb-2">
        <label for="seo_title" class="block text-sm font-bold text-slate-200">SEO title</label>
        <div class="text-xs text-slate-400"><span id="seoTitleCount">0</span> caracteres</div>
      </div>
      <input id="seo_title" name="seo_title" type="text" value="<?= htmlspecialchars((string) ($form['seo_title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Titulo otimizado para busca">
      <?php if ($fieldError('seo_title') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('seo_title'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
    </div>

    <div>
      <div class="flex items-center justify-between gap-3 flex-wrap mb-2">
        <label for="seo_description" class="block text-sm font-bold text-slate-200">SEO description</label>
        <div class="text-xs text-slate-400"><span id="seoDescCount">0</span> caracteres</div>
      </div>
      <textarea id="seo_description" name="seo_description" rows="3" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Descricao curta para mecanismos de busca."><?= htmlspecialchars((string) ($form['seo_description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
      <?php if ($fieldError('seo_description') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('seo_description'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label for="seo_keywords" class="block text-sm font-bold text-slate-200 mb-2">SEO keywords</label>
      <input id="seo_keywords" name="seo_keywords" type="text" value="<?= htmlspecialchars((string) ($form['seo_keywords'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="gpu, review, hardware">
    </div>

    <div>
      <label for="tags" class="block text-sm font-bold text-slate-200 mb-2">Tags</label>
      <input id="tags" name="tags" type="text" value="<?= htmlspecialchars((string) ($form['tags'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="placa de video, comparativo, pc gamer">
    </div>
  </div>
</section>
