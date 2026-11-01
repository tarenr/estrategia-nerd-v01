# Castlevania: Symphony of the Night — O Castelo Invertido (Piloto #1)

> **Atualizado em 01/11/2026** (Forge #1783). Substitui a versão de 31/10/2026 (40,78 s), que tinha afirmações erradas
> ("física invertida", "derrota o Richter usando os óculos") e textos de post que não valem mais.
> **Série:** vídeo do Echo, um único arquivo vertical 1080×1920 para Instagram Reels e YouTube Shorts.
> **Pilar:** Clássicos Cult e Nostalgia (sexta-feira). **Publicação prevista:** sexta, 06/11/2026, 18h30.

## Locução aprovada

- **Duração:** 44,57 s. **Voz:** `pt-BR-AntonioNeural` (edge-tts), porque os créditos do ElevenLabs acabaram.
- **Arquivos:** `audio/locucao_corrigida.mp3` e os tempos de cada palavra em `audio/whisper_palavras_locucao_corrigida.json`.
- **Texto:**

> Em 1997, a indústria priorizava o 3D. Castlevania fez o contrário: apostou no 2D e criou um clássico.
> Mas o maior segredo do jogo estava escondido depois do suposto final. Ao descobrir quem realmente controlava
> Richter, você liberava uma segunda versão do castelo, completamente de cabeça para baixo. As mesmas áreas
> ganhavam novas rotas, inimigos e chefes. Lugares que você já conhecia precisavam ser explorados de outra
> maneira. Essa segunda jornada ampliava o jogo e reforçava a exploração que ajudou a consolidar o estilo
> metroidvania. Você conseguiu chegar ao castelo invertido? Conta aí nos comentários.

## Decisões já tomadas

| Assunto | Decisão |
|---|---|
| Fontes de imagem | `GamePlay.mp4` bruto (640×480, sem áudio) da pasta original; a tela de título vem do vídeo antigo aos 14 s (pedido do usuário). O resto do vídeo editado antigo não é usado. |
| Proporção | gameplay sempre em 4:3, sem cortar nem esticar. |
| Música | Bloody Tears a partir de 0:22 da faixa. Risco: é música da Konami e pode gerar reivindicação de direitos autorais no YouTube ou áudio silenciado no Instagram. |
| Quarto e câmera | quarto do Manifesto com monitor 4:3 (`v2/vendor/setup_desenho.js`); aproximação de 3,94 a 8,88 s até 3,33×; recuo de 39,80 a 41,60 s, no meio da pergunta final. |
| Atuação do Echo | copiada do Manifesto em `v2/vendor/echo-atuacao-sala.js` (Echo sentado no mesmo lugar e tamanho do Manifesto: x 252, y 1338, escala 1,2; mãos do Manifesto; controle 3D da v2, visto de frente como a câmera vê, com a parte de cima achatada, como numa foto de mãos segurando o controle). 0 a 3,9 s: sentado, apontando o monitor. 3,9 a 4,5 s: volta ao controle. 40,6 a 41,3 s: pousa o controle na mesa; ele fica inteiro sobre o tampo, na frente da caneca (X 450, Y 1124), longe da borda da cadeira. 41,3 a 42,0 s: vira para a câmera. 42,0 a 43,4 s: sai da cadeira vindo para a frente (escala até 1,7, descendo até o centro, desenhado na frente do quarto); a Propulsão Matrix do site (`v2/vendor/echo-propulsao-matrix.js`) acende em 42,3 s e cresce até 42,9 s; no mesmo tempo some a luz azul que o motor desenha embaixo do Echo (sentado, ela continua como no Manifesto). Olhos felizes no "Conta aí". |
| Slogan | não entra neste vídeo (fica nos perfis e no Manifesto). |
| Frase marcante no fim | adiada; ver `docs/governanca/backlog-baixa-prioridade.md`. |
| Área segura (Reels + Shorts) | texto e rosto do Echo entre Y 250 e 1450 e entre X 90 e 990, e até X 925 entre Y 970 e 1450. Medido em 01/11/2026: o Echo fica em X 100–458, Y 924–1328. |
| Chamada final | só a pergunta nos comentários; sem "link na bio" e sem "inscreva-se". |

## Vídeo final (01/11/2026, Forge #1786)

- **Arquivo:** `v2/renders/final/castlevania_castelo_invertido_1080x1920.mp4` (1080x1920, 60 fps, H.264 + AAC, 44,6 s, ~23 MB).
- **Render:** `v2/render_janela_v2.py` (OneTake, desfoque de movimento como no Manifesto) em pedaços de 1 s, pela `v2/quadros.html?offset=&dur=`. Com pedaços de 3 s um navegador emperrou; com 1 s cada pedaço leva de 11 a 25 s (45 pedaços, ~12 min).
- **Legenda:** a do Manifesto (Orbitron 700, HUD com barras ciano, palavra falada em ciano), gerada por `v2/legenda/montar_legenda_texto.py` e `v2/legenda/gerar_estados.py`. Diferenças em relação ao Manifesto: topo em Y 270 e largura entre X 90 e 925 (área segura de Reels + Shorts) e as pausas entre blocos preenchidas com um estado transparente (sem isso as legendas adiantam). 56 blocos, 103 estados, soma das durações = 44,57 s.
- **Áudio:** a mixagem da prévia (`v2/assets/audio_mixado_preview.mp3`, Bloody Tears a partir de 0:22) nivelada para -15,9 LUFS, pico -4,5 dB (`v2/renders/final/audio_final.wav`). A música já some sozinha depois da última fala.

## Capa (01/11/2026, Forge #1791)

- **Escolhida:** `v2/capa/capa_a.jpg` (1080x1920): fundo do vídeo final sem legenda em 1,0 s (Echo sentado, tela de título do Castlevania no monitor) e o texto "O SEGREDO DEPOIS DO FINAL" no estilo da legenda (Orbitron 74 px, "SEGREDO" em ciano), em X 92–923 e Y 340–540, dentro da grade 3:4 do Instagram e da área segura das duas redes.
- Outras opções guardadas: `capa_b.jpg` (Phantom Skull, 21,4 s) e `capa_c.jpg` (Echo na frente com a propulsão, 44 s). Gerador: script da sessão (fundos do `castlevania_final_mudo_1080x1920.mp4`).

## Publicação (agendada em 01/11/2026, Forge #1793)

- **Arquivos publicados:** `public/uploads/reels/castlevania-castelo-invertido/reel.mp4` e `capa.jpg` (endereço público `https://nerd.tfr-info.com.br/uploads/reels/castlevania-castelo-invertido/`).
- **YouTube (Short):** fila local `youtube_videos` item **#79**, público em **02/11/2026 18:30** (envio antecipado, privado até a data). Título: "Castlevania: Symphony of the Night e o segredo depois do final". Descrição e tags aprovadas pelo usuário (no item #79). Sem capa no YouTube: o Shorts usa um quadro do vídeo.
- **Instagram (Reels):** fila local `instagram_posts` post **#65058**, **04/11/2026 18:30** (segunda e terça já tinham Darksiders às 19:30). Legenda aprovada pelo usuário (no post #65058), sem "link na bio".

## Pendente

- Fantasia de Alucard (Forge #1773): fica para outro vídeo, se o usuário quiser.
- Fim do vídeo pelo molde (seção 11): pacote de origem e limpeza da pasta de trabalho, depois de publicado e com aprovação.
