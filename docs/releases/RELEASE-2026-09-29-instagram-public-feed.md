Release: RELEASE-2026-09-29-instagram-public-feed
Data: 2026-09-29
Responsavel: Taren Felipe Ribeiro

Entram:
- FEAT-010 - Widget de Feed do Instagram no frontend publico do blog (Task #327 / #341 / #342)
- HOT-004 - Substituicao de icones FontAwesome por SVGs inline nativos e badges legiveis com rotulos explicitos de curtidas e comentarios
- INF-005 - Sincronizacao de cache do feed via config/instagram-feed.json para funcionamento sem dependencia de tabela remota de Instagram em Stage e Producao
- Task #343 - Deploy e homologacao nos ambientes Local, Stage e Producao

Ficam fora:
- Mudancas de schema nos bancos remotos (Instagram permanece com regra arquitetural estrita de ambiente unico/local)
- Cross-posting automatico no formulario de criacao do blog (Task #328 - implementado localmente em 2026-09-29, fora deste release; ver FEAT-010 v2.5.0)
- Agendador periodico via Task Scheduler (Task #329)

Origem validada: estrategia-nerd-stage (validada)
Paridade local -> stage: validada
Paridade stage -> pacote: validada

Backup necessario: sim
Backup usado:
- BS-PROD-20260929-094844 (banco: 416,1 KB | sistema: 705,2 KB)

Banco precisa rodar script: nao
Mudanca de schema: nao
Mudanca de dados: nao

Rotas criticas afetadas:
- / (Home publica)

Arquivos principais:
- app/Services/Site/InstagramFeedService.php
- app/Views/components/site/home/instagram-feed.php
- app/Views/site/home.php
- app/Controllers/Site/HomeController.php
- app/Services/Site/HomeService.php
- config/instagram-feed.json
- scripts/verify-changes.php

Pacotes aplicados:
- Stage: code_2026-09-29_09-42-57_0b621fe (32 arquivos)
- Stage: code_2026-09-29_09-47-24_e6b04f2 (2 arquivos)
- Producao: code_2026-09-29_09-48-33_e6b04f2 (31 arquivos)

Commits locais:
- c3d6ad4 feat(site): adicionar widget de feed do instagram no frontend publico
- 0b621fe fix(site): usar svgs inline e rotulos explicitos de curtidas e comentarios no feed do instagram
- e6b04f2 feat(instagram): mover cache do feed para config/instagram-feed.json para sincronizacao com stage e producao

Checklist pre-deploy usado:
- /docs/checklists/pre-deploy.md

Checklist pos-deploy usado:
- /docs/checklists/post-deploy.md

Rollback associado:
- Backup de sistema e banco: BS-PROD-20260929-094844
- Pacote anterior de producao: code_2026-09-27_15-43-14_450f96b.zip

Validacoes realizadas:
- Local testado e aprovado com 17/17 testes na suite verify-changes.php
- Stage testado e aprovado com feed do Instagram renderizado (82 KB) em https://estrategianerd.com.br/stage
- Producao testada e aprovada com feed do Instagram renderizado (87 KB) em https://estrategianerd.com.br
- php -l nos arquivos PHP alterados (100% OK)
- PHPStan Level 5 (100% OK, 0 erros)
- git diff --check (100% OK)

Status:
- Implantada com sucesso em Producao
