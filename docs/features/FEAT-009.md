# FEAT-009 - Agendamento de posts (calendario + auto-publicacao)

## Objetivo
Dar suporte real ao status `agendado` dos posts: uma tela de agendamento no
admin (calendario mensal em grade + lista, com navegacao por mes/ano) e um
mecanismo generico que publica automaticamente qualquer post agendado assim
que a data (`data_publicacao`) chega, em producao.

## Contexto
O status `agendado` ja existia no schema (`posts.status`) e no formulario de
criar/editar post, mas nao tinha efeito real: um post agendado so ficava
escondido do publico (pagina "ainda nao foi publicado"), sem nenhuma
automacao publicando ele na hora certa. O unico script parecido
(`scripts/en-blog-schedule-publish.php`) era ad-hoc, com uma lista fixa de
post_id => data pra uma leva especifica de conteudo (FEAT-008), e so tratava
o status `rascunho`, nunca `agendado`.

## Escopo
- Tela `GET /admin/agendamento-posts`: calendario mensal em grade + lista
  agrupada por data, alternaveis por botao, com navegacao de mes/ano
  compartilhada entre os dois modos. Abre no mes atual. Destaca visualmente
  qualquer "agendado" ja vencido. Respeita o ambiente alvo (local/producao)
  ja usado no resto do admin.
- Botao de selecionar data (`showPicker()`) no formulario de criar/editar
  post, ao lado do campo `data_publicacao` existente. Sem biblioteca nova.
- Script generico `scripts/en-blog-publish-scheduled.php`: localiza todo
  post com `status='agendado' AND data_publicacao <= NOW()` e publica.
  Idempotente, sem lista hardcoded.

## Arquivos alterados
- `app/Repositories/PostRepository.php` (metodos `findDueScheduled`,
  `markPublished`, `listForSchedule`)
- `app/Services/Admin/PostsService.php` (`getScheduleViewModel`, `monthLabel`)
- `app/Controllers/Admin/PostsController.php` (`schedule`)
- `app/Views/admin/posts/agendamento.php` (nova)
- `app/Views/components/admin/sidebar.php`
- `app/Views/components/admin/posts/form-main-fields.php`
- `config/routes.php`
- `scripts/en-blog-publish-scheduled.php` (novo)

## Automacao
`scripts/en-blog-publish-scheduled.php` roda direto no servidor de
producao, via Cron Job no painel da Hostinger (conta u576397693),
1x por dia as 12:00 UTC (09:00 Brasilia) - comando:
`php /home/u576397693/domains/estrategianerd.com.br/public_html/_app_core/scripts/en-blog-publish-scheduled.php`

## Monitoramento do cron (29/09/2026)
Motivo: em 29/09/2026 o cron publicou o post do dia, mas nao deixou rastro no servidor
(saida do hPanel e `_app_core/storage/logs/` vazias). O script gravava o log com erro
silenciado e, quando o banco falhava, o `bootstrap.php` encerrava o processo com `exit`
sem codigo de erro - o cron terminava como "sucesso" sem registro nenhum.

Agora toda execucao deixa rastro em `storage/logs/`:

- `cron-publish-scheduled.log`: uma linha por execucao (JSON do resultado ou `ERRO: ...`).
- `cron-publish-scheduled.last.json` (heartbeat, sobrescrito a cada execucao, gravado de
  forma atomica): `iniciado_em`, `finalizado_em`, `ambiente`, `dry_run`, `ok`,
  `publicados` (ids publicados nesta execucao, preservados mesmo se um post seguinte
  falhar), `seriam_publicados` (so em `--dry-run`) e `erro`.
- Um `register_shutdown_function` registrado antes do `bootstrap.php` grava `ok=false`
  e forca **exit code 1** quando o processo e encerrado antes de concluir (falha de banco
  no bootstrap ou erro fatal). Excecoes no fluxo tambem resultam em `ok=false` e exit 1.
- Se nao conseguir gravar o log ou o heartbeat, o script avisa em STDERR com o caminho.

Como conferir: abrir o `.last.json` - `ok=true` e `finalizado_em` de hoje apos as 09:00
indicam execucao bem-sucedida. Recomendado tambem redirecionar a saida do Cron Job da
Hostinger para um arquivo fora do `public_html` (ex.: `>> .../cron-logs/blog-publish.out 2>&1`),
para registrar inclusive falhas antes do PHP iniciar.

Status: em producao desde 29/09/2026 22:18 (BRT) - release `RELEASE-2026-09-29-cron-monitoramento`,
pacote `code_2026-09-29_21-28-04_bed2001`, backup `BS-PROD-20260929-221637`. Stage testada no servidor
(falha forcada: exit 1 e ok=false; dry-run: exit 0 e ok=true).

Cron Job da Hostinger (desde 30/09/2026): `php /home/u576397693/domains/estrategianerd.com.br/public_html/_app_core/scripts/en-blog-publish-scheduled.php >> /home/u576397693/blog-publish.out 2>&1` ("0 12 * * *"). A saida vai para `/home/u576397693/blog-publish.out` (fora do public_html; a pasta da conta ja e gravavel pelo cron, sem precisar de pasta nova).

Validado em 30/09/2026 09:00:02 (BRT): .last.json ok=true e linha no .log. O `blog-publish.out` e criado mas fica vazio (o STDOUT do PHP nao chega ao arquivo nesse ambiente); ele serve para erros antes do PHP iniciar.

Como conferir sem SSH (hospedagem Single nao tem): por FTP, ler `blog-publish.out` e `_app_core/storage/logs/cron-publish-scheduled.last.json`. Para rodar algo no servidor so existe o Cron Job (criar temporario com pelo menos ~10 min de antecedencia; com menos, pode nao disparar).

Observacao: o script antigo ja registrava as execucoes normais em `cron-publish-scheduled.log` (28/09 e 29/09 conferidos por FTP); a lacuna real era a falha de banco no bootstrap, que terminava com exit 0 e sem registro.

## Deploy em Producao (27/09/2026)
- **Diretriz de Arquitetura (Fase 1b / Tarefa 129):** o admin do site e 100% local.
  Portanto, apenas o core funcional necessario para a automacao remota foi publicado
  em producao (pacote `code_2026-09-27_15-43-14_450f96b`, contendo estritamente
  `app/Repositories/PostRepository.php` e `scripts/en-blog-publish-scheduled.php`).
- Backup preventivo previo realizado: `BS-PROD-20260927-154234`.
- Telas administrativas (calendario, form, controllers e rotas) permanecem rodando
  exclusivamente no ambiente local.

## Migracao de Posts (27/09/2026)
Os 8 posts da leva FEAT-008 (posts 33 a 40 em producao / 65 a 72 no local) foram
migrados de `rascunho` para `agendado` com horario fixado as 09:00:00 (Brasilia):
- Post #33 (local #65): 2026-09-29 09:00:00 - `como-escolher-fonte-para-pc`
- Post #34 (local #66): 2026-10-02 09:00:00 - `como-verificar-saude-ssd-hd`
- Post #35 (local #67): 2026-10-06 09:00:00 - `temperatura-cpu-gpu-quando-se-preocupar`
- Post #36 (local #68): 2026-10-09 09:00:00 - `12-jogos-leves-pc-fraco-ou-modesto`
- Post #37 (local #69): 2026-10-13 09:00:00 - `historia-counter-strike`
- Post #38 (local #70): 2026-10-16 09:00:00 - `10-filmes-nerds-essenciais`
- Post #39 (local #71): 2026-10-20 09:00:00 - `animes-essenciais-para-iniciantes`
- Post #40 (local #72): 2026-10-23 09:00:00 - `desenhos-anos-90-2000-que-envelheceram-bem`

No local, os posts #63 e #64 foram marcados como `publicado` com as datas reais
de publicacao dos posts #31 e #32 de producao (22/09 e 25/09), garantindo paridade
completa de visualizacao no calendario `/admin/agendamento-posts`.

## Observacao
`scripts/en-blog-schedule-publish.php` (FEAT-008) foi descontinuado em favor do novo
mecanismo generico `scripts/en-blog-publish-scheduled.php`.

Em 29/09/2026 a tarefa local do Windows `EstrategiaNerd-PublicarPostsAgendados` (que
rodava o script descontinuado e ja estava desativada) foi removida. Backup do XML em
`C:\Users\WINDOWS\ProjectBackup\scheduled-tasks\EstrategiaNerd-PublicarPostsAgendados-20260929-183416.xml`
(restaurar com `Register-ScheduledTask -Xml (Get-Content <arquivo> -Raw) -TaskName EstrategiaNerd-PublicarPostsAgendados`).
A publicacao do blog continua pelo Cron Job da Hostinger descrito em "Automacao".