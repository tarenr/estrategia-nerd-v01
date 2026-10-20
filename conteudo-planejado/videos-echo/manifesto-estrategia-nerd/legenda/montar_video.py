"""montar_video.py
Monta o vídeo final renders/v12_pos/legenda_hud/v12_final_hud_540x960.mp4:
- Sobrepõe os estados da legenda HUD (ffconcat) escalados para 540x960 sobre
  renders/v12_Q/v12_Q_0_60_540x960.mp4 a 60 fps mantendo transparência.
- Incorpora o áudio mixado de renders/v12_pos/som/mix_manifesto.wav (-16 LUFS) codificado em AAC 256k.
- Garante exatamente 60,000 s de duração a 60 fps.
"""

import subprocess
import sys
import time
from pathlib import Path

SCRIPT_DIR = Path(__file__).resolve().parent
ROOT_DIR = SCRIPT_DIR.parent.parent.parent

VIDEO_IN = ROOT_DIR / "renders" / "v12_Q" / "v12_Q_0_60_540x960.mp4"
AUDIO_IN = ROOT_DIR / "renders" / "v12_pos" / "som" / "mix_manifesto.wav"
CONCAT_TXT = SCRIPT_DIR / "estados.txt"
VIDEO_OUT = SCRIPT_DIR / "v12_final_hud_540x960.mp4"


def main():
    start = time.time()
    print("Iniciando montagem do vídeo com FFmpeg...")
    print(f"Vídeo base: {VIDEO_IN} (existe: {VIDEO_IN.exists()})")
    print(f"Áudio mix: {AUDIO_IN} (existe: {AUDIO_IN.exists()})")
    print(f"Concat legendas: {CONCAT_TXT} (existe: {CONCAT_TXT.exists()})")
    print(f"Saída: {VIDEO_OUT}")

    if not VIDEO_IN.exists() or not AUDIO_IN.exists() or not CONCAT_TXT.exists():
        sys.exit("ERRO: Um ou mais arquivos de entrada não existem.")

    # Comando FFmpeg com filtro complexo:
    # 1. Escala a sequência de PNGs transparente para 540x960 com interpolação lanczos a 60 fps
    # 2. Faz overlay sobre o vídeo base com formato auto (preserva alfa)
    # 3. Mapeia o áudio da mixagem com codec aac
    # 4. Trava duração em exatamente 60.000 s
    filter_desc = (
        "[1:v]scale=540:960:flags=lanczos,fps=60[sub];"
        "[0:v][sub]overlay=0:0:format=auto[v]"
    )

    cmd = [
        "ffmpeg", "-y",
        "-i", str(VIDEO_IN),
        "-f", "concat", "-safe", "0", "-i", str(CONCAT_TXT),
        "-i", str(AUDIO_IN),
        "-filter_complex", filter_desc,
        "-map", "[v]",
        "-map", "2:a",
        "-c:v", "libx264",
        "-preset", "medium",
        "-crf", "18",
        "-pix_fmt", "yuv420p",
        "-r", "60",
        "-c:a", "aac",
        "-b:a", "256k",
        "-t", "60.000",
        str(VIDEO_OUT)
    ]

    print("Executando FFmpeg...")
    p = subprocess.run(cmd, capture_output=True, text=True)
    if p.returncode != 0:
        print("ERRO FFmpeg:", p.stderr[-1000:])
        sys.exit(p.returncode)

    elapsed = time.time() - start
    print(f"Montagem concluída com sucesso em {elapsed:.1f}s.")
    print(f"Tamanho do arquivo: {VIDEO_OUT.stat().st_size} bytes.")


if __name__ == "__main__":
    main()
