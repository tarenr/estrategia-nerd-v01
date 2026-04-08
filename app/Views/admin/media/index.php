<?php
declare(strict_types=1);

use App\Support\View;

$summary = $summary ?? ['total' => 0, 'images' => 0, 'others' => 0, 'directories' => 0, 'orphans' => 0, 'size_label' => '0 B'];
$filters = $filters ?? ['busca' => '', 'tipo' => '', 'estado' => ''];
$pagination = $pagination ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 12, 'pages' => 1];
$sort = (string) ($sort ?? 'data');
$dir = (string) ($dir ?? 'desc');
$errors = $errors ?? [];
$uploaded = isset($_GET['uploaded']) && (string) $_GET['uploaded'] === '1';
$deleted = isset($_GET['deleted']) && (string) $_GET['deleted'] === '1';
$orphanCleaned = isset($_GET['orphan_cleaned']) && (string) $_GET['orphan_cleaned'] === '1';
$orphanRemoved = max(0, (int) ($_GET['orphan_removed'] ?? 0));
?>

<div class="max-w-7xl mx-auto px-4 py-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Midia</h1>
      <div class="admin-page-subtitle">Gerencie uploads do portal, copie URLs e mantenha a base visual pronta para integrar com posts.</div>
    </div>

    <div class="admin-page-actions">
      <div class="admin-chip">Arquivos: <?= number_format((int) ($summary['total'] ?? 0), 0, ',', '.') ?></div>
    </div>
  </div>

  <div class="space-y-6">
    <?php if ($uploaded): ?>
      <section class="admin-panel border border-emerald-500/30"><div class="text-sm font-bold text-emerald-300">Arquivo enviado com sucesso.</div><div class="text-xs text-slate-400 mt-1">A nova midia ja esta disponivel para copiar URL, visualizar ou reaproveitar em posts.</div></section>
    <?php endif; ?>

    <?php if ($deleted): ?>
      <section class="admin-panel border border-rose-500/30"><div class="text-sm font-bold text-rose-300">Arquivo excluido com sucesso.</div><div class="text-xs text-slate-400 mt-1">A midia removida nao aparece mais na biblioteca de uploads.</div></section>
    <?php endif; ?>

    <?php if ($orphanCleaned): ?>
      <section class="admin-panel border border-amber-500/30"><div class="text-sm font-bold text-amber-200">Limpeza global concluida.</div><div class="text-xs text-slate-400 mt-1"><?= $orphanRemoved > 0 ? $orphanRemoved . ' arquivo(s) orfao(s) removido(s) da biblioteca.' : 'Nenhuma imagem orfa visivel foi removida.' ?></div></section>
    <?php endif; ?>

    <?php View::component('admin/media/summary-cards', ['summary' => $summary]); ?>
    <?php View::component('admin/media/upload-panel', ['errors' => $errors]); ?>
    <?php View::component('admin/media/filters', ['filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
    <?php View::component('admin/media/table', ['items' => $pagination['items'] ?? [], 'filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
    <?php View::component('admin/media/pagination', ['filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
  </div>
</div>

<script src="<?= url('/assets/js/admin-media.js') . '?v=' . @filemtime(base_path('public/assets/js/admin-media.js')) ?>" defer></script>
