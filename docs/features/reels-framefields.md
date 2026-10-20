# Reels e Vídeos com o motor Framefields — Arquitetura WebGPU, 3D e Operação

ID: FEAT-REELS-FRAMEFIELDS  
Tipo: Infraestrutura (motor de vídeo acelerado por GPU) + operação de Reels  
Data: 16/10/2026  
Tarefas Forge: Infraestrutura Geral (ID 8)  

---

## 1. Visão Geral e Objetivo

O **Framefields** (`C:\Users\WINDOWS\Projects\framefields`, pacote `framefields` v2.1+) é um SDK de composição e renderização de vídeo desenvolvido especificamente para **TypeScript e agentes de IA**.

Diferente de motores como OneTake ou Motionmaxxing (que abrem um navegador Chrome/Playwright headless para capturar telas quadro a quadro), o Framefields **elimina o navegador por completo**:
- **Zero Headless-Browser Overhead**: Não usa Chromium, nem Puppeteer, nem DOM reflows e nem pipeline de screenshots.
- **Renderização Nativa em GPU**: Renderiza os quadros diretamente na placa de vídeo via **WebGPU e Dawn** no Node.js (>= 22), utilizando DirectX / Vulkan no Windows.
- **Trio Criativo em 1 SDK**: Combina composição 2D (Photoshop), tipografia e motion graphics (After Effects) e cenas/câmeras 3D com malhas (Blender).

No ecossistema do **Estratégia Nerd**, o Framefields atua como ferramenta de **Infraestrutura Geral** para vídeos de alta performance, comparativos de hardware com elementos 3D e motion graphics acelerados.

---

## 2. Arquitetura Técnica

| Componente | Detalhe |
|---|---|
| **Linguagem & Runtime** | TypeScript executado nativamente em Node.js v24. |
| **Engine Gráfica** | WebGPU nativo via Dawn / WGSL, compilando pipelines de renderização direto para a GPU. |
| **Precisão de Tempo** | Discrete sample points e BeatGrid para sincronização perfeita de áudio sem drift de timers flutuantes. |
| **Composição Modular** | `new Composition({ width, height, fps, durationFrames })` com árvore de nós e camadas (`Layer`, nós 2D/3D). |
| **Pós-Processamento** | Shaders integrados de granulado cinematográfico (`FilmGrain`), vinhetas (`Vignette`), blur óptico e profundidade de campo. |

---

## 3. Área Segura do Instagram Reels (1080×1920)

Assim como nos demais motores do projeto, em vídeos verticais (9:16) a área útil de texto e botões deve obedecer aos limites para não ser encoberta pela interface do Instagram:
- **Eixo Vertical (Y)**: $Y \in [170, 1540]\text{ px}$.
- **Eixo Horizontal (X)**: $X \in [72, 925]\text{ px}$.

---

## 4. Estrutura de Cenas em TypeScript

As cenas são declaradas por código limpo e determinístico:

```typescript
import { Composition, Layer, FilmGrain, Vignette } from "framefields";

export async function buildFilm(): Promise<Composition> {
  const film = new Composition({
    width: 1080,
    height: 1920,
    fps: 60,
    durationFrames: 600, // 10s a 60 fps
    backgroundColor: "#0b0d11",
  });

  // Adicionar nós de cena (motion graphics, textos, modelos 3D)
  // ...

  // Áudio com BeatGrid
  film.add(Layer.audio("caminho/para/audio.mp3", { volume: 1, durationFrames: 600 }));

  // Efeitos ópticos
  film.apply(new Vignette({ strength: 0.15, radius: 0.9 }));
  film.apply(new FilmGrain({ strength: 0.03, animated: true }));

  return film;
}
```

---

## 5. Comandos Operacionais

Executados a partir de `C:\Users\WINDOWS\Projects\framefields`:

```bash
# Preview interativo no navegador local (renderiza via WebGPU no browser)
npx pnpm render preview

# Renderização final MP4 acelerada por hardware (GPU headless)
npx pnpm render

# Renderização de quadros de teste (PNG)
npx pnpm render frames 0 60 180
```

---

## 6. Registro de Testes Práticos e Avaliação de Resultados (AM4 vs AM5)

Em 16/10/2026, foi desenvolvido um caso real de produção para validar o motor Framefields na criação de Reels verticais (1080×1920) para o blog *Estratégia Nerd*, utilizando o tema *"AM4 vs AM5 em 2026: Vale a pena o upgrade?"* baseado nas publicações existentes do setup.

### 6.1. Histórico de Tentativas

1. **Tentativa 1 — Composição 2D com Cards e Textos (`v1`)**:
   - **Abordagem**: Utilização de `Layer.rect`, `Layer.text` e `Layer.image` organizados em painéis verticais com transições de fade e slides.
   - **Resultado**: Rejeitado imediatamente pelo usuário (*"Ficou horrível, tudo misturado"*). O layout ficou denso, confuso e estático.

2. **Tentativa 2 — Motion Graphics 2D com Curvas de Easing (`v2`)**:
   - **Abordagem**: Separação clara em 4 atos, inclusão de badges de preço ("R$ 3.500" vs "R$ 5.200"), barras de especificações, tags e motion blur via shaders.
   - **Resultado**: Fortemente rejeitado pelo usuário (*"Ficou muito ruim, sem animações, sem transições, nem nada que chame atenção. Nem um Power point é tão ruim"* e *"Não ficou bom"*). A sensação transmitida continuava sendo a de uma sequência de slides estáticos com textos trocando sem profundidade ou apelo visual.

3. **Tentativa 3 — Estúdio 3D com Câmera Orbital e Iluminação Dinâmica (`v3`)**:
   - **Abordagem**: Migração total para o subsistema 3D do Framefields:
     - Câmera orbital contínua (`Layer.camera` com `orbit`) navegando pelo espaço 3D (azimute de -20° a +26°, raio de 2300 para 1500 e handshake físico).
     - Piso de estúdio 3D reflexivo (`Layer3D.roundedBox`) e grid em perspectiva (`Layer3D.grid`).
     - Modelos tridimensionais dos processadores AM4 e AM5 (PCB verde/preto e IHS em níquel escovado metálico).
     - Iluminação de estúdio com SpotLights dinâmicos (âmbar para AM4, ciano para AM5) que aumentavam de intensidade no foco da câmera.
     - Fotos reais das capas dos reviews do blog integradas em planos 3D flutuantes.
     - Trilha sonora temática sincronizada.
   - **Resultado**: Rejeitado pelo usuário (*"Ainda parece uma apresentação do Power point. Deixa pra lá, coloque na documentação suas tentativas e que não foi de acordo com o que eu esperava"*).

### 6.2. Diagnóstico e Conclusão Técnica

- **Performance do Motor**: O Framefields provou sua alta eficiência de renderização (vídeo 10s @ 30fps compilado em apenas 16 segundos via WebGPU, sem abrir instâncias de navegadores headless).
- **Limitação Estética / Perceptiva**: A composição puramente procedural em código (TypeScript) combinando cartões de texto, fotos flutuantes e primitivas 3D não gerou o ritmo cinematográfico, dinamismo orgânico e atratividade esperados para conteúdos virais do Instagram. A estrutura de blocos de informação, mesmo viajando no espaço 3D, manteve uma leitura visual análoga a slides de apresentação corporativa.
- **Diretriz Futura**: Para vídeos e reels do *Estratégia Nerd*, manter a preferência pelos pipelines consolidados (OneTake / Motionmaxxing) ou restringir o Framefields a vinhetas puramente visuais, partículas abstratas e efeitos geométricos, evitando o formato de "cards comparativos com texto em tópicos" que induz à estética de PowerPoint.

