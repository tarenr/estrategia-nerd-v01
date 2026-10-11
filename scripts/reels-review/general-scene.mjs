import Konva from 'konva';
import 'konva/skia-backend';
import {FontLibrary,loadImage} from 'skia-canvas';
import {resolve} from 'node:path';
import {manualState,imageRuns,framingGeometry} from './scene.mjs';

export async function createGeneralScene(spec,root){
  if(![208,216,232,234].includes(spec.reviewPostId)||spec.compositionProfile!=='general-editorial-v1'||spec.duration!==20||spec.scenes.length!==4||spec.beatStarts.some((t,i)=>t!==i*5))throw new Error('Padrão geral fora do escopo aprovado.');
  FontLibrary.use('EN General Display','C:/Windows/Fonts/impact.ttf');
  FontLibrary.use('EN General Body',resolve(root,'resources/reels-konva/fonts/Inter.ttf'));
  const runs=imageRuns(spec);
  const [background,...covers]=await Promise.all([spec.visualStyle.background,...runs.map(r=>r.image)].map(p=>loadImage(resolve(root,p))));
  const stage=new Konva.Stage({width:1080,height:1920,listening:false}),layer=new Konva.Layer({listening:false});
  layer.batchDraw=()=>layer;stage.add(layer);layer.getCanvas()._canvas.gpu=false;
  layer.add(new Konva.Image({image:background,width:1080,height:1920,listening:false}));
  layer.add(new Konva.Rect({width:1080,height:1920,fill:'#000',opacity:.18,listening:false}));
  const text=(value,attrs)=>{const node=new Konva.Text({text:value,fontFamily:'EN General Display',fill:'#f5f0e4',align:'center',shadowColor:'#000',shadowBlur:10,listening:false,...attrs});layer.add(node);return node;};
  const fit=(node,height,max,min)=>{for(let size=max;size>=min;size-=2){node.fontSize(size);if(node.height()<=height)return size;}throw new Error('Texto geral não cabe: '+node.text());};
  text('ESTRATÉGIA\nNERD',{x:90,y:72,width:900,fontSize:43,lineHeight:1.07});
  text(spec.visualStyle.collection??'EM FOCO',{x:90,y:214,width:900,fontFamily:'EN General Body',fontSize:30,fill:'#ffc044'});
  const header=text('',{x:80,y:290,width:920,fontSize:174,lineHeight:1.02});
  const frame={x:54,y:715,width:972,height:546.75};
  layer.add(new Konva.Rect({x:49,y:710,width:982,height:557,stroke:'#c7a66b',strokeWidth:3,listening:false}));
  const photo=new Konva.Image({listening:false});layer.add(photo);
  const subtitle=text('',{x:75,y:1358,width:930,fontSize:85,lineHeight:1.23});
  const body=text('',{x:95,y:1580,width:890,fontFamily:'EN General Body',fontSize:35,lineHeight:1.2});
  layer.add(new Konva.Line({points:[415,1710,665,1710],stroke:'#c7a66b',strokeWidth:3,listening:false}));
  text(spec.visualStyle.footer??'CONFIRA NO BLOG · LINK NA BIO',{x:80,y:1742,width:920,fontFamily:'EN General Body',fontSize:27});
  let active=-1,activeImage=-1;const typography=[];
  function render(time){
    const state=manualState(spec,time);
    if(state.index!==active){active=state.index;header.text(spec.visualStyle.headlines[active]);const titleSize=fit(header,360,174,95);subtitle.text(spec.visualStyle.subtitles[active]);fit(subtitle,210,85,62);body.text(spec.scenes[active].body);const bodySize=fit(body,110,35,31);typography.push({scene:active,titleSize,bodySize});}
    const t=Math.max(0,Math.min(spec.duration-1/30,time)),index=runs.findIndex(r=>t>=r.start&&t<r.end);
    if(index!==activeImage){activeImage=index;const cover=covers[index],geometry=framingGeometry(cover.width,cover.height,runs[index].framing,frame);photo.setAttrs({image:cover,...geometry,x:frame.x+geometry.x,y:frame.y+geometry.y});}
    const opacity=Math.min(1,Math.max(0,state.local)/.18);for(const node of [header,subtitle,body])node.opacity(opacity);
    layer.draw();return state;
  }
  return {stage,layer,render,typography,destroy:()=>stage.destroy()};
}
