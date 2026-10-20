#!/usr/bin/env python3
"""mix_manifesto.py — Mixagem final, ducking dinâmico, normalização EBU R128 e multiplexação.

Executa a mixagem de:
1. Locução guia (voz à frente): locucao-guia.wav (48 kHz estéreo)
2. Música de fundo: pixabay_561438.mp3 (19.25s a 79.25s, fade in 0.2s, fade out 2.0s)
   com ducking dinâmico via sidechaincompress comandado pela narração (voz >= 12 dB acima da música).
3. Efeitos sonoros: sfx_manifesto.wav (48 kHz estéreo)
4. Normalização com loudnorm (alvo I=-16 LUFS, TP=-1.5 dB, LRA=11)
5. Gera mix_manifesto.wav e multiplexa com o vídeo aprovado da legenda (-c:v copy)
   gerando renders/v12_pos/v12_final_previa_540x960.mp4
"""

import sys, os, json, subprocess
from pathlib import Path
import numpy as np

BASE_DIR = Path(r"C:\Users\WINDOWS\Projects\estrategia-nerd\scratch\manifesto-10-10\v3")
EDITORIAL_DIR = BASE_DIR / "editorial-rework"
SOM_DIR = EDITORIAL_DIR / "renders" / "v12_pos" / "som"
V12_POS_DIR = EDITORIAL_DIR / "renders" / "v12_pos"

VOICE_PATH = BASE_DIR / "assets" / "audio" / "locucao-guia.wav"
MUSIC_PATH = Path(r"C:\Users\WINDOWS\Projects\estrategia-nerd\public\uploads\audio\pixabay_561438.mp3")
SFX_PATH = SOM_DIR / "sfx_manifesto.wav"
VIDEO_IN = V12_POS_DIR / "legenda" / "v12_legenda_0_60_540x960.mp4"

OUT_MIX_WAV = SOM_DIR / "mix_manifesto.wav"
OUT_FINAL_MP4 = V12_POS_DIR / "v12_final_previa_540x960.mp4"
WHISPER_PATH = EDITORIAL_DIR / "storyboard" / "whisper_palavras_locucao.json"


def run_cmd(cmd, desc=""):
    print(f"\n[RUN] {desc}...")
    p = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    if p.returncode != 0:
        print(f"[ERRO] Falha em {desc}:", p.stderr.decode("utf-8", errors="ignore"))
        sys.exit(1)
    return p.stdout, p.stderr


def mix_audio():
    print("=== ETAPA 1: Análise e mixagem de áudio ===")
    
    # 1. Medir parâmetros do Loudnorm (Pass 1)
    filter_pass1 = (
        "[0:a]asplit=2[v_mix][v_key];"
        "[1:a]atrim=start=19.25:end=79.25,asetpts=PTS-STARTPTS,volume=0.16,afade=t=in:d=0.2,afade=t=out:st=58.0:d=2.0[m_raw];"
        "[m_raw][v_key]sidechaincompress=threshold=0.025:ratio=10:attack=10:release=220[m_duck];"
        "[2:a]volume=0.85[s];"
        "[v_mix][m_duck][s]amix=inputs=3:duration=first:normalize=0,"
        "loudnorm=I=-16:TP=-1.5:LRA=11:print_format=json"
    )
    
    cmd_p1 = [
        "ffmpeg", "-y",
        "-i", str(VOICE_PATH),
        "-i", str(MUSIC_PATH),
        "-i", str(SFX_PATH),
        "-filter_complex", filter_pass1,
        "-f", "null", "-"
    ]
    _, err_p1 = run_cmd(cmd_p1, "Passo 1 do Loudnorm (Análise EBU R128)")
    err_str = err_p1.decode("utf-8", errors="ignore")
    
    # Extrair JSON da análise
    idx_start = err_str.rfind("{")
    idx_end = err_str.rfind("}")
    if idx_start == -1 or idx_end == -1:
        print("[ERRO] Não foi possível encontrar dados JSON do loudnorm.")
        sys.exit(1)
        
    ln_data = json.loads(err_str[idx_start:idx_end+1])
    print("[Passo 1 Concluído] Parâmetros medidos:")
    print(f"  Input Integrated: {ln_data['input_i']} LUFS")
    print(f"  Input True Peak:   {ln_data['input_tp']} dBTP")
    print(f"  Input LRA:         {ln_data['input_lra']} LU")
    print(f"  Input Threshold:   {ln_data['input_thresh']} LUFS")
    print(f"  Target Offset:     {ln_data['target_offset']} LU")
    
    # 2. Renderizar mix_manifesto.wav com Loudnorm linear de dois passos (Pass 2)
    filter_pass2 = (
        "[0:a]asplit=2[v_mix][v_key];"
        "[1:a]atrim=start=19.25:end=79.25,asetpts=PTS-STARTPTS,volume=0.16,afade=t=in:d=0.2,afade=t=out:st=58.0:d=2.0[m_raw];"
        "[m_raw][v_key]sidechaincompress=threshold=0.025:ratio=10:attack=10:release=220[m_duck];"
        "[2:a]volume=0.85[s];"
        "[v_mix][m_duck][s]amix=inputs=3:duration=first:normalize=0,"
        f"loudnorm=I=-16:TP=-1.5:LRA=11:measured_I={ln_data['input_i']}:measured_TP={ln_data['input_tp']}:"
        f"measured_LRA={ln_data['input_lra']}:measured_thresh={ln_data['input_thresh']}:"
        f"offset={ln_data['target_offset']}:linear=true[out_a]"
    )
    
    cmd_p2 = [
        "ffmpeg", "-y",
        "-i", str(VOICE_PATH),
        "-i", str(MUSIC_PATH),
        "-i", str(SFX_PATH),
        "-filter_complex", filter_pass2,
        "-map", "[out_a]",
        "-c:a", "pcm_s16le",
        "-ar", "48000",
        str(OUT_MIX_WAV)
    ]
    run_cmd(cmd_p2, "Passo 2 do Loudnorm (Gerando mix_manifesto.wav)")
    print(f"[OK] Áudio final gerado em: {OUT_MIX_WAV}")


def generate_final_preview():
    print("\n=== ETAPA 2: Multiplexação da Prévia Final ===")
    cmd_mux = [
        "ffmpeg", "-y",
        "-i", str(VIDEO_IN),
        "-i", str(OUT_MIX_WAV),
        "-map", "0:v:0",
        "-map", "1:a:0",
        "-c:v", "copy",
        "-c:a", "aac",
        "-b:a", "256k",
        "-shortest",
        str(OUT_FINAL_MP4)
    ]
    run_cmd(cmd_mux, "Multiplexação de vídeo (copy) + áudio AAC (256k)")
    print(f"[OK] Vídeo final gerado: {OUT_FINAL_MP4}")


def measure_ebur128(file_path):
    print(f"\n[EBU R128] Medindo {file_path.name}...")
    cmd = [
        "ffmpeg", "-i", str(file_path),
        "-af", "ebur128=peak=true",
        "-f", "null", "-"
    ]
    p = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    err = p.stderr.decode("utf-8", errors="ignore")
    
    # Extrair summary
    res = {}
    lines = err.splitlines()
    for i, line in enumerate(lines):
        if "Integrated loudness:" in line:
            for sub in lines[i:i+5]:
                if "I:" in sub:
                    res["integrated_lufs"] = float(sub.split("I:")[1].split("LUFS")[0].strip())
        if "True peak:" in line:
            for sub in lines[i:i+5]:
                if "Peak:" in sub:
                    res["true_peak_db"] = float(sub.split("Peak:")[1].split("dBFS")[0].strip())
        if "Loudness range:" in line:
            for sub in lines[i:i+5]:
                if "LRA:" in sub:
                    res["lra_lu"] = float(sub.split("LRA:")[1].split("LU")[0].strip())
    return res


def measure_ducking_difference():
    print("\n[DUCKING] Medindo separação Voz x Música durante trechos de fala...")
    # Extrair música com ducking isolada
    filter_duck = (
        "[1:a]atrim=start=19.25:end=79.25,asetpts=PTS-STARTPTS,volume=0.16,afade=t=in:d=0.2,afade=t=out:st=58.0:d=2.0[m_raw];"
        "[m_raw][0:a]sidechaincompress=threshold=0.025:ratio=10:attack=10:release=220[ducked_m]"
    )
    cmd_m = [
        "ffmpeg", "-y", "-i", str(VOICE_PATH), "-i", str(MUSIC_PATH),
        "-filter_complex", filter_duck,
        "-map", "[ducked_m]",
        "-f", "s16le", "-ac", "1", "-ar", "48000", "-"
    ]
    pm = subprocess.run(cmd_m, stdout=subprocess.PIPE, stderr=subprocess.DEVNULL)
    ducked_music = np.frombuffer(pm.stdout, dtype=np.int16).astype(np.float32)

    cmd_v = ["ffmpeg", "-y", "-i", str(VOICE_PATH), "-f", "s16le", "-ac", "1", "-ar", "48000", "-"]
    pv = subprocess.run(cmd_v, stdout=subprocess.PIPE, stderr=subprocess.DEVNULL)
    voice = np.frombuffer(pv.stdout, dtype=np.int16).astype(np.float32)

    with open(WHISPER_PATH, "r", encoding="utf-8") as f:
        wdata = json.load(f)

    sr = 48000
    n = min(len(voice), len(ducked_music))
    speech_mask = np.zeros(n, dtype=bool)
    for w in wdata["palavras"]:
        i0 = int(w["ini"] * sr)
        i1 = min(n, int(w["fim"] * sr))
        speech_mask[i0:i1] = True

    v_speech = voice[:n][speech_mask]
    m_speech = ducked_music[:n][speech_mask]

    rms_v = np.sqrt(np.mean(v_speech ** 2.0))
    rms_m = np.sqrt(np.mean(m_speech ** 2.0))

    db_v = 20.0 * np.log10(rms_v / 32768.0)
    db_m = 20.0 * np.log10(rms_m / 32768.0)
    diff = db_v - db_m
    return {
        "voice_rms_dbfs": round(db_v, 2),
        "music_rms_dbfs": round(db_m, 2),
        "diff_db": round(diff, 2),
        "speech_duration_s": round(float(np.sum(speech_mask)) / sr, 2)
    }


def verify_integrity():
    print("\n=== ETAPA 3: Bateria de Testes e Validação ===")
    
    # Teste A: ffprobe e decodificação completa sem erros
    print("[TESTE A] Verificando formato do vídeo final e integridade...")
    cmd_probe = [
        "ffprobe", "-v", "error",
        "-show_entries", "format=duration,size,bit_rate:stream=codec_name,width,height,r_frame_rate,duration",
        "-of", "json",
        str(OUT_FINAL_MP4)
    ]
    p = subprocess.run(cmd_probe, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    probe_info = json.loads(p.stdout.decode("utf-8"))
    
    # Decodificação completa para garantir zero erros
    cmd_check = ["ffmpeg", "-v", "error", "-i", str(OUT_FINAL_MP4), "-f", "null", "-"]
    p_check = subprocess.run(cmd_check, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    decode_errors = p_check.stderr.decode("utf-8").strip()
    
    # Teste B: ebur128 do áudio final
    print("[TESTE B] Medição EBU R128 do áudio final...")
    ebu_mix = measure_ebur128(OUT_MIX_WAV)
    ebu_mp4 = measure_ebur128(OUT_FINAL_MP4)
    
    # Teste C: Diferença voz x música durante fala
    print("[TESTE C] Medição da separação entre voz e música durante a fala...")
    duck_stats = measure_ducking_difference()
    
    # Resultados consolidados
    print("\n" + "=" * 60)
    print("RELATÓRIO DE RESULTADOS TÉCNICOS:")
    print("=" * 60)
    print("TESTE A — FORMATO E DECODIFICAÇÃO:")
    for s in probe_info["streams"]:
        print(f"  Stream {s.get('codec_name')}: dur={s.get('duration')}s, fps={s.get('r_frame_rate', 'N/A')}, {s.get('width','')}x{s.get('height','')}")
    print(f"  Duração do contêiner: {probe_info['format']['duration']} s")
    print(f"  Erros de decodificação: {'Nenhum erro (0)' if not decode_errors else decode_errors}")
    
    print("\nTESTE B — EBU R128 (LOUDNORM):")
    print(f"  mix_manifesto.wav -> I: {ebu_mix.get('integrated_lufs')} LUFS, TP: {ebu_mix.get('true_peak_db')} dBTP, LRA: {ebu_mix.get('lra_lu')} LU")
    print(f"  v12_final_previa_540x960.mp4 -> I: {ebu_mp4.get('integrated_lufs')} LUFS, TP: {ebu_mp4.get('true_peak_db')} dBTP, LRA: {ebu_mp4.get('lra_lu')} LU")
    
    print("\nTESTE C — SEPARAÇÃO VOZ × MÚSICA:")
    print(f"  Duração total de fala avaliada: {duck_stats['speech_duration_s']} s")
    print(f"  Nível RMS da voz na fala: {duck_stats['voice_rms_dbfs']} dBFS")
    print(f"  Nível RMS da música na fala: {duck_stats['music_rms_dbfs']} dBFS")
    print(f"  Diferença (Voz - Música): {duck_stats['diff_db']} dB (Critério >= 12 dB: {'APROVADO' if duck_stats['diff_db'] >= 12.0 else 'REPROVADO'})")
    print("=" * 60)


if __name__ == "__main__":
    mix_audio()
    generate_final_preview()
    verify_integrity()
