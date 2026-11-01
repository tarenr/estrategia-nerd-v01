import asyncio
import subprocess
from pathlib import Path

from playwright.async_api import async_playwright


RAIZ = Path(__file__).resolve().parent
WEBM = RAIZ / "preview-frenetica-sem-audio.webm"
MP4 = RAIZ / "preview-frenetica-44s.mp4"
AUDIO = RAIZ / "assets" / "audio_mixado_preview.mp3"


async def gravar_canvas():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True, args=["--autoplay-policy=no-user-gesture-required"])
        page = await browser.new_page(viewport={"width": 1100, "height": 1940})
        await page.goto((RAIZ / "preview.html").as_uri(), wait_until="load")
        await page.wait_for_function("window.CenasV2 && document.querySelector('#video-gameplay').readyState >= 2")
        async with page.expect_download(timeout=90000) as info:
            await page.evaluate("""async () => {
              const canvas = document.querySelector('#stage');
              const audio = document.querySelector('#audio');
              const video = document.querySelector('#video-gameplay');
              audio.muted = true;
              audio.currentTime = 0;
              video.currentTime = 0;
              const stream = canvas.captureStream(30);
              const recorder = new MediaRecorder(stream, {mimeType: 'video/webm;codecs=vp9', videoBitsPerSecond: 9000000});
              const chunks = [];
              recorder.ondataavailable = e => { if (e.data.size) chunks.push(e.data); };
              const acabou = new Promise(resolve => recorder.onstop = resolve);
              recorder.start(500);
              document.querySelector('#btn-play').click();
              await new Promise(resolve => {
                const poll = () => audio.currentTime >= 44.52 || audio.ended ? resolve() : setTimeout(poll, 50);
                poll();
              });
              recorder.stop();
              await acabou;
              const blob = new Blob(chunks, {type: 'video/webm'});
              const a = document.createElement('a');
              a.href = URL.createObjectURL(blob);
              a.download = 'preview-frenetica-sem-audio.webm';
              a.click();
            }""")
        download = await info.value
        await download.save_as(WEBM)
        await browser.close()


if __name__ == "__main__":
    asyncio.run(gravar_canvas())
    subprocess.run([
        "ffmpeg", "-y", "-i", str(WEBM), "-i", str(AUDIO),
        "-map", "0:v:0", "-map", "1:a:0", "-t", "44.57",
        "-c:v", "libx264", "-preset", "medium", "-crf", "18",
        "-pix_fmt", "yuv420p", "-c:a", "aac", "-b:a", "192k",
        "-movflags", "+faststart", str(MP4)
    ], check=True)
