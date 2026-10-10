import { spawn, spawnSync } from 'node:child_process';
import { createHash, randomBytes } from 'node:crypto';
import { existsSync, readFileSync, mkdirSync, openSync, closeSync, unlinkSync, copyFileSync, constants, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { performance } from 'node:perf_hooks';
import { createScene, WIDTH, HEIGHT, FPS, VERSION, validateSpec } from './scene.mjs';

export function inspectMedia(path, ffprobe='ffprobe') {
  const result=spawnSync(ffprobe,['-v','error','-show_streams','-show_format','-of','json',path],{encoding:'utf8',timeout:15000,windowsHide:true});
  if(result.error || result.status!==0) throw new Error('Não foi possível inspecionar a mídia local.');
  return JSON.parse(result.stdout);
}

export function validateAudio(spec,root,ffprobe='ffprobe') {
  const path=resolve(root,spec.audio);
  if(!existsSync(path)) throw new Error('Trilha local não encontrada.');
  const meta=inspectMedia(path,ffprobe);
  const duration=Number(meta.format?.duration);
  if(!meta.streams?.some(s=>s.codec_type==='audio') || !Number.isFinite(duration) || duration<spec.audioStart+spec.duration) {
    throw new Error('A trilha não cobre o vídeo inteiro no recorte solicitado.');
  }
  return path;
}

export function inspectOutput(path,duration,ffprobe='ffprobe') {
  const meta=inspectMedia(path,ffprobe);
  const video=meta.streams.find(s=>s.codec_type==='video');
  const audio=meta.streams.find(s=>s.codec_type==='audio');
  if(!video || video.width!==WIDTH || video.height!==HEIGHT || video.codec_name!=='h264' || video.pix_fmt!=='yuv420p'
    || video.avg_frame_rate!=='30/1' || !audio || audio.codec_name!=='aac' || Math.abs(Number(meta.format.duration)-duration)>0.12) {
    throw new Error('O MP4 não atende aos critérios de vídeo e áudio.');
  }
  return meta;
}

const sha=(path)=>createHash('sha256').update(readFileSync(path)).digest('hex');
function writeFrame(stream,buffer){
  return new Promise((resolveFrame,rejectFrame)=>{
    // Callback includes backpressure: never queue the next frame before this write completes.
    stream.write(buffer,(error)=>error?rejectFrame(error):resolveFrame());
  });
}

/** One direct FFmpeg child, bounded buffers, private staging file, exclusive final publication. */
export async function renderVideo(spec,root,output,{ffmpeg='ffmpeg',ffprobe='ffprobe',timeoutMs=180000,maxRssBytes=768*1024**2,onFrame=()=>{}}={}) {
  validateSpec(spec);
  if(!Number.isFinite(timeoutMs) || timeoutMs<1 || timeoutMs>600000) throw new Error('Timeout inválido.');
  if(!Number.isFinite(maxRssBytes) || maxRssBytes<1 || maxRssBytes>2048*1024**2) throw new Error('Limite de memória inválido.');
  const audioPath=validateAudio(spec,root,ffprobe);
  const outputPath=resolve(output);
  if(existsSync(outputPath) || existsSync(outputPath+'.manifest.json')) throw new Error('Saída já existe; escolha outra pasta.');
  const codecs=spawnSync(ffmpeg,['-hide_banner','-encoders'],{encoding:'utf8',timeout:15000,windowsHide:true});
  if(codecs.error || codecs.status!==0 || !codecs.stdout.includes('libx264') || !codecs.stdout.includes(' aac ')) throw new Error('FFmpeg com H.264 e AAC obrigatório.');
  mkdirSync(dirname(outputPath),{recursive:true});
  const lockPath=outputPath+'.render.lock';
  const lock=openSync(lockPath,'wx');
  const partial=outputPath+'.'+randomBytes(4).toString('hex')+'.partial.mp4';
  let scene=null;let child=null;let timedOut=false;let cancelled=false;let streamError=null;
  let stderr='';let peakRss=process.memoryUsage().rss;
  const started=performance.now();
  let timer=null;let closed=null;
  const cancel=()=>{cancelled=true;child?.kill('SIGKILL');};
  const onParentExit=()=>child?.kill('SIGKILL');
  process.on('SIGINT',cancel);process.on('SIGTERM',cancel);process.on('exit',onParentExit);
  try {
    scene=await createScene(spec,root);
    const args=['-hide_banner','-loglevel','error','-nostdin','-n','-f','rawvideo','-pix_fmt','rgba','-s',`${WIDTH}x${HEIGHT}`,'-r',String(FPS),'-i','pipe:0',
      '-ss',String(spec.audioStart),'-t',String(spec.duration),'-i',audioPath,'-map','0:v:0','-map','1:a:0','-c:v','libx264','-preset','veryfast','-crf','20',
      '-threads','2','-pix_fmt','yuv420p','-c:a','aac','-b:a','192k','-ar','48000','-af',`afade=t=in:d=0.45,afade=t=out:st=${spec.duration-0.8}:d=0.8`,
      '-t',String(spec.duration),'-movflags','+faststart',partial];
    child=spawn(ffmpeg,args,{windowsHide:true,stdio:['pipe','ignore','pipe']});
    child.stderr.on('data',data=>{stderr=(stderr+data.toString()).slice(-4000);});
    child.stdin.on('error',error=>{streamError=error;});
    closed=new Promise(resolveClose=>{
      child.once('error',error=>{streamError=error;resolveClose(-1);});
      child.once('close',code=>resolveClose(code));
    });
    timer=setTimeout(()=>{timedOut=true;child.kill('SIGKILL');},timeoutMs);
    for(let frame=0;frame<spec.duration*FPS;frame++){
      if(timedOut || cancelled || streamError) throw new Error(timedOut?'Tempo de renderização excedido.':cancelled?'Renderização cancelada.':'FFmpeg interrompido.');
      scene.render(frame/FPS);
      const canvas=scene.layer.getCanvas()._canvas;
      const data=canvas.getContext('2d').getImageData(0,0,WIDTH,HEIGHT).data;
      await writeFrame(child.stdin,Buffer.from(data.buffer,data.byteOffset,data.byteLength));
      peakRss=Math.max(peakRss,process.memoryUsage().rss);
      if(peakRss>maxRssBytes) throw new Error('Limite de memória da renderização excedido.');
      onFrame({frame,pid:child.pid,peakRss});
    }
    child.stdin.end();
    const code=await closed;
    if(timedOut || cancelled || streamError || code!==0) throw new Error(timedOut?'Tempo de renderização excedido.':cancelled?'Renderização cancelada.':`FFmpeg falhou: ${stderr.slice(-800)}`);
    const media=inspectOutput(partial,spec.duration,ffprobe);
    const decode=spawnSync(ffmpeg,['-v','error','-i',partial,'-f','null','-'],{encoding:'utf8',timeout:30000,windowsHide:true});
    if(decode.error || decode.status!==0) throw new Error('Falha na decodificação completa do MP4.');
    copyFileSync(partial,outputPath,constants.COPYFILE_EXCL);
    const elapsedMs=Math.round(performance.now()-started);
    const packageJson=JSON.parse(readFileSync(resolve(root,'package.json'),'utf8'));
    const manifest={version:VERSION,createdAt:new Date().toISOString(),articleId:spec.articleId,articleTitle:spec.articleTitle,spec,
      dimensions:[WIDTH,HEIGHT],fps:FPS,frames:spec.duration*FPS,elapsedMs,msPerFrame:elapsedMs/(spec.duration*FPS),peakRss,
      versions:{node:process.version,konva:packageJson.dependencies.konva,skiaCanvas:packageJson.dependencies['skia-canvas'],ffmpeg:spawnSync(ffmpeg,['-version'],{encoding:'utf8',windowsHide:true}).stdout.split('\n')[0]},
      sources:spec.scenes.map(s=>({path:s.image,sha256:sha(resolve(root,s.image))})),audio:{path:spec.audio,start:spec.audioStart,sha256:sha(audioPath)},
      fonts:['BebasNeue-Regular.ttf','Inter.ttf'].map(f=>({file:f,sha256:sha(resolve(root,'resources/reels-konva/fonts',f))})),
      rendererSources:['scene.mjs','render.mjs'].map(f=>({file:f,sha256:sha(resolve(root,'scripts/reels-konva',f))})),
      manualRenderer:spec.editorial==='manual'?{file:'scripts/reels-review/scene.mjs',sha256:sha(resolve(root,'scripts/reels-review/scene.mjs'))}:null,
      hardwareStyle:spec.compositionProfile==='hardware-product-v1'?{renderer:'scripts/reels-review/hardware-scene.mjs',rendererSha256:sha(resolve(root,'scripts/reels-review/hardware-scene.mjs')),background:spec.visualStyle.background,backgroundSha256:sha(resolve(root,spec.visualStyle.background)),font:'C:/Windows/Fonts/bahnschrift.ttf',fontSha256:sha('C:/Windows/Fonts/bahnschrift.ttf')}:null,
      diagnosticStyle:['diagnostic-207-v1','diagnostic-guide-v1'].includes(spec.compositionProfile)?{renderer:'scripts/reels-review/diagnostic-scene.mjs',rendererSha256:sha(resolve(root,'scripts/reels-review/diagnostic-scene.mjs')),background:spec.visualStyle.background,backgroundSha256:sha(resolve(root,spec.visualStyle.background))}:null,
      typography:scene.typography,videoSha256:sha(outputPath),media};
    writeFileSync(outputPath+'.manifest.json',JSON.stringify(manifest,null,2),{flag:'wx'});
    return manifest;
  } catch(error) {
    if(timedOut) throw new Error('Tempo de renderização excedido.');
    if(cancelled) throw new Error('Renderização cancelada.');
    throw error;
  } finally {
    if(timer) clearTimeout(timer);
    if(child && child.exitCode===null) {child.kill('SIGKILL');if(closed) await closed;}
    scene?.destroy();
    process.off('SIGINT',cancel);process.off('SIGTERM',cancel);process.off('exit',onParentExit);
    if(existsSync(partial)) unlinkSync(partial);
    closeSync(lock);unlinkSync(lockPath);
  }
}
