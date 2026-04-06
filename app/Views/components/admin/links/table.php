<?php
declare(strict_types=1);

use App\Support\Csrf;

$items = $items ?? [];
$filters = $filters ?? ['busca' => '', 'tipo' => '', 'promocao' => '', 'status' => '', 'destaque' => '', 'monitoramento' => ''];
$sort = (string) ($sort ?? 'posicao');
$dir = (string) ($dir ?? 'asc');
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$page = max(1, (int) ($pagination['page'] ?? 1));
$pages = max(1, (int) ($pagination['pages'] ?? 1));
$perPage = max(5, (int) ($pagination['per_page'] ?? 10));
$total = max(0, (int) ($pagination['total'] ?? 0));

$baseQuery = [
    'busca' => (string) ($filters['busca'] ?? ''),
    'tipo' => (string) ($filters['tipo'] ?? ''),
    'promocao' => (string) ($filters['promocao'] ?? ''),
    'status' => (string) ($filters['status'] ?? ''),
    'destaque' => (string) ($filters['destaque'] ?? ''),
    'monitoramento' => (string) ($filters['monitoramento'] ?? ''),
    'per_page' => $perPage,
];

$currentUrl = url('/admin/links?' . http_build_query(array_filter([
    'busca' => $baseQuery['busca'],
    'tipo' => $baseQuery['tipo'],
    'promocao' => $baseQuery['promocao'],
    'status' => $baseQuery['status'],
    'destaque' => $baseQuery['destaque'],
    'monitoramento' => $baseQuery['monitoramento'],
    'sort' => $sort,
    'dir' => $dir,
    'page' => $page,
    'per_page' => $perPage,
], static fn ($value): bool => $value !== '' && $value !== 0)));

$buildUrl = static function (int $targetPage, ?int $targetPerPage = null) use ($baseQuery, $sort, $dir): string {
    return url('/admin/links?' . http_build_query(array_filter([
        'busca' => $baseQuery['busca'],
        'tipo' => $baseQuery['tipo'],
        'promocao' => $baseQuery['promocao'],
        'status' => $baseQuery['status'],
        'destaque' => $baseQuery['destaque'],
        'monitoramento' => $baseQuery['monitoramento'],
        'sort' => $sort,
        'dir' => $dir,
        'page' => max(1, $targetPage),
        'per_page' => $targetPerPage ?? (int) $baseQuery['per_page'],
    ], static fn ($value): bool => $value !== '' && $value !== 0)));
};

$sortUrl = static function (string $column) use ($baseQuery, $sort, $dir): string {
    $nextDir = $sort === $column && $dir === 'asc' ? 'desc' : 'asc';
    return url('/admin/links?' . http_build_query(array_filter([
        'busca' => $baseQuery['busca'],
        'tipo' => $baseQuery['tipo'],
        'promocao' => $baseQuery['promocao'],
        'status' => $baseQuery['status'],
        'destaque' => $baseQuery['destaque'],
        'monitoramento' => $baseQuery['monitoramento'],
        'sort' => $column,
        'dir' => $nextDir,
        'page' => 1,
        'per_page' => (int) $baseQuery['per_page'],
    ], static fn ($value): bool => $value !== '' && $value !== 0)));
};

$sortIcon = static function (string $column) use ($sort, $dir): string {
    if ($sort !== $column) {
        return '&#8596;';
    }

    return $dir === 'asc' ? '&#8593;' : '&#8595;';
};

$typeBadge = static function (string $tipo): array {
    return match ($tipo) {
        'produto' => ['label' => 'Produto', 'class' => 'border-sky-500/30 text-sky-100 bg-sky-500/10'],
        'cupom' => ['label' => 'Cupom', 'class' => 'border-blue-500/30 text-blue-100 bg-blue-500/10'],
        'rede_social' => ['label' => 'Rede Social', 'class' => 'border-indigo-500/30 text-indigo-100 bg-indigo-500/10'],
        'servico' => ['label' => 'Servicos', 'class' => 'border-orange-500/30 text-orange-100 bg-orange-500/10'],
        default => ['label' => 'Conteudo', 'class' => 'border-slate-500/30 text-slate-100 bg-slate-500/10'],
    };
};

$statusBadge = static function (string $status): array {
    return $status === 'oculto'
        ? ['label' => 'OCULTO', 'class' => 'border-slate-500/30 text-slate-300 bg-slate-500/10', 'title' => 'Ativar link']
        : ['label' => 'ATIVO', 'class' => 'border-emerald-500/30 text-emerald-300 bg-emerald-500/10', 'title' => 'Ocultar link'];
};

$expiryMeta = static function (?string $value): array {
    $raw = trim((string) $value);
    if ($raw === '') {
        return ['label' => '-', 'class' => 'text-slate-400', 'chip' => 'Sem expiracao'];
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return ['label' => $raw, 'class' => 'text-slate-400', 'chip' => 'Data invalida'];
    }

    $today = strtotime(date('Y-m-d 00:00:00'));
    $days = (int) floor(($timestamp - $today) / 86400);

    if ($days < 0) {
        return ['label' => date('d/m/Y H:i', $timestamp), 'class' => 'text-rose-300', 'chip' => 'Expirado'];
    }

    if ($days === 0) {
        return ['label' => date('d/m/Y H:i', $timestamp), 'class' => 'text-amber-300', 'chip' => 'Expira hoje'];
    }

    if ($days <= 7) {
        return ['label' => date('d/m/Y H:i', $timestamp), 'class' => 'text-amber-200', 'chip' => 'Expira em ' . $days . ' dia(s)'];
    }

    return ['label' => date('d/m/Y H:i', $timestamp), 'class' => 'text-slate-300', 'chip' => 'Programado'];
};

$monitorMeta = static function (array $item): array {
    $checkedAt = trim((string) ($item['ultima_verificacao'] ?? ''));
    $httpCode = (int) ($item['codigo_http'] ?? 0);
    $finalUrl = trim((string) ($item['url_final'] ?? ''));
    $status = (string) ($item['status'] ?? 'ativo');

    if ($checkedAt === '') {
        return [
            'label' => 'VERIFICAR',
            'class' => 'border-slate-500/30 text-slate-300 bg-slate-500/10',
            'timestamp' => 'Nenhuma checagem ainda.',
            'detail' => 'Clique para validar o destino agora.',
        ];
    }

    $isOk = $status !== 'quebrado' && $httpCode >= 200 && $httpCode < 400;
    $detail = $httpCode > 0 ? 'HTTP ' . $httpCode : 'Sem codigo HTTP';
    if ($finalUrl !== '') {
        $detail .= ' - destino atualizado';
    }

    return [
        'label' => $isOk ? 'OK' : 'REVISAR',
        'class' => $isOk ? 'border-emerald-500/30 text-emerald-300 bg-emerald-500/10' : 'border-rose-500/30 text-rose-300 bg-rose-500/10',
        'timestamp' => $checkedAt,
        'detail' => $detail,
    ];
};

$start = max(1, $page - 2);
$end = min($pages, $page + 2);
if (($end - $start) < 4) {
    $start = max(1, $end - 4);
    $end = min($pages, $start + 4);
}
$firstItem = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$lastItem = $total > 0 ? min($total, $page * $perPage) : 0;
?>

<section class="admin-panel overflow-hidden">
  <div class="overflow-x-hidden">
    <table class="w-full table-fixed text-sm" style="table-layout:fixed;width:100%;">
      <colgroup>
        <col style="width:29%;">
        <col style="width:13%;">
        <col style="width:11%;">
        <col style="width:11%;">
        <col style="width:11%;">
        <col style="width:11%;">
        <col style="width:11%;">
      </colgroup>
      <thead>
        <tr class="bg-cyan-500/10 text-slate-200">
          <?php foreach (['titulo' => 'Titulo', 'tipo' => 'Tipo', 'status' => 'Status', 'posicao' => 'Posicao', 'expira_em' => 'Expira', 'updated_at' => 'Monitoramento'] as $column => $label): ?>
            <th class="px-4 py-3 text-left font-bold uppercase tracking-[0.18em] text-xs">
              <a href="<?= htmlspecialchars($sortUrl($column), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex items-center gap-2 hover:text-cyan-300 transition" data-admin-links-link>
                <span><?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <span class="text-cyan-300 text-xs"><?= $sortIcon($column) ?></span>
              </a>
            </th>
          <?php endforeach; ?>
          <th class="px-4 py-3 text-right font-bold uppercase tracking-[0.18em] text-xs">Acoes</th>
        </tr>
      </thead>
      <tbody data-admin-links-sortable>
        <?php if ($items === []): ?>
          <tr>
            <td colspan="7" class="px-4 py-12 text-center text-slate-400">
              <div class="font-bold text-white mb-2">Nenhum link encontrado.</div>
              <div class="text-sm">Ajuste os filtros ou crie o primeiro item da Central Nerd.</div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($items as $item): ?>
            <?php
            $id = (int) ($item['id'] ?? 0);
            $status = (string) ($item['status'] ?? 'ativo');
            $statusMeta = $statusBadge($status);
            $tipoMeta = $typeBadge((string) ($item['tipo'] ?? 'conteudo'));
            $expiraMeta = $expiryMeta((string) ($item['expira_em'] ?? ''));
            $monitor = $monitorMeta($item);
            $editUrl = url('/admin/editar-link?id=' . $id);
            $deleteUrl = url('/admin/excluir-link?id=' . $id);
            $openUrl = (string) ($item['url'] ?? '#');
            $destaque = (int) ($item['destaque'] ?? 0) === 1;
            $promocao = (int) ($item['promocao'] ?? 0) === 1;
            $titulo = (string) ($item['titulo'] ?? '');
            $slug = (string) ($item['slug'] ?? '');
            $urlDestino = (string) ($item['url'] ?? '');
            $descricao = trim((string) ($item['descricao'] ?? ''));
            $grupo = trim((string) ($item['subgrupo_publico'] ?? ''));
            $desconto = trim((string) ($item['desconto_percentual'] ?? ''));
            ?>
            <tr class="border-t border-slate-800/70 align-top" data-link-row-id="<?= $id ?>" draggable="true">
              <td class="px-4 py-4">
                <div class="flex items-start gap-3">
                  <span class="mt-1 inline-flex text-slate-500 hover:text-cyan-300 cursor-grab active:cursor-grabbing" title="Arrastar para reordenar" data-link-drag-handle>
                    <i class="fa-solid fa-grip-vertical"></i>
                  </span>
                  <div class="min-w-0 flex-1">
                    <a href="<?= htmlspecialchars($openUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="font-bold text-white hover:text-cyan-300 transition">
                      <?= htmlspecialchars($titulo !== '' ? $titulo : 'Sem titulo', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </a>
                    <div class="text-xs text-slate-500 mt-1">#<?= $id ?><?php if ($slug !== ''): ?> - <?= htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?php endif; ?></div>
                    <div class="text-xs text-cyan-300 mt-2 break-all" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                      <?= htmlspecialchars($urlDestino, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </div>
                    <?php if ($descricao !== ''): ?>
                      <div class="text-xs text-slate-400 mt-2" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                        <?= htmlspecialchars($descricao, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td class="px-4 py-4">
                <div class="flex flex-col gap-2 max-w-[11rem]">
                  <span class="inline-flex min-w-[8.5rem] items-center justify-center text-center whitespace-nowrap gap-2 px-3 py-1 rounded-full text-xs font-bold border <?= $tipoMeta['class'] ?>">
                    <span><?= htmlspecialchars($tipoMeta['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                  </span>
                  <?php if ((string) ($item['tipo'] ?? '') === 'produto' && $grupo !== ''): ?>
                    <span class="inline-flex min-w-[8.5rem] items-center justify-center text-center whitespace-nowrap gap-2 px-3 py-1 rounded-full text-xs font-bold border border-cyan-500/20 text-cyan-100 bg-cyan-500/10">
                      <?= htmlspecialchars($grupo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </span>
                  <?php endif; ?>
                  <?php if ($promocao): ?>
                    <span class="inline-flex min-w-[8.5rem] items-center justify-center text-center whitespace-nowrap gap-2 px-3 py-1 rounded-full text-xs font-bold border border-emerald-500/25 text-emerald-200 bg-emerald-500/10">Promocao</span>
                  <?php endif; ?>
                  <?php if ((string) ($item['tipo'] ?? '') === 'cupom' && $desconto !== ''): ?>
                    <span class="inline-flex min-w-[8.5rem] items-center justify-center text-center whitespace-nowrap gap-2 px-3 py-1 rounded-full text-xs font-bold border border-blue-500/25 text-blue-100 bg-blue-500/10">
                      <?= htmlspecialchars($desconto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> OFF
                    </span>
                  <?php endif; ?>
                  <form method="POST" action="<?= url('/admin/links/acao') ?>" data-admin-links-action>
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="toggle_destaque">
                    <button type="submit" class="inline-flex min-w-[8.5rem] items-center justify-center text-center whitespace-nowrap gap-2 px-3 py-1 rounded-full text-xs font-bold border <?= $destaque ? 'border-fuchsia-500/25 text-fuchsia-100 bg-fuchsia-500/10' : 'border-slate-600/50 text-slate-300 bg-slate-800/40 hover:border-cyan-400/35 hover:text-cyan-200 transition' ?>">
                      <?= $destaque ? 'Destaque' : 'Sem destaque' ?>
                    </button>
                  </form>
                </div>
              </td>
              <td class="px-4 py-4">
                <form method="POST" action="<?= url('/admin/links/acao') ?>" data-admin-links-action>
                  <?= Csrf::field() ?>
                  <input type="hidden" name="id" value="<?= $id ?>">
                  <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="toggle_status">
                  <button type="submit" class="inline-flex min-w-[8rem] items-center justify-center text-center whitespace-nowrap px-2.5 py-1 rounded-full text-[11px] font-bold border transition hover:brightness-110 <?= $statusMeta['class'] ?>" title="<?= htmlspecialchars($statusMeta['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <?= htmlspecialchars($statusMeta['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                  </button>
                </form>
              </td>
              <td class="px-4 py-4 text-slate-300">
                <?= number_format((int) ($item['posicao'] ?? 0), 0, ',', '.') ?>
              </td>
              <td class="px-4 py-4">
                <div class="<?= htmlspecialchars($expiraMeta['class'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> font-semibold">
                  <?= htmlspecialchars($expiraMeta['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </div>
                <div class="text-xs text-slate-500 mt-1" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                  <?= htmlspecialchars($expiraMeta['chip'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </div>
              </td>
              <td class="px-4 py-4">
                <form method="POST" action="<?= url('/admin/links/acao') ?>" data-admin-links-action>
                  <?= Csrf::field() ?>
                  <input type="hidden" name="id" value="<?= $id ?>">
                  <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="check_link">
                  <button type="submit" class="inline-flex min-w-[8rem] items-center justify-center text-center whitespace-nowrap px-2.5 py-1 rounded-full text-[11px] font-bold border transition hover:brightness-110 <?= $monitor['class'] ?>">
                    <?= htmlspecialchars($monitor['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                  </button>
                </form>
                <div class="text-xs text-slate-400 mt-2" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                  <?= htmlspecialchars($monitor['timestamp'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </div>
                <div class="text-xs text-slate-500 mt-1" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                  <?= htmlspecialchars($monitor['detail'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </div>
              </td>
                            <td class="px-4 py-4">
                <div class="flex flex-wrap justify-end gap-2">
                  <a href="<?= htmlspecialchars($editUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary text-xs">Editar</a>
                  <a href="<?= htmlspecialchars($deleteUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="px-3 py-2 rounded-lg text-xs font-bold border border-rose-500/30 text-rose-200 hover:bg-rose-500/10 transition">Excluir</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <section class="admin-panel mt-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
      <div class="text-sm text-slate-300">
        <?php if ($total > 0): ?>
          Exibindo <span class="font-semibold text-slate-100"><?= number_format($firstItem, 0, ',', '.') ?></span>
          ate <span class="font-semibold text-slate-100"><?= number_format($lastItem, 0, ',', '.') ?></span>
          de <span class="font-semibold text-slate-100"><?= number_format($total, 0, ',', '.') ?></span> links
        <?php else: ?>
          Nenhum link para paginar no momento.
        <?php endif; ?>
      </div>
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex items-center gap-2 text-sm">
          <span class="text-slate-400">Por pagina</span>
          <?php foreach ([10, 20, 50] as $option): ?>
            <?php $active = $perPage === $option; ?>
            <a data-admin-links-link class="px-3 py-2 rounded-xl text-xs font-black border transition-all <?= $active ? 'bg-cyan-500/20 border-cyan-400/40 text-cyan-200' : 'bg-slate-800/40 border-slate-700 text-slate-300 hover:border-cyan-500/40 hover:text-cyan-200' ?>" href="<?= htmlspecialchars($buildUrl(1, $option), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= $option ?></a>
          <?php endforeach; ?>
        </div>
        <nav class="flex items-center gap-2" aria-label="Paginacao dos links">
          <a data-admin-links-link class="px-3 py-2 rounded-xl text-xs font-black border transition-all <?= $total === 0 || $page <= 1 ? 'pointer-events-none border-slate-800 text-slate-600' : 'bg-slate-800/40 border-slate-700 text-slate-300 hover:border-cyan-500/40 hover:text-cyan-200' ?>" href="<?= htmlspecialchars($buildUrl($page - 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Anterior</a>
          <?php for ($current = $start; $current <= $end; $current++): ?>
            <a data-admin-links-link class="min-w-[40px] px-3 py-2 rounded-xl text-center text-xs font-black border transition-all <?= $current === $page ? 'bg-cyan-500/20 border-cyan-400/40 text-cyan-200' : 'bg-slate-800/40 border-slate-700 text-slate-300 hover:border-cyan-500/40 hover:text-cyan-200' ?>" href="<?= htmlspecialchars($buildUrl($current), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= $current ?></a>
          <?php endfor; ?>
          <a data-admin-links-link class="px-3 py-2 rounded-xl text-xs font-black border transition-all <?= $total === 0 || $page >= $pages ? 'pointer-events-none border-slate-800 text-slate-600' : 'bg-slate-800/40 border-slate-700 text-slate-300 hover:border-cyan-500/40 hover:text-cyan-200' ?>" href="<?= htmlspecialchars($buildUrl($page + 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Proxima</a>
        </nav>
      </div>
    </div>
  </section>
</section>
