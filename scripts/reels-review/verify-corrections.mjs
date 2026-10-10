import assert from 'node:assert/strict';
import {readFileSync,writeFileSync} from 'node:fs';
import {resolve,dirname} from 'node:path';
import {fileURLToPath} from 'node:url';
import {createScene,validateSpec} from '../reels-konva/scene.mjs';
import {imageRuns,manualState} from './scene.mjs';
const root=resolve(dirname(fileURLToPath(import.meta.url)),'../..');
const catalog=JSON.parse(readFileSync(resolve(root,'resources/reels-review/corrections-20261010.json'),'utf8'));
assert.deepEqual(catalog.cancelled,[191,192,193,194]);assert.equal(catalog.items.length,37);
assert.equal(new Set(catalog.items.map(i=>i.id)).size,37);assert.equal(catalog.publicationApproval,false);
const counts=new Set(catalog.items.map(i=>i.spec.scenes.length));assert.ok(counts.has(3)&&counts.has(4)&&counts.has(5));
const results=[];
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
if(catalog.items.some(i=>i.spec.compositionProfile==='hardware-product-v1')){
 const before=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/hardware-200-before/catalog.json'),'utf8'));
 for(const item of catalog.items){if(item.id===207&&item.spec.compositionProfile==='diagnostic-207-v1')continue;const original=before.items.find(i=>i.id===item.id);if(item.id!==200){assert.deepEqual(item,original,'Outro registro alterado #'+item.id);continue;}for(const key of ['caption','status','scheduled','trackId','articleId','originalSha256'])assert.deepEqual(item[key],original[key]);for(const key of ['audio','audioStart'])assert.deepEqual(item.spec[key],original.spec[key]);assert.equal(item.spec.duration,20);assert.deepEqual(item.spec.beatStarts,[0,5,10,15]);assert.equal(new Set(item.spec.scenes.map(s=>s.image)).size,4);item.spec.scenes.forEach((s,n)=>{for(const key of ['title','body','eyebrow'])assert.equal(s[key],original.spec.scenes[n][key]);});assert.ok(item.previousVersions.some(v=>v.sha256===original.sha256&&v.video===original.video));}
 console.log('PASS: 200 com quatro fotos e 20 s; textos, áudio, versão anterior e demais 36 preservados.');
}
if(catalog.avatarRevision){
 const before=JSON.parse(readFileSync(resolve(root,'storage/correcao-instagram-20261010/avatar-before/corrections-20261010.json'),'utf8'));
 const ids=[196,229,232,234];
 for(const item of catalog.items){
  const original=before.items.find(i=>i.id===item.id);
  if(item.id===200&&item.spec.compositionProfile==='hardware-product-v1'||item.id===207&&item.spec.compositionProfile==='diagnostic-207-v1')continue;
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
for(const item of catalog.items){
 const spec=item.spec;validateSpec(spec);assert.equal(spec.beatStarts.length,spec.scenes.length);
 assert.equal(manualState(spec,spec.duration-.1).index,spec.scenes.length-1);
 const stage=await createScene(spec,root);
 try{
  for(const start of spec.beatStarts)stage.render(start+2.5);
  assert.equal(stage.typography.length,spec.scenes.length);
  assert.ok(stage.typography.every(t=>t.bodySize>=31),'Texto pequeno #'+item.id);
  const images=stage.layer.find('Image');
  assert.equal(images.length,['hardware-product-v1','diagnostic-207-v1'].includes(spec.compositionProfile)?2:spec.compositionProfile==='196-v5'?1:imageRuns(spec).length);
  if(imageRuns(spec).length===1){for(const time of spec.beatStarts.slice(1)){
   stage.render(time-.05);const before=images[0].width();stage.render(time+.05);
   assert.equal(images[0].width(),before,'Imagem reiniciou #'+item.id);assert.equal(images[0].getParent().opacity(),1);
  }}
  for(const time of [0,2.5,spec.duration-.5]){
   stage.render(time);
   for(const image of images){const crop=image.crop();assert.ok(crop.x>=0&&crop.y>=0&&crop.x+crop.width<=image.image().width+.001&&crop.y+crop.height<=image.image().height+.001);}
  }
  stage.render(2.5);const frame=await stage.layer.getCanvas()._canvas.toBuffer('png');stage.render(3);stage.render(2.5);assert.ok(frame.equals(await stage.layer.getCanvas()._canvas.toBuffer('png')),'Quadro não determinístico');
  assert.throws(()=>validateSpec({...spec,beatStarts:spec.beatStarts.map((n,i)=>i===1?1:n)}));
  results.push({id:item.id,scenes:spec.scenes.length,presentation:spec.presentation,readable:true,imageContinuity:true,framing:true,deterministic:true});
 }finally{stage.destroy();}
}
writeFileSync(resolve(root,'storage/correcao-instagram-20261010/renderer-validation.json'),JSON.stringify({items:results},null,2));
console.log('PASS: 37 roteiros; cenas variáveis, leitura, enquadramento, continuidade e determinismo.');
