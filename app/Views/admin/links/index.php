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

    <?php if ($currentFeatured !== null): ?>
      <?php
      $featuredType = (string) ($currentFeatured['tipo'] ?? '');
      $featuredTypeLabel = match ($featuredType) {
          'produto' => 'Produto',
          'cupom' => 'Cupom',
          'conteudo' => 'Conteúdo',
          'rede_social' => 'Rede Social',
          'servico' => 'Serviço',
          default => 'Link',
      };
      $featuredTitle = trim((string) ($currentFeatured['titulo'] ?? 'Sem título'));
      $featuredDescription = trim((string) ($currentFeatured['descricao'] ?? ''));
      $featuredUrl = trim((string) ($currentFeatured['url'] ?? ''));
      $featuredDiscount = trim((string) ($currentFeatured['desconto_percentual'] ?? ''));
      $featuredDiscountContext = trim((string) ($currentFeatured['desconto_contexto'] ?? ''));
      $featuredCouponCode = trim((string) ($currentFeatured['codigo_cupom'] ?? ''));
      ?>
      <section class="admin-panel border border-cyan-500/25 bg-slate-950/55">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="space-y-2">
            <div class="inline-flex items-center gap-2 rounded-full border border-cyan-500/25 bg-cyan-500/10 px-3 py-1 text-xs text-cyan-100">
              <span class="font-bold">Destaque atual</span>
              <span>#<?= (int) ($currentFeatured['id'] ?? 0) ?></span>
              <span>• <?= htmlspecialchars($featuredTypeLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            </div>
            <div class="text-sm font-bold text-white"><?= htmlspecialchars($featuredTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php if ($featuredDescription !== ''): ?>
              <div class="text-xs leading-5 text-slate-300"><?= htmlspecialchars($featuredDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($featuredType === 'cupom' && ($featuredDiscount !== '' || $featuredDiscountContext !== '')): ?>
              <div class="flex flex-wrap items-center gap-2 text-xs">
                <?php if ($featuredDiscount !== ''): ?>
                  <span class="inline-flex items-center rounded-full border border-blue-500/25 bg-blue-500/10 px-2.5 py-1 text-blue-100"><?= htmlspecialchars($featuredDiscount, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if ($featuredDiscountContext !== ''): ?>
                  <span class="inline-flex items-center rounded-full border border-slate-600/50 bg-slate-800/50 px-2.5 py-1 text-slate-300"><?= htmlspecialchars($featuredDiscountContext, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <?php if ($featuredType === 'cupom' && $featuredCouponCode !== ''): ?>
              <button type="button" class="admin-btn admin-btn-secondary !px-3 !py-2 text-xs" data-current-featured-copy="<?= htmlspecialchars($featuredCouponCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-current-featured-copy-text>
                Copiar código: <?= htmlspecialchars($featuredCouponCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
              </button>
            <?php endif; ?>
            <?php if ($featuredType === 'cupom' && $featuredCouponCode === ''): ?>
              <span class="inline-flex items-center rounded-full border border-slate-600/50 bg-slate-800/40 px-3 py-2 text-xs text-slate-300">Oferta sem código</span>
            <?php endif; ?>
            <?php if ($featuredUrl !== ''): ?>
              <a href="<?= htmlspecialchars($featuredUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="admin-btn admin-btn-secondary !px-3 !py-2 text-xs">Ir para site</a>
            <?php endif; ?>
          </div>
        </div>
      </section>
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
