# Dez imagens internas adicionais de Diablo

Preparação em 08/10/2026. O conjunto foi aprovado, aplicado e homologado em local/stage e depois promovido para produção com aprovação própria, conforme as etapas abaixo. Vídeos não foram alterados.

Galeria: https://nerd.tfr-info.com.br/uploads/previews/diablo-adicionais-20261008/index.html

As dez versões têm 1672 × 941 pixels e nomes versionados. O manifesto em `resources/reels-review/assets/diablo-adicionais/manifest.json` registra o original, hash, método e resultado. Cópias exatas dos dez originais estão na galeria, com sufixo `-antes.webp`. O script recusa sobrescrever arquivos existentes e verifica que os originais não mudaram.

Auriel, Itherael e a cena de Diablo foram editados com a ferramenta nativa imagegen, usando cada original como alvo e `art-12-mesa-v1.webp` como referência de mesa, luz e materiais. Os mestres PNG foram copiados para o projeto. Prompts: preservar personagem e composição completos, sem cortes, sem personagens ou texto novos, pergaminho sobre mesa antiga visto de cima, dimensão 1672 × 941. Para Diablo, converter a mesma cena em gravura sépia, preservando chifres, braços e a pequena figura com espada abaixo. Não utilizar as figuras da referência como conteúdo.

Andariel, Duriel, Belial, Azmodan e Inarius/Lilith preservam os desenhos e inscrições existentes por composição proporcional. O grupo de Arcanjos reutiliza a arte já aprovada. O mapa preserva a imagem original integral, incluindo bordas, inscrições e geografia; não foi redesenhado pela IA.

Validação: dez dimensões conferidas, dez hashes originais preservados, inspeção visual das propostas, galeria e vinte arquivos com HTTP 200 pelo túnel. Suíte obrigatória: 17 testes aprovados, zero falhas. A primeira execução no sandbox falhou por acesso a sessões e MySQL; a mesma suíte passou ao executar com as permissões necessárias.

Pendências identificadas ao fim da preparação: aprovação visual, snapshots, substituição das dez referências, homologação e documentação/Git. Aprovação, snapshots de local/stage, aplicação e homologação foram concluídos nas etapas abaixo. A publicação em produção permanece pendente de plano próprio. As tarefas 898 e 915–918 acompanham esse trabalho.

## Aplicação em local e stage — 08/10/2026

Lote aprovado nesta sessão, incluindo a correção do mapa para `09-mesa-v2.webp`, sem a faixa branca externa. A v1 e os originais foram preservados. O resultado final foi composto com os pixels do mapa original; o interior não foi redesenhado pela IA.

O script `scripts/reels-review/diablo-additional-blog-images.php` admite exclusivamente local e stage, exige dez imagens e três slugs, confere dimensões e hashes, salva snapshots e atualiza apenas `posts.conteudo`. Capas, thumbnails, textos editoriais, status e datas de publicação foram preservados. `data_atualizacao` segue o comportamento automático do MySQL. Não houve alteração em produção ou na fila do Instagram.

Snapshots e evidências: `storage/previews/diablo-adicionais-stage-20261008/`, incluindo `references-local.json`, `references-stage.json`, `verified-local.json`, `verified-stage.json` e `http-images-stage.json`. Os backups de artigos contêm dados editoriais e não devem ser expostos nem versionados. Uma recuperação exige plano próprio para restaurar exclusivamente as referências, preservando alterações posteriores.

Aplicação concluída nos IDs locais 12, 13 e 20 e em stage 12, 13 e 17. Dez arquivos novos enviados ao FTP público de stage e relidos com SHA-256 igual à origem. Não foram sobrescritos arquivos anteriores. O primeiro envio falhou por resolução relativa após `ftp_chdir`; o script foi corrigido para usar destino absoluto antes da repetição. Nenhum artigo remoto foi alterado na tentativa que falhou.

Suíte obrigatória após aplicação: 17 testes aprovados, zero falhas, incluindo PHPStan nível 5. Essa suíte não detecta o bloqueio visual encontrado na homologação.

**Bloqueio encontrado na primeira homologação:** a página pública da Lore omitia as quatro novas imagens apesar de os arquivos estarem disponíveis. A implementação remota de `PostService::assetPathCandidates` procurava em `_app_core` e na raiz do domínio, sem incluir a pasta pública `stage/uploads`. O mapa respondeu HTTP 200 com `Content-Type: image/webp`, mas estava ausente nas pastas de uploads do núcleo. A correção de código recebeu aprovação separada, conforme registrado abaixo.

## Correção permanente do resolvedor e homologação

`app/Services/Site/PostService.php`, exclusivamente em `assetPathCandidates`, passa a considerar a pasta pública ao lado do núcleo `_app_core` e o diretório físico do front controller `index.php`. Assim, instalações em subdiretórios resolvem seus próprios uploads mesmo quando `DOCUMENT_ROOT` aponta para a raiz do domínio. As alternativas anteriores foram preservadas; não há necessidade de duplicar uploads dentro do núcleo ou intervir a cada novo lote.

Somente esse arquivo de código foi enviado para `stage/_app_core`. Antes do envio, sua versão remota foi comparada ao HEAD esperado (normalizando apenas finais de linha), copiada para backup e relida para detectar mudanças concorrentes. O SHA-256 após o envio corresponde ao arquivo local. Evidências adicionais na mesma pasta: `PostService-stage-before.php`, `resolver-stage-deployment.json` e `resolver-validation.json`. Para recuperação, elaborar plano específico para restaurar somente o arquivo remoto de backup; nenhum rollback foi necessário.

Validações concluídas:

- 12 verificações direcionadas do resolvedor: imagens existentes e ausentes, referências relativas e absolutas; instalação local, núcleo embutido na raiz, núcleo de stage em subdiretório e front controller separado.
- PHP lint do arquivo corrigido e `git diff --check` aprovados; suíte obrigatória 17/17, incluindo PHPStan nível 5 sem erros.
- Arquivos e referências de stage revalidados pelo script específico após o envio do código: dez hashes iguais à origem; outros campos e referências preservados.
- Navegador real: duas imagens adicionais carregadas em Arcanjos, quatro em Males do Inferno e quatro na Lore, todas com dimensão natural 1672 × 941. Mapa v2 presente no artigo, sem a faixa branca externa.
- Sem overflow horizontal nos três artigos em desktop; Males do Inferno também conferido a 390 × 844, com as quatro imagens carregadas e sem overflow. A moldura editorial existente foi preservada.

Stage está homologado para este lote. Produção continua fora do escopo e não recebeu as dez imagens adicionais nem esta correção de código. As tarefas Forge #917 e #918 acompanham implementação/homologação e documentação/Git, respectivamente. A publicação em produção exige plano e aprovação próprios. Backups editoriais, fixtures de verificação e evidências operacionais permanecem locais, fora do commit.

## Promoção para produção — 08/10/2026

Após aprovação específica, as dez imagens adicionais (incluindo mapa v2) e a correção homologada do resolvedor foram promovidas exclusivamente de stage para produção. O script `scripts/reels-review/diablo-additional-production.php` prepara o pacote e backups (`--prepare`), aplica (`--apply`) e verifica (`--verify`). Ele exige os três slugs e IDs 12, 13 e 17, valida hashes e caminhos, recusa divergências e transforma somente atributos de imagem do conteúdo original de produção. Não copia o texto editorial de stage.

O código ativo fica em `public_html/_app_core/app/Services/Site/PostService.php`, conforme o front controller; a cópia legada em `public_html/app` não foi alterada. A substituição usou arquivo temporário no mesmo diretório e rename, após backup e comparação da versão ativa. Dez arquivos com nomes versionados foram adicionados a `public_html/uploads`; os originais foram preservados. A única atualização SQL foi `posts.conteudo` nos três IDs, dentro de transação. Capas, SEO, textos, categorias, status e datas de publicação foram conferidos contra o snapshot; `data_atualizacao` segue o comportamento automático do banco. Os demais artigos tiveram seus campos editoriais conferidos e preservados.

Evidências privadas: `storage/previews/diablo-adicionais-production-20261008/`, com `snapshots.json`, `package.json`, `PostService-production-before.php`, `PostService-stage-approved.php`, dez WebPs, `application.json`, `verified.json` e `http-images.json`. Não versionar nem expor snapshots. Recuperação exige plano próprio, restaurando apenas as referências e/ou o código necessário e preservando mudanças posteriores; nenhum rollback foi executado.

Validação: dez hashes por FTP e HTTP iguais a stage, dez HTTP 200, imagens carregadas com dimensões naturais 1672 × 941 nos três artigos em navegador desktop (1280 px) e móvel (390 × 844), sem overflow horizontal. O mapa v2 foi inspecionado no artigo, sem a faixa branca externa. Suíte obrigatória antes e depois da aplicação: 17/17, zero falhas; lint do script aprovado. Forge #919–922 acompanha backup, promoção, validação e documentação/Git. Instagram, seus agendamentos e serviços não foram alterados. Registro da entrega: `docs/releases/RELEASE-2026-10-08-diablo-adicionais-production.md`.
