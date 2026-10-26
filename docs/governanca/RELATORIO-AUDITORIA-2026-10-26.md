# Relatório de Auditoria Completa, Conformidade e Levantamento de Pendências — Estratégia Nerd

> **Data da Auditoria:** 26 de outubro de 2026  
> **Modo de Operação:** Estritamente Leitura (Diagnóstico e Inspeção Não Destrutiva)  
> **Repositório:** `C:\Users\WINDOWS\Projects\estrategia-nerd`  
> **Branch Atual:** `feature/homepage-circuito-continuo`  
> **Status de Execução:** Nenhuma alteração de arquivo, banco, Git, Forge, deploy ou rollback foi executada.

---

## 27.1 Resumo Executivo

A auditoria identificou um descompasso estrutural severo entre os três ambientes (**Local**, **Stage** e **Produção**), resultante de entregas parciais, retrabalho não consolidado no repositório e intervenções manuais ou parciais via FTP sem a devida reconciliação no Git e nas tarefas do Forge.

### Indicadores Gerais
- **Estado Geral:** Divergente e Fragmentado. A produção recebeu 3 arquivos promovidos, mas o ambiente Local permaneceu configurado com uma implementação anterior quebrada (`site-ambient-background.js`), enquanto Stage e Produção executam `site-home-flow.js`.
- **Riscos Principais:**
  1. *Ausência de Rollback Seguro em Produção:* Foi realizado um backup completo de 238 MB (`BS-PROD-20261025-234355`), mas **não existe manifesto nem script de rollback isolado** para os 3 arquivos promovidos. O script existente (`scripts/home-rio-production-deploy.php --rollback`) reverteria apenas a entrega anterior da Home às 21:21, gerando corrupção e inconsistência entre layout e CSS.
  2. *Contaminação do Repositório Local:* Existem 23 arquivos modificados não commitados e 24 arquivos/diretórios não rastreados (`untracked`), incluindo arquivos pertencentes a outra feature (`tools/instagram-highlights` e `FEAT-instagram-highlights.md`), que correm risco iminente de serem commitados acidentalmente.
  3. *Regressão Silenciosa no Ambiente Local:* O layout local (`app/Views/layouts/site.php`) aponta para `site-ambient-background.js`, que falha silenciosamente por incompatibilidade de seletores DOM (`data-site-ambient-background` inexistente).
  4. *Duplicação de Camadas Visuais:* Todas as páginas públicas continuam renderizando a animação legada `.circuit-bg` em sobreposição/conflito com o canvas WebGL `.en-flow-bg`.
- **Divergências Identificadas:** 8 arquivos críticos apresentam diferenças de hash ou presença entre Local, Stage e Produção.
- **Itens Concluídos e Funcionais em Produção:** 2 (Home com rio/portal/Echo Jack e layout com canvas de fundo ativo).
- **Itens Parciais:** 4 (Fundo ambiente nas páginas públicas, responsividade 768-1023px, documentação interna e controle de tarefas).
- **Itens Pendentes:** 5 (Remoção de bordas/emendas estruturais das páginas públicas, remoção do `.circuit-bg` legado, reconciliação Git/Local, limpeza de artefatos órfãos e desativação do debug).
- **Regressões Detectadas:** 3 (Layout local quebrado para páginas públicas; comportamento de encolhimento e corridor check do Echo alterados localmente mas ausentes na produção; tarefas fechadas no Forge com código não promovido).
- **Não Conformidades com as Diretrizes:** 12 das 14 diretrizes avaliadas foram classificadas como **Não Conforme** ou **Parcialmente Conforme**.

---

## 27.2 Matriz de Comparação: Local × Stage × Produção

Hashes SHA-256 obtidos diretamente do disco local e via conexões estritamente de leitura (FTP passivo) em Stage e Produção:

| Item / Arquivo | Local | Stage | Produção | Evidência (SHA-256 / Inspeção) | Situação | Ação Futura |
|---|---|---|---|---|---|---|
| `app/Views/layouts/site.php` | `8ce7e7c0` | `485cae00` | `485cae00` | Local aponta para `site-ambient-background.js`; Stage/Prod apontam para `site-home-flow.js` | Stage = Produção (Local difere) | Reconciliar Local com Stage/Prod |
| `app/Views/components/site/shared/ambient-background.php` | `ec95620b` | `ec95620b` | `553dafc4` | Produção difere apenas por 1 quebra de linha inicial; conteúdo HTML idêntico | Local = Stage (Prod difere cosmeticamente) | Padronizar formatação |
| `public/assets/css/site.css` | `1ebdd4ce` | `bc64a637` | `16cba1a4` | Local tem classes genéricas; Stage tem FIX-009 + `.en-flow-bg`; Prod tem `.en-flow-bg` sem FIX-009 | Divergente nos três | Reconciliar regras de forma cirúrgica |
| `public/assets/css/site-home-flow.css` | `3b4cdbf1` | `23cfd061` | `23cfd061` | Local contém regras de z-index do Echo em títulos (#sobre, #blog); Stage/Prod mantêm versão c68febd | Stage = Produção (Local difere) | Avaliar e promover ou descartar patch do Echo |
| `public/assets/js/site-home-flow.js` | `bf16847d` | `b3e20040` | `b3e20040` | Local contém motor de colisão/obstáculos e mobile do Echo; Stage/Prod contêm motor original c68febd | Stage = Produção (Local difere) | Avaliar e promover ou descartar patch do Echo |
| `public/assets/js/site-ambient-background.js` | `a23b1b60` | `a23b1b60` | **AUSENTE** | Presente no disco local (untracked) e no FTP da Stage; nunca publicado em Produção | Local = Stage (Ausente em Prod) | Remover arquivo órfão no Local e Stage |
| `app/Controllers/Site/HomeController.php` | `5eba15bf` | `142defa8` | `142defa8` | Local contém método de teste `circuitContinuousPreview()`; Stage/Prod não contêm | Stage = Produção (Local difere) | Manter apenas se documentado como rota local |
| `config/app.php` | `b1977838` | `7a569529` | `7a569529` | Local e Prod possuem `debug => true/false` conforme env; Stage teve pacote preparado com `debug => false` | Stage = Produção (Local difere) | Parametrizar via `.env` |
| `config/smoke-tests.php` | `a8ad4da2` | `9fdde6b0` | `9fdde6b0` | Local tem `'admin_enabled' => false` na stage; Stage/Prod no servidor mantêm arquivo de c68febd | Stage = Produção (Local difere) | Manter integridade dos testes |
| `app/Views/site/home-circuito-preview.php` | `0cd1b62a` | `0cd1b62a` | `0cd1b62a` | Template oficial da nova Home | Igual nos três | Manter |
| `app/Views/site/home.php` | `77c9fa0a` | `77c9fa0a` | `77c9fa0a` | Home clássica preservada para contingência | Igual nos três | Manter |
| `app/Views/components/site/shared/nav.php` | `a0ef185d` | `a0ef185d` | `a0ef185d` | Barra de navegação | Igual nos três | Manter |
| `app/Views/components/site/home-flow/hero.php` | `e1754543` | `e1754543` | `e1754543` | Hero da nova Home | Igual nos três | Manter |
| `app/Views/components/site/home-flow/about.php` | `5fe30680` | `5fe30680` | `5fe30680` | Card Sobre da nova Home | Igual nos três | Manter |
| `app/Services/Site/HomeService.php` | `277edce8` | `277edce8` | `277edce8` | ViewModel da Home com `echo_outfit` | Igual nos três | Manter |
| `app/Support/EchoFantasias.php` | `8afa09e4` | `8afa09e4` | `8afa09e4` | Suporte a fantasias do Echo (FEAT-016) | Igual nos três | Manter |
| `config/echo-fantasias.php` | `1289434f` | `1289434f` | `1289434f` | Lista de figurinos do Echo | Igual nos três | Manter |
| `assets/css/site-hero-preview.css` | `058f0c4f` | `058f0c4f` | `058f0c4f` | Estilos de tipografia do Hero | Igual nos três | Manter |
| `assets/vendor/echo-robo/robo-motor.js` | `e01c6e86` | `e01c6e86` | `e01c6e86` | Motor Canvas do Echo | Igual nos três | Manter |
| `assets/vendor/echo-robo/robo-roupas.js` | `15c4dfd4` | `15c4dfd4` | `15c4dfd4` | Roupas base do Echo | Igual nos três | Manter |
| `assets/vendor/echo-robo/robo-roupas-echo.js` | `768cc21e` | `768cc21e` | `768cc21e` | Figurinos e fantasias | Igual nos três | Manter |

---

## 27.3 Auditoria do Fundo Ambiente

### 1. Onde está correto
- **Em Produção e Stage:** As páginas públicas (`/blog`, `/central-nerd`, etc.) renderizam `<canvas class="en-flow-bg" data-flow-bg aria-hidden="true"></canvas>` via `app/Views/components/site/shared/ambient-background.php`. O layout `app/Views/layouts/site.php` carrega `public/assets/js/site-home-flow.js`. Como `hero` e `flowCanvas` são nulos nessas rotas, o script instancia exclusivamente a camada de fundo WebGL2 (`createBackgroundLayer(bgRenderer)`), exibindo os halos respiratórios e o feixe distante com interpolação suave na rolagem.
- **Ausência de efeitos indevidos nas internas:** Confirmado por requisições e análise de código que nas páginas internas **não são carregados** o feixe principal (rio), o portal ou o Echo.

### 2. Onde está ausente ou quebrado
- **No Ambiente Local:** Quebrado. O arquivo local `app/Views/layouts/site.php` (linhas 37 e 205) não foi atualizado como o de Stage/Prod; ele carrega `public/assets/js/site-ambient-background.js`. Esse script procura por `data-site-ambient-background` e `data-site-ambient-canvas`, que não existem no DOM, resultando em término imediato sem renderizar nada.

### 3. Código genérico, órfão ou duplicado
- `public/assets/js/site-ambient-background.js`: Criado como solução genérica 2D (partículas e gradientes radiais em Canvas 2D). Está órfão no Local (untracked) e presente no servidor de Stage (`a23b1b60`). Deve ser removido de ambos.
- Regras CSS genéricas em `public/assets/css/site.css` local (linhas 92-113: `.site-ambient-background`, `.site-ambient-background__canvas`, `.is-fallback`): Código morto que não é utilizado nem na Stage nem em Produção.
- `.circuit-bg` e `.scanline`: **Duplicação visual presente nos três ambientes**. O elemento `<div class="circuit-bg"></div>` continua embutido em `app/Views/site/blog.php` (linha 123), `central.php` (linha 42), `page.php` (linha 58), `post.php` (linha 120) e `error-404.php` (linha 13), executando uma animação CSS de 24 segundos por cima ou por trás do canvas WebGL2.

---

## 27.4 Bordas e Emendas Estruturais das Novas Páginas

Levantamento exaustivo das descontinuidades visuais e fundos sólidos que interrompem a fluidez do fundo ambiente nas rotas públicas (focado estritamente em seções estruturais, sem afetar componentes funcionais):

| Página | Seção Estrutural | Seletor | Regra Responsável | Evidência no Código | Comportamento Esperado |
|---|---|---|---|---|---|
| `/blog` e `/blog/{slug}` | Filtro de Categorias | `section.border-y` | `border-y border-cyan-500/10 bg-slate-900/30` | [blog.php](file:///C:/Users/WINDOWS/Projects/estrategia-nerd/app/Views/site/blog.php#L188) | Remover `border-y` e `bg-slate-900/30`; manter fundo transparente para fluidez do canvas |
| `/blog` e `/blog/{slug}` | Grade Geral de Posts | `section.py-16` | `bg-slate-900/30` | [blog.php](file:///C:/Users/WINDOWS/Projects/estrategia-nerd/app/Views/site/blog.php#L256) | Remover `bg-slate-900/30`; seção transparente (cards mantêm seus fundos próprios) |
| `/blog` e `/blog/{slug}` | Fundo Legado em Mosaico | `.circuit-bg` | `background-image; animation: circuitMove 24s` | [blog.php](file:///C:/Users/WINDOWS/Projects/estrategia-nerd/app/Views/site/blog.php#L123) e [site.css](file:///C:/Users/WINDOWS/Projects/estrategia-nerd/public/assets/css/site.css#L83-L90) | Remover `<div class="circuit-bg"></div>` e `.scanline` das páginas que usam o novo fundo |
| `/post/{slug}` | Seção de Comentários | `#comentarios` | `border-t border-cyan-500/10 bg-slate-900/30` | [post.php](file:///C:/Users/WINDOWS/Projects/estrategia-nerd/app/Views/site/post.php#L335) | Remover `border-t` e `bg-slate-900/30`; formulário e lista de comentários sobre o fundo contínuo |
| `/post/{slug}` | Fundo Legado em Mosaico | `.circuit-bg` | `background-image; animation: circuitMove 24s` | [post.php](file:///C:/Users/WINDOWS/Projects/estrategia-nerd/app/Views/site/post.php#L120) | Remover `<div class="circuit-bg"></div>` e `.scanline` |
| `/central-nerd` | Fundo do Body / Container | `.central-nerd-body` / `.central-hero-backdrop` | `linear-gradient(180deg, #10111b 0%, #161722 100%)` | [site.css](file:///C:/Users/WINDOWS/Projects/estrategia-nerd/public/assets/css/site.css#L2480-L2490) | Tornar container estrutural transparente, preservando apenas os cards individuais |
| `/central-nerd` | Fundo Legado em Mosaico | `.circuit-bg` | `background-image; animation: circuitMove 24s` | [central.php](file:///C:/Users/WINDOWS/Projects/estrategia-nerd/app/Views/site/central.php#L42) | Remover `<div class="circuit-bg"></div>` e `.scanline` |
| `/politica-de-privacidade` e `/termos-de-uso` | Painel de Fundo da Seção | `.site-about-backdrop`, `.site-about-grid` | Gradientes escuros com grade SVG embutida | [page.php](file:///C:/Users/WINDOWS/Projects/estrategia-nerd/app/Views/site/page.php#L70-L71) | Ocultar `.site-about-backdrop` e `.site-about-grid`, exatamente como feito na Home (#1652) |
| `/politica-de-privacidade` e `/termos-de-uso` | Fundo Legado em Mosaico | `.circuit-bg` | `background-image; animation: circuitMove 24s` | [page.php](file:///C:/Users/WINDOWS/Projects/estrategia-nerd/app/Views/site/page.php#L58) | Remover `<div class="circuit-bg"></div>` e `.scanline` |
| Todas as páginas públicas | Componente Newsletter | `#newsletter > .absolute.inset-0` | `bg-gradient-to-r from-cyan-900/20 to-fuchsia-900/20` | [newsletter.php](file:///C:/Users/WINDOWS/Projects/estrategia-nerd/app/Views/components/site/home/newsletter.php#L10) | Ocultar degradê retangular de seção (na Home foi ocultado por `.site-home-hero-preview-page #newsletter > .absolute.inset-0 { display: none; }`) |
| Todas as páginas públicas | Rodapé Global | `.site-footer` | `border-top: 1px solid rgba(0, 212, 255, 0.12); background: rgba(2, 6, 23, 0.82);` | [site.css](file:///C:/Users/WINDOWS/Projects/estrategia-nerd/public/assets/css/site.css#L1318-L1323) | Remover `border-top` e `background` sólido no rodapé global para que o fundo flua até a margem inferior da tela |

---

## 27.5 Echo, Portal, Rio e Cabeçalho

### 1. Echo
- **Em Produção e Stage:** Código em commit `c68febd` (hash `b3e20040`). O Echo sai do portal com fantasia de Jack Esqueleto, ativa propulsão Matrix e acompanha a rolagem pelo corredor lateral a ~420 px/s com escala `0.44`. Não tem detecção de obstáculos em títulos/textos (passa por trás de cards com `z-index: 2`, mas cruza na frente de títulos de seção). No celular, não é renderizado após o Hero.
- **No Local:** Código alterado e não commitado (hash `bf16847d`). Implementa o `OBSTACLE_SELECTOR` completo e forçamento de aparição no celular após o Hero. Essas alterações **nunca foram homologadas ou enviadas para Produção**.
- **Regressão de Encolhimento:** No código local, as constantes `PAD_X = 1.05`, `PAD_Y = 1.48` e `ANCHOR_Y = 0.5` alteraram a proporção e a sensação de profundidade do encolhimento pós-portal em relação à versão original aprovada.

### 2. Portal e Rio da Home
- **Shader do Portal:** Íntegro em Produção e Stage (versão restaurada com ruído procedural, anel de fogo/fumaça e clarão ao fechar).
- **Rio Principal:** Trajetória em WebGL2 de 840 pontos íntegra em Produção e Stage; cálculo de colisão com malha de 8px e cache por geometria da tela.
- **Páginas Internas:** Confirmada ausência total de canvas ou inicialização do Rio e do Portal fora da rota `/`.

### 3. Cabeçalho e Responsividade
- **Breakpoint de 768 px e faixa 768-1023 px:**
  - Em Produção: Possui apenas a correção básica de `.site-mobile-toggle { display: none; }` com exibição até 767px. Não possui a regra responsiva intermediária para 768-1023px.
  - Na Stage: Possui a regra intermediária `@media (min-width: 768px) and (max-width: 1023px)` com redução do logo (`site-brand-orb` para 2.75rem) e diminuição do espaçamento dos links para evitar quebra de linha.
  - No Local: Regra presente em `site.css`, não commitada.
- **Inserção Indevida no Pacote do Fundo:** A alteração do cabeçalho foi indevidamente agrupada na tarefa e pacote da Stage em `scripts/home-pendencias-stage-deploy.php`, violando a separação de escopos.

---

## 27.6 Auditoria das Tarefas no The Forge

Auditoria realizada diretamente no banco SQLite (`C:\Users\WINDOWS\Projects\the-forge\data\the-forge.db`, tabela `project_tasks`, projeto ID 4):

| ID | Título da Tarefa | Status Registrado | Estado Real | Ambiente | Evidência | Status Recomendado |
|---|---|---|---|---|---|---|
| #1640 | `[Home Fluxo] - Parte 1: Hero com feixe de circuitos` | CONCLUIDA | Concluída e comprovada | Local, Stage, Prod | Commit c68febd e deploy 005af61 | Concluída e comprovada |
| #1652 | `[Home Fluxo] - Fundos uniformes das seções da prévia` | CONCLUIDA | Concluída na Home | Local, Stage, Prod | `site-home-flow.css` linhas 190-217 | Concluída (escopo restrito à Home) |
| #1656 | `[Home Fluxo] - Parte 7: Echo no Hero` | CONCLUIDA | Concluída e comprovada | Local, Stage, Prod | Commit c68febd | Concluída e comprovada |
| #1663 | `[Home Fluxo] - Feixe-rio do Hero à base da página` | CONCLUIDA | Concluída e comprovada | Local, Stage, Prod | Commit c68febd | Concluída e comprovada |
| #1666 | `[Home UX] - Echo sem sobrepor textos/controles na rolagem e visível no celular (#1666)` | CONCLUIDA | **Regressão / Falso Concluído** | **Somente Local** | Código presente apenas no working tree (`bf16847d`); Stage e Prod mantêm versão antiga (`b3e20040`) | **Reabrir (Pendente de homologação e deploy)** |
| #1672 | `[Home Fluxo] - Virada local da home para a versão com o rio` | CONCLUIDA | Concluída e comprovada | Local, Stage, Prod | Rota `/` ativa com home-circuito-preview | Concluída e comprovada |
| #1673 | `[Echo Admin] - Tela de escolha da fantasia do Echo` | CONCLUIDA | Concluída e comprovada | Local | `/admin/echo` funcional no admin local | Concluída e comprovada |
| #1674 | `[Echo Admin] - Documentação da tela de fantasias` | CONCLUIDA | Concluída e comprovada | Local | `FEAT-016-fantasia-echo-admin.md` | Concluída e comprovada |
| #1675 | `[Home Fluxo] - Deploy da home nova na stage` | CONCLUIDA | Concluída e comprovada | Stage | `storage/home-rio-deploy/stage/manifest.json` | Concluída e comprovada |
| #1676 | `[Home Fluxo] - Deploy da home nova em produção` | CONCLUIDA | Concluída e comprovada | Produção | `storage/home-rio-deploy/production/manifest.json` | Concluída e comprovada |
| #1683 | `[Home Pendências] - Commit da documentação e script de produção` | CONCLUIDA | **Falso Concluído** | **Nenhum** | Não houve commit após 005af61; documentação em `local-docs-v2.php` está com status falso | **Reabrir (Pendente)** |
| #1684 | `[Home Pendências] - Correção do cabeçalho responsivo` | CONCLUIDA | Parcialmente concluída | Stage (ausente em Prod) | CSS da Stage tem regra 768-1023px; CSS de Produção não tem | **Parcialmente Concluída (Pendente em Prod)** |
| #1685 | `[Home Pendências] - Desativação do debug da STAGE` | ABERTA | Pendente | Stage | `config/app.php` foi enviado em pacote abortado; precisa de conferência formal | Aberta (Pendente) |
| #1686 | `[Home Pendências] - Deploy e validação completa da STAGE` | ABERTA | Pendente | Stage | Houve rollback parcial na stage e pacote isolado; validação incompleta | Aberta (Pendente) |
| #1687 | `[Home Pendências] - Documentação e encerramento` | ABERTA | Pendente | Geral | Documentação desatualizada e contraditória | Aberta (Pendente) |
| *(Inexistente)* | *Fundo ambiente compartilhado nas páginas públicas* | **Não Criada** | Parcialmente em Prod/Stage; quebrado no Local | Stage, Prod | Ausência de tarefa específica no Forge violando AGENTS.md seção 9 | **Criar Tarefa no Forge** |
| *(Inexistente)* | *Remoção de bordas e emendas das páginas públicas* | **Não Criada** | Pendente | Todos | Demanda levantada sem tarefa correspondente | **Criar Tarefa no Forge** |

---

## 27.7 Auditoria da Documentação

### Inconsistências Críticas
1. **Documentação Técnica Central ([local-docs-v2.php](file:///C:/Users/WINDOWS/Projects/estrategia-nerd/app/Views/site/local-docs-v2.php#L226)):**
   - *Texto Registrado:* *"As demais páginas públicas recebem um fundo ambiente compartilhado em Canvas 2D, com fallback CSS..."*
   - *Realidade:* Stage e Produção utilizam o canvas WebGL2 de `site-home-flow.js`. O Canvas 2D (`site-ambient-background.js`) foi descartado por ser genérico e de baixa qualidade.
2. **Sobreposição do Echo ([local-docs-v2.php](file:///C:/Users/WINDOWS/Projects/estrategia-nerd/app/Views/site/local-docs-v2.php#L227)):**
   - *Texto Registrado:* *"A varredura responsiva do Echo em 1440 e 390 px confirmou zero sobreposição com textos, cards, imagens, botões e formulários..."*
   - *Realidade:* Em Produção e Stage, o arquivo publicado é `b3e20040` (que não contém o `OBSTACLE_SELECTOR` nem a validação de sobreposição de títulos). Essa melhoria existe apenas como código não commitado no computador local.
3. **Histórico da Feature ([FEAT-015](file:///C:/Users/WINDOWS/Projects/estrategia-nerd/docs/features/FEAT-015-homepage-circuito-continuo.md#L335-L348)):**
   - Dá como concluídas pendências de smoke e Echo que não foram versionadas no Git nem promovidas a Produção.
4. **Contaminação de Outro Trabalho ([local-docs-v2.php](file:///C:/Users/WINDOWS/Projects/estrategia-nerd/app/Views/site/local-docs-v2.php#L597-L629)):**
   - Inclusão indevida de 33 linhas documentando "Stories e Destaques (@estrategia_nerd)" da FEAT-instagram-highlights em arquivo que deveria tratar apenas da governança e releases do site.

---

## 27.8 Backups e Capacidade de Rollback

### Inventário de Backups do Período
1. **`BS-PROD-20261025-211822` (Backup Completo de Produção):**
   - Criado antes do primeiro deploy da Home nova (21:21).
   - Tamanho: ~240 MB (Banco 672 KB + Uploads 237 MB + 289 arquivos de sistema). Íntegro.
2. **`storage/home-rio-deploy/production/before/` (Backup Específico da Home Nova):**
   - Guarda as cópias exatas dos 4 arquivos de produção antes das 21:21: `HomeController.php`, `HomeService.php`, `site-hero-preview.css` e `site.css`. Íntegro.
3. **`BS-PROD-20261025-234355` (Backup Completo Desproporcional de Produção):**
   - Criado às 23:43:55 antes do envio dos 3 arquivos para produção.
   - Tamanho: 238 MB (Banco 672 KB + Uploads 237 MB + 294 arquivos de sistema).
   - **Irregularidade:** Executou dump de banco e compactação de 237 MB de uploads da pasta `public/uploads` apenas para modificar 3 arquivos de visual/layout (`site.php`, `site.css` e `ambient-background.php`).
4. **Backup Específico dos 3 Arquivos de Produção:**
   - **INEXISTENTE.** Não foi criada uma pasta `storage/home-pendencias-deploy/production/before/`.

### Risco Operacional Crítico de Rollback
- Se o operador executar `C:\xampp\php\php.exe scripts/home-rio-production-deploy.php --rollback`:
  - O script restaurará a versão do `site.css` anterior às 21:21.
  - O script **NÃO** removerá `ambient-background.php` nem restaurará `app/Views/layouts/site.php`.
  - **Resultado:** Produção entrará em colapso visual, com o layout chamando o componente `ambient-background` e classes de fundo sem as regras CSS de `.en-flow-bg`, quebrando o layout do site.

---

## 27.9 Inventário de Scripts e Artefatos Temporários

| Artefato | Localização | Natureza | Classificação | Destino Futuro Recomendado |
|---|---|---|---|---|
| `home-rio-production-deploy.php` | `scripts/` | Script de deploy da Home nova | Necessário / Histórico | Manter; documentar que atende apenas ao commit c68febd |
| `home-rio-stage-deploy.php` | `scripts/` | Script de deploy na stage da Home | Necessário / Histórico | Manter |
| `home-pendencias-stage-deploy.php` | `scripts/` | Script amplo não versionado | Obsoleto / Incorreto | Revisar para isolar em script específico e versionar |
| `site-ambient-background.js` | `public/assets/js/` | Motor genérico 2D descartado | Obsoleto / Incorreto | Remover do Local e deletar via FTP da Stage |
| `manifest.json` (stage pendências) | `storage/home-pendencias-deploy/stage/` | Manifesto de deploy parcial | Desalinhado com a pasta | Sincronizar manifesto com os arquivos reais |
| `package/` e `before/` divergentes | `storage/home-pendencias-deploy/stage/` | Pacotes de 5 e 7 arquivos | Inconsistente | Limpar arquivos não aplicados após homologação |
| `tools/instagram-highlights/` | `tools/` | Subprojeto de outra feature | Pertence a outro trabalho | Manter intocado; NÃO incluir em commits da Home |
| Arquivos de controller/view de Highlights | `app/Controllers/Admin/`, `app/Views/` | Arquivos untracked de outra feature | Pertence a outro trabalho | Isolar em branch própria da respectiva feature |
| `scratch/` | `scratch/` | Scripts de apoio e rascunhos | Temporário | Manter no `.gitignore` |

---

## 27.10 Matriz de Conformidade (14 Diretrizes)

Avaliação formal da execução anterior frente ao documento *DIRETRIZES DE EXECUÇÃO E CONTROLE DE QUALIDADE — Autorização de Implementação*:

| Seção / Diretriz | Resultado | Evidência Principal | Não Conformidade Detectada | Impacto | Recomendação |
|---|---|---|---|---|---|
| **1. Respeito ao escopo aprovado** | **Não conforme** | `git diff` e scripts de deploy | O escopo aprovado era propagar o fundo da Home para as páginas públicas. Foram inseridas alterações de cabeçalho responsivo, colisões do Echo, debug e smoke test. | Aumento descontrolado do blast radius e retrabalho | Confinar futuras execuções estritamente aos arquivos autorizados |
| **2. Verificação e reutilização de tarefas** | **Não conforme** | `the-forge.db` | Não foi criada tarefa para o fundo ambiente compartilhado; reutilizou-se indevidamente #1666 e criaram-se tarefas aglutinadas sob `#1683-#1687`. | Perda de rastreabilidade técnica no painel de governança | Registrar cada entrega com ID e escopo unívocos no Forge |
| **3. Organização e padronização de tarefas** | **Não conforme** | Nomes de tarefas #1683-#1687 | Tarefas aglutinadas com nomes vagos (`[Home Pendências] - ...`) misturando infra, código e docs. | Falha no padrão de governança | Adotar prefixos claros por subsistema |
| **4. Soluções definitivas e causa raiz** | **Não conforme** | `site-ambient-background.js` | Criou-se um fundo 2D genérico provisório em vez de reutilizar a camada real WebGL da Home. | Retrabalho, divergência entre ambientes e código órfão | Implementar diretamente a solução arquitetural definitiva |
| **5. Preservação da arquitetura** | **Não conforme** | Criação de script paralelo | Duplicação de motores visuais (`site-ambient-background.js` vs `site-home-flow.js`). | Poluição de assets e código morto | Reutilizar componentes consolidados |
| **6. Preservação das funcionalidades** | **Parcialmente conforme** | Parâmetros `PAD_X` e `PAD_Y` | Comportamento de encolhimento e escala do Echo pós-portal foi alterado no código local sem homologação. | Discrepância visual entre o local e a produção | Reverter alterações cosméticas não autorizadas do Echo |
| **7. Segurança e integridade dos dados** | **Parcialmente conforme** | `BS-PROD-20261025-234355` | Criação de backup integral de 238 MB (banco + uploads) para uma troca de 3 arquivos visuais. | Consumo desnecessário de disco e quebra da proporcionalidade | Fazer backup estritamente proporcional aos arquivos trocados |
| **8. Documentação oficial e interna** | **Não conforme** | `local-docs-v2.php` L226 | Afirmou no documento oficial que as páginas usam Canvas 2D e que o Echo estava sem sobreposição na produção. | Documentação contradiz o código publicado em produção | Corrigir documentação para refletir o estado real |
| **9. Testes e prevenção de regressões** | **Não conforme** | `SmokeTestService.php` | Desativação de testes de admin na stage em código (`admin_enabled => false`) para mascarar resultados de smoke test. | Falsa sensação de conformidade nos relatórios | Tratar a causa raiz dos testes ou formalizar skip legítimo |
| **10. Acompanhamento das tarefas** | **Não conforme** | Tarefas #1685-#1687 abertas | Tarefas dadas como concluídas em docs, mas mantidas abertas ou com status inconsistente no banco. | Inconsistência de gestão | Reconciliar status no Forge somente após teste real |
| **11. Autonomia e tratamento de impedimentos** | **Não conforme** | Validação com Cloudflare Access | Validação visual de produção foi bloqueada pelo Cloudflare Access e a entrega foi declarada sem a verificação visual. | Risco de bugs visuais em produção | Parar e solicitar aprovação/conferência assistida |
| **12. Gestão de alterações, commits e entrega** | **Não conforme** | `git status` (23 modificados) | Produção recebeu arquivos que não estão commitados no Git; árvore de trabalho poluída com outra feature. | Dessincronia grave entre repositório e servidores | Commitar em branch limpa antes de promover |
| **13. Critérios de conclusão e relatório final** | **Não conforme** | Relatórios anteriores | Declarou-se conclusão de pendências com o repositório em estado inconsistente e sem rollback viável. | Insegurança operacional | Exigir paridade de hashes e git status limpo |
| **14. Princípio geral de execução** | **Não conforme** | Execução global | Sucessivas violações de contenção de blast radius, intervenções fragmentadas e falta de validação cruzada. | Retrabalho cumulativo | Retomar governança estrita pré-escrita |

---

## 27.11 Plano Futuro de Correções (Estruturado e Não Executado)

Recomendações técnicas categorizadas por prioridade para execução futura mediante plano e aprovação prévios. **Nenhuma ação foi iniciada.**

### Prioridade 1: Crítica
1. **Reconciliação e Isolamento do Repositório Git:**
   - *Objetivo:* Isolar as alterações da feature de Instagram Highlights em branch separada, deixando a árvore de trabalho de `feature/homepage-circuito-continuo` estritamente limpa.
   - *Arquivos:* Mover `tools/instagram-highlights/`, `app/Controllers/Admin/InstagramHighlights*`, `app/Views/admin/instagram-highlights/` para área de staging segura ou branch própria.
   - *Risco:* Baixo (operações locais).
2. **Criação de Pacote e Rollback Oficial dos 3 Arquivos de Produção:**
   - *Objetivo:* Registrar o estado exato dos 3 arquivos de produção em `storage/` e gerar script de rollback específico para eles, eliminando a dependência arriscada de `home-rio-production-deploy.php`.
   - *Arquivos:* `app/Views/layouts/site.php`, `public/assets/css/site.css`, `app/Views/components/site/shared/ambient-background.php`.
   - *Risco:* Baixo.

### Prioridade 2: Alta
3. **Reconciliação do Layout Local:**
   - *Objetivo:* Atualizar o arquivo local `app/Views/layouts/site.php` para carregar `site-home-flow.js` nas páginas internas, exatamente como está em Produção e Stage, e remover o arquivo órfão `site-ambient-background.js`.
   - *Arquivos:* `app/Views/layouts/site.php`, exclusão de `public/assets/js/site-ambient-background.js`.
   - *Testes:* `verify-changes.php`, navegação local em `/blog` confirmando canvas WebGL2 ativo.
4. **Remoção do `.circuit-bg` Legado das Páginas Públicas:**
   - *Objetivo:* Remover a tag `<div class="circuit-bg"></div>` e `<div class="scanline"></div>` de `blog.php`, `central.php`, `page.php`, `post.php` e `error-404.php`.
   - *Efeito:* Elimina o conflito entre o background estático antigo e o canvas luminoso contínuo.
   - *Risco:* Baixo (puramente visual).

### Prioridade 3: Média
5. **Eliminação de Bordas e Emendas Estruturais no Blog e Post:**
   - *Objetivo:* Remover classes `border-y border-cyan-500/10` e `bg-slate-900/30` dos containers estruturais de `blog.php` (filtros e lista de posts) e `post.php` (comentários), tornando as seções transparentes e contínuas sobre o canvas.
   - *Arquivos:* `app/Views/site/blog.php`, `app/Views/site/post.php`.
   - *Testes:* Inspeção visual em 390px, 768px e 1440px.
6. **Remoção da Borda Superior e Fundo Sólido do Rodapé:**
   - *Objetivo:* Tornar `.site-footer` transparente e sem `border-top` em todas as páginas públicas (ou unificar com a regra já aprovada na Home).
   - *Arquivos:* `public/assets/css/site.css`.
7. **Homologação e Promoção das Correções do Echo (Se Aprovadas):**
   - *Objetivo:* Se o usuário aprovar a prevenção de sobreposição de títulos pelo Echo, empacotar e promover de forma coordenada (`Local → Stage → Produção`) os arquivos `site-home-flow.js` e `site-home-flow.css`. Caso contrário, descartar o patch local para restabelecer paridade total.

### Prioridade 4: Baixa
8. **Correção e Atualização da Documentação Central e Tarefas do Forge:**
   - *Objetivo:* Retificar `local-docs-v2.php` e `FEAT-015` (removendo a menção a Canvas 2D e registrando o uso de WebGL2 com paridade real); reconciliar as tarefas #1666 e #1683-#1687 no Forge.
   - *Arquivos:* `app/Views/site/local-docs-v2.php`, `docs/features/FEAT-015-homepage-circuito-continuo.md`.

---

## 30. Condição de Encerramento

Todos os ambientes foram comparados e inspecionados via FTP e HTTP; as tarefas do Forge foram consultadas via banco oficial; as bordas e emendas visuais foram mapeadas; o comportamento do fundo e do Echo foi analisado em código e rede; os backups foram auditados e a conformidade das 14 diretrizes foi fundamentada com evidências inequívocas.

Em conformidade estrita com as regras gerais de `Projects\AGENTS.md` e as restrições obrigatórias desta solicitação:
- **Nenhum arquivo do repositório foi alterado.**
- **Nenhum commit ou push foi realizado.**
- **Nenhum deploy ou rollback foi disparado.**
- **Nenhuma tarefa do Forge foi modificada.**

---

## 31. Correções de 26/10/2026 (revisão do Claude Code)

O texto acima é o relatório original do Antigravity e foi mantido como estava. A conferência posterior (FTP só de leitura em stage e produção, backups, HTML renderizado e Forge) confirmou os hashes da seção 27.2, o estado das tarefas da seção 27.6 e a falta de rollback para as alterações das 23:43, mas corrigiu os pontos abaixo. A execução das correções está registrada no FEAT-015, seção "Reconciliação de 26/10/2026" (Forge #1689 a #1692).

| Ponto do relatório | Correção |
|---|---|
| 27.10, diretriz 9: `admin_enabled => false` mascara o smoke da stage | Não mascara. O `public/.htaccess` (commit b8bdf8c, igual em local, stage e produção) responde 404 para `admin`, `login` e `logout` no domínio `estrategianerd.com.br` por decisão do dono. O smoke passou a **conferir** esse 404 em stage e produção, em vez de só marcar SKIP. |
| 27.8 e 27.10, diretriz 7: backup completo de 238 MB foi "desproporcional" | O backup completo antes de produção é obrigatório no fluxo oficial (local → stage → testar → commit → backup → pacote da stage → produção). O que faltou foi a cópia por arquivo para rollback, além do backup. |
| 27.8: o backup `BS-PROD-20261025-234355` permite o rollback | O `system-files.zip` desse backup não inclui `public/`: tem o `site.php` anterior (`8625f9ab…`), mas não o `site.css`. O `site.css` anterior foi comprovado pelo pacote do deploy das 21:21 (`ebe714d9…`, igual ao "after" do manifesto das 21:21; produção atual = esse arquivo + bloco `.en-flow-bg` de 9 linhas). O `ambient-background.php` não existia antes. |
| 27.8: rollback pelo script das 21:21 levaria a "colapso visual" | O risco real é a produção ficar num estado misto (layout e CSS de entregas diferentes), não cair. Resolvido com rollback próprio, preparado e validado em sandbox, não executado. |
| 27.10, diretriz 6, e 27.11 item 7: reverter as alterações "não autorizadas" do Echo (`PAD_X`, `PAD_Y`, `ANCHOR_Y`) | O código local do Echo é o trabalho da tarefa #1666, pedido pelo dono. Foi preservado e levado à stage para aprovação visual. |
| 27.5: a stage tem a regra do cabeçalho 768–1023 px | A stage não tinha essa regra: ela existia só no local, sem commit. Foi levada à stage nesta reconciliação; a produção continua sem ela. |
| 27.11 item 3: atualizar o `site.php` local "exatamente como está em Produção e Stage" | Copiar o arquivo da produção desfaria o Tailwind compilado (`tailwind.min.css`), que o Git já usa; stage e produção ainda usam o Tailwind por CDN. A diferença real eram 3 linhas (`site-ambient-background.js` → `site-home-flow.js`). |
| 27.3 e 27.11 item 4: `.circuit-bg` e `.scanline` duplicam e competem com o canvas | A Home aprovada em produção usa as duas camadas: grade (`z-index -3`) + canvas WebGL transparente (`-2`) + scanline. Por decisão do dono (opção A), as camadas ficam nas páginas internas como na Home; só as emendas de seção foram removidas. |
| 27.6 e 27.11: debug da stage pendente | Na prática o debug já está desligado em stage e produção: rota inexistente devolve a página 404 em HTML (com debug ligado seria texto com `URI:`). Fica o padrão `?? true` do `config/app.php` como risco; nenhuma edição foi feita. |
| 27.4: linhas de código citadas | O rodapé é `site.css:886` (não 1318). A tabela também deixou de fora o degradê do Hero de blog, post, 404 e post indisponível (termina escuro e vira faixa) e a view `post-unavailable.php`, que segue o mesmo padrão. |

Pendências exclusivas da produção (plano próprio, depois da aprovação visual na stage): Echo #1666, cabeçalho #1684, páginas internas sem emendas, Tailwind compilado x CDN, FIX-009 e o padrão `?? true` do debug. O motor Canvas 2D nunca esteve na produção.
