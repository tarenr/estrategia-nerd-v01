# Release — imagens adicionais de Diablo em produção

Data: 08/10/2026. Origem: stage homologado. Destino: produção. Branch: `main`.

## Resultado

Dez imagens internas adicionais promovidas, incluindo `09-mesa-v2.webp` sem a faixa branca externa. Correção permanente de `PostService::assetPathCandidates` promovida para o núcleo ativo `_app_core`, permitindo resolver uploads da pasta pública sem cópias extras no núcleo.

| Artigo em produção | ID | Imagens | Status preservado | Data de publicação preservada |
|---|---:|---:|---|---|
| Os Males do Inferno | 12 | 4 | publicado | 15/04/2026 11:43 |
| Os Arcanjos do Céu | 13 | 2 | publicado | 15/04/2026 23:32 |
| A Lore Completa de Diablo | 17 | 4 | publicado | 23/04/2026 14:54 |

Datas acima reproduzem os registros do banco. Nenhum novo agendamento foi criado. Somente referências de imagem em `posts.conteudo` foram atualizadas; o comportamento automático de `data_atualizacao` foi mantido. Textos, SEO, capas/thumbnails, categorias, status, datas e campos editoriais dos demais artigos foram preservados. Não houve alterações no Instagram, vídeos, dependências ou serviços.

## Operação e proteção

Script específico: `scripts/reels-review/diablo-additional-production.php`, modos `--prepare`, `--apply` e `--verify`. O pacote captura produção, usa imagens e código de stage, exige dez hashes e três slugs/IDs, valida a versão ativa antes da substituição e interrompe diante de divergências. Arquivos de imagem anteriores não são sobrescritos. O código é substituído por rename de arquivo temporário no mesmo diretório. O conteúdo editorial de produção é a base da transformação, com atualização transacional restrita aos três artigos.

Código ativo: `/domains/estrategianerd.com.br/public_html/_app_core/app/Services/Site/PostService.php`. A cópia legada fora de `_app_core` foi preservada. SHA-256 promovido: `6f6515202bb662fd23f62072036114eba86dc29cec746ea7a78f3501a4bb59fa`.

Backups e provas ficam exclusivamente em `storage/previews/diablo-adicionais-production-20261008/`: snapshots dos artigos, backup de código anterior, pacote de stage, registros de aplicação/verificação e validação HTTP. Esses dados não entram no Git. Rollback exige aprovação própria e comparação com alterações posteriores; nenhum rollback foi necessário.

## Validação

- `--prepare`, `--apply` e `--verify` concluídos sem divergências.
- Dez hashes remotos FTP e HTTP iguais ao pacote aprovado; dez respostas HTTP 200.
- Três páginas públicas com as dez imagens carregadas, dimensões naturais 1672 × 941, em desktop de 1280 px e móvel de 390 × 844; sem overflow horizontal.
- Mapa v2 inspecionado visualmente no artigo, sem faixa branca externa.
- PHP lint do script e `git diff --check` aprovados.
- Suíte `scripts/verify-changes.php`: 17 testes aprovados, zero falhas, antes e depois da aplicação; PHPStan nível 5 sem erros.

Tarefas Forge: #919 (backup), #920 (promoção), #921 (validação), #922 (documentação/Git). Implementação do resolvedor e artes previamente versionadas em `2b7a43879eb63326c00740c86d262b1a12f0e448`; esta release registra a promoção. Alterações existentes de outras tarefas foram preservadas e excluídas do commit.

Não foram identificadas pendências técnicas dentro do escopo promovido.
