(function () {
  const menuToggle = document.querySelector('[data-site-menu-toggle]');
  const mobileMenu = document.querySelector('[data-site-mobile-menu]');
  const toast = document.getElementById('siteToast');
  const newsletterForm = document.getElementById('newsletter-form');

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
