<?php
declare(strict_types=1);

$summary = $summary ?? [
    'total' => 0,
    'pendentes' => 0,
    'aprovados' => 0,
    'reprovados' => 0,
    'spam' => 0,
    'respondidos' => 0,
    'comentarios_raiz' => 0,
    'respostas' => 0,
    'moderados' => 0,
    'bloqueados' => 0,
    'sem_resposta' => 0,
    'fila_percentual' => 0.0,
    'taxa_aprovacao' => 0.0,
    'pressao_defensiva' => 0.0,
    'cobertura_resposta' => 0.0,
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
        'icon' => 'fa-comments',
        'label' => 'Total comentarios',
        'hint' => 'Interacoes filtradas na moderacao.',
        'value' => $fmtNumber((int) ($summary['total'] ?? 0)),
        'color' => '#60a5fa',
        'iconBg' => 'linear-gradient(135deg, rgba(96,165,250,.92), rgba(59,130,246,.92))',
        'support' => [
            ['label' => 'Pendentes', 'value' => $fmtNumber((int) ($summary['pendentes'] ?? 0))],
            ['label' => 'Spam', 'value' => $fmtNumber((int) ($summary['spam'] ?? 0))],
        ],
    ],
    [
        'icon' => 'fa-hourglass-half',
        'label' => 'Fila pendente',
        'hint' => 'Aguardando decisao da moderacao.',
        'value' => $fmtNumber((int) ($summary['pendentes'] ?? 0)),
        'color' => '#facc15',
        'iconBg' => 'linear-gradient(135deg, rgba(234,179,8,.88), rgba(249,115,22,.88))',
        'support' => [
            ['label' => 'Ja moderados', 'value' => $fmtNumber((int) ($summary['moderados'] ?? 0))],
            ['label' => 'Peso da fila', 'value' => $fmtPercent((float) ($summary['fila_percentual'] ?? 0.0)), 'accent' => '#facc15'],
        ],
    ],
    [
        'icon' => 'fa-circle-check',
        'label' => 'Aprovados',
        'hint' => 'Liberados para exibicao publica.',
        'value' => $fmtNumber((int) ($summary['aprovados'] ?? 0)),
        'color' => '#34d399',
        'iconBg' => 'linear-gradient(135deg, rgba(34,197,94,.88), rgba(6,182,212,.88))',
        'support' => [
            ['label' => 'Reprovados', 'value' => $fmtNumber((int) ($summary['reprovados'] ?? 0))],
            ['label' => 'Taxa de aprovacao', 'value' => $fmtPercent((float) ($summary['taxa_aprovacao'] ?? 0.0)), 'accent' => '#34d399'],
        ],
    ],
    [
        'icon' => 'fa-shield-halved',
        'label' => 'Spam e bloqueios',
        'hint' => 'Itens retirados ou desviados da conversa.',
        'value' => $fmtNumber((int) ($summary['spam'] ?? 0)),
        'color' => '#c084fc',
        'iconBg' => 'linear-gradient(135deg, rgba(168,85,247,.88), rgba(236,72,153,.88))',
        'support' => [
            ['label' => 'Bloqueados no total', 'value' => $fmtNumber((int) ($summary['bloqueados'] ?? 0))],
            ['label' => 'Pressao defensiva', 'value' => $fmtPercent((float) ($summary['pressao_defensiva'] ?? 0.0)), 'accent' => '#c084fc'],
        ],
    ],
    [
        'icon' => 'fa-reply-all',
        'label' => 'Threads respondidas',
        'hint' => 'Comentarios raiz que ja receberam retorno.',
        'value' => $fmtNumber((int) ($summary['respondidos'] ?? 0)),
        'color' => '#00d4ff',
        'iconBg' => 'linear-gradient(135deg, rgba(34,211,238,.92), rgba(37,99,235,.92))',
        'support' => [
            ['label' => 'Sem resposta', 'value' => $fmtNumber((int) ($summary['sem_resposta'] ?? 0))],
            ['label' => 'Cobertura de resposta', 'value' => $fmtPercent((float) ($summary['cobertura_resposta'] ?? 0.0)), 'accent' => '#00d4ff'],
        ],
    ],
];
?>
<div class="comments-summary-grid">
  <?php foreach ($cards as $card): ?>
    <div class="stat-card stat-card-compact admin-summary-card comments-summary-card">
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