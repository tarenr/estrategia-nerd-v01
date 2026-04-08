<?php
declare(strict_types=1);

$summary = $summary ?? [
    'total_posts' => 0,
    'publicados' => 0,
    'rascunhos' => 0,
    'agendados' => 0,
    'destaques' => 0,
    'destaques_publicados' => 0,
    'destaque_cobertura_publicados' => 0.0,
    'total_views' => 0,
    'views_por_post' => 0,
    'views_por_publicado' => 0,
    'total_curtidas' => 0,
    'total_comentarios' => 0,
    'taxa_engajamento' => 0.0,
];

$fmtNumber = static function (int $value): string {
    return number_format($value, 0, ',', '.');
};

$fmtPercent = static function (float $value): string {
    $rounded = round($value, 1);
    $decimals = abs($rounded - round($rounded)) < 0.05 ? 0 : 1;
    return number_format($rounded, $decimals, ',', '.') . '%';
};

$cards = [
    [
        'icon' => 'fa-newspaper',
        'label' => 'Total posts',
        'hint' => 'Acervo filtrado da central',
        'value' => $fmtNumber((int) ($summary['total_posts'] ?? 0)),
        'color' => '#60a5fa',
        'iconBg' => 'rgba(59,130,246,0.18)',
        'chips' => [
            ['label' => 'Publicados', 'value' => $fmtNumber((int) ($summary['publicados'] ?? 0)), 'class' => 'status-publicado'],
            ['label' => 'Rascunhos', 'value' => $fmtNumber((int) ($summary['rascunhos'] ?? 0)), 'class' => 'status-rascunho'],
            ['label' => 'Agendados', 'value' => $fmtNumber((int) ($summary['agendados'] ?? 0)), 'class' => 'status-agendado'],
        ],
    ],
    [
        'icon' => 'fa-star',
        'label' => 'Destaques ativos',
        'hint' => 'Posts em destaque ja publicados',
        'value' => $fmtNumber((int) ($summary['destaques_publicados'] ?? 0)),
        'color' => '#facc15',
        'iconBg' => 'rgba(250,204,21,0.16)',
        'support' => [
            ['label' => 'Marcados no filtro', 'value' => $fmtNumber((int) ($summary['destaques'] ?? 0))],
            ['label' => 'Cobertura editorial', 'value' => $fmtPercent((float) ($summary['destaque_cobertura_publicados'] ?? 0.0))],
        ],
    ],
    [
        'icon' => 'fa-chart-line',
        'label' => 'Views totais',
        'hint' => 'Alcance acumulado dos posts',
        'value' => $fmtNumber((int) ($summary['total_views'] ?? 0)),
        'color' => '#00d4ff',
        'iconBg' => 'rgba(0,212,255,0.16)',
        'support' => [
            ['label' => 'Media por post', 'value' => $fmtNumber((int) ($summary['views_por_post'] ?? 0))],
            ['label' => 'Media por publicado', 'value' => $fmtNumber((int) ($summary['views_por_publicado'] ?? 0))],
        ],
    ],
    [
        'icon' => 'fa-bolt',
        'label' => 'Taxa de engajamento',
        'hint' => 'Curtidas e comentarios sobre views',
        'value' => $fmtPercent((float) ($summary['taxa_engajamento'] ?? 0.0)),
        'color' => '#f472ff',
        'iconBg' => 'rgba(168,85,247,0.16)',
        'support' => [
            ['label' => 'Curtidas', 'value' => $fmtNumber((int) ($summary['total_curtidas'] ?? 0))],
            ['label' => 'Comentarios', 'value' => $fmtNumber((int) ($summary['total_comentarios'] ?? 0))],
        ],
    ],
];
?>
<div class="posts-summary-grid">
  <?php foreach ($cards as $card): ?>
    <div class="stat-card stat-card-compact posts-summary-card">
      <div
        class="stat-icon"
        style="background: <?= htmlspecialchars((string) $card['iconBg'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>; color: <?= htmlspecialchars((string) $card['color'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>;"
      >
        <i class="fa-solid <?= htmlspecialchars((string) $card['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-hidden="true"></i>
      </div>

      <div class="stat-value neon-text" style="color: <?= htmlspecialchars((string) $card['color'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>;">
        <?= htmlspecialchars((string) $card['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
      </div>

      <div class="stat-label"><?= htmlspecialchars((string) $card['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <div class="posts-summary-card__hint"><?= htmlspecialchars((string) $card['hint'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>

      <?php if (!empty($card['chips'])): ?>
        <div class="stat-chip-row">
          <?php foreach ($card['chips'] as $chip): ?>
            <span class="status-badge <?= htmlspecialchars((string) ($chip['class'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
              <?= htmlspecialchars((string) $chip['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>: <?= htmlspecialchars((string) $chip['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($card['support'])): ?>
        <div class="stat-support">
          <?php foreach ($card['support'] as $line): ?>
            <div class="stat-support-line">
              <span class="stat-support-label"><?= htmlspecialchars((string) $line['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              <span class="stat-support-value"><?= htmlspecialchars((string) $line['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
