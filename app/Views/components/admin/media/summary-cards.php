<?php
declare(strict_types=1);

$summary = $summary ?? [
    'total' => 0,
    'images' => 0,
    'others' => 0,
    'directories' => 0,
    'institutional' => 0,
    'managed_uploads' => 0,
    'post_media' => 0,
    'orphans' => 0,
    'coverage_posts' => 0.0,
    'orphan_rate' => 0.0,
    'size_label' => '0 B',
    'average_size_label' => '0 B',
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
        'icon' => 'fa-photo-film',
        'label' => 'Acervo total',
        'hint' => 'Todos os arquivos monitorados pela biblioteca.',
        'value' => $fmtNumber((int) ($summary['total'] ?? 0)),
        'color' => '#60a5fa',
        'iconBg' => 'linear-gradient(135deg, rgba(96,165,250,.92), rgba(59,130,246,.92))',
        'support' => [
            ['label' => 'Imagens', 'value' => $fmtNumber((int) ($summary['images'] ?? 0))],
            ['label' => 'Outros', 'value' => $fmtNumber((int) ($summary['others'] ?? 0))],
        ],
    ],
    [
        'icon' => 'fa-link',
        'label' => 'Ligadas ao portal',
        'hint' => 'Uploads usados em posts, links, configuracoes ou perfis.',
        'value' => $fmtNumber((int) ($summary['post_media'] ?? 0)),
        'color' => '#00d4ff',
        'iconBg' => 'linear-gradient(135deg, rgba(34,211,238,.92), rgba(37,99,235,.92))',
        'support' => [
            ['label' => 'Uploads gerenciados', 'value' => $fmtNumber((int) ($summary['managed_uploads'] ?? 0))],
            ['label' => 'Cobertura nos uploads', 'value' => $fmtPercent((float) ($summary['coverage_uploads'] ?? ($summary['coverage_posts'] ?? 0.0))), 'accent' => '#00d4ff'],
        ],
    ],
    [
        'icon' => 'fa-broom-ball',
        'label' => 'Orfas para revisar',
        'hint' => 'Arquivos sem uso detectado dentro dos uploads.',
        'value' => $fmtNumber((int) ($summary['orphans'] ?? 0)),
        'color' => '#facc15',
        'iconBg' => 'linear-gradient(135deg, rgba(234,179,8,.88), rgba(249,115,22,.88))',
        'support' => [
            ['label' => 'Pastas monitoradas', 'value' => $fmtNumber((int) ($summary['directories'] ?? 0))],
            ['label' => 'Taxa de limpeza', 'value' => $fmtPercent((float) ($summary['orphan_rate'] ?? 0.0)), 'accent' => '#facc15'],
        ],
    ],
    [
        'icon' => 'fa-hard-drive',
        'label' => 'Espaco usado',
        'hint' => 'Peso total da biblioteca acompanhada pelo admin.',
        'value' => (string) ($summary['size_label'] ?? '0 B'),
        'color' => '#34d399',
        'iconBg' => 'linear-gradient(135deg, rgba(34,197,94,.88), rgba(6,182,212,.88))',
        'support' => [
            ['label' => 'Media por arquivo', 'value' => (string) ($summary['average_size_label'] ?? '0 B')],
            ['label' => 'Institucional', 'value' => $fmtNumber((int) ($summary['institutional'] ?? 0)), 'accent' => '#34d399'],
        ],
    ],
];
?>
<div class="media-summary-grid">
  <?php foreach ($cards as $card): ?>
    <div class="stat-card stat-card-compact admin-summary-card media-summary-card">
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
