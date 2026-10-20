// Composição com transições contínuas (linguagem do Manifesto): cada passagem desenha as duas cenas em
// camadas separadas e junta as duas com câmera (zoom, pan seguindo o cabo, íris da mão, luz revelando,
// entrar no monitor, moldura abrindo). Usado por comp_trans.html.
(function() {
  'use strict';
  const W = 1080, H = 1920;
  const main = document.getElementById('main-canvas');
  const ctx = main.getContext('2d');
  const echoCanvas = document.getElementById('echo-canvas');
  const logoImg = document.getElementById('logo-img');
  window.__meta = { dur: 17.0, fps: 60, width: W, height: H };

  const director = new EchoDirectorTI(echoCanvas, { size: 1000 });
  director.render = function() { this.bot.draw(); };
  window.director = director;
  window.robo = director.bot;                        // cenas_anim.js usa para os olhos acesos no apagão

  const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
  const lerp = (a, b, u) => a + (b - a) * u;
  const inv = (a, b, v) => clamp((v - a) / (b - a), 0, 1);
  const eIO = (u) => { u = clamp(u, 0, 1); return u < 0.5 ? 4 * u * u * u : 1 - Math.pow(-2 * u + 2, 3) / 2; };
  const eOut = (u) => 1 - Math.pow(1 - clamp(u, 0, 1), 3);
  const eIn = (u) => Math.pow(clamp(u, 0, 1), 3);

  const C = window.CenasAnimTI;
  const CENAS = [C.animCena01, C.animCena02, C.animCena03, C.animCena04, C.animCena05,
                 C.animCena06, C.animCena07, C.animCena08, C.animCena09, C.animCena10];
  const CORTES = [0, 2.3, 3.3, 4.2, 4.9, 7.4, 8.6, 9.1, 10.7, 13.1, 17.0];
  const camada = () => { const c = document.createElement('canvas'); c.width = W; c.height = H; return c; };
  const A = camada(), B = camada(), LEG = camada();

  function resetBot() {
    const b = director.bot;
    b.waveSide = 0; b.s.hands = 0; b.s.tilt = 0; b.s.yaw = 0; b.s.pitch = 0; b.s.roll = 0;
    b.col = director.colCyan; b.colT = director.colCyan; b.eyeOv = null;
    b.look.x = 0; b.look.y = 0; b.s.mouthOpen = 0; b.t0 = 0;
  }
  function boca(t) {
    const i = Math.round(t * 30);
    return (window.BOCA_DADOS && i >= 0 && i < window.BOCA_DADOS.length) ? window.BOCA_DADOS[i] : 0;
  }
  // desenha a cena i no tempo t (preso ao intervalo dela) numa camada; devolve a legenda registrada
  function cena(i, t, alvo) {
    const tc = clamp(t, CORTES[i], CORTES[i + 1] - 0.001);
    const tt = tc;
    const x = alvo.getContext('2d');
    x.setTransform(1, 0, 0, 1, 0, 0); x.globalAlpha = 1; x.globalCompositeOperation = 'source-over';
    x.clearRect(0, 0, W, H);
    resetBot();
    window.__legendaGravada = null;
    if (i === 9) CENAS[i](x, tt, director, echoCanvas, logoImg, boca(tt));
    else CENAS[i](x, tt, director, echoCanvas, boca(tt));
    return window.__legendaGravada;
  }
  // câmera: desenha a camada com zoom z em torno do foco (fx, fy), deslocada (dx, dy)
  function cam(fonte, z, fx, fy, dx, dy, alfa) {
    ctx.save();
    ctx.globalAlpha = alfa == null ? 1 : alfa;
    ctx.translate(fx + (dx || 0), fy + (dy || 0)); ctx.scale(z, z); ctx.translate(-fx, -fy);
    ctx.drawImage(fonte, 0, 0);
    ctx.restore();
  }
  function maoDisco(x, y, r) {                        // mão oficial do Echo (#7fe3f5 / #00d4ff) em primeiro plano
    if (r <= 0.5) return;
    ctx.save();
    ctx.fillStyle = '#7fe3f5'; ctx.strokeStyle = '#00d4ff'; ctx.lineWidth = Math.max(4, r * 0.156);
    ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
    ctx.restore();
  }
  function caboVertical(x, y0, y1) {                  // o cabo que a câmera segue entre as tarefas
    ctx.save(); ctx.lineCap = 'round';
    ctx.strokeStyle = '#111c26'; ctx.lineWidth = 34; ctx.beginPath(); ctx.moveTo(x, y0); ctx.lineTo(x, y1); ctx.stroke();
    ctx.strokeStyle = '#0284c7'; ctx.lineWidth = 20; ctx.stroke();
    ctx.strokeStyle = 'rgba(255,255,255,0.28)'; ctx.lineWidth = 4; ctx.beginPath(); ctx.moveTo(x - 4, y0); ctx.lineTo(x - 4, y1); ctx.stroke();
    ctx.restore();
  }

  // ---------- transições (janela em torno de cada corte) ----------
  const TRANS = [
    { // 1 → 2: a mão vem até a lente, cobre o quadro e se afasta já na bancada
      de: 2.02, ate: 2.62, faz(t) {
        const hx = 790, hy = 1290;
        if (t < 2.3) {
          cena(0, t, A); cam(A, 1 + 0.06 * eIn(inv(2.02, 2.3, t)), hx, hy);
          maoDisco(hx, hy, lerp(150, 2300, eIn(inv(2.02, 2.3, t))));
        } else {
          cena(1, t, B); cam(B, 1, 0, 0);
          const u = eOut(inv(2.3, 2.62, t));
          maoDisco(lerp(hx, 1400, u), lerp(hy, 2500, u), lerp(2300, 120, u));
        }
      } },
    { // 2 → 3: a câmera desce seguindo o cabo, da tela para o switch
      de: 3.12, ate: 3.48, faz(t) {
        const u = eIO(inv(3.12, 3.48, t));
        cena(1, t, A); cena(2, t, B);
        cam(A, 1, 0, 0, 0, -u * H); cam(B, 1, 0, 0, 0, (1 - u) * H);
        caboVertical(640, (1 - u) * H - 380, (1 - u) * H + 260);
      } },
    { // 3 → 4: continua descendo pelo cabo, do switch para o HD
      de: 4.04, ate: 4.36, faz(t) {
        const u = eIO(inv(4.04, 4.36, t));
        cena(2, t, A); cena(3, t, B);
        cam(A, 1, 0, 0, 0, -u * H); cam(B, 1, 0, 0, 0, (1 - u) * H);
        caboVertical(560, (1 - u) * H - 380, (1 - u) * H + 200);
      } },
    { // 4 → 5: a câmera recua do HD e revela o rack embolado
      de: 4.72, ate: 5.2, faz(t) {
        cena(3, t, A); cena(4, t, B);
        const u = eIO(inv(4.72, 5.2, t));
        cam(B, lerp(1.7, 1, eOut(inv(4.8, 5.2, t))), 540, 1150);
        cam(A, lerp(1, 0.5, u), 540, 1150, 0, 0, 1 - eIn(inv(4.8, 4.98, t)));
      } },
    { // 5 → 6: a aproximação continua e desce pelo cabo até o plugue
      de: 7.18, ate: 7.62, faz(t) {
        if (t < 7.4) { cena(4, t, A); cam(A, Math.exp(Math.log(2.6) * eIn(inv(7.18, 7.4, t))), 900, 1520); }
        else { cena(5, t, B); cam(B, Math.exp(Math.log(2.2) * (1 - eOut(inv(7.4, 7.62, t)))), 330, 1250); }
      } },
    { // 6 → 7: o plugue sai, a luz pisca e apaga: o apagão é a transição
      de: 8.3, ate: 8.6, faz(t) {
        cena(5, t, A); cam(A, 1, 0, 0);
        let k = eIn(inv(8.3, 8.6, t));
        if ((t > 8.36 && t < 8.40) || (t > 8.46 && t < 8.49)) k = Math.max(k, 0.65);
        ctx.fillStyle = 'rgba(2, 5, 9, ' + (0.93 * k) + ')'; ctx.fillRect(0, 0, W, H);
      } },
    { // 7 → 8: a luminária acende e a mesa aparece junto com a luz
      de: 8.98, ate: 9.38, faz(t) {
        cena(6, t, A); cam(A, 1, 0, 0);
        cena(7, t, B);
        const lx = 890, ly = 700, r = lerp(0, 2300, eIO(inv(8.98, 9.38, t)));
        ctx.save(); ctx.beginPath(); ctx.arc(lx, ly, r, 0, Math.PI * 2); ctx.clip(); ctx.drawImage(B, 0, 0); ctx.restore();
        if (r > 1) {
          const g = ctx.createRadialGradient(lx, ly, r * 0.85, lx, ly, r);
          g.addColorStop(0, 'rgba(251,191,36,0)'); g.addColorStop(1, 'rgba(251,191,36,0.35)');
          ctx.fillStyle = g; ctx.beginPath(); ctx.arc(lx, ly, r, 0, Math.PI * 2); ctx.fill();
        }
      } },
    { // 8 → 9: a câmera entra no monitor da mesa e a tela vira o telão do terminal
      de: 10.42, ate: 10.96, faz(t) {
        // as duas camadas se misturam no ponto de zoom máximo, para a troca não ler como corte
        const mix = eIO(inv(10.6, 10.8, t));
        if (mix < 1) { cena(7, t, A); cam(A, Math.exp(Math.log(3.3) * eIn(inv(10.42, 10.7, t))), 860, 490); }
        if (mix > 0) { cena(8, t, B); cam(B, lerp(1.35, 1, eOut(inv(10.7, 10.96, t))), 540, 520, 0, 0, mix); }
      } },
    { // 9 → 10: a tela do terminal se abre ao meio e revela a marca
      de: 13.04, ate: 13.48, faz(t) {
        cena(9, t, B); cam(B, lerp(1.12, 1, eOut(inv(13.04, 13.48, t))), 540, 600);
        cena(8, t, A);
        const a = eIO(inv(13.04, 13.48, t)), meio = 960;
        ctx.drawImage(A, 0, 0, W, meio, 0, -a * meio, W, meio);
        ctx.drawImage(A, 0, meio, W, H - meio, 0, meio + a * (H - meio), W, H - meio);
        ctx.fillStyle = 'rgba(0, 212, 255, ' + (0.6 * Math.sin(Math.PI * a)) + ')';
        ctx.fillRect(0, meio - a * meio - 4, W, 8); ctx.fillRect(0, meio + a * (H - meio) - 4, W, 8);
      } }
  ];

  function acabamento() {                              // vinheta leve, igual em todas as cenas
    const g = ctx.createRadialGradient(W / 2, H * 0.48, H * 0.32, W / 2, H * 0.5, H * 0.78);
    g.addColorStop(0, 'rgba(0,0,0,0)'); g.addColorStop(1, 'rgba(0,0,0,0.32)');
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
  }

  window.__ready = (async function() {
    await Promise.all([
      document.fonts.load("bold 48px 'Orbitron'"), document.fonts.load("bold 34px 'Orbitron'"),
      document.fonts.load("bold 20px 'JetBrains Mono'"), document.fonts.load("16px 'JetBrains Mono'"), document.fonts.ready
    ]);
    if (!logoImg.complete) await new Promise((r) => { logoImg.onload = r; logoImg.onerror = r; });
    return true;
  })();

  window.__seek = async function(t) {
    window.__simTimeMs = t * 1000.0;
    window.__legendaModo = 'gravar';
    ctx.setTransform(1, 0, 0, 1, 0, 0); ctx.globalAlpha = 1; ctx.globalCompositeOperation = 'source-over';
    ctx.fillStyle = '#060c12'; ctx.fillRect(0, 0, W, H);
    let dono = 0;
    while (dono < 9 && t >= CORTES[dono + 1]) dono++;
    const tr = TRANS.find((x) => t >= x.de && t < x.ate);
    let legenda;
    if (tr) { tr.faz(t); legenda = cena(dono, t, LEG); }   // a legenda segue a fala (cena dona do tempo), sem câmera
    else if (dono === 9) {                         // fecho: aproximação lenta até 16,3 s, depois quadro final parado
      legenda = cena(dono, Math.min(t, 16.3), A);
      cam(A, 1 + 0.06 * eOut(inv(14.9, 16.3, t)), 540, 900);
    }
    else { legenda = cena(dono, t, A); cam(A, 1, 0, 0); }
    acabamento();
    window.__legendaModo = 'normal';
    if (legenda) C.desenharLegendaAnimada(ctx, legenda[0], legenda[1]);
    return true;
  };
})();
