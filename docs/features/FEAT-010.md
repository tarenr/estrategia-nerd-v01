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

## Views (Frontend — Claude Code)

As views são responsabilidade do Claude Code e estão como placeholder em:

- [`app/Views/admin/instagram/index.php`](../app/Views/admin/instagram/index.php)
- [`app/Views/admin/instagram/create.php`](../app/Views/admin/instagram/create.php)
- [`app/Views/admin/instagram/edit.php`](../app/Views/admin/instagram/edit.php)
- [`app/Views/admin/instagram/show.php`](../app/Views/admin/instagram/show.php)

Cada view contém comentários `// TODO (Claude Code)` com a especificação completa
dos elementos visuais a implementar.

---

## PHPStan

Todos os arquivos backend do módulo passam no **PHPStan Level 5** com 0 erros:

```bash
C:\xampp\php\php.exe vendor/bin/phpstan analyse \
  app/Controllers/Admin/InstagramController.php \
  app/Services/Instagram/InstagramApiService.php \
  app/Repositories/InstagramPostRepository.php \
  --level=5 --no-progress
```

---

## Histórico de Alterações

| Data | Versão | Descrição |
|---|---|---|
| 2026-09-27 | 1.0.0 | Criação do módulo: migration, service, repository, controller, rotas, sidebar, CLI |
