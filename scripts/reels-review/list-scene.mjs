import Konva from 'konva';
import 'konva/skia-backend';
import {FontLibrary,loadImage} from 'skia-canvas';
import {resolve} from 'node:path';
import {manualState} from './scene.mjs';

export const LIST_FRAME={x:40,y:590,width:1000,height:600};
export async function createListScene(spec,root){
  const group=[215,217,218,219,233].includes(spec.reviewPostId);
  const count=spec.reviewPostId===233?5:4;
  if((spec.reviewPostId!==205&&!group)||spec.compositionProfile!=='list-central-v1'||spec.presentation!=='list'||spec.duration!==20||spec.scenes.length!==count||spec.beatStarts.length!==count||spec.beatStarts.some((t,i)=>t!==i*20/count))throw new Error('Lista fora do escopo aprovado.');
  if(group&&typeof spec.visualStyle.openingTitle!=='string')throw new Error('Cabeçalho da lista ausente.');
  FontLibrary.use('EN List',resolve(root,'resources/reels-konva/fonts/Inter.ttf'));
  FontLibrary.use('EN List Display',resolve(root,'resources/reels-konva/fonts/BebasNeue-Regular.ttf'));
  const [background,...covers]=await Promise.all([spec.visualStyle.background,...spec.scenes.map(s=>s.image)].map(p=>loadImage(resolve(root,p))));
  const stage=new Konva.Stage({width:1080,height:1920,listening:false}),layer=new Konva.Layer({listening:false});stage.add(layer);
  layer.add(new Konva.Image({image:background,width:1080,height:1920,listening:false}));
  const text=(value,attrs)=>{const n=new Konva.Text({text:value,fontFamily:'EN List',fill:'#f5f3ed',listening:false,...attrs});layer.add(n);return n;};
  const fit=(node,height,max,min)=>{for(let size=max;size>=min;size-=2){node.fontSize(size);if(node.height()<=height)return size;}throw new Error('Texto da lista não cabe: '+node.text());};
  text('ESTRATÉGIA NERD',{x:90,y:92,width:900,fontSize:29,letterSpacing:7,align:'center'});
  text('LISTAS PARA EXPLORAR',{x:90,y:246,width:900,fontSize:28,letterSpacing:5,fill:'#ffc044',align:'center'});
  const header=text('',{x:95,y:337,width:890,fontFamily:'EN List Display',fontSize:78,align:'center',lineHeight:1.05,shadowColor:'#000',shadowBlur:8,shadowOpacity:.9});
  const frame=LIST_FRAME;
  layer.add(new Konva.Rect({...frame,fill:'#080c10',listening:false}));
  const photo=new Konva.Image({listening:false});layer.add(photo);
  const title=text('',{x:95,y:1275,width:890,fontFamily:'EN List Display',fontSize:76,align:'center',lineHeight:1.05});
  const body=text('',{x:105,y:1400,width:870,fontSize:39,align:'center',lineHeight:1.18});
  layer.add(new Konva.Line({points:[425,1580,655,1580],stroke:'#83908d',strokeWidth:2,listening:false}));
  const dots=spec.scenes.map((_,i)=>{const d=new Konva.Circle({x:425+i*230/(count-1),y:1580,radius:7,stroke:'#deded8',strokeWidth:1.5,listening:false});layer.add(d);return d;});
  const counter=text('',{x:90,y:1610,width:900,fontSize:27,align:'center',fill:'#ffc044'});
  text('LEIA A LISTA COMPLETA · LINK NA BIO',{x:90,y:1700,width:900,fontSize:24,letterSpacing:2,align:'center'});
  let active=-1;const typography=[];
  function render(time){
    const state=manualState(spec,time);
    if(active!==state.index){
      active=state.index;const scene=spec.scenes[active],cover=covers[active];
      header.text(active===0&&spec.reviewPostId!==233?(group?spec.visualStyle.openingTitle:'10 jogos que todo fã\nde RPG precisa jogar'):scene.eyebrow);fit(header,190,78,52);
      title.text(scene.title);const titleSize=fit(title,group?115:100,76,group?50:54);
      body.text(scene.body);const bodySize=fit(body,145,39,33);
      const scale=Math.min(frame.width/cover.width,frame.height/cover.height);
      photo.setAttrs({image:cover,x:frame.x+(frame.width-cover.width*scale)/2,y:frame.y+(frame.height-cover.height*scale)/2,width:cover.width*scale,height:cover.height*scale,crop:{x:0,y:0,width:cover.width,height:cover.height}});
      counter.text(String(active+1).padStart(2,'0')+' / '+String(count).padStart(2,'0'));dots.forEach((d,i)=>d.fill(i===active?'#ffc044':'#10191c'));
      typography.push({scene:active,titleSize,bodySize});
    }
    const opacity=Math.min(1,Math.max(0,state.local)/.2);for(const node of [header,title,body])node.opacity(opacity);
    layer.draw();return state;
  }
  return {stage,layer,render,typography,destroy:()=>stage.destroy()};
}
