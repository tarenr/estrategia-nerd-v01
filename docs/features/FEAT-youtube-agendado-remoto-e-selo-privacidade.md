ID: FEAT-youtube-agendado-remoto-e-selo-privacidade
Tipo: Correção e melhoria (painel YouTube)
Nome: "Agendado" inclui vídeos já enviados ao YouTube; selo de privacidade na lista

Objetivo:
O Castlevania (enviado ao YouTube como privado com hora para publicar, status `agendado_remoto`) não aparecia como agendado: o contador do painel principal e o filtro "Agendado" só olhavam `status = 'agendado'` (fila local). A lista também não mostrava quais vídeos são não listados.

Arquivos alterados:
- app/Repositories/YouTubeVideoRepository.php: filtro `status=agendado` e contador `scheduled` do `dashboardSummary` passam a incluir `agendado_remoto`.
- app/Views/admin/youtube/index.php: opção "Agendado (fila ou no YouTube)"; selo "Não listado"/"Privado" ao lado do status, na tabela e nos cartões.
- app/Views/site/local-docs-v2.php: cartão "Acervo & Métricas" do módulo YouTube.

Banco de dados:
- Mudanca de schema: nao
- Mudanca de dados: nao
- Script necessario: nao

Dependencias:
- nenhuma

Impacto em producao: nenhum (módulo só local)
Afeta rotas criticas: nao

Risco:
Baixo (contagem, filtro e visual). Limite conhecido: a privacidade só é relida do YouTube ao conectar o canal ou apertar "Sincronizar".

Origem validada: local
Validado local: sim (verify-changes.php 0 falhas; contador do painel = 1; filtro Agendado traz o Castlevania; 32 selos "Não listado" em "Todos", na tabela e nos cartões; 0 na lista padrão)
Validado stage: nao

Apto para release: sim
