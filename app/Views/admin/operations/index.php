<?php
declare(strict_types=1);

$flash = is_array($flash ?? null) ? $flash : null;
$lastRun = is_array($last_run ?? null) ? $last_run : null;
$syncStatus = is_array($sync_status ?? null) ? $sync_status : [];
$productionPackages = array_values((array) ($syncStatus['production_packages'] ?? []));
$latestStageApply = is_array($syncStatus['latest_stage_apply'] ?? null) ? $syncStatus['latest_stage_apply'] : null;
$latestProductionApply = is_array($syncStatus['latest_production_apply'] ?? null) ? $syncStatus['latest_production_apply'] : null;
$stageReady = (bool) ($syncStatus['stage_ready'] ?? false);
$productionReady = (bool) ($syncStatus['production_ready'] ?? false);
$confirmationPhrase = (string) ($requires_confirmation_phrase ?? 'SINCRONIZAR STAGE');
$syncEnabled = $stageReady && $productionReady;

$formatAdminDate = static function (?string $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return 'Sem registro.';
    }

    try {
        return (new DateTimeImmutable($raw))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i:s');
    } catch (Throwable) {
        return $raw;
    }
};
?>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">
  <div class="admin-page-header">
    <div class="admin-page-heading">
      <h1 class="admin-page-title">Central Operacional</h1>
      <div class="admin-page-subtitle">Sincronizacao editorial controlada entre producao e stage, executada somente no admin local.</div>
    </div>
    <div class="admin-page-actions">
      <div class="admin-chip border-cyan-500/30 text-cyan-200">Somente local</div>
      <div class="admin-chip">Pacotes de producao: <?= number_format((int) ($syncStatus['total_production_packages'] ?? 0), 0, ',', '.') ?></div>
    </div>
  </div>

  <?php if ($flash !== null): ?>
    <?php $isSuccess = (string) ($flash['type'] ?? '') === 'success'; ?>
    <section class="admin-panel border <?= $isSuccess ? 'border-emerald-500/30' : 'border-rose-500/30' ?>">
      <div class="text-sm font-bold <?= $isSuccess ? 'text-emerald-200' : 'text-rose-200' ?>">
        <?= $isSuccess ? 'Operacao concluida.' : 'Operacao interrompida.' ?>
      </div>
      <div class="mt-2 text-sm text-slate-300"><?= htmlspecialchars((string) ($flash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    </section>
  <?php endif; ?>

  <?php if ($lastRun !== null): ?>
    <section class="admin-panel border border-cyan-500/20">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <div class="text-sm font-bold text-cyan-200">Ultima sincronizacao concluida</div>
          <div class="mt-1 text-xs text-slate-400">Resumo da ultima operacao executada nesta sessao.</div>
        </div>
        <div class="admin-chip">Operacao: <?= htmlspecialchars((string) ($lastRun['operation_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </div>

      <div class="mt-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 text-sm">
        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
          <div class="text-slate-400">Pacote aplicado</div>
          <div class="mt-2 font-bold text-white break-all"><?= htmlspecialchars((string) ($lastRun['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
          <div class="text-slate-400">Backup preventivo</div>
          <div class="mt-2 font-bold text-white break-all"><?= htmlspecialchars((string) ($lastRun['pre_apply_backup_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
          <div class="text-slate-400">Posts no pacote</div>
          <div class="mt-2 font-bold text-white"><?= (int) ($lastRun['verification']['stats']['posts'] ?? 0) ?></div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
          <div class="text-slate-400">Uploads incluidos</div>
          <div class="mt-2 font-bold text-white"><?= (int) ($lastRun['verification']['uploads_included'] ?? 0) ?></div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
          <div class="text-slate-400">Links no pacote</div>
          <div class="mt-2 font-bold text-white"><?= (int) ($lastRun['verification']['stats']['links'] ?? 0) ?></div>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
          <div class="text-slate-400">Configs editoriais</div>
          <div class="mt-2 font-bold text-white"><?= (int) ($lastRun['verification']['stats']['configuracoes'] ?? 0) ?></div>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
    <article class="admin-panel">
      <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Stage pronta</div>
      <div class="mt-3 font-orbitron text-3xl font-black <?= $stageReady ? 'text-emerald-300' : 'text-amber-300' ?>"><?= $stageReady ? 'SIM' : 'PENDENTE' ?></div>
      <div class="mt-2 text-sm text-slate-400">Backup e aplicacao editorial precisam estar configurados para a stage.</div>
    </article>
    <article class="admin-panel">
      <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Producao pronta</div>
      <div class="mt-3 font-orbitron text-3xl font-black <?= $productionReady ? 'text-emerald-300' : 'text-amber-300' ?>"><?= $productionReady ? 'SIM' : 'PENDENTE' ?></div>
      <div class="mt-2 text-sm text-slate-400">A exportacao editorial da producao precisa estar configurada para gerar o pacote.</div>
    </article>
    <article class="admin-panel">
      <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Ultima stage aplicada</div>
      <div class="mt-3 font-bold text-white break-all"><?= htmlspecialchars((string) ($latestStageApply['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <div class="mt-2 text-sm text-slate-400"><?= htmlspecialchars($formatAdminDate((string) ($latestStageApply['applied_at'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    </article>
    <article class="admin-panel">
      <div class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Ultimo pacote prod aplicado</div>
      <div class="mt-3 font-bold text-white break-all"><?= htmlspecialchars((string) ($latestProductionApply['package_id'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      <div class="mt-2 text-sm text-slate-400"><?= htmlspecialchars($formatAdminDate((string) ($latestProductionApply['applied_at'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    </article>
  </section>

  <section class="admin-panel border border-amber-500/20">
    <div class="text-sm font-bold text-amber-200">Fluxo da operacao</div>
    <div class="mt-2 text-sm text-slate-300">A sincronizacao percorre o ciclo completo de espelhamento editorial: leitura da producao, montagem do pacote, verificacao de integridade, backup preventivo da stage e aplicacao final do banco com os uploads referenciados.</div>
    <div class="mt-2 text-sm text-slate-400">Escopo desta V1: categorias, posts, links, SEO editorial, proximos passos e uploads referenciados. Nada de usuarios, configuracoes estruturais ou banco inteiro.</div>
    <div class="mt-2 text-sm text-slate-500">O seletor global de ambiente do admin nao altera esta rotina. Aqui a origem continua fixa em <strong class="text-slate-200">Producao</strong> e o destino em <strong class="text-slate-200">Stage</strong>.</div>
  </section>

  <section class="admin-panel">
    <div class="flex items-center justify-between gap-4 flex-wrap mb-5">
      <div>
        <h2 class="font-orbitron text-lg font-black text-white">Sincronizacao producao -> stage</h2>
        <div class="text-xs text-slate-400 mt-1">Rotina local para espelhar apenas o conteudo editorial da producao na stage.</div>
      </div>
    </div>

    <form
      method="POST"
      action="<?= htmlspecialchars(url('/admin/central-operacional/sincronizar-conteudo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
      class="space-y-5"
      data-admin-progress-form
      data-progress-title="Sincronizando conteudo"
      data-progress-message="Estamos exportando o conteudo editorial da producao, verificando o pacote, criando o backup preventivo da stage e aplicando o resultado validado."
      data-progress-stage="Sincronizacao producao -> stage"
      data-progress-steps='<?= htmlspecialchars((string) json_encode([
          ['label' => 'Conectando na producao', 'message' => 'Abrindo a conexao remota para ler a base editorial da producao com seguranca.'],
          ['label' => 'Lendo categorias', 'message' => 'Coletando as categorias editoriais usadas pelo conteudo que sera espelhado na stage.'],
          ['label' => 'Lendo posts', 'message' => 'Consultando os posts, relacionamentos editoriais e proximos passos registrados na producao.'],
          ['label' => 'Lendo links e SEO', 'message' => 'Separando links e configuracoes editoriais publicas que acompanham o conteudo.'],
          ['label' => 'Separando uploads usados', 'message' => 'Mapeando imagens, audios e demais arquivos realmente referenciados pelo conteudo exportado.'],
          ['label' => 'Compactando o pacote', 'message' => 'Montando o pacote editorial completo da producao para aplicar na stage de forma consistente.'],
          ['label' => 'Validando manifesto', 'message' => 'Conferindo manifestos, JSONs e referencias de arquivos antes de qualquer sobrescrita na stage.'],
          ['label' => 'Gerando backup da stage', 'message' => 'Criando um backup preventivo do conteudo atual da stage para garantir retorno seguro, se necessario.'],
          ['label' => 'Aplicando banco na stage', 'message' => 'Atualizando os dados editoriais da stage com o pacote validado vindo da producao.'],
          ['label' => 'Aplicando uploads na stage', 'message' => 'Copiando os arquivos referenciados para manter a stage coerente com o conteudo editorial sincronizado.'],
          ['label' => 'Finalizando sincronizacao', 'message' => 'Fechando a operacao, consolidando o resultado e preparando o retorno da tela com o resumo final.'],
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>'
    >
      <?= \App\Support\Csrf::field() ?>

      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 text-sm text-slate-300">
        <div><strong class="text-white">Origem:</strong> Producao</div>
        <div class="mt-1"><strong class="text-white">Destino:</strong> Stage</div>
        <div class="mt-1"><strong class="text-white">Impacto:</strong> o conteudo atual da stage sera sobrescrito pelo pacote mais novo exportado da producao.</div>
      </div>

      <label class="block">
        <span class="mb-2 block text-xs font-bold uppercase tracking-[0.16em] text-slate-400">Confirmacao obrigatoria</span>
        <input
          type="text"
          name="confirmation_phrase"
          value=""
          placeholder="<?= htmlspecialchars($confirmationPhrase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
          class="w-full rounded-2xl border border-amber-500/30 bg-slate-950/80 px-4 py-3 text-sm text-white outline-none focus:border-amber-300"
          autocomplete="off"
          data-confirmation-input
        >
        <span class="mt-2 block text-xs text-slate-500">Digite exatamente <strong class="text-amber-200"><?= htmlspecialchars($confirmationPhrase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong> para liberar a rotina.</span>
        <span class="mt-2 block text-xs font-bold uppercase tracking-[0.14em] text-amber-300" data-confirmation-status>
          <?= $syncEnabled ? 'Aguardando confirmacao para liberar a execucao.' : 'A sincronizacao esta indisponivel ate os perfis ficarem prontos.' ?>
        </span>
      </label>

      <div class="flex flex-wrap items-center gap-3">
        <button
          type="submit"
          data-progress-submit
          data-confirmation-submit
          class="admin-btn admin-btn-primary"
          <?= $syncEnabled ? 'disabled' : 'disabled' ?>
        >
          <i class="fa-solid fa-rotate"></i>Sincronizar conteudo
        </button>
        <?php if (!$syncEnabled): ?>
          <span class="text-sm text-amber-300">Complete os perfis de stage e producao antes de executar a sincronizacao.</span>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <section class="admin-panel">
    <div class="flex items-center justify-between gap-4 flex-wrap mb-5">
      <div>
        <h2 class="font-orbitron text-lg font-black text-white">Pacotes recentes de producao</h2>
        <div class="text-xs text-slate-400 mt-1">Leitura dos pacotes de conteudo cuja origem registrada ja e a producao.</div>
        <div class="mt-2 text-xs text-slate-500 break-all">Raiz dos pacotes: <?= htmlspecialchars((string) ($syncStatus['package_root'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
      </div>
    </div>

    <?php if ($productionPackages === []): ?>
      <div class="rounded-2xl border border-slate-800 bg-slate-900/40 p-5 text-sm text-slate-400">Nenhum pacote de conteudo com origem em producao foi encontrado ainda.</div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-slate-400">
              <th class="px-4 py-3 font-semibold">Pacote</th>
              <th class="px-4 py-3 font-semibold">Criado em</th>
              <th class="px-4 py-3 font-semibold">Posts</th>
              <th class="px-4 py-3 font-semibold">Links</th>
              <th class="px-4 py-3 font-semibold">Configs</th>
              <th class="px-4 py-3 font-semibold">Uploads</th>
              <th class="px-4 py-3 font-semibold">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($productionPackages as $package): ?>
              <tr class="border-t border-slate-800 text-slate-200">
                <td class="px-4 py-3 font-semibold text-white break-all">
                  <div class="flex flex-wrap items-center gap-2">
                    <span><?= htmlspecialchars((string) ($package['package_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                    <?php if ($package === ($productionPackages[0] ?? null)): ?>
                      <span class="inline-flex rounded-full bg-cyan-500/15 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.14em] text-cyan-200">Mais recente</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="px-4 py-3"><?= htmlspecialchars($formatAdminDate((string) ($package['created_at'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                <td class="px-4 py-3"><?= (int) ($package['posts'] ?? 0) ?></td>
                <td class="px-4 py-3"><?= (int) ($package['links'] ?? 0) ?></td>
                <td class="px-4 py-3"><?= (int) ($package['configs'] ?? 0) ?></td>
                <td class="px-4 py-3"><?= (int) ($package['uploads'] ?? 0) ?></td>
                <td class="px-4 py-3">
                  <span class="inline-flex rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em] <?= !empty($package['is_valid']) ? 'bg-emerald-500/20 text-emerald-100' : 'bg-amber-500/20 text-amber-100' ?>">
                    <?= !empty($package['is_valid']) ? 'VALIDO' : 'PENDENTE' ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>

<div id="admin-operations-progress-overlay" class="fixed inset-0 z-[11000] hidden items-center justify-center bg-slate-950/92 px-4 py-8 backdrop-blur-md" aria-hidden="true">
  <div class="w-full max-w-md rounded-3xl border border-cyan-500/20 bg-slate-950/95 px-6 py-6 shadow-2xl shadow-cyan-500/10">
    <div class="inline-flex items-center rounded-full border border-cyan-500/20 bg-cyan-500/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em] text-cyan-200">
      Operacao em andamento
    </div>
    <h2 id="admin-operations-progress-title" class="mt-4 font-orbitron text-xl font-black text-white">Executando rotina</h2>
    <p id="admin-operations-progress-message" class="mt-3 text-sm leading-7 text-slate-300">Estamos preparando a operacao. Esse processo pode levar alguns segundos dependendo do banco e dos uploads remotos.</p>

    <div class="mt-6 h-3 overflow-hidden rounded-full bg-slate-900">
      <div id="admin-operations-progress-fill" class="h-full w-[12%] rounded-full bg-gradient-to-r from-cyan-400 via-sky-400 to-cyan-300 transition-[width] duration-700 ease-out"></div>
    </div>

    <div class="mt-5 flex items-center justify-between gap-4 text-xs uppercase tracking-[0.18em] text-slate-400">
      <span id="admin-operations-progress-stage">Preparando</span>
      <span id="admin-operations-progress-counter" class="text-cyan-200">Etapa 1 de 11</span>
      <span class="inline-flex gap-1 text-cyan-200">
        <span class="animate-pulse">.</span>
        <span class="animate-pulse [animation-delay:0.18s]">.</span>
        <span class="animate-pulse [animation-delay:0.36s]">.</span>
      </span>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 px-4 py-4">
      <div class="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-500">Agora executando</div>
      <div id="admin-operations-progress-current-label" class="mt-3 text-base font-bold text-cyan-100">Conectando na producao</div>
      <div id="admin-operations-progress-current-detail" class="mt-2 text-sm leading-6 text-slate-400">Preparando a leitura remota do ambiente de origem.</div>
    </div>

    <div class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 px-4 py-4 text-sm text-slate-400">
      Nao feche esta aba enquanto a sincronizacao estiver rodando. A tela sera recarregada automaticamente no fim da requisicao.
    </div>
  </div>
</div>

<script>
  (function () {
    const overlay = document.getElementById('admin-operations-progress-overlay');
    const title = document.getElementById('admin-operations-progress-title');
    const message = document.getElementById('admin-operations-progress-message');
    const stage = document.getElementById('admin-operations-progress-stage');
    const counter = document.getElementById('admin-operations-progress-counter');
    const currentLabel = document.getElementById('admin-operations-progress-current-label');
    const currentDetail = document.getElementById('admin-operations-progress-current-detail');
    const fill = document.getElementById('admin-operations-progress-fill');
    const forms = Array.from(document.querySelectorAll('[data-admin-progress-form]'));
    const appShell = document.body.querySelector('.min-h-screen.flex.w-full.min-w-0');
    let progressTimer = null;

    if (!overlay || !title || !message || !stage || !counter || !currentLabel || !currentDetail || !fill || forms.length === 0) {
      return;
    }

    if (overlay.parentElement !== document.body) {
      document.body.appendChild(overlay);
    }

    const defaultSteps = [
      { label: 'Conectando na producao', message: 'Abrindo a conexao remota para ler a base editorial da producao com seguranca.' },
      { label: 'Lendo categorias', message: 'Coletando as categorias editoriais usadas pelo conteudo que sera espelhado na stage.' },
      { label: 'Lendo posts', message: 'Consultando os posts, relacionamentos editoriais e proximos passos registrados na producao.' },
      { label: 'Lendo links e SEO', message: 'Separando links e configuracoes editoriais publicas que acompanham o conteudo.' },
      { label: 'Separando uploads usados', message: 'Mapeando imagens, audios e demais arquivos realmente referenciados pelo conteudo exportado.' },
      { label: 'Compactando o pacote', message: 'Montando o pacote editorial completo da producao para aplicar na stage de forma consistente.' },
      { label: 'Validando manifesto', message: 'Conferindo manifestos, JSONs e referencias de arquivos antes de qualquer sobrescrita na stage.' },
      { label: 'Gerando backup da stage', message: 'Criando um backup preventivo do conteudo atual da stage para garantir retorno seguro, se necessario.' },
      { label: 'Aplicando banco na stage', message: 'Atualizando os dados editoriais da stage com o pacote validado vindo da producao.' },
      { label: 'Aplicando uploads na stage', message: 'Copiando os arquivos referenciados para manter a stage coerente com o conteudo editorial sincronizado.' },
      { label: 'Finalizando sincronizacao', message: 'Fechando a operacao, consolidando o resultado e preparando o retorno da tela com o resumo final.' }
    ];

    function startProgressSequence(steps) {
      if (progressTimer) {
        window.clearInterval(progressTimer);
      }

      let currentIndex = 0;
      const total = Math.max(steps.length, 1);

      const applyStep = () => {
        const currentStep = steps[Math.min(currentIndex, steps.length - 1)] || defaultSteps[0];
        stage.textContent = currentStep.label || 'Processando';
        message.textContent = currentStep.message || 'Estamos processando sua solicitacao.';
        currentLabel.textContent = currentStep.label || 'Processando';
        currentDetail.textContent = currentStep.message || 'Estamos processando sua solicitacao.';
        counter.textContent = 'Etapa ' + String(Math.min(currentIndex + 1, total)) + ' de ' + String(total);
        fill.style.width = Math.min(92, 18 + (currentIndex * (72 / total))) + '%';
      };

      applyStep();

      progressTimer = window.setInterval(() => {
        if (currentIndex < steps.length - 1) {
          currentIndex += 1;
          applyStep();
          return;
        }

        stage.textContent = 'Finalizando';
        message.textContent = 'A operacao ainda esta rodando. Estamos aguardando a resposta final do servidor para atualizar a tela.';
        currentLabel.textContent = 'Finalizando sincronizacao';
        currentDetail.textContent = 'A operacao ainda esta rodando. Estamos aguardando a resposta final do servidor para atualizar a tela.';
        counter.textContent = 'Etapa ' + String(total) + ' de ' + String(total);
        fill.style.width = '96%';
        window.clearInterval(progressTimer);
        progressTimer = null;
      }, 2200);
    }

    forms.forEach((form) => {
      const confirmationInput = form.querySelector('[data-confirmation-input]');
      const confirmationSubmit = form.querySelector('[data-confirmation-submit]');
      const confirmationStatus = form.querySelector('[data-confirmation-status]');
      const confirmationExpected = <?= json_encode($confirmationPhrase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      const syncReady = <?= $syncEnabled ? 'true' : 'false' ?>;

      const refreshConfirmationState = () => {
        if (!(confirmationSubmit instanceof HTMLButtonElement)) {
          return;
        }

        if (!syncReady) {
          confirmationSubmit.disabled = true;
          return;
        }

        const currentValue = confirmationInput instanceof HTMLInputElement
          ? confirmationInput.value.trim().toUpperCase()
          : '';
        const expectedValue = String(confirmationExpected).trim().toUpperCase();
        const matches = currentValue === expectedValue;

        confirmationSubmit.disabled = !matches;
        confirmationSubmit.classList.toggle('opacity-70', !matches);
        confirmationSubmit.classList.toggle('cursor-not-allowed', !matches);

        if (confirmationStatus) {
          confirmationStatus.textContent = matches
            ? 'Confirmacao validada. A sincronizacao ja pode ser executada.'
            : 'Aguardando a frase exata para liberar a execucao.';
          confirmationStatus.classList.toggle('text-emerald-300', matches);
          confirmationStatus.classList.toggle('text-amber-300', !matches);
        }
      };

      if (confirmationInput instanceof HTMLInputElement) {
        confirmationInput.addEventListener('input', refreshConfirmationState);
      }

      refreshConfirmationState();

      form.addEventListener('submit', () => {
        let steps = defaultSteps;
        try {
          const parsed = JSON.parse(form.getAttribute('data-progress-steps') || '[]');
          if (Array.isArray(parsed) && parsed.length > 0) {
            steps = parsed;
          }
        } catch (error) {}

        title.textContent = form.getAttribute('data-progress-title') || 'Executando rotina';
        message.textContent = form.getAttribute('data-progress-message') || 'Estamos processando sua solicitacao.';
        stage.textContent = form.getAttribute('data-progress-stage') || 'Processando';
        fill.style.width = '12%';
        startProgressSequence(steps);

        const submitButton = form.querySelector('[data-progress-submit]');
        if (submitButton instanceof HTMLButtonElement) {
          submitButton.disabled = true;
          submitButton.classList.add('opacity-70', 'cursor-wait');
        }

        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        if (appShell instanceof HTMLElement) {
          appShell.classList.add('pointer-events-none');
        }
        overlay.classList.remove('pointer-events-none');
        overlay.classList.add('pointer-events-auto');
      });
    });
  })();
</script>
