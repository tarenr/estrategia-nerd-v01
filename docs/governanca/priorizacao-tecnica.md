# Priorização Técnica

## Objetivo
Definir criterios objetivos para priorizacao de demandas tecnicas, reducao de risco operacional e evolucao sustentavel do projeto.

## Criterios de Prioridade
Itens tecnicos devem ser priorizados conforme impacto em:

1. Seguranca
2. Disponibilidade
3. Integridade de dados
4. Capacidade de deploy e rollback
5. Manutenibilidade
6. Evolucao funcional

## Criticos Imediatos
- Seguranca de rotas administrativas e locais
- Protecao de segredos e variaveis sensiveis
- Upload seguro e bloqueio de execucao em `uploads`
- Cobertura de CSRF em rotas `POST`
- Validacao de variaveis por ambiente

## Curto Prazo
- Fluxo real aderente a documentacao
- Backup e restore validados
- Origem correta de pacote para producao
- Promocao entre ambientes controlada
- Testes automatizados minimos
- Logs e rastreabilidade operacional
- Historico de pacote por ambiente

## Estruturais
- Clareza entre rotas e handlers reais
- Separacao entre camadas
- Reducao de logica em view e controller
- Padronizacao de nomenclatura
- Robustez do fluxo editorial
- Validacao de slug
- Vinculos de midia
- Pre-validacoes antes de publicar

## Revisao
Revisao mensal ou apos incidentes relevantes.

## Responsaveis e Evidencias
Toda entrega critica deve registrar:

- responsável técnico
- ambiente validado
- registro técnico da alteração
- resultado dos testes
- impacto esperado
- origem do pacote, quando aplicável
- plano de rollback, quando aplicável
