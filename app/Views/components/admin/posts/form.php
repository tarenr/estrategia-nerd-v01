<?php
declare(strict_types=1);

use App\Support\Csrf;
use App\Support\View;

$mode = (string) ($mode ?? 'create');
$action = (string) ($action ?? '#');
$submitLabel = (string) ($submitLabel ?? 'Salvar');
$form = $form ?? [];
$errors = $errors ?? [];
$categorias = $categorias ?? [];
$mediaItems = $media_items ?? [];

$fieldError = static fn (string $key): string => (string) ($errors[$key] ?? '');
$hasErrors = $errors !== [];
$conteudo = (string) ($form['conteudo'] ?? '');
?>

<form id="postForm" method="POST" action="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="space-y-6" enctype="multipart/form-data" novalidate>
  <?= Csrf::field() ?>
  <?php if ((int) ($form['id'] ?? 0) > 0): ?>
    <input type="hidden" name="id" value="<?= (int) ($form['id'] ?? 0) ?>">
  <?php endif; ?>

  <?php if ($hasErrors): ?>
    <section class="admin-panel border border-rose-500/30">
      <h2 class="font-orbitron text-lg font-black text-rose-300">Ajustes necessarios</h2>
      <div class="mt-3 text-sm text-rose-100 space-y-1">
        <?php foreach ($errors as $message): ?>
          <div><?= htmlspecialchars((string) $message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php View::component('admin/posts/form-main-fields', ['form' => $form, 'fieldError' => $fieldError]); ?>
  <?php View::component('admin/posts/form-editor', ['conteudo' => $conteudo, 'fieldError' => $fieldError]); ?>
  <?php View::component('admin/posts/form-publication', ['form' => $form, 'fieldError' => $fieldError]); ?>
  <?php View::component('admin/posts/form-category', ['form' => $form, 'categorias' => $categorias, 'fieldError' => $fieldError]); ?>
  <?php View::component('admin/posts/form-media-seo', ['form' => $form, 'fieldError' => $fieldError, 'media_items' => $mediaItems, 'orphan_images' => $orphan_images ?? []]); ?>
  <?php View::component('admin/posts/form-actions', ['mode' => $mode, 'submitLabel' => $submitLabel]); ?>
</form>

<?php View::component('admin/posts/form-preview'); ?>
