<?php
declare(strict_types=1);

$checklist = is_array($checklist ?? null) ? $checklist : [];
$items = is_array($checklist['items'] ?? null) ? $checklist['items'] : [];
$stats = is_array($checklist['stats'] ?? null) ? $checklist['stats'] : ['success' => 0, 'warning' => 0, 'error' => 0];
$overall = (string) ($checklist['status'] ?? 'success');
$headline = trim((string) ($checklist['headline'] ?? 'Checklist editorial pronto.'));
$runtime = is_array($checklist['runtime'] ?? null) ? $checklist['runtime'] : [];

$panelClass = match ($overall) {
    'error' => 'is-error',
    'warning' => 'is-warning',
    default => 'is-success',
};

$statusLabel = match ($overall) {
    'error' => 'Bloqueado',
    'warning' => 'Atencao',
    default => 'Pronto',
};

$statusHint = match ($overall) {
    'error' => 'Erros criticos exigem correcao antes de publicar ou agendar.',
    'warning' => 'O post esta utilizavel, mas ainda merece uma revisao final.',
    default => 'Tudo alinhado para seguir com a publicacao.',
};
?>

<section
  id="postPublicationChecklist"
  class="admin-panel post-checklist-panel <?= $panelClass ?>"
  data-checklist-root
  data-initial-status="<?= htmlspecialchars($overall, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
>
  <div class="post-checklist-head">
    <div>
      <div class="post-checklist-kicker">Checklist de publicacao</div>
      <h2 id="postChecklistHeadline" class="font-orbitron text-lg font-black text-white"><?= htmlspecialchars($headline, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
      <div class="post-checklist-subtitle">Revise os itens abaixo antes de salvar ou publicar para evitar erro editorial e midia quebrada.</div>
    </div>
    <div class="post-checklist-state-shell">
      <div id="postChecklistState" class="post-checklist-state"><?= htmlspecialchars($statusLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <div id="postChecklistHint" class="post-checklist-hint"><?= htmlspecialchars($statusHint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    </div>
  </div>

  <div class="post-checklist-tags" aria-label="Tipos de verificacao">
    <span class="post-checklist-tag">Atualizacao ao vivo</span>
    <span class="post-checklist-tag">Editorial</span>
    <span class="post-checklist-tag is-technical">Tecnico</span>
  </div>

  <div class="post-checklist-stats">
    <div class="post-checklist-stat is-success">
      <strong id="postChecklistOkCount"><?= (int) ($stats['success'] ?? 0) ?></strong>
      <span>OK</span>
    </div>
    <div class="post-checklist-stat is-warning">
      <strong id="postChecklistWarningCount"><?= (int) ($stats['warning'] ?? 0) ?></strong>
      <span>Alertas</span>
    </div>
    <div class="post-checklist-stat is-error">
      <strong id="postChecklistErrorCount"><?= (int) ($stats['error'] ?? 0) ?></strong>
      <span>Bloqueios</span>
    </div>
  </div>

  <div id="postChecklistList" class="post-checklist-list">
    <?php foreach ($items as $item): ?>
      <?php
        if (!is_array($item)) {
            continue;
        }
        $itemStatus = (string) ($item['status'] ?? 'success');
        $itemClass = match ($itemStatus) {
            'error' => 'is-error',
            'warning' => 'is-warning',
            default => 'is-success',
        };
        $itemIcon = match ($itemStatus) {
            'error' => '!',
            'warning' => '!',
            default => 'OK',
        };
        $itemGroup = (string) ($item['group'] ?? 'editorial');
        $itemGroupLabel = $itemGroup === 'tecnico' ? 'Tecnico' : 'Editorial';
      ?>
      <article class="post-checklist-item <?= $itemClass ?>">
        <div class="post-checklist-item-icon" aria-hidden="true"><?= $itemIcon ?></div>
        <div class="post-checklist-item-copy">
          <div class="post-checklist-item-meta">
            <span class="post-checklist-item-group <?= $itemGroup === 'tecnico' ? 'is-technical' : '' ?>"><?= htmlspecialchars($itemGroupLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          </div>
          <div class="post-checklist-item-title"><?= htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="post-checklist-item-text"><?= htmlspecialchars((string) ($item['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<script id="postChecklistRuntimeData" type="application/json"><?= json_encode($runtime, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
