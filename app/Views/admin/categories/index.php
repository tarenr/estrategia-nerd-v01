<?php
declare(strict_types=1);

use App\Support\View;

$items = $items ?? [];
$summary = $summary ?? [
    'total' => 0,
    'ativas' => 0,
    'inativas' => 0,
    'indexaveis' => 0,
    'noindex' => 0,
    'menu' => 0,
    'fora_menu' => 0,
    'com_posts' => 0,
    'sem_posts' => 0,
    'total_posts_vinculados' => 0,
    'total_views' => 0,
    'cobertura_ativas' => 0.0,
    'cobertura_editorial' => 0.0,
    'media_posts_por_categoria' => 0.0,
    'media_views_por_categoria' => 0.0,
];
$pagination = $pagination ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$filters = $filters ?? ['busca' => '', 'status' => ''];
$sort = (string) ($sort ?? 'ordem');
$dir = (string) ($dir ?? 'asc');
$charts = is_array($charts ?? null) ? $charts : [];
$encodeChart = static function (array $payload): string {
    return htmlspecialchars((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$created = isset($_GET['created']) && (string) $_GET['created'] === '1';
$updated = isset($_GET['updated']) && (string) $_GET['updated'] === '1';
$deleted = isset($_GET['deleted']) && (string) $_GET['deleted'] === '1';
$deactivated = isset($_GET['deactivated']) && (string) $_GET['deactivated'] === '1';
?>

<div class="max-w-7xl mx-auto px-4 py-6" data-admin-categories-root>
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Categorias</h1>
      <div class="admin-page-subtitle">Gerencie a organizacao editorial que alimenta filtros e formularios de posts.</div>
    </div>

    <div class="admin-page-actions">
      <div class="admin-chip">Total: <?= number_format((int) ($summary['total'] ?? 0), 0, ',', '.') ?></div>
      <a href="<?= url('/admin/criar-categoria') ?>" class="admin-btn admin-btn-primary">Criar Categoria</a>
    </div>
  </div>

  <div class="space-y-6">
    <?php if ($created): ?>
      <section class="admin-panel border border-emerald-500/30"><div class="text-sm font-bold text-emerald-300">Categoria criada com sucesso.</div></section>
    <?php endif; ?>
    <?php if ($updated): ?>
      <section class="admin-panel border border-cyan-500/30"><div class="text-sm font-bold text-cyan-300">Categoria atualizada com sucesso.</div></section>
    <?php endif; ?>
    <?php if ($deleted): ?>
      <section class="admin-panel border border-rose-500/30"><div class="text-sm font-bold text-rose-300">Categoria excluida com sucesso.</div></section>
    <?php endif; ?>
    <?php if ($deactivated): ?>
      <section class="admin-panel border border-amber-500/30">
        <div class="text-sm font-bold text-amber-300">Categoria desativada com sucesso.</div>
        <div class="text-xs text-slate-400 mt-1">Como havia posts vinculados, o sistema preservou a relacao e removeu apenas sua disponibilidade no seletor.</div>
      </section>
    <?php endif; ?>

    <?php View::component('admin/categories/summary-cards', ['summary' => $summary]); ?>

    <section class="admin-module-charts-grid" aria-label="Graficos das categorias">
      <article class="admin-module-chart-card">
        <div class="admin-module-chart-header">
          <div>
            <h2>Configuracao editorial</h2>
            <p>Status, indexacao e disponibilidade no filtro atual.</p>
          </div>
        </div>
        <div class="admin-module-chart-shell admin-module-chart-shell-sm">
          <canvas data-admin-module-chart data-type="bar" data-chart="<?= $encodeChart(is_array($charts['status'] ?? null) ? $charts['status'] : []) ?>"></canvas>
          <div class="admin-module-chart-empty">Sem categorias neste recorte.</div>
        </div>
      </article>

      <article class="admin-module-chart-card">
        <div class="admin-module-chart-header">
          <div>
            <h2>Desempenho por categoria</h2>
            <p>Views e posts vinculados nas principais categorias.</p>
          </div>
        </div>
        <div class="admin-module-chart-shell admin-module-chart-shell-sm">
          <canvas data-admin-module-chart data-type="grouped-bar" data-chart="<?= $encodeChart(is_array($charts['performance'] ?? null) ? $charts['performance'] : []) ?>"></canvas>
          <div class="admin-module-chart-empty">Sem posts vinculados neste recorte.</div>
        </div>
      </article>
    </section>

    <?php View::component('admin/categories/filters', ['filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
    <?php View::component('admin/categories/table', ['items' => $items, 'filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
  </div>
</div>

<?php if (!isset($_GET['_partial']) || (string) $_GET['_partial'] !== '1'): ?>
<script src="<?= url('/assets/js/admin-categories.js') . '?v=' . @filemtime(base_path('public/assets/js/admin-categories.js')) ?>" defer></script>
<?php endif; ?>
