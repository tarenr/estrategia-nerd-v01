<?php
declare(strict_types=1);

$items = $items ?? [];
$filters = $filters ?? ['busca' => '', 'tipo' => '', 'status' => '', 'destaque' => ''];
$sort = (string) ($sort ?? 'posicao');
$dir = (string) ($dir ?? 'asc');
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];

$baseQuery = [
    'busca' => (string) ($filters['busca'] ?? ''),
    'tipo' => (string) ($filters['tipo'] ?? ''),
    'status' => (string) ($filters['status'] ?? ''),
    'destaque' => (string) ($filters['destaque'] ?? ''),
    'per_page' => (int) ($pagination['per_page'] ?? 10),
];

$sortUrl = static function (string $column) use ($baseQuery, $sort, $dir): string {
    $nextDir = $sort === $column && $dir === 'asc' ? 'desc' : 'asc';
    return url('/admin/links?' . http_build_query(array_filter([
        'busca' => $baseQuery['busca'],
        'tipo' => $baseQuery['tipo'],
        'status' => $baseQuery['status'],
        'destaque' => $baseQuery['destaque'],
        'sort' => $column,
        'dir' => $nextDir,
        'page' => 1,
        'per_page' => $baseQuery['per_page'],
    ], static fn ($value): bool => $value !== '' && $value !== 0)));
};

$pageUrl = static function (int $page) use ($baseQuery, $sort, $dir): string {
    return url('/admin/links?' . http_build_query(array_filter([
        'busca' => $baseQuery['busca'],
        'tipo' => $baseQuery['tipo'],
        'status' => $baseQuery['status'],
        'destaque' => $baseQuery['destaque'],
        'sort' => $sort,
        'dir' => $dir,
        'page' => $page,
        'per_page' => $baseQuery['per_page'],
    ], static fn ($value): bool => $value !== '' && $value !== 0)));
};

$sortIcon = static function (string $column) use ($sort, $dir): string {
    if ($sort !== $column) {
        return '&#8596;';
    }

    return $dir === 'asc' ? '&#8593;' : '&#8595;';
};

$typeLabel = static function (string $tipo): string {
    return match ($tipo) {
        'afiliado' => 'Afiliado',
        'oferta' => 'Oferta',
        'rede_social' => 'Rede social',
        'servico' => 'Servico',
        default => 'Conteudo',
    };
};

$statusBadge = static function (string $status): array {
    return match ($status) {
        'ativo' => ['label' => 'ATIVO', 'class' => 'border-emerald-500/30 text-emerald-300 bg-emerald-500/10'],
        'oculto' => ['label' => 'OCULTO', 'class' => 'border-slate-500/30 text-slate-300 bg-slate-500/10'],
        'expirado' => ['label' => 'EXPIRADO', 'class' => 'border-amber-500/30 text-amber-300 bg-amber-500/10'],
        default => ['label' => 'QUEBRADO', 'class' => 'border-rose-500/30 text-rose-300 bg-rose-500/10'],
    };
};
?>

<section class="admin-panel overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="bg-cyan-500/10 text-slate-200">
          <?php foreach (['titulo' => 'Titulo', 'tipo' => 'Tipo', 'status' => 'Status', 'posicao' => 'Posicao', 'expira_em' => 'Expira', 'updated_at' => 'Atualizado'] as $column => $label): ?>
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
      <tbody>
        <?php if ($items === []): ?>
          <tr>
            <td colspan="7" class="px-4 py-12 text-center text-slate-400">
              <div class="font-bold text-white mb-2">Nenhum link encontrado.</div>
              <div class="text-sm">Ajuste os filtros ou crie o primeiro link da bio.</div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($items as $item): ?>
            <?php
            $id = (int) ($item['id'] ?? 0);
            $status = (string) ($item['status'] ?? 'ativo');
            $badge = $statusBadge($status);
            $editUrl = url('/admin/editar-link?id=' . $id);
            $deleteUrl = url('/admin/excluir-link?id=' . $id);
            $destaque = (int) ($item['destaque'] ?? 0) === 1;
            $titulo = (string) ($item['titulo'] ?? '');
            $slug = (string) ($item['slug'] ?? '');
            $urlDestino = (string) ($item['url'] ?? '');
            $descricao = trim((string) ($item['descricao'] ?? ''));
            ?>
            <tr class="border-t border-slate-800/70 align-top">
              <td class="px-4 py-4">
                <div class="font-bold text-white"><?= htmlspecialchars($titulo !== '' ? $titulo : 'Sem titulo', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="text-xs text-slate-500 mt-1">#<?= $id ?><?php if ($slug !== ''): ?> - <?= htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?php endif; ?></div>
                <div class="text-xs text-cyan-300 mt-2 break-all"><?= htmlspecialchars($urlDestino, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <?php if ($descricao !== ''): ?><div class="text-xs text-slate-400 mt-2"><?= htmlspecialchars($descricao, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
              </td>
              <td class="px-4 py-4">
                <div class="flex flex-col gap-2">
                  <span class="admin-chip"><?= htmlspecialchars($typeLabel((string) ($item['tipo'] ?? 'conteudo')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                  <?php if ($destaque): ?><span class="admin-chip" style="border-color:rgba(34,211,238,.28);color:#67e8f9;">Destaque</span><?php endif; ?>
                </div>
              </td>
              <td class="px-4 py-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border <?= $badge['class'] ?>"><?= htmlspecialchars($badge['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              </td>
              <td class="px-4 py-4 text-slate-300"><?= number_format((int) ($item['posicao'] ?? 0), 0, ',', '.') ?></td>
              <td class="px-4 py-4 text-slate-300"><?= htmlspecialchars((string) (($item['expira_em'] ?? '') !== '' ? $item['expira_em'] : '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
              <td class="px-4 py-4 text-slate-400"><?= htmlspecialchars((string) (($item['updated_at'] ?? '') !== '' ? $item['updated_at'] : '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
              <td class="px-4 py-4">
                <div class="flex flex-col items-end gap-2">
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

  <?php if ((int) ($pagination['pages'] ?? 1) > 1): ?>
    <div class="flex flex-wrap items-center justify-between gap-4 pt-6 border-t border-slate-800/70 mt-6">
      <div class="text-sm text-slate-400">
        Mostrando pagina <span class="font-bold text-white"><?= (int) ($pagination['page'] ?? 1) ?></span> de <span class="font-bold text-white"><?= (int) ($pagination['pages'] ?? 1) ?></span>
      </div>

      <div class="flex flex-wrap gap-2">
        <?php
        $page = (int) ($pagination['page'] ?? 1);
        $pages = (int) ($pagination['pages'] ?? 1);
        ?>
        <a href="<?= htmlspecialchars($pageUrl(max(1, $page - 1)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary <?= $page <= 1 ? 'opacity-50 pointer-events-none' : '' ?>" data-admin-links-link>Anterior</a>
        <a href="<?= htmlspecialchars($pageUrl(min($pages, $page + 1)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary <?= $page >= $pages ? 'opacity-50 pointer-events-none' : '' ?>" data-admin-links-link>Proxima</a>
      </div>
    </div>
  <?php endif; ?>
</section>
