"""Renderiza uma janela da v12 em segmentos (cada comando do OneTake < 150 s), junta e muxa a locução.

Uso: render_v12_janela.py --de 38 --ate 48 --rotulo B2 [--seg 5] [--escala 540]
Saída: renders/v12_<rotulo>/  (segmentos em seg_N/, mudo 1080x1920, prévia <escala>x... com a locução de de..ate).
NÃO copia nada para public/uploads (decisão do usuário em 19/10/2026: as prévias vão pelo chat).
O HTML é sempre src/manifesto-60s-v12.html com ?offset=<início do segmento> (sem offset a página começa em 10 s).
"""
import argparse
import subprocess
import sys
import time
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
HTML = RAIZ / "src" / "manifesto-60s-v12.html"
VOZ = RAIZ.parent / "assets" / "audio" / "locucao-guia.wav"
ONETAKE = Path(r"C:\Users\WINDOWS\Projects\onetake")


def roda(cmd, prazo):
    print(">", " ".join(str(c) for c in cmd)[:200], flush=True)
    p = subprocess.Popen([str(c) for c in cmd])
    try:
        p.wait(timeout=prazo)
    except subprocess.TimeoutExpired:
        subprocess.run(["taskkill", "/PID", str(p.pid), "/T", "/F"], capture_output=True)
        sys.exit(f"ERRO: comando passou de {prazo}s e foi encerrado")
    if p.returncode != 0:
        sys.exit(f"ERRO: código {p.returncode}")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--de", type=float, required=True)
    ap.add_argument("--ate", type=float, required=True)
    ap.add_argument("--rotulo", required=True)
    ap.add_argument("--seg", type=float, default=5.0)
    ap.add_argument("--escala", type=int, default=540)
    a = ap.parse_args()
    saida = RAIZ / "renders" / f"v12_{a.rotulo}"
    saida.mkdir(parents=True, exist_ok=True)
    segs, t, i = [], a.de, 0
    t0 = time.time()
    while t < a.ate - 1e-6:
        d = min(a.seg, a.ate - t)
        pasta = saida / f"seg_{i}"
        pasta.mkdir(exist_ok=True)
        mp4 = pasta / "seg.mp4"
        roda([ONETAKE / ".venv" / "Scripts" / "python.exe", ONETAKE / "scripts" / "render.py", f"{HTML}?offset={t}",
              "--width", "1080", "--height", "1920", "--fps", "60", "--dur", f"{d}", "--shutter", "180",
              "--samples", "4", "--workers", "8", "--out", mp4], 150)
        segs.append(mp4)
        t += d
        i += 1
    lista = saida / "lista.txt"
    lista.write_text("".join(f"file '{s.as_posix()}'\n" for s in segs), encoding="utf-8")
    mudo = saida / f"v12_{a.rotulo}_mudo_1080x1920.mp4"
    roda(["ffmpeg", "-v", "error", "-y", "-f", "concat", "-safe", "0", "-i", lista, "-c", "copy", mudo], 120)
    h = int(round(a.escala * 16 / 9 / 2) * 2)
    final = saida / f"v12_{a.rotulo}_{a.de:g}_{a.ate:g}_{a.escala}x{h}.mp4"
    roda(["ffmpeg", "-v", "error", "-y", "-i", mudo, "-ss", f"{a.de}", "-t", f"{a.ate - a.de}", "-i", VOZ,
          "-vf", f"scale={a.escala}:{h}", "-map", "0:v:0", "-map", "1:a:0", "-c:v", "libx264", "-preset", "fast",
          "-crf", "18", "-pix_fmt", "yuv420p", "-c:a", "aac", "-b:a", "192k", "-shortest", final], 150)
    print(f"PRONTO {final} em {time.time() - t0:.0f}s")


if __name__ == "__main__":
    main()
