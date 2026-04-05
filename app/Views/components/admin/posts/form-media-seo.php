<?php
declare(strict_types=1);

$fieldError = $fieldError ?? static fn (string $key): string => '';
$form = $form ?? [];
$mediaItems = $media_items ?? [];
$orphanImages = $orphan_images ?? [];

$coverValue = trim((string) ($form['imagem_capa'] ?? ''));
$thumbValue = trim((string) ($form['imagem_thumb'] ?? ''));
$coverPreview = $coverValue !== '' ? (preg_match('~^https?://~i', $coverValue) ? $coverValue : url('/' . ltrim($coverValue, '/'))) : '';
$thumbPreview = $thumbValue !== '' ? (preg_match('~^https?://~i', $thumbValue) ? $thumbValue : url('/' . ltrim($thumbValue, '/'))) : '';
?>

<section class="admin-panel space-y-5">
  <div>
    <h2 class="font-orbitron text-lg font-black text-white">Midia e SEO</h2>
    <div class="text-xs text-slate-400 mt-1">Envie capa e thumbnail sem sair da tela e reaproveite imagens recentes da biblioteca.</div>
  </div>

  <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
    <div class="rounded-2xl border border-cyan-500/15 bg-slate-950/40 p-4 space-y-4">
      <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
          <h3 class="text-sm font-black text-white">Imagem de capa</h3>
          <div class="text-xs text-slate-400 mt-1">Padrao horizontal 16:9 para header, destaque e cards maiores.</div>
        </div>
        <button type="button" class="admin-btn admin-btn-secondary !px-3 !py-2 text-xs" onclick="selecionarMidia('imagem_capa', '')">Limpar</button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-[160px_1fr] gap-4 items-start">
        <div id="imagem_capa_preview_wrap" class="aspect-[16/9] rounded-2xl border border-cyan-500/20 bg-slate-900/70 overflow-hidden flex items-center justify-center text-xs text-slate-500">
          <?php if ($coverPreview !== ''): ?>
            <img id="imagem_capa_preview" src="<?= htmlspecialchars($coverPreview, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="Preview da capa" class="w-full h-full object-cover">
          <?php else: ?>
            <div id="imagem_capa_preview_empty" class="px-4 text-center">Sem capa selecionada</div>
            <img id="imagem_capa_preview" src="" alt="Preview da capa" class="hidden w-full h-full object-cover">
          <?php endif; ?>
        </div>

        <div class="space-y-3">
          <div>
            <label for="imagem_capa" class="block text-sm font-bold text-slate-200 mb-2">URL ou caminho</label>
            <input id="imagem_capa" name="imagem_capa" type="text" value="<?= htmlspecialchars($coverValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="/uploads/posts/capas/meu-post.jpg" data-media-preview-input="imagem_capa">
            <?php if ($fieldError('imagem_capa') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('imagem_capa'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
          </div>
          <div>
            <label for="imagem_capa_upload" class="block text-sm font-bold text-slate-200 mb-2">Enviar nova capa</label>
            <input id="imagem_capa_upload" name="imagem_capa_upload" type="file" accept="image/*" class="block w-full text-sm text-slate-300 file:mr-4 file:rounded-xl file:border-0 file:bg-cyan-500/15 file:px-4 file:py-2 file:font-bold file:text-cyan-100 hover:file:bg-cyan-500/25">
            <div class="text-xs text-slate-500 mt-2">Formatos aceitos: JPG, PNG, WEBP, GIF e SVG.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="rounded-2xl border border-cyan-500/15 bg-slate-950/40 p-4 space-y-4">
      <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
          <h3 class="text-sm font-black text-white">Thumbnail</h3>
          <div class="text-xs text-slate-400 mt-1">Para listagens compactas, cards menores e miniaturas.</div>
        </div>
        <button type="button" class="admin-btn admin-btn-secondary !px-3 !py-2 text-xs" onclick="selecionarMidia('imagem_thumb', '')">Limpar</button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-[160px_1fr] gap-4 items-start">
        <div id="imagem_thumb_preview_wrap" class="aspect-[16/9] rounded-2xl border border-cyan-500/20 bg-slate-900/70 overflow-hidden flex items-center justify-center text-xs text-slate-500">
          <?php if ($thumbPreview !== ''): ?>
            <img id="imagem_thumb_preview" src="<?= htmlspecialchars($thumbPreview, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="Preview da thumb" class="w-full h-full object-cover">
          <?php else: ?>
            <div id="imagem_thumb_preview_empty" class="px-4 text-center">Sem thumb selecionada</div>
            <img id="imagem_thumb_preview" src="" alt="Preview da thumb" class="hidden w-full h-full object-cover">
          <?php endif; ?>
        </div>

        <div class="space-y-3">
          <div>
            <label for="imagem_thumb" class="block text-sm font-bold text-slate-200 mb-2">URL ou caminho</label>
            <input id="imagem_thumb" name="imagem_thumb" type="text" value="<?= htmlspecialchars($thumbValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="/uploads/posts/thumbs/meu-post.jpg" data-media-preview-input="imagem_thumb">
            <?php if ($fieldError('imagem_thumb') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('imagem_thumb'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
          </div>
          <div>
            <label for="imagem_thumb_upload" class="block text-sm font-bold text-slate-200 mb-2">Enviar nova thumb</label>
            <input id="imagem_thumb_upload" name="imagem_thumb_upload" type="file" accept="image/*" class="block w-full text-sm text-slate-300 file:mr-4 file:rounded-xl file:border-0 file:bg-cyan-500/15 file:px-4 file:py-2 file:font-bold file:text-cyan-100 hover:file:bg-cyan-500/25">
            <div class="text-xs text-slate-500 mt-2">Padrao horizontal 16:9 para listagens, cards e miniaturas.</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="rounded-2xl border border-cyan-500/15 bg-slate-950/40 p-4 space-y-4">
    <div class="flex items-center justify-between gap-3 flex-wrap">
      <div>
        <h3 class="text-sm font-black text-white">Biblioteca recente</h3>
        <div class="text-xs text-slate-400 mt-1">Escolha uma imagem ja enviada e aplique em um clique.</div>
      </div>
      <a href="<?= htmlspecialchars(url('/admin/midia'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary !px-3 !py-2 text-xs">Abrir midia</a>
    </div>

    <?php if ($mediaItems === []): ?>
      <div class="rounded-xl border border-dashed border-slate-700 bg-slate-900/40 px-4 py-6 text-sm text-slate-400">Nenhuma imagem recente encontrada na biblioteca.</div>
    <?php else: ?>
      <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
        <?php foreach ($mediaItems as $item): ?>
          <?php $itemUrl = (string) ($item['public_url'] ?? ''); ?>
          <?php $itemPath = (string) ($item['relative_path'] ?? ''); ?>
          <div class="rounded-2xl border border-cyan-500/15 bg-slate-900/50 overflow-hidden">
            <div class="aspect-square bg-slate-950/70 overflow-hidden">
              <img src="<?= htmlspecialchars($itemUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($item['name'] ?? 'Midia'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="w-full h-full object-cover">
            </div>
            <div class="p-3 space-y-2">
              <div class="text-[11px] leading-4 text-slate-300 break-all"><?= htmlspecialchars((string) ($item['name'] ?? $itemPath), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <div class="grid grid-cols-2 gap-2">
                <button type="button" class="admin-btn admin-btn-secondary !px-2 !py-2 text-[11px]" data-media-pick data-media-target="imagem_capa" data-media-url="<?= htmlspecialchars($itemPath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Usar capa</button>
                <button type="button" class="admin-btn admin-btn-secondary !px-2 !py-2 text-[11px]" data-media-pick data-media-target="imagem_thumb" data-media-url="<?= htmlspecialchars($itemPath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Usar thumb</button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="rounded-2xl border border-amber-500/15 bg-slate-950/40 p-4 space-y-4">
    <div class="flex items-center justify-between gap-3 flex-wrap">
      <div>
        <h3 class="text-sm font-black text-white">Limpeza manual do conteudo</h3>
        <div class="text-xs text-slate-400 mt-1">Revise imagens do corpo que estao na pasta do post, mas nao aparecem mais no HTML salvo.</div>
      </div>
      <?php if ($orphanImages !== [] && (int) ($form['id'] ?? 0) > 0): ?>
        <button
          type="submit"
          name="cleanup_orphan_images"
          value="1"
          formaction="<?= htmlspecialchars(url('/admin/limpar-post-imagens-orfas?id=' . (int) ($form['id'] ?? 0)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
          formmethod="post"
          formnovalidate
          class="admin-btn admin-btn-secondary !px-3 !py-2 text-xs"
        >Remover imagens orfas</button>
      <?php endif; ?>
    </div>

    <?php if ($orphanImages === []): ?>
      <div class="rounded-xl border border-dashed border-slate-700 bg-slate-900/40 px-4 py-6 text-sm text-slate-400">
        Nenhuma imagem orfa encontrada neste post.
      </div>
    <?php else: ?>
      <div class="rounded-xl border border-amber-500/15 bg-slate-950/30 px-4 py-3 text-xs text-amber-100">
        Encontramos <?= count($orphanImages) ?> arquivo(s) na pasta do post que nao estao mais referenciados no conteudo atual. A limpeza abaixo remove apenas esses arquivos.
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
        <?php foreach ($orphanImages as $item): ?>
          <div class="rounded-2xl border border-amber-500/15 bg-slate-900/50 overflow-hidden">
            <div class="aspect-video bg-slate-950/70 overflow-hidden flex items-center justify-center">
              <?php if (($item['is_image'] ?? false) === true): ?>
                <img src="<?= htmlspecialchars((string) ($item['public_url'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($item['name'] ?? 'Imagem orfa'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="w-full h-full object-cover">
              <?php else: ?>
                <div class="px-4 text-center text-xs text-slate-500">Arquivo sem preview</div>
              <?php endif; ?>
            </div>
            <div class="p-3 space-y-2">
              <div class="text-[11px] leading-4 text-slate-200 break-all"><?= htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <div class="text-[11px] text-slate-400 break-all"><?= htmlspecialchars((string) ($item['relative_path'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <div class="flex items-center justify-between gap-3 text-[11px] text-slate-500">
                <span><?= htmlspecialchars((string) ($item['size_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <span><?= htmlspecialchars((string) ($item['modified_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
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
