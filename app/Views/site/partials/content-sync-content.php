<?php

declare(strict_types=1);

use App\Support\Csrf;

$status = (array) ($content_status ?? []);
$items = (array) ($status['items'] ?? []);
$latest = $status['latest'] ?? null;
$latestStageApply = $status['latest_stage_apply'] ?? null;
$latestProductionApply = $status['latest_production_apply'] ?? null;
$running = is_array($status['running'] ?? null) ? $status['running'] : null;
$lastVerification = is_array($last_verification ?? null) ? $last_verification : null;
$lastPostCheck = is_array($last_post_check ?? null) ? $last_post_check : null;
$stageReady = (bool) ($stage_ready ?? false);
$stageCodeReady = (bool) ($stage_code_ready ?? false);
$productionReady = (bool) ($production_ready ?? false);
$productionCodeReady = (bool) ($production_code_ready ?? false);
$codeStatus = (array) ($code_status ?? []);
$codeItems = (array) ($codeStatus['items'] ?? []);
$codeLatest = is_array($codeStatus['latest'] ?? null) ? $codeStatus['latest'] : null;
$codeLatestStageApply = is_array($codeStatus['latest_stage_apply'] ?? null) ? $codeStatus['latest_stage_apply'] : null;
$codeLatestProductionApply = is_array($codeStatus['latest_production_apply'] ?? null) ? $codeStatus['latest_production_apply'] : null;
$parityStatus = (array) ($parity_status ?? []);
$parityContent = is_array($parityStatus['content'] ?? null) ? $parityStatus['content'] : [];
$parityCode = is_array($parityStatus['code'] ?? null) ? $parityStatus['code'] : [];
$parityRecommendations = array_values(array_map('strval', (array) ($parityStatus['recommendations'] ?? [])));
$deploymentPolicy = (array) ($deployment_policy ?? []);
$recentEditorialApplications = array_values(array_filter((array) ($recent_editorial_applications ?? []), 'is_array'));
$recentCodeApplications = array_values(array_filter((array) ($recent_code_applications ?? []), 'is_array'));
$productionGateOpen = (bool) ($deploymentPolicy['production_allowed'] ?? false);
$productionGateMessage = (string) ($deploymentPolicy['message'] ?? '');
$productionGateReason = (string) ($deploymentPolicy['reason'] ?? '');
$productionReady = $productionReady && $productionGateOpen;
$productionCodeReady = $productionCodeReady && $productionGateOpen;
$stageReadyMessage = $stageReady ? 'Banco e uploads da stage estao disponiveis para validacao.' : 'Complete as variaveis CONTENT_SYNC_STAGE_* para usar a homologacao remota.';
$productionReadyMessage = !$productionGateOpen
    ? ($productionGateMessage !== '' ? $productionGateMessage : 'Publicacao bloqueada pela politica operacional.')
    : ($productionReady ? 'Banco e FTP remotos estao disponiveis para publicar.' : 'Complete as variaveis CONTENT_SYNC_PRODUCTION_* ou use o fallback BACKUP_PRODUCTION_*.');
$contentSection = (string) ($content_section ?? 'resumo');
$latestBySource = [
    'stage' => null,
    'production' => null,
    'local' => null,
];

foreach ($items as $contentItem) {
    $sourceKey = strtolower((string) ($contentItem['source_profile'] ?? ''));
    if (array_key_exists($sourceKey, $latestBySource) && $latestBySource[$sourceKey] === null) {
        $latestBySource[$sourceKey] = $contentItem;
    }
}
?>
<section data-content-sync-panel class="space-y-6">
  <?php if ($contentSection === 'resumo'): ?>
    <?php if ($running !== null): ?>
      <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
        <strong class="font-semibold">Rotina em execucao:</strong>
        <?= htmlspecialchars((string) ($running['profile_label'] ?? 'Fluxo ativo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        <?php if (!empty($running['started_at'])): ?>
          <span class="text-amber-200/90">desde <?= htmlspecialchars((string) $running['started_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($deploymentPolicy !== []): ?>
      <div class="rounded-2xl border px-4 py-3 text-sm <?= $productionGateOpen ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-100' : 'border-amber-500/30 bg-amber-500/10 text-amber-100' ?>">
        <strong class="font-semibold">Politica operacional:</strong>
        origem atual <span class="text-white"><?= htmlspecialchars((string) ($deploymentPolicy['current_source'] ?? 'local'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
        -> origem aprovada <span class="text-white"><?= htmlspecialchars((string) ($deploymentPolicy['approved_source'] ?? 'stage'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
        <div class="mt-2 text-xs <?= $productionGateOpen ? 'text-emerald-200/90' : 'text-amber-100/90' ?>">
          <?= htmlspecialchars($productionGateMessage !== '' ? $productionGateMessage : $productionGateReason, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="grid gap-4 xl:grid-cols-5">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Stage</p>
        <div class="mt-4 font-rajdhani text-2xl font-bold <?= $stageReady ? 'text-emerald-300' : 'text-amber-300' ?>"><?= $stageReady ? 'Pronta' : 'Pendente' ?></div>
        <div class="mt-1 text-slate-400"><?= htmlspecialchars($stageReadyMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Ultimo pacote</p>
        <div class="mt-4 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars((string) ($latest['package_id'] ?? 'Pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <div class="mt-1 text-slate-400"><?= htmlspecialchars((string) ($latest['source_profile_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Paridade</p>
        <div class="mt-4 font-rajdhani text-2xl font-bold <?= ($parityStatus['overall_in_sync'] ?? false) ? 'text-emerald-300' : 'text-amber-300' ?>">
          <?= ($parityStatus['overall_in_sync'] ?? false) ? 'Alinhado' : 'Com divergencia' ?>
        </div>
        <div class="mt-4 grid gap-2 text-sm">
          <div class="flex items-center justify-between"><span class="text-slate-500">Conteudo</span><span class="<?= ($parityContent['in_sync'] ?? false) ? 'text-emerald-300' : 'text-amber-300' ?>"><?= ($parityContent['in_sync'] ?? false) ? 'OK' : 'Pendente' ?></span></div>
          <div class="flex items-center justify-between"><span class="text-slate-500">Codigo</span><span class="<?= ($parityCode['in_sync'] ?? false) ? 'text-emerald-300' : 'text-amber-300' ?>"><?= ($parityCode['in_sync'] ?? false) ? 'OK' : 'Pendente' ?></span></div>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Producao</p>
        <div class="mt-4 font-rajdhani text-2xl font-bold <?= $productionReady ? 'text-emerald-300' : 'text-amber-300' ?>"><?= $productionReady ? 'Pronta' : 'Pendente' ?></div>
        <div class="mt-1 text-slate-400"><?= htmlspecialchars($productionReadyMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="font-orbitron text-xs uppercase tracking-[0.25em] text-cyan-300/80">Ultima verificacao</p>
        <?php if (is_array($lastVerification)): ?>
          <div class="mt-4 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars((string) ($lastVerification['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="mt-1 text-slate-400">Status: <span class="<?= ($lastVerification['is_valid'] ?? false) ? 'text-emerald-300' : 'text-rose-300' ?>"><?= ($lastVerification['is_valid'] ?? false) ? 'Valido' : 'Invalido' ?></span></div>
        <?php else: ?>
          <div class="mt-4 text-slate-400">Nenhuma verificacao nesta sessao.</div>
        <?php endif; ?>
      </div>
      </div>

    <div class="grid gap-6 xl:grid-cols-2">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-bold text-white">Ultimas aplicacoes editoriais</h2>
        <p class="mt-2 text-sm leading-7 text-slate-400">Mostra claramente qual pacote editorial saiu de qual origem e em qual ambiente foi aplicado.</p>
        <div class="mt-5 space-y-3">
          <?php if ($recentEditorialApplications === []): ?>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-4 text-sm text-slate-400">Nenhuma aplicacao editorial registrada ainda.</div>
          <?php else: ?>
            <?php foreach (array_slice($recentEditorialApplications, 0, 5) as $apply): ?>
              <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                <div class="flex items-center justify-between gap-4">
                  <p class="font-semibold text-white"><?= htmlspecialchars((string) ($apply['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                  <span class="text-xs uppercase tracking-[0.2em] text-cyan-300"><?= htmlspecialchars((string) ($apply['applied_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                </div>
                <p class="mt-2 text-sm text-slate-300"><?= htmlspecialchars((string) ($apply['source_profile_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> -> <?= htmlspecialchars((string) ($apply['target_profile_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                <p class="mt-2 text-xs text-slate-500">Origem <span class="text-slate-300"><?= htmlspecialchars((string) ($apply['source_profile'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span> · destino <span class="text-slate-300"><?= htmlspecialchars((string) ($apply['target_profile'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></p>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-bold text-white">Ultimos deploys tecnicos</h2>
        <p class="mt-2 text-sm leading-7 text-slate-400">O mesmo resumo vale para codigo: pacote tecnico, horario e ambiente que recebeu o deploy.</p>
        <div class="mt-5 space-y-3">
          <?php if ($recentCodeApplications === []): ?>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-4 text-sm text-slate-400">Nenhum deploy tecnico registrado ainda.</div>
          <?php else: ?>
            <?php foreach (array_slice($recentCodeApplications, 0, 5) as $apply): ?>
              <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                <div class="flex items-center justify-between gap-4">
                  <p class="font-semibold text-white"><?= htmlspecialchars((string) ($apply['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                  <span class="text-xs uppercase tracking-[0.2em] text-cyan-300"><?= htmlspecialchars((string) ($apply['applied_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                </div>
                <p class="mt-2 text-sm text-slate-300"><?= htmlspecialchars((string) ($apply['source_profile_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> -> <?= htmlspecialchars((string) ($apply['target_profile_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                <p class="mt-2 text-xs text-slate-500">Arquivos aplicados: <span class="text-slate-300"><?= (int) (($apply['result']['files_applied'] ?? 0)) ?></span></p>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php elseif ($contentSection === 'editorial'): ?>
    <div class="space-y-6">
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-bold text-white">Gerar pacote por origem</h2>
        <p class="mt-2 text-sm leading-7 text-slate-400">Antes de sincronizar entre ambientes, gere um pacote novo na origem real que voce quer usar. Isso evita depender de artefato antigo e deixa a operacao coerente com o estado atual de stage ou producao.</p>

        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <form method="POST" action="<?= url('/local/conteudo') ?>" class="content-action-form flex h-full flex-col rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Gerando pacote da stage" data-progress-message="Estamos lendo banco e uploads da stage para montar um pacote editorial atualizado." data-progress-stage="Exportacao stage">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="export">
            <input type="hidden" name="profile" value="stage">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Gerar pacote da stage</p>
            <p class="mt-2 text-sm text-slate-400">Cria um novo pacote editorial com a realidade atual da stage para depois usar em `Stage -> Local` ou `Stage -> Producao`.</p>
            <div class="mt-4 rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-3 text-xs text-slate-400">
              <?= htmlspecialchars($stageReadyMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </div>
            <div class="mt-auto pt-4">
              <button type="submit" class="inline-flex items-center justify-center rounded-2xl border px-4 py-2 text-sm font-semibold transition <?= $stageReady ? 'border-sky-400/40 bg-sky-500/10 text-sky-200 hover:border-sky-300 hover:bg-sky-500/20' : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= $stageReady ? '' : 'disabled' ?>>Gerar pacote stage</button>
            </div>
          </form>

          <form method="POST" action="<?= url('/local/conteudo') ?>" class="content-action-form flex h-full flex-col rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Gerando pacote da producao" data-progress-message="Estamos lendo banco e uploads da producao para montar um pacote editorial atualizado." data-progress-stage="Exportacao producao">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="export">
            <input type="hidden" name="profile" value="production">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Gerar pacote da producao</p>
            <p class="mt-2 text-sm text-slate-400">Cria um novo pacote editorial com o estado atual da producao para depois usar em `Producao -> Stage` ou `Producao -> Local`.</p>
            <div class="mt-4 rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-3 text-xs text-slate-400">
              <?= htmlspecialchars($productionReadyMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </div>
            <div class="mt-auto pt-4">
              <button type="submit" class="inline-flex items-center justify-center rounded-2xl border px-4 py-2 text-sm font-semibold transition <?= $productionReady ? 'border-fuchsia-400/40 bg-fuchsia-500/10 text-fuchsia-200 hover:border-fuchsia-300 hover:bg-fuchsia-500/20' : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= $productionReady ? '' : 'disabled' ?>>Gerar pacote producao</button>
            </div>
          </form>

          <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
            <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Leitura operacional</p>
            <p class="mt-2 text-sm text-slate-400">Os fluxos de sincronizacao abaixo sempre usam o ultimo pacote disponivel da origem correspondente. Se ele estiver antigo, gere um pacote novo primeiro.</p>
            <div class="mt-4 grid gap-2 text-sm text-slate-300">
              <div class="flex items-center justify-between"><span class="text-slate-500">Ultimo da stage</span><span class="text-white"><?= htmlspecialchars((string) (($latestBySource['stage']['package_id'] ?? 'Pendente')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
              <div class="flex items-center justify-between"><span class="text-slate-500">Ultimo da producao</span><span class="text-white"><?= htmlspecialchars((string) (($latestBySource['production']['package_id'] ?? 'Pendente')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
            </div>
          </div>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-bold text-white">Rotas oficiais de sincronizacao editorial</h2>
        <p class="mt-2 text-sm leading-7 text-slate-400">A promocao de conteudo passa a ser tratada como sincronizacao entre ambientes reais. O local continua util para espelho e validacao, mas nao aparece como origem oficial de publicacao.</p>

        <div class="mt-5 grid gap-4 md:grid-cols-2">
          <?php foreach ([
              [
                  'title' => 'Stage -> Local',
                  'source' => 'stage',
                  'target' => 'local',
                  'description' => 'Espelha no local o ultimo pacote editorial validado na stage.',
                  'button' => 'Sincronizar local',
                  'button_class' => 'border-sky-400/40 bg-sky-500/10 text-sky-200 hover:border-sky-300 hover:bg-sky-500/20',
                  'message' => 'Estamos aplicando no local o ultimo pacote editorial vindo da stage.',
                  'stage' => 'Stage para local',
                  'ready' => true,
              ],
              [
                  'title' => 'Stage -> Producao',
                  'source' => 'stage',
                  'target' => 'production',
                  'description' => 'Promocao oficial do ultimo pacote editorial validado na stage para a producao.',
                  'button' => 'Promover para producao',
                  'button_class' => 'border-emerald-400/40 bg-emerald-500/10 text-emerald-200 hover:border-emerald-300 hover:bg-emerald-500/20',
                  'message' => 'Estamos promovendo para a producao o ultimo pacote editorial vindo da stage.',
                  'stage' => 'Stage para producao',
                  'ready' => $productionReady,
              ],
              [
                  'title' => 'Producao -> Stage',
                  'source' => 'production',
                  'target' => 'stage',
                  'description' => 'Restaura a paridade da stage com base no que ja esta publicado em producao.',
                  'button' => 'Atualizar stage',
                  'button_class' => 'border-amber-400/40 bg-amber-500/10 text-amber-200 hover:border-amber-300 hover:bg-amber-500/20',
                  'message' => 'Estamos aplicando na stage o ultimo pacote editorial vindo da producao.',
                  'stage' => 'Producao para stage',
                  'ready' => $stageReady,
              ],
              [
                  'title' => 'Producao -> Local',
                  'source' => 'production',
                  'target' => 'local',
                  'description' => 'Traz para o local o ultimo pacote publicado para reproduzir a realidade de producao.',
                  'button' => 'Trazer para local',
                  'button_class' => 'border-fuchsia-400/40 bg-fuchsia-500/10 text-fuchsia-200 hover:border-fuchsia-300 hover:bg-fuchsia-500/20',
                  'message' => 'Estamos aplicando no local o ultimo pacote editorial vindo da producao.',
                  'stage' => 'Producao para local',
                  'ready' => true,
              ],
          ] as $route): ?>
            <?php $package = $latestBySource[$route['source']] ?? null; ?>
            <?php
            $packageId = is_array($package) ? (string) ($package['package_id'] ?? '') : '';
            $sourceLabel = is_array($package) ? (string) ($package['source_profile_label'] ?? ucfirst((string) $route['source'])) : ucfirst((string) $route['source']);
            $statsText = is_array($package)
                ? sprintf('%d posts, %d links, %d uploads', (int) ($package['stats']['posts'] ?? 0), (int) ($package['stats']['links'] ?? 0), (int) ($package['stats']['uploads'] ?? 0))
                : 'Nenhum pacote disponivel nesta origem.';
            $disabled = $packageId === '' || !($route['ready'] ?? false);
            ?>
            <form method="POST" action="<?= url('/local/conteudo') ?>" class="content-action-form flex h-full flex-col rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="<?= htmlspecialchars((string) $route['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-progress-message="<?= htmlspecialchars((string) $route['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-progress-stage="<?= htmlspecialchars((string) $route['stage'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="apply">
              <input type="hidden" name="package_id" value="<?= htmlspecialchars($packageId !== '' ? $packageId : 'latest', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
              <input type="hidden" name="target_profile" value="<?= htmlspecialchars((string) $route['target'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
              <input type="hidden" name="apply_phrase" value="PUBLICAR">
              <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80"><?= htmlspecialchars((string) $route['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
              <p class="mt-2 text-sm text-slate-400"><?= htmlspecialchars((string) $route['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>

              <div class="mt-4 rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-3 text-sm text-slate-300">
                <div class="flex items-center justify-between gap-4">
                  <span class="text-slate-500">Origem atual</span>
                  <span class="text-white"><?= htmlspecialchars($sourceLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                </div>
                <div class="mt-2 flex items-center justify-between gap-4">
                  <span class="text-slate-500">Ultimo pacote</span>
                  <span class="text-white"><?= htmlspecialchars($packageId !== '' ? $packageId : 'Pendente', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                </div>
                <div class="mt-2 text-xs text-slate-500"><?= htmlspecialchars($statsText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                <?php if (is_array($package['last_apply'] ?? null)): ?>
                  <div class="mt-2 text-xs text-slate-500">Ultima aplicacao registrada: <span class="text-slate-300"><?= htmlspecialchars((string) (($package['last_apply']['target_profile_label'] ?? '-') . ' em ' . ($package['last_apply']['applied_at'] ?? '-')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></div>
                <?php endif; ?>
              </div>

              <div class="mt-auto pt-4">
                <button type="submit" class="inline-flex items-center justify-center rounded-2xl border px-4 py-2 text-sm font-semibold transition <?= !$disabled ? $route['button_class'] : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= !$disabled ? '' : 'disabled' ?>><?= htmlspecialchars((string) $route['button'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
              </div>
            </form>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="grid gap-6 xl:grid-cols-[1.05fr_1fr]">
        <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-lg font-bold text-white">Laboratorio local</h2>
          <p class="mt-2 text-sm leading-7 text-slate-400">Essas acoes continuam disponiveis para montar e validar pacotes em ambiente local, mas nao representam promocao oficial para producao.</p>
          <div class="mt-5 grid gap-4 md:grid-cols-2">
            <form method="POST" action="<?= url('/local/conteudo') ?>" class="content-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Gerando pacote de laboratorio" data-progress-message="Estamos exportando posts, links, configuracoes publicas e uploads referenciados do ambiente local." data-progress-stage="Exportacao local">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="export">
              <input type="hidden" name="profile" value="local">
              <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Exportar local</p>
              <p class="mt-2 text-sm text-slate-400">Monta um pacote de teste com o conteudo atual do ambiente local.</p>
              <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20">Gerar pacote</button>
            </form>

            <form method="POST" action="<?= url('/local/conteudo') ?>" class="content-action-form rounded-2xl border border-slate-800 bg-slate-950/70 p-4" data-progress-title="Verificando ultimo pacote" data-progress-message="Estamos conferindo JSONs, manifesto e o pacote de uploads do ultimo conteudo." data-progress-stage="Verificacao local">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="verify">
              <input type="hidden" name="package_id" value="latest">
              <p class="font-orbitron text-xs uppercase tracking-[0.2em] text-cyan-300/80">Verificar ultimo</p>
              <p class="mt-2 text-sm text-slate-400">Confere integridade dos arquivos JSON e do pacote de uploads.</p>
              <button type="submit" class="mt-4 inline-flex items-center justify-center rounded-2xl border border-emerald-400/40 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-200 transition hover:border-emerald-300 hover:bg-emerald-500/20">Verificar</button>
            </form>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-lg font-bold text-white">Pacotes recentes por origem</h2>
          <div class="mt-5 space-y-3">
            <?php foreach (array_filter([$latestBySource['stage'] ?? null, $latestBySource['production'] ?? null, $latestBySource['local'] ?? null], 'is_array') as $item): ?>
              <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                <div class="flex items-center justify-between gap-4">
                  <p class="font-semibold text-white"><?= htmlspecialchars((string) ($item['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                  <span class="text-xs uppercase tracking-[0.2em] <?= ($item['is_valid'] ?? false) ? 'text-emerald-300' : 'text-rose-300' ?>"><?= ($item['is_valid'] ?? false) ? 'Valido' : 'Invalido' ?></span>
                </div>
                <p class="mt-2 text-sm text-slate-300"><?= htmlspecialchars((string) ($item['source_profile_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> · <?= htmlspecialchars((string) ($item['created_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                <p class="mt-2 text-xs text-slate-500"><?= (int) ($item['stats']['posts'] ?? 0) ?> posts · <?= (int) ($item['stats']['links'] ?? 0) ?> links · <?= (int) ($item['stats']['uploads'] ?? 0) ?> uploads</p>
                <?php if (is_array($item['last_apply'] ?? null)): ?>
                  <p class="mt-2 text-xs text-slate-500">Ultimo destino: <span class="text-slate-300"><?= htmlspecialchars((string) (($item['last_apply']['target_profile_label'] ?? '-') . ' em ' . ($item['last_apply']['applied_at'] ?? '-')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></p>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  <?php elseif ($contentSection === 'codigo'): ?>
    <div class="grid gap-6 xl:grid-cols-[1.05fr_1fr]">
      <div class="rounded-3xl border border-blue-500/20 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-bold text-white">Pacotes de codigo</h2>
        <div class="mt-5 grid gap-4 md:grid-cols-2">
          <?php foreach (array_slice($codeItems, 0, 4) as $codeItem): ?>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <p class="font-semibold text-white"><?= htmlspecialchars((string) ($codeItem['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
              <p class="mt-2 text-sm text-slate-400">Commit <?= htmlspecialchars((string) ($codeItem['commit'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> · <?= (int) ($codeItem['files_count'] ?? 0) ?> arquivos</p>
              <div class="mt-4 flex flex-wrap gap-2">
                <form method="POST" action="<?= url('/local/conteudo') ?>" class="content-action-form" data-progress-title="Publicando pacote de codigo" data-progress-message="Estamos enviando os arquivos do pacote de codigo para a stage." data-progress-stage="Deploy tecnico stage">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="action" value="apply_code">
                  <input type="hidden" name="package_id" value="<?= htmlspecialchars((string) ($codeItem['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                  <input type="hidden" name="target_profile" value="stage">
                  <input type="hidden" name="apply_phrase" value="PUBLICAR">
                  <button type="submit" class="rounded-xl border px-3 py-2 text-xs font-semibold transition <?= $stageCodeReady ? 'border-emerald-400/40 bg-emerald-500/10 text-emerald-200 hover:border-emerald-300 hover:bg-emerald-500/20' : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= $stageCodeReady ? '' : 'disabled' ?>>Publicar stage</button>
                </form>
                <form method="POST" action="<?= url('/local/conteudo') ?>" class="content-action-form" data-progress-title="Publicando pacote de codigo" data-progress-message="Estamos enviando os arquivos do pacote de codigo para a producao." data-progress-stage="Deploy de codigo">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="action" value="apply_code">
                  <input type="hidden" name="package_id" value="<?= htmlspecialchars((string) ($codeItem['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                  <input type="hidden" name="target_profile" value="production">
                  <input type="hidden" name="apply_phrase" value="PUBLICAR">
                  <button type="submit" class="rounded-xl border px-3 py-2 text-xs font-semibold transition <?= $productionCodeReady ? 'border-blue-400/40 bg-blue-500/10 text-blue-200 hover:border-blue-300 hover:bg-blue-500/20' : 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500' ?>" <?= $productionCodeReady ? '' : 'disabled' ?>>Publicar codigo</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-bold text-white">Ultimos deploys</h2>
          <div class="mt-5 space-y-3">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <p class="text-xs uppercase tracking-[0.25em] text-slate-500">Ultimo pacote</p>
              <div class="mt-2 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars((string) ($codeLatest['package_id'] ?? 'Pendente'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <p class="text-sm text-slate-300">Stage: <span class="text-white"><?= htmlspecialchars((string) ($codeLatestStageApply['package_id'] ?? 'Sem aplicacao'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></p>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
              <p class="text-sm text-slate-300">Producao: <span class="text-white"><?= htmlspecialchars((string) ($codeLatestProductionApply['package_id'] ?? 'Sem aplicacao'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></p>
            </div>
            <?php if ($recentCodeApplications !== []): ?>
              <?php $codeApply = $recentCodeApplications[0]; ?>
              <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                <p class="text-xs uppercase tracking-[0.25em] text-slate-500">Ultimo deploy detalhado</p>
                <div class="mt-2 text-sm text-slate-300">Pacote <span class="text-white"><?= htmlspecialchars((string) ($codeApply['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span> aplicado em <span class="text-white"><?= htmlspecialchars((string) ($codeApply['target_profile_label'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>.</div>
                <div class="mt-2 text-xs text-slate-500"><?= htmlspecialchars((string) ($codeApply['applied_at'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> · <?= (int) (($codeApply['result']['files_applied'] ?? 0)) ?> arquivos</div>
              </div>
            <?php endif; ?>
          </div>
        </div>
    </div>
  <?php else: ?>
    <div class="grid gap-6 xl:grid-cols-[1.05fr_1fr]">
      <div class="rounded-3xl border border-amber-500/20 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-bold text-white">Publicacao controlada</h2>
        <p class="mt-2 text-sm leading-7 text-slate-400">Escolha um pacote especifico e confirme com a frase <span class="font-semibold text-white">PUBLICAR</span> antes de aplicar.</p>

        <form method="POST" action="<?= url('/local/conteudo') ?>" class="content-action-form mt-5 space-y-4" data-progress-title="Aplicando pacote selecionado" data-progress-message="Estamos aplicando o pacote escolhido no destino informado. Isso pode sobrescrever conteudo no ambiente de destino." data-progress-stage="Publicacao controlada">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="apply">

          <div>
            <label class="mb-2 block text-sm font-semibold text-slate-200">Pacote</label>
            <select name="package_id" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-cyan-400">
              <option value="latest">Ultimo valido</option>
              <?php foreach ($items as $item): ?>
                <option value="<?= htmlspecialchars((string) ($item['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"><?= htmlspecialchars((string) ($item['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="mb-2 block text-sm font-semibold text-slate-200">Destino</label>
            <select name="target_profile" class="w-full rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-cyan-400">
              <option value="stage">Stage</option>
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

      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <h2 class="font-orbitron text-lg font-bold text-white">Pos-check e recomendacoes</h2>
        <div class="mt-5 space-y-3">
          <?php if (is_array($lastPostCheck)): ?>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-4 text-sm text-slate-300">
              <div class="font-semibold text-white">Ultimo pos-check</div>
              <div class="mt-2">Conteudo: <span class="<?= (($lastPostCheck['content']['in_sync'] ?? false) ? 'text-emerald-300' : 'text-amber-300') ?>"><?= (($lastPostCheck['content']['in_sync'] ?? false) ? 'OK' : 'Pendente') ?></span></div>
              <div>Codigo: <span class="<?= (($lastPostCheck['code']['in_sync'] ?? false) ? 'text-emerald-300' : 'text-amber-300') ?>"><?= (($lastPostCheck['code']['in_sync'] ?? false) ? 'OK' : 'Pendente') ?></span></div>
            </div>
          <?php endif; ?>
          <?php foreach ($parityRecommendations as $recommendation): ?>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm text-slate-300"><?= htmlspecialchars($recommendation, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
</section>
