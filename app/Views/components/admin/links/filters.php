<?php
declare(strict_types=1);

$filters = $filters ?? ['busca' => '', 'tipo' => '', 'promocao' => '', 'status' => '', 'destaque' => '', 'monitoramento' => ''];
$pagination = $pagination ?? ['page' => 1, 'per_page' => 10];
$sort = (string) ($sort ?? 'posicao');
$dir = (string) ($dir ?? 'asc');
$page = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 10);
$action = url('/admin/links');
?>

<form method="GET" action="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-panel admin-filter-panel" data-admin-links-filters>
  <div class="admin-filter-head">
    <div>
      <h3 class="font-orbitron text-xl font-black text-white">Filtros</h3>
      <div class="text-xs text-slate-400 mt-1">Busque por titulo, slug, grupo ou URL e refine a base pelos tipos reais da Central Nerd.</div>
    </div>
    <div class="text-xs text-slate-400">Pagina atual <span class="text-cyan-300 font-bold"><?= $page ?></span> - <span class="text-slate-200 font-bold"><?= $perPage ?></span> por pagina</div>
  </div>

  <div class="admin-filter-grid admin-filter-grid-links">
    <div class="admin-filter-field admin-filter-field-search">
      <label class="admin-filter-label" for="links-busca">Buscar</label>
      <input id="links-busca" type="text" name="busca" value="<?= htmlspecialchars((string) ($filters['busca'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nerd-input admin-filter-control" placeholder="Titulo, slug, grupo, descricao ou URL...">
    </div>

    <div class="admin-filter-field">
      <label class="admin-filter-label" for="links-tipo">Tipo</label>
      <select id="links-tipo" name="tipo" class="nerd-input admin-filter-control">
        <option value="">Todos</option>
        <option value="produto" <?= (string) ($filters['tipo'] ?? '') === 'produto' ? 'selected' : '' ?>>Produto</option>
        <option value="cupom" <?= (string) ($filters['tipo'] ?? '') === 'cupom' ? 'selected' : '' ?>>Cupom de Desconto</option>
        <option value="conteudo" <?= (string) ($filters['tipo'] ?? '') === 'conteudo' ? 'selected' : '' ?>>Conteudo</option>
        <option value="rede_social" <?= (string) ($filters['tipo'] ?? '') === 'rede_social' ? 'selected' : '' ?>>Rede Social</option>
        <option value="servico" <?= (string) ($filters['tipo'] ?? '') === 'servico' ? 'selected' : '' ?>>Servicos</option>
      </select>
    </div>

    <div class="admin-filter-field">
      <label class="admin-filter-label" for="links-promocao">Promocao</label>
      <select id="links-promocao" name="promocao" class="nerd-input admin-filter-control">
        <option value="">Todos</option>
        <option value="1" <?= (string) ($filters['promocao'] ?? '') === '1' ? 'selected' : '' ?>>Somente promocoes</option>
        <option value="0" <?= (string) ($filters['promocao'] ?? '') === '0' ? 'selected' : '' ?>>Sem promocao</option>
      </select>
    </div>

    <div class="admin-filter-field">
      <label class="admin-filter-label" for="links-status">Status</label>
      <select id="links-status" name="status" class="nerd-input admin-filter-control">
        <option value="">Todos</option>
        <option value="ativo" <?= (string) ($filters['status'] ?? '') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
        <option value="oculto" <?= (string) ($filters['status'] ?? '') === 'oculto' ? 'selected' : '' ?>>Oculto</option>
        <option value="expirado" <?= (string) ($filters['status'] ?? '') === 'expirado' ? 'selected' : '' ?>>Expirado</option>
        <option value="quebrado" <?= (string) ($filters['status'] ?? '') === 'quebrado' ? 'selected' : '' ?>>Revisar</option>
      </select>
    </div>

    <div class="admin-filter-field">
      <label class="admin-filter-label" for="links-destaque">Destaque</label>
      <select id="links-destaque" name="destaque" class="nerd-input admin-filter-control">
        <option value="">Todos</option>
        <option value="1" <?= (string) ($filters['destaque'] ?? '') === '1' ? 'selected' : '' ?>>Somente destaque</option>
        <option value="0" <?= (string) ($filters['destaque'] ?? '') === '0' ? 'selected' : '' ?>>Sem destaque</option>
      </select>
    </div>

    <div class="admin-filter-field">
      <label class="admin-filter-label" for="links-monitoramento">Monitoramento</label>
      <select id="links-monitoramento" name="monitoramento" class="nerd-input admin-filter-control">
        <option value="">Todos</option>
        <option value="expirando" <?= (string) ($filters['monitoramento'] ?? '') === 'expirando' ? 'selected' : '' ?>>Expirando em 7 dias</option>
        <option value="quebrados" <?= (string) ($filters['monitoramento'] ?? '') === 'quebrados' ? 'selected' : '' ?>>Somente revisar</option>
        <option value="verificados" <?= (string) ($filters['monitoramento'] ?? '') === 'verificados' ? 'selected' : '' ?>>Ja verificados</option>
        <option value="sem_verificacao" <?= (string) ($filters['monitoramento'] ?? '') === 'sem_verificacao' ? 'selected' : '' ?>>Sem verificacao</option>
      </select>
    </div>
  </div>

  <div class="admin-filter-actions">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="page" value="1">
    <input type="hidden" name="per_page" value="<?= $perPage ?>">
    <button type="submit" class="admin-btn admin-btn-primary admin-filter-button">Filtrar</button>
    <a href="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary admin-filter-button" data-admin-links-link>Limpar</a>
  </div>
</form>