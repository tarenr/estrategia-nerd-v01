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

      if (window.adminModuleCharts && typeof window.adminModuleCharts.destroyIn === "function") {
        window.adminModuleCharts.destroyIn(root);
      }
      root.replaceWith(nextRoot);
      document.dispatchEvent(new CustomEvent("admin:module-charts:init", { detail: { root: nextRoot } }));

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

  const fallbackSubmit = (form, submitter) => {
    if (submitter && submitter.name) {
      let fallbackField = form.querySelector("input[data-newsletter-submitter-fallback]");
      if (!fallbackField) {
        fallbackField = document.createElement("input");
        fallbackField.type = "hidden";
        fallbackField.setAttribute("data-newsletter-submitter-fallback", "1");
        form.appendChild(fallbackField);
      }
      fallbackField.name = submitter.name;
      fallbackField.value = submitter.value || "";
    }

    form.submit();
  };

  const submitActionForm = async (form, submitter = null) => {
    const root = getRoot();
    if (!root) return;

    const formData = new FormData(form);
    if (submitter && submitter.name) {
      formData.set(submitter.name, submitter.value || "");
    }

    setLoading(root, true);

    try {
      const response = await fetch(form.action, {
        method: "POST",
        body: formData,
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

      if (window.adminModuleCharts && typeof window.adminModuleCharts.destroyIn === "function") {
        window.adminModuleCharts.destroyIn(root);
      }
      root.replaceWith(nextRoot);
      document.dispatchEvent(new CustomEvent("admin:module-charts:init", { detail: { root: nextRoot } }));

      const browserUrl = new URL(response.url || window.location.href, window.location.origin);
      browserUrl.searchParams.delete("_partial");
      window.history.pushState({}, "", browserUrl.toString());
    } catch (error) {
      console.error(error);
      fallbackSubmit(form, submitter);
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
    const filterForm = event.target.closest("form[data-admin-newsletter-filters]");
    if (filterForm) {
      event.preventDefault();
      submitFilters(filterForm);
      return;
    }

    const actionForm = event.target.closest("form[data-admin-newsletter-action]");
    if (!actionForm) return;

    event.preventDefault();
    submitActionForm(actionForm, event.submitter || null);
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
