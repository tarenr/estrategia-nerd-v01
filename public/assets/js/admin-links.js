(function () {
  const rootSelector = "[data-admin-links-root]";
  const searchDelay = 300;
  let searchTimer = null;
  const chartInstances = [];
  const maxChartInitAttempts = 25;
  let chartInitTimer = null;
  const numberFormatter = new Intl.NumberFormat("pt-BR");
  const compactFormatter = new Intl.NumberFormat("pt-BR", {
    notation: "compact",
    maximumFractionDigits: 1,
  });

  const getRoot = () => document.querySelector(rootSelector);

  const setLoading = (root, isLoading) => {
    root.style.opacity = isLoading ? "0.65" : "1";
    root.style.pointerEvents = isLoading ? "none" : "";
  };

  const escapeHtml = (value) =>
    String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");

  const formatMetric = (value) => {
    const number = Number(value || 0);
    return Math.abs(number) >= 1000 ? compactFormatter.format(number) : numberFormatter.format(number);
  };

  const compactLabel = (value, maxLength = 22) => {
    const label = String(value || "");
    return label.length <= maxLength ? label : `${label.slice(0, Math.max(1, maxLength - 1))}...`;
  };

  const parseChartPayload = (canvas) => {
    if (!canvas) return null;
    try {
      return JSON.parse(canvas.dataset.chart || "{}");
    } catch (error) {
      console.warn("Links chart payload invalid", error);
      return null;
    }
  };

  const chartHasValues = (payload, key = "values") =>
    !!payload && Array.isArray(payload[key]) && payload[key].some((value) => Number(value || 0) > 0);

  const setChartEmpty = (canvas, isEmpty) => {
    const shell = canvas ? canvas.closest(".admin-module-chart-shell") : null;
    if (shell) shell.classList.toggle("is-empty", isEmpty);
  };

  const configureCharts = () => {
    if (!window.Chart) return;
    window.Chart.defaults.color = "rgba(203, 213, 225, 0.82)";
    window.Chart.defaults.font.family = "'Rajdhani', ui-sans-serif, system-ui";
    window.Chart.defaults.plugins.tooltip.backgroundColor = "rgba(2, 6, 23, 0.94)";
    window.Chart.defaults.plugins.tooltip.borderColor = "rgba(34, 211, 238, 0.24)";
    window.Chart.defaults.plugins.tooltip.borderWidth = 1;
    window.Chart.defaults.plugins.tooltip.padding = 12;
    window.Chart.defaults.plugins.tooltip.titleColor = "#f8fafc";
    window.Chart.defaults.plugins.tooltip.bodyColor = "#cbd5e1";
  };

  const rememberChart = (chart) => {
    chartInstances.push(chart);
    return chart;
  };

  const destroyCharts = () => {
    while (chartInstances.length > 0) {
      const chart = chartInstances.pop();
      if (chart && typeof chart.destroy === "function") chart.destroy();
    }
  };

  const renderDoughnutChart = (root, selector, colors) => {
    const canvas = root.querySelector(selector);
    const payload = parseChartPayload(canvas);
    if (!canvas) return;
    if (!window.Chart || !chartHasValues(payload)) {
      setChartEmpty(canvas, true);
      return;
    }

    setChartEmpty(canvas, false);
    rememberChart(new window.Chart(canvas, {
      type: "doughnut",
      data: {
        labels: payload.labels || [],
        datasets: [{
          data: payload.values || [],
          backgroundColor: colors,
          borderColor: "rgba(15, 23, 42, 0.92)",
          borderWidth: 3,
          hoverOffset: 5,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: "64%",
        plugins: {
          legend: {
            position: "bottom",
            labels: { boxHeight: 8, boxWidth: 18, padding: 10, useBorderRadius: true },
          },
          tooltip: {
            callbacks: {
              label(context) {
                const total = context.dataset.data.reduce((sum, value) => sum + Number(value || 0), 0);
                const value = Number(context.parsed || 0);
                const percent = total > 0 ? ` (${Math.round((value / total) * 100)}%)` : "";
                return `${context.label}: ${formatMetric(value)}${percent}`;
              },
            },
          },
        },
      },
    }));
  };

  const renderHorizontalBarChart = (root, selector, label, colors) => {
    const canvas = root.querySelector(selector);
    const payload = parseChartPayload(canvas);
    if (!canvas) return;
    if (!window.Chart || !chartHasValues(payload)) {
      setChartEmpty(canvas, true);
      return;
    }

    setChartEmpty(canvas, false);
    rememberChart(new window.Chart(canvas, {
      type: "bar",
      data: {
        labels: (payload.labels || []).map((item) => compactLabel(item)),
        datasets: [{
          label,
          data: payload.values || [],
          backgroundColor: colors.background,
          borderColor: colors.border,
          borderWidth: 1,
          borderRadius: 7,
          barThickness: 13,
        }],
      },
      options: {
        indexAxis: "y",
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              title(items) {
                const index = items && items[0] ? items[0].dataIndex : -1;
                return index >= 0 && Array.isArray(payload.labels) ? payload.labels[index] : "";
              },
              label(context) {
                return `${label}: ${formatMetric(context.parsed.x)}`;
              },
            },
          },
        },
        scales: {
          x: {
            beginAtZero: true,
            grid: { color: "rgba(51, 65, 85, 0.24)" },
            ticks: { precision: 0, callback: formatMetric },
          },
          y: {
            grid: { display: false },
            ticks: { font: { size: 11 } },
          },
        },
      },
    }));
  };

  const initLinksCharts = (root = getRoot(), attempt = 0) => {
    if (!root) return;
    if (!window.Chart) {
      if (attempt >= maxChartInitAttempts) {
        root.querySelectorAll(".admin-module-chart-shell").forEach((shell) => shell.classList.add("is-empty"));
        return;
      }
      if (chartInitTimer !== null) {
        window.clearTimeout(chartInitTimer);
      }
      chartInitTimer = window.setTimeout(() => initLinksCharts(getRoot(), attempt + 1), 120);
      return;
    }

    configureCharts();
    renderDoughnutChart(root, "#linksStatusChart", [
      "rgba(52, 211, 153, 0.78)",
      "rgba(148, 163, 184, 0.58)",
      "rgba(248, 113, 113, 0.76)",
      "rgba(250, 204, 21, 0.72)",
    ]);
    renderHorizontalBarChart(root, "#linksTypesChart", "Links", {
      background: "rgba(34, 211, 238, 0.48)",
      border: "rgba(34, 211, 238, 0.9)",
    });
    renderHorizontalBarChart(root, "#linksTopClicksChart", "Cliques", {
      background: "rgba(250, 204, 21, 0.44)",
      border: "rgba(250, 204, 21, 0.88)",
    });
  };

  const openInlineConfirmModal = ({ title, message, submitLabel, cancelLabel }) =>
    new Promise((resolve) => {
      const host = document.createElement("div");
      host.className = "fixed inset-0 z-[10020] flex items-center justify-center px-4 py-8";
      host.innerHTML =
        '<div class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm"></div>' +
        '<div class="relative w-full max-w-lg rounded-3xl border border-cyan-500/20 bg-slate-950 shadow-2xl shadow-cyan-500/10">' +
        '<div class="border-b border-slate-800/70 px-6 py-5">' +
        '<div class="font-orbitron text-lg font-black text-white">' + escapeHtml(title || "Confirmacao") + "</div>" +
        "</div>" +
        '<div class="px-6 py-5 text-sm leading-6 text-slate-300">' + escapeHtml(message || "") + "</div>" +
        '<div class="flex items-center justify-end gap-3 border-t border-slate-800/70 px-6 py-5">' +
        '<button type="button" data-inline-cancel class="admin-btn admin-btn-secondary">' + escapeHtml(cancelLabel || "Cancelar") + "</button>" +
        '<button type="button" data-inline-confirm class="admin-btn admin-btn-primary">' + escapeHtml(submitLabel || "Confirmar") + "</button>" +
        "</div>" +
        "</div>";

      const close = (accepted) => {
        host.remove();
        document.body.style.overflow = "";
        resolve(accepted);
      };

      document.body.appendChild(host);
      document.body.style.overflow = "hidden";

      const confirmButton = host.querySelector("[data-inline-confirm]");
      const cancelButton = host.querySelector("[data-inline-cancel]");
      const overlay = host.firstElementChild;

      if (confirmButton) {
        confirmButton.addEventListener("click", () => close(true));
      }
      if (cancelButton) {
        cancelButton.addEventListener("click", () => close(false));
      }
      if (overlay) {
        overlay.addEventListener("click", () => close(false));
      }
    });

  const openConfirmModal = async ({ title, message, submitLabel, cancelLabel }) => {
    if (window.adminUi && typeof window.adminUi.confirm === "function") {
      return window.adminUi.confirm({
        title: title || "Confirmacao",
        subtitle: "Central Nerd",
        message:
          '<p class="text-sm leading-6 text-slate-300">' +
          escapeHtml(message || "") +
          "</p>",
        submitLabel: submitLabel || "Confirmar",
        cancelLabel: cancelLabel || "Cancelar",
      });
    }

    return openInlineConfirmModal({ title, message, submitLabel, cancelLabel });
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

  const captureViewState = (source = null) => {
    const root = getRoot();
    const anchorRow = source && typeof source.closest === "function"
      ? source.closest("tr[data-link-row-id]")
      : null;

    return {
      x: window.scrollX || window.pageXOffset || 0,
      y: window.scrollY || window.pageYOffset || 0,
      rootTop: root ? root.getBoundingClientRect().top : 0,
      anchorId: anchorRow ? anchorRow.getAttribute("data-link-row-id") || "" : "",
      anchorTop: anchorRow ? anchorRow.getBoundingClientRect().top : null,
    };
  };

  const restoreViewState = (state) => {
    if (!state) return;

    const apply = () => {
      const root = getRoot();
      if (!root) return;

      if (state.anchorId && state.anchorTop !== null) {
        const nextAnchor = root.querySelector(`tr[data-link-row-id="${state.anchorId}"]`);
        if (nextAnchor) {
          const nextTop = nextAnchor.getBoundingClientRect().top;
          const delta = nextTop - state.anchorTop;
          if (Math.abs(delta) > 1) {
            window.scrollBy(0, delta);
          }
          return;
        }
      }

      const rootDelta = root.getBoundingClientRect().top - state.rootTop;
      if (Math.abs(rootDelta) > 1) {
        window.scrollBy(0, rootDelta);
        return;
      }

      window.scrollTo(state.x, state.y);
    };

    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(apply);
    });
  };

  const fetchAndSwap = async (url, { pushState = true, source = null } = {}) => {
    const root = getRoot();
    if (!root) return;

    const requestUrl = normalizeUrl(url);
    const viewState = captureViewState(source);
    setLoading(root, true);

    try {
      const response = await fetch(requestUrl.toString(), {
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      });

      if (!response.ok) {
        throw new Error("Falha ao carregar os links.");
      }

      const html = await response.text();
      const doc = new DOMParser().parseFromString(html, "text/html");
      const nextRoot = doc.querySelector(rootSelector);

      if (!nextRoot) {
        throw new Error("Bloco de links nao encontrado na resposta.");
      }

      destroyCharts();
      root.replaceWith(nextRoot);
      initLinksCharts(nextRoot);
      restoreViewState(viewState);

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

  const submitFilters = (form, source = null) => {
    const url = buildFormUrl(form);
    fetchAndSwap(url.toString(), { source });
  };

  const submitReorder = async (ids) => {
    const root = getRoot();
    if (!root || !Array.isArray(ids) || ids.length === 0) return;

    const token = document.querySelector("input[name='_csrf_token']");
    const url = new URL(window.location.href);
    const formData = new FormData();
    const viewState = captureViewState(draggedRow);
    if (token) formData.append("_csrf_token", token.value);
    formData.append("return_to", url.toString());
    ids.forEach((id) => formData.append("ids[]", String(id)));

    setLoading(root, true);

    try {
      const response = await fetch(new URL("/admin/links/reordenar", window.location.origin).toString(), {
        method: "POST",
        body: formData,
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "same-origin",
      });

      if (!response.ok) {
        throw new Error("Falha ao reordenar os links.");
      }

      const html = await response.text();
      const doc = new DOMParser().parseFromString(html, "text/html");
      const nextRoot = doc.querySelector(rootSelector);
      if (!nextRoot) {
        throw new Error("Bloco de links nao encontrado apos reordenar.");
      }

      destroyCharts();
      root.replaceWith(nextRoot);
      initLinksCharts(nextRoot);
      restoreViewState(viewState);
    } catch (error) {
      console.error(error);
      window.location.reload();
    } finally {
      const currentRoot = getRoot();
      if (currentRoot) setLoading(currentRoot, false);
    }
  };

  const submitActionForm = async (form) => {
    const root = getRoot();
    if (!root) return;

    const actionInput = form.querySelector("input[name='action']");
    const action = actionInput ? String(actionInput.value || "").trim() : "";
    if (action === "toggle_destaque") {
      const trigger = form.querySelector("[data-featured-toggle-button]");
      const nextState = trigger ? String(trigger.getAttribute("data-featured-next") || "").trim() : "";
      const message = trigger ? String(trigger.getAttribute("data-featured-confirm-message") || "").trim() : "";
      if (nextState === "on" && message !== "") {
        const ok = await openConfirmModal({
          title: "Trocar destaque principal",
          message,
          submitLabel: "Confirmar troca",
          cancelLabel: "Cancelar",
        });
        if (!ok) {
          return;
        }
      }
    }

    const viewState = captureViewState(form);
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
        throw new Error("Falha ao executar a acao do link.");
      }

      const html = await response.text();
      const doc = new DOMParser().parseFromString(html, "text/html");
      const nextRoot = doc.querySelector(rootSelector);

      if (!nextRoot) {
        const fallbackUrl = response.url || form.action;
        window.location.href = fallbackUrl;
        return;
      }

      destroyCharts();
      root.replaceWith(nextRoot);
      initLinksCharts(nextRoot);
      restoreViewState(viewState);

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

  const copyTextToClipboard = async (value) => {
    const text = String(value || "").trim();
    if (!text) return false;

    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
      return true;
    }

    const input = document.createElement("textarea");
    input.value = text;
    input.setAttribute("readonly", "readonly");
    input.style.position = "fixed";
    input.style.opacity = "0";
    document.body.appendChild(input);
    input.select();
    const ok = document.execCommand("copy");
    document.body.removeChild(input);
    return ok;
  };

  document.addEventListener("click", (event) => {
    const link = event.target.closest("a[data-admin-links-link]");
    if (!link) return;

    event.preventDefault();
    fetchAndSwap(link.href, { source: link });
  });

  document.addEventListener("submit", (event) => {
    const form = event.target.closest("form[data-admin-links-filters]");
    if (form) {
      event.preventDefault();
      submitFilters(form, event.submitter || form);
      return;
    }

    const actionForm = event.target.closest("form[data-admin-links-action]");
    if (!actionForm) return;

    event.preventDefault();
    submitActionForm(actionForm);
  });

  document.addEventListener("input", (event) => {
    const input = event.target.closest("form[data-admin-links-filters] input[name='busca']");
    if (!input) return;

    const form = input.form;
    if (!form) return;

    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => {
      submitFilters(form, input);
    }, searchDelay);
  });

  document.addEventListener("change", (event) => {
    const control = event.target.closest("form[data-admin-links-filters] select");
    if (!control) return;

    const form = control.form;
    if (!form) return;

    window.clearTimeout(searchTimer);
    submitFilters(form, control);
  });

  document.addEventListener("click", async (event) => {
    const button = event.target.closest("[data-current-featured-copy]");
    if (!button) return;

    const original = button.textContent;
    if (!original) return;

    try {
      const ok = await copyTextToClipboard(button.getAttribute("data-current-featured-copy") || "");
      if (!ok) throw new Error("copy-failed");
      button.textContent = "Código copiado";
      window.setTimeout(() => {
        button.textContent = original;
      }, 1800);
    } catch (error) {
      button.textContent = "Falha ao copiar";
      window.setTimeout(() => {
        button.textContent = original;
      }, 1800);
    }
  });

  window.addEventListener("popstate", () => {
    fetchAndSwap(window.location.href, { pushState: false });
  });

  let draggedRow = null;

  document.addEventListener("dragstart", (event) => {
    const row = event.target.closest("tr[data-link-row-id]");
    if (!row) return;

    draggedRow = row;
    row.classList.add("opacity-60");
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", row.getAttribute("data-link-row-id") || "");
  });

  document.addEventListener("dragend", (event) => {
    const row = event.target.closest("tr[data-link-row-id]");
    if (row) row.classList.remove("opacity-60");
    document.querySelectorAll("tr[data-link-row-id]").forEach((item) => item.classList.remove("bg-cyan-500/5"));
    draggedRow = null;
  });

  document.addEventListener("dragover", (event) => {
    const row = event.target.closest("tr[data-link-row-id]");
    if (!draggedRow || !row || row === draggedRow) return;

    event.preventDefault();
    event.dataTransfer.dropEffect = "move";
  });

  document.addEventListener("dragenter", (event) => {
    const row = event.target.closest("tr[data-link-row-id]");
    if (!draggedRow || !row || row === draggedRow) return;
    row.classList.add("bg-cyan-500/5");
  });

  document.addEventListener("dragleave", (event) => {
    const row = event.target.closest("tr[data-link-row-id]");
    if (!row) return;
    row.classList.remove("bg-cyan-500/5");
  });

  document.addEventListener("drop", (event) => {
    const row = event.target.closest("tr[data-link-row-id]");
    const tbody = event.target.closest("tbody[data-admin-links-sortable]");
    if (!draggedRow || !row || !tbody || row === draggedRow) return;

    event.preventDefault();
    row.classList.remove("bg-cyan-500/5");

    const rows = Array.from(tbody.querySelectorAll("tr[data-link-row-id]"));
    const draggedIndex = rows.indexOf(draggedRow);
    const targetIndex = rows.indexOf(row);

    if (draggedIndex < targetIndex) {
      row.after(draggedRow);
    } else {
      row.before(draggedRow);
    }

    const orderedIds = Array.from(tbody.querySelectorAll("tr[data-link-row-id]"))
      .map((item) => parseInt(item.getAttribute("data-link-row-id") || "0", 10))
      .filter((id) => id > 0);

    submitReorder(orderedIds);
  });

  document.addEventListener("mousedown", (event) => {
    const handle = event.target.closest("[data-link-drag-handle]");
    if (!handle) return;

    const row = handle.closest("tr[data-link-row-id]");
    if (!row) return;

    row.draggable = true;
  });

  initLinksCharts();
})();
