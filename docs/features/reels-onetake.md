# Reels e Vídeos com o motor OneTake — Arquitetura, Capacidades e Operação

ID: FEAT-REELS-ONETAKE  
Tipo: Infraestrutura (motor de vídeo e animação) + operação de Reels  
Data: 16/10/2026  
Tarefas Forge: Infraestrutura Geral (ID 8)  

---

## 1. Visão Geral e Objetivo

O **OneTake** (`C:\Users\WINDOWS\Projects\onetake`) é um motor de geração de vídeos e animações em plano-sequência contínuo ("one take"), onde a premissa central é que os elementos visuais nunca são cortados bruscamente nem trocam de tela como slides de PowerPoint: cada elemento em cena se transforma no elemento do próximo momento (kinetic type, morphing de caixas, expansão de palcos e câmeras dinâmicas com inércia física).

No ecossistema do **Estratégia Nerd**, o OneTake serve como infraestrutura compartilhada para a criação de:
- Reels verticais (1080×1920) para o Instagram.
- Demonstrações de produto e interfaces reconstruídas em HTML.
- Vídeos explicativos narrados com sincronização rítmica por BPM e efeitos sonoros sincronizados.

---

## 2. Arquitetura Técnica

O motor difere de renderizadores convencionais por ser 100% determinístico e baseado na web:

| Componente | Função |
|---|---|
| **Composição HTML/DOM** | O vídeo é escrito em um arquivo HTML (`comp.html`). Ele expõe `window.__seek(t)` que posiciona todos os elementos no instante exato $t$ (em segundos). |
| **Biblioteca de Movimento** | `lib/motion.js` fornece curvas de aceleração física (molas amortecidas, transições elásticas, interpolações lineares e easing medido), onde toda propriedade visual é função pura do tempo. |
| **Renderizador Playwright** | `scripts/render.py` roda instâncias headless de navegador via Playwright em Python, buscando quadro a quadro e capturando screenshots de alta precisão. |
| **Desfoque de Obturador Físico (Shutter Blur)** | Com `--shutter 180`, o renderizador realiza múltiplas capturas ao longo da abertura virtual do obturador da câmera e calcula a média em luz linear sRGB. Movimentos rápidos geram desfoque natural sem efeito estroboscópico. |
| **Oráculo de Continuidade** | `scripts/probe.py` e `scripts/verify_promo.py` analisam o vídeo gerado quadro a quadro para medir a taxa de sobrevivência dos elementos nas fronteiras de tempo (carry score) e verificar ritmo, evitando vídeos com cadência uniforme monótona. |

---

## 3. Capacidades de Áudio e Integração

O motor possui módulos dedicados para sonorização:

1. **Trilhas Sonoras do Estratégia Nerd**:
   - Integração direta com a biblioteca de faixas do projeto em `public/uploads/audio/`.
   - Suporte a alinhamento rítmico musical por BPM ($\text{duração do beat} = 60 / \text{BPM}$), permitindo que os marcos visuais coincidam exatamente com os tempos fortes da música.

2. **Efeitos Sonoros Procedurais (SFX)**:
   - O utilitário `scripts/sfx_palette.py` sintetiza bipes, swooshes, cliques, impactos e ruídos determinísticos a partir de frequências matemáticas, compartilhando o mesmo espaço acústico de reverberação.

3. **Locuções e Narração por IA**:
   - Integração com APIs de síntese de voz (como o SDK do ElevenLabs) para gerar narrações em português com modelos multilíngues, sincronizando os tempos da tela com as pausas naturais da voz.

4. **Mixagem Automatizada (FFmpeg)**:
   - Scripts de linha de comando ou utilitários Bash/Python combinam o vídeo renderizado com as camadas de voz, trilha de fundo com ducking e SFX com normalização EBU R128 (`loudnorm=I=-16:TP=-1.5:LRA=11`).

---

## 4. Área Segura do Instagram Reels (1080×1920)

Em qualquer produção vertical voltada para o Instagram Reels, os limites visuais de texto e elementos de ação devem respeitar a área segura para que a interface nativa da rede (botões de curtir/comentar, legenda, avatar e cabeçalho) não sobreponha a informação:

- **Eixo Vertical (Y)**:
  - Margem superior mínima: $Y \ge 170\text{ px}$.
  - Margem inferior máxima: $Y \le 1540\text{ px}$.
- **Eixo Horizontal (X)**:
  - Margem esquerda mínima: $X \ge 72\text{ px}$.
  - Margem direita máxima (coluna de botões do app): $X \le 925\text{ px}$.
- **Auditoria**: É possível rodar validação automatizada das caixas delimitadoras (`getBoundingClientRect()`) via script Playwright em tempos chave antes da aprovação do vídeo.

---

## 5. Como Estruturar Novos Casos

A criação de vídeos no OneTake é livre de modelos rígidos. Cada vídeo é estruturado em sua própria pasta isolada:

```text
cases/
  meu-novo-video/
    comp.html         # Composição visual HTML/CSS/JS (com window.__seek(t))
    motion.js         # Curvas de movimento
    assets/           # Imagens, logos ou mídias locais
    make_sfx.py       # (Opcional) Síntese dos efeitos sonoros
```

Cada caso define livremente seu tempo, tema, aspecto (1080×1920 para Reels, 1920×1080 para widescreen ou 4K), permitindo máxima criatividade sem padrões engessados de roteiro.

---

## 6. Comandos Operacionais

Os comandos são executados a partir da raiz de `C:\Users\WINDOWS\Projects\onetake` com o ambiente virtual Python ativo:

```powershell
# Ativar o ambiente virtual (Windows PowerShell)
.\.venv\Scripts\Activate.ps1

# Render de rascunho rápido (1080p, sem desfoque pesado para validação)
python scripts/render.py cases/meu-novo-video/comp.html --out cases/meu-novo-video/draft.mp4 --shutter 0

# Render de alta qualidade com desfoque de obturador real (shutter 180°) e áudio
python scripts/render.py cases/meu-novo-video/comp.html --out cases/meu-novo-video/final.mp4 --sfx cases/meu-novo-video/sfx.wav --shutter 180

# Medição de continuidade e ritmo pelo oráculo
python scripts/probe.py cases/meu-novo-video/final.mp4
```
