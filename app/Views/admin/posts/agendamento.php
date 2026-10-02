<?php
/**
 * -----------------------------------------------------------------------------
 * @file        app/Views/admin/posts/agendamento.php
 * @project     Estrategia Nerd
 * @purpose     Painel de Agendamento Unificado de Posts (Blog e Instagram)
 *              em Grade Mensal, Semanal e Lista, com miniaturas e ações rápidas
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

use App\Support\Csrf;

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

$weekdayLabels = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
$monthNames = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$currentMonthLabel = ($monthNames[$month - 1] ?? 'Mês') . ' de ' . $year;

$formatDayLabel = static function (int $day) use ($year, $month): string {
    try {
        return (new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day)))->format('d/m/Y');
    } catch (Throwable) {
        return (string) $day;
    }
};

$nowTs = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->getTimestamp();
$isOverdue = static fn (array $event): bool => ($event['status'] ?? '') === 'agendado' && (int) ($event['ts'] ?? 0) < $nowTs;

$statusLabel = static fn (array $event): string => (string) ($event['status'] ?? '') . ($isOverdue($event) ? ' - vencido' : '');

$resolveThumbUrl = static function (?string $path): string {
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:') || str_starts_with($path, 'blob:')) {
        return $path;
    }
    if (preg_match('#\.mp4(\?.*)?$#i', $path)) {
        $clean = preg_replace('#\?.*$#', '', $path);
        $jpg = preg_replace('#\.mp4$#i', '.jpg', $clean);
        if (is_string($jpg) && is_file(base_path('public/' . ltrim($jpg, '/\\')))) {
            return url('/' . ltrim($jpg, '/\\'));
        }
    }
    return url('/' . ltrim($path, '/\\'));
};

// Dias do mês anterior para preenchimento natural do calendário
$prevMonthNum = $month === 1 ? 12 : $month - 1;
$prevYearNum = $month === 1 ? $year - 1 : $year;
$daysInPrevMonth = (int) date('t', (int) mktime(0, 0, 0, $prevMonthNum, 1, $prevYearNum));
$prevMonthStartDay = $daysInPrevMonth - $startWeekday + 1;
?>

<div class="max-w-[1400px] mx-auto px-4 py-6" data-admin-schedule-root>

  <!-- 1. Header Principal Padronizado (Sem Ícone) -->
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Agendamento de Posts</h1>
      <div class="admin-page-subtitle">Gerencie e visualize seus posts do Instagram e do Blog em um só calendário.</div>
    </div>

    <!-- Ação de Criar Agendamento com Dropdown -->
    <div class="admin-page-actions">
      <div class="relative" data-new-schedule-wrap>
        <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 px-4 py-2.5 text-sm font-semibold text-white shadow-[0_0_20px_rgba(147,51,234,0.3)] transition-all cursor-pointer" data-new-schedule-btn>
          <i class="fa-solid fa-plus text-xs"></i>
          <span>Adicionar agendamento</span>
          <i class="fa-solid fa-chevron-down text-[10px] ml-0.5 opacity-80"></i>
        </button>

        <div class="hidden absolute right-0 top-full mt-2 w-52 rounded-2xl border border-slate-800 bg-slate-950/95 p-1.5 shadow-2xl backdrop-blur-xl z-50" data-new-schedule-menu>
          <a href="<?= url('/admin/criar-post') ?>" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold text-slate-200 hover:bg-slate-900 hover:text-white transition-colors">
            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-500/20 text-blue-400 border border-blue-500/30">
              <i class="fa-solid fa-file-lines text-xs"></i>
            </span>
            <div>
              <div class="font-bold">Post no Blog</div>
              <div class="text-[10px] text-slate-400 font-normal">Criar e agendar artigo</div>
            </div>
          </a>
          <a href="<?= url('/admin/instagram') ?>" class="flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold text-slate-200 hover:bg-slate-900 hover:text-white transition-colors">
            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-pink-500/20 text-pink-400 border border-pink-500/30">
              <i class="fa-brands fa-instagram text-xs"></i>
            </span>
            <div>
              <div class="font-bold">Post no Instagram</div>
              <div class="text-[10px] text-slate-400 font-normal">Reel, Carrossel ou Imagem</div>
            </div>
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- 2. Barra de Navegação Temporal e Controles -->
  <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-4 bg-slate-950/60 border border-slate-800/80 rounded-2xl p-3 backdrop-blur-sm">
    <!-- Lado Esquerdo: < > Mês/Ano e Hoje -->
    <div class="flex items-center gap-3 flex-wrap">
      <div class="flex items-center gap-1.5">
        <a href="<?= $buildUrl(['ano' => $prev['ano'], 'mes' => $prev['mes']]) ?>" class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-800 bg-slate-900/80 text-slate-300 hover:bg-slate-800 hover:text-white transition-colors" title="Mês anterior">
          <i class="fa-solid fa-chevron-left text-xs"></i>
        </a>
        <a href="<?= $buildUrl(['ano' => $next['ano'], 'mes' => $next['mes']]) ?>" class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-800 bg-slate-900/80 text-slate-300 hover:bg-slate-800 hover:text-white transition-colors" title="Próximo mês">
          <i class="fa-solid fa-chevron-right text-xs"></i>
        </a>
      </div>

      <div class="text-lg font-bold font-orbitron text-white min-w-[190px]">
        <?= $e($currentMonthLabel) ?>
      </div>

      <a href="<?= $buildUrl(['ano' => (int) date('Y'), 'mes' => (int) date('n')]) ?>" class="px-3 py-1.5 rounded-xl border border-slate-800 bg-slate-900/80 text-xs font-semibold text-slate-300 hover:bg-slate-800 hover:text-white transition-colors">
        Hoje
      </a>

      <!-- Seletor Rápido de Ambiente Alvo -->
      <form method="POST" action="<?= htmlspecialchars(url('/admin/ambiente-alvo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex items-center gap-1.5 ml-2">
        <?= Csrf::field() ?>
        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars((string) ($_SERVER['REQUEST_URI'] ?? url('/admin/agendamento-posts')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider hidden sm:inline">Blog:</span>
        <select name="target_environment" onchange="this.form.submit()" class="rounded-xl border border-slate-800 bg-slate-900/90 px-2.5 py-1 text-xs font-semibold text-cyan-300 hover:border-cyan-500/40 focus:outline-none cursor-pointer" title="Trocar ambiente do blog">
          <?php foreach (\App\Support\EnvironmentManager::allowedTargets() as $envOpt): ?>
            <option value="<?= htmlspecialchars($envOpt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= $envOpt === $targetEnvironment ? ' selected' : '' ?>>
              <?= htmlspecialchars(environment_label($envOpt), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <!-- Lado Direito: Modos de Visão, Filtro de Plataforma e Legenda -->
    <div class="flex items-center gap-3.5 flex-wrap">
      <!-- Segmented Control: Mês / Lista -->
      <div class="inline-flex p-1 rounded-xl bg-slate-900 border border-slate-800">
        <a href="<?= $buildUrl(['view' => 'grade']) ?>" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all <?= $view === 'grade' ? 'bg-gradient-to-r from-purple-600 to-indigo-600 text-white shadow' : 'text-slate-400 hover:text-slate-200' ?>">
          Mês
        </a>
        <a href="<?= $buildUrl(['view' => 'lista']) ?>" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all <?= $view === 'lista' ? 'bg-gradient-to-r from-purple-600 to-indigo-600 text-white shadow' : 'text-slate-400 hover:text-slate-200' ?>">
          Lista
        </a>
      </div>

      <!-- Filtros Interativos por Plataforma (Botões com Indicadores de Ícones) -->
      <div class="inline-flex items-center gap-1 p-1 rounded-xl bg-slate-900 border border-slate-800" data-platform-filter-group>
        <button type="button" data-schedule-filter="all" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all bg-slate-800 text-white shadow cursor-pointer" title="Mostrar todas as plataformas">
          <i class="fa-solid fa-layer-group text-slate-400 text-xs"></i>
          <span>Todas as plataformas</span>
        </button>
        <button type="button" data-schedule-filter="instagram" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all text-slate-400 hover:text-pink-300 hover:bg-slate-800/60 cursor-pointer" title="Filtrar posts do Instagram">
          <i class="fa-brands fa-instagram text-pink-400 text-xs"></i>
          <span>Instagram</span>
        </button>
        <button type="button" data-schedule-filter="blog" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all text-slate-400 hover:text-blue-300 hover:bg-slate-800/60 cursor-pointer" title="Filtrar posts do Blog">
          <i class="fa-regular fa-file-lines text-blue-400 text-xs"></i>
          <span>Blog</span>
        </button>
      </div>
    </div>
  </div>

  <?php if ($instagramUnavailable): ?>
    <div class="admin-panel border border-amber-500/40 mb-4 text-xs text-amber-100 flex items-center gap-2">
      <i class="fa-solid fa-triangle-exclamation text-amber-400"></i>
      <span>Não foi possível carregar os posts do Instagram no momento. Exibindo apenas a grade do blog.</span>
    </div>
  <?php endif; ?>

  <!-- 3. Visualização em Grade (Mês) -->
  <?php if ($view === 'grade'): ?>
    <div class="admin-panel !p-3">
      <!-- Dias da Semana -->
      <div class="grid grid-cols-7 gap-2 mb-2 border-b border-slate-800/80 pb-2">
        <?php foreach ($weekdayLabels as $label): ?>
          <div class="text-xs font-bold text-slate-400 text-center tracking-wider uppercase"><?= $label ?></div>
        <?php endforeach; ?>
      </div>

      <!-- Células do Calendário -->
      <div class="grid grid-cols-7 gap-2">
        <!-- Dias do mês anterior -->
        <?php for ($i = 0; $i < $startWeekday; $i++): ?>
          <?php $prevDayNumber = $prevMonthStartDay + $i; ?>
          <div class="schedule-day-cell rounded-xl border border-slate-800/40 bg-slate-950/30 p-2 opacity-30 select-none overflow-hidden" style="min-height: 200px; height: 200px;">
            <div class="text-xs font-bold text-slate-500 mb-1"><?= $prevDayNumber ?></div>
          </div>
        <?php endfor; ?>

        <!-- Dias do mês atual -->
        <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
          <?php
            $dayEvents = $eventsByDay[$day] ?? [];
            $isToday = $isCurrentMonth && $day === $today;
          ?>
          <div class="schedule-day-cell rounded-xl border <?= $isToday ? 'border-cyan-400/50 bg-cyan-950/10 shadow-[inset_0_0_15px_rgba(6,182,212,0.05)]' : 'border-slate-800/80 bg-slate-900/40' ?> p-2 flex flex-col justify-start transition-colors overflow-hidden" style="min-height: 200px; height: 200px;" data-schedule-day>
            <div class="flex items-center justify-between mb-1 shrink-0">
              <span class="text-xs font-bold <?= $isToday ? 'text-cyan-300 font-black' : 'text-slate-400' ?>"><?= $day ?></span>
              <?php if ($isToday): ?>
                <span class="text-[9px] font-black uppercase tracking-wider text-cyan-400 bg-cyan-500/10 px-1.5 py-0.5 rounded">Hoje</span>
              <?php endif; ?>
            </div>

            <!-- Cards de Eventos do Dia (Sem Barra de Rolagem) -->
            <div class="schedule-events-container flex-1 overflow-hidden" style="scrollbar-width: none;">
              <?php foreach ($dayEvents as $event): ?>
                <?php
                  $isIg = ($event['kind'] ?? '') === 'instagram';
                  $timeFormatted = date('H:i', (int) $event['ts']);
                  $thumbUrl = $resolveThumbUrl($event['thumb'] ?? '');
                  $isErr = ($event['status'] ?? '') === 'erro';
                  $overdue = $isOverdue($event);

                  if ($isIg) {
                      $cardClasses = $isErr
                          ? 'border-rose-500/60 bg-rose-950/40 text-rose-100 hover:border-rose-400'
                          : 'border-pink-500/40 bg-[#241228]/95 hover:border-pink-400/80 text-pink-100 shadow-[0_2px_10px_rgba(236,72,153,0.08)]';
                      $headerClass = 'text-pink-300';
                      $iconHtml = '<i class="fa-brands fa-instagram text-pink-400 text-xs"></i>';
                  } else {
                      $cardClasses = 'border-blue-500/40 bg-[#0f1d38]/95 hover:border-blue-400/80 text-blue-100 shadow-[0_2px_10px_rgba(59,130,246,0.08)]';
                      $headerClass = 'text-blue-300';
                      $iconHtml = '<i class="fa-regular fa-file-lines text-blue-400 text-xs"></i>';
                  }

                  if ($overdue) {
                      $cardClasses .= ' ring-1 ring-rose-400';
                  }
                ?>
                <div data-schedule-item="<?= $e($event['kind']) ?>" class="schedule-event-card group relative rounded-xl border p-1.5 sm:p-2 text-xs transition-all <?= $cardClasses ?>">
                  <div class="flex items-start justify-between gap-1.5">
                    <!-- Informações do Post (Horário e Título em até 3 linhas) -->
                    <div class="flex-1 min-w-0 pr-1">
                      <div class="flex items-center gap-1.5 font-bold text-[11px] mb-1 <?= $headerClass ?>">
                        <?= $iconHtml ?>
                        <span><?= $e($timeFormatted) ?></span>
                      </div>
                      <a href="<?= $e($event['url']) ?>" class="schedule-card-title block font-medium text-white/95 leading-tight hover:underline text-[11px]" title="<?= $e($event['titulo']) ?>">
                        <?= $e($event['titulo']) ?>
                      </a>
                      <?php if ($overdue || $isErr): ?>
                        <div class="mt-1 text-[9px] font-semibold text-rose-300">
                          <?= $isErr ? 'Erro no envio' : 'Agendado vencido' ?>
                        </div>
                      <?php endif; ?>
                    </div>

                    <!-- Miniatura de Capa e Menu de Ações -->
                    <div class="flex items-center gap-1 shrink-0">
                      <?php if ($thumbUrl !== ''): ?>
                        <a href="<?= $e($event['url']) ?>" class="block w-9 h-9 rounded-lg overflow-hidden border border-white/10 shrink-0 bg-slate-800" title="Ver post">
                          <img src="<?= $e($thumbUrl) ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy">
                        </a>
                      <?php endif; ?>

                      <!-- Menu de 3 pontinhos vertical -->
                      <div class="relative" data-action-menu-wrap>
                        <button type="button" class="w-5 h-7 flex items-center justify-center text-slate-400 hover:text-white rounded transition-colors" data-action-menu-btn title="Opções">
                          <i class="fa-solid fa-ellipsis-vertical text-[10px]"></i>
                        </button>
                        <div class="hidden absolute right-0 top-full mt-1 w-28 rounded-xl border border-slate-800 bg-slate-950 p-1 shadow-2xl backdrop-blur-md z-30" data-action-menu-dropdown>
                          <a href="<?= $e($event['url']) ?>" class="flex items-center gap-2 px-2.5 py-1.5 text-[11px] text-slate-300 hover:bg-slate-900 hover:text-white rounded-lg transition-colors">
                            <i class="fa-solid fa-pen text-[10px]"></i> Editar
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="hidden w-full rounded-lg px-2 py-0.5 text-[11px] text-cyan-300 hover:bg-slate-800" data-schedule-more></button>
          </div>
        <?php endfor; ?>

        <!-- Dias do próximo mês para completar a última semana -->
        <?php
          $totalCells = $startWeekday + $daysInMonth;
          $remainder = $totalCells % 7;
          $trailingDays = $remainder === 0 ? 0 : 7 - $remainder;
        ?>
        <?php for ($nextDay = 1; $nextDay <= $trailingDays; $nextDay++): ?>
          <div class="schedule-day-cell rounded-xl border border-slate-800/40 bg-slate-950/30 p-2 opacity-30 select-none overflow-hidden" style="min-height: 200px; height: 200px;">
            <div class="text-xs font-bold text-slate-500 mb-1"><?= $nextDay ?></div>
          </div>
        <?php endfor; ?>
      </div>
    </div>

  <!-- 4. Visualização em Lista -->
  <?php else: ?>
    <div class="admin-panel space-y-4 !p-4">
      <?php if ($events === []): ?>
        <div class="text-sm text-slate-400 py-8 text-center">Nenhum post agendado ou publicado neste mês.</div>
      <?php endif; ?>

      <?php ksort($eventsByDay); ?>
      <?php foreach ($eventsByDay as $day => $dayEvents): ?>
        <div class="border-b border-slate-800/80 pb-4 last:border-b-0" data-schedule-day>
          <div class="text-sm font-bold font-orbitron text-slate-200 mb-3 flex items-center gap-2">
            <i class="fa-regular fa-calendar text-cyan-400 text-xs"></i>
            <span><?= $formatDayLabel((int) $day) ?></span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php foreach ($dayEvents as $event): ?>
              <?php
                $isIg = ($event['kind'] ?? '') === 'instagram';
                $timeFormatted = date('H:i', (int) $event['ts']);
                $thumbUrl = $resolveThumbUrl($event['thumb'] ?? '');
                $isErr = ($event['status'] ?? '') === 'erro';
                $overdue = $isOverdue($event);

                if ($isIg) {
                    $cardClasses = $isErr
                        ? 'border-rose-500/60 bg-rose-950/40 text-rose-100 hover:border-rose-400'
                        : 'border-pink-500/40 bg-[#241228]/95 hover:border-pink-400/80 text-pink-100';
                    $headerClass = 'text-pink-300';
                    $iconHtml = '<i class="fa-brands fa-instagram text-pink-400 text-xs"></i>';
                } else {
                    $cardClasses = 'border-blue-500/40 bg-[#0f1d38]/95 hover:border-blue-400/80 text-blue-100';
                    $headerClass = 'text-blue-300';
                    $iconHtml = '<i class="fa-regular fa-file-lines text-blue-400 text-xs"></i>';
                }

                if ($overdue) {
                    $cardClasses .= ' ring-1 ring-rose-400';
                }
              ?>
              <div data-schedule-item="<?= $e($event['kind']) ?>" class="group rounded-xl border p-3 text-xs transition-all <?= $cardClasses ?> flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-1.5 font-bold text-xs mb-1.5 <?= $headerClass ?>">
                    <?= $iconHtml ?>
                    <span><?= $e($timeFormatted) ?></span>
                    <span class="text-[10px] font-normal opacity-70 ml-1">(<?= $e($event['kind']) ?>)</span>
                  </div>
                  <a href="<?= $e($event['url']) ?>" class="block font-medium text-white/95 line-clamp-2 leading-snug hover:underline text-xs" title="<?= $e($event['titulo']) ?>">
                    <?= $e($event['titulo']) ?>
                  </a>
                  <div class="mt-2 flex items-center gap-2 text-[10px]">
                    <span class="opacity-70"><?= $e($statusLabel($event)) ?></span>
                  </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                  <?php if ($thumbUrl !== ''): ?>
                    <a href="<?= $e($event['url']) ?>" class="block w-12 h-12 rounded-lg overflow-hidden border border-white/10 shrink-0 bg-slate-800">
                      <img src="<?= $e($thumbUrl) ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy">
                    </a>
                  <?php endif; ?>
                  <a href="<?= $e($event['url']) ?>" class="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition-colors" title="Editar">
                    <i class="fa-solid fa-pen text-xs"></i>
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<!-- 5. Script Interativo de Filtros e Dropdowns -->
<script>
(function () {
  var root = document.querySelector('[data-admin-schedule-root]');
  if (!root) { return; }

  // Filtro de Plataformas via Botões Interativos (Todas / Instagram / Blog)
  var filterButtons = root.querySelectorAll('[data-schedule-filter]');
  filterButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var selected = this.getAttribute('data-schedule-filter');

      // Atualiza estilo ativo nos botões com destaque da plataforma
      filterButtons.forEach(function (b) {
        var isCurrent = (b === btn);
        var bKind = b.getAttribute('data-schedule-filter');
        if (isCurrent) {
          if (bKind === 'instagram') {
            b.className = 'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-pink-950/70 border border-pink-500/50 text-pink-200 shadow-[0_0_12px_rgba(236,72,153,0.25)] cursor-pointer';
          } else if (bKind === 'blog') {
            b.className = 'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-blue-950/70 border border-blue-500/50 text-blue-200 shadow-[0_0_12px_rgba(59,130,246,0.25)] cursor-pointer';
          } else {
            b.className = 'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-slate-800 text-white shadow cursor-pointer';
          }
        } else {
          b.className = 'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all text-slate-400 hover:text-white hover:bg-slate-800/60 cursor-pointer';
        }
      });

      // Filtra os cards de evento em tempo real na grade e na lista
      root.querySelectorAll('[data-schedule-item]').forEach(function (item) {
        var kind = item.getAttribute('data-schedule-item');
        var match = (selected === 'all' || selected === kind);
        if (match) {
          item.classList.remove('hidden');
          item.style.removeProperty('display');
        } else {
          item.classList.add('hidden');
          item.style.setProperty('display', 'none', 'important');
        }
      });
    });
  });

  // Menu Dropdown "+ Adicionar agendamento"
  var newBtn = root.querySelector('[data-new-schedule-btn]');
  var newMenu = root.querySelector('[data-new-schedule-menu]');
  if (newBtn && newMenu) {
    newBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      newMenu.classList.toggle('hidden');
    });
  }

  // Menus de 3 pontinhos nas ações dos cards e fechamento global
  document.addEventListener('click', function (e) {
    var actionBtn = e.target.closest('[data-action-menu-btn]');
    var allDropdowns = root.querySelectorAll('[data-action-menu-dropdown]');

    if (actionBtn) {
      e.stopPropagation();
      var wrap = actionBtn.closest('[data-action-menu-wrap]');
      var dropdown = wrap ? wrap.querySelector('[data-action-menu-dropdown]') : null;

      allDropdowns.forEach(function (d) {
        if (d !== dropdown) { d.classList.add('hidden'); }
      });

      if (dropdown) {
        dropdown.classList.toggle('hidden');
      }
      if (newMenu) { newMenu.classList.add('hidden'); }
      return;
    }

    // Fechar menus ao clicar fora
    allDropdowns.forEach(function (d) { d.classList.add('hidden'); });
    if (newMenu && !newMenu.contains(e.target) && e.target !== newBtn && !newBtn.contains(e.target)) {
      newMenu.classList.add('hidden');
    }
  });
})();
</script>
