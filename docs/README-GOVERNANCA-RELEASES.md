# Estrategia Nerd - Governanca Oficial de Features e Releases

Este conjunto de documentos define o modelo oficial EN para controlar:

- feature
- correcao
- melhoria
- hotfix
- release
- checklist
- rollback

## Estrutura oficial

```text
/docs
  /features
  /releases
  /checklists
  /rollback
  /templates
```

## Regras de uso

1. Toda alteracao relevante recebe um ID.
2. Toda alteracao relevante gera documento proprio em `/docs/features`.
3. Toda publicacao planejada gera documento proprio em `/docs/releases`.
4. Toda release deve apontar:
   - o que entra
   - o que fica fora
   - backup exigido
   - mudanca de banco
   - origem validada
   - paridade entre ambientes
5. Toda publicacao deve usar checklist pre e pos deploy.
6. Todo rollback deve ter roteiro objetivo.

## Prefixos oficiais

```text
FEAT-001  = nova funcionalidade
FIX-001   = correcao
IMP-001   = melhoria
HOT-001   = hotfix urgente
```

## Branches recomendadas

```text
codex/feat-FEAT-014-campo-resumo
codex/fix-FIX-009-login
codex/hot-HOT-001-erro-producao
```

## Regra de ouro

Nada sobe sem:

- ID
- documentacao minima
- teste minimo
- impacto conhecido
- origem validada
- paridade confirmada

## Observacao critica

No EN, pacote de producao nao deve nascer do ambiente local.

Fluxo correto:

```text
local -> stage -> validacao -> pacote da stage -> producao
```
