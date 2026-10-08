# Conteúdo revisado — stage, 08/10/2026

Entrega aplicada exclusivamente em [stage](https://estrategianerd.com.br/stage/blog): dez artigos e 21 arquivos de imagem. Produção, fila do Instagram, vídeos e código remoto não foram alterados nesta entrega.

## Artigos

| Artigo | ID stage | Operação | Estado final |
|---|---:|---|---|
| Guerra Eterna | 15 | Atualizar referências e capas aprovadas | publicado |
| Lore de Diablo | 17 | Atualizar referências e capas aprovadas | publicado |
| Mundo de Diablo | 11 | Atualizar referências e capas aprovadas | publicado |
| Arcanjos | 13 | Atualizar referências e capas aprovadas | publicado |
| Males do Inferno | 12 | Atualizar referências e capas aprovadas | publicado |
| Nefalem | 14 | Atualizar referências e capas aprovadas | publicado |
| MSI MAG B650 Tomahawk | 21 | Atualizar capa e thumbnail | publicado |
| Kingston NV3 | 25 | Cadastrar artigo local revisado | publicado |
| The Witcher 3 | 26 | Cadastrar artigo local revisado | publicado |
| Windows 11 24H2 | 27 | Cadastrar artigo local revisado | rascunho |

Os sete artigos existentes preservaram seus textos editoriais de stage. Foram substituídas somente as referências às artes aprovadas e as capas/thumbnails. URLs absolutas antigas dos próprios arquivos passaram a ser relativas ao ambiente, incluindo as referências que apontavam para localhost. Imagens adicionais ainda não refeitas continuam usando os arquivos originais de stage.

NV3, Witcher e Windows estavam ausentes em stage; sua criação recebeu aprovação específica antes da escrita. Foram mantidos os slugs locais, as revisões de conteúdo e SEO e os estados editoriais. Categoria e autoria foram mapeadas por slug/nome, sem reutilizar IDs locais indiscriminadamente. Contadores locais não foram importados.

## Execução e preservação

O publicador CLI `scripts/reels-review/stage-content.php` aceita `--inspect`, `--prepare`, `--apply` e `--verify`. Sua configuração em memória admite somente local e stage; a raiz FTP deve terminar em `/stage/uploads`. Ele não é uma sincronização geral: depende dos manifestos locais das revisões aprovadas de Diablo, hardware e conteúdo temporal e exige exatamente os dez slugs.

Evidências locais, fora do Git: `storage/stage-content/stage-20261008-v2/`.

- `package.json`: estado anterior completo de posts/categorias, alterações selecionadas, arquivos e SHA-256.
- `original-*.bin`: backups dos arquivos anteriores encontrados em stage.
- `application.json`: IDs finais e momento da aplicação.
- `uploaded-*.bin`, `verified-*.bin`, `original-checked-*.bin`: conferências dos arquivos enviados e dos originais preservados.
- `verified.json`: conclusão das verificações de integridade e preservação.

O primeiro pacote, em `storage/stage-content/stage-20261008/`, também foi preservado. Apenas o pacote v2 foi aplicado; ele inclui a normalização das referências legadas. Esses backups contêm dados editoriais e não devem ser expostos publicamente nem versionados.

Arquivos novos têm caminhos próprios; arquivos existentes divergentes não são sobrescritos. Atualizações de banco são transacionais, limitadas aos campos selecionados e interrompidas se houver conflito editorial com o snapshot. Categorias, autoria existente e demais artigos foram conferidos sem alterações. Reaplicar o mesmo pacote reconhece os campos já aplicados; `--prepare` não sobrescreve um pacote existente.

Uma recuperação exige plano próprio: usar os valores anteriores do pacote para restaurar somente os campos alterados; os três cadastros novos precisam de tratamento específico, preservando qualquer edição posterior. Não executar exclusões ou restaurações gerais. Nenhum rollback foi necessário nesta entrega.

## Validação realizada

- Dez registros e seus campos finais conferidos no banco de stage.
- 21/21 arquivos enviados conferidos por SHA-256 e por HTTP 200 com tipo `image/*`.
- Arquivos originais e artigos fora do escopo preservados; categorias inalteradas.
- Nove páginas publicadas com HTTP 200, canonical de stage, OpenGraph com capa de stage, JSON-LD presente e nenhuma referência a localhost no HTML.
- Windows: HTTP 404 esperado, pois permanece rascunho; conteúdo conferido pelo banco.
- Navegador real a 390 px: capas de Mundo de Diablo e B650 carregadas, proporção original mantida, sem overflow horizontal. B650: 1200×800 exibida em aproximadamente 298×198; Diablo: 1672×941 em aproximadamente 298×168.
- Lint específico do publicador: aprovado.
- Suíte obrigatória `scripts/verify-changes.php`: 17/17, PHPStan nível 5 sem erros.

As requisições públicas podem incrementar os contadores normais de visualização; esses contadores não são utilizados como indicadores de conflito editorial.

## Compatibilidade e pendências

Stage não possui a coluna `tipo_post`; o campo foi omitido sem migration. O próximo artigo indicado no cadastro local do Windows não existe em stage; `proximo_post_id` foi mantido nulo para evitar um vínculo incorreto. Não foram criados artigos fora do escopo.

A promoção deste pacote para produção continua pendente de plano e aprovação próprios. A revisão das artes adicionais de Diablo permanece na tarefa #898. Esta entrega não publica nem reagenda Instagram; a agenda aprovada foi tratada separadamente em `docs/features/agenda-diablo-202611.md`.

Forge: #908 preparação/backup, #909 envio/preservação e #910 validação/documentação/Git. A tarefa #889 mantém registrada a pendência de promoção para produção após a validação de stage.
