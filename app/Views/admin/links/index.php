<?php
declare(strict_types=1);

use App\Support\View;

$items = $items ?? [];
$summary = $summary ?? ['total' => 0, 'ativos' => 0, 'promocoes' => 0, 'produtos' => 0, 'cupons' => 0, 'sociais' => 0];
$pagination = $pagination ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$filters = $filters ?? ['busca' => '', 'tipo' => '', 'promocao' => '', 'status' => '', 'destaque' => '', 'monitoramento' => ''];
$currentFeatured = is_array($current_featured ?? null) ? $current_featured : null;
$sort = (string) ($sort ?? 'posicao');
$dir = (string) ($dir ?? 'asc');
$created = isset($_GET['created']) && (string) $_GET['created'] === '1';
$updated = isset($_GET['updated']) && (string) $_GET['updated'] === '1';
$deleted = isset($_GET['deleted']) && (string) $_GET['deleted'] === '1';
$mode = (string) ($_GET['mode'] ?? '');
?>

<div class="max-w-7xl mx-auto px-4 py-6" data-admin-links-root>
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Links</h1>
      <div class="admin-page-subtitle">Gerencie toda a base da Central Nerd em um unico lugar: produtos, cupons, conteudo, redes e servicos.</div>
    </div>

    <div class="admin-page-actions">
      <div class="admin-chip">Total: <?= number_format((int) ($summary['total'] ?? 0), 0, ',', '.') ?></div>
      <a href="<?= url('/admin/criar-link') ?>" class="admin-btn admin-btn-primary">Criar Link</a>
    </div>
  </div>

  <div class="space-y-6">
    <?php if ($created): ?>
      <section class="admin-panel border border-emerald-500/30"><div class="text-sm font-bold text-emerald-300">Link criado com sucesso.</div></section>
    <?php endif; ?>
    <?php if ($updated): ?>
      <section class="admin-panel border border-cyan-500/30">
        <div class="text-sm font-bold text-cyan-300">
          <?php
          $modeLabels = [
              'status_ativo' => 'Link ativado com sucesso.',
              'status_oculto' => 'Link ocultado com sucesso.',
              'destaque_on' => 'Link definido como destaque principal.',
              'destaque_off' => 'Destaque removido do link.',
              'order_up' => 'Link movido para cima.',
              'order_down' => 'Link movido para baixo.',
              'order_drag' => 'Links reordenados com sucesso.',
              'order_unchanged' => 'Esse link ja esta no limite da ordenacao.',
              'checked_ok' => 'Link verificado com sucesso.',
              'checked_fail' => 'A verificacao marcou o link para revisao.',
          ];
          echo htmlspecialchars($modeLabels[$mode] ?? 'Link atualizado com sucesso.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
          ?>
        </div>
      </section>
    <?php endif; ?>
    <?php if ($deleted): ?>
      <section class="admin-panel border border-rose-500/30"><div class="text-sm font-bold text-rose-300">Link excluido com sucesso.</div></section>
    <?php endif; ?>

    <?php View::component('admin/links/summary-cards', ['summary' => $summary]); ?>

    <?php View::component('admin/links/filters', ['filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
    <?php View::component('admin/links/table', ['items' => $items, 'filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination, 'current_featured' => $currentFeatured]); ?>
    <?php View::component('admin/links/pagination', ['filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
  </div>
</div>

<?php if (!isset($_GET['_partial']) || (string) $_GET['_partial'] !== '1'): ?>
<script src="<?= url('/assets/js/admin-links.js') . '?v=' . @filemtime(dirname(__DIR__, 3) . '/public/assets/js/admin-links.js') ?>" defer></script>
<?php endif; ?>
