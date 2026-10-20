"""Versão final 1080x1920 do Manifesto Estratégia Nerd (v12), aprovada pelo usuário em 20/10/2026.

Junta, sem renderizar nada de novo:
  - vídeo aprovado em 1080x1920 (sem áudio): renders/v12_Q/v12_Q_mudo_1080x1920.mp4
  - legenda HUD em Orbitron (PNGs 1080x1920 transparentes + ffconcat): renders/v12_pos/legenda_hud/estados.txt
  - áudio final (narração + música 3 + efeitos, -16 LUFS): renders/v12_pos/som/mix_manifesto.wav
Saída: renders/v12_final/manifesto_estrategia_nerd_v12_1080x1920.mp4 (60 fps, H.264 + AAC). Prazo do ffmpeg: 900 s.
"""
import subprocess
import sys
import time
from pathlib import Path

AQUI = Path(__file__).resolve().parent
RAIZ = AQUI.parent.parent
VIDEO = RAIZ / "renders" / "v12_Q" / "v12_Q_mudo_1080x1920.mp4"
LEGENDA = RAIZ / "renders" / "v12_pos" / "legenda_hud" / "estados.txt"
AUDIO = RAIZ / "renders" / "v12_pos" / "som" / "mix_manifesto.wav"
SAIDA = AQUI / "manifesto_estrategia_nerd_v12_1080x1920.mp4"


def main():
    for p in (VIDEO, LEGENDA, AUDIO):
        if not p.exists():
            sys.exit(f"ERRO: falta {p}")
    t0 = time.time()
    cmd = ["ffmpeg", "-v", "error", "-y", "-i", str(VIDEO), "-f", "concat", "-safe", "0", "-i", str(LEGENDA), "-i", str(AUDIO),
           "-filter_complex", "[1:v]fps=60[sub];[0:v][sub]overlay=0:0:format=auto[v]",
           "-map", "[v]", "-map", "2:a", "-c:v", "libx264", "-preset", "medium", "-crf", "17", "-pix_fmt", "yuv420p",
           "-r", "60", "-movflags", "+faststart", "-c:a", "aac", "-b:a", "256k", "-t", "60.000", str(SAIDA)]
    p = subprocess.run(cmd, capture_output=True, text=True, timeout=900)
    if p.returncode != 0:
        sys.exit("ERRO ffmpeg: " + p.stderr[-800:])
    print(f"PRONTO {SAIDA} ({SAIDA.stat().st_size} bytes) em {time.time() - t0:.0f}s")


if __name__ == "__main__":
    main()
