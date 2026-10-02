# Diretrizes de Qualidade, Governança e Testes Automatizados

> **Projeto:** Estratégia Nerd  
> **Versão:** 1.0.0  
> **Data:** 2026-09-29  
> **Autoridade:** Equipe Técnica & Governança  

---

## 1. Objetivo

Este documento define os padrões mandatórios de qualidade de código, resiliência arquitetural e processos de validação contínua para prevenir regressões, quebras em produção e erros em tempo de execução no portal e painel administrativo do **Estratégia Nerd**.

---

## 2. Princípio da Resiliência e Isolamento de Módulos

O painel administrativo consolida operações de diferentes subsistemas (blog editorial, newsletter, links de afiliados, integrações com redes sociais como Instagram, monitoramento e métricas).

Para garantir estabilidade ininterrupta:
1. **Falha Contida (Blast Radius Zero):** O colapso de uma dependência periférica (ex.: indisponibilidade da API do Instagram, queda do helper de GPU ou ausência de uma tabela externa) **não pode** derrubar a aplicação nem gerar tela de Erro 500 no Dashboard principal (`/admin`).
2. **Defensive Coding Obrigatório:**
   - Todos os pontos de integração externa ou chamadas a tabelas de módulos não essenciais devem estar contidos em blocos `try/catch (\Throwable $e)`.
   - O erro deve ser registrado em log (`error_log` ou logger específico) com mensagem clara.
   - O método consumidor deve fornecer um retorno padrão seguro (`null`, coleção vazia ou estado desconectado), permitindo a renderização do restante da página sem interrupções.

---

## 3. Gestão de Ambientes: Multi-Ambiente vs. Ambiente Único

O sistema adota uma divisão arquitetural estrita entre módulos que suportam alternância de ambiente e módulos centralizados:

### 3.1. Módulos Multi-Ambiente (Alvo Dinâmico)
- **Módulos:** Posts, Categorias, Comentários, Estatísticas do Blog, Newsletter, Links e Auditoria Editorial.
- **Conexão:** Utilizam `$pdo` fornecido por `\App\Support\TargetEnvironmentDatabase::pdo($targetEnvironment)`.
- **Comportamento:** Ao mudar o seletor no topo do painel, a leitura reflete os dados do banco correspondente (`local`, `production` ou `stage`).

### 3.2. Módulos de Ambiente Único (Estritamente Local)
- **Módulos:** Módulo Instagram (FEAT-010), agendador CLI cron, tokens de autenticação Meta.
- **Conexão:** Devem utilizar **exclusivamente** `$GLOBALS['pdo']` (banco de dados local `estrategia-nerd`).
- **Justificativa:** A conta do Instagram é única no mundo real. Os tokens de longa duração e o agendamento de postagens são processados pelo servidor local. Não há replicação dessas tabelas em bancos remotos (Hostinger) até que seja criada a rotina de exibição no frontend público.
- **Regra de Ouro:** É expressamente proibido passar conexões de `TargetEnvironmentDatabase` para repositórios de ambiente único.

---

## 4. Rotina Obrigatória de Testes Pós-Alteração

Todo desenvolvedor ou agente de IA que realize alterações no código do projeto é **obrigado** a executar a suíte de testes antes de submeter alterações ou criar commits:

```bash
C:\xampp\php\php.exe scripts/verify-changes.php
```

### 4.1. Camadas Validadas pelo Script
1. **Linting de Sintaxe (`php -l`):** Varre todos os arquivos alterados e arquivos centrais do sistema, garantindo ausência de erros léxicos ou sintáticos.
2. **Análise Estática (PHPStan Level 5):** Analisa tipagem estrita, existência de classes/métodos e contratos de retorno. Nível de exigência: **0 erros**.
3. **Simulação de Renderização Multi-Ambiente:**
   - Dispara em processos isolados a renderização completa do `DashboardController::index()` sob cada um dos 3 alvos: `local`, `production` e `stage`.
   - Valida status HTTP 200, geração completa do HTML (>70KB), presença de KPIs essenciais e ausência total de `PDOException` ou Fatal Errors.
4. **Integridade do Módulo Instagram:**
   - Renderiza a view administrativa de `/admin/instagram`, garantindo integridade de componentes, carregamento do perfil e ausência de warnings.

### 4.2. Critério de Aceitação
- **Aprovação:** Todos os testes do `scripts/verify-changes.php` devem retornar `[PASS]`.
- **Reprovação:** Qualquer `[FAIL]` bloqueia o commit e exige correção imediata da causa raiz antes do prosseguimento.

---

## 5. Pipeline de Compilação de Assets (Tailwind CSS)

Para garantir máxima performance, estabilidade e conformidade de segurança:
1. **Proibição do Play CDN em Produção:** O uso de `<script src="https://cdn.tailwindcss.com"></script>` é expressamente proibido nos layouts do portal (`admin.php` e `site.php`). Toda estilização utilitária deve ser compilada previamente para arquivo estático minificado.
2. **Localização do CSS Compilado:** O arquivo gerado deve ser salvo em `public/assets/css/tailwind.min.css`.
3. **Comando Oficial de Build:**
   ```bash
   npm run build:css
   ```
   Para modo contínuo durante desenvolvimento:
   ```bash
   npm run watch:css
   ```
4. **Preservação de Classes Dinâmicas (Safelist):** Ao criar novos componentes que utilizem classes utilitárias geradas dinamicamente (como tons de badges, variantes de gradientes de posts ou seletores dinâmicos), a classe ou seu padrão regex correspondente deve ser registrado na propriedade `safelist` do arquivo `tailwind.config.js`.

---

## 6. Governança de Banco de Dados e Runner de Migrations

Todas as alterações estruturais de banco de dados devem ser versionadas em arquivos SQL sequenciais e executadas através do runner oficial:

### 6.1. Estrutura de Arquivos
- Diretório de migrations: `database/migrations/`
- Padrão de nomenclatura: `NNNN_descricao_em_snake_case.sql` (ex.: `0001_baseline_schema.sql`, `0002_create_instagram_tables.sql`).

### 6.2. Comandos do Runner
```bash
# Verificar status das migrations (sem aplicar)
C:\xampp\php\php.exe scripts/migrate.php --status

# Executar migrations pendentes no ambiente local
C:\xampp\php\php.exe scripts/migrate.php

# Executar ou verificar em ambientes remotos
C:\xampp\php\php.exe scripts/migrate.php --target=stage
C:\xampp\php\php.exe scripts/migrate.php --target=production
```

### 6.3. Regras Mandatórias de Idempotência e Segurança
1. **Idempotência Obrigatória:** Cada comando SQL em uma migration deve ser idempotente (`CREATE TABLE IF NOT EXISTS`, etc.).
2. **Proibição de Comandos Destrutivos:** É estritamente proibido o uso de `DROP TABLE`, `DROP DATABASE` ou `TRUNCATE` em migrations de release/produção sem autorização e plano específico.
3. **Rastreabilidade:** Cada execução registra o arquivo e o número do lote (batch) na tabela `migrations` do banco alvo.

---

## 7. Padrões de Mídia e Vídeo para Instagram Reels

Para assegurar compatibilidade universal com navegadores (Chromium/Direct3D 11, Safari, Firefox), players móveis e a Graph API do Instagram:

### 7.1. Especificações Técnicas Obrigatórias de Vídeo (FFmpeg)
- **Container:** MP4 (ISO/IEC 14496-14) com `movflags +faststart` (átomo `moov` no início do arquivo).
- **Codec de Vídeo:** H.264 (AVC High Profile, Level 4.0), 30 fps progressivo.
- **Espaço de Cores e Faixa (Mandatório):**
  - Espaço de cores: Rec.709 (`-colorspace bt709 -color_primaries bt709 -color_trc bt709`)
  - Faixa dinâmica: TV/Limited Range 16-235 (`-color_range tv`)
  - Formato de pixel: `pix_fmt yuv420p` (proibido o uso de `yuvj420p` ou full range `pc` em saídas H.264 para web)
- **Codec de Áudio:** AAC-LC estéreo, 128 kbps, taxa de amostragem 48.000 Hz com fade-out gradual de 1.5s ao final.
- **Dimensões:** 1080x1920 (proporção 9:16 vertical).

### 7.2. Resolução de URLs e Preview no Painel Administrativo
- Mídias locais devem sempre ser resolvidas de forma dinâmica utilizando `url('/' . ltrim($caminho, '/'))` nas views e endpoints administrativos para garantir correspondência exata com o domínio/host/porta em que o operador estiver autenticado (localhost, virtualhost ou túnel Cloudflare).
- Elementos `<video>` nos previews interativos devem possuir atributos `controls`, `playsinline`, `muted` e suporte a renderização nativa imediata no HTML inicial de edição e detalhes.

### 7.3. Geração Automática de Miniaturas (Posters JPG) para Reels e Vídeos
- Todo Reel gerado via `AudioReelGeneratorService` extrai e salva automaticamente uma miniatura `.jpg` no mesmo diretório com o mesmo nome base via FFmpeg (`-ss 00:00:00.500 -vframes 1 -q:v 2`).
- A função de resolução de mídia `$resolveMediaUrl` inspeciona extensões `.mp4` locais e serve o poster `.jpg` correspondente em tags `<img>` de listagens, cards e tabelas, eliminando telas pretas ou ícones quebrados.
- Na tela de detalhes do post (`show.php`), vídeos MP4 são renderizados nativamente em players HTML5 com controles completos e áudio.

---

## 8. Padrões de Navegação Fluida e Filtros Assíncronos (AJAX/Fetch)

Para proporcionar uma experiência rápida e moderna sem recarregamento de página:
1. **Atualização Assíncrona com Fragmentos (`_partial=1`):** Formulários de filtro, paginação e cabeçalhos de ordenação interceptam eventos de navegação, consultam o endpoint com o cabeçalho `X-Requested-With: XMLHttpRequest` e o parâmetro `_partial=1`, e substituem atomicamente o elemento raiz (`[data-ig-posts-root]`).
2. **URLs Amigáveis (`history.pushState`):** O histórico de navegação do browser é mantido perfeitamente atualizado com `pushState`/`replaceState`, limpando parâmetros vazios e padrões (`period=7d`, `sort=publicado_em`, `dir=desc`, `per_page=8`, `view=table`).
3. **Busca em Tempo Real com Debounce:** O campo de busca textual executa requisições automáticas com debounce de 350ms, dispensando cliques manuais em botões de envio.
4. **Legendas e Símbolos Padronizados:** Tabelas com indicadores de engajamento (curtidas, comentários, views) utilizam ícones visuais nos cabeçalhos (`<i class="fa-solid fa-heart"></i>`, `<i class="fa-solid fa-comment"></i>`) acompanhados de barra de legenda explicativa padronizada acima da tabela.
5. **Unificação Editorial:** As publicações, agendamentos e rascunhos coexistem na aba principal «Posts», permitindo segmentação rápida através dos filtros de «Status», «Origem» e «Período».

---

## 9. Governança da Grade Coordenada de Publicação (Instagram & Blog)

Para assegurar coesão editorial e máxima tração cruzada sem riscos de links quebrados:

### 9.1. Regra de Ouro da Sincronização Cruzada
1. **Prioridade do Blog:** Nenhum post do Instagram sobre um artigo novo pode ser publicado antes do artigo estar no ar no blog de produção.
2. **Grade Oficial:**
   - **Blog:** Publica às terças e sextas-feiras às 09:00 (horário de Brasília).
   - **Instagram (Artigos Novos):** O Reel correspondente é agendado rigorosamente para as **19:30 do mesmo dia** em que o artigo entra no ar no blog. Isso garante que qualquer chamada "link na bio" ou busca pelo tema no blog encontre o conteúdo ativo.
   - **Instagram (Acervo Publicado):** Os Reels de artigos que já estão no ar são distribuídos nos **dias livres** (segundas, quartas, quintas, sábados e domingos às 19:30), preenchendo o calendário sem conflito com as estreias do blog.
### 9.2. Ferramental CLI de Automação
- **Sincronizador de Reels do Blog (`scripts/en-instagram-sync-blog-reels.php`):**
  - Suporta a flag `--include-scheduled` para gerar antecipadamente o Smart Canvas 9:16, o áudio por categoria, a legenda e a miniatura para os posts agendados do blog.
- **Distribuidor da Grade (`scripts/en-instagram-distribute-schedule.php`):**
  - Suporta `--dry-run` para simulação visual da grade cronológica de 30 dias.
  - Suporta `--force` para reaplicação ou reordenação idempotente.
  - Atualiza as datas em `instagram_posts` marcando o status como `agendado` e vinculando com precisão de segundo (`19:30:00`).

### 9.3. Padrão Visual do Painel de Agendamento (`/admin/agendamento-posts`)
- **Cards Retangulares com Miniaturas:** Todos os eventos (Blog e Instagram) compartilham o formato retangular (`rounded-xl` com padding consistente), eliminando distorções ovais (`.status-badge` com `border-radius: 9999px` não deve ser aplicada aos cards de calendário).
- **Miniaturas de Capa:** Cada card renderiza uma miniatura quadrada (`w-9 h-9` ou `w-12 h-12`) no lado direito, exibindo a capa do post do blog ou o poster JPG do Reel do Instagram.
- **Diferenciação Cromática:**
  * **Blog:** Fundo azul profundo (`bg-[#0f1d38]/95`), borda azul sutil (`border-blue-500/40`), ícone de documento e horário em azul claro.
  * **Instagram:** Fundo magenta profundo (`bg-[#241228]/95`), borda rosa sutil (`border-pink-500/40`), ícone de câmera e horário em rosa claro.
- **Sanitização de Títulos:** Títulos do blog são desprovidos de marcações wiki `[[` e `]]` na camada de serviço antes da exibição no calendário.
- **Ações Rápidas:** Cada card possui menu de contexto com atalho direto de edição, além de navegação temporal com atalho "Hoje", seletor rápido de ambiente e botão "+ Adicionar agendamento" com opções dedicadas para Blog e Instagram.



