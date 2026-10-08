import Konva from 'konva';
import 'konva/skia-backend';
import {FontLibrary,loadImage} from 'skia-canvas';
import {existsSync} from 'node:fs';
import {resolve} from 'node:path';

export const PALETTES={
  dark:{bg:'#100b09',accent:'#cf7856',muted:'#d2bda9',font:'EN Chronicle'},
  celestial:{bg:'#111521',accent:'#e4c586',muted:'#c6cad9',font:'EN Chronicle'},
  fantasy:{bg:'#0d1719',accent:'#d1bd87',muted:'#c2d5cc',font:'EN Chronicle'},
  tech:{bg:'#091420',accent:'#62dfe9',muted:'#b7c9da',font:'EN Display'},
  calm:{bg:'#0c1a1a',accent:'#9ae0b4',muted:'#c2d6ce',font:'EN Display'},
  action:{bg:'#161719',accent:'#e9af57',muted:'#d4d0c9',font:'EN Display'},
  light:{bg:'#131321',accent:'#e8b89b',muted:'#d4ccde',font:'EN Display'},
  cinema:{bg:'#13101b',accent:'#e0b36d',muted:'#d8cddd',font:'EN Chronicle'},
  anime:{bg:'#181124',accent:'#e7a5df',muted:'#dacbe6',font:'EN Display'},
  nostalgia:{bg:'#151523',accent:'#afc8ff',muted:'#d5d4e6',font:'EN Display'},
};
const clamp=n=>Math.max(0,Math.min(1,n));
const ease=n=>1-(1-clamp(n))**3;
const txt=(parent,value,attrs)=>{const n=new Konva.Text({text:value,fontFamily:'EN Text',fill:'#f8f2e8',listening:false,...attrs});parent.add(n);return n;};
const rule=(parent,points,color,opacity=1,width=2)=>{const n=new Konva.Line({points,stroke:color,strokeWidth:width,opacity,listening:false});parent.add(n);return n;};
const fit=(node,maxHeight,maxSize,minSize)=>{for(let size=maxSize;size>=minSize;size-=2){node.fontSize(size);if(node.height()<=maxHeight)return size;}throw new Error('Texto manual não cabe com leitura confortável: '+node.text());};
export const IMAGE_FRAME={x:86,y:365,width:908,height:511};
export function framingGeometry(width,height,framing={}){
  const region=framing.region??[0,0,1,1];
  const [rx,ry,rw,rh]=region;
  if(region.length!==4||region.some(n=>!Number.isFinite(n))||rx<0||ry<0||rw<=0||rh<=0||rx+rw>1.000001||ry+rh>1.000001)throw new Error('Região de imagem inválida');
  const crop={x:rx*width,y:ry*height,width:rw*width,height:rh*height};
  const w=IMAGE_FRAME.width,h=IMAGE_FRAME.height;
  if(framing.mode==='product'){
    const scale=Math.min(w*0.86/crop.width,h*0.9/crop.height);
    return {crop,x:(w-crop.width*scale)/2,y:(h-crop.height*scale)/2,width:crop.width*scale,height:crop.height*scale};
  }
  if(framing.mode && framing.mode!=='cover')throw new Error('Modo de enquadramento inválido');
  const focus=framing.focus??[0.5,0.5];
  if(focus.length!==2||focus.some(n=>!Number.isFinite(n)||n<0||n>1))throw new Error('Foco inválido');
  const ratio=w/h;
  if(crop.width/crop.height>ratio){const wanted=crop.height*ratio;crop.x+=(crop.width-wanted)*focus[0];crop.width=wanted;}
  else{const wanted=crop.width/ratio;crop.y+=(crop.height-wanted)*focus[1];crop.height=wanted;}
  return {crop,x:0,y:0,width:w,height:h};
}

export function validateManualSpec(spec){
  if(spec.editorial!=='manual' || !PALETTES[spec.tone] || !['chronicle','guide','versus','showcase','culture','dispatch'].includes(spec.layout))throw new Error('Tratamento editorial inválido');
  if(!Array.isArray(spec.beatStarts) || spec.beatStarts.length!==4 || spec.beatStarts[0]!==0 || spec.beatStarts.some((n,i)=>!Number.isFinite(n) || n<0 || n>=spec.duration || (i>0 && n-spec.beatStarts[i-1]<4)))throw new Error('Tempos editoriais inválidos');
  if(spec.duration-spec.beatStarts[3]<4)throw new Error('Fecho curto demais');
  if(spec.layout==='chronicle' && spec.scenes.some(s=>/^\d+[\s/.-]/.test(s.eyebrow)))throw new Error('Histórias não recebem capítulos numerados');
}

// Adjacent identical images are one continuous shot, even as text changes.
export function imageRuns(spec){
  const runs=[];
  spec.scenes.forEach((s,i)=>{const last=runs.at(-1);if(last?.image===s.image && JSON.stringify(last.framing)===JSON.stringify(s.framing)){last.end=i===3?spec.duration:spec.beatStarts[i+1];return;}runs.push({image:s.image,framing:s.framing,start:spec.beatStarts[i],end:i===3?spec.duration:spec.beatStarts[i+1]});});
  return runs;
}
export function manualState(spec,time){
  const t=Math.max(0,Math.min(spec.duration-1/30,time));
  let index=0;for(let i=1;i<4;i++)if(t>=spec.beatStarts[i])index=i;
  const local=t-spec.beatStarts[index];const end=index===3?spec.duration:spec.beatStarts[index+1];
  const delay=index===0?1.2:0.12;
  return {index,local,titleOpacity:ease((local-delay)/0.55)*clamp((end-t)/0.3),bodyOpacity:ease((local-delay-0.48)/0.5)*clamp((end-t)/0.3)};
}

export async function createManualScene(spec,root){
  validateManualSpec(spec);
  FontLibrary.use('EN Display',resolve(root,'resources/reels-konva/fonts/BebasNeue-Regular.ttf'));
  FontLibrary.use('EN Text',resolve(root,'resources/reels-konva/fonts/Inter.ttf'));
  const serif='C:/Windows/Fonts/georgia.ttf';
  FontLibrary.use('EN Chronicle',existsSync(serif)?serif:resolve(root,'resources/reels-konva/fonts/Inter.ttf'));
  const palette=PALETTES[spec.tone];
  const stage=new Konva.Stage({width:1080,height:1920,listening:false});
  const layer=new Konva.Layer({listening:false});stage.add(layer);
  const bg=new Konva.Group({listening:false});layer.add(bg);
  bg.add(new Konva.Rect({width:1080,height:1920,fill:palette.bg}));
  bg.add(new Konva.Rect({width:1080,height:1920,fillLinearGradientStartPoint:{x:0,y:0},fillLinearGradientEndPoint:{x:1080,y:1920},fillLinearGradientColorStops:[0,palette.bg,0.58,'#141826',1,palette.bg],opacity:0.7}));
  if(spec.layout==='chronicle'){
    for(let i=0;i<12;i++)rule(bg,[60,275+i*110,1000,260+i*110],palette.accent,0.035,1);
    rule(bg,[74,245,74,1495],palette.accent,0.24);rule(bg,[990,245,990,1495],palette.accent,0.24);
  }else if(spec.layout==='versus'){
    rule(bg,[540,290,540,1000],palette.accent,0.15,2);
  }else if(spec.layout==='guide'){
    for(let i=0;i<6;i++)rule(bg,[80,330+i*95,1000,330+i*95],palette.accent,0.06,1);
  }
  bg.cache({x:0,y:0,width:1080,height:1920,pixelRatio:1});
  txt(layer,'ESTRATÉGIA NERD',{x:86,y:158,width:850,fontSize:25,letterSpacing:5,fontStyle:'bold',fill:palette.muted});
  txt(layer,spec.collection,{x:86,y:211,width:850,fontSize:18,letterSpacing:2,fill:palette.accent});
  rule(layer,[86,264,994,264],palette.accent,0.4);

  const {x,y,width:w,height:h}=IMAGE_FRAME;const runs=imageRuns(spec);const visual=[];
  for(const run of runs){
    const path=resolve(root,run.image);if(!existsSync(path))throw new Error('Imagem selecionada ausente: '+run.image);
    const img=await loadImage(path);const group=new Konva.Group({x,y,clipX:0,clipY:0,clipWidth:w,clipHeight:h,listening:false});layer.add(group);
    group.add(new Konva.Rect({width:w,height:h,fillLinearGradientStartPoint:{x:0,y:0},fillLinearGradientEndPoint:{x:w,y:h},fillLinearGradientColorStops:[0,'#163143',0.5,palette.bg,1,'#18343e'],listening:false}));
    const framed=framingGeometry(img.width,img.height,run.framing);
    const photo=new Konva.Image({image:img,...framed,listening:false});group.add(photo);
    group.add(new Konva.Rect({width:w,height:h,stroke:palette.accent,strokeWidth:2,opacity:0.3,listening:false}));
    visual.push({group,photo,run,base:{x:photo.x(),y:photo.y(),width:photo.width(),height:photo.height()}});
  }
  rule(layer,[86,y+h+22,994,y+h+22],palette.accent,0.38);
  const label=txt(layer,'',{x:90,y:1025,width:890,fontSize:23,letterSpacing:2,fill:palette.accent});
  const title=txt(layer,'',{x:86,y:1080,width:902,fontFamily:palette.font,fontSize:spec.layout==='chronicle'?74:106,lineHeight:1.08,fontStyle:spec.layout==='chronicle'?'bold':'normal'});
  const body=txt(layer,'',{x:90,y:1300,width:890,fontSize:35,lineHeight:1.4,fill:palette.muted});
  const detail=txt(layer,'',{x:90,y:1495,width:885,fontSize:23,letterSpacing:1,fill:palette.accent});
  const progress=rule(layer,[86,1557,86,1557],palette.accent,0.7,3);
  txt(layer,'Leia a história no blog  ·  Link na bio',{x:88,y:1602,width:895,fontSize:28,fill:palette.muted});
  const dots=[];
  if(['dark','celestial','fantasy'].includes(spec.tone)){for(let n=0;n<13;n++){const dot=new Konva.Circle({radius:n%3===0?2:1,fill:palette.accent,opacity:0.3});layer.add(dot);dots.push(dot);}}
  let active=-1;const typography=[];
  function render(time){
    const state=manualState(spec,time);
    if(state.index!==active){
      active=state.index;const beat=spec.scenes[active];
      label.text(beat.eyebrow.toUpperCase());fit(label,55,23,21);
      title.text(beat.title);const titleSize=fit(title,190,spec.layout==='chronicle'?74:106,spec.layout==='chronicle'?54:72);
      body.text(beat.body);const bodySize=fit(body,178,35,31);
      detail.text(active===3?'ARTIGO COMPLETO NO ESTRATÉGIA NERD':(spec.comparison?spec.comparison.join('   ×   '):''));
      typography.push({scene:active,titleSize,bodySize});
    }
    for(const shot of visual){
      const {run,group,photo,base}=shot;const enter=ease((time-run.start)/0.72);
      const exit=run.end===spec.duration?1:1-ease((time-run.end)/0.72);
      group.opacity(enter*exit);
      const p=clamp((time-run.start)/(run.end-run.start));const zoom=1+p*0.035;
      photo.setAttrs({x:base.x-base.width*(zoom-1)/2+Math.sin(p*Math.PI)*5,y:base.y-base.height*(zoom-1)/2,width:base.width*zoom,height:base.height*zoom});
    }
    label.opacity(state.titleOpacity);title.opacity(state.titleOpacity);body.opacity(state.bodyOpacity);detail.opacity(state.bodyOpacity);
    const direction=spec.layout==='versus'?(active%2?1:-1):1;
    title.x(86+(1-state.titleOpacity)*(['versus','culture'].includes(spec.layout)?direction*36:0));
    title.y(1080+(1-state.titleOpacity)*(spec.layout==='chronicle'?14:26));
    body.y(1300+(1-state.bodyOpacity)*18);
    progress.points([86,1557,86+908*clamp(time/spec.duration),1557]);
    dots.forEach((dot,n)=>dot.position({x:85+(n*149)%900+Math.sin(time*.18+n)*9,y:320+(n*97-time*6+1900)%1170}));
    layer.draw();return {...state,runs:runs.length};
  }
  return {stage,layer,render,typography,destroy:()=>stage.destroy()};
}
