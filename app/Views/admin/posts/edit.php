<?php
declare(strict_types=1);

use App\Support\View;
use App\Support\Csrf;

$form = $form ?? [];
$errors = $errors ?? [];
$categorias = $categorias ?? [];
$nextStepOptions = $next_step_options ?? [];
$mediaItems = $media_items ?? [];
$orphanImages = $orphan_images ?? [];
$mode = (string) ($mode ?? 'edit');
$editId = (int) ($form['id'] ?? 0);
$slug = trim((string) ($form['slug'] ?? ''));
$status = trim((string) ($form['status'] ?? 'rascunho'));
$updated = isset($_GET['updated']) && (string) $_GET['updated'] === '1';
$duplicated = isset($_GET['duplicated']) && (string) $_GET['duplicated'] === '1';
$orphanCleaned = isset($_GET['orphan_cleaned']) && (string) $_GET['orphan_cleaned'] === '1';
$orphanRemoved = max(0, (int) ($_GET['orphan_removed'] ?? 0));
$criarPostJsPath = dirname(__DIR__, 3) . '/public/assets/js/criar-post.js';
$criarPostJsVersion = is_file($criarPostJsPath) ? (string) filemtime($criarPostJsPath) : '1';
?>

<div class="max-w-7xl mx-auto px-4 py-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Editar Post</h1>
      <div class="admin-page-subtitle">Atualize conteudo, metadados e publicacao mantendo o mesmo fluxo editorial.</div>
    </div>

    <div class="admin-page-actions">
      <?php if ($editId > 0): ?>
        <div class="admin-chip">ID: <?= $editId ?></div>
      <?php endif; ?>
      <?php if ($slug !== ''): ?>
        <div class="admin-chip">Slug: <?= htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <?php endif; ?>
      <div class="admin-chip">Status: <?= htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <button type="button" class="admin-btn admin-btn-secondary" onclick="abrirPreview()">Abrir preview</button>
      <form method="POST" action="<?= url('/admin/duplicar-post?id=' . $editId) ?>" class="inline-flex">
        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= $editId ?>">
        <button type="submit" class="admin-btn admin-btn-secondary">Duplicar rascunho</button>
      </form>
      <a href="<?= url('/admin/posts') ?>" class="admin-btn admin-btn-secondary">Voltar para posts</a>
    </div>
  </div>

  <?php if ($updated): ?>
    <section class="admin-panel border border-cyan-500/30 mb-6">
      <div class="text-sm font-bold text-cyan-300">Alteracoes salvas com sucesso.</div>
      <div class="text-xs text-slate-400 mt-1">Voce continua na edicao com a versao mais recente do post carregada.</div>
    </section>
  <?php endif; ?>

  <?php if ($duplicated): ?>
    <section class="admin-panel border border-emerald-500/30 mb-6">
      <div class="text-sm font-bold text-emerald-300">Rascunho duplicado com sucesso.</div>
      <div class="text-xs text-slate-400 mt-1">Esta copia foi aberta para ajustes independentes antes da publicacao.</div>
    </section>
  <?php endif; ?>

  <?php if ($orphanCleaned): ?>
    <section class="admin-panel border border-amber-500/30 mb-6">
      <div class="text-sm font-bold text-amber-200">Limpeza de imagens concluida.</div>
      <div class="text-xs text-slate-400 mt-1"><?= $orphanRemoved > 0 ? $orphanRemoved . ' arquivo(s) removido(s) da pasta do conteudo.' : 'Nenhuma imagem orfa foi encontrada para remover.' ?></div>
    </section>
  <?php endif; ?>

  <?php View::component('admin/posts/form', [
      'mode' => $mode,
      'action' => url('/admin/editar-post?id=' . $editId),
      'submitLabel' => 'Salvar alteracoes',
      'form' => $form,
      'errors' => $errors,
      'categorias' => $categorias,
      'next_step_options' => $nextStepOptions,
      'media_items' => $mediaItems,
      'orphan_images' => $orphanImages,
  ]); ?>
</div>

<script src="<?= url('/assets/js/criar-post.js?v=' . $criarPostJsVersion) ?>" defer></script>
<?php View::component('admin/posts/editor-runtime'); ?>
<?php View::component('admin/posts/editor-system-ui'); ?>
