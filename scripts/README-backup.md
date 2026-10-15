# Rotina de Backup

Esta rotina fica fora do admin e foi feita para:

- gerar backup completo do sistema
- validar se o pacote ficou integro
- registrar o status localmente pelo manifesto
- marcar manualmente quando o pacote ja foi enviado para a nuvem
- permitir restore controlado com confirmacao explicita

O backup completo inclui:

- banco de dados
- uploads
- arquivos do sistema

A rotina de conteudo fica separada em `scripts/content-sync.php` e deve ser usada apenas para pacote editorial/conteudo.

## Comandos

```bash
php scripts/backup.php run local
php scripts/backup.php run local --no-uploads
php scripts/backup.php run stage
php scripts/backup.php run production
php scripts/backup.php status
php scripts/backup.php verify
php scripts/backup.php mark-uploaded
php scripts/backup.php mark-uploaded 2026-04-11_10-30-00
php scripts/backup.php delete-local BS-PROD-20260501-101359 BS-PROD-20260501-101359
php scripts/backup.php restore latest local all --force
```

## Saida do backup

Cada execucao cria uma pasta em:

```text
<BACKUP_ROOT>\03-prod\dados\BS-PROD-AAAAMMDD-HHMMSS
```

Desde 15/10/2026 o `BACKUP_ROOT` e `C:\Users\WINDOWS\ProjectBackup\estrategia-nerd` (antes ficava em `E:\Taren\Documents\Backup\Estratégia Nerd`, que foi mantida intacta como copia). `BACKUP_RETENTION` define quantos backups cada ambiente guarda (hoje 7; era 14 ate 15/10/2026). A retencao so conta e apaga backups com `manifest.json`; uma pasta sem manifesto (backup interrompido) fica fora da contagem e precisa ser removida a mao.

Dentro dela:

- `database.sql`
- `uploads.zip` (nao existe nos backups feitos com `--no-uploads`)
- `system-files.zip`
- `manifest.json`

### Rotina diaria (`scripts/backup/Invoke-DailyBackup.ps1`)

- Ambiente **local**: de segunda a sabado roda com `--no-uploads` (so banco e arquivos do sistema); aos **domingos** roda completo, com `uploads.zip`. O dia e decidido por `[DayOfWeek]` numerico (domingo = 0), sem depender do idioma do Windows. Os uploads ja sao cobertos todo dia pelo backup geral (restic, pasta `Projects`). Se a rodada de domingo falhar, o log registra `ALERTA`.
- **Stage** e **producao**: sempre completos.
- Para testar sem executar nada nem enviar ao Drive: `powershell -File scripts\backup\Invoke-DailyBackup.ps1 -DiaDaSemana 0 -SomenteMostrarComandos` (0 = domingo ... 6 = sabado).

Quando enviado para o Google Drive (`Estrategia Nerd - Backups de Conteudo/Sistema/<Local|Stage|Producao>/<BACKUP_ID>`), a pasta remota recebe os mesmos arquivos.

## Arquivos do sistema

Para manter o backup do sistema enxuto (~25 MB em vez de >500 MB), `BackupManager` exclui automaticamente diretórios transitórios, caches e rascunhos de desenvolvimento: `scratch/`, `tmp/`, `conteudo-planejado/`, `resources/reels-review/`, `.codegraph/`, `appflowy-cloud/`, `notion-import/`, `.claude/`, alem dos ja excluidos `.git/`, `node_modules/`, `storage/`, `vendor/` e `public/uploads/`.

Em ambientes FTP que usam `public_html/_app_core`, a rotina detecta
`_app_core/bootstrap.php` e usa `_app_core` como raiz tecnica do sistema.
Isso evita que o backup completo da producao inclua duplicidades da raiz
`public_html`, como `stage`, assets publicos antigos ou estruturas legadas.

Uploads continuam separados em `uploads.zip`.

## Como o controle funciona

- `status` mostra o ultimo backup, se ele esta valido e se ja foi marcado como enviado
- `verify` revalida o ultimo backup (ou um ID especifico)
- `mark-uploaded` atualiza o `manifest.json` do backup depois que voce enviar manualmente para a nuvem
- `delete-local` remove um backup da pasta local somente quando o ID e repetido como confirmacao
- Envios para o Google Drive (`upload-cloud` ou botoes do painel local) registram destino, quantidade de arquivos e tamanho total enviado
- Backups ja enviados para a nuvem nao sao reenviados (o envio recusa backup ja marcado como enviado)

## Restore

O restore exige `--force` para evitar execucao acidental.

Exemplos:

```bash
php scripts/backup.php restore latest local all --force
php scripts/backup.php restore 2026-04-11_10-30-00 local database --force
php scripts/backup.php restore 2026-04-11_10-30-00 production uploads --force
php scripts/backup.php restore 2026-04-11_10-30-00 production system_files --force
```

## Configuracao

As opcoes ficam em:

- `config/backup.php`
- variaveis opcionais no `.env`

Para o perfil `production`, preencha no `.env`:

```text
BACKUP_PRODUCTION_DB_HOST=
BACKUP_PRODUCTION_DB_PORT=3306
BACKUP_PRODUCTION_DB_DATABASE=
BACKUP_PRODUCTION_DB_USERNAME=
BACKUP_PRODUCTION_DB_PASSWORD=

BACKUP_PRODUCTION_UPLOAD_MODE=ftp
BACKUP_PRODUCTION_FTP_HOST=
BACKUP_PRODUCTION_FTP_PORT=21
BACKUP_PRODUCTION_FTP_USERNAME=
BACKUP_PRODUCTION_FTP_PASSWORD=
BACKUP_PRODUCTION_FTP_ROOT=domains/estrategianerd.com.br/public_html/uploads
BACKUP_PRODUCTION_FTP_PASSIVE=true

CONTENT_SYNC_PRODUCTION_CODE_FTP_ROOT=domains/estrategianerd.com.br/public_html
BACKUP_PRODUCTION_SYSTEM_EXCLUDE=stage
```

`CONTENT_SYNC_PRODUCTION_CODE_FTP_ROOT` pode apontar para `public_html`; o
backup resolve automaticamente para `public_html/_app_core` quando o nucleo
tecnico existe.

## Retencao

Por padrao, o codigo mantem os ultimos `14` backups locais, mas desde 15/10/2026 o valor configurado no `.env` e `7`. A retencao so conta e apaga backups que possuem `manifest.json`; pastas sem manifesto (originadas de backups interrompidos) ficam fora da contagem e precisam ser removidas a mao.

Voce pode ajustar com:

```text
BACKUP_RETENTION=7
```
