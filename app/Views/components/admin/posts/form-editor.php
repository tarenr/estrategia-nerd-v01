<?php
declare(strict_types=1);

$fieldError = $fieldError ?? static fn (string $key): string => '';
$conteudo = (string) ($conteudo ?? '');
?>

<section class="admin-panel">
  <div class="flex items-center justify-between gap-4 flex-wrap mb-4">
    <div>
      <h2 class="font-orbitron text-lg font-black text-white">Conteudo do post</h2>
      <div id="editor-ajuda" class="text-xs text-slate-400 mt-1">Editor do post</div>
    </div>
    <div class="flex items-center gap-2 text-xs text-slate-400">
      <span id="wordCount">0 palavras</span>
      <button type="button" class="admin-btn admin-btn-secondary" onclick="abrirPreview()">Preview</button>
    </div>
  </div>

  <div class="flex flex-wrap gap-2 mb-4 border-b border-slate-800/70 pb-3">
    <button type="button" id="tab-btn-visual" class="px-4 py-2 rounded-t-xl text-sm font-black bg-slate-800 text-gray-400" onclick="switchTab('visual')">Visual</button>
    <button type="button" id="tab-btn-html" class="px-4 py-2 rounded-t-xl text-sm font-black bg-slate-800 text-gray-400" onclick="switchTab('html')">HTML</button>
    <button type="button" id="tab-btn-gerador" class="px-4 py-2 rounded-t-xl text-sm font-black bg-slate-800 text-gray-400" onclick="switchTab('gerador')">Gerador Nerd</button>
  </div>

  <div id="panel-visual" class="editor-panel">
    <div class="editor-toolbar mb-0">
      <button type="button" class="editor-btn" onclick="formatar('bold')"><i class="fa-solid fa-bold"></i></button>
      <button type="button" class="editor-btn" onclick="formatar('italic')"><i class="fa-solid fa-italic"></i></button>
      <button type="button" class="editor-btn" onclick="formatar('insertUnorderedList')"><i class="fa-solid fa-list-ul"></i></button>
      <button type="button" class="editor-btn" onclick="formatar('insertOrderedList')"><i class="fa-solid fa-list-ol"></i></button>
      <button type="button" class="editor-btn" onclick="formatar('formatBlock', '<h2>')">H2</button>
      <button type="button" class="editor-btn" onclick="formatar('formatBlock', '<h3>')">H3</button>
      <button type="button" class="editor-btn" onclick="formatar('blockquote')"><i class="fa-solid fa-quote-left"></i></button>
      <button type="button" class="editor-btn" onclick="inserirLink()"><i class="fa-solid fa-link"></i></button>
      <button type="button" class="editor-btn" onclick="limparFormatacao()"><i class="fa-solid fa-eraser"></i></button>
    </div>
    <div id="editor-visual" class="editor-content" contenteditable="true"><?= $conteudo !== '' ? $conteudo : '<p>Comece a escrever o seu post aqui...</p>' ?></div>
  </div>

  <div id="panel-html" class="editor-panel hidden">
    <textarea id="editor-html" class="nerd-input w-full min-h-[420px] px-4 py-3 rounded-xl font-mono text-sm" oninput="syncFromHtml()"><?= htmlspecialchars($conteudo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
  </div>

  <div id="panel-gerador" class="editor-panel hidden space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="md:col-span-1">
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
      <div class="md:col-span-2">
        <label class="block text-sm font-bold text-slate-200 mb-2">Parametros</label>
        <div id="gerador-campos" class="grid grid-cols-1 md:grid-cols-2 gap-3"></div>
        <p class="mt-3 text-xs text-slate-400">
          No comparativo, use <strong class="text-slate-200">Adicionar linha</strong> para montar cada criterio da tabela em campos separados.
        </p>
      </div>
    </div>

    <div class="flex flex-wrap gap-2">
      <button type="button" class="admin-btn admin-btn-primary" onclick="gerarConteudo()">Gerar estrutura</button>
      <button type="button" id="btn-aplicar" class="admin-btn admin-btn-secondary" onclick="aplicarGerador()">Aplicar no editor</button>
    </div>

    <div id="gerador-preview" class="hidden rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
      <div class="text-sm font-bold text-slate-200 mb-2">Preview do gerador</div>
      <pre id="gerador-preview-content" class="whitespace-pre-wrap text-sm text-slate-300"></pre>
    </div>
  </div>

  <input id="conteudoHidden" name="conteudo" type="hidden" value="<?= htmlspecialchars($conteudo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
  <?php if ($fieldError('conteudo') !== ''): ?><div class="mt-3 text-xs text-rose-300"><?= htmlspecialchars($fieldError('conteudo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
</section>
