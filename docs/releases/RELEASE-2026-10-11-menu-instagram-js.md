Release: RELEASE-2026-10-11-menu-instagram-js
Data: 2026-10-11
Responsavel: Taren Felipe Ribeiro

Entram:
- FEAT-010 v2.12.1 - Menu x secao do Instagram (Tasks #1196 a #1198): o item "Instagram" do menu passa a continuar marcado ao rolar ate o feed (antes, depois de 1,2 s, o "Blog" ficava marcado)

Ficam fora:
- Qualquer outro arquivo: o envio e de UM arquivo, `assets/js/site-home.js`. Nenhum PHP, view, CSS, `.env` ou banco
- O pacote tecnico completo `code_2026-10-11_20-53-43_dd0d11c` (228 arquivos, tudo desde o commit bed2001): gerado so para inspecao, NAO aplicado em nenhum ambiente

Origem validada: local (navegador desktop e mobile 375 px) -> Stage (navegador)
Paridade local -> stage: validada (hash do arquivo identico, SHA-256 86181542d538...)
Paridade stage -> producao: validada (mesmo SHA-256 86181542d538..., 31780 bytes; antes, producao servia 809cdfe27a49..., 31739 bytes)

Backup necessario: sim (do arquivo)
Backup usado:
- Stage: `storage/menu-instagram-js-deploy/stage/site-home.before.js` (copia da versao anterior, 31739 bytes, hash conferido antes do envio)
- Producao: `storage/menu-instagram-js-deploy/production/site-home.before.js` (idem, 31739 bytes)

Banco precisa rodar script: nao
Mudanca de schema: nao
Mudanca de dados: nao

Rotas criticas afetadas:
- / (Home publica): comportamento do menu fixo ao rolar e ao clicar nos itens

Arquivos principais:
- public/assets/js/site-home.js (1 linha: o mapa `hero` -> `home` ganhou `instagram` -> `instagram-feed`)
- scripts/menu-instagram-js-deploy.php (novo, pontual)
- docs/features/FEAT-010.md

Causa:
- O script procura a secao com `document.getElementById(<chave do item>)`; a chave `instagram` nao existe no HTML (a secao e `id="instagram-feed"`, tambem usado pelo link do menu, pelo rodape e pela rolagem). Sem a secao na lista acompanhada, o calculo por rolagem caia no ultimo item anterior (Blog)

Pacotes aplicados:
- Stage: envio direto de 1 arquivo por `scripts/menu-instagram-js-deploy.php --deploy --target=stage` (so aplica se o remoto for exatamente a versao anterior, por hash; arquivo temporario + rename; hash conferido apos a troca)
- Producao: envio direto de 1 arquivo por `scripts/menu-instagram-js-deploy.php --deploy --target=production`, apos aprovacao do usuario e homologacao em Stage (mesmas travas)

Commits locais:
- dd0d11c fix(site): marcar o item Instagram do menu ao rolar ate o feed (correcao)
- 097128f chore(deploy): script pontual para publicar o site-home.js do menu e registrar a release
- commit do registro da publicacao em producao

Checklist pre-deploy usado:
- /docs/checklists/pre-deploy.md (no que se aplica a envio de um unico arquivo estatico: estado remoto lido antes por hash, backup do arquivo, suite local aprovada)

Checklist pos-deploy usado:
- /docs/checklists/post-deploy.md (home 200, JS servido pela home com o hash esperado, menu testado no navegador)

Rollback associado:
- `C:\xampp\php\php.exe scripts/menu-instagram-js-deploy.php --rollback --target=stage|production` (restaura a copia guardada; mesma trava por hash)

Validacoes realizadas:
- Bug reproduzido no navegador local antes da correcao: com o feed na tela, o menu marcava `blog`
- Local (desktop 1400 px e mobile 375 px): marcacao correta ao rolar e ao clicar em Home, Sobre, Blog, Instagram e Newsletter; a marcacao do Instagram permanece apos a trava de 1,2 s
- `node --check` no JS (OK)
- scripts/verify-changes.php: 22 OK, 0 falhas
- Script de deploy: recusa sem `--target`, recusa `--deploy` sem backup, `--deploy` repetido devolve "ja aplicado" (idempotente)
- Stage: `--verify` aprovado (hash remoto, home 200, JS servido pela home com o hash esperado, secao `#instagram-feed` presente) e teste no navegador (topo -> hero; Sobre; Blog; Instagram -> instagram; Newsletter)
- Producao confirmada INTOCADA apos o envio ao Stage; depois da aprovacao, aplicada
- Producao: `--backup` (hash da versao anterior conferido), `--deploy`, `--verify` aprovado (hash remoto, home 200, JS servido pela home com o hash esperado, secao `#instagram-feed`), `--deploy` repetido devolveu "ja aplicado"
- Producao no navegador: Blog -> blog; feed do Instagram -> instagram; Newsletter -> newsletter; home e /blog em 200; feed com #216 e #246 no topo

Limitacoes e pendencias:
- Rollback da producao: `php scripts/menu-instagram-js-deploy.php --rollback --target=production`
- O painel de testes do navegador nao dispara `requestAnimationFrame` nem eventos de rolagem sem renderizacao; a verificacao da rolagem foi feita intercalando capturas de tela (que forcam a renderizacao). O clique real no item foi validado sem essa limitacao
- Se o Cloudflare guardar o JS antigo em cache, o visitante pode demorar a receber o novo; o endereco do JS tem `?v=` (filemtime), o que renova o cache na troca

Status:
- Implantada com sucesso em Stage e Producao
