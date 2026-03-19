/**
 * -----------------------------------------------------------------------------
 * @file        public/assets/js/admin-dashboard.js
 * @project     Estrategia Nerd
 * @author      Taren Felipe Ribeiro
 * @version     1.0.0
 * @purpose     Atualização “live” do Dashboard Admin
 * @description Polling + troca de período (7/14/30) sem refresh, atualizando KPIs/Hoje/Destaques/Atividade + gráfico SVG.
 * @usage       Carregar no layout admin somente em /admin (defer).
 * @notes       Mostra badge LIVE (OK/ERRO) para debug sem DevTools.
 * -----------------------------------------------------------------------------
 */

(function () {
  const POLL_MS = 10000; // 10s

  function fmtInt(n) {
    return Number(n || 0).toLocaleString("pt-BR");
  }
  function fmtK(n) {
    const v = Number(n || 0);
    if (v >= 1000) return (Math.round((v / 1000) * 10) / 10).toString().replace(".", ",") + "k";
    return String(v);
  }
  function fmtPct1(n) {
    const v = Number(n || 0);
    return v.toLocaleString("pt-BR", { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + "%";
  }
  function fmtPct2(n) {
    const v = Number(n || 0);
    return v.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + "%";
  }
  function clamp(v, min, max) {
    return Math.max(min, Math.min(max, v));
  }

  // DEFAULT 30 (igual controller)
  function normalizeDays(days) {
    const raw = (days ?? "").toString().trim();
    if (raw === "") return 30;
    const d = parseInt(raw, 10);
    return [7, 14, 30].includes(d) ? d : 30;
  }

  function getDaysFromUrl() {
    const url = new URL(window.location.href);
    return normalizeDays(url.searchParams.get("days"));
  }

  function setDaysInUrl(days) {
    const url = new URL(window.location.href);
    url.searchParams.set("days", String(days));
    window.history.pushState({ days }, "", url.toString());
  }

  function buildApiUrl(days) {
    const url = new URL(window.location.href);
    url.pathname = url.pathname.replace(/\/admin\/?$/, "/admin/api/dashboard");
    url.searchParams.set("days", String(days));
    return url.toString();
  }

  function ensureLiveBadge() {
    let badge = document.getElementById("liveBadge");
    if (badge) return badge;

    badge = document.createElement("div");
    badge.id = "liveBadge";
    badge.style.position = "fixed";
    badge.style.right = "14px";
    badge.style.bottom = "14px";
    badge.style.zIndex = "9999";
    badge.style.padding = "8px 10px";
    badge.style.borderRadius = "12px";
    badge.style.fontSize = "12px";
    badge.style.fontWeight = "800";
    badge.style.letterSpacing = "0.08em";
    badge.style.textTransform = "uppercase";
    badge.style.background = "rgba(2,6,23,0.85)";
    badge.style.border = "1px solid rgba(34,211,238,0.25)";
    badge.style.color = "rgb(226,232,240)";
    badge.textContent = "LIVE: …";
    document.body.appendChild(badge);
    return badge;
  }

  function setBadgeOk(msg) {
    const b = ensureLiveBadge();
    b.style.borderColor = "rgba(34,211,238,0.35)";
    b.style.color = "rgb(165,243,252)";
    b.textContent = `LIVE: OK • ${msg}`;
  }

  function setBadgeErr(msg) {
    const b = ensureLiveBadge();
    b.style.borderColor = "rgba(248,113,113,0.45)";
    b.style.color = "rgb(254,202,202)";
    b.textContent = `LIVE: ERRO • ${msg}`;
  }

  async function fetchDashboard(days) {
    const apiUrl = buildApiUrl(days);

    const res = await fetch(apiUrl, {
      method: "GET",
      headers: { Accept: "application/json" },
      cache: "no-store",
      credentials: "same-origin",
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const json = await res.json();
    if (!json || json.ok !== true) throw new Error("JSON inválido");
    return json.data || {};
  }

  function setText(el, text) {
    if (!el) return;
    el.textContent = text;
  }

  function setActiveDaysButton(days) {
    const buttons = document.querySelectorAll('a[href*="days="]');
    buttons.forEach((a) => {
      const href = a.getAttribute("href") || "";
      const m = href.match(/days=(\d+)/);
      if (!m) return;

      const d = normalizeDays(m[1]);
      const active = d === days;

      a.classList.toggle("bg-cyan-500/20", active);
      a.classList.toggle("border-cyan-400/40", active);
      a.classList.toggle("text-cyan-200", active);

      a.classList.toggle("bg-slate-800/40", !active);
      a.classList.toggle("border-slate-700", !active);
      a.classList.toggle("text-slate-300", !active);
    });
  }

  // ---------- UI updates ----------

  function findStatCardByLabel(label) {
    const cards = Array.from(document.querySelectorAll(".stat-card"));
    return cards.find((c) => c.textContent.includes(label)) || null;
  }

  function updateStatCards(data) {
    const postsCard = findStatCardByLabel("Total Posts");
    if (postsCard) {
      setText(postsCard.querySelector(".stat-value"), fmtInt(data.total_posts));
      const badges = postsCard.querySelectorAll(".status-badge");
      if (badges[0]) setText(badges[0], `${fmtInt(data.posts_publicados)} pub`);
      if (badges[1]) setText(badges[1], `${fmtInt(data.posts_rascunho)} rasc`);
      if (badges[2]) setText(badges[2], `${fmtInt(data.posts_agendados)} agend`);
    }

    const viewsCard = findStatCardByLabel("Views Totais");
    if (viewsCard) {
      setText(viewsCard.querySelector(".stat-value"), fmtK(data.total_views));
      const pills = viewsCard.querySelectorAll("span.px-2.py-1.rounded-full");
      if (pills[0]) setText(pills[0], `+${fmtK(data.views_hoje)} hoje`);
      if (pills[1]) setText(pills[1], `+${fmtK(data.views_semana)} 7d`);
    }

    const likesCard = findStatCardByLabel("Curtidas");
    if (likesCard) {
      setText(likesCard.querySelector(".stat-value"), fmtK(data.likes_total));
      const small = likesCard.querySelector(".text-xs");
      if (small) {
        const eng = Number(data.engagement_rate || 0);
        small.innerHTML =
          `💬 ${fmtK(data.total_comentarios)} comentários` +
          (eng > 0 ? `<br>📊 ${fmtPct2(eng)} engajamento` : "");
      }
    }

    const newsCard = findStatCardByLabel("Inscritos Ativos");
    if (newsCard) {
      setText(newsCard.querySelector(".stat-value"), fmtK(data.total_inscritos));
      const small = newsCard.querySelector(".text-xs");
      if (small) setText(small, `+${fmtInt(data.inscritos_novos_30dias)} últimos 30 dias`);
    }
  }

  function updateHojeBox(data) {
    const title = Array.from(document.querySelectorAll("h3")).find((h) => h.textContent.includes("Hoje"));
    const card = title ? title.closest('.bg-slate-900\\/50') : null;
    if (!card) return;

    const rows = card.querySelectorAll(".space-y-3 > div.flex");
    if (rows[0]) setText(rows[0].querySelector("span.font-bold"), fmtInt(data.posts_hoje));
    if (rows[1]) setText(rows[1].querySelector("span.font-bold"), fmtK(data.views_hoje));
    if (rows[2]) setText(rows[2].querySelector("span.font-bold"), fmtInt(data.inscritos_hoje));
    if (rows[3]) setText(rows[3].querySelector("span.font-bold"), fmtInt(data.comentarios_hoje));
    if (rows[4]) setText(rows[4].querySelector("span.font-bold"), fmtInt(data.comentarios_pendentes));

    const rateWrap = card.querySelector(".progress-bar")?.parentElement;
    if (rateWrap) {
      const rate = Number(data.taxa_aprovacao_comentarios || 0);
      const label = rateWrap.querySelector(".text-green-400.font-bold");
      const fill = rateWrap.querySelector(".progress-fill");
      if (label) setText(label, fmtPct2(rate));
      if (fill) fill.style.width = `${clamp(rate, 0, 100)}%`;
    }
  }

  function updateDestaques(data) {
    const title = Array.from(document.querySelectorAll("h3")).find((h) => h.textContent.includes("Posts em Destaque"));
    const wrap = title ? title.closest('.bg-slate-900\\/50') : null;
    if (!wrap) return;

    const boxes = wrap.querySelectorAll(".grid.md\\:grid-cols-3 > div");
    if (boxes.length < 3) return;

    const tv = data.top_post_views || null;
    const tl = data.top_post_likes || null;
    const tc = data.top_post_comments || null;

    function setBox(box, item, valueKey, valueLabel) {
      const h4 = box.querySelector("h4");
      const big = box.querySelector(".text-2xl");
      const small = box.querySelector(".text-xs.text-gray-500");

      if (!item) {
        if (h4) setText(h4, "Nenhum post");
        if (big) setText(big, "0");
        if (small) setText(small, valueLabel);
        return;
      }
      if (h4) {
        h4.textContent = item.titulo || "—";
        h4.title = item.titulo || "";
      }
      if (big) setText(big, fmtK(item[valueKey] || 0));
      if (small) setText(small, valueLabel);
    }

    setBox(boxes[0], tv, "views", "visualizações");
    setBox(boxes[1], tl, "curtidas", "curtidas");
    setBox(boxes[2], tc, "comentarios_count", "comentários");
  }

  // ---------- Δ (deltas) ----------

  function arrowFor(v) {
    if (v > 0) return "↗";
    if (v < 0) return "↘";
    return "→";
  }

  function updateDeltaBlock(wrapper, values) {
    // Layout desejado:
    // Views
    // ↗ +xx,x%
    const blocks = wrapper.querySelectorAll(":scope > div");
    if (blocks.length < 3) return;

    const keys = ["views", "posts_novos", "inscricoes"];
    blocks.forEach((b, idx) => {
      const key = keys[idx];
      const valEl = b.querySelector(".mt-1.text-sm.font-black") || b.querySelector("div:nth-child(2)");
      if (!valEl) return;

      const v = values[key];

      // v pode ser number, null (sem base), undefined
      if (v === null || v === undefined) {
        // mostra valor real por diferença absoluta, se existir
        const abs = (values.__abs && typeof values.__abs[key] === "number") ? values.__abs[key] : null;
        if (abs === null) {
          valEl.textContent = "→ —";
        } else {
          const sign = abs >= 0 ? "+" : "";
          valEl.textContent = `${abs > 0 ? "↗" : (abs < 0 ? "↘" : "→")} Δ ${sign}${fmtInt(abs)}`;
        }
        valEl.classList.remove("text-emerald-300", "text-red-300");
        valEl.classList.add("text-slate-300");
        return;
      }

      const num = Number(v || 0);
      valEl.textContent = `${arrowFor(num)} ${(num >= 0 ? "+" : "")}${fmtPct1(num)}`;
      valEl.classList.remove("text-emerald-300", "text-red-300", "text-slate-300");
      valEl.classList.add(num > 0 ? "text-emerald-300" : (num < 0 ? "text-red-300" : "text-slate-300"));
    });
  }

  function updateAtividadeDeltas(data) {
    const activityTitle = Array.from(document.querySelectorAll("h3")).find((h) => h.textContent.includes("Atividade"));
    const wrap = activityTitle ? activityTitle.closest('.bg-slate-900\\/50') : null;
    if (!wrap) return;

    const deltaPercent = (data.chart && data.chart.delta_percent) ? data.chart.delta_percent : {};
    const deltaAbs = (data.chart && data.chart.delta_abs) ? data.chart.delta_abs : {};

    // procura o container que tem 3 blocos (Views/Posts/Inscrições)
    // (o seu layout é "flex flex-wrap justify-end gap-4 text-right")
    const deltaWrapper =
      wrap.querySelector(".flex.flex-wrap.justify-end.gap-4.text-right") ||
      wrap.querySelector('[data-role="deltas"]');

    if (!deltaWrapper) return;

    updateDeltaBlock(deltaWrapper, {
      views: deltaPercent.views,
      posts_novos: deltaPercent.posts_novos,
      inscricoes: deltaPercent.inscricoes,
      __abs: {
        views: deltaAbs.views,
        posts_novos: deltaAbs.posts_novos,
        inscricoes: deltaAbs.inscricoes,
      }
    });
  }

  // ---------- Chart SVG redraw ----------

  function dayLabel(iso) {
    // iso: YYYY-MM-DD
    if (!iso) return "--/--";
    const parts = String(iso).split("-");
    if (parts.length !== 3) return "--/--";
    return `${parts[2]}/${parts[1]}`;
  }

  function clearNode(node) {
    while (node.firstChild) node.removeChild(node.firstChild);
  }

  function createSvgEl(tag) {
    return document.createElementNS("http://www.w3.org/2000/svg", tag);
  }

  function redrawChart(data) {
    const svg = document.getElementById("activitySvg");
    if (!svg) return;

    const series = (data.chart && Array.isArray(data.chart.series)) ? data.chart.series : [];
    const has = series.length >= 2;

    // base dimensions from viewBox
    const vb = (svg.getAttribute("viewBox") || "0 0 920 260").split(" ").map(Number);
    const w = vb[2] || 920;
    const h = vb[3] || 260;

    const padX = 24;
    const padY = 18;
    const plotW = w - padX * 2;
    const plotH = h - padY * 2;

    const n = Math.max(2, series.length);
    const stepX = plotW / (n - 1);

    const views = has ? series.map((r) => Number(r.views || 0)) : [0, 0];
    const posts = has ? series.map((r) => Number(r.posts_novos || 0)) : [0, 0];
    const subs = has ? series.map((r) => Number(r.inscricoes || 0)) : [0, 0];
    const ma7 = has ? series.map((r) => (r.views_ma7 ?? null)) : [null, null];

    const maxViews = Math.max(1, ...views);
    const maxPosts = Math.max(1, ...posts);
    const maxSubs = Math.max(1, ...subs);

    // points for views polyline
    const pts = [];
    for (let i = 0; i < n; i++) {
      const x = padX + i * stepX;
      const v = views[i] ?? 0;
      const y = padY + (plotH - (v / maxViews) * plotH);
      pts.push([x, y]);
    }

    const polyStr = pts.map((p) => `${p[0].toFixed(2)},${p[1].toFixed(2)}`).join(" ");
    const areaStr = `${polyStr} ${(w - padX).toFixed(2)},${(h - padY).toFixed(2)} ${padX.toFixed(2)},${(h - padY).toFixed(2)}`;

    // MA7
    const maPts = [];
    for (let i = 0; i < n; i++) {
      const mv = ma7[i];
      if (mv === null || mv === undefined) continue;
      const x = padX + i * stepX;
      const y = padY + (plotH - (Number(mv) / maxViews) * plotH);
      maPts.push(`${x.toFixed(2)},${y.toFixed(2)}`);
    }
    const maStr = maPts.join(" ");

    // rebuild SVG
    clearNode(svg);

    // grid lines
    for (let g = 0; g <= 4; g++) {
      const gy = padY + (g * (plotH / 4));
      const line = createSvgEl("line");
      line.setAttribute("x1", padX);
      line.setAttribute("y1", gy);
      line.setAttribute("x2", w - padX);
      line.setAttribute("y2", gy);
      line.setAttribute("stroke", "rgba(148,163,184,0.12)");
      line.setAttribute("stroke-width", "1");
      svg.appendChild(line);
    }

    // bars for posts (only if has series)
    if (has) {
      const barMaxH = plotH * 0.35;
      const barW = Math.min(26, Math.max(10, Math.round(stepX * 0.35)));

      for (let i = 0; i < series.length; i++) {
        const p = posts[i] ?? 0;
        const x = padX + i * stepX;
        const bh = p > 0 ? (p / maxPosts) * barMaxH : 0;
        const bx = x - barW / 2;
        const by = padY + (plotH - bh);

        const r = createSvgEl("rect");
        r.setAttribute("x", bx);
        r.setAttribute("y", by);
        r.setAttribute("width", barW);
        r.setAttribute("height", bh);
        r.setAttribute("rx", "6");
        r.setAttribute("fill", "rgba(217,70,239,0.35)");
        r.setAttribute("stroke", "rgba(217,70,239,0.25)");
        r.setAttribute("stroke-width", "1");
        svg.appendChild(r);
      }
    }

    // area
    const area = createSvgEl("polygon");
    area.setAttribute("points", areaStr);
    area.setAttribute("fill", "rgba(34,211,238,0.10)");
    svg.appendChild(area);

    // line
    const line = createSvgEl("polyline");
    line.setAttribute("points", polyStr);
    line.setAttribute("fill", "none");
    line.setAttribute("stroke", "rgba(34,211,238,0.95)");
    line.setAttribute("stroke-width", "3");
    line.setAttribute("stroke-linecap", "round");
    line.setAttribute("stroke-linejoin", "round");
    svg.appendChild(line);

    // ma7
    if (has && maStr) {
      const m = createSvgEl("polyline");
      m.setAttribute("points", maStr);
      m.setAttribute("fill", "none");
      m.setAttribute("stroke", "rgba(226,232,240,0.55)");
      m.setAttribute("stroke-width", "2");
      m.setAttribute("stroke-dasharray", "6 6");
      m.setAttribute("stroke-linecap", "round");
      m.setAttribute("stroke-linejoin", "round");
      svg.appendChild(m);
    }

    // subs points
    if (has) {
      for (let i = 0; i < series.length; i++) {
        const ins = subs[i] ?? 0;
        const x = padX + i * stepX;
        const y = padY + (plotH - (ins / maxSubs) * plotH);

        const c = createSvgEl("circle");
        c.setAttribute("cx", x);
        c.setAttribute("cy", y);
        c.setAttribute("r", "5");
        c.setAttribute("fill", "rgba(16,185,129,0.95)");
        c.setAttribute("stroke", "rgba(16,185,129,0.35)");
        c.setAttribute("stroke-width", "2");
        svg.appendChild(c);
      }
    }

    // update labels row under chart if exists
    const labelsRow = svg.parentElement?.querySelector(".mt-2.grid");
    if (labelsRow) {
      const dates = has ? series.map((r) => dayLabel(r.data)) : ["--/--", "--/--"];
      labelsRow.style.gridTemplateColumns = `repeat(${dates.length}, minmax(0, 1fr))`;
      labelsRow.innerHTML = dates.map((d) => `<div class="text-center text-xs text-gray-500">${d}</div>`).join("");
    }
  }

  function updateAtividadeTitleAndSummary(data, days) {
    const activityTitle = Array.from(document.querySelectorAll("h3")).find((h) => h.textContent.includes("Atividade"));
    const wrap = activityTitle ? activityTitle.closest('.bg-slate-900\\/50') : null;
    if (!wrap) return;

    if (activityTitle) activityTitle.textContent = `📈 Atividade (${days} dias)`;

    const cur = (data.chart && data.chart.current) ? data.chart.current : {};
    const summary = wrap.querySelector(".grid.sm\\:grid-cols-3");
    if (!summary) return;

    const cards = summary.querySelectorAll(":scope > div");

    if (cards[0]) {
      const big = cards[0].querySelector(".text-white.text-2xl.font-black");
      const small = cards[0].querySelector(".text-xs.text-slate-500");
      if (big) setText(big, fmtInt(cur.views || 0));
      if (small) setText(small, `média ${fmtInt(Math.round((cur.views || 0) / days))}/dia`);
    }
    if (cards[1]) {
      const big = cards[1].querySelector(".text-white.text-2xl.font-black");
      const small = cards[1].querySelector(".text-xs.text-slate-500");
      if (big) setText(big, fmtInt(cur.posts_novos || 0));
      if (small) setText(small, `média ${(Number(cur.posts_novos || 0) / days).toLocaleString("pt-BR", { maximumFractionDigits: 1 })}/dia`);
    }
    if (cards[2]) {
      const big = cards[2].querySelector(".text-white.text-2xl.font-black");
      const small = cards[2].querySelector(".text-xs.text-slate-500");
      if (big) setText(big, fmtInt(cur.inscricoes || 0));
      if (small) setText(small, `média ${(Number(cur.inscricoes || 0) / days).toLocaleString("pt-BR", { maximumFractionDigits: 1 })}/dia`);
    }
  }

  // ---------- Actions ----------

  async function refresh(days, { pushUrl = false } = {}) {
    const d = normalizeDays(days);
    try {
      if (pushUrl) setDaysInUrl(d);
      setActiveDaysButton(d);

      const data = await fetchDashboard(d);

      updateStatCards(data);
      updateHojeBox(data);
      updateDestaques(data);

      updateAtividadeTitleAndSummary(data, d);
      updateAtividadeDeltas(data);
      redrawChart(data);

      setBadgeOk(`days=${d} • ${new Date().toLocaleTimeString("pt-BR")}`);
    } catch (err) {
      setBadgeErr((err && err.message) ? err.message : "falha");
    }
  }

  function bindDaysButtons() {
    const buttons = document.querySelectorAll('a[href*="days="]');
    buttons.forEach((a) => {
      a.addEventListener("click", (ev) => {
        ev.preventDefault();
        const href = a.getAttribute("href") || "";
        const m = href.match(/days=(\d+)/);
        if (!m) return;
        refresh(m[1], { pushUrl: true });
      });
    });

    window.addEventListener("popstate", () => refresh(getDaysFromUrl()));
  }

  document.addEventListener("DOMContentLoaded", function () {
    ensureLiveBadge();
    bindDaysButtons();

    const initialDays = getDaysFromUrl(); // default 30
    setActiveDaysButton(initialDays);
    refresh(initialDays);

    setInterval(() => refresh(getDaysFromUrl()), POLL_MS);
  });
})();