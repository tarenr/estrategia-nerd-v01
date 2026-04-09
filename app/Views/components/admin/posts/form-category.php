<?php
declare(strict_types=1);

$fieldError = $fieldError ?? static fn (string $key): string => '';
$form = $form ?? [];
$categorias = $categorias ?? [];
$selectedCategoriaNome = 'Nenhuma categoria selecionada';
$selectedCategoriaCor = '#475569';
foreach ($categorias as $categoria) {
    $categoriaId = (int) ($categoria['id'] ?? 0);
    if ((int) ($form['categoria_post_id'] ?? 0) === $categoriaId) {
        $selectedCategoriaNome = (string) ($categoria['nome'] ?? $selectedCategoriaNome);
        $selectedCategoriaCor = (string) ($categoria['cor'] ?? $selectedCategoriaCor);
        break;
    }
}
$hasCategoria = (int) ($form['categoria_post_id'] ?? 0) > 0;
?>

<section class="admin-panel post-side-panel post-category-panel space-y-5">
  <div>
    <h2 class="font-orbitron text-lg font-black text-white">Categoria</h2>
    <div class="text-xs text-slate-400 mt-1">Escolha a categoria principal do post.</div>
  </div>

  <div class="space-y-4">
    <div class="post-side-field-head">
      <label for="categoria_post_id" class="block text-sm font-bold text-slate-200">Categoria principal</label>
      <span class="post-side-field-meta">Use a categoria para organizar listagens, dashboards e filtros do admin.</span>
    </div>

    <select id="categoria_post_id" name="categoria_post_id" class="nerd-input w-full px-4 py-3 rounded-xl" onchange="if (window.syncCategoriaIndicator) window.syncCategoriaIndicator()">
      <option value="0" data-category-color="#475569">Selecione uma categoria</option>
      <?php foreach ($categorias as $categoria): ?>
        <?php $categoriaId = (int) ($categoria['id'] ?? 0); ?>
        <option value="<?= $categoriaId ?>" data-category-color="<?= htmlspecialchars((string) ($categoria['cor'] ?? '#00d4ff'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= (int) ($form['categoria_post_id'] ?? 0) === $categoriaId ? 'selected' : '' ?>><?= htmlspecialchars((string) ($categoria['nome'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>

    <div id="categoria-indicator" class="post-category-indicator<?= $hasCategoria ? ' is-active' : '' ?>">
      <div class="post-category-indicator-main">
        <span id="categoria-indicator-dot" class="post-category-indicator-dot" style="background: <?= htmlspecialchars($selectedCategoriaCor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></span>
        <div>
          <div class="post-category-indicator-caption">Categoria atual</div>
          <div id="categoria-indicator-label" class="post-category-indicator-label"><?= htmlspecialchars($selectedCategoriaNome, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </div>
      </div>
      <div id="categoria-indicator-state" class="post-category-indicator-state"><?= $hasCategoria ? 'Selecionada' : 'Pendente' ?></div>
    </div>

    <div class="text-xs text-slate-400">Quando a lista crescer, esse indicador deixa a leitura da categoria muito mais rapida.</div>
    <?php if ($fieldError('categoria_post_id') !== ''): ?><div class="text-xs text-rose-300"><?= htmlspecialchars($fieldError('categoria_post_id'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
  </div>
</section>