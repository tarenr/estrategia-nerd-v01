import {readFileSync,writeFileSync,mkdirSync,existsSync,copyFileSync,constants} from 'node:fs';
import {resolve,dirname,basename} from 'node:path';
import {fileURLToPath} from 'node:url';
import {createHash} from 'node:crypto';
import {spawnSync} from 'node:child_process';
import {Canvas} from 'skia-canvas';
import {createScene,validateSpec} from '../reels-konva/scene.mjs';
import {renderVideo,inspectMedia,inspectOutput} from '../reels-konva/render.mjs';

const root=resolve(dirname(fileURLToPath(import.meta.url)),'../..');
const hash=p=>createHash('sha256').update(readFileSync(p)).digest('hex');
const json=p=>JSON.parse(readFileSync(p,'utf8'));
const args=process.argv.slice(2);const name=args[args.indexOf('--run')+1];
if(!args.includes('--run') || !/^[a-z0-9-]+$/.test(name))throw new Error('--run com nome seguro obrigatório');
const dir=resolve(root,'public/uploads/previews',name);const evidence=resolve(root,'storage/previews/reels-review',name);
const manifestPath=resolve(evidence,'manifest.json');
const save=manifest=>writeFileSync(manifestPath,JSON.stringify(manifest,null,2));
const safePublic=p=>{const path=resolve(root,'public',p);if(!path.startsWith(resolve(root,'public/uploads')+'/') && !path.startsWith(resolve(root,'public/uploads')+'\\'))throw new Error('Fonte fora de uploads');if(!existsSync(path))throw new Error('Mídia anterior ausente: '+p);return path;};
const cmd=(tool,argv,timeout=30000)=>{const r=spawnSync(tool,argv,{encoding:'utf8',timeout,windowsHide:true});if(r.error || r.status!==0)throw new Error(tool+' falhou: '+r.stderr.slice(-500));return r;};
function imageFor(scene,article){
  if(scene.image.startsWith('@diablo:')){const name=scene.image.slice(8);if(!/^art-\d{2}-mesa-v1\.webp$/.test(name))throw new Error('Arte Diablo inválida');return 'resources/reels-review/assets/diablo/'+name;}
  if(scene.image.startsWith('@asset:')){const name=scene.image.slice(7);if(!/^[a-z0-9-]+\.(png|jpg)$/.test(name))throw new Error('Nome de asset inválido');return 'resources/reels-review/assets/'+name;}
  return scene.image==='cover'?'public/'+article.imagem_capa:'public/'+dirname(article.imagem_capa).replaceAll('\\','/')+'/'+scene.image;
}

if(args.includes('--prepare')){
  if(existsSync(dir) || existsSync(evidence))throw new Error('Use um nome novo; prévias anteriores são preservadas');
  const snapshot=json(resolve(root,'storage/previews/reels-review/source/snapshot.json'));
  const boards=json(resolve(root,'resources/reels-review/storyboards.json'));
  const catalog=json(resolve(root,'config/reel-music.json'));
  if(boards.items.length!==30 || new Set(boards.items.map(i=>i.id)).size!==30)throw new Error('O conjunto deve conter 30 IDs únicos');
  const items=[];
  for(const board of boards.items){
    const post=snapshot.posts.find(p=>Number(p.id)===board.id);if(!post)throw new Error('Post não encontrado');
    const article=snapshot.articles.find(a=>Number(a.id)===Number(post.post_blog_id));if(!article)throw new Error('Artigo não encontrado');
    const previous=safePublic(post.video_rendered_path);const originalSha256=hash(previous);
    const folder='public/'+dirname(article.imagem_capa).replaceAll('\\','/');
    const specs=board.scenes.map(s=>({...s,image:imageFor(s,article)}));
    const track=board.preserve?snapshot.tracks.find(t=>Number(t.id)===Number(post.audio_track_id)):snapshot.tracks.find(t=>t.origem_id===board.musicSourceId && t.origem==='pixabay' && Number(t.ativo)===1);
    if(!track)throw new Error('Faixa local não encontrada para '+board.id);
    const entry=catalog.tracks.find(t=>t.sourceId===track.origem_id && t.source===track.origem);
    const musicTheme={dark:'dark',celestial:'fantasy',fantasy:'fantasy',tech:'technology',calm:'calm',action:'action',light:'light',cinema:'fantasy',anime:'light',nostalgia:'retro'}[board.tone];
    if(!board.preserve && (!entry?.themes.includes(musicTheme) || hash(safePublic(track.arquivo_path))!==entry.sha256))throw new Error('Música não atende ao tema/integridade de '+board.id);
    const duration=board.preserve?Number(inspectMedia(previous).format.duration):board.duration;
    const spec=board.preserve?null:{articleId:Number(article.id),articleTitle:article.titulo.replaceAll('[[','').replaceAll(']]',''),category:article.categoria,editorial:'manual',collection:board.id>=191&&board.id<=195||board.id===197?'DIABLO · HISTÓRIAS DE SANTUÁRIO':board.layout==='chronicle'?'HISTÓRIAS DOS GAMES':board.layout==='versus'?'ESCOLHAS PARA SEU PC':board.layout==='culture'?'CULTURA E JOGOS':board.layout==='dispatch'?'EM FOCO':'DENTRO DO SEU SETUP',layout:board.layout,tone:board.tone,duration,audio:'public/'+track.arquivo_path,audioStart:board.audioStart,beatStarts:[0,7,14,21],scenes:specs,...(board.comparison?{comparison:board.comparison}:{})};
    if(spec)validateSpec(spec);
    const item={id:board.id,articleId:Number(article.id),title:article.titulo.replaceAll('[[','').replaceAll(']]',''),category:article.categoria,status:post.status,scheduled:post.agendado_para,published:post.publicado_em,original:'/'+post.video_rendered_path,originalSha256,note:board.note,warnings:board.warnings??[],sources:board.sources??[],preserved:board.preserve??false,duration,music:track.titulo,musicSourceId:track.origem_id,musicTheme,audioStart:board.preserve?Number(post.audio_start_seconds):board.audioStart,spec,articleSha256:createHash('sha256').update(article.conteudo).digest('hex'),state:'prepared'};
    if(spec){const scene=await createScene(spec,root);try{for(const start of spec.beatStarts)scene.render(start+2.6);}finally{scene.destroy();}}
    items.push(item);
  }
  mkdirSync(dir,{recursive:true});mkdirSync(evidence,{recursive:true});
  const manifest={version:1,reviewedOn:'2026-10-07',snapshotSha256:hash(resolve(root,'storage/previews/reels-review/source/snapshot.json')),storyboardsSha256:hash(resolve(root,'resources/reels-review/storyboards.json')),directory:dir,items};
  save(manifest);
  for(const item of items)if(item.spec)writeFileSync(resolve(evidence,`spec-${item.id}.json`),JSON.stringify(item.spec,null,2),{flag:'wx'});
  console.log('PREPARED: 30 roteiros, fontes, trilhas e encaixe de texto conferidos');
}
// A correction produces another filename. Earlier previews remain recoverable.
if(args.includes('--revise')){
  if(!args.includes('--id'))throw new Error('--id obrigatório para revisão');
  const id=Number(args[args.indexOf('--id')+1]);const manifest=json(manifestPath);
  const item=manifest.items.find(i=>i.id===id);const board=json(resolve(root,'resources/reels-review/storyboards.json')).items.find(i=>i.id===id);
  if(!item?.spec || !board || item.state!=='ready')throw new Error('Somente uma prévia pronta pode ser revisada');
  const snapshot=json(resolve(root,'storage/previews/reels-review/source/snapshot.json'));
  const article=snapshot.articles.find(a=>Number(a.id)===item.articleId);
  const folder='public/'+dirname(article.imagem_capa).replaceAll('\\','/');
  const revision='v'+(Number(item.revision?.slice(1)??1)+1);
  item.previousPreviews=[...(item.previousPreviews??[]),{video:item.video,poster:item.poster,sha256:item.sha256,spec:item.spec}];
  item.title=board.reviewedTitle??item.title;
  item.spec={...item.spec,articleTitle:board.reviewedTitle??item.spec.articleTitle,scenes:board.scenes.map(s=>({...s,image:imageFor(s,article)}))};
  validateSpec(item.spec);const scene=await createScene(item.spec,root);try{for(const start of item.spec.beatStarts)scene.render(start+2.6);}finally{scene.destroy();}
  item.revision=revision;item.note=board.note;item.warnings=board.warnings??[];item.sources=board.sources??[];item.state='prepared';
  writeFileSync(resolve(evidence,`spec-${id}-${revision}.json`),JSON.stringify(item.spec,null,2),{flag:'wx'});
  manifest.storyboardsSha256=hash(resolve(root,'resources/reels-review/storyboards.json'));save(manifest);
  console.log(`REVISED #${id}: ${revision}, prévia anterior preservada`);
}
if(args.includes('--render')){
  const manifest=json(manifestPath);const selected=args.includes('--id')?Number(args[args.indexOf('--id')+1]):null;
  for(const item of manifest.items.filter(i=>selected===null || i.id===selected)){
    const suffix=item.revision?'-'+item.revision:'';
    const output=resolve(dir,`reel-${item.id}${suffix}.mp4`),cover=resolve(dir,`reel-${item.id}${suffix}.jpg`),contact=resolve(dir,`reel-${item.id}${suffix}-contact.jpg`);
    if(item.state==='ready'){if(hash(output)!==item.sha256)throw new Error('Saída pronta mudou');console.log(`SKIP #${item.id}: saída íntegra`);continue;}
    const original=safePublic(item.original.slice(1));if(hash(original)!==item.originalSha256)throw new Error('Original mudou antes da renderização');
    if(item.preserved){copyFileSync(original,output,constants.COPYFILE_EXCL);cmd('ffmpeg',['-v','error','-n','-ss','2.6','-i',output,'-frames:v','1',cover]);}
    else{
      console.log(`RENDER #${item.id}: ${item.title}`);
      await renderVideo(item.spec,root,output);
      const scene=await createScene(item.spec,root);try{
        scene.render(2.6);writeFileSync(cover,await scene.layer.getCanvas()._canvas.toBuffer('jpg',{quality:0.9}),{flag:'wx'});
        const sheet=new Canvas(1080,480);const ctx=sheet.getContext('2d');
        for(let i=0;i<4;i++){scene.render(item.spec.beatStarts[i]+2.6);ctx.drawImage(scene.layer.getCanvas()._canvas,i*270,0,270,480);}
        writeFileSync(contact,await sheet.toBuffer('jpg',{quality:0.93}),{flag:'wx'});
      }finally{scene.destroy();}
    }
    item.video=basename(output);item.poster=basename(cover);item.contact=item.preserved?null:basename(contact);item.sha256=hash(output);item.state='ready';save(manifest);
    console.log(`READY #${item.id}: ${item.preserved?'referência preservada':'nova versão'} (${item.duration}s)`);
  }
}
if(args.includes('--verify')){
  const manifest=json(manifestPath);const report=[];
  for(const item of manifest.items){
    if(item.state!=='ready')throw new Error('Vídeo pendente #'+item.id);
    const path=resolve(dir,item.video);if(hash(path)!==item.sha256 || hash(safePublic(item.original.slice(1)))!==item.originalSha256)throw new Error('Hash divergente #'+item.id);
    const media=inspectOutput(path,item.duration);
    if(Number(media.streams.find(s=>s.codec_type==='video').nb_frames)!==Math.round(item.duration*30))throw new Error('Contagem de quadros divergente #'+item.id);
    cmd('ffmpeg',['-v','error','-i',path,'-f','null','-']);
    const level=cmd('ffmpeg',['-hide_banner','-i',path,'-vn','-af','volumedetect','-f','null','-']).stderr.match(/max_volume: (-?[\d.]+) dB/);
    if(!level || Number(level[1])<=-35)throw new Error('Música inaudível #'+item.id);
    if(!existsSync(resolve(dir,item.poster)))throw new Error('Capa ausente');
    report.push({id:item.id,decode:true,audible:true,peakDb:Number(level[1]),dimensions:[1080,1920],originalPreserved:true,sha256:item.sha256,preserved:item.preserved,duration:Number(media.format.duration)});
    console.log(`PASS #${item.id}: vídeo completo, áudio audível e original preservado`);
  }
  writeFileSync(resolve(evidence,'validation.json'),JSON.stringify({manifestSha256:hash(manifestPath),items:report},null,2));
}
if(args.includes('--gallery')){
  const manifest=json(manifestPath);if(manifest.items.some(i=>i.state!=='ready'))throw new Error('Conclua o lote antes de montar a galeria');
  const publicItems=manifest.items.map(({id,title,category,status,scheduled,published,original,note,warnings,sources,preserved,duration,music,musicTheme,audioStart,video,poster})=>({id,title,category,status,scheduled,published,original,note,warnings,sources,preserved,duration,music,musicTheme,audioStart,video,poster}));
  const html=readFileSync(resolve(root,'resources/reels-review/gallery.html'),'utf8').replace('/* CATALOG */',JSON.stringify(publicItems).replaceAll('<','\\u003c'));
  writeFileSync(resolve(dir,'index.html'),html);console.log('GALLERY: '+dir+'/index.html');
}
