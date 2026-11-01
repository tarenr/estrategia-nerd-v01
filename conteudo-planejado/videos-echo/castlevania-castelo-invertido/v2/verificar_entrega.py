import hashlib
import os
from pathlib import Path
from PIL import Image

V2_DIR = Path("C:/Users/WINDOWS/Projects/estrategia-nerd/conteudo-planejado/videos-echo/castlevania-castelo-invertido/v2")
QUADROS_DIR = V2_DIR / "quadros"
PRANCHAS_DIR = V2_DIR / "pranchas"

def sha256_file(filepath):
    h = hashlib.sha256()
    with open(filepath, "rb") as f:
        while chunk := f.read(65536):
            h.update(chunk)
    return h.hexdigest()

print("=== VERIFICAÇÃO TÉCNICA DOS QUADROS E DA PRANCHA ===")
quadros = sorted(QUADROS_DIR.glob("*.png"))
print(f"Total de quadros gerados: {len(quadros)}")

for q in quadros:
    im = Image.open(q)
    h = sha256_file(q)
    size = q.stat().st_size
    valid_res = (im.size == (1080, 1920))
    print(f"[{q.name}] Resolução: {im.size} (Válido: {valid_res}) | Tamanho: {size} bytes | SHA-256: {h}")

pranchas = sorted(PRANCHAS_DIR.glob("*.png"))
for p in pranchas:
    im = Image.open(p)
    h = sha256_file(p)
    size = p.stat().st_size
    print(f"\n[PRANCHA: {p.name}] Resolução: {im.size} | Tamanho: {size} bytes | SHA-256: {h}")
