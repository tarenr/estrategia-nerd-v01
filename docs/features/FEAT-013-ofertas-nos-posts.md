ID: FEAT-013
Tipo: Feature + Correcao de dados
Nome: Ofertas da Central Nerd no fim dos posts, rastreio por post e limpeza de cliques falsos
Data: 13/10/2026
Tarefas Forge: #1262 (ofertas nos posts), #1263 (cliques falsos), #1264 (documentacao)

Objetivo:
1) Mostrar 2-3 ofertas da Central Nerd ligadas ao assunto no fim de cada artigo e saber de qual post veio cada clique.
2) Tirar da contagem os cliques falsos gravados por scanners de SQL injection e impedir novos.

Contexto:
Levantamento em producao (11/04 a 12/10/2026): 361 cliques, dos quais 106 vieram de scanners (origem com SQL, ex.: "central-gadgets UNION ALL SELECT..."). Dos 255 reais, 0 vinham dos posts: os artigos so tinham o botao "Abrir a Central Nerd" quando nao havia proximo post.

## Como funciona

Escolha das ofertas (`app/Services/Site/RelatedOffersService.php`), sem campo novo no banco:
- Candidatas: produtos ativos e nao expirados (`LinkRepository::listActiveProductsForPosts`).
- Categoria do post (`categoria_post.slug`) -> prateleiras (`links.subgrupo_publico`):

| Categoria | Prateleiras |
|---|---|
| hardware | PC Gamer Inicial, PC Gamer Intermediario, PC Gamer Topo, Upgrade Monstro |
| games | Setup Gamer, Action Figures |
| cultura-geek | Action Figures, Gadgets |
| gadgets | Gadgets, Setup Gamer |
| dicas | Setup Estrategia Nerd, Setup Gamer |

- Lista da categoria = produtos das prateleiras acima + produtos com palavra em comum entre titulo/tags do post e titulo/descricao da oferta (palavras genericas como "jogos", "gamer", "para" sao ignoradas).
- **Sorteio a cada visita**: 3 produtos da lista, sem repetir no bloco (pedido do usuario em 14/10/2026, para nao mostrar sempre o mesmo produto). A pagina do post sai com `Cache-Control: no-store`, entao cada visita sorteia de novo.
- Os produtos vem do banco do proprio ambiente: em producao, sempre os produtos cadastrados em producao. O clique em `/link/{slug}` e resolvido no mesmo banco.
- Sem oferta na lista, o bloco nao aparece; erro de banco vai para o log e o artigo abre normalmente.
- Link: `/link/{slug}?origem=post-{id}`, com `rel="noopener noreferrer sponsored"`.

Visual: bloco "Ofertas relacionadas da Central Nerd" em `app/Views/site/post.php`, antes de "Proximo passo", reaproveitando os cards da Central (`central-bio-card`, ja no `site.css`).

Painel: quadro "Cliques vindos dos posts" no dashboard (`LinkClickRepository::topPostsByRange`, 5 posts com mais cliques no periodo).

Bloqueio de cliques falsos (`LinkRedirectService::resolve`): se `origem` tiver caractere fora de `[a-z0-9:_-]` ou mais de 60 caracteres, o visitante e redirecionado normalmente, mas o clique nao e gravado.

Limpeza dos registros antigos: 106 linhas de `link_clicks` em producao cuja origem casa com `union|select|extractvalue|order-by|concat|^and-|^50000`. Local e stage tinham 0. Antes de apagar, export JSON em `storage/logs/limpeza-cliques/`; exclusao por id em transacao, abortando se o numero nao for exatamente 106. Rollback: reinserir as linhas do JSON.

Arquivos alterados:
- app/Repositories/LinkRepository.php (listActiveProductsForPosts)
- app/Repositories/LinkClickRepository.php (topPostsByRange)
- app/Services/Site/RelatedOffersService.php (novo)
- app/Services/Site/LinkRedirectService.php (filtro de origem)
- app/Controllers/Site/PostController.php
- app/Views/site/post.php
- app/Services/Admin/DashboardService.php
- app/Views/admin/dashboard.php
- app/Views/site/local-docs-v2.php (secao 17), README.md

Banco de dados:
- Mudanca de schema: nao
- Mudanca de dados: sim (remocao das 106 linhas falsas de link_clicks em producao)
- Script necessario: limpeza com export previo (fora do repositorio, executado uma vez)

Publicacao (fluxo oficial: local -> stage -> producao):
- Stage: FTP com copia previa dos 8 arquivos remotos (`storage/deploy-central-nerd-ofertas-20261014-stage/before`) e verificacao por hash. `LinkRepository.php` foi como "versao da stage + metodo novo": a versao local tem uma ordenacao do admin de links ainda nao publicada, que ficou fora desta entrega.
- Producao: pacote tirado da stage (os 8 arquivos baixados da stage e conferidos contra o pacote testado), copia previa dos arquivos de producao e verificacao por hash.

Impacto em producao: medio (todo artigo ganha o bloco; numeros de cliques caem ~29% ao remover os falsos)
Afeta rotas criticas: sim (/post/{slug} e /link/{slug})

Origem validada: estrategia-nerd-stage
Paridade local -> stage: validada (8 arquivos conferidos por hash; LinkRepository = versao da stage + metodo novo)
Paridade stage -> pacote: validada (pacote de producao baixado da stage e conferido)

Validado local: sim
- Pagina do post no navegador (desktop e 375 px, sem rolagem lateral); ofertas mudam a cada visita.
- Clique real com origem post-64 gravado; origens com SQL redirecionam (302) sem gravar.
- verify-changes.php: 23 OK, 0 falhas.

Validado stage: sim
- Sorteio contra os produtos de producao e da stage (50 visitas por post): todos os 32 posts com oferta, 17 a 27 produtos diferentes por post, nenhum produto repetido no bloco, origem sempre post-{id}; os 54 produtos sorteaveis ativos, com URL de loja valida e imagem HTTP 200.
- Posts de games (desktop) e dicas (375 px) na stage: bloco, imagens e sem rolagem lateral.
- Clique real de teste gravado como post-48 (apagado em seguida pelo id); 2 origens com SQL redirecionaram sem gravar.
- Limpeza em simulacao: stage 0 linhas (nada a fazer), producao 106.

Observacoes:
- Para mudar quais ofertas aparecem num tipo de post: trocar a prateleira da oferta no admin de Links ou ajustar `CATEGORY_SHELVES`.
- Um clique de teste (origem post-64) ficou no banco local.
