<?php
declare(strict_types=1);

use App\Support\View;

$form = $form ?? [];
$errors = $errors ?? [];
$categorias = $categorias ?? [];
$nextStepOptions = $next_step_options ?? [];
$mediaItems = $media_items ?? [];
$criarPostJsPath = dirname(__DIR__, 3) . '/public/assets/js/criar-post.js';
$criarPostJsVersion = is_file($criarPostJsPath) ? (string) filemtime($criarPostJsPath) : '1';
$adminHtmlEditorJsPath = dirname(__DIR__, 3) . '/public/assets/js/admin-post-html-editor.js';
$adminHtmlEditorJsVersion = is_file($adminHtmlEditorJsPath) ? (string) filemtime($adminHtmlEditorJsPath) : '1';
?>

<div class="max-w-7xl mx-auto px-4 py-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Criar Post</h1>
      <div class="admin-page-subtitle">Escreva, organize e publique novos conteudos no padrao do admin.</div>
    </div>

    <div class="admin-page-actions">
      <a href="<?= url('/admin/posts') ?>" class="admin-btn admin-btn-secondary">Voltar para posts</a>
    </div>
  </div>

  <?php View::component('admin/posts/form', [
      'mode' => 'create',
      'action' => url('/admin/criar-post'),
      'submitLabel' => 'Salvar post',
      'form' => $form,
      'errors' => $errors,
      'categorias' => $categorias,
      'next_step_options' => $nextStepOptions,
      'media_items' => $mediaItems,
  ]); ?>
</div>

<script src="<?= url('/assets/js/criar-post.js?v=' . $criarPostJsVersion) ?>" defer></script>
<script src="<?= url('/assets/js/admin-post-html-editor.js?v=' . $adminHtmlEditorJsVersion) ?>" defer></script>
<?php View::component('admin/posts/editor-runtime'); ?>
<?php View::component('admin/posts/editor-system-ui'); ?>
