# Nova agenda de Diablo — novembro de 2026

Plano e datas aprovados em 08/10/2026. Horário: America/Sao_Paulo, 19h30. A ordem é editorial: apresentação do universo, forças do Céu e do Inferno, conflito, humanidade e conclusão com a lore completa.

| Data | Tema | Origem | Entrada na fila |
|---|---|---|---|
| 02/11/2026 | O mundo de Diablo | 191 | 224 |
| 03/11/2026 | Os Arcanjos | 193 | 225 |
| 04/11/2026 | Os Males do Inferno | 192 | 226 |
| 05/11/2026 | A Guerra Eterna | 195 | 195 |
| 06/11/2026 | Os Nefalem | 194 | 227 |
| 07/11/2026 | A lore completa | 197 | 197 |

## Status e histórico

O usuário informou ter excluído os quatro Reels anteriores. Os registros 191–194 passam de `publicado` para `rascunho`, com observação explícita da exclusão em `error_log` e identificação da nova entrada. Não existe estado `excluido` no schema atual; não foi criada migration. Datas da publicação original, IDs da Meta, links, legendas, mídias e métricas foram mantidos como histórico; esses registros não fazem parte dos publicados nem da seleção do publicador automático.

As quatro republicações têm novas entradas e chaves de idempotência determinísticas, sem reaproveitar IDs ou containers da Meta. Os registros 195 e 197 ainda não tinham sido publicados e foram reagendados, retirando os vídeos antigos de 08/10 e 11/10. Ninguém será publicado antes da nova agenda por esta operação. Os demais posts foram preservados.

Os caches `config/instagram-feed.json` e `storage/cache/instagram_feed.json` deixam de exibir os quatro itens excluídos. Outros dados preexistentes são preservados. A galeria informa as novas datas e os IDs atuais da fila nos comentários dos seis vídeos.

## Arquivos e banco local

Manifesto versionado: `resources/reels-review/diablo-schedule-20261008.json`. Os seis MP4 aprovados e JPEG correspondentes foram copiados para `public/uploads/reels/diablo-agenda-20261008`, com nomes derivados do hash. Originais e prévias permanecem intactos. Vídeos 1080 × 1920, 28 segundos; áudio e início da faixa correspondem ao vídeo aprovado, com trilha celestial nos Arcanjos e fantasia sombria nos demais.

`scripts/reels-review/diablo-reschedule.php --apply` atua exclusivamente no banco local, em `instagram_posts` e `instagram_post_media`. Usa o mesmo lock do publicador, bloqueia os seis registros, valida identidade/estado e verifica dias livres antes da transação. Copia legendas existentes sem acrescentar numeração de capítulos. Atualiza mídia, duração e música dos dois reagendados; cria uma mídia para cada nova entrada. Não apaga registros ou arquivos, nem chama a Meta.

`--verify` confere datas, status, chaves de publicação vazias, mídia/áudio, capa JPEG válida, seleção de vídeo pelo serviço usado pelo publicador, dias sem conflito, histórico original e demais agendamentos. `--apply` repetido após sucesso apenas verifica o resultado, sem duplicar entradas.

Backups/evidências em `storage/previews/reels-review/diablo-agenda-20261008`: registros e mídias originais, hashes, demais agendamentos, caches anteriores, manifesto anterior da galeria e mapeamento da aplicação. Preservar esses arquivos. Para desfazer, elaborar plano específico para suspender as novas entradas e restaurar somente os campos necessários; não restaurar automaticamente datas vencidas ou estado publicado dos itens excluídos, pois isso pode reativar vídeos antigos e informação incorreta.

## Validações concluídas

- `--verify` e repetição de `--apply`: seis entradas conferidas e nenhuma duplicidade criada.
- Consultas pelo repositório usado no painel/publicador: os quatro excluídos não aparecem nos publicados; nenhum dos dez IDs relacionados está vencido na fila; calendário de novembro tem os seis novos agendamentos na ordem aprovada.
- Uma publicação por dia, sem conflitos; demais agendamentos e vídeos anteriores preservados por comparação com backup.
- Capas JPEG decodificáveis e vídeos prontos segundo os mesmos serviços usados pelo publicador. SHA-256 das cópias igual ao dos vídeos aprovados já validados integralmente.
- Seis vídeos e seis capas pelo túnel público: 12 respostas HTTP 200, tipos `video/mp4` e `image/jpeg`, sem página de login.
- Dois caches conferidos sem os quatro posts excluídos. Galeria HTTP 200 com as datas de 02/11 a 07/11.
- PHP lint e `git diff --check` aprovados; suíte obrigatória: 17 testes aprovados, zero falhas, incluindo PHPStan.

## Escopo remoto

Esta operação prepara a fila para execução pelo publicador existente nas datas aprovadas; não executa publicação imediata, sincronização Meta, alteração do Task Scheduler ou envio a Hostinger. Stage e produção dos blogs ainda exigem suas próprias entregas. Os vínculos `post_blog_id` da fila correspondem aos artigos de produção; IDs locais diferentes não foram usados para substituí-los.
