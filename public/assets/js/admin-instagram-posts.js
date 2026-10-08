(function () {
  'use strict';

  var rootSelector = '[data-ig-posts-root]';
  var searchDebounceTimer = null;

  var getRoot = function () {
    return document.querySelector(rootSelector);
  };

  var viewMode = 'table';
  var applyViewMode = function (mode) {
    viewMode = mode === 'grid' ? 'grid' : 'table';
    var root = getRoot();
    if (!root) return;
    root.querySelectorAll('[data-posts-view-container]').forEach(function (container) {
      container.classList.toggle('hidden', container.getAttribute('data-posts-view-container') !== viewMode);
    });
    root.querySelectorAll('[data-view-btn]').forEach(function (button) {
      var active = button.getAttribute('data-view-btn') === viewMode;
      ['bg-cyan-500/20', 'text-cyan-200', 'shadow'].forEach(function (name) { button.classList.toggle(name, active); });
      button.classList.toggle('text-slate-400', !active);
      button.setAttribute('aria-pressed', String(active));
    });
    var input = root.querySelector('input[name="view"]');
    if (input) input.value = viewMode;
    root.querySelectorAll('a[data-admin-instagram-link]').forEach(function (link) {
      var target = new URL(link.href, window.location.origin);
      target.searchParams.set('view', viewMode);
      link.href = target.toString();
    });
    try { localStorage.setItem('ig_posts_view_preference', viewMode); } catch (e) {}
  };
  var initialMode = new URL(window.location.href).searchParams.get('view');
  if (initialMode !== 'grid' && initialMode !== 'table') {
    try { initialMode = localStorage.getItem('ig_posts_view_preference'); } catch (e) {}
  }
  applyViewMode(initialMode);

  var setLoading = function (root, isLoading) {
    if (!root) return;
    root.style.opacity = isLoading ? '0.6' : '1';
    root.style.pointerEvents = isLoading ? 'none' : '';
    root.style.transition = 'opacity 0.15s ease-in-out';
  };

  var normalizeUrl = function (url) {
    var parsed = new URL(url, window.location.origin);
    parsed.searchParams.set('_partial', '1');
    return parsed;
  };

  var buildCleanUrl = function (form) {
    var url = new URL(form.action, window.location.origin);
    var formData = new FormData(form);

    for (var pair of formData.entries()) {
      var key = pair[0];
      var value = String(pair[1]).trim();

      if (value === '' || value === '0') {
        continue;
      }
      if (key === 'period' && value === '7d') {
        continue;
      }
      if (key === 'sort' && value === 'publicado_em') {
        continue;
      }
      if (key === 'dir' && value === 'desc') {
        continue;
      }
      if (key === 'per_page' && value === '8') {
        continue;
      }

      url.searchParams.set(key, value);
    }

    return url;
  };

  var fetchAndSwap = async function (url, options) {
    var pushState = (options && options.pushState !== undefined) ? options.pushState : true;
    var root = getRoot();
    if (!root) return;

    var requestUrl = normalizeUrl(url);
    setLoading(root, true);

    try {
      var response = await fetch(requestUrl.toString(), {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'text/html',
        },
      });

      if (!response.ok) {
        throw new Error('Falha ao carregar listagem do Instagram (' + response.status + ').');
      }

      var html = await response.text();
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var nextRoot = doc.querySelector(rootSelector);

      if (!nextRoot) {
        throw new Error('Elemento [data-ig-posts-root] não encontrado no fragmento retornado.');
      }

      // Garante que a aba feed/posts esteja visível
      nextRoot.classList.remove('hidden');

      root.replaceWith(nextRoot);
      applyViewMode(new URL(url, window.location.origin).searchParams.get('view') || viewMode);

      // Se existir a função de troca de abas, garante que o botão "Posts" fique ativo
      if (typeof window.switchIgTab === 'function') {
        var btnFeed = document.querySelector('[data-ig-tab-btn="feed"]');
        if (btnFeed) {
          // Atualiza classes ativas das abas
          ['metricas', 'feed', 'insights'].forEach(function (t) {
            var b = document.querySelector('[data-ig-tab-btn="' + t + '"]');
            var c = document.querySelector('[data-ig-tab-content="' + t + '"]');
            if (b) {
              if (t === 'feed') {
                b.className = 'px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 border bg-cyan-500/20 text-cyan-300 border-cyan-500/40 shadow-sm';
              } else {
                b.className = 'px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 border border-transparent text-slate-400 hover:text-white hover:bg-slate-800/60';
              }
            }
            if (c && t !== 'feed') {
              c.classList.add('hidden');
            }
          });
        }
      }

      if (pushState) {
        var browserUrl = new URL(url, window.location.origin);
        browserUrl.searchParams.delete('_partial');
        window.history.pushState({}, '', browserUrl.toString());
      }
    } catch (err) {
      console.warn('Erro ao atualizar posts via AJAX:', err);
      window.location.href = url;
    } finally {
      var currentRoot = getRoot();
      if (currentRoot) {
        setLoading(currentRoot, false);
      }
    }
  };

  // Submissão do formulário de filtros
  document.addEventListener('submit', function (event) {
    var form = event.target.closest('form[data-admin-instagram-filters]');
    if (!form) return;

    event.preventDefault();
    var cleanUrl = buildCleanUrl(form);
    fetchAndSwap(cleanUrl.toString());
  });

  // Busca em tempo real com debounce de 350ms
  document.addEventListener('input', function (event) {
    var input = event.target;
    if (!input || input.id !== 'posts-busca') return;

    var form = input.closest('form[data-admin-instagram-filters]');
    if (!form) return;

    if (searchDebounceTimer) {
      clearTimeout(searchDebounceTimer);
    }

    searchDebounceTimer = setTimeout(function () {
      var cleanUrl = buildCleanUrl(form);
      fetchAndSwap(cleanUrl.toString());
    }, 350);
  });

  // Mudança nos selects de filtros
  document.addEventListener('change', function (event) {
    var select = event.target;
    if (!select || !select.classList.contains('admin-filter-control')) return;

    var form = select.closest('form[data-admin-instagram-filters]');
    if (!form || select.id === 'posts-busca') return;

    var cleanUrl = buildCleanUrl(form);
    fetchAndSwap(cleanUrl.toString());
  });

  // Cliques em links assíncronos (ordenação, paginação, limpar filtros)
  document.addEventListener('click', function (event) {
    var link = event.target.closest('a[data-admin-instagram-link]');
    if (!link) return;

    event.preventDefault();
    fetchAndSwap(link.href);
  });

  // Histórico do navegador (voltar / avançar)
  window.addEventListener('popstate', function () {
    fetchAndSwap(window.location.href, { pushState: false });
  });

  // Alternador de Visualização (Planilha vs Cards) sem refresh
  document.addEventListener('click', function (event) {
    var btn = event.target.closest('[data-posts-view-toggle] button[data-view-btn]');
    if (!btn) return;

    var targetView = btn.getAttribute('data-view-btn');
    if (!targetView) return;

    applyViewMode(targetView);

    // Atualiza parâmetro view na URL sem recarregar
    var currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('view', targetView);
    window.history.replaceState({}, '', currentUrl.toString());
  });
})();
