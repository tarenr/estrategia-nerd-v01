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
- **Aprovação:** Todos os 12 testes do `scripts/verify-changes.php` devem retornar `[PASS]`.
- **Reprovação:** Qualquer `[FAIL]` bloqueia o commit e exige correção imediata da causa raiz antes do prosseguimento.
