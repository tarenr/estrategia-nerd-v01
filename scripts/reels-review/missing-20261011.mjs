import {readFileSync,writeFileSync,mkdirSync,existsSync} from 'node:fs';
import {resolve,dirname} from 'node:path';
import {fileURLToPath} from 'node:url';
import {createHash} from 'node:crypto';
import {spawnSync} from 'node:child_process';
import {Canvas} from 'skia-canvas';
import {createScene} from '../reels-konva/scene.mjs';
import {renderVideo,inspectOutput} from '../reels-konva/render.mjs';
const root=resolve(dirname(fileURLToPath(import.meta.url)),'../..');
const catalogPath=resolve(root,'resources/reels-review/missing-20261011.json');
const catalog=JSON.parse(readFileSync(catalogPath,'utf8'));
const hash=p=>createHash('sha256').update(readFileSync(p)).digest('hex');
const save=()=>writeFileSync(catalogPath,JSON.stringify(catalog,null,2)+'\n');
if(process.argv.includes('--verify')){
  const previous=JSON.parse(readFileSync(resolve(root,'resources/reels-review/corrections-20261010.json'),'utf8'));
  for(const item of previous.items)if(hash(resolve(root,'public',item.video))!==item.sha256)throw new Error('Vídeo anterior alterado #'+item.id);
  console.log('PASS — 37 vídeos anteriores preservados por hash');
}
for(const item of catalog.items){
  const video=resolve(root,'public',item.video),poster=resolve(root,'public',item.poster);
  mkdirSync(dirname(video),{recursive:true});
  if(process.argv.includes('--render')&&item.state!=='ready'){
    if(existsSync(video)||existsSync(poster))throw new Error('Saída existente: preservar antes de renderizar.');
    item.sources=item.spec.scenes.map(s=>({file:s.image,sha256:hash(resolve(root,s.image))}));
    item.audioSha256=hash(resolve(root,item.spec.audio));
    console.log('RENDER #'+item.id);
    await renderVideo(item.spec,root,video,{timeoutMs:600000,maxRssBytes:1536*1024**2});
    const scene=await createScene(item.spec,root);
    try{
      scene.render(2.5);writeFileSync(poster,await scene.layer.getCanvas()._canvas.toBuffer('jpg',{quality:.9}),{flag:'wx'});
      const sheet=new Canvas(1080,480),ctx=sheet.getContext('2d');
      for(let i=0;i<4;i++){scene.render(i*5+2.5);ctx.drawImage(scene.layer.getCanvas()._canvas,i*270,0,270,480);}
      writeFileSync(video.replace('.mp4','-contact.jpg'),await sheet.toBuffer('jpg',{quality:.9}),{flag:'wx'});
    }finally{scene.destroy();}
    item.sha256=hash(video);item.state='ready';save();
  }
  if(process.argv.includes('--verify')){
    if(item.state!=='ready'||hash(video)!==item.sha256)throw new Error('Vídeo não validável.');
    inspectOutput(video,20);
    const decoded=spawnSync('ffmpeg',['-v','error','-i',video,'-f','null','-'],{encoding:'utf8',windowsHide:true});
    if(decoded.status!==0)throw new Error(decoded.stderr);
    const data=readFileSync(video),atoms=[];let offset=0;
    while(offset+8<=data.length){const size=data.readUInt32BE(offset);atoms.push(data.toString('ascii',offset+4,offset+8));if(size===0)break;offset+=size===1?Number(data.readBigUInt64BE(offset+8)):size;}
    if(atoms.indexOf('moov')<0||atoms.indexOf('mdat')<0||atoms.indexOf('moov')>atoms.indexOf('mdat'))throw new Error('Faststart ausente.');
    for(const source of item.sources)if(hash(resolve(root,source.file))!==source.sha256)throw new Error('Imagem original alterada.');
    if(hash(resolve(root,item.spec.audio))!==item.audioSha256)throw new Error('Trilha alterada.');
    if(item.spec.beatStarts.join(',')!=='0,5,10,15')throw new Error('Tempos fora do escopo.');
    console.log('PASS #'+item.id+' — 20s, H264/AAC, decode, faststart, fontes preservadas');
  }
}
if(process.argv.includes('--gallery')){
  if(catalog.items.some(i=>i.state!=='ready'))throw new Error('Renderização pendente.');
  const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const html='<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Novos Reels — Estratégia Nerd</title><style>body{background:#09131f;color:#eee;font:18px system-ui;margin:auto;padding:20px;max-width:850px}video{width:100%;max-height:75vh;background:black}a{color:#85e3ef}article{margin:30px 0}</style><h1>Novos Reels para aprovação visual</h1><p>Rascunhos. Publicação e agendamento não autorizados.</p>'+catalog.items.map(i=>'<article><h2>'+esc(i.title)+'</h2><video controls playsinline preload="metadata" poster="'+esc(i.poster.split('/').pop())+'" src="'+esc(i.video.split('/').pop())+'"></video><p><a href="'+esc(i.video.split('/').pop())+'" download>Baixar vídeo</a></p></article>').join('')+'</html>';
  writeFileSync(resolve(root,'public/uploads/reels/novos-20261011/gallery.html'),html);
}
