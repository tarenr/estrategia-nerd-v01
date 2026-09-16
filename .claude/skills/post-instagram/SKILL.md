---
name: post-instagram
description: Cria um post completo para o Instagram do @estrategia_nerd (portal Estrategia Nerd) - legenda no tom da conta + hashtags + variacoes de hook + a imagem do post. Use quando o usuario pedir "post", "legenda", "carrossel", "reel", "conteudo pro insta", ou invocar /post-instagram com um tema. Cobre posts de produto/afiliado, nostalgia/curiosidade geek, noticia/trailer e listas.
---

# Post do Instagram — Estrategia Nerd

Gera um post pronto pra revisar e publicar (publicacao e manual). Entrega:
**legenda + 3 variacoes de hook + hashtags + a imagem**.

## Passos

### 1. Entender o pedido
O usuario passa um tema. Ex.: "teclado mecanico Redragon K500", "trailer de GTA 6",
"nostalgia: Cavaleiros do Zodiaco", "top 5 jogos de terror".

Detecte o **tipo** (ver `estilo-estrategia-nerd.md`):
- **A) Produto/afiliado** — tem um produto especifico pra vender/indicar
- **B) Nostalgia/curiosidade geek** — algo retro, historia, "voce lembra de X?"
- **C) Noticia/trailer/lancamento** — novidade de filme/anime/game/tech
- **D) Lista** — "top N ..."
- **Curto/emocional** — frase de efeito

Se o tema for ambiguo, pergunte o tipo e (para produto) peca specs/link/foto.

Para B e C, se precisar de fatos (datas, nomes, specs), pesquise antes (WebSearch) —
o publico percebe erro factual.

### 2. Escrever a legenda
- Leia `estilo-estrategia-nerd.md` e `exemplos.md` ANTES de escrever.
- Siga o molde: hook → corpo (paragrafos curtos, linha em branco entre eles) →
  bullets (se produto/lista) → pergunta de engajamento → CTA (so produto) → hashtags.
- Sempre termina com pergunta ou CTA. Sempre inclui `#EstratégiaNerd` nas hashtags.
- Portugues BR informal, emoji alto, gíria gamer.

### 3. Gerar 3 variacoes de hook
So a primeira linha, 3 opcoes diferentes de abordagem (impacto / pergunta / numero).

### 4. Gerar a imagem

A imagem final e SEMPRE 1080x1350 (feed 4:5) e sai de 2 camadas:
1. **fundo** — ilustracao do SDXL (ou foto real, em post de produto).
2. **acabamento + texto** — o `compose.py` (Chrome headless) aplica color grade na
   paleta da marca, grao, light-leak, vinheta, moldura HUD (cantoneiras), e poe a
   headline pesada (extrusao 3D) + subtitulo + marca. O SDXL NUNCA escreve texto.

Regra da chamada por tipo de post (ver `estilo-estrategia-nerd.md`):
- **B / C / D** (nostalgia, noticia, lista): chamada **curta, 2 a 4 palavras**
  (`[trecho]` entre colchetes sai em ciano). Ex.: `[LAN HOUSE]`, `O RETORNO DOS
  [SAMURAIS]`. Chamada de 5+ palavras quebra em 4 linhas e fica feia — encurta.
  Opcional: `--subtitle "uma linha de apoio"`.
- **A** (produto): sem chamada — deixa o produto limpo, so a marca.
- **Curto/emocional**: sem chamada, imagem limpa.

**Prompt do fundo (SDXL)** — o que separa "post de portal geek" de "telemarketing":
- **1 sujeito forte e grande**, centralizado ou na regra dos tercos. NAO plano
  aberto lotado (o SDXL Turbo perde a geometria e vira bagunca).
- **Luz de recorte dramatica**, 1 fonte de luz clara, fundo com profundidade
  (bokeh / neblina / degrade), nao tudo no mesmo plano.
- **Espaco negativo reservado** no terco de baixo (ou de cima) pra chamada caber —
  descreva isso no prompt ("clear dark empty space in the lower third").
- Prompt em ingles, detalhado. Dois tons:
  - **estilizado / hype** (trailer, ficcao, hero shot): "cinematic key art, bold
    dramatic lighting, rich contrast, geek pop art, highly detailed, depth of field".
  - **nostalgia realista** (lan house, locadora, quarto anos 90/2000): "nostalgic
    cinematic photograph, warm practical lighting, shallow depth of field, early
    2000s, subtle film grain" + no `--negative` acrescente "neon, cyberpunk,
    futuristic, RGB lighting, cluttered, chaotic, dark, gloomy, creepy" (o SDXL
    Turbo puxa pra neon e pra escuro sozinho).
- NAO pedir texto/logo/letras no prompt (o negative ja bloqueia).
- **Telas (TV, monitor, celular) na cena:** o SDXL Turbo nao desenha jogo/interface
  — enche com "video de pessoas" (futebol, plateia, rosto). Solucoes: corte a tela
  fora do quadro / peca "screen cropped at the top edge", "intense screen glare
  hiding the content", ou "monitors facing away, backs of CRT monitors visible".
  E no `--negative`: "sports broadcast, crowd on screen, faces on screen,
  television show".
- `--negative "..."` SUBSTITUI o negative padrao inteiro — copie o default do
  gen.py e adicione, nao passe so as palavras novas.
- Gera 2 seeds, olha as duas, escolhe a melhor, DEPOIS compoe:
  ```
  E:\AI\ComfyUI\venv\Scripts\python.exe E:\AI\en-image\gen.py ^
    --prompt "<prompt>" --out "E:\AI\en-image\saidas\<slug>-bg.png" ^
    --ratio portrait --steps 12 --seeds 2
  ```
  Depois de escolher (`<slug>-bg-s1.png` ou `-s2.png`):
  ```
  E:\AI\ComfyUI\venv\Scripts\python.exe E:\AI\en-image\compose.py ^
    --bg "E:\AI\en-image\saidas\<slug>-bg-sN.png" ^
    --headline "[LAN HOUSE]" --subtitle "..." --grade cyan --frame on ^
    --out "<repo>\.claude\skills\post-instagram\saidas\<slug>.png"
  ```
  Ou, se topar a 1a seed sem escolher: `gen.py ... --headline "..." --final "<path>"`
  (gera 1 e ja compoe). ComfyUI sobe sozinho. 1a imagem da sessao ~200s (compila
  kernels), depois ~1 min por seed a 12 steps. Ratios: `square` 1024x1024,
  `portrait` 1024x1280, `story` 1024x1792, `landscape` 1280x1024.
- `compose.py`: `--grade cyan|purple|warm|none` (default cyan), `--frame on|off`
  (default on), `--position top|bottom`. Se o fundo ja veio otimo e colorido,
  `--grade none` pra nao achatar.

**Card de noticia / franquia** (tipo C de personagem, jogo, filme — quando a IA
NAO consegue desenhar o icone: Wolverine, Zelda, um game especifico...):
- 100% tipografico, sem IA. Fundo na cor da franquia + titulo gigante 3D + riscos
  de garra + data/plataforma. Igual aos cards "ORGULHO GEEK" / "RTX VS RX".
  ```
  E:\AI\ComfyUI\venv\Scripts\python.exe E:\AI\en-image\compose.py --news ^
    --title "WOLVERINE" --kicker "Lancamento" --meta "15 de setembro · so no PS5" ^
    --theme wolverine ^
    --out "<repo>\.claude\skills\post-instagram\saidas\<slug>.png"
  ```
  - `--theme`: `en` (padrao ciano/roxo), `wolverine`, `nvidia`, `amd`,
    `playstation`, `xbox`, `red`. Ou cor custom: `--accent "#f5b301" --accent2 "#1b1b1b"`.
  - `--title` `[trecho]` entre colchetes = cor accent. O titulo auto-encolhe pra caber.
  - `--kicker` (rotulo de cima, opcional), `--meta` (data/plataforma, opcional).
  - `--slash on|off` (3 riscos de garra, default on), `--frame on|off`.
- **"X VS Y" simples**: `--vs --title "A [VS] B" --accent "#.." --accent2 "#.."` — split
  diagonal chapado accent/accent2, "VS" branco no meio.
- **"X VS Y" com produtos** (ex.: GPU): `--gpus` — usa `assets/gpu-left.png` e
  `gpu-right.png` (recorta você via PIL das fotos), fundo = `assets/vs-scene.png`
  (cenario SDXL) com duotone verde-esq/vermelho-dir. Titulo no topo, produtos nos
  lados, marca embaixo. Passe `--bg <cena>` pra trocar o cenario.
- **Imagem pronta -> feed**: `--hero --bg <arte>` — arte no topo + faixa da marca EN
  embaixo (`--kicker`, `--meta`). Pra quando você ja tem a arte e so quer no 4:5.

**Card de texto simples** (listas "TOP N", frase de efeito — sem ilustracao e sem
ser franquia):
- Sem fundo de IA. So a chamada sobre o fundo navy + caveira marca d'agua:
  ```
  E:\AI\ComfyUI\venv\Scripts\python.exe E:\AI\en-image\compose.py ^
    --headline "TOP 5 JOGOS DE [TERROR]" ^
    --out "<repo>\.claude\skills\post-instagram\saidas\<slug>.png"
  ```
- Se a lista tiver que aparecer NA imagem (os 5 itens), monte um HTML proprio
  com a mesma identidade (assets em `E:\AI\en-image\assets\`: `skull.png`,
  `logo-badge.png`, `zekton.ttf`) e renderize com Chrome headless 1080x1350.

**Produto** (tipo A):
- Peca a **foto real do produto** ao usuario (a IA nao inventa o produto certo).
- Compoe a foto como fundo, sem chamada:
  `compose.py --bg "<foto>" --out "<slug>.png"` (so a marca no rodape).

Quando terminar tudo, pra liberar a VRAM: `E:\AI\ComfyUI\stop-comfyui.ps1`.
Saidas ficam em `.claude/skills/post-instagram/saidas/<slug>.png`.

### 5. Entregar
Mostre no chat: legenda final (bloco), as 3 variacoes de hook, as hashtags separadas,
e mande a imagem com SendUserFile.

### 6. Publicar (so quando o usuario mandar)
- Salve a legenda final em `saidas/<slug>.txt` (UTF-8).
- SO publica se o usuario responder algo inequivoco tipo "aprovado, pode publicar"
  / "publica" DEPOIS de ver o material. Nunca antes.
- Comando:
  ```
  E:\AI\ComfyUI\venv\Scripts\python.exe E:\AI\en-image\ig-post.py ^
    --image "saidas\<slug>.png" --caption "saidas\<slug>.txt"
  ```
  (`--dry-run` cria o container mas nao publica; `--keep` nao apaga o JPEG do FTP.)
- O script: PNG->JPEG -> FTP `public_html/ig/` (creds do `.env` do site) -> Graph API
  container -> `media_publish` -> imprime o link -> apaga o JPEG. Post unico so.
- Reporte o link do post pro usuario.

## Arquivos da skill

- `estilo-estrategia-nerd.md` — o guia de voz/estrutura (LEIA sempre)
- `exemplos.md` — legendas reais anotadas por tipo (referencia few-shot)
- `saidas/` — imagens geradas (nao versionado)
- Gerador local em `E:\AI\`:
  - `ComfyUI\` — ComfyUI + DreamShaper XL Turbo (venv com torch 2.8.0+cu128).
    `start-comfyui.ps1` / `stop-comfyui.ps1`.
  - `en-image\gen.py` — gera o fundo (SDXL) e, com `--headline`/`--final`, ja
    chama o compose.
  - `en-image\compose.py` — acabamento + texto -> PNG 1080x1350. Sem `--news`
    usa `template.html` (foto/ilustracao). Com `--news` usa `card-news.html`
    (card tipografico de franquia, tema de cor, riscos de garra).
  - `en-image\template.html`, `en-image\card-news.html` + `en-image\assets\`
    (skull.png, logo-badge.png, logo-lockup.png, zekton.ttf).
  - `en-image\ig-post.py` — publica 1 imagem no @estrategia_nerd (FTP Hostinger +
    Graph API). Le FTP do `.env` do site e `ig_id`/token do `.cache.json` do
    helper do Instagram. Nao versionado com segredo — nenhum segredo no .py.

## Regras

- Nunca publica sem o usuario aprovar aquele post especifico. Gera, mostra, espera o "publica".
- Nao inventa specs de produto nem datas de lancamento — pesquisa ou pergunta.
- Nao poe texto na imagem de IA (SDXL erra) — texto so em card HTML.
- Uma imagem por post por padrao; carrossel so se o usuario pedir (aí gera N cards).
