"""Conferência de um bloco da v12 (refeita em 19/10/2026; as da sessão anterior se perderam).

Subcomandos (todos com prazo total --prazo, padrão 140 s, e HTML só dentro de src/):

  congelar  --html src/X.html --rotulo R [--passo 0.5] [--ate T]
      Grava SHA-256 do canvas da página em cada instante (0..ate) em renders/v12_baseline/hashes_R.json.
  comparar  --html src/X.html --rotulo R --janela A B
      Compara a página com o congelado R: instantes FORA de [A,B] precisam ter o mesmo hash;
      dentro da janela só informa quais mudaram. Sai com código 1 se algo fora da janela mudou.
  contra-v11 --html src/X.html [--ate 37.04] [--passo 0.5]
      Compara a página com a v11 (referência) em cada instante até --ate (inclusive 18–37 s).
  funcoes   --antes A.html --depois B.html
      Diff por função: quais funções (e se há linhas fora de função) mudaram entre dois HTML.

Pegadinha: sem ?offset= a composição começa em 10 s; aqui sempre vai ?offset=0.
"""
import argparse
import asyncio
import difflib
import hashlib
import json
import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
SRC = (RAIZ / "src").resolve()
V11 = SRC / "manifesto-37s-v11.html"
BASE = RAIZ / "renders" / "v12_baseline"


def exige_src(p: Path) -> Path:
    p = Path(p)
    if not p.is_absolute():
        p = (RAIZ / p) if (RAIZ / p).exists() else (Path.cwd() / p)
    p = p.resolve()
    if SRC not in p.parents:
        sys.exit(f"ERRO: o HTML precisa estar dentro de {SRC} (recursos relativos): {p}")
    return p


def instantes(passo: float, ate: float):
    n = int(round(ate / passo))
    ts = [round(i * passo, 3) for i in range(n + 1)]
    if ate not in ts:
        ts.append(round(ate, 3))
    return ts


async def capturar(html: Path, ts, erros):
    from playwright.async_api import async_playwright
    out = {}
    async with async_playwright() as p:
        nav = await p.chromium.launch()
        pg = await nav.new_page(viewport={"width": 1080, "height": 1920}, device_scale_factor=1)
        pg.on("pageerror", lambda e: erros.append(f"pageerror: {e}"))
        pg.on("console", lambda m: erros.append(f"console.{m.type}: {m.text}") if m.type == "error" else None)
        await pg.goto(html.as_uri() + "?offset=0")
        await pg.evaluate("window.__ready")
        dur = await pg.evaluate("(window.__meta && window.__meta.dur) || null")
        alvo = await pg.query_selector("#master-canvas")
        for t in ts:
            if dur and t > dur + 1e-6:
                continue
            await pg.evaluate(f"window.__seek({t})")
            await pg.wait_for_timeout(35)
            png = await alvo.screenshot()
            out[f"{t:.3f}"] = hashlib.sha256(png).hexdigest()
        await nav.close()
    return out, dur


def cmd_congelar(a):
    html = exige_src(a.html)
    erros = []
    ate = a.ate if a.ate else 60.0
    h, dur = asyncio.run(asyncio.wait_for(capturar(html, instantes(a.passo, ate), erros), a.prazo))
    BASE.mkdir(parents=True, exist_ok=True)
    dest = BASE / f"hashes_{a.rotulo}.json"
    dest.write_text(json.dumps({"html": html.name, "html_sha256": hashlib.sha256(html.read_bytes()).hexdigest(),
                                "dur": dur, "hashes": h}, indent=1), encoding="utf-8")
    print(f"congelado {len(h)} instantes (dur={dur}) em {dest}; erros de página: {len(erros)}")
    for e in erros[:10]:
        print("  ", e)
    return 1 if erros else 0


def cmd_comparar(a):
    html = exige_src(a.html)
    ref = json.loads((BASE / f"hashes_{a.rotulo}.json").read_text(encoding="utf-8"))["hashes"]
    ts = sorted(float(k) for k in ref)
    erros = []
    cur, dur = asyncio.run(asyncio.wait_for(capturar(html, ts, erros), a.prazo))
    A, B = a.janela
    fora_mudou, dentro_mudou, iguais = [], [], 0
    for k, h in ref.items():
        if k not in cur:
            continue
        t = float(k)
        if cur[k] != h:
            (dentro_mudou if A <= t <= B else fora_mudou).append(k)
        elif not (A <= t <= B):
            iguais += 1
    print(f"janela [{A},{B}] dur={dur}; fora da janela iguais={iguais}, MUDARAM={len(fora_mudou)} {fora_mudou[:20]}")
    print(f"dentro da janela mudaram: {len(dentro_mudou)} (esperado)")
    print(f"erros de página: {len(erros)}")
    for e in erros[:10]:
        print("  ", e)
    return 1 if (fora_mudou or erros) else 0


def cmd_contra_v11(a):
    html = exige_src(a.html)
    ts = instantes(a.passo, a.ate)
    erros = []

    async def ambos():
        r11, _ = await capturar(V11, ts, erros)
        r12, _ = await capturar(html, ts, erros)
        return r11, r12
    r11, r12 = asyncio.run(asyncio.wait_for(ambos(), a.prazo))
    dif = [k for k in r11 if k in r12 and r11[k] != r12[k]]
    print(f"v11 x {html.name} até {a.ate}s: {len(r11)} instantes, DIFERENTES={len(dif)} {dif[:20]}; erros de página: {len(erros)}")
    for e in erros[:10]:
        print("  ", e)
    return 1 if (dif or erros) else 0


# ---------- diff por função ----------
def intervalos_funcoes(linhas):
    """Devolve [(nome, ini, fim)] (linhas 1-based) por contagem de chaves, ignorando strings e comentários."""
    texto = "".join(linhas)
    achados = [(m.start(), m.group(1)) for m in re.finditer(r"\bfunction\s+([A-Za-z_$][\w$]*)\s*\(", texto)]
    ini_linha = [0]
    for l in linhas:
        ini_linha.append(ini_linha[-1] + len(l))

    def linha_de(pos):
        lo, hi = 0, len(ini_linha) - 1
        while lo < hi:
            mid = (lo + hi) // 2
            if ini_linha[mid + 1] <= pos:
                lo = mid + 1
            else:
                hi = mid
        return lo + 1
    res = []
    for pos, nome in achados:
        i = texto.find("{", texto.find(")", pos))
        if i < 0:
            continue
        prof, j, n = 0, i, len(texto)
        estado = None
        while j < n:
            c = texto[j]
            d = texto[j:j + 2]
            if estado is None:
                if d == "//":
                    k = texto.find("\n", j)
                    j = n if k < 0 else k
                    continue
                if d == "/*":
                    k = texto.find("*/", j + 2)
                    j = n if k < 0 else k + 2
                    continue
                if c in "\"'`":
                    estado = c
                elif c == "{":
                    prof += 1
                elif c == "}":
                    prof -= 1
                    if prof == 0:
                        break
            else:
                if c == "\\":
                    j += 1
                elif c == estado:
                    estado = None
            j += 1
        res.append((nome, linha_de(pos), linha_de(j)))
    return res


def cmd_funcoes(a):
    antes = Path(a.antes).read_text(encoding="utf-8").splitlines(True)
    depois = Path(a.depois).read_text(encoding="utf-8").splitlines(True)
    fx = intervalos_funcoes(depois)
    sm = difflib.SequenceMatcher(None, antes, depois, autojunk=False)
    tocadas, fora = {}, []
    for tag, i1, i2, j1, j2 in sm.get_opcodes():
        if tag == "equal":
            continue
        for lin in range(j1 + 1, max(j2, j1 + 1) + 1):
            cont = [f for f in fx if f[1] <= lin <= f[2]]
            if cont:
                inner = min(cont, key=lambda f: f[2] - f[1])
                tocadas.setdefault(inner[0], []).append(lin)
            else:
                fora.append(lin)
    print(f"linhas antes={len(antes)} depois={len(depois)}; funções tocadas={len(tocadas)}; linhas fora de função={len(fora)}")
    for nome, ls in sorted(tocadas.items()):
        print(f"  {nome}: {len(ls)} linhas ({min(ls)}–{max(ls)})")
    if fora:
        print(f"  <fora de função>: linhas {sorted(set(fora))[:30]}")
    return 0


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sp = ap.add_subparsers(dest="cmd", required=True)
    for nome in ("congelar", "comparar", "contra-v11"):
        s = sp.add_parser(nome)
        s.add_argument("--html", required=True)
        s.add_argument("--prazo", type=float, default=140.0)
        s.add_argument("--passo", type=float, default=0.5)
        if nome != "contra-v11":
            s.add_argument("--rotulo", required=True)
        if nome == "congelar":
            s.add_argument("--ate", type=float, default=0)
        if nome == "comparar":
            s.add_argument("--janela", type=float, nargs=2, required=True)
        if nome == "contra-v11":
            s.add_argument("--ate", type=float, default=37.04)
    s = sp.add_parser("funcoes")
    s.add_argument("--antes", required=True)
    s.add_argument("--depois", required=True)
    a = ap.parse_args()
    fn = {"congelar": cmd_congelar, "comparar": cmd_comparar, "contra-v11": cmd_contra_v11, "funcoes": cmd_funcoes}[a.cmd]
    sys.exit(fn(a))


if __name__ == "__main__":
    main()
