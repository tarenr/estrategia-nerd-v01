import asyncio
import json
import re
import subprocess
from pathlib import Path
import edge_tts
import whisper

AUDIO_DIR = Path("C:/Users/WINDOWS/Projects/estrategia-nerd/conteudo-planejado/videos-echo/castlevania-castelo-invertido/audio")
AUDIO_DIR.mkdir(parents=True, exist_ok=True)

MP3_FILE = AUDIO_DIR / "locucao_completa.mp3"
WAV_FILE = AUDIO_DIR / "locucao_completa.wav"
VOICE = "pt-BR-AntonioNeural"

TEXTO_OFICIAL_CALIBRADO = (
    "Em 1997, a indústria dizia que o 2D estava morto para o 3D. Aí a Konami fez isso aqui. "
    "Quando você derrota o Rícter Belmont usando os óculos sagrados, o jogo não acaba. "
    "O castelo inteiro vira de cabeça para baixo. Um novo mapa, outros chefes e a física invertida. "
    "Uma jogada genial de engenharia: dobraram a duração do jogo reutilizando os mesmos cenários, texturas e blocos, "
    "sem estourar o orçamento e redefinindo o gênero Metróid-Vânia para sempre. "
    "Você conseguiu zerar os 200% nos anos 90 ou travou no primeiro castelo? Me conta aí nos comentários."
)

async def gerar_mp3():
    comm = edge_tts.Communicate(TEXTO_OFICIAL_CALIBRADO, VOICE)
    await comm.save(str(MP3_FILE))
    print(f"MP3 oficial gravado: {MP3_FILE.name} ({MP3_FILE.stat().st_size} bytes)")

def gerar_wav():
    cmd = ["ffmpeg", "-y", "-i", str(MP3_FILE), "-ar", "16000", "-ac", "1", str(WAV_FILE)]
    subprocess.run(cmd, check=True, capture_output=True)
    print(f"WAV oficial gravado: {WAV_FILE.name}")

def detectar_silencios():
    r = subprocess.run(
        ["ffmpeg", "-hide_banner", "-i", str(WAV_FILE), "-af", "silencedetect=noise=-35dB:d=0.20", "-f", "null", "-"],
        capture_output=True, text=True, encoding="utf-8", errors="replace"
    )
    ini = [float(x) for x in re.findall(r"silence_start: ([\d.]+)", r.stderr)]
    dur = [float(x) for x in re.findall(r"silence_duration: ([\d.]+)", r.stderr)]
    return list(zip(ini, dur))

def extrair_whisper():
    model = whisper.load_model("base")
    res = model.transcribe(
        str(WAV_FILE),
        language="pt",
        word_timestamps=True,
        fp16=False,
        condition_on_previous_text=False,
        temperature=0.0
    )

    palavras = []
    for seg in res.get("segments", []):
        for w in seg.get("words", []):
            palavras.append({
                "palavra": w["word"].strip(),
                "ini": round(float(w["start"]), 3),
                "fim": round(float(w["end"]), 3)
            })

    silencios = detectar_silencios()

    dados_saida = {
        "modelo": "whisper base",
        "voz": VOICE,
        "audio_mp3": MP3_FILE.name,
        "audio_wav": WAV_FILE.name,
        "duracao_total": round(palavras[-1]["fim"] if palavras else 0.0, 3),
        "total_palavras": len(palavras),
        "texto_reconhecido": res["text"].strip(),
        "silencios": [{"ini": round(s[0], 3), "duracao": round(s[1], 3)} for s in silencios],
        "palavras": palavras,
        "segmentos": [
            {
                "id": seg["id"],
                "ini": round(float(seg["start"]), 3),
                "fim": round(float(seg["end"]), 3),
                "texto": seg["text"].strip()
            }
            for seg in res.get("segments", [])
        ]
    }

    json_path = AUDIO_DIR / "whisper_palavras_locucao.json"
    with open(json_path, "w", encoding="utf-8") as f:
        json.dump(dados_saida, f, ensure_ascii=False, indent=2)

    print(f"Whisper concluído. Duração: {dados_saida['duracao_total']}s | Palavras: {dados_saida['total_palavras']}")

if __name__ == "__main__":
    asyncio.run(gerar_mp3())
    gerar_wav()
    extrair_whisper()
