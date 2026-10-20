# Echo — Dia do Profissional de TI — pacote de origem (vídeo da série do Echo)

> Pacote criado em 20/10/2026 (Forge #1565) seguindo `docs/governanca/molde-serie-echo.md`, seção "Fim do vídeo".
> Serve para refazer, corrigir ou reaproveitar o vídeo. O vídeo publicado não fica aqui.

## Onde está o vídeo publicado

| O quê | Onde |
|---|---|
| Vídeo final (H.264 + AAC, 1080×1920) | `public/uploads/reels/echo-dia-do-ti/reel.mp4` |
| Capa (cópia aqui em `capa.jpg`) | `public/uploads/reels/echo-dia-do-ti/capa.jpg` |
| Post no Instagram | post **#23051**, Reels, **publicado em 19/10/2026 às 23:22** (agendado para 23:20) |

## Escolhas deste vídeo (exemplo, não padrão da série)

Echo com a fantasia "Cara de TI" (camisa do Estratégia Nerd), sala de servidor, voz Cornelius gravada pelo usuário no ElevenLabs ("tentou reiniciar?"), legenda em Orbitron sem caixa e sempre na mesma altura, transições contínuas na linguagem do Manifesto.

## Conteúdo do pacote

| Pasta | Conteúdo |
|---|---|
| `v3/` | versão final: cenas (`cenas.js`), boca (`boca.js`, `boca.json`, `gerar_boca.py`), quadros-chave (`quadros.html`), medidas e área segura, LEIAME da v3 |
| `v3/anim/` | animação com transições (`comp_trans.html`, `cenas_anim.js`, `trans.js`), mixagem (`audio/mix.wav`) e scripts de conferência e áudio |
| `v3/vendor/` | motor do Echo, fantasias (`robo-roupas-echo.js`) e `echo-director.js` |
| `v3/fonts/`, `v3/refs/` | JetBrains Mono, Orbitron (woff2), símbolo da marca e a imagem de referência de estilo enviada pelo usuário (só clima) |
| `v3/pranchas/` | prancha da v3 |
| `audio/fala.mp3`, `tempos.json` | voz original e tempos da fala |
| `LEIAME-v1.md` | registro da v1 (reprovada) |

**Testado em 20/10/2026:** `v3/quadros.html` e `v3/anim/comp_trans.html` abrem de dentro do pacote sem erro nem arquivo faltando; 6 instantes da animação idênticos (SHA-256) à pasta original.

## Contraexemplos (por que a v1 e a v2 foram reprovadas)

Cabos como linhas retas ("pernas de aranha"), traço de diagrama sem contorno e sombra, chão vazio, Echo pequeno (~1/3 da largura), legenda cortada e com cara de campo de formulário. Detalhes no molde, seção 8.

## Pasta de trabalho original

`C:\Users\WINDOWS\Projects\output\echo-ti-video\`. Em 20/10/2026 saíram os quadros (`frames/`) e dois perfis de navegador (`perfil_chrome/` e `v2/perfil_chrome/`); o resto continua lá até decisão do usuário (inclui um terceiro perfil em `v3/perfil_chrome/` e os renders da v3).
