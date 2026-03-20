/* eslint-disable no-console */
/**
 * -----------------------------------------------------------------------------
 * @file        public/assets/js/admin-dashboard.js
 * @project     Estrategia Nerd
 * @purpose     Dashboard Admin (range por data + polling + labels X menos emboladas)
 * -----------------------------------------------------------------------------
 */

(function () {
  "use strict";

  const path = window.location.pathname || "";
  if (!/^\/admin(?:\/dashboard)?\/?$/.test(path)) return;

  const $ = (sel, root = document) => root.querySelector(sel);

  const form = $("#js-date-range-form");
  const startInput = $("#js-start-date");
  const endInput = $("#js-end-date");
  const applyBtn = $("#js-apply-range");

  const liveBadge = document.createElement("span");
  liveBadge.textContent = "LIVE";
  liveBadge.style.cssText =
    "display:inline-block;margin-left:8px;padding:2px 6px;border-radius:8px;font-size:11px;opacity:.85;";

  function parseYmd(s) {
    if (!s || typeof s !== "string") return null;
    const v = s.trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(v)) return null;
    const dt = new Date(v + "T00:00:00");
    if (Number.isNaN(dt.getTime())) return null;
    return v;
  }

  function toDate(s) {
    return new Date(s + "T00:00:00");
  }

  function fmtYmd(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
  }

  function clampRange90(startYmd, endYmd) {
    const ds = toDate(startYmd);
    const de = toDate(endYmd);
    if (ds > de) return clampRange90(endYmd, startYmd);

    const diffDays = Math.floor((de - ds) / 86400000) + 1;
    if (diffDays <= 90) return [startYmd, endYmd];

    const ds2 = new Date(de);
    ds2.setDate(ds2.getDate() - 89);
    return [fmtYmd(ds2), endYmd];
  }

  function applyDynamicBounds() {
    const s = parseYmd(startInput?.value);
    const e = parseYmd(endInput?.value);

    if (!startInput || !endInput) return;

    if (s) {
      endInput.min = s;
      const ds = toDate(s);
      const maxEnd = new Date(ds);
      maxEnd.setDate(maxEnd.getDate() + 89);
      endInput.max = fmtYmd(maxEnd);
    }

    if (e) {
      startInput.max = e;
      const de = toDate(e);
      const minStart = new Date(de);
      minStart.setDate(minStart.getDate() - 89);
      startInput.min = fmtYmd(minStart);
    }
  }

  function ensureClampInputs() {
    const s = parseYmd(startInput?.value);
    const e = parseYmd(endInput?.value);
    if (!s || !e) return;

    const [cs, ce] = clampRange90(s, e);
    if (cs !== s) startInput.value = cs;
    if (ce !== e) endInput.value = ce;
  }

  function buildApiUrl() {
    const url = new URL(window.location.href);
    url.pathname = url.pathname.replace(
      /^\/admin(?:\/dashboard)?\/?$/,
      "/admin/api/dashboard"
    );
    return url;
  }

  function updateUrlQuery(start, end) {
    const url = new URL(window.location.href);
    url.searchParams.set("start", start);
    url.searchParams.set("end", end);
    url.searchParams.delete("days");
    window.history.replaceState({}, "", url.toString());
  }

  function pickLabelStep(rangeDays) {
    if (rangeDays <= 30) return 5;
    if (rangeDays <= 60) return 7;
    return 10;
  }

  function thinLabels(labels, rangeDays) {
    const step = pickLabelStep(rangeDays);
    const n = labels.length;
    const keep = new Array(n).fill(false);
    if (n === 0) return keep;
    keep[0] = true;
    keep[n - 1] = true;
    for (let i = 0; i < n; i += step) keep[i] = true;
    return keep;
  }

  function findStatCardByLabel(labelText) {
    const cards = document.querySelectorAll(".stat-card");
    for (const c of cards) {
      const t = c.querySelector(".stat-title");
      if (!t) continue;
      if (t.textContent && t.textContent.trim() === labelText) return c;
    }
    return null;
  }

  function setStatValue(card, valueText) {
    const v = card?.querySelector(".stat-value");
    if (v) v.textContent = valueText;
  }

  function setStatDelta(card, deltaText) {
    const d = card?.querySelector(".stat-delta");
    if (d) d.textContent = deltaText;
  }

  function updateKpis(data) {
    const viewsCard = findStatCardByLabel("Views Totais");
    if (viewsCard) {
      setStatValue(viewsCard, String(data.total_views ?? 0));
      const dp = data.views_delta_percent;
      if (dp === null || typeof dp === "undefined") {
        setStatDelta(viewsCard, "");
      } else {
        const sign = dp > 0 ? "+" : "";
        setStatDelta(viewsCard, `${sign}${dp}%`);
      }
    }

    const postsCard = findStatCardByLabel("Total Posts");
    if (postsCard) setStatValue(postsCard, String(data.total_posts ?? 0));

    const subsCard = findStatCardByLabel("Newsletter");
    if (subsCard) setStatValue(subsCard, String(data.total_inscritos ?? 0));
  }

  function updateRangeTitle(data) {
    const el = $("#js-range-title");
    if (!el) return;

    const start = data.start || "";
    const end = data.end || "";
    const days = data.days || 30;
    el.textContent = `Atividade (${start} a ${end} • ${days} dias)`;
  }

  function updateChart(data) {
    const series = Array.isArray(data.views_series) ? data.views_series : [];
    const svg = $("#js-views-svg");
    if (!svg) return;

    const labels = series.map((p) => p.label || "");
    const rangeDays = Number(data.days || labels.length || 30);
    const keep = thinLabels(labels, rangeDays);

    const labelNodes = svg.querySelectorAll("[data-x-label]");
    labelNodes.forEach((node, idx) => {
      node.textContent = labels[idx] || "";
      node.style.display = keep[idx] ? "" : "none";
    });

    const poly = svg.querySelector("polyline");
    if (!poly) return;

    const values = series.map((p) => Number(p.views || 0));
    const max = Math.max(1, ...values);
    const w = 820;
    const h = 220;
    const pad = 18;

    const n = values.length;
    if (n <= 1) {
      poly.setAttribute("points", "");
      return;
    }

    const dx = (w - pad * 2) / (n - 1);
    const points = values
      .map((v, i) => {
        const x = pad + i * dx;
        const y = pad + (h - pad * 2) * (1 - v / max);
        return `${x.toFixed(1)},${y.toFixed(1)}`;
      })
      .join(" ");

    poly.setAttribute("points", points);
  }

  async function fetchLive() {
    const apiUrl = buildApiUrl();
    const start = parseYmd(startInput?.value);
    const end = parseYmd(endInput?.value);
    if (start) apiUrl.searchParams.set("start", start);
    if (end) apiUrl.searchParams.set("end", end);

    const res = await fetch(apiUrl.toString(), {
      headers: { "Accept": "application/json" },
      cache: "no-store",
    });
    const json = await res.json();
    if (!json || !json.ok) return;

    const data = json.data || {};
    updateKpis(data);
    updateRangeTitle(data);
    updateChart(data);
  }

  function enablePolling() {
    const anchor = $("#js-live-anchor");
    if (anchor && !anchor.querySelector("span")) anchor.appendChild(liveBadge);

    const intervalMs = 15000;
    setInterval(() => {
      fetchLive().catch((e) => console.warn("live update failed:", e));
    }, intervalMs);
  }

  if (startInput) {
    startInput.addEventListener("change", () => {
      applyDynamicBounds();
      ensureClampInputs();
      applyDynamicBounds();
    });
  }

  if (endInput) {
    endInput.addEventListener("change", () => {
      applyDynamicBounds();
      ensureClampInputs();
      applyDynamicBounds();
    });
  }

  if (form) {
    form.addEventListener("submit", (ev) => {
      ev.preventDefault();

      applyDynamicBounds();
      ensureClampInputs();
      applyDynamicBounds();

      const s = parseYmd(startInput?.value);
      const e = parseYmd(endInput?.value);
      if (!s || !e) return;

      updateUrlQuery(s, e);
      fetchLive().catch((err) => console.warn("fetch failed:", err));
    });
  }

  applyDynamicBounds();
  ensureClampInputs();
  applyDynamicBounds();

  enablePolling();
})();