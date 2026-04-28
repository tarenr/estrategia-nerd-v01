<?php

declare(strict_types=1);

$tabs = is_array($tabs ?? null) ? $tabs : [];
$activeTab = (string) ($active_tab ?? array_key_first($tabs) ?? 'backlog');
$activeConfig = is_array($tabs[$activeTab] ?? null) ? $tabs[$activeTab] : reset($tabs);
$contentHtml = (string) ($content_html ?? '');
?>
<section class="space-y-5">
  <style>
    .admin-hub-loading {
      position: fixed;
      inset: 0;
      z-index: 9998;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(2, 6, 23, 0.58);
      backdrop-filter: blur(8px);
    }

    .admin-hub-loading.is-visible {
      display: flex;
    }

    .admin-hub-loading-card {
      width: min(92vw, 26rem);
      border-radius: 1.5rem;
      border: 1px solid rgba(34, 211, 238, 0.2);
      background: rgba(15, 23, 42, 0.96);
      padding: 1.25rem 1.35rem;
      box-shadow: 0 0 32px rgba(6, 182, 212, 0.12);
    }
  </style>

  <div id="knowledge-hub-loading" class="admin-hub-loading" aria-hidden="true">
    <div class="admin-hub-loading-card">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Carregando</p>
      <h2 class="mt-3 font-orbitron text-xl font-black text-white">Abrindo aba</h2>
      <p class="mt-3 text-sm leading-7 text-slate-300">Estamos preparando o conteudo desta area tecnica.</p>
    </div>
  </div>

<header class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_30px_rgba(6,182,212,0.06)]">
    <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Base Tecnica Local</p>
    <h1 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Backlog, documentacao e regras no padrao do admin</h1>
    <p class="mt-3 max-w-4xl text-sm leading-7 text-slate-300">
      Este modulo concentra planejamento, documentacao e politica permanente do projeto em um unico lugar, sem sair do admin local.
    </p>
  </header>

  <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-4">
    <div class="grid gap-3 md:grid-cols-3">
      <?php foreach ($tabs as $key => $tab): ?>
        <?php $isActive = $key === $activeTab; ?>
        <a
          href="<?= htmlspecialchars(url('/admin/base-tecnica?aba=' . $key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
          class="rounded-2xl border px-4 py-4 transition <?= $isActive ? 'border-cyan-300/70 bg-cyan-500/15 text-cyan-100 shadow-[0_0_24px_rgba(34,211,238,0.12)]' : 'border-slate-700 bg-slate-950/70 text-slate-200 hover:border-cyan-400/50 hover:bg-cyan-500/10' ?>"
          aria-current="<?= $isActive ? 'page' : 'false' ?>"
        >
          <div class="font-orbitron text-sm font-bold tracking-wide"><?= htmlspecialchars((string) ($tab['label'] ?? $key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="mt-1 text-xs text-slate-400"><?= htmlspecialchars((string) ($tab['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <section aria-labelledby="knowledge-tab-content">
    <h2 id="knowledge-tab-content" class="sr-only"><?= htmlspecialchars((string) ($activeConfig['label'] ?? 'Base Tecnica'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
    <?= $contentHtml ?>
  </section>
</section>
<script>
  (() => {
    const overlay = document.getElementById('knowledge-hub-loading');
    if (!overlay) {
      return;
    }

    const links = document.querySelectorAll('a[href*="/admin/base-tecnica?aba="]');
    for (const link of links) {
      link.addEventListener('click', () => {
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');
      });
    }
  })();
</script>
