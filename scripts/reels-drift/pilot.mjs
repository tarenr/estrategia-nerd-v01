import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import { DriftClient } from './mcp.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const executable = process.argv[2];
const output = path.resolve(process.argv[3] || path.join(root, 'storage/previews/reels-drift', `diablo-${Date.now()}`));
if (!executable || !fs.existsSync(executable)) throw new Error('Informe o caminho de drift.exe');
if (fs.existsSync(output)) throw new Error('Saída já existe; use uma pasta nova');
const spec = JSON.parse(fs.readFileSync(path.join(root, 'scripts/reels-konva/pilot.json'), 'utf8'));
for (const asset of [spec.audio, ...spec.scenes.map(scene => scene.image)]) if (!fs.existsSync(path.join(root, asset))) throw new Error(`Asset ausente: ${asset}`);
fs.mkdirSync(output, { recursive: true });
const client = new DriftClient(executable);
const journal = [];
async function call(name, args = {}) {
  const outer = await client.tool(name, args);
  const data = JSON.parse(outer.content.find(item => item.type === 'text').text);
  journal.push({ name, args, result: data });
  fs.writeFileSync(path.join(output, 'operations.json'), JSON.stringify(journal, null, 2));
  if (data.ok === false || data.error) throw new Error(JSON.stringify(data));
  return data;
}
async function text(content, at, duration, y, size, color = '#f6eee1') {
  await call('add_track', { type: 'text' });
  const added = await call('add_text', { text: content, at });
  const clip = added.id || added.clip;
  if (!clip) throw new Error(`Sem UUID: ${JSON.stringify(added)}`);
  await call('set_duration', { clip, duration });
  await call('set_transform', { clip, x: 90, y, w: 900, h: size > 70 ? 260 : 160 });
  await call('set_text', { clip, style: { fontFamily: 'Segoe UI', fontWeight: size > 70 ? 800 : 600, pixelSize: size, color, align: 'left', valign: 'center', wordWrap: true, shadowEnabled: true, shadowColor: '#000000', shadowBlur: 10, animIn: { kind: 'slideUp', duration: 0.65, unit: 'word', stagger: 0.035, ease: 'easeOut' } } });
  return clip;
}
try {
  const info = await client.initialize();
  fs.writeFileSync(path.join(output, 'runtime.json'), JSON.stringify(info, null, 2));
  await call('set_project_setup', { width: 1080, height: 1920, fps: 30 });
  await call('set_background', { kind: 'color', color: '#100c12' });
  const imported = await call('import_media', { paths: spec.scenes.map(scene => path.join(root, scene.image)) });
  const images = [];
  for (let i = 0; i < spec.scenes.length; i++) {
    const scene = spec.scenes[i];
    const at = i * 6;
    const added = await call('place_clip', { asset: imported.assets[i].id, at });
    const clip = added.id || added.clip;
    images.push(clip);
    await call('set_duration', { clip, duration: 6 });
    const probe = JSON.parse(spawnSync('ffprobe', ['-v', 'error', '-show_streams', '-of', 'json', path.join(root, scene.image)], { encoding: 'utf8' }).stdout).streams[0];
    const scale = Math.min(980 / probe.width, 760 / probe.height);
    const w = probe.width * scale, h = probe.height * scale;
    await call('set_transform', { clip, x: (1080 - w) / 2, y: 540 + (760 - h) / 2, w, h });
    for (const [prop, start, end] of [['width', w * 0.94, w], ['height', h * 0.94, h], ['x', (1080 - w * 0.94) / 2, (1080 - w) / 2]]) {
      await call('set_keyframe', { clip, prop, at, value: start });
      await call('set_keyframe', { clip, prop, at: at + 5.9, value: end });
    }
  }
  for (const clip of images.slice(0, -1)) await call('add_transition', { clip, kind: 'crossfade', duration: 0.5 });
  const audio = await call('import_media', { paths: [path.join(root, spec.audio)] });
  const audioAdded = await call('place_clip', { asset: audio.assets[0].id, at: 0 });
  await call('set_trim', { clip: audioAdded.id || audioAdded.clip, in: spec.audioStart, out: spec.audioStart + spec.duration });
  await call('set_fade', { clip: audioAdded.id || audioAdded.clip, in: 0.4, out: 0.8 });
  for (let i = 0; i < spec.scenes.length; i++) {
    const scene = spec.scenes[i], at = i * 6;
    await text(scene.eyebrow.toUpperCase(), at, 6, 210, 32, '#e8aa55');
    await text(scene.title, at, 6, 315, 82);
    await text(scene.body, at, 6, 1330, 40);
    await text(scene.chapter, at, 6, 1560, 30, '#e8aa55');
  }
  await text('ESTRATÉGIA NERD  /  DIABLO', 0, 24, 90, 26, '#e8aa55');
  await text('LEIA A HISTÓRIA COMPLETA NO BLOG', 0, 24, 1730, 26);
  await call('save_project', { path: path.join(output, 'diablo.drift') });
  const saved = await call('inspect', { clips: true, detail: true });
  if (saved.dirty || path.resolve(saved.path) !== path.join(output, 'diablo.drift') || !fs.existsSync(path.join(output, 'diablo.drift'))) throw new Error('Projeto não foi salvo');
  fs.writeFileSync(path.join(output, 'project-inspection.json'), JSON.stringify(saved, null, 2));
  console.log(JSON.stringify(await call('list_export_options')));
  const timings = [];
  for (let iteration = 1; iteration <= 2; iteration++) {
    const start = Date.now();
    let peakWorkingSetBytes = 0;
    await call('export_video', { path: path.join(output, `pilot-${iteration}.mp4`), video: 'h264', audio: 'aac', audio_bitrate: 192, fps: 30, height: 1920, rate: 'crf', crf: 20, preset: 'medium', in: 0, out: 24 });
    let status;
    do {
      await new Promise(resolve => setTimeout(resolve, 1000));
      status = await call('export_status');
      const measured = spawnSync('powershell.exe', ['-NoProfile', '-Command', `(Get-Process -Id ${client.process.pid}).WorkingSet64`], { encoding: 'utf8', windowsHide: true });
      peakWorkingSetBytes = Math.max(peakWorkingSetBytes, Number(measured.stdout.trim()) || 0);
      if (Date.now() - start > 600000) { await call('cancel_export'); throw new Error('Exportação excedeu 10 minutos'); }
    } while (status.busy || status.active);
    timings.push({ iteration, elapsedMs: Date.now() - start, peakWorkingSetBytes, measurement: 'WorkingSet64 sampled at polling, includes polling overhead in elapsed time', status });
    if (!fs.existsSync(path.join(output, `pilot-${iteration}.mp4`))) throw new Error(`Sem MP4: ${JSON.stringify(status)}`);
    console.log(`Exportação ${iteration}: ${timings.at(-1).elapsedMs} ms`);
  }
  fs.writeFileSync(path.join(output, 'timings.json'), JSON.stringify(timings, null, 2));
  console.log(output);
} finally { client.close(); }
