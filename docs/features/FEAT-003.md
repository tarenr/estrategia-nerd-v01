ID: FEAT-003
Tipo: Nova funcionalidade
Nome: Suite automatizada operacional

Objetivo:
Criar uma suite automatizada operacional em niveis para validar o Estrategia Nerd em ambiente local e stage sem substituir nem quebrar o SmokeTestService existente.

Contexto:
O projeto ja possuia smoke tests de sanidade, login e paginas criticas. A nova suite amplia a base para um contrato operacional mais claro, com niveis, ambientes, relatorios JSON e bloqueios de seguranca rastreaveis.

Arquivos alterados:
- scripts/tests.php
- config/automated-tests.php
- app/Services/Site/AutomatedTestService.php
- app/Services/Site/OperationalTestService.php
- app/Controllers/Admin/AutomatedTestsController.php
- app/Views/admin/tests/index.php
- docs/features/FEAT-003.md

Escopo desta etapa:
- Base da suite nova.
- Configuracao centralizada.
- Execucao via CLI.
- Execucao manual pela tela `/admin/testes`.
- Relatorios JSON em `storage/automated-tests`.
- Safe local.
- Safe stage.
- Routine com selecao explicita de rotinas.
- CRUD tecnico temporario com criacao, verificacao e remocao.
- Midia tecnica temporaria com criacao e remocao.
- Backup sem uploads.
- Preflight local.
- Testes unitarios locais para contratos puros.
- Full reconhecido, mas bloqueado por padrao.
- Tela admin de Testes organizada por abas:
  - Visao Geral
  - Testes Safe
  - Rotinas
  - Unitarios
  - Relatorios

Comandos:
```bash
php scripts/tests.php safe local
php scripts/tests.php unit local
php scripts/tests.php routine local safe database_crud
php scripts/tests.php routine local media preflight
php scripts/tests.php safe stage
php scripts/tests.php routine stage safe database_crud
```

Rotinas selecionaveis:
- `safe`: rotas, login, logout, assets e paginas criticas.
- `database_crud`: cria, confere e remove registros tecnicos temporarios.
- `media`: cria e remove arquivo tecnico temporario em uploads.
- `backup_without_uploads`: gera e verifica backup sem uploads.
- `preflight`: executa preflight local. Disponivel somente em local.

Testes unitarios:
- `unit local`: valida contratos puros sem HTTP, banco, FTP, Dropbox, deploy, restore ou backup.
- Cobre normalizacao de nivel/ambiente, CSRF em HTML, fragmentos obrigatorios, assinaturas de erro, URLs/assets e payloads de resultado/bloqueio.

Tela admin:
- `/admin/testes?aba=visao-geral`: mostra resumo curto por ambiente.
- `/admin/testes?aba=safe`: executa safe local/stage.
- `/admin/testes?aba=rotinas`: executa rotinas operacionais selecionadas.
- `/admin/testes?aba=unitarios`: executa testes unitarios locais e mostra o ultimo resultado.
- `/admin/testes?aba=relatorios`: mostra resumo acumulado, filtros por ambiente/nivel/status, detalhe do relatorio selecionado e separa relatorios operacionais e smoke.

Semantica dos status:
- OK: validado com sucesso.
- FAIL: comportamento esperado falhou.
- SKIP: teste nao aplicavel ou nao executado por condicao legitima.
- BLOCKED: protecao operacional funcionou corretamente e impediu acao nao permitida.

Falha significa que algo esperado nao passou. Bloqueio significa que uma protecao operacional funcionou corretamente.

Regras de seguranca:
- Nunca executar deploy.
- Nunca executar restore.
- Nunca fazer backup com uploads.
- Nunca enviar arquivos ao Dropbox.
- Nunca apagar conteudo real.
- Nunca executar routine/full em producao.
- Nunca sincronizar dados para stage ou producao.
- Stage safe e estritamente leitura + autenticacao, sem escrita.
- Stage routine so executa escrita controlada quando a rotina e explicitamente selecionada.
- Toda rotina com escrita deve registrar dados criados, dados removidos e residuos pendentes.

Bloqueios registrados:
- DEPLOY_DISABLED
- RESTORE_DISABLED
- BACKUP_WITH_UPLOADS_DISABLED
- DROPBOX_UPLOAD_DISABLED
- DATA_SYNC_DISABLED
- FULL_BLOCKED

Relatorio JSON:
Cada execucao grava um arquivo em `storage/automated-tests/*.json` com ambiente, nivel, horarios, duracao, testes executados, OK, falhas, pulos, rotinas executadas, dados criados, dados removidos, residuos pendentes, bloqueios de seguranca, erros e status final.

Leitura de relatorios:
- A tela lista ate 50 relatorios operacionais recentes.
- O painel de detalhe agrupa testes por grupo.
- Falhas aparecem em bloco proprio quando existirem.
- Auditoria mostra bloqueios, dados criados, dados removidos e residuos pendentes.
- Filtros disponiveis: ambiente, nivel e status.

Banco de dados:
- Mudanca de schema: nao
- Mudanca de dados: temporaria e removida na mesma execucao quando `database_crud` e selecionado.
- Script necessario: nao

Impacto em producao:
Nao aplicavel nesta etapa. Producao nao entra nos ambientes permitidos da suite automatizada operacional.

Risco:
Baixo a medio, conforme rotina selecionada. Safe executa apenas leituras HTTP e autenticacao. Routine pode executar escrita controlada e limpeza quando selecionada manualmente. A suite nao executa deploy, restore, Dropbox, backup com uploads ou sincronizacao de dados.

Validacao:
- `php -l` nos arquivos PHP novos.
- `git diff --check`.
- Executar `php scripts/tests.php safe local` quando o ambiente local estiver disponivel.
- Executar `php scripts/tests.php unit local`.
- Executar `php scripts/tests.php safe stage` quando a stage estiver acessivel.
- Executar rotina local selecionada com `php scripts/tests.php routine local database_crud`.
- Conferir a tela `/admin/testes` no navegador local.
- Conferir as abas `/admin/testes?aba=visao-geral`, `safe`, `rotinas`, `unitarios` e `relatorios`.
- Conferir filtros em `/admin/testes?aba=relatorios&nivel=unit&ambiente=local&status=ok`.

Resultado da validacao local:
- `safe local`: 24 testes OK, incluindo rotas publicas, sitemap, post real, asset principal, login/logout, paginas admin, Central Operacional, Health Check, Testes Automatizados, Observabilidade, SEO Tecnico cacheado e assets admin.
- `unit local`: 26 testes OK, cobrindo contratos puros de nivel, ambiente, CSRF, fragmentos, assinaturas de erro, URLs/assets e payloads de resultado/bloqueio.
- `routine local database_crud`: pendente de validacao final.
- `full local`: bloqueado corretamente como `FULL_BLOCKED`.
- `safe stage`: 20 testes OK, incluindo login/logout, paginas editoriais, assets e ausencia de menus tecnicos proibidos.
- Pagina `/admin/testes` conferida no navegador local com a nova secao de Suite operacional renderizada.

Status:
- Local: validado em safe
- Stage: validado em safe
- Producao: nao aplicavel
