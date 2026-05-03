Release: RELEASE-2026-05-03-dashboard-admin
Data: 2026-05-03
Responsavel: Taren Felipe Ribeiro

Entram:
- HOT-002 - Corrige cache do JS do dashboard admin
- HOT-003 - Corrige leitura dos filtros de data no dashboard

Ficam fora:
- Mudancas de banco
- Mudancas de schema
- Deploy editorial
- Alteracoes fora do dashboard/admin

Origem validada: estrategia-nerd-stage
Paridade local -> stage: validada
Paridade stage -> pacote: validada

Backup necessario: sim
Backup usado:
- BS-PROD-20260501-113019

Banco precisa rodar script: nao
Mudanca de schema: nao
Mudanca de dados: nao

Rotas criticas afetadas:
- /admin

Arquivos principais:
- app/Controllers/Admin/DashboardController.php
- app/Services/Admin/DashboardService.php
- app/Views/admin/dashboard.php
- app/Views/layouts/admin.php
- public/assets/js/admin-dashboard.js
- public/assets/css/admin.css

Pacotes aplicados:
- HOT-003-STAGE-PROD-DASHBOARD-20260503-192117
- HOT-003-STAGE-PROD-DASHBOARD-URL-20260503-193719
- HOT-003-STAGE-PROD-ADMIN-CSS-20260503-194458
- HOT-003-STAGE-PROD-DASHBOARD-JS-20260503-201030

Commit local:
- 27684a2 Corrige filtros do dashboard admin

Checklist pre-deploy usado:
- /docs/checklists/pre-deploy.md

Checklist pos-deploy usado:
- /docs/checklists/post-deploy.md

Rollback associado:
- Restaurar backups gerados em storage/production-dashboard-hotfix-backups
- Restaurar backups gerados em storage/production-dashboard-url-hotfix-backups
- Restaurar backups gerados em storage/production-admin-css-backups
- Restaurar backups gerados em storage/production-dashboard-js-backups

Validacoes realizadas:
- Local testado e aprovado
- Stage testada e aprovada
- Producao testada e aprovada
- php -l nos arquivos PHP alterados
- node --check em public/assets/js/admin-dashboard.js
- git diff --check

Status:
- Implantada

Observacoes:
- O ajuste final do JS removeu min/max dinamico durante a interacao dos campos de data e manteve o clamp somente no submit.
- O dashboard de producao foi confirmado como OK apos Ctrl + F5 e teste dos filtros.
