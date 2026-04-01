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
?>

<section class="admin-panel space-y-5">
  <div>
    <h2 class="font-orbitron text-lg font-black text-white">Categoria</h2>
    <div class="text-xs text-slate-400 mt-1">Escolha a categoria principal do post.</div>
  </div>

  <div class="space-y-3">
    <label for="categoria_post_id" class="block text-sm font-bold text-slate-200 mb-2">Categoria principal</label>
    <select id="categoria_post_id" name="categoria_post_id" class="nerd-input w-full px-4 py-3 rounded-xl" onchange="if (window.syncCategoriaIndicator) window.syncCategoriaIndicator()">
      <option value="0" data-category-color="#475569">Selecione uma categoria</option>
      <?php foreach ($categorias as $categoria): ?>
        <?php $categoriaId = (int) ($categoria['id'] ?? 0); ?>
        <option value="<?= $categoriaId ?>" data-category-color="<?= htmlspecialchars((string) ($categoria['cor'] ?? '#00d4ff'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= (int) ($form['categoria_post_id'] ?? 0) === $categoriaId ? 'selected' : '' ?>><?= htmlspecialchars((string) ($categoria['nome'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>

    <div id="categoria-indicator" class="inline-flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-900/50 px-3 py-2 text-xs text-slate-300">
      <span id="categoria-indicator-dot" class="inline-flex w-3 h-3 rounded-full" style="background: <?= htmlspecialchars($selectedCategoriaCor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></span>
      <span id="categoria-indicator-label"><?= htmlspecialchars($selectedCategoriaNome, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
    </div>

    <div class="text-xs text-slate-400">Quando a lista crescer, este campo continua mais facil de escanear e manter.</div>
    <?php if ($fieldError('categoria_post_id') !== ''): ?><div class="text-xs text-rose-300"><?= htmlspecialchars($fieldError('categoria_post_id'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
  </div>

  <script>
    (function () {
      function syncCategoriaIndicatorInline() {
        var select = document.getElementById('categoria_post_id');
        var dot = document.getElementById('categoria-indicator-dot');
        var label = document.getElementById('categoria-indicator-label');
        if (!select || !dot || !label) return;

        var option = select.options[select.selectedIndex] || null;
        var color = option && option.getAttribute('data-category-color') ? option.getAttribute('data-category-color') : '#475569';
        var text = option && option.value && option.value !== '0' ? option.textContent.trim() : 'Nenhuma categoria selecionada';

        dot.style.background = color || '#475569';
        label.textContent = text;
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', syncCategoriaIndicatorInline);
      } else {
        syncCategoriaIndicatorInline();
      }

      window.addEventListener('load', syncCategoriaIndicatorInline);
      window.addEventListener('pageshow', syncCategoriaIndicatorInline);
      window.setTimeout(syncCategoriaIndicatorInline, 0);
      window.setTimeout(syncCategoriaIndicatorInline, 150);

      var select = document.getElementById('categoria_post_id');
      if (select) {
        select.addEventListener('change', syncCategoriaIndicatorInline);
      }
    })();
  </script>
</section>
