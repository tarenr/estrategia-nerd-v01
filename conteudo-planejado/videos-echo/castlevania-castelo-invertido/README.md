# Castlevania: Symphony of the Night — O Castelo Invertido — pacote de origem (vídeo da série do Echo)

> Pacote montado em 01/11/2026 (Forge #1800) seguindo `docs/governanca/molde-serie-echo.md`, seção 11.
> Serve para **refazer, corrigir ou reaproveitar** o vídeo. O vídeo publicado **não** fica aqui.
> Detalhes da produção, decisões e textos: `roteiro.md`.

## Onde está o publicado

| O quê | Onde |
|---|---|
| Vídeo final 1080×1920, 60 fps, 44,6 s, -16 LUFS (H.264 + AAC, ~23 MB) | `public/uploads/reels/castlevania-castelo-invertido/reel.mp4` |
| Capa JPEG 1080×1920 (opção A; cópia aqui em `v2/capa/capa_a.jpg`) | `public/uploads/reels/castlevania-castelo-invertido/capa.jpg` |
| YouTube Shorts | fila local `youtube_videos` item **#79**, vídeo `F-pa2cBEm8o`, público em **02/11/2026 18:30** |
| Instagram Reels | fila local `instagram_posts` post **#65058**, **04/11/2026 18:30** |

## O que o usuário aprovou (escolhas deste vídeo, não padrão da série)

- Locução de 44,57 s com a voz `pt-BR-AntonioNeural` (`audio/`); música Bloody Tears a partir de 0:22.
- Gameplay 4:3 no monitor do quarto do Manifesto, zoom de 3,33× de 3,94 a 8,88 s e recuo de 39,80 a 41,60 s.
- Atuação copiada do Manifesto, controle 3D visto de frente, saída para a frente com a Propulsão Matrix (linha "Atuação do Echo" do `roteiro.md`).
- Legenda igual à do Manifesto, no alto (Y 270) e entre X 90 e 925; capa "O SEGREDO DEPOIS DO FINAL" (opção A).
- Fantasia de Alucard (`figurino-alucard-v1/`, Forge #1773): pronta, mas **não** usada neste vídeo.

## Conteúdo do pacote

| Pasta / arquivo | O quê |
|---|---|
| `roteiro.md` | locução oficial, decisões, render, legenda, áudio, capa e publicação |
| `audio/` | locução aprovada (`locucao_corrigida.*`), tempos por palavra (`whisper_palavras_locucao_corrigida.json`) e scripts |
| `v2/quadros.html` | composição que o render usa (`window.__seek`; aceita `?offset=` e `?dur=`) |
| `v2/cenas_v2.js`, `v2/timeline-v2.cjs` | cenas, câmera e ligação da atuação |
| `v2/vendor/` | motor do Echo, quarto (`setup_desenho.js`), atuação (`echo-atuacao-sala.js`) e propulsão (`echo-propulsao-matrix.js`) |
| `v2/assets/` | `gameplay_sync.mp4` (montagem do jogo), `trilha_bloody_tears.mp3`, `audio_mixado_preview.mp3` (mixagem final antes do nivelamento), `orig_title_15s_cropped.png` |
| `v2/render_janela_v2.py` | render em pedaços de 1 s (OneTake, 60 fps, desfoque de movimento) |
| `v2/legenda/` | scripts da legenda (`montar_legenda_texto.py`, `gerar_estados.py`), texto, estados e `estados.txt` (ffconcat) |
| `v2/capa/` | as 3 opções de capa (A escolhida) |
| `v2/pranchas/`, `v2/prancha-frenetica/`, `v2/selecao-gameplay-bruto/prancha-selecao-com-mapa.png`, `v1/pranchas/` | pranchas aprovadas e reprovadas |
| `v1/fonts/` | Orbitron 700 (usada pela legenda e pela capa) |
| `v2/reprovadas/cenas_v2_gelatina.js` | versão reprovada ("gelatina"), de onde veio o desenho do controle 3D |
| `figurino-alucard-v1/` | fantasia de Alucard (não usada) |

## Como refazer

1. **Prévia rápida (540×960, 30 fps):** abrir `v2/quadros.html` pelo Playwright e chamar `window.__seek(t)` quadro a quadro (método usado na sessão de 01/11; leva ~3 min).
2. **Render final:** `python v2/render_janela_v2.py --de 0 --ate 44.57 --rotulo final` (≈12 min; sai em `v2/renders/final/castlevania_final_mudo_1080x1920.mp4`).
3. **Legenda:** `python v2/legenda/montar_legenda_texto.py` e depois `python v2/legenda/gerar_estados.py` (≈6 s).
4. **Áudio:** nivelar `v2/assets/audio_mixado_preview.mp3` para -16 LUFS com pico até -1,5 dB (`loudnorm` em duas passagens).
5. **Montagem:** de dentro de `v2/legenda/`: `ffmpeg -i <mudo> -f concat -safe 0 -i estados.txt -i <audio> -filter_complex "[1:v]fps=60[sub];[0:v][sub]overlay=0:0:format=auto,format=yuv420p[v]" -map "[v]" -map 2:a -c:v libx264 -crf 18 -r 60 -c:a aac -b:a 256k -movflags +faststart -t 44.57 <saida>`.

## O que foi apagado (01/11/2026, 407,8 MB; pasta de 468 MB para 60 MB)

Prévias superadas (53,5 MB), `v2/renders/` com os pedaços, a versão sem som e o final idêntico ao publicado (81,8 MB), quadros e testes soltos em `v2/assets/` e o `gameplay_sync_anterior.mp4` (110,6 MB), `validacao*` (34,5 MB), `selecao-*` exceto a prancha da seleção (71,2 MB), `v2/quadros/` (9,5 MB), `v2/_frames/` (4,0 MB), quadros brutos da prancha frenética (7,7 MB), mídias de `v2/reprovadas/` (24,3 MB) e `v1` exceto fontes e pranchas (10,7 MB). Teste do pacote depois da limpeza: quadros de 1, 20 e 42,5 s com hash idêntico ao de antes, 0 erros; `reel.mp4` e `capa.jpg` publicados decodificam sem erro.
