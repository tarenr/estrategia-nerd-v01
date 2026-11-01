import asyncio
from pathlib import Path

from playwright.async_api import async_playwright


RAIZ = Path(__file__).resolve().parent
HTML = RAIZ / "quadros.html"
SAIDA = RAIZ / "validacao-monitor"
TEMPOS = (1, 6, 15, 24, 33, 40)


async def main():
    SAIDA.mkdir(exist_ok=True)
    erros = []
    async with async_playwright() as p:
        navegador = await p.chromium.launch()
        pagina = await navegador.new_page(viewport={"width": 540, "height": 960})
        pagina.on("pageerror", lambda erro: erros.append(str(erro)))
        pagina.on("console", lambda msg: erros.append(msg.text) if msg.type == "error" else None)
        print("abrindo composição", flush=True)
        await pagina.goto(HTML.as_uri(), wait_until="domcontentloaded", timeout=15_000)
        print("aguardando composição", flush=True)
        await asyncio.wait_for(pagina.evaluate("window.__ready"), timeout=15)
        for tempo in TEMPOS:
            print(f"quadro {tempo}s", flush=True)
            await asyncio.wait_for(pagina.evaluate("t => window.__seek(t)", tempo), timeout=15)
            await pagina.screenshot(path=str(SAIDA / f"monitor_{tempo:02d}s.png"))
        await navegador.close()
    if erros:
        raise RuntimeError("Erros de página: " + " | ".join(erros[:5]))
    print(f"{len(TEMPOS)} quadros gerados; 0 erros de página")


if __name__ == "__main__":
    asyncio.run(main())
