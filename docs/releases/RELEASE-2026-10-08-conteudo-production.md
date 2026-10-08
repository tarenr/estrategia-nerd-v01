# Conteúdo consolidado — produção, 08/10/2026

Dez artigos revisados e publicados em [produção](https://estrategianerd.com.br/blog), após homologação em stage e aprovação do plano completo. **Produção foi a base editorial**; a entrega incorporou as artes aprovadas e correções pontuais, sem copiar integralmente o conteúdo anterior de stage sobre os artigos públicos.

## Artigos e alterações

| Artigo | ID produção | ID stage | Alteração em produção |
|---|---:|---:|---|
| Guerra Eterna | 15 | 15 | Referências das artes, capa e thumbnail |
| Lore de Diablo | 17 | 17 | Referências das artes, capa e thumbnail |
| Mundo de Diablo | 11 | 11 | Referências das artes, capa e thumbnail |
| Arcanjos | 13 | 13 | Referências das artes, capa e thumbnail |
| Males do Inferno | 12 | 12 | Referências das artes, capa e thumbnail |
| Nefalem | 14 | 14 | Capa e thumbnail |
| MSI MAG B650 Tomahawk | 20 | 21 | Capa e thumbnail |
| Kingston NV3 | 23 | 25 | Capa e thumbnail |
| The Witcher 3 | 29 | 26 | Trechos editoriais, título, resumo e SEO |
| Windows 11 24H2 | 30 | 27 | Orientação de atualização e descrição SEO |

Os textos dos oito artigos de Diablo e hardware foram preservados, inclusive a narração e a remoção anterior da oferta incorreta da B650. IDs, slugs, autoria, categorias, datas de publicação, estados, `tipo_post`, vínculos de continuidade e demais campos de produção permaneceram intactos. Todos os dez continuam publicados. Requisições públicas seguem a contagem normal de visualizações.

Witcher recebeu correções por correspondência de parágrafos e títulos de seção: lançamento já ocorrido em 29/09/2026, condições do upgrade dentro do mesmo ecossistema, tempos verbais e fontes oficiais. Demais seções e trailer foram mantidos. Fontes: [anúncio de disponibilidade](https://www.thewitcher.com/es/en/news/52054/the-witcher-3-the-wild-hunt-remastered-is-available-now) e [notas e condições do upgrade](https://www.thewitcher.com/es/en/news/52041/see-whats-new-in-the-witcher-3-wild-hunt-remastered).

Windows preservou título, resumo, alerta antes de 13/10/2026 e tabela de prazos por edição. A orientação passou a recomendar uma versão com suporte oferecida pelo Windows Update, mencionando a distribuição gradual da 26H2 para dispositivos elegíveis e a continuidade do suporte da 25H2, sem forçar uma atualização não oferecida ao PC. Fontes consultadas: [informações de versões](https://learn.microsoft.com/en-us/windows/release-health/windows11-release-information) e [distribuição e compatibilidade](https://learn.microsoft.com/en-us/windows/release-health/status-windows-11-25h2).

## Homologação e arquivos

Stage recebeu a versão consolidada dos dez artigos, baseada em produção. Categoria e autoria foram mapeadas por slug/nome, e a continuidade por slug do artigo de destino. Vínculos para artigos ausentes em stage ficaram nulos; os vínculos originais de produção foram preservados. Windows continua rascunho em stage e publicado em produção. Nenhum artigo foi criado ou excluído.

As artes de **Diablo** e **Malthael**, aprovadas e disponíveis localmente mas ausentes do pacote anterior de stage, foram incluídas na homologação no tamanho **1672×941**. O conjunto final de transferência contém **21 arquivos de artes de Diablo e hardware**, com hashes conferidos nos dois ambientes.

A primeira composição listava 23 arquivos porque incluía também as capas existentes de Windows e Witcher. A proteção de SHA-256 interrompeu a primeira tentativa de promoção ao encontrar uma capa de Windows diferente em produção, antes de qualquer atualização de artigos nesse ambiente. Essas duas capas não eram artes refeitas desta entrega: foram retiradas da transferência, mantendo os bytes existentes de cada ambiente e verificando-os contra seus próprios backups. Não houve sobrescrita nem criação de capa substituta.

Também foram resolvidas diferenças legadas de diretório: alguns originais ficam em `images/` na produção e diretamente na pasta do post em stage. As referências de stage foram mapeadas aos próprios originais existentes, sem alterar os caminhos corretos da produção ou copiar imagens adicionais não revisadas.

## Publicador, backups e proteção

Publicador pontual: `scripts/reels-review/production-content.php`. Ele usa somente stage/produção, exige os dez slugs dos manifestos aprovados e verifica as raízes FTP. Não envia código para a hospedagem.

Sequência executada: `--prepare`, `--apply-stage`, `--stage-paths`, `--apply-stage`, `--retain-existing-covers`, `--verify-stage`, `--apply-production`. A promoção exige evidência de stage, reconfere seus campos e baixa de stage as artes que serão enviadas, validando os hashes.

Evidências locais fora do Git: `storage/content-production/merged-20261008/`.

- `before.json`: estado anterior completo dos posts e categorias dos dois ambientes.
- `package.json`, `package-v2.json`, `package-v3.json`: composições preservadas; **v3 é o pacote final**, com aliases de stage e capas temporais preservadas.
- `before-*.bin`: 78 arquivos originais copiados para backup.
- `sent-*.bin`, `stage-source-*.bin`, `check-*.bin`, `old-check-*.bin`: conferências de origem, destino e originais.
- `verified-stage.json`, `verified-production.json`: integridade do pacote e preservação dos dados restantes.
- `validate-http.ps1`, `http-stage.json`, `http-production.json`: validação HTTP e resultados.

Não publicar ou versionar snapshots e backups editoriais. Arquivos novos usam caminhos próprios; divergências de arquivos existentes são recusadas. Atualizações são transacionais e limitadas aos campos que mudaram, com comparação do estado editorial anterior sob bloqueio. Artigos fora do escopo, quantidade de registros e categorias foram conferidos sem alteração.

Nenhum rollback foi necessário. Em uma recuperação autorizada, restaurar exclusivamente os campos desta entrega usando `before.json`, exigindo que os valores atuais ainda correspondam ao pacote aplicado. Recusar sobrescrever edições posteriores; preservar arquivos originais e não executar exclusões ou restaurações gerais.

## Validações e conclusão

- Banco: dez artigos conferidos em cada ambiente, demais dados de posts e categorias preservados.
- Artes: 21/21 SHA-256 corretos em stage e produção; originais preservados contra seus backups.
- Conteúdo: comparação independente confirmou texto preservado nos oito artigos de Diablo/hardware e tabela de prazos do Windows intacta.
- Stage: nove páginas HTTP 200 e Windows HTTP 404 esperado; 29 imagens acessíveis e 12 links entre posts válidos.
- Produção: dez páginas HTTP 200; 40 imagens acessíveis e 23 links entre posts válidos. Cada página tem canonical do próprio ambiente, OpenGraph com a capa correta, um `Article` e um `BreadcrumbList`. Nenhuma referência a stage/localhost, blocos de teste ou oferta incorreta no HTML de produção.
- Navegador real a 390/1280 px: Diablo, B650 e NV3 conferidos, sem overflow horizontal. Capas preservam proporção; imagens internas de Diablo utilizam `object-fit: contain` na produção. A área reservada das imagens internas pode ter altura maior que a própria arte; o conteúdo não foi recortado.
- Lint do publicador aprovado. Suíte obrigatória: **17/17**, PHPStan nível 5 sem erros, antes da promoção e após a entrega.

Forge: tarefa principal #889 e subtarefas #911–914. A revisão de outras artes de Diablo permanece fora deste pacote, na tarefa #898. Instagram, agenda, músicas, vídeos, catálogo de ofertas, infraestrutura e código remoto não foram alterados. Alterações locais anteriores foram preservadas e não entram no commit desta entrega.

O commit registra o publicador e esta documentação; a publicação editorial já foi aplicada aos bancos e uploads. Apenas atualizar o checkout pelo Git não reproduz a operação de dados.
