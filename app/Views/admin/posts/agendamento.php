<?php
declare(strict_types=1);

$view = (($view ?? 'grade') === 'lista') ? 'lista' : 'grade';
$year = (int) ($year ?? date('Y'));
$month = (int) ($month ?? date('n'));
$daysInMonth = (int) ($days_in_month ?? 30);
$startWeekday = (int) ($start_weekday ?? 0);
$postsByDay = is_array($posts_by_day ?? null) ? $posts_by_day : [];
$posts = is_array($posts ?? null) ? $posts : [];
$prev = is_array($prev ?? null) ? $prev : ['ano' => $year, 'mes' => $month];
$next = is_array($next ?? null) ? $next : ['ano' => $year, 'mes' => $month];
$today = (int) ($today ?? 0);
$isCurrentMonth = (bool) ($is_current_month ?? false);

$buildUrl = static function (array $overrides = []) use ($view, $year, $month): string {
    $query = ['view' => $view, 'ano' => $year, 'mes' => $month];
    foreach ($overrides as $key => $value) {
        $query[$key] = $value;
    }
    $base = function_exists('url') ? url('/admin/agendamento-posts') : '/admin/agendamento-posts';

    return $base . '?' . http_build_query($query);
};

$weekdayLabels = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];
$monthNames = ['Janeiro', 'Fevereiro', 'Marco', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

$formatTime = static function ($value): string {
    if (!$value) {
        return '';
    }
    try {
        return (new DateTimeImmutable((string) $value))->format('H:i');
    } catch (Throwable) {
        return '';
    }
};

$formatDayLabel = static function (int $day) use ($year, $month): string {
    try {
        return (new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day)))->format('d/m/Y');
    } catch (Throwable) {
        return (string) $day;
    }
};

$statusClasses = static function (string $status): string {
    return match ($status) {
        'publicado' => 'status-badge status-publicado',
        'rascunho' => 'status-badge status-rascunho',
        'agendado' => 'status-badge status-agendado',
        default => 'status-badge',
    };
};

$now = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
$isOverdue = static function (array $post) use ($now): bool {
    if (($post['status'] ?? '') !== 'agendado') {
        return false;
    }
    try {
        return (new DateTimeImmutable((string) ($post['data_publicacao'] ?? ''))) < $now;
    } catch (Throwable) {
        return false;
    }
};

$editUrl = static function (array $post): string {
    return (function_exists('url') ? url('/admin/editar-post') : '/admin/editar-post') . '?id=' . (int) ($post['id'] ?? 0);
};
?>

<div class="max-w-7xl mx-auto px-4 py-6" data-admin-schedule-root>
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Agendamento de Posts</h1>
      <div class="admin-page-subtitle">Visualize quando cada post foi ou sera publicado.</div>
    </div>
    <div class="admin-page-actions">
      <a href="<?= $buildUrl(['view' => 'grade']) ?>" class="admin-btn <?= $view === 'grade' ? 'admin-btn-primary' : 'admin-btn-secondary' ?>">Grade</a>
      <a href="<?= $buildUrl(['view' => 'lista']) ?>" class="admin-btn <?= $view === 'lista' ? 'admin-btn-primary' : 'admin-btn-secondary' ?>">Lista</a>
    </div>
  </div>

  <div class="admin-panel flex items-center justify-between gap-4 mb-6 flex-wrap">
    <a href="<?= $buildUrl(['ano' => $prev['ano'], 'mes' => $prev['mes']]) ?>" class="admin-btn admin-btn-secondary">&larr; Anterior</a>
    <form method="get" action="<?= function_exists('url') ? url('/admin/agendamento-posts') : '/admin/agendamento-posts' ?>" class="flex items-center gap-2">
      <input type="hidden" name="view" value="<?= htmlspecialchars($view, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      <select name="mes" class="nerd-input px-3 py-2 rounded-xl" onchange="this.form.submit()">
        <?php foreach ($monthNames as $index => $label): ?>
          <option value="<?= $index + 1 ?>" <?= ($month === $index + 1) ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
      <select name="ano" class="nerd-input px-3 py-2 rounded-xl" onchange="this.form.submit()">
        <?php for ($y = (int) date('Y') - 1; $y <= (int) date('Y') + 2; $y++): ?>
          <option value="<?= $y ?>" <?= ($year === $y) ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </form>
    <a href="<?= $buildUrl(['ano' => $next['ano'], 'mes' => $next['mes']]) ?>" class="admin-btn admin-btn-secondary">Proximo &rarr;</a>
  </div>

  <?php if ($view === 'grade'): ?>
    <div class="admin-panel">
      <div class="grid grid-cols-7 gap-2 mb-2">
        <?php foreach ($weekdayLabels as $label): ?>
          <div class="text-xs font-bold text-slate-400 text-center"><?= $label ?></div>
        <?php endforeach; ?>
      </div>
      <div class="grid grid-cols-7 gap-2">
        <?php for ($i = 0; $i < $startWeekday; $i++): ?>
          <div class="min-h-[90px] rounded-xl bg-slate-900/20"></div>
        <?php endfor; ?>
        <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
          <?php $dayPosts = $postsByDay[$day] ?? []; $isToday = $isCurrentMonth && $day === $today; ?>
          <div class="min-h-[90px] rounded-xl border <?= $isToday ? 'border-cyan-400/60 bg-cyan-500/5' : 'border-slate-800 bg-slate-900/40' ?> p-2 space-y-1">
            <div class="text-xs font-bold <?= $isToday ? 'text-cyan-300' : 'text-slate-400' ?>"><?= $day ?></div>
            <?php foreach ($dayPosts as $post): ?>
              <a href="<?= $editUrl($post) ?>" class="block rounded-lg px-2 py-1 text-xs <?= $statusClasses((string) ($post['status'] ?? '')) ?> <?= $isOverdue($post) ? 'ring-1 ring-rose-400' : '' ?>" title="<?= htmlspecialchars((string) ($post['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <?= $formatTime($post['data_publicacao'] ?? null) ?> - <?= htmlspecialchars(mb_strimwidth((string) ($post['titulo'] ?? ''), 0, 28, '...'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?= $isOverdue($post) ? ' (vencido)' : '' ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="admin-panel space-y-4">
      <?php if ($posts === []): ?>
        <div class="text-sm text-slate-400">Nenhum post com data de publicacao neste mes.</div>
      <?php endif; ?>
      <?php
        $grouped = [];
        foreach ($posts as $post) {
            $day = 0;
            try {
                $day = (int) (new DateTimeImmutable((string) ($post['data_publicacao'] ?? '')))->format('j');
            } catch (Throwable) {
            }
            $grouped[$day][] = $post;
        }
        ksort($grouped);
      ?>
      <?php foreach ($grouped as $day => $dayPosts): ?>
        <div>
          <div class="text-sm font-bold text-slate-200 mb-2"><?= $formatDayLabel($day) ?></div>
          <div class="space-y-2">
            <?php foreach ($dayPosts as $post): ?>
              <a href="<?= $editUrl($post) ?>" class="admin-panel flex items-center justify-between gap-3 hover:border-cyan-500/40">
                <div class="flex items-center gap-3">
                  <span class="text-xs text-slate-400"><?= $formatTime($post['data_publicacao'] ?? null) ?></span>
                  <span class="text-sm text-white"><?= htmlspecialchars((string) ($post['titulo'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                </div>
                <span class="<?= $statusClasses((string) ($post['status'] ?? '')) ?>">
                  <?= htmlspecialchars((string) ($post['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?= $isOverdue($post) ? ' - vencido' : '' ?>
                </span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>