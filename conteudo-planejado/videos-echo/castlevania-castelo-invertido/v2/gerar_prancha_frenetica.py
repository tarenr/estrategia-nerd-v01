import json
import subprocess
import textwrap
from pathlib import Path

from PIL import Image, ImageDraw, ImageEnhance, ImageFont, ImageOps


RAIZ = Path(__file__).resolve().parent
PASTA_ORIGINAL = Path(r"E:\Taren\Documents\1 - Projeto Renda Extra\Postagens\Estratégia Nerd\Curiosidades e dicas\Castlevania - SOTN")
GAMEPLAY = PASTA_ORIGINAL / "GamePlay.mp4"
TRAILER = PASTA_ORIGINAL / "trailer.mp4"
MAPA = PASTA_ORIGINAL / "sotnmap1-SF.png"
PRESS_START = Path(r"C:\Users\WINDOWS\AppData\Local\Temp\codex-clipboard-95141030-6040-414d-a115-4c83ce9ee360.png")
ENCERRAMENTO = RAIZ / "assets" / "teste_t40s_encerramento.png"
SAIDA = RAIZ / "prancha-frenetica"
FONT = Path(r"C:\Windows\Fonts\arial.ttf")


def quadro_video(video: Path, tempo: float, saida: Path):
    subprocess.run([
        "ffmpeg", "-y", "-hide_banner", "-loglevel", "error", "-i", str(video),
        "-ss", str(tempo), "-frames:v", "1", str(saida),
    ], check=True)


def ajustar_640x480(img: Image.Image) -> Image.Image:
    fundo = Image.new("RGB", (640, 480), "black")
    contido = ImageOps.contain(img.convert("RGB"), (640, 480), Image.Resampling.LANCZOS)
    fundo.paste(contido, ((640 - contido.width) // 2, (480 - contido.height) // 2))
    return fundo


def painel(item: dict, imagem: Image.Image) -> Image.Image:
    base = Image.new("RGB", (640, 590), "#101018")
    base.paste(ajustar_640x480(imagem), (0, 0))
    draw = ImageDraw.Draw(base)
    titulo = ImageFont.truetype(str(FONT), 22)
    corpo = ImageFont.truetype(str(FONT), 18)
    draw.text((14, 490), f"{item['n']:02d}  {item['inicio']:.2f}–{item['fim']:.2f}s  ·  {item['fonte']}", font=titulo, fill="white")
    acao = " / ".join(textwrap.wrap(item["acao"], 54))
    transicao = " / ".join(textwrap.wrap("Transição: " + item["transicao"], 54))
    draw.text((14, 521), acao, font=corpo, fill="#d9e2ff")
    draw.text((14, 551), transicao, font=corpo, fill="#75e7ff")
    return base


def montar_pagina(paineis: list[Image.Image], caminho: Path):
    linhas = (len(paineis) + 1) // 2
    folha = Image.new("RGB", (1300, linhas * 600), "#07040d")
    for i, p in enumerate(paineis):
        folha.paste(p, ((i % 2) * 650 + 5, (i // 2) * 600 + 5))
    folha.save(caminho, optimize=True)


def main():
    SAIDA.mkdir(exist_ok=True)
    itens = json.loads((RAIZ / "timeline-frenetica.json").read_text(encoding="utf-8"))
    paineis = []

    for item in itens:
        bruto = SAIDA / f"bruto_{item['n']:02d}.png"
        if item["fonte"] == "press-start":
            captura = Image.open(PRESS_START)
            imagem = captura.crop((0, 180, captura.width, 720))
        elif item["fonte"] == "encerramento":
            imagem = Image.open(ENCERRAMENTO)
        elif item["fonte"] == "mapa":
            fundo_path = SAIDA / "fundo_mapa.png"
            quadro_video(GAMEPLAY, 200.0, fundo_path)
            fundo = ImageEnhance.Brightness(Image.open(fundo_path).convert("RGB")).enhance(0.35)
            mapa = ImageOps.contain(Image.open(MAPA).convert("RGBA"), (600, 460), Image.Resampling.LANCZOS)
            fundo = fundo.convert("RGBA")
            fundo.alpha_composite(mapa, ((640 - mapa.width) // 2, (480 - mapa.height) // 2))
            imagem = fundo.convert("RGB")
        else:
            fonte = GAMEPLAY if item["fonte"] == "gameplay" else TRAILER
            quadro_video(fonte, item["origem"], bruto)
            imagem = Image.open(bruto)
        paineis.append(painel(item, imagem))

    montar_pagina(paineis[:10], SAIDA / "prancha-01-planos-01-10.png")
    montar_pagina(paineis[10:20], SAIDA / "prancha-02-planos-11-20.png")
    montar_pagina(paineis[20:], SAIDA / "prancha-03-planos-21-29.png")

    captura = Image.open(PRESS_START)
    base_on = ajustar_640x480(captura.crop((0, 180, captura.width, 720)))
    off_path = SAIDA / "press_start_off.png"
    quadro_video(GAMEPLAY, 3.5, off_path)
    estados = [
        painel({"n": 1, "inicio": 0, "fim": 0.8, "fonte": "PRESS START aceso", "acao": "opção visível", "transicao": "piscar natural"}, base_on),
        painel({"n": 2, "inicio": 0.8, "fim": 1.6, "fonte": "PRESS START apagado", "acao": "opção invisível", "transicao": "piscar natural"}, Image.open(off_path)),
        painel({"n": 3, "inicio": 1.6, "fim": 2.8, "fonte": "PRESS START aceso", "acao": "opção retorna antes do zoom", "transicao": "zoom no monitor"}, base_on),
    ]
    folha = Image.new("RGB", (1950, 600), "#07040d")
    for i, estado in enumerate(estados):
        folha.paste(estado, (i * 650 + 5, 5))
    folha.save(SAIDA / "prancha-press-start-3-estados.png", optimize=True)

    print("3 pranchas temporais + prancha PRESS START geradas")


if __name__ == "__main__":
    main()
