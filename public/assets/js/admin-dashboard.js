/* eslint-disable no-console */
/**
 * -----------------------------------------------------------------------------
 * @file        public/assets/js/admin-dashboard.js
 * @project     Estrategia Nerd
 * @purpose     Dashboard Admin (filtro por data sem reload e graficos)
 * -----------------------------------------------------------------------------
 */

(function () {
  "use strict";

  const path = window.location.pathname || "";
  if (!/(?:^|\/)admin(?:\/dashboard)?\/?$/.test(path)) return;

  const MS_DAY = 24 * 60 * 60 * 1000;
  const compactNumber = new Intl.NumberFormat("pt-BR", {
    notation: "compact",
    maximumFractionDigits: 1,
  });
  const fullNumber = new Intl.NumberFormat("pt-BR");
  const dashboardCharts = [];

  function parseYmd(value) {
    if (!value || typeof value !== "string") return null;
    const trimmed = value.trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) return null;

    const [year, month, day] = trimmed.split("-").map(Number);
    const date = new Date(Date.UTC(year, month - 1, day));
    return Number.isNaN(date.getTime()) ? null : date;
  }

  function fmtYmd(date) {
    const year = date.getUTCFullYear();
    const month = String(date.getUTCMonth() + 1).padStart(2, "0");
    const day = String(date.getUTCDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  }

  function clampValues(startInput, endInput) {
    let start = parseYmd(startInput.value);
    let end = parseYmd(endInput.value);

    if (!start || !end) return;

    if (start > end) {
      const previousStart = start;
      start = end;
      end = previousStart;
    }

    const maxEnd = new Date(start.getTime() + (89 * MS_DAY));
    if (end > maxEnd) {
      end = maxEnd;
    }

    const minStart = new Date(end.getTime() - (89 * MS_DAY));
    if (start < minStart) {
      start = minStart;
    }

    startInput.value = fmtYmd(start);
    endInput.value = fmtYmd(end);
  }

  function parseChartPayload(canvas) {
    if (!canvas) return null;
    try {
      return JSON.parse(canvas.dataset.chart || "{}");
    } catch (error) {
      console.warn("Dashboard chart payload invalid", error);
      return null;
    }
  }

  function formatMetric(value) {
    const number = Number(value || 0);
    return Math.abs(number) >= 1000 ? compactNumber.format(number) : fullNumber.format(number);
  }

  function compactLabel(value, maxLength) {
    const label = String(value || "");
    if (label.length <= maxLength) return label;
    return `${label.slice(0, Math.max(1, maxLength - 1))}...`;
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

  function rememberChart(chart) {
    dashboardCharts.push(chart);
    return chart;
  }

  function destroyCharts() {
    while (dashboardCharts.length > 0) {
      const chart = dashboardCharts.pop();
      if (chart && typeof chart.destroy === "function") {
        chart.destroy();
      }
    }
  }

  function renderActivityChart(root) {
    const canvas = root.querySelector("#dashboardActivityChart");
    const payload = parseChartPayload(canvas);
    if (!canvas || !payload || !Array.isArray(payload.labels) || payload.labels.length < 2 || !window.Chart) return;

    const ctx = canvas.getContext("2d");
    if (!ctx) return;

    const gradient = ctx.createLinearGradient(0, 0, 0, 320);
    gradient.addColorStop(0, "rgba(34, 211, 238, 0.28)");
    gradient.addColorStop(1, "rgba(34, 211, 238, 0.01)");

    rememberChart(new window.Chart(ctx, {
      data: {
        labels: payload.labels,
        datasets: [
          {
            type: "bar",
            label: "Posts novos",
            data: payload.posts || [],
            yAxisID: "count",
            backgroundColor: "rgba(217, 70, 239, 0.36)",
            borderColor: "rgba(217, 70, 239, 0.82)",
            borderWidth: 1,
            borderRadius: 8,
            maxBarThickness: 28,
            order: 4,
          },
          {
            type: "bar",
            label: "Inscricoes",
            data: payload.subscriptions || [],
            yAxisID: "count",
            backgroundColor: "rgba(16, 185, 129, 0.28)",
            borderColor: "rgba(16, 185, 129, 0.84)",
            borderWidth: 1,
            borderRadius: 8,
            maxBarThickness: 28,
            order: 3,
          },
          {
            type: "line",
            label: "Views",
            data: payload.views || [],
            yAxisID: "views",
            borderColor: "rgba(34, 211, 238, 0.96)",
            backgroundColor: gradient,
            borderWidth: 3,
            fill: true,
            pointBackgroundColor: "#0f172a",
            pointBorderColor: "rgba(34, 211, 238, 0.96)",
            pointBorderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 5,
            tension: 0.35,
            order: 1,
          },
          {
            type: "line",
            label: "Media movel 7 dias",
            data: payload.movingAverage || [],
            yAxisID: "views",
            borderColor: "rgba(226, 232, 240, 0.64)",
            borderDash: [6, 6],
            borderWidth: 2,
            pointRadius: 0,
            pointHoverRadius: 4,
            tension: 0.35,
            order: 2,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: "index",
          intersect: false,
        },
        plugins: {
          legend: {
            position: "bottom",
            labels: {
              boxHeight: 9,
              boxWidth: 26,
              padding: 18,
              useBorderRadius: true,
            },
          },
          tooltip: {
            callbacks: {
              label(context) {
                return `${context.dataset.label}: ${formatMetric(context.parsed.y)}`;
              },
            },
          },
        },
        scales: {
          x: {
            grid: {
              color: "rgba(51, 65, 85, 0.28)",
            },
            ticks: {
              maxRotation: 0,
              autoSkip: true,
              maxTicksLimit: 10,
            },
          },
          views: {
            type: "linear",
            position: "left",
            beginAtZero: true,
            grid: {
              color: "rgba(148, 163, 184, 0.12)",
            },
            ticks: {
              callback: formatMetric,
            },
          },
          count: {
            type: "linear",
            position: "right",
            beginAtZero: true,
            grid: {
              drawOnChartArea: false,
            },
            ticks: {
              precision: 0,
              callback: formatMetric,
            },
          },
        },
      },
    }));
  }

  function renderTodayChart(root) {
    const canvas = root.querySelector("#dashboardTodayChart");
    const payload = parseChartPayload(canvas);
    if (!canvas || !payload || !Array.isArray(payload.labels) || payload.labels.length === 0 || !window.Chart) return;

    rememberChart(new window.Chart(canvas, {
      type: "bar",
      data: {
        labels: payload.labels,
        datasets: [
          {
            label: "Hoje",
            data: payload.values || [],
            backgroundColor: [
              "rgba(96, 165, 250, 0.62)",
              "rgba(34, 211, 238, 0.62)",
              "rgba(52, 211, 153, 0.62)",
              "rgba(192, 132, 252, 0.62)",
            ],
            borderColor: [
              "rgba(96, 165, 250, 0.95)",
              "rgba(34, 211, 238, 0.95)",
              "rgba(52, 211, 153, 0.95)",
              "rgba(192, 132, 252, 0.95)",
            ],
            borderWidth: 1,
            borderRadius: 8,
            barThickness: 16,
          },
        ],
      },
      options: {
        indexAxis: "y",
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
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
            grid: {
              color: "rgba(51, 65, 85, 0.24)",
            },
            ticks: {
              callback: formatMetric,
            },
          },
          y: {
            grid: {
              display: false,
            },
          },
        },
      },
    }));
  }

  function renderLinkSectionsChart(root) {
    const canvas = root.querySelector("#dashboardLinkSectionsChart");
    const payload = parseChartPayload(canvas);
    if (!canvas || !payload || !Array.isArray(payload.labels) || payload.labels.length === 0 || !window.Chart) return;

    rememberChart(new window.Chart(canvas, {
      type: "doughnut",
      data: {
        labels: payload.labels,
        datasets: [
          {
            data: payload.values || [],
            backgroundColor: [
              "rgba(34, 211, 238, 0.78)",
              "rgba(96, 165, 250, 0.78)",
              "rgba(52, 211, 153, 0.78)",
              "rgba(250, 204, 21, 0.78)",
              "rgba(192, 132, 252, 0.78)",
              "rgba(248, 113, 113, 0.78)",
            ],
            borderColor: "rgba(15, 23, 42, 0.92)",
            borderWidth: 3,
            hoverOffset: 5,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: "64%",
        plugins: {
          legend: {
            display: false,
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
  }

  function renderLinkTimelineChart(root) {
    const canvas = root.querySelector("#dashboardLinkTimelineChart");
    const payload = parseChartPayload(canvas);
    if (!canvas || !payload || !Array.isArray(payload.labels) || payload.labels.length < 2 || !window.Chart) return;

    rememberChart(new window.Chart(canvas, {
      type: "line",
      data: {
        labels: payload.labels,
        datasets: [
          {
            label: "Cliques",
            data: payload.clicks || [],
            borderColor: "rgba(34, 211, 238, 0.96)",
            backgroundColor: "rgba(34, 211, 238, 0.12)",
            borderWidth: 3,
            fill: true,
            pointRadius: 2,
            pointHoverRadius: 5,
            tension: 0.35,
          },
          {
            label: "Cliques unicos",
            data: payload.unique || [],
            borderColor: "rgba(96, 165, 250, 0.86)",
            backgroundColor: "rgba(96, 165, 250, 0.06)",
            borderWidth: 2,
            borderDash: [6, 6],
            fill: false,
            pointRadius: 2,
            pointHoverRadius: 5,
            tension: 0.35,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: "index",
          intersect: false,
        },
        plugins: {
          legend: {
            position: "bottom",
            labels: {
              boxHeight: 7,
              boxWidth: 20,
              padding: 8,
              useBorderRadius: true,
            },
          },
          tooltip: {
            callbacks: {
              label(context) {
                return `${context.dataset.label}: ${formatMetric(context.parsed.y)}`;
              },
            },
          },
        },
        scales: {
          x: {
            grid: {
              color: "rgba(51, 65, 85, 0.22)",
            },
            ticks: {
              maxRotation: 0,
              autoSkip: true,
              maxTicksLimit: 9,
            },
          },
          y: {
            beginAtZero: true,
            grid: {
              color: "rgba(148, 163, 184, 0.1)",
            },
            ticks: {
              precision: 0,
              callback: formatMetric,
            },
          },
        },
      },
    }));
  }

  function renderEditorialChart(root) {
    const canvas = root.querySelector("#dashboardEditorialChart");
    const payload = parseChartPayload(canvas);
    if (!canvas || !payload || !Array.isArray(payload.labels) || payload.labels.length === 0 || !window.Chart) return;

    rememberChart(new window.Chart(canvas, {
      type: "bar",
      data: {
        labels: payload.labels,
        datasets: [
          {
            label: "Destaques",
            data: payload.values || [],
            backgroundColor: [
              "rgba(34, 211, 238, 0.58)",
              "rgba(232, 121, 249, 0.58)",
              "rgba(250, 204, 21, 0.58)",
            ],
            borderColor: [
              "rgba(34, 211, 238, 0.92)",
              "rgba(232, 121, 249, 0.92)",
              "rgba(250, 204, 21, 0.92)",
            ],
            borderWidth: 1,
            borderRadius: 8,
            barThickness: 18,
          },
        ],
      },
      options: {
        indexAxis: "y",
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            callbacks: {
              afterLabel(context) {
                const title = Array.isArray(payload.titles) ? payload.titles[context.dataIndex] : "";
                return title ? `Post: ${title}` : "";
              },
              label(context) {
                const units = Array.isArray(payload.units) ? payload.units : [];
                const unit = units[context.dataIndex] || "interacoes";
                return `${formatMetric(context.parsed.x)} ${unit}`;
              },
            },
          },
        },
        scales: {
          x: {
            beginAtZero: true,
            grid: {
              color: "rgba(51, 65, 85, 0.24)",
            },
            ticks: {
              callback: formatMetric,
            },
          },
          y: {
            grid: {
              display: false,
            },
          },
        },
      },
    }));
  }

  function normalizeHexColor(value, fallback) {
    return /^#[0-9a-f]{6}$/i.test(String(value || "")) ? value : fallback;
  }

  function hexToRgba(hex, alpha) {
    const normalized = normalizeHexColor(hex, "#22d3ee").replace("#", "");
    const red = parseInt(normalized.slice(0, 2), 16);
    const green = parseInt(normalized.slice(2, 4), 16);
    const blue = parseInt(normalized.slice(4, 6), 16);
    return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
  }

  function renderCategoriesChart(root) {
    const canvas = root.querySelector("#dashboardCategoriesChart");
    const payload = parseChartPayload(canvas);
    if (!canvas || !payload || !Array.isArray(payload.labels) || payload.labels.length === 0 || !window.Chart) return;

    const colors = Array.isArray(payload.colors) ? payload.colors : [];
    const labels = Array.isArray(payload.labels)
      ? payload.labels.map((label) => compactLabel(label, 18))
      : [];

    rememberChart(new window.Chart(canvas, {
      type: "bar",
      data: {
        labels,
        datasets: [
          {
            label: "Views",
            data: payload.views || [],
            backgroundColor: colors.map((color) => hexToRgba(color, 0.52)),
            borderColor: colors.map((color) => hexToRgba(color, 0.92)),
            borderWidth: 1,
            borderRadius: 6,
            barThickness: 12,
          },
          {
            label: "Posts",
            data: payload.posts || [],
            backgroundColor: "rgba(148, 163, 184, 0.2)",
            borderColor: "rgba(203, 213, 225, 0.5)",
            borderWidth: 1,
            borderRadius: 6,
            barThickness: 8,
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
            labels: {
              boxHeight: 7,
              boxWidth: 18,
              padding: 6,
              useBorderRadius: true,
            },
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
            grid: {
              color: "rgba(51, 65, 85, 0.24)",
            },
            ticks: {
              callback: formatMetric,
            },
          },
          y: {
            grid: {
              display: false,
            },
            ticks: {
              font: {
                size: 11,
              },
            },
          },
        },
      },
    }));
  }

  function renderCharts(root) {
    chartDefaults();
    renderActivityChart(root);
    renderTodayChart(root);
    renderLinkSectionsChart(root);
    renderLinkTimelineChart(root);
    renderEditorialChart(root);
    renderCategoriesChart(root);
  }

  function dashboardUrl(form) {
    const params = new URLSearchParams(new FormData(form));
    const endpoint = form.dataset.dashboardEndpoint || form.action;
    return `${endpoint}${endpoint.includes("?") ? "&" : "?"}${params.toString()}`;
  }

  function browserUrl(form) {
    const params = new URLSearchParams(new FormData(form));
    const url = new URL(form.action, window.location.origin);
    url.search = params.toString();
    return `${url.pathname}${url.search}`;
  }

  function setLoading(root, isLoading) {
    root.classList.toggle("dashboard-ajax-loading", isLoading);
    root.setAttribute("aria-busy", isLoading ? "true" : "false");

    const button = root.querySelector("#js-apply-range");
    if (button) {
      button.disabled = isLoading;
      button.classList.toggle("opacity-70", isLoading);
      button.classList.toggle("cursor-wait", isLoading);
    }
  }

  function replaceDashboard(html) {
    const currentRoot = document.getElementById("adminDashboardRoot");
    if (!currentRoot || typeof html !== "string" || html.trim() === "") return null;

    const template = document.createElement("template");
    template.innerHTML = html.trim();
    const nextRoot = template.content.querySelector("#adminDashboardRoot");
    if (!nextRoot) return null;

    destroyCharts();
    currentRoot.replaceWith(nextRoot);
    initDashboard(nextRoot);
    return nextRoot;
  }

  async function submitDateRange(event) {
    event.preventDefault();

    const form = event.currentTarget;
    const root = form.closest("#adminDashboardRoot");
    const startInput = form.querySelector("#js-start-date");
    const endInput = form.querySelector("#js-end-date");
    if (!root || !startInput || !endInput) return;

    clampValues(startInput, endInput);
    setLoading(root, true);

    try {
      const response = await fetch(dashboardUrl(form), {
        headers: {
          "Accept": "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "same-origin",
      });

      if (!response.ok) {
        throw new Error(`Dashboard request failed: ${response.status}`);
      }

      const payload = await response.json();
      if (!payload || payload.ok !== true || typeof payload.html !== "string") {
        throw new Error("Dashboard response missing HTML");
      }

      const nextRoot = replaceDashboard(payload.html);
      window.history.replaceState({}, "", browserUrl(form));
      if (nextRoot) {
        nextRoot.focus({ preventScroll: true });
      }
    } catch (error) {
      console.warn(error);
      form.submit();
    } finally {
      const activeRoot = document.getElementById("adminDashboardRoot");
      if (activeRoot) {
        setLoading(activeRoot, false);
      }
    }
  }

  function bindDateRangeForm(root) {
    const form = root.querySelector("#js-date-range-form");
    if (!form || form.dataset.ajaxBound === "1") return;

    form.dataset.ajaxBound = "1";
    form.addEventListener("submit", submitDateRange);
  }

  function initDashboard(root) {
    if (!root) return;

    if (!root.hasAttribute("tabindex")) {
      root.setAttribute("tabindex", "-1");
    }

    bindDateRangeForm(root);
    renderCharts(root);
  }

  initDashboard(document.getElementById("adminDashboardRoot"));
})();
