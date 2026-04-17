<?php

declare(strict_types=1);

use App\Support\Csrf;

$status = (array) ($content_status ?? []);
$items = (array) ($status['items'] ?? []);
$latest = $status['latest'] ?? null;
$latestProductionApply = $status['latest_production_apply'] ?? null;
$running = is_array($status['running'] ?? null) ? $status['running'] : null;
$flash = is_array($flash ?? null) ? $flash : null;
$lastVerification = is_array($last_verification ?? null) ? $last_verification : null;
$lastPostCheck = is_array($last_post_check ?? null) ? $last_post_check : null;
$productionReady = (bool) ($production_ready ?? false);
$productionCodeReady = (bool) ($production_code_ready ?? false);
$codeStatus = (array) ($code_status ?? []);
$codeItems = (array) ($codeStatus['items'] ?? []);
$codeLatest = is_array($codeStatus['latest'] ?? null) ? $codeStatus['latest'] : null;
$codeLatestProductionApply = is_array($codeStatus['latest_production_apply'] ?? null) ? $codeStatus['latest_production_apply'] : null;
$parityStatus = (array) ($parity_status ?? []);
$parityContent = is_array($parityStatus['content'] ?? null) ? $parityStatus['content'] : [];
$parityCode = is_array($parityStatus['code'] ?? null) ? $parityStatus['code'] : [];
$parityRecommendations = array_values(array_map('strval', (array) ($parityStatus['recommendations'] ?? [])));

$alertClasses = [
    'success' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100',
    'error' => 'border-rose-500/40 bg-rose-500/10 text-rose-100',
];
$flashClass = $flash !== null ? ($alertClasses[$flash['type']] ?? $alertClasses['success']) : '';
?>
<section class="min-h-screen bg-slate-950 px-4 py-8 text-slate-100">
  <style>
    .content-progress-overlay {
      position: fixed;
      inset: 0;
      z-index: 9999;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(2, 6, 23, 0.84);
      backdrop-filter: blur(12px);
    }

    .content-progress-overlay.is-visible {
      display: flex;
    }

    .content-progress-card {
      width: min(92vw, 34rem);
      border-radius: 1.75rem;
      border: 1px solid rgba(34, 211, 238, 0.25);
      background: linear-gradient(180deg, rgba(15, 23, 42, 0.96), rgba(2, 6, 23, 0.96));
      padding: 1.5rem;
      box-shadow: 0 0 40px rgba(6, 182, 212, 0.12);
    }

    .content-progress-bar {
      height: 0.8rem;
      overflow: hidden;
      border-radius: 999px;
      background: rgba(30, 41, 59, 0.9);
      border: 1px solid rgba(51, 65, 85, 0.8);
    }

    .content-progress-fill {
      height: 100%;
      width: 8%;
      border-radius: inherit;
      background: linear-gradient(90deg, #22d3ee, #60a5fa, #c084fc);
      box-shadow: 0 0 24px rgba(96, 165, 250, 0.35);
      transition: width 0.4s ease;
    }

    .content-progress-dots span {
      animation: contentBlink 1.2s infinite ease-in-out;
      display: inline-block;
    }

    .content-progress-dots span:nth-child(2) { animation-delay: 0.18s; }
    .content-progress-dots span:nth-child(3) { animation-delay: 0.36s; }

    @keyframes contentBlink {
      0%, 80%, 100% { opacity: 0.25; transform: translateY(0); }
      40% { opacity: 1; transform: translateY(-1px); }
    }
  </style>

  <div id="content-progress-overlay" class="content-progress-overlay" aria-hidden="true">
    <div class="content-progress-card">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Processando</p>
      <h2 id="content-progress-title" class="mt-3 font-orbitron text-2xl font-black text-white">Executando rotina de conteúdo</h2>
      <p id="content-progress-message" class="mt-3 text-sm leading-7 text-slate-300">Estamos preparando o pacote e validando os arquivos. Esse processo pode levar alguns segundos.</p>
      <div class="mt-6 content-progress-bar">
        <div id="content-progress-fill" class="content-progress-fill"></div>
      </div>
      <div class="mt-4 flex items-center justify-between text-xs uppercase tracking-[0.2em] text-slate-400">
        <span id="content-progress-stage">Preparando</span>
        <span class="content-progress-dots"><span>.</span><span>.</span><span>.</span></span>
      </div>
      <p class="mt-4 text-xs text-slate-500">Para evitar envio duplicado, os botões ficam bloqueados até a resposta da página.</p>
    </div>
  </div>
  <div class="mx-auto max-w-7xl space-y-6">
    <?php \App\Support\View::component('site/local-tools-nav', ['active' => 'content']); ?>

    <div class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Rotina Local</p>
          <h1 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Publicacao de Conteudo</h1>
          <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-300">Exporte um pacote com posts, links, configuracoes publicas e uploads referenciados. Depois valide e publique na producao com um passo controlado.</p>
        </div>
        <div class="rounded-2xl border border-slate-700/70 bg-slate-950/70 px-4 py-3 text-sm text-slate-300">
          <div><span class="text-slate-500">Raiz:</span> <?= htmlspecialchars((string) ($status['package_root'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="mt-1"><span class="text-slate-500">Pacotes:</span> <?= (int) ($status['total_packages'] ?? 0) ?></div>
        </div>
      </div>

      <?php if ($flash !== null): ?>
        <div class="mt-4 rounded-2xl border px-4 py-3 text-sm <?= htmlspecialchars($flashClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          <?= htmlspecialchars((string) ($flash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <?php if ($running !== null): ?>
        <div class="mt-4 rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
          <strong class="font-semibold">Rotina em execucao:</strong>
          <?= htmlspecialchars((string) ($running['profile_label'] ?? 'Fluxo ativo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
          <?php if (!empty($running['started_at'])): ?>
            <span class="text-amber-200/90">desde <?= htmlspecialchars((string) $running['started_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="grid gap-4 xl:grid-cols-4">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Ultimo pacote</p>
        <?php if (is_array($latest)): ?>
          <div class="mt-4 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars((string) ($latest['package_id'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="mt-1 text-slate-400"><?= htmlspecialchars((string) ($latest['source_profile_label'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="mt-4 grid gap-2 text-sm">
            <div class="flex items-center justify-between"><span class="text-slate-500">Valido</span><span class="<?= ($latest['is_valid'] ?? false) ? 'text-emerald-300' : 'text-rose-300' ?>"><?= ($latest['is_valid'] ?? false) ? 'OK' : 'Falhou' ?></span></div>
            <div class="flex items-center justify-between"><span class="text-slate-500">Posts</span><span><?= (int) ($latest['stats']['posts'] ?? 0) ?></span></div>
            <div class="flex items-center justify-between"><span class="text-slate-500">Links</span><span><?= (int) ($latest['stats']['links'] ?? 0) ?></span></div>
            <div class="flex items-center justify-between"><span class="text-slate-500">Uploads</span><span><?= (int) ($latest['stats']['uploads'] ?? 0) ?></span></div>
          </div>
          <?php if (($latest['data_files'] ?? []) !== []): ?>
            <div class="mt-4">
              <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Manifesto</p>
              <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-300">
                <?php foreach ((array) ($latest['data_files'] ?? []) as $dataFile): ?>
                  <span class="rounded-full border border-slate-700 bg-slate-950/80 px-3 py-1"><?= htmlspecialchars((string) $dataFile, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <p class="mt-4 text-slate-400">Nenhum pacote gerado ainda.</p>
        <?php endif; ?>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Paridade local x producao</p>
        <div class="mt-4 font-rajdhani text-2xl font-bold <?= ($parityStatus['overall_in_sync'] ?? false) ? 'text-emerald-300' : 'text-amber-300' ?>">
          <?= ($parityStatus['overall_in_sync'] ?? false) ? 'Alinhado' : 'Com divergencia' ?>
        </div>
        <div class="mt-4 grid gap-2 text-sm">
          <div class="flex items-center justify-between"><span class="text-slate-500">Conteudo</span><span class="<?= ($parityContent['in_sync'] ?? false) ? 'text-emerald-300' : 'text-amber-300' ?>"><?= ($parityContent['in_sync'] ?? false) ? 'OK' : 'Pendente' ?></span></div>
          <div class="flex items-center justify-between"><span class="text-slate-500">Codigo</span><span class="<?= ($parityCode['in_sync'] ?? false) ? 'text-emerald-300' : 'text-amber-300' ?>"><?= ($parityCode['in_sync'] ?? false) ? 'OK' : 'Pendente' ?></span></div>
        </div>
        <?php if ($parityRecommendations !== []): ?>
          <div class="mt-4 space-y-2 text-xs leading-6 text-slate-300">
            <?php foreach ($parityRecommendations as $recommendation): ?>
              <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-3 py-2"><?= htmlspecialchars($recommendation, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Producao</p>
        <div class="mt-4 font-rajdhani text-2xl font-bold <?= $productionReady ? 'text-emerald-300' : 'text-amber-300' ?>"><?= $productionReady ? 'Pronta' : 'Pendente' ?></div>
        <div class="mt-1 text-slate-400"><?= $productionReady ? 'Banco e FTP remotos estao disponiveis para publicar.' : 'Complete as variaveis CONTENT_SYNC_PRODUCTION_* ou use o fallback BACKUP_PRODUCTION_*.' ?></div>
        <?php if (is_array($latestProductionApply)): ?>
          <div class="mt-4 text-sm text-slate-300">Ultima publicacao: <span class="text-white"><?= htmlspecialchars((string) ($latestProductionApply['package_id'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
          <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) ($latestProductionApply['applied_at'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <?php endif; ?>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Ultima verificacao</p>
        <?php if (is_array($lastVerification)): ?>
          <div class="mt-4 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars((string) ($lastVerification['package_id'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="mt-1 text-slate-400">Resultado mais recente da checagem do pacote.</div>
          <div class="mt-4 text-sm text-slate-300">Status: <span class="<?= ($lastVerification['is_valid'] ?? false) ? 'text-emerald-300' : 'text-rose-300' ?>"><?= ($lastVerification['is_valid'] ?? false) ? 'Valido' : 'Invalido' ?></span></div>
        <?php else: ?>
          <div class="mt-4 text-slate-400">Nenhuma verificacao executada nesta sessao.</div>
        <?php endif; ?>
        <?php if (is_array($lastPostCheck)): ?>
          <div class="mt-4 rounded-2xl border border-slate-800 bg-slate-950/70 px-3 py-3 text-xs text-slate-300">
            <div class="font-semibold text-white">Pos-check mais recente</div>
            <div class="mt-1">Conteudo: <span class="<?= (($lastPostCheck['content']['in_sync'] ?? false) ? 'text-emerald-300' : 'text-amber-300') ?>"><?= (($lastPostCheck['content']['in_sync'] ?? false) ? 'OK' : 'Pendente') ?></span></div>
            <div>Codigo: <span class="<?= (($lastPostCheck['code']['in_sync'] ?? false) ? 'text-emerald-300' : 'text-amber-300') ?>"><?= (($lastPostCheck['code']['in_sync'] ?? false) ? 'OK' : 'Pendente') ?></span></div>
            <div class="mt-1 text-slate-500"><?= htmlspecialchars((string) ($lastPostCheck['checked_at'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="rounded-3xl border border-blue-500/20 bg-slate-900/80 p-6">
      <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
        <div>
          <h2 class="font-orbitron text-lg font-bold text-white">Pacotes de codigo (sem banco)</h2>
          <p class="mt-1 text-sm text-slate-400">Use estes pacotes para deploy tecnico. Nao inclui posts, links ou qualquer dado do banco.</p>
        </div>
        <div class="text-xs text-slate-400">
          <span class="text-slate-500">Raiz:</span>
          <?= htmlspecialchars((string) ($codeStatus['package_root'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
          <span class="mx-2 text-slate-600">•</span>
          <span class="text-slate-500">Pacotes:</span> <?= (int) ($codeStatus['total_packages'] ?? 0) ?>
        </div>
      </div>

      <?php if (is_array($codeLatestProductionApply)): ?>
        <div class="mt-4 rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm text-slate-300">
          <span class="text-slate-500">Ultimo pacote tecnico em producao:</span>
          <span class="ml-2 font-semibold text-white"><?= htmlspecialchars((string) ($codeLatestProductionApply['package_id'] ?? 'â€”'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
          <span class="ml-2 text-xs text-slate-500"><?= htmlspecialchars((string) ($codeLatestProductionApply['applied_at'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
        </div>
      <?php endif; ?>

      <?php if ($codeLatest !== null): ?>
        <div class="mt-4 rounded-2xl border border-slate-800 bg-slate-950/70 p-4 text-sm">
          <div class="text-white font-semibold"><?= htmlspecialchars((string) ($codeLatest['package_id'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="mt-1 text-slate-400">Commit: <span class="text-slate-200"><?= htmlspecialchars((string) ($codeLatest['commit'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span> • Arquivos: <span class="text-slate-200"><?= (int) ($codeLatest['files_count'] ?? 0) ?></span></div>
          <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) ($codeLatest['created_at'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="mt-2 text-xs text-cyan-300 break-all"><?= htmlspecialchars((string) ($codeLatest['zip_path'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <?php if (($codeLatest['files_preview'] ?? []) !== []): ?>
            <div class="mt-4">
              <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Manifesto tecnico</p>
              <div class="mt-2 space-y-1 text-xs text-slate-300">
                <?php foreach ((array) ($codeLatest['files_preview'] ?? []) as $filePath): ?>
                  <div class="rounded-xl border border-slate-800 bg-slate-900/70 px-3 py-2"><?= htmlspecialchars((string) $filePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <?php endforeach; ?>
                <?php if ((int) ($codeLatest['files_extra'] ?? 0) > 0): ?>
                  <div class="text-slate-500">+ <?= (int) ($codeLatest['files_extra'] ?? 0) ?> arquivo(s) no pacote</div>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
          <?php if (is_array($codeLatest['last_apply'] ?? null)): ?>
            <div class="mt-4 text-xs text-slate-300">
              Ultima aplicacao: <span class="text-white"><?= htmlspecialchars((string) ($codeLatest['last_apply']['target_profile_label'] ?? 'â€”'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
              <span class="ml-2 text-slate-500"><?= htmlspecialchars((string) ($codeLatest['last_apply']['applied_at'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            </div>
          <?php endif; ?>
          <form method="POST" action="<?= url('/local/conteudo') ?>" class="content-action-form mt-3" data-progress-title="Publicando pacote de código" data-progress-message="Estamos enviando os arquivos do pacote de código para a produção." data-progress-stage="Deploy de código">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="apply_code">
            <input type="hidden" name="package_id" value="<?= htmlspecialchars((string) ($codeLatest['package_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <input type="hidden" name="target_profile" value="production">
            <input type="hidden" name="apply_phrase" value="PUBLICAR">
            <button type="submit" class="rounded-xl border px-3 py-2 text-xs font-semibold transition <?= $productionCodeReady ? 'border-blue-400/40 bg-blue-500/10 text-blue-200 hover:border-blue-300 hover:bg-blue-500/20' : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= $productionCodeReady ? '' : 'disabled' ?>>Publicar último pacote de código</button>
          </form>
        </div>
      <?php else: ?>
        <div class="mt-4 rounded-2xl border border-slate-800 bg-slate-950/70 p-4 text-sm text-slate-400">Nenhum pacote de codigo encontrado.</div>
      <?php endif; ?>

      <?php if ($codeItems !== []): ?>
        <div class="mt-5 overflow-x-auto">
          <table class="min-w-full border-separate border-spacing-y-3 text-sm text-slate-200">
            <thead>
              <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                <th class="px-4 py-2">Pacote</th>
                <th class="px-4 py-2">Commit</th>
                <th class="px-4 py-2">Arquivos</th>
                <th class="px-4 py-2">Criado em</th>
                <th class="px-4 py-2">Manifesto</th>
                <th class="px-4 py-2">Ultima aplicacao</th>
                <th class="px-4 py-2">Zip</th>
                <th class="px-4 py-2">Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($codeItems as $codeItem): ?>
                <tr class="rounded-2xl border border-slate-800 bg-slate-950/70">
                  <td class="px-4 py-4 align-top font-semibold text-white"><?= htmlspecialchars((string) ($codeItem['package_id'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                  <td class="px-4 py-4 align-top text-slate-300"><?= htmlspecialchars((string) ($codeItem['commit'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                  <td class="px-4 py-4 align-top text-slate-300"><?= (int) ($codeItem['files_count'] ?? 0) ?></td>
                  <td class="px-4 py-4 align-top text-xs text-slate-400"><?= htmlspecialchars((string) ($codeItem['created_at'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                  <td class="px-4 py-4 align-top text-xs text-slate-300">
                    <?php if (($codeItem['files_preview'] ?? []) !== []): ?>
                      <?php foreach ((array) ($codeItem['files_preview'] ?? []) as $filePath): ?>
                        <div class="mb-2 rounded-xl border border-slate-800 bg-slate-900/70 px-3 py-2 last:mb-0"><?= htmlspecialchars((string) $filePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                      <?php endforeach; ?>
                      <?php if ((int) ($codeItem['files_extra'] ?? 0) > 0): ?>
                        <div class="text-slate-500">+ <?= (int) ($codeItem['files_extra'] ?? 0) ?> arquivo(s)</div>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="text-slate-500">Sem lista</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-4 align-top text-xs text-slate-300">
                    <?php if (is_array($codeItem['last_apply'] ?? null)): ?>
                      <div><?= htmlspecialchars((string) ($codeItem['last_apply']['target_profile_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                      <div class="mt-1 text-slate-500"><?= htmlspecialchars((string) ($codeItem['last_apply']['applied_at'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    <?php else: ?>
                      <span class="text-slate-500">Ainda nao aplicado</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-4 align-top text-xs text-cyan-300 break-all"><?= htmlspecialchars((string) ($codeItem['zip_path'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                  <td class="px-4 py-4 align-top">
                    <form method="POST" action="<?= url('/local/conteudo') ?>" class="content-action-form" data-progress-title="Publicando pacote de código" data-progress-message="Estamos enviando os arquivos do pacote de código para a produção." data-progress-stage="Deploy de código">
                      <?= Csrf::field() ?>
                      <input type="hidden" name="action" value="apply_code">
                      <input type="hidden" name="package_id" value="<?= htmlspecialchars((string) ($codeItem['package_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      <input type="hidden" name="target_profile" value="production">
                      <input type="hidden" name="apply_phrase" value="PUBLICAR">
                      <button type="submit" class="rounded-xl border px-3 py-2 text-xs font-semibold transition <?= $productionCodeReady ? 'border-blue-400/40 bg-blue-500/10 text-blue-200 hover:border-blue-300 hover:bg-blue-500/20' : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= $productionCodeReady ? '' : 'disabled' ?>>Publicar código</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.05fr_1fr]">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-bold text-white">Acoes rapidas</h2>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
          <form method="POST" action="<?= url('/local/conteudo') ?>" class="content-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Gerando pacote de conteúdo" data-progress-message="Estamos exportando posts, links, configurações públicas e uploads referenciados do ambiente local." data-progress-stage="Exportação">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="export">
            <input type="hidden" name="profile" value="local">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Exportar local</p>
            <p class="mt-2 text-sm text-slate-400">Monta um pacote novo com conteudo do ambiente local.</p>
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20">Gerar pacote</button>
          </form>

          <form method="POST" action="<?= url('/local/conteudo') ?>" class="content-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Verificando pacote" data-progress-message="Estamos conferindo JSONs, manifesto e o pacote de uploads do último conteúdo." data-progress-stage="Verificação">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="verify">
            <input type="hidden" name="package_id" value="latest">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Verificar ultimo</p>
            <p class="mt-2 text-sm text-slate-400">Confere integridade dos arquivos JSON e do pacote de uploads.</p>
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border border-emerald-400/40 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-200 transition hover:border-emerald-300 hover:bg-emerald-500/20">Verificar</button>
          </form>

          <form method="POST" action="<?= url('/local/conteudo') ?>" class="content-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Aplicando pacote no local" data-progress-message="Estamos importando o pacote selecionado no ambiente local para validar o fluxo completo." data-progress-stage="Aplicação local">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="apply">
            <input type="hidden" name="package_id" value="latest">
            <input type="hidden" name="target_profile" value="local">
            <input type="hidden" name="apply_phrase" value="PUBLICAR">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Aplicar no local</p>
            <p class="mt-2 text-sm text-slate-400">Bom para validar o fluxo completo sem tocar na producao.</p>
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border border-sky-400/40 bg-sky-500/10 px-4 py-2 text-sm font-semibold text-sky-200 transition hover:border-sky-300 hover:bg-sky-500/20">Aplicar local</button>
          </form>

          <form method="POST" action="<?= url('/local/conteudo') ?>" class="content-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Publicando conteúdo na produção" data-progress-message="Estamos enviando o pacote validado para o banco e os uploads da produção." data-progress-stage="Publicação">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="apply">
            <input type="hidden" name="package_id" value="latest">
            <input type="hidden" name="target_profile" value="production">
            <input type="hidden" name="apply_phrase" value="PUBLICAR">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Publicar ultimo</p>
            <p class="mt-2 text-sm text-slate-400">Envia o ultimo pacote valido para o banco e uploads da producao.</p>
            <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border px-4 py-2 text-sm font-semibold transition <?= $productionReady ? 'border-fuchsia-400/40 bg-fuchsia-500/10 text-fuchsia-200 hover:border-fuchsia-300 hover:bg-fuchsia-500/20' : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= $productionReady ? '' : 'disabled' ?>>Publicar producao</button>
          </form>
        </div>
      </div>

      <div class="rounded-3xl border border-amber-500/20 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-bold text-white">Publicacao controlada</h2>
        <p class="mt-2 text-sm leading-7 text-slate-400">Escolha um pacote especifico e confirme com a frase <span class="font-semibold text-white">PUBLICAR</span> antes de aplicar.</p>

        <form method="POST" action="<?= url('/local/conteudo') ?>" class="content-action-form mt-5 space-y-4" data-progress-title="Aplicando pacote selecionado" data-progress-message="Estamos aplicando o pacote escolhido no destino informado. Isso pode sobrescrever conteúdo no ambiente de destino." data-progress-stage="Publicação controlada">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="apply">

          <div>
            <label class="mb-2 block text-sm font-semibold text-slate-200">Pacote</label>
            <select name="package_id" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-cyan-400">
              <option value="latest">Ultimo valido</option>
              <?php foreach ($items as $item): ?>
                <option value="<?= htmlspecialchars((string) ($item['package_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars((string) ($item['package_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="mb-2 block text-sm font-semibold text-slate-200">Destino</label>
            <select name="target_profile" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-cyan-400">
              <option value="production">Producao</option>
              <option value="local">Local</option>
            </select>
          </div>

          <div>
            <label class="mb-2 block text-sm font-semibold text-slate-200">Confirmacao</label>
            <input type="text" name="apply_phrase" placeholder="Digite PUBLICAR" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-amber-400">
          </div>

          <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-amber-400/40 bg-amber-500/10 px-5 py-3 text-sm font-semibold text-amber-200 transition hover:border-amber-300 hover:bg-amber-500/20">Aplicar pacote</button>
        </form>
      </div>
    </div>

    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <h2 class="font-orbitron text-lg font-bold text-white">Pacotes recentes</h2>
      <div class="mt-5 overflow-x-auto">
        <table class="min-w-full border-separate border-spacing-y-3 text-sm text-slate-200">
          <thead>
            <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
              <th class="px-4 py-2">Pacote</th>
              <th class="px-4 py-2">Origem</th>
              <th class="px-4 py-2">Conteudo</th>
              <th class="px-4 py-2">Status</th>
              <th class="px-4 py-2">Ultima aplicacao</th>
              <th class="px-4 py-2">Acoes</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
              <tr class="rounded-2xl border border-slate-800 bg-slate-950/70">
                <td class="px-4 py-4 align-top">
                  <div class="font-semibold text-white"><?= htmlspecialchars((string) ($item['package_id'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) ($item['created_at'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </td>
                <td class="px-4 py-4 align-top">
                  <div><?= htmlspecialchars((string) ($item['source_profile_label'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <div class="mt-1 text-xs text-slate-500"><?= htmlspecialchars((string) ($item['source_profile'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                </td>
                <td class="px-4 py-4 align-top text-xs text-slate-300">
                  <div>Posts: <?= (int) ($item['stats']['posts'] ?? 0) ?></div>
                  <div>Links: <?= (int) ($item['stats']['links'] ?? 0) ?></div>
                  <div>Configs: <?= (int) ($item['stats']['configuracoes'] ?? 0) ?></div>
                  <div>Uploads: <?= (int) ($item['stats']['uploads'] ?? 0) ?></div>
                </td>
                <td class="px-4 py-4 align-top">
                  <div class="<?= ($item['is_valid'] ?? false) ? 'text-emerald-300' : 'text-rose-300' ?>"><?= ($item['is_valid'] ?? false) ? 'Valido' : 'Invalido' ?></div>
                </td>
                <td class="px-4 py-4 align-top text-xs text-slate-300">
                  <?php if (is_array($item['last_apply'] ?? null)): ?>
                    <div><?= htmlspecialchars((string) ($item['last_apply']['target_profile_label'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                    <div class="mt-1 text-slate-500"><?= htmlspecialchars((string) ($item['last_apply']['applied_at'] ?? '—'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                  <?php else: ?>
                    <span class="text-slate-500">Ainda nao aplicado</span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-4 align-top">
                  <div class="flex flex-wrap gap-2">
                    <form method="POST" action="<?= url('/local/conteudo') ?>" class="content-action-form" data-progress-title="Verificando pacote" data-progress-message="Estamos conferindo a integridade do pacote selecionado." data-progress-stage="Verificação">
                      <?= Csrf::field() ?>
                      <input type="hidden" name="action" value="verify">
                      <input type="hidden" name="package_id" value="<?= htmlspecialchars((string) ($item['package_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      <button type="submit" class="rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-200 transition hover:bg-emerald-500/20">Verificar</button>
                    </form>
                    <form method="POST" action="<?= url('/local/conteudo') ?>" class="content-action-form" data-progress-title="Publicando pacote" data-progress-message="Estamos aplicando o pacote selecionado em produção." data-progress-stage="Publicação">
                      <?= Csrf::field() ?>
                      <input type="hidden" name="action" value="apply">
                      <input type="hidden" name="package_id" value="<?= htmlspecialchars((string) ($item['package_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                      <input type="hidden" name="target_profile" value="production">
                      <input type="hidden" name="apply_phrase" value="PUBLICAR">
                      <button type="submit" class="rounded-xl border border-fuchsia-400/30 bg-fuchsia-500/10 px-3 py-2 text-xs font-semibold text-fuchsia-200 transition hover:bg-fuchsia-500/20" <?= $productionReady ? '' : 'disabled' ?>>Publicar</button>
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
      const overlay = document.getElementById('content-progress-overlay');
      const title = document.getElementById('content-progress-title');
      const message = document.getElementById('content-progress-message');
      const stage = document.getElementById('content-progress-stage');
      const fill = document.getElementById('content-progress-fill');
      const forms = Array.from(document.querySelectorAll('.content-action-form'));
      let progressTimer = null;
      let locked = false;

      const steps = [10, 18, 28, 38, 52, 66, 78, 86, 92];
      const stageLabels = ['Preparando', 'Lendo conteúdo', 'Montando pacote', 'Conferindo uploads', 'Validando', 'Conectando destino', 'Finalizando'];

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

        title.textContent = currentForm.dataset.progressTitle || 'Executando rotina de conteúdo';
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
