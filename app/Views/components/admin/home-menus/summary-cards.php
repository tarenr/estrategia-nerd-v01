<?php

declare(strict_types=1);

$summary = $summary ?? ['total' => 0, 'home' => 0, 'menu' => 0, 'public_active' => 0, 'public_disabled' => 0, 'custom_labels' => 0];
$fmt = static fn (int $value): string => number_format($value, 0, ',', '.');

$cards = [
    [
        'icon' => 'fa-sitemap',
        'label' => 'Base mapeada',
        'hint' => 'Modulos rastreados pela camada publica.',
        'value' => $fmt((int) ($summary['total'] ?? 0)),
        'color' => '#22d3ee',
        'iconBg' => 'linear-gradient(135deg, rgba(34,211,238,.92), rgba(37,99,235,.92))',
        'support' => [
            ['label' => 'Rotulos customizados', 'value' => $fmt((int) ($summary['custom_labels'] ?? 0))],
            ['label' => 'Publicos ativos', 'value' => $fmt((int) ($summary['public_active'] ?? 0)), 'accent' => '#22d3ee'],
        ],
    ],
    [
        'icon' => 'fa-house-signal',
        'label' => 'Na home',
        'hint' => 'Blocos visiveis na pagina inicial.',
        'value' => $fmt((int) ($summary['home'] ?? 0)),
        'color' => '#38bdf8',
        'iconBg' => 'linear-gradient(135deg, rgba(56,189,248,.92), rgba(37,99,235,.92))',
        'support' => [
            ['label' => 'Blocos visiveis', 'value' => $fmt((int) ($summary['home'] ?? 0))],
            ['label' => 'Fora da home', 'value' => $fmt(max(0, (int) ($summary['total'] ?? 0) - (int) ($summary['home'] ?? 0))), 'accent' => '#38bdf8'],
        ],
    ],
    [
        'icon' => 'fa-bars',
        'label' => 'No menu',
        'hint' => 'Itens expostos no topo e no menu mobile.',
        'value' => $fmt((int) ($summary['menu'] ?? 0)),
        'color' => '#a78bfa',
        'iconBg' => 'linear-gradient(135deg, rgba(167,139,250,.88), rgba(168,85,247,.88))',
        'support' => [
            ['label' => 'Itens visiveis', 'value' => $fmt((int) ($summary['menu'] ?? 0))],
            ['label' => 'Ocultos no topo', 'value' => $fmt(max(0, (int) ($summary['total'] ?? 0) - (int) ($summary['menu'] ?? 0))), 'accent' => '#a78bfa'],
        ],
    ],
    [
        'icon' => 'fa-toggle-on',
        'label' => 'Modulos publicos',
        'hint' => 'Controles gerais de disponibilidade externa.',
        'value' => $fmt((int) ($summary['public_active'] ?? 0)),
        'color' => '#34d399',
        'iconBg' => 'linear-gradient(135deg, rgba(52,211,153,.88), rgba(14,165,233,.88))',
        'support' => [
            ['label' => 'Ativos', 'value' => $fmt((int) ($summary['public_active'] ?? 0))],
            ['label' => 'Desligados', 'value' => $fmt((int) ($summary['public_disabled'] ?? 0)), 'accent' => '#34d399'],
        ],
    ],
];
?>
<section class="home-menus-summary-grid">
  <?php foreach ($cards as $card): ?>
    <article class="stat-card stat-card-compact admin-summary-card home-menus-summary-card">
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
    </article>
  <?php endforeach; ?>
</section>