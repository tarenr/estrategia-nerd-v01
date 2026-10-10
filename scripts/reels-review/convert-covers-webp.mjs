import { readdirSync, statSync, writeFileSync, existsSync } from 'node:fs';
import { resolve, join, dirname } from 'node:path';
import { Canvas, loadImage } from 'skia-canvas';

const postsDir = resolve('public/uploads/posts');

function findPngCovers(dir) {
  const results = [];
  const entries = readdirSync(dir, { withFileTypes: true });
  for (const entry of entries) {
    const full = join(dir, entry.name);
    if (entry.isDirectory()) {
      results.push(...findPngCovers(full));
    } else if (entry.isFile() && entry.name.toLowerCase() === 'capa.png') {
      results.push(full);
    }
  }
  return results;
}

const targets = findPngCovers(postsDir);
console.log(`Encontradas ${targets.length} capas PNG para conversão para WebP.`);

let totalOriginal = 0;
let totalWebp = 0;

for (const pngPath of targets) {
  const dir = dirname(pngPath);
  const webpPath = join(dir, 'capa.webp');
  const originalSize = statSync(pngPath).size;
  totalOriginal += originalSize;

  const img = await loadImage(pngPath);
  const canvas = new Canvas(img.width, img.height);
  const ctx = canvas.getContext('2d');
  ctx.drawImage(img, 0, 0);

  const buffer = await canvas.toBuffer('webp', { quality: 0.82 });
  writeFileSync(webpPath, buffer);

  const newSize = statSync(webpPath).size;
  totalWebp += newSize;

  const savedPct = Math.round((1 - newSize / originalSize) * 100);
  console.log(`[OK] ${dir.replace(postsDir, '')}\\capa.webp: ${Math.round(originalSize / 1024)} KB -> ${Math.round(newSize / 1024)} KB (-${savedPct}%)`);
}

console.log('----------------------------------------------------');
console.log(`TOTAL ORIGINAL: ${Math.round(totalOriginal / 1024)} KB (${(totalOriginal / (1024 * 1024)).toFixed(2)} MB)`);
console.log(`TOTAL WEBP:     ${Math.round(totalWebp / 1024)} KB (${(totalWebp / (1024 * 1024)).toFixed(2)} MB)`);
console.log(`ECONOMIA GERAL: ${Math.round((1 - totalWebp / totalOriginal) * 100)}% de redução.`);
