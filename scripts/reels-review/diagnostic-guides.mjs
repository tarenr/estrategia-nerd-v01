import {readFileSync,writeFileSync,existsSync} from 'node:fs';
import {resolve} from 'node:path';
import {createHash} from 'node:crypto';
import assert from 'node:assert/strict';
import {Canvas} from 'skia-canvas';
import {createScene} from '../reels-konva/scene.mjs';
import {renderVideo,inspectOutput} from '../reels-konva/render.mjs';
const root=process.cwd(),catalogPath=resolve(root,'resources/reels-review/corrections-20261010.json');
const json=p=>JSON.parse(readFileSync(p,'utf8')),hash=b=>createHash('sha256').update(b).digest('hex');
const before=json(resolve(root,'storage/correcao-instagram-20261010/guides-before/catalog.json'));
const ids=[204,209,212,213],results=[];
const verifyOnly=process.argv.includes('--verify');
for(const id of ids){
 const original=before.items.find(i=>i.id===id);assert.equal(original.status,'agendado');
 const item=structuredClone(original);
 item.previousVersions=[...(original.previousVersions??[]),{video:original.video,poster:original.poster,sha256:original.sha256,spec:structuredClone(original.spec)}];
 item.revision=3;item.state='ready';item.video='uploads/reels/correcao-instagram-20261010/reel-'+id+'-v3.mp4';item.poster=item.video.replace('.mp4','.jpg');
 Object.assign(item.spec,{duration:20,beatStarts:item.spec.scenes.map((_,i)=>i*20/item.spec.scenes.length),reviewPostId:id,compositionProfile:'diagnostic-guide-v1',visualStyle:{background:'public/uploads/reels/correcao-instagram-20261010/images/reel-207-v3-background.png'}});
 item.spec.scenes.forEach(s=>{s.image='public/uploads/reels/correcao-instagram-20261010/images/reel-'+id+'-guide-v3.png';s.framing={mode:'cover',motion:'still'};});
 item.guideRevision={reference:'207 v5',tool:'ImageGen',singleFixedImage:true,textsPreserved:true,audioSourcePreserved:true,queueChanged:false,prompts:'storage/correcao-instagram-20261010/guides-imagegen.json'};
 const output=resolve(root,'public',item.video);
 if(!existsSync(output)){assert.equal(verifyOnly,false,'Vídeo ausente #'+id);console.log('RENDER #'+id);await renderVideo(item.spec,root,output,{timeoutMs:600000,maxRssBytes:1536*1024**2});}
 assert.deepEqual(json(output+'.manifest.json').spec,item.spec,'Manifesto divergente');inspectOutput(output,20);
 const stage=await createScene(item.spec,root);
 try{
  let pixels=null;const sheet=new Canvas(item.spec.scenes.length*270,480),ctx=sheet.getContext('2d');
  for(const [i,t] of item.spec.beatStarts.entries()){
   stage.render(t+2);const photo=stage.layer.find('Image')[1];assert.equal(photo.width(),994);assert.equal(photo.height(),680);
   const canvas=stage.layer.getCanvas()._canvas;
   const data=canvas.getContext('2d').getImageData(46,758,994,680).data;
   const current=hash(Buffer.from(data.buffer,data.byteOffset,data.byteLength));if(pixels)assert.equal(current,pixels,'A imagem mudou com o texto #'+id);pixels=current;
   ctx.drawImage(canvas,i*270,0,270,480);
  }
  for(const t of item.spec.beatStarts.slice(1)){
   stage.render(t-.05);const photo=stage.layer.find('Image')[1],image=photo.image(),crop=photo.crop();
   stage.render(t+.05);assert.equal(photo.image(),image,'Imagem reiniciou');assert.deepEqual(photo.crop(),crop);assert.equal(photo.getParent().opacity(),1);
  }
  assert.ok(stage.typography.every(t=>t.bodySize>=31));
  if(!verifyOnly){writeFileSync(output.replace('.mp4','-contact.jpg'),await sheet.toBuffer('jpg',{quality:.9}));stage.render(2);writeFileSync(resolve(root,'public',item.poster),await stage.layer.getCanvas()._canvas.toBuffer('jpg',{quality:.9}));}
 }finally{stage.destroy();}
 for(const key of ['caption','status','scheduled','trackId','articleId','originalSha256'])assert.deepEqual(item[key],original[key]);
 for(const key of ['audio','audioStart'])assert.deepEqual(item.spec[key],original.spec[key]);
 item.spec.scenes.forEach((s,i)=>{for(const key of ['title','eyebrow','body'])assert.equal(s[key],original.spec.scenes[i][key]);});
 const bytes=readFileSync(output),atoms=[];let offset=0;
 while(offset+8<=bytes.length){let size=bytes.readUInt32BE(offset);const type=bytes.toString('ascii',offset+4,offset+8);if(size===1)size=Number(bytes.readBigUInt64BE(offset+8));if(size===0)size=bytes.length-offset;assert.ok(size>=8);atoms.push(type);offset+=size;}
 assert.ok(atoms.indexOf('moov')>=0&&atoms.indexOf('moov')<atoms.indexOf('mdat'));
 item.sha256=hash(bytes);
 const raw=readFileSync(catalogPath,'utf8'),latest=JSON.parse(raw),index=latest.items.findIndex(i=>i.id===id),current=latest.items[index];
 if(current.spec.compositionProfile==='diagnostic-guide-v1'){assert.deepEqual(current,item,'Prévia concluída divergiu');}
 else{assert.equal(verifyOnly,false);assert.deepEqual(current,original,'Registro mudou durante renderização');latest.items[index]=item;assert.equal(readFileSync(catalogPath,'utf8'),raw);writeFileSync(catalogPath,JSON.stringify(latest,null,2)+'\n');}
 results.push({id,singleFixedImage:true,pixelsUnchanged:true,imageContinuity:true,duration:20,atoms,textsPreserved:true,audioSourcePreserved:true});console.log('PASS #'+id+': imagem fixa, textos/trilha preservados, 20 s e faststart');
}
const latest=json(catalogPath);
for(const id of [196,200,207,214])assert.deepEqual(latest.items.find(i=>i.id===id),before.items.find(i=>i.id===id),'Referência aprovada alterada #'+id);
if(!verifyOnly)writeFileSync(resolve(root,'storage/correcao-instagram-20261010/guides-validation.json'),JSON.stringify({results,approvedReferencesPreserved:true,published214Preserved:true,queueChanged:false},null,2));
console.log('PASS: 196, 200, 207 e publicado 214 preservados.');
