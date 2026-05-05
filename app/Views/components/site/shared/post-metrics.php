<?php
$views = max(0, (int) ($views ?? 0));
$likes = max(0, (int) ($likes ?? 0));
$comments = max(0, (int) ($comments ?? 0));

$formatMetric = static fn (int $value): string => number_format($value, 0, ',', '.');
?>

<div class="site-post-metrics" aria-label="Metricas do post">
  <span class="site-post-metric" title="Visualizacoes">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/>
      <circle cx="12" cy="12" r="3"/>
    </svg>
    <?= htmlspecialchars($formatMetric($views), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
  </span>
  <span class="site-post-metric" title="Curtidas">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M7 10v10M7 10l4.2-6.4c.7-1.1 2.4-.6 2.4.7v4h4.2c1.4 0 2.5 1.2 2.2 2.6l-1.2 6.7A3 3 0 0 1 15.9 20H7M4 10h3v10H4a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1Z"/>
    </svg>
    <?= htmlspecialchars($formatMetric($likes), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
  </span>
  <span class="site-post-metric" title="Comentarios">
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M21 11.5a7.5 7.5 0 0 1-7.5 7.5H8l-5 3 1.7-4.5A7.5 7.5 0 1 1 21 11.5Z"/>
    </svg>
    <?= htmlspecialchars($formatMetric($comments), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
  </span>
</div>
