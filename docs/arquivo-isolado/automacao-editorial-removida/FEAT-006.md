# FEAT-006 - Base Local da Automacao Editorial do Blog

## Objetivo

Criar a primeira versao da automacao editorial do blog, sem Instagram, permitindo gerar um post completo como rascunho a partir de um unico tema informado pelo usuario.

## Contexto

O planejamento da automacao editorial foi registrado em `docs/features/FEAT-005.md`. Esta etapa implementa apenas a base do blog: tela administrativa simplificada, inferencia automatica de campos editoriais, geracao de HTML editorial por IA local quando disponivel, SEO basico e capa automatica por template local.

## Arquivos Alterados

- `config/routes.php`
- `app/Controllers/Admin/EditorialAutomationController.php`
- `app/Services/Admin/EditorialAutomationService.php`
- `app/Views/admin/editorial-automation/index.php`
- `app/Views/components/admin/sidebar.php`
- `.env.example`
- `.env` local
- `docs/features/FEAT-006.md`

## Escopo Desta Etapa

- Rota `GET /admin/automacao-editorial`.
- Rota `POST /admin/automacao-editorial`.
- Formulario com apenas o tema do post.
- Inferencia automatica de categoria.
- Inferencia automatica de formato.
- Inferencia automatica de palavras-chave, fontes de checagem e observacoes editoriais.
- Integracao opcional com Ollama local para gerar conteudo HTML longo.
- Resumo, SEO, tags, categoria, slug e tempo de leitura continuam preenchidos automaticamente pelo sistema.
- Fallback automatico para template local quando Ollama estiver indisponivel, sem modelo ou com resposta invalida.
- Marcacao dos novos posts automaticos com a tag `automacao-editorial`.
- Listagem dos ultimos posts criados pela ferramenta na propria tela.
- Criacao de post com status `rascunho`.
- HTML inicial com aviso editorial para revisao.
- SEO title, SEO description, tags e tempo de leitura basicos.
- Capa local em `uploads/posts/{slug}/images/capa-auto.webp` quando GD/WebP estiver disponivel.
- `imagem_capa` e `imagem_thumb` preenchidas com a capa automatica quando a geracao estiver disponivel.
- Fallback para PNG quando WebP nao estiver disponivel.
- Sem publicacao automatica.
- Sem Instagram.

## Banco De Dados

- Mudanca de schema: nao
- Mudanca de dados: sim, somente quando o formulario for executado e sempre criando post `rascunho`.
- Script necessario: nao

## Dependencias

- Tabela `posts` existente.
- Tabela `categoria_post` com ao menos uma categoria ativa.
- Extensao PHP GD para gerar capa. Sem GD, o rascunho ainda pode ser criado sem capa.
- Ollama local opcional para geracao por IA:
  - `OLLAMA_BASE_URL`
  - `OLLAMA_MODEL` recomendado: `llama3.2:3b`
  - `OLLAMA_TIMEOUT`
- O modelo `qwen3:4b` foi testado, mas nao foi adotado como padrao local porque respondeu com raciocinio longo/JSON instavel neste ambiente.

## Impacto Em Producao

Medio. A rota cria dados editoriais quando acionada por usuario autenticado. Nao publica conteudo automaticamente.

## Afeta Rotas Criticas

Nao.

## Risco

Baixo a medio. O maior risco e criacao de rascunhos incompletos ou capas simples demais. A mitigacao e manter todos os posts como `rascunho` e exigir revisao pela tela normal de edicao.

## Validacao Minima

- `php -l` nos arquivos PHP novos/alterados.
- `git diff --check`.
- Acessar `/admin/automacao-editorial` autenticado.
- Gerar rascunho local.
- Confirmar redirecionamento para `/admin/editar-post?id={id}`.
- Confirmar que o status do post criado e `rascunho`.
- Confirmar que o post aparece em `/admin/automacao-editorial`.
- Confirmar status de IA local na tela.
- Com Ollama ativo e modelo `llama3.2:3b`, confirmar `ai_status=ai` no log `editorial_automation`.
- Com Ollama ativo, confirmar que o conteudo gerado passa de 1200 caracteres de texto.
- Com Ollama inativo, confirmar fallback por template sem quebrar a criacao.
- Confirmar que nenhuma publicacao externa e executada.

## Validacao Local Executada

- `ollama pull llama3.2:3b` executado com sucesso.
- Teste curto de JSON com `llama3.2:3b` respondeu corretamente.
- Teste de criacao de rascunho com rollback:
  - tema: `Plataforma AM4 vs AM5: ainda vale investir no antigo?`
  - status gerado: `rascunho`
  - `ai_status`: `ai`
  - modelo: `llama3.2:3b`
  - conteudo HTML validado acima de 1200 caracteres
  - capa gerada por template local
  - contagem de posts antes/depois preservada pelo rollback

## Status Stage/Producao

- Origem validada: nao validada.
- Paridade local -> stage: nao validada.
- Paridade stage -> pacote: nao validada.
- Producao: nao publicado.
- Apto para release: nao.
