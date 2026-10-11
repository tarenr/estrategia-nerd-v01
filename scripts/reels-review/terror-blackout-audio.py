"""Reel 231 v7: music crescendo without added noise, pause and approved scream."""
from pathlib import Path
import array
import hashlib
import json
import math
import subprocess
import sys
import wave

root = Path(__file__).resolve().parents[2]
base = root / 'resources/reels-review/assets/terror/audio'
target = base / 'reel-231-blackout-v7.wav'
manifest_path = target.with_suffix('.json')
if target.exists() or manifest_path.exists():
    raise SystemExit('Existing output preserved; choose a new revision.')
license_path = base / 'wuxiascrub-screams.license.json'
license_info = json.loads(license_path.read_text(encoding='utf8'))
source = root / license_info['path']
music_path = base / 'the-house-of-leaves.mp3'
rate, hit = 48000, 640000

def pcm(path, channels):
    values = array.array('h', subprocess.check_output(['ffmpeg', '-v', 'error', '-i', str(path), '-ar', str(rate), '-ac', str(channels), '-f', 's16le', 'pipe:1']))
    if sys.byteorder != 'little':
        values.byteswap()
    return values

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

if sha(source) != license_info['sha256']:
    raise SystemExit('Scream source diverges from licensed/approved file.')
scream, music = pcm(source, 1), pcm(music_path, 2)
start = next(n for n, value in enumerate(scream[:rate]) if abs(value) > 1000)
end = int(1.32 * rate)
if start / rate != license_info['excerptStart'] or end / rate != license_info['excerptEnd']:
    raise SystemExit('Excerpt differs from approved preview.')
clip = scream[start:end]
scale = .38 * 32767 / max(abs(value) for value in clip)
result = array.array('h')
for frame in range(20 * rate):
    t = frame / rate
    if frame < hit:
        gain = (.55 + 1.45 * min(1, max(0, (t - 1) / 11.15))) * min(1, t / .45, max(0, (12.5 - t) / .025))
        left, right = (.42 * math.tanh((music[frame * 2 + side] / 32768 * gain) / .42) * 32767 for side in (0, 1))
    else:
        gain = 1.1 * min(1, max(0, (t - 14.15) / .65))
        left, right = (.42 * math.tanh((music[frame * 2 + side] / 32768 * gain) / .42) * 32767 for side in (0, 1))
        n = frame - hit
        if n < len(clip):
            envelope = min(1, n / (rate * .003), (len(clip) - n) / (rate * .08))
            value = clip[n] * scale * envelope
            left += value
            right += value
    # Keep the exact envelope of the approved full preview; renderer also fades.
    fade = min(1, max(0, (20 - t) / .8)) if t > 19.2 else 1
    result.extend(round(max(-.44 * 32767, min(.44 * 32767, value)) * fade) for value in (left, right))
if sys.byteorder != 'little':
    result.byteswap()
with wave.open(str(target), 'wb') as stream:
    stream.setnchannels(2)
    stream.setsampwidth(2)
    stream.setframerate(rate)
    stream.writeframes(result.tobytes())
manifest = {'duration': 20, 'rate': rate, 'channels': 2, 'music': music_path.relative_to(root).as_posix(), 'musicSha256': sha(music_path), 'screamLicense': license_path.relative_to(root).as_posix(), 'screamSource': source.relative_to(root).as_posix(), 'screamSha256': sha(source), 'sha256': sha(target), 'generator': 'scripts/reels-review/terror-blackout-audio.py', 'generatorSha256': sha(Path(__file__)), 'illustrative': True, 'notOriginalGameAudio': True, 'approvedScreamExcerptMatched': True, 'addedNoise': False, 'musicGain': {'start': .55, 'end': 2.0, 'ramp': [1, 12.15], 'fadeToPause': [12.475, 12.5]}, 'silence': [12.5, hit / rate], 'impact': {'time': hit / rate, 'sample': hit, 'videoFrame': 400, 'type': 'recorded scream', 'end': (hit + len(clip)) / rate}, 'musicReturn': [14.15, 14.8], 'musicBedEnds': 20, 'sourceEndFade': [19.2, 20], 'peakBound': .44}
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n', encoding='utf8')
print('Created 231 v7 source; music crescendo, no added noise, approved scream.')
