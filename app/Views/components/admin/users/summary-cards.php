<?php

declare(strict_types=1);

$summary = $summary ?? [];

$fmtNumber = static fn (int $value): string => number_format($value, 0, ',', '.');
$fmtPercent = static fn (float $value): string => number_format($value, 1, ',', '.') . '%';

$cards = [
    [
        'icon' => 'fa-users',
        'label' => 'Base total',
        'hint' => 'Equipe filtrada no admin.',
        'value' => $fmtNumber((int) ($summary['total'] ?? 0)),
        'color' => '#60a5fa',
        'iconBg' => 'linear-gradient(135deg, rgba(34,211,238,.92), rgba(37,99,235,.92))',
        'support' => [
            ['label' => 'Ativos', 'value' => $fmtNumber((int) ($summary['ativos'] ?? 0))],
            ['label' => 'Inativos', 'value' => $fmtNumber((int) ($summary['inativos'] ?? 0))],
        ],
    ],
    [
        'icon' => 'fa-user-check',
        'label' => 'Usuarios ativos',
        'hint' => 'Perfis aptos a entrar.',
        'value' => $fmtNumber((int) ($summary['ativos'] ?? 0)),
        'color' => '#34d399',
        'iconBg' => 'linear-gradient(135deg, rgba(34,197,94,.88), rgba(6,182,212,.88))',
        'support' => [
            ['label' => 'Cobertura', 'value' => $fmtPercent((float) ($summary['ativos_rate'] ?? 0.0))],
            ['label' => 'Nunca acessaram', 'value' => $fmtNumber((int) ($summary['nunca_acessaram'] ?? 0)), 'accent' => '#34d399'],
        ],
    ],
    [
        'icon' => 'fa-user-shield',
        'label' => 'Admins ativos',
        'hint' => 'Controle total do painel.',
        'value' => $fmtNumber((int) ($summary['admins'] ?? 0)),
        'color' => '#facc15',
        'iconBg' => 'linear-gradient(135deg, rgba(234,179,8,.88), rgba(249,115,22,.88))',
        'support' => [
            ['label' => 'Editores', 'value' => $fmtNumber((int) ($summary['editores'] ?? 0))],
            ['label' => 'Cobertura admin', 'value' => $fmtPercent((float) ($summary['admins_rate'] ?? 0.0)), 'accent' => '#facc15'],
        ],
    ],
    [
        'icon' => 'fa-camera-retro',
        'label' => 'Avatar com foto',
        'hint' => 'Perfis personalizados.',
        'value' => $fmtNumber((int) ($summary['com_foto'] ?? 0)),
        'color' => '#f472b6',
        'iconBg' => 'linear-gradient(135deg, rgba(244,63,94,.88), rgba(168,85,247,.88))',
        'support' => [
            ['label' => 'Com icone', 'value' => $fmtNumber((int) ($summary['com_icone'] ?? 0))],
            ['label' => 'Cobertura foto', 'value' => $fmtPercent((float) ($summary['foto_rate'] ?? 0.0)), 'accent' => '#f472b6'],
        ],
    ],
    [
        'icon' => 'fa-bolt',
        'label' => 'Acesso recente',
        'hint' => 'Ritmo operacional.',
        'value' => $fmtNumber((int) ($summary['acesso_hoje'] ?? 0)),
        'color' => '#38bdf8',
        'iconBg' => 'linear-gradient(135deg, rgba(59,130,246,.88), rgba(34,211,238,.88))',
        'support' => [
            ['label' => 'Ultimos 7 dias', 'value' => $fmtNumber((int) ($summary['acesso_7_dias'] ?? 0))],
            ['label' => 'Novos 30 dias', 'value' => $fmtNumber((int) ($summary['novos_30_dias'] ?? 0)), 'accent' => '#38bdf8'],
        ],
    ],
];
?>
<section class="users-summary-grid">
  <?php foreach ($cards as $card): ?>
    <article class="stat-card stat-card-compact admin-summary-card users-summary-card">
      <div class="stat-icon" style="background: <?= htmlspecialchars((string) $card['iconBg'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>; color: <?= htmlspecialchars((string) $card['color'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>;"><i class="fa-solid <?= htmlspecialchars((string) $card['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></i></div>
      <div class="stat-value neon-text" style="color: <?= htmlspecialchars((string) $card['color'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>;"><?= htmlspecialchars((string) $card['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
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