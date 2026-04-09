<?php

declare(strict_types=1);

$filters = $filters ?? ['busca' => '', 'papel' => '', 'status' => ''];
$sort = (string) ($sort ?? 'criado_em');
$dir = (string) ($dir ?? 'desc');
$page = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 10);
$action = function_exists('url') ? url('/admin/usuarios') : '/admin/usuarios';
?>

<form method="GET" action="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-panel admin-filter-panel">
  <div class="admin-filter-head">
    <div>
      <h3 class="font-orbitron text-xl font-black text-white">Filtros</h3>
      <div class="text-xs text-slate-400 mt-1">Busque por nome, usuario ou email e refine a equipe por papel e status.</div>
    </div>
    <div class="text-xs text-slate-400">Pagina atual <span class="text-cyan-300 font-bold"><?= (int) $page ?></span> - <span class="text-slate-200 font-bold"><?= (int) $perPage ?></span> por pagina</div>
  </div>

  <div class="admin-filter-grid admin-filter-grid-users">
    <div class="admin-filter-field admin-filter-field-search">
      <label class="admin-filter-label" for="users-busca">Buscar</label>
      <input id="users-busca" name="busca" value="<?= htmlspecialchars((string) ($filters['busca'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" placeholder="Nome, usuario ou email..." class="nerd-input admin-filter-control">
    </div>

    <div class="admin-filter-field">
      <label class="admin-filter-label" for="users-papel">Papel</label>
      <select id="users-papel" name="papel" class="nerd-input admin-filter-control">
        <option value="" <?= ($filters['papel'] ?? '') === '' ? 'selected' : '' ?>>Todos</option>
        <option value="admin" <?= ($filters['papel'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador</option>
        <option value="editor" <?= ($filters['papel'] ?? '') === 'editor' ? 'selected' : '' ?>>Editor</option>
      </select>
    </div>

    <div class="admin-filter-field">
      <label class="admin-filter-label" for="users-status">Status</label>
      <select id="users-status" name="status" class="nerd-input admin-filter-control">
        <option value="" <?= ($filters['status'] ?? '') === '' ? 'selected' : '' ?>>Todos</option>
        <option value="ativo" <?= ($filters['status'] ?? '') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
        <option value="inativo" <?= ($filters['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
      </select>
    </div>
  </div>

  <div class="admin-filter-actions">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="page" value="1">
    <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
    <button class="admin-btn admin-btn-primary admin-filter-button" type="submit">Filtrar</button>
    <a class="admin-btn admin-btn-secondary admin-filter-button" href="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Limpar</a>
  </div>
</form>