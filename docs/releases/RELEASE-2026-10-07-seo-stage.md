# Release SEO — somente stage, 07/10/2026

Publicado e validado em [stage](https://estrategianerd.com.br/stage/). Produção não foi alvo desta entrega. Tarefas Forge #869–872, prefixo `[SEO Stage]`.

## Pacote e preservação do estado

- Fonte SEO: commit `de5bff3271a3c8ab1b6cc02d4d3bf76abfaa0b1c`.
- Pacote: `code_2026-10-07_seo-stage_de5bff3`.
- ZIP SHA-256: `8071d79b435620abbba2ab3c29cc2bc03090ea922528fcff79251cb4a6f3f05c`.
- Raiz remota de código: `domains/estrategianerd.com.br/public_html/stage/_app_core`.
- Backup local dos cinco arquivos substituídos: `storage/seo-stage/seo-stage-20261007-165304-de47fc/before/`.
- Snapshot, SHA-256 individuais, patch, aplicação e hashes remotos confirmados: mesma pasta, arquivos `snapshot.json`, `seo.patch`, `application.json` e `verified-files.json`.

Foram enviados somente:

1. `app/Repositories/PostRepository.php`
2. `app/Services/Site/PostService.php`
3. `app/Views/layouts/site.php`
4. `app/Views/site/post.php`
5. `public/assets/css/site.css` (destino público `stage/assets/css/site.css`)

Stage tinha diferenças anteriores no repositório de posts e no carregamento de Tailwind do layout. Para não incluir funcionalidades fora do escopo ou substituir a configuração existente, os arquivos do pacote foram preparados aplicando **somente o patch SEO** sobre o snapshot remoto. `git apply --check` aprovou a aplicação, o diff confirmou exclusivamente cinco arquivos/97 inserções/22 remoções e os quatro PHP do pacote passaram no lint.

O pacote restrito segue o formato do publicador existente `ContentSyncManager::applyCode`, chamado explicitamente com destino `stage`; o perfil de produção foi removido da configuração em memória da operação. Antes de enviar, os hashes remotos foram novamente comparados com o backup. Após o envio, **5/5 hashes** conferiram com o pacote. Nenhuma credencial foi gravada no pacote ou relatório. O backup permite restaurar exclusivamente esses cinco arquivos caso necessário; não houve rollback.

Não foram executadas migrations, sincronização editorial ou alterações administrativas de dados. As requisições de validação aos artigos passam pelo comportamento normal de contagem de visualizações do site. Uploads, `.env`, configurações da hospedagem e alterações locais anteriores não entraram no pacote.

## Validação

- Preflight: aprovado, após corrigir falsos positivos; 14/14 testes de encoding.
- Suíte local obrigatória: 17/17, PHPStan nível 5 sem erros.
- Validação remota: **36/36 verificações**, registradas em `http-validation.json` na pasta de evidências.
- Home, blog, sitemap e robots: HTTP 200.
- `/admin` e `/login`: HTTP 404, conforme remoção anterior do acesso administrativo de stage; não foi realizado login nem reativado o admin.
- Três artigos do sitemap: HTTP 200, canonical correto, um `Article` e um `BreadcrumbList`, breadcrumb visual/estruturado coerente, autoria consistente, publicação válida e ausência de `dateModified` artificial.
- OpenGraph/Twitter Cards: únicos, consistentes, sem marcadores visuais no título; três imagens acessíveis com HTTP 200 e tipo `image/*`.
- Chrome real: 375/1280 px, breadcrumb sem overflow; capturas `stage-375.png` e `stage-1280.png` revisadas visualmente, com fontes e imagens reais.

Amostras: artigo sobre o setup do Estratégia Nerd, lore de Diablo e MSI MAG B650 Tomahawk WiFi. Nenhuma validação comprova posição no Google ou recrawl; foram verificadas as páginas e imagens disponíveis na hospedagem.

## Limites

Ao concluir esta etapa, produção aguardava plano e aprovação próprios. Foi posteriormente publicada em uma entrega específica: `docs/releases/RELEASE-2026-10-07-seo-production.md`. `dateModified` segue omitido pela ausência de fonte editorial confiável, conforme `docs/features/seo-artigos.md`. A correção do preflight permanece local. Os artefatos de backup/pacote/evidência ficam fora do Git; código da correção e documentação são versionados.
