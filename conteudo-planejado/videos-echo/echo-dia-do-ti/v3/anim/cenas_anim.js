(function(window) {
  'use strict';

  // =========================================================================
  // PALETA E CONSTANTES OFICIAIS DA VERSÃO 3
  // =========================================================================
  const PALETA = {
    ink: '#0a1017',
    cyanGlow: '#00d4ff',
    cyanCore: '#7fe3f5',
    bgDark: '#08131d',
    bgFloor: '#050c12',
    wallBase: '#122630',
    wallLight: '#183340',
    wallDark: '#0a1820',
    rackFrame: '#1a2936',
    rackFace: '#0d1822',
    rackRail: '#223545',
    floorBase: '#060e14',
    floorGrid: '#0e1f2b',
    cableDark: '#0c1622',
    cableDark2: '#162838',
    cableBlue: '#0284c7',
    cableBlueDark: '#0369a1',
    cableYellow: '#eab308',
    ledGreen: '#22c55e',
    ledAmber: '#f59e0b',
    ledRed: '#ef4444',
    ledCyan: '#00d4ff',
    deskWood: '#b45309',
    deskWoodDark: '#78350f'
  };

  // Funções de Easing puras e determinísticas (Motionmaxxing)
  const clamp = (v, min, max) => Math.max(min, Math.min(max, v));
  const lerp = (a, b, t) => a + (b - a) * t;
  const easeInQuad = t => t * t;
  const easeOutQuad = t => t * (2 - t);
  const easeInOutQuad = t => t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t;
  const easeOutCubic = t => (--t) * t * t + 1;
  const easeInCubic = t => t * t * t;
  const easeInOutCubic = t => t < 0.5 ? 4 * t * t * t : (t - 1) * (2 * t - 2) * (2 * t - 2) + 1;
  const easeOutBack = (t, s = 1.70158) => {
    t = t - 1;
    return t * t * ((s + 1) * t + s) + 1;
  };

  // =========================================================================
  // FUNÇÕES AUXILIARES DE DESENHO (PRESERVADAS DA V3)
  // =========================================================================
  function drawRoundedRect(ctx, x, y, w, h, r, fill, stroke, strokeWidth = 6) {
    ctx.save();
    ctx.beginPath();
    if (typeof ctx.roundRect === 'function') {
      ctx.roundRect(x, y, w, h, r);
    } else {
      ctx.rect(x, y, w, h);
    }
    if (fill) {
      ctx.fillStyle = fill;
      ctx.fill();
    }
    if (stroke) {
      ctx.strokeStyle = stroke;
      ctx.lineWidth = strokeWidth;
      ctx.lineJoin = 'round';
      ctx.stroke();
    }
    ctx.restore();
  }

  function drawThickCable(ctx, pts, color, colorDark, width = 20, inkWidth = 7) {
    if (!pts || pts.length < 4) return;
    ctx.save();
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    function tracePath() {
      ctx.beginPath();
      ctx.moveTo(pts[0], pts[1]);
      if (pts.length === 4) {
        ctx.lineTo(pts[2], pts[3]);
      } else if (pts.length === 6) {
        ctx.quadraticCurveTo(pts[2], pts[3], pts[4], pts[5]);
      } else if (pts.length >= 8) {
        ctx.bezierCurveTo(pts[2], pts[3], pts[4], pts[5], pts[6], pts[7]);
      }
    }

    ctx.strokeStyle = PALETA.ink;
    ctx.lineWidth = width + inkWidth * 2;
    tracePath();
    ctx.stroke();

    ctx.strokeStyle = color;
    ctx.lineWidth = width;
    tracePath();
    ctx.stroke();

    if (colorDark) {
      ctx.save();
      ctx.strokeStyle = colorDark;
      ctx.lineWidth = width * 0.40;
      tracePath();
      ctx.stroke();
      ctx.restore();
    }
    ctx.restore();
  }

  function drawPlugue2Pinos(ctx, x, y, angle = 0, scale = 1.0) {
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(angle);
    ctx.scale(scale, scale);

    const pinW = 6, pinH = 26;
    ctx.fillStyle = '#cbd5e1';
    ctx.strokeStyle = PALETA.ink;
    ctx.lineWidth = 3;

    drawRoundedRect(ctx, -14, -pinH, pinW, pinH, 3, '#cbd5e1', PALETA.ink, 3);
    drawRoundedRect(ctx, 8, -pinH, pinW, pinH, 3, '#cbd5e1', PALETA.ink, 3);

    drawRoundedRect(ctx, -24, 0, 48, 56, 12, '#1e293b', PALETA.ink, 5);
    drawRoundedRect(ctx, -18, 56, 36, 18, 6, '#0f172a', PALETA.ink, 4);

    ctx.fillStyle = '#334155';
    for (let gy = 12; gy < 48; gy += 10) {
      ctx.fillRect(-18, gy, 36, 4);
    }
    ctx.restore();
  }

  function maoEcho(ctx, x, y, tamanhoEcho, escala = 1) {
    const k = tamanhoEcho / 280;
    const r = 84 * 0.18 * 0.85 * k * escala;
    ctx.save();
    ctx.fillStyle = '#7fe3f5';
    ctx.strokeStyle = '#00d4ff';
    ctx.lineWidth = 2 * k * escala;
    ctx.beginPath();
    ctx.arc(x, y, r, 0, Math.PI * 2);
    ctx.fill();
    ctx.stroke();
    ctx.restore();
  }

  function bocaFalando(director, echoCanvas, valor) {
    const b = director.bot;
    b.s.mouthOpen = valor > 0.05 ? Math.min(1, valor) : 0;
    b.draw();
  }

  function desenharEcho(ctxDest, canvasEcho, x, y, largura, altura, opts = {}) {
    const strokeWidth = opts.strokeWidth || 7;
    const strokeColor = opts.strokeColor || PALETA.ink;
    const apagado = opts.apagado || false;

    const off = document.createElement('canvas');
    off.width = canvasEcho.width;
    off.height = canvasEcho.height;
    const offCtx = off.getContext('2d');

    if (apagado) {
      offCtx.drawImage(canvasEcho, 0, 0);
      offCtx.globalCompositeOperation = 'source-in';
      offCtx.fillStyle = '#061019';
      offCtx.fillRect(0, 0, off.width, off.height);
      offCtx.globalCompositeOperation = 'source-over';

      const bot = window.robo;
      if (bot) {
        const cx = off.width / 2;
        const cy = off.height * 0.42;
        offCtx.save();
        offCtx.fillStyle = '#00d4ff';
        offCtx.shadowColor = '#00d4ff';
        offCtx.shadowBlur = 32;
        offCtx.beginPath();
        offCtx.ellipse(cx - 55, cy, 26, 38, 0, 0, Math.PI * 2);
        offCtx.ellipse(cx + 55, cy, 26, 38, 0, 0, Math.PI * 2);
        offCtx.fill();
        offCtx.restore();
      }
    } else {
      offCtx.drawImage(canvasEcho, 0, 0);
    }

    ctxDest.save();
    if (strokeWidth > 0 && !apagado) {
      const d = strokeWidth;
      const offsets = [
        [-d, 0], [d, 0], [0, -d], [0, d],
        [-d*0.7, -d*0.7], [d*0.7, -d*0.7], [-d*0.7, d*0.7], [d*0.7, d*0.7]
      ];
      const sil = document.createElement('canvas');
      sil.width = off.width;
      sil.height = off.height;
      const sCtx = sil.getContext('2d');
      sCtx.drawImage(off, 0, 0);
      sCtx.globalCompositeOperation = 'source-in';
      sCtx.fillStyle = strokeColor;
      sCtx.fillRect(0, 0, sil.width, sil.height);

      for (const [ox, oy] of offsets) {
        ctxDest.drawImage(sil, x + ox, y + oy, largura, altura);
      }
    }
    ctxDest.drawImage(off, x, y, largura, altura);
    ctxDest.restore();
  }

  // =========================================================================
  // LEGENDA ANIMADA COM DESTAQUE PROGRESSIVO (ÁREA SEGURA V3 GARANTIDA)
  // =========================================================================
  function desenharLegendaAnimada(ctx, linhasData, tVideo) {
    if (!linhasData || linhasData.length === 0) return null;
    // comp_trans.html: durante as transições as cenas só registram a legenda; ela é desenhada uma vez, por cima, sem câmera
    if (window.__legendaModo === 'gravar') { window.__legendaGravada = [linhasData, tVideo]; return null; }

    const w = 1080;
    const yCenter = 250;
    const maxSafeW = 850;

    function measureLineWidth(fs, lWords) {
      ctx.save();
      ctx.font = `bold ${fs}px 'Orbitron', -apple-system, sans-serif`;
      let totalW = 0;
      for (let i = 0; i < lWords.length; i++) {
        totalW += ctx.measureText(lWords[i].text).width;
        if (i < lWords.length - 1) {
          totalW += ctx.measureText(' ').width;
        }
      }
      ctx.restore();
      return totalW;
    }

    let fontSize = 48;
    const minFontSize = 32;
    while (fontSize > minFontSize) {
      let overflow = false;
      for (const linha of linhasData) {
        if (measureLineWidth(fontSize, linha) > maxSafeW) {
          overflow = true;
          break;
        }
      }
      if (!overflow) break;
      fontSize -= 2;
    }

    const lineHeight = Math.round(fontSize * 1.30);
    const nLinhas = linhasData.length;
    const totalH = (nLinhas - 1) * lineHeight;
    const startY = yCenter - totalH / 2;

    ctx.save();
    ctx.font = `bold ${fontSize}px 'Orbitron', -apple-system, sans-serif`;
    ctx.textAlign = 'left';
    ctx.textBaseline = 'middle';
    ctx.lineJoin = 'round';

    for (let lIdx = 0; lIdx < nLinhas; lIdx++) {
      const linha = linhasData[lIdx];
      const lineY = startY + lIdx * lineHeight;
      const lineW = measureLineWidth(fontSize, linha);
      let curX = (w - lineW) / 2;
      const spaceW = ctx.measureText(' ').width;

      // 1. Passe do Contorno (Ink espesso 10px) para todas as palavras
      for (let wIdx = 0; wIdx < linha.length; wIdx++) {
        const item = linha[wIdx];
        ctx.strokeStyle = PALETA.ink;
        ctx.lineWidth = 10;
        ctx.strokeText(item.text, curX, lineY);
        curX += ctx.measureText(item.text).width + (wIdx < linha.length - 1 ? spaceW : 0);
      }

      // 2. Passe do Preenchimento com destaque progressivo da palavra falada
      curX = (w - lineW) / 2;
      for (let wIdx = 0; wIdx < linha.length; wIdx++) {
        const item = linha[wIdx];
        const isSpoken = (tVideo >= (item.t || 0));
        let fillColor = item.baseColor || '#ffffff';

        if (isSpoken) {
          fillColor = item.highlightColor || '#00d4ff';
        }

        ctx.fillStyle = fillColor;
        ctx.fillText(item.text, curX, lineY);
        curX += ctx.measureText(item.text).width + (wIdx < linha.length - 1 ? spaceW : 0);
      }
    }
    ctx.restore();
  }

  // =========================================================================
  // AS 10 CENAS ANIMADAS
  // =========================================================================

  /**
   * C01: 0.0s – 2.3s ("Ei, você aí do TI?")
   * Echo emerge de baixo com easing, antecipação, aproximação da mão esférica à câmera,
   * micro-push de câmera e sincronização labial com boca.json.
   */
  function animCena01(ctx, t, director, echoCanvas, mouthVal) {
    const w = 1080, h = 1920;
    const tRel = clamp(t / 2.3, 0.0, 1.0);

    // Micro push-in de câmera suave
    const camScale = lerp(1.0, 1.025, easeOutQuad(tRel));
    ctx.save();
    ctx.translate(w / 2, 960);
    ctx.scale(camScale, camScale);
    ctx.translate(-w / 2, -960);

    // Fundo da Cena 1
    ctx.fillStyle = '#0d222b';
    ctx.fillRect(0, 0, w, h);

    drawRoundedRect(ctx, -20, -20, w + 40, 260, 0, '#152b36', PALETA.ink, 6);
    for (let tx = 80; tx < w; tx += 180) {
      drawRoundedRect(ctx, tx, 40, 90, 80, 8, '#243b47', PALETA.ink, 4);
      ctx.fillStyle = (tx % 360 === 0) ? PALETA.cableYellow : PALETA.cableBlue;
      ctx.fillRect(tx + 10, 60, 70, 16);
      ctx.fillRect(tx + 10, 85, 70, 16);
    }
    drawThickCable(ctx, [0, 160, 400, 160, 800, 160, w, 160], '#475569', '#1e293b', 18, 5);

    ctx.fillStyle = '#08151a';
    ctx.beginPath();
    ctx.moveTo(0, 1080);
    ctx.lineTo(w, 1080);
    ctx.lineTo(w, h);
    ctx.lineTo(0, h);
    ctx.fill();

    ctx.strokeStyle = '#122e38';
    ctx.lineWidth = 4;
    for (let lx = -200; lx < w + 400; lx += 160) {
      ctx.beginPath();
      ctx.moveTo(lx, 1080);
      ctx.lineTo(lx - 240, h);
      ctx.stroke();
    }

    // Racks com LEDs
    const racks = [
      { x: 30, y: 320, rw: 240, rh: 1050 },
      { x: 810, y: 320, rw: 240, rh: 1050 }
    ];
    racks.forEach(rb => {
      drawRoundedRect(ctx, rb.x, rb.y, rb.rw, rb.rh, 16, '#18303d', PALETA.ink, 6);
      drawRoundedRect(ctx, rb.x + 15, rb.y + 20, rb.rw - 30, rb.rh - 40, 10, '#0f2029', PALETA.ink, 4);
      for (let ly = rb.y + 60; ly < rb.y + rb.rh - 60; ly += 55) {
        ctx.save();
        const col = (ly % 110 === 0) ? PALETA.ledGreen : ((ly % 165 === 0) ? PALETA.ledAmber : PALETA.ledCyan);
        ctx.shadowColor = col;
        ctx.shadowBlur = 14;
        ctx.fillStyle = col;
        ctx.beginPath();
        ctx.arc(rb.x + 35, ly, 6, 0, Math.PI * 2);
        ctx.arc(rb.x + 55, ly, 6, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
      }
    });

    const rimGrad = ctx.createRadialGradient(w / 2, 780, 180, w / 2, 780, 560);
    rimGrad.addColorStop(0, 'rgba(0, 212, 255, 0.25)');
    rimGrad.addColorStop(1, 'rgba(0, 212, 255, 0)');
    ctx.fillStyle = rimGrad;
    ctx.fillRect(0, 300, w, 1100);

    // Movimento do Echo: surge de baixo com recuo de antecipação aos 0.3s
    let echoY = 360;
    if (t < 0.6) {
      const u = t / 0.6;
      echoY = lerp(450, 360, easeOutBack(u, 1.2));
    }

    director.setSize(1220);
    director.setPose('K2');
    const b = director.bot;
    b.look.x = 0.0;
    b.look.y = 0.0;
    b.s.yaw = 0.0;
    b.s.pitch = 0.0;
    b.s.tilt = 0.0;
    b.s.hands = 0;
    b.s.mouthOpen = 0.0;
    b.eyeOv = 'pill';
    director.render();
    bocaFalando(director, echoCanvas, mouthVal);

    desenharEcho(ctx, echoCanvas, -70, echoY, 1220, 1220, { strokeWidth: 7 });

    // Mão esquerda ao lado do corpo
    maoEcho(ctx, 190, 1330 + (echoY - 360), 1220);

    // MÃO DIREITA ESFÉRICA APROXIMANDO-SE DA CÂMERA
    let handProgress = clamp((t - 0.2) / 0.8, 0.0, 1.0);
    handProgress = easeOutBack(handProgress, 1.4);
    const hx = lerp(710, 790, handProgress);
    const hy = lerp(1410, 1290, handProgress);
    const hScale = lerp(1.2, 2.8, handProgress);

    // Linhas de impacto surgem quando a mão atinge a proximidade máxima
    if (handProgress > 0.65) {
      const lineAlpha = clamp((handProgress - 0.65) / 0.35, 0.0, 1.0);
      const hr = 56 * hScale;
      ctx.save();
      ctx.strokeStyle = `rgba(127, 227, 245, ${lineAlpha})`;
      ctx.lineCap = 'round';
      ctx.lineWidth = 12;
      [[-2.5, 1.0], [-1.9, 1.0], [-3.0, 1.0]].forEach(([ang]) => {
        ctx.beginPath();
        ctx.moveTo(hx + Math.cos(ang) * (hr + 30), hy + Math.sin(ang) * (hr + 30));
        ctx.lineTo(hx + Math.cos(ang) * (hr + 85), hy + Math.sin(ang) * (hr + 85));
        ctx.stroke();
      });
      ctx.restore();
    }

    maoEcho(ctx, hx, hy, 1220, hScale);
    ctx.restore(); // Fecha o context da câmera

    // Legenda com destaque progressivo
    const l1 = [
      { text: "EI,", t: 0.30, highlightColor: "#ffffff" },
      { text: "VOCÊ", t: 0.65, highlightColor: "#00d4ff" },
      { text: "AÍ", t: 1.05, highlightColor: "#00d4ff" }
    ];
    const l2 = [
      { text: "DO", t: 1.40, highlightColor: "#ffffff" },
      { text: "TI!", t: 1.65, highlightColor: "#00d4ff" }
    ];
    desenharLegendaAnimada(ctx, [l1, l2], t);
  }

  /**
   * C02: 2.3s – 3.3s ("Que formata,")
   * Barra de progresso avança e acelera no final até 100% com confirmação verde e reação do Echo; digitação no teclado.
   */
  function animCena02(ctx, t, director, echoCanvas, mouthVal) {
    const w = 1080, h = 1920;
    const tRel = clamp((t - 2.3) / 1.0, 0.0, 1.0);

    ctx.fillStyle = '#1a333d';
    ctx.fillRect(0, 0, w, h);

    drawRoundedRect(ctx, -20, -20, w + 40, 1140, 0, '#132830', PALETA.ink, 6);
    ctx.fillStyle = '#0b1920';
    for (let py = 100; py < 1080; py += 55) {
      for (let px = 40; px < w; px += 55) {
        ctx.beginPath();
        ctx.arc(px, py, 3.5, 0, Math.PI * 2);
        ctx.fill();
      }
    }

    drawRoundedRect(ctx, 60, 420, 24, 140, 6, '#38bdf8', PALETA.ink, 5);
    drawRoundedRect(ctx, 95, 420, 24, 140, 6, '#38bdf8', PALETA.ink, 5);
    drawRoundedRect(ctx, 60, 380, 60, 50, 10, '#64748b', PALETA.ink, 5);

    drawRoundedRect(ctx, 40, 220, w - 80, 28, 4, '#334155', PALETA.ink, 6);
    drawRoundedRect(ctx, 80, 130, 160, 90, 8, '#1e293b', PALETA.ink, 5);
    ctx.fillStyle = PALETA.ledGreen;
    ctx.fillRect(100, 190, 8, 8);
    ctx.fillRect(120, 190, 8, 8);
    ctx.fillRect(140, 190, 8, 8);

    drawRoundedRect(ctx, -20, 1100, w + 40, h - 1100 + 40, 0, '#1f2e38', PALETA.ink, 8);
    drawRoundedRect(ctx, 40, 1130, w - 80, 720, 16, '#27485a', PALETA.ink, 6);
    ctx.strokeStyle = 'rgba(15, 33, 44, 0.7)';
    ctx.lineWidth = 3;
    for (let gx = 80; gx < w - 40; gx += 70) { ctx.beginPath(); ctx.moveTo(gx, 1134); ctx.lineTo(gx, 1846); ctx.stroke(); }
    for (let gy = 1180; gy < 1846; gy += 70) { ctx.beginPath(); ctx.moveTo(44, gy); ctx.lineTo(w - 44, gy); ctx.stroke(); }
    ctx.fillStyle = 'rgba(255, 255, 255, 0.08)';
    ctx.fillRect(44, 1134, w - 88, 26);

    drawRoundedRect(ctx, 700, 1420, 220, 130, 12, '#94a3b8', PALETA.ink, 6);
    drawRoundedRect(ctx, 724, 1442, 172, 86, 8, '#1e293b', PALETA.ink, 4);
    ctx.fillStyle = '#22c55e'; ctx.fillRect(744, 1470, 60, 14);
    drawThickCable(ctx, [180, 1640, 520, 1560], '#f59e0b', '#b45309', 22, 6);
    drawThickCable(ctx, [520, 1560, 640, 1530], '#cbd5e1', '#64748b', 10, 4);
    ctx.fillStyle = '#cbd5e1'; ctx.strokeStyle = PALETA.ink; ctx.lineWidth = 3;
    [[300, 1720], [340, 1745], [390, 1712]].forEach(([px, py]) => { ctx.beginPath(); ctx.arc(px, py, 10, 0, Math.PI * 2); ctx.fill(); ctx.stroke(); });

    drawRoundedRect(ctx, 820, 1640, 90, 110, 14, '#f8fafc', PALETA.ink, 6);
    ctx.fillStyle = '#0284c7'; ctx.font = 'bold 18px monospace'; ctx.fillText('<TI/>', 834, 1702);

    // Monitor CRT
    const monX = 380, monY = 380, monW = 660, monH = 680;
    ctx.fillStyle = 'rgba(0, 0, 0, 0.45)';
    ctx.beginPath();
    ctx.roundRect(monX + 15, monY + 20, monW, monH, 26);
    ctx.fill();

    drawRoundedRect(ctx, monX, monY, monW, monH, 24, '#1e293b', PALETA.ink, 8);
    ctx.fillStyle = PALETA.ink;
    for (let vx = monX + 80; vx < monX + monW - 80; vx += 30) {
      ctx.fillRect(vx, monY + 30, 18, 8);
    }

    const scrX = monX + 36, scrY = monY + 60, scrW = monW - 72, scrH = monH - 110;
    drawRoundedRect(ctx, scrX, scrY, scrW, scrH, 14, '#031720', PALETA.ink, 6);

    const winX = scrX + 24, winY = scrY + 30, winW = scrW - 48, winH = scrH - 60;
    drawRoundedRect(ctx, winX, winY, winW, winH, 10, '#0a141d', '#0284c7', 4);

    ctx.fillStyle = '#0284c7';
    ctx.fillRect(winX, winY, winW, 40);
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 20px monospace';
    ctx.fillText('FORMAT_TI_V3.EXE - NVME 2TB', winX + 18, winY + 26);

    ctx.fillStyle = '#38bdf8';
    ctx.font = 'bold 22px monospace';
    ctx.fillText('DISPOSITIVO: C:\\ [SSD NVME 2TB]', winX + 24, winY + 95);
    ctx.fillStyle = '#94a3b8';
    ctx.font = '18px monospace';
    ctx.fillText('SISTEMA: NTFS · CLUSTER 4096 BYTES', winX + 24, winY + 135);

    // Barra de progresso animada: avança e acelera no final
    // De tRel 0.0 a 0.7 avança de 30% a 75%; de 0.7 a 0.85 acelera de 75% a 100%!
    let progressVal = 0.35;
    if (tRel < 0.70) {
      progressVal = lerp(0.35, 0.75, tRel / 0.70);
    } else if (tRel < 0.85) {
      progressVal = lerp(0.75, 1.0, easeInCubic((tRel - 0.70) / 0.15));
    } else {
      progressVal = 1.0;
    }

    const isComplete = progressVal >= 0.999;
    const barX = winX + 24, barY = winY + 180, barW = winW - 48, barH = 50;
    const barFillW = (barW - 6) * progressVal;

    drawRoundedRect(ctx, barX, barY, barW, barH, 8, '#064e3b', '#10b981', 3);
    drawRoundedRect(ctx, barX + 3, barY + 3, barFillW, barH - 6, 6, isComplete ? '#22c55e' : '#38bdf8', null, 0);

    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 24px "Orbitron", sans-serif';
    ctx.textAlign = 'center';
    const pctTxt = isComplete ? '100% CONCLUÍDO' : `FORMATANDO... ${Math.round(progressVal * 100)}%`;
    ctx.fillText(pctTxt, winX + winW / 2, barY + barH / 2 + 1);
    ctx.textAlign = 'left';

    ctx.fillStyle = isComplete ? '#4ade80' : '#f59e0b';
    ctx.font = 'bold 20px monospace';
    const statusTxt = isComplete ? 'STATUS: SISTEMA PRONTO P/ USO' : 'STATUS: FORMATANDO SETORES...';
    ctx.fillText(statusTxt, winX + 24, winY + 285);

    // Teclado mecânico
    const kbX = 260, kbY = 1170, kbW = 600, kbH = 150;
    drawRoundedRect(ctx, kbX, kbY, kbW, kbH, 16, '#0f172a', PALETA.ink, 7);
    for (let r = 0; r < 4; r++) {
      for (let c = 0; c < 11; c++) {
        const kx = kbX + 25 + c * 50;
        const ky = kbY + 18 + r * 30;
        const kcol = (r === 1 && c === 3) ? '#00d4ff' : ((r === 2 && c === 10) ? '#22c55e' : '#334155');
        drawRoundedRect(ctx, kx, ky, 42, 22, 4, kcol, PALETA.ink, 2);
      }
    }

    // ECHO: Cabeça reage ao término (nod de aprovação aos 0.85s)
    director.setSize(740);
    director.setPose('K1');
    const b = director.bot;
    b.look.x = 0.55;
    b.look.y = isComplete ? -0.1 : 0.1;
    b.s.yaw = 0.28;
    b.s.tilt = isComplete ? 0.02 : -0.05;
    b.s.hands = 0;
    b.eyeOv = isComplete ? 'happy' : 'pill';
    director.render();

    desenharEcho(ctx, echoCanvas, -60, 680, 680, 680, { strokeWidth: 7 });

    // Mãos digitando no teclado mecânico (parando quando atinge 100%)
    ctx.save();
    const isTyping = !isComplete;
    const typeOffsetL = isTyping ? Math.sin(t * 30) * 5 : 0;
    const typeOffsetR = isTyping ? Math.cos(t * 30) * 5 : 0;
    maoEcho(ctx, 347, 1217 + typeOffsetL, 680);
    maoEcho(ctx, 567, 1197 + typeOffsetR, 680);
    ctx.restore();

    desenharLegendaAnimada(ctx, [
      [{ text: "FORMATA", t: 2.38, highlightColor: "#00d4ff" }]
    ], t);
  }

  /**
   * C03: 3.3s – 4.2s ("configura,")
   * Conector RJ45 se aproxima, sofre resistência no encaixe, entra com click;
   * LED de link acende imediatamente em verde como consequência direta; Echo percebe.
   */
  function animCena03(ctx, t, director, echoCanvas, mouthVal) {
    const w = 1080, h = 1920;
    const tRel = clamp((t - 3.3) / 0.9, 0.0, 1.0);

    ctx.fillStyle = '#111e29';
    ctx.fillRect(0, 0, w, h);

    drawRoundedRect(ctx, 30, 0, 90, h, 0, '#1e2d3b', PALETA.ink, 6);
    drawRoundedRect(ctx, w - 120, 0, 90, h, 0, '#1e2d3b', PALETA.ink, 6);

    ctx.fillStyle = PALETA.rackRail;
    for (let uy = 100; uy < h - 100; uy += 80) {
      ctx.fillRect(55, uy, 40, 16);
      ctx.fillRect(w - 95, uy, 40, 16);
      ctx.fillStyle = '#94a3b8';
      ctx.font = 'bold 16px monospace';
      ctx.fillText(`${Math.round(uy / 80)}U`, 58, uy - 6);
      ctx.fillStyle = PALETA.rackRail;
    }

    const ppX = 140, ppY = 360, ppW = 800, ppH = 150;
    drawRoundedRect(ctx, ppX, ppY, ppW, ppH, 12, '#182433', PALETA.ink, 7);
    for (let px = ppX + 40; px < ppX + ppW - 40; px += 34) {
      drawRoundedRect(ctx, px, ppY + 45, 24, 30, 4, '#0b131d', PALETA.ink, 3);
      ctx.strokeStyle = (px % 68 === 0) ? PALETA.cableBlue : PALETA.cableYellow;
      ctx.lineWidth = 8;
      ctx.beginPath();
      ctx.moveTo(px + 12, ppY + 75);
      ctx.quadraticCurveTo(px + 12, ppY + 160, px + 50, ppY + 240);
      ctx.stroke();
    }

    const swX = 140, swY = 620, swW = 800, swH = 260;
    drawRoundedRect(ctx, swX, swY, swW, swH, 14, '#1e293b', PALETA.ink, 8);

    ctx.fillStyle = '#38bdf8';
    ctx.font = 'bold 22px monospace';
    ctx.fillText('SWITCH GIGA 48P POE+ // CORE-01', swX + 30, swY + 40);

    // O encaixe do RJ45 acontece na porta 11 aos 0.55s (t = 3.8s)
    const isPlugged = tRel >= 0.55;

    for (let row = 0; row < 2; row++) {
      for (let p = 0; p < 18; p++) {
        const portX = swX + 40 + p * 40;
        const portY = swY + 80 + row * 70;
        drawRoundedRect(ctx, portX, portY, 30, 36, 4, '#09111a', PALETA.ink, 3);

        const isTarget = (row === 0 && p === 11);
        ctx.save();
        let ledCol = (p % 3 === 0) ? PALETA.ledGreen : '#15803d';
        if (isTarget) {
          ledCol = isPlugged ? PALETA.ledGreen : '#0f3818';
        }
        ctx.fillStyle = ledCol;
        if (isTarget && isPlugged) {
          ctx.shadowColor = PALETA.ledGreen;
          ctx.shadowBlur = 28;
        }
        ctx.beginPath();
        ctx.arc(portX + 15, portY - 10, (isTarget && isPlugged) ? 7 : 4, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
      }
    }

    drawThickCable(ctx, [swX - 10, 360, swX - 40, 560, swX - 30, 960], PALETA.cableBlue, null, 26, 6);
    drawThickCable(ctx, [swX + swW + 10, 360, swX + swW + 40, 560, swX + swW + 30, 960], PALETA.cableYellow, null, 24, 6);

    // ECHO
    director.setSize(800);
    director.setPose('K1');
    const b = director.bot;
    b.look.x = 0.1;
    b.look.y = -0.4;
    b.s.yaw = -0.15;
    b.s.tilt = isPlugged ? 0.03 : 0.08;
    b.s.hands = 0;
    b.eyeOv = isPlugged ? 'happy' : 'pill';
    director.render();

    desenharEcho(ctx, echoCanvas, 190, 940, 700, 700, { strokeWidth: 7 });
    maoEcho(ctx, 250, 1440, 700);

    // Movimento do conector RJ45:
    // Alvo final: (plugX, plugY)
    const plugX = swX + 40 + 11 * 40 + 15;
    const finalPlugY = swY + 98;
    let curPlugY = finalPlugY;

    if (tRel < 0.40) {
      // Aproximação
      curPlugY = lerp(finalPlugY + 70, finalPlugY + 12, easeOutQuad(tRel / 0.40));
    } else if (tRel < 0.55) {
      // Resistência mecânica na entrada da porta
      const u = (tRel - 0.40) / 0.15;
      curPlugY = lerp(finalPlugY + 12, finalPlugY + 6, u);
    } else {
      // Click firme no encaixe
      curPlugY = finalPlugY;
    }

    ctx.save();
    drawThickCable(ctx, [plugX, curPlugY + 30, plugX + 40, curPlugY + 230, plugX + 260, curPlugY + 200, 1100, curPlugY + 420], '#0284c7', '#0369a1', 18, 5);
    maoEcho(ctx, plugX + 10, curPlugY + 60, 700, 1.3);

    // Conector transparente
    drawRoundedRect(ctx, plugX - 14, curPlugY - 8, 28, 42, 4, 'rgba(56, 189, 248, 0.75)', PALETA.ink, 4);
    ctx.fillStyle = '#eab308';
    ctx.fillRect(plugX - 10, curPlugY - 6, 20, 6);

    // Clarão de confirmação de link
    if (isPlugged) {
      ctx.save();
      ctx.shadowColor = '#22c55e';
      ctx.shadowBlur = 32;
      ctx.fillStyle = '#4ade80';
      ctx.beginPath();
      ctx.arc(plugX, finalPlugY - 12, 10, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();
    }
    ctx.restore();

    desenharLegendaAnimada(ctx, [
      [{ text: "CONFIGURA", t: 3.35, highlightColor: "#00d4ff" }]
    ], t);
  }

  /**
   * C04: 4.2s – 4.9s ("faz backup")
   * Cascata dinâmica de pastas em arco parabólico entrando no HD de 4TB; HD e Echo reagem ao impacto.
   */
  function animCena04(ctx, t, director, echoCanvas, mouthVal) {
    const w = 1080, h = 1920;
    const tRel = clamp((t - 4.2) / 0.7, 0.0, 1.0);

    ctx.fillStyle = '#102530';
    ctx.fillRect(0, 0, w, h);

    ctx.fillStyle = '#0b1c24';
    ctx.fillRect(0, 0, w, 1180);
    for (let lx = 100; lx < w; lx += 220) {
      drawRoundedRect(ctx, lx, 100, 12, 1040, 6, '#0891b2', null, 0);
      drawRoundedRect(ctx, lx + 26, 260, 170, 840, 14, '#162e3b', PALETA.ink, 5);
      for (let ty = 300; ty < 1050; ty += 80) {
        drawRoundedRect(ctx, lx + 40, ty, 142, 55, 6, '#0f2029', PALETA.ink, 3);
        ctx.fillStyle = PALETA.ledCyan;
        ctx.fillRect(lx + 55, ty + 24, 10, 6);
        ctx.fillRect(lx + 75, ty + 24, 10, 6);
      }
    }

    ctx.fillStyle = '#08171f';
    ctx.beginPath();
    ctx.moveTo(0, 1180);
    ctx.lineTo(w, 1180);
    ctx.lineTo(w, h);
    ctx.lineTo(0, h);
    ctx.fill();

    ctx.strokeStyle = '#153340';
    ctx.lineWidth = 4;
    for (let fx = -200; fx < w + 400; fx += 180) {
      ctx.beginPath();
      ctx.moveTo(fx, 1180);
      ctx.lineTo(fx - 200, h);
      ctx.stroke();
    }

    // HD Externo reage com micro-vibração durante a recepção
    const hdVibe = Math.sin(t * 60) * 1.5;
    const hdX = 360, hdY = 1120 + hdVibe, hdW = 340, hdH = 220;

    director.setSize(760);
    director.setPose('K3');
    const b = director.bot;
    b.look.x = -0.3;
    b.look.y = -0.1;
    b.s.yaw = -0.15;
    b.s.tilt = 0.05;
    b.eyeOv = 'pill';
    b.s.hands = 0;
    director.render();
    bocaFalando(director, echoCanvas, mouthVal);

    desenharEcho(ctx, echoCanvas, 280, 680, 760, 760, { strokeWidth: 7 });

    // Desenhar HD
    ctx.fillStyle = 'rgba(0, 0, 0, 0.45)';
    ctx.beginPath();
    ctx.roundRect(hdX + 10, hdY + 15, hdW, hdH, 24);
    ctx.fill();

    drawRoundedRect(ctx, hdX, hdY, hdW, hdH, 22, '#ea580c', PALETA.ink, 8);
    drawRoundedRect(ctx, hdX + 30, hdY + 24, hdW - 60, hdH - 48, 14, '#1e293b', PALETA.ink, 6);

    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 26px "Orbitron", sans-serif';
    ctx.fillText('BACKUP 4TB', hdX + 50, hdY + 80);
    ctx.fillStyle = '#38bdf8';
    ctx.font = 'bold 18px monospace';
    ctx.fillText('ENCRYPTED AES-256', hdX + 50, hdY + 120);

    // LED de atividade piscando rápido
    const ledFlash = Math.sin(t * 40) > 0;
    ctx.save();
    ctx.shadowColor = '#00d4ff';
    ctx.shadowBlur = ledFlash ? 20 : 6;
    ctx.fillStyle = ledFlash ? '#00d4ff' : '#0284c7';
    ctx.beginPath();
    ctx.arc(hdX + hdW - 65, hdY + 80, 8, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();

    maoEcho(ctx, hdX + 12, hdY + 117, 760);
    maoEcho(ctx, hdX + hdW - 12, hdY + 117, 760);

    // Trajetória parabólica dinâmica
    ctx.save();
    ctx.strokeStyle = 'rgba(0, 212, 255, 0.50)';
    ctx.lineWidth = 8;
    ctx.setLineDash([16, 12]);
    ctx.beginPath();
    ctx.moveTo(140, 360);
    ctx.quadraticCurveTo(120, 780, hdX + 80, hdY + 30);
    ctx.stroke();
    ctx.restore();

    // Cascata de 5 pastas em movimento
    const pastasList = [
      { baseU: 0.10, cor: '#f59e0b', txt: 'FINANCEIRO', rot: -0.25 },
      { baseU: 0.30, cor: '#38bdf8', txt: 'CLIENTES', rot: -0.15 },
      { baseU: 0.50, cor: '#f59e0b', txt: 'DATABASE.SQL', rot: 0.12 },
      { baseU: 0.70, cor: '#22c55e', txt: 'PROJETOS', rot: -0.08 },
      { baseU: 0.90, cor: '#f59e0b', txt: 'BACKUP_FULL', rot: 0.20 }
    ];

    pastasList.forEach((p, idx) => {
      // u progride ao longo do tempo da cena
      let u = (p.baseU + tRel * 0.7) % 1.1;
      if (u > 1.0) return; // já entrou no HD

      // Posição na curva de Bezier quadrática
      const p0 = { x: 140, y: 360 };
      const p1 = { x: 120, y: 780 };
      const p2 = { x: hdX + 80, y: hdY + 30 };

      const curX = (1 - u) * (1 - u) * p0.x + 2 * (1 - u) * u * p1.x + u * u * p2.x;
      const curY = (1 - u) * (1 - u) * p0.y + 2 * (1 - u) * u * p1.y + u * u * p2.y;
      const curScale = lerp(1.1, 0.45, u);

      ctx.save();
      ctx.translate(curX, curY);
      ctx.rotate(p.rot + u * 0.3);
      ctx.scale(curScale, curScale);

      drawRoundedRect(ctx, -60, -45, 120, 90, 12, p.cor, PALETA.ink, 5);
      drawRoundedRect(ctx, -60, -60, 50, 20, 6, p.cor, PALETA.ink, 4);
      drawRoundedRect(ctx, -45, -35, 90, 70, 6, '#ffffff', PALETA.ink, 4);

      ctx.fillStyle = '#64748b';
      ctx.fillRect(-35, -20, 70, 6);
      ctx.fillRect(-35, -5, 55, 6);
      ctx.fillRect(-35, 10, 65, 6);
      ctx.restore();
    });

    desenharLegendaAnimada(ctx, [
      [
        { text: "FAZ", t: 4.25, highlightColor: "#ffffff" },
        { text: "BACKUP", t: 4.45, highlightColor: "#00d4ff" }
      ]
    ], t);
  }

  /**
   * C05: 4.9s – 7.4s ("e resolve quase tudo com…")
   * Câmera aproxima devagar, movimento secundário reduzido, foco na expressão "hmm", pausa no "com…".
   */
  function animCena05(ctx, t, director, echoCanvas, mouthVal) {
    const w = 1080, h = 1920;
    const tRel = clamp((t - 4.9) / 2.5, 0.0, 1.0);

    // Push-in lento e cinematográfico de câmera
    const camPush = lerp(1.0, 1.045, easeOutQuad(tRel));
    ctx.save();
    ctx.translate(540, 960);
    ctx.scale(camPush, camPush);
    ctx.translate(-540, -960);

    ctx.fillStyle = PALETA.wallBase;
    ctx.fillRect(0, 0, w, 1160);
    ctx.fillStyle = PALETA.wallDark;
    ctx.fillRect(0, 0, w, 200);

    for (let px = 60; px < w; px += 240) {
      drawRoundedRect(ctx, px, 240, 190, 320, 14, PALETA.wallLight, PALETA.wallDark, 4);
    }
    drawThickCable(ctx, [0, 220, 400, 220, 800, 220, w, 220], '#275252', null, 20, 6);

    ctx.fillStyle = PALETA.floorBase;
    ctx.fillRect(0, 1160, w, h - 1160);
    ctx.strokeStyle = PALETA.floorGrid;
    ctx.lineWidth = 4;
    for (let lx = -400; lx < w + 600; lx += 140) {
      ctx.beginPath();
      ctx.moveTo(lx, 1160);
      ctx.lineTo(lx - 220, h);
      ctx.stroke();
    }
    for (let ly = 1160; ly < h; ly += 95) {
      ctx.beginPath();
      ctx.moveTo(0, ly);
      ctx.lineTo(w, ly);
      ctx.stroke();
    }

    const rkX = 450, rkY = 460, rkW = 540, rkH = 920;
    ctx.fillStyle = 'rgba(7, 12, 18, 0.45)';
    ctx.beginPath();
    ctx.roundRect(rkX - 25, rkY + rkH - 20, rkW + 70, 130, 22);
    ctx.fill();

    drawRoundedRect(ctx, rkX, rkY, rkW, rkH, 20, PALETA.rackFrame, PALETA.ink, 8);
    drawRoundedRect(ctx, rkX + 24, rkY + 24, rkW - 48, rkH - 48, 12, PALETA.rackFace, PALETA.ink, 5);

    for (let gy = rkY + 60; gy < rkY + rkH - 80; gy += 68) {
      drawRoundedRect(ctx, rkX + 40, gy, rkW - 80, 52, 6, '#1e293b', PALETA.ink, 3);
      for (let lx = rkX + 60; lx < rkX + 240; lx += 32) {
        ctx.fillStyle = (lx % 64 === 0) ? PALETA.ledGreen : PALETA.ledAmber;
        ctx.beginPath();
        ctx.arc(lx, gy + 26, 4, 0, Math.PI * 2);
        ctx.fill();
      }
    }

    // NINHO DE CABOS
    const nestX = rkX + rkW / 2;
    const nestY = rkY - 10;
    drawRoundedRect(ctx, nestX - 250, nestY - 70, 500, 180, 70, '#101d2e', PALETA.ink, 9);
    drawRoundedRect(ctx, nestX - 220, nestY - 110, 440, 160, 60, '#0c1624', PALETA.ink, 9);

    const loopsCables = [
      [nestX - 220, nestY + 20, nestX - 280, nestY - 120, nestX - 140, nestY - 160, nestX - 60, nestY - 10, PALETA.cableDark, 26],
      [nestX - 80, nestY - 20, nestX + 10, nestY - 180, nestX + 180, nestY - 170, nestX + 150, nestY + 20, PALETA.cableDark2, 28],
      [nestX + 80, nestY + 10, nestX + 220, nestY - 140, nestX + 270, nestY - 40, nestX + 170, nestY + 50, PALETA.cableDark, 24],
      [nestX - 180, nestY - 40, nestX - 120, nestY - 160, nestX + 30, nestY - 140, nestX + 90, nestY - 10, PALETA.cableBlueDark, 24],
      [nestX - 150, nestY - 10, nestX - 200, nestY - 100, nestX - 70, nestY - 120, nestX - 30, nestY + 30, PALETA.cableBlue, 22],
      [nestX - 50, nestY + 20, nestX - 90, nestY - 90, nestX + 40, nestY - 130, nestX + 70, nestY + 40, PALETA.cableYellow, 20],
      [nestX + 20, nestY + 10, nestX + 100, nestY - 110, nestX + 190, nestY - 80, nestX + 130, nestY + 60, PALETA.cableDark, 26],
      [nestX - 110, nestY + 40, nestX - 40, nestY - 70, nestX + 60, nestY - 60, nestX + 110, nestY + 50, PALETA.cableBlue, 22],
      [nestX - 190, nestY + 30, nestX - 140, nestY - 70, nestX - 20, nestY - 80, nestX + 40, nestY + 50, PALETA.cableDark2, 24],
      [nestX - 210, nestY + 40, nestX - 320, nestY + 220, nestX - 190, nestY + 460, nestX - 280, nestY + 680, PALETA.cableDark, 26],
      [nestX - 170, nestY + 60, nestX - 270, nestY + 260, nestX - 150, nestY + 520, nestX - 230, nestY + 840, PALETA.cableBlue, 24],
      [nestX - 130, nestY + 80, nestX - 230, nestY + 320, nestX - 120, nestY + 600, nestX - 200, nestY + 920, PALETA.cableDark2, 24],
      [nestX - 240, nestY + 180, nestX - 340, nestY + 380, nestX - 220, nestY + 620, nestX - 300, nestY + 880, PALETA.cableYellow, 20],
      [nestX + 210, nestY + 40, nestX + 310, nestY + 240, nestX + 170, nestY + 500, nestX + 260, nestY + 760, PALETA.cableDark, 26],
      [nestX + 160, nestY + 60, nestX + 250, nestY + 300, nestX + 140, nestY + 580, nestX + 210, nestY + 860, PALETA.cableBlue, 24],
      [nestX + 120, nestY + 80, nestX + 210, nestY + 360, nestX + 110, nestY + 640, nestX + 170, nestY + 920, PALETA.cableDark2, 22],
      [nestX - 60, nestY + 70, nestX - 90, nestY + 240, nestX - 30, nestY + 480, nestX - 80, nestY + 780, PALETA.cableDark, 22],
      [nestX + 40, nestY + 80, nestX + 70, nestY + 280, nestX + 20, nestY + 520, nestX + 60, nestY + 820, PALETA.cableBlueDark, 22],
      [nestX - 280, nestY + 680, nestX - 360, 1260, nestX - 160, 1380, nestX + 20, 1440, PALETA.cableDark, 28],
      [nestX - 230, nestY + 840, nestX - 300, 1340, nestX - 420, 1460, nestX - 220, 1580, PALETA.cableBlue, 26],
      [nestX - 200, nestY + 920, nestX - 120, 1420, nestX - 260, 1540, nestX - 80, 1660, PALETA.cableDark2, 24],
      [nestX - 300, nestY + 880, nestX - 380, 1380, nestX - 200, 1520, nestX - 20, 1620, PALETA.cableYellow, 22],
      [nestX + 260, nestY + 760, nestX + 340, 1300, nestX + 160, 1440, nestX + 90, 1580, PALETA.cableDark, 26],
      [nestX + 210, nestY + 860, nestX + 290, 1380, nestX + 380, 1520, nestX + 240, 1640, PALETA.cableBlue, 24],
      [nestX + 170, nestY + 920, nestX + 80, 1460, nestX + 220, 1600, nestX + 140, 1720, PALETA.cableDark2, 24]
    ];

    loopsCables.forEach(lp => {
      drawThickCable(ctx, [lp[0], lp[1], lp[2], lp[3], lp[4], lp[5], lp[6], lp[7]], lp[8], null, lp[9], 6);
    });

    drawPlugue2Pinos(ctx, nestX - 200, 1540, 0.45, 1.25);
    drawPlugue2Pinos(ctx, nestX + 80, 1580, -0.65, 1.15);
    drawPlugue2Pinos(ctx, nestX + 240, 1640, 1.15, 1.35);
    drawPlugue2Pinos(ctx, nestX - 50, 1680, 2.10, 1.20);

    drawRoundedRect(ctx, nestX - 150, 1440, 180, 65, 14, '#334155', PALETA.ink, 6);
    for (let tx = nestX - 120; tx < nestX + 15; tx += 42) {
      ctx.fillStyle = PALETA.ink;
      ctx.beginPath();
      ctx.arc(tx, 1460, 4, 0, Math.PI * 2);
      ctx.arc(tx, 1482, 4, 0, Math.PI * 2);
      ctx.fill();
    }

    // ECHO: Expressão "hmm" com aceno de coçar a cabeça
    director.setSize(940);
    director.setPose('K4');
    const b = director.bot;
    b.look.x = 0.5;
    b.look.y = -0.3;
    b.s.yaw = 0.15;
    b.s.tilt = 0.12;
    b.col = director.colAmber;
    b.colT = director.colAmber;
    b.eyeOv = 'hmm';
    // Coçando em ritmo sutil até a pausa narrativa em "com..."
    const scratch = (t < 7.0) ? Math.sin(t * 6) * 0.08 : 0;
    b.s.tilt = 0.12 + scratch;
    b.waveSide = -1;
    director.render();
    bocaFalando(director, echoCanvas, mouthVal);

    desenharEcho(ctx, echoCanvas, 20, 780, 940, 940, { strokeWidth: 7 });
    b.waveSide = 0;

    ctx.restore(); // Fecha o context do push-in da câmera

    desenharLegendaAnimada(ctx, [
      [
        { text: "E", t: 5.50, highlightColor: "#ffffff" },
        { text: "RESOLVE", t: 5.70, highlightColor: "#ffffff" },
        { text: "QUASE", t: 6.10, highlightColor: "#00d4ff" }
      ],
      [
        { text: "TUDO", t: 6.45, highlightColor: "#ffffff" },
        { text: "COM…", t: 6.80, highlightColor: "#00d4ff" }
      ]
    ], t);
  }

  /**
   * C06: 7.4s – 8.6s ("Já tentou reiniciar?")
   * Plugue puxado deliberadamente; apagão acontece COMO CONSEQUÊNCIA imediata;
   * luzes diminuem, mas Echo permanece claramente visível (sem transparência errada).
   */
  function animCena06(ctx, t, director, echoCanvas, mouthVal) {
    const w = 1080, h = 1920;
    const tRel = clamp((t - 7.4) / 1.2, 0.0, 1.0);

    // O plugue é puxado aos 0.55s da cena (t = 8.06s). A partir daí, corte de luz imediato!
    const isPulled = tRel >= 0.55;

    // Fundo escurece mais após puxar o plugue
    ctx.fillStyle = isPulled ? '#040b12' : '#0a1722';
    ctx.fillRect(0, 0, w, h);

    ctx.save();
    ctx.translate(w / 2, 900);
    ctx.rotate(-0.1);
    ctx.translate(-w / 2, -900);

    // Racks
    drawRoundedRect(ctx, -120, 180, 380, 1400, 18, isPulled ? '#0a1520' : '#112230', PALETA.ink, 6);
    drawRoundedRect(ctx, 820, 180, 380, 1400, 18, isPulled ? '#0a1520' : '#112230', PALETA.ink, 6);

    for (let ly = 240; ly < 1500; ly += 70) {
      drawRoundedRect(ctx, -90, ly, 320, 46, 6, '#0c1a25', PALETA.ink, 3);
      drawRoundedRect(ctx, 850, ly, 320, 46, 6, '#0c1a25', PALETA.ink, 3);

      // LEDs: acesos em âmbar antes do puxão, caem para quase apagados/standby escuro após o puxão
      ctx.fillStyle = isPulled ? '#451a03' : PALETA.ledAmber;
      ctx.beginPath();
      ctx.arc(200, ly + 23, 6, 0, Math.PI * 2);
      ctx.arc(880, ly + 23, 6, 0, Math.PI * 2);
      ctx.fill();
    }

    ctx.fillStyle = '#081219';
    ctx.fillRect(-200, 1500, w + 400, 800);

    // Luz de recorte diminui após o apagão
    const spotAlpha = isPulled ? 0.08 : 0.30;
    const spotGrad = ctx.createRadialGradient(660, 820, 80, 660, 820, 640);
    spotGrad.addColorStop(0, `rgba(0, 212, 255, ${spotAlpha})`);
    spotGrad.addColorStop(1, 'rgba(0, 212, 255, 0)');
    ctx.fillStyle = spotGrad;
    ctx.fillRect(-200, 100, w + 400, 1500);

    // ECHO: Olhar cúmplice de "eu sei"
    director.setSize(1100);
    director.setPose('K5');
    const b = director.bot;
    b.look.x = -0.55;
    b.look.y = 0.1;
    b.s.yaw = -0.42;
    b.s.tilt = isPulled ? 0.18 : 0.12; // inclinação cúmplice quando puxa
    b.s.hands = 0;
    b.eyeOv = 'pill';
    director.render();
    bocaFalando(director, echoCanvas, mouthVal);

    desenharEcho(ctx, echoCanvas, 170, 380, 1100, 1100, { strokeWidth: 8 });
    maoEcho(ctx, 930, 1250, 1100);
    ctx.restore();

    // Ação física de puxar o plugue para baixo/trás:
    let pullProgress = 0.0;
    if (tRel >= 0.50) {
      pullProgress = easeOutBack(clamp((tRel - 0.50) / 0.18, 0.0, 1.0), 1.5);
    }
    const plugX = lerp(330, 310, pullProgress);
    const plugY = lerp(1250, 1370, pullProgress);
    const plugAngle = lerp(-0.55, -0.68, pullProgress);
    const plugScale = lerp(4.6, 5.0, pullProgress);

    drawThickCable(ctx, [plugX - 90, plugY + 20, plugX - 230, plugY + 260, plugX - 120, 1990], PALETA.cableDark, null, 46, 10);
    maoEcho(ctx, plugX - 120, plugY + 70, 1100, 1.8);
    drawPlugue2Pinos(ctx, plugX, plugY, plugAngle, plugScale);

    desenharLegendaAnimada(ctx, [
      [{ text: "JÁ", t: 7.60, highlightColor: "#ffffff" }, { text: "TENTOU", t: 7.80, highlightColor: "#ffffff" }],
      [{ text: "REINICIAR?", t: 8.15, highlightColor: "#00d4ff" }]
    ], t);
  }

  /**
   * C07: 8.6s – 9.1s (Apagão / Reinício)
   * Barra de reinício progride deterministicamente de 15% a 100%;
   * luzes e atividade voltam gradualmente com alívio do Echo.
   */
  function animCena07(ctx, t, director, echoCanvas, mouthVal) {
    const w = 1080, h = 1920;
    const tRel = clamp((t - 8.6) / 0.5, 0.0, 1.0);

    // Retorno gradual de luz a partir de 70% de progresso
    const returnAlpha = tRel > 0.65 ? (tRel - 0.65) / 0.35 : 0.0;
    ctx.fillStyle = returnAlpha > 0 ? `rgba(6, 16, 26, ${1.0})` : '#03080e';
    ctx.fillRect(0, 0, w, h);

    // Silhuetas dos racks voltam a ter contorno sutil
    drawRoundedRect(ctx, 40, 260, 300, 1140, 16, '#060f18', returnAlpha > 0 ? '#0c2236' : '#091522', 2);
    drawRoundedRect(ctx, 740, 260, 300, 1140, 16, '#060f18', returnAlpha > 0 ? '#0c2236' : '#091522', 2);

    // LEDs voltam a piscar no finalzinho da cena
    if (returnAlpha > 0.4) {
      for (let ly = 320; ly < 1200; ly += 90) {
        ctx.fillStyle = PALETA.ledGreen;
        ctx.beginPath();
        ctx.arc(80, ly, 5, 0, Math.PI * 2);
        ctx.arc(780, ly, 5, 0, Math.PI * 2);
        ctx.fill();
      }
    }

    director.setSize(760);
    director.setPose('K6');
    const b = director.bot;
    b.look.x = 0.0;
    b.look.y = returnAlpha > 0 ? -0.1 : 0.0;
    b.s.yaw = 0.0;
    b.s.tilt = 0.0;
    b.s.hands = 0.0;
    b.eyeOv = returnAlpha > 0.7 ? 'happy' : 'pill';
    director.render();

    // Echo em silhueta escura com olhos ciano acesos
    desenharEcho(ctx, echoCanvas, 160, 680, 760, 760, { apagado: true });

    // Barra de progresso animada de 15% a 100%
    const progressVal = lerp(0.15, 1.0, easeInOutQuad(tRel));
    const barW = 460, barH = 26;
    const barX = (w - barW) / 2;
    const barY = 1380;

    drawRoundedRect(ctx, barX, barY, barW, barH, 13, '#09131d', '#1e293b', 3);
    drawRoundedRect(ctx, barX + 3, barY + 3, (barW - 6) * progressVal, barH - 6, 10, '#00d4ff', null, 0);

    ctx.fillStyle = '#64748b';
    ctx.font = 'bold 20px monospace';
    ctx.textAlign = 'center';
    const pct = Math.min(100, Math.round(progressVal * 100));
    ctx.fillText(`reiniciando... ${pct}%`, w / 2, barY + barH + 35);
    ctx.textAlign = 'left';
  }

  /**
   * C08: 9.1s – 10.7s ("Hoje o dia é seu!")
   * Echo prepara, caneca sobe natural com brinde no alto, vapor d'água animado e término suave.
   */
  function animCena08(ctx, t, director, echoCanvas, mouthVal) {
    const w = 1080, h = 1920;
    const tRel = clamp((t - 9.1) / 1.6, 0.0, 1.0);

    ctx.fillStyle = '#1e2736';
    ctx.fillRect(0, 0, w, h);

    ctx.fillStyle = '#161e2b';
    ctx.fillRect(0, 0, w, 1100);
    ctx.strokeStyle = '#232f42';
    ctx.lineWidth = 3;
    for (let by = 80; by < 1100; by += 70) {
      ctx.beginPath();
      ctx.moveTo(0, by);
      ctx.lineTo(w, by);
      ctx.stroke();
    }

    const postIts = [
      { x: 80, y: 380, rot: -0.10, cor: '#fde047', txt: 'TICKET #404' },
      { x: 190, y: 390, rot: 0.15, cor: '#f472b6', txt: 'DNS OK!' },
      { x: 80, y: 500, rot: 0.08, cor: '#4ade80', txt: 'CAFÉ ☕' },
      { x: 190, y: 510, rot: -0.12, cor: '#38bdf8', txt: 'BACKUP 100%' }
    ];
    postIts.forEach(pi => {
      ctx.save();
      ctx.translate(pi.x + 45, pi.y + 45);
      ctx.rotate(pi.rot);
      drawRoundedRect(ctx, -45, -45, 90, 90, 6, pi.cor, PALETA.ink, 4);
      ctx.fillStyle = PALETA.ink;
      ctx.font = 'bold 13px sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(pi.txt, 0, 5);
      ctx.restore();
    });

    [[320, 360], [690, 360]].forEach(([mx, my], k) => {
      drawRoundedRect(ctx, mx + 140, my + 250, 60, 120, 6, '#334155', PALETA.ink, 5);
      drawRoundedRect(ctx, mx, my, 340, 260, 14, '#1e293b', PALETA.ink, 7);
      drawRoundedRect(ctx, mx + 18, my + 18, 304, 210, 8, '#0b2a3a', PALETA.ink, 4);
      ctx.fillStyle = k ? '#22c55e' : '#38bdf8';
      for (let ln = 0; ln < 6; ln++) ctx.fillRect(mx + 36, my + 44 + ln * 30, 120 + ((ln * 53 + k * 31) % 140), 10);
    });

    // Luminária
    ctx.save();
    ctx.lineCap = 'round';
    ctx.strokeStyle = PALETA.ink; ctx.lineWidth = 26;
    ctx.beginPath(); ctx.moveTo(960, 1110); ctx.lineTo(1000, 860); ctx.lineTo(900, 700); ctx.stroke();
    ctx.strokeStyle = '#64748b'; ctx.lineWidth = 14;
    ctx.beginPath(); ctx.moveTo(960, 1110); ctx.lineTo(1000, 860); ctx.lineTo(900, 700); ctx.stroke();
    ctx.restore();
    ctx.save();
    ctx.translate(890, 690); ctx.rotate(0.5);
    ctx.beginPath(); ctx.moveTo(-70, 40); ctx.lineTo(-30, -40); ctx.lineTo(30, -40); ctx.lineTo(70, 40); ctx.closePath();
    ctx.fillStyle = '#f59e0b'; ctx.fill(); ctx.lineWidth = 7; ctx.strokeStyle = PALETA.ink; ctx.stroke();
    ctx.fillStyle = '#fef3c7'; ctx.beginPath(); ctx.ellipse(0, 40, 62, 14, 0, 0, Math.PI * 2); ctx.fill();
    ctx.restore();
    drawRoundedRect(ctx, 900, 1090, 130, 30, 10, '#475569', PALETA.ink, 5);

    const lampGrad = ctx.createRadialGradient(880, 720, 50, 880, 720, 680);
    lampGrad.addColorStop(0, 'rgba(251, 191, 36, 0.40)');
    lampGrad.addColorStop(1, 'rgba(251, 191, 36, 0)');
    ctx.fillStyle = lampGrad;
    ctx.fillRect(0, 300, w, 1100);

    drawRoundedRect(ctx, -20, 1100, w + 40, h - 1100 + 40, 0, PALETA.deskWood, PALETA.ink, 8);
    ctx.fillStyle = '#d97706'; ctx.fillRect(0, 1104, w, 14);
    ctx.fillStyle = PALETA.deskWoodDark; ctx.fillRect(0, 1118, w, 16);
    ctx.strokeStyle = 'rgba(120, 53, 15, 0.55)'; ctx.lineWidth = 4;
    for (let vy = 1170; vy < h; vy += 90) {
      ctx.beginPath(); ctx.moveTo(0, vy); ctx.bezierCurveTo(300, vy - 25, 700, vy + 25, w, vy - 10); ctx.stroke();
    }

    drawRoundedRect(ctx, 160, 1180, 480, 140, 14, '#1e293b', PALETA.ink, 6);

    // ECHO
    director.setSize(745);
    director.setPose('K7');
    const b = director.bot;
    b.look.x = 0.2;
    b.look.y = -0.1;
    b.s.yaw = 0.12;
    b.s.tilt = -0.06;
    b.s.hands = 0;
    b.eyeOv = 'happy';
    director.render();
    bocaFalando(director, echoCanvas, mouthVal);

    desenharEcho(ctx, echoCanvas, 160, 720, 745, 745, { strokeWidth: 7 });
    maoEcho(ctx, 330, 1330, 745);

    // Movimento da caneca:
    // 0.0 - 0.2: antecipação (dip de -5px)
    // 0.2 - 0.65: sobe com brinde natural de Y=1040 até Y=920 com inclinação
    // 0.65 - 1.1: segura no alto
    // 1.1 - 1.6: acomoda suavemente em Y=980
    let mugY = 1040;
    let mugAngle = 0.0;
    if (tRel < 0.20) {
      mugY = lerp(1040, 1046, easeInQuad(tRel / 0.20));
    } else if (tRel < 0.65) {
      const u = easeOutBack((tRel - 0.20) / 0.45, 1.3);
      mugY = lerp(1046, 920, u);
      mugAngle = lerp(0.0, 0.12, u);
    } else if (tRel < 1.10) {
      mugY = 920;
      mugAngle = 0.12;
    } else {
      const u = easeInOutQuad((tRel - 1.10) / 0.50);
      mugY = lerp(920, 970, u);
      mugAngle = lerp(0.12, 0.04, u);
    }

    ctx.save();
    const mugX = 720;
    ctx.translate(mugX, mugY);
    ctx.rotate(mugAngle);

    ctx.fillStyle = 'rgba(0, 0, 0, 0.35)';
    ctx.beginPath();
    ctx.ellipse(0, 65, 55, 20, 0, 0, Math.PI * 2);
    ctx.fill();

    maoEcho(ctx, -52, 18, 745);

    drawRoundedRect(ctx, -45, -50, 90, 110, 16, '#f8fafc', PALETA.ink, 6);

    ctx.beginPath();
    ctx.arc(45, 5, 28, -Math.PI * 0.4, Math.PI * 0.4);
    ctx.strokeStyle = PALETA.ink; ctx.lineWidth = 14; ctx.stroke();
    ctx.beginPath();
    ctx.arc(45, 5, 28, -Math.PI * 0.4, Math.PI * 0.4);
    ctx.strokeStyle = '#f8fafc'; ctx.lineWidth = 8; ctx.stroke();

    ctx.fillStyle = '#451a03';
    ctx.beginPath();
    ctx.ellipse(0, -48, 38, 12, 0, 0, Math.PI * 2);
    ctx.fill();

    ctx.fillStyle = '#0284c7';
    ctx.font = 'bold 18px monospace';
    ctx.textAlign = 'center';
    ctx.fillText('<TI/>', 0, 15);
    ctx.textAlign = 'left';

    // Fumaça animada senoidalmente
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.45)';
    ctx.lineWidth = 4;
    const smokePhase = t * 4;
    ctx.beginPath();
    ctx.moveTo(-10, -60);
    ctx.quadraticCurveTo(-25 + Math.sin(smokePhase) * 6, -95, -10 + Math.sin(smokePhase + 1) * 8, -130);
    ctx.moveTo(15, -60);
    ctx.quadraticCurveTo(30 + Math.cos(smokePhase) * 6, -95, 15 + Math.cos(smokePhase + 1) * 8, -130);
    ctx.stroke();

    ctx.restore();

    desenharLegendaAnimada(ctx, [
      [
        { text: "HOJE", t: 9.14, highlightColor: "#ffffff" },
        { text: "O", t: 9.35, highlightColor: "#ffffff" },
        { text: "DIA", t: 9.50, highlightColor: "#ffffff" }
      ],
      [
        { text: "É", t: 9.85, highlightColor: "#00d4ff" },
        { text: "SEU!", t: 10.05, highlightColor: "#00d4ff" }
      ]
    ], t);
  }

  /**
   * C09: 10.7s – 13.1s ("Feliz dia do profissional de TI!")
   * Telão central digitado em JetBrains Mono com cursor, LEDs em onda ordenada, Echo comemora de mãos para cima.
   */
  function animCena09(ctx, t, director, echoCanvas, mouthVal) {
    const w = 1080, h = 1920;
    const tRel = clamp((t - 10.7) / 2.4, 0.0, 1.0);

    ctx.fillStyle = '#091522';
    ctx.fillRect(0, 0, w, h);

    drawRoundedRect(ctx, 30, 260, 200, 780, 16, '#0f202f', PALETA.ink, 6);
    drawRoundedRect(ctx, 850, 260, 200, 780, 16, '#0f202f', PALETA.ink, 6);

    // LEDs em varredura ordenada (onda coerente)
    for (let ly = 300; ly < 1000; ly += 60) {
      const ledCycle = (ly / 60 + t * 4) % 12;
      const isBright = ledCycle < 4;
      ctx.save();
      ctx.shadowColor = PALETA.ledGreen;
      ctx.shadowBlur = isBright ? 18 : 6;
      ctx.fillStyle = isBright ? '#4ade80' : '#15803d';
      ctx.beginPath();
      ctx.arc(60, ly, 7, 0, Math.PI * 2);
      ctx.arc(880, ly, 7, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();
    }

    // Telão Central
    const scrX = 220, scrY = 360, scrW = 640, scrH = 460;
    ctx.fillStyle = 'rgba(0, 0, 0, 0.45)';
    ctx.beginPath();
    ctx.roundRect(scrX + 12, scrY + 16, scrW, scrH, 20);
    ctx.fill();

    drawRoundedRect(ctx, scrX, scrY, scrW, scrH, 18, '#1e293b', PALETA.ink, 7);
    drawRoundedRect(ctx, scrX + 16, scrY + 16, scrW - 32, scrH - 32, 12, '#030d17', PALETA.ink, 5);

    // Digitação real do terminal em JetBrains Mono
    const fullCmd = 'root@datacenter-core:~# ./celebrar_dia_ti.sh';
    const cmdCharCount = Math.floor(clamp(tRel / 0.35, 0.0, 1.0) * fullCmd.length);
    const typedCmd = fullCmd.substring(0, cmdCharCount);
    const cursor = (Math.floor(t * 3.5) % 2 === 0) ? '█' : '';

    ctx.save();
    ctx.fillStyle = '#22c55e';
    ctx.font = "bold 20px 'JetBrains Mono', monospace";
    ctx.fillText(typedCmd + (tRel < 0.38 ? cursor : ''), scrX + 35, scrY + 65);

    if (tRel >= 0.38) {
      ctx.fillStyle = '#38bdf8';
      ctx.font = "bold 18px 'JetBrains Mono', monospace";
      if (tRel >= 0.38) ctx.fillText('[OK] SERVICOS 100% OPERACIONAIS', scrX + 35, scrY + 120);
      if (tRel >= 0.50) ctx.fillText('[OK] ZERO TICKETS PENDENTES', scrX + 35, scrY + 155);
      if (tRel >= 0.62) ctx.fillText('[OK] CAFE = ILIMITADO', scrX + 35, scrY + 190);
    }

    if (tRel >= 0.72) {
      ctx.fillStyle = '#00d4ff';
      ctx.font = "bold 22px 'JetBrains Mono', monospace";
      ctx.fillText('>> PARABÉNS PELO SEU DIA! <<', scrX + 35, scrY + 250);
    }

    ctx.fillStyle = '#4ade80';
    ctx.font = "16px 'JetBrains Mono', monospace";
    ctx.fillText('UPTIME SISTEMA: 99.999% [PERFEITO]', scrX + 35, scrY + 310);

    const upBarW = clamp(tRel / 0.85, 0.0, 1.0) * (scrW - 74);
    drawRoundedRect(ctx, scrX + 35, scrY + 335, scrW - 70, 24, 6, '#064e3b', '#22c55e', 2);
    drawRoundedRect(ctx, scrX + 37, scrY + 337, upBarW, 20, 4, '#22c55e', null, 0);
    ctx.restore();

    const glowGrad = ctx.createRadialGradient(w / 2, 980, 100, w / 2, 980, 560);
    glowGrad.addColorStop(0, 'rgba(34, 197, 94, 0.25)');
    glowGrad.addColorStop(1, 'rgba(34, 197, 94, 0)');
    ctx.fillStyle = glowGrad;
    ctx.fillRect(0, 600, w, 900);

    // ECHO: Comemoração com mãos para cima e bounce
    const bounceY = Math.sin(t * 8) * 8;
    director.setSize(758);
    director.setPose('K8');
    const b = director.bot;
    b.look.x = 0.0;
    b.look.y = -0.2;
    b.s.yaw = 0.0;
    b.s.tilt = 0.0;
    b.s.hands = 0;
    b.eyeOv = 'happy';
    director.render();
    bocaFalando(director, echoCanvas, mouthVal);

    desenharEcho(ctx, echoCanvas, 160, 840 + bounceY, 758, 758, { strokeWidth: 7 });

    if (window.__capaMaosBaixo) {                    // só para a capa do post: mãos em repouso ao lado do corpo
      maoEcho(ctx, 330, 1470, 758); maoEcho(ctx, 750, 1470, 758);
    } else {
      maoEcho(ctx, 312, 917 + bounceY, 758);
      maoEcho(ctx, 767, 917 + bounceY, 758);
    }

    desenharLegendaAnimada(ctx, [
      [
        { text: "FELIZ", t: 10.83, highlightColor: "#ffffff" },
        { text: "DIA", t: 11.15, highlightColor: "#ffffff" },
        { text: "DO", t: 11.40, highlightColor: "#ffffff" }
      ],
      [
        { text: "PROFISSIONAL", t: 11.60, highlightColor: "#00d4ff" },
        { text: "DE", t: 12.20, highlightColor: "#00d4ff" },
        { text: "TI!", t: 12.35, highlightColor: "#00d4ff" }
      ]
    ], t);
  }

  /**
   * C10: 13.1s – 17.0s ("Um abraço do Estratégia Nerd!" + fecho institucional)
   * Caveira ganha destaque, data e @ entram em sequência, Echo acena naturalmente com mãos canônicas.
   */
  function animCena10(ctx, t, director, echoCanvas, logoImg, mouthVal) {
    const w = 1080, h = 1920;
    const tRel = clamp((t - 13.1) / 3.9, 0.0, 1.0);

    ctx.fillStyle = '#060c14';
    ctx.fillRect(0, 0, w, h);

    // Brilho ciano da marca respirando suavemente
    const pulse = 1.0 + Math.sin(t * 2) * 0.15;
    const brandGlow = ctx.createRadialGradient(w / 2, 850, 120 * pulse, w / 2, 850, 680);
    brandGlow.addColorStop(0, 'rgba(0, 212, 255, 0.28)');
    brandGlow.addColorStop(1, 'rgba(0, 212, 255, 0)');
    ctx.fillStyle = brandGlow;
    ctx.fillRect(0, 240, w, 1200);

    ctx.strokeStyle = 'rgba(0, 212, 255, 0.12)';
    ctx.lineWidth = 2;
    for (let x = 60; x < w; x += 120) {
      ctx.beginPath();
      ctx.moveTo(x, 260);
      ctx.lineTo(x, 1400);
      ctx.stroke();
    }
    for (let y = 300; y < 1400; y += 120) {
      ctx.beginPath();
      ctx.moveTo(60, y);
      ctx.lineTo(w - 60, y);
      ctx.stroke();
    }

    // Caveira com fade-in e scale suave
    const logoAlpha = clamp(tRel / 0.25, 0.0, 1.0);
    const logoScale = lerp(0.92, 1.0, easeOutCubic(logoAlpha));
    const logoH = 560 * logoScale;
    let logoW = logoH;

    if (logoImg && logoImg.complete && logoImg.naturalWidth > 0) {
      logoW = logoH * logoImg.naturalWidth / logoImg.naturalHeight;
      ctx.save();
      ctx.globalAlpha = logoAlpha;
      ctx.shadowColor = 'rgba(0, 212, 255, 0.8)';
      ctx.shadowBlur = 40;
      ctx.drawImage(logoImg, (w - logoW) / 2, 250 + (560 - logoH) / 2, logoW, logoH);
      ctx.restore();
    }

    // Entrada em sequência da data e @
    const dateAlpha = clamp((t - 14.0) / 0.4, 0.0, 1.0);
    if (dateAlpha > 0) {
      const dateY = lerp(815, 800, easeOutQuad(dateAlpha));
      ctx.save();
      ctx.globalAlpha = dateAlpha;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.lineJoin = 'round';
      ctx.font = "700 34px 'Orbitron', sans-serif";
      ctx.lineWidth = 8;
      ctx.strokeStyle = '#0a1017';
      ctx.strokeText('19/10 · DIA DO PROFISSIONAL DE TI', w / 2, dateY);
      ctx.fillStyle = '#ffffff';
      ctx.fillText('19/10 · DIA DO PROFISSIONAL DE TI', w / 2, dateY);
      ctx.restore();
    }

    const atAlpha = clamp((t - 14.6) / 0.4, 0.0, 1.0);
    if (atAlpha > 0) {
      const atY = lerp(870, 856, easeOutQuad(atAlpha));
      ctx.save();
      ctx.globalAlpha = atAlpha;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.lineJoin = 'round';
      ctx.font = "30px 'JetBrains Mono', monospace";
      ctx.lineWidth = 6;
      ctx.strokeStyle = '#0a1017';
      ctx.strokeText('@estrategia_nerd', w / 2, atY);
      ctx.fillStyle = '#00d4ff';
      ctx.fillText('@estrategia_nerd', w / 2, atY);
      ctx.restore();
    }

    // ECHO: Acenando continuamente com a mão oficial
    director.setSize(720);
    director.setPose('K1');
    const b = director.bot;
    b.look.x = 0.0;
    b.look.y = 0.0;
    b.s.yaw = 0.0;
    b.s.tilt = 0.05 + Math.sin(t * 3) * 0.02;
    b.s.hands = 1.0;
    b.waveSide = 1;
    b.eyeOv = 'happy';
    director.render();
    // Boca fala até o final da fala (~15.2s), depois fecha completamente
    const finalMouth = (t <= 15.2) ? mouthVal : 0.0;
    bocaFalando(director, echoCanvas, finalMouth);

    desenharEcho(ctx, echoCanvas, 180, 900, 720, 720, { strokeWidth: 7 });
    b.waveSide = 0;

    // Legenda até a fala terminar
    if (t <= 15.2) {
      desenharLegendaAnimada(ctx, [
        [
          { text: "UM", t: 13.28, highlightColor: "#ffffff" },
          { text: "ABRAÇO", t: 13.50, highlightColor: "#ffffff" },
          { text: "DO", t: 13.85, highlightColor: "#ffffff" }
        ],
        [
          { text: "ESTRATÉGIA", t: 14.05, highlightColor: "#00d4ff" },
          { text: "NERD!", t: 14.50, highlightColor: "#00d4ff" }
        ]
      ], t);
    }
  }

  // Exportar os renderizadores animados
  window.CenasAnimTI = {
    PALETA,
    animCena01,
    animCena02,
    animCena03,
    animCena04,
    animCena05,
    animCena06,
    animCena07,
    animCena08,
    animCena09,
    animCena10,
    desenharLegendaAnimada
  };

})(window);
