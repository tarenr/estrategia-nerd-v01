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
| `status` | enum | `rascunho` · `agendado` · `publicando` · `publicado` · `erro` |
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
| `caminho` | varchar(500) | Caminho relativo em `uploads/instagram/` ou URL absoluta |
| `url_publica` | varchar(500) | URL acessível publicamente (exigida pela Meta API) |
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
findDueScheduled(): array        // posts prontos para publicar (WITH SELECT FOR UPDATE SKIP LOCKED)

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

---

## Script CLI de Publicação Agendada

**Arquivo:** `scripts/en-instagram-publish-scheduled.php`

```bash
# Execução manual
C:\xampp\php\php.exe scripts/en-instagram-publish-scheduled.php

# Configuração recomendada (Task Scheduler Windows — a cada 5 min)
Program: C:\xampp\php\php.exe
Arguments: C:\Users\WINDOWS\Projects\estrategia-nerd\scripts\en-instagram-publish-scheduled.php
Start in: C:\Users\WINDOWS\Projects\estrategia-nerd
```

### Fluxo do script

```
1. Verificar SAPI = CLI (bloqueia execução via browser)
2. Adquirir lock de processo global (flock) → previne execução paralela
3. Buscar conta ativa
4. SELECT posts com status='agendado' AND agendado_para <= NOW() (FOR UPDATE SKIP LOCKED)
5. Para cada post:
   a. UPDATE status='publicando' WHERE id=X AND status='agendado' (lock atômico)
   b. Criar container(s) na Meta API
   c. Polling do status do container (até 6 tentativas × 5s = 30s máx)
   d. publishMedia() → ig_media_id
   e. Buscar permalink via getMediaDetails()
   f. markPublished() ou markError()
6. Log mensal em storage/logs/instagram/publish-YYYY-MM.log
7. Liberar lock de processo
```

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
- [`app/Views/admin/instagram/create.php`](../../app/Views/admin/instagram/create.php) — criação de post com seletor de tipo (imagem, carrossel, reels, story), upload/preview, importar do blog e preview interativo
- [`app/Views/admin/instagram/edit.php`](../../app/Views/admin/instagram/edit.php) — edição com gerenciamento e remoção individual de mídias (bloqueada em modo somente leitura quando o post já está `publicado`/`publicando`)
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

## PHPStan

Todos os arquivos backend do módulo passam no **PHPStan Level 5** com 0 erros:

```bash
C:\xampp\php\php.exe vendor/bin/phpstan analyse --level=5 --no-progress
```

---

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



