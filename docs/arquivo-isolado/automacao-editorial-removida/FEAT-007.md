# FEAT-007 - Categorias Automaticas e Templates Editoriais

## Objetivo

Evoluir a automacao editorial para decidir categoria automaticamente, criar categoria nova quando nenhuma existente combinar bem e exibir essa origem na tela de automacao.

## Arquivos Alterados

- `app/Controllers/Admin/EditorialAutomationController.php`
- `app/Repositories/EditorialAutomationCategoryRepository.php`
- `app/Repositories/PostRepository.php`
- `app/Services/Admin/EditorialAutomationService.php`
- `app/Views/admin/editorial-automation/index.php`
- `scripts/sql/create-editorial-automation-categories.sql`
- `docs/features/FEAT-007.md`

## Escopo

- Criar tabela `editorial_automation_categories`.
- Registrar categorias criadas pela automacao em tabela propria.
- Criar categoria nova apenas quando o tema nao tiver boa correspondencia com categorias existentes.
- Categoria criada pela automacao nasce ativa, indexavel e fora do menu (`exibir_no_menu = 0`).
- Exibir selo `Nova` na listagem de posts automaticos quando a categoria veio da automacao.
- Exibir bloco lateral com categorias criadas pela automacao.
- Adicionar tipos editoriais `review` e `explicativo`.
- Gerar prompt HTML com secoes por tipo de post, sem prender o template a Hardware/AM4.

## Banco De Dados

- Mudanca de schema: sim.
- Script: `scripts/sql/create-editorial-automation-categories.sql`.
- A aplicacao tambem executa `CREATE TABLE IF NOT EXISTS` localmente para permitir teste imediato.
- Mudanca de dados: sim, quando uma categoria nova for criada pela automacao.

## Impacto Em Producao

Medio. A mudanca cria uma tabela nova e pode criar categorias automaticamente quando a ferramenta for usada. As categorias novas nao entram no menu publico automaticamente.

## Afeta Rotas Criticas

Nao.

## Validacao Minima

- `php -l` nos arquivos PHP alterados.
- `git diff --check`.
- Acessar `/admin/automacao-editorial`.
- Gerar post com categoria existente e confirmar que nenhuma categoria nova foi criada.
- Gerar post com tema sem categoria existente clara e confirmar:
  - categoria nova criada em `categoria_post`;
  - registro criado em `editorial_automation_categories`;
  - categoria aparece no bloco lateral da tela;
  - categoria fica `exibir_no_menu = 0`;
  - post aparece com selo `Nova` na lista da automacao.
- Confirmar fallback quando IA local estiver indisponivel.

## Validacao Local Executada

- `php -l` executado com sucesso em:
  - `app/Controllers/Admin/EditorialAutomationController.php`
  - `app/Repositories/EditorialAutomationCategoryRepository.php`
  - `app/Repositories/PostRepository.php`
  - `app/Services/Admin/EditorialAutomationService.php`
  - `app/Views/admin/editorial-automation/index.php`
- `git diff --check` executado sem erros bloqueantes, apenas avisos CRLF existentes.
- Tabela `editorial_automation_categories` criada localmente via `ensureSchema`.
- Teste com rollback usando `Plataforma AM4 vs AM5: ainda vale investir no antigo?`:
  - usou categoria existente `Hardware`;
  - nao criou categoria nova;
  - rollback preservou contagem de posts e categorias automaticas.
- Teste com rollback usando `Como configurar um servidor caseiro com TrueNAS`:
  - criou categoria temporaria `Infraestrutura`;
  - registrou vinculo em `editorial_automation_categories`;
  - categoria nasceu com `exibir_no_menu = 0`;
  - rollback preservou contagem de posts, categorias e categorias automaticas.
- `getViewModel` da tela de automacao retornou dados sem erro e confirmou Ollama disponivel localmente.

## Status Stage/Producao

- Origem validada: nao validada.
- Paridade local -> stage: nao validada.
- Paridade stage -> pacote: nao validada.
- Producao: nao publicado.
- Apto para release: nao.
