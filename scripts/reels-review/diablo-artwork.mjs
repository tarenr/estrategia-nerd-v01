import {readFileSync,writeFileSync,existsSync,copyFileSync,constants} from 'node:fs';
import {resolve,dirname} from 'node:path';
import {fileURLToPath} from 'node:url';
import {createHash} from 'node:crypto';
import {Canvas,loadImage} from 'skia-canvas';

const root=resolve(dirname(fileURLToPath(import.meta.url)),'../..');
const assets=resolve(root,'resources/reels-review/assets/diablo');
const preview=resolve(root,'public/uploads/previews/diablo-artes-20261008');
const review=JSON.parse(readFileSync(resolve(root,'storage/previews/diablo-artes-20261008/review.json'),'utf8'));
const sha=p=>createHash('sha256').update(readFileSync(p)).digest('hex');
const background=await loadImage(resolve(assets,'mesa-v1.png'));
const entries=[];
for(const original of review.originals.filter(e=>e.key!=='art-17')){
  if(sha(original.path)!==original.sha)throw new Error('Original mudou: '+original.key);
  const canvas=new Canvas(1672,941),ctx=canvas.getContext('2d');
  ctx.drawImage(background,0,0,1672,941);
  let source;
  if(original.key==='art-04'){
    source=original.path;
    const map=await loadImage(source),height=861,width=height*map.width/map.height;
    ctx.save();ctx.shadowColor='#170b08';ctx.shadowBlur=22;ctx.shadowOffsetY=8;
    ctx.drawImage(map,(1672-width)/2,40,width,height);ctx.restore();
    const originalCopy=resolve(assets,'mapa-original.webp');
    if(!existsSync(originalCopy))copyFileSync(source,originalCopy,constants.COPYFILE_EXCL);
  }else if(review.retained.includes(original.key)){
    source=original.path;const image=await loadImage(source),scale=Math.min(1672/image.width,941/image.height);
    ctx.drawImage(image,(1672-image.width*scale)/2,(941-image.height*scale)/2,image.width*scale,image.height*scale);
  }else{
    source=resolve(preview,review.outputs.find(e=>e.key===original.key).file);
    const image=await loadImage(source);if(image.width!==1672||image.height!==941)throw new Error('Tamanho incorreto');
    ctx.drawImage(image,0,0);
  }
  const file=original.key+'-mesa-v1.webp',out=resolve(assets,file);
  if(existsSync(out))throw new Error('Asset existente: '+file);
  writeFileSync(out,await canvas.toBuffer('webp',{quality:0.97}),{flag:'wx'});
  if(original.key==='art-04'){
    writeFileSync(resolve(preview,'art-04-mapa-corrigido.png'),await canvas.toBuffer('png'),{flag:'wx'});
  }
  entries.push({key:original.key,file,sha256:sha(out),originalSha256:original.sha,method:original.key==='art-04'?'original-map-composite':review.retained.includes(original.key)?'original-contained':'approved-image',uses:original.uses.map(e=>({...e,path:e.path.replace(/^public\//,'')}))});
}
const nef=entries.find(e=>e.key==='art-13');nef.uses.push(...review.originals.find(e=>e.key==='art-17').uses.map(e=>({...e,path:e.path.replace(/^public\//,'')})));
const result={version:1,dimensions:[1672,941],instagramIds:[191,192,193,194,195,197],scope:'local blog and preview videos only',entries};
writeFileSync(resolve(assets,'manifest.json'),JSON.stringify(result,null,2),{flag:'wx'});
console.log('READY: 16 artes 1672x941; mapa original composto sem reescrever letras; originais preservados');
