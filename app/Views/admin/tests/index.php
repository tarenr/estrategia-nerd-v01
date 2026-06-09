<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\View;

$tests = is_array($tests ?? null) ? $tests : [];
$automatedTests = is_array($automated_tests ?? null) ? $automated_tests : [];
$automatedFlash = is_array($automated_flash ?? null) ? $automated_flash : null;
$latestByEnvironment = is_array($tests['latest_by_environment'] ?? null) ? $tests['latest_by_environment'] : [];
$history = is_array($tests['history'] ?? null) ? $tests['history'] : [];
$automatedLatestByEnvironment = is_array($automatedTests['latest_by_environment'] ?? null) ? $automatedTests['latest_by_environment'] : [];
$automatedLatestByLevel = is_array($automatedTests['latest_by_level'] ?? null) ? $automatedTests['latest_by_level'] : [];
$automatedHistory = is_array($automatedTests['history'] ?? null) ? $automatedTests['history'] : [];
$automatedReportHistory = is_array($automatedTests['report_history'] ?? null) ? $automatedTests['report_history'] : $automatedHistory;
$automatedReportSummary = is_array($automatedTests['report_summary'] ?? null) ? $automatedTests['report_summary'] : [];
$routineCatalog = is_array($automatedTests['routine_catalog'] ?? null) ? $automatedTests['routine_catalog'] : [];
$environments = [
    'local' => 'Local',
    'stage' => 'Stage',
    'production' => 'Producao',
];
$operationalEnvironments = [
    'local' => 'Local',
    'stage' => 'Stage',
];
$tabs = [
    'visao-geral' => 'Visao Geral',
    'safe' => 'Testes Safe',
    'rotinas' => 'Rotinas',
    'unitarios' => 'Unitarios',
    'relatorios' => 'Relatorios',
];
$activeTab = strtolower(trim((string) ($_GET['aba'] ?? 'visao-geral')));
if (!array_key_exists($activeTab, $tabs)) {
    $activeTab = 'visao-geral';
}

$formatDate = static function (?string $value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return 'Leitura pendente';
    }

    $timestamp = strtotime($value);
    return $timestamp !== false ? date('d/m/Y H:i:s', $timestamp) : $value;
};

$statusTone = static function (array $item): string {
    $summary = is_array($item['summary'] ?? null) ? $item['summary'] : [];
    return strtolower((string) ($item['status'] ?? '')) === 'ok' && (int) ($summary['fail'] ?? 0) === 0 ? 'success' : 'warning';
};

$automatedTone = static function (array $item): string {
    $status = strtolower((string) ($item['status'] ?? ''));
    return match ($status) {
        'ok' => 'success',
        'blocked' => 'warning',
        'fail' => 'danger',
        default => 'neutral',
    };
};

$tabUrl = static fn (string $tab): string => url('/admin/testes') . '?aba=' . rawurlencode($tab);
$reportFilterUrl = static function (array $params = []): string {
    $query = array_merge(['aba' => 'relatorios'], $params);
    return url('/admin/testes') . '?' . http_build_query($query);
};
?>
<section class="space-y-6">
  <?php View::component('admin/v2/page-header', [
      'eyebrow' => 'Operacional',
      'title' => 'Testes Automatizados',
      'description' => '',
  ]); ?>

  <div class="rounded-3xl border border-slate-800 bg-slate-900/70 p-2">
    <div class="grid gap-2 md:grid-cols-4">
      <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Modo</div>
        <div class="mt-2 text-sm font-black text-white">Escolha Manual</div>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Suite</div>
        <div class="mt-2 text-sm font-black text-white">Smoke + Operacional</div>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Operacional</div>
        <div class="mt-2 text-sm font-black text-white"><?= count($automatedHistory) ?> registro(s)</div>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
        <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Smoke</div>
        <div class="mt-2 text-sm font-black text-white"><?= count($history) ?> registro(s)</div>
      </div>
    </div>
  </div>

  <?php if ($automatedFlash !== null): ?>
    <?php $flashSuccess = (string) ($automatedFlash['type'] ?? '') === 'success'; ?>
    <div class="rounded-2xl border <?= $flashSuccess ? 'border-emerald-400/30 bg-emerald-500/10 text-emerald-200' : 'border-rose-400/30 bg-rose-500/10 text-rose-200' ?> px-5 py-4 text-sm font-semibold">
      <?= htmlspecialchars((string) ($automatedFlash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <nav class="grid gap-2 rounded-3xl border border-slate-800 bg-slate-900/70 p-2 md:grid-cols-5" aria-label="Abas de testes">
    <?php foreach ($tabs as $tabKey => $tabLabel): ?>
      <?php $isActive = $activeTab === $tabKey; ?>
      <a href="<?= htmlspecialchars($tabUrl($tabKey), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="rounded-2xl border px-4 py-3 text-left transition <?= $isActive ? 'border-cyan-400/60 bg-cyan-500/10 text-white' : 'border-slate-800 bg-slate-950/60 text-slate-400 hover:border-cyan-400/40 hover:text-slate-100' ?>">
        <span class="block font-orbitron text-[10px] font-black uppercase tracking-[0.18em]"><?= htmlspecialchars($tabLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php if ($activeTab === 'visao-geral'): ?>
    <section class="space-y-5">
      <div class="grid gap-4 lg:grid-cols-2">
        <?php foreach ($operationalEnvironments as $key => $label): ?>
          <?php
            $latest = is_array($automatedLatestByEnvironment[$key] ?? null) ? $automatedLatestByEnvironment[$key] : [];
            $tone = $latest !== [] ? $automatedTone($latest) : 'neutral';
            $blocks = is_array($latest['security_blocks'] ?? null) ? $latest['security_blocks'] : [];
            $selected = array_values(array_map('strval', (array) ($latest['selected_routines'] ?? [])));
          ?>
          <article class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-cyan-300/70"><?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="mt-2 break-all text-lg font-black text-white"><?= htmlspecialchars((string) ($latest['id'] ?? 'Leitura pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>
              <?php View::component('admin/v2/status-badge', [
                  'label' => $latest !== [] ? strtoupper((string) ($latest['status'] ?? 'pendente')) : 'Pendente',
                  'tone' => $tone,
              ]); ?>
            </div>
            <dl class="mt-5 grid gap-3 text-sm">
              <div class="flex items-center justify-between gap-3 border-t border-slate-800 pt-3">
                <dt class="text-slate-500">Nivel</dt>
                <dd class="text-right font-semibold text-slate-200"><?= htmlspecialchars((string) ($latest['level'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
              </div>
              <div class="flex items-center justify-between gap-3 border-t border-slate-800 pt-3">
                <dt class="text-slate-500">Rotinas</dt>
                <dd class="text-right font-semibold text-slate-200"><?= htmlspecialchars($selected !== [] ? implode(', ', $selected) : '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
              </div>
              <div class="flex items-center justify-between gap-3 border-t border-slate-800 pt-3">
                <dt class="text-slate-500">Execucao</dt>
                <dd class="text-right font-semibold text-slate-200"><?= htmlspecialchars($formatDate((string) ($latest['finished_at'] ?? $latest['started_at'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
              </div>
              <div class="grid grid-cols-4 gap-2 border-t border-slate-800 pt-3 text-center">
                <div><span class="block text-slate-500">OK</span><strong class="text-white"><?= (int) ($latest['tests_ok'] ?? 0) ?></strong></div>
                <div><span class="block text-slate-500">Falhas</span><strong class="text-white"><?= (int) ($latest['tests_failed'] ?? 0) ?></strong></div>
                <div><span class="block text-slate-500">Pulados</span><strong class="text-white"><?= (int) ($latest['tests_skipped'] ?? 0) ?></strong></div>
                <div><span class="block text-slate-500">Bloqueios</span><strong class="text-white"><?= count($blocks) ?></strong></div>
              </div>
            </dl>
          </article>
        <?php endforeach; ?>
      </div>

      <div class="grid gap-4 lg:grid-cols-3">
        <?php foreach ($environments as $key => $label): ?>
          <?php
            $latest = is_array($latestByEnvironment[$key] ?? null) ? $latestByEnvironment[$key] : [];
            $summary = is_array($latest['summary'] ?? null) ? $latest['summary'] : [];
            $tone = $latest !== [] ? $statusTone($latest) : 'neutral';
          ?>
          <article class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-cyan-300/70">Smoke <?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="mt-2 break-all text-base font-black text-white"><?= htmlspecialchars((string) ($latest['id'] ?? 'Leitura pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>
              <?php View::component('admin/v2/status-badge', [
                  'label' => $latest !== [] ? strtoupper((string) ($latest['status'] ?? 'pendente')) : 'Pendente',
                  'tone' => $tone,
              ]); ?>
            </div>
            <dl class="mt-5 grid gap-3 text-sm">
              <div class="flex items-center justify-between gap-3 border-t border-slate-800 pt-3">
                <dt class="text-slate-500">Ultima execucao</dt>
                <dd class="text-right font-semibold text-slate-200"><?= htmlspecialchars($formatDate((string) ($latest['finished_at'] ?? $latest['started_at'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></dd>
              </div>
              <div class="grid grid-cols-3 gap-2 border-t border-slate-800 pt-3 text-center">
                <div><span class="block text-slate-500">OK</span><strong class="text-white"><?= (int) ($summary['ok'] ?? 0) ?></strong></div>
                <div><span class="block text-slate-500">Falhas</span><strong class="text-white"><?= (int) ($summary['fail'] ?? 0) ?></strong></div>
                <div><span class="block text-slate-500">Total</span><strong class="text-white"><?= (int) ($summary['total'] ?? 0) ?></strong></div>
              </div>
            </dl>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php elseif ($activeTab === 'safe'): ?>
    <section class="rounded-3xl border border-cyan-400/20 bg-slate-900/80 p-6">
      <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.2em] text-cyan-300/70">Validacao rapida</div>
          <h2 class="mt-2 font-orbitron text-lg font-black text-white">Testes Safe</h2>
        </div>
        <?php View::component('admin/v2/status-badge', ['label' => 'Leitura + Login', 'tone' => 'neutral']); ?>
      </div>

      <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <?php foreach ($operationalEnvironments as $environmentKey => $environmentLabel): ?>
          <form method="POST" action="<?= htmlspecialchars(url('/admin/testes'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="rounded-2xl border border-slate-800 bg-slate-950/70 p-5">
            <?= Csrf::field() ?>
            <input type="hidden" name="environment" value="<?= htmlspecialchars($environmentKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Ambiente</div>
            <h3 class="mt-2 text-base font-black text-white"><?= htmlspecialchars($environmentLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
            <div class="mt-5 grid grid-cols-2 gap-2 text-sm">
              <div class="rounded-xl border border-slate-800 bg-slate-900/70 px-3 py-2 text-slate-300">Rotas publicas</div>
              <div class="rounded-xl border border-slate-800 bg-slate-900/70 px-3 py-2 text-slate-300">Login/logout</div>
              <div class="rounded-xl border border-slate-800 bg-slate-900/70 px-3 py-2 text-slate-300">Assets</div>
              <div class="rounded-xl border border-slate-800 bg-slate-900/70 px-3 py-2 text-slate-300">Paginas admin</div>
            </div>
            <button type="submit" name="level" value="safe" class="mt-5 w-full rounded-xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-2 text-sm font-bold text-cyan-100 transition hover:border-cyan-300">Executar safe <?= htmlspecialchars(strtolower($environmentLabel), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
          </form>
        <?php endforeach; ?>
      </div>
    </section>
  <?php elseif ($activeTab === 'rotinas'): ?>
    <section class="rounded-3xl border border-amber-400/20 bg-slate-900/80 p-6">
      <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.2em] text-amber-300/70">Execucao controlada</div>
          <h2 class="mt-2 font-orbitron text-lg font-black text-white">Rotinas operacionais</h2>
        </div>
        <?php View::component('admin/v2/status-badge', ['label' => 'Selecionavel', 'tone' => 'warning']); ?>
      </div>

      <div class="mt-6 grid gap-4 xl:grid-cols-2">
        <?php foreach ($operationalEnvironments as $environmentKey => $environmentLabel): ?>
          <?php $catalog = is_array($routineCatalog[$environmentKey] ?? null) ? $routineCatalog[$environmentKey] : []; ?>
          <form method="POST" action="<?= htmlspecialchars(url('/admin/testes'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="rounded-2xl border border-slate-800 bg-slate-950/70 p-5">
            <?= Csrf::field() ?>
            <input type="hidden" name="environment" value="<?= htmlspecialchars($environmentKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Ambiente</div>
            <h3 class="mt-2 text-base font-black text-white"><?= htmlspecialchars($environmentLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h3>
            <div class="mt-5 grid gap-3">
              <?php foreach ($catalog as $routine): ?>
                <?php $routine = is_array($routine) ? $routine : []; ?>
                <label class="flex gap-3 rounded-xl border border-slate-800 bg-slate-900/70 p-3 text-sm text-slate-300">
                  <input type="checkbox" name="routines[]" value="<?= htmlspecialchars((string) ($routine['key'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="mt-1 h-4 w-4 rounded border-slate-600 bg-slate-950 text-cyan-400 focus:ring-cyan-400" <?= (string) ($routine['key'] ?? '') === 'safe' ? 'checked' : '' ?>>
                  <span>
                    <span class="block font-bold text-white"><?= htmlspecialchars((string) ($routine['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    <span class="mt-1 block text-xs leading-5 text-slate-500"><?= htmlspecialchars((string) ($routine['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
            <button type="submit" name="level" value="routine" class="mt-4 w-full rounded-xl border border-amber-400/40 bg-amber-500/10 px-4 py-2 text-sm font-bold text-amber-100 transition hover:border-amber-300">Executar rotinas selecionadas</button>
          </form>
        <?php endforeach; ?>
      </div>
    </section>
  <?php elseif ($activeTab === 'unitarios'): ?>
    <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <?php
        $unitLatest = is_array($automatedLatestByLevel['local']['unit'] ?? null) ? $automatedLatestByLevel['local']['unit'] : [];
        $unitTone = $unitLatest !== [] ? $automatedTone($unitLatest) : 'neutral';
      ?>
      <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.2em] text-cyan-300/70">Codigo isolado</div>
          <h2 class="mt-2 font-orbitron text-lg font-black text-white">Testes unitarios</h2>
        </div>
        <?php View::component('admin/v2/status-badge', ['label' => $unitLatest !== [] ? strtoupper((string) ($unitLatest['status'] ?? 'pendente')) : 'Local', 'tone' => $unitTone]); ?>
      </div>

      <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <form method="POST" action="<?= htmlspecialchars(url('/admin/testes'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="rounded-2xl border border-cyan-400/20 bg-cyan-500/10 p-5">
          <?= Csrf::field() ?>
          <input type="hidden" name="environment" value="local">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-cyan-300/80">Execucao</div>
          <div class="mt-2 text-sm font-semibold text-cyan-50">Contratos puros, sem HTTP, banco, FTP ou servicos externos.</div>
          <button type="submit" name="level" value="unit" class="mt-5 w-full rounded-xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-2 text-sm font-bold text-cyan-100 transition hover:border-cyan-300">Executar unitarios</button>
        </form>
        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-5">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Services</div>
          <div class="mt-2 text-sm font-semibold text-slate-300">OperationalTestService e contratos de resultado.</div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-5">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Ultimo resultado</div>
          <div class="mt-2 text-sm font-semibold text-slate-300"><?= htmlspecialchars((string) ($unitLatest['id'] ?? 'Leitura pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="mt-4 grid grid-cols-3 gap-2 text-center text-sm">
            <div><span class="block text-slate-500">OK</span><strong class="text-white"><?= (int) ($unitLatest['tests_ok'] ?? 0) ?></strong></div>
            <div><span class="block text-slate-500">Falhas</span><strong class="text-white"><?= (int) ($unitLatest['tests_failed'] ?? 0) ?></strong></div>
            <div><span class="block text-slate-500">Total</span><strong class="text-white"><?= (int) ($unitLatest['tests_executed'] ?? 0) ?></strong></div>
          </div>
        </div>
      </div>
    </section>
  <?php else: ?>
    <?php
      $reportEnvironment = strtolower(trim((string) ($_GET['ambiente'] ?? 'todos')));
      $reportLevel = strtolower(trim((string) ($_GET['nivel'] ?? 'todos')));
      $reportStatus = strtolower(trim((string) ($_GET['status'] ?? 'todos')));
      $selectedReportId = trim((string) ($_GET['relatorio'] ?? ''));
      $filteredReports = array_values(array_filter($automatedReportHistory, static function (array $item) use ($reportEnvironment, $reportLevel, $reportStatus): bool {
          if ($reportEnvironment !== 'todos' && strtolower((string) ($item['environment'] ?? '')) !== $reportEnvironment) {
              return false;
          }
          if ($reportLevel !== 'todos' && strtolower((string) ($item['level'] ?? '')) !== $reportLevel) {
              return false;
          }
          if ($reportStatus !== 'todos' && strtolower((string) ($item['status'] ?? '')) !== $reportStatus) {
              return false;
          }
          return true;
      }));
      $selectedReport = [];
      foreach ($automatedReportHistory as $candidate) {
          $candidate = is_array($candidate) ? $candidate : [];
          if ($selectedReportId !== '' && (string) ($candidate['id'] ?? '') === $selectedReportId) {
              $selectedReport = $candidate;
              break;
          }
      }
      if ($selectedReport === []) {
          $selectedReport = is_array($filteredReports[0] ?? null) ? $filteredReports[0] : [];
      }
      $selectedTests = is_array($selectedReport['tests'] ?? null) ? $selectedReport['tests'] : [];
      $selectedBlocks = is_array($selectedReport['security_blocks'] ?? null) ? $selectedReport['security_blocks'] : [];
      $selectedCreated = is_array($selectedReport['created_data'] ?? null) ? $selectedReport['created_data'] : [];
      $selectedRemoved = is_array($selectedReport['removed_data'] ?? null) ? $selectedReport['removed_data'] : [];
      $selectedResidues = is_array($selectedReport['pending_residues'] ?? null) ? $selectedReport['pending_residues'] : [];
      $testsByGroup = [];
      foreach ($selectedTests as $test) {
          $test = is_array($test) ? $test : [];
          $group = (string) ($test['group'] ?? 'geral');
          $testsByGroup[$group][] = $test;
      }
    ?>
    <section class="space-y-6">
      <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Relatorios</div>
          <div class="mt-2 text-xl font-black text-white"><?= (int) ($automatedReportSummary['total'] ?? 0) ?></div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">OK acumulado</div>
          <div class="mt-2 text-xl font-black text-emerald-200"><?= (int) ($automatedReportSummary['tests_ok'] ?? 0) ?></div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Falhas</div>
          <div class="mt-2 text-xl font-black text-rose-200"><?= (int) ($automatedReportSummary['tests_failed'] ?? 0) ?></div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
          <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Bloqueios</div>
          <div class="mt-2 text-xl font-black text-amber-200"><?= (int) ($automatedReportSummary['security_blocks'] ?? 0) ?></div>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <div class="flex flex-wrap gap-2">
          <?php foreach (['todos' => 'Todos', 'local' => 'Local', 'stage' => 'Stage'] as $key => $label): ?>
            <a href="<?= htmlspecialchars($reportFilterUrl(['ambiente' => $key, 'nivel' => $reportLevel, 'status' => $reportStatus]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="rounded-xl border px-3 py-2 text-xs font-bold <?= $reportEnvironment === $key ? 'border-cyan-400/60 bg-cyan-500/10 text-cyan-100' : 'border-slate-800 bg-slate-950/70 text-slate-400' ?>"><?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
          <?php endforeach; ?>
          <?php foreach (['todos' => 'Todos niveis', 'safe' => 'Safe', 'routine' => 'Routine', 'unit' => 'Unit', 'full' => 'Full'] as $key => $label): ?>
            <a href="<?= htmlspecialchars($reportFilterUrl(['ambiente' => $reportEnvironment, 'nivel' => $key, 'status' => $reportStatus]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="rounded-xl border px-3 py-2 text-xs font-bold <?= $reportLevel === $key ? 'border-cyan-400/60 bg-cyan-500/10 text-cyan-100' : 'border-slate-800 bg-slate-950/70 text-slate-400' ?>"><?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
          <?php endforeach; ?>
          <?php foreach (['todos' => 'Todos status', 'ok' => 'OK', 'fail' => 'Fail', 'blocked' => 'Blocked'] as $key => $label): ?>
            <a href="<?= htmlspecialchars($reportFilterUrl(['ambiente' => $reportEnvironment, 'nivel' => $reportLevel, 'status' => $key]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="rounded-xl border px-3 py-2 text-xs font-bold <?= $reportStatus === $key ? 'border-cyan-400/60 bg-cyan-500/10 text-cyan-100' : 'border-slate-800 bg-slate-950/70 text-slate-400' ?>"><?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ($selectedReport !== []): ?>
        <?php $selectedTone = $automatedTone($selectedReport); ?>
        <div class="rounded-3xl border border-cyan-400/20 bg-slate-900/80 p-6">
          <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
              <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.2em] text-cyan-300/70">Detalhe do relatorio</div>
              <h2 class="mt-2 break-all font-orbitron text-lg font-black text-white"><?= htmlspecialchars((string) ($selectedReport['id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
            </div>
            <?php View::component('admin/v2/status-badge', [
                'label' => strtoupper((string) ($selectedReport['status'] ?? 'pendente')),
                'tone' => $selectedTone,
            ]); ?>
          </div>
          <div class="mt-5 grid gap-3 md:grid-cols-4">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4 text-sm"><span class="block text-slate-500">Ambiente</span><strong class="text-white"><?= htmlspecialchars((string) ($selectedReport['environment'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4 text-sm"><span class="block text-slate-500">Nivel</span><strong class="text-white"><?= htmlspecialchars((string) ($selectedReport['level'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4 text-sm"><span class="block text-slate-500">Duracao</span><strong class="text-white"><?= (int) ($selectedReport['duration_ms'] ?? 0) ?>ms</strong></div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4 text-sm"><span class="block text-slate-500">Execucao</span><strong class="text-white"><?= htmlspecialchars($formatDate((string) ($selectedReport['finished_at'] ?? $selectedReport['started_at'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></div>
          </div>
          <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-5">
              <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Grupos testados</div>
              <div class="mt-4 grid gap-2">
                <?php foreach ($testsByGroup as $group => $groupTests): ?>
                  <?php
                    $groupOk = count(array_filter($groupTests, static fn (array $test): bool => (string) ($test['status'] ?? '') === 'ok'));
                    $groupFail = count(array_filter($groupTests, static fn (array $test): bool => (string) ($test['status'] ?? '') === 'fail'));
                  ?>
                  <div class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-900/70 px-3 py-2 text-sm">
                    <span class="font-semibold text-white"><?= htmlspecialchars($group, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    <span class="text-slate-400"><?= $groupOk ?> OK / <?= $groupFail ?> falha(s)</span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-5">
              <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Auditoria</div>
              <div class="mt-4 grid gap-2 text-sm">
                <div class="flex justify-between rounded-xl border border-slate-800 bg-slate-900/70 px-3 py-2"><span class="text-slate-400">Bloqueios</span><strong class="text-amber-200"><?= count($selectedBlocks) ?></strong></div>
                <div class="flex justify-between rounded-xl border border-slate-800 bg-slate-900/70 px-3 py-2"><span class="text-slate-400">Criados</span><strong class="text-white"><?= count($selectedCreated) ?></strong></div>
                <div class="flex justify-between rounded-xl border border-slate-800 bg-slate-900/70 px-3 py-2"><span class="text-slate-400">Removidos</span><strong class="text-white"><?= count($selectedRemoved) ?></strong></div>
                <div class="flex justify-between rounded-xl border border-slate-800 bg-slate-900/70 px-3 py-2"><span class="text-slate-400">Residuos</span><strong class="<?= $selectedResidues === [] ? 'text-emerald-200' : 'text-rose-200' ?>"><?= count($selectedResidues) ?></strong></div>
              </div>
            </div>
          </div>

          <?php $failedTests = array_values(array_filter($selectedTests, static fn (array $test): bool => (string) ($test['status'] ?? '') === 'fail')); ?>
          <?php if ($failedTests !== []): ?>
            <div class="mt-5 rounded-2xl border border-rose-400/30 bg-rose-500/10 p-5">
              <div class="font-orbitron text-[10px] font-black uppercase tracking-[0.18em] text-rose-200">Falhas</div>
              <div class="mt-3 grid gap-2">
                <?php foreach ($failedTests as $test): ?>
                  <div class="rounded-xl border border-rose-400/20 bg-slate-950/70 px-3 py-2 text-sm text-rose-100">
                    <strong><?= htmlspecialchars((string) ($test['name'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                    <span class="block text-rose-200/80"><?= htmlspecialchars((string) ($test['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-black text-white">Relatorios Operacionais</h2>
        <div class="mt-5 overflow-x-auto">
          <table class="min-w-full border-separate border-spacing-y-3 text-left text-sm text-slate-200">
            <thead>
              <tr class="font-orbitron text-[10px] uppercase tracking-[0.18em] text-slate-500">
                <th class="px-4 py-2">Data</th>
                <th class="px-4 py-2">Ambiente</th>
                <th class="px-4 py-2">Nivel</th>
                <th class="px-4 py-2">Rotinas</th>
                <th class="px-4 py-2">Resumo</th>
                <th class="px-4 py-2">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($filteredReports as $item): ?>
                <?php
                  $item = is_array($item) ? $item : [];
                  $tone = $automatedTone($item);
                  $selected = array_values(array_map('strval', (array) ($item['selected_routines'] ?? [])));
                ?>
                <tr>
                  <td class="rounded-l-2xl border-y border-l border-slate-800 bg-slate-950/70 px-4 py-3 text-xs font-semibold text-slate-300"><?= htmlspecialchars($formatDate((string) ($item['finished_at'] ?? $item['started_at'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                  <td class="border-y border-slate-800 bg-slate-950/70 px-4 py-3 font-black text-white"><?= htmlspecialchars((string) ($item['environment'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                  <td class="border-y border-slate-800 bg-slate-950/70 px-4 py-3 font-semibold text-slate-200"><a class="text-cyan-100 hover:text-white" href="<?= htmlspecialchars($reportFilterUrl(['ambiente' => $reportEnvironment, 'nivel' => $reportLevel, 'status' => $reportStatus, 'relatorio' => (string) ($item['id'] ?? '')]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars((string) ($item['level'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a></td>
                  <td class="border-y border-slate-800 bg-slate-950/70 px-4 py-3 text-slate-300"><?= htmlspecialchars($selected !== [] ? implode(', ', $selected) : '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                  <td class="border-y border-slate-800 bg-slate-950/70 px-4 py-3"><?= (int) ($item['tests_ok'] ?? 0) ?> OK / <?= (int) ($item['tests_failed'] ?? 0) ?> falha(s) / <?= (int) ($item['tests_skipped'] ?? 0) ?> pulado(s)</td>
                  <td class="rounded-r-2xl border-y border-r border-slate-800 bg-slate-950/70 px-4 py-3">
                    <?php View::component('admin/v2/status-badge', [
                        'label' => strtoupper((string) ($item['status'] ?? 'pendente')),
                        'tone' => $tone,
                    ]); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if ($filteredReports === []): ?>
                <tr><td colspan="6" class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-8 text-center text-sm font-semibold text-slate-400">Nenhum relatorio operacional encontrado.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-black text-white">Relatorios Smoke</h2>
        <div class="mt-5 overflow-x-auto">
          <table class="min-w-full border-separate border-spacing-y-3 text-left text-sm text-slate-200">
            <thead>
              <tr class="font-orbitron text-[10px] uppercase tracking-[0.18em] text-slate-500">
                <th class="px-4 py-2">Data</th>
                <th class="px-4 py-2">Ambiente</th>
                <th class="px-4 py-2">Identificador</th>
                <th class="px-4 py-2">Resumo</th>
                <th class="px-4 py-2">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($history as $item): ?>
                <?php
                  $item = is_array($item) ? $item : [];
                  $summary = is_array($item['summary'] ?? null) ? $item['summary'] : [];
                  $tone = $statusTone($item);
                ?>
                <tr>
                  <td class="rounded-l-2xl border-y border-l border-slate-800 bg-slate-950/70 px-4 py-3 text-xs font-semibold text-slate-300"><?= htmlspecialchars($formatDate((string) ($item['finished_at'] ?? $item['started_at'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                  <td class="border-y border-slate-800 bg-slate-950/70 px-4 py-3 font-black text-white"><?= htmlspecialchars((string) ($item['environment_label'] ?? $item['environment'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                  <td class="border-y border-slate-800 bg-slate-950/70 px-4 py-3 font-semibold text-cyan-100"><?= htmlspecialchars((string) ($item['id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                  <td class="border-y border-slate-800 bg-slate-950/70 px-4 py-3"><?= (int) ($summary['ok'] ?? 0) ?> OK / <?= (int) ($summary['fail'] ?? 0) ?> falha(s) / <?= (int) ($summary['skip'] ?? 0) ?> ignorado(s)</td>
                  <td class="rounded-r-2xl border-y border-r border-slate-800 bg-slate-950/70 px-4 py-3">
                    <?php View::component('admin/v2/status-badge', [
                        'label' => strtoupper((string) ($item['status'] ?? 'pendente')),
                        'tone' => $tone,
                    ]); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if ($history === []): ?>
                <tr><td colspan="5" class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-8 text-center text-sm font-semibold text-slate-400">Nenhum smoke salvo encontrado.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  <?php endif; ?>
</section>
