// Desenho do cenário do Setup Gamer em camadas — compartilhado por cenario_setup.html e pelos quadros da Fase 1.
// SetupDesenho.criar({ tela }) -> { L, compor(ctx), redesenharMesa(tela), TELA }
//   tela(ctx, TELA): desenha o conteúdo da tela do monitor (já recortado na área da tela).
//   Sem 'tela', mostra o trecho padrão do RPG (rpg_desenho.js), como no still aprovado.
// Cenário do Setup Gamer (manifesto 30–37,2 s) — quarto gamer roxo com neon, mesa, monitor, gabinete e cadeira.
// A tela do monitor mostra o RPG desenhado pelo mesmo código da cena anterior (rpg_desenho.js),
// para o zoom contínuo quarto <-> jogo parecer o mesmo computador. Tudo em Canvas 2D, nenhuma imagem externa.
// Cadeira virada 3/4 para quem assiste: lugar do Echo sentado (fica vazia aqui).
(function () {
  'use strict';
  const W = 1080, H = 1920;
  const SEMENTE = 3010;
  const RODAPE = 1430;                                   // encontro da parede com o chão
  const TELA = { x: 444, y: 820, w: 432, h: 243 };       // área útil do monitor (16:9)
  const RECORTE_RPG = { x: 0, y: 860, w: 1080, h: 607 }; // trecho do RPG mostrado na tela

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
  const camada = () => { const c = document.createElement('canvas'); c.width = W; c.height = H; return c; };
  function rrPath(ctx, x, y, w, h, r) {
    r = Math.max(0, Math.min(r, w / 2, h / 2));
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }
  function caixa(ctx, x, y, w, h, r, fill) { ctx.beginPath(); rrPath(ctx, x, y, w, h, r); ctx.fillStyle = fill; ctx.fill(); }
  function vgrad(ctx, y0, y1, stops) { const g = ctx.createLinearGradient(0, y0, 0, y1); stops.forEach(([o, c]) => g.addColorStop(o, c)); return g; }
  function hgrad(ctx, x0, x1, stops) { const g = ctx.createLinearGradient(x0, 0, x1, 0); stops.forEach(([o, c]) => g.addColorStop(o, c)); return g; }
  function brilho(ctx, x, y, r, cor, a) {
    const g = ctx.createRadialGradient(x, y, 0, x, y, r);
    g.addColorStop(0, cor.replace('A', a)); g.addColorStop(1, cor.replace('A', 0));
    ctx.fillStyle = g; ctx.fillRect(x - r, y - r, r * 2, r * 2);
  }
  function neon(ctx, caminho, cor, larg) {
    ctx.save(); ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    ctx.shadowColor = cor; ctx.shadowBlur = 42; ctx.strokeStyle = cor; ctx.globalAlpha = 0.55; ctx.lineWidth = larg * 1.7;
    caminho(); ctx.stroke();
    ctx.shadowBlur = 18; ctx.globalAlpha = 1; ctx.lineWidth = larg; caminho(); ctx.stroke();
    ctx.shadowBlur = 0; ctx.strokeStyle = 'rgba(255,255,255,0.85)'; ctx.lineWidth = larg * 0.34; caminho(); ctx.stroke();
    ctx.restore();
  }

  // ---------- parede e chão ----------
  function desenharParede(c) {
    const ctx = c.getContext('2d');
    ctx.fillStyle = vgrad(ctx, 0, RODAPE, [[0, '#4a1ea6'], [0.35, '#6c2bc8'], [0.7, '#8a3ad8'], [1, '#7530c8']]);
    ctx.fillRect(0, 0, W, RODAPE);
    // manchas suaves de luz na parede
    ctx.save(); ctx.globalCompositeOperation = 'screen'; ctx.filter = 'blur(40px)';
    [[650, 360, 260, 'rgba(120,80,255,0.35)'], [250, 700, 300, 'rgba(160,70,230,0.3)'], [920, 900, 260, 'rgba(230,80,220,0.22)']].forEach(([x, y, r, cor]) => {
      ctx.fillStyle = cor; ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2); ctx.fill();
    });
    ctx.restore();
    // chão
    ctx.fillStyle = vgrad(ctx, RODAPE, H, [[0, '#2c146a'], [0.4, '#22105a'], [1, '#150938']]);
    ctx.fillRect(0, RODAPE, W, H - RODAPE);
    ctx.fillStyle = '#2f1774'; ctx.fillRect(0, RODAPE - 8, W, 18);
    ctx.fillStyle = 'rgba(200,150,255,0.35)'; ctx.fillRect(0, RODAPE - 8, W, 3);
  }

  function desenharChao(c) {
    const ctx = c.getContext('2d'); const R = rng(SEMENTE + 1);
    // sombra da mesa e reflexos de neon no piso
    ctx.save(); ctx.filter = 'blur(20px)';
    ctx.fillStyle = 'rgba(8,2,26,0.55)'; ctx.beginPath(); ctx.ellipse(680, RODAPE + 30, 380, 40, 0, 0, Math.PI * 2); ctx.fill();
    ctx.restore();
    ctx.save(); ctx.globalCompositeOperation = 'screen'; ctx.filter = 'blur(26px)';
    ctx.fillStyle = 'rgba(80,200,255,0.18)'; ctx.fillRect(560, 1450, 240, 30);
    ctx.fillStyle = 'rgba(255,80,220,0.16)'; ctx.fillRect(760, 1460, 220, 26);
    ctx.restore();
    // tapete redondo sob a cadeira
    ctx.fillStyle = '#3b1d86'; ctx.beginPath(); ctx.ellipse(300, 1690, 320, 92, 0, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = 'rgba(190,140,255,0.45)'; ctx.lineWidth = 5; ctx.beginPath(); ctx.ellipse(300, 1690, 300, 82, 0, 0, Math.PI * 2); ctx.stroke();
    ctx.strokeStyle = 'rgba(190,140,255,0.2)'; ctx.lineWidth = 3; ctx.beginPath(); ctx.ellipse(300, 1690, 250, 66, 0, 0, Math.PI * 2); ctx.stroke();
    // cabos
    ctx.strokeStyle = '#1a0c42'; ctx.lineWidth = 6; ctx.lineCap = 'round';
    ctx.beginPath(); ctx.moveTo(760, 1440); ctx.bezierCurveTo(700, 1520, 640, 1470, 590, 1540); ctx.bezierCurveTo(540, 1610, 620, 1650, 560, 1720); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(960, 1440); ctx.bezierCurveTo(1010, 1480, 1040, 1460, 1090, 1500); ctx.stroke();
    ctx.strokeStyle = 'rgba(170,120,255,0.25)'; ctx.lineWidth = 2;
    ctx.beginPath(); ctx.moveTo(760, 1438); ctx.bezierCurveTo(700, 1518, 640, 1468, 590, 1538); ctx.stroke();
    // grão leve no piso
    for (let i = 0; i < 5000; i++) {
      ctx.fillStyle = R() < 0.5 ? 'rgba(255,255,255,0.03)' : 'rgba(0,0,0,0.06)';
      ctx.fillRect(R() * W, RODAPE + R() * (H - RODAPE), 1.5, 1.5);
    }
  }

  // ---------- decoração da parede ----------
  function desenharDecoracao(c) {
    const ctx = c.getContext('2d');
    // painel verde-ciano com marcas de jogo
    ctx.save();
    ctx.shadowColor = 'rgba(10,0,40,0.5)'; ctx.shadowBlur = 24; ctx.shadowOffsetY = 10;
    ctx.beginPath(); ctx.moveTo(60, 214); ctx.lineTo(400, 228); ctx.lineTo(392, 456); ctx.lineTo(70, 444); ctx.closePath();
    ctx.fillStyle = hgrad(ctx, 60, 400, [[0, '#52e6dc'], [0.55, '#41dcb0'], [1, '#3ccf6a']]); ctx.fill();
    ctx.restore();
    ctx.save();
    ctx.beginPath(); ctx.moveTo(60, 214); ctx.lineTo(400, 228); ctx.lineTo(392, 456); ctx.lineTo(70, 444); ctx.closePath(); ctx.clip();
    ctx.fillStyle = 'rgba(255,255,255,0.12)'; ctx.beginPath(); ctx.moveTo(60, 214); ctx.lineTo(250, 222); ctx.lineTo(120, 450); ctx.lineTo(60, 448); ctx.closePath(); ctx.fill();
    ctx.strokeStyle = 'rgba(255,255,255,0.55)'; ctx.lineWidth = 5; ctx.lineCap = 'round';
    [[140, 350, 44], [300, 330, 30], [214, 270, 11]].forEach(([x, y, r]) => { ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2); ctx.stroke(); });
    [[118, 262], [262, 262], [292, 420], [200, 420]].forEach(([x, y]) => {
      ctx.beginPath(); ctx.moveTo(x - 10, y - 10); ctx.lineTo(x + 10, y + 10); ctx.moveTo(x + 10, y - 10); ctx.lineTo(x - 10, y + 10); ctx.stroke();
    });
    ctx.restore();
    // pôster quadriculado inclinado
    ctx.save(); ctx.translate(452, 486); ctx.rotate(0.21);
    ctx.shadowColor = 'rgba(10,0,40,0.55)'; ctx.shadowBlur = 22; ctx.shadowOffsetY = 10;
    caixa(ctx, -100, -100, 200, 200, 6, '#f06ad8');
    ctx.shadowColor = 'transparent';
    for (let i = 0; i < 8; i++) for (let j = 0; j < 8; j++) {
      ctx.fillStyle = (i + j) % 2 ? '#5a22aa' : '#ef77df';
      ctx.fillRect(-88 + i * 22, -88 + j * 22, 22, 22);
    }
    ctx.fillStyle = 'rgba(255,255,255,0.12)'; ctx.beginPath(); ctx.moveTo(-88, -88); ctx.lineTo(40, -88); ctx.lineTo(-88, 40); ctx.closePath(); ctx.fill();
    ctx.restore();
    // prateleira branca com objetos
    ctx.save(); ctx.filter = 'blur(10px)'; ctx.fillStyle = 'rgba(20,0,60,0.45)'; ctx.fillRect(712, 622, 370, 22); ctx.restore();
    ctx.save(); ctx.translate(762, 528); ctx.rotate(-0.05);
    caixa(ctx, -44, -66, 92, 134, 6, '#f6f1ff');
    ctx.fillStyle = 'rgba(120,90,180,0.25)'; ctx.fillRect(28, -64, 18, 130);
    ctx.fillStyle = '#2a1d5e'; ctx.beginPath(); ctx.moveTo(-14, -30); ctx.lineTo(18, -12); ctx.lineTo(-14, 6); ctx.closePath(); ctx.fill();
    ctx.fillStyle = 'rgba(42,29,94,0.6)'; ctx.fillRect(-26, 26, 50, 5); ctx.fillRect(-26, 38, 36, 5);
    ctx.restore();
    caixa(ctx, 852, 548, 154, 28, 4, '#ef5fdc'); ctx.fillStyle = 'rgba(255,255,255,0.3)'; ctx.fillRect(856, 551, 146, 4);
    caixa(ctx, 870, 576, 176, 24, 4, '#3fd2f2'); ctx.fillStyle = 'rgba(255,255,255,0.3)'; ctx.fillRect(874, 579, 168, 4);
    // vaso com planta
    caixa(ctx, 1030, 548, 40, 52, 6, '#f6f1ff');
    [[-0.9, 40], [-0.3, 52], [0.3, 46], [0.9, 36], [-0.6, 30]].forEach(([a, s]) => {
      ctx.save(); ctx.translate(1050, 548); ctx.rotate(a - Math.PI / 2);
      ctx.fillStyle = '#3ccf6a'; ctx.beginPath(); ctx.moveTo(0, 0); ctx.bezierCurveTo(s * 0.4, -s * 0.3, s * 0.85, -s * 0.2, s, 0); ctx.bezierCurveTo(s * 0.85, s * 0.2, s * 0.4, s * 0.3, 0, 0); ctx.fill();
      ctx.restore();
    });
    ctx.fillStyle = vgrad(ctx, 600, 618, [[0, '#ffffff'], [1, '#d6caf5']]);
    ctx.beginPath(); rrPath(ctx, 700, 600, 390, 18, 4); ctx.fill();
  }

  // ---------- neons ----------
  function desenharNeons(c) {
    const ctx = c.getContext('2d');
    const raio = [[602, 182], [690, 194], [650, 304], [726, 304], [606, 478], [636, 352], [566, 352]];
    neon(ctx, () => { ctx.beginPath(); raio.forEach(([x, y], i) => i ? ctx.lineTo(x, y) : ctx.moveTo(x, y)); ctx.closePath(); }, '#62f6ff', 11);
    [[694, '#5cf5a8'], [740, '#ff5ad8'], [786, '#4ad8ff']].forEach(([y, cor]) => {
      neon(ctx, () => { ctx.beginPath(); ctx.moveTo(918, y); ctx.lineTo(1100, y); }, cor, 9);
    });
    // painéis hexagonais de luz no alto
    const hexs = [[808, 108, '#ff5ad8'], [867, 142, '#62f6ff'], [926, 108, '#b46bff'], [867, 210, '#ff5ad8'], [985, 142, '#62f6ff'], [926, 244, '#5cf5a8']];
    hexs.forEach(([x, y, cor]) => {
      const caminho = (r) => { ctx.beginPath(); for (let k = 0; k < 6; k++) { const a = Math.PI / 6 + k * Math.PI / 3; ctx.lineTo(x + Math.cos(a) * r, y + Math.sin(a) * r); } ctx.closePath(); };
      ctx.save(); ctx.shadowColor = cor; ctx.shadowBlur = 30; caminho(34); ctx.fillStyle = cor; ctx.globalAlpha = 0.85; ctx.fill(); ctx.restore();
      ctx.save(); caminho(26); ctx.fillStyle = 'rgba(255,255,255,0.35)'; ctx.fill(); ctx.restore();
    });
  }

  // ---------- mesa, monitor e PC ----------
  function desenharMesa(c, desenharTela) {
    const ctx = c.getContext('2d');
    // pernas e tampo
    [[372, 398], [944, 970]].forEach(([a, b]) => { ctx.fillStyle = hgrad(ctx, a, b, [[0, '#f4efff'], [1, '#c9bcef']]); ctx.fillRect(a, 1158, b - a, RODAPE + 12 - 1158); });
    ctx.fillStyle = '#ece4ff'; ctx.beginPath(); ctx.moveTo(366, 1110); ctx.lineTo(990, 1110); ctx.lineTo(1000, 1130); ctx.lineTo(356, 1130); ctx.closePath(); ctx.fill();
    ctx.fillStyle = vgrad(ctx, 1130, 1160, [[0, '#ddd2fa'], [1, '#b7a8e6']]); ctx.fillRect(356, 1130, 644, 30);
    ctx.fillStyle = 'rgba(30,0,70,0.3)'; ctx.fillRect(356, 1158, 644, 6);
    // gabinete debaixo da mesa, com vidro e ventoinhas
    caixa(ctx, 742, 1178, 196, RODAPE + 4 - 1178, 10, '#140c26');
    caixa(ctx, 752, 1188, 176, RODAPE - 16 - 1188, 6, '#1e1238');
    ctx.save(); ctx.beginPath(); rrPath(ctx, 752, 1188, 176, RODAPE - 16 - 1188, 6); ctx.clip();
    ctx.fillStyle = '#120a26'; ctx.fillRect(828, 1196, 92, 210);
    caixa(ctx, 832, 1290, 86, 26, 4, '#2a1a4e');
    ctx.save(); ctx.shadowColor = '#ff5ad8'; ctx.shadowBlur = 14; ctx.fillStyle = '#ff5ad8'; ctx.fillRect(834, 1314, 82, 3); ctx.restore();
    [[794, 1224, '#62f6ff'], [794, 1298, '#ff5ad8'], [794, 1372, '#62f6ff']].forEach(([x, y, cor]) => {
      ctx.save(); ctx.shadowColor = cor; ctx.shadowBlur = 20; ctx.strokeStyle = cor; ctx.lineWidth = 4;
      ctx.beginPath(); ctx.arc(x, y, 26, 0, Math.PI * 2); ctx.stroke(); ctx.restore();
      ctx.fillStyle = '#0c0718'; ctx.beginPath(); ctx.arc(x, y, 22, 0, Math.PI * 2); ctx.fill();
      ctx.strokeStyle = 'rgba(160,130,220,0.5)'; ctx.lineWidth = 3;
      for (let k = 0; k < 5; k++) { const a = k / 5 * Math.PI * 2; ctx.beginPath(); ctx.moveTo(x + Math.cos(a) * 5, y + Math.sin(a) * 5); ctx.quadraticCurveTo(x + Math.cos(a + 0.6) * 14, y + Math.sin(a + 0.6) * 14, x + Math.cos(a + 0.9) * 20, y + Math.sin(a + 0.9) * 20); ctx.stroke(); }
      ctx.fillStyle = '#2a1d50'; ctx.beginPath(); ctx.arc(x, y, 5, 0, Math.PI * 2); ctx.fill();
    });
    ctx.save(); ctx.shadowColor = '#62f6ff'; ctx.shadowBlur = 12; ctx.fillStyle = '#62f6ff'; ctx.fillRect(922, 1196, 3, 200); ctx.restore();
    ctx.fillStyle = 'rgba(255,255,255,0.08)'; ctx.beginPath(); ctx.moveTo(752, 1188); ctx.lineTo(830, 1188); ctx.lineTo(752, 1300); ctx.closePath(); ctx.fill();
    ctx.restore();
    ctx.fillStyle = '#0a0616'; ctx.fillRect(756, RODAPE + 2, 28, 8); ctx.fillRect(896, RODAPE + 2, 28, 8);
    // monitor
    const bx = TELA.x - 14, by = TELA.y - 14, bw = TELA.w + 28, bh = TELA.h + 28;
    ctx.fillStyle = '#16101f'; ctx.fillRect(TELA.x + TELA.w / 2 - 22, by + bh, 44, 1112 - (by + bh));
    caixa(ctx, TELA.x + TELA.w / 2 - 80, 1100, 160, 12, 5, '#1d1530');
    ctx.save(); ctx.shadowColor = 'rgba(100,170,255,0.6)'; ctx.shadowBlur = 40;
    caixa(ctx, bx, by, bw, bh, 12, '#120d1c'); ctx.restore();
    ctx.fillStyle = 'rgba(200,180,255,0.25)'; ctx.fillRect(bx + 12, by + 2, bw - 24, 2);
    ctx.save(); ctx.beginPath(); rrPath(ctx, TELA.x, TELA.y, TELA.w, TELA.h, 3); ctx.clip();
    desenharTela(ctx, TELA);
    ctx.fillStyle = 'rgba(255,255,255,0.07)'; ctx.beginPath(); ctx.moveTo(TELA.x, TELA.y); ctx.lineTo(TELA.x + 170, TELA.y); ctx.lineTo(TELA.x + 60, TELA.y + TELA.h); ctx.lineTo(TELA.x, TELA.y + TELA.h); ctx.closePath(); ctx.fill();
    ctx.restore();
    ctx.fillStyle = '#62f6ff'; ctx.beginPath(); ctx.arc(TELA.x + TELA.w - 6, by + bh - 7, 2.5, 0, Math.PI * 2); ctx.fill();
    // caixa de som à direita
    caixa(ctx, 902, 962, 82, 150, 8, '#17112a');
    ctx.fillStyle = 'rgba(200,170,255,0.2)'; ctx.fillRect(906, 964, 74, 3);
    [[943, 1000, 13, '#ff5ad8'], [943, 1064, 25, '#62f6ff']].forEach(([x, y, r, cor]) => {
      ctx.save(); ctx.shadowColor = cor; ctx.shadowBlur = 12; ctx.strokeStyle = cor; ctx.lineWidth = 4; ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2); ctx.stroke(); ctx.restore();
      ctx.fillStyle = '#0c0818'; ctx.beginPath(); ctx.arc(x, y, r - 4, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#2b2150'; ctx.beginPath(); ctx.arc(x, y, (r - 4) * 0.4, 0, Math.PI * 2); ctx.fill();
    });
    // caneca
    caixa(ctx, 402, 1058, 44, 54, 6, '#1d1533');
    ctx.strokeStyle = '#1d1533'; ctx.lineWidth = 7; ctx.beginPath(); ctx.arc(450, 1084, 13, -1.2, 1.2); ctx.stroke();
    ctx.fillStyle = '#62f6ff'; ctx.beginPath(); ctx.moveTo(424, 1074); ctx.lineTo(432, 1086); ctx.lineTo(416, 1086); ctx.closePath(); ctx.fill();
    ctx.fillStyle = 'rgba(255,255,255,0.15)'; ctx.fillRect(406, 1062, 6, 46);
    // teclado com luz embaixo e mouse
    ctx.save(); ctx.shadowColor = '#ff5ad8'; ctx.shadowBlur = 18;
    ctx.fillStyle = '#1b1530'; ctx.beginPath(); ctx.moveTo(548, 1096); ctx.lineTo(812, 1096); ctx.lineTo(826, 1118); ctx.lineTo(534, 1118); ctx.closePath(); ctx.fill(); ctx.restore();
    for (let r = 0; r < 3; r++) for (let k = 0; k < 15; k++) {
      const t = k / 15, x0 = 552 + t * 256 - r * 4, y0 = 1099 + r * 6;
      ctx.fillStyle = r === 2 && k > 4 && k < 11 ? 'rgba(90,216,255,0.6)' : '#342a5c';
      ctx.fillRect(x0, y0, 15, 4);
    }
    ctx.fillStyle = hgrad(ctx, 534, 826, [[0, '#ff5ad8'], [0.5, '#b46bff'], [1, '#62f6ff']]); ctx.fillRect(536, 1117, 288, 3);
    ctx.fillStyle = '#1b1530'; ctx.beginPath(); ctx.ellipse(858, 1110, 17, 9, 0, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = '#62f6ff'; ctx.fillRect(857, 1102, 2, 7);
  }

  // ---------- cadeira gamer (3/4, virada para quem assiste) ----------
  function desenharCadeira(c) {
    const ctx = c.getContext('2d');
    ctx.save(); ctx.filter = 'blur(12px)'; ctx.fillStyle = 'rgba(6,0,24,0.6)';
    ctx.beginPath(); ctx.ellipse(282, 1672, 230, 34, 0, 0, Math.PI * 2); ctx.fill(); ctx.restore();
    // base em estrela com rodízios
    const cx = 276, cy = 1606;
    [[86, 1650], [168, 1680], [392, 1680], [470, 1650], [282, 1632]].forEach(([x, y]) => {
      ctx.strokeStyle = '#1a1430'; ctx.lineWidth = 20; ctx.lineCap = 'round'; ctx.beginPath(); ctx.moveTo(cx, cy); ctx.lineTo(x, y - 12); ctx.stroke();
      ctx.strokeStyle = 'rgba(170,140,240,0.3)'; ctx.lineWidth = 4; ctx.beginPath(); ctx.moveTo(cx, cy - 7); ctx.lineTo(x, y - 19); ctx.stroke();
      ctx.fillStyle = '#0f0a1e'; ctx.beginPath(); ctx.arc(x, y, 15, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = 'rgba(170,140,240,0.35)'; ctx.beginPath(); ctx.arc(x - 4, y - 5, 4, 0, Math.PI * 2); ctx.fill();
    });
    ctx.fillStyle = hgrad(ctx, 260, 292, [[0, '#4a4170'], [0.5, '#2a2246'], [1, '#17112b']]); ctx.fillRect(260, 1404, 32, cy - 1404);
    // assento
    ctx.save(); ctx.shadowColor = 'rgba(6,0,24,0.6)'; ctx.shadowBlur = 20; ctx.shadowOffsetY = 12;
    ctx.beginPath(); rrPath(ctx, 66, 1318, 412, 96, 44); ctx.fillStyle = vgrad(ctx, 1318, 1414, [[0, '#2e2350'], [1, '#150e28']]); ctx.fill(); ctx.restore();
    ctx.save(); ctx.shadowColor = '#6dff8a'; ctx.shadowBlur = 16; ctx.strokeStyle = '#6dff8a'; ctx.lineWidth = 6;
    ctx.beginPath(); ctx.moveTo(108, 1404); ctx.lineTo(436, 1404); ctx.stroke(); ctx.restore();
    // encosto (lado esquerdo aparece porque a cadeira está girada)
    ctx.fillStyle = '#0d0819';
    ctx.beginPath(); ctx.moveTo(98, 1012); ctx.lineTo(120, 1000); ctx.lineTo(132, 1330); ctx.lineTo(112, 1322); ctx.closePath(); ctx.fill();
    ctx.beginPath(); ctx.moveTo(132, 1332); ctx.lineTo(120, 1000); ctx.quadraticCurveTo(122, 906, 252, 898); ctx.quadraticCurveTo(386, 904, 384, 1000); ctx.lineTo(372, 1332); ctx.closePath();
    const ge = ctx.createRadialGradient(250, 1080, 30, 250, 1120, 260); ge.addColorStop(0, '#2c2150'); ge.addColorStop(1, '#130c25');
    ctx.fillStyle = ge; ctx.fill();
    ctx.strokeStyle = 'rgba(190,160,255,0.25)'; ctx.lineWidth = 3; ctx.stroke();
    // almofada lombar
    ctx.beginPath(); rrPath(ctx, 172, 1176, 160, 62, 26); ctx.fillStyle = '#251b44'; ctx.fill();
    ctx.strokeStyle = 'rgba(109,255,138,0.6)'; ctx.lineWidth = 3; ctx.stroke();
    // filetes verdes de neon do encosto
    neon(ctx, () => { ctx.beginPath(); ctx.moveTo(176, 1320); ctx.lineTo(168, 1060); ctx.quadraticCurveTo(166, 960, 214, 930); }, '#6dff8a', 6);
    neon(ctx, () => { ctx.beginPath(); ctx.moveTo(328, 1320); ctx.lineTo(336, 1060); ctx.quadraticCurveTo(338, 960, 290, 930); }, '#6dff8a', 6);
    neon(ctx, () => { ctx.beginPath(); ctx.ellipse(252, 962, 30, 36, 0, 0, Math.PI * 2); }, '#6dff8a', 6);
    // braços da cadeira
    [[60, 150, 86], [404, 494, 432]].forEach(([a, b, p]) => {
      ctx.fillStyle = '#17112b'; ctx.fillRect(p - 9, 1238, 18, 86);
      caixa(ctx, a, 1222, b - a, 24, 10, '#1d1534');
      ctx.save(); ctx.shadowColor = '#6dff8a'; ctx.shadowBlur = 10; ctx.fillStyle = '#6dff8a'; ctx.fillRect(a + 10, 1222, b - a - 20, 3); ctx.restore();
    });
  }

  // ---------- pufe com controle ----------
  function desenharPufe(c) {
    const ctx = c.getContext('2d');
    ctx.save(); ctx.filter = 'blur(16px)'; ctx.fillStyle = 'rgba(6,0,24,0.6)';
    ctx.beginPath(); ctx.ellipse(860, 1885, 260, 40, 0, 0, Math.PI * 2); ctx.fill(); ctx.restore();
    ctx.beginPath();
    ctx.moveTo(612, 1890); ctx.bezierCurveTo(590, 1760, 640, 1620, 780, 1572); ctx.bezierCurveTo(900, 1532, 1040, 1560, 1090, 1660);
    ctx.lineTo(1090, 1900); ctx.bezierCurveTo(940, 1925, 760, 1925, 612, 1890); ctx.closePath();
    const g = ctx.createRadialGradient(760, 1640, 20, 860, 1760, 330);
    g.addColorStop(0, '#c37af5'); g.addColorStop(0.45, '#8d3fe0'); g.addColorStop(1, '#4d1c9c');
    ctx.fillStyle = g; ctx.fill();
    ctx.strokeStyle = 'rgba(60,15,120,0.55)'; ctx.lineWidth = 6;
    ctx.beginPath(); ctx.moveTo(700, 1760); ctx.bezierCurveTo(800, 1700, 920, 1700, 1040, 1740); ctx.stroke();
    ctx.strokeStyle = 'rgba(255,220,255,0.35)'; ctx.lineWidth = 4;
    ctx.beginPath(); ctx.moveTo(650, 1700); ctx.bezierCurveTo(690, 1620, 760, 1590, 840, 1580); ctx.stroke();
    // controle
    ctx.save(); ctx.translate(850, 1690); ctx.rotate(-0.22);
    ctx.fillStyle = '#191326';
    ctx.beginPath(); ctx.moveTo(-70, -22); ctx.quadraticCurveTo(0, -36, 70, -22); ctx.quadraticCurveTo(100, -10, 96, 30);
    ctx.quadraticCurveTo(90, 52, 66, 40); ctx.quadraticCurveTo(40, 20, 0, 18); ctx.quadraticCurveTo(-40, 20, -66, 40);
    ctx.quadraticCurveTo(-90, 52, -96, 30); ctx.quadraticCurveTo(-100, -10, -70, -22); ctx.closePath(); ctx.fill();
    ctx.strokeStyle = 'rgba(190,160,255,0.35)'; ctx.lineWidth = 2.5; ctx.stroke();
    ctx.fillStyle = '#3a2f5c'; ctx.fillRect(-58, -6, 26, 8); ctx.fillRect(-49, -15, 8, 26);
    [[44, -10, '#62f6ff'], [56, 0, '#ff5ad8'], [44, 10, '#5cf5a8'], [32, 0, '#ffd257']].forEach(([x, y, cor]) => {
      ctx.fillStyle = cor; ctx.beginPath(); ctx.arc(x, y, 5, 0, Math.PI * 2); ctx.fill();
    });
    ctx.fillStyle = '#0e0a18'; ctx.beginPath(); ctx.arc(-18, 10, 9, 0, Math.PI * 2); ctx.arc(18, 10, 9, 0, Math.PI * 2); ctx.fill();
    ctx.restore();
  }

  // ---------- luzes ----------
  function desenharLuz(c) {
    const ctx = c.getContext('2d');
    brilho(ctx, 646, 330, 330, 'rgba(98,246,255,A)', 0.26);
    brilho(ctx, 230, 330, 300, 'rgba(70,230,170,A)', 0.18);
    brilho(ctx, 900, 170, 280, 'rgba(255,90,216,A)', 0.2);
    brilho(ctx, 1010, 740, 240, 'rgba(255,90,216,A)', 0.18);
    brilho(ctx, TELA.x + TELA.w / 2, TELA.y + TELA.h / 2, 460, 'rgba(110,170,255,A)', 0.24);
    brilho(ctx, 840, RODAPE, 220, 'rgba(255,90,216,A)', 0.2);
    brilho(ctx, 252, 1100, 260, 'rgba(109,255,138,A)', 0.1);
  }

  function desenharAcabamento(c) {
    const ctx = c.getContext('2d'); const R = rng(SEMENTE + 9);
    const gv = ctx.createRadialGradient(540, 1000, 520, 540, 1000, 1300);
    gv.addColorStop(0, 'rgba(10,2,30,0)'); gv.addColorStop(1, 'rgba(10,2,30,0.5)');
    ctx.fillStyle = gv; ctx.fillRect(0, 0, W, H);
    for (let i = 0; i < 9000; i++) {
      ctx.fillStyle = R() < 0.5 ? 'rgba(255,255,255,0.03)' : 'rgba(0,0,0,0.05)';
      ctx.fillRect(R() * W, R() * H, 1.2, 1.2);
    }
  }

  // ---------- montagem ----------
  function telaRpgPadrao() {
    const rpg = RPGDesenho.criar();
    const rpgCanvas = camada();
    rpg.compor(rpgCanvas.getContext('2d'));
    return (ctx, T) => ctx.drawImage(rpgCanvas, RECORTE_RPG.x, RECORTE_RPG.y, RECORTE_RPG.w, RECORTE_RPG.h, T.x, T.y, T.w, T.h);
  }
  function criar(o) {
    o = o || {};
    const L = {
      parede: camada(), chao: camada(), decoracao: camada(), neons: camada(), mesa_pc: camada(),
      cadeira: camada(), pufe: camada(), luz: camada(), acabamento: camada(),
    };
    desenharParede(L.parede);
    desenharChao(L.chao);
    desenharDecoracao(L.decoracao);
    desenharNeons(L.neons);
    desenharMesa(L.mesa_pc, o.tela || telaRpgPadrao());
    desenharCadeira(L.cadeira);
    desenharPufe(L.pufe);
    desenharLuz(L.luz);
    desenharAcabamento(L.acabamento);
    function compor(ctx) {
      for (const nome of Object.keys(L)) {
        ctx.globalCompositeOperation = nome === 'luz' ? 'screen' : 'source-over';
        ctx.drawImage(L[nome], 0, 0);
      }
      ctx.globalCompositeOperation = 'source-over';
    }
    // redesenha a mesa com outro conteúdo na tela (jogo vivo, um quadro por vez)
    function redesenharMesa(tela) {
      L.mesa_pc.getContext('2d').clearRect(0, 0, W, H);
      desenharMesa(L.mesa_pc, tela);
    }
    return { L, compor, redesenharMesa, TELA };
  }

  window.SetupDesenho = { W, H, TELA, RECORTE_RPG, RODAPE, criar };
})();
