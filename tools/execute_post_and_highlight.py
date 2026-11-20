"""
Executa a publicação do Post do Echo no Feed com música (Timecop1983 - On the Run),
registra no MySQL e em seguida publica o Story e cria o Destaque 'Echo!' no perfil.
"""
import json
import os
import sys
import subprocess
from pathlib import Path
from instagrapi.types import StoryLink

SCRIPT_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = SCRIPT_DIR.parent
STORAGE_ROOT = PROJECT_ROOT / "storage" / "instagram-highlights"
SESSION_FILE = STORAGE_ROOT / "auth" / "session.json"

sys.path.insert(0, str(PROJECT_ROOT / "tools" / "instagram-studio"))
from adapter import InstagramStudioAdapter
from public_urls import article_url

def main():
    print("=== 1. CONECTANDO AO INSTAGRAM VIA ADAPTER ===")
    adapter = InstagramStudioAdapter(SESSION_FILE)
    print(f"Autenticado com sucesso. User ID: {adapter.client.user_id}")

    # 1. Localizar a trilha Timecop1983 - On the Run
    print("\n=== 2. LOCALIZANDO TRILHA NATIVA: Timecop1983 - On the Run ===")
    tracks = adapter.search_music("Timecop1983 On the Run")
    if not tracks:
        raise RuntimeError("Trilha Timecop1983 On the Run não encontrada no catálogo do Instagram.")
    
    selected_track = tracks[0]
    print(f"Trilha encontrada: {selected_track['title']} por {selected_track['display_artist']} (ID: {selected_track['id']})")
    
    # 2. Publicar Post no Feed
    feed_img = PROJECT_ROOT / "public" / "uploads" / "instagram" / "echo" / "conheca-o-echo.jpg"
    if not feed_img.is_file():
        raise FileNotFoundError(f"Imagem do feed não encontrada: {feed_img}")
        
    caption = (
        "Nos bastidores do Estratégia Nerd, quem organiza as pesquisas, sintetiza dados e acompanha os testes no laboratório é o Echo 🤖\n\n"
        "Escrevemos um artigo completo no blog contando como ele foi concebido, a arquitetura por trás do assistente e o que vem pela frente nos próximos projetos do canal.\n\n"
        "👉 Acesse pelo link na bio ou direto em:\n"
        f"{article_url('conheca-o-echo-assistente-estrategia-nerd')}\n\n"
        "#EstrategiaNerd #Echo #InteligenciaArtificial #Tech #Dev #Synthwave #Robotics"
    )
    
    print("\n=== 3. PUBLICANDO POST NO FEED COM MÚSICA ===")
    # Usa o raw_dict para garantir que todos os parâmetros exigidos pela API interna estejam presentes
    track_payload = selected_track.get("raw_dict") or selected_track
    feed_res = adapter.upload_photo(
        path=feed_img,
        caption=caption,
        track_dict=track_payload,
        start_time_ms=0
    )
    
    print("Post publicado com sucesso no feed!")
    print(json.dumps(feed_res, indent=2))
    
    post_pk = feed_res.get("id") or feed_res.get("pk")
    permalink = feed_res.get("url") or f"https://www.instagram.com/p/{feed_res.get('code')}/"
    
    # 3. Registrar no MySQL
    print("\n=== 4. REGISTRANDO NO BANCO DE DADOS LOCAL (MySQL) ===")
    sql_post = f"""
    INSERT INTO instagram_posts (
        account_id, status, tipo, legenda, hashtags_count, curtidas, comentarios_count,
        publicado_em, post_blog_id, ig_media_id, permalink, origin, criado_em, atualizado_em
    ) VALUES (
        1, 'publicado', 'imagem', '{caption.replace("'", "''")}', 7, 0, 0,
        NOW(), 88, '{post_pk}', '{permalink}', 'local', NOW(), NOW()
    );
    """
    
    # Executa via mysql client do XAMPP
    mysql_bin = "C:/xampp/mysql/bin/mysql.exe"
    p = subprocess.Popen([mysql_bin, "-u", "root", "estrategia-nerd", "-e", sql_post], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    out, err = p.communicate()
    if p.returncode != 0:
        print(f"Aviso MySQL post: {err.decode('utf-8', errors='ignore')}")
    else:
        print(f"Registro criado em instagram_posts para media {post_pk} (Blog Post 88).")
        
    # Obtém o ID inserido
    p_id = subprocess.Popen([mysql_bin, "-u", "root", "estrategia-nerd", "-e", f"SELECT id FROM instagram_posts WHERE ig_media_id = '{post_pk}' LIMIT 1;"], stdout=subprocess.PIPE)
    out_id, _ = p_id.communicate()
    lines = out_id.decode("utf-8").strip().split("\n")
    inserted_id = lines[1].strip() if len(lines) > 1 else None
    
    if inserted_id:
        sql_media = f"""
        INSERT INTO instagram_post_media (
            post_id, ordem, tipo_arquivo, caminho, url_publica, largura, altura, criado_em
        ) VALUES (
            {inserted_id}, 0, 'imagem', 'uploads/instagram/echo/conheca-o-echo.jpg',
            '{permalink}', 1254, 1254, NOW()
        );
        """
        subprocess.run([mysql_bin, "-u", "root", "estrategia-nerd", "-e", sql_media], check=False)
        print(f"Mídia vinculada em instagram_post_media com post_id {inserted_id}.")

    # 4. Publicar Story e Adicionar ao Destaque
    print("\n=== 5. PUBLICANDO STORY VERTICAL E CRIANDO DESTAQUE 'Echo!' ===")
    story_img = PROJECT_ROOT / "public" / "uploads" / "instagram" / "echo" / "conheca-o-echo-story.jpg"
    if not story_img.is_file():
        raise FileNotFoundError(f"Imagem do story não encontrada: {story_img}")
        
    story_article_url = article_url("conheca-o-echo-assistente-estrategia-nerd")
    link_sticker = StoryLink(
        webUri=story_article_url,
        x=0.5,
        y=0.70,
        width=0.38,
        height=0.07
    )
    
    story_res = adapter.client.photo_upload_to_story(
        story_img,
        caption="Conheça o Echo! 🤖",
        links=[link_sticker]
    )
    
    story_pk = getattr(story_res, "pk", "") or getattr(story_res, "id", "")
    print(f"Story publicado com sucesso! PK: {story_pk}")
    
    # Criar ou adicionar ao Destaque "Echo!"
    print("\n=== 6. ADICIONANDO AO DESTAQUE 'Echo!' ===")
    existing_highlights = adapter.client.user_highlights(adapter.client.user_id)
    target_hl = None
    for h in existing_highlights:
        if h.title.strip().lower() in ["echo!", "echo"]:
            target_hl = h
            break
            
    if target_hl:
        print(f"Destaque existente '{target_hl.title}' encontrado (ID: {target_hl.pk}).")
        # Se já existe, adiciona a story
        adapter.client.highlight_change_stories(target_hl.pk, added_story_ids=[story_pk], removed_story_ids=[])
        print(f"Story {story_pk} adicionado ao Destaque '{target_hl.title}'.")
    else:
        print("Criando novo Destaque 'Echo!'...")
        new_hl = adapter.client.highlight_create(
            title="Echo!",
            story_ids=[story_pk],
            cover_story_id=story_pk
        )
        print(f"Destaque 'Echo!' criado com sucesso! PK: {getattr(new_hl, 'pk', '')}")
        
    print("\n=== SUCESSO TOTAL: FEED + STORY + DESTAQUE CONCLUÍDOS! ===")

if __name__ == "__main__":
    main()
