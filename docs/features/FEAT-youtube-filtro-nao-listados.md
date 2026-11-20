ID: FEAT-youtube-filtro-nao-listados
Tipo: Melhoria (painel YouTube)
Nome: Separar os vídeos não listados (fase antiga) da fase nova no /admin/youtube

Objetivo:
Os 32 vídeos antigos do canal ficaram não listados em 31/10/2026 (marco zero da fase nova). A sincronização traz todos para `youtube_videos`, e o painel os misturava com os vídeos da fase nova.

Diagnóstico (02/11/2026, só leitura):
- Banco local: 32 vídeos `unlisted` (origem youtube, publicados entre 12/2024 e 07/2025) e 24 `public` (origem local, fila da fase nova). A privacidade já estava sincronizada.
- O canal não tem nenhuma playlist: no YouTube público os não listados não aparecem em lugar nenhum.

Arquivos alterados:
- app/Repositories/YouTubeVideoRepository.php: filtro `privacidade` em `listVideosPaged` (padrão: sem não listados; `unlisted`: só eles; `todos`); `getContentTypeDistribution`, `getDailyPerformance`, `getKpiHistoryAndComparisons`, `dashboardSummary` e `listAll` ignoram os não listados.
- app/Controllers/Admin/YouTubeController.php: lê o parâmetro `privacidade`.
- app/Views/admin/youtube/index.php: seletor "Privacidade" nos filtros (a busca passou de 4 para 2 colunas) e o parâmetro preservado nos links.
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
Baixo. As contagens do painel caem porque os antigos saem delas (efeito desejado). O vídeo da Vida de TI, se for não listado, também sai.

Origem validada: local
Validado local: sim (verify-changes.php: 33 OK, 0 falhas; /admin/youtube renderizado com os três valores do filtro: 24, 32 e 56 itens)
Validado stage: nao

Apto para release: sim

Observacoes:
Forge: "[YouTube] - Separar vídeos não listados da fase nova".
