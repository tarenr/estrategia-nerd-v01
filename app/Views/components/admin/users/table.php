<?php

declare(strict_types=1);

use App\Support\Csrf;

$items = $items ?? [];
$filters = $filters ?? ['busca' => '', 'papel' => '', 'status' => ''];
$sort = (string) ($sort ?? 'criado_em');
$dir = (string) ($dir ?? 'desc');
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$currentUserId = (int) ($current_user_id ?? 0);
$activeAdminsTotal = (int) ($active_admins_total ?? 0);
$total = max(0, (int) ($pagination['total'] ?? count($items)));
$baseUrl = function_exists('url') ? url('/admin/usuarios') : '/admin/usuarios';

$buildUrl = static function (array $overrides = []) use ($baseUrl, $filters, $sort, $dir, $pagination): string {
    $query = array_merge([
        'busca' => (string) ($filters['busca'] ?? ''),
        'papel' => (string) ($filters['papel'] ?? ''),
        'status' => (string) ($filters['status'] ?? ''),
        'sort' => $sort,
        'dir' => $dir,
        'page' => (int) ($pagination['page'] ?? 1),
        'per_page' => (int) ($pagination['per_page'] ?? 10),
    ], $overrides);
    $query = array_filter($query, static fn ($value): bool => !($value === '' || $value === null || $value === 0));
    $qs = http_build_query($query);
    return $qs !== '' ? $baseUrl . '?' . $qs : $baseUrl;
};

$sortUrl = static function (string $column) use ($buildUrl, $sort, $dir): string {
    $nextDir = $sort === $column && $dir === 'asc' ? 'desc' : 'asc';
    return $buildUrl(['sort' => $column, 'dir' => $nextDir, 'page' => 1]);
};

$sortIcon = static function (string $column) use ($sort, $dir): string {
    if ($sort !== $column) {
        return '&#8596;';
    }

    return $dir === 'asc' ? '&#8593;' : '&#8595;';
};

$formatDate = static function (?string $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '-';
    }

    $ts = strtotime($raw);
    return $ts !== false ? date('d/m/Y H:i', $ts) : '-';
};

$avatarUrl = static function (string $path): string {
    $normalized = trim($path);
    if ($normalized === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $normalized)) {
        return $normalized;
    }

    return url('/' . ltrim($normalized, '/'));
};
?>

<section class="admin-panel users-table-panel">
  <div class="posts-table-head">
    <div>
      <h3 class="font-orbitron text-xl font-black text-white">Lista de usuarios</h3>
      <div class="text-xs text-slate-400 mt-1"><?= number_format($total, 0, ',', '.') ?> usuario(s) encontrado(s)</div>
    </div>
    <span class="posts-table-order"><?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> / <?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
  </div>

  <?php if ($items === []): ?>
    <div class="text-center py-12 border-2 border-dashed border-gray-700 rounded-xl">
      <div class="text-3xl mb-4 font-orbitron font-black text-cyan-300">SEM</div>
      <h4 class="text-xl font-bold text-white mb-2">Nenhum usuario encontrado</h4>
      <div class="text-slate-400 text-sm">Ajuste os filtros ou crie um novo acesso para o admin.</div>
    </div>
  <?php else: ?>
    <div class="posts-table-wrap users-table-wrap">
      <table class="users-table">
        <colgroup>
          <col class="users-table-col-user">
          <col class="users-table-col-role">
          <col class="users-table-col-status">
          <col class="users-table-col-date">
          <col class="users-table-col-access">
          <col class="users-table-col-actions">
        </colgroup>
        <thead class="posts-table-thead">
          <tr>
            <th class="posts-table-th posts-table-th-left">
              <a href="<?= htmlspecialchars($sortUrl('nome'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="posts-table-sort posts-table-sort-left">
                <span>Usuario</span>
                <span><?= $sortIcon('nome') ?></span>
              </a>
            </th>
            <th class="posts-table-th posts-table-th-center">
              <a href="<?= htmlspecialchars($sortUrl('papel'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="posts-table-sort posts-table-sort-center">
                <span>Papel</span>
                <span><?= $sortIcon('papel') ?></span>
              </a>
            </th>
            <th class="posts-table-th posts-table-th-center">
              <a href="<?= htmlspecialchars($sortUrl('status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="posts-table-sort posts-table-sort-center">
                <span>Status</span>
                <span><?= $sortIcon('status') ?></span>
              </a>
            </th>
            <th class="posts-table-th posts-table-th-center">
              <a href="<?= htmlspecialchars($sortUrl('criado_em'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="posts-table-sort posts-table-sort-center">
                <span>Cadastro</span>
                <span><?= $sortIcon('criado_em') ?></span>
              </a>
            </th>
            <th class="posts-table-th posts-table-th-center">
              <a href="<?= htmlspecialchars($sortUrl('ultimo_acesso'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="posts-table-sort posts-table-sort-center">
                <span>Ultimo acesso</span>
                <span><?= $sortIcon('ultimo_acesso') ?></span>
              </a>
            </th>
            <th class="posts-table-th posts-table-th-center">Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <?php
              $id = (int) ($item['id'] ?? 0);
              $nome = trim((string) ($item['nome'] ?? ''));
              $usuario = trim((string) ($item['usuario'] ?? ''));
              $email = trim((string) ($item['email'] ?? ''));
              $papel = (string) ($item['papel'] ?? 'editor');
              $status = (string) ($item['status'] ?? 'inativo');
              $avatarTipo = (string) ($item['avatar_tipo'] ?? 'icone');
              $avatarIcone = trim((string) ($item['avatar_icone'] ?? 'fa-solid fa-user'));
              $avatarCor = trim((string) ($item['avatar_cor'] ?? '#38bdf8'));
              $avatarImagem = trim((string) ($item['avatar_imagem'] ?? ''));
              $avatarFocalX = max(0.0, min(100.0, (float) ($item['avatar_focal_x'] ?? 50.0)));
              $avatarFocalY = max(0.0, min(100.0, (float) ($item['avatar_focal_y'] ?? 50.0)));
              $avatarFocusStyle = 'object-position: ' . number_format($avatarFocalX, 2, '.', '') . '% ' . number_format($avatarFocalY, 2, '.', '') . '%;';
              $canToggle = (bool) ($item['can_toggle'] ?? true);
              $canDelete = (bool) ($item['can_delete'] ?? true);
              $isCurrentUser = (bool) ($item['is_current_user'] ?? ($currentUserId > 0 && $id === $currentUserId));
              $isLastActiveAdmin = (bool) ($item['is_last_active_admin'] ?? ($papel === 'admin' && $status === 'ativo' && $activeAdminsTotal <= 1));
              $toggleTarget = $status === 'ativo' ? 'inativo' : 'ativo';
              $statusClass = $status === 'ativo' ? 'users-table-status-toggle-active' : 'users-table-status-toggle-inactive';
              $roleLabel = $papel === 'admin' ? 'Administrador' : 'Editor';
              $roleClass = $papel === 'admin' ? 'users-table-role-admin' : 'users-table-role-editor';
              $statusNote = $isCurrentUser ? 'Sessao atual' : ($isLastActiveAdmin ? 'Ultimo admin ativo' : '');
              $avatarPreview = $avatarTipo === 'foto' && $avatarImagem !== '' ? $avatarUrl($avatarImagem) : '';
              $deleteUrl = url('/admin/excluir-usuario?id=' . $id);
            ?>
            <tr class="users-table-row">
              <td class="users-table-td users-table-user-cell">
                <div class="users-table-user-wrap">
                  <div class="users-avatar users-avatar-table"<?php if ($avatarPreview === ''): ?> style="background: linear-gradient(135deg, <?= htmlspecialchars($avatarCor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>, rgba(15, 23, 42, 0.92));"<?php endif; ?>>
                    <?php if ($avatarPreview !== ''): ?>
                      <img src="<?= htmlspecialchars($avatarPreview, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($nome !== '' ? $nome : $usuario, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" style="<?= htmlspecialchars($avatarFocusStyle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <?php else: ?>
                      <i class="<?= htmlspecialchars($avatarIcone, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></i>
                    <?php endif; ?>
                  </div>
                  <div class="users-table-user-copy">
                    <a href="<?= url('/admin/editar-usuario?id=' . $id) ?>" class="users-table-user-name">#<?= $id ?> - <?= htmlspecialchars($nome !== '' ? $nome : $usuario, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
                    <div class="users-table-user-login">@<?= htmlspecialchars($usuario, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    <div class="users-table-user-email"><?= htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  </div>
                </div>
              </td>
              <td class="users-table-td users-table-td-center">
                <span class="users-table-role <?= htmlspecialchars($roleClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($roleLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              </td>
              <td class="users-table-td users-table-td-center">
                <form method="POST" action="<?= url('/admin/usuarios/status') ?>" class="users-table-status-form">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="id" value="<?= $id ?>">
                  <input type="hidden" name="status" value="<?= htmlspecialchars($toggleTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                  <button type="submit" class="users-table-status-toggle <?= htmlspecialchars($statusClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?= !$canToggle ? ' is-disabled' : '' ?>" <?= !$canToggle ? 'disabled' : '' ?> title="Alterar status do usuario" aria-label="Alterar status do usuario">
                    <?= htmlspecialchars(strtoupper($status), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                  </button>
                </form>
                <?php if ($statusNote !== ''): ?>
                  <div class="users-table-status-note"><?= htmlspecialchars($statusNote, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <?php endif; ?>
              </td>
              <td class="users-table-td users-table-td-center">
                <div class="users-table-date"><?= htmlspecialchars($formatDate((string) ($item['criado_em'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </td>
              <td class="users-table-td users-table-td-center">
                <div class="users-table-date<?= trim((string) ($item['ultimo_acesso'] ?? '')) === '' ? ' is-empty' : '' ?>"><?= htmlspecialchars(trim((string) ($item['ultimo_acesso'] ?? '')) !== '' ? $formatDate((string) ($item['ultimo_acesso'] ?? '')) : 'Nunca', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </td>
              <td class="users-table-td users-table-td-center">
                <div class="links-table-actions users-table-actions">
                  <?php if ($canDelete): ?>
                    <a href="<?= htmlspecialchars($deleteUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="links-table-action posts-table-action posts-table-action-delete" title="Excluir usuario" aria-label="Excluir usuario">
                      <i class="fa-solid fa-trash"></i>
                    </a>
                  <?php else: ?>
                    <span class="links-table-action posts-table-action posts-table-action-delete users-table-action-disabled" title="Acao indisponivel">
                      <i class="fa-solid fa-trash"></i>
                    </span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>