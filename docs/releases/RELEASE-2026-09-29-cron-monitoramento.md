Release: RELEASE-2026-09-29-cron-monitoramento
Data: 2026-09-29
Responsavel: Taren Felipe Ribeiro

Entram:
- FEAT-009 - Registro verificavel de execucao do cron de publicacao do blog (log + heartbeat .last.json, exit 1 em falha, inclusive quando o bootstrap encerra por falha de banco)
- Arquivos do FEAT-010 alterados desde o ultimo pacote de producao (ec7f946), que o pacote tecnico inclui por ser incremental. Nao executam em producao: o /admin e bloqueado fora do local (public/index.php) e o Instagram e local-only.

Ficam fora:
- Alerta automatico (NERD // OPS / ntfy) de cron parado ou com falha - fase 2
- Mudancas de schema ou de dados

Analise de pertinencia (o que roda em producao):
- scripts/en-blog-publish-scheduled.php - roda (Cron Job diario da Hostinger); motivo da release
- config/routes.php - carregado em toda requisicao; +1 rota /admin/... bloqueada fora do local
- app/Services/Site/InstagramFeedService.php - roda na home; mudanca so em saveCache(), nao alcancado em producao (sem tabelas instagram_*, cai no loadCache)
- app/Repositories/InstagramPostRepository.php - instanciado na home; metodos alterados nao sao chamados em producao
- app/Controllers/Admin/InstagramController.php, app/Controllers/Admin/PostsController.php, app/Services/Admin/PostsService.php, app/Services/Instagram/BlogCrosspostService.php, app/Services/Instagram/GeminiCaptionService.php, app/Services/Instagram/SmartCanvasRenderer.php, app/Views/components/admin/posts/form.php, app/Views/components/admin/posts/form-instagram-crosspost.php - so via /admin; nao executam
- scripts/en-instagram-publish-scheduled.php - nao agendado em producao; nao executa

Origem validada: estrategia-nerd-stage (paridade stage -> pacote validada por download e comparacao, 13/13)
Paridade local -> stage: validada (13/13 identicos)
Paridade stage -> pacote: validada (hash pacote x local 13/13)

Backup necessario: sim
Backup usado:
- (preencher apos o backup de producao)

Banco precisa rodar script: nao
Mudanca de schema: nao
Mudanca de dados: nao

Rotas criticas verificadas (codigo HTTP igual antes/depois): /, /blog, /post/{slug}, /central-nerd, /admin (bloqueado), asset, upload, e as URLs dos logs em _app_core/storage/logs (403).

Pacotes aplicados:
- Stage: code_2026-09-29_21-28-04_bed2001 (13 arquivos)
- Producao: (preencher)

Commits locais:
- bed2001 feat(cron): registro verificavel de execucao do cron de publicacao do blog (FEAT-009)
- d017ee9, 6bf34a5, 80e9490, f7aeb2f (FEAT-010, arquivos inertes em producao)

Validacoes realizadas:
- Local: verify-changes.php 17/17; php -l e PHPStan nivel 5 no script do cron (0 erros); dry-run, falha de bootstrap simulada (exit 1 + heartbeat ok=false), gravacao negada (aviso em STDERR)
- Stage: 10 URLs com o mesmo codigo HTTP de antes; logs 403; script executado no servidor via Cron Job temporario (30/09 01:15 UTC): teste de falha exit=1 e ok=false, dry-run exit=0 e ok=true, sem avisos

Rollback associado:
- Restaurar o backup de producao desta release SOMENTE no escopo system_files (_app_core). Nao restaurar banco nem uploads.
- Reaplicar pacote anterior nao serve (pacotes sao incrementais).

Status:
- Em andamento: Stage validada; producao pendente
