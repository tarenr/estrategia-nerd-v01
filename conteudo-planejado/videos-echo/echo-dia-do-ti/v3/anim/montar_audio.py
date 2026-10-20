import os
import subprocess
import json
import numpy as np
from scipy.io import wavfile

FALA_MP3 = r"C:\Users\WINDOWS\Projects\output\echo-ti-video\audio\fala.mp3"
MUSICA_MP3 = r"C:\Users\WINDOWS\Projects\estrategia-nerd\public\uploads\audio\pixabay_259777.mp3"
OUT_DIR = r"C:\Users\WINDOWS\Projects\output\echo-ti-video\v3\anim\audio"
RAW_MIX_WAV = os.path.join(OUT_DIR, "raw_mix.wav")
FINAL_MIX_WAV = os.path.join(OUT_DIR, "mix.wav")

SAMPLE_RATE = 48000
DURATION = 17.0
VOICE_OFFSET = 0.3
TOTAL_SAMPLES = int(DURATION * SAMPLE_RATE)

def load_audio_pcm(file_path, duration=None, offset=0.0):
    cmd = ["ffmpeg", "-y"]
    if offset > 0:
        cmd += ["-ss", str(offset)]
    cmd += ["-i", file_path]
    if duration:
        cmd += ["-t", str(duration)]
    cmd += ["-f", "s16le", "-ac", "2", "-ar", str(SAMPLE_RATE), "-"]
    proc = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True)
    samples = np.frombuffer(proc.stdout, dtype=np.int16).astype(np.float32) / 32768.0
    return samples.reshape(-1, 2)

def main():
    os.makedirs(OUT_DIR, exist_ok=True)
    print("Carregando voz...")
    voice_samples = load_audio_pcm(FALA_MP3)
    print(f"Voz: {len(voice_samples)/SAMPLE_RATE:.2f}s")

    print("Carregando música...")
    music_samples = load_audio_pcm(MUSICA_MP3, duration=DURATION)
    if len(music_samples) < TOTAL_SAMPLES:
        pad = np.zeros((TOTAL_SAMPLES - len(music_samples), 2), dtype=np.float32)
        music_samples = np.vstack([music_samples, pad])
    else:
        music_samples = music_samples[:TOTAL_SAMPLES]

    # Alinhar voz com offset de 0.3s
    voice_track = np.zeros((TOTAL_SAMPLES, 2), dtype=np.float32)
    offset_samples = int(VOICE_OFFSET * SAMPLE_RATE)
    v_len = min(len(voice_samples), TOTAL_SAMPLES - offset_samples)
    voice_track[offset_samples:offset_samples + v_len] = voice_samples[:v_len]

    # Calcular envelope da voz para ducking suave
    mono_voice = np.mean(np.abs(voice_track), axis=1)
    # Janela RMS de 40ms (~1920 amostras)
    win_size = int(0.04 * SAMPLE_RATE)
    # Convolução com janela retangular
    kernel = np.ones(win_size) / win_size
    smoothed_env = np.convolve(mono_voice, kernel, mode="same")
    max_env = np.percentile(smoothed_env[smoothed_env > 0.01], 95) if np.any(smoothed_env > 0.01) else 0.5
    norm_env = np.clip(smoothed_env / max_env, 0.0, 1.0)

    # Ganho de ducking:
    # Quando fala: ganho cai para ~0.10 (-20dB)
    # Quando em silêncio: ganho sobe para ~0.35 (-9dB)
    # Transições suaves
    ducking_gain = np.zeros(TOTAL_SAMPLES, dtype=np.float32)
    current_gain = 0.35
    attack = 0.005 # ataque rápido quando a voz entra
    release = 0.0005 # liberação suave quando a voz para

    for i in range(TOTAL_SAMPLES):
        target_gain = 0.35 - (0.24 * norm_env[i])
        if target_gain < current_gain:
            current_gain = current_gain * (1.0 - attack) + target_gain * attack
        else:
            current_gain = current_gain * (1.0 - release) + target_gain * release
        ducking_gain[i] = current_gain

    # Fade out no final da música (16.2s a 17.0s)
    fade_start = int(16.2 * SAMPLE_RATE)
    fade_len = TOTAL_SAMPLES - fade_start
    fade_curve = np.linspace(1.0, 0.0, fade_len, dtype=np.float32)
    ducking_gain[fade_start:] *= fade_curve

    # Aplicar ducking na música
    ducked_music = music_samples * ducking_gain[:, None]

    # Mix bruto (voz clara e destacada + música ducked)
    raw_mix = (voice_track * 1.05) + ducked_music

    # Salvar temporário raw
    int_samples = np.clip(raw_mix * 32767.0, -32768.0, 32767.0).astype(np.int16)
    wavfile.write(RAW_MIX_WAV, SAMPLE_RATE, int_samples)
    print("Mix bruto gerado. Normalizando para -15 LUFS ± 1 LU com True Peak <= -1.0 dBFS...")

    # Usar ffmpeg loudnorm para atingir exatamente -15 LUFS e TP <= -1.5 dBFS sem clipping
    norm_cmd = [
        "ffmpeg", "-y", "-i", RAW_MIX_WAV,
        "-af", "loudnorm=I=-15.0:TP=-1.5:LRA=7.0",
        "-c:a", "pcm_s16le", "-ar", str(SAMPLE_RATE),
        FINAL_MIX_WAV
    ]
    subprocess.run(norm_cmd, check=True)
    if os.path.exists(RAW_MIX_WAV):
        os.remove(RAW_MIX_WAV)

    # Medir com ebur128
    measure_cmd = [
        "ffmpeg", "-i", FINAL_MIX_WAV,
        "-filter_complex", "ebur128=peak=true",
        "-f", "null", "-"
    ]
    proc = subprocess.run(measure_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    print("Resultado da medição EBUR128:")
    for line in proc.stderr.splitlines():
        if "Integrated loudness" in line or "True peak" in line or "Loudness range" in line:
            print("  ", line.strip())

if __name__ == "__main__":
    main()
