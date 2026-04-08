<?php
declare(strict_types=1);

$filters = $filters ?? ['busca' => '', 'status' => ''];
$sort = (string) ($sort ?? 'ordem');
$dir = (string) ($dir ?? 'asc');
$page = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 10);
$busca = (string) ($filters['busca'] ?? '');
$status = (string) ($filters['status'] ?? '');
$action = function_exists('url') ? url('/admin/categorias') : '/admin/categorias';
?>

<form method="GET" action="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-admin-categories-filters class="admin-panel admin-filter-panel">
  <div class="admin-filter-head">
    <div>
      <h3 class="font-orbitron text-xl font-black text-white">Filtros</h3>
      <div class="text-xs text-slate-400 mt-1">Refine a listagem mantendo a navegacao fluida</div>
    </div>
    <div class="text-xs text-slate-400">Pagina atual <span class="text-cyan-300 font-bold"><?= $page ?></span> - <span class="text-slate-200 font-bold"><?= $perPage ?></span> por pagina</div>
  </div>

  <div class="admin-filter-grid admin-filter-grid-categories">
    <div class="admin-filter-field admin-filter-field-search">
      <label class="admin-filter-label" for="categories-busca">Buscar</label>
      <input id="categories-busca" name="busca" value="<?= htmlspecialchars($busca, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" placeholder="Nome ou slug da categoria..." class="nerd-input admin-filter-control" />
    </div>

    <div class="admin-filter-field">
      <label class="admin-filter-label" for="categories-status">Status</label>
      <select id="categories-status" name="status" class="nerd-input admin-filter-control">
        <option value="" <?= $status === '' ? 'selected' : '' ?>>Todos</option>
        <option value="ativas" <?= $status === 'ativas' ? 'selected' : '' ?>>Somente ativas</option>
        <option value="inativas" <?= $status === 'inativas' ? 'selected' : '' ?>>Somente inativas</option>
        <option value="com_posts" <?= $status === 'com_posts' ? 'selected' : '' ?>>Com posts vinculados</option>
        <option value="sem_posts" <?= $status === 'sem_posts' ? 'selected' : '' ?>>Sem posts vinculados</option>
      </select>
    </div>
  </div>

  <div class="admin-filter-actions">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="page" value="1">
    <input type="hidden" name="per_page" value="<?= $perPage ?>">
    <button class="admin-btn admin-btn-primary admin-filter-button" type="submit">Filtrar</button>
    <a data-admin-categories-link class="admin-btn admin-btn-secondary admin-filter-button" href="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Limpar</a>
  </div>
</form>