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

  const copyTextToClipboard = async (value) => {
    const text = String(value || '').trim();
    if (!text) return false;

    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
      return true;
    }

    const input = document.createElement('textarea');
    input.value = text;
    input.setAttribute('readonly', 'readonly');
    input.style.position = 'fixed';
    input.style.opacity = '0';
    document.body.appendChild(input);
    input.select();
    const ok = document.execCommand('copy');
    document.body.removeChild(input);
    return ok;
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

  const centralAccordions = Array.from(document.querySelectorAll('[data-central-accordion]'));
  if (centralAccordions.length > 0) {
    centralAccordions.forEach((accordion) => {
      accordion.addEventListener('toggle', () => {
        if (!accordion.open) return;
        centralAccordions.forEach((other) => {
          if (other !== accordion) {
            other.open = false;
          }
        });
      });
    });
  }

  const bindCouponCopyButtons = (scope = document) => {
    scope.querySelectorAll('[data-copy-coupon-trigger]').forEach((trigger) => {
      if (trigger.dataset.bound === '1') return;
      trigger.dataset.bound = '1';
      let resetTimer = null;

      trigger.addEventListener('click', async () => {
        const raw = trigger.getAttribute('data-copy-coupon') || '';
        const feedback = trigger.querySelector('[data-copy-coupon-feedback]');

        try {
          const copied = await copyTextToClipboard(raw);
          if (!copied) {
            throw new Error('Nao foi possivel copiar o cupom agora.');
          }

          trigger.classList.add('is-copied');
          window.clearTimeout(resetTimer);
          resetTimer = window.setTimeout(() => {
            trigger.classList.remove('is-copied');
          }, 2200);

          showToast(`Cupom ${raw} copiado para a area de transferencia.`, 'success');
        } catch (error) {
          trigger.classList.remove('is-copied');
          showToast(error instanceof Error ? error.message : 'Nao foi possivel copiar o cupom agora.', 'error');
        }
      });
    });
  };

  bindCouponCopyButtons(document);

  const progressBar = document.getElementById('postProgressBar');
  const articleContent = document.querySelector('.article-content');
  if (progressBar && articleContent) {
    const updateProgress = () => {
      const rect = articleContent.getBoundingClientRect();
      const total = articleContent.offsetHeight - window.innerHeight;
      if (total <= 0) {
        progressBar.style.width = '100%';
        return;
      }

      const scrolled = Math.min(Math.max(-rect.top, 0), total);
      const percent = Math.min((scrolled / total) * 100, 100);
      progressBar.style.width = `${percent}%`;
    };

    updateProgress();
    window.addEventListener('scroll', updateProgress, { passive: true });
    window.addEventListener('resize', updateProgress);
  }

  const tocLinks = Array.from(document.querySelectorAll('.toc-link'));
  const articleSections = Array.from(document.querySelectorAll('.article-content h2[id]'));
  if (tocLinks.length > 0 && articleSections.length > 0) {
    const activateCurrentSection = () => {
      let currentId = articleSections[0]?.id || '';
      articleSections.forEach((section) => {
        const top = section.getBoundingClientRect().top;
        if (top <= 140) {
          currentId = section.id;
        }
      });

      tocLinks.forEach((link) => {
        const isActive = link.getAttribute('href') === `#${currentId}`;
        link.classList.toggle('active', isActive);
      });
    };

    activateCurrentSection();
    window.addEventListener('scroll', activateCurrentSection, { passive: true });
  }

  const shareButtons = Array.from(document.querySelectorAll('[data-share]'));
  shareButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const type = button.getAttribute('data-share');
      const pageUrl = window.location.href;
      const pageTitle = document.title;
      let targetUrl = '';

      switch (type) {
        case 'twitter':
          targetUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(pageUrl)}&text=${encodeURIComponent(pageTitle)}`;
          break;
        case 'facebook':
          targetUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(pageUrl)}`;
          break;
        case 'linkedin':
          targetUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(pageUrl)}`;
          break;
        default:
          return;
      }

      window.open(targetUrl, '_blank', 'noopener,noreferrer,width=640,height=640');
    });
  });

  const copyLinkButton = document.querySelector('[data-copy-link]');
  if (copyLinkButton) {
    copyLinkButton.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(window.location.href);
        showToast('Link copiado com sucesso.', 'success');
      } catch (error) {
        showToast('Nao foi possivel copiar o link agora.', 'error');
      }
    });
  }

  const likeForm = document.querySelector('[data-like-form]');
  const likeButton = document.querySelector('[data-like-button]');
  const likeCountTargets = Array.from(document.querySelectorAll('[data-like-count]'));
  if (likeForm && likeButton) {
    const likedKey = `liked:${window.location.pathname}`;
    if (window.localStorage.getItem(likedKey) === '1') {
      likeButton.classList.add('is-liked');
    }

    likeForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (likeButton.classList.contains('is-liked')) {
        showToast('Voce ja curtiu este post neste navegador.', 'success');
        return;
      }

      likeButton.setAttribute('disabled', 'disabled');
      try {
        const response = await fetch(likeForm.action, {
          method: 'POST',
          body: new FormData(likeForm),
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        });
        const result = await response.json();
        if (!response.ok || !result.ok) {
          throw new Error(result.message || 'Nao foi possivel registrar a curtida.');
        }

        likeCountTargets.forEach((target) => {
          target.textContent = Number(result.likes || 0).toLocaleString('pt-BR');
        });
        likeButton.classList.add('is-liked');
        window.localStorage.setItem(likedKey, '1');
        showToast(result.message || 'Curtida registrada.', 'success');
      } catch (error) {
        showToast(error instanceof Error ? error.message : 'Erro ao curtir o post.', 'error');
      } finally {
        likeButton.removeAttribute('disabled');
      }
    });
  }

  const commentsList = document.querySelector('[data-comments-list]');
  const showMoreCommentsButton = document.querySelector('[data-show-more-comments]');
  const mainCommentForm = document.querySelector('.comment-form-card');
  const commentTotalTargets = Array.from(document.querySelectorAll('[data-comments-total]'));
  const visibleRootLimit = 3;

  const updateCommentTotals = (total) => {
    commentTotalTargets.forEach((target) => {
      target.textContent = String(total);
    });
  };

  const applyCommentVisibilityLimit = () => {
    if (!commentsList) return;
    const rootComments = Array.from(commentsList.querySelectorAll('[data-comment-root]'));
    let hiddenCount = 0;

    rootComments.forEach((item, index) => {
      const shouldHide = index >= visibleRootLimit;
      item.hidden = shouldHide;
      item.classList.toggle('comment-hidden', shouldHide);
      if (shouldHide) hiddenCount += 1;
    });

    if (!showMoreCommentsButton) return;
    showMoreCommentsButton.hidden = hiddenCount === 0;
  };

  const bindReplyToggles = (scope = document) => {
    scope.querySelectorAll('[data-reply-toggle]').forEach((button) => {
      if (button.dataset.bound === '1') return;
      button.dataset.bound = '1';
      button.addEventListener('click', () => {
        const id = button.getAttribute('data-reply-toggle');
        const form = document.querySelector(`[data-reply-form="${id}"]`);
        if (!form) return;
        const isHidden = form.hidden;
        form.hidden = !isHidden;
        if (isHidden) {
          form.querySelector('input[name="nome"]')?.focus();
        }
      });
    });
  };

  bindReplyToggles(document);

  if (showMoreCommentsButton && commentsList) {
    showMoreCommentsButton.addEventListener('click', () => {
      commentsList.querySelectorAll('.comment-hidden').forEach((item) => {
        item.hidden = false;
        item.classList.remove('comment-hidden');
      });
      showMoreCommentsButton.remove();
    });
  }

  const submitCommentForm = async (form, onSuccess) => {
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      const result = await response.json();
      if (!response.ok || !result.ok) {
        throw new Error(result.message || 'Nao foi possivel publicar seu comentario.');
      }

      if (typeof onSuccess === 'function') {
        onSuccess(result);
      }
      form.reset();
      showToast(result.message || 'Comentario publicado com sucesso.', 'success');
    } catch (error) {
      showToast(error instanceof Error ? error.message : 'Erro ao enviar comentario.', 'error');
    }
  };

  if (mainCommentForm && commentsList) {
    mainCommentForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      await submitCommentForm(mainCommentForm, (result) => {
        if (!result.html) return;

        const template = document.createElement('div');
        template.innerHTML = String(result.html).trim();

        const emptyPanel = commentsList.querySelector('.site-empty-panel');
        if (emptyPanel) {
          emptyPanel.remove();
        }
        commentsList.prepend(template.firstElementChild);
        bindReplyToggles(commentsList);
        updateCommentTotals(result.comment_total || 0);
        applyCommentVisibilityLimit();
      });
    });

    commentsList.addEventListener('submit', async (event) => {
      const form = event.target instanceof HTMLFormElement ? event.target : null;
      if (!form || !form.matches('.comment-reply-form')) return;
      event.preventDefault();
      await submitCommentForm(form, (result) => {
        form.hidden = true;
        if (!result.html) return;
        const parentId = form.getAttribute('data-reply-form');
        const childrenHost = document.querySelector(`[data-comment-children="${parentId}"]`);
        if (!childrenHost) return;

        const wrapper = document.createElement('div');
        wrapper.innerHTML = String(result.html).trim();
        childrenHost.append(wrapper.firstElementChild);
        updateCommentTotals(result.comment_total || 0);
      });
    });

    applyCommentVisibilityLimit();
  }

  const resolveSiteBasePath = () => {
    const script = document.currentScript || document.querySelector('script[src*="/assets/js/site-home.js"]');
    if (script && script.src) {
      try {
        const url = new URL(script.src, window.location.href);
        const marker = '/assets/js/site-home.js';
        const markerIndex = url.pathname.indexOf(marker);
        if (markerIndex !== -1) {
          return url.pathname.slice(0, markerIndex);
        }
      } catch (error) {}
    }

    return '';
  };

  const normalizePublicMediaUrl = (value) => {
    const raw = String(value || '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw)) {
      try {
        const url = new URL(raw);
        const localHosts = ['localhost', '127.0.0.1'];
        if (localHosts.includes(url.hostname.toLowerCase()) && !localHosts.includes(window.location.hostname.toLowerCase())) {
          return normalizePublicMediaUrl(`${url.pathname}${url.search || ''}`);
        }
      } catch (error) {}

      return raw;
    }
    if (raw.startsWith('//')) return `${window.location.protocol}${raw}`;
    if (raw.charAt(0) !== '/') return raw;

    const basePath = resolveSiteBasePath();
    if (!basePath || raw === basePath || raw.startsWith(`${basePath}/`)) {
      return raw;
    }

    return `${basePath}${raw}`;
  };

  const initEditorialAudioBlocks = () => {
    const blocks = Array.from(document.querySelectorAll('.en-audio-block'));
    if (blocks.length === 0) return;

    const state = window.__enAudioBlockState = window.__enAudioBlockState || { active: null };

    const ensureAudioButtonStructure = (button) => {
      if (!button) return;
      let iconWrap = button.querySelector('.en-audio-button-icon');
      let textWrap = button.querySelector('.en-audio-button-text');
      if (!iconWrap) {
        iconWrap = document.createElement('span');
        iconWrap.className = 'en-audio-button-icon';
        iconWrap.setAttribute('aria-hidden', 'true');
        iconWrap.textContent = '▶';
        button.insertBefore(iconWrap, button.firstChild);
      }
      if (!textWrap) {
        const text = String(button.textContent || '').trim();
        Array.from(button.childNodes).forEach((node) => {
          if (node !== iconWrap) {
            button.removeChild(node);
          }
        });
        textWrap = document.createElement('span');
        textWrap.className = 'en-audio-button-text';
        textWrap.textContent = text || 'Ouvir narracao';
        button.appendChild(textWrap);
      }
    };

    const setButtonState = (button, iconGlyph, text) => {
      if (!button) return;
      ensureAudioButtonStructure(button);
      const icon = button.querySelector('.en-audio-button-icon');
      const textNode = button.querySelector('.en-audio-button-text');
      if (icon) icon.textContent = iconGlyph;
      if (textNode) textNode.textContent = text;
    };

    const stopActive = () => {
      if (!state.active) return;
      const { narracao, ambiente, button, block } = state.active;
      try { if (narracao) { narracao.pause(); narracao.currentTime = 0; } } catch (error) {}
      try { if (ambiente) { ambiente.pause(); ambiente.currentTime = 0; } } catch (error) {}
      if (button) {
        const initial = button.getAttribute('data-en-audio-initial') || button.textContent || 'Ouvir narracao';
        setButtonState(button, '▶', initial);
        button.removeAttribute('aria-pressed');
      }
      if (block) {
        block.classList.remove('is-playing');
      }
      state.active = null;
    };

    blocks.forEach((block) => {
      const button = block.querySelector('[data-en-audio-toggle]');
      if (!button) return;
      if (button.dataset.bound === '1') return;
      button.dataset.bound = '1';
      ensureAudioButtonStructure(button);

      const narracaoSrc = normalizePublicMediaUrl(block.getAttribute('data-audio-narracao') || '');
      const ambienteSrc = normalizePublicMediaUrl(block.getAttribute('data-audio-ambiente') || '');
      const initialText = (button.querySelector('.en-audio-button-text') || button).textContent || 'Ouvir narracao';
      button.setAttribute('data-en-audio-initial', initialText);

      if (!narracaoSrc && !ambienteSrc) {
        setButtonState(button, '■', 'Audio indisponivel');
        button.setAttribute('disabled', 'disabled');
        button.setAttribute('aria-disabled', 'true');
        block.classList.add('has-audio-error');
        return;
      }

      const narracao = narracaoSrc ? new Audio(narracaoSrc) : null;
      const ambiente = ambienteSrc ? new Audio(ambienteSrc) : null;
      if (ambiente) ambiente.loop = true;

      const resetUi = () => {
        setButtonState(button, '▶', initialText);
        button.removeAttribute('aria-pressed');
        block.classList.remove('is-playing');
      };

      if (narracao) {
        narracao.addEventListener('ended', () => {
          try { if (ambiente) { ambiente.pause(); ambiente.currentTime = 0; } } catch (error) {}
          try { narracao.currentTime = 0; } catch (error) {}
          if (state.active && state.active.block === block) {
            state.active = null;
          }
          resetUi();
        });
      }

      button.addEventListener('click', async () => {
        const isCurrent = state.active && state.active.block === block;
        const isPlaying = isCurrent && ((narracao && !narracao.paused) || (ambiente && !ambiente.paused));

        if (isPlaying) {
          stopActive();
          return;
        }

        if (state.active && !isCurrent) {
          stopActive();
        }

        try {
          if (narracao) narracao.volume = 1;
          if (ambiente) ambiente.volume = narracao ? 0.12 : 0.35;

          if (narracao) {
            await narracao.play();
            if (ambiente) {
              ambiente.play().catch(() => {
                try { ambiente.pause(); ambiente.currentTime = 0; } catch (error) {}
              });
            }
          } else if (ambiente) {
            await ambiente.play();
          }

          setButtonState(button, '❚❚', 'Pausar');
          button.setAttribute('aria-pressed', 'true');
          block.classList.add('is-playing');
          state.active = { block, button, narracao, ambiente };
        } catch (error) {
          stopActive();
          setButtonState(button, '■', 'Audio indisponivel');
          block.classList.add('has-audio-error');
        }
      });
    });
  };


  initEditorialAudioBlocks();

})();
