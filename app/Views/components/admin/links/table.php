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

$formatDateTime = static function (?string $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return $raw;
    }

    return date('d/m/Y H:i', $timestamp);
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

$expiryMeta = static function (?string $value) use ($formatDateTime): array {
    $raw = trim((string) $value);
    if ($raw === '') {
        return ['label' => 'Sem expiracao', 'note' => '', 'class' => 'text-slate-400'];
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return ['label' => $raw, 'note' => '', 'class' => 'text-slate-400'];
    }

    $today = strtotime(date('Y-m-d 00:00:00'));
    $days = (int) floor(($timestamp - $today) / 86400);

    if ($days < 0) {
        return ['label' => $formatDateTime($raw), 'note' => 'Expirado', 'class' => 'text-rose-300'];
    }

    if ($days === 0) {
        return ['label' => $formatDateTime($raw), 'note' => 'Expira hoje', 'class' => 'text-amber-300'];
    }

    if ($days <= 7) {
        return ['label' => $formatDateTime($raw), 'note' => 'Expira em ' . $days . ' dia(s)', 'class' => 'text-amber-200'];
    }

    return ['label' => $formatDateTime($raw), 'note' => 'Programado', 'class' => 'text-slate-300'];
};

$monitorMeta = static function (array $item) use ($formatDateTime): array {
    $checkedAt = trim((string) ($item['ultima_verificacao'] ?? ''));
    $httpCode = (int) ($item['codigo_http'] ?? 0);
    $finalUrl = trim((string) ($item['url_final'] ?? ''));
    $status = (string) ($item['status'] ?? 'ativo');
    $clickTotal = (int) ($item['click_total'] ?? 0);
    $clickToday = (int) ($item['click_today'] ?? 0);

    if ($checkedAt === '') {
        return [
            'label' => 'VERIFICAR',
            'class' => 'border-slate-500/30 text-slate-300 bg-slate-500/10',
            'timestamp' => 'Nenhuma checagem ainda',
            'detail' => 'Clique para validar o destino agora.',
            'click_total' => $clickTotal,
            'click_today' => $clickToday,
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
        'timestamp' => $formatDateTime($checkedAt),
        'detail' => $detail,
        'click_total' => $clickTotal,
        'click_today' => $clickToday,
    ];
};

$orderLabel = mb_strtoupper(str_replace('_', ' ', $sort)) . ' / ' . mb_strtoupper($dir);
?>

<section class="admin-panel links-table-panel">
  <div class="posts-table-head">
    <div>
      <h3 class="font-orbitron text-xl font-black text-white">Lista de links</h3>
      <div class="text-xs text-slate-400 mt-1"><?= number_format($total, 0, ',', '.') ?> link(s) encontrado(s)</div>
    </div>
    <span class="posts-table-order"><?= htmlspecialchars($orderLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
  </div>

  <?php if ($items === []): ?>
    <div class="text-center py-12 border-2 border-dashed border-gray-700 rounded-xl">
      <div class="text-3xl mb-4 font-orbitron font-black text-cyan-300">SEM</div>
      <h4 class="text-xl font-bold text-white mb-2">Nenhum link encontrado</h4>
      <div class="text-slate-400 text-sm">Ajuste os filtros ou crie um novo item para a Central Nerd.</div>
    </div>
  <?php else: ?>
    <div class="posts-table-wrap links-table-wrap">
      <table class="links-table">
        <colgroup>
          <col class="links-table-col-title">
          <col class="links-table-col-type">
          <col class="links-table-col-status">
          <col class="links-table-col-position">
          <col class="links-table-col-expire">
          <col class="links-table-col-monitor">
          <col class="links-table-col-actions">
        </colgroup>
        <thead class="posts-table-thead">
          <tr>
            <th class="posts-table-th posts-table-th-left">
              <a href="<?= htmlspecialchars($sortUrl('titulo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="posts-table-sort" data-admin-links-link>
                <span>Titulo</span>
                <span><?= $sortIcon('titulo') ?></span>
              </a>
            </th>
            <th class="posts-table-th posts-table-th-center">
              <a href="<?= htmlspecialchars($sortUrl('tipo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="posts-table-sort posts-table-sort-center" data-admin-links-link>
                <span>Tipo</span>
                <span><?= $sortIcon('tipo') ?></span>
              </a>
            </th>
            <th class="posts-table-th posts-table-th-center">
              <a href="<?= htmlspecialchars($sortUrl('status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="posts-table-sort posts-table-sort-center" data-admin-links-link>
                <span>Status</span>
                <span><?= $sortIcon('status') ?></span>
              </a>
            </th>
            <th class="posts-table-th posts-table-th-center">
              <a href="<?= htmlspecialchars($sortUrl('posicao'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="posts-table-sort posts-table-sort-center" data-admin-links-link>
                <span>Posicao</span>
                <span><?= $sortIcon('posicao') ?></span>
              </a>
            </th>
            <th class="posts-table-th posts-table-th-center">
              <a href="<?= htmlspecialchars($sortUrl('expira_em'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="posts-table-sort posts-table-sort-center" data-admin-links-link>
                <span>Expira</span>
                <span><?= $sortIcon('expira_em') ?></span>
              </a>
            </th>
            <th class="posts-table-th posts-table-th-center">
              <a href="<?= htmlspecialchars($sortUrl('updated_at'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="posts-table-sort posts-table-sort-center" data-admin-links-link>
                <span>Monitoramento</span>
                <span><?= $sortIcon('updated_at') ?></span>
              </a>
            </th>
            <th class="posts-table-th posts-table-th-center">Acoes</th>
          </tr>
        </thead>
        <tbody class="posts-table-body" data-admin-links-sortable>
          <?php foreach ($items as $item): ?>
            <?php
            $id = (int) ($item['id'] ?? 0);
            $status = (string) ($item['status'] ?? 'ativo');
            $statusMeta = $statusBadge($status);
            $tipoMeta = $typeBadge((string) ($item['tipo'] ?? 'conteudo'));
            $expiraMeta = $expiryMeta((string) ($item['expira_em'] ?? ''));
            $monitor = $monitorMeta($item);
            $editUrl = url('/admin/editar-link?id=' . $id);
            $deleteUrl = url('/admin/excluir-link?' . http_build_query(['id' => $id, 'return_to' => $currentUrl]));
            $openUrl = trim((string) ($item['url'] ?? ''));
            $destaque = (int) ($item['destaque'] ?? 0) === 1;
            $promocao = (int) ($item['promocao'] ?? 0) === 1;
            $titulo = trim((string) ($item['titulo'] ?? ''));
            $slug = trim((string) ($item['slug'] ?? ''));
            $descricao = trim((string) ($item['descricao'] ?? ''));
            $grupo = trim((string) ($item['subgrupo_publico'] ?? ''));
            $desconto = trim((string) ($item['desconto_percentual'] ?? ''));
            ?>
            <tr class="posts-table-row<?= $destaque ? ' is-highlight' : '' ?>" data-link-row-id="<?= $id ?>" draggable="true">
              <td class="posts-table-td links-table-title-cell">
                <div class="links-table-title-top">
                  <span class="links-table-drag" title="Arrastar para reordenar" data-link-drag-handle>
                    <i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
                  </span>
                  <div class="links-table-title-stack">
                    <a href="<?= htmlspecialchars($editUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="links-table-title-link">
                      <?= htmlspecialchars($titulo !== '' ? $titulo : 'Sem titulo', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </a>
                    <div class="links-table-subline">#<?= $id ?><?php if ($slug !== ''): ?> - <?= htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?php endif; ?></div>
                    <?php if ($openUrl !== ''): ?>
                      <a href="<?= htmlspecialchars($openUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="links-table-url">
                        <span class="links-table-url-text"><?= htmlspecialchars($openUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                      </a>
                    <?php endif; ?>
                    <?php if ($descricao !== ''): ?>
                      <div class="links-table-desc"><?= htmlspecialchars($descricao, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>

              <td class="posts-table-td posts-table-td-center">
                <div class="links-table-type-stack">
                  <span class="links-table-type-chip <?= htmlspecialchars($tipoMeta['class'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <?= htmlspecialchars($tipoMeta['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                  </span>
                  <?php if ((string) ($item['tipo'] ?? '') === 'produto' && $grupo !== ''): ?>
                    <span class="links-table-type-chip border-cyan-500/20 text-cyan-100 bg-cyan-500/10" title="<?= htmlspecialchars($grupo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      <?= htmlspecialchars($grupo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </span>
                  <?php endif; ?>
                  <?php if ($promocao): ?>
                    <span class="links-table-type-chip border-emerald-500/25 text-emerald-200 bg-emerald-500/10">Promocao</span>
                  <?php endif; ?>
                  <?php if ((string) ($item['tipo'] ?? '') === 'cupom' && $desconto !== ''): ?>
                    <span class="links-table-type-chip border-blue-500/25 text-blue-100 bg-blue-500/10">
                      <?= htmlspecialchars($desconto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> OFF
                    </span>
                  <?php endif; ?>
                  <form method="POST" action="<?= url('/admin/links/acao') ?>" data-admin-links-action class="links-table-type-form">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="toggle_destaque">
                    <button type="submit" class="links-table-type-toggle <?= $destaque ? 'border-fuchsia-500/25 text-fuchsia-100 bg-fuchsia-500/10' : 'border-slate-600/50 text-slate-300 bg-slate-800/40' ?>">
                      <?= $destaque ? 'Destaque' : 'Sem destaque' ?>
                    </button>
                  </form>
                </div>
              </td>

              <td class="posts-table-td posts-table-td-center">
                <form method="POST" action="<?= url('/admin/links/acao') ?>" data-admin-links-action class="links-table-status-form">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="id" value="<?= $id ?>">
                  <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="toggle_status">
                  <button type="submit" class="links-table-status-toggle <?= htmlspecialchars($statusMeta['class'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="<?= htmlspecialchars($statusMeta['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <?= htmlspecialchars($statusMeta['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                  </button>
                </form>
              </td>

              <td class="posts-table-td posts-table-td-center">
                <span class="links-table-position"><?= number_format((int) ($item['posicao'] ?? 0), 0, ',', '.') ?></span>
              </td>

              <td class="posts-table-td posts-table-td-center">
                <div class="links-table-expiry">
                  <div class="links-table-expiry-main <?= htmlspecialchars($expiraMeta['class'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <?= htmlspecialchars($expiraMeta['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                  </div>
                  <?php if ($expiraMeta['note'] !== ''): ?>
                    <div class="links-table-expiry-note"><?= htmlspecialchars($expiraMeta['note'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <?php endif; ?>
                </div>
              </td>

              <td class="posts-table-td posts-table-td-center">
                <div class="links-table-monitor">
                  <form method="POST" action="<?= url('/admin/links/acao') ?>" data-admin-links-action class="links-table-monitor-form">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="check_link">
                    <button type="submit" class="links-table-monitor-toggle <?= htmlspecialchars($monitor['class'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      <?= htmlspecialchars($monitor['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </button>
                  </form>
                  <div class="links-table-monitor-metrics">
                    <span class="links-table-monitor-chip links-table-monitor-chip-total"><?= number_format((int) ($monitor['click_total'] ?? 0), 0, ',', '.') ?> total</span>
                    <span class="links-table-monitor-chip links-table-monitor-chip-today"><?= number_format((int) ($monitor['click_today'] ?? 0), 0, ',', '.') ?> hoje</span>
                  </div>
                  <div class="links-table-monitor-meta"><?= htmlspecialchars($monitor['timestamp'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <div class="links-table-monitor-detail"><?= htmlspecialchars($monitor['detail'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </div>
              </td>

              <td class="posts-table-td posts-table-td-center">
                <div class="links-table-actions">
                  <a href="<?= htmlspecialchars($deleteUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="links-table-action posts-table-action posts-table-action-delete" aria-label="Excluir link" title="Excluir link">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>