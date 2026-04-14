<?php
declare(strict_types=1);

use App\Support\View;

$form = $form ?? [];
$errors = $errors ?? [];
$link = $link ?? [];
$mediaItems = $media_items ?? [];
$currentFeatured = is_array($current_featured ?? null) ? $current_featured : null;
?>

<div class="max-w-6xl mx-auto px-4 py-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Editar Link</h1>
      <div class="admin-page-subtitle">Atualize destino, status, destaque e dados da pagina de bio sem perder a organizacao da lista.</div>
    </div>

    <div class="admin-page-actions">
      <div class="admin-chip">ID: <?= (int) ($form['id'] ?? 0) ?></div>
      <div class="admin-chip">Tipo: <?= htmlspecialchars((string) ($link['tipo'] ?? 'link'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <a href="<?= url('/admin/links') ?>" class="admin-btn admin-btn-secondary">Voltar para links</a>
    </div>
  </div>

  <?php View::component('admin/links/form', [
      'mode' => 'edit',
      'action' => url('/admin/editar-link?id=' . (int) ($form['id'] ?? 0)),
      'submitLabel' => 'Salvar alteracoes',
      'form' => $form,
      'errors' => $errors,
      'media_items' => $mediaItems,
      'current_featured' => $currentFeatured,
  ]); ?>
</div>
