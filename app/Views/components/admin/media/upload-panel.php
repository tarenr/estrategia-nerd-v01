<?php
declare(strict_types=1);

$errors = $errors ?? [];
?>

<section class="admin-panel">
  <div class="flex items-start justify-between gap-4 flex-wrap">
    <div>
      <div class="admin-panel-title"><i class="fa-solid fa-upload text-cyan-300"></i><span>Enviar imagem</span></div>
      <div class="admin-panel-subtitle">Aceita JPG, PNG, WEBP, GIF e SVG com ate 8 MB por arquivo.</div>
    </div>
    <div class="admin-chip">Destino: <code>public/uploads/media/ANO/MES</code></div>
  </div>

  <form method="POST" action="<?= url('/admin/midia/upload') ?>" enctype="multipart/form-data" class="mt-6 grid lg:grid-cols-[1fr_auto] gap-4 items-end">
    <?= \App\Support\Csrf::field() ?>
    <div>
      <label class="block text-sm font-bold text-slate-200 mb-2" for="arquivo">Arquivo</label>
      <input id="arquivo" name="arquivo" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,.svg" class="nerd-input w-full px-4 py-3 rounded-xl file:mr-4 file:border-0 file:bg-cyan-500/15 file:px-3 file:py-2 file:text-cyan-200 file:rounded-lg">
      <?php if (isset($errors['arquivo'])): ?>
        <div class="mt-2 text-sm text-rose-300 font-semibold"><?= htmlspecialchars((string) $errors['arquivo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <?php else: ?>
        <div class="mt-2 text-xs text-slate-500">Use nomes descritivos. O sistema gera uma versao segura do nome antes de salvar.</div>
      <?php endif; ?>
    </div>

    <button type="submit" class="admin-btn admin-btn-primary">Enviar</button>
  </form>
</section>