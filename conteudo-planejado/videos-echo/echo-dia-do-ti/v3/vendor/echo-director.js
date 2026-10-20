/**
 * echo-director.js - Adaptador determinístico para orquestrar o robô Echo nas cenas do Dia do TI
 * Configura poses, ângulos de visão, expressões e acessórios para cada quadro-chave.
 */
(function(window) {
  'use strict';

  class EchoDirectorTI {
    constructor(canvas, opts = {}) {
      if (!canvas) throw new Error("Canvas element required for EchoDirectorTI");
      this.canvas = canvas;

      if (!window.RoboMotor || !window.RoboMotor.RoboBot) {
        throw new Error("RoboMotor.RoboBot not loaded");
      }

      this.bot = new window.RoboMotor.RoboBot(canvas, {
        theme: window.RoboMotor.THEMES.echo
      });

      // Tamanho padrão
      const size = opts.size || 550;
      this.bot.setSize(size);

      // Vestir a fantasia de TI oficial
      if (window.RoboRoupas && this.bot.wardrobe) {
        this.bot.wardrobe.wear('headsetTI');
        this.bot.wardrobe.wear('oculosProgramador');
        this.bot.wardrobe.wear('moletomNerd');
      }

      // Parâmetros determinísticos
      this.bot.t0 = 0;
      this.bot.last = 0;
      this.bot.tw = [];
      this.bot.lock = {};
      this.bot.parts = [];
      this.bot.confetti = [];
      this.bot.speaking = false;
      this.bot.accessory = 'none';

      // Cores oficiais
      this.colCyan = [0, 212, 255];    // #00d4ff (padrão)
      this.colAmber = [245, 165, 36];  // #f5a524 (dúvida / alerta)
      this.colGreen = [34, 197, 94];   // #22c55e (sucesso / comemoração)
    }

    setSize(sizePx) {
      this.bot.setSize(sizePx);
    }

    /**
     * Aplica pose específica de cada quadro-chave (K1 a K8)
     */
    setPose(key, opts = {}) {
      const b = this.bot;
      const s = b.s;

      // Reset base
      b.tw = [];
      b.lock = {};
      s.sy = 1.0;
      s.sx = 1.0;
      s.open = 1.0;
      s.mouthOpen = 0.0;
      s.tilt = 0;
      s.lean = 0;
      s.sway = 0;
      b.state = 'idle';
      b.badge = null;
      b.accessory = 'none';
      b.eyeOv = null;
      b.col = this.colCyan;
      b.colT = this.colCyan;

      switch(key) {
        case 'K2': // Ei, você aí do TI! Saindo do rack e acenando
          b.look.x = -0.15;
          b.look.y = 0.05;
          s.yaw = -0.12;
          s.pitch = 0.04;
          s.tilt = -0.06;
          s.lean = 0.08; // Leve inclinação para frente saindo do rack
          s.hands = 0.85; // Mão erguida em aceno
          s.mouthOpen = 0.45; // Falando "Ei, você aí do TI!"
          b.eyeOv = 'pill';
          break;

        case 'K3': // Faz backup: olhando para o monitor/HD
          b.look.x = -0.65; // Olhando para a esquerda (onde está o carrinho com o monitor)
          b.look.y = 0.25;  // Olhando um pouco para baixo
          s.yaw = -0.32;
          s.pitch = 0.18;
          s.tilt = 0.08;
          s.hands = 0.95; // Mãos estendidas segurando o HD externo
          s.mouthOpen = 0.15;
          b.eyeOv = 'pill';
          break;

        case 'K4': // E resolve quase tudo com: Dúvida diante dos cabos
          b.look.x = 0.55;  // Olhando para a direita/cima (para o emaranhado e o alerta)
          b.look.y = -0.35;
          s.yaw = 0.28;
          s.pitch = -0.22;
          s.tilt = 0.16;   // Inclinação de confusão
          s.lean = -0.06;
          s.hands = 0.5;   // Mão perto da cabeça / gesto interrogativo
          s.mouthOpen = 0.0;
          b.eyeOv = 'flat'; // Olhos semicerrados de estranhamento
          b.col = this.colAmber; // Alerta/dúvida em âmbar
          b.colT = this.colAmber;
          break;

        case 'K5': // Já tentou reiniciar? Mão puxando plugue
          b.look.x = 0.4;
          b.look.y = 0.3;
          s.yaw = 0.2;
          s.pitch = 0.15;
          s.tilt = 0.05;
          s.hands = 1.0;   // Mão puxando
          s.mouthOpen = 0.2;
          b.eyeOv = 'pill';
          b.col = this.colCyan;
          b.colT = this.colCyan;
          break;

        case 'K6': // Hoje o dia é seu: Brinde com café
          b.look.x = 0.0;  // Olhar direto nos olhos do espectador
          b.look.y = -0.05;
          s.yaw = 0.0;
          s.pitch = -0.02;
          s.tilt = 0.04;
          s.hands = 1.0;   // Mão erguida em pose de brinde
          s.mouthOpen = 0.35; // Sorriso calmo e confiante
          b.eyeOv = 'pill';
          b.accessory = 'none'; // Usar a caneca cartoon exclusiva desenhada na cena
          break;

        case 'K7': // Feliz dia do profissional de TI! Comemoração
          b.look.x = 0.0;
          b.look.y = -0.15;
          s.yaw = 0.0;
          s.pitch = -0.1;
          s.tilt = -0.05;
          s.hands = 1.0;   // Mãos erguidas celebrando
          s.mouthOpen = 0.65; // Boca aberta comemorando
          b.eyeOv = 'happy'; // Olhos felizes em arco
          b.col = this.colGreen;
          b.colT = this.colGreen;
          break;

        case 'K8': // Fecho com caveira e marca
          b.look.x = 0.0;
          b.look.y = 0.0;
          s.yaw = 0.0;
          s.pitch = 0.0;
          s.tilt = 0.02;
          s.hands = 0.4;
          s.mouthOpen = 0.0;
          b.eyeOv = 'pill';
          b.col = this.colCyan;
          b.colT = this.colCyan;
          break;

        default:
          b.look.x = 0;
          b.look.y = 0;
          s.yaw = 0;
          s.pitch = 0;
          s.hands = 0;
          break;
      }

      b.draw();
    }
  }

  window.EchoDirectorTI = EchoDirectorTI;
})(typeof window !== 'undefined' ? window : globalThis);
