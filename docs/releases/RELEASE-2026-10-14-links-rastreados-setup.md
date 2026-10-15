Release: RELEASE-2026-10-14-links-rastreados-setup
Data: 2026-10-14
Responsavel: Taren Felipe Ribeiro

Entram:
- Forge #1289 `[Blog Setup] - Link da B850 e links rastreados nos artigos do setup (producao)`
- Link novo da placa-mae do setup (MSI MAG B850 TOMAHAWK MAX WIFI) no grupo "Setup Estrategia Nerd" da Central Nerd
- ASUS TUF B850M-Plus saiu do grupo do setup e foi para "PC Gamer Intermediario"
- Os 6 artigos do setup passam a ter link de produto rastreado (`/link/<slug>?origem=post-<id>`) dentro do HTML do conteudo
- Forge #1290 `[Blog Setup] - Corrigir fecho do #50 (producao)`: no artigo `msi-mag-b850-tomahawk-max-wifi` (id 50 em producao), a frase "Aqui no Estrategia Nerd, cada peca e analisada pelo que entrega no uso real, nao pelo marketing." (que contradizia o texto, sem testes proprios) virou "Este texto reune a ficha oficial da MSI; testes de desempenho nao fazem parte dele." Hash do conteudo antes -> depois: f8fd9e7ffa56 -> 7c03fac54a8f. Backup: `storage/content-corrections/fecho-50-20261014/backup-production-post-50.json`. So `conteudo` e `data_atualizacao` mudaram; os links rastreados continuam

- Forge #1291 `[Hardware Motion] - Corrigir Reel #200`: vídeo B650 novo em `reel-v2.mp4`/`reel-v2.jpg`, `instagram_posts` 200 e `instagram_post_media` 131 atualizados em transação, legenda sem a B850; #200 publicado em 14/10/2026 às 17:12 (https://www.instagram.com/reel/DefNnpjCJai/)
- Documentação central: tempos de render e limite de paralelo na Documentação Técnica (seção 16), nos Procedimentos (cartão "Produção de Reels") e no `docs/features/reels-motionmaxxing.md` (seções 9 e 10)

- 15/10/2026, Forge #1293: padrão de arquivos dos Reels (nome fixo, como as capas) e troca dos vídeos novos dos Reels #202, #203, #204, #190 e #207 em `public/uploads/posts/<slug>/reels/` (versões antigas em `reels/anteriores/`, backup em `storage/reels-troca-20261015/`); Regras Permanentes (item 7), Documentação Técnica (seção 16), Procedimentos e `reels-motionmaxxing.md` (seção 11) atualizados
- 15/10/2026, IMP-034 (Forge #1306 e #1307): cache da lista de pacotes de conteúdo em `scripts/content-sync/ContentSyncManager.php`; a Central Operacional passa de ~12 s para ~79 ms (cache quente) e o `smoke-test.php` de 4 falhas para 1 (a que sobrou é o teste "Backup e Restore", desatualizado, sem relação com o cache); Documentação Técnica (seção 13) e `docs/features/IMP-034.md` atualizados
- 15/10/2026, Forge #1324 e #1325 (Fase A): backup do Estratégia Nerd copiado do E: para `C:\Users\WINDOWS\ProjectBackup\estrategia-nerd` (743 arquivos, 31.206.567.840 bytes; 109 manifestos e 6 zips com SHA-256 iguais; pasta do E: mantida intacta como cópia) e `BACKUP_ROOT` do `.env` trocado (backup do arquivo em `storage/mover-backup-20261015/`); `scripts/backup/Invoke-DailyBackup.ps1` passa a rodar o local com `--no-uploads` de segunda a sábado e completo aos domingos (dia por `DayOfWeek` numérico; teste com `-DiaDaSemana` e `-SomenteMostrarComandos`); a retenção segue em 14, e a redução para 7 depende de aprovação da lista de exclusão; Documentação Técnica (seção 13) e `scripts/README-backup.md` atualizados. Rodada real em 15/10/2026 13:07 (`backup.php run local --no-uploads`): pacote `BS-LOCAL-20261015-130719` (banco 745 KB, sistema 519 MB, sem uploads; `verify`: válido; manifesto `system_without_uploads`); a rotação normal apagou 1 pasta antiga (`BS-LOCAL-20260930-213002`, 0,19 GB); telas de Backup Sistêmico mostram o pacote sem erro
- 15/10/2026 (Fase B, `BACKUP_RETENTION` 14 para 7): `.env` ajustado (backup em `storage/mover-backup-20261015/env.antes-retencao`) e 24 das 26 pastas listadas apagadas com `backup.php delete-local` (9 locais e 9 do stage e 8 da produção, menos 2); sobraram 7 no local, 7 no stage e 6 na produção. Ficaram 2 pastas sem `manifest.json` (`BS-LOCAL-20261007-213001`, 1,13 GB, e `BS-STAGE-20260929-213128`, 76 MB), que o sistema não reconhece; removidas à mão, pelo caminho exato, com autorização do usuário (só continham `database.sql` e `uploads.zip`; a pasta antiga do E: mantém cópia). Lista usada: `storage/mover-backup-20261015/alvos-fase-b.json`
- 15/10/2026, Forge #1334, #1335 e #1336: fechamento da documentação de backup (caminho C:\Users\WINDOWS\ProjectBackup\estrategia-nerd, retenção 7 no README-backup.md, cartão Procedimentos e regra 8 das Regras Permanentes; fallbacks em config/backup.php e config/content-sync.php atualizados; padrão de Reel na seção 13 ajustado); The Forge atualizado para ler o backup no caminho novo (backupRoutes.js e rotina-backup.md); teste de fumaça "Backup e Restore" apontado para /admin/central-operacional-v2/backup-sistemico em SmokeTestService.php (smoke-test passa com 26 OK e 0 FAIL)

Ficam fora:
- Local e stage: NAO foram alterados (a producao e copiada para eles depois)
- Link da ZOTAC (RTX 3050 Twin Edge): modelo ainda a confirmar com o usuario (a placa dele e low profile)
- Codigo do sorteio de ofertas (`RelatedOffersService`) e o fecho do artigo B850 (plano a parte)

Aplicado direto em PRODUCAO, sem passar pela stage, por decisao do usuario. Feito pelo admin local com "Ambiente alvo = Producao".

Origem validada: producao (banco `u576397693_estrategianerd`), conferida por SELECT e pelas paginas publicas
Paridade local -> stage -> producao: NAO aplicavel (so producao foi alterada)

Backup necessario: sim
Backup usado: `storage/content-corrections/setup-links-20261014/backup-production.json` (fora do Git): posts 18, 19, 21, 22, 23, 50 e links 10 a 14, antes da alteracao

Banco precisa rodar script: nao (edicao pelo admin)
Mudanca de schema: nao
Mudanca de dados: sim, em producao

Dados alterados (IDs de PRODUCAO; os IDs mudam por ambiente, localizar por slug):
- `links`: novo registro `placa-mae-msi-mag-b850-tomahawk-max-wifi-ddr5-am5-atx` (id 67 em producao), URL de afiliado, grupo "Setup Estrategia Nerd", posicao 70
- `links`: `placa-mae-asus-tuf-gaming-b850m-plus-wifi-ddr5-amd-am5` (id 11 em producao): `subgrupo_publico` "Setup Estrategia Nerd" -> "PC Gamer Intermediario", `posicao` 70 -> 380; status e URL iguais
- `posts.conteudo` (hash SHA-256 antes -> depois, 12 primeiros caracteres):
  - 18 `o-setup-que-sustenta-o-estrategia-nerd-a-maquina-por-tras-de-tudo`: 87ce10fadca9 -> a6b77f392d94 (bloco novo com os 5 links das pecas)
  - 19 `amd-ryzen-5-7600x-desempenho-real-no-meu-setup`: 42a135898fe3 -> 60a75d415eeb
  - 21 `rtx-3050-6gb-a-escolha-inteligente-para-um-setup-equilibrado`: faa42ed42670 -> 5d7e23f9afb2
  - 22 `corsair-vengeance-ddr5-32gb-6000mhz-equilibrio-ideal-para-setups-modernos`: e617ed6f895c -> 92bb322e8a0e (2 links)
  - 23 `kingston-nv3-1tb-nvme-pcie-4-0-velocidade-real-para-o-dia-a-dia`: 6424fa0953a6 -> f1aa45af97e8 (2 links; o link longo do Mercado Livre saiu)
  - 50 `msi-mag-b850-tomahawk-max-wifi`: b10b5b668d2b -> f8fd9e7ffa56 (bloco novo com o link da MSI)

Achados:
- Os links do corpo dos artigos 21 e 22 eram o mesmo `meli.la/2AsQUce`, que abre uma lista da pagina de afiliado e nao um produto. Foram trocados pelos links cadastrados (ZOTAC e Corsair), conferidos no navegador
- Antes da mudanca, nenhum clique estava registrado com `origem` `post-18/19/21/22/23/50`
- O editor de posts so grava o que esta no campo oculto `conteudo`; editar o textarea sem abrir a aba HTML nao altera nada (o salvamento da a mensagem de sucesso mesmo assim)

Rotas criticas afetadas:
- /post/<slug> dos 6 artigos; /link/<slug> (redirecionamento rastreado); Central Nerd (grupos de produtos)

Conferencia feita:
- SELECT contra o backup: so `conteudo` e `data_atualizacao` mudaram nos 6 posts; nos links, so `subgrupo_publico`, `posicao` e `updated_at` do id 11; links 10, 12, 13 e 14 sem mudanca
- Paginas publicas dos 6 artigos: HTTP 200, sem nenhum `meli.la` direto no corpo
- Clique de teste em `/link/placa-mae-msi-mag-b850-tomahawk-max-wifi-ddr5-am5-atx?origem=post-50`: 302 para `https://meli.la/1JGz5kf` e 1 registro em `link_clicks` (`link_id` 67, `origem` `post-50`) — registro de teste

Rollback:
- Posts: restaurar `conteudo` a partir do backup (UPDATE guardado por slug e pelo hash "depois")
- Link 11: voltar `subgrupo_publico` para "Setup Estrategia Nerd" e `posicao` para 70
- Link da MSI: mudar o status para `oculto`
