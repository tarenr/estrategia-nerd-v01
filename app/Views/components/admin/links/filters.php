<?php
declare(strict_types=1);

$filters = $filters ?? ['busca' => '', 'tipo' => '', 'promocao' => '', 'status' => '', 'destaque' => '', 'monitoramento' => ''];
$pagination = $pagination ?? ['page' => 1, 'per_page' => 10];
$sort = (string) ($sort ?? 'posicao');
$dir = (string) ($dir ?? 'asc');
?>

<section class="admin-panel">
  <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-6">
    <div>
      <h2 class="font-orbitron text-xl font-black text-white">Filtros</h2>
      <p class="text-sm text-slate-400 mt-2">Busque por titulo, slug, grupo ou URL e refine a base pelos tipos reais da Central Nerd.</p>
    </div>

    <div class="text-sm text-slate-400">
      Pagina atual <span class="font-bold text-white"><?= (int) ($pagination['page'] ?? 1) ?></span> - <?= (int) ($pagination['per_page'] ?? 10) ?> por pagina
    </div>
  </div>

  <form method="GET" action="<?= url('/admin/links') ?>" class="mt-6 space-y-4" data-admin-links-filters>
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_auto] gap-4 items-end">
      <div>
        <label for="links-busca" class="block text-sm font-bold text-slate-200 mb-2">Buscar</label>
        <input id="links-busca" type="text" name="busca" value="<?= htmlspecialchars((string) ($filters['busca'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input w-full px-4 py-3 rounded-xl" placeholder="Titulo, slug, grupo, descricao ou URL...">
      </div>

      <div class="flex flex-wrap gap-3 xl:justify-end">
        <button type="submit" class="admin-btn admin-btn-primary !h-[50px] !px-5 !py-0 rounded-xl">Filtrar</button>
        <a href="<?= url('/admin/links') ?>" class="admin-btn admin-btn-secondary !h-[50px] !px-5 !py-0 rounded-xl" data-admin-links-link>Limpar</a>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
      <div>
        <label for="links-tipo" class="block text-sm font-bold text-slate-200 mb-2">Tipo</label>
        <select id="links-tipo" name="tipo" class="nerd-input w-full px-4 py-3 rounded-xl">
          <option value="">Todos</option>
          <option value="produto" <?= (string) ($filters['tipo'] ?? '') === 'produto' ? 'selected' : '' ?>>Produto</option>
          <option value="cupom" <?= (string) ($filters['tipo'] ?? '') === 'cupom' ? 'selected' : '' ?>>Cupom de Desconto</option>
          <option value="conteudo" <?= (string) ($filters['tipo'] ?? '') === 'conteudo' ? 'selected' : '' ?>>Conteudo</option>
          <option value="rede_social" <?= (string) ($filters['tipo'] ?? '') === 'rede_social' ? 'selected' : '' ?>>Rede Social</option>
          <option value="servico" <?= (string) ($filters['tipo'] ?? '') === 'servico' ? 'selected' : '' ?>>Servicos</option>
        </select>
      </div>

      <div>
        <label for="links-promocao" class="block text-sm font-bold text-slate-200 mb-2">Promocao</label>
        <select id="links-promocao" name="promocao" class="nerd-input w-full px-4 py-3 rounded-xl">
          <option value="">Todos</option>
          <option value="1" <?= (string) ($filters['promocao'] ?? '') === '1' ? 'selected' : '' ?>>Somente promocoes</option>
          <option value="0" <?= (string) ($filters['promocao'] ?? '') === '0' ? 'selected' : '' ?>>Sem promocao</option>
        </select>
      </div>

      <div>
        <label for="links-status" class="block text-sm font-bold text-slate-200 mb-2">Status</label>
        <select id="links-status" name="status" class="nerd-input w-full px-4 py-3 rounded-xl">
          <option value="">Todos</option>
          <option value="ativo" <?= (string) ($filters['status'] ?? '') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
          <option value="oculto" <?= (string) ($filters['status'] ?? '') === 'oculto' ? 'selected' : '' ?>>Oculto</option>
          <option value="expirado" <?= (string) ($filters['status'] ?? '') === 'expirado' ? 'selected' : '' ?>>Expirado</option>
          <option value="quebrado" <?= (string) ($filters['status'] ?? '') === 'quebrado' ? 'selected' : '' ?>>Revisar</option>
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

      <div>
        <label for="links-monitoramento" class="block text-sm font-bold text-slate-200 mb-2">Monitoramento</label>
        <select id="links-monitoramento" name="monitoramento" class="nerd-input w-full px-4 py-3 rounded-xl">
          <option value="">Todos</option>
          <option value="expirando" <?= (string) ($filters['monitoramento'] ?? '') === 'expirando' ? 'selected' : '' ?>>Expirando em 7 dias</option>
          <option value="quebrados" <?= (string) ($filters['monitoramento'] ?? '') === 'quebrados' ? 'selected' : '' ?>>Somente revisar</option>
          <option value="verificados" <?= (string) ($filters['monitoramento'] ?? '') === 'verificados' ? 'selected' : '' ?>>Ja verificados</option>
          <option value="sem_verificacao" <?= (string) ($filters['monitoramento'] ?? '') === 'sem_verificacao' ? 'selected' : '' ?>>Sem verificacao</option>
        </select>
      </div>
    </div>
  </form>
</section>