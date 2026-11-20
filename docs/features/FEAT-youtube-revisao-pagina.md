ID: FEAT-youtube-revisao-pagina
Tipo: Correção e melhoria (painel YouTube)
Nome: Revisão da página /admin/youtube: gráfico, contagens, ordem, datas de agendamento e atualização sem recarregar

Objetivo:
A página tinha o gráfico sem cor (as barras usavam `bg-gradient-to-t`, que não existe no Tailwind compilado), as datas do gráfico estourando o quadro, contagens infladas pelos 21 vídeos cancelados da grade antiga, informações repetidas em vários lugares, rolagem lateral na tabela de vídeos, data errada nos agendados (mostrava a hora do envio ao YouTube, não a data marcada) e recarregava a página inteira a cada filtro, período ou sincronização. Tarefa Forge #1834 (02/11/2026).

O que mudou:
- Gráfico: barras de visualizações e de curtidas lado a lado, mesma escala, cores num `<style>` da própria página; uma data a cada N dias (a última sempre aparece), cabe no quadro em qualquer período; subtítulo "Visualizações acumuladas de cada vídeo, no dia da publicação" (não é visualização por dia: isso exige a API de Analytics).
- Contagens: o card "Vídeos e Shorts" conta só vídeos não cancelados e não "unlisted" (`countActiveVideos()`).
- Cada informação aparece num lugar só (mais o gráfico): saíram os números do rodapé do "Canal Conectado" (fica só "Sincronizado em"), o "Total cadastrado localmente" das Ações Rápidas, o selo de contagem da aba e a hora da sincronização da nota do card de visualizações (a nota é só "Canal: N"; o texto completo fica no title).
- Lista: ordem padrão publicados, depois agendados (`agendado` e `agendado_remoto`), depois o resto, cada grupo do mais recente ao mais antigo; a ordenação escolhida prevalece. Cancelados ficam escondidos, com a opção "Cancelado" no filtro Status.
- Datas: agendado mostra "Agendado para dd/mm/aaaa hh:mm" (de `scheduled_at`) na planilha e nos cards; a ordem e a ordenação pela coluna "Publicação" usam essa data. O `published_at` de um agendado no YouTube é a hora do envio, não a da publicação.
- Largura: a página ocupa a janela toda (sem o limite de 1280 px). Na tabela de vídeos a coluna "Título" é limitada (380 px a partir de 1700 px de janela, 240 px abaixo disso), com o texto cortado em "…" (título inteiro ao passar o mouse); o espaço que sobra vai para as outras colunas. A partir de 1700 px, selos de status e data ficam numa linha só; abaixo disso empilham para não rolar (tarefa Forge #1841, corrigindo a primeira versão, que dava ao título todo o espaço e quebrava status e data em 2 linhas). Sem rolagem lateral a partir de ~1366 px de janela; abaixo disso a tabela rola dentro do quadro.
- Sem recarregar: período e datas usam `?ajax=metrics` (KPIs, gráfico, distribuição, totais e dados do canal); filtros, ordenação, paginação, por página e Planilha/Cards usam `?ajax=videos`, que devolve o HTML da parcial `_lista-videos.php` (o mesmo da página); "Sincronizar" envia por fetch (o controller responde JSON quando o pedido aceita `application/json`) e depois recarrega métricas e lista com aviso. O endereço acompanha (`history.replaceState`), incluindo `tab=videos`. Sem JavaScript, links e formulários recarregam a página como antes.

Arquivos alterados:
- app/Repositories/YouTubeVideoRepository.php: ordem padrão por grupo, data do agendado na ordenação, cancelados fora por padrão, `countActiveVideos()`.
- app/Controllers/Admin/YouTubeController.php: ordem padrão vazia, `?ajax=videos`, dados do canal no `?ajax=metrics`, `sync()` em JSON quando pedido.
- app/Views/admin/youtube/index.php: gráfico, cards, remoção das repetições, largura total, filtros e JavaScript.
- app/Views/admin/youtube/_lista-videos.php (novo): planilha/cards e paginação.
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
Baixo. Envio e agendamento não mudaram. Os números continuam dependendo de apertar "Sincronizar".

Pendências fora do escopo:
- Layout do admin em celular: a barra lateral não recolhe (afeta todas as páginas, inclusive Instagram); por isso a página não foi validada em largura de celular.
- O seletor "Ordenar por" não tem as opções Título, Status, Formato e Comentários; ao ordenar por esses cabeçalhos ele mostra a primeira opção.
- Visualizações por dia de verdade exigem permissão de Analytics e reconectar o canal.

Origem validada: local
Validado local: sim (verify-changes.php 0 falhas; navegador em 1920 e 1366 px, as duas abas, período 7/14/30 dias e personalizado, filtro Cancelado, paginação, por página, Planilha/Cards, limpar, ordenar por título e por publicação, Sincronizar; endereço recarregado volta ao mesmo estado; console sem erros)
Validado stage: nao

Apto para release: sim
