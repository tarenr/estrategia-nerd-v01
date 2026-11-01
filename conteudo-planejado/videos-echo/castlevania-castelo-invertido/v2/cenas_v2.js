/**
 * cenas_v2.js - Motor Contínuo do Vídeo Híbrido Castlevania SOTN
 * Quarto Gamer + Monitor Retrô 4:3 Nativo (Zero Corte, Zero Espaço Vazio)
 * Echo com as poses do Manifesto (vendor/echo-atuacao-sala.js): corpo rígido, poses paradas e lip-sync
 * Câmera: Push-in Suave (3.9s–8.8s) e Recuo Antecipado Suave (35.5s–38.0s)
 */
(function(window) {
  'use strict';

  const W = 1080;
  const H = 1920;

  const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
  const lerp = (a, b, t) => a + (b - a) * t;
  const invLerp = (a, b, v) => clamp((v - a) / (b - a), 0, 1);
  const eIO = (t) => { t = clamp(t, 0, 1); return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; };

  const ASSETS = {};
  const ASSET_PATHS = {
    gameplayFallback: 'assets/orig_title_15s_cropped.png'
  };

  let assetsLoaded = false;
  function carregarAssets() {
    if (assetsLoaded) return Promise.resolve();
    const promises = Object.keys(ASSET_PATHS).map(key => {
      return new Promise((resolve) => {
        const img = new Image();
        img.onload = () => { ASSETS[key] = img; resolve(); };
        img.onerror = () => { console.warn('Falha asset:', ASSET_PATHS[key]); resolve(); };
        img.src = ASSET_PATHS[key];
      });
    });
    return Promise.all(promises).then(() => { assetsLoaded = true; });
  }

  let _videoElement = null;
  function setVideoElement(video) {
    _videoElement = video;
  }

  // ---------- Sincronia Labial Oficial (97 Palavras Whisper da Locução) ----------
  const PALAVRAS = [
  {
    "ini": 0.0,
    "fim": 0.28,
    "p": "Em"
  },
  {
    "ini": 0.28,
    "fim": 1.38,
    "p": "1997,"
  },
  {
    "ini": 2.16,
    "fim": 2.22,
    "p": "a"
  },
  {
    "ini": 2.22,
    "fim": 2.74,
    "p": "indústria"
  },
  {
    "ini": 2.74,
    "fim": 3.34,
    "p": "priorizava"
  },
  {
    "ini": 3.34,
    "fim": 3.94,
    "p": "3D."
  },
  {
    "ini": 4.88,
    "fim": 5.64,
    "p": "Castelhevania"
  },
  {
    "ini": 5.64,
    "fim": 5.84,
    "p": "fez"
  },
  {
    "ini": 5.84,
    "fim": 6.04,
    "p": "o"
  },
  {
    "ini": 6.04,
    "fim": 6.54,
    "p": "contrário,"
  },
  {
    "ini": 6.82,
    "fim": 7.24,
    "p": "apostou"
  },
  {
    "ini": 7.24,
    "fim": 7.38,
    "p": "no"
  },
  {
    "ini": 7.38,
    "fim": 7.8,
    "p": "2D"
  },
  {
    "ini": 7.8,
    "fim": 7.98,
    "p": "e"
  },
  {
    "ini": 7.98,
    "fim": 8.18,
    "p": "criou"
  },
  {
    "ini": 8.18,
    "fim": 8.32,
    "p": "um"
  },
  {
    "ini": 8.32,
    "fim": 8.88,
    "p": "clássico."
  },
  {
    "ini": 9.68,
    "fim": 9.98,
    "p": "Mais"
  },
  {
    "ini": 9.98,
    "fim": 10.14,
    "p": "o"
  },
  {
    "ini": 10.14,
    "fim": 10.38,
    "p": "maior"
  },
  {
    "ini": 10.38,
    "fim": 10.96,
    "p": "cedredo"
  },
  {
    "ini": 10.96,
    "fim": 11.08,
    "p": "do"
  },
  {
    "ini": 11.08,
    "fim": 11.32,
    "p": "jogo"
  },
  {
    "ini": 11.32,
    "fim": 11.68,
    "p": "estava"
  },
  {
    "ini": 11.68,
    "fim": 12.16,
    "p": "escondido"
  },
  {
    "ini": 12.16,
    "fim": 12.5,
    "p": "depois"
  },
  {
    "ini": 12.5,
    "fim": 12.76,
    "p": "do"
  },
  {
    "ini": 12.76,
    "fim": 13.24,
    "p": "suposto"
  },
  {
    "ini": 13.24,
    "fim": 13.62,
    "p": "final."
  },
  {
    "ini": 14.36,
    "fim": 14.74,
    "p": "Ao"
  },
  {
    "ini": 14.74,
    "fim": 15.22,
    "p": "descobrir"
  },
  {
    "ini": 15.22,
    "fim": 15.48,
    "p": "quem"
  },
  {
    "ini": 15.48,
    "fim": 16.0,
    "p": "realmente"
  },
  {
    "ini": 16.0,
    "fim": 16.66,
    "p": "controlava"
  },
  {
    "ini": 16.66,
    "fim": 16.8,
    "p": "a"
  },
  {
    "ini": 16.8,
    "fim": 17.22,
    "p": "Richter,"
  },
  {
    "ini": 17.58,
    "fim": 17.76,
    "p": "você"
  },
  {
    "ini": 17.76,
    "fim": 18.22,
    "p": "liberava"
  },
  {
    "ini": 18.22,
    "fim": 18.4,
    "p": "uma"
  },
  {
    "ini": 18.4,
    "fim": 18.72,
    "p": "segunda"
  },
  {
    "ini": 18.72,
    "fim": 19.12,
    "p": "versão"
  },
  {
    "ini": 19.12,
    "fim": 19.34,
    "p": "do"
  },
  {
    "ini": 19.34,
    "fim": 19.84,
    "p": "castelo,"
  },
  {
    "ini": 20.16,
    "fim": 20.68,
    "p": "completamente"
  },
  {
    "ini": 20.68,
    "fim": 21.04,
    "p": "de"
  },
  {
    "ini": 21.04,
    "fim": 21.38,
    "p": "cabeça"
  },
  {
    "ini": 21.38,
    "fim": 21.68,
    "p": "para"
  },
  {
    "ini": 21.68,
    "fim": 22.08,
    "p": "baixo."
  },
  {
    "ini": 22.8,
    "fim": 23.22,
    "p": "As"
  },
  {
    "ini": 23.22,
    "fim": 23.62,
    "p": "mesmas"
  },
  {
    "ini": 23.62,
    "fim": 23.88,
    "p": "áreas"
  },
  {
    "ini": 23.88,
    "fim": 24.38,
    "p": "ganhavam"
  },
  {
    "ini": 24.38,
    "fim": 24.78,
    "p": "novas"
  },
  {
    "ini": 24.78,
    "fim": 25.26,
    "p": "rotas,"
  },
  {
    "ini": 25.56,
    "fim": 25.94,
    "p": "inimigos"
  },
  {
    "ini": 25.94,
    "fim": 26.16,
    "p": "e"
  },
  {
    "ini": 26.16,
    "fim": 26.7,
    "p": "chefes."
  },
  {
    "ini": 26.7,
    "fim": 28.16,
    "p": "Lugares"
  },
  {
    "ini": 28.16,
    "fim": 28.26,
    "p": "que"
  },
  {
    "ini": 28.26,
    "fim": 28.48,
    "p": "você"
  },
  {
    "ini": 28.48,
    "fim": 28.64,
    "p": "já"
  },
  {
    "ini": 28.64,
    "fim": 29.12,
    "p": "conhecia"
  },
  {
    "ini": 29.12,
    "fim": 29.68,
    "p": "precisavam"
  },
  {
    "ini": 29.68,
    "fim": 29.9,
    "p": "ser"
  },
  {
    "ini": 29.9,
    "fim": 30.46,
    "p": "explorados"
  },
  {
    "ini": 30.46,
    "fim": 30.68,
    "p": "de"
  },
  {
    "ini": 30.68,
    "fim": 30.94,
    "p": "outra"
  },
  {
    "ini": 30.94,
    "fim": 31.34,
    "p": "maneira."
  },
  {
    "ini": 32.1,
    "fim": 32.5,
    "p": "Essa"
  },
  {
    "ini": 32.5,
    "fim": 32.92,
    "p": "segunda"
  },
  {
    "ini": 32.92,
    "fim": 33.4,
    "p": "jornada"
  },
  {
    "ini": 33.4,
    "fim": 33.96,
    "p": "ampliava"
  },
  {
    "ini": 33.96,
    "fim": 34.1,
    "p": "o"
  },
  {
    "ini": 34.1,
    "fim": 34.28,
    "p": "jogo"
  },
  {
    "ini": 34.28,
    "fim": 34.46,
    "p": "e"
  },
  {
    "ini": 34.46,
    "fim": 35.02,
    "p": "reforçava"
  },
  {
    "ini": 35.02,
    "fim": 35.18,
    "p": "a"
  },
  {
    "ini": 35.18,
    "fim": 35.72,
    "p": "exploração"
  },
  {
    "ini": 35.72,
    "fim": 35.88,
    "p": "que"
  },
  {
    "ini": 35.88,
    "fim": 36.24,
    "p": "ajudou"
  },
  {
    "ini": 36.24,
    "fim": 36.36,
    "p": "a"
  },
  {
    "ini": 36.36,
    "fim": 36.84,
    "p": "consolidar"
  },
  {
    "ini": 36.84,
    "fim": 36.98,
    "p": "o"
  },
  {
    "ini": 36.98,
    "fim": 37.26,
    "p": "estilo"
  },
  {
    "ini": 37.26,
    "fim": 37.58,
    "p": "metrô"
  },
  {
    "ini": 37.58,
    "fim": 37.7,
    "p": "-de"
  },
  {
    "ini": 37.7,
    "fim": 38.08,
    "p": "-vania."
  },
  {
    "ini": 38.66,
    "fim": 39.26,
    "p": "Você"
  },
  {
    "ini": 39.26,
    "fim": 39.76,
    "p": "conseguiu"
  },
  {
    "ini": 39.76,
    "fim": 40.06,
    "p": "chegar"
  },
  {
    "ini": 40.06,
    "fim": 40.22,
    "p": "ao"
  },
  {
    "ini": 40.22,
    "fim": 40.64,
    "p": "castelo"
  },
  {
    "ini": 40.64,
    "fim": 41.26,
    "p": "invertido?"
  },
  {
    "ini": 42.04,
    "fim": 42.56,
    "p": "Conta"
  },
  {
    "ini": 42.56,
    "fim": 42.7,
    "p": "aí"
  },
  {
    "ini": 42.7,
    "fim": 42.82,
    "p": "nos"
  },
  {
    "ini": 42.82,
    "fim": 43.48,
    "p": "comentários!"
  }
];

  function calcularBoca(t) {
    for (let i = 0; i < PALAVRAS.length; i++) {
      const w = PALAVRAS[i];
      if (t >= w.ini && t <= w.fim) {
        const u = (t - w.ini) / (w.fim - w.ini);
        // Oscilação silábica ampla e expressiva (0.35 a 1.0)
        return 0.35 + 0.65 * Math.abs(Math.sin(u * Math.PI * 3.8));
      }
    }
    return 0.0;
  }

  // ---------- Atuação do Echo: Manifesto + Propulsão Matrix (vendor/echo-atuacao-sala.js, vendor/echo-propulsao-matrix.js) ----------
  const palavra = (txt) => PALAVRAS.find((w) => w.p === txt);
  let ATUACAO = null;
  const carregarScript = (src) => new Promise((resolve, reject) => {
    const el = document.createElement('script');
    el.src = src;
    el.onload = resolve;
    el.onerror = () => reject(new Error('Falha ao carregar ' + src));
    document.head.appendChild(el);
  });
  async function prepararAtuacao() {
    if (ATUACAO) return;
    if (!window.EchoAtuacaoSala) await carregarScript('vendor/echo-atuacao-sala.js');
    if (!window.EchoPropulsaoMatrix) await carregarScript('vendor/echo-propulsao-matrix.js');
    ATUACAO = window.EchoAtuacaoSala.criar({
      foco: palavra('3D.').fim,                                     // fim do gancho: volta ao controle e olha o jogo
      pousa: 40.60,                                                 // recuo da câmera: controle vai para a mesa
      cta: [palavra('Conta').ini, palavra('comentários!').fim],
      piscadas: [1.62, 5.60, 7.70, 41.55],
      piscadasFelizes: [43.75],
      apertos: [4.55, 4.87, 5.20, 6.40, 6.72],
      // sai da cadeira vindo para a frente (cresce e desce) e vem até o centro para o "conta aí"
      frente: { ini: 42.0, meio: 42.5, fim: 43.4, meioPara: { x: 330, y: 1390, k: 1.42 }, para: { x: 540, y: 1470, k: 1.7 } },
      ignicao: [42.3, 42.9]                                         // propulsão acende fora do plano da cadeira e cresce
    }, {
      // Echo sentado, controle e mãos nas posições do Manifesto (mesmo quarto)
      // na mesa: inteiro sobre o tampo (começa em X 356), na frente da caneca, longe da borda da cadeira
      x: SENTADO.x, y: SENTADO.y, k: SENTADO.k, colo: { dy: -44, rot: -0.05 }, mesa: MESA_CONTROLE,
      aponta: [385, 1160], solta: [240, 1280]
    });
  }

  // ---------- Echo Oficial no Quarto ----------
  let director = null;
  let echoCanvas = null;

  function prepararEcho() {
    if (!echoCanvas) {
      echoCanvas = document.createElement('canvas');
      echoCanvas.width = 600;
      echoCanvas.height = 600;
    }
    if (!director && window.EchoDirector) {
      director = new window.EchoDirector(echoCanvas);
    }
  }

  const MOTOR = { R: 84, GROUND: 246, HEAD_CY: -151 };
  const CIANO = [0, 212, 255];
  const SENTADO = { x: 252, y: 1338, k: 1.2 };   // mesmo lugar e tamanho do Manifesto: senta na almofada

  function posar(T, p) {
    prepararEcho();
    const k = p.k || 1.45;
    director.setSize(Math.max(8, Math.round(280 * k)));
    director.seek(T);
    const b = director.bot, s = b.s;
    const [lx, ly] = p.olhar || [0.25, -0.08];
    b.look.x = lx; b.look.y = ly;
    s.yaw = clamp(lx * 0.45 + (p.yaw || 0), -0.42, 0.42);

    // Aceno de cabeça sincronizado com a ênfase da fala
    const nod = (p.boca > 0.1) ? Math.sin(T * 6.0) * 0.04 : 0;
    s.pitch = clamp(ly * 0.28 + nod, -0.24, 0.20);
    s.tilt = p.inclina || 0;
    s.lean = p.lean || 0;
    s.sway = p.sway || 0;
    s.hands = 0;
    s.mouthOpen = p.boca != null ? p.boca : calcularBoca(T);
    s.open = p.piscada != null ? p.piscada : 1;
    s.sx *= p.sx || 1;
    s.sy *= p.sy || 1;
    b.eyeOv = p.olhos || 'pill';
    b.col = p.cor || CIANO;
    b.colT = p.cor || CIANO;
    b.draw();
    return { canvas: echoCanvas, k: k };
  }

  const pelaBase = (ctx, e, x, y) => {
    ctx.drawImage(e.canvas, x - 140 * e.k, y - MOTOR.GROUND * e.k);
  };

  // Mãos azuis oficiais segurando as laterais do controle
  function maos(ctx, k, pts) {
    const SPEC = window.RoboMotor && window.RoboMotor.THEMES && window.RoboMotor.THEMES.echo
      ? window.RoboMotor.THEMES.echo.spec
      : { hands: ['#7fe3f5', '#00d4ff'] };
    ctx.save();
    ctx.fillStyle = SPEC.hands[0];
    ctx.strokeStyle = SPEC.hands[1];
    ctx.lineWidth = 2.2 * k;
    for (const [x, y, e] of pts) {
      ctx.beginPath();
      ctx.arc(x, y, MOTOR.R * 0.18 * 0.85 * k * (e || 1), 0, Math.PI * 2);
      ctx.fill();
      ctx.stroke();
    }
    ctx.restore();
  }

  // Controle 3D visto de frente (o da v2, já desenhado como a câmera vê: botões à esquerda, direcional à direita),
  // com a parte de cima achatada para parecer visto de frente e um pouco de baixo (versão B aprovada em 01/11/2026)
  const ACHATAR_CONTROLE = 0.55;
  function controle3D(ctx, x, y, rot, esc, t) {
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(rot);
    ctx.scale(esc, esc);

    ctx.save(); ctx.translate(0, 20); ctx.scale(1, ACHATAR_CONTROLE); ctx.translate(0, -20);
    // 1. Face superior inclinada para trás
    const gradTopo = ctx.createLinearGradient(0, -110, 0, 20);
    gradTopo.addColorStop(0, '#261b40');
    gradTopo.addColorStop(0.5, '#352558');
    gradTopo.addColorStop(1, '#443070');

    ctx.beginPath();
    ctx.moveTo(-220, 20);
    ctx.bezierCurveTo(-260, -20, -250, -90, -170, -110);
    ctx.bezierCurveTo(-110, -120, -60, -95, 0, -85);
    ctx.bezierCurveTo(60, -95, 110, -120, 170, -110);
    ctx.bezierCurveTo(250, -90, 260, -20, 220, 20);
    ctx.quadraticCurveTo(110, 35, 0, 28);
    ctx.quadraticCurveTo(-110, 35, -220, 20);
    ctx.closePath();
    ctx.fillStyle = gradTopo;
    ctx.fill();
    ctx.strokeStyle = '#5a4290';
    ctx.lineWidth = 3;
    ctx.stroke();

    ctx.restore();
    // 2. Borda frontal espessa com gatilhos
    const gradBorda = ctx.createLinearGradient(0, 20, 0, 85);
    gradBorda.addColorStop(0, '#2d1f4a');
    gradBorda.addColorStop(0.6, '#1c1330');
    gradBorda.addColorStop(1, '#0e091a');

    ctx.beginPath();
    ctx.moveTo(-220, 20);
    ctx.quadraticCurveTo(-110, 35, 0, 28);
    ctx.quadraticCurveTo(110, 35, 220, 20);
    ctx.quadraticCurveTo(230, 50, 210, 80);
    ctx.quadraticCurveTo(110, 95, 0, 88);
    ctx.quadraticCurveTo(-110, 95, -210, 80);
    ctx.quadraticCurveTo(-230, 50, -220, 20);
    ctx.closePath();
    ctx.fillStyle = gradBorda;
    ctx.fill();
    ctx.strokeStyle = '#3e2a66';
    ctx.lineWidth = 3;
    ctx.stroke();

    // Gatilhos frontais
    ctx.fillStyle = '#110b20';
    ctx.fillRect(-175, 36, 85, 34);
    ctx.fillRect(90, 36, 85, 34);
    ctx.fillStyle = '#261942';
    ctx.fillRect(-172, 39, 79, 28);
    ctx.fillRect(93, 39, 79, 28);
    ctx.strokeStyle = '#4e357e';
    ctx.lineWidth = 2;
    ctx.strokeRect(-172, 39, 79, 28);
    ctx.strokeRect(93, 39, 79, 28);

    // Barra de luz LED ciana central pulsando no jogo
    const pulsoLed = 0.7 + 0.3 * Math.sin((t || 0) * 4.5);
    ctx.save();
    ctx.shadowColor = '#00d4ff';
    ctx.shadowBlur = 14 * pulsoLed;
    ctx.fillStyle = '#62f6ff';
    ctx.beginPath();
    ctx.roundRect(-28, 50, 56, 7, 3);
    ctx.fill();
    ctx.restore();

    ctx.save(); ctx.translate(0, 20); ctx.scale(1, ACHATAR_CONTROLE); ctx.translate(0, -20);
    // 3. Botões de ação em relevo 3D
    function desenharBotao(bx, by, r, corHex) {
      ctx.fillStyle = 'rgba(0,0,0,0.5)';
      ctx.beginPath(); ctx.ellipse(bx, by + 4, r, r * 0.75, 0, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#160e28';
      ctx.beginPath(); ctx.ellipse(bx, by + 2, r, r * 0.75, 0, 0, Math.PI * 2); ctx.fill();
      const gb = ctx.createRadialGradient(bx - r * 0.3, by - r * 0.3, r * 0.1, bx, by, r);
      gb.addColorStop(0, '#ffffff'); gb.addColorStop(0.35, corHex); gb.addColorStop(1, '#0e081c');
      ctx.fillStyle = gb;
      ctx.beginPath(); ctx.ellipse(bx, by, r, r * 0.75, 0, 0, Math.PI * 2); ctx.fill();
      ctx.strokeStyle = 'rgba(255,255,255,0.4)'; ctx.lineWidth = 1.5; ctx.stroke();
    }
    desenharBotao(-140, -84, 15, '#5cf5a8');
    desenharBotao(-178, -50, 15, '#62f6ff');
    desenharBotao(-102, -50, 15, '#ffd257');
    desenharBotao(-140, -16, 15, '#ff5ad8');

    // Select e Start centrais
    ctx.fillStyle = '#120b22';
    ctx.beginPath(); ctx.roundRect(-42 - 14, -40, 28, 10, 5); ctx.fill();
    ctx.beginPath(); ctx.roundRect(42 - 14, -40, 28, 10, 5); ctx.fill();

    // 4. D-PAD 3D
    const dx = 140, dy = -54;
    ctx.fillStyle = '#140c26';
    ctx.beginPath(); ctx.ellipse(dx, dy + 4, 52, 42, 0, 0, Math.PI * 2); ctx.fill();

    const wCross = 28, armL = 36;
    function pathCruz(ox, oy) {
      ctx.beginPath();
      ctx.moveTo(ox - wCross / 2, oy - armL); ctx.lineTo(ox + wCross / 2, oy - armL);
      ctx.lineTo(ox + wCross / 2, oy - wCross / 2); ctx.lineTo(ox + armL, oy - wCross / 2);
      ctx.lineTo(ox + armL, oy + wCross / 2); ctx.lineTo(ox + wCross / 2, oy + wCross / 2);
      ctx.lineTo(ox + wCross / 2, oy + armL); ctx.lineTo(ox - wCross / 2, oy + armL);
      ctx.lineTo(ox - wCross / 2, oy + wCross / 2); ctx.lineTo(ox - armL, oy + wCross / 2);
      ctx.lineTo(ox - armL, oy - wCross / 2); ctx.lineTo(ox - wCross / 2, oy - wCross / 2);
      ctx.closePath();
    }
    ctx.fillStyle = '#0f081c'; pathCruz(dx, dy + 4); ctx.fill();
    const gCross = ctx.createLinearGradient(dx, dy - armL, dx, dy + armL);
    gCross.addColorStop(0, '#423068'); gCross.addColorStop(0.5, '#2c1e48'); gCross.addColorStop(1, '#1b122e');
    pathCruz(dx, dy); ctx.fillStyle = gCross; ctx.fill();
    ctx.strokeStyle = '#63499a'; ctx.lineWidth = 2; ctx.stroke();

    // 5. Analógicos 3D
    function desenharStick(sx, sy) {
      ctx.fillStyle = '#100920'; ctx.beginPath(); ctx.ellipse(sx, sy + 3, 30, 22, 0, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#22153c'; ctx.fillRect(sx - 8, sy - 7, 16, 14);
      const gs = ctx.createRadialGradient(sx - 5, sy - 12, 2, sx, sy - 8, 20);
      gs.addColorStop(0, '#5a428a'); gs.addColorStop(0.4, '#342454'); gs.addColorStop(1, '#180f2b');
      ctx.fillStyle = gs; ctx.beginPath(); ctx.ellipse(sx, sy - 8, 22, 15, 0, 0, Math.PI * 2); ctx.fill();
      ctx.strokeStyle = '#7456b0'; ctx.lineWidth = 1.5; ctx.stroke();
    }
    desenharStick(-55, -2);
    desenharStick(55, -2);

    ctx.restore();
    ctx.restore();
  }


  // controle 3D no colo e na mesa; escala 0,21 deixa as alças (±208 no desenho) onde estão as mãos do Echo (±44 px)
  const MESA_CONTROLE = { x: 450, y: 1124, rot: 0.10 };
  function desenharControle(T, ctx) {
    const c = ATUACAO.controle(T);
    controle3D(ctx, c.x, c.y, c.rot, 0.21, T);
  }

  // a luz azul que o motor desenha embaixo do Echo some quando a propulsão acende (o motor não é alterado)
  function luzDoMotorAjustavel(bot) {
    if (bot.__luzAjustavel) return;
    const original = bot.drawGlow;
    bot.drawGlow = function (by) {
      const a = this.luzBase == null ? 1 : this.luzBase;
      if (a <= 0.01) return;
      this.x.save(); this.x.globalAlpha *= a; original.call(this, by); this.x.restore();
    };
    bot.__luzAjustavel = true;
  }

  // Echo (com a propulsão sob a base); comControle = o controle vai na frente do corpo, no colo
  function desenharEcho(T, pose, ctx, comControle) {
    const corpo = ATUACAO.corpo(T);
    prepararEcho();
    luzDoMotorAjustavel(director.bot);
    director.bot.luzBase = 1 - ATUACAO.propulsao(T);
    const e = posar(T, Object.assign({ k: corpo.k }, pose));
    const b = director.bot.base, s = director.bot.s;
    window.EchoPropulsaoMatrix.desenhar(ctx, {
      x: corpo.x + (b.x - 140) * corpo.k, y: corpo.y + (b.y - MOTOR.GROUND) * corpo.k, k: corpo.k,
      sx: s.sx, sy: s.sy, rot: s.tilt + s.lean + s.sway, intensidade: ATUACAO.propulsao(T), T
    });
    pelaBase(ctx, e, corpo.x, corpo.y);
    if (comControle) desenharControle(T, ctx);
    maos(ctx, corpo.k, ATUACAO.maos(T, ATUACAO.controle(T)));
  }


  // Instância singleton da Sala Gamer com monitor 4:3
  let SALA_INSTANCIA = null;
  function obterSala(desenharTelaCallback) {
    if (!SALA_INSTANCIA) {
      SALA_INSTANCIA = window.SetupDesenho.criar({ tela: desenharTelaCallback });
    } else {
      SALA_INSTANCIA.redesenharMesa(desenharTelaCallback);
    }
    return SALA_INSTANCIA;
  }

  // =========================================================================
  // FUNÇÃO CONTÍNUA DE RENDERIZAÇÃO POR TEMPO t (0.0s a 44.57s)
  // Monitor Retrô 4:3 Nativo (Zero corte, zero espaço vazio)
  // =========================================================================
  async function renderizarQuadro(ctx, t, customVideo) {
    await carregarAssets();
    await prepararAtuacao();
    ctx.clearRect(0, 0, W, H);

    const vid = customVideo || _videoElement;

    // -----------------------------------------------------------------------
    // CÁLCULO CONTÍNUO DA CÂMERA (ZOOM E POSIÇÃO)
    // 0.0s a 3.94s: Plano aberto 1.0x (Echo apresenta com TV mostrando título aos 14s)
    // 3.94s a 8.88s: Push-in suave 1.0x -> 2.4x em direção ao monitor
    // 8.88s a 35.5s: Close 2.4x no monitor 4:3 (gameplay pleno com moldura e quarto ao redor)
    // 35.5s a 38.0s: Recuo antecipado suave 2.4x -> 1.0x retornando ao quarto
    // 38.0s a 44.57s: Plano aberto 1.0x com Echo frontal entregando pergunta final à comunidade
    // -----------------------------------------------------------------------
    let u = 0.0;
    if (t <= 3.94) {
      u = 0.0;
    } else if (t > 3.94 && t <= 8.88) {
      u = eIO(invLerp(3.94, 8.88, t));
    } else if (t > 8.88 && t <= 39.80) {
      u = 1.0;
    } else if (t > 39.80 && t <= 41.60) {
      // Recuo suave no meio da pergunta final
      u = 1.0 - eIO(invLerp(39.80, 41.60, t));
    } else {
      u = 0.0;
    }

    const z = lerp(1.0, 3.333, u);
    const camX = lerp(540, 660, u);
    const camY = lerp(960, 941.5, u);

    // Callback de desenho do conteúdo ativo no monitor 4:3 nativo (w: 324, h: 243)
    const desenharTelaNaMesa = (c, TELA) => {
      c.fillStyle = '#06020c';
      c.fillRect(TELA.x, TELA.y, TELA.w, TELA.h);

      const source = (vid && vid.readyState >= 2 && vid.videoWidth > 0)
        ? vid
        : (ASSETS.gameplayFallback || null);

      if (source) {
        const sw = source.videoWidth || source.naturalWidth || source.width;
        const sh = source.videoHeight || source.naturalHeight || source.height;
        // Preenchimento perfeito 4:3 de ponta a ponta sem sobrar espaço e sem cortar nada!
        c.drawImage(source, 0, 0, sw, sh, TELA.x, TELA.y, TELA.w, TELA.h);
      }

      // Linhas suaves de scanline CRT retrô
      c.fillStyle = 'rgba(0,0,0,0.12)';
      for (let sy = TELA.y; sy < TELA.y + TELA.h; sy += 3) {
        c.fillRect(TELA.x, sy, TELA.w, 1.2);
      }

      // Reflexo sutil de vidro na tela
      c.fillStyle = 'rgba(255,255,255,0.05)';
      c.beginPath();
      c.moveTo(TELA.x, TELA.y);
      c.lineTo(TELA.x + 120, TELA.y);
      c.lineTo(TELA.x + 35, TELA.y + TELA.h);
      c.lineTo(TELA.x, TELA.y + TELA.h);
      c.closePath();
      c.fill();
    };

    // Obtém a sala gamer com o monitor 4:3 redesenhado
    const SALA = obterSala(desenharTelaNaMesa);

    ctx.save();
    if (z !== 1.0) {
      ctx.translate(W / 2, H / 2);
      ctx.scale(z, z);
      ctx.translate(-camX, -camY);
    }

    // Renderiza todas as camadas oficiais do quarto
    for (const nome of Object.keys(SALA.L)) {
      ctx.globalCompositeOperation = nome === 'luz' ? 'screen' : 'source-over';
      ctx.drawImage(SALA.L[nome], 0, 0);

      // Echo: sentado, logo após a cadeira; vindo para a frente, depois do pufe (a luz e o acabamento continuam por cima)
      if (u < 0.98) {
        const naFrente = ATUACAO.corpo(t).frente;
        ctx.globalCompositeOperation = 'source-over';
        if (nome === 'cadeira') {
          if (naFrente) desenharControle(t, ctx);
          else desenharEcho(t, ATUACAO.pose(t), ctx, true);
        } else if (nome === 'pufe' && naFrente) {
          desenharEcho(t, ATUACAO.pose(t), ctx, false);
        }
      }
    }

    ctx.restore();
    ctx.globalCompositeOperation = 'source-over';
  }

  async function desenharQuadro01(ctx) {
    await renderizarQuadro(ctx, 1.0);
  }

  window.CenasV2 = {
    renderizarQuadro,
    desenharQuadro01,
    setVideoElement,
    PALAVRAS
  };

})(window);
