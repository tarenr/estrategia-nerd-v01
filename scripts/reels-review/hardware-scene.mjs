import Konva from 'konva';
import 'konva/skia-backend';
import {FontLibrary,loadImage} from 'skia-canvas';
import {existsSync} from 'node:fs';
import {resolve} from 'node:path';
import {manualState} from './scene.mjs';

// Stable composition for the approved hardware design; photos remain inside it.
export const HARDWARE_FRAME={x:82,y:764,width:916,height:618};
export async function createHardwareScene(spec,root){
  if(spec.reviewPostId!==200||spec.presentation!=='product'||spec.duration!==20||spec.scenes.length!==4||spec.beatStarts.some((t,i)=>t!==i*5))throw new Error('Perfil de hardware fora do escopo aprovado do 200.');
  const font='C:/Windows/Fonts/bahnschrift.ttf';
  if(!existsSync(font))throw new Error('Fonte de hardware indisponível; não substituir a tipografia silenciosamente.');
  FontLibrary.use('EN Hardware',font);
  FontLibrary.use('EN Text',resolve(root,'resources/reels-konva/fonts/Inter.ttf'));
  const background=await loadImage(resolve(root,spec.visualStyle.background));
  const images=await Promise.all(spec.scenes.map(s=>loadImage(resolve(root,s.image))));
  const stage=new Konva.Stage({width:1080,height:1920,listening:false});
  const layer=new Konva.Layer({listening:false});stage.add(layer);
  layer.add(new Konva.Image({image:background,width:1080,height:1920,listening:false}));
  const text=(value,attrs)=>{const node=new Konva.Text({text:value,fontFamily:'EN Text',fill:'#f6f4ee',listening:false,...attrs});layer.add(node);return node;};
  const fit=(node,height,max,min)=>{for(let size=max;size>=min;size-=2){node.fontSize(size);if(node.height()<=height)return size;}throw new Error('Texto de hardware não cabe: '+node.text());};
  text('ESTRATÉGIA',{x:86,y:163,width:265,fontSize:27,fontStyle:'bold',letterSpacing:4});
  text('NERD',{x:365,y:163,width:220,fontSize:27,fontStyle:'bold',letterSpacing:4,fill:'#55d9ee'});
  text('HARDWARE',{x:800,y:176,width:172,fontSize:18,letterSpacing:2,align:'center'});
  const label=text('',{x:90,y:300,width:890,fontFamily:'EN Hardware',fontSize:25,letterSpacing:3,fill:'#55d9ee'});
  const title=text('',{x:86,y:352,width:908,fontFamily:'EN Hardware',fontStyle:'bold',fontSize:96,lineHeight:1.08,letterSpacing:1});
  const body=text('',{x:90,y:610,width:898,fontSize:35,lineHeight:1.35,fill:'#e2e5e5'});
  const frame=HARDWARE_FRAME;
  const picture=new Konva.Group({x:frame.x,y:frame.y,listening:false,clipFunc(ctx){const w=frame.width,h=frame.height,c=28;ctx.beginPath();ctx.moveTo(c,0);ctx.lineTo(w-c,0);ctx.lineTo(w,c);ctx.lineTo(w,h-c);ctx.lineTo(w-c,h);ctx.lineTo(c,h);ctx.lineTo(0,h-c);ctx.lineTo(0,c);ctx.closePath();}});layer.add(picture);
  const photo=new Konva.Image({image:images[0],x:0,y:0,width:frame.width,height:frame.height,listening:false});picture.add(photo);
  text('ANÁLISE DE PRODUTO',{x:90,y:1475,width:870,fontFamily:'EN Hardware',fontStyle:'bold',fontSize:38,letterSpacing:1,fill:'#55d9ee'});
  text('Leia o artigo  →  Link na bio',{x:90,y:1670,width:870,fontSize:34,fontStyle:'bold'});
  const progress=new Konva.Line({points:[90,1540,90,1540],stroke:'#55d9ee',strokeWidth:3,listening:false});layer.add(progress);
  let active=-1;const typography=[];
  function render(time){
    const state=manualState(spec,time);
    if(active!==state.index){
      active=state.index;const scene=spec.scenes[active];const img=images[active];
      label.text(scene.eyebrow.toUpperCase());fit(label,60,25,23);
      title.text(scene.title.toUpperCase());const titleSize=fit(title,235,96,68);
      body.text(scene.body);const bodySize=fit(body,132,35,31);
      // Crop only excess scenery; keep a stationary full-bleed photo in every scene.
      const ratio=frame.width/frame.height;
      const width=Math.min(img.width,img.height*ratio),height=width/ratio;
      photo.image(img);photo.crop({x:(img.width-width)/2,y:(img.height-height)/2,width,height});
      typography.push({scene:active,titleSize,bodySize});
    }
    const opacity=Math.min(1,Math.max(0,state.local)/.25);
    label.opacity(opacity);title.opacity(opacity);body.opacity(opacity);
    progress.points([90,1540,90+890*Math.max(0,Math.min(1,time/spec.duration)),1540]);
    layer.draw();return state;
  }
  return {stage,layer,render,typography,destroy:()=>stage.destroy()};
}
