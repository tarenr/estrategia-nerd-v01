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

$sortLink = static function (string $column) use ($sort, $dir, $buildUrl): string {
    $nextDir = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';
    return $buildUrl(['sort' => $column, 'dir' => $nextDir, 'page' => 1]);
};

$sortIcon = static function (string $column) use ($sort, $dir): string {
    if ($sort !== $column) {
        return '<span class="text-slate-600">&harr;</span>';
    }

    return $dir === 'asc'
        ? '<span class="text-cyan-300">&uarr;</span>'
        : '<span class="text-cyan-300">&darr;</span>';
};

$page = (int) ($pagination['page'] ?? 1);
$pages = max(1, (int) ($pagination['pages'] ?? 1));
$total = (int) ($pagination['total'] ?? count($items));
$perPage = (int) ($pagination['per_page'] ?? 12);
?>

<section class="admin-panel">
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
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <?php foreach ($items as $item): ?>
        <?php
          $deleteUrl = url('/admin/excluir-midia?path=' . rawurlencode((string) ($item['relative_path'] ?? '')));
          $copyUrl = (string) ($item['public_url'] ?? '');
          $previewUrl = (string) ($item['public_url'] ?? '');
          $libraryLabel = (string) ($item['library'] ?? 'Upload');
          $isManagedUpload = (bool) ($item['is_managed_upload'] ?? false);
        ?>
        <article class="rounded-2xl border border-slate-800 bg-slate-900/40 overflow-hidden flex flex-col">
          <div class="aspect-[16/10] bg-slate-950/70 flex items-center justify-center border-b border-slate-800 overflow-hidden">
            <?php if (($item['is_image'] ?? false) === true): ?>
              <img src="<?= htmlspecialchars($previewUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($item['name'] ?? 'midia'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="w-full h-full object-cover">
            <?php else: ?>
              <div class="text-slate-400 text-sm font-bold uppercase tracking-[0.2em]"><?= htmlspecialchars((string) ($item['extension'] ?? 'ARQ'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php endif; ?>
          </div>

          <div class="p-4 flex-1 flex flex-col gap-3">
            <div>
              <div class="font-semibold text-slate-100 break-all"><?= htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <div class="text-xs text-slate-500 mt-1 break-all"><?= htmlspecialchars((string) ($item['relative_path'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            </div>

            <dl class="grid grid-cols-2 gap-3 text-xs">
              <div><dt class="text-slate-500 uppercase tracking-wide">Origem</dt><dd class="text-slate-200 mt-1"><?= htmlspecialchars($libraryLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd></div>
              <div><dt class="text-slate-500 uppercase tracking-wide">Tamanho</dt><dd class="text-slate-200 mt-1"><?= htmlspecialchars((string) ($item['size_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd></div>
              <div><dt class="text-slate-500 uppercase tracking-wide">Tipo</dt><dd class="text-slate-200 mt-1"><?= htmlspecialchars((string) ($item['mime'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd></div>
              <div><dt class="text-slate-500 uppercase tracking-wide">Pasta</dt><dd class="text-slate-200 mt-1 break-all"><?= htmlspecialchars((string) ($item['directory'] ?? 'uploads'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd></div>
              <div><dt class="text-slate-500 uppercase tracking-wide">Status</dt><dd class="text-slate-200 mt-1"><?= ($item['is_orphan'] ?? false) === true ? 'Orfa' : 'Em uso' ?></dd></div>
              <div><dt class="text-slate-500 uppercase tracking-wide">Post</dt><dd class="text-slate-200 mt-1 break-all"><?= htmlspecialchars((string) ($item['post_slug'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?: '-' ?></dd></div>
              <div class="col-span-2"><dt class="text-slate-500 uppercase tracking-wide">Dimensoes</dt><dd class="text-slate-200 mt-1"><?= htmlspecialchars((string) ($item['dimensions_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd></div>
            </dl>

            <div class="text-xs text-slate-500">Atualizado em <?= htmlspecialchars((string) ($item['modified_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>

            <div class="mt-auto flex items-center gap-2 flex-wrap">
              <a class="admin-btn admin-btn-secondary" href="<?= htmlspecialchars($previewUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noreferrer">Ver</a>
              <button type="button" class="admin-btn admin-btn-primary" data-copy-url="<?= htmlspecialchars($copyUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Copiar URL</button>
              <?php if ($isManagedUpload): ?>
                <a class="admin-btn admin-btn-danger" href="<?= htmlspecialchars($deleteUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Excluir</a>
              <?php else: ?>
                <span class="admin-chip">Asset institucional</span>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="mt-5 flex items-center justify-between gap-4 flex-wrap rounded-xl border border-slate-800 bg-slate-900/40 px-4 py-3">
      <div class="text-xs text-slate-400">Pagina atual: <?= $page ?> - <?= $perPage ?> por pagina</div>
      <div class="flex items-center gap-2">
        <?php $prevUrl = $buildUrl(['page' => max(1, $page - 1)]); $nextUrl = $buildUrl(['page' => min($pages, $page + 1)]); $prevDisabled = $page <= 1; $nextDisabled = $page >= $pages; ?>
        <a class="admin-btn admin-btn-secondary <?= $prevDisabled ? 'pointer-events-none opacity-50' : '' ?>" href="<?= htmlspecialchars($prevUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Anterior</a>
        <span class="admin-chip"><?= $page ?> / <?= $pages ?></span>
        <a class="admin-btn admin-btn-secondary <?= $nextDisabled ? 'pointer-events-none opacity-50' : '' ?>" href="<?= htmlspecialchars($nextUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Proxima</a>
      </div>
    </div>
  <?php endif; ?>
</section>
