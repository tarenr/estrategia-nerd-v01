import subprocess
import os
from PIL import Image

vid = r"E:/Taren/Documents/1 - Projeto Renda Extra/Postagens/Estratégia Nerd/Curiosidades e dicas/Castlevania - SOTN/Castlevania - SOTN.mp4"
assets_dir = r"C:/Users/WINDOWS/Projects/estrategia-nerd/conteudo-planejado/videos-echo/castlevania-castelo-invertido/v2/assets"

timecodes = {
    "orig_title_15s": 15.0,
    "orig_richter_19s": 19.5,
    "orig_map_33s": 33.0,
    "orig_inverted_42s": 42.0,
    "orig_inverted_47s": 47.0,
    "orig_boss_52s": 52.0,
    "orig_gameplay_56s": 56.0
}

for name, ts in timecodes.items():
    raw_path = os.path.join(assets_dir, f"{name}_raw.png")
    cropped_path = os.path.join(assets_dir, f"{name}_cropped.png")
    cmd = [
        "ffmpeg", "-y", "-ss", str(ts),
        "-i", vid,
        "-vframes", "1",
        "-q:v", "1",
        raw_path
    ]
    subprocess.run(cmd, capture_output=True)
    if os.path.exists(raw_path):
        im = Image.open(raw_path)
        # Crop to the 4:3 gameplay area (y=135 to y=945)
        crop = im.crop((0, 135, 1080, 945))
        crop.save(cropped_path)
        print(f"Extracted {name}: raw={im.size}, cropped={crop.size} ({os.path.getsize(cropped_path)} bytes)")
