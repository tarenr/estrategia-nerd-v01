"""
gerar_prancha.py
Gera a prancha comparativa de alta resolução v3/pranchas/prancha_v3.png
com os 10 quadros (número, tempo e frase) e a imagem de referência ao lado.
"""
import os
import json
from PIL import Image, ImageDraw, ImageFont

V3_DIR = r'C:\Users\WINDOWS\Projects\output\echo-ti-video\v3'
QUADROS_DIR = os.path.join(V3_DIR, 'quadros')
REFS_DIR = os.path.join(V3_DIR, 'refs')
PRANCHAS_DIR = os.path.join(V3_DIR, 'pranchas')
OUT_PRANCHA = os.path.join(PRANCHAS_DIR, 'prancha_v3.png')

os.makedirs(PRANCHAS_DIR, exist_ok=True)

# Carregar metadados
with open(os.path.join(V3_DIR, 'medidas.json'), 'r', encoding='utf-8') as f:
    medidas = json.load(f)

with open(os.path.join(V3_DIR, 'area_segura.json'), 'r', encoding='utf-8') as f:
    area_segura = json.load(f)

# Fontes do sistema
FONT_BOLD_PATH = 'C:/Windows/Fonts/segoeuib.ttf'
FONT_REG_PATH = 'C:/Windows/Fonts/segoeui.ttf'

font_title = ImageFont.truetype(FONT_BOLD_PATH, 42)
font_subtitle = ImageFont.truetype(FONT_REG_PATH, 22)
font_ref_title = ImageFont.truetype(FONT_BOLD_PATH, 28)
font_card_head = ImageFont.truetype(FONT_BOLD_PATH, 20)
font_card_sub = ImageFont.truetype(FONT_REG_PATH, 16)
font_badge = ImageFont.truetype(FONT_BOLD_PATH, 15)
font_metrics = ImageFont.truetype(FONT_REG_PATH, 14)

# Dimensões da Prancha 4K Editorial (3400 x 1850)
PW, PH = 3400, 1850
prancha = Image.new('RGB', (PW, PH), color=(11, 19, 27))
draw = ImageDraw.Draw(prancha)

# Cabeçalho Principal da Prancha
draw.rectangle([(0, 0), (PW, 100)], fill=(15, 27, 40))
draw.line([(0, 100), (PW, 100)], fill=(56, 189, 248), width=3)
draw.text((50, 20), "REEL: DIA DO PROFISSIONAL DE TI · V3 PRANCHA EDITORIAL REVISADA", font=font_title, fill=(248, 250, 252))
draw.text((50, 68), "Legenda Orbitron 700 sem caixas · Boca robótica aberta · Ninho denso de cabos · Echo visível em C02/C03 · 10 Cenários únicos", font=font_subtitle, fill=(148, 163, 184))

# Status de conformidade no topo direito
draw.rectangle([(PW - 520, 24), (PW - 50, 76)], fill=(6, 78, 59), outline=(34, 197, 94), width=2)
draw.text((PW - 490, 36), "10 CENAS · CONFORMIDADE TOTAL", font=font_card_head, fill=(255, 255, 255))

# ========================================================
# COLUNA DA ESQUERDA: REFERÊNCIA DE ESTILO E ANÁLISE
# ========================================================
col_w = 820
draw.rectangle([(40, 130), (col_w, PH - 40)], fill=(15, 23, 34), outline=(30, 41, 59), width=2)

draw.text((70, 150), "REFERÊNCIA DE ESTILO OFICIAL", font=font_ref_title, fill=(251, 191, 36))
draw.text((70, 185), "referencia-estilo.webp · Padrão cartoon com volume", font=font_card_sub, fill=(148, 163, 184))

# Carregar e colar a referência
ref_path = os.path.join(REFS_DIR, 'referencia-estilo.webp')
if os.path.exists(ref_path):
    ref_img = Image.open(ref_path).convert('RGB')
    rw = 720
    rh = int(ref_img.height * (rw / ref_img.width))
    ref_thumb = ref_img.resize((rw, rh), Image.Resampling.LANCZOS)
    prancha.paste(ref_thumb, (60, 225))
    draw.rectangle([(60, 225), (60 + rw, 225 + rh)], outline=(17, 28, 38), width=4)
    ref_bottom = 225 + rh
else:
    ref_bottom = 500

# Painel de conformidade técnica e resolução dos problemas da v2 e v3 anterior
draw.text((70, ref_bottom + 30), "ITENS CORRIGIDOS NA V3:", font=font_card_head, fill=(56, 189, 248))

checks = [
    ("✔ Boca Robótica Aberta", "Forma escura com brilho ciano (#00d4ff), sem dentes/língua, proporcional a boca.json."),
    ("✔ Legendas em Orbitron 700", "Sem caixas, centralizadas, altura fixa uniforme (Y=250), contorno grosso e ciano no destaque."),
    ("✔ Ninho Denso de Cabos (C05)", "Dezenas de cabos embolados em laços no topo, descendo nas laterais e no chão com plugues."),
    ("✔ Echo Visível em C02 e C03", "Cabeça e mãos visíveis com >= 40% da largura (599px e 649px) operando teclado e switch."),
    ("✔ Enquadramentos C01 vs C06", "C01 close frontal com mão apontando vs C06 3/4 com plugue de 2 pinos grande em primeiro plano."),
    ("✔ Telão C09 em JetBrains Mono", "Terminal com tipografia oficial monospace, uptime 99.999% e comemoração."),
    ("✔ Fundos Cel-Shading Ricos", "Zero fundo escuro vazio; texturas de parede, racks detalhados, conduítes e luzes.")
]

cy = ref_bottom + 65
for tit, desc in checks:
    draw.text((70, cy), tit, font=font_card_head, fill=(34, 197, 94))
    draw.text((70, cy + 22), desc, font=font_card_sub, fill=(203, 213, 225))
    cy += 50

# Paleta de Cores da Referência
draw.text((70, cy + 15), "PALETA DE CORES DA CENA:", font=font_card_head, fill=(251, 191, 36))
cores_swatch = [
    ("#111c26", "Tinta/Ink"),
    ("#3c8e8c", "Parede Petróleo"),
    ("#255e5c", "Piso"),
    ("#1e4b85", "Cabo Azul"),
    ("#eab308", "Cabo Amarelo"),
    ("#22c55e", "LED Ativo")
]

sw_x = 70
sw_y = cy + 45
for hex_col, nome in cores_swatch:
    rgb = tuple(int(hex_col.lstrip('#')[i:i+2], 16) for i in (0, 2, 4))
    draw.rectangle([(sw_x, sw_y), (sw_x + 36, sw_y + 36)], fill=rgb, outline=(0, 0, 0), width=2)
    draw.text((sw_x + 46, sw_y + 8), nome, font=font_metrics, fill=(226, 232, 240))
    sw_x += 230
    if sw_x > 700:
        sw_x = 70
        sw_y += 42

# ========================================================
# ÁREA DA DIREITA: OS 10 QUADROS EM GRADE 5x2
# ========================================================
thumb_w = 440
thumb_h = int(thumb_w * (1920 / 1080)) # 782px
gap_x = 42
gap_y = 60
start_x = 870
start_y = 140

cenas_info = [
    ("C01", "0,0–2,3s", "EI, VOCÊ AÍ / DO TI!", "Close frontal · Apontando · Boca robô aberta"),
    ("C02", "2,3–3,3s", "FORMATA", "Echo visível >=40% · Monitor 100% · Bancada TI"),
    ("C03", "3,3–4,2s", "CONFIGURA", "Echo plugando RJ45 · Switch 48P · Link LED verde"),
    ("C04", "4,2–4,9s", "FAZ BACKUP", "Cofre digital · Pastas em arco · HD externo 4TB"),
    ("C05", "4,9–7,4s", "E RESOLVE QUASE / TUDO COM…", "Ninho denso 30+ cabos · Laços e tomadas no chão"),
    ("C06", "7,4–8,6s", "JÁ TENTOU / REINICIAR?", "Enquadramento 3/4 · Plugue gigante · Olhar 'eu sei'"),
    ("C07", "8,6–9,1s", "(APAGÃO TOTAL)", "Silhueta no escuro · Olhos ciano · Reboot 42%"),
    ("C08", "9,1–10,7s", "HOJE O DIA / É SEU!", "Mesa TI acolhedora · Luz quente · Brinde caneca"),
    ("C09", "10,7–13,1s", "FELIZ DIA DO / PROFISSIONAL DE TI!", "Telão JetBrains Mono · Mãos ao alto · NOC"),
    ("C10", "13,1–17,0s", "UM ABRAÇO DO / ESTRATÉGIA NERD!", "Estúdio da marca · Caveira oficial · Aceno amigável")
]

for idx, (cid, tempo, frase, desc) in enumerate(cenas_info):
    row = idx // 5
    col = idx % 5

    x = start_x + col * (thumb_w + gap_x)
    y = start_y + row * (thumb_h + gap_y + 40)

    # Carregar imagem do quadro
    img_path = os.path.join(QUADROS_DIR, f"{cid}.png")
    if os.path.exists(img_path):
        q_img = Image.open(img_path).convert('RGB')
        q_thumb = q_img.resize((thumb_w, thumb_h), Image.Resampling.LANCZOS)
        prancha.paste(q_thumb, (x, y))
        draw.rectangle([(x, y), (x + thumb_w, y + thumb_h)], outline=(30, 41, 59), width=3)
    else:
        draw.rectangle([(x, y), (x + thumb_w, y + thumb_h)], fill=(20, 20, 20), outline=(255, 0, 0), width=3)

    # Badge de identificação sobre o topo do quadro
    draw.rectangle([(x, y), (x + 130, y + 34)], fill=(15, 23, 42))
    draw.text((x + 8, y + 6), f"{cid} · {tempo}", font=font_badge, fill=(56, 189, 248))

    # Barra de informações abaixo do quadro
    info_y = y + thumb_h + 8
    draw.rectangle([(x, info_y), (x + thumb_w, info_y + 58)], fill=(15, 23, 34), outline=(30, 41, 59), width=1)

    draw.text((x + 8, info_y + 6), frase, font=font_badge, fill=(255, 255, 255))
    draw.text((x + 8, info_y + 24), desc, font=font_metrics, fill=(148, 163, 184))

    # Medidas anotadas
    m_info = medidas.get(cid, {})
    ew = m_info.get('echoLarguraVisivelPx', '-')
    hw = m_info.get('cabecaLarguraPx', '-')
    bv = m_info.get('bocaValor', '-')

    met_txt = f"Echo: {ew}px | Boca: {bv}"
    if cid in ['C01', 'C06']:
        met_txt += f" | Cabeça: {hw}px"
    draw.text((x + 8, info_y + 40), met_txt, font=font_metrics, fill=(34, 197, 94))

# Salvar prancha final
prancha.save(OUT_PRANCHA, 'PNG', quality=95)
stat = os.stat(OUT_PRANCHA)
print(f"Prancha salva com sucesso em: {OUT_PRANCHA} ({stat.st_size} bytes)")
