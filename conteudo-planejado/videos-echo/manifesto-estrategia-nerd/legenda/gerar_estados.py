"""gerar_estados.py
Gera todos os estados da legenda HUD com Orbitron 700 para o vídeo Manifesto Estratégia Nerd:
- Renderiza em HTML 1080x1920 transparente via Playwright (Chromium).
- Para cada palavra falada de cada bloco, gera um PNG transparente em estados/.
- Posições dinâmicas contra colisões de texto do vídeo:
  * Blocos 0 a 6 (0.00s a 6.10s): EM CIMA (Y=200). Texto do vídeo fica na metade inferior.
  * Blocos 7 a 12 (6.10s a 11.50s): EM CIMA REBAIXADO (Y=360). Preserva o cartão de notícias (Y=200..294).
  * Blocos 16 a 24 (13.32s a 19.70s): EMBAIXO (base em Y=1500, centro em X=498). Preserva o cartão do portal
    (BANCADA DE TESTES / GUIA DE DECISÃO REAL / 16 GB ou 32 GB de RAM?) no alto da tela.
  * Blocos 13 a 15 e 25 a 74: EM CIMA (Y=200). Topo da tela limpo.
- Centralizado entre X=72 e X=925 (largura máxima 853 px, centro em 498.5).
- Tamanho alvo 92 px (reduz cirurgicamente até 70 px se necessário; quebra em 2 linhas se ainda exceder).
- Grava estados.json e estados.txt (ffconcat).
- Garante fechamento do navegador em bloco finally e timeout global.
"""

import asyncio
import base64
import json
import sys
import time
from pathlib import Path
from playwright.async_api import async_playwright

SCRIPT_DIR = Path(__file__).resolve().parent
ROOT_DIR = SCRIPT_DIR.parent.parent.parent
STORYBOARD_JSON = ROOT_DIR / "storyboard" / "legenda_texto.json"
FONT_PATH = Path(r"C:\Users\WINDOWS\Projects\estrategia-nerd\scratch\reels-motionmaxxing\film-201-v9\fonts\orbitron-700-latin.woff2")
ESTADOS_DIR = SCRIPT_DIR / "estados"
ESTADOS_JSON = SCRIPT_DIR / "estados.json"
FFCONCAT_TXT = SCRIPT_DIR / "estados.txt"

# Constantes de layout
TARGET_FONT_SIZE = 92
MIN_FONT_SIZE = 70
MAX_WIDTH = 853
X_START = 72
X_END = 925
CENTER_X = 498.5

# Mapeamento de posições verticais por bloco
LOWERED_BLOCKS_Y360 = {7, 8, 9, 10, 11, 12}
BOTTOM_BLOCKS_Y1500 = {16, 17, 18, 19, 20, 21, 22, 23, 24}

DEFAULT_TOP_Y = 200
LOWERED_TOP_Y = 360
BOTTOM_BASE_Y = 1500
BOTTOM_OFFSET = 1920 - BOTTOM_BASE_Y  # 420 px a partir do fundo


def escape_html(text: str) -> str:
    return text.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;").replace('"', "&quot;")


def build_block_html(words: list, active_idx: int) -> str:
    parts = []
    for idx, w in enumerate(words):
        p_text = escape_html(w["p"])
        if idx == active_idx:
            parts.append(f"<b>{p_text}</b>")
        else:
            parts.append(p_text)
    return " ".join(parts)


def get_position_config(b_idx: int) -> dict:
    if b_idx in BOTTOM_BLOCKS_Y1500:
        return {
            "tipo": "EMBAIXO",
            "css_top": "auto",
            "css_bottom": f"{BOTTOM_OFFSET}px",
            "descricao": "Base em Y=1500 (livra cartão do portal no topo)"
        }
    elif b_idx in LOWERED_BLOCKS_Y360:
        return {
            "tipo": "EM_CIMA_REBAIXADO",
            "css_top": f"{LOWERED_TOP_Y}px",
            "css_bottom": "auto",
            "descricao": "Topo em Y=360 (livra cartão de notícias no topo)"
        }
    else:
        return {
            "tipo": "EM_CIMA",
            "css_top": f"{DEFAULT_TOP_Y}px",
            "css_bottom": "auto",
            "descricao": "Topo padrão em Y=200"
        }


async def main():
    start_time = time.time()
    ESTADOS_DIR.mkdir(parents=True, exist_ok=True)

    print("Carregando legenda_texto.json e fonte Orbitron...")
    data = json.loads(STORYBOARD_JSON.read_text(encoding="utf-8"))
    blocos = data["blocos"]

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
html, body {{
  margin: 0;
  padding: 0;
  width: 1080px;
  height: 1920px;
  background: transparent;
  overflow: hidden;
}}
.leg {{
  position: absolute;
  left: {X_START}px;
  width: {MAX_WIDTH}px;
  top: {DEFAULT_TOP_Y}px;
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
<div class="leg" id="leg">
  <span class="hud" id="hud"></span>
</div>
</body>
</html>"""

    estados = []
    block_metrics = []
    reduced_blocks = []
    multiline_blocks = []

    print("Iniciando Playwright Chromium...")
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        try:
            page = await browser.new_page(viewport={"width": 1080, "height": 1920})
            await page.set_content(html_template)
            await page.evaluate("document.fonts.ready")

            # 1. Pré-dimensionamento de todos os blocos
            for b_idx, bloco in enumerate(blocos):
                words = bloco["palavras"]
                full_raw_text = " ".join(w["p"] for w in words)
                pos_cfg = get_position_config(b_idx)

                # Testar tamanho 92px
                metric = await page.evaluate(f"""() => {{
                    const leg = document.getElementById('leg');
                    const hud = document.getElementById('hud');
                    leg.style.top = '{pos_cfg["css_top"]}';
                    leg.style.bottom = '{pos_cfg["css_bottom"]}';
                    leg.style.fontSize = '{TARGET_FONT_SIZE}px';
                    hud.style.whiteSpace = 'nowrap';
                    hud.style.maxWidth = 'none';
                    hud.textContent = {json.dumps(full_raw_text)};
                    const r = hud.getBoundingClientRect();
                    return {{ width: r.width, height: r.height, left: r.left, right: r.right, top: r.top, bottom: r.bottom }};
                }}""")

                chosen_size = TARGET_FONT_SIZE
                lines = 1

                # Redução progressiva se largura > MAX_WIDTH
                if metric["width"] > MAX_WIDTH:
                    cur_sz = TARGET_FONT_SIZE
                    while cur_sz > MIN_FONT_SIZE and metric["width"] > MAX_WIDTH:
                        cur_sz -= 1
                        metric = await page.evaluate(f"""() => {{
                            const leg = document.getElementById('leg');
                            const hud = document.getElementById('hud');
                            leg.style.fontSize = '{cur_sz}px';
                            const r = hud.getBoundingClientRect();
                            return {{ width: r.width, height: r.height, left: r.left, right: r.right, top: r.top, bottom: r.bottom }};
                        }}""")
                    chosen_size = cur_sz

                if chosen_size < TARGET_FONT_SIZE:
                    reduced_blocks.append((b_idx, full_raw_text, chosen_size, round(metric["width"], 1)))

                # Se ainda não couber em 70px, quebra em 2 linhas
                if metric["width"] > MAX_WIDTH and chosen_size == MIN_FONT_SIZE:
                    lines = 2
                    multiline_blocks.append((b_idx, full_raw_text))
                    metric = await page.evaluate(f"""() => {{
                        const hud = document.getElementById('hud');
                        hud.style.whiteSpace = 'normal';
                        hud.style.maxWidth = '{MAX_WIDTH}px';
                        const r = hud.getBoundingClientRect();
                        return {{ width: r.width, height: r.height, left: r.left, right: r.right, top: r.top, bottom: r.bottom }};
                    }}""")

                # Coordenadas finais da caixa no quadro 1080x1920
                x_min = round(metric["left"])
                x_max = round(metric["right"])
                y_min = round(metric["top"])
                y_max = round(metric["bottom"])

                block_metrics.append({
                    "bloco_idx": b_idx,
                    "texto": full_raw_text,
                    "ini": bloco["ini"],
                    "fim": bloco["fim"],
                    "font_size": chosen_size,
                    "linhas": lines,
                    "posicao_tipo": pos_cfg["tipo"],
                    "posicao_desc": pos_cfg["descricao"],
                    "width": round(metric["width"], 1),
                    "height": round(metric["height"], 1),
                    "x_min": x_min,
                    "x_max": x_max,
                    "y_min": y_min,
                    "y_max": y_max,
                })

            print(f"Pré-dimensionamento concluído: {len(reduced_blocks)} blocos reduzidos, {len(multiline_blocks)} com 2 linhas.")
            print(f"Blocos rebaixados Y=360: {len(LOWERED_BLOCKS_Y360)} | Blocos embaixo base Y=1500: {len(BOTTOM_BLOCKS_Y1500)}")

            # 2. Renderização de cada estado (palavra falada ativa)
            state_counter = 0
            for b_idx, bloco in enumerate(blocos):
                words = bloco["palavras"]
                num_words = len(words)
                bm = block_metrics[b_idx]
                pos_cfg = get_position_config(b_idx)
                chosen_size = bm["font_size"]
                lines = bm["linhas"]

                for w_idx, word in enumerate(words):
                    w_ini = word["ini"]
                    if w_idx + 1 < num_words:
                        w_fim = words[w_idx + 1]["ini"]
                    else:
                        w_fim = bloco["fim"]
                    dur = round(w_fim - w_ini, 4)

                    html_content = build_block_html(words, w_idx)
                    state_filename = f"estado_{state_counter:03d}.png"
                    state_path = ESTADOS_DIR / state_filename

                    # Atualizar DOM
                    white_space = "normal" if lines > 1 else "nowrap"
                    max_w = f"{MAX_WIDTH}px" if lines > 1 else "none"
                    await page.evaluate(f"""() => {{
                        const leg = document.getElementById('leg');
                        const hud = document.getElementById('hud');
                        leg.style.top = '{pos_cfg["css_top"]}';
                        leg.style.bottom = '{pos_cfg["css_bottom"]}';
                        leg.style.fontSize = '{chosen_size}px';
                        hud.style.whiteSpace = '{white_space}';
                        hud.style.maxWidth = '{max_w}';
                        hud.innerHTML = {json.dumps(html_content)};
                    }}""")

                    # Screenshot transparente
                    await page.screenshot(path=str(state_path), omit_background=True)

                    estados.append({
                        "estado_idx": state_counter,
                        "bloco_idx": b_idx,
                        "palavra_idx": w_idx,
                        "palavra_ativa": word["p"],
                        "texto_bloco": bm["texto"],
                        "ini": round(w_ini, 3),
                        "fim": round(w_fim, 3),
                        "duracao": dur,
                        "font_size": chosen_size,
                        "posicao_tipo": bm["posicao_tipo"],
                        "x_min": bm["x_min"],
                        "x_max": bm["x_max"],
                        "y_min": bm["y_min"],
                        "y_max": bm["y_max"],
                        "arquivo": f"estados/{state_filename}"
                    })

                    state_counter += 1

            # 3. Estado vazio (tela transparente entre fim do último bloco 58.24s e 60.00s)
            empty_filename = "estado_empty.png"
            empty_path = ESTADOS_DIR / empty_filename
            await page.evaluate("""() => {
                document.getElementById('hud').innerHTML = '';
                document.getElementById('hud').style.display = 'none';
            }""")
            await page.screenshot(path=str(empty_path), omit_background=True)

            last_fim = blocos[-1]["fim"]
            if last_fim < 60.0:
                estados.append({
                    "estado_idx": state_counter,
                    "bloco_idx": -1,
                    "palavra_idx": -1,
                    "palavra_ativa": "",
                    "texto_bloco": "[TRANSPARENTE]",
                    "ini": round(last_fim, 3),
                    "fim": 60.0,
                    "duracao": round(60.0 - last_fim, 4),
                    "font_size": 0,
                    "posicao_tipo": "N/A",
                    "x_min": 0,
                    "x_max": 0,
                    "y_min": 0,
                    "y_max": 0,
                    "arquivo": f"estados/{empty_filename}"
                })

        finally:
            await browser.close()
            print("Navegador Chromium encerrado com sucesso.")

    # Gravar estados.json
    output_meta = {
        "total_estados": len(estados),
        "total_blocos": len(blocos),
        "blocos_rebaixados_y360": list(LOWERED_BLOCKS_Y360),
        "blocos_embaixo_y1500": list(BOTTOM_BLOCKS_Y1500),
        "blocos_reduzidos": [{"bloco": r[0], "texto": r[1], "tamanho": r[2], "largura": r[3]} for r in reduced_blocks],
        "blocos_duas_linhas": multiline_blocks,
        "block_metrics": block_metrics,
        "estados": estados
    }
    ESTADOS_JSON.write_text(json.dumps(output_meta, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"Metadados salvos em {ESTADOS_JSON}")

    # Gravar estados.txt (ffconcat)
    concat_lines = ["ffconcat version 1.0"]
    for e in estados:
        concat_lines.append(f"file '{e['arquivo']}'")
        concat_lines.append(f"duration {e['duracao']:.6f}")
    # Repetir o último arquivo sem duration conforme especificação ffconcat
    concat_lines.append(f"file '{estados[-1]['arquivo']}'")
    FFCONCAT_TXT.write_text("\n".join(concat_lines) + "\n", encoding="utf-8")
    print(f"Arquivo ffconcat salvo em {FFCONCAT_TXT}")

    elapsed = time.time() - start_time
    print(f"Geração concluída em {elapsed:.1f}s ({len(estados)} estados gerados).")


if __name__ == "__main__":
    asyncio.run(asyncio.wait_for(main(), 120.0))
