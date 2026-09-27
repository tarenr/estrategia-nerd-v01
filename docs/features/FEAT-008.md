# FEAT-008 — Calendário editorial de publicação (leva FEAT-005)

## Objetivo
Definir as datas planejadas em que cada um dos 11 posts da leva FEAT-005
(hoje como rascunho em produção) deve virar `publicado` de verdade.

## Calendário

Cadência: 2 posts por semana (terça e sexta).

| Data | Post | Local id | Produção id | Status |
|---|---|---|---|---|
| Sex, 18/09/2026 | Windows 11 24H2: fim das atualizações em outubro | 62 | 30 | **Publicado manualmente pelo usuário** |
| Ter, 22/09/2026 | RTX 5060 vs RX 9060 XT (1080p) | 63 | 31 | Rascunho |
| Sex, 25/09/2026 | AM4 ou AM5 em 2026 | 64 | 32 | Rascunho |
| Ter, 29/09/2026 | Como escolher fonte para PC | 65 | 33 | Rascunho |
| Sex, 02/10/2026 | Como verificar saúde de SSD/HD | 66 | 34 | Rascunho |
| Ter, 06/10/2026 | Temperatura CPU/GPU: quando se preocupar | 67 | 35 | Rascunho |
| Sex, 09/10/2026 | 12 jogos leves para PC fraco ou modesto | 68 | 36 | Rascunho |
| Ter, 13/10/2026 | A história do Counter-Strike | 69 | 37 | Rascunho |
| Sex, 16/10/2026 | 10 filmes nerds essenciais | 70 | 38 | Rascunho |
| Ter, 20/10/2026 | 10 animes essenciais | 71 | 39 | Rascunho |
| Sex, 23/10/2026 | 10 desenhos dos anos 90/2000 que envelheceram bem | 72 | 40 | Rascunho |

O post do Windows 11 (prazo externo: precisa estar no ar antes de 13/10/2026,
fim do suporte do Windows 11 24H2) foi publicado manualmente pelo usuário
antes da data planejada.

## Observação sobre mídia pendente (Concluída em 27/09/2026)
Todos os 32 trailers e aberturas pendentes dos posts `12 jogos leves` (post 36, 12 vídeos YouTube),
`10 animes essenciais` (post 39, 9 vídeos YouTube + 1 autohospedado em `uploads/posts/animes-essenciais-para-iniciantes/video/fullmetal-alchemist-brotherhood.mp4`)
e `10 desenhos dos anos 90/2000` (post 40, 10 vídeos YouTube) foram incorporados com sucesso aos
rascunhos no banco de produção. Nenhuma pendência de mídia restante nesses posts.

## Automação
Script `scripts/en-blog-schedule-publish.php`. Roda via Cron Job na
Hostinger (conta u576397693), diariamente às 12:00 UTC (09:00
Brasília), direto em produção. Confirmado funcionando em 27/09/2026
(posts 31 e 32 publicados nas datas certas).
