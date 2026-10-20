// Praça do feed (manifesto 0–18 s): a internet como uma rua noturna vista de baixo, em cânion vertical.
// Luz quente de poste + telas brancas; neon fica reservado ao quarto gamer da T1. Tudo em Canvas 2D.
// Manchetes de fundo são rabiscos (não texto). Texto real só nos objetos de primeiro plano, desenhado
// direto no quadro (para a medição da área segura enxergar cada palavra).
// PracaDesenho.criar() -> { L (camadas fixas), bolhas, outdoor, carimbo, cacos, achismo, tv, balanca, livro, pagina, clima }
(function () {
  'use strict';
  const W = 1080, H = 1920;
  const VP = { x: 540, y: 1010 };
  const SEMENTE = 4010;

  function rng(seed) {
    let a = seed >>> 0;
    return function () {
      a = (a + 0x6D2B79F5) | 0;
      let t = Math.imul(a ^ (a >>> 15), 1 | a);
      t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }
  const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
  const lerp = (a, b, t) => a + (b - a) * t;
  const camada = (w, h) => { const c = document.createElement('canvas'); c.width = w || W; c.height = h || H; return c; };
  function rrPath(ctx, x, y, w, h, r) {
    r = Math.max(0, Math.min(r, w / 2, h / 2));
    ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
  }
  const caixa = (ctx, x, y, w, h, r, cor) => { ctx.beginPath(); rrPath(ctx, x, y, w, h, r); ctx.fillStyle = cor; ctx.fill(); };
  const vgrad = (ctx, y0, y1, stops) => { const g = ctx.createLinearGradient(0, y0, 0, y1); stops.forEach(([o, c]) => g.addColorStop(o, c)); return g; };

  // rabisco de texto: linhas onduladas que lembram manchete sem serem lidas
  function rabisco(ctx, R, x, y, w, linhas, cor, esp) {
    ctx.strokeStyle = cor; ctx.lineWidth = esp; ctx.lineCap = 'round';
    for (let l = 0; l < linhas; l++) {
      const lw = w * (l === linhas - 1 ? 0.45 + R() * 0.3 : 0.75 + R() * 0.25);
      ctx.beginPath(); ctx.moveTo(x, y + l * esp * 2.6);
      for (let s = 0; s <= lw; s += 9) ctx.lineTo(x + s, y + l * esp * 2.6 + Math.sin(s * 0.35 + l + R()) * esp * 0.35);
      ctx.stroke();
    }
  }
  // ícones genéricos de notificação
  function icone(ctx, tipo, x, y, r) {
    ctx.save(); ctx.translate(x, y);
    const cores = { coracao: '#ff4d6d', sino: '#ffc23d', comentario: '#46b4ff', joinha: '#4ade80' };
    ctx.fillStyle = cores[tipo]; ctx.beginPath(); ctx.arc(0, 0, r, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = '#ffffff'; ctx.strokeStyle = '#ffffff'; ctx.lineWidth = r * 0.16; ctx.lineJoin = 'round';
    const s = r * 0.5;
    if (tipo === 'coracao') { ctx.beginPath(); ctx.moveTo(0, s * 0.8); ctx.bezierCurveTo(-s * 1.4, -s * 0.2, -s * 0.6, -s * 1.2, 0, -s * 0.45); ctx.bezierCurveTo(s * 0.6, -s * 1.2, s * 1.4, -s * 0.2, 0, s * 0.8); ctx.fill(); }
    else if (tipo === 'sino') { ctx.beginPath(); ctx.moveTo(-s * 0.8, s * 0.5); ctx.quadraticCurveTo(-s * 0.7, -s * 0.9, 0, -s * 0.9); ctx.quadraticCurveTo(s * 0.7, -s * 0.9, s * 0.8, s * 0.5); ctx.closePath(); ctx.fill(); ctx.beginPath(); ctx.arc(0, s * 0.75, s * 0.22, 0, Math.PI * 2); ctx.fill(); }
    else if (tipo === 'comentario') { ctx.beginPath(); rrPath(ctx, -s * 0.9, -s * 0.7, s * 1.8, s * 1.2, s * 0.35); ctx.fill(); ctx.beginPath(); ctx.moveTo(-s * 0.3, s * 0.45); ctx.lineTo(-s * 0.6, s * 0.95); ctx.lineTo(s * 0.1, s * 0.45); ctx.fill(); }
    else { ctx.beginPath(); rrPath(ctx, -s * 0.9, -s * 0.1, s * 0.5, s * 1.0, s * 0.12); ctx.fill(); ctx.beginPath(); ctx.moveTo(-s * 0.3, s * 0.9); ctx.lineTo(-s * 0.3, -s * 0.1); ctx.lineTo(0, -s * 0.9); ctx.quadraticCurveTo(s * 0.35, -s * 0.9, s * 0.2, -s * 0.2); ctx.lineTo(s * 0.85, -s * 0.2); ctx.lineTo(s * 0.7, s * 0.9); ctx.closePath(); ctx.fill(); }
    ctx.restore();
  }

  // miniatura de notícia: uma "foto" ilustrada (céu, morro e sol), sem texto
  function miniatura(ctx, x, y, s, v) {
    const ceus = [['#ffb070', '#7a4a8a'], ['#7fc8f0', '#3a4f8a'], ['#f59e7a', '#5a3a6a'], ['#9be0c0', '#2f5a6a']];
    const [c0, c1] = ceus[v % 4];
    ctx.save(); ctx.beginPath(); rrPath(ctx, x, y, s, s, s * 0.14); ctx.clip();
    ctx.fillStyle = vgrad(ctx, y, y + s, [[0, c0], [1, c1]]); ctx.fillRect(x, y, s, s);
    ctx.fillStyle = 'rgba(255,245,220,0.9)'; ctx.beginPath(); ctx.arc(x + s * 0.7, y + s * 0.32, s * 0.12, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = 'rgba(30,22,50,0.75)'; ctx.beginPath(); ctx.moveTo(x - s * 0.1, y + s); ctx.lineTo(x + s * 0.38, y + s * 0.5);
    ctx.lineTo(x + s * 0.62, y + s * 0.72); ctx.lineTo(x + s * 0.8, y + s * 0.58); ctx.lineTo(x + s * 1.1, y + s); ctx.closePath(); ctx.fill();
    ctx.restore();
  }

  // ---------- fachadas (textura plana, depois mapeada em perspectiva) ----------
  function texturaFachada(R, larg, alt, base) {
    const c = camada(larg, alt), ctx = c.getContext('2d');
    ctx.fillStyle = vgrad(ctx, 0, alt, [[0, base[0]], [1, base[1]]]); ctx.fillRect(0, 0, larg, alt);
    const andar = 118;
    for (let y = 40; y < alt; y += andar) {
      ctx.fillStyle = 'rgba(4,4,16,0.35)'; ctx.fillRect(0, y + andar - 10, larg, 10);
      ctx.fillStyle = 'rgba(160,150,230,0.12)'; ctx.fillRect(0, y + andar - 12, larg, 2);
      for (let x = 24; x < larg - 40; x += 74) {
        const r = R();
        const cor = r < 0.22 ? '#ffc477' : r < 0.32 ? '#e6efff' : r < 0.4 ? '#ff9a5c' : '#14122a';
        ctx.fillStyle = cor; ctx.fillRect(x, y + 22, 46, 62);
        if (r < 0.4) { ctx.fillStyle = 'rgba(255,255,255,0.18)'; ctx.fillRect(x, y + 22, 46, 6); }
        ctx.fillStyle = 'rgba(0,0,0,0.25)'; ctx.fillRect(x + 21, y + 22, 4, 62);
      }
    }
    // telas e painéis luminosos (sem texto legível)
    const telas = 4 + Math.floor(R() * 2);
    for (let i = 0; i < telas; i++) {
      const w = 180 + R() * 220, h = 120 + R() * 160, x = 20 + R() * (larg - w - 40), y = 120 + R() * (alt - h - 400);
      ctx.save(); ctx.shadowColor = 'rgba(220,235,255,0.7)'; ctx.shadowBlur = 30;
      caixa(ctx, x, y, w, h, 10, '#eaf1ff'); ctx.restore();
      ctx.fillStyle = 'rgba(30,40,80,0.08)'; ctx.fillRect(x, y + h * 0.62, w, h * 0.38);
      miniatura(ctx, x + 16, y + 16, 40, i);
      rabisco(ctx, R, x + 64, y + 26, w - 90, 2, '#3a3f66', 7);
      rabisco(ctx, R, x + 22, y + h * 0.55, w - 44, 3, '#8890b8', 5);
    }
    // letreiro vertical em forma de celular
    const px = 30 + R() * (larg - 140), py = 200 + R() * 400;
    ctx.save(); ctx.shadowColor = 'rgba(255,190,110,0.6)'; ctx.shadowBlur = 24;
    caixa(ctx, px, py, 92, 190, 18, '#2a2448'); ctx.restore();
    caixa(ctx, px + 8, py + 16, 76, 150, 10, '#ffd28a');
    rabisco(ctx, R, px + 16, py + 40, 58, 4, '#7a4a20', 5);
    return c;
  }
  // mapeia uma textura numa fachada com bordas verticais (perspectiva por fatias, com encurtamento)
  function fachada(ctx, tex, lado, n) {
    const xPerto = lado < 0 ? 0 : W, xLonge = lado < 0 ? 320 : 760;
    const topo = (x) => VP.y - 2.864 * Math.abs(VP.x - x), base = (x) => VP.y + Math.abs(VP.x - x) * 910 / 1100;
    const u = (x) => (1 / Math.abs(x - VP.x) - 1 / 540) / (1 / 220 - 1 / 540);
    for (let i = 0; i < n; i++) {
      const x0 = lerp(xPerto, xLonge, i / n), x1 = lerp(xPerto, xLonge, (i + 1) / n), xm = (x0 + x1) / 2;
      const u0 = u(x0), u1 = u(x1);
      const sx = tex.width * Math.min(u0, u1), sw = Math.max(1, tex.width * Math.abs(u1 - u0));
      ctx.drawImage(tex, sx, 0, sw, tex.height, Math.min(x0, x1), topo(xm), Math.abs(x1 - x0) + 0.8, base(xm) - topo(xm));
    }
    // sombra de volume: mais escuro perto da câmera (luz vem da rua)
    const g = ctx.createLinearGradient(xPerto, 0, xLonge, 0);
    g.addColorStop(0, 'rgba(4,3,14,0.45)'); g.addColorStop(1, 'rgba(4,3,14,0)');
    ctx.save(); ctx.beginPath();
    ctx.moveTo(xPerto, topo(xPerto)); ctx.lineTo(xLonge, topo(xLonge)); ctx.lineTo(xLonge, base(xLonge)); ctx.lineTo(xPerto, base(xPerto)); ctx.closePath();
    ctx.fillStyle = g; ctx.fill(); ctx.restore();
    // quina iluminada pela rua
    ctx.strokeStyle = 'rgba(255,190,120,0.35)'; ctx.lineWidth = 3;
    ctx.beginPath(); ctx.moveTo(xLonge, topo(xLonge)); ctx.lineTo(xLonge, base(xLonge)); ctx.stroke();
  }

  // ---------- camadas fixas ----------
  function desenharCeu(c) {
    const ctx = c.getContext('2d'), R = rng(SEMENTE + 1);
    ctx.fillStyle = vgrad(ctx, 0, VP.y, [[0, '#0a0b26'], [0.45, '#17163f'], [0.8, '#33224f'], [1, '#5a3550']]);
    ctx.fillRect(0, 0, W, VP.y + 20);
    for (let i = 0; i < 70; i++) {
      const x = 260 + R() * 560, y = R() * 520, a = (0.25 + R() * 0.5) * (1 - y / 600);
      ctx.fillStyle = `rgba(255,245,255,${a.toFixed(2)})`; ctx.beginPath(); ctx.arc(x, y, 0.8 + R() * 1.3, 0, Math.PI * 2); ctx.fill();
    }
    const gb = ctx.createRadialGradient(540, 980, 20, 540, 980, 520);
    gb.addColorStop(0, 'rgba(255,170,110,0.35)'); gb.addColorStop(1, 'rgba(255,140,120,0)');
    ctx.fillStyle = gb; ctx.fillRect(0, 400, W, 700);
  }
  function desenharFundo(c) {
    const ctx = c.getContext('2d'), R = rng(SEMENTE + 2);
    // prédios no fim da rua, de frente, com névoa
    const blocos = [[300, 520, 96], [392, 430, 88], [476, 600, 70], [540, 360, 92], [628, 470, 84], [708, 560, 76], [770, 420, 60]];
    for (const [x, topo, w] of blocos) {
      ctx.fillStyle = vgrad(ctx, topo, VP.y, [[0, '#2a2452'], [1, '#3d2c58']]);
      ctx.fillRect(x - w / 2, topo, w, VP.y - topo + 4);
      for (let y = topo + 18; y < VP.y - 20; y += 26) for (let xx = x - w / 2 + 10; xx < x + w / 2 - 12; xx += 18) {
        if (R() < 0.45) { ctx.fillStyle = R() < 0.7 ? 'rgba(255,200,130,0.75)' : 'rgba(230,240,255,0.8)'; ctx.fillRect(xx, y, 8, 11); }
      }
    }
    // duas torres em forma de celular com tela acesa
    for (const [x, topo] of [[452, 300], [640, 340]]) {
      ctx.save(); ctx.shadowColor = 'rgba(210,225,255,0.6)'; ctx.shadowBlur = 40;
      caixa(ctx, x - 52, topo, 104, 200, 22, '#1e1a3c'); ctx.restore();
      caixa(ctx, x - 44, topo + 18, 88, 164, 12, '#dbe6ff');
      caixa(ctx, x - 14, topo + 6, 28, 6, 3, '#0d0b1e');
      miniatura(ctx, x - 36, topo + 30, 24, x < 540 ? 0 : 1);
      rabisco(ctx, R, x - 6, topo + 36, 40, 2, '#3a3f66', 4);
      rabisco(ctx, R, x - 34, topo + 80, 66, 5, '#7c84ad', 3.5);
    }
    const gn = ctx.createLinearGradient(0, 700, 0, VP.y);
    gn.addColorStop(0, 'rgba(120,80,130,0)'); gn.addColorStop(1, 'rgba(150,95,130,0.55)');
    ctx.fillStyle = gn; ctx.fillRect(280, 700, 520, VP.y - 700 + 10);
  }
  function desenharFachadas(c) {
    const ctx = c.getContext('2d');
    fachada(ctx, texturaFachada(rng(SEMENTE + 3), 760, 2400, ['#1c1a3c', '#221d44']), -1, 140);
    fachada(ctx, texturaFachada(rng(SEMENTE + 4), 760, 2400, ['#1a1838', '#241e46']), 1, 140);
  }
  const meioFio = (y, lado) => VP.x + lado * 700 * (y - VP.y) / 910;
  const baseFachada = (y, lado) => VP.x + lado * 1100 * (y - VP.y) / 910;
  function desenharChao(c) {
    const ctx = c.getContext('2d'), R = rng(SEMENTE + 5);
    ctx.beginPath(); ctx.moveTo(VP.x, VP.y); ctx.lineTo(baseFachada(H, -1), H); ctx.lineTo(baseFachada(H, 1), H); ctx.closePath();
    ctx.fillStyle = vgrad(ctx, VP.y, H, [[0, '#3a2a4c'], [0.12, '#221c36'], [1, '#0d0b18']]); ctx.fill();
    for (const lado of [-1, 1]) {   // calçadas
      ctx.beginPath(); ctx.moveTo(VP.x, VP.y); ctx.lineTo(baseFachada(H, lado), H); ctx.lineTo(meioFio(H, lado), H); ctx.closePath();
      ctx.fillStyle = vgrad(ctx, VP.y, H, [[0, '#4a3858'], [0.15, '#2c2440'], [1, '#191526']]); ctx.fill();
      ctx.strokeStyle = 'rgba(255,190,130,0.45)'; ctx.lineWidth = 3;
      ctx.beginPath(); ctx.moveTo(VP.x, VP.y); ctx.lineTo(meioFio(H, lado), H); ctx.stroke();
    }
    // faixa de pedestres perto da câmera
    for (let i = -5; i <= 5; i++) {
      const y0 = 1560, y1 = 1640, x0a = VP.x + (i * 90 - 30) * (y0 - VP.y) / 550, x0b = VP.x + (i * 90 + 30) * (y0 - VP.y) / 550;
      const x1a = VP.x + (i * 90 - 30) * (y1 - VP.y) / 550, x1b = VP.x + (i * 90 + 30) * (y1 - VP.y) / 550;
      ctx.fillStyle = 'rgba(220,215,240,0.16)';
      ctx.beginPath(); ctx.moveTo(x0a, y0); ctx.lineTo(x0b, y0); ctx.lineTo(x1b, y1); ctx.lineTo(x1a, y1); ctx.closePath(); ctx.fill();
    }
    // asfalto molhado: reflexos verticais das luzes
    ctx.save(); ctx.filter = 'blur(10px)';
    for (let i = 0; i < 26; i++) {
      const lado = R() < 0.5 ? -1 : 1, d = 0.15 + R() * 0.8, y = VP.y + d * 910, x = lerp(meioFio(y, lado), VP.x, 0.15 + R() * 0.5);
      const quente = R() < 0.65;
      ctx.fillStyle = quente ? 'rgba(255,170,90,0.22)' : 'rgba(200,220,255,0.18)';
      ctx.fillRect(x - 6 * d, y, 12 * d + 4, 220 * d);
    }
    ctx.restore();
    for (let i = 0; i < 9000; i++) {
      const y = VP.y + Math.pow(R(), 0.8) * (H - VP.y), x = R() * W;
      ctx.fillStyle = R() < 0.5 ? 'rgba(255,255,255,0.03)' : 'rgba(0,0,0,0.07)'; ctx.fillRect(x, y, 1.6, 1.6);
    }
  }
  const POSTES = [0.1, 0.2, 0.34, 0.55, 0.85];
  function desenharPostes(c) {
    const ctx = c.getContext('2d');
    for (const d of POSTES) for (const lado of [-1, 1]) {
      const y = VP.y + d * 910, x = meioFio(y, lado) + lado * 30 * d, alt = 820 * d, larg = Math.max(2, 14 * d);
      ctx.fillStyle = '#16142a'; ctx.fillRect(x - larg / 2, y - alt, larg, alt);
      ctx.beginPath(); ctx.moveTo(x, y - alt); ctx.quadraticCurveTo(x - lado * 60 * d, y - alt - 30 * d, x - lado * 110 * d, y - alt + 6 * d);
      ctx.strokeStyle = '#16142a'; ctx.lineWidth = larg * 0.8; ctx.stroke();
      caixa(ctx, x - lado * 110 * d - 26 * d, y - alt + 4 * d, 52 * d, 14 * d, 6 * d, '#2a2540');
      ctx.fillStyle = '#ffd59a'; ctx.fillRect(x - lado * 110 * d - 20 * d, y - alt + 16 * d, 40 * d, 4 * d);
    }
  }
  function desenharLuz(c) {
    const ctx = c.getContext('2d');
    for (const d of POSTES) for (const lado of [-1, 1]) {
      const y = VP.y + d * 910, x = meioFio(y, lado) + lado * 30 * d - lado * 110 * d, yh = y - 820 * d + 18 * d;
      const g = ctx.createRadialGradient(x, yh, 2, x, yh, 260 * d + 30);
      g.addColorStop(0, 'rgba(255,180,90,0.55)'); g.addColorStop(1, 'rgba(255,150,70,0)');
      ctx.fillStyle = g; ctx.fillRect(x - 300, yh - 300, 600, 600);
      const gc = ctx.createLinearGradient(0, yh, 0, y);
      gc.addColorStop(0, 'rgba(255,170,80,0.08)'); gc.addColorStop(1, 'rgba(255,170,80,0)');
      ctx.fillStyle = gc; ctx.beginPath(); ctx.moveTo(x - 20 * d, yh); ctx.lineTo(x + 20 * d, yh); ctx.lineTo(x + 220 * d, y); ctx.lineTo(x - 220 * d, y); ctx.closePath(); ctx.fill();
    }
  }
  function desenharAcabamento(c) {
    const ctx = c.getContext('2d'), R = rng(SEMENTE + 9);
    const gv = ctx.createRadialGradient(540, 1000, 480, 540, 1000, 1300);
    gv.addColorStop(0, 'rgba(6,4,18,0)'); gv.addColorStop(1, 'rgba(6,4,18,0.55)');
    ctx.fillStyle = gv; ctx.fillRect(0, 0, W, H);
    for (let i = 0; i < 9000; i++) { ctx.fillStyle = R() < 0.5 ? 'rgba(255,255,255,0.03)' : 'rgba(0,0,0,0.05)'; ctx.fillRect(R() * W, R() * H, 1.2, 1.2); }
  }

  // ---------- elementos por quadro ----------
  // bolhas de notificação (sem texto): lista de { x, y, esc, rot, tipo, alfa }
  function bolhas(ctx, lista) {
    for (const b of lista) {
      if (b.alfa <= 0.01) continue;
      const R = rng(b.semente || 1);
      ctx.save(); ctx.globalAlpha = b.alfa; ctx.translate(b.x, b.y); ctx.rotate(b.rot || 0); ctx.scale(b.esc, b.esc);
      ctx.shadowColor = 'rgba(0,0,0,0.35)'; ctx.shadowBlur = 14; ctx.shadowOffsetY = 6;
      caixa(ctx, -80, -30, 160, 60, 22, 'rgba(244,246,255,0.95)');
      ctx.shadowColor = 'transparent';
      ctx.beginPath(); ctx.moveTo(-40, 28); ctx.lineTo(-54, 46); ctx.lineTo(-22, 28); ctx.fillStyle = 'rgba(244,246,255,0.95)'; ctx.fill();
      icone(ctx, b.tipo, -50, 0, 18);
      rabisco(ctx, R, -22, -10, 88, 2, '#4a4f78', 5);
      ctx.restore();
    }
  }
  const OUTDOOR = { x: 560, y: 300, w: 440, h: 300 };
  // outdoor "A MELHOR GPU DE TODAS!" de frente, preso à fachada da direita
  function outdoor(ctx, o) {
    o = o || {};
    const { x, y, w, h } = OUTDOOR;
    ctx.save();
    ctx.fillStyle = '#16142a'; ctx.fillRect(x + w - 60, y + h, 14, 240); ctx.fillRect(x + w - 30, y + h - 40, 60, 12);
    ctx.fillRect(x + 40, y + h, 12, 120); ctx.fillRect(x + 40, y + h + 110, w - 60, 10);
    caixa(ctx, x - 12, y - 12, w + 24, h + 24, 14, '#1e1b34');
    if (!o.quebra || o.quebra < 1) {
      ctx.save(); ctx.globalAlpha = 1 - (o.quebra || 0) * 0.9;
      ctx.shadowColor = 'rgba(255,230,200,0.7)'; ctx.shadowBlur = 40;
      caixa(ctx, x, y, w, h, 8, '#fff5e6'); ctx.shadowColor = 'transparent';
      // placa de vídeo genérica (sem marca)
      caixa(ctx, x + 22, y + 176, 170, 92, 10, '#2c2846');
      for (const fx of [x + 66, x + 148]) {
        ctx.strokeStyle = '#ff5a5f'; ctx.lineWidth = 4; ctx.beginPath(); ctx.arc(fx, y + 222, 30, 0, Math.PI * 2); ctx.stroke();
        ctx.strokeStyle = '#8c86b8'; ctx.lineWidth = 3;
        for (let k = 0; k < 6; k++) { const a = k / 6 * Math.PI * 2; ctx.beginPath(); ctx.moveTo(fx, y + 222); ctx.lineTo(fx + Math.cos(a) * 26, y + 222 + Math.sin(a) * 26); ctx.stroke(); }
      }
      ctx.fillStyle = '#e8343a'; ctx.font = '700 64px Rajdhani, sans-serif'; ctx.textBaseline = 'alphabetic';
      ctx.fillText('A MELHOR GPU', x + 26, y + 84);
      ctx.textAlign = 'right'; ctx.fillText('DE TODAS!', x + w - 26, y + 156); ctx.textAlign = 'left';
      ctx.restore();
    }
    // luzes do outdoor
    for (const lx of [x + 90, x + w - 90]) {
      ctx.fillStyle = '#2a2540'; ctx.fillRect(lx - 14, y - 34, 28, 14);
      const g = ctx.createRadialGradient(lx, y - 20, 2, lx, y + 60, 160);
      g.addColorStop(0, 'rgba(255,220,170,0.4)'); g.addColorStop(1, 'rgba(255,200,150,0)');
      ctx.fillStyle = g; ctx.fillRect(lx - 170, y - 30, 340, 260);
    }
    ctx.restore();
  }
  // carimbo "ANÁLISE": p = 0 (no alto) .. 1 (batido); a marca fica depois de bater
  function carimbo(ctx, p, marca) {
    const cx = OUTDOOR.x + OUTDOOR.w - 128, cy = OUTDOOR.y + OUTDOOR.h - 74;
    if (marca > 0) {
      ctx.save(); ctx.globalAlpha = marca; ctx.translate(cx, cy); ctx.rotate(-0.16);
      ctx.strokeStyle = '#ff4a2c'; ctx.lineWidth = 7; ctx.beginPath(); rrPath(ctx, -132, -46, 264, 92, 10); ctx.stroke();
      ctx.fillStyle = '#ff4a2c'; ctx.font = '700 72px Rajdhani, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText('ANÁLISE', 0, 4);
      // falhas de tinta do carimbo
      const R = rng(SEMENTE + 77); ctx.globalCompositeOperation = 'destination-out';
      for (let i = 0; i < 70; i++) { ctx.beginPath(); ctx.arc(-130 + R() * 260, -44 + R() * 88, 0.8 + R() * 2.4, 0, Math.PI * 2); ctx.fill(); }
      ctx.restore();
    }
    if (p < 1) {   // o carimbo (cabo de madeira + base) descendo
      const y = cy - 60 - (1 - p) * 420;
      ctx.save(); ctx.translate(cx, y); ctx.rotate(-0.16);
      caixa(ctx, -120, -20, 240, 40, 8, '#3a2a22');
      caixa(ctx, -126, 18, 252, 18, 6, '#c2331f');
      ctx.fillStyle = '#7a4a2a'; ctx.beginPath(); ctx.moveTo(-26, -20); ctx.lineTo(26, -20); ctx.lineTo(18, -120); ctx.lineTo(-18, -120); ctx.closePath(); ctx.fill();
      ctx.fillStyle = '#5a3420'; ctx.beginPath(); ctx.ellipse(0, -132, 44, 26, 0, 0, Math.PI * 2); ctx.fill();
      ctx.restore();
    }
  }
  // cacos do outdoor quebrando (p 0..1)
  function cacos(ctx, p) {
    if (p <= 0) return;
    const R = rng(SEMENTE + 88), { x, y, w, h } = OUTDOOR, cx = x + w * 0.55, cy = y + h * 0.5;
    ctx.save();
    const flash = Math.max(0, 1 - p * 3);
    if (flash > 0) { const g = ctx.createRadialGradient(cx, cy, 10, cx, cy, 420); g.addColorStop(0, `rgba(255,240,220,${0.8 * flash})`); g.addColorStop(1, 'rgba(255,240,220,0)'); ctx.fillStyle = g; ctx.fillRect(cx - 420, cy - 420, 840, 840); }
    for (let i = 0; i < 46; i++) {
      const a = R() * Math.PI * 2, v = 200 + R() * 700, s = 10 + R() * 34;
      const px = cx + Math.cos(a) * v * p + (R() - 0.5) * w * 0.6, py = cy + Math.sin(a) * v * p + 900 * p * p * 0.6 + (R() - 0.5) * h * 0.6;
      ctx.save(); ctx.translate(px, py); ctx.rotate(R() * 6 + p * (R() - 0.5) * 12);
      ctx.globalAlpha = clamp(1.2 - p, 0, 1);
      ctx.fillStyle = R() < 0.5 ? 'rgba(255,248,236,0.9)' : 'rgba(210,230,255,0.75)';
      ctx.strokeStyle = 'rgba(255,255,255,0.9)'; ctx.lineWidth = 1.5;
      ctx.beginPath(); ctx.moveTo(0, -s); ctx.lineTo(s * 0.7, s * 0.6); ctx.lineTo(-s * 0.5, s * 0.4); ctx.closePath(); ctx.fill(); ctx.stroke();
      ctx.restore();
    }
    ctx.restore();
  }
  // balão rabiscado "achismo" vestindo a moldura brilhante de vídeo ("cara de conteúdo")
  function achismo(ctx, o) {
    const { x, y, esc, mascara } = o;
    ctx.save(); ctx.translate(x, y); ctx.scale(esc, esc);
    ctx.shadowColor = 'rgba(0,0,0,0.4)'; ctx.shadowBlur = 24; ctx.shadowOffsetY = 10;
    ctx.fillStyle = '#efe9da';
    ctx.beginPath(); ctx.ellipse(0, 0, 210, 140, 0, 0, Math.PI * 2); ctx.fill();
    ctx.beginPath(); ctx.moveTo(-70, 120); ctx.lineTo(-130, 200); ctx.lineTo(-10, 132); ctx.fill();
    ctx.shadowColor = 'transparent';
    const R = rng(SEMENTE + 33);
    ctx.strokeStyle = '#3a3346'; ctx.lineWidth = 4; ctx.lineCap = 'round';
    for (let i = 0; i < 5; i++) {   // rabiscos de lápis
      ctx.beginPath(); let px = -150 + R() * 40, py = -80 + i * 34;
      ctx.moveTo(px, py);
      for (let s = 0; s < 22; s++) { px += 12 + R() * 4; ctx.lineTo(px, py + Math.sin(s * 1.3 + i) * 10); }
      ctx.stroke();
    }
    ctx.fillStyle = '#3a3346'; ctx.font = 'italic 600 60px Rajdhani, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText('achismo', 0, mascara > 0 ? 84 : 4);   // a palavra continua à mostra por baixo da máscara
    if (mascara > 0) {
      ctx.save(); ctx.globalAlpha = Math.min(1, mascara * 1.4);
      const my = lerp(-330, -96, mascara);
      ctx.shadowColor = 'rgba(255,240,220,0.6)'; ctx.shadowBlur = 36;
      const g = ctx.createLinearGradient(0, my - 120, 0, my + 120);
      g.addColorStop(0, '#fdfaf4'); g.addColorStop(1, '#e4ddf2');
      ctx.beginPath(); rrPath(ctx, -190, my - 118, 380, 236, 22); ctx.fillStyle = g; ctx.fill();
      ctx.shadowColor = 'transparent';
      caixa(ctx, -176, my - 104, 352, 170, 14, '#211c3a');
      ctx.fillStyle = 'rgba(255,255,255,0.12)'; ctx.beginPath(); ctx.moveTo(-176, my - 104); ctx.lineTo(-40, my - 104); ctx.lineTo(-120, my + 66); ctx.lineTo(-176, my + 66); ctx.closePath(); ctx.fill();
      ctx.fillStyle = '#ff4d4d'; ctx.beginPath(); ctx.arc(0, my - 20, 40, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#ffffff'; ctx.beginPath(); ctx.moveTo(-12, my - 42); ctx.lineTo(22, my - 20); ctx.lineTo(-12, my + 2); ctx.closePath(); ctx.fill();
      ctx.fillStyle = '#d8d0ea'; ctx.fillRect(-176, my + 80, 352, 8); ctx.fillStyle = '#ff4d4d'; ctx.fillRect(-176, my + 80, 210, 8);
      ctx.fillStyle = '#ff4d4d'; ctx.font = '700 40px Rajdhani, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText('CONTEÚDO', 0, my + 112);
      ctx.restore();
    }
    ctx.restore();
  }
  // TV com a barra de carregamento que nunca termina
  function tv(ctx, x, y, esc, prog) {
    ctx.save(); ctx.translate(x, y); ctx.scale(esc, esc);
    ctx.shadowColor = 'rgba(0,0,0,0.45)'; ctx.shadowBlur = 24; ctx.shadowOffsetY = 12;
    caixa(ctx, -130, -96, 260, 180, 22, '#2a2644'); ctx.shadowColor = 'transparent';
    caixa(ctx, -112, -80, 224, 136, 12, '#101a34');
    ctx.strokeStyle = '#2a2644'; ctx.lineWidth = 6; ctx.beginPath(); ctx.moveTo(-40, -96); ctx.lineTo(-74, -150); ctx.moveTo(40, -96); ctx.lineTo(74, -150); ctx.stroke();
    ctx.fillStyle = '#2a2644'; ctx.fillRect(-50, 84, 100, 14);
    ctx.strokeStyle = 'rgba(120,180,255,0.9)'; ctx.lineWidth = 7; ctx.lineCap = 'round';
    ctx.beginPath(); ctx.arc(0, -24, 26, prog * 12, prog * 12 + Math.PI * 1.4); ctx.stroke();
    caixa(ctx, -90, 22, 180, 12, 6, '#2d3658');
    caixa(ctx, -90, 22, 180 * (0.08 + 0.84 * (prog % 1)), 12, 6, '#ff5a5f');
    ctx.restore();
  }
  // balança com duas caixas de placa, só "A" e "B" (sem números)
  function balanca(ctx, x, y, esc, incl) {
    ctx.save(); ctx.translate(x, y); ctx.scale(esc, esc);
    ctx.shadowColor = 'rgba(0,0,0,0.45)'; ctx.shadowBlur = 20; ctx.shadowOffsetY = 10;
    ctx.fillStyle = '#b8903e'; ctx.fillRect(-10, -90, 20, 190); caixa(ctx, -70, 96, 140, 18, 8, '#b8903e');
    ctx.shadowColor = 'transparent';
    ctx.save(); ctx.translate(0, -90); ctx.rotate(incl);
    ctx.fillStyle = '#d8b25a'; ctx.fillRect(-150, -6, 300, 12);
    ctx.fillStyle = '#f0cd78'; ctx.beginPath(); ctx.arc(0, 0, 14, 0, Math.PI * 2); ctx.fill();
    for (const [sx, rot, cor, letra] of [[-140, -incl, '#3fb6e6', 'A'], [140, -incl, '#8c88a8', 'B']]) {
      ctx.save(); ctx.translate(sx, 0); ctx.rotate(rot);
      ctx.strokeStyle = '#d8b25a'; ctx.lineWidth = 3;
      ctx.beginPath(); ctx.moveTo(0, 0); ctx.lineTo(-56, 90); ctx.moveTo(0, 0); ctx.lineTo(56, 90); ctx.stroke();
      ctx.fillStyle = '#c99c46'; ctx.beginPath(); ctx.ellipse(0, 92, 66, 12, 0, 0, Math.PI * 2); ctx.fill();
      caixa(ctx, -46, 26, 92, 62, 8, cor);
      ctx.fillStyle = 'rgba(255,255,255,0.22)'; ctx.fillRect(-46, 26, 92, 8);
      ctx.fillStyle = '#ffffff'; ctx.font = '700 46px Rajdhani, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(letra, 0, 60);
      ctx.restore();
    }
    ctx.restore();
    ctx.restore();
  }
  // livro rasgado ao meio, com páginas voando (p = quanto as páginas já voaram)
  function livro(ctx, x, y, esc, p) {
    ctx.save(); ctx.translate(x, y); ctx.scale(esc, esc);
    ctx.shadowColor = 'rgba(0,0,0,0.45)'; ctx.shadowBlur = 20; ctx.shadowOffsetY = 10;
    ctx.fillStyle = '#5a3a6a'; ctx.beginPath(); ctx.moveTo(-150, -90); ctx.lineTo(0, -80); ctx.lineTo(0, 96); ctx.lineTo(-150, 86); ctx.closePath(); ctx.fill();
    ctx.shadowColor = 'transparent';
    ctx.fillStyle = '#f3ecdc'; ctx.beginPath(); ctx.moveTo(-140, -80); ctx.lineTo(-4, -72); ctx.lineTo(-4, 86); ctx.lineTo(-140, 78); ctx.closePath(); ctx.fill();
    const R = rng(SEMENTE + 44);
    rabisco(ctx, R, -126, -52, 108, 7, '#8a8070', 4);
    // metade direita rasgada
    ctx.fillStyle = '#f3ecdc'; ctx.beginPath(); ctx.moveTo(4, -72); ctx.lineTo(60, -76);
    for (let k = 0; k < 9; k++) ctx.lineTo(60 + (k % 2 ? 16 : -6), -76 + k * 19);
    ctx.lineTo(4, 86); ctx.closePath(); ctx.fill();
    ctx.strokeStyle = '#c8bca4'; ctx.lineWidth = 2; ctx.stroke();
    rabisco(ctx, R, 14, -52, 34, 7, '#8a8070', 4);
    ctx.fillStyle = '#5a3a6a'; ctx.fillRect(-4, -80, 8, 176);
    for (let i = 0; i < 3; i++) {   // páginas soltas voando
      const px = 90 + i * 40 + p * (60 + i * 50), py = -60 + i * 30 - p * (80 + i * 40);
      ctx.save(); ctx.translate(px, py); ctx.rotate(0.3 + i * 0.4 + p * (i + 1));
      ctx.fillStyle = '#efe6d2'; ctx.fillRect(-34, -44, 68, 88);
      rabisco(ctx, R, -26, -32, 52, 5, '#a89c84', 3);
      ctx.restore();
    }
    ctx.restore();
  }
  const PAGINA = { x: 130, y: 210, w: 820, h: 1040 };
  const PASSOS = ['Informação', 'Contexto', 'Compreensão', 'Decisão'];
  // a publicação real do portal (título e capa reais); 'passos' = quantos passos já acenderam
  function pagina(ctx, o) {
    const { x, y, w, h } = Object.assign({}, PAGINA, o.retangulo || {});
    ctx.save();
    ctx.shadowColor = 'rgba(0,0,0,0.55)'; ctx.shadowBlur = 50; ctx.shadowOffsetY = 20;
    caixa(ctx, x, y, w, h, 26, '#0e1626'); ctx.shadowColor = 'transparent';
    ctx.strokeStyle = 'rgba(47,211,232,0.85)'; ctx.lineWidth = 4; ctx.beginPath(); rrPath(ctx, x, y, w, h, 26); ctx.stroke();
    if (o.logo) ctx.drawImage(o.logo, x + 44, y + 34, 56, 56);
    ctx.fillStyle = '#ffffff'; ctx.font = '700 34px SpaceGrotesk, sans-serif'; ctx.textBaseline = 'middle';
    ctx.fillText('ESTRATÉGIA NERD', x + 116, y + 62);
    const cx = x + 50, cy = y + 120, cw = w - 100, ch = Math.round(cw * 9 / 16);
    ctx.save(); ctx.beginPath(); rrPath(ctx, cx, cy, cw, ch, 16); ctx.clip();
    if (o.capa) {
      const s = Math.max(cw / o.capa.width, ch / o.capa.height), dw = o.capa.width * s, dh = o.capa.height * s;
      ctx.drawImage(o.capa, cx + (cw - dw) / 2, cy + (ch - dh) / 2, dw, dh);
    } else { ctx.fillStyle = '#1c2a44'; ctx.fillRect(cx, cy, cw, ch); }
    ctx.restore();
    ctx.textBaseline = 'alphabetic';
    ctx.font = '700 46px SpaceGrotesk, sans-serif';
    ctx.fillStyle = '#2fd3e8'; ctx.fillText('16 GB ou 32 GB de RAM em 2026:', cx, cy + ch + 76);
    ctx.fillStyle = '#ffffff'; ctx.fillText('Quanto um PC Gamer', cx, cy + ch + 134); ctx.fillText('Realmente Precisa?', cx, cy + ch + 192);
    // os quatro passos
    const py = y + h - 104, pw = (w - 100 - 3 * 14) / 4;
    PASSOS.forEach((txt, i) => {
      const px = x + 50 + i * (pw + 14), aceso = (o.passos || 0) > i;
      ctx.fillStyle = aceso ? 'rgba(47,211,232,0.18)' : 'rgba(255,255,255,0.05)';
      ctx.strokeStyle = aceso ? '#2fd3e8' : 'rgba(150,170,200,0.4)'; ctx.lineWidth = 3;
      ctx.beginPath(); rrPath(ctx, px, py, pw, 64, 16); ctx.fill(); ctx.stroke();
      ctx.fillStyle = aceso ? '#ffffff' : 'rgba(200,210,230,0.65)'; ctx.font = '600 27px SpaceGrotesk, sans-serif';
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(txt, px + pw / 2, py + 33); ctx.textAlign = 'left';
    });
    ctx.restore();
  }
  // clima da praça por batida: escuro com foco de luz; calma = menos saturação e tom roxo (liga com o quarto)
  function clima(ctx, o) {
    if (o.escuro > 0) {
      const [fx, fy, fr] = o.foco || [540, 960, 600];
      const g = ctx.createRadialGradient(fx, fy, fr * 0.35, fx, fy, fr);
      g.addColorStop(0, 'rgba(6,5,20,0)'); g.addColorStop(1, `rgba(6,5,20,${o.escuro})`);
      ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    }
    if (o.calma > 0) {
      ctx.save();
      ctx.globalCompositeOperation = 'saturation'; ctx.fillStyle = `rgba(128,128,128,${0.75 * o.calma})`; ctx.fillRect(0, 0, W, H);
      ctx.globalCompositeOperation = 'color'; ctx.fillStyle = `rgba(90,40,170,${0.22 * o.calma})`; ctx.fillRect(0, 0, W, H);
      ctx.globalCompositeOperation = 'source-over'; ctx.fillStyle = `rgba(8,6,24,${0.35 * o.calma})`; ctx.fillRect(0, 0, W, H);
      ctx.restore();
    }
  }

  // placa de vídeo genérica (sem marca) em "foto de produto" com luz quente; t gira as ventoinhas
  function gpuIlustrada(ctx, x, y, w, h, t, o) {
    o = o || {};
    const acento = o.cor || '#ff6b5f';
    ctx.save(); ctx.beginPath(); rrPath(ctx, x, y, w, h, Math.min(14, h * 0.1)); ctx.clip();
    ctx.fillStyle = vgrad(ctx, y, y + h, [[0, '#3d2e5c'], [1, '#1b1630']]); ctx.fillRect(x, y, w, h);
    const gl = ctx.createRadialGradient(x + w * 0.5, y - h * 0.15, 4, x + w * 0.5, y - h * 0.15, h * 1.2);
    gl.addColorStop(0, 'rgba(255,190,120,0.42)'); gl.addColorStop(1, 'rgba(255,170,110,0)');
    ctx.fillStyle = gl; ctx.fillRect(x, y, w, h);
    ctx.fillStyle = 'rgba(8,5,18,0.32)'; ctx.fillRect(x, y + h * 0.8, w, h * 0.2);
    const gw = Math.min(w * 0.84, h * 2.4), gh = gw * 0.36, gx = x + (w - gw) / 2, gy = y + h * 0.48 - gh / 2;
    // sombra no chão
    ctx.fillStyle = 'rgba(0,0,0,0.35)'; ctx.beginPath(); ctx.ellipse(x + w / 2, gy + gh + gh * 0.16, gw * 0.48, gh * 0.12, 0, 0, Math.PI * 2); ctx.fill();
    // conector dourado e corpo
    ctx.fillStyle = '#d9a441'; ctx.fillRect(gx + gw * 0.14, gy + gh - 2, gw * 0.4, gh * 0.12);
    ctx.fillStyle = vgrad(ctx, gy, gy + gh, [[0, '#55507a'], [1, '#2a2542']]);
    ctx.beginPath(); rrPath(ctx, gx, gy, gw, gh, gh * 0.16); ctx.fill();
    ctx.strokeStyle = 'rgba(255,214,170,0.55)'; ctx.lineWidth = Math.max(1, gh * 0.025);
    ctx.beginPath(); ctx.moveTo(gx + gh * 0.16, gy + 1); ctx.lineTo(gx + gw - gh * 0.16, gy + 1); ctx.stroke();
    ctx.fillStyle = acento; ctx.fillRect(gx + gw * 0.06, gy + gh * 0.86, gw * 0.88, Math.max(1.5, gh * 0.05));
    // ventoinhas
    const fr = gh * 0.38;
    [0.28, 0.72].forEach((fx, i) => {
      const cx = gx + gw * fx, cy = gy + gh * 0.47;
      ctx.fillStyle = '#15122a'; ctx.beginPath(); ctx.arc(cx, cy, fr, 0, Math.PI * 2); ctx.fill();
      ctx.strokeStyle = acento; ctx.lineWidth = Math.max(1.2, fr * 0.1); ctx.stroke();
      ctx.strokeStyle = 'rgba(190,180,230,0.75)'; ctx.lineWidth = Math.max(1, fr * 0.07);
      const rot = (t || 0) * 5.5 * (i ? -1 : 1);
      for (let b = 0; b < 7; b++) {
        const a = rot + b * Math.PI * 2 / 7;
        ctx.beginPath(); ctx.moveTo(cx + Math.cos(a) * fr * 0.28, cy + Math.sin(a) * fr * 0.28);
        ctx.quadraticCurveTo(cx + Math.cos(a + 0.5) * fr * 0.7, cy + Math.sin(a + 0.5) * fr * 0.7, cx + Math.cos(a + 0.2) * fr * 0.86, cy + Math.sin(a + 0.2) * fr * 0.86);
        ctx.stroke();
      }
      ctx.fillStyle = '#3a3358'; ctx.beginPath(); ctx.arc(cx, cy, fr * 0.26, 0, Math.PI * 2); ctx.fill();
    });
    ctx.restore();
  }

  function criar() {
    const L = { ceu: camada(), fundo: camada(), fachadas: camada(), chao: camada(), postes: camada(), luz: camada(), acabamento: camada() };
    desenharCeu(L.ceu); desenharFundo(L.fundo); desenharFachadas(L.fachadas); desenharChao(L.chao);
    desenharPostes(L.postes); desenharLuz(L.luz); desenharAcabamento(L.acabamento);
    return { L, bolhas, outdoor, carimbo, cacos, achismo, tv, balanca, livro, pagina, clima };
  }
  window.PracaDesenho = { W, H, VP, OUTDOOR, PAGINA, PASSOS, criar, estilo: { gpu: gpuIlustrada, miniatura } };
})();
