<?php
declare(strict_types=1);

$view = (($view ?? 'grade') === 'lista') ? 'lista' : 'grade';
$year = (int) ($year ?? date('Y'));
$month = (int) ($month ?? date('n'));
$daysInMonth = (int) ($days_in_month ?? 30);
$startWeekday = (int) ($start_weekday ?? 0);
$eventsByDay = is_array($events_by_day ?? null) ? $events_by_day : [];
$events = is_array($events ?? null) ? $events : [];
$instagramUnavailable = (bool) ($instagram_unavailable ?? false);
$targetEnvironment = (string) ($target_environment ?? 'local');
$prev = is_array($prev ?? null) ? $prev : ['ano' => $year, 'mes' => $month];
$next = is_array($next ?? null) ? $next : ['ano' => $year, 'mes' => $month];
$today = (int) ($today ?? 0);
$isCurrentMonth = (bool) ($is_current_month ?? false);

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

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

$nowTs = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->getTimestamp();
$isOverdue = static fn (array $event): bool => ($event['status'] ?? '') === 'agendado' && (int) ($event['ts'] ?? 0) < $nowTs;

// Blog mantem as cores de status; Instagram em rosa, com erro em vermelho e vencido com borda vermelha.
$eventClasses = static function (array $event) use ($statusClasses, $isOverdue): string {
    if (($event['kind'] ?? '') === 'instagram') {
        $base = ($event['status'] ?? '') === 'erro'
            ? 'border border-rose-500/60 bg-rose-500/20 text-rose-100'
            : 'border border-pink-400/40 bg-pink-500/15 text-pink-100';
    } else {
        $base = $statusClasses((string) ($event['status'] ?? ''));
    }

    return $base . ($isOverdue($event) ? ' ring-1 ring-rose-400' : '');
};

$eventIcon = static fn (array $event): string => ($event['kind'] ?? '') === 'instagram'
    ? '<i class="fa-brands fa-instagram" aria-hidden="true"></i>'
    : '<i class="fa-solid fa-newspaper" aria-hidden="true"></i>';

$statusLabel = static fn (array $event): string => (string) ($event['status'] ?? '') . ($isOverdue($event) ? ' - vencido' : '');
?>

<div class="max-w-7xl mx-auto px-4 py-6" data-admin-schedule-root>
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Agendamento de Posts</h1>
      <div class="admin-page-subtitle">Visualize quando cada post do blog e do Instagram foi ou sera publicado.</div>
    </div>
    <div class="admin-page-actions">
      <a href="<?= $buildUrl(['view' => 'grade']) ?>" class="admin-btn <?= $view === 'grade' ? 'admin-btn-primary' : 'admin-btn-secondary' ?>">Grade</a>
      <a href="<?= $buildUrl(['view' => 'lista']) ?>" class="admin-btn <?= $view === 'lista' ? 'admin-btn-primary' : 'admin-btn-secondary' ?>">Lista</a>
    </div>
  </div>

  <div class="admin-panel flex items-center justify-between gap-4 mb-4 flex-wrap">
    <a href="<?= $buildUrl(['ano' => $prev['ano'], 'mes' => $prev['mes']]) ?>" class="admin-btn admin-btn-secondary">&larr; Anterior</a>
    <form method="get" action="<?= function_exists('url') ? url('/admin/agendamento-posts') : '/admin/agendamento-posts' ?>" class="flex items-center gap-2">
      <input type="hidden" name="view" value="<?= $e($view) ?>">
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

  <div class="flex flex-wrap items-center gap-3 mb-4 text-xs text-slate-400">
    <span>Mostrar:</span>
    <button type="button" class="admin-btn admin-btn-secondary !px-3 !py-1.5" data-schedule-kind="blog" aria-pressed="true"><i class="fa-solid fa-newspaper" aria-hidden="true"></i> Blog</button>
    <button type="button" class="admin-btn admin-btn-secondary !px-3 !py-1.5 text-pink-200" data-schedule-kind="instagram" aria-pressed="true"><i class="fa-brands fa-instagram" aria-hidden="true"></i> Instagram</button>
    <span class="ml-auto">Blog: ambiente <?= $e(function_exists('environment_label') ? environment_label($targetEnvironment) : $targetEnvironment) ?> · Instagram: banco local · <span class="text-rose-300">borda vermelha = agendado vencido · fundo vermelho = erro</span></span>
  </div>

  <?php if ($instagramUnavailable): ?>
    <div class="admin-panel border border-amber-500/40 mb-4 text-xs text-amber-100">Nao foi possivel carregar os posts do Instagram agora. O calendario mostra so o blog.</div>
  <?php endif; ?>

  <?php if ($view === 'grade'): ?>
    <div class="admin-panel">
      <div class="grid grid-cols-7 gap-2 mb-2">
        <?php foreach ($weekdayLabels as $label): ?>
          <div class="text-xs font-bold text-slate-400 text-center"><?= $label ?></div>
        <?php endforeach; ?>
      </div>
      <div class="grid grid-cols-7 gap-2">
        <?php for ($i = 0; $i < $startWeekday; $i++): ?>
          <div class="min-h-[150px] rounded-xl bg-slate-900/20"></div>
        <?php endfor; ?>
        <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
          <?php $dayEvents = $eventsByDay[$day] ?? []; $isToday = $isCurrentMonth && $day === $today; ?>
          <div class="min-h-[150px] rounded-xl border <?= $isToday ? 'border-cyan-400/60 bg-cyan-500/5' : 'border-slate-800 bg-slate-900/40' ?> p-2 space-y-1" data-schedule-day>
            <div class="text-xs font-bold <?= $isToday ? 'text-cyan-300' : 'text-slate-400' ?>"><?= $day ?></div>
            <?php foreach ($dayEvents as $event): ?>
              <a href="<?= $e($event['url']) ?>" data-schedule-item="<?= $e($event['kind']) ?>" class="block rounded-lg px-2 py-1 text-xs <?= $eventClasses($event) ?>" title="<?= $e($event['titulo'] . ' (' . $statusLabel($event) . ')') ?>">
                <span class="font-bold"><?= $eventIcon($event) ?> <?= $e(date('H:i', (int) $event['ts'])) ?></span>
                <span class="block line-clamp-2 break-words"><?= $e($event['titulo']) ?></span>
                <?php if (($event['kind'] ?? '') === 'instagram' || $isOverdue($event)): ?>
                  <span class="block text-[10px] opacity-80"><?= $e($statusLabel($event)) ?></span>
                <?php endif; ?>
              </a>
            <?php endforeach; ?>
            <button type="button" class="hidden w-full rounded-lg px-2 py-0.5 text-[11px] text-cyan-300 hover:bg-slate-800" data-schedule-more></button>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="admin-panel space-y-4">
      <?php if ($events === []): ?>
        <div class="text-sm text-slate-400">Nenhum post com data neste mes.</div>
      <?php endif; ?>
      <?php ksort($eventsByDay); ?>
      <?php foreach ($eventsByDay as $day => $dayEvents): ?>
        <div data-schedule-day>
          <div class="text-sm font-bold text-slate-200 mb-2"><?= $formatDayLabel((int) $day) ?></div>
          <div class="space-y-2">
            <?php foreach ($dayEvents as $event): ?>
              <a href="<?= $e($event['url']) ?>" data-schedule-item="<?= $e($event['kind']) ?>" class="admin-panel flex items-center justify-between gap-3 hover:border-cyan-500/40">
                <div class="flex min-w-0 items-center gap-3">
                  <span class="text-xs text-slate-400 whitespace-nowrap"><?= $e(date('H:i', (int) $event['ts'])) ?></span>
                  <span class="text-sm <?= ($event['kind'] ?? '') === 'instagram' ? 'text-pink-200' : 'text-slate-300' ?>"><?= $eventIcon($event) ?></span>
                  <span class="text-sm text-white truncate"><?= $e($event['titulo']) ?></span>
                </div>
                <span class="rounded-lg px-2 py-1 text-xs whitespace-nowrap <?= $eventClasses($event) ?>"><?= $e($statusLabel($event)) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  var root = document.querySelector('[data-admin-schedule-root]');
  if (!root) { return; }
  var LIMIT = 4;
  var isGrid = <?= $view === 'grade' ? 'true' : 'false' ?>;
  var active = { blog: true, instagram: true };
  try {
    var saved = JSON.parse(window.localStorage.getItem('en-schedule-kinds') || 'null');
    if (saved && typeof saved === 'object') { active.blog = saved.blog !== false; active.instagram = saved.instagram !== false; }
  } catch (err) { /* armazenamento indisponivel: usa o padrao */ }

  function apply() {
    root.querySelectorAll('[data-schedule-kind]').forEach(function (btn) {
      var on = active[btn.getAttribute('data-schedule-kind')];
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      btn.classList.toggle('opacity-40', !on);
    });
    root.querySelectorAll('[data-schedule-day]').forEach(function (day) {
      var expanded = day.getAttribute('data-expanded') === '1';
      var visible = 0;
      var hiddenByLimit = 0;
      day.querySelectorAll('[data-schedule-item]').forEach(function (item) {
        var show = !!active[item.getAttribute('data-schedule-item')];
        if (show && isGrid && !expanded && visible >= LIMIT) { show = false; hiddenByLimit++; }
        if (show) { visible++; }
        item.classList.toggle('hidden', !show);
      });
      var more = day.querySelector('[data-schedule-more]');
      if (more) {
        more.classList.toggle('hidden', !(hiddenByLimit > 0 || expanded));
        more.textContent = expanded ? 'mostrar menos' : '+' + hiddenByLimit + ' mais';
      }
      if (!isGrid) { day.classList.toggle('hidden', visible === 0); }
    });
  }

  root.addEventListener('click', function (event) {
    var kindBtn = event.target.closest('[data-schedule-kind]');
    if (kindBtn) {
      var kind = kindBtn.getAttribute('data-schedule-kind');
      active[kind] = !active[kind];
      try { window.localStorage.setItem('en-schedule-kinds', JSON.stringify(active)); } catch (err) { /* ignora */ }
      apply();
      return;
    }
    var more = event.target.closest('[data-schedule-more]');
    if (more) {
      var day = more.closest('[data-schedule-day]');
      day.setAttribute('data-expanded', day.getAttribute('data-expanded') === '1' ? '0' : '1');
      apply();
    }
  });

  apply();
})();
</script>
