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

$page = max(1, (int) ($pagination['page'] ?? 1));
$pages = max(1, (int) ($pagination['pages'] ?? 1));
$total = max(0, (int) ($pagination['total'] ?? count($items)));
$perPage = max(5, (int) ($pagination['per_page'] ?? 10));
$start = max(1, $page - 2);
$end = min($pages, $page + 2);
if (($end - $start) < 4) {
    $start = max(1, $end - 4);
    $end = min($pages, $start + 4);
}
$firstItem = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$lastItem = $total > 0 ? min($total, $page * $perPage) : 0;
?>

<section class="admin-panel categories-table-panel">
  <div class="posts-table-head">
    <div>
      <h3 class="font-orbitron text-xl font-black text-white">Lista de Categorias</h3>
      <div class="text-xs text-slate-400 mt-1"><?= number_format($total, 0, ',', '.') ?> categoria(s) encontrada(s)</div>
    </div>
    <span class="posts-table-order"><?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> / <?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
  </div>

  <?php if ($items === []): ?>
    <div class="text-center py-12 border-2 border-dashed border-gray-700 rounded-xl">
      <div class="text-3xl mb-4 font-orbitron font-black text-cyan-300">SEM</div>
      <h4 class="text-xl font-bold text-white mb-2">Nenhuma categoria encontrada</h4>
      <div class="text-slate-400 text-sm">Ajuste os filtros ou crie uma nova categoria para organizar o fluxo editorial.</div>
    </div>
  <?php else: ?>
    <div class="categories-table-wrap">
      <table class="categories-table">
        <colgroup>
          <col class="categories-table-col-title">
          <col class="categories-table-col-status">
          <col class="categories-table-col-order">
          <col class="categories-table-col-posts">
          <col class="categories-table-col-views">
          <col class="categories-table-col-actions">
        </colgroup>
        <thead class="posts-table-thead">
          <tr>
            <th class="posts-table-th posts-table-th-left"><a data-admin-categories-link class="posts-table-sort posts-table-sort-left" href="<?= htmlspecialchars($sortLink('nome'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Categoria <?= $sortIcon('nome') ?></a></th>
            <th class="posts-table-th posts-table-th-center"><a data-admin-categories-link class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('ativo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Status <?= $sortIcon('ativo') ?></a></th>
            <th class="posts-table-th posts-table-th-center"><a data-admin-categories-link class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('ordem'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Ordem <?= $sortIcon('ordem') ?></a></th>
            <th class="posts-table-th posts-table-th-center"><a data-admin-categories-link class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('total_posts'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Posts <?= $sortIcon('total_posts') ?></a></th>
            <th class="posts-table-th posts-table-th-center"><a data-admin-categories-link class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('total_views'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Views <?= $sortIcon('total_views') ?></a></th>
            <th class="posts-table-th posts-table-th-center">Acoes</th>
          </tr>
        </thead>
        <tbody class="categories-table-body">
          <?php foreach ($items as $item): ?>
            <?php
              $id = (int) ($item['id'] ?? 0);
              $nome = trim((string) ($item['nome'] ?? ''));
              $slug = trim((string) ($item['slug'] ?? ''));
              $cor = trim((string) ($item['cor'] ?? '#00d4ff'));
              $ativo = (int) ($item['ativo'] ?? 1) === 1;
              $indexar = (int) ($item['indexar'] ?? 1) === 1;
              $exibirNoMenu = (int) ($item['exibir_no_menu'] ?? 1) === 1;
              $ordem = (int) ($item['ordem'] ?? 0);
              $postsCount = (int) ($item['total_posts'] ?? 0);
              $viewsCount = (int) ($item['total_views'] ?? 0);
              $editUrl = url('/admin/editar-categoria?id=' . $id);
              $deleteUrl = url('/admin/excluir-categoria?id=' . $id);
              $postsUrl = url('/admin/posts?categoria=' . $id);
            ?>
            <tr class="categories-table-row">
              <td class="categories-table-td categories-table-title-cell">
                <a class="categories-table-title-link" href="<?= htmlspecialchars($editUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($nome !== '' ? $nome : 'Sem nome', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
                <div class="categories-table-subline">#<?= $id ?><?php if ($slug !== ''): ?> <span class="categories-table-subline-dot">&bull;</span> <?= htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?php endif; ?></div>
                <div class="categories-table-color-row">
                  <span class="categories-table-color-dot" style="background: <?= htmlspecialchars($cor !== '' ? $cor : '#00d4ff', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></span>
                  <span class="categories-table-color-chip"><?= htmlspecialchars($cor !== '' ? $cor : '#00d4ff', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                </div>
                <div class="flex flex-wrap gap-2 mt-3">
                  <span class="<?= $indexar ? 'status-badge status-publicado' : 'status-badge status-rascunho' ?>">
                    <?= $indexar ? 'indexavel' : 'noindex' ?>
                  </span>
                  <span class="<?= $exibirNoMenu ? 'status-badge status-publicado' : 'status-badge status-rascunho' ?>">
                    <?= $exibirNoMenu ? 'menu' : 'fora menu' ?>
                  </span>
                </div>
              </td>
              <td class="categories-table-td categories-table-td-center">
                <div class="flex flex-col items-center gap-2">
                  <span class="<?= $ativo ? 'status-badge status-publicado' : 'status-badge status-rascunho' ?>"><?= $ativo ? 'ativa' : 'inativa' ?></span>
                  <span class="text-[11px] text-slate-500"><?= $indexar ? 'SEO ligado' : 'SEO bloqueado' ?></span>
                </div>
              </td>
              <td class="categories-table-td categories-table-td-center">
                <span class="categories-table-metric<?= $ordem === 0 ? ' is-zero' : '' ?>"><?= number_format($ordem, 0, ',', '.') ?></span>
              </td>
              <td class="categories-table-td categories-table-td-center">
                <?php if ($postsCount > 0): ?>
                  <a class="categories-table-metric-link" href="<?= htmlspecialchars($postsUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <span class="categories-table-metric"><?= number_format($postsCount, 0, ',', '.') ?></span>
                    <span class="categories-table-metric-caption">ver posts</span>
                  </a>
                <?php else: ?>
                  <span class="categories-table-metric is-zero">0</span>
                <?php endif; ?>
              </td>
              <td class="categories-table-td categories-table-td-center">
                <span class="categories-table-metric<?= $viewsCount === 0 ? ' is-zero' : '' ?>"><?= number_format($viewsCount, 0, ',', '.') ?></span>
              </td>
              <td class="categories-table-td categories-table-td-center">
                <div class="categories-table-actions">
                  <a
                    class="categories-table-action categories-table-action-delete"
                    href="<?= htmlspecialchars($deleteUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                    aria-label="Excluir categoria"
                    title="Excluir categoria"
                  >
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <section class="admin-panel posts-pagination-panel mt-5">
      <div class="posts-pagination-shell">
        <div class="posts-pagination-summary">
          Exibindo <span><?= number_format($firstItem, 0, ',', '.') ?></span> ate <span><?= number_format($lastItem, 0, ',', '.') ?></span> de <span><?= number_format($total, 0, ',', '.') ?></span> categorias
        </div>

        <div class="posts-pagination-controls">
          <div class="posts-pagination-per-page">
            <span class="posts-pagination-kicker">Por pagina</span>
            <div class="posts-pagination-chip-group">
              <?php foreach ([10, 20, 50] as $option): ?>
                <?php $active = $perPage === $option; ?>
                <a
                  data-admin-categories-link
                  class="posts-pagination-chip<?= $active ? ' is-active' : '' ?>"
                  href="<?= htmlspecialchars($buildUrl(['page' => 1, 'per_page' => $option]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                >
                  <?= $option ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>

          <nav class="posts-pagination-nav" aria-label="Paginacao das categorias">
            <a
              data-admin-categories-link
              class="posts-pagination-link posts-pagination-link-wide<?= $page <= 1 ? ' is-disabled' : '' ?>"
              href="<?= htmlspecialchars($buildUrl(['page' => max(1, $page - 1)]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            >Anterior</a>

            <?php for ($current = $start; $current <= $end; $current++): ?>
              <a
                data-admin-categories-link
                class="posts-pagination-link<?= $current === $page ? ' is-active' : '' ?>"
                href="<?= htmlspecialchars($buildUrl(['page' => $current]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
              ><?= $current ?></a>
            <?php endfor; ?>

            <a
              data-admin-categories-link
              class="posts-pagination-link posts-pagination-link-wide<?= $page >= $pages ? ' is-disabled' : '' ?>"
              href="<?= htmlspecialchars($buildUrl(['page' => min($pages, $page + 1)]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            >Proxima</a>
          </nav>
        </div>
      </div>
    </section>
  <?php endif; ?>
</section>
