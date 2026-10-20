import os
import subprocess

VIDEO_MP4 = r"C:\Users\WINDOWS\Projects\output\echo-ti-video\v3\anim\render\echo-dia-do-ti-v3.mp4"
CONF_DIR = r"C:\Users\WINDOWS\Projects\output\echo-ti-video\v3\anim\conferencia"

os.makedirs(CONF_DIR, exist_ok=True)

quadros_para_extrair = [
    # 1 por cena
    ("cena_C01.png", 1.40),
    ("cena_C02.png", 2.80),
    ("cena_C03.png", 3.70),
    ("cena_C04.png", 4.55),
    ("cena_C05.png", 6.15),
    ("cena_C06.png", 7.80),
    ("cena_C07.png", 8.85),
    ("cena_C08.png", 9.90),
    ("cena_C09.png", 12.00),
    ("cena_C10.png", 15.50),
    # Pontos críticos
    ("critico_C01_mao_chegando.png", 0.90),
    ("critico_C03_encaixe_led.png", 3.85),
    ("critico_C06_plugue_apagao_echo_visivel.png", 8.20),
    ("critico_C07_luzes_voltando.png", 9.00),
    ("critico_C09_digitacao_terminal.png", 11.20),
    # Boca aberta vs fechada
    ("boca_aberta_palavra.png", 1.43),
    ("boca_fechada_pausa.png", 8.85),
    ("boca_fechada_fecho.png", 16.20)
]

print("Extraindo quadros de conferência diretamente do vídeo MP4...")
for nome, t in quadros_para_extrair:
    out_path = os.path.join(CONF_DIR, nome)
    cmd = [
        "ffmpeg", "-y", "-ss", f"{t:.3f}",
        "-i", VIDEO_MP4,
        "-vframes", "1",
        "-q:v", "2",
        out_path
    ]
    subprocess.run(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, check=True)
    size = os.path.getsize(out_path)
    print(f"  [OK] {nome} aos {t:.2f}s ({size} bytes)")

print("\nTodos os quadros de conferência foram extraídos com sucesso!")
