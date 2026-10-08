# Revisão temporal — Windows e The Witcher

## Escopo aplicado em 8 de outubro de 2026

Revisão dos artigos locais encontrados pelos slugs existentes: The Witcher (ID local 61) e Windows (ID local 62). IDs do snapshot antigo eram 29/30; o script seleciona por slug e valida a identidade antes de gravar. URLs, imagens, categorias, tags, status, agenda, relações e demais campos são preservados.

Campos alterados em `posts`: `titulo`, `resumo`, `conteudo`, `seo_title`, `seo_description`; `data_atualizacao` pode mudar automaticamente no MySQL. Operação exclusivamente local, sem migration.

The Witcher deixa de anunciar uma chegada futura e informa lançamento em 29/09/2026. A gratuidade é condicionada à posse do jogo e ao mesmo ecossistema de plataforma. Resumo e SEO acompanham a revisão; referências oficiais do lançamento e notas de atualização foram acrescentadas. O slug histórico é preservado para não quebrar links.

Windows mantém a data de 13/10/2026 para 24H2 Home/Pro, sem apresentá-la como um evento necessariamente futuro. Preserva a distinção de Enterprise/Education, com 12/10/2027, e orienta a versão com suporte oferecida ao dispositivo pelo Windows Update. Não promete que uma versão específica será oferecida a todos. O artigo local permanece rascunho; não foi publicado para contornar esse estado.

## Backup e recuperação

`php scripts/reels-review/temporal-blog-content.php --apply` salva registros originais e campos desejados em `storage/previews/reels-review/temporal-20261008/backup.json`, sem sobrescrever o backup. O UPDATE usa transação e bloqueio dos dois registros e recusa mudanças concorrentes nos campos editoriais. Verifica que os demais campos atuais não mudaram durante a operação.

`--verify` confere todos os cinco campos. `--rollback` restaura somente esses campos do backup, preservando alterações posteriores em outros campos e recusando conflito editorial. `--inspect` lê registros locais. Nunca passa um ambiente remoto ao script.

## Vídeos e observações

Roteiros 208 e 209 usam as mesmas imagens e trilhas anteriores, com texto revisado. A revisão do título é declarada em `reviewedTitle` no storyboard e propagada ao vídeo e ao título da galeria ao executar `batch.mjs --revise`. Snapshot original e versões anteriores são preservados. Os avisos de revisão temporal foram removidos após a correção e verificação dos artigos locais. As agendas originais, 27 e 28 de outubro, não foram alteradas.

Os vídeos da galeria são prévias: não atualizam a fila do Instagram. Produção e stage ainda precisam receber e validar as revisões antes de qualquer publicação real.

## Fontes verificadas

## Validação concluída

- `--apply` e `--verify`: cinco campos dos dois artigos locais conferidos; slugs e campos fora do escopo preservados.
- Artigo Witcher HTTP 200, com título corrigido e condições do upgrade; Windows conferido no banco mantendo o estado rascunho.
- Vídeos 208/209 em revisão v3, 28 segundos, folhas de contato examinadas visualmente. Galeria HTTP 200 com as novas versões e sem os dois avisos resolvidos.
- `verify.mjs`: 30 tratamentos editoriais aprovados. `batch.mjs --verify`: 30 vídeos completos, áudio audível e originais preservados.
- PHP lint, Node syntax e `git diff --check`: aprovados.
- Suíte obrigatória: 17 testes aprovados, zero falhas, incluindo PHPStan e renderização dos três ambientes.

## Referências

- https://www.thewitcher.com/es/en/news/52054/the-witcher-3-the-wild-hunt-remastered-is-available-now
- https://www.thewitcher.com/es/en/news/52041/see-whats-new-in-the-witcher-3-wild-hunt-remastered
- https://learn.microsoft.com/en-us/windows/release-health/windows11-release-information
