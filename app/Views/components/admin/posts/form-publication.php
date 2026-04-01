<?php
declare(strict_types=1);

$fieldError = $fieldError ?? static fn (string $key): string => '';
$form = $form ?? [];
?>

<section class="admin-panel space-y-5">
  <div>
    <h2 class="font-orbitron text-lg font-black text-white">Publicacao</h2>
    <div class="text-xs text-slate-400 mt-1">Defina o comportamento do post antes de salvar.</div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label for="status" class="block text-sm font-bold text-slate-200 mb-2">Status</label>
      <select id="status" name="status" class="nerd-input w-full px-4 py-3 rounded-xl">
        <?php foreach (['rascunho' => 'Rascunho', 'publicado' => 'Publicado', 'agendado' => 'Agendado'] as $value => $label): ?>
          <option value="<?= $value ?>" <?= (string) ($form['status'] ?? 'rascunho') === $value ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($fieldError('status') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
    </div>

    <div>
      <label for="tempo_leitura" class="block text-sm font-bold text-slate-200 mb-2">Tempo de leitura (min)</label>
      <input id="tempo_leitura" name="tempo_leitura" type="number" min="1" max="120" value="<?= (int) ($form['tempo_leitura'] ?? 5) ?>" class="nerd-input w-full px-4 py-3 rounded-xl">
    </div>
  </div>

  <label class="flex items-center gap-3 rounded-xl border border-slate-800 bg-slate-900/50 px-4 py-3 cursor-pointer">
    <input type="checkbox" name="destaque" value="1" <?= (int) ($form['destaque'] ?? 0) === 1 ? 'checked' : '' ?>>
    <span>
      <span class="block text-sm font-bold text-slate-200">Marcar como destaque</span>
      <span class="block text-xs text-slate-400">Posts em destaque ganham mais destaque nas listagens.</span>
    </span>
  </label>
</section>
