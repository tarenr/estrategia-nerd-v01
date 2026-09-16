# FEAT-011 - Integracao inicial com ComfyUI

## Objetivo

Preparar a automacao editorial para conversar com ComfyUI sem tornar a geracao de posts dependente dele.

## Entregue

- Leitura de `COMFYUI_BASE_URL`.
- Verificacao de disponibilidade via `/system_stats`.
- Pasta documentada para workflows: `config/comfyui-workflows/`.
- Busca de workflows para capa:
  - `cover_{slug-da-categoria}.json`;
  - `cover_tecnologia.json`;
  - `cover_default.json`.
- Substituicao de placeholders no workflow:
  - `{{PROMPT}}`;
  - `{{NEGATIVE_PROMPT}}`;
  - `{{TITLE}}`;
  - `{{CATEGORY}}`;
  - `{{SLUG}}`.
- Enfileiramento do workflow em `/prompt`.
- Consulta curta de `/history/{prompt_id}` para localizar a primeira imagem gerada.
- Download da imagem via `/view`.
- Salvamento da capa em `uploads/posts/{slug}/images/capa-comfy.{ext}`.
- Fallback obrigatorio para capa por template local quando:
  - ComfyUI nao esta configurado;
  - ComfyUI nao responde;
  - workflow nao existe;
  - workflow e invalido;
  - imagem final nao fica disponivel dentro de `COMFYUI_WAIT_SECONDS`.
- Registro no log da automacao:
  - engine da capa;
  - status do ComfyUI;
  - erro;
  - `prompt_id`, quando existir.

## Configuracao

Variaveis:

- `COMFYUI_BASE_URL`: URL local do ComfyUI, por exemplo `http://127.0.0.1:8188`.
- `COMFYUI_TIMEOUT`: timeout das chamadas principais.
- `COMFYUI_WAIT_SECONDS`: tempo maximo para aguardar a imagem apos o enfileiramento.

## Limite da etapa atual

O download automatico ja existe para a primeira imagem retornada no historico, mas ainda depende do workflow salvar imagem no output do ComfyUI. Imagem interna no corpo do post ainda nao e inserida automaticamente.

## Proxima fase

Para usar a imagem final do ComfyUI no post:

1. Criar workflows reais por categoria.
2. Validar dimensao exata da imagem baixada.
3. Gerar thumb separada quando necessario.
4. Para imagem interna, inserir somente quando o arquivo real existir usando o bloco oficial `figure.article-figure.content-block-image`.
