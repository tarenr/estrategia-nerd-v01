<?php
declare(strict_types=1);

use App\Support\View;

$items = $items ?? [];
$summary = $summary ?? ['total' => 0, 'ativos' => 0, 'promocoes' => 0, 'produtos' => 0, 'cupons' => 0, 'sociais' => 0];
$pagination = $pagination ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$filters = $filters ?? ['busca' => '', 'tipo' => '', 'promocao' => '', 'status' => '', 'destaque' => '', 'monitoramento' => ''];
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
              'destaque_on' => 'Link marcado como destaque.',
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

    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
      <?php
      $cards = [
          ['label' => 'Total', 'value' => (int) ($summary['total'] ?? 0)],
          ['label' => 'Ativos', 'value' => (int) ($summary['ativos'] ?? 0)],
          ['label' => 'Promocoes', 'value' => (int) ($summary['promocoes'] ?? 0)],
          ['label' => 'Produtos', 'value' => (int) ($summary['produtos'] ?? 0)],
          ['label' => 'Cupons', 'value' => (int) ($summary['cupons'] ?? 0)],
      ];
      ?>
      <?php foreach ($cards as $card): ?>
        <article class="stat-card">
          <div class="text-sm text-slate-400"><?= htmlspecialchars($card['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="mt-3 text-4xl font-orbitron font-black text-white"><?= number_format((int) $card['value'], 0, ',', '.') ?></div>
        </article>
      <?php endforeach; ?>
    </section>

    <?php View::component('admin/links/filters', ['filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
    <?php View::component('admin/links/table', ['items' => $items, 'filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
  </div>
</div>

<?php if (!isset($_GET['_partial']) || (string) $_GET['_partial'] !== '1'): ?>
<script src="<?= url('/assets/js/admin-links.js') . '?v=' . @filemtime(dirname(__DIR__, 3) . '/public/assets/js/admin-links.js') ?>" defer></script>
<?php endif; ?>