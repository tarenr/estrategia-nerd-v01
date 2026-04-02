<?php
declare(strict_types=1);

$filters = $filters ?? ['busca' => '', 'status' => '', 'respondido' => '', 'post' => 0];
$posts = $posts ?? [];
$sort = (string) ($sort ?? 'data');
$dir = (string) ($dir ?? 'desc');
$page = (int) ($pagination['page'] ?? 1);
$perPage = (int) ($pagination['per_page'] ?? 10);
$busca = (string) ($filters['busca'] ?? '');
$status = (string) ($filters['status'] ?? '');
$respondido = (string) ($filters['respondido'] ?? '');
$post = (int) ($filters['post'] ?? 0);
$action = function_exists('url') ? url('/admin/comentarios') : '/admin/comentarios';
?>

<form method="GET" action="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-admin-comments-filters class="bg-slate-900/50 border border-cyan-500/20 rounded-2xl p-6">
  <div class="flex items-center justify-between mb-4 gap-4 flex-wrap">
    <div>
      <h3 class="font-orbitron text-xl font-black text-white">Filtros</h3>
      <div class="text-xs text-slate-400 mt-1">Busque por autor, email, texto ou post e refine a moderacao sem recarregar a pagina inteira</div>
    </div>
    <div class="text-xs text-slate-400">Pagina atual <span class="text-cyan-300 font-bold"><?= $page ?></span> - <span class="text-slate-200 font-bold"><?= $perPage ?></span> por pagina</div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
    <div class="md:col-span-5"><label class="block text-xs text-slate-400 mb-1">Buscar</label><input name="busca" value="<?= htmlspecialchars($busca, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" placeholder="Autor, email, comentario ou post..." class="nerd-input w-full px-4 py-3 rounded-xl" /></div>
    <div class="md:col-span-2"><label class="block text-xs text-slate-400 mb-1">Status</label><select name="status" class="nerd-input w-full px-4 py-3 rounded-xl"><option value="" <?= $status === '' ? 'selected' : '' ?>>Todos</option><option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>>Pendente</option><option value="aprovado" <?= $status === 'aprovado' ? 'selected' : '' ?>>Aprovado</option><option value="reprovado" <?= $status === 'reprovado' ? 'selected' : '' ?>>Reprovado</option><option value="spam" <?= $status === 'spam' ? 'selected' : '' ?>>Spam</option></select></div>
    <div class="md:col-span-2"><label class="block text-xs text-slate-400 mb-1">Respondido</label><select name="respondido" class="nerd-input w-full px-4 py-3 rounded-xl"><option value="" <?= $respondido === '' ? 'selected' : '' ?>>Todos</option><option value="1" <?= $respondido === '1' ? 'selected' : '' ?>>Respondidos</option><option value="0" <?= $respondido === '0' ? 'selected' : '' ?>>Nao respondidos</option></select></div>
    <div class="md:col-span-3"><label class="block text-xs text-slate-400 mb-1">Post</label><select name="post" class="nerd-input w-full px-4 py-3 rounded-xl"><option value="0" <?= $post === 0 ? 'selected' : '' ?>>Todos</option><?php foreach ($posts as $item): ?><?php $id = (int) ($item['id'] ?? 0); ?><option value="<?= $id ?>" <?= $post === $id ? 'selected' : '' ?>><?= htmlspecialchars((string) ($item['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option><?php endforeach; ?></select></div>
  </div>

  <div class="mt-4 flex flex-wrap items-center gap-2">
    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="dir" value="<?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <input type="hidden" name="page" value="1">
    <input type="hidden" name="per_page" value="<?= $perPage ?>">
    <button class="admin-btn admin-btn-primary" type="submit">Filtrar</button>
    <a data-admin-comments-link class="admin-btn admin-btn-secondary" href="<?= htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Limpar</a>
  </div>
</form>