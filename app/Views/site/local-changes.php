<?php

declare(strict_types=1);

$embedMode = (bool) ($embed_mode ?? false);
$adminEmbed = (bool) ($admin_embed ?? false);
$projectVersion = trim((string) ($project_version ?? 'local'));
$generatedAt = trim((string) ($generated_at ?? date('Y-m-d H:i:s')));
$featureDocs = is_array($feature_docs ?? null) ? $feature_docs : [];
$releaseDocs = is_array($release_docs ?? null) ? $release_docs : [];
$changeDocs = is_array($change_docs ?? null) ? $change_docs : [];
$selectedChangeDoc = is_array($selected_change_doc ?? null) ? $selected_change_doc : null;
$changeDocBaseUrl = trim((string) ($change_doc_base_url ?? url('/local/mudancas/documento?grupo=')));
$changeDocDataBaseUrl = trim((string) ($change_doc_data_base_url ?? url('/local/mudancas/documento-dados?grupo=')));
$activityLogs = is_array($activity_logs ?? null) ? $activity_logs : [];
$operationLogs = is_array($operation_logs ?? null) ? $operation_logs : [];

$activityEventMeta = [
    'general_audit_ran' => [
        'label' => 'Auditoria geral executada',
        'accent' => 'text-cyan-200',
    ],
    'content_sync_production_to_stage_succeeded' => [
        'label' => 'Sincronizacao producao -> stage concluida',
        'accent' => 'text-emerald-200',
    ],
    'content_sync_production_to_stage_failed' => [
        'label' => 'Sincronizacao producao -> stage falhou',
        'accent' => 'text-rose-200',
    ],
];

$formatSystemEvent = static function (array $item) use ($activityEventMeta): array {
    $eventKey = (string) ($item['event'] ?? 'evento');
    $context = is_array($item['context'] ?? null) ? $item['context'] : [];
    $meta = $activityEventMeta[$eventKey] ?? [
        'label' => ucwords(str_replace('_', ' ', $eventKey)),
        'accent' => 'text-cyan-200',
    ];

    $summary = 'Evento tecnico registrado no sistema.';

    if ($eventKey === 'general_audit_ran') {
        $overallStatus = strtoupper((string) ($context['overall_status'] ?? 'ok'));
        $critical = (int) ($context['critical_findings'] ?? 0);
        $warning = (int) ($context['warning_findings'] ?? 0);
        $duration = (int) ($context['duration_ms'] ?? 0);
        $summary = sprintf(
            'Status %s, %d criticos, %d alertas, %0.2fs.',
            $overallStatus,
            $critical,
            $warning,
            $duration > 0 ? ($duration / 1000) : 0
        );
    } elseif ($eventKey === 'content_sync_production_to_stage_succeeded') {
        $packageId = (string) ($context['package_id'] ?? '-');
        $backupId = (string) ($context['pre_apply_backup_id'] ?? '-');
        $posts = (int) ($context['verification']['stats']['posts'] ?? 0);
        $uploads = (int) ($context['verification']['uploads_included'] ?? 0);
        $summary = sprintf(
            'Pacote %s aplicado na stage com backup %s, %d posts e %d uploads.',
            $packageId,
            $backupId,
            $posts,
            $uploads
        );
    } elseif ($eventKey === 'content_sync_production_to_stage_failed') {
        $operationId = (string) ($context['operation_id'] ?? '-');
        $summary = sprintf('Falha registrada na sincronizacao da operacao %s.', $operationId);
    }

    return [
        'label' => (string) $meta['label'],
        'accent' => (string) $meta['accent'],
        'summary' => $summary,
        'context_json' => json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
    ];
};
?>
<section class="<?= $adminEmbed ? 'text-slate-100' : 'min-h-screen bg-slate-950 px-4 py-8 text-slate-100' ?>">
  <style>
    .doc-card { border: 1px solid rgba(51, 65, 85, 0.9); background: rgba(2, 6, 23, 0.65); border-radius: 1rem; padding: 1rem; }
    .doc-label { font-family: Orbitron, ui-sans-serif, system-ui; font-size: 0.66rem; letter-spacing: 0.2em; text-transform: uppercase; color: rgba(148, 163, 184, 0.95); }
    .doc-table { min-width: 100%; border-collapse: separate; border-spacing: 0 0.55rem; font-size: 0.84rem; }
    .doc-table th { text-align: left; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.18em; color: rgba(100, 116, 139, 1); padding: 0.5rem 0.75rem; }
    .doc-table td { padding: 0.72rem 0.75rem; vertical-align: top; }
    .doc-row { border: 1px solid rgba(51, 65, 85, 0.95); background: rgba(2, 6, 23, 0.72); }
    .doc-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    .doc-pre {
      max-width: 100%;
      overflow-x: auto;
      white-space: pre-wrap;
      overflow-wrap: anywhere;
      word-break: break-word;
      font-size: 0.84rem;
      line-height: 1.75;
      color: rgba(226, 232, 240, 0.96);
    }
    .changes-col-file { width: 16%; }
    .changes-col-updated { width: 20%; }
    .changes-col-path { width: 56%; }
    .changes-col-open { width: 8%; }
    .doc-context-toggle[open] summary i { transform: rotate(180deg); }
    .changes-tab-panel[hidden] { display: none; }
    .changes-tab-button.is-active {
      border-color: rgba(34, 211, 238, 0.65);
      background: rgba(8, 145, 178, 0.18);
      color: #e0faff;
      box-shadow: 0 0 24px rgba(6, 182, 212, 0.12);
    }
    .activity-card {
      border: 1px solid rgba(51, 65, 85, 0.95);
      background: rgba(2, 6, 23, 0.72);
      border-radius: 1.25rem;
      padding: 1rem;
    }
    .activity-kpi {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      border-radius: 9999px;
      border: 1px solid rgba(71, 85, 105, 0.8);
      background: rgba(15, 23, 42, 0.88);
      padding: 0.22rem 0.65rem;
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      color: rgba(226, 232, 240, 0.96);
      white-space: nowrap;
    }
    .activity-kpi-ok {
      border-color: rgba(52, 211, 153, 0.45);
      color: rgba(167, 243, 208, 0.96);
      background: rgba(6, 78, 59, 0.28);
    }
    .activity-kpi-warn {
      border-color: rgba(251, 191, 36, 0.45);
      color: rgba(253, 230, 138, 0.98);
      background: rgba(120, 53, 15, 0.28);
    }
    .activity-kpi-error {
      border-color: rgba(251, 113, 133, 0.45);
      color: rgba(254, 205, 211, 0.98);
      background: rgba(127, 29, 29, 0.28);
    }
    .change-doc-panel[hidden] { display: none; }
    .change-doc-panel {
      overflow: hidden;
    }
  </style>

  <div class="<?= $adminEmbed ? 'space-y-6' : 'mx-auto max-w-7xl space-y-6' ?>">
    <?php if (!$embedMode): ?>
      <?php \App\Support\View::component('site/local-tools-nav', ['active' => 'docs']); ?>
    <?php endif; ?>

    <header class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Historico Local</p>
      <h1 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Mudancas recentes do projeto</h1>
      <p class="mt-3 max-w-4xl text-sm leading-7 text-slate-300">
        Painel local para visualizar entregas recentes, operacoes executadas e atividade tecnica do sistema em um unico lugar.
      </p>
      <div class="mt-5 grid gap-3 md:grid-cols-4">
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Versao</p>
          <p class="mt-1 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars($projectVersion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        </div>
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Atualizado em</p>
          <p class="mt-1 text-sm text-slate-200"><?= htmlspecialchars($generatedAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        </div>
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Features recentes</p>
          <p class="mt-1 font-rajdhani text-2xl font-bold text-white"><?= count($featureDocs) ?></p>
        </div>
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Mudancas registradas</p>
          <p class="mt-1 font-rajdhani text-2xl font-bold text-white"><?= count($changeDocs) ?></p>
        </div>
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Logs operacionais</p>
          <p class="mt-1 font-rajdhani text-2xl font-bold text-white"><?= count($operationLogs) ?></p>
        </div>
      </div>
    </header>

    <?php
      $closeUrl = $adminEmbed
          ? url('/admin/base-tecnica?aba=mudancas')
          : url('/local/mudancas');
    ?>
      <section id="change-doc-panel" class="change-doc-panel rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_32px_rgba(6,182,212,0.08)]" <?= $selectedChangeDoc === null ? 'hidden' : '' ?>>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Documento aberto</p>
            <h2 id="change-doc-title" class="mt-2 font-orbitron text-2xl font-black tracking-tight text-white"><?= htmlspecialchars((string) ($selectedChangeDoc['file'] ?? 'arquivo.md'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
            <p class="mt-3 max-w-4xl text-sm leading-7 text-slate-300">Leitura do arquivo diretamente dentro da aba <span class="font-semibold text-white">Mudancas</span>, sem sair do admin.</p>
          </div>
          <a id="change-doc-close" href="<?= htmlspecialchars($closeUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20">
            Fechar leitura
          </a>
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-3">
          <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Grupo</p>
            <p id="change-doc-group" class="mt-1 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars((string) ($selectedChangeDoc['group'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
          </div>
          <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Atualizado em</p>
            <p id="change-doc-updated" class="mt-1 text-sm text-slate-200"><?= htmlspecialchars((string) ($selectedChangeDoc['updated_at'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
          </div>
          <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Caminho</p>
            <p id="change-doc-path" class="mt-1 text-sm text-slate-200 doc-mono"><?= htmlspecialchars((string) ($selectedChangeDoc['path'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
          </div>
        </div>

        <div class="mt-5 doc-card">
          <pre id="change-doc-body" class="doc-pre"><?= htmlspecialchars((string) ($selectedChangeDoc['body'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
        </div>
      </section>

    <div class="grid gap-6 xl:grid-cols-2">
      <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-xl font-bold text-cyan-200">Features recentes</h2>
        <div class="mt-4 overflow-x-auto">
          <table class="doc-table">
            <colgroup>
              <col class="changes-col-file">
              <col class="changes-col-updated">
              <col class="changes-col-path">
              <col class="changes-col-open">
            </colgroup>
            <thead>
              <tr><th>Arquivo</th><th>Atualizado em</th><th>Caminho</th><th>Abrir</th></tr>
            </thead>
            <tbody>
              <?php if ($featureDocs === []): ?>
                <tr class="doc-row"><td colspan="4" class="text-slate-400">Nenhum documento de feature encontrado ainda.</td></tr>
              <?php else: ?>
                <?php foreach ($featureDocs as $item): ?>
                  <tr class="doc-row">
                    <td class="text-white doc-mono"><?= htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td class="text-slate-300"><?= htmlspecialchars((string) ($item['updated_at'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td class="text-slate-400 doc-mono"><?= htmlspecialchars((string) ($item['path'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td>
                      <a
                        href="<?= htmlspecialchars($changeDocBaseUrl . 'features&arquivo=' . rawurlencode((string) ($item['name'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                        data-doc-link="true"
                        data-doc-data-url="<?= htmlspecialchars($changeDocDataBaseUrl . 'features&arquivo=' . rawurlencode((string) ($item['name'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-cyan-400/40 bg-cyan-500/10 text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20"
                        title="Abrir arquivo"
                        aria-label="Abrir arquivo"
                      >
                        <i class="fa-solid fa-arrow-up-right-from-square text-sm" aria-hidden="true"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-xl font-bold text-cyan-200">Releases recentes</h2>
        <div class="mt-4 overflow-x-auto">
          <table class="doc-table">
            <colgroup>
              <col class="changes-col-file">
              <col class="changes-col-updated">
              <col class="changes-col-path">
              <col class="changes-col-open">
            </colgroup>
            <thead>
              <tr><th>Arquivo</th><th>Atualizado em</th><th>Caminho</th><th>Abrir</th></tr>
            </thead>
            <tbody>
              <?php if ($releaseDocs === []): ?>
                <tr class="doc-row"><td colspan="4" class="text-slate-400">Nenhum documento de release encontrado ainda.</td></tr>
              <?php else: ?>
                <?php foreach ($releaseDocs as $item): ?>
                  <tr class="doc-row">
                    <td class="text-white doc-mono"><?= htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td class="text-slate-300"><?= htmlspecialchars((string) ($item['updated_at'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td class="text-slate-400 doc-mono"><?= htmlspecialchars((string) ($item['path'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td>
                      <a
                        href="<?= htmlspecialchars($changeDocBaseUrl . 'releases&arquivo=' . rawurlencode((string) ($item['name'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                        data-doc-link="true"
                        data-doc-data-url="<?= htmlspecialchars($changeDocDataBaseUrl . 'releases&arquivo=' . rawurlencode((string) ($item['name'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-cyan-400/40 bg-cyan-500/10 text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20"
                        title="Abrir arquivo"
                        aria-label="Abrir arquivo"
                      >
                        <i class="fa-solid fa-arrow-up-right-from-square text-sm" aria-hidden="true"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">Registro completo de mudancas</h2>
          <p class="mt-2 text-sm leading-7 text-slate-400">Aqui ficam todas as features e releases documentadas na governanca oficial do projeto, ordenadas pela atualizacao mais recente.</p>
        </div>
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-3 text-sm text-slate-300">
          <span class="text-slate-500">Total listado:</span>
          <span class="ml-2 font-semibold text-white"><?= count($changeDocs) ?></span>
        </div>
      </div>

      <div class="mt-4 overflow-x-auto">
        <table class="doc-table">
          <colgroup>
            <col style="width: 12%;">
            <col class="changes-col-file">
            <col class="changes-col-updated">
            <col style="width: 44%;">
            <col class="changes-col-open">
          </colgroup>
          <thead>
            <tr><th>Tipo</th><th>Arquivo</th><th>Atualizado em</th><th>Caminho</th><th>Abrir</th></tr>
          </thead>
          <tbody>
            <?php if ($changeDocs === []): ?>
              <tr class="doc-row"><td colspan="5" class="text-slate-400">Nenhuma mudanca documentada ainda.</td></tr>
            <?php else: ?>
              <?php foreach ($changeDocs as $item): ?>
                <tr class="doc-row">
                  <td>
                    <span class="rounded-full border border-slate-700 bg-slate-950/80 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-300">
                      <?= htmlspecialchars((string) ($item['type'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </span>
                  </td>
                  <td class="text-white doc-mono"><?= htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                  <td class="text-slate-300"><?= htmlspecialchars((string) ($item['updated_at'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                  <td class="text-slate-400 doc-mono"><?= htmlspecialchars((string) ($item['path'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                  <td>
                    <?php $group = strtolower((string) ($item['type'] ?? '')) === 'release' ? 'releases' : 'features'; ?>
                    <a
                      href="<?= htmlspecialchars($changeDocBaseUrl . $group . '&arquivo=' . rawurlencode((string) ($item['name'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                      data-doc-link="true"
                      data-doc-data-url="<?= htmlspecialchars($changeDocDataBaseUrl . $group . '&arquivo=' . rawurlencode((string) ($item['name'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-cyan-400/40 bg-cyan-500/10 text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20"
                      title="Abrir arquivo"
                      aria-label="Abrir arquivo"
                    >
                      <i class="fa-solid fa-arrow-up-right-from-square text-sm" aria-hidden="true"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">Historico tecnico detalhado</h2>
          <p class="mt-2 text-sm leading-7 text-slate-400">Use as abas para alternar entre eventos do sistema e operacoes executadas, com mais espaco horizontal para leitura.</p>
        </div>
        <div class="grid gap-2 sm:grid-cols-2">
          <button type="button" class="changes-tab-button is-active rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-3 text-left font-orbitron text-xs uppercase tracking-[0.2em] text-slate-300 transition hover:border-cyan-400/50 hover:text-white" data-changes-tab="atividade">Atividade do sistema</button>
          <button type="button" class="changes-tab-button rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-3 text-left font-orbitron text-xs uppercase tracking-[0.2em] text-slate-300 transition hover:border-cyan-400/50 hover:text-white" data-changes-tab="operacoes">Operacoes recentes</button>
        </div>
      </div>

      <div class="changes-tab-panel mt-5" data-changes-panel="atividade">
        <h2 class="font-orbitron text-xl font-bold text-cyan-200">Atividade do sistema</h2>
        <div class="mt-4 space-y-4">
          <?php if ($activityLogs === []): ?>
            <div class="activity-card text-slate-400">Nenhum log de atividade encontrado.</div>
          <?php else: ?>
            <?php foreach ($activityLogs as $item): ?>
              <?php
                $eventDisplay = $formatSystemEvent($item);
                $context = is_array($item['context'] ?? null) ? $item['context'] : [];
                $timestampRaw = (string) ($item['timestamp'] ?? '');
                $timestampDate = $timestampRaw !== '' ? date('d/m/Y', strtotime($timestampRaw)) : '-';
                $timestampTime = $timestampRaw !== '' ? date('H:i:s', strtotime($timestampRaw)) : '-';
                $overallStatus = strtolower((string) ($context['overall_status'] ?? ''));
                $criticalCount = (int) ($context['critical_findings'] ?? 0);
                $warningCount = (int) ($context['warning_findings'] ?? 0);
                $durationMs = (int) ($context['duration_ms'] ?? 0);
                $durationLabel = $durationMs > 0 ? number_format($durationMs / 1000, 2, '.', '') . 's' : null;
                $statusClass = match ($overallStatus) {
                    'ok' => 'activity-kpi-ok',
                    'warn' => 'activity-kpi-warn',
                    'fail', 'error' => 'activity-kpi-error',
                    default => '',
                };
              ?>
              <article class="activity-card">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                  <div class="min-w-0 flex-1 space-y-3">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                      <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-3">
                          <h3 class="<?= htmlspecialchars($eventDisplay['accent'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> font-orbitron text-base font-bold">
                            <?= htmlspecialchars($eventDisplay['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                          </h3>
                          <span class="rounded-full border border-slate-700 bg-slate-950/80 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-300">
                            <?= htmlspecialchars((string) ($item['channel'] ?? 'system'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                          </span>
                        </div>
                        <div class="mt-2 text-xs text-slate-500 doc-mono">
                          <?= htmlspecialchars((string) ($item['event'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </div>
                      </div>

                      <div class="shrink-0 rounded-2xl border border-slate-800 bg-slate-950/80 px-4 py-3 text-right">
                        <div class="text-sm font-semibold text-white"><?= htmlspecialchars($timestampDate, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                        <div class="mt-1 text-xs uppercase tracking-[0.14em] text-slate-500"><?= htmlspecialchars($timestampTime, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                      </div>
                    </div>

                    <p class="text-sm leading-7 text-slate-300"><?= htmlspecialchars($eventDisplay['summary'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>

                    <div class="flex flex-wrap gap-2">
                      <?php if ($overallStatus !== ''): ?>
                        <span class="activity-kpi <?= htmlspecialchars($statusClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                          Status <?= htmlspecialchars(strtoupper($overallStatus), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </span>
                      <?php endif; ?>
                      <?php if (array_key_exists('critical_findings', $context)): ?>
                        <span class="activity-kpi <?= $criticalCount > 0 ? 'activity-kpi-error' : 'activity-kpi-ok' ?>">
                          <?= $criticalCount ?> criticos
                        </span>
                      <?php endif; ?>
                      <?php if (array_key_exists('warning_findings', $context)): ?>
                        <span class="activity-kpi <?= $warningCount > 0 ? 'activity-kpi-warn' : 'activity-kpi-ok' ?>">
                          <?= $warningCount ?> alertas
                        </span>
                      <?php endif; ?>
                      <?php if ($durationLabel !== null): ?>
                        <span class="activity-kpi"><?= htmlspecialchars($durationLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                      <?php endif; ?>
                      <?php if (!empty($context['target_environment'])): ?>
                        <span class="activity-kpi">alvo <?= htmlspecialchars((string) $context['target_environment'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>

                <details class="doc-context-toggle mt-4">
                  <summary class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-700 bg-slate-950/80 px-3 py-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-300 hover:border-cyan-400/50 hover:text-cyan-200">
                    Ver contexto tecnico
                    <i class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200" aria-hidden="true"></i>
                  </summary>
                  <pre class="mt-3 overflow-x-auto rounded-2xl border border-slate-800 bg-slate-950/80 p-3 text-[11px] leading-6 text-slate-400 doc-mono"><?= htmlspecialchars($eventDisplay['context_json'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
                </details>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="changes-tab-panel mt-5" data-changes-panel="operacoes" hidden>
        <h2 class="font-orbitron text-xl font-bold text-cyan-200">Operacoes recentes</h2>
        <div class="mt-4 overflow-x-auto">
          <table class="doc-table">
            <thead>
              <tr><th>Quando</th><th>Tipo</th><th>Status</th><th>Resumo</th></tr>
            </thead>
            <tbody>
              <?php if ($operationLogs === []): ?>
                <tr class="doc-row"><td colspan="4" class="text-slate-400">Nenhum log operacional encontrado.</td></tr>
              <?php else: ?>
                <?php foreach ($operationLogs as $item): ?>
                  <tr class="doc-row">
                    <td class="text-slate-300"><?= htmlspecialchars((string) ($item['timestamp'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td class="text-white doc-mono"><?= htmlspecialchars((string) ($item['type'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td class="text-cyan-200"><?= htmlspecialchars((string) ($item['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    <td class="text-slate-400">
                      <?= htmlspecialchars((string) ($item['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                      <div class="mt-1 text-xs doc-mono text-slate-500">
                        origem=<?= htmlspecialchars((string) ($item['origin'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        destino=<?= htmlspecialchars((string) ($item['destination'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        id=<?= htmlspecialchars((string) ($item['id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>

  <script>
    (() => {
      const buttons = Array.from(document.querySelectorAll('[data-changes-tab]'));
      const panels = Array.from(document.querySelectorAll('[data-changes-panel]'));

      if (buttons.length === 0 || panels.length === 0) {
        return;
      }

      const activate = (target) => {
        buttons.forEach((button) => {
          button.classList.toggle('is-active', button.dataset.changesTab === target);
        });

        panels.forEach((panel) => {
          panel.hidden = panel.dataset.changesPanel !== target;
        });
      };

      buttons.forEach((button) => {
        button.addEventListener('click', () => {
          activate(button.dataset.changesTab || 'atividade');
        });
      });
    })();

    (() => {
      const panel = document.getElementById('change-doc-panel');
      const closeLink = document.getElementById('change-doc-close');
      const title = document.getElementById('change-doc-title');
      const group = document.getElementById('change-doc-group');
      const updated = document.getElementById('change-doc-updated');
      const path = document.getElementById('change-doc-path');
      const body = document.getElementById('change-doc-body');

      if (!panel || !closeLink || !title || !group || !updated || !path || !body) {
        return;
      }

      const baseUrl = <?= json_encode($closeUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

      const openDocument = (payload, nextUrl) => {
        title.textContent = payload.file || 'arquivo.md';
        group.textContent = payload.group || '';
        updated.textContent = payload.updated_at || '';
        path.textContent = payload.path || '';
        body.textContent = payload.body || '';
        panel.hidden = false;
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        window.history.pushState({ changeDocOpen: true }, '', nextUrl);
      };

      const closeDocument = (push = true) => {
        panel.hidden = true;
        if (push) {
          window.history.pushState({ changeDocOpen: false }, '', baseUrl);
        }
      };

      for (const link of document.querySelectorAll('[data-doc-link="true"]')) {
        link.addEventListener('click', async (event) => {
          event.preventDefault();
          const nextUrl = link.getAttribute('href') || '';
          const dataUrl = link.getAttribute('data-doc-data-url') || '';

          if (dataUrl === '') {
            window.location.href = nextUrl;
            return;
          }

          try {
            const response = await fetch(dataUrl, {
              headers: {
                'X-Requested-With': 'XMLHttpRequest'
              }
            });

            if (!response.ok) {
              window.location.href = nextUrl;
              return;
            }

            const payload = await response.json();
            openDocument(payload, nextUrl);
          } catch (error) {
            window.location.href = nextUrl;
          }
        });
      }

      closeLink.addEventListener('click', (event) => {
        event.preventDefault();
        closeDocument(true);
      });

      window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        const hasDocParams = params.get('grupo') && params.get('arquivo');

        if (hasDocParams) {
          window.location.reload();
          return;
        }

        closeDocument(false);
      });
    })();
  </script>
</section>
