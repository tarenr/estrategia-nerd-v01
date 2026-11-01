import json
import subprocess
from pathlib import Path


RAIZ = Path(__file__).resolve().parent
TIMELINE = RAIZ / "timeline-frenetica.json"
ORIGEM = Path(r"E:\Taren\Documents\1 - Projeto Renda Extra\Postagens\Estratégia Nerd\Curiosidades e dicas\Castlevania - SOTN")
SAIDA = RAIZ / "assets" / "gameplay_sync.mp4"


def montar_comando():
    planos = json.loads(TIMELINE.read_text(encoding="utf-8"))
    entradas = []
    filtros = []
    labels = []
    duracoes = []

    fontes = {
        "trailer": ORIGEM / "trailer.mp4",
        "gameplay": ORIGEM / "GamePlay.mp4",
    }
    imagem_on = Path(r"C:\Users\WINDOWS\AppData\Local\Temp\codex-clipboard-95141030-6040-414d-a115-4c83ce9ee360.png")
    imagem_off = RAIZ / "prancha-frenetica" / "press_start_off.png"
    mapa = ORIGEM / "sotnmap1-SF.png"

    # PRESS START em três estados, conforme a prancha aprovada.
    especiais = [
        (imagem_on, 0.80, True),
        (imagem_off, 0.80, False),
        (imagem_on, 1.20, True),
    ]
    for caminho, duracao, recortar_cursor in especiais:
        idx = len(labels)
        entradas += ["-loop", "1", "-t", str(duracao + 0.14), "-i", str(caminho)]
        recorte = "crop=896:540:0:180," if recortar_cursor else ""
        filtros.append(f"[{idx}:v]{recorte}scale=1080:810:force_original_aspect_ratio=decrease,pad=1080:810:(ow-iw)/2:(oh-ih)/2:black,fps=30,format=yuv420p[v{idx}]")
        labels.append(f"v{idx}")
        duracoes.append(duracao)

    for plano in planos[1:-1]:
        duracao = round(plano["fim"] - plano["inicio"], 3)
        idx = len(labels)
        if plano["fonte"] == "mapa":
            entradas += ["-loop", "1", "-t", str(duracao + 0.14), "-i", str(mapa)]
            filtros.append(f"[{idx}:v]scale=1040:770:force_original_aspect_ratio=decrease,pad=1080:810:(ow-iw)/2:(oh-ih)/2:black,fps=30,format=yuv420p[v{idx}]")
        else:
            entradas += ["-ss", str(plano["origem"]), "-t", str(duracao + 0.14), "-i", str(fontes[plano["fonte"]])]
            filtros.append(f"[{idx}:v]scale=1080:810:flags=lanczos,fps=30,format=yuv420p[v{idx}]")
        labels.append(f"v{idx}")
        duracoes.append(duracao)

    # O último trecho continua exibindo gameplay no monitor; a câmera do canvas
    # é que revela novamente o quarto e o Echo.
    ultimo = planos[-1]
    duracao = round(ultimo["fim"] - ultimo["inicio"], 3)
    idx = len(labels)
    entradas += ["-ss", "42.5", "-t", str(duracao + 0.14), "-i", str(fontes["trailer"])]
    filtros.append(f"[{idx}:v]scale=1080:810:flags=lanczos,fps=30,format=yuv420p[v{idx}]")
    labels.append(f"v{idx}")
    duracoes.append(duracao)

    atual = labels[0]
    acumulado = duracoes[0]
    transicao = 4 / 30
    tipos = [
        "fade", "fade", "wipeup", "fadewhite", "dissolve", "circleopen",
        "slideright", "radial", "zoomin", "fadewhite", "dissolve", "pixelize",
        "fade", "zoomin", "smoothup", "fadewhite", "slideleft", "radial",
        "circleopen", "wipeleft", "fade", "slideright", "zoomin", "dissolve",
        "slideup", "fadeblack", "circleopen", "smoothright", "fade", "fade"
    ]
    for i in range(1, len(labels)):
        saida = f"x{i}"
        offset = acumulado - transicao
        filtros.append(f"[{atual}][{labels[i]}]xfade=transition={tipos[i - 1]}:duration={transicao:.6f}:offset={offset:.6f}[{saida}]")
        atual = saida
        acumulado += duracoes[i] - transicao

    # As sobreposições encurtam a cadeia; desacelerar minimamente recompõe 44,57 s.
    fator = 44.57 / acumulado
    filtros.append(f"[{atual}]setpts={fator:.9f}*PTS,trim=duration=44.57,setpts=PTS-STARTPTS[outv]")
    return ["ffmpeg", "-y", *entradas, "-filter_complex", ";".join(filtros), "-map", "[outv]", "-an", "-c:v", "libx264", "-preset", "medium", "-crf", "18", "-pix_fmt", "yuv420p", "-movflags", "+faststart", str(SAIDA)]


if __name__ == "__main__":
    subprocess.run(montar_comando(), check=True)
