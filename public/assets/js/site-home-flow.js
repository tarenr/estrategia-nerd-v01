/* FEAT-015 — Prévia do circuito contínuo: feixes de luz da homepage.
 *
 * Duas camadas, um só ciclo de animação:
 * - Fundo da página (canvas fixo atrás de tudo): um feixe distante, largo e
 *   desfocado, mais a luz ambiente. A forma muda devagar com a rolagem, então
 *   nada é cortado quando a página desce.
 * - Hero (canvas do Hero): o feixe principal, dezenas de fios finos de luz com
 *   pulsos de dados, que nasce no alto e sai pela base (a Parte 2 continua dali).
 *
 * Renderizador WebGL2 com brilho gaussiano e mistura aditiva, sem biblioteca.
 * Sem WebGL2 ou com erro: só o fundo escuro. Com movimento reduzido: um quadro
 * parado. O conteúdo da página nunca depende deste script.
 */
(function () {
  'use strict';

  const hero = document.querySelector('[data-flow-hero]');
  const flowCanvas = document.querySelector('[data-flow-path]');
  const bgCanvas = document.querySelector('[data-flow-bg]');
  if (!flowCanvas && !bgCanvas) return;

  const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Abertura: no primeiro ~1,1 s o feixe acende da origem até a base e a luz de
  // fundo sobe de apagada ao normal, junto com a entrada dos textos. Conta só o
  // tempo efetivamente renderizado: um cálculo pesado de layout não pode fazer a
  // abertura saltar diretamente para o fim.
  const INTRO_MS = 1100;
  let introElapsed = 0;
  const introK = () => (reduceMotion ? 1 : 1 - Math.pow(1 - Math.min(1, introElapsed / INTRO_MS), 3));

  // Textos do Hero em rede lenta: a entrada animada (site-hero-preview.js) só roda
  // quando a página inteira termina de ser lida. Se em 1,2 s ela não começou, os
  // textos aparecem com fade e o estado final fica travado (a entrada atrasada
  // não dá "tranco" depois). Na conexão normal a entrada começa antes e nada muda.
  if (hero && !reduceMotion) {
    window.setTimeout(() => {
      const items = Array.from(hero.querySelectorAll('[data-hero-animate]'));
      const started = items.some((el) => el.style.opacity !== '');
      if (items.length && !started) hero.classList.add('en-flow-texts-ready');
    }, 1200);
  }
  const CYAN = [0.133, 0.827, 0.933];
  const BLUE = [0.231, 0.51, 0.965];
  const VIOLET = [0.659, 0.333, 0.969];
  const WHITE = [0.86, 0.97, 1];

  // Amostras por curva (padrão; o feixe principal usa as suas, proporcionais ao caminho).
  const SAMPLES = 96;
  const FLOATS = 8;
  const MAX_VERTS = 160000;

  // ---------- Renderizador WebGL2 (brilho gaussiano + mistura aditiva) ----------
  const VERT = `#version 300 es
in vec2 a_pos; in vec2 a_local; in float a_int; in vec3 a_col;
uniform vec2 u_res;
out vec2 v_local; out float v_int; out vec3 v_col;
void main() {
  vec2 c = a_pos / u_res * 2.0 - 1.0;
  gl_Position = vec4(c.x, -c.y, 0.0, 1.0);
  v_local = a_local; v_int = a_int; v_col = a_col;
}`;
  const FRAG = `#version 300 es
precision mediump float;
in vec2 v_local; in float v_int; in vec3 v_col;
out vec4 outColor;
void main() {
  float d = dot(v_local, v_local);
  float a = (exp(-d * 9.0) + exp(-d * 2.4) * 0.32) * v_int;
  outColor = vec4(v_col * a, a);
}`;

  function createRenderer(canvas) {
    const gl = canvas.getContext('webgl2', { alpha: true, antialias: false, premultipliedAlpha: true });
    if (!gl) return null;

    const compile = (type, source) => {
      const shader = gl.createShader(type);
      gl.shaderSource(shader, source);
      gl.compileShader(shader);
      if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) throw new Error(gl.getShaderInfoLog(shader) || 'shader');
      return shader;
    };
    const program = gl.createProgram();
    gl.attachShader(program, compile(gl.VERTEX_SHADER, VERT));
    gl.attachShader(program, compile(gl.FRAGMENT_SHADER, FRAG));
    gl.linkProgram(program);
    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) throw new Error(gl.getProgramInfoLog(program) || 'program');
    gl.useProgram(program);

    const data = new Float32Array(MAX_VERTS * FLOATS);
    const buffer = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
    gl.bufferData(gl.ARRAY_BUFFER, data.byteLength, gl.DYNAMIC_DRAW);
    const stride = FLOATS * 4;
    [['a_pos', 2, 0], ['a_local', 2, 2], ['a_int', 1, 4], ['a_col', 3, 5]].forEach(([name, size, offset]) => {
      const loc = gl.getAttribLocation(program, name);
      gl.enableVertexAttribArray(loc);
      gl.vertexAttribPointer(loc, size, gl.FLOAT, false, stride, offset * 4);
    });
    const uRes = gl.getUniformLocation(program, 'u_res');
    gl.enable(gl.BLEND);
    gl.blendFunc(gl.ONE, gl.ONE);

    let count = 0;
    const vert = (x, y, lx, ly, intensity, c) => {
      if (count >= MAX_VERTS) return;
      const o = count * FLOATS;
      data[o] = x; data[o + 1] = y; data[o + 2] = lx; data[o + 3] = ly;
      data[o + 4] = intensity; data[o + 5] = c[0]; data[o + 6] = c[1]; data[o + 7] = c[2];
      count += 1;
    };

    return {
      canvas,
      begin() { count = 0; },
      glow(x, y, r, intensity, c) {
        vert(x - r, y - r, -1, -1, intensity, c); vert(x + r, y - r, 1, -1, intensity, c); vert(x - r, y + r, -1, 1, intensity, c);
        vert(x + r, y - r, 1, -1, intensity, c); vert(x + r, y + r, 1, 1, intensity, c); vert(x - r, y + r, -1, 1, intensity, c);
      },
      // Faixa de luz por pontos, com intensidade (multiplicada por scale) e cor por ponto.
      // halfWidth: número ou largura por ponto.
      strip(xs, ys, halfWidth, intensities, scale, colors, from, to) {
        let prevL = null;
        let prevR = null;
        let prevI = 0;
        let prevC = null;
        for (let i = from; i <= to; i += 1) {
          const a = Math.max(from, i - 1);
          const b = Math.min(to, i + 1);
          let dx = xs[b] - xs[a];
          let dy = ys[b] - ys[a];
          const len = Math.hypot(dx, dy) || 1;
          dx /= len; dy /= len;
          const hw = typeof halfWidth === 'number' ? halfWidth : halfWidth[i];
          const l = [xs[i] - dy * hw, ys[i] + dx * hw];
          const r = [xs[i] + dy * hw, ys[i] - dx * hw];
          const it = intensities[i] * scale;
          const c = colors[i];
          if (prevL) {
            vert(prevL[0], prevL[1], -1, 0, prevI, prevC); vert(prevR[0], prevR[1], 1, 0, prevI, prevC); vert(l[0], l[1], -1, 0, it, c);
            vert(prevR[0], prevR[1], 1, 0, prevI, prevC); vert(r[0], r[1], 1, 0, it, c); vert(l[0], l[1], -1, 0, it, c);
          }
          prevL = l; prevR = r; prevI = it; prevC = c;
        }
      },
      flush() {
        gl.viewport(0, 0, canvas.width, canvas.height);
        gl.clearColor(0, 0, 0, 0);
        gl.clear(gl.COLOR_BUFFER_BIT);
        if (!count) return;
        gl.uniform2f(uRes, canvas.width, canvas.height);
        gl.bufferSubData(gl.ARRAY_BUFFER, 0, data, 0, count * FLOATS);
        gl.drawArrays(gl.TRIANGLES, 0, count);
      },
    };
  }

  function safeRenderer(canvas) {
    if (!canvas) return null;
    try {
      return createRenderer(canvas);
    } catch (error) {
      if (window.console) console.warn('[site-home-flow] camada desativada:', error);
      return null;
    }
  }

  // ---------- Feixe: curva central + fios que torcem e ondulam ----------
  const mix = (a, b, t) => [a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t, a[2] + (b[2] - a[2]) * t];

  // Cor ao longo do feixe: ciano -> azul -> violeta.
  function colorAt(t) {
    return t < 0.5 ? mix(CYAN, BLUE, t * 2) : mix(BLUE, VIOLET, (t - 0.5) * 2);
  }

  function catmull(p0, p1, p2, p3, t) {
    const t2 = t * t;
    const t3 = t2 * t;
    return 0.5 * ((2 * p1) + (-p0 + p2) * t + (2 * p0 - 5 * p1 + 4 * p2 - p3) * t2 + (-p0 + 3 * p1 - 3 * p2 + p3) * t3);
  }

  // n: amostras da curva (o feixe principal, que percorre a página, usa bem mais).
  function createBeam(count, n = SAMPLES) {
    const cx = new Float32Array(n + 1);
    const cy = new Float32Array(n + 1);
    const nx = new Float32Array(n + 1);
    const ny = new Float32Array(n + 1);
    const strands = [];
    for (let i = 0; i < count; i += 1) {
      strands.push({
        lane: count > 1 ? i / (count - 1) - 0.5 : 0,
        phase: Math.random() * Math.PI * 2,
        speed: 0.35 + Math.random() * 0.5,
        wobble: 0.15 + Math.random() * 0.35,
        bright: 0.55 + Math.random() * 0.45,
        hue: Math.random() * 0.18 - 0.09,
        xs: new Float32Array(n + 1),
        ys: new Float32Array(n + 1),
      });
    }

    return {
      n,
      cx,
      cy,
      strands,
      // path: pontos de controle já em px do canvas.
      setPath(path) {
        const segs = path.length - 1;
        for (let s = 0; s <= n; s += 1) {
          const u = (s / n) * segs;
          const k = Math.min(segs - 1, Math.floor(u));
          const t = u - k;
          const p0 = path[Math.max(0, k - 1)];
          const p1 = path[k];
          const p2 = path[k + 1];
          const p3 = path[Math.min(path.length - 1, k + 2)];
          cx[s] = catmull(p0[0], p1[0], p2[0], p3[0], t);
          cy[s] = catmull(p0[1], p1[1], p2[1], p3[1], t);
        }
        for (let s = 0; s <= n; s += 1) {
          const a = Math.max(0, s - 1);
          const b = Math.min(n, s + 1);
          let dx = cx[b] - cx[a];
          let dy = cy[b] - cy[a];
          const len = Math.hypot(dx, dy) || 1;
          dx /= len; dy /= len;
          nx[s] = -dy;
          ny[s] = dx;
        }
      },
      // Abre no meio e afina nas pontas (fita), com torção e ondulação no tempo.
      // widths (opcional): largura por amostra, no lugar do formato de fita.
      // tScale (opcional): quantas "fitas do Hero" cabem no caminho, para a torção
      // e a ondulação seguirem a distância percorrida e não a fração do caminho.
      place(time, spreadPx, widths, tScale = 1) {
        strands.forEach((strand) => {
          for (let s = 0; s <= n; s += 1) {
            const t = s / n;
            const spread = widths ? widths[s] : spreadPx * (0.35 + 0.9 * Math.sin(Math.PI * t));
            const tt = t * tScale;
            const twist = Math.cos(tt * 5.2 - time * 0.42 + strand.lane * 0.9);
            const wave = Math.sin(tt * 9 + time * strand.speed + strand.phase) * strand.wobble;
            const offset = spread * (strand.lane * (0.55 + 0.45 * twist) + wave * 0.18);
            strand.xs[s] = cx[s] + nx[s] * offset;
            strand.ys[s] = cy[s] + ny[s] * offset;
          }
        });
      },
    };
  }

  const MAX_SAMPLES = 900;
  const intensity = new Float32Array(MAX_SAMPLES + 1);
  const colors = new Array(MAX_SAMPLES + 1);
  // style: { haze, hazeWidth, glowWidth, glow, coreWidth, core, hueShift, hueRange, flowSpeed, fadeBelow }
  // fadeBelow (px do canvas): abaixo desta altura o feixe se dissolve até sumir na borda.
  function fadeAt(y, style) {
    if (!style.fadeBelow) return 1;
    const k = (y - style.fadeBelow) / style.fadeSpan;
    return k <= 0 ? 1 : Math.max(0, 1 - k * k * (3 - 2 * Math.min(1, k)));
  }

  // reveal (0..1): até onde o feixe já acendeu, da origem (t = 0) até a ponta.
  const revealAt = (t, style) => (style.reveal === undefined ? 1 : Math.min(1, Math.max(0, (style.reveal * 1.2 - t) / 0.2)));

  // style extra (opcional): mask (por amostra), flatBase (sem o formato de fita no
  // brilho), tScale (efeitos pela distância), hueCycles (cor ciano↔violeta repetindo
  // ao longo do caminho), from/to (desenhar só esse intervalo de amostras).
  function drawBeam(renderer, beam, time, style) {
    const n = beam.n;
    const from = style.from || 0;
    const to = style.to === undefined ? n : style.to;
    const tScale = style.tScale || 1;
    const hueAt = (t) => (style.hueCycles ? 0.5 - 0.5 * Math.cos(Math.PI * 2 * t * style.hueCycles) : Math.min(1, t * style.hueRange + style.hueShift));
    for (let s = from; s <= to; s += 1) {
      const t = s / n;
      const base = style.flatBase ? 1 : 0.5 + 0.5 * Math.sin(Math.PI * t);
      intensity[s] = base * fadeAt(beam.cy[s], style) * revealAt(t, style) * (style.mask ? style.mask[s] : 1);
      colors[s] = colorAt(hueAt(t));
    }
    if (style.haze > 0) renderer.strip(beam.cx, beam.cy, style.hazeWidth, intensity, style.haze, colors, from, to);

    beam.strands.forEach((strand) => {
      for (let s = from; s <= to; s += 1) {
        const t = s / n;
        // Brilho que corre ao longo do fio (sensação de dados passando).
        const flow = 0.55 + 0.45 * Math.sin(t * tScale * 7 - time * style.flowSpeed * strand.speed + strand.phase);
        intensity[s] = strand.bright * flow * fadeAt(strand.ys[s], style) * revealAt(t, style) * (style.mask ? style.mask[s] : 1);
        colors[s] = colorAt(Math.min(1, Math.max(0, hueAt(t) + strand.hue)));
      }
      renderer.strip(strand.xs, strand.ys, style.glowWidth, intensity, style.glow, colors, from, to);
      if (style.core > 0) renderer.strip(strand.xs, strand.ys, style.coreWidth, intensity, style.core, colors, from, to);
    });
  }
  // ---------- Camada de fundo: feixe distante + luz ambiente, seguindo a rolagem ----------
  function createBackgroundLayer(renderer) {
    // Formas do feixe distante (frações da tela) no topo, no meio e no fim da
    // página; entre elas a forma é interpolada pela rolagem. Diagonal oposta à
    // do feixe principal para dar profundidade.
    const KEYS = [
      [[-0.2, 0.95], [0.18, 0.66], [0.45, 0.42], [0.78, 0.2], [1.2, -0.05]],
      [[-0.2, 0.15], [0.22, 0.38], [0.52, 0.56], [0.82, 0.74], [1.2, 0.95]],
      [[-0.2, 0.7], [0.25, 0.42], [0.58, 0.6], [0.86, 0.38], [1.2, 0.55]],
    ];
    let beam = null;
    let width = 1;
    let height = 1;
    let dpr = 1;
    let progress = 0;
    let smooth = 0;
    // Véu: abaixo do Hero a luz fica ~30% mais apagada, igual em todas as seções.
    let gainTarget = 1;
    let gain = 1;

    const smoothstep = (t) => t * t * (3 - 2 * t);

    function readProgress() {
      const max = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
      progress = Math.min(1, Math.max(0, window.scrollY / max));
      const heroH = hero ? hero.offsetHeight : window.innerHeight;
      const k = Math.min(1, Math.max(0, window.scrollY / Math.max(1, heroH)));
      gainTarget = 1 - 0.3 * smoothstep(k);
    }

    function pathAt(p, time) {
      const span = (KEYS.length - 1) * p;
      const k = Math.min(KEYS.length - 2, Math.floor(span));
      const t = smoothstep(span - k);
      return KEYS[k].map((a, i) => {
        const b = KEYS[k + 1][i];
        const sway = i === 0 || i === KEYS[k].length - 1 ? 0 : 1;
        return [
          (a[0] + (b[0] - a[0]) * t + sway * 0.03 * Math.sin(time * 0.11 + i * 1.3)) * width * dpr,
          (a[1] + (b[1] - a[1]) * t + sway * 0.04 * Math.sin(time * 0.09 + i * 2.1)) * height * dpr,
        ];
      });
    }

    function resize() {
      width = Math.max(1, window.innerWidth);
      height = Math.max(1, window.innerHeight);
      // É um plano desfocado: meia resolução basta e economiza processamento.
      dpr = Math.min(window.devicePixelRatio || 1, 1) * 0.6;
      if (!beam) beam = createBeam(width < 768 ? 8 : 14);
      renderer.canvas.width = Math.round(width * dpr);
      renderer.canvas.height = Math.round(height * dpr);
      readProgress();
    }

    function draw(time) {
      renderer.begin();
      beam.setPath(pathAt(smooth, time));
      const spreadPx = 0.32 * Math.min(width, height * 1.6) * dpr;
      // Tempo mais lento que o feixe principal: o plano de trás se move menos.
      const slow = time * 0.35;
      beam.place(slow, spreadPx);

      // Luz ambiente: halos grandes ao longo do feixe distante, respirando devagar.
      const breathe = 0.75 + 0.25 * Math.sin(time * 0.45);
      const radius = 0.3 * Math.min(width, height) * dpr;
      for (let s = 6; s < beam.n; s += 14) {
        renderer.glow(beam.cx[s], beam.cy[s], radius, 0.05 * breathe * gain * introK(), colorAt(0.55 + 0.4 * (s / beam.n)));
      }

      drawBeam(renderer, beam, slow, {
        haze: 0.07 * gain * introK(), hazeWidth: spreadPx * 0.8, glowWidth: 22 * dpr, glow: 0.06 * gain * introK(),
        coreWidth: 3 * dpr, core: 0.09 * gain * introK(), hueShift: 0.45, hueRange: 0.55, flowSpeed: 0.8,
      });
      renderer.flush();
    }

    resize();
    window.addEventListener('scroll', readProgress, { passive: true });

    return {
      resize,
      frame(dt, time) {
        const ease = 1 - Math.exp(-dt * 2.5);
        smooth += (progress - smooth) * ease;
        gain += (gainTarget - gain) * ease;
        draw(time);
      },
      still() { smooth = 0; gain = gainTarget; draw(0); },
    };
  }

  // ---------- Feixe principal: um caminho só, do alto do Hero ao logo do Rodapé ----------
  // Canvas fixo do tamanho da tela. A mesma fita de fios entra pelo alto do Hero,
  // atravessa o Hero, passa por trás do texto do Sobre e pelo vão dos pilares, e
  // segue pela página: ao lado dos blocos quando a lateral tem espaço (>= 60 px),
  // por trás deles quando não tem, cruzando de um lado para o outro no meio dos vãos
  // entre as seções (cantos de 45°, como trilha de placa) até o logo do Rodapé.
  // Torção, brilho e cor seguem a distância percorrida, então o Hero fica igual.
  // No Hero o feixe acende na abertura; abaixo dele é traçado pela rolagem na
  // primeira descida e nunca recua. Desenha só o trecho visível.
  function createFlowLayer(renderer) {
    // Trajeto no Hero em frações (x, y) e fator de largura (formato de fita).
    const HERO_PATH = {
      wide: [[1.12, -0.06, 0.35], [0.86, 0.2, 0.99], [0.66, 0.5, 1.25], [0.56, 0.78, 0.99], [0.52, 1.0, 0.5]],
      narrow: [[1.15, 0.02, 0.35], [0.9, 0.26, 0.99], [0.74, 0.55, 1.25], [0.62, 0.8, 0.99], [0.56, 1.0, 0.5]],
    };
    const N = 840;
    const narrowStart = window.innerWidth < 768;
    const beam = createBeam(narrowStart ? 18 : 40, N);
    const widths = new Float32Array(N + 1);
    const haze = new Float32Array(N + 1);
    const mask = new Float32Array(N + 1);
    const pulses = [];
    const heads = [];
    let ctrl = [];
    let riverW = null;
    let heroTop = 0;
    let heroBottom = 0;
    let heroH = 1;
    let fadeOutY = Infinity;
    let endY = Infinity;
    let totalLen = 1;
    let tScale = 1;
    let pen = 0;
    let penTarget = 0;
    let vw = 1;
    let vh = 1;
    let dpr = 1;
    let heroVisible = true;
    let pathInitialized = false;
    let initialTrace = true;
    let riverCacheSignature = '';
    let riverCache = null;
    // Versão das regras do rio: mudou o cálculo, mude aqui (o cache da sessão
    // guarda o caminho pronto e, sem isso, mostraria o rio antigo).
    const RIVER_VERSION = 'rio-2026-10-25b';
    const RIVER_CACHE_KEY = 'en-flow-river';

    // Posição no documento sem transformações (as entradas animadas usam transform).
    const docRect = (el) => {
      let x = 0;
      let y = 0;
      for (let n = el; n; n = n.offsetParent) { x += n.offsetLeft; y += n.offsetTop; }
      return { left: x, top: y, right: x + el.offsetWidth, bottom: y + el.offsetHeight, width: el.offsetWidth, height: el.offsetHeight };
    };

    // Controles que o feixe não cruza: campos, botões, formulário e links com cara
    // de botão. Só os visíveis na largura da página (slides fora do carrossel não).
    const CONTROLS = 'button, input, textarea, select, form, a[class*="px-"][class*="py-"], .site-footer-social-badge';
    function controlRects(top, pad) {
      const width = document.documentElement.clientWidth;
      const out = [];
      document.querySelectorAll(CONTROLS).forEach((el) => {
        if (hero.contains(el)) return;
        const q = el.getBoundingClientRect();
        if (q.width < 2 || q.height < 2 || q.right <= 0 || q.left >= width) return;
        // Posição final, sem o deslocamento das entradas animadas (transform).
        const d = docRect(el);
        if (d.bottom < top) return;
        out.push({ l: d.left - pad, t: d.top - pad, r: d.right + pad, b: d.bottom + pad });
      });
      return out;
    }

    // ---------- O rio ----------
    // Depois do Hero o feixe serpenteia de um lado ao outro da página, por trás do
    // conteúdo, como as voltas de um rio: vertical em cada volta (ápice) e meio
    // cosseno entre elas, com largura parecida com a do Hero (60% a 100%). Regras:
    // raio da curva >= 1,2x a largura (os fios só se dobrariam abaixo de 0,5x); a
    // largura muda no máximo 25% da largura local a cada 100 px; segue até o fim
    // da página e se dissolve. Botões e campos ficam fora: a volta mais próxima se
    // desloca até o feixe passar pelo lado livre; se não couber, ele estreita ali.
    // O feixe final é reamostrado em 840 pontos para renderização. O solucionador
    // de layout não precisa da mesma densidade: 8 px preservam as colisões e a
    // curvatura visual enquanto reduzem pela metade o trabalho na abertura.
    const RIVER_STEP = 8;

    // Amostras densas do rio (x, y) entre os nós: [saída do Hero, ápices..., fim da página].
    function riverSamples(knots, slope0) {
      const xs = [];
      const ys = [];
      for (let k = 0; k < knots.length - 1; k += 1) {
        const a = knots[k];
        const b = knots[k + 1];
        const h = b.y - a.y;
        for (let y = a.y + (k ? RIVER_STEP : 0); y <= b.y; y += RIVER_STEP) {
          const t = (y - a.y) / h;
          let x;
          if (k === 0) {
            // Saída do Hero: Hermite (inclinação do Hero até vertical no 1º ápice).
            const m = slope0 * h;
            x = (2 * t ** 3 - 3 * t ** 2 + 1) * a.x + (t ** 3 - 2 * t ** 2 + t) * m + (-2 * t ** 3 + 3 * t ** 2) * b.x;
          } else {
            x = a.x + (b.x - a.x) * (1 - Math.cos(Math.PI * t)) / 2;
          }
          xs.push(x);
          ys.push(y);
        }
      }
      return { xs, ys };
    }

    // Quantas amostras de borda do feixe caem dentro de controles (entre i0 e i1).
    function hitCount(xs, ys, ws, rects, i0, i1) {
      let n = 0;
      for (let i = Math.max(1, i0); i < Math.min(xs.length - 1, i1); i += 1) {
        const dx = xs[i + 1] - xs[i - 1];
        const dy = ys[i + 1] - ys[i - 1];
        const l = Math.hypot(dx, dy) || 1;
        const nx = -dy / l;
        const ny = dx / l;
        for (let q = -0.5; q <= 0.5; q += 0.125) {
          const px = xs[i] + nx * q * ws[i];
          const py = ys[i] + ny * q * ws[i];
          if (rects.some((r) => px > r.l && px < r.r && py > r.t && py < r.b)) { n += 1; break; }
        }
      }
      return n;
    }

    // Largura limitada pela taxa (25% da largura local a cada 100 px de caminho),
    // nos dois sentidos.
    function limitRate(ws, xs, ys) {
      const rate = (i) => Math.pow(1.25, Math.hypot(xs[i] - xs[i - 1], ys[i] - ys[i - 1]) / 100);
      for (let i = 1; i < ws.length; i += 1) ws[i] = Math.min(ws[i], ws[i - 1] * rate(i));
      for (let i = ws.length - 2; i >= 0; i -= 1) ws[i] = Math.min(ws[i], ws[i + 1] * rate(i + 1));
    }

    function buildRiver(h, last, slope0, startW, end, narrow, width, REF) {
      // Folga: cobre a pequena diferença entre o rio calculado e o desenhado.
      const pad = narrow ? 6 : 14;
      const rects = controlRects(h.bottom, pad);
      const xL = narrow ? width * 0.12 : width * 0.25;
      const xR = width - xL;
      const spacing = narrow ? 900 : 1400;
      // A 1ª volta fica longe o bastante para a saída do Hero (larga) curvar devagar.
      const first = narrow ? 650 : 1000;
      const tail = narrow ? 500 : 700;
      const yFirst = h.bottom + first;
      const yLast = end.y - tail;
      const nBase = Math.max(1, Math.round((yLast - yFirst) / spacing) + 1);
      // Começa à esquerda (o Hero sai para a esquerda) e alterna.
      const sideOf = (i) => (i % 2 === 0 ? -1 : 1);
      // Voltas de tamanhos um pouco diferentes, como num rio.
      const VARY = [0, 0.04, -0.05, 0.03, -0.03, 0.05, -0.04];

      // Raio local da curva (px) a partir das amostras.
      const radii = (xs, ys) => xs.map((_, i) => {
        if (i === 0 || i === xs.length - 1) return Infinity;
        const ax = xs[i] - xs[i - 1];
        const ay = ys[i] - ys[i - 1];
        const bx = xs[i + 1] - xs[i];
        const by = ys[i + 1] - ys[i];
        const cross = Math.abs(ax * by - ay * bx);
        const la = Math.hypot(ax, ay);
        const lb = Math.hypot(bx, by);
        const lc = Math.hypot(xs[i + 1] - xs[i - 1], ys[i + 1] - ys[i - 1]);
        return cross < 1e-6 ? Infinity : (la * lb * lc) / (2 * cross);
      });

      // Um rio com n voltas: posiciona, desvia dos controles e mede o resultado.
      function solve(n) {
        const step = n > 1 ? (yLast - yFirst) / (n - 1) : 0;
        const apex = [];
        for (let i = 0; i < n; i += 1) {
          apex.push({ x: sideOf(i) < 0 ? xL : xR, y: yFirst + i * step + (i > 0 && i < n - 1 ? VARY[i % VARY.length] * step : 0) });
        }
        // Depois da última volta o rio desce até sair pela base da página (reto ou
        // inclinado para dentro, se precisar desviar de algo no rodapé).
        let endShift = 0;
        const knotsOf = () => [{ x: last.x, y: last.y }].concat(apex, [{ x: apex[n - 1].x + endShift, y: end.y }]);

        // Largura-alvo, perto da do Hero: um pouco mais estreita nas voltas e
        // alternando trechos bem largos e médios entre elas. Interpolação em cosseno:
        // sem quinas na borda do feixe (viravam "cotovelos" nos fios).
        const targets = (ys) => {
          const keys = [{ y: last.y, w: startW }];
          apex.forEach((a, i) => {
            const prevY = i ? apex[i - 1].y : last.y;
            keys.push({ y: (prevY + a.y) / 2, w: REF * (i % 2 ? 0.95 : 0.78) });
            // No celular a volta fica na borda, ao lado de botões de largura quase
            // total: mais estreita ali (45%) para caber na margem.
            keys.push({ y: a.y, w: REF * (narrow ? 0.45 : 0.62) });
          });
          keys.push({ y: end.y, w: REF * 0.8 });
          let k = 1;
          return ys.map((y) => {
            while (k < keys.length - 1 && y > keys[k].y) k += 1;
            const a = keys[k - 1];
            const b = keys[k];
            const f = Math.min(1, Math.max(0, (y - a.y) / Math.max(1, b.y - a.y)));
            return a.w + (b.w - a.w) * (1 - Math.cos(Math.PI * f)) / 2;
          });
        };
        const widthsFor = (xs, ys) => {
          const ws = targets(ys);
          const rr = radii(xs, ys);
          // Raio suavizado (a medida ponto a ponto oscila) e largura <= raio / 1,4:
          // a regra é raio >= 1,2x a largura; a folga cobre o Catmull-Rom do feixe,
          // que fecha um pouco a curva entre os pontos de controle.
          for (let i = 0; i < ws.length; i += 1) {
            let r = Infinity;
            for (let j = Math.max(0, i - 6); j <= Math.min(ws.length - 1, i + 6); j += 1) r = Math.min(r, rr[j]);
            ws[i] = Math.min(ws[i], r / 1.4);
          }
          ws[0] = startW;
          limitRate(ws, xs, ys);
          return ws;
        };

        let path = riverSamples(knotsOf(), slope0);
        let ws = widthsFor(path.xs, path.ys);
        if (rects.length) {
          // Cada volta tenta deslocamentos na vertical (até ±360 px) e, no desktop,
          // também mais para dentro ou para fora; duas rodadas, a 2ª já com as outras ajustadas.
          // Em telas estreitas os botões ocupam quase toda a largura: a volta pode
          // encostar na borda (meio feixe fora da tela) para passar ao lado deles.
          const xMin = narrow ? 4 : width * 0.12;
          const dxs = narrow ? [0, -15, -30, 15] : [0, 60, -60, 120, -120];
          for (let round = 0; round < 2; round += 1) {
            for (let i = 0; i < n; i += 1) {
              const lo = i ? apex[i - 1].y : last.y;
              const hi = i < n - 1 ? apex[i + 1].y : end.y;
              const minGap = Math.max(step, spacing) * 0.6;
              const x0 = apex[i].x;
              const y0 = apex[i].y;
              const inward = x0 < width / 2 ? 1 : -1;
              const shifts = i === n - 1 ? (narrow ? [0, 60, 120, 180] : [0, 120, 240, 360]).map((s) => s * inward) : [endShift];
              let best = { dx: 0, dy: 0, shift: endShift, hits: Infinity };
              for (let d = 0; d <= (narrow ? 480 : 360) && best.hits > 0; d += 20) {
                for (const dy of d ? [d, -d] : [0]) {
                  const y = y0 + dy;
                  if (y - lo < minGap || hi - y < (i < n - 1 ? minGap : tail * 0.6)) continue;
                  for (const dx of dxs) {
                    const x = x0 + dx * inward;
                    if (x < xMin || x > width - xMin) continue;
                    for (const shift of shifts) {
                      apex[i].x = x;
                      apex[i].y = y;
                      endShift = shift;
                      const p = riverSamples(knotsOf(), slope0);
                      const w = widthsFor(p.xs, p.ys);
                      const i0 = Math.max(0, Math.floor((lo - last.y) / RIVER_STEP));
                      const i1 = Math.ceil((hi - last.y) / RIVER_STEP);
                      const hits = hitCount(p.xs, p.ys, w, rects, i0, i1);
                      if (hits < best.hits) best = { dx: dx * inward, dy, shift, hits };
                      if (!hits) break;
                    }
                    if (best.hits === 0) break;
                  }
                  if (best.hits === 0) break;
                }
              }
              apex[i].x = x0 + best.dx;
              apex[i].y = y0 + best.dy;
              endShift = best.shift;
            }
          }
          path = riverSamples(knotsOf(), slope0);
          ws = widthsFor(path.xs, path.ys);
        }
        // Quanto a curva já estreitou o feixe abaixo do alvo, e quantos encostos sobraram.
        const goal = targets(path.ys);
        let loss = 0;
        for (let i = 0; i < ws.length; i += 1) loss += Math.max(0, goal[i] - ws[i]) / goal[i];
        const hits = rects.length ? hitCount(path.xs, path.ys, ws, rects, 0, ws.length) : 0;
        return { path, ws, apex, hits, loss: loss / ws.length };
      }

      // Testa uma volta a menos, a conta normal e uma a mais: fica com o rio que
      // não encosta em nada e perde menos largura.
      let best = null;
      for (const n of [nBase, nBase - 1, nBase + 1]) {
        if (n < 1) continue;
        const r = solve(n);
        const cost = r.hits * 1000 + r.loss;
        if (!best || cost < best.cost) best = Object.assign(r, { cost });
      }
      const { path, apex } = best;
      const ws = best.ws;
      if (best.hits) {
        // O que ainda encostar: estreita ali (mínimo 20 px) e reaplica a taxa.
        const minW = 20;
        for (let pass = 0; pass < 14; pass += 1) {
          let changed = false;
          for (let i = 1; i < ws.length - 1; i += 1) {
            if (ws[i] > minW && hitCount(path.xs, path.ys, ws, rects, i, i + 1)) {
              ws[i] = Math.max(minW, ws[i] * 0.85);
              changed = true;
            }
          }
          if (!changed) break;
          limitRate(ws, path.xs, path.ys);
        }
      }
      return { xs: path.xs, ys: path.ys, ws, apexY: apex.map((a) => a.y) };
    }

    // Letra pequena (até 16 px) não segura a leitura só com a sombra quando o
    // feixe passa atrás dela: ali o rio fica mais fraco (40%), com
    // transição suave de ~60 px antes e depois.
    function smallTextDim(river, top) {
      const boxes = [];
      document.querySelectorAll('h1, h2, h3, h4, p').forEach((el) => {
        if (hero.contains(el) || parseFloat(getComputedStyle(el).fontSize) > 16) return;
        const q = el.getBoundingClientRect();
        if (q.width < 4 || q.height < 4) return;
        const d = docRect(el);
        if (d.bottom < top) return;
        boxes.push({ l: d.left, t: d.top, r: d.right, b: d.bottom });
      });
      const hit = river.xs.map((x, i) => {
        const y = river.ys[i];
        const half = river.ws[i] * 0.5;
        return boxes.some((b) => x + half > b.l && x - half < b.r && y > b.t - 8 && y < b.b + 8) ? 1 : 0;
      });
      const reach = Math.round(60 / RIVER_STEP);
      return hit.map((_, i) => {
        let d = 0;
        for (let j = Math.max(0, i - reach); j <= Math.min(hit.length - 1, i + reach); j += 1) {
          if (hit[j]) d = Math.max(d, 1 - Math.abs(j - i) / (reach + 1));
        }
        return 1 - 0.6 * d;
      });
    }

    function measure() {
      const h = docRect(hero);
      const width = document.documentElement.clientWidth;
      const narrow = h.width < 768;
      const S = (narrow ? 0.2 : 0.17) * Math.min(h.width, h.height * 1.6);
      heroTop = h.top;
      heroBottom = h.bottom;
      heroH = h.height;
      const heroPts = (narrow ? HERO_PATH.narrow : HERO_PATH.wide).map(([fx, fy, wf], i, arr) => ({
        x: h.left + fx * h.width,
        y: h.top + fy * h.height,
        w: S * wf,
        sway: i > 0 && i < arr.length - 1,
      }));
      const last = heroPts[heroPts.length - 1];
      const prevPt = heroPts[heroPts.length - 2];
      const footer = document.querySelector('.site-footer');
      if (!footer) {
        // Página ainda não lida: o feixe sai pela base do Hero.
        ctrl = heroPts.concat([{ x: last.x, y: h.bottom + h.height * 0.15, w: S * 0.35 }]);
        riverW = null;
        fadeOutY = Infinity;
        endY = Infinity;
      } else {
        // O feixe não afunila mais na base do Hero (ele continua): mesma largura do
        // ponto anterior, e o rio parte dela sem quina (a emenda virava "cotovelo").
        last.w = prevPt.w;
        // Fim: segue pelo rodapé até a base da página e se dissolve no fundo.
        const docH = document.documentElement.scrollHeight;
        const end = { y: docH + 400 };
        // Inclinação na saída do Hero (dx/dy), pela tangente do Catmull-Rom no último ponto.
        const slope0 = (last.x - prevPt.x) / Math.max(1, last.y - prevPt.y);
        const geometry = ['sobre', 'blog', 'instagram-feed', 'newsletter']
          .map((id) => document.getElementById(id))
          .filter(Boolean)
          .concat([footer])
          .map((el) => {
            const r = docRect(el);
            return `${Math.round(r.top)}:${Math.round(r.bottom)}:${Math.round(r.width)}`;
          })
          .join('|');
        const signature = `${RIVER_VERSION}:${width}:${Math.round(h.height)}:${docH}:${geometry}`;
        let river = riverCacheSignature === signature ? riverCache : null;
        if (!river) {
          try {
            const stored = JSON.parse(window.sessionStorage.getItem(RIVER_CACHE_KEY) || 'null');
            if (stored && stored.signature === signature && stored.river) river = stored.river;
          } catch (_) { /* cache indisponível ou inválido */ }
        }
        if (!river) {
          river = buildRiver(h, last, slope0, last.w, end, narrow, width, S * 1.25);
          try { window.sessionStorage.setItem(RIVER_CACHE_KEY, JSON.stringify({ signature, river })); } catch (_) { /* cache indisponível */ }
        }
        riverCacheSignature = signature;
        riverCache = river;
        // Pontos de controle a cada meio trecho médio do Hero: o feixe passa por
        // eles (Catmull-Rom) sem se afastar do rio calculado, e o Hero ainda fica
        // com amostras suficientes (as amostras se dividem por trecho).
        let heroLen = 0;
        for (let i = 1; i < heroPts.length; i += 1) heroLen += Math.hypot(heroPts[i].x - heroPts[i - 1].x, heroPts[i].y - heroPts[i - 1].y);
        const spacing = heroLen / (heroPts.length - 1) / 2;
        // Espaçamento exatamente igual ao longo do rio: o Catmull-Rom uniforme do
        // feixe dobra onde os pontos ficam desiguais.
        ctrl = heroPts.slice();
        const cum = [0];
        for (let i = 1; i < river.xs.length; i += 1) cum.push(cum[i - 1] + Math.hypot(river.xs[i] - river.xs[i - 1], river.ys[i] - river.ys[i - 1]));
        const total = cum[cum.length - 1];
        const segs = Math.max(1, Math.round(total / spacing));
        let j = 0;
        for (let k = 1; k <= segs; k += 1) {
          const target = (total * k) / segs;
          while (j < cum.length - 2 && cum[j + 1] < target) j += 1;
          const f = (target - cum[j]) / Math.max(1e-6, cum[j + 1] - cum[j]);
          ctrl.push({
            x: river.xs[j] + (river.xs[j + 1] - river.xs[j]) * f,
            y: river.ys[j] + (river.ys[j + 1] - river.ys[j]) * f,
            w: river.ws[j] + (river.ws[j + 1] - river.ws[j]) * f,
          });
        }
        ctrl[ctrl.length - 1] = { x: river.xs[river.xs.length - 1], y: end.y, w: river.ws[river.ws.length - 1] };
        riverW = { ys: river.ys, ws: river.ws, dim: smallTextDim(river, h.bottom) };
        // Atravessa o rodapé e sai pela base da página (some só depois dela).
        endY = end.y;
        fadeOutY = docH;
      }

      // Comprimentos: a torção e a cor seguem a distância (o Hero mantém o visual).
      const len = (pts) => pts.reduce((sum, p, i) => (i ? sum + Math.hypot(p.x - pts[i - 1].x, p.y - pts[i - 1].y) : 0), 0);
      totalLen = Math.max(1, len(ctrl));
      tScale = Math.max(1, totalLen / Math.max(1, len(heroPts)));
      if (!pathInitialized && Number.isFinite(endY)) {
        // A trajetória completa é preparada antes do primeiro quadro. O traçado
        // visual, porém, nasce na saída do Hero e acompanha a página pela ponta.
        pen = heroBottom - 40;
        penTarget = heroBottom;
        pulses.length = 0;
        pathInitialized = true;
      }
      if (reduceMotion) { pen = endY; penTarget = endY; }
    }

    function resize() {
      vw = Math.max(1, window.innerWidth);
      vh = Math.max(1, window.innerHeight);
      dpr = Math.min(window.devicePixelRatio || 1, vw < 768 ? 1.25 : 1.5);
      renderer.canvas.width = Math.round(vw * dpr);
      renderer.canvas.height = Math.round(vh * dpr);
      measure();
    }

    // Largura: no rio, pelo perfil fino calculado (assim o ponto mais estreito de
    // cada volta é respeitado); no Hero, pelos pontos de controle. O caminho sempre desce.
    function riverAt(y, values) {
      const ys = riverW.ys;
      let lo = 0;
      let hi = ys.length - 1;
      if (y >= ys[hi]) return values[hi];
      while (hi - lo > 1) {
        const mid = (lo + hi) >> 1;
        if (ys[mid] <= y) lo = mid; else hi = mid;
      }
      const k = (y - ys[lo]) / Math.max(1e-6, ys[hi] - ys[lo]);
      return values[lo] + (values[hi] - values[lo]) * k;
    }

    function widthAtDoc(y) {
      if (riverW && y > riverW.ys[0]) return riverAt(y, riverW.ws);
      if (y <= ctrl[0].y) return ctrl[0].w;
      for (let i = 1; i < ctrl.length; i += 1) {
        if (y <= ctrl[i].y) {
          const k = (y - ctrl[i - 1].y) / Math.max(1, ctrl[i].y - ctrl[i - 1].y);
          return ctrl[i - 1].w + (ctrl[i].w - ctrl[i - 1].w) * k;
        }
      }
      return ctrl[ctrl.length - 1].w;
    }

    function draw(time, withPulses) {
      renderer.begin();
      heads.length = 0;
      if (!ctrl.length) { renderer.flush(); return; }
      const top = window.scrollY;
      const lastY = Math.min(endY, Math.max(heroBottom, pen) + 200);
      if (lastY < top || heroTop > top + vh) { renderer.flush(); return; }

      beam.setPath(ctrl.map((p, i) => [
        (p.x + (p.sway ? 0.022 * vw * Math.sin(time * 0.23 + i * 1.7) : 0)) * dpr,
        (p.y + (p.sway ? 0.03 * heroH * Math.sin(time * 0.19 + i * 2.3) : 0) - top) * dpr,
      ]));

      // Só o trecho visível (com folga), para o caminho longo não pesar.
      const margin = 220 * dpr;
      let from = 0;
      while (from < N && beam.cy[from] < -margin) from += 1;
      let to = N;
      while (to > from && beam.cy[to] > vh * dpr + margin) to -= 1;
      from = Math.max(0, from - 2);
      to = Math.min(N, to + 2);

      // Abertura: o feixe acende de cima para baixo dentro do Hero; terminada a
      // abertura, essa máscara não limita mais nada (o resto é o traçado).
      const revealY = introK() >= 1 ? Infinity : heroTop + introK() * (heroBottom - heroTop) * 1.25;
      // Abaixo da base do Hero, só o trecho traçado pela rolagem (com ponta suave).
      const penEff = Math.max(pen, heroBottom - 40);
      for (let s = from; s <= to; s += 1) {
        const yDoc = beam.cy[s] / dpr + top;
        const w = widthAtDoc(yDoc) * dpr;
        widths[s] = w;
        haze[s] = w * 0.8;
        const intro = Math.min(1, Math.max(0, (revealY - yDoc) / (heroH * 0.2)));
        const traced = Math.min(1, Math.max(0, (penEff - yDoc) / 80));
        const fadeOut = 1 - Math.min(1, Math.max(0, (yDoc - fadeOutY) / Math.max(1, endY - fadeOutY)));
        // Formato de fita no Hero (acende até o meio); da metade do Hero em diante
        // desce suave até o brilho constante do rio, sem degrau na emenda.
        const inHero = (yDoc - heroTop) / heroH;
        const profile = inHero < 0.5
          ? 0.5 + 0.5 * Math.sin(Math.PI * Math.max(0, inHero * 0.92 + 0.04))
          : 0.75 + 0.125 * (1 + Math.cos(Math.PI * Math.min(1, (inHero - 0.5) / 0.5)));
        const dim = riverW && yDoc > riverW.ys[0] ? riverAt(yDoc, riverW.dim) : 1;
        mask[s] = intro * traced * fadeOut * profile * dim;
      }
      beam.place(time, 0, widths, tScale);
      drawBeam(renderer, beam, time, {
        haze: 0.12, hazeWidth: haze, glowWidth: 11 * dpr, glow: 0.1,
        coreWidth: 1.4 * dpr, core: 0.72, hueShift: 0, hueRange: 1, flowSpeed: 1.6,
        mask, flatBase: true, tScale, hueCycles: tScale * 0.5, from, to,
      });

      // Ponta brilhante enquanto o traçado avança abaixo do Hero.
      if (pen > heroBottom && pen < endY - 4) {
        let s = from;
        while (s < to && beam.cy[s] / dpr + top < pen) s += 1;
        if (mask[Math.max(from, s - 1)] > 0.05) {
          renderer.glow(beam.cx[s], beam.cy[s], 18 * dpr, 0.8, WHITE);
          renderer.glow(beam.cx[s], beam.cy[s], 40 * dpr, 0.3, CYAN);
        }
      }

      if (withPulses && introK() >= 1) {
        const heroRect = hero.getBoundingClientRect();
        pulses.forEach((p) => {
          const strand = beam.strands[p.strand];
          if (!strand) return;
          const pf = Math.max(from, Math.floor((p.t - p.length) * N));
          const pt = Math.min(to, Math.ceil(p.t * N));
          if (pt - pf < 1) return;
          for (let s = pf; s <= pt; s += 1) {
            intensity[s] = Math.pow((s - pf) / Math.max(1, pt - pf), 1.5) * mask[s];
            colors[s] = WHITE;
          }
          renderer.strip(strand.xs, strand.ys, 7 * dpr, intensity, 0.25, colors, pf, pt);
          renderer.strip(strand.xs, strand.ys, 1.8 * dpr, intensity, 1, colors, pf, pt);
          if (mask[pt] > 0.2) {
            renderer.glow(strand.xs[pt], strand.ys[pt], 16 * dpr, 0.75 * mask[pt], WHITE);
            renderer.glow(strand.xs[pt], strand.ys[pt], 34 * dpr, 0.25 * mask[pt], colorAt(p.t * tScale * 0.5 % 1));
            // O Echo olha para os pulsos: posição em px CSS do Hero.
            heads.push({ x: strand.xs[pt] / dpr - heroRect.left, y: strand.ys[pt] / dpr - heroRect.top });
          }
        });
      }
      renderer.flush();
    }

    // Fração do caminho até onde já foi traçado (para os pulsos).
    function tracedT() {
      if (!ctrl.length) return 0;
      const y = Math.max(heroBottom, pen);
      let acc = 0;
      for (let i = 1; i < ctrl.length; i += 1) {
        const a = ctrl[i - 1];
        const b = ctrl[i];
        const seg = Math.hypot(b.x - a.x, b.y - a.y);
        if (y <= b.y) return Math.min(1, (acc + seg * Math.max(0, (y - a.y) / Math.max(1, b.y - a.y))) / totalLen);
        acc += seg;
      }
      return 1;
    }

    function update(dt) {
      // No fim da página o traçado vai até a base (a rolagem não passa de lá).
      // Só com o caminho completo medido: antes disso o fim ainda é infinito.
      const atBottom = Number.isFinite(endY) && window.scrollY + vh >= document.documentElement.scrollHeight - 2;
      penTarget = Math.max(penTarget, atBottom ? endY : window.scrollY + vh * 0.7);
      if (penTarget > pen) {
        // Na ativação do rio, a ponta precisa ser percebida atravessando a página:
        // limita a recuperação para que o trecho não apareça inteiro de uma vez.
        // Depois de alcançar a posição atual, volta a acompanhar saltos de menu.
        const gap = penTarget - pen;
        const speed = initialTrace
          ? Math.min(2600, Math.max(900, gap * 1.25))
          : Math.max(900, gap * 4);
        pen += Math.min(gap, speed * dt);
        if (initialTrace && penTarget - pen < 10) initialTrace = false;
      }
      // Pulsos pela distância: ~1 a cada 600 px do trecho traçado, 140-240 px/s.
      const limit = tracedT();
      const want = Math.max(narrowStart ? 3 : 6, Math.round((limit * totalLen) / 600));
      while (pulses.length < want) {
        pulses.push({
          strand: Math.floor(Math.random() * beam.strands.length),
          t: Math.random() * limit,
          speed: (140 + Math.random() * 100) / totalLen,
          length: Math.min(0.2, (60 + Math.random() * 60) / totalLen),
        });
      }
      for (let i = pulses.length - 1; i >= 0; i -= 1) {
        const p = pulses[i];
        p.t += p.speed * dt;
        if (p.t - p.length > limit) {
          pulses.splice(i, 1);
          window.dispatchEvent(new CustomEvent('en:flow-exit'));
        }
      }
    }

    // Recalcula quando qualquer parte da página muda de tamanho (fontes, fotos,
    // carrossel), agrupado num quadro.
    let measureQueued = false;
    const queueMeasure = () => {
      if (measureQueued) return;
      measureQueued = true;
      // Movimento reduzido não tem ciclo de animação: redesenha o quadro parado.
      requestAnimationFrame(() => { measureQueued = false; measure(); if (reduceMotion) draw(0, false); });
    };

    resize();
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(queueMeasure);
    document.addEventListener('DOMContentLoaded', queueMeasure, { once: true });
    window.addEventListener('load', queueMeasure);
    window.setTimeout(queueMeasure, 2600);
    document.addEventListener('DOMContentLoaded', () => {
      const page = document.querySelector('.site-home-page') || document.body;
      if ('ResizeObserver' in window) new ResizeObserver(queueMeasure).observe(page);
    }, { once: true });
    if ('IntersectionObserver' in window) {
      new IntersectionObserver((entries) => { heroVisible = entries[0].isIntersecting; }).observe(hero);
    }
    hero.classList.add('is-live');
    // Movimento reduzido: sem ciclo de animação; redesenha parado ao rolar.
    if (reduceMotion) {
      let queued = false;
      window.addEventListener('scroll', () => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => { queued = false; draw(0, false); });
      }, { passive: true });
    }

    return {
      resize,
      frame(dt, time) {
        update(dt);
        draw(time, true);
      },
      still() {
        measure();
        draw(0, false);
      },
      heads: () => heads,
      isVisible: () => heroVisible,
      // Pontos de controle (px do documento) para conferência nos testes.
      path: () => ctrl.map((p) => ({ x: p.x, y: p.y, w: p.w })),
      widths: () => (riverW ? { ys: riverW.ys.slice(), ws: riverW.ws.slice() } : null),
    };
  }

  // ---------- Echo no Hero (Parte 7) ----------
  // Usa o motor do Echo (cópia de echo-companion em assets/vendor/echo-robo).
  // Fica num lugar do Hero livre de texto e botões; se nenhum lugar servir, não aparece.
  function createEchoLayer(heroLayer) {
    const canvas = hero.querySelector('[data-flow-echo]');
    if (!canvas || !window.RoboMotor || !window.RoboRoupas) return null;
    let robo;
    try {
      robo = new window.RoboMotor.RoboBot(canvas, { theme: window.RoboMotor.THEMES.echo });
    } catch (error) {
      if (window.console) console.warn('[site-home-flow] Echo desativado:', error);
      return null;
    }
    robo.onSound = () => {};
    robo.drawGlow = () => {};

    // Fantasia escolhida no admin para este ambiente (FEAT-016): a view passa as
    // peças em window.EN_ECHO_OUTFIT; peça que não existir no motor é ignorada.
    function equipOutfit() {
      if (!robo || !robo.wardrobe || !window.RoboRoupas || !window.RoboRoupas.ITEMS) return;
      const now = performance.now();
      (Array.isArray(window.EN_ECHO_OUTFIT) ? window.EN_ECHO_OUTFIT : []).forEach((piece) => {
        if (window.RoboRoupas.ITEMS[piece] && !robo.wardrobe.has(piece)) robo.wardrobe.wear(piece, now);
      });
    }
    equipOutfit();

    const ctx = canvas.getContext('2d');

    const PAD_X = 1.05;
    const PAD_Y = 1.48;
    const ANCHOR_Y = 0.5;

    const T_OPEN = 500;
    const T_HOLE = 900;
    const T_OUT = 1600;
    const T_END = 2200;
    const PORTAL_REACH = 0.85;
    const portalCanvas = hero.querySelector('[data-flow-portal]');
    let portal = null;
    let portalOk = false;
    let arrivalStart = 0;
    let arrived = false;
    let emerged = false;

    const SPOTS = {
      wide: [[0.87, 0.34], [0.88, 0.62], [0.13, 0.34], [0.12, 0.62]],
      narrow: [[0.82, 0.9], [0.18, 0.9]],
    };
    let center = { x: 0, y: 0 };
    let size = 0;
    let shown = false;
    let mouse = null;
    let mouseAt = 0;
    let lastReact = 0;
    let lookDownUntil = 0;
    let scrolledOnce = false;
    let following = false;
    let lastScrollY = window.scrollY;
    let scrollSpeed = 0;
    let scrollInertia = 0;
    let navigationLookUntil = 0;
    let navigationLookY = 0;
    let nextIdleAt = 0;
    let lastIdleAction = '';
    let footerGreeted = false;
    let idleSideOverride = null;
    let nextIdleSideToggleAt = performance.now() + 7000;
    let currentSide = 'right';
    let obstaclesCache = [];
    let obstaclesDirty = true;
    let obstaclesAt = 0;
    let obstaclesScrollY = -1;
    let lastScrollEventAt = 0;

    const follow = {
      x: 0, y: 0, tx: 0, ty: 0, vx: 0, vy: 0,
      scale: 1, opacity: 1,
    };

    const OBSTACLE_SELECTOR = 'h1, h2, h3, h4, p, button, a[class*="px-"][class*="py-"], a[class*="btn"], .site-nav-cta, .site-primary-btn, .site-secondary-btn, .site-footer-social-badge, input, textarea, select, form, .site-nav, .nerd-card, .site-preview-post-card, article, figure, img';

    function getObstacles(now = performance.now()) {
      const sy = Math.round(window.scrollY);
      const ttl = now - lastScrollEventAt < 500 ? 30 : 250;
      if (!obstaclesDirty && sy === obstaclesScrollY && now - obstaclesAt < ttl) {
        return obstaclesCache;
      }
      const vw = window.innerWidth;
      const vh = window.innerHeight;
      const list = [];
      const nodes = document.querySelectorAll(OBSTACLE_SELECTOR);
      for (let i = 0; i < nodes.length; i += 1) {
        const el = nodes[i];
        const st = getComputedStyle(el);
        if (st.display === 'none' || st.visibility === 'hidden' || parseFloat(st.opacity) < 0.05) continue;
        const r = el.getBoundingClientRect();
        if (r.width < 2 || r.height < 2 || r.right <= 0 || r.left >= vw || r.bottom <= 0 || r.top >= vh) continue;
        list.push({ l: r.left, t: r.top, r: r.right, b: r.bottom, isNav: el.classList.contains('site-nav') });
      }
      obstaclesCache = list;
      obstaclesDirty = false;
      obstaclesAt = now;
      obstaclesScrollY = sy;
      return list;
    }

    // Mapeamento dinâmico de seções e lados para travessia por trás das imagens
    const SECTION_CONFIGS = [
      { key: 'home', selector: '#home', side: 'right', vertical: 0.38 },
      { key: 'sobre', selector: '#sobre', side: 'left', vertical: 0.42 },
      { key: 'blog', selector: '#blog', side: 'right', vertical: 0.40 },
      { key: 'instagram', selector: '#instagram-feed', side: 'left', vertical: 0.42 },
      { key: 'newsletter', selector: '#newsletter', side: 'right', vertical: 0.44 },
      { key: 'rodape', selector: '.site-footer', side: 'left', vertical: 0.40 },
    ];

    function getActiveTarget() {
      const winMid = window.scrollY + window.innerHeight * 0.45;
      let active = SECTION_CONFIGS[0];
      for (let i = 0; i < SECTION_CONFIGS.length; i++) {
        const item = SECTION_CONFIGS[i];
        const el = document.querySelector(item.selector);
        if (!el) continue;
        const rect = el.getBoundingClientRect();
        const top = Math.round(rect.top + window.scrollY);
        const bottom = top + Math.round(rect.height);
        if (winMid >= top && winMid <= bottom) {
          active = item;
          break;
        } else if (winMid > bottom) {
          active = item;
        }
      }
      return active;
    }

    // ---------- Sistema de Propulsão Matrix Cadente ----------
    const MATRIX_CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ<>/{}$#*+=!?:|';
    const particles = [];
    const MAX_PARTICLES = 45;
    const NOZZLES = [-24, 0, 24]; // 3 jatos independentes sob o corpo

    function spawnParticle() {
      const nozzleIndex = Math.floor(Math.random() * NOZZLES.length);
      const nozzle = NOZZLES[nozzleIndex] + (Math.random() - 0.5) * 5;
      return {
        x: nozzle,
        y: 2 + Math.random() * 4,
        vy: 95 + Math.random() * 65, // velocidade cadente suave dentro do canvas compacto
        char: MATRIX_CHARS[Math.floor(Math.random() * MATRIX_CHARS.length)],
        life: 0,
        maxLife: 0.46 + Math.random() * 0.24,
        size: 13 + Math.random() * 3.5, // tamanho nítido e legível
      };
    }

    function drawMatrixPropulsionUnderBody(dt, boost) {
      if (!following) return;
      const s = robo.s;
      const b = robo.base;
      if (!b) return;

      ctx.save();
      const dpr = Math.min(2, window.devicePixelRatio || 1);
      const k = (size * dpr) / 280;
      const offX = (canvas.width - 280 * k) / 2;
      const offY = (canvas.height * 0.42 - 280 * k / 2);

      // Entra no espaço 280x280 do robô
      ctx.setTransform(k, 0, 0, k, offX, offY);

      // Aplica a matriz física exata do corpo do robô:
      // (0, 0) fica milimetricamente no centro inferior da base do corpo!
      ctx.translate(b.x, b.y);
      ctx.scale(s.sx, s.sy);
      ctx.translate(0, -100);
      ctx.rotate(s.tilt + s.lean + s.sway);
      ctx.translate(0, 100);

      // Emite novas partículas cadentes
      while (particles.length < MAX_PARTICLES) {
        particles.push(spawnParticle());
      }

      ctx.textAlign = 'center';
      ctx.textBaseline = 'top';

      const speedFactor = 1.0 + Math.min(1.8, Math.abs(boost || 1) * 0.6);

      for (let i = particles.length - 1; i >= 0; i--) {
        const p = particles[i];
        p.life += dt;
        p.y += p.vy * speedFactor * dt;

        // Variação de caracteres ao longo da queda (efeito chuva Matrix)
        if (Math.random() < 0.08) {
          p.char = MATRIX_CHARS[Math.floor(Math.random() * MATRIX_CHARS.length)];
        }

        const progress = p.life / p.maxLife;
        // Removido corte rígido por coordenadas: sumiço 100% gradual por opacidade
        if (progress >= 1) {
          particles.splice(i, 1);
          continue;
        }

        ctx.font = `bold ${Math.round(p.size)}px "Courier New", Consolas, monospace`;

        if (progress < 0.16) {
          // Ignição: branco brilhante com brilho néon Matrix
          const a = progress / 0.16;
          ctx.fillStyle = `rgba(255, 255, 255, ${a})`;
          ctx.shadowColor = '#00ff66';
          ctx.shadowBlur = 3;
        } else if (progress < 0.55) {
          // Corpo do jato: verde néon Matrix vibrante e nítido
          const a = 1.0 - (progress - 0.16) * 0.35;
          ctx.fillStyle = `rgba(0, 255, 102, ${a})`;
          ctx.shadowColor = '#00ff66';
          ctx.shadowBlur = 2;
        } else {
          // Cauda: verde digital esvanecendo suavemente até 0.0 absoluto (sem cortes abruptos)
          const a = Math.max(0, (1.0 - progress) / 0.45 * 0.75);
          ctx.fillStyle = `rgba(34, 197, 94, ${a})`;
          ctx.shadowBlur = 0;
        }

        ctx.fillText(p.char, p.x, p.y);
      }

      ctx.restore();
    }

    function blockedRects(heroRect) {
      const rects = [];
      const content = hero.querySelector('.en-flow-hero__content');
      if (!content) return rects;
      const walker = document.createTreeWalker(content, NodeFilter.SHOW_TEXT);
      const range = document.createRange();
      while (walker.nextNode()) {
        if (!walker.currentNode.textContent.trim()) continue;
        range.selectNodeContents(walker.currentNode);
        Array.from(range.getClientRects()).forEach((r) => rects.push(r));
      }
      content.querySelectorAll('a, button, [data-hero-animate="eyebrow"], [data-hero-animate="support"]').forEach((el) => rects.push(el.getBoundingClientRect()));
      return rects.map((r) => ({ l: r.left - heroRect.left, t: r.top - heroRect.top, r: r.right - heroRect.left, b: r.bottom - heroRect.top }));
    }

    function place() {
      if (following) return;
      const heroRect = hero.getBoundingClientRect();
      const narrow = heroRect.width < 768;
      size = narrow ? 104 : Math.round(Math.min(210, Math.max(150, heroRect.width * 0.13)));
      const blocked = blockedRects(heroRect);
      const gap = 8;
      const canvasHalf = size * 0.9;
      const free = (cx, cy, half) => {
        const h = Math.max(half, canvasHalf);
        const box = { l: cx - h - gap, t: cy - h - gap, r: cx + h + gap, b: cy + h + gap };
        if (box.l < -24 || box.r > heroRect.width + 24 || box.t < 72 || box.b > heroRect.height) return false;
        return !blocked.some((o) => box.l < o.r && box.r > o.l && box.t < o.b && box.b > o.t);
      };
      const baseSpots = (narrow ? SPOTS.narrow : SPOTS.wide).map(([fx, fy]) => ({ x: fx * heroRect.width, y: fy * heroRect.height }));
      const spots = [];
      baseSpots.forEach((p) => {
        [0, 16, 28, 40, -16].forEach((dx) => {
          [0, -24, 24, -48].forEach((dy) => {
            spots.push({ x: p.x + (p.x > heroRect.width * 0.5 ? dx : -dx), y: p.y + dy });
          });
        });
      });
      const withPortal = spots.find((p) => free(p.x, p.y, size * PORTAL_REACH));
      const spot = withPortal || spots.find((p) => free(p.x, p.y, size / 2));
      shown = !!spot;
      canvas.hidden = !shown;
      if (!shown) {
        if (portalCanvas) portalCanvas.hidden = true;
        return;
      }
      portalOk = !!withPortal && !arrived;
      center = spot;
      sizeCanvas();
      canvas.style.left = `${spot.x}px`;
      canvas.style.top = `${spot.y}px`;
      if (portalCanvas) {
        portalCanvas.style.left = `${spot.x}px`;
        portalCanvas.style.top = `${spot.y}px`;
        if (portal) portal.resize(size * PORTAL_REACH * 2.2);
      }
    }

    function sizeCanvas() {
      const dpr = Math.min(2, window.devicePixelRatio || 1);
      if (!following) {
        // No Hero durante o portal: 100% quadrado e perfeitamente centralizado com o shader do portal
        const css = Math.round(size * 1.8);
        canvas.width = Math.round(css * dpr);
        canvas.height = Math.round(css * dpr);
        canvas.style.width = `${css}px`;
        canvas.style.height = `${css}px`;
        const k = (size * dpr) / 280;
        const off = (canvas.width - 280 * k) / 2;
        ctx.setTransform(k, 0, 0, k, off, off);
        return;
      }
      const cssW = Math.round(size * PAD_X);
      const cssH = Math.round(size * PAD_Y);
      canvas.width = Math.round(cssW * dpr);
      canvas.height = Math.round(cssH * dpr);
      canvas.style.width = `${cssW}px`;
      canvas.style.height = `${cssH}px`;
      const k = (size * dpr) / 280;
      const offX = (canvas.width - 280 * k) / 2;
      const offY = (canvas.height * 0.42 - 280 * k / 2);
      ctx.setTransform(k, 0, 0, k, offX, offY);
    }

    function boxOverlaps(cx, cy, bw, bh, obstacles, pad) {
      const vw = window.innerWidth;
      const vh = window.innerHeight;
      const l = cx - bw * 0.5;
      const r = cx + bw * 0.5;
      const t = cy - bh * ANCHOR_Y;
      const b = cy + bh * (1 - ANCHOR_Y);
      if (l < 4 || r > vw - 4 || t < 68 || b > vh - 6) return true;
      for (let i = 0; i < obstacles.length; i += 1) {
        const o = obstacles[i];
        if (l < o.r + pad && r > o.l - pad && t < o.b + pad && b > o.t - pad) return true;
      }
      return false;
    }

    function findBestFollowSpot(preferredSide, obstacles, narrow, verticalPref, allowOtherSide = true) {
      const vw = window.innerWidth;
      const vh = window.innerHeight;
      const pad = narrow ? 12 : 6;
      const sizes = narrow ? [64, 54] : [96, 84, 72];
      const sides = allowOtherSide
        ? [preferredSide, preferredSide === 'left' ? 'right' : 'left']
        : [preferredSide];
      const idealY = vh * (narrow ? 0.74 : verticalPref);

      for (let sIdx = 0; sIdx < sides.length; sIdx += 1) {
        const side = sides[sIdx];
        for (let zIdx = 0; zIdx < sizes.length; zIdx += 1) {
          const candSize = sizes[zIdx];
          const bw = candSize * PAD_X;
          const bh = candSize * PAD_Y;
          const halfW = bw * 0.5;
          const offsets = narrow
            ? [halfW + 6, halfW + 16, halfW + 30]
            : [halfW + 18, halfW + 8, halfW + 34];
          const minY = Math.ceil(72 + bh * ANCHOR_Y);
          const maxY = Math.floor(vh - 8 - bh * (1 - ANCHOR_Y));
          let best = null;
          let bestScore = Infinity;

          for (let oIdx = 0; oIdx < offsets.length; oIdx += 1) {
            const cx = side === 'left' ? offsets[oIdx] : (vw - offsets[oIdx]);
            for (let cy = minY; cy <= maxY; cy += 16) {
              if (boxOverlaps(cx, cy, bw, bh, obstacles, pad)) continue;
              const distCurrent = (!canvas.hidden && follow.opacity > 0.1 && side === currentSide)
                ? Math.abs(cy - follow.y) * 0.65 + Math.abs(cy - idealY) * 0.35
                : Math.abs(cy - idealY);
              const score = distCurrent + oIdx * 8;
              if (score < bestScore) {
                bestScore = score;
                best = { x: cx, y: cy, size: candSize, side, bw, bh };
              }
            }
          }
          if (best) return best;
        }
      }
      return null;
    }

    function pathIsClear(fromX, fromY, spot, obstacles) {
      const steps = 18;
      for (let step = 1; step <= steps; step += 1) {
        const progress = step / steps;
        const x = fromX + (spot.x - fromX) * progress;
        const y = fromY + (spot.y - fromY) * progress;
        if (boxOverlaps(x, y, spot.bw, spot.bh, obstacles, 4)) return false;
      }
      return true;
    }

    function enterFollowMode(snap = false) {
      if (following) return;
      following = true;
      const narrow = window.innerWidth < 768;
      if (!size) size = narrow ? 104 : Math.round(Math.min(210, Math.max(150, window.innerWidth * 0.13)));
      const r = canvas.getBoundingClientRect();
      follow.x = r.width > 0 ? r.left + r.width / 2 : window.innerWidth - (narrow ? 44 : 76);
      follow.y = r.height > 0 ? r.top + r.height / 2 : window.innerHeight * 0.4;
      const followTargetScale = (narrow ? 64 : 96) / Math.max(1, size);
      // No desktop, preserve a escala cheia da chegada para que a transicao ao
      // modo de acompanhamento encolha o Echo de forma visivel e continua.
      follow.scale = narrow ? followTargetScale : 1;
      if (narrow || snap) {
        follow.opacity = 0;
        follow.scale *= 0.25;
      }

      sizeCanvas();

      // Move para o container da página (<main>) para sair do stacking context isolado do Hero
      if (hero.parentElement && canvas.parentElement !== hero.parentElement) {
        hero.parentElement.appendChild(canvas);
      }
      canvas.classList.add('is-following');
      canvas.style.left = '0';
      canvas.style.top = '0';

      equipOutfit();
      scheduleIdle();
      obstaclesDirty = true;
      updateFollow(0.016);
    }

    function scheduleIdle(now = performance.now()) {
      nextIdleAt = now + 5000 + Math.random() * 4000;
    }

    function animateIdle(now) {
      if (!following || now < nextIdleAt) return;
      const actions = ['wave', 'hatHop', 'cuddle', 'hop', 'proud'].filter((a) => a !== lastIdleAction);
      const action = actions[Math.floor(Math.random() * actions.length)];
      lastIdleAction = action;
      if (action === 'wave') robo.wave(1, 1200);
      else if (action === 'hatHop') robo.hatHop();
      else if (action === 'cuddle') robo.cuddle();
      else if (action === 'hop') robo.hop();
      else robo.emote('proud', 1200);
      scheduleIdle(now);
    }

    function updateFollow(dt) {
      if (!following) return;
      const narrow = window.innerWidth < 768;
      const now = performance.now();

      // Decaimento suave da inércia da rolagem
      scrollInertia *= Math.exp(-4.2 * dt);

      const mobileMenuEl = document.getElementById('siteMobileMenu');
      const menuOpen = narrow && (
        !!document.querySelector('[data-site-menu-toggle][aria-expanded="true"]') ||
        (mobileMenuEl && !mobileMenuEl.hidden)
      );
      const activeTag = document.activeElement && document.activeElement.tagName;
      const editing = /^(INPUT|TEXTAREA|SELECT)$/.test(activeTag || '');
      const heroRect = hero.getBoundingClientRect();
      const inMobileHero = narrow && heroRect.bottom > window.innerHeight * 0.35;

      const obstacles = getObstacles(now);
      const curSize = Math.max(1, follow.scale * size);
      const curBw = curSize * PAD_X;
      const curBh = curSize * PAD_Y;
      const curCollides = boxOverlaps(follow.x, follow.y, curBw, curBh, obstacles, 2);

      if (menuOpen || editing || inMobileHero) {
        follow.opacity = 0;
        canvas.style.opacity = '0';
        canvas.hidden = true;
        return;
      }

      const active = getActiveTarget();
      if (now >= nextIdleSideToggleAt) {
        idleSideOverride = (idleSideOverride || active.side) === 'left' ? 'right' : 'left';
        nextIdleSideToggleAt = now + 7000;
      }
      const requestedSide = idleSideOverride || active.side;
      // No celular, manter o canto atual evita travessias sobre uma coluna de
      // texto que ocupa quase toda a largura. O movimento continua vertical.
      const preferredSide = narrow && !canvas.hidden ? currentSide : requestedSide;
      const preferredSpot = findBestFollowSpot(preferredSide, obstacles, narrow, active.vertical, false);
      let spot = preferredSpot;

      // Troca de lado apenas quando todo o corredor esta livre. Enquanto houver
      // texto ou controle no caminho, o Echo continua flutuando no lado atual.
      if (!canvas.hidden && follow.opacity > 0.03 && preferredSide !== currentSide) {
        const canCross = preferredSpot !== null && pathIsClear(follow.x, follow.y, preferredSpot, obstacles);
        if (!canCross) {
          spot = findBestFollowSpot(currentSide, obstacles, narrow, active.vertical, false);
        }
      }

      if (!spot) {
        if (curCollides) {
          follow.opacity = 0;
        } else {
          follow.opacity = Math.max(0, follow.opacity - dt * 6);
        }
        if (follow.opacity <= 0.03) {
          follow.opacity = 0;
          canvas.style.opacity = '0';
          canvas.hidden = true;
        } else {
          canvas.style.opacity = String(follow.opacity);
        }
        return;
      }

      const targetScale = spot.size / Math.max(1, size);

      // Se estava oculto, posiciona direto no lugar livre e surge suavemente (crescendo no mobile)
      if (canvas.hidden || follow.opacity <= 0.03) {
        follow.x = spot.x;
        follow.y = spot.y;
        follow.tx = spot.x;
        follow.ty = spot.y;
        follow.vx = 0;
        follow.vy = 0;
        currentSide = spot.side;
        if (narrow && follow.scale < targetScale * 0.4) {
          follow.scale = targetScale * 0.28;
        } else if (!narrow) {
          follow.scale = targetScale;
        }
        canvas.hidden = false;
      }

      const bob = Math.sin(now * 0.0022) * 3;
      const bobY = !boxOverlaps(spot.x, spot.y + bob, spot.bw, spot.bh, obstacles, narrow ? 4 : 6) ? spot.y + bob : spot.y;
      follow.tx = spot.x;
      follow.ty = bobY;

      // Preserva a travessia flutuante da versao publicada: mudar de lado altera
      // apenas o alvo, sem fade, salto ou reposicionamento instantaneo.
      const springX = 3.2;
      const dampingX = Math.exp(-3.4 * dt);
      follow.vx = (follow.vx + (follow.tx - follow.x) * springX * dt) * dampingX;
      if (Math.abs(follow.vx) > 420) {
        follow.vx = Math.sign(follow.vx) * 420;
      }
      const springY = 7.5;
      const dampingY = Math.exp(-5.5 * dt);
      follow.vy = (follow.vy + (follow.ty - follow.y) * springY * dt) * dampingY;
      if (Math.abs(follow.vy) > 550) {
        follow.vy = Math.sign(follow.vy) * 550;
      }

      const nextX = follow.x + follow.vx * dt;
      const nextY = follow.y + follow.vy * dt;
      const nextScale = follow.scale + (targetScale - follow.scale) * (1 - Math.exp(-6.5 * dt));
      const nextSize = Math.max(1, nextScale * size);
      if (boxOverlaps(nextX, nextY, nextSize * PAD_X, nextSize * PAD_Y, obstacles, 2)) {
        follow.vx = 0;
        follow.vy = 0;
        follow.opacity = Math.max(0, follow.opacity - dt * 6);
        canvas.style.opacity = String(follow.opacity);
        if (follow.opacity <= 0.03) canvas.hidden = true;
        return;
      }
      follow.x = nextX;
      follow.y = nextY;
      follow.scale = nextScale;
      if (Math.abs(follow.x - spot.x) < 3) {
        currentSide = spot.side;
      }

      canvas.hidden = false;
      follow.opacity = Math.min(1, follow.opacity + (1 - follow.opacity) * (1 - Math.exp(-7.5 * dt)) + 0.02);
      canvas.style.opacity = String(follow.opacity);
      canvas.style.transform = `translate3d(${follow.x}px, ${follow.y}px, 0) translate(-50%, -${ANCHOR_Y * 100}%) scale(${follow.scale})`;

      // A geometria real do canvas e a autoridade final. Fontes responsivas e
      // arredondamentos podem diferir alguns pixels do modelo usado na busca.
      const rendered = canvas.getBoundingClientRect();
      for (let index = 0; index < obstacles.length; index += 1) {
        const obstacle = obstacles[index];
        if (
          rendered.left < obstacle.r + (narrow ? 8 : 2) &&
          rendered.right > obstacle.l - (narrow ? 8 : 2) &&
          rendered.top < obstacle.b + (narrow ? 8 : 2) &&
          rendered.bottom > obstacle.t - (narrow ? 8 : 2)
        ) {
          follow.opacity = 0;
          canvas.style.opacity = '0';
          canvas.hidden = true;
          break;
        }
      }

    }

    function render(dt, boost) {
      ctx.save();
      ctx.setTransform(1, 0, 0, 1, 0, 0);
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.restore();

      // Garante que o Echo permaneça com a fantasia do Jack Esqueleto o tempo todo
      equipOutfit();

      // Desenha o robô Echo
      robo.update();
      robo.draw();

      // Desenha a propulsão Matrix sob a base física do corpo (alinhamento milimétrico 100% reto)
      drawMatrixPropulsionUnderBody(dt, boost);
    }

    // Portal shader
    const PORTAL_FRAG = `#version 300 es
precision highp float;
uniform vec2 u_res;
uniform float u_time;
uniform float u_open;
uniform float u_hole;
uniform float u_close;
out vec4 outColor;
float hash(vec2 p) { p = fract(p * vec2(123.34, 456.21)); p += dot(p, p + 45.32); return fract(p.x * p.y); }
float noise(vec2 p) {
  vec2 i = floor(p); vec2 f = fract(p);
  float a = hash(i); float b = hash(i + vec2(1.0, 0.0)); float c = hash(i + vec2(0.0, 1.0)); float d = hash(i + vec2(1.0, 1.0));
  vec2 u = f * f * (3.0 - 2.0 * f);
  return mix(a, b, u.x) + (c - a) * u.y * (1.0 - u.x) + (d - b) * u.x * u.y;
}
float fbm(vec2 p) { float v = 0.0; float a = 0.5; for (int i = 0; i < 5; i++) { v += a * noise(p); p *= 2.03; a *= 0.5; } return v; }
void main() {
  vec2 uv = (gl_FragCoord.xy / u_res) * 2.0 - 1.0;
  float r = length(uv);
  float ang = atan(uv.y, uv.x);
  float R = 0.6 * u_open * (1.0 - 0.9 * u_close);
  float swirl = ang + u_time * (1.4 + 3.0 * u_close) + r * 3.2;
  vec2 q = vec2(cos(swirl), sin(swirl)) * r * 3.0;
  // Distorção do próprio ruído: cria os fiapos de fumaça e fogo.
  vec2 w = vec2(fbm(q * 2.0 + u_time * 0.6), fbm(q * 2.0 - u_time * 0.5 + 7.3));
  float n = fbm(q * 2.3 + w * 1.8 + vec2(u_time * 0.7, -u_time * 0.5));
  float n2 = fbm(vec2(ang * 5.0 + u_time * 1.2, r * 9.0 - u_time * 2.6) + w * 1.4);
  float th = 0.05 + 0.12 * n;
  float d = r - R - (n - 0.5) * 0.2;
  float ring = exp(-d * d / (th * th));
  float fill = (1.0 - u_hole) * smoothstep(R + 0.06, R - 0.3, r) * (0.45 + 0.7 * n);
  float flames = smoothstep(0.5, 0.82, n2) * exp(-max(r - R, 0.0) * 5.5) * step(R * 0.9, r) * u_open;
  float wisps = smoothstep(0.55, 0.9, n) * exp(-abs(d) * 9.0);
  float e = ring * (0.35 + 1.3 * n * n) + fill + flames * 0.95 + wisps * 0.8;
  e *= 1.0 - u_hole * smoothstep(R - 0.04, R - 0.28, r) * 0.96;
  e *= smoothstep(1.0, 0.84, r);
  float flash = smoothstep(0.75, 0.92, u_close) * (1.0 - smoothstep(0.92, 1.0, u_close));
  e += flash * exp(-r * r * 10.0) * 2.2;
  e *= 1.0 - smoothstep(0.94, 1.0, u_close);
  vec3 cyan = vec3(0.133, 0.827, 0.933);
  vec3 violet = vec3(0.659, 0.333, 0.969);
  vec3 white = vec3(0.86, 0.98, 1.0);
  vec3 col = mix(cyan, violet, clamp(smoothstep(R * 0.95, R + 0.28, r) * 0.85 + (n2 - 0.5) * 0.35, 0.0, 1.0));
  col = mix(col, white, clamp(ring * n * 0.7 + flash, 0.0, 1.0));
  // O portal tampa o que está atrás (o feixe): disco interno opaco e escuro,
  // com um brilho leve na borda de dentro; encolhe e some junto com o anel.
  float occl = smoothstep(R + 0.03, R - 0.04, r) * smoothstep(0.0, 0.25, u_open) * (1.0 - smoothstep(0.85, 1.0, u_close));
  vec3 voidCol = vec3(0.008, 0.024, 0.09) + cyan * 0.12 * smoothstep(R - 0.3, R, r);
  float glowA = clamp(e, 0.0, 1.0);
  float a = max(glowA, occl);
  outColor = vec4(col * glowA + voidCol * (a - glowA), a);
}`;

    function createPortal(target) {
      const gl = target.getContext('webgl2', { alpha: true, premultipliedAlpha: true, antialias: false });
      if (!gl) return null;
      const compile = (type, source) => {
        const s = gl.createShader(type);
        gl.shaderSource(s, source);
        gl.compileShader(s);
        if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) throw new Error(gl.getShaderInfoLog(s) || 'portal');
        return s;
      };
      const program = gl.createProgram();
      gl.attachShader(program, compile(gl.VERTEX_SHADER, '#version 300 es\nin vec2 p; void main() { gl_Position = vec4(p, 0.0, 1.0); }'));
      gl.attachShader(program, compile(gl.FRAGMENT_SHADER, PORTAL_FRAG));
      gl.linkProgram(program);
      if (!gl.getProgramParameter(program, gl.LINK_STATUS)) throw new Error(gl.getProgramInfoLog(program) || 'portal');
      gl.useProgram(program);
      const buffer = gl.createBuffer();
      gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
      gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
      const loc = gl.getAttribLocation(program, 'p');
      gl.enableVertexAttribArray(loc);
      gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);
      const u = (name) => gl.getUniformLocation(program, name);
      const uni = { res: u('u_res'), time: u('u_time'), open: u('u_open'), hole: u('u_hole'), close: u('u_close') };
      return {
        resize(css) {
          const dpr = Math.min(1.5, window.devicePixelRatio || 1);
          const px = Math.round(css);
          target.width = Math.round(px * dpr);
          target.height = Math.round(px * dpr);
          target.style.width = `${px}px`;
          target.style.height = `${px}px`;
        },
        draw(time, open, hole, close) {
          gl.viewport(0, 0, target.width, target.height);
          gl.clearColor(0, 0, 0, 0);
          gl.clear(gl.COLOR_BUFFER_BIT);
          gl.uniform2f(uni.res, target.width, target.height);
          gl.uniform1f(uni.time, time);
          gl.uniform1f(uni.open, open);
          gl.uniform1f(uni.hole, hole);
          gl.uniform1f(uni.close, close);
          gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
        },
      };
    }

    const ease = (t) => 1 - Math.pow(1 - Math.min(1, Math.max(0, t)), 3);

    // Chegada: o portal abre no lugar do Echo, ele sai do centro vindo na direção
    // de quem olha, e o portal se fecha com um clarão. Sem portal (sem WebGL2 ou
    // sem espaço livre), o Echo só aparece, crescendo.
    function arrival(now) {
      if (arrived) return;
      if (!arrivalStart) {
        arrivalStart = now;
        if (portalOk && portalCanvas && !portal) {
          try { portal = createPortal(portalCanvas); } catch (error) { portal = null; }
          if (portal) portal.resize(size * PORTAL_REACH * 2.2);
        }
        if (portal && portalOk) portalCanvas.hidden = false;
      }
      const t = now - arrivalStart;
      const usePortal = portal && portalOk;
      if (usePortal) {
        portal.draw(t / 1000, ease(t / T_OPEN), ease((t - T_OPEN) / (T_HOLE - T_OPEN)), Math.min(1, Math.max(0, (t - T_OUT) / (T_END - T_OUT))));
      }
      const from = usePortal ? T_HOLE : 0;
      const k = ease((t - from) / (T_OUT - T_HOLE));
      canvas.style.transform = `translate(-50%, -50%) scale(${0.2 + 0.8 * k})`;
      canvas.style.opacity = String(Math.min(1, Math.max(0, (t - from) / 350)));
      if (t >= from + (T_OUT - T_HOLE) && !emerged) {
        emerged = true;
        robo.emote('surprised', 900);
      }
      if (t >= (usePortal ? T_END : T_OUT - T_HOLE)) {
        arrived = true;
        canvas.style.opacity = '1';
        if (portalCanvas) portalCanvas.hidden = true;
        enterFollowMode();
      }
    }

    window.addEventListener('pointermove', (event) => {
      mouse = { x: event.clientX, y: event.clientY };
      mouseAt = performance.now();
    }, { passive: true });

    document.addEventListener('focusin', () => { if (following) updateFollow(0.016); });
    document.addEventListener('focusout', () => { if (following) updateFollow(0.016); });

    window.addEventListener('scroll', () => {
      const currentY = window.scrollY;
      const delta = currentY - lastScrollY;
      lastScrollY = currentY;
      obstaclesDirty = true;
      lastScrollEventAt = performance.now();

      scrollSpeed = delta;
      scrollInertia = Math.max(-120, Math.min(180, scrollInertia * 0.6 + delta * 2.5));
      idleSideOverride = null;
      nextIdleSideToggleAt = performance.now() + 7000;

      if (!following) {
        const narrow = window.innerWidth < 768;
        if (narrow) {
          if (hero.getBoundingClientRect().bottom <= window.innerHeight * 0.35) {
            arrived = true;
            shown = true;
            if (portalCanvas) portalCanvas.hidden = true;
            enterFollowMode(true);
          }
        } else if (currentY > 40) {
          arrived = true;
          shown = true;
          if (portalCanvas) portalCanvas.hidden = true;
          enterFollowMode(false);
        }
      } else {
        updateFollow(0.016);
      }

      if (Math.abs(delta) > 3) {
        navigationLookY = delta > 0 ? 1 : -1;
        navigationLookUntil = performance.now() + 500;
        if (robo && robo.s) {
          robo.s.tilt = delta > 0 ? 0.08 : -0.06;
        }
      }

      // Reação no rodapé da página
      const distToBottom = document.documentElement.scrollHeight - (currentY + window.innerHeight);
      if (distToBottom < 260 && !footerGreeted) {
        footerGreeted = true;
        robo.wave(1, 1500);
      } else if (distToBottom > 400) {
        footerGreeted = false;
      }
    }, { passive: true });

    function steer(now) {
      if (now < navigationLookUntil) {
        robo.look.x = 0;
        robo.look.y = navigationLookY;
        return;
      }
      if (now < lookDownUntil) {
        robo.look.x = 0;
        robo.look.y = 1;
        return;
      }

      // Olha para o cursor ou pulso de luz do feixe mais próximo
      const anchor = following ? follow : center;
      const heads = heroLayer && !following ? heroLayer.heads() : [];
      let nearest = null;
      let best = Infinity;
      heads.forEach((h) => {
        const d = Math.hypot(h.x - anchor.x, h.y - anchor.y);
        if (d < best) { best = d; nearest = h; }
      });

      let target = null;
      if (mouse && now - mouseAt < 2500) target = mouse;
      else if (nearest) target = nearest;

      if (target) {
        const reach = size * 2.5;
        robo.look.x = Math.max(-1, Math.min(1, (target.x - anchor.x) / reach));
        robo.look.y = Math.max(-1, Math.min(1, (target.y - anchor.y) / reach));
      }
    }

    place();
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(place);
    window.setTimeout(place, 2600);

    return {
      resize() {
        obstaclesDirty = true;
        if (!following) {
          place();
        } else {
          sizeCanvas();
          updateFollow(0.016);
        }
      },
      frame(dt) {
        if (!shown && !following) {
          if (window.innerWidth < 768 && hero.getBoundingClientRect().bottom <= window.innerHeight * 0.35) {
            arrived = true;
            shown = true;
            if (portalCanvas) portalCanvas.hidden = true;
            enterFollowMode(true);
          } else {
            return;
          }
        }
        const now = performance.now();
        arrival(now);
        updateFollow(dt);
        if (canvas.hidden) return;
        steer(now);
        animateIdle(now);
        const boost = 1.0 + Math.min(1.6, Math.abs(scrollInertia) / 70);
        render(dt, boost);
      },
      still() {
        obstaclesDirty = true;
        if (window.innerWidth < 768 && hero.getBoundingClientRect().bottom > window.innerHeight * 0.35) {
          canvas.hidden = true;
          if (portalCanvas) portalCanvas.hidden = true;
          return;
        }
        if (!shown) return;
        arrived = true;
        if (portalCanvas) portalCanvas.hidden = true;
        enterFollowMode(true);
        if (!canvas.hidden) {
          canvas.style.opacity = '1';
          render(0.016, 1.0);
        }
      },
    };
  }

  // ---------- Ciclo de vida ----------
  const flowRenderer = safeRenderer(flowCanvas);
  const bgRenderer = safeRenderer(bgCanvas);
  const layers = [];
  // Feixe principal (um caminho só, do Hero ao Sobre); o Echo olha os pulsos dele.
  const heroLayer = flowRenderer && hero ? createFlowLayer(flowRenderer) : null;
  if (heroLayer) layers.push(heroLayer);
  // Conferência nos testes: ?flow-debug expõe os pontos do caminho.
  if (heroLayer && /[?&]flow-debug\b/.test(window.location.search)) window.enFlowDebug = { path: heroLayer.path, widths: heroLayer.widths };
  if (bgRenderer) layers.push(createBackgroundLayer(bgRenderer));
  if (!layers.length) return;

  // O Echo depende do motor (scripts com defer, que chegam depois deste): cria
  // agora se já estiver carregado, senão quando a página terminar de ser lida.
  const addEcho = () => {
    const echoLayer = hero ? createEchoLayer(heroLayer) : null;
    if (!echoLayer) return;
    layers.push(echoLayer);
    if (reduceMotion) echoLayer.still();
  };
  if (window.RoboMotor && window.RoboRoupas) addEcho();
  else document.addEventListener('DOMContentLoaded', addEcho, { once: true });

  let resizeQueued = false;
  const onResize = (redraw) => {
    if (resizeQueued) return;
    resizeQueued = true;
    requestAnimationFrame(() => {
      resizeQueued = false;
      layers.forEach((layer) => layer.resize());
      if (redraw) layers.forEach((layer) => layer.still());
    });
  };

  if (reduceMotion) {
    layers.forEach((layer) => layer.still());
    window.addEventListener('resize', () => onResize(true));
    return;
  }

  let running = false;
  let last = 0;
  let clock = 0;
  let frame = 0;
  let lost = false;
  let recoveryScheduled = false;

  const loop = (now) => {
    if (document.hidden || lost) { running = false; return; }
    const dt = last ? Math.min((now - last) / 1000, 0.05) : 0;
    last = now;
    introElapsed = Math.min(INTRO_MS, introElapsed + dt * 1000);
    clock += dt;
    layers.forEach((layer) => layer.frame(dt, clock));
    frame = requestAnimationFrame(loop);
  };
  const resume = () => {
    if (running || document.hidden || lost) return;
    running = true;
    last = 0;
    frame = requestAnimationFrame(loop);
  };

  document.addEventListener('visibilitychange', resume);
  window.addEventListener('resize', () => onResize(false));
  if (hero && 'ResizeObserver' in window) new ResizeObserver(() => onResize(false)).observe(hero);

  [flowCanvas, bgCanvas].forEach((canvas) => {
    if (!canvas) return;
    canvas.addEventListener('webglcontextlost', (event) => {
      event.preventDefault();
      cancelAnimationFrame(frame);
      lost = true;
      running = false;
      if (recoveryScheduled) return;
      recoveryScheduled = true;

      // Os programas, buffers e atributos pertencem ao contexto perdido e não
      // podem ser reutilizados. Uma recarga recria as duas camadas em conjunto;
      // o marcador evita um ciclo infinito se o navegador perder o contexto de
      // novo imediatamente (por exemplo, GPU indisponível).
      let previousRecovery = 0;
      try { previousRecovery = Number(window.sessionStorage.getItem('en-flow-webgl-recovery') || 0); } catch (_) { /* storage indisponível */ }
      const now = Date.now();
      if (now - previousRecovery < 30000) return;
      try { window.sessionStorage.setItem('en-flow-webgl-recovery', String(now)); } catch (_) { /* storage indisponível */ }
      window.setTimeout(() => window.location.reload(), 250);
    });
  });

  resume();
})();
