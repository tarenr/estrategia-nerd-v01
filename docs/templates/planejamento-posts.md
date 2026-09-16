ID: FEAT-0XX
Tipo: Planejamento de Posts
Nome: Planejamento de leva de posts - [nome da leva]

## Objetivo

Planejar uma leva de posts (blog e/ou Instagram) antes de iniciar a
producao de conteudo, definindo pauta, prioridade, prazos e o fluxo
de publicacao de cada post.

## Contexto

Descrever de onde veio a pauta desta leva (ex: validacao com o
usuario, sazonalidade, lancamento de produto, etc.) e o objetivo
editorial (ex: SEO, engajamento, cobertura de nicho).

## Fluxo de publicacao por post (padrao)

Posts de blog usam uma rotina propria de conteudo (diferente do
fluxo de deploy de codigo `local -> stage -> producao`). Ela publica
conteudo (posts, categorias, links, configs, uploads referenciados)
direto do local pra producao, sem sincronizar o banco inteiro. Nao
pular etapas.

1. **Geracao do conteudo** - usar a skill apropriada:
   - `post-blog` para posts de blog (titulo, resumo, conteudo HTML,
     SEO, imagem de capa e imagens de apoio). Gera um JSON no formato
     de `.claude/skills/post-blog/SKILL.md`.
   - `post-instagram` para posts de Instagram (legenda, hashtags,
     variacoes de hook, imagem).
2. **Rascunho local** - inserir o JSON gerado como rascunho no banco
   local:
   ```bash
   php scripts/en-blog-draft.php caminho/para/post.json
   ```
   Isso cria o post com `status: rascunho` so no banco local (via
   `PostRepository`), sem tocar producao.
3. **Revisao manual** - revisar texto, imagens, SEO e categoria
   antes de seguir. Ajustar o que for necessario direto no rascunho
   (admin local).
4. **Preflight obrigatorio** - antes de publicar qualquer coisa:
   ```bash
   php scripts/preflight-check.php
   ```
5. **Backup de producao** - antes de publicar:
   ```bash
   php scripts/backup.php run production
   php scripts/backup.php verify latest
   ```
6. **Empacotar o conteudo local**:
   ```bash
   php scripts/content-sync.php export local
   php scripts/content-sync.php verify latest
   ```
7. **Validar no proprio local (opcional, recomendado)**:
   ```bash
   php scripts/content-sync.php apply latest local --force
   ```
8. **Publicar em producao** - sempre manual e aprovado
   explicitamente, nunca automatico:
   ```bash
   php scripts/content-sync.php apply latest production --force
   ```
   Existe tambem uma interface local em `/local/conteudo` que faz
   export/verify/apply/publish sem CLI (essa rota nunca vai para
   producao).
9. **Checklist pos-deploy** - conferir
   `docs/checklists/post-deploy.md` (site abriu, login/logout admin,
   criar post funcionando, imagens subindo, rotas criticas
   respondendo, sem erro relevante no log, performance normal).

## Checklist minimo por post

- [ ] Titulo e resumo definidos
- [ ] Conteudo revisado (sem erros factuais ou de portugues)
- [ ] SEO (meta title, meta description, slug) revisado
- [ ] Imagem de capa e imagens de apoio geradas/aprovadas
- [ ] Categoria correta
- [ ] Rascunho salvo no banco local
- [ ] Paridade local -> stage validada
- [ ] Aprovacao explicita para publicar em producao
- [ ] Publicado em producao

## Lista de posts desta leva

| # | Titulo | Categoria | Prioridade / Prazo | Status |
|---|--------|-----------|---------------------|--------|
| 1 |        |           |                     | nao iniciado |

## Observacoes

Espaco para riscos, dependencias externas (ex: prazos de terceiros,
eventos, lancamentos) ou decisoes tomadas durante o planejamento.
