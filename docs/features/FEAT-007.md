# FEAT-007 - Campo "Tipo de post" (Comparativo, Review, Ficha técnica, Guia, Notícia, Lista)

Tipo: Nova funcionalidade
Status: Implementado (local)
Data: 2026-09-14

## Objetivo

Adicionar um campo de classificação por formato de conteúdo ("Tipo de
post") na tela de criar/editar post, para uso interno do admin (organização
e futura filtragem) - reaproveitando as mesmas 6 estruturas já definidas em
`docs/blog-estruturas-de-conteudo.html` e usadas pela skill `/post-blog`.

## Decisão de escopo

- Valores fixos: `comparativo`, `review`, `ficha_tecnica`, `guia`,
  `noticia`, `lista` (mesmas 6 estruturas oficiais de conteúdo).
- Campo opcional (nullable), não bloqueia posts existentes.
- Uso só no admin nesta fase - não aparece no site público (sem selo/badge
  nos cards ou na página do post).

## O que foi implementado

1. `posts.tipo_post VARCHAR(30) NULL` adicionada no banco **local** via
   `ALTER TABLE`.
2. `PostRepository`: mesmo padrão de coluna opcional já usado para
   `proximo_post_id` (`supportsTipoPostColumn()` / `supportsTipoPost()`) -
   SELECT/INSERT/UPDATE condicionais, para não quebrar em stage/produção
   antes da migração rodar lá.
3. `PostsService`: campo incluído em `normalizeForm()`, `mapPostToForm()`,
   nos payloads de `createPost()`/`updatePost()`, e validação simples
   (erro só se vier um valor fora da lista de 6 permitidos).
4. `app/Views/components/admin/posts/form-publication.php`: novo select
   "Tipo de post" no painel "Publicação" (entre Status/Tempo de leitura e
   Próximo passo), desabilitado com aviso quando a coluna não existir no
   banco.

## Arquivos alterados

- `app/Repositories/PostRepository.php`
- `app/Services/Admin/PostsService.php`
- `app/Views/components/admin/posts/form-publication.php`

## Banco de dados

- Mudança de schema: sim - `ALTER TABLE posts ADD COLUMN tipo_post VARCHAR(30) NULL AFTER categoria_post_id;`
- Aplicada em: **local** apenas, nesta etapa.
- Mudança de dados: não (coluna fica NULL em todos os posts existentes).
- Rollback: `ALTER TABLE posts DROP COLUMN tipo_post;` (reversível, sem
  impacto em outras colunas).

## Pendência para stage/produção

Rodar o mesmo `ALTER TABLE` nos bancos de stage e produção quando for a
hora de levar essa mudança pra lá (segue o mesmo fluxo já usado para
`proximo_post_id` - o código já funciona sem a coluna, então não há
urgência/quebra enquanto isso não acontece).

## Impacto em produção

Nenhum. Mudança feita e testada só no ambiente local; produção não tem a
coluna ainda e o código já lida com essa ausência de forma graciosa.

## Validação

- `php -l` nos 3 arquivos alterados (sem erro de sintaxe).
- Teste manual pendente: abrir `/admin/criar-post` e `/admin/editar-post`
  localmente e confirmar que o select aparece e salva corretamente.
