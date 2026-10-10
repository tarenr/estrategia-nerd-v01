import {readFileSync,writeFileSync,existsSync} from 'node:fs';
import {resolve} from 'node:path';
import {createHash} from 'node:crypto';
import assert from 'node:assert/strict';
import {Canvas} from 'skia-canvas';
import {createScene} from '../reels-konva/scene.mjs';
import {renderVideo,inspectOutput} from '../reels-konva/render.mjs';
const root=process.cwd(),catalogPath=resolve(root,'resources/reels-review/corrections-20261010.json');
const read=p=>JSON.parse(readFileSync(p,'utf8'));
const hash=p=>createHash('sha256').update(readFileSync(p)).digest('hex');
const original=read(resolve(root,'storage/correcao-instagram-20261010/diagnostic-207-before/catalog.json')).items.find(i=>i.id===207);
const current=read(catalogPath).items.find(i=>i.id===207);
const ramFix=process.argv.includes('--ram-fix'),revision=ramFix?5:4;
if(current.revision>=revision&&current.spec.compositionProfile==='diagnostic-207-v1'){
 const video=resolve(root,'public',current.video);inspectOutput(video,20);assert.equal(hash(video),current.sha256);
 console.log('207 v'+current.revision+' já concluído; nenhum arquivo ou catálogo alterado.');process.exit(0);
}
const item=structuredClone(original);
assert.equal(item.spec.scenes.length,5);
item.previousVersions=[...(current.previousVersions??[]),{video:current.video,poster:current.poster,sha256:current.sha256,spec:structuredClone(current.spec)}];
item.revision=revision;item.state='prepared';
item.video='uploads/reels/correcao-instagram-20261010/reel-207-v'+revision+'.mp4';
item.poster='uploads/reels/correcao-instagram-20261010/reel-207-v'+revision+'.jpg';
Object.assign(item.spec,{duration:20,beatStarts:[0,4,8,12,16],reviewPostId:207,compositionProfile:'diagnostic-207-v1',visualStyle:{background:'public/uploads/reels/correcao-instagram-20261010/images/reel-207-v3-background.png'}});
item.spec.scenes.forEach((s,i)=>{s.image='public/uploads/reels/correcao-instagram-20261010/images/reel-207-v3-scene-'+String(i+1).padStart(3,'0')+'.png';s.framing={mode:'cover',motion:'still'};});
item.diagnosticRevision={reference:'reel-207-proposta-01.png',tool:'ImageGen',prompts:'storage/correcao-instagram-20261010/diagnostic-207-imagegen.json',textsPreserved:true,queueChanged:false,publicationApproved:false};
if(ramFix){
 assert.equal(current.revision,4,'Correção da RAM exige a versão 4 existente');
 item.spec.scenes[3].image='public/uploads/reels/correcao-instagram-20261010/images/reel-207-v5-scene-004.png';
 const compare=structuredClone(current.spec);compare.scenes[3].image=item.spec.scenes[3].image;
 assert.deepEqual(item.spec,compare,'Correção deve alterar somente a imagem da quarta cena');
 item.diagnosticRevision.imageCorrection={scene:4,reason:'Remover mãos com posição artificial',prompts:'storage/correcao-instagram-20261010/diagnostic-207-ram-fix.json'};
}
const output=resolve(root,'public',item.video);
if(!existsSync(output))await renderVideo(item.spec,root,output,{timeoutMs:600000,maxRssBytes:1536*1024**2});
const manifest=read(output+'.manifest.json');assert.deepEqual(manifest.spec,item.spec);
const stage=await createScene(item.spec,root);
try{
 const sheet=new Canvas(1350,480),ctx=sheet.getContext('2d');
 item.spec.beatStarts.forEach((t,i)=>{stage.render(t+2);ctx.drawImage(stage.layer.getCanvas()._canvas,i*270,0,270,480);const images=stage.layer.find('Image');assert.equal(images.length,2);assert.equal(images[1].width(),994);assert.equal(images[1].height(),680);});
 writeFileSync(output.replace('.mp4','-contact.jpg'),await sheet.toBuffer('jpg',{quality:.9}));
 stage.render(2);writeFileSync(resolve(root,'public',item.poster),await stage.layer.getCanvas()._canvas.toBuffer('jpg',{quality:.9}));
 assert.ok(stage.typography.every(t=>t.bodySize>=31));
}finally{stage.destroy();}
inspectOutput(output,20);
const bytes=readFileSync(output);let offset=0;const atoms=[];
while(offset+8<=bytes.length){let size=bytes.readUInt32BE(offset);const type=bytes.toString('ascii',offset+4,offset+8);if(size===1)size=Number(bytes.readBigUInt64BE(offset+8));if(size===0)size=bytes.length-offset;assert.ok(size>=8);atoms.push(type);offset+=size;}
assert.ok(atoms.indexOf('moov')>=0&&atoms.indexOf('moov')<atoms.indexOf('mdat'),'Faststart ausente');
for(const key of ['caption','status','scheduled','trackId','articleId'])assert.deepEqual(item[key],original[key]);
for(const key of ['audio','audioStart'])assert.deepEqual(item.spec[key],original.spec[key]);
item.spec.scenes.forEach((s,i)=>{for(const key of ['eyebrow','title','body'])assert.equal(s[key],original.spec.scenes[i][key]);});
item.sha256=hash(output);item.state='ready';
// Re-read after the long render so unrelated concurrent catalog changes survive.
const latestRaw=readFileSync(catalogPath,'utf8'),latest=JSON.parse(latestRaw),index=latest.items.findIndex(i=>i.id===207);
assert.deepEqual(latest.items[index],current,'207 mudou durante a execução: não sobrescrever');
latest.items[index]=item;
assert.equal(readFileSync(catalogPath,'utf8'),latestRaw,'Catálogo concorrente alterado');
writeFileSync(catalogPath,JSON.stringify(latest,null,2)+'\n');
writeFileSync(resolve(root,'storage/correcao-instagram-20261010/diagnostic-207-validation.json'),JSON.stringify({id:207,atoms,duration:20,textsPreserved:true,audioSourcePreserved:true,queueChanged:false,otherItemsPreserved:true},null,2));
console.log('PASS 207: cinco cenas, textos e trilha preservados, moldura preenchida, faststart confirmado; catálogo concorrente preservado.');
