<?php

declare(strict_types=1);

use App\Support\Csrf;

$flash = is_array($flash ?? null) ? $flash : null;
$operationsStatus = (array) ($operations_status ?? []);
$policy = (array) ($operationsStatus['policy'] ?? []);
$backup = (array) ($operationsStatus['backup'] ?? []);
$content = (array) ($operationsStatus['content'] ?? []);
$contentPackages = is_array($content['items'] ?? null) ? $content['items'] : [];
$code = (array) ($operationsStatus['code'] ?? []);
$technicalBackup = (array) ($operationsStatus['technical_backup'] ?? []);
$technicalProfiles = (array) ($technicalBackup['profiles'] ?? []);
$technicalLocalList = is_array($technicalProfiles['local'] ?? null) ? $technicalProfiles['local'] : [];
$technicalStageList = is_array($technicalProfiles['stage'] ?? null) ? $technicalProfiles['stage'] : [];
$technicalProductionList = is_array($technicalProfiles['production'] ?? null) ? $technicalProfiles['production'] : [];
$logs = (array) ($operationsStatus['logs'] ?? []);
$logCategories = is_array($logs['categories'] ?? null) ? $logs['categories'] : [];
$parity = (array) ($operationsStatus['parity'] ?? []);
$parityContent = (array) ($parity['content'] ?? []);
$parityCode = (array) ($parity['code'] ?? []);
$productionGateOpen = (bool) ($policy['production_allowed'] ?? false);
$alertClasses = [
    'success' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100',
    'error' => 'border-rose-500/40 bg-rose-500/10 text-rose-100',
];
$flashClass = $flash !== null ? ($alertClasses[$flash['type']] ?? $alertClasses['success']) : '';
?>
<section class="min-h-screen bg-slate-950 px-4 py-8 text-slate-100">
  <style>
    .operations-progress-overlay {
      position: fixed;
      inset: 0;
      z-index: 9999;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(2, 6, 23, 0.84);
      backdrop-filter: blur(12px);
    }

    .operations-progress-overlay.is-visible {
      display: flex;
    }

    .operations-progress-card {
      width: min(92vw, 34rem);
      border-radius: 1.75rem;
      border: 1px solid rgba(34, 211, 238, 0.25);
      background: linear-gradient(180deg, rgba(15, 23, 42, 0.96), rgba(2, 6, 23, 0.96));
      padding: 1.5rem;
      box-shadow: 0 0 40px rgba(6, 182, 212, 0.12);
    }

    .operations-progress-bar {
      height: 0.8rem;
      overflow: hidden;
      border-radius: 999px;
      background: rgba(30, 41, 59, 0.9);
      border: 1px solid rgba(51, 65, 85, 0.8);
    }

    .operations-progress-fill {
      height: 100%;
      width: 8%;
      border-radius: inherit;
      background: linear-gradient(90deg, #22d3ee, #60a5fa, #c084fc);
      box-shadow: 0 0 24px rgba(96, 165, 250, 0.35);
      transition: width 0.4s ease;
    }

    .operations-progress-dots span {
      animation: operationsBlink 1.2s infinite ease-in-out;
      display: inline-block;
    }

    .operations-progress-dots span:nth-child(2) { animation-delay: 0.18s; }
    .operations-progress-dots span:nth-child(3) { animation-delay: 0.36s; }

    @keyframes operationsBlink {
      0%, 80%, 100% { opacity: 0.25; transform: translateY(0); }
      40% { opacity: 1; transform: translateY(-1px); }
    }
  </style>

  <div id="operations-progress-overlay" class="operations-progress-overlay" aria-hidden="true">
    <div class="operations-progress-card">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Processando</p>
      <h2 id="operations-progress-title" class="mt-3 font-orbitron text-2xl font-black text-white">Executando rotina operacional</h2>
      <p id="operations-progress-message" class="mt-3 text-sm leading-7 text-slate-300">Estamos processando sua solicitacao. Essa etapa pode levar alguns segundos dependendo do ambiente e da quantidade de arquivos.</p>
      <div class="mt-6 operations-progress-bar">
        <div id="operations-progress-fill" class="operations-progress-fill"></div>
      </div>
      <div class="mt-4 flex items-center justify-between text-xs uppercase tracking-[0.2em] text-slate-400">
        <span id="operations-progress-stage">Preparando</span>
        <span class="operations-progress-dots"><span>.</span><span>.</span><span>.</span></span>
      </div>
      <p class="mt-4 text-xs text-slate-500">Para evitar execucao duplicada, os botoes ficam bloqueados ate a resposta da pagina.</p>
    </div>
  </div>

  <div class="mx-auto max-w-[112rem] space-y-6">
    <?php \App\Support\View::component('site/local-tools-nav', ['active' => 'operations']); ?>

    <div class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Central Operacional</p>
      <h1 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Status consolidado</h1>
      <p class="mt-3 text-sm leading-7 text-slate-300">Visao inicial da nova central com politica operacional, backup de dados, backup tecnico, pacote de conteudo e deploy tecnico. Nesta etapa a central entra como painel de leitura.</p>
    </div>

    <?php if ($flash !== null): ?>
      <div class="rounded-2xl border px-4 py-3 text-sm <?= htmlspecialchars($flashClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <?= htmlspecialchars((string) ($flash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <div class="grid gap-4 xl:grid-cols-6">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Politica</p>
        <div class="mt-4 font-rajdhani text-2xl font-bold <?= $productionGateOpen ? 'text-emerald-300' : 'text-amber-300' ?>">
          <?= $productionGateOpen ? 'Producao liberada' : 'Producao bloqueada' ?>
        </div>
        <div class="mt-2 text-sm text-slate-400">
          Origem atual: <span class="text-white"><?= htmlspecialchars((string) ($policy['current_source'] ?? 'local'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
        </div>
        <div class="mt-1 text-sm text-slate-400">
          Origem aprovada: <span class="text-white"><?= htmlspecialchars((string) ($policy['approved_source'] ?? 'stage'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Backup de dados</p>
        <div class="mt-4 font-rajdhani text-2xl font-bold text-white"><?= (int) ($backup['total'] ?? 0) ?></div>
        <div class="mt-1 text-slate-400">pacotes registrados</div>
        <?php if (is_array($backup['latest'] ?? null)): ?>
          <div class="mt-4 text-sm text-slate-300"><?= htmlspecialchars((string) ($backup['latest']['backup_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) ($backup['latest']['created_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <?php endif; ?>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Backup tecnico local</p>
        <?php if (is_array($technicalBackup['local_latest'] ?? null)): ?>
          <div class="mt-4 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars((string) ($technicalBackup['local_latest']['backup_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="mt-1 text-slate-400"><?= (int) ($technicalBackup['local_latest']['files_count'] ?? 0) ?> arquivos</div>
        <?php else: ?>
          <div class="mt-4 font-rajdhani text-2xl font-bold text-amber-300">Pendente</div>
          <div class="mt-1 text-slate-400">Nenhum backup tecnico local ainda.</div>
        <?php endif; ?>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Backup tecnico stage</p>
        <?php if (is_array($technicalBackup['stage_latest'] ?? null)): ?>
          <div class="mt-4 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars((string) ($technicalBackup['stage_latest']['backup_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="mt-1 text-slate-400"><?= (int) ($technicalBackup['stage_latest']['files_count'] ?? 0) ?> arquivos</div>
        <?php else: ?>
          <div class="mt-4 font-rajdhani text-2xl font-bold text-amber-300">Pendente</div>
          <div class="mt-1 text-slate-400">Nenhum backup tecnico stage ainda.</div>
        <?php endif; ?>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Backup tecnico prod</p>
        <?php if (is_array($technicalBackup['production_latest'] ?? null)): ?>
          <div class="mt-4 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars((string) ($technicalBackup['production_latest']['backup_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="mt-1 text-slate-400"><?= (int) ($technicalBackup['production_latest']['files_count'] ?? 0) ?> arquivos</div>
        <?php else: ?>
          <div class="mt-4 font-rajdhani text-2xl font-bold text-amber-300">Pendente</div>
          <div class="mt-1 text-slate-400">Nenhum backup tecnico producao ainda.</div>
        <?php endif; ?>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Paridade</p>
        <div class="mt-4 text-sm text-slate-300">
          <div class="flex items-center justify-between"><span class="text-slate-500">Conteudo</span><span class="<?= ($parityContent['in_sync'] ?? false) ? 'text-emerald-300' : 'text-amber-300' ?>"><?= ($parityContent['in_sync'] ?? false) ? 'OK' : 'Pendente' ?></span></div>
          <div class="mt-2 flex items-center justify-between"><span class="text-slate-500">Codigo</span><span class="<?= ($parityCode['in_sync'] ?? false) ? 'text-emerald-300' : 'text-amber-300' ?>"><?= ($parityCode['in_sync'] ?? false) ? 'OK' : 'Pendente' ?></span></div>
        </div>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-bold text-white">Backup de dados</h2>
        <p class="mt-2 text-sm leading-7 text-slate-400">Executa dump de banco e pacote de uploads, com leitura do último estado já consolidado da rotina antiga.</p>

        <div class="mt-5 grid gap-4 md:grid-cols-4">
          <form method="POST" action="<?= htmlspecialchars(url('/local/operacoes'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="operations-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Executando backup local" data-progress-message="Estamos exportando o banco local e compactando os uploads da base local." data-progress-stage="Backup local">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="backup_dados">
            <input type="hidden" name="profile" value="local">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Local</p>
            <p class="mt-2 text-sm text-slate-400">Gera backup de dados e uploads do ambiente local.</p>
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border px-4 py-2 text-sm font-semibold transition <?= ($backup['local_ready'] ?? false) ? 'border-cyan-400/40 bg-cyan-500/10 text-cyan-200 hover:border-cyan-300 hover:bg-cyan-500/20' : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= ($backup['local_ready'] ?? false) ? '' : 'disabled' ?>>Gerar backup local</button>
          </form>

          <form method="POST" action="<?= htmlspecialchars(url('/local/operacoes'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="operations-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Executando backup stage" data-progress-message="Estamos conectando na stage para salvar banco e uploads na trilha de dados da central." data-progress-stage="Backup stage">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="backup_dados">
            <input type="hidden" name="profile" value="stage">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Stage</p>
            <p class="mt-2 text-sm text-slate-400">Busca banco e uploads remotos do ambiente de stage.</p>
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border px-4 py-2 text-sm font-semibold transition <?= ($backup['stage_ready'] ?? false) ? 'border-emerald-400/40 bg-emerald-500/10 text-emerald-200 hover:border-emerald-300 hover:bg-emerald-500/20' : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= ($backup['stage_ready'] ?? false) ? '' : 'disabled' ?>>Gerar backup stage</button>
          </form>

          <form method="POST" action="<?= htmlspecialchars(url('/local/operacoes'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="operations-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Executando backup de producao" data-progress-message="Estamos conectando no banco remoto e baixando os uploads da producao." data-progress-stage="Backup producao">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="backup_dados">
            <input type="hidden" name="profile" value="production">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Producao</p>
            <p class="mt-2 text-sm text-slate-400">Busca banco e uploads remotos do ambiente de producao.</p>
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border px-4 py-2 text-sm font-semibold transition <?= ($backup['production_ready'] ?? false) ? 'border-fuchsia-400/40 bg-fuchsia-500/10 text-fuchsia-200 hover:border-fuchsia-300 hover:bg-fuchsia-500/20' : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= ($backup['production_ready'] ?? false) ? '' : 'disabled' ?>>Gerar backup producao</button>
          </form>

          <form method="POST" action="<?= htmlspecialchars(url('/local/operacoes'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="operations-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Verificando backup de dados" data-progress-message="Estamos conferindo manifesto, dump e zip do ultimo backup de dados." data-progress-stage="Verificacao">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="verify_backup_dados">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Verificacao</p>
            <p class="mt-2 text-sm text-slate-400">Confere manifesto, dump e zip do último backup de dados.</p>
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border border-emerald-400/40 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-200 transition hover:border-emerald-300 hover:bg-emerald-500/20">Verificar ultimo</button>
          </form>
        </div>

        <div class="mt-5 grid gap-3 text-sm text-slate-300 md:grid-cols-2">
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Ultimo backup</div>
            <?php if (is_array($backup['latest'] ?? null)): ?>
              <div class="mt-2 text-white"><?= htmlspecialchars((string) ($backup['latest']['backup_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) ($backup['latest']['created_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <div class="mt-2 text-xs text-slate-400">Perfil: <?= htmlspecialchars((string) ($backup['latest']['profile_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php else: ?>
              <div class="mt-2 text-amber-300">Nenhum backup ainda.</div>
            <?php endif; ?>
          </div>
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Ultimo enviado</div>
            <?php if (is_array($backup['latest_uploaded'] ?? null)): ?>
              <div class="mt-2 text-white"><?= htmlspecialchars((string) ($backup['latest_uploaded']['backup_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) ($backup['latest_uploaded']['cloud_uploaded_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php else: ?>
              <div class="mt-2 text-amber-300">Nenhum backup marcado como enviado.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-bold text-white">Backup tecnico</h2>
        <p class="mt-2 text-sm leading-7 text-slate-400">Gera snapshot dos arquivos tecnicos de local, stage e producao dentro da estrutura numerada da central.</p>

        <div class="mt-5 grid gap-4 md:grid-cols-3">
          <form method="POST" action="<?= htmlspecialchars(url('/local/operacoes'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="operations-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Executando backup tecnico local" data-progress-message="Estamos gerando o snapshot tecnico da base local e calculando os checksums dos arquivos." data-progress-stage="Backup tecnico local">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="backup_tecnico">
            <input type="hidden" name="profile" value="local">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Local</p>
            <p class="mt-2 text-sm text-slate-400">
              <?= ($technicalBackup['local_ready'] ?? false) ? 'Snapshot tecnico da base local pronto para execucao.' : 'Complete a configuracao tecnica local antes de gerar.' ?>
            </p>
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border px-4 py-2 text-sm font-semibold transition <?= ($technicalBackup['local_ready'] ?? false) ? 'border-cyan-400/40 bg-cyan-500/10 text-cyan-200 hover:border-cyan-300 hover:bg-cyan-500/20' : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= ($technicalBackup['local_ready'] ?? false) ? '' : 'disabled' ?>>Gerar backup tecnico local</button>
          </form>

          <form method="POST" action="<?= htmlspecialchars(url('/local/operacoes'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="operations-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Executando backup tecnico stage" data-progress-message="Estamos gerando o snapshot tecnico da stage e registrando os arquivos no manifesto." data-progress-stage="Backup tecnico stage">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="backup_tecnico">
            <input type="hidden" name="profile" value="stage">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Stage</p>
            <p class="mt-2 text-sm text-slate-400">
              <?= ($technicalBackup['stage_ready'] ?? false) ? 'Snapshot tecnico da stage pronto para execucao.' : 'Complete as variaveis CONTENT_SYNC_STAGE_CODE_* antes de gerar.' ?>
            </p>
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border px-4 py-2 text-sm font-semibold transition <?= ($technicalBackup['stage_ready'] ?? false) ? 'border-emerald-400/40 bg-emerald-500/10 text-emerald-200 hover:border-emerald-300 hover:bg-emerald-500/20' : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= ($technicalBackup['stage_ready'] ?? false) ? '' : 'disabled' ?>>Gerar backup tecnico stage</button>
          </form>

          <form method="POST" action="<?= htmlspecialchars(url('/local/operacoes'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="operations-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Executando backup tecnico producao" data-progress-message="Estamos gerando o snapshot tecnico da producao e registrando os arquivos no manifesto." data-progress-stage="Backup tecnico producao">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="backup_tecnico">
            <input type="hidden" name="profile" value="production">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Producao</p>
            <p class="mt-2 text-sm text-slate-400">
              <?= ($technicalBackup['production_ready'] ?? false) ? 'Snapshot tecnico da producao pronto para execucao.' : 'Complete as variaveis CONTENT_SYNC_PRODUCTION_CODE_* antes de gerar.' ?>
            </p>
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border px-4 py-2 text-sm font-semibold transition <?= ($technicalBackup['production_ready'] ?? false) ? 'border-fuchsia-400/40 bg-fuchsia-500/10 text-fuchsia-200 hover:border-fuchsia-300 hover:bg-fuchsia-500/20' : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= ($technicalBackup['production_ready'] ?? false) ? '' : 'disabled' ?>>Gerar backup tecnico producao</button>
          </form>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <?php
          $latestStageContent = is_array($content['latest_stage_apply'] ?? null) ? $content['latest_stage_apply'] : null;
          $latestProductionContent = is_array($content['latest_production_apply'] ?? null) ? $content['latest_production_apply'] : null;
        ?>
        <h2 class="font-orbitron text-lg font-bold text-white">Promocao de conteudo</h2>
        <p class="mt-2 text-sm leading-7 text-slate-400">Escolha um pacote editorial ja gerado em <span class="text-white">stage</span> ou <span class="text-white">producao</span> e envie para o ambiente de destino permitido. A regra desta central cobre os fluxos <span class="text-white">stage -&gt; producao</span>, <span class="text-white">producao -&gt; stage</span>, <span class="text-white">stage -&gt; local</span> e <span class="text-white">producao -&gt; local</span>.</p>

        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4 text-sm text-slate-300">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Pacotes monitorados</div>
            <div class="mt-2 font-orbitron text-base text-white"><?= (int) ($content['total'] ?? 0) ?></div>
            <div class="mt-1 text-slate-400">A lista abaixo ignora pacotes locais legados e considera apenas stage/producao.</div>
          </div>

          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4 text-sm text-slate-300">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Ultima stage</div>
            <?php if ($latestStageContent !== null): ?>
              <div class="mt-2 text-white"><?= htmlspecialchars((string) ($latestStageContent['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) ($latestStageContent['applied_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php else: ?>
              <div class="mt-2 text-amber-300">Nenhuma aplicacao em stage registrada.</div>
            <?php endif; ?>
          </div>

          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4 text-sm text-slate-300">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Ultima producao</div>
            <?php if ($latestProductionContent !== null): ?>
              <div class="mt-2 text-white"><?= htmlspecialchars((string) ($latestProductionContent['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) ($latestProductionContent['applied_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php else: ?>
              <div class="mt-2 text-amber-300">Nenhuma promocao para producao registrada.</div>
            <?php endif; ?>
          </div>

          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4 text-sm text-slate-300">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Ambientes prontos</div>
            <div class="mt-2 text-xs uppercase tracking-[0.18em] text-slate-400">Local <span class="ml-2 text-emerald-300">SIM</span></div>
            <div class="mt-2 text-xs uppercase tracking-[0.18em] text-slate-400">Stage <span class="ml-2 <?= ($content['stage_ready'] ?? false) ? 'text-emerald-300' : 'text-amber-300' ?>"><?= ($content['stage_ready'] ?? false) ? 'SIM' : 'PENDENTE' ?></span></div>
            <div class="mt-2 text-xs uppercase tracking-[0.18em] text-slate-400">Producao <span class="ml-2 <?= ($content['production_ready'] ?? false) ? 'text-emerald-300' : 'text-amber-300' ?>"><?= ($content['production_ready'] ?? false) ? 'SIM' : 'PENDENTE' ?></span></div>
          </div>
        </div>

        <div class="mt-5 grid gap-4 md:grid-cols-3">
          <form method="POST" action="<?= htmlspecialchars(url('/local/operacoes'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="operations-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Gerando pacote da stage" data-progress-message="Estamos exportando posts, links, configuracoes publicas e uploads referenciados diretamente da stage." data-progress-stage="Pacote stage">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="export_content">
            <input type="hidden" name="profile" value="stage">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Gerar pacote stage</p>
            <p class="mt-2 text-sm text-slate-400">Cria um pacote editorial a partir do ambiente de stage para envio controlado a local ou producao.</p>
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border px-4 py-2 text-sm font-semibold transition <?= ($content['stage_ready'] ?? false) ? 'border-emerald-400/40 bg-emerald-500/10 text-emerald-200 hover:border-emerald-300 hover:bg-emerald-500/20' : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= ($content['stage_ready'] ?? false) ? '' : 'disabled' ?>>Gerar pacote stage</button>
          </form>

          <form method="POST" action="<?= htmlspecialchars(url('/local/operacoes'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="operations-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Gerando pacote da producao" data-progress-message="Estamos exportando posts, links, configuracoes publicas e uploads referenciados diretamente da producao." data-progress-stage="Pacote producao">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="export_content">
            <input type="hidden" name="profile" value="production">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Gerar pacote producao</p>
            <p class="mt-2 text-sm text-slate-400">Cria um pacote editorial a partir da producao para envio controlado a stage ou local.</p>
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border px-4 py-2 text-sm font-semibold transition <?= ($content['production_ready'] ?? false) ? 'border-fuchsia-400/40 bg-fuchsia-500/10 text-fuchsia-200 hover:border-fuchsia-300 hover:bg-fuchsia-500/20' : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= ($content['production_ready'] ?? false) ? '' : 'disabled' ?>>Gerar pacote producao</button>
          </form>

          <form method="POST" action="<?= htmlspecialchars(url('/local/operacoes'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="operations-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Verificando ultimo pacote de conteudo" data-progress-message="Estamos conferindo JSONs, manifesto e zip do ultimo pacote editorial disponivel." data-progress-stage="Verificacao">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="verify_content">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Verificar ultimo pacote</p>
            <p class="mt-2 text-sm text-slate-400">Confere manifesto e arquivos do ultimo pacote de conteudo antes de aplicar no destino.</p>
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20">Verificar pacote</button>
          </form>
        </div>

        <form method="POST" action="<?= htmlspecialchars(url('/local/operacoes'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="operations-action-form mt-5 rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Aplicando pacote de conteudo" data-progress-message="Estamos aplicando o pacote editorial selecionado no ambiente de destino escolhido." data-progress-stage="Promocao">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="apply_content">
          <div class="grid gap-4 xl:grid-cols-[1.4fr_0.9fr_1fr_auto]">
            <div>
              <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Pacote</label>
              <select name="package_id" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-cyan-400">
                <option value="">Selecione um pacote</option>
                <?php foreach ($contentPackages as $package): ?>
                  <?php
                    $packageId = (string) ($package['package_id'] ?? '');
                    $sourceProfile = (string) ($package['source_profile'] ?? '');
                    $sourceLabel = (string) ($package['source_profile_label'] ?? $sourceProfile);
                    $createdAt = (string) ($package['created_at'] ?? '');
                    $allowedTargets = implode(', ', array_map('strval', (array) ($package['allowed_targets'] ?? [])));
                  ?>
                  <?php if ($packageId === '') { continue; } ?>
                  <option value="<?= htmlspecialchars($packageId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <?= htmlspecialchars($packageId . ' | origem: ' . $sourceLabel . ' | destinos: ' . strtoupper($allowedTargets !== '' ? $allowedTargets : 'nenhum') . ' | ' . $createdAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Destino</label>
              <select name="target_profile" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-cyan-400">
                <option value="local">Local</option>
                <option value="stage" <?= ($content['stage_ready'] ?? false) ? '' : 'disabled' ?>>Stage</option>
                <option value="production" <?= (($content['production_ready'] ?? false) && $productionGateOpen) ? '' : 'disabled' ?>>Producao</option>
              </select>
            </div>
            <div>
              <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Confirmacao</label>
              <input type="text" name="apply_phrase" placeholder="Digite PUBLICAR" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-amber-400">
            </div>
            <div class="flex items-end">
              <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-amber-400/40 bg-amber-500/10 px-4 py-3 text-sm font-semibold text-amber-200 transition hover:border-amber-300 hover:bg-amber-500/20">Aplicar pacote</button>
            </div>
          </div>
          <p class="mt-3 text-xs text-slate-500">A validacao final do par origem/destino acontece no backend. Pacotes com origem <span class="text-white">local</span> ficam fora desta trilha da central.</p>
        </form>

        <div class="mt-5 rounded-2xl border border-slate-800 bg-slate-950/60 p-4">
          <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Pacotes recentes</div>
          <?php if ($contentPackages !== []): ?>
            <div class="mt-4 space-y-3">
              <?php foreach (array_slice($contentPackages, 0, 6) as $package): ?>
                <?php
                  $allowedTargets = (array) ($package['allowed_targets'] ?? []);
                  $allowedLabel = $allowedTargets !== [] ? implode(', ', array_map('strtoupper', array_map('strval', $allowedTargets))) : 'nenhum';
                ?>
                <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-3 text-sm text-slate-300">
                  <div class="text-white"><?= htmlspecialchars((string) ($package['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <div class="mt-1 text-xs text-slate-500">Origem: <?= htmlspecialchars((string) ($package['source_profile_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> | <?= htmlspecialchars((string) ($package['created_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <div class="mt-2 text-xs text-slate-400">Destinos permitidos: <span class="text-slate-200"><?= htmlspecialchars($allowedLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="mt-4 text-amber-300">Nenhum pacote de conteudo listado ainda.</div>
          <?php endif; ?>
        </div>

      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-bold text-white">Deploy tecnico</h2>
        <div class="mt-4 grid gap-3 text-sm text-slate-300">
          <div><span class="text-slate-500">Raiz:</span> <?= htmlspecialchars((string) ($code['root'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div><span class="text-slate-500">Pacotes:</span> <?= (int) ($code['total'] ?? 0) ?></div>
          <?php if (is_array($code['latest'] ?? null)): ?>
            <div><span class="text-slate-500">Ultimo pacote:</span> <span class="text-white"><?= htmlspecialchars((string) ($code['latest']['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
          <?php endif; ?>
          <?php if (is_array($code['latest_stage_apply'] ?? null)): ?>
            <div><span class="text-slate-500">Ultima stage:</span> <span class="text-white"><?= htmlspecialchars((string) ($code['latest_stage_apply']['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
          <?php endif; ?>
          <?php if (is_array($code['latest_production_apply'] ?? null)): ?>
            <div><span class="text-slate-500">Ultima producao:</span> <span class="text-white"><?= htmlspecialchars((string) ($code['latest_production_apply']['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
          <?php endif; ?>
        </div>

        <form method="POST" action="<?= htmlspecialchars(url('/local/operacoes'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="operations-action-form mt-5 rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Gerando pacote tecnico" data-progress-message="Estamos montando um pacote tecnico com os arquivos alterados e elegiveis para deploy." data-progress-stage="Pacote tecnico">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="export_code">
          <div class="grid gap-4 md:grid-cols-[1fr_auto]">
            <div>
              <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Notas do pacote</label>
              <input type="text" name="code_notes" placeholder="Ex.: central operacional e logs separados" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-cyan-400">
            </div>
            <div class="flex items-end">
              <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-3 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20">Gerar pacote tecnico</button>
            </div>
          </div>
          <p class="mt-3 text-xs text-slate-500">O pacote tecnico leva apenas arquivos elegiveis das alteracoes atuais do git. Arquivos excluidos ou fora do escopo tecnico ficam de fora.</p>
        </form>

        <form method="POST" action="<?= htmlspecialchars(url('/local/operacoes'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="operations-action-form mt-5 rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Publicando pacote tecnico" data-progress-message="Estamos aplicando o ultimo pacote tecnico no ambiente selecionado." data-progress-stage="Deploy tecnico">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="apply_code">
          <div class="grid gap-4 md:grid-cols-[1fr_1fr_auto]">
            <div>
              <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Destino</label>
              <select name="target_profile" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-cyan-400">
                <option value="stage" <?= ($code['stage_ready'] ?? false) ? '' : 'disabled' ?>>Stage</option>
                <option value="production" <?= (($code['production_ready'] ?? false) && $productionGateOpen) ? '' : 'disabled' ?>>Producao</option>
              </select>
            </div>
            <div>
              <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Confirmacao</label>
              <input type="text" name="apply_phrase" placeholder="Digite PUBLICAR" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-amber-400">
            </div>
            <div class="flex items-end">
              <button type="submit" class="inline-flex items-center justify-center rounded-2xl border px-4 py-3 text-sm font-semibold transition <?= ($code['stage_ready'] ?? false) ? 'border-blue-400/40 bg-blue-500/10 text-blue-200 hover:border-blue-300 hover:bg-blue-500/20' : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= ($code['stage_ready'] ?? false) ? '' : 'disabled' ?>>Publicar ultimo pacote tecnico</button>
            </div>
          </div>
          <p class="mt-3 text-xs text-slate-500">A central usa o ultimo pacote tecnico disponivel. Producao continua bloqueada sem origem aprovada.</p>
        </form>

      </div>
    </div>

    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <h2 class="font-orbitron text-lg font-bold text-white">Listagem de backups tecnicos</h2>
      <p class="mt-2 text-sm leading-7 text-slate-400">Primeira listagem operacional dos snapshots tecnicos gerados pela central, separada por ambiente para facilitar consulta e rollback.</p>

      <div class="mt-5 grid gap-4 xl:grid-cols-3">
        <?php
          $technicalLists = [
            'Local' => $technicalLocalList,
            'Stage' => $technicalStageList,
            'Producao' => $technicalProductionList,
          ];
        ?>
        <?php foreach ($technicalLists as $label => $items): ?>
          <div class="rounded-2xl border border-slate-800 bg-slate-950/60 p-4">
            <div class="flex items-center justify-between gap-3">
              <div class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80"><?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <div class="text-xs text-slate-500"><?= count($items) ?> item(ns)</div>
            </div>

            <?php if ($items !== []): ?>
              <div class="mt-4 space-y-3">
                <?php foreach (array_slice($items, 0, 5) as $item): ?>
                  <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-3 text-sm text-slate-300">
                    <div class="text-white"><?= htmlspecialchars((string) ($item['backup_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) ($item['created_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    <div class="mt-2 flex items-center justify-between gap-3 text-xs">
                      <span class="text-slate-400"><?= (int) ($item['files_count'] ?? 0) ?> arquivos</span>
                      <span class="<?= strtolower((string) ($item['status'] ?? '')) === 'ready' ? 'text-emerald-300' : 'text-amber-300' ?>">
                        <?= htmlspecialchars((string) ($item['status'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                      </span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="mt-4 rounded-2xl border border-dashed border-slate-700 bg-slate-900/40 p-4 text-sm text-slate-400">
                Nenhum backup tecnico listado ainda neste ambiente.
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h2 class="font-orbitron text-lg font-bold text-white">Logs e historico</h2>
          <p class="mt-2 text-sm leading-7 text-slate-400">A central agora separa o historico mensal por trilha operacional para nao misturar backup de dados, backup tecnico e pacote de conteudo.</p>
        </div>
      </div>

      <div class="mt-5 grid gap-4 xl:grid-cols-3">
        <?php foreach (['dados', 'tecnico', 'conteudo'] as $logKey): ?>
          <?php
            $logGroup = is_array($logCategories[$logKey] ?? null) ? $logCategories[$logKey] : [];
            $logEntries = is_array($logGroup['entries'] ?? null) ? $logGroup['entries'] : [];
            $logFileName = is_string($logGroup['latest_file'] ?? null) ? basename((string) $logGroup['latest_file']) : 'Nenhum log ainda';
          ?>
          <div class="rounded-2xl border border-slate-800 bg-slate-950/60 p-4">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80"><?= htmlspecialchars((string) ($logGroup['label'] ?? ucfirst($logKey)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <div class="mt-2 text-xs text-slate-500">Arquivo ativo</div>
                <div class="mt-1 break-all text-sm text-white"><?= htmlspecialchars($logFileName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              </div>
              <div class="rounded-2xl border border-slate-800 bg-slate-900/80 px-3 py-2 text-right text-xs text-slate-400">
                <div><?= (int) ($logGroup['total_loaded'] ?? 0) ?> entrada(s)</div>
              </div>
            </div>

            <?php if ($logEntries !== []): ?>
              <div class="mt-4 space-y-3">
                <?php foreach ($logEntries as $entry): ?>
                  <?php
                    $statusValue = strtolower((string) ($entry['status'] ?? '-'));
                    $statusClass = $statusValue === 'ok'
                      ? 'text-emerald-300'
                      : ($statusValue === 'fail' ? 'text-rose-300' : 'text-amber-300');
                  ?>
                  <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-3 text-sm text-slate-300">
                    <div class="flex items-start justify-between gap-3">
                      <div class="text-white"><?= htmlspecialchars((string) ($entry['id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                      <div class="text-xs font-semibold uppercase tracking-[0.18em] <?= $statusClass ?>"><?= htmlspecialchars((string) ($entry['status'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    </div>
                    <div class="mt-2 text-xs uppercase tracking-[0.15em] text-cyan-200"><?= htmlspecialchars((string) ($entry['tipo'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    <div class="mt-2 text-xs text-slate-500"><?= htmlspecialchars((string) ($entry['timestamp'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    <div class="mt-2 flex items-center justify-between gap-3 text-xs text-slate-400">
                      <span>Origem: <?= htmlspecialchars((string) ($entry['origem'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                      <span>Destino: <?= htmlspecialchars((string) ($entry['destino'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    </div>
                    <div class="mt-3 text-sm text-slate-300"><?= htmlspecialchars((string) ($entry['msg'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="mt-4 rounded-2xl border border-dashed border-slate-700 bg-slate-900/40 p-4 text-sm text-slate-400">
                Ainda nao existem entradas nesta trilha. Assim que uma rotina real rodar, este historico aparece aqui.
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
      <div class="rounded-3xl border border-rose-500/30 bg-slate-900/80 p-6 shadow-[0_0_30px_rgba(244,63,94,0.08)]">
        <h2 class="font-orbitron text-lg font-bold text-white">Restore de dados</h2>
        <p class="mt-2 text-sm leading-7 text-slate-400">Area critica para restaurar banco, uploads ou ambos a partir do ultimo backup pronto. Use somente quando houver necessidade real de rollback operacional.</p>

        <div class="mt-5 grid gap-3 text-sm text-slate-300 md:grid-cols-2">
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Ultimo backup disponivel</div>
            <?php if (is_array($backup['latest'] ?? null)): ?>
              <div class="mt-2 text-white"><?= htmlspecialchars((string) ($backup['latest']['backup_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) ($backup['latest']['created_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <div class="mt-2 text-xs text-slate-400">Perfil de origem: <?= htmlspecialchars((string) ($backup['latest']['profile_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php else: ?>
              <div class="mt-2 text-amber-300">Nenhum backup disponivel para restore.</div>
            <?php endif; ?>
          </div>

          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Escopos aceitos</div>
            <div class="mt-2 text-slate-300">`ALL` restaura banco e uploads.</div>
            <div class="mt-1 text-slate-300">`DATABASE` restaura somente o banco.</div>
            <div class="mt-1 text-slate-300">`UPLOADS` restaura somente os arquivos.</div>
          </div>
        </div>

        <form method="POST" action="<?= htmlspecialchars(url('/local/operacoes'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="operations-action-form mt-5 rounded-2xl border border-rose-500/20 bg-slate-950/70 p-4" data-progress-title="Executando restore de dados" data-progress-message="Estamos restaurando banco, uploads ou ambos a partir do ultimo backup valido." data-progress-stage="Restore">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="restore_data">
          <div class="grid gap-4 md:grid-cols-[1fr_1fr_1fr_auto]">
            <div>
              <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Destino</label>
              <select name="target_profile" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-rose-400">
                <option value="local" <?= ($backup['local_ready'] ?? false) ? '' : 'disabled' ?>>Local</option>
                <option value="stage" <?= ($backup['stage_ready'] ?? false) ? '' : 'disabled' ?>>Stage</option>
                <option value="production" <?= ($backup['production_ready'] ?? false) ? '' : 'disabled' ?>>Producao</option>
              </select>
            </div>
            <div>
              <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Escopo</label>
              <select name="scope" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-rose-400">
                <option value="all">ALL</option>
                <option value="database">DATABASE</option>
                <option value="uploads">UPLOADS</option>
              </select>
            </div>
            <div>
              <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Confirmacao</label>
              <input type="text" name="restore_phrase" placeholder="Digite CONFIRMAR" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-rose-400">
            </div>
            <div class="flex items-end">
              <button type="submit" class="inline-flex items-center justify-center rounded-2xl border px-4 py-3 text-sm font-semibold transition <?= is_array($backup['latest'] ?? null) ? 'border-rose-400/40 bg-rose-500/10 text-rose-200 hover:border-rose-300 hover:bg-rose-500/20' : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= is_array($backup['latest'] ?? null) ? '' : 'disabled' ?>>Executar restore</button>
            </div>
          </div>
          <p class="mt-3 text-xs text-slate-500">O restore sempre usa o ultimo backup validado disponivel na estrutura atual. Use essa rotina com muito cuidado.</p>
        </form>
      </div>

      <div class="rounded-3xl border border-amber-500/20 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-bold text-white">Rollback tecnico</h2>
        <p class="mt-2 text-sm leading-7 text-slate-400">Reaplica o ultimo snapshot tecnico de stage ou producao no proprio ambiente alvo. Use isso apenas quando um deploy recente tiver quebrado o ambiente e o snapshot ja existir.</p>

        <div class="mt-5 grid gap-3 text-sm text-slate-300">
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Ultimo snapshot stage</div>
            <?php if (is_array($technicalBackup['stage_latest'] ?? null)): ?>
              <div class="mt-2 text-white"><?= htmlspecialchars((string) ($technicalBackup['stage_latest']['backup_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) ($technicalBackup['stage_latest']['created_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php else: ?>
              <div class="mt-2 text-amber-300">Nenhum snapshot tecnico stage disponivel.</div>
            <?php endif; ?>
          </div>

          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Ultimo snapshot producao</div>
            <?php if (is_array($technicalBackup['production_latest'] ?? null)): ?>
              <div class="mt-2 text-white"><?= htmlspecialchars((string) ($technicalBackup['production_latest']['backup_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
              <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) ($technicalBackup['production_latest']['created_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php else: ?>
              <div class="mt-2 text-amber-300">Nenhum snapshot tecnico producao disponivel.</div>
            <?php endif; ?>
          </div>
        </div>

        <form method="POST" action="<?= htmlspecialchars(url('/local/operacoes'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="operations-action-form mt-5 rounded-2xl border border-amber-500/20 bg-slate-950/70 p-4" data-progress-title="Executando rollback tecnico" data-progress-message="Estamos reaplicando o ultimo snapshot tecnico do ambiente selecionado." data-progress-stage="Rollback tecnico">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="rollback_technical">
          <div class="grid gap-4 md:grid-cols-[1fr_1fr_auto]">
            <div>
              <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Destino</label>
              <select name="target_profile" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-amber-400">
                <option value="local" <?= is_array($technicalBackup['local_latest'] ?? null) ? '' : 'disabled' ?>>Local</option>
                <option value="stage" <?= is_array($technicalBackup['stage_latest'] ?? null) ? '' : 'disabled' ?>>Stage</option>
                <option value="production" <?= is_array($technicalBackup['production_latest'] ?? null) ? '' : 'disabled' ?>>Producao</option>
              </select>
            </div>
            <div>
              <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Confirmacao</label>
              <input type="text" name="rollback_phrase" placeholder="Digite CONFIRMAR" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-amber-400">
            </div>
            <div class="flex items-end">
              <button type="submit" class="inline-flex items-center justify-center rounded-2xl border px-4 py-3 text-sm font-semibold transition <?= (is_array($technicalBackup['local_latest'] ?? null) || is_array($technicalBackup['stage_latest'] ?? null) || is_array($technicalBackup['production_latest'] ?? null)) ? 'border-amber-400/40 bg-amber-500/10 text-amber-200 hover:border-amber-300 hover:bg-amber-500/20' : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= (is_array($technicalBackup['local_latest'] ?? null) || is_array($technicalBackup['stage_latest'] ?? null) || is_array($technicalBackup['production_latest'] ?? null)) ? '' : 'disabled' ?>>Executar rollback</button>
            </div>
          </div>
          <p class="mt-3 text-xs text-slate-500">O rollback tecnico usa sempre o ultimo snapshot do ambiente selecionado. Gere um novo backup tecnico antes de qualquer deploy sensivel.</p>
        </form>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <h2 class="font-orbitron text-lg font-bold text-white">Raizes operacionais</h2>
      <div class="mt-4 grid gap-3 text-sm text-slate-300 md:grid-cols-2">
        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
          <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Backup</div>
          <div class="mt-2 break-all text-white"><?= htmlspecialchars((string) ($backup['root'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
          <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Backup tecnico</div>
          <div class="mt-2 break-all text-white"><?= htmlspecialchars((string) ($technicalBackup['root'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </div>
      </div>
    </div>
  </div>

  <script>
    (() => {
      const overlay = document.getElementById('operations-progress-overlay');
      const title = document.getElementById('operations-progress-title');
      const message = document.getElementById('operations-progress-message');
      const stage = document.getElementById('operations-progress-stage');
      const fill = document.getElementById('operations-progress-fill');
      const forms = Array.from(document.querySelectorAll('.operations-action-form'));
      let progressTimer = null;
      let locked = false;

      const steps = [10, 18, 28, 38, 52, 66, 78, 86, 92];
      const stageLabels = ['Preparando', 'Conectando', 'Lendo origem', 'Processando arquivos', 'Validando', 'Aplicando destino', 'Finalizando'];

      const disableAll = (currentForm) => {
        forms.forEach((form) => {
          form.querySelectorAll('button').forEach((button) => {
            button.disabled = true;
          });

          if (form !== currentForm) {
            form.querySelectorAll('input, select, textarea').forEach((element) => {
              if (element instanceof HTMLInputElement && element.type === 'hidden') {
                return;
              }

              element.disabled = true;
            });
          }
        });
      };

      const startProgress = (currentForm) => {
        if (locked) {
          return false;
        }

        locked = true;
        disableAll(currentForm);

        title.textContent = currentForm.dataset.progressTitle || 'Executando rotina operacional';
        message.textContent = currentForm.dataset.progressMessage || 'Estamos processando sua solicitacao.';
        stage.textContent = currentForm.dataset.progressStage || 'Processando';
        fill.style.width = '10%';
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');

        let index = 0;
        progressTimer = window.setInterval(() => {
          index = Math.min(index + 1, steps.length - 1);
          fill.style.width = steps[index] + '%';
          if (index < stageLabels.length) {
            stage.textContent = stageLabels[index];
          }
        }, 900);

        return true;
      };

      forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
          if (!startProgress(form)) {
            event.preventDefault();
          }
        });
      });

      window.addEventListener('pageshow', () => {
        if (progressTimer) {
          window.clearInterval(progressTimer);
        }
      });
    })();
  </script>
</section>
