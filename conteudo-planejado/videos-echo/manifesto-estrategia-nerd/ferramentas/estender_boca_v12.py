"""Estende a tabela window.BOCA_ATO03 da v12 até 60 s, pelo MESMO método de update_v12.py (volume da locução por quadro).

Hoje a tabela termina em 42,183 s e depois dela o código devolve boca fechada. Este script:
  - lê ../assets/audio/locucao-guia.wav (somente leitura);
  - mantém TODOS os pontos existentes (inalterados) e acrescenta um ponto por quadro (1/60 s) de 42,2 a 60,0 s;
  - regrava só a linha window.BOCA_ATO03 = [...]; em src/manifesto-60s-v12.html;
  - grava o que acrescentou em storyboard/boca_42_60.json.
Uso: estender_boca_v12.py [--aplicar]   (sem --aplicar só mostra o que faria)
"""
import json
import math
import re
import struct
import sys
import wave
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
V12 = RAIZ / "src" / "manifesto-60s-v12.html"
WAV = RAIZ.parent / "assets" / "audio" / "locucao-guia.wav"
SAIDA = RAIZ / "storyboard" / "boca_42_60.json"
INI, FIM, FPS = 42.2, 60.0, 60


def calcula():
    with wave.open(str(WAV), "r") as w:
        rate, canais = w.getframerate(), w.getnchannels()
        w.setpos(int((INI - 0.1) * rate))
        raw = w.readframes(int((FIM - INI + 0.4) * rate))
    s = struct.unpack(f"<{len(raw) // 2}h", raw)
    mono = [(s[i] + s[i + 1]) / 2.0 for i in range(0, len(s), 2)] if canais == 2 else list(s)
    base = int(0.1 * rate)
    quadro = rate / FPS
    pts = []
    n = int(round((FIM - INI) * FPS)) + 1
    for f in range(n):
        t = INI + f / FPS
        i0 = base + int(f * quadro)
        i1 = base + int((f + 1) * quadro)
        chunk = mono[i0:i1]
        rms = math.sqrt(sum(x * x for x in chunk) / max(1, len(chunk))) if chunk else 0
        val = min(1.0, max(0.0, (rms - 300) / 2000))
        if val < 0.06:
            val = 0.0
        pts.append([round(t, 3), round(val, 2)])
    return pts


def main():
    html = V12.read_text(encoding="utf-8")
    m = re.search(r"window\.BOCA_ATO03\s*=\s*(\[\[.*?\]\])\s*;", html, re.S)
    if not m:
        sys.exit("não achei window.BOCA_ATO03")
    antigos = json.loads(m.group(1))
    ultimo = antigos[-1][0]
    novos = [p for p in calcula() if p[0] > ultimo]
    abertos = sum(1 for _, v in novos if v > 0.3)
    print(f"tabela atual: {len(antigos)} pontos, de {antigos[0][0]} a {ultimo}")
    print(f"novos: {len(novos)} pontos, de {novos[0][0]} a {novos[-1][0]}; com boca > 0,3 em {abertos / len(novos):.0%} do tempo; máx {max(v for _, v in novos)}")
    sem_fala = [p[0] for p in novos if p[0] > 57.7 and p[1] > 0.06]
    print(f"pontos com boca aberta depois de 57,7 s (silêncio): {len(sem_fala)}")
    if "--aplicar" not in sys.argv:
        print("(simulação: nada foi gravado)")
        return
    SAIDA.write_text(json.dumps(novos, separators=(",", ":")), encoding="utf-8")
    comb = antigos + novos
    novo_html = html.replace(m.group(0), f"window.BOCA_ATO03 = {json.dumps(comb, separators=(',', ':'))};")
    V12.write_text(novo_html, encoding="utf-8")
    print("gravado:", V12.name, "| pontos agora:", len(comb))


if __name__ == "__main__":
    main()
