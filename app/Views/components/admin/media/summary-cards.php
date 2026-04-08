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
        'hint' => 'Todos os arquivos monitorados pela biblioteca',
        'value' => $fmtNumber((int) ($summary['total'] ?? 0)),
        'color' => '#60a5fa',
        'iconBg' => 'rgba(59,130,246,0.18)',
        'chips' => [
            ['label' => 'Imagens', 'value' => $fmtNumber((int) ($summary['images'] ?? 0)), 'class' => 'status-publicado'],
            ['label' => 'Outros', 'value' => $fmtNumber((int) ($summary['others'] ?? 0)), 'class' => 'status-rascunho'],
        ],
    ],
    [
        'icon' => 'fa-link',
        'label' => 'Ligadas aos posts',
        'hint' => 'Uploads que ja aparecem em capas, thumbs ou conteudo',
        'value' => $fmtNumber((int) ($summary['post_media'] ?? 0)),
        'color' => '#00d4ff',
        'iconBg' => 'rgba(0,212,255,0.16)',
        'support' => [
            ['label' => 'Uploads gerenciados', 'value' => $fmtNumber((int) ($summary['managed_uploads'] ?? 0))],
            ['label' => 'Cobertura nos posts', 'value' => $fmtPercent((float) ($summary['coverage_posts'] ?? 0.0)), 'accent' => '#00d4ff'],
        ],
    ],
    [
        'icon' => 'fa-broom-ball',
        'label' => 'Orfas para revisar',
        'hint' => 'Arquivos sem uso detectado dentro dos uploads',
        'value' => $fmtNumber((int) ($summary['orphans'] ?? 0)),
        'color' => '#facc15',
        'iconBg' => 'rgba(250,204,21,0.16)',
        'support' => [
            ['label' => 'Pastas monitoradas', 'value' => $fmtNumber((int) ($summary['directories'] ?? 0))],
            ['label' => 'Taxa de limpeza', 'value' => $fmtPercent((float) ($summary['orphan_rate'] ?? 0.0)), 'accent' => '#facc15'],
        ],
    ],
    [
        'icon' => 'fa-hard-drive',
        'label' => 'Espaco usado',
        'hint' => 'Peso total da biblioteca acompanhada pelo admin',
        'value' => (string) ($summary['size_label'] ?? '0 B'),
        'color' => '#34d399',
        'iconBg' => 'rgba(16,185,129,0.16)',
        'support' => [
            ['label' => 'Media por arquivo', 'value' => (string) ($summary['average_size_label'] ?? '0 B')],
            ['label' => 'Institucional', 'value' => $fmtNumber((int) ($summary['institutional'] ?? 0)), 'accent' => '#34d399'],
        ],
    ],
];
?>
<div class="media-summary-grid">
  <?php foreach ($cards as $card): ?>
    <div class="stat-card stat-card-compact media-summary-card">
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
      <div class="media-summary-card__hint"><?= htmlspecialchars((string) $card['hint'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>

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
              <span
                class="stat-support-value"
                <?php if (!empty($line['accent'])): ?>style="color: <?= htmlspecialchars((string) $line['accent'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>;"<?php endif; ?>
              >
                <?= htmlspecialchars((string) $line['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
              </span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>