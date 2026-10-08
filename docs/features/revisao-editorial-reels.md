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
- A capa do artigo Kingston NV3 mostra NV2. A nova prévia usa uma imagem oficial da [linha NV3](https://www.kingston.com/en/ssd/nv3-nvme-pcie-ssd). As capas incorretas dos dois artigos continuam no blog: corrigir o conteúdo publicado requer escopo próprio. Origem e hashes das duas imagens estão em `resources/reels-review/assets/sources.json`.
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
