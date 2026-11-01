import asyncio
from pathlib import Path
import edge_tts

OUT_DIR = Path("C:/Users/WINDOWS/Projects/estrategia-nerd/conteudo-planejado/videos-echo/castlevania-castelo-invertido/audio")
OUT_DIR.mkdir(parents=True, exist_ok=True)

FRASE = "Em 1997, a indústria dizia que o 2D tinha morrido para o 3D. Aí a Konami fez isso aqui."

async def gerar():
    configs = [
        ("amostra_antonio_padrao.mp3", "pt-BR-AntonioNeural", "+0%", "+0Hz"),
        ("amostra_antonio_sobrio.mp3", "pt-BR-AntonioNeural", "-4%", "-2Hz"),
        ("amostra_antonio_dinamico.mp3", "pt-BR-AntonioNeural", "+4%", "+0Hz"),
    ]
    for filename, voice, rate, pitch in configs:
        target = OUT_DIR / filename
        communicate = edge_tts.Communicate(FRASE, voice, rate=rate, pitch=pitch)
        await communicate.save(str(target))
        print(f"Gerado: {target.name} ({target.stat().st_size} bytes)")

if __name__ == "__main__":
    asyncio.run(gerar())
