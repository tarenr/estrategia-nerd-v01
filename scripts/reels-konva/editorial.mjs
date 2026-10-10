import { existsSync, readFileSync, mkdirSync, writeFileSync } from 'node:fs';
import {resolve,dirname,extname} from 'node:path';
import {fileURLToPath} from 'node:url';
import {createScene} from './scene.mjs';
import {renderVideo} from './render.mjs';

// Single-output worker called by PHP. Paths/config contain no credentials.
const root=resolve(dirname(fileURLToPath(import.meta.url)),'../..');
const args=process.argv.slice(2);
const option=name=>{const n=args.indexOf(name);if(n<0 || !args[n+1] || args[n+1].startsWith('--'))throw new Error(`Opção obrigatória: ${name}`);return args[n+1];};
try{
  const spec=JSON.parse(readFileSync(resolve(option('--spec')),'utf8'));
  const output=resolve(option('--output'));
  if(extname(output).toLowerCase()!=='.mp4' || existsSync(output))throw new Error('Saída MP4 deve ser nova.');
  const cover=output.replace(/\.mp4$/i,'.jpg');
  if(existsSync(cover))throw new Error('Capa já existe.');
  // Fit and inspect every scene before producing a final video.
  const scene=await createScene(spec,root);
  let thumbnail;
  try{
    for(const start of spec.beatStarts ?? [0,spec.duration/4,spec.duration/2,spec.duration*3/4])scene.render(start+2);
    scene.render(1.6);
    thumbnail=await scene.layer.getCanvas()._canvas.toBuffer('jpg',{quality:0.92});
  }finally{scene.destroy();}
  const result=await renderVideo(spec,root,output,{ffmpeg:option('--ffmpeg'),ffprobe:option('--ffprobe')});
  mkdirSync(dirname(output),{recursive:true});
  writeFileSync(cover,thumbnail,{flag:'wx'});
  console.log(JSON.stringify({video:output,cover,duration:Number(result.media.format.duration),sha256:result.videoSha256}));
}catch(error){console.error(error.message);process.exitCode=1;}
