const test = require('node:test');
const assert = require('node:assert/strict');

const {
  SEGMENTOS_GAMEPLAY,
  FONTES_APROVADAS,
  MUSICA_ORIGEM_INICIO,
  obterSegmento,
  calcularEnquadramentoMonitor,
  calcularAtuacaoEcho,
} = require('../timeline-v2.cjs');

test('abre com a tela do jogo e só depois entra no gameplay', () => {
  const titulo = obterSegmento(0);
  assert.equal(titulo.id, 'titulo');
  assert.ok(titulo.origemInicio >= 3, 'não inclui a dissolução da tela anterior');
  assert.equal(obterSegmento(3.9).id, 'titulo');
  assert.notEqual(obterSegmento(5.2).id, 'titulo');
});

test('o plano do castelo começa depois da tela preta do original', () => {
  const castelo = SEGMENTOS_GAMEPLAY.find(({ id }) => id === 'castelo');
  assert.ok(castelo.origemInicio >= 101);
});

test('usa somente o gameplay bruto e o mapa estático aprovados', () => {
  assert.deepEqual(FONTES_APROVADAS, {
    gameplay: 'GamePlay.mp4',
    mapa: 'sotnmap1-SF.png',
    musica: 'Bloody Tears.mp3',
  });
  assert.equal(MUSICA_ORIGEM_INICIO, 22);
});

test('cada assunto da locução usa um corte editorial explicitamente mapeado', () => {
  assert.deepEqual(
    SEGMENTOS_GAMEPLAY.map(({ id }) => id),
    ['titulo', 'classico-2d', 'richter-controlado', 'batalha-richter', 'shaft', 'castelo', 'inversao', 'exploracao', 'mapa', 'retorno'],
  );

  for (let i = 0; i < SEGMENTOS_GAMEPLAY.length; i += 1) {
    const atual = SEGMENTOS_GAMEPLAY[i];
    assert.ok(atual.fim > atual.inicio, `${atual.id} tem duração positiva`);
    if (atual.tipo === 'video') {
      assert.ok(atual.origemFim > atual.origemInicio, `${atual.id} tem trecho de origem válido`);
    } else {
      assert.equal(atual.tipo, 'imagem');
      assert.equal(atual.fonte, 'sotnmap1-SF.png');
    }
    if (i > 0) assert.equal(atual.inicio, SEGMENTOS_GAMEPLAY[i - 1].fim);
  }
});

test('o gameplay 4:3 permanece inteiro e as laterais recebem um fundo de preenchimento', () => {
  const monitor = { x: 410, y: 520, w: 500, h: 281 };
  const origem4x3 = { w: 640, h: 480 };
  const enquadramento = calcularEnquadramentoMonitor(origem4x3, monitor);

  assert.deepEqual(enquadramento.principal.origem, { sx: 0, sy: 0, sw: 640, sh: 480 });
  assert.equal(enquadramento.principal.dh, monitor.h);
  assert.ok(enquadramento.principal.dw < monitor.w);
  assert.equal(enquadramento.fundo.dw, monitor.w);
  assert.equal(enquadramento.fundo.dh, monitor.h);
});

test('Echo executa ações legíveis, não apenas oscilação decorativa', () => {
  assert.equal(calcularAtuacaoEcho(1).acao, 'apresenta');
  assert.equal(calcularAtuacaoEcho(5.5).acao, 'olha-monitor');
  assert.equal(calcularAtuacaoEcho(40).acao, 'pergunta-publico');
  assert.ok(Math.abs(calcularAtuacaoEcho(1).deslocamentoX - calcularAtuacaoEcho(5.5).deslocamentoX) >= 12);
  assert.ok(Math.abs(calcularAtuacaoEcho(5.5).yaw) >= 0.12);
});
