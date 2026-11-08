/** Stories e destaques: revisão local, execução explícita e recuperação sem reenvio. */
(function () {
  'use strict';

  function freezeCopy(value) {
    var copy = JSON.parse(JSON.stringify(value));
    function freeze(node) {
      if (node && typeof node === 'object') {
        Object.values(node).forEach(freeze);
        Object.freeze(node);
      }
      return node;
    }
    return freeze(copy);
  }

  // Independent of the DOM so interruption/review behavior can be exercised offline.
  class Operation {
    constructor(request, storage, makeId, notify) {
      this.request = request;
      this.storage = storage;
      this.makeId = makeId;
      this.notify = notify || function () {};
      this.key = 'ih:estrategia_nerd:pending';
      this.id = storage.getItem(this.key) || '';
      this.attempted = Boolean(this.id);
      this.busy = false;
      this.payload = null;
      this.job = null;
      this.signature = '';
    }
    review(content) {
      if (this.busy || this.attempted) throw new Error('Confira a operação em andamento antes de iniciar outra.');
      var signature = JSON.stringify(content);
      if (signature !== this.signature) this.id = this.makeId();
      this.signature = signature;
      this.payload = freezeCopy(Object.assign({}, content, { job_id: this.id }));
      return this.payload;
    }
    async recover(id) {
      if (this.busy) throw new Error('Aguarde a etapa em andamento.');
      this.id = id || this.id;
      if (!/^[a-f0-9]{32}$/.test(this.id)) throw new Error('Identificador da operação inválido.');
      this.attempted = true;
      this.storage.setItem(this.key, this.id);
      this.busy = true;
      try {
        var response = await this.request('/job?id=' + encodeURIComponent(this.id));
        this.job = response.job;
        if (!this.job || this.job.id !== this.id) throw new Error('Estado da operação não confirmado.');
        this.notify(this.job);
        return this.job;
      } catch (error) {
        if (error.code === 'JOB_NOT_FOUND') {
          this.storage.removeItem(this.key);
          this.id = ''; this.attempted = false; this.payload = null; this.job = null; this.signature = '';
        }
        throw error;
      } finally {
        this.busy = false;
      }
    }
    async publish() {
      if (this.busy) throw new Error('Já existe uma etapa em andamento.');
      if (!this.payload && !this.job) throw new Error('Revise a operação antes de publicar.');
      if (this.job && this.job.status !== 'ready') throw new Error('Esta operação precisa ser conferida antes de continuar.');
      // Persist only the identifier, before any request. Never save credentials or links here.
      this.storage.setItem(this.key, this.id);
      this.attempted = true;
      this.busy = true;
      try {
        if (!this.job) {
          var prepared = await this.request('/prepare', this.payload);
          this.job = prepared.job;
        }
        if (!this.job || this.job.id !== this.id) throw new Error('Operação preparada não corresponde à revisão.');
        this.notify(this.job);
        while (this.job.status === 'ready') {
          var result = await this.request('/step', { job_id: this.id });
          if (!result.job || result.job.id !== this.id) throw new Error('Resultado da etapa não confirmado.');
          this.job = result.job;
          this.notify(this.job);
        }
        return this.job;
      } catch (error) {
        // A lost response never authorizes another mutation. Refresh local state once only.
        this.job = null;
        try {
          var recovered = await this.request('/job?id=' + encodeURIComponent(this.id));
          if (recovered.job && recovered.job.id === this.id) {
            this.job = recovered.job;
            this.notify(this.job);
          }
        } catch (_) { /* Keep the same ID blocked until an explicit local check. */ }
        throw error;
      } finally {
        this.busy = false;
      }
    }
  }

  class Wizard {
    constructor() { this.step = 1; }
    go(step, options) {
      options = options || {};
      if (options.busy || options.uploads) throw new Error('Aguarde o envio terminar antes de mudar de etapa.');
      if (step === 3 && !options.reviewed) throw new Error('Revise a montagem antes de continuar.');
      if (![1, 2, 3].includes(step)) throw new Error('Etapa inválida.');
      this.step = step;
    }
  }
  function canPublish(connection) { return connection.runtime_ready === true && connection.session_stored === true; }
  function progressLabel(job) {
    if (job.status === 'completed') return 'Stories e destaque publicados';
    if (job.status === 'unknown') return 'Confira o resultado no Instagram antes de continuar';
    var total = (job.items || []).filter(function (item) { return item.asset; }).length;
    if (job.next_action === 'upload') return 'Publicando story ' + Math.min(job.completed_steps + 1, total) + ' de ' + total;
    return {create: 'Criando o destaque', add: 'Adicionando os stories ao destaque', rename: 'Atualizando o nome do destaque', cover: 'Atualizando a capa'}[job.next_action] || 'Conferindo publicação';
  }
  if (typeof module !== 'undefined' && module.exports) module.exports = { Operation: Operation, freezeCopy: freezeCopy, Wizard: Wizard, canPublish: canPublish, progressLabel: progressLabel };
  if (typeof document === 'undefined') return;

  var config = window.IH_CONFIG || {};
  var endpoint = config.endpoint || '/admin/instagram-destaques';
  var byId = function (id) { return document.getElementById(id); };
  var root = document.querySelector('.ih-container');
  if (!root) return;
  var state = { items: [], active: 0, uploads: 0, modal: null, returnFocus: null, catalogBusy: false, cover: '', coverUrl: '' };
  var wizard = new Wizard();
  var connection = config.status || {};
  var reviewValid = false;
  var currentMode = 'new';
  var modeDrafts = {new: {title:'', cover:'', coverUrl:''}, existing: {title:'', cover:'', coverUrl:''}};
  var dropZone = byId('ihDropZone');
  var fileInput = byId('ihFileInput');
  var storyList = byId('ihStoryList');
  var btnReview = byId('ihBtnReview');
  var progressModal = byId('ihProgressModal');
  var reconcileModal = byId('ihReconcileModal');
  var notice = byId('ihOperationNotice');
  var noticeText = byId('ihOperationMessage');
  var btnRecover = byId('ihBtnRecover');
  var btnContinue = byId('ihBtnContinue');
  var btnReconcile = byId('ihBtnReconcile');
  var btnNew = byId('ihBtnNewOperation');
  var operation;

  async function request(path, body) {
    var options = { credentials: 'same-origin', headers: { 'X-CSRF-Token': config.csrfToken || '' } };
    if (body !== undefined) {
      options.method = 'POST';
      if (body instanceof FormData) options.body = body;
      else {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
      }
    }
    var response = await fetch(endpoint + path, options);
    var data;
    try { data = await response.json(); } catch (_) { throw new Error('O servidor não retornou uma resposta válida.'); }
    if (!response.ok || data.ok !== true) {
      var error = new Error(data.message || 'Não foi possível concluir a solicitação.');
      error.code = data.code;
      throw error;
    }
    return data;
  }
  function message(text) {
    notice.classList.remove('hidden');
    noticeText.textContent = text;
  }
  function newId() {
    var bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    return Array.from(bytes, function (byte) { return byte.toString(16).padStart(2, '0'); }).join('');
  }
  try { operation = new Operation(request, window.sessionStorage, newId, showJob); }
  catch (_) {
    message('O armazenamento desta aba está indisponível. Permita o armazenamento do site antes de publicar.');
    root.querySelectorAll('input, button, select').forEach(function (el) { el.disabled = true; });
    return;
  }

  function editable() { return wizard.step === 2 && !operation.attempted && !operation.busy && !state.modal && !state.uploads; }
  function showStep(step, reviewed) {
    try { wizard.go(step, {busy: operation.busy, uploads:state.uploads, reviewed:reviewed}); }
    catch (error) { message(error.message); return; }
    root.querySelectorAll('[data-ih-panel]').forEach(function (panel) { panel.classList.toggle('hidden', Number(panel.dataset.ihPanel) !== step); });
    root.querySelectorAll('[data-ih-step]').forEach(function (button) {
      if (Number(button.dataset.ihStep) === step) button.setAttribute('aria-current','step');
      else button.removeAttribute('aria-current');
    });
    if (step === 2) reviewValid = false;
    syncControls();
    var heading = root.querySelector('[data-ih-panel="' + step + '"] h2');
    heading.focus();
  }
  function renderConnection() {
    byId('ihConnectionStatus').textContent = connection.session_stored ? 'Configuração encontrada. A conta será verificada ao publicar.' : 'Conta ainda não configurada.';
    if (!connection.runtime_ready) byId('ihConnectionStatus').textContent = 'A conexão está indisponível neste computador. Você pode preparar a montagem enquanto isso.';
    byId('ihPublishRequirement').classList.toggle('hidden', canPublish(connection));
    syncControls();
  }
  root.querySelectorAll('[data-ih-step]').forEach(function (button) { button.addEventListener('click', function () {
    var step = Number(button.dataset.ihStep);
    if (step === 3) { if (reviewValid) showStep(3, true); else btnReview.click(); }
    else showStep(step);
  }); });
  byId('ihStartBuilding').addEventListener('click', function () { showStep(2); });
  byId('ihBackSetup').addEventListener('click', function () { showStep(1); });
  byId('ihEditDraft').addEventListener('click', function () { if (!operation.attempted) showStep(2); });
  byId('ihGoConnect').addEventListener('click', function () { showStep(1); byId('ihAuthHelp').open = true; });
  byId('ihRefreshStatus').addEventListener('click', async function () {
    this.disabled = true;
    try { connection = await request('/status'); renderConnection(); }
    catch (_) { connection = {}; renderConnection(); message('Não foi possível conferir a configuração. Sua montagem continua nesta aba.'); }
    finally { this.disabled = false; }
  });
  byId('ihCopyCommand').addEventListener('click', async function () {
    try { await navigator.clipboard.writeText(byId('ihAuthCommand').textContent); byId('ihCopyStatus').textContent = 'Comando copiado. Cole no PowerShell e pressione Enter.'; }
    catch (_) { byId('ihCopyStatus').textContent = 'Não foi possível copiar automaticamente. Selecione o comando acima e copie.'; }
  });
  byId('ihTogglePreview').addEventListener('click', function () {
    var expanded = this.getAttribute('aria-expanded') !== 'true';
    this.setAttribute('aria-expanded', String(expanded));
    this.textContent = expanded ? 'Fechar prévia' : 'Ver como ficará';
    byId('ihPreviewBody').classList.toggle('is-open', expanded);
  });
  function syncControls() {
    var locked = operation.attempted || operation.busy || state.uploads > 0;
    root.querySelectorAll('[data-ih-editor] input, [data-ih-editor] button, [data-ih-editor] select').forEach(function (el) { el.disabled = locked; });
    dropZone.setAttribute('aria-disabled', String(locked));
    dropZone.classList.toggle('ih-disabled', locked);
    btnReview.disabled = locked || !state.items.length;
    byId('ihUploadStatus').textContent = state.uploads ? 'Enviando imagens. Aguarde antes de revisar.' : '';
    byId('ihBtnRefreshCatalog').disabled = locked || state.catalogBusy || !canPublish(connection);
    byId('ihReviewHint').textContent = state.uploads ? 'Aguarde as imagens terminarem de enviar.' : state.items.length ? 'Você vai conferir tudo antes de publicar.' : 'Adicione uma imagem para continuar.';
    byId('ihEditDraft').disabled = operation.attempted || operation.busy;
    byId('ihBtnConfirmPublish').disabled = !canPublish(connection) || !reviewValid || operation.busy || Boolean(state.uploads) || (operation.job && operation.job.status !== 'ready');
    root.querySelectorAll('[data-ih-step]').forEach(function (button) { button.disabled = operation.busy || Boolean(state.uploads) || (button.dataset.ihStep === '3' && !reviewValid && (!state.items.length || operation.attempted)); });
    [byId('ihStartBuilding'), byId('ihBackSetup')].forEach(function (button) { button.disabled = operation.busy || Boolean(state.uploads); });
    btnRecover.classList.toggle('hidden', !operation.attempted);
    btnRecover.disabled = operation.busy || !operation.id;
    btnContinue.classList.toggle('hidden', !operation.job || operation.job.status !== 'ready');
    btnReconcile.classList.toggle('hidden', !operation.job || operation.job.status !== 'unknown');
    btnNew.classList.toggle('hidden', !operation.job || !['completed', 'failed'].includes(operation.job.status));
    root.querySelectorAll('[data-ih-resume], [data-ih-reconcile]').forEach(function (button) { button.disabled = operation.busy || Boolean(state.uploads); });
  }
  function openModal(modal) {
    if (state.modal) state.modal.classList.add('hidden');
    else state.returnFocus = document.activeElement;
    state.modal = modal;
    modal.classList.remove('hidden');
    root.inert = true;
    document.body.classList.add('ih-modal-open');
    var focus = modal.querySelector('button:not([disabled]), input:not([type="hidden"]), [tabindex]');
    (focus || modal).focus();
  }
  function closeModal() {
    if (operation.busy) return;
    if (state.modal) state.modal.classList.add('hidden');
    state.modal = null;
    root.inert = false;
    document.body.classList.remove('ih-modal-open');
    syncControls();
    if (state.returnFocus && !state.returnFocus.disabled) state.returnFocus.focus();
  }
  document.addEventListener('keydown', function (event) {
    if (!state.modal) return;
    if (event.key === 'Escape') { event.preventDefault(); closeModal(); }
    if (event.key !== 'Tab') return;
    var focusables = Array.from(state.modal.querySelectorAll('button:not([disabled]), input:not([disabled]):not([type="hidden"]), [tabindex="0"]'));
    if (!focusables.length) { event.preventDefault(); return; }
    var first = focusables[0], last = focusables[focusables.length - 1];
    if (event.shiftKey && (document.activeElement === first || !state.modal.contains(document.activeElement))) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && (document.activeElement === last || !state.modal.contains(document.activeElement))) { event.preventDefault(); first.focus(); }
  });
  document.querySelectorAll('[data-ih-close]').forEach(function (button) { button.addEventListener('click', closeModal); });

  async function upload(file, kind) {
    if (file.type !== 'image/jpeg' || file.size > 8 * 1024 * 1024) throw new Error('Use imagens JPEG de até 8 MB.');
    var form = new FormData();
    form.append('file', file);
    form.append('kind', kind);
    form.append('_csrf_token', config.csrfToken || '');
    var response = await request('/upload', form);
    if (!response.asset || !/^[a-f0-9]{32}$/.test(response.asset.id)) throw new Error('Imagem enviada não foi identificada.');
    // Always construct a same-origin private media URL from the validated asset ID.
    return { id: response.asset.id, url: endpoint + '/media/' + response.asset.id };
  }
  async function handleFiles(files) {
    if (!editable() || !files || !files.length) return;
    files = Array.from(files);
    if (state.items.length + files.length > 20) { message('Adicione no máximo 20 stories por operação.'); return; }
    state.uploads++;
    syncControls();
    try {
      // Preserve selection order and retain successful uploads if another file fails.
      for (var file of files) {
        try {
          var asset = await upload(file, 'story');
          state.items.push({ asset: asset.id, url: asset.url, link: '' });
        } catch (error) { message(file.name + ': ' + error.message); }
      }
      renderStories();
    } finally { state.uploads--; fileInput.value = ''; syncControls(); }
  }
  dropZone.addEventListener('click', function () { if (editable()) fileInput.click(); });
  dropZone.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); if (editable()) fileInput.click(); }
  });
  fileInput.addEventListener('click', function (event) { event.stopPropagation(); });
  fileInput.addEventListener('change', function () { handleFiles(fileInput.files); });
  dropZone.addEventListener('dragover', function (event) { event.preventDefault(); });
  dropZone.addEventListener('drop', function (event) { event.preventDefault(); handleFiles(event.dataTransfer.files); });

  function renderStories() {
    storyList.replaceChildren();
    byId('ihStoryCountBadge').textContent = state.items.length + ' / 20';
    if (!state.items.length) storyList.textContent = 'Adicione imagens acima para montar sua sequência.';
    state.items.forEach(function (item, index) {
      var card = document.createElement('div');
      card.className = 'ih-story-item' + (state.active === index ? ' active' : '');
      var thumbButton = document.createElement('button');
      thumbButton.type = 'button';
      thumbButton.setAttribute('aria-label', 'Visualizar story ' + (index + 1));
      var img = document.createElement('img');
      img.className = 'ih-story-thumb'; img.alt = 'Story ' + (index + 1); img.src = item.url;
      thumbButton.appendChild(img);
      thumbButton.addEventListener('click', function () { state.active = index; updatePreview(); });
      card.appendChild(thumbButton);
      var body = document.createElement('div'); body.className = 'ih-story-fields';
      var label = document.createElement('label'); label.textContent = 'Story ' + (index + 1) + ' — Cole o link desta peça (opcional)';
      label.htmlFor = 'ih-link-' + index;
      var input = document.createElement('input'); input.type = 'url'; input.className = 'ih-input';
      input.id = label.htmlFor; input.placeholder = 'https://…'; input.value = item.link;
      input.addEventListener('input', function () { if (editable()) { item.link = input.value.trim(); updatePreview(); } });
      body.append(label, input);
      var buttons = document.createElement('div'); buttons.className = 'ih-item-actions';
      [['Subir', -1], ['Descer', 1], ['Remover', 0]].forEach(function (action) {
        var button = document.createElement('button'); button.type = 'button'; button.className = 'ih-btn ih-btn-secondary';
        button.textContent = action[0]; button.setAttribute('aria-label', action[0] + ' story ' + (index + 1));
        button.addEventListener('click', function () {
          if (!editable()) return;
          var target = index + action[1];
          if (action[1] === 0) state.items.splice(index, 1);
          else if (target >= 0 && target < state.items.length) [state.items[index], state.items[target]] = [state.items[target], state.items[index]];
          state.active = Math.min(Math.max(target, 0), Math.max(0, state.items.length - 1));
          renderStories();
        });
        buttons.appendChild(button);
      });
      body.appendChild(buttons); card.appendChild(body); storyList.appendChild(card);
    });
    updatePreview(); syncControls();
  }
  function updatePreview() {
    var item = state.items[state.active];
    byId('ihPreviewPanel').classList.toggle('hidden', !item);
    root.querySelector('.ih-build-layout').classList.toggle('has-preview', Boolean(item));
    byId('ihPhoneEmptyNotice').classList.toggle('hidden', Boolean(item));
    byId('ihPhoneImage').classList.toggle('hidden', !item);
    byId('ihPhoneLinkSticker').classList.toggle('hidden', !item || !item.link);
    byId('ihPhoneProgressBar').replaceChildren();
    if (item) {
      byId('ihPhoneImage').src = item.url;
      var text = item.link;
      try { text = new URL(item.link).hostname; } catch (_) { /* Show incomplete typed text as plain text. */ }
      byId('ihPhoneLinkText').textContent = text;
      state.items.forEach(function (_, index) {
        var pill = document.createElement('div'); pill.className = 'ih-progress-pill' + (index <= state.active ? ' active' : '');
        byId('ihPhoneProgressBar').appendChild(pill);
      });
    }
    byId('ihPreviewIndexIndicator').textContent = (item ? state.active + 1 : 0) + ' / ' + state.items.length;
    byId('ihPreviewPrev').disabled = state.active === 0;
    byId('ihPreviewNext').disabled = state.active >= state.items.length - 1;
  }
  byId('ihPreviewPrev').addEventListener('click', function () { if (state.active > 0) { state.active--; updatePreview(); } });
  byId('ihPreviewNext').addEventListener('click', function () { if (state.active < state.items.length - 1) { state.active++; updatePreview(); } });
  document.querySelectorAll('input[name="ih_highlight_mode"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      if (!editable()) return;
      modeDrafts[currentMode] = {title:byId('ihHighlightTitle').value, cover:state.cover, coverUrl:state.coverUrl};
      currentMode = radio.value;
      var draft = modeDrafts[currentMode];
      byId('ihExistingHighlightFields').classList.toggle('hidden', radio.value !== 'existing');
      byId('ihTitleLabel').textContent = radio.value === 'existing' ? 'Novo nome (opcional, vazio mantém o atual):' : 'Nome do destaque:';
      byId('ihHighlightTitle').value = draft.title;
      byId('ihTitleCounter').textContent = draft.title.length + ' / 15';
      state.cover = draft.cover; state.coverUrl = draft.coverUrl;
      byId('ihCoverPreview').classList.toggle('hidden', !draft.cover);
      if (draft.coverUrl) byId('ihCoverPreview').src = draft.coverUrl;
    });
  });
  byId('ihHighlightTitle').addEventListener('input', function () { byId('ihTitleCounter').textContent = this.value.length + ' / 15'; });
  byId('ihCoverPicker').addEventListener('click', function () { if (editable()) byId('ihCoverFileInput').click(); });
  byId('ihCoverFileInput').addEventListener('change', async function () {
    if (!editable() || !this.files[0]) return;
    state.uploads++; syncControls();
    try {
      var asset = await upload(this.files[0], 'cover');
      state.cover = asset.id; state.coverUrl = asset.url;
      byId('ihCoverPreview').src = asset.url; byId('ihCoverPreview').classList.remove('hidden');
    } catch (error) { message(error.message); }
    finally { state.uploads--; this.value = ''; syncControls(); }
  });
  byId('ihBtnRefreshCatalog').addEventListener('click', async function () {
    if (!editable() || state.catalogBusy) return;
    state.catalogBusy = true; syncControls();
    try {
      var response = await request('/catalog', {});
      var select = byId('ihExistingSelect'); select.replaceChildren(new Option('Escolha um destaque', ''));
      response.highlights.forEach(function (highlight) { select.appendChild(new Option(highlight.title + ' (' + highlight.media_count + ' stories)', highlight.id)); });
      message('Destaques consultados. Identidade conferida nesta consulta.');
    } catch (error) { message(error.message); }
    finally { state.catalogBusy = false; syncControls(); }
  });

  function renderReview(content, resume) {
    var summary = byId('ihReviewSummary'); summary.replaceChildren();
    var target = content.highlight || {};
    var selected = byId('ihExistingSelect');
    var targetName = target.id && selected.value === target.id ? selected.options[selected.selectedIndex].textContent : target.id;
    var lines = ['Conta: @estrategia_nerd', (target.id ? 'Adicionar ao destaque: ' + targetName : 'Criar destaque: ' + target.title), 'Stories nesta montagem: ' + content.items.length,
      'Capa: ' + (target.cover_asset ? 'Foto escolhida' : target.id ? 'Manter a atual' : 'Primeiro story')];
    if (target.id && target.title) lines.push('Novo nome: ' + target.title);
    if (resume) lines.push('Continuaremos da próxima etapa pendente. Os stories já confirmados não serão enviados novamente.');
    lines.forEach(function (line) { var p = document.createElement('p'); p.textContent = line; summary.appendChild(p); });
    var list = document.createElement('ol'); list.className = 'ih-review-items';
    content.items.forEach(function (item, index) {
      var li = document.createElement('li');
      if (item.asset && /^[a-f0-9]{32}$/.test(item.asset)) { var img = document.createElement('img'); img.src = endpoint + '/media/' + item.asset; img.alt = 'Imagem do story ' + (index + 1); li.appendChild(img); }
      var caption = document.createElement('div'); var title = document.createElement('strong'); title.textContent = 'Story ' + (index + 1); caption.appendChild(title);
      var link = document.createElement('p'); link.textContent = item.story_id ? 'Story existente: ' + item.story_id : item.link || 'Sem link'; caption.appendChild(link); li.appendChild(caption);
      list.appendChild(li);
    });
    summary.appendChild(list);
    if (target.cover_asset && /^[a-f0-9]{32}$/.test(target.cover_asset)) { var cover = document.createElement('img'); cover.className = 'ih-review-cover'; cover.alt = 'Capa do destaque'; cover.src = endpoint + '/media/' + target.cover_asset; summary.prepend(cover); }
    reviewValid = true;
    byId('ihBtnConfirmPublish').textContent = resume ? 'Continuar publicação' : 'Publicar stories e montar destaque';
    showStep(3, true);
  }
  btnReview.addEventListener('click', function () {
    if (!editable() || !state.items.length) return;
    try {
      if (!byId('ihHighlightTitle').checkValidity()) { byId('ihHighlightTitle').reportValidity(); return; }
      var existing = document.querySelector('input[name="ih_highlight_mode"]:checked').value === 'existing';
      var target = { id: existing ? byId('ihExistingSelect').value : '', title: byId('ihHighlightTitle').value.trim(), cover_asset: state.cover };
      if (existing ? !target.id : !target.title) throw new Error(existing ? 'Escolha o destaque de destino.' : 'Informe o nome do destaque.');
      var items = state.items.map(function (item) {
        if (item.link) {
          var url;
          try { url = new URL(item.link); } catch (_) { throw new Error('Informe um link HTTPS completo em cada story.'); }
          if (url.protocol !== 'https:' || url.username || url.password) throw new Error('Use links HTTPS sem credenciais.');
        }
        return { asset: item.asset, link: item.link };
      });
      renderReview(operation.review({ account: 'estrategia_nerd', items: items, highlight: target }), false);
    } catch (error) { message(error.message); }
  });

  function showJob(job) {
    var labels = { ready: 'Pronta para continuar', running: 'Etapa em andamento; confira o estado antes de continuar', unknown: 'Resultado incerto: confira a etapa no Instagram', failed: 'Operação interrompida', completed: 'Publicação concluída' };
    message((labels[job.status] || 'Estado a conferir') + '. ' + (job.message || '') + ' Operação: ' + job.id);
    var percent = job.total_steps ? Math.round(job.completed_steps / job.total_steps * 100) : 0;
    byId('ihProgressStatusText').textContent = progressLabel(job);
    byId('ihProgressStepDetail').textContent = job.completed_steps + ' de ' + job.total_steps + ' etapas concluídas.';
    byId('ihProgressBarFill').style.width = percent + '%'; byId('ihProgressPercent').textContent = percent + '%';
    syncControls();
  }
  byId('ihBtnConfirmPublish').addEventListener('click', async function () {
    if (operation.busy || state.uploads || !reviewValid || !canPublish(connection)) return;
    this.disabled = true;
    openModal(progressModal);
    byId('ihProgressStatusText').textContent = 'Validando operação...';
    try { await operation.publish(); }
    catch (error) { message('Envio interrompido. ' + error.message + ' Nenhuma etapa será reenviada automaticamente. Confira o estado local.'); }
    finally { closeModal(); syncControls(); }
  });
  async function recover(id, review) {
    if (operation.busy || state.uploads) return;
    try {
      var promise = operation.recover(id); syncControls();
      var job = await promise;
      if (review && job.status === 'ready') renderReview(job, true);
    } catch (error) { message(error.code === 'JOB_NOT_FOUND' ? error.message + ' Revise a montagem novamente.' : 'Não foi possível conferir a operação. ' + error.message + ' O envio permanece bloqueado.'); }
    finally { syncControls(); }
  }
  btnRecover.addEventListener('click', function () { recover(operation.id, false); });
  btnContinue.addEventListener('click', function () { recover(operation.id, true); });
  document.querySelectorAll('[data-ih-resume]').forEach(function (button) {
    button.addEventListener('click', function () { recover(button.dataset.ihResume, true); });
  });
  var actionNames = { upload: 'Publicar story', create: 'Criar destaque', add: 'Adicionar stories ao destaque', rename: 'Alterar nome do destaque', cover: 'Alterar capa do destaque' };
  async function openReconcile(id) {
    await recover(id, false);
    var job = operation.job;
    if (!job || job.id !== id || job.status !== 'unknown') return;
    byId('ihReconcileJobId').value = id;
    byId('ihReconcileRemoteId').value = '';
    byId('ihReconcileConfirmCheck').checked = false;
    byId('ihBtnSubmitReconcile').disabled = true;
    byId('ihReconcileMessage').textContent = 'Confira esta etapa no Instagram: ' + (actionNames[job.next_action] || job.next_action) + '. ' + (job.message || '');
    byId('ihReconcileRemoteId').required = ['upload', 'create'].includes(job.next_action);
    openModal(reconcileModal);
  }
  btnReconcile.addEventListener('click', function () { openReconcile(operation.id); });
  document.querySelectorAll('[data-ih-reconcile]').forEach(function (button) {
    button.addEventListener('click', function () { openReconcile(button.dataset.ihReconcile); });
  });
  byId('ihReconcileConfirmCheck').addEventListener('change', function () { byId('ihBtnSubmitReconcile').disabled = !this.checked || operation.busy; });
  byId('ihBtnSubmitReconcile').addEventListener('click', async function () {
    if (operation.busy || !byId('ihReconcileConfirmCheck').checked) return;
    var input = byId('ihReconcileRemoteId');
    if (input.required && !input.value.trim()) { input.reportValidity(); return; }
    this.disabled = true; operation.busy = true;
    try {
      await request('/reconcile', { job_id: byId('ihReconcileJobId').value, remote_id: input.value.trim() || null, verified_visual: true });
      operation.busy = false; closeModal();
      await recover(operation.id, false);
    } catch (error) {
      message('Reconciliação não confirmada. ' + error.message);
      operation.busy = false; closeModal();
      await recover(operation.id, false);
    } finally { operation.busy = false; syncControls(); }
  });
  btnNew.addEventListener('click', function () {
    if (!operation.job || !['completed', 'failed'].includes(operation.job.status) || operation.busy) return;
    if (operation.job.status === 'failed' && !window.confirm('Uma nova operação não retoma a anterior. Confira os stories já publicados para não selecioná-los novamente. Deseja abrir uma montagem vazia?')) return;
    operation.storage.removeItem(operation.key);
    window.location.reload();
  });
  updatePreview(); renderConnection(); syncControls();
  if (operation.id) recover(operation.id, false);
})();
