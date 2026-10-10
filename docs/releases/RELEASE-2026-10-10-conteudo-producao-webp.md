# Sincronização de Conteúdo e Capas WebP — Produção, 10/10/2026

Entrega aplicada com sucesso no ambiente de [Produção](https://estrategianerd.com.br/blog) a partir do pacote oficial de homologação Stage.

## Resumo da Entrega
- **Backup Preventivo:** `BS-PROD-20261010-003348` (verificado e íntegro em disco)
- **Pacote Aplicado:** `PC-STAGE-20261010-003619`
- **Origem:** Stage / Homologação remota (Hostinger)
- **Destino:** Produção oficial (Hostinger)
- **Status:** Sucesso (aplicado com `--force`)

## Escopo Aplicado em Produção

### 1. Artigos e Capas WebP
Todos os artigos do blog foram sincronizados para a Produção, com atualização das capas para o formato otimizado WebP e inclusão dos artigos recentes de cultura pop/games:

| Artigo / Slug | ID Prod | Capa em Produção | Formato e Peso |
|---|---:|---|---|
| `12-jogos-leves-pc-fraco-ou-modesto` | 36 | `uploads/posts/12-jogos-leves-pc-fraco-ou-modesto/images/capa.webp` | WebP (177 KB) |
| `historia-counter-strike` | 37 | `uploads/posts/historia-counter-strike/images/capa.webp` | WebP (195 KB) |
| `10-filmes-nerds-essenciais` | 38 | `uploads/posts/10-filmes-nerds-essenciais/images/capa.webp` | WebP (177 KB) |
| `animes-essenciais-para-iniciantes` | 39 | `uploads/posts/animes-essenciais-para-iniciantes/images/capa.webp` | WebP (227 KB) |
| `desenhos-anos-90-2000-que-envelheceram-bem` | 40 | `uploads/posts/desenhos-anos-90-2000-que-envelheceram-bem/images/capa.webp` | WebP (145 KB) |
| `amd-ryzen-5-7600x-desempenho-real-no-meu-setup` | 19 | `uploads/posts/amd-ryzen-5-7600x-desempenho-real-no-meu-setup/images/capa.webp` | WebP (142 KB) |
| `amd-ryzen-7-5700x-desempenho-real-no-meu-setup` | 43 | `uploads/posts/amd-ryzen-7-5700x-desempenho-real-no-meu-setup/images/capa.webp` | WebP (142 KB) |
| `rtx-5060-vs-rx-9060-xt-1080p` | 31 | `uploads/posts/rtx-5060-vs-rx-9060-xt-1080p/images/capa.webp` | WebP (128 KB) |
| `am4-ou-am5-em-2026` | 32 | `uploads/posts/am4-ou-am5-em-2026/images/capa.webp` | WebP (147 KB) |
| `como-escolher-fonte-para-pc` | 33 | `uploads/posts/como-escolher-fonte-para-pc/images/capa.webp` | WebP (153 KB) |
| `como-verificar-saude-ssd-hd` | 34 | `uploads/posts/como-verificar-saude-ssd-hd/images/capa.webp` | WebP (134 KB) |
| `temperatura-cpu-gpu-quando-se-preocupar` | 35 | `uploads/posts/temperatura-cpu-gpu-quando-se-preocupar/images/capa.webp` | WebP (124 KB) |

Total de posts no banco de Produção: **33 posts** (sendo 30 com capas WebP otimizadas).

### 2. Otimização de Performance
- Arquivos `.webp` transferidos para `domains/estrategianerd.com.br/public_html/uploads`.
- Redução de consumo de banda superior a **90%** nas capas dos artigos (de ~2 MB para ~180 KB cada).

### 3. Melhoria Técnica no ContentSyncManager
- Prevenção de colisão de posts no mapeamento por histórico (`findTargetPost`):
  - Rastreamento dos IDs de destino já reivindicados na rodada (`$claimedTargetIds`).
  - Desconsideração de correspondência histórica quando o slug atual do post de destino coincidir com o slug direto de outro post pertencente ao mesmo lote de sincronização.
  - Limpeza de redirecionamentos obsoletos na tabela `post_slug_history` para evitar desvios indevidos de rota.

## Validação Realizada
- **Backup Preventivo:** `BS-PROD-20261010-003348` gerado e conferido.
- **Validação de Pacote:** `PC-STAGE-20261010-003619` validado com `verify latest`.
- **Integridade Pós-Aplicação:** Sucesso no assert de integridade de slugs em produção.
- **Suíte de Testes do Projeto:** **17 OK | 0 FALHAS** em `scripts/verify-changes.php`.
- **Dashboard Multi-Ambiente:** Validado com sucesso para `production` (83 KB | KPI: Sim | Painel: Sim).
