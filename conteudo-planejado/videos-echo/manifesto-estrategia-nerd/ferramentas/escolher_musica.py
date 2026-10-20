"""Mede as músicas da biblioteca do portal (public/uploads/audio/pixabay_*.mp3) e gera amostras para o usuário escolher.

Para cada faixa: duração, ritmo estimado (BPM por autocorrelação do envelope de ataques) e energia média.
Escolhe 3 candidatas (duração >= 62 s, 85–125 BPM, energia média-alta) e grava amostras de 15 s em
renders/v12_som/amostras/ (fade de entrada e saída). Só lê as músicas; prazo de 60 s por faixa.
"""
import json
import subprocess
from pathlib import Path

import numpy as np

RAIZ = Path(__file__).resolve().parent.parent
BIB = Path(r"C:\Users\WINDOWS\Projects\estrategia-nerd\public\uploads\audio")
SAI = RAIZ / "renders" / "v12_som" / "amostras"
SR = 8000


def decodifica(p, ini=20, dur=40):
    r = subprocess.run(["ffmpeg", "-v", "error", "-ss", str(ini), "-t", str(dur), "-i", str(p), "-ac", "1", "-ar", str(SR),
                        "-f", "f32le", "-"], capture_output=True, timeout=60)
    return np.frombuffer(r.stdout, dtype=np.float32)


def duracao(p):
    r = subprocess.run(["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", str(p)],
                       capture_output=True, text=True, timeout=30)
    return float(r.stdout.strip() or 0)


def bpm(x):
    hop = 80  # 10 ms
    n = len(x) // hop
    env = np.sqrt((x[: n * hop].reshape(n, hop) ** 2).mean(axis=1))
    on = np.maximum(np.diff(env), 0)
    on = on - on.mean()
    ac = np.correlate(on, on, mode="full")[len(on) - 1:]
    lags = np.arange(len(ac)) * hop / SR
    ok = (lags >= 60 / 160) & (lags <= 60 / 70)
    lag = lags[ok][np.argmax(ac[ok])]
    return 60 / lag


def main():
    SAI.mkdir(parents=True, exist_ok=True)
    linhas = []
    for p in sorted(BIB.glob("pixabay_*.mp3")):
        d = duracao(p)
        x = decodifica(p)
        if len(x) < SR * 10:
            continue
        rms = float(np.sqrt((x ** 2).mean()))
        linhas.append({"faixa": p.name, "dur": round(d, 1), "bpm": round(bpm(x)), "energia": round(20 * np.log10(rms + 1e-9), 1)})
    (SAI.parent / "musicas_medidas.json").write_text(json.dumps(linhas, indent=1), encoding="utf-8")
    cand = [l for l in linhas if l["dur"] >= 62 and 85 <= l["bpm"] <= 125]
    cand.sort(key=lambda l: -l["energia"])
    # 3 candidatas com ritmos diferentes
    escolhidas = []
    for l in cand:
        if all(abs(l["bpm"] - e["bpm"]) >= 8 for e in escolhidas):
            escolhidas.append(l)
        if len(escolhidas) == 3:
            break
    for i, l in enumerate(escolhidas, 1):
        dst = SAI / f"musica_{i}_{l['faixa'].replace('.mp3', '')}.mp3"
        subprocess.run(["ffmpeg", "-v", "error", "-y", "-ss", "20", "-t", "15", "-i", str(BIB / l["faixa"]),
                        "-af", "afade=t=in:d=0.8,afade=t=out:st=14:d=1", "-b:a", "160k", str(dst)], timeout=60, check=True)
        print(f"opção {i}: {l['faixa']}  {l['dur']} s  ~{l['bpm']} BPM  energia {l['energia']} dB  -> {dst.name}")
    print(f"faixas medidas: {len(linhas)}; candidatas: {len(cand)}")


if __name__ == "__main__":
    main()
