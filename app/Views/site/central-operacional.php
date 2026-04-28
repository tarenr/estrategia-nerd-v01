<?php

declare(strict_types=1);

$embedMode = (bool) ($embed_mode ?? false);
$adminEmbed = (bool) ($admin_embed ?? false);
$flash = is_array($flash ?? null) ? $flash : null;
$overviewSection = (string) ($overview_section ?? 'resumo');
$overviewSections = is_array($overview_sections ?? null) ? $overview_sections : [];
$overviewBaseUrl = (string) ($overview_base_url ?? ($adminEmbed ? url('/admin/central-operacional?aba=visao-geral') : url('/local/operacoes')));

$alertClasses = [
    'success' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100',
    'error' => 'border-rose-500/40 bg-rose-500/10 text-rose-100',
];
$flashClass = $flash !== null ? ($alertClasses[$flash['type']] ?? $alertClasses['success']) : '';

$sectionUrl = static function (string $section) use ($overviewBaseUrl): string {
    $separator = str_contains($overviewBaseUrl, '?') ? '&' : '?';

    return $overviewBaseUrl . $separator . 'secao=' . rawurlencode($section);
};
?>
<section class="<?= $adminEmbed ? 'text-slate-100' : 'min-h-screen bg-slate-950 px-4 py-8 text-slate-100' ?>">
  <style>
    .operations-overview-inline-loading[hidden] {
      display: none;
    }

    .operations-summary-card {
      display: flex;
      min-height: 13.5rem;
      flex-direction: column;
      justify-content: space-between;
      border-radius: 1.5rem;
      border: 1px solid rgba(30, 41, 59, 0.9);
      background:
        radial-gradient(circle at top right, rgba(34, 211, 238, 0.08), transparent 40%),
        rgba(15, 23, 42, 0.82);
      padding: 1.25rem;
      box-shadow: inset 0 1px 0 rgba(148, 163, 184, 0.05);
    }

    .operations-summary-card--highlight {
      border-color: rgba(34, 211, 238, 0.24);
      background:
        radial-gradient(circle at top right, rgba(34, 211, 238, 0.12), transparent 42%),
        rgba(15, 23, 42, 0.88);
    }

    .operations-summary-label {
      font-family: Orbitron, ui-sans-serif, system-ui, sans-serif;
      font-size: 0.68rem;
      font-weight: 700;
      letter-spacing: 0.22em;
      text-transform: uppercase;
      color: rgba(103, 232, 249, 0.8);
    }

    .operations-summary-headline {
      margin-top: 0.9rem;
      font-family: Rajdhani, ui-sans-serif, system-ui, sans-serif;
      font-size: 2rem;
      font-weight: 700;
      line-height: 1;
      color: #f8fafc;
    }

    .operations-summary-note {
      margin-top: 0.45rem;
      font-size: 0.9rem;
      color: #94a3b8;
    }

    .operations-summary-list {
      margin-top: 1rem;
      display: grid;
      gap: 0.6rem;
      font-size: 0.85rem;
    }

    .operations-summary-list > div {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      color: #cbd5e1;
    }

    .operations-summary-list span:first-child {
      color: #64748b;
    }

    .operations-summary-pill-row {
      margin-top: 1rem;
      display: flex;
      flex-wrap: wrap;
      gap: 0.55rem;
    }
  </style>

  <div class="<?= $adminEmbed ? 'space-y-6' : 'mx-auto max-w-7xl space-y-6' ?>">
    <?php if (!$embedMode): ?>
      <?php \App\Support\View::component('site/local-tools-nav', ['active' => 'operations']); ?>
    <?php endif; ?>

    <div class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Central Operacional</p>
      <h1 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Visao geral em subabas</h1>
      <p class="mt-3 max-w-4xl text-sm leading-7 text-slate-300">A leitura desta area foi dividida para evitar uma carga unica pesada demais. Agora cada subaba busca seu proprio bloco sob demanda: primeiro entra o shell leve, depois voce abre so o que precisa consultar.</p>
    </div>

    <?php if ($flash !== null): ?>
      <div class="rounded-2xl border px-4 py-3 text-sm <?= htmlspecialchars($flashClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <?= htmlspecialchars((string) ($flash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-4">
      <div class="grid gap-3 xl:grid-cols-4">
        <?php foreach ($overviewSections as $key => $section): ?>
          <?php $isActive = $key === $overviewSection; ?>
          <a
            href="<?= htmlspecialchars($sectionUrl((string) $key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            data-overview-link="true"
            class="rounded-2xl border px-4 py-4 transition <?= $isActive ? 'border-cyan-300/70 bg-cyan-500/15 text-cyan-100 shadow-[0_0_24px_rgba(34,211,238,0.12)]' : 'border-slate-700 bg-slate-950/70 text-slate-200 hover:border-cyan-400/50 hover:bg-cyan-500/10' ?>"
            aria-current="<?= $isActive ? 'page' : 'false' ?>"
          >
            <div class="font-orbitron text-sm font-bold tracking-wide"><?= htmlspecialchars((string) ($section['label'] ?? $key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div class="mt-1 text-xs text-slate-400"><?= htmlspecialchars((string) ($section['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div id="operations-overview-loading" class="operations-overview-inline-loading rounded-2xl border border-cyan-500/20 bg-cyan-500/10 px-4 py-3 text-sm text-cyan-100" hidden>
      Carregando bloco operacional...
    </div>

    <div data-operations-overview-content>
      <?= \App\Support\View::fragment('site/partials/operations-overview-content', get_defined_vars()) ?>
    </div>
  </div>

  <script>
    (() => {
      const adminEmbed = <?= $adminEmbed ? 'true' : 'false' ?>;
      const overlay = document.getElementById('operations-overview-loading');
      const content = document.querySelector('[data-operations-overview-content]');
      const links = Array.from(document.querySelectorAll('[data-overview-link="true"]'));
      if (!overlay || !content || links.length === 0) {
        return;
      }

      const setLoading = (visible) => {
        overlay.hidden = !visible;
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

      const withOverviewFragment = (url) => {
        const next = new URL(url, window.location.origin);
        next.searchParams.set('overview_fragment', '1');
        return next.toString();
      };

      const loadSection = async (url, push = true) => {
        setLoading(true);

        try {
          const response = await fetch(withOverviewFragment(url), {
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });

          if (!response.ok) {
            window.location.href = url;
            return;
          }

          const html = await response.text();
          content.innerHTML = html;
          rehydrateScripts(content);
          activateLink(url);

          if (push) {
            window.history.pushState({ operationsOverview: true }, '', url);
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
          loadSection(link.href, true);
        });
      });

      if (!adminEmbed) {
        window.addEventListener('popstate', () => {
          const url = new URL(window.location.href);
          if (!url.pathname.includes('/central-operacional') && !url.pathname.includes('/local/operacoes')) {
            return;
          }

          const section = url.searchParams.get('secao');
          if (section) {
            loadSection(window.location.href, false);
          }
        });
      }
    })();
  </script>
</section>
