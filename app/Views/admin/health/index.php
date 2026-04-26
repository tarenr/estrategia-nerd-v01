<?php
declare(strict_types=1);

$checks = $checks ?? [];
$summary = $summary ?? ['total' => 0, 'ok' => 0, 'warn' => 0, 'fail' => 0, 'score' => 0];
$runtime = $runtime ?? [];
$targetEnvironment = (string) ($target_environment ?? current_environment());
$targetEnvironmentLabel = (string) ($target_environment_label ?? environment_label($targetEnvironment));
$executionEnvironmentLabel = (string) ($execution_environment_label ?? environment_label(current_environment()));
$isRemoteTarget = (bool) ($is_remote_target ?? false);

$statusCardClass = static function (string $status): string {
    return match ($status) {
        'ok' => 'border-emerald-500/25 bg-emerald-500/10 text-emerald-200',
        'warn' => 'border-amber-500/25 bg-amber-500/10 text-amber-200',
        default => 'border-rose-500/25 bg-rose-500/10 text-rose-200',
    };
};
?>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Health Check</h1>
      <div class="admin-page-subtitle">
        <?= $isRemoteTarget
            ? 'Diagnostico remoto em modo leitura, centralizado no local para validar banco e storage do ambiente alvo.'
            : 'Diagnostico tecnico do painel, ambiente PHP, banco e estrutura de arquivos antes da publicacao.' ?>
      </div>
    </div>
    <div class="admin-page-actions">
      <a href="<?= htmlspecialchars(url('/admin/health'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="admin-btn admin-btn-secondary">Atualizar leitura</a>
    </div>
  </div>

  <section class="admin-panel">
    <div class="flex flex-wrap items-center gap-3">
      <span class="inline-flex items-center gap-2 rounded-full border border-slate-700 bg-slate-900/70 px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-slate-200">
        <span class="text-slate-400">Execucao</span>
        <span><?= htmlspecialchars($executionEnvironmentLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
      </span>
      <span class="inline-flex items-center gap-2 rounded-full border border-cyan-500/25 bg-cyan-500/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-cyan-100">
        <span class="text-cyan-300">Alvo</span>
        <span><?= htmlspecialchars($targetEnvironmentLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
      </span>
      <?php if ($isRemoteTarget): ?>
        <span class="inline-flex items-center gap-2 rounded-full border border-amber-500/25 bg-amber-500/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-amber-100">
          <span>Somente leitura</span>
        </span>
      <?php endif; ?>
    </div>
    <div class="mt-3 text-sm text-slate-400">
      <?= $isRemoteTarget
          ? 'A leitura abaixo consulta o banco e o perfil de storage do alvo selecionado, sem alterar dados e sem executar escrita remota.'
          : 'Leitura completa do ambiente local, incluindo verificacoes de escrita em sessao e diretorios do projeto.' ?>
    </div>
  </section>

  <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
    <article class="admin-panel">
      <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Score geral</div>
      <div class="mt-3 font-orbitron text-4xl font-black text-white"><?= (int) ($summary['score'] ?? 0) ?>%</div>
      <div class="mt-2 text-sm text-slate-400">Leitura geral do ambiente com base nos checks validados.</div>
    </article>
    <article class="admin-panel">
      <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Checks OK</div>
      <div class="mt-3 font-orbitron text-4xl font-black text-emerald-300"><?= (int) ($summary['ok'] ?? 0) ?></div>
      <div class="mt-2 text-sm text-slate-400">Itens operacionais sem alerta ou bloqueio.</div>
    </article>
    <article class="admin-panel">
      <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Alertas</div>
      <div class="mt-3 font-orbitron text-4xl font-black text-amber-300"><?= (int) ($summary['warn'] ?? 0) ?></div>
      <div class="mt-2 text-sm text-slate-400">Pontos que merecem revisao antes de subir o portal.</div>
    </article>
    <article class="admin-panel">
      <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Falhas</div>
      <div class="mt-3 font-orbitron text-4xl font-black text-rose-300"><?= (int) ($summary['fail'] ?? 0) ?></div>
      <div class="mt-2 text-sm text-slate-400">Itens que podem bloquear partes do sistema.</div>
    </article>
  </section>

  <section class="admin-panel">
    <div class="flex items-center justify-between gap-4 flex-wrap mb-5">
      <div>
        <h2 class="font-orbitron text-lg font-black text-white">Runtime rapido</h2>
        <div class="text-xs text-slate-400 mt-1">
          <?= $isRemoteTarget
              ? 'Leitura consolidada do local com o alvo remoto selecionado.'
              : 'Leitura direta do ambiente atual do projeto.' ?>
        </div>
      </div>
      <div class="admin-chip">Total de checks: <?= (int) ($summary['total'] ?? 0) ?></div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 text-sm">
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
        <div class="text-slate-400">PHP</div>
        <div class="mt-2 font-bold text-white"><?= htmlspecialchars((string) ($runtime['php_version'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </div>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
        <div class="text-slate-400">APP URL</div>
        <div class="mt-2 font-bold text-white break-all"><?= htmlspecialchars((string) ($runtime['app_url'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </div>
      <?php if ($isRemoteTarget): ?>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
          <div class="text-slate-400">Banco alvo</div>
          <div class="mt-2 font-bold text-white break-all">
            <?= htmlspecialchars((string) (($runtime['target_database_host'] ?? '-') . ' / ' . ($runtime['target_database_name'] ?? '-')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
          </div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
          <div class="text-slate-400">Uploads alvo</div>
          <div class="mt-2 font-bold text-white break-all"><?= htmlspecialchars((string) ($runtime['target_uploads_mode'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="mt-2 text-xs text-slate-400 break-all"><?= htmlspecialchars((string) ($runtime['target_uploads_root'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </div>
      <?php else: ?>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
          <div class="text-slate-400">Raiz do projeto</div>
          <div class="mt-2 font-bold text-white break-all"><?= htmlspecialchars((string) ($runtime['base_path'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
          <div class="text-slate-400">Pasta de sessao</div>
          <div class="mt-2 font-bold text-white break-all"><?= htmlspecialchars((string) ($runtime['session_path'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php foreach ($checks as $groupLabel => $groupChecks): ?>
    <section class="admin-panel">
      <div class="flex items-center justify-between gap-4 flex-wrap mb-5">
        <div>
          <h2 class="font-orbitron text-lg font-black text-white"><?= htmlspecialchars(ucfirst((string) $groupLabel), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
          <div class="text-xs text-slate-400 mt-1">Verificacoes desta area do ambiente e do admin.</div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <?php foreach ($groupChecks as $item): ?>
          <?php $status = (string) ($item['status'] ?? 'warn'); ?>
          <article class="rounded-2xl border p-4 <?= $statusCardClass($status) ?>">
            <div class="flex items-center justify-between gap-3">
              <div class="font-bold text-white"><?= htmlspecialchars((string) ($item['label'] ?? 'Check'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em] <?= $status === 'ok' ? 'bg-emerald-500/20 text-emerald-100' : ($status === 'warn' ? 'bg-amber-500/20 text-amber-100' : 'bg-rose-500/20 text-rose-100') ?>">
                <?= htmlspecialchars(strtoupper($status), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
              </span>
            </div>
            <div class="mt-3 text-sm font-bold text-white break-all"><?= htmlspecialchars((string) ($item['value'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div class="mt-2 text-sm leading-6 text-slate-300"><?= htmlspecialchars((string) ($item['detail'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>
