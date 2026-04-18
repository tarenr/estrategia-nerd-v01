<?php
declare(strict_types=1);

$fieldError = $fieldError ?? static fn (string $key): string => '';
$form = $form ?? [];
$mediaItems = $media_items ?? [];
$imageMediaItems = $image_media_items ?? [];
$orphanFiles = $orphan_files ?? ($orphan_images ?? []);
$quickMediaSource = is_array($imageMediaItems) && $imageMediaItems !== []
    ? array_values($imageMediaItems)
    : array_values(array_filter(is_array($mediaItems ?? null) ? $mediaItems : [], static fn (array $item): bool => (($item['media_type'] ?? '') === 'image') || (($item['is_image'] ?? false) === true)));
$quickMediaItems = array_slice($quickMediaSource, 0, 4);

$coverValue = trim((string) ($form['imagem_capa'] ?? ''));
$thumbValue = trim((string) ($form['imagem_thumb'] ?? ''));
$coverPreview = $coverValue !== '' ? (preg_match('~^https?://~i', $coverValue) ? $coverValue : url('/' . ltrim($coverValue, '/'))) : '';
$thumbPreview = $thumbValue !== '' ? (preg_match('~^https?://~i', $thumbValue) ? $thumbValue : url('/' . ltrim($thumbValue, '/'))) : '';
?>

<div class="post-media-seo-grid">
  <section class="admin-panel post-media-panel space-y-5">
    <div>
      <h2 class="font-orbitron text-lg font-black text-white">Midia</h2>
      <div class="text-xs text-slate-400 mt-1">Defina capa, thumb e use a biblioteca central sem sair do formulario.</div>
    </div>

    <div class="post-media-duo-grid">
      <div class="post-media-card">
        <div class="post-media-card-head">
          <div>
            <h3 class="text-sm font-black text-white">Imagem de capa</h3>
            <div class="text-xs text-slate-400 mt-1">Padrao 16:9 para header, destaque e cards maiores.</div>
          </div>
          <button type="button" class="admin-btn admin-btn-secondary post-media-clear-btn" onclick="selecionarMidia('imagem_capa', '')">Limpar</button>
        </div>

        <div class="post-media-card-body">
          <div id="imagem_capa_preview_wrap" class="post-media-preview<?= $coverPreview !== '' ? ' has-media' : '' ?>">
            <button
              type="button"
              class="post-media-preview-open<?= $coverPreview === '' ? ' is-disabled' : '' ?>"
              data-media-preview-open
              data-media-preview-src="<?= htmlspecialchars($coverPreview, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
              data-media-preview-title="Imagem de capa"
              data-media-preview-path="<?= htmlspecialchars($coverValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
              <?= $coverPreview === '' ? 'disabled' : '' ?>
            >
              <div id="imagem_capa_preview_empty" class="px-4 text-center<?= $coverPreview !== '' ? ' hidden' : '' ?>">Sem capa selecionada</div>
              <img id="imagem_capa_preview" src="<?= htmlspecialchars($coverPreview, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="Preview da capa" class="<?= $coverPreview === '' ? 'hidden ' : '' ?>w-full h-full object-cover">
            </button>
          </div>

          <div class="post-media-fields">
            <div>
              <label for="imagem_capa" class="block text-sm font-bold text-slate-200 mb-2">URL ou caminho</label>
              <input id="imagem_capa" name="imagem_capa" type="text" value="<?= htmlspecialchars($coverValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="/uploads/posts/capas/meu-post.jpg" data-media-preview-input="imagem_capa">
              <?php if ($fieldError('imagem_capa') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('imagem_capa'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
            </div>

            <div>
              <label for="imagem_capa_upload" class="block text-sm font-bold text-slate-200 mb-2">Enviar nova capa</label>
              <input id="imagem_capa_upload" name="imagem_capa_upload" type="file" accept="image/*" class="block w-full text-sm text-slate-300 file:mr-4 file:rounded-xl file:border-0 file:bg-cyan-500/15 file:px-4 file:py-2 file:font-bold file:text-cyan-100 hover:file:bg-cyan-500/25">
              <div class="text-xs text-slate-500 mt-2">JPG, PNG, WEBP, GIF e SVG. A capa nova substitui a anterior.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="post-media-card">
        <div class="post-media-card-head">
          <div>
            <h3 class="text-sm font-black text-white">Thumbnail</h3>
            <div class="text-xs text-slate-400 mt-1">Miniatura 16:9 para listas, cards compactos e previews.</div>
          </div>
          <button type="button" class="admin-btn admin-btn-secondary post-media-clear-btn" onclick="selecionarMidia('imagem_thumb', '')">Limpar</button>
        </div>

        <div class="post-media-card-body">
          <div id="imagem_thumb_preview_wrap" class="post-media-preview<?= $thumbPreview !== '' ? ' has-media' : '' ?>">
            <button
              type="button"
              class="post-media-preview-open<?= $thumbPreview === '' ? ' is-disabled' : '' ?>"
              data-media-preview-open
              data-media-preview-src="<?= htmlspecialchars($thumbPreview, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
              data-media-preview-title="Thumbnail"
              data-media-preview-path="<?= htmlspecialchars($thumbValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
              <?= $thumbPreview === '' ? 'disabled' : '' ?>
            >
              <div id="imagem_thumb_preview_empty" class="px-4 text-center<?= $thumbPreview !== '' ? ' hidden' : '' ?>">Sem thumb selecionada</div>
              <img id="imagem_thumb_preview" src="<?= htmlspecialchars($thumbPreview, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="Preview da thumb" class="<?= $thumbPreview === '' ? 'hidden ' : '' ?>w-full h-full object-cover">
            </button>
          </div>

          <div class="post-media-fields">
            <div>
              <label for="imagem_thumb" class="block text-sm font-bold text-slate-200 mb-2">URL ou caminho</label>
              <input id="imagem_thumb" name="imagem_thumb" type="text" value="<?= htmlspecialchars($thumbValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="/uploads/posts/thumbs/meu-post.jpg" data-media-preview-input="imagem_thumb">
              <?php if ($fieldError('imagem_thumb') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('imagem_thumb'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
            </div>

            <div>
              <label for="imagem_thumb_upload" class="block text-sm font-bold text-slate-200 mb-2">Enviar nova thumb</label>
              <input id="imagem_thumb_upload" name="imagem_thumb_upload" type="file" accept="image/*" class="block w-full text-sm text-slate-300 file:mr-4 file:rounded-xl file:border-0 file:bg-cyan-500/15 file:px-4 file:py-2 file:font-bold file:text-cyan-100 hover:file:bg-cyan-500/25">
              <div class="text-xs text-slate-500 mt-2">Use uma thumb mais limpa para o grid do blog e listagens curtas.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="post-media-quick-panel">
      <div class="post-media-quick-head">
        <div>
          <h3 class="text-sm font-black text-white">Selecao rapida da biblioteca</h3>
          <div class="text-xs text-slate-400 mt-1">Mostra apenas as ultimas imagens para aplicar em um clique.</div>
        </div>
        <a href="<?= htmlspecialchars(url('/admin/midia?tipo=imagem'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary post-media-open-btn">Abrir midia</a>
      </div>

      <?php if ($quickMediaItems === []): ?>
        <div class="rounded-xl border border-dashed border-slate-700 bg-slate-900/40 px-4 py-5 text-sm text-slate-400">Nenhuma imagem recente encontrada na biblioteca central.</div>
      <?php else: ?>
        <div class="post-media-quick-grid">
          <?php foreach ($quickMediaItems as $item): ?>
            <?php $itemUrl = (string) ($item['public_url'] ?? ''); ?>
            <?php $itemPath = (string) ($item['relative_path'] ?? ''); ?>
            <?php $itemName = (string) ($item['name'] ?? $itemPath); ?>
            <div class="post-media-quick-item">
              <button
                type="button"
                class="post-media-quick-thumb post-media-preview-trigger"
                data-media-preview-open
                data-media-preview-src="<?= htmlspecialchars($itemUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                data-media-preview-title="<?= htmlspecialchars($itemName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                data-media-preview-path="<?= htmlspecialchars($itemPath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
              >
                <img src="<?= htmlspecialchars($itemUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($itemName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="w-full h-full object-cover">
              </button>

              <div class="post-media-quick-copy">
                <button
                  type="button"
                  class="post-media-quick-name post-media-preview-link"
                  data-media-preview-open
                  data-media-preview-src="<?= htmlspecialchars($itemUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                  data-media-preview-title="<?= htmlspecialchars($itemName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                  data-media-preview-path="<?= htmlspecialchars($itemPath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                ><?= htmlspecialchars($itemName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
                <div class="post-media-quick-path"><?= htmlspecialchars($itemPath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>

              <div class="post-media-quick-actions">
                <button type="button" class="admin-btn admin-btn-secondary post-media-mini-btn" data-media-pick data-media-target="imagem_capa" data-media-url="<?= htmlspecialchars($itemPath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Capa</button>
                <button type="button" class="admin-btn admin-btn-secondary post-media-mini-btn" data-media-pick data-media-target="imagem_thumb" data-media-url="<?= htmlspecialchars($itemPath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Thumb</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div id="postMediaPreviewPanel" class="post-media-inline-preview hidden">
      <div class="post-media-inline-preview-head">
        <div>
          <div class="post-media-inline-preview-kicker">Preview rapido</div>
          <div id="postMediaPreviewTitle" class="post-media-inline-preview-title">Preview da imagem</div>
          <div id="postMediaPreviewPath" class="post-media-inline-preview-path"></div>
        </div>
        <button type="button" class="admin-btn admin-btn-secondary post-media-inline-preview-close" onclick="window.fecharPreviewMidiaPost && window.fecharPreviewMidiaPost()">Fechar</button>
      </div>
      <div class="post-media-inline-preview-stage">
        <img id="postMediaPreviewImage" src="" alt="Preview da imagem" class="post-media-inline-preview-image">
      </div>
    </div>

    <div class="rounded-2xl border border-amber-500/15 bg-slate-950/40 p-4 space-y-4">
      <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
          <h3 class="text-sm font-black text-white">Limpeza manual do conteudo</h3>
          <div class="text-xs text-slate-400 mt-1">Revise arquivos do post que estao na pasta da publicacao, mas nao aparecem mais no HTML salvo.</div>
        </div>
        <?php if ($orphanFiles !== [] && (int) ($form['id'] ?? 0) > 0): ?>
          <button
            type="submit"
            name="cleanup_orphan_files"
            value="1"
            formaction="<?= htmlspecialchars(url('/admin/limpar-post-arquivos-orfos?id=' . (int) ($form['id'] ?? 0)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            formmethod="post"
            formnovalidate
            class="admin-btn admin-btn-secondary !px-3 !py-2 text-xs"
          >Remover arquivos orfos</button>
        <?php endif; ?>
      </div>

      <?php if ($orphanFiles === []): ?>
        <div class="rounded-xl border border-dashed border-slate-700 bg-slate-900/40 px-4 py-6 text-sm text-slate-400">
          Nenhum arquivo orfo encontrado neste post.
        </div>
      <?php else: ?>
        <div class="rounded-xl border border-amber-500/15 bg-slate-950/30 px-4 py-3 text-xs text-amber-100">
          Encontramos <?= count($orphanFiles) ?> arquivo(s) na pasta do post que nao estao mais referenciados no conteudo atual. A limpeza abaixo remove apenas esses arquivos.
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <?php foreach ($orphanFiles as $item): ?>
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
  </section>

  <section class="admin-panel post-seo-panel space-y-5">
    <div>
      <h2 class="font-orbitron text-lg font-black text-white">SEO</h2>
      <div class="text-xs text-slate-400 mt-1">Ajuste titulo, descricao e palavras-chave para busca e compartilhamento.</div>
    </div>

    <div class="post-seo-stack">
      <div>
        <div class="flex items-center justify-between gap-3 flex-wrap mb-2">
          <label for="seo_title" class="block text-sm font-bold text-slate-200">SEO title</label>
          <div class="text-xs text-slate-400"><span id="seoTitleCount">0</span> caracteres</div>
        </div>
        <input id="seo_title" name="seo_title" type="text" value="<?= htmlspecialchars((string) ($form['seo_title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Titulo otimizado para busca">
        <div class="text-xs text-slate-500 mt-2">Priorize clareza e mantenha o titulo de busca mais objetivo que o publico, se precisar.</div>
        <?php if ($fieldError('seo_title') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('seo_title'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>

      <div>
        <div class="flex items-center justify-between gap-3 flex-wrap mb-2">
          <label for="seo_description" class="block text-sm font-bold text-slate-200">SEO description</label>
          <div class="text-xs text-slate-400"><span id="seoDescCount">0</span> caracteres</div>
        </div>
        <textarea id="seo_description" name="seo_description" rows="4" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Descricao curta para mecanismos de busca."><?= htmlspecialchars((string) ($form['seo_description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
        <div class="text-xs text-slate-500 mt-2">Use uma promessa clara do conteudo e evite repetir o titulo palavra por palavra.</div>
        <?php if ($fieldError('seo_description') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('seo_description'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>

      <div class="post-seo-grid">
        <div>
          <label for="seo_keywords" class="block text-sm font-bold text-slate-200 mb-2">SEO keywords</label>
          <input id="seo_keywords" name="seo_keywords" type="text" value="<?= htmlspecialchars((string) ($form['seo_keywords'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="gpu, review, hardware">
          <div class="text-xs text-slate-500 mt-2">Separe por virgula apenas os termos realmente centrais.</div>
        </div>

        <div>
          <label for="tags" class="block text-sm font-bold text-slate-200 mb-2">Tags</label>
          <input id="tags" name="tags" type="text" value="<?= htmlspecialchars((string) ($form['tags'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="placa de video, comparativo, pc gamer">
          <div class="text-xs text-slate-500 mt-2">Use tags editoriais para navegacao e relacionamento entre posts.</div>
        </div>
      </div>

      <div class="post-seo-note">
        <div class="post-seo-note-title">Leitura rapida</div>
        <div class="post-seo-note-text">SEO title e description devem resumir o beneficio do post em uma leitura curta. Keywords e tags entram so como apoio.</div>
      </div>
    </div>
  </section>
</div>

