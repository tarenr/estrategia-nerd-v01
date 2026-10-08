# Correção da oferta no artigo MSI B650 — 08/10/2026

Removido somente o parágrafo com a oferta da **Asus Tuf Gaming B850M-Plus** do artigo da **MSI MAG B650 Tomahawk WiFi**. Nenhuma oferta substituta foi adicionada, conforme escolha do usuário.

Artigo: `msi-mag-b650-tomahawk-wifi-a-base-que-sustenta-um-setup-de-verdade`.

- Local: post #23.
- Produção: post #20.
- Stage: já estava sem o parágrafo; nenhuma escrita realizada nesse ambiente.

## Aplicação e preservação

Um comando PHP CLI pontual, usando o carregador de ambiente e os perfis existentes, preparou e salvou o backup dos dois registros antes de alterar dados. O parágrafo esperado precisava ocorrer exatamente uma vez em cada origem; em seguida foi retirado por correspondência literal, preservando todos os demais bytes de `conteudo`.

Em cada ambiente, foi executado um `UPDATE posts SET conteudo=?` transacional, limitado por ID, slug e comparação binária com o conteúdo anterior. A leitura antes do commit confirmou o conteúdo esperado e a preservação dos demais campos, exceto eventual timestamp automático `data_atualizacao`. Não foram modificados o catálogo de links, imagens, categorias, vínculos entre artigos ou Instagram.

Backup local, fora do Git: `storage/content-corrections/b650-offer-20261008-160027/backup.json`. Evidências de IDs, SHA-256 antes/depois e preservação dos demais campos: `verified.json`, na mesma pasta. Não publicar esses arquivos. Não foi necessário rollback; uma restauração deve usar somente o conteúdo anterior do artigo e recusar sobrescrever edições posteriores.

## Validação

- Local e produção: um parágrafo removido em cada registro; restante do conteúdo preservado por comparação exata.
- [Página de produção](https://estrategianerd.com.br/post/msi-mag-b650-tomahawk-wifi-a-base-que-sustenta-um-setup-de-verdade): HTTP 200, link e texto da oferta ausentes, chamada final do artigo presente.
- Suíte obrigatória `scripts/verify-changes.php`: **17/17**, zero falhas; PHPStan nível 5 aprovado.
- A requisição pública segue o contador normal de visualizações do site.

Forge: correção registrada na tarefa existente #889. Essa tarefa permanece pendente por incluir a promoção de conteúdo para produção, que aguarda plano revisado e aprovação próprios. A presente entrega não promoveu o pacote de imagens de stage.

O commit desta entrega registra a documentação; a correção editorial foi aplicada ao banco. Uma atualização de código via Git, isoladamente, não reaplica essa alteração de dados.
