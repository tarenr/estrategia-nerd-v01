<?php
declare(strict_types=1);

$saved = isset($_GET['saved']) && (string) $_GET['saved'] === '1';
?>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Configuracoes</h1>
      <div class="admin-page-subtitle">Centralize dados globais do portal, branding e a base publica da futura pagina de links.</div>
    </div>
  </div>

  <?php if ($saved): ?>
    <section class="admin-panel border border-emerald-500/30">
      <div class="text-sm font-bold text-emerald-200">Configuracoes atualizadas com sucesso.</div>
      <div class="mt-2 text-sm text-emerald-100/90">Os dados globais do portal foram salvos e ja podem alimentar o restante do sistema.</div>
    </section>
  <?php endif; ?>

  <?php \App\Support\View::component('admin/settings/form', [
      'form' => $form ?? [],
      'errors' => $errors ?? [],
      'media_items' => $media_items ?? [],
      'action' => url('/admin/configuracoes'),
      'submitLabel' => 'Salvar configuracoes',
  ]); ?>
</div>
