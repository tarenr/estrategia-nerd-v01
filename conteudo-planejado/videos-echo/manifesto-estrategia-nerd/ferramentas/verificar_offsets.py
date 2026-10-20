"""Garante que renderizar em segmentos (?offset=O) dá os MESMOS quadros que uma página única com ?offset=0.

Para cada O em --offsets e cada t em --ts: hash do canvas com ?offset=O e __seek(t)  ==  hash com ?offset=0 e __seek(O+t).
Uso: verificar_offsets.py --html src/manifesto-60s-v12.html --offsets 0 5 10 ... [--ts 0 0.0167 1.234] [--prazo 140]
"""
import argparse
import asyncio
import hashlib
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
SRC = (RAIZ / "src").resolve()


async def pagina(nav, html, off):
    pg = await nav.new_page(viewport={"width": 1080, "height": 1920}, device_scale_factor=1)
    await pg.goto(html.as_uri() + f"?offset={off}")
    await pg.evaluate("window.__ready")
    return pg, await pg.query_selector("#master-canvas")


async def hash_em(pg, alvo, t):
    await pg.evaluate(f"window.__seek({t})")
    await pg.wait_for_timeout(35)
    return hashlib.sha256(await alvo.screenshot()).hexdigest()


async def main(a):
    from playwright.async_api import async_playwright
    html = Path(a.html)
    html = (html if html.is_absolute() else RAIZ / html).resolve()
    if SRC not in html.parents:
        sys.exit("HTML precisa estar em src/")
    ruins = 0
    async with async_playwright() as p:
        nav = await p.chromium.launch()
        p0, a0 = await pagina(nav, html, 0)
        for off in a.offsets:
            po, ao = await pagina(nav, html, off)
            for t in a.ts:
                h1 = await hash_em(po, ao, t)
                h0 = await hash_em(p0, a0, round(off + t, 4))
                ok = h1 == h0
                ruins += not ok
                print(f"offset={off:>5} t={t:>7}: {'IGUAL' if ok else 'DIFERENTE'}")
            await po.close()
        await nav.close()
    print(f"TOTAL diferentes: {ruins}")
    return 1 if ruins else 0


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--html", required=True)
    ap.add_argument("--offsets", type=float, nargs="+", required=True)
    ap.add_argument("--ts", type=float, nargs="+", default=[0.0, 0.0167, 1.234])
    ap.add_argument("--prazo", type=float, default=140.0)
    a = ap.parse_args()
    sys.exit(asyncio.run(asyncio.wait_for(main(a), a.prazo)))
