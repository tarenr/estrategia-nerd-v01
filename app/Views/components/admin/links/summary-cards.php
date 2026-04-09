<?php
declare(strict_types=1);

$summary = $summary ?? ['total' => 0];
$fmtNumber = static function (int $value): string { return number_format($value, 0, ',', '.'); };
$fmtFloat = static function (float $value): string { return number_format($value, 1, ',', '.'); };

$cards = [
    [
        'icon' => 'fa-link',
        'label' => 'Base total',
        'hint' => 'Leitura geral da base filtrada da Central Nerd.',
        'value' => $fmtNumber((int) ($summary['total'] ?? 0)),
        'color' => '#22d3ee',
        'iconBg' => 'linear-gradient(135deg, rgba(34,211,238,.92), rgba(37,99,235,.92))',
        'support' => [
            ['label' => 'Ativos', 'value' => $fmtNumber((int) ($summary['ativos'] ?? 0))],
            ['label' => 'Revisar', 'value' => $fmtNumber((int) ($summary['revisar'] ?? 0))],
            ['label' => 'Ocultos', 'value' => $fmtNumber((int) ($summary['ocultos'] ?? 0))],
            ['label' => 'Destaques', 'value' => $fmtNumber((int) ($summary['destaques'] ?? 0)), 'accent' => '#22d3ee'],
        ],
    ],
    [
        'icon' => 'fa-box-open',
        'label' => 'Produtos na central',
        'hint' => 'Catalogo principal com produtos e promocoes.',
        'value' => $fmtNumber((int) ($summary['produtos_total'] ?? 0)),
        'color' => '#38bdf8',
        'iconBg' => 'linear-gradient(135deg, rgba(56,189,248,.92), rgba(37,99,235,.92))',
        'support' => [
            ['label' => 'Promocoes', 'value' => $fmtNumber((int) ($summary['promocoes'] ?? 0))],
            ['label' => 'Catalogo', 'value' => $fmtNumber((int) ($summary['produtos_catalogo'] ?? 0))],
            ['label' => 'Grupos ativos', 'value' => $fmtNumber((int) ($summary['grupos_produtos'] ?? 0)), 'accent' => '#38bdf8'],
        ],
    ],
    [
        'icon' => 'fa-ticket',
        'label' => 'Cupons e desconto',
        'hint' => 'Base comercial com codigo e contexto prontos.',
        'value' => $fmtNumber((int) ($summary['cupons'] ?? 0)),
        'color' => '#f472b6',
        'iconBg' => 'linear-gradient(135deg, rgba(244,114,182,.88), rgba(168,85,247,.88))',
        'support' => [
            ['label' => 'Com codigo', 'value' => $fmtNumber((int) ($summary['cupons_codigo'] ?? 0))],
            ['label' => 'Com contexto', 'value' => $fmtNumber((int) ($summary['cupons_contexto'] ?? 0)), 'accent' => '#f472b6'],
        ],
    ],
    [
        'icon' => 'fa-layer-group',
        'label' => 'Conteudo e canais',
        'hint' => 'Entradas editoriais, redes e servicos da central.',
        'value' => $fmtNumber((int) ($summary['canais_total'] ?? 0)),
        'color' => '#34d399',
        'iconBg' => 'linear-gradient(135deg, rgba(52,211,153,.88), rgba(14,165,233,.88))',
        'support' => [
            ['label' => 'Conteudo', 'value' => $fmtNumber((int) ($summary['conteudo'] ?? 0))],
            ['label' => 'Redes', 'value' => $fmtNumber((int) ($summary['redes'] ?? 0))],
            ['label' => 'Servicos', 'value' => $fmtNumber((int) ($summary['servicos'] ?? 0)), 'accent' => '#34d399'],
        ],
    ],
    [
        'icon' => 'fa-arrow-pointer',
        'label' => 'Cliques rastreados',
        'hint' => 'Performance somada dos links filtrados.',
        'value' => $fmtNumber((int) ($summary['click_total'] ?? 0)),
        'color' => '#facc15',
        'iconBg' => 'linear-gradient(135deg, rgba(234,179,8,.88), rgba(249,115,22,.88))',
        'support' => [
            ['label' => 'Hoje', 'value' => $fmtNumber((int) ($summary['click_today'] ?? 0))],
            ['label' => 'Links com clique', 'value' => $fmtNumber((int) ($summary['links_com_clique'] ?? 0))],
            ['label' => 'Media por link', 'value' => $fmtFloat((float) ($summary['click_avg'] ?? 0.0)), 'accent' => '#facc15'],
        ],
    ],
];
?>
<section class="links-summary-grid">
  <?php foreach ($cards as $card): ?>
    <article class="stat-card stat-card-compact admin-summary-card links-summary-card">
      <div class="stat-icon" style="background: <?= htmlspecialchars((string) $card['iconBg'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>; color: <?= htmlspecialchars((string) $card['color'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>;">
        <i class="fa-solid <?= htmlspecialchars((string) $card['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-hidden="true"></i>
      </div>
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