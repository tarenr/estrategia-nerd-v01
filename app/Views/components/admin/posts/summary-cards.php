<?php
declare(strict_types=1);

$summary = $summary ?? [
    'total_posts' => 0,
    'publicados' => 0,
    'rascunhos' => 0,
    'agendados' => 0,
    'destaques' => 0,
    'total_views' => 0,
    'total_curtidas' => 0,
    'total_comentarios' => 0,
];

$cards = [
    [
        'icon' => 'fa-newspaper',
        'label' => 'Total Posts',
        'value' => (int) $summary['total_posts'],
        'color' => '#60a5fa',
        'iconBg' => 'rgba(59,130,246,0.18)',
        'meta' => [
            'Publicados' => (int) $summary['publicados'],
            'Rascunhos' => (int) $summary['rascunhos'],
            'Agendados' => (int) $summary['agendados'],
        ],
    ],
    [
        'icon' => 'fa-star',
        'label' => 'Destaques',
        'value' => (int) ($summary['destaques'] ?? 0),
        'color' => '#facc15',
        'iconBg' => 'rgba(250,204,21,0.16)',
        'meta' => [
            'Total' => (int) $summary['total_posts'],
            'Ativos' => (int) $summary['publicados'],
        ],
    ],
    [
        'icon' => 'fa-chart-line',
        'label' => 'Views Totais',
        'value' => (int) $summary['total_views'],
        'color' => '#00d4ff',
        'iconBg' => 'rgba(0,212,255,0.16)',
        'meta' => [
            'Curtidas' => (int) $summary['total_curtidas'],
            'Comentarios' => (int) $summary['total_comentarios'],
        ],
    ],
    [
        'icon' => 'fa-bolt',
        'label' => 'Engajamento',
        'value' => (int) $summary['total_curtidas'],
        'color' => '#c084fc',
        'iconBg' => 'rgba(168,85,247,0.16)',
        'meta' => [
            'Curtidas' => (int) $summary['total_curtidas'],
            'Comentarios' => (int) $summary['total_comentarios'],
        ],
    ],
];
?>
<div class="grid md:grid-cols-2 xl:grid-cols-4 gap-6">
  <?php foreach ($cards as $c): ?>
    <div class="stat-card">
      <div class="stat-icon" style="background: <?= htmlspecialchars((string) $c['iconBg'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>; color: <?= htmlspecialchars((string) $c['color'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>;">
        <i class="fa-solid <?= htmlspecialchars((string) $c['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-hidden="true"></i>
      </div>
      <div class="stat-value neon-text" style="color: <?= htmlspecialchars((string) $c['color'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>;"><?= number_format((int) $c['value'], 0, ',', '.') ?></div>
      <div class="text-slate-400 text-sm mt-2"><?= htmlspecialchars((string) $c['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <div class="flex flex-wrap gap-2 text-xs mt-3">
        <?php foreach (($c['meta'] ?? []) as $metaLabel => $metaValue): ?>
          <span class="px-2 py-1 rounded-full" style="background: rgba(148,163,184,0.12); color: #cbd5e1;"><?= htmlspecialchars((string) $metaLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>: <?= number_format((int) $metaValue, 0, ',', '.') ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
