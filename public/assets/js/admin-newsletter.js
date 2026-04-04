(function () {
  const rootSelector = "[data-admin-newsletter-root]";
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

  const fetchAndSwap = async (url, { pushState = true } = {}) => {
    const root = getRoot();
    if (!root) return;

    const requestUrl = normalizeUrl(url);
    setLoading(root, true);

    try {
      const response = await fetch(requestUrl.toString(), {
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      });

      if (!response.ok) {
        throw new Error("Falha ao carregar a newsletter.");
      }

      const html = await response.text();
      const doc = new DOMParser().parseFromString(html, "text/html");
      const nextRoot = doc.querySelector(rootSelector);

      if (!nextRoot) {
        throw new Error("Bloco da newsletter nao encontrado na resposta.");
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

  const submitActionForm = async (form) => {
    const root = getRoot();
    if (!root) return;

    setLoading(root, true);

    try {
      const response = await fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "same-origin",
      });

      if (!response.ok) {
        throw new Error("Falha ao executar a acao da newsletter.");
      }

      const html = await response.text();
      const doc = new DOMParser().parseFromString(html, "text/html");
      const nextRoot = doc.querySelector(rootSelector);

      if (!nextRoot) {
        const fallbackUrl = response.url || form.action;
        window.location.href = fallbackUrl;
        return;
      }

      root.replaceWith(nextRoot);

      const browserUrl = new URL(response.url || window.location.href, window.location.origin);
      browserUrl.searchParams.delete("_partial");
      window.history.pushState({}, "", browserUrl.toString());
    } catch (error) {
      console.error(error);
      form.submit();
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
    const link = event.target.closest("a[data-admin-newsletter-link]");
    if (!link) return;

    event.preventDefault();
    fetchAndSwap(link.href);
  });

  document.addEventListener("submit", (event) => {
    const form = event.target.closest("form[data-admin-newsletter-filters]");
    if (form) {
      event.preventDefault();
      submitFilters(form);
      return;
    }

    const actionForm = event.target.closest("form[data-admin-newsletter-action]");
    if (!actionForm) return;

    event.preventDefault();
    submitActionForm(actionForm);
  });

  document.addEventListener("input", (event) => {
    const input = event.target.closest("form[data-admin-newsletter-filters] input[name='busca']");
    if (!input) return;

    const form = input.form;
    if (!form) return;

    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => {
      submitFilters(form);
    }, searchDelay);
  });

  document.addEventListener("change", (event) => {
    const select = event.target.closest("form[data-admin-newsletter-filters] select[name='status']");
    if (!select) return;

    const form = select.form;
    if (!form) return;

    window.clearTimeout(searchTimer);
    submitFilters(form);
  });

  window.addEventListener("popstate", () => {
    fetchAndSwap(window.location.href, { pushState: false });
  });
})();
