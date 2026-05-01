# Rollback Oficial EN

1. Preservar backup completo do estado atual
2. Restaurar backup completo validado, quando necessario
3. Reaplicar pacote tecnico estavel, se o restore completo nao for o caminho escolhido
4. Limpar cache, se aplicavel
5. Validar login
6. Validar posts
7. Validar rotas criticas
8. Comunicar retorno

## Rotas minimas

- /
- /blog
- /post/{slug}
- /admin
- /central-nerd

## Campos de registro

- Release:
- Backup completo usado:
- Escopo restaurado: banco / uploads / system_files / all
- Origem do pacote tecnico, quando aplicavel:
- Responsavel:
- Data:
- Motivo:
- Resultado:

## Observacoes

- Em producao, `system_files` corresponde ao nucleo tecnico em `_app_core`.
- Banco e uploads de producao nao devem ser substituidos por stage/local sem confirmacao explicita.
- Antes de qualquer rollback em producao, validar se existe backup completo recente e integro.
