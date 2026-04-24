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

    wrapper.querySelectorAll('.en-audio-block').forEach(function (block) {
      ['data-audio-narracao', 'data-audio-ambiente'].forEach(function (attr) {
        if (block.hasAttribute(attr)) {
          block.setAttribute(attr, normalizeMediaUrl(block.getAttribute(attr)));
        }
      });
    });

    return wrapper.innerHTML;
  }

  function initPreviewAudioBlocks(root) {
    var scope = root || document;
    var blocks = Array.prototype.slice.call(scope.querySelectorAll('.en-audio-block'));
    var active = null;

    function stopActive() {
      if (!active) return;
      try { if (active.narracao) { active.narracao.pause(); active.narracao.currentTime = 0; } } catch (error) {}
      try { if (active.ambiente) { active.ambiente.pause(); active.ambiente.currentTime = 0; } } catch (error) {}
      if (active.button) {
        active.button.textContent = active.initialText || 'Ouvir narracao';
        active.button.removeAttribute('aria-pressed');
      }
      if (active.block) active.block.classList.remove('is-playing');
      active = null;
    }

    blocks.forEach(function (block) {
      var button = block.querySelector('[data-en-audio-toggle]');
      if (!button || button.dataset.previewAudioBound === '1') return;
      button.dataset.previewAudioBound = '1';

      var narracaoSrc = normalizeMediaUrl(block.getAttribute('data-audio-narracao') || '');
      var ambienteSrc = normalizeMediaUrl(block.getAttribute('data-audio-ambiente') || '');
      var initialText = button.textContent || 'Ouvir narracao';
      if (!narracaoSrc && !ambienteSrc) {
        button.textContent = 'Audio indisponivel';
        button.disabled = true;
        return;
      }

      var narracao = narracaoSrc ? new Audio(narracaoSrc) : null;
      var ambiente = ambienteSrc ? new Audio(ambienteSrc) : null;
      if (ambiente) ambiente.loop = true;

      if (narracao) {
        narracao.addEventListener('ended', function () {
          try { if (ambiente) { ambiente.pause(); ambiente.currentTime = 0; } } catch (error) {}
          button.textContent = initialText;
          button.removeAttribute('aria-pressed');
          block.classList.remove('is-playing');
          active = null;
        });
      }

      button.addEventListener('click', function () {
        var isCurrent = active && active.block === block;
        var isPlaying = isCurrent && ((narracao && !narracao.paused) || (ambiente && !ambiente.paused));
        if (isPlaying) {
          stopActive();
          return;
        }
        if (active && !isCurrent) stopActive();

        Promise.resolve()
          .then(function () {
            if (narracao) narracao.volume = 1;
            if (ambiente) ambiente.volume = narracao ? 0.12 : 0.35;
            if (narracao) {
              return narracao.play().then(function () {
                if (ambiente) ambiente.play().catch(function () {});
              });
            }
            return ambiente ? ambiente.play() : null;
          })
          .then(function () {
            button.textContent = 'Pausar';
            button.setAttribute('aria-pressed', 'true');
            block.classList.add('is-playing');
            active = { block: block, button: button, narracao: narracao, ambiente: ambiente, initialText: initialText };
          })
          .catch(function () {
            stopActive();
            button.textContent = 'Audio indisponivel';
          });
      });
    });
  }

  function initPreviewMedia(root) {
    var scope = root || document;
    initPreviewAudioBlocks(scope);
    scope.querySelectorAll('video').forEach(function (video) {
      try { video.load(); } catch (error) {}
    });
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function indentMarkup(value, prefix) {
    return String(value || '')
      .split('\n')
      .map(function (line) {
        return line ? String(prefix || '') + line : line;
      })
      .join('\n');
  }

  function buildImageFigureHtml(url, alt, legenda) {
    var imageUrl = String(url || '').replace(/"/g, '&quot;');
    var imageAlt = escapeHtml(alt || '');
    var caption = String(legenda || '').trim();
    var lines = [
      '<figure class="article-figure content-block-image">',
      '  <img src="' + imageUrl + '" alt="' + imageAlt + '">'
    ];
    if (caption) {
      lines.push('  <figcaption>' + escapeHtml(caption) + '</figcaption>');
    }
    lines.push('</figure>');
    return '\n' + lines.join('\n') + '\n';
  }

  function buildVideoFigureHtml(value, legenda) {
    var raw = String(value || '').trim();
    var caption = String(legenda || '').trim();
    if (!raw) return '';

    var lines = [
      '<figure class="content-block content-block-video">',
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
      return '\n' + lines.join('\n') + '\n';
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
      return '\n' + lines.join('\n') + '\n';
    }

    lines.push('  <video controls preload="metadata">');
    lines.push('    <source src="' + raw.replace(/"/g, '&quot;') + '">');
    lines.push('  </video>');
    if (caption) {
      lines.push('  <figcaption>' + escapeHtml(caption) + '</figcaption>');
    }
    lines.push('</figure>');
    return '\n' + lines.join('\n') + '\n';
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
      addKnownExistingUpload(url || '');
      syncMediaPreview(targetId);
      renderChecklistRuntime();
    };
  }
  function currentCategoryLabel() {
    var select = byId('categoria_post_id');
    if (!select || select.selectedIndex < 0) return 'Geral';
    var option = select.options[select.selectedIndex];
    return option && option.textContent ? option.textContent.trim() : 'Geral';
  }

  function checklistRoot() {
    return byId('postPublicationChecklist');
  }

  function checklistRuntimeMeta() {
    if (!window.__postChecklistRuntimeMeta) {
      var node = byId('postChecklistRuntimeData');
      var payload = {};
      if (node && node.textContent) {
        try {
          payload = JSON.parse(node.textContent);
        } catch (error) {
          payload = {};
        }
      }
      payload.slug = String(payload.slug || '').trim();
      payload.managed_files = Array.isArray(payload.managed_files) ? payload.managed_files.slice() : [];
      payload.known_existing_uploads = Array.isArray(payload.known_existing_uploads) ? payload.known_existing_uploads.slice() : [];
      window.__postChecklistRuntimeMeta = payload;
    }
    return window.__postChecklistRuntimeMeta;
  }

  function currentPostStatus() {
    var status = byId('status');
    return status ? String(status.value || 'rascunho').trim() : 'rascunho';
  }

  function hasValue(id) {
    var node = byId(id);
    return !!(node && String(node.value || '').trim() !== '');
  }

  function currentEditorText() {
    var root = editor();
    return root ? String(root.innerText || '').replace(/\s+/g, ' ').trim() : '';
  }

  function currentSlugValue() {
    var slug = byId('slug');
    var titulo = byId('titulo');
    var value = slug && String(slug.value || '').trim() !== '' ? slug.value : (titulo ? titulo.value : '');
    value = String(value || '').toLowerCase().trim();
    if (!value) return '';
    value = value.normalize ? value.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : value;
    value = value.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 190);
    return value;
  }

  function normalizeChecklistAssetReference(value) {
    var raw = String(value || '').trim();
    if (!raw) return '';
    if (/^(data:|blob:)/i.test(raw)) return '';
    if (/^https?:\/\//i.test(raw)) {
      try {
        raw = new URL(raw, window.location.origin).pathname || '';
      } catch (error) {
        return '';
      }
    }
    raw = raw.replace(/\\/g, '/').replace(/^\/+/, '');
    return raw.indexOf('uploads/') === 0 ? raw : '';
  }

  function collectChecklistReferencesFromHtml(html) {
    var wrapper = document.createElement('div');
    wrapper.innerHTML = String(html || '');
    var refs = {};

    wrapper.querySelectorAll('[src],[href],[poster]').forEach(function (node) {
      ['src', 'href', 'poster'].forEach(function (attr) {
        if (!node.hasAttribute(attr)) return;
        var normalized = normalizeChecklistAssetReference(node.getAttribute(attr));
        if (normalized) refs[normalized] = true;
      });
    });

    wrapper.querySelectorAll('[data-audio-narracao],[data-audio-ambiente]').forEach(function (node) {
      ['data-audio-narracao', 'data-audio-ambiente'].forEach(function (attr) {
        if (!node.hasAttribute(attr)) return;
        var normalized = normalizeChecklistAssetReference(node.getAttribute(attr));
        if (normalized) refs[normalized] = true;
      });
    });

    return Object.keys(refs);
  }

  function collectChecklistMissingAltImages(html) {
    var wrapper = document.createElement('div');
    wrapper.innerHTML = String(html || '');
    var items = [];
    Array.prototype.slice.call(wrapper.querySelectorAll('img')).forEach(function (img, index) {
      var alt = String(img.getAttribute('alt') || '').trim();
      if (alt) return;
      items.push({
        index: index + 1,
        src: normalizeChecklistAssetReference(img.getAttribute('src') || '')
      });
    });
    return items;
  }

  function addKnownExistingUpload(path) {
    var normalized = normalizeChecklistAssetReference(path);
    if (!normalized) return;
    var meta = checklistRuntimeMeta();
    if (meta.known_existing_uploads.indexOf(normalized) === -1) {
      meta.known_existing_uploads.push(normalized);
    }
  }

  function addManagedUpload(path) {
    var normalized = normalizeChecklistAssetReference(path);
    if (!normalized) return;
    var meta = checklistRuntimeMeta();
    if (meta.managed_files.indexOf(normalized) === -1) {
      meta.managed_files.push(normalized);
    }
    addKnownExistingUpload(normalized);
  }

  function collectTechnicalChecklistState(slug, html) {
    var meta = checklistRuntimeMeta();
    var referenced = {};
    var protectedFiles = {};
    var coverPath = normalizeChecklistAssetReference(byId('imagem_capa') ? byId('imagem_capa').value : '');
    var thumbPath = normalizeChecklistAssetReference(byId('imagem_thumb') ? byId('imagem_thumb').value : '');
    var currentSlug = String(slug || meta.slug || '').trim();
    var managedFiles = Array.isArray(meta.managed_files) ? meta.managed_files.slice() : [];
    var knownExisting = Array.isArray(meta.known_existing_uploads) ? meta.known_existing_uploads.slice() : [];

    if (coverPath) {
      protectedFiles[coverPath] = true;
      addKnownExistingUpload(coverPath);
    }
    if (thumbPath) {
      protectedFiles[thumbPath] = true;
      addKnownExistingUpload(thumbPath);
    }

    collectChecklistReferencesFromHtml(html).forEach(function (path) {
      referenced[path] = true;
    });

    var missingMedia = [];
    Object.keys(referenced).forEach(function (path) {
      var isKnown = knownExisting.indexOf(path) !== -1;
      var isCurrentPostFile = currentSlug && path.indexOf('uploads/posts/' + currentSlug + '/') === 0;
      var isManaged = managedFiles.indexOf(path) !== -1;
      if (!isKnown && !(isCurrentPostFile && isManaged)) {
        missingMedia.push(path);
      }
    });

    var orphanFiles = [];
    managedFiles.forEach(function (path) {
      if (protectedFiles[path] || referenced[path]) return;
      orphanFiles.push(path);
    });

    return {
      missingMedia: missingMedia,
      missingAltImages: collectChecklistMissingAltImages(html),
      orphanFiles: orphanFiles
    };
  }

  function checklistItem(status, title, message, group) {
    return { status: status, title: title, message: message, group: group || 'editorial' };
  }

  function buildChecklistRuntimeState() {
    var items = [];
    var stats = { success: 0, warning: 0, error: 0 };
    var status = byId('status');
    var nextStep = byId('proximo_post_id');
    var dataPublicacao = byId('data_publicacao');
    var seoTitle = byId('seo_title');
    var seoDescription = byId('seo_description');
    var titulo = byId('titulo');
    var categoria = byId('categoria_post_id');
    var cover = byId('imagem_capa');
    var thumb = byId('imagem_thumb');
    var currentStatus = currentPostStatus();
    var slug = currentSlugValue();
    var contentText = currentEditorText();
    var contentHtml = editor() ? String(editor().innerHTML || '') : '';
    var technicalState = collectTechnicalChecklistState(slug, contentHtml);

    function pushItem(item) {
      items.push(item);
      if (Object.prototype.hasOwnProperty.call(stats, item.status)) {
        stats[item.status] += 1;
      }
    }

    if (!titulo || String(titulo.value || '').trim() === '') {
      pushItem(checklistItem('error', 'Titulo', 'Informe o titulo do post.'));
    } else if (String(titulo.value || '').trim().length > 200) {
      pushItem(checklistItem('error', 'Titulo', 'O titulo passou do limite de 200 caracteres.'));
    } else {
      pushItem(checklistItem('success', 'Titulo', 'Titulo preenchido e pronto para publicacao.'));
    }

    pushItem(slug ? checklistItem('success', 'Slug', 'Slug atual: ' + slug) : checklistItem('error', 'Slug', 'Nao foi possivel gerar um slug valido.'));

    pushItem(contentText ? checklistItem('success', 'Conteudo', 'Conteudo preenchido com ' + contentText.length + ' caracteres visiveis.') : checklistItem('error', 'Conteudo', 'O post ainda nao tem conteudo editorial salvo.'));

    if (!categoria || !categoria.value || categoria.value === '0') {
      pushItem(checklistItem('error', 'Categoria', 'Selecione uma categoria valida para o post.'));
    } else {
      pushItem(checklistItem('success', 'Categoria', 'Categoria selecionada: ' + currentCategoryLabel() + '.'));
    }

    if (!status || ['publicado', 'rascunho', 'agendado'].indexOf(currentStatus) === -1) {
      pushItem(checklistItem('error', 'Status', 'Selecione um status valido.'));
    } else if (currentStatus === 'publicado') {
      pushItem(checklistItem('success', 'Status', 'Post marcado para publicacao imediata.'));
    } else if (currentStatus === 'agendado') {
      pushItem(checklistItem('warning', 'Status', 'Post em modo agendado. Revise a data antes de publicar.'));
    } else {
      pushItem(checklistItem('warning', 'Status', 'Post permanece em rascunho ate a publicacao.'));
    }

    var dataValue = dataPublicacao ? String(dataPublicacao.value || '').trim() : '';
    if (!dataValue) {
      pushItem(checklistItem('error', 'Data de publicacao', 'Informe uma data de publicacao valida.'));
    } else {
      var scheduledAt = new Date(dataValue);
      var hasValidDate = !isNaN(scheduledAt.getTime());
      if (!hasValidDate) {
        pushItem(checklistItem('error', 'Data de publicacao', 'Informe uma data de publicacao valida.'));
      } else if (currentStatus === 'agendado' && scheduledAt.getTime() <= Date.now()) {
        pushItem(checklistItem('error', 'Data de publicacao', 'Posts agendados precisam de uma data futura.'));
      } else if (currentStatus === 'publicado' && scheduledAt.getTime() > Date.now()) {
        pushItem(checklistItem('warning', 'Data de publicacao', 'A data esta no futuro mesmo com status publicado.'));
      } else {
        pushItem(checklistItem('success', 'Data de publicacao', 'Data configurada: ' + dataValue.replace('T', ' ') + '.'));
      }
    }

    pushItem(hasValue('imagem_capa') ? checklistItem('success', 'Imagem de capa', 'Capa pronta para o front.') : checklistItem('warning', 'Imagem de capa', 'O post ainda esta sem capa.'));
    pushItem(hasValue('imagem_thumb') ? checklistItem('success', 'Thumbnail', 'Thumb pronta para cards e listagens.') : checklistItem('warning', 'Thumbnail', 'O post ainda esta sem thumb.'));

    var seoTitleValue = seoTitle ? String(seoTitle.value || '').trim() : '';
    var seoDescriptionValue = seoDescription ? String(seoDescription.value || '').trim() : '';
    if (!seoTitleValue && !seoDescriptionValue) {
      pushItem(checklistItem('warning', 'SEO', 'SEO title e description ainda estao vazios.'));
    } else if (seoTitleValue.length > 200 || seoDescriptionValue.length > 300) {
      pushItem(checklistItem('error', 'SEO', 'Os campos de SEO passaram do limite permitido.'));
    } else {
      var seoParts = [];
      if (seoTitleValue) seoParts.push('title ok');
      if (seoDescriptionValue) seoParts.push('description ok');
      pushItem(checklistItem('success', 'SEO', 'Campos prontos: ' + seoParts.join(' e ') + '.'));
    }

    if (!nextStep || nextStep.disabled) {
      pushItem(checklistItem('warning', 'Proximo passo', 'O banco atual ainda nao suporta CTA dedicado de proximo passo.'));
    } else if (!nextStep.value || nextStep.value === '0') {
      pushItem(checklistItem('warning', 'Proximo passo', 'Nenhum post recomendado foi selecionado.'));
    } else {
      var nextOption = nextStep.options[nextStep.selectedIndex];
      var nextLabel = nextOption && nextOption.textContent ? nextOption.textContent.trim() : 'post publicado';
      pushItem(checklistItem('success', 'Proximo passo', 'CTA aponta para: ' + nextLabel + '.'));
    }

    if (technicalState.missingMedia.length > 0) {
      pushItem(checklistItem('error', 'Midia referenciada', technicalState.missingMedia.length + ' arquivo(s) citado(s) no HTML nao foram encontrados no conjunto atual do post.', 'tecnico'));
    } else {
      pushItem(checklistItem('success', 'Midia referenciada', 'Todas as midias citadas no HTML foram reconhecidas.', 'tecnico'));
    }

    if (technicalState.missingAltImages.length > 0) {
      pushItem(checklistItem('warning', 'Alt de imagens', technicalState.missingAltImages.length + ' imagem(ns) do HTML estao sem alt preenchido.', 'tecnico'));
    } else {
      pushItem(checklistItem('success', 'Alt de imagens', 'Imagens do HTML com alt preenchido ou sem imagens inline pendentes.', 'tecnico'));
    }

    if (technicalState.orphanFiles.length > 0) {
      pushItem(checklistItem('warning', 'Arquivos soltos', technicalState.orphanFiles.length + ' arquivo(s) da pasta do post nao aparecem mais no HTML salvo.', 'tecnico'));
    } else {
      pushItem(checklistItem('success', 'Arquivos soltos', 'Nenhum arquivo solto detectado na pasta do post.', 'tecnico'));
    }

    var overall = 'success';
    var headline = 'Pronto para publicar.';
    if (stats.error > 0) {
      overall = 'error';
      headline = 'Publicacao bloqueada ate corrigir os erros criticos.';
    } else if (stats.warning > 0) {
      overall = 'warning';
      headline = 'Post utilizavel, mas ainda com alertas editoriais.';
    }

    return {
      status: overall,
      headline: headline,
      stats: stats,
      items: items,
      currentPostStatus: currentStatus,
      technicalState: technicalState
    };
  }

  function updateSubmitGuards(state) {
    var submitButtons = document.querySelectorAll('[data-submit-role]');
    var message = byId('postSubmitGuardMessage');
    var shouldBlock = state.status === 'error' && ['publicado', 'agendado'].indexOf(state.currentPostStatus) !== -1;

    submitButtons.forEach(function (button) {
      button.disabled = shouldBlock;
      button.classList.toggle('is-disabled-by-checklist', shouldBlock);
      button.setAttribute('aria-disabled', shouldBlock ? 'true' : 'false');
      if (shouldBlock) {
        button.setAttribute('title', 'Corrija os erros criticos do checklist antes de publicar.');
      } else if (button.dataset.submitRole === 'toolbar') {
        button.setAttribute('title', 'Salvar post');
      } else {
        button.removeAttribute('title');
      }
    });

    if (!message) return;
    if (shouldBlock) {
      message.textContent = state.currentPostStatus === 'agendado'
        ? 'Agendamento bloqueado: corrija os erros criticos do checklist antes de continuar.'
        : 'Publicacao bloqueada: corrija os erros criticos do checklist antes de continuar.';
      message.classList.remove('hidden');
      return;
    }

    if (state.status === 'error') {
      message.textContent = 'Os erros criticos ainda existem, mas voce pode continuar salvando como rascunho.';
      message.classList.remove('hidden');
      return;
    }

    if (state.status === 'warning' && ['publicado', 'agendado'].indexOf(state.currentPostStatus) !== -1) {
      message.textContent = 'Publicacao liberada com alertas. Revise os avisos antes de concluir.';
      message.classList.remove('hidden');
      return;
    }

    message.textContent = '';
    message.classList.add('hidden');
  }

  function renderChecklistRuntime() {
    var root = checklistRoot();
    if (!root) return;

    var state = buildChecklistRuntimeState();
    root.classList.remove('is-success', 'is-warning', 'is-error');
    root.classList.add(state.status === 'error' ? 'is-error' : (state.status === 'warning' ? 'is-warning' : 'is-success'));

    var headline = byId('postChecklistHeadline');
    var stateLabel = byId('postChecklistState');
    var stateHint = byId('postChecklistHint');
    var okCount = byId('postChecklistOkCount');
    var warningCount = byId('postChecklistWarningCount');
    var errorCount = byId('postChecklistErrorCount');
    var list = byId('postChecklistList');

    if (headline) headline.textContent = state.headline;
    if (stateLabel) stateLabel.textContent = state.status === 'error' ? 'Bloqueado' : (state.status === 'warning' ? 'Atencao' : 'Pronto');
    if (stateHint) stateHint.textContent = state.status === 'error'
      ? 'Erros criticos exigem correcao antes de publicar ou agendar.'
      : (state.status === 'warning'
        ? 'O post esta utilizavel, mas ainda merece uma revisao final.'
        : 'Tudo alinhado para seguir com a publicacao.');
    if (okCount) okCount.textContent = String(state.stats.success);
    if (warningCount) warningCount.textContent = String(state.stats.warning);
    if (errorCount) errorCount.textContent = String(state.stats.error);
    updateSubmitGuards(state);

    if (!list) return;
    list.innerHTML = state.items.map(function (item) {
      var icon = item.status === 'error' ? '!' : (item.status === 'warning' ? '!' : 'OK');
      var itemClass = item.status === 'error' ? 'is-error' : (item.status === 'warning' ? 'is-warning' : 'is-success');
      var itemGroup = item.group === 'tecnico' ? 'Tecnico' : 'Editorial';
      return '' +
        '<article class="post-checklist-item ' + itemClass + '">' +
          '<div class="post-checklist-item-icon" aria-hidden="true">' + icon + '</div>' +
          '<div class="post-checklist-item-copy">' +
            '<div class="post-checklist-item-meta"><span class="post-checklist-item-group">' + escapeHtml(itemGroup) + '</span></div>' +
            '<div class="post-checklist-item-title">' + escapeHtml(item.title) + '</div>' +
            '<div class="post-checklist-item-text">' + escapeHtml(item.message) + '</div>' +
          '</div>' +
        '</article>';
    }).join('');
  }

  function bindChecklistRuntime() {
    ['titulo', 'slug', 'categoria_post_id', 'status', 'data_publicacao', 'imagem_capa', 'imagem_thumb', 'seo_title', 'seo_description', 'proximo_post_id'].forEach(function (id) {
      var field = byId(id);
      if (!field || field.dataset.checklistBound === '1') return;
      field.dataset.checklistBound = '1';
      field.addEventListener('input', renderChecklistRuntime);
      field.addEventListener('change', renderChecklistRuntime);
    });
    renderChecklistRuntime();
  }

  function bindChecklistSubmitGuard() {
    var form = byId('postForm');
    if (!form || form.dataset.checklistSubmitBound === '1') return;
    form.dataset.checklistSubmitBound = '1';
    form.addEventListener('submit', function (event) {
      var state = buildChecklistRuntimeState();
      var shouldBlock = state.status === 'error' && ['publicado', 'agendado'].indexOf(state.currentPostStatus) !== -1;
      updateSubmitGuards(state);
      if (!shouldBlock) return;
      event.preventDefault();
      var message = byId('postSubmitGuardMessage');
      if (message) {
        message.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
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
      '.article-body .en-audio-block{background:linear-gradient(180deg,rgba(18,16,24,.96),rgba(8,10,18,.96));border:1px solid rgba(103,232,249,.22);border-radius:16px;padding:18px 18px 16px;margin:18px 0 22px;box-shadow:0 0 22px rgba(0,0,0,.32);}' +
      '.article-body .en-audio-header{display:flex;align-items:center;gap:10px;margin-bottom:8px;color:rgba(248,250,252,.95);}' +
      '.article-body .en-audio-title{font-family:Orbitron,sans-serif;font-weight:900;letter-spacing:.06em;text-transform:uppercase;font-size:.95rem;color:rgba(165,243,252,.95);}' +
      '.article-body .en-audio-subtitle{margin:0 0 14px;color:rgba(226,232,240,.92);font-style:italic;line-height:1.6;}' +
      '.article-body .en-audio-button{display:inline-flex;align-items:center;gap:10px;border-radius:10px;border:1px solid rgba(251,191,36,.28);background:linear-gradient(180deg,rgba(59,36,28,.92),rgba(22,13,12,.92));color:rgba(255,237,213,.95);padding:10px 16px;cursor:pointer;font-weight:800;font-size:.95rem;}' +
      '.article-body .en-audio-block.is-playing{border-color:rgba(34,211,238,.36);box-shadow:0 0 26px rgba(34,211,238,.12),0 0 22px rgba(0,0,0,.34);}' +
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
    previewWindow.setTimeout(function () {
      initPreviewMedia(previewWindow.document);
    }, 50);
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

        addManagedUpload(payload.path || '');
        var alt = window.prompt('Texto alternativo da imagem (opcional):') || '';
        var legenda = window.prompt('Legenda da imagem (opcional):') || '';
        var html = buildImageFigureHtml(payload.url || '', alt, legenda);
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
        renderChecklistRuntime();
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
      var html = buildImageFigureHtml(url, alt, legenda);
      insertHtmlAtCursor(html);
    };

    window.inserirVideo = function inserirVideo() {
      saveSelection();
      var raw = window.prompt('Cole a URL do video, o link do YouTube ou o iframe completo:');
      if (!raw) return;
      var legenda = window.prompt('Legenda do video (opcional):') || '';

      var html = buildVideoFigureHtml(raw, legenda);
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
    bindChecklistRuntime();
    bindChecklistSubmitGuard();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEditorRuntime);
    window.addEventListener('pageshow', function () { bindMediaPreviews(); });
  } else {
    initEditorRuntime();
  }
})();
</script>
