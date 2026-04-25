<?php
declare(strict_types=1);

use App\Support\View;

$summary = $summary ?? ['total_posts' => 0,'publicados' => 0,'rascunhos' => 0,'agendados' => 0,'destaques' => 0,'total_views' => 0,'total_curtidas' => 0,'total_comentarios' => 0];
$filters = $filters ?? ['status' => '', 'categoria' => 0, 'destaque' => '', 'busca' => ''];
$pagination = $pagination ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$categorias = $categorias ?? [];
$sort = (string)($sort ?? 'data');
$dir = (string)($dir ?? 'desc');
$created = isset($_GET['created']) && (string)$_GET['created'] === '1';
$updated = isset($_GET['updated']) && (string)$_GET['updated'] === '1';
$deleted = isset($_GET['deleted']) && (string)$_GET['deleted'] === '1';
?>

<div class="max-w-7xl mx-auto px-4 py-6" data-admin-posts-root>
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Central de Posts</h1>
      <div class="admin-page-subtitle">Gestao editorial, filtros e desempenho dos posts</div>
    </div>

    <div class="admin-page-actions">
      <div class="admin-chip">
        Total: <?= number_format((int)($summary['total_posts'] ?? 0), 0, ',', '.') ?>
      </div>
      <a href="<?= function_exists('url') ? url('/admin/criar-post') : '/admin/criar-post' ?>" class="admin-btn admin-btn-primary">
        Criar Post
      </a>
    </div>
  </div>

  <div class="space-y-6">
    <?php if ($created): ?>
      <section class="admin-panel border border-emerald-500/30">
        <div class="text-sm font-bold text-emerald-300">Post criado com sucesso.</div>
        <div class="text-xs text-slate-400 mt-1">A publicacao ja esta disponivel na central para revisao, ordenacao e proximos ajustes.</div>
      </section>
    <?php endif; ?>

    <?php if ($updated): ?>
      <section class="admin-panel border border-cyan-500/30">
        <div class="text-sm font-bold text-cyan-300">Post atualizado com sucesso.</div>
        <div class="text-xs text-slate-400 mt-1">As alteracoes foram salvas e a central ja reflete a versao mais recente do conteudo.</div>
      </section>
    <?php endif; ?>

    <?php if ($deleted): ?>
      <section class="admin-panel border border-rose-500/30">
        <div class="text-sm font-bold text-rose-300">Post excluido com sucesso.</div>
        <div class="text-xs text-slate-400 mt-1">O conteudo removido nao aparece mais na central e a pasta local de midia foi enviada para `uploads/trash/posts` quando aplicavel.</div>
      </section>
    <?php endif; ?>

    <?php View::component('admin/posts/summary-cards', ['summary' => $summary]); ?>
    <?php View::component('admin/posts/filters', ['filters' => $filters,'categorias' => $categorias,'sort' => $sort,'dir' => $dir,'pagination' => $pagination]); ?>
    <?php View::component('admin/posts/table', ['items' => $pagination['items'] ?? [],'sort' => $sort,'dir' => $dir,'filters' => $filters,'pagination' => $pagination]); ?>
    <?php View::component('admin/posts/pagination', ['filters' => $filters,'sort' => $sort,'dir' => $dir,'pagination' => $pagination]); ?>
  </div>
</div>

<script src="<?= url('/assets/js/admin-posts.js') ?>" defer></script>
