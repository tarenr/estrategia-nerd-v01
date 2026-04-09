<?php
declare(strict_types=1);

$fieldError = $fieldError ?? static fn (string $key): string => '';
$form = $form ?? [];
?>

<section class="admin-panel space-y-6 post-main-fields-panel">
  <div>
    <h2 class="font-orbitron text-lg font-black text-white">Informacoes principais</h2>
    <div class="text-xs text-slate-400 mt-1">Defina titulo, slug, data de publicacao e um resumo curto para o post.</div>
  </div>

  <div class="post-main-fields-grid">
    <div class="post-main-field post-main-field-title">
      <div class="post-main-field-head">
        <label for="titulo" class="admin-filter-label mb-0">Titulo</label>
      </div>
      <input id="titulo" name="titulo" type="text" value="<?= htmlspecialchars((string) ($form['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input admin-filter-control post-main-field-control post-main-title-control" placeholder="Ex.: RTX 5070 vs RX 9070: qual vale mais a pena?">
      <div class="post-title-helper">
        <span class="post-title-helper-chip">Destaque publico</span>
        <div class="post-title-helper-copy">
          Use <code>[[trecho]]</code> para destacar parte do titulo na pagina publica.
          <span class="post-title-helper-example">Ex.: O Futuro dos Processadores: <code>[[Intel vs AMD vs ARM]]</code> em 2026</span>
        </div>
      </div>
      <?php if ($fieldError('titulo') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('titulo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
    </div>

    <div class="post-main-fields-secondary-grid">
      <div class="post-main-field">
        <div class="post-main-field-head">
          <label for="slug" class="admin-filter-label mb-0">Slug</label>
        </div>
        <div class="post-slug-shell">
          <input id="slug" name="slug" type="text" value="<?= htmlspecialchars((string) ($form['slug'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input admin-filter-control post-main-field-control" placeholder="slug-do-post">
          <button type="button" class="admin-btn admin-btn-secondary post-slug-generate" onclick="gerarSlug()">Gerar</button>
        </div>
        <div class="post-main-field-footer">URL publica usada no post e nas listagens.</div>
        <?php if ($fieldError('slug') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('slug'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>

      <div class="post-main-field">
        <div class="post-main-field-head">
          <label for="data_publicacao" class="admin-filter-label mb-0">Data de publicacao</label>
        </div>
        <input id="data_publicacao" name="data_publicacao" type="datetime-local" value="<?= htmlspecialchars((string) ($form['data_publicacao'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input admin-filter-control post-main-field-control">
        <div class="post-main-field-footer">Defina quando o conteudo deve entrar no ar.</div>
        <?php if ($fieldError('data_publicacao') !== ''): ?><div class="mt-2 text-xs text-rose-300"><?= htmlspecialchars($fieldError('data_publicacao'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
      </div>
    </div>

    <div class="post-main-field">
      <div class="post-main-field-head">
        <label for="resumo" class="admin-filter-label mb-0">Resumo</label>
        <span class="post-main-counter"><span id="resumoCount">0</span> caracteres</span>
      </div>
      <textarea id="resumo" name="resumo" rows="4" class="nerd-input admin-filter-control post-main-field-control post-main-summary-control" placeholder="Resumo curto para listagens, cards e meta description."><?= htmlspecialchars((string) ($form['resumo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
      <div class="post-main-field-footer">Use um resumo curto e direto para fortalecer listagens e SEO.</div>
    </div>
  </div>
</section>