# Sincronização de Conteúdo e Capas WebP — Stage, 10/10/2026

Entrega aplicada exclusivamente no ambiente de homologação [Stage](https://estrategianerd.com.br/stage/blog) a partir do ambiente local.
Produção e fila de agendamento do Instagram permanecem isolados e inalterados.

## Resumo da Entrega
- **Pacote aplicado:** `PC-LOCAL-20261010-001601`
- **Origem:** Local / Homologação
- **Destino:** Stage / Homologação remota (Hostinger)
- **Status:** Sucesso (aplicado com `--force`)

## Escopo Aplicado em Stage

### 1. Artigos e Capas WebP
Todos os artigos do blog foram sincronizados para o Stage, com destaque para a inclusão dos artigos recentes de cultura pop/games e a aplicação das novas capas otimizadas em WebP:

| Artigo / Slug | ID Stage | Capa em Stage | Formato e Peso |
|---|---:|---|---|
| `12-jogos-leves-pc-fraco-ou-modesto` | 39 | `uploads/posts/12-jogos-leves-pc-fraco-ou-modesto/images/capa.webp` | WebP (177 KB) |
| `historia-counter-strike` | 40 | `uploads/posts/historia-counter-strike/images/capa.webp` | WebP (195 KB) |
| `10-filmes-nerds-essenciais` | 41 | `uploads/posts/10-filmes-nerds-essenciais/images/capa.webp` | WebP (177 KB) |
| `animes-essenciais-para-iniciantes` | 42 | `uploads/posts/animes-essenciais-para-iniciantes/images/capa.webp` | WebP (227 KB) |
| `desenhos-anos-90-2000-que-envelheceram-bem` | 43 | `uploads/posts/desenhos-anos-90-2000-que-envelheceram-bem/images/capa.webp` | WebP (145 KB) |
| `rtx-5060-vs-rx-9060-xt-1080p` | 34 | `uploads/posts/rtx-5060-vs-rx-9060-xt-1080p/images/capa.webp` | WebP (128 KB) |
| `am4-ou-am5-em-2026` | 35 | `uploads/posts/am4-ou-am5-em-2026/images/capa.webp` | WebP (147 KB) |
| `como-escolher-fonte-para-pc` | 36 | `uploads/posts/como-escolher-fonte-para-pc/images/capa.webp` | WebP (153 KB) |
| `como-verificar-saude-ssd-hd` | 37 | `uploads/posts/como-verificar-saude-ssd-hd/images/capa.webp` | WebP (134 KB) |
| `temperatura-cpu-gpu-quando-se-preocupar` | 38 | `uploads/posts/temperatura-cpu-gpu-quando-se-preocupar/images/capa.webp` | WebP (124 KB) |

Total de posts no banco de Stage após a aplicação: **32 posts**.

### 2. Otimização de Rede
- Os arquivos `.webp` foram enviados via FTP para `domains/estrategianerd.com.br/public_html/stage/uploads`.
- A redução de peso nas 14 capas atingiu **92%** (de 26,36 MB em PNG para 2,08 MB em WebP).

### 3. Ajuste Técnico no ContentSyncManager
- Alinhada a verificação de integridade de encoding de `scripts/content-sync/ContentSyncManager.php` com o padrão oficial de `scripts/preflight-encoding.php`.
- O regex anterior (`/\x{00C3}./u`) gerava falso-positivo para palavras legítimas da língua portuguesa em caixa alta como `NÃO` ou `BOTÃO`. Agora utiliza a verificação estrita por bytes de continuação UTF-8 (`[\x{0080}-\x{00BF}...]`), aceitando caracteres acentuados maiúsculos legítimos.

## Validação Realizada
- **Preflight:** Aprovado (`scripts/preflight-check.php`).
- **Verificação do Pacote:** `is_valid: true` para `PC-LOCAL-20261010-001601`.
- **Banco de Dados de Stage:** Conferido via PDO remoto, 32 posts com capas WebP ativas.
- **Suíte de Testes do Projeto:** **17 OK | 0 FALHAS** em `scripts/verify-changes.php`.
