# Pipeline de Assets e Runner de Migrations

> **Projeto:** Estratégia Nerd  
> **Área:** Engenharia de Infraestrutura e Governança Técnica  
> **Status:** Ativo  
> **Versão:** 1.0.0 (2026-10-01)  

---

## 1. Visão Geral

Este documento detalha o funcionamento, configuração e manutenção de dois pilares de infraestrutura e hardening do portal **Estratégia Nerd**:
1. **Pipeline de Assets Estáticos:** Compilação do Tailwind CSS e bundles JavaScript, eliminando dependências de CDNs externas em tempo de execução.
2. **Runner de Migrations:** Sistema idempotente de controle de versão de schema do banco de dados (MySQL/MariaDB).

---

## 2. Pipeline de Assets Estáticos

### 2.1. Objetivos do Hardening
- **Segurança:** Bloqueio de CDNs de terceiros no frontend para mitigar riscos de supply-chain e permitir Content Security Policy (CSP) restritiva sem `unsafe-inline` ou `unsafe-eval`.
- **Performance:** Eliminação do tempo de compilação JIT no navegador do cliente (Tailwind Play CDN), reduzindo o Cumulative Layout Shift (CLS) e tempo de First Contentful Paint (FCP).
- **Offline / Resiliência:** O portal e painel administrativo funcionam integralmente mesmo sem conexão à internet externa para assets.

### 2.2. Estrutura de Arquivos
- **Configuração do Tailwind:** `tailwind.config.js`
- **Entrada CSS:** `resources/css/tailwind.css`
- **Saída Minificada:** `public/assets/css/tailwind.min.css`
- **Editor HTML do Admin:** `scripts/build-admin-html-editor.mjs` -> `public/assets/vendor/codemirror/admin-html-editor.bundle.js`

### 2.3. Comandos de Build
```bash
# Compilar todo o projeto (CSS + Bundles JS)
npm run build

# Compilar exclusivamente o Tailwind CSS minificado
npm run build:css

# Modo observador durante desenvolvimento (recompilação sob demanda)
npm run watch:css

# Compilar o bundle CodeMirror do editor HTML do admin
npm run build:admin-html-editor
```

### 2.4. Inclusão nos Layouts
Nos layouts principais (`app/Views/layouts/admin.php` e `app/Views/layouts/site.php`), os assets são referenciados via helper `asset()`:
```html
<link rel="stylesheet" href="<?= asset('assets/css/tailwind.min.css') ?>">
```

### 2.5. Tratamento de Classes Dinâmicas (Safelist)
Classes utilitárias geradas de forma dinâmica (como badges por status, tons de cards de posts e alertas contextuais) estão mapeadas na chave `safelist` do arquivo `tailwind.config.js`, garantindo que não sejam descartadas pelo tree-shaking do PurgeCSS.

---

## 3. Runner de Migrations

### 3.1. Arquitetura
O runner de migrations (`scripts/migrate.php`) gerencia as alterações estruturais de banco de dados nos ambientes `local`, `stage` e `production`.

- **Tabela de Controle:** `migrations`
  - `id`: Identificador sequencial
  - `migration`: Nome do arquivo executado (único)
  - `batch`: Número do lote da execução
  - `executed_at`: Timestamp da aplicação

### 3.2. Estrutura de Diretórios
```text
database/
  ├── migrations/
  │   ├── 0001_baseline_schema.sql
  │   ├── 0002_create_instagram_tables.sql
  │   └── ...
  └── schema.sql
```

### 3.3. Comandos do Runner
```bash
# Verificar o status das migrations em ambiente local
C:\xampp\php\php.exe scripts/migrate.php --status

# Executar todas as migrations pendentes no ambiente local
C:\xampp\php\php.exe scripts/migrate.php

# Executar ou verificar em ambientes remotos
C:\xampp\php\php.exe scripts/migrate.php --target=stage
C:\xampp\php\php.exe scripts/migrate.php --target=production
```

### 3.4. Diretrizes para Novas Migrations
1. **Nomeação:** Sempre utilize o prefixo numérico de 4 dígitos em ordem crescente (`0003_nome_da_migration.sql`).
2. **Idempotência:** Utilize sempre `CREATE TABLE IF NOT EXISTS`, `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` ou construções que não causem quebras se executadas novamente.
3. **Não Destrutivo:** Nunca inclua comandos `DROP TABLE`, `DROP COLUMN` ou `TRUNCATE` em migrations sem aprovação explícita e plano de contingência.
4. **Encoding:** Todos os arquivos de migration devem estar salvos em codificação UTF-8 sem BOM (Byte Order Mark).
