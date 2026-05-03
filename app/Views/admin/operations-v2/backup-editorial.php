<?php

declare(strict_types=1);

use App\Support\View;

$module = is_array($module ?? null) ? $module : [];
$contentTools = is_array($content_tools ?? null) ? $content_tools : [];
$contentTools['admin_embed'] = true;
$contentTools['embed_mode'] = true;
$editorialSection = (string) ($contentTools['editorial_section'] ?? 'resumo');
$editorialSections = is_array($contentTools['editorial_sections'] ?? null) ? $contentTools['editorial_sections'] : [];
$editorialBaseUrl = (string) ($contentTools['editorial_base_url'] ?? url('/admin/central-operacional-v2/backup-editorial'));
$sectionUrl = static function (string $section) use ($editorialBaseUrl): string {
    $separator = str_contains($editorialBaseUrl, '?') ? '&' : '?';

    return $editorialBaseUrl . $separator . 'editorial_secao=' . rawurlencode($section);
};
?>
<section class="space-y-6">
  <?php View::component('admin/v2/page-header', [
      'eyebrow' => 'Central Operacional',
      'title' => (string) ($module['label'] ?? 'Backup Editorial e Restore'),
      'description' => '',
      'actions' => [
          [
              'href' => url('/admin/central-operacional-v2'),
              'label' => 'Voltar',
              'icon' => 'fa-solid fa-arrow-left',
              'variant' => 'secondary',
          ],
      ],
  ]); ?>

  <style>
    .editorial-progress-overlay {
      position: fixed;
      inset: 0;
      z-index: 9999;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(2, 6, 23, 0.84);
      backdrop-filter: blur(12px);
    }

    .editorial-progress-overlay.is-visible {
      display: flex;
    }

    .editorial-progress-card {
      width: min(92vw, 34rem);
      border-radius: 1.75rem;
      border: 1px solid rgba(34, 211, 238, 0.25);
      background: linear-gradient(180deg, rgba(15, 23, 42, 0.96), rgba(2, 6, 23, 0.96));
      padding: 1.5rem;
      box-shadow: 0 0 40px rgba(6, 182, 212, 0.12);
    }

    .editorial-progress-bar {
      height: 0.8rem;
      overflow: hidden;
      border-radius: 999px;
      background: rgba(30, 41, 59, 0.9);
      border: 1px solid rgba(51, 65, 85, 0.8);
    }

    .editorial-progress-fill {
      height: 100%;
      width: 8%;
      border-radius: inherit;
      background: linear-gradient(90deg, #22d3ee, #60a5fa, #c084fc);
      box-shadow: 0 0 24px rgba(96, 165, 250, 0.35);
      transition: width 0.4s ease;
    }
  </style>

  <div id="editorial-progress-overlay" class="editorial-progress-overlay" aria-hidden="true">
    <div class="editorial-progress-card">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Processando</p>
      <h2 id="editorial-progress-title" class="mt-3 font-orbitron text-2xl font-black text-white">Executando rotina editorial</h2>
      <p id="editorial-progress-message" class="mt-3 text-sm leading-7 text-slate-300">Aguardando o proximo passo da rotina.</p>
      <div class="mt-6 editorial-progress-bar">
        <div id="editorial-progress-fill" class="editorial-progress-fill"></div>
      </div>
      <div class="mt-4 flex items-center justify-between text-xs uppercase tracking-[0.2em] text-slate-400">
        <span id="editorial-progress-stage">Preparando</span>
        <span id="editorial-progress-percent">0%</span>
      </div>
      <p id="editorial-progress-meta" class="mt-4 text-xs text-slate-500">A tela acompanha a rotina atual de conteudo.</p>
    </div>
  </div>

  <div class="rounded-[1.25rem] border border-slate-800 bg-slate-950/70 p-2">
    <?php $sectionCount = max(1, count($editorialSections)); ?>
    <div class="grid gap-2 md:grid-cols-2 <?= $sectionCount >= 5 ? 'xl:grid-cols-5' : 'xl:grid-cols-' . $sectionCount ?>">
      <?php foreach ($editorialSections as $key => $section): ?>
        <?php $isActive = $key === $editorialSection; ?>
        <a
          href="<?= htmlspecialchars($sectionUrl((string) $key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
          data-editorial-link="true"
          data-editorial-tab="true"
          class="flex min-h-11 items-center rounded-xl border px-4 py-3 text-left transition <?= $isActive ? 'border-cyan-400/45 bg-cyan-500/10 text-cyan-100 shadow-[0_0_22px_rgba(34,211,238,0.12)]' : 'border-slate-800 bg-slate-900/70 text-slate-300 hover:border-cyan-500/35 hover:bg-cyan-500/10 hover:text-cyan-100' ?>"
          aria-current="<?= $isActive ? 'page' : 'false' ?>"
        >
          <div class="font-orbitron text-xs font-black uppercase tracking-[0.14em]"><?= htmlspecialchars((string) ($section['label'] ?? $key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div data-editorial-inline-loading class="rounded-2xl border border-cyan-500/20 bg-cyan-500/10 px-4 py-3 text-sm text-cyan-100" hidden>
    Carregando secao editorial...
  </div>

  <div data-editorial-content>
    <?= View::fragment('admin/operations-v2/partials/backup-editorial-content', $contentTools) ?>
  </div>

  <script>
    (() => {
      const overlay = document.getElementById('editorial-progress-overlay');
      const progressTitle = document.getElementById('editorial-progress-title');
      const progressMessage = document.getElementById('editorial-progress-message');
      const progressStage = document.getElementById('editorial-progress-stage');
      const progressPercent = document.getElementById('editorial-progress-percent');
      const progressFill = document.getElementById('editorial-progress-fill');
      const progressMeta = document.getElementById('editorial-progress-meta');
      const content = document.querySelector('[data-editorial-content]');
      const inlineLoading = document.querySelector('[data-editorial-inline-loading]');
      const topLinks = Array.from(document.querySelectorAll('[data-editorial-tab="true"]'));
      if (!overlay || !progressTitle || !progressMessage || !progressStage || !progressPercent || !progressFill || !progressMeta || !content || !inlineLoading) {
        return;
      }

      let pollTimer = null;
      const progressUrl = '<?= url('/local/conteudo') ?>';

      const setModalState = ({ title, message, stage, percent }) => {
        const nextPercent = Math.max(0, Math.min(100, Number(percent || 8)));
        progressTitle.textContent = title || 'Executando rotina editorial';
        progressMessage.textContent = message || 'Aguardando o proximo passo da rotina.';
        progressStage.textContent = stage || 'Preparando';
        progressPercent.textContent = `${nextPercent}%`;
        progressFill.style.width = Math.max(6, nextPercent) + '%';
      };

      const stopPolling = () => {
        if (pollTimer !== null) {
          window.clearInterval(pollTimer);
          pollTimer = null;
        }
      };

      const setModalLoading = (visible, form = null) => {
        overlay.classList.toggle('is-visible', visible);
        overlay.setAttribute('aria-hidden', visible ? 'false' : 'true');
        if (!visible) {
          stopPolling();
          return;
        }

        setModalState({
          title: form?.dataset.progressTitle || 'Executando rotina editorial',
          message: form?.dataset.progressMessage || 'Preparando a rotina solicitada.',
          stage: form?.dataset.progressStage || 'Preparando',
          percent: 10,
        });
        progressMeta.textContent = 'Aguardando atualizacao da rotina...';
      };

      const activateLink = (url) => {
        const current = new URL(url, window.location.origin);
        const activeSection = current.searchParams.get('editorial_secao') || 'resumo';
        topLinks.forEach((link) => {
          const linkUrl = new URL(link.href, window.location.origin);
          const isActive = (linkUrl.searchParams.get('editorial_secao') || 'resumo') === activeSection;
          link.classList.toggle('border-cyan-400/45', isActive);
          link.classList.toggle('bg-cyan-500/10', isActive);
          link.classList.toggle('text-cyan-100', isActive);
          link.classList.toggle('border-slate-800', !isActive);
          link.classList.toggle('bg-slate-900/70', !isActive);
          link.classList.toggle('text-slate-300', !isActive);
          link.setAttribute('aria-current', isActive ? 'page' : 'false');
        });
      };

      document.addEventListener('click', async (event) => {
        const link = event.target instanceof Element ? event.target.closest('[data-editorial-link="true"]') : null;
        if (!(link instanceof HTMLAnchorElement)) {
          return;
        }
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
          return;
        }

        event.preventDefault();
        const url = new URL(link.href);
        url.searchParams.set('editorial_fragment', '1');
        inlineLoading.hidden = false;

        try {
          const response = await fetch(url.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
          });
          if (!response.ok) {
            throw new Error('Falha ao carregar secao.');
          }
          content.innerHTML = await response.text();
          url.searchParams.delete('editorial_fragment');
          window.history.pushState({}, '', url.toString());
          activateLink(url.toString());
        } catch (error) {
          window.location.href = link.href;
        } finally {
          inlineLoading.hidden = true;
        }
      });

      const pollProgress = (progressId) => {
        stopPolling();
        if (!progressId) {
          return;
        }

        pollTimer = window.setInterval(async () => {
          try {
            const url = new URL(progressUrl, window.location.origin);
            url.searchParams.set('content_progress', '1');
            url.searchParams.set('id', progressId);
            const response = await fetch(url.toString(), {
              headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) {
              return;
            }

            const data = await response.json();
            setModalState({
              title: data.title,
              message: data.message,
              stage: data.stage,
              percent: data.percent,
            });

            if (data.status === 'completed' || data.status === 'error') {
              stopPolling();
              progressMeta.textContent = data.status === 'completed' ? 'Rotina concluida. Atualizando tela...' : 'A rotina informou falha.';
            }
          } catch (error) {
            stopPolling();
          }
        }, 900);
      };

      document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.classList.contains('editorial-action-form')) {
          return;
        }

        let progressInput = form.querySelector('input[name="progress_id"]');
        if (!progressInput) {
          progressInput = document.createElement('input');
          progressInput.type = 'hidden';
          progressInput.name = 'progress_id';
          form.appendChild(progressInput);
        }

        if (!progressInput.value) {
          progressInput.value = `editorial_${Date.now()}_${Math.random().toString(16).slice(2)}`;
        }

        setModalLoading(true, form);
        pollProgress(progressInput.value);
      });
    })();
  </script>
</section>
