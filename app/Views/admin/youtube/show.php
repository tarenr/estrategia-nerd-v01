<?php
/**
 * @file        app/Views/admin/youtube/show.php
 * @project     Estrategia Nerd
 * @purpose     Visualização detalhada de um vídeo/Short do YouTube
 */

declare(strict_types=1);

$esc = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$video = $video ?? [];
$isShort = ($video['content_kind'] ?? 'video') === 'short';
$thumb = !empty($video['thumbnail_url']) ? $video['thumbnail_url'] : (!empty($video['thumbnail_path']) ? url('/' . ltrim((string) $video['thumbnail_path'], '/')) : null);
$duration = (int) ($video['duration_seconds'] ?? 0);
$durationFormatted = $duration > 0 ? sprintf('%02d:%02d', (int) floor($duration / 60), $duration % 60) : '—';
$localVideo = !empty($video['video_path']) ? url('/' . ltrim((string) $video['video_path'], '/')) : null;
$youtubeId = trim((string) ($video['youtube_video_id'] ?? ''));
$canEmbedYouTube = $localVideo === null
    && $youtubeId !== ''
    && ($video['privacy_status'] ?? 'private') === 'public';
?>

<div class="max-w-4xl mx-auto px-4 py-6 space-y-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <div class="flex items-center gap-2 mb-1">
        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold <?= $isShort ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' ?>">
          <i class="fa-solid <?= $isShort ? 'fa-mobile-screen-button' : 'fa-film' ?>"></i>
          <?= $isShort ? 'YouTube Short' : 'Vídeo Longo' ?>
        </span>
        <span class="text-xs text-slate-500 font-mono">ID: #<?= (int) ($video['id'] ?? 0) ?></span>
      </div>
      <h1 class="admin-page-title text-xl lg:text-2xl font-bold text-white"><?= $esc($video['title'] ?? 'Sem título') ?></h1>
      <div class="admin-page-subtitle">Acompanhamento de envio, métricas e processamento no YouTube.</div>
    </div>
    <div class="admin-page-actions flex items-center gap-2">
      <a href="<?= url('/admin/youtube') ?>" class="admin-btn admin-btn-secondary flex items-center gap-2">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Voltar ao painel</span>
      </a>
      <?php if (!empty($video['permalink'])): ?>
        <a href="<?= $esc($video['permalink']) ?>" target="_blank" rel="noopener noreferrer" class="admin-btn admin-btn-primary flex items-center gap-2">
          <i class="fa-brands fa-youtube"></i>
          <span>Abrir no YouTube</span>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
    <!-- Capa e Métricas -->
    <div class="md:col-span-5 space-y-4">
      <div class="aspect-video bg-black border border-slate-800 rounded-xl overflow-hidden relative flex items-center justify-center">
        <?php if ($localVideo !== null): ?>
          <video
            src="<?= $esc($localVideo) ?>"
            <?php if ($thumb): ?>poster="<?= $esc($thumb) ?>"<?php endif; ?>
            controls
            playsinline
            preload="metadata"
            class="w-full h-full object-contain bg-black"
          >
            Seu navegador não consegue reproduzir este vídeo.
          </video>
        <?php elseif ($canEmbedYouTube): ?>
          <iframe
            src="https://www.youtube-nocookie.com/embed/<?= $esc($youtubeId) ?>"
            title="<?= $esc($video['title'] ?? 'Vídeo do YouTube') ?>"
            class="w-full h-full border-0"
            loading="lazy"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            referrerpolicy="strict-origin-when-cross-origin"
            allowfullscreen
          ></iframe>
        <?php else: ?>
          <div class="px-6 text-center text-slate-400">
            <i class="fa-solid fa-film text-4xl text-slate-700"></i>
            <p class="mt-3 text-sm font-semibold text-slate-300">Reprodução indisponível</p>
            <p class="mt-1 text-xs">O arquivo local não está disponível e o YouTube não incorpora vídeos privados.</p>
          </div>
        <?php endif; ?>
        <?php if ($localVideo === null && !$canEmbedYouTube): ?>
          <span class="absolute bottom-2 right-2 px-2 py-0.5 rounded bg-black/80 text-white font-mono text-xs">
            <?= $durationFormatted ?>
          </span>
        <?php endif; ?>
      </div>

      <!-- Métricas Rápidas -->
      <div class="grid grid-cols-3 gap-2">
        <div class="admin-panel !p-3 text-center rounded-xl bg-slate-900/90 border border-slate-800">
          <div class="text-[10px] text-slate-400 uppercase font-semibold">Views</div>
          <div class="text-lg font-black text-white font-mono mt-0.5"><?= number_format((int) ($video['views_count'] ?? 0), 0, ',', '.') ?></div>
        </div>
        <div class="admin-panel !p-3 text-center rounded-xl bg-slate-900/90 border border-slate-800">
          <div class="text-[10px] text-slate-400 uppercase font-semibold">Curtidas</div>
          <div class="text-lg font-black text-white font-mono mt-0.5"><?= number_format((int) ($video['likes_count'] ?? 0), 0, ',', '.') ?></div>
        </div>
        <div class="admin-panel !p-3 text-center rounded-xl bg-slate-900/90 border border-slate-800">
          <div class="text-[10px] text-slate-400 uppercase font-semibold">Comentários</div>
          <div class="text-lg font-black text-white font-mono mt-0.5"><?= number_format((int) ($video['comments_count'] ?? 0), 0, ',', '.') ?></div>
        </div>
      </div>
    </div>

    <!-- Informações Técnicas -->
    <div class="md:col-span-7">
      <section class="admin-panel rounded-xl bg-slate-900/90 border border-slate-800 p-5 space-y-4">
        <h3 class="text-sm font-bold text-white border-b border-slate-800 pb-2 flex items-center gap-2">
          <i class="fa-solid fa-circle-info text-cyan-400"></i>
          <span>Dados do Vídeo</span>
        </h3>

        <dl class="grid grid-cols-2 gap-4 text-xs">
          <div>
            <dt class="text-slate-400 uppercase font-semibold text-[10px]">Status</dt>
            <dd class="mt-1 font-bold text-cyan-300"><?= $esc($video['status'] ?? '—') ?></dd>
          </div>
          <div>
            <dt class="text-slate-400 uppercase font-semibold text-[10px]">Origem</dt>
            <dd class="mt-1 text-slate-200"><?= ($video['origin'] ?? '') === 'youtube' ? 'Sincronizado do YouTube' : 'Upload Local' ?></dd>
          </div>
          <div>
            <dt class="text-slate-400 uppercase font-semibold text-[10px]">Fase de Publicação</dt>
            <dd class="mt-1 text-slate-200 font-mono"><?= $esc($video['publish_phase'] ?? '—') ?></dd>
          </div>
          <div>
            <dt class="text-slate-400 uppercase font-semibold text-[10px]">Visibilidade</dt>
            <dd class="mt-1 text-slate-200 capitalize"><?= $esc($video['privacy_status'] ?? 'private') ?></dd>
          </div>
          <div>
            <dt class="text-slate-400 uppercase font-semibold text-[10px]">Publicado em</dt>
            <dd class="mt-1 text-slate-200"><?= $esc($video['published_at'] ?? '—') ?></dd>
          </div>
          <div>
            <dt class="text-slate-400 uppercase font-semibold text-[10px]">Agendamento Previsto</dt>
            <dd class="mt-1 text-slate-200"><?= $esc($video['scheduled_at'] ?? '—') ?></dd>
          </div>
          <div>
            <dt class="text-slate-400 uppercase font-semibold text-[10px]">ID Remoto no YouTube</dt>
            <dd class="mt-1 font-mono text-cyan-300"><?= $esc($video['youtube_video_id'] ?? '—') ?></dd>
          </div>
          <div>
            <dt class="text-slate-400 uppercase font-semibold text-[10px]">Última Sincronização</dt>
            <dd class="mt-1 text-slate-200"><?= $esc($video['last_synced_at'] ?? '—') ?></dd>
          </div>
        </dl>

        <?php if (!empty($video['description'])): ?>
          <div class="pt-3 border-t border-slate-800">
            <div class="text-[10px] text-slate-400 uppercase font-semibold mb-1">Descrição</div>
            <div class="p-3 bg-slate-950/60 rounded-lg text-slate-300 text-xs whitespace-pre-wrap max-h-48 overflow-y-auto font-sans leading-relaxed">
              <?= $esc($video['description']) ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($video['error_log'])): ?>
          <div class="rounded-xl border border-rose-500/30 bg-rose-950/20 p-4 text-xs text-rose-200 space-y-1">
            <div class="font-bold flex items-center gap-1.5 text-rose-400">
              <i class="fa-solid fa-triangle-exclamation"></i>
              <span>Log de Erro / Reconciliação</span>
            </div>
            <p class="font-mono"><?= $esc($video['error_log']) ?></p>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </div>
</div>
