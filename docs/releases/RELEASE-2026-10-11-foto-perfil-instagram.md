Release: RELEASE-2026-10-11-foto-perfil-instagram
Data: 2026-10-11
Responsavel: Taren Felipe Ribeiro

Entram:
- FEAT-010 v2.12.2 - Foto de perfil do Instagram guardada como arquivo local (Tasks #1203 a #1208): o avatar do card do Instagram na home estava quebrado porque a URL assinada da Meta expira em cerca de 14 dias (a publicada expirou em 02/10/2026)
- Sync de perfil: seguidores, midias e foto atualizados automaticamente (antes so o botao do painel admin fazia isso; o banco estava com 92 midias contra 95 na Meta)
- Exclusao do pacote tecnico nao aplicado `code_2026-10-11_20-53-43_dd0d11c` (228 arquivos), gerado so para inspecao

Ficam fora:
- Qualquer outro arquivo de producao: o envio de codigo e de UM arquivo, a view `app/Views/components/site/home/instagram-feed.php`; o restante e dado (JSON e imagem)
- `InstagramFeedService` e demais PHP do feed: rodam so no PC local (a producao le o JSON), nao foram enviados
- Schema ou dados dos bancos remotos
- Layout do card (nenhuma mudanca visual)

Origem validada: local (navegador) -> Stage (navegador) -> Producao (navegador)
Paridade local -> stage: validada (view com o mesmo hash; JSON e `profile.jpg` identicos; reexecucao do publicador devolve `unchanged`)
Paridade stage -> producao: validada (mesma view, mesma foto e mesmo JSON do Stage)

Backup necessario: sim (do arquivo de codigo)
Backup usado:
- Stage: `storage/feed-avatar-view-deploy/stage/instagram-feed.before.php` (view anterior, hash conferido antes do envio)
- Stage: `config/instagram-feed.json.bak` remoto e `storage/instagram-feed-publish/stage/before-*.json` local (JSON anterior)
- Producao: `storage/feed-avatar-view-deploy/production/instagram-feed.before.php` (view anterior), `config/instagram-feed.json.bak` remoto e `storage/instagram-feed-publish/production/before-*.json` local

Banco precisa rodar script: nao
Mudanca de schema: nao
Mudanca de dados: sim, somente no banco LOCAL (`estrategia-nerd`, tabela `instagram_accounts`): `syncAccount` atualizou seguidores, `media_count` (92 -> 95), foto e `synced_at`. Bancos remotos nao foram tocados.

Rotas criticas afetadas:
- / (Home publica, card de perfil da secao do Instagram), em Stage e Producao

Arquivos principais:
- app/Services/Instagram/InstagramProfileAvatar.php (novo)
- app/Services/Instagram/InstagramFeedPublisher.php (valida, envia e confere a foto)
- app/Services/Site/InstagramFeedService.php (`profile_picture` aponta para o arquivo local)
- app/Views/components/site/home/instagram-feed.php (`url()` no avatar local; unico PHP enviado)
- scripts/sync-instagram-feed-assets.php (passo 2a: perfil e foto)
- scripts/feed-avatar-view-deploy.php (novo, pontual)
- scripts/verify-instagram-feed-sync.php (42 testes isolados) e scripts/verify-changes.php (lint)
- config/instagram-feed.json e public/assets/instagram-feed/profile.jpg (dados publicados)
- docs/features/FEAT-010.md

Causa:
- O feed guardava `profile_picture_url` (URL assinada com validade curta) e o sync automatico nao atualizava o perfil; a URL vencida passou a responder 403 e o navegador mostrava so o texto do `alt`

Pacotes aplicados:
- Stage: view enviada por `scripts/feed-avatar-view-deploy.php --deploy --target=stage` (so aplica se o remoto for exatamente a versao anterior, por hash); JSON e `profile.jpg` enviados por `scripts/en-instagram-publish-feed.php stage`
- Producao: view por `scripts/feed-avatar-view-deploy.php --deploy --target=production` (mesmas travas), depois JSON e `profile.jpg` por `scripts/en-instagram-publish-feed.php production`, apos aprovacao do usuario e homologacao em Stage

Commits locais:
- ver historico da branch main (commits de 2026-10-11 com escopo "instagram")

Checklist pre-deploy usado:
- /docs/checklists/pre-deploy.md (no que se aplica a envio de 1 arquivo de codigo + dados: estado remoto lido por hash antes, backup, suite local aprovada)

Checklist pos-deploy usado:
- /docs/checklists/post-deploy.md (home 200, avatar 200 como imagem, avatar carregado no navegador)

Rollback associado:
- View: `php scripts/feed-avatar-view-deploy.php --rollback --target=stage|production`
- JSON: restaurar `config/instagram-feed.json.bak` no remoto (ou reenviar `before-*.json`)
- Desligar a automacao do feed sem apagar nada: criar `storage/instagram-feed-publish/auto.disabled`

Validacoes realizadas:
- Diagnostico: URL da foto no feed expirada em 2026-10-02 21:30 (HTTP 403); a Meta devolve hoje uma URL valida (HTTP 200, JPEG 1080x1080), que expira em 2026-10-16
- `verify-instagram-feed-sync.php`: 42 testes isolados aprovados (foto ausente, pequena, nao-JPEG e PNG recusadas; caminho fora da pasta recusado; versao `?v=` invalida recusada; fallback para URL http aceito; escrita atomica e preservacao da foto anterior; perfil incompleto nunca grava no banco)
- PHPStan nivel 5: 0 erros; `verify-changes.php`: 23 OK, 0 falhas
- Sync real: perfil atualizado (91 seguidores, 95 midias), foto de 163 KB salva (1080x1080, image/jpeg), feed com `/assets/instagram-feed/profile.jpg?v=3c64b435`
- Local no navegador: avatar carregado (1080x1080), card correto e item "Instagram" marcado no menu
- Script da view: recusa sem `--target`, recusa `--deploy` sem backup
- Stage: view v2.12.2 (`--verify` aprovado); dry-run mostrou 0 capas, foto de perfil e troca do JSON; envio real aprovado com verificacao HTTP (home, capa do primeiro card e foto de perfil); reexecucao devolveu `unchanged`; avatar no navegador com `src` ja prefixado por `/stage` pela view (1080x1080, carregado)
- Producao: view v2.12.2 (`--verify` aprovado, home e /blog em 200); dry-run mostrou 0 capas, foto de perfil e troca do JSON; envio real com verificacao HTTP; reexecucao devolveu `unchanged`; a pagina nao referencia mais a URL da Meta (fbcdn); foto servida pelo proprio dominio (200, image/jpeg, 166991 bytes)
- Producao no navegador: avatar carregado (1080x1080) com `src` em `/assets/instagram-feed/profile.jpg?v=3c64b435`; primeiro card #216; item "Instagram" marcado no menu
- Ciclo automatico do agendador (apos remover o interruptor): execucao normal sem sync nem envio (nada vencido); com o sync forcado a ficar vencido, rodou o sync, o JSON mudou (o #216 foi de 1 para 2 curtidas, igual a Meta) e o publicador enviou a Producao sozinho com verificacao aprovada

Limitacoes e pendencias:
- Ordem usada em producao: view antes do JSON (a view antiga ja serviria o caminho absoluto, mas sem a trava por hash)
- O interruptor `storage/instagram-feed-publish/auto.disabled` ficou LIGADO durante o trabalho (o feed automatico da v2.12.0 ficou pausado e os contadores de curtidas e comentarios na producao ficaram parados no snapshot das 20:35) e foi REMOVIDO apos a publicacao em producao: o feed automatico voltou a rodar
- A URL da foto na Meta e re-baixada a cada 24 h (modo automatico); se a Meta devolver algo que nao seja JPEG, a foto anterior e mantida e o aviso aparece no log do agendador
- O pacote tecnico de 228 arquivos foi excluido: nunca aplicado (`applied_targets` vazio); o ultimo pacote aplicado continua `code_2026-09-29_21-28-04_bed2001`

Status:
- Implantada com sucesso em Stage e Producao
