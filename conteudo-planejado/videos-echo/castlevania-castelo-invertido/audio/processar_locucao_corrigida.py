import asyncio
import json
import re
import subprocess
from pathlib import Path

import edge_tts
import whisper


AUDIO_DIR = Path(__file__).resolve().parent
MP3_FILE = AUDIO_DIR / "locucao_corrigida.mp3"
WAV_FILE = AUDIO_DIR / "locucao_corrigida.wav"
JSON_FILE = AUDIO_DIR / "whisper_palavras_locucao_corrigida.json"
VOICE = "pt-BR-AntonioNeural"

TEXTO_OFICIAL = (
    "Em 1997, a indústria priorizava o 3D. Castlevania fez o contrário: apostou no 2D e criou um clássico. "
    "Mas o maior segredo do jogo estava escondido depois do suposto final. "
    "Ao descobrir quem realmente controlava Richter, você liberava uma segunda versão do castelo, "
    "completamente de cabeça para baixo. "
    "As mesmas áreas ganhavam novas rotas, inimigos e chefes. "
    "Lugares que você já conhecia precisavam ser explorados de outra maneira. "
    "Essa segunda jornada ampliava o jogo e reforçava a exploração que ajudou a consolidar o estilo metroidvania. "
    "Você conseguiu chegar ao castelo invertido? Conta aí nos comentários."
)


async def gerar_mp3() -> None:
    comunicacao = edge_tts.Communicate(TEXTO_OFICIAL, VOICE)
    await comunicacao.save(str(MP3_FILE))
    print(f"MP3 gravado: {MP3_FILE.name} ({MP3_FILE.stat().st_size} bytes)")


def gerar_wav() -> None:
    subprocess.run(
        [
            "ffmpeg",
            "-v",
            "error",
            "-y",
            "-i",
            str(MP3_FILE),
            "-ar",
            "16000",
            "-ac",
            "1",
            str(WAV_FILE),
        ],
        check=True,
        timeout=120,
    )
    print(f"WAV gravado: {WAV_FILE.name} ({WAV_FILE.stat().st_size} bytes)")


def detectar_silencios() -> list[dict[str, float]]:
    resultado = subprocess.run(
        [
            "ffmpeg",
            "-hide_banner",
            "-i",
            str(WAV_FILE),
            "-af",
            "silencedetect=noise=-35dB:d=0.20",
            "-f",
            "null",
            "-",
        ],
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace",
        timeout=120,
    )
    inicios = [float(valor) for valor in re.findall(r"silence_start: ([\d.]+)", resultado.stderr)]
    duracoes = [float(valor) for valor in re.findall(r"silence_duration: ([\d.]+)", resultado.stderr)]
    return [
        {"ini": round(inicio, 3), "duracao": round(duracao, 3)}
        for inicio, duracao in zip(inicios, duracoes)
    ]


def transcrever() -> None:
    modelo = whisper.load_model("base")
    resultado = modelo.transcribe(
        str(WAV_FILE),
        language="pt",
        word_timestamps=True,
        fp16=False,
        condition_on_previous_text=False,
        temperature=0.0,
    )

    palavras = []
    for segmento in resultado.get("segments", []):
        for palavra in segmento.get("words", []):
            palavras.append(
                {
                    "palavra": palavra["word"].strip(),
                    "ini": round(float(palavra["start"]), 3),
                    "fim": round(float(palavra["end"]), 3),
                }
            )

    duracao = round(palavras[-1]["fim"] if palavras else 0.0, 3)
    dados = {
        "modelo": "whisper base",
        "voz": VOICE,
        "audio_mp3": MP3_FILE.name,
        "audio_wav": WAV_FILE.name,
        "texto_oficial": TEXTO_OFICIAL,
        "duracao_total": duracao,
        "total_palavras": len(palavras),
        "texto_reconhecido": resultado["text"].strip(),
        "silencios": detectar_silencios(),
        "palavras": palavras,
        "segmentos": [
            {
                "id": segmento["id"],
                "ini": round(float(segmento["start"]), 3),
                "fim": round(float(segmento["end"]), 3),
                "texto": segmento["text"].strip(),
            }
            for segmento in resultado.get("segments", [])
        ],
    }

    with JSON_FILE.open("w", encoding="utf-8") as arquivo:
        json.dump(dados, arquivo, ensure_ascii=False, indent=2)

    print(f"Whisper concluído: {duracao}s | {len(palavras)} palavras")
    print(f"JSON gravado: {JSON_FILE.name}")


def main() -> None:
    asyncio.run(gerar_mp3())
    gerar_wav()
    transcrever()


if __name__ == "__main__":
    main()
