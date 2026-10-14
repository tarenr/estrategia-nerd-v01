# Backlog de baixa prioridade — para avaliar depois

> **Origem:** a antiga página "Backlog" da Central Técnica (`app/Views/site/local-backlog.php`, abril/2026), com 44 tarefas em 5 fases e 20 da trilha complementar de mídia e editor. Ela não tinha status e as tarefas nunca foram acompanhadas no Forge.
> **Verificação:** feita em 13/10/2026 contra o código, os commits, os documentos e o Forge (tarefa Forge #1252). Cada item tem a evidência encontrada.
> **Regra:** tarefas ativas ficam **somente no Forge** (projeto Estratégia Nerd). Para trabalhar em qualquer item daqui, crie a tarefa no Forge com prefixo e remova o item desta lista. Ver `docs/governanca/documentacao-central.md`.

## 1. O que saiu desta lista e foi para o Forge (necessário)

| Item antigo | Virou |
|---|---|
| F1-T10 — restore de validação | Forge #1258: `scripts/verify-backup-restore.php` (backups local e produção restauráveis em 13/10/2026) |
| F5-T08 / F2-T04 — documentação central e rotas documentadas | Forge #1252 a #1257 (`[Docs]`) |
| F5-T07 — revisão mensal | Forge #1260 (recriada todo mês) |
| Achado: rota `POST /admin/limpar-post-imagens-orfas` sem método | Forge #1259 (rota removida) |

## 2. Já feitas (nada a fazer; registradas para histórico)

| Item | Evidência |
|---|---|
| F1-T05 proteção `/admin` e `/local` | `auth` + `capability` em todas as rotas admin; `ensureLocalOnly`/`EnvironmentGuard`; público redireciona ao Cloudflare Access |
| F1-T06 `.env` e uploads | `.env` fora de `public/`; `public/uploads/.htaccess` bloqueia PHP |
| F1-T07 CSRF | todas as rotas POST existentes validam `Csrf::validate` |
| F1-T08 variáveis de ambiente | `APP_DEBUG` falso por padrão (`config/app.php`); Forge #420 |
| F1-T09 fluxo de deploy | `docs/releases/RELEASE-2026-10-08-*`, IMP-025 |
| F2-T03 padrão CRUD | index/create/store/edit/update/deleteConfirm/destroy nos módulos |
| F3-T02 componentes reutilizáveis | `app/Views/components/admin/*` e `components/admin/v2` |
| F3-T05 saúde/status | `/admin/health`, observabilidade da Central Operacional V2 |
| F3-T06 navegação por módulos | menu lateral agrupado |
| F4-T01 / F4-T05 fluxo do post e pré-validação | checklist do editor bloqueia agendamento com erro crítico |
| F4-T02 slug | `post_slug_history` + regra de colisão do sync |
| F4-T03 mídias órfãs | rotas de limpeza em Mídia e Posts |
| F4-T04 upload | `MidiaService` valida tipo, tamanho e extensão |
| F5-T03 trilha de ações sensíveis | `AuditService`, `/admin/auditoria-geral` |
| F5-T05 rastreio de deploy/sync | `package_id` (`ContentSyncAdminService`), "Últimas aplicações" |
| F5-T06 rastreio de backup | manifesto, `daily-backup-*.log`, IMP-023 |
| F5-T09 suíte de testes | `verify-changes.php`, `smoke-test.php`, scripts `verify-*` |
| F1-T01 controllers sem SQL | 0 de 37 controllers acessam o banco (sem o relatório formal) |
| MID-UP-01 / MID-UP-02 / MID-UP-03 | biblioteca aceita áudio/vídeo, filtro por tipo (`components/admin/media/filters.php`), uso por post (`collectMediaUsage`) |
| EDT-03 / EDT-04 | bloco de áudio no editor e cópia de mídia da biblioteca (`/admin/copiar-post-midia-biblioteca`) |
| FRT-AUD-01 a 03 | `en-audio-block` (HTML), `site.css`, `criar-post.js`/`site-home.js` |
| CM-01 | CodeMirror 6 em `public/assets/js/admin-post-html-editor.js` |

## 3. Baixa prioridade (avaliar depois)

| Item | Situação encontrada | Por que é baixa prioridade |
|---|---|---|
| F1-T02 / F1-T03 / F2-T06 — SQL fora dos repositories | 13 dos 54 services consultam o banco direto | refatoração grande e arriscada; **regra para código novo** em Regras de Negócio (quem mexer move o SQL) |
| F2-T01 / F2-T02 — nomes fora do padrão | 9 classes sem sufixo (`KonvaReelRenderer`, `InstagramFeedPublisher`, `OperationsV2Presenter`, exceções…) | os sufixos atuais descrevem o papel; a regra passou a aceitar `Renderer`, `Publisher`, `Presenter`, `Exception` |
| F1-T04 — views sem lógica pesada | sem evidência (não mensurável por busca) | revisão manual de baixo ganho |
| F2-T05 — consolidar componentes | componentes existem; remoção de duplicação não comprovada | arrumação |
| F2-T07 — template de módulo novo | não existe (só templates de feature/release) | útil para equipe; operação é solo |
| F3-T01 — mapa do dashboard | admin V2 existe; mapa não | documental |
| F3-T03 — padrão de feedback das operações | sem evidência | cosmético |
| F3-T04 — destacar ações críticas | existem na Central V2; validação de navegação não registrada | já atende na prática |
| F3-T07 — estrutura para módulos novos | sem evidência | sem demanda |
| F4-T06 — análise de preview seguro | preview existe no editor e em `/home-preview` | já atende na prática |
| F5-T01 / F5-T02 — mapa de logs e modelo de falha | logs por fluxo existem; mapa/modelo não | formalização |
| F5-T04 — padrão de mensagens de erro | sem evidência | cosmético |
| MID-BD-01 — mídia como entidade tipada no banco | tipos existem no editor e nas pastas; não há tabela de mídia | funciona por arquivos |
| MID-BD-02 — estrutura física por tipo | `posts/{slug}/images` comprovado; áudio/vídeo não conferido | parcial |
| MID-BD-03 / FRT-AUD-04 — compatibilidade com blocos antigos | sem evidência | só se aparecer post quebrado |
| EDT-01 / EDT-02 — botão salvar na barra e reorganizar toolbar | sem evidência | conforto do editor |
| CM-02 / CM-03 — sincronização visual↔HTML e reabertura de blocos especiais | sem teste registrado | validar se surgir problema |
| CHK-01 a 03 — checklist e pacote da trilha de áudio | sem evidência | a trilha já está em uso |

## 4. Decisão pendente do usuário

| Item | Pergunta |
|---|---|
| F4-T07 — integração dos posts com a Central Nerd | Nenhum commit nessa área desde maio. A Central Nerd (página de conversão) ainda é estratégica? Se sim, vira tarefa no Forge para revisar os links de saída dos posts; se não, sai desta lista. |
