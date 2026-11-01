import os
import sys
import hashlib
import subprocess
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

V2_DIR = Path("C:/Users/WINDOWS/Projects/estrategia-nerd/conteudo-planejado/videos-echo/castlevania-castelo-invertido/v2")
QUADROS_DIR = V2_DIR / "quadros"
PRANCHAS_DIR = V2_DIR / "pranchas"
QUADROS_DIR.mkdir(parents=True, exist_ok=True)
PRANCHAS_DIR.mkdir(parents=True, exist_ok=True)

CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
HTML_URL = "file:///" + str(V2_DIR.resolve() / "quadros.html").replace("\\", "/")

QUADROS_INFO = [
    {
        "id": "Q01",
        "tempo": "0.0s – 3.5s",
        "origem": "Setup Gamer Oficial (Manifesto)",
        "titulo": "Q01 · GANCHO NO SETUP GAMER",
        "locucao": "“Em 1997, a indústria priorizava o 3D.”",
        "desc": "Echo oficial parado diante da mesa/cadeira gamer em plano médio, olhando para a câmera e entregando o gancho inicial. Monitor ao fundo ligado exibindo o início da exibição de Castlevania SOTN."
    },
    {
        "id": "Q02",
        "tempo": "3.5s – 6.5s",
        "origem": "Setup Gamer (Push-in Óptico 1.5x)",
        "titulo": "Q02 · INÍCIO DO PUSH-IN DA CÂMERA",
        "locucao": "“Castlevania fez o contrário: apostou no 2D...”",
        "desc": "A câmera inicia um avanço suave (push-in) em direção ao monitor gamer. O Echo se desloca naturalmente para a borda esquerda à medida que a tela do computador ganha escala no quadro."
    },
    {
        "id": "Q03",
        "tempo": "6.5s – 8.8s",
        "origem": "Setup Gamer (Enquadramento Fechado 2.2x)",
        "titulo": "Q03 · MONITOR PREENCHENDO O QUADRO",
        "locucao": "“...e criou um clássico.”",
        "desc": "Enquadramento fechado no monitor gamer: a moldura externa e a base ficam visíveis, com o quarto preenchendo o ambiente. A tela exibe a abertura clássica sem atravessar o vidro. Echo fora de quadro."
    },
    {
        "id": "Q04",
        "tempo": "8.8s – 13.6s",
        "origem": "Castlevania - SOTN.mp4 [14.3s – 17.6s]",
        "titulo": "Q04 · TELA CLÁSSICA NO MONITOR",
        "locucao": "“Mas o maior segredo do jogo estava escondido...”",
        "desc": "Gameplay real dentro da tela do monitor com moldura visível. O quarto preenche naturalmente as sobras superior e inferior com luz refletida no ambiente. Exibe tela de 1997. Zero áreas pretas artificiais."
    },
    {
        "id": "Q05",
        "tempo": "13.6s – 22.0s",
        "origem": "Castlevania - SOTN.mp4 [17.6s – 23.8s]",
        "titulo": "Q05 · A BATALHA CONTRA RICHTER",
        "locucao": "“Ao descobrir quem realmente controlava Richter...”",
        "desc": "Gameplay real no monitor: Alucard vs Richter Belmont no Salão do Trono ('RICHTER x 00'). A moldura do monitor e o quarto acolhem o jogo em 4:3 com luz ciano/âmbar projetada suavemente."
    },
    {
        "id": "Q06",
        "tempo": "22.0s – 26.7s",
        "origem": "Castlevania - SOTN.mp4 [40.7s – 45.0s]",
        "titulo": "Q06 · CASTELO INVERTIDO (ROTAS E INIMIGOS)",
        "locucao": "“As mesmas áreas ganhavam novas rotas, inimigos...”",
        "desc": "Gameplay real no monitor: Castelo Invertido com arquitetura de ponta-cabeça e novos inimigos (Discus Lord / Phantom Skull), com o quarto imerso no jogo sem cards, dashboards ou HUDs artificiais."
    },
    {
        "id": "Q07",
        "tempo": "26.7s – 31.3s",
        "origem": "Castlevania - SOTN.mp4 [45.0s – 50.0s]",
        "titulo": "Q07 · NOVA FORMA DE EXPLORAÇÃO",
        "locucao": "“Lugares que você já conhecia precisavam...”",
        "desc": "Gameplay real no monitor: navegação e travessia pelas câmaras invertidas com saltos altos contra Hunting Girl, ilustrando a reinvenção do espaço exploratório dentro do setup gamer."
    },
    {
        "id": "Q08",
        "tempo": "31.3s – 38.0s",
        "origem": "Castlevania - SOTN.mp4 [30.0s – 31.5s]",
        "titulo": "Q08 · MAPA E ESTILO METROIDVANIA",
        "locucao": "“Essa segunda jornada ampliava o jogo e reforçava...”",
        "desc": "Exibição nítida do mapa do castelo e exploração aberta na tela do monitor (frame real de alta legibilidade, sem estouro branco), consolidando o legado Metroidvania na história dos games."
    },
    {
        "id": "Q09",
        "tempo": "38.0s – 44.57s",
        "origem": "Castlevania - SOTN.mp4 [57.0s – 60.0s]",
        "titulo": "Q09 · ENCERRAMENTO COM A PERGUNTA FINAL",
        "locucao": "“Você conseguiu chegar ao castelo invertido? Conta aí...”",
        "desc": "Quadro final forte de encerramento: Alucard em ação atmosférica no castelo na tela do monitor com iluminação do quarto em fade suave. Sem CTA intrusivo, sem poluição visual."
    }
]

def sha256_file(filepath):
    h = hashlib.sha256()
    with open(filepath, "rb") as f:
        while chunk := f.read(65536):
            h.update(chunk)
    return h.hexdigest()

def capturar_quadros():
    print("--- Capturando os 9 quadros oficiais em 1080x1920 via Chrome Headless ---")
    for q in QUADROS_INFO:
        qid = q["id"]
        out_img = QUADROS_DIR / f"{qid}.png"
        url = f"{HTML_URL}?quadro={qid}"
        print(f"Renderizando {qid}...")
        cmd = [
            CHROME,
            "--headless=new",
            "--no-sandbox",
            "--disable-gpu",
            "--allow-file-access-from-files",
            f"--screenshot={out_img}",
            "--window-size=1080,1920",
            "--virtual-time-budget=2500",
            url
        ]
        res = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
        if out_img.exists():
            im = Image.open(out_img)
            print(f" -> Sucesso {out_img.name}: {im.size} ({out_img.stat().st_size} bytes, sha256={sha256_file(out_img)[:12]}...)")
        else:
            print(f"ERRO ao capturar {qid}:", res.stderr)
            sys.exit(1)

def montar_prancha():
    print("\n--- Montando Prancha Editorial Consolidada em Alta Resolução (3200x2720) ---")
    PW = 3200
    PH = 2720
    prancha = Image.new("RGB", (PW, PH), color=(8, 14, 22))
    draw = ImageDraw.Draw(prancha)

    font_title = ImageFont.truetype("C:/Windows/Fonts/segoeuib.ttf", 40)
    font_subtitle = ImageFont.truetype("C:/Windows/Fonts/segoeui.ttf", 22)
    font_badge = ImageFont.truetype("C:/Windows/Fonts/segoeuib.ttf", 15)
    font_card_head = ImageFont.truetype("C:/Windows/Fonts/segoeuib.ttf", 20)
    font_card_time = ImageFont.truetype("C:/Windows/Fonts/segoeuib.ttf", 15)
    font_card_origem = ImageFont.truetype("C:/Windows/Fonts/segoeui.ttf", 14)
    font_card_locucao = ImageFont.truetype("C:/Windows/Fonts/segoeuii.ttf", 15)
    font_card_desc = ImageFont.truetype("C:/Windows/Fonts/segoeui.ttf", 14)
    font_info_bold = ImageFont.truetype("C:/Windows/Fonts/segoeuib.ttf", 18)
    font_info_text = ImageFont.truetype("C:/Windows/Fonts/segoeui.ttf", 15)

    # 1. Cabeçalho Principal da Prancha
    draw.rectangle([(0, 0), (PW, 130)], fill=(12, 20, 32))
    draw.line([(0, 130), (PW, 130)], fill=(0, 212, 255), width=3)
    draw.text((60, 26), "ESTRATÉGIA NERD · PILOTO #1: CASTLEVANIA SOTN (O CASTELO INVERTIDO)", font=font_title, fill=(248, 250, 252))
    draw.text((60, 78), "PRANCHA EDITORIAL HÍBRIDA · MOLDE DO ECHO (1080×1920) · GAMEPLAY REAL NO MONITOR COM QUARTO NATURAL", font=font_subtitle, fill=(148, 163, 184))

    # Badge de conformidade
    draw.rectangle([(PW - 660, 34), (PW - 60, 96)], fill=(6, 78, 59), outline=(34, 197, 94), width=2)
    draw.text((PW - 630, 52), "MOLDE OFICIAL · SEM BARRAS PRETAS · 9 QUADROS", font=font_badge, fill=(255, 255, 255))

    FW = 560
    FH = int(FW * (1920 / 1080)) # ~995px
    col_gap = 45
    margin_x = 70
    row_gap = 40
    row1_y = 160
    row2_y = row1_y + FH + 240 + row_gap

    for i, q in enumerate(QUADROS_INFO):
        row = i // 5
        col = i % 5

        x = margin_x + col * (FW + col_gap)
        y = row1_y if row == 0 else row2_y

        card_h = FH + 230
        draw.rectangle([(x - 8, y - 8), (x + FW + 8, y + card_h)], fill=(15, 26, 40), outline=(28, 44, 66), width=2)

        img_path = QUADROS_DIR / f"{q['id']}.png"
        if img_path.exists():
            im = Image.open(img_path).resize((FW, FH), Image.Resampling.LANCZOS)
            prancha.paste(im, (x, y))

        meta_y = y + FH + 12

        # Badges de Tempo e Origem
        draw.rectangle([(x, meta_y), (x + 130, meta_y + 28)], fill=(2, 132, 199))
        draw.text((x + 10, meta_y + 5), q["tempo"], font=font_card_time, fill=(255, 255, 255))

        draw.rectangle([(x + 140, meta_y), (x + FW, meta_y + 28)], fill=(22, 38, 58), outline=(45, 68, 96), width=1)
        draw.text((x + 148, meta_y + 6), q["origem"], font=font_card_origem, fill=(186, 230, 253))

        # Título do Quadro
        draw.text((x, meta_y + 36), q["titulo"], font=font_card_head, fill=(241, 245, 249))

        # Locução oficial itálico ciano
        draw.text((x, meta_y + 66), q["locucao"], font=font_card_locucao, fill=(0, 212, 255))

        # Descrição da ação
        words = q["desc"].split()
        lines = []
        cur = []
        for w in words:
            cur.append(w)
            if len(" ".join(cur)) > 44:
                lines.append(" ".join(cur))
                cur = []
        if cur:
            lines.append(" ".join(cur))

        for j, line in enumerate(lines[:4]):
            draw.text((x, meta_y + 92 + j * 20), line, font=font_card_desc, fill=(148, 163, 184))

    # Slot 10 (Linha 2, Coluna 5): Card de Resumo Técnico Editorial
    rx = margin_x + 4 * (FW + col_gap)
    ry = row2_y
    rcard_h = FH + 230
    draw.rectangle([(rx - 8, ry - 8), (rx + FW + 8, ry + rcard_h)], fill=(12, 22, 36), outline=(0, 212, 255), width=2)

    draw.rectangle([(rx, ry), (rx + FW, ry + 42)], fill=(16, 42, 68))
    draw.text((rx + 16, ry + 10), "ESTRATÉGIA VISUAL APROVADA", font=font_info_bold, fill=(0, 212, 255))

    bullets = [
        ("Echo Oficial no Gancho:", "Q01 com Echo em plano médio lúcido diante da cadeira gamer."),
        ("Push-in Progressivo:", "Q02–Q03 com avanço ótico suave terminando no monitor (sem atravessar)."),
        ("Gameplay no Monitor:", "Q04–Q09 com gameplay 4:3 real dentro da moldura com luz refletida."),
        ("Quarto Natural nas Sobras:", "Parede neon no topo e mesa na base preenchem 9:16 sem barras pretas."),
        ("Zero Poluição Visual:", "Zero cards, zero dashboards, zero HUDs e zero Echo em Q04–Q09."),
        ("Mapa sem Estouro:", "Q08 com mapa real nítido (orig_map_30s) em contraste perfeito."),
        ("Encerramento Marcante:", "Q09 com visual forte no castelo e iluminação em fade suave.")
    ]

    by = ry + 60
    for title, desc in bullets:
        draw.text((rx + 16, by), f"• {title}", font=font_info_bold, fill=(241, 245, 249))
        by += 24
        words = desc.split()
        lines = []
        cur = []
        for w in words:
            cur.append(w)
            if len(" ".join(cur)) > 42:
                lines.append(" ".join(cur))
                cur = []
        if cur:
            lines.append(" ".join(cur))
        for l in lines:
            draw.text((rx + 30, by), l, font=font_info_text, fill=(148, 163, 184))
            by += 22
        by += 10

    # Rodapé Editorial
    draw.rectangle([(0, PH - 60), (PW, PH)], fill=(12, 20, 32))
    draw.text((60, PH - 40), "ESTRATÉGIA NERD · 'NADA DE ACHISMO. SÓ ESTRATÉGIA' · PRODUÇÃO HÍBRIDA OFICIAL · FORGE #1772", font=font_subtitle, fill=(100, 116, 139))

    out_prancha = PRANCHAS_DIR / "prancha_editorial_v2.png"
    prancha.save(out_prancha, quality=95)
    print(f" -> Prancha salva: {out_prancha.name} ({out_prancha.stat().st_size} bytes, sha256={sha256_file(out_prancha)})")

if __name__ == "__main__":
    capturar_quadros()
    montar_prancha()
