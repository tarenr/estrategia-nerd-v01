# Estruturas de conteúdo do blog — Estratégia Nerd

Extraído de `C:\Users\WINDOWS\Projects\estrategia-nerd\docs\blog-estruturas-de-conteudo.html`
(fonte de verdade do projeto — se esse arquivo mudar lá, atualize aqui).

O `conteudo` do post é HTML salvo direto no banco. **Só usar as classes abaixo** —
o front do site já sabe estilizar elas. Nunca inventar classe nova nem CSS inline.

## Título do post: destaque com `[[trecho]]`

No campo `titulo` (não no `conteudo`), `[[trecho]]` vira um `<span class="post-title-accent">`
no H1 da página do post — em todo outro lugar (breadcrumb, posts relacionados,
alt de imagem, navegação anterior/próximo) o `[[ ]]` é removido automaticamente
e some, sem quebrar nada. **Sempre usar em todo post novo.**

Exemplo: `O Futuro dos Processadores: [[Intel vs AMD vs ARM]] em 2026`

Regra: destaque a parte mais "chamativa"/específica do título (o nome do
produto, o confronto, o número), não o título inteiro.

## Blocos de texto simples (sempre disponíveis)

- `<h2>texto</h2>` — seção macro. **Entra no índice automático do post.** Use pra
  cada seção principal.
- `<h3>texto</h3>` — subseção. Tem estilo visual próprio e forte — não espere
  neutro. Use dentro de uma seção H2.
- `<p>texto</p>` — parágrafo corrido.
- `<ul><li>...</li></ul>` / `<ol><li>...</li></ol>` — lista / lista numerada.
- `<blockquote>frase</blockquote>` — citação/destaque.
- `<a href="...">texto</a>` — link.
- `<table><thead>...</thead><tbody>...</tbody></table>` — tabela simples. **Não
  envolver em wrapper manual** — o front aplica `.article-table-wrap` sozinho.

## Mídia

**Imagem editorial:**
```html
<figure class="article-figure content-block-image" data-en-block="media"
  data-media-type="image" data-src="uploads/posts/{slug}/images/img-001.webp"
  data-alt="Descrição curta e objetiva" data-caption="Legenda opcional (só se agregar)">
  <img src="uploads/posts/{slug}/images/img-001.webp" alt="Descrição curta e objetiva">
  <figcaption>Legenda opcional</figcaption>
</figure>
```
- Caminho sempre em `uploads/posts/{slug}/images/`.
- `alt` objetivo e descritivo (acessibilidade + SEO).
- Sem legenda? Não inclua `<figcaption>` nem `data-caption`.

**Vídeo do YouTube (trailer, gameplay, anúncio):**
```html
<figure class="content-block content-block-video" data-en-block="media"
  data-media-type="video" data-src="https://www.youtube.com/watch?v=ID"
  data-caption="Trailer oficial">
  <div class="content-block-label">Vídeo</div>
  <div class="aspect-video">
    <iframe src="https://www.youtube.com/embed/ID" title="Trailer" loading="lazy" allowfullscreen></iframe>
  </div>
  <figcaption>Trailer oficial</figcaption>
</figure>
```
- **O ID do vídeo tem que vir de uma busca real (WebSearch) pelo trailer/vídeo
  oficial.** Nunca inventar um ID — se não achar, não inclui o bloco.

**Áudio (raro — narração/som ambiente):**
```html
<div class="en-audio-block" data-audio-narracao="uploads/posts/{slug}/audio/nar-001.mp3"
  data-audio-title="Título" data-audio-subtitle="Frase de contexto" data-audio-button="Ouvir narração">
  <div class="en-audio-header">
    <span class="en-audio-icon" aria-hidden="true"></span>
    <strong class="en-audio-title">Título</strong>
  </div>
  <p class="en-audio-subtitle">Frase de contexto</p>
  <button type="button" class="en-audio-button">Ouvir narração</button>
</div>
```

## Blocos ricos ("herdados do Gerador Nerd" — usar como esqueleto)

`content-block` genérico + variantes de cor: `content-block-note` (info),
`content-block-success` (positivo), `content-block-warning` (negativo/atenção),
`content-block-highlight` (destaque forte/veredito), `content-block-faq` (FAQ),
`content-block-table` (tabela em caixa). `content-grid-two` = grid de 2 colunas
(usar pra Pros/Contras).

### Comparativo (2 produtos/opções — GPU vs GPU, jogo vs jogo, etc.)
```html
<h2>Produto A vs Produto B: qual vale mais a pena?</h2>
<p>Contexto: pra que uso isso serve o comparativo (ex.: 1080p, custo-benefício).</p>

<div class="content-block content-block-note">
  <div class="content-block-label">Resumo rápido</div>
  <p>1-2 frases já entregando a direção do veredito.</p>
</div>

<div class="content-block content-block-table">
  <div class="content-block-label">Tabela comparativa</div>
  <table class="nerd-comparison-table">
    <thead><tr><th>Critério</th><th>Produto A</th><th>Produto B</th></tr></thead>
    <tbody>
      <tr><td>Critério 1</td><td>...</td><td>...</td></tr>
      <tr><td>Critério 2</td><td>...</td><td>...</td></tr>
    </tbody>
  </table>
</div>

<h3>Comparativo em tópicos</h3>
<ul>
  <li><b>Performance:</b> ...</li>
  <li><b>Preço:</b> ...</li>
</ul>

<div class="content-grid-two">
  <div class="content-block content-block-success">
    <div class="content-block-label">Ponto forte de A</div>
    <ul><li>...</li></ul>
  </div>
  <div class="content-block content-block-warning">
    <div class="content-block-label">Ponto forte de B</div>
    <ul><li>...</li></ul>
  </div>
</div>

<div class="content-block content-block-highlight">
  <div class="content-block-label">Veredito</div>
  <p>Conclusão direta: pra quem A é melhor, pra quem B é melhor.</p>
</div>

<div class="content-block content-block-faq">
  <div class="content-block-label">Perguntas frequentes</div>
  <h3>Pergunta 1?</h3>
  <p>Resposta objetiva.</p>
  <h3>Pergunta 2?</h3>
  <p>Resposta objetiva.</p>
</div>
```

### Review (1 produto)
```html
<h2>Review: Nome do Produto</h2>
<p>Visão geral do produto e pra quem ele faz sentido.</p>

<h3>Pontos principais</h3>
<ul><li>...</li><li>...</li></ul>

<h3>Pros e contras</h3>
<div class="content-grid-two">
  <div class="content-block content-block-success">
    <div class="content-block-label">Pros</div>
    <ul><li>...</li></ul>
  </div>
  <div class="content-block content-block-warning">
    <div class="content-block-label">Contras</div>
    <ul><li>...</li></ul>
  </div>
</div>

<h3>Conclusão</h3>
<p>Veredito final direto.</p>
```

### Ficha técnica
```html
<h2>Ficha técnica: Nome do Produto</h2>
<div class="content-block content-block-note">
  <div class="content-block-label">Visão geral</div>
  <p><b>Marca:</b> ...<br><b>Faixa de preço:</b> ...</p>
</div>
<div class="content-block">
  <div class="content-block-label">Especificações principais</div>
  <ul>
    <li><b>Tela:</b> ...</li>
    <li><b>Bateria:</b> ...</li>
  </ul>
</div>
```

### Guia (passo a passo)
```html
<h2>Como fazer X</h2>
<p><b>Nível:</b> Iniciante/Intermediário/Avançado</p>
<h3>O que você vai aprender</h3>
<ul><li>...</li></ul>
<h3>Passo a passo</h3>
<ol><li>...</li><li>...</li></ol>
<h3>Dicas finais</h3>
<ul><li>...</li></ul>
```

### Notícia / lançamento (trailer, anúncio)
```html
<h2>Título da notícia</h2>
<p>Contexto rápido do que aconteceu.</p>

<!-- se tiver trailer real, o video entra aqui -->

<h3>O que foi anunciado</h3>
<ul><li>...</li><li>...</li></ul>
<h3>Por que isso importa</h3>
<p>...</p>
<h3>O que esperar agora</h3>
<p>...</p>
```

### Lista ranqueada (Top N)
```html
<h2>Top N Categoria</h2>
<p>Critério usado e pra quem a lista faz sentido.</p>
<ol>
  <li><b>#1</b> Item + diferencial.</li>
  <li><b>#2</b> Item + diferencial.</li>
</ol>
<h3>Como escolher</h3>
<ul><li>Dica final de decisão.</li></ul>
```

## Regras obrigatórias

- H2 só pra seção macro (índice do post). H3 só pra subseção dentro de um H2.
- Mídia sempre pelos blocos oficiais acima — nunca `<img>`/`<iframe>` solto.
- Caminho de mídia sempre `uploads/posts/{slug}/...`.
- Vídeo do YouTube: **ID real, achado por busca** — nunca inventado.
- Sem CSS inline, sem classe nova, sem colar HTML de fora.
- Post médio/longo (comparativo, review, guia, notícia) leva **1 a 2 imagens no
  corpo**, não só a capa — quebra o texto e ilustra.

## Profundidade editorial (benchmark real do site)

Um comparativo publicado de verdade no blog (`rtx-3050-vs-rx-6600-...`) tem **13
H2 + 8 H3, ~35-40 parágrafos**. O esqueleto da seção anterior é só o *molde
mínimo de blocos* — o post de verdade precisa ser bem mais longo e cobrir cada
ângulo em sua própria seção H2, não tudo espremido em 1-2 blocos. Pra um
**Comparativo**, a ordem de seções esperada é:

1. Introdução (contexto do comparativo)
2. Apresentação do produto/opção A (specs, recursos)
3. Apresentação do produto/opção B (specs, recursos)
4. Uma seção H2 **por critério relevante** (desempenho, recurso X, recurso Y,
   consumo, preço...) — cada uma com 2-4 parágrafos, não uma linha
5. Resumo comparativo (aí sim cabe a tabela `content-block-table`)
6. Casos de uso / pra quem cada opção é melhor
7. Veredito final (`content-block-highlight`)
8. Checklist antes de comprar/decidir (lista)
9. (Opcional) Fontes/referências, se usou dados de fora

Pra **Review, Guia e Notícia** vale a mesma lógica: mais seções H2 curtas e
específicas rendem um post melhor do que poucas seções genéricas. Se o tema não
tiver profundidade suficiente pra isso, é sinal de que precisa de mais pesquisa
(WebSearch) antes de escrever, não de encolher a estrutura.
- Tabela sem wrapper manual.
