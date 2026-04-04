<?php
declare(strict_types=1);

$filters = $filters ?? ['busca' => '', 'tipo' => '', 'status' => '', 'destaque' => ''];
$pagination = $pagination ?? ['page' => 1, 'per_page' => 10];
$sort = (string) ($sort ?? 'posicao');
$dir = (string) ($dir ?? 'asc');
?>

<section class="admin-panel">
  <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-6">
    <div>
      <h2 class="font-orbitron text-xl font-black text-white">Filtros</h2>
      <p class="text-sm text-slate-400 mt-2">Busque por titulo, slug ou URL e refine a base por tipo, status e destaque.</p>
    </div>

    <div class="text-sm text-slate-400">
      Pagina atual <span class="font-bold text-white"><?= (int) ($pagination['page'] ?? 1) ?></span> - <?= (int) ($pagination['per_page'] ?? 10) ?> por pagina
    </div>
  </div>

  <form method="GET" action="<?= url('/admin/links') ?>" class="mt-6 grid grid-cols-1 lg:grid-cols-[minmax(0,1.5fr)_minmax(180px,0.55fr)_minmax(180px,0.55fr)_minmax(180px,0.55fr)_auto] gap-4 items-end" data-admin-links-filters>
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

    <div>
      <label for="links-busca" class="block text-sm font-bold text-slate-200 mb-2">Buscar</label>
      <input id="links-busca" type="text" name="busca" value="<?= htmlspecialchars((string) ($filters['busca'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Titulo, slug, descricao ou URL...">
    </div>

    <div>
      <label for="links-tipo" class="block text-sm font-bold text-slate-200 mb-2">Tipo</label>
      <select id="links-tipo" name="tipo" class="nerd-input w-full px-4 py-3 rounded-xl">
        <option value="">Todos</option>
        <option value="afiliado" <?= (string) ($filters['tipo'] ?? '') === 'afiliado' ? 'selected' : '' ?>>Afiliado</option>
        <option value="oferta" <?= (string) ($filters['tipo'] ?? '') === 'oferta' ? 'selected' : '' ?>>Oferta</option>
        <option value="conteudo" <?= (string) ($filters['tipo'] ?? '') === 'conteudo' ? 'selected' : '' ?>>Conteudo</option>
        <option value="rede_social" <?= (string) ($filters['tipo'] ?? '') === 'rede_social' ? 'selected' : '' ?>>Rede social</option>
        <option value="servico" <?= (string) ($filters['tipo'] ?? '') === 'servico' ? 'selected' : '' ?>>Servico</option>
      </select>
    </div>

    <div>
      <label for="links-status" class="block text-sm font-bold text-slate-200 mb-2">Status</label>
      <select id="links-status" name="status" class="nerd-input w-full px-4 py-3 rounded-xl">
        <option value="">Todos</option>
        <option value="ativo" <?= (string) ($filters['status'] ?? '') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
        <option value="oculto" <?= (string) ($filters['status'] ?? '') === 'oculto' ? 'selected' : '' ?>>Oculto</option>
        <option value="expirado" <?= (string) ($filters['status'] ?? '') === 'expirado' ? 'selected' : '' ?>>Expirado</option>
        <option value="quebrado" <?= (string) ($filters['status'] ?? '') === 'quebrado' ? 'selected' : '' ?>>Quebrado</option>
      </select>
    </div>

    <div>
      <label for="links-destaque" class="block text-sm font-bold text-slate-200 mb-2">Destaque</label>
      <select id="links-destaque" name="destaque" class="nerd-input w-full px-4 py-3 rounded-xl">
        <option value="">Todos</option>
        <option value="1" <?= (string) ($filters['destaque'] ?? '') === '1' ? 'selected' : '' ?>>Somente destaque</option>
        <option value="0" <?= (string) ($filters['destaque'] ?? '') === '0' ? 'selected' : '' ?>>Sem destaque</option>
      </select>
    </div>

    <div class="flex flex-wrap gap-3">
      <button type="submit" class="admin-btn admin-btn-primary">Filtrar</button>
      <a href="<?= url('/admin/links') ?>" class="admin-btn admin-btn-secondary" data-admin-links-link>Limpar</a>
    </div>
  </form>
</section>
