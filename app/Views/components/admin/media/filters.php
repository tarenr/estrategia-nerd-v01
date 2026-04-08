<?php
declare(strict_types=1);

$filters = $filters ?? ['busca' => '', 'tipo' => '', 'estado' => ''];
$sort = (string) ($sort ?? 'data');
$dir = (string) ($dir ?? 'desc');
$page = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 12);
$busca = (string) ($filters['busca'] ?? '');
$tipo = (string) ($filters['tipo'] ?? '');
$estado = (string) ($filters['estado'] ?? '');
$action = url('/admin/midia');
?>

<form method="GET" action="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-panel admin-filter-panel">
  <div class="admin-filter-head">
    <div>
      <h3 class="font-orbitron text-xl font-black text-white">Filtros</h3>
      <div class="text-xs text-slate-400 mt-1">Busque por nome, pasta ou tipo de arquivo dentro da biblioteca.</div>
    </div>
    <div class="text-xs text-slate-400">Pagina atual <span class="text-cyan-300 font-bold"><?= $page ?></span> - <span class="text-slate-200 font-bold"><?= $perPage ?></span> por pagina</div>
  </div>

  <div class="admin-filter-grid admin-filter-grid-media">
    <div class="admin-filter-field admin-filter-field-search">
      <label class="admin-filter-label" for="media-busca">Buscar</label>
      <input id="media-busca" name="busca" value="<?= htmlspecialchars($busca, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" placeholder="Nome do arquivo, pasta ou MIME..." class="nerd-input admin-filter-control">
    </div>

    <div class="admin-filter-field">
      <label class="admin-filter-label" for="media-tipo">Tipo</label>
      <select id="media-tipo" name="tipo" class="nerd-input admin-filter-control">
        <option value="" <?= $tipo === '' ? 'selected' : '' ?>>Todos</option>
        <option value="imagem" <?= $tipo === 'imagem' ? 'selected' : '' ?>>Somente imagens</option>
        <option value="outros" <?= $tipo === 'outros' ? 'selected' : '' ?>>Outros arquivos</option>
      </select>
    </div>

    <div class="admin-filter-field">
      <label class="admin-filter-label" for="media-estado">Estado</label>
      <select id="media-estado" name="estado" class="nerd-input admin-filter-control">
        <option value="" <?= $estado === '' ? 'selected' : '' ?>>Todos</option>
        <option value="uso" <?= $estado === 'uso' ? 'selected' : '' ?>>Em uso</option>
        <option value="orfa" <?= $estado === 'orfa' ? 'selected' : '' ?>>Somente orfas</option>
      </select>
    </div>
  </div>

  <div class="admin-filter-actions">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="page" value="1">
    <input type="hidden" name="per_page" value="<?= $perPage ?>">
    <button type="submit" class="admin-btn admin-btn-primary admin-filter-button">Filtrar</button>
    <a class="admin-btn admin-btn-secondary admin-filter-button" href="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Limpar</a>
  </div>
</form>