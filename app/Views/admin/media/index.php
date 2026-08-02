<?php
declare(strict_types=1);

use App\Support\View;

$summary = $summary ?? ['total' => 0, 'images' => 0, 'audio' => 0, 'video' => 0, 'others' => 0, 'directories' => 0, 'orphans' => 0, 'size_label' => '0 B'];
$filters = $filters ?? ['busca' => '', 'tipo' => '', 'estado' => ''];
$pagination = $pagination ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 12, 'pages' => 1];
$sort = (string) ($sort ?? 'data');
$dir = (string) ($dir ?? 'desc');
$errors = $errors ?? [];
$upload = $upload ?? ['accept' => '', 'max_size_label' => '8 MB'];
$charts = is_array($charts ?? null) ? $charts : [];
$encodeChart = static function (array $payload): string {
    return htmlspecialchars((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$uploaded = isset($_GET['uploaded']) && (string) $_GET['uploaded'] === '1';
$deleted = isset($_GET['deleted']) && (string) $_GET['deleted'] === '1';
$orphanCleaned = isset($_GET['orphan_cleaned']) && (string) $_GET['orphan_cleaned'] === '1';
$orphanRemoved = max(0, (int) ($_GET['orphan_removed'] ?? 0));
$orphanFailed = max(0, (int) ($_GET['orphan_failed'] ?? 0));
?>

<div class="max-w-7xl mx-auto px-4 py-6" data-admin-media-root>
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Midia</h1>
      <div class="admin-page-subtitle">Gerencie uploads do portal, copie URLs e acompanhe onde cada arquivo esta sendo usado.</div>
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
      <section class="admin-panel border border-amber-500/30"><div class="text-sm font-bold text-amber-200">Limpeza global concluida.</div><div class="text-xs text-slate-400 mt-1"><?php if ($orphanRemoved > 0): ?><?= $orphanRemoved ?> arquivo(s) orfao(s) removido(s) da biblioteca.<?php else: ?>Nenhuma midia orfa visivel foi removida.<?php endif; ?><?php if ($orphanFailed > 0): ?> <?= $orphanFailed ?> arquivo(s) nao puderam ser removidos por validacao de caminho ou permissao.<?php endif; ?></div></section>
    <?php endif; ?>

    <?php View::component('admin/media/summary-cards', ['summary' => $summary]); ?>

    <section class="admin-module-charts-grid" aria-label="Graficos da midia">
      <article class="admin-module-chart-card">
        <div class="admin-module-chart-header">
          <div>
            <h2>Tipos de arquivo</h2>
            <p>Composicao da biblioteca por formato.</p>
          </div>
        </div>
        <div class="admin-module-chart-shell admin-module-chart-shell-sm">
          <canvas data-admin-module-chart data-type="doughnut" data-chart="<?= $encodeChart(is_array($charts['types'] ?? null) ? $charts['types'] : []) ?>"></canvas>
          <div class="admin-module-chart-empty">Sem arquivos na biblioteca.</div>
        </div>
      </article>

      <article class="admin-module-chart-card">
        <div class="admin-module-chart-header">
          <div>
            <h2>Estado de uso</h2>
            <p>Arquivos em uso, disponiveis, orfaos e protegidos.</p>
          </div>
        </div>
        <div class="admin-module-chart-shell admin-module-chart-shell-sm">
          <canvas data-admin-module-chart data-type="bar" data-chart="<?= $encodeChart(is_array($charts['usage'] ?? null) ? $charts['usage'] : []) ?>"></canvas>
          <div class="admin-module-chart-empty">Sem estados para comparar.</div>
        </div>
      </article>

      <article class="admin-module-chart-card admin-module-chart-card-wide">
        <div class="admin-module-chart-header">
          <div>
            <h2>Peso por tipo</h2>
            <p>Distribuicao do armazenamento por familia de arquivo.</p>
          </div>
        </div>
        <div class="admin-module-chart-shell">
          <canvas data-admin-module-chart data-type="bar" data-chart="<?= $encodeChart(is_array($charts['size'] ?? null) ? $charts['size'] : []) ?>"></canvas>
          <div class="admin-module-chart-empty">Sem tamanho registrado para comparar.</div>
        </div>
      </article>
    </section>

    <?php View::component('admin/media/upload-panel', ['errors' => $errors, 'upload' => $upload, 'filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
    <?php View::component('admin/media/filters', ['filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
    <?php View::component('admin/media/table', ['items' => $pagination['items'] ?? [], 'filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
    <?php View::component('admin/media/pagination', ['filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
  </div>
</div>

<script src="<?= url('/assets/js/admin-media.js') . '?v=' . @filemtime(base_path('public/assets/js/admin-media.js')) ?>" defer></script>
