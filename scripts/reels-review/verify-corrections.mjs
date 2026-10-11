import assert from 'node:assert/strict';
import {readFileSync,writeFileSync} from 'node:fs';
import {resolve,dirname} from 'node:path';
import {fileURLToPath} from 'node:url';
import {createHash} from 'node:crypto';
import {createScene,validateSpec} from '../reels-konva/scene.mjs';
import {imageRuns,manualState,framingGeometry} from './scene.mjs';
const root=resolve(dirname(fileURLToPath(import.meta.url)),'../..');
const catalog=JSON.parse(readFileSync(resolve(root,'resources/reels-review/corrections-20261010.json'),'utf8'));
assert.deepEqual(catalog.cancelled,[191,192,193,194]);assert.equal(catalog.items.length,37);
assert.equal(new Set(catalog.items.map(i=>i.id)).size,37);assert.equal(catalog.publicationApproval,false);
const counts=new Set(catalog.items.map(i=>i.spec.scenes.length));assert.ok(counts.has(3)&&counts.has(4)&&counts.has(5));
const results=[];
const isGeneral=i=>i.id===208&&i.spec.compositionProfile==='general-editorial-v1';
const isTerrorGroup=i=>[229,231].includes(i.id)&&i.spec.compositionProfile==='terror-atmosphere-v1'&&i.spec.timingRevision==='terror-group-v1';
const isTerrorRevision=i=>(i.id===230||isTerrorGroup(i))&&i.spec.compositionProfile==='terror-atmosphere-v1';
if(catalog.items.some(isTerrorGroup)){
 const before=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/terror-group-before/catalog.json'),'utf8'));
 for(const item of catalog.items){
  if(isGeneral(item))continue;
  const old=before.items.find(i=>i.id===item.id);
  if(!isTerrorGroup(item)){assert.deepEqual(item,old,'Registro fora do grupo de terror alterado');continue;}
  for(const key of ['status','scheduled','articleId','articleSlug','articleSha256','original','originalSha256','avatarReference'])assert.deepEqual(item[key],old[key]);
  const blackout=item.id===231&&item.spec.musicRevision==='terror-blackout-v1';
  const scream=item.id===231&&(item.spec.musicRevision==='terror-scream-v1'||blackout);
  const impact=item.id===231&&(item.spec.musicRevision==='terror-impact-v1'||scream);
  if(!impact)assert.deepEqual(item.spec.scenes,old.spec.scenes,'Roteiro/artes alterados');
  const license=JSON.parse(readFileSync(resolve(root,item.musicLicense),'utf8'));
  if(!impact)assert.equal(item.caption,old.caption+'\n\nMúsica: '+license.credit);
  else{
   const prior=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/terror-impact-before/catalog.json'),'utf8')),previous=prior.items.find(i=>i.id===231);
   for(const other of catalog.items.filter(i=>i.id!==231&&!isGeneral(i)))assert.deepEqual(other,prior.items.find(i=>i.id===other.id),'Registro fora da montagem 231 alterado');
   assert.deepEqual(item.spec.scenes.map(s=>[s.image,s.framing]),previous.spec.scenes.map(s=>[s.image,s.framing]));
   const preserved=structuredClone(item.spec);for(const key of ['audio','musicRevision','scenes'])preserved[key]=previous.spec[key];
   if(blackout)delete preserved.visualStyle.blackout;
   for(const key of ['headlines','subtitles'])preserved.visualStyle[key]=previous.spec.visualStyle[key];assert.deepEqual(preserved,previous.spec);
   assert.ok(item.caption.endsWith('Música: '+license.credit));assert.ok(item.caption.includes('Demonstração sonora ilustrativa, não áudio original dos jogos.'));
   assert.ok(item.previousVersions.some(v=>v.video===previous.video&&v.sha256===previous.sha256));
   if(scream){
    const baseline=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/terror-scream-before/catalog.json'),'utf8')),v4=baseline.items.find(i=>i.id===231);
    for(const other of catalog.items.filter(i=>i.id!==231&&!isGeneral(i)))assert.deepEqual(other,baseline.items.find(i=>i.id===other.id),'Outro registro alterado na troca pelo grito');
    const visual=structuredClone(item.spec);visual.audio=v4.spec.audio;visual.musicRevision=v4.spec.musicRevision;if(blackout)delete visual.visualStyle.blackout;assert.deepEqual(visual,v4.spec,'Visual/tempos alterados na troca pelo grito');
    const voice=JSON.parse(readFileSync(resolve(root,item.screamLicense),'utf8'));
    assert.equal(voice.author,'WuxiaScrub');assert.equal(voice.license,'CC0 1.0');assert.equal(voice.userAuditionApproved,true);
    assert.equal(createHash('sha256').update(readFileSync(resolve(root,voice.path))).digest('hex'),voice.sha256);
    if(!blackout)assert.equal(createHash('sha256').update(readFileSync(resolve(root,item.spec.audio))).digest('hex'),voice.approvedMixedSha256,'Mix divergiu da previa aprovada');
    assert.ok(item.caption.includes('Efeito de grito: WuxiaScrub'));assert.ok(item.previousVersions.some(v=>v.video===v4.video&&v.sha256===v4.sha256));
   }
  }
  assert.equal(item.trackId,null);
  assert.equal(item.spec.duration,20);assert.deepEqual(item.spec.beatStarts,[0,5,10,15]);assert.equal(item.spec.audioStart,0);
  assert.ok(item.previousVersions.some(v=>v.video===old.video&&v.sha256===old.sha256));
  assert.equal(createHash('sha256').update(readFileSync(resolve(root,license.path))).digest('hex'),license.sha256);
  const runs=imageRuns(item.spec);assert.equal(runs.length,item.id===229?4:3);
  for(const run of runs)assert.ok(Math.abs(run.end-run.start-20/runs.length)<1e-10);
  if(item.id===229){assert.equal(item.spec.audio,license.path);assert.equal(item.avatarReference,true);}
  else{
   const demo=JSON.parse(readFileSync(resolve(root,item.soundDemonstration),'utf8'));
   assert.equal(demo.music,license.path);assert.equal(demo.musicSha256,license.sha256);assert.equal(demo.duration,20);
   assert.equal(createHash('sha256').update(readFileSync(resolve(root,item.spec.audio))).digest('hex'),demo.sha256);
   assert.equal(createHash('sha256').update(readFileSync(resolve(root,demo.generator))).digest('hex'),demo.generatorSha256);
   if(scream){
    assert.equal(demo.screamLicense,item.screamLicense);if(blackout){assert.equal(demo.approvedScreamExcerptMatched,true);assert.equal(demo.addedNoise,false);assert.deepEqual(demo.musicGain.fadeToPause,[12.475,12.5]);}else assert.equal(demo.approvedPreviewMatched,true);assert.equal(demo.impact.type,'recorded scream');
    assert.equal(createHash('sha256').update(readFileSync(resolve(root,demo.screamSource))).digest('hex'),demo.screamSha256);
    if(!blackout)assert.equal(createHash('sha256').update(readFileSync(resolve(root,demo.bed))).digest('hex'),demo.bedSha256);
   }
   const wav=readFileSync(resolve(root,item.spec.audio));assert.equal(wav.toString('ascii',0,4),'RIFF');assert.equal(wav.toString('ascii',8,12),'WAVE');
   let data,channels,rate,bits;for(let p=12;p+8<=wav.length;){const size=wav.readUInt32LE(p+4),kind=wav.toString('ascii',p,p+4);if(kind==='fmt '){assert.equal(wav.readUInt16LE(p+8),1);channels=wav.readUInt16LE(p+10);rate=wav.readUInt32LE(p+12);bits=wav.readUInt16LE(p+22);}if(kind==='data')data=wav.subarray(p+8,p+8+size);p+=8+size+(size%2);}
   assert.equal(channels,2);assert.equal(rate,48000);assert.equal(bits,16);assert.equal(data.length,20*rate*4);
   const peak=(start,end,side)=>{let max=0;for(let f=Math.ceil(start*rate);f<Math.floor(end*rate);f++)max=Math.max(max,Math.abs(data.readInt16LE(f*4+side*2)));return max;};
   if(impact){
    assert.equal(demo.impact.time,runs[2].start);assert.equal(demo.impact.sample,640000);assert.equal(demo.impact.videoFrame,400);
    assert.equal(demo.musicBedEnds,20);assert.deepEqual(demo.silence,[12.5,20/3*2]);
    for(const side of [0,1]){
     assert.equal(peak(12.5,demo.impact.time,side),0,'Pausa curta preenchida');
     const hit=peak(demo.impact.time,scream?demo.impact.end:demo.impact.time+.25,side),build=peak(11.5,12.15,side);
     assert.ok(hit>9000&&(blackout||hit>2*build),'Impacto sem contraste');assert.ok(peak(0,20,side)<(blackout?15000:14000),'Impacto ultrapassou limite de pico');
     for(let t=15;t<19.5;t+=.5)assert.ok(peak(t,t+.5,side)>100,'Musica nao retornou ate o fechamento');
    }
    assert.equal(data.readInt16LE(640000*4),0);assert.notEqual(data.readInt16LE(640001*4),0,'Impacto fora de sincronia');
   }else{
    for(const side of [0,1]){assert.equal(peak(10,15,side),0,'Silencio preenchido');assert.ok(peak(1.5,3.5,side)>600);for(const start of demo.anticipation)assert.ok(peak(start,start+.18,side)>1800);}
    assert.ok(peak(16,16.5,0)>2000);assert.equal(peak(16,16.5,1),0,'Esquerda vazou para direita');
    assert.ok(peak(18.5,19,1)>2000);assert.equal(peak(18.5,19,0),0,'Direita vazou para esquerda');
   }
  }
  await assert.rejects(()=>createScene({...item.spec,reviewPostId:232},root),/fora do escopo/);
  const stage=await createScene(item.spec,root);
  try{
   const photo=stage.layer.find('Image')[1];
   if(blackout){
    const baseline=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/terror-blackout-before/catalog.json'),'utf8'));
    for(const other of catalog.items.filter(i=>i.id!==231&&!isGeneral(i)))assert.deepEqual(other,baseline.items.find(i=>i.id===other.id));
    const overlay=stage.layer.findOne('.terror-blackout');assert.ok(overlay);assert.equal(stage.layer.findOne('.terror-pulse'),undefined);
    let blackFrames=0;for(let frame=0;frame<600;frame++){stage.render(frame/30);assert.equal(overlay.opacity(),frame>=375&&frame<403?1:0);blackFrames+=overlay.opacity();}assert.equal(blackFrames,28);
    stage.render(400/30);const pixels=stage.layer.getCanvas()._canvas.getContext('2d').getImageData(0,0,1080,1920).data;
    for(let n=0;n<pixels.length;n+=4){assert.equal(pixels[n]+pixels[n+1]+pixels[n+2],0);assert.equal(pixels[n+3],255);}
   }
   for(const run of runs){
    stage.render(run.start+.25);const cover=photo.image(),expected=framingGeometry(cover.width,cover.height,run.framing,{width:974,height:498});
    assert.deepEqual(photo.crop(),expected.crop);assert.equal(photo.width(),expected.width);assert.equal(photo.height(),expected.height);
    stage.render(run.end-1/30);assert.equal(photo.image(),cover);assert.deepEqual(photo.crop(),expected.crop);
   }
   if(item.id===231){stage.render(5.2);const first=photo.image();stage.render(6.7);assert.notEqual(photo.image(),first);const second=photo.image();stage.render(10.2);assert.equal(photo.image(),second);stage.render(13.4);const third=photo.image();assert.notEqual(third,second);stage.render(15.2);assert.equal(photo.image(),third);}
  }finally{stage.destroy();}
 }
 console.log('PASS: terror 229/231, imagens igualmente divididas, avatar/piloto preservados; montagem sonora, pausa, contraste e retomada verificados.');
}
if(catalog.items.some(isTerrorRevision)){
 const before=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/terror-230-before/catalog.json'),'utf8'));
 for(const item of catalog.items){
  if(isGeneral(item))continue;
  if(isTerrorGroup(item))continue;
  const old=before.items.find(i=>i.id===item.id);
  if(!isTerrorRevision(item)){assert.deepEqual(item,old,'Registro fora do piloto de terror alterado');continue;}
  for(const key of ['status','scheduled','articleId','articleSha256','original','originalSha256'])assert.deepEqual(item[key],old[key]);
  assert.deepEqual(item.spec.scenes,old.spec.scenes);
  if(item.spec.musicRevision==='terror-house-of-leaves-v1'){
   const prior=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/terror-audio-before/catalog.json'),'utf8'));
   for(const other of catalog.items.filter(i=>i.id!==230&&!isTerrorGroup(i)&&!isGeneral(i)))assert.deepEqual(other,prior.items.find(i=>i.id===other.id));
   const pilot=prior.items.find(i=>i.id===230),license=JSON.parse(readFileSync(resolve(root,item.musicLicense),'utf8'));
   assert.equal(license.title,'The House of Leaves');assert.equal(license.license,'CC BY 4.0');
   assert.equal(createHash('sha256').update(readFileSync(resolve(root,license.path))).digest('hex'),license.sha256);
   assert.equal(item.spec.audio,license.path);assert.notEqual(item.spec.audio,pilot.spec.audio);
   assert.equal(item.spec.audioStart,0);assert.equal(item.trackId,null);
   assert.equal(item.caption,pilot.caption+'\n\nMúsica: '+license.credit);
   const visual={...item.spec};delete visual.musicRevision;visual.audio=pilot.spec.audio;visual.audioStart=pilot.spec.audioStart;
   assert.deepEqual(visual,pilot.spec,'Visual/tempos alterados na troca da trilha');
   assert.ok(item.previousVersions.some(v=>v.video===pilot.video&&v.sha256===pilot.sha256));
  }else{
   assert.equal(item.caption,old.caption);assert.equal(item.trackId,old.trackId);
   for(const key of ['audio','audioStart'])assert.deepEqual(item.spec[key],old.spec[key]);
  }
  assert.equal(item.spec.duration,20);assert.deepEqual(item.spec.beatStarts,[0,20/3,40/3]);
  assert.equal(item.spec.closingLabel,'TRÊS JEITOS DE SENTIR MEDO');
  assert.ok(item.previousVersions.some(v=>v.video===old.video&&v.sha256===old.sha256));
  await assert.rejects(()=>createScene({...item.spec,reviewPostId:231},root),/fora do escopo/);
  const stage=await createScene(item.spec,root);
  try{
   const photo=stage.layer.find('Image')[1];let previous;
   for(const [n,start] of item.spec.beatStarts.entries()){
    if(n){stage.render(start-1/30);assert.equal(photo.image(),previous);}
    assert.equal(stage.render(start+.1).index,n);const cover=photo.image();
    if(previous)assert.notEqual(cover,previous);
    assert.deepEqual(photo.crop(),{x:0,y:0,width:cover.width,height:cover.height});
    assert.ok(Math.abs(photo.width()/photo.height()-cover.width/cover.height)<.00001);
    stage.render(start+6.5);assert.equal(photo.image(),cover);previous=cover;
   }
   stage.render(2.5);const stable=await stage.layer.getCanvas()._canvas.toBuffer('png');
   for(const time of [9,15,19]){
    stage.render(time);await new Promise(r=>setTimeout(r,20));stage.render(2.5);
    assert.ok(stable.equals(await stage.layer.getCanvas()._canvas.toBuffer('png')),'Exportacao de terror variou apos troca de cena');
   }
  }finally{stage.destroy();}
 }
 console.log('PASS: piloto 230, tres imagens originais igualmente divididas em 20 s; visual preservado, trilha/licenca verificadas; registros fora do grupo preservados.');
}
const isDiabloGroup=i=>[197,224,225,226,227].includes(i.id)&&i.spec.timingRevision==='diablo-group-v1';
const isDiabloRevision=i=>(i.id===195||isDiabloGroup(i))&&i.spec.compositionProfile==='diablo-duality-v1';
if(catalog.items.some(isDiabloGroup)){
 const before=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/diablo-group-before/catalog.json'),'utf8'));
 for(const item of catalog.items){
  if(isGeneral(item))continue;
  if(isTerrorRevision(item))continue;
  const old=before.items.find(i=>i.id===item.id);
  if(!isDiabloGroup(item)){assert.deepEqual(item,old,'Registro fora do grupo Diablo alterado');continue;}
  for(const key of ['caption','status','scheduled','articleId','articleSha256','trackId','original','originalSha256'])assert.deepEqual(item[key],old[key]);
  for(const key of ['audio','audioStart','scenes'])assert.deepEqual(item.spec[key],old.spec[key]);
  assert.equal(item.spec.duration,20);assert.deepEqual(item.spec.beatStarts,[0,5,10,15]);assert.equal(item.reuse,false);
  assert.ok(item.previousVersions.some(v=>v.video===old.video&&v.sha256===old.sha256));
  const runs=imageRuns(item.spec);assert.equal(runs.length,item.id===227?1:4);
  for(const run of runs)assert.equal(run.end-run.start,20/runs.length);
  await assert.rejects(()=>createScene({...item.spec,reviewPostId:208},root),/fora do escopo/);
  const stage=await createScene(item.spec,root);
  try{
   let previous;
   for(const [n,start] of item.spec.beatStarts.entries()){
    assert.equal(stage.render(start+.5).index,n);const photo=stage.layer.find('Image')[1],cover=photo.image();
    assert.deepEqual(photo.crop(),{x:0,y:0,width:cover.width,height:cover.height});
    assert.ok(Math.abs(photo.width()/photo.height()-cover.width/cover.height)<.00001);
    if(previous){if(item.id===227)assert.equal(cover,previous);else assert.notEqual(cover,previous);}
    stage.render(start+4.9);assert.equal(photo.image(),cover);previous=cover;
   }
  }finally{stage.destroy();}
 }
 console.log('PASS: cinco Diablo com tempos iguais por imagem, textos/audio preservados e demais 32 registros intactos.');
}
if(catalog.items.some(isDiabloRevision)){
 const before=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/diablo-195-before/catalog.json'),'utf8'));
 for(const item of catalog.items){
  if(isGeneral(item))continue;
  if(isTerrorRevision(item)||isDiabloGroup(item))continue;
  const old=before.items.find(i=>i.id===item.id);
  if(!isDiabloRevision(item)){assert.deepEqual(item,old,'Outro registro alterado no Diablo 195');continue;}
  for(const key of ['caption','status','scheduled','articleId','trackId'])assert.deepEqual(item[key],old[key]);
  for(const key of ['audio','audioStart'])assert.deepEqual(item.spec[key],old.spec[key]);
  const revised=item.spec.typographyRevision==='diablo-195-v3';
  const independent=item.spec.timingRevision==='diablo-195-v4';
  if(revised){
   assert.equal(item.spec.duration,independent?20:15);assert.deepEqual(item.spec.beatStarts,independent?[0,5,10,15]:[0,5,10,12.5]);
   item.spec.scenes.forEach((s,n)=>{for(const key of ['eyebrow','title','body'])assert.equal(s[key],old.spec.scenes[n][key]);if(n<2)assert.equal(s.image,old.spec.scenes[n].image);else assert.equal(s.image,'resources/reels-review/assets/diablo/art-santuario-humanidade-mesa-v1.png');});
   assert.deepEqual(imageRuns(item.spec).map(r=>[r.start,r.end]),independent?[[0,20/3],[20/3,40/3],[40/3,20]]:[[0,5],[5,10],[10,15]]);
   if(independent){
    const v3=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/diablo-195-v4-before/catalog.json'),'utf8')).items.find(i=>i.id===195);
    assert.ok(item.previousVersions.some(v=>v.video===v3.video&&v.sha256===v3.sha256));
    const expected={...v3.spec,duration:20,beatStarts:[0,5,10,15],timingRevision:'diablo-195-v4'};assert.deepEqual(item.spec,expected);
   }
   const v2=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/diablo-195-v3-before/catalog.json'),'utf8')).items.find(i=>i.id===195);
   assert.ok(item.previousVersions.some(v=>v.video===v2.video&&v.sha256===v2.sha256));
  }else{assert.deepEqual(item.spec.scenes,old.spec.scenes);assert.equal(item.spec.duration,20);assert.deepEqual(item.spec.beatStarts,[0,5,10,15]);}
  assert.ok(item.previousVersions.some(v=>v.video===old.video&&v.sha256===old.sha256));
  await assert.rejects(()=>createScene({...item.spec,reviewPostId:197},root),/fora do escopo|Duração editorial fora/);
  const stage=await createScene(item.spec,root);
  try{
   if(independent){
    const photo=stage.layer.find('Image')[1];stage.render(1);const first=photo.image();
    assert.equal(stage.render(5.1).index,1);assert.equal(photo.image(),first);
    stage.render(6.7);const second=photo.image();assert.notEqual(second,first);
    assert.equal(stage.render(10.1).index,2);assert.equal(photo.image(),second);
    stage.render(13.4);const third=photo.image();assert.notEqual(third,second);
    assert.equal(stage.render(15.1).index,3);assert.equal(photo.image(),third);
    stage.render(19.9);assert.equal(photo.image(),third);
   }
   for(const start of item.spec.beatStarts){
   stage.render(start+.1);const photo=stage.layer.find('Image')[1],cover=photo.image();
   assert.ok(Math.abs(photo.width()/photo.height()-cover.width/cover.height)<.00001);
   assert.deepEqual(photo.crop(),{x:0,y:0,width:cover.width,height:cover.height});
   const position=[photo.x(),photo.y(),photo.width(),photo.height()];stage.render(start+4.5);
   assert.deepEqual([photo.x(),photo.y(),photo.width(),photo.height()],position);
  }
  stage.render(revised?12.45:9.95);const photo=stage.layer.find('Image')[1];
  const position=[photo.x(),photo.y(),photo.width(),photo.height()];stage.render(revised?12.55:10.05);
  assert.deepEqual([photo.x(),photo.y(),photo.width(),photo.height()],position,'Arte repetida reiniciou na transicao');
  }finally{stage.destroy();}
 }
 console.log('PASS: Diablo 195; tempos e artes aprovados, roteiros e audio preservados; registros fora do grupo Diablo preservados.');
}
const isListGroup=i=>[215,217,218,219,233].includes(i.id)&&i.spec.compositionProfile==='list-central-v1';
if(catalog.items.some(isListGroup)){
 const before=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/list-group-before/catalog.json'),'utf8'));
 for(const item of catalog.items){
  if(isGeneral(item))continue;
  const old=before.items.find(i=>i.id===item.id);
  if(isTerrorRevision(item)||isDiabloRevision(item))continue;
  if(!isListGroup(item)){assert.deepEqual(item,old,'Outro registro alterado no grupo de listas');continue;}
  for(const key of ['caption','status','scheduled','articleId','trackId'])assert.deepEqual(item[key],old[key]);
  for(const key of ['audio','audioStart','scenes'])assert.deepEqual(item.spec[key],old.spec[key]);
  assert.equal(item.spec.duration,20);assert.deepEqual(item.spec.beatStarts,item.spec.scenes.map((_,n)=>n*20/item.spec.scenes.length));
  assert.ok(item.previousVersions.some(v=>v.video===old.video&&v.sha256===old.sha256));
  await assert.rejects(()=>createScene({...item.spec,reviewPostId:208},root),/fora do escopo/);
  const stage=await createScene(item.spec,root);
  try{for(const start of item.spec.beatStarts){
   stage.render(start+.1);const photo=stage.layer.find('Image')[1],cover=photo.image();
   assert.ok(Math.abs(photo.width()/photo.height()-cover.width/cover.height)<.00001);
   assert.ok(Math.abs(photo.x()+photo.width()/2-540)<.00001&&Math.abs(photo.y()+photo.height()/2-890)<.00001);
   assert.deepEqual(photo.crop(),{x:0,y:0,width:cover.width,height:cover.height});
   const position=[photo.x(),photo.y(),photo.width(),photo.height()];stage.render(start+3.5);
   assert.deepEqual([photo.x(),photo.y(),photo.width(),photo.height()],position);
  }}finally{stage.destroy();}
 }
 const stage=await createScene(catalog.items.find(i=>i.id===205).spec,root);
 try{stage.render(7.5);assert.ok((await stage.layer.getCanvas()._canvas.toBuffer('png')).equals(readFileSync(resolve(root,'storage/correcao-instagram-20261010/list-group-before/205-frame.png'))),'205 aprovado mudou visualmente');}finally{stage.destroy();}
 console.log('PASS: cinco listas em 20 s; fotos proporcionais e originais; roteiros e audio preservados; 205 identico e demais 32 registros intactos.');
}
const isListRevision=i=>i.id===205&&i.spec.compositionProfile==='list-central-v1';
if(catalog.items.some(isListRevision)){
 const before=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/list-205-before/catalog.json'),'utf8'));
 for(const item of catalog.items){
  if(isGeneral(item))continue;
  const old=before.items.find(i=>i.id===item.id);
  if(isTerrorRevision(item)||isDiabloRevision(item)||isListGroup(item))continue;
  if(!isListRevision(item)){assert.deepEqual(item,old,'Outro registro alterado no Reel 205');continue;}
  for(const key of ['caption','status','scheduled','articleId','trackId'])assert.deepEqual(item[key],old[key]);
  for(const key of ['audio','audioStart'])assert.deepEqual(item.spec[key],old.spec[key]);
  if(item.chronoRevision){
   const previous=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/list-205-v3-before/catalog.json'),'utf8'));
   const v2=previous.items.find(i=>i.id===205),preserved=structuredClone(item.spec);
   assert.equal(item.spec.scenes[1].image,'public/uploads/reels/correcao-instagram-20261010/images/reel-205-v3-chrono-trigger.png');
   preserved.scenes[1].image=v2.spec.scenes[1].image;assert.deepEqual(preserved,v2.spec,'Somente a imagem Chrono pode mudar');
   assert.ok(item.previousVersions.some(v=>v.video===v2.video&&v.sha256===v2.sha256));
  }else assert.deepEqual(item.spec.scenes,old.spec.scenes);
  assert.equal(item.spec.duration,20);assert.deepEqual(item.spec.beatStarts,[0,5,10,15]);assert.equal(item.reuse,false);
  assert.ok(item.previousVersions.some(v=>v.video===old.video&&v.sha256===old.sha256));
  await assert.rejects(()=>createScene({...item.spec,reviewPostId:208},root),/fora do escopo/);
  const stage=await createScene(item.spec,root);
  try{for(const start of item.spec.beatStarts){
   stage.render(start+.1);const photo=stage.layer.find('Image')[1],cover=photo.image();
   assert.ok(Math.abs(photo.width()/photo.height()-cover.width/cover.height)<.00001,'Foto distorcida');
   assert.equal(photo.x()+photo.width()/2,540);assert.equal(photo.y()+photo.height()/2,890);
   const crop=photo.crop();assert.deepEqual(crop,{x:0,y:0,width:cover.width,height:cover.height});
   const position=[photo.x(),photo.y(),photo.width(),photo.height()];stage.render(start+4.5);
   assert.deepEqual([photo.x(),photo.y(),photo.width(),photo.height()],position,'Foto deve permanecer fixa na cena');
  }}finally{stage.destroy();}
 }
 console.log('PASS: lista 205 em 20 s, fotos originais centrais sem corte, áudio e textos preservados; demais 36 registros intactos.');
}
const isComparisonGroup=i=>[190,210,211].includes(i.id)&&i.spec.compositionProfile==='comparison-versus-v1';
if(catalog.items.some(isComparisonGroup)){
 const before=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/comparison-group-before/catalog.json'),'utf8'));
 for(const item of catalog.items){
  if(isGeneral(item))continue;
  const old=before.items.find(i=>i.id===item.id);
  if(isTerrorRevision(item)||isDiabloRevision(item)||isListGroup(item)||isListRevision(item))continue;
  if(!isComparisonGroup(item)){assert.deepEqual(item,old,'Outro registro alterado no grupo de comparativos');continue;}
  for(const key of ['caption','status','scheduled','articleId','trackId'])assert.deepEqual(item[key],old[key]);
  for(const key of ['audio','audioStart','comparison'])assert.deepEqual(item.spec[key],old.spec[key]);
  assert.equal(item.spec.duration,20);assert.deepEqual(item.spec.beatStarts,[0,5,10,15]);
  assert.deepEqual(item.spec.scenes,old.spec.scenes,'Roteiro e capas devem ser preservados');
  item.spec.visualStyle.titleLines.forEach((lines,n)=>assert.equal(lines.join(' '),item.spec.scenes[n].title));
  assert.ok(item.previousVersions.some(v=>v.video===old.video&&v.sha256===old.sha256));
  await assert.rejects(()=>createScene({...item.spec,reviewPostId:208},root),/fora do escopo/);
 }
 const approved=catalog.items.find(i=>i.id===206),stage=await createScene(approved.spec,root);
 try{stage.render(2.5);assert.ok((await stage.layer.getCanvas()._canvas.toBuffer('png')).equals(readFileSync(resolve(root,'storage/correcao-instagram-20261010/comparison-group-before/206-frame.png'))),'Quadro aprovado do 206 mudou');}finally{stage.destroy();}
 console.log('PASS: comparativos 190, 210 e 211 com roteiros e capas originais; 206 visualmente idêntico; demais 34 registros preservados.');
}
const isComparisonRevision=i=>i.id===206&&i.spec.compositionProfile==='comparison-206-v1';
if(catalog.items.some(isComparisonRevision)){
 const before=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/comparison-206-before/catalog.json'),'utf8'));
 for(const item of catalog.items){
  if(isGeneral(item))continue;
  if(isTerrorRevision(item)||isDiabloRevision(item)||isListGroup(item)||isListRevision(item)||isComparisonGroup(item))continue;
  const old=before.items.find(i=>i.id===item.id);
  if(!isComparisonRevision(item)){assert.deepEqual(item,old,'Outro registro alterado na revisão 206');continue;}
  for(const key of ['caption','status','scheduled','articleId','trackId'])assert.deepEqual(item[key],old[key]);
  for(const key of ['audio','audioStart'])assert.deepEqual(item.spec[key],old.spec[key]);
  assert.equal(item.spec.duration,20);assert.deepEqual(item.spec.beatStarts,[0,5,10,15]);assert.equal(item.spec.scenes.length,4);
  item.spec.scenes.forEach((s,n)=>{for(const key of ['eyebrow','title','body','image'])assert.equal(s[key],old.spec.scenes[n][key]);});
  assert.equal(new Set(item.spec.scenes.map(s=>s.image)).size,1);
  assert.ok(item.previousVersions.some(v=>v.video===old.video&&v.sha256===old.sha256));
  if(item.spec.typographyRevision==='206-v4'){
   const previous=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/comparison-206-v4-before/catalog.json'),'utf8'));
   const v3=previous.items.find(i=>i.id===206);
   const preservedSpec={...item.spec};delete preservedSpec.typographyRevision;
   assert.deepEqual(preservedSpec,v3.spec,'Alteração visual deve preservar fundo, cenas e áudio');
   assert.ok(item.previousVersions.some(v=>v.video===v3.video&&v.sha256===v3.sha256));
  }
  await assert.rejects(()=>createScene({...item.spec,reviewPostId:210},root),/fora do escopo/);
 }
 console.log('PASS: comparativo 206 com capa original, quatro textos e áudio preservados; demais 36 intactos.');
}
const isGuideRevision=i=>[204,209,212,213].includes(i.id)&&i.spec.compositionProfile==='diagnostic-guide-v1';
if(catalog.items.some(isGuideRevision)){
 const before=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/guides-before/catalog.json'),'utf8'));
 for(const item of catalog.items.filter(isGuideRevision)){
  const old=before.items.find(i=>i.id===item.id);
  for(const key of ['caption','status','scheduled','articleId','trackId'])assert.deepEqual(item[key],old[key]);
  for(const key of ['audio','audioStart'])assert.deepEqual(item.spec[key],old.spec[key]);
  assert.equal(item.spec.scenes.length,old.spec.scenes.length);assert.equal(new Set(item.spec.scenes.map(s=>s.image)).size,1);assert.equal(item.spec.duration,20);
  item.spec.scenes.forEach((s,i)=>{for(const key of ['title','eyebrow','body'])assert.equal(s[key],old.spec.scenes[i][key]);});
 }
 for(const id of [207,214])assert.deepEqual(catalog.items.find(i=>i.id===id),before.items.find(i=>i.id===id));
 console.log('PASS: guias com imagem única; roteiro, trilha, 207 e publicado 214 preservados.');
}
if(catalog.items.some(i=>i.spec.compositionProfile==='diagnostic-207-v1')){
 const before=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/diagnostic-207-before/catalog.json'),'utf8'));
 const item=catalog.items.find(i=>i.id===207),old=before.items.find(i=>i.id===207);
 for(const key of ['caption','status','scheduled','trackId','articleId'])assert.deepEqual(item[key],old[key]);
 for(const key of ['audio','audioStart'])assert.deepEqual(item.spec[key],old.spec[key]);
 assert.equal(item.spec.duration,20);assert.deepEqual(item.spec.beatStarts,[0,4,8,12,16]);
 item.spec.scenes.forEach((s,i)=>{for(const key of ['eyebrow','title','body'])assert.equal(s[key],old.spec.scenes[i][key]);});
 assert.equal(new Set(item.spec.scenes.map(s=>s.image)).size,5);
 console.log('PASS: diagnóstico 207 com cinco imagens e textos/trilha preservados.');
}
const isHardwareRevision=i=>[198,199,200,201,202,203].includes(i.id)&&i.spec.compositionProfile==='hardware-product-v1';
if(catalog.items.some(isHardwareRevision)){
 const before=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/hardware-198-203-before/catalog.json'),'utf8'));
 for(const item of catalog.items){
  if(isGeneral(item))continue;
  if(isTerrorRevision(item)||isDiabloRevision(item)||isListGroup(item)||isListRevision(item)||isComparisonGroup(item)||isComparisonRevision(item)||isGuideRevision(item)||item.id===207&&item.spec.compositionProfile==='diagnostic-207-v1')continue;
  const original=before.items.find(i=>i.id===item.id);
  if(!isHardwareRevision(item)){assert.deepEqual(item,original,'Outro registro alterado #'+item.id);continue;}
  for(const key of ['caption','status','scheduled','trackId','articleId','originalSha256'])assert.deepEqual(item[key],original[key]);
  for(const key of ['audio','audioStart'])assert.deepEqual(item.spec[key],original.spec[key]);
  const revisedSetup=item.id===198&&item.revision>=4;
  assert.equal(item.spec.duration,20);assert.deepEqual(item.spec.beatStarts,revisedSetup?[0,4,8,12,16]:[0,5,10,15]);
  assert.equal(item.spec.scenes.length,revisedSetup?5:4);
  if(revisedSetup){
   assert.deepEqual(item.spec.scenes.map(s=>s.title),['O setup em ação','Ryzen 5 7600X','MSI B650 TOMAHAWK WIFI','RTX 3050 de 6 GB','32 GB de DDR5']);
   assert.equal(new Set(item.spec.scenes.map(s=>s.image)).size,5);
   for(const [n,oldIndex] of [[0,0],[1,1],[2,2],[4,3]])for(const key of ['body','eyebrow'])assert.equal(item.spec.scenes[n][key],original.spec.scenes[oldIndex][key]);
  }else item.spec.scenes.forEach((s,n)=>{for(const key of ['title','body','eyebrow'])assert.equal(s[key],original.spec.scenes[n][key]);});
  if(item.coverRevision){
   assert.deepEqual(item.coverRevision.sources,item.spec.scenes.map(s=>s.image));
   for(const path of item.coverRevision.sources)assert.ok(path.startsWith('public/uploads/posts/')&&path.endsWith('.webp'),'Usar imagens existentes dos artigos');
   if(item.id===201)assert.ok(item.spec.scenes.every(s=>s.image==='public/uploads/posts/'+item.articleSlug+'/images/capa.webp'));
  }
  if(item.id!==200)assert.ok(item.previousVersions.some(v=>v.sha256===original.sha256&&v.video===original.video));
 }
 console.log('PASS: hardware 20 s; 198 com cinco peças/cenas; 201 com capa do artigo; áudio, versões anteriores e demais registros preservados.');
}
if(catalog.avatarRevision){
 const before=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/avatar-before/corrections-20261010.json'),'utf8'));
 const ids=[196,229,232,234];
 for(const item of catalog.items){
  if(isGeneral(item))continue;
  const original=before.items.find(i=>i.id===item.id);
  if(isTerrorRevision(item)||isDiabloRevision(item)||isListGroup(item)||isListRevision(item)||isComparisonGroup(item)||isComparisonRevision(item)||isGuideRevision(item)||isHardwareRevision(item)||item.id===207&&item.spec.compositionProfile==='diagnostic-207-v1')continue;
  if(!ids.includes(item.id)){assert.deepEqual(item,original,'Registro fora do escopo alterado #'+item.id);continue;}
  const approved196=item.id===196&&item.spec.compositionProfile==='196-v5';assert.equal(item.revision,approved196?5:3);assert.equal(item.avatarReference,true);
  for(const key of ['caption','status','scheduled','articleId','articleSlug','original','originalSha256','trackId'])assert.deepEqual(item[key],original[key],'Campo preservado divergiu: '+key);
  for(const key of ['audio','audioStart','duration','beatStarts'])assert.deepEqual(item.spec[key],original.spec[key],'Áudio/tempos alterados #'+item.id);
  assert.equal(item.spec.scenes.length,4);
  for(const [index,s] of item.spec.scenes.entries()){
   for(const key of ['title','body','eyebrow'])assert.equal(s[key],original.spec.scenes[index][key],'Roteiro alterado #'+item.id);
   if(approved196){assert.ok(s.image.endsWith('reel-196-v5-scene-'+String(index+1).padStart(3,'0')+'.png'));assert.equal(s.imageFit,'cover');}else{assert.ok(s.image.endsWith('reel-'+item.id+'-avatar-v3.png'));assert.deepEqual(s.framing.region,[[0,0,.5,.5],[.5,0,.5,.5],[0,.5,.5,.5],[.5,.5,.5,.5]][index]);}
  }
  assert.ok(item.previousVersions.some(v=>v.sha256===original.sha256&&v.video===original.video));
 }
 console.log('PASS: avatar em quatro Reels; roteiros, áudio, versões anteriores e demais 33 preservados.');
}

if(catalog.items.some(isGeneral)){
 const before=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/general-208-before/catalog.json'),'utf8'));
 for(const item of catalog.items){
  const old=before.items.find(i=>i.id===item.id);
  if(!isGeneral(item)){assert.deepEqual(item,old);continue;}
  for(const key of ['caption','status','scheduled','articleId','articleSlug','articleSha256','original','originalSha256','trackId'])assert.deepEqual(item[key],old[key]);
  for(const key of ['scenes','audio','audioStart'])assert.deepEqual(item.spec[key],old.spec[key]);
  assert.equal(item.spec.duration,20);assert.deepEqual(item.spec.beatStarts,[0,5,10,15]);assert.equal(imageRuns(item.spec).length,1);
  assert.ok(item.previousVersions.some(v=>v.sha256===old.sha256&&v.video===old.video));
  const stage=await createScene(item.spec,root);try{stage.render(2.5);const image=stage.layer.find('Image')[1].image();for(const t of [5.2,10.2,15.2,19.9]){stage.render(t);assert.equal(stage.layer.find('Image')[1].image(),image);}assert.ok(stage.typography.every(t=>t.bodySize>=31));}finally{stage.destroy();}
 }
 console.log('PASS: piloto geral 208, capa original continua, textos de 5s, audio e outros 36 preservados.');
}
for(const item of catalog.items){
 const spec=item.spec;validateSpec(spec);assert.equal(spec.beatStarts.length,spec.scenes.length);
 assert.equal(manualState(spec,spec.duration-.1).index,spec.scenes.length-1);
 const stage=await createScene(spec,root);
 try{
  for(const [n,start] of spec.beatStarts.entries())stage.render(start+Math.min(2.5,((spec.beatStarts[n+1]??spec.duration)-start)/2));
  assert.equal(stage.typography.length,spec.scenes.length);
  assert.ok(stage.typography.every(t=>t.bodySize>=31),'Texto pequeno #'+item.id);
  const images=stage.layer.find('Image');
  assert.equal(images.length,['hardware-product-v1','diagnostic-207-v1','diagnostic-guide-v1','comparison-206-v1','comparison-versus-v1','list-central-v1','diablo-duality-v1','terror-atmosphere-v1','general-editorial-v1'].includes(spec.compositionProfile)?2:spec.compositionProfile==='196-v5'?1:imageRuns(spec).length);
  if(imageRuns(spec).length===1){for(const time of spec.beatStarts.slice(1)){
   stage.render(time-.05);const before=images[0].width();stage.render(time+.05);
   assert.equal(images[0].width(),before,'Imagem reiniciou #'+item.id);assert.equal(images[0].getParent().opacity(),1);
  }}
  for(const time of [0,2.5,spec.duration-.5]){
   stage.render(time);
   for(const image of images){const crop=image.crop();assert.ok(crop.x>=0&&crop.y>=0&&crop.x+crop.width<=image.image().width+.001&&crop.y+crop.height<=image.image().height+.001);}
  }
  stage.render(2.5);const frame=await stage.layer.getCanvas()._canvas.toBuffer('png');stage.render(3);stage.render(2.5);const repeatedFrame=await stage.layer.getCanvas()._canvas.toBuffer('png');
  assert.ok(frame.equals(repeatedFrame),'Quadro não determinístico #'+item.id);
  assert.throws(()=>validateSpec({...spec,beatStarts:spec.beatStarts.map((n,i)=>i===1?1:n)}));
  results.push({id:item.id,scenes:spec.scenes.length,presentation:spec.presentation,readable:true,imageContinuity:true,framing:true,deterministic:true});
 }finally{stage.destroy();}
}
writeFileSync(resolve(root,'storage/correcao-instagram-20261010/renderer-validation.json'),JSON.stringify({items:results},null,2));
console.log('PASS: 37 roteiros; cenas variáveis, leitura, enquadramento, continuidade e determinismo.');
