<?php

declare(strict_types=1);

use App\Support\Csrf;

$backupStatus = (array) ($backup_status ?? []);
$items = (array) ($backupStatus['items'] ?? []);
$latest = $backupStatus['latest'] ?? null;
$latestUploaded = $backupStatus['latest_uploaded'] ?? null;
$running = $backupStatus['running'] ?? null;
$flash = is_array($flash ?? null) ? $flash : null;
$lastVerification = is_array($last_verification ?? null) ? $last_verification : null;
$productionReady = (bool) ($production_ready ?? false);

$alertClasses = [
    'success' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100',
    'error' => 'border-rose-500/40 bg-rose-500/10 text-rose-100',
];
$flashClass = $flash !== null ? ($alertClasses[$flash['type']] ?? $alertClasses['success']) : '';
?>
<section class="min-h-screen bg-slate-950 px-4 py-8 text-slate-100">
  <style>
    .backup-progress-overlay {
      position: fixed;
      inset: 0;
      z-index: 9999;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(2, 6, 23, 0.84);
      backdrop-filter: blur(12px);
    }

    .backup-progress-overlay.is-visible {
      display: flex;
    }

    .backup-progress-card {
      width: min(92vw, 34rem);
      border-radius: 1.75rem;
      border: 1px solid rgba(34, 211, 238, 0.25);
      background: linear-gradient(180deg, rgba(15, 23, 42, 0.96), rgba(2, 6, 23, 0.96));
      padding: 1.5rem;
      box-shadow: 0 0 40px rgba(6, 182, 212, 0.12);
    }

    .backup-progress-bar {
      height: 0.8rem;
      overflow: hidden;
      border-radius: 999px;
      background: rgba(30, 41, 59, 0.9);
      border: 1px solid rgba(51, 65, 85, 0.8);
    }

    .backup-progress-fill {
      height: 100%;
      width: 8%;
      border-radius: inherit;
      background: linear-gradient(90deg, #22d3ee, #60a5fa, #c084fc);
      box-shadow: 0 0 24px rgba(96, 165, 250, 0.35);
      transition: width 0.4s ease;
    }

    .backup-progress-dots span {
      animation: backupBlink 1.2s infinite ease-in-out;
      display: inline-block;
    }

    .backup-progress-dots span:nth-child(2) { animation-delay: 0.18s; }
    .backup-progress-dots span:nth-child(3) { animation-delay: 0.36s; }

    @keyframes backupBlink {
      0%, 80%, 100% { opacity: 0.25; transform: translateY(0); }
      40% { opacity: 1; transform: translateY(-1px); }
    }
  </style>

  <div id="backup-progress-overlay" class="backup-progress-overlay" aria-hidden="true">
    <div class="backup-progress-card">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Processando</p>
      <h2 id="backup-progress-title" class="mt-3 font-orbitron text-2xl font-black text-white">Executando rotina de backup</h2>
      <p id="backup-progress-message" class="mt-3 text-sm leading-7 text-slate-300">Estamos preparando os arquivos. Esse processo pode levar alguns segundos, especialmente na produção.</p>
      <div class="mt-6 backup-progress-bar">
        <div id="backup-progress-fill" class="backup-progress-fill"></div>
      </div>
      <div class="mt-4 flex items-center justify-between text-xs uppercase tracking-[0.2em] text-slate-400">
        <span id="backup-progress-stage">Preparando</span>
        <span class="backup-progress-dots"><span>.</span><span>.</span><span>.</span></span>
      </div>
      <p class="mt-4 text-xs text-slate-500">Para evitar backup duplicado, os botões ficam bloqueados até a resposta da página.</p>
    </div>
  </div>

  <div class="mx-auto max-w-7xl space-y-6">
    <?php \App\Support\View::component('site/local-tools-nav', ['active' => 'backup']); ?>

    <div class="flex flex-col gap-3 rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Rotina Local</p>
          <h1 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Backup e Restore</h1>
          <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-300">Painel local para criar backup full, validar integridade, marcar envio manual para a nuvem e restaurar banco ou uploads com segurança.</p>
        </div>
        <div class="rounded-2xl border border-slate-700/70 bg-slate-950/70 px-4 py-3 text-sm text-slate-300">
          <div><span class="text-slate-500">Raiz:</span> <?= htmlspecialchars((string) ($backupStatus['backup_root'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="mt-1"><span class="text-slate-500">Backups locais:</span> <?= (int) ($backupStatus['total_backups'] ?? 0) ?></div>
        </div>
      </div>

      <?php if ($flash !== null): ?>
        <div class="rounded-2xl border px-4 py-3 text-sm <?= htmlspecialchars($flashClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          <?= htmlspecialchars((string) ($flash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <?php if (is_array($running)): ?>
        <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
          <strong class="font-semibold">Backup em execução:</strong>
          <?= htmlspecialchars((string) ($running['profile_label'] ?? 'Rotina ativa'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
          <?php if (!empty($running['started_at'])): ?>
            <span class="text-amber-200/90">desde <?= htmlspecialchars((string) $running['started_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Último backup</p>
        <div class="mt-4 text-sm text-slate-300">
          <?php if (is_array($latest)): ?>
            <div class="font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars((string) ($latest['backup_id'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div class="mt-1 text-slate-400"><?= htmlspecialchars((string) ($latest['profile_label'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div class="mt-4 grid gap-2 text-sm">
              <div class="flex items-center justify-between"><span class="text-slate-500">Validade</span><span class="<?= ($latest['is_valid'] ?? false) ? 'text-emerald-300' : 'text-rose-300' ?>"><?= ($latest['is_valid'] ?? false) ? 'OK' : 'Falhou' ?></span></div>
              <div class="flex items-center justify-between"><span class="text-slate-500">Banco</span><span><?= htmlspecialchars((string) ($latest['database_size'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
              <div class="flex items-center justify-between"><span class="text-slate-500">Uploads</span><span><?= htmlspecialchars((string) ($latest['uploads_size'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
              <div class="flex items-center justify-between"><span class="text-slate-500">Pacote</span><span><?= htmlspecialchars((string) ($latest['total_size'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
            </div>
          <?php else: ?>
            <p class="text-slate-400">Nenhum backup criado ainda.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Nuvem</p>
        <div class="mt-4 text-sm text-slate-300">
          <?php if (is_array($latestUploaded)): ?>
            <div class="font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars((string) ($latestUploaded['backup_id'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div class="mt-1 text-slate-400">Último backup marcado como enviado</div>
            <div class="mt-4 text-slate-300">Enviado em: <span class="text-white"><?= htmlspecialchars((string) ($latestUploaded['cloud_uploaded_at'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
          <?php else: ?>
            <div class="font-rajdhani text-2xl font-bold text-white">Pendente</div>
            <div class="mt-1 text-slate-400">Ainda não existe backup marcado como enviado.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Perfil de produção</p>
        <div class="mt-4 text-sm text-slate-300">
          <div class="font-rajdhani text-2xl font-bold <?= $productionReady ? 'text-emerald-300' : 'text-amber-300' ?>"><?= $productionReady ? 'Pronto' : 'Pendente' ?></div>
          <div class="mt-1 text-slate-400"><?= $productionReady ? 'Banco e FTP da produção já podem ser usados na interface.' : 'Preencha as variáveis BACKUP_PRODUCTION_* no .env antes de usar backup remoto.' ?></div>
        </div>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.1fr_1fr]">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-bold text-white">Ações rápidas</h2>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
          <form method="POST" action="<?= url('/local/backup') ?>" class="backup-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Executando backup local" data-progress-message="Estamos exportando o banco local e compactando os uploads." data-progress-stage="Backup local">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="run">
            <input type="hidden" name="profile" value="local">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Backup local</p>
            <p class="mt-2 text-sm text-slate-400">Gera dump do banco local e pacote completo dos uploads locais.</p>
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20">Executar agora</button>
          </form>

          <form method="POST" action="<?= url('/local/backup') ?>" class="backup-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Executando backup de produção" data-progress-message="Estamos conectando no banco remoto e baixando os uploads da hospedagem." data-progress-stage="Backup produção">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="run">
            <input type="hidden" name="profile" value="production">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Backup produção</p>
            <p class="mt-2 text-sm text-slate-400">Busca banco e uploads remotos usando o perfil de produção configurado.</p>
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border px-4 py-2 text-sm font-semibold transition <?= $productionReady ? 'border-fuchsia-400/40 bg-fuchsia-500/10 text-fuchsia-200 hover:border-fuchsia-300 hover:bg-fuchsia-500/20' : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= $productionReady ? '' : 'disabled' ?>>Executar produção</button>
          </form>

          <form method="POST" action="<?= url('/local/backup') ?>" class="backup-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Verificando backup" data-progress-message="Estamos conferindo o manifesto, os checksums e o pacote mais recente." data-progress-stage="Verificação">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="verify">
            <input type="hidden" name="backup_id" value="latest">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Verificar último</p>
            <p class="mt-2 text-sm text-slate-400">Confirma se o manifesto, o dump e o zip do último backup estão íntegros.</p>
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border border-emerald-400/40 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-200 transition hover:border-emerald-300 hover:bg-emerald-500/20">Verificar</button>
          </form>

          <form method="POST" action="<?= url('/local/backup') ?>" class="backup-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Registrando envio" data-progress-message="Estamos marcando este pacote como enviado para a nuvem no manifesto local." data-progress-stage="Registro">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="mark_uploaded">
            <input type="hidden" name="backup_id" value="latest">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Marcar envio</p>
            <p class="mt-2 text-sm text-slate-400">Depois de subir o pacote manualmente ao Drive, registra isso no manifesto.</p>
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border border-amber-400/40 bg-amber-500/10 px-4 py-2 text-sm font-semibold text-amber-200 transition hover:border-amber-300 hover:bg-amber-500/20">Marcar último como enviado</button>
          </form>
        </div>
      </div>

      <div class="rounded-3xl border border-rose-500/20 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-bold text-white">Restore controlado</h2>
        <p class="mt-2 text-sm leading-7 text-slate-400">Use esta área só quando for realmente necessário voltar banco, uploads ou ambos. A confirmação exige a frase <span class="font-semibold text-white">RESTAURAR</span>.</p>

        <form method="POST" action="<?= url('/local/backup') ?>" class="backup-action-form mt-5 space-y-4" data-progress-title="Executando restore" data-progress-message="Estamos aplicando o backup selecionado. Esse passo pode sobrescrever banco ou uploads." data-progress-stage="Restore">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="restore">

          <div>
            <label class="mb-2 block text-sm font-semibold text-slate-200">Backup</label>
            <select name="backup_id" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-cyan-400">
              <option value="latest">Último válido</option>
              <?php foreach ($items as $item): ?>
                <option value="<?= htmlspecialchars((string) ($item['backup_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars((string) ($item['backup_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <label class="mb-2 block text-sm font-semibold text-slate-200">Destino</label>
              <select name="target_profile" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-cyan-400">
                <option value="local">Local</option>
                <option value="production" <?= $productionReady ? '' : 'disabled' ?>>Produção</option>
              </select>
            </div>
            <div>
              <label class="mb-2 block text-sm font-semibold text-slate-200">Escopo</label>
              <select name="scope" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-cyan-400">
                <option value="all">Banco + uploads</option>
                <option value="database">Só banco</option>
                <option value="uploads">Só uploads</option>
              </select>
            </div>
          </div>

          <div>
            <label class="mb-2 block text-sm font-semibold text-slate-200">Confirmação</label>
            <input type="text" name="restore_phrase" placeholder="Digite RESTAURAR" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-rose-400">
          </div>

          <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-rose-400/40 bg-rose-500/10 px-5 py-3 text-sm font-semibold text-rose-200 transition hover:border-rose-300 hover:bg-rose-500/20">Executar restore</button>
        </form>
      </div>
    </div>

    <?php if ($lastVerification !== null): ?>
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-bold text-white">Última verificação</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Backup</div>
            <div class="mt-2 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars((string) ($lastVerification['backup_id'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          </div>
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Banco</div>
            <div class="mt-2 text-sm text-white"><?= htmlspecialchars((string) ($lastVerification['database_verification']['message'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          </div>
          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Uploads</div>
            <div class="mt-2 text-sm text-white"><?= htmlspecialchars((string) ($lastVerification['uploads_verification']['message'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
        <div>
          <h2 class="font-orbitron text-lg font-bold text-white">Backups recentes</h2>
          <p class="mt-1 text-sm text-slate-400">A lista abaixo mostra validade, tamanho e o status de envio para nuvem dos pacotes locais.</p>
        </div>
      </div>

      <div class="mt-5 overflow-x-auto">
        <table class="min-w-full border-separate border-spacing-y-3 text-sm text-slate-200">
          <thead>
            <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
              <th class="px-4 py-2">Backup</th>
              <th class="px-4 py-2">Perfil</th>
              <th class="px-4 py-2">Pacote</th>
              <th class="px-4 py-2">Validade</th>
              <th class="px-4 py-2">Nuvem</th>
              <th class="px-4 py-2">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
              <tr class="rounded-2xl border border-slate-800 bg-slate-950/70">
                <td class="px-4 py-4 align-top">
                  <div class="font-semibold text-white"><?= htmlspecialchars((string) ($item['backup_id'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) ($item['created_at'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </td>
                <td class="px-4 py-4 align-top">
                  <div><?= htmlspecialchars((string) ($item['profile_label'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) ($item['profile'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </td>
                <td class="px-4 py-4 align-top">
                  <div>Total: <span class="text-white"><?= htmlspecialchars((string) ($item['total_size'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                  <div class="mt-1 text-xs text-slate-500">Banco: <?= htmlspecialchars((string) ($item['database_size'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <div class="text-xs text-slate-500">Uploads: <?= htmlspecialchars((string) ($item['uploads_size'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </td>
                <td class="px-4 py-4 align-top">
                  <div class="<?= ($item['is_valid'] ?? false) ? 'text-emerald-300' : 'text-rose-300' ?>"><?= ($item['is_valid'] ?? false) ? 'OK' : 'Falhou' ?></div>
                  <div class="mt-1 text-xs text-slate-500">Banco: <?= htmlspecialchars((string) ($item['database_status'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <div class="text-xs text-slate-500">Uploads: <?= htmlspecialchars((string) ($item['uploads_status'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </td>
                <td class="px-4 py-4 align-top">
                  <div class="<?= ($item['cloud_uploaded'] ?? false) ? 'text-emerald-300' : 'text-amber-300' ?>"><?= ($item['cloud_uploaded'] ?? false) ? 'Enviado' : 'Pendente' ?></div>
                  <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) (($item['cloud_uploaded_at'] ?? '') ?: 'Ainda não marcado'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </td>
                <td class="px-4 py-4 align-top">
                  <div class="flex flex-wrap gap-2">
                    <form method="POST" action="<?= url('/local/backup') ?>" class="backup-action-form" data-progress-title="Verificando backup" data-progress-message="Estamos conferindo a integridade do pacote selecionado." data-progress-stage="Verificação">
                      <?= Csrf::field() ?>
                      <input type="hidden" name="action" value="verify">
                      <input type="hidden" name="backup_id" value="<?= htmlspecialchars((string) ($item['backup_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      <button type="submit" class="rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-200 transition hover:bg-emerald-500/20">Verificar</button>
                    </form>
                    <form method="POST" action="<?= url('/local/backup') ?>" class="backup-action-form" data-progress-title="Registrando envio" data-progress-message="Estamos marcando o backup selecionado como enviado para a nuvem." data-progress-stage="Registro">
                      <?= Csrf::field() ?>
                      <input type="hidden" name="action" value="mark_uploaded">
                      <input type="hidden" name="backup_id" value="<?= htmlspecialchars((string) ($item['backup_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      <button type="submit" class="rounded-xl border border-amber-400/30 bg-amber-500/10 px-3 py-2 text-xs font-semibold text-amber-200 transition hover:bg-amber-500/20">Marcar envio</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    (() => {
      const overlay = document.getElementById('backup-progress-overlay');
      const title = document.getElementById('backup-progress-title');
      const message = document.getElementById('backup-progress-message');
      const stage = document.getElementById('backup-progress-stage');
      const fill = document.getElementById('backup-progress-fill');
      const forms = Array.from(document.querySelectorAll('.backup-action-form'));
      let progressTimer = null;
      let locked = false;

      const steps = [10, 18, 28, 38, 52, 66, 78, 86, 92];
      const stageLabels = ['Preparando', 'Conectando', 'Exportando banco', 'Coletando arquivos', 'Compactando', 'Validando', 'Finalizando'];

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

        title.textContent = currentForm.dataset.progressTitle || 'Executando rotina';
        message.textContent = currentForm.dataset.progressMessage || 'Estamos processando sua solicitação.';
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
