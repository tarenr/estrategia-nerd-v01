import {readFileSync,writeFileSync} from 'node:fs';
import {resolve,dirname} from 'node:path';
import {fileURLToPath} from 'node:url';
import {spawnSync} from 'node:child_process';
import {createHash} from 'node:crypto';
import {createScene,THEMES,containedImage} from './scene.mjs';
import {inspectOutput} from './render.mjs';
const root=resolve(dirname(fileURLToPath(import.meta.url)),'../..');
const index=process.argv.indexOf('--manifest');
if(index<0)throw new Error('--manifest obrigatório');
const path=resolve(process.argv[index+1]);const manifest=JSON.parse(readFileSync(path,'utf8'));
const hash=path=>createHash('sha256').update(readFileSync(path)).digest('hex');
const report=[];
for(const [w,h] of [[1920,1080],[1080,1920],[1000,1000]]){
  for(const progress of [0,0.5,1]){const box=containedImage(w,h,840,600,progress);if(box.x<0 || box.y<0 || box.x+box.width>840.00001 || box.y+box.height>600.00001)throw new Error('Enquadramento corta a imagem');}
}
for(const item of manifest.items){
  if(item.state!=='ready')throw new Error(`Reel ${item.id} pendente`);
  const video=resolve(root,'public',item.video);
  if(hash(video)!==item.video_sha256)throw new Error('MP4 mudou');
  const output=inspectOutput(video,item.duration);
  if(Number(output.streams.find(s=>s.codec_type==='video').nb_frames)!==item.duration*30)throw new Error('Quadros incompletos');
  const decoded=spawnSync('ffmpeg',['-v','error','-i',video,'-f','null','-'],{encoding:'utf8',timeout:30000,windowsHide:true});
  if(decoded.status!==0 || decoded.stderr.trim())throw new Error(`Falha de decodificação ${item.id}: ${decoded.stderr}`);
  const audio=spawnSync('ffmpeg',['-hide_banner','-i',video,'-vn','-af','volumedetect','-f','null','-'],{encoding:'utf8',timeout:15000,windowsHide:true});
  const peak=audio.stderr.match(/max_volume: (-?[\d.]+) dB/);
  if(audio.status!==0 || !peak || Number(peak[1])<=-35)throw new Error(`Áudio inaudível ${item.id}`);
  const scene=await createScene(item.spec,root);
  try{
    for(let i=0;i<4;i++){scene.render(i*item.duration/4+1.6);if(scene.typography[i].titleSize<70 || scene.typography[i].bodySize<26)throw new Error('Fonte ilegível');}
    scene.render(1.6);const first=await scene.layer.getCanvas()._canvas.toBuffer('png');
    scene.render(2.6);const next=await scene.layer.getCanvas()._canvas.toBuffer('png');
    if(first.equals(next))throw new Error('Animação ausente');
    if(!THEMES[item.spec.category])throw new Error('Categoria sem tema');
  }finally{scene.destroy();}
  report.push({reel:item.id,category:item.spec.category,duration:item.duration,decode:true,audible:true,motion:true,sha256:item.video_sha256});
  console.log(`PASS #${item.id}: MP4 completo, música audível, 4 cenas legíveis e animação`);
}
writeFileSync(resolve(dirname(path),'validation.json'),JSON.stringify({validatedAt:new Date().toISOString(),manifestSha256:hash(path),items:report},null,2));
console.log(`${report.length} Reels validados`);
