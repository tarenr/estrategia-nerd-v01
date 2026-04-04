(function () {
  const menuToggle = document.querySelector('[data-site-menu-toggle]');
  const mobileMenu = document.querySelector('[data-site-mobile-menu]');
  const toast = document.getElementById('siteToast');
  const newsletterForm = document.getElementById('newsletter-form');
  const getBlogSearchForm = () => document.querySelector('form[action$="/blog"]');
  const getBlogDynamicContent = () => document.getElementById('blogDynamicContent');
  const getCategoryFilters = () => document.getElementById('category-filters');

  const showToast = (message, type = 'success') => {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.remove('is-error', 'is-success', 'is-visible');
    toast.classList.add(type === 'error' ? 'is-error' : 'is-success');
    requestAnimationFrame(() => toast.classList.add('is-visible'));
    window.setTimeout(() => toast.classList.remove('is-visible'), 3200);
  };

  const celebrateNewsletter = () => {
    const host = document.createElement('div');
    host.className = 'site-confetti-burst';
    document.body.appendChild(host);

    const colors = ['#22d3ee', '#3b82f6', '#a855f7', '#10b981', '#f59e0b', '#f43f5e'];
    const count = 22;

    for (let i = 0; i < count; i += 1) {
      const piece = document.createElement('span');
      piece.className = 'site-confetti-piece';
      piece.style.left = `${50 + (Math.random() * 24 - 12)}%`;
      piece.style.top = `${42 + (Math.random() * 8 - 4)}%`;
      piece.style.background = colors[i % colors.length];
      piece.style.setProperty('--dx', `${Math.random() * 320 - 160}px`);
      piece.style.setProperty('--dy', `${120 + Math.random() * 180}px`);
      piece.style.setProperty('--rot', `${Math.random() * 720 - 360}deg`);
      piece.style.animationDelay = `${Math.random() * 80}ms`;
      host.appendChild(piece);
    }

    window.setTimeout(() => host.remove(), 1800);
  };

  if (menuToggle && mobileMenu) {
    menuToggle.addEventListener('click', () => {
      const expanded = menuToggle.getAttribute('aria-expanded') === 'true';
      menuToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      mobileMenu.hidden = expanded;
    });

    mobileMenu.querySelectorAll('a[href^="#"]').forEach((link) => {
      link.addEventListener('click', () => {
        menuToggle.setAttribute('aria-expanded', 'false');
        mobileMenu.hidden = true;
      });
    });
  }

  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener('click', (event) => {
      const href = anchor.getAttribute('href') || '';
      if (href === '#' || href.length < 2) return;
      const target = document.querySelector(href);
      if (!target) return;
      event.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  const counters = Array.from(document.querySelectorAll('[data-counter]'));
  const revealItems = Array.from(document.querySelectorAll('[data-reveal]'));
  if (counters.length > 0 && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        const element = entry.target;
        const target = parseInt(element.getAttribute('data-counter') || '0', 10);
        const duration = 1200;
        const startTime = performance.now();

        const tick = (now) => {
          const progress = Math.min((now - startTime) / duration, 1);
          const value = Math.round(target * progress);
          element.textContent = value.toLocaleString('pt-BR');
          if (progress < 1) {
            requestAnimationFrame(tick);
          }
        };

        requestAnimationFrame(tick);
        observer.unobserve(element);
      });
    }, { threshold: 0.45 });

    counters.forEach((counter) => {
      counter.textContent = '0';
      observer.observe(counter);
    });
  }

  const setupReveal = (items) => {
    if (items.length === 0) return;
    if (!('IntersectionObserver' in window)) {
      items.forEach((item) => item.classList.add('is-visible'));
      return;
    }

    const revealObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        revealObserver.unobserve(entry.target);
      });
    }, { threshold: 0.18, rootMargin: '0px 0px -6% 0px' });

    items.forEach((item, index) => {
      item.style.setProperty('--reveal-delay', `${Math.min(index % 3, 2) * 90}ms`);
      revealObserver.observe(item);
    });
  };

  setupReveal(revealItems);

  const refreshBlogUi = () => {
    setupReveal(Array.from(document.querySelectorAll('[data-reveal]:not(.is-visible)')));
  };

  const updateBlogPage = async (targetUrl, pushState = true) => {
    const currentDynamicContent = getBlogDynamicContent();
    const currentCategoryFilters = getCategoryFilters();

    if (!currentDynamicContent || !currentCategoryFilters) {
      window.location.href = targetUrl;
      return;
    }

    try {
      const response = await fetch(targetUrl, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      const html = await response.text();
      if (!response.ok) {
        throw new Error('Nao foi possivel atualizar o blog.');
      }

      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');
      const nextFilters = doc.getElementById('category-filters');
      const nextContent = doc.getElementById('blogDynamicContent');
      const nextSearchInput = doc.getElementById('search-input');
      const currentSearchInput = document.getElementById('search-input');

      if (!nextFilters || !nextContent) {
        window.location.href = targetUrl;
        return;
      }

      currentCategoryFilters.outerHTML = nextFilters.outerHTML;
      currentDynamicContent.outerHTML = nextContent.outerHTML;

      if (currentSearchInput && nextSearchInput) {
        currentSearchInput.value = nextSearchInput.value;
      }

      if (pushState) {
        window.history.pushState({ blogUrl: targetUrl }, '', targetUrl);
      }

      refreshBlogUi();
    } catch (error) {
      showToast(error instanceof Error ? error.message : 'Erro ao atualizar o blog.', 'error');
    }
  };

  if (getBlogSearchForm() && getBlogDynamicContent()) {
    let searchTimer = null;

    document.addEventListener('submit', (event) => {
      const targetForm = event.target instanceof HTMLFormElement ? event.target : null;
      if (!targetForm || targetForm !== getBlogSearchForm()) return;
      event.preventDefault();
      const formData = new FormData(targetForm);
      const params = new URLSearchParams();
      const q = String(formData.get('q') || '').trim();
      const categoria = String(formData.get('categoria') || '').trim();
      if (q !== '') params.set('q', q);
      if (categoria !== '') params.set('categoria', categoria);
      updateBlogPage(`${targetForm.action}${params.toString() ? `?${params.toString()}` : ''}`);
    });

    document.addEventListener('input', (event) => {
      const target = event.target instanceof HTMLInputElement ? event.target : null;
      if (!target || target.id !== 'search-input') return;
      window.clearTimeout(searchTimer);
      searchTimer = window.setTimeout(() => {
        getBlogSearchForm()?.requestSubmit();
      }, 260);
    });

    document.addEventListener('click', (event) => {
      const target = event.target instanceof Element ? event.target.closest('#category-filters a, .page-btn[href]') : null;
      if (!target) return;
      event.preventDefault();
      const href = target.getAttribute('href');
      if (!href) return;
      updateBlogPage(href);
    });

    window.addEventListener('popstate', () => {
      updateBlogPage(window.location.href, false);
    });
  }

  if (newsletterForm) {
    newsletterForm.addEventListener('submit', async (event) => {
      event.preventDefault();

      const button = document.getElementById('submit-btn');
      const text = document.getElementById('btn-text');
      const loading = document.getElementById('btn-loading');
      const formData = new FormData(newsletterForm);
      const name = String(formData.get('nome') || '').trim();
      const email = String(formData.get('email') || '').trim();

      if (!name) {
        showToast('Informe seu nome.', 'error');
        return;
      }

      if (!email || !email.includes('@')) {
        showToast('Informe um e-mail valido.', 'error');
        return;
      }

      if (button) button.setAttribute('disabled', 'disabled');
      if (text) text.classList.add('hidden');
      if (loading) loading.classList.remove('hidden');

      try {
        const response = await fetch(newsletterForm.action, {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
        });

        const result = await response.json();
        if (!response.ok || !result.ok) {
          throw new Error(result.message || 'Nao foi possivel concluir sua inscricao agora.');
        }

        newsletterForm.reset();
        celebrateNewsletter();
        showToast(result.message || 'Cadastro realizado com sucesso.', 'success');
      } catch (error) {
        showToast(error instanceof Error ? error.message : 'Erro ao enviar a newsletter.', 'error');
      } finally {
        if (button) button.removeAttribute('disabled');
        if (text) text.classList.remove('hidden');
        if (loading) loading.classList.add('hidden');
      }
    });
  }
})();
