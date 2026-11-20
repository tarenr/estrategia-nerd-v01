# FEAT-017 — Padrão de portas: Forge 4000 e Echo 5020

Entrega local de 08/11/2026, tarefas Forge #1971, #1972 e #1973 (projeto Infraestrutura Geral, id 8), e #1976 (projeto Estratégia Nerd, id 4). Continua a padronização registrada na tarefa #1967. Referência oficial: `the-forge/docs/padrao-portas-e-servicos.md`.

## O que mudou

- O The Forge é referenciado na porta **4000** e o Echo Companion na porta **5020**. As portas antigas 4477 e 4884 deixam de ser usadas.
- `scripts/en-links-check.php` (criação de tarefas do Estratégia Nerd no Forge) e `app/Views/site/local-backlog.php` (link para o Forge) apontam para a 4000. Antes, a criação de tarefas falhava na 4477.
- `app/Controllers/Admin/TechnicalCenterController.php` (texto que indica o painel local do Forge) e `scripts/operations/inserir-post-echo.php` (texto sobre a porta do Echo) citam as portas novas.

## Banco de dados

- Mudança de schema: não. Mudança de dados: não. Script necessário: nenhum.

## Verificação

- `scripts/verify-changes.php` em 08/11/2026: APROVADO COM SUCESSO, 37 verificações OK e 0 falhas. PHPStan nível 5 sem erros. Dashboard renderizado nos ambientes local, production e stage.
- `php -l` nos quatro arquivos alterados: sem erros. A suíte não inclui esses quatro arquivos, por isso a checagem foi feita à parte.
- `/local/backlog` e `/local/mudancas` respondem 200 em local.
- Não validado: as telas da Central Técnica exigem login, e o texto de `TechnicalCenterController.php` não foi conferido renderizado.

## Fora deste repositório

- The Forge (`.env`, `.env.example`, `iniciar.ps1`, `docs/plano-correcoes-2026-09.md`), Echo Companion (`bin/dispatcher.js`, `bin/approval-hook.js`, `README.md`) e Voice Studio (`backend/benchmark_echo_tts.py`) foram ajustados nos respectivos repositórios. Este documento não os descreve.

## Pendências

- A parte da Estratégia Nerd foi movida para a tarefa Forge #1976, no projeto 4 (Estratégia Nerd), e está concluída. A tarefa #1972 ficou só com o Voice Studio, no projeto 8, e segue aberta até ter teste técnico.
- Validação em stage e produção: não feita. Apto para release: não.

## Riscos

- Baixo. São URLs e textos. O script de criação de tarefas volta a funcionar após a correção.
