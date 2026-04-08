<?php
declare(strict_types=1);

use App\Support\Csrf;

$items = $items ?? [];
$filters = $filters ?? ['busca' => '', 'status' => ''];
$sort = (string) ($sort ?? 'data_cadastro');
$dir = (string) ($dir ?? 'desc');
$pagination = $pagination ?? ['total' => 0, 'page' => 1, 'per_page' => 10, 'pages' => 1];

$baseQuery = [
    'busca' => (string) ($filters['busca'] ?? ''),
    'status' => (string) ($filters['status'] ?? ''),
    'per_page' => (int) ($pagination['per_page'] ?? 10),
];
$currentUrl = url('/admin/newsletter?' . http_build_query(array_filter([
    'busca' => $baseQuery['busca'],
    'status' => $baseQuery['status'],
    'sort' => $sort,
    'dir' => $dir,
    'page' => (int) ($pagination['page'] ?? 1),
    'per_page' => $baseQuery['per_page'],
], static fn ($value): bool => $value !== '' && $value !== 0)));

$sortUrl = static function (string $column) use ($baseQuery, $sort, $dir): string {
    $nextDir = $sort === $column && $dir === 'asc' ? 'desc' : 'asc';
    return url('/admin/newsletter?' . http_build_query(array_filter([
        'busca' => $baseQuery['busca'],
        'status' => $baseQuery['status'],
        'sort' => $column,
        'dir' => $nextDir,
        'page' => 1,
        'per_page' => $baseQuery['per_page'],
    ], static fn ($value): bool => $value !== '' && $value !== 0)));
};

$pageUrl = static function (int $page) use ($baseQuery, $sort, $dir): string {
    return url('/admin/newsletter?' . http_build_query(array_filter([
        'busca' => $baseQuery['busca'],
        'status' => $baseQuery['status'],
        'sort' => $sort,
        'dir' => $dir,
        'page' => $page,
        'per_page' => $baseQuery['per_page'],
    ], static fn ($value): bool => $value !== '' && $value !== 0)));
};

$statusBadge = static function (string $status): array {
    return match ($status) {
        'ativo' => ['label' => 'ATIVO', 'class' => 'newsletter-table-status-toggle newsletter-table-status-toggle-active'],
        'inativo' => ['label' => 'INATIVO', 'class' => 'newsletter-table-status-toggle newsletter-table-status-toggle-inactive'],
        default => ['label' => 'DESINSCRITO', 'class' => 'newsletter-table-status-toggle newsletter-table-status-toggle-unsubscribed'],
    };
};

$nextStatus = static function (string $status): string {
    return match ($status) {
        'ativo' => 'inativo',
        'inativo' => 'ativo',
        default => 'ativo',
    };
};

$sortIcon = static function (string $column) use ($sort, $dir): string {
    if ($sort !== $column) {
        return '&#8596;';
    }

    return $dir === 'asc' ? '&#8593;' : '&#8595;';
};

$page = max(1, (int) ($pagination['page'] ?? 1));
$pages = max(1, (int) ($pagination['pages'] ?? 1));
$total = max(0, (int) ($pagination['total'] ?? count($items)));
?>

<section class="admin-panel newsletter-table-panel">
  <div class="posts-table-head">
    <div>
      <h3 class="font-orbitron text-xl font-black text-white">Lista de inscritos</h3>
      <div class="text-xs text-slate-400 mt-1"><?= number_format($total, 0, ',', '.') ?> inscrito(s) encontrado(s)</div>
    </div>
    <span class="posts-table-order"><?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> / <?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
  </div>

  <?php if ($items === []): ?>
    <div class="text-center py-12 border-2 border-dashed border-gray-700 rounded-xl">
      <div class="text-3xl mb-4 font-orbitron font-black text-cyan-300">SEM</div>
      <h4 class="text-xl font-bold text-white mb-2">Nenhum inscrito encontrado</h4>
      <div class="text-slate-400 text-sm">Ajuste os filtros para encontrar contatos especificos.</div>
    </div>
  <?php else: ?>
    <div class="posts-table-wrap newsletter-table-wrap">
      <table class="newsletter-table">
        <colgroup>
          <col class="newsletter-table-col-contact">
          <col class="newsletter-table-col-status">
          <col class="newsletter-table-col-date">
          <col class="newsletter-table-col-ip">
          <col class="newsletter-table-col-actions">
        </colgroup>
        <thead class="posts-table-thead">
          <tr>
            <th class="posts-table-th posts-table-th-left">
              <a href="<?= htmlspecialchars($sortUrl('nome'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="posts-table-sort posts-table-sort-left" data-admin-newsletter-link>
                <span>Contato</span>
                <span><?= $sortIcon('nome') ?></span>
              </a>
            </th>
            <th class="posts-table-th posts-table-th-center">
              <a href="<?= htmlspecialchars($sortUrl('status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="posts-table-sort posts-table-sort-center" data-admin-newsletter-link>
                <span>Status</span>
                <span><?= $sortIcon('status') ?></span>
              </a>
            </th>
            <th class="posts-table-th posts-table-th-center">
              <a href="<?= htmlspecialchars($sortUrl('data_cadastro'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="posts-table-sort posts-table-sort-center" data-admin-newsletter-link>
                <span>Cadastro</span>
                <span><?= $sortIcon('data_cadastro') ?></span>
              </a>
            </th>
            <th class="posts-table-th posts-table-th-center">
              <a href="<?= htmlspecialchars($sortUrl('ip'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="posts-table-sort posts-table-sort-center" data-admin-newsletter-link>
                <span>IP</span>
                <span><?= $sortIcon('ip') ?></span>
              </a>
            </th>
            <th class="posts-table-th posts-table-th-center">Acoes</th>
          </tr>
        </thead>
        <tbody class="newsletter-table-body">
          <?php foreach ($items as $item): ?>
            <?php
              $id = (int) ($item['id'] ?? 0);
              $status = (string) ($item['status'] ?? 'ativo');
              $badge = $statusBadge($status);
              $toggleStatus = $nextStatus($status);
              $deleteUrl = url('/admin/excluir-inscrito?' . http_build_query(['id' => $id, 'return_to' => $currentUrl]));
              $rawDate = (string) ($item['data_cadastro'] ?? '');
              $timestamp = strtotime($rawDate);
              $dateLabel = $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '-';
              $name = trim((string) ($item['nome'] ?? ''));
              $email = trim((string) ($item['email'] ?? ''));
              $emailLocal = $email !== '' ? explode('@', $email, 2)[0] : '';
              $displayName = $name !== '' ? $name : ($emailLocal !== '' ? $emailLocal : 'Sem nome');
              $ip = trim((string) ($item['ip'] ?? ''));
              $ipLabel = $ip !== '' ? $ip : '-';
              $canUnsubscribe = $status !== 'desinscreve';
            ?>
            <tr class="newsletter-table-row">
              <td class="newsletter-table-td newsletter-table-contact-cell">
                <div class="newsletter-table-contact-top">
                  <div class="newsletter-table-contact-title-wrap">
                    <span class="newsletter-table-contact-inline-id">#<?= $id ?></span>
                    <span class="newsletter-table-contact-inline-separator">-</span>
                    <div class="newsletter-table-contact-name"><?= htmlspecialchars($displayName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  </div>
                </div>
                <?php if ($email !== ''): ?>
                  <div class="newsletter-table-contact-email" title="<?= htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <?php else: ?>
                  <div class="newsletter-table-contact-email is-empty">Sem email registrado</div>
                <?php endif; ?>
              </td>

              <td class="newsletter-table-td newsletter-table-state-cell newsletter-table-td-center">
                <form method="POST" action="<?= url('/admin/newsletter/status') ?>" class="newsletter-table-inline-form newsletter-table-inline-form-status" data-admin-newsletter-action>
                  <?= Csrf::field() ?>
                  <input type="hidden" name="id" value="<?= $id ?>">
                  <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                  <button
                    type="submit"
                    name="status"
                    value="<?= htmlspecialchars($toggleStatus, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                    class="<?= htmlspecialchars((string) $badge['class'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                    aria-label="Alterar status do inscrito"
                    title="Alterar status do inscrito"
                  >
                    <?= htmlspecialchars((string) $badge['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                  </button>
                </form>
              </td>

              <td class="newsletter-table-td newsletter-table-date-cell newsletter-table-td-center">
                <div class="newsletter-table-date"><?= htmlspecialchars($dateLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </td>

              <td class="newsletter-table-td newsletter-table-ip-cell newsletter-table-td-center">
                <div class="newsletter-table-ip"><?= htmlspecialchars($ipLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </td>

              <td class="newsletter-table-td newsletter-table-actions-cell newsletter-table-td-center">
                <div class="posts-table-actions newsletter-table-actions">
                  <form method="POST" action="<?= url('/admin/newsletter/status') ?>" class="newsletter-table-inline-form" data-admin-newsletter-action>
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <button
                      type="submit"
                      name="status"
                      value="desinscreve"
                      class="posts-table-action posts-table-action-unsubscribe<?= !$canUnsubscribe ? ' is-disabled' : '' ?>"
                      aria-label="Desinscrever inscrito"
                      title="Desinscrever inscrito"
                      <?= !$canUnsubscribe ? 'disabled' : '' ?>
                    >
                      <i class="fa-solid fa-user-minus" aria-hidden="true"></i>
                    </button>
                  </form>
                  <a href="<?= htmlspecialchars($deleteUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="posts-table-action posts-table-action-delete" aria-label="Excluir inscrito" title="Excluir inscrito">
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

  <?php if ($pages > 1): ?>
    <div class="flex flex-wrap items-center justify-between gap-4 pt-6 border-t border-slate-800/70 mt-6">
      <div class="text-sm text-slate-400">
        Mostrando pagina <span class="font-bold text-white"><?= $page ?></span> de <span class="font-bold text-white"><?= $pages ?></span>
      </div>

      <div class="flex flex-wrap gap-2">
        <a href="<?= htmlspecialchars($pageUrl(max(1, $page - 1)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary <?= $page <= 1 ? 'opacity-50 pointer-events-none' : '' ?>" data-admin-newsletter-link>Anterior</a>
        <a href="<?= htmlspecialchars($pageUrl(min($pages, $page + 1)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary <?= $page >= $pages ? 'opacity-50 pointer-events-none' : '' ?>" data-admin-newsletter-link>Proxima</a>
      </div>
    </div>
  <?php endif; ?>
</section>