"""R0: prancha do roteiro visual de 43,5–60 s (rascunho para aprovação do usuário; NÃO é a animação final).

Usa quadros REAIS de cenas já aprovadas (v11, 0–18 s) e da v12 atual (Echo no quarto), com legendas de voz/tempo.
Saída: renders/v12_R0/storyboard_43_60.png (+ quadros em renders/v12_R0/quadros/). Prazo total 100 s; HTML só de src/.
"""
import asyncio
import io
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont
from playwright.async_api import async_playwright

RAIZ = Path(__file__).resolve().parent.parent
SRC = RAIZ / "src"
SAIDA = RAIZ / "renders" / "v12_R0"
(SAIDA / "quadros").mkdir(parents=True, exist_ok=True)

# (arquivo, tempo da composição, tempo da voz, frase, o que acontece)
CENAS = [
    ("manifesto-37s-v11.html", 3.3, "43,5–46,5 s", "“Porque uma boa análise não elimina a opinião.”",
     "CORTE para a praça do começo: o cartão com OPINIÃO e ANÁLISE lado a lado (volta do que o vídeo abriu)."),
    ("manifesto-37s-v11.html", 16.0, "46,9–49,1 s", "“Ela mostra de onde vêm as conclusões.”",
     "CORTE para o cartão real do portal: o gráfico de telemetria se desenha e o Echo aponta de onde vem o dado."),
    ("manifesto-37s-v11.html", 16.9, "49,6–51,1 s", "“Quer entender antes de escolher?”",
     "O cartão da decisão: 16 GB × 32 GB. O Echo olha para o espectador e a pergunta fica na tela."),
    ("manifesto-37s-v11.html", 5.2, "51,6–55,3 s", "“Estratégia Nerd. Nada de achismo. Só estratégia.”",
     "A praça de novo: ACHISMO é riscado e a marca do portal assume o cartão (símbolo + ESTRATÉGIA NERD, grande)."),
    ("manifesto-60s-v12.html", 59.0, "55,6–60 s", "“Acesse o link na bio e siga o perfil.”",
     "VOLTA ao Echo no quarto (aqui, o enquadramento atual; no final, mais perto do rosto), falando; LINK NA BIO e @estrategia_nerd grandes; aceno no silêncio final."),
]


def fonte(tam, negrito=False):
    for f in (r"C:\Windows\Fonts\arialbd.ttf" if negrito else r"C:\Windows\Fonts\arial.ttf",):
        if Path(f).exists():
            return ImageFont.truetype(f, tam)
    return ImageFont.load_default()


def quebra(d, txt, f, largura):
    linhas, atual = [], ""
    for w in txt.split():
        t = (atual + " " + w).strip()
        if d.textlength(t, font=f) <= largura:
            atual = t
        else:
            linhas.append(atual)
            atual = w
    linhas.append(atual)
    return linhas


async def captura():
    imgs = []
    async with async_playwright() as p:
        nav = await p.chromium.launch()
        for arq, t, *_ in CENAS:
            pg = await nav.new_page(viewport={"width": 1080, "height": 1920}, device_scale_factor=1)
            await pg.goto((SRC / arq).resolve().as_uri() + "?offset=0")
            await pg.evaluate("window.__ready")
            alvo = await pg.query_selector("#master-canvas")
            await pg.evaluate(f"window.__seek({t})")
            await pg.wait_for_timeout(60)
            im = Image.open(io.BytesIO(await alvo.screenshot())).convert("RGB")
            im.save(SAIDA / "quadros" / f"{arq[:14]}_{t:.1f}s.png")
            imgs.append(im)
            await pg.close()
        await nav.close()
    return imgs


def main():
    imgs = asyncio.run(asyncio.wait_for(captura(), 100))
    # último quadro: plano fechado no Echo + CTA (recorte do quarto)
    ult = imgs[-1].crop((0, 880, 1080, 1700)).resize((1080, 820))
    L, A = 330, 586  # miniatura
    miniaturas = []
    for i, im in enumerate(imgs):
        miniaturas.append(im.resize((L, A), Image.Resampling.LANCZOS))
    W = 5 * (L + 14) + 14
    H = 90 + A + 215
    folha = Image.new("RGB", (W, H), "#0a0e17")
    d = ImageDraw.Draw(folha)
    d.text((14, 12), "Manifesto v12 — desenho das cenas de 43,5 a 60 s (rascunho para aprovação; quadros reais das cenas já aprovadas)",
           font=fonte(20, True), fill="#ffffff")
    d.text((14, 44), "Até 43,5 s: fica no quarto (como está), com o Echo falando. A partir daqui, as cenas mudam:",
           font=fonte(17), fill="#00d4ff")
    for i, (arq, t, tv, frase, o_que) in enumerate(CENAS):
        x = 14 + i * (L + 14)
        folha.paste(miniaturas[i], (x, 80))
        d.rectangle([x, 80, x + L, 80 + A], outline="#00d4ff", width=2)
        y = 80 + A + 10
        d.text((x, y), tv, font=fonte(18, True), fill="#f5a524")
        y += 26
        for ln in quebra(d, frase, fonte(16, True), L):
            d.text((x, y), ln, font=fonte(16, True), fill="#ffffff")
            y += 20
        y += 4
        for ln in quebra(d, o_que, fonte(15), L):
            d.text((x, y), ln, font=fonte(15), fill="#cbd5e1")
            y += 19
    folha.save(SAIDA / "storyboard_43_60.png")
    print("prancha:", SAIDA / "storyboard_43_60.png", folha.size)


if __name__ == "__main__":
    main()
