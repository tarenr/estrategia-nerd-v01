"""Renderiza uma janela do Castlevania (v2) em segmentos (cada comando do OneTake < 150 s) e junta sem áudio.
Cópia do render do Manifesto (manifesto-estrategia-nerd/ferramentas/render_v12_janela.py), apontando para quadros.html.
Uso: render_janela_v2.py --de 0 --ate 44.57 --rotulo final [--seg 3]
Saída: renders/<rotulo>/  (segmentos em seg_N/, mudo 1080x1920 a 60 fps). O render.py do OneTake apaga os _frames
ao lado de cada seg.mp4 (por isso cada segmento tem a sua pasta). A página recebe ?offset=<início>&dur=<duração>.
"""
import argparse
import subprocess
import sys
import time
from pathlib import Path

RAIZ = Path(__file__).resolve().parent
HTML = RAIZ / "quadros.html"
ONETAKE = Path(r"C:\Users\WINDOWS\Projects\media\onetake")


def roda(cmd, prazo, sair=True):
    print(">", " ".join(str(c) for c in cmd)[:200], flush=True)
    p = subprocess.Popen([str(c) for c in cmd])
    try:
        p.wait(timeout=prazo)
    except subprocess.TimeoutExpired:
        subprocess.run(["taskkill", "/PID", str(p.pid), "/T", "/F"], capture_output=True)
        if sair:
            sys.exit(f"ERRO: comando passou de {prazo}s e foi encerrado")
        print(f"AVISO: comando passou de {prazo}s e foi encerrado", flush=True)
        return False
    if p.returncode != 0:
        sys.exit(f"ERRO: código {p.returncode}")
    return True


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--de", type=float, required=True)
    ap.add_argument("--ate", type=float, required=True)
    ap.add_argument("--rotulo", required=True)
    # 1 s por pedaço: com 3 s um navegador emperrou no fim do pedaço (01/11/2026); 1 s leva ~25 s com desfoque
    ap.add_argument("--seg", type=float, default=1.0)
    a = ap.parse_args()
    saida = RAIZ / "renders" / a.rotulo
    saida.mkdir(parents=True, exist_ok=True)
    segs, t, i = [], a.de, 0
    t0 = time.time()
    while t < a.ate - 1e-6:
        d = min(a.seg, a.ate - t)
        pasta = saida / f"seg_{i}"
        pasta.mkdir(exist_ok=True)
        mp4 = pasta / "seg.mp4"
        inicio = time.time()
        cmd = [ONETAKE / ".venv" / "Scripts" / "python.exe", ONETAKE / "scripts" / "render.py",
               f"{HTML}?offset={t:.4f}&dur={d:.4f}",
               "--width", "1080", "--height", "1920", "--fps", "60", "--dur", f"{d}", "--shutter", "180",
               "--samples", "4", "--workers", "8", "--out", mp4]
        # um pedaço que emperra tem uma segunda chance; se emperrar de novo, o render para
        if not roda(cmd, 150, sair=False):
            roda(cmd, 150)
        print(f"segmento {i} ({t:.2f}–{t + d:.2f} s): {time.time() - inicio:.0f} s", flush=True)
        segs.append(mp4)
        t += d
        i += 1
    lista = saida / "lista.txt"
    lista.write_text("".join(f"file '{s.as_posix()}'\n" for s in segs), encoding="utf-8")
    mudo = saida / f"castlevania_{a.rotulo}_mudo_1080x1920.mp4"
    roda(["ffmpeg", "-v", "error", "-y", "-f", "concat", "-safe", "0", "-i", lista, "-c", "copy", mudo], 120)
    print(f"PRONTO {mudo} em {time.time() - t0:.0f}s")


if __name__ == "__main__":
    main()
