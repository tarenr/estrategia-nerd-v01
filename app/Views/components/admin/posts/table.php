<?php
declare(strict_types=1);

$items = $items ?? [];
$filters = $filters ?? ['status' => '', 'categoria' => 0, 'destaque' => '', 'busca' => ''];
$pagination = $pagination ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$sort = (string) ($sort ?? 'data');
$dir = (string) ($dir ?? 'desc');
$baseUrl = function_exists('url') ? url('/admin/posts') : '/admin/posts';

$buildUrl = static function (array $overrides = []) use ($baseUrl, $filters, $pagination, $sort, $dir): string {
    $query = [
        'status' => (string) ($filters['status'] ?? ''),
        'categoria' => (int) ($filters['categoria'] ?? 0),
        'destaque' => (string) ($filters['destaque'] ?? ''),
        'busca' => (string) ($filters['busca'] ?? ''),
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

$formatDate = static function ($value): string {
    if (!$value) {
        return '-';
    }

    try {
        return (new DateTimeImmutable((string) $value))->format('d/m/Y H:i');
    } catch (Throwable) {
        return (string) $value;
    }
};

$cleanTitle = static function (?string $value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return 'Sem titulo';
    }

    $value = preg_replace('/\[\[(.*?)\]\]/u', '$1', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    $value = trim($value);

    return $value !== '' ? $value : 'Sem titulo';
};

$statusClasses = static function (string $status): string {
    return match ($status) {
        'publicado' => 'status-badge status-publicado',
        'rascunho' => 'status-badge status-rascunho',
        'agendado' => 'status-badge status-agendado',
        default => 'status-badge',
    };
};
?>

<section class="admin-panel posts-table-panel">
  <div class="posts-table-head">
    <div>
      <h3 class="font-orbitron text-xl font-black text-white">Lista de Posts</h3>
      <div class="text-xs text-slate-400 mt-1"><?= number_format((int) ($pagination['total'] ?? 0), 0, ',', '.') ?> resultado(s) encontrado(s)</div>
    </div>
    <span class="posts-table-order"><?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> / <?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
  </div>

  <?php if ($items === []): ?>
    <div class="text-center py-12 border-2 border-dashed border-gray-700 rounded-xl">
      <div class="text-3xl mb-4 font-orbitron font-black text-cyan-300">SEM</div>
      <h4 class="text-xl font-bold text-white mb-2">Nenhum post encontrado</h4>
      <div class="text-slate-400 text-sm">Ajuste os filtros ou limpe a busca para ver mais resultados.</div>
    </div>
  <?php else: ?>
    <div class="posts-table-wrap">
      <table class="posts-table">
        <colgroup>
          <col class="posts-table-col-title">
          <col class="posts-table-col-category">
          <col class="posts-table-col-status">
          <col class="posts-table-col-date">
          <col class="posts-table-col-metric">
          <col class="posts-table-col-metric">
          <col class="posts-table-col-metric">
          <col class="posts-table-col-actions">
        </colgroup>
        <thead class="posts-table-thead">
          <tr>
            <th class="posts-table-th posts-table-th-left"><a data-admin-posts-link class="posts-table-sort posts-table-sort-left" href="<?= htmlspecialchars($sortLink('titulo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Titulo <?= $sortIcon('titulo') ?></a></th>
            <th class="posts-table-th posts-table-th-left"><a data-admin-posts-link class="posts-table-sort posts-table-sort-left" href="<?= htmlspecialchars($sortLink('categoria'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Categoria <?= $sortIcon('categoria') ?></a></th>
            <th class="posts-table-th posts-table-th-left"><a data-admin-posts-link class="posts-table-sort posts-table-sort-left" href="<?= htmlspecialchars($sortLink('status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Status <?= $sortIcon('status') ?></a></th>
            <th class="posts-table-th posts-table-th-left"><a data-admin-posts-link class="posts-table-sort posts-table-sort-left" href="<?= htmlspecialchars($sortLink('data'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Publicacao <?= $sortIcon('data') ?></a></th>
            <th class="posts-table-th posts-table-th-center"><a data-admin-posts-link class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('views'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Views <?= $sortIcon('views') ?></a></th>
            <th class="posts-table-th posts-table-th-center"><a data-admin-posts-link class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('curtidas'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Curtidas <?= $sortIcon('curtidas') ?></a></th>
            <th class="posts-table-th posts-table-th-center"><a data-admin-posts-link class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('comentarios'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Comentarios <?= $sortIcon('comentarios') ?></a></th>
            <th class="posts-table-th posts-table-th-center">Acoes</th>
          </tr>
        </thead>
        <tbody class="posts-table-body">
          <?php foreach ($items as $item): ?>
            <?php
              $titulo = $cleanTitle((string) ($item['titulo'] ?? ''));
              $slug = (string) ($item['slug'] ?? '');
              $categoriaNome = (string) ($item['categoria_nome'] ?? 'Sem categoria');
              $categoriaCor = (string) ($item['categoria_cor'] ?? '#00d4ff');
              $status = (string) ($item['status'] ?? '');
              $destaque = (int) ($item['destaque'] ?? 0) === 1;
              $editUrl = function_exists('url') ? url('/admin/editar-post?id=' . (int) ($item['id'] ?? 0)) : '#';
              $deleteUrl = function_exists('url') ? url('/admin/excluir-post?id=' . (int) ($item['id'] ?? 0)) : '#';
              $viewUrl = $slug !== '' && function_exists('url') ? url('/post/' . $slug) : '#';
              $views = (int) ($item['views'] ?? 0);
              $curtidas = (int) ($item['curtidas'] ?? 0);
              $comentarios = (int) ($item['comentarios_count'] ?? 0);
            ?>
            <tr class="posts-table-row<?= $destaque ? ' is-highlight' : '' ?>">
              <td class="posts-table-td posts-table-title-cell">
                <div class="posts-table-title-top">
                  <a class="posts-table-title-link" href="<?= htmlspecialchars($editUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($titulo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
                  <?php if ($destaque): ?><span class="posts-table-highlight">Destaque</span><?php endif; ?>
                </div>
                <div class="posts-table-subline">#<?= (int) ($item['id'] ?? 0) ?><?php if ($slug !== ''): ?> <span class="posts-table-subline-dot">•</span> <?= htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?php endif; ?></div>
              </td>
              <td class="posts-table-td">
                <div class="posts-table-category"><span class="posts-table-category-dot" style="background: <?= htmlspecialchars($categoriaCor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></span><span><?= htmlspecialchars($categoriaNome, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
              </td>
              <td class="posts-table-td">
                <span class="<?= htmlspecialchars($statusClasses($status), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              </td>
              <td class="posts-table-td">
                <div class="posts-table-date"><?= htmlspecialchars($formatDate($item['data_publicacao'] ?? null), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </td>
              <td class="posts-table-td posts-table-td-center"><span class="posts-table-metric<?= $views === 0 ? ' is-zero' : '' ?>"><?= number_format($views, 0, ',', '.') ?></span></td>
              <td class="posts-table-td posts-table-td-center"><span class="posts-table-metric<?= $curtidas === 0 ? ' is-zero' : '' ?>"><?= number_format($curtidas, 0, ',', '.') ?></span></td>
              <td class="posts-table-td posts-table-td-center"><span class="posts-table-metric<?= $comentarios === 0 ? ' is-zero' : '' ?>"><?= number_format($comentarios, 0, ',', '.') ?></span></td>
              <td class="posts-table-td">
                <div class="posts-table-actions">
                  <a
                    class="posts-table-action posts-table-action-view"
                    href="<?= htmlspecialchars($viewUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                    target="_blank"
                    rel="noreferrer"
                    aria-label="Ver post"
                    title="Ver post"
                  >
                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                  </a>
                  <a
                    class="posts-table-action posts-table-action-delete"
                    href="<?= htmlspecialchars($deleteUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                    aria-label="Excluir post"
                    title="Excluir post"
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
  <?php endif; ?>
</section>