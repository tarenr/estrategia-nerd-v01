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

- a rotina faz `upsert` por slug e historico de slug
- ela preserva metricas reais da producao, como views, curtidas e comentarios dos posts
- links sao atualizados pelos campos editoriais, sem sobrescrever dados de monitoramento
- uploads sao enviados apenas para os arquivos incluidos no pacote
- a rotina nao remove conteudo que exista so na producao

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
