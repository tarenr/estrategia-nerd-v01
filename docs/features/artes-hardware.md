# Artes B650 e NV3

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
