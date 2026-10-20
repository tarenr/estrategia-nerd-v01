# -*- coding: utf-8 -*-
"""
Regenera os assets do post do Echo seguindo estritamente o padrão oficial do Estratégia Nerd:
- Zero texto desenhado no bitmap
- Prints reais, limpos e puros da tela do robô
- Nomenclatura oficial: capa.webp, thumb.webp, img-001.webp até img-007.webp
- Formato WebP otimizado sobre fundo preto puro OLED (0, 0, 0)
"""
import os
from PIL import Image

H_DIR = 'C:/Users/WINDOWS/Projects/output/echo-demo/h1/frames'
V_DIR = 'C:/Users/WINDOWS/Projects/output/echo-demo/v2rec3/frames'
DEST_DIR = 'C:/Users/WINDOWS/Projects/estrategia-nerd/public/uploads/posts/conheca-o-echo-assistente-estrategia-nerd/images'
REELS_DIR = 'C:/Users/WINDOWS/Projects/estrategia-nerd/public/uploads/posts/conheca-o-echo-assistente-estrategia-nerd/reels'

os.makedirs(DEST_DIR, exist_ok=True)
os.makedirs(REELS_DIR, exist_ok=True)

def load_h(fname):
    return Image.open(os.path.join(H_DIR, fname))

def load_v(fname):
    return Image.open(os.path.join(V_DIR, fname))

print("1. Gerando capa.webp e thumb.webp limpas...")
# Capa: frame puro 1920x1080 do Echo ciano na tela deitada (sem texto)
capa = load_h('01284.jpg')
capa.save(os.path.join(DEST_DIR, 'capa.webp'), 'WEBP', quality=90)

# Thumb: miniatura proporcional (600x338)
thumb = capa.resize((600, 338), Image.Resampling.LANCZOS)
thumb.save(os.path.join(DEST_DIR, 'thumb.webp'), 'WEBP', quality=88)

print("2. Gerando img-001.webp: Orientação de telas (lado a lado puro, sem texto)...")
# Montagem limpa: tela horizontal à esquerda e tela vertical à direita sobre fundo preto OLED
canvas1 = Image.new('RGB', (1600, 900), (0, 0, 0))
h_pure = load_h('04633.jpg').resize((1040, 585), Image.Resampling.LANCZOS)
v_pure = load_v('01371.jpg').resize((460, 818), Image.Resampling.LANCZOS)
canvas1.paste(h_pure, (40, 157))
canvas1.paste(v_pure, (1100, 41))
canvas1.save(os.path.join(DEST_DIR, 'img-001.webp'), 'WEBP', quality=90)

print("3. Gerando img-002.webp: Uma cor para cada IA (Grid 2x2 limpo, sem texto)...")
# Grid 2x2 com os 4 frames limpos das cores
canvas2 = Image.new('RGB', (1600, 900), (0, 0, 0))
f_claude = load_h('01020.jpg').resize((780, 438), Image.Resampling.LANCZOS)
f_anti = load_h('01284.jpg').resize((780, 438), Image.Resampling.LANCZOS)
f_codex = load_h('01524.jpg').resize((780, 438), Image.Resampling.LANCZOS)
f_multi = load_h('01765.jpg').resize((780, 438), Image.Resampling.LANCZOS)
canvas2.paste(f_claude, (15, 8))
canvas2.paste(f_anti, (805, 8))
canvas2.paste(f_codex, (15, 454))
canvas2.paste(f_multi, (805, 454))
canvas2.save(os.path.join(DEST_DIR, 'img-002.webp'), 'WEBP', quality=90)

print("4. Gerando img-003.webp: Estados de alerta (3 quadros limpos, sem texto)...")
# 3 quadros horizontais dos estados puros
canvas3 = Image.new('RGB', (1600, 600), (0, 0, 0))
f_apr = load_h('02318.jpg').resize((520, 580), Image.Resampling.LANCZOS)
f_ok = load_h('02864.jpg').resize((520, 580), Image.Resampling.LANCZOS)
f_err = load_h('03295.jpg').resize((520, 580), Image.Resampling.LANCZOS)
canvas3.paste(f_apr, (10, 10))
canvas3.paste(f_ok, (540, 10))
canvas3.paste(f_err, (1070, 10))
canvas3.save(os.path.join(DEST_DIR, 'img-003.webp'), 'WEBP', quality=90)

print("5. Gerando img-004.webp: Aprovação no celular (frame puro da tela, sem anotações)...")
# Frame puro da tela do celular durante o pedido de permissão do Claude
f_appr_cell = load_h('03990.jpg')
f_appr_cell.save(os.path.join(DEST_DIR, 'img-004.webp'), 'WEBP', quality=90)

print("6. Gerando img-005.webp: Pergunte por voz (Grid 2x2 dos 4 cartões limpos)...")
canvas5 = Image.new('RGB', (1600, 900), (0, 0, 0))
c1_pure = load_h('04633.jpg').resize((780, 438), Image.Resampling.LANCZOS)
c2_pure = load_h('04968.jpg').resize((780, 438), Image.Resampling.LANCZOS)
c3_pure = load_h('05306.jpg').resize((780, 438), Image.Resampling.LANCZOS)
c5_pure = load_h('05980.jpg').resize((780, 438), Image.Resampling.LANCZOS)
canvas5.paste(c1_pure, (15, 8))
canvas5.paste(c2_pure, (805, 8))
canvas5.paste(c3_pure, (15, 454))
canvas5.paste(c5_pure, (805, 454))
canvas5.save(os.path.join(DEST_DIR, 'img-005.webp'), 'WEBP', quality=90)

print("7. Gerando img-006.webp: Personalidade (3 expressões limpas lado a lado)...")
canvas6 = Image.new('RGB', (1600, 600), (0, 0, 0))
f_pet = load_h('07801.jpg').resize((520, 580), Image.Resampling.LANCZOS)
f_angry = load_h('08037.jpg').resize((520, 580), Image.Resampling.LANCZOS)
f_dance = load_h('08411.jpg').resize((520, 580), Image.Resampling.LANCZOS)
canvas6.paste(f_pet, (10, 10))
canvas6.paste(f_angry, (540, 10))
canvas6.paste(f_dance, (1070, 10))
canvas6.save(os.path.join(DEST_DIR, 'img-006.webp'), 'WEBP', quality=90)

print("8. Gerando img-007.webp: Halloween (Jack & Sally limpos lado a lado)...")
canvas7 = Image.new('RGB', (1600, 800), (0, 0, 0))
f_jack = load_h('08971.jpg').resize((780, 780), Image.Resampling.LANCZOS)
f_sally = load_h('09434.jpg').resize((780, 780), Image.Resampling.LANCZOS)
canvas7.paste(f_jack, (15, 10))
canvas7.paste(f_sally, (805, 10))
canvas7.save(os.path.join(DEST_DIR, 'img-007.webp'), 'WEBP', quality=90)

print("9. Gerando capas e poster do Reel em reels/...")
# Copia e gera capa do reel a partir de um frame vertical do Echo
reel_frame = load_v('01371.jpg') # 1080x1920
reel_frame.save(os.path.join(REELS_DIR, 'capa.jpg'), 'JPEG', quality=90)
reel_frame.save(os.path.join(REELS_DIR, 'reel.jpg'), 'JPEG', quality=90)

# Limpar arquivos antigos fora do padrao se existirem
for old_f in ['01-orientacao-telas.webp', '02-cores-agentes.webp', '03-diagrama-fluxo.webp', '04-estados-alertas.webp', '05-aprovacao-celular.webp', '06-cartoes-respostas.webp', '07-personalidade-reacoes.webp', '08-halloween-jack-sally.webp']:
    old_p = os.path.join(DEST_DIR, old_f)
    if os.path.exists(old_p):
        os.remove(old_p)
        print('Removido arquivo antigo:', old_f)

print("Processamento de imagens concluído com sucesso!")
