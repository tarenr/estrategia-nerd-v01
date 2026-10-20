---
name: agendar-reel-instagram
description: Agenda um Reel pronto (vídeo 1080x1920 + capa JPEG) na fila local do Instagram do @estrategia_nerd - confere a fila do dia, escolhe o horário com o usuário, copia vídeo e capa para public/uploads/reels/<nome>/, cria o post agendado pelo InstagramPostRepository e confere o resultado. Use quando o usuário pedir para agendar, programar ou colocar na fila um Reel ou vídeo já aprovado, ou disser "post deve ser agendado para amanhã".
---

# Agendar Reel na fila do Instagram

Esta skill **não** autoriza escrita: copiar arquivos e criar o post exigem plano e "Aprovado" do usuário (ritual do `CLAUDE.md` de `C:\Users\WINDOWS\Projects`). Consultar o banco local, mesmo só para ler, exige perguntar antes ("Posso consultar a fila de <data> no banco local, só leitura?").

## Antes do plano (leitura)

1. Vídeo final e capa aprovados (capa JPEG 1080x1920 **obrigatória**; sem ela a publicação é bloqueada).
2. Texto do post (legenda + hashtags) mostrado ao usuário e aprovado.
3. Com permissão, listar a fila do dia (SELECT em `instagram_posts` por `agendado_para`, ignorando `cancelado`/`excluido`). Grade oficial: 12:00 e 19:30. Se o horário estiver ocupado, propor outro (o usuário prefere outro horário a mexer em post já agendado; Reels manuais já usaram 16:00).

## Execução (depois do "Aprovado")

1. Copiar para `estrategia-nerd/public/uploads/reels/<nome>/` como `reel.mp4` e `capa.jpg`; conferir hash igual ao original. Não sobrescrever pasta existente.
2. Conferir pelo endereço público **sem `/public`**: `https://nerd.tfr-info.com.br/uploads/reels/<nome>/reel.mp4` e `capa.jpg` devem responder 200 (com `/public/...` o Cloudflare Access redireciona para login).
3. Script PHP temporário **fora do projeto** (scratchpad), com `require 'C:/Users/WINDOWS/Projects/estrategia-nerd/bootstrap.php'` e `App\Repositories\InstagramPostRepository`:
   - parar se já existir post com o mesmo `video_rendered_path` ou outro post no mesmo `agendado_para`;
   - `create([...'account_id' => 1, 'status' => 'agendado', 'tipo' => 'reels', 'legenda' => ..., 'hashtags_count' => N, 'agendado_para' => 'AAAA-MM-DD HH:MM:SS', 'video_rendered_path' => 'uploads/reels/<nome>/reel.mp4', 'render_status' => 'ready', 'idempotency_key' => <UUID v4>, 'origin' => 'local'])`;
   - `addMedia($id, ['ordem' => 0, 'tipo_arquivo' => 'video', 'caminho' => ..., 'largura' => 1080, 'altura' => 1920, 'duracao_s' => <s>])`;
   - `markRenderedReady($id, $caminho)` (o `addMedia` zera o vídeo renderizado);
   - gravar o resultado em JSON UTF-8 e ler o arquivo (o terminal do Windows estraga acentos).
4. Listar a fila de novo e confirmar: o post novo como `agendado` no horário certo e os outros intactos.
5. Forge (projeto 4) e memória da fila (`project_estrategia_nerd_fila_instagram_reels.md`).

## Avisos ao usuário

- O publicador (`EstrategiaNerd-InstagramPublicarAgendados`, a cada 5 min) posta sozinho; o PC precisa estar ligado e o túnel no ar no horário.
- Não publicar manualmente nem mexer em post já agendado sem pedido.
- Registro de exemplo: Manifesto, post #23397, 21/10/2026 16:00 (`docs/governanca/molde-serie-echo.md`).
