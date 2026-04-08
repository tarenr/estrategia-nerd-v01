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

<form method="GET" action="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-admin-posts-filters class="admin-panel admin-filter-panel">
  <div class="admin-filter-head">
    <div>
      <h3 class="font-orbitron text-xl font-black text-white">Filtros</h3>
      <div class="text-xs text-slate-400 mt-1">Refine a listagem mantendo a navegacao fluida</div>
    </div>
    <div class="text-xs text-slate-400">Pagina atual <span class="text-cyan-300 font-bold"><?= (int)$page ?></span> - <span class="text-slate-200 font-bold"><?= (int)$perPage ?></span> por pagina</div>
  </div>

  <div class="admin-filter-grid admin-filter-grid-posts">
    <div class="admin-filter-field admin-filter-field-search">
      <label class="admin-filter-label" for="posts-busca">Buscar</label>
      <input id="posts-busca" name="busca" value="<?= htmlspecialchars($busca, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" placeholder="Titulo, resumo ou slug..." class="nerd-input admin-filter-control" />
    </div>

    <div class="admin-filter-field">
      <label class="admin-filter-label" for="posts-status">Status</label>
      <select id="posts-status" name="status" class="nerd-input admin-filter-control">
        <option value="" <?= $status === '' ? 'selected' : '' ?>>Todos</option>
        <option value="publicado" <?= $status === 'publicado' ? 'selected' : '' ?>>Publicado</option>
        <option value="rascunho" <?= $status === 'rascunho' ? 'selected' : '' ?>>Rascunho</option>
        <option value="agendado" <?= $status === 'agendado' ? 'selected' : '' ?>>Agendado</option>
      </select>
    </div>

    <div class="admin-filter-field">
      <label class="admin-filter-label" for="posts-categoria">Categoria</label>
      <select id="posts-categoria" name="categoria" class="nerd-input admin-filter-control">
        <option value="0" <?= $categoria === 0 ? 'selected' : '' ?>>Todas</option>
        <?php foreach ($categorias as $c): ?>
          <?php $cid = (int)($c['id'] ?? 0); $cnome = (string)($c['nome'] ?? ''); ?>
          <option value="<?= $cid ?>" <?= $categoria === $cid ? 'selected' : '' ?>><?= htmlspecialchars($cnome, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="admin-filter-field">
      <label class="admin-filter-label" for="posts-destaque">Destaque</label>
      <select id="posts-destaque" name="destaque" class="nerd-input admin-filter-control">
        <option value="" <?= $destaque === '' ? 'selected' : '' ?>>Todos</option>
        <option value="1" <?= $destaque === '1' ? 'selected' : '' ?>>Somente destaques</option>
        <option value="0" <?= $destaque === '0' ? 'selected' : '' ?>>Sem destaque</option>
      </select>
    </div>
  </div>

  <div class="admin-filter-actions">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="page" value="1">
    <input type="hidden" name="per_page" value="<?= (int)$perPage ?>">
    <button class="admin-btn admin-btn-primary admin-filter-button" type="submit">Filtrar</button>
    <a data-admin-posts-link class="admin-btn admin-btn-secondary admin-filter-button" href="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Limpar</a>
  </div>
</form>