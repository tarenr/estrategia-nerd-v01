import Konva from 'konva';
import 'konva/skia-backend';
import {FontLibrary, loadImage} from 'skia-canvas';
import {resolve} from 'node:path';
import {manualState, validateManualSpec} from './scene.mjs';

// Reusable chapter profile: timing and images come from the manual spec.
export async function createDarksidersScene(spec, root) {
  validateManualSpec(spec);
  if(spec.editorial !== 'manual' || spec.compositionProfile !== 'darksiders-chronicle-v1' || !spec.visualStyle?.background) {
    throw new Error('Perfil Darksiders exige spec manual e fundo ilustrado.');
  }
  FontLibrary.use('EN Darksiders Title', 'C:/Windows/Fonts/georgiab.ttf');
  FontLibrary.use('EN Darksiders Text', resolve(root, 'resources/reels-konva/fonts/Inter.ttf'));
  const [background, ...images] = await Promise.all(
    [spec.visualStyle.background, ...spec.scenes.map(scene => scene.image)].map(path => loadImage(resolve(root, path)))
  );
  const stage = new Konva.Stage({width:1080, height:1920, listening:false});
  const layer = new Konva.Layer({listening:false});
  stage.add(layer);
  layer.add(new Konva.Image({image:background, width:1080, height:1920, listening:false}));
  layer.add(new Konva.Rect({width:1080, height:650, fillLinearGradientStartPoint:{x:0,y:0}, fillLinearGradientEndPoint:{x:0,y:650}, fillLinearGradientColorStops:[0,'#080604',0.7,'rgba(8,6,4,0.65)',1,'rgba(8,6,4,0)'], listening:false}));
  layer.add(new Konva.Rect({y:1230, width:1080, height:690, fillLinearGradientStartPoint:{x:0,y:0}, fillLinearGradientEndPoint:{x:0,y:690}, fillLinearGradientColorStops:[0,'rgba(8,6,4,0)',0.18,'rgba(8,6,4,0.8)',1,'#080604'], listening:false}));
  const text = (value, attrs) => {
    const node = new Konva.Text({text:value, fontFamily:'EN Darksiders Text', fill:'#f9edd4', align:'center', shadowColor:'#000', shadowBlur:8, shadowOpacity:1, listening:false, ...attrs});
    layer.add(node);
    return node;
  };
  const serif = {fontFamily:'EN Darksiders Title', fill:'#f1d398', stroke:'#291708', strokeWidth:1, fillAfterStrokeEnabled:true};
  const fit = (node, height, maximum, minimum) => {
    for(let size=maximum; size>=minimum; size-=2) {
      node.fontSize(size);
      if(node.height()<=height) return size;
    }
    throw new Error('Texto Darksiders não cabe: '+node.text());
  };
  text('ESTRATÉGIA\nNERD', {x:330,y:95,width:420,fontSize:35,lineHeight:1.12,letterSpacing:4,...serif});
  text(spec.collection, {x:90,y:245,width:900,fontSize:23,letterSpacing:3,fill:'#e1c497'});
  const heading = text('', {x:120,y:325,width:840,fontSize:86,lineHeight:1.08,...serif});
  text('CÉU', {x:75,y:595,width:370,fontSize:26,letterSpacing:5,fill:'#f4d89b'});
  text('INFERNO', {x:635,y:595,width:370,fontSize:26,letterSpacing:5,fill:'#ecad92'});
  const frame = {x:24,y:660,width:1032,height:581};
  layer.add(new Konva.Rect({...frame,fill:'#160f09',shadowColor:'#000',shadowBlur:24,shadowOpacity:0.9,listening:false}));
  const photo = new Konva.Image({listening:false});
  layer.add(photo);
  layer.add(new Konva.Rect({...frame,stroke:'#b98c45',strokeWidth:2,listening:false}));
  const title = text('', {x:90,y:1300,width:900,fontSize:66,lineHeight:1.08,...serif});
  const body = text('', {x:105,y:1490,width:870,fontSize:38,lineHeight:1.24});
  layer.add(new Konva.Line({points:[180,1665,900,1665],stroke:'#ba8e49',strokeWidth:1,opacity:0.7,listening:false}));
  text('CONHEÇA A HISTÓRIA · LINK NA BIO', {x:75,y:1700,width:930,fontSize:25,letterSpacing:2,fill:'#e7c991'});
  let active = -1;
  const typography = [];
  function render(time) {
    const state = manualState(spec,time);
    if(active !== state.index) {
      active = state.index;
      const scene = spec.scenes[active];
      heading.text(scene.eyebrow);
      const headingSize = fit(heading,230,86,50);
      title.text(scene.title);
      const titleSize = fit(title,150,66,44);
      body.text(scene.body);
      const bodySize = fit(body,140,38,30);
      const image = images[active];
      const scale = Math.min(frame.width/image.width,frame.height/image.height);
      photo.setAttrs({image,x:frame.x+(frame.width-image.width*scale)/2,y:frame.y+(frame.height-image.height*scale)/2,width:image.width*scale,height:image.height*scale});
      typography.push({scene:active,headingSize,titleSize,bodySize});
    }
    const opacity = Math.min(1,Math.max(0,state.local)/0.25);
    for(const node of [heading,title,body]) node.opacity(opacity);
    layer.draw();
    return state;
  }
  return {stage,layer,render,typography,destroy:()=>stage.destroy()};
}
