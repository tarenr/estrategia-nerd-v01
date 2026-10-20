import subprocess
import json
import numpy as np

AUDIO_FILE = r'C:\Users\WINDOWS\Projects\output\echo-ti-video\audio\fala.mp3'
OUTPUT_JSON = r'C:\Users\WINDOWS\Projects\output\echo-ti-video\v3\boca.json'

FPS = 30
VIDEO_DURATION = 17.0 # segundos
AUDIO_OFFSET = 0.3    # segundos (a voz entra aos 0.3s)
SAMPLE_RATE = 16000

# 1. Extrair PCM 16-bit mono 16kHz
cmd = [
    'ffmpeg', '-y', '-i', AUDIO_FILE,
    '-f', 's16le', '-ac', '1', '-ar', str(SAMPLE_RATE),
    '-'
]
proc = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True)
audio_samples = np.frombuffer(proc.stdout, dtype=np.int16).astype(np.float32)
audio_duration = len(audio_samples) / SAMPLE_RATE
print(f"Áudio carregado: {len(audio_samples)} amostras ({audio_duration:.3f} s)")

total_frames = int(VIDEO_DURATION * FPS)
raw_envelope = np.zeros(total_frames, dtype=np.float32)

samples_per_frame = SAMPLE_RATE / FPS

for frame in range(total_frames):
    t_start = frame / FPS - AUDIO_OFFSET
    t_end = (frame + 1) / FPS - AUDIO_OFFSET
    
    if t_end <= 0 or t_start >= audio_duration:
        raw_envelope[frame] = 0.0
        continue
    
    idx_start = max(0, int(t_start * SAMPLE_RATE))
    idx_end = min(len(audio_samples), int(t_end * SAMPLE_RATE))
    
    if idx_end > idx_start:
        chunk = audio_samples[idx_start:idx_end]
        rms = np.sqrt(np.mean(chunk ** 2))
        raw_envelope[frame] = rms

# Normalização
# Usar percentil 92 dos trechos falados para normalizar
speaking_mask = raw_envelope > 500
if np.any(speaking_mask):
    peak_rms = np.percentile(raw_envelope[speaking_mask], 92)
else:
    peak_rms = np.max(raw_envelope) or 1.0

norm_envelope = raw_envelope / peak_rms
norm_envelope = np.clip(norm_envelope, 0.0, 1.0)

# Suavização suave (envelope de ataque e decaimento)
smoothed = np.zeros_like(norm_envelope)
val = 0.0
for i, x in enumerate(norm_envelope):
    if x > val:
        val = val * 0.3 + x * 0.7 # ataque rápido
    else:
        val = val * 0.65 + x * 0.35 # decaimento suave
    # Se muito baixo (ruído de fundo), zera
    if val < 0.08:
        val = 0.0
    smoothed[i] = val

smoothed = np.clip(smoothed, 0.0, 1.0)
boca_list = [round(float(v), 3) for v in smoothed]

with open(OUTPUT_JSON, 'w', encoding='utf-8') as f:
    json.dump(boca_list, f, indent=2)

print(f"Salvo {OUTPUT_JSON} com {len(boca_list)} quadros.")

# Validar cenas 1, 6, 9 e 10
cenas_teste = [
    ("Cena 1 (0.0-2.3s)", 0.0, 2.3),
    ("Cena 6 (7.4-8.6s)", 7.4, 8.6),
    ("Cena 9 (10.7-13.1s)", 10.7, 13.1),
    ("Cena 10 (13.1-17.0s)", 13.1, 17.0),
]

print("\n--- Verificação de quadros com boca aberta (>= 0.6) ---")
instantes_escolhidos = {}
for nome, t0, t1 in cenas_teste:
    f0 = int(t0 * FPS)
    f1 = int(t1 * FPS)
    candidatos = [(f, f / FPS, boca_list[f]) for f in range(f0, f1) if boca_list[f] >= 0.6]
    if candidatos:
        # Pega o melhor (maior volume no miolo do intervalo)
        melhor = max(candidatos, key=lambda c: c[2])
        instantes_escolhidos[nome] = melhor
        print(f"{nome}: Encontrados {len(candidatos)} quadros >= 0.6! Melhor: frame {melhor[0]} aos {melhor[1]:.2f}s com boca={melhor[2]}")
    else:
        max_no_intervalo = max([(f, f / FPS, boca_list[f]) for f in range(f0, f1)], key=lambda c: c[2])
        print(f"ATENÇÃO: {nome} não teve >= 0.6! Máximo foi {max_no_intervalo}")

