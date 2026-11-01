"""gerar_estados.py - legenda HUD (Orbitron 700) do Castlevania, igual à do Manifesto.
Cópia de manifesto-estrategia-nerd/legenda/gerar_estados.py, com estas diferenças:
- todos os blocos em cima, com o topo em Y=270 (no Manifesto era Y=200, que só serve para o Reels;
  a área segura de Reels + Shorts começa em Y=250); fica acima do jogo e longe do rosto do Echo;
- pausas entre blocos (e antes do primeiro) viram um estado transparente; sem isso, o ffconcat encadeia os
  estados sem as pausas e as legendas seguintes adiantam;
- o último estado vai até 44,57 s (fim do vídeo).
Renderiza cada palavra falada (ativa em ciano) num PNG transparente 1080x1920 e grava estados.json e estados.txt (ffconcat).
"""
import asyncio
import base64
import json
import time
from pathlib import Path

from playwright.async_api import async_playwright

AQUI = Path(__file__).resolve().parent
LEGENDA_JSON = AQUI / "legenda_texto.json"
FONT_PATH = AQUI.parent.parent / "v1" / "fonts" / "orbitron-700-latin.woff2"   # mesma fonte do Manifesto (só leitura)
ESTADOS_DIR = AQUI / "estados"
ESTADOS_JSON = AQUI / "estados.json"
FFCONCAT_TXT = AQUI / "estados.txt"
FIM_VIDEO = 44.57

# Layout do Manifesto
TARGET_FONT_SIZE = 92
MIN_FONT_SIZE = 70
MAX_WIDTH = 835          # entre X 90 e 925 (área segura de Reels + Shorts; o Manifesto usava 72–925)
X_START = 90
TOP_Y = 270


def escape_html(text: str) -> str:
    return text.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;").replace('"', "&quot;")


def build_block_html(words: list, active_idx: int) -> str:
    parts = []
    for idx, w in enumerate(words):
        p_text = escape_html(w["p"])
        parts.append(f"<b>{p_text}</b>" if idx == active_idx else p_text)
    return " ".join(parts)


async def main():
    start_time = time.time()
    ESTADOS_DIR.mkdir(parents=True, exist_ok=True)
    blocos = json.loads(LEGENDA_JSON.read_text(encoding="utf-8"))["blocos"]
    font_b64 = base64.b64encode(FONT_PATH.read_bytes()).decode("ascii")

    html_template = f"""<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
@font-face {{
  font-family: 'Orb';
  font-weight: 700;
  src: url('data:font/woff2;base64,{font_b64}') format('woff2');
}}
html, body {{ margin: 0; padding: 0; width: 1080px; height: 1920px; background: transparent; overflow: hidden; }}
.leg {{
  position: absolute;
  left: {X_START}px;
  width: {MAX_WIDTH}px;
  top: {TOP_Y}px;
  text-align: center;
  font-family: 'Orb', sans-serif;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: #fff;
  line-height: 1.15;
  text-shadow: -3px -3px 0 #0a0e17, 3px -3px 0 #0a0e17, -3px 3px 0 #0a0e17, 3px 3px 0 #0a0e17, 0 5px 0 #0a0e17, 0 0 22px rgba(0,212,255,.35);
}}
.leg b {{
  color: #00d4ff;
  font-weight: inherit;
  text-shadow: -3px -3px 0 #0a0e17, 3px -3px 0 #0a0e17, -3px 3px 0 #0a0e17, 3px 3px 0 #0a0e17, 0 5px 0 #0a0e17, 0 0 26px rgba(0,212,255,.9);
}}
.hud {{
  display: inline-block;
  padding: 8px 26px 14px;
  border-left: 6px solid #00d4ff;
  border-right: 6px solid #00d4ff;
  background: linear-gradient(90deg, rgba(10,14,23,0), rgba(10,14,23,.55) 15%, rgba(10,14,23,.55) 85%, rgba(10,14,23,0));
  box-sizing: border-box;
  white-space: nowrap;
}}
</style>
</head>
<body>
<div class="leg" id="leg"><span class="hud" id="hud"></span></div>
</body>
</html>"""

    estados, block_metrics, reduced_blocks, multiline_blocks = [], [], [], []
    medir = """() => { const r = document.getElementById('hud').getBoundingClientRect();
                       return { width: r.width, height: r.height, left: r.left, right: r.right, top: r.top, bottom: r.bottom }; }"""

    async with async_playwright() as p:
        browser = await p.chromium.launch()
        try:
            page = await browser.new_page(viewport={"width": 1080, "height": 1920})
            await page.set_content(html_template)
            await page.evaluate("document.fonts.ready")

            # 1. tamanho de cada bloco (92 px; reduz até 70 px; se não couber, 2 linhas)
            for b_idx, bloco in enumerate(blocos):
                texto = " ".join(w["p"] for w in bloco["palavras"])
                await page.evaluate(f"""() => {{ const leg = document.getElementById('leg'), hud = document.getElementById('hud');
                    leg.style.fontSize = '{TARGET_FONT_SIZE}px'; hud.style.whiteSpace = 'nowrap'; hud.style.maxWidth = 'none';
                    hud.style.display = 'inline-block'; hud.textContent = {json.dumps(texto)}; }}""")
                metric = await page.evaluate(medir)
                size, lines = TARGET_FONT_SIZE, 1
                while metric["width"] > MAX_WIDTH and size > MIN_FONT_SIZE:
                    size -= 1
                    await page.evaluate(f"() => {{ document.getElementById('leg').style.fontSize = '{size}px'; }}")
                    metric = await page.evaluate(medir)
                if size < TARGET_FONT_SIZE:
                    reduced_blocks.append({"bloco": b_idx, "texto": texto, "tamanho": size})
                if metric["width"] > MAX_WIDTH:
                    lines = 2
                    multiline_blocks.append({"bloco": b_idx, "texto": texto})
                    await page.evaluate(f"""() => {{ const hud = document.getElementById('hud');
                        hud.style.whiteSpace = 'normal'; hud.style.maxWidth = '{MAX_WIDTH}px'; }}""")
                    metric = await page.evaluate(medir)
                block_metrics.append({"bloco_idx": b_idx, "texto": texto, "ini": bloco["ini"], "fim": bloco["fim"],
                                      "font_size": size, "linhas": lines,
                                      "x_min": round(metric["left"]), "x_max": round(metric["right"]),
                                      "y_min": round(metric["top"]), "y_max": round(metric["bottom"])})

            # estado transparente (pausas e fim)
            await page.evaluate("() => { const hud = document.getElementById('hud'); hud.innerHTML = ''; hud.style.display = 'none'; }")
            await page.screenshot(path=str(ESTADOS_DIR / "estado_vazio.png"), omit_background=True)
            await page.evaluate("() => { document.getElementById('hud').style.display = 'inline-block'; }")

            def vazio(ini, fim):
                estados.append({"estado_idx": len(estados), "bloco_idx": -1, "palavra_ativa": "", "texto_bloco": "[TRANSPARENTE]",
                                "ini": round(ini, 3), "fim": round(fim, 3), "duracao": round(fim - ini, 4),
                                "arquivo": "estados/estado_vazio.png"})

            # 2. um estado por palavra falada; pausas preenchidas com o estado transparente
            cursor = 0.0
            for b_idx, bloco in enumerate(blocos):
                words, bm = bloco["palavras"], block_metrics[b_idx]
                if words[0]["ini"] - cursor > 0.0005:
                    vazio(cursor, words[0]["ini"])
                for w_idx, word in enumerate(words):
                    w_ini = word["ini"]
                    w_fim = words[w_idx + 1]["ini"] if w_idx + 1 < len(words) else bloco["fim"]
                    nome = f"estado_{len(estados):03d}.png"
                    white_space = "normal" if bm["linhas"] > 1 else "nowrap"
                    max_w = f"{MAX_WIDTH}px" if bm["linhas"] > 1 else "none"
                    await page.evaluate(f"""() => {{ const leg = document.getElementById('leg'), hud = document.getElementById('hud');
                        leg.style.fontSize = '{bm["font_size"]}px'; hud.style.whiteSpace = '{white_space}';
                        hud.style.maxWidth = '{max_w}'; hud.innerHTML = {json.dumps(build_block_html(words, w_idx))}; }}""")
                    await page.screenshot(path=str(ESTADOS_DIR / nome), omit_background=True)
                    estados.append({"estado_idx": len(estados), "bloco_idx": b_idx, "palavra_ativa": word["p"],
                                    "texto_bloco": bm["texto"], "ini": round(w_ini, 3), "fim": round(w_fim, 3),
                                    "duracao": round(w_fim - w_ini, 4), "font_size": bm["font_size"],
                                    "x_min": bm["x_min"], "x_max": bm["x_max"], "y_min": bm["y_min"], "y_max": bm["y_max"],
                                    "arquivo": f"estados/{nome}"})
                    cursor = w_fim
            if FIM_VIDEO - cursor > 0.0005:
                vazio(cursor, FIM_VIDEO)
        finally:
            await browser.close()

    ESTADOS_JSON.write_text(json.dumps({"total_estados": len(estados), "total_blocos": len(blocos), "topo_y": TOP_Y,
                                        "blocos_reduzidos": reduced_blocks, "blocos_duas_linhas": multiline_blocks,
                                        "block_metrics": block_metrics, "estados": estados},
                                       ensure_ascii=False, indent=2), encoding="utf-8")
    linhas = ["ffconcat version 1.0"]
    for e in estados:
        linhas += [f"file '{e['arquivo']}'", f"duration {e['duracao']:.6f}"]
    linhas.append(f"file '{estados[-1]['arquivo']}'")   # o ffconcat pede o último arquivo repetido sem duration
    FFCONCAT_TXT.write_text("\n".join(linhas) + "\n", encoding="utf-8")
    soma = sum(e["duracao"] for e in estados)
    ys = [b["y_min"] for b in block_metrics] + [b["y_max"] for b in block_metrics]
    xs = [b["x_min"] for b in block_metrics] + [b["x_max"] for b in block_metrics]
    print(f"{len(estados)} estados, soma das durações {soma:.3f} s (vídeo {FIM_VIDEO} s)")
    print(f"caixa da legenda: Y {min(ys)}–{max(ys)}, X {min(xs)}–{max(xs)}; reduzidos: {len(reduced_blocks)}; 2 linhas: {len(multiline_blocks)}")
    print(f"pronto em {time.time() - start_time:.1f} s")


if __name__ == "__main__":
    asyncio.run(asyncio.wait_for(main(), 140.0))
