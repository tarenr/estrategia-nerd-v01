<?php
declare(strict_types=1);

$items = $items ?? [];
$filters = $filters ?? ['busca' => '', 'tipo' => '', 'estado' => ''];
$pagination = $pagination ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 12, 'pages' => 1];
$sort = (string) ($sort ?? 'data');
$dir = (string) ($dir ?? 'desc');
$baseUrl = url('/admin/midia');

$buildUrl = static function (array $overrides = []) use ($baseUrl, $filters, $pagination, $sort, $dir): string {
    $query = [
        'busca' => (string) ($filters['busca'] ?? ''),
        'tipo' => (string) ($filters['tipo'] ?? ''),
        'estado' => (string) ($filters['estado'] ?? ''),
        'sort' => $sort,
        'dir' => $dir,
        'page' => (int) ($pagination['page'] ?? 1),
        'per_page' => (int) ($pagination['per_page'] ?? 12),
    ];

    foreach ($overrides as $key => $value) {
        $query[$key] = $value;
    }

    $query = array_filter($query, static fn ($value): bool => !($value === '' || $value === null || $value === 0));
    $queryString = http_build_query($query);

    return $queryString !== '' ? $baseUrl . '?' . $queryString : $baseUrl;
};

$page = (int) ($pagination['page'] ?? 1);
$pages = max(1, (int) ($pagination['pages'] ?? 1));
$total = (int) ($pagination['total'] ?? count($items));
$perPage = (int) ($pagination['per_page'] ?? 12);
?>

<section class="admin-panel media-library-panel">
  <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
    <div>
      <h3 class="font-orbitron text-xl font-black text-white">Biblioteca de Midia</h3>
      <div class="text-xs text-slate-400 mt-1"><?= number_format($total, 0, ',', '.') ?> arquivo(s) encontrados</div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <?php if (($filters['estado'] ?? '') === 'orfa' && $items !== []): ?>
        <form method="POST" action="<?= htmlspecialchars(url('/admin/midia/limpar-orfas'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex">
          <?= \App\Support\Csrf::field() ?>
          <input type="hidden" name="busca" value="<?= htmlspecialchars((string) ($filters['busca'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          <input type="hidden" name="tipo" value="<?= htmlspecialchars((string) ($filters['tipo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          <input type="hidden" name="estado" value="orfa">
          <button type="submit" class="admin-btn admin-btn-secondary">Remover orfas visiveis</button>
        </form>
      <?php endif; ?>
      <span class="text-cyan-400 text-sm font-bold uppercase"><?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> / <?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
    </div>
  </div>

  <?php if ($items === []): ?>
    <div class="text-center py-12 border-2 border-dashed border-gray-700 rounded-xl">
      <div class="text-4xl text-cyan-300 mb-4"><i class="fa-solid fa-photo-film"></i></div>
      <h4 class="text-xl font-bold text-white mb-2">Nenhum arquivo encontrado</h4>
      <div class="text-slate-400 text-sm">Envie a primeira imagem ou ajuste os filtros para explorar a biblioteca.</div>
    </div>
  <?php else: ?>
    <div class="media-library-grid">
      <?php foreach ($items as $item): ?>
        <?php
          $deleteUrl = url('/admin/excluir-midia?path=' . rawurlencode((string) ($item['relative_path'] ?? '')));
          $copyUrl = (string) ($item['public_url'] ?? '');
          $previewUrl = (string) ($item['public_url'] ?? '');
          $libraryLabel = (string) ($item['library'] ?? 'Upload');
          $isManagedUpload = (bool) ($item['is_managed_upload'] ?? false);
          $postSlug = trim((string) ($item['post_slug'] ?? ''));
          $postTitle = trim((string) ($item['post_title'] ?? ''));
          $postFilterUrl = trim((string) ($item['post_filter_url'] ?? ''));
          $linkedPostsCount = max(0, (int) ($item['linked_posts_count'] ?? 0));
          $usageState = (string) ($item['usage_state'] ?? 'available');
          $statusLabel = (string) ($item['status_label'] ?? 'Disponivel');
          $statusClass = match ($usageState) {
              'in_use' => ' is-positive',
              'orphan' => ' is-warning',
              'available' => ' is-available',
              default => ' is-muted',
          };
        ?>
        <article class="media-library-card">
          <div class="media-library-preview">
            <?php if (($item['is_image'] ?? false) === true): ?>
              <img src="<?= htmlspecialchars($previewUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($item['name'] ?? 'midia'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="media-library-image">
            <?php else: ?>
              <div class="media-library-file-fallback">
                <span class="media-library-file-ext"><?= htmlspecialchars((string) strtoupper((string) ($item['extension'] ?? 'ARQ')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              </div>
            <?php endif; ?>
          </div>

          <div class="media-library-body">
            <div class="media-library-header">
              <div class="media-library-title-wrap">
                <div class="media-library-title"><?= htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="media-library-path"><?= htmlspecialchars((string) ($item['relative_path'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>
              <div class="media-library-flags">
                <span class="media-library-flag"><?= htmlspecialchars($libraryLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              </div>
            </div>

            <div class="media-library-meta-grid">
              <div class="media-library-meta-item">
                <div class="media-library-meta-label">Tipo</div>
                <div class="media-library-meta-value"><?= htmlspecialchars((string) ($item['mime'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>
              <div class="media-library-meta-item">
                <div class="media-library-meta-label">Tamanho</div>
                <div class="media-library-meta-value"><?= htmlspecialchars((string) ($item['size_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>
              <div class="media-library-meta-item">
                <div class="media-library-meta-label">Dimensoes</div>
                <div class="media-library-meta-value"><?= htmlspecialchars((string) ($item['dimensions_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>
              <div class="media-library-meta-item">
                <div class="media-library-meta-label">Pasta</div>
                <div class="media-library-meta-value media-library-meta-value-break"><?= htmlspecialchars((string) ($item['directory'] ?? 'uploads'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>
            </div>

            <div class="media-library-linkage-row">
              <div class="media-library-linkage-block">
                <div class="media-library-linkage-label">Status</div>
                <div class="media-library-linkage-value<?= $statusClass ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>
              <div class="media-library-linkage-block">
                <div class="media-library-linkage-label">Post</div>
                <?php if ($postFilterUrl !== ''): ?>
                  <a class="media-library-linkage-link" href="<?= htmlspecialchars($postFilterUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($postTitle !== '' ? $postTitle : $postSlug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
                  <?php if ($linkedPostsCount > 1): ?>
                    <div class="media-library-linkage-note">+<?= $linkedPostsCount - 1 ?> outro(s) post(s)</div>
                  <?php endif; ?>
                <?php else: ?>
                  <div class="media-library-linkage-value is-muted">Sem vinculo</div>
                <?php endif; ?>
              </div>
            </div>

            <div class="media-library-updated">Atualizado em <?= htmlspecialchars((string) ($item['modified_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>

            <div class="media-library-actions">
              <button type="button" class="admin-btn admin-btn-primary media-library-copy" data-copy-url="<?= htmlspecialchars($copyUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Copiar URL</button>
              <div class="media-library-actions-side">
                <a class="media-library-icon-btn" href="<?= htmlspecialchars($previewUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noreferrer" aria-label="Ver arquivo" title="Ver arquivo">
                  <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                </a>
                <?php if ($isManagedUpload): ?>
                  <a class="media-library-icon-btn media-library-icon-btn-danger" href="<?= htmlspecialchars($deleteUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-label="Excluir arquivo" title="Excluir arquivo">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                  </a>
                <?php else: ?>
                  <span class="media-library-asset-note">Asset institucional</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>