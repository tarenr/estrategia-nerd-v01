// Protótipo isolado — fantasia do Echo inspirada em Alucard, de Castlevania SOTN.
// Não altera o catálogo compartilhado até aprovação visual.
(function (root) {
  'use strict';

  const Roupas = root.RoboRoupas;
  if (!Roupas) throw new Error('RoboRoupas deve ser carregado antes de alucard-roupa.js');

  const { BODY, clipBodyWithoutScreen, bodyOutline } = Roupas.shape;
  const OURO = '#c9a34a';
  const OURO_CLARO = '#f1d788';
  const PRETO = '#11111a';
  const VINHO = '#4a142d';
  const BRANCO = '#eef2f7';

  function cabeloTras(ctx) {
    ctx.save();
    const grad = ctx.createLinearGradient(-82, -225, 70, -70);
    grad.addColorStop(0, '#ffffff');
    grad.addColorStop(0.5, '#dce4ed');
    grad.addColorStop(1, '#8e9aaa');
    ctx.fillStyle = grad;
    ctx.strokeStyle = '#586372';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(-68, -190);
    ctx.bezierCurveTo(-96, -155, -82, -96, -57, -72);
    ctx.lineTo(-39, -102);
    ctx.bezierCurveTo(-53, -67, -25, -50, -12, -81);
    ctx.bezierCurveTo(-5, -55, 20, -57, 18, -88);
    ctx.bezierCurveTo(50, -58, 70, -83, 52, -112);
    ctx.bezierCurveTo(93, -90, 91, -139, 67, -175);
    ctx.closePath();
    ctx.fill();
    ctx.stroke();
    ctx.restore();
  }

  function cabeloFrente(ctx) {
    ctx.save();
    const grad = ctx.createLinearGradient(-70, -230, 65, -130);
    grad.addColorStop(0, '#ffffff');
    grad.addColorStop(0.62, '#e4eaf1');
    grad.addColorStop(1, '#98a5b4');
    ctx.fillStyle = grad;
    ctx.strokeStyle = '#657180';
    ctx.lineWidth = 1.8;
    ctx.beginPath();
    ctx.moveTo(-67, -185);
    ctx.bezierCurveTo(-66, -220, -35, -235, -7, -226);
    ctx.bezierCurveTo(20, -242, 54, -216, 63, -183);
    ctx.bezierCurveTo(45, -198, 36, -184, 30, -161);
    ctx.bezierCurveTo(17, -188, 4, -185, -5, -149);
    ctx.bezierCurveTo(-16, -186, -36, -178, -47, -144);
    ctx.bezierCurveTo(-49, -169, -57, -184, -67, -185);
    ctx.closePath();
    ctx.fill();
    ctx.stroke();
    ctx.restore();
  }

  function gola(ctx) {
    ctx.save();
    ctx.fillStyle = PRETO;
    ctx.strokeStyle = OURO;
    ctx.lineWidth = 2;
    for (const lado of [-1, 1]) {
      ctx.save();
      ctx.scale(lado, 1);
      ctx.beginPath();
      ctx.moveTo(45, 35);
      ctx.quadraticCurveTo(74, 48, 79, 77);
      ctx.lineTo(45, 61);
      ctx.closePath();
      ctx.fill();
      ctx.stroke();
      ctx.restore();
    }
    ctx.restore();
  }

  function capa(ctx, t) {
    const onda = Math.sin(t * 1.4) * 3;
    ctx.save();
    ctx.fillStyle = VINHO;
    ctx.strokeStyle = '#210815';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(-47, -76);
    ctx.quadraticCurveTo(-86, -54, -80 + onda, 15);
    ctx.quadraticCurveTo(-47, 1, 0, 12);
    ctx.quadraticCurveTo(48, 0, 80 - onda, 15);
    ctx.quadraticCurveTo(86, -54, 47, -76);
    ctx.closePath();
    ctx.fill();
    ctx.stroke();
    ctx.restore();
  }

  function traje(ctx) {
    ctx.save();
    clipBodyWithoutScreen(ctx);
    const grad = ctx.createLinearGradient(-BODY.hw, -78, BODY.hw, 0);
    grad.addColorStop(0, '#080910');
    grad.addColorStop(0.5, '#242534');
    grad.addColorStop(1, '#080910');
    ctx.fillStyle = grad;
    ctx.fillRect(-BODY.hw - 3, -82, BODY.hw * 2 + 6, 86);
    ctx.restore();

    ctx.beginPath();
    bodyOutline(ctx);
    ctx.strokeStyle = OURO;
    ctx.lineWidth = 2.2;
    ctx.stroke();

    // Lapelas douradas, sem cobrir a tela do peito.
    ctx.fillStyle = OURO;
    ctx.beginPath();
    ctx.moveTo(-43, -71); ctx.lineTo(-27, -63); ctx.lineTo(-34, -28); ctx.lineTo(-45, -39); ctx.closePath();
    ctx.moveTo(43, -71); ctx.lineTo(27, -63); ctx.lineTo(34, -28); ctx.lineTo(45, -39); ctx.closePath();
    ctx.fill();

    // Jabô branco curto acima da tela.
    ctx.fillStyle = BRANCO;
    ctx.strokeStyle = '#9aa6b5';
    ctx.lineWidth = 1.2;
    for (let i = 0; i < 3; i++) {
      ctx.beginPath();
      ctx.moveTo(-18 + i * 5, -75 + i * 5);
      ctx.quadraticCurveTo(0, -65 + i * 4, 18 - i * 5, -75 + i * 5);
      ctx.quadraticCurveTo(0, -55 + i * 3, -18 + i * 5, -75 + i * 5);
      ctx.fill();
      ctx.stroke();
    }

    ctx.fillStyle = OURO_CLARO;
    ctx.beginPath();
    ctx.arc(0, -69, 4.5, 0, Math.PI * 2);
    ctx.fill();
  }

  Object.assign(Roupas.ITEMS, {
    cabeloAlucard: {
      label: 'Cabelo branco de Alucard', slot: 'cabeca', owner: 'echo', lab: true,
      anchor: 'robo', top: 36, springs: [], back: cabeloTras, draw: cabeloFrente
    },
    golaAlucard: {
      label: 'Gola alta de Alucard', slot: 'rosto', owner: 'echo', lab: true,
      top: 0, springs: [], draw: gola
    },
    trajeAlucard: {
      label: 'Traje e capa de Alucard', slot: 'pescoco', owner: 'echo', lab: true,
      top: 0, springs: [[55, 5, 0]], back: capa, draw: traje
    }
  });

  Roupas.SETS.alucard = {
    label: 'Fantasia de Alucard', owner: 'echo', lab: true,
    items: ['cabeloAlucard', 'golaAlucard', 'trajeAlucard']
  };
})(typeof window !== 'undefined' ? window : globalThis);

