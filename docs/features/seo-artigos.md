# SEO dos artigos — dados estruturados e compartilhamento

Entrega local de 07/10/2026, tarefas Forge #338, #339 e #340. Reaproveita o motor existente; não cria dependências, tabelas ou metadados paralelos. Stage foi publicado e validado em uma etapa posteriormente aprovada, tarefas #869–872: `docs/releases/RELEASE-2026-10-07-seo-stage.md`. Produção não foi publicada.

## Caminho de navegação

`PostService` gera uma única lista: Início → Blog → Categoria → Artigo. Sem categoria vinculada com nome e slug, omite essa etapa. A categoria usa a rota existente `/blog/{slug}`, com codificação do segmento. A mesma lista alimenta o breadcrumb acessível em `site/post.php` e o schema `BreadcrumbList`, com posições consecutivas e URL do artigo igual à canonical. A página atual recebe `aria-current="page"`. O estilo em `site.css` permite quebra de linhas e de palavras longas, sem truncar o título.

## Article, autoria e datas

- `Article` permanece centralizado no serviço e no layout público. `headline` usa o título editorial, enquanto a página e os metadados sociais preferem o título SEO.
- O repositório consulta somente `usuarios.nome` pelo `autor_id`, na mesma conexão do conteúdo. Não envia login, e-mail ou credenciais. Nome preenchido representa `Person`; perfil com o mesmo nome da marca representa `Organization`. Sem nome/perfil disponível, usa a organização editorial. O artigo exibe a mesma autoria. Falha da consulta opcional não interrompe a leitura do post e registra uma mensagem genérica.
- `datePublished` aceita o formato persistido `Y-m-d H:i:s`, valida o calendário e usa o fuso configurado da aplicação. Datas ausentes, zeradas ou inválidas são omitidas. Nunca substituir por “agora”.
- **`dateModified` é omitido:** `posts.data_atualizacao` tem `ON UPDATE current_timestamp()` e muda também com visualizações/curtidas. Esse campo não comprova atualização editorial. Expor a data como edição seria enganoso; uma futura fonte confiável exige planejamento próprio, sem migration nesta entrega.
- FAQ não foi implementado. O Google encerrou os resultados enriquecidos de FAQ em maio de 2026: [registro oficial](https://developers.google.com/search/updates#may-2026).

## Compartilhamento

OpenGraph e Twitter Cards continuam sendo emitidos uma única vez por `layouts/site.php`:

- Título SEO → título editorial com nome do site.
- Descrição SEO → resumo → descrição padrão.
- Imagem de capa → thumbnail → logo da marca no compartilhamento.
- A URL compartilhada é a canonical do artigo; imagens locais são convertidas para URLs da aplicação e imagens HTTP(S) externas são preservadas.

A limpeza remove HTML e marcadores visuais `[[...]]` antes de escolher as alternativas. Campos que ficam vazios após a limpeza também usam a alternativa. O texto destacado do H1 permanece preservado. O JSON-LD usa os flags `JSON_HEX_*`, evitando que conteúdo contendo `</script>` encerre o bloco. Não gerar um segundo conjunto de tags para corrigir o primeiro.

## Validação

```powershell
C:\xampp\php\php.exe scripts/verify-seo.php
C:\xampp\php\php.exe scripts/verify-changes.php
git diff --check
```

`verify-seo.php` executa **39 verificações** com SQLite em memória e renderização real do serviço, template e layout. Não carrega bootstrap/.env nem faz HTTP. A fixture usa id zero para não executar os contadores de leitura. Valida autoria, datas e fuso, alternativas, categoria ausente, URLs codificadas, imagens externas, canonical, metadados únicos, coerência visual/schema e conteúdo com delimitador de script. Testa também a degradação da consulta opcional de autoria quando a tabela não está disponível.

O argumento opcional `--preview` salva HTML sintético em uma pasta única de `storage/previews/seo/`. O comportamento normal mantém as fixtures somente em memória. A revisão de layout usa os CSS locais com rede bloqueada; por isso imagens fictícias/fontes remotas não carregam nessas prévias.

Revisão em Chrome headless: larguras **375 e 1280 px**, título normal e título longo (incluindo palavra sem espaços), quatro casos sem overflow do breadcrumb. Capturas em `storage/previews/seo/seo-20261007-163524-f46cef/`. A revisão visual confirmou legibilidade, hierarquia e quebra de linhas. Esses arquivos de evidência não são versionados.

Critério: testes específicos sem falhas, suíte geral com 17 aprovações e PHPStan nível 5 sem erros, lint dos PHP alterados e diff sem erros de whitespace. Nenhum post real foi modificado pelos testes específicos; nenhum deploy ou envio a rede social foi realizado. A validação do HTML publicado e do acesso dos crawlers às imagens fica para o plano de deploy.
