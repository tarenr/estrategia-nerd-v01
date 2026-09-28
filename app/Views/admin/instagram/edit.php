<?php
/**
 * @file        app/Views/admin/instagram/edit.php
 * @project     Estrategia Nerd
 * @purpose     Tela de edição de post do Instagram (FEAT-010)
 *
 * Variáveis disponíveis (injetadas pelo InstagramController::edit()):
 * @var string                        $title
 * @var array<string,mixed>           $post         Post a editar
 * @var array<int,array<string,mixed>> $medias       Mídias atuais do post
 * @var array<int,array<string,mixed>> $blog_posts   Posts publicados do blog
 * @var string                        $csrf_token
 * @var string|null                   $error        Mensagem de erro de validação
 * @var array<string,mixed>           $old          Valores anteriores do formulário
 */

declare(strict_types=1);

$post      = $post ?? [];
$medias    = $medias ?? [];
$blogPosts = $blog_posts ?? [];
$csrfToken = $csrf_token ?? '';
$error     = $error ?? null;
$old       = $old ?? [];

$esc = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$postId  = (int) ($post['id'] ?? 0);
$status  = (string) ($post['status'] ?? 'rascunho');

$statusLabels = ['rascunho' => 'Rascunho', 'agendado' => 'Agendado', 'publicando' => 'Publicando', 'publicado' => 'Publicado', 'erro' => 'Erro'];
$statusColors = ['rascunho' => 'border-slate-600 text-slate-300', 'agendado' => 'border-amber-500/40 text-amber-200', 'publicando' => 'border-cyan-500/40 text-cyan-200', 'publicado' => 'border-emerald-500/40 text-emerald-200', 'erro' => 'border-rose-500/40 text-rose-200'];

$isLocked = in_array($status, ['publicado', 'publicando'], true);

$oldTipo     = (string) ($old['tipo'] ?? $post['tipo'] ?? 'imagem');
$oldLegenda  = (string) ($old['legenda'] ?? $post['legenda'] ?? '');
$oldAgendadoRaw = (string) ($old['agendado_para'] ?? $post['agendado_para'] ?? '');
$oldBlogId   = (string) ($old['post_blog_id'] ?? $post['post_blog_id'] ?? '');
?>

<div class="max-w-7xl mx-auto px-4 py-6" data-instagram-form-root>
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title"><i class="fa-brands fa-instagram text-pink-400" aria-hidden="true"></i> Editar Post — Instagram</h1>
      <div class="admin-page-subtitle">
        <span class="admin-chip <?= $esc($statusColors[$status] ?? 'border-slate-600 text-slate-300') ?>"><?= $esc($statusLabels[$status] ?? $status) ?></span>
      </div>
    </div>
    <div class="admin-page-actions">
      <a href="<?= url('/admin/instagram/posts/' . $postId) ?>" class="admin-btn admin-btn-secondary"><i class="fa-solid fa-eye" aria-hidden="true"></i> Ver detalhes</a>
      <a href="<?= url('/admin/instagram') ?>" class="admin-btn admin-btn-secondary"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar</a>
    </div>
  </div>

  <div class="mt-6">
    <?php if ($isLocked): ?>
      <section class="admin-panel border border-amber-500/30">
        <div class="text-sm font-bold text-amber-200">Este post já foi publicado (ou está publicando) no Instagram.</div>
        <div class="text-xs text-slate-400 mt-1">
          Editar a legenda ou a mídia por aqui não altera o post que já está no ar — a API do Instagram não permite editar uma mídia publicada, e reenviar criaria uma publicação duplicada. Por isso a edição fica bloqueada para este post.
        </div>
        <div class="mt-3 text-sm text-slate-300"><span class="font-bold">Legenda atual:</span> <?= nl2br($esc((string) ($post['legenda'] ?? '—'))) ?></div>
        <?php if (!empty($post['permalink'])): ?>
          <a href="<?= $esc((string) $post['permalink']) ?>" target="_blank" rel="noopener noreferrer" class="admin-btn admin-btn-secondary mt-4"><i class="fa-brands fa-instagram" aria-hidden="true"></i> Ver no Instagram</a>
        <?php endif; ?>
      </section>
    <?php else: ?>

      <?php if ($error !== null && $error !== ''): ?>
        <section class="admin-panel border border-rose-500/30 mb-4">
          <div class="text-sm font-bold text-rose-300">Ajustes necessários</div>
          <div class="mt-2 text-sm text-rose-100"><?= $esc($error) ?></div>
        </section>
      <?php endif; ?>

      <?php if ($status === 'erro' && !empty($post['error_log'])): ?>
        <section class="admin-panel border border-rose-500/30 mb-4">
          <div class="text-sm font-bold text-rose-300">Falha na última tentativa de publicação</div>
          <div class="mt-2 text-xs text-rose-200"><?= $esc((string) $post['error_log']) ?></div>
        </section>
      <?php endif; ?>

      <form id="igPostForm" method="POST" action="<?= url('/admin/instagram/posts/' . $postId . '/editar') ?>" enctype="multipart/form-data" class="flex flex-col lg:flex-row items-start gap-6 mt-4" novalidate>
        <input type="hidden" name="_csrf_token" value="<?= $esc($csrfToken) ?>">
        <input type="hidden" name="id" value="<?= $postId ?>">
        <input type="hidden" name="tipo" id="igTipoInput" value="<?= $esc($oldTipo) ?>">
        <input type="hidden" name="post_blog_id" id="igPostBlogId" value="<?= $esc($oldBlogId) ?>">

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
                  <img data-ig-blog-preview-img class="h-14 w-14 rounded-lg object-cover bg-slate-800" alt="" onerror="this.classList.add('hidden'); if(this.nextElementSibling) this.nextElementSibling.classList.remove('hidden');">
                  <div class="hidden h-14 w-14 rounded-lg bg-slate-800 flex items-center justify-center text-slate-500 text-xs">
                    <i class="fa-solid fa-image" aria-hidden="true"></i>
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="text-sm font-bold text-white truncate" data-ig-blog-preview-title></div>
                    <div class="text-xs text-slate-400 truncate" data-ig-blog-preview-resumo></div>
                  </div>
                  <button type="button" class="admin-btn admin-btn-secondary !px-3 !py-1.5 text-xs" data-ig-blog-clear>Remover</button>
                </div>
              </div>
            </section>
          <?php endif; ?>

          <!-- Mídias atuais -->
          <?php if ($medias !== []): ?>
            <section class="admin-panel">
              <div class="admin-panel-title"><i class="fa-solid fa-photo-film text-cyan-300" aria-hidden="true"></i><span>Mídias atuais</span></div>
              <div class="grid grid-cols-3 sm:grid-cols-4 gap-3 mt-4">
                <?php foreach ($medias as $m): ?>
                  <?php
                  $mUrl = trim((string) ($m['url_publica'] ?? ''));
                  if ($mUrl === '') {
                      $caminho = trim((string) ($m['caminho'] ?? ''));
                      $mUrl = $caminho !== '' ? (str_starts_with($caminho, 'http') ? $caminho : url('/' . ltrim($caminho, '/'))) : '';
                  }
                  $isVideo = (string) ($m['tipo_arquivo'] ?? '') === 'video';
                  ?>
                  <div class="relative group aspect-square rounded-lg overflow-hidden bg-slate-800 border border-slate-700 flex items-center justify-center">
                    <?php if ($isVideo): ?>
                      <i class="fa-solid fa-video text-slate-400" aria-hidden="true"></i>
                    <?php elseif ($mUrl !== ''): ?>
                      <img src="<?= $esc($mUrl) ?>" alt="" class="h-full w-full object-cover" onerror="this.classList.add('hidden'); if(this.nextElementSibling) this.nextElementSibling.classList.remove('hidden');">
                      <div class="hidden h-full w-full flex items-center justify-center bg-slate-800 text-slate-500">
                        <i class="fa-solid fa-image text-lg" aria-hidden="true"></i>
                      </div>
                    <?php else: ?>
                      <i class="fa-solid fa-image text-slate-500" aria-hidden="true"></i>
                    <?php endif; ?>
                    <button type="button" class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity h-7 w-7 rounded-md bg-rose-600/90 hover:bg-rose-600 text-white text-xs flex items-center justify-center shadow" title="Remover esta mídia" onclick="if(confirm('Remover esta mídia do post?')) { var f = document.getElementById('igDeleteMediaForm'); f.action = '<?= url('/admin/instagram/media/' . ((int) ($m['id'] ?? 0)) . '/delete') ?>'; f.submit(); }">
                      <i class="fa-solid fa-trash" aria-hidden="true"></i>
                    </button>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="mt-3 text-xs text-slate-400">Enviar novos arquivos abaixo adiciona ao conjunto de mídias deste post. Você pode remover mídias individualmente pela lixeira.</div>
            </section>
          <?php endif; ?>

          <!-- Upload de mídia -->
          <section class="admin-panel">
            <div class="admin-panel-title"><i class="fa-solid fa-upload text-cyan-300" aria-hidden="true"></i><span><?= $medias !== [] ? 'Adicionar mais mídias' : 'Mídia' ?></span></div>
            <label for="igMediaInput" class="ig-dropzone mt-3" data-ig-dropzone tabindex="0" role="button">
              <input id="igMediaInput" name="medias[]" type="file" accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime" multiple class="sr-only" data-ig-media-input>
              <div class="text-center">
                <i class="fa-solid fa-cloud-arrow-up text-2xl text-cyan-300" aria-hidden="true"></i>
                <div class="text-sm font-bold text-slate-200 mt-2">Arraste as novas mídias aqui ou clique para selecionar</div>
                <div class="text-xs text-slate-500 mt-1">Deixe em branco para manter as mídias atuais</div>
              </div>
            </label>
            <div class="grid grid-cols-3 sm:grid-cols-4 gap-3 mt-4" data-ig-media-preview></div>
          </section>

          <!-- Legenda -->
          <section class="admin-panel">
            <div class="flex items-center justify-between">
              <label for="igLegenda" class="admin-filter-label mb-0">Legenda</label>
              <div class="flex items-center gap-3 text-xs">
                <span data-ig-char-count>0</span><span class="text-slate-500">/2.200 caracteres</span>
                <span class="text-slate-600">•</span>
                <span data-ig-hashtag-count>0</span><span class="text-slate-500">/30 hashtags</span>
              </div>
            </div>
            <textarea id="igLegenda" name="legenda" rows="6" maxlength="2200" class="nerd-input admin-filter-control w-full mt-2" placeholder="Escreva a legenda... use #hashtags para engajamento"><?= $esc($oldLegenda) ?></textarea>
          </section>

          <!-- Ações -->
          <section class="admin-panel">
            <label class="admin-filter-label">Quando publicar</label>
            <div class="mt-2" data-ig-agendado-wrap style="<?= $oldAgendadoRaw !== '' ? '' : 'display:none;' ?>">
              <input type="datetime-local" id="igAgendadoPara" value="<?= $esc(str_replace(' ', 'T', substr($oldAgendadoRaw, 0, 16))) ?>" class="nerd-input admin-filter-control w-full sm:w-64">
              <input type="hidden" name="agendado_para" id="igAgendadoParaSubmit" value="">
            </div>

            <div class="flex flex-wrap gap-3 mt-4">
              <button type="submit" name="acao" value="rascunho" class="admin-btn admin-btn-secondary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Salvar como Rascunho</button>
              <button type="submit" name="acao" value="agendar" class="admin-btn admin-btn-secondary" data-ig-acao-agendar><i class="fa-solid fa-clock" aria-hidden="true"></i> Agendar Publicação</button>
              <button type="submit" name="acao" value="publicar" class="admin-btn admin-btn-primary" data-ig-acao-publicar><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Publicar Agora</button>
            </div>
          </section>
        </div>

        <!-- Preview interativo -->
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
                <?php
                $firstMedia = $medias[0] ?? null;
                $firstMediaUrl = '';
                if ($firstMedia !== null && (string) ($firstMedia['tipo_arquivo'] ?? '') !== 'video') {
                    $firstMediaUrl = trim((string) ($firstMedia['url_publica'] ?? ''));
                    if ($firstMediaUrl === '') {
                        $caminho = trim((string) ($firstMedia['caminho'] ?? ''));
                        $firstMediaUrl = $caminho !== '' ? (str_starts_with($caminho, 'http') ? $caminho : url('/' . ltrim($caminho, '/'))) : '';
                    }
                }
                ?>
                <?php if ($firstMediaUrl !== ''): ?>
                  <img src="<?= $esc($firstMediaUrl) ?>" class="h-full w-full object-cover" onerror="this.classList.add('hidden'); if(this.nextElementSibling) this.nextElementSibling.classList.remove('hidden');">
                  <div class="hidden h-full w-full flex items-center justify-center bg-slate-900 text-slate-600">
                    <i class="fa-solid fa-image text-3xl" aria-hidden="true"></i>
                  </div>
                <?php else: ?>
                  <i class="fa-solid fa-image text-3xl text-slate-700" aria-hidden="true"></i>
                <?php endif; ?>
              </div>
              <div class="p-3">
                <div class="flex items-center gap-3 text-slate-300 mb-2">
                  <i class="fa-regular fa-heart" aria-hidden="true"></i>
                  <i class="fa-regular fa-comment" aria-hidden="true"></i>
                  <i class="fa-regular fa-paper-plane" aria-hidden="true"></i>
                </div>
                <div class="text-xs text-slate-200 leading-4"><span class="font-bold">@<?= $esc((string) ($account['username'] ?? 'sua_conta')) ?></span> <span data-ig-preview-caption class="text-slate-400"><?= $esc($oldLegenda !== '' ? $oldLegenda : 'Sua legenda aparece aqui…') ?></span></div>
              </div>
            </div>
          </section>
        </div>
      </form>

      <!-- Formulário oculto seguro para exclusão de mídias (fora do form principal para evitar aninhamento inválido) -->
      <form id="igDeleteMediaForm" method="POST" action="" class="hidden">
        <input type="hidden" name="_csrf_token" value="<?= $esc($csrfToken) ?>">
        <input type="hidden" name="post_id" value="<?= $postId ?>">
      </form>
    <?php endif; ?>
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

<?php if (!$isLocked): ?>
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

  if (acaoAgendarBtn && agendadoWrap) {
    acaoAgendarBtn.addEventListener('click', function () {
      agendadoWrap.style.display = 'block';
      if (agendadoInput) { agendadoInput.focus(); }
    });
  }

  var legenda = document.getElementById('igLegenda');
  var charCount = form.querySelector('[data-ig-char-count]');
  var hashtagCount = form.querySelector('[data-ig-hashtag-count]');
  var previewCaption = form.querySelector('[data-ig-preview-caption]');

  function updateLegendaCounters() {
    var value = legenda.value || '';
    var len = value.length;
    var tags = value.match(/#[^\s#]+/g);

    if (charCount) {
      charCount.textContent = String(len);
      charCount.classList.toggle('text-rose-400', len > 2200);
      charCount.classList.toggle('font-bold', len > 2200);
    }
    if (hashtagCount) {
      var tagCount = tags ? tags.length : 0;
      hashtagCount.textContent = String(tagCount);
      hashtagCount.classList.toggle('text-rose-400', tagCount > 30);
      hashtagCount.classList.toggle('font-bold', tagCount > 30);
    }
    if (previewCaption) {
      previewCaption.textContent = value.trim() !== '' ? value : 'Sua legenda aparece aqui…';
    }
  }
  if (legenda) {
    legenda.addEventListener('input', updateLegendaCounters);
    updateLegendaCounters();
  }

  var dropzone = form.querySelector('[data-ig-dropzone]');
  var mediaInput = document.getElementById('igMediaInput');
  var mediaPreview = form.querySelector('[data-ig-media-preview]');
  var previewMedia = form.querySelector('[data-ig-preview-media]');

  function renderMediaPreview() {
    if (!mediaInput || !mediaPreview) { return; }
    mediaPreview.innerHTML = '';

    var files = Array.prototype.slice.call(mediaInput.files || []);
    if (files.length === 0) { return; }
    if (previewMedia) { previewMedia.innerHTML = '<i class="fa-solid fa-image text-3xl text-slate-700"></i>'; }

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

  var blogSelect = document.getElementById('igBlogSelect');
  var blogPreview = form.querySelector('[data-ig-blog-preview]');
  var blogPreviewImg = form.querySelector('[data-ig-blog-preview-img]');
  var blogPreviewTitle = form.querySelector('[data-ig-blog-preview-title]');
  var blogPreviewResumo = form.querySelector('[data-ig-blog-preview-resumo]');
  var blogClearBtn = form.querySelector('[data-ig-blog-clear]');
  var postBlogIdInput = document.getElementById('igPostBlogId');

  function clearBlogImport() {
    if (postBlogIdInput) { postBlogIdInput.value = ''; }
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

          if (blogPreview) { blogPreview.classList.remove('hidden'); }
          if (blogPreviewImg) { blogPreviewImg.src = resolveMediaUrl(post.capa); }
          if (blogPreviewTitle) { blogPreviewTitle.textContent = post.titulo || ''; }
          if (blogPreviewResumo) { blogPreviewResumo.textContent = post.resumo || ''; }
        })
        .catch(function () { /* silencioso: usuario pode preencher manualmente */ });
    });
  }
  if (blogClearBtn) {
    blogClearBtn.addEventListener('click', clearBlogImport);
  }

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
<?php endif; ?>
