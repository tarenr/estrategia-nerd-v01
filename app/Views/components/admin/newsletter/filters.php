<?php
declare(strict_types=1);

$filters = $filters ?? ['busca' => '', 'status' => ''];
$pagination = $pagination ?? ['page' => 1, 'per_page' => 10];
$sort = (string) ($sort ?? 'data_cadastro');
$dir = (string) ($dir ?? 'desc');
$action = url('/admin/newsletter');
?>

<form method="GET" action="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-panel admin-filter-panel" data-admin-newsletter-filters>
  <div class="admin-filter-head">
    <div>
      <h3 class="font-orbitron text-xl font-black text-white">Filtros</h3>
      <div class="text-xs text-slate-400 mt-1">Busque por email, nome ou IP e refine a base por status.</div>
    </div>
    <div class="text-xs text-slate-400">Pagina atual <span class="text-cyan-300 font-bold"><?= (int) ($pagination['page'] ?? 1) ?></span> - <span class="text-slate-200 font-bold"><?= (int) ($pagination['per_page'] ?? 10) ?></span> por pagina</div>
  </div>

  <div class="admin-filter-grid admin-filter-grid-newsletter">
    <div class="admin-filter-field admin-filter-field-search">
      <label class="admin-filter-label" for="newsletter-busca">Buscar</label>
      <input id="newsletter-busca" type="text" name="busca" value="<?= htmlspecialchars((string) ($filters['busca'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input admin-filter-control" placeholder="Email, nome ou IP...">
    </div>

    <div class="admin-filter-field">
      <label class="admin-filter-label" for="newsletter-status">Status</label>
      <select id="newsletter-status" name="status" class="nerd-input admin-filter-control">
        <option value="" <?= (string) ($filters['status'] ?? '') === '' ? 'selected' : '' ?>>Todos</option>
        <option value="ativo" <?= (string) ($filters['status'] ?? '') === 'ativo' ? 'selected' : '' ?>>Ativos</option>
        <option value="inativo" <?= (string) ($filters['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativos</option>
        <option value="desinscreve" <?= (string) ($filters['status'] ?? '') === 'desinscreve' ? 'selected' : '' ?>>Desinscritos</option>
      </select>
    </div>
  </div>

  <div class="admin-filter-actions">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="page" value="1">
    <input type="hidden" name="per_page" value="<?= (int) ($pagination['per_page'] ?? 10) ?>">
    <button type="submit" class="admin-btn admin-btn-primary admin-filter-button">Filtrar</button>
    <a href="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary admin-filter-button" data-admin-newsletter-link>Limpar</a>
  </div>
</form>