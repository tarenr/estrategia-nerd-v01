"""Mede a caixa real de cada texto desenhado no canvas (fillText/strokeText) e confere a área segura dos Reels.

Regra (feedback do usuário, 13/10/2026): nenhum texto acima de Y=170 nem abaixo de Y=1540; X >= 72 e X <= 1008;
entre Y=970 e Y=1790 o texto não passa de X=925 (coluna de ícones). Texto com alfa < 0.15 é ignorado.
Letras em movimento podem passar por instantes: o relatório mostra o instante de cada ocorrência.

Uso: medir_texto_pagina.py --html src/X.html (--tempos 40 41.9 | --de 37 --ate 42 --passo 0.25) [--json saida.json]
O HTML precisa estar dentro de src/. Prazo total --prazo (padrão 140 s).
"""
import argparse
import asyncio
import json
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
SRC = (RAIZ / "src").resolve()

GANCHO = """
(() => {
  // Caixas de texto por canvas, em pixels do próprio canvas. As camadas fora da tela são compostas no mestre
  // por drawImage; as caixas são propagadas por essa composição (aproximação: ignora recortes/clip).
  const LISTAS = new WeakMap();
  const lista = (cv) => { let l = LISTAS.get(cv); if (!l) { l = []; LISTAS.set(cv, l); } return l; };
  const proto = CanvasRenderingContext2D.prototype;
  const reg = (ctx, txt, x, y) => {
    try {
      const m = ctx.measureText(String(txt));
      const asc = m.actualBoundingBoxAscent || 0, desc = m.actualBoundingBoxDescent || 0;
      const esq = m.actualBoundingBoxLeft || 0, dir = m.actualBoundingBoxRight || m.width || 0;
      const pts = [[x - esq, y - asc], [x + dir, y - asc], [x - esq, y + desc], [x + dir, y + desc]];
      const T = ctx.getTransform();
      const out = pts.map(([px, py]) => [T.a * px + T.c * py + T.e, T.b * px + T.d * py + T.f]);
      const xs = out.map(p => p[0]), ys = out.map(p => p[1]);
      lista(ctx.canvas).push({ txt: String(txt), alfa: ctx.globalAlpha,
        x0: Math.min(...xs), x1: Math.max(...xs), y0: Math.min(...ys), y1: Math.max(...ys) });
    } catch (e) {}
  };
  for (const nome of ["fillText", "strokeText"]) {
    const orig = proto[nome];
    proto[nome] = function (txt, x, y, ...r) { reg(this, txt, x, y); return orig.call(this, txt, x, y, ...r); };
  }
  const origClear = proto.clearRect;
  proto.clearRect = function (x, y, w, h) {
    try {
      const cv = this.canvas;
      if (x <= 0 && y <= 0 && x + w >= cv.width && y + h >= cv.height) lista(cv).length = 0;
    } catch (e) {}
    return origClear.call(this, x, y, w, h);
  };
  const origDraw = proto.drawImage;
  proto.drawImage = function (src, ...a) {
    try {
      const L = src && LISTAS.get(src);
      if (L && L.length) {
        let sx = 0, sy = 0, sw = src.width, sh = src.height, dx, dy, dw, dh;
        if (a.length === 2) { dx = a[0]; dy = a[1]; dw = sw; dh = sh; }
        else if (a.length === 4) { dx = a[0]; dy = a[1]; dw = a[2]; dh = a[3]; }
        else { [sx, sy, sw, sh, dx, dy, dw, dh] = a; }
        const T = this.getTransform(), fx = dw / sw, fy = dh / sh, al = this.globalAlpha;
        const alvo = lista(this.canvas);
        for (const c of L) {
          const pts = [[c.x0, c.y0], [c.x1, c.y0], [c.x0, c.y1], [c.x1, c.y1]].map(([px, py]) => {
            const qx = dx + (px - sx) * fx, qy = dy + (py - sy) * fy;
            return [T.a * qx + T.c * qy + T.e, T.b * qx + T.d * qy + T.f];
          });
          const xs = pts.map(p => p[0]), ys = pts.map(p => p[1]);
          alvo.push({ txt: c.txt, alfa: c.alfa * al, x0: Math.min(...xs), x1: Math.max(...xs),
                      y0: Math.min(...ys), y1: Math.max(...ys) });
        }
      }
    } catch (e) {}
    return origDraw.call(this, src, ...a);
  };
  window.__zerarTextos = () => { const m = document.getElementById('master-canvas'); if (m) lista(m).length = 0; };
  window.__lerTextos = () => {
    const m = document.getElementById('master-canvas'), r = m.getBoundingClientRect(), k = r.width / m.width;
    return lista(m).map(c => ({ txt: c.txt, alfa: c.alfa, x0: c.x0 * k + r.left, x1: c.x1 * k + r.left,
                                 y0: c.y0 * k + r.top, y1: c.y1 * k + r.top }));
  };
})();
"""


def fora(c):
    motivos = []
    if c["y0"] < 170:
        motivos.append("Y<170")
    if c["y1"] > 1540:
        motivos.append("Y>1540")
    if c["x0"] < 72:
        motivos.append("X<72")
    if c["x1"] > 1008:
        motivos.append("X>1008")
    if c["x1"] > 925 and c["y1"] >= 970 and c["y0"] <= 1790:
        motivos.append("X>925 em Y970-1790")
    return motivos


async def medir(html, ts, erros):
    from playwright.async_api import async_playwright
    res = {}
    async with async_playwright() as p:
        nav = await p.chromium.launch()
        pg = await nav.new_page(viewport={"width": 1080, "height": 1920}, device_scale_factor=1)
        pg.on("pageerror", lambda e: erros.append(f"pageerror: {e}"))
        pg.on("console", lambda m: erros.append(f"console.{m.type}: {m.text}") if m.type == "error" else None)
        await pg.add_init_script(GANCHO)
        await pg.goto(html.as_uri() + "?offset=0")
        await pg.evaluate("window.__ready")
        for t in ts:
            await pg.evaluate("window.__zerarTextos()")
            await pg.evaluate(f"window.__seek({t})")
            await pg.wait_for_timeout(35)
            res[t] = await pg.evaluate("window.__lerTextos()")
        await nav.close()
    return res


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--html", required=True)
    ap.add_argument("--tempos", type=float, nargs="*")
    ap.add_argument("--de", type=float)
    ap.add_argument("--ate", type=float)
    ap.add_argument("--passo", type=float, default=0.25)
    ap.add_argument("--json")
    ap.add_argument("--prazo", type=float, default=140.0)
    a = ap.parse_args()
    html = Path(a.html)
    if not html.is_absolute():
        html = (RAIZ / html)
    html = html.resolve()
    if SRC not in html.parents:
        sys.exit(f"ERRO: HTML fora de {SRC}")
    if a.tempos:
        ts = a.tempos
    else:
        n = int(round((a.ate - a.de) / a.passo))
        ts = [round(a.de + i * a.passo, 3) for i in range(n + 1)]
    erros = []
    res = asyncio.run(asyncio.wait_for(medir(html, ts, erros), a.prazo))
    total_fora = 0
    resumo = {}
    for t in ts:
        itens = [c for c in res[t] if c["alfa"] >= 0.15 and c["txt"].strip()]
        ruins = [(c, fora(c)) for c in itens]
        ruins = [(c, m) for c, m in ruins if m]
        total_fora += len(ruins)
        resumo[t] = {"textos": len(itens), "fora": [
            {"txt": c["txt"][:40], "x": [round(c["x0"]), round(c["x1"])], "y": [round(c["y0"]), round(c["y1"])], "motivo": m}
            for c, m in ruins]}
        print(f"t={t:6.2f}s  textos={len(itens):3d}  fora da área segura={len(ruins)}")
        for c, m in ruins:
            print(f"      '{c['txt'][:40]}' X[{c['x0']:.0f},{c['x1']:.0f}] Y[{c['y0']:.0f},{c['y1']:.0f}] -> {', '.join(m)}")
    print(f"TOTAL fora da área segura: {total_fora}; erros de página: {len(erros)}")
    for e in erros[:10]:
        print("  ", e)
    if a.json:
        Path(a.json).write_text(json.dumps(resumo, ensure_ascii=False, indent=1), encoding="utf-8")
    sys.exit(1 if erros else 0)


if __name__ == "__main__":
    main()
