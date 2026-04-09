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
        'label' => 'Total de posts',
        'hint' => 'Acervo filtrado da central editorial.',
        'value' => $fmtNumber((int) ($summary['total_posts'] ?? 0)),
        'color' => '#60a5fa',
        'iconBg' => 'linear-gradient(135deg, rgba(96,165,250,.92), rgba(59,130,246,.92))',
        'support' => [
            ['label' => 'Publicados', 'value' => $fmtNumber((int) ($summary['publicados'] ?? 0))],
            ['label' => 'Rascunhos', 'value' => $fmtNumber((int) ($summary['rascunhos'] ?? 0))],
            ['label' => 'Agendados', 'value' => $fmtNumber((int) ($summary['agendados'] ?? 0))],
        ],
    ],
    [
        'icon' => 'fa-star',
        'label' => 'Destaques ativos',
        'hint' => 'Posts em destaque ja publicados.',
        'value' => $fmtNumber((int) ($summary['destaques_publicados'] ?? 0)),
        'color' => '#facc15',
        'iconBg' => 'linear-gradient(135deg, rgba(234,179,8,.88), rgba(249,115,22,.88))',
        'support' => [
            ['label' => 'Marcados no filtro', 'value' => $fmtNumber((int) ($summary['destaques'] ?? 0))],
            ['label' => 'Cobertura editorial', 'value' => $fmtPercent((float) ($summary['destaque_cobertura_publicados'] ?? 0.0)), 'accent' => '#facc15'],
        ],
    ],
    [
        'icon' => 'fa-chart-line',
        'label' => 'Views totais',
        'hint' => 'Alcance acumulado dos posts.',
        'value' => $fmtNumber((int) ($summary['total_views'] ?? 0)),
        'color' => '#00d4ff',
        'iconBg' => 'linear-gradient(135deg, rgba(34,211,238,.92), rgba(37,99,235,.92))',
        'support' => [
            ['label' => 'Media por post', 'value' => $fmtNumber((int) ($summary['views_por_post'] ?? 0))],
            ['label' => 'Media por publicado', 'value' => $fmtNumber((int) ($summary['views_por_publicado'] ?? 0)), 'accent' => '#00d4ff'],
        ],
    ],
    [
        'icon' => 'fa-bolt',
        'label' => 'Taxa de engajamento',
        'hint' => 'Curtidas e comentarios sobre views.',
        'value' => $fmtPercent((float) ($summary['taxa_engajamento'] ?? 0.0)),
        'color' => '#f472ff',
        'iconBg' => 'linear-gradient(135deg, rgba(168,85,247,.88), rgba(236,72,153,.88))',
        'support' => [
            ['label' => 'Curtidas', 'value' => $fmtNumber((int) ($summary['total_curtidas'] ?? 0))],
            ['label' => 'Comentarios', 'value' => $fmtNumber((int) ($summary['total_comentarios'] ?? 0)), 'accent' => '#f472ff'],
        ],
    ],
];
?>
<div class="posts-summary-grid">
  <?php foreach ($cards as $card): ?>
    <div class="stat-card stat-card-compact admin-summary-card posts-summary-card">
      <div class="stat-icon" style="background: <?= htmlspecialchars((string) $card['iconBg'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>; color: <?= htmlspecialchars((string) $card['color'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>;">
        <i class="fa-solid <?= htmlspecialchars((string) $card['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-hidden="true"></i>
      </div>
      <div class="stat-value neon-text" style="color: <?= htmlspecialchars((string) $card['color'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>;">
        <?= htmlspecialchars((string) $card['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
      </div>
      <div class="stat-label"><?= htmlspecialchars((string) $card['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <div class="admin-summary-card__hint"><?= htmlspecialchars((string) $card['hint'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <div class="stat-support">
        <?php foreach ($card['support'] as $line): ?>
          <div class="stat-support-line">
            <span class="stat-support-label"><?= htmlspecialchars((string) $line['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            <span class="stat-support-value"<?php if (!empty($line['accent'])): ?> style="color: <?= htmlspecialchars((string) $line['accent'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>;"<?php endif; ?>><?= htmlspecialchars((string) $line['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>