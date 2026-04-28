<?php

declare(strict_types=1);

$embedMode = (bool) ($embed_mode ?? false);
$adminEmbed = (bool) ($admin_embed ?? false);
$projectVersion = trim((string) ($project_version ?? 'local'));
$generatedAt = trim((string) ($generated_at ?? date('Y-m-d H:i:s')));
$rulesBody = trim((string) ($rules_body ?? ''));
?>
<section class="<?= $adminEmbed ? 'text-slate-100' : 'min-h-screen bg-slate-950 px-4 py-8 text-slate-100' ?>">
  <style>
    .doc-card { border: 1px solid rgba(51, 65, 85, 0.9); background: rgba(2, 6, 23, 0.65); border-radius: 1rem; padding: 1rem; }
    .doc-label { font-family: Orbitron, ui-sans-serif, system-ui; font-size: 0.66rem; letter-spacing: 0.2em; text-transform: uppercase; color: rgba(148, 163, 184, 0.95); }
    .doc-rule { border: 1px solid rgba(34, 211, 238, 0.25); background: rgba(8, 47, 73, 0.45); }
    .doc-alert { border: 1px solid rgba(251, 191, 36, 0.28); background: rgba(120, 53, 15, 0.28); }
    .doc-strong { border: 1px solid rgba(244, 114, 182, 0.25); background: rgba(80, 7, 36, 0.24); }
    .doc-pre { white-space: pre-wrap; word-break: break-word; font-size: 0.82rem; line-height: 1.65; color: rgba(226, 232, 240, 0.96); }
  </style>

  <div class="<?= $adminEmbed ? 'space-y-6' : 'mx-auto max-w-7xl space-y-6' ?>">
    <?php if (!$embedMode): ?>
      <?php \App\Support\View::component('site/local-tools-nav', ['active' => 'rules']); ?>
    <?php endif; ?>

    <header class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Regras Permanentes</p>
      <h1 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Politica operacional obrigatoria do projeto</h1>
      <p class="mt-3 max-w-5xl text-sm leading-7 text-slate-300">
        Esta pagina concentra as regras permanentes de arquitetura, deploy, seguranca e disciplina operacional.
        O objetivo e impedir atalho inseguro, reduzir retrabalho e manter `local`, `stage` e `producao` sob governanca real.
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
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Regra de maior peso</p>
          <p class="mt-1 text-sm text-slate-200">Pacote de producao so nasce da `stage` validada.</p>
        </div>
      </div>
    </header>

    <section class="grid gap-6 xl:grid-cols-[1.05fr_1.95fr]">
      <aside class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
        <h2 class="font-orbitron text-base font-bold text-white">Leitura rapida</h2>
        <div class="mt-4 space-y-3">
          <div class="doc-card doc-alert">
            <p class="doc-label">Bloqueio absoluto</p>
            <p class="mt-2 text-sm text-amber-100">Se a origem do pacote nao for `stage`, a publicacao em producao deve parar.</p>
          </div>
          <div class="doc-card doc-rule">
            <p class="doc-label">Teste em producao</p>
            <p class="mt-2 text-sm text-slate-100">Producao recebe apenas validacao minima de sanidade, nunca teste exploratorio.</p>
          </div>
          <div class="doc-card doc-strong">
            <p class="doc-label">Paridade obrigatoria</p>
            <p class="mt-2 text-sm text-fuchsia-100">Toda correcao publicada deve ser reproduzida em `stage` e `local` antes de encerrar a tarefa.</p>
          </div>
        </div>
      </aside>

      <div class="space-y-6">
        <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">Melhorias aplicadas nas regras</h2>
          <div class="mt-4 grid gap-3 md:grid-cols-2">
            <div class="doc-card doc-rule">
              <p class="doc-label">Promocao entre ambientes</p>
              <p class="mt-2 text-sm text-slate-100">O fluxo agora deixa explicito que `local` desenvolve, `stage` valida e so a `stage` pode virar origem de pacote para `producao`.</p>
            </div>
            <div class="doc-card doc-rule">
              <p class="doc-label">Teste em producao</p>
              <p class="mt-2 text-sm text-slate-100">As regras reforcam que producao nao e laboratorio. Ali so entra smoke curto e verificacao minima pos-publicacao.</p>
            </div>
            <div class="doc-card doc-rule">
              <p class="doc-label">Saida operacional</p>
              <p class="mt-2 text-sm text-slate-100">Foi reforcado o registro de `backup_id`, `package_id`, origem real do pacote e validacao executada antes de encerrar qualquer deploy.</p>
            </div>
            <div class="doc-card doc-rule">
              <p class="doc-label">Automacao util</p>
              <p class="mt-2 text-sm text-slate-100">As regras agora incentivam automatizar preflight, origem do pacote, validacao de rotas e bloqueios sem substituir criterio humano.</p>
            </div>
          </div>
        </section>

        <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
          <h2 class="font-orbitron text-xl font-bold text-cyan-200">Texto oficial em vigor</h2>
          <div class="doc-card mt-4">
            <pre class="doc-pre"><?= htmlspecialchars($rulesBody, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
          </div>
        </section>
      </div>
    </section>
  </div>
</section>
