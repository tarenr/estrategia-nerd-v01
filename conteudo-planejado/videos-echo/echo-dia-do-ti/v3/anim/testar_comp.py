import asyncio
import hashlib
import os
from playwright.async_api import async_playwright
from PIL import Image
import numpy as np

COMP_HTML = os.path.abspath(r"C:\Users\WINDOWS\Projects\output\echo-ti-video\v3\anim\comp.html")
CONF_DIR = os.path.abspath(r"C:\Users\WINDOWS\Projects\output\echo-ti-video\v3\anim\conferencia")

async def test_determinism():
    os.makedirs(CONF_DIR, exist_ok=True)
    errors = []

    async with async_playwright() as p:
        browser = await p.chromium.launch()
        context = await browser.new_context(viewport={"width": 1080, "height": 1920})
        page = await context.new_page()

        page.on("pageerror", lambda e: errors.append(f"PAGE ERROR: {e}"))
        page.on("console", lambda m: errors.append(f"CONSOLE {m.type.upper()}: {m.text}") if m.type == "error" else None)

        print(f"Abrindo comp.html: file://{COMP_HTML}")
        await page.goto(f"file://{COMP_HTML}")
        await page.evaluate("window.__ready")
        print("window.__ready concluído com sucesso.")

        # 1. Captura direta em t = 8.5s
        print("Capturando t = 8.5s diretamente...")
        await page.evaluate("window.__seek(8.5)")
        direct_png = await page.screenshot(type="png")
        direct_path = os.path.join(CONF_DIR, "determinismo_direct.png")
        with open(direct_path, "wb") as f:
            f.write(direct_png)

        # 2. Navegar por outros tempos aleatórios
        print("Navegando por t = 0.5s, 3.5s, 6.0s, 12.0s, 15.5s...")
        for t_nav in [0.5, 3.5, 6.0, 12.0, 15.5]:
            await page.evaluate(f"window.__seek({t_nav})")

        # 3. Capturar novamente em t = 8.5s
        print("Retornando e capturando t = 8.5s após navegação...")
        await page.evaluate("window.__seek(8.5)")
        seeked_png = await page.screenshot(type="png")
        seeked_path = os.path.join(CONF_DIR, "determinismo_seeked.png")
        with open(seeked_path, "wb") as f:
            f.write(seeked_png)

        await browser.close()

    # Comparar os hashes
    hash_direct = hashlib.sha256(direct_png).hexdigest()
    hash_seeked = hashlib.sha256(seeked_png).hexdigest()

    print(f"\nHash Direto:  {hash_direct}")
    print(f"Hash Navegado:{hash_seeked}")

    img1 = np.asarray(Image.open(direct_path).convert("RGB"))
    img2 = np.asarray(Image.open(seeked_path).convert("RGB"))
    diff = np.abs(img1.astype(np.int32) - img2.astype(np.int32))
    max_diff = diff.max()
    mean_diff = diff.mean()

    print(f"Diferença máxima de pixel: {max_diff}")
    print(f"Diferença média de pixel:  {mean_diff:.4f}")

    if max_diff == 0:
        print("\nTESTE DE DETERMINISMO: APROVADO COM SUCESSO (100% IDÊNTICO)!")
    else:
        print(f"\nATENÇÃO: Divergência detectada! Max diff = {max_diff}")

    if errors:
        print("\nErros encontrados na página:")
        for err in errors:
            print("  ", err)
    else:
        print("Nenhum erro de console ou de página detectado.")

if __name__ == "__main__":
    asyncio.run(test_determinism())
