# Reels de Cultura Pop e Listas — Exemplos Visuais e Reagendamento

Data: 09/10/2026. Projeto: Estratégia Nerd.

## Contexto e Escopo
Após a validação bem-sucedida do piloto do post #215 com troca dinâmica de imagens para cada exemplo, aplicamos a solução aos posts de cultura pop e listas do blog, além de reagendar o post #215 corrigido para a data livre de 11/10/2026.

## Posts e Exemplos Visuais Integrados

### 1. #205 — 10 Jogos que todo fã de RPG precisa jogar antes de morrer
- **Imagens integradas (Steam CDN):**
  - Cena 1: Capa do artigo (`capa.webp`)
  - Cena 2: `chrono-trigger.jpg` (*Chrono Trigger*)
  - Cena 3: `the-witcher-3.jpg` (*The Witcher 3: Wild Hunt*)
  - Cena 4: `baldurs-gate-3.jpg` (*Baldur's Gate 3*)
- **Vídeo gerado:** `reel-205-v3.mp4` (28s, layout `culture`, tom `fantasy`).

### 2. #216 — A história do Counter-Strike: por que ainda é gigante
- **Imagens integradas (Steam CDN e capa oficial):**
  - Cena 1: Capa oficial do post (`capa.png` / `cover`) — Abertura e gancho inicial
  - Cena 2: `half-life-mod.jpg` (*Mod Half-Life 1999*) — As origens no PC
  - Cena 3: `cs-16.jpg` (*Counter-Strike 1.6*) — A era das lan houses e cenário competitivo
  - Cena 4: `cs2.jpg` (*Counter-Strike 2*) — Nova geração Source 2
- **Vídeo gerado:** `reel-216-v4.mp4` (28s, layout `chronicle`, tom `action`).

### 3. #217 — 10 Filmes nerds essenciais que todo geek deveria ver
- **Imagens integradas (TMDB 1280px):**
  - Cena 1: Capa do artigo
  - Cena 2: `matrix.jpg` (*The Matrix, 1999*)
  - Cena 3: `fellowship-of-the-ring.jpg` (*O Senhor dos Anéis: A Sociedade do Anel*)
  - Cena 4: `back-to-the-future.jpg` (*De Volta para o Futuro*)
- **Vídeo gerado:** `reel-217-v3.mp4` (28s, layout `culture`, tom `cinema`).

### 4. #218 — 10 Animes essenciais pra quem nunca assistiu nenhum
- **Imagens integradas (TMDB / WallpaperCave 1080p):**
  - Cena 1: Capa do artigo
  - Cena 2: `death-note.jpg` (*Death Note*)
  - Cena 3: `spy-x-family.jpg` (*Spy × Family*)
  - Cena 4: `fullmetal-alchemist.jpg` (*Fullmetal Alchemist: Brotherhood*)
- **Vídeo gerado:** `reel-218-v3.mp4` (28s, layout `culture`, tom `anime`).

### 5. #219 — 10 Desenhos dos anos 90 e 2000 que envelheceram bem
- **Imagens integradas (Cartoon Network / TMDB / WallpaperCave 1080p):**
  - Cena 1: Capa do artigo
  - Cena 2: `laboratorio-dexter.jpg` (*O Laboratório de Dexter*, Cartoon Network)
  - Cena 3: `samurai-jack.jpg` (*Samurai Jack*)
  - Cena 4: `avatar-aang.jpg` (*Avatar: A Lenda de Aang*)
- **Vídeo gerado:** `reel-219-v4.mp4` (28s, layout `culture`, tom `nostalgia`).

## Ajustes Editoriais Finos (v4)
- **#216**: Abertura restaurada com a capa oficial do artigo (`cover`), mantendo a identidade visual do portal e reservando o mod de Half-Life para a cena seguinte.
- **#219**: Asset de *O Laboratório de Dexter* substituído pela arte canônica do desenho animado do Cartoon Network (resolução 1280x720), corrigindo imagem anterior equivocada da série dramática com atores.

## Reagendamento do Post #215 (12 Jogos PC Fraco)
- **Arquivo promovido para produção:** `public/uploads/reels/editorial-202610/reel-215-v3.mp4` e `.jpg`.
- **Registro MySQL atualizado:**
  - `status`: `agendado`
  - `agendado_para`: `2026-10-11 19:30:00` (preenchendo a vaga livre)
  - `video_rendered_path`: `uploads/reels/editorial-202610/reel-215-v3.mp4`
  - `idempotency_key`: Nova chave UUID v4 gerada
  - `duracao_s`: 28 segundos
- **Galeria visual:** Todos os vídeos revisados (`reel-216-v4.mp4`, `reel-219-v4.mp4` e demais `v3`) já estão atualizados e reproduzíveis em `public/uploads/previews/revisao-reels-20261007/index.html`.
