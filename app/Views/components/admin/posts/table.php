<?php
declare(strict_types=1);

$items = $items ?? [];
$filters = $filters ?? ['status' => '', 'categoria' => 0, 'destaque' => '', 'busca' => ''];
$pagination = $pagination ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$sort = (string) ($sort ?? 'data');
$dir = (string) ($dir ?? 'desc');
$baseUrl = function_exists('url') ? url('/admin/posts') : '/admin/posts';
$instagramLinks = is_array($instagram_links ?? null) ? $instagram_links : null;

$buildUrl = static function (array $overrides = []) use ($baseUrl, $filters, $pagination, $sort, $dir): string {
    $query = [
        'status' => (string) ($filters['status'] ?? ''),
        'categoria' => (int) ($filters['categoria'] ?? 0),
        'destaque' => (string) ($filters['destaque'] ?? ''),
        'busca' => (string) ($filters['busca'] ?? ''),
        'instagram' => (string) ($filters['instagram'] ?? ''),
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
      <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-400" aria-label="Legenda da tabela">
        <span><i class="fa-solid fa-eye" aria-hidden="true"></i> Views</span>
        <span><i class="fa-solid fa-heart" aria-hidden="true"></i> Curtidas</span>
        <span><i class="fa-solid fa-comment" aria-hidden="true"></i> Comentários</span>
        <span><i class="fa-brands fa-instagram text-pink-400" aria-hidden="true"></i> Instagram: <span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-400 align-middle"></span> tem post <span class="ml-1 inline-block h-2.5 w-2.5 rounded-full bg-rose-500 align-middle"></span> não tem</span>
      </div>
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
          <col class="posts-table-col-metric">
          <col class="posts-table-col-actions">
        </colgroup>
        <thead class="posts-table-thead">
          <tr>
            <th class="posts-table-th posts-table-th-left"><a data-admin-posts-link class="posts-table-sort posts-table-sort-left" href="<?= htmlspecialchars($sortLink('titulo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Titulo <?= $sortIcon('titulo') ?></a></th>
            <th class="posts-table-th posts-table-th-left"><a data-admin-posts-link class="posts-table-sort posts-table-sort-left" href="<?= htmlspecialchars($sortLink('categoria'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Categoria <?= $sortIcon('categoria') ?></a></th>
            <th class="posts-table-th posts-table-th-left"><a data-admin-posts-link class="posts-table-sort posts-table-sort-left" href="<?= htmlspecialchars($sortLink('status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Status <?= $sortIcon('status') ?></a></th>
            <th class="posts-table-th posts-table-th-left"><a data-admin-posts-link class="posts-table-sort posts-table-sort-left" href="<?= htmlspecialchars($sortLink('data'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Publicacao <?= $sortIcon('data') ?></a></th>
            <th class="posts-table-th posts-table-th-center"><a data-admin-posts-link class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('views'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="Views" aria-label="Ordenar por views"><i class="fa-solid fa-eye" aria-hidden="true"></i> <?= $sortIcon('views') ?></a></th>
            <th class="posts-table-th posts-table-th-center"><a data-admin-posts-link class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('curtidas'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="Curtidas" aria-label="Ordenar por curtidas"><i class="fa-solid fa-heart" aria-hidden="true"></i> <?= $sortIcon('curtidas') ?></a></th>
            <th class="posts-table-th posts-table-th-center"><a data-admin-posts-link class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('comentarios'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="Comentários" aria-label="Ordenar por comentários"><i class="fa-solid fa-comment" aria-hidden="true"></i> <?= $sortIcon('comentarios') ?></a></th>
            <th class="posts-table-th posts-table-th-center" title="Post no Instagram"><i class="fa-brands fa-instagram text-pink-400" aria-hidden="true"></i><span class="sr-only">Instagram</span></th>
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
              $postId = (int) ($item['id'] ?? 0);
              $igLink = $instagramLinks[$postId] ?? null;
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
              <td class="posts-table-td posts-table-td-center">
                <?php if ($instagramLinks === null): ?>
                  <span class="inline-block h-3 w-3 rounded-full bg-slate-600" title="Não foi possível consultar o Instagram agora"></span>
                <?php elseif ($igLink !== null): ?>
                  <a href="<?= htmlspecialchars(url('/admin/instagram/posts/' . (int) $igLink['id'] . ((string) $igLink['status'] === 'publicado' ? '' : '/editar')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-block h-3 w-3 rounded-full bg-emerald-400 ring-2 ring-emerald-400/20 hover:ring-emerald-300" title="<?= htmlspecialchars('Post no Instagram #' . (int) $igLink['id'] . ' (' . (string) $igLink['status'] . ')', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><span class="sr-only">Tem post no Instagram</span></a>
                <?php else: ?>
                  <a href="<?= htmlspecialchars(url('/admin/editar-post?id=' . $postId . '&ig=1'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-block h-3 w-3 rounded-full bg-rose-500 ring-2 ring-rose-500/20 hover:ring-rose-400" title="Sem post no Instagram, clique para criar"><span class="sr-only">Sem post no Instagram</span></a>
                <?php endif; ?>
              </td>
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