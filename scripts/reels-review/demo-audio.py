"""Illustrative stereo cues for Reel 231; no audio extracted from games."""
from pathlib import Path
import array
import math
import random
import subprocess
import sys
import wave

root = Path(__file__).resolve().parents[2]
target = root / 'public/uploads/reels/correcao-instagram-20261010/audio/reel-231-demo.wav'
if target.exists():
    raise SystemExit('Existing audio preserved; use a new revision.')
rate = 48000
music = subprocess.check_output(['ffmpeg', '-v', 'error', '-ss', '0', '-i', str(root / 'public/uploads/audio/pixabay_136824.mp3'), '-t', '28', '-f', 's16le', '-ac', '2', '-ar', str(rate), 'pipe:1'])
samples = array.array('h', music)
if sys.byteorder != 'little':
    samples.byteswap()
if len(samples) < 28 * rate * 2:
    raise SystemExit('Music does not cover the complete demonstration.')
rng = random.Random(231)
result = array.array('h')
for frame in range(28 * rate):
    t = frame / rate
    volume = 0.13 if not 14 <= t < 21 else 0
    left = samples[frame * 2] / 32768 * volume
    right = samples[frame * 2 + 1] / 32768 * volume
    if 2 <= t < 5:
        envelope = min(1, (t - 2) / .3, (5 - t) / .3)
        noise = rng.uniform(-1, 1) * .025 * envelope
        left += noise
        right += noise
    for start in (8, 10, 11.5, 12.5):
        if start <= t < start + .18:
            p = (t - start) / .18
            tone = .065 * math.sin(math.pi * p) ** 2 * math.sin(2 * math.pi * 750 * t)
            left += tone
            right += tone
    for start, side in ((22, 'left'), (25, 'right')):
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
target.parent.mkdir(parents=True, exist_ok=True)
with wave.open(str(target), 'wb') as stream:
    stream.setnchannels(2)
    stream.setsampwidth(2)
    stream.setframerate(rate)
    stream.writeframes(result.tobytes())
print('Created 28-second illustrative sound demonstration: noise, anticipation, silence, left/right.')
