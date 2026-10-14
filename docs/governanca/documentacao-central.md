# Documentação central do Estratégia Nerd — guia obrigatório para qualquer IA ou pessoa

> **Vale para:** Claude Code, Codex, OpenCode, Antigravity, Gemini e qualquer outro agente, e para o próprio usuário.
> **Criado em:** 13/10/2026 (tarefa Forge #1253). **Revisão:** mensal (ver seção 5).

## 1. Regra principal

**Toda alteração do sistema atualiza a documentação central na mesma tarefa.** Alteração sem a documentação correspondente atualizada **não está concluída** — nem o código, nem a tarefa no Forge.

"Documentação central" é o que aparece no admin em **Central Técnica → Base de Conhecimento** (`/admin/central-tecnica/base-conhecimento`). Criar um arquivo `.md` em `docs/features/` registra a entrega, mas **não substitui** atualizar as páginas centrais afetadas (Documentação Técnica, Regras, Procedimentos, Padrões, FAQ).

## 2. Onde fica cada área (fonte que o sistema lê)

| Área no admin | Arquivo-fonte | O que vai lá | Validar em |
|---|---|---|---|
| Visão Geral | montada por `TechnicalCenterController::knowledgeViewModel` | contadores automáticos; só editar o texto fixo dos cartões | `/admin/central-tecnica/base-conhecimento` |
| Backlog | `app/Views/site/local-backlog.php` | **não guarda tarefas**: aponta para o Forge e para `docs/governanca/backlog-baixa-prioridade.md` | `/local/backlog` |
| Documentação Técnica | `app/Views/site/local-docs-v2.php` | arquitetura, stack, rotas por módulo, segurança, deploy, backup, `.env` (só nomes), Instagram/Reels, erros comuns, evolução | `/local/docs` |
| Regras de Negócio | `docs/CODEX-REGRAS-PERMANENTES.txt` | regras permanentes e bloqueios | `/local/regras` |
| Histórico de Mudanças | `docs/features/*.md` e `docs/releases/*.md` (listados automaticamente) | um documento por entrega (modelo em `docs/templates/FEATURE-TEMPLATE.md`) | `/local/mudancas` |
| Procedimentos / Padrões / FAQ | cartões em `TechnicalCenterController::plannedSectionContent()` | passo a passo operacional, padrões, perguntas frequentes | admin (render da view; exige login) |
| Estruturas de Posts | `docs/blog-estruturas-de-conteudo.html` + cartões | blocos do editor de posts | `/local/blog-estruturas` |
| Resumo do repositório | `README.md` | visão geral, comandos e integrações | — |
| Regras para agentes | `AGENTS.md` | convenções obrigatórias do repositório | — |

As rotas `/local/*` só abrem no próprio PC (`ensureLocalOnly`) e, pelo endereço público, passam pelo login do Cloudflare Access.

## 3. Quando atualizar o quê

| Tipo de alteração | Atualizar |
|---|---|
| Rota nova/removida, módulo novo | Documentação Técnica (tabela de rotas por módulo) + Histórico |
| Variável de ambiente nova | Documentação Técnica, seção `.env` (só o **nome** e a finalidade; nunca o valor) |
| Fluxo operacional (backup, deploy, publicação, troca de mídia) | Documentação Técnica + Procedimentos + Histórico |
| Regra que não pode ser quebrada | Regras de Negócio |
| Padrão visual ou de código | Padrões e Boas Práticas |
| Problema recorrente e sua solução | Documentação Técnica (Erros comuns) + FAQ |
| Qualquer entrega | Histórico (`docs/features/`) e a tarefa no Forge |

## 4. Como validar antes de concluir

1. `C:\xampp\php\php.exe scripts/verify-changes.php` → precisa terminar em `APROVADO COM SUCESSO! (0 FALHAS)`.
2. Abrir as rotas `/local/*` afetadas e conferir o texto **renderizado** (não só o arquivo).
3. Para Procedimentos/Padrões/FAQ (só no admin, com login): renderizar a view localmente ou abrir no admin logado.
4. Conferir que nada antigo e contraditório ficou na página.
5. Marcar a tarefa no Forge como concluída só depois disso.

## 5. Tarefas: somente no Forge

- Tarefas ativas ficam **somente** no Forge (`http://127.0.0.1:4477`, projeto **Estratégia Nerd**, id 4), com prefixo `[Assunto] - Descrição` (ver `the-forge/docs/integracao-outros-projetos.md`). Use `curl.exe` com o corpo JSON em arquivo UTF-8 para não corromper acentos.
- Itens de **baixa prioridade** ficam em `docs/governanca/backlog-baixa-prioridade.md` até alguém decidir trazê-los para o Forge.
- **Revisão mensal:** existe sempre uma tarefa `[Docs] - Revisão mensal da documentação central (próxima: DD/MM/AAAA)`. Ao executá-la: listar os commits do mês (`git log --since=...`), conferir se cada mudança relevante aparece nas áreas da seção 2, corrigir o que faltar, concluir a tarefa e **criar a do mês seguinte**.

## 6. Ferramentas de verificação úteis

| Ferramenta | Para quê |
|---|---|
| `scripts/verify-changes.php` | suíte obrigatória (lint, PHPStan nível 5, dashboard nos 3 ambientes, Instagram) |
| `scripts/smoke-test.php local` | smoke das rotas principais |
| `scripts/verify-backup-restore.php <local\|stage\|production> [ID] [--skip-uploads]` | prova que um backup é restaurável sem tocar nos ambientes reais (checksums, CRC de todos os arquivos dos zips e importação do banco em um banco temporário que é apagado no fim) |
| `scripts/verify-reel-cover.php` e demais `verify-*` | verificações específicas por módulo |
