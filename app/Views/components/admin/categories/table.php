<?php
declare(strict_types=1);

$items = $items ?? [];
$filters = $filters ?? ['busca' => '', 'status' => ''];
$pagination = $pagination ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$sort = (string) ($sort ?? 'ordem');
$dir = (string) ($dir ?? 'asc');
$baseUrl = function_exists('url') ? url('/admin/categorias') : '/admin/categorias';

$buildUrl = static function (array $overrides = []) use ($baseUrl, $pagination, $filters, $sort, $dir): string {
    $query = [
        'busca' => (string) ($filters['busca'] ?? ''),
        'status' => (string) ($filters['status'] ?? ''),
        'sort' => $sort,
        'dir' => $dir,
        'page' => (int) ($pagination['page'] ?? 1),
        'per_page' => (int) ($pagination['per_page'] ?? 10),
    ];

    foreach ($overrides as $key => $value) {
        $query[$key] = $value;
    }

    $query = array_filter($query, static fn ($value): bool => !($value === '' || $value === null || $value === 0));
    $qs = http_build_query($query);

    return $qs !== '' ? $baseUrl . '?' . $qs : $baseUrl;
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
$perPage = (int) ($pagination['per_page'] ?? 10);
?>

<section class="admin-panel">
  <div class="flex items-center justify-between mb-6 gap-4">
    <div>
      <h3 class="font-orbitron text-xl font-black text-white">Lista de Categorias</h3>
      <div class="text-xs text-slate-400 mt-1"><?= number_format($total, 0, ',', '.') ?> categoria(s) encontrada(s)</div>
    </div>
    <span class="text-cyan-400 text-sm font-bold uppercase"><?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> / <?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
  </div>

  <?php if ($items === []): ?>
    <div class="text-center py-12 border-2 border-dashed border-gray-700 rounded-xl">
      <div class="text-3xl mb-4 font-orbitron font-black text-cyan-300">SEM</div>
      <h4 class="text-xl font-bold text-white mb-2">Nenhuma categoria encontrada</h4>
      <div class="text-slate-400 text-sm">Ajuste os filtros ou crie uma nova categoria para organizar o fluxo editorial.</div>
    </div>
  <?php else: ?>
    <div class="overflow-x-auto rounded-xl border border-slate-800">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-800/70 text-slate-300">
          <tr class="text-left">
            <th class="px-4 py-3 font-semibold"><a data-admin-categories-link class="inline-flex items-center gap-2 hover:text-cyan-300 transition" href="<?= htmlspecialchars($sortLink('nome'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Categoria <?= $sortIcon('nome') ?></a></th>
            <th class="px-4 py-3 font-semibold"><a data-admin-categories-link class="inline-flex items-center gap-2 hover:text-cyan-300 transition" href="<?= htmlspecialchars($sortLink('slug'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Slug <?= $sortIcon('slug') ?></a></th>
            <th class="px-4 py-3 font-semibold"><a data-admin-categories-link class="inline-flex items-center gap-2 hover:text-cyan-300 transition" href="<?= htmlspecialchars($sortLink('cor'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Cor <?= $sortIcon('cor') ?></a></th>
            <th class="px-4 py-3 font-semibold"><a data-admin-categories-link class="inline-flex items-center gap-2 hover:text-cyan-300 transition" href="<?= htmlspecialchars($sortLink('ativo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Status <?= $sortIcon('ativo') ?></a></th>
            <th class="px-4 py-3 font-semibold"><a data-admin-categories-link class="inline-flex items-center gap-2 hover:text-cyan-300 transition" href="<?= htmlspecialchars($sortLink('ordem'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Ordem <?= $sortIcon('ordem') ?></a></th>
            <th class="px-4 py-3 font-semibold"><a data-admin-categories-link class="inline-flex items-center gap-2 hover:text-cyan-300 transition" href="<?= htmlspecialchars($sortLink('total_posts'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Posts <?= $sortIcon('total_posts') ?></a></th>
            <th class="px-4 py-3 font-semibold"><a data-admin-categories-link class="inline-flex items-center gap-2 hover:text-cyan-300 transition" href="<?= htmlspecialchars($sortLink('total_views'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Views <?= $sortIcon('total_views') ?></a></th>
            <th class="px-4 py-3 font-semibold text-right">Acoes</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/70">
          <?php foreach ($items as $item): ?>
            <?php
              $id = (int) ($item['id'] ?? 0);
              $ativo = (int) ($item['ativo'] ?? 1) === 1;
              $postsCount = (int) ($item['total_posts'] ?? 0);
              $editUrl = url('/admin/editar-categoria?id=' . $id);
              $deleteUrl = url('/admin/excluir-categoria?id=' . $id);
              $postsUrl = url('/admin/posts?categoria=' . $id);
            ?>
            <tr class="hover:bg-slate-800/40 transition">
              <td class="px-4 py-4 align-top"><div class="font-semibold text-slate-100"><?= htmlspecialchars((string) ($item['nome'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><div class="mt-1 text-xs text-slate-400">#<?= $id ?></div></td>
              <td class="px-4 py-4 align-top text-slate-300"><?= htmlspecialchars((string) ($item['slug'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
              <td class="px-4 py-4 align-top"><div class="flex items-center gap-2 text-slate-200 text-xs"><span class="w-3 h-3 rounded-full border border-slate-700" style="background: <?= htmlspecialchars((string) ($item['cor'] ?? '#00d4ff'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></span><span><?= htmlspecialchars((string) ($item['cor'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div></td>
              <td class="px-4 py-4 align-top"><span class="<?= $ativo ? 'status-badge status-publicado' : 'status-badge status-rascunho' ?>"><?= $ativo ? 'ativa' : 'inativa' ?></span></td>
              <td class="px-4 py-4 align-top text-slate-200"><?= number_format((int) ($item['ordem'] ?? 0), 0, ',', '.') ?></td>
              <td class="px-4 py-4 align-top text-slate-200">
                <?php if ($postsCount > 0): ?>
                  <a class="inline-flex items-center gap-2 rounded-lg border border-cyan-500/20 bg-cyan-500/10 px-3 py-2 text-xs font-bold text-cyan-200 hover:bg-cyan-500/15 transition" href="<?= htmlspecialchars($postsUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= number_format($postsCount, 0, ',', '.') ?><span class="text-[11px] text-cyan-300/80">ver posts</span></a>
                <?php else: ?>
                  <span class="text-slate-500">0</span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-4 align-top text-slate-200"><?= number_format((int) ($item['total_views'] ?? 0), 0, ',', '.') ?></td>
              <td class="px-4 py-4 align-top"><div class="flex items-center justify-end gap-2"><a class="btn-edit px-3 py-2 rounded-lg text-xs font-bold" href="<?= htmlspecialchars($editUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Editar</a><a class="px-3 py-2 rounded-lg text-xs font-bold border border-rose-500/30 text-rose-200 hover:bg-rose-500/10 transition" href="<?= htmlspecialchars($deleteUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Excluir</a></div></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="mt-5 flex items-center justify-between gap-4 flex-wrap rounded-xl border border-slate-800 bg-slate-900/40 px-4 py-3">
      <div class="text-xs text-slate-400">Pagina atual: <?= $page ?> - <?= $perPage ?> por pagina</div>
      <div class="flex items-center gap-2">
        <?php $prevUrl = $buildUrl(['page' => max(1, $page - 1)]); $nextUrl = $buildUrl(['page' => min($pages, $page + 1)]); $prevDisabled = $page <= 1; $nextDisabled = $page >= $pages; ?>
        <a data-admin-categories-link class="admin-btn admin-btn-secondary <?= $prevDisabled ? 'pointer-events-none opacity-50' : '' ?>" href="<?= htmlspecialchars($prevUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Anterior</a>
        <span class="admin-chip"><?= $page ?> / <?= $pages ?></span>
        <a data-admin-categories-link class="admin-btn admin-btn-secondary <?= $nextDisabled ? 'pointer-events-none opacity-50' : '' ?>" href="<?= htmlspecialchars($nextUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Proxima</a>
      </div>
    </div>
  <?php endif; ?>
</section>
