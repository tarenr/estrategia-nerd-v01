<?php
declare(strict_types=1);

use App\Support\Csrf;

$mode = (string) ($mode ?? 'create');
$action = (string) ($action ?? '#');
$submitLabel = (string) ($submitLabel ?? 'Salvar categoria');
$form = $form ?? [];
$errors = $errors ?? [];

$fieldError = static fn (string $key): string => (string) ($errors[$key] ?? '');
$selectedColor = htmlspecialchars((string) ($form['cor'] ?? '#00d4ff'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>

<form method="POST" action="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="space-y-6" novalidate>
  <?= Csrf::field() ?>
  <?php if ((int) ($form['id'] ?? 0) > 0): ?>
    <input type="hidden" name="id" value="<?= (int) ($form['id'] ?? 0) ?>">
  <?php endif; ?>

  <?php if ($errors !== []): ?>
    <section class="admin-panel border border-rose-500/30">
      <h2 class="font-orbitron text-lg font-black text-rose-300">Ajustes necessarios</h2>
      <div class="mt-3 text-sm text-rose-100 space-y-1">
        <?php foreach ($errors as $message): ?>
          <div><?= htmlspecialchars((string) $message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="admin-panel">
    <div class="flex items-center justify-between gap-4 flex-wrap mb-5">
      <div>
        <h2 class="font-orbitron text-lg font-black text-white">Dados da categoria</h2>
        <div class="text-xs text-slate-400 mt-1">Defina nome, slug, cor, ordem e disponibilidade no admin.</div>
      </div>
      <div class="admin-chip">Modo: <?= $mode === 'edit' ? 'edicao' : 'criacao' ?></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <div>
        <label for="nome" class="block text-sm font-bold text-slate-200 mb-2">Nome</label>
        <input id="nome" name="nome" type="text" value="<?= htmlspecialchars((string) ($form['nome'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Ex.: Gadgets">
        <?php if ($fieldError('nome') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('nome'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>

      <div>
        <label for="slug" class="block text-sm font-bold text-slate-200 mb-2">Slug</label>
        <div class="flex gap-2">
          <input id="slug" name="slug" type="text" value="<?= htmlspecialchars((string) ($form['slug'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="gadgets">
          <button type="button" class="admin-btn admin-btn-secondary" id="gerarCategoriaSlug">Gerar</button>
        </div>
        <?php if ($fieldError('slug') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('slug'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>

      <div>
        <label for="cor" class="block text-sm font-bold text-slate-200 mb-2">Cor</label>
        <div class="flex items-center gap-3">
          <input id="cor_picker" type="color" value="<?= $selectedColor ?>" class="w-14 h-12 rounded-2xl border border-slate-700 bg-slate-900 cursor-pointer">
          <input id="cor" name="cor" type="text" value="<?= $selectedColor ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="#00d4ff">
          <span id="categoriaColorPreview" class="inline-flex w-12 h-12 rounded-2xl border border-slate-700" style="background: <?= $selectedColor ?>"></span>
        </div>
        <?php if ($fieldError('cor') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('cor'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>

      <div>
        <label for="ordem" class="block text-sm font-bold text-slate-200 mb-2">Ordem</label>
        <input id="ordem" name="ordem" type="number" min="0" value="<?= (int) ($form['ordem'] ?? 0) ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="0">
      </div>
    </div>

    <div class="mt-5">
      <label class="inline-flex items-center gap-3 text-sm text-slate-200">
        <input type="hidden" name="ativo" value="0">
        <input type="checkbox" name="ativo" value="1" class="rounded border-slate-700 bg-slate-900" <?= (int) ($form['ativo'] ?? 1) === 1 ? 'checked' : '' ?>>
        Categoria ativa no seletor de posts
      </label>
    </div>
  </section>

  <section class="admin-panel flex flex-wrap items-center justify-between gap-3">
    <div class="text-xs text-slate-400">Categorias inativas continuam preservadas e podem ser reativadas depois.</div>
    <div class="flex flex-wrap gap-2">
      <a href="<?= url('/admin/categorias') ?>" class="admin-btn admin-btn-secondary">Cancelar</a>
      <button type="submit" class="admin-btn admin-btn-primary"><?= htmlspecialchars($submitLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
    </div>
  </section>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var nome = document.getElementById('nome');
  var slug = document.getElementById('slug');
  var gerar = document.getElementById('gerarCategoriaSlug');
  var cor = document.getElementById('cor');
  var picker = document.getElementById('cor_picker');
  var preview = document.getElementById('categoriaColorPreview');

  if (gerar && nome && slug) {
    gerar.addEventListener('click', function () {
      var value = nome.value || '';
      value = value.toLowerCase();
      try {
        value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      } catch (e) {}
      value = value.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
      slug.value = value;
    });
  }

  if (cor && preview) {
    var syncColor = function () {
      var value = cor.value || '#00d4ff';
      preview.style.background = value;
      if (picker && /^#[0-9a-fA-F]{6}$/.test(value)) {
        picker.value = value;
      }
    };
    cor.addEventListener('input', syncColor);
    syncColor();
  }

  if (picker && cor) {
    picker.addEventListener('input', function () {
      cor.value = picker.value || '#00d4ff';
      if (preview) {
        preview.style.background = cor.value;
      }
    });
  }
});
</script>
