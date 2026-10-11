Release: RELEASE-2026-10-11-instagram-feed-automatico
Data: 2026-10-11
Responsavel: Taren Felipe Ribeiro

Entram:
- FEAT-010 v2.12.0 - Feed publico do Instagram automatico (Tasks #1191 a #1195): sync com a Meta, reconciliacao de posts apagados e envio por FTP do `config/instagram-feed.json` e das capas para Stage e Producao, sem pacote de deploy
- Correcao do estado publicado: home parada em posts de 12/09/2026; passou a mostrar os Reels de outubro (#216, #246 e #214 no topo)

Ficam fora:
- Codigo que roda em producao (views, `InstagramFeedService`, rotas): nenhum arquivo de codigo foi enviado; so o snapshot JSON e as capas
- Mudanca de schema ou de dados nos bancos remotos
- Token da Meta ou qualquer credencial no servidor
- Task Scheduler, Cloudflare e Apache (sem alteracao)
- Republicacao do Reel #196 (pendente em outro trabalho, tarefa #1086)

Origem validada: local, depois Stage (envio real e verificado), depois Producao
Paridade local -> stage: validada (hash do JSON identico; reexecucao devolve `unchanged`)
Paridade stage -> producao: validada (mesmo hash, mesmas 3 capas novas)

Backup necessario: nao (envio restrito a 1 JSON + 3 capas novas; nenhum arquivo existente alem do JSON foi sobrescrito)
Backup usado: nenhum backup de sistema BS-PROD foi gerado; protecoes especificas desta mudanca:
- Remoto (Stage e Producao): `config/instagram-feed.json.bak` com o JSON anterior
- Local: `storage/instagram-feed-publish/{stage,production}/before-20261011-*.json` (copia do JSON remoto anterior)
- Local: `C:\Users\WINDOWS\ProjectBackup\feed-ig-20261011\` (JSON e 92 capas antes do sync)

Banco precisa rodar script: nao
Mudanca de schema: nao
Mudanca de dados: sim, somente no banco LOCAL (`estrategia-nerd`, tabela `instagram_posts`): posts #209 e #196 passaram de `publicado` a `cancelado` (apagados no Instagram); nota em `error_log`. Bancos remotos nao foram tocados.

Rotas criticas afetadas:
- / (Home publica, secao do feed do Instagram), em Stage e Producao

Arquivos principais:
- app/Services/Instagram/InstagramFeedReconciler.php (novo)
- app/Services/Instagram/InstagramFeedPublisher.php (novo)
- app/Repositories/InstagramPostRepository.php (`markRemovedFromInstagram`)
- scripts/en-instagram-publish-feed.php (novo)
- scripts/en-instagram-publish-scheduled.php (`ig_feed_maintenance`)
- scripts/sync-instagram-feed-assets.php (reconciliacao e `--only-missing`)
- scripts/verify-instagram-feed-sync.php (novo)
- scripts/verify-changes.php (lint dos arquivos novos)
- config/instagram-feed.json e public/assets/instagram-feed/post_{216,246,214}.jpg (dados publicados)
- docs/features/FEAT-010.md

Pacotes aplicados:
- Stage: envio direto pelo publicador (3 capas + JSON de 14233 bytes), sem pacote de codigo
- Producao: envio direto pelo publicador (3 capas + JSON de 14233 bytes), sem pacote de codigo

Commits locais:
- ver historico da branch main (commits de 2026-10-11 com escopo "instagram")

Checklist pre-deploy usado:
- /docs/checklists/pre-deploy.md (no que se aplica a envio restrito de dados: estado remoto lido antes, dry-run em Stage e Producao, evidencia do JSON anterior)

Checklist pos-deploy usado:
- /docs/checklists/post-deploy.md (home em 200, primeiro card e capas conferidos)

Rollback associado:
- Restaurar `config/instagram-feed.json.bak` por cima de `config/instagram-feed.json` no remoto (ou reenviar `before-*.json` local)
- Desativar a automacao sem apagar nada: criar `storage/instagram-feed-publish/auto.disabled`
- Reverter um cancelamento indevido: `UPDATE instagram_posts SET status='publicado' WHERE id=<id>`

Validacoes realizadas:
- php -l em todos os PHP novos/alterados (OK)
- PHPStan nivel 5: 0 erros
- scripts/verify-changes.php: 22 OK, 0 falhas (inclui dashboard nos 3 ambientes, modulo Instagram e home com o feed)
- scripts/verify-instagram-feed-sync.php: 26 testes isolados aprovados (travas da reconciliacao, validacao do feed, FTP indisponivel sem vazar senha, JSON vazio/corrompido/.mp4 recusados antes de abrir FTP, estado pendente)
- Teste de `ftp_rename` sobre arquivo existente em Stage: aceito, arquivos descartaveis removidos
- Sync real: 95 midias na Meta; exatamente #209 e #196 cancelados; feed local com 18 posts e capas validas
- Stage: envio real; home em 200 com #216, #246 e #214 no topo; capas em 200 (image/jpeg); reexecucao devolveu `unchanged`
- Producao: envio real apos Stage aprovado; home em 200 (203 KB); mesmos tres primeiros cards; capas em 200; `/blog` em 200; posts apagados (#209, #196) ausentes
- Agendador real (3 execucoes): sync automatico OK; reenvio automatico de estado pendente OK (`unchanged`); interruptor `auto.disabled` impede o envio e a remocao o reativa

Limitacoes e pendencias:
- A automacao depende do PC ligado e logado (mesmo requisito do agendador de publicacao)
- Envio por FTP sem TLS (mesmo canal dos deploys existentes)
- Cache de CDN pode atrasar a aparicao no site; o publicador reconfirma a cada ciclo
- O primeiro Reel publicado depois desta release e a prova final do fluxo completo (publicacao -> sync -> envio); acompanhar o log `feed-publish-2026-10.log`
- Post #196 segue `cancelado` ate ser republicado (outro trabalho)

Status:
- Implantada com sucesso em Stage e Producao
