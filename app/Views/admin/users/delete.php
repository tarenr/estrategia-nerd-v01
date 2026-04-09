<?php

declare(strict_types=1);

use App\Support\Csrf;

$usuario = is_array($usuario ?? null) ? $usuario : [];
$title = (string) ($title ?? 'Excluir Usuario');
$postsCount = (int) ($posts_count ?? 0);
$isCurrentUser = (bool) ($is_current_user ?? false);
$isLastActiveAdmin = (bool) ($is_last_active_admin ?? false);
$nome = trim((string) ($usuario['nome'] ?? $usuario['usuario'] ?? 'Usuario'));
$papel = (string) ($usuario['papel'] ?? 'editor');
$status = (string) ($usuario['status'] ?? 'inativo');
$blocked = $isCurrentUser || $isLastActiveAdmin;
?>

<div class="max-w-3xl mx-auto px-4 py-6 space-y-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title"><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
      <div class="admin-page-subtitle">Revise o impacto dessa remocao antes de confirmar a exclusao.</div>
    </div>

    <div class="admin-page-actions">
      <a href="<?= url('/admin/usuarios') ?>" class="admin-btn admin-btn-secondary"><i class="fa-solid fa-arrow-left"></i>Voltar para usuarios</a>
    </div>
  </div>

  <section class="admin-panel space-y-4">
    <div>
      <h2 class="admin-panel-title"><i class="fa-solid fa-user-slash text-rose-300"></i><span>Excluir usuario</span></h2>
      <div class="admin-panel-subtitle">Essa acao remove o acesso ao painel e preserva a integridade dos posts ja publicados.</div>
    </div>

    <div class="users-delete-card">
      <div>
        <div class="users-delete-name"><?= htmlspecialchars($nome, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <div class="users-delete-meta">@<?= htmlspecialchars((string) ($usuario['usuario'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> - <?= htmlspecialchars((string) ($usuario['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </div>
      <div class="users-delete-badges">
        <span class="users-table-role <?= $papel === 'admin' ? 'users-table-role-admin' : 'users-table-role-editor' ?>"><?= htmlspecialchars($papel === 'admin' ? 'Administrador' : 'Editor', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
        <span class="users-table-status-chip <?= $status === 'ativo' ? 'users-table-status-chip-active' : 'users-table-status-chip-inactive' ?>"><?= htmlspecialchars(strtoupper($status), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
      </div>
    </div>

    <div class="space-y-3 text-sm text-slate-300">
      <div>Posts vinculados ao autor: <strong class="text-white"><?= number_format($postsCount, 0, ',', '.') ?></strong></div>
      <?php if ($postsCount > 0): ?>
        <div class="text-slate-400">Se confirmar a exclusao, os posts desse autor serao transferidos automaticamente para outro administrador ativo.</div>
      <?php endif; ?>
      <?php if ($isCurrentUser): ?>
        <div class="text-amber-300">Sua propria sessao nao pode ser excluida enquanto voce estiver logado.</div>
      <?php elseif ($isLastActiveAdmin): ?>
        <div class="text-amber-300">Este e o ultimo administrador ativo. Crie ou ative outro admin antes de remover esta conta.</div>
      <?php else: ?>
        <div class="text-rose-300">Essa acao nao pode ser desfeita.</div>
      <?php endif; ?>
    </div>

    <div class="admin-page-actions pt-2">
      <a href="<?= url('/admin/usuarios') ?>" class="admin-btn admin-btn-secondary">Cancelar</a>
      <?php if (!$blocked): ?>
        <form method="POST" action="<?= url('/admin/excluir-usuario?id=' . (int) ($usuario['id'] ?? 0)) ?>">
          <?= Csrf::field() ?>
          <input type="hidden" name="id" value="<?= (int) ($usuario['id'] ?? 0) ?>">
          <button type="submit" class="admin-btn admin-btn-danger"><i class="fa-solid fa-trash"></i>Excluir usuario</button>
        </form>
      <?php endif; ?>
    </div>
  </section>
</div>