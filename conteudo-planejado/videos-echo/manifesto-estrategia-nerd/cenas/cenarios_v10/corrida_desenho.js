// Desenho do cenário da Corrida em camadas — compartilhado por cenario_corrida.html, pela tela do monitor
// do Setup e pelos quadros da Fase 1. Depende de carro_frente.js.
// CorridaDesenho.criar() -> { L, compor(ctx, opts), atualizar(fase) }
//   opts: { fase, sacolejo, guiaEcho, interior(ctx,S), maos(ctx,S) } — fase = distância percorrida (0 = still aprovado)
// Cenário da Corrida (manifesto 20,96–24,62 s) — tudo desenhado em Canvas 2D, nenhuma imagem externa.
// Câmera à frente do carro, olhando para trás dele: a estrada foge para o horizonte.
// Camadas separadas (window.__layers) para animar depois no OneTake.
(function () {
  'use strict';
  const W = 1080, H = 1920;
  const VPX = 540, VPY = 905, F = 771;   // ponto de fuga e distância focal (altura da câmera = 1)
  const CURVA = 0.00035;                 // curva suave da estrada lá no fundo
  const SEMENTE = 1010;
  const CARRO = { cx: 540, chao: 1652, largura: 820 };

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
  const mix = (a, b, t) => { const A = hex(a), B = hex(b); return 'rgb(' + A.map((v, i) => Math.round(v + (B[i] - v) * t)).join(',') + ')'; };
  const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
  const camada = () => { const c = document.createElement('canvas'); c.width = W; c.height = H; return c; };
  const curva = (Z) => CURVA * Z * Z;
  const proj = (X, Z, Yw) => { const k = F / Z; return [VPX + (X + curva(Z)) * k, VPY + (1 - (Yw || 0)) * k]; };
  function rrPath(ctx, x, y, w, h, r) {
    r = Math.min(r, w / 2, h / 2);
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }

  const ZS = [];
  for (let i = 0, z0 = 0.5, z1 = 520, n = 240; i <= n; i++) ZS.push(z0 * Math.pow(z1 / z0, i / n));
  function zsEntre(zA, zB) { const r = [zA]; for (const z of ZS) if (z > zA && z < zB) r.push(z); r.push(zB); return r; }
  function tiraChao(ctx, X0, X1, zA, zB) {
    const zs = zsEntre(zA, zB);
    ctx.beginPath();
    zs.forEach((z, i) => { const [p, q] = proj(X0, z); i ? ctx.lineTo(p, q) : ctx.moveTo(p, q); });
    for (let i = zs.length - 1; i >= 0; i--) { const [p, q] = proj(X1, zs[i]); ctx.lineTo(p, q); }
    ctx.closePath();
  }
  function tiraParede(ctx, X, Y0, Y1, zA, zB) {
    const zs = zsEntre(zA, zB);
    ctx.beginPath();
    zs.forEach((z, i) => { const [p, q] = proj(X, z, Y1); i ? ctx.lineTo(p, q) : ctx.moveTo(p, q); });
    for (let i = zs.length - 1; i >= 0; i--) { const [p, q] = proj(X, zs[i], Y0); ctx.lineTo(p, q); }
    ctx.closePath();
  }

  // ---------- céu ----------
  function nuvem(ctx, R, cx, cy, w, h, cTopo, cBase, cBorda) {
    const off = document.createElement('canvas');
    off.width = Math.ceil(w + 60); off.height = Math.ceil(h * 2.8 + 20);
    const o = off.getContext('2d');
    const ox = 30, by = off.height - 10, bh = h * 0.42;
    o.beginPath();
    rrPath(o, ox, by - bh, w, bh, bh / 2);
    const n = 5 + Math.floor(R() * 3);
    for (let i = 0; i < n; i++) {
      const t = (i + 0.5) / n;
      const x = ox + w * (0.1 + 0.8 * t) + (R() - 0.5) * w * 0.06;
      const r = h * (0.42 + 0.95 * Math.pow(Math.sin(Math.PI * t), 0.8)) * (0.75 + R() * 0.45);
      o.moveTo(x + r, by - bh * 0.6);
      o.arc(x, by - bh * 0.6, r, 0, Math.PI * 2);
    }
    const g = o.createLinearGradient(0, by - h * 2.2, 0, by);
    g.addColorStop(0, cTopo); g.addColorStop(1, cBase);
    o.fillStyle = g; o.fill('nonzero');
    o.globalCompositeOperation = 'destination-out';
    o.fillRect(0, by, off.width, off.height);
    o.globalCompositeOperation = 'source-atop';
    const g2 = o.createLinearGradient(0, by - h * 0.9, 0, by);
    g2.addColorStop(0, 'rgba(0,0,0,0)'); g2.addColorStop(0.6, 'rgba(0,0,0,0)'); g2.addColorStop(1, cBorda);
    o.fillStyle = g2; o.fillRect(0, 0, off.width, off.height);
    // luz rosada nas bordas de cima das bolhas
    const g3 = o.createLinearGradient(0, by - h * 2.2, 0, by - h * 1.2);
    g3.addColorStop(0, 'rgba(255,190,225,0.28)'); g3.addColorStop(1, 'rgba(255,190,225,0)');
    o.fillStyle = g3; o.fillRect(0, 0, off.width, off.height);
    ctx.drawImage(off, cx - w / 2 - ox, cy - by);
  }

  function desenharCeu(c) {
    const ctx = c.getContext('2d');
    const R = rng(SEMENTE + 1);
    const g = ctx.createLinearGradient(0, 0, 0, VPY);
    [[0, '#121341'], [0.16, '#1e1c56'], [0.32, '#34287b'], [0.47, '#5d2d89'], [0.61, '#983880'],
     [0.73, '#d4506f'], [0.83, '#f27b50'], [0.92, '#ffac4b'], [1, '#ffd27c']].forEach(([o, cc]) => g.addColorStop(o, cc));
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, VPY + 40);

    for (let i = 0; i < 170; i++) {
      const x = R() * W, y = Math.pow(R(), 1.6) * 560;
      const a = (1 - y / 560) * (0.2 + R() * 0.6), r = 0.6 + R() * 1.4;
      ctx.fillStyle = `rgba(255,240,255,${a.toFixed(3)})`;
      ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2); ctx.fill();
    }

    const SX = 540, SY = 760, SR = 128;
    const gg = ctx.createRadialGradient(SX, SY, SR * 0.6, SX, SY, 640);
    gg.addColorStop(0, 'rgba(255,205,125,0.6)'); gg.addColorStop(0.35, 'rgba(255,140,110,0.22)'); gg.addColorStop(1, 'rgba(255,110,140,0)');
    ctx.fillStyle = gg; ctx.fillRect(0, 0, W, VPY + 40);
    const gd = ctx.createLinearGradient(0, SY - SR, 0, SY + SR);
    gd.addColorStop(0, '#fff7cc'); gd.addColorStop(0.5, '#ffcf5e'); gd.addColorStop(1, '#ff8a3c');
    ctx.save(); ctx.shadowColor = 'rgba(255,190,90,0.95)'; ctx.shadowBlur = 70;
    ctx.fillStyle = gd; ctx.beginPath(); ctx.arc(SX, SY, SR, 0, Math.PI * 2); ctx.fill(); ctx.restore();

    nuvem(ctx, R, 830, 215, 440, 92, '#2c2468', '#5a3584', 'rgba(170,80,160,0.75)');
    nuvem(ctx, R, 165, 300, 270, 60, '#30266c', '#673a88', 'rgba(190,90,170,0.7)');
    nuvem(ctx, R, 870, 470, 390, 82, '#6a2f86', '#d65a8a', 'rgba(255,160,180,0.9)');
    nuvem(ctx, R, 215, 565, 340, 66, '#74318a', '#e2648a', 'rgba(255,170,175,0.9)');
    nuvem(ctx, R, 330, 690, 230, 42, '#b04a86', '#ff8f88', 'rgba(255,200,160,0.95)');
    nuvem(ctx, R, 790, 655, 270, 48, '#a8467f', '#ff8a86', 'rgba(255,205,160,0.95)');
    // fiapos atravessando o sol
    [[470, 742, 150, 5], [600, 770, 120, 4], [520, 795, 210, 6]].forEach(([x, y, w, h]) => {
      ctx.fillStyle = 'rgba(255,120,150,0.55)';
      ctx.beginPath(); ctx.ellipse(x, y, w / 2, h / 2, 0, 0, Math.PI * 2); ctx.fill();
    });
    // rastros de velocidade no céu
    [[1050, 290, 955, 410], [560, 40, 490, 120], [140, 400, 40, 480], [990, 565, 915, 640]].forEach(([x1, y1, x2, y2]) => {
      const gl = ctx.createLinearGradient(x1, y1, x2, y2);
      gl.addColorStop(0, 'rgba(255,255,255,0)'); gl.addColorStop(0.5, 'rgba(255,235,255,0.42)'); gl.addColorStop(1, 'rgba(255,255,255,0)');
      ctx.strokeStyle = gl; ctx.lineWidth = 2.2; ctx.lineCap = 'round';
      ctx.beginPath(); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke();
    });
  }

  // ---------- montanhas ----------
  function serra(ctx, R, base, cTopo, cBase, yTopo, borda, sombra) {
    // cumes principais + serrilhado miúdo entre eles
    const pts = [];
    for (let i = 0; i < base.length - 1; i++) {
      const [ax, ay] = base[i], [bx, by] = base[i + 1];
      pts.push([ax, ay]);
      for (let k = 1; k < 4; k++) {
        const t = k / 4;
        pts.push([ax + (bx - ax) * t + (R() - 0.5) * 8, ay + (by - ay) * t + (R() - 0.5) * 14]);
      }
    }
    pts.push(base[base.length - 1]);
    const caminho = () => { ctx.beginPath(); pts.forEach(([x, y], i) => i ? ctx.lineTo(x, y) : ctx.moveTo(x, y)); };
    caminho(); ctx.lineTo(1100, VPY + 12); ctx.lineTo(-20, VPY + 12); ctx.closePath();
    const g = ctx.createLinearGradient(0, yTopo, 0, VPY); g.addColorStop(0, cTopo); g.addColorStop(1, cBase);
    ctx.fillStyle = g; ctx.fill();
    // facetas em triângulo a partir de cada cume: encosta virada para o sol (centro) clara, a outra escura
    ctx.save();
    caminho(); ctx.lineTo(1100, VPY + 12); ctx.lineTo(-20, VPY + 12); ctx.closePath(); ctx.clip();
    for (let i = 1; i < base.length - 1; i++) {
      const [px, py] = base[i];
      if (!(py < base[i - 1][1] && py < base[i + 1][1])) continue;
      const lado = px < 540 ? 1 : -1;
      const viz = base[i + lado];
      const pe = [px + lado * (Math.abs(viz[0] - px) * 0.18), VPY];
      const gl = ctx.createLinearGradient(0, py, 0, VPY);
      gl.addColorStop(0, 'rgba(255,180,180,0.2)'); gl.addColorStop(1, 'rgba(255,180,180,0)');
      ctx.fillStyle = gl;
      ctx.beginPath(); ctx.moveTo(px, py); ctx.lineTo(viz[0], viz[1]); ctx.lineTo(pe[0] + lado * Math.abs(viz[0] - px) * 0.55, VPY); ctx.lineTo(pe[0], pe[1]); ctx.closePath(); ctx.fill();
      const oposto = base[i - lado];
      const gs = ctx.createLinearGradient(0, py, 0, VPY);
      gs.addColorStop(0, sombra); gs.addColorStop(1, 'rgba(0,0,0,0)');
      ctx.fillStyle = gs;
      ctx.beginPath(); ctx.moveTo(px, py); ctx.lineTo(oposto[0], oposto[1]); ctx.lineTo(px - lado * Math.abs(oposto[0] - px) * 0.5, VPY); ctx.lineTo(px - lado * 6, VPY); ctx.closePath(); ctx.fill();
    }
    ctx.restore();
    if (borda) { caminho(); ctx.strokeStyle = borda; ctx.lineWidth = 2.5; ctx.lineJoin = 'round'; ctx.stroke(); }
  }

  function desenharMontanhas(c) {
    const ctx = c.getContext('2d');
    const R = rng(SEMENTE + 2);
    serra(ctx, R, [[-20, 770], [60, 705], [140, 738], [240, 645], [300, 612], [360, 652], [430, 705], [480, 742], [540, 800],
      [600, 748], [660, 702], [730, 640], [790, 603], [850, 642], [920, 692], [990, 652], [1100, 722]],
      '#583d8c', '#8f4f93', 600, 'rgba(255,175,150,0.6)', 'rgba(30,10,70,0.22)');
    serra(ctx, R, [[-20, 805], [70, 762], [180, 803], [270, 742], [350, 792], [420, 772], [500, 832], [560, 848], [640, 816],
      [720, 772], [800, 802], [880, 752], [960, 792], [1100, 762]],
      '#3b2b6e', '#4d3274', 740, 'rgba(255,150,150,0.35)', 'rgba(20,8,55,0.26)');
    // morros próximos com linha de pinheirinhos
    ctx.fillStyle = '#231b47';
    ctx.beginPath(); ctx.moveTo(-20, VPY + 12);
    for (let x = -20; x <= 1100; x += 4) ctx.lineTo(x, 866 + 10 * Math.sin(x * 0.013) + 6 * Math.sin(x * 0.041 + 1.3));
    ctx.lineTo(1100, VPY + 12); ctx.closePath(); ctx.fill();
    for (let x = -10; x < 1100; x += 7 + R() * 9) {
      const yb = 868 + 10 * Math.sin(x * 0.013) + 6 * Math.sin(x * 0.041 + 1.3), h = 10 + R() * 18, w = h * 0.38;
      ctx.beginPath(); ctx.moveTo(x, yb - h); ctx.lineTo(x + w, yb + 2); ctx.lineTo(x - w, yb + 2); ctx.closePath(); ctx.fill();
    }
    // névoa quente no horizonte
    ctx.save(); ctx.translate(540, VPY - 8); ctx.scale(2.4, 0.32);
    const gh = ctx.createRadialGradient(0, 0, 0, 0, 0, 420);
    gh.addColorStop(0, 'rgba(255,160,120,0.55)'); gh.addColorStop(1, 'rgba(255,120,140,0)');
    ctx.fillStyle = gh; ctx.beginPath(); ctx.arc(0, 0, 420, 0, Math.PI * 2); ctx.fill(); ctx.restore();
  }

  // ---------- chão e estrada ----------
  function desenharEstradaBase(c) {
    const ctx = c.getContext('2d');
    const R = rng(SEMENTE + 3);
    // terreno fora da pista
    const gt = ctx.createLinearGradient(0, VPY, 0, H);
    gt.addColorStop(0, '#3a2a5a'); gt.addColorStop(0.08, '#251d40'); gt.addColorStop(1, '#100d1b');
    ctx.fillStyle = gt; ctx.fillRect(0, VPY, W, H - VPY);
    // asfalto
    tiraChao(ctx, -3.62, 3.62, 0.5, 520);
    const ga = ctx.createLinearGradient(0, VPY, 0, H);
    ga.addColorStop(0, '#5b4573'); ga.addColorStop(0.05, '#41345c'); ga.addColorStop(0.28, '#2d2846'); ga.addColorStop(1, '#1c1829');
    ctx.fillStyle = ga; ctx.fill();

    ctx.save();
    tiraChao(ctx, -3.62, 3.62, 0.5, 520); ctx.clip();
    // textura do asfalto
    for (let i = 0; i < 26000; i++) {
      const y = VPY + Math.pow(R(), 0.8) * (H - VPY), x = R() * W, s = 0.6 + R() * (1 + (y - VPY) / 500);
      ctx.fillStyle = R() < 0.5 ? `rgba(255,255,255,${(0.03 + R() * 0.05).toFixed(3)})` : `rgba(0,0,0,${(0.05 + R() * 0.08).toFixed(3)})`;
      ctx.fillRect(x, y, s, s);
    }
    // reflexo do pôr do sol no asfalto
    ctx.globalCompositeOperation = 'screen';
    ctx.save(); ctx.translate(VPX + 30, VPY); ctx.scale(0.6, 1.9);
    const gr = ctx.createRadialGradient(0, 0, 0, 0, 0, 340);
    gr.addColorStop(0, 'rgba(255,155,100,0.38)'); gr.addColorStop(1, 'rgba(255,120,120,0)');
    ctx.fillStyle = gr; ctx.beginPath(); ctx.arc(0, 0, 340, 0, Math.PI * 2); ctx.fill(); ctx.restore();
    ctx.globalCompositeOperation = 'source-over';
    // rastros de velocidade no asfalto, partindo do ponto de fuga
    ctx.lineCap = 'round';
    for (let i = 0; i < 280; i++) {
      const tx = -1800 + R() * 4680, ty = H + 200;
      const s1 = 0.05 + Math.pow(R(), 0.6) * 0.85, s2 = Math.min(1, s1 + 0.04 + R() * 0.22);
      const quente = Math.abs(tx - VPX) < 700 && R() < 0.5;
      const a = (0.02 + R() * 0.07) * (0.4 + s1);
      ctx.strokeStyle = quente ? `rgba(255,170,120,${a.toFixed(3)})` : `rgba(190,175,255,${a.toFixed(3)})`;
      ctx.lineWidth = 0.8 + s1 * 3.5 * R();
      ctx.beginPath();
      ctx.moveTo(VPX + (tx - VPX) * s1, VPY + (ty - VPY) * s1);
      ctx.lineTo(VPX + (tx - VPX) * s2, VPY + (ty - VPY) * s2);
      ctx.stroke();
    }
    // rastros longos na parte de baixo (sensação de velocidade perto da câmera)
    for (let i = 0; i < 120; i++) {
      const tx = -2600 + R() * 6280, ty = H + 200;
      const s1 = 0.55 + R() * 0.4, s2 = Math.min(1.1, s1 + 0.12 + R() * 0.3);
      const quente = R() < 0.45;
      const a = 0.05 + R() * 0.1;
      ctx.strokeStyle = quente ? `rgba(255,165,110,${a.toFixed(3)})` : `rgba(200,185,255,${a.toFixed(3)})`;
      ctx.lineWidth = 1.5 + R() * 4;
      ctx.beginPath();
      ctx.moveTo(VPX + (tx - VPX) * s1, VPY + (ty - VPY) * s1);
      ctx.lineTo(VPX + (tx - VPX) * s2, VPY + (ty - VPY) * s2);
      ctx.stroke();
    }
    // rastros bem perto da câmera, no pé do quadro
    for (let i = 0; i < 70; i++) {
      const tx = -900 + R() * 2880, ty = H;
      const s1 = 0.7 + R() * 0.26, s2 = s1 + 0.05 + R() * 0.12;
      const a = 0.07 + R() * 0.12;
      ctx.strokeStyle = R() < 0.5 ? `rgba(255,165,110,${a.toFixed(3)})` : `rgba(205,190,255,${a.toFixed(3)})`;
      ctx.lineWidth = 2 + R() * 5;
      ctx.beginPath();
      ctx.moveTo(VPX + (tx - VPX) * s1, VPY + (ty - VPY) * s1);
      ctx.lineTo(VPX + (tx - VPX) * s2, VPY + (ty - VPY) * s2);
      ctx.stroke();
    }
    for (let i = 0; i < 30; i++) {
      const tx = R() < 0.5 ? -700 + R() * 800 : 980 + R() * 800, ty = H;
      const s1 = 0.62 + R() * 0.3, s2 = Math.min(1.05, s1 + 0.08 + R() * 0.16);
      ctx.strokeStyle = R() < 0.6 ? `rgba(255,185,120,${(0.16 + R() * 0.2).toFixed(3)})` : `rgba(255,245,255,${(0.12 + R() * 0.16).toFixed(3)})`;
      ctx.lineWidth = 2 + R() * 3.5;
      ctx.beginPath();
      ctx.moveTo(VPX + (tx - VPX) * s1, VPY + (ty - VPY) * s1);
      ctx.lineTo(VPX + (tx - VPX) * s2, VPY + (ty - VPY) * s2);
      ctx.stroke();
    }
    // névoa do horizonte sobre a pista
    const gn = ctx.createLinearGradient(0, VPY, 0, VPY + 120);
    gn.addColorStop(0, 'rgba(210,120,160,0.55)'); gn.addColorStop(1, 'rgba(210,120,160,0)');
    ctx.fillStyle = gn; ctx.fillRect(0, VPY, W, 120);
    ctx.restore();
  }

  // Camada da estrada: base pronta + faixas na posição da fase (as faixas fogem para o horizonte)
  function desenharEstrada(c, base, fase) {
    const ctx = c.getContext('2d');
    if (fase) ctx.drawImage(base, 0, 0); else desenharEstradaBase(c);   // fase 0 = desenho direto (still aprovado)
    const resto = fase % 6.2;
    // faixas tracejadas brancas
    for (const X of [-2.15, -0.95, 0.95, 2.15]) {
      for (let z = resto ? 0.55 + resto - 6.2 : 0.55; z < 230; z += 6.2) {
        if (z + 2.7 <= 0.5) continue;
        const zA = Math.max(z, 0.5);
        const a = 0.95 * Math.pow(1 - zA / 230, 0.6);
        ctx.save();
        ctx.shadowColor = `rgba(255,255,255,${(a * 0.5).toFixed(3)})`; ctx.shadowBlur = 8;
        tiraChao(ctx, X - 0.045, X + 0.045, zA, z + 2.7);
        ctx.fillStyle = `rgba(246,240,255,${a.toFixed(3)})`; ctx.fill();
        ctx.restore();
      }
    }
    // linhas laranja das bordas
    for (const s of [-1, 1]) {
      ctx.save();
      ctx.shadowColor = 'rgba(255,120,30,0.9)'; ctx.shadowBlur = 12;
      tiraChao(ctx, s * 3.48 - 0.05, s * 3.48 + 0.05, 0.5, 320);
      const gl = ctx.createLinearGradient(0, VPY, 0, VPY + 160);
      gl.addColorStop(0, 'rgba(255,170,90,0.3)'); gl.addColorStop(1, 'rgba(255,150,60,1)');
      ctx.fillStyle = gl; ctx.fill();
      ctx.restore();
    }
  }

  // ---------- pinheiros ----------
  function pinheiro(ctx, P, x, yb, hp, lado, cEsc, cLuz, cBorda, cTronco) {
    let k = 0;
    const R = () => P[k++];   // P = sorteios da árvore, na mesma ordem em que eram feitos
    const n = 5 + Math.floor(R() * 3);
    const topo = yb - hp, fh = hp * 0.9;
    ctx.fillStyle = cTronco;
    ctx.fillRect(x - hp * 0.02, yb - hp * 0.11, hp * 0.04, hp * 0.11);
    for (let i = n - 1; i >= 0; i--) {
      const yt = topo + fh * (i / n) * 0.82;
      const ybt = Math.min(topo + fh * ((i + 1.55) / n) * 0.82, yb - hp * 0.05);
      const hw = hp * 0.34 * Math.min(1, (i + 1.5) / n) * (0.9 + R() * 0.2);
      const dh = ybt - yt;
      const pts = [[x, yt], [x + hw * 0.55, yt + dh * 0.62], [x + hw, ybt], [x + hw * 0.62, ybt - dh * 0.07], [x + hw * 0.3, ybt + dh * 0.04],
        [x, ybt - dh * 0.05], [x - hw * 0.3, ybt + dh * 0.04], [x - hw * 0.62, ybt - dh * 0.07], [x - hw, ybt], [x - hw * 0.55, yt + dh * 0.62]];
      ctx.beginPath(); pts.forEach(([a, b], k) => k ? ctx.lineTo(a, b) : ctx.moveTo(a, b)); ctx.closePath();
      ctx.fillStyle = cEsc; ctx.fill();
      ctx.save(); ctx.clip();
      ctx.fillStyle = cLuz;
      if (lado > 0) ctx.fillRect(x + hw * 0.04, yt, hw + 2, dh + 6); else ctx.fillRect(x - hw - 2, yt, hw * 0.96 + 2, dh + 6);
      ctx.restore();
      ctx.strokeStyle = cBorda; ctx.lineWidth = Math.max(1, hp * 0.007); ctx.lineJoin = 'round';
      ctx.beginPath(); ctx.moveTo(x, yt);
      if (lado > 0) { ctx.lineTo(pts[1][0], pts[1][1]); ctx.lineTo(pts[2][0], pts[2][1]); }
      else { ctx.lineTo(pts[9][0], pts[9][1]); ctx.lineTo(pts[8][0], pts[8][1]); }
      ctx.stroke();
    }
  }

  const visivel = (x, hp) => !(x + hp * 0.4 < -60 || x - hp * 0.4 > W + 60);
  function sorteiosPinheiro(R) {
    const n = 5 + Math.floor(R() * 3), P = [ (n - 5 + 0.5) / 3 ];
    for (let i = 0; i < n; i++) P.push(R());
    return P;
  }
  // Mesmas árvores e mesmos sorteios do still aprovado (fase 0) + árvores extras atrás da câmera,
  // que entram pelas bordas quando a estrada anda.
  function prepararArvores() {
    const R = rng(SEMENTE + 4);
    const arvores = [];
    for (const s of [-1, 1]) {
      for (let i = 0; i < 90; i++) {
        const Z = 6.5 + Math.pow(R(), 0.75) * 210;
        const X = s * (4.7 + R() * R() * 10);
        const alt = 3.6 + R() * 4.4;
        arvores.push({ s, Z, X, alt });
      }
    }
    arvores.sort((a, b) => b.Z - a.Z);
    for (const a of arvores) {
      const [x] = proj(a.X, a.Z, 0);
      if (visivel(x, a.alt * F / a.Z)) a.P = sorteiosPinheiro(R);
    }
    const R2 = rng(SEMENTE + 41);
    for (const a of arvores) if (!a.P) a.P = sorteiosPinheiro(R2);
    const extras = [];
    for (const s of [-1, 1]) {
      for (let i = 0; i < 70; i++) {
        const Z = -130 + R2() * 132;
        const X = s * (4.7 + R2() * R2() * 10);
        const alt = 3.6 + R2() * 4.4;
        extras.push({ s, Z, X, alt, P: sorteiosPinheiro(R2) });
      }
    }
    return arvores.concat(extras).sort((a, b) => b.Z - a.Z);
  }
  function desenharArvores(c, arvores, fase) {
    const ctx = c.getContext('2d');
    for (const a of arvores) {
      const Z = a.Z + fase;
      if (Z < 0.8) continue;
      const [x, yb] = proj(a.X, Z, 0);
      const hp = a.alt * F / Z;
      if (!visivel(x, hp)) continue;
      const f = Math.pow(clamp((Z - 7) / 130, 0, 1), 0.7);
      const lado = -a.s; // lado iluminado é o virado para o centro (sol atrás da estrada)
      const borda = `rgba(255,${Math.round(165 + 30 * f)},${Math.round(140 + 30 * f)},${(0.3 - 0.15 * f).toFixed(3)})`;
      pinheiro(ctx, a.P, x, yb, hp, lado, mix('#14302b', '#463474', f), mix('#2e6b4c', '#6a4b8f', f), borda, mix('#3a2230', '#3b2b60', f));
    }
  }

  // ---------- guard-rail com refletores ciano ----------
  function desenharGuardRail(c, fase) {
    const ctx = c.getContext('2d');
    const rp = fase % 2.2, rr = fase % 3.0;
    for (const s of [-1, 1]) {
      const X = s * 3.9;
      for (let z = rp ? 0.9 + rp - 2.2 : 0.9; z < 170; z += 2.2) {
        if (z < 0.6) continue;
        const [a, b] = proj(X + s * 0.05, z, 0), [, cc] = proj(X + s * 0.05, z, 0.38);
        const w = Math.max(1, 0.08 * F / z);
        ctx.fillStyle = mix('#1c1b38', '#6c5089', clamp(z / 170, 0, 1));
        ctx.fillRect(a - w / 2, cc, w, b - cc);
      }
      tiraParede(ctx, X, 0.34, 0.8, 0.6, 420);
      let g = ctx.createLinearGradient(0, VPY, 0, VPY + 150);
      g.addColorStop(0, '#a07aa8'); g.addColorStop(1, '#565b92');
      ctx.fillStyle = g; ctx.fill();
      tiraParede(ctx, X, 0.74, 0.8, 0.6, 420);
      g = ctx.createLinearGradient(0, VPY, 0, VPY + 120);
      g.addColorStop(0, '#d6a9c4'); g.addColorStop(1, '#bfc3ea');
      ctx.fillStyle = g; ctx.fill();
      tiraParede(ctx, X, 0.47, 0.5, 0.6, 420); ctx.fillStyle = 'rgba(28,26,70,0.5)'; ctx.fill();
      tiraParede(ctx, X, 0.34, 0.38, 0.6, 420); ctx.fillStyle = 'rgba(20,18,50,0.55)'; ctx.fill();
      for (let z = rr ? 1.2 + rr - 3.0 : 1.2; z < 150; z += 3.0) {
        if (z < 0.6) continue;
        const a = clamp(1 - z / 150, 0.15, 1);
        const p = [proj(X, z, 0.68), proj(X, z + 0.9, 0.68), proj(X, z + 0.9, 0.53), proj(X, z, 0.53)];
        ctx.save();
        ctx.shadowColor = `rgba(0,230,255,${a.toFixed(3)})`; ctx.shadowBlur = 18;
        ctx.fillStyle = `rgba(90,245,255,${a.toFixed(3)})`;
        ctx.beginPath(); p.forEach(([x, y], i) => i ? ctx.lineTo(x, y) : ctx.moveTo(x, y)); ctx.closePath(); ctx.fill();
        ctx.restore();
      }
    }
  }

  // ---------- acabamento ----------
  function desenharAcabamento(c) {
    const ctx = c.getContext('2d');
    const R = rng(SEMENTE + 5);
    const gv = ctx.createRadialGradient(540, 1000, 480, 540, 1000, 1300);
    gv.addColorStop(0, 'rgba(6,4,18,0)'); gv.addColorStop(1, 'rgba(6,4,18,0.55)');
    ctx.fillStyle = gv; ctx.fillRect(0, 0, W, H);
    for (let i = 0; i < 9000; i++) {
      ctx.fillStyle = R() < 0.5 ? 'rgba(255,255,255,0.035)' : 'rgba(0,0,0,0.05)';
      ctx.fillRect(R() * W, R() * H, 1.2, 1.2);
    }
  }

  // ---------- montagem ----------
  function criar() {
    const L = {
      ceu: camada(), montanhas: camada(), chao_estrada: camada(), arvores: camada(),
      guardrail: camada(), luz_farois: camada(), carro: camada(), acabamento: camada(),
    };
    desenharCeu(L.ceu);
    desenharMontanhas(L.montanhas);
    const baseEstrada = camada();
    desenharEstradaBase(baseEstrada);
    const ARVORES = prepararArvores();
    let faseAtual = null;
    function atualizar(fase) {
      fase = fase || 0;
      if (fase === faseAtual) return;
      faseAtual = fase;
      for (const nome of ['chao_estrada', 'arvores', 'guardrail']) L[nome].getContext('2d').clearRect(0, 0, W, H);
      desenharEstrada(L.chao_estrada, baseEstrada, fase);
      desenharArvores(L.arvores, ARVORES, fase);
      desenharGuardRail(L.guardrail, fase);
    }
    atualizar(0);
    desenharAcabamento(L.acabamento);
    function compor(ctx, o) {
      o = o || {};
      atualizar(o.fase);
      const sacolejo = o.sacolejo || 0;
      const lc = L.luz_farois.getContext('2d'); lc.clearRect(0, 0, W, H);
      CarroFrente.luz(lc, CARRO.cx, CARRO.chao, CARRO.largura, { sacolejo });
      const cc = L.carro.getContext('2d'); cc.clearRect(0, 0, W, H);
      CarroFrente.draw(cc, CARRO.cx, CARRO.chao, CARRO.largura, { sacolejo, guiaEcho: !!o.guiaEcho, interior: o.interior, maos: o.maos });
      for (const nome of Object.keys(L)) {
        ctx.globalCompositeOperation = nome === 'luz_farois' ? 'lighter' : 'source-over';
        ctx.drawImage(L[nome], 0, 0);
      }
      ctx.globalCompositeOperation = 'source-over';
    }
    return { L, compor, atualizar };
  }

  window.CorridaDesenho = { W, H, VPX, VPY, F, CARRO, criar };
})();
