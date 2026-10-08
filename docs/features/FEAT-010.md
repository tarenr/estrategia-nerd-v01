# FEAT-010 — Módulo Instagram

> **Status:** Em desenvolvimento  
> **Prioridade:** Alta  
> **Responsável:** Taren Felipe Ribeiro  
> **Criado em:** 2026-09-27

---

## Objetivo

Implementar um módulo completo de gerenciamento do Instagram diretamente
no painel administrativo (`/admin/instagram`), cobrindo:

- Visão geral da conta e métricas de desempenho
- Criação, edição e agendamento de posts (imagem, carrossel, reels, story)
- Preview interativo no estilo feed do Instagram
- Publicação via Meta Content Publishing API (Graph API v21.0)
- Script CLI de publicação automática de posts agendados
- Cache de insights e histórico de seguidores

---

## Arquitetura

```
app/
├── Controllers/Admin/InstagramController.php   ← Endpoints admin
├── Repositories/InstagramPostRepository.php    ← CRUD local + cache insights
├── Services/Instagram/InstagramApiService.php  ← Meta Graph API v21.0
└── Views/admin/instagram/
    ├── index.php   ← Dashboard
    ├── create.php  ← Criar post
    ├── edit.php    ← Editar post
    └── show.php    ← Detalhes + insights

scripts/en-instagram-publish-scheduled.php      ← CLI de publicação agendada
scripts/en-instagram-migration.php              ← Migration das 4 tabelas

config/routes.php                               ← Rotas /admin/instagram/*
app/Views/components/admin/sidebar.php          ← Item "Instagram" em Crescimento
```

---

## Banco de Dados

### `instagram_accounts`

Armazena a conta Instagram conectada ao painel.

| Coluna | Tipo | Descrição |
|---|---|---|
| `id` | int unsigned PK | Auto-increment |
| `ig_user_id` | varchar(30) UNIQUE | ID numérico da conta na Meta |
| `username` | varchar(60) | @username |
| `access_token` | text | Token permanente de acesso à Graph API |
| `token_expires_at` | datetime | Validade do token (nulo = permanente) |
| `permissions` | json | Escopos autorizados |
| `profile_picture` | varchar(500) | URL da foto de perfil |
| `bio` | text | Biografia |
| `website` | varchar(255) | URL do site |
| `followers_count` | int | Seguidores |
| `follows_count` | int | Seguindo |
| `media_count` | int | Total de mídias |
| `synced_at` | datetime | Última sincronização com a Meta |
| `ativo` | tinyint(1) | Conta ativa (1) ou inativa (0) |

### `instagram_posts`

Posts locais: rascunhos, agendados, em publicação, publicados e com erro.

| Coluna | Tipo | Descrição |
|---|---|---|
| `id` | int unsigned PK | |
| `account_id` | int unsigned FK | Referência a `instagram_accounts.id` |
| `status` | enum | `rascunho` · `agendado` · `publicando` · `publicado` · `erro` · `cancelado` |
| `tipo` | enum | `imagem` · `carrossel` · `reels` · `story` |
| `legenda` | text | Texto do post (máx 2.200 chars, máx 30 hashtags) |
| `hashtags_count` | tinyint | Quantidade de hashtags na legenda |
| `agendado_para` | datetime | Data/hora de publicação agendada |
| `publicado_em` | datetime | Data/hora de publicação efetiva |
| `post_blog_id` | int | Referência a `posts.id` (opcional) |
| `creation_id` | varchar(50) | ID do container criado na Meta |
| `ig_media_id` | varchar(50) UNIQUE | ID da mídia publicada no Instagram |
| `permalink` | varchar(500) | URL permanente do post |
| `idempotency_key` | varchar(36) UNIQUE | UUID para prevenir publicação dupla |
| `error_log` | text | Mensagem de erro (quando status = 'erro') |
| `origin` | enum | `local` (criado aqui) · `instagram` (sincronizado) |
| `criado_por` | int | ID do usuário que criou |

### `instagram_post_media`

Arquivos de mídia associados a cada post (suporta carrossel de 2–10 itens).

| Coluna | Tipo | Descrição |
|---|---|---|
| `id` | int unsigned PK | |
| `post_id` | int unsigned FK | Referência a `instagram_posts.id` CASCADE |
| `ordem` | tinyint unsigned | Posição no carrossel (0-based) |
| `tipo_arquivo` | enum | `imagem` · `video` |
| `caminho` | varchar(2048) | Caminho relativo em `uploads/instagram/` ou URL absoluta |
| `url_publica` | varchar(2048) | URL acessível publicamente (exigida pela Meta API) |
| `largura` | smallint | Largura em pixels |
| `altura` | smallint | Altura em pixels |
| `duracao_s` | smallint | Duração em segundos (vídeos) |

### `instagram_insights_cache`

Cache de métricas e histórico diário de seguidores.

| Coluna | Tipo | Descrição |
|---|---|---|
| `id` | int unsigned PK | |
| `account_id` | int unsigned FK | Referência a `instagram_accounts.id` CASCADE |
| `periodo` | enum | `7d` · `30d` |
| `data_referencia` | date | Data de início do período (UNIQUE com account_id + periodo) |
| `alcance` | int | Reach agregado do período |
| `impressoes` | int | Impressions agregados |
| `visitas_perfil` | int | Profile views agregados |
| `interacoes` | int | Total interactions agregado |
| `seguidores` | int | Snapshot de seguidores na data |
| `variacao_seguidores` | int | Delta em relação ao snapshot anterior |
| `payload_raw` | json | Resposta bruta da API (auditoria) |

---

## `InstagramApiService`

**Namespace:** `App\Services\Instagram`  
**Arquivo:** `app/Services/Instagram/InstagramApiService.php`

### Constantes públicas

| Constante | Valor | Descrição |
|---|---|---|
| `MAX_CAPTION_LENGTH` | 2200 | Limite de caracteres na legenda |
| `MAX_HASHTAG_COUNT` | 30 | Limite de hashtags por post |
| `MAX_CAROUSEL_ITEMS` | 10 | Máximo de mídias num carrossel |
| `MIN_CAROUSEL_ITEMS` | 2 | Mínimo de mídias num carrossel |

### Métodos principais

```php
// Perfil e Insights (fallback para helper local :58772)
getProfile(): array
getInsights(string $period = '7d'): array

// Feed e Detalhes
getMediaFeed(int $limit = 12): array
getMediaDetails(string $mediaId): array     // inclui insights do post
getMediaComments(string $mediaId, int $limit = 20): array

// Publicação
createImageContainer(string $imageUrl, ?string $caption, array $extra = []): string
createCarouselContainer(list<string> $childrenIds, ?string $caption): string
checkContainerStatus(string $creationId): string  // FINISHED, IN_PROGRESS, ERROR...
publishMedia(string $creationId): string          // retorna ig_media_id

// Validação
validateCaption(?string $caption): array{ok:bool, errors:list<string>}
countHashtags(?string $caption): int
```

### Fallback para helper local

O helper local roda em `http://127.0.0.1:58772` e é consultado com timeout
de **2 segundos** nas operações de leitura (`getProfile`, `getInsights`).
Em caso de falha ou timeout, o serviço cai automaticamente para a Graph API
com timeout de **15 segundos**.

---

## `InstagramPostRepository`

**Namespace:** `App\Repositories`  
**Arquivo:** `app/Repositories/InstagramPostRepository.php`

### Métodos principais

```php
// Leitura
findActiveAccount(): ?array
listDrafts(int $accountId, int $limit = 50): array
listScheduled(int $accountId, int $limit = 50): array
listPublished(int $accountId, int $limit = 30): array
findById(int $id): ?array
findMediaByPostId(int $postId): array
findDueScheduled(): array        // posts prontos para publicar (SELECT simples; concorrencia via flock + lockForPublishing)

// Escrita
create(array $data): int
update(int $id, array $data): bool
lockForPublishing(int $id): bool  // lock atômico: status agendado → publicando
markPublished(int $id, string $igMediaId, string $permalink): bool
markError(int $id, string $errorMessage): bool
saveCreationId(int $id, string $creationId): bool
addMedia(int $postId, array $media): int
deleteMediaByPostId(int $postId): bool
upsertFromFeed(array $data): void  // sincronização de feed externo (ON DUPLICATE KEY UPDATE)
syncAccount(int $accountId, array $profile): bool

// Cache de Insights
upsertInsightsCache(array $data): void
getLatestInsights(int $accountId, string $period = '7d'): ?array
```

---

## `InstagramController`

**Namespace:** `App\Controllers\Admin`  
**Arquivo:** `app/Controllers/Admin/InstagramController.php`

### Endpoints

| Método | Rota | Ação | Middleware |
|---|---|---|---|
| GET | `/admin/instagram` | `index()` — Dashboard | `auth` |
| POST | `/admin/instagram/sincronizar` | `sync()` — Sincronizar conta | `auth` |
| GET | `/admin/instagram/posts/criar` | `create()` | `auth` |
| POST | `/admin/instagram/posts/criar` | `store()` | `auth` |
| GET | `/admin/instagram/posts/{id}/editar` | `edit()` | `auth` |
| POST | `/admin/instagram/posts/{id}/editar` | `update()` | `auth` |
| GET | `/admin/instagram/posts/{id}` | `show()` — Detalhes | `auth` |
| GET | `/admin/instagram/api/blog-post` | `blogPostData()` — API interna | `auth` |
| POST | `/admin/instagram/api/crosspost-preview` | `crosspostPreview()` — prévia do cross-post do blog (JSON, CSRF) | `auth` |

---

## Script CLI de Publicação Agendada

**Arquivo:** `scripts/en-instagram-publish-scheduled.php`

```bash
# Execução manual
C:\xampp\php\php.exe scripts/en-instagram-publish-scheduled.php

```

### Tarefa agendada (Windows Task Scheduler) — Task #329

| Item | Valor |
|---|---|
| Nome | `EstrategiaNerd-InstagramPublicarAgendados` |
| Programa | `C:\xampp\php\php-win.exe` (CLI sem janela de console) |
| Argumento | `"C:\Users\WINDOWS\Projects\estrategia-nerd\scripts\en-instagram-publish-scheduled.php"` |
| Iniciar em | `C:\Users\WINDOWS\Projects\estrategia-nerd` |
| Gatilho | a cada 5 minutos, sem data de fim (conferir `Duration` vazio no XML exportado) |
| Conta | usuário atual, `LogonType Interactive`, `RunLevel Limited` (sem senha e sem admin) |
| Configurações | `MultipleInstances IgnoreNew`, `ExecutionTimeLimit` 60 min, `StartWhenAvailable` |

- **Só roda com o usuário logado.** Com o PC desligado ou deslogado, os agendados vencidos saem na próxima execução.
- `LastTaskResult` segue o exit code do script: `0` = nada a publicar ou tudo publicado; `1` = algum post falhou (ver `error_log` do post e o log mensal).
- **Risco conhecido:** se o Windows encerrar o processo pelo limite de 60 min no meio de uma publicação, o post fica em `publicando` e o script não o recupera (só busca `agendado`). Conferir posts parados em `publicando` no painel.
- **Rollback:** `Unregister-ScheduledTask -TaskName EstrategiaNerd-InstagramPublicarAgendados -Confirm:$false`.

### Fluxo do script

```
1. Verificar SAPI = CLI (bloqueia execução via browser)
2. Adquirir lock de processo global (flock) → previne execução paralela
   2a. Recuperar presos: posts em 'publicando' sem atualização há mais de 90 min viram 'erro' (markStalePublishingAsError) — roda antes de buscar a conta, então funciona mesmo com a conta desativada. Não voltam para 'agendado' porque podem já ter saído na Meta; reagendar é manual, depois de conferir no Instagram.
3. Buscar conta ativa
4. SELECT posts com status='agendado' AND agendado_para <= NOW() (sem FOR UPDATE SKIP LOCKED, que nao existe no MariaDB 10.4 local; a exclusao mutua vem do flock do passo 2 e do UPDATE atomico do passo 5a)
5. Para cada post:
   a. UPDATE status='publicando' WHERE id=X AND status='agendado' (lock atômico)
   a2. Conferir tipo x mídias (InstagramPostRepository::mediaRuleError); combinação inválida (ex.: post antigo, de antes das travas) vai para 'erro' com a mensagem, sem chamar a Meta
   b. Validar a capa JPEG dos Reels e criar container(s) na Meta API com `cover_url` obrigatório; capa ausente/inválida bloqueia antes do envio (ver "Capa do Reels")
   c. Polling do status do container (até 6 tentativas × 5s = 30s máx)
   d. publishMedia() → ig_media_id
   e. Buscar permalink via getMediaDetails()
   f. markPublished() ou markError()
6. Log mensal em storage/logs/instagram/publish-YYYY-MM.log
7. Liberar lock de processo
```

### Capa do Reels (`cover_url`) — Tasks #643 a #645

Sem capa, a Meta usa o primeiro quadro do vídeo (`thumb_offset` padrão `0`). Nos Reels animados esse quadro é só o fundo azul, antes de o título e a imagem entrarem, e era essa a capa que aparecia no Instagram.

- `InstagramApiService::reelCoverParams($videoPath, base_path('public'))` procura a capa `.jpg` com o mesmo nome ao lado do MP4 (ex.: `reel-192-article-12-fa0fff0b.mp4` → `reel-192-article-12-fa0fff0b.jpg`) e devolve `['cover_url' => URL pública]`. A capa precisa estar dentro de `public/`, não estar vazia, ter dimensões positivas e ser JPEG decodificável pelo GD. Desde 07/10/2026, ausência ou invalidez lança um aviso claro e **bloqueia antes de qualquer POST à Meta**; não retorna parâmetros vazios nem usa o quadro inicial como fallback.
- A criação genérica de containers também exige `cover_url` não vazio para `media_type=REELS`, protegendo chamadas que não passam pelo helper. O tratamento existente registra `status=erro` e a orientação de gerar/repor a capa, visível nas telas do post e de edição. Não há novo container/ID de publicação nessa falha. Após corrigir o JPG, tentar novamente pela ação normal de publicação.
- Usado nos dois ramos de Reels (com trilha e sem trilha) do agendador (`scripts/en-instagram-publish-scheduled.php`) e do "Publicar agora" (`InstagramController`). Carrossel e Story não mudam.
- As capas já existem para os Reels gerados pelo sistema: Konva captura a primeira cena completa aos 1,6 s; o modelo anterior `EditorialMotionReelRenderer` captura depois da entrada do título e da imagem (`max(3,5 s, entrada do HUD + 0,5 s)`), e `AudioReelGeneratorService::generateReel()` grava o pôster aos 0,1 s (canvas estático).
- O agendador registra `Post #N — capa validada: <URL>`; capa inválida produz erro com orientação, sem envio. Vídeos remotos/manuais sem JPG local também precisam de uma capa válida para publicar como Reel.
- Regressão: `C:\xampp\php\php.exe scripts/verify-reel-cover.php` usa JPEGs isolados, SQLite efêmero e HTTP simulado para verificar ausência, arquivo vazio/corrompido/incompleto, PNG com extensão JPG, caminho externo, chamadas diretas sem capa e fluxo válido. As 26 capas agendadas são conferidas em leitura, sem removê-las para simular falhas.
- A URL da capa passa pelo mesmo `buildPublicMediaUrl()` do vídeo; `/uploads/` já está liberado no Cloudflare Access para a Meta.
- A Meta só aceita a capa na criação do container. Reel já publicado não troca a capa pela API (o update de IG Media só aceita `comment_enabled`); a troca é manual no app do Instagram.

### Exit codes

| Código | Significado |
|---|---|
| 0 | Sucesso (ou nada para publicar) |
| 1 | Um ou mais posts falharam na publicação |

---

## Configuração da Conta

Para ativar o módulo, insira diretamente no banco de dados:

```sql
INSERT INTO instagram_accounts (ig_user_id, username, access_token, ativo)
VALUES ('SEU_IG_USER_ID', 'estrategia_nerd', 'SEU_ACCESS_TOKEN_PERMANENTE', 1);
```

> **Importante:** O token deve ser um **token de acesso de longa duração** (Long-Lived Token)
> obtido via Meta for Developers. Tokens de curta duração expiram em 1 hora.
> Consulte: https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/get-started

---

## Pré-requisitos da Meta API

- App Meta com permissões: `instagram_basic`, `instagram_content_publish`, `instagram_manage_insights`
- Conta Instagram Business ou Creator conectada a uma Página do Facebook
- Para publicação: as imagens/vídeos devem estar em **URLs acessíveis publicamente**
  (domínio de produção ou hosting público)

---

## Limites e Validações

| Parâmetro | Limite |
|---|---|
| Legenda | 2.200 caracteres (validado com `mb_strlen`) |
| Hashtags por post | 30 |
| Mídias por carrossel | 2–10 |
| Publicações por conta | 50/dia (limite da Meta API) |
| Timeout helper local | 2 segundos |
| Timeout Graph API | 15 segundos |

---

## Views (Frontend)

As 4 views estão implementadas em:

- [`app/Views/admin/instagram/index.php`](../../app/Views/admin/instagram/index.php) — dashboard: status da conexão, header do perfil com métricas e badge de variação, métricas 7d/30d, feed recente, agendados e rascunhos
- [`app/Views/admin/instagram/create.php`](../../app/Views/admin/instagram/create.php) — criação de post com seletor de tipo (imagem, carrossel, reels, story) com travas por mídia, upload cumulativo com lixeira e preview interativo (carrossel navegável e vídeo)
- [`app/Views/admin/instagram/edit.php`](../../app/Views/admin/instagram/edit.php) — edição com as mesmas travas; a lixeira das mídias salvas só marca para remoção ao salvar (bloqueada em modo somente leitura quando o post já está `publicado`/`publicando`)
- [`app/Views/admin/instagram/show.php`](../../app/Views/admin/instagram/show.php) — detalhes, insights e comentários

---

## Limitações Anteriores Resolvidas

1. **Reels e Stories com publicação real**:
   - `createVideoContainer()` adicionado em `InstagramApiService` (`media_type=REELS` ou `STORIES`, omitindo legenda para Stories conforme a especificação da Graph API).
   - Suporte a Story tanto em imagem quanto em vídeo.
   - Fluxo de publicação ramificado por tipo e centralizado em `InstagramController::publishNow()` e no agendador CLI `scripts/en-instagram-publish-scheduled.php`.
   - Polling adaptativo para processamento de vídeo (até 12 ciclos de 5s no CLI e 15 ciclos de 2s no web, com fail-fast caso a Meta retorne `ERROR` ou `EXPIRED`).

2. **Remoção individual de mídia**:
   - Método `deleteMedia(int $mediaId, int $postId): bool` em `InstagramPostRepository` condicionado por `id` e `post_id`.
   - Rota `POST /admin/instagram/media/{id}/delete` com validação de token CSRF, bloqueio de remoção em posts já publicados ou em publicação, e exclusão segura do arquivo físico em `uploads/instagram/`.
   - Botão de exclusão (lixeira) em cada card de mídia na view `edit.php`.

3. **Cálculo da variação de seguidores**:
   - `persistInsights()` agora obtém a contagem de seguidores de `getProfile()`, consulta o snapshot de data anterior via `getPreviousInsights()` e calcula o delta real em relação à base prévia.
   - O dashboard (`index.php`) exibe o badge de variação (+ / -) ao lado da contagem de seguidores.

4. **Normalização do contrato do helper local**:
   - Respostas do helper local em `InstagramApiService::getProfile()` e `getInsights()` são normalizadas para a estrutura da Graph API (`followers` → `followers_count`, `profilePic` → `profile_picture_url`, `reach7d`, etc.).
   - *Nota técnica sobre o helper local:* o helper atua como fallback rápido de desenvolvimento, mas não fornece métricas de impressões/interações e não diferencia períodos (7d vs 30d). Ao conectar a Graph API com token permanente, as métricas completas passam a ser coletadas nativamente.

5. **Validação robusta de upload e nomes seguros**:
   - `saveUploadedMedia()` valida arquivos com `finfo_file` (MIME real), whitelist de extensões permitidas (`jpg, jpeg, png, webp, mp4, mov`), limites de tamanho (8MB para imagens, 100MB para vídeos) e validação de `is_uploaded_file`.
   - Geração de nomes aleatórios seguros via `bin2hex(random_bytes(16))` eliminando qualquer risco de colisão concorrente ou path traversal.

---

## Cross-post Blog → Instagram (Task #328)

No criar/editar post do blog (`app/Views/components/admin/posts/form-instagram-crosspost.php`) há a opção **"Criar rascunho no Instagram"**. Nada é publicado automaticamente: o resultado é sempre um **rascunho** no módulo Instagram.

### Fluxo

1. **Ligar a opção** → `POST /admin/instagram/api/crosspost-preview` (auth + CSRF) envia título, resumo, categoria e a imagem: arte dedicada (opcional) > capa pendente (`imagem_capa_upload`) > capa salva (`imagem_capa`).
2. O servidor gera a imagem em `public/uploads/instagram/preview/<token>.jpg` e a legenda (IA ou alternativa sem IA) e devolve imagem, legenda, hashtags e um **token**.
3. O card estilo Instagram atualiza ao vivo conforme o autor edita legenda/hashtags (contadores 2.200/30, "Gerar outra legenda").
4. **Salvar o blog** → depois que o post do blog é gravado, `PostsService` chama `BlogCrosspostService::persistAfterSave()` isolado em `try/catch (\Throwable)`. A IA **não** é chamada de novo: grava-se a legenda como o autor deixou e a mesma imagem da prévia.
5. Na edição, o card do post do Instagram vinculado mostra status, imagem, legenda e link para o módulo. Em Reels, a imagem é a capa `.jpg` gerada ao lado do MP4 (a mesma da lista do Instagram); sem capa, o card mostra o próprio vídeo, sem som.

### Imagem — `SmartCanvasRenderer`

- Limites antes de abrir no GD: 10 MB, 25 megapixels, MIME real (`finfo`) JPEG/PNG/WebP; orientação EXIF corrigida.
- Canvas 1080x1080 (largura/altura parametrizáveis — base para o futuro Card Generator, Task #335): imagem original **inteira** centralizada, fundo com a própria imagem desfocada e escurecida (`#0b0f19`), faixas "ESTRATEGIA NERD" e "LEIA O ARTIGO NO BLOG /// LINK NA BIO".
- Arte dedicada não é composta: é regravada como JPEG (descarta metadados) e precisa ser 1:1 ou 4:5.

### Legenda — `GeminiCaptionService`

- Provedor: Google Gemini, nível gratuito, com a mesma `GEMINI_API_KEY` já usada pelo projeto (a chave vale para todos os modelos; `GEMINI_IMAGE_MODEL` é só o modelo de imagem).
- Variáveis no `.env`: `GEMINI_TEXT_MODEL` (principal, `gemini-3.8-flash`) e `GEMINI_TEXT_MODEL_FALLBACK` (reserva, `gemini-3.5-flash`).
- Envia só texto (título, resumo, categoria) e pede JSON (`responseMimeType: application/json`).
- Tentativas: principal → principal de novo após ~1 s (só em 429/503/erro de rede) → modelo reserva. Modelo inexistente (404) pula direto para o reserva. Teto de ~30 s somando tudo, cada chamada com até 15 s.
- Qualquer falha → **alternativa sem IA**: título + resumo + "Leia o artigo completo no blog - link na bio." + hashtags padrão (`#EstrategiaNerd #Nerd #Geek #CulturaPop` + categoria). A tela avisa quando a IA não foi usada; "Gerar outra legenda" tenta de novo.
- No nível gratuito o Google pode usar o conteúdo enviado para melhorar os modelos; só vai conteúdo público do post. Logs registram apenas modelo e status HTTP, nunca a chave nem o corpo do erro.
- A OpenAI foi avaliada e descartada: a conta não tem saldo e os tokens gratuitos do compartilhamento de dados exigem conta em nível pago. `OPENAI_API_KEY` ficou no `.env` sem uso.

### Regras de gravação — `BlogCrosspostService`

- **Chave idempotente:** `idempotency_key` = UUID v5 de `blog:<ambiente>:<post_id>`. O índice único existente impede rascunho duplicado (inclusive em saves simultâneos) e separa o mesmo `post_id` de ambientes diferentes. Sem migration.
- **Token** (sessão, 24 h): ligado a autor, ambiente, post (ou `form_uid` na criação), arquivo da prévia e SHA-256 dos bytes da imagem usada. No save, a capa é comparada pelos **bytes originais** recebidos (upload pendente ou arquivo no caminho informado), então mudar o slug não invalida a prévia; trocar a capa sem gerar nova prévia é recusado.
- **Status:** só `rascunho` e `erro` são atualizados (linha lida com `SELECT … FOR UPDATE`, sem depender de `rowCount`); `agendado`, `publicando` e `publicado` ficam somente leitura. Atualizar um `erro` volta para `rascunho`.
- **Reels (Task #541):** post vinculado do tipo `reels` (Reels automático do IMP-026 ou post que ganhou trilha na tela do Instagram) é somente leitura pelo blog em qualquer status. O card avisa "este artigo já tem um Reels" e o save recusa a gravação dentro da mesma transação (`SELECT … FOR UPDATE`), inclusive quando o formulário foi aberto antes de o Reels existir, sem trocar o vídeo pela arte quadrada. O Reels é editado pelo módulo Instagram.
- **Transação local:** legenda + troca de mídia na mesma transação; em falha, rollback e o arquivo promovido é apagado.
- **Falha parcial:** o blog sempre fica salvo; o controller mostra "Blog salvo; rascunho do Instagram não foi atualizado: <motivo>". Salvar de novo tenta outra vez sem duplicar.
- Capa que não existe nos uploads deste servidor (ex.: post editado em outro ambiente) exige arte dedicada.

### Criar a partir do blog: só pelo post do blog (Tasks #389 a #392)

Desde 30/09/2026 o painel "Importar do Blog" **foi removido** das telas de criar e editar do Instagram (e o endpoint `GET /admin/instagram/api/blog-post`). Post do Instagram a partir de um post do blog é criado **só** pela opção "Criar rascunho no Instagram" no criar/editar do post do blog. Na edição do Instagram, o campo oculto `post_blog_id` continua, para não apagar vínculos existentes ao salvar.

**Lista de Posts do blog (`/admin/posts`):**

- Coluna com o ícone do Instagram: ponto **verde** = o post já tem post no Instagram (qualquer status; clique abre o post no módulo Instagram), **vermelho** = não tem (clique abre `/admin/editar-post?id=X&ig=1`, com a opção do Instagram ligada e a prévia gerada), **cinza** = o banco do Instagram não respondeu.
- Títulos "Views", "Curtidas" e "Comentários" viraram ícones (olho, coração, balão), com a legenda acima da tabela.
- Filtro **Instagram** (Todos / Com post / Sem post), parâmetro `?instagram=com|sem`, mantido em ordenação, paginação e recarga por AJAX. Vale também para os cards de resumo e gráficos. Se o banco do Instagram não responder, o filtro é ignorado com aviso.
- Regra de vínculo por ambiente (`BlogCrosspostService::linkedBlogPosts($ambiente)`): cross-post conta no ambiente da sua chave UUID v5 (`blog:<ambiente>:<id>`); vínculos manuais antigos (chave aleatória) contam só no ambiente local. Os ids vêm do banco local do Instagram e entram na consulta dos posts como parâmetros (`PostRepository::buildAdminWhere`).

**Agendamento (`/admin/agendamento-posts`):**

- O calendário (grade e lista) mostra os posts do blog do ambiente alvo **e** os do Instagram (banco local): agendados pela data agendada, publicados pela data de publicação, `publicando` e `erro` pela data agendada. Rascunhos ficam de fora.
- Instagram em rosa com o ícone; `erro` com fundo vermelho; agendado com data passada com borda vermelha e "vencido". Título = primeira linha da legenda. Clique: agendado/erro abre a edição, publicado/publicando abre os detalhes.
- Botões **Blog** e **Instagram** para mostrar/esconder cada tipo (preferência salva no navegador).
- Dias com altura mínima de 150px, até 4 itens visíveis e "+N mais" para expandir o dia; título em até 2 linhas.
- Se o banco do Instagram falhar, o calendário do blog continua com aviso.

---

## Smart Canvas para qualquer imagem do Instagram (Task #386)

Ao salvar um post na tela do Instagram (criar ou editar) com a opção **"Ajustar automaticamente imagens fora do padrão (Smart Canvas)"** marcada (padrão), o `InstagramMediaFitter` olha o **conjunto final** de mídias do post (atuais, biblioteca e uploads novos) e gera versões sem cortes, **sem os textos de marca**, com fundo desfocado:

| Tipo | Aceito sem ajuste | Fora disso |
|---|---|---|
| Imagem | proporção 4:5 a 1.91:1 | mais alta → 1080x1350; mais larga → 1080x1080 |
| Carrossel | mesma proporção em todos os itens e dentro da faixa | todos em 1080x1080 (o Instagram corta o carrossel pela proporção do 1º item) |
| Story | ~9:16 | 1080x1920 |
| Reels / vídeo | — | não mexe |

- Proporção calculada com a orientação EXIF (fotos de celular giradas); o renderer também corrige as orientações espelhadas (2, 4, 5, 7).
- Só arquivos locais dentro de `public/uploads/` (caminho resolvido com `realpath`, MIME real, 10 MB, 25 MP). URLs externas (CDN da Meta) ficam como estão. O arquivo original **nunca** é alterado: o ajuste gera um arquivo novo em `uploads/instagram/` e a mídia passa a apontar para ele (com `largura`/`altura`).
- Gera todos os arquivos primeiro e só então atualiza o banco numa transação; em falha, desfaz, apaga o que gerou, mantém as originais, **não publica** e abre a edição com o aviso `?ajuste=falhou`.
- Roda depois de salvar as mídias e **antes** do "Publicar agora". Rodar de novo não refaz o que já está no padrão.
- A tela mostra em cada miniatura quais imagens serão ajustadas e para qual formato (mesma regra, em JavaScript).

---

## Travas de tipo x mídias (Tasks #394 a #398)

Regra única, usada nas telas (JavaScript) e no servidor (`InstagramPostRepository::mediaRuleError`):

| Tipo | Mídias aceitas |
|---|---|
| Imagem | exatamente 1 imagem |
| Reels | exatamente 1 vídeo |
| Story | exatamente 1 item (imagem ou vídeo) |
| Carrossel | 2 a 10 itens (imagens e vídeos misturados) |

**Telas (criar e editar)** — contam mídias salvas + novas:

- 0 mídia: todos os tipos liberados. 1 imagem: Reels desabilitado. 1 vídeo: Imagem desabilitado. 2 a 10: só Carrossel (selecionado automaticamente). O motivo aparece no tooltip do botão desabilitado.
- Tipo de 1 mídia (Imagem, Reels, Story) com 1 mídia: o "enviar mídia" fica travado com aviso até remover a atual (ou trocar para Carrossel). Com 10 mídias, trava pelo limite.
- Seleção cumulativa: cada escolha soma às anteriores. Um lote que passaria de 10 é recusado inteiro, mantendo a seleção atual.
- Lixeira ao passar o mouse: nas mídias novas remove o arquivo da seleção; nas salvas (edição) só **marca** para remoção (escurece, com "desfazer") — a remoção acontece ao salvar, junto com o resto, sem perder legenda nem arquivos novos. Depois de remover, os tipos voltam a ser liberados pela tabela.
- Carrossel com 1 item não salva (aviso na tela).
- Preview do celular mostra todas as mídias na ordem de publicação (salvas, depois novas), com setas, pontos e contador "n/total"; vídeo toca no preview (mudo, em loop, com controles) e as miniaturas de vídeo mostram o primeiro quadro. Reels e Story usam moldura 9:16 sem corte.

**Servidor** (`InstagramController::store`/`update`):

- Confere tudo **antes** de gravar: mídias salvas que ficam, uploads (MIME real; upload que falhou vira erro e não conta) e URLs da biblioteca. Rascunho sem nenhuma mídia continua permitido; agendar/publicar exige mídia.
- Em erro, a tela volta com a mensagem e, se havia arquivos, "Selecione os arquivos novamente" (o navegador descarta arquivos ao recarregar). Na edição, as marcas da lixeira e a legenda digitada são mantidas.
- Os arquivos são movidos antes da transação; post/mídias gravam numa transação. Se algo falhar, desfaz a transação e apaga os arquivos movidos — nada fica pela metade.
- Ao salvar, a `ordem` das mídias é renumerada em sequência (0, 1, 2…: salvas primeiro, depois as novas).
- Post agendado não fica inválido por remoção: o `update` recusa, e a rota antiga `POST /admin/instagram/media/{id}/delete` (que a tela não usa mais) também bloqueia para posts `agendado`.
- "Publicar agora" e o agendador conferem de novo antes de chamar a Meta; combinação inválida vai para `erro` com mensagem clara e nenhum envio.

---

## Diretriz de Ambientes e Banco de Dados

> **REGRA ARQUITETURAL MANDATÓRIA (Ambiente Único):**
> 
> 1. **Ambiente estritamente LOCAL:** O módulo Instagram opera com base de dados única no banco **local** (`estrategia-nerd` via `$GLOBALS['pdo']`). A conta do Instagram é uma entidade real unificada (`@estrategia_nerd`), e seus tokens de acesso à Graph API, agendamentos, cron CLI e sincronização com a Meta são centralizados na máquina local.
> 2. **Isolamento de Produção e Stage:** Os bancos remotos da Hostinger (`u576397693_estrategianerd` e `u576397693_en_stage`) **NÃO** possuem nem devem receber as tabelas do Instagram. Quando o usuário alterna o "Ambiente Alvo" no painel administrativo para inspecionar posts, estatísticas e newsletter de produção ou stage, o `DashboardController` e `DashboardService` continuam consultando o Instagram exclusivamente a partir do `$GLOBALS['pdo']` local.
> 3. **Transição para Multi-Ambiente no Futuro:** O módulo Instagram só passará a ser multi-ambiente quando for implementada a rotina de exibição do feed/posts do Instagram no site público principal (frontend em produção). Até lá, a separação local é estrita.

---

## PHPStan

Todos os arquivos backend do módulo passam no **PHPStan Level 5** com 0 erros:

```bash
C:\xampp\php\php.exe vendor/bin/phpstan analyse --level=5 --no-progress
```

---

## Histórico de Alterações

| Data | Versão | Descrição |
|---|---|---|
| 2026-09-27 | 1.0.0 | Criação do módulo: migration, service, repository, controller, rotas, sidebar, CLI e views |
| 2026-09-27 | 1.1.0 | Correção das 5 limitações: Reels/Stories reais no controller e CLI, remoção individual de mídia, cálculo de variação de seguidores, normalização do helper local e validação de upload com MIME real |
| 2026-09-27 | 1.2.0 | Sincronização completa de feed com paginação cursor (92 posts reais), suporte a Graph API v21.0 (`views`, `reach`, `total_interactions`, `metric_type=total_value`), sanitização de tokens em logs de URL, filtro de datas flexível (atalhos 7d/14d/21d/30d e seleção livre `start`/`end`), e reconstrução visual completa no padrão oficial do Estratégia Nerd (`posts-table`, `admin-filter-panel`, `posts-pagination-panel`). |
| 2026-09-28 | 1.3.0 | Campos de filtro alinhados lado a lado em grid horizontal (`.admin-filter-grid-instagram`), botão alternador de visualização instantânea Planilha (`posts-table`) vs. Cards (grade visual com thumbnails 1:1) com persistência em `localStorage`, URLs limpas e amigáveis (omissão de parâmetros padrão e vazios), e seções de Agendados e Rascunhos movidas para o final da página empilhadas em largura total com tabela oficial e busca rápida em tempo real. |
| 2026-09-28 | 1.4.0 | Reestruturação de layout nas telas de criação e edição (`create.php` e `edit.php`) em 2 colunas proporcionais (cards 62% à esquerda e preview interativo do smartphone sticky 38% à direita), correção da importação do blog (eliminação da quebra de grid do `#igMediaUrlsWrap`), paginação inicial padronizada para 8 itens (2 fileiras completas no modo Cards) com seletores de 8, 16, 24 e 'Todos', e fallback gracioso de mídia (`onerror`) para URLs expiradas da CDN da Meta (`fbcdn.net`). |
| 2026-09-28 | 1.4.1 | Alinhamento pixel-perfect do topo entre os blocos de configuração e o Preview (isolando inputs ocultos para evitar a margem do seletor `* + *` de `space-y-6`), eliminação do aninhamento inválido de `<form>` na remoção de mídias em `edit.php` (usando formulário externo e trigger JS seguro para manter a árvore DOM intacta), e padronização visual completa do Preview em edição. |
| 2026-09-28 | 1.5.0 | Separação física e visual do campo de Hashtags do texto principal da Legenda em `create.php` e `edit.php` (com contadores independentes, união automática e preview sincronizado com destaque ciano), implementação da exclusão de posts rascunho/agendados/erro no repository (`deletePost`) e controller com limpeza física das mídias em `uploads/instagram/`, e modal popup customizado de confirmação no tema oficial do Estratégia Nerd integrado no dashboard e na tela de edição. |
| 2026-09-28 | 1.5.1 | Correção do contexto de empilhamento do popup de exclusão (teleporte do modal para `document.body.appendChild` e `z-[10000]`), garantindo que o modal apareça rigorosamente centralizado na viewport visível do usuário, mesmo após rolagem profunda em páginas extensas. |
| 2026-09-28 | 1.6.0 | Exibição completa da biografia do perfil (sem truncamento, com quebras de linha `nl2br`) e link clicável da bio (`website`) no dashboard, acompanhados de botão de ação e modal popup explicativo com a matriz detalhada de regras, permissões e limitações da Meta Graph API (segurança contra sequestro de contas e guia de sincronização). |
| 2026-09-28 | 1.6.1 | Correção de precedência em `InstagramApiService::getProfile()`: priorização estrita da Meta Graph API v21.0 oficial como fonte autoritativa (garantindo captura integral de biografia com quebras de linha/emojis e website oficial), relegando o helper local da porta 58772 apenas como contingência offline, e sincronização dos dados no banco local. |
| 2026-09-28 | 1.6.2 | Redimensionamento proporcional dobrado da foto de perfil no card do Dashboard de 64px para 144-160px (`h-36 w-36 sm:h-40 sm:w-40`) com moldura estilizada em ciano (`border-2 border-cyan-500/40 shadow-xl`), alinhamento responsivo pixel-perfect com a altura da biografia completa, agrupamento do bloco numérico de métricas e badge indicativo de link principal do perfil. |
| 2026-09-28 | 1.7.0 | Reestruturação completa do Dashboard inspirada em interface profissional moderna: barra superior enxuta de sincronização, grid de 4 KPIs com sparklines SVG temáticos, divisão central em 2 colunas (Informações da Conta 60% e Ações Rápidas 2x2 40%), navegação em abas persistentes (Métricas, Posts Recentes, Agendados & Rascunhos, Insights) e gráficos vetoriais nativos em SVG (Desempenho temporal com curvas de tendência e Gráfico Donut de Tipos de Conteúdo com total e percentuais reais). |
| 2026-09-28 | 1.8.0 | Otimização SPA e eliminação de redundâncias de UI: status de conexão e última sincronização integrados diretamente no cabeçalho de "Informações da conta" (eliminando barra superior e botões repetidos), centralização das ações no card "Ações Rápidas", filtros de período (7d, 14d, 30d e customizado) atualizados em tempo real sem recarregamento da página (Fetch API assíncrono via `?ajax=metrics` redesenhando dinamicamente os SVGs do gráfico de desempenho e do donut, além dos KPIs e cards analíticos) e URLs amigáveis e limpas via `window.history.replaceState`. |
| 2026-09-29 | 1.9.0 | Integração macro no Dashboard Principal (`/admin`): injeção de repositório no `DashboardController` e `DashboardService`, inclusão do 6º card de KPI no grid superior com seguidores e alcance, novo painel dedicado "Instagram & Redes Sociais" com status de conexão, mini-KPIs, fila editorial e mini-galeria dos 4 posts mais recentes, e indicador de publicações agendadas para hoje no painel "Hoje". |
| 2026-09-29 | 1.9.1 | Correção de isolamento de ambiente no Dashboard Principal: instanciação de `InstagramPostRepository` com `$GLOBALS['pdo']` local (blindando contra consultas indevidas aos bancos remotos de Produção/Stage), inclusão de `try/catch` defensivo em `DashboardService::buildInstagramData()` para degradação graciosa sem erro 500, e formalização da regra arquitetural de ambiente único do Instagram. |
| 2026-09-29 | 2.0.0 | Frontend Público: Widget de feed do Instagram na Home pública (`/`) com `InstagramFeedService`, cache serializado em `storage/cache/instagram_feed.json`, componente visual cyberpunk responsivo (`site/home/instagram-feed.php`) e inclusão na suíte de testes `scripts/verify-changes.php`. |
| 2026-09-29 | 2.1.0 | Deploy e Homologação Multi-Ambiente: Substituição dos ícones FontAwesome por SVGs inline nativos com badges temáticas e rótulos explícitos de curtidas e comentários no frontend público; provisionamento do snapshot do feed em `config/instagram-feed.json` integrado ao pipeline de deploy de código; backup de segurança preventivo gerado (`BS-PROD-20260929-094844`); pacote técnico aplicado com sucesso em Stage (`https://estrategianerd.com.br/stage`) e Produção (`https://estrategianerd.com.br`), validado e homologado de ponta a ponta. |
| 2026-09-29 | 2.2.0 | Redesign UI Cyberpunk Estruturado (Mockup): Reconstrução completa de `instagram-feed.php` adotando o paradigma de cards editoriais widescreen 3x2 (imagem 16:9/16:10 + título e subtítulo sempre visíveis + rodapé com data pt-BR, curtidas, comentários e link externo), cabeçalho de seção em 2 colunas com card de perfil expandido contendo 4 editorias em pills vetoriais (Bastidores, Setups, Notícias, Comunidade), sub-cabeçalho com `/// Publicações Recentes` e controles de navegação, e CTA centralizado em pílula gradiente no rodapé da seção. |
| 2026-09-29 | 2.3.0 | Paginação de 3 Páginas de 6 Posts & Navegação no Menu: Implementação da paginação interativa no feed com exatamente 3 páginas de 6 cards (18 posts no total), badge de status ("Página X de 3") e controle via JavaScript nativo nos botões `<` e `>` com bloqueio/opacidade nos limites; inclusão da seção 'instagram' em `SiteSections` apontando para `#instagram-feed`, adicionando o item 'Instagram' automaticamente no menu principal desktop/mobile e no rodapé ('Explore o portal'). |
| 2026-09-29 | 2.4.0 | Resiliência e Persistência Local de Mídias de Reels: Correção em `InstagramPostRepository::upsertFromFeed` priorizando estritamente a URL de capa estática (`thumbnail_url`) sobre o arquivo de vídeo `.mp4` para publicações do tipo Reels/Vídeo (eliminando falhas em tags `<img>` e o fallback para o ícone cinza de câmera tanto no painel admin quanto no site público); criação do diretório versionado `public/assets/instagram-feed/` com download de 18 imagens de capa oficiais persistidas localmente; atualização de `InstagramFeedService` e da view admin para priorizar os assets locais; adição de fallback resiliente com alternância suave no componente do feed; e sincronização completa dos dados da Meta Graph API. |
| 2026-09-29 | 2.5.0 | Cross-post Blog → Instagram (Task #328): opção "Criar rascunho no Instagram" no criar/editar post do blog com prévia estilo Instagram, Smart Canvas 1080x1080 sem cortes (`SmartCanvasRenderer`), legenda via Google Gemini com modelo reserva e alternativa sem IA (`GeminiCaptionService`), gravação idempotente por UUID v5 e token de prévia (`BlogCrosspostService`), endpoint `crosspost-preview` e card do post vinculado na edição. |
| 2026-09-29 | 2.5.1 | Correção do agendador (`scripts/en-instagram-publish-scheduled.php`): `findDueScheduled()` usava `FOR UPDATE SKIP LOCKED`, inexistente no MariaDB 10.4.32 local (erro 1064 em toda execução, nenhum agendado era publicado). Passa a usar `SELECT` simples; a exclusão mútua continua garantida pelo `flock` do script e pelo `UPDATE … WHERE status='agendado'` atômico de `lockForPublishing()`. |
| 2026-09-29 | 2.5.2 | Agendamento do publicador (Task #329): tarefa `EstrategiaNerd-InstagramPublicarAgendados` no Task Scheduler a cada 5 min com `php-win.exe`, `IgnoreNew`, limite de 60 min e execução só com o usuário logado; documentados exit codes, risco de post preso em `publicando` e rollback. |
| 2026-09-29 | 2.5.3 | Pendências do agendador e do feed: (1) `markStalePublishingAsError(90)` no início do script libera posts presos em `publicando` como `erro` com mensagem orientando conferir no Instagram antes de reagendar; (2) `InstagramFeedService::saveCache()` só regrava `config/instagram-feed.json` e `storage/cache/instagram_feed.json` quando o conteúdo muda (ignora `updated_at`), eliminando a alteração falsa no git a cada renderização da home. |
| 2026-09-30 | 2.5.4 | Arquivos do FEAT-010 alterados desde `ec7f946` (cross-post, agendador, snapshot do feed) foram para Stage e Produção dentro da `RELEASE-2026-09-29-cron-monitoramento`, porque o pacote técnico é incremental. Não executam em produção: `/admin` é bloqueado fora do local e o Instagram segue local-only; o log de erros de produção não mostrou nenhum tipo de erro novo após o deploy. |
| 2026-09-30 | 2.6.0 | Smart Canvas para qualquer imagem do módulo (`InstagramMediaFitter`, regras por tipo, EXIF, conjunto final, transação e limpeza), opção e avisos nas telas de criar/editar, e "Importar do Blog" da tela de criar levando para a rotina do post do blog (`?ig=1`), listando só posts sem Instagram e com trava de ambiente. |
| 2026-09-30 | 2.7.0 | "Importar do Blog" removido das telas do Instagram (criação só pelo post do blog); lista de Posts do blog com coluna do Instagram (verde/vermelho/cinza), métricas em ícones com legenda e filtro Instagram com/sem post; Agendamento mostrando também os posts do Instagram (rosa, erro e vencido destacados, botões Blog/Instagram, dias de 150px com "+N mais"). |
| 2026-09-30 | 2.8.0 | Travas de tipo x mídias (Tasks #394 a #398): tipos desabilitados com motivo, seleção cumulativa até 10, lixeira (nova remove; salva marca para remoção ao salvar), envio travado em tipos de 1 mídia, preview com carrossel navegável e vídeo; validação no servidor antes de gravar (com desfazer em falha e renumeração da ordem), conferência no "Publicar agora" e no agendador, e bloqueio na rota antiga de remover mídia para posts agendados. |
| 2026-10-01 | 2.8.1 | Tela de edição: o preview mostra a conta real (`@usuario`) também nas telas de erro do salvamento (antes aparecia "@sua_conta") e deixa de gerar aviso de PHP pela variável inexistente `$oldLegenda` (Tasks #400 a #403). |
| 2026-10-01 | 2.9.0 | Capas no admin para todos os posts (Tasks #404 a #407): `sync-instagram-feed-assets.php` passa a baixar a capa local de todos os posts publicados (o feed do site continua com os 18 mais recentes); `caminho`/`url_publica` de `instagram_post_media` sobem de varchar(500) para varchar(2048) (links de capa de Reels/carrossel da Meta passavam de 500 e eram cortados, dando 403); `upsertFromFeed` não grava mais capa em post criado no admin (`origin = 'local'`, ordem 0-based). |
| 2026-10-03 | 2.10.0 | Resolução de Mídias Públicas e Compatibilidade de Vídeo no Reel: (1) `InstagramApiService::buildPublicMediaUrl` centraliza a resolução de URLs públicas de mídias enviadas à Meta a partir de `config('instagram.public_media_url')` (default `https://nerd.tfr-info.com.br`), impedindo chamadas com `localhost` ou `127.0.0.1` e normalizando URLs legadas; (2) Liberação de bypass no Cloudflare Access para `/uploads/` garantindo acesso irrestrito da Meta aos arquivos de mídia; (3) `AudioReelGeneratorService::generateReel` aceita vídeo como entrada com descarte do áudio original e aplicação da trilha sonora com fade-out. |
| 2026-10-03 | 2.10.1 | Cross-post x Reels (Tasks #541 e #542): post vinculado do tipo `reels` fica somente leitura pelo blog em qualquer status; o card mostra a capa `.jpg` do vídeo e avisa que o artigo já tem um Reels; o save recusa dentro da transação, sem trocar o vídeo pela arte quadrada. |
| 2026-10-03 | 2.11.0 | Capa do Reels (Tasks #643 a #645): a publicação de Reels (agendador e "Publicar agora") envia `cover_url` com a capa `.jpg` gerada ao lado do MP4 (`InstagramApiService::reelCoverParams`), em vez de deixar a Meta usar o primeiro quadro (fundo azul nos Reels animados); o agendador registra a capa usada no log. |
| 2026-10-08 | 2.11.1 | Lista/cards (Tasks #926–928): controle único em `admin-instagram-posts.js`, com eventos delegados e reaplicação após substituição AJAX. Formulário, links, URL explícita (`view=table`/`view=grid`) e preferência local ficam sincronizados. A URL tem prioridade sobre a preferência salva. Quantidades: 8, 16, 24, 32, 48 e Todos. Não altera a fila ou publicações. Lint PHP/JS e suíte obrigatória 17/17 aprovados; homologação interativa aguardando autenticação no painel local. |









