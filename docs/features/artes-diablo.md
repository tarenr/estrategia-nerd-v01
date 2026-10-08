# Artes Diablo — aplicação local e prévias

## Entrega de 08/10/2026

O usuário aprovou o estilo de ilustrações em pergaminho sobre mesa de madeira.
O lote usa 16 arquivos distintos, todos com **1672 × 941 pixels**, para os
Reels 191, 192, 193, 194, 195 e 197. A ilustração dos Nefalem é reutilizada
nos dois artigos que já compartilhavam o desenho. Os retratos de Diablo,
Mephisto e Baal foram preservados e acomodados no mesmo tamanho sem cortes.

As demais composições aprovadas foram produzidas pela ferramenta integrada
de geração de imagens. As versões finais em WebP, o fundo da mesa e o
manifesto de hashes ficam em `resources/reels-review/assets/diablo/`.
Não há dependência de serviço pago para renderizar os vídeos.

### Mapa: composição, sem reescrever nomes

As tentativas de redesenhar o mapa com IA alteraram legendas pequenas.
Esses rascunhos não são usados. O mapa final combina a mesa gerada com o
mapa original, reduzido proporcionalmente e inteiro, por `skia-canvas`.
Nenhum texto, costa ou localização é redesenhado. A fonte original é
preservada em `mapa-original.webp`; seu SHA256 consta do manifesto.
O processo de preparação está em `scripts/reels-review/diablo-artwork.mjs`.
Ele requer o inventário e as prévias locais desta sessão e não sobrescreve
arquivos existentes. Um novo lote exige nomes versionados.

## Blog local

Somente os artigos locais 11, 12, 13, 14, 16 e 20 foram modificados,
identificados por slug, nunca pelos IDs de Instagram. A tabela `posts`
recebe mudanças em `conteudo` (somente URLs de imagens), `imagem_capa`
e `imagem_thumb`. A data de atualização é mantida automaticamente pelo MySQL.
As seis capas e miniaturas usam as artes aprovadas; as capas dos artigos
"O mundo de Diablo" e "A lore completa" usam suas ilustrações de abertura.

Arquivos anteriores permanecem intactos. Os novos nomes incluem a chave
da arte e um prefixo do SHA256, evitando sobrescrita e cache antigo.
URLs absolutas de produção das imagens substituídas foram convertidas em
caminhos relativos, que o site resolve para o ambiente local.
Textos, títulos, SEO, status e demais referências não são modificados.

`scripts/reels-review/diablo-blog-images.php` opera exclusivamente no banco
local configurado pelo projeto. Modos:

```powershell
C:/xampp/php/php.exe scripts/reels-review/diablo-blog-images.php --inspect
C:/xampp/php/php.exe scripts/reels-review/diablo-blog-images.php --apply
C:/xampp/php/php.exe scripts/reels-review/diablo-blog-images.php --verify
C:/xampp/php/php.exe scripts/reels-review/diablo-blog-images.php --rollback
```

`--inspect` cria backup exclusivo em
`storage/previews/diablo-artes-20261008/blog-local-backup.json`.
`--apply` e `--rollback` usam transação e rejeitam mudanças inesperadas no
conteúdo/referências dos artigos. Contadores de visualização e outros
campos atuais são preservados. `--rollback` repõe apenas os três campos
afetados; não exclui arquivos. O rollback foi exercitado durante a
validação da aplicação, antes da versão final das referências.
`--verify` pode ser repetido e registra evidência versionada local.

## Reels e galeria

Os storyboards dos seis posts apontam para os assets versionados via
`@diablo:`. O enquadramento `contain` com `motion: still` mantém a imagem
inteira dentro da moldura em todo o vídeo, sem zoom que corte as bordas.
A entrada da arte, as transições entre imagens diferentes, textos e
partículas continuam animados. Uma imagem repetida permanece na mesma
tomada durante a troca de texto. Histórias não recebem capítulos numerados.
Trilhas e roteiros aprovados foram preservados.

Versões: 191–195 em v3; 197 em v4. Os seis MP4 têm 28 s, 1080 × 1920,
30 fps, H.264/yuv420p e AAC. Capas e versões anteriores ficam preservadas.
São prévias: não modificam a fila do Instagram nem publicam posts.

[Galeria pelo túnel](https://nerd.tfr-info.com.br/uploads/previews/revisao-reels-20261007/index.html?v=artesdiablo1)
— selecionar **Diablo** para ver os seis vídeos.

## Validação

- 16 assets decodificados; dimensões e SHA256 conferidos, fonte do mapa intacta.
- Referências dos seis artigos verificadas em transação; outros artigos preservados.
- Seis páginas locais e suas novas imagens respondem HTTP 200.
- 30 tratamentos editoriais verificados: leitura, imagem antes do texto,
  continuidade de imagem única e animação determinística. Nos seis Diablo,
  toda a arte permanece dentro da moldura em amostras a cada 0,5 s.
- 30 MP4 da galeria decodificados, áudio audível e originais preservados.
- Galeria no navegador em 390 × 844: filtro Diablo e reprodução do novo
  vídeo 191 (28 s, readyState 4, sem erro), sem excesso de largura.
- Suíte obrigatória do projeto: **17 OK, 0 falhas** (incluindo PHPStan e
  renderização dos painéis nos três ambientes).

Comandos de validação:

```powershell
node scripts/reels-review/verify.mjs --run revisao-reels-20261007
node scripts/reels-review/batch.mjs --run revisao-reels-20261007 --verify
C:/xampp/php/php.exe scripts/verify-changes.php
```

## Limites e próxima revisão

Hostinger, stage, produção e Instagram não foram alterados nesta etapa.
As artes e scripts são versionados; MP4 e backups permanecem no armazenamento
local de uploads/storage, conforme a política do repositório.

A galeria aprovada continha as imagens usadas nos seis vídeos, não todas
as imagens internas dos artigos. Permanecem para uma revisão específica:

- Arcanjos: imagens 03 e 04.
- Males do Inferno: imagens 04, 06, 07 e 08.
- Lore completa: img-009, img-010, img-012 e img.webp.

Essas dez imagens não foram substituídas por cenas diferentes apenas para
uniformizar o visual. A revisão deve preservar o personagem e o contexto
de cada imagem antes de gerar novas artes.
