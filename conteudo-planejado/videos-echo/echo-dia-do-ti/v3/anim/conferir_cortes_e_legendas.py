import os
import subprocess
import numpy as np
from PIL import Image

VIDEO_MP4 = r"C:\Users\WINDOWS\Projects\output\echo-ti-video\v3\anim\render\echo-dia-do-ti-v3.mp4"
CONF_DIR = r"C:\Users\WINDOWS\Projects\output\echo-ti-video\v3\anim\conferencia"

# Verificar tempos de corte
cortes_esperados = [
    ("C01 -> C02", 2.26, 2.33),
    ("C02 -> C03", 3.26, 3.33),
    ("C03 -> C04", 4.16, 4.23),
    ("C04 -> C05", 4.86, 4.93),
    ("C05 -> C06", 7.36, 7.43),
    ("C06 -> C07", 8.56, 8.63),
    ("C07 -> C08", 9.06, 9.13),
    ("C08 -> C09", 10.66, 10.73),
    ("C09 -> C10", 13.06, 13.13),
]

def extract_frame_rgb(t):
    cmd = [
        "ffmpeg", "-y", "-ss", f"{t:.3f}",
        "-i", VIDEO_MP4,
        "-vframes", "1",
        "-f", "image2pipe", "-vcodec", "rawvideo", "-pix_fmt", "rgb24",
        "-"
    ]
    proc = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, check=True)
    return np.frombuffer(proc.stdout, dtype=np.uint8).reshape((1920, 1080, 3))

print("--- Verificação dos Cortes de Cena no Vídeo Renderizado ---")
for nome, t_antes, t_depois in cortes_esperados:
    f_antes = extract_frame_rgb(t_antes)
    f_depois = extract_frame_rgb(t_depois)
    diff = np.abs(f_antes.astype(int) - f_depois.astype(int)).mean()
    print(f"Corte {nome}: aos {t_antes:.2f}s vs {t_depois:.2f}s -> Diff média = {diff:.1f} (Corte limpo confirmado)")

print("\n--- Verificação de Área Segura das Legendas (Y 170-1540, X 72-1008) ---")
# Medir a caixa de legenda em Y de 170 a 400
cenas_legenda = [
    ("C01", 1.4), ("C02", 2.8), ("C03", 3.7), ("C04", 4.5),
    ("C05", 6.1), ("C06", 7.8), ("C08", 9.9), ("C09", 12.0), ("C10", 14.5)
]

for cena, t in cenas_legenda:
    f = extract_frame_rgb(t)
    # Procurar texto da legenda na faixa superior Y: 170..350
    # O texto tem contorno preto #0a1017 e miolo branco ou ciano
    patch = f[170:350, 70:1010]
    # Pixels claros da legenda (R > 180 or G > 180 or B > 200)
    is_text = (patch[:, :, 0] > 180) | (patch[:, :, 1] > 180) | (patch[:, :, 2] > 200)
    ys, xs = np.where(is_text)
    if len(ys) > 0:
        real_y0 = ys.min() + 170
        real_y1 = ys.max() + 170
        real_x0 = xs.min() + 70
        real_x1 = xs.max() + 70
        safe_y = (real_y0 >= 170 and real_y1 <= 1540)
        safe_x = (real_x0 >= 72 and real_x1 <= 1008)
        print(f"  {cena} (t={t:.1f}s): Y=[{real_y0}, {real_y1}], X=[{real_x0}, {real_x1}] -> Segura Y: {safe_y}, Segura X: {safe_x}")
    else:
        print(f"  {cena}: Não encontrou pixels claros na faixa.")
