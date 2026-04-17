<?php
declare(strict_types=1);

$fieldError = $fieldError ?? static fn (string $key): string => '';
$form = $form ?? [];
$nextStepOptions = is_array($next_step_options ?? null) ? $next_step_options : [];
$supportsNextStep = (bool) ($supports_next_step ?? true);
$currentStatus = (string) ($form['status'] ?? 'rascunho');
$currentTempo = (int) ($form['tempo_leitura'] ?? 5);
$currentNextStep = max(0, (int) ($form['proximo_post_id'] ?? 0));
$isDestaque = (int) ($form['destaque'] ?? 0) === 1;
$statusMeta = [
    'rascunho' => ['label' => 'Rascunho', 'hint' => 'Ainda nao aparece no publico.'],
    'publicado' => ['label' => 'Publicado', 'hint' => 'Ja pode entrar nas listagens e no post.'],
    'agendado' => ['label' => 'Agendado', 'hint' => 'Vai ao ar na data definida acima.'],
];
$tempoHint = $currentTempo <= 4
    ? 'Leitura curta e direta.'
    : ($currentTempo <= 8 ? 'Faixa boa para post padrao.' : 'Leitura mais longa e aprofundada.');
?>

<section class="admin-panel post-side-panel post-publication-panel space-y-5">
  <div>
    <h2 class="font-orbitron text-lg font-black text-white">Publicacao</h2>
    <div class="text-xs text-slate-400 mt-1">Defina o comportamento do post antes de salvar.</div>
  </div>

  <div class="post-publication-grid">
    <div class="post-publication-field">
      <div class="post-side-field-head">
        <label for="status" class="block text-sm font-bold text-slate-200">Status</label>
        <span class="post-side-field-meta"><?= htmlspecialchars((string) ($statusMeta[$currentStatus]['hint'] ?? 'Escolha como o post sera tratado.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
      </div>
      <select id="status" name="status" class="nerd-input w-full px-4 py-3 rounded-xl">
        <?php foreach (['rascunho' => 'Rascunho', 'publicado' => 'Publicado', 'agendado' => 'Agendado'] as $value => $label): ?>
          <option value="<?= $value ?>" <?= $currentStatus === $value ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($fieldError('status') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
    </div>

    <div class="post-publication-field">
      <div class="post-side-field-head">
        <label for="tempo_leitura" class="block text-sm font-bold text-slate-200">Tempo de leitura (min)</label>
        <span class="post-side-field-meta"><?= htmlspecialchars($tempoHint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
      </div>
      <input id="tempo_leitura" name="tempo_leitura" type="number" min="1" max="120" value="<?= $currentTempo ?>" class="nerd-input w-full px-4 py-3 rounded-xl">
    </div>
  </div>

  <div class="post-publication-field">
    <div class="post-side-field-head">
      <label for="proximo_post_id" class="block text-sm font-bold text-slate-200">Proximo passo (post recomendado)</label>
      <span class="post-side-field-meta">
        <?= $supportsNextStep
          ? 'Opcional: substitui os botoes padrao por um CTA direto para outro post publicado.'
          : 'Indisponivel neste banco: execute a migracao da coluna proximo_post_id para habilitar.' ?>
      </span>
    </div>
    <select id="proximo_post_id" name="proximo_post_id" class="nerd-input w-full px-4 py-3 rounded-xl" <?= $supportsNextStep ? '' : 'disabled' ?>>
      <option value="0">Sem post recomendado</option>
      <?php foreach ($nextStepOptions as $option): ?>
        <?php
          $optionId = (int) ($option['id'] ?? 0);
          $optionTitle = trim((string) ($option['titulo'] ?? ''));
          $optionDate = trim((string) ($option['data_publicacao'] ?? ''));
          $optionSlug = trim((string) ($option['slug'] ?? ''));
          $optionDateLabel = '';
          $optionTimestamp = $optionDate !== '' ? strtotime($optionDate) : false;
          if ($optionTimestamp !== false) {
              $optionDateLabel = ' (' . date('d/m/Y', $optionTimestamp) . ')';
          }
          if ($optionId <= 0 || $optionTitle === '') {
              continue;
          }
        ?>
        <option value="<?= $optionId ?>" <?= $currentNextStep === $optionId ? 'selected' : '' ?>>
          <?= htmlspecialchars($optionTitle . $optionDateLabel . ($optionSlug !== '' ? ' - ' . $optionSlug : ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?php if (!$supportsNextStep): ?>
      <input type="hidden" name="proximo_post_id" value="0">
    <?php endif; ?>
    <?php if ($fieldError('proximo_post_id') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('proximo_post_id'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
  </div>

  <label class="post-destaque-toggle<?= $isDestaque ? ' is-active' : '' ?>">
    <input type="checkbox" name="destaque" value="1" <?= $isDestaque ? 'checked' : '' ?>>
    <span class="post-destaque-toggle-mark" aria-hidden="true"></span>
    <span class="post-destaque-toggle-copy">
      <span class="post-destaque-toggle-title">Marcar como destaque</span>
      <span class="post-destaque-toggle-text">Posts destacados ganham mais presenca nas listagens e no topo dos blocos editoriais.</span>
    </span>
    <span class="post-destaque-toggle-state"><?= $isDestaque ? 'Ativo' : 'Desligado' ?></span>
  </label>
</section>
