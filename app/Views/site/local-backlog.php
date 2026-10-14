<?php

declare(strict_types=1);

/*
 * Backlog da Central Tecnica.
 * Desde 13/10/2026 as tarefas ficam SOMENTE no Forge (projeto Estrategia Nerd).
 * Esta pagina so orienta onde ficam as tarefas e os itens de baixa prioridade.
 * A antiga lista de 44 tarefas por fases (+20 da trilha de midia/editor) foi verificada
 * e consolidada em docs/governanca/backlog-baixa-prioridade.md (tarefa Forge #1252).
 */

$embedMode = (bool) ($embed_mode ?? false);
$adminEmbed = (bool) ($admin_embed ?? false);
$projectVersion = trim((string) ($project_version ?? 'local'));
$generatedAt = trim((string) ($generated_at ?? date('Y-m-d H:i:s')));
$forgeUrl = 'http://127.0.0.1:4477';
$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<section class="<?= $adminEmbed ? 'text-slate-100' : 'min-h-screen bg-slate-950 px-4 py-8 text-slate-100' ?>">
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
  </style>

  <div class="<?= $adminEmbed ? 'space-y-6' : 'mx-auto max-w-[1720px] space-y-6' ?>">
    <?php if (!$embedMode): ?>
      <?php \App\Support\View::component('site/local-tools-nav', ['active' => 'backlog']); ?>
    <?php endif; ?>

    <header class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Backlog Tecnico</p>
      <h1 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">As tarefas ficam somente no Forge</h1>
      <p class="mt-3 max-w-5xl text-sm leading-7 text-slate-300">
        Desde 13/10/2026 nenhuma tarefa e mantida nesta pagina. Tarefas ativas ficam no Forge, no projeto
        <strong>Estrategia Nerd</strong>, com prefixo <code>[Assunto] - Descricao</code>. Itens de baixa prioridade
        ficam em um arquivo proprio ate alguem decidir traze-los para o Forge.
      </p>
      <div class="mt-5 grid gap-3 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Versao de referencia</p>
          <p class="mt-1 font-rajdhani text-2xl font-bold text-white"><?= $e($projectVersion) ?></p>
        </div>
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Atualizado em</p>
          <p class="mt-1 text-sm text-slate-200"><?= $e($generatedAt) ?></p>
        </div>
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Fonte das tarefas</p>
          <p class="mt-1 text-sm text-slate-200">Forge &mdash; <code><?= $e($forgeUrl) ?></code> (somente neste PC)</p>
        </div>
      </div>
    </header>

    <article class="grid gap-4 lg:grid-cols-2">
      <section class="doc-card">
        <p class="doc-label">Tarefas ativas</p>
        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-slate-200">
          <li>Abrir o Forge em <code><?= $e($forgeUrl) ?></code> e entrar no projeto <strong>Estrategia Nerd</strong> (id 4).</li>
          <li>Criar tarefas por <code>POST /api/projects/4/tasks/item</code> e concluir por <code>PATCH /api/tasks/:id</code>, com o cabecalho <code>X-Forge-Csrf: 1</code> (guia: <code>the-forge/docs/integracao-outros-projetos.md</code>).</li>
          <li>Enviar o JSON a partir de arquivo UTF-8 (<code>curl.exe --data-binary @arquivo.json</code>) para nao corromper acentos.</li>
          <li>Concluir a tarefa so depois do teste tecnico e da documentacao central atualizada.</li>
        </ul>
      </section>

      <section class="doc-card">
        <p class="doc-label">Baixa prioridade</p>
        <ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-slate-200">
          <li>Arquivo: <code>docs/governanca/backlog-baixa-prioridade.md</code>.</li>
          <li>Contem a verificacao de 13/10/2026 das 64 tarefas da antiga lista (44 por fases + 20 da trilha de midia e editor), cada uma com a evidencia encontrada: o que ja foi feito, o que foi para o Forge e o que ficou para depois.</li>
          <li>Para trabalhar em um item: criar a tarefa no Forge e remover o item do arquivo.</li>
          <li>Ha uma decisao pendente registrada no arquivo (integracao com a Central Nerd).</li>
        </ul>
      </section>

      <section class="doc-card doc-rule lg:col-span-2">
        <p class="doc-label">Regra da documentacao central</p>
        <p class="mt-3 text-sm leading-6 text-slate-100">
          Toda alteracao do sistema atualiza, na mesma tarefa, a documentacao central (Documentacao Tecnica, Regras,
          Historico, Procedimentos, Padroes e FAQ). O guia com o arquivo de cada area, quando atualizar e como validar
          esta em <code>docs/governanca/documentacao-central.md</code> e e citado no <code>AGENTS.md</code>.
          A revisao mensal da documentacao e uma tarefa recorrente no Forge.
        </p>
      </section>
    </article>
  </div>
</section>
