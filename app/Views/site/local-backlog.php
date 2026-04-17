<?php

declare(strict_types=1);

$projectVersion = trim((string) ($project_version ?? 'local'));
$generatedAt = trim((string) ($generated_at ?? date('Y-m-d H:i:s')));
$phaseCount = 5;
$taskCount = 38;
$currentSuggestedPhase = 'Fase 1';
$currentFocus = 'Auditoria critica de seguranca, rotas sensiveis e fluxo operacional';
$executionRule = 'Concluir itens criticos da Fase 1 e estabilizar Fase 2 antes de expansao forte em Fase 3/4.';
$publicationRule = 'Backlog e operado no local e so gera mudanca em producao apos validacao. Documentacao permanece somente local.';
?>
<section class="min-h-screen bg-slate-950 px-4 py-8 text-slate-100">
  <style>
    .doc-card {
      border: 1px solid rgba(51, 65, 85, 0.9);
      background: rgba(2, 6, 23, 0.65);
      border-radius: 1rem;
      padding: 1rem;
    }

    .doc-label {
      font-family: Orbitron, ui-sans-serif, system-ui;
      font-size: 0.66rem;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: rgba(148, 163, 184, 0.95);
    }

    .doc-rule {
      border: 1px solid rgba(34, 211, 238, 0.25);
      background: rgba(8, 47, 73, 0.45);
    }

    .doc-alert {
      border: 1px solid rgba(251, 191, 36, 0.28);
      background: rgba(120, 53, 15, 0.28);
    }

    .doc-table {
      width: 100%;
      min-width: 0;
      table-layout: fixed;
      border-collapse: separate;
      border-spacing: 0 0.55rem;
      font-size: clamp(0.74rem, 0.16vw + 0.68rem, 0.84rem);
      line-height: 1.35;
    }

    .doc-table th {
      text-align: left;
      font-size: clamp(0.56rem, 0.12vw + 0.52rem, 0.66rem);
      text-transform: uppercase;
      letter-spacing: 0.18em;
      color: rgba(100, 116, 139, 1);
      padding: 0.5rem 0.62rem;
      white-space: normal;
    }

    .doc-table td {
      padding: 0.62rem 0.62rem;
      vertical-align: top;
      overflow-wrap: break-word;
      word-break: break-word;
    }

    .doc-row {
      border: 1px solid rgba(51, 65, 85, 0.95);
      background: rgba(2, 6, 23, 0.72);
    }

    .doc-critical {
      border-color: rgba(251, 191, 36, 0.7);
      background: rgba(120, 53, 15, 0.24);
      box-shadow: inset 0 0 0 1px rgba(251, 191, 36, 0.22);
    }

    .status-badge {
      display: inline-flex;
      align-items: center;
      border-radius: 9999px;
      border: 1px solid rgba(71, 85, 105, 0.85);
      background: rgba(15, 23, 42, 0.9);
      padding: 0.15rem 0.55rem;
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      color: rgba(191, 219, 254, 0.95);
      white-space: nowrap;
    }

    .status-nao-iniciada {
      border-color: rgba(59, 130, 246, 0.5);
      color: rgba(147, 197, 253, 0.98);
      background: rgba(30, 58, 138, 0.36);
    }

    .task-badge {
      margin-left: 0.45rem;
      border-radius: 9999px;
      border: 1px solid rgba(251, 191, 36, 0.55);
      background: rgba(120, 53, 15, 0.35);
      padding: 0.1rem 0.45rem;
      font-size: 0.62rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: rgba(254, 243, 199, 0.95);
      white-space: nowrap;
    }

    .doc-table th:nth-child(1), .doc-table td:nth-child(1) { width: 4%; }
    .doc-table th:nth-child(2), .doc-table td:nth-child(2) { width: 7%; }
    .doc-table th:nth-child(3), .doc-table td:nth-child(3) { width: 21%; }
    .doc-table th:nth-child(4), .doc-table td:nth-child(4) { width: 6%; }
    .doc-table th:nth-child(5), .doc-table td:nth-child(5) { width: 6%; }
    .doc-table th:nth-child(6), .doc-table td:nth-child(6) { width: 6%; }
    .doc-table th:nth-child(7), .doc-table td:nth-child(7) { width: 8%; }
    .doc-table th:nth-child(8), .doc-table td:nth-child(8) { width: 18%; }
    .doc-table th:nth-child(9), .doc-table td:nth-child(9) { width: 8%; }
    .doc-table th:nth-child(10), .doc-table td:nth-child(10) { width: 16%; }

    @media (max-width: 1280px) {
      .doc-table {
        font-size: 0.77rem;
      }

      .doc-table th {
        font-size: 0.58rem;
        letter-spacing: 0.14em;
      }
    }
  </style>

  <div class="mx-auto max-w-[1720px] space-y-6">
    <?php \App\Support\View::component('site/local-tools-nav', ['active' => 'backlog']); ?>

    <header class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Backlog Tecnico Local</p>
      <h1 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Plano de evolucao do sistema Estrategia Nerd</h1>
      <p class="mt-3 max-w-5xl text-sm leading-7 text-slate-300">
        Este backlog e complementar a documentacao local. Nao substitui a base tecnica atual.
        O foco e transformar diretrizes em execucao por fases, mantendo stack, arquitetura e padroes definidos.
      </p>
      <div class="mt-5 grid gap-3 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Versao de referencia</p>
          <p class="mt-1 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars($projectVersion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        </div>
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Atualizado em</p>
          <p class="mt-1 text-sm text-slate-200"><?= htmlspecialchars($generatedAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        </div>
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Escopo</p>
          <p class="mt-1 text-sm text-slate-200">Auditoria, padronizacao, admin, editorial e observabilidade</p>
        </div>
      </div>
    </header>

    <section id="resumo-executivo" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <h2 class="font-orbitron text-xl font-bold text-cyan-200">Resumo executivo</h2>
      <div class="mt-4 grid gap-3 md:grid-cols-5">
        <div class="doc-card">
          <p class="doc-label">Total de fases</p>
          <p class="mt-2 font-rajdhani text-2xl font-bold text-white"><?= (int) $phaseCount ?></p>
        </div>
        <div class="doc-card">
          <p class="doc-label">Total de tarefas</p>
          <p class="mt-2 font-rajdhani text-2xl font-bold text-white"><?= (int) $taskCount ?></p>
        </div>
        <div class="doc-card">
          <p class="doc-label">Fase atual sugerida</p>
          <p class="mt-2 text-sm font-semibold text-slate-100"><?= htmlspecialchars($currentSuggestedPhase, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        </div>
        <div class="doc-card md:col-span-2">
          <p class="doc-label">Foco atual</p>
          <p class="mt-2 text-sm text-slate-200"><?= htmlspecialchars($currentFocus, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        </div>
      </div>
      <div class="doc-card doc-alert mt-4">
        <p class="doc-label">Regra principal de execucao</p>
        <p class="mt-2 text-sm text-amber-100"><?= htmlspecialchars($executionRule, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
      </div>
      <div class="doc-card doc-alert mt-4">
        <p class="doc-label">Regra de publicacao</p>
        <p class="mt-2 text-sm text-amber-100"><?= htmlspecialchars($publicationRule, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
      </div>
    </section>

    <section id="ordem-execucao" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <h2 class="font-orbitron text-xl font-bold text-cyan-200">Ordem sugerida de execucao</h2>
      <div class="doc-card doc-rule mt-4">
        <p class="doc-label">Diretriz operacional</p>
        <ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-200">
          <li>Comecar por tarefas de prioridade Alta com impacto Alto e esforco Baixo/Medio.</li>
          <li>Concluir os pontos criticos da Fase 1 antes de acelerar Fase 3 e Fase 4.</li>
          <li>Usar a Fase 2 como etapa de estabilizacao estrutural antes da expansao.</li>
          <li>Evitar iniciar tarefas de prioridade Baixa antes das estruturais.</li>
          <li>Executar Fase 5 progressivamente para sustentar operacao, sem esperar fim total da evolucao visual.</li>
        </ol>
      </div>
    </section>

    <article class="grid gap-6 xl:grid-cols-[0.62fr_2.38fr] 2xl:grid-cols-[0.58fr_2.42fr]">
      <aside class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <h2 class="font-orbitron text-base font-bold text-white">Indice do backlog</h2>
        <nav class="mt-4 space-y-2 text-sm">
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#resumo-executivo">Resumo executivo</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#ordem-execucao">Ordem sugerida</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#fase-1">Fase 1. Auditoria doc x sistema</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#fase-2">Fase 2. Padronizacao estrutural</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#fase-3">Fase 3. Evolucao do admin</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#fase-4">Fase 4. Evolucao editorial</a>
          <a class="block rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 hover:border-cyan-400/60" href="#fase-5">Fase 5. Observabilidade</a>
        </nav>

        <div class="doc-card doc-alert mt-4">
          <p class="doc-label">Regra obrigatoria</p>
          <p class="mt-2 text-sm text-amber-100">Executar por fase e validar criterios de saida antes de avancar para a proxima.</p>
        </div>
      </aside>

      <div class="space-y-6">
        <section id="fase-1" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">Fase 1 - Auditoria do sistema contra a documentacao</h2>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Objetivo</p>
            <p class="mt-2 text-sm text-slate-200">Confirmar aderencia entre o que esta documentado e o comportamento real do sistema.</p>
          </div>
          <div class="doc-card doc-alert mt-4">
            <p class="doc-label">Limite operacional da fase</p>
            <p class="mt-2 text-sm text-amber-100">A Fase 1 e de auditoria: inspecionar, validar e registrar inconsistencias. Correcao estrutural fica para as fases seguintes para evitar refatoracao descontrolada.</p>
          </div>
          <div class="mt-4 overflow-x-auto">
            <table class="doc-table">
              <thead>
                <tr>
                  <th>Fase</th>
                  <th>Tarefa</th>
                  <th>Descricao</th>
                  <th>Prioridade</th>
                  <th>Impacto</th>
                  <th>Esforco</th>
                  <th>Status</th>
                  <th>Criterio de conclusao</th>
                  <th>Dependencias</th>
                  <th>Resultado esperado</th>
                </tr>
              </thead>
              <tbody>
                <tr class="doc-row"><td>F1</td><td>F1-T01</td><td>Auditar controllers para garantir ausencia de SQL/acesso direto ao banco.</td><td>Alta</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Relatorio de controllers revisados com evidencias por modulo.</td><td>Nenhuma</td><td>Mapa de controllers aderentes e pontos fora do padrao.</td></tr>
                <tr class="doc-row"><td>F1</td><td>F1-T02</td><td>Auditar services para confirmar concentracao de regra de negocio e montagem de view model.</td><td>Alta</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Matriz service x regra de negocio validada e registrada.</td><td>F1-T01</td><td>Relatorio de consistencia da camada de servico.</td></tr>
                <tr class="doc-row"><td>F1</td><td>F1-T03</td><td>Auditar repositories para garantir isolamento de acesso a dados.</td><td>Alta</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Inventario de repositories e queries com conformidade marcada.</td><td>F1-T01</td><td>Lista de queries por repositorio e desvios encontrados.</td></tr>
                <tr class="doc-row"><td>F1</td><td>F1-T04</td><td>Verificar views para remover logica pesada e regras nao visuais.</td><td>Alta</td><td>Medio</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Checklist de views revisadas com pontos fora do padrao.</td><td>F1-T02</td><td>Checklist de views limpas com pendencias priorizadas.</td></tr>
                <tr class="doc-row doc-critical"><td>F1</td><td>F1-T05 <span class="task-badge">Critica</span></td><td>Auditar protecao de rotas `/admin` e `/local`, inclusive guardas de ambiente.</td><td>Alta</td><td>Alto</td><td>Baixo</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Teste manual de acesso nao autorizado anexado com resultado por rota.</td><td>Nenhuma</td><td>Rotas sensiveis validadas com evidencias de bloqueio.</td></tr>
                <tr class="doc-row doc-critical"><td>F1</td><td>F1-T06 <span class="task-badge">Critica</span></td><td>Validar protecao de `.env`, regras de upload e bloqueio de execucao em `uploads`.</td><td>Alta</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Evidencias de bloqueio `.env` e validacao de upload registradas.</td><td>F1-T05</td><td>Checklist de seguranca concluido com correcoes propostas.</td></tr>
                <tr class="doc-row doc-critical"><td>F1</td><td>F1-T07 <span class="task-badge">Critica</span></td><td>Revisar cobertura de CSRF em operacoes POST e formularios sensiveis.</td><td>Alta</td><td>Alto</td><td>Baixo</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Matriz de endpoints POST com status CSRF preenchida.</td><td>F1-T05</td><td>Matriz de rotas POST com status CSRF.</td></tr>
                <tr class="doc-row"><td>F1</td><td>F1-T08</td><td>Validar variaveis de ambiente chave (`APP_DEBUG`, `APP_ENV`, `APP_URL`) por perfil.</td><td>Media</td><td>Alto</td><td>Baixo</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Checklist de ambiente local/producao validado e documentado.</td><td>Nenhuma</td><td>Padrao de ambiente confirmado para local e producao.</td></tr>
                <tr class="doc-row doc-critical"><td>F1</td><td>F1-T09 <span class="task-badge">Critica</span></td><td>Comparar fluxo real de deploy com o fluxo documentado (preflight, pacote, publicacao).</td><td>Alta</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Fluxo de deploy executado em teste com evidencias por etapa.</td><td>F1-T08</td><td>Gap analysis de deploy com acoes corretivas objetivas.</td></tr>
                <tr class="doc-row doc-critical"><td>F1</td><td>F1-T10 <span class="task-badge">Critica</span></td><td>Comparar fluxo real de backup/restore com a rotina oficial documentada.</td><td>Alta</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Backup e restore de validacao executados com log e evidencias.</td><td>F1-T08</td><td>Relatorio final de inconsistencias priorizadas (P1/P2/P3).</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <section id="fase-2" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">Fase 2 - Padronizacao estrutural do codigo</h2>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Objetivo</p>
            <p class="mt-2 text-sm text-slate-200">Uniformizar a base para manutencao previsivel, com regras de nomenclatura e separacao de camadas.</p>
          </div>
          <div class="mt-4 overflow-x-auto">
            <table class="doc-table">
              <thead>
                <tr>
                  <th>Fase</th>
                  <th>Tarefa</th>
                  <th>Descricao</th>
                  <th>Prioridade</th>
                  <th>Impacto</th>
                  <th>Esforco</th>
                  <th>Status</th>
                  <th>Criterio de conclusao</th>
                  <th>Dependencias</th>
                  <th>Resultado esperado</th>
                </tr>
              </thead>
              <tbody>
                <tr class="doc-row"><td>F2</td><td>F2-T01</td><td>Mapear classes/arquivos fora do padrao de nomenclatura (PascalCase e sufixos).</td><td>Alta</td><td>Medio</td><td>Baixo</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Lista de classes/arquivos fora do padrao consolidada.</td><td>F1-T01, F1-T03</td><td>Inventario de nomes fora do padrao.</td></tr>
                <tr class="doc-row"><td>F2</td><td>F2-T02</td><td>Padronizar sufixos `Controller`, `Service` e `Repository` onde necessario.</td><td>Alta</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Renomeacoes aplicadas e referencias atualizadas sem quebra de rota.</td><td>F2-T01</td><td>Nomenclatura uniforme nas camadas principais.</td></tr>
                <tr class="doc-row"><td>F2</td><td>F2-T03</td><td>Padronizar estrutura minima de metodos por modulo CRUD no admin.</td><td>Media</td><td>Medio</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Padrao de metodos aplicado e revisado em todos os modulos CRUD.</td><td>F2-T02</td><td>Controladores com padrao previsivel de metodos.</td></tr>
                <tr class="doc-row"><td>F2</td><td>F2-T04</td><td>Alinhar mapeamento de rotas com controllers documentados e responsaveis reais.</td><td>Alta</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Tabela de rotas reconciliada com teste manual dos endpoints principais.</td><td>F1-T09</td><td>Rotas e handlers consistentes com documentacao.</td></tr>
                <tr class="doc-row"><td>F2</td><td>F2-T05</td><td>Revisar views para consolidar componentes e reduzir duplicacao estrutural.</td><td>Media</td><td>Medio</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Componentes comuns extraidos e repeticoes removidas.</td><td>F1-T04</td><td>Padrao visual/estrutural mais reutilizavel.</td></tr>
                <tr class="doc-row"><td>F2</td><td>F2-T06</td><td>Isolar pontos mistos entre camadas (ex.: regra de negocio em view/controller).</td><td>Alta</td><td>Alto</td><td>Alto</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Pontos mistos corrigidos com diff validado e sem regressao funcional.</td><td>F1-T02, F1-T04</td><td>Reducao de acoplamento e risco de regressao.</td></tr>
                <tr class="doc-row"><td>F2</td><td>F2-T07</td><td>Definir template repetivel para novos modulos (controller + service + repository + view).</td><td>Media</td><td>Medio</td><td>Baixo</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Template interno documentado e validado em um modulo piloto.</td><td>F2-T02, F2-T06</td><td>Guia interno pronto para expansao sem improviso.</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <section id="fase-3" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">Fase 3 - Evolucao do admin</h2>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Objetivo</p>
            <p class="mt-2 text-sm text-slate-200">Consolidar o admin como centro operacional com componentes reutilizaveis e feedback confiavel.</p>
          </div>
          <div class="mt-4 overflow-x-auto">
            <table class="doc-table">
              <thead>
                <tr>
                  <th>Fase</th>
                  <th>Tarefa</th>
                  <th>Descricao</th>
                  <th>Prioridade</th>
                  <th>Impacto</th>
                  <th>Esforco</th>
                  <th>Status</th>
                  <th>Criterio de conclusao</th>
                  <th>Dependencias</th>
                  <th>Resultado esperado</th>
                </tr>
              </thead>
              <tbody>
                <tr class="doc-row"><td>F3</td><td>F3-T01</td><td>Auditar estrutura atual do dashboard e identificar blocos reutilizaveis.</td><td>Alta</td><td>Alto</td><td>Baixo</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Mapa de componentes com ranking de reutilizacao aprovado.</td><td>F1-T01, F1-T04</td><td>Mapa de componentes com priorizacao de refino.</td></tr>
                <tr class="doc-row"><td>F3</td><td>F3-T02</td><td>Criar padrao reutilizavel para cards, tabelas, formularios e acoes no admin.</td><td>Alta</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Componentes base aplicados em pelo menos dois modulos do admin.</td><td>F3-T01, F2-T05</td><td>Biblioteca interna de componentes administrativos.</td></tr>
                <tr class="doc-row"><td>F3</td><td>F3-T03</td><td>Padronizar feedback de operacoes criticas (sucesso, erro, warning e confirmacao).</td><td>Alta</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Padrao de feedback aplicado em operacoes criticas definidas.</td><td>F3-T02</td><td>Operacao mais segura e menos ambigua para o operador.</td></tr>
                <tr class="doc-row"><td>F3</td><td>F3-T04</td><td>Destacar acoes operacionais prioritarias (backup, sync, limpeza de midia, publicacao).</td><td>Media</td><td>Medio</td><td>Baixo</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Acoes criticas destacadas e validadas com teste de navegacao.</td><td>F3-T02</td><td>Navegacao orientada por tarefas criticas.</td></tr>
                <tr class="doc-row"><td>F3</td><td>F3-T05</td><td>Evoluir area de status/saude com indicadores de ambiente e operacao.</td><td>Media</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Indicadores minimos exibidos com fonte de dados documentada.</td><td>F3-T01, F5-T01</td><td>Visao de saude mais clara no admin.</td></tr>
                <tr class="doc-row"><td>F3</td><td>F3-T06</td><td>Melhorar navegacao interna por modulos com agrupamento funcional consistente.</td><td>Media</td><td>Medio</td><td>Baixo</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Navegacao reorganizada e aprovada em revisao operacional.</td><td>F3-T02</td><td>Fluxo operacional mais rapido no dia a dia.</td></tr>
                <tr class="doc-row"><td>F3</td><td>F3-T07</td><td>Preparar estrutura dinamica para novos modulos sem quebrar padrao atual.</td><td>Baixa</td><td>Medio</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Estrutura de extensao definida e validada com modulo de teste.</td><td>F2-T07, F3-T06</td><td>Escalabilidade administrativa com menor retrabalho.</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <section id="fase-4" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">Fase 4 - Evolucao do fluxo editorial</h2>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Objetivo</p>
            <p class="mt-2 text-sm text-slate-200">Melhorar criacao, edicao e publicacao de conteudo com consistencia, seguranca e menor friccao.</p>
          </div>
          <div class="mt-4 overflow-x-auto">
            <table class="doc-table">
              <thead>
                <tr>
                  <th>Fase</th>
                  <th>Tarefa</th>
                  <th>Descricao</th>
                  <th>Prioridade</th>
                  <th>Impacto</th>
                  <th>Esforco</th>
                  <th>Status</th>
                  <th>Criterio de conclusao</th>
                  <th>Dependencias</th>
                  <th>Resultado esperado</th>
                </tr>
              </thead>
              <tbody>
                <tr class="doc-row"><td>F4</td><td>F4-T01</td><td>Revisar fluxo de criacao/edicao de post (ordem de campos, usabilidade e validacoes).</td><td>Alta</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Fluxo de formulario revisado e testado ponta a ponta.</td><td>F3-T02</td><td>Formularios editoriais mais claros e consistentes.</td></tr>
                <tr class="doc-row"><td>F4</td><td>F4-T02</td><td>Fortalecer validacao de slug (unicidade, historico e conflitos de rota).</td><td>Alta</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Validacao de slug aplicada com cenarios de colisao testados.</td><td>F1-T10</td><td>URLs estaveis e menor risco de quebra SEO.</td></tr>
                <tr class="doc-row"><td>F4</td><td>F4-T03</td><td>Revisar vinculos de midia em posts e links para reduzir orfas e referencias quebradas.</td><td>Alta</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Relatorio de vinculos revisado e inconsistencias corrigidas/documentadas.</td><td>F1-T06</td><td>Integridade de midia melhorada no fluxo editorial.</td></tr>
                <tr class="doc-row"><td>F4</td><td>F4-T04</td><td>Reforcar validacao de upload (tipo, tamanho, path) com mensagens operacionais claras.</td><td>Alta</td><td>Alto</td><td>Baixo</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Regras de upload validadas com testes de erro e sucesso.</td><td>F1-T06</td><td>Menos erro de publicacao e mais seguranca.</td></tr>
                <tr class="doc-row"><td>F4</td><td>F4-T05</td><td>Adicionar pre-validacoes editoriais antes de publicar (titulo, subtitulo, imagem, CTA e tags).</td><td>Media</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Checklist pre-publicacao ativo com validacao manual executada.</td><td>F4-T01</td><td>Conteudo publicado com padrao editorial minimo.</td></tr>
                <tr class="doc-row"><td>F4</td><td>F4-T06</td><td>Preparar base tecnica para preview seguro (analise e implementacao incremental viavel).</td><td>Baixa</td><td>Medio</td><td>Alto</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Analise tecnica concluida e plano incremental aprovado.</td><td>F4-T01, F4-T05</td><td>Caminho de preview definido sem quebrar fluxo atual.</td></tr>
                <tr class="doc-row"><td>F4</td><td>F4-T07</td><td>Revisar integracao entre posts e Central Nerd para melhorar saida de conversao.</td><td>Media</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Links de saida revisados e testados em posts alvo.</td><td>F4-T05, F3-T04</td><td>Navegacao editorial conectada a objetivo de conversao.</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <section id="fase-5" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">Fase 5 - Observabilidade e manutencao operacional</h2>
          <div class="doc-card doc-rule mt-4">
            <p class="doc-label">Objetivo</p>
            <p class="mt-2 text-sm text-slate-200">Aumentar rastreabilidade, diagnostico e suporte operacional continuo do projeto.</p>
          </div>
          <div class="mt-4 overflow-x-auto">
            <table class="doc-table">
              <thead>
                <tr>
                  <th>Fase</th>
                  <th>Tarefa</th>
                  <th>Descricao</th>
                  <th>Prioridade</th>
                  <th>Impacto</th>
                  <th>Esforco</th>
                  <th>Status</th>
                  <th>Criterio de conclusao</th>
                  <th>Dependencias</th>
                  <th>Resultado esperado</th>
                </tr>
              </thead>
              <tbody>
                <tr class="doc-row"><td>F5</td><td>F5-T01</td><td>Mapear pontos de log para backup, sync, deploy e acoes criticas no admin.</td><td>Alta</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Mapa de logging por fluxo documentado com pontos obrigatorios.</td><td>F1-T09, F1-T10</td><td>Plano de logging operacional por fluxo.</td></tr>
                <tr class="doc-row"><td>F5</td><td>F5-T02</td><td>Registrar falhas operacionais relevantes com contexto minimo para reproduzir erro.</td><td>Alta</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Modelo de log de falha aplicado e validado em casos reais.</td><td>F5-T01</td><td>Diagnostico mais rapido e menos tentativa/erro.</td></tr>
                <tr class="doc-row"><td>F5</td><td>F5-T03</td><td>Registrar acoes sensiveis do admin (publicar, excluir, restore, limpar orfas).</td><td>Media</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Trilha de auditoria ativa para acoes administrativas criticas.</td><td>F5-T01</td><td>Trilha de auditoria para operacoes criticas.</td></tr>
                <tr class="doc-row"><td>F5</td><td>F5-T04</td><td>Padronizar mensagens de erro tecnico com contexto de acao recomendada.</td><td>Media</td><td>Medio</td><td>Baixo</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Padrao de mensagens aplicado e revisado nos fluxos criticos.</td><td>F5-T02</td><td>Feedback operacional mais claro para manutencao.</td></tr>
                <tr class="doc-row"><td>F5</td><td>F5-T05</td><td>Fortalecer rastreabilidade de deploy/sync com IDs de pacote e status por etapa.</td><td>Alta</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Historico de pacotes e etapas registrado com IDs unicos.</td><td>F5-T01, F5-T02</td><td>Historico confiavel de publicacao e rollback.</td></tr>
                <tr class="doc-row"><td>F5</td><td>F5-T06</td><td>Fortalecer rastreabilidade de backup/restore com verificacao e registro de escopo.</td><td>Alta</td><td>Alto</td><td>Medio</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Log de backup/restore com escopo, resultado e evidencias.</td><td>F5-T01, F1-T10</td><td>Operacao de backup auditavel ponta a ponta.</td></tr>
                <tr class="doc-row"><td>F5</td><td>F5-T07</td><td>Consolidar rotina de revisao mensal da documentacao + backlog + pendencias abertas.</td><td>Media</td><td>Medio</td><td>Baixo</td><td><span class="status-badge status-nao-iniciada">Nao iniciada</span></td><td>Ritual mensal registrado com ata e atualizacao de backlog.</td><td>F5-T05, F5-T06</td><td>Ciclo continuo de manutencao orientado por dados.</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <section class="rounded-3xl border border-fuchsia-500/20 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-fuchsia-200">Resultado esperado por fase</h2>
          <div class="mt-4 grid gap-3 md:grid-cols-2">
            <div class="doc-card"><p class="doc-label">Fase 1</p><p class="mt-2 text-sm text-slate-200">Relatorio de inconsistencias doc x sistema, com prioridade e acao.</p></div>
            <div class="doc-card"><p class="doc-label">Fase 2</p><p class="mt-2 text-sm text-slate-200">Base mais previsivel e aderente ao padrao arquitetural.</p></div>
            <div class="doc-card"><p class="doc-label">Fase 3</p><p class="mt-2 text-sm text-slate-200">Admin mais forte, reutilizavel e orientado por operacao.</p></div>
            <div class="doc-card"><p class="doc-label">Fase 4</p><p class="mt-2 text-sm text-slate-200">Fluxo editorial mais seguro, pratico e consistente.</p></div>
            <div class="doc-card md:col-span-2"><p class="doc-label">Fase 5</p><p class="mt-2 text-sm text-slate-200">Sistema mais auditavel e facil de manter com rastreabilidade operacional real.</p></div>
          </div>
        </section>
      </div>
    </article>
  </div>
</section>
