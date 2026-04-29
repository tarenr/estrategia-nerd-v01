/* eslint-disable no-console */
/**
 * -----------------------------------------------------------------------------
 * @file        public/assets/js/admin-dashboard.js
 * @project     Estrategia Nerd
 * @purpose     Dashboard Admin (filtro por data com clamp de 90 dias)
 * -----------------------------------------------------------------------------
 */

(function () {
  "use strict";

  const path = window.location.pathname || "";
  if (!/^\/admin(?:\/dashboard)?\/?$/.test(path)) return;

  const form = document.getElementById("js-date-range-form");
  const startInput = document.getElementById("js-start-date");
  const endInput = document.getElementById("js-end-date");

  if (!form || !startInput || !endInput) return;

  const MS_DAY = 24 * 60 * 60 * 1000;

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

  function applyBounds() {
    const start = parseYmd(startInput.value);
    const end = parseYmd(endInput.value);

    if (start) {
      endInput.min = fmtYmd(start);
      const maxEnd = new Date(start.getTime() + (89 * MS_DAY));
      endInput.max = fmtYmd(maxEnd);
    } else {
      endInput.min = "";
      endInput.max = "";
    }

    if (end) {
      startInput.max = fmtYmd(end);
      const minStart = new Date(end.getTime() - (89 * MS_DAY));
      startInput.min = fmtYmd(minStart);
    } else {
      startInput.min = "";
      startInput.max = "";
    }
  }

  function clampValues() {
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

  startInput.addEventListener("change", function () {
    clampValues();
    applyBounds();
  });

  endInput.addEventListener("change", function () {
    clampValues();
    applyBounds();
  });

  form.addEventListener("submit", function () {
    clampValues();
    applyBounds();
  });

  clampValues();
  applyBounds();
})();
