<?php
declare(strict_types=1);

$filters = $filters ?? ['busca' => '', 'tipo' => '', 'promocao' => '', 'status' => '', 'destaque' => '', 'monitoramento' => ''];
$pagination = $pagination ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$sort = (string) ($sort ?? 'posicao');
$dir = (string) ($dir ?? 'asc');
$baseUrl = function_exists('url') ? url('/admin/links') : '/admin/links';

$buildUrl = static function (array $overrides = []) use ($baseUrl, $filters, $pagination, $sort, $dir): string {
    $query = [
        'busca' => (string) ($filters['busca'] ?? ''),
        'tipo' => (string) ($filters['tipo'] ?? ''),
        'promocao' => (string) ($filters['promocao'] ?? ''),
        'status' => (string) ($filters['status'] ?? ''),
        'destaque' => (string) ($filters['destaque'] ?? ''),
        'monitoramento' => (string) ($filters['monitoramento'] ?? ''),
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

$page = max(1, (int) ($pagination['page'] ?? 1));
$pages = max(1, (int) ($pagination['pages'] ?? 1));
$total = max(0, (int) ($pagination['total'] ?? 0));
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

<section class="admin-panel posts-pagination-panel">
  <div class="posts-pagination-shell">
    <div class="posts-pagination-summary">
      <?php if ($total > 0): ?>
        Exibindo <span><?= number_format($firstItem, 0, ',', '.') ?></span> ate <span><?= number_format($lastItem, 0, ',', '.') ?></span> de <span><?= number_format($total, 0, ',', '.') ?></span> links
      <?php else: ?>
        Nenhum link para paginar no momento.
      <?php endif; ?>
    </div>

    <div class="posts-pagination-controls">
      <div class="posts-pagination-per-page">
        <span class="posts-pagination-kicker">Por pagina</span>
        <div class="posts-pagination-chip-group">
          <?php foreach ([10, 20, 50] as $option): ?>
            <?php $active = $perPage === $option; ?>
            <a data-admin-links-link class="posts-pagination-chip<?= $active ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildUrl(['page' => 1, 'per_page' => $option]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= $option ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <nav class="posts-pagination-nav" aria-label="Paginacao dos links">
        <a data-admin-links-link class="posts-pagination-link posts-pagination-link-wide<?= $page <= 1 ? ' is-disabled' : '' ?>" href="<?= htmlspecialchars($buildUrl(['page' => max(1, $page - 1)]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Anterior</a>

        <?php for ($current = $start; $current <= $end; $current++): ?>
          <a data-admin-links-link class="posts-pagination-link<?= $current === $page ? ' is-active' : '' ?>" href="<?= htmlspecialchars($buildUrl(['page' => $current]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= $current ?></a>
        <?php endfor; ?>

        <a data-admin-links-link class="posts-pagination-link posts-pagination-link-wide<?= $page >= $pages ? ' is-disabled' : '' ?>" href="<?= htmlspecialchars($buildUrl(['page' => min($pages, $page + 1)]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Proxima</a>
      </nav>
    </div>
  </div>
</section>