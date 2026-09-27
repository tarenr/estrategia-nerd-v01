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