/**
 * echo-atuacao-sala.js - Atuação do Echo no quarto, copiada do Manifesto
 * (manifesto-60s-v12.html: Echo sentado e poseSala, olhoAberto, aperto, o pouso do controle e as mãos do Ato 04,
 * e o Echo saindo da cadeira em metodoQuartoDinamico). Parado, o corpo é rígido; o movimento vem de mudar de lugar.
 * No fim ele sai da cadeira vindo para a frente (cresce e desce: profundidade) e acende a Propulsão Matrix.
 * Para reaproveitar em outro vídeo, copie este arquivo e passe as marcas da nova locução.
 *
 * marcas: { foco, pousa, cta: [ini, fim], piscadas, piscadasFelizes, apertos,
 *           frente: { ini, meio, fim, meioPara: { x, y, k }, para: { x, y, k } }, ignicao: [ini, fim] }
 *   foco    = fim da fala de abertura: a mão volta ao controle e o Echo olha o jogo
 *   pousa   = o controle vai para a mesa em arco (0,73 s), a mão solta e o Echo vira para a câmera
 *   cta     = trecho do "conta aí nos comentários" (olhos felizes)
 *   frente  = sai da cadeira vindo para a frente: até "meio" sai do plano da cadeira, até "fim" chega em "para"
 *   ignicao = a propulsão acende em ignicao[0] e chega à força máxima em ignicao[1]
 *   x, y são a base do Echo (como em pelaBase); k é a escala
 * geo (posições absolutas do quarto do Manifesto): { x, y, k, colo: { dy, rot }, mesa: { x, y, rot },
 *   aponta: [x, y] (mão apontando o monitor no gancho), solta: [x, y] (mão depois de pousar o controle) }
 */
(function (window) {
  'use strict';

  const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
  const lerp = (a, b, t) => a + (b - a) * t;
  const inv = (a, b, v) => clamp((v - a) / (b - a), 0, 1);
  const eIO = (t) => { t = clamp(t, 0, 1); return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; };
  const eOut = (t) => 1 - Math.pow(1 - clamp(t, 0, 1), 3);
  const mix = (p, q, t) => [lerp(p[0], q[0], t), lerp(p[1], q[1], t)];
  const misturar = (de, para, u) => de.map((p, i) => [lerp(p[0], para[i][0], u), lerp(p[1], para[i][1], u), lerp(p[2], para[i][2], u)]);

  // olhares do Manifesto: no jogo (poseSala), para o espectador e no controle pousando (Ato 04)
  const NO_MONITOR = [0.7, -0.3];
  const NA_FRENTE = [0.0, -0.04];
  const NA_MESA = [0.42, 0.08];
  // mãos flutuando (metodoQuartoDinamico, escala 1.18): a direita aponta, a esquerda acompanha com balanço
  const FLUTUA = { direita: [72 / 1.18, -52 / 1.18], esquerda: [-44 / 1.18, -30 / 1.18] };

  const aperto = (T, tempos) => { for (const tp of tempos) if (T >= tp && T < tp + 0.07) return 0.86; return 1; };
  const olhoAberto = (T, tempos) => {
    for (const p of tempos) if (T >= p && T <= p + 0.13) return 1 - 0.95 * Math.sin(Math.PI * (T - p) / 0.13);
    return 1;
  };

  function criar(marcas, geo) {
    const m = Object.assign({ piscadas: [], piscadasFelizes: [], apertos: [] }, marcas);
    const sentado = { x: geo.x, y: geo.y, k: geo.k };

    const fases = (T) => ({
      virar: eOut(inv(m.foco - 0.25, m.foco, T)),
      desk: eIO(inv(m.pousa, m.pousa + 0.73, T)),
      solta: eIO(inv(m.pousa + 0.73, m.pousa + 1.38, T)),
      frente: eIO(inv(m.pousa + 0.28, m.pousa + 1.28, T)),
      sai: m.frente ? eIO(inv(m.frente.ini, m.frente.meio, T)) : 0,
      vem: m.frente ? eIO(inv(m.frente.meio, m.frente.fim, T)) : 0
    });

    // corpo: base, escala e se já está na frente da cadeira (desenhado à frente das camadas do quarto)
    function corpo(T) {
      if (!m.frente || T < m.frente.ini) return { x: sentado.x, y: sentado.y, k: sentado.k, frente: false };
      const f = fases(T), a = m.frente.meioPara, b = m.frente.para;
      const p1 = { x: lerp(sentado.x, a.x, f.sai), y: lerp(sentado.y, a.y, f.sai), k: lerp(sentado.k, a.k, f.sai) };
      const bob = 5 * Math.sin(T * 3.2) * f.sai;
      return { x: lerp(p1.x, b.x, f.vem), y: lerp(p1.y, b.y, f.vem) + bob, k: lerp(p1.k, b.k, f.vem), frente: true };
    }

    // propulsão: 0 até a ignição, cresce até a força máxima
    const propulsao = (T) => (m.ignicao ? eOut(inv(m.ignicao[0], m.ignicao[1], T)) : 0);

    function pose(T) {
      const f = fases(T);
      let olhar, yaw, lean, inclina, fala;
      if (T < m.pousa) {
        olhar = mix(NA_FRENTE, NO_MONITOR, f.virar);
        yaw = lerp(0.02, 0, f.virar);
        inclina = 0.03;
        fala = 1 - f.virar;
        lean = 0.008 * Math.sin(T * 2.1) * fala;
      } else {
        olhar = mix(NO_MONITOR, NA_FRENTE, f.frente);
        if (f.desk > 0.05 && f.desk < 0.95) olhar = mix(olhar, NA_MESA, Math.sin(Math.PI * f.desk));
        yaw = lerp(0, 0.02, f.frente);
        fala = f.frente;
        lean = 0.055 * Math.sin(Math.PI * f.desk) + 0.008 * Math.sin(T * 2.1) * fala;
        inclina = lerp(0.03, 0.01, f.frente) + 0.03 * Math.sin(Math.PI * inv(m.cta[0], m.cta[1], T));
      }
      let olhos = 'pill';
      if (T >= m.cta[0]) olhos = m.piscadasFelizes.some((p) => T >= p && T <= p + 0.13) ? 'pill' : 'happy';
      return {
        olhar, olhos, yaw, lean, inclina, sx: 1,
        sy: 1 + 0.012 * Math.sin(T * 3.2) * fala,
        piscada: olhoAberto(T, m.piscadas),
        aperto: aperto(T, m.apertos)
      };
    }

    // controle: no colo; no fechamento pousa na mesa em arco e fica lá (Ato 04)
    function controle(T) {
      const d = fases(T).desk;
      const colo = { x: geo.x, y: geo.y + geo.colo.dy, rot: geo.colo.rot };
      return {
        x: lerp(colo.x, geo.mesa.x, d),
        y: lerp(colo.y, geo.mesa.y, d) - 18 * Math.sin(Math.PI * d),
        rot: lerp(colo.rot, geo.mesa.rot, d)
      };
    }

    // mãos: [esquerda, direita] como [x, y, escala]
    function maos(T, c) {
      const f = fases(T);
      const ap = aperto(T, m.apertos);
      const cos = Math.cos(c.rot), sin = Math.sin(c.rot);
      const gripEsq = [c.x - 38 * cos - 6, c.y - 38 * sin + 4, ap];
      const gripDir = [c.x + 38 * cos + 6, c.y + 38 * sin + 4, T >= m.pousa ? 1 : ap];
      const bob = 5 * Math.sin(T * 2.8);

      if (T < m.pousa) {
        // gancho: mão esquerda apontando o monitor; volta ao controle para jogar
        const volta = eIO(inv(m.foco - 0.55, m.foco + 0.10, T));
        const esq = [lerp(geo.aponta[0], gripEsq[0], volta), lerp(geo.aponta[1] + bob * (1 - volta), gripEsq[1], volta), lerp(1, ap, volta)];
        return [esq, gripDir];
      }
      // controle pousado: a esquerda solta e descansa; a direita fica no controle
      const esqSolta = [lerp(gripEsq[0], geo.solta[0], f.solta), lerp(gripEsq[1], geo.solta[1] + bob * f.solta, f.solta), 1];
      const sentadas = [esqSolta, gripDir];
      if (!m.frente || T < m.frente.ini) return sentadas;
      // saindo para a frente: as duas mãos vão para junto do corpo, como no Manifesto flutuando
      const b = corpo(T), bobF = 5 * Math.sin(T * 3.2);
      const flutuando = [
        [b.x + FLUTUA.esquerda[0] * b.k, b.y + FLUTUA.esquerda[1] * b.k + bobF, 1],
        [b.x + FLUTUA.direita[0] * b.k, b.y + FLUTUA.direita[1] * b.k, 1]
      ];
      return misturar(sentadas, flutuando, f.sai);
    }

    return { pose, corpo, controle, maos, propulsao };
  }

  window.EchoAtuacaoSala = { criar };
})(window);
