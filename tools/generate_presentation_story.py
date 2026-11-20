"""
Gera o story vertical de apresentação oficial do Estratégia Nerd (1080x1920)
Seguindo os padrões de design: Glassmorphism dark, neon cyan/purple e zonas de segurança do Instagram.
"""
import os
import math
from PIL import Image, ImageDraw, ImageFont, ImageFilter

def create_story():
    width = 1080
    height = 1920
    
    # 1. Base dark canvas com gradiente futurista
    base = Image.new("RGBA", (width, height), (10, 14, 26, 255))
    draw = ImageDraw.Draw(base)
    
    # Gradiente de fundo vertical escuro (de #080c14 a #0d1527)
    for y in range(height):
        ratio = y / height
        r = int(8 + (15 - 8) * ratio)
        g = int(12 + (22 - 12) * ratio)
        b = int(22 + (45 - 22) * ratio)
        draw.line([(0, y), (width, y)], fill=(r, g, b, 255))
        
    # 2. Efeito de iluminação radial (Glows neon)
    glow_layer = Image.new("RGBA", (width, height), (0, 0, 0, 0))
    glow_draw = ImageDraw.Draw(glow_layer)
    
    # Glow ciano no topo/direita
    glow_draw.ellipse([width - 400, 150, width + 400, 950], fill=(0, 242, 254, 38))
    # Glow roxo no centro/esquerda
    glow_draw.ellipse([-300, 600, 500, 1400], fill=(138, 43, 226, 45))
    # Glow azul escuro na base
    glow_draw.ellipse([200, 1200, 880, 1880], fill=(59, 130, 246, 35))
    
    # Blur no glow
    glow_layer = glow_layer.filter(ImageFilter.GaussianBlur(120))
    base = Image.alpha_composite(base, glow_layer)
    draw = ImageDraw.Draw(base)
    
    # 3. Grid sci-fi sutil de fundo
    grid_layer = Image.new("RGBA", (width, height), (0, 0, 0, 0))
    grid_draw = ImageDraw.Draw(grid_layer)
    grid_size = 90
    for x in range(0, width, grid_size):
        grid_draw.line([(x, 0), (x, height)], fill=(255, 255, 255, 6), width=1)
    for y in range(0, height, grid_size):
        grid_draw.line([(0, y), (width, y)], fill=(255, 255, 255, 6), width=1)
    base = Image.alpha_composite(base, grid_layer)
    draw = ImageDraw.Draw(base)

    # 4. Fontes
    font_bold = "C:/Windows/Fonts/seguibl.ttf" # Segoe UI Black
    font_semi = "C:/Windows/Fonts/seguisb.ttf" # Segoe UI Semibold
    font_reg = "C:/Windows/Fonts/segoeui.ttf"   # Segoe UI Regular
    
    try:
        f_badge = ImageFont.truetype(font_semi, 28)
        f_title_big = ImageFont.truetype(font_bold, 72)
        f_title_sub = ImageFont.truetype(font_bold, 54)
        f_desc = ImageFont.truetype(font_reg, 32)
        f_card_title = ImageFont.truetype(font_semi, 34)
        f_card_desc = ImageFont.truetype(font_reg, 26)
        f_cta = ImageFont.truetype(font_semi, 30)
    except Exception as e:
        print(f"Fallback fonts: {e}")
        f_badge = ImageFont.load_default()
        f_title_big = ImageFont.load_default()
        f_title_sub = ImageFont.load_default()
        f_desc = ImageFont.load_default()
        f_card_title = ImageFont.load_default()
        f_card_desc = ImageFont.load_default()
        f_cta = ImageFont.load_default()

    # 5. Topo Seguro: Logo oficial e Badge de Boas-Vindas (a partir de y=280 para não colidir com o status do IG)
    y_cursor = 280
    
    # Badge pill "CANAL OFICIAL • 2026"
    badge_text = "ESTRATÉGIA NERD  •  HUB OFICIAL"
    bbox = draw.textbbox((0, 0), badge_text, font=f_badge)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    bx, by = (width - tw) // 2, y_cursor
    
    # Card do badge com borda ciano neon
    pad_x, pad_y = 28, 14
    badge_rect = [bx - pad_x, by - pad_y, bx + tw + pad_x, by + th + pad_y]
    
    overlay = Image.new("RGBA", (width, height), (0, 0, 0, 0))
    ov_draw = ImageDraw.Draw(overlay)
    ov_draw.rounded_rectangle(badge_rect, radius=24, fill=(14, 25, 48, 200), outline=(0, 242, 254, 180), width=2)
    base = Image.alpha_composite(base, overlay)
    draw = ImageDraw.Draw(base)
    draw.text((bx, by - 2), badge_text, font=f_badge, fill=(0, 242, 254, 255))
    
    # Logo horizontal oficial
    logo_path = "C:/Users/WINDOWS/Projects/estrategia-nerd/public/assets/brand/logo-horizontal.png"
    if os.path.exists(logo_path):
        try:
            logo_img = Image.open(logo_path).convert("RGBA")
            # Redimensionar mantendo proporção (largura ~ 620px)
            aspect = logo_img.height / logo_img.width
            target_w = 640
            target_h = int(target_w * aspect)
            logo_img = logo_img.resize((target_w, target_h), Image.Resampling.LANCZOS)
            logo_x = (width - target_w) // 2
            logo_y = y_cursor + 90
            base.paste(logo_img, (logo_x, logo_y), logo_img)
            y_cursor = logo_y + target_h + 30
        except Exception as err:
            print(f"Erro logo: {err}")
            y_cursor += 160
    else:
        y_cursor += 160
        
    draw = ImageDraw.Draw(base)

    # 6. Manchete Principal (Headline de Alto Impacto)
    y_cursor += 20
    line1 = "O UNIVERSO NERD"
    bbox1 = draw.textbbox((0, 0), line1, font=f_title_big)
    draw.text(((width - (bbox1[2] - bbox1[0])) // 2, y_cursor), line1, font=f_title_big, fill=(255, 255, 255, 255))
    
    y_cursor += 85
    line2 = "EM OUTRO NÍVEL"
    bbox2 = draw.textbbox((0, 0), line2, font=f_title_big)
    draw.text(((width - (bbox2[2] - bbox2[0])) // 2, y_cursor), line2, font=f_title_big, fill=(0, 242, 254, 255))
    
    y_cursor += 95
    desc = "Tecnologia, games, cinema e análises diretas ao ponto."
    bbox_d = draw.textbbox((0, 0), desc, font=f_desc)
    draw.text(((width - (bbox_d[2] - bbox_d[0])) // 2, y_cursor), desc, font=f_desc, fill=(160, 178, 205, 255))
    
    # 7. Três Lâminas de Conteúdo (Cards Glassmorphic)
    cards = [
        {"icon": "🎮", "title": "Games & Reviews", "desc": "Lançamentos, análises completas e gameplays."},
        {"icon": "🎬", "title": "Cinema & Séries", "desc": "Trailers comentados, teorias e listas nerds."},
        {"icon": "⚡", "title": "Tech & Inteligência Artificial", "desc": "As ferramentas e novidades que mudam o mundo."}
    ]
    
    card_w = 920
    card_h = 135
    card_x = (width - card_w) // 2
    y_cursor += 75
    
    for c in cards:
        card_rect = [card_x, y_cursor, card_x + card_w, y_cursor + card_h]
        
        card_layer = Image.new("RGBA", (width, height), (0, 0, 0, 0))
        c_draw = ImageDraw.Draw(card_layer)
        # Fundo glassmorphic translúcido
        c_draw.rounded_rectangle(card_rect, radius=20, fill=(18, 26, 45, 175), outline=(56, 189, 248, 50), width=1)
        base = Image.alpha_composite(base, card_layer)
        draw = ImageDraw.Draw(base)
        
        # Ícone / emoji em destaque
        draw.text((card_x + 35, y_cursor + 35), c["icon"], font=f_card_title, fill=(255, 255, 255, 255))
        
        # Título do Card
        draw.text((card_x + 105, y_cursor + 28), c["title"], font=f_card_title, fill=(240, 246, 255, 255))
        # Descrição do Card
        draw.text((card_x + 105, y_cursor + 76), c["desc"], font=f_card_desc, fill=(148, 163, 184, 255))
        
        y_cursor += card_h + 24

    # 8. Rodapé Seguro: Call to Action apontando para o Link Sticker do Instagram
    # No Instagram Studio, o sticker do link é posicionado em y: 0.78 (aproximadamente y=1498 no canvas 1920)
    cta_y = 1390
    cta_text = "TOQUE NO LINK ABAIXO PARA CONHECER"
    bbox_cta = draw.textbbox((0, 0), cta_text, font=f_cta)
    cta_x = (width - (bbox_cta[2] - bbox_cta[0])) // 2
    
    # Barra suave com setas
    draw.text((cta_x, cta_y), cta_text, font=f_cta, fill=(248, 250, 252, 230))
    
    # Seta visual sutil indicando o sticker de link que fica logo abaixo
    arrow_y = cta_y + 45
    draw.polygon([(width // 2 - 15, arrow_y), (width // 2 + 15, arrow_y), (width // 2, arrow_y + 16)], fill=(0, 242, 254, 255))
    
    # Efeito de moldura discreta de contorno geral
    draw.rounded_rectangle([20, 20, width - 20, height - 20], radius=32, outline=(255, 255, 255, 15), width=2)
    
    # 9. Salvar arquivo
    output_dir = "C:/Users/WINDOWS/Projects/estrategia-nerd/public/uploads/instagram"
    os.makedirs(output_dir, exist_ok=True)
    output_path = os.path.join(output_dir, "story_apresentacao_estrategia_nerd.png")
    
    base.save(output_path, "PNG", optimize=True)
    print(f"STORY_GERADO_SUCESSO: {output_path} ({width}x{height})")
    return output_path

if __name__ == "__main__":
    create_story()
