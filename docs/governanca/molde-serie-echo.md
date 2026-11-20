# Molde da série de vídeos do Echo

> **Para quem:** qualquer pessoa ou IA (Claude Code, Antigravity, Codex, Gemini) que vá produzir um vídeo do Echo para o Instagram (@estrategia_nerd) e o YouTube Shorts (@estrategianerd).
> **Criado em:** 20/10/2026 (Forge #1560), a partir do Manifesto Estratégia Nerd (v12, aprovado) e do Reel do Dia do Profissional de TI. **Atualizado em 01/11/2026** (Forge #1801) com o vídeo do Castlevania, **em 06/11/2026** (Forge #1915) com o quarto vivo em camadas (`quarto_setup_raster.js` + `producao-camadas-v1/`) como padrão oficial de todos os vídeos da série, e **em 15/11/2026** (Forge #2076) com o padrão alternativo "Monitor em Foco - Espectador Nerd (Sem Narração)" para aberturas originais e momentos épicos de games.
> **Leia junto:** `docs/governanca/diretrizes-redes-sociais.md` (estratégia, público, pilares e regras das duas redes), `docs/governanca/guia-direcao-audiovisual-ia.md` (princípios de direção) e a skill `video-echo-serie`.

Este documento separa três coisas que **não podem ser confundidas**:

1. o que é **igual em todo vídeo** da série (seção 1);
2. o que **se decide de novo em cada vídeo**, com o usuário (seção 2) — as escolhas do Manifesto aparecem só como exemplo;
3. os **erros já cometidos** e como evitá-los (seção 8).

---

## 1. O que é igual em todo vídeo da série

| Item | Regra |
|---|---|
| Apresentador | O Echo, desenhado pelo motor oficial (`robo-motor.js`): **sem braços e sem pernas**, flutua, mãos flutuantes (#7fe3f5 / #00d4ff). Fantasias vêm de `robo-roupas-echo.js` (cópia usada no Dia do TI: `conteudo-planejado/videos-echo/echo-dia-do-ti/v3/vendor/`). |
| Atuação padrão | **Atuação padrão = Manifesto, pelo `_kit-echo`** (`conteudo-planejado/videos-echo/_kit-echo/echo-padrao.js`). Medida no vídeo aprovado: tempo sem mudança visível durante a fala ≤ 1,4 s (Manifesto medido: máx 1,1–1,2 s); intervalo entre deslocamentos ~2,05 s (média 201,9 px); viradas de cabeça e olhos a cada ~1,7 s (35 trocas em 60 s); flutuação rígida de ±5 px (~3,1 Hz) com casco 100% rígido (`sx=1, sy=1`, sem gelatina); relógio do motor vivo com `seek(t)` contínuo; sem brilho azul no chão (`drawGlow = () => {}`); pontos de passagem sem pousar a cada pose intermediária. |
| Fala | O Echo **mexe a boca durante toda a fala**, pela voz real (curva de abertura quadro a quadro pelo volume). Boca fechada nas pausas e no silêncio final. |
| Formato | Vertical 9:16, 1080×1920. Prévias em 540×960. |
| Área segura de TEXTO e rosto (Reels + Shorts) | O mesmo vídeo vai às duas redes: texto e rosto do Echo entre **Y 250 e 1450**, entre **X 90 e 990**, e nada além de **X 925 entre Y 970 e 1450** (coluna de botões). Era Y 170–1540 e X 72–1008 quando o vídeo ia só ao Reels. **Medir, não estimar.** Imagem e fundo podem passar por baixo da interface. |
| Chamada final | Só a pergunta para os comentários. Sem "link na bio" (não funciona no YouTube), "inscreva-se" ou "siga o perfil" no áudio e na tela. |
| Publicação | **Nada vai a público sem o usuário ver o arquivo final antes**, mesmo que o plano diga "sobe já público". |
| Ordem de trabalho | Roteiro em texto → aprovação → desenho das cenas (prancha com quadros reais) → aprovação → animação em blocos → prévia com voz → aprovação → legenda e som → prévia → versão final → capa → agendamento. Cada seta com aprovação é uma parada (seção 3). |
| Quarto do Echo | É a **cópia do setup do usuário** (regra de 02/11/2026; padrão vivo em camadas aprovado em 06/11/2026 para **todos os vídeos**). Montar o quarto com `conteudo-planejado/videos-echo/_kit-quarto/quarto_setup_raster.js` (`producao-camadas-v1/`, seção 9): inclui os 5 quadros de Yu-Gi-Oh, 6 painéis hexagonais LED 3D, 3 barras neon, painel pixelado 6×6 em perspectiva, gabinete RGB com 4 ventoinhas e memórias RAM animadas, cadeira ampliada 1,28× e Echo ampliado sentado sobre a almofada da cadeira (`posicaoEcho: 'sentado'`, `k = 1.50`), saindo da cadeira no fechamento após pousar o controle 3D no assento para flutuar no meio do quarto (`posicaoEcho: 'frente'`, `k = 1.85`) com a Propulsão Matrix (`echo-propulsao-matrix.js`). |
| Aprovação | Só vale **"Aprovado"** dito pelo usuário depois do plano. "ok", "sim", "pode", "pode fazer", "manda" **não** aprovam. Mudou o escopo: parar e pedir de novo. |
| Conferências técnicas | Em todo bloco: o que não podia mudar continua idêntico (hash), 0 erros de página, texto dentro da área segura, quadros do MP4 olhados de verdade, emendas sem salto, volume final -16 LUFS e pico -1,5 dBTP (seção 7). |
| Comunicação com o usuário | Português simples e curto. Uma decisão por vez. Sempre dizer o que foi conferido e o que não foi. |

### 1.1 Subpadrão Oficial: "Monitor em Foco - Espectador Nerd (Sem Narração)"

Criado e aprovado em 15/11/2026 (Forge #2076) para vídeos de **nostalgia musical pura** (aberturas clássicas de desenhos, animes e séries dos anos 80/90) e **cenas épicas de gameplay**.

| Item | Especificação do Padrão "Monitor em Foco" |
|---|---|
| **Conceito & Papel do Echo** | O Echo atua como o **espectador nerd hipnotizado**, assistindo e reagindo ao conteúdo junto com quem vê o vídeo. Não é um apresentador falando para a câmera. |
| **Locução & Narração** | **Zero narração.** Proibido voz de IA (Edge-TTS), gravação falada ou interrupções verbais sobre o áudio. O impacto vem 100% da música e do conteúdo visual. |
| **Boca e Expressão** | Boca **fechada e imóvel** o tempo todo. Olhos expressivos (`wide`, `pill` ou `happy`), com bobbing sutil de cabeça no ritmo da trilha. |
| **Pose e Oclusão no Quarto** | Echo no modo `atras-monitor`, localizado atrás da mesa e do monitor central no setup vivo (`_kit-quarto`). As duas mãos azuis flutuantes (#00d4ff) ficam apoiadas e visíveis segurando a **moldura superior do monitor** (técnica portada de `cinema_desenho.js` de Power Rangers). |
| **Direção do Olhar** | **Olhar obrigatoriamente para baixo**, cabeça levemente inclinada e pupilas direcionadas para a tela do monitor (como se estivesse assistindo fixamente à exibição). |
| **Câmera & Zoom** | **A tela já começa rodando o vídeo desde o frame zero.** De 0,0s a ~1,2s: plano do setup estabelecendo o Echo segurando a tela e vidrado no que passa. De ~1,2s a ~1,8s: **push-in rápido e enérgico (~0,6s)** acelerando em direção à tela do monitor até que o vídeo ocupe toda a área útil 9:16. |
| **Tratamento Sonoro** | **Áudio original na íntegra**, masterizado a -16 LUFS e pico -1,5 dBTP. Em aberturas (ex.: Hulk, ThunderCats, SilverHawks), preservar arranjo original, guitarras, solos e coro sem cortes bruscos. Em games, trilha épica no talo sincronizada com a ação. |
| **Texto e Encerramento** | **Zero texto na tela.** Proibido caixas de HUD, títulos sobrepostos, tags ou legendas cobrindo a cena. O vídeo e a música rolam 100% limpos de ponta a ponta. Qualquer chamada para comentários ou contexto vai exclusivamente na legenda da publicação nas redes sociais. |

---

## 2. O que se decide em cada vídeo (perguntar ao usuário)

Nada desta tabela é padrão. As colunas de exemplo mostram **o que foi escolhido naquele vídeo**, para servir de referência, não de regra.

| Decisão | Como decidir | Manifesto (exemplo) | Dia do TI (exemplo) | Castlevania (exemplo) |
|---|---|---|---|---|
| Duração e ritmo | pelo roteiro e pela voz | 60 s, 60 fps | ~17 s | 44,6 s, 60 fps |
| Roteiro e CTA | texto aprovado; vale o que a voz **diz** | CTA "Acesse o link na bio e siga o perfil" | "tentou reiniciar?" | "Conta aí nos comentários" |
| Voz | edge-tts `pt-BR-AntonioNeural` (o ElevenLabs está sem créditos); o usuário vai gravar quando tiver microfone, sem refazer os vídeos antigos | Cornelius (voz-guia) | Cornelius, gravada pelo usuário | Antonio (edge-tts) |
| Cenários | roteiro visual com quadros reais (seção 4) | praça do feed, corrida, RPG, quarto gamer | sala de servidor | quarto do Manifesto com monitor 4:3 e gameplay real |
| Figurino do Echo | combinar com o tema | padrão | "Cara de TI" com camisa do Estratégia Nerd | padrão (Alucard pronto, não usado) |
| Herói e vilão (opcional) | só quando o tema tem herói e vilão claros (nem todo vídeo tem). Formato: o Echo de herói apresenta o tema; o vilão empurra a tela e fala um pouco; o herói volta no fim com a frase ou o golpe icônico e termina na logo. Exemplo: Yusuke apresenta, Toguro empurra a tela, Yusuke volta com "Veja no Estratégia Nerd… Leigan!" (mão com dedos, o disparo toma a tela e aparece a logo). Vilão com outra voz. Fantasias do par em `robo-roupas-echo.js` | não usou | não usou | não usou |
| Fonte e estilo da legenda | mostrar 2–3 opções sobre quadros reais | Orbitron + moldura HUD | Orbitron, sem caixa | a do Manifesto (escolha do usuário) |
| Posição da legenda | onde não cobre texto do vídeo, conferido cena a cena | em cima; desce onde há cartão | embaixo, sempre na mesma altura | em cima (Y 270), acima do jogo |
| Música | 3 trechos de 15 s da biblioteca para o usuário escolher | `pixabay_561438.mp3` (~122 BPM) | própria | Bloody Tears a partir de 0:22 (escolha do usuário) |
| Efeitos sonoros | o que acontece na tela pede som; o usuário pode pedir sons específicos | vidro real, carro, engrenagem, cliques | rack, apagão | nenhum |
| Capa | 3 opções com quadros reais + texto aprovado | Echo no quarto + "NADA DE ACHISMO. SÓ ESTRATÉGIA." | quadro de 12,6 s | Echo no quarto com a tela de título + "O SEGREDO DEPOIS DO FINAL" |
| Horário do post | ver a fila; slot ocupado = outro horário | 21/10 16:00 (12:00 e 19:30 ocupados) | 19/10 23:20 | YouTube 02/11 18:30; Instagram 04/11 18:30 (dias de Darksiders evitados) |

---

## 3. Ordem de produção (com as paradas)

1. **Roteiro em texto.** Analisar o que a voz diz e escrever cena por cena: tempo, frase, cenário, o que o Echo faz, texto na tela. Entregar o texto e **parar**.
2. **Voz.** Uma take contínua (não picotada por frase). Hoje: edge-tts `pt-BR-AntonioNeural`; antes da locução inteira, uma amostra só com os nomes em inglês do roteiro (a voz já errou "metroidvania" e "assets"). Transcrever com Whisper (`base`, já instalado) para ter o **tempo de cada palavra**; corrigir a grafia pelo roteiro (o Whisper erra nomes: "Estrateja", "axismo", "Castelhevania").
3. **Desenho das cenas (prancha).** Quadros reais dos cenários (existentes ou novos) com a frase e a ação de cada trecho. Mostrar ao usuário **antes de animar**. Cenas novas não podem ser cópia do começo do vídeo.
4. **Animação em blocos.** Um bloco por conversa nova do Antigravity, em sequência (nunca dois escrevendo no mesmo arquivo; nunca duas IAs na mesma pasta ao mesmo tempo). Cada bloco: linha de base congelada antes, mudança só dentro da janela de tempo, conferência pelo Claude Code depois. Atuação do Echo: **copiar** do Manifesto e dos modelos da seção 9, nunca inventar movimento; conferir em imagens antes de qualquer render.
5. **Prévia com voz** (540×960) enviada pelo chat. Parada para o usuário ver. Gerar quadro a quadro pela composição (`window.__seek`), nunca gravando o navegador em tempo real (a gravação perde quadros: sai a 30 fps e mais curta que o áudio).
6. **Legenda** (seção 6). Opções de fonte sobre quadros reais; conferir cobertura cena a cena.
7. **Som** (seção 6). Música escolhida, efeitos nos acontecimentos, ducking sob a voz.
8. **Prévia final** com legenda e som. Parada.
9. **Versão final 1080×1920** (sem renderizar de novo: juntar vídeo aprovado + legenda + áudio).
10. **Capa** (3 opções, texto aprovado).
11. **Agendamento**, depois de o usuário ver o arquivo final e aprovar os textos de cada rede:
    - Instagram: fila local `instagram_posts` (skill `agendar-reel-instagram`); evitar dois Reels no mesmo dia.
    - YouTube Shorts: item na fila `youtube_videos` (repositório `YouTubeVideoRepository::create`), com envio remoto imediato (`remote_upload_due_at = NOW()`) e data programada (`scheduled_at`). O publicador envia o vídeo privado com `publishAt` e capa personalizada (via `setThumbnail`), e a reconciliação atualiza o status para `agendado_remoto` com permalink liberado para conferência no YouTube Studio de imediato. Exemplo: Castlevania (#79), Yu-Gi-Oh (#223), Power Rangers (#224) e Chaos Legion (#225).
12. **Registro:** Forge, README da pasta de trabalho, memória.
13. **Fim do vídeo (pacote e limpeza):** depois de agendado ou publicado, montar o pacote de origem em `conteudo-planejado/videos-echo/<nome>/`, testar e apagar o lixo (seção 11).

---

## 4. Cenários: como os aprovados foram feitos e o nível mínimo de detalhe

### 4.1 Como os aprovados foram feitos (Manifesto, pacote `conteudo-planejado/videos-echo/manifesto-estrategia-nerd/cenas/cenarios_v10/`)

- **Tudo em código (Canvas 2D), sem imagem externa.** Cada cenário é um arquivo `*_desenho.js` (Corrida 488 linhas e 22 funções; Setup 364 e 22; RPG 681 e 36; Praça/início 524 e 28), com **6 a 19 degradês** cada.
- **Camadas de profundidade**, do fundo para a frente, desenhadas em canvases separados: céu → distante → médio → estrutura → chão/plataforma → frente → **luz** → **acabamento**. As camadas separadas permitem mover a câmera com paralaxe.
- **Objetos que contam o assunto**, não fundo vazio. RPG: ruínas, aqueduto, cachoeira, torre com janela acesa, engrenagem de pedra com corrente, alavanca, hera, moitas, runas. Setup: parede roxa com neons, mesa, monitor, gabinete, cadeira 3/4, pufe, prateleira. Corrida: céu com nuvens, serras, pinheiros, estrada com curva, guard-rail, carro vermelho de frente.
- **Textura e material:** tijolos, folhas, madeira, metal, vidro — feitos com padrões e sorteio com **semente fixa** (`rng(SEMENTE)`), para o quadro sair sempre igual.
- **Luz e sombra:** uma fonte de luz principal por cena (sol, janela acesa, neon) e uma camada de luz própria.
- **Perspectiva de verdade quando a cena pede:** a Corrida usa ponto de fuga e distância focal (`VPX`, `VPY`, `F`).
- **Coerência entre cenas:** o monitor do quarto mostra o RPG **pelo mesmo código**, e por isso o zoom quarto ↔ jogo parece o mesmo computador. Para esse zoom, o mundo do jogo tem ~3.413 px de largura e o 9:16 é a faixa central.
- **Lugar reservado para o Echo** em cada cenário (comentado no topo do arquivo, ex.: "Espaço do Echo: sobre a plataforma, x ~250–450").
- **Referências só de clima.** Prancha lado a lado com a referência, medindo brilho e paleta, antes de mostrar. **Nunca recortar a imagem de referência.**

### 4.1.1 Quarto padrão oficial em camadas vivas (`conteudo-planejado/videos-echo/_kit-quarto/producao-camadas-v1/`, aprovado em 06/11/2026)

- **Seis camadas raster independentes em 1080×1920 (`quarto_setup_raster.js`):** `00-ambiente.png` (parede e piso sem luzes estáticas cravadas), `10-quadros.png` (5 quadros de Yu-Gi-Oh com alfa), `20-mesa-equipamentos.png` (mesa, gaveteiro, monitores, gabinete e painel pixelado com visores limpos na base), `30-cadeira.png` e `31-cadeira-frente.png` (cadeira ampliada 1,28× ancorada em `X=10, Y=1900`, com frente sem encosto `Y >= 1590`), `40-pufe.png` (pufe e controle) e `50-acabamento.png` (vinheta periférica).
- **Iluminação viva determinística por `tempo`:** 6 painéis hexagonais LED 3D e 3 barras neon na parede (apagam/acendem com halo real), matriz 6×6 em perspectiva no painel pixelado (equalizador colorido e ícone) e gabinete RGB com 3 ventoinhas frontais, cooler traseiro e 2 pentes de memória RAM percorrendo o ciclo cromático completo.
- **Quatro modos de oclusão (`posicaoEcho`):** `sentado` (padrão inicial: sobre a almofada da cadeira à frente do encosto), `frente` (flutuando no meio do quarto à frente da cadeira e do pufe), `atras-da-cadeira` e `atras-da-mesa`. O monitor central expõe `mainScreen` (`x: 306, y: 778, w: 468, h: 242`) para receber gameplay ou conteúdo sem cobrir a moldura.

### 4.2 Nível mínimo de detalhe (checklist antes de animar)

- [ ] Pelo menos 6 camadas de profundidade, incluindo luz e acabamento.
- [ ] Objetos ligados ao assunto do vídeo, reconhecíveis no celular.
- [ ] Textura em pelo menos dois materiais (não só cores chapadas).
- [ ] Uma fonte de luz definida e sombra coerente; nada "chapado e escuro".
- [ ] Lugar do Echo reservado, com contato com o chão (sombra) ou reflexo; o Echo grande o bastante para ler a expressão (referência: ≥ 45% da largura quando ele é o foco).
- [ ] Chão e fundo não vazios.
- [ ] Prancha com a referência do usuário ao lado, brilho e paleta medidos.
- [ ] Determinístico (semente fixa; o mesmo instante gera o mesmo quadro).

### 4.3 Contraexemplos (reprovados)

| Cenário | Por que foi reprovado | Onde está |
|---|---|---|
| Manifesto v9 (`cenarios_v9`) | arte chapada e escura; stills feitos com **recortes da imagem de referência** (proibido); pernas no Echo | `conteudo-planejado/videos-echo/manifesto-estrategia-nerd/pranchas/reprovadas/` |
| Dia do TI v1/v2 | cabos como linhas retas ("pernas de aranha"); traço de diagrama, sem contorno e sombra de ilustração; chão vazio; Echo pequeno (~1/3 da largura); monitor e arquivos minúsculos | `conteudo-planejado/videos-echo/echo-dia-do-ti/` (README, seção "Contraexemplos") |
| Primeiro final do Manifesto (37–60 s) | 23 s no mesmo quarto, com cards e cápsulas pequenos, "tudo muito igual" | `conteudo-planejado/videos-echo/manifesto-estrategia-nerd/pranchas/reprovadas/prancha_B5.png` |
| Roteiro novo que copiava o começo | o final reaproveitava as cenas do início | `.../manifesto-estrategia-nerd/pranchas/reprovadas/storyboard_43_60.png` |

Cenários aprovados (referência visual): `conteudo-planejado/videos-echo/manifesto-estrategia-nerd/pranchas/aprovadas/` (corrida, RPG, setup e o final no quarto).

---

## 5. Animação: o que se espera

- **Cada frase da voz tem uma ação** que a mostra (não só texto aparecendo).
- **Tempo máximo sem mudança visível durante a fala: ≤ 1,4 s** (medido no Manifesto aprovado: máx 1,1–1,2 s; pausas sem ação no máximo 1,5 s). Movimento de enfeite (brilho, partícula) não conta como ação.
- **Deslocamentos de corpo:** a cada 1,5 a 3,0 s durante a fala (média do Manifesto: intervalo de 2,05 s, distância média de 201,9 px no quadro 1080).
- **Viradas de cabeça, expressão e olhos:** pelo menos 1 a cada 2–3 s (Manifesto: 35 trocas em 60 s, 6 tipos de olhos — `pill`, `wide`, `angry`, `flat`, `happy`, `dot` — e alternância de humor ciano/âmbar).
- **Flutuação (bobbing) e casco:** amplitude suave ±5 px (~3,1 Hz), casco 100% rígido (`sx = 1, sy = 1`). NUNCA esticar nem balançar como gelatina.
- **Relógio do motor vivo:** sempre contínuo com `t` real (`seek(t)`), nunca congelado em valor fixo (o que congela a oscilação interna dos olhos e motor).
- **Trajetos e transições fluidos:** usar pontos de passagem sem amortecimento até parar (`passa: true`) para o robô não pousar a cada pose intermediária.
- **O Echo se move e reage:** sai do lugar, flutua até um objeto, aponta, olha para quem assiste, volta, mexe no mouse — sem teleporte.
- **Atuação e encerramento padrão no quarto (60 fps):** o Echo inicia sentado sobre a almofada da cadeira (`X ≈ 289, Y ≈ 1574, k = 1.50`), segurando o controle 3D no colo com as mãos azuis oficiais; aponta o monitor no gancho e volta ao controle para jogar; no fechamento, pousa o controle 3D sobre o assento da cadeira (`X ≈ 289, Y ≈ 1558`, callback `desenharSobreCadeira`) e sai em voo fluido para o meio do quarto (`posicaoEcho: 'frente'`, `X ≈ 575, Y ≈ 1460, k = 1.85`) acendendo a Propulsão Matrix (`echo-propulsao-matrix.js`) sob a base e abrindo olhos `happy`.
- **A câmera trabalha:** aproximação, recuo, pan, zoom num objeto (ex.: zoom no monitor até a tela ocupar o quadro).
- **Variedade de cena:** o vídeo não pode ficar mais de ~6 s no mesmo enquadramento sem uma mudança de câmera, de ação ou de cena. Mudar de cena no final se o começo varia.
- **Transições que carregam sentido** (aprovadas no Manifesto): T1 folha → quarto → push-in na tela; T2 sol → bola da alavanca; T3 recuo da tela → Echo jogando. Nada de "tela colada".
- **Objetos entram e saem com motivo**; elementos não somem todos de uma vez.
- **Boca:** conferir em quadros que ela abre de forma visível; em planos abertos o "O" da boca é pequeno — usar plano mais fechado quando a fala importa.

---

## 6. Voz, boca, legenda e som (como fazer)

- **Boca:** tabela `[tempo, abertura]` por quadro, calculada pelo volume da voz (RMS por quadro, limiar ~300, escala 2000, abaixo de 0,06 = fechada). Modelo: `conteudo-planejado/videos-echo/manifesto-estrategia-nerd/ferramentas/estender_boca_v12.py`.
- **Legenda:** texto do roteiro com o tempo de cada palavra (Whisper); blocos de **uma linha**; palavra falada destacada. **Fonte padrão da série: Orbitron 700** (arquivo `orbitron-700-latin.woff2`, a mesma do Manifesto e do Castelo Invertido), estilo HUD, tamanho 92 px com redução até 70 px para caber em uma linha. Posição padrão: alto da tela (Y 270), entre X 90 e 925. Posição **conferida com o usuário em cada vídeo**, cena a cena. Conferir em **todos os trechos** que não cobre texto do vídeo nem o rosto do Echo. O ffmpeg desta máquina **não lê woff2**: com fonte woff2 (Orbitron, por exemplo), desenhar a legenda no navegador como PNGs transparentes e sobrepor com ffmpeg (modelo: `conteudo-planejado/videos-echo/manifesto-estrategia-nerd/legenda/`). As **pausas entre blocos** precisam virar um estado transparente na lista do ffconcat; sem isso as legendas seguintes adiantam (modelo corrigido: `conteudo-planejado/videos-echo/castlevania-castelo-invertido/v2/legenda/gerar_estados.py`; a soma das durações tem de ser igual à duração do vídeo).
- **Voz:** edge-tts `pt-BR-AntonioNeural` enquanto o ElevenLabs estiver sem créditos (scripts no pacote do Castlevania, pasta `audio/`).
- **Som:** música da biblioteca do portal (`public/uploads/audio/pixabay_*.mp3`), escolhida pelo usuário entre 3 trechos; efeitos sintetizados com `onetake/scripts/sfx_palette.py` (air, glass, wood, sub, bubble) + foley real quando houver (`assets/audio/foley_glass_*.wav`); cada efeito confirmado no quadro do vídeo; música com ducking (sidechain pela voz), ≥ 12 dB abaixo da voz durante a fala; final em -16 LUFS, pico -1,5 dBTP. Modelo: `conteudo-planejado/videos-echo/manifesto-estrategia-nerd/audio/`.
- **Biblioteca de sons ElevenLabs (19/11/2026):** efeitos criados pela API ficam em `conteudo-planejado/videos-echo/_kit-sons/` (catálogo com descrição, duração e vídeos que usaram cada som). Antes de gerar, `python -I gerar_sons.py --listar` e reaproveitar o que já existe; gerar com 2 opções A/B para o usuário ouvir e escolher; o gerador confere os créditos e a chave vem só da variável `ELEVENLABS_API_KEY`.

---

## 7. Conferências técnicas (todo bloco)

| Conferência | Ferramenta (pacote do Manifesto, `conteudo-planejado/videos-echo/manifesto-estrategia-nerd/ferramentas/`) |
|---|---|
| Congelar a linha de base e comparar fora da janela | `verificar_bloco.py congelar` / `comparar --janela A B` |
| Diff por função (só o que podia mudar) | `verificar_bloco.py funcoes` |
| Trecho antigo idêntico à versão anterior | `verificar_bloco.py contra-v11` |
| Texto na área segura | `medir_texto_pagina.py` (acumula camadas antigas em séries longas: confirmar no quadro) |
| MP4 íntegro, duração, quadros, recortes | `verificar_crop.py integridade` / `quadros --prancha` |
| Render em segmentos (< 150 s por comando) | `render_v12_janela.py`; para composição com `window.__seek` e `?offset=&dur=`: `castlevania-castelo-invertido/v2/render_janela_v2.py` (pedaços de 1 s) |
| Segmentos iguais à página única | `verificar_offsets.py` |
| Volume | `ffmpeg -af ebur128=peak=true` |

O Claude Code confere **por conta própria** cada entrega do Antigravity; relatório de IA é entrada, não prova.

---

## 8. Erros já cometidos (e como evitar)

### 8.1 Direção e roteiro

| Erro | O que aconteceu | Causa | Como evitar |
|---|---|---|---|
| Final todo na mesma cena | 37–60 s do Manifesto ficaram no quarto; o usuário: "fez todo o resto na mesma cena", "tudo muito igual" | instrução repassada ("continuar do último quadro") virou "tudo no quarto" sem avisar o efeito | mostrar no roteiro quantos segundos ficam em cada cena; seguir a seção 5 |
| Cópia do começo no final | ao "variar", o roteiro novo reaproveitou as cenas do início | confundir variedade com reaproveitamento | cenas novas para trechos novos; reaproveitar só personagem e estilo |
| Boca parada | de 42 a 60 s a boca do Echo ficou fechada | a tabela da boca terminava em 42,18 s e nenhum teste conferia a boca | estender a boca até o fim da fala; conferir boca em quadros |
| Roteiro antigo diferente da voz | o script dizia "Siga o portal"; a voz dizia "link na bio" | confiar no roteiro sem ouvir | transcrever a voz antes; vale o que se ouve |
| Texto e plano longos | o usuário respondeu "não entendi nada" | plano técnico demais, muitas opções | 3–6 linhas, uma pergunta por vez |
| Escolha de um vídeo virou regra | fonte da legenda, posição e música do Manifesto quase viraram padrão da série | não separar padrão de escolha | seções 1 e 2 deste documento |
| Plano de animação aprovado só pelo texto | Castlevania: o plano dizia "respiração ampla de 7 px, balanço corporal" e foi aprovado antes de existir imagem; saiu a "gelatina" | movimento não se julga por descrição | para animação, aprovar por imagem ou clipe, nunca só pelo texto do plano |
| Instrução ambígua virou regra literal | "o Echo parado no quarto" (ficar no quarto) foi lido como "imóvel" | não confirmar o sentido | repetir a instrução com as próprias palavras antes de executar |
| Publicado sem o usuário ver | Manifesto no YouTube (01/11/2026): o corte foi conferido só por transcrição e quadros, e terminou seco, sem final | o plano dizia "sobe já público" | parada para o usuário ver o arquivo antes de qualquer publicação (seção 1) |
| Referência de animação esquecida | Animar confiando só no código ou em descrições sem ver o vídeo real aprovado | Achar que copiar código sem olhar o vídeo basta | Antes de animar, abrir o vídeo de referência e comparar as folhas de quadros (3 fps) lado a lado com a montagem |

### 8.2 Cenário e animação

| Erro | O que aconteceu | Como evitar |
|---|---|---|
| Cenário pobre | Dia do TI: cabos "pernas de aranha", traço de diagrama, chão vazio, Echo pequeno | checklist 4.2 antes de animar; prancha com a referência |
| Arte chapada e escura / recorte da referência | Manifesto v9 | camadas + luz + acabamento; referência só de clima |
| Cards somem de uma vez | aos 43,5 s os 4 cards do método desaparecem num corte | saída com motivo e tempo (deslizar, esmaecer) |
| Mão longe do objeto | a mão do Echo vai até o mouse no outro lado da mesa | posicionar o Echo perto do que ele manipula |
| Cartões e cápsulas pequenos | texto de monitor e cápsulas ilegíveis no celular | letra ≥ 40 px no quadro 1080 depois do zoom; aproximar a câmera |
| Cadeira cortada / controle "pousado" que não pousou | relatório dizia "pousado na mesa", o quadro mostrava na mão | conferir no quadro do MP4, não no relatório |
| "Gelatina" | Castlevania v2: flutuar ±13 px, esticar 5% e balançar olhar, mãos e controle em senoide o tempo todo; o usuário: "parece um slime e não um robô" | casco sem esticar nem tremer; a atuação vem de deslocamento, cabeça e rosto, olhos, mãos, símbolos, câmera e ações ligadas à fala; o movimento vem de mudar de lugar (voar, sair da cadeira), não de tremer |
| Relógio do motor fixo | Montagem inicial: `director.seek(1.2)` cravado por quadro congelou a oscilação dos olhos e do motor interno | Achar que `seek` estático estabiliza o robô | Sempre passar o tempo real contínuo `director.seek(t)` pelo módulo `EchoPadrao` |
| Brilho azul desenhado no chão | Brilho circular azul desenhado na base atravessava móveis e poluía o chão | `drawGlow` ligado por padrão no motor antigo | Desligar brilho no chão com função vazia (`drawGlow = () => {}`) |
| Propulsão atravessando a mesa | Efeito de matriz/propulsão desenhado abaixo do Echo quando ele está sentado ou atrás da mesa cortava os móveis | Não respeitar a oclusão das camadas do quarto | Ligar propulsão apenas em voo livre no meio do quarto (`posicaoEcho: 'frente'`), longe da mesa e da cadeira |
| Echo na frente do monitor | O robô flutuando cobria o monitor enquanto a locução falava da tela do setup | Posicionamento sem considerar o foco visual da cena | Posicionar o Echo à esquerda (`X ≈ 280–350`) ou recolhido na cadeira quando o monitor for o foco principal |
| Tamanho invertido | Echo menor em primeiro plano e maior ao fundo | Não conferir proporções relativas na perspectiva da cena | Conferir escala `k` em cada ponto: maior na frente (`k ≈ 1.85`), padrão na cadeira (`k ≈ 1.50`) |
| Echo pousando a cada pose | Paradas bruscas e desaceleração zero a cada quadro-chave intermediário | Curvas de interpolação Hermite sem tangente contínua nos pontos de passagem | Marcar pontos intermediários de trajetória com `passa: true` para manter velocidade contínua |
| Coordenadas copiadas sem conferir o quarto | as posições do Manifesto num quarto com o Echo maior puseram o controle "na mesa" ao lado do rosto | conferir cada coordenada copiada em imagem, no quarto do vídeo novo |
| Controle ao contrário | o controle do Manifesto é desenhado como o jogador o vê; de frente para a câmera, o Echo "jogava ao contrário" | desenhar o controle como a câmera vê (modelo: controle 3D do Castlevania, parte de cima achatada) |
| Tamanho mudando sem motivo | o Echo encolheu flutuando no mesmo quarto | mudar o tamanho só para dar profundidade (vindo para a frente cresce e desce) |
| Controle solto no ar | com o Echo fora da cadeira, o controle ficou parado na altura do colo | o controle sempre apoiado (colo, assento da cadeira ou mesa) ou na mão |
| Luzes estáticas cravadas na camada base | Quarto v1 inicial: `00-ambiente.png` e `20-mesa-equipamentos.png` mantinham os hexágonos, barras neon, tela pixel e anéis RGB acesos na imagem base, impedindo apagamento real e troca limpa de cor | limpar com patch suave (Coons/base escura) qualquer elemento luminoso na camada estática antes de desenhar a emissão dinâmica por `tempo` |
| Camada frontal da cadeira cobrindo o Echo sentado | Quarto v1 inicial: `31-cadeira-frente.png` incluía o encosto e o apoio de cabeça, deixando o Echo oculto atrás da cadeira mesmo no modo `sentado` | restringir `31-cadeira-frente.png` apenas à saia/base inferior do assento (`Y >= 1590`), mantendo o encosto exclusivamente em `30-cadeira.png` |

### 8.3 Legenda

| Erro | O que aconteceu | Como evitar |
|---|---|---|
| Pequena e fina | ~35 px no quadro 1080, contorno fraco | ≥ 100 px, contorno grosso, uma linha |
| Duas linhas sobre o Echo | blocos longos quebravam e cobriam o peito do Echo | blocos curtos (≤ 14 letras na fonte usada) |
| Cobriu texto do vídeo | embaixo cobriu os cards 37–43 s; em cima cobriu o cartão do portal 13–20 s | conferir cobertura em **todos** os trechos; mudar a posição só onde há texto |
| "Simples demais" / fonte que não combina | usuário pediu fonte tech/pixel | mostrar opções sobre quadros reais |
| Dia do TI | texto cortado fora da tela, caixa desalinhada, cara de campo de formulário, uma cor por cena | medir a caixa real; sem caixa de formulário; cores constantes |
| Legenda adiantando depois das pausas | o ffconcat encadeia os estados sem as pausas entre blocos (no Castlevania, ~1 s de atraso acumulado) | estado transparente nas pausas; soma das durações = duração do vídeo |

### 8.4 Execução com o Antigravity e ferramentas

| Erro | O que aconteceu | Como evitar |
|---|---|---|
| Teste travado | script abria HTML fora de `src/`, sem prazo: 50 min parado | todo script com prazo; HTML só de dentro da pasta do projeto; `espera.py` acorda em 15 min sem passo |
| Processos órfãos | dezenas de `find_diff.py` e navegadores abertos por horas | fechar navegador em `finally`; conferir processos ao fim do bloco |
| Investigação sem fim | 370+ passos atrás de uma diferença de hash | no máximo 3 comandos de investigação; depois parar e relatar |
| Afirmar o que não fez | relatórios dizendo "corrigido"/"sem cortes" que os quadros desmentiam | conferência independente pelo Claude Code |
| Acentos corrompidos no Forge | título da tarefa 1534 gravado com U+FFFD | `curl.exe` com corpo JSON em arquivo UTF-8 |
| Medidor com falso positivo | `medir_texto_pagina.py` acusou 6.507 "fora" numa série de 60 s | séries curtas e confirmação no quadro |
| Arquivo errado | um script gravou a prancha na pasta do bloco anterior; outro leu o vídeo antigo | conferir caminho de entrada e saída antes de rodar |
| Lixo acumulado | renders com `--keep-frames` deixaram ~6 GB de quadros PNG; pedaços de render e perfis de navegador ficaram para trás (Manifesto + Dia do TI: 6,3 GB em 33 pastas) | renderizar sem `--keep-frames`; pacote e limpeza no fim do vídeo (seção 11) |
| Duas IAs na mesma pasta | Castlevania (31/10/2026): Codex e Antigravity reescreveram o mesmo `cenas_v2.js`, o gameplay e a mixagem, sem passar o bastão; um não sabia da prévia do outro | uma IA por vez na pasta; o estado e as decisões ficam num arquivo do projeto (roteiro/README), não no chat |
| Conferência de fachada | os quadros de 10, 18, 24 e 29 s apresentados como prova eram o mesmo arquivo (hash idêntico); um relatório disse "60 fps" num vídeo de 30 fps | conferir hashes diferentes entre si e medir com ffprobe; relatório não é prova |
| Documento fora do projeto | a estratégia de redes ficou só na pasta interna do Antigravity e as outras IAs trabalharam sem ela | documentos de decisão sempre em `docs/governanca/` (hoje: `diretrizes-redes-sociais.md`) |

### 8.5 Técnicos

| Problema | Solução |
|---|---|
| Sem `?offset=` a composição começa em 10 s | sempre passar `?offset=0` (ou o início do segmento) |
| `render.py` apaga a pasta `_frames` ao lado da saída | renderizar numa subpasta própria |
| Comando de render > 150 s | renderizar em segmentos de 5 s e juntar; se a composição busca um vídeo por quadro (gameplay no monitor), pedaços de 1 s: com 3 s um navegador emperrou no fim do pedaço (Castlevania, 01/11/2026) |
| Prévia gravada em tempo real | `captureStream` + `MediaRecorder` perdeu quadros (30 fps, vídeo 0,37 s mais curto que o áudio) | prévia quadro a quadro pela composição (`window.__seek`) |
| `render_v12_janela.py` sem achar o OneTake | apontava para `Projects\onetake`, que mudou para `Projects\media\onetake` | corrigido em 01/11/2026 |
| ffmpeg não lê woff2 | legenda desenhada no navegador (PNG) |
| Fonte pixel local incompleta | a Press Start 2P do gradio tem 2 KB, sem letras; fonte pixel exige download aprovado |
| Arquivo > 30 MB não chega ao celular pelo chat | mandar a prévia 540×960 |
| Endereço público com `/public` cai no login do Cloudflare | usar `https://nerd.tfr-info.com.br/uploads/...` |
| Primeiro quadro de página nova difere poucos pixels | aceitável (MAE ≤ 0,003); render em segmentos é seguro |

### 8.6 Prevenção de Erros Gerais (Diretrizes Operacionais de Produção)

Catálogo obrigatório consolidado a partir da produção do vídeo *Montando meu setup* e da esteira de agendamento (15/11/2026):

| Classe do Erro | O que causa / Sintoma | Como fazer corretamente (Regra Obrigatória) |
|---|---|---|
| **Colisão e oclusão de objetos** | Echo atravessando telas, monitores ou superfícies sólidas como fantasma ao descer ou se movimentar. | Respeitar as camadas visuais reais. Se o Echo precisa passar para trás de um objeto em primeiro plano, ele deve subir pelo plano frontal até ultrapassar o topo da moldura do objeto (ex.: `Y < topo`), alternar a camada de renderização para trás (`atras-monitor`) e só então descer suavemente. |
| **Física e animação de peças ("passe de mágica")** | Cabos, ferramentas e componentes surgindo repentinamente estáticos na bancada ou no ar. | Peças e objetos móveis nunca entram por corte seco no ar. Devem ter trajetória física: gravidade, aceleração, amortecimento elástico (bounce) no contato com a mesa/chão e sincronia motora com as mãos ou olhar do Echo. |
| **Sincronia e travamento de boca / personagem** | Echo falando com a boca imóvel, mexendo os lábios no silêncio ou corpo congelado por vários segundos. | A boca deve ser dirigida por RMS quadro a quadro a partir do áudio final oficial. Ao substituir qualquer take de locução, é obrigatório reprocessar o Whisper (`word_timestamps`) e a tabela de boca RMS. O Echo nunca fica imóvel por > 1,2s durante a fala (atuação viva do Manifesto). |
| **Repetição e redundância de texto no roteiro** | Repetição desatenta do mesmo verbo ou comando em frases consecutivas (ex.: "Vamos começar..." e logo em seguida "Vou começar pelos monitores..."). | Auditar rigorosamente o roteiro antes de enviar para a locução. Cada frase deve introduzir um termo novo; eliminar vícios de linguagem e redundâncias de verbos de abertura. |
| **Corte e duração de áudio fora do molde** | Vídeo renderizado com duração quebrada que não fecha com a minutagem oficial do molde (ex.: 65,00 s exatos). | Toda locução e mixagem final (áudio master) deve ser cravada na duração matemática exata do molde (ex.: 65,000000 s a -16 LUFS e -1,5 dBTP), com fade-out musical controlado no último segundo e sem cortes secos na voz. |
| **Sincronia e formatação de legenda HUD** | Palavras ativas no HUD descompassadas com a voz ou texto quebrando em 3 linhas. | Aplicar o padrão oficial Orbitron 700, palavra ativa em ciano, moldura HUD tecnológica e blocos curtos (máximo 1 linha com até 14 letras grandes). Timestamps do Whisper conferidos de ouvido com os picos do áudio. |
| **Omissão de envio de capa na API externa** | Upload do vídeo MP4 concluído, mas o YouTube fica exibindo um quadro escuro aleatório do início. | O agendamento só é considerado completo após o disparo explícito da API de miniaturas (`setThumbnail` / `thumbnails->set`) enviando o arquivo `capa.jpg` oficial. |
| **Imagem quebrada por dependência de link externo** | Painel exibindo ícone de imagem quebrada por tentar carregar URLs assinadas do YouTube que retornam 404. | O painel e as views administrativas devem **sempre priorizar o arquivo local salvo no servidor (`thumbnail_path`)** e possuir atributo de fallback `onerror` na tag `<img>` para nunca deixar a tela quebrada. |
| **Proporção e distorção de capa** | Capa vertical sendo exibida com bordas borradas/esticadas (16:9) ou elementos essenciais cortados no feed. | Toda capa de Reels e Shorts deve ser gerada na proporção nativa vertical 9:16 (1080×1920) e conferida contra as duas safe zones: 4:5 central (Feed do Instagram, Y=240 a 1680) e 1:1 quadrado (Grade do perfil, Y=420 a 1500). |
| **Omissão de etapas de aprovação (Decisão unilateral)** | Gerar opções de capas ou soluções visuais e aplicar uma delas no post sem mostrar ao usuário. | Toda variação visual (ex.: as 3 opções de capa) deve ser apresentada em carrossel ou mosaico diretamente no chat, aguardando a escolha explícita do usuário antes de publicar ou associar ao post final. |
| **Quebra de cronologia e dependências narrativas** | Agendar vídeos fora da ordem lógica dos acontecimentos do universo do Echo (ex.: agendar estreia na parede de coleções antes da montagem do quarto). | Mapear o estado do cenário do Echo antes de agendar qualquer vídeo. Vídeos que dependem de modificações no quarto (ex.: Mark 39 na parede de nichos) devem obrigatoriamente ser agendados para datas posteriores ao vídeo que introduz a mudança (Setup). |
| **Não disparo de upload automático na fila** | Inserir agendamento no banco com `remote_upload_due_at = NULL`, fazendo o despachante ignorar o vídeo. | Ao registrar agendamentos imediatos no banco, o campo `remote_upload_due_at` deve ser preenchido obrigatoriamente com `NOW()` para que a rotina operacional (`en-youtube-publish-scheduled.php`) capture e suba o vídeo de imediato. |
| **Execução e sintaxe de comandos sem validação** | Erros repetidos de parser no PowerShell, aspas quebradas ou chutes de nomes de colunas no banco. | Inspecionar previamente o schema (`SHOW COLUMNS` / PRAGMA) antes de rodar queries; para scripts de manipulação de dados complexos no Windows, escrever arquivos `.php` dedicados em `scratch/` e executá-los de forma limpa. |

---

## 9. Onde estão os modelos

- Manifesto (referência completa): pacote `conteudo-planejado/videos-echo/manifesto-estrategia-nerd/` (README, cenas, áudio, roteiro, legenda, ferramentas, pranchas). A pasta de trabalho `scratch/manifesto-10-10/` é temporária e sai depois do post publicado.
- Dia do TI: pacote `conteudo-planejado/videos-echo/echo-dia-do-ti/` (v3 = versão com transições).
- Motor do Echo: `conteudo-planejado/videos-echo/manifesto-estrategia-nerd/cenas/src/vendor/robo-motor.js`; fantasias: `conteudo-planejado/videos-echo/echo-dia-do-ti/v3/vendor/robo-roupas-echo.js` (lista de figurinos em `C:\Users\WINDOWS\Projects\output\echo-galeria-descartada\galeria-roupas.js`).
- Castlevania (híbrido Reels + Shorts, gameplay no monitor): pacote `conteudo-planejado/videos-echo/castlevania-castelo-invertido/` (README). Reaproveitáveis em `v2/vendor/`: `echo-atuacao-sala.js` (atuação do Manifesto parametrizada pelas marcas da locução: gancho, jogo, pouso do controle, saída para a frente) e `echo-propulsao-matrix.js` (Propulsão Matrix do site, calculada pelo tempo); `setup_desenho.js` com monitor 4:3; `v2/render_janela_v2.py` (render com `?offset=&dur=`); `v2/legenda/` (legenda do Manifesto com pausas corrigidas).
- Quarto como cópia do setup do usuário (padrão oficial aprovado em 06/11/2026 para todos os vídeos): `conteudo-planejado/videos-echo/_kit-quarto/` (`quarto_setup_raster.js`, `producao-camadas-v1/`, `pecas.json`, README). Renderiza o quarto vivo em camadas 1080×1920 com os 5 quadros de Yu-Gi-Oh, 6 painéis hexagonais LED 3D, 3 barras neon, painel pixelado 6×6 em perspectiva, gabinete RGB com ventoinhas e RAM animadas, cadeira ampliada 1,28× e Echo sentado na almofada da cadeira (`k = 1.50`), pousando o controle 3D no assento e saindo em voo fluido para o meio do quarto (`k = 1.85`) com Propulsão Matrix no encerramento. O `quarto_setup.js` procedural permanece apenas como legado dos vídeos anteriores a 06/11/2026.
- Kit de Atuação Padrão do Echo: `conteudo-planejado/videos-echo/_kit-echo/` (`echo-padrao.js`, `conferir-atuacao.cjs`, `referencia-manifesto.json`, `README.md`). Módulo oficial de atuação e conferência automática que audita e impede Echo parado durante fala, relógio congelado, gelatina e brilho indevido.
- Agendamento: skill `agendar-reel-instagram`, fila do YouTube (seção 3, passo 11) e Central Técnica → Procedimentos.
- Estratégia e regras das duas redes: `docs/governanca/diretrizes-redes-sociais.md`.

## 10. Manutenção

Cada vídeo novo da série acrescenta suas escolhas na seção 2 (como exemplo) e seus erros na seção 8. Regra nova vira item da seção 1 **só com a aprovação do usuário**.

---

## 11. Fim do vídeo: o que guardar e o que apagar (lixo)

Vale quando o vídeo está **aprovado e agendado ou publicado**. Pedido do usuário em 20/10/2026: o que é pertinente fica **na pasta de cada post**; o resto é lixo e sai.

### 11.1 Onde fica cada coisa

| Pasta | O que guarda | Git | Backup | Público |
|---|---|---|---|---|
| `conteudo-planejado/videos-echo/<nome>/` | **pacote de origem** (o que guardar, abaixo) + `README.md` | sim | sim (git/GitHub) | não |
| `public/uploads/reels/<nome>/` | o publicado: `reel.mp4` e `capa.jpg` | não (ignorado) | sim (uploads) | sim, pelo endereço exato: a Meta baixa o vídeo por URL (`video_url`/`cover_url`) |
| pasta de trabalho (`scratch/...`, `output/...`) | tudo durante a produção | não | **não** (`scratch/` fica fora do backup) | não |

Arquivos de trabalho nunca vão para `public/uploads/` (ficariam baixáveis por quem tivesse o endereço). O que está em `public/uploads/reels/` **nunca se apaga**: o publicador e o site usam.

### 11.2 Guardar (pacote de origem, dezenas de MB)

- Composição final (HTML/JS), motor do Echo e fantasias usados, desenhos dos cenários — com as pastas na **mesma profundidade** da original, para abrir sem mudar caminhos.
- Fontes e imagens que a composição carrega (símbolo da marca etc.).
- Voz original (custa crédito para regerar), mixagem final, script e lista dos efeitos; a música da biblioteca do portal fica onde está.
- Tempo de cada palavra, texto da legenda, tabela da boca.
- Scripts de legenda, render, som e conferência.
- Pranchas aprovadas e reprovadas citadas no molde; cópia da capa.
- `README.md`: onde está o publicado, post e horário, escolhas aprovadas, como refazer, o que foi apagado.
- **Não** copiar o vídeo final para o pacote: ele já está igual em `public/uploads/reels/<nome>/`.

### 11.3 Apagar (lixo)

- Quadros PNG dos renders (`_frames`) e pedaços de render (`seg_*`).
- Prévias reprovadas ou superadas, quadros soltos de conferência.
- Perfis de navegador (`perfil_chrome`) e arquivos temporários da sessão.
- Por último, a pasta de trabalho inteira, **depois** do post publicado e com confirmação do usuário.

### 11.4 Como fazer (ordem obrigatória)

1. Montar o pacote e conferir o hash de cada cópia.
2. **Testar o pacote:** abrir a composição de dentro dele e comparar instantes com a original (hash idêntico, 0 erros, 0 arquivos faltando).
3. Listar o lixo com os tamanhos e conferir que os vídeos finais existem e decodificam sem erro (`ffmpeg -v error ... -f null -`).
4. Apagar só as pastas listadas; medir o espaço liberado.
5. Commitar o pacote (pasta `conteudo-planejado/` é versionada; `scratch/` está no `.gitignore`).
6. Operação destrutiva: entra no plano com o aviso "ATENÇÃO: este plano contém operação potencialmente destrutiva" e só roda depois do "Aprovado".

**Prevenção:** renderizar **sem `--keep-frames`**, a não ser que os quadros sejam necessários; scripts de teste fecham o navegador e não deixam processos abertos.

**Exemplos:** Manifesto e Dia do TI (20/10/2026): pacotes de 31,3 MB e 6,6 MB; lixo apagado: 6,30 GB em 33 pastas.
