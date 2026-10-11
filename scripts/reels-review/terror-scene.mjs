import Konva from 'konva';
import 'konva/skia-backend';
import {FontLibrary,loadImage} from 'skia-canvas';
import {resolve} from 'node:path';
import {manualState} from './scene.mjs';

export const TERROR_FRAME={x:53,y:734,width:974,height:498};
export async function createTerrorScene(spec,root){
  if(spec.reviewPostId!==230||spec.compositionProfile!=='terror-atmosphere-v1'||spec.duration!==20||spec.scenes.length!==3||spec.beatStarts.length!==3||spec.beatStarts.some((t,i)=>t!==i*20/3)||spec.visualStyle.subtitles.length!==3)throw new Error('Terror fora do escopo aprovado.');
  FontLibrary.use('EN Terror Display','C:/Windows/Fonts/impact.ttf');
  FontLibrary.use('EN Terror Label',resolve(root,'resources/reels-konva/fonts/BebasNeue-Regular.ttf'));
  FontLibrary.use('EN Terror Body',resolve(root,'resources/reels-konva/fonts/Inter.ttf'));
  const [background,...covers]=await Promise.all([spec.visualStyle.background,...spec.scenes.map(s=>s.image)].map(p=>loadImage(resolve(root,p))));
  const stage=new Konva.Stage({width:1080,height:1920,listening:false}),layer=new Konva.Layer({listening:false});
  // Offline frames are drawn explicitly; queued UI redraws can race PNG export.
  layer.batchDraw=()=>layer;stage.add(layer);
  // CPU rasterization keeps outlined display text identical across exports.
  layer.getCanvas()._canvas.gpu=false;
  layer.add(new Konva.Image({image:background,width:1080,height:1920,listening:false}));
  const text=(value,attrs)=>{const n=new Konva.Text({text:value,fontFamily:'EN Terror Body',fill:'#f5f0e4',shadowColor:'#000',shadowBlur:8,shadowOpacity:1,listening:false,...attrs});layer.add(n);return n;};
  const fit=(node,height,max,min)=>{for(let size=max;size>=min;size-=2){node.fontSize(size);if(node.height()<=height)return size;}throw new Error('Texto de terror não cabe: '+node.text());};
  text('ESTRATÉGIA\nNERD',{x:330,y:55,width:420,fontFamily:'EN Terror Display',fontSize:43,lineHeight:.95,align:'center',stroke:'#0a0b0b',strokeWidth:1,fillAfterStrokeEnabled:true});
  layer.add(new Konva.Line({points:[460,151,620,151],stroke:'#b9101b',strokeWidth:3,listening:false}));
  text('TERROR · ALÉM DOS JUMPSCARES',{x:115,y:203,width:850,fontFamily:'EN Terror Label',fontSize:31,letterSpacing:6,align:'center'});
  const header=text('',{x:110,y:263,width:860,fontFamily:'EN Terror Display',fontSize:226,lineHeight:.91,align:'center',stroke:'#121512',strokeWidth:2,fillAfterStrokeEnabled:true,shadowBlur:12,shadowOffsetY:5});
  const label=text('',{x:340,y:691,width:400,fontFamily:'EN Terror Label',fontSize:30,letterSpacing:5,align:'center',fill:'#fff6e8'});
  const frame=TERROR_FRAME;layer.add(new Konva.Rect({...frame,fill:'#0b0e0d',listening:false}));
  const photo=new Konva.Image({listening:false});layer.add(photo);
  const subtitle=text('',{x:85,y:1282,width:910,fontFamily:'EN Terror Display',fontSize:132,lineHeight:.92,align:'center',stroke:'#101310',strokeWidth:2,fillAfterStrokeEnabled:true,shadowBlur:12,shadowOffsetY:4});
  const body=text('',{x:105,y:1545,width:870,fontSize:41,lineHeight:1.2,align:'center'});
  text('CONHEÇA OS 3 JOGOS · LINK NA BIO',{x:100,y:1760,width:880,fontFamily:'EN Terror Label',fontSize:32,letterSpacing:4,align:'center'});
  let active=-1;const typography=[];
  function render(time){
    const state=manualState(spec,time);
    if(active!==state.index){
      active=state.index;const scene=spec.scenes[active],cover=covers[active];
      const headlines=['A dúvida\nassusta','Cada item é\numa escolha','O cotidiano\nfica estranho'];
      header.text(headlines[active]);const titleSize=fit(header,382,226,120);
      label.text(scene.eyebrow.toUpperCase());
      subtitle.text(spec.visualStyle.subtitles[active]);fit(subtitle,247,132,76);
      body.text(scene.body);const bodySize=fit(body,140,41,35);
      const scale=Math.min(frame.width/cover.width,frame.height/cover.height);
      photo.setAttrs({image:cover,x:frame.x+(frame.width-cover.width*scale)/2,y:frame.y+(frame.height-cover.height*scale)/2,width:cover.width*scale,height:cover.height*scale,crop:{x:0,y:0,width:cover.width,height:cover.height}});
      typography.push({scene:active,titleSize,bodySize});
    }
    const opacity=Math.min(1,Math.max(0,state.local)/.18);for(const node of [header,label,subtitle,body])node.opacity(opacity);
    layer.draw();return state;
  }
  return {stage,layer,render,typography,destroy:()=>stage.destroy()};
}
