<?php
declare(strict_types=1);

use App\Support\View;

$items = $items ?? [];
$summary = $summary ?? [
    'total' => 0,
    'ativas' => 0,
    'inativas' => 0,
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

    <?php View::component('admin/categories/filters', ['filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
    <?php View::component('admin/categories/table', ['items' => $items, 'filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
  </div>
</div>

<script src="<?= url('/assets/js/admin-categories.js') . '?v=' . @filemtime(dirname(__DIR__, 3) . '/public/assets/js/admin-categories.js') ?>" defer></script>