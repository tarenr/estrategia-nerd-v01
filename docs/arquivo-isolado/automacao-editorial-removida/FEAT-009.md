# FEAT-009 - Avaliacao de imagem interna e ComfyUI

## Objetivo

Registrar a decisao sobre imagens na automacao editorial depois da separacao entre conteudo publico e instrucoes internas.

## Decisao atual

- A automacao continua gerando capa e thumb por template local quando o ComfyUI nao estiver disponivel.
- Para temas de hardware/comparativo, o template local usa uma composicao visual especifica com divisao AM4/AM5, sockets desenhados, fundo tecnico, badges de custo/tecnologia e barra de analise.
- O titulo criado pela automacao recebe destaque publico com `[[...]]` no trecho principal. Exemplo: `Plataforma [[AM4 vs AM5]]: ainda vale investir no antigo?`.
- O corpo do post nao deve receber uma "sugestao de imagem" em texto.
- Imagem interna so deve entrar no HTML quando existir um arquivo real salvo em uploads.
- Enquanto o ComfyUI nao estiver integrado, o post pode ficar sem imagem interna automatica.

## ComfyUI

O planejamento atual continua valido para usar ComfyUI como motor visual local/opcional.

Referencias verificadas em 2026-07-29:

- Repositorio oficial: https://github.com/Comfy-Org/ComfyUI
- Documentacao oficial: https://docs.comfy.org/

Pontos relevantes:

- O ComfyUI possui backend/API e fila de execucao.
- Workflows podem ser salvos/carregados como JSON.
- Ele suporta uso local no Windows e tambem instalacao portatil.
- O projeto informa suporte a modelos de imagem modernos e execucao offline para o core local.

## Proxima etapa recomendada

Criar uma integracao separada para imagem:

1. Configurar `COMFYUI_BASE_URL`.
2. Criar pasta `config/comfyui-workflows/`.
3. Salvar workflows por uso:
   - `cover_tecnologia.json`
   - `cover_games.json`
   - `cover_anime.json`
   - `internal_image.json`
4. Criar servico `ComfyUiImageService`.
5. Enviar prompt, categoria, slug, uso e dimensao para o workflow.
6. Baixar o arquivo gerado para `uploads/posts/{slug}/imagens/`.
7. Inserir no post somente um bloco oficial real:

```html
<figure class="article-figure content-block-image" data-en-block="media" data-media-type="image">
  <img src="uploads/posts/slug/imagens/img-001.webp" alt="Descricao objetiva">
  <figcaption>Legenda util para o leitor.</figcaption>
</figure>
```

## Regra de fallback

Se o ComfyUI estiver offline, lento ou sem workflow disponivel:

- manter a capa por template local;
- nao inserir imagem interna falsa;
- registrar no log que a imagem interna foi ignorada por falta de motor visual.
