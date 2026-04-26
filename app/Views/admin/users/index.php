<?php

declare(strict_types=1);

use App\Support\View;

$summary = is_array($summary ?? null) ? $summary : [];
$pagination = $pagination ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$filters = $filters ?? ['busca' => '', 'papel' => '', 'status' => ''];
$sort = (string) ($sort ?? 'criado_em');
$dir = (string) ($dir ?? 'desc');
$flash = trim((string) ($flash ?? ($_GET['flash'] ?? '')));
$currentUserId = isset($current_user_id) ? (int) $current_user_id : 0;
$activeAdminsTotal = (int) ($active_admins_total ?? 0);
$targetEnvironment = (string) ($target_environment ?? current_environment());
$targetEnvironmentLabel = (string) ($target_environment_label ?? environment_label($targetEnvironment));
$isRemoteTarget = (bool) ($is_remote_target ?? false);

$flashMap = [
    'created' => ['level' => 'success', 'title' => 'Usuario criado com sucesso.', 'body' => 'O novo acesso ja esta disponivel no painel.'],
    'updated' => ['level' => 'success', 'title' => 'Usuario atualizado com sucesso.', 'body' => 'Os dados e o avatar foram sincronizados no sistema.'],
    'deleted' => ['level' => 'danger', 'title' => 'Usuario removido com sucesso.', 'body' => 'Os posts vinculados foram preservados com seguranca.'],
    'status_updated' => ['level' => 'info', 'title' => 'Status atualizado com sucesso.', 'body' => 'O acesso do usuario ao painel foi atualizado.'],
    'production_confirmation_required' => ['level' => 'warning', 'title' => 'Confirmacao obrigatoria para producao.', 'body' => 'Use a tela de edicao ou exclusao e digite PRODUCAO para confirmar a alteracao estrutural.'],
    'cannot_delete_self' => ['level' => 'warning', 'title' => 'Sua propria sessao nao pode ser excluida.', 'body' => 'Use outro administrador para remover esta conta.'],
    'cannot_disable_self' => ['level' => 'warning', 'title' => 'Sua propria sessao nao pode ser desativada.', 'body' => 'Mantenha sua conta ativa enquanto estiver logado.'],
    'cannot_remove_last_admin' => ['level' => 'warning', 'title' => 'Mantenha ao menos um administrador ativo.', 'body' => 'Crie ou ative outro admin antes de fazer essa alteracao.'],
];
$flashMeta = $flashMap[$flash] ?? null;
?>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Usuarios</h1>
      <div class="admin-page-subtitle">Gerencie acessos do painel, papeis, status e a identidade visual de cada membro da equipe.</div>
    </div>

    <div class="admin-page-actions">
      <div class="admin-chip">Total: <?= number_format((int) ($summary['total'] ?? 0), 0, ',', '.') ?></div>
      <div class="admin-chip<?= $isRemoteTarget ? ' border-cyan-500/30 text-cyan-200' : '' ?>">Ambiente alvo: <?= htmlspecialchars($targetEnvironmentLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <a href="<?= url('/admin/criar-usuario') ?>" class="admin-btn admin-btn-primary"><i class="fa-solid fa-user-plus"></i>Criar Usuario</a>
    </div>
  </div>

  <?php if ($isRemoteTarget): ?>
    <section class="admin-panel border border-cyan-500/20">
      <div class="text-sm font-bold text-cyan-200">Modo multiambiente ativo</div>
      <div class="mt-2 text-sm text-slate-300">Esta tela esta lendo e salvando os usuarios do ambiente <?= htmlspecialchars($targetEnvironmentLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>.</div>
      <div class="mt-2 text-sm text-slate-400">A sua sessao local nao e espelhada nesse alvo, entao protecoes de autoedicao/remocao so valem para o ambiente em execucao.</div>
    </section>
  <?php endif; ?>

  <?php if (is_array($flashMeta)): ?>
    <?php
      $toneClass = match ((string) ($flashMeta['level'] ?? 'info')) {
          'success' => 'border-emerald-500/30 text-emerald-300',
          'danger' => 'border-rose-500/30 text-rose-300',
          'warning' => 'border-amber-500/30 text-amber-300',
          default => 'border-cyan-500/30 text-cyan-300',
      };
    ?>
    <section class="admin-panel border <?= htmlspecialchars($toneClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <div class="text-sm font-bold"><?= htmlspecialchars((string) ($flashMeta['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <div class="mt-2 text-sm text-slate-200/90"><?= htmlspecialchars((string) ($flashMeta['body'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    </section>
  <?php endif; ?>

  <?php View::component('admin/users/summary-cards', ['summary' => $summary]); ?>
  <?php View::component('admin/users/filters', ['filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
  <?php View::component('admin/users/table', ['items' => $items ?? [], 'filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination, 'current_user_id' => $currentUserId, 'active_admins_total' => $activeAdminsTotal, 'requires_production_confirmation' => $requires_production_confirmation ?? false]); ?>
  <?php View::component('admin/users/pagination', ['filters' => $filters, 'sort' => $sort, 'dir' => $dir, 'pagination' => $pagination]); ?>
</div>
