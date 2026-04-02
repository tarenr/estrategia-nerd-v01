<?php
declare(strict_types=1);

use App\Support\View;

$form = $form ?? [];
$errors = $errors ?? [];
?>

<div class="max-w-6xl mx-auto px-4 py-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Criar Categoria</h1>
      <div class="admin-page-subtitle">Adicione novas categorias para o fluxo editorial e os formularios de posts.</div>
    </div>

    <div class="admin-page-actions">
      <a href="<?= url('/admin/categorias') ?>" class="admin-btn admin-btn-secondary">Voltar para categorias</a>
    </div>
  </div>

  <?php View::component('admin/categories/form', [
      'mode' => 'create',
      'action' => url('/admin/criar-categoria'),
      'submitLabel' => 'Salvar categoria',
      'form' => $form,
      'errors' => $errors,
  ]); ?>
</div>
