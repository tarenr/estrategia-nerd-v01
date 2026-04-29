<?php

declare(strict_types=1);

$projectVersion = trim((string) ($project_version ?? 'local'));
$generatedAt = trim((string) ($generated_at ?? date('Y-m-d H:i:s')));
$docGroup = trim((string) ($doc_group ?? 'docs'));
$docFile = trim((string) ($doc_file ?? 'arquivo.md'));
$docPath = trim((string) ($doc_path ?? ''));
$docBody = trim((string) ($doc_body ?? ''));
$backUrl = trim((string) ($back_url ?? url('/local/mudancas')));
$backLabel = trim((string) ($back_label ?? 'Voltar para Mudancas'));
?>
<section class="min-h-screen bg-slate-950 px-4 py-8 text-slate-100">
  <style>
    .doc-card { border: 1px solid rgba(51, 65, 85, 0.9); background: rgba(2, 6, 23, 0.65); border-radius: 1rem; padding: 1rem; }
    .doc-pre { white-space: pre-wrap; word-break: break-word; font-size: 0.84rem; line-height: 1.75; color: rgba(226, 232, 240, 0.96); }
  </style>

  <div class="mx-auto max-w-7xl space-y-6">
    <?php \App\Support\View::component('site/local-tools-nav', ['active' => 'docs']); ?>

    <header class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Documento interno</p>
          <h1 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white"><?= htmlspecialchars($docFile, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
          <p class="mt-3 max-w-4xl text-sm leading-7 text-slate-300">
            Visualizacao direta do arquivo documentado na governanca oficial do projeto.
          </p>
        </div>
        <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-2xl border border-cyan-400/40 bg-cyan-500/10 px-4 py-2 text-sm font-semibold text-cyan-200 transition hover:border-cyan-300 hover:bg-cyan-500/20">
          <?= htmlspecialchars($backLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </a>
      </div>

      <div class="mt-5 grid gap-3 md:grid-cols-4">
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Grupo</p>
          <p class="mt-1 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars($docGroup, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        </div>
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Versao</p>
          <p class="mt-1 font-rajdhani text-2xl font-bold text-white"><?= htmlspecialchars($projectVersion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        </div>
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Atualizado em</p>
          <p class="mt-1 text-sm text-slate-200"><?= htmlspecialchars($generatedAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        </div>
        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-4">
          <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Caminho</p>
          <p class="mt-1 text-sm text-slate-200"><?= htmlspecialchars($docPath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        </div>
      </div>
    </header>

    <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
      <div class="doc-card">
        <pre class="doc-pre"><?= htmlspecialchars($docBody, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
      </div>
    </section>
  </div>
</section>
