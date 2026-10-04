<?php
declare(strict_types=1);

use App\Support\Csrf;

$items = $items ?? [];
$filters = $filters ?? ['busca' => '', 'tipo' => '', 'promocao' => '', 'status' => '', 'destaque' => '', 'monitoramento' => ''];
$sort = (string) ($sort ?? 'posicao');
$dir = (string) ($dir ?? 'asc');
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];
$currentFeatured = is_array($current_featured ?? null) ? $current_featured : null;
$currentFeaturedId = (int) ($currentFeatured['id'] ?? 0);
$currentFeaturedTitle = trim((string) ($currentFeatured['titulo'] ?? ''));
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
      <div class="text-xs text-slate-400 mt-1"><?= number_format($total, 0, ',', '.') ?> link(s) cadastrado(s)</div>
    </div>
    <div class="links-table-head-actions">
      <form method="POST" action="<?= url('/admin/links/acao') ?>" data-admin-links-action data-check-all-form class="links-table-check-all-form">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="check_all">
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <button type="submit" class="links-btn-check-all" data-check-all-button title="Testar integridade HTTP de todos os links do catálogo agora sem recarregar a página">
          <i class="fa-solid fa-bolt-lightning text-amber-400" aria-hidden="true"></i>
          <span class="links-check-all-label">Verificar Todos</span>
        </button>
      </form>
      <span class="posts-table-order"><?= htmlspecialchars($orderLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
    </div>
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
          <col class="links-col-item">
          <col class="links-col-category">
          <col class="links-col-clicks">
          <col class="links-col-status">
          <col class="links-col-actions">
        </colgroup>
        <thead class="posts-table-thead">
          <tr>
            <th class="posts-table-th posts-table-th-left">
              <a href="<?= htmlspecialchars($sortUrl('titulo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="posts-table-sort" data-admin-links-link>
                <i class="fa-solid fa-cube text-cyan-400 text-xs mr-1" aria-hidden="true"></i>
                <span>Item / Link</span>
                <span><?= $sortIcon('titulo') ?></span>
              </a>
            </th>
            <th class="posts-table-th posts-table-th-left">
              <a href="<?= htmlspecialchars($sortUrl('categoria'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="posts-table-sort" data-admin-links-link>
                <i class="fa-solid fa-layer-group text-slate-400 text-xs mr-1" aria-hidden="true"></i>
                <span>Categoria</span>
                <span><?= $sortIcon('categoria') ?></span>
              </a>
            </th>
            <th class="posts-table-th posts-table-th-center">
              <a href="<?= htmlspecialchars($sortUrl('updated_at'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="posts-table-sort posts-table-sort-center" data-admin-links-link>
                <i class="fa-solid fa-arrow-pointer text-sky-400 text-xs mr-1" aria-hidden="true"></i>
                <span>Cliques</span>
                <span><?= $sortIcon('updated_at') ?></span>
              </a>
            </th>
            <th class="posts-table-th posts-table-th-center">
              <a href="<?= htmlspecialchars($sortUrl('status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="posts-table-sort posts-table-sort-center" data-admin-links-link>
                <i class="fa-solid fa-circle-dot text-emerald-400 text-xs mr-1" aria-hidden="true"></i>
                <span>Status</span>
                <span><?= $sortIcon('status') ?></span>
              </a>
            </th>
            <th class="posts-table-th posts-table-th-center">
              <i class="fa-solid fa-ellipsis text-slate-400 text-xs mr-1" aria-hidden="true"></i>
              <span>Ações</span>
            </th>
          </tr>
        </thead>
        <tbody class="posts-table-body" data-admin-links-sortable>
          <?php foreach ($items as $item): ?>
            <?php
            $id = (int) ($item['id'] ?? 0);
            $status = (string) ($item['status'] ?? 'ativo');
            $tipo = (string) ($item['tipo'] ?? 'conteudo');
            $monitor = $monitorMeta($item);
            $editUrl = url('/admin/editar-link?id=' . $id);
            $deleteUrl = url('/admin/excluir-link?' . http_build_query(['id' => $id, 'return_to' => $currentUrl]));
            $openUrl = trim((string) ($item['url'] ?? ''));
            $destaque = (int) ($item['destaque'] ?? 0) === 1;
            $promocao = (int) ($item['promocao'] ?? 0) === 1;
            $titulo = trim((string) ($item['titulo'] ?? ''));
            $slug = trim((string) ($item['slug'] ?? ''));
            $grupo = trim((string) ($item['subgrupo_publico'] ?? ''));
            $desconto = trim((string) ($item['desconto_percentual'] ?? ''));
            $imagem = trim((string) ($item['imagem'] ?? ''));
            $canonicalShortUrl = $slug !== '' ? url('/link/' . rawurlencode($slug)) : '';
            $needsFeaturedConfirm = !$destaque && $currentFeaturedId > 0 && $currentFeaturedId !== $id;
            $featuredConfirmMessage = $needsFeaturedConfirm
                ? 'Confirmar? Este item substituirá o destaque atual: ' . ($currentFeaturedTitle !== '' ? $currentFeaturedTitle : 'item atual') . '.'
                : '';

            // Cache-busting automático baseado no timestamp do arquivo
            $imageFile = $imagem !== '' ? dirname(__DIR__, 4) . '/public/' . ltrim($imagem, '/\\') : '';
            $imageVer = ($imageFile !== '' && is_file($imageFile)) ? (string) filemtime($imageFile) : '1';
            $imageUrl = $imagem !== '' ? url('/' . ltrim($imagem, '/\\')) . '?v=' . $imageVer : '';

            $iconFallback = match ($tipo) {
                'produto' => 'fa-box',
                'cupom' => 'fa-ticket',
                'rede_social' => 'fa-share-nodes',
                'servico' => 'fa-laptop-code',
                default => 'fa-link',
            };

            // Rótulo limpo de Categoria
            $categoriaLabel = match ($tipo) {
                'cupom' => ($desconto !== '' ? 'Cupom ' . $desconto : 'Cupom de Desconto'),
                'produto' => ($grupo !== '' ? $grupo : 'Produto'),
                'rede_social' => 'Rede Social',
                'servico' => 'Serviços',
                default => 'Conteúdo Geral',
            };

            $isBroken = $status === 'quebrado' || ($monitor['label'] ?? '') === 'REVISAR';
            ?>
            <tr class="posts-table-row<?= $destaque ? ' is-highlight' : '' ?>" data-link-row-id="<?= $id ?>" draggable="true">
              <!-- 1. Item / Link -->
              <td class="posts-table-td links-table-title-cell">
                <div class="links-table-title-top">
                  <span class="links-table-drag" title="Arrastar para reordenar" data-link-drag-handle>
                    <i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
                  </span>

                  <a href="<?= htmlspecialchars($editUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="links-table-thumb-wrap" title="Editar link">
                    <?php if ($imagem !== ''): ?>
                      <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="<?= htmlspecialchars($titulo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="links-table-thumb" width="46" height="46" loading="lazy">
                    <?php else: ?>
                      <div class="links-table-thumb-placeholder">
                        <i class="fa-solid <?= $iconFallback ?>" aria-hidden="true"></i>
                      </div>
                    <?php endif; ?>
                  </a>

                  <div class="links-table-title-stack">
                    <div class="links-table-title-row">
                      <a href="<?= htmlspecialchars($editUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="links-table-title-link">
                        <?= htmlspecialchars($titulo !== '' ? $titulo : 'Sem título', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                      </a>
                      <?php if ($destaque): ?>
                        <span class="links-table-star-badge" title="Destaque principal da Central Nerd"><i class="fa-solid fa-star"></i></span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>

              <!-- 2. Categoria Limpa -->
              <td class="posts-table-td links-table-category-cell">
                <span class="links-table-category-text" title="<?= htmlspecialchars($categoriaLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                  <?= htmlspecialchars($categoriaLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </span>
              </td>

              <!-- 3. Cliques / Desempenho -->
              <td class="posts-table-td posts-table-td-center">
                <div class="links-table-perf">
                  <span class="links-perf-total"><strong><?= number_format((int) ($monitor['click_total'] ?? 0), 0, ',', '.') ?></strong> <span class="links-perf-unit">cliques</span></span>
                  <span class="links-perf-today">+<?= number_format((int) ($monitor['click_today'] ?? 0), 0, ',', '.') ?> hoje</span>
                </div>
              </td>

              <!-- 4. Status com Dot Circular -->
              <td class="posts-table-td posts-table-td-center">
                <?php if ($isBroken): ?>
                  <span class="links-status-dot-btn is-broken" title="Alerta: URL externa falhou no último teste (<?= htmlspecialchars($monitor['detail'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>)">
                    <span class="links-status-dot is-broken"></span>
                    <span class="links-status-text">Revisar</span>
                  </span>
                <?php else: ?>
                  <form method="POST" action="<?= url('/admin/links/acao') ?>" data-admin-links-action class="links-status-dot-form">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <button type="submit" class="links-status-dot-btn <?= $status === 'ativo' ? 'is-active' : 'is-hidden' ?>" title="Status: <?= $status === 'ativo' ? 'Ativo (clique para ocultar)' : 'Oculto (clique para ativar)' ?>">
                      <span class="links-status-dot <?= $status === 'ativo' ? 'is-active' : 'is-hidden' ?>"></span>
                      <span class="links-status-text"><?= $status === 'ativo' ? 'Ativo' : 'Oculto' ?></span>
                    </button>
                  </form>
                <?php endif; ?>
              </td>

              <!-- 5. Ações Dropdown -->
              <td class="posts-table-td posts-table-td-center links-table-actions-cell">
                <div class="links-dropdown" data-links-dropdown>
                  <button type="button" class="links-dropdown-trigger" data-dropdown-trigger aria-expanded="false" title="Menu de ações">
                    <span>⋯ Ações</span>
                    <i class="fa-solid fa-chevron-down links-dropdown-chevron" aria-hidden="true"></i>
                  </button>

                  <div class="links-dropdown-menu" data-dropdown-menu>
                    <a href="<?= htmlspecialchars($editUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="links-dropdown-item">
                      <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                      <span>Editar link</span>
                    </a>

                    <?php if ($canonicalShortUrl !== ''): ?>
                      <button type="button" class="links-dropdown-item links-dropdown-item-copy" data-copy-link="<?= htmlspecialchars($canonicalShortUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" title="Copiar link de divulgação para a área de transferência">
                        <i class="fa-solid fa-copy text-cyan-400" aria-hidden="true"></i>
                        <span class="links-copy-info">
                          <span class="links-copy-text">Copiar Link</span>
                          <span class="links-copy-slug">/link/<?= htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                        </span>
                      </button>
                    <?php endif; ?>

                    <?php if ($openUrl !== ''): ?>
                      <a href="<?= htmlspecialchars($openUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="links-dropdown-item">
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                        <span>Abrir anúncio oficial</span>
                      </a>
                    <?php endif; ?>

                    <div class="links-dropdown-divider"></div>

                    <form method="POST" action="<?= url('/admin/links/acao') ?>" data-admin-links-action class="links-dropdown-form">
                      <?= Csrf::field() ?>
                      <input type="hidden" name="id" value="<?= $id ?>">
                      <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      <input type="hidden" name="action" value="toggle_destaque">
                      <button type="submit" class="links-dropdown-item <?= $destaque ? 'is-featured-active' : '' ?>" data-featured-toggle-button data-featured-next="<?= $destaque ? 'off' : 'on' ?>" data-featured-confirm-message="<?= htmlspecialchars($featuredConfirmMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                        <i class="fa-solid fa-star" aria-hidden="true"></i>
                        <span><?= $destaque ? 'Remover destaque' : 'Definir destaque' ?></span>
                      </button>
                    </form>

                    <form method="POST" action="<?= url('/admin/links/acao') ?>" data-admin-links-action class="links-dropdown-form">
                      <?= Csrf::field() ?>
                      <input type="hidden" name="id" value="<?= $id ?>">
                      <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      <input type="hidden" name="action" value="check_link">
                      <button type="submit" class="links-dropdown-item">
                        <i class="fa-solid fa-heart-pulse" aria-hidden="true"></i>
                        <span>Testar este link</span>
                      </button>
                    </form>

                    <div class="links-dropdown-divider"></div>

                    <a href="<?= htmlspecialchars($deleteUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="links-dropdown-item links-dropdown-item-danger" onclick="return confirm('Tem certeza que deseja excluir o link: &quot;<?= htmlspecialchars(addslashes($titulo !== '' ? $titulo : 'este link'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>&quot;?');">
                      <i class="fa-solid fa-trash" aria-hidden="true"></i>
                      <span>Excluir link</span>
                    </a>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
