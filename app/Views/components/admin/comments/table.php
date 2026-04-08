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

$truncate = static function (string $value, int $limit = 180): string {
    $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? $value);
    if (mb_strlen($value) <= $limit) {
        return $value;
    }

    return rtrim(mb_substr($value, 0, $limit - 1)) . '...';
};

$cleanTitle = static function (?string $value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return 'Post removido';
    }

    $value = preg_replace('/\[\[(.*?)\]\]/u', '$1', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    $value = trim($value);

    return $value !== '' ? $value : 'Post removido';
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

$statusMeta = static function (string $status): array {
    return match ($status) {
        'aprovado' => ['label' => 'Aprovado', 'class' => 'comments-table-status-toggle comments-table-status-toggle-approved'],
        'reprovado' => ['label' => 'Reprovado', 'class' => 'comments-table-status-toggle comments-table-status-toggle-rejected'],
        'spam' => ['label' => 'Spam', 'class' => 'comments-table-status-toggle comments-table-status-toggle-spam'],
        default => ['label' => 'AG. moderacao', 'class' => 'comments-table-status-toggle comments-table-status-toggle-pending'],
    };
};

$moderationOptions = static function (string $status): array {
    return match ($status) {
        'aprovado' => [
            ['value' => 'reject', 'label' => 'Reprovar', 'class' => 'comments-table-status-action comments-table-status-action-reject'],
            ['value' => 'pending', 'label' => 'Pendente', 'class' => 'comments-table-status-action comments-table-status-action-pending'],
        ],
        'reprovado' => [
            ['value' => 'approve', 'label' => 'Aprovar', 'class' => 'comments-table-status-action comments-table-status-action-approve'],
            ['value' => 'pending', 'label' => 'Pendente', 'class' => 'comments-table-status-action comments-table-status-action-pending'],
        ],
        'spam' => [
            ['value' => 'approve', 'label' => 'Aprovar', 'class' => 'comments-table-status-action comments-table-status-action-approve'],
            ['value' => 'pending', 'label' => 'Pendente', 'class' => 'comments-table-status-action comments-table-status-action-pending'],
        ],
        default => [
            ['value' => 'approve', 'label' => 'Aprovar', 'class' => 'comments-table-status-action comments-table-status-action-approve'],
            ['value' => 'reject', 'label' => 'Reprovar', 'class' => 'comments-table-status-action comments-table-status-action-reject'],
        ],
    };
};

$currentUrl = $buildUrl();
$page = max(1, (int) ($pagination['page'] ?? 1));
$pages = max(1, (int) ($pagination['pages'] ?? 1));
$total = max(0, (int) ($pagination['total'] ?? count($items)));
$perPage = max(5, (int) ($pagination['per_page'] ?? 10));
$start = max(1, $page - 2);
$end = min($pages, $page + 2);
if (($end - $start) < 4) {
    $start = max(1, $end - 4);
    $end = min($pages, $start + 4);
}
$firstItem = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$lastItem = $total > 0 ? min($total, $page * $perPage) : 0;
?>

<section class="admin-panel comments-table-panel">
  <div class="posts-table-head">
    <div>
      <h3 class="font-orbitron text-xl font-black text-white">Lista de comentarios</h3>
      <div class="text-xs text-slate-400 mt-1"><?= number_format($total, 0, ',', '.') ?> comentario(s) encontrado(s)</div>
    </div>
    <span class="posts-table-order"><?= htmlspecialchars($sort, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> / <?= htmlspecialchars($dir, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
  </div>

  <?php if ($items === []): ?>
    <div class="text-center py-12 border-2 border-dashed border-gray-700 rounded-xl">
      <div class="text-3xl mb-4 font-orbitron font-black text-cyan-300">SEM</div>
      <h4 class="text-xl font-bold text-white mb-2">Nenhum comentario encontrado</h4>
      <div class="text-slate-400 text-sm">Ajuste os filtros para encontrar comentarios especificos.</div>
    </div>
  <?php else: ?>
    <div class="comments-table-wrap">
      <table class="comments-table">
        <colgroup>
          <col class="comments-table-col-main">
          <col class="comments-table-col-post">
          <col class="comments-table-col-state">
          <col class="comments-table-col-date">
          <col class="comments-table-col-actions">
        </colgroup>
        <thead class="posts-table-thead">
          <tr>
            <th class="posts-table-th posts-table-th-left"><a data-admin-comments-link class="posts-table-sort posts-table-sort-left" href="<?= htmlspecialchars($sortLink('autor'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Comentario <?= $sortIcon('autor') ?></a></th>
            <th class="posts-table-th posts-table-th-left"><a data-admin-comments-link class="posts-table-sort posts-table-sort-left" href="<?= htmlspecialchars($sortLink('post'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Post <?= $sortIcon('post') ?></a></th>
            <th class="posts-table-th posts-table-th-left"><a data-admin-comments-link class="posts-table-sort posts-table-sort-left" href="<?= htmlspecialchars($sortLink('status'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Estado <?= $sortIcon('status') ?></a></th>
            <th class="posts-table-th posts-table-th-center"><a data-admin-comments-link class="posts-table-sort posts-table-sort-center" href="<?= htmlspecialchars($sortLink('data'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Data <?= $sortIcon('data') ?></a></th>
            <th class="posts-table-th posts-table-th-center">Acoes</th>
          </tr>
        </thead>
        <tbody class="comments-table-body">
          <?php foreach ($items as $item): ?>
            <?php
              $id = (int) ($item['id'] ?? 0);
              $status = (string) ($item['status'] ?? 'pendente');
              $statusInfo = $statusMeta($status);
              $options = $moderationOptions($status);
              $hasAdminReply = (int) ($item['has_admin_reply'] ?? 0) === 1;
              $adminReplyCount = (int) ($item['admin_reply_count'] ?? 0);
              $isReply = (int) ($item['parent_id'] ?? 0) > 0;
              $postId = (int) ($item['post_id'] ?? 0);
              $postTitulo = $cleanTitle((string) ($item['post_titulo'] ?? 'Post removido'));
              $postSlug = trim((string) ($item['post_slug'] ?? ''));
              $deleteUrl = url('/admin/excluir-comentario?id=' . $id . '&return_to=' . rawurlencode($currentUrl));
              $replyUrl = url('/admin/responder-comentario?id=' . $id . '&return_to=' . rawurlencode($currentUrl));
              $editPostUrl = $postId > 0 ? url('/admin/editar-post?id=' . $postId) : '';
              $publicPostUrl = $postSlug !== '' ? url('/post/' . $postSlug) : '';
              $responseLabel = $hasAdminReply ? 'Respondido' : 'Sem resposta';
              $email = trim((string) ($item['email'] ?? ''));
              $showEmail = $email !== '' && !str_ends_with(strtolower($email), '@admin.estrategia-nerd.local');
              $authorName = trim((string) ($item['nome'] ?? 'Anonimo'));
              $authorName = $authorName !== '' ? $authorName : 'Anonimo';
              $parentAuthor = trim((string) ($item['parent_nome'] ?? 'Leitor'));
              $parentAuthor = $parentAuthor !== '' ? $parentAuthor : 'Leitor';
            ?>
            <tr class="comments-table-row">
              <td class="comments-table-td comments-table-main-cell">
                <div class="comments-table-main-top">
                  <div class="comments-table-author"><?= htmlspecialchars($authorName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <span class="comments-table-id">#<?= $id ?></span>
                </div>

                <?php if ($showEmail): ?>
                  <div class="comments-table-email"><?= htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <?php endif; ?>

                <div class="comments-table-message"><?= htmlspecialchars($truncate((string) ($item['comentario'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>

                <?php if ($isReply): ?>
                  <div class="comments-table-context">Em resposta a <strong><?= htmlspecialchars($parentAuthor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong> (#<?= (int) ($item['parent_id'] ?? 0) ?>)</div>
                <?php endif; ?>
              </td>

              <td class="comments-table-td comments-table-post-cell">
                <div class="comments-table-post-stack">
                  <?php if ($editPostUrl !== ''): ?>
                    <a class="comments-table-post-link" href="<?= htmlspecialchars($editPostUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars($postTitulo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
                  <?php else: ?>
                    <span class="comments-table-post-link is-disabled"><?= htmlspecialchars($postTitulo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                  <?php endif; ?>
                  <div class="comments-table-post-actions-inline">
                    <?php if ($publicPostUrl !== ''): ?>
                      <a class="comments-table-post-preview" href="<?= htmlspecialchars($publicPostUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" target="_blank" rel="noreferrer" title="Abrir post publico" aria-label="Abrir post publico">
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
              </td>

              <td class="comments-table-td comments-table-state-cell">
                <form method="POST" action="<?= url('/admin/moderar-comentario') ?>" class="comments-table-moderation-form">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="id" value="<?= $id ?>">
                  <input type="hidden" name="return_to" value="<?= htmlspecialchars($currentUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

                  <div class="comments-table-status-stack" data-comment-status-menu>
                    <button
                      type="button"
                      class="<?= htmlspecialchars((string) $statusInfo['class'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                      data-comment-status-toggle
                      aria-expanded="false"
                    >
                      <?= htmlspecialchars((string) $statusInfo['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </button>

                    <div class="comments-table-status-actions" data-comment-status-actions hidden>
                      <?php foreach ($options as $option): ?>
                        <button
                          type="submit"
                          name="action"
                          value="<?= htmlspecialchars((string) $option['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                          class="<?= htmlspecialchars((string) $option['class'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                        >
                          <?= htmlspecialchars((string) $option['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </button>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </form>

                <div class="comments-table-state-note"><?= htmlspecialchars($responseLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div><?php if ($hasAdminReply && $adminReplyCount > 1): ?><div class="comments-table-state-subnote"><?= $adminReplyCount ?> respostas da equipe</div><?php endif; ?>
              </td>

              <td class="comments-table-td comments-table-td-center comments-table-date-cell">
                <div class="comments-table-date"><?= htmlspecialchars($formatDate($item['data'] ?? null), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </td>

              <td class="comments-table-td comments-table-td-center">
                <div class="comments-table-actions">
                  <a
                    class="comments-table-action comments-table-action-reply"
                    href="<?= htmlspecialchars($replyUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                    aria-label="Responder comentario"
                    title="Responder comentario"
                  >
                    <i class="fa-solid fa-reply" aria-hidden="true"></i>
                  </a>
                  <a
                    class="comments-table-action comments-table-action-delete"
                    href="<?= htmlspecialchars($deleteUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                    aria-label="Excluir comentario"
                    title="Excluir comentario"
                  >
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