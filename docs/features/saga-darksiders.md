# Saga Darksiders — piloto do capítulo 1 (atualizado em 12/10/2026)

Saga de 6 capítulos no formato da saga do Diablo: artigo de blog com narração e música de fundo, mais um Reel narrado por capítulo. Esta entrega cobre **somente o capítulo 1**, "O mundo de Darksiders", como piloto. Os capítulos 2 a 6 só começam depois da aprovação. A pauta completa está em `conteudo-planejado/saga-darksiders/pauta.md`.

## Estado da entrega

| Item | Estado |
|---|---|
| Artigo (blog) | Rascunho no banco **local**, post #82, slug `o-mundo-de-darksiders-ceu-inferno-e-o-equilibrio`, categoria `games`. Nada em stage ou produção. |
| Narração do blog | `nar-001.mp3`, 43,4 s, no bloco `en-audio-block`. |
| Música de fundo do blog | `amb-001.mp3`, 107 s, atributo `data-audio-ambiente`. |
| Reel | v2 em MP4 1080×1920, 30 fps, 26 s, narração + trilha, com capa JPG. v1 preservado; **não** foi criado registro na fila do Instagram. |
| Revisão visual | Livro, fundo e três artes restantes aprovados pelo usuário. Quadros do v2 inspecionados e folha de contato apresentada; aprovação do vídeo final ainda não registrada. |
| Revisão auditiva | Narração aprovada pelo usuário. Nesta execução houve somente medição técnica do áudio, sem escuta do Reel. |
| Imagens do artigo | Capa com o impasse e imagem do corpo com o Conselho, ambas em livro antigo. Backups das imagens anteriores preservados. |

## Narração (ElevenLabs)

Gerada na conta do usuário, pela interface Text to Speech. Voz **Cornelius**, modelo **Eleven Multilingual v2**. Parâmetros presentes no nome dos arquivos baixados: speed 0,85, stability 60, similarity 75, style exaggeration 25, speaker boost, MP3 44,1 kHz 128 kbps.

- Blog: 542 caracteres, 43,4 s.
- Reel: 225 caracteres, 23,7 s.
- Consumo: 767 créditos (de 10.000 para 9.233).

Os textos usados estão em `conteudo-planejado/saga-darksiders/cap-01-o-mundo-de-darksiders/` (`narracao-blog.txt` e `narracao-reel.txt`) e os MP3 originais em `.../audio/`. Os arquivos baixados também permanecem em `Downloads`.

Formato dos textos: uma frase curta por linha, reticências nas pausas e linha em branco entre blocos, como nas narrações do Diablo.

## Música de fundo

Faixa **Dark Fantasy Ambient (Dungeon Synth)**, de DeusLower, Pixabay id 248213, do grupo `epic` da biblioteca (`docs/IMP-032-faixas-pixabay.json`), arquivo `public/uploads/audio/pixabay_248213.mp3`. É a mesma faixa que os Reels do Diablo usam no catálogo atual.

Licença conforme o manifesto: Pixabay Content License, uso comercial e edição permitidos, sem atribuição obrigatória, **proibido redistribuir a faixa isolada**. O artigo serve a trilha como arquivo separado (`amb-001.mp3`), igual ao padrão do artigo do Diablo. Se isso for considerado redistribuição isolada, o caminho é entregar uma versão editada (recorte, fade ou mixagem).

A escolha foi feita pelo título e pelo grupo, **sem audição**. Alternativas da mesma biblioteca: "Caves of Dawn" (10376) e "Best Dramatic Music" (136824).

## Reel

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

O snapshot do registro e o payload da atualização ficam somente no ambiente local, em `verification-v2/post-82-before.json` e `post-82-images-v2.json`, fora do commit. A primeira tentativa foi bloqueada pelo sandbox; a retomada autorizada funcionou sem alterar serviços, permissões ou credenciais.

## Testes executados

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

## Documentação oficial e acompanhamento

Este arquivo é a fonte oficial exibida em **Base Técnica → Mudanças**: `LocalDocsController::changesViewData()` carrega os documentos de `docs/features/`, e `KnowledgeHubController` usa esses dados na aba `mudancas`. Não há registro paralelo de conteúdo para atualizar; README: N/A.

Fonte e interface oficial validadas: `changesViewData(true)` retornou o conteúdo exato deste arquivo, e a renderização de `site/local-changes` apresentou o perfil novo e os resultados atualizados. Não foi necessário modificar controllers ou duplicar documentação.

Forge, projeto 4: tarefas existentes #1213 (testes) e #1214 (documentação) reutilizadas; criadas #1221 `[Darksiders] - Criar perfil visual do Reel` e #1222 `[Darksiders] - Refazer Reel e imagem do cap. 1`. As quatro foram concluídas somente após as verificações. O acesso local funcionou na retomada fora do sandbox, sem iniciar servidor.

Push do código e artefatos para o GitHub foi autorizado pelo usuário na retomada. Isso não publica o artigo, não sincroniza o banco e não agenda o Reel. Não houve publicação em stage/produção nem registro em `instagram_posts`.

O commit desta entrega inclui o perfil novo, seu registro no pipeline e este documento. Mídias, specs, backups e evidências em `public/uploads/` continuam locais, conforme o `.gitignore` existente; não houve alteração dessa política nem inclusão forçada de uploads no Git. O piloto de regressão e payloads temporários também permanecem fora do commit.
