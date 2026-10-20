# Reel "Dia do Profissional de TI" — Versão 3 (v3 Revisada)

Prancha editorial e quadros-chave estáticos (1080x1920) produzidos para aprovação de arte, enquadramentos e proporções do Reel com o robô Echo.

---

## 1. Fontes Tipográficas Oficiais Utilizadas

As fontes foram copiadas sem alterações para `v3/fonts/` e carregadas via `@font-face` com sincronização garantida por `document.fonts.ready`:

- **Legendas (todas as cenas):** `Orbitron 700`
  - Arquivo: `v3/fonts/orbitron-700-latin.woff2`
  - Origem: `estrategia-nerd\scratch\reels-motionmaxxing\film-200-v1\fonts\orbitron-700-latin.woff2`
  - Aplicação: Texto centralizado horizontalmente ($X = 540$), altura fixa constante ($Y_{\text{centro}} = 250$), sem caixas de fundo, contorno de tinta escuro espesso (10 px), sombra suave, base branca (`#ffffff`) e palavra-chave em ciano (`#00d4ff`). Acentos latinos (Á, É, Ç) validados.
- **Telão do Terminal (Cena 9):** `JetBrains Mono`
  - Arquivo: `v3/fonts/JetBrainsMono.ttf`
  - Origem: `estrategia-nerd\scratch\manifesto-10-10\v3\assets\fonts\JetBrainsMono.ttf`
  - Aplicação: Linhas de comando e relatório de status/uptime no grande telão do centro de operações (NOC).

---

## 2. Correções Aplicadas na v3 Revisada

1. **Boca Robótica Grande e Aberta (C01, C06, C09, C10):**
   - Echo é um robô: a boca não tem dentes nem língua.
   - Construção: forma arredondada escura (`#0a1017`) com contorno escuro grosso de tinta e cavidade interior com brilho suave ciano (`#00d4ff`) e barra horizontal emissora de voz.
   - Proporcional ao `boca.json`: nos closes (C01 e C06) possui entre 138 e 142 px de largura diretamente na face do robô, sendo perfeitamente identificável a olho nu em recorte de 400 px.
2. **Legendas Padronizadas sem Caixa:**
   - Caixas de fundo removidas em todas as cenas.
   - Posição vertical rigorosamente uniforme em $Y_{\text{centro}} = 250$.
   - No máximo 2 linhas, com dimensionamento dinâmico que impede transbordamento.
3. **Área Segura 9:16 Rigorosa:**
   - Medição real baseada nos pixels desenhados da legenda em cada quadro, gravada em `v3/area_segura.json`.
   - Limites validados: $Y \in [187, 305]$ (dentro de $[170, 1540]$), $X \in [269, 811]$ (dentro de $[72, 1008]$). Zero obstrução de botões laterais do Reels.
4. **Diferenciação de Enquadramento entre C01 e C06:**
   - **C01:** Close frontal direto; mão flutuante em escorço e perspectiva grande em primeiro plano apontando diretamente para a lente da câmera.
   - **C06:** Enquadramento assimétrico em 3/4; Echo de lado com cabeça inclinada e olhar cúmplice de "eu sei"; em primeiro plano imediato, plugue de 2 pinos desconectado grande (escala 2.2) com cabo grosso caindo.
5. **Ninho Denso de Cabos no Rack (C05):**
   - Inspirado diretamente na referência oficial (`referencia-estilo.webp`): mais de 35 cabos volumosos escuros com contorno preto e cel-shading tubular entrelaçados em laços densos sobre o rack, caindo em alças curvas pelas laterais e se espalhando pelo chão com múltiplos plugues de 2 pinos e filtro de linha.
6. **Echo Real e Visível em C02 e C03 ($\ge 40\%$ da tela):**
   - **C02 ("FORMATA"):** Echo visível em 3/4 ocupando 600 px ($\ge 55\%$ da largura), com cabeça, óculos de programador e mãos digitando no teclado mecânico diante do monitor CRT.
   - **C03 ("CONFIGURA"):** Echo visível em ângulo elevado ocupando 649 px ($\ge 60\%$ da largura), com cabeça, óculos e mãos conectando o cabo RJ45 no switch com LED de link verde aceso.
7. **Fundos Preenchidos em Todas as 10 Cenas:**
   - Cenários ricos em estilo cartoon cel-shading em 2 tons: pegboards com ferramentas, prateleiras de hardware, calhas de cabos, conduítes industriais, cofres de dados com fitas, iluminação quente de luminária, monitores auxiliares e terminal hacker. Zero fundo vazio.
8. **Telão de C09 em JetBrains Mono:**
   - Terminal central exibe comandos e uptime de 99.999% na tipografia monoespaçada canônica.
9. **Recortes de Teste e Validação:**
   - Salvos em `v3/conferencia/` para inspeção visual individual de bocas e textos (`boca_C01.png`, `boca_C06.png`, `legenda_C06.png`, `legenda_C09.png`).

---

## 3. Roteiro e Cenas Atualizadas

| Cena | Intervalo | Texto na Tela (Orbitron 700) | Enquadramento e Ação Visual |
|---|---|---|---|
| **C01** | 0,0–2,3s | `EI, VOCÊ AÍ` / `DO TI!` | Close frontal (cabeça 990px), boca robótica aberta (0.993), mão apontando para a lente em primeiro plano; corredor de racks com LEDs e conduítes. |
| **C02** | 2,3–3,3s | `FORMATA` | Bancada de manutenção com pegboard de ferramentas; Echo visível (600px, 55%) digitando no teclado; monitor CRT exibindo 100% concluído. |
| **C03** | 3,3–4,2s | `CONFIGURA` | Switch corporativo 48P; Echo visível (649px, 60%) conectando cabo RJ45 com conector transparente e LED verde de link acendendo. |
| **C04** | 4,2–4,9s | `FAZ BACKUP` | Cofre de dados com storages e conduítes; cascata parabólica de pastas e arquivos voando para um HD externo rugged de 4TB segurado pelo Echo. |
| **C05** | 4,9–7,4s | `E RESOLVE QUASE` / `TUDO COM…` | Rack com ninho denso de dezenas de cabos embolados no topo, caindo em laços pelas laterais e se espalhando com plugues no chão; Echo coçando a cabeça com olhos âmbar "hmm". |
| **C06** | 7,4–8,6s | `JÁ TENTOU` / `REINICIAR?` | Enquadramento em 3/4 com cabeça inclinada e olhar cúmplice "eu sei"; boca robô aberta (0.692); plugue de 2 pinos grande em primeiro plano na mão. |
| **C07** | 8,6–9,1s | *(sem texto)* | Apagão total: ambiente e robô escuros em silhueta; apenas os olhos ciano brilhantes acesos; barra sutil `reiniciando... 42%`. |
| **C08** | 9,1–10,7s | `HOJE O DIA` / `É SEU!` | Mesa do TI aconchegante com luminária de luz âmbar quente, post-its coloridos, dois monitores e teclado mecânico; Echo erguendo caneca de café em brinde. |
| **C09** | 10,7–13,1s | `FELIZ DIA DO` / `PROFISSIONAL DE TI!` | Centro de operações (NOC) com telão central em JetBrains Mono, LEDs todos verdes; Echo comemorando com as duas mãos erguidas e boca aberta (0.995). |
| **C10** | 13,1–17,0s | `UM ABRAÇO DO` / `ESTRATÉGIA NERD!` | Fundo estúdio da marca (zero racks) com grid ciano; Echo acenando amigavelmente com boca aberta (0.944); cartão institucional com caveira (`logo-symbol.png`). |

---

## 4. Como Reproduzir e Regenerar

### 4.1 Quadros Estáticos e Prancha
```bash
# 1. Renderizar os 10 quadros 1080x1920 via Chrome Headless / CDP
node render_quadros.js

# 2. Compor a prancha comparativa em alta resolução
python gerar_prancha.py
```

### 4.2 Áudio Mixado e Vídeo Animado (v3)
```bash
# 1. Gerar o áudio mixado (fala aos 0.3s + música com ducking, -15 LUFS, True Peak <= -1.0 dBFS)
& "C:\Users\WINDOWS\Projects\onetake\.venv\Scripts\python.exe" anim\montar_audio.py

# 2. Renderizar o vídeo completo no OneTake (1080x1920 @ 30 fps, 17.0s)
& "C:\Users\WINDOWS\Projects\onetake\.venv\Scripts\python.exe" "C:\Users\WINDOWS\Projects\onetake\scripts\render.py" anim\comp.html --width 1080 --height 1920 --fps 30 --out anim\render\echo-dia-do-ti-v3.mp4 --sfx anim\audio\mix.wav

# 3. Gerar a prévia compacta para validação (540x960)
ffmpeg -y -i anim\render\echo-dia-do-ti-v3.mp4 -vf "scale=540:960" -c:v libx264 -preset fast -crf 22 -c:a copy anim\render\previa_540x960.mp4
```

Arquivos gerados:
- `quadros/C01.png` a `C10.png`: quadros estáticos em 1080x1920.
- `pranchas/prancha_v3.png`: prancha comparativa 3400x1850 em alta resolução.
- `anim/audio/mix.wav`: trilha mixada sincronizada em -15 LUFS.
- `anim/render/echo-dia-do-ti-v3.mp4`: vídeo renderizado em 1080x1920 @ 30 fps (OneTake).
- `anim/render/previa_540x960.mp4`: prévia compacta para conferência rápida.
- `anim/conferencia/`: quadros extraídos direto do vídeo MP4 e testes de determinismo.
- `medidas.json`: largura do Echo e da cabeça por cena.
- `area_segura.json`: caixas reais por pixel das legendas.

---

## Correções de 19/10/2026 (segunda rodada, Claude Code)

- **Boca:** usa a fala do próprio motor do Echo (`s.mouthOpen`, o "o" canônico), com a abertura vinda do `boca.json`, igual ao Manifesto. A boca extra desenhada por cima foi removida (`bocaFalando()` em `cenas.js`).
- **Mãos:** `maoEcho()` desenha a mão oficial igual ao Manifesto (`drawOfficialEchoHands`): círculo `#7fe3f5`, borda `#00d4ff`, raio `R*0.18*0.85*k`, linha `2k`. Usada nas cenas 1, 3, 6 e 8.
- **Cena 1:** mão grande em primeiro plano vindo na direção da câmera (o Echo oficial não tem dedos).
- **Cena 3:** o cabo de rede sai do conector para a direita, sem passar pelo rosto.
- **Cena 6:** câmera inclinada, Echo à direita olhando de lado e plugue grande em primeiro plano.
- **Cena 8:** mesa com veios de madeira, luminária articulada e monitores ligados.
- **Cena 10:** caveira grande, "19/10 · DIA DO PROFISSIONAL DE TI" e "@estrategia_nerd" (Y 786–868, dentro da área segura); cartão com frase inventada removido.
- **Mãos (acabamento):** cenas 2, 4 e 9 trocadas pela mão oficial (`maoEcho`); cena 5 usa o aceno do próprio motor (`waveSide = -1`) para a mão perto da cabeça. Nenhuma cena tem mais de duas mãos.
- **Cena 2:** tapete antiestático com grade, SSD aberto, chave de fenda, parafusos e caneca na bancada.

---

## Vídeo com transições (linguagem do Manifesto) — 19/10/2026

- `anim/comp_trans.html` + `anim/trans.js`: cada passagem desenha as duas cenas de `anim/cenas_anim.js` em camadas e junta com câmera, sem corte seco:
  1→2 mão do Echo cobre a lente · 2→3→4 câmera desce seguindo o cabo · 4→5 recua do HD e revela o rack · 5→6 aproximação até o plugue · 6→7 a luz pisca e apaga · 7→8 a luminária acende e revela a mesa · 8→9 entra no monitor · 9→10 a tela do terminal se abre e revela a marca.
- A legenda é desenhada uma vez, por cima, sem câmera (as cenas só a registram: `window.__legendaModo = 'gravar'`).
- Prévia rápida (~20 s de render): `python onetake/scripts/render.py anim/comp_trans.html --width 1080 --height 1920 --fps 30 --shutter 0 --out anim/render_rapido/rapido.mp4 --sfx anim/audio/mix.wav`
- Vídeo bom (como o Manifesto): `--fps 60 --samples 4` (shutter 180°) com saída em `anim/render60/`; conferência com `~/.claude/skills/motionmaxxing/scripts/look.py`.
- `anim/comp.html` (versão com cortes secos) e `anim/render/` ficam como referência.
