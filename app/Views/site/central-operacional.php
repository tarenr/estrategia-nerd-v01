<?php

declare(strict_types=1);

$embedMode = (bool) ($embed_mode ?? false);
$adminEmbed = (bool) ($admin_embed ?? false);

$flash = is_array($flash ?? null) ? $flash : null;
$operationsStatus = (array) ($operations_status ?? []);
$policy = (array) ($operationsStatus['policy'] ?? []);
$backup = (array) ($operationsStatus['backup'] ?? []);
$content = (array) ($operationsStatus['content'] ?? []);
$code = (array) ($operationsStatus['code'] ?? []);
$technicalBackup = (array) ($operationsStatus['technical_backup'] ?? []);
$parity = (array) ($operationsStatus['parity'] ?? []);
$parityContent = (array) ($parity['content'] ?? []);
$parityCode = (array) ($parity['code'] ?? []);
$logs = (array) ($operationsStatus['logs'] ?? []);
$logCategories = is_array($logs['categories'] ?? null) ? $logs['categories'] : [];

$productionGateOpen = (bool) ($policy['production_allowed'] ?? false);
$latestDataBackup = is_array($backup['latest'] ?? null) ? $backup['latest'] : null;
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

$alertClasses = [
    'success' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100',
    'error' => 'border-rose-500/40 bg-rose-500/10 text-rose-100',
];
$flashClass = $flash !== null ? ($alertClasses[$flash['type']] ?? $alertClasses['success']) : '';

$statusBadge = static function (bool $ok, string $okLabel = 'OK', string $pendingLabel = 'Pendente'): string {
    $class = $ok ? 'text-emerald-300' : 'text-amber-300';
    $label = $ok ? $okLabel : $pendingLabel;

    return '<span class="' . $class . '">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
};

$renderMetaLine = static function (string $label, string $value): string {
    return '<div class="flex items-center justify-between gap-4"><span class="text-slate-500">' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span><span class="text-right text-slate-200">' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span></div>';
};

$recentSystemLogs = array_slice(array_values((array) ($logCategories['system'] ?? [])), 0, 4);
$recentOperationsLogs = array_slice(array_values((array) ($logCategories['operations'] ?? [])), 0, 4);
?>
<section class="<?= $adminEmbed ? 'text-slate-100' : 'min-h-screen bg-slate-950 px-4 py-8 text-slate-100' ?>">
  <style>
    .operations-summary-card {
      display: flex;
      min-height: 13.5rem;
      flex-direction: column;
      justify-content: space-between;
      border-radius: 1.5rem;
      border: 1px solid rgba(30, 41, 59, 0.9);
      background:
        radial-gradient(circle at top right, rgba(34, 211, 238, 0.08), transparent 40%),
        rgba(15, 23, 42, 0.82);
      padding: 1.25rem;
      box-shadow: inset 0 1px 0 rgba(148, 163, 184, 0.05);
    }

    .operations-summary-card--highlight {
      border-color: rgba(34, 211, 238, 0.24);
      background:
        radial-gradient(circle at top right, rgba(34, 211, 238, 0.12), transparent 42%),
        rgba(15, 23, 42, 0.88);
    }

    .operations-summary-label {
      font-family: Orbitron, ui-sans-serif, system-ui, sans-serif;
      font-size: 0.68rem;
      font-weight: 700;
      letter-spacing: 0.22em;
      text-transform: uppercase;
      color: rgba(103, 232, 249, 0.8);
    }

    .operations-summary-headline {
      margin-top: 0.9rem;
      font-family: Rajdhani, ui-sans-serif, system-ui, sans-serif;
      font-size: 2rem;
      font-weight: 700;
      line-height: 1;
      color: #f8fafc;
    }

    .operations-summary-note {
      margin-top: 0.45rem;
      font-size: 0.9rem;
      color: #94a3b8;
    }

    .operations-summary-list {
      margin-top: 1rem;
      display: grid;
      gap: 0.6rem;
      font-size: 0.85rem;
    }

    .operations-summary-list > div {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      color: #cbd5e1;
    }

    .operations-summary-list span:first-child {
      color: #64748b;
    }

    .operations-summary-pill-row {
      margin-top: 1rem;
      display: flex;
      flex-wrap: wrap;
      gap: 0.55rem;
    }

    .operations-summary-pill {
      border-radius: 999px;
      border: 1px solid rgba(51, 65, 85, 0.9);
      background: rgba(2, 6, 23, 0.46);
      padding: 0.35rem 0.7rem;
      font-size: 0.72rem;
      font-weight: 600;
      color: #cbd5e1;
    }
  </style>
  <div class="<?= $adminEmbed ? 'space-y-6' : 'mx-auto max-w-7xl space-y-6' ?>">
    <?php if (!$embedMode): ?>
      <?php \App\Support\View::component('site/local-tools-nav', ['active' => 'operations']); ?>
    <?php endif; ?>

    <div class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Central Operacional</p>
      <h1 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Visao geral</h1>
      <p class="mt-3 max-w-4xl text-sm leading-7 text-slate-300">Esta aba agora funciona so como painel-resumo. Ela mostra politica, estado atual, ultimos pacotes e atalhos para as operacoes detalhadas. Backup, restore, publicacao e rollback ficam apenas nas abas proprias.</p>
    </div>

    <?php if ($flash !== null): ?>
      <div class="rounded-2xl border px-4 py-3 text-sm <?= htmlspecialchars($flashClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <?= htmlspecialchars((string) ($flash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <div class="grid gap-4 xl:grid-cols-6">
      <div class="operations-summary-card operations-summary-card--highlight">
        <div>
          <p class="operations-summary-label">Politica</p>
          <div class="operations-summary-headline <?= $productionGateOpen ? 'text-emerald-300' : 'text-amber-300' ?>">
            <?= $productionGateOpen ? 'Producao liberada' : 'Producao bloqueada' ?>
          </div>
          <p class="operations-summary-note">Estado atual da regra de promocao para producao.</p>
        </div>
        <div class="operations-summary-list">
          <div><span>Origem atual</span><strong><?= htmlspecialchars((string) ($policy['current_source'] ?? 'local'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></div>
          <div><span>Origem aprovada</span><strong><?= htmlspecialchars((string) ($policy['approved_source'] ?? 'stage'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></div>
        </div>
      </div>

      <div class="operations-summary-card">
        <div>
          <p class="operations-summary-label">Backup de dados</p>
          <div class="operations-summary-headline text-white"><?= htmlspecialchars((string) ($latestDataBackup['backup_id'] ?? 'Pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <p class="operations-summary-note"><?= (int) ($backup['total'] ?? 0) ?> pacotes registrados</p>
        </div>
        <?php if ($latestDataBackup !== null): ?>
          <div>
            <div class="operations-summary-pill-row">
              <span class="operations-summary-pill"><?= htmlspecialchars((string) ($latestDataBackup['profile_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              <span class="operations-summary-pill"><?= (bool) ($latestDataBackup['is_valid'] ?? false) ? 'Valido' : 'Falhou' ?></span>
            </div>
            <div class="operations-summary-list">
              <div><span>Criado em</span><strong><?= htmlspecialchars((string) ($latestDataBackup['created_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="operations-summary-card">
        <div>
          <p class="operations-summary-label">Tecnico local</p>
          <div class="operations-summary-headline text-white"><?= htmlspecialchars((string) ($latestTechnicalLocal['backup_id'] ?? 'Pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <p class="operations-summary-note">Snapshot tecnico mais recente do ambiente local.</p>
        </div>
        <div class="operations-summary-pill-row">
          <span class="operations-summary-pill"><?= (int) ($latestTechnicalLocal['files_count'] ?? 0) ?> arquivos</span>
        </div>
      </div>

      <div class="operations-summary-card">
        <div>
          <p class="operations-summary-label">Tecnico stage</p>
          <div class="operations-summary-headline text-white"><?= htmlspecialchars((string) ($latestTechnicalStage['backup_id'] ?? 'Pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <p class="operations-summary-note">Ultimo snapshot tecnico enviado para homologacao.</p>
        </div>
        <div class="operations-summary-pill-row">
          <span class="operations-summary-pill"><?= (int) ($latestTechnicalStage['files_count'] ?? 0) ?> arquivos</span>
        </div>
      </div>

      <div class="operations-summary-card">
        <div>
          <p class="operations-summary-label">Tecnico producao</p>
          <div class="operations-summary-headline text-white"><?= htmlspecialchars((string) ($latestTechnicalProduction['backup_id'] ?? 'Pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <p class="operations-summary-note">Ultimo snapshot tecnico promovido para producao.</p>
        </div>
        <div class="operations-summary-pill-row">
          <span class="operations-summary-pill"><?= (int) ($latestTechnicalProduction['files_count'] ?? 0) ?> arquivos</span>
        </div>
      </div>

      <div class="operations-summary-card">
        <div>
          <p class="operations-summary-label">Paridade</p>
          <div class="operations-summary-headline text-white">Estado atual</div>
          <p class="operations-summary-note">Resumo rapido da consistencia entre conteudo e codigo.</p>
        </div>
        <div class="operations-summary-list">
          <div><span>Conteudo</span><strong class="<?= (bool) ($parityContent['in_sync'] ?? false) ? 'text-emerald-300' : 'text-amber-300' ?>"><?= (bool) ($parityContent['in_sync'] ?? false) ? 'OK' : 'Pendente' ?></strong></div>
          <div><span>Codigo</span><strong class="<?= (bool) ($parityCode['in_sync'] ?? false) ? 'text-emerald-300' : 'text-amber-300' ?>"><?= (bool) ($parityCode['in_sync'] ?? false) ? 'OK' : 'Pendente' ?></strong></div>
        </div>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Aba dedicada</p>
            <h2 class="mt-2 font-orbitron text-xl font-bold text-white">Backup e Restore</h2>
            <p class="mt-3 text-sm leading-7 text-slate-400">Concentra os backups de dados, backups tecnicos, verificacoes, restore de banco/uploads e rollback tecnico. Nada disso precisa ficar repetido na visao geral.</p>
          </div>
          <a href="<?= htmlspecialchars($backupTabUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20">Abrir aba</a>
        </div>

        <div class="mt-5 grid gap-4 md:grid-cols-2">
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-slate-400">Ultimo backup de dados</p>
            <div class="mt-3 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars((string) ($latestDataBackup['backup_id'] ?? 'Pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div class="mt-2 space-y-2 text-sm">
              <?= $renderMetaLine('Validade', strip_tags($statusBadge((bool) ($latestDataBackup['is_valid'] ?? false), 'OK', 'Falhou'))) ?>
              <?= $renderMetaLine('Perfil', (string) ($latestDataBackup['profile_label'] ?? '-')) ?>
            </div>
          </div>

          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-slate-400">Ultimos tecnicos</p>
            <div class="mt-3 space-y-2 text-sm">
              <?= $renderMetaLine('Local', (string) ($latestTechnicalLocal['backup_id'] ?? 'Pendente')) ?>
              <?= $renderMetaLine('Stage', (string) ($latestTechnicalStage['backup_id'] ?? 'Pendente')) ?>
              <?= $renderMetaLine('Producao', (string) ($latestTechnicalProduction['backup_id'] ?? 'Pendente')) ?>
            </div>
          </div>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Aba dedicada</p>
            <h2 class="mt-2 font-orbitron text-xl font-bold text-white">Conteudo</h2>
            <p class="mt-3 text-sm leading-7 text-slate-400">Concentra exportacao de pacotes, verificacao, publicacao em stage e producao, deploy tecnico e acompanhamento de paridade. A visao geral so resume o estado atual.</p>
          </div>
          <a href="<?= htmlspecialchars($contentTabUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20">Abrir aba</a>
        </div>

        <div class="mt-5 grid gap-4 md:grid-cols-2">
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-slate-400">Conteudo editorial</p>
            <div class="mt-3 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars((string) ($latestContentPackage['package_id'] ?? 'Pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div class="mt-2 space-y-2 text-sm">
              <?= $renderMetaLine('Stage', (string) ($latestStageContentApply['package_id'] ?? 'Sem aplicacao')) ?>
              <?= $renderMetaLine('Producao', (string) ($latestProductionContentApply['package_id'] ?? 'Sem aplicacao')) ?>
            </div>
          </div>

          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-slate-400">Codigo tecnico</p>
            <div class="mt-3 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars((string) ($latestCodePackage['package_id'] ?? 'Pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div class="mt-2 space-y-2 text-sm">
              <?= $renderMetaLine('Stage', (string) ($latestStageCodeApply['package_id'] ?? 'Sem aplicacao')) ?>
              <?= $renderMetaLine('Producao', (string) ($latestProductionCodeApply['package_id'] ?? 'Sem aplicacao')) ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <div class="flex items-center justify-between gap-4">
          <h2 class="font-orbitron text-lg font-bold text-white">Historico rapido do sistema</h2>
          <span class="rounded-full border border-slate-700 bg-slate-950/80 px-3 py-1 text-xs uppercase tracking-[0.2em] text-slate-400"><?= count($recentSystemLogs) ?> eventos</span>
        </div>

        <?php if ($recentSystemLogs === []): ?>
          <p class="mt-4 text-sm text-slate-400">Nenhum evento recente registrado no canal de sistema.</p>
        <?php else: ?>
          <div class="mt-5 space-y-3">
            <?php foreach ($recentSystemLogs as $entry): ?>
              <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                <div class="flex items-center justify-between gap-4">
                  <p class="font-rajdhani text-lg font-bold text-white"><?= htmlspecialchars((string) ($entry['event'] ?? 'evento'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                  <span class="text-xs uppercase tracking-[0.2em] text-slate-500"><?= htmlspecialchars((string) ($entry['created_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                </div>
                <p class="mt-2 text-sm text-slate-400"><?= htmlspecialchars((string) (($entry['context_summary'] ?? $entry['summary'] ?? 'Sem resumo adicional.')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <div class="flex items-center justify-between gap-4">
          <h2 class="font-orbitron text-lg font-bold text-white">Historico rapido de operacoes</h2>
          <span class="rounded-full border border-slate-700 bg-slate-950/80 px-3 py-1 text-xs uppercase tracking-[0.2em] text-slate-400"><?= count($recentOperationsLogs) ?> eventos</span>
        </div>

        <?php if ($recentOperationsLogs === []): ?>
          <p class="mt-4 text-sm text-slate-400">Nenhuma operacao recente registrada.</p>
        <?php else: ?>
          <div class="mt-5 space-y-3">
            <?php foreach ($recentOperationsLogs as $entry): ?>
              <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                <div class="flex items-center justify-between gap-4">
                  <p class="font-rajdhani text-lg font-bold text-white"><?= htmlspecialchars((string) ($entry['event'] ?? 'operacao'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                  <span class="text-xs uppercase tracking-[0.2em] text-slate-500"><?= htmlspecialchars((string) ($entry['created_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                </div>
                <p class="mt-2 text-sm text-slate-400"><?= htmlspecialchars((string) (($entry['context_summary'] ?? $entry['summary'] ?? 'Sem resumo adicional.')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</section>
