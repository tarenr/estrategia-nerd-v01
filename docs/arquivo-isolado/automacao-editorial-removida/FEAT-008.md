# FEAT-008 - Progresso visual e reforço editorial da automação

## Objetivo

Melhorar a experiência da tela de automação editorial e reduzir rascunhos simples demais quando a IA local retorna um texto curto ou pouco estruturado.

## Entregue

- Popup de progresso exibido ao criar um post automático.
- Passos visuais para análise do tema, categoria, IA local, HTML editorial, capa, salvamento e abertura da edição.
- Camada de enriquecimento HTML depois da IA local.
- Uso das estruturas oficiais documentadas em `docs/blog-estruturas-de-conteudo.html`.
- Garantia de blocos editoriais mínimos quando o texto gerado vier fraco:
  - leitura do Estratégia Nerd;
  - subtítulos `h3`;
  - `content-block` oficiais;
  - tabela comparativa;
  - grade `content-grid-two` para pontos fortes e limitações;
  - bloco de FAQ;
  - critérios de decisão;
  - para quem faz sentido;
  - conclusão.
- Separação entre conteúdo público e instruções internas:
  - checklist editorial não entra no HTML do post;
  - sugestão de imagem interna não entra no HTML do post;
  - fontes genéricas de revisão não entram no HTML do post;
  - o corpo deve ficar publicável para o leitor.
- Textos públicos fixos do template usam acentos e evitam frases genéricas como "Como análise..." ou "Conclusão provisória".
- `Resumo rápido` é específico do formato/tema e não despeja a lista de palavras-chave dentro do artigo.
- `Veredito` usa linguagem de artigo, com recomendação por contexto, sem instrução editorial ao autor.
- Prompt do Ollama reforçado para pedir texto com acentos, sem markdown, sem checklist interno e sem lista de palavras-chave no corpo.
- Sanitização ampliada para preservar tags e classes seguras dos blocos oficiais do post.

## Arquivos alterados

- `app/Views/admin/editorial-automation/index.php`
- `app/Services/Admin/EditorialAutomationService.php`

## Observacoes

- O popup atual acompanha um envio tradicional de formulario. Ele mostra progresso estimado no front-end enquanto a requisicao PHP roda.
- Para progresso 100% real por etapa, a próxima fase deve mover a geração para uma fila com logs por job e consulta AJAX/SSE.
- A camada determinística não substitui a revisão humana. Ela organiza o rascunho para que a tela de edição já receba um post com estrutura mais parecida com os demais posts do portal.
- O post `id=23` foi usado como referência de densidade estrutural: várias seções `h2`, subtítulos `h3`, blockquotes e blocos `content-block`.
- Imagens internas devem ser geradas/anexadas somente quando houver arquivo real. Enquanto o ComfyUI não gerar um arquivo válido, o sistema não deve inserir no corpo um bloco dizendo para criar imagem depois.

## Validacao esperada

- Criar um rascunho pela tela `/admin/automacao-editorial`.
- Confirmar que o popup aparece imediatamente apos o submit.
- Confirmar que o post criado possui HTML com várias seções, listas e blockquote.
- Confirmar que o post criado possui `h3`, `content-block`, `content-grid-two`, tabela ou FAQ.
- Confirmar que o corpo não exibe instruções internas como "Sugestão de imagem interna", "Pontos para revisão" ou "Fontes e links para conferir".
- Confirmar que o corpo não exibe lista de palavras-chave como parágrafo do artigo.
- Confirmar que o resumo e a conclusão usam linguagem pública, com acentos e sem texto de instrução editorial.
- Confirmar que o post permanece como `rascunho`.
