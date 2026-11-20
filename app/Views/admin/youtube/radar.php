<?php
/**
 * @file        app/Views/admin/youtube/radar.php
 * @project     Estrategia Nerd
 * @purpose     Interface do Radar de Conteúdo, Tendências e Transcrições do YouTube (FEAT-017)
 *
 * Variáveis disponíveis (injetadas por YouTubeController::radar()):
 * @var string               $title
 * @var string               $query
 * @var string               $type
 * @var array<string,mixed>  $results
 * @var array<int,string>    $suggested
 * @var string|null          $error
 */

declare(strict_types=1);

$query     = (string) ($query ?? '');
$type      = (string) ($type ?? 'all');
$results   = (array) ($results ?? []);
$items     = (array) ($results['items'] ?? []);
$isCached  = (bool) ($results['cached'] ?? false);
$cachedAt  = (string) ($results['cached_at'] ?? '');
$suggested = (array) ($suggested ?? []);
$error     = $error ?? null;

$esc = static fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>

<div id="yt-radar-page" class="w-full px-4 py-6 space-y-6">
  <!-- Top bar / Header -->
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <div class="flex items-center gap-3">
        <a href="<?= url('/admin/youtube') ?>" class="text-zinc-400 hover:text-white transition-colors" title="Voltar ao Studio">
          <i class="fa-solid fa-arrow-left text-lg"></i>
        </a>
        <h1 class="admin-page-title flex items-center gap-3">
          <i class="fa-solid fa-radar text-red-500 animate-pulse" aria-hidden="true"></i>
          <span>Radar de Conteúdo & Transcrições</span>
        </h1>
        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-950/60 text-red-300 border border-red-500/30">
          InnerTube Engine
        </span>
      </div>
      <div class="admin-page-subtitle">
        Pesquise formatos virais, monitore temas em alta no nicho nerd/tech e extraia roteiros completos com timestamps para inspirar produções do Echo.
      </div>
    </div>
    <div class="admin-page-actions flex items-center gap-2">
      <a href="<?= url('/admin/youtube') ?>" class="admin-btn admin-btn-secondary flex items-center gap-2">
        <i class="fa-brands fa-youtube text-red-500"></i>
        <span>YouTube Studio</span>
      </a>
      <a href="<?= url('/admin/youtube/posts/criar') ?>" class="admin-btn admin-btn-primary flex items-center gap-2">
        <i class="fa-solid fa-cloud-arrow-up"></i>
        <span>Novo vídeo</span>
      </a>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="admin-panel border border-rose-500/40 bg-rose-950/20 text-rose-200 text-sm flex items-center gap-3 py-3 px-4 rounded-xl" role="alert">
      <i class="fa-solid fa-triangle-exclamation text-rose-400 text-lg"></i>
      <span><?= $esc($error) ?></span>
    </div>
  <?php endif; ?>

  <!-- Caixa de Pesquisa e Filtros -->
  <div class="admin-panel bg-zinc-900/90 border border-zinc-800 rounded-2xl p-5 shadow-xl space-y-4">
    <form method="get" action="<?= url('/admin/youtube/radar') ?>" class="space-y-4" id="radar-search-form">
      <div class="flex flex-col md:flex-row gap-3 items-stretch md:items-center">
        <!-- Campo de Busca -->
        <div class="relative flex-1">
          <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-500">
            <i class="fa-solid fa-magnifying-glass"></i>
          </div>
          <input
            type="text"
            name="q"
            id="radar-search-input"
            value="<?= $esc($query) ?>"
            placeholder="Ex.: inteligência artificial, setup dev, filme nerd, easter eggs..."
            class="w-full pl-10 pr-4 py-2.5 bg-zinc-950 border border-zinc-700/80 rounded-xl text-white placeholder-zinc-500 focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-colors text-sm"
            autocomplete="off"
            required
          >
        </div>

        <!-- Filtro de Tipo -->
        <div class="flex items-center gap-1 bg-zinc-950 p-1 rounded-xl border border-zinc-800 self-start md:self-auto">
          <label class="cursor-pointer">
            <input type="radio" name="type" value="all" <?= $type === 'all' ? 'checked' : '' ?> class="sr-only peer" onchange="this.form.submit()">
            <span class="px-3 py-1.5 rounded-lg text-xs font-medium text-zinc-400 peer-checked:bg-zinc-800 peer-checked:text-white transition-all flex items-center gap-1.5">
              <i class="fa-solid fa-border-all"></i>
              Todos
            </span>
          </label>
          <label class="cursor-pointer">
            <input type="radio" name="type" value="shorts" <?= $type === 'shorts' ? 'checked' : '' ?> class="sr-only peer" onchange="this.form.submit()">
            <span class="px-3 py-1.5 rounded-lg text-xs font-medium text-zinc-400 peer-checked:bg-red-600 peer-checked:text-white transition-all flex items-center gap-1.5">
              <i class="fa-solid fa-bolt text-amber-300"></i>
              Shorts
            </span>
          </label>
          <label class="cursor-pointer">
            <input type="radio" name="type" value="videos" <?= $type === 'videos' ? 'checked' : '' ?> class="sr-only peer" onchange="this.form.submit()">
            <span class="px-3 py-1.5 rounded-lg text-xs font-medium text-zinc-400 peer-checked:bg-zinc-800 peer-checked:text-white transition-all flex items-center gap-1.5">
              <i class="fa-solid fa-video"></i>
              Vídeos
            </span>
          </label>
        </div>

        <!-- Botão Submeter -->
        <button type="submit" class="admin-btn admin-btn-primary px-5 py-2.5 flex items-center justify-center gap-2">
          <i class="fa-solid fa-radar"></i>
          <span>Varrer Radar</span>
        </button>
      </div>

      <!-- Sugestões Rápidas -->
      <?php if (!empty($suggested)): ?>
        <div class="flex items-center gap-2 flex-wrap pt-1 text-xs">
          <span class="text-zinc-500 font-medium flex items-center gap-1">
            <i class="fa-solid fa-fire text-amber-400"></i> Em alta:
          </span>
          <?php foreach ($suggested as $kw): ?>
            <button
              type="button"
              onclick="document.getElementById('radar-search-input').value = '<?= $esc($kw) ?>'; document.getElementById('radar-search-form').submit();"
              class="px-2.5 py-1 rounded-lg bg-zinc-800/80 hover:bg-zinc-700 text-zinc-300 hover:text-white border border-zinc-700/50 transition-colors"
            >
              <?= $esc($kw) ?>
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </form>
  </div>

  <!-- Resultados da Pesquisa -->
  <?php if (!empty($query)): ?>
    <div class="space-y-4">
      <div class="flex items-center justify-between flex-wrap gap-2 text-sm text-zinc-400 px-1">
        <div class="flex items-center gap-2">
          <span>Encontrados <strong><?= count($items) ?></strong> resultados para "<strong class="text-white"><?= $esc($query) ?></strong>"</span>
          <?php if ($isCached): ?>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs bg-zinc-800 text-zinc-400 border border-zinc-700/60" title="Resultado recuperado do cache local para resposta ultra-rápida">
              <i class="fa-solid fa-clock-rotate-left text-zinc-500"></i> Cache: <?= $esc(substr($cachedAt, 11, 5)) ?>
            </span>
          <?php endif; ?>
        </div>
        <div class="text-xs text-zinc-500">
          InnerTube Engine • Zero Cota da API consumida
        </div>
      </div>

      <?php if (empty($items)): ?>
        <div class="admin-panel bg-zinc-900 border border-zinc-800 rounded-2xl p-12 text-center space-y-3">
          <div class="w-12 h-12 mx-auto rounded-full bg-zinc-800 flex items-center justify-center text-zinc-500 text-xl">
            <i class="fa-solid fa-radar"></i>
          </div>
          <h3 class="text-white font-semibold">Nenhum vídeo retornado pelo Radar</h3>
          <p class="text-zinc-400 text-sm max-w-md mx-auto">
            Tente pesquisar com termos mais amplos ou clique em uma das sugestões em alta no topo.
          </p>
        </div>
      <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          <?php foreach ($items as $item): ?>
            <?php
              $vidId    = (string) ($item['id'] ?? '');
              $vidTitle = (string) ($item['title'] ?? 'Sem título');
              $vidThumb = (string) ($item['thumbnail'] ?? '');
              $vidType  = (string) ($item['type'] ?? 'video');
              $vidDur   = (string) ($item['duration'] ?? '');
              $vidViews = (string) ($item['views'] ?? '');
              $vidChan  = (string) ($item['channel'] ?? '');
              $vidDate  = (string) ($item['published'] ?? '');
              $vidUrl   = (string) ($item['url'] ?? "https://www.youtube.com/watch?v={$vidId}");
              $isShort  = ($vidType === 'shorts');
            ?>
            <div class="group bg-zinc-900/90 border border-zinc-800/80 hover:border-zinc-700 rounded-2xl overflow-hidden flex flex-col justify-between transition-all duration-200 hover:shadow-xl hover:-translate-y-0.5">
              <!-- Thumbnail Wrapper -->
              <div class="relative w-full aspect-video bg-zinc-950 overflow-hidden">
                <?php if (!empty($vidThumb)): ?>
                  <img
                    src="<?= $esc($vidThumb) ?>"
                    alt="<?= $esc($vidTitle) ?>"
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                    loading="lazy"
                  >
                <?php else: ?>
                  <div class="w-full h-full flex items-center justify-center text-zinc-700 text-3xl">
                    <i class="fa-brands fa-youtube"></i>
                  </div>
                <?php endif; ?>

                <!-- Badges sobre o thumbnail -->
                <div class="absolute top-2.5 left-2.5 flex items-center gap-1.5">
                  <?php if ($isShort): ?>
                    <span class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-red-600 text-white shadow-md flex items-center gap-1">
                      <i class="fa-solid fa-bolt"></i> SHORTS
                    </span>
                  <?php endif; ?>
                </div>

                <?php if (!empty($vidDur)): ?>
                  <span class="absolute bottom-2 right-2 px-1.5 py-0.5 rounded bg-black/80 text-white text-[11px] font-mono font-medium backdrop-blur-sm">
                    <?= $esc($vidDur) ?>
                  </span>
                <?php endif; ?>
              </div>

              <!-- Informações -->
              <div class="p-4 flex-1 flex flex-col justify-between space-y-3">
                <div class="space-y-1.5">
                  <h3 class="text-sm font-semibold text-zinc-100 line-clamp-2 leading-snug group-hover:text-red-400 transition-colors" title="<?= $esc($vidTitle) ?>">
                    <?= $esc($vidTitle) ?>
                  </h3>
                  <div class="text-xs text-zinc-400 flex items-center gap-1.5 truncate">
                    <i class="fa-solid fa-user-circle text-zinc-500"></i>
                    <span><?= $esc($vidChan) ?></span>
                  </div>
                </div>

                <div class="space-y-3">
                  <div class="flex items-center justify-between text-xs text-zinc-500 pt-2 border-t border-zinc-800/60">
                    <span><?= $esc($vidViews) ?></span>
                    <span><?= $esc($vidDate) ?></span>
                  </div>

                  <!-- Ações do Card -->
                  <div class="grid grid-cols-2 gap-2">
                    <button
                      type="button"
                      onclick="openTranscriptModal('<?= $esc($vidId) ?>', '<?= $esc(addslashes($vidTitle)) ?>', '<?= $esc($vidChan) ?>', '<?= $esc($vidUrl) ?>')"
                      class="px-2.5 py-2 rounded-xl bg-zinc-800 hover:bg-red-600 text-zinc-200 hover:text-white font-medium text-xs flex items-center justify-center gap-1.5 transition-colors border border-zinc-700/60 hover:border-red-500"
                      title="Extrair roteiro completo do vídeo"
                    >
                      <i class="fa-solid fa-file-lines"></i>
                      <span>Roteiro</span>
                    </button>
                    <a
                      href="<?= $esc($vidUrl) ?>"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="px-2.5 py-2 rounded-xl bg-zinc-950 hover:bg-zinc-800 text-zinc-400 hover:text-white text-xs flex items-center justify-center gap-1.5 transition-colors border border-zinc-800"
                      title="Abrir no YouTube"
                    >
                      <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                      <span>Assistir</span>
                    </a>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <!-- Estado Inicial / Boas-vindas -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4">
      <div class="admin-panel bg-zinc-900/60 border border-zinc-800/80 rounded-2xl p-6 space-y-3">
        <div class="w-10 h-10 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 flex items-center justify-center text-lg">
          <i class="fa-solid fa-bolt"></i>
        </div>
        <h3 class="text-white font-semibold text-base">Radar de Shorts Virais</h3>
        <p class="text-zinc-400 text-sm leading-relaxed">
          Descubra os formatos que estão ganhando tração vertical e engajando milhões de espectadores no algoritmo do YouTube.
        </p>
      </div>

      <div class="admin-panel bg-zinc-900/60 border border-zinc-800/80 rounded-2xl p-6 space-y-3">
        <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center text-lg">
          <i class="fa-solid fa-closed-captioning"></i>
        </div>
        <h3 class="text-white font-semibold text-base">Extração de Roteiros</h3>
        <p class="text-zinc-400 text-sm leading-relaxed">
          Obtenha transcrições completas palavra por palavra com timestamps precisos sem precisar assistir ou transcrever manualmente.
        </p>
      </div>

      <div class="admin-panel bg-zinc-900/60 border border-zinc-800/80 rounded-2xl p-6 space-y-3">
        <div class="w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400 flex items-center justify-center text-lg">
          <i class="fa-solid fa-robot"></i>
        </div>
        <h3 class="text-white font-semibold text-base">Inspirar o Echo</h3>
        <p class="text-zinc-400 text-sm leading-relaxed">
          Copie o texto corrido com 1 clique para usar como referência nos moldes de criação de vídeos da série do robô Echo.
        </p>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Modal de Transcrição / Roteiro -->
<div id="transcript-modal" style="display:none; position:fixed; inset:0; z-index:10000; background:rgba(2,6,23,0.85); backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:20px;">
  <div style="background:#090d16; border:1px solid #1e293b; border-radius:18px; max-width:760px; width:100%; max-height:85vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.85);">
    <!-- Header do Modal -->
    <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/80">
      <div class="flex items-center gap-3 overflow-hidden">
        <div class="w-9 h-9 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 flex items-center justify-center text-base shrink-0">
          <i class="fa-solid fa-file-lines"></i>
        </div>
        <div class="overflow-hidden">
          <h3 id="modal-video-title" class="text-white font-semibold text-sm truncate">Carregando roteiro...</h3>
          <div class="flex items-center gap-2 text-xs text-slate-400">
            <span id="modal-video-channel">Canal</span>
            <span>•</span>
            <span id="modal-transcript-stats" class="text-slate-400 font-mono">-- palavras</span>
          </div>
        </div>
      </div>
      <button
        type="button"
        onclick="closeTranscriptModal()"
        class="text-slate-400 hover:text-white p-2 rounded-lg hover:bg-slate-800 transition-colors"
        title="Fechar (Esc)"
      >
        <i class="fa-solid fa-xmark text-lg"></i>
      </button>
    </div>

    <!-- Barra de Ferramentas / Formato -->
    <div class="px-6 py-2.5 bg-slate-950/40 border-b border-slate-800/80 flex items-center justify-between text-xs flex-wrap gap-2">
      <div class="flex items-center gap-1 bg-slate-900 p-1 rounded-lg border border-slate-800">
        <button
          type="button"
          id="btn-tab-plain"
          onclick="switchTranscriptTab('plain')"
          class="px-3 py-1 rounded text-xs font-medium bg-red-600 text-white transition-all flex items-center gap-1.5"
        >
          <i class="fa-solid fa-align-left"></i>
          Texto Corrido
        </button>
        <button
          type="button"
          id="btn-tab-timestamps"
          onclick="switchTranscriptTab('timestamps')"
          class="px-3 py-1 rounded text-xs font-medium text-slate-400 hover:text-white transition-all flex items-center gap-1.5"
        >
          <i class="fa-solid fa-stopwatch"></i>
          Com Timestamps
        </button>
      </div>

      <div class="flex items-center gap-2">
        <span id="transcript-cache-badge" class="hidden px-2 py-0.5 rounded bg-slate-800 text-slate-400 text-[11px] border border-slate-700/50">
          Cache local ativo
        </span>
        <a id="modal-yt-link" href="#" target="_blank" rel="noopener noreferrer" class="text-slate-400 hover:text-red-400 flex items-center gap-1 text-xs">
          <span>Abrir no YouTube</span>
          <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
        </a>
      </div>
    </div>

    <!-- Corpo do Conteúdo / Scrollable -->
    <div class="p-6 flex-1 overflow-y-auto space-y-4 text-sm leading-relaxed text-slate-200 font-sans" id="modal-content-area" style="max-height: calc(85vh - 140px);">
      <!-- Loading State -->
      <div id="modal-loading" class="py-16 text-center space-y-3">
        <i class="fa-solid fa-circle-notch fa-spin text-3xl text-red-500"></i>
        <p class="text-slate-400 text-sm">Extraindo transcrição com o radar...</p>
      </div>

      <!-- Erro -->
      <div id="modal-error" class="hidden py-12 text-center space-y-3">
        <div class="w-12 h-12 mx-auto rounded-full bg-rose-950/40 border border-rose-500/40 text-rose-400 flex items-center justify-center text-xl">
          <i class="fa-solid fa-circle-exclamation"></i>
        </div>
        <h4 class="text-white font-medium text-base">Transcrição indisponível</h4>
        <p id="modal-error-msg" class="text-slate-400 text-xs max-w-md mx-auto">
          Este vídeo não possui legendas ou transcrições públicas ativadas no YouTube.
        </p>
      </div>

      <!-- Visão 1: Texto Corrido -->
      <div id="view-plain" class="hidden whitespace-pre-wrap select-text selection:bg-red-500/30 selection:text-white font-sans text-slate-200 leading-relaxed text-[13.5px]"></div>

      <!-- Visão 2: Com Timestamps -->
      <div id="view-timestamps" class="hidden space-y-2 font-mono text-xs select-text"></div>
    </div>

    <!-- Rodapé do Modal com Ações de Cópia -->
    <div class="px-6 py-3.5 border-t border-slate-800 bg-slate-950/70 flex items-center justify-between flex-wrap gap-3">
      <div class="text-xs text-slate-500">
        Pronto para colar em prompts do Echo ou roteiros no Forge.
      </div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          id="btn-copy-transcript"
          onclick="copyTranscriptToClipboard()"
          class="admin-btn admin-btn-primary px-4 py-2 text-xs flex items-center gap-2"
        >
          <i class="fa-solid fa-copy"></i>
          <span id="copy-btn-text">Copiar Roteiro</span>
        </button>
        <button
          type="button"
          onclick="closeTranscriptModal()"
          class="admin-btn admin-btn-secondary px-3 py-2 text-xs"
        >
          Fechar
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  let currentTranscriptData = null;
  let activeTab = 'plain';

  function formatTime(seconds) {
    const s = Math.floor(seconds);
    const m = Math.floor(s / 60);
    const sec = s % 60;
    return `${m.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`;
  }

  function openTranscriptModal(videoId, title, channel, ytUrl) {
    const modal = document.getElementById('transcript-modal');
    if (modal && modal.parentElement !== document.body) {
      document.body.appendChild(modal);
    }
    const titleEl = document.getElementById('modal-video-title');
    const channelEl = document.getElementById('modal-video-channel');
    const ytLinkEl = document.getElementById('modal-yt-link');
    const loadingEl = document.getElementById('modal-loading');
    const errorEl = document.getElementById('modal-error');
    const viewPlainEl = document.getElementById('view-plain');
    const viewTimestampsEl = document.getElementById('view-timestamps');
    const statsEl = document.getElementById('modal-transcript-stats');
    const badgeEl = document.getElementById('transcript-cache-badge');

    titleEl.textContent = title;
    channelEl.textContent = channel;
    ytLinkEl.href = ytUrl;

    loadingEl.classList.remove('hidden');
    errorEl.classList.add('hidden');
    viewPlainEl.classList.add('hidden');
    viewTimestampsEl.classList.add('hidden');
    badgeEl.classList.add('hidden');
    statsEl.textContent = 'Carregando...';

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // Disparar requisição assíncrona
    fetch(`<?= url('/admin/youtube/radar/transcript') ?>?video_id=${encodeURIComponent(videoId)}`)
      .then(response => response.json())
      .then(data => {
        loadingEl.classList.add('hidden');
        if (!data.success) {
          errorEl.classList.remove('hidden');
          document.getElementById('modal-error-msg').textContent = data.message || 'Erro ao extrair transcrição.';
          statsEl.textContent = 'Indisponível';
          return;
        }

        currentTranscriptData = data.data;

        if (currentTranscriptData.cached) {
          badgeEl.classList.remove('hidden');
        }

        const wordsCount = currentTranscriptData.words_count || currentTranscriptData.full_text.split(/\s+/).length;
        statsEl.textContent = `${wordsCount} palavras • ${currentTranscriptData.segments.length} trechos`;

        // Renderizar Texto Corrido
        viewPlainEl.textContent = currentTranscriptData.full_text;

        // Renderizar Timestamps
        viewTimestampsEl.innerHTML = '';
        currentTranscriptData.segments.forEach(seg => {
          const row = document.createElement('div');
          row.className = 'flex items-start gap-3 py-1 px-2 rounded hover:bg-zinc-800/50 transition-colors';
          const time = formatTime(seg.start);
          row.innerHTML = `<span class="text-red-400 font-semibold select-none shrink-0">[${time}]</span> <span class="text-zinc-200 select-text">${escapeHtml(seg.text)}</span>`;
          viewTimestampsEl.appendChild(row);
        });

        switchTranscriptTab(activeTab);
      })
      .catch(err => {
        loadingEl.classList.add('hidden');
        errorEl.classList.remove('hidden');
        document.getElementById('modal-error-msg').textContent = 'Erro de comunicação ao carregar a transcrição.';
        statsEl.textContent = 'Erro';
      });
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function closeTranscriptModal() {
    const modal = document.getElementById('transcript-modal');
    modal.style.display = 'none';
    document.body.style.overflow = '';
  }

  function switchTranscriptTab(tab) {
    activeTab = tab;
    const btnPlain = document.getElementById('btn-tab-plain');
    const btnTimestamps = document.getElementById('btn-tab-timestamps');
    const viewPlainEl = document.getElementById('view-plain');
    const viewTimestampsEl = document.getElementById('view-timestamps');

    if (!currentTranscriptData) return;

    if (tab === 'plain') {
      btnPlain.className = 'px-3 py-1 rounded text-xs font-medium bg-red-600 text-white transition-all flex items-center gap-1.5';
      btnTimestamps.className = 'px-3 py-1 rounded text-xs font-medium text-zinc-400 hover:text-white transition-all flex items-center gap-1.5';
      viewPlainEl.classList.remove('hidden');
      viewTimestampsEl.classList.add('hidden');
    } else {
      btnTimestamps.className = 'px-3 py-1 rounded text-xs font-medium bg-red-600 text-white transition-all flex items-center gap-1.5';
      btnPlain.className = 'px-3 py-1 rounded text-xs font-medium text-zinc-400 hover:text-white transition-all flex items-center gap-1.5';
      viewTimestampsEl.classList.remove('hidden');
      viewPlainEl.classList.add('hidden');
    }
  }

  function copyTranscriptToClipboard() {
    if (!currentTranscriptData) return;

    let textToCopy = '';
    if (activeTab === 'plain') {
      textToCopy = currentTranscriptData.full_text;
    } else {
      textToCopy = currentTranscriptData.segments.map(s => `[${formatTime(s.start)}] ${s.text}`).join("\n");
    }

    navigator.clipboard.writeText(textToCopy).then(() => {
      const btnText = document.getElementById('copy-btn-text');
      const original = btnText.textContent;
      btnText.textContent = 'Copiado com Sucesso!';
      setTimeout(() => {
        btnText.textContent = original;
      }, 2000);
    });
  }

  // Fechar com ESC
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeTranscriptModal();
    }
  });

  // Fechar ao clicar fora
  document.getElementById('transcript-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'transcript-modal') {
      closeTranscriptModal();
    }
  });
</script>
