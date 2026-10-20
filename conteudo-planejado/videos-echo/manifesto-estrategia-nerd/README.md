# Manifesto Estratégia Nerd — pacote de origem (vídeo da série do Echo)

> Pacote criado em 20/10/2026 (Forge #1565) seguindo `docs/governanca/molde-serie-echo.md`, seção "Fim do vídeo".
> Serve para **refazer, corrigir ou reaproveitar** o vídeo. O vídeo publicado **não** fica aqui.

## Onde está o vídeo publicado

| O quê | Onde |
|---|---|
| Vídeo final 1080×1920, 60 fps, 60 s, -16 LUFS (H.264 + AAC, ~40 MB) | `public/uploads/reels/manifesto-estrategia-nerd/reel.mp4` |
| Capa JPEG 1080×1920 (cópia aqui em `capa.jpg`) | `public/uploads/reels/manifesto-estrategia-nerd/capa.jpg` |
| Post no Instagram | fila local, post **#23397**, Reels, agendado para **21/10/2026 16:00** |
| Endereço público (a Meta baixa por ele) | `https://nerd.tfr-info.com.br/uploads/reels/manifesto-estrategia-nerd/` |

## O que o usuário aprovou (escolhas deste vídeo, não padrão da série)

- Roteiro: o da voz (`roteiro/legenda_texto.json`); CTA "Acesse o link na bio e siga o perfil".
- Cenários: praça do feed (0–18 s), corrida, RPG e quarto gamer; final no quarto com o Echo se movendo, zoom no monitor e volta à cadeira.
- Legenda: Orbitron com moldura HUD, em cima; desce só onde cobriria texto (6–11,5 s e 13,3–19,7 s).
- Música: opção 3, `public/uploads/audio/pixabay_561438.mp3` (~122 BPM, biblioteca do portal; não copiada aqui).
- Efeitos: 39, inclusive vidro real (arremesso e choque da notícia), carro acelerando e engrenagem do RPG (`audio/eventos_sfx.json`).
- Capa: opção 3 (Echo no quarto + "NADA DE ACHISMO. SÓ ESTRATÉGIA.").

## Conteúdo do pacote

| Pasta | Conteúdo |
|---|---|
| `cenas/src/` | composição final `manifesto-60s-v12.html` (com a tabela da boca embutida), `echo-director.js`, motor do Echo (`vendor/`) |
| `cenas/cenarios_v10/` | os 5 desenhos de cenário em Canvas 2D usados pela composição |
| `assets/` | fontes (JetBrains Mono, Rajdhani, Space Grotesk), símbolo da marca, voz original `locucao-guia.wav`, sons de vidro |
| `audio/` | mixagem final `mix_manifesto.wav` (narração + música + efeitos), script dos efeitos e da mixagem, lista de efeitos |
| `roteiro/` | tempos por palavra (Whisper), texto da legenda, tempos 0–18 s, boca 42–60 s |
| `legenda/` | gerador dos quadros da legenda HUD (Orbitron) e da montagem; `estados.json` com os tempos |
| `ferramentas/` | render em segmentos, conferências (hash, área segura, MP4, offsets), transcrição, boca, legenda, música, storyboard, montagem final |
| `pranchas/aprovadas/` | cenários aprovados, conferência do final, opções de legenda e de capa |
| `pranchas/reprovadas/` | primeiro final (tudo no quarto), roteiro que copiava o começo, still da v9 |

## Como refazer

1. A composição abre de dentro do pacote: `cenas/src/manifesto-60s-v12.html?offset=0` (as pastas estão na mesma profundidade da original; as duas imagens do post "16 GB ou 32 GB" vêm de `public/uploads/posts/16gb-ou-32gb-ram-pc-gamer-2026/images/`). **Testado em 20/10/2026:** 20 instantes de 0,5 a 59,5 s idênticos (SHA-256) à composição original, 0 erros.
2. Render: `ferramentas/render_v12_janela.py` (OneTake, segmentos de 5 s). Os scripts de `ferramentas/`, `legenda/` e `audio/` foram escritos para a pasta de trabalho original (`scratch/manifesto-10-10/v3/editorial-rework/`): ajuste os caminhos do topo de cada script antes de rodar.
3. Montagem final: `ferramentas/montar_final.py` (vídeo sem áudio + legenda + `audio/mix_manifesto.wav`).

## O que foi apagado (lixo, em 20/10/2026)

Quadros PNG guardados dos renders (`_frames`, ~6 GB) e pedaços de render (`seg_*`). A pasta de trabalho `scratch/manifesto-10-10/` (versões v1 a v11, relatórios, prévias) só sai depois do post publicado, com confirmação do usuário.
