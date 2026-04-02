<?php
declare(strict_types=1);

use App\Support\Csrf;

$items = $items ?? [];
$filters = $filters ?? ['busca' => '', 'status' => '', 'respondido' => '', 'post' => 0];
$pagination = $pagination ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$sort = (string) ($sort ?? 'data');
$dir = (string) ($dir ?? 'desc');
$baseUrl = function_exists('url') ? url('/admin/comentarios') : '/admin/comentarios';

$buildUrl = static function (array $overrides = []) use ($baseUrl, $filters, $pagination, $sort, $dir): string {
    $query = [
        'busca' => (string) ($filters['busca'] ?? ''),
        'status' => (string) ($filters['status'] ?? ''),
        'respondido' => (string) ($filters['respondido'] ?? ''),
        'post' => (int) ($filters['post'] ?? 0),
        'sort' => $sort,
        'dir' => $dir,
        'page' => (int) ($pagination['page'] ?? 1),
        'per_page' => (int) ($pagination['per_page'] ?? 10),
    ];

    foreach ($overrides as $key => $value) {
        $query[$key] = $value;
    }

    $query = array_filter($query, static fn ($value): bool => !($value === '' || $value === null || $value === 0));
    $qs = http_build_query($query);
    return $qs !== '' ? $baseUrl . '?' . $qs : $baseUrl;
};

$sortLink = static function (string $column) use ($sort, $dir, $buildUrl): string {
    $nextDir = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';
    return $buildUrl(['sort' => $column, 'dir' => $nextDir, 'page' => 1]);
};

$sortIcon = static function (string $column) use ($sort, $dir): string {
    if ($sort !== $column) {
        return '<span class="text-slate-600">&harr;</span>';
    }

    return $dir === 'asc' ? '<span class="text-cyan-300">&uarr;</span>' : '<span class="text-cyan-300">&darr;</span>';
};

$formatDate = static function ($value): string {
    if (!$value) {
        return '-';
    }

    try {
        return (new DateTimeImmutable((string) $value))->format('d/m/Y H:i');
    } catch (Throwable) {
        return (string) $value;
    }
};

$truncate = static function (string $value, int $limit = 140): string {
    $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? $value);
    if (mb_strlen($value) <= $limit) {
        return $value;
    }

    return rtrim(mb_substr($value, 0, $limit - 1)) . '...';
};

$statusBadge = static function (string $status): string {
    return match ($status) {
        'aprovado' => 'background:rgba(34,197,94,.15); color:#86efac; border:1px solid rgba(34,197,94,.3);',
        'reprovado' => 'background:rgba(248,113,113,.12); color:#fda4af; border:1px solid rgba(248,113,113,.28);',
        'spam' => 'background:rgba(168,85,247,.12); color:#d8b4fe; border:1px solid rgba(168,85,247,.28);',
        default => 'background:rgba(250,204,21,.12); color:#fde68a; border:1px solid rgba(250,204,21,.28);',
    };
};

$currentUrl = $buildUrl();
$page = (int) ($pagination['page'] ?? 1);
$pages = max(1, (int) ($pagination['pages'] ?? 1));
$total = (int) ($pagination['total'] ?? count($items));
$perPage = (int) ($pagination['per_page'] ?? 10);
?>

<section class="admin-panel">
  <div class="flex items-center justify-between mb-6 gap-4">
    <div>
      <h3 class="font-orbitron text-xl font-black text-white">Central de Comentarios</h3>
      <div class="text-xs text-slate-400 mt-1"><?= number_format($total, 0, ',', '.') ?> comentario(s) encontrado(s)</div>
    </div>
    <span class="text-cyan-400 text-sm font-bold uppercase"><?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> / <?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
  </div>

  <?php if ($items === []): ?>
    <div class="text-center py-12 border-2 border-dashed border-gray-700 rounded-xl">
      <div class="text-3xl mb-4 font-orbitron font-black text-cyan-300">SEM</div>
      <h4 class="text-xl font-bold text-white mb-2">Nenhum comentario encontrado</h4>
      <div class="text-slate-400 text-sm">Ajuste os filtros para encontrar comentarios especificos.</div>
    </div>
  <?php else: ?>
    <div class="overflow-x-auto rounded-xl border border-slate-800">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-800/70 text-slate-300">
          <tr class="text-left">
            <th class="px-4 py-3 font-semibold"><a data-admin-comments-link class="inline-flex items-center gap-2 hover:text-cyan-300 transition" href="<?= htmlspecialchars($sortLink('autor'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Autor <?= $sortIcon('autor') ?></a></th>
            <th class="px-4 py-3 font-semibold">Comentario</th>
            <th class="px-4 py-3 font-semibold"><a data-admin-comments-link class="inline-flex items-center gap-2 hover:text-cyan-300 transition" href="<?= htmlspecialchars($sortLink('post'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Post <?= $sortIcon('post') ?></a></th>
            <th class="px-4 py-3 font-semibold"><a data-admin-comments-link class="inline-flex items-center gap-2 hover:text-cyan-300 transition" href="<?= htmlspecialchars($sortLink('status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Status <?= $sortIcon('status') ?></a></th>
            <th class="px-4 py-3 font-semibold"><a data-admin-comments-link class="inline-flex items-center gap-2 hover:text-cyan-300 transition" href="<?= htmlspecialchars($sortLink('respondido'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Resposta <?= $sortIcon('respondido') ?></a></th>
            <th class="px-4 py-3 font-semibold"><a data-admin-comments-link class="inline-flex items-center gap-2 hover:text-cyan-300 transition" href="<?= htmlspecialchars($sortLink('data'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Data <?= $sortIcon('data') ?></a></th>
            <th class="px-4 py-3 font-semibold text-right">Acoes</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/70">
          <?php foreach ($items as $item): ?>
            <?php
              $id = (int) ($item['id'] ?? 0);
              $status = (string) ($item['status'] ?? 'pendente');
              $hasReply = (int) ($item['has_reply'] ?? 0) === 1;
              $postId = (int) ($item['post_id'] ?? 0);
              $postTitulo = (string) ($item['post_titulo'] ?? 'Post removido');
              $deleteUrl = url('/admin/excluir-comentario?id=' . $id . '&return_to=' . rawurlencode($currentUrl));
              $replyUrl = url('/admin/responder-comentario?id=' . $id . '&return_to=' . rawurlencode($currentUrl));
              $editPostUrl = $postId > 0 ? url('/admin/editar-post?id=' . $postId) : '';
            ?>
            <tr class="hover:bg-slate-800/40 transition">
              <td class="px-4 py-4 align-top">
                <div class="font-semibold text-slate-100"><?= htmlspecialchars((string) ($item['nome'] ?? 'Anonimo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="mt-1 text-xs text-slate-400"><?= htmlspecialchars((string) ($item['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="mt-2 text-[11px] text-slate-500">#<?= $id ?></div>
              </td>
              <td class="px-4 py-4 align-top"><div class="text-slate-200 leading-relaxed"><?= htmlspecialchars($truncate((string) ($item['comentario'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div></td>
              <td class="px-4 py-4 align-top"><?php if ($editPostUrl !== ''): ?><a href="<?= htmlspecialchars($editPostUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="text-cyan-200 hover:text-cyan-100 transition font-semibold"><?= htmlspecialchars($postTitulo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a><?php else: ?><span class="text-slate-400"><?= htmlspecialchars($postTitulo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span><?php endif; ?><div class="mt-1 text-xs text-slate-500">Post #<?= $postId ?></div></td>
              <td class="px-4 py-4 align-top"><span class="status-badge" style="<?= htmlspecialchars($statusBadge($status), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($status, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></td>
              <td class="px-4 py-4 align-top"><span class="admin-chip inline-flex whitespace-nowrap"><?= $hasReply ? 'respondido' : 'sem resposta' ?></span></td>
              <td class="px-4 py-4 align-top text-slate-300"><?= htmlspecialchars($formatDate($item['data'] ?? null), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
              <td class="px-4 py-4 align-top"><div class="flex flex-col items-end gap-2"><form method="POST" action="<?= url('/admin/moderar-comentario') ?>" class="flex flex-wrap justify-end gap-2"><?= Csrf::field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return_to" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?php if ($status !== 'aprovado'): ?><button type="submit" name="action" value="approve" class="btn-edit px-3 py-2 rounded-lg text-xs font-bold">Aprovar</button><?php endif; ?><?php if ($status !== 'reprovado'): ?><button type="submit" name="action" value="reject" class="admin-btn admin-btn-secondary text-xs">Reprovar</button><?php endif; ?><?php if ($status !== 'spam'): ?><button type="submit" name="action" value="spam" class="admin-btn admin-btn-secondary text-xs" style="border-color:rgba(168,85,247,.25); color:#d8b4fe;">Spam</button><?php endif; ?><?php if ($status !== 'pendente'): ?><button type="submit" name="action" value="pending" class="admin-btn admin-btn-secondary text-xs">Pendente</button><?php endif; ?></form><div class="flex flex-wrap justify-end gap-2"><a class="admin-btn admin-btn-secondary text-xs" href="<?= htmlspecialchars($replyUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= $hasReply ? 'Responder novamente' : 'Responder' ?></a><a class="px-3 py-2 rounded-lg text-xs font-bold border border-rose-500/30 text-rose-200 hover:bg-rose-500/10 transition" href="<?= htmlspecialchars($deleteUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Excluir</a></div></div></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="mt-5 flex items-center justify-between gap-4 flex-wrap rounded-xl border border-slate-800 bg-slate-900/40 px-4 py-3">
      <div class="text-xs text-slate-400">Pagina atual: <?= $page ?> - <?= $perPage ?> por pagina</div>
      <div class="flex items-center gap-2">
        <?php $prevUrl = $buildUrl(['page' => max(1, $page - 1)]); $nextUrl = $buildUrl(['page' => min($pages, $page + 1)]); $prevDisabled = $page <= 1; $nextDisabled = $page >= $pages; ?>
        <a data-admin-comments-link class="admin-btn admin-btn-secondary <?= $prevDisabled ? 'pointer-events-none opacity-50' : '' ?>" href="<?= htmlspecialchars($prevUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Anterior</a>
        <span class="admin-chip"><?= $page ?> / <?= $pages ?></span>
        <a data-admin-comments-link class="admin-btn admin-btn-secondary <?= $nextDisabled ? 'pointer-events-none opacity-50' : '' ?>" href="<?= htmlspecialchars($nextUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Proxima</a>
      </div>
    </div>
  <?php endif; ?>
</section>