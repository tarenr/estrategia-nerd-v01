<?php
declare(strict_types=1);

use App\Support\View;

$form = $form ?? [];
$errors = $errors ?? [];
$categoria = $categoria ?? [];
?>

<div class="max-w-6xl mx-auto px-4 py-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Editar Categoria</h1>
      <div class="admin-page-subtitle">Atualize nome, cor, ordem e disponibilidade sem quebrar o vinculo com os posts.</div>
    </div>

    <div class="admin-page-actions">
      <div class="admin-chip">ID: <?= (int) ($form['id'] ?? 0) ?></div>
      <div class="admin-chip">Posts: <?= number_format((int) ($categoria['total_posts'] ?? 0), 0, ',', '.') ?></div>
      <a href="<?= url('/admin/categorias') ?>" class="admin-btn admin-btn-secondary">Voltar para categorias</a>
    </div>
  </div>

  <?php View::component('admin/categories/form', [
      'mode' => 'edit',
      'action' => url('/admin/editar-categoria?id=' . (int) ($form['id'] ?? 0)),
      'submitLabel' => 'Salvar alteracoes',
      'form' => $form,
      'errors' => $errors,
  ]); ?>
</div>
