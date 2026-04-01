<?php
declare(strict_types=1);

$filters = $filters ?? ['status' => '', 'categoria' => 0, 'destaque' => '', 'busca' => ''];
$pagination = $pagination ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$sort = (string)($sort ?? 'data');
$dir = (string)($dir ?? 'desc');
$page = max(1, (int)($pagination['page'] ?? 1));
$pages = max(1, (int)($pagination['pages'] ?? 1));
$perPage = max(5, (int)($pagination['per_page'] ?? 10));
$total = max(0, (int)($pagination['total'] ?? 0));
$baseUrl = function_exists('url') ? url('/admin/posts') : '/admin/posts';
$buildUrl = static function (int $targetPage, ?int $targetPerPage = null) use ($baseUrl, $filters, $sort, $dir, $perPage): string {
    $query = ['status' => (string)($filters['status'] ?? ''),'categoria' => (int)($filters['categoria'] ?? 0),'destaque' => (string)($filters['destaque'] ?? ''),'busca' => (string)($filters['busca'] ?? ''),'sort' => $sort,'dir' => $dir,'page' => max(1, $targetPage),'per_page' => $targetPerPage ?? $perPage];
    $query = array_filter($query, static fn ($value): bool => !($value === '' || $value === null || $value === 0));
    $qs = http_build_query($query);
    return $qs !== '' ? $baseUrl . '?' . $qs : $baseUrl;
};
$start = max(1, $page - 2);
$end = min($pages, $page + 2);
if (($end - $start) < 4) { $start = max(1, $end - 4); $end = min($pages, $start + 4); }
$firstItem = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$lastItem = $total > 0 ? min($total, $page * $perPage) : 0;
?>

<section class="admin-panel">
  <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
    <div class="text-sm text-slate-300"><?php if ($total > 0): ?>Exibindo <span class="font-semibold text-slate-100"><?= number_format($firstItem, 0, ',', '.') ?></span> ate <span class="font-semibold text-slate-100"><?= number_format($lastItem, 0, ',', '.') ?></span> de <span class="font-semibold text-slate-100"><?= number_format($total, 0, ',', '.') ?></span> posts<?php else: ?>Nenhum post para paginar no momento.<?php endif; ?></div>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
      <div class="flex items-center gap-2 text-sm"><span class="text-slate-400">Por pagina</span><?php foreach ([10, 20, 50] as $option): ?><?php $active = $perPage === $option; ?><a data-admin-posts-link class="px-3 py-2 rounded-xl text-xs font-black border transition-all <?= $active ? 'bg-cyan-500/20 border-cyan-400/40 text-cyan-200' : 'bg-slate-800/40 border-slate-700 text-slate-300 hover:border-cyan-500/40 hover:text-cyan-200' ?>" href="<?= htmlspecialchars($buildUrl(1, $option), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= $option ?></a><?php endforeach; ?></div>
      <nav class="flex items-center gap-2" aria-label="Paginacao dos posts">
        <a data-admin-posts-link class="px-3 py-2 rounded-xl text-xs font-black border transition-all <?= $total === 0 || $page <= 1 ? 'pointer-events-none border-slate-800 text-slate-600' : 'bg-slate-800/40 border-slate-700 text-slate-300 hover:border-cyan-500/40 hover:text-cyan-200' ?>" href="<?= htmlspecialchars($buildUrl($page - 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Anterior</a>
        <?php for ($current = $start; $current <= $end; $current++): ?><a data-admin-posts-link class="min-w-[40px] px-3 py-2 rounded-xl text-center text-xs font-black border transition-all <?= $current === $page ? 'bg-cyan-500/20 border-cyan-400/40 text-cyan-200' : 'bg-slate-800/40 border-slate-700 text-slate-300 hover:border-cyan-500/40 hover:text-cyan-200' ?>" href="<?= htmlspecialchars($buildUrl($current), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= $current ?></a><?php endfor; ?>
        <a data-admin-posts-link class="px-3 py-2 rounded-xl text-xs font-black border transition-all <?= $total === 0 || $page >= $pages ? 'pointer-events-none border-slate-800 text-slate-600' : 'bg-slate-800/40 border-slate-700 text-slate-300 hover:border-cyan-500/40 hover:text-cyan-200' ?>" href="<?= htmlspecialchars($buildUrl($page + 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Proxima</a>
      </nav>
    </div>
  </div>
</section>

