# FEAT-006 - Seletor de ambiente-alvo e escrita direta em producao (Posts, Categoria, Comentarios, Midia, Newsletter, Links)

Tipo: Nova funcionalidade
Status: Implementado (local), aguardando validacao e publicacao
Data: 2026-09-13

## Objetivo

Permitir que o admin local, quando o usuario seleciona "Alvo: Producao" no
seletor de ambiente ja existente no Dashboard/Usuarios/Configuracoes/
Home-e-Menus, tambem crie/edite/exclua Posts, Categorias, Comentarios,
Midia, Newsletter e Links diretamente no banco (e, quando aplicavel, nos
arquivos de midia) do ambiente selecionado - sem precisar do fluxo de
content-sync para essas operacoes do dia a dia.

## Decisao de escopo (registrada durante a conversa com o usuario)

- **Sem backup obrigatorio e sem confirmacao digitada para criar/editar.**
  O usuario decidiu explicitamente que operacoes rotineiras de conteudo
  (salvar um post, editar uma categoria, moderar um comentario, etc.) nao
  precisam de fricção extra so por causa do ambiente-alvo - o mesmo cuidado
  que existiria publicando direto em producao. So operacoes destrutivas
  (exclusao permanente) e pacotes/sincronizacoes em massa continuam com
  salvaguarda.
- **Confirmacao digitada "PRODUCAO" mantida so em exclusoes permanentes**
  (destroy() de Posts, Categorias, Comentarios, Midia, Newsletter, Links),
  reaproveitando o `App\Support\ProductionChangeGuard` ja usado em
  Usuarios/Configuracoes.
- **Sem mudanca em `config/routes.php`** - nenhuma rota ganhou novo
  requisito de capability. O comportamento em stage/producao real (quando
  o codigo estiver publicado la) continua identico ao que era antes deste
  FEAT, preservando a decisao tomada na conversa do FIX-008.
- **Limite conhecido em Midia:** a listagem/navegacao da biblioteca
  (`/admin/midia`) continua mostrando so os arquivos locais, mesmo com
  alvo=producao - reimplementar a varredura de arquivos via FTP remoto e um
  projeto a parte, bem maior. So upload de arquivo novo e exclusao de um
  arquivo especifico passam a valer pro ambiente selecionado.
- **Limite conhecido em exclusao de Post:** mover a pasta de midia do post
  para a lixeira tecnica (`uploads/trash/posts/...`) continua sendo uma
  operacao local em disco; nao ha um equivalente remoto implementado nesta
  fase (a exclusao do registro no banco, essa sim, respeita o ambiente-alvo).

## O que foi implementado

1. `app/Views/layouts/admin.php`: adicionados os padroes de rota de Posts,
   Categoria, Comentarios, Midia, Newsletter e Links a lista que exibe o
   banner "Contexto multiambiente" (jah existia para
   Dashboard/Usuarios/Configuracoes/Home-e-Menus/Health).
2. Novo `App\Support\TargetEnvironmentUploads`: helper que envia
   (`putFile`) ou remove (`deleteFile`) um arquivo via FTP no ambiente-alvo,
   usando o profile de `config/content-sync.php` (mesmas variaveis
   `CONTENT_SYNC_PRODUCTION_FTP_*` / `BACKUP_PRODUCTION_FTP_*` ja usadas
   pelo content-sync). Nao faz nada quando o alvo e o proprio ambiente de
   execucao.
3. `App\Services\Admin\MidiaService`: dois metodos novos,
   `pushToTargetEnvironment()` e `deleteFromTargetEnvironment()`, que
   resolvem o caminho absoluto local e delegam pro helper acima. Reaproveitado
   por PostsService, LinksService e MidiaController.
4. `PostsController`, `CategoriasController`, `ComentariosController`,
   `NewsletterController`, `LinksController`: trocado `$GLOBALS['pdo']` por
   `TargetEnvironmentDatabase::pdo($targetEnvironment)`, mesmo padrao ja
   usado em `UsuariosController`. `MidiaController` mantido com PDO local
   (a listagem/uso de midia continua sendo calculada so a partir do banco
   local, ver limite acima).
5. `PostsService` e `LinksService`: novo parametro opcional
   `$targetEnvironment` no construtor; apos um upload de imagem (capa,
   thumb, corpo do post, imagem do link) ser salvo localmente, o arquivo e
   tambem enviado pro ambiente-alvo via `MidiaService::pushToTargetEnvironment()`.
6. Confirmacao "PRODUCAO" adicionada aos metodos `destroy()` de Posts,
   Categorias, Comentarios, Midia, Newsletter e Links, com o mesmo padrao
   visual (caixa amber + campo de texto) ja usado em Usuarios. Views de
   exclusao atualizadas: `admin/posts/delete.php`,
   `admin/categories/delete.php`, `admin/comments/delete.php`,
   `admin/newsletter/delete.php`, `admin/links/delete.php`,
   `admin/media/delete.php`.

## Arquivos alterados

- `app/Views/layouts/admin.php`
- `app/Support/TargetEnvironmentUploads.php` (novo)
- `app/Services/Admin/MidiaService.php`
- `app/Services/Admin/PostsService.php`
- `app/Services/Admin/LinksService.php`
- `app/Controllers/Admin/PostsController.php`
- `app/Controllers/Admin/CategoriasController.php`
- `app/Controllers/Admin/ComentariosController.php`
- `app/Controllers/Admin/NewsletterController.php`
- `app/Controllers/Admin/LinksController.php`
- `app/Controllers/Admin/MidiaController.php`
- `app/Views/admin/posts/delete.php`
- `app/Views/admin/categories/delete.php`
- `app/Views/admin/comments/delete.php`
- `app/Views/admin/newsletter/delete.php`
- `app/Views/admin/links/delete.php`
- `app/Views/admin/media/delete.php`

## Banco de dados

- Mudanca de schema: nao
- Mudanca de dados: nao
- Script necessario: nao

## Pre-requisito para funcionar contra producao de verdade

As variaveis `CONTENT_SYNC_PRODUCTION_DB_*` e `CONTENT_SYNC_PRODUCTION_FTP_*`
(ou os equivalentes `BACKUP_PRODUCTION_*`) precisam estar preenchidas no
`.env` local. Sem isso, `TargetEnvironmentDatabase::pdo('production')` e
`TargetEnvironmentUploads` lancam excecao com mensagem clara em vez de
falhar silenciosamente.

## Impacto em producao

Nenhum imediato. Este pacote so muda o comportamento do admin quando
**executado localmente** com o ambiente-alvo setado para stage/producao;
o comportamento do codigo publicado em stage/producao continua identico
(nenhuma rota nova, nenhum capability novo). O impacto real so aparece
quando alguem usar o seletor de ambiente-alvo no admin local pra
efetivamente gravar em producao.

## Risco

Medio. Aceito explicitamente pelo usuario: escrita/edicao de conteudo em
producao sem backup previo automatico. Mitigado apenas para exclusao
permanente (confirmacao "PRODUCAO"). Recomendacao registrada (nao
implementada, fora de escopo): um servico central de escrita remota com
capabilities dedicadas, caso o uso justifique mais protecao no futuro.

## Validacao

- `php -l` em todos os arquivos alterados/criados (sem erro de sintaxe).
- Teste manual pendente: usar o seletor de ambiente-alvo em cada uma das 6
  telas com credenciais de producao preenchidas, e confirmar leitura/escrita
  correta antes de considerar "validado em producao".

## Status Stage/Producao

- Origem validada: nao validada.
- Paridade local -> stage: nao validada.
- Producao: nao publicado.
