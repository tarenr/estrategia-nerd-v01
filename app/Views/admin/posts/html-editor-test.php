<?php
declare(strict_types=1);

$adminHtmlEditorJsPath = dirname(__DIR__, 3) . '/public/assets/js/admin-post-html-editor.js';
$adminHtmlEditorJsVersion = is_file($adminHtmlEditorJsPath) ? (string) filemtime($adminHtmlEditorJsPath) : '1';
$sampleHtml = <<<'HTML'
<section class="content-block content-block-highlight">
  <div class="content-block-label">Resumo inicial</div>
  <p>Este bloco existe apenas para validar o editor HTML com destaque de sintaxe.</p>
</section>

<figure class="article-figure content-block-image">
  <img src="/uploads/exemplo.webp" alt="Imagem de teste">
  <figcaption>Legenda de validacao.</figcaption>
</figure>
HTML;
?>

<div class="max-w-6xl mx-auto px-4 py-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Teste do Editor HTML</h1>
      <div class="admin-page-subtitle">Ambiente isolado para validar o CodeMirror antes de levar a integracao para a tela de posts.</div>
    </div>

    <div class="admin-page-actions">
      <a href="<?= url('/admin/posts') ?>" class="admin-btn admin-btn-secondary">Voltar para posts</a>
    </div>
  </div>

  <section class="admin-panel html-editor-test-panel admin-html-editor-shell" data-html-editor-root data-html-editor-prevent-submit="1">
    <div class="html-editor-test-toolbar admin-html-editor-toolbar">
      <div class="admin-html-editor-toolbar-actions">
        <label class="html-editor-test-toggle admin-html-editor-toggle">
          <input type="checkbox" data-html-editor-wrap>
          <span>Quebra de linha visual</span>
        </label>
        <button type="button" class="admin-btn admin-btn-secondary admin-html-editor-format-btn" data-html-editor-format>Formatar HTML</button>
      </div>
      <span class="html-editor-test-status admin-html-editor-status" data-html-editor-status>Pronto para validar</span>
    </div>

    <form id="htmlEditorTestForm" class="space-y-5">
      <div data-html-editor-mount class="html-editor-test-mount admin-html-editor-mount"></div>

      <textarea
        id="html-editor-test-textarea"
        name="conteudo_html_teste"
        data-html-editor-textarea
        class="html-editor-test-textarea admin-html-editor-textarea"
      ><?= htmlspecialchars($sampleHtml, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>

      <div class="html-editor-test-actions">
        <button type="submit" class="admin-btn admin-btn-primary">Validar sincronizacao</button>
      </div>

      <div class="html-editor-test-output-shell">
        <div class="html-editor-test-output-title">Valor atual do textarea enviado no submit</div>
        <pre data-html-editor-output class="html-editor-test-output"></pre>
      </div>
    </form>
  </section>
</div>

<script src="<?= url('/assets/js/admin-post-html-editor.js?v=' . $adminHtmlEditorJsVersion) ?>" defer></script>
