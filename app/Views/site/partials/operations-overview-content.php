<?php

declare(strict_types=1);

$adminEmbed = (bool) ($admin_embed ?? false);
$overviewSection = (string) ($overview_section ?? 'resumo');
$operationsStatus = (array) ($operations_status ?? []);
$policy = (array) ($operationsStatus['policy'] ?? []);
$backup = (array) ($operationsStatus['backup'] ?? []);
$content = (array) ($operationsStatus['content'] ?? []);
$code = (array) ($operationsStatus['code'] ?? []);
$technicalBackup = (array) ($operationsStatus['technical_backup'] ?? []);
$parity = (array) ($operationsStatus['parity'] ?? []);
$smokeTests = (array) ($operationsStatus['smoke_tests'] ?? []);
$logCategories = is_array($operationsStatus['logs']['categories'] ?? null) ? $operationsStatus['logs']['categories'] : [];

$productionGateOpen = (bool) ($policy['production_allowed'] ?? false);
$parityContent = (array) ($parity['content'] ?? []);
$parityCode = (array) ($parity['code'] ?? []);

$latestDataBackup = is_array($backup['latest'] ?? null) ? $backup['latest'] : null;
$latestUploadedBackup = is_array($backup['latest_uploaded'] ?? null) ? $backup['latest_uploaded'] : null;
$latestContentPackage = is_array($content['latest'] ?? null) ? $content['latest'] : null;
$latestCodePackage = is_array($code['latest'] ?? null) ? $code['latest'] : null;
$latestStageContentApply = is_array($content['latest_stage_apply'] ?? null) ? $content['latest_stage_apply'] : null;
$latestProductionContentApply = is_array($content['latest_production_apply'] ?? null) ? $content['latest_production_apply'] : null;
$latestStageCodeApply = is_array($code['latest_stage_apply'] ?? null) ? $code['latest_stage_apply'] : null;
$latestProductionCodeApply = is_array($code['latest_production_apply'] ?? null) ? $code['latest_production_apply'] : null;
$latestTechnicalLocal = is_array($technicalBackup['local_latest'] ?? null) ? $technicalBackup['local_latest'] : null;
$latestTechnicalStage = is_array($technicalBackup['stage_latest'] ?? null) ? $technicalBackup['stage_latest'] : null;
$latestTechnicalProduction = is_array($technicalBackup['production_latest'] ?? null) ? $technicalBackup['production_latest'] : null;

$backupTabUrl = $adminEmbed ? url('/admin/central-operacional?aba=backup-restore') : url('/local/backup');
$contentTabUrl = $adminEmbed ? url('/admin/central-operacional?aba=conteudo') : url('/local/conteudo');
$testsReturnUrl = $adminEmbed
    ? url('/admin/central-operacional?aba=visao-geral&secao=testes')
    : url('/local/operacoes?secao=testes');
$testsReturnPath = (string) parse_url($testsReturnUrl, PHP_URL_PATH);
$testsReturnQuery = (string) parse_url($testsReturnUrl, PHP_URL_QUERY);
$testsReturnTarget = $testsReturnPath . ($testsReturnQuery !== '' ? '?' . $testsReturnQuery : '');

$metaLine = static function (string $label, string $value): string {
    return '<div class="flex items-start justify-between gap-4"><span class="text-slate-500">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span><strong class="text-right font-semibold text-slate-100">' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong></div>';
};

$pill = static function (string $label, string $tone = 'default'): string {
    $classes = match ($tone) {
        'ok' => 'border-emerald-400/30 bg-emerald-500/10 text-emerald-200',
        'warn' => 'border-amber-400/30 bg-amber-500/10 text-amber-200',
        default => 'border-slate-700 bg-slate-950/70 text-slate-300',
    };

    return '<span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold ' . $classes . '">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
};

$statusPill = static function (string $status) use ($pill): string {
    return match (strtolower($status)) {
        'ok' => $pill('OK', 'ok'),
        'fail' => $pill('Falhou', 'warn'),
        'skip' => $pill('Ignorado'),
        default => $pill('Pendente'),
    };
};
?>
<section data-operations-overview-panel class="space-y-6">
  <?php if ($overviewSection === 'resumo'): ?>
    <div class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Visao Geral</p>
      <h2 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Resumo operacional leve</h2>
      <p class="mt-3 max-w-4xl text-sm leading-7 text-slate-300">Esta entrada ficou propositalmente mais leve. Ela carrega so a politica atual e a orientacao de fluxo. Backups, pacotes e historico agora entram sob demanda quando voce abre a subaba correspondente.</p>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
      <div class="operations-summary-card operations-summary-card--highlight">
        <div>
          <p class="operations-summary-label">Politica</p>
          <div class="operations-summary-headline <?= $productionGateOpen ? 'text-emerald-300' : 'text-amber-300' ?>">
            <?= $productionGateOpen ? 'Producao liberada' : 'Producao bloqueada' ?>
          </div>
          <p class="operations-summary-note"><?= htmlspecialchars((string) ($policy['message'] ?? 'Promocao controlada por politica operacional.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        </div>
        <div class="operations-summary-list">
          <?= $metaLine('Origem atual', (string) ($policy['current_source'] ?? 'local')) ?>
          <?= $metaLine('Origem aprovada', (string) ($policy['approved_source'] ?? 'stage')) ?>
        </div>
      </div>

      <div class="operations-summary-card">
        <div>
          <p class="operations-summary-label">Fluxo oficial</p>
          <div class="operations-summary-headline text-white">Local -> Stage</div>
          <p class="operations-summary-note">Validar em stage antes de qualquer pacote de producao.</p>
        </div>
        <div class="operations-summary-pill-row">
          <?= $pill('Smoke em stage', 'ok') ?>
          <?= $pill('Pacote nasce da stage', 'warn') ?>
        </div>
      </div>

      <div class="operations-summary-card">
        <div>
          <p class="operations-summary-label">Subabas</p>
          <div class="operations-summary-headline text-white">Carga sob demanda</div>
          <p class="operations-summary-note">Backups, pacotes e historico foram separados para abrir mais rapido e consultar so quando precisar.</p>
        </div>
        <div class="operations-summary-pill-row">
          <?= $pill('Backups') ?>
          <?= $pill('Pacotes') ?>
          <?= $pill('Historico') ?>
        </div>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Aba dedicada</p>
            <h3 class="mt-2 font-orbitron text-xl font-bold text-white">Backup e Restore</h3>
            <p class="mt-3 text-sm leading-7 text-slate-400">Use esta aba para consultar backups de dados, snapshots tecnicos, verificacoes e restauracoes controladas.</p>
          </div>
          <a href="<?= htmlspecialchars($backupTabUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20">Abrir aba</a>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Aba dedicada</p>
            <h3 class="mt-2 font-orbitron text-xl font-bold text-white">Conteudo</h3>
            <p class="mt-3 text-sm leading-7 text-slate-400">Use esta aba para consultar pacotes editoriais, deploy tecnico, paridade e publicacoes controladas.</p>
          </div>
          <a href="<?= htmlspecialchars($contentTabUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20">Abrir aba</a>
        </div>
      </div>
    </div>
  <?php elseif ($overviewSection === 'backups'): ?>
    <div class="grid gap-4 xl:grid-cols-5">
      <div class="operations-summary-card operations-summary-card--highlight">
        <div>
          <p class="operations-summary-label">Backup de dados</p>
          <div class="operations-summary-headline text-white"><?= htmlspecialchars((string) ($latestDataBackup['backup_id'] ?? 'Pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <p class="operations-summary-note"><?= (int) ($backup['total'] ?? 0) ?> pacotes registrados na base local.</p>
        </div>
        <?php if ($latestDataBackup !== null): ?>
          <div>
            <div class="operations-summary-pill-row">
              <?= $pill((string) ($latestDataBackup['profile_label'] ?? 'Perfil')) ?>
              <?= $pill((bool) ($latestDataBackup['is_valid'] ?? false) ? 'Valido' : 'Falhou', (bool) ($latestDataBackup['is_valid'] ?? false) ? 'ok' : 'warn') ?>
            </div>
            <div class="operations-summary-list">
              <?= $metaLine('Criado em', (string) ($latestDataBackup['created_at'] ?? '-')) ?>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="operations-summary-card">
        <div>
          <p class="operations-summary-label">Nuvem</p>
          <div class="operations-summary-headline text-white"><?= htmlspecialchars((string) ($latestUploadedBackup['backup_id'] ?? 'Pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <p class="operations-summary-note">Ultimo pacote marcado como enviado manualmente.</p>
        </div>
        <div class="operations-summary-list">
          <?= $metaLine('Enviado em', (string) ($latestUploadedBackup['cloud_uploaded_at'] ?? 'Ainda nao marcado')) ?>
        </div>
      </div>

      <div class="operations-summary-card">
        <div>
          <p class="operations-summary-label">Tecnico local</p>
          <div class="operations-summary-headline text-white"><?= htmlspecialchars((string) ($latestTechnicalLocal['backup_id'] ?? 'Pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <p class="operations-summary-note">Snapshot tecnico mais recente do ambiente local.</p>
        </div>
        <div class="operations-summary-pill-row">
          <?= $pill(((int) ($latestTechnicalLocal['files_count'] ?? 0)) . ' arquivos') ?>
        </div>
      </div>

      <div class="operations-summary-card">
        <div>
          <p class="operations-summary-label">Tecnico stage</p>
          <div class="operations-summary-headline text-white"><?= htmlspecialchars((string) ($latestTechnicalStage['backup_id'] ?? 'Pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <p class="operations-summary-note">Ultimo snapshot tecnico promovido para homologacao.</p>
        </div>
        <div class="operations-summary-pill-row">
          <?= $pill(((int) ($latestTechnicalStage['files_count'] ?? 0)) . ' arquivos') ?>
        </div>
      </div>

      <div class="operations-summary-card">
        <div>
          <p class="operations-summary-label">Tecnico producao</p>
          <div class="operations-summary-headline text-white"><?= htmlspecialchars((string) ($latestTechnicalProduction['backup_id'] ?? 'Pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <p class="operations-summary-note">Ultimo snapshot tecnico promovido para producao.</p>
        </div>
        <div class="operations-summary-pill-row">
          <?= $pill(((int) ($latestTechnicalProduction['files_count'] ?? 0)) . ' arquivos') ?>
        </div>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <div class="flex items-center justify-between gap-4">
          <h3 class="font-orbitron text-lg font-bold text-white">Perfis de backup</h3>
          <a href="<?= htmlspecialchars($backupTabUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="text-sm font-semibold text-cyan-200 hover:text-cyan-100">Abrir Backup e Restore</a>
        </div>
        <div class="mt-5 grid gap-4 md:grid-cols-3">
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-500">Local</p>
            <p class="mt-3 text-sm text-slate-300"><?= (bool) ($backup['local_ready'] ?? false) ? 'Configurado' : 'Pendente' ?></p>
          </div>
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-500">Stage</p>
            <p class="mt-3 text-sm text-slate-300"><?= (bool) ($backup['stage_ready'] ?? false) ? 'Configurado' : 'Pendente' ?></p>
          </div>
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <p class="text-xs uppercase tracking-[0.25em] text-slate-500">Producao</p>
            <p class="mt-3 text-sm text-slate-300"><?= (bool) ($backup['production_ready'] ?? false) ? 'Configurado' : 'Pendente' ?></p>
          </div>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h3 class="font-orbitron text-lg font-bold text-white">Snapshots tecnicos</h3>
        <div class="mt-5 space-y-3">
          <?php foreach (['local' => $latestTechnicalLocal, 'stage' => $latestTechnicalStage, 'production' => $latestTechnicalProduction] as $profile => $snapshot): ?>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <div class="flex items-center justify-between gap-4">
                <p class="font-semibold text-white"><?= htmlspecialchars(ucfirst($profile), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                <span class="text-xs uppercase tracking-[0.2em] text-slate-500"><?= htmlspecialchars((string) ($snapshot['created_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              </div>
              <p class="mt-2 text-sm text-slate-300"><?= htmlspecialchars((string) ($snapshot['backup_id'] ?? 'Sem snapshot'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php elseif ($overviewSection === 'pacotes'): ?>
    <div class="grid gap-4 xl:grid-cols-3">
      <div class="operations-summary-card operations-summary-card--highlight">
        <div>
          <p class="operations-summary-label">Conteudo editorial</p>
          <div class="operations-summary-headline text-white"><?= htmlspecialchars((string) ($latestContentPackage['package_id'] ?? 'Pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <p class="operations-summary-note"><?= (int) ($content['total'] ?? 0) ?> pacotes registrados para sincronizacao editorial.</p>
        </div>
        <div class="operations-summary-list">
          <?= $metaLine('Stage', (string) ($latestStageContentApply['package_id'] ?? 'Sem aplicacao')) ?>
          <?= $metaLine('Producao', (string) ($latestProductionContentApply['package_id'] ?? 'Sem aplicacao')) ?>
        </div>
      </div>

      <div class="operations-summary-card">
        <div>
          <p class="operations-summary-label">Codigo tecnico</p>
          <div class="operations-summary-headline text-white"><?= htmlspecialchars((string) ($latestCodePackage['package_id'] ?? 'Pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <p class="operations-summary-note"><?= (int) ($code['total'] ?? 0) ?> pacotes tecnicos disponiveis no repositorio local.</p>
        </div>
        <div class="operations-summary-list">
          <?= $metaLine('Stage', (string) ($latestStageCodeApply['package_id'] ?? 'Sem aplicacao')) ?>
          <?= $metaLine('Producao', (string) ($latestProductionCodeApply['package_id'] ?? 'Sem aplicacao')) ?>
        </div>
      </div>

      <div class="operations-summary-card">
        <div>
          <p class="operations-summary-label">Paridade</p>
          <div class="operations-summary-headline text-white"><?= (bool) ($parity['overall_in_sync'] ?? false) ? 'Alinhado' : 'Com divergencia' ?></div>
          <p class="operations-summary-note">Resumo rapido da consistencia entre conteudo e codigo no fluxo de promocao.</p>
        </div>
        <div class="operations-summary-list">
          <div><span>Conteudo</span><strong class="<?= (bool) ($parityContent['in_sync'] ?? false) ? 'text-emerald-300' : 'text-amber-300' ?>"><?= (bool) ($parityContent['in_sync'] ?? false) ? 'OK' : 'Pendente' ?></strong></div>
          <div><span>Codigo</span><strong class="<?= (bool) ($parityCode['in_sync'] ?? false) ? 'text-emerald-300' : 'text-amber-300' ?>"><?= (bool) ($parityCode['in_sync'] ?? false) ? 'OK' : 'Pendente' ?></strong></div>
        </div>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h3 class="font-orbitron text-lg font-bold text-white">Conteudo editorial</h3>
        <div class="mt-5 space-y-3">
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <?= $metaLine('Raiz', (string) ($content['root'] ?? '-')) ?>
            <?= $metaLine('Stage pronto', (bool) ($content['stage_ready'] ?? false) ? 'Sim' : 'Nao') ?>
            <?= $metaLine('Producao pronta', (bool) ($content['production_ready'] ?? false) ? 'Sim' : 'Nao') ?>
          </div>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <div class="flex items-center justify-between gap-4">
          <h3 class="font-orbitron text-lg font-bold text-white">Promocao e paridade</h3>
          <a href="<?= htmlspecialchars($contentTabUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="text-sm font-semibold text-cyan-200 hover:text-cyan-100">Abrir Conteudo</a>
        </div>
        <div class="mt-5 space-y-3">
          <?php foreach ((array) ($parity['recommendations'] ?? []) as $recommendation): ?>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm text-slate-300"><?= htmlspecialchars((string) $recommendation, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <?php endforeach; ?>
          <?php if ((array) ($parity['recommendations'] ?? []) === []): ?>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm text-slate-300">Nenhuma recomendacao pendente no momento.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php elseif ($overviewSection === 'testes'): ?>
    <?php
      $smokeEnvironments = is_array($smokeTests['environments'] ?? null) ? $smokeTests['environments'] : [];
      $latestByEnvironment = is_array($smokeTests['latest_by_environment'] ?? null) ? $smokeTests['latest_by_environment'] : [];
      $smokeHistory = is_array($smokeTests['history'] ?? null) ? $smokeTests['history'] : [];
    ?>
    <div class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Testes Automatizados</p>
      <h2 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Smoke tests por ambiente</h2>
      <p class="mt-3 max-w-4xl text-sm leading-7 text-slate-300">Executa verificacoes seguras de leitura antes de considerar uma promocao tecnica. A stage so fica apta quando os testes obrigatorios passam sem falhas.</p>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
      <?php foreach ($smokeEnvironments as $environment): ?>
        <?php
          $key = (string) ($environment['key'] ?? '');
          $latest = is_array($latestByEnvironment[$key] ?? null) ? $latestByEnvironment[$key] : [];
          $summary = is_array($latest['summary'] ?? null) ? $latest['summary'] : [];
          $status = (string) ($latest['status'] ?? 'pending');
        ?>
        <div class="operations-summary-card <?= $key === 'stage' ? 'operations-summary-card--highlight' : '' ?>">
          <div>
            <p class="operations-summary-label"><?= htmlspecialchars((string) ($environment['label'] ?? $key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <div class="operations-summary-headline <?= $status === 'ok' ? 'text-emerald-300' : ($status === 'fail' ? 'text-amber-300' : 'text-white') ?>">
              <?= $status === 'ok' ? 'OK' : ($status === 'fail' ? 'Falhou' : 'Sem teste') ?>
            </div>
            <p class="operations-summary-note break-all"><?= htmlspecialchars((string) ($environment['base_url'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
          </div>
          <div>
            <div class="operations-summary-list">
              <?= $metaLine('Ultima execucao', (string) ($latest['finished_at'] ?? 'Nunca')) ?>
              <?= $metaLine('Resultado', sprintf('%d OK / %d falhas / %d ignorados', (int) ($summary['ok'] ?? 0), (int) ($summary['fail'] ?? 0), (int) ($summary['skip'] ?? 0))) ?>
            </div>
            <form method="POST" action="<?= htmlspecialchars(url('/local/operacoes'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="mt-4">
              <?= \App\Support\Csrf::field() ?>
              <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($testsReturnTarget, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
              <input type="hidden" name="action" value="run_smoke_tests">
              <input type="hidden" name="environment" value="<?= htmlspecialchars($key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
              <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20">Executar testes</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php $stageLatest = is_array($latestByEnvironment['stage'] ?? null) ? $latestByEnvironment['stage'] : []; ?>
    <div class="rounded-3xl border <?= (bool) ($stageLatest['ready_for_technical_deploy'] ?? false) ? 'border-emerald-500/30 bg-emerald-500/10' : 'border-amber-500/30 bg-amber-500/10' ?> p-5">
      <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
        <div>
          <p class="font-orbitron text-xs uppercase tracking-[0.25em] <?= (bool) ($stageLatest['ready_for_technical_deploy'] ?? false) ? 'text-emerald-200' : 'text-amber-200' ?>">Trava de promocao tecnica</p>
          <p class="mt-2 text-lg font-semibold text-white"><?= (bool) ($stageLatest['ready_for_technical_deploy'] ?? false) ? 'Stage apta para pacote tecnico' : 'Stage ainda nao liberada para pacote tecnico' ?></p>
        </div>
        <?= $statusPill((string) ($stageLatest['status'] ?? 'pending')) ?>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h3 class="font-orbitron text-lg font-bold text-white">Detalhes da ultima execucao</h3>
        <?php $latestOverall = $smokeHistory[0] ?? null; ?>
        <?php if (is_array($latestOverall)): ?>
          <div class="mt-4 rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
              <div>
                <p class="font-semibold text-white"><?= htmlspecialchars((string) ($latestOverall['id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                <p class="mt-1 text-sm text-slate-400"><?= htmlspecialchars((string) ($latestOverall['environment_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> - <?= htmlspecialchars((string) ($latestOverall['finished_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
              </div>
              <?= $statusPill((string) ($latestOverall['status'] ?? 'pending')) ?>
            </div>
          </div>
          <div class="mt-4 overflow-hidden rounded-2xl border border-slate-800">
            <table class="w-full text-left text-sm">
              <thead class="bg-slate-950/80 text-xs uppercase tracking-[0.2em] text-slate-500">
                <tr>
                  <th class="px-4 py-3">Teste</th>
                  <th class="px-4 py-3">Status</th>
                  <th class="px-4 py-3">HTTP</th>
                  <th class="px-4 py-3">Tempo</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800 bg-slate-950/50">
                <?php foreach ((array) ($latestOverall['tests'] ?? []) as $test): ?>
                  <tr>
                    <td class="px-4 py-3">
                      <div class="font-semibold text-white"><?= htmlspecialchars((string) ($test['name'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                      <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) ($test['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    </td>
                    <td class="px-4 py-3"><?= $statusPill((string) ($test['status'] ?? 'pending')) ?></td>
                    <td class="px-4 py-3 text-slate-300"><?= htmlspecialchars((string) ($test['http_status'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-slate-300"><?= (int) ($test['duration_ms'] ?? 0) ?>ms</td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="mt-4 text-sm text-slate-400">Nenhum teste executado ainda.</p>
        <?php endif; ?>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h3 class="font-orbitron text-lg font-bold text-white">Historico recente</h3>
        <div class="mt-5 space-y-3">
          <?php foreach ($smokeHistory as $run): ?>
            <?php $runSummary = is_array($run['summary'] ?? null) ? $run['summary'] : []; ?>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <div class="flex items-center justify-between gap-4">
                <p class="font-semibold text-white"><?= htmlspecialchars((string) ($run['environment_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                <?= $statusPill((string) ($run['status'] ?? 'pending')) ?>
              </div>
              <p class="mt-2 text-xs text-slate-500"><?= htmlspecialchars((string) ($run['id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
              <p class="mt-2 text-sm text-slate-300"><?= (int) ($runSummary['ok'] ?? 0) ?> OK, <?= (int) ($runSummary['fail'] ?? 0) ?> falhas, <?= (int) ($runSummary['skip'] ?? 0) ?> ignorados</p>
            </div>
          <?php endforeach; ?>
          <?php if ($smokeHistory === []): ?>
            <p class="text-sm text-slate-400">Sem historico registrado.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Historico</p>
      <h2 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Leitura operacional recente</h2>
      <p class="mt-3 max-w-4xl text-sm leading-7 text-slate-300">Cada grupo abaixo carrega so os ultimos eventos relevantes para a central. Isso ajuda a diagnosticar o que aconteceu sem pesar a entrada da pagina.</p>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
      <?php foreach ($logCategories as $categoryKey => $category): ?>
        <?php
          $entries = is_array($category['entries'] ?? null) ? $category['entries'] : [];
          $label = (string) ($category['label'] ?? ucfirst((string) $categoryKey));
        ?>
        <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80"><?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
              <h3 class="mt-2 font-orbitron text-lg font-bold text-white"><?= count($entries) ?> eventos</h3>
            </div>
            <span class="rounded-full border border-slate-700 bg-slate-950/80 px-3 py-1 text-xs uppercase tracking-[0.2em] text-slate-400"><?= htmlspecialchars((string) basename((string) ($category['latest_file'] ?? '-')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          </div>

          <?php if ($entries === []): ?>
            <p class="mt-4 text-sm text-slate-400">Nenhum evento recente nesta categoria.</p>
          <?php else: ?>
            <div class="mt-5 space-y-3">
              <?php foreach ($entries as $entry): ?>
                <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                  <div class="flex items-center justify-between gap-4">
                    <p class="font-semibold text-white"><?= htmlspecialchars((string) ($entry['type'] ?? 'evento'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                    <span class="text-xs uppercase tracking-[0.2em] text-slate-500"><?= htmlspecialchars((string) ($entry['timestamp'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                  </div>
                  <p class="mt-2 text-sm text-slate-300"><?= htmlspecialchars((string) ($entry['message'] ?? 'Sem resumo adicional.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                  <div class="mt-3 flex flex-wrap gap-2">
                    <?= $pill('origem: ' . (string) ($entry['origin'] ?? '-')) ?>
                    <?= $pill('destino: ' . (string) ($entry['destination'] ?? '-')) ?>
                    <?= $pill('status: ' . (string) ($entry['status'] ?? '-'), strtolower((string) ($entry['status'] ?? '')) === 'ok' ? 'ok' : 'warn') ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
