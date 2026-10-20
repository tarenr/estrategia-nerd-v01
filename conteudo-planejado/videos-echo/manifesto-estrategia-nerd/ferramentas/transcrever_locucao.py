"""B1 da Fase B: transcreve a locução oficial e mede o tempo de cada palavra (Whisper 'base', já em ~/.cache/whisper).

Lê  ../assets/audio/locucao-guia.wav (somente leitura) e grava, em storyboard/:
  whisper_palavras_locucao.json   - todas as palavras (0–60 s) com início/fim em segundos
  narration-timing-37-60.json     - cues de 37–60 s: frase, início, fim, pausa depois, palavras
Pausas vêm do ffmpeg silencedetect (-35 dB, 0,25 s). Nada é regravado no áudio.
O texto reconhecido pode ter erros de grafia: o que vale é o que se OUVE; o relatório mostra o reconhecido ao lado
do roteiro de gerar_locucao_completa.py para o usuário conferir.
"""
import json
import re
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
WAV = (RAIZ.parent / "assets" / "audio" / "locucao-guia.wav").resolve()
SAIDA = RAIZ / "storyboard"

ROTEIRO_ANTIGO = {
    "ato_04 (37–51 s no script)": "Nosso método é simples: questionar, investigar, comparar fontes e explicar com clareza. "
                                  "Porque uma boa análise não elimina a opinião. Ela mostra de onde vêm as conclusões.",
    "ato_05 (51–60 s no script)": "Quer entender antes de escolher? Estratégia Nerd. Nada de achismo. Só estratégia. Siga o portal.",
}


def silencios():
    r = subprocess.run(["ffmpeg", "-hide_banner", "-i", str(WAV), "-af", "silencedetect=noise=-35dB:d=0.25", "-f", "null", "-"],
                       capture_output=True, text=True, timeout=120, encoding="utf-8", errors="replace")
    ini = [float(x) for x in re.findall(r"silence_start: ([\d.]+)", r.stderr)]
    dur = [float(x) for x in re.findall(r"silence_duration: ([\d.]+)", r.stderr)]
    return list(zip(ini, dur))


def main():
    import whisper
    modelo = whisper.load_model("base")
    res = modelo.transcribe(str(WAV), language="pt", word_timestamps=True, fp16=False,
                            condition_on_previous_text=False, temperature=0.0)
    palavras = []
    for seg in res["segments"]:
        for w in seg.get("words", []):
            palavras.append({"palavra": w["word"].strip(), "ini": round(float(w["start"]), 3), "fim": round(float(w["end"]), 3)})
    SAIDA.mkdir(exist_ok=True)
    (SAIDA / "whisper_palavras_locucao.json").write_text(
        json.dumps({"modelo": "whisper base", "audio": WAV.name, "texto": res["text"].strip(), "palavras": palavras},
                   ensure_ascii=False, indent=1), encoding="utf-8")

    sil = silencios()
    # corta cues nas pausas >= 0,25 s (silencedetect) dentro de 37–60 s
    cortes = sorted(s for s, d in sil if 36.5 <= s <= 60)
    cues, atual, k = [], [], 0
    for p in palavras:
        if p["ini"] < 36.9:
            continue
        atual.append(p)
        prox = [c for c in cortes if c >= p["fim"] - 0.12]
        fim_cue = bool(prox) and prox[0] <= p["fim"] + 0.2
        if fim_cue:
            k += 1
            dur_pausa = next((d for s, d in sil if abs(s - prox[0]) < 1e-6), 0)
            cues.append({"id": f"cue_{k:02d}", "frase": " ".join(w["palavra"] for w in atual),
                         "ini": atual[0]["ini"], "fim": atual[-1]["fim"], "pausa_depois_s": round(dur_pausa, 3), "palavras": atual})
            atual = []
    if atual:
        k += 1
        cues.append({"id": f"cue_{k:02d}", "frase": " ".join(w["palavra"] for w in atual), "ini": atual[0]["ini"],
                     "fim": atual[-1]["fim"], "pausa_depois_s": None, "palavras": atual})
    (SAIDA / "narration-timing-37-60.json").write_text(
        json.dumps({"audio": WAV.name, "modelo": "whisper base (grafia pode errar; vale o que se ouve)",
                    "silencios_ffmpeg": [{"ini": round(s, 3), "dur": round(d, 3)} for s, d in sil if s >= 36],
                    "roteiro_antigo_do_script": ROTEIRO_ANTIGO, "cues": cues}, ensure_ascii=False, indent=1), encoding="utf-8")
    print("palavras:", len(palavras), "| cues 37–60:", len(cues))
    for c in cues:
        print(f"{c['id']} {c['ini']:6.2f}–{c['fim']:6.2f}  {c['frase']}")


if __name__ == "__main__":
    sys.exit(main())
