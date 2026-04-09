<?php
declare(strict_types=1);

$summary = $summary ?? [
    'total' => 0,
    'ativas' => 0,
    'inativas' => 0,
    'com_posts' => 0,
    'sem_posts' => 0,
    'total_posts_vinculados' => 0,
    'total_views' => 0,
    'cobertura_ativas' => 0.0,
    'cobertura_editorial' => 0.0,
    'media_posts_por_categoria' => 0.0,
    'media_views_por_categoria' => 0.0,
];

$fmtNumber = static function (int $value): string {
    return number_format($value, 0, ',', '.');
};

$fmtPercent = static function (float $value): string {
    $rounded = round($value, 1);
    $decimals = abs($rounded - round($rounded)) < 0.05 ? 0 : 1;
    return number_format($rounded, $decimals, ',', '.') . '%';
};

$fmtAverage = static function (float $value): string {
    $rounded = round($value, 1);
    $decimals = abs($rounded - round($rounded)) < 0.05 ? 0 : 1;
    return number_format($rounded, $decimals, ',', '.');
};

$cards = [
    [
        'icon' => 'fa-layer-group',
        'label' => 'Total categorias',
        'hint' => 'Base filtrada da organizacao editorial.',
        'value' => $fmtNumber((int) ($summary['total'] ?? 0)),
        'color' => '#60a5fa',
        'iconBg' => 'linear-gradient(135deg, rgba(96,165,250,.92), rgba(59,130,246,.92))',
        'support' => [
            ['label' => 'Ativas', 'value' => $fmtNumber((int) ($summary['ativas'] ?? 0))],
            ['label' => 'Inativas', 'value' => $fmtNumber((int) ($summary['inativas'] ?? 0))],
        ],
    ],
    [
        'icon' => 'fa-check-double',
        'label' => 'Ativas no seletor',
        'hint' => 'Disponiveis para formularios e novos posts.',
        'value' => $fmtNumber((int) ($summary['ativas'] ?? 0)),
        'color' => '#34d399',
        'iconBg' => 'linear-gradient(135deg, rgba(34,197,94,.88), rgba(6,182,212,.88))',
        'support' => [
            ['label' => 'Inativas', 'value' => $fmtNumber((int) ($summary['inativas'] ?? 0))],
            ['label' => 'Cobertura do cadastro', 'value' => $fmtPercent((float) ($summary['cobertura_ativas'] ?? 0.0)), 'accent' => '#34d399'],
        ],
    ],
    [
        'icon' => 'fa-newspaper',
        'label' => 'Com posts',
        'hint' => 'Categorias com conteudo vinculado.',
        'value' => $fmtNumber((int) ($summary['com_posts'] ?? 0)),
        'color' => '#facc15',
        'iconBg' => 'linear-gradient(135deg, rgba(234,179,8,.88), rgba(249,115,22,.88))',
        'support' => [
            ['label' => 'Sem posts', 'value' => $fmtNumber((int) ($summary['sem_posts'] ?? 0))],
            ['label' => 'Cobertura editorial', 'value' => $fmtPercent((float) ($summary['cobertura_editorial'] ?? 0.0)), 'accent' => '#facc15'],
        ],
    ],
    [
        'icon' => 'fa-chart-line',
        'label' => 'Views acumuladas',
        'hint' => 'Alcance dos posts ligados as categorias.',
        'value' => $fmtNumber((int) ($summary['total_views'] ?? 0)),
        'color' => '#00d4ff',
        'iconBg' => 'linear-gradient(135deg, rgba(34,211,238,.92), rgba(37,99,235,.92))',
        'support' => [
            ['label' => 'Posts vinculados', 'value' => $fmtNumber((int) ($summary['total_posts_vinculados'] ?? 0))],
            ['label' => 'Media por categoria', 'value' => $fmtAverage((float) ($summary['media_views_por_categoria'] ?? 0.0)), 'accent' => '#00d4ff'],
        ],
    ],
];
?>
<div class="categories-summary-grid">
  <?php foreach ($cards as $card): ?>
    <div class="stat-card stat-card-compact admin-summary-card categories-summary-card">
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
            <span class="stat-support-value"<?php if (!empty($line['accent'])): ?>style="color: <?= htmlspecialchars((string) $line['accent'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>;"<?php endif; ?>><?= htmlspecialchars((string) $line['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>