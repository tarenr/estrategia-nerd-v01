"""
Substitui o post anterior no Feed do Instagram:
1. Deleta a media anterior (4018801690463005057)
2. Publica o novo post com a Imagem 1 e a trilha Timecop1983 - On the Run
3. Atualiza os registros no MySQL local
"""
import json
import sys
import subprocess
from pathlib import Path

SCRIPT_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = SCRIPT_DIR.parent
STORAGE_ROOT = PROJECT_ROOT / "storage" / "instagram-highlights"
SESSION_FILE = STORAGE_ROOT / "auth" / "session.json"

sys.path.insert(0, str(PROJECT_ROOT / "tools" / "instagram-studio"))
from adapter import InstagramStudioAdapter

def main():
    print("=== 1. CONECTANDO AO INSTAGRAM VIA ADAPTER ===")
    adapter = InstagramStudioAdapter(SESSION_FILE)
    print(f"Autenticado com sucesso. User ID: {adapter.client.user_id}")

    # 1. Deletar post antigo
    old_media_id = "4018801690463005057"
    print(f"\n=== 2. REMOVENDO POST ANTERIOR ({old_media_id}) DO INSTAGRAM ===")
    try:
        del_res = adapter.client.media_delete(old_media_id)
        print(f"Resultado da exclusão: {del_res}")
    except Exception as e:
        print(f"Aviso ao deletar media {old_media_id}: {e}")

    # 2. Localizar trilha Timecop1983 - On the Run
    print("\n=== 3. LOCALIZANDO TRILHA NATIVA: Timecop1983 - On the Run ===")
    tracks = adapter.search_music("Timecop1983 On the Run")
    if not tracks:
        raise RuntimeError("Trilha Timecop1983 não encontrada no Instagram.")
    selected_track = tracks[0]
    print(f"Trilha: {selected_track['title']} por {selected_track['display_artist']} (ID: {selected_track['id']})")

    # 3. Publicar novo post com Imagem 1
    feed_img = PROJECT_ROOT / "public" / "uploads" / "instagram" / "echo" / "conheca-o-echo.jpg"
    caption = (
        "Nos bastidores do Estratégia Nerd, quem organiza as pesquisas, sintetiza dados e acompanha os testes no laboratório é o Echo 🤖\n\n"
        "Escrevemos um artigo completo no blog contando como ele foi concebido, a arquitetura por trás do assistente e o que vem pela frente nos próximos projetos do canal.\n\n"
        "👉 Acesse pelo link na bio ou direto em:\n"
        "estrategianerd.com.br/artigo/conheca-o-echo-assistente-estrategia-nerd\n\n"
        "#EstrategiaNerd #Echo #InteligenciaArtificial #Tech #Dev #Synthwave #Robotics"
    )

    print("\n=== 4. PUBLICANDO NOVO POST OFICIAL NO FEED COM MÚSICA ===")
    track_payload = selected_track.get("raw_dict") or selected_track
    feed_res = adapter.upload_photo(
        path=feed_img,
        caption=caption,
        track_dict=track_payload,
        start_time_ms=0
    )
    print("Novo post publicado com sucesso no Feed!")
    print(json.dumps(feed_res, indent=2))

    new_pk = feed_res.get("id") or feed_res.get("pk")
    new_url = feed_res.get("url") or f"https://www.instagram.com/p/{feed_res.get('code')}/"

    # 4. Atualizar no MySQL
    print("\n=== 5. ATUALIZANDO BANCO DE DADOS LOCAL (MySQL) ===")
    mysql_bin = "C:/xampp/mysql/bin/mysql.exe"
    
    # Atualiza instagram_posts (id 55009)
    sql_up_post = f"""
    UPDATE instagram_posts 
    SET ig_media_id = '{new_pk}', permalink = '{new_url}', publicado_em = NOW(), atualizado_em = NOW()
    WHERE id = 55009;
    """
    subprocess.run([mysql_bin, "-u", "root", "estrategia-nerd", "-e", sql_up_post], check=False)
    
    # Atualiza instagram_post_media
    sql_up_media = f"""
    UPDATE instagram_post_media 
    SET url_publica = '{new_url}', largura = 1024, altura = 1024
    WHERE post_id = 55009;
    """
    subprocess.run([mysql_bin, "-u", "root", "estrategia-nerd", "-e", sql_up_media], check=False)
    print(f"Banco atualizado com o novo post ID {new_pk} ({new_url}).")

    print("\n=== SUCESSO TOTAL: POST DO FEED SUBSTITUÍDO COM A ARTE CORRETA! ===")

if __name__ == "__main__":
    main()
