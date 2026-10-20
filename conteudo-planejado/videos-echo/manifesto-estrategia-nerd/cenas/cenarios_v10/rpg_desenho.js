// Desenho do cenário do RPG em camadas — compartilhado por cenario_rpg.html e pela tela do monitor do Setup.
// RPGDesenho.criar() -> { L: camadas 1080x1920, compor(ctx) }
// Cenário do RPG (manifesto 25–30 s) — ruínas, aqueduto, cachoeira, torre com janela acesa,
// engrenagem de pedra com corrente, plataforma de tijolos com a alavanca. Tudo desenhado em Canvas 2D.
// Espaço do Echo: sobre a plataforma, x ~250–450 (fica livre).
(function () {
  'use strict';
  const W = 1080, H = 1920;
  const SEMENTE = 2510;
  const PISO = 1330;               // topo da plataforma
  const ALAVANCA = { x: 640 };     // base da alavanca sobre o piso
  const ENGRENAGEM = { x: 822, y: 1108, r: 132 };
  const JANELA = { x: 928, y: 596, w: 82, h: 200 };

  // ---------- utilitários ----------
  function rng(seed) {
    let a = seed >>> 0;
    return function () {
      a = (a + 0x6D2B79F5) | 0;
      let t = Math.imul(a ^ (a >>> 15), 1 | a);
      t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }
  const hex = (c) => { const n = parseInt(c.slice(1), 16); return [n >> 16 & 255, n >> 8 & 255, n & 255]; };
  const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
  const escala = (c, k) => 'rgb(' + hex(c).map((v) => clamp(Math.round(v * k), 0, 255)).join(',') + ')';
  const camada = () => { const c = document.createElement('canvas'); c.width = W; c.height = H; return c; };
  let OP = {};                                              // { dinamico, alvo } da instância sendo desenhada
  const LIXO = document.createElement('canvas').getContext('2d');   // consome sorteios sem desenhar
  function rrPath(ctx, x, y, w, h, r) {
    r = Math.max(0, Math.min(r, w / 2, h / 2));
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }
  function arcoPath(ctx, x, y, w, h) { // abertura em arco: topo em y, base em y+h
    ctx.moveTo(x, y + h);
    ctx.lineTo(x, y + w / 2);
    ctx.arc(x + w / 2, y + w / 2, w / 2, Math.PI, 0);
    ctx.lineTo(x + w, y + h);
    ctx.closePath();
  }
  function vgrad(ctx, y0, y1, stops) {
    const g = ctx.createLinearGradient(0, y0, 0, y1);
    stops.forEach(([o, c]) => g.addColorStop(o, c));
    return g;
  }

  // ---------- pedra ----------
  const PEDRA = {
    base: '#2f3862', vari: 0.1, bh: 54, bwMin: 66, bwMax: 128, gap: 5, raio: 6, falha: 0.025,
    argamassa: '#10152c', topo: 'rgba(160,185,240,0.32)', sombra: 'rgba(4,8,26,0.46)',
  };
  function tijolo(ctx, R, x, y, w, h, cor, o) {
    ctx.beginPath(); rrPath(ctx, x, y, w, h, o.raio); ctx.fillStyle = cor; ctx.fill();
    ctx.save(); ctx.clip();
    ctx.fillStyle = o.topo; ctx.fillRect(x, y, w, Math.max(3, h * 0.15));
    ctx.fillStyle = o.sombra; ctx.fillRect(x, y + h * 0.8, w, h * 0.2);
    ctx.fillStyle = 'rgba(0,0,0,0.12)'; ctx.fillRect(x + w * 0.9, y, w * 0.1, h);
    if (R() < 0.22) {
      const cx = R() < 0.5 ? x : x + w, cy = R() < 0.5 ? y : y + h, s = 6 + R() * 11;
      ctx.fillStyle = o.argamassa; ctx.beginPath();
      ctx.moveTo(cx, cy); ctx.lineTo(cx + (cx === x ? s : -s), cy); ctx.lineTo(cx, cy + (cy === y ? s : -s)); ctx.closePath(); ctx.fill();
    }
    if (R() < 0.16) {
      ctx.strokeStyle = 'rgba(6,10,28,0.55)'; ctx.lineWidth = 1.6; ctx.beginPath();
      let px = x + w * (0.2 + R() * 0.6), py = y + 2; ctx.moveTo(px, py);
      for (let i = 0; i < 3; i++) { px += (R() - 0.5) * 14; py += h * 0.3; ctx.lineTo(px, py); }
      ctx.stroke();
    }
    ctx.restore();
  }
  function parede(ctx, R, x0, y0, x1, y1, o) {
    o = Object.assign({}, PEDRA, o || {});
    ctx.save(); ctx.beginPath(); ctx.rect(x0, y0, x1 - x0, y1 - y0); ctx.clip();
    ctx.fillStyle = o.argamassa; ctx.fillRect(x0, y0, x1 - x0, y1 - y0);
    for (let y = y0; y < y1; y += o.bh) {
      let x = x0 - R() * o.bwMin * 0.9;
      while (x < x1) {
        const bw = o.bwMin + R() * (o.bwMax - o.bwMin);
        if (R() > o.falha) tijolo(ctx, R, x + o.gap / 2, y + o.gap / 2, bw - o.gap, o.bh - o.gap, escala(o.base, 1 + (R() - 0.5) * 2 * o.vari), o);
        x += bw;
      }
    }
    ctx.restore();
  }
  // luz de volume sobre uma área de parede (mais clara de um lado)
  function volume(ctx, x0, y0, x1, y1, ladoClaro, forca) {
    const g = ctx.createLinearGradient(x0, 0, x1, 0);
    const claro = `rgba(140,190,240,${forca})`, escuro = `rgba(2,6,22,${forca * 1.4})`;
    g.addColorStop(0, ladoClaro < 0 ? claro : escuro); g.addColorStop(1, ladoClaro < 0 ? escuro : claro);
    ctx.save(); ctx.globalCompositeOperation = 'source-atop'; ctx.fillStyle = g; ctx.fillRect(x0, y0, x1 - x0, y1 - y0); ctx.restore();
  }

  // ---------- vegetação ----------
  const VERDES = ['#1b5233', '#256f38', '#2f8a3e', '#4aa543', '#79c64a'];
  function folha(ctx, x, y, s, ang, cor, nervura) {
    ctx.save(); ctx.translate(x, y); ctx.rotate(ang);
    ctx.beginPath(); ctx.moveTo(0, 0);
    ctx.bezierCurveTo(s * 0.35, -s * 0.44, s * 0.85, -s * 0.34, s, 0);
    ctx.bezierCurveTo(s * 0.85, s * 0.34, s * 0.35, s * 0.44, 0, 0);
    ctx.closePath(); ctx.fillStyle = cor; ctx.fill();
    if (nervura) { ctx.strokeStyle = nervura; ctx.lineWidth = Math.max(0.8, s * 0.06); ctx.beginPath(); ctx.moveTo(s * 0.1, 0); ctx.lineTo(s * 0.85, 0); ctx.stroke(); }
    ctx.restore();
  }
  function hera(ctx, R, x, y, comp, tam) {
    const pts = []; const fase = R() * 6;
    for (let d = 0; d <= comp; d += 11) pts.push([x + Math.sin(d * 0.022 + fase) * 12 * (d / comp) + (R() - 0.5) * 2, y + d]);
    ctx.strokeStyle = '#173f27'; ctx.lineWidth = 3; ctx.lineCap = 'round';
    ctx.beginPath(); pts.forEach(([a, b], i) => i ? ctx.lineTo(a, b) : ctx.moveTo(a, b)); ctx.stroke();
    pts.forEach(([a, b], i) => {
      const t = i / pts.length, s = tam * (1 - t * 0.45) * (0.7 + R() * 0.5);
      const dir = i % 2 ? 1 : -1;
      const ang = (dir > 0 ? 0.55 : Math.PI - 0.55) + (R() - 0.5) * 0.7;
      folha(ctx, a, b, s, ang, VERDES[Math.floor(R() * VERDES.length)], 'rgba(8,36,18,0.45)');
      if (R() < 0.45) folha(ctx, a, b, s * 0.8, (dir > 0 ? Math.PI - 0.8 : 0.8) + (R() - 0.5) * 0.6, VERDES[Math.floor(R() * 4)], null);
    });
    const [ax, ay] = pts[pts.length - 1];
    folha(ctx, ax, ay, tam * 0.6, Math.PI / 2 + (R() - 0.5) * 0.4, VERDES[4], null);
  }
  function tufo(ctx, R, x, y, larg, tam) {
    // musgo deitado sobre a pedra + folhas para cima
    ctx.fillStyle = '#226b36';
    ctx.beginPath(); ctx.ellipse(x, y + 2, larg / 2, Math.max(4, tam * 0.3), 0, Math.PI, 0); ctx.fill();
    ctx.fillStyle = '#3f9a40';
    ctx.beginPath(); ctx.ellipse(x - larg * 0.08, y, larg * 0.36, Math.max(3, tam * 0.2), 0, Math.PI, 0); ctx.fill();
    const n = Math.round(larg / 4.5);
    for (let i = 0; i < n; i++) {
      const px = x + (R() - 0.5) * larg * 0.95, s = tam * (0.5 + R() * 0.7);
      folha(ctx, px, y + 3, s, -Math.PI / 2 + (R() - 0.5) * 1.7, VERDES[1 + Math.floor(R() * 4)], null);
    }
  }
  function copa(ctx, R, x, y, larg, alt, cBase, cLuz) { // y = base da copa
    const n = 6 + Math.floor(R() * 5);
    ctx.fillStyle = cBase; ctx.fillRect(x - larg * 0.42, y - alt * 0.32, larg * 0.84, alt * 0.32);
    for (let i = 0; i < n; i++) {
      const px = x + (R() - 0.5) * larg * 0.8, r = alt * (0.24 + R() * 0.3), py = y - r * 0.85 - R() * alt * 0.38;
      ctx.save();
      ctx.beginPath(); ctx.arc(px, py, r, 0, Math.PI * 2); ctx.fillStyle = cBase; ctx.fill(); ctx.clip();
      ctx.fillStyle = cLuz; ctx.beginPath(); ctx.arc(px + r * 0.34, py - r * 0.44, r * 0.66, 0, Math.PI * 2); ctx.fill();
      ctx.restore();
    }
  }
  function silhueta(ctx, R, cx, cy, raio, n, cor, borda) { // folhagem escura de primeiro plano
    for (let i = 0; i < n; i++) {
      const ang = -Math.PI / 2 + (R() - 0.5) * 2.6, s = raio * (0.45 + R() * 0.6);
      const bx = cx + (R() - 0.5) * raio * 0.9, by = cy + R() * raio * 0.25;
      ctx.save(); ctx.translate(bx, by); ctx.rotate(ang);
      ctx.beginPath(); ctx.moveTo(0, 0);
      ctx.bezierCurveTo(s * 0.3, -s * 0.5, s * 0.9, -s * 0.42, s, 0);
      ctx.bezierCurveTo(s * 0.9, s * 0.42, s * 0.3, s * 0.5, 0, 0);
      ctx.closePath(); ctx.fillStyle = cor; ctx.fill();
      ctx.strokeStyle = borda; ctx.lineWidth = 2; ctx.stroke();
      ctx.restore();
    }
  }
  function trevo(ctx, x, y, r, cor, borda) {
    ctx.fillStyle = cor; ctx.strokeStyle = borda; ctx.lineWidth = 2;
    [[0, -r * 0.62], [-r * 0.58, r * 0.2], [r * 0.58, r * 0.2]].forEach(([dx, dy]) => {
      ctx.beginPath(); ctx.arc(x + dx, y + dy, r * 0.55, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
    });
    ctx.beginPath(); ctx.moveTo(x, y + r * 0.2); ctx.lineTo(x + r * 0.15, y + r * 1.6); ctx.stroke();
  }

  // ---------- mecanismos ----------
  function engrenagem(ctx, R, cx, cy, r0, dentes, rot) {
    ctx.save(); ctx.translate(cx, cy); ctx.rotate(rot);
    const rd = r0 * 1.17;
    ctx.beginPath();
    for (let i = 0; i < dentes; i++) {
      const a0 = i / dentes * Math.PI * 2, da = Math.PI * 2 / dentes;
      [[r0, a0], [r0, a0 + da * 0.16], [rd, a0 + da * 0.27], [rd, a0 + da * 0.63], [r0, a0 + da * 0.74], [r0, a0 + da]]
        .forEach(([r, a], k) => { const x = r * Math.cos(a), y = r * Math.sin(a); (i === 0 && k === 0) ? ctx.moveTo(x, y) : ctx.lineTo(x, y); });
    }
    ctx.closePath();
    const g = ctx.createRadialGradient(r0 * 0.3, -r0 * 0.35, r0 * 0.1, 0, 0, rd);
    g.addColorStop(0, '#7a83ab'); g.addColorStop(0.6, '#4b5378'); g.addColorStop(1, '#2f3557');
    ctx.fillStyle = g; ctx.fill();
    ctx.lineWidth = 5; ctx.strokeStyle = '#181d38'; ctx.stroke();
    // anel interno, furos e cubo
    ctx.lineWidth = 7; ctx.strokeStyle = 'rgba(20,25,50,0.55)';
    ctx.beginPath(); ctx.arc(0, 0, r0 * 0.74, 0, Math.PI * 2); ctx.stroke();
    ctx.lineWidth = 2.5; ctx.strokeStyle = 'rgba(170,185,230,0.35)';
    ctx.beginPath(); ctx.arc(0, 0, r0 * 0.78, Math.PI * 1.1, Math.PI * 1.9); ctx.stroke();
    for (let i = 0; i < 5; i++) {
      const a = i / 5 * Math.PI * 2 + 0.3, x = Math.cos(a) * r0 * 0.46, y = Math.sin(a) * r0 * 0.46;
      ctx.fillStyle = '#121731'; ctx.beginPath(); ctx.arc(x, y, r0 * 0.14, 0, Math.PI * 2); ctx.fill();
      ctx.strokeStyle = 'rgba(160,175,225,0.3)'; ctx.lineWidth = 2;
      ctx.beginPath(); ctx.arc(x, y, r0 * 0.14, Math.PI * 0.1, Math.PI * 0.9); ctx.stroke();
    }
    const gh = ctx.createRadialGradient(-r0 * 0.05, -r0 * 0.08, 2, 0, 0, r0 * 0.22);
    gh.addColorStop(0, '#9aa3c8'); gh.addColorStop(1, '#3a4166');
    ctx.fillStyle = gh; ctx.beginPath(); ctx.arc(0, 0, r0 * 0.22, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = '#181d38'; ctx.lineWidth = 3; ctx.stroke();
    ctx.fillStyle = '#20253f'; ctx.beginPath(); ctx.arc(0, 0, r0 * 0.07, 0, Math.PI * 2); ctx.fill();
    // lascas e rachaduras da pedra
    ctx.strokeStyle = 'rgba(15,20,40,0.6)'; ctx.lineWidth = 2;
    for (let i = 0; i < 4; i++) {
      const a = R() * Math.PI * 2; let r = r0 * (0.8 + R() * 0.2);
      ctx.beginPath(); ctx.moveTo(Math.cos(a) * r, Math.sin(a) * r);
      for (let k = 0; k < 3; k++) { r -= r0 * 0.08; const b = a + (R() - 0.5) * 0.2; ctx.lineTo(Math.cos(b) * r, Math.sin(b) * r); }
      ctx.stroke();
    }
    ctx.restore();
  }
  function corrente(ctx, x0, y0, x1, y1, elo, desloc) {
    const len = Math.hypot(x1 - x0, y1 - y0), ang = Math.atan2(y1 - y0, x1 - x0), passo = elo * 1.45;
    const n = Math.floor(len / passo);
    const d = desloc ? ((desloc % (2 * passo)) + 2 * passo) % (2 * passo) : 0;
    for (let i = d ? -2 : 0; i <= n; i++) {
      const pos = passo * i + d;
      if (d && (pos < 0 || pos > len)) continue;
      const cx = d ? x0 + Math.cos(ang) * pos : x0 + Math.cos(ang) * passo * i;
      const cy = d ? y0 + Math.sin(ang) * pos : y0 + Math.sin(ang) * passo * i;
      ctx.save(); ctx.translate(cx, cy); ctx.rotate(ang);
      if (i % 2 === 0) {
        ctx.lineWidth = elo * 0.34; ctx.strokeStyle = '#1e2239';
        ctx.beginPath(); ctx.ellipse(0, 0, elo * 0.95, elo * 0.5, 0, 0, Math.PI * 2); ctx.stroke();
        ctx.lineWidth = elo * 0.12; ctx.strokeStyle = '#6b74a0';
        ctx.beginPath(); ctx.ellipse(0, 0, elo * 0.95, elo * 0.5, 0, Math.PI * 1.1, Math.PI * 1.85); ctx.stroke();
      } else {
        ctx.lineWidth = elo * 0.36; ctx.strokeStyle = '#2a2f4c'; ctx.lineCap = 'round';
        ctx.beginPath(); ctx.moveTo(-elo * 0.85, 0); ctx.lineTo(elo * 0.85, 0); ctx.stroke();
        ctx.lineWidth = elo * 0.1; ctx.strokeStyle = '#7f88b4';
        ctx.beginPath(); ctx.moveTo(-elo * 0.6, -elo * 0.08); ctx.lineTo(elo * 0.6, -elo * 0.08); ctx.stroke();
      }
      ctx.restore();
    }
  }
  function alavanca(ctx, x, piso, ang) {
    // base de bronze em meia cúpula
    const gb = ctx.createLinearGradient(x - 60, piso - 60, x + 60, piso);
    gb.addColorStop(0, '#c08a3e'); gb.addColorStop(0.5, '#8a5e27'); gb.addColorStop(1, '#4e3216');
    ctx.fillStyle = gb;
    ctx.beginPath(); ctx.moveTo(x - 66, piso); ctx.quadraticCurveTo(x - 62, piso - 58, x, piso - 60); ctx.quadraticCurveTo(x + 62, piso - 58, x + 66, piso); ctx.closePath(); ctx.fill();
    ctx.strokeStyle = '#3a240f'; ctx.lineWidth = 3; ctx.stroke();
    ctx.fillStyle = '#5e3d18'; ctx.fillRect(x - 74, piso - 12, 148, 14);
    ctx.fillStyle = 'rgba(255,210,140,0.45)'; ctx.fillRect(x - 72, piso - 12, 144, 3);
    ctx.fillStyle = '#26170a'; ctx.beginPath(); rrPath(ctx, x - 30, piso - 54, 60, 12, 6); ctx.fill();
    [[-44, -22], [44, -22]].forEach(([dx, dy]) => { ctx.fillStyle = '#e0b060'; ctx.beginPath(); ctx.arc(x + dx, piso + dy, 4, 0, Math.PI * 2); ctx.fill(); });
    // haste inclinada e bola laranja (ang > 0 puxa para a direita, girando no pivô)
    const px = x, py = piso - 50;
    let tx = x - 54, ty = piso - 168;
    if (ang) {
      const comp = Math.hypot(54, 118), a = Math.atan2(-118, -54) + ang;
      tx = px + comp * Math.cos(a); ty = py + comp * Math.sin(a);
    }
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#1d1c2b'; ctx.lineWidth = 15; ctx.beginPath(); ctx.moveTo(px, py); ctx.lineTo(tx, ty); ctx.stroke();
    ctx.strokeStyle = '#4a4864'; ctx.lineWidth = 4; ctx.beginPath(); ctx.moveTo(px + 3, py - 2); ctx.lineTo(tx + 3, ty - 2); ctx.stroke();
    ctx.save(); ctx.shadowColor = 'rgba(255,140,30,0.8)'; ctx.shadowBlur = 26;
    const gk = ctx.createRadialGradient(tx - 8, ty - 10, 3, tx, ty, 28);
    gk.addColorStop(0, '#ffe0a0'); gk.addColorStop(0.35, '#ff9a2a'); gk.addColorStop(1, '#c25408');
    ctx.fillStyle = gk; ctx.beginPath(); ctx.arc(tx, ty, 27, 0, Math.PI * 2); ctx.fill(); ctx.restore();
    ctx.fillStyle = 'rgba(255,255,240,0.85)'; ctx.beginPath(); ctx.ellipse(tx - 9, ty - 11, 7, 5, -0.5, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = '#7a5226'; ctx.beginPath(); ctx.arc(px, py, 9, 0, Math.PI * 2); ctx.fill();
    return [tx, ty];
  }
  // Placa da runa na torre: sempre esteve ali, discreta (o "detalhe que passa despercebido")
  const RUNA = { x: 968, y: 887 };
  function glifo(ctx, cx, cy, cor, lw) {
    ctx.strokeStyle = cor; ctx.lineWidth = lw; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    ctx.beginPath(); ctx.arc(cx, cy, 24, 0, Math.PI * 2); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(cx, cy - 24); ctx.lineTo(cx + 20, cy + 12); ctx.lineTo(cx - 20, cy + 12); ctx.closePath(); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(cx, cy - 12); ctx.lineTo(cx, cy + 24); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(cx - 36, cy); ctx.lineTo(cx - 29, cy); ctx.moveTo(cx + 29, cy); ctx.lineTo(cx + 36, cy); ctx.stroke();
  }
  function placaRuna(ctx) {
    ctx.save();
    ctx.fillStyle = '#323b66'; ctx.beginPath(); rrPath(ctx, RUNA.x - 54, RUNA.y - 39, 108, 78, 8); ctx.fill();
    ctx.fillStyle = 'rgba(160,185,240,0.25)'; ctx.fillRect(RUNA.x - 50, RUNA.y - 37, 100, 3);
    ctx.fillStyle = 'rgba(4,8,26,0.4)'; ctx.fillRect(RUNA.x - 54, RUNA.y + 33, 108, 6);
    glifo(ctx, RUNA.x, RUNA.y, '#1a2042', 4);
    glifo(ctx, RUNA.x + 1, RUNA.y + 1, 'rgba(160,185,240,0.16)', 1.5);
    ctx.restore();
  }
  function runa(ctx, b) {
    if (b <= 0) return;
    ctx.save();
    const g = ctx.createRadialGradient(RUNA.x, RUNA.y, 4, RUNA.x, RUNA.y, 120);
    g.addColorStop(0, `rgba(98,246,255,${(0.35 * b).toFixed(3)})`); g.addColorStop(1, 'rgba(98,246,255,0)');
    ctx.globalCompositeOperation = 'screen'; ctx.fillStyle = g; ctx.fillRect(RUNA.x - 120, RUNA.y - 120, 240, 240);
    ctx.globalCompositeOperation = 'source-over';
    ctx.globalAlpha = b; ctx.shadowColor = 'rgba(98,246,255,0.95)'; ctx.shadowBlur = 24;
    glifo(ctx, RUNA.x, RUNA.y, '#a6fbff', 4);
    ctx.restore();
  }
  function janela(ctx, j) {
    const { x, y, w, h } = j;
    ctx.save();
    // moldura de pedra (aduelas)
    ctx.beginPath(); arcoPath(ctx, x - 22, y - 22, w + 44, h + 22);
    ctx.fillStyle = '#3c4674'; ctx.fill();
    ctx.strokeStyle = '#141a36'; ctx.lineWidth = 4; ctx.stroke();
    ctx.strokeStyle = 'rgba(14,20,46,0.9)'; ctx.lineWidth = 3;
    for (let i = 0; i <= 6; i++) {
      const a = Math.PI + i / 6 * Math.PI, cx = x + w / 2, cy = y + w / 2;
      ctx.beginPath(); ctx.moveTo(cx + Math.cos(a) * w / 2, cy + Math.sin(a) * w / 2);
      ctx.lineTo(cx + Math.cos(a) * (w / 2 + 22), cy + Math.sin(a) * (w / 2 + 22)); ctx.stroke();
    }
    for (let yy = y + w / 2 + 40; yy < y + h; yy += 44) {
      [[x - 22, x], [x + w, x + w + 22]].forEach(([a, b]) => { ctx.beginPath(); ctx.moveTo(a, yy); ctx.lineTo(b, yy); ctx.stroke(); });
    }
    // vidro aceso
    ctx.beginPath(); arcoPath(ctx, x, y, w, h);
    ctx.save(); ctx.shadowColor = 'rgba(255,170,50,1)'; ctx.shadowBlur = 50;
    ctx.fillStyle = vgrad(ctx, y, y + h, [[0, '#fff3b0'], [0.45, '#ffd25a'], [1, '#f39223']]); ctx.fill(); ctx.restore();
    ctx.clip();
    ctx.fillStyle = 'rgba(160,80,10,0.25)'; ctx.fillRect(x, y, 10, h);
    ctx.fillStyle = 'rgba(120,60,10,0.35)'; ctx.fillRect(x + w / 2 - 3, y, 6, h);
    ctx.fillRect(x, y + h * 0.5, w, 6);
    ctx.restore();
    // peitoril
    ctx.fillStyle = '#4a5585'; ctx.fillRect(x - 30, y + h, w + 60, 16);
    ctx.fillStyle = 'rgba(255,190,90,0.6)'; ctx.fillRect(x - 30, y + h, w + 60, 4);
  }
  function cachoeira(ctx, R, x, y, w, h) {
    ctx.save();
    ctx.fillStyle = vgrad(ctx, y, y + h, [[0, '#e3f6fc'], [0.5, '#b4e3f4'], [1, '#d8f2fb']]);
    ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x + w, y);
    ctx.bezierCurveTo(x + w + 4, y + h * 0.4, x + w + 10, y + h * 0.8, x + w + 16, y + h);
    ctx.lineTo(x - 14, y + h); ctx.bezierCurveTo(x - 8, y + h * 0.8, x - 4, y + h * 0.4, x, y); ctx.closePath();
    ctx.globalAlpha = 0.92; ctx.fill(); ctx.clip();
    for (let i = 0; i < 26; i++) {
      const sx = x - 12 + R() * (w + 26), sy = y + R() * h * 0.6, sl = 60 + R() * 220;
      ctx.strokeStyle = R() < 0.5 ? 'rgba(255,255,255,0.75)' : 'rgba(110,190,225,0.45)';
      ctx.lineWidth = 1.5 + R() * 2.5;
      ctx.beginPath(); ctx.moveTo(sx, sy); ctx.lineTo(sx + 2, sy + sl); ctx.stroke();
    }
    ctx.restore();
    ctx.save(); ctx.filter = 'blur(14px)';
    for (let i = 0; i < 7; i++) {
      ctx.fillStyle = `rgba(240,250,255,${(0.35 + R() * 0.3).toFixed(2)})`;
      ctx.beginPath(); ctx.ellipse(x + w / 2 + (R() - 0.5) * 90, y + h - 6 + (R() - 0.5) * 24, 40 + R() * 40, 16 + R() * 14, 0, 0, Math.PI * 2); ctx.fill();
    }
    ctx.restore();
  }

  // ---------- ruína recortada, bloco a bloco ----------
  // bordas(y) -> [xEsq, xDir] da fileira; quina quente nos blocos da borda virada para o centro
  function ruina(ctx, R, y0, y1, bordas, o) {
    o = Object.assign({}, PEDRA, o || {});
    const quente = o.quente || 0;          // +1 borda direita quente, -1 borda esquerda
    for (let y = y0; y < y1; y += o.bh) {
      const [e, d] = bordas(y);
      const esq = e + (o.ruidoEsq ? (R() - 0.5) * o.ruidoEsq : 0);
      const dir = d + (o.ruidoDir ? (R() - 0.5) * o.ruidoDir : 0);
      ctx.fillStyle = o.argamassa; ctx.fillRect(esq, y, dir - esq, o.bh);
      let x = esq - (quente < 0 ? 0 : R() * o.bwMin * 0.8);
      const blocos = [];
      while (x < dir) { const bw = o.bwMin + R() * (o.bwMax - o.bwMin); blocos.push([x, Math.min(bw, dir - x)]); x += bw; }
      // bloco saliente ocasional na borda
      if (R() < 0.3) {
        const [bx, bw] = blocos[quente < 0 ? 0 : blocos.length - 1];
        blocos.push(quente < 0 ? [bx - 22 - R() * 20, bw * 0.7] : [bx + bw * 0.4, bw * 0.7 + 12 + R() * 16]);
      }
      blocos.forEach(([bx, bw], i) => {
        if (bw < 14 || R() < o.falha) return;
        const cor = escala(o.base, 1 + (R() - 0.5) * 2 * o.vari);
        tijolo(ctx, R, bx + o.gap / 2, y + o.gap / 2, bw - o.gap, o.bh - o.gap, cor, o);
        const naBorda = quente > 0 ? (bx + bw >= dir - 2 || i >= blocos.length - 2) : quente < 0 ? (i === 0 || bx < esq + 2 || i === blocos.length - 1) : false;
        if (naBorda) {
          const xx = quente > 0 ? bx + bw - o.gap / 2 - 2 : bx + o.gap / 2 + 2;
          ctx.strokeStyle = 'rgba(240,160,80,0.75)'; ctx.lineWidth = 3;
          ctx.beginPath(); ctx.moveTo(xx, y + o.gap / 2 + 6); ctx.lineTo(xx, y + o.bh - o.gap / 2 - 6); ctx.stroke();
          ctx.strokeStyle = 'rgba(240,170,90,0.45)'; ctx.lineWidth = 2.5;
          ctx.beginPath(); ctx.moveTo(bx + o.gap / 2 + 6, y + o.gap / 2 + 1.5); ctx.lineTo(bx + bw - o.gap / 2 - 6, y + o.gap / 2 + 1.5); ctx.stroke();
        }
      });
    }
  }
  function moita(ctx, R, x, y, larg, alt) { // tufo denso de folhas sobre a pedra
    const n = Math.round(larg * alt / 70);
    for (let i = 0; i < n; i++) {
      const t = R(), px = x + (R() - 0.5) * larg * (1 - t * 0.5), py = y - t * alt;
      const s = 14 + R() * 16;
      const cor = VERDES[clamp(Math.floor(t * 4 + R() * 1.6), 0, 4)];
      folha(ctx, px, py, s, -Math.PI / 2 + (px - x) / larg * 2.2 + (R() - 0.5) * 0.9, cor, R() < 0.5 ? 'rgba(8,36,18,0.4)' : null);
    }
  }
  function folhaGrande(ctx, x, y, s, ang, cor, borda) {
    ctx.save(); ctx.translate(x, y); ctx.rotate(ang);
    ctx.strokeStyle = cor; ctx.lineWidth = Math.max(4, s * 0.035); ctx.beginPath(); ctx.moveTo(-s * 0.35, 0); ctx.lineTo(0, 0); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(0, 0);
    ctx.bezierCurveTo(s * 0.25, -s * 0.36, s * 0.75, -s * 0.36, s, 0);
    ctx.bezierCurveTo(s * 0.75, s * 0.36, s * 0.25, s * 0.36, 0, 0);
    ctx.closePath(); ctx.fillStyle = cor; ctx.fill();
    ctx.strokeStyle = borda; ctx.lineWidth = 2.5; ctx.stroke();
    ctx.beginPath(); ctx.moveTo(s * 0.08, 0); ctx.lineTo(s * 0.9, 0); ctx.stroke();
    ctx.restore();
  }

  // ---------- camadas ----------
  function desenharCeu(c) {
    const ctx = c.getContext('2d'); const R = rng(SEMENTE + 1);
    ctx.fillStyle = vgrad(ctx, 0, 1300, [[0, '#2565a8'], [0.4, '#3e86c2'], [0.75, '#66a8d5'], [1, '#8cc3e2']]);
    ctx.fillRect(0, 0, W, H);
    ctx.save(); ctx.filter = 'blur(26px)';
    [[330, 300, 240, 44], [780, 220, 280, 52], [560, 560, 300, 40], [200, 660, 200, 34]].forEach(([x, y, w, h]) => {
      ctx.fillStyle = 'rgba(210,235,252,0.3)'; ctx.beginPath(); ctx.ellipse(x, y, w, h, 0, 0, Math.PI * 2); ctx.fill();
    });
    ctx.restore();
    ctx.fillStyle = '#7cb3d7';
    ctx.beginPath(); ctx.moveTo(-10, 1000);
    for (let x = -10; x <= 1090; x += 6) ctx.lineTo(x, 800 - 80 * Math.sin(x * 0.006 + 0.6) - 34 * Math.sin(x * 0.019 + 2) - 12 * Math.sin(x * 0.05));
    ctx.lineTo(1090, 1000); ctx.closePath(); ctx.fill();
    for (let x = -40; x < 1120; x += 66 + R() * 40) copa(ctx, R, x, 1000 + R() * 30, 140 + R() * 80, 120 + R() * 60, '#5e9ac6', '#7db6da');
  }

  function desenharDistante(c) {
    const ctx = c.getContext('2d'); const R = rng(SEMENTE + 2);
    const BASE = '#3a73a9', LUZ = '#72acd6', FUNDO = '#336da2';
    // penhasco da cachoeira com árvores no alto
    ctx.fillStyle = FUNDO;
    ctx.beginPath(); ctx.moveTo(600, 1320); ctx.lineTo(606, 700); ctx.quadraticCurveTo(650, 650, 712, 664); ctx.lineTo(778, 640); ctx.lineTo(796, 1320); ctx.closePath(); ctx.fill();
    ctx.fillStyle = 'rgba(120,175,220,0.35)'; ctx.fillRect(606, 690, 8, 630);
    copa(ctx, R, 690, 676, 200, 120, '#33709f', '#4f8dc0');
    cachoeira(ctx, R, 680, 684, 34, 600);
    ctx.save(); ctx.globalCompositeOperation = 'screen'; ctx.filter = 'blur(16px)';
    ctx.fillStyle = 'rgba(150,225,255,0.2)'; ctx.fillRect(672, 700, 50, 570); ctx.restore();
    // torre de ruína com dois arcos altos
    ctx.save();
    ctx.beginPath();
    ctx.moveTo(340, 960); ctx.lineTo(340, 470); ctx.lineTo(382, 470); ctx.lineTo(382, 436); ctx.lineTo(446, 436); ctx.lineTo(446, 396);
    ctx.lineTo(536, 396); ctx.lineTo(536, 424); ctx.lineTo(592, 424); ctx.lineTo(592, 462); ctx.lineTo(640, 462); ctx.lineTo(640, 960); ctx.closePath();
    arcoPath(ctx, 394, 516, 80, 330);
    arcoPath(ctx, 512, 516, 80, 330);
    ctx.fillStyle = BASE; ctx.fill('evenodd');
    ctx.clip('evenodd');
    ctx.strokeStyle = 'rgba(40,90,145,0.55)'; ctx.lineWidth = 2;
    for (let y = 470; y < 960; y += 32) {
      ctx.beginPath(); ctx.moveTo(340, y); ctx.lineTo(640, y); ctx.stroke();
      for (let x = 340 + (y / 32 % 2) * 30; x < 640; x += 62) { ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x, y + 32); ctx.stroke(); }
    }
    ctx.fillStyle = LUZ; ctx.fillRect(340, 394, 300, 8);
    ctx.fillStyle = 'rgba(25,65,115,0.35)'; ctx.fillRect(608, 390, 32, 570);
    ctx.restore();
    ctx.strokeStyle = 'rgba(140,195,232,0.6)'; ctx.lineWidth = 3;
    [[394, 516, 80], [512, 516, 80]].forEach(([x, y, w]) => { ctx.beginPath(); ctx.arc(x + w / 2, y + w / 2, w / 2 + 1, Math.PI * 1.15, Math.PI * 1.85); ctx.stroke(); });
    copa(ctx, R, 492, 400, 110, 74, '#3a77aa', '#5b97c8');
    moita(ctx, R, 360, 470, 40, 30);
    // aqueduto com arcos (quebrado num trecho)
    ctx.beginPath();
    ctx.rect(140, 892, 430, 38); ctx.rect(616, 900, 240, 30);
    for (let x = 140; x < 856; x += 90) { if (x > 560 && x < 616) continue; ctx.rect(x, 930, 26, 170); }
    ctx.fillStyle = BASE; ctx.fill();
    for (let x = 140; x < 830; x += 90) {
      if (x > 500 && x < 616) continue;
      ctx.beginPath(); ctx.moveTo(x + 26, 930); ctx.lineTo(x + 90, 930); ctx.lineTo(x + 90, 962);
      ctx.arc(x + 58, 962, 32, 0, Math.PI, true); ctx.closePath(); ctx.fill();
    }
    ctx.fillStyle = LUZ; ctx.fillRect(140, 892, 430, 6); ctx.fillRect(616, 900, 240, 6);
    for (let x = 150; x < 880; x += 74 + R() * 50) copa(ctx, R, x, 1150 + R() * 30, 130 + R() * 60, 120 + R() * 40, '#34709f', '#4f8cbe');
    ctx.fillStyle = vgrad(ctx, 820, 1250, [[0, 'rgba(150,205,236,0)'], [1, 'rgba(150,205,236,0.16)']]);
    ctx.fillRect(0, 820, W, 430);
  }

  function desenharMedio(c) {
    const ctx = c.getContext('2d'); const R = rng(SEMENTE + 3);
    const BASE = '#27598f', LUZ = '#4a83ba';
    ctx.save();
    ctx.beginPath();
    ctx.moveTo(140, 1420); ctx.lineTo(140, 1050); ctx.lineTo(220, 1050); ctx.lineTo(220, 1018); ctx.lineTo(410, 1018); ctx.lineTo(410, 1040);
    ctx.lineTo(560, 1040); ctx.lineTo(560, 1070); ctx.lineTo(712, 1070); ctx.lineTo(712, 1420); ctx.closePath();
    arcoPath(ctx, 206, 1104, 176, 330);
    arcoPath(ctx, 452, 1126, 184, 310);
    ctx.fillStyle = BASE; ctx.fill('evenodd');
    ctx.clip('evenodd');
    ctx.strokeStyle = 'rgba(18,52,98,0.6)'; ctx.lineWidth = 2.5;
    for (let y = 1050; y < 1420; y += 40) {
      ctx.beginPath(); ctx.moveTo(140, y); ctx.lineTo(712, y); ctx.stroke();
      for (let x = 140 + (y / 40 % 2) * 40; x < 712; x += 82) { ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x, y + 40); ctx.stroke(); }
    }
    ctx.fillStyle = LUZ; ctx.fillRect(140, 1016, 572, 7);
    ctx.fillStyle = 'rgba(14,40,80,0.35)'; ctx.fillRect(672, 1010, 40, 410);
    ctx.restore();
    ctx.strokeStyle = 'rgba(110,165,215,0.55)'; ctx.lineWidth = 3;
    [[206, 1104, 176], [452, 1126, 184]].forEach(([x, y, w]) => { ctx.beginPath(); ctx.arc(x + w / 2, y + w / 2, w / 2 + 1, Math.PI * 1.15, Math.PI * 1.85); ctx.stroke(); });
    for (const [x, y, l] of [[240, 1020, 160], [430, 1042, 120], [600, 1072, 190], [700, 1072, 100]]) hera(ctx, R, x, y, l, 15);
    moita(ctx, R, 300, 1020, 120, 34); moita(ctx, R, 640, 1070, 110, 30);
    for (let x = 120; x < 780; x += 100 + R() * 40) copa(ctx, R, x, 1340 + R() * 20, 150 + R() * 60, 130 + R() * 40, '#1f5285', '#336ca3');
    ctx.fillStyle = vgrad(ctx, 1180, 1360, [[0, 'rgba(130,190,230,0)'], [1, 'rgba(130,190,230,0.18)']]);
    ctx.fillRect(0, 1180, W, 180);
  }

  function desenharEstrutura(c) {
    const ctx = c.getContext('2d'); const R = rng(SEMENTE + 4);
    // ruína da esquerda: marquise em degraus no topo + pilar
    ruina(ctx, R, -10, PISO + 10, (y) => {
      if (y < 50) return [-10, 430];
      if (y < 104) return [-10, 392];
      if (y < 158) return [-10, 330];
      if (y < 212) return [-10, 262];
      return [-10, y > 900 && y < 1010 ? 214 : 182];
    }, { quente: 1, ruidoDir: 34, bh: 54 });
    const gp = ctx.createLinearGradient(-10, 0, 230, 0);
    gp.addColorStop(0, 'rgba(2,6,22,0.4)'); gp.addColorStop(1, 'rgba(2,6,22,0)');
    ctx.save(); ctx.globalCompositeOperation = 'source-atop'; ctx.fillStyle = gp; ctx.fillRect(-10, 0, 240, PISO); ctx.restore();
    // torre da direita, com o alto quebrado
    ruina(ctx, R, -10, PISO + 10, (y) => {
      if (y < 60) return [900, 1090];
      if (y < 120) return [852, 1090];
      if (y < 180) return [812, 1090];
      return [y > 640 && y < 760 ? 770 : 792, 1090];
    }, { quente: -1, ruidoEsq: 30, bh: 58, bwMin: 72, bwMax: 134 });
    const gt = ctx.createLinearGradient(1090, 0, 820, 0);
    gt.addColorStop(0, 'rgba(2,6,22,0.38)'); gt.addColorStop(1, 'rgba(2,6,22,0)');
    ctx.save(); ctx.globalCompositeOperation = 'source-atop'; ctx.fillStyle = gt; ctx.fillRect(820, 0, 270, PISO); ctx.restore();
    // cornija da torre
    ctx.fillStyle = '#3a4574'; ctx.beginPath(); rrPath(ctx, 776, 418, 320, 26, 4); ctx.fill();
    ctx.fillStyle = 'rgba(255,180,100,0.55)'; ctx.fillRect(780, 418, 312, 4);
    ctx.fillStyle = 'rgba(4,8,26,0.5)'; ctx.fillRect(776, 444, 320, 10);
    janela(ctx, JANELA);
    // corrente, suporte e engrenagem
    ctx.fillStyle = '#4a3a2a'; ctx.beginPath(); rrPath(ctx, 852, 440, 30, 22, 4); ctx.fill();
    if (!OP.dinamico) corrente(ctx, 866, 458, 846, ENGRENAGEM.y - ENGRENAGEM.r * 1.05, 15);
    ctx.fillStyle = '#262c4e'; ctx.beginPath(); rrPath(ctx, ENGRENAGEM.x + 30, ENGRENAGEM.y - 34, 270, 68, 10); ctx.fill();
    ctx.fillStyle = 'rgba(160,180,230,0.3)'; ctx.fillRect(ENGRENAGEM.x + 34, ENGRENAGEM.y - 34, 260, 5);
    engrenagem(OP.dinamico ? LIXO : ctx, R, ENGRENAGEM.x, ENGRENAGEM.y, ENGRENAGEM.r, 12, 0.12);   // mesmos sorteios nos dois modos
    if (!OP.dinamico) corrente(ctx, ENGRENAGEM.x + ENGRENAGEM.r * 0.95, ENGRENAGEM.y + 40, 1100, 1250, 15);
    if (OP.dinamico) placaRuna(ctx);
    // vegetação: moitas nos degraus e heras grandes penduradas
    moita(ctx, R, 404, 50, 70, 34); moita(ctx, R, 350, 104, 90, 38); moita(ctx, R, 290, 158, 80, 34); moita(ctx, R, 220, 212, 90, 40);
    moita(ctx, R, 880, 60, 70, 30); moita(ctx, R, 835, 120, 60, 28); moita(ctx, R, 960, 418, 150, 40); moita(ctx, R, 806, 418, 60, 30);
    for (const [x, y, l, t] of [[372, 104, 300, 24], [312, 158, 430, 25], [246, 212, 260, 23], [196, 214, 520, 24], [120, 230, 200, 21],
      [820, 450, 330, 24], [1040, 452, 440, 24], [990, 452, 180, 21], [830, 180, 210, 21]]) hera(ctx, R, x, y, l, t);
    moita(ctx, R, 200, 960, 80, 40);
  }

  function desenharPlataforma(c) {
    const ctx = c.getContext('2d'); const R = rng(SEMENTE + 5);
    // abismo
    ctx.fillStyle = vgrad(ctx, 1380, H, [[0, '#3d7bb2'], [0.45, '#22568d'], [1, '#0f2a56']]);
    ctx.fillRect(560, 1336, 540, H - 1336);
    for (const [x, y, w] of [[630, 1500, 74], [790, 1560, 52]]) {
      ctx.fillStyle = '#1f4c82'; ctx.fillRect(x, y, w, H - y);
      ctx.fillStyle = 'rgba(120,180,225,0.35)'; ctx.fillRect(x, y, w, 6);
      copa(ctx, R, x + w / 2, y + 8, w * 1.8, 60, '#1e5084', '#2f68a0');
    }
    ctx.save(); ctx.filter = 'blur(16px)';
    ctx.fillStyle = 'rgba(160,210,240,0.35)'; ctx.beginPath(); ctx.ellipse(720, 1680, 210, 50, 0, 0, Math.PI * 2); ctx.fill();
    ctx.restore();
    // parede sob a plataforma, com arco escuro
    ctx.save();
    ctx.beginPath(); ctx.rect(-10, PISO + 80, 594, H - PISO); arcoPath(ctx, 176, 1600, 240, 340); ctx.clip('evenodd');
    ruina(ctx, R, PISO + 80, H + 10, (y) => [-10, y > 1700 ? 560 : 584], { quente: 1, ruidoDir: 26, bh: 56, base: '#29315a' });
    ctx.restore();
    ctx.save(); ctx.beginPath(); arcoPath(ctx, 176, 1600, 240, 340); ctx.clip();
    ctx.fillStyle = vgrad(ctx, 1600, H, [[0, '#0e2048'], [1, '#081632']]); ctx.fillRect(170, 1590, 260, 340);
    ctx.restore();
    ctx.strokeStyle = 'rgba(240,160,80,0.55)'; ctx.lineWidth = 3;
    ctx.beginPath(); ctx.arc(296, 1720, 121, Math.PI * 1.1, Math.PI * 1.9); ctx.stroke();
    // borda de baixo à direita
    ctx.save(); ctx.beginPath(); ctx.rect(892, 1470, 220, H); ctx.clip();
    ruina(ctx, R, 1486, H + 10, () => [900, 1090], { quente: -1, ruidoEsq: 20, bh: 56, base: '#2b3358' });
    ctx.restore();
    ctx.fillStyle = vgrad(ctx, 1466, 1488, [[0, '#6b7bb0'], [1, '#4d5a8c']]); ctx.beginPath(); rrPath(ctx, 896, 1466, 200, 22, 4); ctx.fill();
    ctx.fillStyle = 'rgba(255,180,95,0.8)'; ctx.fillRect(898, 1486, 196, 3);
    // plataforma: blocos grandes na frente + tampo claro com quina quente
    ruina(ctx, R, PISO + 8, PISO + 84, () => [-10, 896], { quente: 1, bh: 38, bwMin: 84, bwMax: 150, base: '#343e6b', raio: 5 });
    ctx.fillStyle = vgrad(ctx, PISO - 12, PISO + 10, [[0, '#7080b5'], [1, '#4f5c8f']]);
    ctx.beginPath(); rrPath(ctx, -10, PISO - 12, 906, 22, 4); ctx.fill();
    for (let x = 60; x < 880; x += 100 + R() * 80) { ctx.fillStyle = 'rgba(20,26,58,0.55)'; ctx.fillRect(x, PISO - 12, 4, 22); }
    ctx.fillStyle = 'rgba(255,185,95,0.85)'; ctx.fillRect(-10, PISO + 8, 906, 3);
    ctx.fillStyle = 'rgba(4,8,26,0.55)'; ctx.fillRect(-10, PISO + 84, 906, 12);
    for (const [x, w, h] of [[606, 60, 32], [694, 44, 22], [772, 72, 38], [852, 40, 18]]) {
      ctx.fillStyle = '#262e52'; ctx.beginPath(); rrPath(ctx, x, PISO + 84, w, h, 6); ctx.fill();
      ctx.fillStyle = 'rgba(150,175,235,0.22)'; ctx.fillRect(x + 4, PISO + 86, w - 8, 3);
    }
    if (!OP.dinamico) alavanca(ctx, ALAVANCA.x, PISO - 12);
    // vegetação (longe do espaço do Echo, x ~250–450)
    moita(ctx, R, 80, PISO - 12, 140, 40); moita(ctx, R, 520, PISO - 12, 80, 30); moita(ctx, R, 806, PISO - 12, 150, 44);
    moita(ctx, R, 990, 1466, 120, 38);
    for (const [x, y, l, t] of [[120, PISO + 86, 220, 23], [470, PISO + 86, 160, 21], [560, PISO + 86, 300, 23], [836, PISO + 86, 240, 23],
      [950, 1490, 260, 22], [40, 1600, 170, 20]]) hera(ctx, R, x, y, l, t);
  }

  function desenharFrente(c) {
    const ctx = c.getContext('2d');
    const COR = '#061433', BORDA = '#14305c';
    for (const [x, y, s, a] of [[-30, 1990, 330, -1.0], [10, 1990, 280, -1.45], [60, 1990, 300, -0.55], [-60, 1880, 240, -0.2],
      [150, 1990, 220, -0.95], [230, 1990, 200, -1.35]]) folhaGrande(ctx, x, y, s, a, COR, BORDA);
    for (const [x, y, s, a] of [[1110, 1990, 330, -2.1], [1080, 1990, 280, -1.65], [1040, 1990, 300, -2.6], [1140, 1890, 240, -2.95],
      [940, 1990, 220, -2.2]]) folhaGrande(ctx, x, y, s, a, COR, BORDA);
    trevo(ctx, 400, 1800, 32, COR, BORDA); trevo(ctx, 470, 1850, 26, COR, BORDA); trevo(ctx, 760, 1820, 30, COR, BORDA);
    trevo(ctx, 700, 1870, 22, COR, BORDA);
    for (const [x, y, s, a] of [[1100, -20, 200, 2.0], [1080, -30, 170, 1.55], [1060, -10, 150, 2.45]]) folhaGrande(ctx, x, y, s, a, COR, BORDA);
  }

  function desenharLuz(c) {
    const ctx = c.getContext('2d'); const R = rng(SEMENTE + 7);
    for (const [x0, l0, x1, l1, a] of [[860, 70, 140, 240, 0.08], [980, 50, 400, 210, 0.07], [720, 40, 40, 150, 0.05], [1060, 60, 620, 240, 0.06]]) {
      const g = ctx.createLinearGradient(x0, 0, x1, 1500);
      g.addColorStop(0, `rgba(225,245,255,${a})`); g.addColorStop(1, 'rgba(225,245,255,0)');
      ctx.fillStyle = g;
      ctx.beginPath(); ctx.moveTo(x0, 0); ctx.lineTo(x0 + l0, 0); ctx.lineTo(x1 + l1, 1500); ctx.lineTo(x1, 1500); ctx.closePath(); ctx.fill();
    }
    const gj = ctx.createRadialGradient(JANELA.x + JANELA.w / 2, JANELA.y + JANELA.h / 2, 20, JANELA.x + JANELA.w / 2, JANELA.y + JANELA.h / 2, 360);
    gj.addColorStop(0, 'rgba(255,175,70,0.42)'); gj.addColorStop(1, 'rgba(255,140,40,0)');
    ctx.fillStyle = gj; ctx.fillRect(0, 0, W, H);
    if (!OP.dinamico) {
      const gb = ctx.createRadialGradient(ALAVANCA.x - 54, PISO - 180, 6, ALAVANCA.x - 54, PISO - 180, 130);
      gb.addColorStop(0, 'rgba(255,150,50,0.3)'); gb.addColorStop(1, 'rgba(255,150,50,0)');
      ctx.fillStyle = gb; ctx.fillRect(0, 0, W, H);
    }
    for (let i = 0; i < 30; i++) {
      const x = 190 + R() * 760, y = 960 + R() * 600, r = 1.5 + R() * 2.5;
      const g = ctx.createRadialGradient(x, y, 0, x, y, r * 5);
      g.addColorStop(0, 'rgba(230,255,200,0.9)'); g.addColorStop(0.3, 'rgba(190,255,170,0.35)'); g.addColorStop(1, 'rgba(190,255,170,0)');
      ctx.fillStyle = g; ctx.beginPath(); ctx.arc(x, y, r * 5, 0, Math.PI * 2); ctx.fill();
    }
  }

  function desenharAcabamento(c) {
    const ctx = c.getContext('2d'); const R = rng(SEMENTE + 8);
    const gv = ctx.createRadialGradient(540, 1050, 520, 540, 1050, 1300);
    gv.addColorStop(0, 'rgba(3,10,30,0)'); gv.addColorStop(1, 'rgba(3,10,30,0.45)');
    ctx.fillStyle = gv; ctx.fillRect(0, 0, W, H);
    for (let i = 0; i < 9000; i++) {
      ctx.fillStyle = R() < 0.5 ? 'rgba(255,255,255,0.03)' : 'rgba(0,0,0,0.05)';
      ctx.fillRect(R() * W, R() * H, 1.2, 1.2);
    }
  }

  // ---------- montagem ----------
  // o: { dinamico: true }  -> alavanca, engrenagem, correntes e brilho da bola desenhados por quadro; placa da runa na torre
  //    { alvo: { x0, y0, w, h, escala } } -> camadas w×h com o recorte da cena (x0, y0) ampliado (sem perder nitidez)
  function criar(o) {
    OP = o || {};
    const nova = () => {
      const c = document.createElement('canvas');
      if (!OP.alvo) { c.width = W; c.height = H; return c; }
      const a = OP.alvo;
      c.width = a.w; c.height = a.h;
      c.getContext('2d').setTransform(a.escala, 0, 0, a.escala, -a.x0 * a.escala, -a.y0 * a.escala);
      return c;
    };
    const L = {
      ceu: nova(), ruinas_distantes: nova(), ruinas_medias: nova(), estrutura: nova(),
      plataforma: nova(), luz: nova(), primeiro_plano: nova(), acabamento: nova(),
    };
    desenharCeu(L.ceu);
    desenharDistante(L.ruinas_distantes);
    desenharMedio(L.ruinas_medias);
    desenharEstrutura(L.estrutura);
    desenharPlataforma(L.plataforma);
    desenharLuz(L.luz);
    desenharFrente(L.primeiro_plano);
    desenharAcabamento(L.acabamento);
    function compor(ctx) {
      for (const nome of Object.keys(L)) {
        ctx.globalCompositeOperation = nome === 'luz' ? 'screen' : 'source-over';
        ctx.drawImage(L[nome], 0, 0);
      }
      ctx.globalCompositeOperation = 'source-over';
    }
    const RG = () => rng(SEMENTE + 60);   // rachaduras fixas da engrenagem desenhada por quadro
    return {
      L, compor,
      alavanca: (ctx, ang) => alavanca(ctx, ALAVANCA.x, PISO - 12, ang),
      brilhoAlavanca: (ctx, x, y) => {
        const gb = ctx.createRadialGradient(x, y, 6, x, y, 130);
        gb.addColorStop(0, 'rgba(255,150,50,0.3)'); gb.addColorStop(1, 'rgba(255,150,50,0)');
        ctx.fillStyle = gb; ctx.fillRect(x - 130, y - 130, 260, 260);
      },
      mecanismo: (ctx, rot, desloc) => {
        corrente(ctx, 866, 458, 846, ENGRENAGEM.y - ENGRENAGEM.r * 1.05, 15, desloc);
        engrenagem(ctx, RG(), ENGRENAGEM.x, ENGRENAGEM.y, ENGRENAGEM.r, 12, 0.12 + rot);
        corrente(ctx, ENGRENAGEM.x + ENGRENAGEM.r * 0.95, ENGRENAGEM.y + 40, 1100, 1250, 15, desloc);
      },
      runa,
    };
  }

  window.RPGDesenho = { W, H, PISO, ALAVANCA, ENGRENAGEM, JANELA, RUNA, BOLA: { x: ALAVANCA.x - 54, y: PISO - 12 - 168, r: 27 }, criar };
})();
