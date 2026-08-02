# FEAT-004 - Pagina Hostinger API para Estrategia Nerd

## Objetivo

Criar uma pagina administrativa de leitura para consolidar informacoes da API da Hostinger referentes somente ao dominio `estrategianerd.com.br`.

## Contexto

O projeto passou a consultar dados da Hostinger API para inventario operacional de dominio, DNS, hospedagem, bancos, assinaturas e snapshots. A tela deve ser somente leitura e nao deve permitir alteracoes em DNS, dominio, hosting, billing ou forwarding.

## Arquivos Alterados

- `config/hostinger-api.php`
- `config/routes.php`
- `app/Controllers/Admin/HostingerApiController.php`
- `app/Services/Site/HostingerApiService.php`
- `app/Views/admin/hostinger-api/index.php`
- `app/Views/components/admin/sidebar.php`
- `.env.example`
- `docs/features/FEAT-004.md`

## Impacto Em Producao

Baixo. A mudanca adiciona uma rota autenticada no admin e uma chamada externa de leitura quando a tela e acessada.

## Afeta Rotas Criticas

Nao.

## Mudanca De Schema

Nao.

## Mudanca De Dados

Nao.

## Dependencias

- `HOSTINGER_API_TOKEN` configurado no `.env` do ambiente onde a tela for usada.
- Extensao PHP cURL habilitada.
- Acesso de rede para `https://developers.hostinger.com`.

## Risco

Baixo a medio. A tela depende de API externa e pode ficar parcialmente indisponivel se a Hostinger API falhar ou se o token estiver ausente. O service trata erro sem derrubar o admin.

## Validacao Minima

- `php -l` nos arquivos PHP novos/alterados.
- `git diff --check`.
- Acessar `/admin/central-operacional-v2/hostinger-api` em ambiente local autenticado.
- Confirmar que a tela mostra alerta tratado quando `HOSTINGER_API_TOKEN` esta ausente.
- Confirmar que a tela nao exibe o token.

## Status Stage/Producao

- Origem validada: nao validada.
- Paridade local -> stage: nao validada.
- Paridade stage -> pacote: nao validada.
- Producao: nao publicado.
