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

<form method="GET" action="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-admin-categories-filters class="bg-slate-900/50 border border-cyan-500/20 rounded-2xl p-6">
  <div class="flex items-center justify-between mb-4 gap-4 flex-wrap">
    <div>
      <h3 class="font-orbitron text-xl font-black text-white">Filtros</h3>
      <div class="text-xs text-slate-400 mt-1">Busque categorias e refine a listagem sem recarregar a pagina inteira</div>
    </div>
    <div class="text-xs text-slate-400">Pagina atual <span class="text-cyan-300 font-bold"><?= $page ?></span> - <span class="text-slate-200 font-bold"><?= $perPage ?></span> por pagina</div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
    <div class="md:col-span-8">
      <label class="block text-xs text-slate-400 mb-1">Buscar</label>
      <input name="busca" value="<?= htmlspecialchars($busca, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" placeholder="Nome ou slug da categoria..." class="nerd-input w-full px-4 py-3 rounded-xl" />
    </div>
    <div class="md:col-span-4">
      <label class="block text-xs text-slate-400 mb-1">Status</label>
      <select name="status" class="nerd-input w-full px-4 py-3 rounded-xl">
        <option value="" <?= $status === '' ? 'selected' : '' ?>>Todos</option>
        <option value="ativas" <?= $status === 'ativas' ? 'selected' : '' ?>>Somente ativas</option>
        <option value="inativas" <?= $status === 'inativas' ? 'selected' : '' ?>>Somente inativas</option>
        <option value="com_posts" <?= $status === 'com_posts' ? 'selected' : '' ?>>Com posts vinculados</option>
        <option value="sem_posts" <?= $status === 'sem_posts' ? 'selected' : '' ?>>Sem posts vinculados</option>
      </select>
    </div>
  </div>

  <div class="mt-4 flex flex-wrap items-center gap-2">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="page" value="1">
    <input type="hidden" name="per_page" value="<?= $perPage ?>">
    <button class="admin-btn admin-btn-primary" type="submit">Filtrar</button>
    <a data-admin-categories-link class="admin-btn admin-btn-secondary" href="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Limpar</a>
  </div>
</form>
