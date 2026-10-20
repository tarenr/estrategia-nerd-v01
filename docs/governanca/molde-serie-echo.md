# Molde da série de vídeos do Echo

> **Para quem:** qualquer pessoa ou IA (Claude Code, Antigravity, Codex, Gemini) que vá produzir um vídeo do Echo para o @estrategia_nerd.
> **Criado em:** 20/10/2026 (Forge #1560), a partir do Manifesto Estratégia Nerd (v12, aprovado) e do Reel do Dia do Profissional de TI.
> **Leia junto:** `docs/governanca/guia-direcao-audiovisual-ia.md` (princípios de direção) e a skill `video-echo-serie`.

Este documento separa três coisas que **não podem ser confundidas**:

1. o que é **igual em todo vídeo** da série (seção 1);
2. o que **se decide de novo em cada vídeo**, com o usuário (seção 2) — as escolhas do Manifesto aparecem só como exemplo;
3. os **erros já cometidos** e como evitá-los (seção 8).

---

## 1. O que é igual em todo vídeo da série

| Item | Regra |
|---|---|
| Apresentador | O Echo, desenhado pelo motor oficial (`robo-motor.js`): **sem braços e sem pernas**, flutua, mãos flutuantes (#7fe3f5 / #00d4ff). Fantasias vêm de `robo-roupas-echo.js` (cópia usada no Dia do TI: `conteudo-planejado/videos-echo/echo-dia-do-ti/v3/vendor/`). |
| Fala | O Echo **mexe a boca durante toda a fala**, pela voz real (curva de abertura quadro a quadro pelo volume). Boca fechada nas pausas e no silêncio final. |
| Formato | Vertical 9:16, 1080×1920. Prévias em 540×960. |
| Área segura de TEXTO (Reels) | Nada acima de Y=170 nem abaixo de Y=1540; X ≥ 72 e ≤ 1008; entre Y 970 e 1790 nada além de X=925. **Medir, não estimar.** Imagem e fundo podem passar por baixo da interface. |
| Ordem de trabalho | Roteiro em texto → aprovação → desenho das cenas (prancha com quadros reais) → aprovação → animação em blocos → prévia com voz → aprovação → legenda e som → prévia → versão final → capa → agendamento. Cada seta com aprovação é uma parada (seção 3). |
| Aprovação | Só vale **"Aprovado"** dito pelo usuário depois do plano. "ok", "sim", "pode", "pode fazer", "manda" **não** aprovam. Mudou o escopo: parar e pedir de novo. |
| Conferências técnicas | Em todo bloco: o que não podia mudar continua idêntico (hash), 0 erros de página, texto dentro da área segura, quadros do MP4 olhados de verdade, emendas sem salto, volume final -16 LUFS e pico -1,5 dBTP (seção 7). |
| Comunicação com o usuário | Português simples e curto. Uma decisão por vez. Sempre dizer o que foi conferido e o que não foi. |

---

## 2. O que se decide em cada vídeo (perguntar ao usuário)

Nada desta tabela é padrão. A coluna "Manifesto" mostra **o que foi escolhido naquele vídeo**, para servir de referência, não de regra.

| Decisão | Como decidir | Manifesto (exemplo) | Dia do TI (exemplo) |
|---|---|---|---|
| Duração e ritmo | pelo roteiro e pela voz | 60 s, 60 fps | ~17 s |
| Roteiro e CTA | texto aprovado; vale o que a voz **diz** | CTA "Acesse o link na bio e siga o perfil" | "tentou reiniciar?" |
| Voz | gravar no ElevenLabs; o usuário pode gravar ele mesmo | Cornelius (voz-guia) | Cornelius, gravada pelo usuário |
| Cenários | roteiro visual com quadros reais (seção 4) | praça do feed, corrida, RPG, quarto gamer | sala de servidor |
| Figurino do Echo | combinar com o tema | padrão | "Cara de TI" com camisa do Estratégia Nerd |
| Fonte e estilo da legenda | mostrar 2–3 opções sobre quadros reais | Orbitron + moldura HUD | Orbitron, sem caixa |
| Posição da legenda | onde não cobre texto do vídeo, conferido cena a cena | em cima; desce onde há cartão | embaixo, sempre na mesma altura |
| Música | 3 trechos de 15 s da biblioteca para o usuário escolher | `pixabay_561438.mp3` (~122 BPM) | própria |
| Efeitos sonoros | o que acontece na tela pede som; o usuário pode pedir sons específicos | vidro real, carro, engrenagem, cliques | rack, apagão |
| Capa | 3 opções com quadros reais + texto aprovado | Echo no quarto + "NADA DE ACHISMO. SÓ ESTRATÉGIA." | quadro de 12,6 s |
| Horário do post | ver a fila; slot ocupado = outro horário | 21/10 16:00 (12:00 e 19:30 ocupados) | 19/10 23:20 |

---

## 3. Ordem de produção (com as paradas)

1. **Roteiro em texto.** Analisar o que a voz diz e escrever cena por cena: tempo, frase, cenário, o que o Echo faz, texto na tela. Entregar o texto e **parar**.
2. **Voz.** Uma take contínua (não picotada por frase). Transcrever com Whisper (`base`, já instalado) para ter o **tempo de cada palavra**; corrigir a grafia pelo roteiro (o Whisper erra nomes: "Estrateja", "axismo").
3. **Desenho das cenas (prancha).** Quadros reais dos cenários (existentes ou novos) com a frase e a ação de cada trecho. Mostrar ao usuário **antes de animar**. Cenas novas não podem ser cópia do começo do vídeo.
4. **Animação em blocos.** Um bloco por conversa nova do Antigravity, em sequência (nunca dois escrevendo no mesmo arquivo). Cada bloco: linha de base congelada antes, mudança só dentro da janela de tempo, conferência pelo Claude Code depois.
5. **Prévia com voz** (540×960) enviada pelo chat. Parada para o usuário ver.
6. **Legenda** (seção 6). Opções de fonte sobre quadros reais; conferir cobertura cena a cena.
7. **Som** (seção 6). Música escolhida, efeitos nos acontecimentos, ducking sob a voz.
8. **Prévia final** com legenda e som. Parada.
9. **Versão final 1080×1920** (sem renderizar de novo: juntar vídeo aprovado + legenda + áudio).
10. **Capa** (3 opções, texto aprovado).
11. **Agendamento** na fila do Instagram (skill `agendar-reel-instagram`).
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
- **O Echo se move e reage:** sai do lugar, flutua até um objeto, aponta, olha para quem assiste, volta, mexe no mouse — sem teleporte.
- **A câmera trabalha:** aproximação, recuo, pan, zoom num objeto (ex.: zoom no monitor até a tela ocupar o quadro).
- **Variedade de cena:** o vídeo não pode ficar mais de ~6 s no mesmo enquadramento sem uma mudança de câmera, de ação ou de cena. Mudar de cena no final se o começo varia.
- **Transições que carregam sentido** (aprovadas no Manifesto): T1 folha → quarto → push-in na tela; T2 sol → bola da alavanca; T3 recuo da tela → Echo jogando. Nada de "tela colada".
- **Nada parado mais de ~1,4 s**; movimento de enfeite (brilho, partícula) não conta como ação.
- **Objetos entram e saem com motivo**; elementos não somem todos de uma vez.
- **Boca:** conferir em quadros que ela abre de forma visível; em planos abertos o "O" da boca é pequeno — usar plano mais fechado quando a fala importa.

---

## 6. Voz, boca, legenda e som (como fazer)

- **Boca:** tabela `[tempo, abertura]` por quadro, calculada pelo volume da voz (RMS por quadro, limiar ~300, escala 2000, abaixo de 0,06 = fechada). Modelo: `conteudo-planejado/videos-echo/manifesto-estrategia-nerd/ferramentas/estender_boca_v12.py`.
- **Legenda:** texto do roteiro com o tempo de cada palavra (Whisper); blocos de **uma linha**; palavra falada destacada. Escolher fonte e posição **com o usuário em cada vídeo**. Conferir em **todos os trechos** que não cobre texto do vídeo nem o rosto do Echo. O ffmpeg desta máquina **não lê woff2**: com fonte woff2 (Orbitron, por exemplo), desenhar a legenda no navegador como PNGs transparentes e sobrepor com ffmpeg (modelo: `conteudo-planejado/videos-echo/manifesto-estrategia-nerd/legenda/`).
- **Som:** música da biblioteca do portal (`public/uploads/audio/pixabay_*.mp3`), escolhida pelo usuário entre 3 trechos; efeitos sintetizados com `onetake/scripts/sfx_palette.py` (air, glass, wood, sub, bubble) + foley real quando houver (`assets/audio/foley_glass_*.wav`); cada efeito confirmado no quadro do vídeo; música com ducking (sidechain pela voz), ≥ 12 dB abaixo da voz durante a fala; final em -16 LUFS, pico -1,5 dBTP. Modelo: `conteudo-planejado/videos-echo/manifesto-estrategia-nerd/audio/`.

---

## 7. Conferências técnicas (todo bloco)

| Conferência | Ferramenta (pacote do Manifesto, `conteudo-planejado/videos-echo/manifesto-estrategia-nerd/ferramentas/`) |
|---|---|
| Congelar a linha de base e comparar fora da janela | `verificar_bloco.py congelar` / `comparar --janela A B` |
| Diff por função (só o que podia mudar) | `verificar_bloco.py funcoes` |
| Trecho antigo idêntico à versão anterior | `verificar_bloco.py contra-v11` |
| Texto na área segura | `medir_texto_pagina.py` (acumula camadas antigas em séries longas: confirmar no quadro) |
| MP4 íntegro, duração, quadros, recortes | `verificar_crop.py integridade` / `quadros --prancha` |
| Render em segmentos (< 150 s por comando) | `render_v12_janela.py` |
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

### 8.2 Cenário e animação

| Erro | O que aconteceu | Como evitar |
|---|---|---|
| Cenário pobre | Dia do TI: cabos "pernas de aranha", traço de diagrama, chão vazio, Echo pequeno | checklist 4.2 antes de animar; prancha com a referência |
| Arte chapada e escura / recorte da referência | Manifesto v9 | camadas + luz + acabamento; referência só de clima |
| Cards somem de uma vez | aos 43,5 s os 4 cards do método desaparecem num corte | saída com motivo e tempo (deslizar, esmaecer) |
| Mão longe do objeto | a mão do Echo vai até o mouse no outro lado da mesa | posicionar o Echo perto do que ele manipula |
| Cartões e cápsulas pequenos | texto de monitor e cápsulas ilegíveis no celular | letra ≥ 40 px no quadro 1080 depois do zoom; aproximar a câmera |
| Cadeira cortada / controle "pousado" que não pousou | relatório dizia "pousado na mesa", o quadro mostrava na mão | conferir no quadro do MP4, não no relatório |

### 8.3 Legenda

| Erro | O que aconteceu | Como evitar |
|---|---|---|
| Pequena e fina | ~35 px no quadro 1080, contorno fraco | ≥ 100 px, contorno grosso, uma linha |
| Duas linhas sobre o Echo | blocos longos quebravam e cobriam o peito do Echo | blocos curtos (≤ 14 letras na fonte usada) |
| Cobriu texto do vídeo | embaixo cobriu os cards 37–43 s; em cima cobriu o cartão do portal 13–20 s | conferir cobertura em **todos** os trechos; mudar a posição só onde há texto |
| "Simples demais" / fonte que não combina | usuário pediu fonte tech/pixel | mostrar opções sobre quadros reais |
| Dia do TI | texto cortado fora da tela, caixa desalinhada, cara de campo de formulário, uma cor por cena | medir a caixa real; sem caixa de formulário; cores constantes |

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

### 8.5 Técnicos

| Problema | Solução |
|---|---|
| Sem `?offset=` a composição começa em 10 s | sempre passar `?offset=0` (ou o início do segmento) |
| `render.py` apaga a pasta `_frames` ao lado da saída | renderizar numa subpasta própria |
| Comando de render > 150 s | renderizar em segmentos de 5 s e juntar |
| ffmpeg não lê woff2 | legenda desenhada no navegador (PNG) |
| Fonte pixel local incompleta | a Press Start 2P do gradio tem 2 KB, sem letras; fonte pixel exige download aprovado |
| Arquivo > 30 MB não chega ao celular pelo chat | mandar a prévia 540×960 |
| Endereço público com `/public` cai no login do Cloudflare | usar `https://nerd.tfr-info.com.br/uploads/...` |
| Primeiro quadro de página nova difere poucos pixels | aceitável (MAE ≤ 0,003); render em segmentos é seguro |

---

## 9. Onde estão os modelos

- Manifesto (referência completa): pacote `conteudo-planejado/videos-echo/manifesto-estrategia-nerd/` (README, cenas, áudio, roteiro, legenda, ferramentas, pranchas). A pasta de trabalho `scratch/manifesto-10-10/` é temporária e sai depois do post publicado.
- Dia do TI: pacote `conteudo-planejado/videos-echo/echo-dia-do-ti/` (v3 = versão com transições).
- Motor do Echo: `conteudo-planejado/videos-echo/manifesto-estrategia-nerd/cenas/src/vendor/robo-motor.js`; fantasias: `conteudo-planejado/videos-echo/echo-dia-do-ti/v3/vendor/robo-roupas-echo.js` (lista de figurinos em `C:\Users\WINDOWS\Projects\output\echo-galeria-descartada\galeria-roupas.js`).
- Agendamento: skill `agendar-reel-instagram` e Central Técnica → Procedimentos.

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
