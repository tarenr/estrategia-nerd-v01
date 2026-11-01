/**
 * echo-director.js
 * Adaptador determinístico para orquestrar o Echo com Lip-Sync analítico no OneTake
 * Manifesto Estratégia Nerd — "A Morte do Achismo"
 */
(function(window) {
  'use strict';

  // Curvas de interpolação analítica
  const clamp = (v, min, max) => Math.max(min, Math.min(max, v));
  const lerp = (a, b, t) => a + (b - a) * t;
  const easeOutCubic = t => 1 - Math.pow(1 - clamp(t, 0, 1), 3);
  const easeInOutCubic = t => {
    t = clamp(t, 0, 1);
    return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
  };

  // Envelope fonético extraído diretamente do áudio oficial de Cornelius (210 frames a 60 fps)
  const LIP_SYNC = [
    0.0, 0.0, 0.101, 0.434, 0.768, 0.999, 0.993, 0.981, 0.923, 0.882, 
    0.851, 0.855, 0.902, 0.946, 1.0, 1.0, 0.818, 0.485, 0.152, 0.086, 
    0.392, 0.726, 0.965, 0.898, 0.73, 0.563, 0.458, 0.482, 0.516, 0.673, 
    0.817, 0.958, 0.975, 0.939, 0.891, 0.861, 0.804, 0.706, 0.566, 0.393, 
    0.205, 0.067, 0.0, 0.0, 0.103, 0.226, 0.337, 0.332, 0.28, 0.169, 
    0.071, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 
    0.0, 0.0, 0.0, 0.0, 0.097, 0.323, 0.656, 0.795, 0.801, 0.685, 
    0.674, 0.57, 0.353, 0.127, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 
    0.0, 0.072, 0.072, 0.406, 0.667, 0.876, 0.726, 0.606, 0.637, 0.542, 
    0.56, 0.635, 0.88, 0.921, 0.742, 0.409, 0.137, 0.0, 0.0, 0.075, 
    0.346, 0.679, 0.937, 1.0, 1.0, 1.0, 0.988, 0.968, 0.866, 0.779, 
    0.629, 0.591, 0.54, 0.554, 0.579, 0.672, 0.768, 0.833, 0.873, 0.932, 
    0.983, 1.0, 1.0, 0.897, 0.71, 0.466, 0.319, 0.267, 0.319, 0.478, 
    0.695, 0.852, 0.81, 0.594, 0.549, 0.615, 0.792, 0.784, 0.746, 0.677, 
    0.623, 0.636, 0.766, 0.857, 0.871, 0.773, 0.752, 0.711, 0.67, 0.563, 
    0.531, 0.472, 0.45, 0.386, 0.38, 0.402, 0.404, 0.382, 0.336, 0.308, 
    0.271, 0.161, 0.07, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 
    0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 
    0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 
    0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 
    0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0
  ];

  class EchoDirector {
    constructor(canvas) {
      if (!canvas) throw new Error("Canvas element required for EchoDirector");
      this.canvas = canvas;
      
      if (!window.RoboMotor || !window.RoboMotor.RoboBot) {
        throw new Error("RoboMotor.RoboBot not loaded");
      }

      this.bot = new window.RoboMotor.RoboBot(canvas, {
        theme: window.RoboMotor.THEMES.echo
      });

      // Configurações base determinísticas
      this.bot.t0 = 0;
      this.bot.last = 0;
      this.bot.tw = [];
      this.bot.lock = {};
      this.bot.parts = [];
      this.bot.confetti = [];
      this.bot.speaking = false;
      this.bot.accessory = 'none';

      // Cores hex
      this.cyanCol = [0, 212, 255];   // #00d4ff
      this.amberCol = [245, 165, 36]; // #F5A524 (dúvida/alerta)
    }

    setSize(sizePx) {
      this.bot.setSize(sizePx);
    }

    /**
     * Seek analítico determinístico para o tempo t (segundos)
     */
    seek(t) {
      window.__currentTimeMs = t * 1000;
      const b = this.bot;
      const s = b.s;

      b.tw = [];
      b.lock = {};
      b.t0 = 0;
      b.last = t * 1000;

      // 1. Sincronização Labial (Lip-Sync com a fala de Cornelius)
      // Mapeamento exato de frame com interpolação linear contínua
      const frameF = clamp(t * 60, 0, 209);
      const fIdx = Math.floor(frameF);
      const frac = frameF - fIdx;
      const m1 = LIP_SYNC[fIdx] || 0.0;
      const m2 = LIP_SYNC[Math.min(209, fIdx + 1)] || 0.0;
      let mouthVal = lerp(m1, m2, frac);

      // Silêncio absoluto durante o hold final (após 2.40s)
      if (t >= 2.35) {
        mouthVal = 0.0;
      }
      s.mouthOpen = mouthVal;

      // 2. Respiração senoidal contínua
      s.sy = 1 + Math.sin(t * 2.4) * 0.022;
      s.sx = 1 - Math.sin(t * 2.4) * 0.014;

      // 3. Piscadas determinísticas em instantes fixos
      let open = 1.0;
      if (t >= 0.35 && t <= 0.48) {
        const p = (t - 0.35) / 0.13;
        open = 1.0 - 0.95 * Math.sin(p * Math.PI);
      } else if (t >= 2.80 && t <= 2.93) {
        const p = (t - 2.80) / 0.13;
        open = 1.0 - 0.95 * Math.sin(p * Math.PI);
      }
      s.open = clamp(open, 0.05, 1.0);

      // 4. Atuação por Planos Narrativos:
      // - Plano 1 (0.00 a 0.65s): Apresentação em Destaque. Contato visual com a câmera.
      // - Plano 2 (0.65 a 1.40s): Plano Compartilhado. Echo lateral, olha p/ fluxo à direita.
      // - Plano 3 (1.40 a 2.20s): Conteúdo em Tela Cheia (Echo oculto no corte).
      // - Plano 4 (2.20 a 3.50s): Retorno do Echo. Olhar frontal, autoridade, boca fecha e hold estável.

      let targetLookX = 0;
      let targetLookY = 0;
      let targetTilt = 0;
      let targetLean = 0;
      let eyeShape = 'pill';
      let colTarget = this.cyanCol;
      let handsTarget = 0;

      if (t < 0.65) {
        // Plano 1: Contato visual direto enquanto fala
        targetLookX = 0;
        targetLookY = 0;
        targetTilt = Math.sin(t * 4) * 0.03; // leve balanço natural de fala
        targetLean = 0;
        eyeShape = 'pill';
        colTarget = this.cyanCol;
        handsTarget = mouthVal > 0.4 ? 0.6 : 0.2; // mãos acompanham a fala
      } else if (t >= 0.65 && t < 1.40) {
        // Plano 2: Vira a cabeça e olhar para o fluxo de notícias
        const prog = easeOutCubic((t - 0.65) / 0.30);
        targetLookX = lerp(0, 0.75, prog);
        targetLookY = lerp(0, -0.15, prog);
        targetTilt = lerp(0, 0.06, prog);
        targetLean = lerp(0, 0.04, prog);
        eyeShape = 'pill';
        colTarget = this.cyanCol;
        handsTarget = lerp(0.2, 0.85, prog); // mão aponta para o fluxo
      } else if (t >= 1.40 && t < 2.30) {
        // Plano 3: Echo PERMANECE VISÍVEL no terço inferior, observando e reagindo com desconfiança
        const prog3 = easeOutCubic((t - 1.40) / 0.25);
        targetLookX = lerp(0.75, 0.55, prog3); // olha para cima e para a direita (em direção ao card)
        targetLookY = lerp(-0.15, -0.65, prog3); // cabeça erguida olhando a manchete
        targetTilt = 0.10; // inclinação de dúvida / questionamento
        targetLean = -0.05;
        // Expressão de desconfiança: olhos flat (semicerrados) com cor âmbar de alerta editorial
        eyeShape = t > 1.70 ? 'flat' : 'pill';
        colTarget = t > 1.70 ? this.amberCol : this.cyanCol;
        handsTarget = 0.7; // mão aberta em pose interrogativa
      } else {
        // Plano 4: Retorno ao primeiro plano com autoridade no Clímax (2.30 a 3.50s)
        const retProg = easeOutCubic((t - 2.30) / 0.25);
        targetLookX = lerp(0.55, 0, retProg);
        targetLookY = lerp(-0.65, 0, retProg);
        targetTilt = lerp(0.10, 0, retProg);
        targetLean = lerp(-0.05, 0, retProg);
        eyeShape = 'pill';
        colTarget = this.cyanCol;
        handsTarget = lerp(0.7, 0, retProg); // postura firme e sóbria
      }

      b.look.x = targetLookX;
      b.look.y = targetLookY;
      s.yaw = clamp(targetLookX * 0.45, -0.42, 0.42);
      s.pitch = clamp(targetLookY * 0.28, -0.24, 0.16);
      s.tilt = targetTilt;
      s.lean = targetLean;
      s.sway = 0;
      s.hands = handsTarget;
      b.eyeOv = eyeShape;
      b.col = colTarget;
      b.colT = colTarget;
      b.state = 'idle';
      b.badge = null;

      b.draw();
    }
  }

  window.EchoDirector = EchoDirector;
})(window);
