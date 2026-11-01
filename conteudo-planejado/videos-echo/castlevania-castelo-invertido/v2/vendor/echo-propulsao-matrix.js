/**
 * echo-propulsao-matrix.js - Propulsão Matrix do Echo (3 jatos de letras verdes sob a base do corpo),
 * copiada de public/assets/js/site-home-flow.js ("Sistema de Propulsão Matrix Cadente", FEAT-015).
 * No site as letras são sorteadas a cada quadro; aqui cada letra é calculada pelo tempo (mesmo T, mesmo quadro),
 * para o render em segmentos dar sempre o mesmo resultado. Caracteres, cores, tamanhos, velocidades e
 * vida das letras são os do site; as coordenadas são as do espaço 280x280 do robô, na escala k.
 *
 * desenhar(ctx, { x, y, k, sx, sy, rot, intensidade, T })
 *   x, y = base do corpo (centro inferior) em px; k = escala do robô; sx, sy, rot = do motor
 *   intensidade 0..1 = quantas letras e com que força (0 = desligada)
 */
(function (window) {
  'use strict';

  const MATRIX_CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ<>/{}$#*+=!?:|';
  const NOZZLES = [-24, 0, 24];        // 3 jatos independentes sob o corpo
  const POR_JATO = 15;                 // 45 partículas no total, como no site
  const VELOCIDADE = 1.6;              // speedFactor do site com boost 1

  // número pseudoaleatório fixo para (jato, partícula, ciclo, campo)
  function h(a, b, c, d) {
    const x = Math.sin(a * 127.1 + b * 311.7 + c * 74.7 + d * 19.3) * 43758.5453;
    return x - Math.floor(x);
  }

  function desenhar(ctx, o) {
    const forca = Math.max(0, Math.min(1, o.intensidade || 0));
    if (forca <= 0.01) return;
    ctx.save();
    ctx.translate(o.x, o.y);
    ctx.scale(o.k * (o.sx || 1), o.k * (o.sy || 1));
    ctx.translate(0, -100);
    ctx.rotate(o.rot || 0);
    ctx.translate(0, 100);
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    const ativos = Math.max(1, Math.round(POR_JATO * forca));
    const vel = VELOCIDADE * (0.4 + 0.6 * forca);   // na ignição os jatos saem curtos e crescem
    for (let n = 0; n < NOZZLES.length; n++) {
      for (let i = 0; i < ativos; i++) {
        const vida = 0.46 + h(n, i, 0, 1) * 0.24;
        const tt = o.T + h(n, i, 0, 2) * vida;
        const ciclo = Math.floor(tt / vida);
        const idade = tt - ciclo * vida;
        const progress = idade / vida;
        const x = NOZZLES[n] + (h(n, i, ciclo, 3) - 0.5) * 5;
        const y = 2 + h(n, i, ciclo, 4) * 4 + (95 + h(n, i, ciclo, 5) * 65) * vel * idade;
        const size = 13 + h(n, i, ciclo, 6) * 3.5;
        const ch = MATRIX_CHARS[Math.floor(h(n, i, ciclo, 7 + Math.floor(idade / 0.2)) * MATRIX_CHARS.length)];
        ctx.font = `bold ${Math.round(size)}px "Courier New", Consolas, monospace`;
        if (progress < 0.16) {
          // ignição: branco brilhante com brilho néon Matrix
          ctx.fillStyle = `rgba(255, 255, 255, ${(progress / 0.16) * forca})`;
          ctx.shadowColor = '#00ff66';
          ctx.shadowBlur = 3;
        } else if (progress < 0.55) {
          // corpo do jato: verde néon
          ctx.fillStyle = `rgba(0, 255, 102, ${(1.0 - (progress - 0.16) * 0.35) * forca})`;
          ctx.shadowColor = '#00ff66';
          ctx.shadowBlur = 2;
        } else {
          // cauda: verde digital sumindo até zero
          ctx.fillStyle = `rgba(34, 197, 94, ${Math.max(0, (1.0 - progress) / 0.45 * 0.75) * forca})`;
          ctx.shadowBlur = 0;
        }
        ctx.fillText(ch, x, y);
      }
    }
    ctx.restore();
  }

  window.EchoPropulsaoMatrix = { desenhar };
})(window);
