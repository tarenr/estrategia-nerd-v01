# Rotina de Backup

Esta rotina fica fora do admin e foi feita para:

- gerar backup full
- validar se o pacote ficou integro
- registrar o status localmente pelo manifesto
- marcar manualmente quando o pacote ja foi enviado para a nuvem
- permitir restore controlado com confirmacao explicita

## Comandos

```bash
php scripts/backup.php run local
php scripts/backup.php run production
php scripts/backup.php status
php scripts/backup.php verify
php scripts/backup.php mark-uploaded
php scripts/backup.php mark-uploaded 2026-04-11_10-30-00
php scripts/backup.php restore latest local all --force
```

## Saida do backup

Cada execucao cria uma pasta em:

```text
D:\Taren\Documents\Backup\Estratégia Nerd\AAAA-MM-DD_HH-mm-ss
```

Dentro dela:

- `database.sql`
- `uploads.zip`
- `manifest.json`

## Como o controle funciona

- `status` mostra o ultimo backup, se ele esta valido e se ja foi marcado como enviado
- `verify` revalida o ultimo backup (ou um ID especifico)
- `mark-uploaded` atualiza o `manifest.json` do backup depois que voce enviar manualmente para a nuvem

## Restore

O restore exige `--force` para evitar execucao acidental.

Exemplos:

```bash
php scripts/backup.php restore latest local all --force
php scripts/backup.php restore 2026-04-11_10-30-00 local database --force
php scripts/backup.php restore 2026-04-11_10-30-00 production uploads --force
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
```

## Retencao

Por padrao, o script mantem os ultimos `14` backups locais.

Voce pode ajustar com:

```text
BACKUP_RETENTION=14
```
