/**
 * Admin layout behavior:
 * - Sidebar collapse/expand (persisted in localStorage)
 * - Instant tooltips for elements with [data-tooltip]
 */
(function () {
  const sidebar = document.getElementById('adminSidebar');
  const scroll = document.getElementById('adminSidebarScroll');
  const toggle = document.getElementById('sidebarToggle');
  const toggleIcon = document.getElementById('sidebarToggleIcon');
  const toggleSr = document.getElementById('sidebarToggleSr');

  if (!sidebar || !scroll || !toggle) return;

  const KEY = 'admin.sidebar.collapsed';
  const expandedW = 'w-[260px]';
  const collapsedW = 'w-[76px]';

  function apply(collapsed) {
    sidebar.dataset.collapsed = collapsed ? '1' : '0';
    document.body.dataset.adminSidebarCollapsed = collapsed ? '1' : '0';

    sidebar.classList.toggle(expandedW, !collapsed);
    sidebar.classList.toggle(collapsedW, collapsed);

    scroll.classList.add('p-4');

    scroll.querySelectorAll('[data-sb-text]').forEach((el) => el.classList.toggle('hidden', collapsed));
    scroll.querySelectorAll('[data-sb-item]').forEach((el) => {
      el.classList.toggle('justify-center', collapsed);
      el.classList.toggle('gap-0', collapsed);
      el.classList.toggle('gap-3', !collapsed);
    });

    scroll.querySelectorAll('[data-sb-sep]').forEach((el) => {
      el.classList.toggle('w-8', collapsed);
      el.classList.toggle('mx-auto', collapsed);
    });
    scroll.querySelectorAll('[data-sb-section]').forEach((el) => {
      el.classList.toggle('justify-center', collapsed);
    });
    toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');

    if (toggleIcon) {
      toggleIcon.classList.toggle('rotate-180', collapsed);
    }

    toggle.classList.toggle('mx-auto', collapsed);

    const label = collapsed ? 'Expandir menu' : 'Recolher menu';
    toggle.dataset.tooltip = label;
    toggle.title = label;
    if (toggleSr) toggleSr.textContent = label;
  }

  const saved = localStorage.getItem(KEY);
  apply(saved === '1');

  toggle.addEventListener('click', () => {
    const next = sidebar.dataset.collapsed !== '1';
    localStorage.setItem(KEY, next ? '1' : '0');
    apply(next);
  });
})();

(function () {
  let tip = null;

  function ensureTip() {
    if (tip) return tip;
    tip = document.createElement('div');
    tip.className = 'fixed z-[9999] px-2 py-1 text-xs rounded-lg border border-slate-700 bg-slate-950 text-slate-100 shadow-lg pointer-events-none hidden';
    document.body.appendChild(tip);
    return tip;
  }

  function show(event) {
    const element = event.currentTarget;
    const text = element.getAttribute('data-tooltip');
    if (!text) return;

    const tooltip = ensureTip();
    tooltip.textContent = text;
    tooltip.classList.remove('hidden');

    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.right + 10 + 'px';
    tooltip.style.top = rect.top + rect.height / 2 + 'px';
    tooltip.style.transform = 'translateY(-50%)';
  }

  function hide() {
    if (!tip) return;
    tip.classList.add('hidden');
  }

  document.querySelectorAll('[data-tooltip]').forEach((element) => {
    element.addEventListener('mouseenter', show);
    element.addEventListener('mouseleave', hide);
    element.addEventListener('blur', hide);
  });
})();

(function () {
  const refreshButton = document.querySelector('[data-admin-hard-refresh]');
  if (!refreshButton) return;

  refreshButton.addEventListener('click', () => {
    try {
      sessionStorage.clear();
    } catch (error) {}

    const url = new URL(window.location.href);
    url.searchParams.set('nocache', String(Date.now()));
    window.location.replace(url.toString());
  });
})();

(function () {
  const rootId = 'adminSystemModalRoot';
  const toastId = 'adminSystemToastRoot';

  function ensureModalRoot() {
    let root = document.getElementById(rootId);
    if (root) return root;

    root = document.createElement('div');
    root.id = rootId;
    root.className = 'fixed inset-0 z-[10000] hidden items-center justify-center px-4 py-8';
    root.innerHTML = '' +
      '<div class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm" data-admin-modal-close></div>' +
      '<div class="relative w-full max-w-xl rounded-3xl border border-cyan-500/20 bg-slate-950 shadow-2xl shadow-cyan-500/10">' +
      '<div class="flex items-center justify-between gap-4 border-b border-slate-800/70 px-6 py-5">' +
      '<div>' +
      '<div id="adminSystemModalTitle" class="font-orbitron text-lg font-black text-white">Sistema</div>' +
      '<div id="adminSystemModalSubtitle" class="mt-1 text-xs text-slate-400"></div>' +
      '</div>' +
      '<button type="button" id="adminSystemModalClose" class="admin-btn admin-btn-secondary !px-3 !py-2 text-xs">Fechar</button>' +
      '</div>' +
      '<div class="px-6 py-5 space-y-4">' +
      '<div id="adminSystemModalMessage" class="text-sm leading-6 text-slate-300"></div>' +
      '<form id="adminSystemModalForm" class="space-y-4"></form>' +
      '</div>' +
      '<div class="flex items-center justify-end gap-3 border-t border-slate-800/70 px-6 py-5">' +
      '<button type="button" id="adminSystemModalCancel" class="admin-btn admin-btn-secondary">Cancelar</button>' +
      '<button type="button" id="adminSystemModalSubmit" class="admin-btn admin-btn-primary">Confirmar</button>' +
      '</div>' +
      '</div>';

    document.body.appendChild(root);
    return root;
  }

  function ensureToastRoot() {
    let root = document.getElementById(toastId);
    if (root) return root;
    root = document.createElement('div');
    root.id = toastId;
    root.className = 'fixed top-4 right-4 z-[10001] flex flex-col gap-3 max-w-sm';
    document.body.appendChild(root);
    return root;
  }

  function createField(field) {
    const wrap = document.createElement('div');
    wrap.className = 'space-y-2';

    const label = document.createElement('label');
    label.className = 'block text-sm font-bold text-slate-200';
    label.textContent = field.label || field.name || 'Campo';
    label.htmlFor = 'admin-modal-field-' + field.name;
    wrap.appendChild(label);

    let input;
    if (field.type === 'textarea') {
      input = document.createElement('textarea');
      input.rows = field.rows || 4;
    } else {
      input = document.createElement('input');
      input.type = field.type || 'text';
    }

    input.id = 'admin-modal-field-' + field.name;
    input.name = field.name;
    input.value = field.value || '';
    input.placeholder = field.placeholder || '';
    input.className = 'nerd-input w-full rounded-xl px-4 py-3';
    if (field.required) {
      input.required = true;
    }

    wrap.appendChild(input);
    return wrap;
  }

  function openModal(options) {
    const root = ensureModalRoot();
    const title = root.querySelector('#adminSystemModalTitle');
    const subtitle = root.querySelector('#adminSystemModalSubtitle');
    const message = root.querySelector('#adminSystemModalMessage');
    const form = root.querySelector('#adminSystemModalForm');
    const submit = root.querySelector('#adminSystemModalSubmit');
    const cancel = root.querySelector('#adminSystemModalCancel');
    const close = root.querySelector('#adminSystemModalClose');
    const overlay = root.querySelector('[data-admin-modal-close]');

    title.textContent = options.title || 'Sistema';
    subtitle.textContent = options.subtitle || '';
    subtitle.classList.toggle('hidden', !options.subtitle);
    message.innerHTML = options.message || '';
    form.innerHTML = '';

    (options.fields || []).forEach((field) => {
      form.appendChild(createField(field));
    });

    submit.textContent = options.submitLabel || 'Confirmar';
    cancel.textContent = options.cancelLabel || 'Cancelar';
    cancel.classList.toggle('hidden', options.hideCancel === true);
    close.classList.toggle('hidden', options.hideClose === true);
    submit.classList.toggle('admin-btn-primary', !options.destructive);
    submit.classList.toggle('admin-btn-secondary', !!options.destructive);
    if (options.destructive) {
      submit.classList.add('!border-rose-500/40', '!text-rose-200', 'hover:!bg-rose-500/10');
    } else {
      submit.classList.remove('!border-rose-500/40', '!text-rose-200', 'hover:!bg-rose-500/10');
    }

    root.classList.remove('hidden');
    root.classList.add('flex');
    document.body.style.overflow = 'hidden';

    const firstInput = form.querySelector('input, textarea');
    if (firstInput) {
      window.setTimeout(() => firstInput.focus(), 20);
    }

    return new Promise((resolve) => {
      const cleanup = () => {
        root.classList.add('hidden');
        root.classList.remove('flex');
        document.body.style.overflow = '';
        submit.removeEventListener('click', onSubmit);
        cancel.removeEventListener('click', onCancel);
        close.removeEventListener('click', onCancel);
        overlay.removeEventListener('click', onCancel);
      };

      const onCancel = () => {
        cleanup();
        resolve(null);
      };

      const onSubmit = () => {
        const values = {};
        let invalid = false;
        form.querySelectorAll('input, textarea').forEach((input) => {
          const value = String(input.value || '').trim();
          if (input.required && value === '') {
            input.classList.add('!border-rose-400');
            invalid = true;
          } else {
            input.classList.remove('!border-rose-400');
          }
          values[input.name] = value;
        });

        if (invalid) return;

        cleanup();
        resolve(values);
      };

      submit.addEventListener('click', onSubmit);
      cancel.addEventListener('click', onCancel);
      close.addEventListener('click', onCancel);
      overlay.addEventListener('click', onCancel);
    });
  }

  function toast(message, type) {
    const root = ensureToastRoot();
    const item = document.createElement('div');
    const tone = type === 'error'
      ? 'border-rose-500/30 bg-rose-500/10 text-rose-100'
      : 'border-cyan-500/30 bg-slate-950/95 text-slate-100';
    item.className = 'rounded-2xl border px-4 py-3 text-sm shadow-xl backdrop-blur ' + tone;
    item.textContent = message;
    root.appendChild(item);
    window.setTimeout(() => {
      item.classList.add('opacity-0', 'translate-y-1');
      window.setTimeout(() => item.remove(), 180);
    }, 2400);
  }

  window.adminUi = window.adminUi || {};
  window.adminUi.prompt = openModal;
  window.adminUi.confirm = function confirmModal(options) {
    return openModal({
      title: options && options.title ? options.title : 'Confirmacao',
      subtitle: options && options.subtitle ? options.subtitle : '',
      message: options && options.message ? options.message : '',
      submitLabel: options && options.submitLabel ? options.submitLabel : 'Confirmar',
      cancelLabel: options && options.cancelLabel ? options.cancelLabel : 'Cancelar',
      hideCancel: false,
      hideClose: false,
      fields: [],
      destructive: !!(options && options.destructive),
    }).then(function (result) {
      return result !== null;
    });
  };
  window.adminUi.alert = function alertModal(options) {
    return openModal({
      title: options && options.title ? options.title : 'Aviso',
      subtitle: options && options.subtitle ? options.subtitle : '',
      message: options && options.message ? options.message : '',
      submitLabel: options && options.submitLabel ? options.submitLabel : 'OK',
      hideCancel: true,
      hideClose: false,
      fields: []
    });
  };
  window.adminUi.toast = toast;
})();
