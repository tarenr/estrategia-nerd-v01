<?php
declare(strict_types=1);
?>
<script>
(function () {
  function byId(id) {
    return document.getElementById(id);
  }

  function editor() {
    return byId('editor-visual');
  }

  function hiddenField() {
    return byId('conteudoHidden');
  }

  function htmlField() {
    return byId('editor-html');
  }

  function postForm() {
    return byId('postForm');
  }

  function syncHiddenFields() {
    var root = editor();
    var hidden = hiddenField();
    var html = htmlField();
    if (!root) return;
    if (hidden) hidden.value = root.innerHTML;
    if (html) html.value = root.innerHTML;
    var wordCount = byId('wordCount');
    if (wordCount) {
      var text = (root.innerText || '').trim();
      var words = text ? text.split(/\s+/).filter(Boolean).length : 0;
      wordCount.textContent = words + ' palavra' + (words !== 1 ? 's' : '');
    }
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function getStoredRange() {
    return window.__postEditorSelection && window.__postEditorSelection.range
      ? window.__postEditorSelection.range
      : null;
  }

  function currentRange() {
    var root = editor();
    if (!root) return null;
    var selection = window.getSelection ? window.getSelection() : null;
    if (!selection || selection.rangeCount === 0) return getStoredRange();
    var range = selection.getRangeAt(0);
    if (!root.contains(range.commonAncestorContainer)) return getStoredRange();
    return range.cloneRange();
  }

  function restoreRange(range) {
    var root = editor();
    if (!root) return;
    root.focus();
    var selection = window.getSelection ? window.getSelection() : null;
    if (!selection) return;
    selection.removeAllRanges();
    if (range) {
      selection.addRange(range);
      return;
    }
    var fallback = document.createRange();
    fallback.selectNodeContents(root);
    fallback.collapse(false);
    selection.addRange(fallback);
  }

  function saveRange(range) {
    if (!window.__postEditorSelection) {
      window.__postEditorSelection = { range: null };
    }
    window.__postEditorSelection.range = range ? range.cloneRange() : currentRange();
  }

  function moveCaretToMarker() {
    var root = editor();
    if (!root) return;
    var marker = root.querySelector('[data-editor-caret="1"]');
    if (!marker) return;

    marker.removeAttribute('data-editor-caret');
    marker.style.textAlign = 'left';

    if (!String(marker.innerHTML || '').trim()) {
      marker.innerHTML = '<br>';
    }

    var selection = window.getSelection ? window.getSelection() : null;
    if (!selection) return;

    var range = document.createRange();
    range.selectNodeContents(marker);
    range.collapse(true);
    selection.removeAllRanges();
    selection.addRange(range);
  }

  function insertAtRange(range, html) {
    var root = editor();
    if (!root) return;
    restoreRange(range);
    try {
      document.execCommand('insertHTML', false, html);
    } catch (error) {
      root.innerHTML += html;
    }
    moveCaretToMarker();
    syncHiddenFields();
    saveRange(currentRange());
  }

  function appBasePath() {
    var path = String(window.location.pathname || '');
    var adminIndex = path.indexOf('/admin/');
    if (adminIndex !== -1) return path.slice(0, adminIndex);
    if (path.endsWith('/admin')) return path.slice(0, -6);
    return '';
  }

  function ensureUi() {
    var modal = byId('postEditorSystemModal');
    var toastRoot = byId('postEditorSystemToastRoot');

    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'postEditorSystemModal';
      modal.style.cssText = 'position:fixed;inset:0;z-index:10050;display:none;align-items:center;justify-content:center;padding:24px;';
      modal.innerHTML = '' +
        '<div data-ui-overlay style="position:absolute;inset:0;background:rgba(15,23,42,.62);backdrop-filter:blur(6px);"></div>' +
        '<div style="position:relative;width:min(100%,720px);border-radius:24px;border:1px solid rgba(103,232,249,.24);background:linear-gradient(180deg, rgba(21,30,51,.98), rgba(12,18,33,.98));box-shadow:0 30px 90px rgba(0,0,0,.42);color:#e2e8f0;overflow:hidden;">' +
        '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:22px 24px;border-bottom:1px solid rgba(71,85,105,.42);background:rgba(15,23,42,.44);">' +
        '<div><div id="postEditorSystemModalTitle" style="font-family:Orbitron,sans-serif;font-size:18px;font-weight:900;color:#f8fafc;">Editor</div><div id="postEditorSystemModalSubtitle" style="margin-top:6px;font-size:12px;color:#cbd5e1;"></div></div>' +
        '<button type="button" id="postEditorSystemCloseTop" class="admin-btn admin-btn-secondary" style="padding:8px 12px;">Fechar</button>' +
        '</div>' +
        '<div style="padding:24px;display:grid;gap:16px;background:rgba(15,23,42,.24);">' +
        '<div id="postEditorSystemModalMessage" style="font-size:14px;line-height:1.7;color:#dbe4f0;"></div>' +
        '<form id="postEditorSystemModalForm" style="display:grid;gap:14px;"></form>' +
        '</div>' +
        '<div style="display:flex;justify-content:flex-end;gap:12px;padding:20px 24px;border-top:1px solid rgba(71,85,105,.42);background:rgba(9,14,27,.38);">' +
        '<button type="button" id="postEditorSystemCancel" class="admin-btn admin-btn-secondary">Cancelar</button>' +
        '<button type="button" id="postEditorSystemSubmit" class="admin-btn admin-btn-primary">Confirmar</button>' +
        '</div>' +
        '</div>';
      document.body.appendChild(modal);
    }

    if (!toastRoot) {
      toastRoot = document.createElement('div');
      toastRoot.id = 'postEditorSystemToastRoot';
      toastRoot.style.cssText = 'position:fixed;top:16px;right:16px;z-index:10051;display:flex;flex-direction:column;gap:12px;max-width:360px;';
      document.body.appendChild(toastRoot);
    }

    return { modal: modal, toastRoot: toastRoot };
  }

  function toast(message, type) {
    var ui = ensureUi();
    var item = document.createElement('div');
    var isError = type === 'error';
    item.style.cssText = 'border-radius:18px;border:1px solid ' + (isError ? 'rgba(251,113,133,.35)' : 'rgba(34,211,238,.24)') + ';background:' + (isError ? 'rgba(127,29,29,.92)' : 'rgba(2,6,23,.94)') + ';color:#e2e8f0;padding:14px 16px;font-size:13px;line-height:1.5;box-shadow:0 20px 45px rgba(0,0,0,.35);';
    item.textContent = message;
    ui.toastRoot.appendChild(item);
    window.setTimeout(function () { item.remove(); }, 2600);
  }

  function promptModal(options) {
    var ui = ensureUi();
    var modal = ui.modal;
    var title = byId('postEditorSystemModalTitle');
    var subtitle = byId('postEditorSystemModalSubtitle');
    var message = byId('postEditorSystemModalMessage');
    var form = byId('postEditorSystemModalForm');
    var submit = byId('postEditorSystemSubmit');
    var cancel = byId('postEditorSystemCancel');
    var closeTop = byId('postEditorSystemCloseTop');
    var overlay = modal.querySelector('[data-ui-overlay]');

    title.textContent = options.title || 'Editor';
    subtitle.textContent = options.subtitle || '';
    subtitle.style.display = options.subtitle ? 'block' : 'none';
    message.innerHTML = options.message || '';
    form.innerHTML = '';

    (options.fields || []).forEach(function (field) {
      var wrap = document.createElement('div');
      wrap.style.display = 'grid';
      wrap.style.gap = '8px';

      var label = document.createElement('label');
      label.textContent = field.label || field.name || 'Campo';
      label.htmlFor = 'post-editor-system-' + field.name;
      label.style.cssText = 'font-size:13px;font-weight:700;color:#e2e8f0;';
      wrap.appendChild(label);

      var input = field.type === 'textarea' ? document.createElement('textarea') : document.createElement('input');
      if (field.type !== 'textarea') input.type = field.type || 'text';
      if (field.type === 'textarea') input.rows = field.rows || 4;
      input.id = 'post-editor-system-' + field.name;
      input.name = field.name;
      input.value = field.value || '';
      input.placeholder = field.placeholder || '';
      input.required = !!field.required;
      input.className = 'nerd-input';
      input.style.cssText = 'width:100%;border-radius:14px;padding:12px 14px;background:rgba(248,250,252,.06);background-color:rgba(248,250,252,.06);color:#f8fafc;border:1px solid rgba(103,232,249,.24);box-shadow:inset 0 1px 0 rgba(255,255,255,.03);';
      wrap.appendChild(input);
      form.appendChild(wrap);
    });

    submit.textContent = options.submitLabel || 'Confirmar';
    cancel.style.display = options.hideCancel ? 'none' : 'inline-flex';
    closeTop.style.display = options.hideClose ? 'none' : 'inline-flex';
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    var first = form.querySelector('input, textarea');
    if (first) window.setTimeout(function () { first.focus(); }, 20);

    return new Promise(function (resolve) {
      function cleanup(result) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
        submit.removeEventListener('click', onSubmit);
        cancel.removeEventListener('click', onCancel);
        closeTop.removeEventListener('click', onCancel);
        overlay.removeEventListener('click', onCancel);
        resolve(result);
      }
      function onCancel() { cleanup(null); }
      function onSubmit() {
        var values = {};
        var invalid = false;
        form.querySelectorAll('input, textarea').forEach(function (input) {
          var value = String(input.value || '').trim();
          if (input.required && !value) {
            input.style.borderColor = 'rgba(251,113,133,.65)';
            invalid = true;
          } else {
            input.style.borderColor = 'rgba(34,211,238,.22)';
          }
          values[input.name] = value;
        });
        if (invalid) return;
        cleanup(values);
      }
      submit.addEventListener('click', onSubmit);
      cancel.addEventListener('click', onCancel);
      closeTop.addEventListener('click', onCancel);
      overlay.addEventListener('click', onCancel);
    });
  }

  function alertModal(message, title) {
    return promptModal({ title: title || 'Editor', message: '<p>' + String(message || '') + '</p>', submitLabel: 'OK', hideCancel: true, fields: [] });
  }

  function askImageData(initialUrl) {
    return promptModal({
      title: 'Inserir imagem',
      subtitle: 'Defina a URL e os dados da imagem.',
      submitLabel: 'Inserir imagem',
      fields: [
        { name: 'url', label: 'URL ou caminho', required: true, value: initialUrl || '', placeholder: '/uploads/posts/meu-post/imagem.webp' },
        { name: 'alt', label: 'Texto alternativo', placeholder: 'Descricao curta da imagem' },
        { name: 'legenda', label: 'Legenda', type: 'textarea', rows: 3, placeholder: 'Legenda opcional' }
      ]
    });
  }

  function askVideoData() {
    return promptModal({
      title: 'Inserir video',
      subtitle: 'Aceita YouTube, iframe completo ou URL de video proprio.',
      submitLabel: 'Inserir video',
      fields: [
        { name: 'valor', label: 'URL ou iframe', type: 'textarea', rows: 4, required: true, placeholder: 'https://www.youtube.com/watch?v=... ou <iframe ...></iframe>' },
        { name: 'legenda', label: 'Legenda', type: 'textarea', rows: 3, placeholder: 'Legenda opcional' }
      ]
    });
  }

  function askVideoMeta() {
    return promptModal({
      title: 'Legenda do video',
      subtitle: 'Adicione uma legenda para acompanhar o bloco de video no conteudo.',
      submitLabel: 'Inserir video',
      fields: [
        { name: 'legenda', label: 'Legenda', type: 'textarea', rows: 3, placeholder: 'Legenda opcional' }
      ]
    });
  }

  function promptVideoValue(label, initialValue) {
    return promptModal({
      title: 'Usar origem de video',
      subtitle: label || 'Informe a URL publica, link do YouTube ou iframe completo.',
      submitLabel: 'Usar origem',
      fields: [
        { name: 'valor', label: 'URL, YouTube ou iframe', type: 'textarea', rows: 4, required: true, value: initialValue || '', placeholder: 'https://www.youtube.com/watch?v=... ou <iframe ...></iframe>' }
      ]
    }).then(function (data) {
      if (!data) return null;
      return String(data.valor || '').trim() || null;
    });
  }

  function promptMediaPath(type, label, initialValue) {
    var placeholders = {
      image: '/uploads/posts/meu-post/images/minha-imagem.webp',
      audio: '/uploads/posts/meu-post/audio/minha-narracao.mp3',
      video: '/uploads/posts/meu-post/video/meu-video.mp4'
    };

    return promptModal({
      title: 'Usar URL de ' + ({ image: 'imagem', audio: 'audio', video: 'video' })[String(type || '').toLowerCase()],
      subtitle: label || 'Informe a URL publica ou o caminho relativo do arquivo.',
      submitLabel: 'Usar URL',
      fields: [
        { name: 'path', label: 'URL ou caminho', required: true, value: initialValue || '', placeholder: placeholders[String(type || '').toLowerCase()] || '/uploads/arquivo' }
      ]
    }).then(function (data) {
      if (!data) return null;
      var value = normalizeMediaPath(String(data.path || '').trim());
      return value || null;
    });
  }

  function mediaDisplayName(value) {
    var raw = String(value || '').trim();
    if (!raw) return '';
    var clean = raw.split('?')[0].split('#')[0];
    var parts = clean.split('/').filter(Boolean);
    return parts.length ? parts[parts.length - 1] : raw;
  }

  function buildSingleMediaSourceSummary(kindLabel, entry, emptyMessage) {
    if (!entry || !entry.path) {
      return '' +
        '<div style="display:grid;gap:6px;padding:14px;border-radius:16px;border:1px dashed rgba(148,163,184,.32);background:rgba(248,250,252,.03);">' +
        '  <div style="font-size:13px;font-weight:800;color:#f8fafc;">' + escapeHtml(kindLabel) + '</div>' +
        '  <div style="font-size:12px;color:#94a3b8;">' + escapeHtml(emptyMessage || 'Nenhuma midia selecionada ainda.') + '</div>' +
        '</div>';
    }

    var sourceLabel = entry.source === 'upload'
      ? 'Upload direto'
      : entry.source === 'url'
        ? 'URL manual'
        : 'Biblioteca';

    return '' +
      '<div style="display:grid;gap:6px;padding:14px;border-radius:16px;border:1px solid rgba(103,232,249,.16);background:rgba(248,250,252,.04);">' +
      '  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">' +
      '    <div style="font-size:13px;font-weight:800;color:#f8fafc;">' + escapeHtml(kindLabel) + '</div>' +
      '    <span style="font-size:11px;font-weight:700;color:#67e8f9;">' + escapeHtml(sourceLabel) + '</span>' +
      '  </div>' +
      '  <div style="font-size:12px;font-weight:700;color:#e2e8f0;word-break:break-word;">' + escapeHtml(entry.label || mediaDisplayName(entry.path) || 'Arquivo') + '</div>' +
      '  <div style="font-size:11px;color:#94a3b8;word-break:break-all;">' + escapeHtml(entry.path) + '</div>' +
      '</div>';
  }

  function buildAudioSourceSummary(kind, entry) {
    var kindLabel = kind === 'narracao' ? 'Narracao' : 'Ambiente';
    if (!entry || !entry.path) {
      return '' +
        '<div style="display:grid;gap:6px;padding:14px;border-radius:16px;border:1px dashed rgba(148,163,184,.32);background:rgba(248,250,252,.03);">' +
        '  <div style="font-size:13px;font-weight:800;color:#f8fafc;">' + kindLabel + '</div>' +
        '  <div style="font-size:12px;color:#94a3b8;">Nenhum audio selecionado ainda.</div>' +
        '</div>';
    }

    var sourceLabel = entry.source === 'upload'
      ? 'Upload direto'
      : entry.source === 'url'
        ? 'URL manual'
        : 'Biblioteca';

    return '' +
      '<div style="display:grid;gap:6px;padding:14px;border-radius:16px;border:1px solid rgba(103,232,249,.16);background:rgba(248,250,252,.04);">' +
      '  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">' +
      '    <div style="font-size:13px;font-weight:800;color:#f8fafc;">' + kindLabel + '</div>' +
      '    <span style="font-size:11px;font-weight:700;color:#67e8f9;">' + escapeHtml(sourceLabel) + '</span>' +
      '  </div>' +
      '  <div style="font-size:12px;font-weight:700;color:#e2e8f0;word-break:break-word;">' + escapeHtml(entry.label || mediaDisplayName(entry.path) || 'Arquivo') + '</div>' +
      '  <div style="font-size:11px;color:#94a3b8;word-break:break-all;">' + escapeHtml(entry.path) + '</div>' +
      '</div>';
  }

  function openAudioBlockBuilder(preferredAction, initialData, mode) {
    var ui = ensureUi();
    var modal = ui.modal;
    var title = byId('postEditorSystemModalTitle');
    var subtitle = byId('postEditorSystemModalSubtitle');
    var message = byId('postEditorSystemModalMessage');
    var form = byId('postEditorSystemModalForm');
    var submit = byId('postEditorSystemSubmit');
    var cancel = byId('postEditorSystemCancel');
    var closeTop = byId('postEditorSystemCloseTop');
    var overlay = modal.querySelector('[data-ui-overlay]');
    var initial = initialData || {};
    var state = {
      title: String(initial.title || ''),
      subtitle: String(initial.subtitle || ''),
      buttonText: String(initial.buttonText || 'Ouvir narracao'),
      narracao: { path: String(initial.narracao || ''), label: mediaDisplayName(initial.narracao || ''), source: initial.narracao ? 'url' : '' },
      ambiente: { path: String(initial.ambiente || ''), label: mediaDisplayName(initial.ambiente || ''), source: initial.ambiente ? 'url' : '' }
    };
    var isNestedDialogOpen = false;

    function showBuilder() {
      title.textContent = mode === 'edit' ? 'Editar bloco de audio' : 'Bloco de audio';
      subtitle.textContent = preferredAction === 'upload' ? 'Configure o bloco e envie narracao e ambiente sem sair do fluxo.' : (preferredAction === 'library' ? 'Configure o bloco e escolha os audios na biblioteca do post.' : (preferredAction === 'url' ? 'Configure o bloco e informe as URLs de narracao e ambiente.' : 'Configure o bloco e escolha narracao e ambiente sem sair do fluxo.')); 
      subtitle.style.display = 'block';
      message.innerHTML = '<div style="padding:12px 14px;border-radius:14px;border:1px solid rgba(103,232,249,.16);background:rgba(248,250,252,.04);font-size:12px;color:#cbd5e1;">Cada canal aceita <strong style="color:#f8fafc;">Enviar</strong>, <strong style="color:#f8fafc;">Biblioteca</strong> ou <strong style="color:#f8fafc;">URL</strong>. Voce pode usar apenas um deles ou os dois juntos.</div>';
      submit.textContent = mode === 'edit' ? 'Salvar alteracoes' : 'Inserir bloco';
      submit.style.display = 'inline-flex';
      submit.disabled = false;
      cancel.textContent = 'Cancelar';
      cancel.style.display = 'inline-flex';
      closeTop.style.display = 'inline-flex';
      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }

    function render() {
      showBuilder();
      form.innerHTML = '' +
        '<div style="display:grid;gap:12px;">' +
        '  <div style="display:grid;gap:8px;">' +
        '    <label for="audio-builder-title" style="font-size:13px;font-weight:700;color:#e2e8f0;">Titulo</label>' +
        '    <input id="audio-builder-title" type="text" value="' + escapeHtml(state.title) + '" placeholder="Relato dos Horadrim" class="nerd-input" style="width:100%;border-radius:14px;padding:12px 14px;background:rgba(248,250,252,.06);color:#f8fafc;border:1px solid rgba(103,232,249,.24);">' +
        '  </div>' +
        '  <div style="display:grid;gap:8px;">' +
        '    <label for="audio-builder-subtitle" style="font-size:13px;font-weight:700;color:#e2e8f0;">Subtitulo</label>' +
        '    <textarea id="audio-builder-subtitle" rows="3" placeholder="Ouca a introducao deste relato." class="nerd-input" style="width:100%;border-radius:14px;padding:12px 14px;background:rgba(248,250,252,.06);color:#f8fafc;border:1px solid rgba(103,232,249,.24);">' + escapeHtml(state.subtitle) + '</textarea>' +
        '  </div>' +
        '  <div style="display:grid;gap:8px;">' +
        '    <label for="audio-builder-button" style="font-size:13px;font-weight:700;color:#e2e8f0;">Texto do botao</label>' +
        '    <input id="audio-builder-button" type="text" value="' + escapeHtml(state.buttonText) + '" placeholder="Ouvir narracao" class="nerd-input" style="width:100%;border-radius:14px;padding:12px 14px;background:rgba(248,250,252,.06);color:#f8fafc;border:1px solid rgba(103,232,249,.24);">' +
        '  </div>' +
        '</div>' +
        '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">' +
        '  <div style="display:grid;gap:12px;">' +
        buildAudioSourceSummary('narracao', state.narracao) +
        '    <div style="display:flex;flex-wrap:wrap;gap:8px;">' +
        '      <button type="button" data-audio-source="narracao" data-audio-action="upload" class="admin-btn admin-btn-secondary">Enviar</button>' +
        '      <button type="button" data-audio-source="narracao" data-audio-action="library" class="admin-btn admin-btn-secondary">Biblioteca</button>' +
        '      <button type="button" data-audio-source="narracao" data-audio-action="url" class="admin-btn admin-btn-secondary">URL</button>' +
        '      <button type="button" data-audio-source="narracao" data-audio-action="clear" class="admin-btn admin-btn-secondary">Limpar</button>' +
        '    </div>' +
        '  </div>' +
        '  <div style="display:grid;gap:12px;">' +
        buildAudioSourceSummary('ambiente', state.ambiente) +
        '    <div style="display:flex;flex-wrap:wrap;gap:8px;">' +
        '      <button type="button" data-audio-source="ambiente" data-audio-action="upload" class="admin-btn admin-btn-secondary">Enviar</button>' +
        '      <button type="button" data-audio-source="ambiente" data-audio-action="library" class="admin-btn admin-btn-secondary">Biblioteca</button>' +
        '      <button type="button" data-audio-source="ambiente" data-audio-action="url" class="admin-btn admin-btn-secondary">URL</button>' +
        '      <button type="button" data-audio-source="ambiente" data-audio-action="clear" class="admin-btn admin-btn-secondary">Limpar</button>' +
        '    </div>' +
        '  </div>' +
        '</div>';

      var titleInput = byId('audio-builder-title');
      var subtitleInput = byId('audio-builder-subtitle');
      var buttonInput = byId('audio-builder-button');
      [titleInput, subtitleInput, buttonInput].forEach(function (input) {
        if (!input) return;
        input.addEventListener('input', function () {
          state.title = titleInput ? String(titleInput.value || '') : '';
          state.subtitle = subtitleInput ? String(subtitleInput.value || '') : '';
          state.buttonText = buttonInput ? String(buttonInput.value || '') : '';
        });
      });

      form.querySelectorAll('[data-audio-action]').forEach(function (button) {
        button.addEventListener('click', function () {
          var kind = String(button.getAttribute('data-audio-source') || 'narracao');
          var action = String(button.getAttribute('data-audio-action') || '');
          var current = state[kind] || { path: '', label: '', source: '' };
          state.title = titleInput ? String(titleInput.value || '') : state.title;
          state.subtitle = subtitleInput ? String(subtitleInput.value || '') : state.subtitle;
          state.buttonText = buttonInput ? String(buttonInput.value || '') : state.buttonText;

          if (action === 'clear') {
            state[kind] = { path: '', label: '', source: '' };
            render();
            return;
          }

          if (action === 'upload') {
            isNestedDialogOpen = true;
            uploadMediaToLibrary('audio', { context: 'post', audioRole: kind }).then(function (item) {
              if (!item) {
                showBuilder();
                return;
              }
              state[kind] = {
                path: String(item.relative_path || ''),
                label: String(item.name || mediaDisplayName(item.relative_path || item.public_url || '') || 'Audio'),
                source: 'upload'
              };
              render();
            }).finally(function () {
              isNestedDialogOpen = false;
            });
            return;
          }

          if (action === 'library') {
            isNestedDialogOpen = true;
            pickMediaItemFromLibrary('audio', {
              title: kind === 'narracao' ? 'Selecionar audio de narracao' : 'Selecionar audio de ambiente',
              subtitle: 'Escolha um audio da biblioteca ou envie um novo pela modal.',
              context: 'post',
              audioRole: kind
            }).then(function (item) {
              if (!item) {
                showBuilder();
                return;
              }
              state[kind] = {
                path: String(item.path || ''),
                label: mediaDisplayName(item.path || item.url || '') || 'Audio',
                source: 'library'
              };
              render();
            }).finally(function () {
              isNestedDialogOpen = false;
            });
            return;
          }

          if (action === 'url') {
            isNestedDialogOpen = true;
            promptMediaPath('audio', 'Informe a URL ou caminho do audio ' + (kind === 'narracao' ? 'de narracao.' : 'de ambiente.'), current.path || '').then(function (path) {
              if (!path) {
                showBuilder();
                return;
              }
              state[kind] = {
                path: String(path || ''),
                label: mediaDisplayName(path || '') || 'Audio',
                source: 'url'
              };
              render();
            }).finally(function () {
              isNestedDialogOpen = false;
            });
          }
        });
      });
    }

    return new Promise(function (resolve) {
      function cleanup(result) {
        isNestedDialogOpen = false;
        modal.style.display = 'none';
        document.body.style.overflow = '';
        submit.removeEventListener('click', onSubmit);
        cancel.removeEventListener('click', onCancel);
        closeTop.removeEventListener('click', onCancel);
        overlay.removeEventListener('click', onCancel);
        resolve(result || null);
      }

      function onCancel() {
        if (isNestedDialogOpen) return;
        cleanup(null);
      }

      function onSubmit() {
        if (isNestedDialogOpen) return;
        var titleInput = byId('audio-builder-title');
        var subtitleInput = byId('audio-builder-subtitle');
        var buttonInput = byId('audio-builder-button');
        state.title = titleInput ? String(titleInput.value || '').trim() : '';
        state.subtitle = subtitleInput ? String(subtitleInput.value || '').trim() : '';
        state.buttonText = buttonInput ? String(buttonInput.value || '').trim() : '';

        if (!state.narracao.path && !state.ambiente.path) {
          alertModal('Selecione ao menos um audio, seja narracao ou ambiente.').then(function () { render(); });
          return;
        }

        cleanup({
          title: state.title,
          subtitle: state.subtitle,
          buttonText: state.buttonText || 'Ouvir narracao',
          narracao: state.narracao.path || '',
          ambiente: state.ambiente.path || ''
        });
      }

      render();
      submit.addEventListener('click', onSubmit);
      cancel.addEventListener('click', onCancel);
      closeTop.addEventListener('click', onCancel);
      overlay.addEventListener('click', onCancel);
    });
  }

  function openImageBlockBuilder(preferredAction, initialData, mode) {
    var ui = ensureUi();
    var modal = ui.modal;
    var title = byId('postEditorSystemModalTitle');
    var subtitle = byId('postEditorSystemModalSubtitle');
    var message = byId('postEditorSystemModalMessage');
    var form = byId('postEditorSystemModalForm');
    var submit = byId('postEditorSystemSubmit');
    var cancel = byId('postEditorSystemCancel');
    var closeTop = byId('postEditorSystemCloseTop');
    var overlay = modal.querySelector('[data-ui-overlay]');
    var initial = initialData || {};
    var state = {
      alt: String(initial.alt || ''),
      legenda: String(initial.legenda || ''),
      media: {
        path: String(initial.path || ''),
        label: mediaDisplayName(initial.path || ''),
        source: initial.path ? 'url' : ''
      }
    };
    var isNestedDialogOpen = false;
    var isSubmitting = false;

    function showBuilder() {
      title.textContent = mode === 'edit' ? 'Editar bloco de imagem' : 'Bloco de imagem';
      subtitle.textContent = preferredAction === 'upload'
        ? 'Configure a imagem e envie o arquivo sem sair do fluxo.'
        : (preferredAction === 'library'
          ? 'Configure a imagem e escolha um arquivo ja cadastrado.'
          : (preferredAction === 'url'
            ? 'Configure a imagem e informe uma URL ou caminho valido.'
            : 'Configure a imagem e escolha entre upload, biblioteca ou URL sem sair do fluxo.'));
      subtitle.style.display = 'block';
      message.innerHTML = '<div style="padding:12px 14px;border-radius:14px;border:1px solid rgba(103,232,249,.16);background:rgba(248,250,252,.04);font-size:12px;color:#cbd5e1;">A imagem aceita <strong style="color:#f8fafc;">Enviar</strong>, <strong style="color:#f8fafc;">Biblioteca</strong> ou <strong style="color:#f8fafc;">URL</strong>. Todos os caminhos geram o mesmo bloco final no conteudo.</div>';
      submit.textContent = isSubmitting ? (mode === 'edit' ? 'Salvando...' : 'Inserindo...') : (mode === 'edit' ? 'Salvar alteracoes' : 'Inserir bloco');
      submit.style.display = 'inline-flex';
      submit.disabled = isSubmitting;
      cancel.textContent = 'Cancelar';
      cancel.style.display = 'inline-flex';
      closeTop.style.display = 'inline-flex';
      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }

    function render() {
      showBuilder();
      form.innerHTML = '' +
        '<div style="display:grid;gap:12px;">' +
        '  <div style="display:grid;gap:8px;">' +
        '    <label for="image-builder-alt" style="font-size:13px;font-weight:700;color:#e2e8f0;">Texto alternativo</label>' +
        '    <input id="image-builder-alt" type="text" value="' + escapeHtml(state.alt) + '" placeholder="Descricao curta da imagem" class="nerd-input" style="width:100%;border-radius:14px;padding:12px 14px;background:rgba(248,250,252,.06);color:#f8fafc;border:1px solid rgba(103,232,249,.24);">' +
        '  </div>' +
        '  <div style="display:grid;gap:8px;">' +
        '    <label for="image-builder-legenda" style="font-size:13px;font-weight:700;color:#e2e8f0;">Legenda</label>' +
        '    <textarea id="image-builder-legenda" rows="3" placeholder="Legenda opcional" class="nerd-input" style="width:100%;border-radius:14px;padding:12px 14px;background:rgba(248,250,252,.06);color:#f8fafc;border:1px solid rgba(103,232,249,.24);">' + escapeHtml(state.legenda) + '</textarea>' +
        '  </div>' +
        '</div>' +
        '<div style="display:grid;gap:12px;">' +
        buildSingleMediaSourceSummary('Imagem', state.media, 'Nenhuma imagem selecionada ainda.') +
        '  <div style="display:flex;flex-wrap:wrap;gap:8px;">' +
        '    <button type="button" data-image-action="upload" class="admin-btn admin-btn-secondary">Enviar</button>' +
        '    <button type="button" data-image-action="library" class="admin-btn admin-btn-secondary">Biblioteca</button>' +
        '    <button type="button" data-image-action="url" class="admin-btn admin-btn-secondary">URL</button>' +
        '    <button type="button" data-image-action="clear" class="admin-btn admin-btn-secondary">Limpar</button>' +
        '  </div>' +
        '</div>';

      var altInput = byId('image-builder-alt');
      var legendaInput = byId('image-builder-legenda');
      [altInput, legendaInput].forEach(function (input) {
        if (!input) return;
        input.addEventListener('input', function () {
          state.alt = altInput ? String(altInput.value || '') : '';
          state.legenda = legendaInput ? String(legendaInput.value || '') : '';
        });
      });

      form.querySelectorAll('[data-image-action]').forEach(function (button) {
        button.addEventListener('click', function () {
          var action = String(button.getAttribute('data-image-action') || '');
          var current = state.media || { path: '', label: '', source: '' };
          state.alt = altInput ? String(altInput.value || '') : state.alt;
          state.legenda = legendaInput ? String(legendaInput.value || '') : state.legenda;

          if (action === 'clear') {
            state.media = { path: '', label: '', source: '' };
            render();
            return;
          }

          if (action === 'upload') {
            isNestedDialogOpen = true;
            pickLocalMediaFile('image')
              .then(function (file) {
                if (!file) return null;
                return uploadInlineImageAsset(file);
              })
              .then(function (payload) {
                if (!payload) {
                  render();
                  return;
                }
                if (payload.ok !== true) {
                  return alertModal(payload.error || 'Falha no upload da imagem.').then(function () {
                    render();
                  });
                }
                state.media = {
                  path: String(payload.url || payload.path || ''),
                  label: mediaDisplayName(payload.url || payload.path || '') || 'Imagem',
                  source: 'upload'
                };
                render();
              })
              .finally(function () {
                isNestedDialogOpen = false;
              });
            return;
          }

          if (action === 'library') {
            isNestedDialogOpen = true;
            pickMediaItemFromLibrary('image', {
              title: 'Selecionar imagem da biblioteca',
              subtitle: 'Busque por nome e escolha a imagem que deseja usar no post.'
            }).then(function (item) {
              if (!item) {
                render();
                return;
              }
              state.media = {
                path: String(item.path || item.url || ''),
                label: mediaDisplayName(item.path || item.url || '') || 'Imagem',
                source: 'library'
              };
              render();
            }).finally(function () {
              isNestedDialogOpen = false;
            });
            return;
          }

          if (action === 'url') {
            isNestedDialogOpen = true;
            promptMediaPath('image', 'Informe a URL ou caminho da imagem que deseja usar.', current.path || '').then(function (path) {
              if (!path) {
                render();
                return;
              }
              state.media = {
                path: String(path || ''),
                label: mediaDisplayName(path || '') || 'Imagem',
                source: 'url'
              };
              render();
            }).finally(function () {
              isNestedDialogOpen = false;
            });
          }
        });
      });
    }

    return new Promise(function (resolve) {
      function cleanup(result) {
        isNestedDialogOpen = false;
        isSubmitting = false;
        modal.style.display = 'none';
        document.body.style.overflow = '';
        submit.removeEventListener('click', onSubmit);
        cancel.removeEventListener('click', onCancel);
        closeTop.removeEventListener('click', onCancel);
        overlay.removeEventListener('click', onCancel);
        resolve(result || null);
      }

      function onCancel() {
        if (isNestedDialogOpen || isSubmitting) return;
        cleanup(null);
      }

      function onSubmit() {
        if (isNestedDialogOpen || isSubmitting) return;

        var altInput = byId('image-builder-alt');
        var legendaInput = byId('image-builder-legenda');
        state.alt = altInput ? String(altInput.value || '').trim() : '';
        state.legenda = legendaInput ? String(legendaInput.value || '').trim() : '';

        if (!state.media.path) {
          isNestedDialogOpen = true;
          alertModal('Selecione ao menos uma imagem para inserir no conteudo.').then(function () {
            isNestedDialogOpen = false;
            render();
          });
          return;
        }

        isSubmitting = true;
        showBuilder();

        var finalizePath = Promise.resolve(String(state.media.path || ''));
        if (state.media.source === 'library') {
          finalizePath = ensurePostIdentity()
            .then(function (identity) {
              if (!identity) return null;
              return duplicateLibraryImageToPost(state.media.path, identity);
            })
            .then(function (payload) {
              if (!payload) return null;
              if (payload.ok !== true) {
                throw new Error(payload.error || 'Falha ao preparar a imagem da biblioteca.');
              }
              if (payload.item) refreshMediaLibraryCache(payload.item);
              return String(payload.url || payload.path || '').trim();
            });
        }

        finalizePath.then(function (finalPath) {
          if (!finalPath) {
            isSubmitting = false;
            render();
            return;
          }
          cleanup({
            path: finalPath,
            alt: state.alt,
            legenda: state.legenda
          });
        }).catch(function (error) {
          isSubmitting = false;
          isNestedDialogOpen = true;
          alertModal(error && error.message ? error.message : 'Falha ao preparar a imagem para o conteudo.').then(function () {
            isNestedDialogOpen = false;
            render();
          });
        });
      }

      render();
      submit.addEventListener('click', onSubmit);
      cancel.addEventListener('click', onCancel);
      closeTop.addEventListener('click', onCancel);
      overlay.addEventListener('click', onCancel);
    });
  }

  function openVideoBlockBuilder(preferredAction, initialData, mode) {
    var ui = ensureUi();
    var modal = ui.modal;
    var title = byId('postEditorSystemModalTitle');
    var subtitle = byId('postEditorSystemModalSubtitle');
    var message = byId('postEditorSystemModalMessage');
    var form = byId('postEditorSystemModalForm');
    var submit = byId('postEditorSystemSubmit');
    var cancel = byId('postEditorSystemCancel');
    var closeTop = byId('postEditorSystemCloseTop');
    var overlay = modal.querySelector('[data-ui-overlay]');
    var initial = initialData || {};
    var state = {
      legenda: String(initial.legenda || ''),
      media: {
        path: String(initial.path || ''),
        label: mediaDisplayName(initial.path || ''),
        source: initial.path ? 'url' : ''
      }
    };
    var isNestedDialogOpen = false;

    function showBuilder() {
      title.textContent = mode === 'edit' ? 'Editar bloco de video' : 'Bloco de video';
      subtitle.textContent = preferredAction === 'upload'
        ? 'Configure o video e envie o arquivo sem sair do fluxo.'
        : (preferredAction === 'library'
          ? 'Configure o video e escolha um arquivo ja cadastrado.'
          : (preferredAction === 'url'
            ? 'Configure o video e informe a origem por URL, YouTube ou iframe.'
            : 'Configure o video e escolha entre upload, biblioteca ou URL sem sair do fluxo.'));
      subtitle.style.display = 'block';
      message.innerHTML = '<div style="padding:12px 14px;border-radius:14px;border:1px solid rgba(103,232,249,.16);background:rgba(248,250,252,.04);font-size:12px;color:#cbd5e1;">O video aceita <strong style="color:#f8fafc;">Enviar</strong>, <strong style="color:#f8fafc;">Biblioteca</strong> ou <strong style="color:#f8fafc;">URL</strong>. Todos os caminhos geram o mesmo bloco final no conteudo.</div>';
      submit.textContent = mode === 'edit' ? 'Salvar alteracoes' : 'Inserir bloco';
      submit.style.display = 'inline-flex';
      submit.disabled = false;
      cancel.textContent = 'Cancelar';
      cancel.style.display = 'inline-flex';
      closeTop.style.display = 'inline-flex';
      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }

    function render() {
      showBuilder();
      form.innerHTML = '' +
        '<div style="display:grid;gap:12px;">' +
        '  <div style="display:grid;gap:8px;">' +
        '    <label for="video-builder-legenda" style="font-size:13px;font-weight:700;color:#e2e8f0;">Legenda</label>' +
        '    <textarea id="video-builder-legenda" rows="3" placeholder="Legenda opcional" class="nerd-input" style="width:100%;border-radius:14px;padding:12px 14px;background:rgba(248,250,252,.06);color:#f8fafc;border:1px solid rgba(103,232,249,.24);">' + escapeHtml(state.legenda) + '</textarea>' +
        '  </div>' +
        '</div>' +
        '<div style="display:grid;gap:12px;">' +
        buildSingleMediaSourceSummary('Video', state.media, 'Nenhum video selecionado ainda.') +
        '  <div style="display:flex;flex-wrap:wrap;gap:8px;">' +
        '    <button type="button" data-video-action="upload" class="admin-btn admin-btn-secondary">Enviar</button>' +
        '    <button type="button" data-video-action="library" class="admin-btn admin-btn-secondary">Biblioteca</button>' +
        '    <button type="button" data-video-action="url" class="admin-btn admin-btn-secondary">URL</button>' +
        '    <button type="button" data-video-action="clear" class="admin-btn admin-btn-secondary">Limpar</button>' +
        '  </div>' +
        '</div>';

      var legendaInput = byId('video-builder-legenda');
      if (legendaInput) {
        legendaInput.addEventListener('input', function () {
          state.legenda = String(legendaInput.value || '');
        });
      }

      form.querySelectorAll('[data-video-action]').forEach(function (button) {
        button.addEventListener('click', function () {
          var action = String(button.getAttribute('data-video-action') || '');
          var current = state.media || { path: '', label: '', source: '' };
          state.legenda = legendaInput ? String(legendaInput.value || '') : state.legenda;

          if (action === 'clear') {
            state.media = { path: '', label: '', source: '' };
            render();
            return;
          }

          if (action === 'upload') {
            isNestedDialogOpen = true;
            uploadMediaToLibrary('video', { context: 'post' }).then(function (item) {
              if (!item) {
                render();
                return;
              }
              state.media = {
                path: String(item.relative_path || item.public_url || ''),
                label: String(item.name || mediaDisplayName(item.relative_path || item.public_url || '') || 'Video'),
                source: 'upload'
              };
              render();
            }).finally(function () {
              isNestedDialogOpen = false;
            });
            return;
          }

          if (action === 'library') {
            isNestedDialogOpen = true;
            pickMediaItemFromLibrary('video', {
              title: 'Selecionar video da biblioteca',
              subtitle: 'Busque por nome e escolha o video que deseja usar no post.'
            }).then(function (item) {
              if (!item) {
                render();
                return;
              }
              state.media = {
                path: String(item.path || item.url || ''),
                label: mediaDisplayName(item.path || item.url || '') || 'Video',
                source: 'library'
              };
              render();
            }).finally(function () {
              isNestedDialogOpen = false;
            });
            return;
          }

          if (action === 'url') {
            isNestedDialogOpen = true;
            promptVideoValue('Informe a URL publica, link do YouTube ou iframe completo do video.', current.path || '').then(function (value) {
              if (!value) {
                render();
                return;
              }
              state.media = {
                path: String(value || ''),
                label: mediaDisplayName(value || '') || 'Video',
                source: 'url'
              };
              render();
            }).finally(function () {
              isNestedDialogOpen = false;
            });
          }
        });
      });
    }

    return new Promise(function (resolve) {
      function cleanup(result) {
        isNestedDialogOpen = false;
        modal.style.display = 'none';
        document.body.style.overflow = '';
        submit.removeEventListener('click', onSubmit);
        cancel.removeEventListener('click', onCancel);
        closeTop.removeEventListener('click', onCancel);
        overlay.removeEventListener('click', onCancel);
        resolve(result || null);
      }

      function onCancel() {
        if (isNestedDialogOpen) return;
        cleanup(null);
      }

      function onSubmit() {
        if (isNestedDialogOpen) return;

        var legendaInput = byId('video-builder-legenda');
        state.legenda = legendaInput ? String(legendaInput.value || '').trim() : '';

        if (!state.media.path) {
          isNestedDialogOpen = true;
          alertModal('Selecione ao menos um video para inserir no conteudo.').then(function () {
            isNestedDialogOpen = false;
            render();
          });
          return;
        }

        cleanup({
          path: state.media.path,
          legenda: state.legenda
        });
      }

      render();
      submit.addEventListener('click', onSubmit);
      cancel.addEventListener('click', onCancel);
      closeTop.addEventListener('click', onCancel);
      overlay.addEventListener('click', onCancel);
    });
  }

  function askLinkData() {

    return promptModal({
      title: 'Inserir link',
      subtitle: 'Defina o endereco e, se quiser, um texto para o link.',
      submitLabel: 'Inserir link',
      fields: [
        { name: 'url', label: 'URL', required: true, placeholder: 'https://exemplo.com' },
        { name: 'texto', label: 'Texto do link', placeholder: 'Opcional se voce ja selecionou um texto' }
      ]
    });
  }

  function askImageMeta() {
    return promptModal({
      title: 'Dados da imagem',
      subtitle: 'Defina os dados que acompanham a imagem no conteudo.',
      submitLabel: 'Inserir no conteudo',
      fields: [
        { name: 'alt', label: 'Texto alternativo', placeholder: 'Descricao curta da imagem' },
        { name: 'legenda', label: 'Legenda', type: 'textarea', rows: 3, placeholder: 'Legenda opcional' }
      ]
    });
  }


  function readMediaLibraryData() {
    if (window.__postEditorMediaLibraryCache) return window.__postEditorMediaLibraryCache;
    var node = byId('postEditorMediaLibraryData');
    if (!node) {
      window.__postEditorMediaLibraryCache = { all: [], image: [], audio: [], video: [] };
      return window.__postEditorMediaLibraryCache;
    }

    try {
      var parsed = JSON.parse(node.textContent || '{}');
      window.__postEditorMediaLibraryCache = {
        all: Array.isArray(parsed.all) ? parsed.all : [],
        image: Array.isArray(parsed.image) ? parsed.image : [],
        audio: Array.isArray(parsed.audio) ? parsed.audio : [],
        video: Array.isArray(parsed.video) ? parsed.video : []
      };
    } catch (error) {
      window.__postEditorMediaLibraryCache = { all: [], image: [], audio: [], video: [] };
    }

    return window.__postEditorMediaLibraryCache;
  }

  function getMediaLibraryItems(type) {
    var data = readMediaLibraryData();
    var key = String(type || 'all').toLowerCase();
    if (key === 'all') {
      return Array.isArray(data.all) ? data.all : [];
    }
    if (Array.isArray(data[key]) && data[key].length) {
      return data[key];
    }
    return (Array.isArray(data.all) ? data.all : []).filter(function (item) {
      return String((item && item.media_type) || '').toLowerCase() === key;
    });
  }

  function refreshMediaLibraryCache(item) {
    if (!item || typeof item !== 'object') return;
    var cache = readMediaLibraryData();
    var path = String(item.relative_path || '').trim();
    var type = String(item.media_type || '').trim().toLowerCase();
    function merge(list) {
      var next = Array.isArray(list) ? list.slice() : [];
      if (!path) return next;
      next = next.filter(function (entry) { return String((entry && entry.relative_path) || '').trim() !== path; });
      next.unshift(item);
      return next;
    }
    cache.all = merge(cache.all);
    if (type === 'image' || type === 'audio' || type === 'video') {
      cache[type] = merge(cache[type]);
    }
    window.__postEditorMediaLibraryCache = cache;
  }

  function mediaTypeHumanLabel(type) {
    return ({ image: 'imagens', audio: 'audios', video: 'videos' })[String(type || '').toLowerCase()] || 'midias';
  }

  function mediaTypeAccept(type) {
    return ({ image: 'image/*', audio: 'audio/*', video: 'video/*' })[String(type || '').toLowerCase()] || '*/*';
  }

  function pickLocalMediaFile(type) {
    var input = document.createElement('input');
    input.type = 'file';
    input.accept = mediaTypeAccept(type);
    input.style.display = 'none';
    document.body.appendChild(input);

    return new Promise(function (resolve) {
      function finish(result) {
        input.remove();
        resolve(result || null);
      }

      input.addEventListener('change', function () {
        var file = input.files && input.files[0] ? input.files[0] : null;
        finish(file || null);
      }, { once: true });

      input.click();
    });
  }

  function uploadMediaFileToLibrary(file, type, options) {
    var csrf = document.querySelector('input[name="_csrf_token"]');

    if (!file) {
      return Promise.resolve(null);
    }

    return new Promise(function (resolve) {
      function finish(result) {
        resolve(result || null);
      }

      var identityPromise = options && options.context === 'post'
        ? ensurePostIdentity()
        : Promise.resolve({ title: '', slug: '' });

      identityPromise.then(function (identity) {
        if (!identity && options && options.context === 'post') {
          finish(null);
          return null;
        }

        var data = new FormData();
        data.append('_csrf_token', csrf ? csrf.value : '');
        data.append('context', options && options.context ? options.context : 'library');
        data.append('media_type', type);
        if (options && options.audioRole) data.append('audio_role', options.audioRole);
        if (identity && identity.slug) data.append('post_slug', identity.slug);
        if (identity && identity.title) data.append('post_title', identity.title);
        data.append('arquivo', file);

        return fetch('<?= htmlspecialchars(url('/admin/midia/upload'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>', {
          method: 'POST',
          body: data,
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          credentials: 'same-origin'
        });
      }).then(function (response) {
        if (!response) return null;
        return response.text().then(function (body) {
          try {
            return JSON.parse(body);
          } catch (error) {
            return { ok: false, error: 'Nao foi possivel interpretar a resposta do upload.' };
          }
        });
      }).then(function (payload) {
        if (!payload) {
          finish(null);
          return;
        }
        if (payload.ok !== true || !payload.item) {
          alertModal(payload.error || 'Falha no upload da midia.').then(function () { finish(null); });
          return;
        }
        refreshMediaLibraryCache(payload.item);
        toast('Midia enviada com sucesso.');
        finish(payload.item);
      }).catch(function () {
        alertModal('Falha no upload da midia.').then(function () { finish(null); });
      });
    });
  }

  function uploadMediaToLibrary(type, options) {
    return pickLocalMediaFile(type).then(function (file) {
      if (!file) return null;
      return uploadMediaFileToLibrary(file, type, options);
    });
  }

  function duplicateLibraryImageToPost(path, identity) {
    var csrf = document.querySelector('input[name="_csrf_token"]');
    var data = new FormData();
    data.append('_csrf_token', csrf ? csrf.value : '');
    data.append('path', String(path || '').trim());
    data.append('slug', identity && identity.slug ? String(identity.slug || '').trim() : '');
    data.append('titulo', identity && identity.title ? String(identity.title || '').trim() : '');

    return fetch('<?= htmlspecialchars(url('/admin/copiar-post-imagem-biblioteca'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>', {
      method: 'POST',
      body: data,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      credentials: 'same-origin'
    }).then(function (response) {
      return response.text().then(function (body) {
        try {
          return JSON.parse(body);
        } catch (error) {
          return { ok: false, error: 'Nao foi possivel interpretar a resposta da biblioteca.' };
        }
      });
    });
  }

  function uploadInlineImageAsset(file) {
    var csrf = document.querySelector('input[name="_csrf_token"]');
    var slug = byId('slug');
    var titulo = byId('titulo');

    if (!file) return Promise.resolve(null);

    return ensurePostIdentity()
      .then(function (identity) {
        if (!identity) return null;

        return Promise.resolve(typeof window.__optimizeImageUploadFile === 'function'
          ? window.__optimizeImageUploadFile(file, { maxWidth: 1600, maxHeight: 1600, quality: 0.84 })
          : file)
          .catch(function () { return file; })
          .then(function (processedFile) {
            var data = new FormData();
            data.append('_csrf_token', csrf ? csrf.value : '');
            data.append('slug', slug && slug.value ? slug.value : (identity.slug || ''));
            data.append('titulo', titulo && titulo.value ? titulo.value : (identity.title || ''));
            data.append('imagem', processedFile);

            return fetch('<?= htmlspecialchars(url('/admin/upload-post-imagem'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>', {
              method: 'POST',
              body: data,
              credentials: 'same-origin'
            });
          });
      })
      .then(function (response) {
        if (!response) return null;
        return response.text().then(function (text) {
          try {
            return JSON.parse(text);
          } catch (error) {
            return { ok: false, error: 'Nao foi possivel interpretar a resposta do upload.' };
          }
        });
      });
  }

  function buildMediaLibraryCard(item, type) {
    var url = String((item && item.public_url) || '').trim();
    var path = String((item && item.relative_path) || '').trim();
    var title = String((item && item.name) || path || 'Arquivo').trim();
    var mime = String((item && item.mime) || '').trim();
    var size = String((item && item.size_label) || '').trim();
    var mediaType = String((item && item.media_type) || type || 'other').trim();
    var searchText = String([title, path, String((item && item.basename) || '').trim()].join(' ')).toLowerCase().trim();

    var preview = '<div style="display:flex;align-items:center;justify-content:center;height:126px;border-radius:14px;background:rgba(15,23,42,.88);border:1px solid rgba(34,211,238,.12);overflow:hidden;">';
    if (mediaType === 'image' && url) {
      preview += '<img src="' + escapeHtml(url) + '" alt="' + escapeHtml(title) + '" style="display:block;width:100%;height:100%;object-fit:cover;">';
    } else if (mediaType === 'audio' && url) {
      preview += '<audio controls preload="none" style="width:min(100%,260px);"><source src="' + escapeHtml(url) + '" type="' + escapeHtml(mime || 'audio/mpeg') + '"></audio>';
    } else if (mediaType === 'video' && url) {
      preview += '<video controls preload="metadata" style="width:100%;height:100%;object-fit:cover;"><source src="' + escapeHtml(url) + '" type="' + escapeHtml(mime || 'video/mp4') + '"></video>';
    } else {
      preview += '<div style="font-family:Orbitron,sans-serif;font-size:1rem;font-weight:800;color:#67e8f9;letter-spacing:.08em;">' + escapeHtml((String((item && item.extension) || 'arq') || 'ARQ').toUpperCase()) + '</div>';
    }
    preview += '</div>';

    return '' +
      '<div data-media-library-entry="1" data-media-library-search="' + escapeHtml(searchText) + '" style="display:grid;gap:12px;padding:14px;border-radius:18px;border:1px solid rgba(34,211,238,.16);background:rgba(2,6,23,.82);text-align:left;">' +
      preview +
      '<div style="display:grid;gap:4px;min-width:0;">' +
      '<div style="font-size:13px;font-weight:800;color:#f8fafc;line-height:1.4;word-break:break-word;">' + escapeHtml(title) + '</div>' +
      '<div style="font-size:11px;color:#94a3b8;word-break:break-all;">' + escapeHtml(path) + '</div>' +
      '<div style="font-size:11px;color:#67e8f9;">' + escapeHtml(size || '-') + (mime ? ' - ' + escapeHtml(mime) : '') + '</div>' +
      '</div>' +
      '<button type="button" data-media-library-pick="1" data-media-library-path="' + escapeHtml(path) + '" data-media-library-url="' + escapeHtml(url) + '" class="admin-btn admin-btn-primary" style="justify-content:center;">Usar esta midia</button>' +
      '</div>';
  }

  function pickMediaItemFromLibrary(type, options) {
    var items = getMediaLibraryItems(type);
    var ui = ensureUi();
    var modal = ui.modal;
    var title = byId('postEditorSystemModalTitle');
    var subtitle = byId('postEditorSystemModalSubtitle');
    var message = byId('postEditorSystemModalMessage');
    var form = byId('postEditorSystemModalForm');
    var submit = byId('postEditorSystemSubmit');
    var cancel = byId('postEditorSystemCancel');
    var closeTop = byId('postEditorSystemCloseTop');
    var overlay = modal.querySelector('[data-ui-overlay]');
    var label = mediaTypeHumanLabel(type);
    var canUpload = !options || options.allowUpload !== false;
    var extraFields = Array.isArray(options && options.extraFields) ? options.extraFields : [];

    title.textContent = (options && options.title) || ('Biblioteca de ' + label.charAt(0).toUpperCase() + label.slice(1));
    subtitle.textContent = (options && options.subtitle) || ('Selecione uma das ' + label + ' recentes ja cadastradas no portal.');
    subtitle.style.display = 'block';
    message.innerHTML = canUpload
      ? '<div style="padding:12px 14px;border-radius:14px;border:1px solid rgba(103,232,249,.16);background:rgba(248,250,252,.04);font-size:12px;color:#cbd5e1;">Voce pode selecionar um arquivo existente ou enviar um novo pela propria modal.</div>'
      : '';
    submit.style.display = canUpload ? 'inline-flex' : 'none';
    submit.textContent = 'Enviar ' + ({ image: 'imagem', audio: 'audio', video: 'video' })[String(type || '').toLowerCase()];
    submit.disabled = false;
    cancel.style.display = 'inline-flex';
    closeTop.style.display = 'inline-flex';
    cancel.textContent = 'Fechar';

    if (!items.length) {
      form.innerHTML = '<div style="border:1px dashed rgba(148,163,184,.35);border-radius:18px;padding:18px;color:#cbd5e1;font-size:13px;line-height:1.7;background:rgba(255,255,255,.02);">Nenhuma ' + label + ' recente encontrada. Use o botao abaixo para enviar um arquivo agora.</div>';
    } else {
      var fieldsHtml = extraFields.map(function (field) {
        var fieldId = 'post-editor-media-library-field-' + String(field.name || '');
        var isTextarea = field.type === 'textarea';
        var commonStyle = 'width:100%;border-radius:14px;padding:12px 14px;background:rgba(248,250,252,.06);background-color:rgba(248,250,252,.06);color:#f8fafc;border:1px solid rgba(103,232,249,.24);box-shadow:inset 0 1px 0 rgba(255,255,255,.03);';
        if (isTextarea) {
          return '' +
            '<div style="display:grid;gap:8px;">' +
            '  <label for="' + escapeHtml(fieldId) + '" style="font-size:12px;font-weight:800;color:#e2e8f0;">' + escapeHtml(field.label || field.name || 'Campo') + '</label>' +
            '  <textarea id="' + escapeHtml(fieldId) + '" data-media-library-field="' + escapeHtml(field.name || '') + '" rows="' + String(field.rows || 3) + '" placeholder="' + escapeHtml(field.placeholder || '') + '" class="nerd-input" style="' + commonStyle + '">' + escapeHtml(field.value || '') + '</textarea>' +
            '</div>';
        }
        return '' +
          '<div style="display:grid;gap:8px;">' +
          '  <label for="' + escapeHtml(fieldId) + '" style="font-size:12px;font-weight:800;color:#e2e8f0;">' + escapeHtml(field.label || field.name || 'Campo') + '</label>' +
          '  <input id="' + escapeHtml(fieldId) + '" data-media-library-field="' + escapeHtml(field.name || '') + '" type="' + escapeHtml(field.type || 'text') + '" value="' + escapeHtml(field.value || '') + '" placeholder="' + escapeHtml(field.placeholder || '') + '" class="nerd-input" style="' + commonStyle + '">' +
          '</div>';
      }).join('');
      form.innerHTML = '' +
        '<div style="display:grid;gap:14px;">' +
        (fieldsHtml ? ('  <div style="display:grid;gap:14px;">' + fieldsHtml + '</div>') : '') +
        '  <div style="display:grid;gap:8px;">' +
        '    <label for="post-editor-media-library-search" style="font-size:12px;font-weight:800;color:#e2e8f0;">Buscar por nome</label>' +
        '    <input id="post-editor-media-library-search" type="text" class="nerd-input" placeholder="Digite o nome ou caminho da midia" style="width:100%;border-radius:14px;padding:12px 14px;background:rgba(248,250,252,.06);background-color:rgba(248,250,252,.06);color:#f8fafc;border:1px solid rgba(103,232,249,.24);box-shadow:inset 0 1px 0 rgba(255,255,255,.03);">' +
        '  </div>' +
        '  <div id="post-editor-media-library-empty" style="display:none;border:1px dashed rgba(148,163,184,.35);border-radius:18px;padding:18px;color:#cbd5e1;font-size:13px;line-height:1.7;background:rgba(255,255,255,.02);">Nenhuma ' + label + ' encontrada para esta busca.</div>' +
        '  <div id="post-editor-media-library-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;max-height:420px;overflow:auto;padding-right:4px;">' + items.map(function (item) { return buildMediaLibraryCard(item, type); }).join('') + '</div>' +
        '</div>';
    }

    var searchInput = byId('post-editor-media-library-search');
    var emptyState = byId('post-editor-media-library-empty');
    function applySearch() {
      if (!searchInput) return;
      var term = String(searchInput.value || '').toLowerCase().trim();
      var visible = 0;
      form.querySelectorAll('[data-media-library-entry]').forEach(function (entry) {
        var searchValue = String(entry.getAttribute('data-media-library-search') || '').toLowerCase();
        var match = term === '' || searchValue.indexOf(term) !== -1;
        entry.style.display = match ? 'grid' : 'none';
        if (match) visible++;
      });
      if (emptyState) {
        emptyState.style.display = visible === 0 ? 'block' : 'none';
      }
    }
    if (searchInput) {
      searchInput.addEventListener('input', applySearch);
      window.setTimeout(function () { searchInput.focus(); }, 20);
      applySearch();
    }

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    return new Promise(function (resolve) {
      function cleanup(result) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
        submit.style.display = '';
        submit.disabled = false;
        cancel.textContent = 'Cancelar';
        if (searchInput) searchInput.removeEventListener('input', applySearch);
        form.removeEventListener('click', onPick);
        submit.removeEventListener('click', onUpload);
        cancel.removeEventListener('click', onCancel);
        closeTop.removeEventListener('click', onCancel);
        overlay.removeEventListener('click', onCancel);
        resolve(result || null);
      }
      function onCancel() { cleanup(null); }
      function onPick(event) {
        var trigger = event.target && event.target.closest ? event.target.closest('[data-media-library-pick]') : null;
        if (!trigger) return;
        event.preventDefault();
        var meta = {};
        form.querySelectorAll('[data-media-library-field]').forEach(function (field) {
          var key = String(field.getAttribute('data-media-library-field') || '').trim();
          if (!key) return;
          meta[key] = String(field.value || '').trim();
        });
        cleanup({
          path: trigger.getAttribute('data-media-library-path') || '',
          url: trigger.getAttribute('data-media-library-url') || '',
          meta: meta
        });
      }
      function onUpload(event) {
        if (event) event.preventDefault();
        submit.disabled = true;
        submit.textContent = 'Enviando...';
        uploadMediaToLibrary(type, {
          context: options && options.context ? options.context : 'library',
          audioRole: options && options.audioRole ? options.audioRole : ''
        }).then(function (item) {
          if (!item) {
            submit.disabled = false;
            submit.textContent = 'Enviar ' + ({ image: 'imagem', audio: 'audio', video: 'video' })[String(type || '').toLowerCase()];
            return;
          }
          cleanup({
            path: String(item.relative_path || ''),
            url: String(item.public_url || '')
          });
        });
      }
      form.addEventListener('click', onPick);
      if (canUpload) submit.addEventListener('click', onUpload);
      cancel.addEventListener('click', onCancel);
      closeTop.addEventListener('click', onCancel);
      overlay.addEventListener('click', onCancel);
    });
  }

  function slugify(value) {
    var raw = String(value || '').toLowerCase();
    try {
      raw = raw.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    } catch (error) {}
    return raw.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  }

  function ensurePostIdentity() {
    var titulo = byId('titulo');
    var slug = byId('slug');
    var currentTitle = titulo && titulo.value ? String(titulo.value).trim() : '';
    var currentSlug = slug && slug.value ? String(slug.value).trim() : '';
    if (currentTitle || currentSlug) {
      return Promise.resolve({
        title: currentTitle,
        slug: currentSlug || slugify(currentTitle)
      });
    }

    return promptModal({
      title: 'Identificacao do post',
      subtitle: 'Precisamos de um titulo ou slug para organizar a imagem enviada.',
      submitLabel: 'Continuar upload',
      fields: [
        { name: 'titulo', label: 'Titulo do post', placeholder: 'Ex.: Review do produto X' },
        { name: 'slug', label: 'Slug do post', placeholder: 'review-do-produto-x' }
      ]
    }).then(function (data) {
      if (!data) return null;
      var titleValue = String(data.titulo || '').trim();
      var slugValue = String(data.slug || '').trim() || slugify(titleValue);
      if (!titleValue && !slugValue) {
        return alertModal('Informe ao menos um titulo ou slug para continuar o upload.').then(function () { return null; });
      }
      if (titulo && titleValue && !titulo.value) titulo.value = titleValue;
      if (slug && slugValue && !slug.value) slug.value = slugValue;
      return {
        title: titulo && titulo.value ? String(titulo.value).trim() : titleValue,
        slug: slug && slug.value ? String(slug.value).trim() : slugValue
      };
    });
  }

  function confirmLeaveSystem() {
    return promptModal({
      title: 'Sair sem salvar?',
      subtitle: 'Existem alteracoes nao salvas nesta tela.',
      message: '<p>Se voce sair agora, as alteracoes ainda nao salvas serao perdidas.</p>',
      submitLabel: 'Sair assim mesmo',
      fields: []
    }).then(function (result) {
      return result !== null;
    });
  }

  function appendCaretParagraph(html) {
    return '\n' + html + '\n<p data-editor-caret="1" style="text-align:left;"><br></p>\n';
  }

  function indentMarkup(value, prefix) {
    return String(value || '')
      .split('\n')
      .map(function (line) {
        return line ? String(prefix || '') + line : line;
      })
      .join('\n');
  }

  function blockAttr(name, value) {
    return ' ' + name + '="' + escapeHtml(value || '') + '"';
  }

  function buildFigureMarkup(url, alt, legenda) {
    var imageUrl = String(url || '').replace(/"/g, '&quot;');
    var imageAlt = escapeHtml(alt || '');
    var caption = String(legenda || '').trim();
    var lines = [
      '<figure class="article-figure content-block-image" data-en-block="media" data-media-type="image"' +
        blockAttr('data-src', url) +
        blockAttr('data-alt', alt) +
        blockAttr('data-caption', caption) +
        '>',
      '  <img src="' + imageUrl + '" alt="' + imageAlt + '">'
    ];
    if (caption) {
      lines.push('  <figcaption>' + escapeHtml(caption) + '</figcaption>');
    }
    lines.push('</figure>');
    return lines.join('\n');
  }

  function buildFigure(url, alt, legenda) {
    return appendCaretParagraph(buildFigureMarkup(url, alt, legenda));
  }

  function buildVideoMarkup(value, legenda) {
    var raw = String(value || '').trim();
    var caption = String(legenda || '').trim();
    if (!raw) return '';
    var lines = [
      '<figure class="content-block content-block-video" data-en-block="media" data-media-type="video"' +
        blockAttr('data-src', raw) +
        blockAttr('data-caption', caption) +
        '>',
      '  <div class="content-block-label">Video</div>'
    ];
    if (/^<iframe[\s\S]*<\/iframe>$/i.test(raw)) {
      lines.push('  <div class="aspect-video">');
      lines.push(indentMarkup(raw, '    '));
      lines.push('  </div>');
      if (caption) {
        lines.push('  <figcaption>' + escapeHtml(caption) + '</figcaption>');
      }
      lines.push('</figure>');
      return lines.join('\n');
    }
    var match = raw.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([A-Za-z0-9_-]{6,})/i);
    if (match) {
      lines.push('  <div class="aspect-video">');
      lines.push('    <iframe src="https://www.youtube.com/embed/' + match[1] + '" title="Video incorporado" loading="lazy" allowfullscreen></iframe>');
      lines.push('  </div>');
      if (caption) {
        lines.push('  <figcaption>' + escapeHtml(caption) + '</figcaption>');
      }
      lines.push('</figure>');
      return lines.join('\n');
    }
    lines.push('  <video controls preload="metadata">');
    lines.push('    <source src="' + raw.replace(/"/g, '&quot;') + '">');
    lines.push('  </video>');
    if (caption) {
      lines.push('  <figcaption>' + escapeHtml(caption) + '</figcaption>');
    }
    lines.push('</figure>');
    return lines.join('\n');
  }

  function buildVideoHtml(value, legenda) {
    return appendCaretParagraph(buildVideoMarkup(value, legenda));
  }

  function formatQuote() {
    var range = currentRange();
    var selection = window.getSelection ? window.getSelection() : null;
    if (!selection || !selection.rangeCount) {
      try { document.execCommand('formatBlock', false, 'blockquote'); } catch (error) {}
      syncHiddenFields();
      return;
    }
    restoreRange(range);
    try { document.execCommand('formatBlock', false, 'blockquote'); } catch (error) {}
    syncHiddenFields();
    saveRange(currentRange());
    toast('Bloco de citacao aplicado.');
  }

  function insertLinkAtSelection() {
    var range = currentRange();
    saveRange(range);
    askLinkData().then(function (data) {
      if (!data || !data.url) return;
      restoreRange(range);
      var selection = window.getSelection ? window.getSelection() : null;
      var selectedText = selection && selection.rangeCount ? String(selection.toString() || '').trim() : '';
      var linkText = String(data.texto || '').trim() || selectedText || data.url;
      var html = '<a href="' + String(data.url).replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer">' + linkText + '</a>';
      insertAtRange(range, html);
      toast('Link inserido no conteudo.');
    });
  }

  function clearFormattingSelection() {
    restoreRange(currentRange());
    try {
      document.execCommand('removeFormat', false, null);
      document.execCommand('unlink', false, null);
    } catch (error) {}
    syncHiddenFields();
    saveRange(currentRange());
    toast('Formatacao removida da selecao.');
  }

  function doImagePrompt(initialUrl) {
    var range = currentRange();
    saveRange(range);
    askImageData(initialUrl || '').then(function (data) {
      if (!data || !data.url) return;
      insertAtRange(range, buildFigure(data.url, data.alt, data.legenda));
      toast('Imagem inserida no conteudo.');
    });
  }

  function insertImageFromLibrary() {
    var range = currentRange();
    saveRange(range);
    pickMediaItemFromLibrary('image', {
      title: 'Biblioteca de imagens',
      subtitle: 'Busque por nome, selecione a imagem e o editor criara uma copia numerada para este post.',
      context: 'post',
      extraFields: [
        { name: 'alt', label: 'Texto alternativo', placeholder: 'Descricao curta da imagem' },
        { name: 'legenda', label: 'Legenda', type: 'textarea', rows: 3, placeholder: 'Legenda opcional' }
      ]
    }).then(function (item) {
      if (!item || !item.path) return;
      return ensurePostIdentity()
        .then(function (identity) {
          if (!identity) return null;
          return duplicateLibraryImageToPost(item.path, identity);
        })
        .then(function (payload) {
          if (!payload) return;
          if (payload.ok !== true) {
            return alertModal(payload.error || 'Falha ao preparar a imagem da biblioteca.');
          }
          if (payload.item) refreshMediaLibraryCache(payload.item);
          var meta = item.meta || {};
          insertAtRange(range, buildFigure(payload.url || payload.path || '', meta.alt || '', meta.legenda || ''));
          toast('Imagem da biblioteca inserida no conteudo.');
        })
        .catch(function () {
          alertModal('Falha ao preparar a imagem da biblioteca para o conteudo.');
        });
    });
  }

  function doVideoPrompt() {
    var range = currentRange();
    saveRange(range);
    askVideoData().then(function (data) {
      if (!data || !data.valor) return;
      insertAtRange(range, buildVideoHtml(data.valor, data.legenda || ''));
      toast('Video inserido no conteudo.');
    });
  }

  function insertVideoFromLibrary() {
    var range = currentRange();
    saveRange(range);
    pickMediaItemFromLibrary('video', {
      title: 'Biblioteca de videos',
      subtitle: 'Busque por nome, selecione o video e defina a legenda antes de inserir no conteudo.',
      context: 'post',
      extraFields: [
        { name: 'legenda', label: 'Legenda', type: 'textarea', rows: 3, placeholder: 'Legenda opcional' }
      ]
    }).then(function (item) {
      if (!item || !item.url) return;
      var meta = item.meta || {};
      insertAtRange(range, buildVideoHtml(item.path || item.url, meta.legenda || ''));
      toast('Video da biblioteca inserido no conteudo.');
    });
  }

  function insertUploadedVideo() {
    var range = currentRange();
    saveRange(range);
    pickLocalMediaFile('video').then(function (file) {
      if (!file) return null;
      return uploadMediaFileToLibrary(file, 'video', { context: 'post' }).then(function (item) {
        if (!item) return null;
        return askVideoMeta().then(function (meta) {
          if (meta === null) return null;
          return { item: item, meta: meta };
        });
      });
    }).then(function (payload) {
      if (!payload || !payload.item) return;
      insertAtRange(range, buildVideoHtml(payload.item.relative_path || payload.item.public_url || '', payload.meta.legenda || ''));
      toast('Video enviado e inserido no conteudo.');
    });
  }

  function insertAudioBlockFromUpload() {
    insertAudioBlock('upload');
  }

  function insertAudioBlockFromLibrary() {
    insertAudioBlock('library');
  }

  function insertAudioBlockFromUrl() {
    insertAudioBlock('url');
  }


  function normalizeMediaPath(value) {
    var raw = String(value || '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw)) return raw;
    if (raw.charAt(0) === '/') return raw;
    return '/' + raw.replace(/^\.?\//, '');
  }

  function buildAudioBlockMarkup(data) {
    var title = String((data && data.title) || '').trim();
    var subtitle = String((data && data.subtitle) || '').trim();
    var buttonText = String((data && data.buttonText) || 'Ouvir narracao').trim();
    var narracao = normalizeMediaPath((data && data.narracao) || '');
    var ambiente = normalizeMediaPath((data && data.ambiente) || '');

    var attrs = ['data-en-block="media"', 'data-media-type="audio"'];
    attrs.push('data-title="' + escapeHtml(title || '') + '"');
    attrs.push('data-subtitle="' + escapeHtml(subtitle || '') + '"');
    attrs.push('data-button-text="' + escapeHtml(buttonText || '') + '"');
    if (narracao) attrs.push('data-audio-narracao="' + escapeHtml(narracao) + '"');
    if (ambiente) attrs.push('data-audio-ambiente="' + escapeHtml(ambiente) + '"');

    var lines = [
      '<div class="en-audio-block" ' + attrs.join(' ') + '>',
      '  <div class="en-audio-header">',
      '    <span class="en-audio-icon" aria-hidden="true"><i class="fa-solid fa-scroll"></i></span>',
      '    <strong class="en-audio-title">' + escapeHtml(title || 'Relato') + '</strong>',
      '  </div>'
    ];
    if (subtitle) {
      lines.push('  <p class="en-audio-subtitle">' + escapeHtml(subtitle) + '</p>');
    }
    lines.push('  <button type="button" class="en-audio-button" data-en-audio-toggle="1">' + escapeHtml(buttonText) + '</button>');
    lines.push('</div>');

    return lines.join('\n');
  }

  function buildAudioBlockHtml(data) {
    return appendCaretParagraph(buildAudioBlockMarkup(data));
  }

  function mediaBlockFromTarget(target) {
    if (!target || !target.closest) return null;
    var wrapped = target.closest('[data-en-block="media"], .content-block-image, .content-block-video, figure.article-figure, .en-audio-block, figure');
    if (wrapped) return wrapped;
    return target.closest('img, video, iframe');
  }

  function mediaBlockType(block) {
    if (!block) return '';
    var explicit = String(block.getAttribute('data-media-type') || '').trim();
    if (explicit) return explicit;
    var tagName = String(block.tagName || '').toLowerCase();
    if (tagName === 'img') return 'image';
    if (tagName === 'video' || tagName === 'iframe') return 'video';
    if (block.classList.contains('en-audio-block')) return 'audio';
    if (block.classList.contains('content-block-video')) return 'video';
    if (block.classList.contains('content-block-image') || block.querySelector('img')) return 'image';
    if (block.querySelector('video') || block.querySelector('iframe')) return 'video';
    return '';
  }

  function mediaBlockData(block) {
    var type = mediaBlockType(block);
    var captionEl = block ? block.querySelector('figcaption') : null;
    if (type === 'image') {
      var img = String(block.tagName || '').toLowerCase() === 'img' ? block : block.querySelector('img');
      return {
        path: block.getAttribute('data-src') || (img ? img.getAttribute('src') : '') || '',
        alt: block.getAttribute('data-alt') || (img ? img.getAttribute('alt') : '') || '',
        legenda: block.getAttribute('data-caption') || (captionEl ? captionEl.textContent.trim() : '')
      };
    }
    if (type === 'video') {
      var tagName = String(block.tagName || '').toLowerCase();
      var iframe = tagName === 'iframe' ? block : block.querySelector('iframe');
      var source = block.querySelector ? block.querySelector('source') : null;
      var video = tagName === 'video' ? block : block.querySelector('video');
      return {
        path: block.getAttribute('data-src') || (iframe ? iframe.outerHTML : '') || (source ? source.getAttribute('src') : '') || (video ? video.getAttribute('src') : '') || '',
        legenda: block.getAttribute('data-caption') || (captionEl ? captionEl.textContent.trim() : '')
      };
    }
    if (type === 'audio') {
      var titleEl = block.querySelector('.en-audio-title');
      var subtitleEl = block.querySelector('.en-audio-subtitle');
      var button = block.querySelector('[data-en-audio-toggle]');
      return {
        title: block.getAttribute('data-title') || (titleEl ? titleEl.textContent.trim() : ''),
        subtitle: block.getAttribute('data-subtitle') || (subtitleEl ? subtitleEl.textContent.trim() : ''),
        buttonText: block.getAttribute('data-button-text') || (button ? button.textContent.trim() : 'Ouvir narracao'),
        narracao: block.getAttribute('data-audio-narracao') || '',
        ambiente: block.getAttribute('data-audio-ambiente') || ''
      };
    }
    return {};
  }

  function replaceMediaBlock(block, html) {
    if (!block || !html) return;
    block.outerHTML = html;
    syncHiddenFields();
    hideMediaBlockToolbar();
    toast('Bloco atualizado.');
  }

  function editMediaBlock(block) {
    var type = mediaBlockType(block);
    var data = mediaBlockData(block);
    if (type === 'image') {
      openImageBlockBuilder('', data, 'edit').then(function (payload) {
        if (!payload || !payload.path) return;
        replaceMediaBlock(block, buildFigureMarkup(payload.path, payload.alt || '', payload.legenda || ''));
      });
      return;
    }
    if (type === 'video') {
      openVideoBlockBuilder('', data, 'edit').then(function (payload) {
        if (!payload || !payload.path) return;
        replaceMediaBlock(block, buildVideoMarkup(payload.path, payload.legenda || ''));
      });
      return;
    }
    if (type === 'audio') {
      openAudioBlockBuilder('', data, 'edit').then(function (payload) {
        if (!payload) return;
        replaceMediaBlock(block, buildAudioBlockMarkup(payload));
      });
    }
  }

  function hideMediaBlockToolbar() {
    var toolbar = byId('postEditorMediaBlockToolbar');
    if (toolbar) toolbar.remove();
  }

  function showMediaBlockToolbar(block) {
    if (!block || !editor() || !editor().contains(block)) return;
    hideMediaBlockToolbar();
    var rect = block.getBoundingClientRect();
    var toolbar = document.createElement('div');
    toolbar.id = 'postEditorMediaBlockToolbar';
    toolbar.style.cssText = 'position:absolute;z-index:10040;display:flex;flex-wrap:wrap;gap:6px;padding:8px;border-radius:14px;border:1px solid rgba(34,211,238,.28);background:rgba(2,6,23,.96);box-shadow:0 18px 45px rgba(0,0,0,.35);';
    toolbar.innerHTML = '' +
      '<button type="button" data-action="edit" class="admin-btn admin-btn-primary" style="padding:7px 10px;font-size:12px;">Editar</button>' +
      '<button type="button" data-action="duplicate" class="admin-btn admin-btn-secondary" style="padding:7px 10px;font-size:12px;">Duplicar</button>' +
      '<button type="button" data-action="up" class="admin-btn admin-btn-secondary" style="padding:7px 10px;font-size:12px;">Mover acima</button>' +
      '<button type="button" data-action="down" class="admin-btn admin-btn-secondary" style="padding:7px 10px;font-size:12px;">Mover abaixo</button>' +
      '<button type="button" data-action="remove" class="admin-btn admin-btn-secondary" style="padding:7px 10px;font-size:12px;color:#fecaca;border-color:rgba(248,113,113,.35);">Remover</button>';
    toolbar.style.left = Math.max(12, rect.left + window.scrollX) + 'px';
    toolbar.style.top = Math.max(12, rect.top + window.scrollY - 48) + 'px';
    toolbar.addEventListener('click', function (event) {
      var button = event.target && event.target.closest ? event.target.closest('[data-action]') : null;
      if (!button) return;
      event.preventDefault();
      event.stopPropagation();
      var action = button.getAttribute('data-action');
      if (action === 'edit') {
        editMediaBlock(block);
      } else if (action === 'duplicate') {
        block.insertAdjacentHTML('afterend', '\n' + block.outerHTML + '\n');
        syncHiddenFields();
        toast('Bloco duplicado.');
      } else if (action === 'up' && block.previousElementSibling) {
        block.parentNode.insertBefore(block, block.previousElementSibling);
        syncHiddenFields();
        showMediaBlockToolbar(block);
      } else if (action === 'down' && block.nextElementSibling) {
        block.parentNode.insertBefore(block.nextElementSibling, block);
        syncHiddenFields();
        showMediaBlockToolbar(block);
      } else if (action === 'remove') {
        if (window.confirm('Remover este bloco de midia?')) {
          block.remove();
          syncHiddenFields();
          hideMediaBlockToolbar();
          toast('Bloco removido.');
        }
      }
    });
    document.body.appendChild(toolbar);
  }

  function insertImageBlock(preferredAction) {
    var range = currentRange();
    saveRange(range);

    openImageBlockBuilder(preferredAction || '').then(function (payload) {
      if (!payload || !payload.path) return;

      var stored = getStoredRange() || range;
      insertAtRange(stored, buildFigure(payload.path, payload.alt || '', payload.legenda || ''));
      toast('Bloco de imagem inserido no conteudo.');
    });
  }

  function insertVideoBlock(preferredAction) {
    var range = currentRange();
    saveRange(range);

    openVideoBlockBuilder(preferredAction || '').then(function (payload) {
      if (!payload || !payload.path) return;

      var stored = getStoredRange() || range;
      insertAtRange(stored, buildVideoHtml(payload.path, payload.legenda || ''));
      toast('Bloco de video inserido no conteudo.');
    });
  }

  function insertAudioBlock(preferredAction) {
    var range = currentRange();
    saveRange(range);

    openAudioBlockBuilder(preferredAction || '').then(function (payload) {
      if (!payload) return;

      var narracaoPath = String(payload.narracao || '').trim();
      var ambientePath = String(payload.ambiente || '').trim();
      if (!narracaoPath && !ambientePath) {
        return alertModal('Selecione ao menos um audio (narracao ou ambiente) para inserir o bloco.');
      }

      var stored = getStoredRange() || range;
      var html = buildAudioBlockHtml({
        title: payload.title,
        subtitle: payload.subtitle,
        buttonText: payload.buttonText,
        narracao: narracaoPath,
        ambiente: ambientePath
      });
      insertAtRange(stored, html);
      toast('Bloco de audio inserido no conteudo.');
    });
  }

  function editorHelpByTab(tabName) {
    var messages = {
      visual: 'Use a barra acima para formatar.',
      html: 'Edite diretamente o codigo HTML.'
    };
    return messages[tabName] || messages.visual;
  }

  function detectActiveEditorTab() {
    if (!byId('panel-html') || byId('panel-html').classList.contains('hidden') === false) return 'html';
    return 'visual';
  }

  function setEditorHelp(tabName) {
    var help = byId('editor-ajuda');
    if (!help) return;
    help.textContent = editorHelpByTab(tabName || detectActiveEditorTab());
  }

  function safeSwitchTab(tabName) {
    document.querySelectorAll('.editor-panel').forEach(function (panel) {
      panel.classList.add('hidden');
    });

    var panel = byId('panel-' + tabName);
    if (panel) panel.classList.remove('hidden');

    document.querySelectorAll('[id^="tab-btn-"]').forEach(function (btn) {
      btn.classList.remove('bg-cyan-500/20', 'text-cyan-400', 'border-t', 'border-x', 'border-cyan-500/30');
      btn.classList.add('bg-slate-800', 'text-gray-400');
    });

    var activeBtn = byId('tab-btn-' + tabName);
    if (activeBtn) {
      activeBtn.classList.remove('bg-slate-800', 'text-gray-400');
      activeBtn.classList.add('bg-cyan-500/20', 'text-cyan-400', 'border-t', 'border-x', 'border-cyan-500/30');
    }

    setEditorHelp(tabName);

    var visual = byId('editor-visual');
    var htmlArea = byId('editor-html');
    if (tabName === 'html' && visual && htmlArea) {
      htmlArea.value = visual.innerHTML;
      if (window.AdminHtmlEditor && typeof window.AdminHtmlEditor.setValueByTextareaId === 'function') {
        window.AdminHtmlEditor.setValueByTextareaId('editor-html', htmlArea.value);
      }
    } else if (tabName === 'visual' && visual && htmlArea) {
      if (window.AdminHtmlEditor && typeof window.AdminHtmlEditor.syncByTextareaId === 'function') {
        window.AdminHtmlEditor.syncByTextareaId('editor-html');
      }
      visual.innerHTML = htmlArea.value;
      syncHiddenFields();
    }

  }

  function installEditorTabFixes() {
    window.switchTab = safeSwitchTab;
    setEditorHelp(detectActiveEditorTab());
  }
  function bindMediaButtons() {
    [
      ['editor-image-block-trigger', function () { if (typeof window.inserirBlocoImagem === 'function') window.inserirBlocoImagem(); }],
      ['editor-video-block-trigger', function () { if (typeof window.inserirBlocoVideo === 'function') window.inserirBlocoVideo(); }],
      ['editor-audio-block-trigger', function () { if (typeof window.inserirBlocoAudio === 'function') window.inserirBlocoAudio(); }],
      ['editor-toolbar-image-block', function () { if (typeof window.inserirBlocoImagem === 'function') window.inserirBlocoImagem(); }],
      ['editor-toolbar-video-block', function () { if (typeof window.inserirBlocoVideo === 'function') window.inserirBlocoVideo(); }],
      ['editor-toolbar-audio-block', function () { if (typeof window.inserirBlocoAudio === 'function') window.inserirBlocoAudio(); }]
    ].forEach(function (pair) {
      var button = byId(pair[0]);
      if (!button) return;
      button.onclick = function (event) {
        if (event) {
          event.preventDefault();
          event.stopPropagation();
        }
        pair[1]();
        return false;
      };
    });
  }

  function serializeFormState(form) {
    if (!form) return '';
    syncHiddenFields();
    var data = new FormData(form);
    data.delete('_csrf_token');
    var pairs = [];
    data.forEach(function (value, key) {
      pairs.push([key, String(value)]);
    });
    pairs.sort(function (a, b) {
      if (a[0] === b[0]) return a[1] < b[1] ? -1 : (a[1] > b[1] ? 1 : 0);
      return a[0] < b[0] ? -1 : 1;
    });
    return pairs.map(function (pair) { return pair[0] + '=' + pair[1]; }).join('&');
  }

  function initInternalLeaveGuard() {
    var form = postForm();
    if (!form) return;
    var tracker = window.__postEditorLeaveTracker = window.__postEditorLeaveTracker || { initialState: '', submitting: false, bound: false };
    tracker.initialState = serializeFormState(form);

    form.addEventListener('submit', function () {
      tracker.submitting = true;
    });

    if (tracker.bound) return;
    tracker.bound = true;

    document.addEventListener('click', function (event) {
      var anchor = event.target && event.target.closest ? event.target.closest('a[href]') : null;
      if (!anchor) return;
      if (anchor.target === '_blank' || anchor.hasAttribute('download')) return;
      if (anchor.getAttribute('href').charAt(0) === '#') return;
      if (tracker.submitting) return;
      var href = anchor.getAttribute('href') || '';
      if (!href || href.indexOf('javascript:') === 0) return;
      var url;
      try {
        url = new URL(href, window.location.href);
      } catch (error) {
        return;
      }
      if (url.origin !== window.location.origin) return;
      if (url.pathname === window.location.pathname && url.search === window.location.search) return;
      if (url.pathname.indexOf(appBasePath() + '/admin') !== 0) return;
      if (serializeFormState(form) === tracker.initialState) return;

      event.preventDefault();
      confirmLeaveSystem().then(function (shouldLeave) {
        if (!shouldLeave) return;
        window.__postEditorAllowUnload = true;
        tracker.submitting = true;
        window.location.href = url.toString();
      });
    }, true);
  }

  function initSystemUi() {
    var root = editor();
    var originalInput = byId('editorImageUpload');
    if (!root || !originalInput) return;
    if (document.body.dataset.postEditorSystemUiReady === '1') {
      bindMediaButtons();
      return;
    }
    document.body.dataset.postEditorSystemUiReady = '1';

    var fileInput = originalInput.cloneNode(true);
    originalInput.parentNode.replaceChild(fileInput, originalInput);

    root.addEventListener('keyup', function () { saveRange(currentRange()); syncHiddenFields(); });
    root.addEventListener('mouseup', function () { saveRange(currentRange()); syncHiddenFields(); });
    root.addEventListener('focus', function () { saveRange(currentRange()); syncHiddenFields(); });
    root.addEventListener('input', function () { saveRange(currentRange()); syncHiddenFields(); });
    root.addEventListener('click', function (event) {
      var block = mediaBlockFromTarget(event.target);
      if (!block || !root.contains(block)) return;
      event.preventDefault();
      showMediaBlockToolbar(block);
    });
    root.addEventListener('dblclick', function (event) {
      var block = mediaBlockFromTarget(event.target);
      if (!block || !root.contains(block)) return;
      event.preventDefault();
      editMediaBlock(block);
    });
    document.addEventListener('click', function (event) {
      var toolbar = byId('postEditorMediaBlockToolbar');
      if (toolbar && toolbar.contains(event.target)) return;
      if (mediaBlockFromTarget(event.target)) return;
      hideMediaBlockToolbar();
    });

    window.enviarImagemDoEditor = function () { insertImageBlock('upload'); };
    window.inserirImagem = function () { insertImageBlock('url'); };
    window.inserirImagemDaBiblioteca = function () { insertImageBlock('library'); };
    window.inserirBlocoImagem = function () { insertImageBlock(); };
    window.enviarVideoDoEditor = function () { insertVideoBlock('upload'); };
    window.inserirVideo = function () { insertVideoBlock('url'); };
    window.inserirVideoDaBiblioteca = function () { insertVideoBlock('library'); };
    window.inserirBlocoVideo = function () { insertVideoBlock(); };
    window.inserirAudioPorUpload = function () { insertAudioBlockFromUpload(); };
    window.inserirAudioDaBiblioteca = function () { insertAudioBlockFromLibrary(); };
    window.inserirAudioPorUrl = function () { insertAudioBlockFromUrl(); };
    window.inserirBlocoAudio = function () { insertAudioBlock(); };
    window.inserirLink = function () { insertLinkAtSelection(); };
    window.limparFormatacao = function () { clearFormattingSelection(); };
    window.aplicarCitacao = function () { formatQuote(); };

    fileInput.addEventListener('change', function (event) {
      var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
      if (!file) return;
      if (event) {
        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') event.stopImmediatePropagation();
      }

      var range = getStoredRange() || currentRange();
      var csrf = document.querySelector('input[name="_csrf_token"]');
      var slug = byId('slug');
      var titulo = byId('titulo');

      ensurePostIdentity()
        .then(function (identity) {
          if (!identity) {
            fileInput.value = '';
            return null;
          }

          return Promise.resolve(typeof window.__optimizeImageUploadFile === 'function'
            ? window.__optimizeImageUploadFile(file, { maxWidth: 1600, maxHeight: 1600, quality: 0.84 })
            : file)
            .catch(function () { return file; })
            .then(function (processedFile) {
              var data = new FormData();
              data.append('_csrf_token', csrf ? csrf.value : '');
              data.append('slug', slug && slug.value ? slug.value : (identity.slug || ''));
              data.append('titulo', titulo && titulo.value ? titulo.value : (identity.title || ''));
              data.append('imagem', processedFile);
              return fetch('<?= htmlspecialchars(url('/admin/upload-post-imagem'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>', {
                method: 'POST',
                body: data,
                credentials: 'same-origin'
              });
            });
        })
        .then(function (response) {
          if (!response) return null;
          return response.text().then(function (text) {
            try { return JSON.parse(text); } catch (error) { return { ok: false, error: 'Nao foi possivel interpretar a resposta do upload.' }; }
          });
        })
        .then(function (payload) {
          if (!payload) return;
          if (!payload || payload.ok !== true) {
            return alertModal(payload && payload.error ? payload.error : 'Falha no upload da imagem.');
          }
          return askImageMeta().then(function (meta) {
            if (meta === null) return;
            insertAtRange(range, buildFigure(payload.url || '', meta.alt || '', meta.legenda || ''));
            toast('Imagem inserida no conteudo.');
          });
        })
        .catch(function () {
          alertModal('Falha ao enviar a imagem do conteudo.');
        })
        .finally(function () {
          fileInput.value = '';
        });
    }, true);

    bindMediaButtons();
    initInternalLeaveGuard();
    syncHiddenFields();
    saveRange(currentRange());
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSystemUi);
    window.addEventListener('load', initSystemUi);
  } else {
    window.setTimeout(initSystemUi, 0);
  }
})();
</script>
