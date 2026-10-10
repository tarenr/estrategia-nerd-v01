<?php
/**
 * @file        app/Views/admin/instagram/show.php
 * @project     Estrategia Nerd
 * @purpose     Tela de detalhes de mídia do Instagram (FEAT-010)
 *
 * Variáveis disponíveis (injetadas pelo InstagramController::show()):
 * @var string                        $title
 * @var array<string,mixed>           $post         Post local
 * @var array<int,array<string,mixed>> $medias       Mídias do post
 * @var array<int,array<string,mixed>> $insights     Insights da Meta (alcance, impressões, salvamentos, interações)
 * @var array<int,array<string,mixed>> $comments     Comentários recentes
 */

declare(strict_types=1);

$post     = $post ?? [];
$medias   = $medias ?? [];
$insights = $insights ?? [];
$comments = $comments ?? [];

$esc = static fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$fmt = static fn (int $v): string => number_format($v, 0, ',', '.');

$postId = (int) ($post['id'] ?? 0);
$status = (string) ($post['status'] ?? 'rascunho');
$confirmationPending = in_array((string) ($post['publish_phase'] ?? ''), ['awaiting_confirmation','published_id_pending'], true);

$statusLabels = ['rascunho' => 'Rascunho', 'agendado' => 'Agendado', 'publicando' => 'Publicando', 'publicado' => 'Publicado', 'erro' => 'Erro', 'cancelado' => 'Cancelado'];
$statusColors = ['rascunho' => 'border-slate-600 text-slate-300', 'agendado' => 'border-amber-500/40 text-amber-200', 'publicando' => 'border-cyan-500/40 text-cyan-200', 'publicado' => 'border-emerald-500/40 text-emerald-200', 'erro' => 'border-rose-500/40 text-rose-200', 'cancelado' => 'border-slate-500/40 text-slate-400'];

// $insights vem no formato bruto da Graph API: [{name, values:[{value}]}, ...]
$insightTotals = ['reach' => null, 'impressions' => null, 'saved' => null, 'total_interactions' => null];
foreach ($insights as $metric) {
    $name = (string) ($metric['name'] ?? '');
    if (!array_key_exists($name, $insightTotals)) {
        continue;
    }
    $sum = 0;
    foreach ((array) ($metric['values'] ?? []) as $v) {
        $sum += (int) ($v['value'] ?? 0);
    }
    $insightTotals[$name] = $sum;
}

$firstMedia    = $medias[0] ?? null;
$firstMediaUrl = '';
$firstIsVideo  = false;
if ($firstMedia !== null) {
    $caminho = trim((string) ($firstMedia['caminho'] ?? ''));
    $firstIsVideo = (string) ($firstMedia['tipo_arquivo'] ?? '') === 'video' || preg_match('#\.mp4(\?.*)?$#i', $caminho);
    $firstMediaUrl = trim((string) ($firstMedia['url_publica'] ?? ''));
    if ($firstMediaUrl === '') {
        $firstMediaUrl = $caminho !== '' ? (str_starts_with($caminho, 'http') ? $caminho : url('/' . ltrim($caminho, '/'))) : '';
    }
}
$readyPreview = \App\Services\Instagram\AudioReelGeneratorService::readyVideoPath($post, base_path('public'));
if ($readyPreview !== null && !empty($post['audio_track_id'])) {
    $firstMediaUrl = url('/' . ltrim($readyPreview, '/'));
    $firstIsVideo = true;
}
?>

<div class="max-w-5xl mx-auto" data-instagram-show-root>
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title"><i class="fa-brands fa-instagram text-pink-400" aria-hidden="true"></i> Detalhes do Post</h1>
      <div class="admin-page-subtitle">
        <span class="admin-chip <?= $esc($statusColors[$status] ?? 'border-slate-600 text-slate-300') ?>"><?= $esc($confirmationPending ? 'Aguardando confirmação' : ($statusLabels[$status] ?? $status)) ?></span>
      </div>
    </div>
    <div class="admin-page-actions">
      <a href="<?= url('/admin/instagram/posts/' . $postId . '/editar') ?>" class="admin-btn admin-btn-secondary"><i class="fa-solid fa-pen" aria-hidden="true"></i> Editar Post</a>
      <a href="<?= url('/admin/instagram') ?>" class="admin-btn admin-btn-primary"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar</a>
    </div>
  </div>

  <?php if ($confirmationPending): ?>
    <div role="status" class="mt-4 rounded-xl border border-amber-500/40 bg-amber-950/30 p-4 text-sm text-amber-100">
      <strong>Aguardando confirmação da publicação.</strong>
      <p class="mt-1"><?= $esc((string) ($post['error_log'] ?? 'A resposta da Meta ainda não permite confirmar o resultado.')) ?></p>
      <p class="mt-1">Confira o Instagram antes de uma nova tentativa. O sistema não reenviará esta publicação automaticamente.</p>
    </div>
  <?php elseif ($status === 'publicado' && empty($post['permalink'])): ?>
    <div role="status" class="mt-4 rounded-xl border border-cyan-500/40 p-4 text-sm text-cyan-100">Publicação confirmada. O link está aguardando atualização; não é necessário publicar novamente.</div>
  <?php elseif ($status === 'erro' && !empty($post['error_log'])): ?>
    <div role="alert" class="mt-4 rounded-xl border border-rose-500/40 p-4 text-sm text-rose-100"><?= $esc((string) $post['error_log']) ?></div>
  <?php endif; ?>

  <div class="grid grid-cols-1 lg:grid-cols-[1fr_1.3fr] gap-6 mt-6">
    <!-- Mídia -->
    <section class="admin-panel">
      <div class="rounded-xl overflow-hidden bg-slate-900 border border-slate-800 aspect-square flex items-center justify-center">
        <?php if ($firstIsVideo && $firstMediaUrl !== ''): ?>
          <video src="<?= $esc($firstMediaUrl) ?>" controls playsinline class="h-full w-full object-contain bg-black"></video>
        <?php elseif ($firstMediaUrl !== ''): ?>
          <img src="<?= $esc($firstMediaUrl) ?>" alt="" class="h-full w-full object-cover" onerror="this.classList.add('hidden'); if(this.nextElementSibling) this.nextElementSibling.classList.remove('hidden');">
          <div class="hidden h-full w-full flex items-center justify-center bg-slate-900 text-slate-600">
            <i class="fa-brands fa-instagram text-4xl" aria-hidden="true"></i>
          </div>
        <?php else: ?>
          <i class="fa-brands fa-instagram text-4xl text-slate-600" aria-hidden="true"></i>
        <?php endif; ?>
      </div>
      <?php if (count($medias) > 1): ?>
        <div class="grid grid-cols-4 gap-2 mt-3">
          <?php foreach (array_slice($medias, 1) as $m): ?>
            <?php
            $mUrl = trim((string) ($m['url_publica'] ?? ''));
            $caminho = trim((string) ($m['caminho'] ?? ''));
            if ($mUrl === '') {
                $mUrl = $caminho !== '' ? (str_starts_with($caminho, 'http') ? $caminho : url('/' . ltrim($caminho, '/'))) : '';
            }
            $mVideo = (string) ($m['tipo_arquivo'] ?? '') === 'video' || preg_match('#\.mp4(\?.*)?$#i', $caminho);
            ?>
            <div class="aspect-square rounded-lg overflow-hidden bg-slate-800 border border-slate-700 flex items-center justify-center">
              <?php if ($mVideo && $mUrl !== ''): ?>
                <video src="<?= $esc($mUrl) ?>" muted class="h-full w-full object-cover"></video>
              <?php elseif ($mUrl !== ''): ?>
                <img src="<?= $esc($mUrl) ?>" alt="" class="h-full w-full object-cover" onerror="this.classList.add('hidden'); if(this.nextElementSibling) this.nextElementSibling.classList.remove('hidden');">
                <div class="hidden h-full w-full flex items-center justify-center bg-slate-800 text-slate-500">
                  <i class="fa-solid fa-image text-sm" aria-hidden="true"></i>
                </div>
              <?php else: ?>
                <i class="fa-solid fa-image text-slate-500 text-sm" aria-hidden="true"></i>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($post['legenda'])): ?>
        <div class="mt-4 text-sm text-slate-300 leading-5"><?= nl2br($esc((string) $post['legenda'])) ?></div>
      <?php else: ?>
        <div class="mt-4 text-xs italic text-slate-500">Sem legenda cadastrada para esta publicação.</div>
      <?php endif; ?>

      <?php if (!empty($audio_track)): ?>
        <div class="mt-4 p-3 rounded-xl bg-purple-950/20 border border-purple-500/30 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-purple-900/40 border border-purple-500/30 flex items-center justify-center text-purple-300">
              <i class="fa-solid fa-music"></i>
            </div>
            <div>
              <div class="text-xs font-bold text-slate-100"><?= $esc((string) ($audio_track['titulo'] ?? 'Trilha Sonora')) ?></div>
              <div class="text-[11px] text-slate-400">
                <?= $esc((string) ($audio_track['artista'] ?? '')) ?> • Corte: <?= sprintf('%02d:%02d', floor((int) ($post['audio_start_seconds'] ?? 0) / 60), (int) ($post['audio_start_seconds'] ?? 0) % 60) ?> - <?= sprintf('%02d:%02d', floor(((int) ($post['audio_start_seconds'] ?? 0) + (int) ($post['audio_duration_seconds'] ?? 10)) / 60), ((int) ($post['audio_start_seconds'] ?? 0) + (int) ($post['audio_duration_seconds'] ?? 10)) % 60) ?> (<?= (int) ($post['audio_duration_seconds'] ?? 10) ?>s)
              </div>
            </div>
          </div>
          <?php
          $trackUrl = (string) ($audio_track['url'] ?? '');
          if ($trackUrl === '' && !empty($audio_track['arquivo_path'])) {
              $trackUrl = (string) asset($audio_track['arquivo_path']);
          }
          ?>
          <?php if ($trackUrl !== ''): ?>
            <audio controls src="<?= $esc($trackUrl) ?>" class="h-8 max-w-[180px]"></audio>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($post['permalink'])): ?>
        <a href="<?= $esc((string) $post['permalink']) ?>" target="_blank" rel="noopener noreferrer" class="admin-btn admin-btn-secondary mt-4 w-full justify-center">
          <i class="fa-brands fa-instagram" aria-hidden="true"></i> Abrir no Instagram
        </a>
      <?php endif; ?>
    </section>

    <div class="space-y-6">
      <!-- Insights -->
      <section class="admin-panel">
        <div class="admin-panel-title"><i class="fa-solid fa-chart-simple text-cyan-300" aria-hidden="true"></i><span>Insights</span></div>
        <?php if (array_filter($insightTotals, static fn ($v) => $v !== null) === []): ?>
          <div class="mt-3 text-sm text-slate-400">Sem dados de insights disponíveis para este post ainda.</div>
        <?php else: ?>
          <div class="grid grid-cols-2 gap-4 mt-4">
            <?php foreach (['reach' => ['Alcance', 'fa-eye', '#00d4ff'], 'impressions' => ['Impressões', 'fa-chart-column', '#facc15'], 'saved' => ['Salvamentos', 'fa-bookmark', '#34d399'], 'total_interactions' => ['Interações', 'fa-heart', '#f472b6']] as $key => [$label, $icon, $color]): ?>
              <div class="stat-card stat-card-compact admin-summary-card">
                <div class="stat-icon" style="background: <?= $esc($color) ?>22; color: <?= $esc($color) ?>;">
                  <i class="fa-solid <?= $esc($icon) ?>" aria-hidden="true"></i>
                </div>
                <div class="stat-value neon-text" style="color: <?= $esc($color) ?>;"><?= $insightTotals[$key] === null ? '—' : $fmt($insightTotals[$key]) ?></div>
                <div class="stat-label"><?= $esc($label) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <!-- Comentários -->
      <section class="admin-panel">
        <div class="admin-panel-title"><i class="fa-solid fa-comments text-violet-300" aria-hidden="true"></i><span>Comentários</span></div>
        <?php if ($comments === []): ?>
          <div class="mt-3 text-sm text-slate-400">Nenhum comentário encontrado.</div>
        <?php else: ?>
          <div class="mt-4 space-y-3 max-h-[420px] overflow-y-auto pr-1">
            <?php foreach ($comments as $c): ?>
              <div class="flex items-start gap-3 rounded-xl border border-slate-800 bg-slate-900/50 p-3">
                <div class="h-8 w-8 shrink-0 rounded-full bg-gradient-to-br from-pink-500 to-orange-400 flex items-center justify-center text-[10px] font-black text-white">
                  <?= $esc(mb_strtoupper(mb_substr((string) ($c['username'] ?? '?'), 0, 1))) ?>
                </div>
                <div class="min-w-0">
                  <div class="text-xs font-bold text-white">@<?= $esc((string) ($c['username'] ?? '')) ?></div>
                  <div class="text-sm text-slate-300 mt-0.5"><?= $esc((string) ($c['text'] ?? '')) ?></div>
                  <?php $ts = (string) ($c['timestamp'] ?? ''); ?>
                  <?php if ($ts !== ''): ?>
                    <div class="text-[11px] text-slate-500 mt-1"><?= $esc(date('d/m/Y H:i', strtotime($ts) ?: time())) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </div>
</div>
