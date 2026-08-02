(function () {
  "use strict";

  const chartInstances = new WeakMap();
  const maxAttempts = 25;
  const fullNumber = new Intl.NumberFormat("pt-BR");
  const compactNumber = new Intl.NumberFormat("pt-BR", {
    notation: "compact",
    maximumFractionDigits: 1,
  });

  function formatMetric(value) {
    const number = Number(value || 0);
    return Math.abs(number) >= 1000 ? compactNumber.format(number) : fullNumber.format(number);
  }

  function compactLabel(value, maxLength) {
    const label = String(value || "");
    return label.length <= maxLength ? label : `${label.slice(0, Math.max(1, maxLength - 1))}...`;
  }

  function parsePayload(canvas) {
    if (!canvas) return null;
    try {
      return JSON.parse(canvas.dataset.chart || "{}");
    } catch (error) {
      console.warn("Admin module chart payload invalid", error);
      return null;
    }
  }

  function hasValues(payload, key) {
    return !!payload && Array.isArray(payload[key]) && payload[key].some((value) => Number(value || 0) > 0);
  }

  function setEmpty(canvas, isEmpty) {
    const shell = canvas ? canvas.closest(".admin-module-chart-shell") : null;
    if (shell) shell.classList.toggle("is-empty", isEmpty);
  }

  function normalizeHexColor(value, fallback) {
    return /^#[0-9a-f]{6}$/i.test(String(value || "")) ? String(value) : fallback;
  }

  function hexToRgba(hex, alpha) {
    const normalized = normalizeHexColor(hex, "#22d3ee").replace("#", "");
    const red = parseInt(normalized.slice(0, 2), 16);
    const green = parseInt(normalized.slice(2, 4), 16);
    const blue = parseInt(normalized.slice(4, 6), 16);
    return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
  }

  function defaultColors(payload, alpha) {
    const fallback = ["#22d3ee", "#60a5fa", "#34d399", "#facc15", "#f472b6", "#a78bfa", "#fb7185"];
    const colors = Array.isArray(payload.colors) && payload.colors.length > 0 ? payload.colors : fallback;
    return colors.map((color) => hexToRgba(color, alpha));
  }

  function chartDefaults() {
    if (!window.Chart) return;
    window.Chart.defaults.color = "rgba(203, 213, 225, 0.82)";
    window.Chart.defaults.font.family = "'Rajdhani', ui-sans-serif, system-ui";
    window.Chart.defaults.plugins.tooltip.backgroundColor = "rgba(2, 6, 23, 0.94)";
    window.Chart.defaults.plugins.tooltip.borderColor = "rgba(34, 211, 238, 0.24)";
    window.Chart.defaults.plugins.tooltip.borderWidth = 1;
    window.Chart.defaults.plugins.tooltip.padding = 12;
    window.Chart.defaults.plugins.tooltip.titleColor = "#f8fafc";
    window.Chart.defaults.plugins.tooltip.bodyColor = "#cbd5e1";
  }

  function destroyIn(root) {
    root.querySelectorAll("[data-admin-module-chart]").forEach((canvas) => {
      const chart = chartInstances.get(canvas);
      if (chart && typeof chart.destroy === "function") chart.destroy();
      chartInstances.delete(canvas);
    });
  }

  function renderDoughnut(canvas, payload) {
    return new window.Chart(canvas, {
      type: "doughnut",
      data: {
        labels: payload.labels || [],
        datasets: [{
          data: payload.values || [],
          backgroundColor: defaultColors(payload, 0.76),
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
    });
  }

  function renderBar(canvas, payload) {
    const valueKey = canvas.dataset.valueKey || "values";
    const axis = canvas.dataset.axis === "x" ? "x" : "y";
    return new window.Chart(canvas, {
      type: "bar",
      data: {
        labels: (payload.labels || []).map((label) => compactLabel(label, 24)),
        datasets: [{
          label: payload.label || canvas.dataset.label || "Total",
          data: payload[valueKey] || [],
          backgroundColor: defaultColors(payload, 0.48),
          borderColor: defaultColors(payload, 0.9),
          borderWidth: 1,
          borderRadius: 7,
          barThickness: Number(canvas.dataset.barThickness || 13),
        }],
      },
      options: {
        indexAxis: axis,
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
                const value = axis === "y" ? context.parsed.x : context.parsed.y;
                return `${payload.label || "Total"}: ${formatMetric(value)}`;
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
            beginAtZero: axis === "x",
            grid: axis === "y" ? { display: false } : { color: "rgba(51, 65, 85, 0.24)" },
            ticks: axis === "y" ? { font: { size: 11 } } : { precision: 0, callback: formatMetric },
          },
        },
      },
    });
  }

  function renderGroupedBar(canvas, payload) {
    const datasets = Array.isArray(payload.datasets) ? payload.datasets : [];
    return new window.Chart(canvas, {
      type: "bar",
      data: {
        labels: (payload.labels || []).map((label) => compactLabel(label, 24)),
        datasets: datasets.map((dataset, index) => ({
          label: dataset.label || `Serie ${index + 1}`,
          data: dataset.values || [],
          backgroundColor: dataset.backgroundColor || defaultColors(payload, 0.34 + index * 0.12)[index] || "rgba(34, 211, 238, 0.48)",
          borderColor: dataset.borderColor || defaultColors(payload, 0.82)[index] || "rgba(34, 211, 238, 0.9)",
          borderWidth: 1,
          borderRadius: 7,
          barThickness: Number(dataset.barThickness || 11),
        })),
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
    });
  }

  function renderCanvas(canvas) {
    const payload = parsePayload(canvas);
    const valueKey = canvas.dataset.valueKey || "values";
    const hasData = canvas.dataset.type === "grouped-bar"
      ? !!payload && Array.isArray(payload.datasets) && payload.datasets.some((dataset) => hasValues(dataset, "values"))
      : hasValues(payload, valueKey);

    if (!payload || !hasData) {
      setEmpty(canvas, true);
      return;
    }

    setEmpty(canvas, false);
    const type = canvas.dataset.type || "bar";
    const chart = type === "doughnut"
      ? renderDoughnut(canvas, payload)
      : type === "grouped-bar"
        ? renderGroupedBar(canvas, payload)
        : renderBar(canvas, payload);
    chartInstances.set(canvas, chart);
  }

  function init(root, attempt) {
    const scope = root || document;
    if (!window.Chart) {
      if ((attempt || 0) >= maxAttempts) {
        scope.querySelectorAll(".admin-module-chart-shell").forEach((shell) => shell.classList.add("is-empty"));
        return;
      }
      window.setTimeout(() => init(root, (attempt || 0) + 1), 120);
      return;
    }

    chartDefaults();
    destroyIn(scope);
    scope.querySelectorAll("[data-admin-module-chart]").forEach(renderCanvas);
  }

  window.adminModuleCharts = { init, destroyIn };
  document.addEventListener("DOMContentLoaded", () => init(document));
  document.addEventListener("admin:module-charts:init", (event) => init(event.detail && event.detail.root ? event.detail.root : document));
})();
