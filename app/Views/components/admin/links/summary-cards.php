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
        'iconBg' => 'rgba(34,211,238,0.16)',
        'chips' => [
            ['label' => 'Ativos', 'value' => $fmtNumber((int) ($summary['ativos'] ?? 0)), 'class' => 'status-publicado'],
            ['label' => 'Revisar', 'value' => $fmtNumber((int) ($summary['revisar'] ?? 0)), 'class' => 'status-rascunho'],
        ],
        'support' => [
            ['label' => 'Ocultos', 'value' => $fmtNumber((int) ($summary['ocultos'] ?? 0))],
            ['label' => 'Destaques', 'value' => $fmtNumber((int) ($summary['destaques'] ?? 0)), 'accent' => '#22d3ee'],
        ],
    ],
    [
        'icon' => 'fa-box-open',
        'label' => 'Produtos na central',
        'hint' => 'Catalogo principal com produtos e promocoes mapeadas.',
        'value' => $fmtNumber((int) ($summary['produtos_total'] ?? 0)),
        'color' => '#38bdf8',
        'iconBg' => 'rgba(56,189,248,0.16)',
        'chips' => [
            ['label' => 'Promocoes', 'value' => $fmtNumber((int) ($summary['promocoes'] ?? 0)), 'class' => 'status-agendado'],
            ['label' => 'Catalogo', 'value' => $fmtNumber((int) ($summary['produtos_catalogo'] ?? 0)), 'class' => 'status-publicado'],
        ],
        'support' => [
            ['label' => 'Grupos ativos', 'value' => $fmtNumber((int) ($summary['grupos_produtos'] ?? 0))],
            ['label' => 'Links em destaque', 'value' => $fmtNumber((int) ($summary['destaques'] ?? 0)), 'accent' => '#38bdf8'],
        ],
    ],
    [
        'icon' => 'fa-ticket',
        'label' => 'Cupons e desconto',
        'hint' => 'Base comercial com codigo e contexto prontos para uso.',
        'value' => $fmtNumber((int) ($summary['cupons'] ?? 0)),
        'color' => '#f472b6',
        'iconBg' => 'rgba(244,114,182,0.16)',
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
        'iconBg' => 'rgba(52,211,153,0.16)',
        'chips' => [
            ['label' => 'Conteudo', 'value' => $fmtNumber((int) ($summary['conteudo'] ?? 0)), 'class' => 'status-publicado'],
            ['label' => 'Redes', 'value' => $fmtNumber((int) ($summary['redes'] ?? 0)), 'class' => 'status-agendado'],
        ],
        'support' => [
            ['label' => 'Servicos', 'value' => $fmtNumber((int) ($summary['servicos'] ?? 0))],
            ['label' => 'Base auxiliar', 'value' => $fmtNumber((int) ($summary['canais_total'] ?? 0)), 'accent' => '#34d399'],
        ],
    ],
    [
        'icon' => 'fa-arrow-pointer',
        'label' => 'Cliques rastreados',
        'hint' => 'Performance somada dos links filtrados nesta listagem.',
        'value' => $fmtNumber((int) ($summary['click_total'] ?? 0)),
        'color' => '#facc15',
        'iconBg' => 'rgba(250,204,21,0.16)',
        'support' => [
            ['label' => 'Hoje', 'value' => $fmtNumber((int) ($summary['click_today'] ?? 0))],
            ['label' => 'Links com clique', 'value' => $fmtNumber((int) ($summary['links_com_clique'] ?? 0)), 'accent' => '#facc15'],
        ],
        'footer' => 'Media por link clicado: ' . $fmtFloat((float) ($summary['click_avg'] ?? 0.0)),
    ],
];
?>
<section class="links-summary-grid">
  <?php foreach ($cards as $card): ?>
    <article class="stat-card stat-card-compact links-summary-card">
      <div class="stat-icon" style="background: <?= htmlspecialchars((string) $card['iconBg'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>; color: <?= htmlspecialchars((string) $card['color'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>;">
        <i class="fa-solid <?= htmlspecialchars((string) $card['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-hidden="true"></i>
      </div>
      <div class="stat-value neon-text" style="color: <?= htmlspecialchars((string) $card['color'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>;"><?= htmlspecialchars((string) $card['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <div class="stat-label"><?= htmlspecialchars((string) $card['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <div class="links-summary-card__hint"><?= htmlspecialchars((string) $card['hint'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <?php if (!empty($card['chips'])): ?>
        <div class="stat-chip-row">
          <?php foreach ($card['chips'] as $chip): ?>
            <span class="status-badge <?= htmlspecialchars((string) ($chip['class'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars((string) $chip['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>: <?= htmlspecialchars((string) $chip['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($card['support'])): ?>
        <div class="stat-support">
          <?php foreach ($card['support'] as $line): ?>
            <div class="stat-support-line">
              <span class="stat-support-label"><?= htmlspecialchars((string) $line['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              <span class="stat-support-value"<?php if (!empty($line['accent'])): ?> style="color: <?= htmlspecialchars((string) $line['accent'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>;"<?php endif; ?>><?= htmlspecialchars((string) $line['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($card['footer'])): ?>
        <div class="links-summary-card__footer"><?= htmlspecialchars((string) $card['footer'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</section>