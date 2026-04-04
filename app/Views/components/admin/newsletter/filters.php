<?php
declare(strict_types=1);

$filters = $filters ?? ['busca' => '', 'status' => ''];
$pagination = $pagination ?? ['page' => 1, 'per_page' => 10];
$sort = (string) ($sort ?? 'data_cadastro');
$dir = (string) ($dir ?? 'desc');
?>

<section class="admin-panel">
  <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-6">
    <div>
      <h2 class="font-orbitron text-xl font-black text-white">Filtros</h2>
      <p class="text-sm text-slate-400 mt-2">Busque por email, nome ou IP e refine a base por status.</p>
    </div>

    <div class="text-sm text-slate-400">
      Pagina atual <span class="font-bold text-white"><?= (int) ($pagination['page'] ?? 1) ?></span> - <?= (int) ($pagination['per_page'] ?? 10) ?> por pagina
    </div>
  </div>

  <form method="GET" action="<?= url('/admin/newsletter') ?>" class="mt-6 grid grid-cols-1 lg:grid-cols-[minmax(0,1.6fr)_minmax(220px,0.7fr)_auto] gap-4 items-end" data-admin-newsletter-filters>
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

    <div>
      <label for="newsletter-busca" class="block text-sm font-bold text-slate-200 mb-2">Buscar</label>
      <input id="newsletter-busca" type="text" name="busca" value="<?= htmlspecialchars((string) ($filters['busca'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Email, nome ou IP...">
    </div>

    <div>
      <label for="newsletter-status" class="block text-sm font-bold text-slate-200 mb-2">Status</label>
      <select id="newsletter-status" name="status" class="nerd-input w-full px-4 py-3 rounded-xl">
        <option value="">Todos</option>
        <option value="ativo" <?= (string) ($filters['status'] ?? '') === 'ativo' ? 'selected' : '' ?>>Ativos</option>
        <option value="inativo" <?= (string) ($filters['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativos</option>
        <option value="desinscreve" <?= (string) ($filters['status'] ?? '') === 'desinscreve' ? 'selected' : '' ?>>Desinscritos</option>
      </select>
    </div>

    <div class="flex flex-wrap gap-3">
      <button type="submit" class="admin-btn admin-btn-primary">Filtrar</button>
      <a href="<?= url('/admin/newsletter') ?>" class="admin-btn admin-btn-secondary" data-admin-newsletter-link>Limpar</a>
    </div>
  </form>
</section>
