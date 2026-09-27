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
producao, via Cron Job no painel da Hostinger (mesmo mecanismo do script
antigo de FEAT-008), 1x por dia as 09:00 (Brasilia).

## Observacao
`scripts/en-blog-schedule-publish.php` (FEAT-008) continua existindo,
intocado - e especifico pra aquela leva de conteudo e so trata `rascunho`.