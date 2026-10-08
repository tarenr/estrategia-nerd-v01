import {mkdirSync,readFileSync,writeFileSync,copyFileSync,constants,existsSync} from 'node:fs';
import {resolve} from 'node:path';
import {createHash} from 'node:crypto';
import {Canvas,loadImage} from 'skia-canvas';
const root=process.cwd(), assets=resolve(root,'resources/reels-review/assets/diablo-adicionais'), preview=resolve(root,'public/uploads/previews/diablo-adicionais-20261008');
mkdirSync(assets,{recursive:true});mkdirSync(preview,{recursive:true});
const a='os-arcanjos-do-ceu-a-verdade-por-tras-da-luz-em-diablo',m='os-males-do-inferno-em-diablo-quem-realmente-controla-o-caos',l='a-lore-completa-de-diablo-entenda-toda-a-historia-do-universo';
const generated='C:/Users/WINDOWS/.codex/generated_images/01a11641-aa6e-7361-9178-c96d40b386e4/';
const entries=[
 ['Auriel',a,a+'-03.webp','exec-131643fd-513d-4863-837b-83844e86bdea.png'],
 ['Itherael',a,a+'-04.webp','exec-dde6dde1-262d-4d7c-b11d-ae50fdba76b7.png'],
 ['Andariel',m,m+'-04.webp'],['Duriel',m,m+'-06.webp'],['Belial',m,m+'-07.webp'],['Azmodan',m,m+'-08.webp'],
 ['Inarius e Lilith',l,'img-012.webp'],['Arcanjos',l,'img-010.webp','reuse'],['Mapa do Inferno',l,'img.webp','map'],
 ['Diablo',l,'img-009.webp','exec-eeef8b37-95c5-4290-884d-4116e27207ae.png']
];
const sha=p=>createHash('sha256').update(readFileSync(p)).digest('hex');
const bg=await loadImage(resolve(root,'resources/reels-review/assets/diablo/mesa-v1.png'));
const result=[];
for(const [i,e] of entries.entries()){
 const [name,slug,filename,treatment]=e,key=String(i+1).padStart(2,'0'), original=resolve(root,'public/uploads/posts',slug,'images',filename);
 const before=`${key}-antes.webp`,after=`${key}-mesa-v1.webp`;
 if(existsSync(resolve(assets,after)))throw new Error('Arquivo existente: '+after);
 const originalHash=sha(original);copyFileSync(original,resolve(preview,before),constants.COPYFILE_EXCL);
 let source=original,method='original-contained';
 if(treatment==='reuse'){source=resolve(root,'resources/reels-review/assets/diablo/art-12-mesa-v1.webp');method='approved-art-reused';}
 else if(treatment && treatment!=='map'){source=generated+treatment;method='native-imagegen';copyFileSync(source,resolve(assets,`${key}-master-v1.png`),constants.COPYFILE_EXCL);}
 const im=await loadImage(source),c=new Canvas(1672,941),ctx=c.getContext('2d');ctx.drawImage(bg,0,0,1672,941);
 const inset=treatment==='map'?40:0,s=Math.min((1672-inset*2)/im.width,(941-inset*2)/im.height);
 ctx.drawImage(im,(1672-im.width*s)/2,(941-im.height*s)/2,im.width*s,im.height*s);
 writeFileSync(resolve(assets,after),await c.toBuffer('webp',{quality:0.97}),{flag:'wx'});
 copyFileSync(resolve(assets,after),resolve(preview,after),constants.COPYFILE_EXCL);
 const final=await loadImage(resolve(assets,after));if(final.width!==1672||final.height!==941||sha(original)!==originalHash)throw new Error('Integridade inválida');
 result.push({key,name,slug,original:original.replaceAll('\\','/'),originalSha256:originalHash,before,after,sha256:sha(resolve(assets,after)),method:treatment==='map'?'original-map-composite':method,dimensions:[1672,941]});
}
writeFileSync(resolve(assets,'manifest.json'),JSON.stringify({status:'awaiting-visual-approval',scope:'preview only; no blog references changed',entries:result},null,2),{flag:'wx'});
const cards=result.map(e=>`<section><h2>${e.name}</h2><p>${e.slug===a?'Os Arcanjos do Céu':e.slug===m?'Os Males do Inferno':'A Lore Completa de Diablo'}</p><div class="pair"><figure><figcaption>Antes</figcaption><a href="${e.before}"><img src="${e.before}" alt="Original: ${e.name}" loading="lazy"></a></figure><figure><figcaption>Proposta — 1672 × 941</figcaption><a href="${e.after}"><img src="${e.after}" alt="Proposta: ${e.name}" loading="lazy"></a></figure></div></section>`).join('\n');
writeFileSync(resolve(preview,'index.html'),`<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Diablo — revisão de imagens</title><style>body{margin:0;background:#181410;color:#eee;font:16px system-ui}main{max-width:1400px;margin:auto;padding:20px}section{margin:30px 0;padding:16px;background:#28221b;border-radius:12px}.pair{display:grid;grid-template-columns:1fr 1fr;gap:16px}figure{margin:0}img{display:block;width:100%;height:auto;margin-top:10px}p,figcaption{color:#d1be9e}a{color:inherit}@media(max-width:700px){.pair{grid-template-columns:1fr}main{padding:12px}}</style><main><h1>Diablo — dez imagens para revisão</h1><p>Compare as versões. Toque em uma imagem para abrir inteira. Estas propostas ainda não foram aplicadas aos artigos.</p>${cards}</main></html>`,{flag:'wx'});
console.log('PASS: dez imagens 1672x941; dez originais preservados; galeria pronta');
