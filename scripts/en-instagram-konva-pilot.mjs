import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createScene } from './reels-konva/scene.mjs';
import { renderVideo } from './reels-konva/render.mjs';

const root=resolve(dirname(fileURLToPath(import.meta.url)),'..');
const args=process.argv.slice(2);
const get=(key,fallback)=>{const pos=args.indexOf(key);return pos<0?fallback:args[pos+1];};
if(args.includes('--help')){
  console.log('Piloto local isolado: --spec arquivo.json --output pasta-nova [--still] [--ffmpeg caminho] [--ffprobe caminho]. Sem banco, Meta ou agenda.');
  process.exit(0);
}
const stamp=new Date().toISOString().replace(/[:.]/g,'-');
const output=resolve(root,get('--output',`storage/previews/reels-konva/diablo-${stamp}`));
try {
  if(existsSync(output)) throw new Error('Pasta de saída já existe. Escolha uma pasta nova.');
  const spec=JSON.parse(readFileSync(resolve(root,get('--spec','scripts/reels-konva/pilot.json')),'utf8'));
  const scene=await createScene(spec,root);
  try {
    mkdirSync(output,{recursive:true});
    for(let chapter=0;chapter<4;chapter++){
      scene.render(chapter*spec.duration/4+1.6);
      const png=await scene.layer.getCanvas()._canvas.toBuffer('png');
      writeFileSync(resolve(output,`scene-${chapter+1}.png`),png,{flag:'wx'});
    }
  } finally {scene.destroy();}
  if(!args.includes('--still')){
    let last=-1;
    const manifest=await renderVideo(spec,root,resolve(output,'pilot.mp4'),{
      ffmpeg:get('--ffmpeg','ffmpeg'),ffprobe:get('--ffprobe','ffprobe'),
      onFrame:({frame})=>{const second=Math.floor(frame/30);if(second%5===0 && second!==last){last=second;console.log(`Cena renderizada: ${second}s/${spec.duration}s`);}},
    });
    console.log(JSON.stringify({output,elapsedMs:manifest.elapsedMs,peakRssMB:Math.round(manifest.peakRss/1024**2),sha256:manifest.videoSha256},null,2));
  } else console.log(`Prévias: ${output}`);
} catch(error){console.error(error.message);process.exitCode=1;}
