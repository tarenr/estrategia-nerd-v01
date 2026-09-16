# FEAT-005 - Planejamento de Automacao Editorial com ComfyUI e Instagram

## Objetivo

Planejar a automacao editorial do Estrategia Nerd para criacao de posts de blog com apoio de IA local e geracao visual via ComfyUI, preparando tambem a etapa posterior de distribuicao no Instagram.

## Contexto

O objetivo operacional e reduzir o trabalho manual de criacao de conteudo sem depender de IA paga. A estrategia inicial deve focar no blog, com posts criados automaticamente como rascunho ou agendados, e somente depois evoluir para publicacao no Instagram.

O ComfyUI entra como motor local/opcional de imagens. Ele nao deve ser tratado como dependencia obrigatoria para publicar um post, pois pode estar offline, lento ou sem modelo adequado. O sistema deve manter fallback por template automatico de imagem.

## Escopo Planejado

- Criacao automatica de pauta.
- Geracao automatica de briefing editorial.
- Geracao de texto com IA local, preferencialmente Ollama.
- Montagem de HTML do post com template editorial padronizado.
- Geracao de capa por template automatico.
- Geracao opcional de capa/imagens internas via ComfyUI.
- Validacao editorial automatizada.
- Criacao do post no admin como rascunho ou agendado.
- Preparacao dos dados para publicacao futura no Instagram.

## Fora De Escopo Nesta Etapa

- Publicacao direta em producao.
- Publicacao direta no Instagram sem revisao inicial.
- Automacao de comentarios, mensagens ou inbox do Instagram.
- Geracao de videos completos para Reels.
- Uso obrigatorio de IA paga.
- Alteracao de schema sem documento tecnico proprio.

## Arquitetura Proposta

```text
Fila de ideias/categorias
  -> Gerador de pauta
  -> Briefing editorial
  -> IA local para texto
  -> Validador editorial
  -> Briefing visual
  -> Template de imagem ou ComfyUI
  -> Criador de post
  -> Rascunho/agendamento no blog
  -> Pacote social para Instagram
```

## Motor De Texto

Opcao recomendada:

- Ollama local para gerar titulo, resumo, estrutura, corpo do post, SEO title, meta description, tags e legenda social.

Regras editoriais:

- O texto deve ser criado a partir de uma pauta estruturada.
- Conteudo factual deve ter fonte informada.
- Noticias devem manter links para fontes oficiais quando possivel.
- O sistema deve evitar publicar conteudo sensivel sem revisao.
- A primeira versao deve criar rascunho, nao publicar automaticamente.

## Motor Visual ComfyUI

O ComfyUI deve ser usado para gerar imagens quando houver workflow disponivel e o servico local estiver respondendo.

Workflows planejados:

- `cover_games.json`
- `cover_anime.json`
- `cover_cinema.json`
- `cover_tecnologia.json`
- `internal_image.json`

Entrada esperada:

```json
{
  "categoria": "games",
  "titulo": "Nintendo Switch 2 pode mudar o mercado em 2026",
  "formato": "1200x630",
  "uso": "capa",
  "estilo": "editorial gamer, neon, console futurista, alta energia"
}
```

Saida esperada:

- Imagem final em formato web.
- Arquivo salvo em pasta de uploads do post.
- Caminho registrado no post.
- Log da geracao com prompt, workflow, status e tempo.

## Fallback Visual

Se o ComfyUI nao estiver disponivel:

- usar template automatico de capa;
- manter identidade visual do Estrategia Nerd;
- gerar imagem em 1200x630 para blog/Open Graph;
- opcionalmente gerar versao 1080x1350 para Instagram.

Esse fallback e obrigatorio para evitar que a criacao do post dependa 100% do ComfyUI.

## Planejamento Instagram

O Instagram deve ser tratado como segunda fase, reaproveitando o post do blog como fonte principal.

Formatos planejados:

- Feed imagem unica: capa do post adaptada para 1080x1350 ou 1080x1080.
- Carrossel: resumo do post em 3 a 6 cards.
- Reel futuro: video vertical curto, fora da primeira etapa.
- Story futuro: chamada para o post, dependendo da permissao disponivel.

Fluxo proposto:

```text
Post do blog aprovado
  -> Gerar legenda curta
  -> Gerar hashtags
  -> Adaptar imagem
  -> Validar limites da API
  -> Criar container no Instagram
  -> Verificar status do container
  -> Publicar ou agendar
  -> Registrar retorno da API
```

## Pre-Requisitos Instagram

Para publicacao via API oficial da Meta:

- Conta Instagram profissional, Business ou Creator.
- Pagina do Facebook vinculada ao perfil do Instagram.
- App Meta configurado.
- Token de acesso valido.
- Permissoes de publicacao aprovadas.

Permissoes provaveis no fluxo com Facebook Login:

- `pages_show_list`
- `instagram_basic`
- `instagram_content_publish`
- `pages_read_engagement`

No fluxo mais novo com Instagram Login, a permissao de publicacao indicada pela documentacao/Postman da Meta e:

- `instagram_business_content_publish`

## Limitacoes Instagram A Considerar

- Conta pessoal nao publica via API oficial.
- A API publica em contas profissionais.
- A midia precisa estar acessivel por URL publica para a Meta buscar.
- O fluxo de publicacao usa container antes do publish final.
- Reels exigem video em formato suportado e processamento antes da publicacao.
- Legendas e hashtags devem respeitar limites da plataforma.
- O sistema deve registrar erros e nao tentar republicar em loop.

## Modelo De Dados Futuro

Possiveis entidades futuras:

- `automation_posts`: fila e estado do post automatico.
- `automation_assets`: imagens geradas e metadados.
- `automation_social_posts`: pacote social por rede.
- `automation_runs`: logs de execucao.

Estados sugeridos:

- `draft`
- `ready_for_review`
- `approved`
- `scheduled`
- `published`
- `failed`
- `blocked`

## Tela Admin Futura

Pagina sugerida:

- `/admin/automacao-editorial`

Abas:

- Ideias
- Gerar Post
- Rascunhos
- Imagens
- Instagram
- Logs

Acoes iniciais:

- Gerar pauta.
- Gerar post de teste.
- Gerar capa por template.
- Gerar capa via ComfyUI.
- Criar rascunho.
- Visualizar HTML.

## Regras De Seguranca

- Nunca publicar diretamente em producao na primeira fase.
- Nunca publicar no Instagram sem token seguro no `.env`.
- Nunca salvar token em arquivo versionado.
- Nunca expor prompt interno, token ou resposta sensivel da API na interface publica.
- Registrar status e erro de cada execucao.
- Ter bloqueio por ambiente: local pode testar, stage valida, producao apenas executa fluxo aprovado.

## Fases De Implementacao

### Fase 1 - Base Do Blog

- Criar gerador de pauta manual/semiautomatico.
- Criar template HTML editorial.
- Criar service para montar rascunho.
- Criar capa via template automatico.
- Registrar logs.

### Fase 2 - IA Local

- Integrar com Ollama local.
- Criar prompts editoriais por categoria.
- Validar estrutura e tamanho do conteudo.
- Criar post como rascunho.

### Fase 3 - ComfyUI

- Configurar URL local do ComfyUI.
- Criar workflows por categoria.
- Enviar prompt/workflow para API local.
- Salvar imagem gerada.
- Criar fallback automatico.

### Fase 4 - Instagram

- Criar pacote social a partir do post.
- Gerar legenda e hashtags.
- Adaptar imagem para formatos sociais.
- Implementar integracao com API da Meta em modo teste.
- Publicar primeiro como operacao manual/aprovada.

### Fase 5 - Automacao Completa

- Criar agenda editorial.
- Agendar posts no blog.
- Agendar/publicar Instagram.
- Medir resultados.
- Ajustar temas com base em performance.

## Dependencias

- Ollama local para texto.
- ComfyUI local para imagens avancadas.
- Modelos de imagem instalados localmente.
- PHP cURL habilitado.
- Permissoes adequadas no servidor para salvar imagens.
- App Meta e conta Instagram profissional para fase social.

## Impacto Em Producao

Medio quando implementado, pois cria conteudo, arquivos de midia e possivelmente publicacoes externas. Na etapa atual, este documento nao altera comportamento do sistema.

## Afeta Rotas Criticas

Nao nesta etapa de planejamento.

## Mudanca De Schema

Nao nesta etapa de planejamento.

## Mudanca De Dados

Nao nesta etapa de planejamento.

## Risco

Medio. A automacao editorial pode gerar informacao incorreta, imagem inadequada ou publicacao fora do tom da marca. Por isso, a primeira versao deve operar com rascunho e aprovacao manual.

Riscos especificos:

- alucinacao em texto gerado por IA;
- imagem gerada com elementos indevidos;
- dependencia local do ComfyUI;
- falha de token/API do Instagram;
- publicacao duplicada;
- uso de midia sem direito adequado quando fontes externas forem usadas.

## Validacao Minima Futura

- `php -l` nos arquivos PHP novos/alterados.
- `git diff --check`.
- Gerar post de teste em local.
- Confirmar que o post fica como rascunho.
- Confirmar que nenhuma publicacao externa ocorre sem aprovacao.
- Confirmar fallback de imagem quando ComfyUI estiver indisponivel.
- Confirmar que tokens nao aparecem em tela, log publico ou diff.
- Validar stage antes de qualquer pacote.

## Referencias

- ComfyUI: https://github.com/Comfy-Org/ComfyUI
- Documentacao ComfyUI: https://docs.comfy.org/
- Meta Instagram Content Publishing: https://developers.facebook.com/docs/instagram-platform/content-publishing/
- Meta/Postman Instagram API Collection: https://www.postman.com/meta/instagram/documentation/6yqw8pt/instagram-api

## Status Stage/Producao

- Origem validada: nao validada.
- Paridade local -> stage: nao validada.
- Paridade stage -> pacote: nao validada.
- Producao: nao publicado.
- Apto para release: nao.
