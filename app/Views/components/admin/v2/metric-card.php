<?php

declare(strict_types=1);

$label = (string) ($label ?? 'Indicador');
$value = (string) ($value ?? '-');
$hint = (string) ($hint ?? '');
$icon = (string) ($icon ?? 'fa-solid fa-circle-info');
$tone = (string) ($tone ?? 'info');
$href = (string) ($href ?? '');
$support = is_array($support ?? null) ? $support : [];

$tones = [
    'info' => ['color' => '#38bdf8', 'bg' => 'linear-gradient(135deg, rgba(34,211,238,.92), rgba(37,99,235,.92))'],
    'success' => ['color' => '#34d399', 'bg' => 'linear-gradient(135deg, rgba(34,197,94,.88), rgba(6,182,212,.88))'],
    'warning' => ['color' => '#facc15', 'bg' => 'linear-gradient(135deg, rgba(250,204,21,.9), rgba(245,158,11,.88))'],
    'danger' => ['color' => '#fb7185', 'bg' => 'linear-gradient(135deg, rgba(244,63,94,.9), rgba(190,18,60,.88))'],
    'neutral' => ['color' => '#94a3b8', 'bg' => 'linear-gradient(135deg, rgba(100,116,139,.86), rgba(15,23,42,.94))'],
];
$theme = $tones[$tone] ?? $tones['info'];
$tag = $href !== '' ? 'a' : 'article';
$hrefAttribute = $href !== '' ? ' href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '';
$cardClass = 'stat-card stat-card-compact admin-summary-card';
if ($href !== '') {
    $cardClass .= ' transition hover:-translate-y-1 hover:border-cyan-400/55 hover:shadow-[0_18px_44px_rgba(34,211,238,0.14)]';
}
?>
<<?= $tag ?><?= $hrefAttribute ?> class="<?= htmlspecialchars($cardClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="padding: 1.25rem; min-height: 12.5rem; border-color: rgba(34,211,238,.22); background: linear-gradient(145deg, rgba(15,23,42,.96), rgba(2,6,23,.86));">
  <div class="stat-icon" style="width: 3.15rem !important; height: 3.15rem !important; margin-bottom: 1rem !important; background: <?= htmlspecialchars($theme['bg'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>; color: <?= htmlspecialchars($theme['color'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>; box-shadow: 0 16px 34px rgba(2,6,23,.28);">
    <i class="<?= htmlspecialchars($icon, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-hidden="true"></i>
  </div>
  <div class="stat-info">
    <div class="stat-label" style="font-size: 0.8rem; margin-top: 0.15rem; color: #e2e8f0; letter-spacing: .04em; text-transform: uppercase;"><?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <div class="stat-value" style="margin-top: 0.5rem; font-size: 1.12rem; line-height: 1.22; color: #fff; word-break: break-word;"><?= htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <?php if ($hint !== ''): ?>
      <div class="admin-summary-card__hint" style="margin-top: .65rem; min-height: auto; color: #94a3b8; font-size: .76rem; line-height: 1.45;"><?= htmlspecialchars($hint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($support !== []): ?>
      <div class="mt-4 space-y-2 border-t border-slate-800/80 pt-3">
        <?php foreach ($support as $line): ?>
          <div class="flex items-start justify-between gap-3 text-xs">
            <span class="text-slate-500"><?= htmlspecialchars((string) ($line['label'] ?? 'Info'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            <strong class="text-right text-slate-200"><?= htmlspecialchars((string) ($line['value'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</<?= $tag ?>>
