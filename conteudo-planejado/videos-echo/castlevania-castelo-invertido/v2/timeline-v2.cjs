(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  if (root) root.TimelineV2 = api;
})(typeof window !== 'undefined' ? window : globalThis, function () {
  'use strict';

  const FONTES_APROVADAS = Object.freeze({
    gameplay: 'GamePlay.mp4',
    mapa: 'sotnmap1-SF.png',
    musica: 'Bloody Tears.mp3',
  });
  const MUSICA_ORIGEM_INICIO = 22;

  const SEGMENTOS_GAMEPLAY = [
    { id: 'titulo', tipo: 'video', inicio: 0.00, fim: 4.88, origemInicio: 3.00, origemFim: 4.40 },
    { id: 'classico-2d', tipo: 'video', inicio: 4.88, fim: 8.88, origemInicio: 5.00, origemFim: 9.00 },
    { id: 'richter-controlado', tipo: 'video', inicio: 8.88, fim: 13.60, origemInicio: 35.00, origemFim: 39.72 },
    { id: 'batalha-richter', tipo: 'video', inicio: 13.60, fim: 17.00, origemInicio: 60.00, origemFim: 63.40 },
    { id: 'shaft', tipo: 'video', inicio: 17.00, fim: 20.30, origemInicio: 84.00, origemFim: 87.30 },
    { id: 'castelo', tipo: 'video', inicio: 20.30, fim: 22.80, origemInicio: 101.00, origemFim: 103.50 },
    { id: 'inversao', tipo: 'video', inicio: 22.80, fim: 26.70, origemInicio: 111.00, origemFim: 114.90 },
    { id: 'exploracao', tipo: 'video', inicio: 26.70, fim: 31.30, origemInicio: 199.00, origemFim: 203.60 },
    { id: 'mapa', tipo: 'imagem', fonte: 'sotnmap1-SF.png', inicio: 31.30, fim: 35.50 },
    { id: 'retorno', tipo: 'video', inicio: 35.50, fim: 44.57, origemInicio: 204.00, origemFim: 213.07 },
  ];

  function clamp(v, min, max) {
    return Math.max(min, Math.min(max, v));
  }

  function obterSegmento(t) {
    const tempo = clamp(Number(t) || 0, 0, SEGMENTOS_GAMEPLAY.at(-1).fim);
    return SEGMENTOS_GAMEPLAY.find((segmento, i) =>
      tempo >= segmento.inicio && (tempo < segmento.fim || i === SEGMENTOS_GAMEPLAY.length - 1)
    );
  }

  function calcularEnquadramentoMonitor(origem, monitor) {
    const origemAspecto = origem.w / origem.h;
    const destinoAspecto = monitor.w / monitor.h;
    let sx = 0;
    let sy = 0;
    let sw = origem.w;
    let sh = origem.h;

    if (origemAspecto > destinoAspecto) {
      sw = origem.h * destinoAspecto;
      sx = (origem.w - sw) / 2;
    } else if (origemAspecto < destinoAspecto) {
      sh = origem.w / destinoAspecto;
      sy = (origem.h - sh) / 2;
    }

    const escalaPrincipal = Math.min(monitor.w / origem.w, monitor.h / origem.h);
    const principalW = origem.w * escalaPrincipal;
    const principalH = origem.h * escalaPrincipal;

    return {
      fundo: {
        origem: { sx, sy, sw, sh },
        dx: monitor.x,
        dy: monitor.y,
        dw: monitor.w,
        dh: monitor.h,
      },
      principal: {
        origem: { sx: 0, sy: 0, sw: origem.w, sh: origem.h },
        dx: monitor.x + (monitor.w - principalW) / 2,
        dy: monitor.y + (monitor.h - principalH) / 2,
        dw: principalW,
        dh: principalH,
      },
    };
  }

  function calcularAtuacaoEcho(t) {
    if (t < 3.94) {
      return {
        acao: 'apresenta',
        deslocamentoX: -10 + Math.sin(t * 1.8) * 4,
        deslocamentoY: Math.sin(t * 2.2) * 3,
        yaw: -0.08 + Math.sin(t * 1.1) * 0.04,
        inclinacao: -0.035,
        maoLivre: t > 1.25 && t < 3.55 ? 1 : 0,
      };
    }
    if (t < 8.88) {
      const p = clamp((t - 3.94) / 1.4, 0, 1);
      return {
        acao: 'olha-monitor',
        deslocamentoX: -10 + 30 * p,
        deslocamentoY: -4,
        yaw: 0.28 * p,
        inclinacao: 0.065 * p,
        maoLivre: 0,
      };
    }
    if (t >= 38.0) {
      const pulso = Math.sin((t - 38) * 1.25);
      return {
        acao: 'pergunta-publico',
        deslocamentoX: 12 + pulso * 7,
        deslocamentoY: -5 + Math.sin(t * 2.0) * 3,
        yaw: -0.04,
        inclinacao: pulso * 0.045,
        maoLivre: t > 40.8 && t < 43.8 ? -1 : 0,
      };
    }
    return {
      acao: 'acompanha-gameplay',
      deslocamentoX: 18,
      deslocamentoY: -4,
      yaw: 0.24,
      inclinacao: 0.04,
      maoLivre: 0,
    };
  }

  return {
    FONTES_APROVADAS,
    MUSICA_ORIGEM_INICIO,
    SEGMENTOS_GAMEPLAY,
    obterSegmento,
    calcularEnquadramentoMonitor,
    calcularAtuacaoEcho,
  };
});
