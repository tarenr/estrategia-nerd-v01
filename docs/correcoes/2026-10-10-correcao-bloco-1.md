# Correção da Leva 02 — Bloco 1

Data: 10/10/2026. Escopo local: artigos 77–80 e Instagram 229–234. Sem publicação no Instagram ou sincronização do blog em produção.

## Onde a execução anterior errou

A imagem chamada Alan Wake 2 era uma cópia de Resident Evil 4. O artigo 79 também tinha jogos trocados nas imagens. Categorias textuais e tipos não estavam coerentes com o cadastro. Os textos repetiam blocos de outro sistema visual e faziam afirmações sem fonte. Legendas e vínculos misturavam pautas, incluindo o squad vinculado a Os Nefalem. Arquivos de mídia não estavam cadastrados na tabela oficial. Marcar vídeos antigos como prontos não comprovava uma nova renderização. As galerias divergiam do conteúdo atualizado.

A primeira correção ainda usou artes programáticas inadequadas nos Reels de humor e manteve 230/233 como carrosséis, contrariando a orientação de usar Reels com música para conteúdos referentes aos artigos. Esta revisão substitui as artes por ImageGen e converte os dois formatos.

## Implementação

Artigos: 77 seleção comentada (lista/Games), 78 explicativo (guia/Games), 79 recomendações (lista/Dicas), 80 retomada de campanha (guia/Dicas). Estruturas próprias, fontes primárias, sem FAQ/tabela obrigatórios. Imagens WebP 1200×675 em `uploads/posts/{slug}/images/capa.webp` e `img-001.webp` a `img-003.webp`. A captura de Alan Wake 2 veio da página oficial PlayStation. Revisadas alegações de infrassom, áudio espacial, duração de runs e preservação do save.

Instagram: seis Reels 1080×1920 H.264/AAC, música incorporada e trilhas diferentes. 229/231: arquivos `reel-{id}-revisado.mp4` já conferidos. 230/232/233/234: `reel-{id}-final.mp4`, com poster e manifesto. Duram 24 segundos, exceto 230 (28) e 233 (30). Um vídeo por registro. Conversão de 230/233 em transação local pelo repositório oficial, com backup dos vínculos anteriores; arquivos antigos preservados. Status e datas mantidos. 232/234 são humor autônomo e não apontam para artigos diferentes.

Trilhas por ID: 229/21 Caves of Dawn; 230/25 Dark Fantasy Ambient; 231/22 Best Dramatic Music; 232/34 Sunset Vibes; 233/28 Little Slime’s Adventure; 234/15 The Arcade City. Biblioteca existente, sem novos downloads.

## Imagens criadas com ImageGen integrado

Saídas do projeto: `public/uploads/reels/leva02-bloco1/squad-imagegen.png` e `relogio-imagegen.png`.

Prompt do squad: ilustração editorial cinematográfica em quadrinhos, 16:9, adulto gamer brasileiro esperando respostas no celular; quatro vinhetas orgânicas de amigos trabalhando, estudando, viajando e dormindo; cinco adultos, anatomia correta, paleta azul-marinho/ciano/magenta, pintura e traço profissional, sem textos, logos, interface ou cards.

Prompt do relógio: ilustração editorial cinematográfica em quadrinhos, 16:9, adulto gamer cansado segurando controle no fim da noite; despertador analógico em primeiro plano, café e amanhecer ao fundo, expressão divertida, anatomia correta, paleta azul-marinho/ciano/magenta e luz quente; cena única sem lettering, logos, interface ou cards. O gerador incluiu numerais naturais no mostrador, sem prejudicar a narrativa.

## Documentação e prévias

A fonte oficial da Central de Conhecimento é `docs/features/planejamento-editorial-leva-02.md`, exibida pelo controlador LocalDocs e pela Base Técnica administrativa. Atualizada nessa fonte. As três galerias de prévia são geradas do mesmo payload, com seis players e os quatro artigos. Endereço de revisão solicitado: https://nerd.tfr-info.com.br/public/uploads/previews/leva02-bloco1-v3/index.html.

## Backup e recuperação

Original: `storage/correcao-leva02-20261010-codex/`, JSON dos registros, 183 arquivos e manifesto SHA-256. Antes da conversão: `storage/correcao-leva02-reels-20261010/`, snapshots de posts, Instagram e 14 mídias, além dos scripts/documentos anteriores. Os vídeos e cards anteriores continuam no disco.

Recuperação, somente com autorização específica: conferir o ambiente local e ausência de publicação em andamento, restaurar os campos e vínculos dos IDs do lote em transação usando os JSONs correspondentes, restaurar os arquivos do backup e regenerar as prévias. Não executar UPDATE/DELETE amplo nem sincronizar outro ambiente. O script de revisão não faz rollback automático.

## Validação e entrega

Validação executada: 259 verificações de integridade aprovadas; suíte obrigatória `scripts/verify-changes.php`: 17 OK, zero falhas (inclui PHPStan nível 5 e dashboards). Inspecionadas 24 cenas finais em seis folhas de revisão. Os seis players locais carregam sem erros com as durações esperadas. Documentação oficial exibida pela interface LocalDocs. Tarefas 1075–1079 reutilizadas no Forge.

Limitação de acesso: o endereço HTTPS solicitado redirecionou para autenticação Cloudflare Access no navegador desta sessão. A aba foi aberta para o usuário; a prévia local equivalente foi exibida e validada. Não se afirma validação do conteúdo além dessa autenticação.

Uploads e storage seguem a política de arquivos ignorados do projeto: commit/push de código e documentação não publica as mídias nem altera o ambiente de produção. Bloco 2 permanece fora do escopo.
