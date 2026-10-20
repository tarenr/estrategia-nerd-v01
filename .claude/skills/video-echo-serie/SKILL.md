---
name: video-echo-serie
description: Conduz a produção de um vídeo da série do Echo (robô apresentador do @estrategia_nerd, vertical 1080x1920) seguindo o molde interno do Estratégia Nerd - roteiro em texto, voz, tempo por palavra, prancha de cenas com quadros reais, animação em blocos, boca sincronizada, legenda, som, prévias, versão final, capa e agendamento, com parada para aprovação em cada etapa. Use quando o usuário pedir um vídeo, Reel ou animação com o Echo, um "vídeo da série", "fazer o Echo apresentar", "molde do Echo", ou retomar um vídeo do Echo em andamento.
---

# Vídeo da série do Echo

Fonte de verdade: `estrategia-nerd/docs/governanca/molde-serie-echo.md` (leia antes de propor qualquer coisa) e `docs/governanca/guia-direcao-audiovisual-ia.md`. Esta skill só organiza o trabalho; ela **não** autoriza escrita. Todo passo que cria ou altera arquivo segue o ritual do `CLAUDE.md`/`AGENTS.md` de `C:\Users\WINDOWS\Projects`: plano, "Aprovado" do usuário, execução, parada.

## Regra mais importante: padrão x escolha do vídeo

- **Igual em todo vídeo** (seção 1 do molde): Echo pelo motor oficial, sem braços e sem pernas, com a boca mexendo durante toda a fala; 1080x1920; área segura de texto medida; aprovação em cada etapa; conferências técnicas.
- **Decidir com o usuário em cada vídeo** (seção 2): cenários, figurino, duração, fonte e posição da legenda, música, efeitos, capa, horário. **Nunca** copie a escolha de um vídeo anterior como se fosse regra (ex.: a Orbitron do Manifesto). Ofereça opções e pergunte.
- Antes de cada etapa, releia a seção 8 do molde (erros já cometidos) e diga ao usuário qual erro aquela etapa evita.

## Etapas (uma parada por etapa)

1. **Roteiro em texto.** Analise o que a voz diz (ou vai dizer) e escreva cena por cena: tempo, frase, cenário, o que o Echo faz, texto na tela. Mostre quantos segundos ficam em cada cenário. Entregue e pare.
2. **Voz.** Uma take contínua no ElevenLabs (o usuário pode gravar). Transcreva com Whisper `base` (já instalado; nada a instalar) para ter o tempo de cada palavra. Vale o que a voz diz; corrija só a grafia.
3. **Prancha das cenas.** Quadros reais (existentes ou novos) com frase e ação. Cenário novo segue o checklist da seção 4.2 do molde (6+ camadas, objetos do assunto, textura, luz, lugar do Echo). Nada de copiar o começo do vídeo no final. Pare.
4. **Animação em blocos.** Um bloco por conversa nova do Antigravity (skills `antigravity-subagente` e `bloco-para-ia`), em sequência. Antes de cada bloco: backup e linha de base congelada. Depois: conferência própria (hash fora da janela, diff por função, 0 erros de página, área segura, quadros olhados, boca).
5. **Prévia com voz** (540x960) pelo chat. Pare.
6. **Legenda.** 2–3 opções de fonte/posição sobre quadros reais; blocos de uma linha; conferir cobertura de texto e do rosto do Echo em todos os trechos. Fonte woff2 → desenhar no navegador (o ffmpeg daqui não lê woff2).
7. **Som.** 3 trechos de música da biblioteca para o usuário escolher; efeitos confirmados no quadro; ducking; -16 LUFS e pico -1,5 dBTP.
8. **Prévia final**, depois **versão 1080x1920** (juntar, sem renderizar de novo), **capa** (3 opções, texto aprovado) e **agendamento** (skill `agendar-reel-instagram`). Uma parada por item.
9. **Registro.** Forge (projeto 4, `curl.exe` com JSON em arquivo UTF-8), README da pasta, memória; acrescentar no molde as escolhas deste vídeo (como exemplo) e os erros novos.
10. **Fim do vídeo: pacote e limpeza** (seção 11 do molde), depois de agendado ou publicado, em plano com aviso de operação destrutiva: montar o pacote de origem em `conteudo-planejado/videos-echo/<nome>/` (mesma profundidade de pastas, sem o vídeo final, que fica em `public/uploads/reels/<nome>/`), testar abrindo a composição de dentro dele (hash idêntico, 0 erros), listar o lixo com tamanhos, conferir os vídeos finais e só então apagar `_frames`, `seg_*`, prévias superadas e perfis de navegador. A pasta de trabalho inteira só sai depois do post publicado, com confirmação. Renderize sem `--keep-frames` para não acumular.

## Animação: mínimo aceitável

Cada frase com uma ação; o Echo se move e reage sem teleporte; a câmera trabalha; transições com sentido; nada parado mais de ~1,4 s; variar a cada ~6 s; objetos não somem todos de uma vez; boca visível (plano mais fechado quando a fala importa).

## Armadilhas técnicas (detalhes na seção 8.5 do molde)

`?offset=` sempre; `render.py` apaga `_frames` ao lado da saída (subpasta própria); render em segmentos < 150 s; todo script com prazo e HTML aberto de dentro da pasta do projeto; arquivo > 30 MB não chega ao celular (mandar a prévia); endereço público sem `/public`.

## Comunicação

Português simples e curto, uma decisão por vez. "ok", "sim", "pode", "manda" não aprovam: peça "Aprovado". Diga sempre o que conferiu e o que não conferiu.
