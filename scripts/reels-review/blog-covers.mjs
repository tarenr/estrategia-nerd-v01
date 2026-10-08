import {Canvas,loadImage,FontLibrary} from 'skia-canvas';
import {readFileSync,writeFileSync,existsSync,mkdirSync} from 'node:fs';
import {resolve,dirname} from 'node:path';
import {fileURLToPath} from 'node:url';
import {createHash} from 'node:crypto';
const root=resolve(dirname(fileURLToPath(import.meta.url)),'../..');
const dir=resolve(root,'storage/previews/reels-review/blog-covers-20261008');mkdirSync(dir,{recursive:true});
FontLibrary.use('EN Text',resolve(root,'resources/reels-konva/fonts/Inter.ttf'));
const entries=[
  {slug:'msi-mag-b650-tomahawk-wifi-a-base-que-sustenta-um-setup-de-verdade',asset:'msi-b650.png',title:'MSI MAG B650',subtitle:'TOMAHAWK WIFI',kind:'Placa-mãe · Plataforma AM5'},
  {slug:'kingston-nv3-1tb-nvme-pcie-4-0-velocidade-real-para-o-dia-a-dia',asset:'kingston-nv3.jpg',title:'Kingston NV3',subtitle:'SSD NVMe · M.2',kind:'Armazenamento · Linha NV3'},
];
const records=[];
for(const [index,item] of entries.entries()){
  const asset=resolve(root,'resources/reels-review/assets',item.asset),image=await loadImage(asset);
  const c=new Canvas(1200,800),ctx=c.getContext('2d');
  const gradient=ctx.createLinearGradient(0,0,1200,800);gradient.addColorStop(0,'#07121e');gradient.addColorStop(0.7,'#163747');gradient.addColorStop(1,'#07121e');ctx.fillStyle=gradient;ctx.fillRect(0,0,1200,800);
  ctx.strokeStyle='#55d6e733';ctx.lineWidth=2;for(let i=0;i<7;i++){ctx.beginPath();ctx.moveTo(0,240+i*90);ctx.lineTo(1200,80+i*90);ctx.stroke();}
  ctx.fillStyle='#83dfec';ctx.font='bold 20px EN Text';ctx.fillText('ESTRATÉGIA NERD',62,65);
  if(index===0){
    const height=590,width=image.width*height/image.height;ctx.drawImage(image,690-width/2,118,width,height);
    ctx.fillStyle='#f1f7fa';ctx.font='bold 47px EN Text';ctx.fillText(item.title,62,275);ctx.font='bold 32px EN Text';ctx.fillText(item.subtitle,62,330);
    ctx.fillStyle='#b6ced9';ctx.font='22px EN Text';ctx.fillText('Plataforma AM5',62,392);ctx.fillText('Conexões e expansão',62,430);
  }else{
    // Official artwork has large empty margins: use a source region that retains the whole SSD.
    ctx.drawImage(image,image.width*0.32,0,image.width*0.4,image.height*0.74,152,236,896,494);
    ctx.fillStyle='#f1f7fa';ctx.font='bold 54px EN Text';ctx.fillText(item.title,62,146);ctx.fillStyle='#b6ced9';ctx.font='26px EN Text';ctx.fillText(item.subtitle,62,194);
  }
  ctx.fillStyle='#9be6f0';ctx.font='20px EN Text';ctx.fillText(item.kind,62,758);
  const buffer=await c.toBuffer('webp',{quality:0.94});const sha=createHash('sha256').update(buffer).digest('hex');
  const name=`capa-corrigida-${sha.slice(0,12)}.webp`,file=resolve(dir,index===0?'b650.webp':'nv3.webp');
  if(existsSync(file)){if(createHash('sha256').update(readFileSync(file)).digest('hex')!==sha)throw Error('Capa existente divergente');}else writeFileSync(file,buffer,{flag:'wx'});
  records.push({...item,name,file,sha256:sha,sourceSha256:createHash('sha256').update(readFileSync(asset)).digest('hex'),width:1200,height:800});
}
writeFileSync(resolve(dir,'covers.json'),JSON.stringify(records,null,2));console.log('Capas B650/NV3 compostas a partir de imagens oficiais; 1200×800 WebP.');
