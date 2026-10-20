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
    .rules-card { border-radius: 1rem; padding: 1.15rem 1.25rem; font-size: 0.875rem; line-height: 1.55rem; }
    .rules-callout-danger { border: 1px solid rgba(244, 63, 94, 0.35); background: rgba(136, 19, 55, 0.25); color: #fecdd3; }
    .rules-callout-warn { border: 1px solid rgba(245, 158, 11, 0.35); background: rgba(120, 53, 15, 0.25); color: #fde68a; }
    .rules-callout-info { border: 1px solid rgba(6, 182, 212, 0.35); background: rgba(8, 47, 73, 0.25); color: #bae6fd; }
    .rules-callout-success { border: 1px solid rgba(16, 185, 129, 0.35); background: rgba(6, 78, 59, 0.25); color: #a7f3d0; }
    .doc-pre { white-space: pre-wrap; word-break: break-word; font-family: monospace; font-size: 0.8rem; line-height: 1.6; color: #cbd5e1; }
  </style>

  <div class="<?= $adminEmbed ? 'space-y-6' : 'mx-auto max-w-7xl space-y-6' ?>">
    <?php if (!$embedMode && !$adminEmbed): ?>
      <?php \App\Support\View::component('site/local-tools-nav', ['active' => 'rules']); ?>
      <header class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_35px_rgba(6,182,212,0.06)]">
        <p class="font-orbitron text-xs uppercase tracking-[0.3em] text-cyan-300/70">Regras Permanentes</p>
        <h1 class="mt-2 font-orbitron text-2xl font-black tracking-tight text-white md:text-3xl text-balance">Política Operacional Obrigatória do Projeto</h1>
        <p class="mt-2.5 max-w-4xl text-sm leading-relaxed text-slate-300">Regras permanentes de arquitetura, paridade, segurança e disciplina operacional do Estratégia Nerd.</p>
      </header>
    <?php endif; ?>

    <!-- 4 Regras de Ouro em Destaque Visual -->
    <div>
      <div class="flex items-center justify-between border-b border-slate-800 pb-3">
        <h2 class="font-orbitron text-xs font-black uppercase tracking-[0.2em] text-slate-400">As 4 Regras de Maior Peso</h2>
        <span class="rounded-full border border-rose-500/30 bg-rose-950/60 px-2 py-0.5 font-mono text-[10px] font-bold text-rose-300">Bloqueio Absoluto</span>
      </div>

      <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div class="rules-card rules-callout-danger">
          <div class="flex items-center gap-2 font-bold text-rose-200">
            <i class="fa-solid fa-ban text-xs"></i>
            <span>1. Trava Atômica de Origem (Stage &rarr; Produção)</span>
          </div>
          <p class="mt-2 text-xs leading-relaxed text-rose-100">
            É <strong>terminantemente proibido</strong> publicar código ou pacotes de conteúdo em produção a partir do ambiente local. Pacotes de produção devem nascer exclusivamente da <code class="font-mono text-white">estrategia-nerd-stage</code> validada.
          </p>
        </div>

        <div class="rules-card rules-callout-warn">
          <div class="flex items-center gap-2 font-bold text-amber-200">
            <i class="fa-solid fa-lock text-xs"></i>
            <span>2. Isolamento do Módulo Instagram (Local-Only)</span>
          </div>
          <p class="mt-2 text-xs leading-relaxed text-amber-100">
            Tokens da Meta Graph API v21.0, fila de postagens e credenciais residem exclusivamente no MySQL local (<code class="font-mono text-white">$GLOBALS['pdo']</code>). Nunca trafegam para o banco remoto de produção.
          </p>
        </div>

        <div class="rules-card rules-callout-info">
          <div class="flex items-center gap-2 font-bold text-cyan-200">
            <i class="fa-solid fa-eye-slash text-xs"></i>
            <span>3. Bloqueio Anti-Indexação da Stage (Noindex)</span>
          </div>
          <p class="mt-2 text-xs leading-relaxed text-cyan-100">
            Com <code class="font-mono text-white">APP_ENV=stage</code>, o <code class="font-mono text-white">bootstrap.php</code> envia compulsoriamente <code class="font-mono text-white">X-Robots-Tag: noindex, nofollow</code> para proteger a autoridade de busca e impedir conteúdo duplicado.
          </p>
        </div>

        <div class="rules-card rules-callout-success">
          <div class="flex items-center gap-2 font-bold text-emerald-200">
            <i class="fa-solid fa-shield-check text-xs"></i>
            <span>4. Suíte Obrigatória Pré-Commit (Zero Falhas)</span>
          </div>
          <p class="mt-2 text-xs leading-relaxed text-emerald-100">
            Nenhuma alteração é commitada sem aprovação total de <code class="font-mono text-white">verify-changes.php</code> (lint, PHPStan Nível 5, renderizações multi-ambiente do dashboard e Instagram com 0 erros).
          </p>
        </div>
      </div>
    </div>

    <!-- Texto Oficial do Codex Preservado na Íntegra -->
    <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-sm">
      <div class="flex items-center justify-between border-b border-slate-800 pb-3">
        <h3 class="font-orbitron text-sm font-bold text-white">Texto Oficial do Codex em Vigor</h3>
        <span class="font-mono text-xs text-slate-500">docs/CODEX-REGRAS-PERMANENTES.txt</span>
      </div>
      <div class="mt-4 max-h-[600px] overflow-y-auto rounded-2xl border border-slate-800 bg-slate-950 p-4">
        <pre class="doc-pre"><?= htmlspecialchars($rulesBody, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
      </div>
    </section>
  </div>
</section>
