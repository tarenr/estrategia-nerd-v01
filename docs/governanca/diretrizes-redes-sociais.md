# Diretrizes de redes sociais do Estratégia Nerd

> **Fonte única** da estratégia de conteúdo para YouTube (@estrategianerd), Instagram (@estrategia_nerd) e blog (estrategianerd.com.br).
> Origem: documento do Antigravity de 31/10/2026 (estava só na pasta interna dele), corrigido com as decisões do usuário até 01/11/2026 (Forge #1801).
> Regras de produção dos vídeos do Echo: `docs/governanca/molde-serie-echo.md`. Tarefas: só no Forge (projeto 4).

## 1. Posicionamento

- **Slogan:** "Nada de achismo. Só estratégia." Fica nos perfis e no Manifesto; **não** entra em todo vídeo.
- **Compromisso que o slogan impõe:** toda peça precisa de fato verificável (fonte, dado, bastidor). Opinião aparece como opinião, nunca disfarçada de análise. Frases sem fonte saem do roteiro.
- **O canal não é:** notícia quente, unboxing de produto barato, gritaria e fórmula de retenção adolescente.

## 2. Público

O "nerd maduro" de 25 a 45 anos, que trabalha (muitas vezes em TI), tem pouco tempo e orçamento consciente. O próprio usuário é esse público (40 anos, profissional de TI, apaixonado por tecnologia e pela nostalgia de jogos, animes, desenhos e filmes). Teste de qualquer roteiro: "eu assistiria até o fim ou acharia uma bobagem?".

## 3. Pilares

| Pilar | O quê | Exemplos |
|---|---|---|
| Tecnologia Consciente | decisões técnicas e financeiras: diagnóstico, custo-benefício, anti-cilada | "PC liga mas não dá vídeo", "16 GB ou 32 GB", "como escolher fonte" |
| Nostalgia com Substância | jogos, animes, desenhos e filmes que marcaram época, sempre com fato, bastidor ou segredo de design | Castlevania SOTN (castelo invertido), Gundam Wing: Endless Duel |

- Linha de humor "Vida de TI": **suspensa** em 01/11/2026 (o usuário achou fora do posicionamento; o vídeo pronto ficou não listado no YouTube).
- Peças fora dos pilares só com decisão explícita do usuário.

## 4. Cadência

- A descrição do YouTube promete **"vídeo toda semana"** (desde 01/11/2026; antes prometia terça e sexta às 18h30).
- Não há dia fixo. Antes de prometer um ritmo maior, medir quanto tempo leva produzir um vídeo do Echo.
- Instagram: grade da fila em 12:00 e 19:30; vídeos do Echo já usaram 16:00 e 18:30. Evitar dois Reels no mesmo dia.

## 5. O apresentador: Echo

- Robô animado pelo motor oficial; atua no quarto vivo em camadas que é cópia do setup do operador (`conteudo-planejado/videos-echo/_kit-quarto/quarto_setup_raster.js` + `producao-camadas-v1/`, com cadeira ampliada 1,28×, 5 quadros de Yu-Gi-Oh, iluminação animada por `tempo` e atuação padrão sentado `k = 1.50` → pousa controle no assento → flutua no meio do quarto `k = 1.85` a 60 fps com Propulsão Matrix; modelos em `conteudo-planejado/videos-echo/_kit-quarto/producao-camadas-v1/demo.mjs` e `castlevania-castelo-invertido/v2/vendor/`).
- **Voz atual:** `pt-BR-AntonioNeural` (edge-tts), porque os créditos do ElevenLabs acabaram. Ela erra nomes em inglês: testar a pronúncia antes da locução inteira.
- **Voz futura:** o usuário vai gravar quando comprar microfone. Os vídeos antigos **não** serão refeitos com a voz nova.
- Ideia em amadurecimento: frase marcante do personagem no fim (ver `docs/governanca/backlog-baixa-prioridade.md`).

## 6. Formato híbrido (um vídeo para Reels e Shorts)

1. **Um único arquivo** vertical 1080×1920, de cerca de 45 s (locução de 80 a 100 palavras).
2. **Área segura das duas redes:** texto e rosto do Echo entre **Y 250 e 1450**, entre **X 90 e 990**, e até **X 925 entre Y 970 e 1450** (coluna de botões). A capa segue a mesma área e cabe na grade 3:4 do Instagram (Y 240–1680). Os números de Shorts vieram do documento original e não foram medidos numa fonte oficial.
3. **Chamada final:** só a pergunta para os comentários. Proibido no áudio e na tela: "link na bio" (não funciona no YouTube), "inscreva-se", "siga o perfil".
4. **Textos diferentes por rede:** título, descrição e tags no YouTube; legenda e hashtags no Instagram (`.claude/skills/post-instagram/estilo-estrategia-nerd.md`, com tom contido para o público maduro). "Link na bio" no Instagram só se houver artigo **publicado em produção**.
5. **Nada vai a público sem o usuário ver o arquivo final antes.**

## 7. Perfis

- **YouTube:** descrição atual com o slogan e "vídeo toda semana" (texto exato no Studio). Os 32 vídeos antigos foram deixados **não listados** em 31/10/2026, como marco zero do canal.
- **Instagram:** proposta de bio de 31/10/2026 (até 150 caracteres): "⚡ Nada de achismo. Só estratégia. / 🤖 Apresentado pelo Echo / 💻 Hardware consciente sem enrolação / 🕹️ Clássicos cult dos games / 👇 Artigos e guias:", com o site no campo de links. Não foi conferido se a bio foi trocada.

## 8. Direitos autorais (o que se sabe, 01/11/2026)

- O vídeo antigo do Gundam usou a música de abertura da pasta e ficou quase um ano no ar **sem reivindicação**.
- Vídeos antigos com cenas de desenho receberam reivindicação **audiovisual** (ex.: WBTV no ThunderCats), com efeito **só sobre a receita**. O canal não é monetizado.
- Cada dono escolhe o que fazer (receita, bloqueio, silêncio). No Instagram, áudio silenciado derruba a voz do Echo junto. Por isso: acompanhar as primeiras 24 h de cada publicação.
- Gameplay baixado de outros canais é aceitável enquanto o canal não é monetizado, mas deve ser sinalizado no roteiro.

## 9. Números e afirmações sem fonte

O documento original trazia números apresentados como fato (ex.: conversão de Short para vídeo longo de 1 a 3%, decisão do espectador em 2,5 s). Tratar como **estimativa**, nunca como dado. Antes de usar um número em roteiro, achar a fonte.

## 10. Onde ficam as coisas

| O quê | Onde |
|---|---|
| Produção dos vídeos do Echo | `docs/governanca/molde-serie-echo.md` e skill `video-echo-serie` |
| Quarto padrão do Echo (camadas vivas v1) | `conteudo-planejado/videos-echo/_kit-quarto/` (`quarto_setup_raster.js`, `producao-camadas-v1/` e `pecas.json`) |
| Agendamento no Instagram | skill `agendar-reel-instagram` |
| Agendamento no YouTube | fila local `youtube_videos` (`docs/features/FEAT-youtube-integration.md`); molde, seção 3 |
| Pacotes dos vídeos | `conteudo-planejado/videos-echo/<nome>/` |
| Temas candidatos (pasta "Curiosidades e dicas") | `conteudo-planejado/videos-echo/temas-curiosidades-e-dicas.md` |
| Publicado | `public/uploads/reels/<nome>/` |
