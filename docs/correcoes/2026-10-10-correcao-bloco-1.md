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

# Correção da Leva 02 — Bloco 1

Data: 10/10/2026. Escopo local: artigos 77–80 e Instagram 229–234. Sem publicação no Instagram ou sincronização do blog em produção.

## Onde a execução anterior errou

A imagem chamada Alan Wake 2 era uma cópia de Resident Evil 4. O artigo 79 também tinha jogos trocados nas imagens. Categorias textuais e tipos não estavam coerentes com o cadastro. Os textos repetiam blocos de outro sistema visual e faziam afirmações sem fonte. Legendas e vínculos misturavam pautas, incluindo o squad vinculado a Os Nefalem. Arquivos de mídia não estavam cadastrados na tabela oficial. Marcar vídeos antigos como prontos não comprovava uma nova renderização. As galerias divergiam do conteúdo atualizado.

A primeira correção ainda usou artes programáticas inadequadas nos Reels de humor e manteve 230/233 como carrosséis, contrariando a orientação de usar Reels com música para conteúdos referentes aos artigos. Esta revisão substitui as artes por ImageGen e converte os dois formatos.

## Implementação

Artigos: 77 seleção comentada (lista/Games), 78 explicativo (guia/Games), 79 recomendações (lista/Dicas), 80 retomada de campanha (guia/Dicas). Estruturas próprias, fontes primárias, sem FAQ/tabela obrigatórios. Imagens WebP 1200×675 em `uploads/posts/{slug}/images/capa.webp` e `img-001.webp` a `img-003.webp`. A captura de Alan Wake 2 veio da página oficial PlayStation. Revisadas alegações de infrassom, áudio espacial, duração de runs e preservação do save.

Instagram: seis Reels 1080×1920 H.264/AAC, música incorporada e trilhas diferentes. 229/231: arquivos `reel-{id}-revisado.mp4` já conferidos. 230/232/233/234: `reel-{id}-final.mp4`, com poster e manifesto. Duram 24 segundos, exceto 230 (28) e 233 (30). Um vídeo por registro. Conversão de 230/233 em transação local pelo repositório oficial, com backup dos vínculos anteriores; arquivos antigos preservados. Status e datas mantidos. 232/234 são humor autônomo e não apontam para artigos diferentes.

Trilhas por ID: 229/21 Caves of Dawn; 230/25 Dark Fantasy Ambient; 231/22 Best Dramatic Music; 232/34 Sunset Vibes; 233/28 Little Slime’s Adventure; 234/15 The Arcade City. Biblioteca existente, sem novos downloads.

## Imagens criadas com ImageGen integrado

Saídas do projeto: `public/uploads/reels/leva02-bloco1/squad-imagegen.png` e `relogio-imagegen.png`.

Prompt do squad: ilustração editorial cinematográfica em quadrinhos, 16:9, adulto gamer brasileiro esperando respostas no celular; quatro vinhetas orgânicas de amigos trabalhando, estudando, viajando e dormindo; cinco adultos, anatomia correta, paleta azul-marinho/ciano/magenta, pintura e traço profissional, sem textos, logos, interface ou cards.

Prompt do relógio: ilustração editorial cinematográfica em quadrinhos, 16:9, adulto gamer cansado segurando controle no fim da noite; despertador analógico em primeiro plano, café e amanhecer ao fundo, expressão divertida, anatomia correta, paleta azul-marinho/ciano/magenta e luz quente; cena única sem lettering, logos, interface ou cards. O gerador incluiu numerais naturais no mostrador, sem prejudicar a narrativa.

## Documentação e prévias

A fonte oficial da Central de Conhecimento é `docs/features/planejamento-editorial-leva-02.md`, exibida pelo controlador LocalDocs e pela Base Técnica administrativa. Atualizada nessa fonte. As três galerias de prévia são geradas do mesmo payload, com seis players e os quatro artigos. Endereço de revisão solicitado: https://nerd.tfr-info.com.br/public/uploads/previews/leva02-bloco1-v3/index.html.

## Backup e recuperação

Original: `storage/correcao-leva02-20261010-codex/`, JSON dos registros, 183 arquivos e manifesto SHA-256. Antes da conversão: `storage/correcao-leva02-reels-20261010/`, snapshots de posts, Instagram e 14 mídias, além dos scripts/documentos anteriores. Os vídeos e cards anteriores continuam no disco.

Recuperação, somente com autorização específica: conferir o ambiente local e ausência de publicação em andamento, restaurar os campos e vínculos dos IDs do lote em transação usando os JSONs correspondentes, restaurar os arquivos do backup e regenerar as prévias. Não executar UPDATE/DELETE amplo nem sincronizar outro ambiente. O script de revisão não faz rollback automático.

## Validação e entrega

Validação executada: 259 verificações de integridade aprovadas; suíte obrigatória `scripts/verify-changes.php`: 17 OK, zero falhas (inclui PHPStan nível 5 e dashboards). Inspecionadas 24 cenas finais em seis folhas de revisão. Os seis players locais carregam sem erros com as durações esperadas. Documentação oficial exibida pela interface LocalDocs. Tarefas 1075–1079 reutilizadas no Forge.

Limitação de acesso: o endereço HTTPS solicitado redirecionou para autenticação Cloudflare Access no navegador desta sessão. A aba foi aberta para o usuário; a prévia local equivalente foi exibida e validada. Não se afirma validação do conteúdo além dessa autenticação.

Uploads e storage seguem a política de arquivos ignorados do projeto: commit/push de código e documentação não publica as mídias nem altera o ambiente de produção. Bloco 2 permanece fora do escopo.
