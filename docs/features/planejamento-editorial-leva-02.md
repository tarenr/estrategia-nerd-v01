## Avatar de referência — revisão 3 (10/10/2026)

Os Reels 196, 229, 232 e 234 receberam 16 cenas geradas por ImageGen a partir de `1000000217.jpg`. Cabelo, óculos, barba, roupa e olhos castanhos seguem a referência; os coadjuvantes do 232 permanecem personagens distintos. Cada sequência conserva o roteiro, a legenda, os tempos e a trilha anteriores. Os outros 33 itens foram comparados integralmente ao catálogo anterior e preservados.

As artes estão em `public/uploads/reels/correcao-instagram-20261010/images/reel-{id}-avatar-v3.png`; vídeos e folhas de conferência usam `reel-{id}-v3`. Prompts e referência estão em `avatarRevision` no catálogo `resources/reels-review/corrections-20261010.json`. O comando `node scripts/reels-review/corrections.mjs --avatar-revision` restringe a alteração aos quatro IDs e preserva as versões anteriores. Backup do catálogo, galeria e documentação: `storage/correcao-instagram-20261010/avatar-before/`.

A galeria existente oferece o filtro **Com seu avatar (4)** e o acesso `index.html?avatar=1`. Não houve alteração de banco, fila, agendamentos ou cancelamentos, nem publicação no Instagram. Validação: 37 roteiros aprovados, preservação dos outros 33 e do áudio dos quatro confirmada; suíte oficial PHP com 17 OK e zero falhas. As quatro folhas de cenas foram inspecionadas visualmente. A inspeção externa depende do acesso Cloudflare; a validação de viewport móvel permanece limitada pelo navegador disponível.

## Correção aprovada em 10/10/2026 — estado vigente

Esta seção substitui as declarações de conclusão integral e de formato uniforme dos relatórios anteriores. A implementação está preparada para inspeção; publicação e aplicação das novas versões à fila ainda estão pendentes da avaliação visual combinada.

Foram auditados 41 registros. Os cancelados 191–194 permanecem cancelados, sem reativação. Os 37 restantes têm ficha individual com legenda, origem, cenas, imagens, enquadramento e trilha em `resources/reels-review/corrections-20261010.json`. São 25 novas renderizações e 12 vídeos reaproveitados. História, comparação, orientação prática, análise de produto, recomendações, explicação sonora e humor têm sequências próprias, com três, quatro ou cinco cenas, conforme a pauta.

A causa do problema era transformar resumos em quatro cenas iguais, usar uma imagem genérica e tratar renderização técnica como revisão editorial. O renderizador agora exige roteiro individual; não fabrica cenas a partir do resumo. Imagens consecutivas iguais permanecem contínuas, sem aparentar uma troca a cada texto. A identidade visual é comum, mas a estrutura editorial é específica.

O post 196, publicado ao meio-dia, tem uma nova prévia com quatro imagens narrativas geradas por ImageGen e legenda revisada. Seu cadastro publicado foi preservado. Não foi republicado e não foi criada uma duplicata com vínculo ambíguo. O publicado 214 também foi preservado. O rascunho 1 tem Reel preparado, mas seu cadastro continua rascunho no formato original até a inspeção. As 34 versões agendadas permanecem na fila original; as legendas e vídeos novos estão na galeria, aguardando liberação.

Cinco referências de mídia (205, 216–219) foram sincronizadas ao cache já vigente pelo repositório oficial, sem mudar o vídeo efetivo ou as datas. O painel prioriza o mesmo cache válido utilizado na publicação. O artigo 17 em produção teve somente o título corrigido para “A Lore Completa de Diablo: Entenda Toda a História do Universo”; corpo, categoria, slug e SEO foram preservados.

Os conteúdos 229, 232 e 234 usam artes de humor geradas por ImageGen. O 230 compara Silent Hill 2, Signalis e Alan Wake 2. O 231 usa exemplos sonoros ilustrativos de antecipação, silêncio e direção, identificados como demonstração, não áudio original de jogos. O 233 mostra separadamente Balatro, Slay the Spire, Vampire Survivors, Celeste e Hades. Não foram produzidas artes por CSS: CSS serve apenas à interface da galeria; o compositor posiciona imagens raster e textos no vídeo.

### Validação e acesso

- `scripts/verify-changes.php`: 17 OK, zero falhas, incluindo PHPStan e renderização local, produção e stage.
- `scripts/verify-konva-integration.php`: 26 OK, zero falhas; rejeita geração sem roteiro individual e duração inválida.
- `scripts/reels-review/verify-corrections.mjs`: 37 roteiros aprovados nas verificações de cenas, tempos, recortes e continuidade.
- `scripts/reels-review/corrections.mjs --verify`: 37 vídeos decodificados integralmente, H.264/AAC, 1080×1920, áudio presente, hashes conferidos e originais preservados. Não equivale a escuta estética integral.
- `correct-approved.php --verify-state`: 41 registros preservados, cinco mídias alinhadas e corpo do artigo 17 preservado. O horário de atualização dos cinco registros sincronizados muda normalmente.
- Galeria: `public/uploads/previews/leva02-bloco1-v3/index.html`, com 37 players, filtros, roteiro e legenda. O acesso local foi verificado; a URL externa exige autenticação Cloudflare e não teve seu conteúdo externo confirmado.
- Interface: busca por 196 retornou um resultado; filtro de humor retornou 229 e 234; Reel 196 reproduziu com duração de 32 s, 1080×1920 e sem erro. Não houve overflow horizontal no viewport efetivo de 1280 px. A tentativa de override móvel não alterou o viewport; validação móvel desta versão permanece pendente. A documentação foi aberta na rota oficial `/local/mudancas/documento?grupo=features&arquivo=revisao-editorial-reels.md`.
- Backup: `storage/correcao-instagram-20261010/`, com JSON dos dados e 46 arquivos de mídia conferidos por SHA-256. As versões v1 foram preservadas após os ajustes de recorte v2.

### Operação e recuperação

Use os scripts CLI versionados; não execute atualizações genéricas por intervalo de ID. O backup é obrigatório antes de escrever. A sincronização de cinco mídias usa transação, verifica estado/arquivo e é idempotente quanto ao caminho. A correção do título 17 verifica concorrência e preserva o conteúdo. Não há alteração de schema nem nova dependência.

Para recuperação, compare os registros com `before.json`, confirme que não houve edição/publicação posterior e restaure apenas os campos afetados em transação. Os arquivos originais não foram sobrescritos. Não restaure o lote inteiro por cima de alterações posteriores. Rollback de código deve usar o histórico Git sem apagar trabalho não relacionado.

O catálogo mantém `publicationApproval=false`. A regeneração vinculada exige roteiro, origem de produção, slug, hash do corpo e trilha correspondentes. Após a inspeção, a aplicação à fila e a republicação de 196 deverão reconciliar cadastro, mídia e cache; a aprovação da execução técnica não foi tratada como aprovação de publicação.

### Fontes e artes

Imagens oficiais da MSI B650 e Kingston NV3 seguem `resources/reels-review/assets/sources.json`. A screenshot de Slay the Spire vem da página oficial Steam (app 646570). Artes ImageGen foram salvas em `public/uploads/reels/correcao-instagram-20261010/images/`: `reel-196-img-001.png`, `reel-229-img-001.png`, `reel-232-img-001.png` e `reel-234-img-001.png`. A direção de cada conjunto está registrada em `assetProvenance` no catálogo; cada painel é enquadrado como uma cena distinta.

A data de suporte do Windows 11 24H2 Home/Pro foi conferida na [tabela oficial de versões da Microsoft](https://learn.microsoft.com/en-us/windows/release-health/windows11-release-information). As condições do upgrade de The Witcher 3 foram conferidas no [anúncio oficial](https://www.thewitcher.com/no/en/news/52041/see-whats-new-in-the-witcher-3-wild-hunt-remastered).

Mídias e backups seguem a política existente de arquivos ignorados. Commit/push de código e catálogo não substituem distribuição dos arquivos de vídeo ao servidor. A inspeção visual e musical pelo usuário, a liberação da fila e a republicação permanecem pendentes.

---

## Registro histórico anterior

# Planejamento editorial — Leva 02: A vida de quem é nerd

Registro: 06/10/2026. Projeto: Estratégia Nerd / @estrategia_nerd.

## Estado e objetivo

Bloco 1 revisado no ambiente local em 10/10/2026: artigos 77–80 e Instagram
229–234. Datas e estados existentes foram preservados. Publicação e sincronização
com stage/produção continuam fora desta correção. O Bloco 2 permanece pendente.

Objetivo: atrair público novo e criar motivos para acompanhar a marca,
conectando games, cultura geek e tecnologia a experiências do cotidiano.
As pautas e datas abaixo são propostas, não publicações confirmadas.

## Contexto e referências locais

- `docs/features/FEAT-005.md`: primeira leva de 11 artigos.
- `docs/features/FEAT-008.md`: calendário anterior, previsto até 23/10/2026,
  com dois artigos por semana, às terças e sextas.
- `conteudo-planejado/instagram-lote-01/`: 11 conteúdos preparados anteriormente.
- `.claude/skills/post-instagram/estilo-estrategia-nerd.md`: voz da marca.
- `docs/templates/planejamento-posts.md`: referência de fluxo editorial.

Os documentos anteriores não confirmam o estado atual de todas as publicações.
Conferir as filas antes de fechar este calendário, evitando duplicidades.

## Público e posicionamento

Hipótese inicial: pessoas que gostam de games e cultura geek, conciliam
esses interesses com a rotina e querem aproveitar melhor seu tempo e setup.
Não há faixa etária definida nem dados de desempenho levantados nesta etapa.

Voz: português brasileiro informal, direto, bem-humorado e próximo; nostalgia
com histórias concretas, referências que ajudam o assunto e informação verificada.
Não inventar depoimentos, experiências pessoais, disponibilidade ou urgência.

## Quadros e linhas editoriais

| Linha | Papel | Abordagem |
|---|---|---|
| Perrengue Nerd | Identificação e humor | Rotina gamer, amigos, backlog e vontade de upgrade |
| Memória Desbloqueada | Nostalgia e participação | Caixas, manuais e jogos nunca terminados |
| Descobertas | Apresentar experiências | Curadoria com critérios e motivo para conhecer |
| Utilidade | Resolver dúvidas | Diagnósticos e explicações acessíveis |

Testar somente os dois primeiros como quadros recorrentes nesta leva.
Instagram deve funcionar por si; blog aprofunda temas relacionados.
Cada peça terá uma ação principal, sem acumular pedidos de interação.

## Período e cadência propostos

26/10 a 22/11/2026, em dois blocos de produção de duas semanas.

- Blog: terça e sexta, oito artigos.
- Instagram: segunda, quarta e sábado, formato exclusivamente Reel para conteúdos relacionados aos artigos. A distribuição original de carrosséis foi substituída no Bloco 1 aprovado.
- Stories: três conjuntos curtos por semana, 12 no total.
- Horários: definir após conferir insights e agenda existente.
- Reels: testar 20–40 segundos, ajustando ao conteúdo.
- Bloco 1: seis Reels com música; os antigos carrosséis 230 e 233 ficam preservados como versões anteriores.
- Artigos: extensão suficiente para cumprir a proposta, sem meta artificial.

## Calendário do Instagram

| Data | Formato / linha | Pauta e abordagem | Ação principal |
|---|---|---|---|
| 26/10 | Reel / Perrengue Nerd | Você escolhe um jogo de terror. À noite, até o corredor vira fase. Encenação cotidiana. | Qual jogo te deixou assim? |
| 28/10 | Reel / Descobertas | Qual tipo de terror combina com você? Tensão, atmosfera, sobrevivência e sustos; exemplos pesquisados. | Salvar para escolher |
| 31/10 | Reel / Nostalgia | O som que fazia você parar de andar no jogo. Uma lembrança e o papel do áudio na expectativa. | Qual som você reconhece na hora? |
| 02/11 | Reel / Perrengue Nerd | O grupo tem cinco pessoas e nenhuma consegue jogar no mesmo horário. Conversa encenada com personagens fictícios. | Enviar ao grupo |
| 04/11 | Reel / Utilidade | Tenho 30 minutos: o que dá para jogar? Duração da sessão e facilidade de interromper. | Salvar para uma noite corrida |
| 07/11 | Reel / Perrengue Nerd | Só mais uma partida: a frase que nunca vem sozinha. Negociação com o próprio horário. | Qual jogo causa isso? |
| 09/11 | Reel / Utilidade | O contador mostra 120 FPS, mas o jogo engasga. Média de FPS e regularidade dos quadros. | Em qual jogo acontece? |
| 11/11 | Carrossel / Utilidade | Antes de comprar uma peça, confira estas coisas. Uso, configurações, memória, armazenamento e temperaturas. | Salvar o checklist |
| 14/11 | Reel / Perrengue Nerd | Você abriu a loja para olhar. Saiu planejando outro setup. Humor sobre upgrade. | Qual peça está na lista? |
| 16/11 | Reel / Memória Desbloqueada | Antes do download, tinha caixa, manual e mapa dobrado. Objetos próprios ou material autorizado. | Qual caixa você guardaria? |
| 18/11 | Carrossel / Cultura | Remake, remaster ou reboot? Diferenças com exemplos verificados. | Qual jogo merece voltar? |
| 21/11 | Reel / Memória Desbloqueada | O jogo que ficou esperando você voltar. História sobre um jogo nunca terminado. | Relatar uma experiência |

## Calendário do blog

| Data | Título provisório | Escopo |
|---|---|---|
| 27/10 | Terror sem depender de jumpscare: experiências para conhecer | Seleção por tipo de medo, critérios claros e avisos de conteúdo |
| 30/10 | Como o som cria medo nos jogos | Silêncio, pistas sonoras e antecipação, com exemplos |
| 03/11 | Jogos para quem só tem 30 minutos por dia | Sessões curtas, pausa e facilidade de retomar; distinto da lista de jogos leves |
| 06/11 | Como voltar a um jogo depois de meses sem jogar | Reaprender comandos, recuperar contexto, continuar ou recomeçar |
| 10/11 | FPS alto e jogo travando: o que investigar | Diagnóstico progressivo, sem promessas universais |
| 13/11 | Como identificar seu próximo upgrade sem comprar por impulso | Uso e limitações observadas; complementar artigos anteriores de hardware |
| 17/11 | Como começar uma coleção de jogos físicos | Objetivo, orçamento, conservação, compatibilidade e cuidados na compra |
| 20/11 | Remake, remaster e reboot: diferenças e exemplos | Explicação acessível, considerando nuances de uso dos termos |

Pesquisar obras, informações técnicas e dados de compra antes da redação.
Criar links entre artigos relacionados e conteúdos anteriores pertinentes.

## Stories e participação

Três momentos semanais: pergunta/enquete antes da pauta; trecho ou bastidor
no dia da publicação, com link quando pertinente; respostas ou continuidade.
Exemplos: jogar terror com fone, tempo disponível à noite e jogos não terminados.

Relatos podem inspirar novas pautas. Solicitar autorização antes de reproduzir
mensagens com nome, foto ou outra identificação. Não apresentar histórias
inventadas como relatos reais.

## Fluxo de produção futuro

1. Conferir filas atuais, publicações e métricas disponíveis em leitura.
2. Pesquisar fontes, exemplos e condições de utilização das mídias.
3. Detalhar briefing por peça: público, ideia, abertura, formato e ação principal.
4. Redigir artigos com profundidade adequada (referência: cerca de 1.000 palavras), roteiros, cards e legendas seguindo `post-blog` e `post-instagram`. Escolher a estrutura pela intenção: curadoria, explicativo, recomendações ou guia. Tabelas, FAQ e vídeos são opcionais e precisam ajudar o assunto; não repetir o mesmo molde nos quatro artigos. Embutir somente vídeos com origem e funcionamento confirmados.
5. Garantir atribuição obrigatória de categoria (`categoria_id` e `categoria_post_id` nunca nulos) em cada artigo.
6. Produzir mídias: capas e imagens em `uploads/posts/{slug}/images/` com `capa.webp` e `img-001.webp` em diante, WebP 1200×675. Conferir visualmente a obra retratada antes de escrever alt e legenda. Renderizar Reels pelo engine Konva/Skia (`scripts/reels-konva/`), com `-movflags +faststart`, posters JPG e trilhas distintas no lote. Registrar as mídias na tabela oficial, além de gravar os arquivos. Quando uma imagem permanece entre cenas, usar um plano contínuo sem movimento de troca simulado.
7. Revisar fatos, português, tom, legibilidade, áudio, imagens, links e SEO.
8. Apresentar o conteúdo completo ao responsável para revisão editorial.
9. Preparar rascunhos e publicação somente dentro de um plano de execução aprovado, respeitando o fluxo vigente do projeto e a aprovação de produção.

Bloco 1: semanas de 26/10 e 02/11. Bloco 2: semanas de 09/11 e 16/11.
Não executar comandos de importação ou publicação a partir deste documento
sem definir e aprovar o escopo operacional correspondente.

## Métricas e revisões

Registrar um período anterior comparável antes de estabelecer metas numéricas.

| Objetivo | Evidência, conforme disponibilidade |
|---|---|
| Descoberta | Alcance entre não seguidores e visitas ao perfil |
| Acompanhamento | Novos seguidores no período e por publicação, quando disponível |
| Identificação | Compartilhamentos e qualidade dos comentários |
| Utilidade | Salvamentos |
| Interesse nos Reels | Tempo médio assistido |
| Leitura | Visitas aos artigos e origem do tráfego |

Referência sobre tempo assistido: [Meta — Reels insights](https://about.fb.com/news/2023/04/instagram-reels-trending-audio-and-gifts-updates/).

- Revisão intermediária proposta: 09/11. Comparar peças com tempo semelhante
  no ar; ajustar abertura, duração e abordagem do segundo bloco sem ampliar o escopo.
- Revisão final proposta: 23/11. Avaliar continuidade dos quadros e novas perguntas.
- Um resultado isolado não define toda a estratégia. Não prometer crescimento.

## Checklist e acompanhamento

- [x] Documentar proposta editorial e calendário.
- [x] Conferir filas, duplicidades e métricas de referência.
- [x] Validar pautas e datas finais com o responsável.
- [x] Aprovar plano operacional de produção.
- [x] Atualizar a fonte oficial da Central de Conhecimento com regras de estrutura editorial, nomenclatura, categorias e validação de mídias.
- [x] Produzir e revisar bloco 1 (artigos IDs 77–80 e Instagram IDs 229–234).
- [ ] Produzir e revisar bloco 2.
- [ ] Autorizar preparação e publicação conforme o fluxo vigente.
- [ ] Realizar revisões intermediária e final.

Tarefa solicitada no Forge: `[Leva 02] - Produzir e acompanhar a leva editorial
A vida de quem é nerd — documentação: docs/features/planejamento-editorial-leva-02.md`.
A tarefa permanece pendente enquanto a leva não for executada e avaliada.

## Limites desta entrega

Produção do Bloco 1 com documentação atualizada e galeria de prévia local/túnel. Publicação final em produção exige aprovação do fluxo de sync.

## Correção verificada do Bloco 1 — 10/10/2026

| Artigo | Estrutura editorial | Tipo do cadastro | Categoria |
|---|---|---|---|
| 77 | Seleção comentada: Silent Hill 2, Signalis e Alan Wake 2 | lista | Games (3) |
| 78 | Explicação de pistas, silêncio, graves e áudio espacial | guia | Games (3) |
| 79 | Recomendações por meta e ponto de parada | lista | Dicas (5) |
| 80 | Passos para retomar uma campanha e preservar o save | guia | Dicas (5) |

O cadastro não oferece o tipo “explicativo”; o artigo 78 usa `guia`, com estrutura
explicativa no corpo. Os quatro textos não são reviews e não recebem FAQ/tabela
por obrigação. O resumo, o SEO e o conteúdo usam a mesma pauta.

Erros encontrados: imagem de Resident Evil 4 identificada como Alan Wake 2,
imagens de jogos trocadas no artigo 79, taxonomia textual `gadgets`, tipo vazio,
afirmações sem sustentação, HTML de outro sistema de estilos, vínculo do squad
com “Os Nefalem”, chamada do Reel “Só mais uma” para um guia diferente e mídias
ausentes no cadastro do Instagram. Os Reels antigos tinham sido marcados como
prontos sem nova renderização. As prévias mantinham textos de versões anteriores.

Correção: captura oficial de Alan Wake 2, mapeamento visual coerente, fontes
primárias nos artigos, categorias e tipos preenchidos, seis legendas com uma
ação principal e quatro a seis hashtags. Instagram 232 e 234 são humor autônomo,
com `post_blog_id = NULL`; cenas fictícias são identificadas. Os vídeos têm
24–30 segundos, 1080×1920, H.264/AAC e quatro cenas com progressão de texto.
As artes dos dois Reels de humor permanecem estáticas enquanto a história avança.

Mídias finais: seis Reels (229–234), cada um com um vídeo cadastrado e música incorporada. 229/231 usam `reel-{id}-revisado.mp4`; 230/232/233/234 usam `reel-{id}-final.mp4`, todos com poster JPG e manifesto. 230 dura 28 segundos; 233 dura 30; os demais duram 24. As seis trilhas são distintas no lote.

As ilustrações de humor 232/234 foram criadas pelo ImageGen integrado, salvas como `squad-imagegen.png` e `relogio-imagegen.png` em `uploads/reels/leva02-bloco1/`. São cenas fictícias, sem simular capturas de gameplay. O canvas oficial organiza textos e compõe o vídeo; não desenha essas ilustrações. Não usar movimento de troca quando a imagem permanece a mesma. Os cards antigos não são removidos dos arquivos, mas deixam de ser a mídia ativa de 230/233. Todos os seis registros são `reels` e ficam `ready` somente após validar os vídeos.

As galerias `leva02-bloco1`, `leva02-bloco1-v2` e `leva02-bloco1-v3` são geradas
do mesmo payload que alimenta o banco. Sua revisão deve conferir o arquivo
apontado pelo registro, não uma cópia antiga com aparência semelhante.

Operação local: `scripts/execute-full-plan-corrections.php`, etapas `--backup-reels`, `--prepare`,
`--render`, `--apply`, `--gallery` e `--verify`. Dados editoriais em
`scripts/restructure-posts.php`, sem efeito colateral ao incluir o arquivo.
Não executar sem o backup indicado e o escopo autorizado. O script não importa
conteúdo em outro ambiente nem chama a publicação do Instagram.

Backup anterior à conversão: `storage/correcao-leva02-reels-20261010/`, incluindo os 14 vínculos anteriores.

Backup original: `storage/correcao-leva02-20261010-codex/`, com JSON de `posts`,
`instagram_posts` e `instagram_post_media`, cópia de 183 arquivos e manifesto
SHA-256. Renderizações e manifestos novos preservam os arquivos anteriores.
Procedimento de recuperação e evidências em
`docs/correcoes/2026-10-10-correcao-bloco-1.md`.

Esta página é a própria fonte exibida pela Central de Conhecimento em
`/admin/base-tecnica?aba=mudancas&grupo=features&arquivo=planejamento-editorial-leva-02.md`.
Não há uma segunda cópia desta documentação em tabela do banco.
