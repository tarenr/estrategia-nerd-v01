import subprocess
import numpy as np

VIDEO_MP4 = r"C:\Users\WINDOWS\Projects\output\echo-ti-video\v3\anim\render\echo-dia-do-ti-v3.mp4"

# Extrair todos os 510 frames em resolução 270x480 para análise rápida de movimento
cmd = [
    "ffmpeg", "-i", VIDEO_MP4,
    "-vf", "scale=270:480",
    "-f", "rawvideo", "-pix_fmt", "gray",
    "-"
]
proc = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, check=True)
data = np.frombuffer(proc.stdout, dtype=np.uint8).reshape((-1, 480, 270))
num_frames = len(data)
print(f"Total de frames analisados: {num_frames}")

deltas = []
for i in range(num_frames - 1):
    diff = np.abs(data[i+1].astype(int) - data[i].astype(int)).mean()
    deltas.append(diff)

deltas = np.array(deltas)

# Analisar por cena
cenas = [
    ("C01 (0.0-2.3s)", 0, int(2.3 * 30)),
    ("C02 (2.3-3.3s)", int(2.3 * 30), int(3.3 * 30)),
    ("C03 (3.3-4.2s)", int(3.3 * 30), int(4.2 * 30)),
    ("C04 (4.2-4.9s)", int(4.2 * 30), int(4.9 * 30)),
    ("C05 (4.9-7.4s)", int(4.9 * 30), int(7.4 * 30)),
    ("C06 (7.4-8.6s)", int(7.4 * 30), int(8.6 * 30)),
    ("C07 (8.6-9.1s)", int(8.6 * 30), int(9.1 * 30)),
    ("C08 (9.1-10.7s)", int(9.1 * 30), int(10.7 * 30)),
    ("C09 (10.7-13.1s)", int(10.7 * 30), int(13.1 * 30)),
    ("C10 (13.1-17.0s)", int(13.1 * 30), 509),
]

print("\n--- Análise Honestidade de Movimento (Média de variação pixel a pixel) ---")
for nome, f0, f1 in cenas:
    slice_d = deltas[f0:f1]
    # Ignorar o frame do corte (primeiro frame)
    if len(slice_d) > 1:
        motion_inner = slice_d[1:]
        avg_motion = motion_inner.mean()
        min_motion = motion_inner.min()
        max_motion = motion_inner.max()
        still_frames = np.sum(motion_inner < 0.05)
        print(f"{nome}: Média={avg_motion:.2f}, Min={min_motion:.2f}, Max={max_motion:.2f}, Frames Quase Estáticos={still_frames}/{len(motion_inner)}")
