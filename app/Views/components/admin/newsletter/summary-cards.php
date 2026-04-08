<?php
declare(strict_types=1);

$summary = $summary ?? [
    'total' => 0,
    'ativos' => 0,
    'inativos' => 0,
    'desinscritos' => 0,
    'hoje' => 0,
    'last7' => 0,
    'active_last7' => 0,
    'ativos_rate' => 0.0,
    'inativos_rate' => 0.0,
    'desinscritos_rate' => 0.0,
    'daily_avg_7' => 0.0,
];
?>

<section class="newsletter-summary-grid">
  <article class="stat-card stat-card-compact">
    <div class="stat-icon" style="background:linear-gradient(135deg, rgba(34,211,238,.92), rgba(37,99,235,.92))"><i class="fa-solid fa-envelope-open-text"></i></div>
    <div class="stat-value text-white"><?= number_format((int) ($summary['total'] ?? 0), 0, ',', '.') ?></div>
    <div class="stat-label">Base total</div>
    <div class="newsletter-summary-card__hint">Leitura geral dos contatos filtrados na central.</div>
    <div class="stat-chip-row">
      <span class="status-badge status-publicado">Ativos: <?= number_format((int) ($summary['ativos'] ?? 0), 0, ',', '.') ?></span>
      <span class="status-badge status-rascunho">Inativos: <?= number_format((int) ($summary['inativos'] ?? 0), 0, ',', '.') ?></span>
    </div>
  </article>

  <article class="stat-card stat-card-compact">
    <div class="stat-icon" style="background:linear-gradient(135deg, rgba(34,197,94,.88), rgba(6,182,212,.88))"><i class="fa-solid fa-circle-check"></i></div>
    <div class="stat-value text-white"><?= number_format((int) ($summary['ativos'] ?? 0), 0, ',', '.') ?></div>
    <div class="stat-label">Ativos na base</div>
    <div class="newsletter-summary-card__hint">Contatos prontos para seguir na lista principal.</div>
    <div class="stat-support">
      <div class="stat-support-line"><span class="stat-support-label">Cobertura</span><span class="stat-support-value text-cyan-300"><?= number_format((float) ($summary['ativos_rate'] ?? 0), 1, ',', '.') ?>%</span></div>
      <div class="stat-support-line"><span class="stat-support-label">Novos ativos em 7 dias</span><span class="stat-support-value text-emerald-300"><?= number_format((int) ($summary['active_last7'] ?? 0), 0, ',', '.') ?></span></div>
    </div>
  </article>

  <article class="stat-card stat-card-compact">
    <div class="stat-icon" style="background:linear-gradient(135deg, rgba(234,179,8,.88), rgba(249,115,22,.88))"><i class="fa-solid fa-triangle-exclamation"></i></div>
    <div class="stat-value text-white"><?= number_format((int) ($summary['inativos'] ?? 0), 0, ',', '.') ?></div>
    <div class="stat-label">Inativos para revisar</div>
    <div class="newsletter-summary-card__hint">Contatos fora do fluxo principal que pedem triagem.</div>
    <div class="stat-support">
      <div class="stat-support-line"><span class="stat-support-label">Peso na base</span><span class="stat-support-value text-amber-300"><?= number_format((float) ($summary['inativos_rate'] ?? 0), 1, ',', '.') ?>%</span></div>
      <div class="stat-support-line"><span class="stat-support-label">Desinscritos</span><span class="stat-support-value text-rose-300"><?= number_format((int) ($summary['desinscritos'] ?? 0), 0, ',', '.') ?></span></div>
    </div>
  </article>

  <article class="stat-card stat-card-compact">
    <div class="stat-icon" style="background:linear-gradient(135deg, rgba(244,63,94,.88), rgba(168,85,247,.88))"><i class="fa-solid fa-user-minus"></i></div>
    <div class="stat-value text-white"><?= number_format((int) ($summary['desinscritos'] ?? 0), 0, ',', '.') ?></div>
    <div class="stat-label">Saidas da base</div>
    <div class="newsletter-summary-card__hint">Indicador de desgaste ou limpeza de contatos.</div>
    <div class="stat-support">
      <div class="stat-support-line"><span class="stat-support-label">Taxa da base</span><span class="stat-support-value text-rose-300"><?= number_format((float) ($summary['desinscritos_rate'] ?? 0), 1, ',', '.') ?>%</span></div>
      <div class="stat-support-line"><span class="stat-support-label">Base restante</span><span class="stat-support-value text-cyan-300"><?= number_format(max(0, (int) (($summary['total'] ?? 0) - ($summary['desinscritos'] ?? 0))), 0, ',', '.') ?></span></div>
    </div>
  </article>

  <article class="stat-card stat-card-compact">
    <div class="stat-icon" style="background:linear-gradient(135deg, rgba(59,130,246,.88), rgba(34,211,238,.88))"><i class="fa-solid fa-bolt"></i></div>
    <div class="stat-value text-white"><?= number_format((int) ($summary['hoje'] ?? 0), 0, ',', '.') ?></div>
    <div class="stat-label">Captação recente</div>
    <div class="newsletter-summary-card__hint">Ritmo de entrada para leitura operacional rápida.</div>
    <div class="stat-support">
      <div class="stat-support-line"><span class="stat-support-label">Ultimos 7 dias</span><span class="stat-support-value text-cyan-300"><?= number_format((int) ($summary['last7'] ?? 0), 0, ',', '.') ?></span></div>
      <div class="stat-support-line"><span class="stat-support-label">Media diaria</span><span class="stat-support-value text-slate-200"><?= number_format((float) ($summary['daily_avg_7'] ?? 0), 1, ',', '.') ?></span></div>
    </div>
  </article>
</section>