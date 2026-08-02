<?php
declare(strict_types=1);

use App\Support\View;

$items = $items ?? [];
$summary = $summary ?? ['total' => 0, 'ativos' => 0, 'inativos' => 0, 'desinscritos' => 0, 'hoje' => 0];
$pagination = $pagination ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$filters = $filters ?? ['busca' => '', 'status' => ''];
$sort = (string) ($sort ?? 'data_cadastro');
$dir = (string) ($dir ?? 'desc');
$charts = is_array($charts ?? null) ? $charts : [];
$encodeChart = static function (array $payload): string {
    return htmlspecialchars((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$updated = isset($_GET['updated']) && (string) $_GET['updated'] === '1';
$deleted = isset($_GET['deleted']) && (string) $_GET['deleted'] === '1';
$mode = (string) ($_GET['mode'] ?? '');
?>

<div class="max-w-7xl mx-auto px-4 py-6" data-admin-newsletter-root>
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Newsletter</h1>
      <div class="admin-page-subtitle">Gerencie os inscritos, acompanhe o status da base e filtre contatos rapidamente.</div>
    </div>

    <div class="admin-page-actions">
      <div class="admin-chip">Total: <?= number_format((int) ($summary['total'] ?? 0), 0, ',', '.') ?></div>
    </div>
  </div>

  <div class="space-y-6">
    <?php if ($updated): ?>
      <section class="admin-panel border border-cyan-500/30">
        <div class="text-sm font-bold text-cyan-300">
          <?php
          $statusLabels = ['ativo' => 'Inscrito marcado como ativo.', 'inativo' => 'Inscrito marcado como inativo.', 'desinscreve' => 'Inscrito marcado como desinscrito.'];
          echo htmlspecialchars($statusLabels[$mode] ?? 'Status atualizado com sucesso.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
          ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($deleted): ?>
      <section class="admin-panel border border-rose-500/30"><div class="text-sm font-bold text-rose-300">Inscrito excluido com sucesso.</div></section>
    <?php endif; ?>

    <?php View::component('admin/newsletter/summary-cards', ['summary' => $summary]); ?>

    <section class="admin-module-charts-grid" aria-label="Graficos da newsletter">
      <article class="admin-module-chart-card">
        <div class="admin-module-chart-header">
          <div>
            <h2>Status da base</h2>
            <p>Ativos, inativos e desinscritos no filtro atual.</p>
          </div>
        </div>
        <div class="admin-module-chart-shell admin-module-chart-shell-sm">
          <canvas data-admin-module-chart data-type="doughnut" data-chart="<?= $encodeChart(is_array($charts['status'] ?? null) ? $charts['status'] : []) ?>"></canvas>
          <div class="admin-module-chart-empty">Sem inscritos neste recorte.</div>
        </div>
      </article>

      <article class="admin-module-chart-card">
        <div class="admin-module-chart-header">
          <div>
            <h2>Cadastros nos ultimos 7 dias</h2>
            <p>Volume total e novos ativos por dia.</p>
          </div>
        </div>
        <div class="admin-module-chart-shell admin-module-chart-shell-sm">
          <canvas data-admin-module-chart data-type="grouped-bar" data-chart="<?= $encodeChart(is_array($charts['daily'] ?? null) ? $charts['daily'] : []) ?>"></canvas>
          <div class="admin-module-chart-empty">Sem cadastros recentes neste recorte.</div>
        </div>
      </article>
    </section>

    <?php View::component('admin/newsletter/filters', ['filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
    <?php View::component('admin/newsletter/table', ['items' => $items, 'filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
  </div>
</div>

<?php if (!isset($_GET['_partial']) || (string) $_GET['_partial'] !== '1'): ?>
<script src="<?= url('/assets/js/admin-newsletter.js') . '?v=' . @filemtime(base_path('public/assets/js/admin-newsletter.js')) ?>" defer></script>
<?php endif; ?>
