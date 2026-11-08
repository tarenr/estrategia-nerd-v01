# Guia de Portabilidade: Integrações do TryPost para o Estratégia Nerd

Este documento serve como referência técnica detalhada para qualquer IA ou desenvolvedor que for implementar melhorias no publicador do Estratégia Nerd ou portar novos conectores de redes sociais a partir do TryPost.

---

## 1. Localização dos Projetos no Sistema

- **Origem (TryPost):** `C:\Users\WINDOWS\Projects\apps\trypost\app\Services\Social\`
- **Destino (Estratégia Nerd):** `C:\Users\WINDOWS\Projects\estrategia-nerd\app\Services\`

Ambos os projetos são desenvolvidos em **PHP**, o que permite portar a lógica diretamente sem dependência de containers Docker ou Node.js.

---

## 2. Melhorias para o que já temos (Instagram)

### Arquivos Envolvidos:
- **Origem:** `C:\Users\WINDOWS\Projects\apps\trypost\app\Services\Social\InstagramPublisher.php`
- **Destino:** `C:\Users\WINDOWS\Projects\estrategia-nerd\app\Services\Instagram\InstagramApiService.php` e `InstagramFeedPublisher.php`

### O que implementar:
1. **Renovação Proativa de Token (`ConnectionVerifier`):**
   - Verificar se o token de acesso da Meta expira nas próximas 24 horas antes do envio agendado.
   - Chamar `oauth/access_token` com `grant_type=fb_exchange_token` para renovar o token de longa duração preventivamente, evitando falha no momento do disparo.
2. **Mecanismo Anti-Duplicação (`PublishCheckpoint`):**
   - No envio de Reels, salvar o ID do container temporário (`creation_id`) retornado pela Meta no banco de dados.
   - Se houver timeout ou queda de conexão antes da publicação final (`media_publish`), consultar o status do `creation_id` em vez de criar um novo container e duplicar o Reel.
3. **Suporte a Carrossel Multi-Mídia:**
   - Aproveitar o fluxo de criação de itens filhos (`carousel_children`) para permitir agendar sequências de imagens e vídeos no mesmo post.

---

## 3. Novo Conector: TikTok (Vídeos Verticais do Echo)

### Arquivos Envolvidos:
- **Origem:** `C:\Users\WINDOWS\Projects\apps\trypost\app\Services\Social\TikTokPublisher.php`
- **Destino:** `C:\Users\WINDOWS\Projects\estrategia-nerd\app\Services\Social\TikTokPublishService.php`

### O que implementar:
1. **Upload Direto para a TikTok Content Posting API:**
   - Inicia o envio em `https://open.tiktokapis.com/v2/post/publish/video/init/`.
   - Faz o upload do binário do arquivo MP4 (`1080x1920`) renderizado pelo Echo.
2. **Consulta Assíncrona de Status:**
   - Consultar o status de processamento do vídeo (`publish_id`) em intervalos de 30s e 60s até a confirmação de publicação.
3. **Privacidade e Configurações:**
   - Suporte a privacidade (`PUBLIC_TO_EVERYONE`, `MUTUAL_FOLLOW_FRIENDS`, `SELF_ONLY`), permissão de comentários, duet e stitch.

---

## 4. Novo Conector: Threads da Meta

### Arquivos Envolvidos:
- **Origem:** `C:\Users\WINDOWS\Projects\apps\trypost\app\Services\Social\ThreadsPublisher.php`
- **Destino:** `C:\Users\WINDOWS\Projects\estrategia-nerd\app\Services\Social\ThreadsPublishService.php`

### O que implementar:
1. **Aproveitamento das Credenciais da Meta:**
   - Utiliza a Threads Publishing API oficial da Meta.
   - Criação de container de texto (`media_type=TEXT`) ou imagem/vídeo para postar resumos de notícias do portal e links de artigos.

---

## 5. Tarefas Cadastradas no The Forge

As tarefas correspondentes foram implementadas e homologadas na versão 2.15.0 com suíte de testes determinística em `scripts/verify-social-integrations.php`:
1. `[Integrações-Social] - Blindar Instagram: Renovação proativa e anti-duplicação` (Tarefa #1924) — **Concluída**
   - Métodos `inspectToken()` e `refreshLongLivedToken()` em `InstagramApiService.php`.
   - Método `updateAccountToken()` em `InstagramPostRepository.php`.
   - Reutilização defensiva de `creation_id` em `InstagramPublishConfirmationService.php` e renovação proativa no CLI agendado `scripts/en-instagram-publish-scheduled.php`.
2. `[Integrações-Social] - Portar conector do TikTok para vídeos verticais` (Tarefa #1925) — **Concluída**
   - Implementado em `app/Services/Social/TikTokPublishService.php`. Suporta `initVideoPublish()`, `fetchPublishStatus()`, `waitForPublishCompletion()` e `refreshAccessToken()`.
3. `[Integrações-Social] - Portar conector do Threads da Meta` (Tarefa #1926) — **Concluída**
   - Implementado em `app/Services/Social/ThreadsPublishService.php`. Suporta texto, fotos, vídeos, carrosséis, consulta de status e renovação de token (`th_refresh_token`).
