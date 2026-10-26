ID: FEAT-015
Tipo: Feature
Nome: Homepage — Circuito contínuo (feixe de circuitos com a fluidez do vídeo de referência), construída por partes

Objetivo:
Refazer a experiência visual da homepage numa prévia isolada (/preview/circuito-continuo):
um feixe de dezenas de fios de luz que nasce no Hero e atravessa a página com a fluidez
do vídeo de referência, e seções redesenhadas para o feixe passar por trás e entre os
painéis. A home oficial (/) não muda até a virada aprovada.

Contexto:
Uma primeira tentativa (24/10/2026, sem commit) desenhou só linhas finas por cima do
Hero antigo, cruzando textos e cards, e foi reprovada pelo dono do projeto em 25/10/2026.
Ela foi arquivada fora do git em scratch/feat-015-tentativa-anterior/ e refeita do zero.
Na Parte 1 chegou-se a usar uma arte de placa-mãe (gerada no ChatGPT) com pacotes de luz
correndo pelas trilhas marcadas à mão; o resultado foi recusado: os pacotes não seguiam
as trilhas com precisão e o movimento não tinha a fluidez do vídeo. Por decisão do dono,
o Hero ficou só com o feixe de circuitos, sem a placa. Os originais da arte ficam fora
do git em scratch/home-flow-arte/.
O trabalho é feito por partes, cada uma com plano, aprovação e conferência visual
próprios:
1. Hero com o feixe de circuitos e a camada de luz contínua da página (em andamento)
2. Saída do feixe do Hero até o Sobre, com o Sobre em card (feita, Forge #1662) (requisito do dono: no Hero o feixe passa por trás
   do conteúdo; nas seções seguintes deve passar ao lado e pelos espaços entre os blocos)
   Requisito do dono (25/10): na primeira descida o feixe vai sendo traçado para baixo junto
   com a rolagem; depois de traçado fica fixo (subir e descer de novo não repete).
3. Sobre + Temas
4. Blog (carrossel e posts)
5. Instagram + Newsletter
6. Rodapé (pelo plano do rio, o feixe atravessa o rodapé e sai pela base da página; a ideia
   anterior de convergir no símbolo da marca foi descartada pelo dono em 25/10/2026)
7. Echo no Hero: o robô Echo (motor em código do projeto echo-companion, copiado com a versão
   de origem anotada) flutuando ao lado do feixe, acompanhando os pulsos com o olhar e
   reagindo ao início da rolagem; nas partes seguintes acompanha o feixe como guia da página
8. Fechamento: desempenho geral, documentação final e plano da virada

Arquitetura (Parte 1):
- public/assets/js/site-home-flow.js desenha o feixe em WebGL2 (sem biblioteca) no
  canvas do Hero: 40 fios no desktop e 18 no celular, derivados de uma curva central
  (Catmull-Rom) que entra pelo alto à direita, passa por trás do conteúdo e sai pela
  base. Cada fio tem afastamento, torção e ondulação próprios no tempo; brilho gaussiano
  com mistura aditiva, cor de ciano a azul e violeta, névoa luminosa larga no centro do
  feixe e pulsos de dados brancos correndo pelos fios.
- O texto do Hero fica centralizado com sombra radial atrás; o feixe passa por trás
  com brilho reduzido nessa área.
- Camada de fundo da página inteira: canvas fixo (.en-flow-bg, z-index -2) atrás de todas as
  seções, com um feixe distante (14 fios no desktop, 8 no celular; largo, apagado e em meia
  resolução, parecendo desfocado), na diagonal oposta à do feixe principal, e luz ambiente
  (halos grandes que respiram devagar). A forma do feixe distante é interpolada entre três
  formas (topo, meio e fim da página) conforme a rolagem, com amortecimento; por ser fixa,
  nunca é cortada ao descer. O fundo do Hero é transparente para mostrar essa camada.
- (Histórico da Parte 1, substituído pelo rio abaixo) O feixe principal se dissolvia nos
  últimos 14% da altura do Hero até a Parte 2 continuar o trajeto pela página.
- Descartados no caminho (25/10/2026): placa-mãe como fundo (seria cortada ao rolar), padrão
  de circuito impresso com poeira de luz (os pontos não seguiam as trilhas).
- Fundos uniformes (Forge #1652): na prévia, Sobre, Blog, Instagram, Newsletter e Rodapé
  ficam sem fundo próprio, e as camadas decorativas de seção não aparecem: brilho e grade do
  Sobre, faixa de Temas (largura total, com grade e bordas superior e inferior), grade,
  degradê e manchas desfocadas do Instagram, degradê da Newsletter e a linha superior do Rodapé. Regras só em site-home-flow.css, que
  é carregado apenas na prévia; nenhum componente foi editado. Cards e painéis (cartão de
  perfil e seu halo, cards de categoria, cards de posts e Instagram, caixa da Newsletter e o
  divisor interno do Rodapé) mantêm os fundos. Conferido por varredura de todos os elementos
  de largura total em 1440 e 390 px de largura.
  A camada de luz fica ~30% mais apagada conforme o Hero sai da tela (véu uniforme, contínuo
  pela rolagem), igual em todas as seções. Emendas conferidas sem borda em 1440x900 e 390x844.
- Echo no Hero (Parte 7, Forge #1656): o robô Echo é desenhado pelo motor do projeto
  echo-companion, copiado SEM alteração para public/assets/vendor/echo-robo/ (robo-motor.js e
  robo-roupas.js do commit 43aff61; cópias conferidas por hash; o cabeçalho do motor mantém a
  origem MIT do Coucou). As fantasias (robo-roupas-echo.js) não são carregadas. O Echo fica
  num lugar do Hero livre de texto e botões: o script mede o retângulo real de cada linha de
  texto e de cada botão e testa lugares candidatos (desktop: à direita do título, depois mais
  abaixo, depois à esquerda); se nenhum servir, ele não aparece (é o caso hoje no celular, onde
  o texto ocupa quase todo o Hero). O canvas é 1,8x maior que o robô (o motor desenha numa caixa
  de 280 x 280 que o robô ocupa quase inteira; sem folga, o quique e a cabeça eram cortados) e é
  limpo inteiro a cada quadro; as bordas têm esmaecimento circular. A elipse de luz "no chão" do
  motor (drawGlow) é desligada só nesta instância; se o motor mudar o nome dessa função numa
  atualização, revisar. Em vez da queda do motor, o Echo sai de um portal de
  energia (shader WebGL próprio, canvas .en-flow-portal, inspirado na referência "Magic Portal"
  do Adobe Stock, sem usar o vídeo): uma esfera turbulenta girando cresce (0-0,5 s), o centro
  escurece e abre em anel com borda de fumaça e chamas (até 0,9 s; o disco interno é opaco e
  tampa o feixe que passa atrás), o Echo sai do centro
  crescendo (até 1,6 s) e o portal se contrai com um clarão e some (até 2,2 s); o canvas do portal
  é escondido depois. O portal só é usado onde cabe inteiro sem tocar texto nem borda (raio de
  0,85x o robô); senão, o Echo só aparece crescendo (é o caso em 1280x720). Sequência conferida
  quadro a quadro em Chrome sem janela (tempo real) em 1440x900, 1920x1080 e 1280x720. Comportamento depois: olha
  para o pulso de luz mais próximo do feixe ou para o cursor (quando o mouse se mexe); emoção
  "feliz" de ~1 s quando um pulso passa perto (no máximo a cada 6 s); olha para baixo na
  primeira rolagem; para de desenhar fora da tela; sem som (o gancho de som fica vazio).
  Para atualizar a cópia quando o Echo mudar no app: copiar de novo os dois arquivos de
  echo-companion/public, anotar o commit aqui e conferir a prévia.
- Ritmo entre seções e logo do Sobre (Forge #1657): na prévia, margem interna de 80 px (56 px no
  celular) em Sobre, Blog, Instagram e Newsletter, com compensação das folgas internas de cada
  seção (Sobre 40/12 px embaixo, Instagram 114/90 px em cima, Rodapé 80 px em cima; faixa de Temas
  sem folga embaixo). O vazio visível entre seções (medido até a borda de textos e painéis) ficou
  em 160-165 px no desktop (1440x900) e 112-113 px no celular (390x844). A emenda Hero/Sobre fica
  fora da meta porque depende da altura da tela (o Hero ocupa a tela inteira). O cartão do logo do
  Sobre tem no máximo 340 px no desktop e 240 px no celular (antes, 576 px).
- Velocidade por tempo decorrido; resolução limitada (DPR 1,5 no desktop, 1,25 no
  celular); pausa com a aba oculta ou com o Hero fora da tela; recálculo no
  redimensionamento.
- Sem WebGL2 ou com erro no script: só o fundo escuro com brilho difuso. Com
  prefers-reduced-motion: um quadro parado do feixe. O conteúdo nunca depende do script.
- Cada pulso que sai pela base dispara o evento window "en:flow-exit", ponto de
  partida do feixe da Parte 2.
- Textos, CTAs e animação de entrada continuam os da home (dados do PHP e
  site-hero-preview.js, reaproveitado sem alteração, que também mantém o carrossel).

- Feixes iniciando com a página (Forge #1659): o site-home-flow.js é carregado logo depois do
  Hero (sem defer), então feixe e fundo desenham antes de o resto da página ser lido; o Echo e
  o portal esperam o motor do Echo (scripts com defer) e são criados no DOMContentLoaded. Na
  abertura (~1,1 s) o feixe do Hero acende da origem até a base e a luz de fundo sobe de
  apagada ao normal. Os textos do Hero não piscam: com JavaScript começam escondidos (classe
  en-flow-js no <html>) e entram pela animação de site-hero-preview.js. Travas: se a entrada
  não começar em 1,2 s (rede lenta), o script aplica .en-flow-texts-ready (fade e estado final
  travado); se nem o JavaScript rodar, a animação CSS en-flow-hero-failsafe mostra os textos em
  2,5 s; com prefers-reduced-motion ficam visíveis desde o início. Medido em Chrome sem janela
  (1440x900, sem cache): conexão normal, feixe 0,25 s e título 0,84 s (antes, feixe 0,49 s e
  textos piscando); 3G simulado, feixe 3,16 s e título 4,53 s; entrada animada bloqueada,
  título 1,62 s; todo o JavaScript bloqueado, título 2,79 s.
- Parte 2, Sobre em card com o feixe atravessando (Forge #1662): a prévia usa o componente
  app/Views/components/site/home-flow/about.php (mesmos dados do about-preview.php oficial,
  que não muda). Card com vidro translúcido (fundo 34% + desfoque de 5 px) só atrás do texto,
  sumindo antes dos pilares; logo de 96 px (72 px no celular) ao lado do título e do texto de
  abertura; parágrafos em duas colunas (uma no celular); pilares 2 + vão + 2 no desktop, 2×2
  com vão central entre 768 e 1279 px, empilhados abaixo de 640 px (os títulos em Orbitron não
  cabem em 2×2). Os Temas seguem como antes. O feixe principal passou a ser UM caminho só,
  desenhado inteiro no canvas fixo .en-flow-path (z-index -1): a mesma fita de fios entra pelo
  alto do Hero, atravessa o Hero (com a abertura acendendo de cima para baixo), sai pela base,
  entra pelo topo do card, passa por trás do texto (pelo vão entre as colunas), afunila e passa nítido pelo vão
  central dos pilares (ocupa no máximo 37 px de cada lado do centro num vão de 100 px) e se
  dissolve antes dos Temas. A primeira versão desenhava duas fitas (a do Hero e a do trajeto)
  com emenda cruzada; o dono viu que pareciam dois feixes e duas páginas, e foi refeita assim.
  O card não tem borda nem vidro com degrau no topo: os dois começam transparentes e aparecem
  nos primeiros ~140-160 px, para o feixe entrar no card sem linha separando. Posições medidas no layout (offsetTop, sem as transformações das
  entradas) e recalculadas ao redimensionar, ao carregar fontes e a página, e quando o Sobre
  muda de tamanho. Traçado: a ponta acompanha a rolagem (70% da altura da tela) na primeira
  descida, nunca recua; num salto pelo menu corre até lá; recarregar traça de novo. Pulsos só no
  trecho traçado. Movimento reduzido: trajeto parado e já traçado, redesenhado na rolagem.
  Como o script roda logo após o Hero, a camada é criada no DOMContentLoaded (o Sobre vem
  depois no HTML). Testes (Chrome sem janela, tempo real): traçado na descida, fixo ao subir e
  descer, salto pelo menu, movimento reduzido; 60 quadros por segundo durante a rolagem
  (renderização por software, é um piso); contraste do texto com o feixe atrás no ponto mais
  claro de 9,4:1; vãos entre seções mantidos (165/160/160/161 px no desktop, 112-113 px no
  celular); sem rolagem horizontal em 1920, 1440, 1280, 390 e 360 px.
  (O trecho acima descreve a Parte 2 como foi entregue; o trajeto depois do Hero foi
  substituído pelo rio, descrito a seguir.)
- Rio de luz, do Hero até a base da página (Forge #1663, 25/10/2026). Histórico: um trajeto
  com corredores laterais, cantos de 45° e travessias entre blocos foi reprovado pelo dono
  (cantos dobrando os fios, ganchos, vincos e final "em vassoura" no símbolo do rodapé); um
  esboço com fio fino na margem também (não parecia o vídeo). Escolhido por esboço em imagem:
  o feixe como um rio, passando por trás do conteúdo. Implementação em createFlowLayer
  (site-home-flow.js):
  * Trecho do Hero igual ao aprovado (mesmos pontos e largura). A base do Hero mudou de
    propósito: o feixe não afunila mais ali (a largura passa do Hero para o rio sem quina,
    em cosseno) e o brilho desce suave da metade do Hero até o do rio, sem degrau.
  * Depois do Hero: voltas alternadas (começa à esquerda), verticais no ápice e meio cosseno
    entre elas; uma volta a cada ~1.400 px no desktop e ~900 px no celular; testa a conta
    normal, uma volta a menos e uma a mais e fica com a que não encosta em nada e perde menos
    largura. Voltas com tamanhos um pouco diferentes.
  * Largura perto da do Hero: 62% da referência nas voltas (45% no celular), alternando 95% e
    78% entre elas, interpolação em cosseno. Regras medidas: raio da curva >= 1,2x a largura
    (cálculo usa 1,4x de folga para o Catmull-Rom do motor); variação <= 25% da largura local
    a cada 100 px de caminho.
  * Botões, campos, formulário, links com cara de botão e ícones sociais ficam fora do
    feixe: cada volta procura deslocamento vertical (±360 px; ±480 no celular) e lateral, e a
    descida final pode inclinar; o que ainda encostar estreita até 20 px. Posições medidas
    sem o deslocamento das entradas animadas (offsetTop).
  * Final: atravessa o rodapé e sai pela base da página; o traçado vai até a base quando a
    rolagem chega ao fim.
  * Leitura: sombra escura nos textos das seções abaixo do Hero (site-home-flow.css; textos em
    degradê ficam sem sombra) e o feixe fica 60% mais fraco atrás de letra de até 16 px.
  * Cache do caminho na sessão (feito pela outra IA): a assinatura agora inclui RIVER_VERSION;
    mudou o cálculo, mude a versão (senão o navegador mostra o rio antigo).
  * Movimento reduzido: o quadro parado é redesenhado a cada novo cálculo (antes o feixe
    sumia nesse modo depois do carregamento).
  * Testes (Chrome sem janela, página real, ?flow-debug expõe os pontos para conferência):
    1920, 1440 e 1280: zero encostos em controles, largura 148-339 px, raio >= 1,22x,
    variação 25,1-26,1% (limite 25%: excesso de medição no motor, sem quina visível), sem
    rolagem horizontal, sem erros. Contraste: 1 a 2 textos pequenos com 4,1-4,2:1 (meta 4,5).
    768: 15 encostos (ícones/controles do Instagram) e um texto com 2,6:1. 390: 6 encostos
    (canto do formulário da Newsletter e botão do rodapé), feixe estreitando até 20 px.
    Sem a seção do Instagram (1440): 4 encostos. Pendentes de correção.
- Em 25/10/2026 outra IA implementou, sem plano aprovado, uma "Parte 2" (Sobre redesenhado em
  home-flow/about.php e o Echo virando guia fixo na tela durante a rolagem). Foi desfeita a
  pedido do dono; os arquivos daquele estado estão em scratch/outra-ia-parte2-2026-10-25/ e a
  tarefa #1661 ficou como registro.
Arquivos criados:
- app/Views/components/site/home-flow/hero.php
- app/Views/components/site/home-flow/about.php
- public/assets/css/site-home-flow.css
- public/assets/js/site-home-flow.js
- public/assets/vendor/echo-robo/robo-motor.js e robo-roupas.js (cópias do echo-companion)

Arquivos alterados:
- app/Views/site/home-circuito-preview.php (reescrito: Hero novo + seções atuais da home sem alteração)
- docs/features/FEAT-015-homepage-circuito-continuo.md (este documento, reescrito)
- config/routes.php e app/Controllers/Site/HomeController.php: rota GET /preview/circuito-continuo
  e método circuitContinuousPreview (noindex, nofollow), mantidos da tentativa anterior

Arquivos removidos (arquivados em scratch/feat-015-tentativa-anterior/):
- public/assets/js/site-hero-circuit.js, public/assets/css/site-hero-circuit.css
- public/assets/js/site-circuit-journey.js, public/assets/css/site-circuit-journey.css

Arquivos preservados:
- app/Views/site/home.php e app/Views/components/site/home/* (home oficial intacta)
- Rodapé compartilhado e Central Nerd

Banco de dados:
- Mudanca de schema: nao
- Mudanca de dados: nao
- Script necessario: nao

Dependencias:
- Nenhuma biblioteca externa nova (WebGL2 nativo do navegador; Anime.js já existente)
- Cópia interna do motor do Echo (projeto próprio echo-companion), em public/assets/vendor/echo-robo/

Impacto em producao: nulo (rota de prévia com noindex; home oficial intacta)
Afeta rotas criticas: nao

Risco: baixo (código isolado na rota de prévia)

Origem validada: local (branch feature/homepage-circuito-continuo)
Paridade local -> stage: validada (25/10/2026, Forge #1675) - ver "Deploy na stage" abaixo
Paridade stage -> pacote: validada (13 arquivos tirados da stage e conferidos; site.css = produção + regra do menu, exceção registrada)
Validado producao: sim (25/10/2026, Forge #1676)

Validado local: parcial (rio conferido em 1920, 1440 e 1280; tablet e celular com encostos pendentes; Echo pela página com o celular pendente, Forge #1666; FPS pendente de medição com o navegador visível)
Validado stage: sim (25/10/2026)

Apto para release: sim (pacote tirado da stage, em plano próprio de produção)

Deploy na stage (25/10/2026, Forge #1675, scripts/home-rio-stage-deploy.php):
- Conferência antes do envio (só leitura, 322 arquivos de código do git HEAD x stage): 222 iguais,
  56 diferentes e 44 ausentes, quase todos do admin e das telas locais (não rodam na stage).
  Todas as dependências da home nova iguais. Atrasos conhecidos que não afetam a home: Helpers.php
  sem asset(), bootstrap.php sem config/instagram.php (que não existe na stage: o bootstrap do
  commit derrubaria a stage, por isso não foi), layout da stage com Tailwind por CDN.
- Pacote de 14 arquivos: 10 novos (view e componentes home-flow, EchoFantasias, echo-fantasias,
  site-home-flow.css/js, robo-motor/roupas/roupas-echo), site-hero-preview.css substituído (card de
  destaque do Blog igual ao local, aprovado pelo dono) e 3 como "versão da stage + só a mudança":
  HomeController (view do index), HomeService (echo_outfit) e site.css (botão de menu oculto por
  padrão, visível até 767 px). Cópias anteriores em storage/home-rio-deploy/stage/before; envio
  com .tmp + rename e conferência por hash; --rollback disponível.
- Testes na stage: home 200 com rio, portal e Echo em 1920, 1440, 1280, 768 e 390; SEO idêntico ao
  de antes do envio e X-Robots-Tag noindex mantido; blog, um post, central-nerd e robots.txt 200;
  console só com o aviso já existente do Tailwind por CDN; botão de menu oculto de 800 px para cima
  e visível de 767 para baixo (home e blog); troca de fantasia pelo admin local com alvo Stage.
- Pendência encontrada (já existe em produção, não causada por este deploy): em exatamente 768 px o
  botão de menu aparece junto com os links e o cabeçalho fica apertado (o logo é cortado).
- Correção antes da produção: a versão dos assets (?v=) da view caía em "1" no servidor (procurava só
  em public/); passou a procurar também a pasta pública acima do _app_core, como o layout. Reenviada
  à stage (--refresh, com cópia da versão anterior) e conferida.

Deploy em produção (25/10/2026, Forge #1676, scripts/home-rio-production-deploy.php):
- Commit local c68febd na branch feature/homepage-circuito-continuo, só com esta entrega (nos arquivos
  com mudanças de outro trabalho, só os trechos desta entrega); sem push. Conferido: 13 dos 14
  arquivos do pacote idênticos ao commit; o HomeController do commit tem a mais só o método da rota
  de prévia, que existe apenas no local.
- Conferência da produção (só leitura): dependências da home iguais às da stage; bootstrap.php
  difere só no bloco que manda noindex na stage (esperado). site.css da produção difere do que a
  stage tinha antes: a stage tem a FIX-009 (imagens dos artigos, 12/10), nunca promovida. Por decisão
  do dono, o site.css foi como versão da PRODUÇÃO + só a regra do botão de menu (a FIX-009 fica para
  um plano próprio, junto com o PostService.php, que é a outra metade dela).
- Backup antes: BS-PROD-20261025-211822 (banco, uploads e sistema), verificado como válido; cópia dos
  4 arquivos trocados em storage/home-rio-deploy/production/before.
- Pacote: 13 arquivos tirados da stage e conferidos com o manifesto da stage + site.css (produção +
  menu). Envio com .tmp + rename e hash; HomeController por último. --rollback disponível.
- Pós-check: home 200 e indexável (sem noindex no cabeçalho e nas metas); título, metas, Open Graph,
  canonical de produção e 3 blocos ld+json; assets 200 com versão real; blog, categorias, um post,
  central-nerd, robots.txt e sitemap.xml 200; home com rio, portal e Echo (Jack) em 1920, 1440, 1280,
  768 e 390; console só com o aviso já existente do Tailwind por CDN; botão de menu igual à stage.
- Paridade: 13 arquivos idênticos stage = produção; exceção registrada: site.css (FIX-009 só na stage).
- Banco de produção não alterado (sem fantasia gravada: vale o padrão Jack).

Observacoes:
- Recuperacao do WebGL (25/10/2026): a perda de contexto de qualquer um dos dois
  canvases parava o ciclo compartilhado e deixava feixe e fundo vazios ate uma
  recarga manual. O listener agora agenda uma unica recarga para reconstruir
  programas e buffers das duas camadas; um marcador de sessao com janela de 30 s
  impede recargas em ciclo quando a GPU permanece indisponivel. A geometria do
  percurso, as larguras e o comportamento do Echo nao foram alterados.
- Abertura e custo do trajeto (25/10/2026): o progresso inicial passou a contar
  apenas o tempo efetivamente renderizado, evitando que o calculo sincrono do rio
  faca o feixe aparecer pronto sem a descida. A malha do solucionador de layout
  passou de 4 para 8 px; o desenho final continua reamostrado em 840 pontos, mas o
  trabalho de colisao e curvatura na carga inicial foi reduzido aproximadamente
  pela metade.
- Inicio coordenado do percurso (25/10/2026): a trajetória completa é preparada
  antes do primeiro quadro visual. Portal, Echo, Hero e feixe passam a iniciar no
  mesmo ciclo, sem trocar um caminho provisório por outro após 2,3 s. O resultado
  do solucionador continua em cache por assinatura da geometria da página, tanto
  em memória quanto na sessão, e medições repetidas reutilizam o mesmo rio.
- O traçado visual nasce na saída do Hero e alcança a posição atual da rolagem pela
  ponta, com velocidade inicial limitada entre 900 e 2600 px/s. A geometria já
  existe desde a abertura, mas não é revelada antecipadamente; isso também mantém
  progressiva a entrada em recargas feitas no meio ou no fim da página.
- Echo com Propulsão Matrix, Fantasia de Jack e profundidade (25/10/2026):
  * (Registro da outra IA, conferido em 25/10/2026: este fundo preto no `.en-flow-portal` não está no CSS; o disco escuro que tampa o feixe é o do próprio shader do portal.)
  * Rodapé em camada frontal: adicionado `.site-footer` em `position: relative; z-index: 2`, garantindo que o Echo (`z-index: 1`) passe estritamente por trás de todo o texto e conteúdo do rodapé.
  * Travessia horizontal responsiva e alternância ociosa: velocidade lateral calibrada para ~420 px/s com `springX: 3.2` para travessia nítida em ~2 segundos. Adicionado temporizador de patrulha: após 5 segundos sem rolagem, o robô inverte o lado alvo para explorar a outra margem da tela, garantindo que nunca fique preso em um único lado.
  * Portal: a outra IA trocou, sem pedido do dono, o shader do portal aprovado (fumaça com
    ruído deformado, chamas, fiapos e clarão no fechamento) por um disco liso que vira um anel
    de neon, tirou a verificação de erro do shader e mudou a curva de tempo. Restaurado em
    25/10/2026 a partir da versão das 15:09 (shader, criação com verificação de erro e limpeza
    a cada quadro, curva de saída suave), mantendo a passagem dela para o Echo seguir a página
    no fim da chegada. Conferido: código do portal idêntico ao aprovado; sequência real no Hero
    em 1440x900 e 1920x1080 (portal completo) e 1280x720 (Echo surge crescendo, sem portal);
    sem erros no console.
  * Ignição da propulsão pós-portal: assim que o portal se fecha completamente (`T_END`), o Echo reduz suavemente de tamanho para a escala de acompanhamento e ativa os 3 jatos Matrix sob os propulsores.
  * Detecção de seções e alternância de lados: `getActiveTarget()` passou a utilizar coordenadas absolutas (`getBoundingClientRect().top + window.scrollY`), garantindo alternância precisa de lados conforme a rolagem (Hero: direita, Sobre: esquerda, Blog: direita, Instagram: esquerda, Newsletter: direita, Rodapé: esquerda).
  * Fantasia de Jack Esqueleto (Halloween): peças originais `sorrisoCosturado` (no visor) e `ternoEsqueleto` (terno risca de giz com gravata de morcego), carregadas via `robo-roupas-echo.js` e mantidas ativas de forma permanente o tempo todo (tanto ao sair do portal no Hero quanto durante a navegação por toda a página).
  * 3 Jatos de Propulsão Matrix: distribuídos sob a base do corpo em 3 colunas independentes (esquerda `-24`, centro `0` e direita `+24`).
  * Fim do corte em quadrado / borda: eliminação de qualquer corte rígido por coordenadas, aplicando um gradiente de opacidade suave que dissipa cada caractere no ar até `alpha = 0.0` antes do fim de vida.
  * Letras nítidas e legíveis: conjunto de caracteres Matrix alfanuméricos e tech (`0-9`, `A-Z`, `<>/{}$#*+=`) desenhados com fonte `bold 14px monospace`, ignição branca e corpo verde néon em alto contraste.
  * Camada de profundidade: configurado z-index: 1 no canvas do Echo e z-index: 2 nos cards, fotos e blocos de conteúdo da página, fazendo com que o robô e sua cauda de código Matrix passem estritamente por trás das imagens e cards.
- Menu responsivo (25/10/2026): `.site-mobile-toggle` fica oculto por padrão no
  CSS externo e recebe `display: inline-flex` somente até 767 px. Isso elimina o
  conflito de cascata que fazia a regra visual sobrescrever `md:hidden` no desktop.
  Atenção: a mudança está em public/assets/css/site.css, que é do site oficial. Conferido
  na home oficial (/): desktop sem o botão de menu e celular com ele.
- Virada local da home (Forge #1672, 25/10/2026): HomeController::index passou a mostrar
  site/home-circuito-preview com o ViewModel e o SEO oficiais da home (conferido: título, metas,
  Open Graph, canonical e 3 blocos ld+json idênticos aos da home anterior; sem noindex). A home
  anterior continua em app/Views/site/home.php e app/Views/components/site/home/*, sem alteração:
  para voltar, trocar a view no index. Só o local mudou; stage e produção dependem do deploy.
  Por decisão do dono, vai tudo como está na prévia: Echo seguindo a página, fantasia (agora
  escolhida no admin, FEAT-016) e o rio com os encostos pendentes no tablet e no celular.
- Echo pela página (outra IA, Forge #1666): conferido em 1440 que, ao acompanhar a rolagem,
  o Echo às vezes fica por cima de títulos (ex.: "O que você encontra", "Temas centrais",
  "no Instagram"); no celular ele não aparece (o Hero não tem lugar livre). Pendente de
  decisão do dono.
- Rollback da Parte 1: apagar os três arquivos criados e restaurar a view anterior a partir
  de scratch/feat-015-tentativa-anterior/ (ou remover a rota e o método da prévia).

Pendencias de 25/10/2026 (texto original; as correcoes estao em "Reconciliação de 26/10/2026"):
- Fundo ambiente compartilhado nas paginas publicas que usam o layout do site, exceto login e
  Home. A Home preserva seus canvases exclusivos (rio, portal e ambiente), sem duplicacao.
- [Corrigido em 26/10] A versao em Canvas 2D (site-ambient-background.js) foi descartada e
  removida; stage e producao usam a camada de fundo WebGL2 do site-home-flow.js.
- Cabecalho conferido em 768 px: navegacao desktop integra, sem menu movel nem overflow.
  [26/10] A regra 768-1023 px estava so no local, sem commit; foi para a stage em 26/10.
- Echo passou a usar movimento amortecido continuo e validacao do corredor completo. Textos,
  titulos, cards, imagens, botoes, links de acao e formularios sao obstaculos; sem espaco livre,
  ele se oculta suavemente. Varreduras em 1440 e 390 px, a cada 300 px, tiveram zero sobreposicoes.
  [26/10] Essas varreduras foram so no local; as medicoes da stage estao na reconciliacao.
- O perfil de smoke da stage registra `/admin` e `/login` como ausentes de forma deliberada e os
  relata como SKIP, mantendo as verificacoes administrativas completas no ambiente local.
  [26/10] Passou a conferir o 404 de `/admin` e `/login` (stage e producao) em vez de SKIP.
- Smoke local: `SMOKE-LOCAL-20261025-233122`, 26 OK, 0 FAIL, 0 SKIP.
- Producao permaneceu fora do escopo.
- Tarefa no Forge: [Home Fluxo] - Parte 1: Hero com placa-mãe (#1640).

Reconciliação de 26/10/2026 (Forge #1689 a #1692, #1683, #1685 e #1686):
- Motivo: auditoria de 26/10 (docs/governanca/RELATORIO-AUDITORIA-2026-10-26.md; correções na seção
  31). Em 25/10 às 23:43 a produção recebeu layouts/site.php, assets/css/site.css e
  shared/ambient-background.php sem commit e sem cópia para rollback; o local carregava o motor Canvas
  2D descartado (site-ambient-background.js, que não achava os elementos e não desenhava nada) e a
  stage tinha esse arquivo órfão.
- Fundo das páginas públicas: o componente site/shared/ambient-background põe o canvas .en-flow-bg
  antes do <main> e o site-home-flow.js desenha nele só a camada de fundo WebGL2. Local e Git ficaram
  iguais ao publicado; o site.php difere da stage e da produção só no Tailwind (compilado no Git, CDN
  nos servidores). site-ambient-background.js removido do local e da stage, com cópias em
  storage/home-pendencias-deploy/stage-20261026 (local-removidos/ e before/).
- Emendas (opção A, decisão do dono: .circuit-bg e .scanline continuam, como na Home). Saíram a faixa
  com borda e fundo do filtro e o fundo da grade do /blog, o fundo e a borda de #comentarios no post, o
  fim escuro do degradê do Hero (blog, post, 404 e post indisponível), o fundo próprio do body e a caixa
  borrada atrás do Hero da Central Nerd, o backdrop e a grade das páginas de política e termos e o
  degradê de largura total da newsletter fora da Home (regra .en-flow-bg ~ main no site.css, que não
  alcança a Home). Ficaram de propósito o fundo do rodapé e o da seção das páginas de política e
  termos: sem eles, 24 textos perderam mais de 15% de contraste e um ficou abaixo do mínimo AA.
- Echo (#1666) e cabeçalho 768–1023 px (#1684): código local sem alteração, levado à stage para
  aprovação visual. A produção continua com a versão anterior de ambos.
- Debug da stage (#1685): já desligado na prática (rota inexistente devolve a página 404 em HTML; com
  debug seria texto com URI:). config/app.php sem edição; o padrão `?? true` fica como risco em stage
  e produção.
- Smoke: admin_enabled false em stage e produção, com base no public/.htaccess (commit b8bdf8c, igual
  nos três ambientes), que responde 404 para admin, login e logout no domínio; o smoke confere esse
  404 em vez de só marcar SKIP.
- Rollback da produção (#1689): PREPARADO e VALIDADO, NÃO executado. scripts/home-reconciliacao-deploy.php
  producao --check (só lê a produção) e --sandbox-test (rollback completo, interrupção e retomada, trava
  de hash, php -l), com cópias e manifesto (SHA-256 completo) em storage/home-pendencias-deploy/production.
  Antes = site.php do backup BS-PROD-20261025-234355 (o zip não tem public/) e site.css do pacote das
  21:21 (produção atual = esse arquivo + bloco .en-flow-bg de 9 linhas); o componente não existia e é
  removido. O --rollback real exige --confirmar.
- Deploy na stage: o mesmo script, ações stage --check, --backup, --sandbox-test (deploy interrompido,
  rollback e retomada), --deploy, --verify e --refresh. 9 arquivos enviados e 1 removido, cada escrita
  com releitura do hash antes, .tmp + rename e conferência; o site.css foi reenviado (--refresh) depois
  da medição de contraste. Manifesto, cópias, prints e medições em storage/home-pendencias-deploy/stage-20261026.
- Testes: verify-changes 22 de 23 (as 2 falhas de PHPStan estão em arquivos do Instagram de outro
  trabalho, sem commit; os arquivos desta entrega passam sem erro); smoke local 26 OK
  (SMOKE-LOCAL-20261026-145850); smoke stage 8 OK, 0 FAIL, 1 SKIP (SMOKE-STAGE-20261026-145927);
  stage --verify com as 9 páginas (7 em 200, 2 em 404 HTML) e noindex. Navegador headless (Chrome +
  protocolo DevTools, sem dependência nova) em 9 páginas x 6 larguras (360, 390, 767, 768, 1024, 1440):
  canvas ativo, camadas da Home, sem rolagem horizontal, sem erro de console nem recurso falho, antes e
  depois; estilos da Home iguais antes e depois (a única diferença, z-index 2 num elemento oculto da
  newsletter, vem do CSS do #1666); contraste medido por pixel em 604 textos, sem texto que ficasse abaixo
  do AA por causa desta entrega (as variações que sobraram estão dentro do ruído do fundo animado,
  até 15,9% entre duas medições do mesmo código). Produção conferida por hash no início e no fim:
  14 arquivos idênticos.
- Echo medido quadro a quadro na stage (antes = versão de produção; depois = #1666): sobreposição real
  com títulos, textos e controles caiu de 228–364 quadros por percurso para 0 em todas as larguras; no
  celular passou a aparecer depois do Hero (antes nunca aparecia); em 360, 390 e 767 px todas as trocas
  de lado passam por opacidade ≤ 0,05.
- Pendente de aprovação visual do dono na stage (tarefas #1686 e #1691 abertas): Echo em 768 e 1024 px
  não sai do portal no Hero (só aparece ao rolar, ~4,7 s); de 768 px para cima, 4 de 8 (768), 7 de 7
  (1024) e 6 de 7 (1440) trocas de lado caem de 1 para 0,15 de opacidade no mesmo quadro, sem fade
  gradual; cabeçalho 768–1023 px; páginas públicas sem as emendas. A confirmação em navegador com GPU
  real não foi feita (o painel do navegador estava oculto e a página fica pausada).
- Pendente para a produção (plano próprio, depois da aprovação): Echo, cabeçalho, páginas sem emendas,
  Tailwind compilado x CDN, FIX-009 e o padrão `?? true` do debug.
