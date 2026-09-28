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

$statusLabels = ['rascunho' => 'Rascunho', 'agendado' => 'Agendado', 'publicando' => 'Publicando', 'publicado' => 'Publicado', 'erro' => 'Erro'];
$statusColors = ['rascunho' => 'border-slate-600 text-slate-300', 'agendado' => 'border-amber-500/40 text-amber-200', 'publicando' => 'border-cyan-500/40 text-cyan-200', 'publicado' => 'border-emerald-500/40 text-emerald-200', 'erro' => 'border-rose-500/40 text-rose-200'];

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
    $firstIsVideo = (string) ($firstMedia['tipo_arquivo'] ?? '') === 'video';
    $firstMediaUrl = trim((string) ($firstMedia['url_publica'] ?? ''));
    if ($firstMediaUrl === '') {
        $caminho = trim((string) ($firstMedia['caminho'] ?? ''));
        $firstMediaUrl = $caminho !== '' ? (str_starts_with($caminho, 'http') ? $caminho : url('/' . ltrim($caminho, '/'))) : '';
    }
}
?>

<div class="max-w-5xl mx-auto" data-instagram-show-root>
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title"><i class="fa-brands fa-instagram text-pink-400" aria-hidden="true"></i> Detalhes do Post</h1>
      <div class="admin-page-subtitle">
        <span class="admin-chip <?= $esc($statusColors[$status] ?? 'border-slate-600 text-slate-300') ?>"><?= $esc($statusLabels[$status] ?? $status) ?></span>
      </div>
    </div>
    <div class="admin-page-actions">
      <a href="<?= url('/admin/instagram/posts/' . $postId . '/editar') ?>" class="admin-btn admin-btn-secondary"><i class="fa-solid fa-pen" aria-hidden="true"></i> Editar Post</a>
      <a href="<?= url('/admin/instagram') ?>" class="admin-btn admin-btn-primary"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar</a>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-[1fr_1.3fr] gap-6 mt-6">
    <!-- Mídia -->
    <section class="admin-panel">
      <div class="rounded-xl overflow-hidden bg-slate-900 border border-slate-800 aspect-square flex items-center justify-center">
        <?php if ($firstIsVideo): ?>
          <i class="fa-solid fa-video text-4xl text-slate-500" aria-hidden="true"></i>
        <?php elseif ($firstMediaUrl !== ''): ?>
          <img src="<?= $esc($firstMediaUrl) ?>" alt="" class="h-full w-full object-cover">
        <?php else: ?>
          <i class="fa-brands fa-instagram text-4xl text-slate-600" aria-hidden="true"></i>
        <?php endif; ?>
      </div>
      <?php if (count($medias) > 1): ?>
        <div class="grid grid-cols-4 gap-2 mt-3">
          <?php foreach (array_slice($medias, 1) as $m): ?>
            <?php
            $mUrl = trim((string) ($m['url_publica'] ?? ''));
            if ($mUrl === '') {
                $caminho = trim((string) ($m['caminho'] ?? ''));
                $mUrl = $caminho !== '' ? (str_starts_with($caminho, 'http') ? $caminho : url('/' . ltrim($caminho, '/'))) : '';
            }
            $mVideo = (string) ($m['tipo_arquivo'] ?? '') === 'video';
            ?>
            <div class="aspect-square rounded-lg overflow-hidden bg-slate-800 border border-slate-700 flex items-center justify-center">
              <?php if ($mVideo): ?>
                <i class="fa-solid fa-video text-slate-400 text-sm" aria-hidden="true"></i>
              <?php elseif ($mUrl !== ''): ?>
                <img src="<?= $esc($mUrl) ?>" alt="" class="h-full w-full object-cover">
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($post['legenda'])): ?>
        <div class="mt-4 text-sm text-slate-300 leading-5"><?= nl2br($esc((string) $post['legenda'])) ?></div>
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
