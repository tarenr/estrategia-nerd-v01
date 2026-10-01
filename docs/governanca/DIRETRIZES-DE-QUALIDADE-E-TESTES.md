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

