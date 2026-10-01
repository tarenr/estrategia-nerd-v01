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
 * @var string                        $csrf_token
 * @var string|null                   $error        Mensagem de erro de validação
 * @var array<string,mixed>           $old          Valores anteriores do formulário
 */

declare(strict_types=1);

$post      = $post ?? [];
$medias    = $medias ?? [];
$csrfToken = $csrf_token ?? '';
$error     = $error ?? null;
$old       = $old ?? [];

$esc = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$postId  = (int) ($post['id'] ?? 0);
$status  = (string) ($post['status'] ?? 'rascunho');

$statusLabels = ['rascunho' => 'Rascunho', 'agendado' => 'Agendado', 'publicando' => 'Publicando', 'publicado' => 'Publicado', 'erro' => 'Erro'];
$statusColors = ['rascunho' => 'border-slate-600 text-slate-300', 'agendado' => 'border-amber-500/40 text-amber-200', 'publicando' => 'border-cyan-500/40 text-cyan-200', 'publicado' => 'border-emerald-500/40 text-emerald-200', 'erro' => 'border-rose-500/40 text-rose-200'];

$isLocked = in_array($status, ['publicado', 'publicando'], true);

$oldTipo        = (string) ($old['tipo'] ?? $post['tipo'] ?? 'imagem');
$rawLegenda     = (string) ($old['legenda'] ?? $post['legenda'] ?? '');
$oldAgendadoRaw = (string) ($old['agendado_para'] ?? $post['agendado_para'] ?? '');
$oldBlogId      = (string) ($old['post_blog_id'] ?? $post['post_blog_id'] ?? '');
$autoFit        = $old === [] ? true : ((string) ($old['ig_auto_fit'] ?? '') === '1');
$fitFailed      = (string) ($_GET['ajuste'] ?? '') === 'falhou';

if (isset($old['hashtags'])) {
    $oldHashtags    = (string) $old['hashtags'];
    $oldLegendaText = (string) ($old['legenda_texto'] ?? $old['legenda'] ?? '');
} else {
    if (preg_match('/^(.*?)(?:\r?\n\s*)*((?:#[^\s#]+\s*)+)$/s', $rawLegenda, $matches)) {
        $oldLegendaText = trim($matches[1]);
        $oldHashtags    = trim($matches[2]);
    } else {
        $oldLegendaText = $rawLegenda;
        $oldHashtags    = '';
    }
}
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
      <?php if (!$isLocked): ?>
        <button type="button" class="admin-btn !border-rose-500/40 text-rose-300 hover:!bg-rose-500/20" onclick="openIgDeleteModal('<?= url('/admin/instagram/posts/' . $postId . '/delete') ?>', '<?= $esc(addslashes($oldLegendaText !== '' ? substr($oldLegendaText, 0, 35) : ('Post #' . $postId))) ?>')">
          <i class="fa-solid fa-trash mr-1.5" aria-hidden="true"></i> Excluir Post
        </button>
      <?php endif; ?>
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

      <?php if ($fitFailed): ?>
        <section class="admin-panel border border-amber-500/40 mb-4">
          <div class="text-sm font-bold text-amber-200">O post foi salvo, mas o ajuste automático das imagens (Smart Canvas) falhou.</div>
          <div class="mt-1 text-xs text-slate-300">As imagens originais foram mantidas e nada foi publicado. Salve de novo para tentar outra vez, ou desmarque a opção de ajuste.</div>
        </section>
      <?php endif; ?>

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

          <!-- Mídias atuais -->
          <?php if ($medias !== []): ?>
            <section class="admin-panel">
              <div class="admin-panel-title"><i class="fa-solid fa-photo-film text-cyan-300" aria-hidden="true"></i><span>Mídias atuais</span></div>
              <div class="grid grid-cols-3 sm:grid-cols-4 gap-3 mt-4">
                <?php $removedOld = array_map('intval', (array) ($old['remove_media_ids'] ?? [])); ?>
                <?php foreach ($medias as $m): ?>
                  <?php
                  $mId  = (int) ($m['id'] ?? 0);
                  $caminho = trim((string) ($m['caminho'] ?? ''));
                  if ($caminho !== '' && !str_starts_with($caminho, 'http')) {
                      $mUrl = url('/' . ltrim($caminho, '/'));
                  } else {
                      $mUrl = trim((string) ($m['url_publica'] ?? ''));
                      if ($mUrl === '' && $caminho !== '') {
                          $mUrl = $caminho;
                      }
                  }
                  $isVideo   = (string) ($m['tipo_arquivo'] ?? '') === 'video';
                  $isRemoved = in_array($mId, $removedOld, true);
                  ?>
                  <div class="relative group aspect-square rounded-lg overflow-hidden bg-slate-800 border border-slate-700 flex items-center justify-center" data-ig-fit-item="<?= $isRemoved ? 'removido' : ($isVideo ? 'video' : 'imagem') ?>" data-ig-saved-media data-id="<?= $mId ?>" data-kind="<?= $isVideo ? 'video' : 'imagem' ?>" data-url="<?= $esc($mUrl) ?>" data-removed="<?= $isRemoved ? '1' : '0' ?>">
                    <?php if ($isVideo && $mUrl !== ''): ?>
                      <video src="<?= $esc($mUrl) ?>#t=0.1" controls muted playsinline preload="metadata" class="h-full w-full object-cover"></video>
                      <span class="absolute top-1 left-1 rounded bg-black/70 px-1.5 py-0.5 text-[10px] text-white pointer-events-none"><i class="fa-solid fa-video" aria-hidden="true"></i> vídeo</span>
                    <?php elseif ($isVideo): ?>
                      <i class="fa-solid fa-video text-slate-400" aria-hidden="true"></i>
                    <?php elseif ($mUrl !== ''): ?>
                      <img src="<?= $esc($mUrl) ?>" alt="" class="h-full w-full object-cover" onload="window.igRefreshFitBadges && window.igRefreshFitBadges();" onerror="this.classList.add('hidden'); if(this.nextElementSibling) this.nextElementSibling.classList.remove('hidden');">
                      <div class="hidden h-full w-full flex items-center justify-center bg-slate-800 text-slate-500">
                        <i class="fa-solid fa-image text-lg" aria-hidden="true"></i>
                      </div>
                    <?php else: ?>
                      <i class="fa-solid fa-image text-slate-500" aria-hidden="true"></i>
                    <?php endif; ?>
                    <div class="<?= $isRemoved ? '' : 'hidden ' ?>absolute inset-0 z-10 bg-slate-950/80 flex flex-col items-center justify-center gap-1 px-1 text-center" data-ig-removed-overlay>
                      <span class="text-[11px] font-bold text-rose-200">Será removida ao salvar</span>
                      <button type="button" class="text-[11px] font-bold text-cyan-300 underline" data-ig-undo-remove>desfazer</button>
                    </div>
                    <button type="button" class="<?= $isRemoved ? 'hidden ' : '' ?>absolute top-1 right-1 z-10 opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity h-7 w-7 rounded-md bg-rose-600/90 hover:bg-rose-600 text-white text-xs flex items-center justify-center shadow" title="Remover esta mídia" aria-label="Remover esta mídia" data-ig-saved-trash>
                      <i class="fa-solid fa-trash" aria-hidden="true"></i>
                    </button>
                  </div>
                <?php endforeach; ?>
              </div>
              <div data-ig-remove-inputs></div>
              <div class="mt-3 text-xs text-slate-400">Novos arquivos entram depois destas, na ordem de publicação. A lixeira só marca a mídia: ela sai quando você salvar, e o salvamento é recusado se o resultado não servir para o tipo.</div>
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
            <div class="hidden mt-2 text-xs text-amber-200" data-ig-dropzone-lock role="status"></div>
            <div class="hidden mt-2 text-xs font-bold text-rose-300" data-ig-media-notice role="alert"></div>
            <div class="grid grid-cols-3 sm:grid-cols-4 gap-3 mt-4" data-ig-media-preview></div>
            <label class="mt-4 flex items-start gap-2 text-sm text-slate-200 cursor-pointer">
              <input type="checkbox" name="ig_auto_fit" value="1" id="igAutoFit" class="mt-1 accent-cyan-500"<?= $autoFit ? ' checked' : '' ?>>
              <span><strong>Ajustar automaticamente imagens fora do padrão (Smart Canvas)</strong><span class="block text-xs text-slate-400">Vale para todas as mídias do post (atuais e novas). Imagens fora do formato do Instagram viram 4:5, 1:1 ou 9:16 com fundo desfocado, sem cortar nada. Os arquivos originais não são alterados.</span></span>
            </label>
          </section>

          <!-- Trilha Sonora do Reel (FEAT-012) -->
          <?php
          $audioTrack = $audio_track ?? null;
          $audioTrackId = (int) ($old['audio_track_id'] ?? $post['audio_track_id'] ?? 0);
          $audioStartSeconds = (int) ($old['audio_start_seconds'] ?? $post['audio_start_seconds'] ?? 0);
          $audioDurationSeconds = (int) ($old['audio_duration_seconds'] ?? $post['audio_duration_seconds'] ?? 10);
          require __DIR__ . '/partials/audio_section.php';
          ?>

          <!-- Legenda e Hashtags -->
          <section class="admin-panel">
            <div class="flex items-center justify-between">
              <label for="igLegenda" class="admin-filter-label mb-0">Texto da Legenda</label>
              <div class="flex items-center gap-3 text-xs">
                <span data-ig-char-count>0</span><span class="text-slate-500">/2.200 caracteres</span>
              </div>
            </div>
            <textarea id="igLegenda" name="legenda" rows="5" maxlength="2200" class="nerd-input admin-filter-control w-full mt-2" placeholder="Escreva o texto principal do post aqui..."><?= $esc($oldLegendaText) ?></textarea>

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
              <input type="text" id="igHashtags" name="hashtags" value="<?= $esc($oldHashtags) ?>" class="nerd-input admin-filter-control w-full mt-2" placeholder="Ex: #games #rpg #nerd #dicas (digite com ou sem #)">
              <div class="mt-1.5 text-[11px] text-slate-500">As hashtags serão anexadas ao final da publicação automaticamente.</div>
            </div>
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
              <?php
              $firstMedia = $medias[0] ?? null;
              $firstMediaUrl = '';
              $firstMediaIsVideo = false;
              if ($firstMedia !== null) {
                  $firstMediaIsVideo = (string) ($firstMedia['tipo_arquivo'] ?? '') === 'video';
                  $caminho = trim((string) ($firstMedia['caminho'] ?? ''));
                  if ($caminho !== '' && !str_starts_with($caminho, 'http')) {
                      $firstMediaUrl = url('/' . ltrim($caminho, '/'));
                  } else {
                      $firstMediaUrl = trim((string) ($firstMedia['url_publica'] ?? ''));
                      if ($firstMediaUrl === '' && $caminho !== '') {
                          $firstMediaUrl = $caminho;
                      }
                  }
              }
              $isReelsOrStory = in_array((string) ($post['tipo'] ?? ''), ['reels', 'story'], true);
              ?>
              <div class="<?= $isReelsOrStory ? 'relative' : 'aspect-square' ?> bg-slate-900 flex items-center justify-center overflow-hidden" style="<?= $isReelsOrStory ? 'aspect-ratio: 9 / 16;' : '' ?>" data-ig-preview-media>
                <?php if ($firstMediaUrl !== '' && $firstMediaIsVideo): ?>
                  <video src="<?= $esc($firstMediaUrl) ?>" controls autoplay loop muted playsinline class="h-full w-full <?= $isReelsOrStory ? 'object-contain' : 'object-cover' ?>"></video>
                <?php elseif ($firstMediaUrl !== ''): ?>
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
                <div class="text-xs text-slate-200 leading-4"><span class="font-bold">@<?= $esc((string) ($account['username'] ?? 'sua_conta')) ?></span> <span data-ig-preview-caption class="text-slate-400"><?= $esc($oldLegendaText !== '' ? $oldLegendaText : 'Sua legenda aparece aqui…') ?></span></div>
              </div>
            </div>
          </section>
        </div>
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
.ig-tipo-card-disabled, .ig-tipo-card-disabled:hover { opacity:.35; cursor:not-allowed; border-color: rgba(100,116,139,.35); }
.ig-dropzone-locked, .ig-dropzone-locked:hover { opacity:.45; cursor:not-allowed; border-color: rgba(100,116,139,.4); background: transparent; }
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
    refreshFitBadges();
    renderPhonePreview();
  }

  if (tipoSelect) {
    tipoSelect.addEventListener('click', function (event) {
      var card = event.target.closest('[data-ig-tipo]');
      if (!card) { return; }
      if (card.getAttribute('aria-disabled') === 'true') { showNotice(card.title); return; }
      showNotice('');
      applyTipo(card.getAttribute('data-ig-tipo'));
      applyLocks();
    });
  }

  if (acaoAgendarBtn && agendadoWrap) {
    acaoAgendarBtn.addEventListener('click', function () {
      agendadoWrap.style.display = 'block';
      if (agendadoInput) { agendadoInput.focus(); }
    });
  }

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

  // ── Midias: travas de tipo, selecao cumulativa, lixeira e previa ────────────
  // Mesma regra do servidor: imagem = 1 imagem; reels = 1 video; story = 1 item; carrossel = 2 a 10.
  var MAX_MEDIA = 10;
  var SINGLE_TYPES = ['imagem', 'reels', 'story'];
  var ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/quicktime'];
  var dropzone = form.querySelector('[data-ig-dropzone]');
  var dropzoneLock = form.querySelector('[data-ig-dropzone-lock]');
  var mediaNotice = form.querySelector('[data-ig-media-notice]');
  var mediaInput = document.getElementById('igMediaInput');
  var mediaPreview = form.querySelector('[data-ig-media-preview]');
  var previewMedia = form.querySelector('[data-ig-preview-media]');
  var removeInputs = form.querySelector('[data-ig-remove-inputs]');
  var newFiles = [];
  var previewIndex = 0;
  var dropzoneLocked = false;
  var saved = Array.prototype.slice.call(form.querySelectorAll('[data-ig-saved-media]')).map(function (node) {
    return { node: node, id: node.getAttribute('data-id'), kind: node.getAttribute('data-kind'), url: node.getAttribute('data-url'), removed: node.getAttribute('data-removed') === '1' };
  });

  function mk(tag, className, html) {
    var node = document.createElement(tag);
    if (className) { node.className = className; }
    if (html) { node.innerHTML = html; }
    return node;
  }

  // Ordem de publicacao: salvas primeiro, depois as novas.
  function activeItems() {
    var list = [];
    saved.forEach(function (s) { if (!s.removed) { list.push({ kind: s.kind, url: s.url }); } });
    newFiles.forEach(function (n) { list.push({ kind: n.kind, url: n.url }); });
    return list;
  }

  function lockInfo() {
    var items = activeItems();
    var total = items.length;
    var videos = items.filter(function (i) { return i.kind === 'video'; }).length;
    var reasons = {};
    if (total === 1 && videos === 0) { reasons.reels = 'Reels aceita só vídeo. Remova a imagem para escolher Reels.'; }
    if (total === 1 && videos === 1) { reasons.imagem = 'Imagem não aceita vídeo. Use Reels, Story ou Carrossel.'; }
    if (total >= 2) {
      var msg = 'Com ' + total + ' mídias só o Carrossel é possível. Remova até sobrar 1 para trocar o tipo.';
      reasons.imagem = msg; reasons.reels = msg; reasons.story = msg;
    }
    return { total: total, videos: videos, reasons: reasons };
  }

  function showNotice(text) {
    if (!mediaNotice) { return; }
    mediaNotice.textContent = text || '';
    mediaNotice.classList.toggle('hidden', !text);
  }

  function applyLocks() {
    var info = lockInfo();
    var tipo = tipoInput.value || 'imagem';
    var forced = null;
    if (info.total >= 2) { forced = 'carrossel'; } else if (info.reasons[tipo]) { forced = info.videos === 1 ? 'reels' : 'imagem'; }
    if (forced && forced !== tipo) { tipo = forced; applyTipo(tipo); }

    tipoSelect.querySelectorAll('[data-ig-tipo]').forEach(function (card) {
      var reason = info.reasons[card.getAttribute('data-ig-tipo')] || '';
      card.classList.toggle('ig-tipo-card-disabled', reason !== '');
      card.setAttribute('aria-disabled', reason !== '' ? 'true' : 'false');
      card.title = reason;
    });

    var lockMsg = '';
    if (SINGLE_TYPES.indexOf(tipo) !== -1 && info.total >= 1) {
      lockMsg = (tipoLabels[tipo] || tipo) + ' aceita só 1 mídia. Remova a atual para enviar outra, ou escolha Carrossel para adicionar mais.';
    } else if (info.total >= MAX_MEDIA) {
      lockMsg = 'Limite de ' + MAX_MEDIA + ' mídias atingido. Remova alguma para adicionar outra.';
    }
    dropzoneLocked = lockMsg !== '';
    if (dropzone) {
      dropzone.classList.toggle('ig-dropzone-locked', dropzoneLocked);
      dropzone.setAttribute('aria-disabled', dropzoneLocked ? 'true' : 'false');
    }
    if (dropzoneLock) {
      dropzoneLock.textContent = lockMsg;
      dropzoneLock.classList.toggle('hidden', !dropzoneLocked);
    }
  }

  function syncInput() {
    if (!mediaInput || typeof DataTransfer === 'undefined') { return; }
    var dt = new DataTransfer();
    newFiles.forEach(function (n) { dt.items.add(n.file); });
    mediaInput.files = dt.files;
  }

  // Lote inteiro entra ou nada entra: a selecao atual nunca se perde.
  function addFiles(list) {
    var batch = Array.prototype.slice.call(list || []);
    if (batch.length === 0) { return; }
    var info = lockInfo();
    var tipo = tipoInput.value || 'imagem';
    var bad = batch.filter(function (f) { return ACCEPTED_TYPES.indexOf(f.type) === -1; });
    if (bad.length) {
      showNotice('Formato não aceito: ' + bad.map(function (f) { return f.name; }).join(', ') + '. Envie JPG, PNG, WEBP, MP4 ou MOV. Nada deste lote foi adicionado.');
      return;
    }
    if (dropzoneLocked || (SINGLE_TYPES.indexOf(tipo) !== -1 && info.total >= 1)) {
      showNotice((tipoLabels[tipo] || tipo) + ' aceita só 1 mídia. Nada deste lote foi adicionado.');
      return;
    }
    if (info.total + batch.length > MAX_MEDIA) {
      showNotice('Este lote tem ' + batch.length + ' arquivo(s) e o post já tem ' + info.total + ': passaria do limite de ' + MAX_MEDIA + ' mídias. Nada foi adicionado; a seleção atual foi mantida.');
      return;
    }
    batch.forEach(function (f) {
      newFiles.push({ file: f, url: URL.createObjectURL(f), kind: f.type.indexOf('video') === 0 ? 'video' : 'imagem' });
    });
    showNotice('');
    syncInput();
    renderAll();
  }

  function removeNew(idx) {
    var item = newFiles[idx];
    if (!item) { return; }
    URL.revokeObjectURL(item.url);
    newFiles.splice(idx, 1);
    showNotice('');
    syncInput();
    renderAll();
  }

  function trashButton(onClick) {
    var btn = mk('button', 'absolute top-1 right-1 opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity h-7 w-7 rounded-md bg-rose-600/90 hover:bg-rose-600 text-white text-xs flex items-center justify-center shadow', '<i class="fa-solid fa-trash" aria-hidden="true"></i>');
    btn.type = 'button';
    btn.title = 'Remover esta mídia';
    btn.setAttribute('aria-label', 'Remover esta mídia');
    btn.addEventListener('click', onClick);
    return btn;
  }

  function videoThumb(url) {
    var v = document.createElement('video');
    v.src = url;
    v.muted = true;
    v.playsInline = true;
    v.preload = 'metadata';
    v.className = 'h-full w-full object-cover';
    // Mostra o primeiro quadro em vez de tela preta.
    v.addEventListener('loadedmetadata', function () { try { v.currentTime = Math.min(0.1, v.duration || 0.1); } catch (e) { /* sem quadro */ } });
    return v;
  }

  function renderNewThumbs() {
    if (!mediaPreview) { return; }
    mediaPreview.innerHTML = '';
    newFiles.forEach(function (n, idx) {
      var thumb = mk('div', 'relative group aspect-square rounded-lg overflow-hidden bg-slate-800 border border-slate-700 flex items-center justify-center');
      thumb.setAttribute('data-ig-fit-item', n.kind);
      if (n.kind === 'video') {
        thumb.appendChild(videoThumb(n.url));
        thumb.appendChild(mk('span', 'absolute top-1 left-1 rounded bg-black/70 px-1.5 py-0.5 text-[10px] text-white', '<i class="fa-solid fa-video" aria-hidden="true"></i> vídeo'));
      } else {
        var img = document.createElement('img');
        img.src = n.url;
        img.className = 'h-full w-full object-cover';
        img.onload = refreshFitBadges;
        thumb.appendChild(img);
      }
      thumb.appendChild(trashButton(function () { removeNew(idx); }));
      mediaPreview.appendChild(thumb);
    });
  }

  function renderSaved() {
    if (removeInputs) { removeInputs.innerHTML = ''; }
    saved.forEach(function (s) {
      var overlay = s.node.querySelector('[data-ig-removed-overlay]');
      var trash = s.node.querySelector('[data-ig-saved-trash]');
      if (overlay) { overlay.classList.toggle('hidden', !s.removed); }
      if (trash) { trash.classList.toggle('hidden', s.removed); }
      s.node.setAttribute('data-ig-fit-item', s.removed ? 'removido' : s.kind);
      if (s.removed && removeInputs) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'remove_media_ids[]';
        input.value = s.id;
        removeInputs.appendChild(input);
      }
    });
  }

  saved.forEach(function (s) {
    var trash = s.node.querySelector('[data-ig-saved-trash]');
    var undo = s.node.querySelector('[data-ig-undo-remove]');
    if (trash) { trash.addEventListener('click', function () { s.removed = true; showNotice(''); renderAll(); }); }
    if (undo) {
      undo.addEventListener('click', function () {
        if (lockInfo().total + 1 > MAX_MEDIA) { showNotice('Não dá para desfazer: passaria do limite de ' + MAX_MEDIA + ' mídias.'); return; }
        s.removed = false;
        showNotice('');
        renderAll();
      });
    }
  });

  function renderPhonePreview() {
    if (!previewMedia) { return; }
    var items = activeItems();
    var tipo = tipoInput.value || 'imagem';
    var tall = tipo === 'reels' || tipo === 'story';
    previewMedia.classList.remove('aspect-square');
    previewMedia.classList.add('relative');
    previewMedia.style.aspectRatio = tall ? '9 / 16' : '1 / 1';
    previewMedia.innerHTML = '';
    if (items.length === 0) {
      previewMedia.innerHTML = '<i class="fa-solid fa-image text-3xl text-slate-700" aria-hidden="true"></i>';
      return;
    }
    previewIndex = Math.max(0, Math.min(previewIndex, items.length - 1));
    var item = items[previewIndex];
    var fitClass = tall ? 'object-contain' : 'object-cover';
    if (item.kind === 'video') {
      var v = document.createElement('video');
      v.src = item.url;
      v.muted = true;
      v.loop = true;
      v.controls = true;
      v.autoplay = true;
      v.playsInline = true;
      v.className = 'h-full w-full ' + fitClass;
      previewMedia.appendChild(v);
    } else {
      var img = document.createElement('img');
      img.src = item.url;
      img.className = 'h-full w-full ' + fitClass;
      previewMedia.appendChild(img);
    }
    if (items.length > 1) {
      previewMedia.appendChild(mk('span', 'absolute top-2 right-2 rounded-full bg-black/70 px-2 py-0.5 text-[10px] font-bold text-white', String(previewIndex + 1) + '/' + items.length));
      if (previewIndex > 0) {
        var prev = mk('button', 'absolute left-1.5 top-1/2 -translate-y-1/2 h-7 w-7 rounded-full bg-white/80 text-slate-900 text-xs flex items-center justify-center shadow', '<i class="fa-solid fa-chevron-left" aria-hidden="true"></i>');
        prev.type = 'button';
        prev.setAttribute('aria-label', 'Mídia anterior');
        prev.addEventListener('click', function () { previewIndex--; renderPhonePreview(); });
        previewMedia.appendChild(prev);
      }
      if (previewIndex < items.length - 1) {
        var next = mk('button', 'absolute right-1.5 top-1/2 -translate-y-1/2 h-7 w-7 rounded-full bg-white/80 text-slate-900 text-xs flex items-center justify-center shadow', '<i class="fa-solid fa-chevron-right" aria-hidden="true"></i>');
        next.type = 'button';
        next.setAttribute('aria-label', 'Próxima mídia');
        next.addEventListener('click', function () { previewIndex++; renderPhonePreview(); });
        previewMedia.appendChild(next);
      }
      var dots = mk('div', 'absolute left-0 right-0 flex justify-center gap-1 pointer-events-none ' + (item.kind === 'video' ? 'top-2.5' : 'bottom-2'));
      items.forEach(function (_, i) {
        dots.appendChild(mk('span', 'h-1.5 w-1.5 rounded-full ' + (i === previewIndex ? 'bg-cyan-400' : 'bg-white/50')));
      });
      previewMedia.appendChild(dots);
    }
  }

  function renderAll() {
    renderSaved();
    renderNewThumbs();
    applyLocks();
    renderPhonePreview();
    refreshFitBadges();
  }

  if (mediaInput) {
    mediaInput.addEventListener('change', function () {
      var batch = Array.prototype.slice.call(mediaInput.files || []);
      syncInput(); // devolve ao input a selecao anterior; addFiles acrescenta o lote se ele couber
      addFiles(batch);
    });
  }
  if (dropzone && mediaInput) {
    dropzone.addEventListener('click', function (e) { if (dropzoneLocked) { e.preventDefault(); } });
    ['dragover', 'dragenter'].forEach(function (evt) {
      dropzone.addEventListener(evt, function (e) { e.preventDefault(); if (!dropzoneLocked) { dropzone.classList.add('is-dragover'); } });
    });
    ['dragleave', 'drop'].forEach(function (evt) {
      dropzone.addEventListener(evt, function (e) { e.preventDefault(); dropzone.classList.remove('is-dragover'); });
    });
    dropzone.addEventListener('drop', function (e) {
      if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
        addFiles(e.dataTransfer.files);
      }
    });
  }

  // Bloqueia o envio que o servidor recusaria, sem perder os arquivos escolhidos.
  form.addEventListener('submit', function (event) {
    var acao = event.submitter ? event.submitter.value : '';
    var info = lockInfo();
    var msg = '';
    if ((tipoInput.value || 'imagem') === 'carrossel' && info.total === 1) {
      msg = 'Carrossel precisa de pelo menos 2 mídias. Adicione mais uma ou troque o tipo.';
    } else if (info.total === 0 && (acao === 'agendar' || acao === 'publicar')) {
      msg = 'Adicione a mídia do post antes de agendar ou publicar.';
    }
    if (msg !== '') {
      event.preventDefault();
      event.stopImmediatePropagation();
      showNotice(msg);
      if (mediaNotice) { mediaNotice.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
    }
  });

  // Smart Canvas: mesma regra do InstagramMediaFitter para avisar quais imagens serao ajustadas.
  var autoFit = document.getElementById('igAutoFit');

  function fitTargets(tipo, ratios) {
    var result = ratios.map(function () { return null; });
    var images = [];
    ratios.forEach(function (r, i) { if (r) { images.push([i, r]); } });
    if (tipo === 'reels' || images.length === 0) { return result; }
    if (tipo === 'story') {
      images.forEach(function (p) { if (Math.abs(p[1] - 0.5625) > 0.02) { result[p[0]] = '1080x1920'; } });
      return result;
    }
    if (tipo === 'carrossel') {
      var values = images.map(function (p) { return p[1]; });
      var outOfRange = values.some(function (r) { return r < 0.8 || r > 1.91; });
      var mixed = (Math.max.apply(null, values) - Math.min.apply(null, values)) > 0.02;
      if (outOfRange || mixed) {
        images.forEach(function (p) { if (Math.abs(p[1] - 1) > 0.02) { result[p[0]] = '1080x1080'; } });
      }
      return result;
    }
    images.forEach(function (p) {
      if (p[1] < 0.8) { result[p[0]] = '1080x1350'; } else if (p[1] > 1.91) { result[p[0]] = '1080x1080'; }
    });
    return result;
  }

  function refreshFitBadges() {
    var items = Array.prototype.slice.call(document.querySelectorAll('#igPostForm [data-ig-fit-item]'));
    var ratios = items.map(function (item) {
      if (item.getAttribute('data-ig-fit-item') !== 'imagem') { return null; }
      var img = item.querySelector('img');
      return img && img.naturalWidth && img.naturalHeight ? img.naturalWidth / img.naturalHeight : null;
    });
    var enabled = !!(autoFit && autoFit.checked);
    var tipoField = document.getElementById('igTipoInput');
    var targets = fitTargets(tipoField ? (tipoField.value || 'imagem') : 'imagem', ratios);
    items.forEach(function (item, i) {
      var badge = item.querySelector('[data-ig-fit-badge]');
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'absolute bottom-1 left-1 right-1 rounded bg-cyan-400/90 px-1 py-0.5 text-center text-[10px] font-bold text-slate-950';
        badge.setAttribute('data-ig-fit-badge', '');
        item.appendChild(badge);
      }
      var target = enabled ? targets[i] : null;
      badge.textContent = target ? 'Smart Canvas ' + target : '';
      badge.classList.toggle('hidden', !target);
    });
  }

  window.igRefreshFitBadges = refreshFitBadges;
  if (autoFit) { autoFit.addEventListener('change', refreshFitBadges); }


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

  // Estado inicial: tipo vindo do servidor + travas conforme as midias ja salvas.
  applyTipo(tipoInput.value || 'imagem');
  renderAll();

  // Modal Popup de Confirmação de Exclusão de Post
  window.openIgDeleteModal = function (actionUrl, itemTitle) {
    var modal = document.getElementById('igDeleteModal');
    var form = document.getElementById('igDeleteModalForm');
    var titleEl = document.getElementById('igDeleteModalItemTitle');
    if (!modal || !form) return;
    if (modal.parentElement !== document.body) {
      document.body.appendChild(modal);
    }
    form.action = actionUrl;
    if (titleEl) titleEl.textContent = itemTitle ? '«' + itemTitle + '»' : 'este post';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.classList.add('overflow-hidden');
  };

  window.closeIgDeleteModal = function () {
    var modal = document.getElementById('igDeleteModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
  };
})();
</script>

<!-- Modal Popup de Confirmação de Exclusão -->
<div id="igDeleteModal" class="fixed inset-0 z-[10000] bg-black/80 backdrop-blur-sm hidden items-center justify-center p-4">
  <div class="admin-panel border border-rose-500/40 bg-slate-900/95 max-w-md w-full p-6 shadow-2xl rounded-2xl animate-in fade-in duration-200" role="dialog" aria-modal="true">
    <div class="flex items-center gap-4">
      <div class="h-12 w-12 rounded-xl bg-rose-500/20 text-rose-400 flex items-center justify-center text-2xl flex-shrink-0">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
      </div>
      <div>
        <h3 class="text-base font-bold text-white">Confirmar Exclusão</h3>
        <p class="text-xs text-slate-400 mt-0.5">Esta ação não pode ser desfeita.</p>
      </div>
    </div>
    <div class="mt-4 text-sm text-slate-300">
      Tem certeza de que deseja excluir <span id="igDeleteModalItemTitle" class="font-bold text-white">este post</span>? O rascunho ou agendamento e todas as suas mídias serão removidos permanentemente do sistema.
    </div>
    <div class="mt-6 flex items-center justify-end gap-3">
      <button type="button" class="admin-btn admin-btn-secondary" onclick="closeIgDeleteModal()">Cancelar</button>
      <form id="igDeleteModalForm" method="POST" action="">
        <input type="hidden" name="_csrf_token" value="<?= $esc($csrfToken) ?>">
        <button type="submit" class="admin-btn !bg-rose-600 hover:!bg-rose-500 text-white font-bold">
          <i class="fa-solid fa-trash mr-1.5" aria-hidden="true"></i> Sim, Excluir Post
        </button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
