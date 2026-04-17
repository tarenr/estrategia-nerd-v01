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

  function htmlArea() {
    return byId('editor-html');
  }

  function getSelectionStore() {
    if (!window.__postEditorSelection) {
      window.__postEditorSelection = { range: null };
    }
    return window.__postEditorSelection;
  }

  function saveSelection() {
    var root = editor();
    if (!root) return;
    var selection = window.getSelection ? window.getSelection() : null;
    if (!selection || selection.rangeCount === 0) return;
    var range = selection.getRangeAt(0);
    if (!root.contains(range.commonAncestorContainer)) return;
    getSelectionStore().range = range.cloneRange();
  }

  function restoreSelection() {
    var root = editor();
    var store = getSelectionStore();
    if (!root) return;
    root.focus();

    if (!store.range) {
      var range = document.createRange();
      range.selectNodeContents(root);
      range.collapse(false);
      var selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(range);
      return;
    }

    var selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(store.range);
  }

  function syncEditorState() {
    var root = editor();
    var hidden = hiddenField();
    if (!root || !hidden) return;

    hidden.value = root.innerHTML;
    var html = htmlArea();
    if (html) html.value = root.innerHTML;

    var wordCount = byId('wordCount');
    if (wordCount) {
      var text = (root.innerText || '').trim();
      var words = text ? text.split(/\s+/).filter(Boolean).length : 0;
      wordCount.textContent = words + ' palavra' + (words !== 1 ? 's' : '');
    }
  }

  function insertHtmlAtCursor(html) {
    restoreSelection();
    try {
      document.execCommand('insertHTML', false, html);
    } catch (error) {
      var root = editor();
      if (!root) return;
      root.innerHTML += html;
    }
    syncEditorState();
    saveSelection();
  }

  function appendHtmlToEditor(html) {
    var root = editor();
    if (!root) return;
    root.innerHTML += html;
    syncEditorState();
    saveSelection();
  }

  function replaceEditorHtml(html) {
    var root = editor();
    if (!root) return;
    root.innerHTML = html;
    syncEditorState();
    saveSelection();
  }

  function appBasePath() {
    var path = String(window.location.pathname || '');
    var adminIndex = path.indexOf('/admin/');
    if (adminIndex !== -1) {
      return path.slice(0, adminIndex);
    }
    if (path.endsWith('/admin')) {
      return path.slice(0, -6);
    }
    return '';
  }

  function normalizeMediaUrl(value) {
    var raw = String(value || '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw)) return raw;
    if (raw.indexOf('//') === 0) return window.location.protocol + raw;

    var base = appBasePath();
    if (raw.charAt(0) === '/') {
      if (base !== '' && raw.indexOf(base + '/') !== 0) {
        return window.location.origin + base + raw;
      }
      return window.location.origin + raw;
    }

    return window.location.origin + (base !== '' ? base : '') + '/' + raw.replace(/^\/+/, '');
  }

  function normalizePreviewContent(html) {
    var wrapper = document.createElement('div');
    wrapper.innerHTML = String(html || '');

    wrapper.querySelectorAll('img, iframe, video, source, a').forEach(function (node) {
      if (node.hasAttribute('src')) {
        node.setAttribute('src', normalizeMediaUrl(node.getAttribute('src')));
      }
      if (node.hasAttribute('href')) {
        node.setAttribute('href', normalizeMediaUrl(node.getAttribute('href')));
      }
      if (node.hasAttribute('poster')) {
        node.setAttribute('poster', normalizeMediaUrl(node.getAttribute('poster')));
      }
    });

    return wrapper.innerHTML;
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderHighlightedTitle(title) {
    var raw = String(title || '').trim();
    if (!raw) return 'Sem titulo';

    return escapeHtml(raw).replace(/\[\[(.+?)\]\]/g, '<span class="article-title-accent">$1</span>');
  }

  function currentCoverUrl() {
    var input = byId('imagem_capa');
    return input ? normalizeMediaUrl(input.value) : '';
  }

  function syncMediaPreview(targetId) {
    var input = byId(targetId);
    var preview = byId(targetId + '_preview');
    var empty = byId(targetId + '_preview_empty');
    if (!input || !preview) return;

    var mediaUrl = normalizeMediaUrl(input.value);
    if (mediaUrl) {
      preview.src = mediaUrl;
      preview.classList.remove('hidden');
      if (empty) empty.classList.add('hidden');
    } else {
      preview.removeAttribute('src');
      preview.classList.add('hidden');
      if (empty) empty.classList.remove('hidden');
    }
  }

  function bindMediaPreviews() {
    ['imagem_capa', 'imagem_thumb'].forEach(function (targetId) {
      var input = byId(targetId);
      if (!input || input.dataset.runtimeBound === '1') return;
      input.dataset.runtimeBound = '1';
      input.addEventListener('input', function () {
        syncMediaPreview(targetId);
      });
      input.addEventListener('change', function () {
        syncMediaPreview(targetId);
      });
      syncMediaPreview(targetId);
    });

    window.selecionarMidia = function selecionarMidia(targetId, url) {
      var input = byId(targetId);
      if (!input) return;
      input.value = url || '';
      syncMediaPreview(targetId);
    };
  }
  function currentCategoryLabel() {
    var select = byId('categoria_post_id');
    if (!select || select.selectedIndex < 0) return 'Geral';
    var option = select.options[select.selectedIndex];
    return option && option.textContent ? option.textContent.trim() : 'Geral';
  }

  function buildPreviewHtml() {
    var tituloEl = byId('titulo');
    var title = renderHighlightedTitle(tituloEl && tituloEl.value ? tituloEl.value : 'Sem titulo');
    var content = editor() ? normalizePreviewContent(editor().innerHTML) : '<p><em>Sem conteudo.</em></p>';
    var category = currentCategoryLabel();
    var coverUrl = currentCoverUrl();

    return '' +
      '<style>' +
      'body{margin:0;background:#0a0e17;color:#e2e8f0;font-family:Rajdhani,Segoe UI,Tahoma,sans-serif;line-height:1.8;overflow-x:hidden;}' +
      '.wrap{max-width:920px;margin:0 auto;padding:48px 24px 80px;}' +
      '.article-header{margin-bottom:32px;}' +
      '.article-chip{display:inline-flex;align-items:center;padding:6px 12px;background:#00d4ff;color:#020617;font-size:12px;font-weight:900;border-radius:999px;text-transform:uppercase;margin-bottom:18px;}' +
      '.article-title{font-family:Orbitron,sans-serif;font-size:2.4rem;line-height:1.1;margin:0 0 24px;color:#fff;}' +
      '.article-title-accent{background:linear-gradient(90deg,#38bdf8 0%,#60a5fa 34%,#8b5cf6 68%,#22d3ee 100%);-webkit-background-clip:text;background-clip:text;color:transparent;}' +
      '.article-cover{width:100%;border-radius:20px;display:block;margin:0 0 28px;border:1px solid rgba(0,212,255,.18);}' +
      '.article-body{color:#cbd5e1;line-height:1.85;}' +
      '.article-body h2{font-family:Orbitron,sans-serif;font-size:2rem;font-weight:700;color:#00d4ff;margin:2.5rem 0 1.5rem;padding-bottom:.5rem;border-bottom:2px solid rgba(0,212,255,.3);}' +
      '.article-body h3{font-family:Orbitron,sans-serif;font-size:1.5rem;font-weight:600;color:#b829dd;margin:2rem 0 1rem;}' +
      '.article-body p{margin-bottom:1.5rem;font-size:1.05rem;color:#cbd5e1;}' +
      '.article-body ul,.article-body ol{margin:0 0 1.5rem;}' +
      '.article-body ul{list-style:none;padding-left:.35rem;}' +
      '.article-body ul li{position:relative;padding-left:1.35rem;}' +
      '.article-body ul li::before{content:"✦";position:absolute;left:0;top:.03rem;color:#22d3ee;font-size:.85em;}' +
      '.article-body ol{padding-left:1.45rem;}' +
      '.article-body li{margin-bottom:.75rem;font-size:1.05rem;color:#cbd5e1;}' +
      '.article-body blockquote{border-left:4px solid #00d4ff;padding:1.5rem;margin:2rem 0;background:rgba(0,212,255,.05);border-radius:0 12px 12px 0;color:#94a3b8;}' +
      '.article-body .content-grid-two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;margin:1.4rem 0;}' +
      '.article-body .content-block{border-radius:16px;border:1px solid rgba(34,211,238,.22);background:linear-gradient(145deg,rgba(15,23,42,.88),rgba(2,6,23,.84));padding:1rem 1.1rem;box-shadow:0 12px 28px rgba(2,6,23,.24);}' +
      '.article-body .content-block > *:last-child{margin-bottom:0 !important;}' +
      '.article-body .content-block-label{font-family:Orbitron,sans-serif;font-size:.95rem;font-weight:800;letter-spacing:.02em;text-transform:uppercase;color:#67e8f9;margin:0 0 .75rem;}' +
      '.article-body .content-block-note{border-color:rgba(34,211,238,.28);background:linear-gradient(145deg,rgba(6,78,92,.32),rgba(2,6,23,.84));}' +
      '.article-body .content-block-highlight{border-color:rgba(56,189,248,.3);background:linear-gradient(145deg,rgba(12,74,110,.35),rgba(2,6,23,.84));}' +
      '.article-body .content-block-success{border-color:rgba(16,185,129,.3);background:linear-gradient(145deg,rgba(6,78,59,.28),rgba(2,6,23,.84));}' +
      '.article-body .content-block-warning{border-color:rgba(245,158,11,.3);background:linear-gradient(145deg,rgba(120,53,15,.3),rgba(2,6,23,.84));}' +
      '.article-body .content-block-image{padding:0;overflow:hidden;}' +
      '.article-body .content-block-image .content-block-label{padding:1rem 1rem 0;margin-bottom:.35rem;}' +
      '.article-body .content-block-image figure{margin:0;max-width:100%;}' +
      '.article-body .content-block-video .aspect-video{position:relative;padding-top:56.25%;border-radius:12px;overflow:hidden;background:#0f172a;}' +
      '.article-body .content-block-video iframe{position:absolute;inset:0;width:100%;height:100%;margin:0;border-radius:0;min-height:0;}' +
      '.article-body .content-block-table{padding:0;overflow:hidden;}' +
      '.article-body .content-block-table .content-block-label{padding:1rem 1rem 0;}' +
      '.article-body .content-block-faq h3{margin-top:0;}' +
      '.article-body img{display:block;width:auto;max-width:100%;max-height:56vh;height:auto;border-radius:12px;margin:0 auto;border:1px solid rgba(0,212,255,.2);} .article-body figure{margin:2rem auto;max-width:min(100%,760px);display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;} .article-body figure.content-media-wide{max-width:100%;width:100%;display:block;}' +
      '.article-body figure{margin:2rem 0;}' +
      '.article-body figcaption{text-align:center;color:#64748b;font-size:.9rem;margin-top:-1rem;margin-bottom:0;font-style:italic;}' +
      '.article-body iframe,.article-body video{display:block;width:100%;min-height:320px;border:0;border-radius:16px;margin:2rem 0;}' +
      '.article-body table{width:100%;border-collapse:collapse;margin:1.5rem 0;background:rgba(15,23,42,.82);}' +
      '.article-body th,.article-body td{border:1px solid rgba(51,65,85,.8);padding:12px 14px;text-align:left;vertical-align:top;}' +
      '.article-body th{background:rgba(30,41,59,.55);}' +
      '@media (max-width: 760px){.article-body .content-grid-two{grid-template-columns:1fr;}}' +
      '</style>' +
      '<div class="wrap">' +
      '<div class="article-header">' +
      '<div class="article-chip">' + category + '</div>' +
      '<h1 class="article-title">' + title + '</h1>' +
      (coverUrl ? '<img class="article-cover" src="' + coverUrl.replace(/"/g, '&quot;') + '" alt="Capa do post">' : '') +
      '</div>' +
      '<div class="article-body">' + content + '</div>' +
      '</div>';
  }

  function openPreviewWindow() {
    syncEditorState();
    var previewWindow = window.open('about:blank', '_blank');
    if (!previewWindow) return;
    previewWindow.document.open();
    previewWindow.document.write('<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Preview do Post</title><link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@300;400;500;600;700&display=swap" rel="stylesheet"></head><body>' + buildPreviewHtml() + '</body></html>');
    previewWindow.document.close();
  }

  function parseJsonResponse(response) {
    return response.text().then(function (text) {
      try {
        return JSON.parse(text);
      } catch (error) {
        return { ok: false, error: 'Nao foi possivel interpretar a resposta do upload.', raw: text };
      }
    });
  }

  function uploadInlineImage(file) {
    if (!file) return;

    var csrf = document.querySelector('input[name="_csrf_token"]');
    var slug = byId('slug');
    var titulo = byId('titulo');
    var slugBase = slug && slug.value ? slug.value : (titulo && titulo.value ? titulo.value : '');
    if (!slugBase) {
      window.alert('Informe um titulo ou slug antes de enviar a imagem do conteudo.');
      return;
    }

    Promise.resolve(typeof window.__optimizeImageUploadFile === 'function'
      ? window.__optimizeImageUploadFile(file, { maxWidth: 1600, maxHeight: 1600, quality: 0.84 })
      : file)
      .catch(function () {
        return file;
      })
      .then(function (processedFile) {
        var data = new FormData();
        data.append('_csrf_token', csrf ? csrf.value : '');
        data.append('slug', slug && slug.value ? slug.value : '');
        data.append('titulo', titulo && titulo.value ? titulo.value : '');
        data.append('imagem', processedFile);

        return fetch('<?= htmlspecialchars(url('/admin/upload-post-imagem'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>', {
          method: 'POST',
          body: data,
          credentials: 'same-origin'
        });
      })
      .then(parseJsonResponse)
      .then(function (payload) {
        if (!payload || payload.ok !== true) {
          window.alert(payload && payload.error ? payload.error : 'Falha no upload da imagem.');
          return;
        }

        var alt = window.prompt('Texto alternativo da imagem (opcional):') || '';
        var legenda = window.prompt('Legenda da imagem (opcional):') || '';
        var html = '<figure><img src="' + String(payload.url || '').replace(/"/g, '&quot;') + '" alt="' + String(alt).replace(/"/g, '&quot;') + '">' + (legenda ? '<figcaption>' + legenda + '</figcaption>' : '') + '</figure>';
        insertHtmlAtCursor(html);
      })
      .catch(function () {
        window.alert('Falha ao enviar a imagem do conteudo.');
      });
  }

  function applyGeneratedContent() {
    var preview = byId('gerador-preview-content');
    if (!preview) return;
    var html = preview.textContent || '';
    if (!html.trim()) return;

    var mode = byId('gerador-apply-mode');
    var applyMode = mode ? mode.value : 'cursor';

    if (applyMode === 'replace') {
      replaceEditorHtml(html);
      return;
    }

    if (applyMode === 'append') {
      appendHtmlToEditor(html);
      return;
    }

    insertHtmlAtCursor(html);
  }

  function initEditorRuntime() {
    var root = editor();
    if (!root) return;

    ['keyup', 'mouseup', 'focus', 'input'].forEach(function (eventName) {
      root.addEventListener(eventName, function () {
        saveSelection();
        syncEditorState();
      });
    });

    var fileInput = byId('editorImageUpload');
    if (fileInput && fileInput.dataset.bound !== '1') {
      fileInput.dataset.bound = '1';
      fileInput.addEventListener('change', function () {
        var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
        uploadInlineImage(file);
        fileInput.value = '';
      });
    }

    window.enviarImagemDoEditor = function enviarImagemDoEditor() {
      saveSelection();
      if (fileInput) fileInput.click();
    };

    window.inserirImagem = function inserirImagem() {
      saveSelection();
      var url = window.prompt('Cole a URL/caminho da imagem:');
      if (!url) return;
      var alt = window.prompt('Texto alternativo (opcional):') || '';
      var legenda = window.prompt('Legenda (opcional):') || '';
      var html = '<figure><img src="' + String(url).replace(/"/g, '&quot;') + '" alt="' + String(alt).replace(/"/g, '&quot;') + '">' + (legenda ? '<figcaption>' + legenda + '</figcaption>' : '') + '</figure>';
      insertHtmlAtCursor(html);
    };

    window.inserirVideo = function inserirVideo() {
      saveSelection();
      var raw = window.prompt('Cole a URL do video, o link do YouTube ou o iframe completo:');
      if (!raw) return;

      var value = String(raw).trim();
      var html = '';

      if (/^<iframe[\s\S]*<\/iframe>$/i.test(value)) {
        html = value;
      } else {
        var match = value.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([A-Za-z0-9_-]{6,})/i);
        if (match) {
          html = '<div class="content-block content-block-video"><div class="content-block-label">Video</div><div class="aspect-video"><iframe src="https://www.youtube.com/embed/' + match[1] + '" title="Video incorporado" loading="lazy" allowfullscreen></iframe></div></div>';
        } else {
          html = '<video controls preload="metadata" style="width:100%;border-radius:16px;overflow:hidden;"><source src="' + value.replace(/"/g, '&quot;') + '"></video>';
        }
      }

      insertHtmlAtCursor(html);
    };

    window.abrirPreview = function abrirPreview() {
      openPreviewWindow();
    };

    window.aplicarGerador = function aplicarGerador() {
      applyGeneratedContent();
      if (typeof window.switchTab === 'function') {
        window.switchTab('visual');
      }
    };

    saveSelection();
    syncEditorState();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEditorRuntime);
    window.addEventListener('pageshow', function () { bindMediaPreviews(); });
  } else {
    initEditorRuntime();
  }
})();
</script>
