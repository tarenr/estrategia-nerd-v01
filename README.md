# Estratégia Nerd (PHP puro)

Portal editorial, conversão de afiliados e painel administrativo do **Estratégia Nerd**.

## Rodar Localmente
Acesse no navegador:
- Painel Administrativo: `http://localhost/estrategia-nerd/admin` (ou `http://nerd.tfr-info.com.br/admin`)
- Portal Público: `http://localhost/estrategia-nerd/public/`

---

## Validação e Qualidade (Obrigatório)

Antes de commitar ou concluir qualquer alteração no código, **é obrigatório rodar a suíte de verificação**:

```bash
C:\xampp\php\php.exe scripts/verify-changes.php
```

Esta rotina valida:
1. Linting de sintaxe PHP (`php -l`)
2. Análise estática de tipos (**PHPStan Level 5**, 0 erros tolerados)
3. Renderização multi-ambiente do Dashboard Admin (`local`, `production` e `stage`)
4. Integridade do módulo Instagram (`/admin/instagram`)

### Análise Estática Isolada
```bash
C:\xampp\php\php.exe vendor/bin/phpstan analyse --level=5 --no-progress
```

### Preflight e Smoke Tests
```bash
C:\xampp\php\php.exe scripts/preflight-check.php
C:\xampp\php\php.exe scripts/smoke-test.php local
```

---

## Estrutura de Pastas
- `app/` — Código-fonte da aplicação (Controllers, Services, Repositories, Support)
- `config/` — Configurações da aplicação, rotas, banco e testes
- `database/` — Schemas SQL e histórico de migrations
- `docs/` — Documentação técnica, governança e features
- `public/` — Raiz pública servida pelo Apache
- `scripts/` — Scripts CLI operacionais, crons e suítes de testes
- `storage/` — Logs, sessões e arquivos temporários

---

## Diretrizes de Desenvolvimento
Consulte [AGENTS.md](file:///C:/Users/WINDOWS/Projects/estrategia-nerd/AGENTS.md) e [docs/governanca/DIRETRIZES-DE-QUALIDADE-E-TESTES.md](file:///C:/Users/WINDOWS/Projects/estrategia-nerd/docs/governanca/DIRETRIZES-DE-QUALIDADE-E-TESTES.md) para convenções de ambiente, código defensivo e regras mandatórias.
