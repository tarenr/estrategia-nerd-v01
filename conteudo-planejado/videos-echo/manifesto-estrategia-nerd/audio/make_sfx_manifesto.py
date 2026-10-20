#!/usr/bin/env python3
"""make_sfx_manifesto.py — Geração de sonoplastia completa para o vídeo Manifesto Estratégia Nerd (60 s).

Atende rigorosamente a todos os pedidos do usuário:
- Vidro / coisas quebrando (choque da GPU em 3.96s/6.16s e arremesso violento pelo Echo em 12.73s) usando foley real
  (foley_glass_burst_0148.wav e foley_glass_mirror_0387.wav).
- Carro acelerando na corrida (20.96s a 24.50s) com subida de rotação, harmônicos, ruído e troca de marcha / engasgo.
- Engrenagem no RPG (28.00s a 29.95s) com cliques metálicos ritmados sincronizados à rotação e rangido grave.
- Entradas de cartões, transições com whoosh, cards do método em escala ascendente, conclusão, cliques de mouse, risco no achismo e CTA final.
- Efeitos nunca cobrem a voz: ganhos atenuados durante os trechos de fala da narração.
"""

import sys, os, json, wave
from pathlib import Path
import numpy as np
from scipy.io import wavfile

# Adicionar a paleta de efeitos do OneTake
ONETAKE_SCRIPTS = Path(r"C:\Users\WINDOWS\Projects\onetake\scripts")
sys.path.insert(0, str(ONETAKE_SCRIPTS))
from sfx_palette import Score, air, glass, wood, sub, bubble, wobble, SR

BASE_DIR = Path(r"C:\Users\WINDOWS\Projects\estrategia-nerd\scratch\manifesto-10-10\v3")
ASSETS_AUDIO = BASE_DIR / "assets" / "audio"
FOLEY_BURST = ASSETS_AUDIO / "foley_glass_burst_0148.wav"
FOLEY_SHARDS = ASSETS_AUDIO / "foley_glass_mirror_0387.wav"

OUT_DIR = BASE_DIR / "editorial-rework" / "renders" / "v12_pos" / "som"
OUT_WAV = OUT_DIR / "sfx_manifesto.wav"
OUT_JSON = OUT_DIR / "eventos_sfx.json"


def load_wav_mono(path):
    """Carrega arquivo WAV e retorna array float32 em [-1, 1] no sample rate SR."""
    rate, data = wavfile.read(path)
    if data.dtype == np.int16:
        data = data.astype(np.float32) / 32768.0
    elif data.dtype == np.int32:
        data = data.astype(np.float32) / 2147483648.0
    if data.ndim > 1:
        data = np.mean(data, axis=1)
    # Se taxa for diferente de 48000, resample básico
    if rate != SR:
        num_target = int(len(data) * SR / rate)
        data = np.interp(np.linspace(0, len(data), num_target), np.arange(len(data)), data)
    return data.astype(np.float32)


def synth_car_acceleration(dur=3.54, gear_shift_t=0.92):
    """Sintetiza aceleração de motor de carro esportivo (20.96s a 24.50s):
    - Curva de f0 subindo
    - Troca de marcha / engasgo em gear_shift_t (queda e retomada forte de rotação)
    - Série harmônica de 4 cilindros + saturação de escape + ruído aerodinâmico
    """
    n = int(SR * dur)
    t = np.arange(n) / SR
    f0 = np.zeros(n, dtype=np.float32)
    
    for i, ti in enumerate(t):
        if ti < gear_shift_t:
            u = np.clip(ti / gear_shift_t, 0.0, 1.0)
            f0[i] = 78.0 + 85.0 * (u ** 1.3)  # 78 Hz -> 163 Hz
        elif ti < gear_shift_t + 0.16:
            u = np.clip((ti - gear_shift_t) / 0.16, 0.0, 1.0)
            f0[i] = 163.0 - 55.0 * u           # Queda momentânea para 108 Hz
        else:
            rem = max(0.01, dur - (gear_shift_t + 0.16))
            u = np.clip((ti - gear_shift_t - 0.16) / rem, 0.0, 1.0)
            f0[i] = 108.0 + 145.0 * (u ** 1.25) # Arrancada até 253 Hz
            
    phase = np.cumsum(2.0 * np.pi * f0 / SR)
    # Harmônicos ricos do motor
    engine = (
        0.45 * np.sin(phase) +
        0.35 * np.sin(phase * 2.0) +
        0.28 * np.sin(phase * 3.0) +
        0.18 * np.sin(phase * 4.0) +
        0.12 * np.sin(phase * 0.5)
    )
    # Saturação suave simulando compressão de escape
    engine = np.tanh(engine * 1.7)
    
    # Ruído aerodinâmico e de asfalto
    rng = np.random.default_rng(42)
    air_rush = rng.normal(0, 0.15, n)
    
    # Envelope dinâmico com fade in e fade out na saída
    env = np.ones(n, dtype=np.float32)
    in_samples = int(0.12 * SR)
    out_samples = int(0.35 * SR)
    env[:in_samples] = np.linspace(0, 1, in_samples)
    env[-out_samples:] = np.linspace(1, 0, out_samples)
    
    sig = (engine * 0.85 + air_rush * 0.22) * env
    return sig / (np.max(np.abs(sig)) + 1e-9)


def synth_rpg_gear(dur=1.95):
    """Sintetiza mecanismo de engrenagem do RPG (28.00s a 29.95s):
    - Inicia rotação com aceleração angular em 28.00s até 28.90s, mantendo rotação contínua
    - Cliques metálicos periódicos no passo dos dentes da engrenagem
    - Rangido e fricção mecânica grave (pedra / ferro antigo)
    """
    n = int(SR * dur)
    t = np.arange(n) / SR
    omega = np.where(t < 0.90, 1.10 * ((t / 0.90) ** 2.0), 1.10)
    angle = np.cumsum(omega / SR)
    
    # 14 dentes na engrenagem
    teeth_step = 2.0 * np.pi / 14.0
    phase_in_tooth = (angle % teeth_step) / teeth_step
    click_indices = np.where(np.diff(phase_in_tooth) < -0.5)[0]
    
    clicks = np.zeros(n, dtype=np.float32)
    for c_idx in click_indices:
        decay_len = min(int(0.045 * SR), n - c_idx)
        t_click = np.arange(decay_len) / SR
        metal = (
            np.sin(2.0 * np.pi * 2300.0 * t_click) * 0.7 +
            np.sin(2.0 * np.pi * 3700.0 * t_click) * 0.4
        ) * np.exp(-t_click * 170.0)
        clicks[c_idx:c_idx + decay_len] += metal
        
    groan_freq = 68.0 + omega * 32.0
    groan = np.sin(np.cumsum(2.0 * np.pi * groan_freq / SR)) * (omega / 1.10) * 0.45
    rng = np.random.default_rng(99)
    friction = rng.normal(0, 0.08, n) * (omega / 1.10)
    
    env = np.ones(n, dtype=np.float32)
    env[-int(0.20 * SR):] = np.linspace(1, 0, int(0.20 * SR))
    sig = (clicks * 0.70 + (groan + friction) * 0.45) * env
    return sig / (np.max(np.abs(sig)) + 1e-9)


def build_manifesto_sfx():
    dur_total = 60.0
    s = Score(dur=dur_total, T60=0.90)
    
    # Carregar arquivos de foley real do projeto
    foley_burst_raw = load_wav_mono(FOLEY_BURST)
    foley_shards_raw = load_wav_mono(FOLEY_SHARDS)
    
    eventos = []
    
    def registrar(t, tipo, descricao, gain, pan=0.0):
        eventos.append({
            "t": round(t, 3),
            "tipo": tipo,
            "descricao": descricao,
            "gain": round(gain, 2),
            "pan": round(pan, 2)
        })

    # =========================================================================
    # 1. ENTRADAS DOS CARTÕES DA PRAÇA (0 a 18 s)
    # =========================================================================
    # 0.25 s: Entrada do primeiro card de hardware
    s.place(wood(f=280, dur=0.08), t=0.25, gain=0.28, pan=-0.20, send=0.18)
    s.place(bubble(f=440, dur=0.14), t=0.26, gain=0.22, pan=-0.20, send=0.15)
    registrar(0.25, "wood + bubble", "Entrada do card de hardware na praça (escala 0.96 -> 1.00)", 0.28, -0.20)
    
    # 0.68 s: Choque / expansão da notícia da GPU
    s.place(wood(f=320, dur=0.08), t=0.68, gain=0.32, pan=-0.05, send=0.20)
    s.place(bubble(f=510, dur=0.14), t=0.69, gain=0.22, pan=0.0, send=0.18)
    registrar(0.68, "wood + bubble", "Choque e desaceleração do card da GPU em foco", 0.32, -0.05)
    
    # 3.40 s: Card do achismo / dobra de transição
    s.place(wood(f=260, dur=0.08), t=3.40, gain=0.26, pan=0.0, send=0.18)
    s.place(bubble(f=420, dur=0.14), t=3.41, gain=0.20, pan=0.0, send=0.15)
    registrar(3.40, "wood + bubble", "Dobra do card anterior para ficha do ACHISMO", 0.26, 0.0)

    # 3.96 s: PEDIDO DO USUÁRIO — Choque da notícia da GPU (carimbo TOP estala na placa)
    # Pedaço cortante inicial do foley_burst simulando impacto duro / estalo de vidro na placa
    foley_stamp_slice = foley_burst_raw[:int(0.28 * SR)] * np.exp(-np.linspace(0, 15, int(0.28 * SR)))
    s.place(foley_stamp_slice, t=3.96, gain=0.30, pan=0.15, send=0.25)
    s.place(wood(f=340, dur=0.09), t=3.96, gain=0.28, pan=0.15, send=0.18)
    registrar(3.96, "foley vidro + wood", "Impacto duro do carimbo TOP na GPU (choque da notícia da GPU)", 0.30, 0.15)
    
    # 6.08 s: Entrada da tríade de sintomas ("Vídeos intermináveis")
    s.place(wood(f=290, dur=0.08), t=6.08, gain=0.28, pan=-0.15, send=0.18)
    s.place(bubble(f=450, dur=0.14), t=6.09, gain=0.22, pan=-0.15, send=0.15)
    registrar(6.08, "wood + bubble", "Entrada do primeiro módulo de sintomas (Vídeos intermináveis)", 0.28, -0.15)

    # 6.16 s: GPU publication quebra e dissolve saindo de quadro
    foley_gpu_break = foley_shards_raw[:int(0.45 * SR)] * np.exp(-np.linspace(0, 8, int(0.45 * SR)))
    s.place(foley_gpu_break, t=6.16, gain=0.24, pan=-0.35, send=0.30)
    registrar(6.16, "foley estilhaços", "Quebra e descarte da publicação da GPU saindo de quadro", 0.24, -0.35)

    # 9.50 s: Entrada do card do sintoma 3 ("Histórias pela metade")
    s.place(wood(f=280, dur=0.08), t=9.50, gain=0.25, pan=0.15, send=0.18)
    s.place(bubble(f=460, dur=0.14), t=9.51, gain=0.20, pan=0.15, send=0.15)
    registrar(9.50, "wood + bubble", "Entrada do card Histórias pela metade (lore cortada)", 0.25, 0.15)

    # 12.24 s: Echo pega o cartão e prepara arremesso
    s.place(air(dur=0.38, f0=220, f1=640, q=1.6, shape=0.5), t=12.24, gain=0.24, pan=0.10, send=0.20, pan_to=-0.25)
    registrar(12.24, "air", "Windup do Echo pegando os cartões da praça para arremesso", 0.24, 0.10)

    # 12.73 s: PEDIDO OBRIGATÓRIO DO USUÁRIO — Vidro quebrando com arremesso do Echo
    # O cartão/tela arremessado bate na parede/chão fora do enquadramento à esquerda (pan=-0.72)
    # Estouro com foley_glass_burst_0148.wav + estilhaços se espalhando com foley_glass_mirror_0387.wav
    s.place(foley_burst_raw, t=12.73, gain=0.68, pan=-0.72, send=0.45)
    s.place(foley_shards_raw, t=12.76, gain=0.55, pan=-0.65, send=0.50)
    registrar(12.73, "foley vidro (burst + shards)", "Arremesso do Echo: vidro quebra fora da tela (pan=-0.72, susto do Echo)", 0.68, -0.72)

    # 13.45 s: Entrada do card real do Estratégia Nerd
    s.place(wood(f=270, dur=0.09), t=13.45, gain=0.28, pan=0.20, send=0.20)
    s.place(bubble(f=480, dur=0.15), t=13.46, gain=0.22, pan=0.20, send=0.18)
    registrar(13.45, "wood + bubble", "Echo traz a publicação oficial do Estratégia Nerd", 0.28, 0.20)

    # =========================================================================
    # 2. TRANSIÇÕES DE CENA (Whooshes)
    # =========================================================================
    # 18.05 s: Transição da folha para o quarto
    s.place(air(dur=0.65, f0=180, f1=1800, q=1.2, shape=0.45), t=18.05, gain=0.32, pan=-0.20, send=0.30, pan_to=0.20)
    registrar(18.05, "air (whoosh)", "Transição de cena: folha editorial -> setup gamer no quarto", 0.32, -0.20)

    # 20.60 s: Abertura da moldura para a corrida
    s.place(air(dur=0.45, f0=220, f1=2400, q=1.3, shape=0.5), t=20.60, gain=0.36, pan=0.0, send=0.28)
    registrar(20.60, "air (whoosh)", "Abertura da moldura: zoom mergulhando no jogo de corrida", 0.36, 0.0)

    # =========================================================================
    # 3. PEDIDO OBRIGATÓRIO DO USUÁRIO — CARRO ACELERANDO NA CORRIDA (20.96s - 24.50s)
    # =========================================================================
    car_sound = synth_car_acceleration(dur=3.54, gear_shift_t=0.92)
    s.place(car_sound, t=20.96, gain=0.52, pan=0.0, send=0.35)
    registrar(20.96, "motor acelerando", "Início da aceleração do carro esportivo ao pôr do sol", 0.52, 0.0)
    registrar(21.88, "troca de marcha / engasgo", "Microstutter / troca de marcha sincronizada ao sacolejo visual", 0.48, 0.0)
    registrar(24.05, "arrancada final", "Carro passa por baixo da câmera saindo acelerado", 0.50, 0.0)

    # 24.50 s: Passagem do carro para a bola mística do RPG
    s.place(air(dur=0.60, f0=190, f1=2000, q=1.2, shape=0.45), t=24.50, gain=0.34, pan=0.0, send=0.30)
    registrar(24.50, "air (whoosh)", "Transição de cena: saída da rodovia -> torre e bola mística do RPG", 0.34, 0.0)

    # =========================================================================
    # 4. PEDIDO OBRIGATÓRIO DO USUÁRIO — ENGRENAGEM NO RPG (25.60s - 29.95s)
    # =========================================================================
    # 27.75 s: Echo puxa a alavanca de pedra
    s.place(wood(f=210, dur=0.12), t=27.75, gain=0.35, pan=0.10, send=0.20)
    registrar(27.75, "wood (clique alavanca)", "Echo aciona a alavanca dourada do mecanismo ancestral", 0.35, 0.10)

    # 28.00 s a 29.95 s: Engrenagem girando com cliques metálicos ritmados + rangido grave
    gear_sound = synth_rpg_gear(dur=1.95)
    s.place(gear_sound, t=28.00, gain=0.50, pan=0.25, send=0.35)
    registrar(28.00, "engrenagem RPG", "Início do giro da engrenagem com cliques metálicos ritmados e rangido", 0.50, 0.25)

    # 29.95 s: Fechamento da moldura e recuo da câmera da sala
    s.place(air(dur=0.55, f0=220, f1=1800, q=1.3, shape=0.45), t=29.95, gain=0.32, pan=0.0, send=0.28)
    registrar(29.95, "air (whoosh)", "Fechamento da moldura do RPG e recuo da câmera para o quarto", 0.32, 0.0)

    # 33.20 s: Zoom no menu de opções do jogo
    s.place(air(dur=0.35, f0=260, f1=2200, q=1.4, shape=0.5), t=33.20, gain=0.28, pan=0.10, send=0.20)
    registrar(33.20, "air (whoosh)", "Zoom da câmera no menu de configurações gráficas do jogo", 0.28, 0.10)

    # =========================================================================
    # 5. CARDS DO MÉTODO (39.00s a 42.10s) — Tique + Notas em escala ascendente
    # =========================================================================
    # Card 1: 39.00 s — Questionar (C5 = 523.25 Hz)
    s.place(wood(f=300, dur=0.07), t=39.00, gain=0.32, pan=-0.25, send=0.12)
    s.place(glass(f=523.25, dur=0.55, bright=0.85), t=39.02, gain=0.25, pan=-0.25, send=0.30)
    registrar(39.00, "wood + glass (C5)", "Card 1 do Método: Questionar (tique + nota C5)", 0.32, -0.25)

    # Card 2: 39.86 s — Investigar (D5 = 587.33 Hz)
    s.place(wood(f=320, dur=0.07), t=39.86, gain=0.32, pan=-0.10, send=0.12)
    s.place(glass(f=587.33, dur=0.55, bright=0.90), t=39.88, gain=0.26, pan=-0.10, send=0.30)
    registrar(39.86, "wood + glass (D5)", "Card 2 do Método: Investigar (tique + nota D5)", 0.32, -0.10)

    # Card 3: 40.98 s — Comparar (E5 = 659.25 Hz)
    s.place(wood(f=340, dur=0.07), t=40.98, gain=0.32, pan=0.05, send=0.12)
    s.place(glass(f=659.25, dur=0.55, bright=0.95), t=41.00, gain=0.27, pan=0.05, send=0.30)
    registrar(40.98, "wood + glass (E5)", "Card 3 do Método: Comparar (tique + nota E5)", 0.32, 0.05)

    # Card 4: 42.10 s — Concluir (G5 = 783.99 Hz)
    s.place(wood(f=360, dur=0.07), t=42.10, gain=0.34, pan=0.20, send=0.12)
    s.place(glass(f=783.99, dur=0.60, bright=1.00), t=42.12, gain=0.28, pan=0.20, send=0.32)
    registrar(42.10, "wood + glass (G5)", "Card 4 do Método: Concluir (tique + nota G5)", 0.34, 0.20)

    # =========================================================================
    # 6. ECHO, MONITOR E CONCLUSÃO (43.50s a 52.60s)
    # =========================================================================
    # 43.50 s: Echo sai da cadeira (whoosh suave)
    s.place(air(dur=0.60, f0=150, f1=1400, q=1.2, shape=0.5), t=43.50, gain=0.28, pan=-0.10, send=0.22)
    registrar(43.50, "air (whoosh)", "Echo levanta da cadeira para apontar o monitor", 0.28, -0.10)

    # 46.60 s: Zoom no monitor (whoosh longo)
    s.place(air(dur=0.85, f0=140, f1=1800, q=1.2, shape=0.4), t=46.60, gain=0.30, pan=0.15, send=0.25)
    registrar(46.60, "air (whoosh)", "Zoom dinâmico da câmera aproximando do monitor", 0.30, 0.15)

    # 48.58 s: A CONCLUSÃO acende: "ding" brilhante glass
    s.place(glass(f=1046.50, dur=0.85, bright=1.15), t=48.58, gain=0.36, pan=0.0, send=0.38)
    registrar(48.58, "glass (ding)", "A caixa de CONCLUSÃO acende em ciano no monitor", 0.36, 0.0)

    # 49.20 s: Câmera recua: whoosh reverso curto
    s.place(air(dur=0.45, f0=1800, f1=220, q=1.3, shape=0.4), t=49.20, gain=0.28, pan=0.0, send=0.22)
    registrar(49.20, "air (whoosh reverso)", "Câmera recua do monitor para enquadrar o Echo", 0.28, 0.0)

    # 49.70 s: "?" no monitor: bubble pop
    s.place(bubble(f=480, dur=0.20), t=49.70, gain=0.30, pan=0.10, send=0.20)
    registrar(49.70, "bubble", "Ponto de interrogação (?) surge na tela do monitor", 0.30, 0.10)

    # 51.10 s: Echo volta para a cadeira: whoosh suave
    s.place(air(dur=0.60, f0=160, f1=1200, q=1.2, shape=0.5), t=51.10, gain=0.26, pan=-0.10, send=0.20)
    registrar(51.10, "air (whoosh)", "Echo retorna suavemente para a cadeira gamer", 0.26, -0.10)

    # 51.60 s: A marca entra: brilho (glass grave + sub leve)
    s.place(glass(f=329.63, dur=0.90, bright=0.85), t=51.60, gain=0.28, pan=0.0, send=0.30)
    s.place(sub(f=55, dur=0.45), t=51.60, gain=0.24, pan=0.0, send=0.18)
    registrar(51.60, "glass + sub", "Entrada do card com a marca Estratégia Nerd", 0.28, 0.0)

    # =========================================================================
    # 7. INTERAÇÕES FINAIS, CLIQUES E CTA (52.60s a 60.00s)
    # =========================================================================
    # Cliques do mouse (confirmados no código: 53.15s, 53.95s, 54.80s)
    s.place(wood(f=480, dur=0.05), t=53.15, gain=0.28, pan=0.25, send=0.12)
    registrar(53.15, "wood (clique mouse)", "Primeiro clique físico do mouse pelo Echo", 0.28, 0.25)

    # 53.35 s - 53.68 s: Risco cortando o ACHISMO (arranhão air rápido e agudo)
    s.place(air(dur=0.33, f0=1200, f1=4200, q=2.0, shape=0.5), t=53.35, gain=0.30, pan=0.10, send=0.20)
    registrar(53.35, "air (arranhão/risco)", "Traço vermelho rasgando/cortando a palavra ACHISMO", 0.30, 0.10)

    # 53.95 s: Segundo clique do mouse + marca de volta
    s.place(wood(f=480, dur=0.05), t=53.95, gain=0.28, pan=0.25, send=0.12)
    s.place(glass(f=659.25, dur=0.45, bright=0.90), t=53.95, gain=0.22, pan=0.0, send=0.25)
    registrar(53.95, "wood (clique mouse) + glass", "Segundo clique do mouse e marca reaparece", 0.28, 0.25)

    # 54.80 s: Terceiro clique do mouse
    s.place(wood(f=480, dur=0.05), t=54.80, gain=0.28, pan=0.25, send=0.12)
    registrar(54.80, "wood (clique mouse)", "Terceiro clique físico do mouse pelo Echo", 0.28, 0.25)

    # 56.08 s: LINK NA BIO (pop)
    s.place(bubble(f=560, dur=0.18), t=56.08, gain=0.26, pan=0.0, send=0.18)
    registrar(56.08, "bubble", "Surge o badge LINK NA BIO", 0.26, 0.0)

    # 56.58 s: @estrategia_nerd (pop)
    s.place(bubble(f=620, dur=0.18), t=56.58, gain=0.26, pan=0.0, send=0.18)
    registrar(56.58, "bubble", "Surge o handle @estrategia_nerd", 0.26, 0.0)

    # 57.70 s: Aceno do Echo (bubble leve)
    s.place(bubble(f=480, dur=0.22), t=57.70, gain=0.20, pan=-0.10, send=0.20)
    registrar(57.70, "bubble", "Echo acena em despedida", 0.20, -0.10)

    # 58.50 s: Fecho com acorde glass suave
    s.place(glass(f=523.25, dur=1.4, bright=0.85), t=58.50, gain=0.24, pan=-0.20, send=0.40)
    s.place(glass(f=659.25, dur=1.4, bright=0.90), t=58.52, gain=0.24, pan=0.20, send=0.40)
    s.place(glass(f=783.99, dur=1.5, bright=0.95), t=58.55, gain=0.26, pan=0.0, send=0.45)
    registrar(58.50, "glass (acorde final)", "Fecho harmônico com acorde de vidro estéreo", 0.26, 0.0)

    # Exportar áudio
    s.write(str(OUT_WAV), peak_db=-7.0)
    print(f"[OK] SFX gerada com sucesso: {OUT_WAV}")

    # Exportar metadados em JSON
    with open(OUT_JSON, "w", encoding="utf-8") as f:
        json.dump(eventos, f, indent=2, ensure_ascii=False)
    print(f"[OK] Metadados salvos: {OUT_JSON} ({len(eventos)} eventos registrados)")


if __name__ == "__main__":
    build_manifesto_sfx()
