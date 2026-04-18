<?php
declare(strict_types=1);

$errors = $errors ?? [];
$upload = $upload ?? ['accept' => '.jpg,.jpeg,.png,.webp,.gif,.svg,.mp3,.wav,.ogg,.m4a,.aac,.mp4,.webm,.mov,.ogv', 'max_size_label' => '8 MB para imagens, 25 MB para audios e 80 MB para videos', 'scope' => 'library', 'scope_label' => 'Biblioteca central', 'media_type' => '', 'media_type_label' => 'Midia', 'post_slug' => '', 'post_title' => '', 'destination_code' => 'public/uploads/media/{tipo}/ANO/MES', 'destination_label' => 'Biblioteca central por tipo'];
$filters = $filters ?? ['busca' => '', 'tipo' => '', 'estado' => ''];
$sort = (string) ($sort ?? 'data');
$dir = (string) ($dir ?? 'desc');
$pagination = $pagination ?? ['per_page' => 12];
?>

<section class="admin-panel media-upload-panel">
  <div class="flex items-start justify-between gap-4 flex-wrap">
    <div>
      <div class="admin-panel-title"><i class="fa-solid fa-upload text-cyan-300"></i><span>Enviar midia</span></div>
      <div class="admin-panel-subtitle">Aceita <?= htmlspecialchars(mb_strtolower((string) ($upload['media_type_label'] ?? 'midia')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> com limite de <?= htmlspecialchars((string) ($upload['max_size_label'] ?? '8 MB'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> por arquivo.</div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <div class="admin-chip">Contexto: <?= htmlspecialchars((string) ($upload['scope_label'] ?? 'Biblioteca central'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <div class="admin-chip">Destino: <code><?= htmlspecialchars((string) ($upload['destination_code'] ?? 'public/uploads/media/{tipo}/ANO/MES'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></code></div>
    </div>
  </div>

  <form method="POST" action="<?= url('/admin/midia/upload') ?>" enctype="multipart/form-data" class="media-upload-form mt-6">
    <?= \App\Support\Csrf::field() ?>
    <input type="hidden" name="busca" value="<?= htmlspecialchars((string) ($filters['busca'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="tipo" value="<?= htmlspecialchars((string) ($filters['tipo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="estado" value="<?= htmlspecialchars((string) ($filters['estado'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="per_page" value="<?= (int) ($pagination['per_page'] ?? 12) ?>">
    <input type="hidden" name="context" value="<?= htmlspecialchars((string) ($upload['scope'] ?? 'library'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="media_type" value="<?= htmlspecialchars((string) ($upload['media_type'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="post_slug" value="<?= htmlspecialchars((string) ($upload['post_slug'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="post_title" value="<?= htmlspecialchars((string) ($upload['post_title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

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
            aria-label="Selecionar ou arrastar midia"
          >
            <input id="arquivo" name="arquivo" type="file" accept="<?= htmlspecialchars((string) ($upload['accept'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="media-upload-input" data-media-file-input>

            <div class="media-upload-dropzone-inner">
              <div class="media-upload-dropzone-icon" aria-hidden="true">
                <i class="fa-solid fa-cloud-arrow-up"></i>
              </div>

              <div class="media-upload-dropzone-copy">
                <div class="media-upload-dropzone-title">Arraste sua midia aqui</div>
                <div class="media-upload-dropzone-subtitle">ou clique para selecionar um arquivo do computador</div>
              </div>

              <div class="media-upload-dropzone-file" data-media-file-label>Nenhum arquivo selecionado</div>
            </div>
          </label>

          <button type="submit" class="admin-btn admin-btn-primary media-upload-submit">Enviar <?= htmlspecialchars((string) ($upload['media_type'] ?? '') !== '' ? mb_strtolower((string) ($upload['media_type_label'] ?? 'midia')) : 'midia', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
        </div>

        <?php if (isset($errors['arquivo'])): ?>
          <div class="mt-2 text-sm text-rose-300 font-semibold"><?= htmlspecialchars((string) $errors['arquivo'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <?php else: ?>
          <div class="mt-2 text-xs text-slate-500">Use nomes descritivos. O sistema padroniza o nome, identifica o tipo, respeita o contexto e organiza a pasta automaticamente.</div>
        <?php endif; ?>
      </div>
    </div>
  </form>
</section>