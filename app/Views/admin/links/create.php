<?php
declare(strict_types=1);

use App\Support\View;

$form = $form ?? [];
$errors = $errors ?? [];
$mediaItems = $media_items ?? [];
$currentFeatured = is_array($current_featured ?? null) ? $current_featured : null;
?>

<div class="max-w-6xl mx-auto px-4 py-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Criar Link</h1>
      <div class="admin-page-subtitle">Cadastre links para a bio, afiliados, redes sociais, ofertas e servicos.</div>
    </div>

    <div class="admin-page-actions">
      <a href="<?= url('/admin/links') ?>" class="admin-btn admin-btn-secondary">Voltar para links</a>
    </div>
  </div>

  <?php View::component('admin/links/form', [
      'mode' => 'create',
      'action' => url('/admin/criar-link'),
      'submitLabel' => 'Salvar link',
      'form' => $form,
      'errors' => $errors,
      'media_items' => $mediaItems,
      'current_featured' => $currentFeatured,
  ]); ?>
</div>
