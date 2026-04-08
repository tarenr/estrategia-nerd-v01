<?php
declare(strict_types=1);

$errors = $errors ?? [];
?>

<section class="admin-panel media-upload-panel">
  <div class="flex items-start justify-between gap-4 flex-wrap">
    <div>
      <div class="admin-panel-title"><i class="fa-solid fa-upload text-cyan-300"></i><span>Enviar imagem</span></div>
      <div class="admin-panel-subtitle">Aceita JPG, PNG, WEBP, GIF e SVG com ate 8 MB por arquivo.</div>
    </div>
    <div class="admin-chip">Destino: <code>public/uploads/media/ANO/MES</code></div>
  </div>

  <form method="POST" action="<?= url('/admin/midia/upload') ?>" enctype="multipart/form-data" class="media-upload-form mt-6">
    <?= \App\Support\Csrf::field() ?>

    <div class="media-upload-main">
      <div class="media-upload-field">
        <label class="block text-sm font-bold text-slate-200 mb-2" for="arquivo">Arquivo</label>

        <div class="media-upload-row">
          <label
            for="arquivo"
            class="media-upload-dropzone"
            data-media-dropzone
            tabindex="0"
            role="button"
            aria-controls="arquivo"
            aria-label="Selecionar ou arrastar imagem"
          >
            <input id="arquivo" name="arquivo" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,.svg" class="media-upload-input" data-media-file-input>

            <div class="media-upload-dropzone-inner">
              <div class="media-upload-dropzone-icon" aria-hidden="true">
                <i class="fa-solid fa-cloud-arrow-up"></i>
              </div>

              <div class="media-upload-dropzone-copy">
                <div class="media-upload-dropzone-title">Arraste sua imagem aqui</div>
                <div class="media-upload-dropzone-subtitle">ou clique para selecionar um arquivo do computador</div>
              </div>

              <div class="media-upload-dropzone-file" data-media-file-label>Nenhum arquivo selecionado</div>
            </div>
          </label>

          <button type="submit" class="admin-btn admin-btn-primary media-upload-submit">Enviar</button>
        </div>

        <?php if (isset($errors['arquivo'])): ?>
          <div class="mt-2 text-sm text-rose-300 font-semibold"><?= htmlspecialchars((string) $errors['arquivo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <?php else: ?>
          <div class="mt-2 text-xs text-slate-500">Use nomes descritivos. O sistema gera uma versao segura do nome antes de salvar.</div>
        <?php endif; ?>
      </div>
    </div>
  </form>
</section>