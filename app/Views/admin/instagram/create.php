<?php
/**
 * @file        app/Views/admin/instagram/create.php
 * @project     Estrategia Nerd
 * @purpose     Tela de criação de post do Instagram (FEAT-010)
 *
 * Variáveis disponíveis (injetadas pelo InstagramController::create()):
 * @var string                        $title
 * @var array<string,mixed>|null      $account      Conta ativa
 * @var array<int,array<string,mixed>> $blog_posts   Posts publicados do blog
 * @var string                        $csrf_token
 * @var string|null                   $error        Mensagem de erro de validação
 * @var array<string,mixed>           $old          Valores anteriores do formulário
 */

declare(strict_types=1);

$account   = $account ?? null;
$blogPosts = $blog_posts ?? [];
$csrfToken = $csrf_token ?? '';
$error     = $error ?? null;
$old       = $old ?? [];

$esc = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$oldTipo     = (string) ($old['tipo'] ?? 'imagem');
$oldLegenda  = (string) ($old['legenda'] ?? '');
$oldHashtags = (string) ($old['hashtags'] ?? '');
$oldAcao     = (string) ($old['acao'] ?? 'rascunho');
$oldAgendado = (string) ($old['agendado_para'] ?? '');
$oldBlogId   = (string) ($old['post_blog_id'] ?? '');
?>

<div class="max-w-7xl mx-auto px-4 py-6" data-instagram-form-root>
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title"><i class="fa-brands fa-instagram text-pink-400" aria-hidden="true"></i> Novo Post — Instagram</h1>
      <div class="admin-page-subtitle">Crie um rascunho, agende ou publique diretamente no Instagram.</div>
    </div>
    <div class="admin-page-actions">
      <a href="<?= url('/admin/instagram') ?>" class="admin-btn admin-btn-secondary"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar</a>
    </div>
  </div>

  <div class="mt-6">
    <?php if ($account === null): ?>
      <section class="admin-panel border border-amber-500/30">
        <div class="text-sm font-bold text-amber-200">Nenhuma conta Instagram configurada.</div>
        <div class="text-xs text-slate-400 mt-1">Configure a conta antes de criar posts — veja as instruções no <a href="<?= url('/admin/instagram') ?>" class="text-cyan-300 underline">dashboard do Instagram</a>.</div>
      </section>
    <?php endif; ?>

    <?php if ($error !== null && $error !== ''): ?>
      <section class="admin-panel border border-rose-500/30 mt-4">
        <div class="text-sm font-bold text-rose-300">Ajustes necessários</div>
        <div class="mt-2 text-sm text-rose-100"><?= $esc($error) ?></div>
      </section>
    <?php endif; ?>

    <form id="igPostForm" method="POST" action="<?= url('/admin/instagram/posts/criar') ?>" enctype="multipart/form-data" class="flex flex-col lg:flex-row items-start gap-6 mt-4" novalidate>
      <input type="hidden" name="_csrf_token" value="<?= $esc($csrfToken) ?>">
      <input type="hidden" name="tipo" id="igTipoInput" value="<?= $esc($oldTipo) ?>">
      <input type="hidden" name="post_blog_id" id="igPostBlogId" value="<?= $esc($oldBlogId) ?>">
      <div id="igMediaUrlsWrap" class="hidden"></div>

      <!-- Coluna da Esquerda: Configuração e Campos do Post (62%) -->
      <div class="w-full lg:w-[62%] space-y-6">
        <!-- Seletor de tipo -->
        <section class="admin-panel">
          <div class="admin-panel-title"><i class="fa-solid fa-layer-group text-cyan-300" aria-hidden="true"></i><span>Tipo de post</span></div>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4" data-ig-tipo-select>
            <?php foreach (['imagem' => ['Imagem', 'fa-image'], 'carrossel' => ['Carrossel', 'fa-images'], 'reels' => ['Reels', 'fa-clapperboard'], 'story' => ['Story', 'fa-circle-play']] as $tipoKey => [$tipoLabel, $tipoIcon]): ?>
              <button type="button" class="ig-tipo-card<?= $oldTipo === $tipoKey ? ' ig-tipo-card-active' : '' ?>" data-ig-tipo="<?= $esc($tipoKey) ?>">
                <i class="fa-solid <?= $esc($tipoIcon) ?>" aria-hidden="true"></i>
                <span><?= $esc($tipoLabel) ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        </section>

        <!-- Importar do blog -->
        <?php if ($blogPosts !== []): ?>
          <section class="admin-panel">
            <div class="admin-panel-title"><i class="fa-solid fa-rss text-orange-300" aria-hidden="true"></i><span>Importar do Blog</span></div>
            <label for="igBlogSelect" class="admin-filter-label mt-3 block">Post do blog</label>
            <select id="igBlogSelect" class="nerd-input admin-filter-control w-full mt-2">
              <option value="">— Selecionar um post publicado —</option>
              <?php foreach ($blogPosts as $bp): ?>
                <option value="<?= (int) ($bp['id'] ?? 0) ?>" <?= $oldBlogId === (string) ($bp['id'] ?? '') ? 'selected' : '' ?>><?= $esc((string) ($bp['titulo'] ?? '')) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="mt-3 hidden" data-ig-blog-preview>
              <div class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-900/60 p-3">
                <img data-ig-blog-preview-img class="h-14 w-14 rounded-lg object-cover bg-slate-800" alt="" onerror="this.style.display='none';">
                <div class="min-w-0 flex-1">
                  <div class="text-sm font-bold text-white truncate" data-ig-blog-preview-title></div>
                  <div class="text-xs text-slate-400 truncate" data-ig-blog-preview-resumo></div>
                </div>
                <button type="button" class="admin-btn admin-btn-secondary !px-3 !py-1.5 text-xs" data-ig-blog-clear>Remover</button>
              </div>
            </div>
          </section>
        <?php endif; ?>

        <!-- Upload de mídia -->
        <section class="admin-panel">
          <div class="admin-panel-title"><i class="fa-solid fa-upload text-cyan-300" aria-hidden="true"></i><span>Mídia</span></div>
          <label for="igMediaInput" class="ig-dropzone mt-3" data-ig-dropzone tabindex="0" role="button">
            <input id="igMediaInput" name="medias[]" type="file" accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime" multiple class="sr-only" data-ig-media-input>
            <div class="text-center">
              <i class="fa-solid fa-cloud-arrow-up text-2xl text-cyan-300" aria-hidden="true"></i>
              <div class="text-sm font-bold text-slate-200 mt-2">Arraste as mídias aqui ou clique para selecionar</div>
              <div class="text-xs text-slate-500 mt-1">Imagem ou vídeo — carrossel aceita de 2 a 10 itens</div>
            </div>
          </label>
          <div class="grid grid-cols-3 sm:grid-cols-4 gap-3 mt-4" data-ig-media-preview></div>
        </section>

        <!-- Legenda e Hashtags -->
        <section class="admin-panel">
          <div class="flex items-center justify-between">
            <label for="igLegenda" class="admin-filter-label mb-0">Texto da Legenda</label>
            <div class="flex items-center gap-3 text-xs">
              <span data-ig-char-count>0</span><span class="text-slate-500">/2.200 caracteres</span>
            </div>
          </div>
          <textarea id="igLegenda" name="legenda" rows="5" maxlength="2200" class="nerd-input admin-filter-control w-full mt-2" placeholder="Escreva o texto principal do post aqui..."><?= $esc($oldLegenda) ?></textarea>

          <div class="mt-4 pt-4 border-t border-slate-800">
            <div class="flex items-center justify-between">
              <label for="igHashtags" class="admin-filter-label mb-0 flex items-center gap-1.5">
                <i class="fa-solid fa-hashtag text-cyan-400" aria-hidden="true"></i>
                <span>Hashtags do Post</span>
              </label>
              <div class="flex items-center gap-2 text-xs">
                <span data-ig-hashtag-count>0</span><span class="text-slate-500">/30 hashtags</span>
              </div>
            </div>
            <input type="text" id="igHashtags" name="hashtags" value="<?= $esc($oldHashtags ?? '') ?>" class="nerd-input admin-filter-control w-full mt-2" placeholder="Ex: #games #rpg #nerd #dicas (digite com ou sem #)">
            <div class="mt-1.5 text-[11px] text-slate-500">As hashtags serão anexadas ao final da publicação automaticamente.</div>
          </div>
        </section>

        <!-- Ações -->
        <section class="admin-panel">
          <label class="admin-filter-label">Quando publicar</label>
          <div class="mt-2" data-ig-agendado-wrap style="display:none;">
            <input type="datetime-local" id="igAgendadoPara" value="<?= $esc(str_replace(' ', 'T', substr($oldAgendado, 0, 16))) ?>" class="nerd-input admin-filter-control w-full sm:w-64">
            <input type="hidden" name="agendado_para" id="igAgendadoParaSubmit" value="">
          </div>

          <div class="flex flex-wrap gap-3 mt-4">
            <button type="submit" name="acao" value="rascunho" class="admin-btn admin-btn-secondary"<?= $oldAcao === 'rascunho' ? '' : '' ?>><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Salvar como Rascunho</button>
            <button type="submit" name="acao" value="agendar" class="admin-btn admin-btn-secondary" data-ig-acao-agendar><i class="fa-solid fa-clock" aria-hidden="true"></i> Agendar Publicação</button>
            <button type="submit" name="acao" value="publicar" class="admin-btn admin-btn-primary" data-ig-acao-publicar<?= $account === null ? ' disabled' : '' ?>><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Publicar Agora</button>
          </div>
        </section>
      </div>

      <!-- Coluna da Direita: Preview Interativo (38%, sticky ao lado dos cards) -->
      <div class="w-full lg:w-[38%] sticky top-6 self-start space-y-4">
        <section class="admin-panel shadow-2xl">
          <div class="admin-panel-title"><i class="fa-solid fa-mobile-screen text-violet-300" aria-hidden="true"></i><span>Preview em Tempo Real</span></div>
          <div class="mt-4 mx-auto w-full max-w-[310px] rounded-2xl border border-slate-700 bg-black overflow-hidden shadow-2xl">
            <div class="flex items-center gap-2 p-2.5 border-b border-slate-800">
              <div class="h-6 w-6 rounded-full bg-gradient-to-br from-pink-500 to-orange-400"></div>
              <div class="text-xs font-bold text-white">@<?= $esc((string) ($account['username'] ?? 'sua_conta')) ?></div>
              <div class="ml-auto text-[10px] rounded-full bg-slate-800 px-2 py-0.5 text-slate-300" data-ig-preview-tipo>Imagem</div>
            </div>
            <div class="aspect-square bg-slate-900 flex items-center justify-center overflow-hidden" data-ig-preview-media>
              <i class="fa-solid fa-image text-3xl text-slate-700" aria-hidden="true"></i>
            </div>
            <div class="p-3">
              <div class="flex items-center gap-3 text-slate-300 mb-2">
                <i class="fa-regular fa-heart" aria-hidden="true"></i>
                <i class="fa-regular fa-comment" aria-hidden="true"></i>
                <i class="fa-regular fa-paper-plane" aria-hidden="true"></i>
              </div>
              <div class="text-xs text-slate-200 leading-relaxed"><span class="font-bold">@<?= $esc((string) ($account['username'] ?? 'sua_conta')) ?></span> <span data-ig-preview-caption class="text-slate-400">Sua legenda aparece aqui…</span></div>
            </div>
          </div>
        </section>
      </div>
    </form>
  </div>
</div>

<style>
.ig-dropzone { display:flex; align-items:center; justify-content:center; border:2px dashed rgba(100,116,139,.4); border-radius:16px; padding:28px 16px; cursor:pointer; transition:border-color .15s ease, background-color .15s ease; }
.ig-dropzone:hover, .ig-dropzone.is-dragover { border-color: rgba(34,211,238,.6); background: rgba(34,211,238,.04); }
.ig-tipo-card { display:flex; flex-direction:column; align-items:center; gap:6px; padding:14px 8px; border-radius:14px; border:1px solid rgba(100,116,139,.35); background: rgba(15,23,42,.4); color:#cbd5e1; font-size:12px; font-weight:700; cursor:pointer; transition: border-color .15s ease, color .15s ease; }
.ig-tipo-card i { font-size:18px; }
.ig-tipo-card:hover { border-color: rgba(34,211,238,.5); }
.ig-tipo-card-active { border-color:#22d3ee; color:#e0f7fa; background: rgba(34,211,238,.08); }
</style>

<script>
(function () {
  var form = document.getElementById('igPostForm');
  if (!form) { return; }

  var appBaseUrl = <?= json_encode(rtrim(url('/'), '/'), JSON_UNESCAPED_SLASHES) ?>;
  function resolveMediaUrl(path) {
    if (!path) { return ''; }
    if (/^https?:\/\//i.test(path)) { return path; }
    return appBaseUrl + '/' + String(path).replace(/^\//, '');
  }

  var tipoInput = document.getElementById('igTipoInput');
  var tipoSelect = form.querySelector('[data-ig-tipo-select]');
  var tipoWarning = form.querySelector('[data-ig-tipo-warning]');
  var previewTipoLabel = form.querySelector('[data-ig-preview-tipo]');
  var acaoPublicarBtn = form.querySelector('[data-ig-acao-publicar]');
  var acaoAgendarBtn = form.querySelector('[data-ig-acao-agendar]');
  var agendadoWrap = form.querySelector('[data-ig-agendado-wrap]');
  var agendadoInput = document.getElementById('igAgendadoPara');
  var tipoLabels = { imagem: 'Imagem', carrossel: 'Carrossel', reels: 'Reels', story: 'Story' };

  function applyTipo(tipo) {
    tipoInput.value = tipo;
    tipoSelect.querySelectorAll('[data-ig-tipo]').forEach(function (card) {
      card.classList.toggle('ig-tipo-card-active', card.getAttribute('data-ig-tipo') === tipo);
    });
    if (previewTipoLabel) { previewTipoLabel.textContent = tipoLabels[tipo] || tipo; }
  }

  if (tipoSelect) {
    tipoSelect.addEventListener('click', function (event) {
      var card = event.target.closest('[data-ig-tipo]');
      if (!card) { return; }
      applyTipo(card.getAttribute('data-ig-tipo'));
    });
  }
  applyTipo(tipoInput.value || 'imagem');

  // Agendamento: mostra o campo de data só quando o botao "Agendar" e usado.
  if (acaoAgendarBtn && agendadoWrap) {
    acaoAgendarBtn.addEventListener('click', function () {
      agendadoWrap.style.display = 'block';
      if (agendadoInput) { agendadoInput.focus(); }
    });
  }
  if (agendadoInput && agendadoInput.value !== '') {
    agendadoWrap.style.display = 'block';
  }

  // Legenda e Hashtags: contadores independentes e preview sincronizado.
  var legenda = document.getElementById('igLegenda');
  var hashtagsInput = document.getElementById('igHashtags');
  var charCount = form.querySelector('[data-ig-char-count]');
  var hashtagCount = form.querySelector('[data-ig-hashtag-count]');
  var previewCaption = form.querySelector('[data-ig-preview-caption]');

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function updateLegendaCounters() {
    var textVal = legenda ? (legenda.value || '') : '';
    var hashVal = hashtagsInput ? (hashtagsInput.value || '') : '';

    // Extrai e contabiliza hashtags
    var tags = [];
    if (hashVal.trim() !== '') {
      var parts = hashVal.trim().split(/[\s,]+/);
      parts.forEach(function (p) {
        var clean = p.replace(/^#+/, '');
        if (clean.length > 0) { tags.push(clean.toLowerCase()); }
      });
    }
    var textTags = textVal.match(/#[^\s#]+/g);
    if (textTags) {
      textTags.forEach(function (t) {
        var clean = t.replace(/^#+/, '');
        if (clean.length > 0) { tags.push(clean.toLowerCase()); }
      });
    }
    var uniqueTags = Array.from(new Set(tags));

    // Caracteres totais
    var totalChars = textVal.length + (hashVal.trim() !== '' ? (textVal ? 2 : 0) + hashVal.length : 0);

    if (charCount) {
      charCount.textContent = String(totalChars);
      charCount.classList.toggle('text-rose-400', totalChars > 2200);
      charCount.classList.toggle('font-bold', totalChars > 2200);
    }
    if (hashtagCount) {
      var tagCount = uniqueTags.length;
      hashtagCount.textContent = String(tagCount);
      hashtagCount.classList.toggle('text-rose-400', tagCount > 30);
      hashtagCount.classList.toggle('font-bold', tagCount > 30);
    }
    if (previewCaption) {
      var displayCaption = textVal.trim();
      var formattedHashtags = '';
      if (hashVal.trim() !== '') {
        formattedHashtags = hashVal.trim().split(/[\s,]+/).filter(function (w) { return w.length > 0; }).map(function (w) {
          return w.startsWith('#') ? w : '#' + w;
        }).join(' ');
      }

      if (displayCaption !== '' && formattedHashtags !== '') {
        previewCaption.innerHTML = escapeHtml(displayCaption).replace(/\n/g, '<br>') + '<br><br><span class="text-cyan-400">' + escapeHtml(formattedHashtags) + '</span>';
      } else if (displayCaption !== '') {
        previewCaption.innerHTML = escapeHtml(displayCaption).replace(/\n/g, '<br>');
      } else if (formattedHashtags !== '') {
        previewCaption.innerHTML = '<span class="text-cyan-400">' + escapeHtml(formattedHashtags) + '</span>';
      } else {
        previewCaption.textContent = 'Sua legenda aparece aqui…';
      }
    }
  }

  if (legenda) {
    legenda.addEventListener('input', updateLegendaCounters);
  }
  if (hashtagsInput) {
    hashtagsInput.addEventListener('input', updateLegendaCounters);
  }
  updateLegendaCounters();

  // Upload de midia: dropzone + preview + espelha no mockup.
  var dropzone = form.querySelector('[data-ig-dropzone]');
  var mediaInput = document.getElementById('igMediaInput');
  var mediaPreview = form.querySelector('[data-ig-media-preview]');
  var previewMedia = form.querySelector('[data-ig-preview-media]');

  function renderMediaPreview() {
    if (!mediaInput || !mediaPreview) { return; }
    mediaPreview.innerHTML = '';
    if (previewMedia) { previewMedia.innerHTML = '<i class="fa-solid fa-image text-3xl text-slate-700"></i>'; }

    var files = Array.prototype.slice.call(mediaInput.files || []);
    files.forEach(function (file, index) {
      var reader = new FileReader();
      reader.onload = function (event) {
        var isVideo = file.type.indexOf('video') === 0;
        var thumb = document.createElement('div');
        thumb.className = 'aspect-square rounded-lg overflow-hidden bg-slate-800 border border-slate-700 flex items-center justify-center';
        if (isVideo) {
          thumb.innerHTML = '<i class="fa-solid fa-video text-slate-400"></i>';
        } else {
          var img = document.createElement('img');
          img.src = event.target.result;
          img.className = 'h-full w-full object-cover';
          thumb.appendChild(img);
        }
        mediaPreview.appendChild(thumb);

        if (index === 0 && previewMedia && !isVideo) {
          previewMedia.innerHTML = '<img src="' + event.target.result + '" class="h-full w-full object-cover">';
        }
      };
      reader.readAsDataURL(file);
    });
  }

  if (mediaInput) {
    mediaInput.addEventListener('change', renderMediaPreview);
  }
  if (dropzone && mediaInput) {
    ['dragover', 'dragenter'].forEach(function (evt) {
      dropzone.addEventListener(evt, function (e) { e.preventDefault(); dropzone.classList.add('is-dragover'); });
    });
    ['dragleave', 'drop'].forEach(function (evt) {
      dropzone.addEventListener(evt, function (e) { e.preventDefault(); dropzone.classList.remove('is-dragover'); });
    });
    dropzone.addEventListener('drop', function (e) {
      if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
        mediaInput.files = e.dataTransfer.files;
        renderMediaPreview();
      }
    });
  }

  // Importar do blog: preenche legenda/capa e usa media_urls[] no lugar do upload.
  var blogSelect = document.getElementById('igBlogSelect');
  var blogPreview = form.querySelector('[data-ig-blog-preview]');
  var blogPreviewImg = form.querySelector('[data-ig-blog-preview-img]');
  var blogPreviewTitle = form.querySelector('[data-ig-blog-preview-title]');
  var blogPreviewResumo = form.querySelector('[data-ig-blog-preview-resumo]');
  var blogClearBtn = form.querySelector('[data-ig-blog-clear]');
  var postBlogIdInput = document.getElementById('igPostBlogId');
  var mediaUrlsWrap = document.getElementById('igMediaUrlsWrap');

  function clearBlogImport() {
    if (postBlogIdInput) { postBlogIdInput.value = ''; }
    if (mediaUrlsWrap) { mediaUrlsWrap.innerHTML = ''; }
    if (mediaInput) { mediaInput.disabled = false; }
    if (dropzone) { dropzone.classList.remove('opacity-50', 'pointer-events-none'); }
    if (blogPreview) { blogPreview.classList.add('hidden'); }
    if (blogSelect) { blogSelect.value = ''; }
  }

  if (blogSelect) {
    blogSelect.addEventListener('change', function () {
      var id = blogSelect.value;
      if (!id) { clearBlogImport(); return; }

      fetch('<?= url('/admin/instagram/api/blog-post') ?>?id=' + encodeURIComponent(id))
        .then(function (res) { return res.json(); })
        .then(function (result) {
          if (!result || !result.ok || !result.post) { return; }
          var post = result.post;

          if (legenda && legenda.value.trim() === '') {
            legenda.value = (post.titulo || '') + (post.resumo ? '\n\n' + post.resumo : '');
            updateLegendaCounters();
          }
          if (postBlogIdInput) { postBlogIdInput.value = String(post.id || ''); }

          if (mediaUrlsWrap) {
            var capaUrl = post.capa || '';
            mediaUrlsWrap.innerHTML = capaUrl ? '<input type="hidden" name="media_urls[0]" value="' + capaUrl.replace(/"/g, '&quot;') + '">' : '';
          }
          if (mediaInput) { mediaInput.disabled = true; mediaInput.value = ''; }
          if (dropzone) { dropzone.classList.add('opacity-50', 'pointer-events-none'); }

          if (blogPreview) { blogPreview.classList.remove('hidden'); }
          if (blogPreviewImg) { blogPreviewImg.src = resolveMediaUrl(post.capa); }
          if (blogPreviewTitle) { blogPreviewTitle.textContent = post.titulo || ''; }
          if (blogPreviewResumo) { blogPreviewResumo.textContent = post.resumo || ''; }
          if (previewMedia && post.capa) { previewMedia.innerHTML = '<img src="' + resolveMediaUrl(post.capa) + '" class="h-full w-full object-cover">'; }
        })
        .catch(function () { /* silencioso: usuario pode preencher manualmente */ });
    });
  }
  if (blogClearBtn) {
    blogClearBtn.addEventListener('click', clearBlogImport);
  }

  // Normaliza agendado_para para "Y-m-d H:i:s" (campo hidden) antes do submit e valida quando "Agendar" for usado.
  var agendadoSubmitInput = document.getElementById('igAgendadoParaSubmit');
  form.addEventListener('submit', function (event) {
    var submitter = event.submitter;
    var acao = submitter ? submitter.value : '';

    if (acao === 'agendar' && agendadoInput && agendadoInput.value === '') {
      event.preventDefault();
      agendadoWrap.style.display = 'block';
      agendadoInput.focus();
      return;
    }

    if (agendadoSubmitInput) {
      agendadoSubmitInput.value = (agendadoInput && agendadoInput.value !== '')
        ? agendadoInput.value.replace('T', ' ') + ':00'
        : '';
    }
  });
})();
</script>
