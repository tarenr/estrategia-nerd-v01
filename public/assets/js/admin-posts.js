(function () {
  const rootSelector = "[data-admin-posts-root]";
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

  const normalizeUrl = (url) => {
    const parsed = new URL(url, window.location.origin);
    parsed.searchParams.set("_partial", "1");
    return parsed;
  };

  const formatMetric = (value) => {
    const number = Number(value || 0);
    return Math.abs(number) >= 1000 ? compactFormatter.format(number) : numberFormatter.format(number);
  };

  const compactLabel = (value, maxLength = 22) => {
    const label = String(value || "");
    return label.length <= maxLength ? label : `${label.slice(0, Math.max(1, maxLength - 1))}...`;
  };

  const normalizeHexColor = (value, fallback = "#22d3ee") =>
    /^#[0-9a-f]{6}$/i.test(String(value || "")) ? String(value) : fallback;

  const hexToRgba = (hex, alpha) => {
    const normalized = normalizeHexColor(hex).replace("#", "");
    const red = parseInt(normalized.slice(0, 2), 16);
    const green = parseInt(normalized.slice(2, 4), 16);
    const blue = parseInt(normalized.slice(4, 6), 16);
    return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
  };

  const parseChartPayload = (canvas) => {
    if (!canvas) return null;
    try {
      return JSON.parse(canvas.dataset.chart || "{}");
    } catch (error) {
      console.warn("Posts chart payload invalid", error);
      return null;
    }
  };

  const chartHasValues = (payload, key = "values") =>
    !!payload && Array.isArray(payload[key]) && payload[key].some((value) => Number(value || 0) > 0);

  const categoryChartHasValues = (payload) =>
    chartHasValues(payload, "views") || chartHasValues(payload, "posts");

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

  const renderDoughnutChart = (root) => {
    const canvas = root.querySelector("#postsStatusChart");
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
          backgroundColor: [
            "rgba(52, 211, 153, 0.78)",
            "rgba(96, 165, 250, 0.68)",
            "rgba(250, 204, 21, 0.72)",
          ],
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

  const renderEngagementChart = (root) => {
    const canvas = root.querySelector("#postsEngagementChart");
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
        labels: payload.labels || [],
        datasets: [{
          label: "Total",
          data: payload.values || [],
          backgroundColor: [
            "rgba(34, 211, 238, 0.52)",
            "rgba(244, 114, 182, 0.52)",
            "rgba(168, 85, 247, 0.48)",
          ],
          borderColor: [
            "rgba(34, 211, 238, 0.92)",
            "rgba(244, 114, 182, 0.92)",
            "rgba(168, 85, 247, 0.86)",
          ],
          borderWidth: 1,
          borderRadius: 8,
          barThickness: 17,
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
              label(context) {
                return formatMetric(context.parsed.x);
              },
            },
          },
        },
        scales: {
          x: {
            beginAtZero: true,
            grid: { color: "rgba(51, 65, 85, 0.24)" },
            ticks: { callback: formatMetric },
          },
          y: { grid: { display: false } },
        },
      },
    }));
  };

  const renderCategoriesChart = (root) => {
    const canvas = root.querySelector("#postsCategoriesChart");
    const payload = parseChartPayload(canvas);
    if (!canvas) return;
    if (!window.Chart || !payload || !Array.isArray(payload.labels) || payload.labels.length === 0 || !categoryChartHasValues(payload)) {
      setChartEmpty(canvas, true);
      return;
    }

    const colors = Array.isArray(payload.colors) ? payload.colors : [];
    setChartEmpty(canvas, false);
    rememberChart(new window.Chart(canvas, {
      type: "bar",
      data: {
        labels: payload.labels.map((label) => compactLabel(label, 24)),
        datasets: [
          {
            label: "Views",
            data: payload.views || [],
            backgroundColor: colors.map((color) => hexToRgba(color, 0.5)),
            borderColor: colors.map((color) => hexToRgba(color, 0.92)),
            borderWidth: 1,
            borderRadius: 7,
            barThickness: 13,
          },
          {
            label: "Posts",
            data: payload.posts || [],
            backgroundColor: "rgba(148, 163, 184, 0.2)",
            borderColor: "rgba(203, 213, 225, 0.5)",
            borderWidth: 1,
            borderRadius: 7,
            barThickness: 9,
          },
        ],
      },
      options: {
        indexAxis: "y",
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
            labels: { boxHeight: 7, boxWidth: 18, padding: 8, useBorderRadius: true },
          },
          tooltip: {
            callbacks: {
              title(items) {
                const index = items && items[0] ? items[0].dataIndex : -1;
                return index >= 0 && Array.isArray(payload.labels) ? payload.labels[index] : "";
              },
              label(context) {
                return `${context.dataset.label}: ${formatMetric(context.parsed.x)}`;
              },
            },
          },
        },
        scales: {
          x: {
            beginAtZero: true,
            grid: { color: "rgba(51, 65, 85, 0.24)" },
            ticks: { callback: formatMetric },
          },
          y: {
            grid: { display: false },
            ticks: { font: { size: 11 } },
          },
        },
      },
    }));
  };

  const initPostsCharts = (root = getRoot(), attempt = 0) => {
    if (!root) return;
    if (!window.Chart) {
      if (attempt >= maxChartInitAttempts) {
        root.querySelectorAll(".admin-module-chart-shell").forEach((shell) => shell.classList.add("is-empty"));
        return;
      }
      if (chartInitTimer !== null) {
        window.clearTimeout(chartInitTimer);
      }
      chartInitTimer = window.setTimeout(() => initPostsCharts(getRoot(), attempt + 1), 120);
      return;
    }

    configureCharts();
    renderDoughnutChart(root);
    renderEngagementChart(root);
    renderCategoriesChart(root);
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

      destroyCharts();
      root.replaceWith(nextRoot);
      initPostsCharts(nextRoot);

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

  initPostsCharts();
})();
