<?php

declare(strict_types=1);

$embedMode = (bool) ($embed_mode ?? false);
$adminEmbed = (bool) ($admin_embed ?? false);
$flash = is_array($flash ?? null) ? $flash : null;
$flashClass = $flash !== null
    ? (($flash['type'] ?? '') === 'error'
        ? 'border-rose-500/40 bg-rose-500/10 text-rose-100'
        : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100')
    : '';
$contentSection = (string) ($content_section ?? 'resumo');
$contentSections = is_array($content_sections ?? null) ? $content_sections : [];
$contentBaseUrl = (string) ($content_base_url ?? ($adminEmbed ? url('/admin/central-operacional?aba=conteudo') : url('/local/conteudo')));

$sectionUrl = static function (string $section) use ($contentBaseUrl): string {
    $separator = str_contains($contentBaseUrl, '?') ? '&' : '?';

    return $contentBaseUrl . $separator . 'content_secao=' . rawurlencode($section);
};
?>
<section class="<?= $adminEmbed ? 'text-slate-100' : 'min-h-screen bg-slate-950 px-4 py-8 text-slate-100' ?>">
  <style>
    .content-inline-loading[hidden] {
      display: none;
    }

    .content-progress-overlay {
      position: fixed;
      inset: 0;
      z-index: 9999;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(2, 6, 23, 0.84);
      backdrop-filter: blur(12px);
    }

    .content-progress-overlay.is-visible {
      display: flex;
    }

    .content-progress-card {
      width: min(92vw, 34rem);
      border-radius: 1.75rem;
      border: 1px solid rgba(34, 211, 238, 0.25);
      background: linear-gradient(180deg, rgba(15, 23, 42, 0.96), rgba(2, 6, 23, 0.96));
      padding: 1.5rem;
      box-shadow: 0 0 40px rgba(6, 182, 212, 0.12);
    }

    .content-progress-bar {
      height: 0.8rem;
      overflow: hidden;
      border-radius: 999px;
      background: rgba(30, 41, 59, 0.9);
      border: 1px solid rgba(51, 65, 85, 0.8);
    }

    .content-progress-fill {
      height: 100%;
      width: 8%;
      border-radius: inherit;
      background: linear-gradient(90deg, #22d3ee, #60a5fa, #c084fc);
      box-shadow: 0 0 24px rgba(96, 165, 250, 0.35);
      transition: width 0.4s ease;
    }

    .content-progress-dots span {
      animation: contentBlink 1.2s infinite ease-in-out;
      display: inline-block;
    }

    .content-progress-dots span:nth-child(2) { animation-delay: 0.18s; }
    .content-progress-dots span:nth-child(3) { animation-delay: 0.36s; }

    @keyframes contentBlink {
      0%, 80%, 100% { opacity: 0.25; transform: translateY(0); }
      40% { opacity: 1; transform: translateY(-1px); }
    }
  </style>

  <div id="content-progress-overlay" class="content-progress-overlay" aria-hidden="true">
    <div class="content-progress-card">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Processando</p>
      <h2 id="content-progress-title" class="mt-3 font-orbitron text-2xl font-black text-white">Executando rotina de conteudo</h2>
      <p id="content-progress-message" class="mt-3 text-sm leading-7 text-slate-300">Estamos preparando o pacote e validando os arquivos. Esse processo pode levar alguns segundos.</p>
      <div class="mt-6 content-progress-bar">
        <div id="content-progress-fill" class="content-progress-fill"></div>
      </div>
      <div class="mt-4 flex items-center justify-between text-xs uppercase tracking-[0.2em] text-slate-400">
        <span id="content-progress-stage">Preparando</span>
        <span id="content-progress-percent">0%</span>
      </div>
      <p id="content-progress-meta" class="mt-4 text-xs text-slate-500">Para evitar envio duplicado, os botoes ficam bloqueados ate a resposta da rotina.</p>
    </div>
  </div>

  <div class="<?= $adminEmbed ? 'space-y-6' : 'mx-auto max-w-7xl space-y-6' ?>">
    <?php if (!$embedMode): ?>
      <?php \App\Support\View::component('site/local-tools-nav', ['active' => 'content']); ?>
    <?php endif; ?>

    <div class="rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Rotina Local</p>
          <h1 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Publicacao de Conteudo</h1>
          <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-300">Esta area foi dividida em subabas para separar panorama, editorial, codigo e publicacao. Assim a consulta inicial fica mais leve e as partes mais pesadas entram quando voce precisa delas.</p>
        </div>
      </div>

      <?php if ($flash !== null): ?>
        <div class="mt-4 rounded-2xl border px-4 py-3 text-sm <?= htmlspecialchars($flashClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
          <?= htmlspecialchars((string) ($flash['message'] ?? '-'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-4">
      <div class="grid gap-3 xl:grid-cols-4">
        <?php foreach ($contentSections as $key => $section): ?>
          <?php $isActive = $key === $contentSection; ?>
          <a
            href="<?= htmlspecialchars($sectionUrl((string) $key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            data-content-link="true"
            class="rounded-2xl border px-4 py-4 transition <?= $isActive ? 'border-cyan-300/70 bg-cyan-500/15 text-cyan-100 shadow-[0_0_24px_rgba(34,211,238,0.12)]' : 'border-slate-700 bg-slate-950/70 text-slate-200 hover:border-cyan-400/50 hover:bg-cyan-500/10' ?>"
            aria-current="<?= $isActive ? 'page' : 'false' ?>"
          >
            <div class="font-orbitron text-sm font-bold tracking-wide"><?= htmlspecialchars((string) ($section['label'] ?? $key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <div class="mt-1 text-xs text-slate-400"><?= htmlspecialchars((string) ($section['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div data-content-inline-loading class="content-inline-loading rounded-2xl border border-cyan-500/20 bg-cyan-500/10 px-4 py-3 text-sm text-cyan-100" hidden>
      Carregando secao de conteudo...
    </div>

    <div data-content-sync-content>
      <?= \App\Support\View::fragment('site/partials/content-sync-content', get_defined_vars()) ?>
    </div>
  </div>

  <script>
    (() => {
      const adminEmbed = <?= $adminEmbed ? 'true' : 'false' ?>;
      const overlay = document.getElementById('content-progress-overlay');
      const progressTitle = document.getElementById('content-progress-title');
      const progressMessage = document.getElementById('content-progress-message');
      const progressStage = document.getElementById('content-progress-stage');
      const progressPercent = document.getElementById('content-progress-percent');
      const progressFill = document.getElementById('content-progress-fill');
      const progressMeta = document.getElementById('content-progress-meta');
      const content = document.querySelector('[data-content-sync-content]');
      const inlineLoading = document.querySelector('[data-content-inline-loading]');
      const links = Array.from(document.querySelectorAll('[data-content-link="true"]'));
      if (!overlay || !progressTitle || !progressMessage || !progressStage || !progressPercent || !progressFill || !progressMeta || !content || !inlineLoading || links.length === 0) {
        return;
      }

      let progressTimer = null;

      const setModalLoading = (visible) => {
        overlay.classList.toggle('is-visible', visible);
        overlay.setAttribute('aria-hidden', visible ? 'false' : 'true');
      };

      const setProgressSnapshot = (snapshot) => {
        const percent = Number(snapshot?.percent ?? 0);
        progressTitle.textContent = snapshot?.title || 'Executando rotina de conteudo';
        progressMessage.textContent = snapshot?.message || 'Aguardando retorno da rotina.';
        progressStage.textContent = snapshot?.stage || 'Aguardando';
        progressPercent.textContent = `${Math.max(0, Math.min(100, percent))}%`;
        progressFill.style.width = `${Math.max(0, Math.min(100, percent))}%`;
        progressMeta.textContent = snapshot?.updated_at
          ? `Ultima atualizacao: ${snapshot.updated_at}`
          : 'Para evitar envio duplicado, os botoes ficam bloqueados ate a resposta da rotina.';
      };

      const setInlineLoading = (visible) => {
        inlineLoading.hidden = !visible;
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
        next.searchParams.set('content_fragment', '1');
        return next.toString();
      };

      const withProgress = (id) => {
        const next = new URL(window.location.origin + '<?= url('/local/conteudo') ?>');
        next.searchParams.set('content_progress', '1');
        next.searchParams.set('id', id);
        return next.toString();
      };

      const makeProgressId = () => `content-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;

      const stopProgressPolling = () => {
        if (progressTimer) {
          window.clearTimeout(progressTimer);
          progressTimer = null;
        }
      };

      const refreshCurrentSection = async (redirectUrl) => {
        const nextUrl = redirectUrl || window.location.href;
        const targetUrl = new URL(nextUrl, window.location.origin);
        const contentSection = targetUrl.searchParams.get('content_secao');
        if (!contentSection) {
          window.location.href = nextUrl;
          return;
        }

        await loadSection(targetUrl.toString(), true);
      };

      const pollProgress = (id) => {
        stopProgressPolling();
        progressTimer = window.setInterval(async () => {
          try {
            const response = await fetch(withProgress(id), {
              headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) {
              return;
            }

            const snapshot = await response.json();
            setProgressSnapshot(snapshot);
          } catch (error) {
            return;
          }
        }, 900);
      };

      const finishAsyncFlow = async (redirectUrl) => {
        stopProgressPolling();
        setModalLoading(false);
        await refreshCurrentSection(redirectUrl);
      };

      const bindActionForms = (root = document) => {
        const forms = Array.from(root.querySelectorAll('.content-action-form'));

        forms.forEach((form) => {
          if (form.dataset.contentBound === 'true') {
            return;
          }

          form.dataset.contentBound = 'true';
          form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const progressId = makeProgressId();
            const title = form.dataset.progressTitle || 'Executando rotina de conteudo';
            const message = form.dataset.progressMessage || 'A rotina de conteudo foi iniciada.';
            const stage = form.dataset.progressStage || 'Preparando';
            setProgressSnapshot({
              title,
              message,
              stage,
              percent: 4,
            });
            setModalLoading(true);

            const formData = new FormData(form);
            formData.set('response', 'json');
            formData.set('progress_id', progressId);

            const currentUrl = new URL(window.location.href);
            if (!currentUrl.searchParams.get('content_secao')) {
              currentUrl.searchParams.set('content_secao', 'resumo');
            }
            formData.set('redirect_to', `${currentUrl.pathname}${currentUrl.search}`);

            try {
              pollProgress(progressId);
              const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData,
              });

              const payload = await response.json().catch(() => null);
              if (!response.ok || !payload?.ok) {
                throw new Error(payload?.message || 'Falha ao executar a rotina de conteudo.');
              }

              setProgressSnapshot({
                title: payload?.title || title,
                message: payload?.message || 'Rotina concluida com sucesso.',
                stage: 'Concluido',
                percent: 100,
                updated_at: new Date().toISOString(),
              });
              await finishAsyncFlow(payload.redirect_url || currentUrl.toString());
            } catch (error) {
              stopProgressPolling();
              setModalLoading(false);
              window.location.href = `${currentUrl.pathname}${currentUrl.search}`;
            }
          });
        });
      };

      const loadSection = async (url, push = true) => {
        setInlineLoading(true);

        try {
          const response = await fetch(withFragment(url), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          });

          if (!response.ok) {
            window.location.href = url;
            return;
          }

          content.innerHTML = await response.text();
          rehydrateScripts(content);
          bindActionForms(content);
          activateLink(url);

          if (push) {
            window.history.pushState({ contentSyncTools: true }, '', url);
          }
        } catch (error) {
          window.location.href = url;
        } finally {
          setInlineLoading(false);
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
          if (!url.pathname.includes('/local/conteudo')) {
            return;
          }

          if (url.searchParams.get('content_secao')) {
            loadSection(window.location.href, false);
          }
        });
      }

      bindActionForms(document);
    })();
  </script>
</section>
