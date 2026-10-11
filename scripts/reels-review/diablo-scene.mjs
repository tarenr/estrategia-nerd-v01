import Konva from 'konva';
import 'konva/skia-backend';
import {FontLibrary,loadImage} from 'skia-canvas';
import {resolve} from 'node:path';
import {manualState} from './scene.mjs';

export const DIABLO_FRAME={x:24,y:665,width:1032,height:585};
export async function createDiabloScene(spec,root){
  const independent=spec.timingRevision==='diablo-195-v4';
  const revised=spec.typographyRevision==='diablo-195-v3'&&!independent;
  const beats=revised?[0,5,10,12.5]:[0,5,10,15];
  if(spec.reviewPostId!==195||spec.compositionProfile!=='diablo-duality-v1'||spec.presentation!=='story'||spec.duration!==(revised?15:20)||spec.scenes.length!==4||spec.beatStarts.length!==4||spec.beatStarts.some((t,i)=>t!==beats[i]))throw new Error('Diablo fora do escopo aprovado.');
  FontLibrary.use('EN Diablo',resolve(root,'resources/reels-konva/fonts/Inter.ttf'));
  FontLibrary.use('EN Diablo Title','C:/Windows/Fonts/georgiab.ttf');
  const [background,...covers]=await Promise.all([spec.visualStyle.background,...spec.scenes.map(s=>s.image)].map(p=>loadImage(resolve(root,p))));
  const stage=new Konva.Stage({width:1080,height:1920,listening:false}),layer=new Konva.Layer({listening:false});stage.add(layer);
  const strong=spec.typographyRevision==='diablo-195-v3';
  layer.add(new Konva.Image({image:background,width:1080,height:1920,listening:false}));
  if(strong)for(const box of [{x:180,y:290,width:720,height:300},{x:65,y:1300,width:950,height:305},{x:100,y:1638,width:880,height:65}])layer.add(new Konva.Rect({...box,fill:'#070403',opacity:.7,cornerRadius:25,listening:false}));
  const text=(value,attrs)=>{const n=new Konva.Text({text:value,fontFamily:'EN Diablo',fill:'#f8e5be',shadowColor:'#000',shadowBlur:7,shadowOpacity:1,listening:false,...attrs});layer.add(n);return n;};
  const fit=(node,height,max,min)=>{for(let size=max;size>=min;size-=2){node.fontSize(size);if(node.height()<=height)return size;}throw new Error('Texto Diablo não cabe: '+node.text());};
  text('ESTRATÉGIA\nNERD',{x:350,y:65,width:380,fontFamily:'EN Diablo Title',fontSize:38,lineHeight:1.1,letterSpacing:3,align:'center'});
  text('DIABLO · CRÔNICAS DE SANTUÁRIO',{x:125,y:292,width:830,fontSize:27,letterSpacing:3,align:'center'});
  const header=text('',{x:145,y:355,width:790,fontFamily:'EN Diablo Title',fontSize:88,lineHeight:1.1,align:'center',stroke:'#311706',strokeWidth:1,fillAfterStrokeEnabled:true});
  text('ALTOS CÉUS',{x:65,y:605,width:380,fontSize:24,letterSpacing:3,align:'center'});
  text('INFERNOS ARDENTES',{x:615,y:605,width:400,fontSize:24,letterSpacing:2,fill:'#ffbc9b',align:'center'});
  const frame=DIABLO_FRAME;layer.add(new Konva.Rect({...frame,fill:'#100c09',listening:false}));
  const photo=new Konva.Image({listening:false});layer.add(photo);
  layer.add(new Konva.Rect({...frame,stroke:'#b78e41',strokeWidth:1,listening:false}));
  const title=text('',{x:95,y:1320,width:890,fontFamily:'EN Diablo Title',fontSize:68,align:'center',lineHeight:1.06});
  const body=text('',{x:110,y:1465,width:860,fontSize:39,align:'center',lineHeight:1.18});
  text('CONHEÇA A HISTÓRIA · LINK NA BIO',{x:95,y:1655,width:890,fontSize:25,letterSpacing:2,align:'center'});
  if(strong){
    for(const node of [header,title])node.setAttrs({stroke:'#211006',strokeWidth:3,fillAfterStrokeEnabled:true,shadowColor:'#000',shadowBlur:13,shadowOffsetY:6,shadowOpacity:1,fillPriority:'linear-gradient',fillLinearGradientStartPoint:{x:0,y:0},fillLinearGradientEndPoint:{x:0,y:235},fillLinearGradientColorStops:[0,'#fffdf1',.25,'#fff4d1',.45,'#e8b565',.6,'#fff4d4',1,'#dba04c']});
    header.setAttrs({x:170,y:340,width:740,lineHeight:.99});
    body.setAttrs({fontFamily:'EN Diablo Title',fill:'#fff9ea',stroke:'#080403',strokeWidth:1.2,fillAfterStrokeEnabled:true,shadowBlur:8,y:1470});
  }
  let active=-1,activeImage=-1;const typography=[];
  function render(time){
    const state=manualState(spec,time);
    if(active!==state.index){
      active=state.index;const scene=spec.scenes[active];
      const lines=['A GUERRA\nETERNA','CÉU E\nINFERNO','Um mundo entre\nas forças','A guerra\natravessa eras'];
      header.text(strong?lines[active]:scene.eyebrow);fit(header,235,strong?110:88,52);
      title.text(scene.title);const titleSize=fit(title,135,68,48);
      body.text(scene.body);const bodySize=fit(body,145,39,35);
      typography.push({scene:active,titleSize,bodySize});
    }
    const imageIndex=independent?Math.min(2,Math.max(0,Math.floor(time*3/spec.duration))):state.index;
    if(activeImage!==imageIndex){
      activeImage=imageIndex;const cover=covers[imageIndex];
      const scale=Math.min(frame.width/cover.width,frame.height/cover.height);
      photo.setAttrs({image:cover,x:frame.x+(frame.width-cover.width*scale)/2,y:frame.y+(frame.height-cover.height*scale)/2,width:cover.width*scale,height:cover.height*scale,crop:{x:0,y:0,width:cover.width,height:cover.height}});
    }
    const opacity=Math.min(1,Math.max(0,state.local)/.22);for(const n of [header,title,body])n.opacity(opacity);
    layer.draw();return state;
  }
  return {stage,layer,render,typography,destroy:()=>stage.destroy()};
}
