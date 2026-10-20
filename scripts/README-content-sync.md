# Rotina de Conteudo

Esta rotina foi feita para publicar conteudo do ambiente local na producao sem sincronizar o banco inteiro.

Escopo da V1:

- categorias de post (`categoria_post`)
- posts
- historico de slug (`post_slug_history`)
- links
- configuracoes publicas selecionadas
- uploads realmente referenciados por posts, links e configuracoes

Ela nao mexe em:

- usuarios
- comentarios
- newsletter
- likes
- estatisticas
- dados operacionais do admin

## Comandos

```bash
php scripts/preflight-check.php
php scripts/content-sync.php export local
php scripts/content-sync.php status
php scripts/content-sync.php verify latest
php scripts/content-sync.php apply latest production --force
php scripts/content-sync.php apply latest local --force
```

## Preflight (obrigatorio antes de alterar/publicar)

```bash
php scripts/preflight-check.php
```

Essa rotina valida:

- caminho canonico do projeto (evita editar/publicar a pasta errada)
- lock pendente de rotina de conteudo
- status local do git (mudancas em aberto)
- encoding suspeito (acentos quebrados / mojibake)
- marcadores de merge pendentes

Se houver falha bloqueante, corrija antes de continuar.

O scanner aceita acentos legítimos como “Âmbar” e “BOTÃO” e identifica sequências características de encoding corrompido. Teste isolado: `php scripts/verify-preflight-encoding.php`. Regras e resultados em `docs/features/preflight-encoding.md`.

## Fluxo recomendado

1. Gere um backup antes de publicar:

```bash
php scripts/backup.php run production
php scripts/backup.php verify latest
```

2. Gere o pacote de conteudo local:

```bash
php scripts/content-sync.php export local
```

3. Verifique o pacote:

```bash
php scripts/content-sync.php verify latest
```

4. Se quiser, valide primeiro no proprio local:

```bash
php scripts/content-sync.php apply latest local --force
```

5. Aplique na producao:

```bash
php scripts/content-sync.php apply latest production --force
```

## Saida do pacote

Cada exportacao cria uma pasta em:

```text
storage/content-sync/<perfil>_AAAA-MM-DD_HH-mm-ss
```

Dentro dela:

- `manifest.json`
- `data/categoria_post.json`
- `data/posts.json`
- `data/post_slug_history.json`
- `data/links.json`
- `data/configuracoes.json`
- `uploads.zip`

## Regras importantes

### Alinhamento de stage em 11/10/2026

`scripts/reels-review/align-stage-20261011.php` usa produção como referência
para os 33 artigos existentes e acrescenta quatro artigos presentes somente
no banco local. O destino de escrita é exclusivamente stage. Categorias,
autores e próximos artigos são remapeados por slug/nome, sem reutilizar IDs
de outro ambiente. Métricas dos artigos existentes e categorias são preservadas.

```powershell
C:/xampp/php/php.exe scripts/reels-review/align-stage-20261011.php --inspect
C:/xampp/php/php.exe scripts/reels-review/align-stage-20261011.php --prepare
C:/xampp/php/php.exe scripts/migrate.php --target=stage --only=0006_add_post_type.sql
C:/xampp/php/php.exe scripts/reels-review/align-stage-20261011.php --apply
C:/xampp/php/php.exe scripts/reels-review/align-stage-20261011.php --verify
```

O runner aceita `--only=<arquivo.sql>` para selecionar uma única migration;
sem essa opção, mantém a execução sequencial habitual. Neste alinhamento,
não executar migrations pendentes de outros módulos. A migration 0006 adiciona
`posts.tipo_post` de forma idempotente e registra sua aplicação no mecanismo
oficial de migrations.

O pacote e os backups ficam em `storage/stage-content/alignment-20261011/`:
`package.json` contém os registros anteriores, schema, referências e hashes;
os arquivos `original-*.bin` preservam imagens divergentes e `asset-*.bin`
guardam a mídia de origem. `applied.json` e `verified.json` só são produzidos
após as respectivas operações. O pacote existente nunca é sobrescrito.
Mudanças na origem ou edição concorrente no destino interrompem a operação.

Imagens diferentes recebem nomes versionados, sem substituir as antigas.
As atualizações editoriais ocorrem em transação; uma falha antes do commit
restaura os dados automaticamente. Após o commit, recuperação exige um plano
próprio baseado nos registros anteriores do pacote, preservando alterações
posteriores e métricas. A coluna aditiva e as imagens antigas podem permanecer;
o script não oferece exclusão automática. Produção, Instagram e agendamentos
não são alterados.

O complemento de navegação aprovado para stage usa o deploy já existente:

```powershell
C:/xampp/php/php.exe scripts/review-navigation-deploy.php --backup --target=stage
C:/xampp/php/php.exe scripts/review-navigation-deploy.php --deploy --target=stage
C:/xampp/php/php.exe scripts/review-navigation-deploy.php --verify --target=stage
```

Backup e payload separados em `storage/review-navigation-20261011-stage/`.
O patch é preparado sobre os dois arquivos remotos, alterando apenas o
carregamento de tipo de post e a escolha do próximo artigo dos reviews.
Destinos inválidos e outros tipos mantêm a navegação cronológica. Não repetir
`--backup` depois do deploy: o estado anterior é imutável. Recuperação, mediante
autorização própria, restaura apenas esses dois arquivos do backup após conferir
edições concorrentes. Sem `--target`, o script mantém o comportamento anterior
de produção; sempre passar `--target=stage` nesta operação.

### Promoção para produção em 11/10/2026

`scripts/reels-review/promote-production-20261011.php` promove para a produção
os quatro artigos validados previamente no stage, acompanhados de suas 16 imagens.
Os 33 artigos existentes de produção são preservados como base operacional, mantendo
suas métricas reais (`views`, `curtidas`, `comentarios_count`) e status inalterados.
Categorias e autores são mapeados pelos registros oficiais do banco de produção.

```powershell
C:/xampp/php/php.exe scripts/reels-review/promote-production-20261011.php --inspect
C:/xampp/php/php.exe scripts/reels-review/promote-production-20261011.php --prepare
C:/xampp/php/php.exe scripts/migrate.php --target=production --only=0006_add_post_type.sql
C:/xampp/php/php.exe scripts/reels-review/promote-production-20261011.php --apply
C:/xampp/php/php.exe scripts/reels-review/promote-production-20261011.php --verify
```

A migration 0006 (`posts.tipo_post`) é registrada formalmente com `--only=0006_add_post_type.sql`.
O pacote e os backups imutáveis ficam em `storage/production-content/promotion-20261011/`:
`package.json` guarda o estado prévio dos 33 artigos, categorias e schema;
arquivos `asset-*.bin` preservam a mídia local de origem.
`applied.json` e `verified.json` registram IDs gerados e confirmação de integridade.

As 16 imagens são enviadas para `uploads/posts/<slug>/images/` via FTP. A inserção dos artigos
ocorre em transação exclusiva com lock e reconexão pós-FTP; em falha antes do commit, aciona
rollback automático. Produção passou de 33 para 37 artigos, todos com respostas HTTP 200 nas rotas `/post/<slug>`.
Instagram e agendamentos não são alterados.

- a rotina faz `upsert` por slug e historico de slug
- ela preserva metricas reais da producao, como views, curtidas e comentarios dos posts
- links sao atualizados pelos campos editoriais, sem sobrescrever dados de monitoramento
- uploads sao enviados apenas para os arquivos incluidos no pacote
- a rotina nao remove conteudo que exista so na producao

### Saga Darksiders na stage em 15/10/2026

`scripts/reels-review/stage-darksiders-20261015.php` publica somente na **stage** os seis artigos da saga Darksiders (origem: banco e `public/uploads/posts/<slug>/` locais), com as 30 imagens e os 6 áudios de narração. Produção, Instagram e agendamentos não são alterados; os outros artigos da stage não são tocados.

```powershell
C:/xampp/php/php.exe scripts/reels-review/stage-darksiders-20261015.php --inspect
C:/xampp/php/php.exe scripts/reels-review/stage-darksiders-20261015.php --prepare
C:/xampp/php/php.exe scripts/reels-review/stage-darksiders-20261015.php --apply
C:/xampp/php/php.exe scripts/reels-review/stage-darksiders-20261015.php --verify
```

- Os seis slugs são fixos no script. `--prepare` recusa se algum já existir na stage e grava o pacote imutável em `storage/stage-content/darksiders-20261015/` (estado anterior da stage, os 36 arquivos e o fingerprint da origem). `--apply` envia por FTP (só destino `/stage/uploads`), reconecta, reconfere a origem e insere os seis artigos numa transação com lock; `--verify` confere o banco e os hashes remotos.
- Decisões do usuário: o #87 (A Jornada de Morte) estava com `categoria_post_id = 0` no local e entrou na stage na categoria `games`, sem alterar o local; os seis entram como `rascunho`, igual ao local, então a rota pública `/post/<slug>` da stage responde 404 até serem publicados.
- Resultado: IDs 50 a 55 na stage (38 → 44 artigos), 36 hashes remotos conferidos, imagens e áudios com HTTP 200 em `https://estrategianerd.com.br/stage/uploads/posts/<slug>/...`.
- Publicação (15/10/2026): `--publish` trocou o status dos IDs 50 a 55 para `publicado` (transação, aborta se não afetar exatamente 6 linhas); `--unpublish` volta para `rascunho`. Validação: 6 páginas `/stage/post/<slug>` com HTTP 200 e `X-Robots-Tag: noindex, nofollow`, 36 arquivos com 200, `/blog/games` lista os 6, sitemap da stage lista os 6, sem rolagem lateral em 375 px e sem imagem quebrada.
- Bloqueio de indexação da stage: `scripts/stage-noindex-deploy.php` (`--backup`, `--deploy`, `--verify`) enviou só `bootstrap.php` (patch sobre a versão remota, que difere da local) e `SeoController.php`; backups em `storage/stage-noindex-20261015/before/`. Regra registrada em `docs/CODEX-REGRAS-PERMANENTES.txt` (seção 19) e na Documentação Técnica (Segurança).
- Recuperação (exige autorização própria): remover os seis artigos pelos IDs de `applied.json` e os arquivos listados no pacote; nenhuma remoção automática foi implementada.

### Promoção da Saga Darksiders para produção em 15/10/2026

`scripts/reels-review/promote-darksiders-production-20261015.php` promove para a **produção** os seis artigos da saga Darksiders validados na stage, acompanhados de seus 36 assets (30 imagens e 6 áudios de narração). Os 39 artigos existentes de produção são rigorosamente preservados, mantendo suas métricas operacionais reais intactas.

```powershell
C:/xampp/php/php.exe scripts/reels-review/promote-darksiders-production-20261015.php --inspect
C:/xampp/php/php.exe scripts/reels-review/promote-darksiders-production-20261015.php --prepare
C:/xampp/php/php.exe scripts/reels-review/promote-darksiders-production-20261015.php --apply
C:/xampp/php/php.exe scripts/reels-review/promote-darksiders-production-20261015.php --verify
```

- Pacote e backups imutáveis salvos em `storage/production-content/darksiders-20261015/` (`package.json`, `asset-*.bin`, `applied.json`, `verified.json`).
- Os 36 assets foram enviados para `/domains/estrategianerd.com.br/public_html/uploads/posts/<slug>/...` via FTP, com conferência remota de SHA-256.
- A inserção ocorreu em transação atômica com lock exclusivo de tabela (`FOR UPDATE`); produção passou de 39 para 45 artigos (IDs gerados: 52 a 57).
- Validação completa: 45 artigos conferidos, 36 hashes remotos validados via FTP e todas as 6 rotas públicas `/post/<slug>` e 36 assets remotos retornando HTTP 200 OK.
- Agendamento da saga (15/10/2026): executado por `scripts/reels-review/apply-darksiders-schedule-20261015.php`, configurando os 6 artigos do blog em produção como `agendado` sequencial de 29/10 a 03/11/2026 às 12:00 e cadastrando os 6 Reels correspondentes na fila do Instagram para as mesmas datas às 19:30 (IDs 11242 a 11247). As rotas públicas respondem HTTP 404 até suas respectivas datas de liberação.

## Interface local

Tambem existe uma interface fora do admin em:

```text
/local/conteudo
```

Ela permite:

- exportar pacote local
- verificar o ultimo pacote
- aplicar no local
- publicar na producao
- revisar os pacotes recentes

## Configuracao

As opcoes ficam em:

- `config/content-sync.php`
- variaveis opcionais no `.env`

A rotina usa, por padrao, os mesmos dados remotos do backup como fallback.
Se quiser separar totalmente as credenciais, voce pode preencher:

```text
CONTENT_SYNC_ROOT=
CONTENT_SYNC_7ZIP_BINARY=

CONTENT_SYNC_PRODUCTION_DB_HOST=
CONTENT_SYNC_PRODUCTION_DB_PORT=3306
CONTENT_SYNC_PRODUCTION_DB_DATABASE=
CONTENT_SYNC_PRODUCTION_DB_USERNAME=
CONTENT_SYNC_PRODUCTION_DB_PASSWORD=

CONTENT_SYNC_PRODUCTION_UPLOAD_MODE=ftp
CONTENT_SYNC_PRODUCTION_FTP_HOST=
CONTENT_SYNC_PRODUCTION_FTP_PORT=21
CONTENT_SYNC_PRODUCTION_FTP_USERNAME=
CONTENT_SYNC_PRODUCTION_FTP_PASSWORD=
CONTENT_SYNC_PRODUCTION_FTP_ROOT=domains/estrategianerd.com.br/public_html/uploads
CONTENT_SYNC_PRODUCTION_FTP_PASSIVE=true
```
