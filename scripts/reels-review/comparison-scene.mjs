import Konva from 'konva';
import 'konva/skia-backend';
import {FontLibrary,loadImage} from 'skia-canvas';
import {resolve} from 'node:path';
import {manualState} from './scene.mjs';

export const COMPARISON_FRAME={x:60,y:880,width:960,height:540};
export async function createComparisonScene(spec,root){
  if(spec.reviewPostId!==206||spec.compositionProfile!=='comparison-206-v1'||spec.presentation!=='comparison'||spec.duration!==20||spec.scenes.length!==4||spec.beatStarts.length!==4||spec.beatStarts.some((t,i)=>t!==i*5)||new Set(spec.scenes.map(s=>s.image)).size!==1)throw new Error('Comparativo fora do escopo aprovado do 206.');
  FontLibrary.use('EN Comparison',resolve(root,'resources/reels-konva/fonts/Inter.ttf'));
  const [background,cover]=await Promise.all([loadImage(resolve(root,spec.visualStyle.background)),loadImage(resolve(root,spec.scenes[0].image))]);
  if(Math.abs(cover.width/cover.height-16/9)>.01)throw new Error('Capa deve preservar a proporção 16:9.');
  const stage=new Konva.Stage({width:1080,height:1920,listening:false});
  const layer=new Konva.Layer({listening:false});stage.add(layer);
  layer.add(new Konva.Image({image:background,width:1080,height:1920,listening:false}));
  const text=(value,attrs)=>{const node=new Konva.Text({text:value,fontFamily:'EN Comparison',fill:'#f5f5f5',listening:false,...attrs});layer.add(node);return node;};
  const fit=(node,height,max,min)=>{for(let size=max;size>=min;size-=2){node.fontSize(size);if(node.height()<=height)return size;}throw new Error('Texto do comparativo não cabe: '+node.text());};
  text('ESTRATÉGIA',{x:94,y:108,width:280,fontSize:29,fontStyle:'bold',letterSpacing:4});
  text('NERD',{x:380,y:108,width:180,fontSize:29,fontStyle:'bold',letterSpacing:4,fill:'#35e2ef'});
  text('COMPARATIVO',{x:700,y:114,width:280,fontSize:23,letterSpacing:3,align:'right'});
  const eyebrow=text('',{x:105,y:273,width:860,fontSize:32,fill:'#f5f5f5'});
  const title=text('',{x:98,y:349,width:890,fontStyle:'bold',lineHeight:1.06,fontSize:106});
  const firstA=text('RTX 3050',{x:98,y:345,width:900,fontStyle:'bold',fontSize:128,fill:'#35e2ef'});
  const firstB=text('ou RX 6600?',{x:98,y:493,width:900,fontStyle:'bold',fontSize:114,fill:'#ffc044'});
  const body=text('',{x:103,y:655,width:880,fontSize:43,lineHeight:1.2});
  text('RTX 3050',{x:87,y:826,width:380,fontSize:35,fontStyle:'bold',fill:'#35e2ef',align:'center'});
  text('VS',{x:490,y:820,width:100,fontSize:44,fontStyle:'bold',align:'center'});
  text('RX 6600',{x:613,y:826,width:380,fontSize:35,fontStyle:'bold',fill:'#ffc044',align:'center'});
  const frame=COMPARISON_FRAME;
  const photo=new Konva.Image({image:cover,...frame,crop:{x:0,y:0,width:cover.width,height:cover.height},listening:false});layer.add(photo);
  text('MODELO   ·   PREÇO   ·   SEUS JOGOS',{x:90,y:1513,width:900,fontSize:28,letterSpacing:3,align:'center'});
  layer.add(new Konva.Rect({x:168,y:1692,width:744,height:112,fill:'#071116',opacity:.94,stroke:'#35e2ef',strokeWidth:2,cornerRadius:12,listening:false}));
  text('Leia o artigo → Link na bio',{x:186,y:1728,width:708,fontSize:38,fontStyle:'bold',align:'center'});
  const progress=new Konva.Line({points:[90,1580,90,1580],stroke:'#35e2ef',strokeWidth:3,listening:false});layer.add(progress);
  let active=-1;const typography=[];
  function render(time){
    const state=manualState(spec,time);
    if(active!==state.index){
      active=state.index;const scene=spec.scenes[active];
      eyebrow.text(scene.eyebrow);fit(eyebrow,65,32,28);
      const first=active===0;firstA.visible(first);firstB.visible(first);title.visible(!first);
      title.text(scene.title);const titleSize=first?114:fit(title,275,106,74);
      body.text(scene.body);const bodySize=fit(body,143,43,35);
      typography.push({scene:active,titleSize,bodySize});
    }
    const opacity=Math.min(1,Math.max(0,state.local)/.22);
    for(const node of [eyebrow,title,firstA,firstB,body])node.opacity(opacity);
    progress.points([90,1580,90+900*Math.max(0,Math.min(1,time/spec.duration)),1580]);
    layer.draw();return state;
  }
  return {stage,layer,render,typography,destroy:()=>stage.destroy()};
}
