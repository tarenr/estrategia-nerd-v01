# Release SEO — produção, 07/10/2026

Publicado e validado em [produção](https://estrategianerd.com.br/), após aprovação específica. Tarefas Forge #876–878, prefixo `[SEO Produção]`.

## Origem, pacote e backup

- Origem: cinco arquivos extraídos da **stage homologada**, com hashes novamente comparados aos registros da publicação anterior.
- Commit da implementação SEO: `de5bff3271a3c8ab1b6cc02d4d3bf76abfaa0b1c`.
- Pacote: `code_2026-10-07_seo-production_de5bff3`.
- ZIP SHA-256: `079c993f0f9c9be0f48cecb589171c9b47fb108c1064fa1ccfddda9c49f4cbb2`.
- Raiz de código em produção: `domains/estrategianerd.com.br/public_html/_app_core`.
- Backup dos arquivos substituídos: `storage/seo-production/seo-production-20261007-171046-2169c9/before/`.
- Evidências: na mesma pasta, `snapshot.json`, `application.json`, `verified-files.json`, `http-validation.json`, HTML de três artigos e capturas `production-375.png`/`production-1280.png`.

O snapshot confirmou que produção tinha exatamente a base pré-SEO usada na homologação de stage, nos cinco arquivos. Assim, os arquivos atuais de stage puderam ser promovidos integralmente, preservando as diferenças anteriores de hospedagem e evitando incluir funcionalidades presentes apenas no código local. Nenhuma alteração local anterior entrou no pacote.

Arquivos publicados:

1. `app/Repositories/PostRepository.php`
2. `app/Services/Site/PostService.php`
3. `app/Views/layouts/site.php`
4. `app/Views/site/post.php`
5. `public/assets/css/site.css` → `public_html/assets/css/site.css`

O publicador existente `ContentSyncManager::applyCode` foi chamado com destino `production`, respeitando a política existente de origem `stage`. A configuração da aplicação do pacote continha somente o perfil de produção e uma verificação explícita da raiz de destino. Antes do envio, os arquivos remotos foram novamente comparados ao backup para recusar mudanças concorrentes. Após o envio, **5/5 hashes** conferiram com o pacote e com a origem stage. Nenhum rollback foi necessário; o backup preserva a possibilidade de restaurar exclusivamente esses cinco arquivos.

## Validações

- Preflight completo aprovado; nenhuma falha de encoding ou conflito de merge.
- Testes específicos de SEO: **39/39**.
- Suíte obrigatória: **17/17**, PHPStan nível 5 sem erros.
- Lint dos quatro PHP do pacote: aprovado.
- Validação remota: **36/36**, três artigos do sitemap e revisão real em Chrome.
- Home, blog, sitemap e robots: HTTP 200.
- `/admin` e `/login`: HTTP 404, preservando a restrição já existente da hospedagem. Não foram reativados nem submetidos a login.
- Artigos: HTTP 200, canonical da produção, um `Article` e um `BreadcrumbList`, nomes/URLs/posições coerentes com o breadcrumb visível e autoria consistente.
- Datas de publicação válidas, sem `dateModified` artificial.
- OpenGraph/Twitter Cards únicos e consistentes, sem marcadores visuais no título; três imagens acessíveis com HTTP 200 e tipo `image/*`.
- Layout em 375/1280 px: breadcrumb sem overflow, capturas revisadas com fontes e imagens reais.

## Escopo preservado e limites

Não foram executadas migrations, sincronização editorial, alteração de credenciais, instalação de dependências ou atualização de uploads. As requisições aos artigos seguem a contagem normal de visualizações do site; não houve execução direta de escrita no banco. Stage foi usado somente como fonte e não recebeu novas alterações.

A entrega não comprova posições no Google nem solicita recrawl. `dateModified` continua omitido até existir uma fonte editorial confiável, conforme `docs/features/seo-artigos.md`. Pacote, backup e evidências não são versionados no Git; a documentação de publicação é versionada. Sem pendências da publicação aprovada.
