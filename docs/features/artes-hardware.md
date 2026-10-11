# Artes B650 e NV3

## Review independente do Ryzen 7 5700X — produção, 11/10/2026

Capa do artigo de produção 43 corrigida para o padrão existente: cenário tecnológico escuro com iluminação ciano/dourada e superfície reflexiva, produto inteiro, sem títulos sobrepostos, 1200×800. Asset: resources/reels-review/assets/5700x-cenario-v1.png. Composição criada pelo imagegen a partir da foto real do 5700X por Qurren (https://commons.wikimedia.org/wiki/File:AMD_Ryzen_7_5700X_1.jpg), adaptada sob CC BY-SA 4.0. Crédito, licença e descrição da adaptação aparecem no próprio artigo. Referência de estilo: capa da B650; prompt preserva o formato AM4, inscrição do modelo e identidade do produto. Não usar inscrições pequenas como fonte técnica.

O título, resumo, conteúdo, metadados e tipo de post foram corrigidos para review independente. Removidas as alegações de uso no setup do autor e os convites à série. Campo proximo_post_id definido como 32 (AM4 ou AM5 em 2026), com link coerente no conteúdo. Endereço e status publicado preservados. Não havia vínculos de entrada apontando para 43; artigos 18–23 da série e o artigo do 7600X preservados. O botão inferior “Próximo post” agora respeita a leitura configurada para reviews: 7600X (19) → B650 (20), e 5700X (43) → AM4 ou AM5 (32). Sem destino publicado válido, mantém a ordem cronológica; a navegação anterior e os demais tipos continuam cronológicos.

Correção de navegação: PostRepository::findPublicBySlug carrega tipo_post com fallback para esquemas sem a coluna; PostService reutiliza o próximo passo publicado, rejeitando autorreferência. Teste scripts/verify-review-navigation.php: 13 verificações aprovadas, incluindo rascunho, destino inexistente, autorreferência, tipos diferentes e sequência dos dois artigos em produção; alterações de teste revertidas por rollback. Suíte oficial: 17 OK, 0 falhas. Deploy restrito aos dois arquivos, preparado sobre as cópias remotas para preservar diferenças alheias ao escopo. Backup, payload e hashes em storage/review-navigation-20261011; scripts/review-navigation-deploy.php oferece --backup, --deploy e --verify. Não repetir --backup após o deploy: o backup original é imutável. Recuperação mediante autorização: restaurar somente esses dois arquivos do backup, após conferir alterações concorrentes. Páginas públicas HTTP 200 com os destinos dos botões conferidos e hashes remotos iguais ao payload.

Aplicação: scripts/reels-review/review-5700x-production.php --backup/--apply/--verify, usando PostRepository::updateAdmin e TargetEnvironmentUploads::putFile. Patch: resources/reels-review/review-5700x-20261011.json. Backup de registros e imagens anteriores em storage/editorial-5700x-20261011; validação no mesmo diretório. Reaplicar é idempotente; divergências concorrentes interrompem a operação. Recuperação: usar o registro 43 e as referências de imagem salvos no backup através do editor/repositório oficial, mediante autorização, preservando os arquivos novos e antigos.

Validação: página pública e nova imagem HTTP 200, imagem remota idêntica por SHA-256, conteúdo e próximo passo conferidos na interface; comparação dos campos com backup confirma preservação dos outros artigos. PHP lint aprovado e suíte obrigatória com 17 OK, 0 falhas. Sem alterações no banco local de posts, no Instagram ou nos vídeos.

## Escopo local aprovado — 8 de outubro de 2026

Capas dos artigos MSI MAG B650 TOMAHAWK WIFI (post local 23) e Kingston NV3 1 TB (post local 26). Artes aprovadas pelo usuário, geradas com imagegen a partir dos produtos oficiais e do estilo dos outros itens: cenário tecnológico escuro, iluminação ciano/dourada, superfície reflexiva, produto inteiro e sem títulos sobrepostos. Dimensões finais 1200 × 800, sem recorte.

Assets versionados: `resources/reels-review/assets/b650-cenario-v1.png` e `nv3-cenario-v1.png`. As fotos oficiais anteriores são preservadas. As artes geradas são ilustrações de produto; inscrições pequenas não devem servir como referência técnica.

## Aplicação e recuperação

`php scripts/reels-review/hardware-blog-images.php --apply` usa exclusivamente o perfil local. Antes de alterar `imagem_capa`, `imagem_thumb` e referências da capa dentro de `conteudo`, salva os dois registros e hashes dos originais em `storage/previews/reels-review/hardware-samples-20261008/blog-backup.json`. Copia novas imagens com nomes derivados do SHA-256, sem sobrescrever anteriores. Atualização transacional com proteção contra mudanças concorrentes nos campos afetados e verificação dos outros campos.

`--verify` verifica referências, hashes e preservação das capas anteriores. `--rollback` recupera os três campos anteriores, sem excluir arquivos. Não executa sincronização remota.

Roteiros 200 e 203 usam imagem inteira (`contain`) e imóvel (`still`), com animação nos textos. Revisões anteriores de vídeo e capas permanecem disponíveis no manifesto. A galeria local é `uploads/previews/revisao-reels-20261007/index.html`.

## Fora do escopo

Hostinger, stage, produção, fila de publicação e Instagram não são alterados. A tarefa remota 889 continua pendente, inclusive pela ausência do artigo NV3 em stage. Alterações preexistentes no repositório não integram este commit.

## Validação final

- Aplicação e verificação dos dois artigos locais: hashes e referências conferidos, originais preservados; páginas e novas imagens HTTP 200.
- Reels 200 e 203: revisão v4, 28 segundos, capas e galeria atualizadas; folhas de contato examinadas visualmente.
- `verify.mjs`: 30 tratamentos aprovados, incluindo imagem anterior ao texto e limites sem cortes a cada 0,5 segundo nos vídeos com `contain/still`.
- `batch.mjs --verify`: 30 vídeos completos, áudio audível e hashes dos vídeos originais preservados.
- Sintaxe do novo PHP e `git diff --check`: aprovados.
- Suíte obrigatória `scripts/verify-changes.php`: 17 testes aprovados, zero falhas, incluindo PHPStan e renderização dos três ambientes.

Prompts das amostras (ferramenta integrada imagegen): integrar o produto oficial B650 ou NV3 em estúdio tecnológico escuro, iluminação ciano e dourada e plataforma reflexiva; preservar produto inteiro e identidade, sem legendas ou banners, composição 3:2. Saída gerada 1536 × 1024 redimensionada proporcionalmente a 1200 × 800, sem recorte.
