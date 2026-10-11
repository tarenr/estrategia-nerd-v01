## Comparativo 206 — versão 3 com fundo versus (10/10/2026)

Primeiro vídeo do grupo de comparações: RTX 3050 versus RX 6600. A proposta visual aprovada usa confronto ciano/âmbar, metal grafite e um VS em relevo no fundo. O fundo foi preparado com ImageGen a partir da proposta aprovada; os produtos vêm diretamente da `capa.webp` existente no artigo, sem gerar ou substituir as placas. A imagem original permanece inteira, na proporção 16:9, fixa em x=60, y=880, 960×540. Marca, títulos e apoio ficam acima; critérios e chamada ficam abaixo.

O compositor `scripts/reels-review/comparison-scene.mjs` aceita exclusivamente o ID 206, perfil `comparison-206-v1`, quatro cenas de cinco segundos e 20 segundos totais. Roteiro, legenda, trilha e ponto inicial do áudio são preservados. Apenas os textos fazem uma entrada suave; a capa não reinicia, muda ou recebe zoom nas trocas de cena. A primeira cena destaca os dois modelos nas cores de confronto, e as demais usam os títulos originais.

Entregas: `public/uploads/reels/correcao-instagram-20261010/reel-206-v3.mp4`, capa `.jpg`, folha `-contact.jpg` e manifesto `.mp4.manifest.json`. Fundo: `images/reel-206-v3-background.png`. As galerias existentes apontam para v3; o endereço novo evita reutilização da v2 em cache. Backup do catálogo: `storage/correcao-instagram-20261010/comparison-206-before/catalog.json`. Os outros 36 registros, arquivos anteriores, banco e fila permanecem preservados. A aprovação visual do vídeo completo e a autorização para publicação são etapas posteriores.

## Correção dos Reels 198 e 201 — versão 4 com imagens dos artigos (10/10/2026)

O 198 possui cinco cenas de quatro segundos: setup completo, Ryzen 5 7600X, MSI B650 TOMAHAWK WIFI, RTX 3050 de 6 GB e Corsair Vengeance DDR5. Cada cena usa a imagem existente do artigo correspondente. A placa-mãe usa `capa-corrigida-c9d791d5fe98.webp` do artigo da B650: a antiga `capa.webp` ainda identifica uma B850 e não deve ser reutilizada nesta revisão. O texto da cena foi corrigido para B650 e a cena da GPU foi acrescentada. As demais falas e o áudio foram preservados.

O 201 usa diretamente a `capa.webp` do artigo da RTX 3050 em suas quatro cenas, preservando roteiro, trilha e duração. Não foram geradas imagens. O compositor permite cinco cenas exclusivamente para o 198; os demais perfis de hardware continuam com quatro cenas.

Entregas: `public/uploads/reels/correcao-instagram-20261010/reel-{198,201}-v4.mp4`, capas `.jpg`, folhas `-contact.jpg` e manifestos `.mp4.manifest.json`. A versão 4 tem endereços novos para evitar reutilização dos vídeos v3 em cache. As versões anteriores permanecem em disco e em `previousVersions`. As duas galerias existentes apontam para v4. Os demais Reels, incluindo 200 e 203, e a fila de publicação permanecem preservados.

## Hardware 198, 199, 201, 202 e 203 — histórico da versão 3 com identidade aprovada do 200 (10/10/2026)

Adaptação dos cinco Reels do grupo de produtos de hardware (198, 199, 201, 202 e 203) à identidade visual aprovada do Reel 200 v3 (commit `5e4ac85`). Cada Reel possui 20 segundos exatos (quatro cenas de 5 segundos), formato vertical 1080×1920 @ 30 fps, moldura técnica fixa (x=82, y=764, 916×618), fundo grafite com circuitos e tipografia Bahnschrift.

- **198 (Setup Completo)**: Foto real autêntica da bancada do setup do Estratégia Nerd (`capa.webp` do Artigo 18, 1600×900) preenchendo a moldura de forma contínua nas 4 cenas de 5 segundos, com foco no conjunto de monitores, gabinete gamer real, mousepad da marca e ambiente de gravação.
- **199 (Ryzen 5 7600X)**: Foto oficial do produto (`capa.webp` do Artigo 19) destacando a embalagem original e o processador AMD Ryzen 5 7600X com o IHS da arquitetura AM5.
- **201 (RTX 3050 6 GB)**: Foto oficial da placa ZOTAC GAMING GeForce RTX 3050 6 GB (`capa.webp` do Artigo 21) com a caixa e o modelo compacto de dupla ventoinha.
- **202 (Corsair Vengeance DDR5 32 GB)**: Foto oficial em alta definição dos dois módulos Corsair Vengeance DDR5 (`capa.webp` do Artigo 22).
- **203 (Kingston NV3 1 TB)**: Arte oficial do Kingston NV3 NVMe M.2 2280 em bancada de testes de alta resolução (1200×800), sem reutilizar referências do modelo anterior NV2.
- **200 (MSI B650 TOMAHAWK WIFI)**: Sequência aprovada do commit `5e4ac85` com 4 fotos fiéis à placa-mãe B650 real, preservada integralmente sem reutilizar a antiga capa do blog que continha referência à B850.

Compositor: `scripts/reels-review/hardware-scene.mjs` atualizado para autorizar exclusivamente os IDs [198, 199, 200, 201, 202, 203], mantendo o Reel 200 aprovado 100% intacto. Todos os vídeos foram renderizados com `-movflags +faststart` (átomo `moov` posicionado no início, offset 32 antes do `mdat`).
Vídeos entregues: `public/uploads/reels/correcao-instagram-20261010/reel-{198,199,201,202,203}-v3.mp4` acompanhados de capa `.jpg` e folha de contato `-contact.jpg`. Galeria atualizada: `public/uploads/previews/leva02-bloco1-v3/index.html` e `public/uploads/reels/correcao-instagram-20261010/gallery.html`. Metadados de prompts registrados em `storage/correcao-instagram-20261010/hardware-198-203-imagegen.json`.
Validações técnicas: decodificação integral sem erros em todos os MP4s, verificação dos 37 roteiros com determinismo e suíte oficial PHP com 17 OK e zero falhas. Nenhuma alteração foi realizada no banco de dados, na fila ou nos posts agendados/publicados.

## Guias de PC — imagem fixa, versões 3 (10/10/2026)

Os Reels 204, 209, 212 e 213 receberam a identidade aprovada do 207 v5: texto acima, moldura central preenchida e chamada abaixo, com posições fixas, grafite, ciano e detalhes em âmbar. Cada vídeo usa uma única imagem adaptada da capa do próprio artigo com ImageGen, sem títulos embutidos, durante todos os 20 segundos. Não há zoom, reinício, troca ou fade da imagem quando o texto muda. O 204 conserva cinco cenas, 209 e 212 quatro, e 213 cinco; roteiro, legenda, trilha e ponto inicial do áudio foram preservados.

O perfil `diagnostic-guide-v1` reutiliza `scripts/reels-review/diagnostic-scene.mjs` exclusivamente para os quatro IDs. A fonte e a geometria do 207 foram preservadas; a imagem é carregada uma vez e compartilhada entre as cenas. O comando `node scripts/reels-review/diagnostic-guides.mjs` preserva arquivos anteriores e relê o catálogo após cada renderização, evitando substituir atualizações concorrentes de outros registros. `--verify` verifica sem atualizar o catálogo ou as capas.

Entregas: `public/uploads/reels/correcao-instagram-20261010/reel-{204,209,212,213}-v3.mp4`, capas `.jpg` e folhas `-contact.jpg`. Imagens: `images/reel-{id}-guide-v3.png`. Galeria existente: `public/uploads/previews/leva02-bloco1-v3/index.html?id={id}&v=3`. Prompts e fontes: `storage/correcao-instagram-20261010/guides-imagegen.json`; backup: `guides-before/`; resultados: `guides-validation.json`.

O usuário esclareceu que os publicados estão aprovados: 214 permanece integralmente preservado, sem nova prévia aplicada, exclusão ou republicação. Os aprovados 196, 200 e 207 também foram preservados. A arte experimental do 214 não foi incorporada ao projeto. Banco, artigos, fila, datas e cancelamentos não foram alterados.

Validação: quatro folhas de cenas inspecionadas; todos os vídeos decodificados integralmente e com faststart confirmado por ordem de átomos; H.264/AAC, 1080×1920, 30 fps e 20 segundos. Testes compararam os pixels da moldura entre todas as cenas, confirmando imagem idêntica, recorte e opacidade contínuos. Os 37 roteiros passaram na regressão; suíte PHP: 17 OK e zero falhas. A avaliação visual dos quatro vídeos pelo usuário permanece pendente; não houve publicação.

## Diagnóstico 207 — correção pontual da RAM, versão 5 (10/10/2026)

A v5 substitui exclusivamente a quarta imagem após o usuário apontar uma posição artificial das mãos. A nova arte ImageGen mostra o gabinete aberto, desligado, com os módulos de RAM instalados e sem pessoas. As outras quatro imagens, fundo, compositor, textos, áudio e tempos foram preservados. A v4 permanece no histórico e em disco.

Vídeo vigente: `public/uploads/reels/correcao-instagram-20261010/reel-207-v5.mp4`; galeria: `index.html?id=207&v=5`. Nova imagem: `images/reel-207-v5-scene-004.png`. Prompt e origem: `storage/correcao-instagram-20261010/diagnostic-207-ram-fix.json`; backup do catálogo: `diagnostic-207-before/catalog-before-ram-fix.json`. O comando `node scripts/reels-review/diagnostic-207.mjs --ram-fix` verifica que a única diferença no roteiro visual é o caminho da quarta imagem. Decodificação integral, duração e faststart passaram; suíte PHP: 17 OK, zero falhas. Fila e banco não foram alterados; avaliação visual do usuário permanece pendente.

## Diagnóstico 207 — versão 4, 20 segundos (10/10/2026)

Primeiro Reel do grupo de orientação prática, produzido após aprovação da proposta visual. Preserva os cinco textos, a legenda e a origem/posição inicial da trilha; usa cinco cenas de quatro segundos. As artes ImageGen representam o problema, cabo do monitor, saída de vídeo, inspeção desligada e desconexão da energia. Grafite, ciano, pequenos detalhes em âmbar e marcações de bancada distinguem o diagnóstico da análise de produtos. Não houve revisão editorial nesta etapa.

Perfil restrito a `reviewPostId=207`, `compositionProfile=diagnostic-207-v1`, em `scripts/reels-review/diagnostic-scene.mjs`. A imagem preenche a moldura fixa x=46, y=758, 994×680, sem zoom; título, apoio e chamadas têm posições fixas. O cabeçalho utiliza Inter em negrito e as marcações usam Bebas Neue, fontes já existentes. O primeiro render v3 ficou preservado; a v4 corrige a largura e quebra dos títulos para aproximá-los da proposta aprovada.

Entrega: `public/uploads/reels/correcao-instagram-20261010/reel-207-v4.mp4`, capa `.jpg` e folha `-contact.jpg`. Galeria: `public/uploads/previews/leva02-bloco1-v3/index.html?id=207&v=4`. Fundo e cinco cenas estão em `images/reel-207-v3-background.png` e `images/reel-207-v3-scene-001.png` a `005.png`. Prompts: `storage/correcao-instagram-20261010/diagnostic-207-imagegen.json`; backup: `diagnostic-207-before/`. A v3 é um ensaio técnico, não a versão vigente.

O comando `node scripts/reels-review/diagnostic-207.mjs` usa o renderizador existente, preserva versões anteriores e relê o catálogo depois da renderização para manter alterações concorrentes de outros IDs; recusa sobrescrever mudanças concorrentes no próprio 207. Se a v4 já estiver pronta, verifica duração/hash sem alterar arquivos. A galeria é regenerada pelo mecanismo existente. O perfil do 200 permanece intacto; os demais conteúdos não foram modificados por esta entrega. Banco, fila e publicação permanecem inalterados.

Validação: cinco quadros e capa inspecionados; decodificação integral sem erros; H.264/AAC 1080×1920, 20 segundos; ordem dos átomos `ftyp`, `moov`, `free`, `mdat`, confirmando faststart; testes dos 37 roteiros aprovados e suíte oficial PHP com 17 OK, zero falhas. Player local reproduziu a versão correta com áudio presente e resolução/duração esperadas. A avaliação estética do vídeo completo e a interface nativa móvel do Instagram dependem da próxima validação. Não houve publicação.

## Hardware 200 — versão 3, 20 segundos (10/10/2026)

Implementação restrita ao Reel 200, após aprovação da proposta visual 03 e do plano completo. Quatro cenas de cinco segundos usam imagens individuais geradas com ImageGen: placa completa, socket/memória, conexões/expansão e planejamento de upgrades. A composição mantém texto acima, foto preenchendo a moldura e chamada abaixo; grafite, ciano, detalhes de circuitos e tipografia Bahnschrift definem esta família de hardware. Não foi aplicada aos demais posts.

O compositor está em `scripts/reels-review/hardware-scene.mjs`, perfil `hardware-product-v1`. A moldura ocupa x=82, y=764, 916×618, sem faixas internas ou zoom. A fonte utiliza a instalação existente `C:/Windows/Fonts/bahnschrift.ttf`; ausência da fonte interrompe a renderização. O manifesto registra hashes do compositor, fundo e fonte. Os prompts estão em `storage/correcao-instagram-20261010/hardware-200-imagegen.json`.

Vídeo e capa: `public/uploads/reels/correcao-instagram-20261010/reel-200-v3.mp4` e `reel-200-v3.jpg`. Galeria vigente: `public/uploads/previews/leva02-bloco1-v3/index.html?id=200&v=3`. As quatro imagens ficam na subpasta `images`, de `reel-200-v3-scene-001.png` a `004.png`; o fundo é `hardware-product-background-v1.png`. A versão 2 permanece preservada, com referência e hash no catálogo. Backup do catálogo e galeria: `storage/correcao-instagram-20261010/hardware-200-before/`.

Textos, legenda e origem da trilha foram preservados; a duração foi reduzida para 20 segundos conforme o plano. Os outros 36 registros foram comparados integralmente e preservados, incluindo o 196 v5 aprovado pelo usuário e os conteúdos de Diablo. Banco, fila, agendamentos e cancelamentos não receberam alterações.

Validação executada: quatro quadros inspecionados; decodificação integral sem erros; MP4 H.264 1080×1920 com áudio AAC e 20 segundos; testes dos 37 roteiros aprovados; suíte oficial PHP com 17 OK e zero falhas. A página local carregou a nova capa e o player reproduziu o vídeo de 20 segundos. A avaliação do vídeo completo pelo usuário e a validação na interface nativa móvel do Instagram permanecem pendentes. Não houve publicação.

Esta fonte também alimenta a documentação interna em `/local/mudancas/documento?grupo=features&arquivo=revisao-editorial-reels.md`.

## 196 — versão 5 com composição anterior (10/10/2026)

A primeira cena foi apresentada e recebeu o retorno “Ficou bom”. As quatro cenas usam agora a composição do vídeo anterior: título e apoio acima, moldura central com linhas e detalhes, chamada abaixo. As quatro artes foram adaptadas com ImageGen e salvas em `public/uploads/reels/correcao-instagram-20261010/images/reel-196-v5-scene-001.png` a `004.png`. O cenário ocupa toda a moldura de 840×600, sem faixas laterais; não há zoom da imagem. O perfil `196-v5` reutiliza o compositor existente exclusivamente neste item, mantendo textos, legenda, áudio, 32 segundos e quatro cenas de oito segundos. Os outros 36 itens foram comparados ao catálogo anterior e preservados.

Vídeo vigente para inspeção: `public/uploads/reels/correcao-instagram-20261010/reel-196-v5.mp4`; galeria: `index.html?id=196&v=5`. As versões anteriores continuam preservadas. A v4 está superada como proposta visual. A primeira cena validada não equivale a aprovação do vídeo completo ou publicação; cadastro, fila e cancelamentos não foram alterados nesta revisão.

Verificações: decodificação integral do MP4 sem erros; quatro quadros inspecionados; testes dos 37 roteiros aprovados, com continuidade, enquadramento e determinismo. A validação na interface nativa móvel do Instagram permanece pendente.

## Correção visual individual do 196 — versão 4 (10/10/2026)

Somente a composição visual foi alterada. As quatro ilustrações do avatar, cenas, textos, duração e áudio da v3 foram preservados. A área de imagem é fixa em x=86, y=300, 908×680; cada ilustração permanece inteira e centralizada. Rótulo, título e corpo começam em y=1025, 1080 e 1300, seguindo as posições da composição comum. O perfil é restrito a `visualRevision: 196-v4`; os outros 36 itens não receberam alterações. A v3 e seu hash estão preservados em `previousVersions`.

Vídeo: `public/uploads/reels/correcao-instagram-20261010/reel-196-v4.mp4`. A galeria existente aceita `?id=196` para validar este item isoladamente. O cadastro publicado e a fila não foram alterados; aprovação visual e republicação continuam pendentes. A verificação técnica não comprova a área segura da interface nativa do Instagram.

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

### Decisão por registro

| ID | Assunto | Decisão |
|---|---|---|
| 1 | 10 jogos que todo fã de RPG precisa jogar antes de morrer | Nova versão individual; legenda revisada |
| 190 | 16 GB ou 32 GB de RAM em 2026: quanto um PC gamer realmente precisa? | Nova versão individual; legenda revisada |
| 195 | A Guerra Eterna: o conflito que nunca teve fim | Vídeo reaproveitado; legenda revisada |
| 196 | As ferramentas que moldam os guerreiros modernos | Nova versão individual; legenda revisada |
| 197 | A Lore Completa de Diablo: Entenda Toda a História do Universo | Vídeo reaproveitado; legenda revisada |
| 198 | O Setup que Sustenta o Estratégia Nerd: A Máquina por Trás de Tudo | Nova versão individual; legenda revisada |
| 199 | AMD Ryzen 5 7600X: Desempenho Real no Meu Setup | Nova versão individual; legenda revisada |
| 200 | MSI MAG B650 TOMAHAWK WIFI: a base que sustenta um setup de verdade | Nova versão individual; legenda revisada |
| 201 | RTX 3050 6GB: a escolha inteligente para um setup equilibrado | Nova versão individual; legenda revisada |
| 202 | Corsair Vengeance DDR5 32GB 6000MHz: equilíbrio ideal para setups modernos | Nova versão individual; legenda revisada |
| 203 | Kingston NV3 1TB NVMe PCIe 4.0: velocidade real para o dia a dia | Nova versão individual; legenda revisada |
| 204 | Erros comuns ao montar um PC gamer e como evitar | Nova versão individual; legenda revisada |
| 205 | 10 jogos que todo fã de RPG precisa jogar antes de morrer | Vídeo reaproveitado; legenda revisada |
| 206 | RTX 3050 vs RX 6600: Qual Vale Mais a Pena em 2026? | Nova versão individual; legenda revisada |
| 207 | PC liga, mas não dá vídeo: como diagnosticar sem trocar peças à toa | Nova versão individual; legenda revisada |
| 208 | The Witcher 3 Remastered: novidades e condições do upgrade | Nova versão individual; legenda revisada |
| 209 | Windows 11 24H2 Home e Pro: fim do suporte em 13/10/2026 | Nova versão individual; legenda revisada |
| 210 | RTX 5060 vs RX 9060 XT: qual placa compensa em 1080p | Nova versão individual; legenda revisada |
| 211 | AM4 ou AM5 em 2026: qual plataforma escolher | Nova versão individual; legenda revisada |
| 212 | Como escolher uma fonte para PC: sem cair em marketing | Nova versão individual; legenda revisada |
| 213 | Como verificar a saúde do SSD e do HD antes de perder seus arquivos | Nova versão individual; legenda revisada |
| 214 | Temperatura de CPU e GPU: quando você deve se preocupar | Nova versão individual; legenda revisada |
| 215 | 12 jogos para PC fraco ou modesto: opções e requisitos | Vídeo reaproveitado; legenda revisada |
| 216 | A história do Counter-Strike: por que ainda é gigante | Vídeo reaproveitado; legenda revisada |
| 217 | 10 filmes nerds essenciais que todo geek deveria ver | Vídeo reaproveitado; legenda revisada |
| 218 | 10 animes essenciais pra quem nunca assistiu nenhum | Vídeo reaproveitado; legenda revisada |
| 219 | 10 desenhos dos anos 90 e 2000 que envelheceram bem | Vídeo reaproveitado; legenda revisada |
| 224 | O mundo de Diablo: Santuário, Céu e Inferno explicados | Vídeo reaproveitado; legenda revisada |
| 225 | Os Arcanjos do Céu: a verdade por trás da luz em Diablo | Vídeo reaproveitado; legenda revisada |
| 226 | Os Males do Inferno em Diablo: Quem Realmente Controla o Caos | Vídeo reaproveitado; legenda revisada |
| 227 | Os Nefalem: o verdadeiro poder por trás da humanidade | Vídeo reaproveitado; legenda revisada |
| 229 | Muito além dos jumpscares: 3 jogos que constroem o medo pela atmosfera | Nova versão individual; legenda revisada |
| 230 | Muito além dos jumpscares: 3 jogos que constroem o medo pela atmosfera | Nova versão individual; legenda revisada |
| 231 | Como o som cria medo nos jogos: pistas, silêncio e espaço | Nova versão individual; legenda revisada |
| 232 | O squad existe. O horário, não. | Nova versão individual; legenda revisada |
| 233 | Jogos para quem só tem 30 minutos por dia: escolha pela pausa | Nova versão individual; legenda revisada |
| 234 | Só mais uma partida | Nova versão individual; legenda revisada |
| 191–194 | Cancelados | Preservar; não reativar |

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

# Revisão editorial dos 30 Reels

Escopo aprovado em 07/10/2026: revisar os posts locais **190–219**, incluindo os Diablo já publicados, gerar prévias separadas e permitir comparação pelo celular. Não inclui os 79 posts antigos importados sem associação editorial ao blog nem o rascunho #1, cuja associação não corresponde à legenda.

## Direção editorial

O modelo anterior extraía cenas do resumo, sem roteiro individual: repetia chamadas genéricas, cortava frases e reapresentava a imagem a cada mudança de texto. Além disso, a seleção por `img*` ignorava retratos cujos nomes eram o slug do artigo. Esta revisão tem **30 decisões humanas explícitas** em `resources/reels-review/storyboards.json`; não apresenta uma divisão automática de resumo como revisão editorial.

Cada roteiro define quatro momentos com textos completos, imagens identificadas pelo nome, tratamento visual, trilha local e recorte. O renderizador não inventa novos roteiros para artigos futuros: esses precisam de nova curadoria.

- Uma imagem é uma tomada contínua. A imagem aparece primeiro, e os textos passam a conduzir o conteúdo; não há fade ou reinício da imagem na troca do texto.
- Imagens diferentes recebem transição apenas nas mudanças reais. Imagens consecutivas iguais são agrupadas em uma tomada.
- Histórias usam marcos narrativos e nomes próprios, sem capítulos 1/2/3. Comparações usam cenários de escolha; guias usam orientações concretas; listas apresentam exemplos, sem insinuar ranking.
- Paletas, tipografia e entrada dos textos variam conforme lore sombria, Céu, hardware, dicas, fantasia, ação, cinema, anime e nostalgia. Movimento determinístico por tempo de quadro: zoom discreto contínuo e animação escalonada de títulos e corpo.
- As imagens existentes são preservadas integralmente por enquadramento proporcional, sem cortar texto impresso. Não há geração paga de imagens, novos serviços ou dependências.

## Revisão por post

| Post | Assunto | Direção da prévia |
|---|---|---|
| 190 | RAM 16/32 GB | Imagem persistente; decidir pelo uso, capacidade e compatibilidade. |
| 191 | Mundo de Diablo | Guerra, Alto Céu, Inferno e Santuário com quatro imagens correspondentes. |
| 192 | Males do Inferno | Retratos de Diablo, Mephisto e Baal; demais Males no fecho, sem ranking. |
| 193 | Arcanjos | Imperius, Tyrael e Malthael identificados; Conselho Angiris no fecho. |
| 194 | Nefalem | Uma arte contínua: origem, poder, enfraquecimento e herança. |
| 195 | Guerra Eterna | Duas artes: conflito, forças em disputa e humanidade; recuperar imagem ignorada pelo nome. |
| 196 | Ferramentas dos guerreiros | Periféricos: precisão, comandos e escolha pelo uso; o artigo não é lore de Diablo. |
| 197 | Lore completa de Diablo | Anu/Tathamet, guerra, mapa de Santuário e Nefalem; sem números. |
| 198 | Setup do Estratégia Nerd | Setup, Ryzen, B850 e DDR5 com imagens de cada componente. |
| 199 | Ryzen 7600X | Produto contínuo: núcleos/threads, uso, refrigeração e conjunto. |
| 200 | MSI B650 | Plataforma, compatibilidade, conexões e upgrades; distinta da B850 do artigo do setup. |
| 201 | RTX 3050 6 GB | Entrada, ajustes e limites; não prometer desempenho universal em ray tracing. |
| 202 | Corsair DDR5 | Capacidade, multitarefa e compatibilidade do kit. |
| 203 | Kingston NV3 | Uso cotidiano e instalação; sem benchmark ou ganho universal inventado. |
| 204 | Erros ao montar PC | Equilíbrio, fonte e planejamento com imagens das seções corretas. |
| 205 | Dez RPGs | Chrono Trigger, The Witcher 3 e Baldur's Gate 3 como exemplos; seleção completa no artigo. |
| 206 | RTX 3050/RX 6600 | Modelo exato, versões de 6/8 GB e preço; sem barras fictícias de FPS. |
| 207 | PC sem vídeo | Monitor, entrada e conector antes de trocar peças; próximos testes no guia. |
| 208 | Witcher Remastered | Anúncio e condições do upgrade; retirar promessa futura de 29 de setembro. |
| 209 | Windows 24H2 | Edição/versão, prazo de 13/10/2026, winver e Windows Update. |
| 210 | RTX 5060/RX 9060 XT | Versão, memória, testes comparáveis e conjunto, sem resultado numérico inventado. |
| 211 | AM4/AM5 | Reaproveitar DDR4 versus comprar plataforma DDR5; considerar custo total. |
| 212 | Fonte | Potência, qualidade, proteções e conectores; eficiência não equivale a segurança. |
| 213 | Saúde do SSD/HD | SMART, interpretação e backup; status bom não garante ausência de falha. |
| 214 | Temperatura CPU/GPU | **Referência aprovada preservada integralmente**, sem forçar o novo modelo. |
| 215 | Jogos para PC modesto | Stardew Valley/Vampire Survivors como exemplos; conferir requisitos por configuração. |
| 216 | História de Counter-Strike | Mod de 1999, versões clássicas, CS:GO e CS2, sem capítulos numerados. |
| 217 | Filmes | Matrix, Sociedade do Anel e De Volta para o Futuro como exemplos de estilos. |
| 218 | Animes | Death Note, Spy x Family e Brotherhood como portas de entrada por gosto. |
| 219 | Desenhos | Dexter, Samurai Jack e Avatar; nostalgia sem ranking. |

## Informações conferidas e pendências editoriais

- [CD PROJEKT RED: anúncio do Remastered](https://www.thewitcher.com/es/en/news/52017/announcing-the-witcher-3-wild-hunt-remastered): confirmado o anúncio e a condição de upgrade para proprietários elegíveis. A prévia não diz que o jogo base é grátis para qualquer pessoa. O título/conteúdo do artigo ainda usa anúncio futuro de 29/09: **atualizar o blog antes de usar o post**, em escopo próprio. O título original continua visível na galeria para identificar o artigo.
- [Microsoft: versões do Windows 11](https://learn.microsoft.com/en-us/windows/release-health/windows11-release-information): a tabela registra 13/10/2026 para fim das atualizações de 24H2 Home/Pro. A redação deve ser conferida novamente na data de publicação. A fila não foi antecipada nem alterada.
- [AMD: Ryzen 7600X](https://ir.amd.com/news-events/press-releases/detail/1089/amdlaunches-ryzen-7000-series-desktop-processors-with-zen-4-architecture-the-fastest-core-in-gaming): seis núcleos/doze threads. [MSI: B850](https://www.msi.com/Motherboard/MAG-B850-TOMAHAWK-MAX-WIFI/Specification): plataforma AM5. A imagem do setup indica B850. A capa do artigo específico B650 também mostra uma B850: foi substituída **na prévia** por imagem oficial da [B650](https://www.msi.com/Motherboard/MAG-B650-TOMAHAWK-WIFI). A capa da RTX 3050 foi inspecionada em tamanho original e indica 6 GB.
- A capa do artigo Kingston NV3 mostra NV2. A nova prévia usa uma imagem oficial da [linha NV3](https://www.kingston.com/en/ssd/nv3-nvme-pcie-ssd). As capas incorretas foram corrigidas no banco local em 08/10/2026. Stage e produção seguem pendentes, conforme o impedimento descrito abaixo. Origem e hashes das duas imagens estão em `resources/reels-review/assets/sources.json`.
- [Blizzard: história de Diablo](https://news.blizzard.com/en-us/article/23725427/diablo-ii-the-story-so-far): conferidos criação de Santuário, origem dos Nefalem e enfraquecimento pelo Worldstone.

As afirmações quantitativas não verificadas do artigo (benchmarks, velocidades comerciais, preços e recomendações universais) não foram repetidas nos roteiros. Esta entrega não corrige automaticamente os artigos do blog.

## Música

Escolhas manuais por tema e ritmo editorial, utilizando apenas arquivos locais ativos com hash conferido em `config/reel-music.json`. Não existe fallback para uma atmosfera incompatível. A compatibilidade aqui é curadoria de metadados e da prévia aprovada pelo usuário, **não análise de BPM nem audição humana certificada de todas as faixas**. Áudio audível/codec/recorte são validados separadamente da adequação estética, que deve ser avaliada com som na galeria.

O catálogo dispõe de poucas faixas dark. Cinco Reels sombrios de Diablo usam **Dark Fantasy Ambient (Dungeon Synth)** em recortes diferentes; alguns recortes se sobrepõem porque a faixa tem 107 segundos. Os Arcanjos usam uma faixa de fantasia medieval. Preferir compatibilidade a música retrô inadequada; esta entrega não amplia o acervo nem promete variedade ilimitada.

## Arquivos e operação

- `scripts/reels-review/snapshot.php`: CLI; apenas SELECT nos registros Instagram **locais** e artigos de produção. Salva snapshot sem credenciais em storage. Não usa o banco remoto para Instagram.
- `scripts/reels-review/scene.mjs`: composição manual isolada; o motor Konva existente só delega quando `editorial=manual`. A geração automática e os vídeos da fila não são migrados implicitamente.
- `scripts/reels-review/batch.mjs`: preflight de texto/fontes/imagens/trilhas, renderização, verificação e página estática. Recusa diretório inicial existente e não sobrescreve MP4 anterior. Correções de uma prévia recebem nome v2/v3, mantendo as anteriores.
- `scripts/reels-review/verify.mjs`: verifica continuidade real da imagem nas fronteiras do texto, precedência da imagem, leitura, movimento e determinismo.
- `resources/reels-review/gallery.html`: modelo da página, com busca, filtro Diablo/agendados/publicados/referência, alternância anterior/revisado, reprodução individual, download e link por post. Cada player já aponta para o revisado com preload="none", sem autoplay. A reprodução pode começar pelo controle nativo; os botões alternam versões.

```powershell
C:/xampp/php/php.exe scripts/reels-review/snapshot.php
node scripts/reels-review/batch.mjs --run revisao-reels-20261007 --prepare
node scripts/reels-review/batch.mjs --run revisao-reels-20261007 --render
node scripts/reels-review/verify.mjs --run revisao-reels-20261007
node scripts/reels-review/batch.mjs --run revisao-reels-20261007 --verify
node scripts/reels-review/batch.mjs --run revisao-reels-20261007 --gallery
# Corrigir um roteiro pronto, preservando a prévia anterior:
node scripts/reels-review/batch.mjs --run revisao-reels-20261007 --revise --id 197
node scripts/reels-review/batch.mjs --run revisao-reels-20261007 --render --id 197
```

O snapshot usa nome exclusivo; para outra leitura, passar `snapshot-after.json`, por exemplo. Preparar outro lote exige nome novo. Evitar dois processos de escrita sobre o mesmo manifesto: aguardar o render terminar antes de revisar ou montar a galeria.

Galeria: **https://nerd.tfr-info.com.br/uploads/previews/revisao-reels-20261007/index.html**. Artefatos, vídeos e evidências são locais em `public/uploads/previews/revisao-reels-20261007` e `storage/previews/reels-review/revisao-reels-20261007`, ignorados pelo Git. No Git ficam os roteiros, renderer, scripts, página-modelo e documentação. Depende do PC/Apache/cloudflared ligados; sem deploy Hostinger.

Os cinco registros publicados refletem o cadastro local. O usuário informou exclusão dos Diablo do Instagram: isso não foi sincronizado nem tratado como autorização de repostagem. Fila, legenda, trilha, referências de vídeo e status originais são preservados.

Tarefas Forge #884–888 cobrem curadoria, animação, lote/validação, galeria móvel e documentação/Git. A consulta de segunda opinião ao Codex CLI read-only não iniciou por acesso negado ao runtime; não conta como validação.

## Validação

Evidências do lote: `manifest.json`, `renderer-validation.json`, `validation.json`, specs por post e manifestos MP4 com hashes de fonte, áudio, fontes tipográficas e renderer. Antes do commit: suíte obrigatória `scripts/verify-changes.php` (17 verificações), PHPStan nível 5 dirigido ao snapshot e regressão da seleção temática (25 verificações).

Critérios: 30 posts presentes, 29 vídeos novos e referência #214 idêntica, MP4 1080×1920/30fps/H.264/yuv420p/AAC, decodificação completa, música audível, fonte legível, capas com imagem e texto, comparação navegável no celular, HTTP 200 e Range 206. A validação técnica não significa aprovação estética automática pelo usuário.

Resultados desta entrega: os 30 posts passaram na validação do renderer e na decodificação integral de vídeo/áudio. As 30 versões originais mantiveram seus hashes. A comparação dos snapshots confirmou os 30 registros locais intactos (`database-validation.json`). A suíte obrigatória passou nas 17 verificações; PHPStan nível 5 não encontrou erros e a regressão temática passou nas 25 verificações.

Pelo túnel, a página respondeu HTTP 200, as 30 capas responderam com MIME de imagem e os 60 vídeos anteriores/revisados responderam Range 206 com MIME MP4 (`http-validation.json`). No navegador com viewport de 390×844, as duas versões do #194 reproduziram sem erro; o revisado avançou até 9,49 segundos. Busca, ausência de resultados e filtros retornaram as contagens esperadas: Diablo 6, publicados 5, agendados 25 e referência 1. Não houve rolagem horizontal. Evidência visual: `mobile-gallery.png`. Essa verificação simula a largura de celular; não é teste em um aparelho físico.

## Correção do player inicial

O player inicialmente não tinha `src`: tocar no controle nativo antes de escolher uma versão deixava a impressão de vídeo ausente. Corrigido associando a prévia revisada desde a criação de cada card, com seleção inicial coerente e `preload="none"`. A página continua sem autoplay e pausa os demais vídeos ao reproduzir um. Não regenerou MP4 nem alterou banco ou fila.

Galeria atualizada no mesmo diretório local. Link com versão para evitar a página antiga em cache: https://nerd.tfr-info.com.br/uploads/previews/revisao-reels-20261007/index.html?v=player2 . Tarefas existentes #887 e #888 reabertas para esta correção, sem duplicação. Suíte obrigatória: 17 verificações aprovadas, zero falhas. Reprodução nativa conferida pelo túnel em viewport de 390×844, sem selecionar o botão Revisado antes: duração 28 s, reprodução ativa e nenhum erro de mídia.

## Enquadramento uniforme e capas corretas — 08/10/2026

A área visual dos 29 Reels manuais agora tem posição e dimensões fixas: x=86, y=365, 908×511 pixels (16:9, com arredondamento de um pixel). Não varia por layout nem pelo tamanho do arquivo de origem. O primeiro ensaio com área mais alta cortava palavras já presentes nas capas; a versão final 16:9 conserva essas inscrições. Cada cena declara `framing`, com modo, foco e região de origem quando necessário. Ilustrações preenchem a moldura por recorte proporcional. A B650 usa composição de produto inteiro sobre fundo próprio; o NV3 usa uma região da arte oficial que elimina margens excessivas. Não existe esticamento da imagem. Uma imagem repetida com o mesmo enquadramento permanece em tomada contínua durante as trocas de texto.

Foram geradas 29 versões adicionais: v2 na maioria dos posts e v3 nos IDs 197, 200 e 203. A referência #214 foi mantida. MP4 e prévias anteriores permanecem no diretório e no histórico do manifesto. A galeria atual é https://nerd.tfr-info.com.br/uploads/previews/revisao-reels-20261007/index.html?v=enquadramento3 . A fila e os arquivos usados pelo Instagram não foram alterados.

Validação: 30 tratamentos passaram no teste de renderer, incluindo continuidade da tomada, moldura fixa, proporção sem distorção e produto inteiro. Os 30 vídeos passaram na decodificação integral e áudio audível; os originais mantiveram seus hashes. Todas as 29 folhas de contato novas foram examinadas visualmente. Os 30 arquivos responderam Range 206/MIME MP4 pelo túnel (`framing-http-validation.json`). O NV3 revisado reproduziu até 28 segundos em viewport de 390×844 sem erro. A suíte obrigatória passou nas 17 verificações e o publicador de capas passou no PHPStan nível 5.

### Capas do blog e publicação restrita

`scripts/reels-review/blog-covers.mjs` compõe duas capas WebP de 1200×800 a partir das fontes oficiais já conferidas, usando processamento local. O original da MSI é preservado com o equipamento inteiro; a arte da Kingston identifica NV3, sem inventar capacidade na etiqueta. Os arquivos recebem nome derivado de SHA-256.

`scripts/reels-review/publish-blog-covers.php` opera somente nos dois slugs aprovados. Guarda todos os campos dos artigos, referências e cópias das capas; envia para nomes novos, compara hashes e atualiza apenas `posts.imagem_capa` com condição por ID, slug e referência anterior. O MySQL atualiza automaticamente `data_atualizacao` (ON UPDATE CURRENT_TIMESTAMP); os demais campos editoriais são comparados integralmente. Demais referências de capa também são conferidas. Instagram não é consultado nem modificado pelo publicador.

Local concluído: B650 id23 e NV3 id26; as páginas HTTP 200 no Apache (`http://127.0.0.1/estrategia-nerd/post/{slug}`) referenciam as capas novas e ambas as imagens respondem HTTP 200/WebP pelo túnel. Os IDs diferem entre ambientes, por isso o slug é obrigatório. Referências de produção e suas imagens originais também foram guardadas antes de qualquer alteração. Evidências em `storage/previews/reels-review/blog-covers-20261008/`, incluindo `covers.json`, `references-local.json`, `references-production.json` e `verified-local.json`. Nenhum desses arquivos contém credenciais.

**Impedimento:** stage contém B650 id21, mas não contém o artigo NV3; id23 lá é “teste”. A inspeção interrompeu antes de escrever em stage. Nenhuma capa foi publicada em stage ou produção. Criar um artigo não fazia parte do plano de atualização de duas referências; o complemento para copiar somente NV3 de produção para stage foi apresentado ao usuário e aguarda aprovação explícita. Não contornar o bloqueio usando IDs de outro ambiente nem sincronizar todo o blog. Tarefa Forge #889 permanece pendente.

Operação autorizada inicialmente:

```powershell
node scripts/reels-review/blog-covers.mjs
C:/xampp/php/php.exe scripts/reels-review/publish-blog-covers.php --inspect local
C:/xampp/php/php.exe scripts/reels-review/publish-blog-covers.php --apply local
C:/xampp/php/php.exe scripts/reels-review/publish-blog-covers.php --verify local
# Após resolver o artigo ausente com aprovação própria:
C:/xampp/php/php.exe scripts/reels-review/publish-blog-covers.php --inspect stage
C:/xampp/php/php.exe scripts/reels-review/publish-blog-covers.php --apply stage
C:/xampp/php/php.exe scripts/reels-review/publish-blog-covers.php --apply production
# Recuperação: --rollback ambiente restaura somente as referências anteriores.
```

A produção exige política de origem stage e a arte exata verificada em stage; a criação ausente não é automatizada pelo script. Backups existentes não são sobrescritos. Se algum destino já contiver um arquivo de mesmo nome com hash diferente, a operação falha. Rollback mantém todos os arquivos e recupera as referências, com novo carimbo automático do banco.
