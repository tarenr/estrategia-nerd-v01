<?php
declare(strict_types=1);

$filters = $filters ?? ['busca' => '', 'tipo' => ''];
$sort = (string) ($sort ?? 'data');
$dir = (string) ($dir ?? 'desc');
$page = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 12);
$action = url('/admin/midia');
?>

<form method="GET" action="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="bg-slate-900/50 border border-cyan-500/20 rounded-2xl p-6">
  <div class="flex items-center justify-between mb-4 gap-4 flex-wrap">
    <div>
      <h3 class="font-orbitron text-xl font-black text-white">Filtros</h3>
      <div class="text-xs text-slate-400 mt-1">Busque por nome, pasta ou tipo de arquivo dentro da biblioteca.</div>
    </div>
    <div class="text-xs text-slate-400">Pagina atual <span class="text-cyan-300 font-bold"><?= $page ?></span> - <span class="text-slate-200 font-bold"><?= $perPage ?></span> por pagina</div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
    <div class="md:col-span-8">
      <label class="block text-xs text-slate-400 mb-1">Buscar</label>
      <input name="busca" value="<?= htmlspecialchars((string) ($filters['busca'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" placeholder="Nome do arquivo, pasta ou MIME..." class="nerd-input w-full px-4 py-3 rounded-xl">
    </div>
    <div class="md:col-span-4">
      <label class="block text-xs text-slate-400 mb-1">Tipo</label>
      <select name="tipo" class="nerd-input w-full px-4 py-3 rounded-xl">
        <option value="" <?= ($filters['tipo'] ?? '') === '' ? 'selected' : '' ?>>Todos</option>
        <option value="imagem" <?= ($filters['tipo'] ?? '') === 'imagem' ? 'selected' : '' ?>>Somente imagens</option>
        <option value="outros" <?= ($filters['tipo'] ?? '') === 'outros' ? 'selected' : '' ?>>Outros arquivos</option>
      </select>
    </div>
  </div>

  <div class="mt-4 flex flex-wrap items-center gap-2">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="page" value="1">
    <input type="hidden" name="per_page" value="<?= $perPage ?>">
    <button type="submit" class="admin-btn admin-btn-primary">Filtrar</button>
    <a class="admin-btn admin-btn-secondary" href="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Limpar</a>
  </div>
</form>