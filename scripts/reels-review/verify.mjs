import assert from 'node:assert/strict';
import {readFileSync,writeFileSync} from 'node:fs';
import {resolve,dirname} from 'node:path';
import {fileURLToPath} from 'node:url';
import {createScene,validateSpec} from '../reels-konva/scene.mjs';
import {imageRuns,IMAGE_FRAME,framingGeometry} from './scene.mjs';
const root=resolve(dirname(fileURLToPath(import.meta.url)),'../..');
const args=process.argv.slice(2),run=args[args.indexOf('--run')+1];if(!args.includes('--run')||!/^[a-z0-9-]+$/.test(run))throw new Error('--run obrigatório');
const dir=resolve(root,'storage/previews/reels-review',run);
const manifest=JSON.parse(readFileSync(resolve(dir,'manifest.json'),'utf8'));
const results=[];
for(const item of manifest.items){
  if(item.preserved){assert.equal(item.id,214);results.push({id:item.id,preserved:true});continue;}
  const spec=item.spec;const scene=await createScene(spec,root);
  try{
    for(let i=0;i<4;i++)scene.render(spec.beatStarts[i]+2.6);
    assert.equal(scene.typography.length,4);
    assert.ok(scene.typography.every(t=>t.bodySize>=31&&t.titleSize>=(spec.layout==='chronicle'?54:72)),'tipografia abaixo do mínimo');
    const images=scene.layer.find('Image');
    for(const image of images){
      const group=image.getParent();
      assert.deepEqual([group.x(),group.y(),group.clipWidth(),group.clipHeight()],[IMAGE_FRAME.x,IMAGE_FRAME.y,IMAGE_FRAME.width,IMAGE_FRAME.height],'moldura varia entre imagens');
      const crop=image.crop();assert.ok(crop.width>0&&crop.height>0);
      assert.ok(Math.abs(image.width()/image.height()-crop.width/crop.height)<0.00001,'imagem esticada');
    }
    if(new Set(spec.scenes.map(s=>s.image)).size===1){
      assert.equal(images.length,1,'uma imagem deve permanecer em uma única tomada');
      for(const time of spec.beatStarts.slice(1)){
        scene.render(time-1/30);const a={opacity:images[0].getParent().opacity(),width:images[0].width(),y:images[0].y()};
        scene.render(time+1/30);const b={opacity:images[0].getParent().opacity(),width:images[0].width(),y:images[0].y()};
        assert.equal(a.opacity,1);assert.equal(b.opacity,1);assert.ok(Math.abs(a.width-b.width)<1&&Math.abs(a.y-b.y)<1,'imagem reiniciou na troca do texto');
      }
    }
    scene.render(0.9);const titles=scene.layer.find('Text').filter(n=>n.text()===spec.scenes[0].title);assert.equal(titles[0]?.opacity(),0,'texto deve esperar a imagem');
    if(spec.scenes.every(s=>s.framing?.mode==='contain' && s.framing?.motion==='still')){
      for(let time=0;time<spec.duration;time+=0.5){
        scene.render(time);
        for(const image of images){assert.ok(image.x()>=-0.001&&image.y()>=-0.001&&image.x()+image.width()<=IMAGE_FRAME.width+0.001&&image.y()+image.height()<=IMAGE_FRAME.height+0.001,'arte completa deve ficar dentro da moldura durante todo o vídeo');assert.equal(image.crop().width,image.image().width);assert.equal(image.crop().height,image.image().height);}
      }
    }
    scene.render(3);const first=await scene.layer.getCanvas()._canvas.toBuffer('png');
    scene.render(4);assert.ok(!first.equals(await scene.layer.getCanvas()._canvas.toBuffer('png')),'sem movimento');
    scene.render(3);assert.ok(first.equals(await scene.layer.getCanvas()._canvas.toBuffer('png')),'animação depende de relógio/aleatoriedade');
    const bad={...spec,beatStarts:[0,1,14,21]};assert.throws(()=>validateSpec(bad));
    if(spec.layout==='chronicle')assert.ok(spec.scenes.every(s=>!/^\d/.test(s.eyebrow)));
    results.push({id:item.id,shots:imageRuns(spec).length,imageFirst:true,continuousSingleImage:true,readable:true,motion:true,deterministic:true});
  }finally{scene.destroy();}
}
const wide=framingGeometry(1600,900,{mode:'cover'});
assert.deepEqual([wide.width,wide.height],[908,511]);
const product=framingGeometry(670,820,{mode:'product'});
assert.deepEqual(product.crop,{x:0,y:0,width:670,height:820});
assert.ok(product.x>=0&&product.y>=0&&product.x+product.width<=908&&product.y+product.height<=511);
assert.throws(()=>framingGeometry(100,100,{region:[0.8,0,0.5,1]}));
const html=readFileSync(resolve(root,'resources/reels-review/gallery.html'),'utf8');
assert.ok(html.includes('controls playsinline preload="none"'));assert.ok(html.includes('other.pause()'));assert.ok(html.includes('Nenhum post corresponde'));assert.ok(html.includes('Referência preservada integralmente'));
writeFileSync(resolve(dir,'renderer-validation.json'),JSON.stringify({items:results,gallery:{lazyVideo:true,singlePlayback:true,emptyState:true}},null,2));
console.log(`PASS: ${results.length} tratamentos editoriais; imagem antes do texto, continuidade, leitura e movimento determinístico`);
