<?php

declare(strict_types=1);

$embedMode = (bool) ($embed_mode ?? false);
$adminEmbed = (bool) ($admin_embed ?? false);
$monitorSection = (string) ($monitor_section ?? 'resumo');
$monitorSections = is_array($monitor_sections ?? null) ? $monitor_sections : [];
$monitorBaseUrl = (string) ($monitor_base_url ?? ($adminEmbed ? url('/admin/central-operacional?aba=monitoramento') : url('/local/monitoramento')));

$sectionUrl = static function (string $section) use ($monitorBaseUrl): string {
    $basePath = parse_url($monitorBaseUrl, PHP_URL_PATH);
    $baseQuery = parse_url($monitorBaseUrl, PHP_URL_QUERY);
    if (is_string($basePath) && str_contains($basePath, '/seo-tecnico') && ($baseQuery === null || $baseQuery === false || $baseQuery === '')) {
        return rtrim($monitorBaseUrl, '/') . '/' . rawurlencode($section);
    }

    $separator = str_contains($monitorBaseUrl, '?') ? '&' : '?';
    return $monitorBaseUrl . $separator . 'monitor_secao=' . rawurlencode($section);
};
?>
<section class="<?= $adminEmbed ? 'text-slate-100' : 'min-h-screen bg-slate-950 px-4 py-8 text-slate-100' ?>">
  <style>
    .search-console-inline-loading[hidden] {
      display: none;
    }
  </style>

  <div class="<?= $adminEmbed ? 'space-y-6' : 'mx-auto max-w-7xl space-y-6' ?>">
    <?php if (!$embedMode): ?>
      <?php \App\Support\View::component('site/local-tools-nav', ['active' => 'operations']); ?>
    <?php endif; ?>

    <div class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Monitoramento Externo</p>
      <h1 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Google Search Console</h1>
      <p class="mt-3 max-w-4xl text-sm leading-7 text-slate-300">Esta aba conecta a propriedade do Search Console ao admin local para acompanhar performance organica, sitemaps e o estado indexado de URLs especificas sem sair da Central Operacional.</p>
    </div>

    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-4">
      <div class="grid gap-3 md:grid-cols-2">
        <?php foreach ($monitorSections as $key => $section): ?>
          <?php $isActive = $key === $monitorSection; ?>
          <a
            href="<?= htmlspecialchars($sectionUrl((string) $key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            data-monitor-link="true"
            class="rounded-2xl border px-4 py-4 transition <?= $isActive ? 'border-cyan-300/70 bg-cyan-500/15 text-cyan-100 shadow-[0_0_24px_rgba(34,211,238,0.12)]' : 'border-slate-700 bg-slate-950/70 text-slate-200 hover:border-cyan-400/50 hover:bg-cyan-500/10' ?>"
            aria-current="<?= $isActive ? 'page' : 'false' ?>"
          >
            <div class="font-orbitron text-sm font-bold tracking-wide"><?= htmlspecialchars((string) ($section['label'] ?? $key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div class="mt-1 text-xs text-slate-400"><?= htmlspecialchars((string) ($section['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div id="search-console-loading" class="search-console-inline-loading rounded-2xl border border-cyan-500/20 bg-cyan-500/10 px-4 py-3 text-sm text-cyan-100" hidden>
      Carregando monitoramento do Search Console...
    </div>

    <div data-search-console-content>
      <?= \App\Support\View::fragment('site/partials/search-console-monitor-content', get_defined_vars()) ?>
    </div>
  </div>

  <script>
    (() => {
      const adminEmbed = <?= $adminEmbed ? 'true' : 'false' ?>;
      const overlay = document.getElementById('search-console-loading');
      const content = document.querySelector('[data-search-console-content]');
      const links = Array.from(document.querySelectorAll('[data-monitor-link="true"]'));
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

      const withFragment = (url) => {
        const next = new URL(url, window.location.origin);
        next.searchParams.set('monitor_fragment', '1');
        return next.toString();
      };

      const isMonitorUrl = (url) => {
        const next = new URL(url, window.location.origin);
        return next.pathname.includes('/monitoramento')
          || next.pathname.includes('/seo-tecnico')
          || next.searchParams.get('aba') === 'monitoramento';
      };

      const loadSection = async (url, push = true) => {
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
          content.innerHTML = html;
          rehydrateScripts(content);
          activateLink(url);

          if (push) {
            window.history.pushState({ searchConsoleMonitor: true }, '', url);
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

      content.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!(target instanceof HTMLAnchorElement)) {
          return;
        }

        if (!isMonitorUrl(target.href)) {
          return;
        }

        event.preventDefault();
        loadSection(target.href, true);
      });

      content.addEventListener('submit', (event) => {
        const form = event.target instanceof HTMLFormElement ? event.target : null;
        if (!form || form.method.toUpperCase() !== 'GET') {
          return;
        }

        const action = form.getAttribute('action') || window.location.href;
        if (!isMonitorUrl(action)) {
          return;
        }

        event.preventDefault();

        const next = new URL(action, window.location.origin);
        const formData = new FormData(form);
        for (const [key, value] of formData.entries()) {
          next.searchParams.set(key, String(value));
        }

        loadSection(next.toString(), true);
      });

      if (!adminEmbed) {
        window.addEventListener('popstate', () => {
          const url = new URL(window.location.href);
          if (!url.pathname.includes('/monitoramento') && !url.pathname.includes('/local/monitoramento')) {
            return;
          }

          const section = url.searchParams.get('monitor_secao');
          if (section) {
            loadSection(window.location.href, false);
          }
        });
      }
    })();
  </script>
</section>
