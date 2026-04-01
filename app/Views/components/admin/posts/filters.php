<?php
declare(strict_types=1);

$filters = $filters ?? ['status' => '', 'categoria' => 0, 'destaque' => '', 'busca' => ''];
$categorias = $categorias ?? [];
$sort = (string)($sort ?? 'data');
$dir = (string)($dir ?? 'desc');
$page = (int)($pagination['page'] ?? 1);
$perPage = (int)($pagination['per_page'] ?? 10);
$status = (string)($filters['status'] ?? '');
$categoria = (int)($filters['categoria'] ?? 0);
$destaque = (string)($filters['destaque'] ?? '');
$busca = (string)($filters['busca'] ?? '');
$action = function_exists('url') ? url('/admin/posts') : '/admin/posts';
?>

<form method="GET" action="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-admin-posts-filters class="bg-slate-900/50 border border-cyan-500/20 rounded-2xl p-6">
  <div class="flex items-center justify-between mb-4 gap-4 flex-wrap">
    <div>
      <h3 class="font-orbitron text-xl font-black text-white">Filtros</h3>
      <div class="text-xs text-slate-400 mt-1">Refine a listagem mantendo a navegacao fluida</div>
    </div>
    <div class="text-xs text-slate-400">Pagina atual <span class="text-cyan-300 font-bold"><?= (int)$page ?></span> - <span class="text-slate-200 font-bold"><?= (int)$perPage ?></span> por pagina</div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
    <div class="md:col-span-5"><label class="block text-xs text-slate-400 mb-1">Buscar</label><input name="busca" value="<?= htmlspecialchars($busca, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" placeholder="Titulo, resumo ou slug..." class="nerd-input w-full px-4 py-3 rounded-xl" /></div>
    <div class="md:col-span-2"><label class="block text-xs text-slate-400 mb-1">Status</label><select name="status" class="nerd-input w-full px-4 py-3 rounded-xl"><option value="" <?= $status === '' ? 'selected' : '' ?>>Todos</option><option value="publicado" <?= $status === 'publicado' ? 'selected' : '' ?>>Publicado</option><option value="rascunho" <?= $status === 'rascunho' ? 'selected' : '' ?>>Rascunho</option><option value="agendado" <?= $status === 'agendado' ? 'selected' : '' ?>>Agendado</option></select></div>
    <div class="md:col-span-3"><label class="block text-xs text-slate-400 mb-1">Categoria</label><select name="categoria" class="nerd-input w-full px-4 py-3 rounded-xl"><option value="0" <?= $categoria === 0 ? 'selected' : '' ?>>Todas</option><?php foreach ($categorias as $c): ?><?php $cid = (int)($c['id'] ?? 0); $cnome = (string)($c['nome'] ?? ''); ?><option value="<?= $cid ?>" <?= $categoria === $cid ? 'selected' : '' ?>><?= htmlspecialchars($cnome, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option><?php endforeach; ?></select></div>
    <div class="md:col-span-2"><label class="block text-xs text-slate-400 mb-1">Destaque</label><select name="destaque" class="nerd-input w-full px-4 py-3 rounded-xl"><option value="" <?= $destaque === '' ? 'selected' : '' ?>>Todos</option><option value="1" <?= $destaque === '1' ? 'selected' : '' ?>>Somente destaques</option><option value="0" <?= $destaque === '0' ? 'selected' : '' ?>>Sem destaque</option></select></div>
  </div>

  <div class="mt-4 flex flex-wrap items-center gap-2">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="page" value="1">
    <input type="hidden" name="per_page" value="<?= (int)$perPage ?>">
    <button class="admin-btn admin-btn-primary" type="submit">Filtrar</button>
    <a data-admin-posts-link class="admin-btn admin-btn-secondary" href="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Limpar</a>
  </div>
</form>
