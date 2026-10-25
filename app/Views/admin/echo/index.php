<?php
declare(strict_types=1);

use App\Support\Csrf;

/** @var array<string, array{label:string,pecas:array<int,string>}> $fantasias */
$fantasias = $fantasias ?? [];
$errors = $errors ?? [];
$saved = isset($_GET['saved']) && (string) $_GET['saved'] === '1';
$targetEnvironmentLabel = (string) ($target_environment_label ?? '');
$isRemoteTarget = (bool) ($is_remote_target ?? false);
$requiresProductionConfirmation = (bool) ($requires_production_confirmation ?? false);
$selectedId = (string) ($selected_id ?? '');
$currentId = (string) ($current_id ?? '');
$savedId = (string) ($saved_id ?? '');
$defaultId = (string) ($default_id ?? '');
$readError = (string) ($read_error ?? '');
$e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$echoVersion = static function (string $file): string {
    $path = base_path('public/assets/vendor/echo-robo/' . $file);
    return is_file($path) ? ((string) filemtime($path) . '-' . (string) filesize($path)) : '1';
};
?>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Fantasia do Echo</h1>
      <div class="admin-page-subtitle">Escolha a roupa com que o Echo aparece na home deste ambiente.</div>
    </div>
    <div class="admin-page-actions">
      <div class="admin-chip<?= $isRemoteTarget ? ' border-cyan-500/30 text-cyan-200' : '' ?>">Ambiente alvo: <?= $e($targetEnvironmentLabel) ?></div>
    </div>
  </div>

  <?php if ($saved): ?>
    <section class="admin-panel border border-emerald-500/30">
      <div class="text-sm font-bold text-emerald-200">Fantasia salva para <?= $e($targetEnvironmentLabel) ?>.</div>
      <div class="mt-2 text-sm text-emerald-100/90">A home deste ambiente passa a usar esta fantasia na próxima visita.</div>
    </section>
  <?php endif; ?>

  <?php if ($readError !== ''): ?>
    <section class="admin-panel border border-rose-500/30">
      <div class="text-sm font-bold text-rose-200"><?= $e($readError) ?></div>
    </section>
  <?php endif; ?>

  <section class="admin-panel">
    <div class="text-sm text-slate-300">
      Em uso agora: <strong class="text-cyan-200"><?= $e((string) ($fantasias[$currentId]['label'] ?? $currentId)) ?></strong>
      <?php if ($savedId === ''): ?>
        <span class="text-slate-400">(nenhuma escolhida neste ambiente; vale o padrão)</span>
      <?php endif; ?>
    </div>
  </section>

  <form method="post" action="<?= $e(url('/admin/echo')) ?>" class="space-y-6">
    <?= Csrf::field() ?>
    <?php if (isset($errors['fantasia'])): ?>
      <div class="text-sm text-rose-300"><?= $e((string) $errors['fantasia']) ?></div>
    <?php endif; ?>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <?php foreach ($fantasias as $id => $fantasia): ?>
        <label class="admin-panel cursor-pointer flex flex-col items-center gap-3 border transition-colors <?= $id === $selectedId ? 'border-cyan-400/70' : 'border-transparent hover:border-slate-600' ?>" data-echo-card>
          <canvas class="echo-fantasia-preview" data-echo-preview data-pecas="<?= $e(implode(',', $fantasia['pecas'])) ?>" width="180" height="180" aria-hidden="true"></canvas>
          <span class="flex items-center gap-2 text-sm font-bold text-slate-100">
            <input type="radio" name="fantasia" value="<?= $e($id) ?>" <?= $id === $selectedId ? 'checked' : '' ?> class="accent-cyan-400">
            <?= $e($fantasia['label']) ?>
          </span>
          <span class="text-xs text-slate-400">
            <?= $id === $currentId ? 'Em uso' : '' ?><?= $id === $defaultId ? ($id === $currentId ? ' · padrão' : 'Padrão') : '' ?>
          </span>
        </label>
      <?php endforeach; ?>
    </div>

    <section class="admin-panel flex flex-wrap items-end justify-between gap-4">
      <div class="flex-1 min-w-[260px]">
        <?php if ($requiresProductionConfirmation): ?>
          <div class="rounded-2xl border border-amber-500/20 bg-amber-500/10 px-4 py-4 space-y-3">
            <div class="text-sm font-bold text-amber-200">Confirmacao obrigatoria para producao</div>
            <div class="text-sm text-slate-300">Digite <strong>PRODUCAO</strong> para confirmar esta alteracao no ambiente de producao.</div>
            <div class="max-w-sm">
              <input type="text" name="production_confirmation" value="" class="nerd-input w-full px-4 py-3 rounded-xl<?= isset($errors['production_confirmation']) ? ' border-rose-500/50' : '' ?>" placeholder="Digite PRODUCAO" autocomplete="off">
              <?php if (isset($errors['production_confirmation'])): ?><div class="mt-2 text-xs text-rose-300"><?= $e((string) $errors['production_confirmation']) ?></div><?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <button type="submit" class="admin-btn admin-btn-primary">Salvar fantasia</button>
    </section>
  </form>
</div>

<script src="<?= $e(url('/assets/vendor/echo-robo/robo-roupas.js?v=' . $echoVersion('robo-roupas.js'))) ?>" defer></script>
<script src="<?= $e(url('/assets/vendor/echo-robo/robo-roupas-echo.js?v=' . $echoVersion('robo-roupas-echo.js'))) ?>" defer></script>
<script src="<?= $e(url('/assets/vendor/echo-robo/robo-motor.js?v=' . $echoVersion('robo-motor.js'))) ?>" defer></script>
<script>
// Prévia: o Echo vestido de verdade, pelo mesmo motor da home.
document.addEventListener('DOMContentLoaded', function () {
  if (!window.RoboMotor || !window.RoboRoupas) return;
  var bots = [];
  document.querySelectorAll('[data-echo-preview]').forEach(function (canvas) {
    try {
      var robo = new window.RoboMotor.RoboBot(canvas, { theme: window.RoboMotor.THEMES.echo });
      robo.onSound = function () {};
      robo.setSize(180);
      var now = performance.now();
      (canvas.getAttribute('data-pecas') || '').split(',').filter(Boolean).forEach(function (peca) {
        if (window.RoboRoupas.ITEMS[peca]) robo.wardrobe.wear(peca, now);
      });
      bots.push({ robo: robo, ctx: canvas.getContext('2d') });
    } catch (error) {
      if (window.console) console.warn('[admin-echo] prévia indisponível:', error);
    }
  });
  document.querySelectorAll('[data-echo-card] input[type="radio"]').forEach(function (input) {
    input.addEventListener('change', function () {
      document.querySelectorAll('[data-echo-card]').forEach(function (card) {
        var on = card.contains(input) && input.checked;
        card.classList.toggle('border-cyan-400/70', on);
        card.classList.toggle('border-transparent', !on);
      });
    });
  });
  function frame() {
    bots.forEach(function (b) {
      b.ctx.save();
      b.ctx.setTransform(1, 0, 0, 1, 0, 0);
      b.ctx.clearRect(0, 0, b.ctx.canvas.width, b.ctx.canvas.height);
      b.ctx.restore();
      b.robo.update();
      b.robo.draw();
    });
    requestAnimationFrame(frame);
  }
  if (bots.length) requestAnimationFrame(frame);
});
</script>
