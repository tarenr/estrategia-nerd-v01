// Carro esportivo vermelho visto de frente — desenhado do zero em Canvas 2D.
// Proporções medidas na imagem de referência do usuário (só como guia de forma; nada é copiado dela).
// Coordenadas internas em "unidades de carro": x 0..100 (largura total com pneus), y para cima a partir do chão.
// CarroFrente.draw(ctx, cx, chaoY, largura, opts)   -> carroceria, cockpit vazio, volante
// CarroFrente.luz(ctx, cx, chaoY, largura, opts)    -> poças de luz dos faróis no asfalto (camada própria)
// opts: { guiaEcho: false, sacolejo: 0, interior(ctx, S), maos(ctx, S) }
//   interior/maos: encaixes para o motorista; S = { u, X(v), Y(v) } converte unidades de carro em pixels
(function () {
  function rr(ctx, x, y, w, h, r) {
    r = Math.min(r, w / 2, h / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }

  function sistema(cx, chaoY, largura, opts) {
    const u = largura / 100;
    const dy = (opts && opts.sacolejo) || 0;
    return { u, X: (v) => cx + (v - 50) * u, Y: (v) => chaoY - v * u + dy };
  }

  function caminho(ctx, S, pts) {
    ctx.beginPath();
    pts.forEach(([x, y], i) => (i ? ctx.lineTo(S.X(x), S.Y(y)) : ctx.moveTo(S.X(x), S.Y(y))));
    ctx.closePath();
  }
  const espelho = (pts) => pts.map(([x, y]) => [100 - x, y]);

  function caixa(ctx, S, x0, y0, x1, y1, r) {
    rr(ctx, S.X(x0), S.Y(y1), (x1 - x0) * S.u, (y1 - y0) * S.u, r * S.u);
  }

  function vgrad(ctx, S, yTopo, yBase, stops) {
    const g = ctx.createLinearGradient(0, S.Y(yTopo), 0, S.Y(yBase));
    stops.forEach(([o, c]) => g.addColorStop(o, c));
    return g;
  }

  function linha(ctx, S, pts, cor, larg) {
    ctx.strokeStyle = cor;
    ctx.lineWidth = larg * S.u;
    ctx.beginPath();
    pts.forEach(([x, y], i) => (i ? ctx.lineTo(S.X(x), S.Y(y)) : ctx.moveTo(S.X(x), S.Y(y))));
    ctx.stroke();
  }

  // Contorno da frente: larga em cima (para-lamas), recuada embaixo para os pneus aparecerem.
  const FRENTE = [
    [9.5, 3.4], [9.5, 9.8], [2.2, 12.6], [0.9, 14], [0.9, 22.6], [2.4, 25.6],
    [97.6, 25.6], [99.1, 22.6], [99.1, 14], [97.8, 12.6], [90.5, 9.8], [90.5, 3.4],
  ];
  const CAPO = [[2.4, 25.6], [97.6, 25.6], [82.2, 33.8], [17.8, 33.8]];
  const MOLDURA_EXT = [[17.2, 33.4], [28.2, 61], [71.8, 61], [82.8, 33.4]];
  const MOLDURA_INT = [[21.4, 34.6], [30.8, 57.8], [69.2, 57.8], [78.6, 34.6]];

  function draw(ctx, cx, chaoY, largura, opts) {
    opts = opts || {};
    const S = sistema(cx, chaoY, largura, opts);
    const { u, X, Y } = S;
    ctx.save();
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';

    // 1. Sombra no asfalto
    ctx.save();
    ctx.translate(X(50), Y(0.4));
    ctx.scale(1, 0.1);
    let g = ctx.createRadialGradient(0, 0, 0, 0, 0, 64 * u);
    g.addColorStop(0, 'rgba(4,2,10,0.9)');
    g.addColorStop(0.72, 'rgba(4,2,10,0.6)');
    g.addColorStop(1, 'rgba(4,2,10,0)');
    ctx.fillStyle = g;
    ctx.beginPath();
    ctx.arc(0, 0, 64 * u, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();

    // 2. Pneus nos cantos
    [[0.6, 11.2], [88.8, 99.4]].forEach(([a, b]) => {
      ctx.fillStyle = vgrad(ctx, S, 13.5, 0, [[0, '#1d1a28'], [1, '#0c0a12']]);
      caixa(ctx, S, a, 0, b, 13.5, 2.8);
      ctx.fill();
      ctx.strokeStyle = '#2c2938';
      ctx.lineWidth = 0.5 * u;
      for (let k = 0; k < 4; k++) linha(ctx, S, [[a + 1.5, 2.3 + k * 2.7], [b - 1.5, 2.3 + k * 2.7]], '#2c2938', 0.5);
      const borda = a < 50 ? a + 0.7 : b - 0.7;
      linha(ctx, S, [[borda, 11.5], [borda, 2]], 'rgba(255,150,120,0.3)', 0.45);
    });

    // 3. Cockpit (interior, banco, volante) — fica atrás do painel e da moldura
    ctx.save();
    caminho(ctx, S, MOLDURA_INT);
    ctx.clip();
    ctx.fillStyle = vgrad(ctx, S, 58, 33, [[0, '#1d1a38'], [0.6, '#141128'], [1, '#0a0815']]);
    ctx.fillRect(X(15), Y(62), 70 * u, 32 * u);
    ctx.fillStyle = vgrad(ctx, S, 56, 36, [[0, '#28234a'], [1, '#17142c']]);
    caixa(ctx, S, 36, 36, 64, 55.5, 5);
    ctx.fill();
    ctx.fillStyle = 'rgba(120,105,190,0.12)';
    caixa(ctx, S, 37.5, 52.5, 62.5, 55, 1.2);
    ctx.fill();
    // encaixe do motorista (Echo oficial), entre o banco e o volante
    if (opts.interior) { ctx.save(); opts.interior(ctx, S); ctx.restore(); }
    // coluna de direção e volante
    ctx.fillStyle = '#0d0b15';
    caixa(ctx, S, 48.4, 33.5, 51.6, 37.5, 0.6);
    ctx.fill();
    ctx.save();
    ctx.translate(X(50), Y(38.2));
    ctx.scale(1, 0.3);
    ctx.lineWidth = 2.5 * u;
    ctx.strokeStyle = '#211e2e';
    ctx.beginPath();
    ctx.arc(0, 0, 12 * u, 0, Math.PI * 2);
    ctx.stroke();
    ctx.lineWidth = 0.7 * u;
    ctx.strokeStyle = '#6f6a8c';
    ctx.beginPath();
    ctx.arc(0, 0, 12.6 * u, Math.PI * 1.06, Math.PI * 1.94);
    ctx.stroke();
    ctx.fillStyle = '#2e2b3e';
    ctx.beginPath();
    ctx.arc(0, 0, 3.2 * u, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
    // encaixe das mãos do motorista sobre o volante
    if (opts.maos) { ctx.save(); opts.maos(ctx, S); ctx.restore(); }
    // reflexos discretos no vidro (não podem esconder o Echo depois)
    ctx.fillStyle = 'rgba(255,255,255,0.055)';
    caminho(ctx, S, [[23, 34.6], [29, 34.6], [45, 57.8], [39, 57.8]]);
    ctx.fill();
    ctx.fillStyle = 'rgba(255,200,230,0.04)';
    caminho(ctx, S, [[31, 34.6], [33, 34.6], [49, 57.8], [47, 57.8]]);
    ctx.fill();
    ctx.restore();

    // 4. Painel na base do para-brisa
    ctx.fillStyle = '#1a0c17';
    caminho(ctx, S, [[18, 33.6], [82, 33.6], [80.4, 36], [19.6, 36]]);
    ctx.fill();

    // 5. Moldura do para-brisa (luz do pôr do sol vindo de trás do carro)
    ctx.beginPath();
    MOLDURA_EXT.forEach(([x, y], i) => (i ? ctx.lineTo(X(x), Y(y)) : ctx.moveTo(X(x), Y(y))));
    ctx.closePath();
    MOLDURA_INT.forEach(([x, y], i) => (i ? ctx.lineTo(X(x), Y(y)) : ctx.moveTo(X(x), Y(y))));
    ctx.closePath();
    ctx.fillStyle = vgrad(ctx, S, 61, 33.4, [[0, '#f24a40'], [0.5, '#d82b34'], [1, '#b01c2b']]);
    ctx.fill('evenodd');
    linha(ctx, S, [[17.5, 33.8], [28.3, 60.8], [71.7, 60.8], [82.5, 33.8]], 'rgba(255,182,135,0.9)', 0.55);
    ctx.strokeStyle = 'rgba(80,6,22,0.9)';
    ctx.lineWidth = 0.5 * u;
    caminho(ctx, S, MOLDURA_INT);
    ctx.stroke();

    // 6. Retrovisores presos na base das colunas
    [[[17.8, 35.2], [12.6, 36.6], 6.4, 15.6], [[82.2, 35.2], [87.4, 36.6], 84.4, 93.6]].forEach(([base, ponta, a, b]) => {
      linha(ctx, S, [base, ponta], '#8c1523', 1.3);
      ctx.fillStyle = vgrad(ctx, S, 41.6, 35.2, [[0, '#f44b41'], [0.6, '#d22733'], [1, '#9c1727']]);
      caixa(ctx, S, a, 35.2, b, 41.6, 2);
      ctx.fill();
      linha(ctx, S, [[a + 1.4, 41.5], [b - 1.4, 41.5]], 'rgba(255,182,135,0.85)', 0.5);
      ctx.fillStyle = 'rgba(60,6,18,0.5)';
      caixa(ctx, S, a + 0.9, 35.7, b - 0.9, 37, 0.6);
      ctx.fill();
    });

    // 7. Capô
    caminho(ctx, S, CAPO);
    ctx.fillStyle = vgrad(ctx, S, 33.8, 25.6, [[0, '#c9232f'], [0.55, '#e3393a'], [1, '#f5584b']]);
    ctx.fill();
    ctx.save();
    caminho(ctx, S, CAPO);
    ctx.clip();
    ctx.fillStyle = 'rgba(255,185,205,0.12)';
    caminho(ctx, S, [[24, 25.6], [46, 25.6], [55, 33.8], [38, 33.8]]);
    ctx.fill();
    ctx.restore();
    // vincos dos para-lamas
    [[[24, 25.9], [21.2, 33.6]], [[76, 25.9], [78.8, 33.6]]].forEach(([p, q]) => {
      linha(ctx, S, [p, q], 'rgba(110,8,26,0.55)', 0.45);
      const d = p[0] < 50 ? 0.7 : -0.7;
      linha(ctx, S, [[p[0] + d, p[1]], [q[0] + d, q[1]]], 'rgba(255,150,150,0.28)', 0.3);
    });
    // entrada de ar do capô
    ctx.fillStyle = '#28070f';
    caixa(ctx, S, 37.5, 28.2, 62.5, 30.9, 0.9);
    ctx.fill();
    linha(ctx, S, [[38.3, 28.3], [61.7, 28.3]], 'rgba(255,125,125,0.4)', 0.3);
    for (let k = 1; k < 5; k++) linha(ctx, S, [[37.5 + k * 5, 28.6], [37.5 + k * 5, 30.5]], 'rgba(0,0,0,0.35)', 0.35);

    // 8. Frente (fáscia)
    caminho(ctx, S, FRENTE);
    ctx.fillStyle = vgrad(ctx, S, 25.6, 3.4, [[0, '#ee3f38'], [0.4, '#d6262f'], [0.75, '#b81b2a'], [1, '#8a1321']]);
    ctx.fill();
    // laterais dos para-lamas (chanfro escuro) e quina iluminada
    [[[0.9, 14], [0.9, 22.6], [2.4, 25.6], [5.2, 24.4], [4.4, 14.6], [2.2, 12.6]]].forEach((lado) => {
      [lado, espelho(lado)].forEach((pts) => {
        caminho(ctx, S, pts);
        ctx.fillStyle = 'rgba(115,9,27,0.6)';
        ctx.fill();
      });
    });
    linha(ctx, S, [[2.6, 25.55], [97.4, 25.55]], 'rgba(255,165,145,0.95)', 0.5);
    // faixa clara logo abaixo do capô
    ctx.fillStyle = 'rgba(255,120,110,0.18)';
    caixa(ctx, S, 4, 23.2, 96, 25.4, 0.6);
    ctx.fill();

    // 9. Faróis retangulares âmbar (grandes, em nicho escuro)
    [[3.2, 23.6], [76.4, 96.8]].forEach(([a, b]) => {
      ctx.fillStyle = '#2d0816';
      caixa(ctx, S, a, 14.4, b, 22.4, 1.4);
      ctx.fill();
      ctx.save();
      ctx.shadowColor = 'rgba(255,176,60,0.95)';
      ctx.shadowBlur = 6 * u;
      ctx.fillStyle = vgrad(ctx, S, 21.4, 15.4, [[0, '#fff4c6'], [0.42, '#ffd658'], [1, '#f6921e']]);
      caixa(ctx, S, a + 1.1, 15.4, b - 1.1, 21.4, 1);
      ctx.fill();
      ctx.restore();
      ctx.fillStyle = 'rgba(255,255,240,0.9)';
      caixa(ctx, S, a + 2.4, 20.1, b - 2.4, 20.8, 0.3);
      ctx.fill();
      linha(ctx, S, [[(a + b) / 2, 15.7], [(a + b) / 2, 21.1]], 'rgba(170,80,10,0.3)', 0.35);
    });

    // 10. Entradas de ar inferiores, piscas e para-choque
    ctx.fillStyle = '#220810';
    caixa(ctx, S, 11, 6.2, 89, 12.6, 1.2);
    ctx.fill();
    ctx.fillStyle = vgrad(ctx, S, 12.6, 6.2, [[0, '#c42330'], [1, '#8f1523']]);
    [[25.2, 27.2], [72.8, 74.8]].forEach(([a, b]) => { caixa(ctx, S, a, 6.2, b, 12.6, 0.3); ctx.fill(); });
    for (let k = 0; k < 3; k++) linha(ctx, S, [[28.5, 7.8 + k * 1.6], [71.5, 7.8 + k * 1.6]], 'rgba(255,90,90,0.16)', 0.35);
    [[13, 22.5], [77.5, 87]].forEach(([a, b]) => {
      ctx.save();
      ctx.shadowColor = 'rgba(255,150,40,0.8)';
      ctx.shadowBlur = 2 * u;
      ctx.fillStyle = '#ffa634';
      caixa(ctx, S, a, 10.2, b, 11.6, 0.5);
      ctx.fill();
      ctx.restore();
    });
    ctx.fillStyle = vgrad(ctx, S, 5.6, 2.6, [[0, '#302a3a'], [1, '#110e17']]);
    caixa(ctx, S, 10.2, 2.6, 89.8, 5.6, 1.2);
    ctx.fill();
    linha(ctx, S, [[11.6, 5.5], [88.4, 5.5]], 'rgba(170,150,200,0.35)', 0.3);

    // 11. Guia opcional do tamanho do Echo (desligado por padrão)
    if (opts.guiaEcho) {
      ctx.setLineDash([2 * u, 1.4 * u]);
      ctx.strokeStyle = 'rgba(0,240,255,0.85)';
      ctx.lineWidth = 0.4 * u;
      caixa(ctx, S, 35, 42, 65, 62, 8);
      ctx.stroke();
      [38.5, 61.5].forEach((x) => {
        ctx.beginPath();
        ctx.arc(X(x), Y(38.4), 3 * u, 0, Math.PI * 2);
        ctx.stroke();
      });
      ctx.setLineDash([]);
    }
    ctx.restore();
  }

  // Poças de luz dos faróis no asfalto à frente do carro e reflexo vermelho da carroceria.
  function luz(ctx, cx, chaoY, largura, opts) {
    const S = sistema(cx, chaoY, largura, opts || {});
    const { u, X, Y } = S;
    ctx.save();
    ctx.globalCompositeOperation = 'lighter';
    [[13.4, -1], [86.6, 1]].forEach(([x, lado]) => {
      ctx.save();
      ctx.translate(X(x + lado * 6), Y(-9));
      ctx.scale(1, 0.26);
      const g = ctx.createRadialGradient(0, 0, 0, 0, 0, 30 * u);
      g.addColorStop(0, 'rgba(255,185,90,0.30)');
      g.addColorStop(0.5, 'rgba(255,150,60,0.11)');
      g.addColorStop(1, 'rgba(255,120,40,0)');
      ctx.fillStyle = g;
      ctx.beginPath();
      ctx.arc(0, 0, 30 * u, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();
    });
    ctx.save();
    ctx.translate(X(50), Y(-2));
    ctx.scale(1, 0.12);
    const gr = ctx.createRadialGradient(0, 0, 0, 0, 0, 54 * u);
    gr.addColorStop(0, 'rgba(225,40,60,0.22)');
    gr.addColorStop(1, 'rgba(225,40,60,0)');
    ctx.fillStyle = gr;
    ctx.beginPath();
    ctx.arc(0, 0, 54 * u, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
    ctx.restore();
  }

  window.CarroFrente = { draw, luz };
})();
