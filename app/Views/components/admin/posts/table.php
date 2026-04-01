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

$statusClasses = static function (string $status): string {
    return match ($status) {
        'publicado' => 'status-badge status-publicado',
        'rascunho' => 'status-badge status-rascunho',
        'agendado' => 'status-badge status-agendado',
        default => 'status-badge',
    };
};
?>

<section class="admin-panel">
  <div class="flex items-center justify-between mb-6 gap-4">
    <div>
      <h3 class="font-orbitron text-xl font-black text-white">Lista de Posts</h3>
      <div class="text-xs text-slate-400 mt-1"><?= number_format((int) ($pagination['total'] ?? 0), 0, ',', '.') ?> resultado(s) encontrado(s)</div>
    </div>
    <span class="text-cyan-400 text-sm font-bold uppercase"><?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> / <?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
  </div>

  <?php if ($items === []): ?>
    <div class="text-center py-12 border-2 border-dashed border-gray-700 rounded-xl">
      <div class="text-3xl mb-4 font-orbitron font-black text-cyan-300">SEM</div>
      <h4 class="text-xl font-bold text-white mb-2">Nenhum post encontrado</h4>
      <div class="text-slate-400 text-sm">Ajuste os filtros ou limpe a busca para ver mais resultados.</div>
    </div>
  <?php else: ?>
    <div class="overflow-x-auto rounded-xl border border-slate-800">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-800/70 text-slate-300">
          <tr class="text-left">
            <th class="px-4 py-3 font-semibold"><a data-admin-posts-link class="inline-flex items-center gap-2 hover:text-cyan-300 transition" href="<?= htmlspecialchars($sortLink('titulo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Titulo <?= $sortIcon('titulo') ?></a></th>
            <th class="px-4 py-3 font-semibold"><a data-admin-posts-link class="inline-flex items-center gap-2 hover:text-cyan-300 transition" href="<?= htmlspecialchars($sortLink('categoria'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Categoria <?= $sortIcon('categoria') ?></a></th>
            <th class="px-4 py-3 font-semibold"><a data-admin-posts-link class="inline-flex items-center gap-2 hover:text-cyan-300 transition" href="<?= htmlspecialchars($sortLink('status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Status <?= $sortIcon('status') ?></a></th>
            <th class="px-4 py-3 font-semibold"><a data-admin-posts-link class="inline-flex items-center gap-2 hover:text-cyan-300 transition" href="<?= htmlspecialchars($sortLink('data'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Publicacao <?= $sortIcon('data') ?></a></th>
            <th class="px-4 py-3 font-semibold"><a data-admin-posts-link class="inline-flex items-center gap-2 hover:text-cyan-300 transition" href="<?= htmlspecialchars($sortLink('views'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Views <?= $sortIcon('views') ?></a></th>
            <th class="px-4 py-3 font-semibold"><a data-admin-posts-link class="inline-flex items-center gap-2 hover:text-cyan-300 transition" href="<?= htmlspecialchars($sortLink('curtidas'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Curtidas <?= $sortIcon('curtidas') ?></a></th>
            <th class="px-4 py-3 font-semibold"><a data-admin-posts-link class="inline-flex items-center gap-2 hover:text-cyan-300 transition" href="<?= htmlspecialchars($sortLink('comentarios'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Comentarios <?= $sortIcon('comentarios') ?></a></th>
            <th class="px-4 py-3 font-semibold text-right">Acoes</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/70">
          <?php foreach ($items as $item): ?>
            <?php
              $titulo = (string) ($item['titulo'] ?? '');
              $slug = (string) ($item['slug'] ?? '');
              $categoriaNome = (string) ($item['categoria_nome'] ?? 'Sem categoria');
              $categoriaCor = (string) ($item['categoria_cor'] ?? '#00d4ff');
              $status = (string) ($item['status'] ?? '');
              $destaque = (int) ($item['destaque'] ?? 0) === 1;
              $editUrl = function_exists('url') ? url('/admin/editar-post?id=' . (int) ($item['id'] ?? 0)) : '#';
              $viewUrl = function_exists('url') ? url('/post/' . $slug) : '#';
            ?>
            <tr class="hover:bg-slate-800/40 transition">
              <td class="px-4 py-4 align-top">
                <div class="font-semibold text-slate-100"><?= htmlspecialchars($titulo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="mt-1 text-xs text-slate-400">#<?= (int) ($item['id'] ?? 0) ?><?php if ($slug !== ''): ?> - <?= htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?php endif; ?></div>
                <?php if ($destaque): ?><div class="mt-2 text-[11px] text-cyan-300 font-bold uppercase">Destaque</div><?php endif; ?>
              </td>
              <td class="px-4 py-4 align-top"><div class="flex items-center gap-2 text-slate-200 text-xs"><span class="w-2 h-2 rounded-full" style="background: <?= htmlspecialchars($categoriaCor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></span><span><?= htmlspecialchars($categoriaNome, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div></td>
              <td class="px-4 py-4 align-top"><span class="<?= htmlspecialchars($statusClasses($status), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></td>
              <td class="px-4 py-4 align-top text-slate-300"><?= htmlspecialchars($formatDate($item['data_publicacao'] ?? null), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
              <td class="px-4 py-4 align-top text-slate-200"><?= number_format((int) ($item['views'] ?? 0), 0, ',', '.') ?></td>
              <td class="px-4 py-4 align-top text-slate-200"><?= number_format((int) ($item['curtidas'] ?? 0), 0, ',', '.') ?></td>
              <td class="px-4 py-4 align-top text-slate-200"><?= number_format((int) ($item['comentarios_count'] ?? 0), 0, ',', '.') ?></td>
              <td class="px-4 py-4 align-top"><div class="flex items-center justify-end gap-2"><a class="btn-edit px-3 py-2 rounded-lg text-xs font-bold" href="<?= htmlspecialchars($editUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Editar</a><a class="px-3 py-2 rounded-lg text-xs font-bold border border-slate-700 text-slate-300 hover:border-cyan-500/40 hover:text-cyan-200 transition" href="<?= htmlspecialchars($viewUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noreferrer">Ver</a></div></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
