import Konva from 'konva';
import 'konva/skia-backend';
import { FontLibrary, loadImage } from 'skia-canvas';
import { existsSync } from 'node:fs';
import { resolve } from 'node:path';
import { createManualScene, validateManualSpec } from '../reels-review/scene.mjs';
import { createHardwareScene } from '../reels-review/hardware-scene.mjs';
import { createDiagnosticScene } from '../reels-review/diagnostic-scene.mjs';

export const WIDTH = 1080;
export const HEIGHT = 1920;
export const FPS = 30;
export const VERSION = 'konva-editorial-v2';
const WHITE = '#f7f3ef';
const AMBER = '#ffb36c';
const CYAN = '#57dce8';
const PURPLE = '#9966ef';
export const THEMES = {
  hardware: {label:'HARDWARE',accent:'#61dcf0',secondary:'#92eff5',frame:'#597ef3'},
  games: {label:'GAMES',accent:AMBER,secondary:CYAN,frame:PURPLE},
  dicas: {label:'DICAS',accent:'#74e2b0',secondary:'#b6f6d0',frame:'#54a7d4'},
  editorial: {label:'CULTURA NERD',accent:'#d2a0ff',secondary:'#80deee',frame:'#b678f0'},
};

export function registerFonts(root) {
  const fonts = [
    ['EN Display', 'BebasNeue-Regular.ttf'],
    ['EN Text', 'Inter.ttf'],
  ];
  for (const [family, filename] of fonts) {
    const path = resolve(root, 'resources/reels-konva/fonts', filename);
    if (!existsSync(path)) throw new Error(`Fonte local ausente: ${filename}`);
    FontLibrary.use(family, path);
  }
}

export function validateSpec(spec) {
  const manual = spec?.editorial === 'manual';
  if (!spec || !Number.isInteger(spec.duration) || spec.duration < 16 || spec.duration > (manual ? 60 : 30)) {
    throw new Error('Duração editorial fora do intervalo permitido.');
  }
  if (!Array.isArray(spec.scenes) || (manual ? spec.scenes.length < 2 || spec.scenes.length > 8 : spec.scenes.length !== 4)) throw new Error('Quantidade de cenas inválida.');
  for (const scene of spec.scenes) {
    for (const key of ['eyebrow', 'title', 'body', 'image']) {
      if (typeof scene[key] !== 'string' || !scene[key].trim()) throw new Error(`Cena sem ${key}.`);
    }
    if (scene.title.length > 110 || scene.body.length > 210 || scene.eyebrow.length > 45) {
      throw new Error('Texto excede o limite editorial; revise antes de gerar.');
    }
  }
  if (typeof spec.audio !== 'string' || !spec.audio) throw new Error('Trilha local obrigatória.');
  if (!Number.isFinite(spec.audioStart) || spec.audioStart < 0) throw new Error('Início da trilha inválido.');
  if (spec.editorial === 'manual') validateManualSpec(spec);
  if(spec.compositionProfile==='196-v5' && (spec.articleId!==16 || spec.category!=='hardware' || spec.scenes.length!==4 || spec.beatStarts.some((t,i)=>t!==i*spec.duration/4))) throw new Error('Composição 196-v5 incompatível com o roteiro preservado.');
}

export function proportionalCrop(imageWidth, imageHeight, width, height, focusX = 0.5) {
  const ratio = width / height;
  const cropWidth = Math.min(imageWidth, imageHeight * ratio);
  const cropHeight = cropWidth / ratio;
  return { x: (imageWidth - cropWidth) * focusX, y: (imageHeight - cropHeight) / 2, width: cropWidth, height: cropHeight };
}

export function containedImage(imageWidth,imageHeight,width,height,progress) {
  const scale=Math.min(width/imageWidth,height/imageHeight)*(0.94+Math.max(0,Math.min(1,progress))*0.06);
  const fittedWidth=imageWidth*scale,fittedHeight=imageHeight*scale;
  return {x:(width-fittedWidth)/2,y:(height-fittedHeight)/2,width:fittedWidth,height:fittedHeight};
}

const clamp = (n) => Math.max(0, Math.min(1, n));
const ease = (n) => 1 - (1 - clamp(n)) ** 3;
export function timeline(time, duration) {
  const safeTime = Math.max(0, Math.min(duration - 1 / FPS, time));
  const length = duration / 4;
  const index = Math.floor(safeTime / length);
  const local = safeTime - index * length;
  return { index, local, progress: local / length, entrance: ease(local / 0.65), exit: clamp((length - local) / 0.38) };
}

function line(parent, points, color, width = 2, opacity = 1) {
  const shape = new Konva.Line({ points, stroke: color, strokeWidth: width, opacity, listening: false });
  parent.add(shape);
  return shape;
}

function text(parent, value, attrs) {
  const shape = new Konva.Text({ text: value, fontFamily: 'EN Text', fill: WHITE, listening: false, ...attrs });
  parent.add(shape);
  return shape;
}

export function fitText(node, maxHeight, maxSize, minSize) {
  for (let size = maxSize; size >= minSize; size -= 2) {
    node.fontSize(size);
    if (node.height() <= maxHeight && node.width() <= 840) return size;
  }
  throw new Error('Texto não cabe com tamanho legível. Encurte o texto.');
}

/** A reusable scene graph; rendering uses explicit frame time, never wall-clock animation. */
export async function createScene(spec, root) {
  validateSpec(spec);
  if(spec.compositionProfile==='diagnostic-207-v1')return createDiagnosticScene(spec,root);
  if(spec.compositionProfile==='hardware-product-v1')return createHardwareScene(spec,root);
  if (spec.editorial === 'manual' && spec.compositionProfile !== '196-v5') return createManualScene(spec, root);
  registerFonts(root);
  const theme=THEMES[spec.category] || THEMES.games;
  const AMBER=theme.accent;const CYAN=theme.secondary;const PURPLE=theme.frame;
  const images = [];
  for (const scene of spec.scenes) {
    const path = resolve(root, scene.image);
    if (!existsSync(path)) throw new Error(`Imagem local ausente: ${scene.image}`);
    images.push(await loadImage(path));
  }
  const stage = new Konva.Stage({ width: WIDTH, height: HEIGHT, listening: false });
  const layer = new Konva.Layer({ listening: false });
  stage.add(layer);

  const background = new Konva.Group({ listening: false });
  layer.add(background);
  background.add(new Konva.Rect({ width: WIDTH, height: HEIGHT, fillLinearGradientStartPoint: { x: 0, y: 0 }, fillLinearGradientEndPoint: { x: WIDTH, y: HEIGHT }, fillLinearGradientColorStops: [0, '#18101d', 0.42, '#090d18', 1, '#100d20'] }));
  // Soft radial fields are rendered and cached once rather than recomputed per frame.
  for (const [x,y,r,color] of [[910,680,670,'rgba(127,63,195,0.23)'],[100,1420,600,'rgba(238,104,40,0.14)']]) {
    background.add(new Konva.Circle({ x,y,radius:r, fillRadialGradientStartPoint:{x:0,y:0},fillRadialGradientEndPoint:{x:0,y:0},fillRadialGradientStartRadius:0,fillRadialGradientEndRadius:r,fillRadialGradientColorStops:[0,color,1,'rgba(0,0,0,0)'] }));
  }
  for (let x = 40; x < WIDTH; x += 80) line(background, [x,100,x,1760], '#654270',1,0.1);
  for (let y = 100; y < 1760; y += 80) line(background, [40,y,1000,y], '#654270',1,0.1);
  line(background,[62,300,62,200,156,200],CYAN,2,0.6);
  line(background,[950,1450,950,1560,856,1560],PURPLE,2,0.6);
  background.cache({ x:0,y:0,width:WIDTH,height:HEIGHT,pixelRatio:1 });

  text(layer,'ESTRATÉGIA',{ x:92,y:169,width:610,fontSize:27,fontStyle:'bold',letterSpacing:5 });
  text(layer,'NERD',{ x:355,y:169,width:230,fontSize:27,fontStyle:'bold',letterSpacing:5,fill:CYAN });
  layer.add(new Konva.Rect({x:784,y:164,width:152,height:42,cornerRadius:5,stroke:PURPLE,strokeWidth:1,fill:'#20152e'}));
  text(layer,theme.label,{x:784,y:176,width:152,fontSize:14,align:'center',letterSpacing:2,fill:'#e5d4f5'});
  line(layer,[92,236,936,236],'#49334e',1);

  const content = new Konva.Group({ listening: false });
  layer.add(content);
  const label = text(content,'',{x:94,y:300,width:800,fontSize:21,letterSpacing:3,fill:AMBER});
  const title = text(content,'',{x:86,y:352,width:840,fontFamily:'EN Display',fontSize:136,lineHeight:0.98,letterSpacing:1});
  const subtitle = text(content,'',{x:96,y:596,width:824,fontSize:31,lineHeight:1.35,fill:'#ccc6d2'});

  const picture = new Konva.Group({x:88,y:770,clipFunc(ctx){
    ctx.beginPath();ctx.moveTo(30,0);ctx.lineTo(840,0);ctx.lineTo(840,570);ctx.lineTo(810,600);ctx.lineTo(0,600);ctx.lineTo(0,30);ctx.closePath();
  }});
  content.add(picture);
  const photo = new Konva.Image({image:images[0],width:840,height:600,listening:false});
  picture.add(photo);
  picture.add(new Konva.Rect({x:0,y:0,width:840,height:600,fillLinearGradientStartPoint:{x:0,y:200},fillLinearGradientEndPoint:{x:0,y:600},fillLinearGradientColorStops:[0,'rgba(8,5,12,0)',0.72,'rgba(8,5,12,0.15)',1,'rgba(8,5,12,0.9)']}));
  // Multi-stroke corners add a restrained glow without costly animated blur filters.
  for (const width of [15,8,2]) {
    const opacity=width===2?0.9:0.07;
    line(content,[116,759,560,759],AMBER,width,opacity);
    line(content,[944,1060,944,1340,914,1370,600,1370],PURPLE,width,opacity);
  }
  const imageCaption=text(content,'',{x:120,y:1265,width:728,fontSize:19,fill:WHITE,letterSpacing:2});
  const chapter=text(content,'',{x:94,y:1444,width:810,fontFamily:'EN Display',fontSize:47,fill:AMBER,letterSpacing:1});

  const particles=new Konva.Group({listening:false});layer.add(particles);
  const dots=[];
  for(let n=0;n<24;n++){
    const dot=new Konva.Circle({radius:n%3===0?2.5:1.3,fill:n%2===0?AMBER:CYAN,opacity:0.45});
    particles.add(dot);dots.push(dot);
  }
  line(layer,[94,1530,936,1530],'#49334e',1);
  const progressLine=line(layer,[94,1530,94,1530],CYAN,3);
  const footer=text(layer,'A HISTÓRIA COMPLETA ESTÁ NO BLOG',{x:94,y:1582,width:825,fontSize:19,letterSpacing:2,fill:'#c5b9ce'});
  const cta=text(layer,'Leia o artigo  →  Link na bio',{x:94,y:1620,width:825,fontSize:33,fontStyle:'bold'});
  let active=-1;
  const typography=[];
  function render(time){
    const state=timeline(time,spec.duration);
    if(state.index!==active){
      active=state.index;const scene=spec.scenes[active];
      label.text(scene.eyebrow.toUpperCase());
      fitText(label,44,21,17);
      title.text(scene.title.toUpperCase());
      const titleSize=fitText(title,230,136,70);
      subtitle.text(scene.body);const bodySize=fitText(subtitle,145,31,26);
      photo.image(images[active]);
      chapter.text(scene.chapter || `${String(active+1).padStart(2,'0')} / ${theme.label}`);
      imageCaption.text(scene.caption || 'ILUSTRAÇÃO DO ARTIGO');
      typography.push({scene:active,titleSize,bodySize});
    }
    const scale=spec.compositionProfile==='196-v5'?1:1+state.progress*0.06;
    if(spec.scenes[active].imageFit==='contain'){
      photo.crop({x:0,y:0,width:images[active].width,height:images[active].height});
      photo.setAttrs(containedImage(images[active].width,images[active].height,840,600,state.progress));
    }else{
      photo.position({x:0,y:0});photo.size({width:840,height:600});
      photo.crop(proportionalCrop(images[active].width,images[active].height,840/scale,600/scale,0.5));
    }
    content.opacity(state.entrance*state.exit);
    title.x(86+(1-state.entrance)*45);
    picture.y(770+(1-state.entrance)*65);
    imageCaption.opacity(state.entrance);
    for(let n=0;n<dots.length;n++){
      dots[n].position({x:70+(n*157)%880+Math.sin(time*0.45+n)*12,y:290+((n*113-time*(9+n%5)+2000)%1150)});
    }
    progressLine.points([94,1530,94+842*clamp(time/spec.duration),1530]);
    footer.opacity(active===3?1:0.65);cta.fill(active===3?CYAN:WHITE);
    layer.draw();
    return state;
  }
  return {stage,layer,render,typography,destroy:()=>stage.destroy()};
}
