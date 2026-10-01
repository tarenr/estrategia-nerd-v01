<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Views/admin/instagram/partials/audio_section.php
 * @project     Estrategia Nerd
 * @purpose     Seção discreta de trilha sonora (Audius / Local) para Posts/Reels (FEAT-012)
 *              Oculta por padrão até clique do usuário.
 * -----------------------------------------------------------------------------
 *
 * @var array<string,mixed>|null $audioTrack
 * @var int                      $audioTrackId
 * @var int                      $audioStartSeconds
 * @var int                      $audioDurationSeconds
 * @var string                   $csrfToken
 */

declare(strict_types=1);

$track              = $audioTrack ?? null;
$audioTrackId       = (int) ($audioTrackId ?? ($track['id'] ?? 0));
$audioStartSeconds  = (int) ($audioStartSeconds ?? 0);
$audioDurationSeconds = (int) ($audioDurationSeconds ?? 10);
$hasAudio           = $audioTrackId > 0 && !empty($track);

$trackTitle   = (string) ($track['titulo'] ?? '');
$trackArtist  = (string) ($track['artista'] ?? '');
$trackGenre   = (string) ($track['genero'] ?? 'Geral');
$trackDuration = (int) ($track['duracao_s'] ?? 0);
$trackUrl     = (string) ($track['url'] ?? '');
if ($trackUrl === '' && !empty($track['arquivo_path'])) {
    $trackUrl = (string) asset($track['arquivo_path']);
}
?>

<!-- Seção Trilha Sonora (FEAT-012) -->
<div id="igAudioModuleRoot" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="space-y-4">
  <!-- Campos hidden para envio no form -->
  <input type="hidden" name="audio_track_id" id="igAudioTrackId" value="<?= $audioTrackId > 0 ? $audioTrackId : '' ?>">
  <input type="hidden" name="audio_start_seconds" id="igAudioStartSeconds" value="<?= $audioStartSeconds ?>">
  <input type="hidden" name="audio_duration_seconds" id="igAudioDurationSeconds" value="<?= $audioDurationSeconds ?>">

  <!-- Card/Botão de Largura Total (Mesma largura dos outros painéis) -->
  <button type="button" id="btnToggleAudioSection" class="w-full admin-panel hover:border-purple-500/50 transition-all p-4 flex items-center justify-between text-left group cursor-pointer focus:outline-none focus:ring-1 focus:ring-purple-400">
    <div class="flex items-center gap-3.5 min-w-0">
      <div class="w-10 h-10 rounded-xl bg-purple-500/20 text-purple-300 flex items-center justify-center text-base border border-purple-500/30 group-hover:scale-105 group-hover:border-purple-400 transition-all flex-shrink-0">
        <i class="fa-solid fa-music"></i>
      </div>
      <div class="min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
          <span id="btnToggleAudioText" class="text-sm font-bold text-slate-100 group-hover:text-purple-200 transition-colors truncate">
            <?= $hasAudio ? 'Trilha: ' . htmlspecialchars($trackTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'Adicionar Trilha Sonora (Reel com Música)' ?>
          </span>
          <span id="igAudioPillBadge" class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded font-semibold <?= $hasAudio ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' : 'bg-purple-500/20 text-purple-300 border border-purple-500/30' ?>">
            <?= $hasAudio ? 'Ativa' : 'Opcional' ?>
          </span>
        </div>
        <div class="text-xs text-slate-400 mt-0.5 truncate">
          Gera automaticamente um Reel vertical (1080×1920) com transições suaves e fade-out.
        </div>
      </div>
    </div>
    <div class="flex items-center gap-2 text-purple-400 text-xs font-semibold flex-shrink-0 pl-3">
      <span id="lblToggleAudioChevronText"><?= $hasAudio ? 'Configurada' : 'Configurar' ?></span>
      <i id="iconToggleAudioChevron" class="fa-solid fa-chevron-down transition-transform duration-200<?= $hasAudio ? ' rotate-180' : '' ?>"></i>
    </div>
  </button>

  <!-- Painel Expansível da Trilha Sonora (Oculto por padrão) -->
  <section id="igAudioPanel" class="<?= $hasAudio ? '' : 'hidden' ?> admin-panel border border-purple-500/30 bg-slate-900/90 p-4 rounded-xl space-y-4">
    <!-- Cabeçalho do Painel -->
    <div class="flex items-center justify-between border-b border-purple-500/20 pb-3">
      <div class="flex items-center gap-2">
        <span class="admin-chip border-purple-500/40 text-purple-300 font-bold">
          <i class="fa-solid fa-compact-disc mr-1 fa-spin" style="--fa-animation-duration: 4s;" aria-hidden="true"></i> Trilha Sonora
        </span>
        <span class="text-xs text-slate-300">
          Duração do Reel: <strong id="igReelDurationBadge" class="text-cyan-300 font-mono font-bold"><?= $audioDurationSeconds ?>s</strong>
          <span id="igReelCalcExpl" class="text-slate-400 text-[11px] ml-1">(4s por imagem, mín 10s, máx 30s)</span>
        </span>
      </div>
      <button type="button" id="btnRemoveAudioTrack" class="text-xs text-rose-400 hover:text-rose-300 flex items-center gap-1 transition-colors">
        <i class="fa-solid fa-xmark"></i> Remover trilha
      </button>
    </div>

    <!-- Card da Faixa Selecionada + Player com Régua de Corte -->
    <div id="igSelectedTrackCard" class="<?= $hasAudio ? '' : 'hidden' ?> bg-purple-950/20 border border-purple-500/30 rounded-lg p-3 space-y-3">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-purple-900/40 border border-purple-500/30 flex items-center justify-center text-purple-300">
            <i class="fa-solid fa-headphones text-lg"></i>
          </div>
          <div>
            <div id="lblSelectedTitle" class="text-sm font-bold text-slate-100"><?= htmlspecialchars($trackTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div class="text-xs text-slate-400">
              <span id="lblSelectedArtist"><?= htmlspecialchars($trackArtist, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span> •
              <span id="lblSelectedGenre" class="text-purple-300"><?= htmlspecialchars($trackGenre, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span> •
              Duração total: <span id="lblSelectedDuration" class="font-mono text-cyan-300"><?= gmdate('i:s', $trackDuration) ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Player de Áudio Oculto -->
      <audio id="igAudioPlayer" src="<?= htmlspecialchars($trackUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" preload="metadata" class="hidden"></audio>

      <!-- Slider de Seleção do Ponto de Início (Corte) -->
      <div class="bg-slate-900/80 rounded-lg p-3 border border-slate-800 space-y-2">
        <div class="flex items-center justify-between text-xs">
          <span class="text-slate-300 flex items-center gap-1.5">
            <i class="fa-solid fa-scissors text-purple-400"></i> Escolha o início do corte da música:
          </span>
          <div class="font-mono text-xs">
            Início: <strong id="lblAudioStart" class="text-cyan-300"><?= gmdate('i:s', $audioStartSeconds) ?></strong>
            <span class="text-slate-500 mx-1">|</span>
            Fim: <strong id="lblAudioEnd" class="text-purple-300"><?= gmdate('i:s', $audioStartSeconds + $audioDurationSeconds) ?></strong>
          </div>
        </div>

        <input type="range" id="igAudioStartSlider" min="0" max="<?= max(0, $trackDuration - $audioDurationSeconds) ?>" value="<?= $audioStartSeconds ?>" step="1" class="w-full accent-purple-500 h-2 bg-slate-800 rounded-lg cursor-pointer">

        <div class="flex items-center justify-between text-[11px] text-slate-500 font-mono">
          <span>00:00 (Início)</span>
          <span id="lblAudioMaxStart">Máx: <?= gmdate('i:s', max(0, $trackDuration - $audioDurationSeconds)) ?></span>
        </div>

        <div class="pt-2 flex items-center gap-3">
          <button type="button" id="btnPreviewAudioSnippet" class="admin-btn admin-btn-secondary text-xs flex items-center gap-2 border-cyan-500/40 text-cyan-300 hover:bg-cyan-950/40">
            <i id="iconPlaySnippet" class="fa-solid fa-play"></i>
            <span id="lblBtnPlaySnippet">Ouvir trecho do Reel (<?= $audioDurationSeconds ?>s)</span>
          </button>
          <span class="text-[11px] text-slate-400">O vídeo terá fade-out de 1.5s suave no final.</span>
        </div>
      </div>
    </div>

    <!-- Navegação de Abas -->
    <div class="flex border-b border-slate-800 text-xs">
      <button type="button" class="ig-audio-tab py-2 px-4 border-b-2 border-purple-500 text-purple-300 font-bold transition-colors" data-audio-tab="local">
        <i class="fa-solid fa-folder-open mr-1.5"></i> Biblioteca Local
      </button>
      <button type="button" class="ig-audio-tab py-2 px-4 border-b-2 border-transparent text-slate-400 hover:text-slate-200 transition-colors" data-audio-tab="audius">
        <i class="fa-solid fa-cloud-arrow-down mr-1.5"></i> Buscar na Web (Audius)
      </button>
      <button type="button" class="ig-audio-tab py-2 px-4 border-b-2 border-transparent text-slate-400 hover:text-slate-200 transition-colors" data-audio-tab="upload">
        <i class="fa-solid fa-arrow-up-from-bracket mr-1.5"></i> Enviar MP3
      </button>
    </div>

    <!-- Conteúdo da Aba 1: Biblioteca Local -->
    <div id="tabContentLocal" class="space-y-3">
      <div class="flex items-center justify-between">
        <span class="text-xs text-slate-400">Faixas disponíveis no servidor para uso imediato:</span>
        <button type="button" id="btnRefreshLocalTracks" class="text-xs text-cyan-400 hover:text-cyan-300 flex items-center gap-1">
          <i class="fa-solid fa-rotate"></i> Atualizar
        </button>
      </div>
      <div id="igLocalTracksList" class="space-y-2 max-h-60 overflow-y-auto pr-1">
        <div class="text-xs text-slate-500 py-3 text-center">Carregando faixas locais...</div>
      </div>
    </div>

    <!-- Conteúdo da Aba 2: Buscar na Web (Audius) -->
    <div id="tabContentAudius" class="hidden space-y-3">
      <div class="flex gap-2">
        <div class="relative flex-1">
          <input type="text" id="igAudiusSearchQuery" class="nerd-input admin-filter-control w-full text-xs" placeholder="Buscar no Audius (ex.: synthwave, gaming, chiptune, lofi, retro, cyberpunk...)">
        </div>
        <button type="button" id="btnAudiusSearchDo" class="admin-btn admin-btn-secondary text-xs flex items-center gap-1.5 border-purple-500/40 text-purple-300">
          <i class="fa-solid fa-magnifying-glass"></i> Buscar
        </button>
      </div>

      <!-- Chips de busca rápida geek/nerd -->
      <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
        <span class="text-slate-500">Sugestões:</span>
        <?php foreach (['synthwave', 'gaming', 'chiptune', 'lofi', 'cyberpunk', 'epic'] as $chip): ?>
          <button type="button" class="ig-search-chip rounded-full bg-slate-800/80 border border-slate-700 hover:border-purple-400 hover:text-purple-300 px-2.5 py-0.5 text-slate-300 transition-colors" data-chip="<?= $chip ?>">
            <?= $chip ?>
          </button>
        <?php endforeach; ?>
      </div>

      <div id="igAudiusResultsList" class="space-y-2 max-h-60 overflow-y-auto pr-1">
        <div class="text-xs text-slate-500 py-4 text-center">Digite um termo acima ou clique em uma sugestão para buscar músicas gratuitas no Audius.</div>
      </div>
    </div>

    <!-- Conteúdo da Aba 3: Upload Próprio -->
    <div id="tabContentUpload" class="hidden space-y-3">
      <div class="bg-slate-900/60 border border-dashed border-slate-700 rounded-lg p-4 space-y-3">
        <div class="text-xs text-slate-300 font-bold">Faça upload de uma música do seu computador (MP3, M4A ou AAC):</div>
        <input type="file" id="igCustomAudioFileInput" accept="audio/mpeg,audio/mp3,audio/x-m4a,audio/aac" class="block w-full text-xs text-slate-400 file:mr-4 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-purple-600 file:text-white hover:file:bg-purple-500 cursor-pointer">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <input type="text" id="igCustomAudioTitle" placeholder="Título da música (opcional)" class="nerd-input admin-filter-control text-xs">
          <input type="text" id="igCustomAudioArtist" placeholder="Artista / Autor (opcional)" class="nerd-input admin-filter-control text-xs">
        </div>
        <button type="button" id="btnUploadCustomAudio" class="admin-btn admin-btn-secondary text-xs flex items-center gap-1.5 border-cyan-500/40 text-cyan-300">
          <i class="fa-solid fa-cloud-arrow-up"></i> Enviar para a Biblioteca
        </button>
        <div id="igUploadNotice" class="text-xs hidden" role="status"></div>
      </div>
    </div>
  </section>
</div>

<script>
(function () {
  var root = document.getElementById('igAudioModuleRoot');
  if (!root) return;

  var csrfToken = root.getAttribute('data-csrf') || '';
  var trackIdInput = document.getElementById('igAudioTrackId');
  var startSecInput = document.getElementById('igAudioStartSeconds');
  var durSecInput = document.getElementById('igAudioDurationSeconds');

  var btnToggle = document.getElementById('btnToggleAudioSection');
  var btnToggleText = document.getElementById('btnToggleAudioText');
  var chevronIcon = document.getElementById('iconToggleAudioChevron');
  var chevronText = document.getElementById('lblToggleAudioChevronText');
  var pillBadge = document.getElementById('igAudioPillBadge');
  var panel = document.getElementById('igAudioPanel');
  var btnRemove = document.getElementById('btnRemoveAudioTrack');

  function updateToggleUI(isOpen) {
    if (chevronIcon) {
      if (isOpen) {
        chevronIcon.classList.add('rotate-180');
      } else {
        chevronIcon.classList.remove('rotate-180');
      }
    }
    if (chevronText) {
      chevronText.textContent = isOpen ? 'Recolher' : (currentTrack ? 'Configurada' : 'Configurar');
    }
  }

  var selectedCard = document.getElementById('igSelectedTrackCard');
  var lblTitle = document.getElementById('lblSelectedTitle');
  var lblArtist = document.getElementById('lblSelectedArtist');
  var lblGenre = document.getElementById('lblSelectedGenre');
  var lblDuration = document.getElementById('lblSelectedDuration');

  var audioPlayer = document.getElementById('igAudioPlayer');
  var slider = document.getElementById('igAudioStartSlider');
  var lblStart = document.getElementById('lblAudioStart');
  var lblEnd = document.getElementById('lblAudioEnd');
  var lblMaxStart = document.getElementById('lblAudioMaxStart');

  var btnPlaySnippet = document.getElementById('btnPreviewAudioSnippet');
  var iconPlaySnippet = document.getElementById('iconPlaySnippet');
  var lblBtnPlaySnippet = document.getElementById('lblBtnPlaySnippet');

  var reelDurationBadge = document.getElementById('igReelDurationBadge');
  var reelCalcExpl = document.getElementById('igReelCalcExpl');

  var localList = document.getElementById('igLocalTracksList');
  var audiusList = document.getElementById('igAudiusResultsList');
  var audiusInput = document.getElementById('igAudiusSearchQuery');
  var btnAudiusSearch = document.getElementById('btnAudiusSearchDo');

  var currentTrack = null;
  var isPlayingSnippet = false;
  var snippetStopTimeout = null;

  function formatTime(sec) {
    sec = Math.max(0, Math.floor(sec || 0));
    var m = Math.floor(sec / 60);
    var s = sec % 60;
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
  }

  // 1. Cálculo da duração do Reel baseado na quantidade de imagens
  function getMediaCount() {
    var previewContainer = document.querySelector('[data-ig-media-preview]');
    if (!previewContainer) return 1;
    var thumbs = previewContainer.querySelectorAll('[data-ig-fit-item]');
    return Math.max(1, thumbs.length);
  }

  function updateReelDuration() {
    var count = getMediaCount();
    var duration = Math.min(30, Math.max(10, count * 4));
    durSecInput.value = duration;
    if (reelDurationBadge) reelDurationBadge.textContent = duration + 's';
    if (reelCalcExpl) {
      reelCalcExpl.textContent = '(' + count + ' imagem' + (count > 1 ? 's' : '') + ' = ' + duration + 's — mín 10s, máx 30s)';
    }
    if (lblBtnPlaySnippet) {
      lblBtnPlaySnippet.textContent = 'Ouvir trecho do Reel (' + duration + 's)';
    }

    if (currentTrack && currentTrack.duracao_s) {
      updateSliderBounds(currentTrack.duracao_s, duration);
    }
  }

  function updateSliderBounds(totalDuration, reelDuration) {
    var maxStart = Math.max(0, totalDuration - reelDuration);
    slider.max = maxStart;
    if (parseInt(slider.value, 10) > maxStart) {
      slider.value = maxStart;
    }
    startSecInput.value = slider.value;
    lblStart.textContent = formatTime(slider.value);
    lblEnd.textContent = formatTime(parseInt(slider.value, 10) + reelDuration);
    if (lblMaxStart) lblMaxStart.textContent = 'Máx: ' + formatTime(maxStart);
  }

  // Monitorar alterações no preview de mídias para atualizar duração do Reel
  var observerTarget = document.querySelector('[data-ig-media-preview]');
  if (observerTarget && window.MutationObserver) {
    var obs = new MutationObserver(function () {
      updateReelDuration();
    });
    obs.observe(observerTarget, { childList: true, subtree: true });
  }

  // 2. Toggle e Remoção da Trilha
  btnToggle.addEventListener('click', function () {
    var isHidden = panel.classList.contains('hidden');
    if (isHidden) {
      panel.classList.remove('hidden');
      updateToggleUI(true);
      loadLocalTracks();
    } else {
      panel.classList.add('hidden');
      updateToggleUI(false);
    }
  });

  btnRemove.addEventListener('click', function () {
    stopSnippet();
    trackIdInput.value = '';
    startSecInput.value = '0';
    currentTrack = null;
    audioPlayer.src = '';
    selectedCard.classList.add('hidden');
    btnToggleText.textContent = 'Adicionar Trilha Sonora (Reel com Música)';
    if (pillBadge) {
      pillBadge.textContent = 'Opcional';
      pillBadge.className = 'text-[10px] uppercase tracking-wider px-2 py-0.5 rounded font-semibold bg-purple-500/20 text-purple-300 border border-purple-500/30';
    }
    panel.classList.add('hidden');
    updateToggleUI(false);
  });

  // 3. Slider de Corte
  slider.addEventListener('input', function () {
    var start = parseInt(slider.value, 10);
    var duration = parseInt(durSecInput.value, 10) || 10;
    startSecInput.value = start;
    lblStart.textContent = formatTime(start);
    lblEnd.textContent = formatTime(start + duration);

    if (isPlayingSnippet) {
      stopSnippet();
      playSnippet();
    }
  });

  // 4. Pré-escuta do trecho
  function stopSnippet() {
    isPlayingSnippet = false;
    if (snippetStopTimeout) {
      clearTimeout(snippetStopTimeout);
      snippetStopTimeout = null;
    }
    audioPlayer.pause();
    iconPlaySnippet.className = 'fa-solid fa-play';
    lblBtnPlaySnippet.textContent = 'Ouvir trecho do Reel (' + (durSecInput.value || 10) + 's)';
  }

  function playSnippet() {
    if (!audioPlayer.src) return;
    var start = parseInt(slider.value, 10) || 0;
    var duration = parseInt(durSecInput.value, 10) || 10;

    audioPlayer.currentTime = start;
    audioPlayer.play().then(function () {
      isPlayingSnippet = true;
      iconPlaySnippet.className = 'fa-solid fa-pause';
      lblBtnPlaySnippet.textContent = 'Pausar pré-escuta';

      snippetStopTimeout = setTimeout(function () {
        stopSnippet();
      }, duration * 1000);
    }).catch(function (e) {
      console.warn('Falha na reprodução:', e);
      stopSnippet();
    });
  }

  btnPlaySnippet.addEventListener('click', function () {
    if (isPlayingSnippet) {
      stopSnippet();
    } else {
      playSnippet();
    }
  });

  // 5. Selecionar faixa
  function selectTrack(track) {
    stopSnippet();
    currentTrack = track;
    trackIdInput.value = track.id;
    startSecInput.value = 0;
    slider.value = 0;

    lblTitle.textContent = track.titulo;
    lblArtist.textContent = track.artista || 'Artista Audius';
    lblGenre.textContent = track.genero || 'Geral';
    lblSelectedDuration.textContent = formatTime(track.duracao_s);

    audioPlayer.src = track.url;
    selectedCard.classList.remove('hidden');
    btnToggleText.textContent = 'Trilha: ' + track.titulo;
    if (pillBadge) {
      pillBadge.textContent = 'Ativa';
      pillBadge.className = 'text-[10px] uppercase tracking-wider px-2 py-0.5 rounded font-semibold bg-cyan-500/20 text-cyan-300 border border-cyan-500/30';
    }

    updateReelDuration();
    selectedCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  // 6. Abas
  var tabs = root.querySelectorAll('.ig-audio-tab');
  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var tabName = tab.getAttribute('data-audio-tab');
      tabs.forEach(function (t) {
        t.className = 'ig-audio-tab py-2 px-4 border-b-2 border-transparent text-slate-400 hover:text-slate-200 transition-colors';
      });
      tab.className = 'ig-audio-tab py-2 px-4 border-b-2 border-purple-500 text-purple-300 font-bold transition-colors';

      document.getElementById('tabContentLocal').classList.toggle('hidden', tabName !== 'local');
      document.getElementById('tabContentAudius').classList.toggle('hidden', tabName !== 'audius');
      document.getElementById('tabContentUpload').classList.toggle('hidden', tabName !== 'upload');

      if (tabName === 'local') loadLocalTracks();
    });
  });

  // 7. Carregar Faixas Locais
  function loadLocalTracks() {
    localList.innerHTML = '<div class="text-xs text-slate-500 py-3 text-center"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Carregando faixas...</div>';
    fetch('/admin/instagram/api/audio/local')
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.ok || !data.tracks || data.tracks.length === 0) {
          localList.innerHTML = '<div class="text-xs text-slate-500 py-3 text-center">Nenhuma faixa local encontrada. Busque no Audius para adicionar!</div>';
          return;
        }
        renderTrackList(data.tracks, localList, false);
      })
      .catch(function () {
        localList.innerHTML = '<div class="text-xs text-rose-400 py-3 text-center">Não foi possível carregar as faixas locais.</div>';
      });
  }

  document.getElementById('btnRefreshLocalTracks').addEventListener('click', loadLocalTracks);

  // 8. Renderizar Lista de Faixas
  function renderTrackList(tracks, container, isAudius) {
    container.innerHTML = '';
    tracks.forEach(function (t) {
      var item = document.createElement('div');
      item.className = 'flex items-center justify-between p-2.5 rounded-lg bg-slate-800/40 hover:bg-slate-800/80 border border-slate-750 transition-colors text-xs';

      var left = document.createElement('div');
      left.className = 'flex items-center gap-2.5 min-w-0 flex-1 mr-3';

      var previewBtn = document.createElement('button');
      previewBtn.type = 'button';
      previewBtn.className = 'h-8 w-8 rounded-full bg-purple-600/30 text-purple-300 hover:bg-purple-600/60 flex items-center justify-center flex-shrink-0 transition-colors';
      previewBtn.innerHTML = '<i class="fa-solid fa-play text-[10px]"></i>';
      previewBtn.addEventListener('click', function () {
        if (audioPlayer.src === (t.url || t.stream_url) && !audioPlayer.paused) {
          audioPlayer.pause();
          previewBtn.innerHTML = '<i class="fa-solid fa-play text-[10px]"></i>';
        } else {
          audioPlayer.src = t.url || t.stream_url;
          audioPlayer.currentTime = 0;
          audioPlayer.play();
          previewBtn.innerHTML = '<i class="fa-solid fa-pause text-[10px]"></i>';
        }
      });

      var info = document.createElement('div');
      info.className = 'min-w-0';
      info.innerHTML = '<div class="font-bold text-slate-200 truncate">' + escapeHtml(t.title || t.titulo) + '</div>' +
        '<div class="text-[11px] text-slate-400 truncate">' + escapeHtml(t.artist || t.artista) + ' • <span class="text-purple-300">' + escapeHtml(t.genre || t.genero) + '</span> • ' + formatTime(t.duration || t.duracao_s) + '</div>';

      left.appendChild(previewBtn);
      left.appendChild(info);

      var right = document.createElement('div');
      right.className = 'flex-shrink-0';

      var actionBtn = document.createElement('button');
      actionBtn.type = 'button';

      if (isAudius) {
        actionBtn.className = 'admin-btn admin-btn-secondary text-[11px] py-1 px-2.5 border-purple-500/40 text-purple-300 hover:bg-purple-900/40 flex items-center gap-1';
        actionBtn.innerHTML = '<i class="fa-solid fa-download"></i> Baixar e Usar';
        actionBtn.addEventListener('click', function () {
          downloadAndUseAudius(t, actionBtn);
        });
      } else {
        actionBtn.className = 'admin-btn admin-btn-secondary text-[11px] py-1 px-2.5 border-cyan-500/40 text-cyan-300 hover:bg-cyan-900/40 flex items-center gap-1';
        actionBtn.innerHTML = '<i class="fa-solid fa-check"></i> Usar Trilha';
        actionBtn.addEventListener('click', function () {
          selectTrack(t);
        });
      }

      right.appendChild(actionBtn);
      item.appendChild(left);
      item.appendChild(right);
      container.appendChild(item);
    });
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
  }

  // 9. Download do Audius
  function downloadAndUseAudius(track, btn) {
    var originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Baixando...';

    var fd = new FormData();
    fd.append('_csrf_token', csrfToken);
    fd.append('track_id', track.id);
    fd.append('title', track.title || '');
    fd.append('artist', track.artist || '');
    fd.append('genre', track.genre || '');
    fd.append('duration', track.duration || 0);

    fetch('/admin/instagram/api/audio/download', {
      method: 'POST',
      body: fd
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        if (data.ok && data.track) {
          selectTrack(data.track);
        } else {
          alert('Erro ao baixar faixa do Audius: ' + (data.error || 'Tente novamente.'));
        }
      })
      .catch(function (e) {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        alert('Falha na comunicação com o servidor ao baixar a faixa.');
      });
  }

  // 10. Busca no Audius
  function doAudiusSearch(query) {
    var q = (query || audiusInput.value || '').trim();
    if (!q) return;

    audiusList.innerHTML = '<div class="text-xs text-slate-500 py-4 text-center"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Buscando no Audius por "' + escapeHtml(q) + '"...</div>';

    fetch('/admin/instagram/api/audio/search?q=' + encodeURIComponent(q))
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.ok || !data.tracks || data.tracks.length === 0) {
          audiusList.innerHTML = '<div class="text-xs text-slate-500 py-4 text-center">Nenhum resultado encontrado para "' + escapeHtml(q) + '".</div>';
          return;
        }
        renderTrackList(data.tracks, audiusList, true);
      })
      .catch(function () {
        audiusList.innerHTML = '<div class="text-xs text-rose-400 py-4 text-center">Erro ao buscar faixas no Audius. Verifique a conexão.</div>';
      });
  }

  btnAudiusSearch.addEventListener('click', function () { doAudiusSearch(); });
  audiusInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      doAudiusSearch();
    }
  });

  root.querySelectorAll('.ig-search-chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      var val = chip.getAttribute('data-chip');
      audiusInput.value = val;
      doAudiusSearch(val);
    });
  });

  // 11. Upload Customizado
  var fileInput = document.getElementById('igCustomAudioFileInput');
  var titleInput = document.getElementById('igCustomAudioTitle');
  var artistInput = document.getElementById('igCustomAudioArtist');
  var btnUpload = document.getElementById('btnUploadCustomAudio');
  var uploadNotice = document.getElementById('igUploadNotice');

  btnUpload.addEventListener('click', function () {
    if (!fileInput.files || !fileInput.files[0]) {
      uploadNotice.className = 'text-xs text-amber-300';
      uploadNotice.textContent = 'Selecione um arquivo de áudio.';
      uploadNotice.classList.remove('hidden');
      return;
    }

    btnUpload.disabled = true;
    btnUpload.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Enviando...';
    uploadNotice.classList.add('hidden');

    var fd = new FormData();
    fd.append('_csrf_token', csrfToken);
    fd.append('audio_file', fileInput.files[0]);
    fd.append('title', titleInput.value || '');
    fd.append('artist', artistInput.value || '');

    fetch('/admin/instagram/api/audio/upload', {
      method: 'POST',
      body: fd
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        btnUpload.disabled = false;
        btnUpload.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Enviar para a Biblioteca';
        if (data.ok && data.track) {
          uploadNotice.className = 'text-xs text-emerald-300';
          uploadNotice.textContent = 'Faixa enviada e pronta!';
          uploadNotice.classList.remove('hidden');
          selectTrack(data.track);
        } else {
          uploadNotice.className = 'text-xs text-rose-400';
          uploadNotice.textContent = data.error || 'Falha no envio do áudio.';
          uploadNotice.classList.remove('hidden');
        }
      })
      .catch(function () {
        btnUpload.disabled = false;
        btnUpload.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Enviar para a Biblioteca';
        uploadNotice.className = 'text-xs text-rose-400';
        uploadNotice.textContent = 'Erro ao conectar ao servidor.';
        uploadNotice.classList.remove('hidden');
      });
  });

  // Inicialização
  updateReelDuration();
})();
</script>
