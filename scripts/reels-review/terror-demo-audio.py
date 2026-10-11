"""Reel 231: existing illustrative cue types, retimed to four 5-second beats."""
from pathlib import Path
import array
import hashlib
import json
import math
import random
import subprocess
import sys
import wave

root = Path(__file__).resolve().parents[2]
music_path = root / 'resources/reels-review/assets/terror/audio/the-house-of-leaves.mp3'
target = root / 'resources/reels-review/assets/terror/audio/reel-231-demo-v3.wav'
manifest_path = target.with_suffix('.json')
if target.exists() or manifest_path.exists():
    raise SystemExit('Existing demonstration preserved; use a new revision.')
rate = 48000
raw = subprocess.check_output(['ffmpeg', '-v', 'error', '-i', str(music_path), '-t', '20', '-f', 's16le', '-ac', '2', '-ar', str(rate), 'pipe:1'])
samples = array.array('h', raw)
if sys.byteorder != 'little':
    samples.byteswap()
if len(samples) < 20 * rate * 2:
    raise SystemExit('Music does not cover demonstration.')
rng = random.Random(231)
result = array.array('h')
for frame in range(20 * rate):
    t = frame / rate
    cue_active = 1.5 <= t < 3.5 or any(s <= t < s + .18 for s in (5.6, 7, 8.1, 8.9))
    # No bed in the silence or direction examples. Ramp out before silence.
    gain = (.08 if cue_active else .25) * min(1, t / .45, max(0, (10 - t) / .35)) if t < 10 else 0
    left, right = (samples[frame * 2 + side] / 32768 * gain for side in (0, 1))
    if 1.5 <= t < 3.5:
        envelope = min(1, (t - 1.5) / .3, (3.5 - t) / .3)
        noise = rng.uniform(-1, 1) * .025 * envelope
        left += noise
        right += noise
    for start in (5.6, 7, 8.1, 8.9):
        if start <= t < start + .18:
            p = (t - start) / .18
            tone = .065 * math.sin(math.pi * p) ** 2 * math.sin(2 * math.pi * 750 * t)
            left += tone
            right += tone
    for start, side in ((16, 'left'), (18.5, 'right')):
        if start <= t < start + .5:
            p = (t - start) / .5
            cue = .07 * math.sin(math.pi * p) ** 2 * math.sin(2 * math.pi * 520 * t)
            if side == 'left':
                left += cue
            else:
                right += cue
    result.extend(round(max(-.3, min(.3, n)) * 32767) for n in (left, right))
if sys.byteorder != 'little':
    result.byteswap()
with wave.open(str(target), 'wb') as stream:
    stream.setnchannels(2)
    stream.setsampwidth(2)
    stream.setframerate(rate)
    stream.writeframes(result.tobytes())
manifest = {'duration': 20, 'rate': rate, 'channels': 2, 'music': str(music_path.relative_to(root)).replace('\\', '/'), 'musicSha256': hashlib.sha256(music_path.read_bytes()).hexdigest(), 'sha256': hashlib.sha256(target.read_bytes()).hexdigest(), 'generator': 'scripts/reels-review/terror-demo-audio.py', 'generatorSha256': hashlib.sha256(Path(__file__).read_bytes()).hexdigest(), 'illustrative': True, 'notOriginalGameAudio': True, 'noise': [1.5, 3.5], 'anticipation': [5.6, 7, 8.1, 8.9], 'silence': [10, 15], 'left': [16, 16.5], 'right': [18.5, 19], 'musicBedEnds': 10}
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print('Created 20-second demonstration: noise, anticipation, silence, left/right.')
