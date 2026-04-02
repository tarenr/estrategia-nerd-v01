<?php
declare(strict_types=1);

use App\Support\View;

$summary = $summary ?? ['total' => 0, 'pendentes' => 0, 'aprovados' => 0, 'reprovados' => 0, 'spam' => 0, 'respondidos' => 0];
$filters = $filters ?? ['busca' => '', 'status' => '', 'respondido' => '', 'post' => 0];
$pagination = $pagination ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$posts = $posts ?? [];
$sort = (string) ($sort ?? 'data');
$dir = (string) ($dir ?? 'desc');
$moderated = isset($_GET['moderated']) && (string) $_GET['moderated'] === '1';
$mode = (string) ($_GET['mode'] ?? '');
$deleted = isset($_GET['deleted']) && (string) $_GET['deleted'] === '1';

$moderationMessage = match ($mode) {
    'approved' => 'Comentario aprovado com sucesso.',
    'rejected' => 'Comentario reprovado com sucesso.',
    'spam' => 'Comentario marcado como spam.',
    'pending' => 'Comentario retornou para pendente.',
    'responded' => 'Comentario marcado como respondido.',
    'unresponded' => 'Marcacao de respondido removida.',
    default => 'Comentario atualizado com sucesso.',
};
?>

<div class="max-w-7xl mx-auto px-4 py-6" data-admin-comments-root>
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Comentarios</h1>
      <div class="admin-page-subtitle">Modere interacoes, acompanhe pendencias e conecte cada comentario ao post certo.</div>
    </div>

    <div class="admin-page-actions">
      <div class="admin-chip">Total: <?= number_format((int) ($summary['total'] ?? 0), 0, ',', '.') ?></div>
    </div>
  </div>

  <div class="space-y-6">
    <?php if ($moderated): ?>
      <section class="admin-panel border border-cyan-500/30"><div class="text-sm font-bold text-cyan-300"><?= htmlspecialchars($moderationMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div></section>
    <?php endif; ?>

    <?php if ($deleted): ?>
      <section class="admin-panel border border-rose-500/30"><div class="text-sm font-bold text-rose-300">Comentario excluido com sucesso.</div></section>
    <?php endif; ?>

    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
      <?php $cards = [['label' => 'Total', 'value' => (int) $summary['total']], ['label' => 'Pendentes', 'value' => (int) $summary['pendentes']], ['label' => 'Aprovados', 'value' => (int) $summary['aprovados']], ['label' => 'Spam', 'value' => (int) $summary['spam']], ['label' => 'Respondidos', 'value' => (int) $summary['respondidos']]]; ?>
      <?php foreach ($cards as $card): ?>
        <article class="stat-card">
          <div class="text-sm text-slate-400"><?= htmlspecialchars($card['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="mt-3 text-4xl font-orbitron font-black text-white"><?= number_format((int) $card['value'], 0, ',', '.') ?></div>
        </article>
      <?php endforeach; ?>
    </section>

    <?php View::component('admin/comments/filters', ['filters' => $filters, 'posts' => $posts, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
    <?php View::component('admin/comments/table', ['items' => $pagination['items'] ?? [], 'filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
  </div>
</div>

<script src="<?= url('/assets/js/admin-comments.js') . '?v=' . @filemtime(base_path('public/assets/js/admin-comments.js')) ?>" defer></script>