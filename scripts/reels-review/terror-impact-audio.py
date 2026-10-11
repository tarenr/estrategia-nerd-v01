"""Reel 231 v4: tension, brief silence, designed horror impact, music return."""
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
target = root / 'resources/reels-review/assets/terror/audio/reel-231-impact-v4.wav'
manifest_path = target.with_suffix('.json')
if target.exists() or manifest_path.exists():
    raise SystemExit('Existing revision preserved; choose a new output.')
rate, duration = 48000, 20
impact_frame = 640000  # First frame of image 3: (400 / 30) * 48000.
impact_time = impact_frame / rate
samples = array.array('h', subprocess.check_output(['ffmpeg', '-v', 'error', '-i', str(music_path), '-t', str(duration), '-f', 's16le', '-ac', '2', '-ar', str(rate), 'pipe:1']))
if sys.byteorder != 'little':
    samples.byteswap()
if len(samples) < duration * rate * 2:
    raise SystemExit('Music is shorter than the reel.')
rng = random.Random(2314)
result = array.array('h')
noise_low, phase = 0.0, 0.0
for frame in range(duration * rate):
    t = frame / rate
    pre = min(1, t / .45, max(0, (12.5 - t) / .35)) if t < 12.5 else 0
    returned = min(1, max(0, (t - 14.15) / .65))
    music_gain = 1.1 * max(pre, returned)
    left, right = (samples[frame * 2 + side] / 32768 * music_gain for side in (0, 1))
    # Low beating drone plus a filtered, increasingly dense air/metal riser.
    if t < 12.5:
        tension = min(1, max(0, (t - 1) / 11.15))
        noise_low = .84 * noise_low + .16 * rng.uniform(-1, 1)
        phase += 2 * math.pi * (170 + 1430 * tension ** 3) / rate
        drone = .025 * tension * (math.sin(2 * math.pi * 57 * t) + .6 * math.sin(2 * math.pi * 60.3 * t))
        riser = (.045 * noise_low + .035 * math.sin(phase)) * tension ** 2
        cue = (drone + riser) * pre
        left += cue
        right += cue
    # Sub-bass hit, noisy transient and inharmonic metallic tail form one event.
    p = t - impact_time
    if 0 <= p < 1.5:
        attack = min(1, p / .003)
        transient = .27 * math.exp(-p / .075) * rng.uniform(-1, 1)
        bass = .23 * math.exp(-p / .35) * math.sin(2 * math.pi * (96 * p - 14 * p * p))
        metal = .16 * math.exp(-p / .65) * (math.sin(2 * math.pi * 1130 * p) + .5 * math.sin(2 * math.pi * 1477 * p))
        rumble = .10 * math.exp(-p / .5) * math.sin(2 * math.pi * 52 * p)
        impact = attack * (transient + bass + metal + rumble)
        left += impact
        right += impact
    # Soft saturation bounds the designed transient; no full-scale clipping.
    result.extend(round(.42 * math.tanh(value / .42) * 32767) for value in (left, right))
if sys.byteorder != 'little':
    result.byteswap()
with wave.open(str(target), 'wb') as stream:
    stream.setnchannels(2)
    stream.setsampwidth(2)
    stream.setframerate(rate)
    stream.writeframes(result.tobytes())
manifest = {'duration': duration, 'rate': rate, 'channels': 2, 'music': music_path.relative_to(root).as_posix(), 'musicSha256': hashlib.sha256(music_path.read_bytes()).hexdigest(), 'sha256': hashlib.sha256(target.read_bytes()).hexdigest(), 'generator': 'scripts/reels-review/terror-impact-audio.py', 'generatorSha256': hashlib.sha256(Path(__file__).read_bytes()).hexdigest(), 'illustrative': True, 'notOriginalGameAudio': True, 'tension': [1, 12.5], 'silence': [12.5, impact_time], 'impact': {'time': impact_time, 'sample': impact_frame, 'videoFrame': 400, 'layers': ['sub-bass hit', 'noise transient', 'inharmonic metal', 'low rumble'], 'end': impact_time + 1.5}, 'musicReturn': [14.15, 14.8], 'musicBedEnds': 20, 'peakBound': .42}
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print('Created 20-second horror sequence; impact synced to image 3 at frame 400.')
