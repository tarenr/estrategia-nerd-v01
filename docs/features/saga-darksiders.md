# Saga Darksiders — piloto do capítulo 1 (atualizado em 12/10/2026)

Saga de 6 capítulos no formato da saga do Diablo: artigo de blog com narração e música de fundo, mais um Reel narrado por capítulo. Esta entrega cobre **somente o capítulo 1**, "O mundo de Darksiders", como piloto. Os capítulos 2 a 6 só começam depois da aprovação. A pauta completa está em `conteudo-planejado/saga-darksiders/pauta.md`.

## Estado da entrega

| Item | Estado |
|---|---|
| Artigo (blog) | Rascunho no banco **local**, post #82, slug `o-mundo-de-darksiders-ceu-inferno-e-o-equilibrio`, categoria `games`. Nada em stage ou produção. |
| Narração do blog | Voz original preservada; player usa `narracao-mix-v3.mp3`, 46 s, com música incorporada. |
| Música de fundo do blog | `Caves of Dawn` (Pixabay 10376), mixada com a voz. Sem `data-audio-ambiente`, evitando duplicação. |
| Reel | v3 em MP4 1080×1920, 30 fps, 26 s, narração + trilha nova, com capa JPG. v1 e v2 preservados; **não** foi criado registro na fila do Instagram. |
| Revisão visual | Gravuras e fundo baseados nas referências do jogo fornecidas pelo usuário. Impasse, Conselho, guerra e fundo aprovados; uso da arte dos selos autorizado na atualização. Quadros do v3 inspecionados; vídeo final aguarda avaliação do usuário. |
| Revisão auditiva | Narração aprovada pelo usuário. Nesta execução houve somente medição técnica do áudio, sem escuta do Reel. |
| Imagens do artigo | Capa com o impasse e quatro gravuras no corpo: impasse, guerra, Conselho e selos. Backups preservados. |

## Narração (ElevenLabs)

Gerada na conta do usuário, pela interface Text to Speech. Voz **Cornelius**, modelo **Eleven Multilingual v2**. Parâmetros presentes no nome dos arquivos baixados: speed 0,85, stability 60, similarity 75, style exaggeration 25, speaker boost, MP3 44,1 kHz 128 kbps.

- Blog: 542 caracteres, 43,4 s.
- Reel: 225 caracteres, 23,7 s.
- Consumo: 767 créditos (de 10.000 para 9.233).

Os textos usados estão em `conteudo-planejado/saga-darksiders/cap-01-o-mundo-de-darksiders/` (`narracao-blog.txt` e `narracao-reel.txt`) e os MP3 originais em `.../audio/`. Os arquivos baixados também permanecem em `Downloads`.

Formato dos textos: uma frase curta por linha, reticências nas pausas e linha em branco entre blocos, como nas narrações do Diablo.

## Música de fundo

### Atual — v3

Faixa **Caves of Dawn**, de GuilhermeBernardes, Pixabay id 10376, já disponível na biblioteca local em `public/uploads/audio/pixabay_10376.mp3`. Seleção aprovada pelo usuário com base nos metadados de instrumental/orquestral/ambiente sombrio; não houve escuta pela IA. Fontes: [página da faixa](https://pixabay.com/music/ambient-caves-of-dawn-10376/) e [licença Pixabay](https://pixabay.com/service/license-summary/), consultadas em 12/10/2026.

O pedido de tentar uma trilha do jogo foi pesquisado: a [política de vídeos da THQ Nordic](https://thqnordic.com/legal/video-policy) permite vídeos com seu conteúdo, mas restringe distribuição de assets isolados. Não foi encontrada autorização específica para extrair a OST e usá-la também no player do blog. A alternativa Pixabay foi apresentada e aprovada; nenhuma OST foi extraída, comprada ou usada.

Mixagens novas, preservando os MP3 de voz e a trilha original:

- Reel: `public/uploads/reels/saga-darksiders-cap01/audio-v3/audio-mix-reel-v3.mp3`, 26 s.
- Blog: `audio-v3/narracao-mix-blog-v3.mp3`, 46 s, na pasta do capítulo; cópia em `public/uploads/posts/o-mundo-de-darksiders-ceu-inferno-e-o-equilibrio/audio/narracao-mix-v3.mp3`.
- Música a partir de 0 s, ganho 0,18; voz com atraso 400 ms; fade musical de entrada 0,7 s e saída 1,5 s. Compressão acionada pela voz: threshold 0,025, ratio 6, attack 40 ms, release 500 ms; `amix normalize=0` e limitador `limit=0.89:level=false`. MP3 estéreo 44,1 kHz, 192 kbps.
- Configuração e origem registradas em `verification-v3/music-selection.json`. O player aponta somente para a mixagem, sem carregar a antiga música em paralelo ou referenciar uma nova faixa isolada.

### Histórico — v1 e v2

Faixa **Dark Fantasy Ambient (Dungeon Synth)**, de DeusLower, Pixabay id 248213, do grupo `epic` da biblioteca (`docs/IMP-032-faixas-pixabay.json`), arquivo `public/uploads/audio/pixabay_248213.mp3`. É a mesma faixa que os Reels do Diablo usam no catálogo atual.

Licença conforme o manifesto: Pixabay Content License, uso comercial e edição permitidos, sem atribuição obrigatória, **proibido redistribuir a faixa isolada**. O artigo anteriormente referenciava a trilha separada (`amb-001.mp3`), igual ao padrão do artigo do Diablo. Esse arquivo foi preservado, mas deixou de ser usado no player do capítulo 1 com a mixagem v3.

A escolha foi feita pelo título e pelo grupo, **sem audição**. Alternativas da mesma biblioteca: "Caves of Dawn" (10376) e "Best Dramatic Music" (136824).

## Reel

> [!IMPORTANT]
> **Padrão Oficial dos Reels da Saga: Migração Mandatória para Motionmaxxing (15/10/2026)**
> Os testes e versões preliminares renderizados com o motor legado Canvas/Skia (`scripts/reels-konva/editorial.mjs`) foram **descontinuados e reprovados** por falta de animação nos textos (comportamento estático "estilo PowerPoint", sem tipografia cinética).
> Conforme alinhamento oficial e diretrizes em `docs/features/reels-motionmaxxing.md`, toda a produção de Reels da Saga Darksiders (Capítulos 1 a 6) utilizará exclusivamente a skill **`motionmaxxing`** (`C:\Users\WINDOWS\.claude\skills\motionmaxxing`), aplicando:
> 1. **Tipografia Cinética:** Palavras como foco central em `Cinzel` e `Cormorant Garamond`, animadas letra por letra com representação de sentido (luz e ordem celestial, brasa viva infernal e nanquim de pena no pergaminho).
> 2. **Deriva Lenta da Imagem:** Moldura com pan cinematográfico contínuo sobre a gravura aprovada, sem congelamentos.
> 3. **Renderização de Estúdio:** Chrome Headless com shutter de 180°, subframes (8x) e granulação orgânica de filme.

### Histórico Legado — v3 (Konva descontinuado)

As artes v2 foram substituídas após o usuário apontar falta de fidelidade à franquia, especialmente no Conselho. A etapa v3 usou capturas e modelos do jogo fornecidos pelo usuário para anjos, demônios e as três cabeças de pedra; concept art não foi usada como referência final desses modelos. O acabamento foi corrigido para desenho em tinta/hachuras sobre papel aparente, com pigmentos discretos, evitando aparência de captura impressa. Personagens variados, sem tropas clonadas. Os selos seguem a disposição circular de sete formas quadradas luminosas na cena final de *Darksiders Genesis*, redesenhadas como gravura; fissuras representam artisticamente sua ruptura, e não reproduzem literalmente um quadro do jogo.

- Amostras finais: `amostras-v3/livro-impasse-gravura-v3.png`, `livro-guerra-gravura-v3.png`, `livro-conselho-gravura-v3.png`, `livro-selos-gravura-v3.png` e `fundo-reel-gameplay-v3.png`.
- Artes utilizadas pelo vídeo: cópias em `images-v3/cena-1.png` até `cena-4.png` e `images-v3/fundo.png` na pasta do capítulo.
- Spec `spec-reel-cap01-v3.json`, mesmo perfil `darksiders-chronicle-v1`, duração 26 s e pausas [0, 5.5, 10.1, 17]. Código e perfis existentes não alterados nesta etapa.
- Entrega: `reel-cap01-v3.mp4`, `reel-cap01-v3.jpg` e manifesto gerado pelo pipeline. A pasta `verification-v3/` contém hashes, registros locais do artigo e quadros extraídos.

```sh
node scripts/reels-konva/editorial.mjs --spec public/uploads/reels/saga-darksiders-cap01/spec-reel-cap01-v3.json --output public/uploads/reels/saga-darksiders-cap01/reel-cap01-v3.mp4 --ffmpeg ffmpeg --ffprobe ffprobe
```

### Histórico — v1

- Mixagem: `public/uploads/reels/saga-darksiders-cap01/audio-mix-reel.mp3`. A trilha (início em 12 s, volume 0,22) entra por baixo da narração (atraso de 0,4 s), com limitador. Média −21,8 dB, pico −4,5 dB.
- Spec: `spec-reel-cap01-v1.json` na mesma pasta. Padrão `editorial: manual`, layout `chronicle`, tom `dark`, quatro cenas, `beatStarts` [0, 5,5, 10,1, 17,0] alinhados às pausas da fala (medidas com `silencedetect`).
- Render: `node scripts/reels-konva/editorial.mjs --spec ... --output ... --ffmpeg ffmpeg --ffprobe ffprobe`. Saída `reel-cap01-v1.mp4` e `reel-cap01-v1.jpg`.
- Imagens: quatro cenas geradas localmente (ComfyUI/SDXL, `E:\AI\en-image\gen.py --ratio blog`), sem texto e sem personagens da franquia.

### Visual v2 — livro do Equilíbrio

O v1 foi reprovado visualmente pelo usuário; sua narração foi aprovada e não foi regenerada. O v2 usa um fundo ilustrado com Céu dourado à esquerda, Inferno vermelho à direita e áreas escuras para texto. As quatro artes foram geradas pela ferramenta integrada da IA a partir da referência do Diablo e do livro aprovado: impasse, guerra, Conselho e sete selos quebrados. A ilustração permanece nas páginas; não há escrita artificial nem personagens reconhecíveis da franquia.

- Perfil: `darksiders-chronicle-v1`, implementado em `scripts/reels-review/darksiders-scene.mjs` e registrado por import e ramo novo em `scripts/reels-konva/scene.mjs`. Perfis existentes preservados.
- Perfil sem restrição a IDs de posts ou duração fixa: usa a spec manual, seus `beatStarts`, cenas e fundo obrigatório em `visualStyle.background`.
- Tipografia Georgia Bold nos títulos, Inter nos textos; rótulos `CÉU` / `INFERNO`; rodapé `CONHEÇA A HISTÓRIA · LINK NA BIO`. A fileira animada opcional de selos não foi implementada.
- Artes finais: `public/uploads/reels/saga-darksiders-cap01/images-v2/cena-1.png` até `cena-4.png` e `fundo.png`. As amostras aprovadas estão em `amostras-v2/` na mesma pasta.
- Spec: `public/uploads/reels/saga-darksiders-cap01/spec-reel-cap01-v2.json`, duração 26 s e `beatStarts` [0, 5.5, 10.1, 17]. Mesma `audio-mix-reel.mp3`, sem consumo de ElevenLabs.
- Entrega: `reel-cap01-v2.mp4`, `reel-cap01-v2.jpg` e manifesto gerado pelo pipeline, na pasta do capítulo.
- Evidências: `verification-v2/original-hashes.json`, quadros `cena-1.jpg` até `cena-4.jpg` e `folha-contato.jpg`.

Renderização (saída deve ser nova):

```sh
node scripts/reels-konva/editorial.mjs --spec public/uploads/reels/saga-darksiders-cap01/spec-reel-cap01-v2.json --output public/uploads/reels/saga-darksiders-cap01/reel-cap01-v2.mp4 --ffmpeg ffmpeg --ffprobe ffprobe
```

## Artigo

Tom e estrutura seguem o artigo-modelo do Diablo (bloco de áudio, blocos `content-block-note` e `content-block-highlight`, intertítulos curtos). O texto foi escrito a partir de duas fontes, registradas no próprio artigo:

- Game Informer, entrevista com os desenvolvedores (2011).
- Wikipedia, verbete da série.

Ressalvas de lore: a entrevista é anterior a *Darksiders II*; a ordem cronológica entre *II*, *III* e *Genesis* é discutida; não há nome oficial em português para o "Charred Council", então o artigo usa "o Conselho" e cita o nome original na primeira menção.

Observação: o botão do player mantém o texto "Ouvir narracao" (sem acento), igual ao artigo do Diablo, porque o JavaScript do site usa esse texto como padrão.

Em 12/10/2026, `scripts/en-blog-draft.php` atualizou as imagens do post #82 a partir de um payload montado com os campos atuais do próprio rascunho. A capa recebeu `images-v2/cena-1.png`; o corpo recebeu `images-v2/cena-3.png`. Todos os campos do registro, inclusive conteúdo, caminhos de áudio e status, foram comparados antes/depois e permaneceram iguais. As imagens antigas estão em `public/uploads/posts/o-mundo-de-darksiders-ceu-inferno-e-o-equilibrio/images/capa-v1-pre-book.png` e `img-001-v1-pre-book.png`; rollback: copiá-las de volta para `capa.png` e `img-001.png`. O texto alternativo anterior foi preservado com o conteúdo; ele ainda menciona selos na cena do Conselho.

Na atualização v3, também em 12/10/2026, o mesmo script oficial inseriu as **quatro imagens no corpo**:

| Arquivo | Posição e conteúdo |
|---|---|
| `capa.png` | Impasse, capa do artigo. |
| `img-001.png` | Impasse, após a abertura e antes de "Um impasse que ninguém consegue vencer". |
| `img-002.png` | Guerra, após a seção do impasse e antes de "O Conselho: a terceira força". |
| `img-003.png` | Três cabeças de pedra, após a seção do Conselho e antes de "O Equilíbrio: uma trégua com nome bonito". |
| `img-004.png` | Sete selos, após "Sete selos mantêm a paz" e antes de "O que vem depois". |

Alt e legendas foram atualizados para as imagens reais. A imagem anterior do Conselho foi retirada do HTML; não há duplicação do bloco antigo. O player agora usa `narracao-mix-v3.mp3` e não possui `data-audio-ambiente`. A comparação normalizada do HTML, descontando figuras e atributos de áudio, confirmou preservação do texto narrativo; todos os outros campos do registro permaneceram iguais, inclusive status de rascunho.

Backups da etapa anterior: `images/capa-v2-before-v3.png` e `images/img-001-v2-before-v3.png` na pasta do post. Snapshot antes/depois e payload local: `verification-v3/post-82-before.json`, `post-82-after.json`, `post-82-update.json`. Para rollback, restaurar o registro a partir do snapshot usando o script existente e restaurar as duas imagens pelos backups; não é necessário apagar as novas mídias sem referência. Voz, mixagem antiga, áudios antigos do artigo e Reels v1/v2 foram preservados por hash.

O snapshot do registro e o payload da atualização ficam somente no ambiente local, em `verification-v2/post-82-before.json` e `post-82-images-v2.json`, fora do commit. A primeira tentativa foi bloqueada pelo sandbox; a retomada autorizada funcionou sem alterar serviços, permissões ou credenciais.

## Testes executados

### Entrega v4 — imagens leves e música mais presente (12/10/2026)

O rascunho local #82 agora usa `capa.webp` e `img-001.webp` a `img-004.webp` na mesma pasta `uploads/posts/o-mundo-de-darksiders-ceu-inferno-e-o-equilibrio/images/`. Conversão via FFmpeg/libwebp, qualidade 82, 1280×720. Os cinco arquivos somavam 17.724.313 bytes; agora somam 1.495.392 bytes, redução de 91,56%. Os PNGs originais foram preservados. As quatro posições, legendas, textos alternativos e todo o texto do artigo permanecem iguais.

A trilha aprovada, “Caves of Dawn”, passou de ganho 0,18 para 0,2852808 (+4 dB) nas duas mixagens. Voz, atraso de 400 ms, ducking (threshold 0,025; ratio 6; attack 40 ms; release 500 ms), fades e limitador foram preservados. Esse aumento se aplica à música; o nível total é dominado pela narração. Não houve escuta pela IA nem regeneração da voz.

- Blog: `audio/narracao-mix-v4.mp3`, 46 s, média −22,8 dB e pico −4,9 dB. O player usa somente essa mixagem, sem segunda faixa de ambiente.
- Reel: `public/uploads/reels/saga-darksiders-cap01/reel-cap01-v4.mp4`, gerado com `spec-reel-cap01-v4.json` e `audio-v4/audio-mix-reel-v4.mp3`. As artes PNG v3 foram mantidas como fontes de alta qualidade para o vídeo.
- Reel validado: 26 s, 1080×1920, 30 fps, H.264/AAC 48 kHz, decodificação integral sem erros, média −23,3 dB e pico −5,8 dB. Sem clipping nos níveis medidos.
- WebPs inspecionados em folha de contato e Conselho em tamanho integral; quadros das quatro cenas do vídeo também inspecionados. Detalhes do desenho e composição preservados.
- Suíte PHP: 23 aprovações, zero falhas. Pipeline Node: 14 aprovações, zero falhas, usando o piloto de regressão v2 documentado abaixo.
- Links HTTPS do Reel, mixagem do blog e cinco WebPs: HTTP 200 e MIME correto, acessíveis pelo túnel `https://nerd.tfr-info.com.br`.
- Comparação do registro antes/depois: somente caminhos das imagens, capa/thumb e nome da mixagem alterados. Rascunho preservado; quatro imagens no corpo; nenhum placeholder ou áudio duplicado.
- Hashes dos PNGs, áudios anteriores, narrações originais e Reels anteriores preservados.

Evidências em `verification-v4/`: snapshots `post-82-before.json` e `post-82-after.json`, payload `post-82-update.json`, `original-hashes.json`, `technical-verification.json` e folhas de contato. Rollback: restaurar no registro apenas os caminhos anteriores conforme snapshot; os arquivos v3 continuam presentes. Não é necessário excluir as mídias v4. Não houve publicação ou agendamento.

As tarefas existentes do Forge #1211, #1222, #1213 e #1214 foram reutilizadas para esta entrega. A documentação oficial continua neste arquivo; nenhuma alteração no renderizador ou player foi necessária. Uploads e evidências continuam locais e ignorados pelo Git.

Verificações do v3 em 12/10/2026:

- `C:\xampp\php\php.exe scripts/verify-changes.php`: 23 verificações aprovadas, zero falhas, status `APROVADO COM SUCESSO!`.
- `node --test scripts/reels-konva/verify.mjs` com `EN_KONVA_PILOT=public/uploads/reels/saga-darksiders-cap01/verification-v2/regression-pilot.mp4`: 14 testes aprovados, zero falhas. Piloto de regressão já gerado com o código atual; pipeline não alterado nesta etapa.
- Reel v3: decodificação integral sem erros; H.264, 1080×1920, 30 fps; AAC 48 kHz; duração 26,000 s. `volumedetect`: média −23,4 dB, pico −5,7 dB.
- Mixagem do blog: 46,000 s, média −22,8 dB, pico −4,9 dB. A voz foi reutilizada, não regenerada; não houve escuta pela IA.
- Quadros em 2, 7,5, 12,1 e 19 s inspecionados na folha de contato `verification-v3/folha-contato.jpg`.
- Post #82: quatro imagens, capa e mixagem corretas por hash; ausência de placeholders e de segunda faixa no player; texto narrativo e campos fora do conteúdo preservados.
- Cinco imagens do post e mixagem responderam HTTP 200 no Apache local, com MIME `image/png` e `audio/mpeg`.
- Reels v1/v2, mixagem antiga, narrações originais, música fonte e áudios antigos do artigo com SHA-256 preservado.

Verificações do v2 em 12/10/2026:

- Decodificação integral com `ffmpeg -v error -i ... -f null -`, sem erro. ffprobe: H.264, 1080×1920, 30 fps, AAC 48 kHz, duração 26,000 s.
- `volumedetect`: média −21,8 dB, pico −4,5 dB. Não houve escuta do áudio.
- Quadros extraídos em 2, 7,5, 12,1 e 19 s; textos e imagens inspecionados na folha de contato.
- Reel v1, sua capa, mixagem, dois MP3 originais e áudios do artigo preservados por SHA-256.
- Perfil exercitado com outro `articleId` e duração 32 s; quatro cenas selecionadas corretamente e renderização do mesmo quadro determinística. Evidências e hashes adicionais do fundo, perfil e Georgia Bold registrados em `verification-v2/technical-verification.json`.
- `C:\xampp\php\php.exe scripts/verify-changes.php`: 23 verificações aprovadas, 0 falhas, status `APROVADO COM SUCESSO!`.
- O primeiro `node --test scripts/reels-konva/verify.mjs` teve 13 aprovações e 1 falha: o manifesto do piloto antigo guarda o hash anterior de `scene.mjs`. Não foram alterados o teste, o piloto ou seu manifesto. Para verificar o código atual, um novo piloto foi renderizado em `verification-v2/regression-pilot.mp4`, usando `scripts/reels-konva/pilot.json`; com `EN_KONVA_PILOT` apontando para esse arquivo, a suíte passou em **14 testes, 0 falhas**. O comando sem essa variável continua usando o piloto antigo e seu manifesto desatualizado.

Verificações da entrega inicial (v1):

- MP3 de narração e mixagem: duração, codec e taxa conferidos com ffprobe.
- Reel: decodificação integral sem erro, 1080×1920, 30 fps, H.264 + AAC 48 kHz, duração 26,0 s, áudio com nível medido; quadros das 4 cenas inspecionados.
- Rascunho: gravado no banco local; sem placeholders de imagem restantes; caminhos de capa, imagem do corpo e dos dois áudios conferidos no registro.
- Arquivos servidos pelo Apache local: `nar-001.mp3`, `amb-001.mp3`, `capa.png` e `img-001.png` responderam 200 com o tipo correto.

## Limitações

- **Não foi feita a escuta** da voz, da música nem do Reel. Só medi duração e nível.
- O editor do admin exige login e **não foi aberto**; a página pública do rascunho responde "post indisponível", como esperado. O player e a aparência do artigo ainda precisam ser vistos no admin.
- No repositório, os Reels do Diablo usam só a trilha musical nas specs. Não encontrei no projeto uma faixa de voz neles, nem o registro de como a narração foi produzida. A mixagem com narração deste piloto é nova.
- O Reel não foi agendado nem vinculado ao artigo na fila do Instagram.
- Nada foi publicado em stage ou produção, e não há sincronização de conteúdo.

## Como repetir nos próximos capítulos

1. Verificar a lore em fontes primárias e registrar as ressalvas.
2. Escrever artigo, narração do blog (~540 caracteres) e narração do Reel (~230).
3. Gerar as duas narrações no ElevenLabs com a mesma voz e os mesmos parâmetros e conferir a duração com ffprobe.
4. Gerar capa e imagem de corpo, montar o JSON em `.claude/skills/post-blog/saidas/` e criar o rascunho com `php scripts/en-blog-draft.php`.
5. Copiar `nar-001.mp3` e `amb-001.mp3` para `public/uploads/posts/{slug}/audio/`.
6. Medir as pausas da narração do Reel, mixar com a trilha e renderizar com `scripts/reels-konva/editorial.mjs`.
7. Revisar e publicar só com aprovação, seguindo o fluxo de `docs/templates/planejamento-posts.md`.

Para repetir o **visual v2** nos capítulos 2 a 6, reutilizar `darksiders-chronicle-v1`, o fundo e uma referência do livro aprovado, gerando artes distintas para cada cena. Criar uma nova spec e novas saídas por capítulo; manter `editorial: manual`, `layout: chronicle`, `tone: dark`, `presentation: story` e `visualStyle.background`. Adaptar `beatStarts` à narração existente de cada capítulo: começar em zero, mínimo de 4 s entre cenas e mínimo de 4 s no fecho. Duração inteira dentro dos limites do pipeline; áudio deve cobrir `audioStart + duration`. Validar o encaixe de todos os textos antes do render, extrair quadros e verificar áudio, formato, decodificação e hashes. Nenhum capítulo seguinte foi produzido nesta entrega.

Para a continuidade, usar as referências **v3**, e não as artes genéricas v2. Primeiro conferir aparência pela gameplay; converter em desenho de livro antigo com hachuras e papel visível, preservando anatomia e equipamento. Variar os personagens de uma mesma facção. Mostrar uma arte por vez e aguardar avaliação. Inserir todas as imagens planejadas no artigo com alt/legendas correspondentes. Para a música, preservar a voz, verificar licença e entregar mixagem única no blog; validar níveis e mostrar a reprodução ao usuário, sem afirmar escuta não realizada.

## Documentação oficial e acompanhamento

Este arquivo é a fonte oficial exibida em **Base Técnica → Mudanças**: `LocalDocsController::changesViewData()` carrega os documentos de `docs/features/`, e `KnowledgeHubController` usa esses dados na aba `mudancas`. Não há registro paralelo de conteúdo para atualizar; README: N/A.

Fonte e interface oficial validadas: `changesViewData(true)` retornou o conteúdo exato deste arquivo, e a renderização de `site/local-changes` apresentou o perfil novo e os resultados atualizados. Não foi necessário modificar controllers ou duplicar documentação.

Forge, projeto 4: tarefas existentes #1213 (testes) e #1214 (documentação) reutilizadas; criadas #1221 `[Darksiders] - Criar perfil visual do Reel` e #1222 `[Darksiders] - Refazer Reel e imagem do cap. 1`. As quatro foram concluídas somente após as verificações. O acesso local funcionou na retomada fora do sandbox, sem iniciar servidor.

Push do código e artefatos para o GitHub foi autorizado pelo usuário na retomada. Isso não publica o artigo, não sincroniza o banco e não agenda o Reel. Não houve publicação em stage/produção nem registro em `instagram_posts`.

O commit desta entrega inclui o perfil novo, seu registro no pipeline e este documento. Mídias, specs, backups e evidências em `public/uploads/` continuam locais, conforme o `.gitignore` existente; não houve alteração dessa política nem inclusão forçada de uploads no Git. O piloto de regressão e payloads temporários também permanecem fora do commit.

Na etapa v3 foram reutilizadas/reabertas #1211 (música), #1222 (imagens e Reel), #1213 (testes) e #1214 (documentação), evitando duplicidades. O commit v3 atualiza esta fonte oficial; código do renderizador e do player não precisaram de alteração. Mídias e dados do post seguem apenas no ambiente local; push de documentação não equivale a publicação/sincronização do conteúdo.

---

## Capítulo 2: Os Nephilim (Produzido em 12/10/2026)

### Estado da entrega
- **Artigo (blog):** Rascunho no banco local, post #83, slug `os-nephilim-a-raca-proibida-de-darksiders`, categoria `games`. 4 imagens no corpo (`img-001.webp` a `img-004.webp`) e capa (`capa.webp`).
- **Narração do blog:** Voz Cornelius (Eleven Multilingual v2), 48,56 s, mixada com a trilha *Caves of Dawn* (Pixabay 10376, ganho 0,285, ducking e limiter, duração 51 s) em `uploads/posts/os-nephilim-a-raca-proibida-de-darksiders/audio/nar-001.mp3`.
- **Reel:** v1 em MP4 1080×1920, 30 fps, 26 s, gerado com perfil `darksiders-chronicle-v1` em `uploads/reels/saga-darksiders-cap02/reel-cap02-v1.mp4`.
- **Ilustrações:** 4 gravuras no estilo de livro antigo em nanquim/hachuras sobre pergaminho:
  1. Criação dos Nephilim por Lilith a partir de cinzas celestiais e abissais.
  2. Absalom com sua armadura oficial e machado de guerra liderando a horda dos Nephilim.
  3. Os Quatro Cavaleiros no massacre do Éden.
  4. Morte contemplando o amuleto das almas no Éden diante de Absalom derrotado.
- **Áudios originais:** Preservados em `conteudo-planejado/saga-darksiders/cap-02-os-nephilim/audio/`.

---

## Capítulo 3: Os Quatro Cavaleiros (Produzido em 12/10/2026)

### Estado da entrega
- **Artigo (blog):** Rascunho no banco local, post #84, slug `os-quatro-cavaleiros-de-darksiders`, categoria `games`. 4 imagens no corpo (`img-001.webp` a `img-004.webp`) e capa (`capa.webp`).
- **Narração do blog:** Voz Cornelius (Eleven Multilingual v2), 44,33 s, mixada com a trilha *Caves of Dawn* (Pixabay 10376, ganho 0,285, ducking e limiter, duração 50 s) em `uploads/posts/os-quatro-cavaleiros-de-darksiders/audio/nar-001.mp3`.
- **Reel:** v1 em MP4 1080×1920, 30 fps, 26 s, gerado com perfil `darksiders-chronicle-v1` em `uploads/reels/saga-darksiders-cap03/reel-cap03-v1.mp4`.
- **Ilustrações:** 4 gravuras no estilo de livro antigo em nanquim/hachuras sobre pergaminho aberto com velas e penas, com fidelidade canônica rigorosa aos designs de Joe Madureira e gameplay:
  1. Os Quatro Cavaleiros reunidos em formação épica diante do Conselho das Cinzas.
  2. Morte (máscara óssea esculpida, peito atlético nu com correias, cabelos negros soltos, foices duplas, SEM capuz de ceifador) e Guerra (capuz vermelho, olhos cianos, armadura colossal e Chaoseater) em combate conjunto.
  3. Fúria (armadura negra pontiaguda, rabo de cavalo flamejante magenta e chicote Scorn) e Conflito (máscara metálica lisa, sobretudo longo de couro e pistolas gêmeas Mercy e Redemption disparando) em ação letal.
  4. A vigília dos Quatro Cavaleiros no desfiladeiro cósmico observando o Sétimo Selo intacto nos céus tempestuosos.
- **Áudios originais:** Preservados em `conteudo-planejado/saga-darksiders/cap-03-os-quatro-cavaleiros/audio/`.
- **Página de prévia mobile:** `public/uploads/previews/darksiders-cap03/index.html` (com player HTML5 do Reel e player de áudio do blog).

---

## Capítulo 4: O Apocalipse Antecipado (Produzido em 13/10/2026)

### Estado da entrega
- **Artigo (blog):** Rascunho no banco local, post #85, slug `o-apocalipse-antecipado-de-darksiders`, categoria `games`. 4 imagens no corpo (`img-001.webp` a `img-004.webp`) e capa (`capa.webp`), formato WebP 1280×720 (q82).
- **Narração do blog:** Voz Cornelius (Eleven Multilingual v2, 23,72 s), mixada com a trilha oficial *Caves of Dawn* (Pixabay 10376, ganho 0,285, ducking, limiter, duração 27,5 s) em `uploads/posts/o-apocalipse-antecipado-de-darksiders/audio/nar-001.mp3`. Áudios originais preservados em `conteudo-planejado/saga-darksiders/cap-04-o-apocalipse-antecipado/audio/`.
- **Foco de entrega:** Artigo completo no blog com áudio narrado e gravuras medievais (sem Reel e sem post para o Instagram, conforme alinhamento com o usuário).
- **Ilustrações:** 4 gravuras no estilo de livro antigo em nanquim/hachuras sobre pergaminho aberto com velas e penas, com fidelidade canônica rigorosa aos designs de Joe Madureira e gameplay:
  1. **Cena 1 (A Queda da Terra):** Chuva de meteoros sobre arranha-céus modernos e colisão das tropas da Hellguard (anjos em armaduras góticas de mármore e ouro com asas mecânicas de lâminas afiadas) contra a Legião Demoníaca (demônio colossal com garras de osso acorrentadas e demônios de pedra).
  2. **Cena 2 (O Confronto de Titãs):** O arcanjo Abaddon em investida aérea com espada flamejante contra o colossal general demônio Straga em rocha vulcânica e veios de lava em plena cidade humana.
  3. **Cena 3 (A Solidão de Guerra):** Guerra em pose dinâmica sobre o corcel fantasma Ruin empinando em chamas vivas (reprodução fiel da arte oficial de Joe Madureira), com a Tremor Gauntlet, Chaoseater e capuz carmesim nas ruínas solitárias da metrópole.
  4. **Cena 4 (O Julgamento e o Vigia):** As três colossais cabeças de pedra do Conselho das Cinzas (*The Charred Council*) julgando Guerra acorrentado, com The Watcher oficial de Joe Madureira (entidade esguia de sombra flamejante, couraça segmentada, cauda ectoplasmática e fitas rúnicas) atado ao seu braço.
- **Página de prévia mobile:** `public/uploads/previews/darksiders-cap04/index.html` (com cards das 4 cenas em alta definição e links diretos).
- **Suíte de testes:** 23 verificações aprovadas, 0 falhas (`scripts/verify-changes.php`).

---

## Capítulo 5: A Jornada de Morte (Produzido em 15/10/2026)

### Estado da entrega
- **Artigo (blog):** Rascunho no banco local, post #87, slug `a-jornada-de-morte-em-darksiders`, categoria `games`. 4 imagens no corpo (`img-001.webp` a `img-004.webp`) e capa (`capa.webp`), formato WebP 1280×720 (q82) padronizado.
- **Foco de entrega:** Artigo completo no blog com gravuras medievais e bloco de áudio narrado (sem Reel e sem post para o Instagram, conforme alinhamento com o usuário).
- **Narração do blog:** Voz Cornelius (Eleven Multilingual v2, 40,07 s), mixada com a trilha oficial *Caves of Dawn* (Pixabay 10376, ganho 0,285, ducking automático via sidechain, limiter e fade-out, duração 43,5 s) em `uploads/posts/a-jornada-de-morte-em-darksiders/audio/nar-001.mp3`. Áudios originais e mixados preservados em `conteudo-planejado/saga-darksiders/cap-05-a-jornada-de-morte/audio/`.
- **Referências oficiais do usuário:** Catalogadas em `conteudo-planejado/saga-darksiders/referencias-oficiais/` (`morte-busto-mascara.png`, `morte-corpo-inteiro-armadura.png`, `morte-foices-duplas.png`, `morte-despair-estatua.png`).
- **Ilustrações:** 4 gravuras no estilo de livro antigo em nanquim/hachuras sobre pergaminho aberto com velas e penas, com fidelidade canônica estrita às referências oficiais fornecidas pelo usuário:
  1. **Cena 1 (A Partida em Desespero):** Morte montado no corcel fantasma Despair (chamas verde-ciano/turquesa, costelas e ossos aparentes, sem capuz de tecido, máscara de marfim esculpida com olhos âmbar e fendas de chamas, cabelos negros longos soltos, foice Harvester e o corvo Dust voando) diante da colossal Árvore da Vida.
  2. **Cena 2 (O Flagelo da Corrupção):** Morte em salto acrobático desferindo golpes letais com foices duplas curvas de combate contra a Corrupção nas Terras da Forja (viscoso lodo negro com cristais pontiagudos amarelos e dourados incandescentes incrustados em um golem de pedra).
  3. **Cena 3 (O Julgamento dos Reis Caídos):** Morte de pé, altivo e desafiador no Reino dos Mortos perante o colossal Rei dos Ossos (*Lord of Bones*), em seu Trono Eterno esculpido em milhares de crânios humanos e ossos sob navios espectrais flutuantes.
  4. **Cena 4 (O Sacrifício Supremo no Poço das Almas):** Morte saltando de peito aberto no vórtice de energia primordial do Poço das Almas (*Well of Souls*), com o amuleto das almas no peito rachando e liberando feixes luminosos esmeralda/dourados para ressuscitar a humanidade e redimir seu irmão Guerra.
- **Página de prévia mobile:** `public/uploads/previews/darksiders-cap05/index.html` (com cards das 4 cenas em alta definição, layout responsivo e links diretos).
- **Suíte de testes:** 23 verificações aprovadas, 0 falhas (`scripts/verify-changes.php`).

---

## Capítulo 6: A Fúria do Equilíbrio (Produzido em 15/10/2026)

### Estado da entrega
- **Artigo (blog):** Rascunho no banco local, post #89, slug `a-furia-do-equilibrio-em-darksiders`, categoria `games`. 4 imagens no corpo (`img-001.webp` a `img-004.webp`) e capa (`capa.webp`), formato WebP 1280×720 (q82) padronizado.
- **Foco de entrega:** Artigo completo no blog com gravuras medievais e bloco de áudio narrado (sem Reel e sem post para o Instagram, conforme alinhamento com o usuário).
- **Narração do blog:** Voz Cornelius (Eleven Multilingual v2, 44,98 s), mixada com a trilha oficial *Caves of Dawn* (Pixabay 10376, ganho 0,285, ducking automático via sidechain, limiter e fade-out, duração 48,5 s) em `uploads/posts/a-furia-do-equilibrio-em-darksiders/audio/nar-001.mp3`. Áudios originais e mixados preservados em `conteudo-planejado/saga-darksiders/cap-06-a-furia-do-equilibrio/audio/`.
- **Referências oficiais do usuário:** Catalogadas em `conteudo-planejado/saga-darksiders/referencias-oficiais/` (`charred-council-oficial.png`, `strife-corpo-inteiro.png`, `strife-joe-mad.png`, `strife-estatua.png`, `strife-sketch-mad.png`).
- **Ilustrações:** 4 gravuras no estilo de livro antigo em nanquim/hachuras sobre pergaminho aberto com velas e penas, com fidelidade canônica estrita às referências oficiais fornecidas pelo usuário:
  1. **Cena 1 (A Audiência de Fúria e o Chicote Scorn):** Fúria em sua armadura negra pontiaguda de placas afiadas com rabo de cavalo flamejante magenta/violeta ondulando, empunhando o chicote de lâminas segmentadas *Scorn* diante das três cabeças colossais de rocha vulcânica do Conselho das Cinzas (*The Charred Council*), com olhos triangulares de lava e bocas abertas em fornalhas concêntricas de magma ardente.
  2. **Cena 2 (O Combate nas Ruínas da Terra):** Fúria em salto acrobático desferindo golpes de chicote com faíscas arcanas contra a titânica abominação do pecado Ira (*Wrath*) em pesada armadura de ferro espinhoso e espadas gigantescas, entre arranha-céus desmoronando e escombros da metrópole terrena.
  3. **Cena 3 (A Revelação da Traição):** Fúria de pé em choque e indignação ao presenciar a falsa Vigia desmascarar sua verdadeira forma como a macabra feiticeira esquelética Inveja (*Envy*), erguendo no alto o *Talismã dos Pecados* incandescente que libera feixes e espíritos das abominações aprisionadas em magia esmeralda e dourada.
  4. **Cena 4 (A Protetora da Humanidade e a Guarda de Conflito):** Fúria guiando com nobreza os humanos sobreviventes em direção ao portal luminoso de refúgio, enquanto o quarto Cavaleiro, Conflito (*Strife* oficial de Joe Madureira), monta guarda vigilante com sua máscara biomecânica branca, cabelos negros espetados, lenço e capa roxa esfarrapada esvoaçante, armadura muscular segmentada, bandoleira de balas e suas lendárias pistolas gêmeas *Mercy* e *Redemption* fumegantes.
- **Página de prévia mobile:** `public/uploads/previews/darksiders-cap06/index.html` (com cards das 4 cenas em alta definição, layout responsivo e links diretos).
- **Suíte de testes:** 23 verificações aprovadas, 0 falhas (`scripts/verify-changes.php`).

---

## Conclusão da Saga Darksiders (6 Capítulos Finalizados):
A saga completa de 6 capítulos está 100% produzida e catalogada no ecossistema local do Estratégia Nerd:
1. **Capítulo 1:** *O mundo de Darksiders: Céu, Inferno e o Equilíbrio explicados* (Post #82)
2. **Capítulo 2:** *Os Nephilim: a raça proibida de Darksiders explicada* (Post #83)
3. **Capítulo 3:** *Os Quatro Cavaleiros: quem são os executores do Equilíbrio em Darksiders* (Post #84)
4. **Capítulo 4:** *O Apocalipse Antecipado: a conspiração que condenou a Terra em Darksiders* (Post #85)
5. **Capítulo 5:** *A Jornada de Morte: o sacrifício do primogênito em Darksiders II* (Post #87)
6. **Capítulo 6:** *A Fúria do Equilíbrio: a caçada aos Sete Pecados em Darksiders III* (Post #89)




