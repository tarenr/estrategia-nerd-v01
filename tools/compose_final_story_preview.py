"""
Compõe a pré-visualização final do Story com a interface realista do Instagram:
- Barra de status e progresso do topo
- Perfil @estrategia_nerd com foto e tempo
- Link Sticker oficial do Instagram posicionado em y: 0.78
- Barra inferior de resposta 'Enviar mensagem...'
"""
import os
from PIL import Image, ImageDraw, ImageFont, ImageFilter

def compose_preview():
    base_path = "C:/Users/WINDOWS/Projects/estrategia-nerd/public/uploads/instagram/story_ia_echo_2d.jpg"
    base = Image.open(base_path).convert("RGBA")
    w, h = base.size # 1080 x 1920
    
    # Camada de sobreposição para a UI
    ui = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    draw = ImageDraw.Draw(ui)
    
    # 1. Gradiente sutil escuro no topo e na base para legibilidade da interface
    top_shadow = Image.new("RGBA", (w, 240), (0, 0, 0, 0))
    t_draw = ImageDraw.Draw(top_shadow)
    for y in range(240):
        alpha = int(120 * (1 - y / 240))
        t_draw.line([(0, y), (w, y)], fill=(0, 0, 0, alpha))
    ui.paste(top_shadow, (0, 0), top_shadow)
    
    bot_shadow = Image.new("RGBA", (w, 280), (0, 0, 0, 0))
    b_draw = ImageDraw.Draw(bot_shadow)
    for y in range(280):
        alpha = int(140 * (y / 280))
        b_draw.line([(0, y), (w, y)], fill=(0, 0, 0, alpha))
    ui.paste(bot_shadow, (0, h - 280), bot_shadow)
    
    draw = ImageDraw.Draw(ui)
    
    # 2. Barra de progresso do Story (topo)
    bar_y = 35
    bar_h = 4
    pad_x = 24
    draw.rounded_rectangle([pad_x, bar_y, w - pad_x, bar_y + bar_h], radius=2, fill=(255, 255, 255, 120))
    # Progresso atual (ex: 70% preenchido)
    draw.rounded_rectangle([pad_x, bar_y, int(pad_x + (w - 2 * pad_x) * 0.65), bar_y + bar_h], radius=2, fill=(255, 255, 255, 255))
    
    # 3. Header: Perfil @estrategia_nerd
    avatar_size = 64
    avatar_x = 28
    avatar_y = 58
    
    profile_path = "C:/Users/WINDOWS/Projects/estrategia-nerd/public/assets/instagram-feed/profile.jpg"
    if not os.path.exists(profile_path):
        profile_path = "C:/Users/WINDOWS/Projects/estrategia-nerd/public/assets/brand/logo-symbol.png"
        
    if os.path.exists(profile_path):
        try:
            p_img = Image.open(profile_path).convert("RGBA").resize((avatar_size, avatar_size), Image.Resampling.LANCZOS)
            # Máscara circular
            mask = Image.new("L", (avatar_size, avatar_size), 0)
            m_draw = ImageDraw.Draw(mask)
            m_draw.ellipse([0, 0, avatar_size, avatar_size], fill=255)
            ui.paste(p_img, (avatar_x, avatar_y), mask)
            # Anel sutil
            draw.ellipse([avatar_x, avatar_y, avatar_x + avatar_size, avatar_y + avatar_size], outline=(255, 255, 255, 180), width=2)
        except Exception as e:
            print(f"Erro avatar: {e}")
            
    # Textos do Header
    font_bold = "C:/Windows/Fonts/seguibld.ttf" if os.path.exists("C:/Windows/Fonts/seguibld.ttf") else "C:/Windows/Fonts/segoeuib.ttf"
    font_reg = "C:/Windows/Fonts/segoeui.ttf"
    
    f_user = ImageFont.truetype(font_bold, 28)
    f_time = ImageFont.truetype(font_reg, 24)
    f_close = ImageFont.truetype(font_bold, 36)
    
    draw.text((avatar_x + avatar_size + 18, avatar_y + 16), "estrategia_nerd", font=f_user, fill=(255, 255, 255, 255))
    draw.text((avatar_x + avatar_size + 240, avatar_y + 20), "1 m", font=f_time, fill=(200, 210, 220, 220))
    
    # Botão Fechar '✕'
    draw.text((w - 55, avatar_y + 12), "✕", font=f_close, fill=(255, 255, 255, 240))
    
    # 4. Link Sticker Oficial do Instagram (posicionado em y: 0.78 = 1498px)
    sticker_y = int(h * 0.78)
    sticker_text = "YOUTUBE.COM/@ESTRATEGIA_NERD"
    f_sticker = ImageFont.truetype(font_bold, 28)
    
    bbox_s = draw.textbbox((0, 0), sticker_text, font=f_sticker)
    st_w = bbox_s[2] - bbox_s[0] + 90
    st_h = 66
    st_x = (w - st_w) // 2
    
    # Sombra do sticker
    shadow_box = [st_x - 4, sticker_y - 2, st_x + st_w + 4, sticker_y + st_h + 6]
    st_shadow = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    sh_draw = ImageDraw.Draw(st_shadow)
    sh_draw.rounded_rectangle(shadow_box, radius=34, fill=(0, 0, 0, 90))
    st_shadow = st_shadow.filter(ImageFilter.GaussianBlur(8))
    ui = Image.alpha_composite(ui, st_shadow)
    draw = ImageDraw.Draw(ui)
    
    # Corpo do sticker (estilo oficial: branco com texto azul escuro)
    draw.rounded_rectangle([st_x, sticker_y, st_x + st_w, sticker_y + st_h], radius=33, fill=(255, 255, 255, 255))
    
    # Ícone de link 🔗 e texto
    f_icon = ImageFont.truetype(font_bold, 26)
    draw.text((st_x + 24, sticker_y + 18), "🔗", font=f_icon, fill=(0, 100, 220, 255))
    draw.text((st_x + 62, sticker_y + 18), sticker_text, font=f_sticker, fill=(18, 25, 38, 255))
    draw.text((st_x + st_w - 32, sticker_y + 18), "↗", font=f_sticker, fill=(100, 116, 139, 255))

    # 5. Barra Inferior de Resposta (estilo Instagram)
    bar_bot_y = h - 110
    input_w = w - 170
    input_h = 56
    input_x = 24
    
    draw.rounded_rectangle([input_x, bar_bot_y, input_x + input_w, bar_bot_y + input_h], radius=28, outline=(255, 255, 255, 140), width=2, fill=(0, 0, 0, 70))
    
    f_reply = ImageFont.truetype(font_reg, 24)
    draw.text((input_x + 28, bar_bot_y + 14), "Enviar mensagem...", font=f_reply, fill=(255, 255, 255, 180))
    
    # Ícones de Curtir (Coração) e Aviãozinho (Compartilhar)
    f_icons_bot = ImageFont.truetype(font_bold, 32)
    draw.text((w - 125, bar_bot_y + 10), "🤍", font=f_icons_bot, fill=(255, 255, 255, 230))
    draw.text((w - 65, bar_bot_y + 8), "✈", font=f_icons_bot, fill=(255, 255, 255, 230))

    # Composição final
    final_img = Image.alpha_composite(base, ui)
    
    out_dir = "C:/Users/WINDOWS/Projects/estrategia-nerd/public/uploads/instagram"
    out_path = os.path.join(out_dir, "preview_story_final.png")
    final_img.save(out_path, "PNG", optimize=True)
    
    # Salva também no diretório do brain para exibição no artefato
    brain_path = "C:/Users/WINDOWS/.gemini/antigravity/brain/a43fbf16-a848-47dc-ad50-7e76ee34a8a1/preview_story_final.png"
    final_img.save(brain_path, "PNG", optimize=True)
    
    print(f"PREVIEW_STORY_CRIADO: {out_path} ({w}x{h})")
    return out_path

if __name__ == "__main__":
    compose_preview()
