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

## Documentação central e tarefas

A documentação oficial fica na Central Técnica do admin (Base de Conhecimento). Toda alteração do sistema a atualiza na mesma tarefa, seguindo [docs/governanca/documentacao-central.md](docs/governanca/documentacao-central.md). Tarefas ficam somente no Forge (projeto Estratégia Nerd); itens de baixa prioridade ficam em [docs/governanca/backlog-baixa-prioridade.md](docs/governanca/backlog-baixa-prioridade.md).

---

## Diretrizes de Desenvolvimento
Consulte [AGENTS.md](file:///C:/Users/WINDOWS/Projects/estrategia-nerd/AGENTS.md) e [docs/governanca/DIRETRIZES-DE-QUALIDADE-E-TESTES.md](file:///C:/Users/WINDOWS/Projects/estrategia-nerd/docs/governanca/DIRETRIZES-DE-QUALIDADE-E-TESTES.md) para convenções de ambiente, código defensivo e regras mandatórias.

---

## Integrações e Serviços

- **Cloudflare Tunnel:** Acesso externo seguro (`https://nerd.tfr-info.com.br`) com proteção de borda.
- **Hostinger API:** Integração de hospedagem, domínios e zonas DNS.
- **Matomo Analytics:** Monitoramento auto-hospedado de tráfego, audiência e métricas do portal (`MATOMO_URL`).
- **Google Drive API:** Destino de nuvem do backup diário (`EstrategiaNerd-BackupDiario`, IMP-023); o Dropbox foi descontinuado e ficou como legado. Prova de restauração sem tocar nos ambientes: `C:\xampp\php\php.exe scripts/verify-backup-restore.php <local|stage|production>`.
- **Reels do Instagram:** publicação agendada a cada 5 minutos (`EstrategiaNerd-InstagramPublicarAgendados`) e Reels em tipografia cinética produzidos com a skill motionmaxxing ([docs/features/reels-motionmaxxing.md](docs/features/reels-motionmaxxing.md)).
- **Central Nerd e afiliados:** ofertas em `/central-nerd`; cliques por `/link/{slug}?origem=...` (`link_clicks`). O fim de cada post mostra 3 ofertas da categoria do post, sorteadas a cada visita, com origem `post-{id}`, e origens fora do padrão (scanners) não são contadas ([docs/features/FEAT-013-ofertas-nos-posts.md](docs/features/FEAT-013-ofertas-nos-posts.md)).
- **Meta / Instagram Graph API:** Automação de publicação de mídias e instagram cards (`/admin/instagram`).
- **FTP Deploy / Sync:** Sincronização de conteúdo e deploy entre ambientes Local, Stage e Produção.

