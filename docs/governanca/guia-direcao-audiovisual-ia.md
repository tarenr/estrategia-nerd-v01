# Estratégia Nerd — Guia de Direção Audiovisual para Produções com IA

> **Documento de referência para a documentação do portal**  
> **Versão:** 1.0 · **Atualizado em:** 17/10/2026  
> **Local sugerido no repositório:** `docs/standards/guia-direcao-audiovisual-ia.md`  
> **Aplicação:** vídeos editoriais, Reels, Shorts, manifestos, vinhetas narrativas, explicadores, apresentações de conteúdos e peças audiovisuais da marca.  
> **Natureza:** diretrizes criativas e operacionais; não é um roteiro nem uma ordem de implementação para um vídeo específico.

## 1. Objetivo

Estabelecer um padrão consistente para **planejar, criar, avaliar e refinar vídeos produzidos com auxílio de IA** para o Estratégia Nerd, registrando as práticas que levaram a uma linguagem mais fluida e os erros que produziram resultados artificiais.

A meta não é movimentar muitos elementos nem demonstrar a potência de um motor gráfico. É fazer com que **narração, imagem, personagem, movimento, montagem e som contem a mesma história**.

**Princípio central:** cada acontecimento importante deve ter uma causa visual ou narrativa perceptível e provocar uma consequência. Objetos não devem apenas entrar, ficar parados e sair; devem **participar da explicação**. Cortes diretos continuam bem-vindos quando têm propósito.

---

## 2. Hierarquia de decisões

Em qualquer produção, priorizar nesta ordem:

1. **Mensagem e fidelidade editorial:** o que o espectador deve entender?
2. **Progressão narrativa:** qual acontecimento prepara o próximo?
3. **Coreografia e atuação:** como os elementos reagem e interagem?
4. **Composição e legibilidade:** para onde o olhar deve ir?
5. **Montagem e sincronização com a locução:** quando algo deve acontecer?
6. **Sonoplastia:** quais acontecimentos precisam ser ouvidos?
7. **Estilo, acabamento e efeitos:** o que melhora a comunicação sem roubar o foco?
8. **Tecnologia:** qual solução executa bem o resultado pretendido?

**Nunca inverter essa ordem:** não mudar a ideia para justificar um efeito ou uma ferramenta recém-descoberta.

## 3. O que aprendemos: erros e correções

| Antipadrão observado | Por que falha | Diretriz correta |
|---|---|---|
| Vídeo com aparência de slides ou site animado | Elementos são tratados como caixas independentes; faltam consequências entre ações | Construir sequências audiovisuais, não layouts sucessivos |
| Personagem sozinho sobre fundo vazio | O ambiente não contextualiza o assunto | Dar ao cenário objetos, imagens ou atividades pertinentes à narração |
| Muitos cards pequenos e ilegíveis | A quantidade não se converte em riqueza visual | Preferir elementos reconhecíveis, com escala, contraste e hierarquia adequados |
| Publicações que apenas deslizam uniformemente | Movimento parece carrossel ou dashboard | Variar trajetórias, velocidade, profundidade e momentos de entrada, com coreografia intencional |
| Objeto importante aparece pronto ou maior que os demais | O espectador percebe uma troca artificial ou perde a surpresa | Quando a história pedir revelação, estabelecer primeiro o objeto no ambiente e acompanhar sua transformação |
| Ampliar todo um card por `scale()` | Conteúdo parece apenas uma imagem esticada | Animar também seus componentes internos: imagem, manchete, critérios e hierarquia |
| Personagem gesticula sem parar | Reduz naturalidade e elimina contraste com reações importantes | Apresentação calma; gestos associados à fala; expressividade forte só diante de estímulo |
| Personagem desaparece ou volta sem motivo | Quebra continuidade e parece correção de layout | Entradas, saídas e reposicionamentos precisam de motivação de montagem ou atuação |
| Objetos atravessam o personagem por erro de camada | Parece transparência indevida | Distinguir oclusão intencional de erro de composição; validar frame a frame |
| Movimento com ausência total de resposta sonora | Ações parecem sem peso | Sonorizar acontecimentos-chave e o ambiente quando apropriado, sem competir com a voz |
| Título/frase aparece isolado após sumir todo o conteúdo | Final parece slide conclusivo | Fazer a conclusão nascer dos elementos ou da demonstração anterior |
| Excesso de neon, partículas, reflexos e câmera tremendo | Recursos decorativos substituem a narrativa | Usar efeitos apenas se explicarem, contextualizarem ou reforçarem a ação |
| Mudança de tecnologia como solução estética automática | Uma nova biblioteca pode produzir o mesmo resultado ruim | Provar benefício com um teste visual comparável antes de migrar |
| Corrigir um problema apagando o que funcionava | A direção muda a cada revisão e gera retrabalho | Congelar os acertos e alterar somente o que foi solicitado |

---

## 4. Gramática da fluidez audiovisual

### 4.1. Pensar em ações, não em telas

Antes de implementar uma sequência, descrever a cadeia de acontecimentos em verbos concretos: **apresentar → selecionar → aproximar → revelar → reagir → comparar → concluir → continuar**. Não é obrigatório seguir essa ordem; o essencial é haver relação entre as ações.

Para objetos importantes, considerar cinco momentos:

1. **Estabelecimento:** de onde vem o objeto e por que está ali?
2. **Preparação:** o público percebe a direção de uma mudança, se isso for desejável.
3. **Ação principal:** algo acontece de forma legível.
4. **Reação/consequência:** personagem, cenário, texto ou outro objeto responde.
5. **Acomodação/continuidade:** o resultado permanece tempo suficiente para compreensão e alimenta a próxima ação.

É possível **surpreender sem antecipação evidente**: quando a surpresa for o objetivo, a preparação pode permanecer discreta. A relação causa–efeito deve ficar clara *depois* do acontecimento.

### 4.2. Transformações que carregam significado

Sempre que fizer sentido, um elemento deve conservar sua identidade enquanto muda de função: uma miniatura pode se tornar uma explicação ampliada; uma etiqueta pode originar uma questão; um dado pode conduzir à escolha seguinte.

- Preferir **transformações de estado** a meros aumentos uniformes de escala.
- Coordenar elementos externos e internos do objeto; não revelar um layout totalmente pronto no último instante.
- A mudança precisa comunicar conteúdo, e não apenas enfeitar.
- Não forçar *morphs* complexos em toda transição. **Um corte simples e bem motivado pode ser melhor.**

### 4.3. Peso, contato e reação

Para ações de interação ou contato, observar: trajetória → contato → consequência → pequena estabilização.

- Reações devem seguir o estímulo e não começar muito antes dele.
- A velocidade pode variar; evitar interpolação linear em todos os movimentos.
- Mãos, olhar e corpo devem responder ao mesmo objeto e ao mesmo instante.
- Uma física **coreografada e convincente** pode ser superior a uma simulação complexa ou aleatória.
- Evitar tanto ausência de reação quanto exageros que infantilizem a cena.
- A ordem de camadas deve respeitar a intenção: um objeto pode cobrir o tronco sem encobrir necessariamente todas as mãos. Confirmar viabilidade técnica antes de prometer esse efeito.

### 4.4. Movimento secundário não substitui ação principal

Um cenário pode ter elementos em movimento constante, mas precisa existir **um foco dominante por vez**. Quando o objeto principal ganha destaque, reduzir contraste, velocidade aparente ou densidade dos demais conforme necessário. O fundo continua vivo, mas não disputa atenção.

---

## 5. Personagens e apresentadores

Personagens da marca — incluindo Echo quando fizer sentido no vídeo — devem **atuar**, não funcionar como decoração fixa.

- **Durante apresentação:** contato visual, boca ligada à fala quando apropriado, inclinação natural, gestos contidos e pausas.
- **Diante de acontecimentos:** olhar acompanha o objeto, expressão muda por um motivo e corpo reage de forma sincronizada.
- **Durante uma demonstração:** o personagem pode diminuir, mudar de posição ou sair do enquadramento para dar protagonismo à informação.
- **Em retornos:** entrada deve ser coerente com o plano anterior; não fazê-lo reaparecer por padrão no centro.
- **Na continuidade:** manter aparência, proporções, expressões oficiais e coerência de atuação.

**Regra importante:** não confundir “o personagem não precisa aparecer em todas as cenas” com “o personagem deve desaparecer arbitrariamente”. A presença ou ausência é uma decisão narrativa.

Se houver locução associada ao apresentador, alinhar boca/visemas às falas e pausas **reais**. Evitar animação labial aleatória e movimentos de boca durante silêncio. Se o personagem não estiver em quadro, a locução pode continuar em *voice-over*.

## 6. Cenários, notícias, imagens e composição

### 6.1. Cenário com contexto

O cenário não precisa de chão, reflexos, volumetria ou um ambiente 3D. Precisa de **informação visual pertinente**, profundidade suficiente e composição intencional. Fundos discretos são válidos quando ajudam a concentrar o olhar; fundos vazios por falta de direção artística, não.

### 6.2. Qualidade do material visual

- Distinguir **ícone** de **ilustração**: ícones funcionam para funções pequenas; conteúdos centrais precisam de imagens expressivas e reconhecíveis.
- Usar imagens, objetos, cenas e detalhes adequados ao que a narração descreve.
- Não preencher espaço com retângulos genéricos e microtextos que ninguém lerá no celular.
- Manter paleta, tipografia, linguagem de bordas e acabamento consistentes, sem tornar todos os planos idênticos.
- Quando um conjunto de itens tem o mesmo papel inicial, mantê-los em **padrão visual coerente**. Um item que receberá foco depois não deve ser denunciado antecipadamente pelo design, a menos que essa antecipação seja intencional.

### 6.3. Fluxos de informações

Quando o conceito envolver fluxo, volume ou excesso de informação:

- Distribuir trajetórias e entradas de modo não uniforme.
- Variar direção, velocidades aparentes, profundidade e escala.
- Permitir entradas e saídas pelas bordas do enquadramento.
- Fazer movimentos parecerem orgânicos, mas manter execução controlada e reproduzível.
- Exibir uma quantidade de elementos suficiente para estabelecer o contexto, sem criar ruído ilegível.

**Quantidade não é o critério principal:** o resultado precisa parecer um mundo editorial vivo, não uma grade de notificações.

### 6.4. Tipografia como ação

Textos podem se construir, deslocar, destacar relações e transformar rótulos em conceitos. A tipografia deve nascer do argumento visual sempre que possível, e precisa ter **tempo de leitura real**. Não tentar encaixar uma frase complexa com muitos movimentos em um intervalo impossível.

---

## 7. Narração, ritmo e sound design

A narração aprovada é a **fonte primária de tempo e conteúdo**. Antes de animar, conferir áudio e roteiro reais, com marcações de início/fim de frases, palavras-chave, pausas e transições. Se documento e áudio divergirem, **registrar a divergência e solicitar decisão**, sem reescrever ou acelerar a voz por conta própria.

### 7.1. Sincronização

- Associar momentos visuais importantes a trechos efetivamente pronunciados.
- Não antecipar conclusões de forma que contradigam a narração.
- Respeitar pausas para reação, humor e leitura.
- Diferenciar a duração de um plano da duração da frase: um objeto pode começar antes e continuar depois.
- Não encerrar ou iniciar a imagem num limite arbitrário de ato se isso cortar fala, som ou movimento.

### 7.2. Sonoplastia narrativa

Sons devem reforçar ações **com significado**: passagem relevante, contato, impacto, deslocamento, abertura, encaixe, revelação, escolha, transição.

- Um efeito pode indicar que algo aconteceu **fora do quadro**; um personagem pode reagir a esse som, ampliando a sensação de mundo.
- Não atribuir som a cada elemento secundário.
- Combinar timbre e intensidade ao peso visual e ao tom editorial da cena.
- A voz deve permanecer inteligível; ajustar efeitos individualmente, usar automação/ducking quando necessário.
- Evitar sustos sonoros injustificados, clipping e dependência de metas numéricas de loudness como prova única de qualidade.
- Verificar a mixagem **ouvindo**, não apenas por métricas.

**Dois testes complementares:** assistir sem áudio para verificar se a história é compreensível e assistir com áudio para verificar se ritmo e sons reforçam — e não mascaram — o sentido.

---

## 8. Identidade editorial, veracidade e direitos

O Estratégia Nerd aborda tecnologia, hardware, games, gadgets, cultura geek e escolhas práticas. Cada produção pode focar um tema, mas não deve reduzir automaticamente toda a identidade do portal a um único assunto recorrente.

Regras obrigatórias:

1. **Jamais apresentar artigos, títulos, imagens ou thumbnails reais do Estratégia Nerd como exemplos de desinformação, achismo ou conteúdo inferior**, inclusive quando recortados, renomeados ou rotulados como “exemplo fictício”.
2. Exemplos negativos devem ser **integralmente fictícios**, distinguíveis de publicações reais, com material visual próprio ou direitos de uso adequados.
3. Ao mostrar conteúdo real do portal de maneira positiva, **confirmar sua existência, título, imagem e afirmações**. Marca e acabamento visual não bastam para provar qualidade; demonstrar informação, contexto, critérios e fontes efetivamente presentes.
4. Não inventar benchmarks, preços, citações, fontes, funcionalidades ou resultados como fatos. Quando um gráfico ou dado for apenas demonstrativo, identificar isso claramente.
5. Respeitar licenças de imagens, fontes, músicas, efeitos e materiais de jogos, séries e outras obras. Não copiar literalmente vídeos de referência.
6. A procedência deve ser inspecionada por **origem e revisão visual**; uma auditoria de caminhos/hashes, sozinha, não garante ausência de reutilização indevida.

---

## 9. Tecnologia: ferramenta a serviço do resultado

Não confundir **motor de renderização**, **biblioteca de animação**, **framework de interface** e **metodologia de direção artística**.

No ecossistema já empregado no projeto, o **OneTake** atua como renderizador; recursos JavaScript/Canvas podem construir e compor objetos; o motor visual do personagem deve ser preservado; e o **Motionmaxxing** pode oferecer referências de coreografia e avaliação. **Motionmaxxing não é autorização para impor uma estética específica.**

Orientações:

- Não trocar de tecnologia porque outra parece moderna ou popular; React, Canvas, WebGL e outras alternativas não garantem direção artística melhor.
- Utilizar primeiro recursos existentes cujas APIs e comportamentos tenham sido **verificados no código e em render real**.
- Separar limitações da ferramenta de falhas da implementação.
- Se uma mudança arquitetural for necessária, criar **teste comparativo pequeno, justo e visualmente verificável** antes de migrar.
- Comparar resultados em movimento, legibilidade, determinismo, tempo de implementação e custo de manutenção — não apenas capturas estáticas.
- Não tratar porcentagens arbitrárias de ganho, “risco zero” ou “qualidade garantida” como diagnósticos técnicos.
- Não alterar bibliotecas compartilhadas ou aplicativos originais só para resolver uma cena experimental sem aprovação explícita.

### 9.1. Animação determinística quando o pipeline exigir

Para renderização por busca de tempo absoluto ou subframes:

- Avaliar o estado visual por `S(t)`, independente da ordem dos frames.
- Evitar depender de `requestAnimationFrame`, relógio real, histórico incremental ou `Math.random()` sem semente controlada.
- Verificar seeks fora de ordem, repetição de instantes, camadas, alfa, fontes e imagens carregadas.
- Validar corretamente fps, contagem de frames, duração, áudio, junções e reprodução em dispositivo móvel.

Não presumir que a própria adoção de uma biblioteca torna o sistema determinístico: **testar**.

---

## 10. Fluxo de produção recomendado

### Fase 1 — Entender a mensagem

Registrar brevemente público, objetivo, canal, formato, tom, mensagem principal, fonte do texto e referências. Identificar o que o vídeo precisa **mostrar**, além de dizer.

### Fase 2 — Marcar a locução e construir o mapa de ações

Ouvir o áudio, conferir o roteiro e criar um mapa simples: **tempo da fala → ação principal → elemento visual → atuação → áudio/SFX → ligação com o próximo plano**. Não definir tempos apenas por estimativa sem conferir a gravação.

### Fase 3 — Escolher linguagem visual e ativos

Definir 1–2 referências de movimento e composição (sem copiá-las), selecionar imagens coerentes e avaliar legibilidade e proveniência. Priorizar objetos que podem **fazer algo**, não apenas ocupar espaço.

### Fase 4 — Prototipar apenas o risco principal

Quando existir uma interação desconhecida (contato, máscara, morph, transição complexa), validar em um **microprotótipo curto**. Não transformar cada observação em mais uma rodada de pesquisa tecnológica. Encerrar a fase quando o movimento estiver aprovado.

### Fase 5 — Produzir trecho curto e avaliá-lo em movimento

Renderizar prévia econômica, com áudio, resolução e fps adequados para avaliação. Assistir à animação inteira: pranchas ajudam a localizar defeitos, **mas não substituem o vídeo**.

### Fase 6 — Refinar sem destruir os acertos

Separar: **aprovado / precisa de ajuste / ainda não demonstrado**. Corrigir somente o necessário. Preservar versões anteriores, e não reinterpretar um comentário específico como autorização para reformular todo o vídeo.

### Fase 7 — Expandir mantendo continuidade

Avançar para o trecho seguinte a partir do estado e da narração existentes. O início do novo trecho deve respeitar o fim do anterior: posição, velocidade, sentido, personagem, som e tempo. Cortes são permitidos, desde que intencionais.

### Fase 8 — Validar entrega

Conferir formato, resolução, fps, duração, áudio, sincronização, integridade de ativos, legibilidade, direitos, determinismo (quando aplicável) e qualidade artística. **Aprovação visual humana é indispensável** antes de produzir a versão final ou ampliar para trechos não autorizados.

---

## 11. Como a IA deve receber e interpretar feedback

Feedback curto **não é** um novo briefing completo. Antes de agir:

1. Identificar **qual comportamento** o feedback critica ou propõe.
2. Distinguir **o que deve mudar** do que foi **aprovado e deve permanecer**.
3. Avaliar implicações de tempo, narração, composição e continuidade.
4. Dar uma recomendação curta e explicar eventuais conflitos.
5. **Somente produzir plano completo ou executar mudanças quando solicitado/autorizado**.

Exemplos de interpretação correta:

- “O personagem pode ficar menor” **não significa** fazê-lo desaparecer.
- “Quero mais elementos” **não significa** multiplicar caixas pequenas ilegíveis.
- “O fundo está vazio” **não significa** adicionar hologramas e partículas.
- “Uma interação está fraca” **não significa** trocar toda a tecnologia.
- “Uma cena está boa” **não significa** que é permitido refazê-la na próxima entrega.

Se uma sugestão criar conflito com uma diretriz já aprovada, **sinalizar o conflito** em vez de escolher silenciosamente uma interpretação.

---

## 12. Checklist de aprovação artística e técnica

Avaliar o vídeo real, no tamanho de reprodução pretendido, com e sem som.

### Narrativa e fluidez

- [ ] A animação mostra algo que a narração está comunicando?
- [ ] A sequência possui causa, ação, reação e continuidade reconhecíveis?
- [ ] Há progressão visual, ou só elementos que aparecem e somem?
- [ ] O espectador sabe onde olhar em cada momento?
- [ ] A conclusão decorre do que foi mostrado?

### Cenário, objetos e personagem

- [ ] O cenário tem contexto sem parecer vazio nem sobrecarregado?
- [ ] Os elementos principais são reconhecíveis e legíveis no celular?
- [ ] Itens equivalentes têm tratamento visual coerente?
- [ ] O personagem atua com gestos motivados e expressões naturais?
- [ ] Posição, camadas, mãos, cabeça e oclusões estão corretas?
- [ ] Movimento de fundo não compete com a informação principal?

### Som e tempo

- [ ] Imagem e atuação acompanham o áudio verdadeiro?
- [ ] A fala permanece clara e sincronizada?
- [ ] Os principais acontecimentos recebem resposta sonora quando apropriado?
- [ ] Efeitos têm volume e caráter adequados, sem clipping?
- [ ] Há tempo suficiente para ler e entender o que importa?

### Identidade, editorial e entrega

- [ ] Não existe conteúdo real do portal indevidamente usado como exemplo negativo?
- [ ] Afirmações, dados e fontes são verdadeiros ou identificados como demonstração?
- [ ] Imagens e sons possuem direitos/proveniência adequados?
- [ ] A linguagem visual é do Estratégia Nerd, não uma cópia da referência?
- [ ] A tecnologia foi usada por necessidade, e não por exibicionismo?
- [ ] Os trechos aprovados e as versões anteriores foram preservados?
- [ ] Arquivo, duração, fps, áudio e junções foram inspecionados?

**Bloqueadores de aprovação:** erro editorial ou de proveniência; narração incorreta; visual ilegível; interação essencial incoerente; sobreposição defeituosa; ausência de continuidade relevante; mudança não autorizada de partes aprovadas.

---

## 13. Contrato resumido para agentes de IA

> Ao produzir vídeos para o Estratégia Nerd, aja como **diretor de animação, editor audiovisual e responsável pela clareza editorial**, não apenas como programador de interfaces. Comece pela mensagem e pela locução real. Construa ações com causa, reação e consequência; dê função narrativa aos objetos, personagens, textos e sons. Preserve o que já foi aprovado. Não substitua fluidez por slides, HUDs genéricos ou efeitos futuristas decorativos. Mantenha imagens reconhecíveis, legibilidade mobile, coerência visual e rigor editorial. Use as ferramentas existentes com critério; teste limitações em protótipos pequenos em vez de migrar por suposição. Avalie os vídeos **em movimento e com áudio**. Solicite aprovação antes de alterar materiais aprovados, ampliar o escopo ou renderizar a versão definitiva.

---

## 14. Manutenção deste guia

Este documento registra **princípios duradouros**, não detalhes temporários de uma produção. Novas técnicas podem ser incorporadas quando demonstrarem melhoria perceptível e repetível em trabalhos reais. As mudanças devem ser registradas com motivação e exemplos de aprendizado, sem transformar uma solução específica em regra obrigatória para todos os vídeos.

**Critério final de qualidade:** o espectador não deve perceber uma sucessão de caixas animadas; deve acompanhar uma história visual clara, com ritmo, personalidade e informação útil.

---

## 15. Erros registrados nas produções da série do Echo (out/2026)

> Acrescentado em 20/10/2026 (Forge #1561) ao trazer este guia para a documentação interna. A tabela completa, com causa e prevenção, está em `docs/governanca/molde-serie-echo.md`, seção 8. Aqui ficam os erros que confirmam os princípios acima.

| Erro observado | Princípio deste guia que foi quebrado |
|---|---|
| Final do Manifesto 23 s no mesmo quarto, "tudo muito igual" | 4.1 pensar em ações; seção 3 "carrossel ou dashboard" |
| Para variar, as cenas do começo foram copiadas no final | 4.2 transformações com significado; progressão narrativa |
| O Echo não mexia a boca em 18 s de fala | 5 personagens e apresentadores; 7.1 sincronização |
| Cards e cápsulas pequenos, ilegíveis no celular | 6.2 qualidade do material; legibilidade mobile |
| Os quatro cards do método somem de uma vez | seção 3 "personagem/objeto desaparece sem motivo" |
| Cenário do Dia do TI com cabos em linhas retas, traço de diagrama e chão vazio | 6.1 cenário com contexto; 6.2 qualidade visual |
| Cenários v9 chapados e escuros, com recortes da referência | 6.2 e 8 identidade e direitos |
| Legenda pequena, depois cobrindo cards e o cartão do portal | 6.4 tipografia como ação; composição e legibilidade |
| Roteiro antigo ("Siga o portal") diferente da voz ("link na bio") | 10 fase 2: marcar a locução real |
| Escolhas de um vídeo (fonte, música, posição da legenda) quase viraram regra da série | 14 "sem transformar uma solução específica em regra" |
| Relatórios de IA afirmando correções que os quadros desmentiam | 11 feedback e 12 checklist: avaliar em movimento, não por relatório |
