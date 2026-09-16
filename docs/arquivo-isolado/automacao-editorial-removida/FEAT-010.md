# FEAT-010 - Observabilidade da automação editorial

## Objetivo

Adicionar uma primeira camada de controle de qualidade e rastreabilidade para posts criados pela automação editorial.

## Entregue

- Score interno de qualidade do HTML gerado.
- Componentes avaliados:
  - estrutura;
  - SEO;
  - legibilidade;
  - completude;
  - validade do HTML publico.
- Checagens estruturais:
  - tamanho do texto;
  - quantidade de `h2`;
  - quantidade de `h3`;
  - blocos `content-block`;
  - `content-grid-two`;
  - tabela;
  - FAQ;
  - blockquote;
  - ausência de linguagem interna no corpo.
- Registro no log `storage/logs/editorial_automation-YYYY-MM.log`:
  - origem da geração;
  - modelo da IA;
  - versao do prompt;
  - versao do template;
  - motivo de fallback;
  - status técnico da automação;
  - score de qualidade;
  - posts parecidos encontrados.
- Exibicao da nota de qualidade na tela `/admin/automacao-editorial`.
- Exibição do motor usado para a capa na lista de posts automáticos:
  - `Template`, quando a capa foi criada pelo gerador visual local;
  - `ComfyUI`, quando a capa veio de um workflow real do ComfyUI.

## Status Técnico

O post continua usando `status = rascunho`. O status técnico da automação fica registrado no log como:

- `completed`;
- `completed_with_fallback`;
- `needs_review`.

## Observações

- A primeira versão não cria nova tabela de histórico. Ela usa log estruturado para reduzir risco de migration durante o MVP.
- A identificação `Template`/`ComfyUI` na tela é inferida pelo caminho da imagem salva: arquivos `capa-comfy.*` indicam ComfyUI; os demais indicam template local.
- Uma próxima fase pode criar tabela própria para jobs da automação e permitir popup com progresso real por AJAX/SSE.
