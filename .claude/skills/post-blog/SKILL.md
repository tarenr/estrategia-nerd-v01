---
name: post-blog
description: Cria um post completo para o blog do Estrategia Nerd (estrategianerd.com.br) - titulo, resumo, conteudo HTML rico (comparativo/review/ficha tecnica/guia/noticia/lista), SEO, imagem de capa e imagens de apoio, e insere como rascunho no banco local pra revisao. Use quando o usuario pedir "post pro blog", "artigo", "escreve sobre X pro site". Publicacao em producao e sempre manual/aprovada.
---

# Post do blog — Estrategia Nerd

Gera um post pronto pra revisar no admin **local** e, depois de aprovado, publicar
em produção. Entrega: **rascunho já salvo no banco local + link pra revisar**.

## Passos

### 1. Entender o pedido e escolher a estrutura
O usuário passa um tema (ex.: "RTX 5070 vs RX 9070", "review do Switch 2",
"trailer novo de X", "como montar PC gamer barato", "top 7 teclados").

Leia **`estruturas-de-conteudo.md`** (LEIA sempre antes de escrever o HTML) e
escolha o esqueleto certo:
- **Comparativo** — 2 produtos/opções
- **Review** — 1 produto
- **Ficha técnica** — specs objetivas
- **Guia** — passo a passo
- **Notícia/lançamento** — trailer, anúncio (pesquisar o vídeo oficial)
- **Lista ranqueada** — Top N

Se for tema de fatos (specs, preço, data, trailer), **pesquise com WebSearch antes**
— nunca invente número, data ou link de vídeo.

Categoria (`categoria_slug`, tem que existir em `categoria_post` no banco):
`hardware`, `games`, `dicas` são as confirmadas em produção; se o tema não encaixar
claramente, pergunte ou rode `SELECT slug FROM categoria_post` (via `en-blog-draft.php`
que já teria falhado te avisando as opções).

### 2. Escrever o conteúdo
- Monte o `conteudo` em HTML seguindo **exatamente** as classes de
  `estruturas-de-conteudo.md` — nunca invente classe nem use CSS inline.
- H2 pra cada seção principal (entra no índice), H3 pra subseção.
- Post médio/longo leva **1-2 imagens no corpo** (ver passo 4) — use os
  placeholders `__BODY_IMAGE_1__`, `__BODY_IMAGE_2__`... nos atributos
  `data-src`/`src` do bloco de imagem; o script troca pelo caminho real.
- Se o tema tiver trailer/vídeo oficial (notícia de jogo/filme/anime), pesquise
  o link real do YouTube e monte o bloco de vídeo com o ID de verdade.
- Tom: informativo, direto, "estratégia não achismo" — a mesma voz da marca
  (ver `estilo-estrategia-nerd.md` da skill `/post-instagram` se precisar
  relembrar o tom), mas em registro de blog: frases completas, sem gíria em
  excesso, sem hashtag. Termina com uma seção de conclusão/veredito ou FAQ.
- `resumo`: 1-2 frases, aparece nos cards do blog e como meta description
  de fallback.
- `seo_title` (até ~60 caracteres), `seo_description` (até ~155), `seo_keywords`
  (vírgula), `tags` (vírgula).

### 3. Gerar a imagem de capa
Formato: **16:9** (`--ratio blog` no `gen.py`, 1280x720).

```
E:\AI\ComfyUI\venv\Scripts\python.exe E:\AI\en-image\gen.py --prompt "<prompt em ingles>" --out "<caminho>-capa.png" --ratio blog --steps 12 --seeds 2
```

Escolha a melhor seed. Regras de prompt: mesmas do `/post-instagram`
(1 sujeito forte, luz de recorte, sem texto/logo pedido no prompt — o negative
já bloqueia). Post de produto/review: peça a foto real ao usuário em vez de gerar.

Se o post levar imagens no corpo (comparativo, review, guia), gere 1-2 extras
do mesmo jeito.

### 4. Verificar post recomendado, destaque e tipo de post
Antes de montar o JSON, consulte a lista de posts publicados (`SELECT id,
titulo, slug, status, destaque, categoria_post_id FROM posts WHERE status =
'publicado'`) e decida:
- **proximo_post_id**: existe algum post publicado com relação real de tema
  ou categoria pra recomendar como "próximo passo"? Só preencha se fizer
  sentido de verdade — não é obrigatório ter um pra cada post.
- **destaque**: o post tem alcance/urgência acima da média (prazo real,
  tema muito buscado) pra justificar destaque na home? Compare com outros
  posts da mesma categoria que já são ou não destaque antes de decidir.
- **tipo_post**: qual das 6 estruturas usadas (`comparativo`, `review`,
  `ficha_tecnica`, `guia`, `noticia`, `lista`) - geralmente já é a mesma
  escolhida no passo 1.

### 5. Montar o JSON e criar o rascunho
Monte um JSON (ver formato no cabeçalho de `scripts/en-blog-draft.php`) com:
`titulo, slug (opcional), resumo, conteudo, categoria_slug, seo_title,
seo_description, seo_keywords, tags, status ("rascunho"), destaque (0 ou 1),
proximo_post_id (opcional), tipo_post (opcional),
data_publicacao (opcional), cover_image_path, body_images: [{"path": "..."}]`.

Salve em `.claude/skills/post-blog/saidas/<slug>.json` e rode:

```
"C:\xampp\php\php.exe" "C:\Users\WINDOWS\Projects\estrategia-nerd\scripts\en-blog-draft.php" ".claude\skills\post-blog\saidas\<slug>.json"
```

Pra **ajustar um rascunho já criado** (em vez de gerar um post novo), inclua
`"post_id": N` no JSON — o script atualiza esse post em vez de inserir outro
(mantém o slug atual a menos que você mande um `slug` novo, e preserva a capa
existente se não mandar `cover_image_path`).

O script:
- gera um slug único, resolve a categoria pelo slug (ou atualiza in-place com `post_id`)
- copia a capa pra `public/uploads/posts/{slug}/images/capa.<ext>`
- copia as imagens do corpo pra `img-001.<ext>`, `img-002.<ext>`... e substitui
  os placeholders `__BODY_IMAGE_N__` no HTML
- insere o post como **rascunho** via `PostRepository` (mesma tabela do admin)
- imprime `{"ok":true,"id":N,"slug":"...","edit_url":"...","preview_url":"..."}`

### 6. Entregar
Mostre no chat: título, resumo, um resumo do que o conteúdo cobre, a capa
(SendUserFile), e o **link do admin local** (`edit_url`) pra revisar/editar.

### 7. Publicar em produção (só quando o usuário aprovar)
Ainda não implementado (`scripts/en-blog-publish-prod.php` pendente — depende
de confirmar o schema do banco de produção). Quando existir: só roda depois de
"aprovado, pode subir"; faz backup antes; escreve só aquele post no banco de
produção + sobe a capa/imagens via FTP.

## Arquivos da skill

- `estruturas-de-conteudo.md` — blocos HTML oficiais + esqueletos por tipo (LEIA sempre)
- `saidas/` — JSONs dos posts gerados (não versionado)
- Script: `C:\Users\WINDOWS\Projects\estrategia-nerd\scripts\en-blog-draft.php`
- Gerador de imagem: `E:\AI\ComfyUI\` + `E:\AI\en-image\gen.py` (`--ratio blog`)

## Regras

- Nunca publica em produção sem aprovação explícita do usuário pra aquele post.
- Nunca inventa spec, preço, data ou link de vídeo — pesquisa ou pergunta.
- Nunca usa classe HTML nova ou CSS inline no `conteudo`.
- Post nasce sempre como **rascunho** no banco local, nunca direto publicado.
- Segue o `docs/CODEX-REGRAS-PERMANENTES.txt` do projeto: script novo respeita
  a separação de camadas (usa `PostRepository`, não SQL solto em lugar nenhum
  fora dele).
