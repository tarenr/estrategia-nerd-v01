<?php
declare(strict_types=1);

use App\Support\View;
use App\Support\Csrf;

$form = $form ?? [];
$errors = $errors ?? [];
$categorias = $categorias ?? [];
$mode = (string) ($mode ?? 'edit');
$editId = (int) ($form['id'] ?? 0);
$slug = trim((string) ($form['slug'] ?? ''));
$status = trim((string) ($form['status'] ?? 'rascunho'));
$updated = isset($_GET['updated']) && (string) $_GET['updated'] === '1';
$duplicated = isset($_GET['duplicated']) && (string) $_GET['duplicated'] === '1';
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

  <?php View::component('admin/posts/form', [
      'mode' => $mode,
      'action' => url('/admin/editar-post?id=' . $editId),
      'submitLabel' => 'Salvar alteracoes',
      'form' => $form,
      'errors' => $errors,
      'categorias' => $categorias,
  ]); ?>
</div>

<script src="<?= url('/assets/js/criar-post.js?v=' . $criarPostJsVersion) ?>" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  window.abrirPreview = function abrirPreview() {
    var editor = document.getElementById('editor-visual');
    var hidden = document.getElementById('conteudoHidden');
    var tituloEl = document.getElementById('titulo');
    var categoriaSelect = document.getElementById('categoria_post_id');

    if (!editor || !hidden) return;

    hidden.value = editor.innerHTML;

    var titulo = tituloEl && tituloEl.value ? tituloEl.value : 'Sem titulo';
    var conteudo = editor.innerHTML || '<p><em>Sem conteudo.</em></p>';
    var categoriaNome = 'Geral';

    if (categoriaSelect && categoriaSelect.selectedIndex >= 0) {
      var option = categoriaSelect.options[categoriaSelect.selectedIndex];
      if (option && option.value) {
        categoriaNome = option.textContent.trim();
      }
    }

    var previewWindow = window.open('about:blank', '_blank');
    if (!previewWindow) return;

    previewWindow.document.open();
    previewWindow.document.write(
      '<!doctype html>' +
      '<html lang="pt-BR">' +
      '<head>' +
      '<meta charset="utf-8">' +
      '<meta name="viewport" content="width=device-width, initial-scale=1">' +
      '<title>Preview - ' + titulo + '</title>' +
      '<style>' +
      'body{margin:0;background:#020617;color:#e2e8f0;font-family:Segoe UI,Tahoma,sans-serif;line-height:1.85;}' +
      '.wrap{max-width:920px;margin:0 auto;padding:48px 24px 80px;}' +
      '.chip{display:inline-flex;align-items:center;padding:6px 12px;background:rgba(0,212,255,.92);color:#020617;font-size:12px;font-weight:900;border-radius:999px;text-transform:uppercase;}' +
      'h1{font-family:Orbitron,Segoe UI,Tahoma,sans-serif;font-size:2.5rem;line-height:1.1;margin:20px 0 28px;color:#fff;}' +
      'h2{font-family:Orbitron,Segoe UI,Tahoma,sans-serif;color:#67e8f9;margin:32px 0 16px;}' +
      'h3{font-family:Orbitron,Segoe UI,Tahoma,sans-serif;color:#c084fc;margin:24px 0 12px;}' +
      'table{width:100%;border-collapse:collapse;margin:24px 0;background:rgba(15,23,42,.82);}' +
      'th,td{border:1px solid rgba(51,65,85,.8);padding:12px 14px;text-align:left;vertical-align:top;}' +
      'th{background:rgba(30,41,59,.55);}' +
      'blockquote{border-left:4px solid #22d3ee;padding-left:16px;color:#94a3b8;margin:20px 0;}' +
      'a{color:#67e8f9;}' +
      '</style>' +
      '</head>' +
      '<body><div class="wrap">' +
      '<span class="chip">' + categoriaNome + '</span>' +
      '<h1>' + titulo + '</h1>' +
      conteudo +
      '</div></body></html>'
    );
    previewWindow.document.close();
  };
});
</script>
