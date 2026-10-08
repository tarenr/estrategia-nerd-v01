import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';

const folder = path.resolve(process.argv[2] || '');
if (!process.argv[2]) throw new Error('Informe a pasta do piloto');
function run(command, args, binary = false) {
  const result = spawnSync(command, args, { encoding: binary ? null : 'utf8', maxBuffer: 64 * 1024 * 1024, windowsHide: true });
  if (result.error || result.status !== 0) throw new Error(`${command}: ${result.error || result.stderr}`);
  return result.stdout;
}
const checks = [];
function check(name, condition) { assert.ok(condition, name); checks.push(name); }
const videos = [];
const project = JSON.parse(fs.readFileSync(path.join(folder, 'project-inspection.json'), 'utf8'));
const clips = project.tracks.flatMap(track => track.items);
check('Timeline com 24 segundos e quatro imagens', project.dur === 24 && clips.filter(clip => clip.kind === 'image').length === 4);
check('Textos das quatro cenas no instante correto', [0, 6, 12, 18].every(at => clips.filter(clip => clip.kind === 'text' && clip.start === at && clip.duration === 6).length === 4));
check('Recorte de áudio preservado: 9–33 segundos', clips.some(clip => clip.kind === 'audio' && clip.inPoint === 9 && clip.outPoint === 33 && clip.duration === 24));
for (let iteration = 1; iteration <= 2; iteration++) {
  const file = path.join(folder, `pilot-${iteration}.mp4`);
  const probe = JSON.parse(run('ffprobe', ['-v', 'error', '-show_streams', '-show_format', '-of', 'json', file]));
  const video = probe.streams.find(stream => stream.codec_type === 'video');
  const audio = probe.streams.find(stream => stream.codec_type === 'audio');
  check(`${iteration}: H264 1080x1920 yuv420p`, video?.codec_name === 'h264' && video.width === 1080 && video.height === 1920 && video.pix_fmt === 'yuv420p');
  check(`${iteration}: 720 quadros / 24 segundos`, Number(video.nb_frames) === 720 && Math.abs(Number(probe.format.duration) - 24) < 0.1);
  check(`${iteration}: AAC 48kHz stereo`, audio?.codec_name === 'aac' && audio.sample_rate === '48000' && audio.channels === 2);
  run('ffmpeg', ['-v', 'error', '-xerror', '-i', file, '-f', 'null', '-']);
  checks.push(`${iteration}: decodificação integral sem erros`);
  const pcm = run('ffmpeg', ['-v', 'error', '-i', file, '-map', '0:a:0', '-ac', '1', '-ar', '8000', '-f', 'f32le', '-'], true);
  let sum = 0;
  for (let i = 0; i < pcm.length; i += 4) sum += pcm.readFloatLE(i) ** 2;
  const rms = Math.sqrt(sum / (pcm.length / 4));
  check(`${iteration}: som não silencioso`, rms > 0.001);
  videos.push({ iteration, sha256: createHash('sha256').update(fs.readFileSync(file)).digest('hex'), bytes: fs.statSync(file).size, rms, probe });
}
const sample = path.join(folder, 'pilot-1.mp4');
for (const at of [0, 1.6, 2, 4, 8, 14, 20, 23]) {
  const target = path.join(folder, `frame-${at}.jpg`);
  if (!fs.existsSync(target)) run('ffmpeg', ['-v', 'error', '-ss', String(at), '-i', sample, '-frames:v', '1', '-q:v', '2', target]);
}
function imageAt(file, at) {
  return run('ffmpeg', ['-v', 'error', '-ss', String(at), '-i', file, '-frames:v', '1', '-vf', 'crop=980:760:50:540,scale=98:76', '-pix_fmt', 'gray', '-f', 'rawvideo', '-'], true);
}
const early = imageAt(sample, 2), late = imageAt(sample, 4);
let movement = 0;
for (let i = 0; i < early.length; i++) movement += Math.abs(early[i] - late[i]);
movement /= early.length;
check('Movimento na imagem dentro da mesma cena', movement > 0.1);
const firstRun = imageAt(sample, 2), secondRun = imageAt(path.join(folder, 'pilot-2.mp4'), 2);
let repeatDifference = 0;
for (let i = 0; i < firstRun.length; i++) repeatDifference += Math.abs(firstRun[i] - secondRun[i]);
repeatDifference /= firstRun.length;
check('Repetição visual consistente', repeatDifference < 1);
const sheet = path.join(folder, 'contact-sheet.jpg');
if (!fs.existsSync(sheet)) run('ffmpeg', ['-v', 'error', '-i', sample, '-vf', "select='eq(n,60)+eq(n,240)+eq(n,420)+eq(n,600)',scale=270:480,tile=4x1", '-frames:v', '1', '-q:v', '2', sheet]);
fs.writeFileSync(path.join(folder, 'validation.json'), JSON.stringify({ checks, movement, repeatDifference, videos }, null, 2));
console.log(JSON.stringify({ passed: checks.length, movement, repeatDifference, sizes: videos.map(video => video.bytes) }));
