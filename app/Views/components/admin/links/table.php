<?php
declare(strict_types=1);

use App\Support\Csrf;

$items = $items ?? [];
$filters = $filters ?? ['busca' => '', 'tipo' => '', 'status' => '', 'destaque' => '', 'monitoramento' => ''];
$sort = (string) ($sort ?? 'posicao');
$dir = (string) ($dir ?? 'asc');
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];

$baseQuery = [
    'busca' => (string) ($filters['busca'] ?? ''),
    'tipo' => (string) ($filters['tipo'] ?? ''),
    'status' => (string) ($filters['status'] ?? ''),
    'destaque' => (string) ($filters['destaque'] ?? ''),
    'monitoramento' => (string) ($filters['monitoramento'] ?? ''),
    'per_page' => (int) ($pagination['per_page'] ?? 10),
];

$currentUrl = url('/admin/links?' . http_build_query(array_filter([
    'busca' => $baseQuery['busca'],
    'tipo' => $baseQuery['tipo'],
    'status' => $baseQuery['status'],
    'destaque' => $baseQuery['destaque'],
    'monitoramento' => $baseQuery['monitoramento'],
    'sort' => $sort,
    'dir' => $dir,
    'page' => (int) ($pagination['page'] ?? 1),
    'per_page' => $baseQuery['per_page'],
], static fn ($value): bool => $value !== '' && $value !== 0)));

$sortUrl = static function (string $column) use ($baseQuery, $sort, $dir): string {
    $nextDir = $sort === $column && $dir === 'asc' ? 'desc' : 'asc';
    return url('/admin/links?' . http_build_query(array_filter([
        'busca' => $baseQuery['busca'],
        'tipo' => $baseQuery['tipo'],
        'status' => $baseQuery['status'],
        'destaque' => $baseQuery['destaque'],
        'monitoramento' => $baseQuery['monitoramento'],
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
        'monitoramento' => $baseQuery['monitoramento'],
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

$typeBadge = static function (string $tipo): array {
    return match ($tipo) {
        'afiliado' => ['label' => 'Afiliado', 'class' => 'border-slate-500/30 text-slate-100 bg-slate-500/10', 'icon' => 'fa-solid fa-coins', 'icon_class' => 'text-teal-300'],
        'oferta' => ['label' => 'Oferta', 'class' => 'border-slate-500/30 text-slate-100 bg-slate-500/10', 'icon' => 'fa-solid fa-bolt', 'icon_class' => 'text-pink-300'],
        'rede_social' => ['label' => 'Rede social', 'class' => 'border-slate-500/30 text-slate-100 bg-slate-500/10', 'icon' => 'fa-solid fa-share-nodes', 'icon_class' => 'text-indigo-300'],
        'servico' => ['label' => 'Servico', 'class' => 'border-slate-500/30 text-slate-100 bg-slate-500/10', 'icon' => 'fa-solid fa-briefcase', 'icon_class' => 'text-orange-300'],
        default => ['label' => 'Conteudo', 'class' => 'border-slate-500/30 text-slate-100 bg-slate-500/10', 'icon' => 'fa-solid fa-newspaper', 'icon_class' => 'text-slate-300'],
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
    $httpCode = (string) ($item['codigo_http'] ?? '');
    $finalUrl = trim((string) ($item['url_final'] ?? ''));

    if ($checkedAt === '') {
        return ['label' => 'Sem verificacao', 'detail' => 'Nenhuma checagem realizada ainda.'];
    }

    $detail = $httpCode !== '' ? 'HTTP ' . $httpCode : 'Sem codigo HTTP';
    if ($finalUrl !== '') {
        $detail .= ' - destino atualizado';
    }

    return ['label' => $checkedAt, 'detail' => $detail];
};
?>

<section class="admin-panel overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
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
              <div class="text-sm">Ajuste os filtros ou crie o primeiro link da bio.</div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($items as $item): ?>
            <?php
            $id = (int) ($item['id'] ?? 0);
            $status = (string) ($item['status'] ?? 'ativo');
            $badge = $statusBadge($status);
            $tipoMeta = $typeBadge((string) ($item['tipo'] ?? 'conteudo'));
            $expiraMeta = $expiryMeta((string) ($item['expira_em'] ?? ''));
            $monitor = $monitorMeta($item);
            $editUrl = url('/admin/editar-link?id=' . $id);
            $deleteUrl = url('/admin/excluir-link?id=' . $id);
            $destaque = (int) ($item['destaque'] ?? 0) === 1;
            $titulo = (string) ($item['titulo'] ?? '');
            $slug = (string) ($item['slug'] ?? '');
            $urlDestino = (string) ($item['url'] ?? '');
            $descricao = trim((string) ($item['descricao'] ?? ''));
            ?>
            <tr class="border-t border-slate-800/70 align-top" data-link-row-id="<?= $id ?>" draggable="true">
              <td class="px-4 py-4">
                <div class="flex items-start gap-3">
                  <span class="mt-1 inline-flex text-slate-500 hover:text-cyan-300 cursor-grab active:cursor-grabbing" title="Arrastar para reordenar" data-link-drag-handle>
                    <i class="fa-solid fa-grip-vertical"></i>
                  </span>
                  <div class="min-w-0">
                    <div class="font-bold text-white"><?= htmlspecialchars($titulo !== '' ? $titulo : 'Sem titulo', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="text-xs text-slate-500 mt-1">#<?= $id ?><?php if ($slug !== ''): ?> - <?= htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?php endif; ?></div>
                <div class="text-xs text-cyan-300 mt-2 break-all"><?= htmlspecialchars($urlDestino, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <?php if ($descricao !== ''): ?><div class="text-xs text-slate-400 mt-2"><?= htmlspecialchars($descricao, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php endif; ?>
                  </div>
                </div>
              </td>
              <td class="px-4 py-4">
                <div class="flex flex-col gap-2">
                  <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold border <?= $tipoMeta['class'] ?>">
                    <i class="<?= htmlspecialchars($tipoMeta['icon'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> <?= htmlspecialchars($tipoMeta['icon_class'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></i>
                    <span><?= htmlspecialchars($tipoMeta['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                  </span>
                  <?php if ($destaque): ?><span class="admin-chip" style="border-color:rgba(168,85,247,.30);color:#d8b4fe;background:rgba(168,85,247,.12);">Destaque</span><?php endif; ?>
                </div>
              </td>
              <td class="px-4 py-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border <?= $badge['class'] ?>"><?= htmlspecialchars($badge['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              </td>
              <td class="px-4 py-4 text-slate-300"><?= number_format((int) ($item['posicao'] ?? 0), 0, ',', '.') ?></td>
              <td class="px-4 py-4">
                <div class="<?= htmlspecialchars($expiraMeta['class'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> font-semibold"><?= htmlspecialchars($expiraMeta['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($expiraMeta['chip'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </td>
              <td class="px-4 py-4">
                <div class="text-slate-300"><?= htmlspecialchars($monitor['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($monitor['detail'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </td>
              <td class="px-4 py-4">
                <div class="flex flex-col items-end gap-2 min-w-[10rem]">
                  <div class="flex flex-wrap justify-end gap-2">
                    <a href="<?= htmlspecialchars($editUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary text-xs">Editar</a>
                    <details class="relative">
                      <summary class="admin-btn admin-btn-secondary text-xs list-none cursor-pointer select-none inline-flex items-center gap-2">
                        <span>Mais</span>
                        <i class="fa-solid fa-chevron-down text-[10px]"></i>
                      </summary>
                      <div class="mt-2 w-44 rounded-2xl border border-cyan-500/20 bg-slate-950/95 p-2 shadow-[0_18px_40px_rgba(2,12,35,0.45)] backdrop-blur">
                        <div class="flex flex-col gap-2">
                          <form method="POST" action="<?= url('/admin/links/acao') ?>" data-admin-links-action>
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="check_link">
                            <button type="submit" class="w-full rounded-xl border border-slate-700/70 px-3 py-2 text-left text-xs font-bold text-slate-200 transition hover:border-cyan-400/40 hover:bg-cyan-500/10">Verificar link</button>
                          </form>
                          <form method="POST" action="<?= url('/admin/links/acao') ?>" data-admin-links-action>
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="toggle_status">
                            <button type="submit" class="w-full rounded-xl border border-slate-700/70 px-3 py-2 text-left text-xs font-bold text-slate-200 transition hover:border-cyan-400/40 hover:bg-cyan-500/10"><?= $status === 'ativo' ? 'Ocultar link' : 'Ativar link' ?></button>
                          </form>
                          <form method="POST" action="<?= url('/admin/links/acao') ?>" data-admin-links-action>
                            <?= Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="toggle_destaque">
                            <button type="submit" class="w-full rounded-xl border border-slate-700/70 px-3 py-2 text-left text-xs font-bold text-slate-200 transition hover:border-cyan-400/40 hover:bg-cyan-500/10"><?= $destaque ? 'Tirar destaque' : 'Marcar destaque' ?></button>
                          </form>
                          <div class="grid grid-cols-2 gap-2">
                            <form method="POST" action="<?= url('/admin/links/acao') ?>" data-admin-links-action>
                              <?= Csrf::field() ?>
                              <input type="hidden" name="id" value="<?= $id ?>">
                              <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                              <input type="hidden" name="action" value="move_up">
                              <button type="submit" class="w-full rounded-xl border border-slate-700/70 px-3 py-2 text-center text-xs font-bold text-slate-200 transition hover:border-cyan-400/40 hover:bg-cyan-500/10" title="Mover para cima">&uarr; Subir</button>
                            </form>
                            <form method="POST" action="<?= url('/admin/links/acao') ?>" data-admin-links-action>
                              <?= Csrf::field() ?>
                              <input type="hidden" name="id" value="<?= $id ?>">
                              <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                              <input type="hidden" name="action" value="move_down">
                              <button type="submit" class="w-full rounded-xl border border-slate-700/70 px-3 py-2 text-center text-xs font-bold text-slate-200 transition hover:border-cyan-400/40 hover:bg-cyan-500/10" title="Mover para baixo">&darr; Descer</button>
                            </form>
                          </div>
                        </div>
                      </div>
                    </details>
                    <a href="<?= htmlspecialchars($deleteUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="px-3 py-2 rounded-lg text-xs font-bold border border-rose-500/30 text-rose-200 hover:bg-rose-500/10 transition">Excluir</a>
                  </div>
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
