<?php

declare(strict_types=1);

$tabs = is_array($tabs ?? null) ? $tabs : [];
$activeTab = (string) ($active_tab ?? array_key_first($tabs) ?? 'visao-geral');
$activeConfig = is_array($tabs[$activeTab] ?? null) ? $tabs[$activeTab] : reset($tabs);
$contentHtml = (string) ($content_html ?? '');
$activeTabUrl = (string) ($active_tab_url ?? url('/admin/central-operacional?aba=' . $activeTab));
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

  <div id="operations-hub-loading" class="admin-hub-loading" aria-hidden="true">
    <div class="admin-hub-loading-card">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Carregando</p>
      <h2 class="mt-3 font-orbitron text-xl font-black text-white">Abrindo aba</h2>
      <p class="mt-3 text-sm leading-7 text-slate-300">Estamos preparando o conteudo operacional desta area.</p>
    </div>
  </div>

<header class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_30px_rgba(6,182,212,0.06)]">
    <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Central Tecnica Local</p>
    <h1 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Central Operacional em abas</h1>
    <p class="mt-3 max-w-4xl text-sm leading-7 text-slate-300">
      Este modulo centraliza as operacoes tecnicas locais do projeto. Backup, restore, conteudo e visao geral ficam agrupados no mesmo menu principal do admin.
    </p>
  </header>

  <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-4">
    <div class="grid gap-3 md:grid-cols-3">
      <?php foreach ($tabs as $key => $tab): ?>
        <?php $isActive = $key === $activeTab; ?>
        <a
          href="<?= htmlspecialchars(url('/admin/central-operacional?aba=' . $key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
          class="rounded-2xl border px-4 py-4 transition <?= $isActive ? 'border-cyan-300/70 bg-cyan-500/15 text-cyan-100 shadow-[0_0_24px_rgba(34,211,238,0.12)]' : 'border-slate-700 bg-slate-950/70 text-slate-200 hover:border-cyan-400/50 hover:bg-cyan-500/10' ?>"
          aria-current="<?= $isActive ? 'page' : 'false' ?>"
        >
          <div class="font-orbitron text-sm font-bold tracking-wide"><?= htmlspecialchars((string) ($tab['label'] ?? $key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          <div class="mt-1 text-xs text-slate-400"><?= htmlspecialchars((string) ($tab['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <section aria-labelledby="operations-tab-content" data-operations-hub-content data-loaded="<?= $contentHtml !== '' ? 'true' : 'false' ?>">
    <h2 id="operations-tab-content" class="sr-only"><?= htmlspecialchars((string) ($activeConfig['label'] ?? 'Central Operacional'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h2>
    <?php if ($contentHtml !== ''): ?>
      <?= $contentHtml ?>
    <?php else: ?>
      <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 text-sm text-slate-400">
        Carregando conteudo operacional...
      </div>
    <?php endif; ?>
  </section>
</section>
<script>
  (() => {
    const overlay = document.getElementById('operations-hub-loading');
    if (!overlay) {
      return;
    }

    const content = document.querySelector('[data-operations-hub-content]');
    const links = Array.from(document.querySelectorAll('a[href*="/admin/central-operacional?aba="]'));
    if (!content || links.length === 0) {
      return;
    }

    const activeTabUrl = <?= json_encode($activeTabUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const setLoading = (visible) => {
      overlay.classList.toggle('is-visible', visible);
      overlay.setAttribute('aria-hidden', visible ? 'false' : 'true');
    };

    const activateLink = (url) => {
      links.forEach((link) => {
        const isActive = link.href === url;
        link.classList.toggle('border-cyan-300/70', isActive);
        link.classList.toggle('bg-cyan-500/15', isActive);
        link.classList.toggle('text-cyan-100', isActive);
        link.classList.toggle('shadow-[0_0_24px_rgba(34,211,238,0.12)]', isActive);
        link.classList.toggle('border-slate-700', !isActive);
        link.classList.toggle('bg-slate-950/70', !isActive);
        link.classList.toggle('text-slate-200', !isActive);
        link.setAttribute('aria-current', isActive ? 'page' : 'false');
      });
    };

    const rehydrateScripts = (root) => {
      for (const oldScript of root.querySelectorAll('script')) {
        const nextScript = document.createElement('script');
        for (const attr of oldScript.attributes) {
          nextScript.setAttribute(attr.name, attr.value);
        }
        nextScript.textContent = oldScript.textContent;
        oldScript.replaceWith(nextScript);
      }
    };

    const withFragment = (url) => {
      const next = new URL(url, window.location.origin);
      next.searchParams.set('fragment', '1');
      return next.toString();
    };

    const loadTab = async (url, push = true) => {
      setLoading(true);

      try {
        const response = await fetch(withFragment(url), {
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });

        if (!response.ok) {
          window.location.href = url;
          return;
        }

        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const nextContent = doc.querySelector('[data-operations-hub-content]');

        if (!nextContent) {
          window.location.href = url;
          return;
        }

        content.innerHTML = nextContent.innerHTML;
        rehydrateScripts(content);
        activateLink(url);

        if (push) {
          window.history.pushState({ operationsHub: true }, '', url);
        }
      } catch (error) {
        window.location.href = url;
      } finally {
        setLoading(false);
      }
    };

    links.forEach((link) => {
      link.addEventListener('click', (event) => {
        event.preventDefault();
        loadTab(link.href, true);
      });
    });

    window.addEventListener('popstate', () => {
      if (window.location.pathname.includes('/admin/central-operacional')) {
        loadTab(window.location.href, false);
      }
    });

    if (content.dataset.loaded !== 'true') {
      loadTab(activeTabUrl, false);
    }
  })();
</script>
