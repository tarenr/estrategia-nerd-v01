"""Conferência do MP4 DECODIFICADO: integridade, quadros fixos e recortes (olho + números).

Subcomandos:
  integridade --mp4 M [--duracao-esperada S]
      ffprobe (duração, fps, resolução, áudio) + decodificação completa com ffmpeg (-v error); 0 erros esperados.
  quadros --mp4 M --tempos 40 41.9 [--crop X Y L A] [--saida PASTA] [--prancha ARQ.png]
      Extrai os quadros (tempo do MP4, não da composição), salva PNG, recorta opcionalmente (X Y L A em pixels do
      quadro) e monta uma prancha curta com rótulos. Imprime brilho médio e proporção de pixels quase iguais ao fundo
      do recorte (para detectar texto encoberto: compare com um quadro de referência via --ref).
  comparar-crop --mp4 M --ref-png P.png --tempo T --crop X Y L A
      Diferença média (0–255) entre o recorte do MP4 e o mesmo recorte de um PNG.

As saídas ficam em --saida (padrão: pasta 'conferencia' ao lado do MP4). Prazo de 120 s por chamada ao ffmpeg.
"""
import argparse
import json
import subprocess
import sys
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw

PRAZO = 120


def run(cmd):
    return subprocess.run(cmd, capture_output=True, text=True, timeout=PRAZO, encoding="utf-8", errors="replace")


def probe(mp4):
    r = run(["ffprobe", "-v", "error", "-show_entries", "format=duration:stream=codec_type,codec_name,width,height,r_frame_rate",
             "-of", "json", str(mp4)])
    return json.loads(r.stdout)


def cmd_integridade(a):
    info = probe(a.mp4)
    dur = float(info["format"]["duration"])
    v = [s for s in info["streams"] if s["codec_type"] == "video"]
    au = [s for s in info["streams"] if s["codec_type"] == "audio"]
    print(f"duração={dur:.3f}s vídeo={v[0]['width']}x{v[0]['height']} {v[0]['r_frame_rate']} {v[0]['codec_name']}; "
          f"áudio={'sim ' + au[0]['codec_name'] if au else 'NÃO'}")
    r = run(["ffmpeg", "-v", "error", "-i", str(a.mp4), "-f", "null", "-"])
    erros = [l for l in r.stderr.splitlines() if l.strip()]
    print(f"decodificação completa: {len(erros)} mensagens de erro")
    for l in erros[:10]:
        print("  ", l)
    ok = not erros and bool(au)
    if a.duracao_esperada:
        d = abs(dur - a.duracao_esperada)
        print(f"diferença de duração para {a.duracao_esperada}s: {d:.3f}s")
        ok = ok and d < 0.1
    return 0 if ok else 1


def extrai(mp4, t, destino):
    r = run(["ffmpeg", "-v", "error", "-y", "-ss", f"{t}", "-i", str(mp4), "-frames:v", "1", str(destino)])
    if r.returncode != 0 or not Path(destino).exists():
        raise SystemExit(f"falha ao extrair t={t}: {r.stderr[:200]}")
    return Image.open(destino).convert("RGB")


def cmd_quadros(a):
    saida = Path(a.saida) if a.saida else Path(a.mp4).parent / "conferencia"
    saida.mkdir(parents=True, exist_ok=True)
    imgs = []
    for t in a.tempos:
        im = extrai(a.mp4, t, saida / f"quadro_{t:06.2f}s.png")
        if a.crop:
            x, y, l, h = a.crop
            im = im.crop((x, y, x + l, y + h))
            im.save(saida / f"recorte_{t:06.2f}s.png")
        arr = np.asarray(im, dtype=np.float32)
        print(f"t={t:6.2f}s  tamanho={im.size}  brilho médio={arr.mean():.1f}  desvio={arr.std():.1f}")
        imgs.append((t, im))
    if a.prancha and imgs:
        h = 480
        thumbs = [(t, im.resize((int(im.width * h / im.height), h), Image.Resampling.LANCZOS)) for t, im in imgs]
        W = sum(i.width for _, i in thumbs) + 8 * (len(thumbs) + 1)
        prancha = Image.new("RGB", (W, h + 40), "#0a0e17")
        d = ImageDraw.Draw(prancha)
        x = 8
        for t, im in thumbs:
            prancha.paste(im, (x, 8))
            d.text((x + 4, h + 14), f"t={t:.2f}s", fill="#ffffff")
            x += im.width + 8
        prancha.save(a.prancha)
        print(f"prancha: {a.prancha}")
    return 0


def cmd_comparar(a):
    saida = Path(a.mp4).parent / "conferencia"
    saida.mkdir(parents=True, exist_ok=True)
    im = extrai(a.mp4, a.tempo, saida / f"cmp_{a.tempo:06.2f}s.png")
    ref = Image.open(a.ref_png).convert("RGB")
    x, y, l, h = a.crop
    if ref.size != im.size:
        ref = ref.resize(im.size, Image.Resampling.LANCZOS)
    A = np.asarray(im.crop((x, y, x + l, y + h)), dtype=np.float32)
    B = np.asarray(ref.crop((x, y, x + l, y + h)), dtype=np.float32)
    print(f"diferença média do recorte: {np.abs(A - B).mean():.2f} /255")
    return 0


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sp = ap.add_subparsers(dest="cmd", required=True)
    s = sp.add_parser("integridade")
    s.add_argument("--mp4", required=True)
    s.add_argument("--duracao-esperada", type=float)
    s = sp.add_parser("quadros")
    s.add_argument("--mp4", required=True)
    s.add_argument("--tempos", type=float, nargs="+", required=True)
    s.add_argument("--crop", type=int, nargs=4)
    s.add_argument("--saida")
    s.add_argument("--prancha")
    s = sp.add_parser("comparar-crop")
    s.add_argument("--mp4", required=True)
    s.add_argument("--ref-png", required=True)
    s.add_argument("--tempo", type=float, required=True)
    s.add_argument("--crop", type=int, nargs=4, required=True)
    a = ap.parse_args()
    sys.exit({"integridade": cmd_integridade, "quadros": cmd_quadros, "comparar-crop": cmd_comparar}[a.cmd](a))


if __name__ == "__main__":
    main()
