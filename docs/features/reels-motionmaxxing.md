# Reels com a skill motionmaxxing — instalação, padrões aprovados e operação

ID: FEAT-REELS-MOTION
Tipo: Feature (produção de Reels) + operação da fila do Instagram
Data: 13/10/2026
Tarefas Forge: #1232 a #1240, #1245 (Reels e série Diablo), #1238 (troca na fila), #1257 (este documento)

## Objetivo

Produzir os Reels do @estrategia_nerd com tipografia cinética e animação que representa o sentido de cada frase, sem cara de "apresentação de slides", usando a skill `motionmaxxing` do Claude Code. Registrar instalação, padrões aprovados pelo usuário, fontes, som, área segura e como os vídeos entram na fila de publicação.

## 1. Instalação (Windows)

| Item | Onde / como |
|---|---|
| Skill | `C:\Users\WINDOWS\.claude\skills\motionmaxxing` (cópia do repositório, sem `.git`) |
| Ajustes Windows | `C:\Users\WINDOWS\.claude\skills\motionmaxxing\LEIA-ME-WINDOWS.md`: `CHROME_PATH` (variável de usuário), `python3.exe` em `motionmaxxing-bin` no início do PATH, Pillow 12.3.0; inclui como desfazer |
| Requisitos | Node, Chrome, ffmpeg, Python 3 com numpy/Pillow |
| Teste da instalação | `examples\selftest\index.html`: `lint.mjs` (G5), `render.mjs` (MP4) e `look.py` (relatório) ok em 13/10/2026 |
| Skill `animate` (cth9191/animate) | instalada em `C:\Users\WINDOWS\.claude\skills\animate` por outra IA a partir do plano entregue; serve para explicativos e histórias desenhadas em canvas, não para os Reels de foto. Não testada nesta entrega. |

Comandos usados (scripts da skill, a partir da pasta do filme):
`node render.mjs index.html draft.mp4 --scale 0.5` (rascunho), `node render.mjs index.html final.mp4 --audio mix.wav --shutter 180 --subframes 8 --grain 0.03` (final), `python look.py final.mp4 --out look --expect 20 --expect-audio` (gates G0/G2/G3, áudio), `node lint.mjs index.html` (G5), e o selfTest do runtime (reprodução por tempo, 0 divergências exigidas).

## 2. Padrões aprovados pelo usuário

Regras que vieram de 9 versões reprovadas por "parecer PowerPoint" e das aprovações seguintes:

- A **imagem do post fica inteira e legível na moldura** do fundo do projeto (cover com corte mínimo, deriva lenta). Nada de escurecer a foto a ponto de não reconhecer, nem zoom repetido.
- O **texto é o foco**: palavras grandes, letra por letra, com animação que **representa o sentido** (contar, apertar, escrever, queimar, acender), e não só aparecer ou deslizar. O texto pode cruzar a moldura e as animações podem passar por cima da imagem.
- **Fontes ligadas ao tema e combinando entre si**, com papel fixo para cada família.
- **Som escolhido para o tema**. Impactos sintéticos foram reprovados.

| Tipo de post | Referência aprovada | Fontes | Animações |
|---|---|---|---|
| Hardware | #201 v9 (RTX 3050 6 GB) | Orbitron (palavras que performam) + Rajdhani (apoio, fonte do site) + Consolas (termos técnicos) | "6" que conta com glow e reflexo e mergulho no 6; letras soltas que se apertam; DLSS saindo de ruído; "ray tracing" em contorno preenchido por luz |
| Lista / anime | #218 v3 (10 animes) | Dela Gothic One (destaques) + Rajdhani | letras que caem; "?" com o único quique; Death Note escrito por ponta de luz; Spy x Family sob tarjas de confidencial; Brotherhood com letras vindo dos dois lados |
| Série Diablo | piloto #224 (O mundo de Diablo) | Cinzel (títulos) + Cormorant Garamond (abertura em itálico e apoio) | Céu acende em luz dourada; Inferno queima em brasa que esfria devagar com fagulhas; Santuário se fixa como tinta; apoio escrito por ponta de pena; título cruza a moldura. O "fogo" com chamas foi testado e reprovado por parecer artificial. |

Fontes: Orbitron, Rajdhani, Dela Gothic One, Cinzel e Cormorant Garamond vêm do Google Fonts (licença OFL), baixadas só no subconjunto latino; Consolas é fonte do Windows.

## 3. Som

- Hardware e anime: música do próprio post (biblioteca IMP-032), com no máximo 2 golpes escolhidos e a música baixando antes do maior.
- Série Diablo: música do post + duas camadas sombrias do Pixabay (licença Pixabay Content, uso livre):
  - "Ancient Temple Horror Ambience – Dark Catacombs" (Coghezzi): https://pixabay.com/sound-effects/film-special-effects-ncient-temple-horror-ambience-dark-catacombs-atmosphere-539976/ (fundo contínuo, reforçado no fim);
  - "Dark Demonic Funeral Ambient" (Chrysalyn): https://pixabay.com/sound-effects/horror-dark-demonic-funeral-ambient-608395/ (coro nas cenas de Inferno).
  Mixagem em -14 LUFS e pico de -3 dBFS. Impacto sintético e labaredas foram reprovados.

## 4. Área segura do texto (Instagram, 1080x1920)

Somente para texto (imagem pode ficar sob os ícones): nada acima de Y 170 nem abaixo de Y 1540; entre Y 970 e 1790 (coluna de ícones) o texto vai até X 925; margem lateral X 72. Medir no navegador com o texto assentado e relatar os números. Também em Regras de Negócio (item 17.7) e Documentação Técnica (seção 16).

## 5. Fluxo de produção

1. Plano aprovado; para séries, **piloto primeiro** e replicação só depois da aprovação.
2. Gerador por post (HTML + runtime da skill), medidas reais das fontes no navegador e cálculo dos tamanhos que cabem na área segura.
3. Rascunho, quadros revistos, gates (G0, G2, G3, G5), selfTest, medição da área segura.
4. Render final com desfoque de obturador; prévia comprimida quando o arquivo passa de 16 MB (limite do link de revisão).
5. Capa `.jpg` com o mesmo nome do `.mp4`.

Fontes dos filmes (geradores, HTML, fontes, artes, áudio e MP4): `scratch/reels-motionmaxxing/` (pasta local, fora do git).

## 6. Entrada na fila (troca de vídeo)

Em 13/10/2026 os vídeos aprovados substituíram os antigos na fila:

| Post | Agendado | Antes | Depois |
|---|---|---|---|
| #218 | 13/10 19:30 | `reel-218-v2.mp4` | `reel-218-v3.mp4` + `.jpg` |
| #201 | 15/10 12:00 | `reel-201-v4.mp4` | `reel-201-v5.mp4` + `.jpg` (versão aprovada "v9") |

Pasta: `public/uploads/reels/correcao-instagram-20261010/`. A troca foi feita em transação (`instagram_posts.video_rendered_path` e `instagram_post_media.caminho`), só para posts ainda agendados, sem envio e com o vídeo antigo; script com `--rollback` em `scratch/reels-motionmaxxing/swap-posts.php`. Validação: `readyVideoPath` e `reelCoverParams` do publicador, endereços públicos com HTTP 200 e `verify-changes.php` com 0 falhas. Os vídeos antigos continuam na pasta. Procedimento geral: Documentação Técnica, seção 16.

## 7. Problemas conhecidos e soluções

| Problema | Solução |
|---|---|
| Quadro parado em PNG derruba o Chrome com fundos muito detalhados (série Diablo) | Renderizar o vídeo (JPEG) e extrair os quadros do próprio vídeo |
| Acentos corrompidos ao criar tarefas no Forge pelo shell | Enviar o JSON a partir de arquivo UTF-8 (`curl.exe --data-binary @arquivo.json`) |
| Gravação no banco bloqueada pelo sistema de permissões do agente | Deixar o script pronto e o usuário executar; o agente valida depois |
| Consulta ao Codex sem resposta | Cancelada após 3 minutos; seguir sem segunda opinião e registrar |
| Vídeo final acima de 16 MB | Cópia comprimida só para o link de revisão; o arquivo da fila é o original |
| Prévia em cache no celular | Republicar com nome de arquivo novo |

## 8. Estado em 13/10/2026

- #201 e #218: na fila com as versões novas.
- Série Diablo (#224, #225, #226, #195, #227, #197): piloto #224 aprovado (com brasa) e som sombrio aprovado; configuração e medidas dos outros 5 prontas; falta renderizar os 5, revisar e trocar na fila. Os Reels de hardware e de anime servem de padrão para os próximos posts do mesmo tipo.

## 9. Reel #200 corrigido em 14/10/2026 (Forge #1291)

- O vídeo B650 novo (`film-200-v3`) está em `uploads/posts/msi-mag-b650-tomahawk-wifi/reels/reel-v2.mp4` e `reel-v2.jpg` (hashes iguais aos de `final.mp4` e `final.jpg`). O `reel.mp4` antigo ficou no lugar, para rollback. `instagram_posts` 200 e `instagram_post_media` 131 apontam para o `reel-v2.mp4` (feito em transação), e a legenda não cita mais a B850.
- O #200 ficou em `rascunho` até o usuário autorizar a publicação; depois voltou a `agendado` (com `render_status = ready`) e foi publicado pelo cron em 14/10/2026 às 17:12, com o `reel-v2.mp4`: https://www.instagram.com/reel/DefNnpjCJai/ (`ig_media_id` 17895055548681149).
- Um primeiro passo, feito antes, sobrescreveu o `reel.mp4` antigo e não atualizou `instagram_post_media`; foi desfeito (arquivo antigo restaurado a partir de `storage/reel-200-20261014/`, fora do Git).

## 11. Padrão de arquivos dos Reels (15/10/2026)

- **Como as capas de artigos:** nome fixo, versão atual. `public/uploads/posts/<slug>/reels/reel.mp4`, com `reel.jpg` e `capa.jpg` ao lado. Post com mais de um Reel: `reels/reel-<id>/reel.mp4`. O banco (`video_rendered_path` e `instagram_post_media.caminho`) aponta sempre para esse nome.
- **Versões anteriores:** vão para `reels/anteriores/` (`reel-AAAAMMDD-<hash8>.mp4` e `.jpg`, `capa-AAAAMMDD-<hash8>.jpg`) antes de colocar a nova; são o rollback.
- Substitui a orientação anterior de `reel-<id>-vN` em `uploads/reels/<lote>/`. Os Reels em `uploads/reels/...` (geradores, todos cancelados) e o #200 (publicado, com `reel-v2.mp4`) ficam como estão.
- Aplicado em 15/10/2026 aos Reels #202, #203, #204, #190 e #207 (Forge #1293): vídeos dos filmes `film-202-v1`, `film-203-v1`, `film-204-v1`, `film-190-v1` e `film-207-v1`, com hashes iguais aos de `final.mp4`/`final.jpg`. Backup em `storage/reels-troca-20261015/` (fora do Git). Os registros do banco não mudaram (o caminho já era esse); todos seguem `agendado` nos mesmos horários.

## 10. Tempos de render (medidos em 14/10/2026)

| Medição | Resultado |
|---|---|
| Vídeo de 20 s, 1080x1920, `--shutter 180 --subframes 8 --grain 0.03` | cerca de 9 a 10 min |
| 3 renders de 20 s ao mesmo tempo | 657,7 s, igual a 1 sozinho (~656 s) |
| Lote real de 3 filmes (#200 B650, #202, #203) | ~9,5 min no total; CPU média 44% (pico 100%); RAM livre mínima 3,3 GB |
| Lote real de 2 filmes (#202, #203) | CPU média 32%; RAM livre mínima 4,4 GB |
| Lote A (#204, #190, #207) | 9 min 41 s a 9 min 50 s para os três |
| Trecho de 5 s: 1 render | ~164 s |
| Trecho de 5 s: 4, 5 e 6 renders juntos | 175, 178 e 177 s; RAM livre mínima 3,4, 2,7 e 1,3 GB |
| Rascunho sem shutter (trecho de 5 s) | ~21 s |
| 4 sub-frames (trecho de 5 s) | ~82 s (SSIM 0,992 contra 8 sub-frames) |
| `--scale 0.5` com shutter | só ~27% mais rápido |

- Sugestão prática: 4 renders ao mesmo tempo, 6 no máximo. **Ainda sem aprovação como padrão.** Padrão de sub-frames segue em 8.
- No Windows definir `CHROME_PATH` (o `render.mjs` só procura caminhos de Mac e Linux).
- O render em paralelo difere do render sozinho (SSIM 0,985 a 0,99), mas os renders em paralelo são iguais entre si.
- Origem dos números: tarefas do Forge 1265, 1266 e 1273 e os arquivos `lote-a-start.txt` e `done.txt` do Lote A.

## Validação

- Gates e selfTest registrados por vídeo nas tarefas do Forge; área segura medida em cada um.
- `verify-changes.php`: 23 OK, 0 falhas após a troca na fila.
