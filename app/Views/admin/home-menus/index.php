<?php

declare(strict_types=1);

use App\Support\View;

$saved = isset($_GET['saved']) && (string) $_GET['saved'] === '1';
$summary = is_array($summary ?? null) ? $summary : [];
?>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Home e Menus</h1>
      <div class="admin-page-subtitle">Controle o que aparece na home, no menu publico e quais modulos ficam realmente ativos no portal.</div>
    </div>

    <div class="admin-page-actions">
      <div class="admin-chip">Modulos: <?= number_format((int) ($summary['total'] ?? 0), 0, ',', '.') ?></div>
    </div>
  </div>

  <?php if ($saved): ?>
    <section class="admin-panel border border-emerald-500/30">
      <div class="text-sm font-bold text-emerald-300">Estrutura publica atualizada com sucesso.</div>
      <div class="mt-2 text-sm text-emerald-100/90">A home, o menu e os modulos publicos agora seguem a configuracao salva nesta tela.</div>
    </section>
  <?php endif; ?>

  <?php View::component('admin/home-menus/summary-cards', ['summary' => $summary]); ?>
  <?php View::component('admin/home-menus/table', [
      'sections' => $sections ?? [],
      'errors' => $errors ?? [],
      'action' => url('/admin/home-e-menus'),
  ]); ?>
</div>