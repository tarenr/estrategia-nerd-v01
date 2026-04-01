(function () {
  const rootSelector = "[data-admin-posts-root]";

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
        throw new Error("Falha ao carregar os posts.");
      }

      const html = await response.text();
      const doc = new DOMParser().parseFromString(html, "text/html");
      const nextRoot = doc.querySelector(rootSelector);

      if (!nextRoot) {
        throw new Error("Bloco de posts nao encontrado na resposta.");
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

  document.addEventListener("submit", (event) => {
    const form = event.target.closest("form[data-admin-posts-filters]");
    if (!form) return;

    event.preventDefault();

    const url = new URL(form.action, window.location.origin);
    const formData = new FormData(form);

    for (const [key, value] of formData.entries()) {
      const stringValue = String(value);

      if (stringValue === "" || stringValue === "0") {
        continue;
      }

      url.searchParams.set(key, stringValue);
    }

    fetchAndSwap(url.toString());
  });

  document.addEventListener("click", (event) => {
    const link = event.target.closest("a[data-admin-posts-link]");
    if (!link) return;

    event.preventDefault();
    fetchAndSwap(link.href);
  });

  window.addEventListener("popstate", () => {
    fetchAndSwap(window.location.href, { pushState: false });
  });
})();
