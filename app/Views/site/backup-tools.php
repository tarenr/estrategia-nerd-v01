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
$backupSection = (string) ($backup_section ?? 'resumo');
$backupSections = is_array($backup_sections ?? null) ? $backup_sections : [];
$backupBaseUrl = (string) ($backup_base_url ?? ($adminEmbed ? url('/admin/central-operacional?aba=backup-restore') : url('/local/backup')));
$backupBasePath = parse_url($backupBaseUrl, PHP_URL_PATH);
$backupBaseQuery = parse_url($backupBaseUrl, PHP_URL_QUERY);
$usesPrettyBackupSections = is_string($backupBasePath)
    && str_contains($backupBasePath, '/central-operacional-v2/backup-sistemico')
    && ($backupBaseQuery === null || $backupBaseQuery === false || $backupBaseQuery === '');

$sectionUrl = static function (string $section) use ($backupBaseUrl, $usesPrettyBackupSections): string {
    if ($usesPrettyBackupSections) {
        return rtrim($backupBaseUrl, '/') . '/' . rawurlencode($section);
    }

    $separator = str_contains($backupBaseUrl, '?') ? '&' : '?';

    return $backupBaseUrl . $separator . 'backup_secao=' . rawurlencode($section);
};
?>
<section class="<?= $adminEmbed ? 'text-slate-100' : 'min-h-screen bg-slate-950 px-4 py-8 text-slate-100' ?>">
  <style>
    .backup-inline-loading[hidden] {
      display: none;
    }

    .backup-progress-overlay {
      position: fixed;
      inset: 0;
      z-index: 9999;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(2, 6, 23, 0.84);
      backdrop-filter: blur(12px);
    }

    .backup-progress-overlay.is-visible {
      display: flex;
    }

    .backup-progress-card {
      width: min(92vw, 34rem);
      border-radius: 1.75rem;
      border: 1px solid rgba(34, 211, 238, 0.25);
      background: linear-gradient(180deg, rgba(15, 23, 42, 0.96), rgba(2, 6, 23, 0.96));
      padding: 1.5rem;
      box-shadow: 0 0 40px rgba(6, 182, 212, 0.12);
    }

    .backup-progress-bar {
      height: 0.8rem;
      overflow: hidden;
      border-radius: 999px;
      background: rgba(30, 41, 59, 0.9);
      border: 1px solid rgba(51, 65, 85, 0.8);
    }

    .backup-progress-fill {
      height: 100%;
      width: 8%;
      border-radius: inherit;
      background: linear-gradient(90deg, #22d3ee, #60a5fa, #c084fc);
      box-shadow: 0 0 24px rgba(96, 165, 250, 0.35);
      transition: width 0.4s ease;
    }

    .backup-progress-dots span {
      animation: backupBlink 1.2s infinite ease-in-out;
      display: inline-block;
    }

    .backup-progress-dots span:nth-child(2) { animation-delay: 0.18s; }
    .backup-progress-dots span:nth-child(3) { animation-delay: 0.36s; }

    @keyframes backupBlink {
      0%, 80%, 100% { opacity: 0.25; transform: translateY(0); }
      40% { opacity: 1; transform: translateY(-1px); }
    }
  </style>

  <div id="backup-progress-overlay" class="backup-progress-overlay" aria-hidden="true">
    <div class="backup-progress-card">
      <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Processando</p>
      <h2 id="backup-progress-title" class="mt-3 font-orbitron text-2xl font-black text-white">Executando rotina de backup</h2>
      <p id="backup-progress-message" class="mt-3 text-sm leading-7 text-slate-300">Aguardando o proximo passo da rotina.</p>
      <div class="mt-6 backup-progress-bar">
        <div id="backup-progress-fill" class="backup-progress-fill"></div>
      </div>
      <div class="mt-4 flex items-center justify-between text-xs uppercase tracking-[0.2em] text-slate-400">
        <span id="backup-progress-stage">Preparando</span>
        <span id="backup-progress-percent">0%</span>
      </div>
      <p id="backup-progress-meta" class="mt-4 text-xs text-slate-500">A tela acompanha a etapa real gravada pela rotina, sem inventar progresso por tempo.</p>
    </div>
  </div>

  <div class="<?= $adminEmbed ? 'space-y-6' : 'mx-auto max-w-7xl space-y-6' ?>">
    <?php if (!$embedMode): ?>
      <?php \App\Support\View::component('site/local-tools-nav', ['active' => 'backup']); ?>
    <?php endif; ?>

    <?php if (!$embedMode): ?>
      <div class="flex flex-col gap-3 rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6 shadow-[0_0_40px_rgba(6,182,212,0.08)]">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <p class="font-orbitron text-xs uppercase tracking-[0.35em] text-cyan-300/70">Rotina Local</p>
            <h1 class="mt-2 font-orbitron text-3xl font-black tracking-tight text-white">Backup de Ambiente</h1>
            <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-300">Esta area concentra backup e restore de ambiente para local, stage e producao. As subabas separam resumo, execucao, restore e historico para manter a entrada mais leve.</p>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($flash !== null): ?>
      <div class="rounded-2xl border px-4 py-3 text-sm <?= htmlspecialchars($flashClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <?= htmlspecialchars((string) ($flash['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <div class="rounded-[1.25rem] border border-slate-800 bg-slate-950/70 p-2">
      <?php $sectionCount = max(1, count($backupSections)); ?>
      <div class="grid gap-2 md:grid-cols-2 <?= $sectionCount >= 5 ? 'xl:grid-cols-5' : 'xl:grid-cols-' . $sectionCount ?>">
        <?php foreach ($backupSections as $key => $section): ?>
          <?php $isActive = $key === $backupSection; ?>
          <a
            href="<?= htmlspecialchars($sectionUrl((string) $key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            data-backup-link="true"
            class="flex min-h-11 items-center rounded-xl border px-4 py-3 text-left transition <?= $isActive ? 'border-cyan-400/45 bg-cyan-500/10 text-cyan-100 shadow-[0_0_22px_rgba(34,211,238,0.12)]' : 'border-slate-800 bg-slate-900/70 text-slate-300 hover:border-cyan-500/35 hover:bg-cyan-500/10 hover:text-cyan-100' ?>"
            aria-current="<?= $isActive ? 'page' : 'false' ?>"
          >
            <div class="font-orbitron text-xs font-black uppercase tracking-[0.14em]"><?= htmlspecialchars((string) ($section['label'] ?? $key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php if (!$embedMode): ?>
              <div class="mt-1 text-xs text-slate-400"><?= htmlspecialchars((string) ($section['description'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div data-backup-inline-loading class="backup-inline-loading rounded-2xl border border-cyan-500/20 bg-cyan-500/10 px-4 py-3 text-sm text-cyan-100" hidden>
      Carregando secao de backup...
    </div>

    <div data-backup-content>
      <?= \App\Support\View::fragment('site/partials/backup-tools-content', get_defined_vars()) ?>
    </div>
  </div>

  <script>
    (() => {
      const adminEmbed = <?= $adminEmbed ? 'true' : 'false' ?>;
      const overlay = document.getElementById('backup-progress-overlay');
      const progressTitle = document.getElementById('backup-progress-title');
      const progressMessage = document.getElementById('backup-progress-message');
      const progressStage = document.getElementById('backup-progress-stage');
      const progressPercent = document.getElementById('backup-progress-percent');
      const progressFill = document.getElementById('backup-progress-fill');
      const progressMeta = document.getElementById('backup-progress-meta');
      const content = document.querySelector('[data-backup-content]');
      const inlineLoading = document.querySelector('[data-backup-inline-loading]');
      const links = Array.from(document.querySelectorAll('[data-backup-link="true"]'));
      if (!overlay || !progressTitle || !progressMessage || !progressStage || !progressPercent || !progressFill || !progressMeta || !content || !inlineLoading || links.length === 0) {
        return;
      }

      let pollTimer = null;
      let currentCloudController = null;

      const stopProgressPolling = () => {
        if (pollTimer !== null) {
          window.clearInterval(pollTimer);
          pollTimer = null;
        }
      };

      const setModalState = ({ title, message, stage, percent }) => {
        const nextPercent = Math.max(0, Math.min(100, Number(percent || 8)));
        progressTitle.textContent = title || 'Executando rotina de backup';
        progressMessage.textContent = message || 'Aguardando o proximo passo da rotina.';
        progressStage.textContent = stage || 'Preparando';
        progressPercent.textContent = `${nextPercent}%`;
        progressFill.style.width = Math.max(6, nextPercent) + '%';
      };

      const setModalLoading = (visible, form = null) => {
        overlay.classList.toggle('is-visible', visible);
        overlay.setAttribute('aria-hidden', visible ? 'false' : 'true');

        if (!visible) {
          stopProgressPolling();
          setModalState({
            title: 'Executando rotina de backup',
            message: 'Aguardando o proximo passo da rotina.',
            stage: 'Preparando',
            percent: 8,
          });
          progressMeta.textContent = 'A tela acompanha a etapa real gravada pela rotina, sem inventar progresso por tempo.';
          return;
        }

        setModalState({
          title: form?.dataset.progressTitle || 'Executando rotina de backup',
          message: form?.dataset.progressMessage || 'Preparando a rotina solicitada.',
          stage: form?.dataset.progressStage || 'Preparando',
          percent: 12,
        });
        progressMeta.textContent = 'Aguardando atualizacao da rotina...';
      };

      const setInlineLoading = (visible) => {
        inlineLoading.hidden = !visible;
      };

      const activateLink = (url) => {
        const sectionFromUrl = (value) => {
          const current = new URL(value, window.location.origin);
          const byQuery = current.searchParams.get('backup_secao');
          if (byQuery) return byQuery;
          const marker = '/backup-sistemico/';
          if (current.pathname.includes(marker)) {
            return decodeURIComponent(current.pathname.slice(current.pathname.indexOf(marker) + marker.length).split('/')[0] || 'resumo');
          }
          return 'resumo';
        };
        const activeSection = sectionFromUrl(url);
        links.forEach((link) => {
          const isActive = sectionFromUrl(link.href) === activeSection;
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
        next.searchParams.set('backup_fragment', '1');
        return next.toString();
      };

      const progressUrl = (progressId) => {
        const next = new URL('<?= htmlspecialchars(url('/local/backup'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>', window.location.origin);
        next.searchParams.set('backup_progress', '1');
        next.searchParams.set('id', progressId);
        return next.toString();
      };

      const plainResponseMessage = (text, fallback) => {
        if (!text) {
          return fallback;
        }

        const normalized = text
          .replace(/<script[\s\S]*?<\/script>/gi, ' ')
          .replace(/<style[\s\S]*?<\/style>/gi, ' ')
          .replace(/<[^>]+>/g, ' ')
          .replace(/\s+/g, ' ')
          .trim();

        return normalized ? normalized.slice(0, 220) : fallback;
      };

      const readActionResponse = async (response, fallback) => {
        const text = await response.text();
        let payload = null;

        if (text) {
          try {
            payload = JSON.parse(text);
          } catch (error) {
            payload = null;
          }
        }

        if (!response.ok || !payload) {
          throw new Error(payload?.message || plainResponseMessage(text, `${fallback} HTTP ${response.status}.`));
        }

        return payload;
      };

      const pollProgress = (progressId) => {
        stopProgressPolling();
        pollTimer = window.setInterval(async () => {
          try {
            const response = await fetch(progressUrl(progressId), {
              headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) {
              return;
            }

            const payload = await response.json();
            setModalState({
              title: payload.title || 'Enviando backup para Dropbox',
              message: payload.message || 'Processando rotina na nuvem.',
              stage: payload.stage || payload.status || 'Processando',
              percent: payload.percent || 18,
            });
            progressMeta.textContent = payload.updated_at
              ? `Ultima atualizacao: ${payload.updated_at}`
              : 'Aguardando nova etapa da rotina.';
          } catch (error) {
            // Mantem a ultima etapa visivel ate o request principal terminar.
          }
        }, 900);
      };

      const bindActionForms = (root = document) => {
        const forms = Array.from(root.querySelectorAll('.backup-action-form'));

        forms.forEach((form) => {
          if (form.dataset.backupBound === 'true') {
            return;
          }

          form.dataset.backupBound = 'true';
          form.addEventListener('submit', () => {
            if (form.dataset.backupCloudAsync === 'true' || form.dataset.backupAsync === 'true') {
              return;
            }
            setModalLoading(true, form);
          });
        });
      };

      const handleBackupAsyncSubmit = (form) => {
        if (form.dataset.backupAsyncBound === 'true') {
          return;
        }

        form.dataset.backupAsyncBound = 'true';
        form.addEventListener('submit', async (event) => {
          event.preventDefault();

          const progressId = `backup-local-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
          const formData = new FormData(form);
          formData.set('response', 'json');
          formData.set('progress_id', progressId);

          setModalLoading(true, form);
          pollProgress(progressId);

          try {
            const response = await fetch(form.getAttribute('action') || window.location.href, {
              method: 'POST',
              body: formData,
              headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            const payload = await readActionResponse(response, 'Nao foi possivel concluir a rotina de backup.');
            stopProgressPolling();

            setModalState({
              title: payload.ok ? 'Rotina concluida' : 'Falha na rotina',
              message: payload.message || 'Rotina concluida.',
              stage: payload.ok ? 'Atualizando painel' : 'Falha na rotina',
              percent: 100,
            });
            progressMeta.textContent = 'Atualizando a tela com o resultado final da rotina.';

            await loadSection(new URL(payload.redirect_url || window.location.href, window.location.origin).toString(), false);
          } catch (error) {
            stopProgressPolling();
            setModalState({
              title: 'Falha na rotina',
              message: error?.message || 'Nao foi possivel concluir a rotina de backup.',
              stage: 'Erro',
              percent: 100,
            });
            progressMeta.textContent = 'A rotina retornou erro e a tela sera recarregada.';
            await loadSection(window.location.href, false);
          } finally {
            window.setTimeout(() => setModalLoading(false), 500);
          }
        });
      };

      const handleCloudAsyncSubmit = (form) => {
        if (form.dataset.backupCloudBound === 'true') {
          return;
        }

        form.dataset.backupCloudBound = 'true';
        form.addEventListener('submit', async (event) => {
          event.preventDefault();

          if (currentCloudController) {
            currentCloudController.abort();
          }

          const progressId = `backup-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
          const formData = new FormData(form);
          formData.set('response', 'json');
          formData.set('progress_id', progressId);

          currentCloudController = new AbortController();
          setModalLoading(true, form);
          pollProgress(progressId);

          try {
            const response = await fetch(form.getAttribute('action') || window.location.href, {
              method: 'POST',
              body: formData,
              headers: { 'X-Requested-With': 'XMLHttpRequest' },
              signal: currentCloudController.signal,
            });

            const payload = await readActionResponse(response, 'Nao foi possivel concluir o envio para Dropbox.');
            stopProgressPolling();

            setModalState({
              title: payload.ok ? 'Envio concluido' : 'Falha no envio',
              message: payload.message || 'Rotina concluida.',
              stage: payload.ok ? 'Atualizando painel' : 'Falha no envio',
              percent: 100,
            });

            await loadSection(new URL(payload.redirect_url || window.location.href, window.location.origin).toString(), false);
          } catch (error) {
            stopProgressPolling();
            setModalState({
              title: 'Falha no envio',
              message: error?.message || 'Nao foi possivel concluir o envio para Dropbox.',
              stage: 'Erro',
              percent: 100,
            });
            await loadSection(window.location.href, false);
          } finally {
            window.setTimeout(() => setModalLoading(false), 500);
            currentCloudController = null;
          }
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
          Array.from(content.querySelectorAll('form[data-backup-async="true"]')).forEach(handleBackupAsyncSubmit);
          Array.from(content.querySelectorAll('form[data-backup-cloud-async="true"]')).forEach(handleCloudAsyncSubmit);
          activateLink(url);

          if (push) {
            window.history.pushState({ backupTools: true }, '', url);
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
          if (!url.pathname.includes('/local/backup')) {
            return;
          }

          if (url.searchParams.get('backup_secao')) {
            loadSection(window.location.href, false);
          }
        });
      }

      bindActionForms(document);
      Array.from(document.querySelectorAll('form[data-backup-async="true"]')).forEach(handleBackupAsyncSubmit);
      Array.from(document.querySelectorAll('form[data-backup-cloud-async="true"]')).forEach(handleCloudAsyncSubmit);
    })();
  </script>
</section>
