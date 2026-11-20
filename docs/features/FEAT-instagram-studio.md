# FEAT — Instagram Studio Pro (@estrategia_nerd via instagrapi)

## 1. Visão Geral e Objetivo

O **Instagram Studio Pro** (`/admin/instagram-studio`) é o **segundo dashboard** do Instagram no Estratégia Nerd. Ele opera em paralelo ao dashboard oficial existente (`/admin/instagram`, baseado na Meta Graph API v21.0), desbloqueando todas as capacidades da API móvel privada do Instagram através da biblioteca `instagrapi`.

Enquanto a API oficial da Meta impõe travas severas (proibição de música comercial em fotos/carrosséis, ausência total de endpoints para Destaques/Highlights, stories sem links clicáveis e obrigatoriedade de URLs públicas HTTPS em túnel), o Instagram Studio Pro disponibiliza:

1. **Feed com Música Oficial:** Upload de fotos estáticas (`photo_upload_with_music`) e carrosséis de 2 a 10 slides (`album_upload_with_music`) com faixas do catálogo do Instagram e controle de início em segundos.
2. **Stories & Destaques Unificados:** Assistente interativo completo embutido diretamente na aba "Stories & Destaques" (upload de até 20 imagens verticais 1080×1920, inserção de stickers de links HTTPS oficiais com prévia no celular, escolha de capa quadrada de destaque e motor de publicação durável em lotes com reconciliação anti-falhas). A rota antiga `/admin/instagram-destaques` redireciona automaticamente para `/admin/instagram-studio#stories`.
3. **Perfil & Bio Dinâmica:** Prévia realista de smartphone e atualização em tempo real do texto da biografia e link externo da bio (para direcionar tráfego para novos artigos do blog), além de fixação/desafixação de posts no topo (`media_pin`/`media_unpin`).
4. **Direct (DMs):** Envio seguro de links e mensagens individuais em resposta a interações de seguidores.
5. **Inteligência Geek & Benchmarking:** Mineração de posts virais por usuário (@) ou hashtag (#) com ordenação por engajamento.
6. **Métricas & Audiência:** Telemetria em tempo real direto da conta via Mobile API (taxa de engajamento baseada em seguidores, médias de curtidas e comentários, comparativo de desempenho por formato Foto vs Carrossel vs Vídeo e ranking dos Top 5 Posts).
7. **Espectadores de Stories:** Consulta dos usuários nominais que visualizaram cada story ativo nas últimas 24h com foto de perfil e username (recurso exclusivo da API mobile).

---

## 2. Decisões Arquiteturais e Segurança

### 2.1. Execução Estritamente Local (Local-Only)
- Protegido por `EnvironmentGuard::requireLocal()` e `Auth::require()`.
- Nenhuma chave ou sessão privada é enviada ou executada em servidores remotos de Stage ou Produção.
- Não há modificação destrutiva de banco de dados.

### 2.2. Motor Python Desacoplado (`tools/instagram-studio/`)
- **Runtime Isolado:** Utiliza o ambiente virtual em `storage/instagram-highlights/venv/` (Python 3.13, instagrapi 3.0.24).
- **Comunicação por Processos:** O PHP (`InstagramStudioService`) despacha chamadas via `proc_open` com `CREATE_NO_WINDOW` e `bypass_shell` no Windows, comunicando exclusivamente via JSON sanitizado em `stdin` e `stdout`.
- **Single-Attempt Protection:** Desativa loops de retentativa cega da biblioteca para evitar duplicações de posts ou bloqueios por repetição.
- **Circuit Breaker Anti-Ban:** Respeita pausas humanas e interrompe execuções se houver desafio (`challenge`) ou `feedback_required`.

---

## 3. Rotas e Contratos

Base: `/admin/instagram-studio`

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/admin/instagram-studio` | Dashboard visual principal com abas e prévias |
| `GET` | `/admin/instagram-studio/status` | Consulta status da sessão privada |
| `GET` | `/admin/instagram-studio/search-music` | Busca músicas no catálogo oficial por termo |
| `GET` | `/admin/instagram-studio/trending-music` | Lista faixas em alta/tendências |
| `POST` | `/admin/instagram-studio/upload-feed` | Publica foto ou carrossel com áudio opcional |
| `GET` | `/admin/instagram-studio/profile` | Obtém dados completos da bio, links e posts |
| `POST` | `/admin/instagram-studio/update-profile` | Atualiza biografia e link externo da bio |
| `POST` | `/admin/instagram-studio/pin-media` | Fixa ou desafixa um post no topo da grade |
| `GET` | `/admin/instagram-studio/search-viral` | Minera posts públicos por hashtag ou usuário |
| `POST` | `/admin/instagram-studio/send-dm` | Envia mensagem direta no privado |
| `GET` | `/admin/instagram-studio/metrics` | Telemetria ao vivo da conta: engajamento, ranking e formatos |
| `GET` | `/admin/instagram-studio/story-viewers` | Espectadores nominais dos stories ativos nas últimas 24h |

---

## 4. Testes e Validação

- **Testes Unitários Python:**
  ```bash
  storage\instagram-highlights\venv\Scripts\python.exe -m unittest discover -s tools/instagram-studio/tests -p "test_*.py" -v
  ```
- **Suíte Obrigatória de Integridade do Projeto:**
  ```bash
  C:\xampp\php\php.exe scripts/verify-changes.php
  ```
