import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync,existsSync,mkdirSync,writeFileSync,readdirSync} from 'node:fs';
import {resolve,dirname} from 'node:path';
import {fileURLToPath} from 'node:url';
import {createHash} from 'node:crypto';
import {spawnSync} from 'node:child_process';
import {createScene,registerFonts,validateSpec} from './scene.mjs';
import {renderVideo,inspectOutput,validateAudio} from './render.mjs';

const root=resolve(dirname(fileURLToPath(import.meta.url)),'../..');
const spec=JSON.parse(readFileSync(resolve(root,'scripts/reels-konva/pilot.json'),'utf8'));
const directory=resolve(root,'storage/previews/reels-konva',`tests-${Date.now()}`);
mkdirSync(directory,{recursive:true});
const hash=data=>createHash('sha256').update(data).digest('hex');
const clone=()=>structuredClone(spec);
const pilot=resolve(root,process.env.EN_KONVA_PILOT || 'storage/previews/reels-konva/diablo-pilot-final/pilot.mp4');

test('MP4 real: dimensões, fps, H.264, AAC e duração corretos',()=>{
  const media=inspectOutput(pilot,spec.duration);
  assert.equal(Number(media.streams.find(s=>s.codec_type==='video').nb_frames),spec.duration*30);
});
test('MP4 real: decodifica vídeo e áudio completos sem erros',()=>{
  const result=spawnSync('ffmpeg',['-v','error','-i',pilot,'-f','null','-'],{encoding:'utf8',timeout:30000,windowsHide:true});
  assert.equal(result.status,0,result.stderr);
});
test('trilha é audível e tem fade, sem cortar o vídeo',()=>{
  const result=spawnSync('ffmpeg',['-hide_banner','-i',pilot,'-vn','-af','volumedetect','-f','null','-'],{encoding:'utf8',timeout:15000,windowsHide:true});
  const peak=result.stderr.match(/max_volume: (-?[\d.]+) dB/);
  assert.equal(result.status,0);assert.ok(peak);assert.ok(Number(peak[1])>-35 && Number(peak[1])<=0);
});
test('saída existente não é substituída',async()=>{
  const before=hash(readFileSync(pilot));
  await assert.rejects(renderVideo(spec,root,pilot),/Saída já existe/);
  assert.equal(hash(readFileSync(pilot)),before);
});
test('áudio ausente e recorte insuficiente impedem a renderização',()=>{
  const missing=clone();missing.audio='storage/arquivo-inexistente.mp3';
  assert.throws(()=>validateAudio(missing,root),/não encontrada/);
  const short=clone();short.audioStart=999999;
  assert.throws(()=>validateAudio(short,root),/não cobre/);
});
test('imagem ausente e fonte ausente falham explicitamente',async()=>{
  const missing=clone();missing.scenes[0].image='storage/imagem-inexistente.png';
  await assert.rejects(createScene(missing,root),/Imagem local ausente/);
  assert.throws(()=>registerFonts(directory),/Fonte local ausente/);
});
test('texto excessivo e duração fora dos limites são rejeitados',()=>{
  const large=clone();large.scenes[0].title='W'.repeat(111);
  assert.throws(()=>validateSpec(large),/limite editorial/);
  const duration=clone();duration.duration=0;assert.throws(()=>validateSpec(duration),/Duração/);
});
test('mesmo quadro tem renderização idêntica; movimento muda o quadro',async()=>{
  const scene=await createScene(spec,root);
  try{
    scene.render(1.6);const a=await scene.layer.getCanvas()._canvas.toBuffer('png');
    scene.render(1.6);const b=await scene.layer.getCanvas()._canvas.toBuffer('png');
    scene.render(2.6);const c=await scene.layer.getCanvas()._canvas.toBuffer('png');
    assert.equal(hash(a),hash(b));assert.notEqual(hash(a),hash(c));
  }finally{scene.destroy();}
});
test('títulos longos com acentos cabem sem fontes ilegíveis',async()=>{
  const long=clone();long.scenes[0].title='História de Santuário: uma jornada entre luz, trevas e humanidade';
  const scene=await createScene(long,root);
  try{scene.render(1.6);assert.ok(scene.typography[0].titleSize>=70);assert.ok(scene.typography[0].bodySize>=26);}
  finally{scene.destroy();}
});
test('bloqueio exclusivo impede dois geradores para a mesma saída',async()=>{
  const output=resolve(directory,'locked.mp4');writeFileSync(output+'.render.lock','another-worker',{flag:'wx'});
  await assert.rejects(renderVideo(spec,root,output),error=>error.code==='EEXIST');
  assert.equal(readFileSync(output+'.render.lock','utf8'),'another-worker');assert.ok(!existsSync(output));
});
test('timeout encerra FFmpeg e remove somente os próprios temporários',async()=>{
  const output=resolve(directory,'timeout.mp4');
  await assert.rejects(renderVideo(spec,root,output,{timeoutMs:1}),/Tempo de renderização excedido/);
  assert.ok(!existsSync(output));assert.ok(!existsSync(output+'.render.lock'));
  assert.ok(!readdirSync(directory).some(f=>f.startsWith('timeout.mp4.') && f.endsWith('.partial.mp4')));
});
test('cancelamento encerra o filho FFmpeg sem saída parcial',async()=>{
  const output=resolve(directory,'cancel.mp4');let pid;
  await assert.rejects(renderVideo(spec,root,output,{onFrame:info=>{pid=info.pid;process.emit('SIGINT');}}),/cancelada/);
  assert.ok(pid);assert.throws(()=>process.kill(pid,0));assert.ok(!existsSync(output));assert.ok(!existsSync(output+'.render.lock'));
});
test('limite de memória interrompe antes de produzir saída final',async()=>{
  const output=resolve(directory,'memory.mp4');
  await assert.rejects(renderVideo(spec,root,output,{maxRssBytes:1}),/Limite de memória/);
  assert.ok(!existsSync(output));assert.ok(!existsSync(output+'.render.lock'));
});
test('manifesto vincula MP4, imagens, fontes e música por hash',()=>{
  const manifest=JSON.parse(readFileSync(pilot+'.manifest.json','utf8'));
  assert.equal(manifest.videoSha256,hash(readFileSync(pilot)));
  for(const source of [...manifest.sources,manifest.audio]) assert.equal(source.sha256,hash(readFileSync(resolve(root,source.path))));
  for(const font of manifest.fonts) assert.equal(font.sha256,hash(readFileSync(resolve(root,'resources/reels-konva/fonts',font.file))));
  for(const source of manifest.rendererSources) assert.equal(source.sha256,hash(readFileSync(resolve(root,'scripts/reels-konva',source.file))));
  assert.ok(manifest.elapsedMs<120000);assert.ok(manifest.peakRss<768*1024**2);
});
