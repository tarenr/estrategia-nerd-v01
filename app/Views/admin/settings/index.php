<?php
declare(strict_types=1);

$saved = isset($_GET['saved']) && (string) $_GET['saved'] === '1';
$targetEnvironment = (string) ($target_environment ?? current_environment());
$targetEnvironmentLabel = (string) ($target_environment_label ?? environment_label($targetEnvironment));
$isRemoteTarget = (bool) ($is_remote_target ?? false);
$allowMediaUploads = (bool) ($allow_media_uploads ?? true);
?>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Configuracoes</h1>
      <div class="admin-page-subtitle">Centralize dados globais do portal, branding e a base publica da futura pagina de links.</div>
    </div>
    <div class="admin-page-actions">
      <div class="admin-chip<?= $isRemoteTarget ? ' border-cyan-500/30 text-cyan-200' : '' ?>">Ambiente alvo: <?= htmlspecialchars($targetEnvironmentLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    </div>
  </div>

  <?php if ($saved): ?>
    <section class="admin-panel border border-emerald-500/30">
      <div class="text-sm font-bold text-emerald-200">Configuracoes atualizadas com sucesso.</div>
      <div class="mt-2 text-sm text-emerald-100/90">Os dados globais do portal foram salvos para <?= htmlspecialchars($targetEnvironmentLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> e ja podem alimentar o restante do sistema.</div>
    </section>
  <?php endif; ?>

  <?php if ($isRemoteTarget): ?>
    <section class="admin-panel border border-cyan-500/20">
      <div class="text-sm font-bold text-cyan-200">Modo multiambiente ativo</div>
      <div class="mt-2 text-sm text-slate-300">Esta tela esta lendo e salvando os textos e URLs globais do ambiente <?= htmlspecialchars($targetEnvironmentLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>.</div>
      <?php if (!$allowMediaUploads): ?>
        <div class="mt-2 text-sm text-slate-400">Upload direto e selecao da biblioteca local ficam bloqueados enquanto o alvo for remoto, para evitar gravar caminhos inconsistentes.</div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php \App\Support\View::component('admin/settings/form', [
      'form' => $form ?? [],
      'errors' => $errors ?? [],
      'media_items' => $media_items ?? [],
      'allow_media_uploads' => $allowMediaUploads,
      'requires_production_confirmation' => $requires_production_confirmation ?? false,
      'action' => url('/admin/configuracoes'),
      'submitLabel' => 'Salvar configuracoes',
  ]); ?>
</div>
