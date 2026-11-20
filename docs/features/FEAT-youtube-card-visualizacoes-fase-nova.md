ID: FEAT-youtube-card-visualizacoes-fase-nova
Tipo: Correção (painel YouTube)
Nome: Card de visualizações pela soma dos vídeos da fase nova

Objetivo:
O card "Visualizações totais" mostrava o total do canal devolvido pela API (`channels.statistics.viewCount`), que veio 0 em 02/11/2026 mesmo com o Manifesto em 766 visualizações: o número do canal atualiza com atraso e, ao que parece, não conta os vídeos não listados (não confirmado).

Arquivos alterados:
- app/Repositories/YouTubeVideoRepository.php: `sumPhaseViews()` soma `views_count` dos vídeos que não são não listados.
- app/Controllers/Admin/YouTubeController.php: passa `phase_views` para a view.
- app/Views/admin/youtube/index.php: card "Visualizações (fase nova)" com a soma; o total do canal e a data da sincronização ficam numa linha menor embaixo.
- app/Views/site/local-docs-v2.php: cartão do módulo YouTube.

Banco de dados:
- Mudanca de schema: nao
- Mudanca de dados: nao
- Script necessario: nao

Dependencias:
- nenhuma

Impacto em producao: nenhum (módulo só local)
Afeta rotas criticas: nao

Risco:
Baixo. Os números dependem de apertar "Sincronizar" (não há rotina automática).

Origem validada: local
Validado local: sim (verify-changes.php 0 falhas; card renderizado com 766 e a nota "Total do canal no YouTube: 0 · sincronizado em 02/11 12:16")
Validado stage: nao

Apto para release: sim
