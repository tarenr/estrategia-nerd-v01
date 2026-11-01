import os
import sys
import hashlib
import subprocess
from pathlib import Path
from PIL import Image

V2_DIR = Path("C:/Users/WINDOWS/Projects/estrategia-nerd/conteudo-planejado/videos-echo/castlevania-castelo-invertido/v2")
QUADROS_DIR = V2_DIR / "quadros"
QUADROS_DIR.mkdir(parents=True, exist_ok=True)

CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
HTML_URL = "file:///" + str(V2_DIR.resolve() / "quadros.html").replace("\\", "/") + "?quadro=Q01"

out_img = QUADROS_DIR / "Q01.png"

def sha256_file(filepath):
    h = hashlib.sha256()
    with open(filepath, "rb") as f:
        while chunk := f.read(65536):
            h.update(chunk)
    return h.hexdigest()

print("--- Capturando exclusivamente Q01.png (1080x1920) via Chrome Headless ---")
cmd = [
    CHROME,
    "--headless=new",
    "--no-sandbox",
    "--disable-gpu",
    "--allow-file-access-from-files",
    f"--screenshot={out_img}",
    "--window-size=1080,1920",
    "--virtual-time-budget=2500",
    HTML_URL
]

res = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
if not out_img.exists():
    print("ERRO: Q01.png nao foi gerado.")
    sys.exit(1)

im = Image.open(out_img)
print(f"Sucesso: {out_img.name}")
print(f"Dimensoes: {im.size} (esperado 1080x1920)")
print(f"Tamanho em bytes: {out_img.stat().st_size}")
print(f"SHA-256: {sha256_file(out_img)}")
