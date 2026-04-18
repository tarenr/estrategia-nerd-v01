<?php
declare(strict_types=1);

$fieldError = $fieldError ?? static fn (string $key): string => '';
$conteudo = (string) ($conteudo ?? '');
$mediaItems = $media_items ?? [];
$imageMediaItems = $image_media_items ?? [];
$audioMediaItems = $audio_media_items ?? [];
$videoMediaItems = $video_media_items ?? [];
$editorMediaLibrary = [
  'all' => array_values(is_array($mediaItems) ? $mediaItems : []),
  'image' => array_values(is_array($imageMediaItems) ? $imageMediaItems : []),
  'audio' => array_values(is_array($audioMediaItems) ? $audioMediaItems : []),
  'video' => array_values(is_array($videoMediaItems) ? $videoMediaItems : []),
];
?>

<section class="admin-panel post-editor-panel">
  <div class="post-editor-head">
    <div class="post-editor-head-copy">
      <h2 class="font-orbitron text-lg font-black text-white">Conteudo do post</h2>
      <span id="wordCount" class="post-editor-word-count">0 palavras</span>
    </div>
  </div>

  <div class="editor-tabs-bar flex flex-wrap gap-2 mb-4 border-b border-slate-800/70 pb-3">
    <button type="button" id="tab-btn-visual" class="editor-tab-btn px-4 py-2 rounded-t-xl text-sm font-black bg-slate-800 text-gray-400" onclick="switchTab('visual')">Visual</button>
    <button type="button" id="tab-btn-html" class="editor-tab-btn px-4 py-2 rounded-t-xl text-sm font-black bg-slate-800 text-gray-400" onclick="switchTab('html')">HTML</button>
    <button type="button" id="tab-btn-gerador" class="editor-tab-btn px-4 py-2 rounded-t-xl text-sm font-black bg-slate-800 text-gray-400" onclick="switchTab('gerador')">Gerador Nerd</button>
  </div>

  <div id="panel-visual" class="editor-panel">
    <div class="editor-sticky-shell">
      <div class="editor-toolbar mb-0">
        <div class="editor-toolbar-group">
          <button type="button" class="editor-btn" title="Negrito" onclick="formatar('bold')"><i class="fa-solid fa-bold"></i></button>
          <button type="button" class="editor-btn" title="Italico" onclick="formatar('italic')"><i class="fa-solid fa-italic"></i></button>
          <button type="button" class="editor-btn" title="Lista com marcadores" onclick="formatar('insertUnorderedList')"><i class="fa-solid fa-list-ul"></i></button>
          <button type="button" class="editor-btn" title="Lista numerada" onclick="formatar('insertOrderedList')"><i class="fa-solid fa-list-ol"></i></button>
        </div>

        <div class="editor-toolbar-separator" aria-hidden="true"></div>

        <div class="editor-toolbar-group">
          <button type="button" class="editor-btn" title="Alinhar a esquerda" onclick="formatar('justifyLeft')"><i class="fa-solid fa-align-left"></i></button>
          <button type="button" class="editor-btn" title="Centralizar" onclick="formatar('justifyCenter')"><i class="fa-solid fa-align-center"></i></button>
          <button type="button" class="editor-btn" title="Alinhar a direita" onclick="formatar('justifyRight')"><i class="fa-solid fa-align-right"></i></button>
          <button type="button" class="editor-btn" title="Justificar" onclick="formatar('justifyFull')"><i class="fa-solid fa-align-justify"></i></button>
        </div>

        <div class="editor-toolbar-separator" aria-hidden="true"></div>

        <div class="editor-toolbar-group">
          <button type="button" class="editor-btn" title="Titulo H2" onclick="formatar('formatBlock', '<h2>')">H2</button>
          <button type="button" class="editor-btn" title="Titulo H3" onclick="formatar('formatBlock', '<h3>')">H3</button>
          <button type="button" class="editor-btn" title="Citacao" onclick="aplicarCitacao()"><i class="fa-solid fa-quote-left"></i></button>
          <button type="button" class="editor-btn" title="Limpar formatacao" onclick="limparFormatacao()"><i class="fa-solid fa-eraser"></i></button>
        </div>

        <div class="editor-toolbar-separator" aria-hidden="true"></div>

        <div class="editor-toolbar-group">
          <button type="button" class="editor-btn" title="Inserir link" onclick="inserirLink()"><i class="fa-solid fa-link"></i></button>
          <button type="button" id="editor-toolbar-image-block" class="editor-btn" title="Inserir imagem" onclick="return window.inserirBlocoImagem ? (window.inserirBlocoImagem(), false) : false;"><i class="fa-solid fa-image"></i></button>
          <button type="button" id="editor-toolbar-video-block" class="editor-btn" title="Inserir video" onclick="return window.inserirBlocoVideo ? (window.inserirBlocoVideo(), false) : false;"><i class="fa-solid fa-video"></i></button>
          <button type="button" id="editor-toolbar-audio-block" class="editor-btn" title="Bloco de audio" onclick="return window.inserirBlocoAudio ? (window.inserirBlocoAudio(), false) : false;"><i class="fa-solid fa-volume-high"></i></button>
        </div>

        <div class="editor-toolbar-separator" aria-hidden="true"></div>

        <div class="editor-toolbar-group">
          <button type="button" class="editor-btn" title="Preview" onclick="abrirPreview()"><i class="fa-solid fa-eye"></i></button>
          <button type="submit" form="postForm" class="editor-btn" title="Salvar post"><i class="fa-solid fa-floppy-disk"></i></button>
        </div>
      </div>
    </div>

    <div id="editor-visual" class="editor-content" contenteditable="true"><?= $conteudo !== '' ? $conteudo : '<p>Comece a escrever o seu post aqui...</p>' ?></div>
  </div>

  <div id="panel-html" class="editor-panel hidden">
    <div
      class="admin-html-editor-shell post-html-editor-shell"
      data-html-editor-root
      data-html-editor-form="#postForm"
      data-html-editor-hidden="#conteudoHidden"
      data-html-editor-sync-from-html="1"
    >
      <div class="admin-html-editor-toolbar">
        <div class="admin-html-editor-toolbar-actions">
          <label class="admin-html-editor-toggle">
            <input type="checkbox" data-html-editor-wrap>
            <span>Quebra de linha visual</span>
          </label>
          <button type="button" class="admin-btn admin-btn-secondary admin-html-editor-format-btn" data-html-editor-format>Formatar HTML</button>
        </div>
        <span class="admin-html-editor-status" data-html-editor-status>HTML sincronizado com o conteudo do post</span>
      </div>

      <div data-html-editor-mount class="admin-html-editor-mount"></div>

      <textarea id="editor-html" data-html-editor-textarea class="admin-html-editor-textarea nerd-input w-full min-h-[420px] px-4 py-3 rounded-xl font-mono text-sm" oninput="syncFromHtml()"><?= htmlspecialchars($conteudo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
    </div>
  </div>

  <div id="panel-gerador" class="editor-panel hidden post-gerador-panel space-y-4">
    <div class="post-gerador-grid">
      <div class="post-gerador-type">
        <label for="gerador-template" class="block text-sm font-bold text-slate-200 mb-2">Tipo de conteudo</label>
        <select id="gerador-template" class="nerd-input w-full px-4 py-3 rounded-xl">
          <option value="comparativo">Comparativo</option>
          <option value="review">Review</option>
          <option value="pros_contras">Pros e Contras</option>
          <option value="faq">FAQ</option>
          <option value="ficha_tecnica">Ficha Tecnica</option>
          <option value="guia">Guia</option>
          <option value="noticia">Noticia</option>
          <option value="lista">Lista</option>
        </select>
      </div>

      <div class="post-gerador-fields">
        <label class="block text-sm font-bold text-slate-200 mb-2">Parametros</label>
        <div id="gerador-campos" class="grid grid-cols-1 md:grid-cols-2 gap-3"></div>
        <p class="post-gerador-fields-note">No comparativo, use <strong class="text-slate-200">Adicionar linha</strong> para montar cada criterio da tabela em campos separados.</p>
      </div>
    </div>

    <div class="post-gerador-actions">
      <button type="button" class="admin-btn admin-btn-primary post-gerador-btn" onclick="gerarConteudo()">Gerar estrutura</button>
      <button type="button" id="btn-aplicar" class="admin-btn admin-btn-secondary post-gerador-btn" onclick="aplicarGerador()">Aplicar no editor</button>

      <div class="post-gerador-mode">
        <label for="gerador-apply-mode">Modo de insercao</label>
        <select id="gerador-apply-mode" class="nerd-input px-3 py-2 rounded-xl text-sm max-w-[220px]">
          <option value="cursor">Inserir no cursor</option>
          <option value="append">Adicionar ao final</option>
          <option value="replace">Substituir tudo</option>
        </select>
      </div>
    </div>

    <div id="gerador-preview" class="hidden post-gerador-preview">
      <div class="post-gerador-preview-title">Preview do gerador</div>
      <pre id="gerador-preview-content" class="whitespace-pre-wrap text-sm text-slate-300"></pre>
    </div>
  </div>

  <script id="postEditorMediaLibraryData" type="application/json"><?= json_encode($editorMediaLibrary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <input id="editorImageUpload" type="file" accept="image/*" class="hidden">
  <input id="conteudoHidden" name="conteudo" type="hidden" value="<?= htmlspecialchars($conteudo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
  <?php if ($fieldError('conteudo') !== ''): ?><div class="mt-3 text-xs text-rose-300"><?= htmlspecialchars($fieldError('conteudo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
</section>
