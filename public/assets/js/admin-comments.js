(function () {
  const rootSelector = "[data-admin-comments-root]";
  const searchDelay = 300;
  let searchTimer = null;

  const getRoot = () => document.querySelector(rootSelector);

  const setLoading = (root, isLoading) => {
    root.style.opacity = isLoading ? "0.65" : "1";
    root.style.pointerEvents = isLoading ? "none" : "";
  };

  const normalizeUrl = (url) => {
    const parsed = new URL(url, window.location.origin);
    parsed.searchParams.set("_partial", "1");
    return parsed;
  };

  const buildFormUrl = (form) => {
    const formData = new FormData(form);
    const url = new URL(form.action, window.location.origin);

    for (const [key, value] of formData.entries()) {
      if (typeof value !== "string") continue;
      const normalized = value.trim();
      if (normalized === "" || normalized === "0") {
        url.searchParams.delete(key);
      } else {
        url.searchParams.set(key, normalized);
      }
    }

    url.searchParams.set("page", "1");
    return url;
  };

  const closeStatusMenus = (except = null) => {
    document.querySelectorAll("[data-comment-status-menu].is-open").forEach((menu) => {
      if (menu === except) return;
      menu.classList.remove("is-open");
      const toggle = menu.querySelector("[data-comment-status-toggle]");
      const actions = menu.querySelector("[data-comment-status-actions]");
      if (toggle) toggle.setAttribute("aria-expanded", "false");
      if (actions) actions.hidden = true;
    });
  };

  const fetchAndSwap = async (url, { pushState = true } = {}) => {
    const root = getRoot();
    if (!root) return;

    const requestUrl = normalizeUrl(url);
    setLoading(root, true);
    closeStatusMenus();

    try {
      const response = await fetch(requestUrl.toString(), {
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      });

      if (!response.ok) {
        throw new Error("Falha ao carregar os comentarios.");
      }

      const html = await response.text();
      const doc = new DOMParser().parseFromString(html, "text/html");
      const nextRoot = doc.querySelector(rootSelector);

      if (!nextRoot) {
        throw new Error("Bloco de comentarios nao encontrado na resposta.");
      }

      root.replaceWith(nextRoot);

      if (pushState) {
        const browserUrl = new URL(url, window.location.origin);
        browserUrl.searchParams.delete("_partial");
        window.history.pushState({}, "", browserUrl.toString());
      }
    } catch (error) {
      console.error(error);
      window.location.href = url;
    } finally {
      const currentRoot = getRoot();
      if (currentRoot) {
        setLoading(currentRoot, false);
      }
    }
  };

  const submitFilters = (form) => {
    const url = buildFormUrl(form);
    fetchAndSwap(url.toString());
  };

  document.addEventListener("click", (event) => {
    const statusToggle = event.target.closest("[data-comment-status-toggle]");
    if (statusToggle) {
      event.preventDefault();
      const menu = statusToggle.closest("[data-comment-status-menu]");
      if (!menu) return;

      const actions = menu.querySelector("[data-comment-status-actions]");
      const isOpen = menu.classList.contains("is-open");
      closeStatusMenus(menu);

      if (isOpen) {
        menu.classList.remove("is-open");
        statusToggle.setAttribute("aria-expanded", "false");
        if (actions) actions.hidden = true;
      } else {
        menu.classList.add("is-open");
        statusToggle.setAttribute("aria-expanded", "true");
        if (actions) actions.hidden = false;
      }
      return;
    }

    if (!event.target.closest("[data-comment-status-menu]")) {
      closeStatusMenus();
    }

    const link = event.target.closest("a[data-admin-comments-link]");
    if (!link) return;

    event.preventDefault();
    fetchAndSwap(link.href);
  });

  document.addEventListener("submit", (event) => {
    const form = event.target.closest("form[data-admin-comments-filters]");
    if (!form) return;

    event.preventDefault();
    submitFilters(form);
  });

  document.addEventListener("input", (event) => {
    const input = event.target.closest("form[data-admin-comments-filters] input[name='busca']");
    if (!input) return;

    const form = input.form;
    if (!form) return;

    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => {
      submitFilters(form);
    }, searchDelay);
  });

  document.addEventListener("change", (event) => {
    const field = event.target.closest("form[data-admin-comments-filters] select[name='status'], form[data-admin-comments-filters] select[name='respondido'], form[data-admin-comments-filters] select[name='post']");
    if (!field) return;

    const form = field.form;
    if (!form) return;

    window.clearTimeout(searchTimer);
    submitFilters(form);
  });

  window.addEventListener("popstate", () => {
    fetchAndSwap(window.location.href, { pushState: false });
  });
})();