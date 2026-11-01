/**
 * cenas_v2.js - Motor Contínuo do Vídeo Híbrido Castlevania SOTN
 * Quarto Gamer + Monitor Retrô 4:3 Nativo (Zero Corte, Zero Espaço Vazio)
 * Echo Vivo (Respiração 7px, Balanço Corporal, Mãos e Controle em Ação, Nods, Blinks e Lip-Sync)
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

  // ---------- Dinâmica Orgânica e Viva do Echo Gamer ----------
  function calcularPiscada(t) {
    const ciclo = t % 2.8;
    if (ciclo >= 1.0 && ciclo <= 1.15) {
      const p = (ciclo - 1.0) / 0.15;
      return 0.05 + 0.95 * Math.abs(Math.cos(p * Math.PI));
    }
    return 1.0;
  }

  function calcularHover(t) {
    // Impulso dinâmico de gamer subindo e respirando na cadeira (amplitude até 13px)
    const base = Math.sin(t * 2.4) * 8.0;
    const pulso = (t >= 3.94 && t < 39.80) ? Math.sin(t * 4.0) * 5.0 : Math.sin(t * 1.8) * 3.0;
    return base + pulso;
  }

  function calcularInclinacao(t) {
    if (t >= 3.94 && t <= 8.88) {
      // Ao iniciar o zoom, inclina a cabeça em direção ao monitor
      const p = eIO(invLerp(3.94, 5.0, t));
      return lerp(0.02, 0.07, p) + Math.sin(t * 2.0) * 0.03;
    }
    if (t > 8.88 && t < 39.80) {
      return 0.06 + Math.sin(t * 2.0) * 0.03;
    }
    if (t >= 39.80 && t <= 41.60) {
      const p = eIO(invLerp(39.80, 41.40, t));
      return lerp(0.06, 0.0, p);
    }
    return Math.sin(t * 1.5) * 0.04;
  }

  function calcularLean(t) {
    // Tronco se projeta para a frente na direção da mesa quando focado na TV
    if (t >= 3.94 && t < 39.80) {
      const p = eIO(invLerp(3.94, 5.2, t));
      return lerp(0.03, 0.11, p) + Math.sin(t * 1.8) * 0.03;
    }
    if (t >= 39.80 && t <= 41.60) {
      const p = eIO(invLerp(39.80, 41.40, t));
      return lerp(0.11, -0.02, p);
    }
    return Math.sin(t * 1.4) * 0.05;
  }

  function calcularSway(t) {
    return Math.sin(t * 0.9) * 0.04;
  }

  function calcularYaw(t) {
    // Rotação da cabeça: vira ativamente para a direita (TV) quando o zoom inicia
    if (t >= 3.94 && t < 39.80) {
      const p = eIO(invLerp(3.94, 5.0, t));
      return lerp(0.05, 0.35, p);
    }
    if (t >= 39.80 && t <= 41.60) {
      const p = eIO(invLerp(39.80, 41.40, t));
      return lerp(0.35, 0.0, p);
    }
    return Math.sin(t * 1.2) * 0.05;
  }

  function calcularOlhar(t) {
    // 0.0s a 3.94s: Olhar confiante acompanhando o gancho
    if (t < 3.94) {
      return [0.18 + Math.sin(t * 1.5) * 0.10, -0.06];
    }
    // 3.94s a 8.88s: Transição do olhar virando direto para a tela da TV à direita
    if (t >= 3.94 && t <= 8.88) {
      const p = eIO(invLerp(3.94, 4.80, t));
      return [
        lerp(0.18, 0.72, p) + Math.sin(t * 2.2) * 0.04,
        lerp(-0.06, -0.28, p)
      ];
    }
    // 8.88s a 39.80s: Foco visual total no jogo Castlevania na TV
    if (t > 8.88 && t < 39.80) {
      return [
        0.72 + Math.sin(t * 1.8) * 0.05,
        -0.28 + Math.sin(t * 2.4) * 0.03
      ];
    }
    // 39.80s a 40.80s: Olhar rastreia o controle descendo ate a mesa gamer
    if (t >= 39.80 && t < 40.80) {
      const uDesk = invLerp(39.80, 40.80, t);
      return [
        lerp(0.72, 0.42, uDesk),
        lerp(-0.28, 0.16, uDesk)
      ];
    }
    // 40.80s a 41.60s: Retorna o foco frontal para o espectador
    if (t >= 40.80 && t <= 41.60) {
      const uAud = eIO(invLerp(40.80, 41.60, t));
      return [
        lerp(0.42, 0.0, uAud),
        lerp(0.16, -0.05, uAud)
      ];
    }
    // 41.60s em diante: Olhar frontal com calor no CTA
    return [0.0, -0.05];
  }

  function calcularOlhos(t) {
    if (t < 3.94) return 'pill';
    if (t >= 3.94 && t < 39.80) return 'dot';
    if (t >= 39.80 && t < 41.50) return 'pill';
    return 'happy'; // 41.50s a 44.57s: CTA com olhos em arco ^ ^
  }

  function calcularSx(t) {
    // Squash & stretch no ritmo da empolgação
    return 1.0 - Math.sin(t * 2.4) * 0.03;
  }

  function calcularSy(t) {
    return 1.0 + Math.sin(t * 2.4) * 0.05;
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
  const SENTADO = { x: 252, y: 1305, k: 1.45 };

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
    s.open = p.piscada != null ? p.piscada : calcularPiscada(T);
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

  // Controle de videogame com perspectiva 3D frontal, iluminação e botões em ação
  function desenharControleNovo(ctx, x, y, rot, esc, t) {
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(rot);
    ctx.scale(esc, esc);

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
  }

  function echoSentado(T, pose, ctx) {
    const hover = pose.hover || 0;
    const x = SENTADO.x;
    const y = SENTADO.y + hover;
    const k = SENTADO.k;

    pelaBase(ctx, posar(T, Object.assign({ k }, pose)), x, y);

    // Posição base do controle no colo
    const ctrlY_lap = y - 56 + Math.sin(T * 3.0) * 4.0;
    const ctrlTilt_lap = -0.04 + Math.sin(T * 3.6) * 0.05;
    const ctrlEsc = 0.25;

    let ctrlX = x;
    let ctrlY = ctrlY_lap;
    let ctrlTilt = ctrlTilt_lap;

    // A partir de 39.80s: trajetória em arco para a mesa (repouso em [360, 1122])
    if (T >= 39.80) {
      const uDesk = clamp((T - 39.80) / 1.0, 0, 1);
      const eDesk = eIO(uDesk);
      const arcY = -18 * Math.sin(Math.PI * eDesk);
      ctrlX = lerp(x, 360, eDesk);
      ctrlY = lerp(ctrlY_lap, 1122, eDesk) + arcY;
      ctrlTilt = lerp(ctrlTilt_lap, 0.08, eDesk);
    }
    desenharControleNovo(ctx, ctrlX, ctrlY, ctrlTilt, ctrlEsc, T);

    const maoOffset = 52;
    const cicloAperto = (T * 3.5) % 1.0;
    const apEsq = cicloAperto < 0.22 ? 0.84 : 1.0;
    const apDir = ((T * 3.5 + 0.35) % 1.0) < 0.25 ? 0.84 : 1.0;

    // Mão Direita: acompanha o controle até o repouso na mesa
    const mDirX = ctrlX + maoOffset;
    const mDirY = ctrlY + 6;
    const mDirEsc = (T >= 40.80) ? 1.0 : apDir;

    // Mão Esquerda com fases expressivas
    let mEsqX = ctrlX - maoOffset;
    let mEsqY = ctrlY + 6;
    let mEsqEsc = apEsq;

    if (T < 3.94) {
      // Fala inicial: mão gesticula livre no ar
      mEsqX = x - 72 + Math.sin(T * 3.8) * 9;
      mEsqY = y - 76 + Math.cos(T * 2.8) * 11;
      mEsqEsc = 1.08;
    } else if (T >= 3.94 && T < 4.60) {
      // Transição suave para o controle ao entrar no jogo
      const uJoin = eIO(invLerp(3.94, 4.60, T));
      const livreX = x - 72 + Math.sin(3.94 * 3.8) * 9;
      const livreY = y - 76 + Math.cos(3.94 * 2.8) * 11;
      mEsqX = lerp(livreX, ctrlX - maoOffset, uJoin);
      mEsqY = lerp(livreY, ctrlY + 6, uJoin);
      mEsqEsc = lerp(1.08, apEsq, uJoin);
    } else if (T >= 40.80) {
      // Descola do controle e gesticula para o espectador no CTA
      const uSolta = eIO(invLerp(40.80, 41.60, T));
      const ctaX = x - 68 + Math.sin(T * 3.2) * 8;
      const ctaY = y - 88 + Math.cos(T * 2.4) * 10;
      mEsqX = lerp(ctrlX - maoOffset, ctaX, uSolta);
      mEsqY = lerp(ctrlY + 6, ctaY, uSolta);
      mEsqEsc = lerp(1.0, 1.12, uSolta);
    }

    maos(ctx, k, [
      [mEsqX, mEsqY, mEsqEsc],
      [mDirX, mDirY, mDirEsc]
    ]);
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

      // Echo sentado na cadeira gamer (desenhado logo após a cadeira)
      if (nome === 'cadeira') {
        if (u < 0.98) {
          ctx.globalCompositeOperation = 'source-over';
          echoSentado(t, {
            olhar: calcularOlhar(t),
            yaw: calcularYaw(t),
            inclina: calcularInclinacao(t),
            lean: calcularLean(t),
            sway: calcularSway(t),
            hover: calcularHover(t),
            sx: calcularSx(t),
            sy: calcularSy(t),
            piscada: calcularPiscada(t),
            boca: calcularBoca(t),
            olhos: calcularOlhos(t)
          }, ctx);
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
