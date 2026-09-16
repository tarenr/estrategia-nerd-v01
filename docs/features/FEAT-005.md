ID: FEAT-005
Tipo: Planejamento de Posts
Nome: Planejamento da primeira leva de posts do blog (11 posts)

## Objetivo

Planejar a producao e publicacao de uma leva de 11 posts para o
blog do Estrategia Nerd antes de iniciar a montagem de qualquer
post, definindo ordem, prioridade e o fluxo de publicacao de cada
um. A montagem dos posts (uso da skill `post-blog`) fica para depois
deste planejamento, conforme decidido com o usuario.

## Contexto

Lista de posts validada previamente com o usuario, cobrindo as
categorias Hardware, Cultura, Games e Dicas. Um dos posts tem prazo
externo definido: o post sobre Windows 11 24H2 precisa ir ao ar
antes do fim do suporte, em 13/10/2026.

Este documento segue o modelo definido em
`docs/templates/planejamento-posts.md`, que deve ser reaproveitado
nas proximas levas de posts.

## Fluxo de publicacao por post (padrao)

Ver `docs/templates/planejamento-posts.md` para o fluxo completo e
os comandos exatos. Resumo: posts de blog usam uma rotina propria de
conteudo (diferente do deploy de codigo `local -> stage ->
producao`), que publica direto do local pra producao sem sincronizar
o banco inteiro:

1. `post-blog` gera o JSON do post
2. `php scripts/en-blog-draft.php post.json` -> rascunho no banco local
3. Revisao manual (admin local)
4. `php scripts/preflight-check.php` (obrigatorio)
5. `php scripts/backup.php run production` + `verify latest`
6. `php scripts/content-sync.php export local` + `verify latest`
7. (opcional) `php scripts/content-sync.php apply latest local --force`
8. `php scripts/content-sync.php apply latest production --force` -
   sempre manual e aprovado explicitamente
9. Checklist pos-deploy (`docs/checklists/post-deploy.md`)

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

| Ordem | # | Titulo | Categoria | Prioridade / Prazo | Status |
|-------|---|--------|-----------|---------------------|--------|
| 1 | 7 | Windows 11 24H2: fim das atualizacoes em outubro | Dicas | **Alta - prazo: antes de 13/10/2026** | nao iniciado |
| 2 | 1 | RTX 5060 vs RX 9060 XT: qual placa compensa em 1080p | Hardware | Normal | nao iniciado |
| 3 | 2 | AM4 ou AM5 em 2026: qual plataforma escolher | Hardware | Normal | nao iniciado |
| 4 | 8 | Como escolher uma fonte para PC sem cair em marketing | Hardware | Normal | nao iniciado |
| 5 | 4 | Como verificar a saude do SSD e do HD antes de perder arquivos | Dicas | Normal | nao iniciado |
| 6 | 10 | Temperatura de CPU e GPU: quando se preocupar | Dicas | Normal | nao iniciado |
| 7 | 5 | 12 jogos leves para PC fraco ou modesto | Games | Normal | nao iniciado |
| 8 | 11 | A historia do Counter-Strike: por que ainda e gigante | Cultura/Games | Normal | nao iniciado |
| 9 | 3 | 10 filmes nerds essenciais que todo geek deveria ver | Cultura | Normal | nao iniciado |
| 10 | 6 | Animes essenciais pra quem nunca assistiu nenhum | Cultura | Normal | nao iniciado |
| 11 | 9 | Desenhos dos anos 90/2000 que envelheceram bem | Cultura | Normal | nao iniciado |

## Ordem sugerida

Ordem de producao definida e aprovada pelo usuario (2026-09-13).
Criterio: primeiro o que tem prazo externo ou perde relevancia mais
rapido (deadline do post #7, precos/disponibilidade de hardware nos
posts #1, #2 e #8), depois dicas evergreen relacionadas a hardware
(#4, #10), depois games (#5, #11), deixando cultura/nostalgia por
ultimo por serem os posts mais atemporais (#3, #6, #9).

O post #7 e o unico com prazo externo rigido (antes de 13/10/2026) e
deve ser o primeiro a ser produzido, garantindo margem para revisao
e publicacao dentro do prazo.

## Arquivos alterados

- `docs/features/FEAT-005.md` (este arquivo)
- `docs/templates/planejamento-posts.md` (novo template reaproveitavel)

## Impacto em producao

Nenhum nesta etapa. Este documento e apenas planejamento; nenhum
post foi gerado, nenhum rascunho foi criado no banco.

## Risco

Baixo. Risco identificado: perder o prazo do post #7 caso a ordem de
producao nao priorize ele a tempo de publicar antes de 13/10/2026.

## Origem validada

Lista de posts validada com o usuario nesta conversa.

## Validado local: nao
## Validado stage: nao
## Apto para release: nao (planejamento apenas, montagem dos posts ainda nao iniciada)

## Observacoes

A montagem dos posts (uso da skill `post-blog` e insercao dos
rascunhos) fica para uma proxima etapa, a ser aprovada
separadamente. Este documento serve de base para o planejamento das
proximas levas de posts, junto com o template em
`docs/templates/planejamento-posts.md`.
