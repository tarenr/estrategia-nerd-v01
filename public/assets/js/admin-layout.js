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

    sidebar.classList.toggle(expandedW, !collapsed);
    sidebar.classList.toggle(collapsedW, collapsed);

    scroll.classList.toggle('p-4', !collapsed);
    scroll.classList.toggle('p-2', collapsed);

    // keep layout stable; only hide text + center icons a bit
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

    toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');

    const icon = collapsed ? '⟫' : '⟪';
    const label = collapsed ? 'Expandir menu' : 'Recolher menu';

    if (toggleIcon) toggleIcon.textContent = icon;
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
    tip.className =
      'fixed z-[9999] px-2 py-1 text-xs rounded-lg border border-slate-700 bg-slate-950 text-slate-100 shadow-lg pointer-events-none hidden';
    document.body.appendChild(tip);
    return tip;
  }

  function show(e) {
    const el = e.currentTarget;
    const text = el.getAttribute('data-tooltip');
    if (!text) return;

    const t = ensureTip();
    t.textContent = text;
    t.classList.remove('hidden');

    const r = el.getBoundingClientRect();
    t.style.left = r.right + 10 + 'px';
    t.style.top = r.top + r.height / 2 + 'px';
    t.style.transform = 'translateY(-50%)';
  }

  function hide() {
    if (!tip) return;
    tip.classList.add('hidden');
  }

  document.querySelectorAll('[data-tooltip]').forEach((el) => {
    el.addEventListener('mouseenter', show);
    el.addEventListener('mouseleave', hide);
    el.addEventListener('blur', hide);
  });
})();