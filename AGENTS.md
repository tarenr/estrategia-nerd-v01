# Diretrizes e Convenções do Projeto — Estratégia Nerd

Este arquivo estabelece os padrões técnicos, regras arquiteturais e protocolos obrigatórios de desenvolvimento específicos do repositório **Estratégia Nerd**. Ele complementa as regras gerais de `C:\Users\WINDOWS\Projects\AGENTS.md`.

---

## 1. Arquitetura de Ambientes e Bancos de Dados

O painel administrativo do Estratégia Nerd opera com suporte a alternância dinâmica de **Ambiente Alvo** (`local`, `production`, `stage`). É fundamental diferenciar o escopo de cada módulo:

### A. Módulos Multi-Ambiente
Utilizam a conexão `$pdo` dinâmica provida por `\App\Support\TargetEnvironmentDatabase::pdo($targetEnvironment)`:
- **Blog & Conteúdo:** Posts, categorias, tags, comentários, upload de capas.
- **Métricas do Site:** Estatísticas de visualização, gráficos temporais.
- **Newsletter:** Lista de inscritos e disparos.
- **Links & Afiliados:** Links monitorados e contagem de cliques.

### B. Módulos de Ambiente Único (Local-Only)
Utilizam **estritamente a conexão local** (`$GLOBALS['pdo']`):
- **Instagram (FEAT-010):** A conta `@estrategia_nerd` é uma entidade unificada. Seus tokens da Meta Graph API v21.0, fila de agendamento, cron CLI (`scripts/en-instagram-publish-scheduled.php`) e cache de insights residem exclusivamente no MySQL local.
- **Regra:** Nunca injetar ou repassar o `$pdo` dinâmico do `TargetEnvironmentDatabase` para módulos locais.
- **Transição futura:** O Instagram só passará a ser multi-ambiente quando for desenvolvida a rotina de exibição pública de feed/cards no frontend do site em produção.

---

## 2. Código Defensivo e Degradação Graciosa (Mandatório)

Qualquer integração com serviços externos (Meta, APIs terceiras, helpers locais) ou módulos periféricos deve seguir o princípio de **isolamento de falhas**:

1. **Nunca causar Erro 500 no Core:** Se uma tabela não existir, o banco falhar ou uma API externa estiver offline, o módulo deve capturar a exceção (`try/catch (\Throwable $e)`), registrar o erro em log (`error_log`) e retornar um fallback gracioso (`null`, array vazio ou estado desconectado).
2. **O Dashboard Principal (`/admin`) NUNCA pode quebrar:** A tela de visão geral deve renderizar todas as informações vitais do blog mesmo que um dos módulos adicionais apresente problemas.

---

## 3. Protocolo Obrigatório de Testes Pós-Alteração

**NENHUMA alteração é considerada concluída ou pode ser commitada sem a execução prévia da suíte de verificação automatizada:**

```bash
C:\xampp\php\php.exe scripts/verify-changes.php
```

A suíte executa de forma encadeada e automatizada:
1. **Sintaxe e Linting (`php -l`):** Validação de todos os arquivos PHP modificados e rotas.
2. **Análise Estática (PHPStan Level 5):** Verificação rigorosa de tipagens e contratos com **zero erros tolerados**.
3. **Renderização Multi-Ambiente do Dashboard:** Simulação isolada do `/admin` nos 3 ambientes-alvo (`local`, `production`, `stage`), garantindo:
   - Status 200 e integridade do HTML (>70KB).
   - Ausência de `PDOException` ou erros de banco.
   - Carregamento correto dos KPIs e painéis.
4. **Renderização do Módulo Instagram:** Validação do endpoint `/admin/instagram`.

> **Critério de Aceitação:** O resultado deve ser estritamente `Status: APROVADO COM SUCESSO! (0 FALHAS)`. Se houver qualquer falha, a entrega é reprovada na hora.

---

## 4. Stack e Comandos Úteis

- **PHP:** 8.2+ (XAMPP em `C:\xampp\php\php.exe`)
- **Webserver:** Apache 2.4 (VirtualHost `nerd.tfr-info.com.br` / Alias `/estrategia-nerd`)
- **Estilo:** Tailwind CSS (utility-first) + FontAwesome 6
- **PHPStan:** `vendor/bin/phpstan analyse --level=5 --no-progress`
- **Suíte de Testes Geral:** `C:\xampp\php\php.exe scripts/verify-changes.php`
- **Smoke Tests:** `C:\xampp\php\php.exe scripts/smoke-test.php local`

## 5. Downloads de trilhas — aprendizado obrigatório (IMP-032)

Antes de retomar downloads de músicas do Pixabay, ler `docs/features/IMP-032.md`, especialmente "Erros observados e orientação para IAs". Não repetir o ciclo de abrir abas, clicar em Recarregar e pedir ao usuário cliques por faixa: a automação foi testada e não salvou novos MP3s. Não atribuir a causa a permissões de múltiplos downloads sem evidência. Confirmar sucesso pelo arquivo completo, duração, codec e hash; a tela de erro e o estado anterior do arquivo não comprovam o resultado da ação atual.

Nesta tarefa, a transferência HTTP do link público observado no botão do site funcionou sem cookies ou mudanças de segurança. Reutilizar esse procedimento apenas dentro de um escopo aprovado, preservar os arquivos existentes e interromper em caso de recusa do servidor. Nunca inventar links de CDN ou desativar proteções para forçar o download.
