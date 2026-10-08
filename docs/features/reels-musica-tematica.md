# Seleção temática de música para Reels

Implementada em 07/10/2026. Complementa IMP-032: a classificação por categoria deixava games receber tanto épico quanto chiptune, e o fallback escolhia faixas de outro grupo para aumentar a diversidade. Agora a compatibilidade temática vem antes da novidade da faixa.

## Comportamento

`TrackPickerService::pick(categoria, duração, contexto)` aceita `titulo`, `resumo` e `tags`. `profileFor` normaliza texto/acento, aplica regras editoriais determinísticas e define atmosfera e ritmo aproximado:

| Conteúdo | Perfil | Ritmo editorial padrão |
|---|---|---|
| Diablo, horror, Resident Evil, Dark Souls, Elden Ring | dark | slow/medium |
| RPG, fantasia, medieval, Skyrim, magia | fantasy | slow/medium |
| Nostalgia, SNES, arcade, pixel, 8/16 bits | retro | medium/fast |
| FPS, corrida, competitivo, ação | action | medium/fast |
| Hardware sem tema mais específico | technology | qualquer ritmo catalogado |
| Dicas sem tema mais específico | calm | slow/medium |
| Cultura e demais assuntos sem tema reconhecido | light | qualquer ritmo catalogado |

A ordem da tabela é a prioridade: Diablo nostálgico permanece dark. Games sem pistas mais específicas usam action. O campo opcional `ritmo` (`slow`, `medium`, `fast`) permite exigir um ritmo específico; não existe inferência do ritmo a partir da análise do movimento do vídeo. A seleção automática usa o ritmo editorial do perfil. As regras podem interpretar mal negações, comparações ou posts com vários temas; não são um modelo de compreensão semântica nem uma análise de BPM.

`config/reel-music.json` classifica as 45 faixas existentes por origem/ID, temas, ritmo editorial, título e SHA-256. As classificações são inferências conservadoras dos títulos e grupos do catálogo, registradas como `metadata_inference`, com `auditionConfirmed=false`. Não afirmar que houve audição ou medição de tempo. A entrada dark/fantasy de `Dark Fantasy Ambient (Dungeon Synth)` usa o descritor explícito da faixa. Atualizar a curadoria quando uma prévia mostrar inadequação, preservando esse estado de evidência.

Uma faixa só participa se estiver ativa, durar mais que o Reel, existir localmente, corresponder à origem/ID e ao hash curado, e atender tema e ritmo. Faixas não classificadas não entram por fallback de gênero. Não há alteração de schema ou dados da biblioteca. Novas importações precisam de classificação no JSON para entrar na seleção temática.

O histórico de uso de todos os posts e as reservas da execução continuam sendo considerados. Primeiro entram faixas **compatíveis sem uso**. Ao esgotar esse subconjunto, é permitida reutilização da compatível menos usada, com escolha de outro recorte quando há espaço. Não escolhe uma faixa incompatível sem uso para evitar repetir. A preferência por novidade não garante variedade ilimitada nem recortes sempre inéditos em uma faixa curta.

Se nenhuma faixa passar, `pick` retorna null e `warning()` descreve tema/ritmo e falta de duração/integridade. O sincronizador registra a mensagem em stderr e no log PHP, contabiliza falha e termina com exit code 1 quando há falhas. Não gera um Reel com trilha arbitrária. Para um artigo ainda sem registro Instagram, o aviso fica no log da sincronização: esta entrega não criou uma notificação no painel nem uma nova tabela de pendências. O artigo permanece elegível para uma execução futura. Não houve implementação de busca/download automático.

O sincronizador passa título/resumo limpos e tags do artigo. O preparador de redistribuição passa o contexto do artigo e mantém sua restrição de não reutilizar faixas; não foi executado para trocar posts. O método legado `poolsFor` continua disponível, mas não decide a seleção de `pick`.

## Validação e piloto

- `scripts/verify-track-themes.php`: 25 verificações com SQLite em memória e fixture de integridade isolada. Temas distintos, prioridade horror sobre nostalgia, ritmo explícito, duração, histórico, reservas, ignore IDs, hash inválido, catálogo inválido, arquivo ausente, faixa não classificada, limpeza de avisos, ausência de fallback e ausência de escrita pelo seletor. A fixture não é áudio; codecs são verificados no piloto real.
- As 45 classificações têm IDs distintos e hashes conferidos tanto com o manifesto original quanto com os 45 arquivos locais. Evidência em `catalog-validation.json` junto ao novo vídeo.
- PHPStan nível 5 dirigido ao seletor/piloto/sincronizador/redistribuição: zero erros. Suíte obrigatória `verify-changes.php`: 17 OK, zero falhas.
- `scripts/en-instagram-music-pilot.php`: consulta somente o banco local, escolhe para Diablo com ritmo slow e grava spec/evidência em storage. Não cria/atualiza posts. Comparação de todas as referências musicais antes/depois da seleção confirmou preservação.
- Faixa escolhida: **Dark Fantasy Ambient (Dungeon Synth)**, Pixabay ID **248213**, inicialmente sem uso no histórico local consultado; recorte **21–45 s**.
- Evidência da seleção: `storage/previews/reels-music/diablo-20261008-024849/` (nome técnico baseado no relógio do processo).
- Novo vídeo: `storage/previews/reels-drift/diablo-music-theme-v1/`. Duas exportações de 24 s, 1080×1920, H.264/yuv420p, AAC estéreo/48 kHz; 15 verificações passaram. A composição foi preservada para comparar somente a música.
- Scripts do Drift aceitam a especificação alternativa como quarto argumento. Exemplo:

```powershell
C:/xampp/php/php.exe scripts/verify-track-themes.php
C:/xampp/php/php.exe scripts/en-instagram-music-pilot.php
node scripts/reels-drift/pilot.mjs 'C:/Users/WINDOWS/Projects/tools/drift/v0.7.5/Drift/drift.exe' storage/previews/reels-drift/pasta-nova storage/previews/reels-music/pasta-da-selecao/spec.json
node scripts/reels-drift/verify.mjs storage/previews/reels-drift/pasta-nova
```

O helper PHP consulta o histórico real, mas não reserva a escolha em banco: é exclusivo para prévias. Artefatos e referências de agendados/publicados permaneceram preservados. Melhorar animação/composição, ampliar o acervo e reprocessar a fila não fizeram parte deste escopo.

Prévia local pelo cloudflared: https://nerd.tfr-info.com.br/uploads/previews/drift-diablo-musica-20261007/index.html . A cópia de vídeo é um novo arquivo; a prévia com a música anterior permanece disponível. Disponibilidade depende do computador, Apache e túnel ligados.

Player, capa e MP4 retornaram HTTP 200 e tipos corretos. Leitura parcial do vídeo retornou HTTP 206 com 1.024 bytes, permitindo reprodução pelo celular. Não foi realizado deploy na Hostinger nem envio à Meta.

Tarefas Forge reutilizadas #834 (seletor) e #837 (documentação); #882 (classificação) e #883 (validação/piloto). A consulta de segunda opinião via Codex CLI somente leitura não iniciou por restrições de acesso do sandbox; não foi usada como evidência de aprovação técnica.
