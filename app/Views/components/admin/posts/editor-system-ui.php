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
        '<div data-ui-overlay style="position:absolute;inset:0;background:rgba(2,6,23,.84);backdrop-filter:blur(4px);"></div>' +
        '<div style="position:relative;width:min(100%,680px);border-radius:24px;border:1px solid rgba(34,211,238,.18);background:#020617;box-shadow:0 30px 90px rgba(0,0,0,.45);color:#e2e8f0;overflow:hidden;">' +
        '<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:22px 24px;border-bottom:1px solid rgba(30,41,59,.9);">' +
        '<div><div id="postEditorSystemModalTitle" style="font-family:Orbitron,sans-serif;font-size:18px;font-weight:900;color:#fff;">Editor</div><div id="postEditorSystemModalSubtitle" style="margin-top:6px;font-size:12px;color:#94a3b8;"></div></div>' +
        '<button type="button" id="postEditorSystemCloseTop" class="admin-btn admin-btn-secondary" style="padding:8px 12px;">Fechar</button>' +
        '</div>' +
        '<div style="padding:24px;display:grid;gap:16px;">' +
        '<div id="postEditorSystemModalMessage" style="font-size:14px;line-height:1.7;color:#cbd5e1;"></div>' +
        '<form id="postEditorSystemModalForm" style="display:grid;gap:14px;"></form>' +
        '</div>' +
        '<div style="display:flex;justify-content:flex-end;gap:12px;padding:20px 24px;border-top:1px solid rgba(30,41,59,.9);">' +
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
      input.style.cssText = 'width:100%;border-radius:14px;padding:12px 14px;background:#0f172a;background-color:#0f172a;color:#e2e8f0;border:1px solid rgba(34,211,238,.22);';
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
        { name: 'valor', label: 'URL ou iframe', type: 'textarea', rows: 4, required: true, placeholder: 'https://www.youtube.com/watch?v=... ou <iframe ...></iframe>' }
      ]
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
    return html + '<p data-editor-caret="1" style="text-align:left;"><br></p>';
  }

  function buildFigure(url, alt, legenda) {
    return appendCaretParagraph('<figure><img src="' + String(url || '').replace(/"/g, '&quot;') + '" alt="' + String(alt || '').replace(/"/g, '&quot;') + '">' + (legenda ? '<figcaption>' + legenda + '</figcaption>' : '') + '</figure>');
  }

  function buildVideoHtml(value) {
    var raw = String(value || '').trim();
    if (!raw) return '';
    if (/^<iframe[\s\S]*<\/iframe>$/i.test(raw)) return appendCaretParagraph(raw);
    var match = raw.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([A-Za-z0-9_-]{6,})/i);
    if (match) return appendCaretParagraph('<div class="content-block content-block-video"><div class="content-block-label">Video</div><div class="aspect-video"><iframe src="https://www.youtube.com/embed/' + match[1] + '" title="Video incorporado" loading="lazy" allowfullscreen></iframe></div></div>');
    return appendCaretParagraph('<video controls preload="metadata" style="width:100%;border-radius:16px;overflow:hidden;"><source src="' + raw.replace(/"/g, '&quot;') + '"></video>');
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

  function doVideoPrompt() {
    var range = currentRange();
    saveRange(range);
    askVideoData().then(function (data) {
      if (!data || !data.valor) return;
      insertAtRange(range, buildVideoHtml(data.valor));
      toast('Video inserido no conteudo.');
    });
  }

  function bindMediaButtons() {
    [
      ['editor-upload-trigger', function () { if (typeof window.enviarImagemDoEditor === 'function') window.enviarImagemDoEditor(); }],
      ['editor-toolbar-upload', function () { if (typeof window.enviarImagemDoEditor === 'function') window.enviarImagemDoEditor(); }],
      ['editor-url-trigger', function () { if (typeof window.inserirImagem === 'function') window.inserirImagem(); }],
      ['editor-video-trigger', function () { if (typeof window.inserirVideo === 'function') window.inserirVideo(); }],
      ['editor-toolbar-video', function () { if (typeof window.inserirVideo === 'function') window.inserirVideo(); }]
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

    window.enviarImagemDoEditor = function () {
      var range = currentRange();
      saveRange(range);
      fileInput.click();
    };

    window.inserirImagem = function () { doImagePrompt(''); };
    window.inserirVideo = function () { doVideoPrompt(); };
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

  if (document.readyState === 'complete') {
    window.setTimeout(initSystemUi, 0);
  } else {
    window.addEventListener('load', initSystemUi);
  }
})();
</script>
