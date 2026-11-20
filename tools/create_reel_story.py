"""
Gera a arte do Story de divulgação do Reel no Instagram (1080x1920)
- Versão limpa para upload (story_divulgacao_reel.jpg)
- Versão prévia com a UI e Link Sticker do Instagram (preview_story_final.png)
"""
import os
from PIL import Image, ImageDraw, ImageFont, ImageFilter

def generate_reel_story():
    base_path = "C:/Users/WINDOWS/Projects/estrategia-nerd/public/uploads/instagram/story_ia_echo_2d.jpg"
    base = Image.open(base_path).convert("RGBA")
    w, h = base.size # 1080 x 1920
    
    # Camada de texto e elementos de design para o Story limpo
    overlay = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    draw = ImageDraw.Draw(overlay)
    
    font_bold = "C:/Windows/Fonts/seguibl.ttf" if os.path.exists("C:/Windows/Fonts/seguibl.ttf") else "C:/Windows/Fonts/segoeuib.ttf"
    font_semi = "C:/Windows/Fonts/seguisb.ttf" if os.path.exists("C:/Windows/Fonts/seguisb.ttf") else "C:/Windows/Fonts/segoeuib.ttf"
    font_reg = "C:/Windows/Fonts/segoeui.ttf"
    
    f_badge = ImageFont.truetype(font_semi, 24)
    f_title = ImageFont.truetype(font_bold, 56)
    f_balloon = ImageFont.truetype(font_semi, 32)
    f_cta = ImageFont.truetype(font_bold, 28)
    
    # 1. Gradientes de realce no topo e na base
    top_shadow = Image.new("RGBA", (w, 380), (0, 0, 0, 0))
    t_draw = ImageDraw.Draw(top_shadow)
    for y in range(380):
        alpha = int(170 * (1 - y / 380))
        t_draw.line([(0, y), (w, y)], fill=(4, 9, 20, alpha))
    overlay.paste(top_shadow, (0, 0), top_shadow)
    
    bot_shadow = Image.new("RGBA", (w, 480), (0, 0, 0, 0))
    b_draw = ImageDraw.Draw(bot_shadow)
    for y in range(480):
        alpha = int(190 * (y / 480))
        b_draw.line([(0, y), (w, y)], fill=(3, 7, 18, alpha))
    overlay.paste(bot_shadow, (0, h - 480), bot_shadow)
    
    draw = ImageDraw.Draw(overlay)

    # 2. Topo: Badge "🚀 DROP NOVO NO AR" (a partir de y=260 para respeitar a interface do IG)
    badge_text = "🚀  DROP NOVO NO FEED"
    bbox_b = draw.textbbox((0, 0), badge_text, font=f_badge)
    bw = bbox_b[2] - bbox_b[0] + 48
    bh = 46
    bx = (w - bw) // 2
    by = 265
    
    draw.rounded_rectangle([bx, by, bx + bw, by + bh], radius=23, fill=(13, 27, 56, 210), outline=(0, 212, 255, 200), width=2)
    draw.text((bx + 24, by + 10), badge_text, font=f_badge, fill=(0, 212, 255, 255))
    
    # 3. Manchete: "VÍDEO NOVO NO PERFIL!"
    title_text = "VÍDEO NOVO NO PERFIL!"
    bbox_t = draw.textbbox((0, 0), title_text, font=f_title)
    tx = (w - (bbox_t[2] - bbox_t[0])) // 2
    ty = 330
    
    # Sombra do texto para contraste máximo
    draw.text((tx + 2, ty + 2), title_text, font=f_title, fill=(0, 0, 0, 220))
    draw.text((tx, ty), title_text, font=f_title, fill=(255, 255, 255, 255))
    
    # 4. Balão de fala do Echo (y: 1200)
    msg_line1 = "“Acabou de sair vídeo novo no feed!”"
    msg_line2 = "Corre pra assistir e deixar sua opinião."
    
    card_w = 860
    card_h = 135
    card_x = (w - card_w) // 2
    card_y = 1200
    
    # Card glassmorphic moderno
    card_layer = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    c_draw = ImageDraw.Draw(card_layer)
    c_draw.rounded_rectangle([card_x, card_y, card_x + card_w, card_y + card_h], radius=24, fill=(10, 18, 38, 220), outline=(0, 212, 255, 120), width=2)
    overlay = Image.alpha_composite(overlay, card_layer)
    draw = ImageDraw.Draw(overlay)
    
    bbox_m1 = draw.textbbox((0, 0), msg_line1, font=f_balloon)
    draw.text(((w - (bbox_m1[2] - bbox_m1[0])) // 2, card_y + 24), msg_line1, font=f_balloon, fill=(255, 255, 255, 255))
    
    f_sub = ImageFont.truetype(font_reg, 26)
    bbox_m2 = draw.textbbox((0, 0), msg_line2, font=f_sub)
    draw.text(((w - (bbox_m2[2] - bbox_m2[0])) // 2, card_y + 76), msg_line2, font=f_sub, fill=(148, 163, 184, 255))
    
    # 5. Indicação do Link Sticker (y: 1420)
    cta_text = "TOQUE NO LINK ABAIXO PARA ASSISTIR"
    bbox_c = draw.textbbox((0, 0), cta_text, font=f_cta)
    cx = (w - (bbox_c[2] - bbox_c[0])) // 2
    cy = 1420
    draw.text((cx, cy), cta_text, font=f_cta, fill=(56, 189, 248, 255))
    
    # Seta animada sutil para baixo
    draw.polygon([(w // 2 - 14, cy + 42), (w // 2 + 14, cy + 42), (w // 2, cy + 56)], fill=(0, 212, 255, 255))
    
    # Salva a versão limpa pronta para upload
    clean_story = Image.alpha_composite(base, overlay)
    
    out_dir = "C:/Users/WINDOWS/Projects/estrategia-nerd/public/uploads/instagram"
    clean_path = os.path.join(out_dir, "story_divulgacao_reel.jpg")
    clean_story.convert("RGB").save(clean_path, "JPEG", quality=95)
    
    # 6. GERA A PRÉVIA COMPLETA COM A UI DO INSTAGRAM E O LINK STICKER
    preview = clean_story.copy()
    ui_layer = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    u_draw = ImageDraw.Draw(ui_layer)
    
    # Barra de progresso do Instagram (topo)
    bar_y = 35
    bar_h = 4
    pad_x = 24
    u_draw.rounded_rectangle([pad_x, bar_y, w - pad_x, bar_y + bar_h], radius=2, fill=(255, 255, 255, 120))
    u_draw.rounded_rectangle([pad_x, bar_y, int(pad_x + (w - 2 * pad_x) * 0.7), bar_y + bar_h], radius=2, fill=(255, 255, 255, 255))
    
    # Header do usuário @estrategia_nerd
    avatar_size = 64
    avatar_x = 28
    avatar_y = 58
    
    profile_path = "C:/Users/WINDOWS/Projects/estrategia-nerd/public/assets/instagram-feed/profile.jpg"
    if os.path.exists(profile_path):
        p_img = Image.open(profile_path).convert("RGBA").resize((avatar_size, avatar_size), Image.Resampling.LANCZOS)
        mask = Image.new("L", (avatar_size, avatar_size), 0)
        ImageDraw.Draw(mask).ellipse([0, 0, avatar_size, avatar_size], fill=255)
        ui_layer.paste(p_img, (avatar_x, avatar_y), mask)
        u_draw.ellipse([avatar_x, avatar_y, avatar_x + avatar_size, avatar_y + avatar_size], outline=(255, 255, 255, 180), width=2)
        
    f_user = ImageFont.truetype(font_bold, 28)
    f_time = ImageFont.truetype(font_reg, 24)
    f_close = ImageFont.truetype(font_bold, 36)
    
    u_draw.text((avatar_x + avatar_size + 18, avatar_y + 16), "estrategia_nerd", font=f_user, fill=(255, 255, 255, 255))
    u_draw.text((avatar_x + avatar_size + 240, avatar_y + 20), "agora", font=f_time, fill=(200, 210, 220, 220))
    u_draw.text((w - 55, avatar_y + 12), "✕", font=f_close, fill=(255, 255, 255, 240))
    
    # 7. LINK STICKER OFICIAL DO INSTAGRAM: "ASSISTIR REEL 🎬"
    # Posicionado exatamente em y: 0.78 = 1498px
    sticker_y = int(h * 0.78)
    sticker_text = "ASSISTIR REEL 🎬"
    f_stk = ImageFont.truetype(font_bold, 30)
    
    bbox_st = u_draw.textbbox((0, 0), sticker_text, font=f_stk)
    st_w = bbox_st[2] - bbox_st[0] + 110
    st_h = 72
    st_x = (w - st_w) // 2
    
    # Sombra do sticker
    sh_layer = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    sh_draw = ImageDraw.Draw(sh_layer)
    sh_draw.rounded_rectangle([st_x - 3, sticker_y - 2, st_x + st_w + 3, sticker_y + st_h + 6], radius=36, fill=(0, 0, 0, 110))
    sh_layer = sh_layer.filter(ImageFilter.GaussianBlur(8))
    ui_layer = Image.alpha_composite(ui_layer, sh_layer)
    u_draw = ImageDraw.Draw(ui_layer)
    
    # Corpo do sticker branco
    u_draw.rounded_rectangle([st_x, sticker_y, st_x + st_w, sticker_y + st_h], radius=36, fill=(255, 255, 255, 255))
    
    # Ícone e texto
    f_ic = ImageFont.truetype(font_bold, 28)
    u_draw.text((st_x + 28, sticker_y + 20), "🔗", font=f_ic, fill=(0, 100, 220, 255))
    u_draw.text((st_x + 72, sticker_y + 20), sticker_text, font=f_stk, fill=(15, 23, 42, 255))
    u_draw.text((st_x + st_w - 38, sticker_y + 20), "↗", font=f_stk, fill=(100, 116, 139, 255))
    
    # Barra inferior de envio "Enviar mensagem..."
    bar_bot_y = h - 110
    input_w = w - 170
    input_h = 56
    input_x = 24
    
    u_draw.rounded_rectangle([input_x, bar_bot_y, input_x + input_w, bar_bot_y + input_h], radius=28, outline=(255, 255, 255, 140), width=2, fill=(0, 0, 0, 80))
    f_reply = ImageFont.truetype(font_reg, 24)
    u_draw.text((input_x + 28, bar_bot_y + 14), "Enviar mensagem...", font=f_reply, fill=(255, 255, 255, 180))
    
    f_icons_bot = ImageFont.truetype(font_bold, 32)
    u_draw.text((w - 125, bar_bot_y + 10), "🤍", font=f_icons_bot, fill=(255, 255, 255, 230))
    u_draw.text((w - 65, bar_bot_y + 8), "✈", font=f_icons_bot, fill=(255, 255, 255, 230))
    
    # Salva a imagem da prévia final
    preview_final = Image.alpha_composite(preview, ui_layer)
    preview_path = os.path.join(out_dir, "preview_story_final.png")
    preview_final.save(preview_path, "PNG", optimize=True)
    
    brain_path = "C:/Users/WINDOWS/.gemini/antigravity/brain/a43fbf16-a848-47dc-ad50-7e76ee34a8a1/preview_story_final.png"
    preview_final.save(brain_path, "PNG", optimize=True)
    
    print(f"STORY_REEL_GERADO_COM_SUCESSO:")
    print(f"- Clean: {clean_path}")
    print(f"- Preview: {preview_path}")
    return clean_path, preview_path

if __name__ == "__main__":
    generate_reel_story()
