---
name: video-echo-serie
description: Conduz a produção de um vídeo da série do Echo (robô apresentador do @estrategia_nerd e do YouTube @estrategianerd, vertical 1080x1920 para Reels e Shorts) seguindo o molde interno do Estratégia Nerd - roteiro em texto, voz, tempo por palavra, prancha de cenas com quadros reais, animação em blocos, boca sincronizada, legenda, som, prévias, versão final, capa e agendamento, com parada para aprovação em cada etapa. Use quando o usuário pedir um vídeo, Reel ou animação com o Echo, um "vídeo da série", "fazer o Echo apresentar", "molde do Echo", ou retomar um vídeo do Echo em andamento.
---

# Vídeo da série do Echo

Fonte de verdade: `estrategia-nerd/docs/governanca/molde-serie-echo.md` (leia antes de propor qualquer coisa), `docs/governanca/diretrizes-redes-sociais.md` (estratégia, público, pilares, regras das duas redes) e `docs/governanca/guia-direcao-audiovisual-ia.md`. Esta skill só organiza o trabalho; ela **não** autoriza escrita. Todo passo que cria ou altera arquivo segue o ritual do `CLAUDE.md`/`AGENTS.md` de `C:\Users\WINDOWS\Projects`: plano, "Aprovado" do usuário, execução, parada.

## Regra mais importante: padrão x escolha do vídeo

- **Igual em todo vídeo** (seção 1 do molde): Echo pelo motor oficial, sem braços e sem pernas, com a boca mexendo durante toda a fala; 1080x1920 para Reels e Shorts; área segura das duas redes medida (texto e rosto entre Y 250 e 1450, X 90–990, X ≤ 925 em Y 970–1450); chamada final só com pergunta nos comentários; aprovação em cada etapa; conferências técnicas; **nada vai a público sem o usuário ver o arquivo final**.
- **Decidir com o usuário em cada vídeo** (seção 2): cenários, figurino, duração, fonte e posição da legenda, música, efeitos, capa, horário. **Nunca** copie a escolha de um vídeo anterior como se fosse regra (ex.: a Orbitron do Manifesto). Ofereça opções e pergunte.
- Antes de cada etapa, releia a seção 8 do molde (erros já cometidos e o catálogo de diretrizes operacionais 8.6) e diga ao usuário qual erro aquela etapa evita.
- **Checklist obrigatório de prevenção (molde seção 8.6):**
  - *Colisão e camadas:* Echo sobe e desce por trás de monitores/mesas, nunca atravessa objetos sólidos.
  - *Física mecânica:* peças e cabos entram com curvas de gravidade e elasticidade, nunca por "passe de mágica".
  - *Sincronia viva:* boca dirigida por RMS a cada take de voz; Echo nunca fica estático > 1,2s.
  - *Roteiro limpo:* zero redundâncias ou repetições de verbos ("vamos começar", "vou começar").
  - *Duração exata:* locução e mixagem cravadas na minutagem oficial do molde (ex.: 65,00 s exatos).
  - *Capas e aprovação:* 3 opções de capa nativas 9:16 (1080×1920) apresentadas em carrossel no chat antes de qualquer agendamento; envio obrigatório da miniatura via API (`setThumbnail`) tanto no Instagram quanto no YouTube.
  - *Cronologia:* checar estado do cenário antes de agendar (vídeos do quarto novo e nichos após o vídeo de montagem).
  - *Agendamento automático:* preencher `remote_upload_due_at = NOW()` ao registrar agendamento no banco.

## Etapas (uma parada por etapa)

1. **Roteiro em texto.** Analise o que a voz diz (ou vai dizer) e escreva cena por cena: tempo, frase, cenário, o que o Echo faz, texto na tela. Mostre quantos segundos ficam em cada cenário. Entregue e pare.
2. **Voz.** Uma take contínua: hoje edge-tts `pt-BR-AntonioNeural` (o ElevenLabs está sem créditos; o usuário vai gravar quando tiver microfone). Antes da locução inteira, uma amostra só com os nomes em inglês do roteiro. Transcreva com Whisper `base` (já instalado; nada a instalar) para ter o tempo de cada palavra. Vale o que a voz diz; corrija só a grafia.
3. **Prancha das cenas.** Quadros reais (existentes ou novos) com frase e ação. O quarto do Echo é a cópia do setup do usuário (padrão oficial aprovado em 06/11/2026 para todos os vídeos): montar com `conteudo-planejado/videos-echo/_kit-quarto/quarto_setup_raster.js` (`producao-camadas-v1/` e `pecas.json`; molde, seções 1 e 9), com cadeira ampliada 1,28×, iluminação viva (6 hexágonos LED, 3 barras neon, painel pixelado 6×6 e gabinete RGB) e o Echo sentado na cadeira (`k = 1.50`), saindo dela no fechamento após pousar o controle 3D no assento para flutuar no meio do quarto (`k = 1.85`) com Propulsão Matrix. Cenário novo segue o checklist da seção 4.2 do molde (6+ camadas, objetos do assunto, textura, luz, lugar do Echo). Nada de copiar o começo do vídeo no final. Pare.
4. **Animação em blocos.** Um bloco por conversa nova do Antigravity (skills `antigravity-subagente` e `bloco-para-ia`), em sequência. Antes de cada bloco: backup e linha de base congelada. Atuação do Echo: usar obrigatoriamente o `_kit-echo` (`conteudo-planejado/videos-echo/_kit-echo/echo-padrao.js`), relógio contínuo `seek(t)` e casco rígido sem gelatina (`sx=1, sy=1`). Antes de mostrar ao usuário: rodar a conferência automática `conferir-atuacao.cjs`, comparar as folhas de quadros lado a lado com o vídeo de referência do Manifesto, conferir diff por função, 0 erros de página, área segura e sincronia da boca. Aprovar animação por imagem ou clipe, nunca só pelo texto do plano; uma IA por vez na pasta.
5. **Prévia com voz** (540x960) pelo chat, gerada quadro a quadro pela composição (`window.__seek`), nunca gravando o navegador em tempo real. Pare.
6. **Legenda.** 2–3 opções de fonte/posição sobre quadros reais; blocos de uma linha; conferir cobertura de texto e do rosto do Echo em todos os trechos. Fonte woff2 → desenhar no navegador (o ffmpeg daqui não lê woff2); pausas entre blocos viram estado transparente (a soma das durações = duração do vídeo).
7. **Som.** 3 trechos de música da biblioteca para o usuário escolher; efeitos confirmados no quadro; ducking; -16 LUFS e pico -1,5 dBTP.
8. **Prévia final**, depois **versão 1080x1920** (juntar, sem renderizar de novo), **capa** (3 opções, texto aprovado), **textos de cada rede** (aprovados) e **agendamento**, só depois de o usuário ver o arquivo final: Instagram pela skill `agendar-reel-instagram`; YouTube Shorts pela fila `youtube_videos` (molde, seção 3, passo 11). Uma parada por item.
9. **Registro.** Forge (projeto 4, `curl.exe` com JSON em arquivo UTF-8), README da pasta, memória; acrescentar no molde as escolhas deste vídeo (como exemplo) e os erros novos.
10. **Fim do vídeo: pacote e limpeza** (seção 11 do molde), depois de agendado ou publicado, em plano com aviso de operação destrutiva: montar o pacote de origem em `conteudo-planejado/videos-echo/<nome>/` (mesma profundidade de pastas, sem o vídeo final, que fica em `public/uploads/reels/<nome>/`), testar abrindo a composição de dentro dele (hash idêntico, 0 erros), listar o lixo com tamanhos, conferir os vídeos finais e só então apagar `_frames`, `seg_*`, prévias superadas e perfis de navegador. A pasta de trabalho inteira só sai depois do post publicado, com confirmação. Renderize sem `--keep-frames` para não acumular.

## Animação: mínimo aceitável

Cada frase com uma ação; o Echo se move e reage sem teleporte; a câmera trabalha; transições com sentido; nada parado mais de ~1,4 s; variar a cada ~6 s; objetos não somem todos de uma vez; boca visível (plano mais fechado quando a fala importa).

## Armadilhas técnicas (detalhes na seção 8.5 do molde)

`?offset=` sempre; `render.py` apaga `_frames` ao lado da saída (subpasta própria); render em segmentos < 150 s (pedaços de 1 s quando a composição busca vídeo por quadro; modelo `render_janela_v2.py` do Castlevania); todo script com prazo e HTML aberto de dentro da pasta do projeto; arquivo > 30 MB não chega ao celular (mandar a prévia); endereço público sem `/public`.

## Comunicação

Português simples e curto, uma decisão por vez. "ok", "sim", "pode", "manda" não aprovam: peça "Aprovado". Diga sempre o que conferiu e o que não conferiu.
