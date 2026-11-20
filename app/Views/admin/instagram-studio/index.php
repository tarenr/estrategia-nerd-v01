<?php
/**
 * @file        app/Views/admin/instagram-studio/index.php
 * @project     Estrategia Nerd
 * @purpose     Dashboard completo do Instagram Studio Pro (instagrapi)
 *
 * @var string               $title
 * @var string               $endpoint
 * @var string               $csrf_token
 * @var array<string, mixed> $connection
 */

declare(strict_types=1);

$esc = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$isAuthenticated = !empty($connection['authenticated']);
$accountName = $connection['username'] ?? 'estrategia_nerd';
$accountPic = !empty($connection['profile_pic_url']) ? $connection['profile_pic_url'] : '/assets/brand/logo-symbol.png';

$highlights_status = $highlights_status ?? [];
$highlights_endpoint = $highlights_endpoint ?? url('/admin/instagram-destaques');
$jobs = (array) ($highlights_status['jobs'] ?? []);
$sessionStored = (bool) ($highlights_status['session_stored'] ?? false);
?>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6" data-instagram-studio-root>
  <!-- Cabeçalho Principal -->
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500 via-pink-500 to-purple-600 flex items-center justify-center text-white text-2xl shadow-lg shadow-pink-500/20">
          <i class="fa-brands fa-instagram" aria-hidden="true"></i>
        </div>
        <div>
          <h1 class="admin-page-title flex items-center gap-3">
            Instagram Studio Pro
            <span class="text-xs px-2.5 py-0.5 rounded-full font-mono uppercase tracking-wider font-semibold <?= $isAuthenticated ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-400 border border-amber-500/30' ?>">
              <i class="fa-solid fa-circle text-[8px] mr-1.5 <?= $isAuthenticated ? 'animate-pulse text-emerald-400' : 'text-amber-400' ?>"></i>
              <?= $isAuthenticated ? 'Conectado (Mobile API)' : 'Sessão Pendente' ?>
            </span>
          </h1>
          <div class="admin-page-subtitle">Segundo dashboard com todas as capacidades do app: fotos/carrosséis com música, stories interativos, bio dinâmica e mineração geek.</div>
        </div>
      </div>
    </div>

    <div class="admin-page-actions flex items-center gap-3">
      <a href="<?= url('/admin/instagram') ?>" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-medium bg-slate-800/80 hover:bg-slate-700/80 text-slate-300 border border-slate-700/60 transition shadow-sm">
        <i class="fa-solid fa-arrow-left text-slate-400"></i> Voltar ao Instagram Oficial
      </a>
      <div class="admin-chip">
        <i class="fa-solid fa-shield-halved text-cyan-400 mr-1.5"></i> Single-Attempt Anti-Ban
      </div>
    </div>
  </div>

  <!-- Navegação por Abas -->
  <div class="flex items-center gap-2 border-b border-slate-800/80 pb-px overflow-x-auto text-sm font-medium">
    <button type="button" data-tab-btn="feed" class="tab-btn active px-4 py-2.5 rounded-t-xl border-b-2 border-pink-500 text-pink-400 bg-slate-900/50 flex items-center gap-2 transition">
      <i class="fa-solid fa-music text-pink-400"></i> Feed com Música
    </button>
    <button type="button" data-tab-btn="stories" class="tab-btn px-4 py-2.5 rounded-t-xl border-b-2 border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-900/30 flex items-center gap-2 transition">
      <i class="fa-solid fa-circle-play text-purple-400"></i> Stories & Destaques
    </button>
    <button type="button" data-tab-btn="profile" class="tab-btn px-4 py-2.5 rounded-t-xl border-b-2 border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-900/30 flex items-center gap-2 transition">
      <i class="fa-solid fa-address-card text-cyan-400"></i> Perfil & Bio
    </button>
    <button type="button" data-tab-btn="direct" class="tab-btn px-4 py-2.5 rounded-t-xl border-b-2 border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-900/30 flex items-center gap-2 transition">
      <i class="fa-solid fa-paper-plane text-emerald-400"></i> Direct (DMs)
    </button>
    <button type="button" data-tab-btn="viral" class="tab-btn px-4 py-2.5 rounded-t-xl border-b-2 border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-900/30 flex items-center gap-2 transition">
      <i class="fa-solid fa-magnifying-glass-chart text-amber-400"></i> Inteligência Geek
    </button>
    <button type="button" data-tab-btn="metrics" class="tab-btn px-4 py-2.5 rounded-t-xl border-b-2 border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-900/30 flex items-center gap-2 transition">
      <i class="fa-solid fa-chart-pie text-blue-400"></i> Métricas & Audiência
    </button>
    <button type="button" data-tab-btn="session" class="tab-btn px-4 py-2.5 rounded-t-xl border-b-2 border-transparent text-slate-400 hover:text-slate-200 hover:bg-slate-900/30 flex items-center gap-2 transition">
      <i class="fa-solid fa-key text-slate-400"></i> Sessão & Segurança
    </button>
  </div>

  <!-- ===================================================================== -->
  <!-- ABA 1: FEED COM MÚSICA (FOTOS E CARROSSÉIS) -->
  <!-- ===================================================================== -->
  <div data-tab-content="feed" class="space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <!-- Formulário de Envio -->
      <div class="lg:col-span-7 space-y-6">
        <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-6 shadow-xl backdrop-blur-sm space-y-5">
          <div class="flex items-center justify-between border-b border-slate-800/80 pb-4">
            <div>
              <h2 class="text-base font-semibold text-slate-100 flex items-center gap-2">
                <i class="fa-solid fa-photo-film text-pink-400"></i> Publicar no Feed com Música Oficial
              </h2>
              <p class="text-xs text-slate-400 mt-0.5">Envie uma foto ou carrossel com trilha sonora direto do catálogo do Instagram.</p>
            </div>
            <!-- Seletor de Tipo -->
            <div class="flex bg-slate-950 p-1 rounded-xl border border-slate-800 text-xs font-medium">
              <button type="button" id="feed-type-photo" class="px-3 py-1.5 rounded-lg bg-pink-500/20 text-pink-300 border border-pink-500/30 transition">Foto Única</button>
              <button type="button" id="feed-type-album" class="px-3 py-1.5 rounded-lg text-slate-400 hover:text-slate-200 transition">Carrossel (2-10)</button>
            </div>
          </div>

          <!-- Upload Drag & Drop -->
          <div id="drop-area" class="border-2 border-dashed border-slate-700/80 hover:border-pink-500/60 rounded-2xl p-6 text-center bg-slate-950/40 hover:bg-pink-500/[0.02] transition cursor-pointer relative group">
            <input type="file" id="feed-file-input" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" accept="image/jpeg,image/png,image/webp">
            <div id="drop-prompt" class="space-y-2">
              <div class="w-12 h-12 rounded-xl bg-slate-800/80 text-pink-400 mx-auto flex items-center justify-center text-xl group-hover:scale-110 transition">
                <i class="fa-solid fa-cloud-arrow-up"></i>
              </div>
              <div class="text-sm font-medium text-slate-200">Arraste a imagem aqui ou <span class="text-pink-400 underline">procure no PC</span></div>
              <div class="text-xs text-slate-500">Suporte a JPEG, PNG e WebP (1080×1350 ou 1080×1080)</div>
            </div>
            <div id="drop-previews" class="hidden flex flex-wrap gap-3 justify-center items-center"></div>
          </div>

          <!-- Legenda -->
          <div class="space-y-2">
            <div class="flex justify-between items-center text-xs text-slate-400">
              <label for="feed-caption" class="font-medium text-slate-300">Legenda do Post</label>
              <span id="char-counter">0 / 2.200 caracteres</span>
            </div>
            <textarea id="feed-caption" rows="4" class="w-full bg-slate-950/80 border border-slate-800 rounded-xl px-4 py-3 text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-pink-500/60 transition" placeholder="Escreva a legenda com ganchos, quebras de linha e hashtags..."></textarea>
          </div>

          <!-- Botão Publicar -->
          <div class="pt-2 flex items-center justify-between">
            <div class="text-xs text-slate-500 flex items-center gap-1.5">
              <i class="fa-solid fa-lock text-slate-600"></i> Upload local sem expor portas ou túnel
            </div>
            <button type="button" id="btn-publish-feed" class="px-5 py-2.5 rounded-xl font-medium text-sm text-white bg-gradient-to-r from-pink-500 to-purple-600 hover:from-pink-600 hover:to-purple-700 transition shadow-lg shadow-pink-500/25 flex items-center gap-2">
              <i class="fa-solid fa-paper-plane"></i> Publicar Agora
            </button>
          </div>
        </div>
      </div>

      <!-- Seletor de Música do Instagram -->
      <div class="lg:col-span-5 space-y-6">
        <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-6 shadow-xl backdrop-blur-sm space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
            <h3 class="text-sm font-semibold text-slate-100 flex items-center gap-2">
              <i class="fa-solid fa-compact-disc text-cyan-400"></i> Trilha Sonora do Instagram
            </h3>
            <span class="text-xs text-cyan-400/80 font-mono">Oficial</span>
          </div>

          <!-- Campo de Busca de Música -->
          <div class="flex gap-2">
            <div class="relative flex-1">
              <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs pointer-events-none"></i>
              <input type="text" id="music-search-query" class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-200 placeholder-slate-600 focus:outline-none focus:border-cyan-500/60 transition" placeholder="Buscar música ou artista...">
            </div>
            <button type="button" id="btn-search-music" class="px-4 py-2 rounded-xl text-xs font-semibold bg-cyan-500/20 text-cyan-300 border border-cyan-500/30 hover:bg-cyan-500/30 transition flex items-center gap-1.5 shadow-sm">
              <i class="fa-solid fa-magnifying-glass"></i> Buscar
            </button>
          </div>

          <!-- Faixa Selecionada Card -->
          <div id="selected-track-card" class="hidden bg-slate-950/80 border border-cyan-500/30 rounded-xl p-3.5 space-y-3">
            <div class="flex items-center gap-3">
              <img id="selected-track-cover" src="" alt="Capa" class="w-12 h-12 rounded-lg object-cover bg-slate-800 border border-slate-700">
              <div class="flex-1 min-w-0">
                <div id="selected-track-title" class="text-xs font-semibold text-slate-100 truncate">Título da Música</div>
                <div id="selected-track-artist" class="text-[11px] text-slate-400 truncate">Artista</div>
                <div class="text-[10px] text-cyan-400 mt-0.5 flex items-center gap-1">
                  <i class="fa-solid fa-circle-check"></i> Trilha vinculada ao post
                </div>
              </div>
              <button type="button" id="btn-remove-track" class="text-slate-500 hover:text-rose-400 text-xs p-1" title="Remover trilha">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </div>

            <!-- Player de Prévia de Áudio -->
            <audio id="audio-preview-player" controls class="w-full h-8 text-xs"></audio>

            <!-- Ajuste de Início do Áudio -->
            <div class="space-y-1.5 pt-1">
              <div class="flex justify-between text-[11px] text-slate-400">
                <span>Início do trecho:</span>
                <span id="start-time-label" class="font-mono text-cyan-300 font-medium">0s</span>
              </div>
              <input type="range" id="audio-start-range" min="0" max="60" value="0" class="w-full accent-cyan-400 cursor-pointer">
              <p class="text-[10px] text-slate-500">Escolha o segundo exato para o áudio começar quando o seguidor abrir o post.</p>
            </div>
          </div>

          <!-- Lista de Resultados de Músicas -->
          <div class="space-y-2">
            <div class="text-xs font-medium text-slate-400 flex items-center justify-between">
              <span>Sugestões & Resultados</span>
              <button type="button" id="btn-load-trending" class="text-[11px] text-cyan-400 hover:underline">Ver em alta</button>
            </div>
            <div id="music-results-list" class="space-y-2 max-h-64 overflow-y-auto pr-1">
              <div class="text-center py-6 text-xs text-slate-600">Busque uma música ou clique em "Ver em alta" para selecionar uma trilha.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ===================================================================== -->
  <!-- ABA 2: STORIES & DESTAQUES (NATIVO STUDIO PRO) -->
  <!-- ===================================================================== -->
  <div data-tab-content="stories" class="hidden space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <!-- Coluna Esquerda: Formulário de Montagem (7 cols) -->
      <div class="lg:col-span-7 space-y-6">
        <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-6 shadow-xl backdrop-blur-sm space-y-5">
          <div class="flex items-center justify-between border-b border-slate-800/80 pb-4">
            <div>
              <h2 class="text-base font-semibold text-slate-100 flex items-center gap-2">
                <i class="fa-solid fa-circle-play text-purple-400"></i> Stories com Link & Montagem de Destaques
              </h2>
              <p class="text-xs text-slate-400 mt-0.5">Publique stories com o sticker oficial de link clicável e agrupe em destaques no perfil @<?= $esc($accountName) ?>.</p>
            </div>
            <!-- Seletor de Modo: Novo Destaque vs Adicionar a Existente -->
            <div class="flex bg-slate-950 p-1 rounded-xl border border-slate-800 text-xs font-medium">
              <button type="button" id="hl-mode-new" class="px-3 py-1.5 rounded-lg bg-purple-500/20 text-purple-300 border border-purple-500/30 transition">Novo Destaque</button>
              <button type="button" id="hl-mode-existing" class="px-3 py-1.5 rounded-lg text-slate-400 hover:text-slate-200 transition">Adicionar a Existente</button>
            </div>
          </div>

          <!-- Seção: Configurações do Destaque -->
          <!-- Modo Novo Destaque -->
          <div id="hl-new-fields" class="space-y-4">
            <div class="space-y-1.5">
              <div class="flex justify-between items-center">
                <label for="hl-title-input" class="text-xs font-medium text-slate-300">Nome do Destaque (Perfil)</label>
                <span id="hl-title-counter" class="text-[11px] text-slate-400">0 / 15 caracteres</span>
              </div>
              <input type="text" id="hl-title-input" maxlength="15" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-purple-500/60 transition" placeholder="Ex.: PC R$400, Gabinetes, Reviews...">
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-300">Capa do Destaque (Opcional)</label>
              <div class="flex items-center gap-3">
                <div class="w-14 h-14 rounded-full border-2 border-dashed border-slate-700 bg-slate-950 flex items-center justify-center overflow-hidden relative group cursor-pointer" id="hl-cover-preview-wrapper">
                  <img id="hl-cover-preview" class="w-full h-full object-cover hidden" alt="Capa">
                  <i id="hl-cover-icon" class="fa-solid fa-camera text-slate-500 group-hover:text-purple-400 transition text-base"></i>
                  <input type="file" id="hl-cover-file-input" accept="image/jpeg,image/png" class="absolute inset-0 opacity-0 cursor-pointer">
                </div>
                <div class="text-xs text-slate-400 space-y-1">
                  <div>JPEG quadrado (320 a 2160 px).</div>
                  <div class="text-[11px] text-slate-500">Se não escolher, o destaque usará automaticamente o 1º story.</div>
                </div>
                <button type="button" id="hl-btn-remove-cover" class="hidden text-xs text-rose-400 hover:text-rose-300 px-2 py-1 rounded bg-rose-500/10 border border-rose-500/20">Remover</button>
              </div>
            </div>
          </div>

          <!-- Modo Adicionar a Existente -->
          <div id="hl-existing-fields" class="hidden space-y-3">
            <label for="hl-existing-select" class="text-xs font-medium text-slate-300">Selecione o Destaque de Destino</label>
            <div class="flex gap-2">
              <select id="hl-existing-select" class="flex-1 bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-purple-500/60 transition">
                <option value="">Clique em "Carregar Destaques" para listar...</option>
              </select>
              <button type="button" id="hl-btn-load-catalog" class="px-4 py-2.5 rounded-xl text-xs font-semibold bg-purple-500/20 text-purple-300 border border-purple-500/30 hover:bg-purple-500/30 transition flex items-center gap-2 whitespace-nowrap">
                <i class="fa-solid fa-arrows-rotate"></i> Carregar Destaques
              </button>
            </div>
            <p class="text-[11px] text-slate-500">Consulta a lista de destaques já publicados na conta @<?= $esc($accountName) ?>.</p>
          </div>

          <!-- Dropzone de Imagens dos Stories (1 a 20) -->
          <div class="space-y-2 pt-2 border-t border-slate-800/80">
            <div class="flex items-center justify-between">
              <label class="text-xs font-medium text-slate-300">Imagens Verticais dos Stories (1 a 20)</label>
              <span id="hl-story-counter" class="text-[11px] text-slate-400">0 / 20 imagens</span>
            </div>

            <div id="hl-drop-area" class="border-2 border-dashed border-slate-700/80 hover:border-purple-500/60 rounded-2xl p-6 text-center bg-slate-950/40 hover:bg-purple-500/[0.02] transition cursor-pointer relative group">
              <input type="file" id="hl-file-input" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" accept="image/jpeg,image/png,image/webp" multiple>
              <div id="hl-drop-prompt" class="space-y-2">
                <div class="w-12 h-12 rounded-xl bg-slate-800/80 text-purple-400 mx-auto flex items-center justify-center text-xl group-hover:scale-110 transition">
                  <i class="fa-solid fa-images"></i>
                </div>
                <div class="text-sm font-medium text-slate-200">Arraste as imagens dos stories aqui ou <span class="text-purple-400 underline">procure no PC</span></div>
                <div class="text-xs text-slate-500">Formato vertical 1080×1920 (JPEG ou PNG) · Até 20 stories por lote</div>
              </div>
            </div>
          </div>

          <!-- Lista de Stories Adicionados (com inputs de link) -->
          <div id="hl-stories-list" class="space-y-3">
            <!-- Renderizado dinamicamente via JS -->
          </div>

          <!-- Botão de Ação -->
          <div class="pt-4 border-t border-slate-800/80 flex items-center justify-between">
            <div class="text-xs text-slate-400" id="hl-publish-hint">
              Adicione pelo menos 1 imagem para publicar.
            </div>
            <button type="button" id="hl-btn-publish" disabled class="px-6 py-2.5 rounded-xl font-medium text-sm text-white bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 disabled:opacity-40 disabled:cursor-not-allowed transition shadow-lg shadow-purple-500/20 flex items-center gap-2">
              <i class="fa-solid fa-paper-plane"></i> Publicar Stories e Montar Destaque
            </button>
          </div>
        </div>

        <!-- Card Colapsável de Histórico de Publicações -->
        <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-5 shadow-xl backdrop-blur-sm">
          <details class="group">
            <summary class="flex items-center justify-between cursor-pointer list-none text-xs font-semibold text-slate-300 hover:text-white transition">
              <span class="flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-slate-400"></i> Histórico de Publicações Anteriores (<?= count($jobs) ?>)
              </span>
              <i class="fa-solid fa-chevron-down text-slate-500 group-open:rotate-180 transition"></i>
            </summary>
            <div class="mt-4 space-y-2 border-t border-slate-800/80 pt-3">
              <?php if (!$jobs): ?>
                <div class="text-center py-4 text-xs text-slate-500">Nenhuma publicação registrada anteriormente.</div>
              <?php else: ?>
                <?php foreach ($jobs as $job): 
                  $jId = (string) ($job['id'] ?? ''); 
                  $jStatus = (string) ($job['status'] ?? 'ready'); 
                  $labels = ['ready' => 'Pronta para continuar', 'completed' => 'Concluída', 'unknown' => 'Precisa conferir no Instagram', 'running' => 'Em andamento', 'failed' => 'Interrompida'];
                  $statusColors = ['completed' => 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20', 'unknown' => 'text-amber-400 bg-amber-500/10 border-amber-500/20', 'failed' => 'text-rose-400 bg-rose-500/10 border-rose-500/20', 'ready' => 'text-cyan-400 bg-cyan-500/10 border-cyan-500/20', 'running' => 'text-purple-400 bg-purple-500/10 border-purple-500/20'];
                ?>
                  <div class="flex items-center justify-between p-3 rounded-xl bg-slate-950/60 border border-slate-800 text-xs">
                    <div>
                      <div class="font-medium text-slate-200"><?= $esc((string) (($job['highlight']['title'] ?? '') ?: 'Destaque existente')) ?></div>
                      <div class="text-[11px] text-slate-400 mt-0.5"><?= (int) ($job['completed_steps'] ?? 0) ?> de <?= (int) ($job['total_steps'] ?? 0) ?> etapas concluídas</div>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-mono border <?= $statusColors[$jStatus] ?? 'text-slate-400 bg-slate-800' ?>"><?= $esc($labels[$jStatus] ?? $jStatus) ?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </details>
        </div>
      </div>

      <!-- Coluna Direita: Mockup do Smartphone Realista (5 cols) -->
      <div class="lg:col-span-5 flex flex-col items-center">
        <div class="w-full max-w-[320px] bg-slate-950 border-4 border-slate-800 rounded-[42px] p-3.5 shadow-2xl space-y-3 relative overflow-hidden">
          <!-- Notch / Sensor do Smartphone -->
          <div class="w-24 h-4 bg-slate-800 rounded-full mx-auto"></div>

          <!-- Tela do Story (9:16) -->
          <div class="relative w-full aspect-[9/16] bg-slate-900 rounded-[28px] overflow-hidden border border-slate-800/80 flex flex-col justify-between p-3 select-none">
            <!-- Imagem de Fundo do Story -->
            <img id="hl-phone-image" class="absolute inset-0 w-full h-full object-cover hidden" alt="Prévia Story">
            <div id="hl-phone-placeholder" class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center text-slate-500 space-y-2">
              <i class="fa-solid fa-mobile-screen-button text-3xl text-slate-700"></i>
              <div class="text-xs font-medium text-slate-400">Prévia do Story em Tempo Real</div>
              <div class="text-[10px] text-slate-600">Adicione uma imagem vertical para visualizar a tela e o sticker de link</div>
            </div>

            <!-- Topo: Barras de Progresso dos Stories -->
            <div class="relative z-10 space-y-2">
              <div id="hl-phone-bars" class="flex gap-1 w-full">
                <div class="h-0.5 flex-1 bg-white/70 rounded-full"></div>
              </div>
              <!-- Header do Story (Avatar + Nome + Tempo) -->
              <div class="flex items-center justify-between text-white drop-shadow">
                <div class="flex items-center gap-2">
                  <img src="<?= $esc($accountPic) ?>" class="w-7 h-7 rounded-full object-cover border border-white/40">
                  <span class="text-xs font-semibold tracking-tight text-white"><?= $esc($accountName) ?></span>
                  <span class="text-[10px] text-white/70">agora</span>
                </div>
                <i class="fa-solid fa-ellipsis text-xs text-white/80"></i>
              </div>
            </div>

            <!-- Centro/Base: Sticker Oficial de Link Clicável -->
            <div class="relative z-10 flex justify-center mb-6">
              <div id="hl-phone-sticker" class="hidden items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-white/95 text-slate-900 shadow-xl backdrop-blur-md text-xs font-bold transition transform hover:scale-105 border border-white">
                <i class="fa-solid fa-link text-purple-600 text-[11px]"></i>
                <span id="hl-phone-sticker-text" class="truncate max-w-[170px] uppercase tracking-wide text-[11px]">LINK</span>
                <i class="fa-solid fa-arrow-up-right-from-square text-[9px] text-slate-500 ml-0.5"></i>
              </div>
            </div>
          </div>

          <!-- Controles de Navegação da Prévia -->
          <div class="flex items-center justify-between px-2 pt-1">
            <button type="button" id="hl-prev-story" disabled class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-800 text-xs text-slate-300 disabled:opacity-30 disabled:cursor-not-allowed hover:bg-slate-800 transition flex items-center gap-1">
              <i class="fa-solid fa-chevron-left text-[10px]"></i> Anterior
            </button>
            <span id="hl-preview-index" class="text-xs font-mono text-slate-400">0 / 0</span>
            <button type="button" id="hl-next-story" disabled class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-800 text-xs text-slate-300 disabled:opacity-30 disabled:cursor-not-allowed hover:bg-slate-800 transition flex items-center gap-1">
              Próximo <i class="fa-solid fa-chevron-right text-[10px]"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ===================================================================== -->
  <!-- ABA 3: PERFIL & BIO -->
  <!-- ===================================================================== -->
  <div data-tab-content="profile" class="hidden space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <!-- Formulário de Edição de Perfil -->
      <div class="lg:col-span-7 space-y-6">
        <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-6 shadow-xl backdrop-blur-sm space-y-5">
          <div class="border-b border-slate-800/80 pb-3">
            <h2 class="text-base font-semibold text-slate-100 flex items-center gap-2">
              <i class="fa-solid fa-address-card text-cyan-400"></i> Gerenciador de Bio e Link do Perfil
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">Altere a biografia e o link da bio dinamicamente quando publicar artigos novos.</p>
          </div>

          <div class="space-y-4">
            <div class="space-y-1.5">
              <label for="bio-text-input" class="text-xs font-medium text-slate-300">Texto da Biografia</label>
              <textarea id="bio-text-input" rows="4" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-cyan-500/60 transition" placeholder="Escreva a bio do perfil..."></textarea>
            </div>

            <div class="space-y-1.5">
              <label for="bio-url-input" class="text-xs font-medium text-slate-300">Link Externo na Bio (Site / Artigo Destaque)</label>
              <div class="relative">
                <i class="fa-solid fa-link absolute left-3.5 top-3 text-slate-500 text-xs"></i>
                <input type="url" id="bio-url-input" class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-9 pr-4 py-2 text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-cyan-500/60 transition" placeholder="https://nerd.tfr-info.com.br/artigo-exemplo">
              </div>
            </div>

            <div class="pt-2 flex justify-end">
              <button type="button" id="btn-save-profile" class="px-5 py-2.5 rounded-xl font-medium text-sm text-slate-900 bg-cyan-400 hover:bg-cyan-300 transition shadow-lg shadow-cyan-400/20 flex items-center gap-2">
                <i class="fa-solid fa-floppy-disk"></i> Salvar Alterações na Bio
              </button>
            </div>
          </div>
        </div>

        <!-- Fixar Mídias no Topo (Pin / Unpin) -->
        <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-6 shadow-xl backdrop-blur-sm space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
            <h3 class="text-sm font-semibold text-slate-100 flex items-center gap-2">
              <i class="fa-solid fa-thumbtack text-amber-400"></i> Fixar Posts no Topo da Grade (Pin to Profile)
            </h3>
            <button type="button" id="btn-refresh-profile-posts" class="text-xs text-slate-400 hover:text-slate-200 flex items-center gap-1">
              <i class="fa-solid fa-arrows-rotate"></i> Atualizar posts
            </button>
          </div>
          <div id="profile-posts-grid" class="grid grid-cols-3 gap-3">
            <div class="text-center py-8 col-span-3 text-xs text-slate-500">Carregando mídias recentes...</div>
          </div>
        </div>
      </div>

      <!-- Smartphone Mockup Preview -->
      <div class="lg:col-span-5 flex justify-center">
        <div class="w-full max-w-[340px] bg-slate-950 border-4 border-slate-800 rounded-[38px] p-4 shadow-2xl space-y-4 relative">
          <!-- Notch / Câmera -->
          <div class="w-24 h-4 bg-slate-800 rounded-full mx-auto"></div>

          <!-- Header do Perfil no Celular -->
          <div class="flex items-center justify-between px-2 pt-1 text-slate-200 text-xs font-semibold">
            <span class="flex items-center gap-1">@<?= $esc($accountName) ?> <i class="fa-solid fa-chevron-down text-[9px] text-slate-400"></i></span>
            <i class="fa-solid fa-bars text-slate-400"></i>
          </div>

          <!-- Avatar e Stats -->
          <div class="flex items-center gap-4 px-2 pt-2">
            <div class="w-16 h-16 rounded-full p-0.5 bg-gradient-to-tr from-amber-500 via-pink-500 to-purple-600">
              <img id="preview-phone-avatar" src="<?= $esc($accountPic) ?>" alt="Avatar" class="w-full h-full rounded-full object-cover bg-slate-900">
            </div>
            <div class="flex-1 flex justify-around text-center">
              <div>
                <div id="preview-stat-posts" class="text-xs font-bold text-slate-100">--</div>
                <div class="text-[10px] text-slate-400">posts</div>
              </div>
              <div>
                <div id="preview-stat-followers" class="text-xs font-bold text-slate-100">--</div>
                <div class="text-[10px] text-slate-400">seguidores</div>
              </div>
              <div>
                <div id="preview-stat-following" class="text-xs font-bold text-slate-100">--</div>
                <div class="text-[10px] text-slate-400">seguindo</div>
              </div>
            </div>
          </div>

          <!-- Bio Preview -->
          <div class="px-2 space-y-1 text-xs">
            <div class="font-semibold text-slate-100">Estratégia Nerd</div>
            <div id="preview-phone-bio" class="text-slate-300 text-[11px] whitespace-pre-line">A cultura nerd, games e hardware explicados sem enrolação.</div>
            <div class="pt-0.5 flex items-center gap-1 text-cyan-400 text-[11px] truncate">
              <i class="fa-solid fa-link text-[9px]"></i>
              <span id="preview-phone-url">nerd.tfr-info.com.br</span>
            </div>
          </div>

          <!-- Grade Visual Mockup -->
          <div class="border-t border-slate-800/80 pt-3 grid grid-cols-3 gap-1 px-1">
            <div class="aspect-square bg-slate-900 rounded-md border border-slate-800 flex items-center justify-center text-slate-700 text-xs"><i class="fa-solid fa-gamepad"></i></div>
            <div class="aspect-square bg-slate-900 rounded-md border border-slate-800 flex items-center justify-center text-slate-700 text-xs"><i class="fa-solid fa-microchip"></i></div>
            <div class="aspect-square bg-slate-900 rounded-md border border-slate-800 flex items-center justify-center text-slate-700 text-xs"><i class="fa-solid fa-robot"></i></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ===================================================================== -->
  <!-- ABA 4: DIRECT (DMs) -->
  <!-- ===================================================================== -->
  <div data-tab-content="direct" class="hidden space-y-6">
    <div class="max-w-2xl mx-auto bg-slate-900/60 border border-slate-800 rounded-2xl p-6 shadow-xl backdrop-blur-sm space-y-5">
      <div class="border-b border-slate-800/80 pb-3">
        <h2 class="text-base font-semibold text-slate-100 flex items-center gap-2">
          <i class="fa-solid fa-paper-plane text-emerald-400"></i> Envio Direto de Mensagem (DM)
        </h2>
        <p class="text-xs text-slate-400 mt-0.5">Envie links de artigos e respostas personalizadas para seguidores de forma individual e segura.</p>
      </div>

      <div class="space-y-4">
        <div class="space-y-1.5">
          <label for="dm-username" class="text-xs font-medium text-slate-300">Usuário do Instagram (@)</label>
          <div class="relative">
            <span class="absolute left-3.5 top-2.5 text-slate-500 text-xs font-mono">@</span>
            <input type="text" id="dm-username" class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-8 pr-4 py-2 text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-emerald-500/60 transition" placeholder="nome_do_usuario">
          </div>
        </div>

        <div class="space-y-1.5">
          <div class="flex items-center justify-between">
            <label for="dm-text" class="text-xs font-medium text-slate-300">Mensagem</label>
            <!-- Templates Rápidos -->
            <div class="flex gap-1.5">
              <button type="button" class="dm-tpl-btn text-[10px] bg-slate-800 hover:bg-slate-700 text-slate-300 px-2 py-0.5 rounded border border-slate-700" data-tpl="Olá! Aqui está o link do artigo completo que você pediu no Estratégia Nerd: https://nerd.tfr-info.com.br">Artigo Blog</button>
              <button type="button" class="dm-tpl-btn text-[10px] bg-slate-800 hover:bg-slate-700 text-slate-300 px-2 py-0.5 rounded border border-slate-700" data-tpl="Fala guerreiro! Esse é o modelo exato da peça recomendada no nosso setup: https://nerd.tfr-info.com.br/links">Link Peça</button>
            </div>
          </div>
          <textarea id="dm-text" rows="4" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-emerald-500/60 transition" placeholder="Digite a mensagem que será enviada pelo @estrategia_nerd..."></textarea>
        </div>

        <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl text-xs text-emerald-300 flex items-start gap-2">
          <i class="fa-solid fa-shield-heart text-emerald-400 mt-0.5"></i>
          <div>
            <strong>Regra Anti-Spam:</strong> Envie DMs apenas em resposta a pedidos reais de seguidores (ex: quem comentou no Reel). Nunca envie mensagens não solicitadas em massa.
          </div>
        </div>

        <div class="pt-2 flex justify-end">
          <button type="button" id="btn-send-dm" class="px-5 py-2.5 rounded-xl font-medium text-sm text-white bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 transition shadow-lg shadow-emerald-500/20 flex items-center gap-2">
            <i class="fa-solid fa-paper-plane"></i> Enviar Mensagem Direta
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ===================================================================== -->
  <!-- ABA 5: INTELIGÊNCIA GEEK & MINERAÇÃO -->
  <!-- ===================================================================== -->
  <div data-tab-content="viral" class="hidden space-y-6">
    <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-6 shadow-xl backdrop-blur-sm space-y-5">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800/80 pb-4">
        <div>
          <h2 class="text-base font-semibold text-slate-100 flex items-center gap-2">
            <i class="fa-solid fa-magnifying-glass-chart text-amber-400"></i> Mineração de Posts Virais (Benchmarking)
          </h2>
          <p class="text-xs text-slate-400 mt-0.5">Pesquise perfis de referência ou hashtags do nicho para analisar os posts de maior sucesso.</p>
        </div>

        <!-- Barra de Busca -->
        <div class="flex items-center gap-2 max-w-md w-full">
          <select id="viral-type" class="bg-slate-950 border border-slate-800 text-xs text-slate-200 rounded-xl px-3 py-2 focus:outline-none">
            <option value="user">@ Usuário</option>
            <option value="hashtag"># Hashtag</option>
          </select>
          <input type="text" id="viral-target-input" class="flex-1 bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs text-slate-100 placeholder-slate-600 focus:outline-none focus:border-amber-500/60 transition" placeholder="Ex: adrenaline, pcgamer, setup...">
          <button type="button" id="btn-search-viral" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-900 bg-amber-400 hover:bg-amber-300 transition flex items-center gap-1.5">
            <i class="fa-solid fa-bolt"></i> Rastrear
          </button>
        </div>
      </div>

      <!-- Grid de Posts Minerados -->
      <div id="viral-results-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
        <div class="col-span-full text-center py-12 text-xs text-slate-500">
          Digite um perfil geek ou hashtag acima para descobrir os posts com maior volume de curtidas e engajamento.
        </div>
      </div>
    </div>
  </div>

  <!-- ===================================================================== -->
  <!-- ABA 6: MÉTRICAS & AUDIÊNCIA -->
  <!-- ===================================================================== -->
  <div data-tab-content="metrics" class="hidden space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-900/60 border border-slate-800 rounded-2xl p-6 shadow-xl backdrop-blur-sm">
      <div>
        <h2 class="text-base font-semibold text-slate-100 flex items-center gap-2">
          <i class="fa-solid fa-chart-pie text-blue-400"></i> Métricas & Audiência em Tempo Real
        </h2>
        <p class="text-xs text-slate-400 mt-0.5">Telemetria direta da conta @<?= $esc($accountName) ?> via Mobile API: engajamento, ranking e espectadores de stories.</p>
      </div>
      <button type="button" id="btn-refresh-metrics" class="px-4 py-2 rounded-xl text-xs font-semibold bg-blue-500/20 text-blue-300 border border-blue-500/30 hover:bg-blue-500/30 transition flex items-center gap-2 shadow-sm">
        <i class="fa-solid fa-arrows-rotate"></i> Atualizar Dados ao Vivo
      </button>
    </div>

    <!-- 4 Cards de KPI no Padrão do Dashboard -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-5 shadow-lg relative overflow-hidden group">
        <div class="flex items-center justify-between text-slate-400 text-xs">
          <span>Seguidores Totais</span>
          <i class="fa-solid fa-users text-pink-400 text-base"></i>
        </div>
        <div class="mt-3 text-2xl font-bold text-slate-100" id="metric-followers">--</div>
        <div class="mt-1 text-[11px] text-slate-500 flex items-center gap-1">
          <i class="fa-solid fa-circle-check text-emerald-400"></i> Comunidade no Instagram
        </div>
      </div>

      <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-5 shadow-lg relative overflow-hidden group">
        <div class="flex items-center justify-between text-slate-400 text-xs">
          <span>Taxa de Engajamento</span>
          <i class="fa-solid fa-fire text-amber-400 text-base"></i>
        </div>
        <div class="mt-3 text-2xl font-bold text-amber-400" id="metric-engagement">--%</div>
        <div class="mt-1 text-[11px] text-slate-500 flex items-center gap-1">
          <i class="fa-solid fa-arrow-trend-up text-cyan-400"></i> Likes + Comentários / Seg.
        </div>
      </div>

      <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-5 shadow-lg relative overflow-hidden group">
        <div class="flex items-center justify-between text-slate-400 text-xs">
          <span>Média de Curtidas</span>
          <i class="fa-solid fa-heart text-rose-400 text-base"></i>
        </div>
        <div class="mt-3 text-2xl font-bold text-slate-100" id="metric-avg-likes">--</div>
        <div class="mt-1 text-[11px] text-slate-500">Por publicação recente</div>
      </div>

      <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-5 shadow-lg relative overflow-hidden group">
        <div class="flex items-center justify-between text-slate-400 text-xs">
          <span>Média de Comentários</span>
          <i class="fa-solid fa-comments text-cyan-400 text-base"></i>
        </div>
        <div class="mt-3 text-2xl font-bold text-slate-100" id="metric-avg-comments">--</div>
        <div class="mt-1 text-[11px] text-slate-500">Interações ativas por post</div>
      </div>
    </div>

    <!-- Seção de Duas Colunas: Top Posts / Formatos e Espectadores de Stories -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <!-- Coluna Esquerda: Top 5 Posts & Comparativo -->
      <div class="lg:col-span-7 space-y-6">
        <!-- Top 5 Posts Mais Engajados -->
        <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-6 shadow-xl backdrop-blur-sm space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
            <h3 class="text-sm font-semibold text-slate-100 flex items-center gap-2">
              <i class="fa-solid fa-trophy text-amber-400"></i> Top 5 Posts com Maior Engajamento
            </h3>
            <span class="text-xs text-slate-400 font-mono">Ranking da Conta</span>
          </div>

          <div id="metric-top-posts-list" class="space-y-3">
            <div class="text-center py-8 text-xs text-slate-500">
              <i class="fa-solid fa-spinner animate-spin mr-1"></i> Carregando métricas da conta...
            </div>
          </div>
        </div>

        <!-- Comparativo por Formato -->
        <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-6 shadow-xl backdrop-blur-sm space-y-4">
          <div class="border-b border-slate-800/80 pb-3">
            <h3 class="text-sm font-semibold text-slate-100 flex items-center gap-2">
              <i class="fa-solid fa-chart-simple text-cyan-400"></i> Desempenho por Formato de Mídia
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">Média de interações comparada entre formatos de publicação.</p>
          </div>

          <div class="grid grid-cols-3 gap-3" id="metric-format-cards">
            <div class="bg-slate-950/60 border border-slate-800 rounded-xl p-3.5 text-center space-y-1">
              <div class="text-[11px] font-semibold text-pink-400 flex items-center justify-center gap-1">
                <i class="fa-solid fa-image"></i> Foto
              </div>
              <div class="text-lg font-bold text-slate-100" id="fmt-photo-likes">--</div>
              <div class="text-[10px] text-slate-500">likes médios</div>
            </div>

            <div class="bg-slate-950/60 border border-slate-800 rounded-xl p-3.5 text-center space-y-1">
              <div class="text-[11px] font-semibold text-cyan-400 flex items-center justify-center gap-1">
                <i class="fa-solid fa-layer-group"></i> Carrossel
              </div>
              <div class="text-lg font-bold text-slate-100" id="fmt-album-likes">--</div>
              <div class="text-[10px] text-slate-500">likes médios</div>
            </div>

            <div class="bg-slate-950/60 border border-slate-800 rounded-xl p-3.5 text-center space-y-1">
              <div class="text-[11px] font-semibold text-purple-400 flex items-center justify-center gap-1">
                <i class="fa-solid fa-film"></i> Reels / Vídeo
              </div>
              <div class="text-lg font-bold text-slate-100" id="fmt-video-likes">--</div>
              <div class="text-[10px] text-slate-500">likes médios</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Coluna Direita: Radar de Espectadores de Stories -->
      <div class="lg:col-span-5 space-y-6">
        <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-6 shadow-xl backdrop-blur-sm space-y-4">
          <div class="flex items-center justify-between border-b border-slate-800/80 pb-3">
            <div>
              <h3 class="text-sm font-semibold text-slate-100 flex items-center gap-2">
                <i class="fa-solid fa-eye text-emerald-400"></i> Espectadores de Stories
              </h3>
              <p class="text-[11px] text-slate-400 mt-0.5">Quem assistiu aos seus stories ativos nas últimas 24h.</p>
            </div>
            <span class="text-[10px] bg-emerald-500/10 text-emerald-300 border border-emerald-500/20 px-2 py-0.5 rounded-full font-mono">Exclusivo Mobile</span>
          </div>

          <div id="story-viewers-container" class="space-y-3">
            <div class="text-center py-10 text-xs text-slate-500">
              <i class="fa-solid fa-spinner animate-spin mr-1"></i> Consultando visualizadores...
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ===================================================================== -->
  <!-- ABA 7: SESSÃO & SEGURANÇA -->
  <!-- ===================================================================== -->
  <div data-tab-content="session" class="hidden space-y-6">
    <div class="max-w-2xl mx-auto bg-slate-900/60 border border-slate-800 rounded-2xl p-6 shadow-xl backdrop-blur-sm space-y-5">
      <div class="border-b border-slate-800/80 pb-3">
        <h2 class="text-base font-semibold text-slate-100 flex items-center gap-2">
          <i class="fa-solid fa-key text-slate-400"></i> Sessão Local e Parâmetros de Dispositivo
        </h2>
        <p class="text-xs text-slate-400 mt-0.5">As credenciais privadas residem estritamente no seu computador em formato criptografado.</p>
      </div>

      <div class="space-y-4">
        <div class="p-4 bg-slate-950/80 border border-slate-800 rounded-xl space-y-2 text-xs">
          <div class="flex justify-between items-center text-slate-300">
            <span class="text-slate-400">Conta Alvo:</span>
            <span class="font-mono font-semibold text-pink-400">@<?= $esc($accountName) ?></span>
          </div>
          <div class="flex justify-between items-center text-slate-300">
            <span class="text-slate-400">Arquivo de Sessão:</span>
            <span class="font-mono text-slate-400">storage/instagram-highlights/auth/session.json</span>
          </div>
          <div class="flex justify-between items-center text-slate-300">
            <span class="text-slate-400">Dispositivo Emulado:</span>
            <span class="font-mono text-cyan-400">Samsung Galaxy S22 / Android 13</span>
          </div>
          <div class="flex justify-between items-center text-slate-300">
            <span class="text-slate-400">Travas Ativas:</span>
            <span class="text-emerald-400 flex items-center gap-1"><i class="fa-solid fa-circle-check"></i> Single-Attempt / Sem repetição cega</span>
          </div>
        </div>

        <div class="p-4 bg-amber-500/10 border border-amber-500/20 rounded-xl space-y-2 text-xs text-amber-300">
          <div class="font-semibold flex items-center gap-2">
            <i class="fa-solid fa-terminal"></i> Como conectar ou renovar a sessão
          </div>
          <p class="text-amber-200/80">Caso a sessão expire ou o Instagram solicite novo desafio (2FA), execute o comando interativo seguro no terminal do projeto:</p>
          <div class="bg-slate-950 px-3 py-2 rounded-lg font-mono text-[11px] text-slate-300 border border-slate-800 flex items-center justify-between select-all">
            <span>storage\instagram-highlights\venv\Scripts\python.exe tools\instagram-highlights\auth_cli.py</span>
            <button type="button" class="text-slate-400 hover:text-white" onclick="navigator.clipboard.writeText('storage\\instagram-highlights\\venv\\Scripts\\python.exe tools\\instagram-highlights\\auth_cli.py'); alert('Comando copiado!');"><i class="fa-regular fa-copy"></i></button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal de Progresso em Tailwind Escuro Moderno -->
<div id="hl-progress-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
  <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4 text-center">
    <div class="w-12 h-12 rounded-2xl bg-purple-500/10 text-purple-400 mx-auto flex items-center justify-center text-xl animate-pulse">
      <i class="fa-solid fa-circle-play"></i>
    </div>
    <div>
      <h3 id="hl-progress-title" class="text-base font-semibold text-slate-100">Publicando Stories…</h3>
      <p id="hl-progress-detail" class="text-xs text-slate-400 mt-1">Aguarde a confirmação das etapas pelo Instagram.</p>
    </div>
    <div class="w-full bg-slate-950 rounded-full h-2 overflow-hidden border border-slate-800">
      <div id="hl-progress-bar" class="h-full bg-gradient-to-r from-purple-500 to-pink-500 transition-all duration-300" style="width: 0%"></div>
    </div>
    <div class="flex justify-between text-xs text-slate-500 font-mono">
      <span id="hl-progress-step-count">Etapa 0 de 0</span>
      <span id="hl-progress-percent">0%</span>
    </div>
    <p class="text-[11px] text-slate-500">Mantenha esta janela aberta enquanto os stories são enviados.</p>
  </div>
</div>

<!-- Script do Instagram Studio -->
<script>
(function() {
  const CSRF_TOKEN = '<?= $csrf_token ?>';
  const ENDPOINT = '<?= $endpoint ?>';
  const HIGHLIGHTS_ENDPOINT = '<?= $highlights_endpoint ?>';

  // 1. Navegação de Abas
  const tabBtns = document.querySelectorAll('[data-tab-btn]');
  const tabContents = document.querySelectorAll('[data-tab-content]');

  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.getAttribute('data-tab-btn');
      tabBtns.forEach(b => {
        b.classList.remove('active', 'border-pink-500', 'text-pink-400', 'bg-slate-900/50');
        b.classList.add('border-transparent', 'text-slate-400');
      });
      tabContents.forEach(c => c.classList.add('hidden'));

      btn.classList.add('active', 'border-pink-500', 'text-pink-400', 'bg-slate-900/50');
      btn.classList.remove('border-transparent', 'text-slate-400');
      const activeContent = document.querySelector(`[data-tab-content="${target}"]`);
      if (activeContent) activeContent.classList.remove('hidden');

      if (target === 'profile') loadProfileData();
      if (target === 'metrics') loadMetricsData();
      try {
        history.replaceState(null, '', '#' + target);
      } catch (e) {}
    });
  });

  // Ativação por hash na URL (ex: #stories, #profile)
  const initialHash = window.location.hash.replace('#', '');
  if (initialHash) {
    const targetBtn = document.querySelector(`[data-tab-btn="${initialHash}"]`);
    if (targetBtn) targetBtn.click();
  }

  // =========================================================================
  // STORIES & DESTAQUES NATIVO
  // =========================================================================
  let currentHighlightMode = 'new';
  let selectedCoverAssetId = null;
  let stories = [];
  let activeStoryIndex = 0;

  // Elementos Modo
  const hlBtnModeNew = document.getElementById('hl-mode-new');
  const hlBtnModeExisting = document.getElementById('hl-mode-existing');
  const hlNewFields = document.getElementById('hl-new-fields');
  const hlExistingFields = document.getElementById('hl-existing-fields');

  if (hlBtnModeNew && hlBtnModeExisting) {
    hlBtnModeNew.addEventListener('click', () => {
      currentHighlightMode = 'new';
      hlBtnModeNew.className = 'px-3 py-1.5 rounded-lg bg-purple-500/20 text-purple-300 border border-purple-500/30 transition';
      hlBtnModeExisting.className = 'px-3 py-1.5 rounded-lg text-slate-400 hover:text-slate-200 transition';
      hlNewFields.classList.remove('hidden');
      hlExistingFields.classList.add('hidden');
      validateStoriesForm();
    });

    hlBtnModeExisting.addEventListener('click', () => {
      currentHighlightMode = 'existing';
      hlBtnModeExisting.className = 'px-3 py-1.5 rounded-lg bg-purple-500/20 text-purple-300 border border-purple-500/30 transition';
      hlBtnModeNew.className = 'px-3 py-1.5 rounded-lg text-slate-400 hover:text-slate-200 transition';
      hlExistingFields.classList.remove('hidden');
      hlNewFields.classList.add('hidden');
      if (hlExistingSelect && hlExistingSelect.options.length <= 1) {
        loadHighlightCatalog();
      }
      validateStoriesForm();
    });
  }

  // Título e Contador
  const hlTitleInput = document.getElementById('hl-title-input');
  const hlTitleCounter = document.getElementById('hl-title-counter');
  if (hlTitleInput) {
    hlTitleInput.addEventListener('input', () => {
      if (hlTitleCounter) hlTitleCounter.textContent = `${hlTitleInput.value.length} / 15 caracteres`;
      validateStoriesForm();
    });
  }

  // Capa do Destaque
  const hlCoverFileInput = document.getElementById('hl-cover-file-input');
  const hlCoverPreview = document.getElementById('hl-cover-preview');
  const hlCoverIcon = document.getElementById('hl-cover-icon');
  const hlBtnRemoveCover = document.getElementById('hl-btn-remove-cover');

  if (hlCoverFileInput) {
    hlCoverFileInput.addEventListener('change', () => {
      const file = hlCoverFileInput.files[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = (e) => {
        hlCoverPreview.src = e.target.result;
        hlCoverPreview.classList.remove('hidden');
        hlCoverIcon.classList.add('hidden');
        hlBtnRemoveCover.classList.remove('hidden');
      };
      reader.readAsDataURL(file);

      const fd = new FormData();
      fd.append('_csrf_token', CSRF_TOKEN);
      fd.append('kind', 'cover');
      fd.append('file', file);
      fetch(`${HIGHLIGHTS_ENDPOINT}/upload`, { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
          if (data.ok && data.asset) {
            selectedCoverAssetId = data.asset.id;
          } else {
            alert('Falha ao processar capa: ' + (data.message || 'Erro desconhecido'));
            resetCover();
          }
        })
        .catch(() => {
          alert('Erro de rede ao enviar capa.');
          resetCover();
        });
    });
  }

  function resetCover() {
    if (hlCoverFileInput) hlCoverFileInput.value = '';
    selectedCoverAssetId = null;
    if (hlCoverPreview) {
      hlCoverPreview.src = '';
      hlCoverPreview.classList.add('hidden');
    }
    if (hlCoverIcon) hlCoverIcon.classList.remove('hidden');
    if (hlBtnRemoveCover) hlBtnRemoveCover.classList.add('hidden');
  }
  if (hlBtnRemoveCover) hlBtnRemoveCover.addEventListener('click', resetCover);

  // Catálogo de Destaques Existentes
  const hlExistingSelect = document.getElementById('hl-existing-select');
  const hlBtnLoadCatalog = document.getElementById('hl-btn-load-catalog');

  function loadHighlightCatalog() {
    if (!hlBtnLoadCatalog) return;
    hlBtnLoadCatalog.disabled = true;
    hlBtnLoadCatalog.innerHTML = '<i class="fa-solid fa-spinner animate-spin"></i> Carregando...';
    fetch(`${HIGHLIGHTS_ENDPOINT}/catalog`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
      body: JSON.stringify({ _csrf_token: CSRF_TOKEN })
    })
      .then(res => res.json())
      .then(data => {
        hlBtnLoadCatalog.disabled = false;
        hlBtnLoadCatalog.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> Atualizar Destaques';
        if (!data.ok || !data.catalog) {
          alert('Não foi possível carregar destaques: ' + (data.message || 'Erro'));
          return;
        }
        hlExistingSelect.innerHTML = '<option value="">Selecione o destaque de destino...</option>';
        data.catalog.forEach(item => {
          const opt = document.createElement('option');
          opt.value = item.id;
          opt.textContent = `${item.title || 'Destaque sem nome'} (${item.media_count || 0} stories)`;
          hlExistingSelect.appendChild(opt);
        });
        validateStoriesForm();
      })
      .catch(() => {
        hlBtnLoadCatalog.disabled = false;
        hlBtnLoadCatalog.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> Tentar Novamente';
      });
  }
  if (hlBtnLoadCatalog) hlBtnLoadCatalog.addEventListener('click', loadHighlightCatalog);
  if (hlExistingSelect) hlExistingSelect.addEventListener('change', validateStoriesForm);

  // Drag & Drop e Upload de Imagens dos Stories
  const hlDropArea = document.getElementById('hl-drop-area');
  const hlFileInput = document.getElementById('hl-file-input');
  const hlStoriesList = document.getElementById('hl-stories-list');
  const hlStoryCounter = document.getElementById('hl-story-counter');
  const hlBtnPublish = document.getElementById('hl-btn-publish');
  const hlPublishHint = document.getElementById('hl-publish-hint');

  // Preview elements
  const hlPhoneImage = document.getElementById('hl-phone-image');
  const hlPhonePlaceholder = document.getElementById('hl-phone-placeholder');
  const hlPhoneBars = document.getElementById('hl-phone-bars');
  const hlPhoneSticker = document.getElementById('hl-phone-sticker');
  const hlPhoneStickerText = document.getElementById('hl-phone-sticker-text');
  const hlPrevStory = document.getElementById('hl-prev-story');
  const hlNextStory = document.getElementById('hl-next-story');
  const hlPreviewIndex = document.getElementById('hl-preview-index');

  if (hlDropArea && hlFileInput) {
    ['dragenter', 'dragover'].forEach(name => {
      hlDropArea.addEventListener(name, (e) => {
        e.preventDefault();
        hlDropArea.classList.add('border-purple-500', 'bg-purple-500/10');
      });
    });
    ['dragleave', 'drop'].forEach(name => {
      hlDropArea.addEventListener(name, (e) => {
        e.preventDefault();
        hlDropArea.classList.remove('border-purple-500', 'bg-purple-500/10');
      });
    });

    hlDropArea.addEventListener('drop', (e) => {
      if (e.dataTransfer && e.dataTransfer.files.length) {
        handleStoryFiles(Array.from(e.dataTransfer.files));
      }
    });

    hlFileInput.addEventListener('change', () => {
      if (hlFileInput.files.length) {
        handleStoryFiles(Array.from(hlFileInput.files));
        hlFileInput.value = '';
      }
    });
  }

  function handleStoryFiles(files) {
    const validFiles = files.filter(f => f.type.startsWith('image/'));
    if (!validFiles.length) {
      alert('Selecione arquivos de imagem válidos (JPEG ou PNG).');
      return;
    }
    if (stories.length + validFiles.length > 20) {
      alert('Máximo de 20 stories por operação.');
      return;
    }

    validFiles.forEach(file => {
      const sObj = {
        id: 's_' + Math.random().toString(36).substr(2, 9),
        file: file,
        previewUrl: URL.createObjectURL(file),
        link: '',
        assetId: null,
        uploading: true
      };
      stories.push(sObj);
      uploadStoryAsset(sObj);
    });

    renderStoriesList();
    updateSmartphonePreview();
    validateStoriesForm();
  }

  function uploadStoryAsset(sObj) {
    const fd = new FormData();
    fd.append('_csrf_token', CSRF_TOKEN);
    fd.append('kind', 'story');
    fd.append('file', sObj.file);

    fetch(`${HIGHLIGHTS_ENDPOINT}/upload`, { method: 'POST', body: fd })
      .then(res => res.json())
      .then(data => {
        if (data.ok && data.asset) {
          sObj.assetId = data.asset.id;
          sObj.uploading = false;
        } else {
          alert('Erro ao enviar story: ' + (data.message || 'Formato inválido'));
          removeStory(sObj.id);
        }
        renderStoriesList();
        validateStoriesForm();
      })
      .catch(() => {
        alert('Erro de conexão ao enviar story.');
        removeStory(sObj.id);
      });
  }

  function removeStory(id) {
    const idx = stories.findIndex(s => s.id === id);
    if (idx !== -1) {
      URL.revokeObjectURL(stories[idx].previewUrl);
      stories.splice(idx, 1);
      if (activeStoryIndex >= stories.length) {
        activeStoryIndex = Math.max(0, stories.length - 1);
      }
      renderStoriesList();
      updateSmartphonePreview();
      validateStoriesForm();
    }
  }

  function renderStoriesList() {
    if (!hlStoriesList || !hlStoryCounter) return;
    hlStoriesList.innerHTML = '';
    hlStoryCounter.textContent = `${stories.length} / 20 imagens`;

    stories.forEach((s, idx) => {
      const card = document.createElement('div');
      card.className = `flex items-center gap-3 p-3 rounded-xl bg-slate-950/70 border transition ${activeStoryIndex === idx ? 'border-purple-500/60 ring-1 ring-purple-500/30' : 'border-slate-800 hover:border-slate-700'}`;
      card.innerHTML = `
        <div class="relative w-12 h-20 rounded-lg overflow-hidden bg-slate-900 border border-slate-800 shrink-0 cursor-pointer">
          <img src="${s.previewUrl}" class="w-full h-full object-cover">
          <span class="absolute top-1 left-1 bg-black/80 text-[9px] font-mono text-white px-1.5 py-0.5 rounded">#${idx + 1}</span>
          ${s.uploading ? '<div class="absolute inset-0 bg-black/60 flex items-center justify-center"><i class="fa-solid fa-spinner animate-spin text-purple-400 text-xs"></i></div>' : ''}
        </div>
        <div class="flex-1 min-w-0 space-y-1.5">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-300">Story #${idx + 1}</span>
            <button type="button" class="text-xs text-rose-400 hover:text-rose-300 transition" data-remove-story="${s.id}" title="Remover story">
              <i class="fa-regular fa-trash-can"></i>
            </button>
          </div>
          <div class="relative">
            <i class="fa-solid fa-link absolute left-3 top-2.5 text-slate-500 text-xs"></i>
            <input type="url" placeholder="https://nerd.tfr-info.com.br/artigo (opcional)" value="${s.link}" data-link-story="${s.id}" class="w-full bg-slate-900 border border-slate-800 rounded-lg pl-8 pr-3 py-1.5 text-xs text-slate-200 placeholder-slate-600 focus:outline-none focus:border-purple-500/60">
          </div>
        </div>
      `;

      card.querySelector('img').addEventListener('click', () => {
        activeStoryIndex = idx;
        renderStoriesList();
        updateSmartphonePreview();
      });

      card.querySelector('[data-remove-story]').addEventListener('click', (e) => {
        e.stopPropagation();
        removeStory(s.id);
      });

      const linkInput = card.querySelector('[data-link-story]');
      linkInput.addEventListener('input', () => {
        s.link = linkInput.value.trim();
        if (activeStoryIndex === idx) {
          updateStickerPreview();
        }
      });

      hlStoriesList.appendChild(card);
    });
  }

  function updateSmartphonePreview() {
    if (!hlPhoneImage || !hlPhonePlaceholder) return;
    if (!stories.length) {
      hlPhoneImage.classList.add('hidden');
      hlPhonePlaceholder.classList.remove('hidden');
      hlPhoneSticker.classList.add('hidden');
      hlPhoneSticker.classList.remove('flex');
      hlPhoneBars.innerHTML = '<div class="h-0.5 flex-1 bg-white/70 rounded-full"></div>';
      hlPreviewIndex.textContent = '0 / 0';
      hlPrevStory.disabled = true;
      hlNextStory.disabled = true;
      return;
    }

    if (activeStoryIndex >= stories.length) activeStoryIndex = stories.length - 1;
    if (activeStoryIndex < 0) activeStoryIndex = 0;

    const cur = stories[activeStoryIndex];
    hlPhoneImage.src = cur.previewUrl;
    hlPhoneImage.classList.remove('hidden');
    hlPhonePlaceholder.classList.add('hidden');

    hlPhoneBars.innerHTML = '';
    stories.forEach((_, i) => {
      const bar = document.createElement('div');
      bar.className = `h-0.5 flex-1 rounded-full ${i <= activeStoryIndex ? 'bg-white' : 'bg-white/30'}`;
      hlPhoneBars.appendChild(bar);
    });

    updateStickerPreview();

    hlPreviewIndex.textContent = `${activeStoryIndex + 1} / ${stories.length}`;
    hlPrevStory.disabled = activeStoryIndex <= 0;
    hlNextStory.disabled = activeStoryIndex >= stories.length - 1;
  }

  function updateStickerPreview() {
    if (!stories.length || !hlPhoneSticker || !hlPhoneStickerText) return;
    const cur = stories[activeStoryIndex];
    if (cur.link) {
      const clean = cur.link.replace(/^https?:\/\/(www\.)?/, '').split('/')[0] || 'LINK';
      hlPhoneStickerText.textContent = clean;
      hlPhoneSticker.classList.remove('hidden');
      hlPhoneSticker.classList.add('flex');
    } else {
      hlPhoneSticker.classList.add('hidden');
      hlPhoneSticker.classList.remove('flex');
    }
  }

  if (hlPrevStory && hlNextStory) {
    hlPrevStory.addEventListener('click', () => {
      if (activeStoryIndex > 0) {
        activeStoryIndex--;
        renderStoriesList();
        updateSmartphonePreview();
      }
    });

    hlNextStory.addEventListener('click', () => {
      if (activeStoryIndex < stories.length - 1) {
        activeStoryIndex++;
        renderStoriesList();
        updateSmartphonePreview();
      }
    });
  }

  function validateStoriesForm() {
    if (!hlBtnPublish || !hlPublishHint) return;
    if (!stories.length) {
      hlBtnPublish.disabled = true;
      hlPublishHint.textContent = 'Adicione pelo menos 1 imagem vertical (1080×1920).';
      return;
    }
    const hasUploading = stories.some(s => s.uploading || !s.assetId);
    if (hasUploading) {
      hlBtnPublish.disabled = true;
      hlPublishHint.textContent = 'Enviando mídias para o servidor... Aguarde.';
      return;
    }
    if (currentHighlightMode === 'new' && (!hlTitleInput || !hlTitleInput.value.trim())) {
      hlBtnPublish.disabled = true;
      hlPublishHint.textContent = 'Informe o nome do destaque (até 15 caracteres).';
      return;
    }
    if (currentHighlightMode === 'existing' && (!hlExistingSelect || !hlExistingSelect.value)) {
      hlBtnPublish.disabled = true;
      hlPublishHint.textContent = 'Selecione o destaque de destino.';
      return;
    }

    hlBtnPublish.disabled = false;
    hlPublishHint.textContent = `Pronto para publicar ${stories.length} story(ies) e montar o destaque!`;
  }

  // Publicação em Lote com Barra de Progresso
  const hlProgressModal = document.getElementById('hl-progress-modal');
  const hlProgressTitle = document.getElementById('hl-progress-title');
  const hlProgressDetail = document.getElementById('hl-progress-detail');
  const hlProgressBar = document.getElementById('hl-progress-bar');
  const hlProgressStepCount = document.getElementById('hl-progress-step-count');
  const hlProgressPercent = document.getElementById('hl-progress-percent');

  if (hlBtnPublish) {
    hlBtnPublish.addEventListener('click', async () => {
      validateStoriesForm();
      if (hlBtnPublish.disabled) return;

      const randomBytes = new Uint8Array(16);
      crypto.getRandomValues(randomBytes);
      const jobId = Array.from(randomBytes).map(b => b.toString(16).padStart(2, '0')).join('');

      hlProgressModal.classList.remove('hidden');
      hlProgressBar.style.width = '5%';
      hlProgressPercent.textContent = '5%';
      hlProgressTitle.textContent = 'Preparando operação…';
      hlProgressDetail.textContent = 'Validando manifesto e travando estado único contra duplicatas.';

      const payload = {
        account: 'estrategia_nerd',
        job_id: jobId,
        items: stories.map(s => ({
          asset: s.assetId,
          link: s.link || ''
        })),
        highlight: {
          id: currentHighlightMode === 'existing' ? hlExistingSelect.value : null,
          title: currentHighlightMode === 'new' ? hlTitleInput.value.trim() : null,
          cover_asset: selectedCoverAssetId || null
        },
        _csrf_token: CSRF_TOKEN
      };

      try {
        const prepRes = await fetch(`${HIGHLIGHTS_ENDPOINT}/prepare`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
          body: JSON.stringify(payload)
        });
        const prepData = await prepRes.json();
        if (!prepData.ok || !prepData.job) {
          throw new Error(prepData.message || 'Falha ao preparar publicação.');
        }

        let job = prepData.job;
        while (job.status === 'ready' || job.status === 'running') {
          const total = job.total_steps || 1;
          const completed = job.completed_steps || 0;
          const pct = Math.max(10, Math.round((completed / total) * 100));

          hlProgressBar.style.width = `${pct}%`;
          hlProgressPercent.textContent = `${pct}%`;
          hlProgressStepCount.textContent = `Etapa ${completed} de ${total}`;
          hlProgressTitle.textContent = 'Publicando no Instagram…';
          hlProgressDetail.textContent = job.message || 'Enviando etapa para o @estrategia_nerd';

          const stepRes = await fetch(`${HIGHLIGHTS_ENDPOINT}/step`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
            body: JSON.stringify({ job_id: jobId, _csrf_token: CSRF_TOKEN })
          });
          const stepData = await stepRes.json();
          if (!stepData.ok || !stepData.job) {
            throw new Error(stepData.message || 'Falha na execução de uma etapa.');
          }
          job = stepData.job;

          if (job.status === 'completed') {
            hlProgressBar.style.width = '100%';
            hlProgressPercent.textContent = '100%';
            hlProgressTitle.textContent = 'Publicação Concluída com Sucesso!';
            hlProgressDetail.textContent = 'Stories e destaques publicados no @estrategia_nerd.';
            setTimeout(() => {
              hlProgressModal.classList.add('hidden');
              alert('🎉 Destaque e stories publicados com sucesso no Instagram!');
              window.location.reload();
            }, 1500);
            return;
          }

          if (job.status === 'failed' || job.status === 'unknown') {
            throw new Error(job.message || 'O Instagram interrompeu o envio. Nenhuma etapa foi reenviada às cegas.');
          }
        }
      } catch (err) {
        hlProgressModal.classList.add('hidden');
        alert('⚠️ Envio interrompido: ' + err.message);
      }
    });
  }

  // 2. Seletor de Tipo de Post (Foto vs Carrossel)
  let currentFeedType = 'photo';
  let selectedFiles = [];
  const btnPhoto = document.getElementById('feed-type-photo');
  const btnAlbum = document.getElementById('feed-type-album');
  const fileInput = document.getElementById('feed-file-input');

  btnPhoto.addEventListener('click', () => {
    currentFeedType = 'photo';
    btnPhoto.className = 'px-3 py-1.5 rounded-lg bg-pink-500/20 text-pink-300 border border-pink-500/30 transition';
    btnAlbum.className = 'px-3 py-1.5 rounded-lg text-slate-400 hover:text-slate-200 transition';
    fileInput.removeAttribute('multiple');
    clearFiles();
  });

  btnAlbum.addEventListener('click', () => {
    currentFeedType = 'album';
    btnAlbum.className = 'px-3 py-1.5 rounded-lg bg-pink-500/20 text-pink-300 border border-pink-500/30 transition';
    btnPhoto.className = 'px-3 py-1.5 rounded-lg text-slate-400 hover:text-slate-200 transition';
    fileInput.setAttribute('multiple', 'multiple');
    clearFiles();
  });

  fileInput.addEventListener('change', (e) => {
    const files = Array.from(e.target.files);
    if (!files.length) return;
    selectedFiles = files;
    renderFilePreviews();
  });

  function clearFiles() {
    selectedFiles = [];
    fileInput.value = '';
    document.getElementById('drop-prompt').classList.remove('hidden');
    document.getElementById('drop-previews').classList.add('hidden');
    document.getElementById('drop-previews').innerHTML = '';
  }

  function renderFilePreviews() {
    const previewsContainer = document.getElementById('drop-previews');
    const prompt = document.getElementById('drop-prompt');
    previewsContainer.innerHTML = '';

    if (!selectedFiles.length) {
      prompt.classList.remove('hidden');
      previewsContainer.classList.add('hidden');
      return;
    }

    prompt.classList.add('hidden');
    previewsContainer.classList.remove('hidden');

    selectedFiles.forEach((file, idx) => {
      const url = URL.createObjectURL(file);
      const wrap = document.createElement('div');
      wrap.className = 'relative w-20 h-20 rounded-xl overflow-hidden border border-slate-700 bg-slate-900 group';
      wrap.innerHTML = `
        <img src="${url}" class="w-full h-full object-cover">
        <span class="absolute top-1 left-1 bg-black/70 text-[9px] font-mono text-white px-1.5 py-0.5 rounded">#${idx + 1}</span>
      `;
      previewsContainer.appendChild(wrap);
    });
  }

  // Contador de caracteres
  const captionInput = document.getElementById('feed-caption');
  const charCounter = document.getElementById('char-counter');
  captionInput.addEventListener('input', () => {
    const len = captionInput.value.length;
    charCounter.textContent = `${len} / 2.200 caracteres`;
    if (len > 2200) charCounter.className = 'text-rose-400 font-semibold';
    else charCounter.className = 'text-slate-400';
  });

  // 3. Músicas do Instagram (Busca, Prévia e Slider)
  let selectedTrack = null;
  const searchMusicInput = document.getElementById('music-search-query');
  const btnSearchMusic = document.getElementById('btn-search-music');
  const btnLoadTrending = document.getElementById('btn-load-trending');
  const musicResultsList = document.getElementById('music-results-list');
  const selectedTrackCard = document.getElementById('selected-track-card');
  const audioPlayer = document.getElementById('audio-preview-player');
  const audioStartRange = document.getElementById('audio-start-range');
  const startTimeLabel = document.getElementById('start-time-label');

  btnSearchMusic.addEventListener('click', () => {
    const q = searchMusicInput.value.trim();
    if (!q) return;
    performMusicSearch(`${ENDPOINT}/search-music?q=` + encodeURIComponent(q));
  });

  searchMusicInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      btnSearchMusic.click();
    }
  });

  btnLoadTrending.addEventListener('click', () => {
    performMusicSearch(`${ENDPOINT}/trending-music`);
  });

  function performMusicSearch(url) {
    musicResultsList.innerHTML = '<div class="text-center py-4 text-xs text-slate-500"><i class="fa-solid fa-spinner animate-spin mr-1"></i> Buscando no catálogo do Instagram...</div>';
    fetch(url)
      .then(res => res.json())
      .then(data => {
        if (!data.ok || !data.tracks || !data.tracks.length) {
          musicResultsList.innerHTML = '<div class="text-center py-4 text-xs text-slate-500">Nenhuma faixa encontrada para este termo.</div>';
          return;
        }
        renderMusicResults(data.tracks);
      })
      .catch(err => {
        musicResultsList.innerHTML = '<div class="text-center py-4 text-xs text-rose-400">Falha ao buscar músicas. Verifique a sessão.</div>';
      });
  }

  function renderMusicResults(tracks) {
    musicResultsList.innerHTML = '';
    tracks.forEach(t => {
      const item = document.createElement('div');
      item.className = 'flex items-center justify-between p-2 rounded-xl bg-slate-950/60 hover:bg-slate-800/60 border border-slate-800/80 transition cursor-pointer group';
      item.innerHTML = `
        <div class="flex items-center gap-2.5 min-w-0">
          <img src="${t.cover_url || '/assets/brand/logo-symbol.png'}" class="w-9 h-9 rounded-lg object-cover bg-slate-900 border border-slate-700/80">
          <div class="min-w-0">
            <div class="text-xs font-medium text-slate-200 truncate group-hover:text-cyan-300 transition">${t.title}</div>
            <div class="text-[11px] text-slate-400 truncate">${t.subtitle || t.display_artist || 'Instagram Audio'}</div>
          </div>
        </div>
        <button type="button" class="text-xs px-2.5 py-1 rounded-lg bg-cyan-500/10 text-cyan-300 border border-cyan-500/20 group-hover:bg-cyan-500/20 transition">Selecionar</button>
      `;
      item.addEventListener('click', () => selectTrack(t));
      musicResultsList.appendChild(item);
    });
  }

  function selectTrack(track) {
    selectedTrack = track;
    selectedTrackCard.classList.remove('hidden');
    document.getElementById('selected-track-cover').src = track.cover_url || '/assets/brand/logo-symbol.png';
    document.getElementById('selected-track-title').textContent = track.title;
    document.getElementById('selected-track-artist').textContent = track.subtitle || track.display_artist || 'Instagram Audio';

    const durationSec = Math.floor((track.duration_ms || 30000) / 1000);
    audioStartRange.max = Math.max(10, durationSec - 10);
    const startOffset = (track.highlight_start_times_ms && track.highlight_start_times_ms[0]) ? Math.floor(track.highlight_start_times_ms[0] / 1000) : 0;
    audioStartRange.value = startOffset;
    startTimeLabel.textContent = `${audioStartRange.value}s`;

    if (track.preview_url) {
      audioPlayer.src = track.preview_url;
      audioPlayer.classList.remove('hidden');
      audioPlayer.currentTime = startOffset;
      audioPlayer.play().catch(() => {});
    } else {
      audioPlayer.classList.add('hidden');
    }
  }

  audioStartRange.addEventListener('input', () => {
    startTimeLabel.textContent = `${audioStartRange.value}s`;
    if (audioPlayer.src) {
      audioPlayer.currentTime = parseInt(audioStartRange.value, 10);
    }
  });

  document.getElementById('btn-remove-track').addEventListener('click', () => {
    selectedTrack = null;
    selectedTrackCard.classList.add('hidden');
    audioPlayer.pause();
    audioPlayer.src = '';
  });

  // 4. Publicar Feed
  const btnPublishFeed = document.getElementById('btn-publish-feed');
  btnPublishFeed.addEventListener('click', () => {
    if (!selectedFiles.length) {
      alert('Selecione pelo menos uma imagem para publicar.');
      return;
    }
    if (currentFeedType === 'album' && selectedFiles.length < 2) {
      alert('Carrossel requer no mínimo 2 imagens.');
      return;
    }

    btnPublishFeed.disabled = true;
    btnPublishFeed.innerHTML = '<i class="fa-solid fa-spinner animate-spin"></i> Publicando...';

    const formData = new FormData();
    formData.append('_csrf_token', CSRF_TOKEN);
    formData.append('media_type', currentFeedType);
    formData.append('caption', captionInput.value);

    if (currentFeedType === 'album') {
      selectedFiles.forEach(f => formData.append('files[]', f));
    } else {
      formData.append('file', selectedFiles[0]);
    }

    if (selectedTrack) {
      formData.append('track_json', JSON.stringify(selectedTrack.raw_dict || selectedTrack));
      formData.append('start_time_ms', parseInt(audioStartRange.value, 10) * 1000);
    }

    fetch(`${ENDPOINT}/upload-feed`, {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      btnPublishFeed.disabled = false;
      btnPublishFeed.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Publicar Agora';

      if (data.ok) {
        alert('Post publicado com sucesso no Instagram!' + (data.url ? `\nLink: ${data.url}` : ''));
        clearFiles();
        captionInput.value = '';
        selectedTrack = null;
        selectedTrackCard.classList.add('hidden');
      } else {
        alert('Erro ao publicar: ' + (data.message || 'Verifique o status da sessão.'));
      }
    })
    .catch(err => {
      btnPublishFeed.disabled = false;
      btnPublishFeed.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Publicar Agora';
      alert('Falha na comunicação com o servidor.');
    });
  });

  // 5. Perfil & Bio
  let profileLoaded = false;
  function loadProfileData() {
    if (profileLoaded) return;
    const postsGrid = document.getElementById('profile-posts-grid');
    postsGrid.innerHTML = '<div class="text-center py-8 col-span-3 text-xs text-slate-500"><i class="fa-solid fa-spinner animate-spin mr-1"></i> Carregando dados do perfil...</div>';

    fetch(`${ENDPOINT}/profile`)
      .then(res => res.json())
      .then(data => {
        if (!data.ok) {
          postsGrid.innerHTML = '<div class="text-center py-6 col-span-3 text-xs text-rose-400">' + (data.message || 'Falha ao carregar perfil.') + '</div>';
          return;
        }
        profileLoaded = true;
        document.getElementById('bio-text-input').value = data.biography || '';
        document.getElementById('bio-url-input').value = data.external_url || '';

        document.getElementById('preview-phone-bio').textContent = data.biography || '';
        document.getElementById('preview-phone-url').textContent = (data.external_url || '').replace(/^https?:\/\//, '');
        document.getElementById('preview-stat-posts').textContent = data.media_count || 0;
        document.getElementById('preview-stat-followers').textContent = data.follower_count || 0;
        document.getElementById('preview-stat-following').textContent = data.following_count || 0;

        renderProfilePosts(data.recent_posts || []);
      })
      .catch(err => {
        postsGrid.innerHTML = '<div class="text-center py-6 col-span-3 text-xs text-rose-400">Erro de rede ao carregar perfil.</div>';
      });
  }

  function renderProfilePosts(posts) {
    const postsGrid = document.getElementById('profile-posts-grid');
    postsGrid.innerHTML = '';
    if (!posts.length) {
      postsGrid.innerHTML = '<div class="text-center py-6 col-span-3 text-xs text-slate-500">Nenhum post recente retornado.</div>';
      return;
    }

    posts.forEach(p => {
      const card = document.createElement('div');
      card.className = 'relative rounded-xl overflow-hidden border border-slate-800 bg-slate-950 group';
      card.innerHTML = `
        <div class="aspect-square bg-slate-900">
          <img src="${p.thumbnail_url || '/assets/brand/logo-symbol.png'}" class="w-full h-full object-cover">
        </div>
        <div class="p-2 space-y-1">
          <div class="flex items-center justify-between text-[11px] text-slate-400">
            <span><i class="fa-solid fa-heart text-pink-400 mr-1"></i>${p.like_count}</span>
            <span><i class="fa-solid fa-comment text-cyan-400 mr-1"></i>${p.comment_count}</span>
          </div>
          <button type="button" class="btn-pin w-full py-1 text-[10px] rounded bg-slate-800 hover:bg-slate-700 text-slate-200 transition font-medium" data-id="${p.id}">
            <i class="fa-solid fa-thumbtack mr-1"></i> Fixar / Desafixar
          </button>
        </div>
      `;

      card.querySelector('.btn-pin').addEventListener('click', () => {
        togglePin(p.id);
      });
      postsGrid.appendChild(card);
    });
  }

  function togglePin(mediaId) {
    const fd = new FormData();
    fd.append('_csrf_token', CSRF_TOKEN);
    fd.append('media_id', mediaId);
    fd.append('pin', '1');

    fetch(`${ENDPOINT}/pin-media`, { method: 'POST', body: fd })
      .then(res => res.json())
      .then(data => {
        if (data.ok) alert('Fixação atualizada com sucesso no perfil!');
        else alert('Erro ao fixar: ' + data.message);
      });
  }

  document.getElementById('btn-save-profile').addEventListener('click', () => {
    const bio = document.getElementById('bio-text-input').value;
    const url = document.getElementById('bio-url-input').value;

    const fd = new FormData();
    fd.append('_csrf_token', CSRF_TOKEN);
    fd.append('biography', bio);
    fd.append('external_url', url);

    fetch(`${ENDPOINT}/update-profile`, { method: 'POST', body: fd })
      .then(res => res.json())
      .then(data => {
        if (data.ok) {
          alert('Bio e link da bio atualizados no Instagram!');
          document.getElementById('preview-phone-bio').textContent = bio;
          document.getElementById('preview-phone-url').textContent = url.replace(/^https?:\/\//, '');
        } else {
          alert('Erro ao atualizar bio: ' + data.message);
        }
      });
  });

  // 6. Direct (DMs)
  document.querySelectorAll('.dm-tpl-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('dm-text').value = btn.getAttribute('data-tpl');
    });
  });

  document.getElementById('btn-send-dm').addEventListener('click', () => {
    const user = document.getElementById('dm-username').value.trim();
    const text = document.getElementById('dm-text').value.trim();

    if (!user || !text) {
      alert('Preencha o usuário e a mensagem.');
      return;
    }

    const fd = new FormData();
    fd.append('_csrf_token', CSRF_TOKEN);
    fd.append('username', user);
    fd.append('text', text);

    fetch(`${ENDPOINT}/send-dm`, { method: 'POST', body: fd })
      .then(res => res.json())
      .then(data => {
        if (data.ok) {
          alert(`Mensagem enviada com sucesso para @${user}!`);
          document.getElementById('dm-username').value = '';
          document.getElementById('dm-text').value = '';
        } else {
          alert('Erro ao enviar DM: ' + data.message);
        }
      });
  });

  // 7. Inteligência Geek & Tendências
  document.getElementById('btn-search-viral').addEventListener('click', () => {
    const target = document.getElementById('viral-target-input').value.trim();
    const isHashtag = document.getElementById('viral-type').value === 'hashtag';
    const grid = document.getElementById('viral-results-grid');

    if (!target) return;
    grid.innerHTML = '<div class="col-span-full text-center py-10 text-xs text-slate-500"><i class="fa-solid fa-spinner animate-spin mr-1"></i> Rastreando e minerando conteúdos...</div>';

    fetch(`${ENDPOINT}/search-viral?target=` + encodeURIComponent(target) + `&is_hashtag=${isHashtag ? 1 : 0}`)
      .then(res => res.json())
      .then(data => {
        if (!data.ok || !data.posts || !data.posts.length) {
          grid.innerHTML = '<div class="col-span-full text-center py-10 text-xs text-slate-500">Nenhum post público encontrado para este alvo.</div>';
          return;
        }

        grid.innerHTML = '';
        data.posts.forEach(p => {
          const card = document.createElement('div');
          card.className = 'bg-slate-950/80 border border-slate-800 rounded-xl overflow-hidden flex flex-col group hover:border-amber-500/40 transition';
          card.innerHTML = `
            <div class="aspect-square bg-slate-900 relative">
              <img src="${p.thumbnail_url || '/assets/brand/logo-symbol.png'}" class="w-full h-full object-cover">
              <a href="${p.url}" target="_blank" class="absolute top-2 right-2 w-7 h-7 rounded-lg bg-black/60 hover:bg-black/90 text-white flex items-center justify-center text-xs backdrop-blur-sm transition">
                <i class="fa-solid fa-arrow-up-right-from-square"></i>
              </a>
            </div>
            <div class="p-3.5 space-y-2 flex-1 flex flex-col justify-between">
              <div class="flex items-center justify-between text-xs font-semibold">
                <span class="text-pink-400 flex items-center gap-1"><i class="fa-solid fa-heart"></i> ${p.like_count.toLocaleString('pt-BR')}</span>
                <span class="text-cyan-400 flex items-center gap-1"><i class="fa-solid fa-comment"></i> ${p.comment_count.toLocaleString('pt-BR')}</span>
              </div>
              <p class="text-xs text-slate-300 line-clamp-3">${p.caption || 'Sem legenda disponível.'}</p>
            </div>
          `;
          grid.appendChild(card);
        });
      });
  });

  // 8. Métricas & Audiência
  let metricsLoaded = false;

  function loadMetricsData() {
    if (metricsLoaded) return;

    fetch(`${ENDPOINT}/metrics`)
      .then(res => res.json())
      .then(data => {
        if (!data.ok) {
          document.getElementById('metric-top-posts-list').innerHTML = `<div class="text-center py-6 text-xs text-rose-400">${data.message || 'Falha ao carregar métricas.'}</div>`;
          return;
        }

        const m = data.metrics || data;
        document.getElementById('metric-followers').textContent = (m.follower_count || 0).toLocaleString('pt-BR');
        document.getElementById('metric-engagement').textContent = `${m.engagement_rate || 0}%`;
        document.getElementById('metric-avg-likes').textContent = (m.avg_likes || 0).toLocaleString('pt-BR');
        document.getElementById('metric-avg-comments').textContent = (m.avg_comments || 0).toLocaleString('pt-BR');

        if (m.format_comparison) {
          const fc = m.format_comparison;
          if (fc.photo) document.getElementById('fmt-photo-likes').textContent = fc.photo.avg_likes ?? 0;
          if (fc.album) document.getElementById('fmt-album-likes').textContent = fc.album.avg_likes ?? 0;
          if (fc.video) document.getElementById('fmt-video-likes').textContent = fc.video.avg_likes ?? 0;
        }

        renderTopPosts(m.top_posts || []);
        metricsLoaded = true;
      })
      .catch(err => {
        document.getElementById('metric-top-posts-list').innerHTML = '<div class="text-center py-6 text-xs text-rose-400">Erro de rede ao carregar métricas.</div>';
      });

    loadStoryViewers();
  }

  function renderTopPosts(posts) {
    const container = document.getElementById('metric-top-posts-list');
    container.innerHTML = '';
    if (!posts.length) {
      container.innerHTML = '<div class="text-center py-6 text-xs text-slate-500">Nenhum post recente registrado para análise.</div>';
      return;
    }

    posts.forEach((p, idx) => {
      const badgeClass = idx === 0 
        ? 'bg-amber-500/20 text-amber-300 border-amber-500/40' 
        : (idx === 1 
          ? 'bg-slate-300/20 text-slate-200 border-slate-300/40' 
          : (idx === 2 
            ? 'bg-amber-700/20 text-amber-400 border-amber-700/40' 
            : 'bg-slate-800 text-slate-400 border-slate-700'));

      const item = document.createElement('div');
      item.className = 'flex items-center justify-between p-3 rounded-xl bg-slate-950/70 border border-slate-800/80 hover:border-slate-700 transition group';
      item.innerHTML = `
        <div class="flex items-center gap-3 min-w-0">
          <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold font-mono border ${badgeClass}">
            #${idx + 1}
          </span>
          <img src="${p.thumbnail_url || '/assets/brand/logo-symbol.png'}" class="w-10 h-10 rounded-lg object-cover bg-slate-900 border border-slate-800 shrink-0">
          <div class="min-w-0">
            <p class="text-xs text-slate-200 truncate group-hover:text-cyan-300 transition">${p.caption || 'Sem legenda'}</p>
            <div class="flex items-center gap-3 text-[11px] text-slate-400 mt-0.5">
              <span class="text-pink-400 flex items-center gap-1"><i class="fa-solid fa-heart"></i> ${(p.like_count || 0).toLocaleString('pt-BR')}</span>
              <span class="text-cyan-400 flex items-center gap-1"><i class="fa-solid fa-comment"></i> ${(p.comment_count || 0).toLocaleString('pt-BR')}</span>
              <span class="capitalize text-slate-500 text-[10px]">• ${p.media_type || 'post'}</span>
            </div>
          </div>
        </div>
        ${p.url ? `
          <a href="${p.url}" target="_blank" class="w-8 h-8 rounded-lg bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-slate-100 flex items-center justify-center text-xs border border-slate-800 shrink-0 transition" title="Ver no Instagram">
            <i class="fa-solid fa-arrow-up-right-from-square"></i>
          </a>
        ` : ''}
      `;
      container.appendChild(item);
    });
  }

  function loadStoryViewers() {
    const container = document.getElementById('story-viewers-container');
    container.innerHTML = '<div class="text-center py-10 text-xs text-slate-500"><i class="fa-solid fa-spinner animate-spin mr-1"></i> Consultando visualizadores ativos...</div>';

    fetch(`${ENDPOINT}/story-viewers`)
      .then(res => res.json())
      .then(data => {
        if (!data.ok) {
          container.innerHTML = `<div class="text-center py-6 text-xs text-rose-400">${data.message || 'Falha ao consultar stories.'}</div>`;
          return;
        }

        const stories = data.stories || [];
        if (!stories.length) {
          container.innerHTML = `
            <div class="text-center py-10 text-xs text-slate-500 space-y-2">
              <i class="fa-solid fa-circle-info text-slate-600 text-2xl block mb-1"></i>
              <p>Nenhum story ativo nas últimas 24h.</p>
              <p class="text-[11px] text-slate-600">Publique um story para ver os espectadores nominais aqui.</p>
            </div>
          `;
          return;
        }

        container.innerHTML = '';
        stories.forEach((story, sIdx) => {
          const card = document.createElement('div');
          card.className = 'p-3.5 bg-slate-950/80 border border-slate-800 rounded-xl space-y-3';
          
          let viewersHtml = '';
          if (!story.viewers || !story.viewers.length) {
            viewersHtml = '<div class="text-[11px] text-slate-500 italic py-1">Nenhum espectador registrado ainda neste story.</div>';
          } else {
            viewersHtml = `
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto pr-1">
                ${story.viewers.map(v => `
                  <div class="flex items-center gap-2 p-1.5 rounded-lg bg-slate-900/60 border border-slate-800/60">
                    <img src="${v.profile_pic_url || '/assets/brand/logo-symbol.png'}" class="w-6 h-6 rounded-full object-cover bg-slate-800">
                    <div class="min-w-0">
                      <div class="text-[11px] font-medium text-slate-200 truncate">@${v.username}</div>
                      ${v.full_name ? `<div class="text-[9px] text-slate-500 truncate">${v.full_name}</div>` : ''}
                    </div>
                  </div>
                `).join('')}
              </div>
            `;
          }

          card.innerHTML = `
            <div class="flex items-center justify-between pb-2 border-b border-slate-800/60">
              <div class="flex items-center gap-2.5">
                <img src="${story.thumbnail_url || '/assets/brand/logo-symbol.png'}" class="w-8 h-12 rounded object-cover bg-slate-900 border border-slate-800">
                <div>
                  <div class="text-xs font-semibold text-slate-200">Story #${sIdx + 1}</div>
                  <div class="text-[10px] text-slate-500">${story.viewer_count || 0} espectadores totais</div>
                </div>
              </div>
              <span class="text-[10px] px-2 py-0.5 rounded-full bg-pink-500/10 text-pink-300 border border-pink-500/20 font-mono">Ativo</span>
            </div>
            ${viewersHtml}
          `;
          container.appendChild(card);
        });
      })
      .catch(err => {
        container.innerHTML = '<div class="text-center py-6 text-xs text-rose-400">Erro de rede ao consultar stories.</div>';
      });
  }

  const btnRefreshMetrics = document.getElementById('btn-refresh-metrics');
  if (btnRefreshMetrics) {
    btnRefreshMetrics.addEventListener('click', () => {
      metricsLoaded = false;
      loadMetricsData();
    });
  }

})();
</script>
