const path = require('path');
const fs = require('fs');
const http = require('http');
const { execFileSync } = require('child_process');
const { chromium } = require('C:/Users/WINDOWS/.claude/skills/animate/node_modules/playwright');

const raiz = __dirname;
const webm = path.join(raiz, 'preview-frenetica-sem-audio.webm');
const mp4 = path.join(raiz, 'preview-frenetica-44s.mp4');
const audio = path.join(raiz, 'assets', 'audio_mixado_preview.mp3');

(async () => {
  const tipos = {'.html': 'text/html', '.js': 'text/javascript', '.cjs': 'text/javascript', '.png': 'image/png', '.mp4': 'video/mp4', '.mp3': 'audio/mpeg'};
  const server = http.createServer((req, res) => {
    const relativo = decodeURIComponent(req.url.split('?')[0] === '/' ? '/preview.html' : req.url.split('?')[0]);
    const alvo = path.resolve(raiz, '.' + relativo);
    if (!alvo.startsWith(raiz) || !fs.existsSync(alvo)) { res.writeHead(404); res.end(); return; }
    res.writeHead(200, {'Content-Type': tipos[path.extname(alvo)] || 'application/octet-stream'});
    fs.createReadStream(alvo).pipe(res);
  });
  await new Promise(resolve => server.listen(8765, '127.0.0.1', resolve));
  const browser = await chromium.launch({
    headless: true,
    executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    args: ['--autoplay-policy=no-user-gesture-required']
  });
  const page = await browser.newPage({viewport: {width: 1100, height: 1940}});
  await page.goto('http://127.0.0.1:8765/preview.html');
  await page.waitForFunction(() => window.CenasV2 && document.querySelector('#video-gameplay').readyState >= 2);
  const downloadPromise = page.waitForEvent('download', {timeout: 90000});
  await page.evaluate(async () => {
    const canvas = document.querySelector('#stage');
    const audioEl = document.querySelector('#audio');
    const video = document.querySelector('#video-gameplay');
    audioEl.muted = true;
    audioEl.currentTime = 0;
    video.currentTime = 0;
    const stream = canvas.captureStream(30);
    const recorder = new MediaRecorder(stream, {mimeType: 'video/webm;codecs=vp9', videoBitsPerSecond: 9000000});
    const chunks = [];
    recorder.ondataavailable = e => { if (e.data.size) chunks.push(e.data); };
    const acabou = new Promise(resolve => recorder.onstop = resolve);
    recorder.start(500);
    document.querySelector('#btn-play').click();
    await new Promise(resolve => {
      const poll = () => audioEl.currentTime >= 44.52 || audioEl.ended ? resolve() : setTimeout(poll, 50);
      poll();
    });
    recorder.stop();
    await acabou;
    const blob = new Blob(chunks, {type: 'video/webm'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'preview-frenetica-sem-audio.webm';
    a.click();
  });
  const download = await downloadPromise;
  await download.saveAs(webm);
  await browser.close();
  await new Promise(resolve => server.close(resolve));
  execFileSync('ffmpeg', ['-y', '-i', webm, '-i', audio, '-map', '0:v:0', '-map', '1:a:0', '-t', '44.57', '-c:v', 'libx264', '-preset', 'medium', '-crf', '18', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '192k', '-movflags', '+faststart', mp4], {stdio: 'inherit'});
})().catch(error => { console.error(error); process.exit(1); });
