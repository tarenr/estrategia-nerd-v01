/**
 * cenas.js - Motor procedural de desenho 2D para as 10 cenas da v3 do Reel Dia do Profissional de TI
 * Padrão visual: Ilustração cartoon com contorno escuro grosso (~6-8px), cel-shading em 2 tons,
 * paleta petróleo/turquesa e volume físico.
 * 10 cenários e enquadramentos únicos e específicos por cena.
 */
(function(window) {
  'use strict';

  // Paleta oficial inspirada na referência e identidade do projeto
  const PALETA = {
    ink: '#111c26',            // Contorno escuro grosso tipo tinta de quadrinho
    inkSoft: '#1e2c38',
    wallBase: '#3c8e8c',       // Parede azul-petróleo média
    wallDark: '#276866',       // Sombra da parede
    wallLight: '#54b0ae',      // Luz da parede
    floorBase: '#255e5c',      // Piso petróleo escuro
    floorDark: '#194543',
    floorGrid: '#17403e',
    rackFrame: '#2c394b',      // Chassi do rack metálico
    rackFrameDark: '#1b2432',
    rackFrameLight: '#415269',
    rackFace: '#0d131d',
    rackRail: '#50637a',
    cableBlue: '#1e4b85',      // Cabos principais grossos
    cableBlueDark: '#132f57',
    cableDark: '#151e28',
    cableDark2: '#1e293b',
    cableYellow: '#eab308',    // Cabos de destaque
    cableYellowDark: '#a16207',
    cableRed: '#ef4444',
    cableRedDark: '#991b1b',
    cableGrey: '#64748b',
    cableGreyDark: '#334155',
    plugBody: '#334155',       // Plugue de borracha
    plugPin: '#cbd5e1',        // Pinos de metal
    ledGreen: '#22c55e',
    ledGreenGlow: 'rgba(34, 197, 94, 0.45)',
    ledAmber: '#f59e0b',
    ledRed: '#ef4444',
    ledCyan: '#06b6d4',
    cardBg: '#0f172a',
    brandDark: '#080d16',
    brandCyan: '#00d4ff',
    deskWood: '#b45309',
    deskWoodDark: '#78350f',
    warmLight: 'rgba(251, 191, 36, 0.22)'
  };

  /**
   * Helper para desenhar retângulos arredondados com contorno grosso
   */
  function drawRoundedRect(ctx, x, y, w, h, r, fill, stroke, strokeWidth = 6) {
    ctx.save();
    ctx.beginPath();
    r = Math.min(r, Math.abs(w) / 2, Math.abs(h) / 2);
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
    if (fill) {
      ctx.fillStyle = fill;
      ctx.fill();
    }
    if (stroke) {
      ctx.strokeStyle = stroke;
      ctx.lineWidth = strokeWidth;
      ctx.lineJoin = 'round';
      ctx.lineCap = 'round';
      ctx.stroke();
    }
    ctx.restore();
  }

  /**
   * Helper para desenhar tubo/cabo volumoso com contorno cartoon e cel-shading em 2 tons
   */
  function drawThickCable(ctx, pts, color, colorDark, width = 20, inkWidth = 7) {
    if (!pts || pts.length < 4) return;
    ctx.save();

    // 1. Contorno externo de tinta
    ctx.beginPath();
    ctx.moveTo(pts[0], pts[1]);
    if (pts.length === 4) {
      ctx.lineTo(pts[2], pts[3]);
    } else if (pts.length === 6) {
      ctx.quadraticCurveTo(pts[2], pts[3], pts[4], pts[5]);
    } else if (pts.length >= 8) {
      ctx.bezierCurveTo(pts[2], pts[3], pts[4], pts[5], pts[6], pts[7]);
    }
    ctx.strokeStyle = PALETA.ink;
    ctx.lineWidth = width + inkWidth * 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.stroke();

    // 2. Sombra de base do cabo
    ctx.strokeStyle = colorDark || PALETA.inkSoft;
    ctx.lineWidth = width;
    ctx.stroke();

    // 3. Tom de luz/cor chapada cel-shade
    ctx.strokeStyle = color;
    ctx.lineWidth = width * 0.72;
    ctx.stroke();

    // 4. Brilho tubular fino
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.28)';
    ctx.lineWidth = Math.max(3, width * 0.16);
    ctx.stroke();

    ctx.restore();
  }

  /**
   * Helper para desenhar plugue cartoon com 2 pinos
   */
  function drawPlugue2Pinos(ctx, x, y, angle = 0, scale = 1.0) {
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(angle);
    ctx.scale(scale, scale);

    // Sombra de apoio
    ctx.fillStyle = 'rgba(0, 0, 0, 0.35)';
    ctx.beginPath();
    ctx.ellipse(0, 20, 30, 10, 0, 0, Math.PI * 2);
    ctx.fill();

    // Pinos metálicos (2 pinos)
    ctx.fillStyle = PALETA.plugPin;
    ctx.strokeStyle = PALETA.ink;
    ctx.lineWidth = 4;

    // Pino superior
    ctx.fillRect(22, -11, 24, 7);
    ctx.strokeRect(22, -11, 24, 7);
    // Pino inferior
    ctx.fillRect(22, 5, 24, 7);
    ctx.strokeRect(22, 5, 24, 7);

    // Furos nos pinos metálicos
    ctx.fillStyle = PALETA.ink;
    ctx.beginPath();
    ctx.arc(38, -7.5, 2.2, 0, Math.PI * 2);
    ctx.arc(38, 8.5, 2.2, 0, Math.PI * 2);
    ctx.fill();

    // Corpo de borracha do plugue
    drawRoundedRect(ctx, -26, -18, 50, 36, 10, PALETA.plugBody, PALETA.ink, 5);

    // Chanfro cel-shade superior
    ctx.fillStyle = '#475569';
    ctx.fillRect(-22, -15, 42, 6);

    // Ranhuras de pegada de borracha
    ctx.fillStyle = PALETA.ink;
    ctx.fillRect(-15, -12, 4, 24);
    ctx.fillRect(-5, -12, 4, 24);
    ctx.fillRect(5, -12, 4, 24);

    // Alívio de tensão do cabo
    drawRoundedRect(ctx, -38, -10, 14, 20, 4, '#1e293b', PALETA.ink, 4);

    ctx.restore();
  }

  /**
   * Boca robótica procedural do Echo (sem dentes/língua)
   * Forma arredondada escura (#0a1017) com contorno preto e interior com brilho ciano suave (#00d4ff)
   * Abertura proporcional a mouthValue.
   */
  function desenharBocaRobo(ctx, cx, cy, width, mouthValue, angle = 0) {
    if (mouthValue < 0.05) return;
    ctx.save();
    ctx.translate(cx, cy);
    if (angle !== 0) ctx.rotate(angle);

    const w = width;
    const h = Math.max(24, w * (0.22 + mouthValue * 0.44));
    const r = Math.min(h / 2, 28);

    // 1. Sombra sutil da boca
    ctx.save();
    ctx.fillStyle = 'rgba(0, 0, 0, 0.45)';
    ctx.beginPath();
    ctx.roundRect(-w / 2, -h / 2 + 4, w, h, [r * 0.7, r * 0.7, r, r]);
    ctx.fill();
    ctx.restore();

    // 2. Fundo escuro robótico (#0a1017)
    ctx.beginPath();
    ctx.roundRect(-w / 2, -h / 2, w, h, [r * 0.7, r * 0.7, r, r]);
    ctx.fillStyle = '#0a1017';
    ctx.fill();

    // 3. Contorno escuro cartoon de tinta
    ctx.strokeStyle = PALETA.ink;
    ctx.lineWidth = Math.max(6, w * 0.052);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.stroke();

    // 4. Interior com brilho ciano suave (#00d4ff) e barra emissora robótica
    ctx.save();
    ctx.beginPath();
    ctx.roundRect(-w / 2 + 4, -h / 2 + 3, w - 8, h - 6, [r * 0.6, r * 0.6, r * 0.85, r * 0.85]);
    ctx.clip();

    // Brilho ciano suave
    const glow = ctx.createRadialGradient(0, 0, 4, 0, 0, w * 0.55);
    const alphaCore = Math.min(0.70, 0.30 + mouthValue * 0.40);
    glow.addColorStop(0, `rgba(0, 212, 255, ${alphaCore})`);
    glow.addColorStop(0.55, `rgba(0, 180, 220, ${alphaCore * 0.45})`);
    glow.addColorStop(1, 'rgba(10, 16, 23, 0)');
    ctx.fillStyle = glow;
    ctx.fillRect(-w / 2, -h / 2, w, h);

    // Emissor horizontal de voz robótica
    const barW = (w - 24) * (0.40 + mouthValue * 0.50);
    const barH = Math.max(4, Math.min(8, h * 0.16));
    ctx.fillStyle = `rgba(0, 212, 255, ${0.50 + mouthValue * 0.45})`;
    ctx.beginPath();
    ctx.roundRect(-barW / 2, -barH / 2, barW, barH, barH / 2);
    ctx.fill();

    ctx.restore();
    ctx.restore();
  }

  /**
   * Boca falando: usa a fala do próprio motor do Echo (s.mouthOpen), com a abertura do boca.json.
   * Chamar logo depois de director.render(), antes de desenharEcho().
   */
  function bocaFalando(director, echoCanvas, valor) {
    const b = director.bot;
    b.s.mouthOpen = valor > 0.05 ? Math.min(1, valor) : 0;
    b.draw();
  }

  /**
   * Mão oficial do Echo, igual ao Manifesto (drawOfficialEchoHands) e ao robo-motor.js:
   * preenchimento #7fe3f5, borda #00d4ff, raio R*0.18*0.85*k (k = tamanho do Echo / 280), linha 2k.
   * escala > 1 só para a mão em primeiro plano, perto da câmera.
   */
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

  /**
   * Renderização de legendas padronizadas em Orbitron 700:
   * Sem caixas de fundo.
   * Contorno escuro grosso (8-10px) e sombra leve.
   * CENTRALIZADO horizontalmente (X = 540).
   * SEMPRE na mesma altura (Y_CENTRO = 250).
   * No máximo 2 linhas, uma palavra-chave em ciano #00d4ff.
   * Mesma cor de base em todas as cenas.
   * Medição REAL pixel a pixel via offscreen canvas.
   */
  function desenharLegenda(ctx, linhasData) {
    if (!linhasData || linhasData.length === 0) return null;

    const w = 1080;
    const yCenter = 250; // Altura fixa e idêntica para todas as cenas

    // Ajustar tamanho de fonte para a linha mais longa caber na largura segura (maxW = 850px)
    let fontSize = 44;
    let lineHeight = 56;
    const maxSafeW = 850;

    // Função de medição de largura total de uma linha
    function measureLineWidth(fs, lData) {
      ctx.font = `700 ${fs}px 'Orbitron', sans-serif`;
      let totalW = 0;
      for (const seg of lData) {
        totalW += ctx.measureText(seg.text).width;
      }
      return totalW;
    }

    // Encontrar tamanho que caiba com folga
    for (let s = 44; s >= 28; s -= 2) {
      let fits = true;
      for (const linha of linhasData) {
        if (measureLineWidth(s, linha) > maxSafeW) {
          fits = false;
          break;
        }
      }
      if (fits) {
        fontSize = s;
        lineHeight = Math.round(fontSize * 1.28);
        break;
      }
    }

    // Criar canvas offscreen para medição exata por pixels dos glifos renderizados
    const off = document.createElement('canvas');
    off.width = w;
    off.height = 500;
    const offCtx = off.getContext('2d');

    const nLinhas = linhasData.length;
    const totalH = (nLinhas - 1) * lineHeight;
    const startY = yCenter - totalH / 2;

    offCtx.font = `700 ${fontSize}px 'Orbitron', sans-serif`;
    offCtx.textBaseline = 'middle';

    const fullTextArray = [];

    linhasData.forEach((linha, lIdx) => {
      const lineY = startY + lIdx * lineHeight;
      const lineW = measureLineWidth(fontSize, linha);
      let curX = (w - lineW) / 2;

      fullTextArray.push(linha.map(s => s.text).join(''));

      // Traço escuro grosso externo (contorno 10px) com sombra suave
      offCtx.save();
      offCtx.shadowColor = 'rgba(0, 0, 0, 0.75)';
      offCtx.shadowBlur = 10;
      offCtx.shadowOffsetY = 4;
      offCtx.strokeStyle = '#0a1017';
      offCtx.lineWidth = 10;
      offCtx.lineJoin = 'round';
      offCtx.lineCap = 'round';

      let tempX = curX;
      for (const seg of linha) {
        offCtx.strokeText(seg.text, tempX, lineY);
        tempX += offCtx.measureText(seg.text).width;
      }
      offCtx.restore();

      // Preenchimento de cor (branco ou ciano)
      offCtx.save();
      tempX = curX;
      for (const seg of linha) {
        offCtx.fillStyle = seg.color || '#ffffff';
        offCtx.fillText(seg.text, tempX, lineY);
        tempX += offCtx.measureText(seg.text).width;
      }
      offCtx.restore();
    });

    // Medir pixels reais renderizados do texto no offscreen canvas
    const imgData = offCtx.getImageData(0, 0, w, 500);
    const data = imgData.data;
    let minX = w, maxX = 0, minY = 500, maxY = 0;
    let found = false;

    for (let y = 0; y < 500; y++) {
      for (let x = 0; x < w; x++) {
        const a = data[(y * w + x) * 4 + 3];
        if (a > 20) { // pixel visível do texto / contorno
          if (x < minX) minX = x;
          if (x > maxX) maxX = x;
          if (y < minY) minY = y;
          if (y > maxY) maxY = y;
          found = true;
        }
      }
    }

    // Desenhar o texto medido no canvas principal
    ctx.drawImage(off, 0, 0);

    return {
      texto: fullTextArray.join(' / '),
      x0: found ? minX : Math.round((w - maxSafeW) / 2),
      y0: found ? minY : yCenter - 30,
      x1: found ? maxX : Math.round((w + maxSafeW) / 2),
      y1: found ? maxY : yCenter + 30,
      largura: found ? (maxX - minX + 1) : maxSafeW,
      altura: found ? (maxY - minY + 1) : 60
    };
  }

  /**
   * Desenha o Echo no canvas de destino com contorno escuro cartoon
   */
  function desenharEcho(ctxDest, canvasEcho, x, y, largura, altura, opts = {}) {
    ctxDest.save();

    if (opts.apagado) {
      // No apagão, o Echo fica escuro (silhueta cinza-escuro/azulada)
      const off = document.createElement('canvas');
      off.width = largura;
      off.height = altura;
      const offCtx = off.getContext('2d');
      offCtx.drawImage(canvasEcho, 0, 0, largura, altura);
      offCtx.globalCompositeOperation = 'source-in';
      offCtx.fillStyle = '#0a141d';
      offCtx.fillRect(0, 0, largura, altura);
      ctxDest.drawImage(off, x, y, largura, altura);

      // Olhos acesos brilhando no escuro
      const eyeX = x + largura * 0.5;
      const eyeY = y + altura * 0.44;
      const eyeR = largura * 0.045;
      const eyeGap = largura * 0.12;

      ctxDest.save();
      ctxDest.shadowColor = '#00d4ff';
      ctxDest.shadowBlur = 22;
      ctxDest.fillStyle = '#00d4ff';

      ctxDest.beginPath();
      ctxDest.arc(eyeX - eyeGap, eyeY, eyeR, 0, Math.PI * 2);
      ctxDest.fill();
      ctxDest.beginPath();
      ctxDest.arc(eyeX + eyeGap, eyeY, eyeR, 0, Math.PI * 2);
      ctxDest.fill();

      // Centro branco nos olhos
      ctxDest.fillStyle = '#ffffff';
      ctxDest.beginPath();
      ctxDest.arc(eyeX - eyeGap, eyeY, eyeR * 0.45, 0, Math.PI * 2);
      ctxDest.arc(eyeX + eyeGap, eyeY, eyeR * 0.45, 0, Math.PI * 2);
      ctxDest.fill();
      ctxDest.restore();

      ctxDest.restore();
      return;
    }

    // Silhueta preta para o contorno cartoon
    const mask = document.createElement('canvas');
    mask.width = largura;
    mask.height = altura;
    const maskCtx = mask.getContext('2d');
    maskCtx.drawImage(canvasEcho, 0, 0, largura, altura);
    maskCtx.globalCompositeOperation = 'source-in';
    maskCtx.fillStyle = PALETA.ink;
    maskCtx.fillRect(0, 0, largura, altura);

    // Dilatação para contorno escuro cartoon de 6-7px
    const r = opts.strokeWidth || 6;
    const angles = [0, 0.785, 1.57, 2.356, 3.14, 3.927, 4.71, 5.498];
    angles.forEach(a => {
      ctxDest.drawImage(mask, x + Math.cos(a) * r, y + Math.sin(a) * r, largura, altura);
    });

    // Desenhar Echo colorido por cima
    ctxDest.drawImage(canvasEcho, x, y, largura, altura);
    ctxDest.restore();
  }

  // ==========================================
  // OS 10 CENÁRIOS ESPECÍFICOS
  // ==========================================

  /**
   * C01: CLOSE no rosto do Echo.
   * Apontando para a câmera com uma das mãos flutuantes em primeiro plano (mão grande, perto da lente).
   * Fundo: Corredor de data center profundo e detalhado com bandejas de cabos, conduítes e LEDs.
   */
  function desenharCena01(ctx, t, director, echoCanvas, mouthVal) {
    const w = 1080, h = 1920;

    // Fundo: Corredor de data center profundo
    ctx.fillStyle = '#0d222b';
    ctx.fillRect(0, 0, w, h);

    // Teto com bandejas de cabos e conduítes industriais
    drawRoundedRect(ctx, -20, -20, w + 40, 260, 0, '#152b36', PALETA.ink, 6);
    // Esteiras de cabos amarelos e azuis no teto
    for (let tx = 80; tx < w; tx += 180) {
      drawRoundedRect(ctx, tx, 40, 90, 80, 8, '#243b47', PALETA.ink, 4);
      ctx.fillStyle = (tx % 360 === 0) ? PALETA.cableYellow : PALETA.cableBlue;
      ctx.fillRect(tx + 10, 60, 70, 16);
      ctx.fillRect(tx + 10, 85, 70, 16);
    }
    // Conduíte horizontal
    drawThickCable(ctx, [0, 160, 400, 160, 800, 160, w, 160], '#475569', '#1e293b', 18, 5);

    // Piso elevado em perspectiva com grelhas de ventilação
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

    // Racks de servidores nas duas laterais com portas perfuradas e matriz de LEDs
    const racks = [
      { x: 30, y: 320, rw: 240, rh: 1050 },
      { x: 810, y: 320, rw: 240, rh: 1050 }
    ];
    racks.forEach(rb => {
      drawRoundedRect(ctx, rb.x, rb.y, rb.rw, rb.rh, 16, '#18303d', PALETA.ink, 6);
      // Painéis internos
      drawRoundedRect(ctx, rb.x + 15, rb.y + 20, rb.rw - 30, rb.rh - 40, 10, '#0f2029', PALETA.ink, 4);
      // LEDs coloridos
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

    // Luz de recorte (rim-light) azulada atrás do Echo
    const rimGrad = ctx.createRadialGradient(w / 2, 780, 180, w / 2, 780, 560);
    rimGrad.addColorStop(0, 'rgba(0, 212, 255, 0.25)');
    rimGrad.addColorStop(1, 'rgba(0, 212, 255, 0)');
    ctx.fillStyle = rimGrad;
    ctx.fillRect(0, 300, w, 1100);

    // ECHO: CLOSE frontal no rosto. Cabeça >= 700 px (990 px).
    director.setSize(1220);
    director.setPose('K2');
    const b = director.bot;
    b.look.x = 0.0;
    b.look.y = 0.0;
    b.s.yaw = 0.0;
    b.s.pitch = 0.0;
    b.s.tilt = 0.0;
    b.s.hands = 0; // mãos do motor desligadas: desenhamos as duas mãos canônicas à mão (uma em primeiro plano)
    b.s.mouthOpen = 0.0;
    b.eyeOv = 'pill';
    director.render();
    bocaFalando(director, echoCanvas, mouthVal);

    // Desenhar Echo centralizado
    desenharEcho(ctx, echoCanvas, -70, 360, 1220, 1220, { strokeWidth: 7 });

    // Mão esquerda do Echo no lugar de sempre (ao lado do corpo)
    maoEcho(ctx, 190, 1330, 1220);

    // MÃO DIREITA EM PRIMEIRO PLANO, PERTO DA LENTE, APONTANDO PARA QUEM ASSISTE
    const hx = 790, hy = 1290, hr = 56 * 2.8;
    // linhas de "tchan" em volta da mão
    ctx.save();
    ctx.strokeStyle = '#7fe3f5';
    ctx.lineCap = 'round';
    ctx.lineWidth = 12;
    [[-2.5, 1.0], [-1.9, 1.0], [-3.0, 1.0]].forEach(([ang]) => {
      ctx.beginPath();
      ctx.moveTo(hx + Math.cos(ang) * (hr + 30), hy + Math.sin(ang) * (hr + 30));
      ctx.lineTo(hx + Math.cos(ang) * (hr + 85), hy + Math.sin(ang) * (hr + 85));
      ctx.stroke();
    });
    ctx.restore();
    maoEcho(ctx, hx, hy, 1220, 2.8);

    // Legenda padronizada
    return desenharLegenda(ctx, [
      [{ text: "EI, VOCÊ AÍ", color: "#ffffff" }],
      [{ text: "DO ", color: "#ffffff" }, { text: "TI!", color: "#00d4ff" }]
    ]);
  }

  /**
   * C02: "FORMATA"
   * Echo aparece de verdade (cabeça e mãos visíveis, >= 40% da largura).
   * Monitor robusto exibindo "Formatando... 100% Concluído" e bancada com pegboard de ferramentas.
   */
  function desenharCena02(ctx, t, director, echoCanvas, mouthVal) {
    const w = 1080, h = 1920;

    // Fundo da bancada de manutenção
    ctx.fillStyle = '#1a333d';
    ctx.fillRect(0, 0, w, h);

    // Parede com pegboard (painel perfurado) metálico
    drawRoundedRect(ctx, -20, -20, w + 40, 1140, 0, '#132830', PALETA.ink, 6);
    ctx.fillStyle = '#0b1920';
    for (let py = 100; py < 1080; py += 55) {
      for (let px = 40; px < w; px += 55) {
        ctx.beginPath();
        ctx.arc(px, py, 3.5, 0, Math.PI * 2);
        ctx.fill();
      }
    }

    // Ferramentas penduradas no pegboard (chaves de fenda, alicate, cabos enrolados)
    // Alicate de crimpar RJ45
    drawRoundedRect(ctx, 60, 420, 24, 140, 6, '#38bdf8', PALETA.ink, 5);
    drawRoundedRect(ctx, 95, 420, 24, 140, 6, '#38bdf8', PALETA.ink, 5);
    drawRoundedRect(ctx, 60, 380, 60, 50, 10, '#64748b', PALETA.ink, 5);

    // Prateleira metálica superior com roteador e caixas de peças
    drawRoundedRect(ctx, 40, 220, w - 80, 28, 4, '#334155', PALETA.ink, 6);
    drawRoundedRect(ctx, 80, 130, 160, 90, 8, '#1e293b', PALETA.ink, 5); // Roteador com antenas
    ctx.fillStyle = PALETA.ledGreen;
    ctx.fillRect(100, 190, 8, 8);
    ctx.fillRect(120, 190, 8, 8);
    ctx.fillRect(140, 190, 8, 8);

    // Superfície da bancada de madeira escura
    drawRoundedRect(ctx, -20, 1100, w + 40, h - 1100 + 40, 0, '#1f2e38', PALETA.ink, 8);
    // Tapete antiestático cinza-azulado com grade, peças e ferramentas na bancada
    drawRoundedRect(ctx, 40, 1130, w - 80, 720, 16, '#27485a', PALETA.ink, 6);
    ctx.strokeStyle = 'rgba(15, 33, 44, 0.7)';
    ctx.lineWidth = 3;
    for (let gx = 80; gx < w - 40; gx += 70) { ctx.beginPath(); ctx.moveTo(gx, 1134); ctx.lineTo(gx, 1846); ctx.stroke(); }
    for (let gy = 1180; gy < 1846; gy += 70) { ctx.beginPath(); ctx.moveTo(44, gy); ctx.lineTo(w - 44, gy); ctx.stroke(); }
    ctx.fillStyle = 'rgba(255, 255, 255, 0.08)';
    ctx.fillRect(44, 1134, w - 88, 26);
    // SSD aberto, chave de fenda e parafusos
    drawRoundedRect(ctx, 700, 1420, 220, 130, 12, '#94a3b8', PALETA.ink, 6);
    drawRoundedRect(ctx, 724, 1442, 172, 86, 8, '#1e293b', PALETA.ink, 4);
    ctx.fillStyle = '#22c55e'; ctx.fillRect(744, 1470, 60, 14);
    drawThickCable(ctx, [180, 1640, 520, 1560], '#f59e0b', '#b45309', 22, 6);
    drawThickCable(ctx, [520, 1560, 640, 1530], '#cbd5e1', '#64748b', 10, 4);
    ctx.fillStyle = '#cbd5e1'; ctx.strokeStyle = PALETA.ink; ctx.lineWidth = 3;
    [[300, 1720], [340, 1745], [390, 1712]].forEach(([px, py]) => { ctx.beginPath(); ctx.arc(px, py, 10, 0, Math.PI * 2); ctx.fill(); ctx.stroke(); });
    // Caneca de café na bancada
    drawRoundedRect(ctx, 820, 1640, 90, 110, 14, '#f8fafc', PALETA.ink, 6);
    ctx.fillStyle = '#0284c7'; ctx.font = 'bold 18px monospace'; ctx.fillText('<TI/>', 834, 1702);

    // MONITOR INDUSTRIAL / CRT À DIREITA
    const monX = 380, monY = 380, monW = 660, monH = 680;
    // Sombra do monitor
    ctx.fillStyle = 'rgba(0, 0, 0, 0.45)';
    ctx.beginPath();
    ctx.roundRect(monX + 15, monY + 20, monW, monH, 26);
    ctx.fill();

    // Carcaça externa robusta
    drawRoundedRect(ctx, monX, monY, monW, monH, 24, '#1e293b', PALETA.ink, 8);
    // Fendas de ventilação
    ctx.fillStyle = PALETA.ink;
    for (let vx = monX + 80; vx < monX + monW - 80; vx += 30) {
      ctx.fillRect(vx, monY + 30, 18, 8);
    }

    // Tela interna do monitor
    const scrX = monX + 36, scrY = monY + 60, scrW = monW - 72, scrH = monH - 110;
    drawRoundedRect(ctx, scrX, scrY, scrW, scrH, 14, '#031720', PALETA.ink, 6);

    // Janela de utilitário FORMAT_V3
    const winX = scrX + 24, winY = scrY + 30, winW = scrW - 48, winH = scrH - 60;
    drawRoundedRect(ctx, winX, winY, winW, winH, 10, '#0a141d', '#0284c7', 4);

    // Título da janela
    ctx.fillStyle = '#0284c7';
    ctx.fillRect(winX, winY, winW, 40);
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 20px monospace';
    ctx.fillText('FORMAT_TI_V3.EXE - NVME 2TB', winX + 18, winY + 26);

    // Linhas de progresso e status
    ctx.fillStyle = '#38bdf8';
    ctx.font = 'bold 22px monospace';
    ctx.fillText('DISPOSITIVO: C:\\ [SSD NVME 2TB]', winX + 24, winY + 95);
    ctx.fillStyle = '#94a3b8';
    ctx.font = '18px monospace';
    ctx.fillText('SISTEMA: NTFS · CLUSTER 4096 BYTES', winX + 24, winY + 135);

    // Barra de progresso 100% verde vibrante
    const barX = winX + 24, barY = winY + 180, barW = winW - 48, barH = 50;
    drawRoundedRect(ctx, barX, barY, barW, barH, 8, '#064e3b', '#10b981', 3);
    drawRoundedRect(ctx, barX + 3, barY + 3, barW - 6, barH - 6, 6, '#22c55e', null, 0);

    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 24px "Orbitron", sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('100% CONCLUÍDO', winX + winW / 2, barY + barH / 2 + 1);
    ctx.textAlign = 'left';

    ctx.fillStyle = '#4ade80';
    ctx.font = 'bold 20px monospace';
    ctx.fillText('STATUS: SISTEMA PRONTO P/ USO', winX + 24, winY + 285);

    // Teclado mecânico na mesa
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

    // ECHO: PRESENTE DE VERDADE (Item 6): Cabeça e mãos visíveis, largura >= 40% (520px).
    // Echo posicionado à esquerda/meio olhando para a tela e digitando
    director.setSize(740);
    director.setPose('K1');
    const b = director.bot;
    b.look.x = 0.55;
    b.look.y = 0.1;
    b.s.yaw = 0.28;
    b.s.tilt = -0.05;
    b.s.hands = 0; // mãos oficiais desenhadas sobre o teclado
    b.eyeOv = 'pill';
    director.render();

    // Desenhar Echo com cabeça e tronco visíveis (largura = 680px >= 40% da tela)
    desenharEcho(ctx, echoCanvas, -60, 680, 680, 680, { strokeWidth: 7 });

    // Mãos do Echo flutuando sobre o teclado digitando ativamente
    ctx.save();
    // Mão esquerda
    maoEcho(ctx, 347, 1217, 680);
    // Mão direita
    maoEcho(ctx, 567, 1197, 680);
    ctx.restore();

    return desenharLegenda(ctx, [
      [{ text: "FORMATA", color: "#00d4ff" }]
    ]);
  }

  /**
   * C03: "CONFIGURA"
   * Echo aparece de verdade (cabeça e mãos visíveis, >= 40% da largura).
   * Visão do switch corporativo, feixes de cabos coloridos; mãos do Echo encaixando RJ45 com LED verde.
   */
  function desenharCena03(ctx, t, director, echoCanvas, mouthVal) {
    const w = 1080, h = 1920;

    // Fundo do interior do rack de telecom
    ctx.fillStyle = '#111e29';
    ctx.fillRect(0, 0, w, h);

    // Trilhos verticais do rack (19 polegadas) com furos e numeração de U
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

    // PATCH PANEL SUPERIOR (24 portas com cabos conectados)
    const ppX = 140, ppY = 360, ppW = 800, ppH = 150;
    drawRoundedRect(ctx, ppX, ppY, ppW, ppH, 12, '#182433', PALETA.ink, 7);
    for (let px = ppX + 40; px < ppX + ppW - 40; px += 34) {
      drawRoundedRect(ctx, px, ppY + 45, 24, 30, 4, '#0b131d', PALETA.ink, 3);
      // Cabos saindo do patch panel
      ctx.strokeStyle = (px % 68 === 0) ? PALETA.cableBlue : PALETA.cableYellow;
      ctx.lineWidth = 8;
      ctx.beginPath();
      ctx.moveTo(px + 12, ppY + 75);
      ctx.quadraticCurveTo(px + 12, ppY + 160, px + 50, ppY + 240);
      ctx.stroke();
    }

    // SWITCH CORPORATIVO PRINCIPAL DE 48 PORTAS
    const swX = 140, swY = 620, swW = 800, swH = 260;
    drawRoundedRect(ctx, swX, swY, swW, swH, 14, '#1e293b', PALETA.ink, 8);

    // Logotipo e identificação do switch
    ctx.fillStyle = '#38bdf8';
    ctx.font = 'bold 22px monospace';
    ctx.fillText('SWITCH GIGA 48P POE+ // CORE-01', swX + 30, swY + 40);

    // Painel de portas RJ45 com LEDs de link
    for (let row = 0; row < 2; row++) {
      for (let p = 0; p < 18; p++) {
        const portX = swX + 40 + p * 40;
        const portY = swY + 80 + row * 70;
        drawRoundedRect(ctx, portX, portY, 30, 36, 4, '#09111a', PALETA.ink, 3);

        // LED de link em cada porta
        const isTarget = (row === 0 && p === 11);
        ctx.save();
        const ledCol = isTarget ? PALETA.ledGreen : ((p % 3 === 0) ? PALETA.ledGreen : '#15803d');
        ctx.fillStyle = ledCol;
        if (isTarget) {
          ctx.shadowColor = PALETA.ledGreen;
          ctx.shadowBlur = 18;
        }
        ctx.beginPath();
        ctx.arc(portX + 15, portY - 10, isTarget ? 6 : 4, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
      }
    }

    // Chicotes de cabos agrupados com fita velcro nas laterais
    drawThickCable(ctx, [swX - 10, 360, swX - 40, 560, swX - 30, 960], PALETA.cableBlue, null, 26, 6);
    drawThickCable(ctx, [swX + swW + 10, 360, swX + swW + 40, 560, swX + swW + 30, 960], PALETA.cableYellow, null, 24, 6);

    // ECHO: PRESENTE DE VERDADE (Item 6): Cabeça e mãos visíveis, largura >= 40% (520px).
    // Echo posicionado no centro inferior inclinado para cima olhando o encaixe do cabo
    director.setSize(800);
    director.setPose('K1');
    const b = director.bot;
    b.look.x = 0.1;
    b.look.y = -0.4;
    b.s.yaw = -0.15;
    b.s.tilt = 0.08;
    b.s.hands = 0; // mãos canônicas desenhadas à parte: uma no conector, outra ao lado do corpo
    b.eyeOv = 'pill';
    director.render();

    // Desenhar Echo com cabeça e tronco visíveis (largura = 700px >= 40% da tela)
    desenharEcho(ctx, echoCanvas, 190, 940, 700, 700, { strokeWidth: 7 });
    maoEcho(ctx, 250, 1440, 700);

    // Mão do Echo plugando o cabo RJ45; o cabo sai para a direita, longe do rosto
    ctx.save();
    const plugX = swX + 40 + 11 * 40 + 15;
    const plugY = swY + 98;

    // Cabo azul sendo plugado: desce um pouco e vai para a borda direita
    drawThickCable(ctx, [plugX, plugY + 30, plugX + 40, plugY + 230, plugX + 260, plugY + 200, 1100, plugY + 420], '#0284c7', '#0369a1', 18, 5);

    // Mão ciano do Echo segurando o conector
    maoEcho(ctx, plugX + 10, plugY + 60, 700, 1.3);

    // Conector RJ45 transparente
    drawRoundedRect(ctx, plugX - 14, plugY - 8, 28, 42, 4, 'rgba(56, 189, 248, 0.75)', PALETA.ink, 4);
    // Pinos dourados
    ctx.fillStyle = '#eab308';
    ctx.fillRect(plugX - 10, plugY - 6, 20, 6);

    // Clarão/glow de conexão bem-sucedida (LED LINK UP)
    ctx.save();
    ctx.shadowColor = '#22c55e';
    ctx.shadowBlur = 28;
    ctx.fillStyle = '#4ade80';
    ctx.beginPath();
    ctx.arc(plugX, plugY - 12, 9, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();

    ctx.restore();

    return desenharLegenda(ctx, [
      [{ text: "CONFIGURA", color: "#00d4ff" }]
    ]);
  }

  /**
   * C04: "FAZ BACKUP"
   * Fundo preenchido: Sala de servidores / cofre de dados com racks iluminados,
   * cascata de pastas e arquivos voando em arco para um HD externo segurado pelo Echo.
   */
  function desenharCena04(ctx, t, director, echoCanvas, mouthVal) {
    const w = 1080, h = 1920;

    // Fundo da sala de servidores / cofre digital
    ctx.fillStyle = '#102530';
    ctx.fillRect(0, 0, w, h);

    // Paredes com painéis tecnológicos e linhas de LED verticais ciano
    ctx.fillStyle = '#0b1c24';
    ctx.fillRect(0, 0, w, 1180);

    for (let lx = 100; lx < w; lx += 220) {
      drawRoundedRect(ctx, lx, 100, 12, 1040, 6, '#0891b2', null, 0);
      // Racks ao fundo em cel-shading
      drawRoundedRect(ctx, lx + 26, 260, 170, 840, 14, '#162e3b', PALETA.ink, 5);
      // Gavetas de fitas LTO / storages
      for (let ty = 300; ty < 1050; ty += 80) {
        drawRoundedRect(ctx, lx + 40, ty, 142, 55, 6, '#0f2029', PALETA.ink, 3);
        ctx.fillStyle = PALETA.ledCyan;
        ctx.fillRect(lx + 55, ty + 24, 10, 6);
        ctx.fillRect(lx + 75, ty + 24, 10, 6);
      }
    }

    // Piso reflexivo do cofre de dados
    ctx.fillStyle = '#08171f';
    ctx.beginPath();
    ctx.moveTo(0, 1180);
    ctx.lineTo(w, 1180);
    ctx.lineTo(w, h);
    ctx.lineTo(0, h);
    ctx.fill();

    // Linhas de perspectiva no piso
    ctx.strokeStyle = '#153340';
    ctx.lineWidth = 4;
    for (let fx = -200; fx < w + 400; fx += 180) {
      ctx.beginPath();
      ctx.moveTo(fx, 1180);
      ctx.lineTo(fx - 200, h);
      ctx.stroke();
    }

    // ECHO: Plano médio segurando o HD de backup
    director.setSize(760);
    director.setPose('K3');
    const b = director.bot;
    b.look.x = -0.3;
    b.look.y = -0.1;
    b.s.yaw = -0.15;
    b.s.tilt = 0.05;
    b.eyeOv = 'pill';
    director.bot.s.hands = 0; // mãos oficiais desenhadas segurando o HD
    director.render();
    bocaFalando(director, echoCanvas, mouthVal);

    // Desenhar Echo à direita
    desenharEcho(ctx, echoCanvas, 280, 680, 760, 760, { strokeWidth: 7 });

    // HD EXTERNO ROBUSTO DE 4TB SEGURADO PELO ECHO
    const hdX = 360, hdY = 1120, hdW = 340, hdH = 220;
    // Sombra do HD
    ctx.fillStyle = 'rgba(0, 0, 0, 0.45)';
    ctx.beginPath();
    ctx.roundRect(hdX + 10, hdY + 15, hdW, hdH, 24);
    ctx.fill();

    // Carcaça emborrachada resistente a quedas
    drawRoundedRect(ctx, hdX, hdY, hdW, hdH, 22, '#ea580c', PALETA.ink, 8);
    // Miolo de alumínio
    drawRoundedRect(ctx, hdX + 30, hdY + 24, hdW - 60, hdH - 48, 14, '#1e293b', PALETA.ink, 6);

    // Texto no HD
    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 26px "Orbitron", sans-serif';
    ctx.fillText('BACKUP 4TB', hdX + 50, hdY + 80);
    ctx.fillStyle = '#38bdf8';
    ctx.font = 'bold 18px monospace';
    ctx.fillText('ENCRYPTED AES-256', hdX + 50, hdY + 120);

    // LED de gravação azul piscando com brilho
    ctx.save();
    ctx.shadowColor = '#00d4ff';
    ctx.shadowBlur = 18;
    ctx.fillStyle = '#00d4ff';
    ctx.beginPath();
    ctx.arc(hdX + hdW - 65, hdY + 80, 8, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();

    // Mãos do Echo segurando o HD
    maoEcho(ctx, hdX + 12, hdY + 117, 760);
    maoEcho(ctx, hdX + hdW - 12, hdY + 117, 760);

    // CASCATA PARABÓLICA DE PASTAS E ARQUIVOS VOANDO EM ARCO PARA O HD
    const pastas = [
      { x: 140, y: 380, rot: -0.25, cor: '#f59e0b', txt: 'FINANCEIRO' },
      { x: 200, y: 530, rot: -0.15, cor: '#38bdf8', txt: 'CLIENTES' },
      { x: 170, y: 700, rot: 0.12, cor: '#f59e0b', txt: 'DATABASE.SQL' },
      { x: 260, y: 860, rot: -0.08, cor: '#22c55e', txt: 'PROJETOS' },
      { x: 330, y: 1010, rot: 0.20, cor: '#f59e0b', txt: 'BACKUP_FULL' }
    ];

    // Arco de partículas e fluxo de dados
    ctx.save();
    ctx.strokeStyle = 'rgba(0, 212, 255, 0.45)';
    ctx.lineWidth = 8;
    ctx.setLineDash([16, 12]);
    ctx.beginPath();
    ctx.moveTo(140, 360);
    ctx.quadraticCurveTo(120, 780, hdX + 80, hdY + 30);
    ctx.stroke();
    ctx.restore();

    pastas.forEach(p => {
      ctx.save();
      ctx.translate(p.x, p.y);
      ctx.rotate(p.rot);

      // Pasta cartoon com aba
      drawRoundedRect(ctx, -60, -45, 120, 90, 12, p.cor, PALETA.ink, 5);
      // Aba superior da pasta
      drawRoundedRect(ctx, -60, -60, 50, 20, 6, p.cor, PALETA.ink, 4);
      // Folha de papel branca saindo de dentro
      drawRoundedRect(ctx, -45, -35, 90, 70, 6, '#ffffff', PALETA.ink, 4);

      // Linhas de texto simuladas na folha
      ctx.fillStyle = '#64748b';
      ctx.fillRect(-35, -20, 70, 6);
      ctx.fillRect(-35, -5, 55, 6);
      ctx.fillRect(-35, 10, 65, 6);

      ctx.restore();
    });

    return desenharLegenda(ctx, [
      [{ text: "FAZ ", color: "#ffffff" }, { text: "BACKUP", color: "#00d4ff" }]
    ]);
  }

  /**
   * C05: "E RESOLVE QUASE / TUDO COM…"
   * Ninho GROSSO de cabos no alto do rack (Item 5):
   * Dezenas de cabos escuros com contorno, embolados em laços sobre o topo,
   * caindo em laços pelas laterais e se espalhando pelo chão com plugues e tomadas soltos.
   * Echo coçando a cabeça com expressão "hmm".
   */
  function desenharCena05(ctx, t, director, echoCanvas, mouthVal) {
    const w = 1080, h = 1920;

    // Parede azul-petróleo e piso em perspectiva fiéis à referência
    ctx.fillStyle = PALETA.wallBase;
    ctx.fillRect(0, 0, w, 1160);
    // Sombra cel-shade superior na parede
    ctx.fillStyle = PALETA.wallDark;
    ctx.fillRect(0, 0, w, 200);

    // Painéis acústicos e conduítes na parede de fundo
    for (let px = 60; px < w; px += 240) {
      drawRoundedRect(ctx, px, 240, 190, 320, 14, PALETA.wallLight, PALETA.wallDark, 4);
    }
    drawThickCable(ctx, [0, 220, 400, 220, 800, 220, w, 220], '#275252', null, 20, 6);

    // Piso com grade em perspectiva isométrica como na referência
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

    // RACK DE SERVIDORES EM PERSPECTIVA
    const rkX = 450, rkY = 460, rkW = 540, rkH = 920;
    // Sombra de apoio no chão
    ctx.fillStyle = 'rgba(7, 12, 18, 0.45)';
    ctx.beginPath();
    ctx.roundRect(rkX - 25, rkY + rkH - 20, rkW + 70, 130, 22);
    ctx.fill();

    // Chassi metálico do rack
    drawRoundedRect(ctx, rkX, rkY, rkW, rkH, 20, PALETA.rackFrame, PALETA.ink, 8);
    // Interior do rack com servidores
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

    // ========================================================
    // NINHO GROSSO DE CABOS NO ALTO DO RACK (Item 5)
    // Dezenas de cabos escuros com contorno, embolados em laços
    // ========================================================
    const nestX = rkX + rkW / 2;
    const nestY = rkY - 10;

    // Massa volumosa de base do ninho
    drawRoundedRect(ctx, nestX - 250, nestY - 70, 500, 180, 70, '#101d2e', PALETA.ink, 9);
    drawRoundedRect(ctx, nestX - 220, nestY - 110, 440, 160, 60, '#0c1624', PALETA.ink, 9);

    // Dezenas de laços e curvas entrelaçadas sobre o topo (35+ cabos volumosos)
    const loopsCables = [
      // Camada profunda (escuros)
      [nestX - 220, nestY + 20, nestX - 280, nestY - 120, nestX - 140, nestY - 160, nestX - 60, nestY - 10, PALETA.cableDark, 26],
      [nestX - 80, nestY - 20, nestX + 10, nestY - 180, nestX + 180, nestY - 170, nestX + 150, nestY + 20, PALETA.cableDark2, 28],
      [nestX + 80, nestY + 10, nestX + 220, nestY - 140, nestX + 270, nestY - 40, nestX + 170, nestY + 50, PALETA.cableDark, 24],
      [nestX - 180, nestY - 40, nestX - 120, nestY - 160, nestX + 30, nestY - 140, nestX + 90, nestY - 10, PALETA.cableBlueDark, 24],

      // Camada média (laços e loops embolados sobrepostos)
      [nestX - 150, nestY - 10, nestX - 200, nestY - 100, nestX - 70, nestY - 120, nestX - 30, nestY + 30, PALETA.cableBlue, 22],
      [nestX - 50, nestY + 20, nestX - 90, nestY - 90, nestX + 40, nestY - 130, nestX + 70, nestY + 40, PALETA.cableYellow, 20],
      [nestX + 20, nestY + 10, nestX + 100, nestY - 110, nestX + 190, nestY - 80, nestX + 130, nestY + 60, PALETA.cableDark, 26],
      [nestX - 110, nestY + 40, nestX - 40, nestY - 70, nestX + 60, nestY - 60, nestX + 110, nestY + 50, PALETA.cableBlue, 22],
      [nestX - 190, nestY + 30, nestX - 140, nestY - 70, nestX - 20, nestY - 80, nestX + 40, nestY + 50, PALETA.cableDark2, 24],

      // Cabos caindo em alças curvas orgânicas pelas laterais do rack (lado esquerdo)
      [nestX - 210, nestY + 40, nestX - 320, nestY + 220, nestX - 190, nestY + 460, nestX - 280, nestY + 680, PALETA.cableDark, 26],
      [nestX - 170, nestY + 60, nestX - 270, nestY + 260, nestX - 150, nestY + 520, nestX - 230, nestY + 840, PALETA.cableBlue, 24],
      [nestX - 130, nestY + 80, nestX - 230, nestY + 320, nestX - 120, nestY + 600, nestX - 200, nestY + 920, PALETA.cableDark2, 24],
      [nestX - 240, nestY + 180, nestX - 340, nestY + 380, nestX - 220, nestY + 620, nestX - 300, nestY + 880, PALETA.cableYellow, 20],

      // Cabos caindo em alças curvas pela lateral direita
      [nestX + 210, nestY + 40, nestX + 310, nestY + 240, nestX + 170, nestY + 500, nestX + 260, nestY + 760, PALETA.cableDark, 26],
      [nestX + 160, nestY + 60, nestX + 250, nestY + 300, nestX + 140, nestY + 580, nestX + 210, nestY + 860, PALETA.cableBlue, 24],
      [nestX + 120, nestY + 80, nestX + 210, nestY + 360, nestX + 110, nestY + 640, nestX + 170, nestY + 920, PALETA.cableDark2, 22],

      // Cabos caindo pela frente do rack
      [nestX - 60, nestY + 70, nestX - 90, nestY + 240, nestX - 30, nestY + 480, nestX - 80, nestY + 780, PALETA.cableDark, 22],
      [nestX + 40, nestY + 80, nestX + 70, nestY + 280, nestX + 20, nestY + 520, nestX + 60, nestY + 820, PALETA.cableBlueDark, 22],

      // Cabos espalhando-se pelo chão em grandes voltas, nós e laços pesados
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

    // Plugues de 2 pinos soltos no chão jogados entre os cabos
    drawPlugue2Pinos(ctx, nestX - 200, 1540, 0.45, 1.25);
    drawPlugue2Pinos(ctx, nestX + 80, 1580, -0.65, 1.15);
    drawPlugue2Pinos(ctx, nestX + 240, 1640, 1.15, 1.35);
    drawPlugue2Pinos(ctx, nestX - 50, 1680, 2.10, 1.20);

    // Régua de tomadas (filtro de linha) no chão
    drawRoundedRect(ctx, nestX - 150, 1440, 180, 65, 14, '#334155', PALETA.ink, 6);
    for (let tx = nestX - 120; tx < nestX + 15; tx += 42) {
      ctx.fillStyle = PALETA.ink;
      ctx.beginPath();
      ctx.arc(tx, 1460, 4, 0, Math.PI * 2);
      ctx.arc(tx, 1482, 4, 0, Math.PI * 2);
      ctx.fill();
    }

    // ECHO: Plano médio à esquerda coçando a cabeça com a mão, olhos âmbar "hmm"
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
    director.bot.waveSide = -1; // aceno do motor: a mão esquerda sobe para perto da cabeça (coçando)
    director.render();
    bocaFalando(director, echoCanvas, mouthVal);

    // Desenhar Echo à esquerda
    desenharEcho(ctx, echoCanvas, 20, 780, 940, 940, { strokeWidth: 7 });

    director.bot.waveSide = 0;

    return desenharLegenda(ctx, [
      [{ text: "E RESOLVE QUASE", color: "#ffffff" }],
      [{ text: "TUDO COM…", color: "#00d4ff" }]
    ]);
  }

  /**
   * C06: "JÁ TENTOU / REINICIAR?"
   * ENQUADRAMENTO DIFERENTE DO C01 (Item 4):
   * Echo em ângulo de 3/4 com a cabeça inclinada, olhar de "eu sei" (cúmplice/malicioso).
   * Plugue de 2 pinos em primeiro plano GRANDE, voltado para a lente, e o rosto logo atrás.
   * Boca robótica grande aberta (Item 1).
   */
  function desenharCena06(ctx, t, director, echoCanvas, mouthVal) {
    const w = 1080, h = 1920;

    // Fundo escuro dramático de data center com racks entrando em standby
    ctx.fillStyle = '#0a1722';
    ctx.fillRect(0, 0, w, h);

    // Câmera inclinada (plano holandês): fundo e Echo giram juntos; plugue e legenda ficam retos
    ctx.save();
    ctx.translate(w / 2, 900);
    ctx.rotate(-0.1);
    ctx.translate(-w / 2, -900);

    // Racks ao fundo com luz azulada e LEDs âmbar de standby
    drawRoundedRect(ctx, -120, 180, 380, 1400, 18, '#112230', PALETA.ink, 6);
    drawRoundedRect(ctx, 820, 180, 380, 1400, 18, '#112230', PALETA.ink, 6);
    for (let ly = 240; ly < 1500; ly += 70) {
      drawRoundedRect(ctx, -90, ly, 320, 46, 6, '#0c1a25', PALETA.ink, 3);
      drawRoundedRect(ctx, 850, ly, 320, 46, 6, '#0c1a25', PALETA.ink, 3);
      ctx.fillStyle = PALETA.ledAmber;
      ctx.beginPath();
      ctx.arc(200, ly + 23, 6, 0, Math.PI * 2);
      ctx.arc(880, ly + 23, 6, 0, Math.PI * 2);
      ctx.fill();
    }
    // Piso
    ctx.fillStyle = '#081219';
    ctx.fillRect(-200, 1500, w + 400, 800);

    // Luz de recorte atrás do Echo
    const spotGrad = ctx.createRadialGradient(660, 820, 80, 660, 820, 640);
    spotGrad.addColorStop(0, 'rgba(0, 212, 255, 0.30)');
    spotGrad.addColorStop(1, 'rgba(0, 212, 255, 0)');
    ctx.fillStyle = spotGrad;
    ctx.fillRect(-200, 100, w + 400, 1500);

    // ECHO deslocado para a direita, olhando de lado para o plugue (cara de "eu sei")
    director.setSize(1100);
    director.setPose('K5');
    const b = director.bot;
    b.look.x = -0.55;
    b.look.y = 0.1;
    b.s.yaw = -0.42;
    b.s.tilt = 0.12;
    b.s.hands = 0;
    b.s.mouthOpen = 0.0;
    b.eyeOv = 'pill';
    director.render();
    bocaFalando(director, echoCanvas, mouthVal);
    desenharEcho(ctx, echoCanvas, 170, 380, 1100, 1100, { strokeWidth: 8 });
    // mão direita do Echo ao lado do corpo
    maoEcho(ctx, 930, 1250, 1100);
    ctx.restore();

    // PRIMEIRO PLANO: plugue gigante perto da lente, segurado pela mão ciano do Echo
    const plugX = 330, plugY = 1250;
    drawThickCable(ctx, [plugX - 90, plugY + 20, plugX - 230, plugY + 260, plugX - 120, 1990], PALETA.cableDark, null, 46, 10);
    maoEcho(ctx, plugX - 120, plugY + 70, 1100, 1.8);
    drawPlugue2Pinos(ctx, plugX, plugY, -0.55, 4.6);

    return desenharLegenda(ctx, [
      [{ text: "JÁ TENTOU", color: "#ffffff" }],
      [{ text: "REINICIAR?", color: "#00d4ff" }]
    ]);
  }

  /**
   * C07: APAGÃO total: cenário e robô escuros em silhueta; apenas os olhos ciano brilhantes acesos.
   * SEM texto de legenda. Barra sutil "reiniciando... 42%".
   */
  function desenharCena07(ctx, t, director, echoCanvas, mouthVal) {
    const w = 1080, h = 1920;

    // Escuridão quase total no cenário (azul-noite ultra escuro)
    ctx.fillStyle = '#03080e';
    ctx.fillRect(0, 0, w, h);

    // Silhuetas sutis dos racks e teto no escuro
    ctx.fillStyle = '#060f18';
    drawRoundedRect(ctx, 40, 260, 300, 1140, 16, '#060f18', '#091522', 2);
    drawRoundedRect(ctx, 740, 260, 300, 1140, 16, '#060f18', '#091522', 2);

    // ECHO: APAGÃO TOTAL (apenas olhos ciano acesos)
    director.setSize(760);
    director.setPose('K6');
    const b = director.bot;
    b.look.x = 0.0;
    b.look.y = 0.0;
    b.s.yaw = 0.0;
    b.s.tilt = 0.0;
    b.s.hands = 0.0;
    b.eyeOv = 'pill';
    director.render();

    // Desenhar Echo com opção apagado = true
    desenharEcho(ctx, echoCanvas, 160, 680, 760, 760, { apagado: true });

    // Barra sutil minimalista de reinicialização
    const barW = 460, barH = 26;
    const barX = (w - barW) / 2;
    const barY = 1380;

    drawRoundedRect(ctx, barX, barY, barW, barH, 13, '#09131d', '#1e293b', 3);
    // Preenchimento de 42%
    drawRoundedRect(ctx, barX + 3, barY + 3, barW * 0.42, barH - 6, 10, '#00d4ff', null, 0);

    ctx.fillStyle = '#64748b';
    ctx.font = 'bold 20px monospace';
    ctx.textAlign = 'center';
    ctx.fillText('reiniciando... 42%', w / 2, barY + barH + 35);
    ctx.textAlign = 'left';

    // Sem texto de legenda (retorna null)
    return null;
  }

  /**
   * C08: "HOJE O DIA / É SEU!"
   * Mesa do TI com luz quente de luminária, caneca em brinde, post-its coloridos, teclado.
   */
  function desenharCena08(ctx, t, director, echoCanvas, mouthVal) {
    const w = 1080, h = 1920;

    // Fundo aconchegante da sala do suporte TI
    ctx.fillStyle = '#1e2736';
    ctx.fillRect(0, 0, w, h);

    // Parede de tijolos / painéis de madeira
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

    // Post-its coloridos e avisos na parede/monitor
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

    // Dois monitores ao fundo, ligados, com suporte
    [[320, 360], [690, 360]].forEach(([mx, my], k) => {
      drawRoundedRect(ctx, mx + 140, my + 250, 60, 120, 6, '#334155', PALETA.ink, 5);
      drawRoundedRect(ctx, mx, my, 340, 260, 14, '#1e293b', PALETA.ink, 7);
      drawRoundedRect(ctx, mx + 18, my + 18, 304, 210, 8, '#0b2a3a', PALETA.ink, 4);
      ctx.fillStyle = k ? '#22c55e' : '#38bdf8';
      for (let ln = 0; ln < 6; ln++) ctx.fillRect(mx + 36, my + 44 + ln * 30, 120 + ((ln * 53 + k * 31) % 140), 10);
    });

    // Luminária de mesa (braço articulado) à direita
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

    // Cone de luz quente vindo da luminária de mesa (foco âmbar acolhedor)
    const lampGrad = ctx.createRadialGradient(880, 720, 50, 880, 720, 680);
    lampGrad.addColorStop(0, 'rgba(251, 191, 36, 0.40)');
    lampGrad.addColorStop(1, 'rgba(251, 191, 36, 0)');
    ctx.fillStyle = lampGrad;
    ctx.fillRect(0, 300, w, 1100);

    // Superfície da mesa de madeira quente
    drawRoundedRect(ctx, -20, 1100, w + 40, h - 1100 + 40, 0, PALETA.deskWood, PALETA.ink, 8);
    // Borda chanfrada da mesa e veios da madeira
    ctx.fillStyle = '#d97706';
    ctx.fillRect(0, 1104, w, 14);
    ctx.fillStyle = PALETA.deskWoodDark;
    ctx.fillRect(0, 1118, w, 16);
    ctx.strokeStyle = 'rgba(120, 53, 15, 0.55)';
    ctx.lineWidth = 4;
    for (let vy = 1170; vy < h; vy += 90) {
      ctx.beginPath();
      ctx.moveTo(0, vy);
      ctx.bezierCurveTo(300, vy - 25, 700, vy + 25, w, vy - 10);
      ctx.stroke();
    }

    // Teclado mecânico na mesa
    drawRoundedRect(ctx, 160, 1180, 480, 140, 14, '#1e293b', PALETA.ink, 6);

    // ECHO: Plano médio erguendo a caneca em brinde
    director.setSize(745);
    director.setPose('K7');
    const b = director.bot;
    b.look.x = 0.2;
    b.look.y = -0.1;
    b.s.yaw = 0.12;
    b.s.tilt = -0.06;
    b.s.hands = 0; // só duas mãos canônicas: uma na caneca, outra ao lado do corpo
    b.eyeOv = 'happy';
    director.render();
    bocaFalando(director, echoCanvas, mouthVal);

    // Desenhar Echo centralizado na mesa
    desenharEcho(ctx, echoCanvas, 160, 720, 745, 745, { strokeWidth: 7 });
    maoEcho(ctx, 330, 1330, 745);

    // CANECA DE CAFÉ ERGUIDA EM BRINDE
    ctx.save();
    const mugX = 720, mugY = 1040;

    // Sombra da caneca
    ctx.fillStyle = 'rgba(0, 0, 0, 0.45)';
    ctx.beginPath();
    ctx.ellipse(mugX, mugY + 65, 55, 20, 0, 0, Math.PI * 2);
    ctx.fill();

    // Mão ciano do Echo segurando a caneca
    maoEcho(ctx, mugX - 52, mugY + 18, 745);

    // Corpo da caneca cartoon
    drawRoundedRect(ctx, mugX - 45, mugY - 50, 90, 110, 16, '#f8fafc', PALETA.ink, 6);
    // Asa da caneca
    ctx.beginPath();
    ctx.arc(mugX + 45, mugY + 5, 28, -Math.PI * 0.4, Math.PI * 0.4);
    ctx.strokeStyle = PALETA.ink;
    ctx.lineWidth = 14;
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(mugX + 45, mugY + 5, 28, -Math.PI * 0.4, Math.PI * 0.4);
    ctx.strokeStyle = '#f8fafc';
    ctx.lineWidth = 8;
    ctx.stroke();

    // Café fumegante no topo da caneca
    ctx.fillStyle = '#451a03';
    ctx.beginPath();
    ctx.ellipse(mugX, mugY - 48, 38, 12, 0, 0, Math.PI * 2);
    ctx.fill();

    // Estampa na caneca: caveira / geek
    ctx.fillStyle = '#0284c7';
    ctx.font = 'bold 18px monospace';
    ctx.textAlign = 'center';
    ctx.fillText('<TI/>', mugX, mugY + 15);
    ctx.textAlign = 'left';

    // Fumaça saindo da caneca
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.45)';
    ctx.lineWidth = 4;
    ctx.beginPath();
    ctx.moveTo(mugX - 10, mugY - 60);
    ctx.quadraticCurveTo(mugX - 25, mugY - 95, mugX - 10, mugY - 130);
    ctx.moveTo(mugX + 15, mugY - 60);
    ctx.quadraticCurveTo(mugX + 30, mugY - 95, mugX + 15, mugY - 130);
    ctx.stroke();

    ctx.restore();

    return desenharLegenda(ctx, [
      [{ text: "HOJE O DIA", color: "#ffffff" }],
      [{ text: "É SEU!", color: "#00d4ff" }]
    ]);
  }

  /**
   * C09: "FELIZ DIA DO / PROFISSIONAL DE TI!"
   * Telão de terminal central digitando em JetBrains Mono (Item 8).
   * Boca robótica proporcional aberta (Item 1).
   * LEDs dos racks todos verdes; Echo comemorando com as duas mãos para cima.
   */
  function desenharCena09(ctx, t, director, echoCanvas, mouthVal) {
    const w = 1080, h = 1920;

    // Fundo da sala de operações / NOC
    ctx.fillStyle = '#091522';
    ctx.fillRect(0, 0, w, h);

    // Painéis de controle e monitores auxiliares nas laterais
    drawRoundedRect(ctx, 30, 260, 200, 780, 16, '#0f202f', PALETA.ink, 6);
    drawRoundedRect(ctx, 850, 260, 200, 780, 16, '#0f202f', PALETA.ink, 6);

    // LEDs todos verdes de 100% uptime / comemoração
    for (let ly = 300; ly < 1000; ly += 60) {
      ctx.save();
      ctx.shadowColor = PALETA.ledGreen;
      ctx.shadowBlur = 16;
      ctx.fillStyle = PALETA.ledGreen;
      ctx.beginPath();
      ctx.arc(60, ly, 7, 0, Math.PI * 2);
      ctx.arc(880, ly, 7, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();
    }

    // TELÃO DE TERMINAL CENTRAL GIGANTE
    const scrX = 220, scrY = 360, scrW = 640, scrH = 460;
    // Sombra do telão
    ctx.fillStyle = 'rgba(0, 0, 0, 0.45)';
    ctx.beginPath();
    ctx.roundRect(scrX + 12, scrY + 16, scrW, scrH, 20);
    ctx.fill();

    // Moldura do telão
    drawRoundedRect(ctx, scrX, scrY, scrW, scrH, 18, '#1e293b', PALETA.ink, 7);
    // Tela interna do terminal
    drawRoundedRect(ctx, scrX + 16, scrY + 16, scrW - 32, scrH - 32, 12, '#030d17', PALETA.ink, 5);

    // TEXTO DO TERMINAL EM JETBRAINS MONO (Item 8)
    ctx.save();
    ctx.fillStyle = '#22c55e';
    ctx.font = "bold 20px 'JetBrains Mono', monospace";
    ctx.fillText('root@datacenter-core:~# ./celebrar_dia_ti.sh', scrX + 35, scrY + 65);

    ctx.fillStyle = '#38bdf8';
    ctx.font = "bold 18px 'JetBrains Mono', monospace";
    ctx.fillText('[OK] SERVICOS 100% OPERACIONAIS', scrX + 35, scrY + 120);
    ctx.fillText('[OK] ZERO TICKETS PENDENTES', scrX + 35, scrY + 155);
    ctx.fillText('[OK] CAFE = ILIMITADO', scrX + 35, scrY + 190);

    ctx.fillStyle = '#00d4ff';
    ctx.font = "bold 22px 'JetBrains Mono', monospace";
    ctx.fillText('>> PARABÉNS PELO SEU DIA! <<', scrX + 35, scrY + 250);

    // Gráfico de uptime 99.999%
    ctx.fillStyle = '#4ade80';
    ctx.font = "16px 'JetBrains Mono', monospace";
    ctx.fillText('UPTIME SISTEMA: 99.999% [PERFEITO]', scrX + 35, scrY + 310);

    // Barra de progresso verde
    drawRoundedRect(ctx, scrX + 35, scrY + 335, scrW - 70, 24, 6, '#064e3b', '#22c55e', 2);
    drawRoundedRect(ctx, scrX + 37, scrY + 337, scrW - 74, 20, 4, '#22c55e', null, 0);
    ctx.restore();

    // Clarão comemorativo de luz verde/ciano
    const glowGrad = ctx.createRadialGradient(w / 2, 980, 100, w / 2, 980, 560);
    glowGrad.addColorStop(0, 'rgba(34, 197, 94, 0.25)');
    glowGrad.addColorStop(1, 'rgba(34, 197, 94, 0)');
    ctx.fillStyle = glowGrad;
    ctx.fillRect(0, 600, w, 900);

    // ECHO: Comemorando com as duas mãos para cima!
    director.setSize(758);
    director.setPose('K8');
    const b = director.bot;
    b.look.x = 0.0;
    b.look.y = -0.2;
    b.s.yaw = 0.0;
    b.s.tilt = 0.0;
    b.s.hands = 0; // mãos oficiais desenhadas erguidas
    b.s.mouthOpen = 0.0;
    b.eyeOv = 'happy';
    director.render();
    bocaFalando(director, echoCanvas, mouthVal);

    // Desenhar Echo centralizado comemorando
    desenharEcho(ctx, echoCanvas, 160, 840, 758, 758, { strokeWidth: 7 });

    // BOCA ROBÓTICA GRANDE ABERTA (Item 1): proporcional ao rosto em plano médio (~70px)

    // MÃOS DO ECHO ERGUIDAS EM VITÓRIA (duas mãos para cima)
    ctx.save();
    // Mão esquerda para cima
    maoEcho(ctx, 312, 917, 758);
    // Mão direita para cima
    maoEcho(ctx, 767, 917, 758);
    ctx.restore();

    return desenharLegenda(ctx, [
      [{ text: "FELIZ DIA DO", color: "#ffffff" }],
      [{ text: "PROFISSIONAL DE TI!", color: "#00d4ff" }]
    ]);
  }

  /**
   * C10: "UM ABRAÇO DO / ESTRATÉGIA NERD!"
   * Fundo da marca (ZERO RACKS). Logotipo oficial de caveira (logo-symbol.png).
   * Boca robótica aberta (Item 1). Echo acenando amigavelmente.
   */
  function desenharCena10(ctx, t, director, echoCanvas, logoImg, mouthVal) {
    const w = 1080, h = 1920;

    // Fundo da marca Estratégia Nerd (azul petróleo escuro sofisticado com grade sutil)
    ctx.fillStyle = '#060c14';
    ctx.fillRect(0, 0, w, h);

    // Gradiente radial central de luz ciano da marca
    const brandGlow = ctx.createRadialGradient(w / 2, 850, 120, w / 2, 850, 680);
    brandGlow.addColorStop(0, 'rgba(0, 212, 255, 0.28)');
    brandGlow.addColorStop(1, 'rgba(0, 212, 255, 0)');
    ctx.fillStyle = brandGlow;
    ctx.fillRect(0, 240, w, 1200);

    // Grade cibernética estilizada da marca ao fundo
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

    // CAVEIRA DO ESTRATÉGIA NERD GRANDE, com brilho ciano
    const logoH = 560;
    let logoW = logoH;
    if (logoImg && logoImg.complete && logoImg.naturalWidth > 0) {
      logoW = logoH * logoImg.naturalWidth / logoImg.naturalHeight;
      ctx.save();
      ctx.shadowColor = 'rgba(0, 212, 255, 0.8)';
      ctx.shadowBlur = 40;
      ctx.drawImage(logoImg, (w - logoW) / 2, 250, logoW, logoH);
      ctx.restore();
    }

    // Data e @ do perfil (acima de Y=970, dentro da área segura)
    ctx.save();
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.lineJoin = 'round';
    ctx.font = "700 34px 'Orbitron', sans-serif";
    ctx.lineWidth = 8;
    ctx.strokeStyle = '#0a1017';
    ctx.strokeText('19/10 · DIA DO PROFISSIONAL DE TI', w / 2, 800);
    ctx.fillStyle = '#ffffff';
    ctx.fillText('19/10 · DIA DO PROFISSIONAL DE TI', w / 2, 800);
    ctx.font = "30px 'JetBrains Mono', monospace";
    ctx.lineWidth = 6;
    ctx.strokeText('@estrategia_nerd', w / 2, 856);
    ctx.fillStyle = '#00d4ff';
    ctx.fillText('@estrategia_nerd', w / 2, 856);
    ctx.restore();

    // ECHO acenando (aceno do próprio motor, mãos canônicas)
    director.setSize(720);
    director.setPose('K1');
    const b = director.bot;
    b.look.x = 0.0;
    b.look.y = 0.0;
    b.s.yaw = 0.0;
    b.s.tilt = 0.05;
    b.s.hands = 1.0;
    b.waveSide = 1;
    b.s.mouthOpen = 0.0;
    b.eyeOv = 'happy';
    director.render();
    bocaFalando(director, echoCanvas, mouthVal);
    desenharEcho(ctx, echoCanvas, 180, 900, 720, 720, { strokeWidth: 7 });
    b.waveSide = 0;

    return desenharLegenda(ctx, [
      [{ text: "UM ABRAÇO DO", color: "#ffffff" }],
      [{ text: "ESTRATÉGIA NERD!", color: "#00d4ff" }]
    ]);
  }

  // Exportar para o escopo global
  window.CenasTI = {
    PALETA,
    desenharCena01,
    desenharCena02,
    desenharCena03,
    desenharCena04,
    desenharCena05,
    desenharCena06,
    desenharCena07,
    desenharCena08,
    desenharCena09,
    desenharCena10
  };

})(window);
